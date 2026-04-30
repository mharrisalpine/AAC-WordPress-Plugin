<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
	define('ABSPATH', __DIR__ . '/../wordpress/');
}

if (!function_exists('sanitize_key')) {
	function sanitize_key($key) {
		$key = strtolower((string) $key);
		$key = str_replace(['-', ' ', '.'], '_', $key);
		return preg_replace('/[^a-z0-9_]/', '', $key);
	}
}

if (!function_exists('sanitize_text_field')) {
	function sanitize_text_field($value) {
		return is_scalar($value) ? trim((string) $value) : '';
	}
}

if (!function_exists('sanitize_email')) {
	function sanitize_email($value) {
		return trim((string) $value);
	}
}

if (!function_exists('absint')) {
	function absint($value) {
		return abs((int) $value);
	}
}

if (!function_exists('get_option')) {
	function get_option($key, $default = false) {
		return $default;
	}
}

if (!function_exists('update_option')) {
	function update_option($key, $value) {
		return true;
	}
}

if (!function_exists('delete_option')) {
	function delete_option($key) {
		return true;
	}
}

require_once __DIR__ . '/../wordpress/aac-salesforce-sync/includes/class-aac-salesforce-sync-settings.php';

$default_settings = AAC_Salesforce_Sync_Settings::get_defaults();
$group_labels = AAC_Salesforce_Sync_Settings::get_group_labels();
$object_definitions = AAC_Salesforce_Sync_Settings::get_object_definitions($default_settings);
$field_definitions = AAC_Salesforce_Sync_Settings::get_field_definitions();
$default_mappings = AAC_Salesforce_Sync_Settings::get_default_field_mappings();

$docs_dir = __DIR__ . '/../docs';
if (!is_dir($docs_dir)) {
	mkdir($docs_dir, 0775, true);
}

$dictionary_path = $docs_dir . '/member-database-salesforce-data-dictionary.csv';
$relationships_path = $docs_dir . '/member-database-salesforce-object-relationships.csv';

/**
 * @return array<string, string>
 */
function infer_storage_details(string $group, string $source_path): array {
	$details = [
		'wordpress_domain' => 'unknown',
		'member_db_storage' => '',
		'member_db_table_or_source' => '',
		'member_db_admin_tab' => '',
		'wordpress_direction' => 'WordPress -> Salesforce',
		'salesforce_to_wordpress_supported' => 'No',
		'salesforce_to_wordpress_route' => '',
		'reverse_sync_notes' => '',
	];

	if (str_starts_with($source_path, 'member_db.profile_row.')) {
		$details['wordpress_domain'] = 'member_db_profile_row';
		$details['member_db_storage'] = 'Mirror table column';
		$details['member_db_table_or_source'] = 'wp_aac_member_db_profiles';
		$details['member_db_admin_tab'] = 'Profile';
	} elseif (str_starts_with($source_path, 'member_db.profile.')) {
		$details['wordpress_domain'] = 'member_db_profile_payload';
		$details['member_db_storage'] = 'JSON snapshot in raw_profile';
		$details['member_db_table_or_source'] = 'wp_aac_member_db_profiles.raw_profile';
		$details['member_db_admin_tab'] = infer_profile_payload_tab($source_path);
	} elseif (str_starts_with($source_path, 'member_db.membership_record.')) {
		$details['wordpress_domain'] = 'member_db_membership_history';
		$details['member_db_storage'] = 'JSON snapshot in raw_record';
		$details['member_db_table_or_source'] = 'wp_aac_member_db_history.raw_record';
		$details['member_db_admin_tab'] = 'Membership History';
	} elseif (str_starts_with($source_path, 'member_db.subscription_record.')) {
		$details['wordpress_domain'] = 'member_db_subscription_history';
		$details['member_db_storage'] = 'JSON snapshot in raw_record';
		$details['member_db_table_or_source'] = 'wp_aac_member_db_subscriptions.raw_record';
		$details['member_db_admin_tab'] = 'Subscriptions';
	} elseif (str_starts_with($source_path, 'member_db.transaction_row.')) {
		$details['wordpress_domain'] = 'member_db_transaction_row';
		$details['member_db_storage'] = 'Mirror table column';
		$details['member_db_table_or_source'] = 'wp_aac_member_db_transactions';
		$details['member_db_admin_tab'] = 'Transactions';
	} elseif (str_starts_with($source_path, 'member_db.transaction_record.')) {
		$details['wordpress_domain'] = 'member_db_transaction_payload';
		$details['member_db_storage'] = 'JSON snapshot in raw_record';
		$details['member_db_table_or_source'] = 'wp_aac_member_db_transactions.raw_record';
		$details['member_db_admin_tab'] = 'Transactions';
	} elseif (str_starts_with($source_path, 'grant.application.')) {
		$details['wordpress_domain'] = 'grants_review_application';
		$details['member_db_storage'] = 'Reviewer application row';
		$details['member_db_table_or_source'] = 'AAC Grants Review application repository';
		$details['member_db_admin_tab'] = 'Grant Applications';
	} elseif (str_starts_with($source_path, 'grant.fields.')) {
		$details['wordpress_domain'] = 'grants_review_normalized_field';
		$details['member_db_storage'] = 'Normalized submitted field row';
		$details['member_db_table_or_source'] = 'AAC Grants Review normalized fields';
		$details['member_db_admin_tab'] = 'Grant Applications';
	}

	if ($group === 'contact') {
		$details['salesforce_to_wordpress_supported'] = 'Yes';
		$details['salesforce_to_wordpress_route'] = 'POST /wp-json/aac-salesforce-sync/v1/contact';
		$details['reverse_sync_notes'] = 'Inbound member matching uses wordpress_user_id, aac_external_key, or email.';
	} elseif ($group === 'membership') {
		$details['salesforce_to_wordpress_supported'] = 'Yes';
		$details['salesforce_to_wordpress_route'] = 'POST /wp-json/aac-salesforce-sync/v1/membership';
		$details['reverse_sync_notes'] = 'Inbound member matching uses wordpress_user_id, aac_external_key, or email.';
	} elseif ($group === 'grant') {
		$details['reverse_sync_notes'] = 'Grant outbound sync can link the grant object back to Contact via Contact lookup fallback logic.';
	}

	return $details;
}

function infer_profile_payload_tab(string $source_path): string {
	$preference_prefixes = [
		'member_db.profile.account_info.publication_pref',
		'member_db.profile.account_info.aaj_pref',
		'member_db.profile.account_info.anac_pref',
		'member_db.profile.account_info.acj_pref',
		'member_db.profile.account_info.guidebook_pref',
		'member_db.profile.account_info.membership_discount_type',
		'member_db.profile.account_info.auto_renew',
		'member_db.profile.account_info.payment_method',
		'member_db.profile.account_info.email_opt_out',
		'member_db.profile.account_info.do_not_call',
		'member_db.profile.account_info.do_not_contact',
		'member_db.profile.account_info.size',
		'member_db.profile.benefits_info.',
		'member_db.profile.family_membership.',
		'member_db.profile.linked_parent_account.',
	];

	foreach ($preference_prefixes as $prefix) {
		if (str_starts_with($source_path, $prefix)) {
			return 'Preferences';
		}
	}

	return 'Profile';
}

/**
 * @return array<string, string>
 */
function mapping_for_field(string $group, string $field_key, array $default_mappings): array {
	$mapping = $default_mappings[$group][$field_key] ?? ['object' => '', 'field' => ''];
	if (is_string($mapping)) {
		return [
			'default_object_key' => $mapping !== '' ? $group : '',
			'default_salesforce_field' => $mapping,
		];
	}

	return [
		'default_object_key' => sanitize_key((string) ($mapping['object'] ?? '')),
		'default_salesforce_field' => sanitize_text_field((string) ($mapping['field'] ?? '')),
	];
}

function object_definition_for_group(string $group, string $object_key, array $object_definitions): array {
	$group_objects = $object_definitions[$group] ?? [];
	if ($object_key !== '' && isset($group_objects[$object_key])) {
		return $group_objects[$object_key];
	}

	$default = reset($group_objects);
	return is_array($default) ? $default : [];
}

function object_key_for_group(string $group, string $object_key, array $object_definitions): string {
	$group_objects = $object_definitions[$group] ?? [];
	if ($object_key !== '' && isset($group_objects[$object_key])) {
		return $object_key;
	}

	$first_key = array_key_first($group_objects);
	return is_string($first_key) ? $first_key : '';
}

function write_csv_row($handle, array $row): void {
	fputcsv($handle, $row, ',', '"', '\\');
}

$dictionary_handle = fopen($dictionary_path, 'wb');
write_csv_row($dictionary_handle, [
	'salesforce_sync_group',
	'group_label',
	'wordpress_field_key',
	'wordpress_label',
	'wordpress_source_path',
	'wordpress_type',
	'wordpress_domain',
	'member_db_storage',
	'member_db_table_or_source',
	'member_db_admin_tab',
	'default_salesforce_object_key',
	'default_salesforce_object_label',
	'default_salesforce_object_api_name',
	'default_salesforce_external_id_field',
	'default_salesforce_field_api_name',
	'wordpress_to_salesforce_direction',
	'salesforce_to_wordpress_supported',
	'salesforce_to_wordpress_route',
	'reverse_sync_notes',
	'implementation_notes',
]);

foreach ($field_definitions as $group => $definitions) {
		ksort($definitions);
	foreach ($definitions as $field_key => $definition) {
		$mapping = mapping_for_field($group, $field_key, $default_mappings);
		$resolved_object_key = object_key_for_group($group, $mapping['default_object_key'], $object_definitions);
		$object_definition = object_definition_for_group($group, $resolved_object_key, $object_definitions);
		$storage = infer_storage_details($group, (string) ($definition['source_path'] ?? ''));

		$notes = [];
		if ($mapping['default_salesforce_field'] === '') {
			$notes[] = 'No default Salesforce field mapping; available in connector for optional mapping.';
		}
		if ($group === 'membership' && str_contains((string) ($definition['source_path'] ?? ''), 'membership_record.')) {
			$notes[] = 'Membership Term sync also uses the two newest mirrored history rows to populate Previous_Term__c and Superseded_By__c.';
		}
		if ($group === 'grant' && str_starts_with((string) ($definition['source_path'] ?? ''), 'grant.fields.')) {
			$notes[] = 'Grant builder field; appears when configured in AAC Portal > Grants.';
		}
		if ($field_key === 'member_db_profile_account_info_auto_renew') {
			$notes[] = 'Mapped by default to both Contact and Membership Term groups in the connector.';
		}

		write_csv_row($dictionary_handle, [
			$group,
			$group_labels[$group] ?? $group,
			$field_key,
			$definition['label'] ?? '',
			$definition['source_path'] ?? '',
			$definition['type'] ?? '',
			$storage['wordpress_domain'],
			$storage['member_db_storage'],
			$storage['member_db_table_or_source'],
			$storage['member_db_admin_tab'],
			$object_definition['key'] ?? $resolved_object_key,
			$object_definition['label'] ?? '',
			$object_definition['object_name'] ?? '',
			$object_definition['external_id_field'] ?? '',
			$mapping['default_salesforce_field'],
			$storage['wordpress_direction'],
			$storage['salesforce_to_wordpress_supported'],
			$storage['salesforce_to_wordpress_route'],
			$storage['reverse_sync_notes'],
			implode(' ', $notes),
		]);
	}
}

fclose($dictionary_handle);

$relationships = [
	[
		'source_system' => 'WordPress Member Database',
		'source_entity' => 'Profile snapshot (wp_aac_member_db_profiles)',
		'source_key_or_field' => 'member_db.profile_row.user_id',
		'target_system' => 'Salesforce',
		'target_object' => 'Contact',
		'target_field_or_relationship' => 'WordPress_User_ID__c',
		'link_or_sync_type' => 'Outbound external ID upsert',
		'notes' => 'Default Contact sync object and default external ID field.',
	],
	[
		'source_system' => 'Salesforce',
		'source_entity' => 'Contact inbound webhook',
		'source_key_or_field' => 'wordpress_user_id / aac_external_key / email',
		'target_system' => 'WordPress Member Database',
		'target_object' => 'Member profile snapshot',
		'target_field_or_relationship' => 'POST /wp-json/aac-salesforce-sync/v1/contact',
		'link_or_sync_type' => 'Inbound update',
		'notes' => 'Used for Salesforce -> WordPress member contact updates.',
	],
	[
		'source_system' => 'WordPress Membership History',
		'source_entity' => 'wp_aac_member_db_history + profile snapshot',
		'source_key_or_field' => 'user_id / member_id / membership term fields',
		'target_system' => 'Salesforce',
		'target_object' => 'Membership_Term__c',
		'target_field_or_relationship' => 'AAC_External_Key__c',
		'link_or_sync_type' => 'Outbound external ID upsert',
		'notes' => 'Membership sync uses the newest mirrored terms and current profile snapshot.',
	],
	[
		'source_system' => 'Salesforce',
		'source_entity' => 'Membership inbound webhook',
		'source_key_or_field' => 'wordpress_user_id / aac_external_key / email',
		'target_system' => 'WordPress Member Database',
		'target_object' => 'Member profile snapshot',
		'target_field_or_relationship' => 'POST /wp-json/aac-salesforce-sync/v1/membership',
		'link_or_sync_type' => 'Inbound update',
		'notes' => 'Used for Salesforce -> WordPress membership updates.',
	],
	[
		'source_system' => 'Salesforce',
		'source_entity' => 'Membership_Term__c',
		'source_key_or_field' => 'Contact__c / ContactId',
		'target_system' => 'Salesforce',
		'target_object' => 'Contact',
		'target_field_or_relationship' => 'Lookup relationship',
		'link_or_sync_type' => 'Object relationship',
		'notes' => 'Worker resolves the Contact lookup using the configured contact external ID field and fallbacks such as AAC member ID, WordPress user ID, or email.',
	],
	[
		'source_system' => 'Salesforce',
		'source_entity' => 'Membership_Term__c',
		'source_key_or_field' => 'Previous_Term__c',
		'target_system' => 'Salesforce',
		'target_object' => 'Membership_Term__c',
		'target_field_or_relationship' => 'Self lookup to prior term',
		'link_or_sync_type' => 'Object relationship',
		'notes' => 'Connector patches the newest term to point at the next most recent term after upsert.',
	],
	[
		'source_system' => 'Salesforce',
		'source_entity' => 'Membership_Term__c',
		'source_key_or_field' => 'Superseded_By__c / Superceded_By__c',
		'target_system' => 'Salesforce',
		'target_object' => 'Membership_Term__c',
		'target_field_or_relationship' => 'Self lookup to newer term',
		'link_or_sync_type' => 'Object relationship',
		'notes' => 'Connector patches the previous term to point forward to the newest term after upsert.',
	],
	[
		'source_system' => 'WordPress Transactions',
		'source_entity' => 'wp_aac_member_db_transactions',
		'source_key_or_field' => 'member_db.transaction_record.id',
		'target_system' => 'Salesforce',
		'target_object' => 'Payment_Transaction__c',
		'target_field_or_relationship' => 'PMPro_Order_ID__c',
		'link_or_sync_type' => 'Outbound external ID upsert',
		'notes' => 'Transaction rows are outbound-only in the current connector.',
	],
	[
		'source_system' => 'WordPress Grants Review',
		'source_entity' => 'Grant application + normalized fields',
		'source_key_or_field' => 'grant.application.review_application_id',
		'target_system' => 'Salesforce',
		'target_object' => 'Grant_Application__c',
		'target_field_or_relationship' => 'Grant_Review_Application_ID__c',
		'link_or_sync_type' => 'Outbound external ID upsert',
		'notes' => 'Grant sync fields include both core application columns and dynamic grant-builder fields.',
	],
	[
		'source_system' => 'Salesforce',
		'source_entity' => 'Grant_Application__c',
		'source_key_or_field' => 'Contact__c / ContactId',
		'target_system' => 'Salesforce',
		'target_object' => 'Contact',
		'target_field_or_relationship' => 'Lookup relationship',
		'link_or_sync_type' => 'Object relationship',
		'notes' => 'Grant sync can attach the linked Contact using configured contact external ID, plus member ID / user ID / email fallbacks.',
	],
];

$relationships_handle = fopen($relationships_path, 'wb');
write_csv_row($relationships_handle, [
	'source_system',
	'source_entity',
	'source_key_or_field',
	'target_system',
	'target_object',
	'target_field_or_relationship',
	'link_or_sync_type',
	'notes',
]);

foreach ($relationships as $row) {
	write_csv_row($relationships_handle, $row);
}

fclose($relationships_handle);

echo "Created:\n";
echo $dictionary_path . "\n";
echo $relationships_path . "\n";
