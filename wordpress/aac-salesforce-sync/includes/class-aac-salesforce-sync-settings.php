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
		return [
			'contact' => [
				'wordpress_user_id' => ['label' => 'WordPress User ID', 'source_path' => 'wp.ID', 'type' => 'integer'],
				'wordpress_user_login' => ['label' => 'WordPress Username', 'source_path' => 'wp.user_login', 'type' => 'string'],
				'wordpress_user_email' => ['label' => 'WordPress Email', 'source_path' => 'wp.user_email', 'type' => 'email'],
				'wordpress_display_name' => ['label' => 'WordPress Display Name', 'source_path' => 'wp.display_name', 'type' => 'string'],
				'wordpress_first_name' => ['label' => 'WordPress First Name', 'source_path' => 'wp.first_name', 'type' => 'string'],
				'wordpress_last_name' => ['label' => 'WordPress Last Name', 'source_path' => 'wp.last_name', 'type' => 'string'],
				'aac_external_key' => ['label' => 'AAC External Key', 'source_path' => 'meta.aac_external_key', 'type' => 'string'],
				'account_first_name' => ['label' => 'AAC Account First Name', 'source_path' => 'account_info.first_name', 'type' => 'string'],
				'account_last_name' => ['label' => 'AAC Account Last Name', 'source_path' => 'account_info.last_name', 'type' => 'string'],
				'account_name' => ['label' => 'AAC Account Full Name', 'source_path' => 'account_info.name', 'type' => 'string'],
				'account_email' => ['label' => 'AAC Account Email', 'source_path' => 'account_info.email', 'type' => 'email'],
				'account_photo_url' => ['label' => 'Profile Photo URL', 'source_path' => 'account_info.photo_url', 'type' => 'url'],
				'account_phone' => ['label' => 'Phone', 'source_path' => 'account_info.phone', 'type' => 'string'],
				'account_phone_type' => ['label' => 'Phone Type', 'source_path' => 'account_info.phone_type', 'type' => 'string'],
				'account_street' => ['label' => 'Street Address', 'source_path' => 'account_info.street', 'type' => 'string'],
				'account_address2' => ['label' => 'Address Line 2', 'source_path' => 'account_info.address2', 'type' => 'string'],
				'account_city' => ['label' => 'City', 'source_path' => 'account_info.city', 'type' => 'string'],
				'account_state' => ['label' => 'State / Province', 'source_path' => 'account_info.state', 'type' => 'string'],
				'account_zip' => ['label' => 'Postal Code', 'source_path' => 'account_info.zip', 'type' => 'string'],
				'account_country' => ['label' => 'Country', 'source_path' => 'account_info.country', 'type' => 'string'],
				'account_tshirt_size' => ['label' => 'T-Shirt Size', 'source_path' => 'account_info.size', 'type' => 'string'],
				'account_publication_pref' => ['label' => 'Legacy Publication Preference', 'source_path' => 'account_info.publication_pref', 'type' => 'string'],
				'account_aaj_pref' => ['label' => 'AAJ Preference', 'source_path' => 'account_info.aaj_pref', 'type' => 'string'],
				'account_anac_pref' => ['label' => 'ANAC Preference', 'source_path' => 'account_info.anac_pref', 'type' => 'string'],
				'account_acj_pref' => ['label' => 'ACJ Preference', 'source_path' => 'account_info.acj_pref', 'type' => 'string'],
				'account_guidebook_pref' => ['label' => 'Guidebook Preference', 'source_path' => 'account_info.guidebook_pref', 'type' => 'string'],
				'account_magazine_subscriptions' => ['label' => 'Magazine Subscriptions', 'source_path' => 'account_info.magazine_subscriptions', 'type' => 'array'],
				'account_membership_discount_type' => ['label' => 'Membership Discount Type', 'source_path' => 'account_info.membership_discount_type', 'type' => 'string'],
				'account_auto_renew' => ['label' => 'Auto Renew', 'source_path' => 'account_info.auto_renew', 'type' => 'boolean'],
				'account_payment_method' => ['label' => 'Payment Method Label', 'source_path' => 'account_info.payment_method', 'type' => 'string'],
				'family_account_role' => ['label' => 'Family Account Role', 'source_path' => 'meta.aac_family_account_role', 'type' => 'string'],
				'linked_parent_user_id' => ['label' => 'Linked Parent User ID', 'source_path' => 'meta.aac_linked_parent_user_id', 'type' => 'integer'],
				'linked_account_slot_id' => ['label' => 'Linked Account Slot ID', 'source_path' => 'meta.aac_linked_account_slot_id', 'type' => 'string'],
				'linked_account_type' => ['label' => 'Linked Account Type', 'source_path' => 'meta.aac_linked_account_type', 'type' => 'string'],
				'linked_account_label' => ['label' => 'Linked Account Label', 'source_path' => 'meta.aac_linked_account_label', 'type' => 'string'],
				'linked_account_invite_code' => ['label' => 'Linked Account Invite Code', 'source_path' => 'meta.aac_linked_account_invite_code', 'type' => 'string'],
				'family_access_until' => ['label' => 'Family Access Until', 'source_path' => 'meta.aac_family_membership_access_until', 'type' => 'date'],
				'family_pending_removal' => ['label' => 'Family Pending Removal', 'source_path' => 'meta.aac_family_membership_pending_removal', 'type' => 'boolean'],
			],
			'membership' => [
				'aac_external_key' => ['label' => 'AAC External Key', 'source_path' => 'meta.aac_external_key', 'type' => 'string'],
				'wordpress_user_id' => ['label' => 'WordPress User ID', 'source_path' => 'wp.ID', 'type' => 'integer'],
				'member_id' => ['label' => 'Member ID', 'source_path' => 'profile_info.member_id', 'type' => 'string'],
				'membership_level' => ['label' => 'Membership Level', 'source_path' => 'profile_info.tier', 'type' => 'string'],
				'membership_status' => ['label' => 'Membership Status', 'source_path' => 'profile_info.status', 'type' => 'string'],
				'renewal_date' => ['label' => 'Renewal Date', 'source_path' => 'profile_info.renewal_date', 'type' => 'date'],
				'expiration_date' => ['label' => 'Expiration Date', 'source_path' => 'profile_info.expiration_date', 'type' => 'date'],
				'joined_date' => ['label' => 'Joined Date', 'source_path' => 'profile_info.joined_date', 'type' => 'date'],
				'auto_renew' => ['label' => 'Auto Renew', 'source_path' => 'account_info.auto_renew', 'type' => 'boolean'],
				'rescue_amount' => ['label' => 'Rescue Benefit Amount', 'source_path' => 'benefits_info.rescue_amount', 'type' => 'integer'],
				'medical_amount' => ['label' => 'Medical Benefit Amount', 'source_path' => 'benefits_info.medical_amount', 'type' => 'integer'],
				'mortal_remains_amount' => ['label' => 'Mortal Remains Amount', 'source_path' => 'benefits_info.mortal_remains_amount', 'type' => 'integer'],
				'rescue_reimbursement_process' => ['label' => 'Rescue Reimbursement Process', 'source_path' => 'benefits_info.rescue_reimbursement_process', 'type' => 'boolean'],
				'pmpro_level_id' => ['label' => 'PMPro Level ID', 'source_path' => 'membership_actions.current_level_id', 'type' => 'integer'],
				'family_account_role' => ['label' => 'Family Account Role', 'source_path' => 'meta.aac_family_account_role', 'type' => 'string'],
				'partner_family_mode' => ['label' => 'Partner Family Mode', 'source_path' => 'meta.aac_partner_family_mode', 'type' => 'string'],
				'partner_family_additional_adult' => ['label' => 'Partner Family Additional Adult', 'source_path' => 'meta.aac_partner_family_additional_adult', 'type' => 'boolean'],
				'partner_family_dependents' => ['label' => 'Partner Family Dependents', 'source_path' => 'meta.aac_partner_family_dependents', 'type' => 'integer'],
				'tshirt_size' => ['label' => 'T-Shirt Size', 'source_path' => 'meta.aac_tshirt_size', 'type' => 'string'],
				'publication_pref' => ['label' => 'Publication Preference', 'source_path' => 'meta.aac_publication_pref', 'type' => 'string'],
				'aaj_pref' => ['label' => 'AAJ Preference', 'source_path' => 'meta.aac_aaj_pref', 'type' => 'string'],
				'anac_pref' => ['label' => 'ANAC Preference', 'source_path' => 'meta.aac_anac_pref', 'type' => 'string'],
				'acj_pref' => ['label' => 'ACJ Preference', 'source_path' => 'meta.aac_acj_pref', 'type' => 'string'],
				'guidebook_pref' => ['label' => 'Guidebook Preference', 'source_path' => 'meta.aac_guidebook_pref', 'type' => 'string'],
				'magazine_addons' => ['label' => 'Magazine Addons', 'source_path' => 'meta.aac_magazine_addons', 'type' => 'array'],
				'magazine_subscription_labels' => ['label' => 'Magazine Subscription Labels', 'source_path' => 'meta.aac_magazine_subscription_labels', 'type' => 'string'],
				'has_alpinist_subscription' => ['label' => 'Has Alpinist Subscription', 'source_path' => 'meta.aac_has_alpinist_subscription', 'type' => 'boolean'],
				'has_backcountry_subscription' => ['label' => 'Has Backcountry Subscription', 'source_path' => 'meta.aac_has_backcountry_subscription', 'type' => 'boolean'],
				'membership_discount_type' => ['label' => 'Membership Discount Type', 'source_path' => 'meta.aac_membership_discount_type', 'type' => 'string'],
				'salesforce_membership_id' => ['label' => 'Salesforce Membership ID', 'source_path' => 'meta.aac_sf_membership_id', 'type' => 'string'],
			],
			'transaction' => [
				'pmpro_order_id' => ['label' => 'PMPro Order ID', 'source_path' => 'transaction.id', 'type' => 'integer'],
				'wordpress_user_id' => ['label' => 'WordPress User ID', 'source_path' => 'wp.ID', 'type' => 'integer'],
				'aac_external_key' => ['label' => 'AAC External Key', 'source_path' => 'meta.aac_external_key', 'type' => 'string'],
				'amount' => ['label' => 'Order Total', 'source_path' => 'transaction.total', 'type' => 'decimal'],
				'status' => ['label' => 'Order Status', 'source_path' => 'transaction.status', 'type' => 'string'],
				'gateway' => ['label' => 'Gateway', 'source_path' => 'transaction.gateway', 'type' => 'string'],
				'transaction_date' => ['label' => 'Transaction Timestamp', 'source_path' => 'transaction.timestamp', 'type' => 'datetime'],
				'pmpro_level_id' => ['label' => 'PMPro Level ID', 'source_path' => 'transaction.membership_id', 'type' => 'integer'],
				'code' => ['label' => 'Order Code', 'source_path' => 'transaction.code', 'type' => 'string'],
				'payment_transaction_id' => ['label' => 'Payment Transaction ID', 'source_path' => 'transaction.payment_transaction_id', 'type' => 'string'],
				'subscription_transaction_id' => ['label' => 'Subscription Transaction ID', 'source_path' => 'transaction.subscription_transaction_id', 'type' => 'string'],
			],
		];
	}

	public static function get_default_field_mappings() {
		return [
			'contact' => [
				'wordpress_user_id' => 'WordPress_User_ID__c',
				'aac_external_key' => 'AAC_External_Key__c',
				'account_first_name' => 'FirstName',
				'account_last_name' => 'LastName',
				'account_email' => 'Email',
				'account_phone' => 'Phone',
				'account_street' => 'MailingStreet',
				'account_city' => 'MailingCity',
				'account_state' => 'MailingState',
				'account_zip' => 'MailingPostalCode',
				'account_country' => 'MailingCountry',
				'family_account_role' => 'AAC_Family_Account_Role__c',
			],
			'membership' => [
				'aac_external_key' => 'AAC_External_Key__c',
				'wordpress_user_id' => 'WordPress_User_ID__c',
				'member_id' => 'AAC_Member_ID__c',
				'membership_level' => 'Membership_Level__c',
				'membership_status' => 'Status__c',
				'renewal_date' => 'Renewal_Date__c',
				'expiration_date' => 'Expiration_Date__c',
				'auto_renew' => 'Auto_Renew__c',
				'rescue_amount' => 'Rescue_Benefit_Amount__c',
				'medical_amount' => 'Medical_Benefit_Amount__c',
				'mortal_remains_amount' => 'Mortal_Remains_Amount__c',
				'rescue_reimbursement_process' => 'Rescue_Reimbursement_Process__c',
				'pmpro_level_id' => 'PMPro_Level_ID__c',
				'family_account_role' => 'Family_Account_Role__c',
			],
			'transaction' => [
				'pmpro_order_id' => 'PMPro_Order_ID__c',
				'wordpress_user_id' => 'WordPress_User_ID__c',
				'aac_external_key' => 'AAC_External_Key__c',
				'amount' => 'Amount__c',
				'status' => 'Status__c',
				'gateway' => 'Gateway__c',
				'transaction_date' => 'Transaction_Date__c',
				'pmpro_level_id' => 'PMPro_Level_ID__c',
				'code' => 'Code__c',
				'payment_transaction_id' => 'Payment_Transaction_ID__c',
				'subscription_transaction_id' => 'Subscription_Transaction_ID__c',
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
		$settings['field_mappings'] = self::merge($defaults, isset($settings['field_mappings']) && is_array($settings['field_mappings']) ? $settings['field_mappings'] : []);
		foreach ($defaults as $group => $fields) {
			$group_input = isset($field_mappings[$group]) && is_array($field_mappings[$group]) ? $field_mappings[$group] : [];
			foreach ($fields as $field_key => $default_field_api_name) {
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
}
