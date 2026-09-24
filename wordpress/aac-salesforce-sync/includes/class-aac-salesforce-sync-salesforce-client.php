<?php

if (!defined('ABSPATH')) {
	exit;
}

class AAC_Salesforce_Sync_Exception extends RuntimeException {
	private $retryable;
	private $retry_after;

	public function __construct($message, $retryable = true, $retry_after = 0, $code = 0, ?Throwable $previous = null) {
		parent::__construct((string) $message, (int) $code, $previous);
		$this->retryable = (bool) $retryable;
		$this->retry_after = max(0, (int) $retry_after);
	}

	public function is_retryable() {
		return $this->retryable;
	}

	public function get_retry_after() {
		return $this->retry_after;
	}
}

class AAC_Salesforce_Sync_Salesforce_Client {
	const TOKEN_TRANSIENT = 'aac_salesforce_sync_access_token';

	private $settings;

	public function __construct() {
		$this->settings = AAC_Salesforce_Sync_Settings::get_settings();
	}

	public function is_configured() {
		return !empty($this->settings['salesforce']['auth_url'])
			&& !empty($this->settings['salesforce']['token_url'])
			&& !empty($this->settings['salesforce']['client_id'])
			&& !empty($this->settings['salesforce']['client_secret']);
	}

	public function can_authorize() {
		return !empty($this->settings['salesforce']['auth_url'])
			&& !empty($this->settings['salesforce']['token_url'])
			&& !empty($this->settings['salesforce']['client_id'])
			&& !empty($this->settings['salesforce']['client_secret']);
	}

	public static function clear_cached_token() {
		delete_transient(self::TOKEN_TRANSIENT);
	}

	public function get_authorization_url($state, $code_challenge = '') {
		if (!$this->can_authorize()) {
			throw new RuntimeException('Salesforce OAuth settings are incomplete.');
		}

		$query = [
			'response_type' => 'code',
			'client_id' => $this->settings['salesforce']['client_id'],
			'redirect_uri' => AAC_Salesforce_Sync_Settings::get_oauth_redirect_uri(),
			'state' => $state,
			'scope' => trim((string) ($this->settings['salesforce']['oauth_scope'] ?? 'api refresh_token offline_access')),
		];

		if ('' !== trim((string) $code_challenge)) {
			$query['code_challenge'] = (string) $code_challenge;
			$query['code_challenge_method'] = 'S256';
		}

		return add_query_arg($query, $this->settings['salesforce']['auth_url']);
	}

	public function exchange_authorization_code($code, $code_verifier = '') {
		if (!$this->can_authorize()) {
			throw new RuntimeException('Salesforce OAuth settings are incomplete.');
		}

		$body = [
			'grant_type' => 'authorization_code',
			'code' => $code,
			'client_id' => $this->settings['salesforce']['client_id'],
			'client_secret' => $this->settings['salesforce']['client_secret'],
			'redirect_uri' => AAC_Salesforce_Sync_Settings::get_oauth_redirect_uri(),
		];

		if ('' !== trim((string) $code_verifier)) {
			$body['code_verifier'] = (string) $code_verifier;
		}

		$response = wp_remote_post(
			$this->settings['salesforce']['token_url'],
			[
				'timeout' => 20,
				'body' => $body,
			]
		);

		$payload = $this->decode_token_response($response);
		$this->persist_auth_payload($payload);

		return $payload;
	}

	public function upsert($object_name, $external_id_field, $external_id_value, $payload) {
		if (!$this->is_configured()) {
			throw new RuntimeException('Salesforce sync is not configured.');
		}

		if ('' === trim((string) $external_id_value)) {
			throw new RuntimeException('Missing external ID value for Salesforce upsert.');
		}

		// Salesforce is happy to upsert by external ID, but it throws a fit if we
		// also try to sneak the record Id into the request body. If an admin maps a
		// local field to Contact.Id by accident, we quietly drop it here instead of
		// sending the whole queue to dead letter camp.
		if (is_array($payload)) {
			unset($payload['Id'], $payload['id'], $payload['IndividualId']);

			// The external ID travels in the URL for the PATCH upsert route. Sending
			// it again in the body makes Salesforce grumpy, so we strip that too.
			if ($external_id_field !== '') {
				unset($payload[$external_id_field]);
			}
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

	public function test_connection() {
		return $this->request(
			'GET',
			sprintf('/services/data/v%s/limits', rawurlencode($this->settings['salesforce']['api_version']))
		);
	}

	public function describe_object($object_name) {
		if ('' === trim((string) $object_name)) {
			throw new RuntimeException('Salesforce object name is required to load fields.');
		}

		$path = sprintf(
			'/services/data/v%s/sobjects/%s/describe',
			rawurlencode($this->settings['salesforce']['api_version']),
			rawurlencode($object_name)
		);

		return $this->request('GET', $path);
	}

	public function query($soql) {
		$soql = trim((string) $soql);
		if ($soql === '') {
			throw new RuntimeException('SOQL query is required.');
		}

		$path = sprintf(
			'/services/data/v%s/query?q=%s',
			rawurlencode($this->settings['salesforce']['api_version']),
			rawurlencode($soql)
		);

		return $this->request('GET', $path);
	}

	public function find_record_id_by_field($object_name, $field_name, $field_value, $field_type = '') {
		$object_name = $this->sanitize_soql_identifier($object_name);
		$field_name = $this->sanitize_soql_identifier($field_name);
		if ($object_name === '' || $field_name === '') {
			return '';
		}

		$literal = $this->build_soql_literal($field_value, $field_type);
		if ($literal === '') {
			return '';
		}

		$soql = sprintf(
			'SELECT Id FROM %s WHERE %s = %s ORDER BY LastModifiedDate DESC LIMIT 1',
			$object_name,
			$field_name,
			$literal
		);

		$result = $this->query($soql);
		$records = is_array($result['records'] ?? null) ? $result['records'] : [];
		if (empty($records[0]['Id'])) {
			return '';
		}

		return sanitize_text_field((string) $records[0]['Id']);
	}

	public function get_record_by_id($object_name, $record_id, array $fields) {
		$object_name = $this->sanitize_soql_identifier($object_name);
		$record_id = trim((string) $record_id);
		$sanitized_fields = [];
		foreach ($fields as $field_name) {
			$field_name = $this->sanitize_soql_identifier($field_name);
			if ($field_name !== '') {
				$sanitized_fields[] = $field_name;
			}
		}

		$sanitized_fields = array_values(array_unique($sanitized_fields));
		if ($object_name === '' || $record_id === '' || empty($sanitized_fields)) {
			return [];
		}

		$soql = sprintf(
			'SELECT %s FROM %s WHERE Id = %s LIMIT 1',
			implode(', ', $sanitized_fields),
			$object_name,
			$this->build_soql_literal($record_id, 'id')
		);

		$result = $this->query($soql);
		$records = is_array($result['records'] ?? null) ? $result['records'] : [];
		if (empty($records[0]) || !is_array($records[0])) {
			return [];
		}

		return $records[0];
	}

	public function update_record($object_name, $record_id, $payload) {
		$object_name = trim((string) $object_name);
		$record_id = trim((string) $record_id);
		if ($object_name === '' || $record_id === '') {
			throw new RuntimeException('Salesforce object name and record ID are required for update.');
		}

		if (is_array($payload)) {
			unset($payload['Id'], $payload['id'], $payload['IndividualId']);
		}

		$path = sprintf(
			'/services/data/v%s/sobjects/%s/%s',
			rawurlencode($this->settings['salesforce']['api_version']),
			rawurlencode($object_name),
			rawurlencode($record_id)
		);

		return $this->request('PATCH', $path, $payload);
	}

	public function request($method, $path, $body = null, $allow_auth_retry = true) {
		$token = $this->get_access_token();
		$url = untrailingslashit($this->get_instance_url()) . $path;
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
			throw new AAC_Salesforce_Sync_Exception($response->get_error_message(), true);
		}

		$status_code = (int) wp_remote_retrieve_response_code($response);
		$raw_body = wp_remote_retrieve_body($response);

		if ($status_code === 401 && $allow_auth_retry) {
			$this->invalidate_access_token();
			return $this->request($method, $path, $body, false);
		}

		if ($status_code >= 400) {
			$message = $this->summarize_salesforce_error_response($raw_body);
			if ($message === '') {
				$message = 'Unknown Salesforce API error.';
			}
			$debug_payload = $this->summarize_debug_payload($body);
			if ($debug_payload !== '') {
				$message .= ' | Request ' . $debug_payload;
			}
			$error_code = $this->extract_salesforce_error_code($raw_body);
			$retryable = $this->is_retryable_response($status_code, $error_code);
			$retry_after = $this->parse_retry_after(wp_remote_retrieve_header($response, 'retry-after'));
			throw new AAC_Salesforce_Sync_Exception('Salesforce API error: ' . $message, $retryable, $retry_after, $status_code);
		}

		if (!$raw_body) {
			return [];
		}

		$decoded = json_decode($raw_body, true);
		return is_array($decoded) ? $decoded : ['raw' => $raw_body];
	}

	private function invalidate_access_token() {
		$auth_state = AAC_Salesforce_Sync_Settings::get_auth_state();
		if (is_array($auth_state)) {
			$auth_state['access_token'] = '';
			$auth_state['expires_at'] = 0;
			AAC_Salesforce_Sync_Settings::update_auth_state($auth_state);
		}
		self::clear_cached_token();
	}

	private function extract_salesforce_error_code($raw_body) {
		$decoded = json_decode((string) $raw_body, true);
		if (!is_array($decoded)) {
			return '';
		}
		$errors = $this->is_list_array($decoded) ? $decoded : [$decoded];
		foreach ($errors as $error) {
			if (!is_array($error)) {
				continue;
			}
			$code = sanitize_key((string) ($error['errorCode'] ?? ($error['error'] ?? '')));
			if ($code !== '') {
				return strtoupper($code);
			}
		}
		return '';
	}

	private function is_retryable_response($status_code, $error_code = '') {
		$status_code = (int) $status_code;
		$error_code = strtoupper((string) $error_code);
		if (in_array($error_code, ['REQUEST_LIMIT_EXCEEDED', 'SERVER_UNAVAILABLE', 'UNKNOWN_EXCEPTION'], true)) {
			return true;
		}
		return in_array($status_code, [401, 408, 409, 425, 429], true) || $status_code >= 500;
	}

	private function parse_retry_after($value) {
		$value = trim((string) $value);
		if ($value === '') {
			return 0;
		}
		if (ctype_digit($value)) {
			return min(DAY_IN_SECONDS, absint($value));
		}
		$timestamp = strtotime($value);
		return $timestamp ? min(DAY_IN_SECONDS, max(0, $timestamp - time())) : 0;
	}

	private function get_access_token() {
		$auth_state = AAC_Salesforce_Sync_Settings::get_auth_state();
		if (!empty($auth_state['access_token']) && !empty($auth_state['expires_at']) && (int) $auth_state['expires_at'] > (time() + 60)) {
			return $auth_state['access_token'];
		}

		if (!empty($auth_state['refresh_token'])) {
			$refreshed = $this->refresh_access_token($auth_state['refresh_token']);
			if (!empty($refreshed['access_token'])) {
				return $refreshed['access_token'];
			}
		}

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

		$body = $this->decode_token_response($response);
		$expires_in = !empty($body['expires_in']) ? max(60, absint($body['expires_in']) - 60) : 15 * MINUTE_IN_SECONDS;
		set_transient(
			self::TOKEN_TRANSIENT,
			[
				'access_token' => $body['access_token'],
				'instance_url' => !empty($body['instance_url']) ? esc_url_raw((string) $body['instance_url']) : '',
			],
			$expires_in
		);

		return $body['access_token'];
	}

	private function refresh_access_token($refresh_token) {
		$response = wp_remote_post(
			$this->settings['salesforce']['token_url'],
			[
				'timeout' => 20,
				'body' => [
					'grant_type' => 'refresh_token',
					'refresh_token' => $refresh_token,
					'client_id' => $this->settings['salesforce']['client_id'],
					'client_secret' => $this->settings['salesforce']['client_secret'],
				],
			]
		);

		$payload = $this->decode_token_response($response);
		if (empty($payload['refresh_token'])) {
			$payload['refresh_token'] = $refresh_token;
		}
		$this->persist_auth_payload($payload);

		return $payload;
	}

	private function decode_token_response($response) {
		if (is_wp_error($response)) {
			throw new RuntimeException($response->get_error_message());
		}

		$status_code = (int) wp_remote_retrieve_response_code($response);
		$raw_body = wp_remote_retrieve_body($response);
		$body = json_decode($raw_body, true);
		if ($status_code >= 400) {
			$message = $raw_body;
			if (is_array($body)) {
				$parts = array_filter([
					isset($body['error']) ? (string) $body['error'] : '',
					isset($body['error_description']) ? (string) $body['error_description'] : '',
				]);
				$message = $parts ? implode(': ', $parts) : wp_json_encode($body);
			}
			throw new RuntimeException('Could not retrieve Salesforce token: ' . $message);
		}

		if (!is_array($body) || empty($body['access_token'])) {
			throw new RuntimeException('Could not retrieve Salesforce access token.');
		}

		return $body;
	}

	private function summarize_debug_payload($payload) {
		if (!is_array($payload) || !$payload) {
			return '';
		}

		$fields = $this->collect_payload_field_names($payload);
		if (!$fields) {
			return '';
		}

		$extra_count = max(0, count($fields) - 50);
		$fields = array_slice($fields, 0, 50);

		$summary = 'fields: ' . implode(', ', $fields);
		if ($extra_count > 0) {
			$summary .= ', +' . $extra_count . ' more';
		}

		return $summary;
	}

	private function summarize_salesforce_error_response($raw_body) {
		$raw_body = trim((string) $raw_body);
		if ($raw_body === '') {
			return '';
		}

		$decoded = json_decode($raw_body, true);
		if (!is_array($decoded)) {
			return $this->redact_error_message($raw_body);
		}

		$errors = $this->is_list_array($decoded) ? $decoded : [$decoded];
		$parts = [];

		foreach ($errors as $error) {
			if (!is_array($error)) {
				continue;
			}

			$code = isset($error['errorCode']) ? sanitize_text_field((string) $error['errorCode']) : '';
			if ($code === '' && isset($error['error'])) {
				$code = sanitize_text_field((string) $error['error']);
			}

			$message = isset($error['message']) ? $this->redact_error_message((string) $error['message']) : '';
			if ($message === '' && isset($error['error_description'])) {
				$message = $this->redact_error_message((string) $error['error_description']);
			}

			$fields = [];
			if (isset($error['fields']) && is_array($error['fields'])) {
				foreach ($error['fields'] as $field) {
					$field = $this->sanitize_debug_field_name((string) $field);
					if ($field !== '') {
						$fields[] = $field;
					}
				}
			}

			$entry = trim($code . ($message !== '' ? ': ' . $message : ''), ': ');
			if ($fields) {
				$entry .= ' (fields: ' . implode(', ', array_values(array_unique($fields))) . ')';
			}

			if ($entry !== '') {
				$parts[] = $entry;
			}
		}

		return implode(' | ', array_slice($parts, 0, 5));
	}

	private function collect_payload_field_names($value, $prefix = '') {
		if (!is_array($value)) {
			return $prefix !== '' ? [$prefix] : [];
		}

		$fields = [];
		$is_list = $this->is_list_array($value);

		foreach ($value as $key => $child_value) {
			$child_prefix = $prefix;
			if ($is_list) {
				$child_prefix .= '[]';
			} else {
				$key = $this->sanitize_debug_field_name((string) $key);
				if ($key === '') {
					continue;
				}
				$child_prefix = $prefix !== '' ? $prefix . '.' . $key : $key;
			}

			$fields = array_merge($fields, $this->collect_payload_field_names($child_value, $child_prefix));
		}

		return array_values(array_unique(array_filter($fields)));
	}

	private function sanitize_debug_field_name($field) {
		$field = trim((string) $field);
		if ($field === '') {
			return '';
		}

		$field = preg_replace('/[^A-Za-z0-9_\\.\\[\\]]/', '', $field);
		return is_string($field) ? substr($field, 0, 120) : '';
	}

	private function redact_error_message($message) {
		$message = wp_strip_all_tags((string) $message);
		$message = preg_replace('/[A-Z0-9._%+\\-]+@[A-Z0-9.\\-]+\\.[A-Z]{2,}/i', '[redacted-email]', $message);
		$message = preg_replace('/\\b(?:\\+?1[\\s.\\-]?)?(?:\\(?\\d{3}\\)?[\\s.\\-]?)\\d{3}[\\s.\\-]?\\d{4}\\b/', '[redacted-phone]', $message);
		$message = preg_replace('/\\b(?:sk|pk|rk|whsec)_(?:live|test)_[A-Za-z0-9_]+\\b/', '[redacted-token]', $message);
		$message = preg_replace('/\\bBearer\\s+[A-Za-z0-9._\\-]+/i', 'Bearer [redacted-token]', $message);
		$message = preg_replace('/\\s+/', ' ', $message);

		$message = trim((string) $message);
		if (strlen($message) > 600) {
			$message = substr($message, 0, 600) . '...';
		}

		return $message;
	}

	private function is_list_array($value) {
		if (!is_array($value)) {
			return false;
		}

		if ($value === []) {
			return true;
		}

		$keys = array_keys($value);
		return $keys === range(0, count($value) - 1);
	}

	private function sanitize_soql_identifier($identifier) {
		$identifier = trim((string) $identifier);
		if ($identifier === '' || !preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $identifier)) {
			return '';
		}

		return $identifier;
	}

	private function build_soql_literal($value, $field_type = '') {
		if ($value === null || $value === '') {
			return '';
		}

		$field_type = strtolower(trim((string) $field_type));
		if (is_bool($value) || $field_type === 'boolean') {
			return !empty($value) ? 'TRUE' : 'FALSE';
		}

		$string_value = trim((string) $value);
		if ($string_value === '') {
			return '';
		}

		if (
			in_array($field_type, ['int', 'integer', 'double', 'currency', 'percent'], true)
			&& is_numeric($string_value)
		) {
			return $string_value;
		}

		return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $string_value) . "'";
	}

	private function persist_auth_payload($payload) {
		$payload = is_array($payload) ? $payload : [];
		$existing = AAC_Salesforce_Sync_Settings::get_auth_state();
		$expires_in = !empty($payload['expires_in']) ? absint($payload['expires_in']) : HOUR_IN_SECONDS;
		$state = [
			'access_token' => sanitize_text_field((string) ($payload['access_token'] ?? '')),
			'refresh_token' => sanitize_text_field((string) ($payload['refresh_token'] ?? ($existing['refresh_token'] ?? ''))),
			'instance_url' => esc_url_raw((string) ($payload['instance_url'] ?? ($existing['instance_url'] ?? $this->settings['salesforce']['instance_url']))),
			'id_url' => esc_url_raw((string) ($payload['id'] ?? ($existing['id_url'] ?? ''))),
			'signature' => sanitize_text_field((string) ($payload['signature'] ?? ($existing['signature'] ?? ''))),
			'scope' => sanitize_text_field((string) ($payload['scope'] ?? ($existing['scope'] ?? ''))),
			'token_type' => sanitize_text_field((string) ($payload['token_type'] ?? ($existing['token_type'] ?? 'Bearer'))),
			'issued_at' => sanitize_text_field((string) ($payload['issued_at'] ?? ($existing['issued_at'] ?? ''))),
			'connected_at' => current_time('mysql'),
			'expires_at' => time() + max(60, $expires_in - 60),
		];

		AAC_Salesforce_Sync_Settings::update_auth_state($state);
		self::clear_cached_token();
	}

	private function get_instance_url() {
		$auth_state = AAC_Salesforce_Sync_Settings::get_auth_state();
		if (!empty($auth_state['instance_url'])) {
			return (string) $auth_state['instance_url'];
		}

		$cached = get_transient(self::TOKEN_TRANSIENT);
		if (is_array($cached) && !empty($cached['instance_url'])) {
			return (string) $cached['instance_url'];
		}

		if (!empty($this->settings['salesforce']['instance_url'])) {
			return (string) $this->settings['salesforce']['instance_url'];
		}

		throw new RuntimeException('Salesforce instance URL is not configured.');
	}
}
