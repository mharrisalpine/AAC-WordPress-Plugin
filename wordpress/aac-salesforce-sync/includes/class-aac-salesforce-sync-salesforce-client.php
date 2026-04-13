<?php

if (!defined('ABSPATH')) {
	exit;
}

class AAC_Salesforce_Sync_Salesforce_Client {
	const TOKEN_TRANSIENT = 'aac_salesforce_sync_access_token';

	private $settings;

	public function __construct() {
		$this->settings = AAC_Salesforce_Sync_Settings::get_settings();
	}

	public function is_configured() {
		return !empty($this->settings['salesforce']['token_url'])
			&& !empty($this->settings['salesforce']['instance_url'])
			&& !empty($this->settings['salesforce']['client_id'])
			&& !empty($this->settings['salesforce']['client_secret']);
	}

	public function upsert($object_name, $external_id_field, $external_id_value, $payload) {
		if (!$this->is_configured()) {
			throw new RuntimeException('Salesforce sync is not configured.');
		}

		if ('' === trim((string) $external_id_value)) {
			throw new RuntimeException('Missing external ID value for Salesforce upsert.');
		}

		$path = sprintf(
			'/services/data/v%s/sobjects/%s/%s/%s',
			rawurlencode($this->settings['salesforce']['api_version']),
			rawurlencode($object_name),
			rawurlencode($external_id_field),
			rawurlencode((string) $external_id_value)
		);

		return $this->request('PATCH', $path, $payload);
	}

	public function request($method, $path, $body = null) {
		$token = $this->get_access_token();
		$url = untrailingslashit($this->settings['salesforce']['instance_url']) . $path;
		$args = [
			'method' => strtoupper($method),
			'timeout' => 20,
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
				'Content-Type' => 'application/json',
				'Accept' => 'application/json',
			],
		];

		if (null !== $body) {
			$args['body'] = wp_json_encode($body);
		}

		$response = wp_remote_request($url, $args);
		if (is_wp_error($response)) {
			throw new RuntimeException($response->get_error_message());
		}

		$status_code = (int) wp_remote_retrieve_response_code($response);
		$raw_body = wp_remote_retrieve_body($response);

		if ($status_code >= 400) {
			$message = $raw_body ?: 'Unknown Salesforce API error.';
			throw new RuntimeException('Salesforce API error: ' . $message);
		}

		if (!$raw_body) {
			return [];
		}

		$decoded = json_decode($raw_body, true);
		return is_array($decoded) ? $decoded : ['raw' => $raw_body];
	}

	private function get_access_token() {
		$cached = get_transient(self::TOKEN_TRANSIENT);
		if (is_array($cached) && !empty($cached['access_token'])) {
			return $cached['access_token'];
		}

		$response = wp_remote_post(
			$this->settings['salesforce']['token_url'],
			[
				'timeout' => 20,
				'body' => [
					'grant_type' => 'client_credentials',
					'client_id' => $this->settings['salesforce']['client_id'],
					'client_secret' => $this->settings['salesforce']['client_secret'],
				],
			]
		);

		if (is_wp_error($response)) {
			throw new RuntimeException($response->get_error_message());
		}

		$body = json_decode(wp_remote_retrieve_body($response), true);
		if (!is_array($body) || empty($body['access_token'])) {
			throw new RuntimeException('Could not retrieve Salesforce access token.');
		}

		$expires_in = !empty($body['expires_in']) ? max(60, absint($body['expires_in']) - 60) : 15 * MINUTE_IN_SECONDS;
		set_transient(
			self::TOKEN_TRANSIENT,
			['access_token' => $body['access_token']],
			$expires_in
		);

		return $body['access_token'];
	}
}
