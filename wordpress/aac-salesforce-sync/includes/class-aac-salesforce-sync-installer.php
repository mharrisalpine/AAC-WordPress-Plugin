<?php

if (!defined('ABSPATH')) {
	exit;
}

class AAC_Salesforce_Sync_Installer {
	const SCHEMA_OPTION = 'aac_salesforce_sync_schema_version';
	const SCHEMA_VERSION = '0.1.0';

	public static function activate() {
		self::install_schema();
		AAC_Salesforce_Sync_Worker::schedule();
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
		$charset_collate = $wpdb->get_charset_collate();

		dbDelta("
			CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				job_type varchar(64) NOT NULL,
				object_type varchar(64) NOT NULL,
				object_id bigint(20) unsigned NOT NULL DEFAULT 0,
				external_key varchar(191) NOT NULL DEFAULT '',
				payload longtext NULL,
				status varchar(32) NOT NULL DEFAULT 'pending',
				attempts int(10) unsigned NOT NULL DEFAULT 0,
				max_attempts int(10) unsigned NOT NULL DEFAULT 5,
				available_at datetime NOT NULL,
				locked_at datetime NULL,
				last_error text NULL,
				source varchar(32) NOT NULL DEFAULT 'wordpress',
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY status_available (status, available_at),
				KEY object_lookup (object_type, object_id),
				KEY external_key (external_key)
			) {$charset_collate};
		");

		update_option(self::SCHEMA_OPTION, self::SCHEMA_VERSION);
	}
}
