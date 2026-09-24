<?php

if (!defined('ABSPATH')) {
	exit;
}

class AAC_Salesforce_Sync_Settings {
	const OPTION_KEY = 'aac_salesforce_sync_settings';
	const PAGE_SLUG = 'aac-salesforce-sync';
	const FIELD_CATALOG_OPTION = 'aac_salesforce_sync_field_catalog';
	const AUTH_STATE_OPTION = 'aac_salesforce_sync_auth_state';
	const OAUTH_DEBUG_OPTION = 'aac_salesforce_sync_oauth_debug';

	public static function get_defaults() {
		return [
			'general' => [
				'enabled' => 0,
				'batch_size' => 10,
				'max_attempts' => 5,
				'sync_contact' => 1,
				'sync_membership' => 1,
				'sync_transaction' => 1,
			],
			'salesforce' => [
				'auth_url' => 'https://login.salesforce.com/services/oauth2/authorize',
				'token_url' => 'https://login.salesforce.com/services/oauth2/token',
				'instance_url' => '',
				'api_version' => '61.0',
				'oauth_scope' => 'api refresh_token offline_access',
				'client_id' => '',
				'client_secret' => '',
				'contact_object' => 'Contact',
				'membership_object' => 'Membership_Term__c',
				'transaction_object' => 'Payment_Transaction__c',
				'contact_external_id_field' => 'WordPress_User_ID__c',
				'membership_external_id_field' => 'AAC_External_Key__c',
				'transaction_external_id_field' => 'PMPro_Order_Line_Key__c',
				'custom_objects' => [],
			],
			'inbound' => [
				'secret' => '',
			],
			'error_report' => [
				'enabled' => 0,
				'recipient_email' => sanitize_email(get_option('admin_email')),
				'daily_hour' => 6,
				'retention_days' => 90,
			],
			'operations' => [
				'alerts_enabled' => 1,
				'alert_email' => sanitize_email(get_option('admin_email')),
				'pending_sla_minutes' => 30,
				'dead_letter_threshold' => 1,
				'completed_job_retention_days' => 30,
			],
			'pull' => [
				'enabled' => 0,
				'sync_contact' => 1,
				'sync_membership' => 1,
				'interval' => 'hourly',
				'batch_size' => 100,
				'lookback_hours' => 24,
			],
			'field_mappings' => self::get_default_field_mappings(),
		];
	}

	public static function get_field_definitions() {
		return self::get_member_database_field_definitions();
	}

	public static function get_default_field_mappings() {
		return [
			'contact' => [
				'member_db_profile_user_id' => self::build_mapping_entry('contact', 'WordPress_User_ID__c'),
				'member_db_profile_member_id' => self::build_mapping_entry('contact', 'AAC_Member_ID__c'),
				'member_db_profile_account_info_first_name' => self::build_mapping_entry('contact', 'FirstName'),
				'member_db_profile_account_info_last_name' => self::build_mapping_entry('contact', 'LastName'),
				'member_db_profile_account_info_email' => self::build_mapping_entry('contact', 'Email'),
				'member_db_profile_account_info_phone' => self::build_mapping_entry('contact', 'Phone'),
				'member_db_profile_account_info_street' => self::build_mapping_entry('contact', 'MailingStreet'),
				'member_db_profile_account_info_city' => self::build_mapping_entry('contact', 'MailingCity'),
				'member_db_profile_account_info_state' => self::build_mapping_entry('contact', 'MailingState'),
				'member_db_profile_account_info_zip' => self::build_mapping_entry('contact', 'MailingPostalCode'),
				'member_db_profile_account_info_country' => self::build_mapping_entry('contact', 'MailingCountry'),
				'member_db_profile_member_since' => self::build_mapping_entry('contact', 'Member_Since__c'),
				'member_db_profile_current_membership_expiration_date' => self::build_mapping_entry('contact', 'Current_Membership_Expiration_Date__c'),
				'member_db_profile_account_info_auto_renew' => self::build_mapping_entry('contact', 'Auto_Renew__c'),
				'member_db_profile_account_info_email_opt_out' => self::build_mapping_entry('contact', 'HasOptedOutOfEmail'),
				'member_db_profile_account_info_do_not_call' => self::build_mapping_entry('contact', 'DoNotCall'),
				'member_db_profile_account_info_do_not_contact' => self::build_mapping_entry('contact', 'npsp__Do_Not_Contact__c'),
				'member_db_profile_primary_account_holder' => self::build_mapping_entry('contact', 'Primary_Account_Holder__c'),
				'member_db_profile_family_plan' => self::build_mapping_entry('contact', 'Family_Plan__c'),
				'member_db_profile_account_role' => self::build_mapping_entry('contact', 'AAC_Family_Account_Role__c'),
			],
			'membership' => [
				'member_db_profile_user_id' => self::build_mapping_entry('membership', 'WordPress_User_ID__c'),
				'member_db_profile_member_id' => self::build_mapping_entry('membership', 'AAC_Member_ID__c'),
				'member_db_profile_membership_level' => self::build_mapping_entry('membership', 'Membership_Level__c'),
				'member_db_profile_membership_status' => self::build_mapping_entry('membership', 'Status__c'),
				'member_db_profile_family_membership_mode' => self::build_mapping_entry('membership', 'Family_Membership__c'),
				'member_db_membership_record_startdate' => self::build_mapping_entry('membership', 'Start_Date__c'),
				'member_db_profile_renewal_date' => self::build_mapping_entry('membership', 'Renewal_Date__c'),
				'member_db_profile_expiration_date' => self::build_mapping_entry('membership', 'End_Date__c'),
				'member_db_profile_account_info_auto_renew' => self::build_mapping_entry('membership', 'Auto_Renew__c'),
				'member_db_profile_account_info_membership_discount_type' => self::build_mapping_entry('membership', 'Discount_Group__c'),
				'member_db_profile_benefits_info_rescue_amount' => self::build_mapping_entry('membership', 'Rescue_Benefit_Amount__c'),
				'member_db_profile_benefits_info_medical_amount' => self::build_mapping_entry('membership', 'Medical_Benefit_Amount__c'),
				'member_db_profile_benefits_info_mortal_remains_amount' => self::build_mapping_entry('membership', 'Mortal_Remains_Amount__c'),
				'member_db_profile_benefits_info_rescue_reimbursement_process' => self::build_mapping_entry('membership', 'Rescue_Reimbursement_Process__c'),
				'member_db_membership_record_membership_id' => self::build_mapping_entry('membership', 'PMPro_Level_ID__c'),
				'member_db_profile_account_role' => self::build_mapping_entry('membership', 'Family_Account_Role__c'),
			],
			'transaction' => [
				'member_db_transaction_line_key' => self::build_mapping_entry('transaction', 'PMPro_Order_Line_Key__c'),
				'member_db_transaction_line_product_type' => self::build_mapping_entry('transaction', 'Product_Type__c'),
				'member_db_transaction_line_label' => self::build_mapping_entry('transaction', 'Product_Name__c'),
				'member_db_transaction_line_amount' => self::build_mapping_entry('transaction', 'Amount__c'),
				'member_db_transaction_line_gross_membership_amount' => self::build_mapping_entry('transaction', 'Gross_Membership_Amount__c'),
				'member_db_transaction_line_discount_amount' => self::build_mapping_entry('transaction', 'Discount_Amount__c'),
				'member_db_transaction_line_net_membership_amount' => self::build_mapping_entry('transaction', 'Net_Membership_Amount__c'),
				'member_db_transaction_line_discount_code' => self::build_mapping_entry('transaction', 'Discount_Code__c'),
				'member_db_transaction_line_discount_type' => self::build_mapping_entry('transaction', 'Discount_Type__c'),
				'member_db_transaction_record_id' => self::build_mapping_entry('transaction', 'PMPro_Order_ID__c'),
				'member_db_transaction_record_user_id' => self::build_mapping_entry('transaction', 'WordPress_User_ID__c'),
				'member_db_transaction_row_source_status' => self::build_mapping_entry('transaction', 'Status__c'),
				'member_db_transaction_record_gateway' => self::build_mapping_entry('transaction', 'Gateway__c'),
				'member_db_transaction_record_timestamp' => self::build_mapping_entry('transaction', 'Transaction_Date__c'),
				'member_db_transaction_record_membership_id' => self::build_mapping_entry('transaction', 'PMPro_Level_ID__c'),
				'member_db_transaction_record_code' => self::build_mapping_entry('transaction', 'Code__c'),
				'member_db_transaction_record_payment_transaction_id' => self::build_mapping_entry('transaction', 'Payment_Transaction_ID__c'),
				'member_db_transaction_record_subscription_transaction_id' => self::build_mapping_entry('transaction', 'Subscription_Transaction_ID__c'),
			],
		];
	}

	public static function get_group_labels() {
		return [
			'contact' => 'Contact',
			'membership' => 'Membership Term',
			'transaction' => 'Transaction',
		];
	}

	public static function get_object_definitions($settings = null) {
		$settings = is_array($settings) ? $settings : self::get_settings();
		$definitions = self::get_default_object_definitions($settings);
		$custom_objects = isset($settings['salesforce']['custom_objects']) && is_array($settings['salesforce']['custom_objects'])
			? $settings['salesforce']['custom_objects']
			: [];

		foreach ($custom_objects as $custom_object) {
			$normalized = self::normalize_custom_object_definition($custom_object);
			if (!$normalized) {
				continue;
			}

			$group = $normalized['group'];
			if (!isset($definitions[$group])) {
				$definitions[$group] = [];
			}

			$definitions[$group][$normalized['key']] = $normalized;
		}

		foreach (array_keys(self::get_group_labels()) as $group) {
			if (!isset($definitions[$group]) || !is_array($definitions[$group])) {
				$definitions[$group] = [];
			}
		}

		return $definitions;
	}

	public static function get_auth_state() {
		$state = get_option(self::AUTH_STATE_OPTION, []);
		return is_array($state) ? $state : [];
	}

	public static function update_auth_state($state) {
		update_option(self::AUTH_STATE_OPTION, is_array($state) ? $state : []);
	}

	public static function clear_auth_state() {
		delete_option(self::AUTH_STATE_OPTION);
	}

	public static function get_oauth_debug() {
		$debug = get_option(self::OAUTH_DEBUG_OPTION, []);
		return is_array($debug) ? $debug : [];
	}

	public static function update_oauth_debug($debug) {
		update_option(self::OAUTH_DEBUG_OPTION, is_array($debug) ? $debug : []);
	}

	public static function clear_oauth_debug() {
		delete_option(self::OAUTH_DEBUG_OPTION);
	}

	public static function get_oauth_redirect_uri() {
		return rest_url('aac-salesforce-sync/v1/oauth/callback');
	}

	public static function get_field_catalog() {
		$catalog = get_option(self::FIELD_CATALOG_OPTION, []);
		return is_array($catalog) ? $catalog : [];
	}

	public static function update_field_catalog($catalog) {
		update_option(self::FIELD_CATALOG_OPTION, is_array($catalog) ? $catalog : []);
	}

	public static function get_settings() {
		$stored = get_option(self::OPTION_KEY, []);
		$stored = is_array($stored) ? $stored : [];
		$settings = self::merge(self::get_defaults(), $stored);
		$settings = self::migrate_profile_field_key_aliases($settings);
		$settings = self::migrate_auto_renew_mapping_to_contact($settings);
		$settings = self::migrate_contact_member_id_mapping($settings);
		$settings = self::migrate_contact_communication_preference_mappings($settings);
		$settings = self::migrate_contact_membership_snapshot_mappings($settings);
		$settings = self::migrate_membership_term_defaults($settings);
		return self::migrate_transaction_line_defaults($settings);
	}

	public static function update_settings($input) {
		$current = self::get_settings();
		$input = is_array($input) ? $input : [];

		$settings = self::merge(self::get_defaults(), $current);

		$general = isset($input['general']) && is_array($input['general']) ? $input['general'] : [];
		$salesforce = isset($input['salesforce']) && is_array($input['salesforce']) ? $input['salesforce'] : [];
		$inbound = isset($input['inbound']) && is_array($input['inbound']) ? $input['inbound'] : [];
		$error_report = isset($input['error_report']) && is_array($input['error_report']) ? $input['error_report'] : [];
		$operations = isset($input['operations']) && is_array($input['operations']) ? $input['operations'] : [];
		$pull = isset($input['pull']) && is_array($input['pull']) ? $input['pull'] : [];
		$field_mappings = isset($input['field_mappings']) && is_array($input['field_mappings']) ? $input['field_mappings'] : [];

		if (array_key_exists('enabled', $general)) {
			$settings['general']['enabled'] = empty($general['enabled']) ? 0 : 1;
		}
		if (array_key_exists('batch_size', $general)) {
			$settings['general']['batch_size'] = max(1, min(50, absint($general['batch_size'])));
		}
		if (array_key_exists('max_attempts', $general)) {
			$settings['general']['max_attempts'] = max(1, min(20, absint($general['max_attempts'])));
		}
		if (array_key_exists('sync_contact', $general)) {
			$settings['general']['sync_contact'] = empty($general['sync_contact']) ? 0 : 1;
		}
		if (array_key_exists('sync_membership', $general)) {
			$settings['general']['sync_membership'] = empty($general['sync_membership']) ? 0 : 1;
		}
		if (array_key_exists('sync_transaction', $general)) {
			$settings['general']['sync_transaction'] = empty($general['sync_transaction']) ? 0 : 1;
		}

		$text_fields = [
			'auth_url',
			'token_url',
			'instance_url',
			'api_version',
			'oauth_scope',
			'client_id',
			'client_secret',
			'contact_object',
			'membership_object',
			'transaction_object',
			'contact_external_id_field',
			'membership_external_id_field',
			'transaction_external_id_field',
		];

		foreach ($text_fields as $field) {
			if (!array_key_exists($field, $salesforce)) {
				continue;
			}

			$value = (string) $salesforce[$field];
			$settings['salesforce'][$field] = in_array($field, ['auth_url', 'token_url', 'instance_url'], true)
				? esc_url_raw($value)
				: sanitize_text_field($value);
		}

		if (array_key_exists('secret', $inbound)) {
			$settings['inbound']['secret'] = sanitize_text_field((string) $inbound['secret']);
		}

		if (array_key_exists('enabled', $error_report)) {
			$settings['error_report']['enabled'] = empty($error_report['enabled']) ? 0 : 1;
		}
		if (array_key_exists('recipient_email', $error_report)) {
			$email = sanitize_email((string) $error_report['recipient_email']);
			$settings['error_report']['recipient_email'] = $email && is_email($email) ? $email : sanitize_email(get_option('admin_email'));
		}
		if (array_key_exists('daily_hour', $error_report)) {
			$settings['error_report']['daily_hour'] = max(0, min(23, absint($error_report['daily_hour'])));
		}
		if (array_key_exists('retention_days', $error_report)) {
			$settings['error_report']['retention_days'] = max(1, min(3650, absint($error_report['retention_days'])));
		}
		if (array_key_exists('alerts_enabled', $operations)) {
			$settings['operations']['alerts_enabled'] = empty($operations['alerts_enabled']) ? 0 : 1;
		}
		if (array_key_exists('alert_email', $operations)) {
			$email = sanitize_email((string) $operations['alert_email']);
			$settings['operations']['alert_email'] = $email && is_email($email) ? $email : sanitize_email(get_option('admin_email'));
		}
		if (array_key_exists('pending_sla_minutes', $operations)) {
			$settings['operations']['pending_sla_minutes'] = max(5, min(1440, absint($operations['pending_sla_minutes'])));
		}
		if (array_key_exists('dead_letter_threshold', $operations)) {
			$settings['operations']['dead_letter_threshold'] = max(1, min(1000, absint($operations['dead_letter_threshold'])));
		}
		if (array_key_exists('completed_job_retention_days', $operations)) {
			$settings['operations']['completed_job_retention_days'] = max(1, min(3650, absint($operations['completed_job_retention_days'])));
		}

		if (array_key_exists('enabled', $pull)) {
			$settings['pull']['enabled'] = empty($pull['enabled']) ? 0 : 1;
		}
		if (array_key_exists('sync_contact', $pull)) {
			$settings['pull']['sync_contact'] = empty($pull['sync_contact']) ? 0 : 1;
		}
		if (array_key_exists('sync_membership', $pull)) {
			$settings['pull']['sync_membership'] = empty($pull['sync_membership']) ? 0 : 1;
		}
		if (array_key_exists('interval', $pull)) {
			$interval = sanitize_key((string) $pull['interval']);
			$settings['pull']['interval'] = in_array($interval, ['aac_salesforce_sync_five_minutes', 'hourly', 'twicedaily', 'daily'], true) ? $interval : 'hourly';
		}
		if (array_key_exists('batch_size', $pull)) {
			$settings['pull']['batch_size'] = max(1, min(200, absint($pull['batch_size'])));
		}
		if (array_key_exists('lookback_hours', $pull)) {
			$settings['pull']['lookback_hours'] = max(1, min(720, absint($pull['lookback_hours'])));
		}

		if (array_key_exists('custom_objects_present', $salesforce)) {
			$custom_objects_input = isset($salesforce['custom_objects']) && is_array($salesforce['custom_objects']) ? $salesforce['custom_objects'] : [];
			$settings['salesforce']['custom_objects'] = self::sanitize_custom_objects($custom_objects_input);
		}

		$defaults = self::get_default_field_mappings();
		$definitions = self::get_field_definitions();
		$settings['field_mappings'] = self::merge($defaults, isset($settings['field_mappings']) && is_array($settings['field_mappings']) ? $settings['field_mappings'] : []);
		foreach ($definitions as $group => $fields) {
			$group_input = isset($field_mappings[$group]) && is_array($field_mappings[$group]) ? $field_mappings[$group] : [];
			foreach ($fields as $field_key => $definition) {
				if (!array_key_exists($field_key, $group_input)) {
					continue;
				}
				$settings['field_mappings'][$group][$field_key] = self::normalize_mapping_entry($group_input[$field_key], $group);
			}
		}

		update_option(self::OPTION_KEY, $settings);

		return $settings;
	}

	private static function merge($defaults, $stored) {
		foreach ($defaults as $key => $value) {
			if (!array_key_exists($key, $stored)) {
				$stored[$key] = $value;
				continue;
			}

			if (is_array($value) && is_array($stored[$key])) {
				$stored[$key] = self::merge($value, $stored[$key]);
			}
		}

		return $stored;
	}

	private static function migrate_auto_renew_mapping_to_contact($settings) {
		if (!is_array($settings)) {
			return $settings;
		}

		$contact_mapping = $settings['field_mappings']['contact']['member_db_profile_account_info_auto_renew'] ?? [];
		$membership_mapping = $settings['field_mappings']['membership']['member_db_profile_account_info_auto_renew'] ?? [];
		$contact_field = sanitize_text_field((string) ($contact_mapping['field'] ?? ''));
		$membership_field = sanitize_text_field((string) ($membership_mapping['field'] ?? ''));

		if ($contact_field === '' && $membership_field !== '') {
			$settings['field_mappings']['contact']['member_db_profile_account_info_auto_renew'] = self::normalize_mapping_entry($membership_mapping, 'contact');
		}

		return $settings;
	}

	private static function migrate_contact_member_id_mapping($settings) {
		if (!is_array($settings)) {
			return $settings;
		}

		$contact_mapping = $settings['field_mappings']['contact']['member_db_profile_member_id'] ?? [];
		$contact_field = sanitize_text_field((string) ($contact_mapping['field'] ?? ''));
		if ($contact_field === '') {
			$settings['field_mappings']['contact']['member_db_profile_member_id'] = self::build_mapping_entry('contact', 'AAC_Member_ID__c');
		}

		return $settings;
	}

	private static function migrate_contact_communication_preference_mappings($settings) {
		if (!is_array($settings)) {
			return $settings;
		}

		$default_map = [
			'member_db_profile_account_info_email_opt_out' => 'HasOptedOutOfEmail',
			'member_db_profile_account_info_do_not_call' => 'DoNotCall',
			'member_db_profile_account_info_do_not_contact' => 'npsp__Do_Not_Contact__c',
		];

		foreach ($default_map as $field_key => $target_field) {
			$current_mapping = $settings['field_mappings']['contact'][$field_key] ?? [];
			$current_field = sanitize_text_field((string) ($current_mapping['field'] ?? ''));
			if ($current_field === '') {
				$settings['field_mappings']['contact'][$field_key] = self::build_mapping_entry('contact', $target_field);
			}
		}

		return $settings;
	}

	private static function migrate_contact_membership_snapshot_mappings($settings) {
		if (!is_array($settings)) {
			return $settings;
		}

		$field_catalog = self::get_field_catalog();
		$candidate_map = [
			'member_db_profile_member_since' => ['Member_Since__c', 'Original_Join_Date__c'],
			'member_db_profile_current_membership_expiration_date' => ['Current_Membership_Expiration_Date__c', 'Current_Term_End_Date__c'],
			'member_db_profile_account_info_auto_renew' => ['Auto_Renewal__c', 'Auto_Renew__c'],
			'member_db_profile_primary_account_holder' => ['Primary_Account_Holder__c'],
			'member_db_profile_family_plan' => ['Family_Plan__c'],
		];

		foreach ($candidate_map as $field_key => $candidate_fields) {
			$current_mapping = $settings['field_mappings']['contact'][$field_key] ?? [];
			$current_field = sanitize_text_field((string) ($current_mapping['field'] ?? ''));
			$resolved_field = self::get_first_existing_catalog_field('contact', $candidate_fields, $field_catalog);

			if ($resolved_field === '') {
				continue;
			}

			if ($current_field === '' || !self::catalog_has_field('contact', $current_field, $field_catalog)) {
				$settings['field_mappings']['contact'][$field_key] = self::build_mapping_entry('contact', $resolved_field);
			}
		}

		$legacy_joined_mapping = self::normalize_mapping_entry($settings['field_mappings']['contact']['member_db_profile_profile_info_joined_date'] ?? [], 'contact');
		$current_member_since_mapping = self::normalize_mapping_entry($settings['field_mappings']['contact']['member_db_profile_member_since'] ?? [], 'contact');
		if ($current_member_since_mapping['field'] === '' && $legacy_joined_mapping['field'] !== '') {
			$settings['field_mappings']['contact']['member_db_profile_member_since'] = $legacy_joined_mapping;
		}
		unset($settings['field_mappings']['contact']['member_db_profile_profile_info_joined_date']);

		return $settings;
	}

	private static function migrate_profile_field_key_aliases($settings) {
		if (!is_array($settings)) {
			return $settings;
		}

		$field_aliases = [
			'contact' => [
				'member_db_profile_row_user_id' => 'member_db_profile_user_id',
				'member_db_profile_row_member_id' => 'member_db_profile_member_id',
				'member_db_profile_row_account_role' => 'member_db_profile_account_role',
			],
			'membership' => [
				'member_db_profile_row_user_id' => 'member_db_profile_user_id',
				'member_db_profile_row_member_id' => 'member_db_profile_member_id',
				'member_db_profile_row_membership_level' => 'member_db_profile_membership_level',
				'member_db_profile_row_membership_status' => 'member_db_profile_membership_status',
				'member_db_profile_row_renewal_date' => 'member_db_profile_renewal_date',
				'member_db_profile_row_expiration_date' => 'member_db_profile_expiration_date',
				'member_db_profile_row_account_role' => 'member_db_profile_account_role',
			],
		];

		foreach ($field_aliases as $group => $aliases) {
			foreach ($aliases as $legacy_key => $current_key) {
				$legacy_mapping = self::normalize_mapping_entry($settings['field_mappings'][$group][$legacy_key] ?? [], $group);
				$current_mapping = self::normalize_mapping_entry($settings['field_mappings'][$group][$current_key] ?? [], $group);
				if ($current_mapping['field'] === '' && $legacy_mapping['field'] !== '') {
					$settings['field_mappings'][$group][$current_key] = $legacy_mapping;
				}
				unset($settings['field_mappings'][$group][$legacy_key]);
			}
		}

		return $settings;
	}

	private static function migrate_membership_term_defaults($settings) {
		if (!is_array($settings)) {
			return $settings;
		}

		$current_object = sanitize_text_field((string) ($settings['salesforce']['membership_object'] ?? ''));
		if ($current_object === '' || $current_object === 'Membership__c') {
			$settings['salesforce']['membership_object'] = 'Membership_Term__c';
		}

		$field_catalog = self::get_field_catalog();
		$membership_fields = isset($field_catalog['objects']['membership']['fields']) && is_array($field_catalog['objects']['membership']['fields'])
			? $field_catalog['objects']['membership']['fields']
			: [];
		$membership_field_names = array_map(
			static function ($field) {
				return sanitize_text_field((string) ($field['name'] ?? ''));
			},
			$membership_fields
		);
		$user_id_field = in_array('Word_Press_User_ID__c', $membership_field_names, true) ? 'Word_Press_User_ID__c' : 'WordPress_User_ID__c';

		$default_membership_field_map = [
			'member_db_profile_user_id' => $user_id_field,
			'member_db_profile_member_id' => 'AAC_Member_ID__c',
			'member_db_profile_membership_level' => 'Membership_Level__c',
			'member_db_profile_membership_status' => 'Status__c',
			'member_db_profile_family_membership_mode' => 'Family_Membership__c',
			'member_db_membership_record_startdate' => 'Start_Date__c',
			'member_db_profile_renewal_date' => 'Renewal_Date__c',
			'member_db_profile_expiration_date' => 'End_Date__c',
			'member_db_profile_account_info_auto_renew' => 'Auto_Renew__c',
			'member_db_profile_account_info_membership_discount_type' => 'Discount_Group__c',
			'member_db_profile_benefits_info_rescue_amount' => 'Rescue_Benefit_Amount__c',
			'member_db_profile_benefits_info_medical_amount' => 'Medical_Benefit_Amount__c',
			'member_db_profile_benefits_info_mortal_remains_amount' => 'Mortal_Remains_Amount__c',
			'member_db_profile_benefits_info_rescue_reimbursement_process' => 'Rescue_Reimbursement_Process__c',
			'member_db_membership_record_membership_id' => 'PMPro_Level_ID__c',
			'member_db_profile_account_role' => 'Family_Account_Role__c',
		];

		foreach ($default_membership_field_map as $field_key => $target_field) {
			$current_mapping = $settings['field_mappings']['membership'][$field_key] ?? [];
			$current_field = sanitize_text_field((string) ($current_mapping['field'] ?? ''));
			if ($current_field === '') {
				$settings['field_mappings']['membership'][$field_key] = self::build_mapping_entry('membership', $target_field);
				continue;
			}

			if ($field_key === 'member_db_profile_expiration_date' && $current_field === 'Expiration_Date__c') {
				$settings['field_mappings']['membership'][$field_key] = self::build_mapping_entry('membership', 'End_Date__c');
			}

			if ($field_key === 'member_db_profile_user_id' && $current_field === 'WordPress_User_ID__c' && $target_field === 'Word_Press_User_ID__c') {
				$settings['field_mappings']['membership'][$field_key] = self::build_mapping_entry('membership', $target_field);
			}

			if ($field_key === 'member_db_membership_record_membership_id' && $current_field === 'AAC_Member_ID__c') {
				$settings['field_mappings']['membership'][$field_key] = self::build_mapping_entry('membership', 'PMPro_Level_ID__c');
			}
		}

		$legacy_membership_field_map = [
			'member_db_profile_row_user_id' => 'member_db_profile_user_id',
			'member_db_profile_row_member_id' => 'member_db_profile_member_id',
			'member_db_profile_row_membership_level' => 'member_db_profile_membership_level',
			'member_db_profile_row_membership_status' => 'member_db_profile_membership_status',
			'member_db_profile_row_renewal_date' => 'member_db_profile_renewal_date',
			'member_db_profile_row_expiration_date' => 'member_db_profile_expiration_date',
			'member_db_profile_row_account_role' => 'member_db_profile_account_role',
		];

		foreach ($legacy_membership_field_map as $legacy_key => $current_key) {
			$legacy_mapping = self::normalize_mapping_entry($settings['field_mappings']['membership'][$legacy_key] ?? [], 'membership');
			$current_mapping = self::normalize_mapping_entry($settings['field_mappings']['membership'][$current_key] ?? [], 'membership');
			$current_field = sanitize_text_field((string) ($current_mapping['field'] ?? ''));
			$legacy_field = sanitize_text_field((string) ($legacy_mapping['field'] ?? ''));

			if ($current_field === '' && $legacy_field !== '') {
				$settings['field_mappings']['membership'][$current_key] = $legacy_mapping;
			}

			unset($settings['field_mappings']['membership'][$legacy_key]);
		}

		return $settings;
	}

	private static function migrate_transaction_line_defaults($settings) {
		if (!is_array($settings)) {
			return $settings;
		}

		$current_external_id = sanitize_text_field((string) ($settings['salesforce']['transaction_external_id_field'] ?? ''));
		if ($current_external_id === '' || $current_external_id === 'PMPro_Order_ID__c') {
			$settings['salesforce']['transaction_external_id_field'] = 'PMPro_Order_Line_Key__c';
		}

		$default_transaction_field_map = [
			'member_db_transaction_line_key' => 'PMPro_Order_Line_Key__c',
			'member_db_transaction_line_product_type' => 'Product_Type__c',
			'member_db_transaction_line_label' => 'Product_Name__c',
			'member_db_transaction_line_amount' => 'Amount__c',
			'member_db_transaction_line_gross_membership_amount' => 'Gross_Membership_Amount__c',
			'member_db_transaction_line_discount_amount' => 'Discount_Amount__c',
			'member_db_transaction_line_net_membership_amount' => 'Net_Membership_Amount__c',
			'member_db_transaction_line_discount_code' => 'Discount_Code__c',
			'member_db_transaction_line_discount_type' => 'Discount_Type__c',
			'member_db_transaction_record_id' => 'PMPro_Order_ID__c',
		];

		foreach ($default_transaction_field_map as $field_key => $target_field) {
			$current_mapping = $settings['field_mappings']['transaction'][$field_key] ?? [];
			$current_field = sanitize_text_field((string) ($current_mapping['field'] ?? ''));
			if ($current_field === '') {
				$settings['field_mappings']['transaction'][$field_key] = self::build_mapping_entry('transaction', $target_field);
			}
		}

		return $settings;
	}

	private static function build_mapping_entry($object_key, $field_name) {
		$object_key = sanitize_key((string) $object_key);
		return [
			'object' => $object_key,
			'field' => sanitize_text_field((string) $field_name),
			'direction' => $object_key === 'transaction' ? 'outbound' : 'bidirectional',
			'clear_empty' => 0,
		];
	}

	private static function get_first_existing_catalog_field($object_key, $candidates, $field_catalog = []) {
		foreach ((array) $candidates as $candidate) {
			$candidate = sanitize_text_field((string) $candidate);
			if ($candidate !== '' && self::catalog_has_field($object_key, $candidate, $field_catalog)) {
				return $candidate;
			}
		}

		return '';
	}

	private static function catalog_has_field($object_key, $field_name, $field_catalog = []) {
		$object_key = sanitize_key((string) $object_key);
		$field_name = sanitize_text_field((string) $field_name);
		if ($object_key === '' || $field_name === '') {
			return false;
		}

		$catalog_group = isset($field_catalog['objects'][$object_key]) && is_array($field_catalog['objects'][$object_key])
			? $field_catalog['objects'][$object_key]
			: [];
		$fields = isset($catalog_group['fields']) && is_array($catalog_group['fields']) ? $catalog_group['fields'] : [];

		foreach ($fields as $field) {
			if (!is_array($field)) {
				continue;
			}

			if (sanitize_text_field((string) ($field['name'] ?? '')) === $field_name) {
				return true;
			}
		}

		return false;
	}

	private static function get_default_object_definitions($settings = []) {
		$salesforce = isset($settings['salesforce']) && is_array($settings['salesforce']) ? $settings['salesforce'] : [];
		return [
			'contact' => [
				'contact' => [
					'key' => 'contact',
					'group' => 'contact',
					'label' => 'Primary Contact',
					'object_name' => sanitize_text_field((string) ($salesforce['contact_object'] ?? 'Contact')),
					'external_id_field' => sanitize_text_field((string) ($salesforce['contact_external_id_field'] ?? 'WordPress_User_ID__c')),
					'is_default' => true,
				],
			],
			'membership' => [
				'membership' => [
					'key' => 'membership',
					'group' => 'membership',
					'label' => 'Primary Membership Term',
					'object_name' => sanitize_text_field((string) ($salesforce['membership_object'] ?? 'Membership_Term__c')),
					'external_id_field' => sanitize_text_field((string) ($salesforce['membership_external_id_field'] ?? 'AAC_External_Key__c')),
					'is_default' => true,
				],
			],
			'transaction' => [
				'transaction' => [
					'key' => 'transaction',
					'group' => 'transaction',
					'label' => 'Primary Transaction',
					'object_name' => sanitize_text_field((string) ($salesforce['transaction_object'] ?? 'Payment_Transaction__c')),
					'external_id_field' => sanitize_text_field((string) ($salesforce['transaction_external_id_field'] ?? 'PMPro_Order_Line_Key__c')),
					'is_default' => true,
				],
			],
		];
	}

	private static function sanitize_custom_objects($custom_objects_input) {
		$normalized = [];
		$seen_keys = [];

		foreach ((array) $custom_objects_input as $row) {
			$row = is_array($row) ? $row : [];
			$group = sanitize_key((string) ($row['group'] ?? ''));
			if (!isset(self::get_group_labels()[$group])) {
				continue;
			}

			$object_name = sanitize_text_field((string) ($row['object_name'] ?? ''));
			if ($object_name === '') {
				continue;
			}

			$raw_key = sanitize_key((string) ($row['key'] ?? ''));
			$key = $raw_key !== '' ? $raw_key : sanitize_key($group . '_' . $object_name);
			if ($key === '' || in_array($key, ['contact', 'membership', 'transaction'], true)) {
				$key = sanitize_key('custom_' . $group . '_' . $object_name);
			}

			$base_key = $key;
			$suffix = 2;
			while (isset($seen_keys[$key])) {
				$key = $base_key . '_' . $suffix;
				$suffix++;
			}
			$seen_keys[$key] = true;

			$normalized[] = [
				'key' => $key,
				'group' => $group,
				'label' => sanitize_text_field((string) ($row['label'] ?? $object_name)),
				'object_name' => $object_name,
				'external_id_field' => sanitize_text_field((string) ($row['external_id_field'] ?? '')),
				'is_default' => false,
			];
		}

		return $normalized;
	}

	public static function normalize_mapping_entry($entry, $default_object_key = '') {
		if (is_string($entry)) {
			$field = sanitize_text_field($entry);
				return [
					'object' => $field !== '' ? sanitize_key((string) $default_object_key) : '',
					'field' => $field,
					'direction' => $default_object_key === 'transaction' ? 'outbound' : 'bidirectional',
					'clear_empty' => 0,
				];
		}

		if (!is_array($entry)) {
			return [
				'object' => '',
				'field' => '',
				'direction' => 'bidirectional',
				'clear_empty' => 0,
			];
		}

		$field = sanitize_text_field((string) ($entry['field'] ?? ''));
		$object = sanitize_key((string) ($entry['object'] ?? ''));
		if ($field !== '' && $object === '') {
			$object = sanitize_key((string) $default_object_key);
		}
		$direction = sanitize_key((string) ($entry['direction'] ?? 'bidirectional'));
		if (!in_array($direction, ['outbound', 'inbound', 'bidirectional'], true)) {
			$direction = 'bidirectional';
		}

		return [
			'object' => $object,
			'field' => $field,
			'direction' => $direction,
			'clear_empty' => empty($entry['clear_empty']) ? 0 : 1,
		];
	}

	private static function normalize_custom_object_definition($object_definition) {
		if (!is_array($object_definition)) {
			return null;
		}

		$group = sanitize_key((string) ($object_definition['group'] ?? ''));
		$key = sanitize_key((string) ($object_definition['key'] ?? ''));
		$object_name = sanitize_text_field((string) ($object_definition['object_name'] ?? ''));

		if (!isset(self::get_group_labels()[$group]) || $key === '' || $object_name === '') {
			return null;
		}

		return [
			'key' => $key,
			'group' => $group,
			'label' => sanitize_text_field((string) ($object_definition['label'] ?? $object_name)),
			'object_name' => $object_name,
			'external_id_field' => sanitize_text_field((string) ($object_definition['external_id_field'] ?? '')),
			'is_default' => !empty($object_definition['is_default']),
		];
	}

	private static function get_member_database_field_definitions() {
		$profile_row_fields = self::build_member_database_profile_row_field_definitions();
		$profile_payload_fields = self::build_path_field_definitions(
			'profile',
			'Member Database Profile',
			'member_db.profile',
			self::get_member_database_profile_fallback_paths()
		);
		$membership_record_fields = self::build_path_field_definitions(
			'membership_record',
			'Member Database Membership Record',
			'member_db.membership_record',
			self::get_pmpro_membership_fallback_paths()
		);
		$subscription_record_fields = self::build_path_field_definitions(
			'subscription_record',
			'Member Database Subscription Record',
			'member_db.subscription_record',
			self::get_pmpro_subscription_fallback_paths()
		);
		$transaction_row_fields = self::build_path_field_definitions(
			'transaction_row',
			'Member Database Transaction Row',
			'member_db.transaction_row',
			self::get_transaction_row_fallback_paths()
		);
		$transaction_record_fields = self::build_path_field_definitions(
			'transaction_record',
			'Member Database Transaction Record',
			'member_db.transaction_record',
			self::get_pmpro_transaction_fallback_paths()
		);
		$transaction_line_fields = self::build_path_field_definitions(
			'transaction_line',
			'Member Database Transaction Line',
			'member_db.transaction_line',
			self::get_transaction_line_fallback_paths()
		);

		$contact_fields = array_merge($profile_payload_fields, $profile_row_fields);
		$membership_fields = array_merge($profile_payload_fields, $profile_row_fields, $membership_record_fields, $subscription_record_fields);

		// Auto renew is a current member preference we want staff to map alongside
		// the rest of the contact snapshot, not buried in term history.
		if (isset($profile_payload_fields['member_db_profile_account_info_auto_renew'])) {
			$contact_fields['member_db_profile_account_info_auto_renew'] = $profile_payload_fields['member_db_profile_account_info_auto_renew'];
		}

		return [
			'contact' => $contact_fields,
			'membership' => $membership_fields,
			'transaction' => array_merge($transaction_row_fields, $transaction_record_fields, $transaction_line_fields),
		];
	}

	private static function build_mirror_row_field_definitions($field_prefix, $label_prefix, $source_prefix, $table_name, $excluded_columns = []) {
		$definitions = [];
		foreach (self::describe_table_columns($table_name) as $column_name => $column_type) {
			if (in_array($column_name, $excluded_columns, true)) {
				continue;
			}

			$field_key = 'member_db_' . $field_prefix . '_' . self::sanitize_field_key($column_name);
			$definitions[$field_key] = [
				'label' => $label_prefix . ': ' . self::humanize_label($column_name),
				'source_path' => $source_prefix . '.' . $column_name,
				'type' => self::normalize_column_type($column_type),
			];
		}

		ksort($definitions);
		return $definitions;
	}

	private static function build_path_field_definitions($field_prefix, $label_prefix, $source_prefix, $paths) {
		$definitions = [];
		foreach ($paths as $path => $type) {
			$field_key = 'member_db_' . $field_prefix . '_' . self::sanitize_field_key($path);
			$definitions[$field_key] = [
				'label' => $label_prefix . ': ' . self::humanize_path_label($path),
				'source_path' => $source_prefix . '.' . $path,
				'type' => $type,
			];
		}

		ksort($definitions);
		return $definitions;
	}

	private static function discover_json_paths_from_table($table_name, $column_name, $fallback_paths) {
		global $wpdb;

		if (!$wpdb || !$table_name) {
			return $fallback_paths;
		}

		$table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ($table_exists !== $table_name) {
			return $fallback_paths;
		}

		$rows = $wpdb->get_col("SELECT {$column_name} FROM {$table_name} WHERE {$column_name} IS NOT NULL AND {$column_name} != '' ORDER BY mirrored_at DESC LIMIT 25"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$paths = [];

		foreach ((array) $rows as $raw_value) {
			$decoded = json_decode((string) $raw_value, true);
			if (!is_array($decoded)) {
				continue;
			}

			$paths = array_merge($paths, self::flatten_definition_paths($decoded));
		}

		if (!$paths) {
			return $fallback_paths;
		}

		ksort($paths);
		return $paths;
	}

	private static function get_member_database_profile_fallback_paths() {
		return [
			'user_id' => 'integer',
			'display_name' => 'string',
			'email' => 'email',
			'member_id' => 'string',
			'membership_level' => 'string',
			'membership_status' => 'string',
			'renewal_date' => 'date',
			'expiration_date' => 'date',
			'account_role' => 'string',
			'account_info.first_name' => 'string',
			'account_info.last_name' => 'string',
			'account_info.name' => 'string',
			'account_info.email' => 'email',
			'account_info.phone' => 'string',
			'account_info.phone_type' => 'string',
			'account_info.street' => 'string',
			'account_info.address2' => 'string',
			'account_info.city' => 'string',
			'account_info.state' => 'string',
			'account_info.zip' => 'string',
			'account_info.country' => 'string',
			'account_info.photo_url' => 'url',
			'account_info.birthdate' => 'date',
			'account_info.size' => 'string',
			'account_info.publication_pref' => 'string',
			'account_info.aaj_pref' => 'string',
			'account_info.anac_pref' => 'string',
			'account_info.acj_pref' => 'string',
			'account_info.guidebook_pref' => 'string',
			'account_info.membership_discount_type' => 'string',
			'account_info.auto_renew' => 'boolean',
			'account_info.payment_method' => 'string',
			'account_info.email_opt_out' => 'boolean',
			'account_info.do_not_call' => 'boolean',
			'account_info.do_not_contact' => 'boolean',
			'member_since' => 'date',
			'current_membership_expiration_date' => 'date',
			'current_auto_renewal' => 'boolean',
			'primary_account_holder' => 'boolean',
			'family_plan' => 'boolean',
			'profile_info.member_id' => 'string',
			'profile_info.joined_date' => 'date',
			'benefits_info.rescue_amount' => 'decimal',
			'benefits_info.medical_amount' => 'decimal',
			'benefits_info.mortal_remains_amount' => 'decimal',
			'benefits_info.rescue_reimbursement_process' => 'boolean',
			'family_membership.mode' => 'string',
			'family_membership.additional_adult' => 'boolean',
			'family_membership.dependent_count' => 'integer',
			'group_account.id' => 'integer',
			'group_account.checkout_code' => 'string',
			'group_account.total_seats' => 'integer',
			'group_account.active_members' => 'integer',
			'group_account.parent_user_id' => 'integer',
			'group_account.account_role' => 'string',
			'linked_parent_account.name' => 'string',
		];
	}

	private static function build_member_database_profile_row_field_definitions() {
		$row_paths = [
			'user_id' => 'integer',
			'display_name' => 'string',
			'email' => 'email',
			'member_id' => 'string',
			'membership_level' => 'string',
			'membership_status' => 'string',
			'renewal_date' => 'date',
			'expiration_date' => 'date',
			'account_role' => 'string',
		];

		$definitions = [];
		foreach ($row_paths as $path => $type) {
			$field_key = 'member_db_profile_' . self::sanitize_field_key($path);
			$definitions[$field_key] = [
				'label' => 'Member Database Profile: ' . self::humanize_path_label($path),
				'source_path' => 'member_db.profile_row.' . $path,
				'type' => $type,
			];
		}

		ksort($definitions);
		return $definitions;
	}

	private static function get_pmpro_membership_fallback_paths() {
		return [
			'id' => 'integer',
			'membership_id' => 'integer',
			'initial_payment' => 'decimal',
			'billing_amount' => 'decimal',
			'cycle_number' => 'integer',
			'cycle_period' => 'string',
			'billing_limit' => 'integer',
			'trial_amount' => 'decimal',
			'trial_limit' => 'integer',
			'status' => 'string',
			'startdate' => 'datetime',
			'enddate' => 'datetime',
			'modified' => 'datetime',
		];
	}

	private static function get_pmpro_subscription_fallback_paths() {
		return [
			'id' => 'integer',
			'membership_id' => 'integer',
			'subscription_transaction_id' => 'string',
			'gateway' => 'string',
			'gateway_environment' => 'string',
			'status' => 'string',
			'billing_amount' => 'decimal',
			'cycle_number' => 'integer',
			'cycle_period' => 'string',
			'billing_limit' => 'integer',
			'trial_amount' => 'decimal',
			'trial_limit' => 'integer',
			'startdate' => 'datetime',
			'enddate' => 'datetime',
			'next_payment_date' => 'datetime',
			'modified' => 'datetime',
		];
	}

	private static function get_pmpro_transaction_fallback_paths() {
		return [
			'id' => 'integer',
			'user_id' => 'integer',
			'membership_id' => 'integer',
			'code' => 'string',
			'subtotal' => 'decimal',
			'tax' => 'decimal',
			'total' => 'decimal',
			'payment_type' => 'string',
			'status' => 'string',
			'gateway' => 'string',
			'gateway_environment' => 'string',
			'payment_transaction_id' => 'string',
			'subscription_transaction_id' => 'string',
			'timestamp' => 'datetime',
			'notes' => 'string',
		];
	}

	private static function get_transaction_row_fallback_paths() {
		return [
			'source_record_id' => 'integer',
			'source_status' => 'string',
			'source_date' => 'datetime',
		];
	}

	private static function get_transaction_line_fallback_paths() {
		return [
			'line_key' => 'string',
			'line_type' => 'string',
			'product_type' => 'string',
			'label' => 'string',
			'amount' => 'decimal',
			'gross_membership_amount' => 'decimal',
			'discount_amount' => 'decimal',
			'net_membership_amount' => 'decimal',
			'donation_amount' => 'decimal',
			'discount_code' => 'string',
			'discount_type' => 'string',
			'pmpro_order_id' => 'integer',
			'pmpro_order_code' => 'string',
			'payment_transaction_id' => 'string',
			'subscription_transaction_id' => 'string',
		];
	}

	private static function flatten_definition_paths($value, $prefix = '') {
		$paths = [];

		if (!is_array($value)) {
			if ($prefix !== '') {
				$paths[$prefix] = self::infer_value_type($value);
			}
			return $paths;
		}

		foreach ($value as $key => $item) {
			$child_key = $prefix === '' ? (string) $key : $prefix . '.' . $key;
			if (is_array($item)) {
				if (self::is_assoc($item)) {
					$paths = array_merge($paths, self::flatten_definition_paths($item, $child_key));
				} else {
					$paths[$child_key] = 'array';
				}
				continue;
			}

			$paths[$child_key] = self::infer_value_type($item);
		}

		return $paths;
	}

	private static function infer_value_type($value) {
		if (is_bool($value)) {
			return 'boolean';
		}

		if (is_int($value)) {
			return 'integer';
		}

		if (is_float($value) || is_numeric($value) && strpos((string) $value, '.') !== false) {
			return 'decimal';
		}

		if (is_array($value)) {
			return 'array';
		}

		$string = (string) $value;
		if ($string !== '' && is_email($string)) {
			return 'email';
		}

		if ($string !== '' && filter_var($string, FILTER_VALIDATE_URL)) {
			return 'url';
		}

		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $string)) {
			return 'date';
		}

		if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}/', $string)) {
			return 'datetime';
		}

		if (is_numeric($string)) {
			return ctype_digit(ltrim($string, '-')) ? 'integer' : 'decimal';
		}

		return 'string';
	}

	private static function describe_table_columns($table_name) {
		global $wpdb;

		if (!$wpdb || !$table_name) {
			return [];
		}

		$table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ($table_exists !== $table_name) {
			return [];
		}

		$columns = [];
		$results = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}", ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ((array) $results as $column) {
			$field_name = sanitize_key($column['Field'] ?? '');
			if ($field_name === '') {
				continue;
			}
			$columns[$field_name] = sanitize_text_field((string) ($column['Type'] ?? 'varchar'));
		}

		return $columns;
	}

	private static function get_mirror_table_name($table_suffix) {
		global $wpdb;

		if (!$wpdb) {
			return '';
		}

		return $wpdb->prefix . $table_suffix;
	}

	private static function normalize_column_type($column_type) {
		$column_type = strtolower((string) $column_type);

		if (strpos($column_type, 'tinyint(1)') !== false || strpos($column_type, 'bool') !== false) {
			return 'boolean';
		}
		if (strpos($column_type, 'int') !== false) {
			return 'integer';
		}
		if (strpos($column_type, 'decimal') !== false || strpos($column_type, 'float') !== false || strpos($column_type, 'double') !== false) {
			return 'decimal';
		}
		if (strpos($column_type, 'datetime') !== false || strpos($column_type, 'timestamp') !== false) {
			return 'datetime';
		}
		if (strpos($column_type, 'date') !== false) {
			return 'date';
		}

		return 'string';
	}

	private static function humanize_label($value) {
		$value = str_replace(['_', '-'], ' ', (string) $value);
		return ucwords(trim($value));
	}

	private static function humanize_path_label($path) {
		$segments = array_map([__CLASS__, 'humanize_label'], explode('.', (string) $path));
		return implode(' > ', $segments);
	}

	private static function sanitize_field_key($value) {
		return strtolower(preg_replace('/[^a-z0-9]+/', '_', (string) $value));
	}

	private static function is_assoc(array $array) {
		return array_keys($array) !== range(0, count($array) - 1);
	}
}
