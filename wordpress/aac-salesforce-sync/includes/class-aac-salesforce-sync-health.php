<?php

if (!defined('ABSPATH')) {
	exit;
}

class AAC_Salesforce_Sync_Health {
	const MONITOR_HOOK = 'aac_salesforce_sync_health_monitor';
	const STATE_OPTION = 'aac_salesforce_sync_health_state';
	const AUDIT_OPTION = 'aac_salesforce_sync_audit_log';

	public static function init() {
		add_action('init', [__CLASS__, 'schedule'], 25);
		add_action(self::MONITOR_HOOK, [__CLASS__, 'run_monitor']);
		add_filter('site_status_tests', [__CLASS__, 'register_site_health_test']);
		add_filter('debug_information', [__CLASS__, 'add_debug_information']);
	}

	public static function schedule() {
		if (!wp_next_scheduled(self::MONITOR_HOOK)) {
			wp_schedule_event(time() + 5 * MINUTE_IN_SECONDS, 'hourly', self::MONITOR_HOOK);
		}
	}

	public static function deactivate() {
		$timestamp = wp_next_scheduled(self::MONITOR_HOOK);
		if ($timestamp) {
			wp_unschedule_event($timestamp, self::MONITOR_HOOK);
		}
	}

	public static function record_event($event, $context = []) {
		$rows = get_option(self::AUDIT_OPTION, []);
		$rows = is_array($rows) ? $rows : [];
		array_unshift($rows, [
			'timestamp' => current_time('mysql', true),
			'event' => sanitize_key((string) $event),
			'context' => self::sanitize_context($context),
		]);
		update_option(self::AUDIT_OPTION, array_slice($rows, 0, 500), false);
	}

	public static function get_audit_log($limit = 100) {
		return array_slice((array) get_option(self::AUDIT_OPTION, []), 0, max(1, min(500, absint($limit))));
	}

	public static function get_report() {
		global $wpdb;
		$settings = AAC_Salesforce_Sync_Settings::get_settings();
		$ops = (array) ($settings['operations'] ?? []);
		$table = AAC_Salesforce_Sync_Queue::table_name();
		$now = time();
		$pending_limit = max(1, absint($ops['pending_sla_minutes'] ?? 30));
		$dead_limit = max(1, absint($ops['dead_letter_threshold'] ?? 1));
		$oldest_pending = $wpdb ? (string) $wpdb->get_var("SELECT MIN(created_at) FROM {$table} WHERE status = 'pending'") : '';
		$last_completed = $wpdb ? (string) $wpdb->get_var("SELECT MAX(updated_at) FROM {$table} WHERE status = 'completed'") : '';
		$since_24h = gmdate('Y-m-d H:i:s', $now - DAY_IN_SECONDS);
		$completed_24h = $wpdb ? (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE status = 'completed' AND updated_at >= %s", $since_24h)) : 0;
		$failed_24h = $wpdb ? (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT job_id) FROM " . AAC_Salesforce_Sync_Error_Log::table_name() . " WHERE failed_at >= %s", $since_24h)) : 0;
		$stats = AAC_Salesforce_Sync_Queue::get_stats();
		$pending_age = $oldest_pending ? max(0, $now - strtotime($oldest_pending . ' UTC')) : 0;
		$cron = wp_next_scheduled(AAC_Salesforce_Sync_Worker::CRON_HOOK);
		$checks = [
			'authorized' => !empty(AAC_Salesforce_Sync_Settings::get_auth_state()['refresh_token']) || !empty(AAC_Salesforce_Sync_Settings::get_auth_state()['access_token']),
			'worker_scheduled' => (bool) $cron,
			'pending_within_sla' => $pending_age <= ($pending_limit * MINUTE_IN_SECONDS),
			'dead_letter_within_threshold' => (int) ($stats['dead_letter'] ?? 0) < $dead_limit,
		];
		$status = empty($settings['general']['enabled']) ? 'disabled' : (in_array(false, $checks, true) ? 'critical' : ((int) ($stats['pending'] ?? 0) > 0 ? 'warning' : 'healthy'));
		return [
			'status' => $status,
			'checked_at' => current_time('mysql', true),
			'checks' => $checks,
			'queue' => $stats,
			'oldest_pending' => $oldest_pending,
			'oldest_pending_age_seconds' => $pending_age,
			'last_completed' => $last_completed,
			'completed_24h' => $completed_24h,
			'failed_24h' => $failed_24h,
			'success_rate_24h' => ($completed_24h + $failed_24h) > 0 ? round(100 * $completed_24h / ($completed_24h + $failed_24h), 1) : 100,
			'next_worker_run' => $cron ? gmdate('Y-m-d H:i:s', $cron) : '',
		];
	}

	public static function run_monitor() {
		$report = self::get_report();
		$settings = AAC_Salesforce_Sync_Settings::get_settings();
		$ops = (array) ($settings['operations'] ?? []);
		$previous = get_option(self::STATE_OPTION, []);
		$previous = is_array($previous) ? $previous : [];
		$last_alert = absint($previous['last_alert_at'] ?? 0);
		$should_alert = $report['status'] === 'critical' && ($previous['status'] ?? '') !== 'critical';
		$should_alert = $should_alert || ($report['status'] === 'critical' && time() - $last_alert >= 6 * HOUR_IN_SECONDS);
		if (!empty($ops['alerts_enabled']) && $should_alert) {
			$recipient = sanitize_email((string) ($ops['alert_email'] ?? get_option('admin_email')));
			if ($recipient && is_email($recipient)) {
				wp_mail($recipient, 'AAC Salesforce Sync health alert', self::format_alert($report));
				$last_alert = time();
				self::record_event('health_alert_sent', ['status' => $report['status'], 'recipient' => $recipient]);
			}
		}
		self::prune_operational_data($settings);
		update_option(self::STATE_OPTION, ['status' => $report['status'], 'last_checked_at' => time(), 'last_alert_at' => $last_alert, 'report' => $report], false);
		do_action('aac_salesforce_sync_health_checked', $report);
		return $report;
	}

	public static function register_site_health_test($tests) {
		$tests['direct']['aac_salesforce_sync'] = ['label' => 'AAC Salesforce Sync operational health', 'test' => [__CLASS__, 'site_health_test']];
		return $tests;
	}

	public static function site_health_test() {
		$report = self::get_report();
		$healthy = $report['status'] !== 'critical';
		return [
			'label' => $healthy ? 'AAC Salesforce Sync is operational' : 'AAC Salesforce Sync needs attention',
			'status' => $healthy ? 'good' : 'critical',
			'badge' => ['label' => 'AAC Salesforce Sync', 'color' => 'blue'],
			'description' => '<p>' . esc_html('Queue status: ' . $report['status'] . '. Pending: ' . (int) ($report['queue']['pending'] ?? 0) . '. Dead letter: ' . (int) ($report['queue']['dead_letter'] ?? 0) . '.') . '</p>',
			'actions' => '',
			'test' => 'aac_salesforce_sync',
		];
	}

	public static function add_debug_information($info) {
		$report = self::get_report();
		$info['aac-salesforce-sync'] = [
			'label' => 'AAC Salesforce Sync',
			'fields' => [
				'version' => ['label' => 'Version', 'value' => AAC_SALESFORCE_SYNC_VERSION],
				'health' => ['label' => 'Health', 'value' => $report['status']],
				'pending' => ['label' => 'Pending jobs', 'value' => (int) ($report['queue']['pending'] ?? 0)],
				'dead_letter' => ['label' => 'Dead-letter jobs', 'value' => (int) ($report['queue']['dead_letter'] ?? 0)],
				'last_completed' => ['label' => 'Last completed job (UTC)', 'value' => $report['last_completed'] ?: 'None'],
			],
		];
		return $info;
	}

	private static function prune_operational_data($settings) {
		global $wpdb;
		$ops = (array) ($settings['operations'] ?? []);
		$days = max(1, absint($ops['completed_job_retention_days'] ?? 30));
		if ($wpdb) {
			$cutoff = gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);
			$wpdb->query($wpdb->prepare("DELETE FROM " . AAC_Salesforce_Sync_Queue::table_name() . " WHERE status = 'completed' AND updated_at < %s", $cutoff));
		}
		$error_days = max(1, absint($settings['error_report']['retention_days'] ?? 90));
		AAC_Salesforce_Sync_Error_Log::clear_rows($error_days);
	}

	private static function format_alert($report) {
		return "AAC Salesforce Sync has entered a critical state.\n\n" .
			'Pending jobs: ' . (int) ($report['queue']['pending'] ?? 0) . "\n" .
			'Dead-letter jobs: ' . (int) ($report['queue']['dead_letter'] ?? 0) . "\n" .
			'Oldest pending job: ' . ($report['oldest_pending'] ?: 'None') . " UTC\n" .
			'Worker scheduled: ' . (!empty($report['checks']['worker_scheduled']) ? 'Yes' : 'No') . "\n";
	}

	private static function sanitize_context($context) {
		$allowed = ['job_id', 'event_id', 'job_type', 'object_type', 'external_key', 'status', 'duration_ms', 'attempts', 'recipient', 'actor_user_id'];
		$result = [];
		foreach ($allowed as $key) {
			if (array_key_exists($key, (array) $context)) {
				$result[$key] = is_numeric($context[$key]) ? $context[$key] : sanitize_text_field((string) $context[$key]);
			}
		}
		return $result;
	}
}
