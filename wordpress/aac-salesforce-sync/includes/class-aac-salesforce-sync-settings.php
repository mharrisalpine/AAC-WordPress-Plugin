<?php

if (!defined('ABSPATH')) {
	exit;
}

class AAC_Salesforce_Sync_Settings {
	const OPTION_KEY = 'aac_salesforce_sync_settings';
	const PAGE_SLUG = 'aac-salesforce-sync';

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
		];
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
}
