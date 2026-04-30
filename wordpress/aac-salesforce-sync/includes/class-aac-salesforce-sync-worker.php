<?php

if (!defined('ABSPATH')) {
	exit;
}

class AAC_Salesforce_Sync_Worker {
	const CRON_HOOK = 'aac_salesforce_sync_process_queue';
	const ASYNC_HOOK = 'aac_salesforce_sync_process_queue_now';

	public function __construct() {
		add_action('init', ['AAC_Salesforce_Sync_Installer', 'maybe_install_schema']);
		add_filter('cron_schedules', [$this, 'register_cron_schedule']);
		add_action('init', [__CLASS__, 'schedule']);
		add_action(self::CRON_HOOK, [$this, 'process_queue']);
		add_action(self::ASYNC_HOOK, [$this, 'process_queue']);

		add_action('profile_update', [$this, 'enqueue_core_profile_update'], 30, 1);
		add_action('personal_options_update', [$this, 'enqueue_core_profile_update'], 100, 1);
		add_action('edit_user_profile_update', [$this, 'enqueue_core_profile_update'], 100, 1);
		add_action('aac_member_portal_member_registered', [$this, 'enqueue_member_registered'], 30, 1);
		add_action('aac_member_portal_profile_updated', [$this, 'enqueue_profile_updated'], 30, 1);
		add_action('pmpro_after_checkout', [$this, 'enqueue_after_checkout'], 40, 2);
		add_action('pmpro_after_change_membership_level', [$this, 'enqueue_after_membership_change'], 40, 2);
		add_action('aac_grants_review_workflow_updated', [$this, 'enqueue_grant_workflow_update'], 30, 4);
		add_action('aac_member_portal_family_account_linked', [$this, 'enqueue_family_account_linked'], 30, 2);
	}

	public static function schedule() {
		if (!wp_next_scheduled(self::CRON_HOOK)) {
			wp_schedule_event(time() + MINUTE_IN_SECONDS, 'aac_salesforce_sync_five_minutes', self::CRON_HOOK);
		}
	}

	public static function kick_queue_runner($delay = 1) {
		$delay = max(0, absint($delay));
		$target_timestamp = time() + $delay;
		$scheduled_timestamp = wp_next_scheduled(self::ASYNC_HOOK);

		if (!$scheduled_timestamp || $scheduled_timestamp > ($target_timestamp + 15)) {
			wp_schedule_single_event($target_timestamp, self::ASYNC_HOOK);
		}

		if (function_exists('spawn_cron')) {
			spawn_cron($target_timestamp);
		}
	}

	public static function deactivate() {
		$timestamp = wp_next_scheduled(self::CRON_HOOK);
		if ($timestamp) {
			wp_unschedule_event($timestamp, self::CRON_HOOK);
		}

		$async_timestamp = wp_next_scheduled(self::ASYNC_HOOK);
		if ($async_timestamp) {
			wp_unschedule_event($async_timestamp, self::ASYNC_HOOK);
		}
	}

	public function register_cron_schedule($schedules) {
		$schedules['aac_salesforce_sync_five_minutes'] = [
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display' => 'Every 5 Minutes (AAC Salesforce Sync)',
		];

		return $schedules;
	}

	public function enqueue_core_profile_update($user_id) {
		$this->enqueue_member_jobs((int) $user_id, 'profile_update');
	}

	public function enqueue_member_registered($user_id) {
		$this->enqueue_member_jobs((int) $user_id, 'member_registered');
	}

	public function enqueue_profile_updated($user_id) {
		$this->enqueue_member_jobs((int) $user_id, 'profile_updated');
	}

	public function enqueue_after_checkout($user_id, $morder = null) {
		$user_id = (int) $user_id;
		$this->enqueue_member_jobs($user_id, 'pmpro_checkout');

		$order_id = 0;
		if (is_object($morder) && !empty($morder->id)) {
			$order_id = (int) $morder->id;
		}

		$this->enqueue_transaction_job($user_id, $order_id, 'pmpro_checkout');
	}

	public function enqueue_after_membership_change($level_id, $user_id = 0) {
		$this->enqueue_membership_job((int) $user_id, 'membership_change', ['level_id' => (int) $level_id]);
	}

	public function enqueue_family_account_linked($parent_user_id, $child_user_id) {
		$parent_user_id = (int) $parent_user_id;
		$child_user_id = (int) $child_user_id;

		if ($parent_user_id > 0) {
			$this->enqueue_member_jobs($parent_user_id, 'family_account_linked_parent');
		}

		if ($child_user_id > 0) {
			$this->enqueue_member_jobs($child_user_id, 'family_account_linked_child');
		}
	}

	public function enqueue_member_jobs($user_id, $source = 'wordpress') {
		$user_id = (int) $user_id;
		if ($user_id <= 0) {
			return;
		}

		if ($this->is_sync_enabled('contact')) {
			AAC_Salesforce_Sync_Queue::enqueue('upsert_contact', 'user', $user_id, (string) $user_id, ['user_id' => $user_id], $source);
		}

		if ($this->is_sync_enabled('membership')) {
			$external_key = $this->get_external_key($user_id);
			AAC_Salesforce_Sync_Queue::enqueue('upsert_membership', 'membership', $user_id, $external_key, ['user_id' => $user_id], $source);
		}
	}

	public function enqueue_membership_job($user_id, $source = 'wordpress', $extra_payload = []) {
		$user_id = (int) $user_id;
		if ($user_id <= 0 || !$this->is_sync_enabled('membership')) {
			return;
		}

		$external_key = $this->get_external_key($user_id);
		AAC_Salesforce_Sync_Queue::enqueue(
			'upsert_membership',
			'membership',
			$user_id,
			$external_key,
			array_merge(['user_id' => $user_id], (array) $extra_payload),
			$source
		);
	}

	public function enqueue_transaction_job($user_id, $order_id = 0, $source = 'wordpress') {
		$user_id = (int) $user_id;
		$order_id = (int) $order_id;

		if ($user_id <= 0 || !$this->is_sync_enabled('transaction')) {
			return;
		}

		$external_key = $order_id > 0 ? (string) $order_id : $this->get_external_key($user_id);
		AAC_Salesforce_Sync_Queue::enqueue(
			'upsert_transaction',
			'transaction',
			$user_id,
			$external_key,
			[
				'user_id' => $user_id,
				'order_id' => $order_id,
			],
			$source
		);
	}

	public function enqueue_grant_workflow_update($application_id, $application = [], $previous_status = '', $new_status = '') {
		$application_id = (int) $application_id;
		$new_status = sanitize_key((string) $new_status);

		if ($application_id <= 0 || !$this->is_sync_enabled('grant')) {
			return;
		}

		if ($new_status === '') {
			return;
		}

		$application = is_array($application) ? $application : [];
		$external_key = (string) $application_id;
		AAC_Salesforce_Sync_Queue::enqueue(
			'upsert_grant',
			'grant',
			$application_id,
			$external_key,
			[
				'application_id' => $application_id,
				'user_id' => absint($application['applicant_user_id'] ?? 0),
				'status_key' => $new_status,
				'previous_status' => sanitize_key((string) $previous_status),
			],
			'grant_workflow_' . $new_status
		);
	}

	public function process_queue($limit = null, $force = false) {
		$settings = AAC_Salesforce_Sync_Settings::get_settings();
		if (empty($settings['general']['enabled'])) {
			return 0;
		}

		$allowed_job_types = $this->get_enabled_job_types($settings);
		if (empty($allowed_job_types)) {
			return 0;
		}

		AAC_Salesforce_Sync_Queue::reset_stale_locks();
		$client = new AAC_Salesforce_Sync_Salesforce_Client();
		$batch_size = $limit ? absint($limit) : (int) $settings['general']['batch_size'];
		$processed = 0;

		for ($i = 0; $i < $batch_size; $i++) {
			$job = AAC_Salesforce_Sync_Queue::claim_next($force, $allowed_job_types);
			if (!$job) {
				break;
			}

			try {
				$this->process_job($job, $client, $settings);
				AAC_Salesforce_Sync_Queue::complete($job['id']);
				$processed++;
			} catch (Exception $exception) {
				AAC_Salesforce_Sync_Queue::release_with_retry($job, $exception->getMessage());
			}
		}

		return $processed;
	}

	private function is_sync_enabled($group, $settings = null) {
		$settings = is_array($settings) ? $settings : AAC_Salesforce_Sync_Settings::get_settings();
		$key_map = [
			'contact' => 'sync_contact',
			'membership' => 'sync_membership',
			'transaction' => 'sync_transaction',
			'grant' => 'sync_grant',
		];
		$key = $key_map[$group] ?? '';
		return $key !== '' && !empty($settings['general'][$key]);
	}

	private function get_enabled_job_types($settings = null) {
		$settings = is_array($settings) ? $settings : AAC_Salesforce_Sync_Settings::get_settings();
		$allowed = [];
		if ($this->is_sync_enabled('contact', $settings)) {
			$allowed[] = 'upsert_contact';
		}
		if ($this->is_sync_enabled('membership', $settings)) {
			$allowed[] = 'upsert_membership';
		}
		if ($this->is_sync_enabled('transaction', $settings)) {
			$allowed[] = 'upsert_transaction';
		}
		if ($this->is_sync_enabled('grant', $settings)) {
			$allowed[] = 'upsert_grant';
		}
		return $allowed;
	}

	public function sync_contact_from_salesforce($payload) {
		$user = $this->find_user_for_inbound_payload($payload);
		if (!$user instanceof WP_User) {
			throw new RuntimeException('Could not locate WordPress user for inbound Salesforce contact payload.');
		}

		$account_info = get_user_meta($user->ID, 'aac_account_info', true);
		$account_info = is_array($account_info) ? $account_info : [];

		$updates = [];
		if (!empty($payload['first_name'])) {
			$account_info['first_name'] = sanitize_text_field($payload['first_name']);
			$updates['first_name'] = sanitize_text_field($payload['first_name']);
		}
		if (!empty($payload['last_name'])) {
			$account_info['last_name'] = sanitize_text_field($payload['last_name']);
			$updates['last_name'] = sanitize_text_field($payload['last_name']);
		}
		if (!empty($payload['email'])) {
			$account_info['email'] = sanitize_email($payload['email']);
			$updates['user_email'] = sanitize_email($payload['email']);
		}
		if (isset($payload['phone'])) {
			$account_info['phone'] = sanitize_text_field((string) $payload['phone']);
		}
		if (isset($payload['street'])) {
			$account_info['street'] = sanitize_text_field((string) $payload['street']);
		}
		if (isset($payload['city'])) {
			$account_info['city'] = sanitize_text_field((string) $payload['city']);
		}
		if (isset($payload['state'])) {
			$account_info['state'] = sanitize_text_field((string) $payload['state']);
		}
		if (isset($payload['postal_code'])) {
			$account_info['zip'] = sanitize_text_field((string) $payload['postal_code']);
		}
		if (isset($payload['country'])) {
			$account_info['country'] = sanitize_text_field((string) $payload['country']);
		}

		if (!empty($updates)) {
			$updates['ID'] = $user->ID;
			wp_update_user($updates);
		}

		update_user_meta($user->ID, 'aac_account_info', $account_info);
		if (!empty($payload['salesforce_contact_id'])) {
			update_user_meta($user->ID, 'aac_sf_contact_id', sanitize_text_field($payload['salesforce_contact_id']));
		}

		return $user->ID;
	}

	public function sync_membership_from_salesforce($payload) {
		$user = $this->find_user_for_inbound_payload($payload);
		if (!$user instanceof WP_User) {
			throw new RuntimeException('Could not locate WordPress user for inbound Salesforce membership payload.');
		}

		$profile_info = get_user_meta($user->ID, 'aac_profile_info', true);
		$benefits_info = get_user_meta($user->ID, 'aac_benefits_info', true);
		$account_info = get_user_meta($user->ID, 'aac_account_info', true);
		$profile_info = is_array($profile_info) ? $profile_info : [];
		$benefits_info = is_array($benefits_info) ? $benefits_info : [];
		$account_info = is_array($account_info) ? $account_info : [];

		$profile_map = [
			'member_id' => 'member_id',
			'membership_level' => 'tier',
			'status' => 'status',
			'renewal_date' => 'renewal_date',
			'expiration_date' => 'expiration_date',
		];

		foreach ($profile_map as $source => $target) {
			if (array_key_exists($source, $payload)) {
				$profile_info[$target] = sanitize_text_field((string) $payload[$source]);
			}
		}

		if (array_key_exists('auto_renew', $payload)) {
			$account_info['auto_renew'] = !empty($payload['auto_renew']);
		}

		$benefit_map = [
			'rescue_amount' => 'rescue_amount',
			'medical_amount' => 'medical_amount',
			'mortal_remains_amount' => 'mortal_remains_amount',
		];

		foreach ($benefit_map as $source => $target) {
			if (array_key_exists($source, $payload)) {
				$benefits_info[$target] = (float) $payload[$source];
			}
		}

		if (array_key_exists('rescue_reimbursement_process', $payload)) {
			$benefits_info['rescue_reimbursement_process'] = !empty($payload['rescue_reimbursement_process']);
		}

		update_user_meta($user->ID, 'aac_profile_info', $profile_info);
		update_user_meta($user->ID, 'aac_benefits_info', $benefits_info);
		update_user_meta($user->ID, 'aac_account_info', $account_info);

		if (!empty($payload['salesforce_membership_id'])) {
			update_user_meta($user->ID, 'aac_sf_membership_id', sanitize_text_field($payload['salesforce_membership_id']));
		}

		if (function_exists('pmpro_changeMembershipLevel') && array_key_exists('pmpro_level_id', $payload)) {
			$pmpro_level_id = absint($payload['pmpro_level_id']);
			pmpro_changeMembershipLevel($pmpro_level_id, $user->ID);
		}

		return $user->ID;
	}

	private function process_job($job, AAC_Salesforce_Sync_Salesforce_Client $client, $settings) {
		$payload = json_decode((string) $job['payload'], true);
		$payload = is_array($payload) ? $payload : [];

		switch ($job['job_type']) {
			case 'upsert_contact':
				$profile = $this->get_portal_profile((int) $job['object_id']);
				$context = $this->build_source_context((int) $job['object_id'], $profile);
				$this->sync_mapped_objects_for_group('contact', $context, (string) $job['external_key'], $client, $settings);
				break;

			case 'upsert_membership':
				$profile = $this->get_portal_profile((int) $job['object_id']);
				$this->sync_membership_terms((int) $job['object_id'], $profile, $client, $settings);
				break;

			case 'upsert_transaction':
				$transaction = $this->get_transaction_payload((int) ($payload['user_id'] ?? $job['object_id']), (int) ($payload['order_id'] ?? 0));
				if (!$transaction) {
					throw new RuntimeException('Could not build PMPro transaction payload.');
				}
				$profile = $this->get_portal_profile((int) ($payload['user_id'] ?? $job['object_id']));
				$context = $this->build_source_context((int) ($payload['user_id'] ?? $job['object_id']), $profile, $transaction);
				$external_id = (string) ($transaction['PMPro_Order_ID__c'] ?? $job['external_key']);
				$this->sync_mapped_objects_for_group('transaction', $context, $external_id, $client, $settings);
				break;

			case 'upsert_grant':
				$grant_context = $this->build_grant_source_context((int) ($payload['application_id'] ?? $job['object_id']));
				$external_id = (string) ($grant_context['grant']['application']['review_application_id'] ?? $job['external_key']);
				$this->sync_mapped_objects_for_group('grant', $grant_context, $external_id, $client, $settings);
				break;

			default:
				throw new RuntimeException('Unsupported sync job type: ' . $job['job_type']);
		}
	}

	private function get_portal_profile($user_id) {
		if (class_exists('AAC_Member_Portal_API') && method_exists('AAC_Member_Portal_API', 'get_instance')) {
			$api = AAC_Member_Portal_API::get_instance();
			if ($api && method_exists($api, 'get_profile_for_user')) {
				return (array) $api->get_profile_for_user((int) $user_id);
			}
		}

		$user = get_user_by('id', (int) $user_id);
		if (!$user instanceof WP_User) {
			throw new RuntimeException('User not found for Salesforce sync.');
		}

		return [
			'account_info' => (array) get_user_meta($user_id, 'aac_account_info', true),
			'profile_info' => (array) get_user_meta($user_id, 'aac_profile_info', true),
			'benefits_info' => (array) get_user_meta($user_id, 'aac_benefits_info', true),
		];
	}

	private function get_transaction_payload($user_id, $order_id = 0) {
		global $wpdb;

		if (!$wpdb || empty($wpdb->pmpro_membership_orders)) {
			return [];
		}

		if ($order_id > 0) {
			$order = $wpdb->get_row(
				$wpdb->prepare("SELECT * FROM {$wpdb->pmpro_membership_orders} WHERE id = %d LIMIT 1", $order_id),
				ARRAY_A
			); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		} else {
			$order = $wpdb->get_row(
				$wpdb->prepare("SELECT * FROM {$wpdb->pmpro_membership_orders} WHERE user_id = %d ORDER BY id DESC LIMIT 1", $user_id),
				ARRAY_A
			); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		if (!is_array($order)) {
			return [];
		}

		$profile = $this->get_portal_profile((int) $user_id);
		$context = $this->build_source_context((int) $user_id, $profile, $order);
		$payloads = $this->build_salesforce_payloads_from_mapping('transaction', $context);

		return isset($payloads['transaction']) && is_array($payloads['transaction']) ? $payloads['transaction'] : [];
	}

	private function sync_mapped_objects_for_group($group, $context, $external_key, AAC_Salesforce_Sync_Salesforce_Client $client, $settings) {
		$payloads = $this->build_salesforce_payloads_from_mapping($group, $context);
		$object_definitions = AAC_Salesforce_Sync_Settings::get_object_definitions($settings);
		$field_catalog = AAC_Salesforce_Sync_Settings::get_field_catalog();
		$group_objects = isset($object_definitions[$group]) && is_array($object_definitions[$group]) ? $object_definitions[$group] : [];

		foreach ($payloads as $object_key => $payload) {
			if (!is_array($payload) || !$payload) {
				continue;
			}

			$object_definition = isset($group_objects[$object_key]) && is_array($group_objects[$object_key]) ? $group_objects[$object_key] : [];
			$object_name = trim((string) ($object_definition['object_name'] ?? ''));
			$external_id_field = trim((string) ($object_definition['external_id_field'] ?? ''));

			if ($object_name === '' || $external_id_field === '') {
				continue;
			}

			$payload = $this->sanitize_reference_field_payloads($payload, $object_key, $field_catalog);

			if ($group === 'contact') {
				$payload = $this->attach_contact_household_account_to_payload($payload, $object_key, $context, $settings, $field_catalog, $client);
			} elseif ($group === 'grant') {
				$payload = $this->attach_grant_contact_lookup_to_payload($payload, $object_key, $context, $settings, $field_catalog, $client);
			} elseif ($group === 'membership') {
				$payload = $this->attach_membership_contact_lookup_to_payload($payload, $object_key, $context, $settings, $field_catalog, $client);
			}

			$payload = $this->sanitize_reference_field_payloads($payload, $object_key, $field_catalog);

			$client->upsert(
				$object_name,
				$external_id_field,
				(string) $external_key,
				$payload
			);
		}
	}

	private function sync_membership_terms($user_id, $profile, AAC_Salesforce_Sync_Salesforce_Client $client, $settings) {
		$user_id = (int) $user_id;
		if ($user_id <= 0) {
			return;
		}

		$base_context = $this->build_source_context($user_id, $profile);
		$history_entries = $this->get_recent_membership_history_entries($user_id, 2);
		if (!$history_entries) {
			$this->sync_mapped_objects_for_group('membership', $base_context, $this->get_external_key($user_id), $client, $settings);
			return;
		}

		$object_definitions = AAC_Salesforce_Sync_Settings::get_object_definitions($settings);
		$membership_definition = isset($object_definitions['membership']['membership']) && is_array($object_definitions['membership']['membership'])
			? $object_definitions['membership']['membership']
			: [];
		$membership_object_name = trim((string) ($membership_definition['object_name'] ?? ''));
		$membership_external_id_field = trim((string) ($membership_definition['external_id_field'] ?? ''));
		$field_catalog = AAC_Salesforce_Sync_Settings::get_field_catalog();

		$linked_terms = [];
		foreach ($history_entries as $index => $history_entry) {
			$term_context = $this->build_membership_term_context_from_history($base_context, $history_entry, $index === 0);
			$external_key = $this->get_membership_term_external_key($user_id, $history_entry);
			$this->sync_mapped_objects_for_group('membership', $term_context, $external_key, $client, $settings);

			if ($membership_object_name !== '' && $membership_external_id_field !== '') {
				$record_id = $client->find_record_id_by_field(
					$membership_object_name,
					$membership_external_id_field,
					$external_key
				);
				if ($record_id !== '') {
					$linked_terms[] = [
						'id' => $record_id,
						'external_key' => $external_key,
						'history_entry' => $history_entry,
					];
				}
			}
		}

		$this->update_membership_term_relationships($linked_terms, $membership_object_name, $client, $field_catalog);
	}

	private function build_salesforce_payloads_from_mapping($group, $context) {
		$definitions = AAC_Salesforce_Sync_Settings::get_field_definitions();
		$settings = AAC_Salesforce_Sync_Settings::get_settings();
		$field_catalog = AAC_Salesforce_Sync_Settings::get_field_catalog();
		$group_definitions = isset($definitions[$group]) && is_array($definitions[$group]) ? $definitions[$group] : [];
		$group_mappings = isset($settings['field_mappings'][$group]) && is_array($settings['field_mappings'][$group]) ? $settings['field_mappings'][$group] : [];
		$payloads = [];

		foreach ($group_definitions as $field_key => $definition) {
			$mapping = AAC_Salesforce_Sync_Settings::normalize_mapping_entry($group_mappings[$field_key] ?? [], $group);
			$object_key = trim((string) ($mapping['object'] ?? ''));
			$salesforce_field = trim((string) ($mapping['field'] ?? ''));
			if ($object_key === '') {
				$object_key = $group;
			}
			if ($salesforce_field === '') {
				continue;
			}

			if ($this->is_skipped_salesforce_field($salesforce_field)) {
				continue;
			}

			if (!$this->object_has_salesforce_field($object_key, $salesforce_field, $field_catalog)) {
				continue;
			}

			$value = $this->resolve_context_path($context, (string) ($definition['source_path'] ?? ''));
			$value = $this->apply_salesforce_field_fallbacks($value, $salesforce_field, $context);
			$value = $this->apply_group_field_overrides($group, $salesforce_field, $value, $context);
			if ($value === null || $value === '') {
				continue;
			}

			if (!isset($payloads[$object_key]) || !is_array($payloads[$object_key])) {
				$payloads[$object_key] = [];
			}

			$normalized_value = $this->normalize_salesforce_field_value(
				$value,
				(string) ($definition['type'] ?? 'string'),
				$this->get_salesforce_field_type($object_key, $salesforce_field, $field_catalog),
				$salesforce_field
			);
			$normalized_value = $this->normalize_salesforce_picklist_value($object_key, $salesforce_field, $normalized_value, $field_catalog);
			if ($normalized_value === null || $normalized_value === '') {
				continue;
			}

			$payloads[$object_key][$salesforce_field] = $normalized_value;
		}

		return $payloads;
	}

	private function apply_salesforce_field_fallbacks($value, $salesforce_field, $context) {
		if ($value !== null && $value !== '') {
			return $value;
		}

		$salesforce_field = trim((string) $salesforce_field);
		if ($salesforce_field === 'LastName') {
			$wp_last_name = trim((string) ($context['wp']['last_name'] ?? ''));
			if ($wp_last_name !== '') {
				return $wp_last_name;
			}

			$display_name = trim((string) ($context['wp']['display_name'] ?? ''));
			if ($display_name !== '') {
				$parts = preg_split('/\s+/', $display_name);
				if (!empty($parts)) {
					return end($parts) ?: 'Member';
				}
			}

			return 'Member';
		}

		if ($salesforce_field === 'FirstName') {
			$wp_first_name = trim((string) ($context['wp']['first_name'] ?? ''));
			if ($wp_first_name !== '') {
				return $wp_first_name;
			}

			$display_name = trim((string) ($context['wp']['display_name'] ?? ''));
			if ($display_name !== '') {
				$parts = preg_split('/\s+/', $display_name);
				if (!empty($parts) && !empty($parts[0])) {
					return $parts[0];
				}
			}
		}

		return $value;
	}

	private function apply_group_field_overrides($group, $salesforce_field, $value, $context) {
		$group = sanitize_key((string) $group);
		$salesforce_field = trim((string) $salesforce_field);

		if ($group === 'membership') {
			if ($salesforce_field === 'Family_Membership__c') {
				return $this->is_family_membership_context($context);
			}

			if ($salesforce_field === 'Discount_Group__c' && $this->is_family_membership_context($context)) {
				return 'Family';
			}
		}

		return $value;
	}

	private function is_family_membership_context($context) {
		$family_mode = sanitize_key((string) $this->resolve_context_path($context, 'member_db.profile.family_membership.mode'));
		if ($family_mode === 'family') {
			return true;
		}

		$account_role = strtolower(trim((string) $this->resolve_context_path($context, 'member_db.profile_row.account_role')));
		if (in_array($account_role, ['parent', 'child'], true)) {
			return true;
		}

		$linked_parent_user_id = absint($this->resolve_context_path($context, 'member_db.profile.linked_parent_account.parent_user_id'));
		if ($linked_parent_user_id > 0) {
			return true;
		}

		$membership_level = strtolower(trim((string) $this->resolve_context_path($context, 'member_db.profile_row.membership_level')));
		if (strpos($membership_level, 'family') !== false) {
			return true;
		}

		$additional_adult = $this->resolve_context_path($context, 'member_db.profile.family_membership.additional_adult');
		$dependent_count = (int) $this->resolve_context_path($context, 'member_db.profile.family_membership.dependent_count');

		return !empty($additional_adult) || $dependent_count > 0;
	}

	private function is_primary_account_holder_context($context) {
		$account_role = strtolower(trim((string) $this->resolve_context_path($context, 'member_db.profile_row.account_role')));
		if ($account_role === 'child') {
			return false;
		}

		$linked_parent_user_id = absint($this->resolve_context_path($context, 'member_db.profile.linked_parent_account.parent_user_id'));
		if ($linked_parent_user_id > 0) {
			return false;
		}

		return true;
	}

	private function build_source_context($user_id, $profile, $transaction = []) {
		$user = get_user_by('id', (int) $user_id);
		$member_db = $this->get_member_database_context((int) $user_id, $transaction);
		$live_profile = is_array($profile) ? $profile : [];
		if ($live_profile) {
			$member_db['profile'] = $live_profile;
		}
		$member_db = $this->hydrate_member_database_profile_sync_fields($member_db);

		if ($user instanceof WP_User) {
			$wp = [
				'ID' => (int) $user->ID,
				'user_login' => (string) $user->user_login,
				'user_email' => (string) $user->user_email,
				'display_name' => (string) $user->display_name,
				'first_name' => (string) $user->first_name,
				'last_name' => (string) $user->last_name,
			];
		} else {
			$wp = [
				'ID' => (int) $user_id,
				'user_login' => '',
				'user_email' => '',
				'display_name' => '',
				'first_name' => '',
				'last_name' => '',
			];
		}

		return [
			'wp' => $wp,
			'member_db' => $member_db,
		];
	}

	private function hydrate_member_database_profile_sync_fields($member_db) {
		$member_db = is_array($member_db) ? $member_db : [];
		$profile = isset($member_db['profile']) && is_array($member_db['profile']) ? $member_db['profile'] : [];
		$profile_row = isset($member_db['profile_row']) && is_array($member_db['profile_row']) ? $member_db['profile_row'] : [];
		$profile_info = isset($profile['profile_info']) && is_array($profile['profile_info']) ? $profile['profile_info'] : [];
		$account_info = isset($profile['account_info']) && is_array($profile['account_info']) ? $profile['account_info'] : [];

		$profile['member_since'] = $this->resolve_member_since_date($member_db, $profile_info, $profile_row);
		$profile['current_membership_expiration_date'] = $this->resolve_current_membership_expiration_date($member_db, $profile_info, $profile_row);
		$profile['current_auto_renewal'] = !empty($account_info['auto_renew']);
		$profile['family_plan'] = $this->is_family_membership_context(['member_db' => $member_db]);
		$profile['primary_account_holder'] = $this->is_primary_account_holder_context(['member_db' => $member_db]);

		$member_db['profile'] = $profile;

		return $member_db;
	}

	private function resolve_member_since_date($member_db, $profile_info = [], $profile_row = []) {
		$candidates = [];

		$joined_date = $this->normalize_membership_history_date($profile_info['joined_date'] ?? '');
		if ($joined_date !== '') {
			$candidates[] = $joined_date;
		}

		$row_joined_date = $this->normalize_membership_history_date($profile_row['joined_date'] ?? '');
		if ($row_joined_date !== '') {
			$candidates[] = $row_joined_date;
		}

		$membership_record_start = $this->normalize_membership_history_date($member_db['membership_record']['startdate'] ?? '');
		if ($membership_record_start !== '') {
			$candidates[] = $membership_record_start;
		}

		$membership_row_source_date = $this->normalize_membership_history_date($member_db['membership_row']['source_date'] ?? '');
		if ($membership_row_source_date !== '') {
			$candidates[] = $membership_row_source_date;
		}

		$oldest_history_date = $this->get_oldest_membership_history_start_date((int) ($profile_row['user_id'] ?? 0));
		if ($oldest_history_date !== '') {
			$candidates[] = $oldest_history_date;
		}

		$candidates = array_values(array_filter(array_unique($candidates)));
		if (!$candidates) {
			return '';
		}

		sort($candidates);
		return (string) $candidates[0];
	}

	private function resolve_current_membership_expiration_date($member_db, $profile_info = [], $profile_row = []) {
		$candidates = [
			$this->normalize_membership_history_date($profile_info['expiration_date'] ?? ''),
			$this->normalize_membership_history_date($profile_row['expiration_date'] ?? ''),
			$this->normalize_membership_history_date($member_db['subscription_record']['enddate'] ?? ''),
			$this->normalize_membership_history_date($member_db['membership_record']['enddate'] ?? ''),
			$this->normalize_membership_history_date($member_db['subscription_row']['source_date'] ?? ''),
			$this->normalize_membership_history_date($member_db['membership_row']['source_date'] ?? ''),
		];

		foreach ($candidates as $candidate) {
			if ($candidate !== '') {
				return $candidate;
			}
		}

		return '';
	}

	private function get_oldest_membership_history_start_date($user_id) {
		global $wpdb;

		$user_id = (int) $user_id;
		if ($user_id <= 0 || !$wpdb) {
			return '';
		}

		$membership_table = $wpdb->prefix . 'aac_member_db_membership_history';
		$table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $membership_table)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ($table_exists !== $membership_table) {
			return '';
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare("SELECT raw_record, source_date FROM {$membership_table} WHERE user_id = %d ORDER BY source_record_id ASC, id ASC", $user_id),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if (!is_array($rows) || !$rows) {
			return '';
		}

		$dates = [];
		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}

			$record = json_decode((string) ($row['raw_record'] ?? ''), true);
			if (is_array($record)) {
				$start_date = $this->normalize_membership_history_date($record['startdate'] ?? '');
				if ($start_date !== '') {
					$dates[] = $start_date;
				}
			}

			$source_date = $this->normalize_membership_history_date($row['source_date'] ?? '');
			if ($source_date !== '') {
				$dates[] = $source_date;
			}
		}

		$dates = array_values(array_filter(array_unique($dates)));
		if (!$dates) {
			return '';
		}

		sort($dates);
		return (string) $dates[0];
	}

	private function build_grant_source_context($application_id) {
		$application = $this->get_grant_application($application_id);
		if (!is_array($application) || empty($application['id'])) {
			throw new RuntimeException('Could not load grant application payload.');
		}

		$applicant_user_id = absint($application['applicant_user_id'] ?? 0);
		$base_context = [
			'wp' => [
				'ID' => $applicant_user_id,
				'user_login' => '',
				'user_email' => '',
				'display_name' => '',
				'first_name' => '',
				'last_name' => '',
			],
			'member_db' => [
				'profile_row' => [],
				'profile' => [],
				'membership_row' => [],
				'membership_record' => [],
				'subscription_row' => [],
				'subscription_record' => [],
				'transaction_row' => [],
				'transaction_record' => [],
			],
		];

		if ($applicant_user_id > 0) {
			try {
				$profile = $this->get_portal_profile($applicant_user_id);
				$base_context = $this->build_source_context($applicant_user_id, $profile);
			} catch (Exception $exception) {
				// Grant sync can still run on the application record even if the
				// member profile mirror is missing or the user account disappeared.
			}
		}

		$base_context['grant'] = [
			'application' => $this->normalize_grant_application_context($application, $base_context),
			'fields' => $this->extract_grant_field_values($application),
		];

		return $base_context;
	}

	private function get_grant_application($application_id) {
		$application_id = (int) $application_id;
		if ($application_id <= 0 || !class_exists('AAC_Grants_Review_Settings') || !class_exists('AAC_Grants_Review_Repository')) {
			return [];
		}

		$repository = new AAC_Grants_Review_Repository(new AAC_Grants_Review_Settings());
		return $repository->get_application($application_id);
	}

	private function normalize_grant_application_context($application, $base_context = []) {
		$normalized_fields = is_array($application['normalized_fields'] ?? null) ? $application['normalized_fields'] : [];
		$field_values = $this->extract_grant_field_values($application);
		$aac_member_id = $this->resolve_grant_aac_member_id($application, $base_context);

		return [
			'review_application_id' => absint($application['id'] ?? 0),
			'source' => sanitize_key((string) ($application['source'] ?? '')),
			'source_form_id' => absint($application['source_form_id'] ?? 0),
			'source_entry_id' => absint($application['source_entry_id'] ?? 0),
			'applicant_user_id' => absint($application['applicant_user_id'] ?? 0),
			'aac_member_id' => $aac_member_id,
			'applicant_name' => sanitize_text_field((string) ($application['applicant_name'] ?? '')),
			'applicant_email' => sanitize_email((string) ($application['applicant_email'] ?? '')),
			'grant_name' => sanitize_text_field((string) ($application['grant_name'] ?? '')),
			'grant_slug' => sanitize_title((string) ($field_values['grant_slug'] ?? '')),
			'category' => sanitize_text_field((string) ($field_values['grant_category'] ?? $field_values['category'] ?? '')),
			'project_title' => sanitize_text_field((string) ($application['project_title'] ?? '')),
			'requested_amount' => round((float) ($application['requested_amount'] ?? 0), 2),
			'status' => sanitize_text_field($this->get_grant_status_label((string) ($application['workflow_status'] ?? ''))),
			'status_key' => sanitize_key((string) ($application['workflow_status'] ?? '')),
			'application_date' => sanitize_text_field((string) ($application['submitted_at'] ?? '')),
			'reviewed_at' => sanitize_text_field((string) ($application['reviewed_at'] ?? '')),
			'last_note' => sanitize_textarea_field((string) ($application['last_note'] ?? '')),
			'normalized_fields' => $normalized_fields,
		];
	}

	private function resolve_grant_aac_member_id($application, $base_context = []) {
		$application_member_id = sanitize_text_field((string) ($application['aac_member_id'] ?? ''));
		$application_member_id = $this->normalize_member_id_for_salesforce($application_member_id);
		if ($application_member_id !== '') {
			return $application_member_id;
		}

		$profile_member_id = sanitize_text_field((string) ($base_context['member_db']['profile']['profile_info']['member_id'] ?? ''));
		$profile_member_id = $this->normalize_member_id_for_salesforce($profile_member_id);
		if ($profile_member_id !== '') {
			return $profile_member_id;
		}

		$row_member_id = sanitize_text_field((string) ($base_context['member_db']['profile_row']['member_id'] ?? ''));
		$row_member_id = $this->normalize_member_id_for_salesforce($row_member_id);
		if ($row_member_id !== '') {
			return $row_member_id;
		}

		$applicant_user_id = absint($application['applicant_user_id'] ?? 0);
		if ($applicant_user_id > 0) {
			$user_meta_member_id = sanitize_text_field((string) get_user_meta($applicant_user_id, 'aac_member_id', true));
			$user_meta_member_id = $this->normalize_member_id_for_salesforce($user_meta_member_id);
			if ($user_meta_member_id !== '') {
				return $user_meta_member_id;
			}
		}

		return '';
	}

	private function normalize_member_id_for_salesforce($member_id) {
		$member_id = sanitize_text_field((string) $member_id);
		$member_id = trim($member_id);
		if ($member_id === '') {
			return '';
		}

		$member_id = preg_replace('/^AAC[\s\-_]*/i', '', $member_id);
		$member_id = trim((string) $member_id);

		if ($member_id !== '' && preg_match('/(\d+)/', $member_id, $matches)) {
			return (string) $matches[1];
		}

		return $member_id;
	}

	private function extract_grant_field_values($application) {
		$values = [];
		$fields = is_array($application['normalized_fields'] ?? null) ? $application['normalized_fields'] : [];

		foreach ($fields as $field) {
			if (!is_array($field)) {
				continue;
			}

			$field_key = sanitize_key((string) ($field['field_id'] ?? $field['field_key'] ?? ''));
			if ($field_key === '') {
				continue;
			}

			$values[$field_key] = sanitize_textarea_field((string) ($field['value'] ?? ''));
		}

		return $values;
	}

	private function get_grant_status_label($status_key) {
		$status_key = sanitize_key((string) $status_key);
		if (class_exists('AAC_Grants_Review_Repository')) {
			$labels = AAC_Grants_Review_Repository::get_workflow_labels();
			if (isset($labels[$status_key])) {
				return (string) $labels[$status_key];
			}
		}

		return ucwords(str_replace('_', ' ', $status_key));
	}

	private function get_member_database_context($user_id, $transaction = []) {
		global $wpdb;

		if (!$wpdb) {
			return [
				'profile_row' => [],
				'profile' => [],
				'membership_row' => [],
				'membership_record' => [],
				'subscription_row' => [],
				'subscription_record' => [],
				'transaction_row' => [],
				'transaction_record' => is_array($transaction) ? $transaction : [],
			];
		}

		$profile_table = $wpdb->prefix . 'aac_member_db_profiles';
		$membership_table = $wpdb->prefix . 'aac_member_db_membership_history';
		$subscription_table = $wpdb->prefix . 'aac_member_db_subscriptions';
		$transaction_table = $wpdb->prefix . 'aac_member_db_transactions';

		$profile_row = $wpdb->get_row(
			$wpdb->prepare("SELECT * FROM {$profile_table} WHERE user_id = %d LIMIT 1", $user_id),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$membership_row = $wpdb->get_row(
			$wpdb->prepare("SELECT * FROM {$membership_table} WHERE user_id = %d ORDER BY source_record_id DESC, id DESC LIMIT 1", $user_id),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$subscription_row = $wpdb->get_row(
			$wpdb->prepare("SELECT * FROM {$subscription_table} WHERE user_id = %d ORDER BY source_record_id DESC, id DESC LIMIT 1", $user_id),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$transaction_row = [];
		if (!empty($transaction['id'])) {
			$transaction_row = $wpdb->get_row(
				$wpdb->prepare("SELECT * FROM {$transaction_table} WHERE user_id = %d AND source_record_id = %d LIMIT 1", $user_id, (int) $transaction['id']),
				ARRAY_A
			); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		if (!is_array($transaction_row) || !$transaction_row) {
			$transaction_row = $wpdb->get_row(
				$wpdb->prepare("SELECT * FROM {$transaction_table} WHERE user_id = %d ORDER BY source_record_id DESC, id DESC LIMIT 1", $user_id),
				ARRAY_A
			); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		if (!is_array($profile_row)) {
			$profile = $this->get_portal_profile($user_id);
			$profile = is_array($profile) ? $profile : [];
			return [
				'profile_row' => [
					'user_id' => $user_id,
				],
				'profile' => $profile,
				'membership_row' => [],
				'membership_record' => [],
				'subscription_row' => [],
				'subscription_record' => [],
				'transaction_row' => is_array($transaction_row) ? $this->strip_large_raw_columns($transaction_row) : [],
				'transaction_record' => is_array($transaction) && $transaction ? $transaction : $this->decode_mirror_row($transaction_row),
			];
		}

		$profile = json_decode((string) ($profile_row['raw_profile'] ?? ''), true);
		$profile = is_array($profile) ? $profile : [];
		unset($profile_row['raw_profile']);

		return [
			'profile_row' => $profile_row,
			'profile' => $profile,
			'membership_row' => is_array($membership_row) ? $this->strip_large_raw_columns($membership_row) : [],
			'membership_record' => $this->decode_mirror_row($membership_row),
			'subscription_row' => is_array($subscription_row) ? $this->strip_large_raw_columns($subscription_row) : [],
			'subscription_record' => $this->decode_mirror_row($subscription_row),
			'transaction_row' => is_array($transaction_row) ? $this->strip_large_raw_columns($transaction_row) : [],
			'transaction_record' => is_array($transaction) && $transaction ? $transaction : $this->decode_mirror_row($transaction_row),
		];
	}

	private function get_recent_membership_history_entries($user_id, $limit = 2) {
		global $wpdb;

		$user_id = (int) $user_id;
		$limit = max(1, (int) $limit);
		if ($user_id <= 0 || !$wpdb) {
			return [];
		}

		$membership_table = $wpdb->prefix . 'aac_member_db_membership_history';
		$rows = $wpdb->get_results(
			$wpdb->prepare("SELECT * FROM {$membership_table} WHERE user_id = %d ORDER BY source_record_id DESC, id DESC LIMIT %d", $user_id, $limit),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$entries = [];
		foreach ((array) $rows as $row) {
			if (!is_array($row)) {
				continue;
			}

			$entries[] = [
				'row' => $this->strip_large_raw_columns($row),
				'record' => $this->decode_mirror_row($row),
			];
		}

		return $entries;
	}

	private function build_membership_term_context_from_history($base_context, $history_entry, $is_current = false) {
		$context = is_array($base_context) ? $base_context : [];
		$history_row = is_array($history_entry['row'] ?? null) ? $history_entry['row'] : [];
		$history_record = is_array($history_entry['record'] ?? null) ? $history_entry['record'] : [];

		$member_db = isset($context['member_db']) && is_array($context['member_db']) ? $context['member_db'] : [];
		$profile_row = isset($member_db['profile_row']) && is_array($member_db['profile_row']) ? $member_db['profile_row'] : [];
		$membership_id = absint($history_record['membership_id'] ?? 0);
		$membership_status = sanitize_text_field((string) ($history_record['status'] ?? $history_row['source_status'] ?? ''));
		$start_date = $this->normalize_membership_history_date($history_record['startdate'] ?? $history_row['source_date'] ?? '');
		$end_date = $this->normalize_membership_history_date($history_record['enddate'] ?? '');
		$renewal_date = '';

		if ($is_current) {
			$renewal_date = sanitize_text_field((string) ($profile_row['renewal_date'] ?? ''));
			if ($renewal_date === '') {
				$renewal_date = $this->normalize_membership_history_date($history_record['enddate'] ?? '');
			}
		} else {
			$renewal_date = $this->normalize_membership_history_date($history_record['enddate'] ?? '');
		}

		if ($end_date === '') {
			$end_date = sanitize_text_field((string) ($profile_row['expiration_date'] ?? ''));
		}
		if ($start_date === '') {
			$start_date = sanitize_text_field((string) ($profile_row['renewal_date'] ?? ''));
		}

		$profile_row['membership_level'] = $this->get_pmpro_level_name_by_id($membership_id) ?: sanitize_text_field((string) ($profile_row['membership_level'] ?? ''));
		$profile_row['membership_status'] = $membership_status !== '' ? $membership_status : sanitize_text_field((string) ($profile_row['membership_status'] ?? ''));
		$profile_row['renewal_date'] = $renewal_date;
		$profile_row['expiration_date'] = $end_date;

		$member_db['profile_row'] = $profile_row;
		$member_db['membership_row'] = $history_row;
		$member_db['membership_record'] = $history_record;
		$context['member_db'] = $member_db;

		return $context;
	}

	private function get_membership_term_external_key($user_id, $history_entry) {
		$user_id = (int) $user_id;
		$history_row = is_array($history_entry['row'] ?? null) ? $history_entry['row'] : [];
		$source_record_id = absint($history_row['source_record_id'] ?? 0);
		if ($user_id > 0 && $source_record_id > 0) {
			return sprintf('aac-membership-%d-%d', $user_id, $source_record_id);
		}

		return $this->get_external_key($user_id);
	}

	private function get_first_existing_salesforce_field($object_key, array $candidates, $field_catalog = []) {
		foreach ($candidates as $candidate) {
			$candidate = trim((string) $candidate);
			if ($candidate !== '' && $this->object_has_salesforce_field($object_key, $candidate, $field_catalog)) {
				return $candidate;
			}
		}

		return '';
	}

	private function update_membership_term_relationships($linked_terms, $membership_object_name, AAC_Salesforce_Sync_Salesforce_Client $client, $field_catalog = []) {
		if (!is_array($linked_terms) || !$linked_terms || $membership_object_name === '') {
			return;
		}

		$previous_field = $this->get_first_existing_salesforce_field('membership', ['Previous_Term__c'], $field_catalog);
		$superseded_field = $this->get_first_existing_salesforce_field('membership', ['Superseded_By__c', 'Superceded_By__c'], $field_catalog);
		if ($previous_field === '' && $superseded_field === '') {
			return;
		}

		$current = $linked_terms[0] ?? [];
		$previous = $linked_terms[1] ?? [];
		if (empty($current['id'])) {
			return;
		}

		$current_payload = [];
		$previous_payload = [];

		if ($previous_field !== '') {
			$current_payload[$previous_field] = !empty($previous['id']) ? (string) $previous['id'] : null;
		}
		if ($superseded_field !== '') {
			$current_payload[$superseded_field] = null;
		}

		if (!empty($previous['id'])) {
			if ($superseded_field !== '') {
				$previous_payload[$superseded_field] = (string) $current['id'];
			}
			if ($previous_field !== '') {
				$previous_payload[$previous_field] = null;
			}
		}

		if ($current_payload) {
			$client->update_record($membership_object_name, (string) $current['id'], $current_payload);
		}
		if (!empty($previous['id']) && $previous_payload) {
			$client->update_record($membership_object_name, (string) $previous['id'], $previous_payload);
		}
	}

	private function normalize_membership_history_date($value) {
		$value = trim((string) $value);
		if ($value === '') {
			return '';
		}

		if (preg_match('/^(-?\d{4})-(\d{2})-(\d{2})$/', $value, $matches)) {
			$year = (int) $matches[1];
			$month = (int) $matches[2];
			$day = (int) $matches[3];
			if ($year < 1900 || !checkdate($month, $day, max(1, $year))) {
				return '';
			}

			return sprintf('%04d-%02d-%02d', $year, $month, $day);
		}

		$timestamp = strtotime($value);
		if (!$timestamp) {
			return '';
		}

		$normalized = gmdate('Y-m-d', $timestamp);
		return $this->is_valid_salesforce_calendar_date($normalized) ? $normalized : '';
	}

	private function get_pmpro_level_name_by_id($membership_id) {
		global $wpdb;

		$membership_id = (int) $membership_id;
		if ($membership_id <= 0 || !$wpdb || empty($wpdb->pmpro_membership_levels)) {
			return '';
		}

		$table = $wpdb->pmpro_membership_levels;
		$name = $wpdb->get_var(
			$wpdb->prepare("SELECT name FROM {$table} WHERE id = %d LIMIT 1", $membership_id)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return sanitize_text_field((string) $name);
	}

	private function decode_mirror_row($row) {
		if (!is_array($row)) {
			return [];
		}

		$record = json_decode((string) ($row['raw_record'] ?? ''), true);
		return is_array($record) ? $record : [];
	}

	private function strip_large_raw_columns($row) {
		if (!is_array($row)) {
			return [];
		}

		unset($row['raw_record'], $row['raw_profile']);
		return $row;
	}

	private function resolve_context_path($context, $path) {
		$path = trim((string) $path);
		if ($path === '') {
			return null;
		}

		$segments = explode('.', $path);
		$current = $context;
		foreach ($segments as $segment) {
			if (!is_array($current) || !array_key_exists($segment, $current)) {
				return null;
			}

			$current = $current[$segment];
		}

		return $current;
	}

	private function normalize_salesforce_field_value($value, $type, $salesforce_field_type = '', $salesforce_field = '') {
		$address_value = $this->normalize_salesforce_address_value($value, $salesforce_field);
		if ($address_value !== null) {
			return $address_value;
		}

		$effective_type = $this->resolve_effective_salesforce_type($type, $salesforce_field_type);

		switch ($effective_type) {
			case 'boolean':
				return $this->normalize_salesforce_boolean_value($value);
			case 'integer':
				return (int) $value;
			case 'decimal':
				return (float) $value;
			case 'array':
				return is_array($value) ? implode(', ', array_map('sanitize_text_field', $value)) : sanitize_text_field((string) $value);
			case 'email':
				return sanitize_email((string) $value);
			case 'url':
				return esc_url_raw((string) $value);
			case 'date':
				return $this->normalize_salesforce_date_value($value);
			case 'datetime':
				return $this->normalize_salesforce_datetime_value($value);
			case 'string':
			default:
				return sanitize_text_field((string) $value);
		}
	}

	private function attach_grant_contact_lookup_to_payload($payload, $object_key, $context, $settings, $field_catalog = [], ?AAC_Salesforce_Sync_Salesforce_Client $client = null) {
		if (!is_array($payload) || !$payload) {
			return $payload;
		}

		$lookup = $this->get_grant_contact_lookup_metadata($object_key, $field_catalog);
		if (!$lookup) {
			return $payload;
		}

		$lookup_field = (string) ($lookup['field_name'] ?? '');
		$relationship_name = (string) ($lookup['relationship_name'] ?? '');
		if ($lookup_field === '') {
			return $payload;
		}

		$raw_lookup_value = $payload[$lookup_field] ?? null;
		$raw_relationship_value = $relationship_name !== '' ? ($payload[$relationship_name] ?? null) : null;

		if ($this->is_valid_salesforce_lookup_id($raw_lookup_value) || ($relationship_name !== '' && is_array($raw_relationship_value) && !empty($raw_relationship_value))) {
			return $payload;
		}

		if ($raw_lookup_value !== null && !$this->is_valid_salesforce_lookup_id($raw_lookup_value)) {
			unset($payload[$lookup_field]);
		}

		if ($relationship_name !== '' && $raw_relationship_value !== null && !is_array($raw_relationship_value)) {
			unset($payload[$relationship_name]);
		}

		$applicant_user_id = absint($context['grant']['application']['applicant_user_id'] ?? 0);
		if ($applicant_user_id > 0) {
			$salesforce_contact_id = sanitize_text_field((string) get_user_meta($applicant_user_id, 'aac_sf_contact_id', true));
			if ($this->is_valid_salesforce_lookup_id($salesforce_contact_id)) {
				$payload[$lookup_field] = $salesforce_contact_id;
				return $payload;
			}
		}

		$contact_external_id_field = trim((string) ($settings['salesforce']['contact_external_id_field'] ?? ''));
		$contact_external_id_value = $contact_external_id_field !== ''
			? $this->resolve_grant_contact_external_id_value($contact_external_id_field, $context)
			: '';
		if ($contact_external_id_field !== '' && $contact_external_id_value !== '') {
			$contact_external_id_value = $this->normalize_related_contact_field_value($contact_external_id_field, $contact_external_id_value, $field_catalog);
		}

		$resolved_contact_id = $this->resolve_salesforce_contact_id_from_context(
			$client,
			$settings,
			$context,
			$field_catalog,
			'grant'
		);
		if ($resolved_contact_id !== '') {
			$payload[$lookup_field] = $resolved_contact_id;
			if ($relationship_name !== '') {
				unset($payload[$relationship_name]);
			}
			return $payload;
		}

		if ($relationship_name !== '' && $contact_external_id_field !== '' && $contact_external_id_value !== '') {
			$payload[$relationship_name] = [
				$contact_external_id_field => $contact_external_id_value,
			];
		}

		return $payload;
	}

	private function attach_membership_contact_lookup_to_payload($payload, $object_key, $context, $settings, $field_catalog = [], ?AAC_Salesforce_Sync_Salesforce_Client $client = null) {
		if (!is_array($payload) || !$payload) {
			return $payload;
		}

		$lookup = $this->get_contact_lookup_metadata($object_key, $field_catalog);
		if (!$lookup) {
			return $payload;
		}

		$lookup_field = (string) ($lookup['field_name'] ?? '');
		$relationship_name = (string) ($lookup['relationship_name'] ?? '');
		if ($lookup_field === '') {
			return $payload;
		}

		$raw_lookup_value = $payload[$lookup_field] ?? null;
		$raw_relationship_value = $relationship_name !== '' ? ($payload[$relationship_name] ?? null) : null;

		if ($this->is_valid_salesforce_lookup_id($raw_lookup_value) || ($relationship_name !== '' && is_array($raw_relationship_value) && !empty($raw_relationship_value))) {
			return $payload;
		}

		if ($raw_lookup_value !== null && !$this->is_valid_salesforce_lookup_id($raw_lookup_value)) {
			unset($payload[$lookup_field]);
		}

		if ($relationship_name !== '' && $raw_relationship_value !== null && !is_array($raw_relationship_value)) {
			unset($payload[$relationship_name]);
		}

		$member_id = sanitize_text_field((string) $this->resolve_context_path($context, 'member_db.profile_row.member_id'));
		if ($member_id === '') {
			$member_id = sanitize_text_field((string) $this->resolve_context_path($context, 'member_db.profile.profile_info.member_id'));
		}
		$user_id = absint($context['wp']['ID'] ?? 0);
		if ($user_id > 0) {
			$salesforce_contact_id = sanitize_text_field((string) get_user_meta($user_id, 'aac_sf_contact_id', true));
			if ($this->is_valid_salesforce_lookup_id($salesforce_contact_id)) {
				$payload[$lookup_field] = $salesforce_contact_id;
				if ($relationship_name !== '') {
					unset($payload[$relationship_name]);
				}
				return $payload;
			}
		}

		if ($member_id === '') {
			return $payload;
		}

		$contact_member_id_field = $this->get_contact_member_id_lookup_field($settings);
		if ($contact_member_id_field === '') {
			return $payload;
		}

		$member_id = $this->normalize_related_contact_field_value($contact_member_id_field, $member_id, $field_catalog);
		$resolved_contact_id = $this->resolve_salesforce_contact_id_from_context(
			$client,
			$settings,
			$context,
			$field_catalog,
			'membership'
		);
		if ($resolved_contact_id !== '') {
			$payload[$lookup_field] = $resolved_contact_id;
			if ($relationship_name !== '') {
				unset($payload[$relationship_name]);
			}
			return $payload;
		}

		if ($relationship_name !== '') {
			unset($payload[$relationship_name]);
		}

		return $payload;
	}

	private function attach_contact_household_account_to_payload($payload, $object_key, $context, $settings, $field_catalog = [], ?AAC_Salesforce_Sync_Salesforce_Client $client = null) {
		if (!is_array($payload) || !$payload) {
			return $payload;
		}

		if (!$client instanceof AAC_Salesforce_Sync_Salesforce_Client) {
			return $payload;
		}

		if (!$this->object_has_salesforce_field($object_key, 'AccountId', $field_catalog)) {
			return $payload;
		}

		$existing_account_id = sanitize_text_field((string) ($payload['AccountId'] ?? ''));
		if ($this->is_valid_salesforce_lookup_id($existing_account_id)) {
			return $payload;
		}

		$parent_user_id = absint($this->resolve_context_path($context, 'member_db.profile.linked_parent_account.parent_user_id'));
		if ($parent_user_id <= 0) {
			$parent_user_id = absint($this->resolve_context_path($context, 'member_db.profile_row.parent_user_id'));
		}

		if ($parent_user_id <= 0) {
			return $payload;
		}

		$parent_profile = $this->get_portal_profile($parent_user_id);
		$parent_context = $this->build_source_context($parent_user_id, $parent_profile);
		$parent_contact_id = sanitize_text_field((string) get_user_meta($parent_user_id, 'aac_sf_contact_id', true));

		if (!$this->is_valid_salesforce_lookup_id($parent_contact_id)) {
			$parent_contact_id = $this->resolve_salesforce_contact_id_from_context(
				$client,
				$settings,
				$parent_context,
				$field_catalog,
				'contact'
			);
		}

		if (!$this->is_valid_salesforce_lookup_id($parent_contact_id)) {
			return $payload;
		}

		$contact_object = trim((string) ($settings['salesforce']['contact_object'] ?? 'Contact'));
		if ($contact_object === '') {
			$contact_object = 'Contact';
		}

		try {
			$parent_contact = $client->get_record_by_id($contact_object, $parent_contact_id, ['AccountId']);
		} catch (Exception $exception) {
			return $payload;
		}

		$parent_account_id = sanitize_text_field((string) ($parent_contact['AccountId'] ?? ''));
		if (!$this->is_valid_salesforce_lookup_id($parent_account_id)) {
			return $payload;
		}

		$payload['AccountId'] = $parent_account_id;

		return $payload;
	}

	private function resolve_salesforce_contact_id_by_external_id($client, $settings, $field_name, $field_value, $field_catalog = []) {
		if (!$client instanceof AAC_Salesforce_Sync_Salesforce_Client) {
			return '';
		}

		$contact_object = trim((string) ($settings['salesforce']['contact_object'] ?? 'Contact'));
		$field_name = trim((string) $field_name);
		if ($contact_object === '' || $field_name === '' || $field_value === null || $field_value === '') {
			return '';
		}

		try {
			return $client->find_record_id_by_field(
				$contact_object,
				$field_name,
				$field_value,
				$this->get_salesforce_field_type('contact', $field_name, $field_catalog)
			);
		} catch (Exception $exception) {
			return '';
		}
	}

	private function resolve_salesforce_contact_id_from_context($client, $settings, $context, $field_catalog = [], $mode = 'membership') {
		if (!$client instanceof AAC_Salesforce_Sync_Salesforce_Client) {
			return '';
		}

		foreach ($this->get_contact_lookup_candidates($settings, $context, $field_catalog, $mode) as $candidate) {
			$field_name = trim((string) ($candidate['field'] ?? ''));
			$field_value = $candidate['value'] ?? '';
			if ($field_name === '' || $field_value === '' || $field_value === null) {
				continue;
			}

			$contact_id = $this->resolve_salesforce_contact_id_by_external_id(
				$client,
				$settings,
				$field_name,
				$field_value,
				$field_catalog
			);
			if ($contact_id !== '') {
				return $contact_id;
			}
		}

		return '';
	}

	private function get_contact_lookup_candidates($settings, $context, $field_catalog = [], $mode = 'membership') {
		$candidates = [];
		$push_candidate = function ($field_name, $value) use (&$candidates, $field_catalog) {
			$field_name = trim((string) $field_name);
			if ($field_name === '' || $value === null || $value === '') {
				return;
			}
			if (!$this->object_has_salesforce_field('contact', $field_name, $field_catalog)) {
				return;
			}

			$key = strtolower($field_name) . '::' . (string) $value;
			if (isset($candidates[$key])) {
				return;
			}

			$candidates[$key] = [
				'field' => $field_name,
				'value' => $this->normalize_related_contact_field_value($field_name, $value, $field_catalog),
			];
		};

		$configured_field = trim((string) ($settings['salesforce']['contact_external_id_field'] ?? ''));
		if ($mode === 'grant') {
			$configured_value = $this->resolve_grant_contact_external_id_value($configured_field, $context);
			$push_candidate($configured_field, $configured_value);
			$push_candidate('Email', sanitize_email((string) ($context['grant']['application']['applicant_email'] ?? '')));
			$push_candidate('WordPress_User_ID__c', absint($context['grant']['application']['applicant_user_id'] ?? 0));
			$push_candidate('Word_Press_User_ID__c', absint($context['grant']['application']['applicant_user_id'] ?? 0));
			$push_candidate('AAC_Member_ID__c', sanitize_text_field((string) ($context['grant']['application']['aac_member_id'] ?? '')));
		} else {
			$member_id = sanitize_text_field((string) $this->resolve_context_path($context, 'member_db.profile_row.member_id'));
			if ($member_id === '') {
				$member_id = sanitize_text_field((string) $this->resolve_context_path($context, 'member_db.profile.profile_info.member_id'));
			}
			$user_id = absint($context['wp']['ID'] ?? 0);
			$email = sanitize_email((string) ($context['wp']['user_email'] ?? $this->resolve_context_path($context, 'member_db.profile_row.email')));

			if ($configured_field !== '') {
				$configured_value = '';
				$normalized_field = strtolower($configured_field);
				if (strpos($normalized_field, 'member') !== false) {
					$configured_value = $member_id;
				} elseif (strpos($normalized_field, 'email') !== false) {
					$configured_value = $email;
				} else {
					$configured_value = $user_id > 0 ? (string) $user_id : '';
				}
				$push_candidate($configured_field, $configured_value);
			}

			$push_candidate('WordPress_User_ID__c', $user_id > 0 ? (string) $user_id : '');
			$push_candidate('Word_Press_User_ID__c', $user_id > 0 ? (string) $user_id : '');
			$push_candidate('AAC_Member_ID__c', $member_id);
			$push_candidate('Email', $email);
		}

		return array_values($candidates);
	}

	private function sanitize_reference_field_payloads($payload, $object_key, $field_catalog = []) {
		if (!is_array($payload) || !$payload) {
			return $payload;
		}

		$catalog_group = isset($field_catalog['objects'][$object_key]) && is_array($field_catalog['objects'][$object_key])
			? $field_catalog['objects'][$object_key]
			: [];
		$fields = isset($catalog_group['fields']) && is_array($catalog_group['fields']) ? $catalog_group['fields'] : [];

		foreach ($fields as $field) {
			if (!is_array($field)) {
				continue;
			}

			$field_name = sanitize_text_field((string) ($field['name'] ?? ''));
			$field_type = strtolower((string) ($field['type'] ?? ''));
			if ($field_name !== '' && array_key_exists($field_name, $payload)) {
				if ($field_type === 'reference') {
					if (!$this->is_valid_salesforce_lookup_id($payload[$field_name])) {
						unset($payload[$field_name]);
					}
				} elseif (!is_array($payload[$field_name])) {
					$payload[$field_name] = $this->normalize_salesforce_field_value(
						$payload[$field_name],
						'string',
						$field_type,
						$field_name
					);
				}
			}

			$relationship_name = sanitize_text_field((string) ($field['relationship_name'] ?? ''));
			if ($relationship_name === '') {
				if (substr($field_name, -3) === '__c') {
					$relationship_name = substr($field_name, 0, -3) . '__r';
				} elseif (substr($field_name, -2) === 'Id') {
					$relationship_name = substr($field_name, 0, -2);
				}
			}

			if ($relationship_name !== '' && array_key_exists($relationship_name, $payload)) {
				if (!is_array($payload[$relationship_name])) {
					unset($payload[$relationship_name]);
					continue;
				}

				$reference_targets = array_values(array_filter(array_map('strval', (array) ($field['reference_to'] ?? []))));
				if (in_array('Contact', $reference_targets, true)) {
					$payload[$relationship_name] = $this->normalize_contact_relationship_payload(
						(array) $payload[$relationship_name],
						$field_catalog
					);
				}
			}
		}

		return $payload;
	}

	private function normalize_contact_relationship_payload(array $payload, $field_catalog = []) {
		foreach ($payload as $field_name => $value) {
			if (is_array($value)) {
				continue;
			}

			$field_name = sanitize_text_field((string) $field_name);
			if ($field_name === '') {
				continue;
			}

			$payload[$field_name] = $this->normalize_related_contact_field_value($field_name, $value, $field_catalog);
		}

		return $payload;
	}

	private function is_valid_salesforce_lookup_id($value) {
		if (!is_scalar($value)) {
			return false;
		}

		$value = trim((string) $value);
		if ($value === '') {
			return false;
		}

		return (bool) preg_match('/^[a-zA-Z0-9]{15}(?:[a-zA-Z0-9]{3})?$/', $value);
	}

	private function get_grant_contact_lookup_metadata($object_key, $field_catalog = []) {
		return $this->get_contact_lookup_metadata($object_key, $field_catalog);
	}

	private function get_contact_lookup_metadata($object_key, $field_catalog = []) {
		$catalog_group = isset($field_catalog['objects'][$object_key]) && is_array($field_catalog['objects'][$object_key])
			? $field_catalog['objects'][$object_key]
			: [];
		$fields = isset($catalog_group['fields']) && is_array($catalog_group['fields']) ? $catalog_group['fields'] : [];
		$fallback_field = [];

		foreach ($fields as $field) {
			if (!is_array($field)) {
				continue;
			}

			$field_name = sanitize_text_field((string) ($field['name'] ?? ''));
			if ($field_name !== '' && in_array($field_name, ['Contact__c', 'ContactId'], true)) {
				$fallback_field = [
					'field_name' => $field_name,
					'relationship_name' => $field_name === 'Contact__c' ? 'Contact__r' : 'Contact',
				];
			}

			if (strtolower((string) ($field['type'] ?? '')) !== 'reference') {
				continue;
			}

			$references = array_map('strval', (array) ($field['reference_to'] ?? []));
			if (!in_array('Contact', $references, true)) {
				continue;
			}

			$field_name = sanitize_text_field((string) ($field['name'] ?? ''));
			if ($field_name === '') {
				continue;
			}

			$relationship_name = sanitize_text_field((string) ($field['relationship_name'] ?? ''));
			if ($relationship_name === '') {
				if (substr($field_name, -3) === '__c') {
					$relationship_name = substr($field_name, 0, -3) . '__r';
				} elseif (substr($field_name, -2) === 'Id') {
					$relationship_name = substr($field_name, 0, -2);
				}
			}

			return [
				'field_name' => $field_name,
				'relationship_name' => $relationship_name,
			];
		}

		return $fallback_field;
	}

	private function get_contact_member_id_lookup_field($settings) {
		$mapping = AAC_Salesforce_Sync_Settings::normalize_mapping_entry(
			$settings['field_mappings']['contact']['member_db_profile_member_id'] ?? [],
			'contact'
		);
		$field_name = sanitize_text_field((string) ($mapping['field'] ?? ''));

		return $field_name !== '' ? $field_name : 'AAC_Member_ID__c';
	}

	private function normalize_related_contact_field_value($field_name, $value, $field_catalog = []) {
		$salesforce_field_type = $this->get_salesforce_field_type('contact', $field_name, $field_catalog);
		$normalized_value = $this->normalize_salesforce_field_value($value, 'string', $salesforce_field_type, $field_name);
		if (
			(is_string($normalized_value) || is_numeric($normalized_value)) &&
			$this->is_numeric_external_id_field($field_name)
		) {
			$numeric = trim((string) $normalized_value);
			if ($numeric !== '' && is_numeric($numeric)) {
				return strpos($numeric, '.') !== false ? (float) $numeric : (int) $numeric;
			}
		}

		return $normalized_value;
	}

	private function object_has_salesforce_field($object_key, $salesforce_field, $field_catalog = []) {
		$catalog_group = isset($field_catalog['objects'][$object_key]) && is_array($field_catalog['objects'][$object_key])
			? $field_catalog['objects'][$object_key]
			: [];
		$fields = isset($catalog_group['fields']) && is_array($catalog_group['fields']) ? $catalog_group['fields'] : [];
		if (!$fields) {
			return true;
		}

		foreach ($fields as $field) {
			if (!is_array($field)) {
				continue;
			}

			if (trim((string) ($field['name'] ?? '')) === trim((string) $salesforce_field)) {
				return true;
			}
		}

		return false;
	}

	private function get_salesforce_field_metadata($object_key, $salesforce_field, $field_catalog = []) {
		$catalog_group = isset($field_catalog['objects'][$object_key]) && is_array($field_catalog['objects'][$object_key])
			? $field_catalog['objects'][$object_key]
			: [];
		$fields = isset($catalog_group['fields']) && is_array($catalog_group['fields']) ? $catalog_group['fields'] : [];

		foreach ($fields as $field) {
			if (!is_array($field)) {
				continue;
			}

			if (trim((string) ($field['name'] ?? '')) === trim((string) $salesforce_field)) {
				return $field;
			}
		}

		return [];
	}

	private function normalize_salesforce_picklist_value($object_key, $salesforce_field, $value, $field_catalog = []) {
		if ($value === null || $value === '') {
			return $value;
		}

		$field = $this->get_salesforce_field_metadata($object_key, $salesforce_field, $field_catalog);
		if (!is_array($field) || strtolower((string) ($field['type'] ?? '')) !== 'picklist') {
			return $value;
		}

		$allowed_values = array_values(array_filter(array_map('strval', (array) ($field['picklist_values'] ?? []))));
		if (!$allowed_values) {
			// Safe fallback for the common Membership Term status mismatch.
			if ($object_key === 'membership' && trim((string) $salesforce_field) === 'Status__c' && strcasecmp((string) $value, 'Inactive') === 0) {
				return 'Expired';
			}
			return $value;
		}

		$value_string = trim((string) $value);
		foreach ($allowed_values as $allowed_value) {
			if (strcasecmp($allowed_value, $value_string) === 0) {
				return $allowed_value;
			}
		}

		$aliases = [
			'active' => ['Active', 'Current', 'Paid'],
			'inactive' => ['Inactive', 'Expired', 'Lapsed', 'Cancelled', 'Canceled'],
			'expired' => ['Expired', 'Inactive', 'Lapsed'],
			'lapsed' => ['Lapsed', 'Expired', 'Inactive'],
			'cancelled' => ['Cancelled', 'Canceled', 'Inactive', 'Expired'],
			'canceled' => ['Canceled', 'Cancelled', 'Inactive', 'Expired'],
			'admin cancelled' => ['Admin Cancelled', 'Cancelled', 'Canceled', 'Inactive', 'Expired'],
			'admin canceled' => ['Admin Canceled', 'Canceled', 'Cancelled', 'Inactive', 'Expired'],
			'changed' => ['Changed', 'Superseded', 'Inactive'],
			'renewed' => ['Renewed', 'Active', 'Current'],
			'needs revision' => ['Needs Revision', 'Revision Requested'],
			'committee review' => ['Committee Review', 'Under Review'],
		];

		$normalized = strtolower(str_replace(['_', '-'], ' ', $value_string));
		$normalized = preg_replace('/\s+/', ' ', $normalized);
		$normalized = is_string($normalized) ? trim($normalized) : '';
		if (!empty($aliases[$normalized])) {
			foreach ($aliases[$normalized] as $candidate) {
				foreach ($allowed_values as $allowed_value) {
					if (strcasecmp($allowed_value, $candidate) === 0) {
						return $allowed_value;
					}
				}
			}
		}

		return $value;
	}

	private function is_numeric_external_id_field($field_name) {
		$field_name = strtolower(trim((string) $field_name));
		if ($field_name === '') {
			return false;
		}

		return strpos($field_name, 'member_id') !== false
			|| strpos($field_name, 'user_id') !== false
			|| strpos($field_name, 'wordpress_user_id') !== false
			|| strpos($field_name, 'word_press_user_id') !== false;
	}

	private function resolve_grant_contact_external_id_value($contact_external_id_field, $context) {
		$normalized_field = strtolower(trim((string) $contact_external_id_field));
		$application = isset($context['grant']['application']) && is_array($context['grant']['application'])
			? $context['grant']['application']
			: [];

		if (strpos($normalized_field, 'member') !== false) {
			return sanitize_text_field((string) ($application['aac_member_id'] ?? ''));
		}

		if (strpos($normalized_field, 'email') !== false) {
			return sanitize_email((string) ($application['applicant_email'] ?? ''));
		}

		if (strpos($normalized_field, 'user') !== false || strpos($normalized_field, 'wordpress') !== false) {
			$applicant_user_id = absint($application['applicant_user_id'] ?? 0);
			return $applicant_user_id > 0 ? (string) $applicant_user_id : '';
		}

		$applicant_user_id = absint($application['applicant_user_id'] ?? 0);
		if ($applicant_user_id > 0) {
			return (string) $applicant_user_id;
		}

		$aac_member_id = sanitize_text_field((string) ($application['aac_member_id'] ?? ''));
		if ($aac_member_id !== '') {
			return $aac_member_id;
		}

		return sanitize_email((string) ($application['applicant_email'] ?? ''));
	}

	private function get_salesforce_field_type($object_key, $salesforce_field, $field_catalog = []) {
		$catalog_group = isset($field_catalog['objects'][$object_key]) && is_array($field_catalog['objects'][$object_key])
			? $field_catalog['objects'][$object_key]
			: [];
		$fields = isset($catalog_group['fields']) && is_array($catalog_group['fields']) ? $catalog_group['fields'] : [];

		foreach ($fields as $field) {
			if (!is_array($field)) {
				continue;
			}

			if (trim((string) ($field['name'] ?? '')) === trim((string) $salesforce_field)) {
				return sanitize_text_field((string) ($field['type'] ?? ''));
			}
		}

		return '';
	}

	private function resolve_effective_salesforce_type($local_type, $salesforce_field_type) {
		$salesforce_field_type = strtolower(trim((string) $salesforce_field_type));
		if ($salesforce_field_type === '') {
			return $local_type;
		}

		$type_map = [
			'boolean' => 'boolean',
			'date' => 'date',
			'datetime' => 'datetime',
			'double' => 'decimal',
			'currency' => 'decimal',
			'percent' => 'decimal',
			'int' => 'integer',
			'integer' => 'integer',
			'email' => 'email',
			'url' => 'url',
			'phone' => 'string',
			'picklist' => 'string',
			'multipicklist' => 'array',
			'reference' => 'string',
			'id' => 'string',
			'textarea' => 'string',
			'string' => 'string',
		];

		return $type_map[$salesforce_field_type] ?? $local_type;
	}

	private function normalize_salesforce_boolean_value($value) {
		if (is_bool($value)) {
			return $value;
		}

		if (is_int($value) || is_float($value)) {
			return ((float) $value) !== 0.0;
		}

		$normalized = strtolower(trim((string) $value));
		if ($normalized === '') {
			return false;
		}

		$truthy = [
			'1',
			'true',
			'yes',
			'on',
			'active',
			'enabled',
			'current',
			'paid',
			'good',
			'member',
		];

		$falsy = [
			'0',
			'false',
			'no',
			'off',
			'inactive',
			'expired',
			'lapsed',
			'cancelled',
			'canceled',
			'suspended',
			'disabled',
		];

		if (in_array($normalized, $truthy, true)) {
			return true;
		}

		if (in_array($normalized, $falsy, true)) {
			return false;
		}

		return in_array($normalized, ['y', 't'], true);
	}

	private function is_skipped_salesforce_field($salesforce_field) {
		// Contact.PhotoUrl looks tempting, but Salesforce owns it and will swat
		// away writes. Skipping it keeps contact sync healthy until a writable
		// custom URL field is mapped instead.
		return trim((string) $salesforce_field) === 'PhotoUrl';
	}

	private function normalize_salesforce_date_value($value) {
		$string = trim((string) $value);
		if ($string === '') {
			return '';
		}

		if (preg_match('/^(-?\d{4})-(\d{2})-(\d{2})$/', $string, $matches)) {
			$year = (int) $matches[1];
			$month = (int) $matches[2];
			$day = (int) $matches[3];
			if ($year < 1900 || !checkdate($month, $day, max(1, $year))) {
				return '';
			}

			return sprintf('%04d-%02d-%02d', $year, $month, $day);
		}

		$timestamp = strtotime($string);
		if ($timestamp === false) {
			return '';
		}

		$normalized = gmdate('Y-m-d', $timestamp);
		return $this->is_valid_salesforce_calendar_date($normalized) ? $normalized : '';
	}

	private function normalize_salesforce_datetime_value($value) {
		$string = trim((string) $value);
		if ($string === '') {
			return '';
		}

		$timestamp = strtotime($string);
		if ($timestamp === false) {
			return '';
		}

		$normalized = gmdate('Y-m-d\TH:i:s\Z', $timestamp);
		return $this->is_valid_salesforce_datetime_value($normalized) ? $normalized : '';
	}

	private function is_valid_salesforce_calendar_date($value) {
		$value = trim((string) $value);
		if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches)) {
			return false;
		}

		$year = (int) $matches[1];
		$month = (int) $matches[2];
		$day = (int) $matches[3];

		return $year >= 1900 && checkdate($month, $day, $year);
	}

	private function is_valid_salesforce_datetime_value($value) {
		$value = trim((string) $value);
		if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})Z$/', $value, $matches)) {
			return false;
		}

		$year = (int) $matches[1];
		$month = (int) $matches[2];
		$day = (int) $matches[3];

		return $year >= 1900 && checkdate($month, $day, $year);
	}

	private function normalize_salesforce_address_value($value, $salesforce_field) {
		$salesforce_field = trim((string) $salesforce_field);
		if ($salesforce_field === '') {
			return null;
		}

		$raw_value = sanitize_text_field((string) $value);
		$raw_value = trim($raw_value);
		if ($raw_value === '') {
			return null;
		}

		if (preg_match('/(?:MailingCountry|BillingCountry)Code$/', $salesforce_field)) {
			return $this->normalize_country_code_value($raw_value);
		}

		if (preg_match('/(?:MailingCountry|BillingCountry)$/', $salesforce_field)) {
			return $this->normalize_country_label_value($raw_value);
		}

		if (preg_match('/(?:MailingState|BillingState)Code$/', $salesforce_field)) {
			return $this->normalize_state_code_value($raw_value);
		}

		if (preg_match('/(?:MailingState|BillingState)$/', $salesforce_field)) {
			return $this->normalize_state_label_value($raw_value);
		}

		return null;
	}

	private function normalize_country_code_value($value) {
		$normalized = strtoupper(trim((string) $value));
		$code_map = [
			'US' => 'US',
			'USA' => 'US',
			'UNITED STATES' => 'US',
			'UNITED STATES OF AMERICA' => 'US',
			'CA' => 'CA',
			'CAN' => 'CA',
			'CANADA' => 'CA',
			'MX' => 'MX',
			'MEX' => 'MX',
			'MEXICO' => 'MX',
		];

		return $code_map[$normalized] ?? $normalized;
	}

	private function normalize_country_label_value($value) {
		$normalized = strtoupper(trim((string) $value));
		$label_map = [
			'US' => 'United States',
			'USA' => 'United States',
			'UNITED STATES' => 'United States',
			'UNITED STATES OF AMERICA' => 'United States',
			'CA' => 'Canada',
			'CAN' => 'Canada',
			'CANADA' => 'Canada',
			'MX' => 'Mexico',
			'MEX' => 'Mexico',
			'MEXICO' => 'Mexico',
		];

		return $label_map[$normalized] ?? sanitize_text_field((string) $value);
	}

	private function normalize_state_code_value($value) {
		$normalized = strtoupper(trim((string) $value));
		$state_map = $this->get_state_code_label_map();
		$labels_to_codes = array_change_key_case(array_flip($state_map), CASE_UPPER);

		if (isset($state_map[$normalized])) {
			return $normalized;
		}

		return $labels_to_codes[$normalized] ?? $normalized;
	}

	private function normalize_state_label_value($value) {
		$normalized = strtoupper(trim((string) $value));
		$state_map = $this->get_state_code_label_map();
		$labels_to_codes = array_change_key_case(array_flip($state_map), CASE_UPPER);

		if (isset($state_map[$normalized])) {
			return $state_map[$normalized];
		}

		if (isset($labels_to_codes[$normalized])) {
			return $state_map[$labels_to_codes[$normalized]];
		}

		return sanitize_text_field((string) $value);
	}

	private function get_state_code_label_map() {
		return [
			'AL' => 'Alabama',
			'AK' => 'Alaska',
			'AZ' => 'Arizona',
			'AR' => 'Arkansas',
			'CA' => 'California',
			'CO' => 'Colorado',
			'CT' => 'Connecticut',
			'DE' => 'Delaware',
			'DC' => 'District of Columbia',
			'FL' => 'Florida',
			'GA' => 'Georgia',
			'HI' => 'Hawaii',
			'ID' => 'Idaho',
			'IL' => 'Illinois',
			'IN' => 'Indiana',
			'IA' => 'Iowa',
			'KS' => 'Kansas',
			'KY' => 'Kentucky',
			'LA' => 'Louisiana',
			'ME' => 'Maine',
			'MD' => 'Maryland',
			'MA' => 'Massachusetts',
			'MI' => 'Michigan',
			'MN' => 'Minnesota',
			'MS' => 'Mississippi',
			'MO' => 'Missouri',
			'MT' => 'Montana',
			'NE' => 'Nebraska',
			'NV' => 'Nevada',
			'NH' => 'New Hampshire',
			'NJ' => 'New Jersey',
			'NM' => 'New Mexico',
			'NY' => 'New York',
			'NC' => 'North Carolina',
			'ND' => 'North Dakota',
			'OH' => 'Ohio',
			'OK' => 'Oklahoma',
			'OR' => 'Oregon',
			'PA' => 'Pennsylvania',
			'RI' => 'Rhode Island',
			'SC' => 'South Carolina',
			'SD' => 'South Dakota',
			'TN' => 'Tennessee',
			'TX' => 'Texas',
			'UT' => 'Utah',
			'VT' => 'Vermont',
			'VA' => 'Virginia',
			'WA' => 'Washington',
			'WV' => 'West Virginia',
			'WI' => 'Wisconsin',
			'WY' => 'Wyoming',
			'AB' => 'Alberta',
			'BC' => 'British Columbia',
			'MB' => 'Manitoba',
			'NB' => 'New Brunswick',
			'NL' => 'Newfoundland and Labrador',
			'NS' => 'Nova Scotia',
			'NT' => 'Northwest Territories',
			'NU' => 'Nunavut',
			'ON' => 'Ontario',
			'PE' => 'Prince Edward Island',
			'QC' => 'Quebec',
			'SK' => 'Saskatchewan',
			'YT' => 'Yukon',
		];
	}

	private function get_external_key($user_id) {
		$existing = (string) get_user_meta($user_id, 'aac_external_key', true);
		if ($existing !== '') {
			return $existing;
		}

		$generated = 'aac-wp-user-' . (int) $user_id;
		update_user_meta($user_id, 'aac_external_key', $generated);
		return $generated;
	}

	private function find_user_for_inbound_payload($payload) {
		if (!empty($payload['wordpress_user_id'])) {
			$user = get_user_by('id', absint($payload['wordpress_user_id']));
			if ($user instanceof WP_User) {
				return $user;
			}
		}

		if (!empty($payload['aac_external_key'])) {
			$users = get_users([
				'meta_key' => 'aac_external_key',
				'meta_value' => sanitize_text_field($payload['aac_external_key']),
				'number' => 1,
				'fields' => 'all',
			]);
			if (!empty($users[0]) && $users[0] instanceof WP_User) {
				return $users[0];
			}
		}

		if (!empty($payload['email'])) {
			$user = get_user_by('email', sanitize_email($payload['email']));
			if ($user instanceof WP_User) {
				return $user;
			}
		}

		return null;
	}
}
