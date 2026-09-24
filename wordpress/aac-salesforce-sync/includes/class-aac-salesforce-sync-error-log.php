<?php

if (!defined('ABSPATH')) {
	exit;
}

class AAC_Salesforce_Sync_Error_Log {
	const DAILY_REPORT_HOOK = 'aac_salesforce_sync_daily_error_report';

	public static function init() {
		add_action(self::DAILY_REPORT_HOOK, [__CLASS__, 'send_daily_report']);
		add_action('init', [__CLASS__, 'maybe_schedule_daily_report'], 20);
	}

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'aac_salesforce_sync_error_log';
	}

	public static function maybe_schedule_daily_report() {
		$settings = AAC_Salesforce_Sync_Settings::get_settings();
		$enabled = !empty($settings['error_report']['enabled']);
		$timestamp = wp_next_scheduled(self::DAILY_REPORT_HOOK);

		if (!$enabled) {
			if ($timestamp) {
				wp_unschedule_event($timestamp, self::DAILY_REPORT_HOOK);
			}
			return;
		}

		if (!$timestamp) {
			wp_schedule_event(self::next_report_timestamp($settings), 'daily', self::DAILY_REPORT_HOOK);
		}
	}

	public static function reschedule_daily_report($settings = null) {
		$timestamp = wp_next_scheduled(self::DAILY_REPORT_HOOK);
		if ($timestamp) {
			wp_unschedule_event($timestamp, self::DAILY_REPORT_HOOK);
		}

		$settings = is_array($settings) ? $settings : AAC_Salesforce_Sync_Settings::get_settings();
		if (!empty($settings['error_report']['enabled'])) {
			wp_schedule_event(self::next_report_timestamp($settings), 'daily', self::DAILY_REPORT_HOOK);
		}
	}

	public static function deactivate() {
		$timestamp = wp_next_scheduled(self::DAILY_REPORT_HOOK);
		if ($timestamp) {
			wp_unschedule_event($timestamp, self::DAILY_REPORT_HOOK);
		}
	}

	public static function record_job_failure($job, $error_message, $request_context = null) {
		$job = is_array($job) ? $job : [];
		$request_context = is_array($request_context) ? $request_context : [];
		$payload = isset($request_context['payload']) && is_array($request_context['payload'])
			? $request_context['payload']
			: [];

		if (!$payload) {
			$raw_payload = json_decode((string) ($job['payload'] ?? ''), true);
			$payload = is_array($raw_payload) ? $raw_payload : [];
		}

		if (!$payload) {
			$payload = ['_job' => (string) ($job['job_type'] ?? 'unknown')];
		}

		foreach ($payload as $field_name => $value) {
			self::insert_row([
				'job_id' => absint($job['id'] ?? 0),
				'job_type' => sanitize_key((string) ($job['job_type'] ?? '')),
				'object_type' => sanitize_key((string) ($job['object_type'] ?? '')),
				'object_id' => absint($job['object_id'] ?? 0),
				'external_key' => sanitize_text_field((string) ($job['external_key'] ?? '')),
				'source' => sanitize_key((string) ($job['source'] ?? '')),
				'salesforce_object' => sanitize_text_field((string) ($request_context['salesforce_object'] ?? '')),
				'salesforce_object_key' => sanitize_key((string) ($request_context['object_key'] ?? '')),
				'salesforce_field' => sanitize_text_field((string) $field_name),
				'field_value' => self::stringify_value($value),
				'error_message' => self::stringify_value($error_message),
				'attempts' => absint($job['attempts'] ?? 0) + 1,
				'failed_at' => current_time('mysql'),
			]);
		}
	}

	public static function list_rows($limit = 500) {
		global $wpdb;

		if (!$wpdb) {
			return [];
		}

		$limit = max(1, min(5000, absint($limit)));
		return (array) $wpdb->get_results("SELECT * FROM " . self::table_name() . " ORDER BY failed_at DESC, id DESC LIMIT {$limit}", ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- internal table and bounded integer limit.
	}

	public static function get_rows_since($since_mysql, $limit = 10000) {
		global $wpdb;

		if (!$wpdb) {
			return [];
		}

		$limit = max(1, min(20000, absint($limit)));
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM " . self::table_name() . " WHERE failed_at >= %s ORDER BY failed_at ASC, id ASC LIMIT {$limit}",
				$since_mysql
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- internal table and bounded integer limit.
	}

	public static function get_stats() {
		global $wpdb;

		if (!$wpdb) {
			return [
				'total' => 0,
				'last_24_hours' => 0,
			];
		}

		$table = self::table_name();
		$since = date('Y-m-d H:i:s', current_time('timestamp') - DAY_IN_SECONDS);

		return [
			'total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}"), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			'last_24_hours' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE failed_at >= %s", $since)), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		];
	}

	public static function clear_rows($days = 0) {
		global $wpdb;

		if (!$wpdb) {
			return 0;
		}

		$days = absint($days);
		if ($days > 0) {
			$cutoff = date('Y-m-d H:i:s', current_time('timestamp') - ($days * DAY_IN_SECONDS));
			$deleted = $wpdb->query(
				$wpdb->prepare("DELETE FROM " . self::table_name() . " WHERE failed_at < %s", $cutoff)
			); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		} else {
			$deleted = $wpdb->query("TRUNCATE TABLE " . self::table_name()); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- internal table only.
		}

		return max(0, (int) $deleted);
	}

	public static function output_csv($rows, $filename = '') {
		$filename = $filename ?: 'aac-salesforce-sync-error-log-' . gmdate('Ymd-His') . '.csv';

		nocache_headers();
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');

		$output = fopen('php://output', 'w');
		if ($output === false) {
			wp_die('Unable to open the Salesforce sync error export stream.');
		}

		self::write_csv($output, $rows);
		fclose($output);
		exit;
	}

	public static function send_daily_report() {
		$settings = AAC_Salesforce_Sync_Settings::get_settings();
		if (empty($settings['error_report']['enabled'])) {
			return false;
		}

		$recipient = sanitize_email((string) ($settings['error_report']['recipient_email'] ?? ''));
		if (!$recipient || !is_email($recipient)) {
			$recipient = sanitize_email(get_option('admin_email'));
		}

		if (!$recipient || !is_email($recipient)) {
			return false;
		}

		$since = date('Y-m-d H:i:s', current_time('timestamp') - DAY_IN_SECONDS);
		$rows = self::get_rows_since($since);
		if (!$rows) {
			return false;
		}

		$upload_dir = wp_upload_dir();
		$base_dir = !empty($upload_dir['basedir']) ? trailingslashit($upload_dir['basedir']) . 'aac-salesforce-sync' : get_temp_dir();
		if (!wp_mkdir_p($base_dir)) {
			$base_dir = get_temp_dir();
		}

		$file_path = trailingslashit($base_dir) . 'aac-salesforce-sync-error-report-' . gmdate('Ymd-His') . '.csv';
		$file = fopen($file_path, 'w');
		if ($file === false) {
			return false;
		}

		self::write_csv($file, $rows);
		fclose($file);

		$subject = sprintf('AAC Salesforce Sync Daily Error Report - %s failed field rows', count($rows));
		$message = "Attached is the AAC Salesforce Sync error report for the last 24 hours.\n\n";
		$message .= 'Rows: ' . count($rows) . "\n";
		$message .= 'Generated: ' . wp_date('Y-m-d H:i:s') . "\n";

		$sent = wp_mail($recipient, $subject, $message, [], [$file_path]);
		wp_delete_file($file_path);

		return (bool) $sent;
	}

	private static function insert_row($row) {
		global $wpdb;

		if (!$wpdb) {
			return false;
		}

		return (bool) $wpdb->insert(
			self::table_name(),
			[
				'job_id' => absint($row['job_id'] ?? 0),
				'job_type' => sanitize_key((string) ($row['job_type'] ?? '')),
				'object_type' => sanitize_key((string) ($row['object_type'] ?? '')),
				'object_id' => absint($row['object_id'] ?? 0),
				'external_key' => sanitize_text_field((string) ($row['external_key'] ?? '')),
				'source' => sanitize_key((string) ($row['source'] ?? '')),
				'salesforce_object' => sanitize_text_field((string) ($row['salesforce_object'] ?? '')),
				'salesforce_object_key' => sanitize_key((string) ($row['salesforce_object_key'] ?? '')),
				'salesforce_field' => sanitize_text_field((string) ($row['salesforce_field'] ?? '')),
				'field_value' => (string) ($row['field_value'] ?? ''),
				'error_message' => (string) ($row['error_message'] ?? ''),
				'attempts' => absint($row['attempts'] ?? 0),
				'failed_at' => sanitize_text_field((string) ($row['failed_at'] ?? current_time('mysql'))),
			],
			['%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s']
		);
	}

	private static function write_csv($output, $rows) {
		fputcsv($output, [
			'id',
			'failure_date',
			'job_id',
			'job_type',
			'object_type',
			'object_id',
			'external_key',
			'source',
			'salesforce_object',
			'salesforce_object_key',
			'salesforce_field',
			'value_being_passed',
			'attempt_number',
			'error_message',
		]);

		foreach ((array) $rows as $row) {
			fputcsv($output, [
				$row['id'] ?? '',
				$row['failed_at'] ?? '',
				$row['job_id'] ?? '',
				$row['job_type'] ?? '',
				$row['object_type'] ?? '',
				$row['object_id'] ?? '',
				$row['external_key'] ?? '',
				$row['source'] ?? '',
				$row['salesforce_object'] ?? '',
				$row['salesforce_object_key'] ?? '',
				$row['salesforce_field'] ?? '',
				$row['field_value'] ?? '',
				$row['attempts'] ?? '',
				$row['error_message'] ?? '',
			]);
		}
	}

	private static function stringify_value($value) {
		if (is_array($value) || is_object($value)) {
			$value = wp_json_encode($value);
		} elseif (is_bool($value)) {
			$value = $value ? 'true' : 'false';
		} elseif ($value === null) {
			$value = '';
		} else {
			$value = (string) $value;
		}

		$value = wp_strip_all_tags((string) $value);
		return function_exists('mb_substr') ? mb_substr($value, 0, 20000) : substr($value, 0, 20000);
	}

	private static function next_report_timestamp($settings) {
		$hour = isset($settings['error_report']['daily_hour'])
			? max(0, min(23, absint($settings['error_report']['daily_hour'])))
			: 6;

		$timezone = wp_timezone();
		$now = new DateTimeImmutable('now', $timezone);
		$next = $now->setTime($hour, 0, 0);
		if ($next->getTimestamp() <= $now->getTimestamp()) {
			$next = $next->modify('+1 day');
		}

		return $next->getTimestamp();
	}
}
