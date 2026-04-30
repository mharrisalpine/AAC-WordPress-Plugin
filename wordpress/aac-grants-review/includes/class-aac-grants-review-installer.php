<?php

if (!defined('ABSPATH')) {
	exit;
}

class AAC_Grants_Review_Installer {
	const SCHEMA_OPTION = 'aac_grants_review_schema_version';
	const SCHEMA_VERSION = '0.3.0';
	const REVIEW_CAP = 'aac_review_grants';
	const REVIEW_PAGE_OPTION = 'aac_grants_review_page_id';
	const REVIEWER_ROLE = 'aac_grant_reviewer';

	public static function activate() {
		self::install_schema();
		self::grant_capabilities();
		self::ensure_review_page();
	}

	public static function maybe_install_schema() {
		if (get_option(self::SCHEMA_OPTION) === self::SCHEMA_VERSION) {
			return;
		}

		self::install_schema();
		self::grant_capabilities();
		self::ensure_review_page();
	}

	private static function grant_capabilities() {
		$roles = ['administrator', 'editor'];

		foreach ($roles as $role_name) {
			$role = get_role($role_name);
			if ($role && !$role->has_cap(self::REVIEW_CAP)) {
				$role->add_cap(self::REVIEW_CAP);
			}
		}

		$reviewer_role = get_role(self::REVIEWER_ROLE);
		if (!$reviewer_role) {
			add_role(
				self::REVIEWER_ROLE,
				'Grant Reviewer',
				[
					'read' => true,
					self::REVIEW_CAP => true,
				]
			);
			return;
		}

		if (!$reviewer_role->has_cap(self::REVIEW_CAP)) {
			$reviewer_role->add_cap(self::REVIEW_CAP);
		}
	}

	private static function install_schema() {
		global $wpdb;

		if (!$wpdb) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$applications_table = AAC_Grants_Review_Repository::applications_table_name();
		$history_table = AAC_Grants_Review_Repository::history_table_name();

		dbDelta("
			CREATE TABLE {$applications_table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				source varchar(32) NOT NULL DEFAULT 'portal',
				source_form_id bigint(20) unsigned NOT NULL DEFAULT 0,
				source_entry_id bigint(20) unsigned NOT NULL DEFAULT 0,
				applicant_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				aac_member_id varchar(191) NOT NULL DEFAULT '',
				applicant_name varchar(191) NOT NULL DEFAULT '',
				applicant_email varchar(191) NOT NULL DEFAULT '',
				grant_name varchar(191) NOT NULL DEFAULT '',
				project_title varchar(191) NOT NULL DEFAULT '',
				requested_amount decimal(12,2) NOT NULL DEFAULT 0.00,
				workflow_status varchar(64) NOT NULL DEFAULT 'submitted',
				assigned_reviewer_id bigint(20) unsigned NOT NULL DEFAULT 0,
				submitted_at datetime NOT NULL,
				reviewed_at datetime NULL,
				last_note text NULL,
				normalized_fields longtext NULL,
				raw_payload longtext NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY source_entry (source, source_entry_id),
				KEY workflow_status (workflow_status),
				KEY source_form_id (source_form_id),
				KEY aac_member_id (aac_member_id),
				KEY applicant_email (applicant_email),
				KEY assigned_reviewer_id (assigned_reviewer_id)
			) {$charset_collate};
		");

		dbDelta("
			CREATE TABLE {$history_table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				application_id bigint(20) unsigned NOT NULL,
				action varchar(64) NOT NULL DEFAULT '',
				from_status varchar(64) NOT NULL DEFAULT '',
				to_status varchar(64) NOT NULL DEFAULT '',
				note text NULL,
				actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				PRIMARY KEY (id),
				KEY application_id (application_id),
				KEY created_at (created_at)
			) {$charset_collate};
		");

		update_option(self::SCHEMA_OPTION, self::SCHEMA_VERSION);
	}

	private static function ensure_review_page() {
		$page_id = absint(get_option(self::REVIEW_PAGE_OPTION, 0));
		if ($page_id > 0 && get_post($page_id)) {
			return;
		}

		$existing = get_page_by_path('grant-review');
		if ($existing instanceof WP_Post) {
			update_option(self::REVIEW_PAGE_OPTION, absint($existing->ID), false);
			return;
		}

		$page_id = wp_insert_post([
			'post_title' => 'Grant Review',
			'post_name' => 'grant-review',
			'post_status' => 'publish',
			'post_type' => 'page',
			'post_content' => '[aac_grants_review_portal]',
		], true);

		if (!is_wp_error($page_id) && $page_id) {
			update_option(self::REVIEW_PAGE_OPTION, absint($page_id), false);
		}
	}
}
