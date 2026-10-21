<?php
if (!defined('ABSPATH')) { exit; }

/** Generates private CSVs in bounded requests, then downloads the finished file. */
class AAC_Member_Portal_Member_Export {
	const AJAX = 'aac_member_export_step';
	const CLEANUP = 'aac_member_export_cleanup';
	const TTL = DAY_IN_SECONDS;
	private $database;

	public function __construct($database) {
		$this->database = $database;
		add_action('wp_ajax_' . self::AJAX, [$this, 'handle_step']);
		add_action('admin_post_aac_member_export_download', [$this, 'download']);
		add_action(self::CLEANUP, [$this, 'cleanup'], 10, 2);
	}

	private function key($user_id) { return 'aac_member_export_job_' . (int) $user_id; }
	public function job($user_id) {
		$job = get_option($this->key($user_id), []);
		return is_array($job) ? $job : [];
	}

	public function start($user_id, $ids, $headers) {
		$lock = $this->key($user_id) . '_start';
		if ((int) get_option($lock, 0) < time() - 30) delete_option($lock);
		if (!add_option($lock, time(), '', false)) return new WP_Error('export_busy', 'An export is already starting. Reload the export page to resume.');
		try { return $this->create_job($user_id, $ids, $headers); }
		finally { delete_option($lock); }
	}

	private function create_job($user_id, $ids, $headers) {
		$old = $this->job($user_id);
		if (($old['status'] ?? '') === 'running' && ($old['expires'] ?? 0) > time() && is_file($old['path'] ?? '')) return $old;
		// Do not put member CSVs in uploads or any other web-accessible directory.
		$directory = realpath(sys_get_temp_dir());
		foreach ([ABSPATH, $_SERVER['DOCUMENT_ROOT'] ?? '', defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : ''] as $root) {
			$root = $root !== '' ? realpath($root) : false;
			if ($root && $directory && ($directory === $root || strpos($directory . DIRECTORY_SEPARATOR, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) === 0)) {
				return new WP_Error('export_storage', 'Private export storage is unavailable. No member data was written.');
			}
		}
		if (!$directory || !is_writable($directory)) return new WP_Error('export_storage', 'Private export storage is not writable.');
		$path = tempnam($directory, 'aac-export-');
		if (!$path) return new WP_Error('export_storage', 'Could not create the export file.');
		if (!chmod($path, 0600)) { unlink($path); return new WP_Error('export_storage', 'Could not secure the private export file.'); }
		$file = fopen($path, 'wb');
		if (!$file || fputcsv($file, $headers, ',', '"', '') === false) {
			if (is_resource($file)) fclose($file);
			unlink($path);
			return new WP_Error('export_storage', 'Could not write the CSV header.');
		}
		$bytes = ftell($file);
		fclose($file);
		$job = ['id' => wp_generate_uuid4(), 'owner' => (int) $user_id, 'path' => $path, 'headers' => array_values($headers),
			'ids' => array_values(array_unique(array_map('intval', $ids))), 'next' => 0, 'rows' => 0, 'bytes' => $bytes,
			'status' => 'running', 'created' => time(), 'expires' => time() + self::TTL, 'error' => ''];
		if (!update_option($this->key($user_id), $job, false)) {
			unlink($path);
			return new WP_Error('export_storage', 'Could not save export progress.');
		}
		if (!empty($old['path']) && is_file($old['path'])) unlink($old['path']);
		wp_schedule_single_event($job['expires'], self::CLEANUP, [(int) $user_id, $job['id']]);
		return $job;
	}

	public function step($user_id, $job_id) {
		$job = $this->job($user_id);
		if (($job['id'] ?? '') !== $job_id || ($job['owner'] ?? 0) !== (int) $user_id) return new WP_Error('export_job', 'This export is no longer available. Start a new export.');
		if (($job['expires'] ?? 0) <= time() || !is_file($job['path'] ?? '')) return new WP_Error('export_expired', 'The export expired. Start a new export.');
		if ($job['status'] !== 'running') return $job;
		$file = fopen($job['path'], 'c+b');
		if (!$file) return new WP_Error('export_storage', 'Could not open the export file.');
		if (!flock($file, LOCK_EX | LOCK_NB)) { fclose($file); return $job; }
		try {
			// Another request may have committed a batch while this request opened the file.
			wp_cache_delete($this->key($user_id), 'options');
			$job = $this->job($user_id);
			if (($job['id'] ?? '') !== $job_id || $job['status'] !== 'running') return $job;
			if (!ftruncate($file, $job['bytes']) || fseek($file, $job['bytes']) !== 0) throw new RuntimeException('Could not restore the last completed export batch.');
			$deadline = microtime(true) + 5;
			$batch = array_slice($job['ids'], $job['next'], 250);
			if ($batch && function_exists('update_meta_cache')) update_meta_cache('user', $batch);
			foreach ($batch as $id) {
				$row = $this->database->build_member_export_row($id, $job['headers']);
				if ($row) {
					$values = array_map(static function ($header) use ($row) { return $row[$header] ?? ''; }, $job['headers']);
					if (fputcsv($file, $values, ',', '"', '') === false) throw new RuntimeException('Could not write a member to the export file.');
					$job['rows']++;
				}
				$job['next']++;
				if (microtime(true) >= $deadline) break;
			}
			if (!fflush($file)) throw new RuntimeException('Could not flush the export batch to storage.');
			$job['bytes'] = ftell($file);
			if ($job['next'] >= count($job['ids'])) $job['status'] = 'ready';
			if (!update_option($this->key($user_id), $job, false)) throw new RuntimeException('Could not save export progress.');
			return $job;
		} catch (Throwable $error) {
			$job['status'] = 'failed';
			$job['error'] = $error->getMessage();
			update_option($this->key($user_id), $job, false);
			return $job;
		} finally { flock($file, LOCK_UN); fclose($file); }
	}

	public function handle_step() {
		if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Permission denied.'], 403);
		check_ajax_referer(self::AJAX);
		$id = sanitize_text_field(wp_unslash($_POST['job'] ?? ''));
		$job = $this->step(get_current_user_id(), $id);
		if (is_wp_error($job)) wp_send_json_error(['message' => $job->get_error_message()], 400);
		wp_send_json_success($this->progress($job));
	}

	private function progress($job) {
		return ['status' => $job['status'], 'processed' => $job['next'], 'total' => count($job['ids']), 'rows' => $job['rows'], 'error' => $job['error'],
			'download' => $job['status'] === 'ready' ? add_query_arg(['action' => 'aac_member_export_download', 'job' => $job['id'], '_wpnonce' => wp_create_nonce('aac_member_export_download')], admin_url('admin-post.php')) : ''];
	}

	public function render() {
		$job = $this->job(get_current_user_id());
		if (!$job || ($job['expires'] ?? 0) <= time()) return;
		$config = ['url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce(self::AJAX), 'id' => $job['id'], 'progress' => $this->progress($job)];
		?>
		<style>#aac-export-progress [hidden] { display: none !important; }</style>
		<div class="notice notice-info" id="aac-export-progress" role="status" aria-live="polite" style="padding:16px">
			<strong>Member CSV export</strong>
			<p data-export-status></p>
			<progress data-export-meter style="width:100%;max-width:700px"></progress>
			<p><a class="button button-primary" data-export-download hidden>Download CSV</a> <button class="button" type="button" data-export-resume hidden>Resume export</button></p>
			<p>Keep this page open while the file is prepared. You can return here to resume. The download is available for 24 hours.</p>
		</div>
		<script>
		(() => {
			const config = <?php echo wp_json_encode($config); ?>;
			const box = document.getElementById('aac-export-progress');
			const status = box.querySelector('[data-export-status]');
			const meter = box.querySelector('[data-export-meter]');
			const download = box.querySelector('[data-export-download]');
			const resume = box.querySelector('[data-export-resume]');
			let busy = false;
			const show = data => {
				meter.max = Math.max(1, data.total); meter.value = data.processed;
				status.textContent = data.status === 'failed' ? `Export stopped: ${data.error} Start a new export to retry.` : `${data.processed.toLocaleString()} of ${data.total.toLocaleString()} members processed; ${data.rows.toLocaleString()} CSV rows. ${data.status === 'ready' ? 'Ready to download.' : 'Preparing file…'}`;
				download.hidden = data.status !== 'ready';
				if (data.download) download.href = data.download;
			};
			const run = async () => {
				if (busy) return; busy = true; resume.hidden = true;
				try {
					while (true) {
						const controller = new AbortController();
						const timeout = setTimeout(() => controller.abort(), 25000);
						let result;
						try {
							const response = await fetch(config.url, {method: 'POST', credentials: 'same-origin', signal: controller.signal,
								body: new URLSearchParams({action: '<?php echo esc_js(self::AJAX); ?>', _ajax_nonce: config.nonce, job: config.id})});
							result = await response.json();
							if (!response.ok || !result.success) throw new Error(result.data?.message || 'Export request failed.');
						} finally { clearTimeout(timeout); }
						show(result.data);
						if (result.data.status !== 'running') break;
						await new Promise(resolve => setTimeout(resolve, 150));
					}
				} catch (error) { status.textContent = 'Export paused. Progress is saved. Click Resume export to retry, or reload this page if your session expired.'; resume.hidden = false; }
				finally { busy = false; }
			};
			resume.addEventListener('click', run);
			show(config.progress);
			if (config.progress.status === 'running') run();
		})();
		</script>
		<?php
	}

	public function download() {
		if (!current_user_can('manage_options')) wp_die('Permission denied.');
		check_admin_referer('aac_member_export_download');
		$job = $this->job(get_current_user_id());
		if (!$job || $job['id'] !== sanitize_text_field(wp_unslash($_GET['job'] ?? '')) || $job['status'] !== 'ready' || $job['expires'] <= time() || !is_file($job['path'])) wp_die('This export is not ready or has expired. Start a new export.');
		$file = fopen($job['path'], 'rb');
		if (!$file) wp_die('The export file is unavailable. Start a new export.');
		while (ob_get_level()) ob_end_clean();
		nocache_headers();
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="aac-member-database-export-' . gmdate('Y-m-d-His', $job['created']) . '.csv"');
		header('Content-Length: ' . $job['bytes']);
		header('X-Content-Type-Options: nosniff');
		fpassthru($file); fclose($file); exit;
	}

	public function cleanup($user_id, $job_id) {
		$job = $this->job($user_id);
		if (($job['id'] ?? '') !== $job_id || ($job['expires'] ?? 0) > time()) return;
		if (is_file($job['path'] ?? '')) unlink($job['path']);
		delete_option($this->key($user_id));
	}
}
