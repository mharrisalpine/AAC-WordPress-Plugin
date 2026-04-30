<?php

if (!defined('ABSPATH')) {
	exit;
}

class AAC_Salesforce_Sync_Queue {
	private static $runner_kicked = false;

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'aac_salesforce_sync_queue';
	}

	public static function enqueue($job_type, $object_type, $object_id = 0, $external_key = '', $payload = [], $source = 'wordpress', $available_at = null) {
		global $wpdb;

		if (!$wpdb) {
			return false;
		}

		$settings = AAC_Salesforce_Sync_Settings::get_settings();
		$now = current_time('mysql');
		$available_at = $available_at ?: $now;

		$inserted = (bool) $wpdb->insert(
			self::table_name(),
			[
				'job_type' => sanitize_key($job_type),
				'object_type' => sanitize_key($object_type),
				'object_id' => absint($object_id),
				'external_key' => sanitize_text_field((string) $external_key),
				'payload' => wp_json_encode($payload),
				'status' => 'pending',
				'attempts' => 0,
				'max_attempts' => (int) $settings['general']['max_attempts'],
				'available_at' => $available_at,
				'locked_at' => null,
				'last_error' => null,
				'source' => sanitize_key($source),
				'created_at' => $now,
				'updated_at' => $now,
			],
			['%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
		);

		if (
			$inserted &&
			!empty($settings['general']['enabled']) &&
			!self::$runner_kicked &&
			class_exists('AAC_Salesforce_Sync_Worker')
		) {
			self::$runner_kicked = true;
			AAC_Salesforce_Sync_Worker::kick_queue_runner();
		}

		return $inserted;
	}

	public static function claim_next($force = false, $allowed_job_types = []) {
		global $wpdb;

		if (!$wpdb) {
			return null;
		}

		$table = self::table_name();
		$now = current_time('mysql');
		$allowed_job_types = array_values(array_filter(array_map('sanitize_key', (array) $allowed_job_types)));
		$type_sql = '';
		if ($allowed_job_types) {
			$placeholders = implode(', ', array_fill(0, count($allowed_job_types), '%s'));
			$type_sql = $wpdb->prepare(" AND job_type IN ({$placeholders})", ...$allowed_job_types);
		}
		if ($force) {
			$job = $wpdb->get_row(
				$wpdb->prepare("SELECT * FROM {$table} WHERE status = %s{$type_sql} ORDER BY id ASC LIMIT 1", 'pending'),
				ARRAY_A
			); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		} else {
			$job = $wpdb->get_row(
				$wpdb->prepare("SELECT * FROM {$table} WHERE status = %s AND available_at <= %s{$type_sql} ORDER BY id ASC LIMIT 1", 'pending', $now),
				ARRAY_A
			); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		if (!$job) {
			return null;
		}

		$updated = $wpdb->update(
			$table,
			[
				'status' => 'processing',
				'locked_at' => $now,
				'updated_at' => $now,
			],
			[
				'id' => (int) $job['id'],
				'status' => 'pending',
			],
			['%s', '%s', '%s'],
			['%d', '%s']
		);

		if (1 !== (int) $updated) {
			return null;
		}

		$job['status'] = 'processing';
		$job['locked_at'] = $now;
		return $job;
	}

	public static function complete($job_id) {
		self::update_job((int) $job_id, [
			'status' => 'completed',
			'locked_at' => null,
			'updated_at' => current_time('mysql'),
		]);
	}

	public static function release_with_retry($job, $error_message) {
		global $wpdb;

		if (!$wpdb || empty($job['id'])) {
			return;
		}

		$attempts = (int) $job['attempts'] + 1;
		$max_attempts = (int) $job['max_attempts'];
		$status = $attempts >= $max_attempts ? 'dead_letter' : 'pending';
		$delay = $attempts >= $max_attempts ? 0 : self::retry_delay_seconds($attempts);
		$available_at = gmdate('Y-m-d H:i:s', time() + $delay);

		$wpdb->update(
			self::table_name(),
			[
				'status' => $status,
				'attempts' => $attempts,
				'available_at' => $available_at,
				'locked_at' => null,
				'last_error' => wp_strip_all_tags((string) $error_message),
				'updated_at' => current_time('mysql'),
			],
			['id' => (int) $job['id']],
			['%s', '%d', '%s', '%s', '%s', '%s'],
			['%d']
		);
	}

	public static function retry_job($job_id) {
		self::update_job((int) $job_id, [
			'status' => 'pending',
			'attempts' => 0,
			'available_at' => current_time('mysql'),
			'locked_at' => null,
			'last_error' => null,
			'updated_at' => current_time('mysql'),
		]);
	}

	public static function make_pending_jobs_available_now() {
		global $wpdb;

		if (!$wpdb) {
			return 0;
		}

		$now = current_time('mysql');
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE " . self::table_name() . " SET available_at = %s, updated_at = %s WHERE status = %s",
				$now,
				$now,
				'pending'
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is internal and values are prepared above.

		return max(0, (int) $updated);
	}

	public static function reset_stale_locks($minutes = 15) {
		global $wpdb;

		if (!$wpdb) {
			return;
		}

		$table = self::table_name();
		$cutoff = gmdate('Y-m-d H:i:s', time() - (absint($minutes) * MINUTE_IN_SECONDS));

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = %s, locked_at = NULL, updated_at = %s WHERE status = %s AND locked_at IS NOT NULL AND locked_at < %s",
				'pending',
				current_time('mysql'),
				'processing',
				$cutoff
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function get_stats() {
		global $wpdb;

		if (!$wpdb) {
			return [];
		}

		$table = self::table_name();
		$rows = $wpdb->get_results("SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status", ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$stats = [
			'pending' => 0,
			'processing' => 0,
			'completed' => 0,
			'dead_letter' => 0,
		];

		foreach ((array) $rows as $row) {
			$stats[$row['status']] = (int) $row['total'];
		}

		return $stats;
	}

	public static function list_jobs($limit = 50) {
		global $wpdb;

		if (!$wpdb) {
			return [];
		}

		$limit = max(1, min(200, absint($limit)));
		$table = self::table_name();
		return (array) $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC LIMIT {$limit}", ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function get_job($job_id) {
		global $wpdb;

		if (!$wpdb) {
			return null;
		}

		return $wpdb->get_row(
			$wpdb->prepare("SELECT * FROM " . self::table_name() . " WHERE id = %d", absint($job_id)),
			ARRAY_A
		);
	}

	public static function clear_all_jobs() {
		global $wpdb;

		if (!$wpdb) {
			return 0;
		}

		$deleted = $wpdb->query("TRUNCATE TABLE " . self::table_name()); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- internal table name only.
		if (false === $deleted) {
			return 0;
		}

		return is_numeric($deleted) ? (int) $deleted : 0;
	}

	private static function update_job($job_id, $fields) {
		global $wpdb;

		if (!$wpdb) {
			return;
		}

		$format = [];
		foreach ($fields as $value) {
			if (is_int($value)) {
				$format[] = '%d';
			} else {
				$format[] = '%s';
			}
		}

		$wpdb->update(self::table_name(), $fields, ['id' => $job_id], $format, ['%d']);
	}

	private static function retry_delay_seconds($attempts) {
		$map = [
			1 => 5 * MINUTE_IN_SECONDS,
			2 => 15 * MINUTE_IN_SECONDS,
			3 => HOUR_IN_SECONDS,
			4 => 6 * HOUR_IN_SECONDS,
		];

		return $map[$attempts] ?? DAY_IN_SECONDS;
	}
}
