<?php

if (!defined('ABSPATH')) {
	exit;
}

class AAC_Salesforce_Sync_Admin {
	const NOTICE_QUERY_ARG = 'aac_salesforce_sync_notice';
	const TAB_QUERY_ARG = 'tab';
	const OAUTH_STATE_TRANSIENT = 'aac_salesforce_sync_oauth_state_';

	private $worker;

	public function __construct(AAC_Salesforce_Sync_Worker $worker) {
		$this->worker = $worker;

		add_action('admin_menu', [$this, 'register_admin_page']);
		add_action('admin_post_aac_salesforce_sync_save_settings', [$this, 'handle_save_settings']);
		add_action('admin_post_aac_salesforce_sync_connect_salesforce', [$this, 'handle_connect_salesforce']);
		add_action('admin_post_aac_salesforce_sync_start_oauth', [$this, 'handle_start_oauth']);
		add_action('admin_post_aac_salesforce_sync_oauth_callback', [$this, 'handle_oauth_callback']);
		add_action('admin_post_aac_salesforce_sync_disconnect_oauth', [$this, 'handle_disconnect_oauth']);
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
		$tab = isset($_POST['aac_salesforce_sync_redirect_tab']) ? sanitize_key(wp_unslash($_POST['aac_salesforce_sync_redirect_tab'])) : 'authorize';
		AAC_Salesforce_Sync_Settings::update_settings($input);

		$this->redirect_with_notice('settings-saved', $tab);
	}

	public function handle_connect_salesforce() {
		$this->assert_admin_request();
		check_admin_referer('aac_salesforce_sync_connect_salesforce');

		try {
			$settings = AAC_Salesforce_Sync_Settings::get_settings();
			$client = new AAC_Salesforce_Sync_Salesforce_Client();
			$client->test_connection();

			$object_map = [
				'contact' => (string) ($settings['salesforce']['contact_object'] ?? ''),
				'membership' => (string) ($settings['salesforce']['membership_object'] ?? ''),
				'transaction' => (string) ($settings['salesforce']['transaction_object'] ?? ''),
			];

			$catalog = [
				'connected_at' => current_time('mysql'),
				'objects' => [],
			];

			foreach ($object_map as $group => $object_name) {
				if ('' === trim($object_name)) {
					continue;
				}

				$describe = $client->describe_object($object_name);
				$fields = [];
				foreach ((array) ($describe['fields'] ?? []) as $field) {
					if (!is_array($field) || empty($field['name'])) {
						continue;
					}

					$fields[] = [
						'name' => sanitize_text_field((string) $field['name']),
						'label' => sanitize_text_field((string) ($field['label'] ?? $field['name'])),
						'type' => sanitize_text_field((string) ($field['type'] ?? 'string')),
					];
				}

				usort($fields, static function ($left, $right) {
					return strcasecmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
				});

				$catalog['objects'][$group] = [
					'object_name' => sanitize_text_field($object_name),
					'label' => sanitize_text_field((string) ($describe['label'] ?? $object_name)),
					'fields' => $fields,
				];
			}

			AAC_Salesforce_Sync_Settings::update_field_catalog($catalog);
			$this->redirect_with_notice('salesforce-connected');
		} catch (Exception $exception) {
			$this->redirect_with_notice('salesforce-connect-error:' . rawurlencode($exception->getMessage()));
		}
	}

	public function handle_start_oauth() {
		$this->assert_admin_request();
		check_admin_referer('aac_salesforce_sync_start_oauth');

		try {
			$client = new AAC_Salesforce_Sync_Salesforce_Client();
			$state = wp_generate_password(32, false, false);
			set_transient(self::OAUTH_STATE_TRANSIENT . get_current_user_id(), $state, 10 * MINUTE_IN_SECONDS);
			wp_safe_redirect($client->get_authorization_url($state));
			exit;
		} catch (Exception $exception) {
			$this->redirect_with_notice('salesforce-connect-error:' . rawurlencode($exception->getMessage()), 'authorize');
		}
	}

	public function handle_oauth_callback() {
		$this->assert_admin_request();

		$expected_state = get_transient(self::OAUTH_STATE_TRANSIENT . get_current_user_id());
		$received_state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';
		$code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
		delete_transient(self::OAUTH_STATE_TRANSIENT . get_current_user_id());

		if (!$expected_state || !$received_state || !hash_equals((string) $expected_state, (string) $received_state)) {
			$this->redirect_with_notice('salesforce-connect-error:' . rawurlencode('Salesforce OAuth state check failed.'), 'authorize');
		}

		if ($code === '') {
			$this->redirect_with_notice('salesforce-connect-error:' . rawurlencode('Salesforce did not return an authorization code.'), 'authorize');
		}

		try {
			$client = new AAC_Salesforce_Sync_Salesforce_Client();
			$client->exchange_authorization_code($code);
			$this->redirect_with_notice('salesforce-authorized', 'authorize');
		} catch (Exception $exception) {
			$this->redirect_with_notice('salesforce-connect-error:' . rawurlencode($exception->getMessage()), 'authorize');
		}
	}

	public function handle_disconnect_oauth() {
		$this->assert_admin_request();
		check_admin_referer('aac_salesforce_sync_disconnect_oauth');

		AAC_Salesforce_Sync_Settings::clear_auth_state();
		$this->redirect_with_notice('salesforce-disconnected', 'authorize');
	}

	public function handle_run_queue() {
		$this->assert_admin_request();
		check_admin_referer('aac_salesforce_sync_run_queue');

		$count = $this->worker->process_queue();
		$this->redirect_with_notice('queue-ran-' . (int) $count, 'queue');
	}

	public function handle_retry_job() {
		$this->assert_admin_request();
		check_admin_referer('aac_salesforce_sync_retry_job');

		$job_id = isset($_GET['job_id']) ? absint(wp_unslash($_GET['job_id'])) : 0;
		if ($job_id > 0) {
			AAC_Salesforce_Sync_Queue::retry_job($job_id);
		}

		$this->redirect_with_notice('job-retried', 'jobs');
	}

	public function render_admin_page() {
		if (!current_user_can('manage_options')) {
			return;
		}

		$settings = AAC_Salesforce_Sync_Settings::get_settings();
		$stats = AAC_Salesforce_Sync_Queue::get_stats();
		$jobs = AAC_Salesforce_Sync_Queue::list_jobs(30);
		$field_catalog = AAC_Salesforce_Sync_Settings::get_field_catalog();
		$field_definitions = AAC_Salesforce_Sync_Settings::get_field_definitions();
		$auth_state = AAC_Salesforce_Sync_Settings::get_auth_state();
		$notice = isset($_GET[self::NOTICE_QUERY_ARG]) ? sanitize_text_field(wp_unslash($_GET[self::NOTICE_QUERY_ARG])) : '';
		$current_tab = $this->get_current_tab();
		$tabs = [
			'authorize' => 'Authorize',
			'objects' => 'Objects',
			'field-maps' => 'Field Maps',
			'queue' => 'Queue',
			'jobs' => 'Jobs',
		];
		?>
		<div class="wrap">
			<h1>AAC Salesforce Sync</h1>
			<p>Queue-driven Salesforce-first sync layer for AAC Member Portal and Paid Memberships Pro.</p>

			<?php if ($notice) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html($this->notice_message($notice)); ?></p></div>
			<?php endif; ?>

			<nav class="nav-tab-wrapper" style="margin:18px 0 24px;">
				<?php foreach ($tabs as $tab_key => $tab_label) : ?>
					<a class="nav-tab <?php echo $current_tab === $tab_key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url($this->build_admin_url([self::TAB_QUERY_ARG => $tab_key])); ?>">
						<?php echo esc_html($tab_label); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<?php
			if ('authorize' === $current_tab) {
				$this->render_authorize_tab($settings, $auth_state, $field_catalog);
			} elseif ('objects' === $current_tab) {
				$this->render_objects_tab($settings);
			} elseif ('field-maps' === $current_tab) {
				$this->render_field_maps_tab($settings, $field_catalog, $field_definitions);
			} elseif ('queue' === $current_tab) {
				$this->render_queue_tab($settings, $stats);
			} else {
				$this->render_jobs_tab($jobs);
			}
			?>
		</div>
		<?php
	}

	private function render_authorize_tab($settings, $auth_state, $field_catalog) {
		$is_authorized = !empty($auth_state['access_token']) || !empty($auth_state['refresh_token']);
		?>
		<div style="display:grid;grid-template-columns:minmax(0,2fr) minmax(320px,1fr);gap:24px;align-items:start;">
			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="background:#fff;padding:24px;border:1px solid #dcdcde;border-radius:8px;">
				<input type="hidden" name="action" value="aac_salesforce_sync_save_settings" />
				<input type="hidden" name="aac_salesforce_sync_redirect_tab" value="authorize" />
				<?php wp_nonce_field('aac_salesforce_sync_save_settings'); ?>

				<h2 style="margin-top:0;">Salesforce OAuth</h2>
				<p>Sign in to your Salesforce org here. This tab holds the connected-app settings and the actual authorization flow.</p>
				<table class="form-table" role="presentation">
					<?php $this->render_text_field('Authorize URL', 'salesforce', 'auth_url', $settings['salesforce']['auth_url']); ?>
					<?php $this->render_text_field('Token URL', 'salesforce', 'token_url', $settings['salesforce']['token_url']); ?>
					<?php $this->render_text_field('Instance URL (optional)', 'salesforce', 'instance_url', $settings['salesforce']['instance_url']); ?>
					<?php $this->render_text_field('API version', 'salesforce', 'api_version', $settings['salesforce']['api_version']); ?>
					<?php $this->render_text_field('Client ID', 'salesforce', 'client_id', $settings['salesforce']['client_id']); ?>
					<?php $this->render_password_field('Client Secret', 'salesforce', 'client_secret', $settings['salesforce']['client_secret']); ?>
					<tr>
						<th scope="row"><label>Redirect URI</label></th>
						<td><code><?php echo esc_html(AAC_Salesforce_Sync_Settings::get_oauth_redirect_uri()); ?></code></td>
					</tr>
				</table>
				<?php submit_button('Save Authorization Settings'); ?>
			</form>

			<div style="display:grid;gap:24px;">
				<div style="background:#fff;padding:24px;border:1px solid #dcdcde;border-radius:8px;">
					<h2 style="margin-top:0;">Authorization Status</h2>
					<p>
						<strong><?php echo esc_html($is_authorized ? 'Authorized' : 'Not authorized'); ?></strong>
					</p>
					<?php if (!empty($auth_state['connected_at'])) : ?>
						<p style="margin:8px 0 0;color:#50575e;">Connected at: <?php echo esc_html((string) $auth_state['connected_at']); ?></p>
					<?php endif; ?>
					<?php if (!empty($auth_state['instance_url'])) : ?>
						<p style="margin:8px 0 0;color:#50575e;">Org instance: <code><?php echo esc_html((string) $auth_state['instance_url']); ?></code></p>
					<?php endif; ?>
					<div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:16px;">
						<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
							<input type="hidden" name="action" value="aac_salesforce_sync_start_oauth" />
							<?php wp_nonce_field('aac_salesforce_sync_start_oauth'); ?>
							<?php submit_button($is_authorized ? 'Reauthorize Salesforce' : 'Sign In to Salesforce', 'primary', '', false); ?>
						</form>
						<?php if ($is_authorized) : ?>
							<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
								<input type="hidden" name="action" value="aac_salesforce_sync_disconnect_oauth" />
								<?php wp_nonce_field('aac_salesforce_sync_disconnect_oauth'); ?>
								<?php submit_button('Disconnect', 'secondary', '', false); ?>
							</form>
						<?php endif; ?>
					</div>
				</div>

				<div style="background:#fff;padding:24px;border:1px solid #dcdcde;border-radius:8px;">
					<h2 style="margin-top:0;">Field Catalog</h2>
					<p>This pulls live Salesforce object field metadata after you authorize.</p>
					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:16px;">
						<input type="hidden" name="action" value="aac_salesforce_sync_connect_salesforce" />
						<?php wp_nonce_field('aac_salesforce_sync_connect_salesforce'); ?>
						<?php submit_button('Refresh Salesforce Fields', 'secondary', '', false); ?>
					</form>
					<?php if (!empty($field_catalog['connected_at'])) : ?>
						<p style="margin:12px 0 0;color:#50575e;">Last field refresh: <?php echo esc_html((string) $field_catalog['connected_at']); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	private function render_objects_tab($settings) {
		?>
		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="background:#fff;padding:24px;border:1px solid #dcdcde;border-radius:8px;max-width:1100px;">
			<input type="hidden" name="action" value="aac_salesforce_sync_save_settings" />
			<input type="hidden" name="aac_salesforce_sync_redirect_tab" value="objects" />
			<?php wp_nonce_field('aac_salesforce_sync_save_settings'); ?>

			<h2 style="margin-top:0;">Objects and Sync Behavior</h2>
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
				<?php $this->render_text_field('Contact object', 'salesforce', 'contact_object', $settings['salesforce']['contact_object']); ?>
				<?php $this->render_text_field('Membership object', 'salesforce', 'membership_object', $settings['salesforce']['membership_object']); ?>
				<?php $this->render_text_field('Transaction object', 'salesforce', 'transaction_object', $settings['salesforce']['transaction_object']); ?>
				<?php $this->render_text_field('Contact external ID field', 'salesforce', 'contact_external_id_field', $settings['salesforce']['contact_external_id_field']); ?>
				<?php $this->render_text_field('Membership external ID field', 'salesforce', 'membership_external_id_field', $settings['salesforce']['membership_external_id_field']); ?>
				<?php $this->render_text_field('Transaction external ID field', 'salesforce', 'transaction_external_id_field', $settings['salesforce']['transaction_external_id_field']); ?>
				<?php $this->render_password_field('Shared secret', 'inbound', 'secret', $settings['inbound']['secret']); ?>
			</table>

			<?php submit_button('Save Object Settings'); ?>
		</form>
		<?php
	}

	private function render_field_maps_tab($settings, $field_catalog, $field_definitions) {
		?>
		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="background:#fff;padding:24px;border:1px solid #dcdcde;border-radius:8px;">
			<input type="hidden" name="action" value="aac_salesforce_sync_save_settings" />
			<input type="hidden" name="aac_salesforce_sync_redirect_tab" value="field-maps" />
			<?php wp_nonce_field('aac_salesforce_sync_save_settings'); ?>

			<h2 style="margin-top:0;">Field Maps</h2>
			<p>These dropdowns now use AAC Member Database fields only. PMPro values appear here because they are mirrored into the Member Database first.</p>
			<?php
			$this->render_field_mapping_table(
				'Contact Field Mapping',
				'contact',
				(string) $settings['salesforce']['contact_object'],
				$field_definitions,
				$field_catalog,
				$settings
			);
			$this->render_field_mapping_table(
				'Membership Field Mapping',
				'membership',
				(string) $settings['salesforce']['membership_object'],
				$field_definitions,
				$field_catalog,
				$settings
			);
			$this->render_field_mapping_table(
				'Transaction Field Mapping',
				'transaction',
				(string) $settings['salesforce']['transaction_object'],
				$field_definitions,
				$field_catalog,
				$settings
			);
			?>
			<?php submit_button('Save Field Maps'); ?>
		</form>
		<?php
	}

	private function render_queue_tab($settings, $stats) {
		?>
		<div style="display:grid;grid-template-columns:minmax(0,1fr) minmax(320px,360px);gap:24px;align-items:start;">
			<div style="background:#fff;padding:24px;border:1px solid #dcdcde;border-radius:8px;">
				<h2 style="margin-top:0;">Queue Status</h2>
				<ul style="margin:0;padding-left:18px;">
					<li>Pending: <?php echo esc_html((string) ($stats['pending'] ?? 0)); ?></li>
					<li>Processing: <?php echo esc_html((string) ($stats['processing'] ?? 0)); ?></li>
					<li>Completed: <?php echo esc_html((string) ($stats['completed'] ?? 0)); ?></li>
					<li>Dead letter: <?php echo esc_html((string) ($stats['dead_letter'] ?? 0)); ?></li>
				</ul>
				<p style="margin-top:16px;color:#50575e;">Batch size: <?php echo esc_html((string) ($settings['general']['batch_size'] ?? 10)); ?>. Max attempts: <?php echo esc_html((string) ($settings['general']['max_attempts'] ?? 5)); ?>.</p>
			</div>

			<div style="background:#fff;padding:24px;border:1px solid #dcdcde;border-radius:8px;">
				<h2 style="margin-top:0;">Run Queue</h2>
				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
					<input type="hidden" name="action" value="aac_salesforce_sync_run_queue" />
					<?php wp_nonce_field('aac_salesforce_sync_run_queue'); ?>
					<?php submit_button('Run Queue Now', 'secondary', '', false); ?>
				</form>
			</div>
		</div>
		<?php
	}

	private function render_jobs_tab($jobs) {
		?>
		<div style="background:#fff;padding:24px;border:1px solid #dcdcde;border-radius:8px;">
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

	private function redirect_with_notice($notice, $tab = null) {
		$tab = $tab ?: $this->get_current_tab();
		wp_safe_redirect(add_query_arg([
			'page' => AAC_Salesforce_Sync_Settings::PAGE_SLUG,
			self::TAB_QUERY_ARG => $tab,
			self::NOTICE_QUERY_ARG => $notice,
		], admin_url(class_exists('AAC_Member_Portal_Admin') ? 'admin.php' : 'tools.php')));
		exit;
	}

	private function notice_message($notice) {
		if (0 === strpos($notice, 'salesforce-connect-error:')) {
			return 'Salesforce connection failed: ' . rawurldecode(substr($notice, strlen('salesforce-connect-error:')));
		}

		if (0 === strpos($notice, 'queue-ran-')) {
			return 'Queue processed. Jobs completed: ' . absint(substr($notice, strlen('queue-ran-')));
		}

		$messages = [
			'settings-saved' => 'Salesforce sync settings saved.',
			'job-retried' => 'Queue job reset for retry.',
			'salesforce-connected' => 'Salesforce connection succeeded and field metadata was refreshed.',
			'salesforce-authorized' => 'Salesforce sign-in succeeded.',
			'salesforce-disconnected' => 'Salesforce authorization was cleared.',
		];

		return $messages[$notice] ?? 'Settings updated.';
	}

	private function get_current_tab() {
		$tab = isset($_GET[self::TAB_QUERY_ARG]) ? sanitize_key(wp_unslash($_GET[self::TAB_QUERY_ARG])) : 'authorize';
		$allowed_tabs = ['authorize', 'objects', 'field-maps', 'queue', 'jobs'];
		return in_array($tab, $allowed_tabs, true) ? $tab : 'authorize';
	}

	private function build_admin_url($args = []) {
		return add_query_arg(array_merge([
			'page' => AAC_Salesforce_Sync_Settings::PAGE_SLUG,
		], $args), admin_url(class_exists('AAC_Member_Portal_Admin') ? 'admin.php' : 'tools.php'));
	}

	private function render_field_mapping_table($title, $group, $object_name, $field_definitions, $field_catalog, $settings) {
		$definitions = isset($field_definitions[$group]) && is_array($field_definitions[$group]) ? $field_definitions[$group] : [];
		$catalog_group = isset($field_catalog['objects'][$group]) && is_array($field_catalog['objects'][$group]) ? $field_catalog['objects'][$group] : [];
		$salesforce_fields = isset($catalog_group['fields']) && is_array($catalog_group['fields']) ? $catalog_group['fields'] : [];
		$selected_fields = isset($settings['field_mappings'][$group]) && is_array($settings['field_mappings'][$group]) ? $settings['field_mappings'][$group] : [];
		?>
		<div style="margin:20px 0 28px;border:1px solid #dcdcde;border-radius:8px;overflow:hidden;">
			<div style="padding:14px 16px;background:#f6f7f7;border-bottom:1px solid #dcdcde;">
				<strong><?php echo esc_html($title); ?></strong>
				<div style="margin-top:4px;color:#50575e;"><?php echo esc_html($object_name ?: 'No Salesforce object selected yet.'); ?></div>
			</div>
			<?php if (empty($salesforce_fields)) : ?>
				<div style="padding:16px;color:#50575e;">
					Connect to Salesforce first to load dropdown options for this object.
				</div>
			<?php else : ?>
				<table class="widefat striped" style="border:none;">
					<thead>
						<tr>
							<th style="width:32%;">Member Database Field</th>
							<th style="width:18%;">Field Type</th>
							<th>Salesforce Field</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($definitions as $field_key => $definition) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html((string) ($definition['label'] ?? $field_key)); ?></strong>
									<div style="margin-top:4px;color:#50575e;"><code><?php echo esc_html((string) ($definition['source_path'] ?? '')); ?></code></div>
								</td>
								<td><?php echo esc_html((string) ($definition['type'] ?? 'string')); ?></td>
								<td>
									<select name="<?php echo esc_attr(AAC_Salesforce_Sync_Settings::OPTION_KEY . '[field_mappings][' . $group . '][' . $field_key . ']'); ?>" style="min-width:320px;max-width:100%;">
										<option value="">Do not sync</option>
										<?php foreach ($salesforce_fields as $salesforce_field) : ?>
											<?php $field_name = (string) ($salesforce_field['name'] ?? ''); ?>
											<option value="<?php echo esc_attr($field_name); ?>" <?php selected((string) ($selected_fields[$field_key] ?? ''), $field_name); ?>>
												<?php echo esc_html((string) ($salesforce_field['label'] ?? $field_name)); ?>
												<?php echo $field_name ? esc_html(' (' . $field_name . ')') : ''; ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
