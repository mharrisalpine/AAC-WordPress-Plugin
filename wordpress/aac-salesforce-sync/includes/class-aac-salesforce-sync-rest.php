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
			$this->worker->enqueue_transaction_job($user_id, absint($payload['order_id'] ?? 0), 'salesforce');
		} elseif ('membership' === $type) {
			$this->worker->enqueue_membership_job($user_id, 'salesforce');
		} else {
			$this->worker->enqueue_member_jobs($user_id, 'salesforce');
		}

		return new WP_REST_Response(['success' => true], 200);
	}
}
