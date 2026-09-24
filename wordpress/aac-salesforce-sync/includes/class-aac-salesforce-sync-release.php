<?php

if (!defined('ABSPATH')) {
	exit;
}

class AAC_Salesforce_Sync_Release {
	const REPORT_OPTION = 'aac_salesforce_sync_preflight_report';

	public static function init(AAC_Salesforce_Sync_Worker $worker) {
		if (defined('WP_CLI') && WP_CLI) {
			WP_CLI::add_command('aac-salesforce status', function () {
				WP_CLI::line(wp_json_encode(AAC_Salesforce_Sync_Health::get_report(), JSON_PRETTY_PRINT));
			});
			WP_CLI::add_command('aac-salesforce preflight', function ($args, $assoc_args) {
				$report = self::run_preflight(!empty($assoc_args['live']));
				WP_CLI::line(wp_json_encode($report, JSON_PRETTY_PRINT));
				$report['ready'] ? WP_CLI::success('Release preflight passed.') : WP_CLI::error('Release preflight failed.');
			});
			WP_CLI::add_command('aac-salesforce run-queue', function () use ($worker) {
				WP_CLI::success('Completed jobs: ' . $worker->process_queue(null, true));
			});
			WP_CLI::add_command('aac-salesforce retry-dead-letters', function ($args, $assoc_args) {
				WP_CLI::success('Jobs reset: ' . AAC_Salesforce_Sync_Queue::retry_dead_letter_jobs(absint($assoc_args['limit'] ?? 1000)));
			});
		}
	}

	public static function run_preflight($live = false) {
		$settings = AAC_Salesforce_Sync_Settings::get_settings();
		$catalog = AAC_Salesforce_Sync_Settings::get_field_catalog();
		$definitions = AAC_Salesforce_Sync_Settings::get_object_definitions($settings);
		$checks = [];
		self::add_check($checks, 'plugin_enabled', 'Sync is enabled', !empty($settings['general']['enabled']), 'critical');
		self::add_check($checks, 'https', 'WordPress uses HTTPS', is_ssl() || strpos(home_url(), 'https://') === 0, 'critical');
		$auth = AAC_Salesforce_Sync_Settings::get_auth_state();
		self::add_check($checks, 'authorized', 'Salesforce OAuth authorization exists', !empty($auth['refresh_token']) || !empty($auth['access_token']), 'critical');
		self::add_check($checks, 'worker_cron', 'Queue worker is scheduled', (bool) wp_next_scheduled(AAC_Salesforce_Sync_Worker::CRON_HOOK), 'critical');
		$secret = (string) ($settings['inbound']['secret'] ?? '');
		self::add_check($checks, 'inbound_secret', 'Inbound shared secret is at least 32 characters', strlen($secret) >= 32, 'critical');
		self::add_check($checks, 'catalog_fresh', 'Salesforce metadata was refreshed within 30 days', !empty($catalog['connected_at']) && strtotime((string) $catalog['connected_at']) >= time() - 30 * DAY_IN_SECONDS, 'warning');

		$enabled = ['contact' => 'sync_contact', 'membership' => 'sync_membership', 'transaction' => 'sync_transaction'];
		foreach ($enabled as $group => $setting_key) {
			if (empty($settings['general'][$setting_key])) {
				continue;
			}
			foreach ((array) ($definitions[$group] ?? []) as $object_key => $definition) {
				$object_name = trim((string) ($definition['object_name'] ?? ''));
				$external_field = trim((string) ($definition['external_id_field'] ?? ''));
				$catalog_object = (array) ($catalog['objects'][$object_key] ?? []);
				$fields = [];
				foreach ((array) ($catalog_object['fields'] ?? []) as $field) {
					$fields[(string) ($field['name'] ?? '')] = $field;
				}
				self::add_check($checks, $object_key . '_object', $object_name . ' metadata is available', $object_name !== '' && !empty($catalog_object) && empty($catalog_object['error']), 'critical');
				self::add_check($checks, $object_key . '_external_id', $object_name . '.' . $external_field . ' exists', $external_field !== '' && isset($fields[$external_field]), 'critical');
				if (isset($fields[$external_field]['external_id'])) {
					self::add_check($checks, $object_key . '_external_flag', $external_field . ' is configured as a Salesforce External ID', !empty($fields[$external_field]['external_id']), 'critical');
					self::add_check($checks, $object_key . '_unique_flag', $external_field . ' is unique', !empty($fields[$external_field]['unique']), 'warning');
				}
			}
			foreach ((array) ($settings['field_mappings'][$group] ?? []) as $mapping_key => $raw_mapping) {
				$mapping = AAC_Salesforce_Sync_Settings::normalize_mapping_entry($raw_mapping, $group);
				$object_key = (string) ($mapping['object'] ?? $group);
				$field_name = trim((string) ($mapping['field'] ?? ''));
				if ($field_name === '' || in_array(strtolower($field_name), ['none', 'skip', 'do_not_sync'], true)) {
					continue;
				}
				$catalog_fields = (array) ($catalog['objects'][$object_key]['fields'] ?? []);
				$catalog_names = array_map(static function ($field) { return (string) ($field['name'] ?? ''); }, $catalog_fields);
				self::add_check($checks, $group . '_' . $mapping_key, ucfirst($group) . ' mapping exists: ' . $field_name, in_array($field_name, $catalog_names, true), 'critical');
			}
		}

		if ($live) {
			try {
				(new AAC_Salesforce_Sync_Salesforce_Client())->test_connection();
				self::add_check($checks, 'live_connection', 'Live Salesforce API connection succeeds', true, 'critical');
			} catch (Throwable $exception) {
				self::add_check($checks, 'live_connection', 'Live Salesforce API connection succeeds', false, 'critical', $exception->getMessage());
			}
		}

		$critical_failures = array_filter($checks, static function ($check) { return !$check['passed'] && $check['severity'] === 'critical'; });
		$report = ['generated_at' => current_time('mysql', true), 'live_test' => (bool) $live, 'ready' => !$critical_failures, 'checks' => array_values($checks)];
		update_option(self::REPORT_OPTION, $report, false);
		AAC_Salesforce_Sync_Health::record_event('release_preflight', ['status' => $report['ready'] ? 'passed' : 'failed', 'actor_user_id' => get_current_user_id()]);
		return $report;
	}

	public static function diagnostic_snapshot() {
		$settings = AAC_Salesforce_Sync_Settings::get_settings();
		return [
			'generated_at' => current_time('mysql', true),
			'plugin_version' => AAC_SALESFORCE_SYNC_VERSION,
			'wordpress_version' => get_bloginfo('version'),
			'php_version' => PHP_VERSION,
			'site_url' => home_url(),
			'health' => AAC_Salesforce_Sync_Health::get_report(),
			'preflight' => get_option(self::REPORT_OPTION, []),
			'object_definitions' => AAC_Salesforce_Sync_Settings::get_object_definitions($settings),
			'enabled_groups' => [
				'contact' => !empty($settings['general']['sync_contact']), 'membership' => !empty($settings['general']['sync_membership']),
				'transaction' => !empty($settings['general']['sync_transaction']),
			],
			'audit' => AAC_Salesforce_Sync_Health::get_audit_log(100),
		];
	}

	private static function add_check(&$checks, $key, $label, $passed, $severity, $detail = '') {
		$checks[] = ['key' => sanitize_key($key), 'label' => sanitize_text_field($label), 'passed' => (bool) $passed, 'severity' => $severity, 'detail' => sanitize_text_field($detail)];
	}
}
