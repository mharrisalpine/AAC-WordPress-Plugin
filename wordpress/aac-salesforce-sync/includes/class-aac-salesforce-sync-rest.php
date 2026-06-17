<?php

if (!defined('ABSPATH')) {
	exit;
}

class AAC_Salesforce_Sync_REST {
	private $worker;

	public function __construct(AAC_Salesforce_Sync_Worker $worker) {
		$this->worker = $worker;
		add_action('rest_api_init', [$this, 'register_routes']);
	}

	public function register_routes() {
		register_rest_route('aac-salesforce-sync/v1', '/oauth/callback', [
			'methods' => WP_REST_Server::READABLE,
			'callback' => [$this, 'handle_oauth_callback'],
			'permission_callback' => '__return_true',
		]);

		register_rest_route('aac-salesforce-sync/v1', '/contact', [
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => [$this, 'handle_contact_sync'],
			'permission_callback' => [$this, 'check_secret'],
		]);

		register_rest_route('aac-salesforce-sync/v1', '/membership', [
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => [$this, 'handle_membership_sync'],
			'permission_callback' => [$this, 'check_secret'],
		]);

		register_rest_route('aac-salesforce-sync/v1', '/enqueue', [
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => [$this, 'handle_enqueue_sync'],
			'permission_callback' => [$this, 'check_secret'],
		]);
	}

	public function check_secret(WP_REST_Request $request) {
		$settings = AAC_Salesforce_Sync_Settings::get_settings();
		$expected = (string) ($settings['inbound']['secret'] ?? '');

		if ($expected === '') {
			return new WP_Error('aac_salesforce_sync_missing_secret', 'Inbound secret has not been configured.', ['status' => 500]);
		}

		$provided = (string) $request->get_header('X-AAC-SF-Secret');
		if ($provided === '') {
			$provided = (string) $request->get_param('secret');
		}

		if (!hash_equals($expected, $provided)) {
			return new WP_Error('aac_salesforce_sync_forbidden', 'Forbidden', ['status' => 403]);
		}

		return true;
	}

	public function handle_contact_sync(WP_REST_Request $request) {
		try {
			$user_id = $this->worker->sync_contact_from_salesforce((array) $request->get_json_params());
			return new WP_REST_Response(['success' => true, 'user_id' => $user_id], 200);
		} catch (Exception $exception) {
			return new WP_REST_Response(['success' => false, 'message' => $exception->getMessage()], 400);
		}
	}

	public function handle_oauth_callback(WP_REST_Request $request) {
		$error = sanitize_text_field((string) $request->get_param('error'));
		$error_description = sanitize_text_field((string) $request->get_param('error_description'));
		$received_state = sanitize_text_field((string) $request->get_param('state'));
		$code = sanitize_text_field((string) $request->get_param('code'));
		AAC_Salesforce_Sync_Settings::update_oauth_debug([
			'last_seen_at' => current_time('mysql'),
			'callback_url' => AAC_Salesforce_Sync_Settings::get_oauth_redirect_uri(),
			'state_received' => $received_state,
			'code_received' => $code !== '',
			'error' => $error,
			'error_description' => $error_description,
			'stage' => 'callback_received',
		]);

		if ($error !== '') {
			$message = $error_description !== '' ? $error . ': ' . $error_description : $error;
			AAC_Salesforce_Sync_Settings::update_oauth_debug([
				'last_seen_at' => current_time('mysql'),
				'callback_url' => AAC_Salesforce_Sync_Settings::get_oauth_redirect_uri(),
				'state_received' => $received_state,
				'code_received' => $code !== '',
				'error' => $error,
				'error_description' => $error_description,
				'stage' => 'salesforce_error',
			]);
			$this->redirect_to_admin_notice('salesforce-connect-error:' . rawurlencode($message), 'authorize');
		}

		$state_payload = $received_state ? get_transient(AAC_Salesforce_Sync_Admin::OAUTH_STATE_TRANSIENT . $received_state) : false;

		if ($received_state) {
			delete_transient(AAC_Salesforce_Sync_Admin::OAUTH_STATE_TRANSIENT . $received_state);
		}

		if (!$state_payload || $received_state === '') {
			AAC_Salesforce_Sync_Settings::update_oauth_debug([
				'last_seen_at' => current_time('mysql'),
				'callback_url' => AAC_Salesforce_Sync_Settings::get_oauth_redirect_uri(),
				'state_received' => $received_state,
				'code_received' => $code !== '',
				'stage' => 'state_check_failed',
			]);
			$this->redirect_to_admin_notice('salesforce-connect-error:' . rawurlencode('Salesforce OAuth state check failed.'), 'authorize');
		}

		if ($code === '') {
			AAC_Salesforce_Sync_Settings::update_oauth_debug([
				'last_seen_at' => current_time('mysql'),
				'callback_url' => AAC_Salesforce_Sync_Settings::get_oauth_redirect_uri(),
				'state_received' => $received_state,
				'code_received' => false,
				'stage' => 'missing_code',
			]);
			$this->redirect_to_admin_notice('salesforce-connect-error:' . rawurlencode('Salesforce did not return an authorization code.'), 'authorize');
		}

		try {
			$client = new AAC_Salesforce_Sync_Salesforce_Client();
			$client->exchange_authorization_code($code);
			AAC_Salesforce_Sync_Settings::update_oauth_debug([
				'last_seen_at' => current_time('mysql'),
				'callback_url' => AAC_Salesforce_Sync_Settings::get_oauth_redirect_uri(),
				'state_received' => $received_state,
				'code_received' => true,
				'stage' => 'authorized',
			]);
			$this->redirect_to_admin_notice('salesforce-authorized', 'authorize');
		} catch (Exception $exception) {
			AAC_Salesforce_Sync_Settings::update_oauth_debug([
				'last_seen_at' => current_time('mysql'),
				'callback_url' => AAC_Salesforce_Sync_Settings::get_oauth_redirect_uri(),
				'state_received' => $received_state,
				'code_received' => true,
				'stage' => 'token_exchange_failed',
				'message' => $exception->getMessage(),
			]);
			$this->redirect_to_admin_notice('salesforce-connect-error:' . rawurlencode($exception->getMessage()), 'authorize');
		}
	}

	public function handle_membership_sync(WP_REST_Request $request) {
		try {
			$user_id = $this->worker->sync_membership_from_salesforce((array) $request->get_json_params());
			return new WP_REST_Response(['success' => true, 'user_id' => $user_id], 200);
		} catch (Exception $exception) {
			return new WP_REST_Response(['success' => false, 'message' => $exception->getMessage()], 400);
		}
	}

	public function handle_enqueue_sync(WP_REST_Request $request) {
		$payload = (array) $request->get_json_params();
		$user_id = absint($payload['user_id'] ?? 0);
		$type = sanitize_key($payload['type'] ?? 'member');

		if ($user_id <= 0) {
			return new WP_REST_Response(['success' => false, 'message' => 'user_id is required.'], 400);
		}

		if ('transaction' === $type) {
			return new WP_REST_Response([
				'success' => false,
				'message' => 'Salesforce inbound sync may update member profile and membership access data only. Transactions remain WordPress/PMPro outbound-only.',
			], 400);
		} elseif ('membership' === $type) {
			$this->worker->enqueue_membership_job($user_id, 'salesforce');
		} else {
			$this->worker->enqueue_member_jobs($user_id, 'salesforce');
		}

		return new WP_REST_Response(['success' => true], 200);
	}

	private function redirect_to_admin_notice($notice, $tab = 'authorize') {
		$target = add_query_arg([
			'page' => AAC_Salesforce_Sync_Settings::PAGE_SLUG,
			AAC_Salesforce_Sync_Admin::TAB_QUERY_ARG => $tab,
			AAC_Salesforce_Sync_Admin::NOTICE_QUERY_ARG => $notice,
		], admin_url(class_exists('AAC_Member_Portal_Admin') ? 'admin.php' : 'tools.php'));

		if (!is_user_logged_in()) {
			$target = wp_login_url($target);
		}

		wp_safe_redirect($target);
		exit;
	}
}
