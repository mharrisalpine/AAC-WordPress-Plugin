<?php

if (!defined('ABSPATH')) {
	exit;
}

class AAC_Salesforce_Sync_Settings {
	const OPTION_KEY = 'aac_salesforce_sync_settings';
	const PAGE_SLUG = 'aac-salesforce-sync';
	const FIELD_CATALOG_OPTION = 'aac_salesforce_sync_field_catalog';

	public static function get_defaults() {
		return [
			'general' => [
				'enabled' => 0,
				'batch_size' => 10,
				'max_attempts' => 5,
			],
			'salesforce' => [
				'token_url' => '',
				'instance_url' => '',
				'api_version' => '61.0',
				'client_id' => '',
				'client_secret' => '',
				'contact_object' => 'Contact',
				'membership_object' => 'Membership__c',
				'transaction_object' => 'Payment_Transaction__c',
				'contact_external_id_field' => 'WordPress_User_ID__c',
				'membership_external_id_field' => 'AAC_External_Key__c',
				'transaction_external_id_field' => 'PMPro_Order_ID__c',
			],
			'inbound' => [
				'secret' => '',
			],
			'field_mappings' => self::get_default_field_mappings(),
		];
	}

	public static function get_field_definitions() {
		$member_db_fields = self::get_member_database_field_definitions();
		$pmpro_fields = self::get_pmpro_field_definitions();

		return [
			'contact' => $member_db_fields,
			'membership' => array_merge(
				$member_db_fields,
				$pmpro_fields['membership'],
				$pmpro_fields['subscriptions']
			),
			'transaction' => $pmpro_fields['transactions'],
		];
	}

	public static function get_default_field_mappings() {
		return [
			'contact' => [
				'member_db_row_user_id' => 'WordPress_User_ID__c',
				'member_db_profile_account_info_first_name' => 'FirstName',
				'member_db_profile_account_info_last_name' => 'LastName',
				'member_db_profile_account_info_email' => 'Email',
				'member_db_profile_account_info_phone' => 'Phone',
				'member_db_profile_account_info_street' => 'MailingStreet',
				'member_db_profile_account_info_city' => 'MailingCity',
				'member_db_profile_account_info_state' => 'MailingState',
				'member_db_profile_account_info_zip' => 'MailingPostalCode',
				'member_db_profile_account_info_country' => 'MailingCountry',
				'member_db_row_account_role' => 'AAC_Family_Account_Role__c',
			],
			'membership' => [
				'member_db_row_user_id' => 'WordPress_User_ID__c',
				'member_db_row_member_id' => 'AAC_Member_ID__c',
				'member_db_row_membership_level' => 'Membership_Level__c',
				'member_db_row_membership_status' => 'Status__c',
				'member_db_row_renewal_date' => 'Renewal_Date__c',
				'member_db_row_expiration_date' => 'Expiration_Date__c',
				'member_db_profile_account_info_auto_renew' => 'Auto_Renew__c',
				'member_db_profile_benefits_info_rescue_amount' => 'Rescue_Benefit_Amount__c',
				'member_db_profile_benefits_info_medical_amount' => 'Medical_Benefit_Amount__c',
				'member_db_profile_benefits_info_mortal_remains_amount' => 'Mortal_Remains_Amount__c',
				'member_db_profile_benefits_info_rescue_reimbursement_process' => 'Rescue_Reimbursement_Process__c',
				'pmpro_membership_membership_id' => 'PMPro_Level_ID__c',
				'member_db_row_account_role' => 'Family_Account_Role__c',
			],
			'transaction' => [
				'pmpro_transaction_id' => 'PMPro_Order_ID__c',
				'pmpro_transaction_user_id' => 'WordPress_User_ID__c',
				'pmpro_transaction_total' => 'Amount__c',
				'pmpro_transaction_status' => 'Status__c',
				'pmpro_transaction_gateway' => 'Gateway__c',
				'pmpro_transaction_timestamp' => 'Transaction_Date__c',
				'pmpro_transaction_membership_id' => 'PMPro_Level_ID__c',
				'pmpro_transaction_code' => 'Code__c',
				'pmpro_transaction_payment_transaction_id' => 'Payment_Transaction_ID__c',
				'pmpro_transaction_subscription_transaction_id' => 'Subscription_Transaction_ID__c',
			],
		];
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
		return self::merge(self::get_defaults(), $stored);
	}

	public static function update_settings($input) {
		$current = self::get_settings();
		$input = is_array($input) ? $input : [];

		$settings = self::merge(self::get_defaults(), $current);

		$general = isset($input['general']) && is_array($input['general']) ? $input['general'] : [];
		$salesforce = isset($input['salesforce']) && is_array($input['salesforce']) ? $input['salesforce'] : [];
		$inbound = isset($input['inbound']) && is_array($input['inbound']) ? $input['inbound'] : [];
		$field_mappings = isset($input['field_mappings']) && is_array($input['field_mappings']) ? $input['field_mappings'] : [];

		$settings['general']['enabled'] = empty($general['enabled']) ? 0 : 1;
		$settings['general']['batch_size'] = max(1, min(50, absint($general['batch_size'] ?? $settings['general']['batch_size'])));
		$settings['general']['max_attempts'] = max(1, min(20, absint($general['max_attempts'] ?? $settings['general']['max_attempts'])));

		$text_fields = [
			'token_url',
			'instance_url',
			'api_version',
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
			$settings['salesforce'][$field] = in_array($field, ['token_url', 'instance_url'], true)
				? esc_url_raw($value)
				: sanitize_text_field($value);
		}

		if (array_key_exists('secret', $inbound)) {
			$settings['inbound']['secret'] = sanitize_text_field((string) $inbound['secret']);
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
				$settings['field_mappings'][$group][$field_key] = sanitize_text_field((string) $group_input[$field_key]);
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

	private static function get_member_database_field_definitions() {
		$definitions = [];

		foreach (self::describe_member_database_profile_table() as $column_name => $column_type) {
			if ('id' === $column_name || 'raw_profile' === $column_name) {
				continue;
			}

			$field_key = 'member_db_row_' . self::sanitize_field_key($column_name);
			$definitions[$field_key] = [
				'label' => 'Member Database Row: ' . self::humanize_label($column_name),
				'source_path' => 'member_db.row.' . $column_name,
				'type' => self::normalize_column_type($column_type),
			];
		}

		foreach (self::discover_member_database_profile_paths() as $path => $type) {
			$field_key = 'member_db_profile_' . self::sanitize_field_key($path);
			$definitions[$field_key] = [
				'label' => 'Member Database Profile: ' . self::humanize_path_label($path),
				'source_path' => 'member_db.profile.' . $path,
				'type' => $type,
			];
		}

		ksort($definitions);
		return $definitions;
	}

	private static function get_pmpro_field_definitions() {
		return [
			'membership' => self::build_table_field_definitions(
				'pmpro_membership',
				'PMPro Membership',
				'pmpro.membership',
				self::get_pmpro_table_name('pmpro_memberships_users')
			),
			'subscriptions' => self::build_table_field_definitions(
				'pmpro_subscription',
				'PMPro Subscription',
				'pmpro.subscription',
				self::get_pmpro_table_name('pmpro_subscriptions')
			),
			'transactions' => self::build_table_field_definitions(
				'pmpro_transaction',
				'PMPro Order',
				'pmpro.transaction',
				self::get_pmpro_table_name('pmpro_membership_orders')
			),
		];
	}

	private static function build_table_field_definitions($field_prefix, $label_prefix, $source_prefix, $table_name) {
		$definitions = [];
		foreach (self::describe_table_columns($table_name) as $column_name => $column_type) {
			$field_key = $field_prefix . '_' . self::sanitize_field_key($column_name);
			$definitions[$field_key] = [
				'label' => $label_prefix . ': ' . self::humanize_label($column_name),
				'source_path' => $source_prefix . '.' . $column_name,
				'type' => self::normalize_column_type($column_type),
			];
		}

		return $definitions;
	}

	private static function describe_member_database_profile_table() {
		global $wpdb;

		if (!$wpdb) {
			return [];
		}

		return self::describe_table_columns($wpdb->prefix . 'aac_member_db_profiles');
	}

	private static function discover_member_database_profile_paths() {
		global $wpdb;

		if (!$wpdb) {
			return self::get_member_database_profile_fallback_paths();
		}

		$table_name = $wpdb->prefix . 'aac_member_db_profiles';
		$rows = $wpdb->get_col("SELECT raw_profile FROM {$table_name} WHERE raw_profile IS NOT NULL AND raw_profile != '' ORDER BY mirrored_at DESC LIMIT 25"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$paths = [];

		foreach ((array) $rows as $raw_profile) {
			$profile = json_decode((string) $raw_profile, true);
			if (!is_array($profile)) {
				continue;
			}

			$paths = array_merge($paths, self::flatten_definition_paths($profile));
		}

		if (!$paths) {
			return self::get_member_database_profile_fallback_paths();
		}

		ksort($paths);
		return $paths;
	}

	private static function get_member_database_profile_fallback_paths() {
		return [
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
			'account_info.size' => 'string',
			'account_info.publication_pref' => 'string',
			'account_info.aaj_pref' => 'string',
			'account_info.anac_pref' => 'string',
			'account_info.acj_pref' => 'string',
			'account_info.guidebook_pref' => 'string',
			'account_info.membership_discount_type' => 'string',
			'account_info.auto_renew' => 'boolean',
			'account_info.payment_method' => 'string',
			'profile_info.member_id' => 'string',
			'profile_info.tier' => 'string',
			'profile_info.status' => 'string',
			'profile_info.joined_date' => 'date',
			'profile_info.renewal_date' => 'date',
			'profile_info.expiration_date' => 'date',
			'benefits_info.rescue_amount' => 'decimal',
			'benefits_info.medical_amount' => 'decimal',
			'benefits_info.mortal_remains_amount' => 'decimal',
			'benefits_info.rescue_reimbursement_process' => 'boolean',
			'family_membership.mode' => 'string',
			'family_membership.additional_adult' => 'boolean',
			'family_membership.dependent_count' => 'integer',
			'linked_parent_account.name' => 'string',
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

	private static function get_pmpro_table_name($property) {
		global $wpdb;

		if ($wpdb && !empty($wpdb->{$property})) {
			return $wpdb->{$property};
		}

		return '';
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
