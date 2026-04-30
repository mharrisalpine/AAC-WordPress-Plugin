<?php

if (!defined('ABSPATH')) {
	exit;
}

class AAC_Member_Portal_Member_Database {
	const PAGE_SLUG = 'aac-member-portal-member-database';
	const SCHEMA_VERSION = '1.0.3';
	const SCHEMA_OPTION = 'aac_member_portal_member_db_schema_version';

	public function __construct() {
		add_action('admin_menu', [$this, 'register_admin_page']);
		add_action('init', [$this, 'maybe_install_schema']);
		add_action('profile_update', [$this, 'sync_member_by_user_id'], 30, 1);
		add_action('personal_options_update', [$this, 'sync_member_by_user_id'], 100, 1);
		add_action('edit_user_profile_update', [$this, 'sync_member_by_user_id'], 100, 1);
		add_action('aac_member_portal_member_registered', [$this, 'sync_member_by_user_id'], 30, 1);
		add_action('aac_member_portal_profile_updated', [$this, 'sync_member_by_user_id'], 30, 1);
		add_action('aac_member_portal_grant_application_submitted', [$this, 'sync_member_after_grant_submission'], 30, 1);
		add_action('pmpro_after_checkout', [$this, 'sync_member_after_checkout'], 40, 2);
		add_action('pmpro_after_change_membership_level', [$this, 'sync_member_after_level_change'], 40, 2);
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
		$podcast_listens = self::podcast_listens_table();

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
				renewal_date varchar(20) NOT NULL DEFAULT '',
				expiration_date varchar(20) NOT NULL DEFAULT '',
				raw_profile longtext NULL,
				mirrored_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY user_id (user_id),
				KEY membership_level (membership_level),
				KEY account_role (account_role)
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
				PRIMARY KEY  (id),
				UNIQUE KEY user_source (user_id, source_record_id),
				KEY source_status (source_status)
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
				PRIMARY KEY  (id),
				UNIQUE KEY user_source (user_id, source_record_id),
				KEY source_status (source_status)
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
				PRIMARY KEY  (id),
				UNIQUE KEY user_source (user_id, source_record_id),
				KEY source_status (source_status)
			) {$charset_collate};
		");

		dbDelta("
			CREATE TABLE {$podcast_listens} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				episode_id varchar(120) NOT NULL DEFAULT '',
				episode_title varchar(255) NOT NULL DEFAULT '',
				source_url varchar(255) NOT NULL DEFAULT '',
				source_page_url varchar(255) NOT NULL DEFAULT '',
				embed_url varchar(255) NOT NULL DEFAULT '',
				status varchar(20) NOT NULL DEFAULT 'started',
				completion_percent decimal(5,2) NOT NULL DEFAULT 0,
				duration_ms bigint(20) unsigned NOT NULL DEFAULT 0,
				last_position_ms bigint(20) unsigned NOT NULL DEFAULT 0,
				listener_ip varchar(100) NOT NULL DEFAULT '',
				started_at datetime NULL,
				completed_at datetime NULL,
				last_event_at datetime NOT NULL,
				completion_count bigint(20) unsigned NOT NULL DEFAULT 0,
				raw_payload longtext NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY user_episode (user_id, episode_id),
				KEY status (status),
				KEY completed_at (completed_at)
			) {$charset_collate};
		");

		update_option(self::SCHEMA_OPTION, self::SCHEMA_VERSION);
	}

	public static function record_podcast_listen($user_id, $payload = []) {
		global $wpdb;

		$user_id = absint($user_id);
		if ($user_id <= 0 || !$wpdb) {
			return new WP_Error('invalid_user', 'A valid user is required to record a podcast listen.', ['status' => 400]);
		}

		$episode_id = sanitize_text_field((string) ($payload['episode_id'] ?? ''));
		if ($episode_id === '') {
			return new WP_Error('invalid_episode', 'A Spotify episode ID is required.', ['status' => 400]);
		}

		$table = self::podcast_listens_table();
		$existing = $wpdb->get_row(
			$wpdb->prepare("SELECT * FROM {$table} WHERE user_id = %d AND episode_id = %s LIMIT 1", $user_id, $episode_id),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.

		$requested_status = strtolower(sanitize_text_field((string) ($payload['status'] ?? 'started')));
		$status = $requested_status === 'completed' ? 'completed' : 'started';
		$completion_percent = isset($payload['completion_percent']) ? (float) $payload['completion_percent'] : 0;
		$completion_percent = max(0, min(100, $completion_percent));
		$duration_ms = isset($payload['duration_ms']) ? max(0, (int) $payload['duration_ms']) : 0;
		$last_position_ms = isset($payload['last_position_ms']) ? max(0, (int) $payload['last_position_ms']) : 0;
		$existing_completion_percent = isset($existing['completion_percent']) ? (float) $existing['completion_percent'] : 0;
		$existing_duration_ms = isset($existing['duration_ms']) ? (int) $existing['duration_ms'] : 0;
		$existing_last_position_ms = isset($existing['last_position_ms']) ? (int) $existing['last_position_ms'] : 0;
		$resolved_duration_ms = max($duration_ms, $existing_duration_ms);
		$resolved_last_position_ms = max($last_position_ms, $existing_last_position_ms);
		$resolved_completion_percent = max($completion_percent, $existing_completion_percent);
		if ($status === 'completed') {
			$resolved_completion_percent = max($resolved_completion_percent, 100);
			if ($resolved_duration_ms > 0) {
				$resolved_last_position_ms = max($resolved_last_position_ms, $resolved_duration_ms);
			}
		}
		$now = current_time('mysql');

		$row = [
			'user_id' => $user_id,
			'episode_id' => $episode_id,
			'episode_title' => sanitize_text_field((string) ($payload['episode_title'] ?? ($existing['episode_title'] ?? ''))),
			'source_url' => esc_url_raw((string) ($payload['source_url'] ?? ($existing['source_url'] ?? ''))),
			'source_page_url' => esc_url_raw((string) ($payload['source_page_url'] ?? ($existing['source_page_url'] ?? ''))),
			'embed_url' => esc_url_raw((string) ($payload['embed_url'] ?? ($existing['embed_url'] ?? ''))),
			'status' => ($status === 'completed' || (($existing['status'] ?? '') === 'completed')) ? 'completed' : 'started',
			'completion_percent' => $resolved_completion_percent,
			'duration_ms' => $resolved_duration_ms,
			'last_position_ms' => $resolved_last_position_ms,
			'listener_ip' => sanitize_text_field((string) ($payload['listener_ip'] ?? ($existing['listener_ip'] ?? ''))),
			'started_at' => !empty($existing['started_at']) ? $existing['started_at'] : $now,
			'completed_at' => $status === 'completed'
				? $now
				: (!empty($existing['completed_at']) ? $existing['completed_at'] : ''),
			'last_event_at' => $now,
			'completion_count' => ($status === 'completed')
				? ((int) ($existing['completion_count'] ?? 0) + 1)
				: (int) ($existing['completion_count'] ?? 0),
			'raw_payload' => wp_json_encode($payload),
		];

		if ($existing) {
			$wpdb->update(
				$table,
				$row,
				['id' => (int) $existing['id']],
				['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s'],
				['%d']
			);
			$row['id'] = (int) $existing['id'];
		} else {
			$wpdb->insert(
				$table,
				$row,
				['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s']
			);
			$row['id'] = (int) $wpdb->insert_id;
		}

		return $row;
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
	}

	public function sync_member_by_user_id($user_id) {
		$this->sync_member((int) $user_id);
	}

	public function sync_member_after_checkout($user_id, $morder = null) {
		$this->sync_member((int) $user_id);
	}

	public function sync_member_after_level_change($level_id, $user_id = 0) {
		$this->sync_member((int) $user_id);
	}

	public function sync_member_after_grant_submission($user_id) {
		$this->sync_member((int) $user_id);
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
		$account_role = sanitize_text_field((string) get_user_meta($user_id, 'aac_family_account_role', true));
		$parent_user_id = absint(get_user_meta($user_id, 'aac_linked_parent_user_id', true));

		if ($parent_user_id <= 0 && !empty($linked_parent['user_id'])) {
			$parent_user_id = (int) $linked_parent['user_id'];
		}

		$mirrored_at = current_time('mysql');
		$wpdb->replace(
			self::profiles_table(),
			[
				'user_id' => $user_id,
				'parent_user_id' => $parent_user_id,
				'account_role' => $account_role,
				'email' => sanitize_email($account_info['email'] ?? $user->user_email),
				'display_name' => sanitize_text_field($account_info['name'] ?? $user->display_name),
				'member_id' => sanitize_text_field($profile_info['member_id'] ?? ''),
				'membership_level' => sanitize_text_field($profile_info['tier'] ?? ''),
				'membership_status' => sanitize_text_field($profile_info['status'] ?? ''),
				'renewal_date' => sanitize_text_field($profile_info['renewal_date'] ?? ''),
				'expiration_date' => sanitize_text_field($profile_info['expiration_date'] ?? ''),
				'raw_profile' => wp_json_encode($profile),
				'mirrored_at' => $mirrored_at,
			],
			['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
		);

		$this->mirror_pmpro_rows($user_id, 'pmpro_memberships_users', self::history_table(), 'status', ['startdate', 'modified', 'date']);
		$this->mirror_pmpro_rows($user_id, 'pmpro_subscriptions', self::subscriptions_table(), 'status', ['next_payment_date', 'cycle_enddate', 'startdate', 'modified']);
		$this->mirror_pmpro_rows($user_id, 'pmpro_membership_orders', self::transactions_table(), 'status', ['timestamp']);

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

	private function mirror_pmpro_rows($user_id, $wpdb_property, $mirror_table, $status_column = 'status', $date_candidates = []) {
		global $wpdb;

		if (!$wpdb || empty($wpdb->{$wpdb_property})) {
			$wpdb->delete($mirror_table, ['user_id' => $user_id], ['%d']);
			return;
		}

		$source_table = $wpdb->{$wpdb_property};
		// We wipe and rebuild the mirrored rows for a user on each sync. It is not
		// the fanciest move in the world, but it is wonderfully hard to lie to and
		// keeps stale PMPro rows from haunting the reports.
		$rows = $wpdb->get_results(
			$wpdb->prepare("SELECT * FROM {$source_table} WHERE user_id = %d ORDER BY id DESC", $user_id),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.

		$wpdb->delete($mirror_table, ['user_id' => $user_id], ['%d']);

		if (!is_array($rows) || !$rows) {
			return;
		}

		$mirrored_at = current_time('mysql');
		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}

			$source_record_id = absint($row['id'] ?? 0);
			$source_status = sanitize_text_field((string) ($row[$status_column] ?? ''));
			$source_date = '';
			foreach ($date_candidates as $candidate) {
				if (!empty($row[$candidate])) {
					$source_date = sanitize_text_field((string) $row[$candidate]);
					break;
				}
			}

			$wpdb->insert(
				$mirror_table,
				[
					'user_id' => $user_id,
					'source_record_id' => $source_record_id,
					'source_status' => $source_status,
					'source_date' => $source_date,
					'raw_record' => wp_json_encode($row),
					'mirrored_at' => $mirrored_at,
				],
				['%d', '%d', '%s', '%s', '%s', '%s']
			);
		}
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
		$podcast_listen_rows = $member_id > 0 ? $this->get_podcast_listen_rows($member_id) : [];
		$grant_application_rows = $member_id > 0 ? $this->get_grant_application_rows($member_id, $profile_row) : [];
		?>
		<div class="wrap">
			<h1>Member Database</h1>
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
					Sync All Members
				</a>
			</form>

			<?php if ($sync_all_count !== null) : ?>
				<div class="notice notice-success inline" style="margin:0 0 16px;">
					<p><?php echo esc_html(sprintf('Synced %d members into the AAC Portal database mirror.', $sync_all_count)); ?></p>
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
					<p style="margin-top:16px;">No mirrored members found. Use “Sync All Members” to build the AAC Portal database mirror.</p>
				<?php else : ?>
					<div style="overflow:auto;margin-top:20px;">
						<table class="widefat striped">
							<thead>
								<tr>
									<th style="min-width:140px;">Open Member</th>
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
									<tr>
										<td>
											<a class="button button-secondary" href="<?php echo esc_url($this->build_admin_url(['member_id' => $row['user_id'], 'tab' => 'profile', 's' => $search, 'paged' => $paged])); ?>">
												Open Member
											</a>
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
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>

					<?php echo $this->render_member_list_pagination($member_list['total'], $member_list['page'], $member_list['per_page'], $search, $member_id, $tab); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endif; ?>
			</section>

			<?php if ($member_id > 0 && $profile_row) : ?>
				<?php
				$tabs = [
					'profile' => 'Profile',
					'preferences' => 'Preferences',
					'grant-applications' => 'Grant Applications',
					'membership-history' => 'Membership History',
					'subscriptions' => 'Subscriptions',
					'transactions' => 'Transactions',
					'podcast-listens' => 'Podcast Listens',
				];
				?>
				<section style="background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:20px;">
					<div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap;">
						<div>
							<h2 style="margin:0;"><?php echo esc_html($profile_row['display_name'] ?: $profile_row['email']); ?></h2>
							<p style="margin:8px 0 0;color:#50575e;">
								<?php echo esc_html($profile_row['email']); ?> · User ID <?php echo esc_html($member_id); ?>
							</p>
						</div>
						<div>
							<a class="button button-secondary" href="<?php echo esc_url($this->build_admin_url(['member_id' => $member_id, 'tab' => $tab, 's' => $search])); ?>">
								Sync This Member
							</a>
						</div>
					</div>

					<?php if ($did_sync) : ?>
						<div class="notice notice-success inline" style="margin:16px 0 0;">
							<p>Member mirror refreshed.</p>
						</div>
					<?php endif; ?>

					<nav class="nav-tab-wrapper" style="margin-top:20px;">
						<?php foreach ($tabs as $tab_key => $tab_label) : ?>
							<a class="nav-tab <?php echo $tab === $tab_key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url($this->build_admin_url(['member_id' => $member_id, 'tab' => $tab_key, 's' => $search])); ?>">
								<?php echo esc_html($tab_label); ?>
							</a>
						<?php endforeach; ?>
					</nav>

					<div style="margin-top:20px;">
						<?php
						if ($tab === 'preferences') {
							$this->render_preferences_tab($profile_row);
						} elseif ($tab === 'grant-applications') {
							$this->render_grant_applications_tab($grant_application_rows);
						} elseif ($tab === 'membership-history') {
							$this->render_json_tab_table($history_rows, 'No mirrored membership history found.', 'membership');
						} elseif ($tab === 'subscriptions') {
							$this->render_json_tab_table($subscription_rows, 'No mirrored subscriptions found.', 'subscription');
						} elseif ($tab === 'transactions') {
							$this->render_json_tab_table($transaction_rows, 'No mirrored transactions found.', 'transaction');
						} elseif ($tab === 'podcast-listens') {
							$this->render_podcast_listens_tab($podcast_listen_rows);
						} else {
							$this->render_profile_tab($profile_row);
						}
						?>
					</div>
				</section>
			<?php elseif ($member_id > 0) : ?>
				<div class="notice notice-warning"><p>This member could not be mirrored yet.</p></div>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_profile_tab($profile_row) {
		$profile = $this->decode_profile_row($profile_row);
		$account_info = $profile['account_info'];
		$profile_info = $profile['profile_info'];
		?>
		<table class="widefat striped">
			<tbody>
				<tr><th style="width:240px;">First Name</th><td><?php echo esc_html($account_info['first_name'] ?? ''); ?></td></tr>
				<tr><th>Last Name</th><td><?php echo esc_html($account_info['last_name'] ?? ''); ?></td></tr>
				<tr><th>Display Name</th><td><?php echo esc_html($profile_row['display_name']); ?></td></tr>
				<tr><th>Email</th><td><?php echo esc_html($profile_row['email']); ?></td></tr>
				<tr><th>Phone</th><td><?php echo esc_html($account_info['phone'] ?? ''); ?></td></tr>
				<tr><th>Birthdate</th><td><?php echo esc_html($account_info['birthdate'] ?? ''); ?></td></tr>
				<tr><th>Street</th><td><?php echo esc_html($account_info['street'] ?? ''); ?></td></tr>
				<tr><th>Address 2</th><td><?php echo esc_html($account_info['address2'] ?? ''); ?></td></tr>
				<tr><th>City</th><td><?php echo esc_html($account_info['city'] ?? ''); ?></td></tr>
				<tr><th>State</th><td><?php echo esc_html($account_info['state'] ?? ''); ?></td></tr>
				<tr><th>ZIP</th><td><?php echo esc_html($account_info['zip'] ?? ''); ?></td></tr>
				<tr><th>Country</th><td><?php echo esc_html($account_info['country'] ?? ''); ?></td></tr>
			</tbody>
		</table>

		<h3 style="margin-top:24px;">Current Membership</h3>
		<table class="widefat striped">
			<tbody>
				<tr><th style="width:240px;">Membership Level</th><td><?php echo esc_html($profile_row['membership_level']); ?></td></tr>
				<tr><th>Membership Status</th><td><?php echo esc_html($profile_row['membership_status']); ?></td></tr>
				<tr><th>Member ID</th><td><?php echo esc_html($profile_row['member_id']); ?></td></tr>
				<tr><th>Member Since</th><td><?php echo esc_html($profile_info['joined_date'] ?? ''); ?></td></tr>
				<tr><th>Renewal Date</th><td><?php echo esc_html($profile_row['renewal_date']); ?></td></tr>
				<tr><th>Expiration Date</th><td><?php echo esc_html($profile_row['expiration_date']); ?></td></tr>
				<tr><th>Account Role</th><td><?php echo esc_html($profile_row['account_role'] ?: 'Standard'); ?></td></tr>
				<tr><th>Mirrored At</th><td><?php echo esc_html($profile_row['mirrored_at']); ?></td></tr>
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
			'Email Opt Out' => !empty($account_info['email_opt_out']) ? 'true' : 'false',
			'Do Not Call' => !empty($account_info['do_not_call']) ? 'true' : 'false',
			'Do Not Contact' => !empty($account_info['do_not_contact']) ? 'true' : 'false',
			'AAJ Preference' => $account_info['aaj_pref'] ?? '',
			'ANAC Preference' => $account_info['anac_pref'] ?? '',
			'American Climbing Journal Preference' => $account_info['acj_pref'] ?? '',
			'Guidebook Preference' => $account_info['guidebook_pref'] ?? '',
			'Membership Discount Type' => $account_info['membership_discount_type'] ?? '',
			'Auto Renew' => !empty($account_info['auto_renew']) ? 'true' : 'false',
			'Magazine Subscriptions' => !empty($account_info['magazine_subscriptions']) ? implode(', ', (array) $account_info['magazine_subscriptions']) : '',
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

	private function render_grant_applications_tab($rows) {
		if (!$rows) {
			echo '<p>No grant applications have been recorded for this member yet.</p>';
			return;
		}
		?>
		<div style="display:grid;gap:20px;">
			<?php foreach ($rows as $application) : ?>
				<?php
				$fields = is_array($application['fields'] ?? null) ? $application['fields'] : [];
				?>
				<section style="border:1px solid #dcdcde;border-radius:14px;padding:18px;background:#fff;">
					<div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap;">
						<div>
							<h3 style="margin:0;"><?php echo esc_html($application['grant_name'] ?: 'Grant Application'); ?></h3>
							<p style="margin:8px 0 0;color:#50575e;">
								Submitted <?php echo esc_html($application['application_date'] ?? ''); ?>
								<?php if (!empty($application['review_application_id'])) : ?>
									· Review ID <?php echo esc_html((string) $application['review_application_id']); ?>
								<?php endif; ?>
							</p>
						</div>
						<div style="display:flex;gap:8px;flex-wrap:wrap;">
							<span class="button button-secondary" style="pointer-events:none;"><?php echo esc_html($application['status'] ?? 'Submitted'); ?></span>
							<?php if (!empty($application['category'])) : ?>
								<span class="button button-secondary" style="pointer-events:none;"><?php echo esc_html($application['category']); ?></span>
							<?php endif; ?>
						</div>
					</div>

					<table class="widefat striped" style="margin-top:18px;">
						<tbody>
							<tr><th style="width:240px;">AAC Member ID</th><td><?php echo esc_html($application['aac_member_id'] ?? ''); ?></td></tr>
							<tr><th style="width:240px;">Project Title</th><td><?php echo esc_html($application['project_title'] ?? ''); ?></td></tr>
							<tr><th>Requested Amount</th><td><?php echo esc_html($application['requested_amount'] ?? ''); ?></td></tr>
							<tr><th>Objective / Location</th><td><?php echo esc_html($application['objective_location'] ?? ''); ?></td></tr>
							<tr><th>Discipline</th><td><?php echo esc_html($application['discipline'] ?? ''); ?></td></tr>
							<tr><th>Team / Partners</th><td><?php echo esc_html($application['team_name'] ?? ''); ?></td></tr>
							<tr><th>Summary</th><td style="white-space:pre-wrap;"><?php echo esc_html($application['summary'] ?? ''); ?></td></tr>
							<tr><th>Grant Slug</th><td><?php echo esc_html($application['grant_slug'] ?? ''); ?></td></tr>
							<tr><th>Last Note</th><td style="white-space:pre-wrap;"><?php echo esc_html($application['last_note'] ?? ''); ?></td></tr>
							<tr><th>Reviewed At</th><td><?php echo esc_html($application['reviewed_at'] ?? ''); ?></td></tr>
						</tbody>
					</table>

					<h4 style="margin:20px 0 10px;">All Submitted Fields</h4>
					<?php if (!$fields) : ?>
						<p style="margin:0;color:#50575e;">No normalized field rows were stored for this application.</p>
					<?php else : ?>
						<div style="overflow:auto;">
							<table class="widefat striped">
								<thead>
									<tr>
										<th style="min-width:220px;">Label</th>
										<th style="min-width:180px;">Field Key</th>
										<th style="min-width:120px;">Type</th>
										<th>Value</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($fields as $field) : ?>
										<tr>
											<td><?php echo esc_html($field['label'] ?? ''); ?></td>
											<td><code><?php echo esc_html($field['field_id'] ?? $field['field_key'] ?? ''); ?></code></td>
											<td><?php echo esc_html($field['type'] ?? ''); ?></td>
											<td style="white-space:pre-wrap;"><?php echo esc_html($field['value'] ?? ''); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</section>
			<?php endforeach; ?>
		</div>
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

	private function render_podcast_listens_tab($rows) {
		if (!$rows) {
			echo '<p>No podcast listens have been recorded for this member yet.</p>';
			return;
		}
		?>
		<div style="overflow:auto;">
			<table class="widefat striped">
				<thead>
					<tr>
						<th style="min-width:220px;">Episode</th>
						<th>Status</th>
						<th>Completion %</th>
						<th>Last Position</th>
						<th>Duration</th>
						<th>Listener IP</th>
						<th>Started At</th>
						<th>Completed At</th>
						<th>Last Event</th>
						<th>Completions</th>
						<th>Source</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($rows as $row) : ?>
						<tr>
							<td style="white-space:normal;word-break:break-word;"><?php echo esc_html($row['episode_title'] ?: $row['episode_id']); ?></td>
							<td><?php echo esc_html($row['status']); ?></td>
							<td><?php echo esc_html(number_format((float) $row['completion_percent'], 2)); ?></td>
							<td><?php echo esc_html($this->format_milliseconds_for_display((int) $row['last_position_ms'])); ?></td>
							<td><?php echo esc_html($this->format_milliseconds_for_display((int) $row['duration_ms'])); ?></td>
							<td><?php echo esc_html((string) ($row['listener_ip'] ?? '')); ?></td>
							<td><?php echo esc_html($row['started_at']); ?></td>
							<td><?php echo esc_html($row['completed_at']); ?></td>
							<td><?php echo esc_html($row['last_event_at']); ?></td>
							<td><?php echo esc_html((string) $row['completion_count']); ?></td>
							<td>
								<?php if (!empty($row['source_url'])) : ?>
									<a href="<?php echo esc_url($row['source_url']); ?>" target="_blank" rel="noreferrer">Spotify Episode</a>
								<?php endif; ?>
							</td>
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

	private function get_podcast_listen_rows($user_id) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare("SELECT * FROM " . self::podcast_listens_table() . ' WHERE user_id = %d ORDER BY last_event_at DESC, id DESC', $user_id),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
	}

	private function get_grant_application_rows($user_id, $profile_row = null) {
		$repository = $this->get_grants_review_repository();
		$email = '';

		if (is_array($profile_row)) {
			$email = sanitize_email((string) ($profile_row['email'] ?? ''));
			if ($email === '') {
				$profile = $this->decode_profile_row($profile_row);
				$email = sanitize_email((string) ($profile['account_info']['email'] ?? ''));
			}
		}

		if ($repository && method_exists($repository, 'get_member_applications')) {
			$applications = $repository->get_member_applications((int) $user_id, $email);
			if (is_array($applications)) {
				return $applications;
			}
		}

		$user_meta_applications = get_user_meta((int) $user_id, 'aac_grant_applications', true);
		return is_array($user_meta_applications) ? $user_meta_applications : [];
	}

	private function get_grants_review_repository() {
		static $repository = null;
		static $initialized = false;

		if ($initialized) {
			return $repository;
		}

		$initialized = true;
		$repository = null;

		if (!class_exists('AAC_Grants_Review_Settings') || !class_exists('AAC_Grants_Review_Repository')) {
			return null;
		}

		$settings = new AAC_Grants_Review_Settings();
		$repository = new AAC_Grants_Review_Repository($settings);

		return $repository;
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

	private function format_milliseconds_for_display($milliseconds) {
		$total_seconds = max(0, (int) floor(((int) $milliseconds) / 1000));
		$hours = (int) floor($total_seconds / 3600);
		$minutes = (int) floor(($total_seconds % 3600) / 60);
		$seconds = $total_seconds % 60;

		if ($hours > 0) {
			return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
		}

		return sprintf('%d:%02d', $minutes, $seconds);
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

	private static function podcast_listens_table() {
		global $wpdb;
		return $wpdb->prefix . 'aac_member_db_podcast_listens';
	}
}
