<?php

if (!defined('ABSPATH')) {
	exit;
}

class AAC_Member_Portal_Member_Database {
	const PAGE_SLUG = 'aac-member-portal-member-database';
	const EXPORT_PAGE_SLUG = 'aac-member-portal-member-database-export';
	const SCHEMA_VERSION = '1.7.0';
	const SCHEMA_OPTION = 'aac_member_portal_member_db_schema_version';
	const EXPORT_FIELDS_OPTION = 'aac_member_portal_member_database_export_fields';
	private static $instance;
	public static function get_instance() {
		return self::$instance ?: new self();
	}
	private $member_export;
	private $pending_member_syncs = [];
	private $is_flushing_member_syncs = false;

	public function __construct() {
		self::$instance = $this;
		require_once __DIR__ . '/class-aac-member-portal-member-export.php';
		$this->member_export = new AAC_Member_Portal_Member_Export($this);
		add_action('admin_menu', [$this, 'register_admin_page']);
		add_action('admin_post_aac_member_portal_edit_member', [$this, 'handle_edit_member']);
		add_action('init', [$this, 'maybe_install_schema']);
		add_action('admin_post_aac_member_portal_export_member_database', [$this, 'handle_export_member_database']);
		add_action('admin_post_aac_member_portal_save_member_export_fields', [$this, 'handle_save_member_export_fields']);
		add_action('profile_update', [$this, 'queue_member_sync'], 30, 1);
		add_action('personal_options_update', [$this, 'queue_member_sync'], 100, 1);
		add_action('edit_user_profile_update', [$this, 'queue_member_sync'], 100, 1);
		add_action('aac_member_portal_member_registered', [$this, 'queue_member_sync'], 30, 1);
		add_action('aac_member_portal_profile_updated', [$this, 'sync_member_after_profile_update'], 30, 1);
		add_action('aac_member_portal_family_account_linked', [$this, 'queue_family_member_syncs'], 30, 2);
		add_action('wp_login', [$this, 'record_member_login'], 30, 2);
		add_action('aac_member_portal_member_logged_in', [$this, 'record_portal_member_login'], 30, 1);
		add_action('added_user_meta', [$this, 'queue_member_sync_after_meta_change'], 100, 4);
		add_action('updated_user_meta', [$this, 'queue_member_sync_after_meta_change'], 100, 4);
		add_action('deleted_user_meta', [$this, 'queue_member_sync_after_meta_change'], 100, 4);
		add_action('pmpro_after_checkout', [$this, 'queue_member_sync_after_checkout'], 40, 2);
		add_action('pmpro_after_change_membership_level', [$this, 'queue_member_sync_after_level_change'], 40, 2);
		add_action('pmpro_updated_order', [$this, 'queue_member_sync_after_order_update'], 40, 1);
		add_action('shutdown', [$this, 'flush_queued_member_syncs'], 20);
		add_action('deleted_user', [$this, 'delete_member_by_user_id'], 30, 1);
	}

	public function delete_member_by_user_id($user_id) {
		global $wpdb;

		$user_id = absint($user_id);
		if (!$wpdb || !$user_id) {
			return;
		}

		foreach ([self::history_table(), self::subscriptions_table(), self::transactions_table(), self::profiles_table()] as $table) {
			$wpdb->delete($table, ['user_id' => $user_id], ['%d']);
		}
	}

	public function prune_orphaned_members() {
		global $wpdb;

		if (!$wpdb || !is_admin() || !current_user_can('manage_options')) {
			return;
		}

		$profiles = self::profiles_table();
		$users = $wpdb->users;
		$orphaned_user_ids = $wpdb->get_col(
			"SELECT profiles.user_id FROM {$profiles} profiles LEFT JOIN {$users} users ON users.ID = profiles.user_id WHERE users.ID IS NULL"
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- plugin-owned table names.

		foreach ((array) $orphaned_user_ids as $user_id) {
			$this->delete_member_by_user_id($user_id);
		}
	}

	public function discard_queued_member_sync($user_id) { unset($this->pending_member_syncs[(int) $user_id]); }

	public function queue_member_sync($user_id) {
		$user_id = absint($user_id);
		if ($user_id > 0) {
			$this->pending_member_syncs[$user_id] = true;
		}
	}

	public function sync_member_after_profile_update($user_id) {
		$user_id = absint($user_id);
		if ($user_id > 0) {
			unset($this->pending_member_syncs[$user_id]);
			$this->sync_member($user_id);
		}
	}

	public function queue_member_sync_after_checkout($user_id, $order = null) {
		$this->queue_member_sync($user_id);
	}

	public function queue_member_sync_after_level_change($level_id, $user_id = 0) {
		$this->queue_member_sync($user_id);
	}

	public function queue_member_sync_after_order_update($order) {
		$user_id = 0;
		if (is_object($order)) {
			$user_id = absint($order->user_id ?? 0);
		} elseif (is_array($order)) {
			$user_id = absint($order['user_id'] ?? 0);
		} else {
			$order_id = absint($order);
			if ($order_id > 0 && class_exists('MemberOrder')) {
				$member_order = new MemberOrder($order_id);
				$user_id = absint($member_order->user_id ?? 0);
			}
		}

		$this->queue_member_sync($user_id);
	}

	public function queue_family_member_syncs($parent_user_id, $child_user_id) {
		$this->queue_member_sync($parent_user_id);
		$this->queue_member_sync($child_user_id);
	}

	public function record_member_login($user_login, $user) {
		if (!$user instanceof WP_User || $user->ID <= 0) {
			return;
		}

		// Administrative "View as Member" sessions are not member activity.
		$request_action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '';
		if (in_array($request_action, ['aac_member_portal_impersonate_member', 'aac_member_portal_return_to_admin'], true)) {
			return;
		}

		$user_id = (int) $user->ID;
		$login_count = $this->get_member_login_count($user_id);
		update_user_meta($user_id, 'aac_last_login_date', current_time('mysql'));
		update_user_meta($user_id, 'aac_login_count', $login_count + 1);
		$this->queue_member_sync($user_id);
	}

	public function record_portal_member_login($user_id) {
		$user = get_user_by('id', absint($user_id));
		if (!$user instanceof WP_User) {
			return;
		}

		$this->record_member_login($user->user_login, $user);
	}

	public function queue_member_sync_after_meta_change($meta_id, $user_id, $meta_key, $meta_value = null) {
		if ($this->is_member_profile_meta_key((string) $meta_key)) {
			$this->queue_member_sync($user_id);
		}
	}

	public function flush_queued_member_syncs() {
		if ($this->is_flushing_member_syncs || empty($this->pending_member_syncs)) {
			return;
		}

		$this->is_flushing_member_syncs = true;
		$user_ids = array_map('intval', array_keys($this->pending_member_syncs));
		$this->pending_member_syncs = [];

		foreach ($user_ids as $user_id) {
			$this->sync_member($user_id);
		}

		$this->is_flushing_member_syncs = false;
	}

	private function is_member_profile_meta_key($meta_key) {
		static $watched_meta_keys = [
			'aac_account_info',
			'aac_profile_info',
			'aac_benefits_info',
			'aac_member_id',
			'aac_membership_discount_type',
			'aac_email_marketing_opt_in',
			'aac_sms_marketing_opt_in',
			'aac_connected_accounts',
			'aac_partner_family_config',
			'aac_partner_family_mode',
			'aac_partner_family_additional_adult',
			'aac_partner_family_dependents',
			'aac_family_account_role',
			'aac_linked_parent_user_id',
			'aac_linked_account_slot_id',
			'aac_linked_account_invite_code',
			'aac_linked_account_type',
			'aac_linked_account_label',
			'aac_family_membership_access_until',
			'aac_family_membership_pending_removal',
			'aac_pending_membership_downgrade',
			'aac_group_account_group_id',
			'aac_group_account_child_level_id',
			'aac_group_account_checkout_code',
			'aac_group_account_synced_at',
			'aac_last_login_date',
			'aac_login_count',
			'first_name',
			'last_name',
			'pmpro_sfirstname',
			'pmpro_slastname',
			'pmpro_sphone',
			'pmpro_saddress1',
			'pmpro_saddress2',
			'pmpro_scity',
			'pmpro_sstate',
			'pmpro_szipcode',
			'pmpro_scountry',
			'pmpro_stripe_customerid',
			'bphone',
			'baddress1',
			'baddress2',
			'bcity',
			'bstate',
			'bzipcode',
			'bcountry',
			't_shirt',
			'birthdate',
			'aaj_preference',
			'anac_preference',
			'american_climbing_journal_preference',
			'guidebook_preferences',
			'student_university',
			'university_or_school',
			'student_university_id',
			'graduation_date',
			'service_component',
			'service_branch',
			'military_service_component',
			'emergency_contact_first_name',
			'emergency_contact_last_name',
			'emergency_contact_phone',
			'emergency_contact_email',
			'emergency_contact_relationship',
			'aac_shipping_address_different',
			'aac_shipping_address_1',
			'aac_shipping_address_2',
			'aac_shipping_city',
			'aac_shipping_state',
			'aac_shipping_postal_code',
			'aac_shipping_country',
			'emergency_first_name',
			'emergency_last_name',
			'emergency_phone',
			'emergency_email',
			'emergency_relationship',
		];

		return in_array($meta_key, $watched_meta_keys, true);
	}

	public static function activate() {
		self::install_schema();
	}

	public function maybe_install_schema() {
		if (get_option(self::SCHEMA_OPTION) === self::SCHEMA_VERSION) {
			return;
		}

		self::install_schema();
	}

	public static function install_schema() {
		global $wpdb;

		if (!$wpdb) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $wpdb->get_charset_collate();
		$profiles = self::profiles_table();
		$history = self::history_table();
		$subscriptions = self::subscriptions_table();
		$transactions = self::transactions_table();

		// Profiles keeps one flattened "what does this member look like right now?"
		// snapshot. The other tables keep mirrored PMPro rows for the moments when
		// staff need receipts, history, and answers.
		dbDelta("
			CREATE TABLE {$profiles} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				parent_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				account_role varchar(32) NOT NULL DEFAULT '',
				email varchar(190) NOT NULL DEFAULT '',
				display_name varchar(190) NOT NULL DEFAULT '',
				member_id varchar(190) NOT NULL DEFAULT '',
				membership_level varchar(100) NOT NULL DEFAULT '',
				membership_status varchar(100) NOT NULL DEFAULT '',
				email_opt_in tinyint(1) unsigned NOT NULL DEFAULT 0,
				sms_opt_in tinyint(1) unsigned NOT NULL DEFAULT 0,
				birthdate varchar(20) NOT NULL DEFAULT '',
				school_university varchar(190) NOT NULL DEFAULT '',
				graduation_date varchar(20) NOT NULL DEFAULT '',
				service_component varchar(100) NOT NULL DEFAULT '',
				emergency_contact_first_name varchar(100) NOT NULL DEFAULT '',
				emergency_contact_last_name varchar(100) NOT NULL DEFAULT '',
				emergency_contact_phone varchar(50) NOT NULL DEFAULT '',
				emergency_contact_email varchar(190) NOT NULL DEFAULT '',
				emergency_contact_relationship varchar(100) NOT NULL DEFAULT '',
				aaj_preference_created_at datetime NULL DEFAULT NULL,
				aaj_preference_updated_at datetime NULL DEFAULT NULL,
				anac_preference_created_at datetime NULL DEFAULT NULL,
				anac_preference_updated_at datetime NULL DEFAULT NULL,
				american_climbing_journal_preference_created_at datetime NULL DEFAULT NULL,
				american_climbing_journal_preference_updated_at datetime NULL DEFAULT NULL,
				guidebook_preferences_created_at datetime NULL DEFAULT NULL,
				guidebook_preferences_updated_at datetime NULL DEFAULT NULL,
				shipping_address_different tinyint(1) unsigned NOT NULL DEFAULT 0,
				shipping_address_1 varchar(190) NOT NULL DEFAULT '',
				shipping_address_2 varchar(190) NOT NULL DEFAULT '',
				shipping_city varchar(100) NOT NULL DEFAULT '',
				shipping_state varchar(100) NOT NULL DEFAULT '',
				shipping_postal_code varchar(32) NOT NULL DEFAULT '',
				shipping_country varchar(100) NOT NULL DEFAULT '',
				last_login_date datetime NULL DEFAULT NULL,
				login_count bigint(20) unsigned NOT NULL DEFAULT 0,
				renewal_date varchar(20) NOT NULL DEFAULT '',
				expiration_date varchar(20) NOT NULL DEFAULT '',
				raw_profile longtext NULL,
				mirrored_at datetime NOT NULL,
				source_modified_at datetime NULL DEFAULT NULL,
				source_hash char(64) NOT NULL DEFAULT '',
				source_version bigint(20) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (id),
				UNIQUE KEY user_id (user_id),
				KEY membership_level (membership_level),
				KEY account_role (account_role),
				KEY mirror_changes (mirrored_at, id),
				KEY source_changes (source_modified_at, id)
			) {$charset_collate};
		");

		dbDelta("
			CREATE TABLE {$history} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				source_record_id bigint(20) unsigned NOT NULL DEFAULT 0,
				source_status varchar(100) NOT NULL DEFAULT '',
				source_date varchar(32) NOT NULL DEFAULT '',
				raw_record longtext NULL,
				mirrored_at datetime NOT NULL,
				source_modified_at datetime NULL DEFAULT NULL,
				source_hash char(64) NOT NULL DEFAULT '',
				source_version bigint(20) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (id),
				UNIQUE KEY user_source (user_id, source_record_id),
				KEY source_status (source_status),
				KEY mirror_changes (mirrored_at, id),
				KEY source_changes (source_modified_at, id)
			) {$charset_collate};
		");

		dbDelta("
			CREATE TABLE {$subscriptions} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				source_record_id bigint(20) unsigned NOT NULL DEFAULT 0,
				source_status varchar(100) NOT NULL DEFAULT '',
				source_date varchar(32) NOT NULL DEFAULT '',
				raw_record longtext NULL,
				mirrored_at datetime NOT NULL,
				source_modified_at datetime NULL DEFAULT NULL,
				source_hash char(64) NOT NULL DEFAULT '',
				source_version bigint(20) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (id),
				UNIQUE KEY user_source (user_id, source_record_id),
				KEY source_status (source_status),
				KEY mirror_changes (mirrored_at, id),
				KEY source_changes (source_modified_at, id)
			) {$charset_collate};
		");

		dbDelta("
			CREATE TABLE {$transactions} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				source_record_id bigint(20) unsigned NOT NULL DEFAULT 0,
				source_status varchar(100) NOT NULL DEFAULT '',
				source_date varchar(32) NOT NULL DEFAULT '',
				raw_record longtext NULL,
				mirrored_at datetime NOT NULL,
				source_modified_at datetime NULL DEFAULT NULL,
				source_hash char(64) NOT NULL DEFAULT '',
				source_version bigint(20) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (id),
				UNIQUE KEY user_source (user_id, source_record_id),
				KEY source_status (source_status),
				KEY mirror_changes (mirrored_at, id),
				KEY source_changes (source_modified_at, id)
			) {$charset_collate};
		");

		// dbDelta can skip newly inserted columns on some managed WordPress hosts.
		// Verify these Salesforce-facing profile fields explicitly before marking
		// the schema upgrade complete.
		foreach ([
			'email_opt_in' => "tinyint(1) unsigned NOT NULL DEFAULT 0",
			'sms_opt_in' => "tinyint(1) unsigned NOT NULL DEFAULT 0",
			'birthdate' => "varchar(20) NOT NULL DEFAULT ''",
			'school_university' => "varchar(190) NOT NULL DEFAULT ''",
			'graduation_date' => "varchar(20) NOT NULL DEFAULT ''",
			'service_component' => "varchar(100) NOT NULL DEFAULT ''",
			'emergency_contact_first_name' => "varchar(100) NOT NULL DEFAULT ''",
			'emergency_contact_last_name' => "varchar(100) NOT NULL DEFAULT ''",
			'emergency_contact_phone' => "varchar(50) NOT NULL DEFAULT ''",
			'emergency_contact_email' => "varchar(190) NOT NULL DEFAULT ''",
			'emergency_contact_relationship' => "varchar(100) NOT NULL DEFAULT ''",
			'aaj_preference_created_at' => 'datetime NULL DEFAULT NULL',
			'aaj_preference_updated_at' => 'datetime NULL DEFAULT NULL',
			'anac_preference_created_at' => 'datetime NULL DEFAULT NULL',
			'anac_preference_updated_at' => 'datetime NULL DEFAULT NULL',
			'american_climbing_journal_preference_created_at' => 'datetime NULL DEFAULT NULL',
			'american_climbing_journal_preference_updated_at' => 'datetime NULL DEFAULT NULL',
			'guidebook_preferences_created_at' => 'datetime NULL DEFAULT NULL',
			'guidebook_preferences_updated_at' => 'datetime NULL DEFAULT NULL',
			'shipping_address_different' => "tinyint(1) unsigned NOT NULL DEFAULT 0",
			'shipping_address_1' => "varchar(190) NOT NULL DEFAULT ''",
			'shipping_address_2' => "varchar(190) NOT NULL DEFAULT ''",
			'shipping_city' => "varchar(100) NOT NULL DEFAULT ''",
			'shipping_state' => "varchar(100) NOT NULL DEFAULT ''",
			'shipping_postal_code' => "varchar(32) NOT NULL DEFAULT ''",
			'shipping_country' => "varchar(100) NOT NULL DEFAULT ''",
		] as $column => $definition) {
			$existing_column = $wpdb->get_var(
				$wpdb->prepare("SHOW COLUMNS FROM {$profiles} LIKE %s", $column)
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- plugin-owned table name.
			if ($existing_column === null) {
				$wpdb->query("ALTER TABLE {$profiles} ADD COLUMN {$column} {$definition} AFTER membership_status"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed plugin-owned identifiers and definitions.
			}
		}

		// Magazine add-ons are no longer part of signup, PMPro member fields, or
		// the Salesforce-facing member mirror. Remove the obsolete mirror columns.
		foreach (['alpinist_subscription', 'backcountry_subscription'] as $obsolete_column) {
			$existing_column = $wpdb->get_var(
				$wpdb->prepare("SHOW COLUMNS FROM {$profiles} LIKE %s", $obsolete_column)
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- plugin-owned table name.
			if ($existing_column !== null) {
				$wpdb->query("ALTER TABLE {$profiles} DROP COLUMN {$obsolete_column}"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed plugin-owned identifiers.
			}
		}

		// Populate the new consent columns for existing mirrored members without
		// waiting for every member to edit and save their profile again.
		$usermeta = $wpdb->usermeta;
		$wpdb->query(
			"UPDATE {$profiles} profiles
			LEFT JOIN {$usermeta} email_consent
				ON email_consent.user_id = profiles.user_id
				AND email_consent.meta_key = 'aac_email_marketing_opt_in'
			LEFT JOIN {$usermeta} sms_consent
				ON sms_consent.user_id = profiles.user_id
				AND sms_consent.meta_key = 'aac_sms_marketing_opt_in'
			SET profiles.email_opt_in = CASE
					WHEN LOWER(COALESCE(email_consent.meta_value, '')) IN ('1', 'true', 'yes', 'on') THEN 1
					ELSE 0
				END,
				profiles.sms_opt_in = CASE
					WHEN LOWER(COALESCE(sms_consent.meta_value, '')) IN ('1', 'true', 'yes', 'on') THEN 1
					ELSE 0
				END"
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- plugin-owned table and WordPress usermeta table.

		// Existing preferences predate per-publication audit fields. Use the
		// WordPress registration timestamp as the best available creation date,
		// while leaving Updated At empty until the member actually changes and
		// saves that individual preference.
		foreach (AAC_Member_Portal_Publication_Preferences::FIELDS as $preference_key) {
			$created_key = $preference_key . '_created_at';
			$updated_key = $preference_key . '_updated_at';
			$created_column = $created_key;
			$updated_column = $updated_key;
			$users_table = $wpdb->users;
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$usermeta} (user_id, meta_key, meta_value)
					SELECT preference.user_id, %s, COALESCE(NULLIF(users.user_registered, '0000-00-00 00:00:00'), UTC_TIMESTAMP())
					FROM {$usermeta} preference
					INNER JOIN {$users_table} users ON users.ID = preference.user_id
					LEFT JOIN {$usermeta} created
						ON created.user_id = preference.user_id AND created.meta_key = %s
					WHERE preference.meta_key = %s
						AND TRIM(preference.meta_value) <> ''
						AND created.umeta_id IS NULL",
					$created_key,
					$created_key,
					$preference_key
				)
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WordPress-owned tables and prepared metadata keys.

			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$profiles} profiles
					LEFT JOIN {$usermeta} created
						ON created.user_id = profiles.user_id AND created.meta_key = %s
					LEFT JOIN {$usermeta} updated
						ON updated.user_id = profiles.user_id AND updated.meta_key = %s
					SET profiles.{$created_column} = NULLIF(created.meta_value, ''),
						profiles.{$updated_column} = NULLIF(updated.meta_value, '')",
					$created_key,
					$updated_key
				)
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- plugin-owned columns come from a fixed class constant.
		}

		$saved_export_fields = get_option(self::EXPORT_FIELDS_OPTION, []);
		if (is_array($saved_export_fields) && !empty($saved_export_fields)) {
			$saved_export_fields = array_values(array_unique(array_merge($saved_export_fields, [
				'email_opt_in',
				'sms_opt_in',
				'shipping_address_different',
				'shipping_address_1',
				'shipping_address_2',
				'shipping_city',
				'shipping_state',
				'shipping_postal_code',
				'shipping_country',
				'aaj_preference_created_at',
				'aaj_preference_updated_at',
				'anac_preference_created_at',
				'anac_preference_updated_at',
				'american_climbing_journal_preference_created_at',
				'american_climbing_journal_preference_updated_at',
				'guidebook_preferences_created_at',
				'guidebook_preferences_updated_at',
			])));
			update_option(self::EXPORT_FIELDS_OPTION, $saved_export_fields, false);
		}

		update_option(self::SCHEMA_OPTION, self::SCHEMA_VERSION);
	}

	public function register_admin_page() {
		add_submenu_page(
			AAC_Member_Portal_Admin::MENU_SLUG,
			'Member Database',
			'Member Database',
			'manage_options',
			self::PAGE_SLUG,
			[$this, 'render_admin_page']
		);

		add_submenu_page(
			null,
			'Member Database Export',
			'Member Database Export',
			'manage_options',
			self::EXPORT_PAGE_SLUG,
			[$this, 'render_export_page']
		);
	}

	public static function render_database_tools_nav($active_slug = self::PAGE_SLUG) {
		$tools = [
			self::PAGE_SLUG => [
				'label' => 'Member Database',
				'url' => admin_url('admin.php?page=' . self::PAGE_SLUG),
			],
			self::EXPORT_PAGE_SLUG => [
				'label' => 'Member Database Export',
				'url' => admin_url('admin.php?page=' . self::EXPORT_PAGE_SLUG),
			],
		];

		if (class_exists('AAC_Member_Portal_Daily_Member_Export')) {
			$tools[AAC_Member_Portal_Daily_Member_Export::MENU_SLUG] = [
				'label' => 'Daily Member Export',
				'url' => admin_url('admin.php?page=' . AAC_Member_Portal_Daily_Member_Export::MENU_SLUG),
			];
		}

		if (class_exists('AAC_Member_Portal_Koha_Sync')) {
			$tools[AAC_Member_Portal_Koha_Sync::MENU_SLUG] = [
				'label' => 'Koha Library Sync',
				'url' => admin_url('admin.php?page=' . AAC_Member_Portal_Koha_Sync::MENU_SLUG),
			];
		}

		if (class_exists('AAC_Member_Portal_Import_Manager')) {
			$tools[AAC_Member_Portal_Import_Manager::PAGE_SLUG] = [
				'label' => 'Member Import Manager',
				'url' => admin_url('admin.php?page=' . AAC_Member_Portal_Import_Manager::PAGE_SLUG),
			];
		}
		?>
		<nav class="nav-tab-wrapper" style="margin:16px 0 22px;">
			<?php foreach ($tools as $slug => $tool) : ?>
				<a class="nav-tab <?php echo $active_slug === $slug ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url($tool['url']); ?>">
					<?php echo esc_html($tool['label']); ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	public function sync_member($user_id) {
		global $wpdb;

		$user_id = (int) $user_id;
		if ($user_id <= 0 || !$wpdb) {
			return false;
		}

		$user = get_user_by('id', $user_id);
		if (!$user instanceof WP_User) {
			return false;
		}

		// We build the mirror from the same payload the frontend uses on purpose.
		// That keeps the admin view and the member-facing view from telling two
		// different stories about the same person.
		$api = AAC_Member_Portal_API::get_instance();
		$profile = $api instanceof AAC_Member_Portal_API ? $api->get_profile_for_user($user_id) : [];
		$account_info = is_array($profile['account_info'] ?? null) ? $profile['account_info'] : [];
		$profile_info = is_array($profile['profile_info'] ?? null) ? $profile['profile_info'] : [];
		$linked_parent = is_array($profile['linked_parent_account'] ?? null) ? $profile['linked_parent_account'] : [];
		$profile['pmpro_membership'] = $this->get_latest_pmpro_row($user_id, 'pmpro_memberships_users');
		$profile['pmpro_subscription'] = $this->get_latest_pmpro_row($user_id, 'pmpro_subscriptions');
		$profile['pmpro_transaction'] = $this->get_latest_pmpro_row($user_id, 'pmpro_membership_orders');
		$last_login_date = $this->get_member_last_login_date($user_id);
		$login_count = $this->get_member_login_count($user_id);
		$birthdate = $this->first_present_value([$this->get_first_user_meta_value($user_id, ['birthdate']), $account_info['birthdate'] ?? '']);
		$school_university = $this->first_present_value([$this->get_first_user_meta_value($user_id, ['student_university', 'university_or_school', 'school_university', 'university_school']), $account_info['student_university'] ?? '', $account_info['university_school'] ?? '']);
		$graduation_date = $this->first_present_value([$this->get_first_user_meta_value($user_id, ['graduation_date', 'student_graduation_date']), $account_info['graduation_date'] ?? '', $account_info['student_graduation_date'] ?? '']);
		$service_component = $this->first_present_value([$this->get_first_user_meta_value($user_id, ['service_component', 'military_service_component']), $account_info['service_component'] ?? '', $account_info['military_service_component'] ?? '']);
		$emergency_contact_first_name = $this->first_present_value([$this->get_first_user_meta_value($user_id, ['emergency_contact_first_name', 'emergency_first_name', 'emergency_first']), $account_info['emergency_contact_first_name'] ?? '']);
		$emergency_contact_last_name = $this->first_present_value([$this->get_first_user_meta_value($user_id, ['emergency_contact_last_name', 'emergency_last_name', 'emergency_last']), $account_info['emergency_contact_last_name'] ?? '']);
		$emergency_contact_phone = $this->first_present_value([$this->get_first_user_meta_value($user_id, ['emergency_contact_phone', 'emergency_phone', 'emergency_contact_phone_number']), $account_info['emergency_contact_phone'] ?? '']);
		$emergency_contact_email = $this->first_present_value([$this->get_first_user_meta_value($user_id, ['emergency_contact_email', 'emergency_email']), $account_info['emergency_contact_email'] ?? '']);
		$emergency_contact_relationship = $this->first_present_value([$this->get_first_user_meta_value($user_id, ['emergency_contact_relationship', 'emergency_relationship']), $account_info['emergency_contact_relationship'] ?? '']);
		$email_opt_in = filter_var(get_user_meta($user_id, 'aac_email_marketing_opt_in', true), FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
		$sms_opt_in = filter_var(get_user_meta($user_id, 'aac_sms_marketing_opt_in', true), FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
		$profile['login_activity'] = [
			'last_login_date' => $last_login_date,
			'login_count' => $login_count,
		];
		$account_role = sanitize_text_field((string) get_user_meta($user_id, 'aac_family_account_role', true));
		$parent_user_id = absint(get_user_meta($user_id, 'aac_linked_parent_user_id', true));
		$preference_timestamps = AAC_Member_Portal_Publication_Preferences::get_timestamps($user_id);

		if ($parent_user_id <= 0 && !empty($linked_parent['user_id'])) {
			$parent_user_id = (int) $linked_parent['user_id'];
		}

		$profile_data = [
			'user_id' => $user_id,
			'parent_user_id' => $parent_user_id,
			'account_role' => $account_role,
			'email' => sanitize_email($account_info['email'] ?? $user->user_email),
			'display_name' => sanitize_text_field($account_info['name'] ?? $user->display_name),
			'member_id' => sanitize_text_field($profile_info['member_id'] ?? ''),
			'membership_level' => sanitize_text_field($profile_info['tier'] ?? ''),
			'membership_status' => sanitize_text_field($profile_info['status'] ?? ''),
			'email_opt_in' => $email_opt_in,
			'sms_opt_in' => $sms_opt_in,
			'birthdate' => sanitize_text_field($birthdate),
			'school_university' => sanitize_text_field($school_university),
			'graduation_date' => sanitize_text_field($graduation_date),
			'service_component' => sanitize_text_field($service_component),
			'emergency_contact_first_name' => sanitize_text_field($emergency_contact_first_name),
			'emergency_contact_last_name' => sanitize_text_field($emergency_contact_last_name),
			'emergency_contact_phone' => sanitize_text_field($emergency_contact_phone),
			'emergency_contact_email' => sanitize_email($emergency_contact_email),
			'emergency_contact_relationship' => sanitize_text_field($emergency_contact_relationship),
			'aaj_preference_created_at' => $preference_timestamps['aaj_preference_created_at'] ?: null,
			'aaj_preference_updated_at' => $preference_timestamps['aaj_preference_updated_at'] ?: null,
			'anac_preference_created_at' => $preference_timestamps['anac_preference_created_at'] ?: null,
			'anac_preference_updated_at' => $preference_timestamps['anac_preference_updated_at'] ?: null,
			'american_climbing_journal_preference_created_at' => $preference_timestamps['american_climbing_journal_preference_created_at'] ?: null,
			'american_climbing_journal_preference_updated_at' => $preference_timestamps['american_climbing_journal_preference_updated_at'] ?: null,
			'guidebook_preferences_created_at' => $preference_timestamps['guidebook_preferences_created_at'] ?: null,
			'guidebook_preferences_updated_at' => $preference_timestamps['guidebook_preferences_updated_at'] ?: null,
			'shipping_address_different' => !empty($account_info['shipping_address_different']) ? 1 : 0,
			'shipping_address_1' => sanitize_text_field($account_info['shipping_address_1'] ?? ''),
			'shipping_address_2' => sanitize_text_field($account_info['shipping_address_2'] ?? ''),
			'shipping_city' => sanitize_text_field($account_info['shipping_city'] ?? ''),
			'shipping_state' => sanitize_text_field($account_info['shipping_state'] ?? ''),
			'shipping_postal_code' => sanitize_text_field($account_info['shipping_postal_code'] ?? ''),
			'shipping_country' => sanitize_text_field($account_info['shipping_country'] ?? ''),
			'last_login_date' => $last_login_date !== '' ? $last_login_date : null,
			'login_count' => $login_count,
			'renewal_date' => sanitize_text_field($profile_info['renewal_date'] ?? ''),
			'expiration_date' => sanitize_text_field($profile_info['expiration_date'] ?? ''),
			'raw_profile' => wp_json_encode($profile),
		];
		$profile_hash = $this->build_source_hash($profile_data);
		$existing_profile = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT id, source_hash, source_version FROM ' . self::profiles_table() . ' WHERE user_id = %d LIMIT 1',
				$user_id
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.

		if (!is_array($existing_profile) || !hash_equals((string) ($existing_profile['source_hash'] ?? ''), $profile_hash)) {
			$changed_at = current_time('mysql');
			$source_version = max(0, absint($existing_profile['source_version'] ?? 0)) + 1;
			$profile_data['mirrored_at'] = $changed_at;
			$profile_data['source_modified_at'] = $changed_at;
			$profile_data['source_hash'] = $profile_hash;
			$profile_data['source_version'] = $source_version;

			if (is_array($existing_profile)) {
				$profile_row_id = absint($existing_profile['id'] ?? 0);
				$write_result = $wpdb->update(
					self::profiles_table(),
					$profile_data,
					['id' => $profile_row_id],
					['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d'],
					['%d']
				);
			} else {
				$write_result = $wpdb->insert(
					self::profiles_table(),
					$profile_data,
					['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d']
				);
				$profile_row_id = absint($wpdb->insert_id);
			}

			if ($write_result !== false && $profile_row_id > 0) {
				$this->announce_mirror_change('profiles', self::profiles_table(), $profile_row_id, $user_id, $source_version, $changed_at, $profile_hash);
			}
		}

		$this->mirror_pmpro_rows($user_id, 'pmpro_memberships_users', self::history_table(), 'membership_history', 'status', ['startdate', 'modified', 'date']);
		$this->mirror_pmpro_rows($user_id, 'pmpro_subscriptions', self::subscriptions_table(), 'subscriptions', 'status', ['next_payment_date', 'cycle_enddate', 'startdate', 'modified']);
		$this->mirror_pmpro_rows($user_id, 'pmpro_membership_orders', self::transactions_table(), 'transactions', 'status', ['timestamp']);

		return true;
	}

	private function get_latest_pmpro_row($user_id, $wpdb_property) {
		global $wpdb;

		if (!$wpdb || empty($wpdb->{$wpdb_property})) {
			return [];
		}

		$row = $wpdb->get_row(
			$wpdb->prepare("SELECT * FROM {$wpdb->{$wpdb_property}} WHERE user_id = %d ORDER BY id DESC LIMIT 1", $user_id),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.

		return is_array($row) ? $row : [];
	}

	private function get_member_last_login_date($user_id) {
		foreach (['aac_last_login_date', 'pmpro_last_login', 'last_login', 'wp_last_login'] as $meta_key) {
			$value = get_user_meta((int) $user_id, $meta_key, true);
			if ($value === '' || $value === null) {
				continue;
			}

			if (is_numeric($value)) {
				return wp_date('Y-m-d H:i:s', (int) $value, wp_timezone());
			}
			if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $value)) {
				return (string) $value;
			}

			$timestamp = strtotime((string) $value);
			if ($timestamp !== false) {
				return wp_date('Y-m-d H:i:s', $timestamp, wp_timezone());
			}
		}

		return '';
	}

	private function get_member_login_count($user_id) {
		foreach (['aac_login_count', 'pmpro_login_count', 'login_count'] as $meta_key) {
			$value = get_user_meta((int) $user_id, $meta_key, true);
			if ($value !== '' && $value !== null && is_numeric($value)) {
				return max(0, (int) $value);
			}
		}

		return 0;
	}

	private function get_first_user_meta_value($user_id, $meta_keys) {
		foreach ((array) $meta_keys as $meta_key) {
			$value = get_user_meta((int) $user_id, (string) $meta_key, true);
			if (!is_array($value) && !is_object($value) && trim((string) $value) !== '') {
				return (string) $value;
			}
		}

		return '';
	}

	private function mirror_pmpro_rows($user_id, $wpdb_property, $mirror_table, $record_type, $status_column = 'status', $date_candidates = []) {
		global $wpdb;

		if (!$wpdb) {
			return;
		}

		$existing_rows = $wpdb->get_results(
			$wpdb->prepare("SELECT id, source_record_id, source_hash, source_version FROM {$mirror_table} WHERE user_id = %d", $user_id),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
		$existing_by_source_id = [];
		foreach ((array) $existing_rows as $existing_row) {
			$existing_by_source_id[(string) absint($existing_row['source_record_id'] ?? 0)] = $existing_row;
		}

		$rows = [];
		if (!empty($wpdb->{$wpdb_property})) {
			$source_table = $wpdb->{$wpdb_property};
			$rows = $wpdb->get_results(
				$wpdb->prepare("SELECT * FROM {$source_table} WHERE user_id = %d ORDER BY id DESC", $user_id),
				ARRAY_A
			); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
		}

		$seen_source_ids = [];
		foreach ((array) $rows as $row) {
			if (!is_array($row)) {
				continue;
			}

			$source_record_id = absint($row['id'] ?? 0);
			$source_key = (string) $source_record_id;
			$seen_source_ids[$source_key] = true;
			$source_status = sanitize_text_field((string) ($row[$status_column] ?? ''));
			$source_date = '';
			foreach ($date_candidates as $candidate) {
				if (!empty($row[$candidate])) {
					$source_date = sanitize_text_field((string) $row[$candidate]);
					break;
				}
			}

			$source_hash = $this->build_source_hash($row);
			$existing_row = $existing_by_source_id[$source_key] ?? null;
			if (is_array($existing_row) && hash_equals((string) ($existing_row['source_hash'] ?? ''), $source_hash)) {
				continue;
			}

			$changed_at = current_time('mysql');
			$source_version = max(0, absint($existing_row['source_version'] ?? 0)) + 1;
			$mirror_data = [
				'user_id' => $user_id,
				'source_record_id' => $source_record_id,
				'source_status' => $source_status,
				'source_date' => $source_date,
				'raw_record' => wp_json_encode($row),
				'mirrored_at' => $changed_at,
				'source_modified_at' => $changed_at,
				'source_hash' => $source_hash,
				'source_version' => $source_version,
			];

			if (is_array($existing_row)) {
				$mirror_row_id = absint($existing_row['id'] ?? 0);
				$write_result = $wpdb->update(
					$mirror_table,
					$mirror_data,
					['id' => $mirror_row_id],
					['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d'],
					['%d']
				);
			} else {
				$write_result = $wpdb->insert(
					$mirror_table,
					$mirror_data,
					['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d']
				);
				$mirror_row_id = absint($wpdb->insert_id);
			}

			if ($write_result !== false && $mirror_row_id > 0) {
				$this->announce_mirror_change($record_type, $mirror_table, $mirror_row_id, $user_id, $source_version, $changed_at, $source_hash);
			}
		}

		foreach ($existing_by_source_id as $source_key => $existing_row) {
			if (isset($seen_source_ids[$source_key])) {
				continue;
			}

			$mirror_row_id = absint($existing_row['id'] ?? 0);
			if ($mirror_row_id <= 0) {
				continue;
			}

			$deleted = $wpdb->delete($mirror_table, ['id' => $mirror_row_id], ['%d']);
			if ($deleted) {
				do_action('aac_member_portal_mirror_record_deleted', $record_type, $mirror_table, $mirror_row_id, $user_id, absint($source_key));
				do_action('aac_npc_sf_source_record_changed', $mirror_table, $mirror_row_id, $user_id, 'delete', current_time('mysql'));
			}
		}
	}

	private function announce_mirror_change($record_type, $mirror_table, $mirror_row_id, $user_id, $source_version, $changed_at, $source_hash) {
		do_action(
			'aac_member_portal_mirror_record_changed',
			$record_type,
			$mirror_table,
			$mirror_row_id,
			$user_id,
			$source_version,
			$changed_at,
			$source_hash
		);
		do_action('aac_npc_sf_source_record_changed', $mirror_table, $mirror_row_id, $user_id, 'upsert', $changed_at);
	}

	private function build_source_hash($value) {
		$normalized = $this->normalize_source_hash_value($value);
		$json = wp_json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		return hash('sha256', is_string($json) ? $json : serialize($normalized));
	}

	private function normalize_source_hash_value($value) {
		if (is_object($value)) {
			$value = get_object_vars($value);
		}

		if (!is_array($value)) {
			return $value;
		}

		$normalized = [];
		foreach ($value as $key => $item) {
			$normalized[$key] = $this->normalize_source_hash_value($item);
		}

		if ($this->is_assoc($normalized)) {
			ksort($normalized);
		}

		return $normalized;
	}

	public function handle_export_member_database() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to export the member database.', 'aac-member-portal'));
		}

		check_admin_referer('aac_member_portal_export_member_database');

		$job = $this->member_export->start(get_current_user_id(), $this->get_member_export_user_ids(), $this->get_selected_member_export_headers());
		if (is_wp_error($job)) wp_die(esc_html($job->get_error_message()));
		wp_safe_redirect($this->build_admin_url(['page' => self::EXPORT_PAGE_SLUG]));
		exit;
	}

	public function handle_save_member_export_fields() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to change member export fields.', 'aac-member-portal'));
		}

		check_admin_referer('aac_member_portal_save_member_export_fields');

		$all_headers = $this->get_member_export_headers();
		$posted_fields = isset($_POST['aac_member_export_fields']) ? (array) wp_unslash($_POST['aac_member_export_fields']) : [];
		$posted_fields = array_map('sanitize_text_field', $posted_fields);
		$selected_fields = array_values(array_intersect($all_headers, $posted_fields));

		if (empty($selected_fields)) {
			delete_option(self::EXPORT_FIELDS_OPTION);
			$redirect_status = 'all';
		} else {
			update_option(self::EXPORT_FIELDS_OPTION, $selected_fields, false);
			$redirect_status = 'saved';
		}

		wp_safe_redirect($this->build_admin_url([
			'page' => self::EXPORT_PAGE_SLUG,
			'aac_export_fields_saved' => $redirect_status,
		]));
		exit;
	}

	public function render_export_page() {
		if (!current_user_can('manage_options')) {
			return;
		}

		$export_url = wp_nonce_url(
			add_query_arg(['action' => 'aac_member_portal_export_member_database'], admin_url('admin-post.php')),
			'aac_member_portal_export_member_database'
		);
		$all_headers = $this->get_member_export_headers();
		$selected_headers = $this->get_selected_member_export_headers();
		$field_groups = $this->get_member_export_field_groups();
		$field_labels = $this->get_member_export_field_labels();
		$selected_lookup = array_fill_keys($selected_headers, true);
		$selected_count = count($selected_headers);
		$total_count = count($all_headers);
		$save_status = isset($_GET['aac_export_fields_saved']) ? sanitize_key(wp_unslash($_GET['aac_export_fields_saved'])) : '';
		?>
		<div class="wrap">
			<h1>Member Database Export</h1>
			<?php self::render_database_tools_nav(self::EXPORT_PAGE_SLUG); ?>
			<?php if ('saved' === $save_status) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e('Member export field selection saved.', 'aac-member-portal'); ?></p>
				</div>
			<?php elseif ('all' === $save_status) : ?>
				<div class="notice notice-info is-dismissible">
					<p><?php esc_html_e('No fields were selected, so the export was reset to include all fields.', 'aac-member-portal'); ?></p>
				</div>
			<?php endif; ?>
			<p>
				Choose which member fields should be included in the CSV. Your saved selection controls the export button on this page
				and the quick export button on the Member Database page.
			</p>
			<div style="background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:20px;max-width:920px;margin-top:18px;">
				<h2 style="margin-top:0;">Selected Member CSV</h2>
				<p>
					The next export will include <strong><?php echo esc_html((string) $selected_count); ?></strong> of
					<strong><?php echo esc_html((string) $total_count); ?></strong> available fields. The export reads the existing
					change-driven mirror in short batches, with saved progress and a download when the file is ready. It includes
					PII, so store and share the file carefully.
				</p>
				<p style="margin-top:18px;">
					<a class="button button-primary button-hero" href="<?php echo esc_url($export_url); ?>">
						Export Selected Fields CSV
					</a>
					<a class="button button-secondary button-hero" href="<?php echo esc_url($this->build_admin_url()); ?>" style="margin-left:8px;">
						Open Member Database
					</a>
				</p>
			</div>

			<?php $this->member_export->render(); ?>

			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width:1180px;margin-top:18px;">
				<?php wp_nonce_field('aac_member_portal_save_member_export_fields'); ?>
				<input type="hidden" name="action" value="aac_member_portal_save_member_export_fields" />
				<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:0 0 14px;">
					<button type="submit" class="button button-primary">Save Field Selection</button>
					<button type="button" class="button" data-aac-export-fields="all">Select All</button>
					<button type="button" class="button" data-aac-export-fields="none">Clear All</button>
				</div>
				<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px;">
					<?php foreach ($field_groups as $group_label => $group_fields) : ?>
						<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px;">
							<h2 style="font-size:16px;margin:0 0 10px;"><?php echo esc_html($group_label); ?></h2>
							<?php foreach ($group_fields as $field_key) : ?>
								<?php if (!in_array($field_key, $all_headers, true)) : ?>
									<?php continue; ?>
								<?php endif; ?>
								<label style="display:block;margin:8px 0;line-height:1.35;">
									<input
										type="checkbox"
										name="aac_member_export_fields[]"
										value="<?php echo esc_attr($field_key); ?>"
										<?php checked(isset($selected_lookup[$field_key])); ?>
									/>
									<span><?php echo esc_html($field_labels[$field_key] ?? $this->format_member_export_field_label($field_key)); ?></span>
									<code style="display:block;margin:2px 0 0 24px;color:#646970;"><?php echo esc_html($field_key); ?></code>
								</label>
							<?php endforeach; ?>
						</div>
					<?php endforeach; ?>
				</div>
				<p style="margin-top:16px;">
					<button type="submit" class="button button-primary">Save Field Selection</button>
				</p>
			</form>
			<script>
				document.addEventListener('click', function(event) {
					var action = event.target && event.target.getAttribute('data-aac-export-fields');
					if (!action) {
						return;
					}
					document.querySelectorAll('input[name="aac_member_export_fields[]"]').forEach(function(input) {
						input.checked = action === 'all';
					});
				});
			</script>
		</div>
		<?php
	}

	private function get_selected_member_export_headers() {
		$all_headers = $this->get_member_export_headers();
		$stored_headers = get_option(self::EXPORT_FIELDS_OPTION, []);

		if (!is_array($stored_headers) || empty($stored_headers)) {
			return $all_headers;
		}

		$stored_headers = array_map('sanitize_text_field', $stored_headers);
		$selected_headers = array_values(array_intersect($all_headers, $stored_headers));

		return !empty($selected_headers) ? $selected_headers : $all_headers;
	}

	private function get_member_export_field_groups() {
		$groups = [
			'WordPress Account' => [
				'wp_user_id',
				'user_login',
				'user_email',
				'user_registered',
				'display_name',
				'roles',
				'last_login_date',
				'login_count',
			],
			'AAC Profile' => [
				'aac_member_id',
				'first_name',
				'last_name',
				'phone',
				'birthdate',
				'billing_address_1',
				'billing_address_2',
				'billing_city',
				'billing_state',
				'billing_zip',
				'billing_country',
				'shipping_address_different',
				'shipping_address_1',
				'shipping_address_2',
				'shipping_city',
				'shipping_state',
				'shipping_postal_code',
				'shipping_country',
				't_shirt_size',
				'aaj_preference',
				'aaj_preference_created_at',
				'aaj_preference_updated_at',
				'accidents_preference',
				'anac_preference_created_at',
				'anac_preference_updated_at',
				'acj_preference',
				'american_climbing_journal_preference_created_at',
				'american_climbing_journal_preference_updated_at',
				'guidebook_preference',
				'guidebook_preferences_created_at',
				'guidebook_preferences_updated_at',
				'email_opt_in',
				'sms_opt_in',
				'membership_discount_type',
				'student_university',
				'student_graduation_date',
				'military_service_component',
				'emergency_contact_first_name',
				'emergency_contact_last_name',
				'emergency_contact_phone',
				'emergency_contact_email',
				'emergency_contact_relationship',
			],
			'Current Membership' => [
				'current_membership_id',
				'current_membership_level',
				'current_membership_status',
				'membership_startdate',
				'membership_enddate',
				'membership_modified',
				'member_since',
				'renewal_date',
				'expiration_date',
				'valid_through_date',
				'auto_renew',
			],
			'Subscription / Stripe' => [
				'current_subscription_id',
				'current_subscription_gateway',
				'current_subscription_environment',
				'current_subscription_transaction_id',
				'current_subscription_status',
				'current_subscription_startdate',
				'current_subscription_enddate',
				'current_subscription_next_payment_date',
				'current_subscription_billing_amount',
				'current_subscription_cycle_number',
				'current_subscription_cycle_period',
				'stripe_customer_id',
			],
			'Latest Order / Payment' => [
				'latest_order_id',
				'latest_order_code',
				'latest_order_membership_id',
				'latest_order_status',
				'latest_order_gateway',
				'latest_order_environment',
				'latest_order_payment_transaction_id',
				'latest_order_subscription_transaction_id',
				'latest_order_subtotal',
				'latest_order_tax',
				'latest_order_total',
				'latest_order_timestamp',
				'latest_order_discount_code',
			],
			'Family / Group Accounts' => [
				'account_role',
				'parent_user_id',
				'parent_member_id',
				'parent_email',
				'linked_account_slot_id',
				'family_membership_mode',
				'family_additional_adult',
				'family_dependent_count',
				'aac_group_account_group_id',
				'aac_group_account_checkout_code',
				'aac_group_account_child_level_id',
				'aac_group_account_synced_at',
				'pmpro_group_account_id',
				'pmpro_group_account_checkout_code',
				'pmpro_group_account_total_seats',
				'pmpro_group_account_active_members',
			],
			'Raw JSON / Audit' => [
				'connected_accounts_json',
				'linked_parent_account_json',
				'family_membership_json',
				'pmpro_membership_history_json',
				'pmpro_subscriptions_json',
				'pmpro_orders_json',
				'pmpro_group_accounts_json',
				'all_user_meta_json',
				'portal_profile_json',
				'mirrored_at',
			],
		];
		$grouped_fields = [];
		foreach ($groups as $fields) {
			$grouped_fields = array_merge($grouped_fields, $fields);
		}
		$ungrouped_fields = array_values(array_diff($this->get_member_export_headers(), $grouped_fields));
		if (!empty($ungrouped_fields)) {
			$groups['Other Fields'] = $ungrouped_fields;
		}

		return $groups;
	}

	private function get_member_export_field_labels() {
		return [
			'wp_user_id' => 'WordPress user ID',
			'user_login' => 'Username',
			'user_email' => 'Email address',
			'user_registered' => 'WordPress registration date',
			'display_name' => 'Display name',
			'roles' => 'WordPress roles',
			'last_login_date' => 'Last login date',
			'login_count' => 'Login count',
			'aac_member_id' => 'AAC member ID',
			'first_name' => 'First name',
			'last_name' => 'Last name',
			'phone' => 'Phone',
			'birthdate' => 'Birthdate',
			'billing_address_1' => 'Billing address 1',
			'billing_address_2' => 'Billing address 2',
			'billing_city' => 'Billing city',
			'billing_state' => 'Billing state',
			'billing_zip' => 'Billing ZIP',
			'billing_country' => 'Billing country',
			'shipping_address_different' => 'Shipping address is different',
			'shipping_address_1' => 'Shipping address 1',
			'shipping_address_2' => 'Shipping address 2',
			'shipping_city' => 'Shipping city',
			'shipping_state' => 'Shipping state',
			'shipping_postal_code' => 'Shipping ZIP / Postal code',
			'shipping_country' => 'Shipping country',
			't_shirt_size' => 'T-shirt size',
			'aaj_preference' => 'AAJ preference',
			'aaj_preference_created_at' => 'AAJ preference created at',
			'aaj_preference_updated_at' => 'AAJ preference updated at',
			'accidents_preference' => 'Accidents preference',
			'anac_preference_created_at' => 'Accidents preference created at',
			'anac_preference_updated_at' => 'Accidents preference updated at',
			'acj_preference' => 'ACJ preference',
			'american_climbing_journal_preference_created_at' => 'ACJ preference created at',
			'american_climbing_journal_preference_updated_at' => 'ACJ preference updated at',
			'guidebook_preference' => 'Guidebook preference',
			'guidebook_preferences_created_at' => 'Guidebook preference created at',
			'guidebook_preferences_updated_at' => 'Guidebook preference updated at',
			'email_opt_in' => 'Email opt in',
			'sms_opt_in' => 'SMS opt in',
			'membership_discount_type' => 'Membership discount type',
			'student_university' => 'Student university/school',
			'student_graduation_date' => 'Student graduation date',
			'military_service_component' => 'Military service component',
			'emergency_contact_first_name' => 'Emergency contact first name',
			'emergency_contact_last_name' => 'Emergency contact last name',
			'emergency_contact_phone' => 'Emergency contact phone',
			'emergency_contact_email' => 'Emergency contact email',
			'emergency_contact_relationship' => 'Emergency contact relationship',
			'current_membership_id' => 'Current membership level ID',
			'current_membership_level' => 'Current membership level',
			'current_membership_status' => 'Current membership status',
			'membership_startdate' => 'Membership start date',
			'membership_enddate' => 'Membership end date',
			'membership_modified' => 'Membership modified date',
			'member_since' => 'Member since',
			'renewal_date' => 'Renewal date',
			'expiration_date' => 'Expiration date',
			'valid_through_date' => 'Valid through date',
			'auto_renew' => 'Auto renewal enabled',
			'current_subscription_id' => 'Current subscription ID',
			'current_subscription_gateway' => 'Current subscription gateway',
			'current_subscription_environment' => 'Current subscription environment',
			'current_subscription_transaction_id' => 'Current subscription transaction ID',
			'current_subscription_status' => 'Current subscription status',
			'current_subscription_startdate' => 'Current subscription start date',
			'current_subscription_enddate' => 'Current subscription end date',
			'current_subscription_next_payment_date' => 'Next payment date',
			'current_subscription_billing_amount' => 'Subscription billing amount',
			'current_subscription_cycle_number' => 'Subscription cycle number',
			'current_subscription_cycle_period' => 'Subscription cycle period',
			'latest_order_id' => 'Latest order ID',
			'latest_order_code' => 'Latest order code',
			'latest_order_membership_id' => 'Latest order membership level ID',
			'latest_order_status' => 'Latest order status',
			'latest_order_gateway' => 'Latest order gateway',
			'latest_order_environment' => 'Latest order environment',
			'latest_order_payment_transaction_id' => 'Latest payment transaction ID',
			'latest_order_subscription_transaction_id' => 'Latest order subscription transaction ID',
			'latest_order_subtotal' => 'Latest order subtotal',
			'latest_order_tax' => 'Latest order tax',
			'latest_order_total' => 'Latest order total',
			'latest_order_timestamp' => 'Latest order date',
			'latest_order_discount_code' => 'Latest order discount code',
			'stripe_customer_id' => 'Stripe customer ID',
			'account_role' => 'Family account role',
			'parent_user_id' => 'Parent WordPress user ID',
			'parent_member_id' => 'Parent AAC member ID',
			'parent_email' => 'Parent email',
			'linked_account_slot_id' => 'Linked account slot ID',
			'family_membership_mode' => 'Family membership mode',
			'family_additional_adult' => 'Family additional adult',
			'family_dependent_count' => 'Family dependent count',
			'aac_group_account_group_id' => 'AAC group account group ID',
			'aac_group_account_checkout_code' => 'AAC group checkout code',
			'aac_group_account_child_level_id' => 'AAC group child level ID',
			'aac_group_account_synced_at' => 'AAC group synced at',
			'pmpro_group_account_id' => 'PMPro group account ID',
			'pmpro_group_account_checkout_code' => 'PMPro group checkout code',
			'pmpro_group_account_total_seats' => 'PMPro group total seats',
			'pmpro_group_account_active_members' => 'PMPro group active members',
			'connected_accounts_json' => 'Connected accounts JSON',
			'linked_parent_account_json' => 'Linked parent account JSON',
			'family_membership_json' => 'Family membership JSON',
			'pmpro_membership_history_json' => 'PMPro membership history JSON',
			'pmpro_subscriptions_json' => 'PMPro subscriptions JSON',
			'pmpro_orders_json' => 'PMPro orders JSON',
			'pmpro_group_accounts_json' => 'PMPro group accounts JSON',
			'all_user_meta_json' => 'All user meta JSON',
			'portal_profile_json' => 'Portal profile JSON',
			'mirrored_at' => 'Mirror updated at',
		];
	}

	private function format_member_export_field_label($field_key) {
		return ucwords(str_replace('_', ' ', (string) $field_key));
	}

	private function get_member_export_headers() {
		return [
			'wp_user_id',
			'user_login',
			'user_email',
			'user_registered',
			'display_name',
			'roles',
			'last_login_date',
			'login_count',
			'aac_member_id',
			'first_name',
			'last_name',
			'phone',
			'birthdate',
			'billing_address_1',
			'billing_address_2',
			'billing_city',
			'billing_state',
			'billing_zip',
			'billing_country',
			'shipping_address_different',
			'shipping_address_1',
			'shipping_address_2',
			'shipping_city',
			'shipping_state',
			'shipping_postal_code',
			'shipping_country',
			't_shirt_size',
			'aaj_preference',
			'aaj_preference_created_at',
			'aaj_preference_updated_at',
			'accidents_preference',
			'anac_preference_created_at',
			'anac_preference_updated_at',
			'acj_preference',
			'american_climbing_journal_preference_created_at',
			'american_climbing_journal_preference_updated_at',
			'guidebook_preference',
			'guidebook_preferences_created_at',
			'guidebook_preferences_updated_at',
			'email_opt_in',
			'sms_opt_in',
			'membership_discount_type',
			'student_university',
			'student_graduation_date',
			'military_service_component',
			'emergency_contact_first_name',
			'emergency_contact_last_name',
			'emergency_contact_phone',
			'emergency_contact_email',
			'emergency_contact_relationship',
			'current_membership_id',
			'current_membership_level',
			'current_membership_status',
			'membership_startdate',
			'membership_enddate',
			'membership_modified',
			'member_since',
			'renewal_date',
			'expiration_date',
			'valid_through_date',
			'auto_renew',
			'current_subscription_id',
			'current_subscription_gateway',
			'current_subscription_environment',
			'current_subscription_transaction_id',
			'current_subscription_status',
			'current_subscription_startdate',
			'current_subscription_enddate',
			'current_subscription_next_payment_date',
			'current_subscription_billing_amount',
			'current_subscription_cycle_number',
			'current_subscription_cycle_period',
			'latest_order_id',
			'latest_order_code',
			'latest_order_membership_id',
			'latest_order_status',
			'latest_order_gateway',
			'latest_order_environment',
			'latest_order_payment_transaction_id',
			'latest_order_subscription_transaction_id',
			'latest_order_subtotal',
			'latest_order_tax',
			'latest_order_total',
			'latest_order_timestamp',
			'latest_order_discount_code',
			'stripe_customer_id',
			'account_role',
			'parent_user_id',
			'parent_member_id',
			'parent_email',
			'linked_account_slot_id',
			'family_membership_mode',
			'family_additional_adult',
			'family_dependent_count',
			'group_account_id',
			'group_account_code',
			'group_account_seat_count',
			'aac_group_account_group_id',
			'aac_group_account_checkout_code',
			'aac_group_account_child_level_id',
			'aac_group_account_synced_at',
			'pmpro_group_account_id',
			'pmpro_group_account_checkout_code',
			'pmpro_group_account_total_seats',
			'pmpro_group_account_active_members',
			'connected_accounts_json',
			'linked_parent_account_json',
			'family_membership_json',
			'pmpro_membership_history_json',
			'pmpro_subscriptions_json',
			'pmpro_orders_json',
			'pmpro_group_accounts_json',
			'all_user_meta_json',
			'portal_profile_json',
			'mirrored_at',
		];
	}

	private function get_member_export_user_ids() {
		global $wpdb;

		$user_ids = [];
		if ($wpdb && !empty($wpdb->pmpro_memberships_users)) {
			$pmpro_user_ids = $wpdb->get_col("SELECT DISTINCT user_id FROM {$wpdb->pmpro_memberships_users} WHERE user_id > 0"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- static PMPro table name.
			$user_ids = array_merge($user_ids, array_map('intval', (array) $pmpro_user_ids));
		}

		if ($wpdb) {
			$profile_user_ids = $wpdb->get_col('SELECT DISTINCT user_id FROM ' . self::profiles_table() . ' WHERE user_id > 0'); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- plugin table name.
			$user_ids = array_merge($user_ids, array_map('intval', (array) $profile_user_ids));
		}

		$member_id_users = get_users([
			'meta_key' => 'aac_member_id',
			'number' => -1,
			'fields' => 'ids',
		]);
		$user_ids = array_merge($user_ids, array_map('intval', (array) $member_id_users));
		$user_ids = array_values(array_unique(array_filter($user_ids)));
		sort($user_ids);

		return $user_ids;
	}

	public function build_member_export_row($user_id, $selected_headers = null) {
		$user = get_user_by('id', $user_id);
		if (!$user instanceof WP_User) return [];
		$needs = static function ($fields) use ($selected_headers) { return $selected_headers === null || (bool) array_intersect($selected_headers, $fields); };
		// Sources are lazy and cached once per member. Creating a field resolver performs no queries.
		$cache = [];
		$loaders = [];
		$get = static function ($source) use (&$cache, &$loaders) {
			if (!array_key_exists($source, $cache)) $cache[$source] = $loaders[$source]();
			return $cache[$source];
		};
		$loaders = [
			'profile_row' => fn() => $this->get_profile_row($user_id),
			'profile' => fn() => is_array($get('profile_row')) ? $this->decode_profile_row($get('profile_row')) : ['account_info' => [], 'profile_info' => [], 'benefits_info' => [], 'family_membership' => [], 'connected_accounts' => [], 'linked_parent_account' => [], 'raw' => []],
			'account_info' => fn() => $get('profile')['account_info'],
			'profile_info' => fn() => $get('profile')['profile_info'],
			'family_membership' => fn() => $get('profile')['family_membership'],
			'connected_accounts' => fn() => $get('profile')['connected_accounts'],
			'linked_parent_account' => fn() => $get('profile')['linked_parent_account'],
			'latest_membership' => fn() => $this->get_latest_pmpro_row($user_id, 'pmpro_memberships_users'),
			'latest_subscription' => fn() => $this->get_latest_pmpro_row($user_id, 'pmpro_subscriptions'),
			'latest_order' => fn() => $this->get_latest_pmpro_row($user_id, 'pmpro_membership_orders'),
			'user_meta' => fn() => $this->get_user_meta_export_values($user_id),
			'group_summary' => fn() => AAC_Member_Portal_Group_Accounts::is_available() ? AAC_Member_Portal_Group_Accounts::get_group_summary_for_parent($user_id) : null,
			'group_account' => fn() => AAC_Member_Portal_Group_Accounts::is_available() ? AAC_Member_Portal_Group_Accounts::get_group_summary_for_user($user_id) : null,
			'pmpro_group_rows' => fn() => $this->get_pmpro_group_account_rows($user_id),
			'parent_user_id' => fn() => absint($get('user_meta')['aac_linked_parent_user_id'] ?? ($get('profile_row')['parent_user_id'] ?? 0)),
			'parent_user' => fn() => $get('parent_user_id') > 0 ? get_user_by('id', $get('parent_user_id')) : null,
			'membership_level_id' => fn() => (int) ($get('latest_membership')['membership_id'] ?? ($get('profile_info')['level_id'] ?? 0)),
			'latest_order_discount_code' => fn() => $this->get_discount_code_for_order($get('latest_order')),
			'preference_timestamps' => fn() => AAC_Member_Portal_Publication_Preferences::get_timestamps($user_id),
		];
		$fields = [
			'wp_user_id' => fn() => (string) $user_id,
			'user_login' => fn() => $user->user_login,
			'user_email' => fn() => $user->user_email,
			'user_registered' => fn() => $user->user_registered,
			'display_name' => fn() => $user->display_name,
			'roles' => fn() => implode('|', (array) $user->roles),
			'last_login_date' => fn() => $needs(['last_login_date']) ? $this->get_member_last_login_date($user_id) : '',
			'login_count' => fn() => $needs(['login_count']) ? (string) $this->get_member_login_count($user_id) : '',
			'aac_member_id' => fn() => $this->first_present_value([
				$get('user_meta')['aac_member_id'] ?? '',
				$get('profile_info')['member_id'] ?? '',
				$get('profile_row')['member_id'] ?? '',
			]),
			'first_name' => fn() => $this->first_present_value([$get('account_info')['first_name'] ?? '', $get('user_meta')['first_name'] ?? '']),
			'last_name' => fn() => $this->first_present_value([$get('account_info')['last_name'] ?? '', $get('user_meta')['last_name'] ?? '']),
			'phone' => fn() => $this->first_present_value([$get('account_info')['phone'] ?? '', $get('user_meta')['pmpro_sphone'] ?? '', $get('user_meta')['bphone'] ?? '']),
			'birthdate' => fn() => $this->first_present_value([$get('account_info')['birthdate'] ?? '', $get('user_meta')['birthdate'] ?? '']),
			'billing_address_1' => fn() => $this->first_present_value([$get('account_info')['street'] ?? '', $get('user_meta')['pmpro_saddress1'] ?? '', $get('user_meta')['baddress1'] ?? '']),
			'billing_address_2' => fn() => $this->first_present_value([$get('account_info')['address2'] ?? '', $get('user_meta')['pmpro_saddress2'] ?? '', $get('user_meta')['baddress2'] ?? '']),
			'billing_city' => fn() => $this->first_present_value([$get('account_info')['city'] ?? '', $get('user_meta')['pmpro_scity'] ?? '', $get('user_meta')['bcity'] ?? '']),
			'billing_state' => fn() => $this->first_present_value([$get('account_info')['state'] ?? '', $get('user_meta')['pmpro_sstate'] ?? '', $get('user_meta')['bstate'] ?? '']),
			'billing_zip' => fn() => $this->first_present_value([$get('account_info')['zip'] ?? '', $get('user_meta')['pmpro_szipcode'] ?? '', $get('user_meta')['bzipcode'] ?? '']),
			'billing_country' => fn() => $this->first_present_value([$get('account_info')['country'] ?? '', $get('user_meta')['pmpro_scountry'] ?? '', $get('user_meta')['bcountry'] ?? '']),
			'shipping_address_different' => fn() => !empty($get('account_info')['shipping_address_different']) ? 'true' : 'false',
			'shipping_address_1' => fn() => $get('account_info')['shipping_address_1'] ?? '',
			'shipping_address_2' => fn() => $get('account_info')['shipping_address_2'] ?? '',
			'shipping_city' => fn() => $get('account_info')['shipping_city'] ?? '',
			'shipping_state' => fn() => $get('account_info')['shipping_state'] ?? '',
			'shipping_postal_code' => fn() => $get('account_info')['shipping_postal_code'] ?? '',
			'shipping_country' => fn() => $get('account_info')['shipping_country'] ?? '',
			't_shirt_size' => fn() => $this->first_present_value([$get('account_info')['size'] ?? '', $get('user_meta')['t_shirt'] ?? '']),
			'aaj_preference' => fn() => $this->first_present_value([$get('account_info')['aaj_pref'] ?? '', $get('user_meta')['aaj_preference'] ?? '']),
			'aaj_preference_created_at' => fn() => (string) ($get('preference_timestamps')['aaj_preference_created_at'] ?? ''),
			'aaj_preference_updated_at' => fn() => (string) ($get('preference_timestamps')['aaj_preference_updated_at'] ?? ''),
			'accidents_preference' => fn() => $this->first_present_value([$get('account_info')['anac_pref'] ?? '', $get('user_meta')['anac_preference'] ?? '']),
			'anac_preference_created_at' => fn() => (string) ($get('preference_timestamps')['anac_preference_created_at'] ?? ''),
			'anac_preference_updated_at' => fn() => (string) ($get('preference_timestamps')['anac_preference_updated_at'] ?? ''),
			'acj_preference' => fn() => $this->first_present_value([$get('account_info')['acj_pref'] ?? '', $get('user_meta')['american_climbing_journal_preference'] ?? '']),
			'american_climbing_journal_preference_created_at' => fn() => (string) ($get('preference_timestamps')['american_climbing_journal_preference_created_at'] ?? ''),
			'american_climbing_journal_preference_updated_at' => fn() => (string) ($get('preference_timestamps')['american_climbing_journal_preference_updated_at'] ?? ''),
			'guidebook_preference' => fn() => $this->first_present_value([$get('account_info')['guidebook_pref'] ?? '', $get('user_meta')['guidebook_preferences'] ?? '']),
			'guidebook_preferences_created_at' => fn() => (string) ($get('preference_timestamps')['guidebook_preferences_created_at'] ?? ''),
			'guidebook_preferences_updated_at' => fn() => (string) ($get('preference_timestamps')['guidebook_preferences_updated_at'] ?? ''),
			'email_opt_in' => fn() => !empty($get('profile_row')['email_opt_in']) ? 'true' : 'false',
			'sms_opt_in' => fn() => !empty($get('profile_row')['sms_opt_in']) ? 'true' : 'false',
			'membership_discount_type' => fn() => $this->first_present_value([$get('account_info')['membership_discount_type'] ?? '', $get('user_meta')['aac_membership_discount_type'] ?? '']),
			'student_university' => fn() => $this->first_present_value([$get('user_meta')['university_school'] ?? '', $get('user_meta')['student_university'] ?? '', $get('user_meta')['aac_student_university'] ?? '']),
			'student_graduation_date' => fn() => $this->first_present_value([$get('user_meta')['graduation_date'] ?? '', $get('user_meta')['student_graduation_date'] ?? '', $get('user_meta')['aac_graduation_date'] ?? '']),
			'military_service_component' => fn() => $this->first_present_value([$get('user_meta')['service_component'] ?? '', $get('user_meta')['military_service_component'] ?? '', $get('user_meta')['aac_service_component'] ?? '']),
			'emergency_contact_first_name' => fn() => $get('account_info')['emergency_contact_first_name'] ?? '',
			'emergency_contact_last_name' => fn() => $get('account_info')['emergency_contact_last_name'] ?? '',
			'emergency_contact_phone' => fn() => $get('account_info')['emergency_contact_phone'] ?? '',
			'emergency_contact_email' => fn() => $get('account_info')['emergency_contact_email'] ?? '',
			'emergency_contact_relationship' => fn() => $get('account_info')['emergency_contact_relationship'] ?? '',
			'current_membership_id' => fn() => (string) $get('membership_level_id'),
			'current_membership_level' => fn() => $this->first_present_value([$get('profile_row')['membership_level'] ?? '', $get('profile_info')['tier'] ?? '', $needs(['current_membership_level']) ? $this->get_pmpro_level_name($get('membership_level_id')) : '']),
			'current_membership_status' => fn() => $this->first_present_value([$get('profile_row')['membership_status'] ?? '', $get('profile_info')['status'] ?? '', $get('latest_membership')['status'] ?? '']),
			'membership_startdate' => fn() => (string) ($get('latest_membership')['startdate'] ?? ''),
			'membership_enddate' => fn() => (string) ($get('latest_membership')['enddate'] ?? ''),
			'membership_modified' => fn() => (string) ($get('latest_membership')['modified'] ?? ''),
			'member_since' => fn() => (string) (($get('profile_info')['member_since_date'] ?? '') ?: (($get('profile_info')['member_since_year'] ?? '') ?: ($get('profile_info')['joined_date'] ?? ''))),
			'renewal_date' => fn() => $this->first_present_value([$get('profile_row')['renewal_date'] ?? '', $get('profile_info')['renewal_date'] ?? '']),
			'expiration_date' => fn() => $this->first_present_value([$get('profile_row')['expiration_date'] ?? '', $get('profile_info')['expiration_date'] ?? '']),
			'valid_through_date' => fn() => (string) ($get('profile_info')['valid_through_date'] ?? ''),
			'auto_renew' => fn() => !empty($get('account_info')['auto_renew']) ? 'true' : 'false',
			'current_subscription_id' => fn() => (string) ($get('latest_subscription')['id'] ?? ''),
			'current_subscription_gateway' => fn() => (string) ($get('latest_subscription')['gateway'] ?? ''),
			'current_subscription_environment' => fn() => (string) ($get('latest_subscription')['gateway_environment'] ?? ''),
			'current_subscription_transaction_id' => fn() => (string) ($get('latest_subscription')['subscription_transaction_id'] ?? ''),
			'current_subscription_status' => fn() => (string) ($get('latest_subscription')['status'] ?? ''),
			'current_subscription_startdate' => fn() => (string) ($get('latest_subscription')['startdate'] ?? ''),
			'current_subscription_enddate' => fn() => (string) ($get('latest_subscription')['enddate'] ?? ''),
			'current_subscription_next_payment_date' => fn() => (string) ($get('latest_subscription')['next_payment_date'] ?? ''),
			'current_subscription_billing_amount' => fn() => (string) ($get('latest_subscription')['billing_amount'] ?? ''),
			'current_subscription_cycle_number' => fn() => (string) ($get('latest_subscription')['cycle_number'] ?? ''),
			'current_subscription_cycle_period' => fn() => (string) ($get('latest_subscription')['cycle_period'] ?? ''),
			'latest_order_id' => fn() => (string) ($get('latest_order')['id'] ?? ''),
			'latest_order_code' => fn() => (string) ($get('latest_order')['code'] ?? ''),
			'latest_order_membership_id' => fn() => (string) ($get('latest_order')['membership_id'] ?? ''),
			'latest_order_status' => fn() => (string) ($get('latest_order')['status'] ?? ''),
			'latest_order_gateway' => fn() => (string) ($get('latest_order')['gateway'] ?? ''),
			'latest_order_environment' => fn() => (string) ($get('latest_order')['gateway_environment'] ?? ''),
			'latest_order_payment_transaction_id' => fn() => (string) ($get('latest_order')['payment_transaction_id'] ?? ''),
			'latest_order_subscription_transaction_id' => fn() => (string) ($get('latest_order')['subscription_transaction_id'] ?? ''),
			'latest_order_subtotal' => fn() => (string) ($get('latest_order')['subtotal'] ?? ''),
			'latest_order_tax' => fn() => (string) ($get('latest_order')['tax'] ?? ''),
			'latest_order_total' => fn() => (string) ($get('latest_order')['total'] ?? ''),
			'latest_order_timestamp' => fn() => (string) ($get('latest_order')['timestamp'] ?? ''),
			'latest_order_discount_code' => fn() => $get('latest_order_discount_code'),
			'stripe_customer_id' => fn() => $this->first_present_value([$get('user_meta')['pmpro_stripe_customerid'] ?? '', $get('user_meta')['stripe_customer_id'] ?? '']),
			'account_role' => fn() => $this->first_present_value([$get('profile_row')['account_role'] ?? '', $get('user_meta')['aac_family_account_role'] ?? '']),
			'parent_user_id' => fn() => $get('parent_user_id') > 0 ? (string) $get('parent_user_id') : '',
			'parent_member_id' => fn() => $needs(['parent_member_id']) && $get('parent_user_id') > 0 ? (string) get_user_meta($get('parent_user_id'), 'aac_member_id', true) : '',
			'parent_email' => fn() => $get('parent_user') instanceof WP_User ? $get('parent_user')->user_email : '',
			'linked_account_slot_id' => fn() => (string) ($get('user_meta')['aac_linked_account_slot_id'] ?? ''),
			'family_membership_mode' => fn() => (string) ($get('family_membership')['mode'] ?? ''),
			'family_additional_adult' => fn() => !empty($get('family_membership')['additional_adult']) ? 'true' : 'false',
			'family_dependent_count' => fn() => isset($get('family_membership')['dependent_count']) ? (string) $get('family_membership')['dependent_count'] : '',
			'group_account_id' => fn() => $get('group_account') ? (string) ($get('group_account')['id'] ?? '') : '',
			'group_account_code' => fn() => $get('group_account') ? (string) ($get('group_account')['checkout_code'] ?? '') : '',
			'group_account_seat_count' => fn() => $get('group_account') ? (string) ($get('group_account')['total_seats'] ?? '') : '',
			'aac_group_account_group_id' => fn() => (string) ($get('user_meta')['aac_group_account_group_id'] ?? ''),
			'aac_group_account_checkout_code' => fn() => (string) ($get('user_meta')['aac_group_account_checkout_code'] ?? ''),
			'aac_group_account_child_level_id' => fn() => (string) ($get('user_meta')['aac_group_account_child_level_id'] ?? ''),
			'aac_group_account_synced_at' => fn() => (string) ($get('user_meta')['aac_group_account_synced_at'] ?? ''),
			'pmpro_group_account_id' => fn() => $get('group_summary') ? (string) ($get('group_summary')['id'] ?? '') : '',
			'pmpro_group_account_checkout_code' => fn() => $get('group_summary') ? (string) ($get('group_summary')['checkout_code'] ?? '') : '',
			'pmpro_group_account_total_seats' => fn() => $get('group_summary') ? (string) ($get('group_summary')['total_seats'] ?? '') : '',
			'pmpro_group_account_active_members' => fn() => $get('group_summary') ? (string) ($get('group_summary')['active_members'] ?? '') : '',
			'connected_accounts_json' => fn() => $needs(['connected_accounts_json']) ? $this->encode_export_json($get('connected_accounts')) : '',
			'linked_parent_account_json' => fn() => $needs(['linked_parent_account_json']) ? $this->encode_export_json($get('linked_parent_account')) : '',
			'family_membership_json' => fn() => $needs(['family_membership_json']) ? $this->encode_export_json($get('family_membership')) : '',
			'pmpro_membership_history_json' => fn() => $needs(['pmpro_membership_history_json']) ? $this->encode_export_json($this->get_all_pmpro_rows($user_id, 'pmpro_memberships_users')) : '',
			'pmpro_subscriptions_json' => fn() => $needs(['pmpro_subscriptions_json']) ? $this->encode_export_json($this->get_all_pmpro_rows($user_id, 'pmpro_subscriptions')) : '',
			'pmpro_orders_json' => fn() => $needs(['pmpro_orders_json']) ? $this->encode_export_json($this->get_all_pmpro_rows($user_id, 'pmpro_membership_orders')) : '',
			'pmpro_group_accounts_json' => fn() => $needs(['pmpro_group_accounts_json']) ? $this->encode_export_json($get('pmpro_group_rows')) : '',
			'all_user_meta_json' => fn() => $needs(['all_user_meta_json']) ? $this->encode_export_json($get('user_meta')) : '',
			'portal_profile_json' => fn() => $needs(['portal_profile_json']) ? $this->encode_export_json($get('profile')['raw']) : '',
			'mirrored_at' => fn() => (string) ($get('profile_row')['mirrored_at'] ?? ''),
		];
		$row = [];
		foreach ($selected_headers ?? array_keys($fields) as $header) {
			if (isset($fields[$header])) $row[$header] = $fields[$header]();
		}
		return $row;
	}

	private function get_user_meta_export_values($user_id) {
		$raw_meta = get_user_meta($user_id);
		$meta = [];
		foreach ((array) $raw_meta as $key => $values) {
			$value = is_array($values) && count($values) === 1 ? $values[0] : $values;
			$meta[$key] = maybe_unserialize($value);
		}

		ksort($meta);
		return $meta;
	}

	private function get_all_pmpro_rows($user_id, $wpdb_property) {
		global $wpdb;

		if (!$wpdb || empty($wpdb->{$wpdb_property})) {
			return [];
		}

		$table = $wpdb->{$wpdb_property};
		$rows = $wpdb->get_results(
			$wpdb->prepare("SELECT * FROM {$table} WHERE user_id = %d ORDER BY id DESC", $user_id),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.

		return is_array($rows) ? $rows : [];
	}

	private function get_pmpro_group_account_rows($user_id) {
		global $wpdb;

		if (!$wpdb) {
			return [];
		}

		$like = $wpdb->esc_like($wpdb->prefix . 'pmprogroupacct') . '%';
		$tables = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $like));
		$rows = [];
		foreach ((array) $tables as $table) {
			$table = preg_replace('/[^A-Za-z0-9_]/', '', (string) $table);
			if ($table === '') {
				continue;
			}

			$columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table names discovered from DB and sanitized above.
			$columns = is_array($columns) ? $columns : [];
			$user_columns = array_values(array_intersect($columns, [
				'user_id',
				'parent_user_id',
				'child_user_id',
				'group_parent_user_id',
				'group_child_user_id',
			]));
			if (!$user_columns) {
				continue;
			}

			$where_parts = array_map(function ($column) {
				return $column . ' = %d';
			}, $user_columns);
			$query = $wpdb->prepare(
				"SELECT * FROM {$table} WHERE " . implode(' OR ', $where_parts) . ' ORDER BY 1 DESC',
				array_fill(0, count($where_parts), $user_id)
			);
			$table_rows = $wpdb->get_results($query, ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
			foreach ((array) $table_rows as $row) {
				$rows[] = [
					'table' => $table,
					'row' => $row,
				];
			}
		}

		return $rows;
	}

	private function get_pmpro_level_name($level_id) {
		$level_id = absint($level_id);
		if ($level_id <= 0 || !function_exists('pmpro_getLevel')) {
			return '';
		}

		$level = pmpro_getLevel($level_id);
		return is_object($level) && !empty($level->name) ? (string) $level->name : '';
	}

	private function get_discount_code_for_order($order) {
		global $wpdb;

		if (!is_array($order) || empty($order['id']) || !$wpdb || empty($wpdb->pmpro_discount_codes_uses) || empty($wpdb->pmpro_discount_codes)) {
			return '';
		}

		$code = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT dc.code
				FROM {$wpdb->pmpro_discount_codes_uses} dcu
				INNER JOIN {$wpdb->pmpro_discount_codes} dc ON dc.id = dcu.code_id
				WHERE dcu.order_id = %d
				ORDER BY dcu.id DESC
				LIMIT 1",
				(int) $order['id']
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.

		return is_string($code) ? $code : '';
	}

	private function first_present_value($values) {
		foreach ((array) $values as $value) {
			if (is_scalar($value) && trim((string) $value) !== '') {
				return (string) $value;
			}
		}

		return '';
	}

	private function encode_export_json($value) {
		if ($value === null || $value === '') {
			return '';
		}

		$json = wp_json_encode($value, JSON_UNESCAPED_SLASHES);
		return is_string($json) ? $json : '';
	}


	public static function editable_member_fields() {
		return [
			'first_name' => 'First name', 'last_name' => 'Last name', 'email' => 'Email',
			'phone' => 'Phone', 'birthdate' => 'Birthdate',
			'street' => 'Street address', 'address2' => 'Address 2', 'city' => 'City',
			'state' => 'State / Province', 'zip' => 'ZIP / Postal code', 'country' => 'Country',
			'shipping_address_different' => 'Use a separate shipping address',
			'shipping_address_1' => 'Shipping street address', 'shipping_address_2' => 'Shipping address 2',
			'shipping_city' => 'Shipping city', 'shipping_state' => 'Shipping state / province',
			'shipping_postal_code' => 'Shipping ZIP / Postal code', 'shipping_country' => 'Shipping country',
			'emergency_contact_first_name' => 'Emergency contact first name',
			'emergency_contact_last_name' => 'Emergency contact last name',
			'emergency_contact_phone' => 'Emergency contact phone',
			'emergency_contact_email' => 'Emergency contact email',
			'emergency_contact_relationship' => 'Emergency contact relationship',
			'student_university' => 'School / University', 'graduation_date' => 'Graduation date',
			'service_component' => 'Service component', 'size' => 'T-shirt size',
			'aaj_pref' => 'AAJ preference', 'anac_pref' => 'Accidents preference',
			'acj_pref' => 'ACJ preference', 'guidebook_pref' => 'Guidebook preference',
			'aac_email_marketing_opt_in' => 'Email communication opt-in',
			'aac_sms_marketing_opt_in' => 'SMS communication opt-in',
		];
	}

	public function handle_edit_member() {
		$member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;
		if (!current_user_can('manage_options') || !current_user_can('edit_user', $member_id)) {
			wp_die('You cannot edit this member.', '', ['response' => 403]);
		}
		check_admin_referer('aac_edit_member_' . $member_id);
		$changes = isset($_POST['member']) && is_array($_POST['member']) ? wp_unslash($_POST['member']) : [];
		$api = AAC_Member_Portal_API::get_instance();
		$result = $api ? $api->admin_update_member($member_id, $changes) : new WP_Error('unavailable', 'Member editing is unavailable.');
		if (is_wp_error($result)) {
			wp_die(esc_html($result->get_error_message()), 'Member not saved', ['back_link' => true]);
		}
		update_user_meta($member_id, 'aac_last_admin_profile_edit', ['admin_id' => get_current_user_id(), 'at' => current_time('mysql')]);
		$this->sync_member($member_id);
		wp_safe_redirect($this->build_admin_url(['member_id' => $member_id, 'tab' => 'profile', 'aac_member_saved' => 1]));
		exit;
	}

	public function render_edit_member_form($member_id, $embedded = false) {
		if (!current_user_can('edit_user', $member_id)) {
			echo '<p>You cannot edit this member.</p>';
			return;
		}
		$api = AAC_Member_Portal_API::get_instance();
		$profile = $api ? $api->get_profile_for_user($member_id) : [];
		$account = $profile['account_info'] ?? [];
		$required = $embedded ? ['email'] : ['first_name', 'last_name', 'email', 'street', 'city', 'state', 'zip', 'country'];
		?>
		<?php if (!$embedded) : ?>
		<h3>Edit Member</h3>
		<p>Update this member's profile and publication preferences. Fields marked * are required.</p>
		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
			<input type="hidden" name="action" value="aac_member_portal_edit_member" />
			<input type="hidden" name="member_id" value="<?php echo esc_attr($member_id); ?>" />
			<?php wp_nonce_field('aac_edit_member_' . $member_id); ?>
		<?php endif; ?>
			<table class="form-table"><tbody>
			<?php foreach (self::editable_member_fields() as $key => $label) :
				$value = $account[$key] ?? '';
				$is_required = in_array($key, $required, true);
				$options = null;
				if (substr($key, -5) === '_pref') { $options = ['Print', 'Digital']; }
				if ($key === 'service_component') { $options = ['Active', 'Reserve', 'Veteran', 'Retired']; }
				if ($key === 'size') { $options = ['No T-shirt', 'Unisex Small', 'Unisex Medium', 'Unisex Large', 'Unisex X-Large', 'Unisex XX-Large']; }
				if ($key === 'emergency_contact_relationship') { $options = $account['emergency_contact_relationship_options'] ?? null; }
				?>
				<tr><th><label for="aac-edit-<?php echo esc_attr($key); ?>"><?php echo esc_html($label . ($is_required ? ' *' : '')); ?></label></th><td>
				<?php if (in_array($key, ['shipping_address_different', 'aac_email_marketing_opt_in', 'aac_sms_marketing_opt_in'], true)) : ?>
					<input type="hidden" name="member[<?php echo esc_attr($key); ?>]" value="0" />
					<input id="aac-edit-<?php echo esc_attr($key); ?>" type="checkbox" name="member[<?php echo esc_attr($key); ?>]" value="1" <?php checked(!empty($value)); ?> />
				<?php elseif (is_array($options) && !empty($options) && is_string(reset($options))) :
					$options = array_unique(array_merge(['', (string) $value], array_values($options))); ?>
					<select id="aac-edit-<?php echo esc_attr($key); ?>" name="member[<?php echo esc_attr($key); ?>]">
					<?php foreach ($options as $option) : ?><option value="<?php echo esc_attr($option); ?>" <?php selected((string) $value, $option); ?>><?php echo esc_html($option ?: 'Select'); ?></option><?php endforeach; ?>
					</select>
				<?php else :
					$type = strpos($key, 'email') !== false ? 'email' : (in_array($key, ['birthdate', 'graduation_date'], true) ? 'date' : 'text'); ?>
					<input class="regular-text" id="aac-edit-<?php echo esc_attr($key); ?>" type="<?php echo esc_attr($type); ?>" name="member[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($value); ?>" <?php echo $is_required ? 'required' : ''; ?> />
				<?php endif; ?>
				</td></tr>
			<?php endforeach; ?>
			</tbody></table>
		<?php if (!$embedded) : ?>
			<?php submit_button('Save Member Changes'); ?>
			<a class="button" href="<?php echo esc_url($this->build_admin_url(['member_id' => $member_id])); ?>">Cancel</a>
		</form>
		<?php endif; ?>
		<?php
	}

	public function render_admin_page() {
		if (!current_user_can('manage_options')) {
			return;
		}

		$member_id = isset($_GET['member_id']) ? absint(wp_unslash($_GET['member_id'])) : 0;
		$tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'profile';
		$search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
		$paged = isset($_GET['paged']) ? max(1, absint(wp_unslash($_GET['paged']))) : 1;
		$did_sync = false;
		$sync_all_count = null;

		if (isset($_GET['aac_sync_all']) && '1' === wp_unslash($_GET['aac_sync_all'])) {
			check_admin_referer('aac_member_db_sync_all');
			$sync_all_count = $this->sync_all_members();
		}

		if ($member_id > 0) {
			$did_sync = $this->sync_member($member_id);
		}

		$member_list = $this->get_member_list_rows($search, $paged, 20);
		$profile_row = $member_id > 0 ? $this->get_profile_row($member_id) : null;
		$history_rows = $member_id > 0 ? $this->get_mirror_rows(self::history_table(), $member_id) : [];
		$subscription_rows = $member_id > 0 ? $this->get_mirror_rows(self::subscriptions_table(), $member_id) : [];
		$transaction_rows = $member_id > 0 ? $this->get_mirror_rows(self::transactions_table(), $member_id) : [];
		?>
		<div class="wrap">
			<h1>Member Database</h1>
			<?php self::render_database_tools_nav(self::PAGE_SLUG); ?>
			<p>This AAC-owned backend mirror stores a portal copy of member profile data plus mirrored PMPro membership history, subscriptions, and transactions.</p>

			<form method="get" style="margin:16px 0 24px;">
				<input type="hidden" name="page" value="<?php echo esc_attr(self::PAGE_SLUG); ?>" />
				<input
					type="search"
					name="s"
					value="<?php echo esc_attr($search); ?>"
					placeholder="Search by name or email"
					class="regular-text"
				/>
				<?php submit_button('Search Members', 'secondary', '', false); ?>
				<a
					class="button button-secondary"
					style="margin-left:8px;"
					href="<?php echo esc_url(wp_nonce_url($this->build_admin_url(['member_id' => $member_id, 'tab' => $tab, 's' => $search, 'paged' => $paged, 'aac_sync_all' => 1]), 'aac_member_db_sync_all')); ?>"
				>
						Run Manual Full Backfill / Repair
				</a>
				<a
					class="button button-primary"
					style="margin-left:8px;"
					href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'aac_member_portal_export_member_database'], admin_url('admin-post.php')), 'aac_member_portal_export_member_database')); ?>"
				>
					Export Selected Fields CSV
				</a>
			</form>

			<?php if ($sync_all_count !== null) : ?>
				<div class="notice notice-success inline" style="margin:0 0 16px;">
					<p><?php echo esc_html(sprintf('Checked %d members during the manual backfill. Only new or changed records were written.', $sync_all_count)); ?></p>
				</div>
			<?php endif; ?>

			<section style="background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:20px;margin-bottom:24px;">
				<div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap;">
					<div>
						<h2 style="margin:0;">All Mirrored Members</h2>
						<p style="margin:8px 0 0;color:#50575e;max-width:900px;">
							This table lists mirrored AAC Portal members with profile/contact details and their current membership status. Open a member to inspect deeper profile, preference, subscription, and transaction records.
						</p>
					</div>
					<div style="color:#50575e;font-size:13px;">
						<?php echo esc_html(sprintf('%d total mirrored members', (int) $member_list['total'])); ?>
					</div>
				</div>

				<?php if (!$member_list['rows']) : ?>
						<p style="margin-top:16px;">No mirrored members found. New and changed member events populate this table automatically; use the manual full backfill only for initial setup or repair.</p>
				<?php else : ?>
					<div style="overflow:auto;margin-top:20px;">
						<table class="widefat striped">
							<thead>
								<tr>
									<th style="min-width:140px;">Member Actions</th>
									<th style="min-width:180px;">Member</th>
									<th style="min-width:220px;">Email</th>
									<th style="min-width:160px;">Phone</th>
									<th style="min-width:140px;">City</th>
									<th style="min-width:120px;">State</th>
									<th style="min-width:140px;">Country</th>
									<th>User ID</th>
									<th>Member ID</th>
									<th>Membership Level</th>
									<th>Status</th>
									<th>Role</th>
									<th>Renewal</th>
									<th>Expiration</th>
									<th>Mirrored At</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($member_list['rows'] as $row) : ?>
									<?php $is_selected_member_row = $member_id > 0 && (int) $row['user_id'] === (int) $member_id && $profile_row; ?>
									<tr<?php echo $is_selected_member_row ? ' style="background:#f6f7f7;"' : ''; ?>>
										<td>
											<div style="display:grid;gap:6px;">
												<a class="button <?php echo $is_selected_member_row ? 'button-primary' : 'button-secondary'; ?>" href="<?php echo esc_url($is_selected_member_row ? $this->build_admin_url(['s' => $search, 'paged' => $paged]) : $this->build_admin_url(['member_id' => $row['user_id'], 'tab' => 'profile', 's' => $search, 'paged' => $paged])); ?>">
													<?php echo esc_html($is_selected_member_row ? 'Close Member' : 'Open Member'); ?>
												</a>
												<?php
                                                if (current_user_can('edit_user', (int) $row['user_id'])) : ?>
                                                    <a class="button button-primary" href="<?php echo esc_url($this->build_admin_url(['member_id' => $row['user_id'], 'tab' => 'edit', 's' => $search, 'paged' => $paged]) . '#aac-member-details'); ?>">Edit Member</a>
                                                <?php endif;
												$list_member_user = get_user_by('id', (int) $row['user_id']);
												$list_member_return_url = $this->build_admin_url(['member_id' => $row['user_id'], 'tab' => 'profile', 's' => $search, 'paged' => $paged]);
												if ($list_member_user instanceof WP_User && !user_can($list_member_user, 'manage_options')) :
													?>
													<a class="button button-primary" href="<?php echo esc_url(AAC_Member_Portal_Impersonation::get_switch_url($row['user_id'], $list_member_return_url)); ?>">
														View as Member
													</a>
												<?php endif; ?>
											</div>
										</td>
										<td style="white-space:normal;word-break:break-word;"><?php echo esc_html($row['display_name'] ?: 'Unknown member'); ?></td>
										<td style="white-space:normal;word-break:break-word;"><?php echo esc_html($row['email']); ?></td>
										<td style="white-space:normal;word-break:break-word;"><?php echo esc_html($row['account_info']['phone'] ?? ''); ?></td>
										<td style="white-space:normal;word-break:break-word;"><?php echo esc_html($row['account_info']['city'] ?? ''); ?></td>
										<td style="white-space:normal;word-break:break-word;"><?php echo esc_html($row['account_info']['state'] ?? ''); ?></td>
										<td style="white-space:normal;word-break:break-word;"><?php echo esc_html($row['account_info']['country'] ?? ''); ?></td>
										<td><?php echo esc_html($row['user_id']); ?></td>
										<td><?php echo esc_html($row['member_id']); ?></td>
										<td><?php echo esc_html($row['membership_level']); ?></td>
										<td><?php echo esc_html($row['membership_status']); ?></td>
										<td><?php echo esc_html($row['account_role'] ?: 'Standard'); ?></td>
										<td><?php echo esc_html($row['renewal_date']); ?></td>
										<td><?php echo esc_html($row['expiration_date']); ?></td>
										<td><?php echo esc_html($row['mirrored_at']); ?></td>
									</tr>
									<?php if ($is_selected_member_row) : ?>
										<tr>
											<td colspan="15" style="padding:0;background:#fff;">
												<div id="aac-member-details" style="padding:20px;border-left:4px solid #2271b1;border-bottom:1px solid #dcdcde;scroll-margin-top:48px;">
													<?php
													$this->render_member_detail_panel(
														$member_id,
														$profile_row,
														$tab,
														$search,
														$paged,
														$did_sync,
														$history_rows,
														$subscription_rows,
														$transaction_rows
													);
													?>
												</div>
											</td>
										</tr>
									<?php endif; ?>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>

					<?php echo $this->render_member_list_pagination($member_list['total'], $member_list['page'], $member_list['per_page'], $search, $member_id, $tab); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endif; ?>
			</section>

			<?php if ($member_id > 0 && !$profile_row) : ?>
				<div class="notice notice-warning"><p>This member could not be mirrored yet.</p></div>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_member_detail_panel($member_id, $profile_row, $tab, $search, $paged, $did_sync, $history_rows, $subscription_rows, $transaction_rows) {
		$tabs = [
			'profile' => 'Profile',
			'edit' => 'Edit Member',
			'preferences' => 'Preferences',
			'membership-history' => 'Membership History',
			'subscriptions' => 'Subscriptions',
			'transactions' => 'Transactions',
		];
		?>
		<div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap;">
			<div>
				<h2 style="margin:0;"><?php echo esc_html($profile_row['display_name'] ?: $profile_row['email']); ?></h2>
				<p style="margin:8px 0 0;color:#50575e;">
					<?php echo esc_html($profile_row['email']); ?> · User ID <?php echo esc_html($member_id); ?>
				</p>
			</div>
			<div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;">
				<a class="button button-secondary" href="<?php echo esc_url($this->build_admin_url(['member_id' => $member_id, 'tab' => $tab, 's' => $search, 'paged' => $paged])); ?>">
					Sync This Member
				</a>
				<?php
				$detail_member_user = get_user_by('id', $member_id);
				if ($detail_member_user instanceof WP_User && !user_can($detail_member_user, 'manage_options')) :
					?>
					<a class="button button-primary" href="<?php echo esc_url(AAC_Member_Portal_Impersonation::get_switch_url($member_id, $this->build_admin_url(['member_id' => $member_id, 'tab' => $tab, 's' => $search, 'paged' => $paged]))); ?>">
						View as Member
					</a>
				<?php endif; ?>
			</div>
		</div>

		<?php if (!empty($_GET['aac_member_saved'])) : ?><div class="notice notice-success inline"><p>Member changes saved.</p></div><?php endif; ?>
		<?php if ($did_sync) : ?>
			<div class="notice notice-success inline" style="margin:16px 0 0;">
				<p>Member mirror refreshed.</p>
			</div>
		<?php endif; ?>

		<nav class="nav-tab-wrapper" style="margin-top:20px;">
			<?php foreach ($tabs as $tab_key => $tab_label) : ?>
				<a class="nav-tab <?php echo $tab === $tab_key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url($this->build_admin_url(['member_id' => $member_id, 'tab' => $tab_key, 's' => $search, 'paged' => $paged])); ?>">
					<?php echo esc_html($tab_label); ?>
				</a>
			<?php endforeach; ?>
		</nav>

		<div style="margin-top:20px;">
			<?php
			if ($tab === 'edit') {
				$this->render_edit_member_form($member_id);
			} elseif ($tab === 'preferences') {
				$this->render_preferences_tab($profile_row);
			} elseif ($tab === 'membership-history') {
				$this->render_json_tab_table($history_rows, 'No mirrored membership history found.', 'membership');
			} elseif ($tab === 'subscriptions') {
				$this->render_json_tab_table($subscription_rows, 'No mirrored subscriptions found.', 'subscription');
			} elseif ($tab === 'transactions') {
				$this->render_json_tab_table($transaction_rows, 'No mirrored transactions found.', 'transaction');
			} else {
				$this->render_profile_tab($profile_row);
			}
			?>
		</div>
		<?php
	}

	private function render_profile_tab($profile_row) {
		$profile = $this->decode_profile_row($profile_row);
		$account_info = $profile['account_info'];
		$profile_info = $profile['profile_info'];
		$stored_group_account = is_array($profile['group_account'] ?? null) ? $profile['group_account'] : [];
		$live_group_account = class_exists('AAC_Member_Portal_Group_Accounts')
			? AAC_Member_Portal_Group_Accounts::get_group_summary_for_user((int) ($profile_row['user_id'] ?? 0))
			: null;
		$group_account = is_array($live_group_account) ? $live_group_account : $stored_group_account;
		?>
		<table class="widefat striped">
			<tbody>
				<tr><th style="width:240px;">First Name</th><td><?php echo esc_html($account_info['first_name'] ?? ''); ?></td></tr>
				<tr><th>Last Name</th><td><?php echo esc_html($account_info['last_name'] ?? ''); ?></td></tr>
				<tr><th>Display Name</th><td><?php echo esc_html($profile_row['display_name']); ?></td></tr>
				<tr><th>Email</th><td><?php echo esc_html($profile_row['email']); ?></td></tr>
				<tr><th>Last Login Date</th><td><?php echo esc_html($profile_row['last_login_date'] ?? ''); ?></td></tr>
				<tr><th>Login Count</th><td><?php echo esc_html((string) ($profile_row['login_count'] ?? 0)); ?></td></tr>
				<tr><th>Phone</th><td><?php echo esc_html($account_info['phone'] ?? ''); ?></td></tr>
				<tr><th>Birthdate</th><td><?php echo esc_html($profile_row['birthdate'] ?? ''); ?></td></tr>
				<tr><th>School / University</th><td><?php echo esc_html($profile_row['school_university'] ?? ''); ?></td></tr>
				<tr><th>Graduation Date</th><td><?php echo esc_html($profile_row['graduation_date'] ?? ''); ?></td></tr>
				<tr><th>Service Component</th><td><?php echo esc_html($profile_row['service_component'] ?? ''); ?></td></tr>
				<tr><th>Street</th><td><?php echo esc_html($account_info['street'] ?? ''); ?></td></tr>
				<tr><th>Address 2</th><td><?php echo esc_html($account_info['address2'] ?? ''); ?></td></tr>
				<tr><th>City</th><td><?php echo esc_html($account_info['city'] ?? ''); ?></td></tr>
				<tr><th>State</th><td><?php echo esc_html($account_info['state'] ?? ''); ?></td></tr>
				<tr><th>ZIP</th><td><?php echo esc_html($account_info['zip'] ?? ''); ?></td></tr>
				<tr><th>Country</th><td><?php echo esc_html($account_info['country'] ?? ''); ?></td></tr>
			</tbody>
		</table>

		<h3 style="margin-top:24px;">Emergency Contact</h3>
		<table class="widefat striped">
			<tbody>
				<tr><th style="width:240px;">First Name</th><td><?php echo esc_html($profile_row['emergency_contact_first_name'] ?? ''); ?></td></tr>
				<tr><th>Last Name</th><td><?php echo esc_html($profile_row['emergency_contact_last_name'] ?? ''); ?></td></tr>
				<tr><th>Phone</th><td><?php echo esc_html($profile_row['emergency_contact_phone'] ?? ''); ?></td></tr>
				<tr><th>Email</th><td><?php echo esc_html($profile_row['emergency_contact_email'] ?? ''); ?></td></tr>
				<tr><th>Relationship</th><td><?php echo esc_html($profile_row['emergency_contact_relationship'] ?? ''); ?></td></tr>
			</tbody>
		</table>

		<h3 style="margin-top:24px;">Current Membership</h3>
		<table class="widefat striped">
			<tbody>
				<tr><th style="width:240px;">Membership Level</th><td><?php echo esc_html($profile_row['membership_level']); ?></td></tr>
				<tr><th>Membership Status</th><td><?php echo esc_html($profile_row['membership_status']); ?></td></tr>
				<tr><th>Member ID</th><td><?php echo esc_html($profile_row['member_id']); ?></td></tr>
				<tr><th>Member Since</th><td><?php echo esc_html(($profile_info['member_since_date'] ?? '') ?: (($profile_info['member_since_year'] ?? '') ?: ($profile_info['joined_date'] ?? ''))); ?></td></tr>
				<tr><th>Renewal Date</th><td><?php echo esc_html($profile_row['renewal_date']); ?></td></tr>
				<tr><th>Expiration Date</th><td><?php echo esc_html($profile_row['expiration_date']); ?></td></tr>
				<tr><th>Account Role</th><td><?php echo esc_html($profile_row['account_role'] ?: 'Standard'); ?></td></tr>
				<tr><th>Mirrored At</th><td><?php echo esc_html($profile_row['mirrored_at']); ?></td></tr>
			</tbody>
		</table>

		<h3 style="margin-top:24px;">Group Account</h3>
		<table class="widefat striped">
			<tbody>
				<tr><th style="width:240px;">Group ID</th><td><?php echo esc_html((string) ($group_account['id'] ?? '')); ?></td></tr>
				<tr><th>Group Code</th><td><code><?php echo esc_html((string) ($group_account['checkout_code'] ?? '')); ?></code></td></tr>
				<tr><th>Seat Count</th><td><?php echo esc_html((string) ($group_account['total_seats'] ?? '')); ?></td></tr>
				<tr><th>Active Associated Accounts</th><td><?php echo esc_html((string) ($group_account['active_members'] ?? '')); ?></td></tr>
				<tr><th>Group Account Role</th><td><?php echo esc_html((string) ($group_account['account_role'] ?? '')); ?></td></tr>
				<tr><th>Parent User ID</th><td><?php echo esc_html((string) ($group_account['parent_user_id'] ?? '')); ?></td></tr>
			</tbody>
		</table>
		<?php
	}

	private function render_preferences_tab($profile_row) {
		$profile = $this->decode_profile_row($profile_row);
		$account_info = $profile['account_info'];
		$benefits_info = $profile['benefits_info'];
		$family_membership = $profile['family_membership'];
		$connected_accounts = is_array($profile['connected_accounts']) ? $profile['connected_accounts'] : [];
		$linked_parent_account = is_array($profile['linked_parent_account']) ? $profile['linked_parent_account'] : [];
		$preference_fields = [
			'T-Shirt Size' => $account_info['size'] ?? '',
			'AAJ Preference' => $account_info['aaj_pref'] ?? '',
			'AAJ Preference Created At' => $profile_row['aaj_preference_created_at'] ?? '',
			'AAJ Preference Updated At' => $profile_row['aaj_preference_updated_at'] ?? '',
			'ANAC Preference' => $account_info['anac_pref'] ?? '',
			'ANAC Preference Created At' => $profile_row['anac_preference_created_at'] ?? '',
			'ANAC Preference Updated At' => $profile_row['anac_preference_updated_at'] ?? '',
			'American Climbing Journal Preference' => $account_info['acj_pref'] ?? '',
			'American Climbing Journal Preference Created At' => $profile_row['american_climbing_journal_preference_created_at'] ?? '',
			'American Climbing Journal Preference Updated At' => $profile_row['american_climbing_journal_preference_updated_at'] ?? '',
			'Guidebook Preference' => $account_info['guidebook_pref'] ?? '',
			'Guidebook Preference Created At' => $profile_row['guidebook_preferences_created_at'] ?? '',
			'Guidebook Preference Updated At' => $profile_row['guidebook_preferences_updated_at'] ?? '',
			'Email Opt In' => !empty($profile_row['email_opt_in']) ? 'Yes' : 'No',
			'SMS Opt In' => !empty($profile_row['sms_opt_in']) ? 'Yes' : 'No',
			'Membership Discount Type' => $account_info['membership_discount_type'] ?? '',
			'Auto Renew' => !empty($account_info['auto_renew']) ? 'true' : 'false',
			'Family Mode' => $family_membership['mode'] ?? '',
			'Additional Adult' => !empty($family_membership['additional_adult']) ? 'true' : 'false',
			'Dependent Count' => isset($family_membership['dependent_count']) ? (string) $family_membership['dependent_count'] : '',
			'Connected Accounts Count' => (string) count($connected_accounts),
			'Linked Parent Account' => $linked_parent_account['name'] ?? '',
			'Rescue Amount' => isset($benefits_info['rescue_amount']) ? (string) $benefits_info['rescue_amount'] : '',
			'Medical Amount' => isset($benefits_info['medical_amount']) ? (string) $benefits_info['medical_amount'] : '',
			'Mortal Remains Amount' => isset($benefits_info['mortal_remains_amount']) ? (string) $benefits_info['mortal_remains_amount'] : '',
			'Rescue Reimbursement Process' => !empty($benefits_info['rescue_reimbursement_process']) ? 'true' : 'false',
		];
		$flat_profile = $this->flatten_assoc($profile);
		?>
		<table class="widefat striped">
			<tbody>
				<?php foreach ($preference_fields as $label => $value) : ?>
					<tr>
						<th style="width:280px;"><?php echo esc_html($label); ?></th>
						<td><?php echo esc_html($value); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_json_tab_table($rows, $empty_message, $record_type = '') {
		if (!$rows) {
			echo '<p>' . esc_html($empty_message) . '</p>';
			return;
		}

		$column_priority_map = [
			'membership' => [
				'membership_id',
				'status',
				'startdate',
				'enddate',
				'initial_payment',
				'billing_amount',
				'cycle_number',
				'cycle_period',
				'billing_limit',
				'trial_amount',
				'trial_limit',
				'modified',
			],
			'subscription' => [
				'id',
				'membership_id',
				'status',
				'gateway',
				'billing_amount',
				'cycle_number',
				'cycle_period',
				'next_payment_date',
				'startdate',
				'enddate',
				'subscription_transaction_id',
				'modified',
			],
			'transaction' => [
				'id',
				'membership_id',
				'code',
				'total',
				'subtotal',
				'tax',
				'payment_type',
				'status',
				'gateway',
				'payment_transaction_id',
				'subscription_transaction_id',
				'timestamp',
				'notes',
			],
		];
		$prioritized_columns = $column_priority_map[$record_type] ?? [];
		$decoded_rows = [];
		$present_keys = [];

		foreach ($rows as $row) {
			$record = json_decode($row['raw_record'] ?? '', true);
			$flat_record = $this->flatten_assoc($record);
			$decoded_rows[] = [
				'meta' => $row,
				'record' => $flat_record,
			];
			foreach ($flat_record as $key => $value) {
				if ($value !== '' && $value !== null) {
					$present_keys[$key] = true;
				}
			}
		}

		$all_keys = [];
		foreach ($prioritized_columns as $key) {
			if (!empty($present_keys[$key])) {
				$all_keys[] = $key;
			}
		}

		if (!$all_keys) {
			$all_keys = array_keys($present_keys);
			sort($all_keys);
		}

		?>
		<div style="overflow:auto;">
			<table class="widefat striped">
				<thead>
					<tr>
						<th>Source ID</th>
						<th>Status</th>
						<th>Date</th>
						<?php foreach ($all_keys as $key) : ?>
							<th><?php echo esc_html($key); ?></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($decoded_rows as $row) : ?>
						<tr>
							<td><?php echo esc_html($row['meta']['source_record_id']); ?></td>
							<td><?php echo esc_html($row['meta']['source_status']); ?></td>
							<td><?php echo esc_html($row['meta']['source_date']); ?></td>
							<?php foreach ($all_keys as $key) : ?>
								<td><?php echo esc_html($row['record'][$key] ?? ''); ?></td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private function get_member_list_rows($search = '', $page = 1, $per_page = 20) {
		global $wpdb;

		$page = max(1, (int) $page);
		$per_page = max(1, (int) $per_page);
		$offset = ($page - 1) * $per_page;
		$table = self::profiles_table();
		$where_sql = '1=1';
		$params = [];

		if ($search !== '') {
			$like = '%' . $wpdb->esc_like($search) . '%';
			$where_sql .= ' AND (display_name LIKE %s OR email LIKE %s OR member_id LIKE %s OR membership_level LIKE %s OR membership_status LIKE %s OR account_role LIKE %s OR raw_profile LIKE %s)';
			$params = [$like, $like, $like, $like, $like, $like, $like];
		}

		if ($params) {
			$total_sql = $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", $params);
			$rows_sql = $wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where_sql} ORDER BY display_name ASC, email ASC, user_id ASC LIMIT %d OFFSET %d",
				array_merge($params, [$per_page, $offset])
			);
		} else {
			$total_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
			$rows_sql = $wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where_sql} ORDER BY display_name ASC, email ASC, user_id ASC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			);
		}

		$total = (int) $wpdb->get_var($total_sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above when needed.
		$rows = $wpdb->get_results($rows_sql, ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.

		$member_rows = [];

		foreach ((array) $rows as $row) {
			$profile = $this->decode_profile_row($row);
			$row['account_info'] = $profile['account_info'];
			$row['profile_info'] = $profile['profile_info'];
			$member_rows[] = $row;
		}

		return [
			'total' => $total,
			'page' => $page,
			'per_page' => $per_page,
			'rows' => $member_rows,
		];
	}

	private function decode_profile_row($profile_row) {
		$profile = json_decode($profile_row['raw_profile'] ?? '', true);
		$profile = is_array($profile) ? $profile : [];

		return [
			'account_info' => is_array($profile['account_info'] ?? null) ? $profile['account_info'] : [],
			'profile_info' => is_array($profile['profile_info'] ?? null) ? $profile['profile_info'] : [],
			'benefits_info' => is_array($profile['benefits_info'] ?? null) ? $profile['benefits_info'] : [],
			'family_membership' => is_array($profile['family_membership'] ?? null) ? $profile['family_membership'] : [],
			'connected_accounts' => is_array($profile['connected_accounts'] ?? null) ? $profile['connected_accounts'] : [],
			'linked_parent_account' => is_array($profile['linked_parent_account'] ?? null) ? $profile['linked_parent_account'] : [],
			'raw' => $profile,
		];
	}

	private function render_member_list_pagination($total, $page, $per_page, $search, $member_id, $tab) {
		$total_pages = (int) ceil($total / max(1, $per_page));
		if ($total_pages <= 1) {
			return '';
		}

		$links = paginate_links([
			'base' => add_query_arg([
				'page' => self::PAGE_SLUG,
				's' => $search,
				'member_id' => $member_id,
				'tab' => $tab,
				'paged' => '%#%',
			], admin_url('admin.php')),
			'format' => '',
			'current' => max(1, $page),
			'total' => $total_pages,
			'type' => 'plain',
			'prev_text' => '&laquo;',
			'next_text' => '&raquo;',
		]);

		if (!$links) {
			return '';
		}

		return '<div class="tablenav" style="margin-top:16px;"><div class="tablenav-pages">' . $links . '</div></div>';
	}

	private function sync_all_members() {
		$this->prune_orphaned_members();

		$user_query = new WP_User_Query([
			'number' => -1,
			'fields' => 'ID',
			'orderby' => 'ID',
			'order' => 'ASC',
		]);

		$count = 0;
		foreach ((array) $user_query->get_results() as $user_id) {
			if ($this->sync_member((int) $user_id)) {
				$count++;
			}
		}

		return $count;
	}

	private function get_profile_row($user_id) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare("SELECT * FROM " . self::profiles_table() . ' WHERE user_id = %d LIMIT 1', $user_id),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
	}

	private function get_mirror_rows($table, $user_id) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare("SELECT * FROM {$table} WHERE user_id = %d ORDER BY source_record_id DESC, id DESC", $user_id),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
	}

	private function flatten_assoc($value, $prefix = '') {
		$rows = [];

		if (!is_array($value)) {
			$rows[$prefix !== '' ? $prefix : 'value'] = $this->stringify_value($value);
			return $rows;
		}

		foreach ($value as $key => $item) {
			$child_key = $prefix === '' ? (string) $key : $prefix . '.' . $key;
			if (is_array($item)) {
				if ($this->is_assoc($item)) {
					$rows = array_merge($rows, $this->flatten_assoc($item, $child_key));
				} else {
					$rows[$child_key] = wp_json_encode($item);
				}
				continue;
			}

			$rows[$child_key] = $this->stringify_value($item);
		}

		return $rows;
	}

	private function stringify_value($value) {
		if (is_bool($value)) {
			return $value ? 'true' : 'false';
		}

		if ($value === null) {
			return '';
		}

		if (is_scalar($value)) {
			return (string) $value;
		}

		return wp_json_encode($value);
	}

	private function is_assoc(array $array) {
		return array_keys($array) !== range(0, count($array) - 1);
	}

	private function build_admin_url($args = []) {
		return add_query_arg(array_merge(['page' => self::PAGE_SLUG], $args), admin_url('admin.php'));
	}

	private static function profiles_table() {
		global $wpdb;
		return $wpdb->prefix . 'aac_member_db_profiles';
	}

	private static function history_table() {
		global $wpdb;
		return $wpdb->prefix . 'aac_member_db_membership_history';
	}

	private static function subscriptions_table() {
		global $wpdb;
		return $wpdb->prefix . 'aac_member_db_subscriptions';
	}

	private static function transactions_table() {
		global $wpdb;
		return $wpdb->prefix . 'aac_member_db_transactions';
	}

}
