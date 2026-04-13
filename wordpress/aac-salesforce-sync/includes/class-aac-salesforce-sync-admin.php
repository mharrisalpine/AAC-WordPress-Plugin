<?php

if (!defined('ABSPATH')) {
	exit;
}

class AAC_Salesforce_Sync_Admin {
	const NOTICE_QUERY_ARG = 'aac_salesforce_sync_notice';

	private $worker;

	public function __construct(AAC_Salesforce_Sync_Worker $worker) {
		$this->worker = $worker;

		add_action('admin_menu', [$this, 'register_admin_page']);
		add_action('admin_post_aac_salesforce_sync_save_settings', [$this, 'handle_save_settings']);
		add_action('admin_post_aac_salesforce_sync_run_queue', [$this, 'handle_run_queue']);
		add_action('admin_post_aac_salesforce_sync_retry_job', [$this, 'handle_retry_job']);
	}

	public function register_admin_page() {
		$parent_slug = class_exists('AAC_Member_Portal_Admin') ? AAC_Member_Portal_Admin::MENU_SLUG : 'tools.php';
		$page_title = 'AAC Salesforce Sync';
		$menu_title = 'Salesforce Sync';

		if ('tools.php' === $parent_slug) {
			add_management_page($page_title, $menu_title, 'manage_options', AAC_Salesforce_Sync_Settings::PAGE_SLUG, [$this, 'render_admin_page']);
			return;
		}

		add_submenu_page($parent_slug, $page_title, $menu_title, 'manage_options', AAC_Salesforce_Sync_Settings::PAGE_SLUG, [$this, 'render_admin_page']);
	}

	public function handle_save_settings() {
		$this->assert_admin_request();
		check_admin_referer('aac_salesforce_sync_save_settings');

		$input = isset($_POST[AAC_Salesforce_Sync_Settings::OPTION_KEY]) ? wp_unslash($_POST[AAC_Salesforce_Sync_Settings::OPTION_KEY]) : [];
		AAC_Salesforce_Sync_Settings::update_settings($input);

		$this->redirect_with_notice('settings-saved');
	}

	public function handle_run_queue() {
		$this->assert_admin_request();
		check_admin_referer('aac_salesforce_sync_run_queue');

		$count = $this->worker->process_queue();
		$this->redirect_with_notice('queue-ran-' . (int) $count);
	}

	public function handle_retry_job() {
		$this->assert_admin_request();
		check_admin_referer('aac_salesforce_sync_retry_job');

		$job_id = isset($_GET['job_id']) ? absint(wp_unslash($_GET['job_id'])) : 0;
		if ($job_id > 0) {
			AAC_Salesforce_Sync_Queue::retry_job($job_id);
		}

		$this->redirect_with_notice('job-retried');
	}

	public function render_admin_page() {
		if (!current_user_can('manage_options')) {
			return;
		}

		$settings = AAC_Salesforce_Sync_Settings::get_settings();
		$stats = AAC_Salesforce_Sync_Queue::get_stats();
		$jobs = AAC_Salesforce_Sync_Queue::list_jobs(30);
		$notice = isset($_GET[self::NOTICE_QUERY_ARG]) ? sanitize_text_field(wp_unslash($_GET[self::NOTICE_QUERY_ARG])) : '';
		?>
		<div class="wrap">
			<h1>AAC Salesforce Sync</h1>
			<p>Queue-driven Salesforce-first sync layer for AAC Member Portal and Paid Memberships Pro.</p>

			<?php if ($notice) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html($this->notice_message($notice)); ?></p></div>
			<?php endif; ?>

			<div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;align-items:start;">
				<div>
					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="background:#fff;padding:24px;border:1px solid #dcdcde;border-radius:8px;">
						<input type="hidden" name="action" value="aac_salesforce_sync_save_settings" />
						<?php wp_nonce_field('aac_salesforce_sync_save_settings'); ?>

						<h2 style="margin-top:0;">General</h2>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row">Enable outbound sync</th>
								<td><label><input type="checkbox" name="<?php echo esc_attr(AAC_Salesforce_Sync_Settings::OPTION_KEY . '[general][enabled]'); ?>" value="1" <?php checked(!empty($settings['general']['enabled'])); ?> /> Queue and process Salesforce sync jobs</label></td>
							</tr>
							<tr>
								<th scope="row"><label>Batch size</label></th>
								<td><input type="number" min="1" max="50" name="<?php echo esc_attr(AAC_Salesforce_Sync_Settings::OPTION_KEY . '[general][batch_size]'); ?>" value="<?php echo esc_attr($settings['general']['batch_size']); ?>" /></td>
							</tr>
							<tr>
								<th scope="row"><label>Max attempts</label></th>
								<td><input type="number" min="1" max="20" name="<?php echo esc_attr(AAC_Salesforce_Sync_Settings::OPTION_KEY . '[general][max_attempts]'); ?>" value="<?php echo esc_attr($settings['general']['max_attempts']); ?>" /></td>
							</tr>
						</table>

						<h2>Salesforce Connection</h2>
						<table class="form-table" role="presentation">
							<?php $this->render_text_field('Token URL', 'salesforce', 'token_url', $settings['salesforce']['token_url']); ?>
							<?php $this->render_text_field('Instance URL', 'salesforce', 'instance_url', $settings['salesforce']['instance_url']); ?>
							<?php $this->render_text_field('API version', 'salesforce', 'api_version', $settings['salesforce']['api_version']); ?>
							<?php $this->render_text_field('Client ID', 'salesforce', 'client_id', $settings['salesforce']['client_id']); ?>
							<?php $this->render_password_field('Client Secret', 'salesforce', 'client_secret', $settings['salesforce']['client_secret']); ?>
						</table>

						<h2>Object Mapping</h2>
						<table class="form-table" role="presentation">
							<?php $this->render_text_field('Contact object', 'salesforce', 'contact_object', $settings['salesforce']['contact_object']); ?>
							<?php $this->render_text_field('Membership object', 'salesforce', 'membership_object', $settings['salesforce']['membership_object']); ?>
							<?php $this->render_text_field('Transaction object', 'salesforce', 'transaction_object', $settings['salesforce']['transaction_object']); ?>
							<?php $this->render_text_field('Contact external ID field', 'salesforce', 'contact_external_id_field', $settings['salesforce']['contact_external_id_field']); ?>
							<?php $this->render_text_field('Membership external ID field', 'salesforce', 'membership_external_id_field', $settings['salesforce']['membership_external_id_field']); ?>
							<?php $this->render_text_field('Transaction external ID field', 'salesforce', 'transaction_external_id_field', $settings['salesforce']['transaction_external_id_field']); ?>
						</table>

						<h2>Inbound Security</h2>
						<table class="form-table" role="presentation">
							<?php $this->render_password_field('Shared secret', 'inbound', 'secret', $settings['inbound']['secret']); ?>
						</table>

						<?php submit_button('Save Salesforce Sync Settings'); ?>
					</form>
				</div>

				<div style="display:grid;gap:24px;">
					<div style="background:#fff;padding:24px;border:1px solid #dcdcde;border-radius:8px;">
						<h2 style="margin-top:0;">Queue</h2>
						<ul style="margin:0;padding-left:18px;">
							<li>Pending: <?php echo esc_html((string) ($stats['pending'] ?? 0)); ?></li>
							<li>Processing: <?php echo esc_html((string) ($stats['processing'] ?? 0)); ?></li>
							<li>Completed: <?php echo esc_html((string) ($stats['completed'] ?? 0)); ?></li>
							<li>Dead letter: <?php echo esc_html((string) ($stats['dead_letter'] ?? 0)); ?></li>
						</ul>
						<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:16px;">
							<input type="hidden" name="action" value="aac_salesforce_sync_run_queue" />
							<?php wp_nonce_field('aac_salesforce_sync_run_queue'); ?>
							<?php submit_button('Run Queue Now', 'secondary', '', false); ?>
						</form>
					</div>

					<div style="background:#fff;padding:24px;border:1px solid #dcdcde;border-radius:8px;">
						<h2 style="margin-top:0;">Useful Hooks</h2>
						<ul style="margin:0;padding-left:18px;">
							<li><code>aac_member_portal_member_registered</code></li>
							<li><code>aac_member_portal_profile_updated</code></li>
							<li><code>pmpro_after_checkout</code></li>
							<li><code>pmpro_after_change_membership_level</code></li>
						</ul>
						<p style="margin-top:12px;">Inbound endpoints:</p>
						<ul style="margin:0;padding-left:18px;">
							<li><code>/wp-json/aac-salesforce-sync/v1/contact</code></li>
							<li><code>/wp-json/aac-salesforce-sync/v1/membership</code></li>
							<li><code>/wp-json/aac-salesforce-sync/v1/enqueue</code></li>
						</ul>
					</div>
				</div>
			</div>

			<div style="margin-top:24px;background:#fff;padding:24px;border:1px solid #dcdcde;border-radius:8px;">
				<h2 style="margin-top:0;">Recent Jobs</h2>
				<table class="widefat striped">
					<thead>
						<tr>
							<th>ID</th>
							<th>Job Type</th>
							<th>Object</th>
							<th>External Key</th>
							<th>Status</th>
							<th>Attempts</th>
							<th>Updated</th>
							<th>Action</th>
						</tr>
					</thead>
					<tbody>
						<?php if (!$jobs) : ?>
							<tr><td colspan="8">No queue jobs yet.</td></tr>
						<?php else : ?>
							<?php foreach ($jobs as $job) : ?>
								<tr>
									<td><?php echo esc_html((string) $job['id']); ?></td>
									<td><?php echo esc_html($job['job_type']); ?></td>
									<td><?php echo esc_html($job['object_type']); ?></td>
									<td><code><?php echo esc_html($job['external_key']); ?></code></td>
									<td><?php echo esc_html($job['status']); ?></td>
									<td><?php echo esc_html((string) $job['attempts']); ?></td>
									<td><?php echo esc_html($job['updated_at']); ?></td>
									<td>
										<?php if (in_array($job['status'], ['dead_letter', 'pending'], true)) : ?>
											<a class="button button-small" href="<?php echo esc_url(wp_nonce_url(add_query_arg([
												'action' => 'aac_salesforce_sync_retry_job',
												'job_id' => (int) $job['id'],
											], admin_url('admin-post.php')), 'aac_salesforce_sync_retry_job')); ?>">Retry</a>
										<?php else : ?>
											—
										<?php endif; ?>
									</td>
								</tr>
								<?php if (!empty($job['last_error'])) : ?>
									<tr>
										<td></td>
										<td colspan="7" style="color:#b32d2e;"><?php echo esc_html($job['last_error']); ?></td>
									</tr>
								<?php endif; ?>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	private function render_text_field($label, $group, $field, $value) {
		$name = AAC_Salesforce_Sync_Settings::OPTION_KEY . '[' . $group . '][' . $field . ']';
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr($field); ?>"><?php echo esc_html($label); ?></label></th>
			<td><input type="text" class="regular-text" id="<?php echo esc_attr($field); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>" /></td>
		</tr>
		<?php
	}

	private function render_password_field($label, $group, $field, $value) {
		$name = AAC_Salesforce_Sync_Settings::OPTION_KEY . '[' . $group . '][' . $field . ']';
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr($field); ?>"><?php echo esc_html($label); ?></label></th>
			<td><input type="password" class="regular-text" id="<?php echo esc_attr($field); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>" autocomplete="off" /></td>
		</tr>
		<?php
	}

	private function assert_admin_request() {
		if (!current_user_can('manage_options')) {
			wp_die('You are not allowed to manage Salesforce sync settings.');
		}
	}

	private function redirect_with_notice($notice) {
		wp_safe_redirect(add_query_arg([
			'page' => AAC_Salesforce_Sync_Settings::PAGE_SLUG,
			self::NOTICE_QUERY_ARG => $notice,
		], admin_url(class_exists('AAC_Member_Portal_Admin') ? 'admin.php' : 'tools.php')));
		exit;
	}

	private function notice_message($notice) {
		if (0 === strpos($notice, 'queue-ran-')) {
			return 'Queue processed. Jobs completed: ' . absint(substr($notice, strlen('queue-ran-')));
		}

		$messages = [
			'settings-saved' => 'Salesforce sync settings saved.',
			'job-retried' => 'Queue job reset for retry.',
		];

		return $messages[$notice] ?? 'Settings updated.';
	}
}
