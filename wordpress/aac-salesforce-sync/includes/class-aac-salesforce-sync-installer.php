<?php

if (!defined('ABSPATH')) {
	exit;
}

class AAC_Salesforce_Sync_Installer {
	const SCHEMA_OPTION = 'aac_salesforce_sync_schema_version';
	const SCHEMA_VERSION = '0.6.0';

	public static function activate() {
		self::install_schema();
		AAC_Salesforce_Sync_Worker::schedule();
		AAC_Salesforce_Sync_Error_Log::reschedule_daily_report();
		AAC_Salesforce_Sync_Pull::reschedule();
	}

	public static function deactivate() {
		AAC_Salesforce_Sync_Worker::deactivate();
		AAC_Salesforce_Sync_Error_Log::deactivate();
		AAC_Salesforce_Sync_Pull::deactivate();
		AAC_Salesforce_Sync_Health::deactivate();
	}

	public static function maybe_install_schema() {
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

		$table = AAC_Salesforce_Sync_Queue::table_name();
		$error_table = AAC_Salesforce_Sync_Error_Log::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		dbDelta("
			CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				job_type varchar(64) NOT NULL,
				object_type varchar(64) NOT NULL,
				object_id bigint(20) unsigned NOT NULL DEFAULT 0,
				external_key varchar(191) NOT NULL DEFAULT '',
				event_id varchar(64) NOT NULL DEFAULT '',
				schema_version varchar(16) NOT NULL DEFAULT '2.0',
				operation varchar(32) NOT NULL DEFAULT 'upsert',
				source_modified_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
				payload longtext NULL,
				status varchar(32) NOT NULL DEFAULT 'pending',
				attempts int(10) unsigned NOT NULL DEFAULT 0,
				max_attempts int(10) unsigned NOT NULL DEFAULT 5,
				available_at datetime NOT NULL,
				locked_at datetime NULL,
				lock_token varchar(64) NOT NULL DEFAULT '',
				locked_by varchar(191) NOT NULL DEFAULT '',
				last_error text NULL,
				source varchar(32) NOT NULL DEFAULT 'wordpress',
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY status_available (status, available_at),
				KEY object_lookup (object_type, object_id),
				KEY external_key (external_key),
				KEY event_id (event_id)
			) {$charset_collate};
		");

		dbDelta("
			CREATE TABLE {$error_table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				job_id bigint(20) unsigned NOT NULL DEFAULT 0,
				job_type varchar(64) NOT NULL DEFAULT '',
				object_type varchar(64) NOT NULL DEFAULT '',
				object_id bigint(20) unsigned NOT NULL DEFAULT 0,
				external_key varchar(191) NOT NULL DEFAULT '',
				source varchar(32) NOT NULL DEFAULT '',
				salesforce_object varchar(128) NOT NULL DEFAULT '',
				salesforce_object_key varchar(128) NOT NULL DEFAULT '',
				salesforce_field varchar(191) NOT NULL DEFAULT '',
				field_value longtext NULL,
				error_message longtext NULL,
				attempts int(10) unsigned NOT NULL DEFAULT 0,
				failed_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY failed_at (failed_at),
				KEY job_lookup (job_id),
				KEY external_key (external_key),
				KEY salesforce_field (salesforce_field)
			) {$charset_collate};
		");

		self::remove_legacy_grants_data();

		update_option(self::SCHEMA_OPTION, self::SCHEMA_VERSION);
	}

	private static function remove_legacy_grants_data() {
		global $wpdb;

		if (!$wpdb) {
			return;
		}

		$settings = get_option(AAC_Salesforce_Sync_Settings::OPTION_KEY, []);
		if (is_array($settings)) {
			unset($settings['general']['sync_grant']);
			unset($settings['salesforce']['grant_object'], $settings['salesforce']['grant_external_id_field']);
			unset($settings['field_mappings']['grant']);
			if (isset($settings['salesforce']['custom_objects']) && is_array($settings['salesforce']['custom_objects'])) {
				$settings['salesforce']['custom_objects'] = array_values(array_filter(
					$settings['salesforce']['custom_objects'],
					function ($object) {
						return !is_array($object) || sanitize_key((string) ($object['group'] ?? '')) !== 'grant';
					}
				));
			}
			update_option(AAC_Salesforce_Sync_Settings::OPTION_KEY, $settings);
		}

		$catalog = get_option(AAC_Salesforce_Sync_Settings::FIELD_CATALOG_OPTION, []);
		if (is_array($catalog) && isset($catalog['objects']) && is_array($catalog['objects'])) {
			foreach ($catalog['objects'] as $object_key => $object) {
				if ($object_key === 'grant' || (is_array($object) && sanitize_key((string) ($object['group'] ?? '')) === 'grant')) {
					unset($catalog['objects'][$object_key]);
				}
			}
			update_option(AAC_Salesforce_Sync_Settings::FIELD_CATALOG_OPTION, $catalog);
		}

		$portal_settings = get_option('aac_member_portal_settings', []);
		if (is_array($portal_settings) && isset($portal_settings['content']) && is_array($portal_settings['content'])) {
			unset($portal_settings['content']['grant_form_fields'], $portal_settings['content']['grant_opportunities']);
			update_option('aac_member_portal_settings', $portal_settings);
		}

		$review_page_id = absint(get_option('aac_grants_review_page_id', 0));
		if ($review_page_id > 0 && get_post($review_page_id)) {
			wp_delete_post($review_page_id, true);
		}

		foreach (['administrator', 'editor'] as $role_name) {
			$role = get_role($role_name);
			if ($role) {
				$role->remove_cap('aac_review_grants');
			}
		}
		remove_role('aac_grant_reviewer');

		$queue_table = $wpdb->prefix . 'aac_salesforce_sync_queue';
		$error_table = $wpdb->prefix . 'aac_salesforce_sync_error_log';
		$wpdb->query("DELETE FROM {$queue_table} WHERE object_type = 'grant' OR job_type = 'upsert_grant'");
		$wpdb->query("DELETE FROM {$error_table} WHERE object_type = 'grant' OR job_type = 'upsert_grant'");
		$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}aac_grant_review_history");
		$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}aac_grant_review_applications");

		delete_option('aac_grants_review_page_id');
		delete_option('aac_grants_review_schema_version');
	}
}
