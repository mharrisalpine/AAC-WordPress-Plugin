<?php
if (!defined('ABSPATH')) { exit; }

/** Sequential, resumable processing of the current staged member queue. */
final class AAC_Member_Portal_Import_Background {
	const STATE = 'aac_import_background_state';
	const LOCK = 'aac_import_background_lock';
	const TICK = 'aac_import_background_tick';
	private $manager;
	public function __construct($manager) {
		$this->manager = $manager;
		add_action(self::TICK, [$this, 'tick']);
		add_action('admin_post_aac_import_background', [$this, 'control']);
		add_action('aac_import_background_controls', [$this, 'render']);
		add_action('admin_notices', [$this, 'notice']);
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
			$s['status'] = get_option(self::LOCK) ? 'pausing' : 'paused';
			$this->log($s, 'Pause requested. An in-progress batch will finish first.');
			$this->save($s);
		} elseif ($command === 'start' && !self::active() && !AAC_Member_Portal_Import_Rollback::active()) {
			$s = ['status' => 'running', 'dry_run' => !empty($_POST['dry_run']), 'processed' => 0, 'errors' => 0, 'remaining' => null, 'log' => []];
			$this->log($s, 'Started processing remaining staged rows. WordPress email suppressed during worker requests.');
			$this->save($s);
			try { $this->schedule(); } catch (Throwable $e) { $s['status'] = 'failed'; $this->log($s, $e->getMessage()); $this->save($s); }
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
			$result = $this->manager->sync_staged_rows(!empty($s['dry_run']), 5);
			if (is_wp_error($result)) { throw new RuntimeException($result->get_error_message()); }
			$s['processed'] += $result['synced'] + $result['validated'];
			$s['errors'] += $result['errors'];
			$s['remaining'] = $result['remaining'];
			$this->log($s, sprintf('Batch: %d synced, %d validated, %d errors, %d family links, %d remaining.', $result['synced'], $result['validated'], $result['errors'], $result['family_links'], $result['remaining']));
			if ($result['errors']) {
				$s['status'] = 'failed';
				$this->log($s, 'Stopped on errors. Download Error CSV and review family-link results before continuing.');
			} elseif (!$result['remaining']) { $s['status'] = 'completed'; }
			elseif ((get_option(self::STATE, [])['status'] ?? '') === 'pausing') { $s['status'] = 'paused'; }
		} catch (Throwable $e) {
			$s['status'] = 'failed';
			$this->log($s, sanitize_text_field($e->getMessage()));
		} finally {
			remove_filter('pre_wp_mail', $block_mail, PHP_INT_MAX);
			$this->save($s);
			delete_option(self::LOCK);
			$finished = true;
			wp_clear_scheduled_hook(self::TICK);
			if ($s['status'] === 'running') {
				try { $this->schedule(); } catch (Throwable $e) { $s['status'] = 'failed'; $this->log($s, $e->getMessage()); $this->save($s); }
			}
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
		echo '<h3>Automatic background processing</h3><p>Processes staged current-member rows in batches of 5. You may close this page. WordPress cron must be running; low-traffic sites should use a server cron. Other import types still run during upload. Refresh this page for progress.</p>';
		echo '<p>Status: <strong>' . esc_html($s['status'] ?? 'Not started') . '</strong> | Processed: ' . (int) ($s['processed'] ?? 0) . ' | Errors: ' . (int) ($s['errors'] ?? 0) . ' | Remaining: ' . esc_html(isset($s['remaining']) ? (string) $s['remaining'] : '—') . '</p>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="aac_import_background">';
		wp_nonce_field('aac_import_background');
		if (self::active()) { echo '<button class="button" name="command" value="pause">Pause after current batch</button>'; }
		else { echo '<label><input type="checkbox" name="dry_run" value="1" checked> Validate only (dry run)</label> <button class="button button-primary" name="command" value="start">Process remaining rows automatically</button>'; }
		echo '</form><p>Completion and failure notifications appear in WordPress admin. No notification emails are sent. Download Error CSV below for individual row errors.</p><details><summary>Processing log (latest 200 entries)</summary><pre style="white-space:pre-wrap">' . esc_html(implode("\n", $s['log'] ?? [])) . '</pre></details>';
	}
}
