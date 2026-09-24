<?php

if (!defined('ABSPATH')) {
	exit;
}

class AAC_Salesforce_Sync_Pull {
	const CRON_HOOK = 'aac_salesforce_sync_pull_from_salesforce';
	const STATE_OPTION = 'aac_salesforce_sync_pull_state';

	private $worker;

	public function __construct(AAC_Salesforce_Sync_Worker $worker) {
		$this->worker = $worker;

		add_action('init', [__CLASS__, 'maybe_schedule'], 30);
		add_action(self::CRON_HOOK, [$this, 'run_scheduled_pull']);
	}

	public static function maybe_schedule() {
		self::reschedule_if_needed(AAC_Salesforce_Sync_Settings::get_settings());
	}

	public static function reschedule($settings = null) {
		self::clear_schedule();
		$settings = is_array($settings) ? $settings : AAC_Salesforce_Sync_Settings::get_settings();
		if (!empty($settings['pull']['enabled'])) {
			wp_schedule_event(time() + MINUTE_IN_SECONDS, self::get_interval($settings), self::CRON_HOOK);
		}
	}

	public static function deactivate() {
		self::clear_schedule();
	}

	public function run_scheduled_pull() {
		$settings = AAC_Salesforce_Sync_Settings::get_settings();
		if (empty($settings['pull']['enabled'])) {
			return [
				'contact' => ['updated' => 0, 'failed' => 0],
				'membership' => ['updated' => 0, 'failed' => 0],
			];
		}

		return $this->run_pull(false);
	}

	public function run_pull($manual = true) {
		$settings = AAC_Salesforce_Sync_Settings::get_settings();
		$client = new AAC_Salesforce_Sync_Salesforce_Client();
		$state = self::get_state();
		$results = [
			'contact' => ['updated' => 0, 'failed' => 0, 'queried' => 0],
			'membership' => ['updated' => 0, 'failed' => 0, 'queried' => 0],
		];

		if (!empty($settings['pull']['sync_contact'])) {
			$results['contact'] = $this->pull_group('contact', $client, $settings, $state);
		}

		if (!empty($settings['pull']['sync_membership'])) {
			$results['membership'] = $this->pull_group('membership', $client, $settings, $state);
		}

		$state['last_run_at'] = current_time('mysql');
		$state['last_run_manual'] = $manual ? 1 : 0;
		$state['last_result'] = $results;
		self::update_state($state);

		return $results;
	}

	public static function get_state() {
		$state = get_option(self::STATE_OPTION, []);
		return is_array($state) ? $state : [];
	}

	public static function update_state($state) {
		update_option(self::STATE_OPTION, is_array($state) ? $state : [], false);
	}

	public static function reset_state() {
		delete_option(self::STATE_OPTION);
	}

	private function pull_group($group, AAC_Salesforce_Sync_Salesforce_Client $client, $settings, $state) {
		$group = sanitize_key((string) $group);
		$object_definitions = AAC_Salesforce_Sync_Settings::get_object_definitions($settings);
		$object_definition = isset($object_definitions[$group][$group]) && is_array($object_definitions[$group][$group])
			? $object_definitions[$group][$group]
			: [];
		$object_name = $this->sanitize_soql_identifier((string) ($object_definition['object_name'] ?? ''));
		if ($object_name === '') {
			throw new RuntimeException('Salesforce pull object is not configured for ' . $group . '.');
		}

		$field_map = $this->get_pull_field_map($group, $settings);
		$fields = array_values(array_unique(array_filter(array_merge(['Id', 'SystemModstamp', 'LastModifiedDate'], array_values($field_map)))));
		$fields = array_values(array_filter(array_map([$this, 'sanitize_soql_identifier'], $fields)));
		if (!$fields) {
			throw new RuntimeException('No Salesforce fields are configured for ' . $group . ' pull.');
		}

		$since = $this->get_since_datetime($group, $settings, $state);
		$batch_size = max(1, min(200, absint($settings['pull']['batch_size'] ?? 100)));
		$soql = sprintf(
			'SELECT %s FROM %s WHERE SystemModstamp > %s ORDER BY SystemModstamp ASC LIMIT %d',
			implode(', ', $fields),
			$object_name,
			$this->format_soql_datetime_literal($since),
			$batch_size
		);

		$started_at = current_time('mysql');
		$result = $client->query($soql);
		$records = is_array($result['records'] ?? null) ? $result['records'] : [];
		$counts = [
			'updated' => 0,
			'failed' => 0,
			'queried' => count($records),
		];

		foreach ($records as $record) {
			if (!is_array($record)) {
				continue;
			}

			$payload = $this->build_inbound_payload($group, $record, $field_map);
			try {
				if ($group === 'contact') {
					$this->worker->sync_contact_from_salesforce($payload);
				} elseif ($group === 'membership') {
					$this->worker->sync_membership_from_salesforce($payload);
				}
				$counts['updated']++;
			} catch (Exception $exception) {
				$counts['failed']++;
				AAC_Salesforce_Sync_Error_Log::record_job_failure(
					[
						'id' => 0,
						'job_type' => 'pull_' . $group,
						'object_type' => $group,
						'object_id' => absint($payload['wordpress_user_id'] ?? 0),
						'external_key' => sanitize_text_field((string) ($payload['aac_external_key'] ?? ($record['Id'] ?? ''))),
						'source' => 'salesforce_pull',
						'attempts' => 0,
					],
					$exception->getMessage(),
					[
						'object_key' => $group,
						'salesforce_object' => $object_name,
						'payload' => $payload,
					]
				);
			}
		}

		$state = self::get_state();
		$state[$group . '_last_attempt_at'] = $started_at;
		if ($counts['failed'] === 0) {
			$state[$group . '_last_success_at'] = $started_at;
		}
		self::update_state($state);

		return $counts;
	}

	private function get_pull_field_map($group, $settings) {
		$mappings = isset($settings['field_mappings'][$group]) && is_array($settings['field_mappings'][$group]) ? $settings['field_mappings'][$group] : [];
		$field_keys = $group === 'membership' ? [
			'member_db_profile_user_id' => 'wordpress_user_id',
			'member_db_profile_member_id' => 'member_id',
			'member_db_profile_membership_level' => 'membership_level',
			'member_db_profile_membership_status' => 'status',
			'member_db_profile_renewal_date' => 'renewal_date',
			'member_db_profile_expiration_date' => 'expiration_date',
			'member_db_profile_account_info_auto_renew' => 'auto_renew',
			'member_db_profile_benefits_info_rescue_amount' => 'rescue_amount',
			'member_db_profile_benefits_info_medical_amount' => 'medical_amount',
			'member_db_profile_benefits_info_mortal_remains_amount' => 'mortal_remains_amount',
			'member_db_profile_benefits_info_rescue_reimbursement_process' => 'rescue_reimbursement_process',
			'member_db_membership_record_membership_id' => 'pmpro_level_id',
		] : [
			'member_db_profile_user_id' => 'wordpress_user_id',
			'member_db_profile_account_info_first_name' => 'first_name',
			'member_db_profile_account_info_last_name' => 'last_name',
			'member_db_profile_account_info_email' => 'email',
			'member_db_profile_account_info_phone' => 'phone',
			'member_db_profile_account_info_street' => 'street',
			'member_db_profile_account_info_city' => 'city',
			'member_db_profile_account_info_state' => 'state',
			'member_db_profile_account_info_zip' => 'postal_code',
			'member_db_profile_account_info_country' => 'country',
		];

		$field_map = [];
		foreach ($field_keys as $field_key => $payload_key) {
			$mapping = AAC_Salesforce_Sync_Settings::normalize_mapping_entry($mappings[$field_key] ?? [], $group);
			$mapped_group = sanitize_key((string) ($mapping['object'] ?? ''));
			$salesforce_field = $this->sanitize_soql_identifier((string) ($mapping['field'] ?? ''));
			if ($salesforce_field === '' || ($mapped_group !== '' && $mapped_group !== $group)) {
				continue;
			}

			$field_map[$payload_key] = $salesforce_field;
		}

		return $field_map;
	}

	private function build_inbound_payload($group, $record, $field_map) {
		$payload = [];
		foreach ($field_map as $payload_key => $salesforce_field) {
			if (array_key_exists($salesforce_field, $record)) {
				$payload[$payload_key] = $record[$salesforce_field];
			}
		}

		if (!empty($record['Id'])) {
			if ($group === 'contact') {
				$payload['salesforce_contact_id'] = sanitize_text_field((string) $record['Id']);
			} elseif ($group === 'membership') {
				$payload['salesforce_membership_id'] = sanitize_text_field((string) $record['Id']);
			}
		}

		if (isset($payload['wordpress_user_id'])) {
			$payload['wordpress_user_id'] = absint($payload['wordpress_user_id']);
		}

		return $payload;
	}

	private function get_since_datetime($group, $settings, $state) {
		$key = sanitize_key((string) $group) . '_last_success_at';
		if (!empty($state[$key])) {
			return (string) $state[$key];
		}

		$lookback_hours = max(1, min(720, absint($settings['pull']['lookback_hours'] ?? 24)));
		return date('Y-m-d H:i:s', current_time('timestamp') - ($lookback_hours * HOUR_IN_SECONDS));
	}

	private static function clear_schedule() {
		$timestamp = wp_next_scheduled(self::CRON_HOOK);
		while ($timestamp) {
			wp_unschedule_event($timestamp, self::CRON_HOOK);
			$timestamp = wp_next_scheduled(self::CRON_HOOK);
		}
	}

	private static function reschedule_if_needed($settings) {
		$timestamp = wp_next_scheduled(self::CRON_HOOK);
		$enabled = !empty($settings['pull']['enabled']);
		if (!$enabled && $timestamp) {
			self::clear_schedule();
			return;
		}

		if ($enabled && !$timestamp) {
			wp_schedule_event(time() + MINUTE_IN_SECONDS, self::get_interval($settings), self::CRON_HOOK);
		}
	}

	private static function get_interval($settings) {
		$interval = sanitize_key((string) ($settings['pull']['interval'] ?? 'hourly'));
		return in_array($interval, ['aac_salesforce_sync_five_minutes', 'hourly', 'twicedaily', 'daily'], true) ? $interval : 'hourly';
	}

	private function sanitize_soql_identifier($identifier) {
		$identifier = trim((string) $identifier);
		return preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)?$/', $identifier) ? $identifier : '';
	}

	private function format_soql_datetime_literal($mysql_datetime) {
		try {
			$date = new DateTimeImmutable((string) $mysql_datetime, wp_timezone());
			return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
		} catch (Exception $exception) {
			return gmdate('Y-m-d\TH:i:s\Z', time() - DAY_IN_SECONDS);
		}
	}
}
