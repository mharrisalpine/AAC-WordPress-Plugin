<?php
if (!defined('ABSPATH')) { exit; }

/** Sequential, resumable processing of the current staged member queue. */
final class AAC_Member_Portal_Import_Background {
	const STATE = 'aac_import_background_state';
	const PAUSE = 'aac_import_background_pause';
	const LOCK = 'aac_import_background_lock';
	const TICK = 'aac_import_background_tick';
	private $manager;
	public function __construct($manager) {
		$this->manager = $manager;
		add_action('wp_ajax_nopriv_aac_import_worker', [$this, 'endpoint']);
		add_action('wp_ajax_aac_import_worker', [$this, 'endpoint']);
		add_action(self::TICK, [$this, 'tick']);
		add_action('admin_post_aac_import_background', [$this, 'control']);
		add_action('aac_import_background_controls', [$this, 'render']);
		add_action('admin_notices', [$this, 'notice']);
	}
	/** A per-job secret authorizes only continuation of the existing queue. */
	public function endpoint() {
		$s = get_option(self::STATE, []);
		$token = (string) wp_unslash($_POST['token'] ?? '');
		if (empty($s['token']) || !hash_equals($s['token'], $token)) { wp_die('Forbidden', '', ['response' => 403]); }
		$this->tick();
		wp_die();
	}
	private function dispatch($s) {
		if (empty($s['token'])) { return; } // Older queued jobs retain cron compatibility.
		$result = wp_remote_post(admin_url('admin-ajax.php'), [
			'timeout' => 1, 'blocking' => false,
			'body' => ['action' => 'aac_import_worker', 'token' => $s['token']],
		]);
		if (is_wp_error($result)) {
			$s = get_option(self::STATE, []);
			$this->log($s, 'Direct continuation unavailable; cron fallback will retry.');
			$this->save($s);
		}
	}
	public static function pause_requested() {
		wp_cache_delete(self::PAUSE, 'options');
		wp_cache_delete('notoptions', 'options');
		return (bool) get_option(self::PAUSE, false);
	}
	public static function memory_available() {
		$limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
		return $limit <= 0 || memory_get_usage(true) < $limit * 0.8;
	}
	public static function active() {
		$s = get_option(self::STATE, []);
		return in_array($s['status'] ?? '', ['running', 'pausing'], true) || get_option(self::LOCK);
	}
	private function save($s) { update_option(self::STATE, $s, false); }
	private function log(&$s, $message) {
		$s['log'][] = gmdate('c') . ' ' . $message;
		$s['log'] = array_slice($s['log'], -200);
		$s['updated'] = time();
	}
	private function schedule() {
		if (!wp_next_scheduled(self::TICK)) {
			$result = wp_schedule_single_event(time() + 10, self::TICK, [], true);
			if (is_wp_error($result) || !$result) { throw new RuntimeException('Could not schedule the next batch. Check WordPress cron.'); }
		}
	}
	public function control() {
		if (!current_user_can('manage_options')) { wp_die('Administrator access required.'); }
		check_admin_referer('aac_import_background');
		$s = get_option(self::STATE, []);
		$command = sanitize_key(wp_unslash($_POST['command'] ?? ''));
		if ($command === 'pause' && ($s['status'] ?? '') === 'running') {
			update_option(self::PAUSE, true, false);
			// Do not overwrite counters while the worker owns the state. It reads PAUSE between rows.
			if (!get_option(self::LOCK)) { $s['status'] = 'paused'; $this->log($s, 'Paused.'); $this->save($s); }
		} elseif ($command === 'start' && !self::active() && !AAC_Member_Portal_Import_Rollback::active()) {
			delete_option(self::PAUSE);
			$s = ['status' => 'running', 'dry_run' => !empty($_POST['dry_run']), 'processed' => 0, 'errors' => 0, 'remaining' => null, 'log' => [], 'token' => wp_generate_password(48, false, false), 'mode' => ($_POST['mode'] ?? '') === 'profile' ? 'profile' : 'full', 'started' => time(), 'work_seconds' => 0];
			$this->log($s, 'Started processing remaining staged rows. WordPress email suppressed during worker requests.');
			$this->save($s);
			try { $this->schedule(); $this->dispatch($s); } catch (Throwable $e) { $s['status'] = 'failed'; $this->log($s, $e->getMessage()); $this->save($s); }
		}
		wp_safe_redirect(admin_url('admin.php?page=' . AAC_Member_Portal_Import_Manager::PAGE_SLUG));
		exit;
	}
	public function tick() {
		$s = get_option(self::STATE, []);
		if (($s['status'] ?? '') !== 'running') { return; }
		// Atomic database-backed lock: never overlap a previous worker.
		if (!add_option(self::LOCK, time(), '', false)) {
			if (time() - (int) get_option(self::LOCK) > 900) {
				$s['status'] = 'failed';
				$this->log($s, 'Worker stopped responding. Review the last member and family links before an administrator clears the worker lock.');
				$this->save($s);
			}
			return;
		}
		// A concurrent request may have finished between our first read and lock acquisition.
		wp_cache_delete(self::STATE, 'options');
		$s = get_option(self::STATE, []);
		if (($s['status'] ?? '') !== 'running') { delete_option(self::LOCK); return; }
		$finished = false;
		register_shutdown_function(function () use (&$finished) {
			if ($finished) { return; }
			$s = get_option(self::STATE, []);
			$s['status'] = 'failed';
			$this->log($s, 'Worker terminated unexpectedly. Some writes may have completed. Review row errors and family links before restarting.');
			$this->save($s);
			delete_option(self::LOCK);
		});
		$block_mail = static function () { return true; };
		add_filter('pre_wp_mail', $block_mail, PHP_INT_MAX);
		try {
			// Leave a watchdog event behind if the PHP process is killed outright.
			wp_schedule_single_event(time() + 960, self::TICK);
			$started = microtime(true);
			$php_limit = (int) ini_get('max_execution_time');
			$budget = $php_limit > 0 ? max(1, min(20, $php_limit - 10)) : 20;
			$deadline = $started + $budget;
			$before = (int) $s['processed'];
			$gap = isset($s['last_finished']) ? max(0, $started - $s['last_finished']) : 0;
			$this->log($s, sprintf('Worker started; scheduling gap %.1fs; mode %s.', $gap, $s['mode'] ?? 'full'));
			$this->save($s);
			do {
				$result = $this->manager->sync_staged_rows(!empty($s['dry_run']), 10, $s['mode'] ?? 'full', $deadline);
				if (is_wp_error($result)) { throw new RuntimeException($result->get_error_message()); }
				$s['processed'] += $result['synced'] + $result['validated'];
				$s['errors'] += $result['errors'];
				$s['remaining'] = $result['remaining'];
				$pause = self::pause_requested();
				if ($result['errors']) {
					$s['status'] = 'failed';
					$this->log($s, 'Stopped on row error. Download Error CSV before continuing.');
				} elseif (!$result['remaining']) { $s['status'] = 'completed'; }
				elseif ($pause) { $s['status'] = 'paused'; }
				$this->save($s);
				// Bound memory as well as time; no concurrent member writes.
			} while ($s['status'] === 'running' && microtime(true) < $deadline && self::memory_available() && $s['processed'] - $before < 100 && $result['synced'] + $result['validated'] > 0);
			$elapsed = microtime(true) - $started;
			$s['work_seconds'] = ($s['work_seconds'] ?? 0) + $elapsed;
			$s['last_finished'] = microtime(true);
			$this->log($s, sprintf('Worker finished: %d records in %.2fs; %d remaining; %d errors.', $s['processed'] - $before, $elapsed, $s['remaining'], $s['errors']));
		} catch (Throwable $e) {
			$s['status'] = 'failed';
			$this->log($s, sanitize_text_field($e->getMessage()));
		} finally {
			// Keep mail suppressed through shutdown hooks for this worker request.
			$this->save($s);
			wp_clear_scheduled_hook(self::TICK);
			if ($s['status'] === 'running') {
				try { $this->schedule(); } catch (Throwable $e) { $s['status'] = 'failed'; $this->log($s, $e->getMessage()); $this->save($s); }
			}
			delete_option(self::LOCK);
			$finished = true;
			if ($s['status'] === 'running') { $this->dispatch($s); }
		}
	}
	public function notice() {
		if (!current_user_can('manage_options')) { return; }
		$s = get_option(self::STATE, []);
		if (!in_array($s['status'] ?? '', ['completed', 'failed'], true)) { return; }
		echo '<div class="notice notice-' . ($s['status'] === 'failed' ? 'error' : 'success') . '"><p>Member import ' . esc_html($s['status']) . '. <a href="' . esc_url(admin_url('admin.php?page=' . AAC_Member_Portal_Import_Manager::PAGE_SLUG)) . '">Review import results and error log</a>.</p></div>';
	}
	public function render() {
		$s = get_option(self::STATE, []);
		echo '<h3>Automatic background processing</h3><p>Processes up to 100 records per worker, checking a 20-second time budget between records. Each record keeps its own rollback checkpoint. Direct background continuation reduces cron waits; WordPress cron remains a fallback. You may close this page. Other import types still run during upload. Refresh for progress.</p>';
		echo '<p>Status: <strong>' . esc_html($s['status'] ?? 'Not started') . '</strong> | Processed: ' . (int) ($s['processed'] ?? 0) . ' | Errors: ' . (int) ($s['errors'] ?? 0) . ' | Remaining: ' . esc_html(isset($s['remaining']) ? (string) $s['remaining'] : '—') . '</p>';
		if (!empty($s['started']) && !empty($s['processed'])) {
			$minutes = max(1, ($s['last_finished'] ?? time()) - $s['started']) / 60;
			$eta = !empty($s['remaining']) ? (int) ceil($s['remaining'] / ($s['processed'] / $minutes)) : 0;
			echo '<p>Estimated remaining: ' . $eta . ' minutes at the average rate.</p>';
			echo '<p>Average: ' . esc_html(number_format($s['processed'] / $minutes, 1)) . ' records/minute. Active processing: ' . esc_html(number_format($s['work_seconds'] ?? 0, 1)) . ' seconds. Mode: ' . esc_html($s['mode'] ?? 'full') . '.</p>';
		}
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="aac_import_background">';
		wp_nonce_field('aac_import_background');
		if (self::active()) { echo '<button class="button" name="command" value="pause">Pause after current batch</button>'; }
		else { echo '<p><label>Import mode <select name="mode"><option value="full">Full member import (accounts, membership and family links)</option><option value="profile">Profile only (existing accounts; preserve memberships and family links)</option></select></label></p><p>Profile only updates nonblank supplied profile fields. It requires an existing account and does not create memberships or change billing.</p><label><input type="checkbox" name="dry_run" value="1" checked> Validate only (dry run)</label> <button class="button button-primary" name="command" value="start">Process remaining rows automatically</button>'; }
		echo '</form><p>Completion and failure notifications appear in WordPress admin. No notification emails are sent. Download Error CSV below for individual row errors.</p><details><summary>Processing log (latest 200 entries)</summary><pre style="white-space:pre-wrap">' . esc_html(implode("\n", $s['log'] ?? [])) . '</pre></details>';
	}
}
