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
		add_action('admin_post_nopriv_aac_salesforce_sync_oauth_callback', [$this, 'handle_oauth_callback']);
		add_action('admin_post_aac_salesforce_sync_disconnect_oauth', [$this, 'handle_disconnect_oauth']);
		add_action('admin_post_aac_salesforce_sync_backfill_members', [$this, 'handle_backfill_members']);
		add_action('admin_post_aac_salesforce_sync_run_queue', [$this, 'handle_run_queue']);
		add_action('admin_post_aac_salesforce_sync_clear_all_jobs', [$this, 'handle_clear_all_jobs']);
		add_action('admin_post_aac_salesforce_sync_retry_job', [$this, 'handle_retry_job']);
		add_action('admin_post_aac_salesforce_sync_export_field_maps_csv', [$this, 'handle_export_field_maps_csv']);
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

		$current = AAC_Salesforce_Sync_Settings::get_settings();
		$input = isset($_POST[AAC_Salesforce_Sync_Settings::OPTION_KEY]) ? wp_unslash($_POST[AAC_Salesforce_Sync_Settings::OPTION_KEY]) : [];
		$tab = isset($_POST['aac_salesforce_sync_redirect_tab']) ? sanitize_key(wp_unslash($_POST['aac_salesforce_sync_redirect_tab'])) : 'authorize';
		$updated = AAC_Salesforce_Sync_Settings::update_settings($input);

		if ($this->oauth_settings_changed($current, $updated)) {
			AAC_Salesforce_Sync_Settings::clear_auth_state();
			AAC_Salesforce_Sync_Salesforce_Client::clear_cached_token();
			AAC_Salesforce_Sync_Settings::update_field_catalog([]);
			$this->redirect_with_notice('settings-saved-reauthorize', $tab);
		}

		$this->redirect_with_notice('settings-saved', $tab);
	}

	public function handle_connect_salesforce() {
		$this->assert_admin_request();
		check_admin_referer('aac_salesforce_sync_connect_salesforce');

		try {
			$settings = AAC_Salesforce_Sync_Settings::get_settings();
			$client = new AAC_Salesforce_Sync_Salesforce_Client();
			$client->test_connection();
			$object_definitions = AAC_Salesforce_Sync_Settings::get_object_definitions($settings);

			$catalog = [
				'connected_at' => current_time('mysql'),
				'objects' => [],
			];
			$loaded_count = 0;
			$error_messages = [];

			foreach ($object_definitions as $group => $objects) {
				foreach ($objects as $object_key => $object_definition) {
					$object_name = (string) ($object_definition['object_name'] ?? '');
					if ('' === trim($object_name)) {
						continue;
					}

					try {
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
							'relationship_name' => sanitize_text_field((string) ($field['relationshipName'] ?? '')),
							'reference_to' => array_values(array_filter(array_map('sanitize_text_field', (array) ($field['referenceTo'] ?? [])))),
							'restricted_picklist' => !empty($field['restrictedPicklist']) ? 1 : 0,
							'picklist_values' => array_values(
								array_filter(
									array_map(
										static function ($picklist_value) {
											if (!is_array($picklist_value) || empty($picklist_value['active'])) {
												return '';
											}

											return sanitize_text_field((string) ($picklist_value['value'] ?? ''));
										},
										(array) ($field['picklistValues'] ?? [])
									)
								)
							),
						];
						}

						usort($fields, static function ($left, $right) {
							return strcasecmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
						});

						$catalog['objects'][$object_key] = [
							'group' => sanitize_key($group),
							'object_key' => sanitize_key($object_key),
							'object_name' => sanitize_text_field($object_name),
							'label' => sanitize_text_field((string) ($object_definition['label'] ?? $describe['label'] ?? $object_name)),
							'fields' => $fields,
							'external_id_field' => sanitize_text_field((string) ($object_definition['external_id_field'] ?? '')),
						];
						$loaded_count++;
					} catch (Exception $exception) {
						$catalog['objects'][$object_key] = [
							'group' => sanitize_key($group),
							'object_key' => sanitize_key($object_key),
							'object_name' => sanitize_text_field($object_name),
							'label' => sanitize_text_field((string) ($object_definition['label'] ?? $object_name)),
							'fields' => [],
							'external_id_field' => sanitize_text_field((string) ($object_definition['external_id_field'] ?? '')),
							'error' => sanitize_text_field($exception->getMessage()),
						];
						$error_messages[] = ucfirst($group) . ' / ' . ($object_definition['label'] ?? $object_key) . ': ' . $exception->getMessage();
					}
				}
			}

			AAC_Salesforce_Sync_Settings::update_field_catalog($catalog);
			if ($loaded_count > 0 && $error_messages) {
				$this->redirect_with_notice('salesforce-connected-partial:' . rawurlencode(implode(' | ', $error_messages)));
			}
			if ($loaded_count > 0) {
				$this->redirect_with_notice('salesforce-connected');
			}
			$this->redirect_with_notice('salesforce-connect-error:' . rawurlencode(implode(' | ', $error_messages)));
		} catch (Exception $exception) {
			$this->redirect_with_notice('salesforce-connect-error:' . rawurlencode($exception->getMessage()));
		}
	}

	public function handle_start_oauth() {
		$this->assert_admin_request();
		check_admin_referer('aac_salesforce_sync_start_oauth');

		try {
			$client = new AAC_Salesforce_Sync_Salesforce_Client();
			if (!$client->can_authorize()) {
				throw new RuntimeException('Save the Salesforce OAuth settings first, then start the sign-in flow.');
			}
			$state = wp_generate_password(32, false, false);
			set_transient(
				self::OAUTH_STATE_TRANSIENT . $state,
				[
					'user_id' => get_current_user_id(),
					'created_at' => time(),
				],
				10 * MINUTE_IN_SECONDS
			);
			wp_redirect($client->get_authorization_url($state), 302, 'AAC Salesforce Sync');
			exit;
		} catch (Exception $exception) {
			$this->redirect_with_notice('salesforce-connect-error:' . rawurlencode($exception->getMessage()), 'authorize');
		}
	}

	public function handle_oauth_callback() {
		$error = isset($_GET['error']) ? sanitize_text_field(wp_unslash($_GET['error'])) : '';
		$error_description = isset($_GET['error_description']) ? sanitize_text_field(wp_unslash($_GET['error_description'])) : '';
		if ($error !== '') {
			$message = $error_description !== '' ? $error . ': ' . $error_description : $error;
			$this->redirect_with_notice('salesforce-connect-error:' . rawurlencode($message), 'authorize');
		}

		$received_state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';
		$state_payload = $received_state ? get_transient(self::OAUTH_STATE_TRANSIENT . $received_state) : false;
		$code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
		if ($received_state) {
			delete_transient(self::OAUTH_STATE_TRANSIENT . $received_state);
		}

		if (!$state_payload || !$received_state) {
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

	public function handle_backfill_members() {
		$this->assert_admin_request();

		$refresh_member_database = !empty($_POST['refresh_member_database']);
		$include_transactions = !empty($_POST['include_transactions']);

		$counts = $this->enqueue_historical_backfill($refresh_member_database, $include_transactions);
		$this->redirect_with_notice(
			sprintf(
				'backfill-enqueued-%d-%d-%d',
				(int) ($counts['members'] ?? 0),
				(int) ($counts['transactions'] ?? 0),
				(int) ($counts['refreshed'] ?? 0)
			),
			'queue'
		);
	}

	public function handle_run_queue() {
		$this->assert_admin_request();

		// Manual queue runs should not sit around waiting for timestamp gremlins.
		// If an admin clicked the button, we make the pending jobs eligible now
		// and let the worker tell us what is actually broken.
		AAC_Salesforce_Sync_Queue::make_pending_jobs_available_now();
		$count = $this->worker->process_queue(null, true);
		$this->redirect_with_notice('queue-ran-' . (int) $count, 'queue');
	}

	public function handle_clear_all_jobs() {
		$this->assert_admin_request();

		$deleted = AAC_Salesforce_Sync_Queue::clear_all_jobs();
		$this->redirect_with_notice('jobs-cleared-' . (int) $deleted, 'queue');
	}

	public function handle_retry_job() {
		$this->assert_admin_request();

		$job_id = isset($_GET['job_id']) ? absint(wp_unslash($_GET['job_id'])) : 0;
		if ($job_id > 0) {
			AAC_Salesforce_Sync_Queue::retry_job($job_id);
		}

		$this->redirect_with_notice('job-retried', 'jobs');
	}

	public function handle_export_field_maps_csv() {
		$this->assert_admin_request();
		check_admin_referer('aac_salesforce_sync_export_field_maps_csv');

		$settings = AAC_Salesforce_Sync_Settings::get_settings();
		$field_catalog = AAC_Salesforce_Sync_Settings::get_field_catalog();
		$field_definitions = AAC_Salesforce_Sync_Settings::get_field_definitions();
		$rows = $this->build_field_map_export_rows($settings, $field_catalog, $field_definitions);

		nocache_headers();
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="aac-salesforce-field-maps-' . gmdate('Ymd-His') . '.csv"');

		$output = fopen('php://output', 'w');
		if ($output === false) {
			wp_die('Unable to open the field map export stream.');
		}

		fputcsv($output, [
			'group',
			'group_label',
			'salesforce_object',
			'wordpress_field_key',
			'wordpress_field_label',
			'wordpress_source_path',
			'wordpress_field_type',
			'salesforce_field_api_name',
			'salesforce_field_label',
			'salesforce_field_type',
			'is_mapped',
			'wordpress_metadata_json',
			'salesforce_metadata_json',
		]);

		foreach ($rows as $row) {
			fputcsv($output, $row);
		}

		fclose($output);
		exit;
	}

	public function render_admin_page() {
		if (!current_user_can('manage_options')) {
			return;
		}

		$settings = AAC_Salesforce_Sync_Settings::get_settings();
		$stats = AAC_Salesforce_Sync_Queue::get_stats();
		$jobs = AAC_Salesforce_Sync_Queue::list_jobs(250);
		$field_catalog = AAC_Salesforce_Sync_Settings::get_field_catalog();
		$field_definitions = AAC_Salesforce_Sync_Settings::get_field_definitions();
		$auth_state = AAC_Salesforce_Sync_Settings::get_auth_state();
		$oauth_debug = AAC_Salesforce_Sync_Settings::get_oauth_debug();
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
				$this->render_authorize_tab($settings, $auth_state, $field_catalog, $oauth_debug);
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

	private function render_authorize_tab($settings, $auth_state, $field_catalog, $oauth_debug) {
		$is_authorized = !empty($auth_state['access_token']) || !empty($auth_state['refresh_token']);
		$oauth_ready = !empty($settings['salesforce']['client_id']) && !empty($settings['salesforce']['client_secret']) && !empty($settings['salesforce']['auth_url']) && !empty($settings['salesforce']['token_url']);
		$configured_scope = trim((string) ($settings['salesforce']['oauth_scope'] ?? 'api refresh_token offline_access'));
		$scope_list = array_filter(array_map('trim', preg_split('/[\s,]+/', $configured_scope)));
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
					<?php $this->render_text_field('OAuth scope', 'salesforce', 'oauth_scope', $settings['salesforce']['oauth_scope']); ?>
					<?php $this->render_text_field('Client ID', 'salesforce', 'client_id', $settings['salesforce']['client_id']); ?>
					<?php $this->render_password_field('Client Secret', 'salesforce', 'client_secret', $settings['salesforce']['client_secret']); ?>
					<tr>
						<th scope="row"><label>Redirect URI</label></th>
						<td>
							<code><?php echo esc_html(AAC_Salesforce_Sync_Settings::get_oauth_redirect_uri()); ?></code>
							<p style="margin:8px 0 0;color:#50575e;">Add this exact callback URL to the Salesforce Connected App. One missing slash and Salesforce throws a tiny revolt.</p>
						</td>
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
					<?php if (!empty($auth_state['scope'])) : ?>
						<p style="margin:8px 0 0;color:#50575e;">Granted scope: <code><?php echo esc_html((string) $auth_state['scope']); ?></code></p>
					<?php endif; ?>
					<?php if (!empty($auth_state['expires_at'])) : ?>
						<p style="margin:8px 0 0;color:#50575e;">Access token expires: <?php echo esc_html(wp_date('Y-m-d H:i:s', (int) $auth_state['expires_at'])); ?></p>
					<?php endif; ?>
					<?php if (!empty($auth_state['refresh_token'])) : ?>
						<p style="margin:8px 0 0;color:#50575e;">Refresh token: stored and ready to quietly do the heavy lifting later.</p>
					<?php endif; ?>
					<?php if (!empty($auth_state['id_url'])) : ?>
						<p style="margin:8px 0 0;color:#50575e;">Identity URL: <code><?php echo esc_html((string) $auth_state['id_url']); ?></code></p>
					<?php endif; ?>
					<?php if (!$oauth_ready) : ?>
						<p style="margin:12px 0 0;color:#b32d2e;">Save the authorize URL, token URL, client ID, client secret, and redirect URI setup before starting OAuth.</p>
					<?php endif; ?>
					<div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:16px;">
						<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
							<input type="hidden" name="action" value="aac_salesforce_sync_start_oauth" />
							<?php wp_nonce_field('aac_salesforce_sync_start_oauth'); ?>
							<?php submit_button($is_authorized ? 'Reauthorize Salesforce' : 'Sign In to Salesforce', 'primary', '', false, $oauth_ready ? [] : ['disabled' => 'disabled']); ?>
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
					<h2 style="margin-top:0;">Connected App Checklist</h2>
					<ol style="margin:0;padding-left:18px;">
						<li>Enable OAuth on the Salesforce Connected App.</li>
						<li>Paste the redirect URI shown on this page into the Connected App callback list.</li>
						<li>Make sure the Connected App allows these scopes:</li>
					</ol>
					<ul style="margin:12px 0 0 18px;">
						<?php foreach ($scope_list as $scope_item) : ?>
							<li><code><?php echo esc_html($scope_item); ?></code></li>
						<?php endforeach; ?>
					</ul>
					<p style="margin:12px 0 0;color:#50575e;">If you are using a sandbox org, swap the authorize and token URLs to the Salesforce test domain before signing in.</p>
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

				<div style="background:#fff;padding:24px;border:1px solid #dcdcde;border-radius:8px;">
					<h2 style="margin-top:0;">OAuth Diagnostics</h2>
					<?php if (empty($oauth_debug)) : ?>
						<p style="margin:0;color:#50575e;">No callback data yet. Once Salesforce returns, this box keeps the last handshake breadcrumbs so we can stop debugging by campfire storytelling.</p>
					<?php else : ?>
						<table class="widefat striped" style="border:none;">
							<tbody>
								<?php foreach ($oauth_debug as $debug_key => $debug_value) : ?>
									<tr>
										<th style="width:34%;"><?php echo esc_html(ucwords(str_replace('_', ' ', (string) $debug_key))); ?></th>
										<td><code><?php echo esc_html(is_scalar($debug_value) ? (string) $debug_value : wp_json_encode($debug_value)); ?></code></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	private function render_objects_tab($settings) {
		$contact_enabled = !empty($settings['general']['sync_contact']);
		$membership_enabled = !empty($settings['general']['sync_membership']);
		$transaction_enabled = !empty($settings['general']['sync_transaction']);
		$grant_enabled = !empty($settings['general']['sync_grant']);
		$group_labels = AAC_Salesforce_Sync_Settings::get_group_labels();
		$object_definitions = AAC_Salesforce_Sync_Settings::get_object_definitions($settings);
		$custom_objects = isset($settings['salesforce']['custom_objects']) && is_array($settings['salesforce']['custom_objects'])
			? $settings['salesforce']['custom_objects']
			: [];
		?>
		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="background:#fff;padding:24px;border:1px solid #dcdcde;border-radius:8px;max-width:1100px;">
			<input type="hidden" name="action" value="aac_salesforce_sync_save_settings" />
			<input type="hidden" name="aac_salesforce_sync_redirect_tab" value="objects" />
			<?php wp_nonce_field('aac_salesforce_sync_save_settings'); ?>

			<h2 style="margin-top:0;">Objects and Sync Behavior</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Enable outbound sync</th>
					<td>
						<input type="hidden" name="<?php echo esc_attr(AAC_Salesforce_Sync_Settings::OPTION_KEY . '[general][enabled]'); ?>" value="0" />
						<label><input type="checkbox" name="<?php echo esc_attr(AAC_Salesforce_Sync_Settings::OPTION_KEY . '[general][enabled]'); ?>" value="1" <?php checked(!empty($settings['general']['enabled'])); ?> /> Queue and process Salesforce sync jobs</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label>Batch size</label></th>
					<td><input type="number" min="1" max="50" name="<?php echo esc_attr(AAC_Salesforce_Sync_Settings::OPTION_KEY . '[general][batch_size]'); ?>" value="<?php echo esc_attr($settings['general']['batch_size']); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label>Max attempts</label></th>
					<td><input type="number" min="1" max="20" name="<?php echo esc_attr(AAC_Salesforce_Sync_Settings::OPTION_KEY . '[general][max_attempts]'); ?>" value="<?php echo esc_attr($settings['general']['max_attempts']); ?>" /></td>
				</tr>
				<tr>
					<th scope="row">Active outbound objects</th>
					<td>
						<input type="hidden" name="<?php echo esc_attr(AAC_Salesforce_Sync_Settings::OPTION_KEY . '[general][sync_contact]'); ?>" value="0" />
						<label style="display:block;margin-bottom:8px;"><input type="checkbox" name="<?php echo esc_attr(AAC_Salesforce_Sync_Settings::OPTION_KEY . '[general][sync_contact]'); ?>" value="1" <?php checked($contact_enabled); ?> /> Sync Contact jobs</label>
						<input type="hidden" name="<?php echo esc_attr(AAC_Salesforce_Sync_Settings::OPTION_KEY . '[general][sync_membership]'); ?>" value="0" />
						<label style="display:block;margin-bottom:8px;"><input type="checkbox" name="<?php echo esc_attr(AAC_Salesforce_Sync_Settings::OPTION_KEY . '[general][sync_membership]'); ?>" value="1" <?php checked($membership_enabled); ?> /> Sync Membership jobs</label>
						<input type="hidden" name="<?php echo esc_attr(AAC_Salesforce_Sync_Settings::OPTION_KEY . '[general][sync_transaction]'); ?>" value="0" />
						<label style="display:block;margin-bottom:8px;"><input type="checkbox" name="<?php echo esc_attr(AAC_Salesforce_Sync_Settings::OPTION_KEY . '[general][sync_transaction]'); ?>" value="1" <?php checked($transaction_enabled); ?> /> Sync Transaction jobs</label>
						<input type="hidden" name="<?php echo esc_attr(AAC_Salesforce_Sync_Settings::OPTION_KEY . '[general][sync_grant]'); ?>" value="0" />
						<label style="display:block;"><input type="checkbox" name="<?php echo esc_attr(AAC_Salesforce_Sync_Settings::OPTION_KEY . '[general][sync_grant]'); ?>" value="1" <?php checked($grant_enabled); ?> /> Sync Grant jobs</label>
						<p style="margin:8px 0 0;color:#50575e;">These switches only control which jobs are queued and processed right now. They do not remove your mappings or object setup, so we can safely run Contact-only for the moment and turn the others back on later.</p>
					</td>
				</tr>
				<?php $this->render_text_field('Contact object', 'salesforce', 'contact_object', $settings['salesforce']['contact_object']); ?>
				<?php $this->render_text_field('Membership Term object', 'salesforce', 'membership_object', $settings['salesforce']['membership_object']); ?>
				<?php $this->render_text_field('Transaction object', 'salesforce', 'transaction_object', $settings['salesforce']['transaction_object']); ?>
				<?php $this->render_text_field('Grant object', 'salesforce', 'grant_object', $settings['salesforce']['grant_object']); ?>
				<?php $this->render_text_field('Contact external ID field', 'salesforce', 'contact_external_id_field', $settings['salesforce']['contact_external_id_field']); ?>
				<?php $this->render_text_field('Membership Term external ID field', 'salesforce', 'membership_external_id_field', $settings['salesforce']['membership_external_id_field']); ?>
				<?php $this->render_text_field('Transaction external ID field', 'salesforce', 'transaction_external_id_field', $settings['salesforce']['transaction_external_id_field']); ?>
				<?php $this->render_text_field('Grant external ID field', 'salesforce', 'grant_external_id_field', $settings['salesforce']['grant_external_id_field']); ?>
				<?php $this->render_password_field('Shared secret', 'inbound', 'secret', $settings['inbound']['secret']); ?>
			</table>

			<hr style="margin:28px 0;border:none;border-top:1px solid #dcdcde;" />
			<h3 style="margin:0 0 8px;">Additional Salesforce Objects</h3>
			<p style="margin:0 0 16px;color:#50575e;">
				Add extra Salesforce objects here when a field group needs to write to more than the primary object. Each object belongs to one sync group and needs its own external ID field so the worker knows how to upsert without guessing in the dark.
			</p>
			<input type="hidden" name="<?php echo esc_attr(AAC_Salesforce_Sync_Settings::OPTION_KEY . '[salesforce][custom_objects_present]'); ?>" value="1" />
			<table class="widefat striped" id="aac-salesforce-custom-objects-table" style="max-width:100%;">
				<thead>
					<tr>
						<th style="width:16%;">Sync Group</th>
						<th style="width:16%;">Object Key</th>
						<th style="width:20%;">Label</th>
						<th style="width:26%;">API Name</th>
						<th style="width:22%;">External ID Field</th>
					</tr>
				</thead>
				<tbody>
					<?php if (!$custom_objects) : ?>
						<tr class="aac-salesforce-custom-object-row">
							<td colspan="5" style="color:#50575e;">No additional objects yet. The primary objects above still do the heavy lifting, but we can add more rows below when a mapping needs somewhere else to land.</td>
						</tr>
					<?php else : ?>
						<?php foreach ($custom_objects as $index => $custom_object) : ?>
							<tr class="aac-salesforce-custom-object-row">
								<td>
									<select name="<?php echo esc_attr(AAC_Salesforce_Sync_Settings::OPTION_KEY . '[salesforce][custom_objects][' . $index . '][group]'); ?>">
										<?php foreach ($group_labels as $group_key => $group_label) : ?>
											<option value="<?php echo esc_attr($group_key); ?>" <?php selected((string) ($custom_object['group'] ?? ''), $group_key); ?>><?php echo esc_html($group_label); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
								<td><input type="text" class="regular-text" name="<?php echo esc_attr(AAC_Salesforce_Sync_Settings::OPTION_KEY . '[salesforce][custom_objects][' . $index . '][key]'); ?>" value="<?php echo esc_attr((string) ($custom_object['key'] ?? '')); ?>" placeholder="contact_discount" /></td>
								<td><input type="text" class="regular-text" name="<?php echo esc_attr(AAC_Salesforce_Sync_Settings::OPTION_KEY . '[salesforce][custom_objects][' . $index . '][label]'); ?>" value="<?php echo esc_attr((string) ($custom_object['label'] ?? '')); ?>" placeholder="Contact Discount Record" /></td>
								<td><input type="text" class="regular-text" name="<?php echo esc_attr(AAC_Salesforce_Sync_Settings::OPTION_KEY . '[salesforce][custom_objects][' . $index . '][object_name]'); ?>" value="<?php echo esc_attr((string) ($custom_object['object_name'] ?? '')); ?>" placeholder="Discount__c" /></td>
								<td><input type="text" class="regular-text" name="<?php echo esc_attr(AAC_Salesforce_Sync_Settings::OPTION_KEY . '[salesforce][custom_objects][' . $index . '][external_id_field]'); ?>" value="<?php echo esc_attr((string) ($custom_object['external_id_field'] ?? '')); ?>" placeholder="AAC_External_Key__c" /></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
			<p style="margin:12px 0 0;">
				<button type="button" class="button" id="aac-salesforce-add-object-row">Add Another Object</button>
			</p>

			<?php submit_button('Save Object Settings'); ?>
		</form>
		<script>
			(function() {
				const table = document.getElementById('aac-salesforce-custom-objects-table');
				const addButton = document.getElementById('aac-salesforce-add-object-row');
				if (!table || !addButton) {
					return;
				}

				const groupOptions = <?php echo wp_json_encode(array_map(static function ($label, $key) {
					return ['value' => $key, 'label' => $label];
				}, $group_labels, array_keys($group_labels))); ?>;

				const buildGroupSelect = (rowIndex) => {
					const select = document.createElement('select');
					select.name = <?php echo wp_json_encode(AAC_Salesforce_Sync_Settings::OPTION_KEY . '[salesforce][custom_objects]['); ?> + rowIndex + '][group]';
					groupOptions.forEach((option) => {
						const node = document.createElement('option');
						node.value = option.value;
						node.textContent = option.label;
						select.appendChild(node);
					});
					return select;
				};

				addButton.addEventListener('click', () => {
					const tbody = table.querySelector('tbody');
					if (!tbody) {
						return;
					}

					const placeholderRow = tbody.querySelector('tr td[colspan="5"]');
					if (placeholderRow) {
						placeholderRow.parentElement.remove();
					}

					const rowIndex = tbody.querySelectorAll('tr.aac-salesforce-custom-object-row').length;
					const row = document.createElement('tr');
					row.className = 'aac-salesforce-custom-object-row';

					const buildCell = () => document.createElement('td');
					const groupCell = buildCell();
					groupCell.appendChild(buildGroupSelect(rowIndex));
					row.appendChild(groupCell);

					[
						{ field: 'key', placeholder: 'contact_discount' },
						{ field: 'label', placeholder: 'Contact Discount Record' },
						{ field: 'object_name', placeholder: 'Discount__c' },
						{ field: 'external_id_field', placeholder: 'AAC_External_Key__c' }
					].forEach((column) => {
						const cell = buildCell();
						const input = document.createElement('input');
						input.type = 'text';
						input.className = 'regular-text';
						input.placeholder = column.placeholder;
						input.name = <?php echo wp_json_encode(AAC_Salesforce_Sync_Settings::OPTION_KEY . '[salesforce][custom_objects]['); ?> + rowIndex + '][' + column.field + ']';
						cell.appendChild(input);
						row.appendChild(cell);
					});

					tbody.appendChild(row);
				});
			})();
		</script>
		<?php
	}

	private function render_field_maps_tab($settings, $field_catalog, $field_definitions) {
		$object_definitions = AAC_Salesforce_Sync_Settings::get_object_definitions($settings);
		?>
		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="background:#fff;padding:24px;border:1px solid #dcdcde;border-radius:8px;">
			<input type="hidden" name="action" value="aac_salesforce_sync_save_settings" />
			<input type="hidden" name="aac_salesforce_sync_redirect_tab" value="field-maps" />
			<?php wp_nonce_field('aac_salesforce_sync_save_settings'); ?>

			<h2 style="margin-top:0;">Field Maps</h2>
			<p>These dropdowns use AAC Member Database fields for contact, membership, and transaction sync, plus grant application fields from the AAC Grants Review workflow. Grant records can now upsert on any workflow status, from <code>Submitted</code> all the way through the final review steps.</p>
			<div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin:0 0 18px;">
				<a class="button button-secondary" href="<?php echo esc_url(wp_nonce_url(add_query_arg([
					'action' => 'aac_salesforce_sync_export_field_maps_csv',
				], admin_url('admin-post.php')), 'aac_salesforce_sync_export_field_maps_csv')); ?>">
					Download Field Map CSV
				</a>
				<span style="color:#50575e;">Exports every WordPress source field, its type and metadata, plus the current Salesforce target and target metadata.</span>
			</div>
			<?php
			$this->render_field_mapping_table(
				'Contact Field Mapping',
				'contact',
				isset($object_definitions['contact']) && is_array($object_definitions['contact']) ? $object_definitions['contact'] : [],
				$field_definitions,
				$field_catalog,
				$settings
			);
			$this->render_field_mapping_table(
				'Membership Field Mapping',
				'membership',
				isset($object_definitions['membership']) && is_array($object_definitions['membership']) ? $object_definitions['membership'] : [],
				$field_definitions,
				$field_catalog,
				$settings
			);
			$this->render_field_mapping_table(
				'Transaction Field Mapping',
				'transaction',
				isset($object_definitions['transaction']) && is_array($object_definitions['transaction']) ? $object_definitions['transaction'] : [],
				$field_definitions,
				$field_catalog,
				$settings
			);
			$this->render_field_mapping_table(
				'Grant Field Mapping',
				'grant',
				isset($object_definitions['grant']) && is_array($object_definitions['grant']) ? $object_definitions['grant'] : [],
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
		$enabled_objects = [];
		if (!empty($settings['general']['sync_contact'])) {
			$enabled_objects[] = 'Contact';
		}
		if (!empty($settings['general']['sync_membership'])) {
			$enabled_objects[] = 'Membership';
		}
		if (!empty($settings['general']['sync_transaction'])) {
			$enabled_objects[] = 'Transaction';
		}
		if (!empty($settings['general']['sync_grant'])) {
			$enabled_objects[] = 'Grant';
		}
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
				<p style="margin-top:8px;color:#50575e;">Currently enabled for outbound sync: <?php echo esc_html($enabled_objects ? implode(', ', $enabled_objects) : 'None'); ?>.</p>
			</div>

			<div style="display:grid;gap:24px;">
				<div style="background:#fff;padding:24px;border:1px solid #dcdcde;border-radius:8px;">
					<h2 style="margin-top:0;">Run Queue</h2>
					<div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
						<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
							<input type="hidden" name="action" value="aac_salesforce_sync_run_queue" />
							<?php wp_nonce_field('aac_salesforce_sync_run_queue'); ?>
							<?php submit_button('Run Queue Now', 'secondary', '', false); ?>
						</form>
						<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return window.confirm('Clear every Salesforce sync job from the queue? This removes pending, processing, completed, and dead-letter rows.');">
							<input type="hidden" name="action" value="aac_salesforce_sync_clear_all_jobs" />
							<?php wp_nonce_field('aac_salesforce_sync_clear_all_jobs'); ?>
							<?php submit_button('Clear All Jobs', 'delete', '', false); ?>
						</form>
					</div>
					<p style="margin:12px 0 0;color:#50575e;">`Clear All Jobs` wipes the queue table clean. Handy for a reset, less handy if you were hoping those rows would still be around later.</p>
				</div>

				<div style="background:#fff;padding:24px;border:1px solid #dcdcde;border-radius:8px;">
					<h2 style="margin-top:0;">Historical Backfill</h2>
					<p style="margin:0 0 16px;color:#50575e;">
						Use this when you want to push the existing WordPress member universe into Salesforce. It only queues the object types that are currently enabled in the Objects tab, so Contact-only mode stays nicely in its lane.
					</p>
					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
						<input type="hidden" name="action" value="aac_salesforce_sync_backfill_members" />
						<?php wp_nonce_field('aac_salesforce_sync_backfill_members'); ?>
						<p style="margin:0 0 10px;">
							<label>
								<input type="checkbox" name="refresh_member_database" value="1" checked="checked" />
								Refresh the AAC Member Database mirror first
							</label>
						</p>
						<p style="margin:0 0 18px;">
							<label>
								<input type="checkbox" name="include_transactions" value="1" />
								Also queue historical PMPro transactions
							</label>
						</p>
						<?php submit_button('Backfill All Members to Salesforce', 'primary', '', false); ?>
					</form>
					<p style="margin:12px 0 0;color:#50575e;">
						This only builds the queue. After that, run the queue now or let cron work through the pile five minutes at a time.
					</p>
				</div>
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
		$target = add_query_arg([
			'page' => AAC_Salesforce_Sync_Settings::PAGE_SLUG,
			self::TAB_QUERY_ARG => $tab,
			self::NOTICE_QUERY_ARG => $notice,
		], admin_url(class_exists('AAC_Member_Portal_Admin') ? 'admin.php' : 'tools.php'));

		if (!is_user_logged_in()) {
			$target = wp_login_url($target);
		}

		wp_safe_redirect($target);
		exit;
	}

	private function notice_message($notice) {
		if (0 === strpos($notice, 'salesforce-connect-error:')) {
			return 'Salesforce connection failed: ' . rawurldecode(substr($notice, strlen('salesforce-connect-error:')));
		}

		if (0 === strpos($notice, 'salesforce-connected-partial:')) {
			return 'Salesforce connected, but some object fields could not be loaded: ' . rawurldecode(substr($notice, strlen('salesforce-connected-partial:')));
		}

		if (0 === strpos($notice, 'queue-ran-')) {
			return 'Queue processed. Jobs completed: ' . absint(substr($notice, strlen('queue-ran-')));
		}

		if (0 === strpos($notice, 'backfill-enqueued-')) {
			$counts = explode('-', substr($notice, strlen('backfill-enqueued-')));
			$member_count = absint($counts[0] ?? 0);
			$transaction_count = absint($counts[1] ?? 0);
			$refresh_count = absint($counts[2] ?? 0);

			return sprintf(
				'Historical backfill queued %d members and %d transactions. Member Database rows refreshed: %d.',
				$member_count,
				$transaction_count,
				$refresh_count
			);
		}

		if (0 === strpos($notice, 'jobs-cleared-')) {
			return 'Queue cleared. Removed jobs: ' . absint(substr($notice, strlen('jobs-cleared-')));
		}

		$messages = [
			'settings-saved' => 'Salesforce sync settings saved.',
			'settings-saved-reauthorize' => 'Salesforce settings saved. OAuth details changed, so the old authorization was cleared. Sign in again so the plugin does not keep trusting yesterday’s badge.',
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

	private function oauth_settings_changed($current, $updated) {
		$keys = ['auth_url', 'token_url', 'instance_url', 'api_version', 'oauth_scope', 'client_id', 'client_secret'];

		foreach ($keys as $key) {
			$current_value = (string) ($current['salesforce'][$key] ?? '');
			$updated_value = (string) ($updated['salesforce'][$key] ?? '');
			if ($current_value !== $updated_value) {
				return true;
			}
		}

		return false;
	}

	private function enqueue_historical_backfill($refresh_member_database, $include_transactions) {
		$user_ids = $this->get_historical_member_user_ids();
		$refreshed = 0;
		$settings = AAC_Salesforce_Sync_Settings::get_settings();
		$transaction_sync_enabled = !empty($settings['general']['sync_transaction']);

		if ($refresh_member_database && $user_ids) {
			$member_database = $this->get_member_database_sync_instance();
			if ($member_database instanceof AAC_Member_Portal_Member_Database) {
				foreach ($user_ids as $user_id) {
					if ($member_database->sync_member((int) $user_id)) {
						$refreshed++;
					}
				}
			}
		}

		foreach ($user_ids as $user_id) {
			$this->worker->enqueue_member_jobs((int) $user_id, 'historic_backfill');
		}

		$transaction_count = 0;
		if ($include_transactions && $transaction_sync_enabled) {
			foreach ($this->get_historical_transaction_rows() as $transaction_row) {
				$user_id = absint($transaction_row['user_id'] ?? 0);
				$order_id = absint($transaction_row['id'] ?? 0);
				if ($user_id <= 0 || $order_id <= 0) {
					continue;
				}

				$this->worker->enqueue_transaction_job($user_id, $order_id, 'historic_backfill');
				$transaction_count++;
			}
		}

		return [
			'members' => count($user_ids),
			'transactions' => $transaction_count,
			'refreshed' => $refreshed,
		];
	}

	private function get_historical_member_user_ids() {
		global $wpdb;

		if (!$wpdb) {
			return [];
		}

		$queries = [];

		if (!empty($wpdb->pmpro_memberships_users)) {
			$queries[] = "SELECT DISTINCT user_id FROM {$wpdb->pmpro_memberships_users} WHERE user_id > 0";
		}

		if (!empty($wpdb->pmpro_subscriptions)) {
			$queries[] = "SELECT DISTINCT user_id FROM {$wpdb->pmpro_subscriptions} WHERE user_id > 0";
		}

		if (!empty($wpdb->pmpro_membership_orders)) {
			$queries[] = "SELECT DISTINCT user_id FROM {$wpdb->pmpro_membership_orders} WHERE user_id > 0";
		}

		$queries[] = $wpdb->prepare(
			"SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key IN (%s, %s) AND meta_value <> ''",
			'aac_family_account_role',
			'aac_linked_parent_user_id'
		);

		$profile_table = $wpdb->prefix . 'aac_member_db_profiles';
		$table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $profile_table));
		if ($table_exists === $profile_table) {
			$queries[] = "SELECT DISTINCT user_id FROM {$profile_table} WHERE user_id > 0 AND (member_id <> '' OR membership_level <> '' OR membership_status <> '' OR account_role <> '' OR parent_user_id > 0)";
		}

		if (!$queries) {
			return [];
		}

		$sql = implode(' UNION ', $queries) . ' ORDER BY user_id ASC';
		$rows = $wpdb->get_col($sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- built from trusted table names and prepared fragments.
		$user_ids = array_values(array_unique(array_filter(array_map('absint', (array) $rows))));

		return $user_ids;
	}

	private function get_historical_transaction_rows() {
		global $wpdb;

		if (!$wpdb || empty($wpdb->pmpro_membership_orders)) {
			return [];
		}

		$rows = $wpdb->get_results(
			"SELECT id, user_id FROM {$wpdb->pmpro_membership_orders} WHERE user_id > 0 ORDER BY id ASC",
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- trusted core PMPro table name.

		return is_array($rows) ? $rows : [];
	}

	private function get_member_database_sync_instance() {
		if (!class_exists('AAC_Member_Portal_Member_Database')) {
			return null;
		}

		try {
			$reflection = new ReflectionClass('AAC_Member_Portal_Member_Database');
			return $reflection->newInstanceWithoutConstructor();
		} catch (ReflectionException $exception) {
			return null;
		}
	}

	private function render_field_mapping_table($title, $group, $object_definitions, $field_definitions, $field_catalog, $settings) {
		$definitions = isset($field_definitions[$group]) && is_array($field_definitions[$group]) ? $field_definitions[$group] : [];
		$object_definitions = is_array($object_definitions) ? $object_definitions : [];
		$selected_fields = isset($settings['field_mappings'][$group]) && is_array($settings['field_mappings'][$group]) ? $settings['field_mappings'][$group] : [];
		$catalog_objects = isset($field_catalog['objects']) && is_array($field_catalog['objects']) ? $field_catalog['objects'] : [];
		$object_fields_map = [];
		$catalog_errors = [];
		$has_salesforce_fields = false;

		foreach ($object_definitions as $object_key => $object_definition) {
			$catalog_entry = isset($catalog_objects[$object_key]) && is_array($catalog_objects[$object_key]) ? $catalog_objects[$object_key] : [];
			$fields = isset($catalog_entry['fields']) && is_array($catalog_entry['fields']) ? $catalog_entry['fields'] : [];
			if ($fields) {
				$has_salesforce_fields = true;
			}
			$object_fields_map[$object_key] = $fields;

			if (!empty($catalog_entry['error'])) {
				$catalog_errors[] = [
					'label' => (string) ($object_definition['label'] ?? $object_key),
					'object_name' => (string) ($object_definition['object_name'] ?? $object_key),
					'message' => (string) $catalog_entry['error'],
				];
			}
		}
		?>
		<div style="margin:20px 0 28px;border:1px solid #dcdcde;border-radius:8px;overflow:hidden;">
			<div style="padding:14px 16px;background:#f6f7f7;border-bottom:1px solid #dcdcde;">
				<strong><?php echo esc_html($title); ?></strong>
				<div style="margin-top:4px;color:#50575e;">
					<?php
					$object_summaries = [];
					foreach ($object_definitions as $object_definition) {
						$object_summaries[] = trim((string) ($object_definition['label'] ?? '')) . ' (' . trim((string) ($object_definition['object_name'] ?? '')) . ')';
					}
					echo esc_html($object_summaries ? implode(' • ', $object_summaries) : 'No Salesforce objects selected yet.');
					?>
				</div>
			</div>
			<?php if (empty($definitions)) : ?>
				<div style="padding:16px;color:#50575e;">
					No Member Database fields are available for this section yet. If this is a fresh setup, run `Sync All Members` in the Member Database so the mirror has something to work with.
				</div>
			<?php else : ?>
				<?php if (!$has_salesforce_fields) : ?>
					<div style="padding:16px;color:#50575e;border-bottom:1px solid #dcdcde;background:#fcfcfc;">
						Member Database fields are ready below. Salesforce dropdown options will appear after Salesforce authorizes and `Refresh Salesforce Fields` succeeds.
					</div>
				<?php endif; ?>
				<?php foreach ($catalog_errors as $catalog_error) : ?>
					<div style="padding:16px;color:#b32d2e;border-bottom:1px solid #dcdcde;background:#fff8f8;">
						Could not load fields for <code><?php echo esc_html($catalog_error['object_name']); ?></code> (<?php echo esc_html($catalog_error['label']); ?>): <?php echo esc_html($catalog_error['message']); ?>
					</div>
				<?php endforeach; ?>
				<table class="widefat striped" style="border:none;">
					<thead>
						<tr>
							<th style="width:30%;">Member Database Field</th>
							<th style="width:14%;">Field Type</th>
							<th style="width:22%;">Salesforce Object</th>
							<th>Salesforce Field</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($definitions as $field_key => $definition) : ?>
							<?php $selected_mapping = AAC_Salesforce_Sync_Settings::normalize_mapping_entry($selected_fields[$field_key] ?? [], $group); ?>
							<?php $selected_object_key = (string) ($selected_mapping['object'] ?? ''); ?>
							<?php $selected_salesforce_field = (string) ($selected_mapping['field'] ?? ''); ?>
							<tr>
								<td>
									<strong><?php echo esc_html((string) ($definition['label'] ?? $field_key)); ?></strong>
									<div style="margin-top:4px;color:#50575e;"><code><?php echo esc_html((string) ($definition['source_path'] ?? '')); ?></code></div>
								</td>
								<td><?php echo esc_html((string) ($definition['type'] ?? 'string')); ?></td>
								<td>
									<select class="aac-salesforce-object-select" data-group="<?php echo esc_attr($group); ?>" data-field-key="<?php echo esc_attr($field_key); ?>" name="<?php echo esc_attr(AAC_Salesforce_Sync_Settings::OPTION_KEY . '[field_mappings][' . $group . '][' . $field_key . '][object]'); ?>" style="min-width:220px;max-width:100%;">
										<option value="">Do not sync</option>
										<?php foreach ($object_definitions as $object_key => $object_definition) : ?>
											<option value="<?php echo esc_attr($object_key); ?>" <?php selected($selected_object_key, $object_key); ?>>
												<?php echo esc_html((string) ($object_definition['label'] ?? $object_key)); ?>
												<?php
												$object_name = trim((string) ($object_definition['object_name'] ?? ''));
												echo $object_name !== '' ? esc_html(' (' . $object_name . ')') : '';
												?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
								<td>
									<select class="aac-salesforce-field-select" data-selected="<?php echo esc_attr($selected_salesforce_field); ?>" data-field-options="<?php echo esc_attr(wp_json_encode($object_fields_map)); ?>" style="min-width:320px;max-width:100%;" name="<?php echo esc_attr(AAC_Salesforce_Sync_Settings::OPTION_KEY . '[field_mappings][' . $group . '][' . $field_key . '][field]'); ?>" <?php disabled(!$has_salesforce_fields); ?>>
										<option value=""><?php echo esc_html($has_salesforce_fields ? 'Select a Salesforce field' : 'Authorize and refresh Salesforce fields first'); ?></option>
									</select>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<script>
					(function() {
						const objectSelects = document.querySelectorAll('.aac-salesforce-object-select[data-group="<?php echo esc_js($group); ?>"]');
						const refreshFieldOptions = (objectSelect) => {
							const row = objectSelect.closest('tr');
							const fieldSelect = row ? row.querySelector('.aac-salesforce-field-select') : null;
							if (!fieldSelect) {
								return;
							}

							let fieldMap = {};
							try {
								fieldMap = JSON.parse(fieldSelect.dataset.fieldOptions || '{}');
							} catch (error) {
								fieldMap = {};
							}

							const selectedObjectKey = objectSelect.value || '';
							const selectedField = fieldSelect.dataset.selected || fieldSelect.value || '';
							const fields = selectedObjectKey && Array.isArray(fieldMap[selectedObjectKey]) ? fieldMap[selectedObjectKey] : [];

							fieldSelect.innerHTML = '';
							const blankOption = document.createElement('option');
							blankOption.value = '';
							blankOption.textContent = selectedObjectKey ? 'Do not map this field' : 'Select a Salesforce object first';
							fieldSelect.appendChild(blankOption);

							fields.forEach((field) => {
							const option = document.createElement('option');
							option.value = field.name || '';
							const fieldType = (field.type || '').trim();
							const fieldTypeLabel = fieldType ? ' • ' + fieldType : '';
							option.textContent = (field.label || field.name || '') + (field.name ? ' (' + field.name + fieldTypeLabel + ')' : '');
							if (option.value === selectedField) {
								option.selected = true;
							}
								fieldSelect.appendChild(option);
							});

							if (!selectedObjectKey) {
								fieldSelect.value = '';
							}
							fieldSelect.dataset.selected = fieldSelect.value || '';
						};

						objectSelects.forEach((objectSelect) => {
							refreshFieldOptions(objectSelect);
							objectSelect.addEventListener('change', () => {
								const row = objectSelect.closest('tr');
								const fieldSelect = row ? row.querySelector('.aac-salesforce-field-select') : null;
								if (fieldSelect) {
									fieldSelect.dataset.selected = '';
								}
								refreshFieldOptions(objectSelect);
							});
						});
					})();
				</script>
			<?php endif; ?>
		</div>
		<?php
	}

	private function build_field_map_export_rows($settings, $field_catalog, $field_definitions) {
		$group_labels = AAC_Salesforce_Sync_Settings::get_group_labels();
		$object_definitions = AAC_Salesforce_Sync_Settings::get_object_definitions($settings);
		$rows = [];

		foreach ($group_labels as $group => $group_label) {
			$definitions = isset($field_definitions[$group]) && is_array($field_definitions[$group]) ? $field_definitions[$group] : [];
			$selected_fields = isset($settings['field_mappings'][$group]) && is_array($settings['field_mappings'][$group]) ? $settings['field_mappings'][$group] : [];
			$group_objects = isset($object_definitions[$group]) && is_array($object_definitions[$group]) ? $object_definitions[$group] : [];

			foreach ($definitions as $field_key => $definition) {
				$mapping = AAC_Salesforce_Sync_Settings::normalize_mapping_entry($selected_fields[$field_key] ?? [], $group);
				$salesforce_field_name = trim((string) ($mapping['field'] ?? ''));
				$mapped_object_key = trim((string) ($mapping['object'] ?? ''));
				$mapped_object_definition = $mapped_object_key !== '' && isset($group_objects[$mapped_object_key]) ? $group_objects[$mapped_object_key] : [];
				$salesforce_object = (string) ($mapped_object_definition['object_name'] ?? '');
				$salesforce_metadata = $this->get_catalog_field_metadata($field_catalog, $mapped_object_key, $salesforce_field_name);

				$rows[] = [
					$group,
					$group_label,
					$salesforce_object,
					$field_key,
					(string) ($definition['label'] ?? $field_key),
					(string) ($definition['source_path'] ?? ''),
					(string) ($definition['type'] ?? 'string'),
					$salesforce_field_name,
					(string) ($salesforce_metadata['label'] ?? ''),
					(string) ($salesforce_metadata['type'] ?? ''),
					$salesforce_field_name !== '' ? 'yes' : 'no',
					wp_json_encode($definition),
					wp_json_encode($salesforce_metadata),
				];
			}
		}

		return $rows;
	}

	private function get_catalog_field_metadata($field_catalog, $object_key, $field_name) {
		if ($object_key === '' || $field_name === '') {
			return [];
		}

		$catalog_entry = isset($field_catalog['objects'][$object_key]) && is_array($field_catalog['objects'][$object_key])
			? $field_catalog['objects'][$object_key]
			: [];
		$fields = isset($catalog_entry['fields']) && is_array($catalog_entry['fields']) ? $catalog_entry['fields'] : [];

		foreach ($fields as $field) {
			if (!is_array($field)) {
				continue;
			}

			if (trim((string) ($field['name'] ?? '')) === $field_name) {
				return $field;
			}
		}

		return [];
	}
}
