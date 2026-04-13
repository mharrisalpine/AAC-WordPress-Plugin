<?php

if (!defined('ABSPATH')) {
	exit;
}

class AAC_Salesforce_Sync_Worker {
	const CRON_HOOK = 'aac_salesforce_sync_process_queue';

	public function __construct() {
		add_action('init', ['AAC_Salesforce_Sync_Installer', 'maybe_install_schema']);
		add_filter('cron_schedules', [$this, 'register_cron_schedule']);
		add_action('init', [__CLASS__, 'schedule']);
		add_action(self::CRON_HOOK, [$this, 'process_queue']);

		add_action('profile_update', [$this, 'enqueue_core_profile_update'], 30, 1);
		add_action('aac_member_portal_member_registered', [$this, 'enqueue_member_registered'], 30, 1);
		add_action('aac_member_portal_profile_updated', [$this, 'enqueue_profile_updated'], 30, 1);
		add_action('pmpro_after_checkout', [$this, 'enqueue_after_checkout'], 40, 2);
		add_action('pmpro_after_change_membership_level', [$this, 'enqueue_after_membership_change'], 40, 2);
	}

	public static function schedule() {
		if (!wp_next_scheduled(self::CRON_HOOK)) {
			wp_schedule_event(time() + MINUTE_IN_SECONDS, 'aac_salesforce_sync_five_minutes', self::CRON_HOOK);
		}
	}

	public static function deactivate() {
		$timestamp = wp_next_scheduled(self::CRON_HOOK);
		if ($timestamp) {
			wp_unschedule_event($timestamp, self::CRON_HOOK);
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

	public function enqueue_member_jobs($user_id, $source = 'wordpress') {
		$user_id = (int) $user_id;
		if ($user_id <= 0) {
			return;
		}

		$external_key = $this->get_external_key($user_id);
		AAC_Salesforce_Sync_Queue::enqueue('upsert_contact', 'user', $user_id, (string) $user_id, ['user_id' => $user_id], $source);
		AAC_Salesforce_Sync_Queue::enqueue('upsert_membership', 'membership', $user_id, $external_key, ['user_id' => $user_id], $source);
	}

	public function enqueue_membership_job($user_id, $source = 'wordpress', $extra_payload = []) {
		$user_id = (int) $user_id;
		if ($user_id <= 0) {
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

		if ($user_id <= 0) {
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

	public function process_queue($limit = null) {
		$settings = AAC_Salesforce_Sync_Settings::get_settings();
		if (empty($settings['general']['enabled'])) {
			return 0;
		}

		AAC_Salesforce_Sync_Queue::reset_stale_locks();
		$client = new AAC_Salesforce_Sync_Salesforce_Client();
		$batch_size = $limit ? absint($limit) : (int) $settings['general']['batch_size'];
		$processed = 0;

		for ($i = 0; $i < $batch_size; $i++) {
			$job = AAC_Salesforce_Sync_Queue::claim_next();
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
				$client->upsert(
					$settings['salesforce']['contact_object'],
					$settings['salesforce']['contact_external_id_field'],
					(string) $job['external_key'],
					$this->map_contact_payload((int) $job['object_id'], $profile)
				);
				break;

			case 'upsert_membership':
				$profile = $this->get_portal_profile((int) $job['object_id']);
				$client->upsert(
					$settings['salesforce']['membership_object'],
					$settings['salesforce']['membership_external_id_field'],
					(string) $job['external_key'],
					$this->map_membership_payload((int) $job['object_id'], $profile)
				);
				break;

			case 'upsert_transaction':
				$transaction = $this->get_transaction_payload((int) ($payload['user_id'] ?? $job['object_id']), (int) ($payload['order_id'] ?? 0));
				if (!$transaction) {
					throw new RuntimeException('Could not build PMPro transaction payload.');
				}
				$external_id = (string) ($transaction['PMPro_Order_ID__c'] ?? $job['external_key']);
				$client->upsert(
					$settings['salesforce']['transaction_object'],
					$settings['salesforce']['transaction_external_id_field'],
					$external_id,
					$transaction
				);
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

	private function map_contact_payload($user_id, $profile) {
		$account = is_array($profile['account_info'] ?? null) ? $profile['account_info'] : [];

		return [
			'WordPress_User_ID__c' => (string) $user_id,
			'AAC_External_Key__c' => $this->get_external_key($user_id),
			'FirstName' => sanitize_text_field($account['first_name'] ?? ''),
			'LastName' => sanitize_text_field($account['last_name'] ?? ''),
			'Email' => sanitize_email($account['email'] ?? ''),
			'Phone' => sanitize_text_field($account['phone'] ?? ''),
			'MailingStreet' => trim(sanitize_text_field(($account['street'] ?? '') . (!empty($account['address2']) ? "\n" . $account['address2'] : ''))),
			'MailingCity' => sanitize_text_field($account['city'] ?? ''),
			'MailingState' => sanitize_text_field($account['state'] ?? ''),
			'MailingPostalCode' => sanitize_text_field($account['zip'] ?? ''),
			'MailingCountry' => sanitize_text_field($account['country'] ?? ''),
			'AAC_Family_Account_Role__c' => sanitize_text_field((string) get_user_meta($user_id, 'aac_family_account_role', true)),
		];
	}

	private function map_membership_payload($user_id, $profile) {
		$profile_info = is_array($profile['profile_info'] ?? null) ? $profile['profile_info'] : [];
		$account = is_array($profile['account_info'] ?? null) ? $profile['account_info'] : [];
		$benefits = is_array($profile['benefits_info'] ?? null) ? $profile['benefits_info'] : [];
		$current_level_id = !empty($profile['membership_actions']['current_level_id']) ? (int) $profile['membership_actions']['current_level_id'] : 0;

		return [
			'AAC_External_Key__c' => $this->get_external_key($user_id),
			'WordPress_User_ID__c' => (string) $user_id,
			'AAC_Member_ID__c' => sanitize_text_field($profile_info['member_id'] ?? ''),
			'Membership_Level__c' => sanitize_text_field($profile_info['tier'] ?? ''),
			'Status__c' => sanitize_text_field($profile_info['status'] ?? ''),
			'Renewal_Date__c' => sanitize_text_field($profile_info['renewal_date'] ?? ''),
			'Expiration_Date__c' => sanitize_text_field($profile_info['expiration_date'] ?? ''),
			'Auto_Renew__c' => !empty($account['auto_renew']),
			'Rescue_Benefit_Amount__c' => (float) ($benefits['rescue_amount'] ?? 0),
			'Medical_Benefit_Amount__c' => (float) ($benefits['medical_amount'] ?? 0),
			'Mortal_Remains_Amount__c' => (float) ($benefits['mortal_remains_amount'] ?? 0),
			'Rescue_Reimbursement_Process__c' => !empty($benefits['rescue_reimbursement_process']),
			'PMPro_Level_ID__c' => $current_level_id ? (string) $current_level_id : '',
			'Family_Account_Role__c' => sanitize_text_field((string) get_user_meta($user_id, 'aac_family_account_role', true)),
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

		return [
			'PMPro_Order_ID__c' => (string) ($order['id'] ?? ''),
			'WordPress_User_ID__c' => (string) $user_id,
			'AAC_External_Key__c' => $this->get_external_key($user_id),
			'Amount__c' => isset($order['total']) ? (float) $order['total'] : 0,
			'Status__c' => sanitize_text_field($order['status'] ?? ''),
			'Gateway__c' => sanitize_text_field($order['gateway'] ?? ''),
			'Transaction_Date__c' => sanitize_text_field($order['timestamp'] ?? ''),
			'PMPro_Level_ID__c' => sanitize_text_field((string) ($order['membership_id'] ?? '')),
			'Code__c' => sanitize_text_field($order['code'] ?? ''),
			'Payment_Transaction_ID__c' => sanitize_text_field($order['payment_transaction_id'] ?? ''),
			'Subscription_Transaction_ID__c' => sanitize_text_field($order['subscription_transaction_id'] ?? ''),
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
