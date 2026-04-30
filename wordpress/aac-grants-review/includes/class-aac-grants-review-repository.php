<?php

if (!defined('ABSPATH')) {
	exit;
}

class AAC_Grants_Review_Repository {
	const STATUS_SUBMITTED = 'submitted';
	const STATUS_ELIGIBILITY = 'eligibility_review';
	const STATUS_COMMITTEE = 'committee_review';
	const STATUS_NEEDS_REVISION = 'needs_revision';
	const STATUS_APPROVED = 'approved';
	const STATUS_REJECTED = 'rejected';

	private $settings;

	public function __construct($settings) {
		$this->settings = $settings;
	}

	public static function applications_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'aac_grant_review_applications';
	}

	public static function history_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'aac_grant_review_history';
	}

	public static function get_workflow_labels() {
		return [
			self::STATUS_SUBMITTED => 'Submitted',
			self::STATUS_ELIGIBILITY => 'Eligibility Review',
			self::STATUS_COMMITTEE => 'Committee Review',
			self::STATUS_NEEDS_REVISION => 'Needs Revision',
			self::STATUS_APPROVED => 'Approved',
			self::STATUS_REJECTED => 'Rejected',
		];
	}

	public static function get_workflow_transitions() {
		return [
			self::STATUS_SUBMITTED => [self::STATUS_ELIGIBILITY],
			self::STATUS_ELIGIBILITY => [self::STATUS_COMMITTEE, self::STATUS_NEEDS_REVISION, self::STATUS_REJECTED],
			self::STATUS_COMMITTEE => [self::STATUS_APPROVED, self::STATUS_NEEDS_REVISION, self::STATUS_REJECTED],
			self::STATUS_NEEDS_REVISION => [self::STATUS_ELIGIBILITY, self::STATUS_REJECTED],
			self::STATUS_APPROVED => [],
			self::STATUS_REJECTED => [self::STATUS_ELIGIBILITY],
		];
	}

	public static function get_status_badge_class($status) {
		switch ($status) {
			case self::STATUS_APPROVED:
				return 'aac-grants-review__badge aac-grants-review__badge--approved';
			case self::STATUS_REJECTED:
				return 'aac-grants-review__badge aac-grants-review__badge--rejected';
			case self::STATUS_NEEDS_REVISION:
				return 'aac-grants-review__badge aac-grants-review__badge--warning';
			case self::STATUS_ELIGIBILITY:
			case self::STATUS_COMMITTEE:
				return 'aac-grants-review__badge aac-grants-review__badge--active';
			default:
				return 'aac-grants-review__badge';
		}
	}

	public function get_applications($args = []) {
		global $wpdb;

		$where = ['1=1'];
		$params = [];

		if (!empty($args['status'])) {
			$where[] = 'workflow_status = %s';
			$params[] = sanitize_key($args['status']);
		}

		if (!empty($args['search'])) {
			$like = '%' . $wpdb->esc_like($args['search']) . '%';
			$where[] = '(applicant_name LIKE %s OR applicant_email LIKE %s OR grant_name LIKE %s OR project_title LIKE %s)';
			array_push($params, $like, $like, $like, $like);
		}

		$limit = isset($args['limit']) ? max(1, absint($args['limit'])) : 100;
		$offset = isset($args['offset']) ? max(0, absint($args['offset'])) : 0;

		$sql = "SELECT * FROM {$this->applications_table_name()} WHERE " . implode(' AND ', $where) . ' ORDER BY submitted_at DESC, id DESC LIMIT %d OFFSET %d';
		$params[] = $limit;
		$params[] = $offset;

		return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
	}

	public function count_applications_by_status() {
		global $wpdb;
		$rows = $wpdb->get_results("SELECT workflow_status, COUNT(*) AS total FROM {$this->applications_table_name()} GROUP BY workflow_status", ARRAY_A);
		$counts = [];

		foreach ($rows as $row) {
			$counts[$row['workflow_status']] = absint($row['total']);
		}

		return $counts;
	}

	public function get_application($id) {
		global $wpdb;

		$application = $wpdb->get_row(
			$wpdb->prepare("SELECT * FROM {$this->applications_table_name()} WHERE id = %d", absint($id)),
			ARRAY_A
		);

		if (!$application) {
			return null;
		}

		$application['normalized_fields'] = $this->decode_json_array($application['normalized_fields'] ?? '');
		$application['raw_payload'] = $this->decode_json_array($application['raw_payload'] ?? '');
		$application['history'] = $this->get_history($id);

		return $application;
	}

	public function get_by_source_entry($source, $entry_id) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->applications_table_name()} WHERE source = %s AND source_entry_id = %d",
				sanitize_key($source),
				absint($entry_id)
			),
			ARRAY_A
		);
	}

	public function create_portal_application($application) {
		global $wpdb;

		$now = current_time('mysql');
		$source_entry_id = $this->generate_source_entry_id();
		$normalized_fields = is_array($application['normalized_fields'] ?? null) ? $application['normalized_fields'] : [];
		$raw_payload = is_array($application['raw_payload'] ?? null) ? $application['raw_payload'] : [];

		$wpdb->insert(
			$this->applications_table_name(),
			[
				'source' => 'portal',
				'source_form_id' => 0,
				'source_entry_id' => $source_entry_id,
				'applicant_user_id' => absint($application['applicant_user_id'] ?? 0),
				'aac_member_id' => sanitize_text_field($application['aac_member_id'] ?? ''),
				'applicant_name' => sanitize_text_field($application['applicant_name'] ?? ''),
				'applicant_email' => sanitize_email($application['applicant_email'] ?? ''),
				'grant_name' => sanitize_text_field($application['grant_name'] ?? ''),
				'project_title' => sanitize_text_field($application['project_title'] ?? ''),
				'requested_amount' => $this->sanitize_amount($application['requested_amount'] ?? 0),
				'workflow_status' => self::STATUS_SUBMITTED,
				'assigned_reviewer_id' => 0,
				'submitted_at' => $application['submitted_at'] ?? $now,
				'normalized_fields' => wp_json_encode($normalized_fields),
				'raw_payload' => wp_json_encode($raw_payload),
				'created_at' => $now,
				'updated_at' => $now,
			],
			['%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%d', '%s', '%s', '%s', '%s', '%s']
		);

		$application_id = absint($wpdb->insert_id);
		if ($application_id) {
			$this->add_history($application_id, [
				'action' => 'submitted_from_portal',
				'from_status' => '',
				'to_status' => self::STATUS_SUBMITTED,
				'note' => 'Application submitted from the AAC member portal.',
				'actor_user_id' => absint($application['applicant_user_id'] ?? 0),
			]);

			$created_application = $this->get_application($application_id);
			if (is_array($created_application)) {
				do_action(
					'aac_grants_review_workflow_updated',
					$application_id,
					$created_application,
					'',
					self::STATUS_SUBMITTED
				);
			}
		}

		return $application_id;
	}

	public function get_member_applications($user_id = 0, $email = '') {
		global $wpdb;

		$user_id = absint($user_id);
		$email = sanitize_email($email);
		$where = [];
		$params = [];

		if ($user_id > 0) {
			$where[] = 'applicant_user_id = %d';
			$params[] = $user_id;
		}

		if ($email !== '') {
			$where[] = 'applicant_email = %s';
			$params[] = $email;
		}

		if (empty($where)) {
			return [];
		}

		$sql = "SELECT * FROM {$this->applications_table_name()} WHERE (" . implode(' OR ', $where) . ') ORDER BY submitted_at DESC, id DESC';
		$rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

		return array_values(array_filter(array_map([$this, 'format_application_for_member'], $rows)));
	}

	public function format_application_for_member($application) {
		if (!is_array($application)) {
			return null;
		}

		$stored_fields = $application['normalized_fields'] ?? [];
		if (!is_array($stored_fields)) {
			$stored_fields = $this->decode_json_array($stored_fields);
		}
		$normalized_fields = $this->prepare_normalized_fields_for_display($stored_fields);
		$status_key = sanitize_key($application['workflow_status'] ?? self::STATUS_SUBMITTED);
		$status_label = self::get_workflow_labels()[$status_key] ?? 'Submitted';

		return [
			'id' => 'grant_review_' . absint($application['id'] ?? 0),
			'review_application_id' => absint($application['id'] ?? 0),
			'aac_member_id' => sanitize_text_field($application['aac_member_id'] ?? ''),
			'grant_slug' => sanitize_title($this->extract_field_value($normalized_fields, ['grant_slug', 'grant slug'])),
			'grant_name' => sanitize_text_field($application['grant_name'] ?? ''),
			'category' => sanitize_text_field($this->extract_field_value($normalized_fields, ['grant_category', 'grant category', 'category'])),
			'application_date' => sanitize_text_field($application['submitted_at'] ?? ''),
			'status' => $status_label,
			'status_key' => $status_key,
			'project_title' => sanitize_text_field($application['project_title'] ?? ''),
			'requested_amount' => $this->format_amount_for_member($application['requested_amount'] ?? ''),
			'objective_location' => sanitize_text_field($this->extract_field_value($normalized_fields, ['objective_location', 'objective / project location', 'project location'])),
			'discipline' => sanitize_text_field($this->extract_field_value($normalized_fields, ['discipline'])),
			'team_name' => sanitize_text_field($this->extract_field_value($normalized_fields, ['team_name', 'team / partners', 'team / partner', 'team'])),
			'summary' => sanitize_textarea_field($this->extract_field_value($normalized_fields, ['summary', 'project summary'])),
			'fields' => $normalized_fields,
			'last_note' => sanitize_textarea_field($application['last_note'] ?? ''),
			'reviewed_at' => sanitize_text_field($application['reviewed_at'] ?? ''),
		];
	}

	public function update_workflow($application_id, $new_status, $note = '', $assigned_reviewer_id = null, $actor_user_id = 0, $allow_terminal_override = false) {
		global $wpdb;

		$application = $this->get_application($application_id);
		if (!$application) {
			return new WP_Error('missing_application', 'Application not found.');
		}

		$new_status = sanitize_key($new_status);
		$allowed = self::get_workflow_transitions()[$application['workflow_status']] ?? [];
		$is_terminal_override = $allow_terminal_override && in_array($new_status, [self::STATUS_APPROVED, self::STATUS_REJECTED], true);

		if (!in_array($new_status, $allowed, true) && !$is_terminal_override) {
			return new WP_Error('invalid_transition', 'That workflow move is not allowed from the current step.');
		}

		$update = [
			'workflow_status' => $new_status,
			'last_note' => wp_kses_post($note),
			'updated_at' => current_time('mysql'),
		];

		if (null !== $assigned_reviewer_id) {
			$update['assigned_reviewer_id'] = absint($assigned_reviewer_id);
		}

		if (in_array($new_status, [self::STATUS_APPROVED, self::STATUS_REJECTED], true)) {
			$update['reviewed_at'] = current_time('mysql');
		}

		$wpdb->update(
			$this->applications_table_name(),
			$update,
			['id' => absint($application_id)],
			null,
			['%d']
		);

		$this->add_history($application_id, [
			'action' => $is_terminal_override ? 'terminal_status_override' : 'status_changed',
			'from_status' => $application['workflow_status'],
			'to_status' => $new_status,
			'note' => $note,
			'actor_user_id' => absint($actor_user_id),
		]);

		$updated_application = $this->get_application($application_id);
		if (is_array($updated_application)) {
			/**
			 * Let other systems react when a grant application changes workflow state.
			 * Salesforce sync uses this so it can wait for the final thumbs-up/thumbs-down
			 * instead of barging into the middle of committee review.
			 */
			do_action(
				'aac_grants_review_workflow_updated',
				$application_id,
				$updated_application,
				sanitize_key((string) $application['workflow_status']),
				$new_status
			);
		}

		return true;
	}

	public function assign_reviewer($application_id, $reviewer_id, $actor_user_id = 0) {
		global $wpdb;

		$reviewer_id = absint($reviewer_id);

		$wpdb->update(
			$this->applications_table_name(),
			[
				'assigned_reviewer_id' => $reviewer_id,
				'updated_at' => current_time('mysql'),
			],
			['id' => absint($application_id)],
			['%d', '%s'],
			['%d']
		);

		$user = $reviewer_id ? get_userdata($reviewer_id) : null;
		$note = $user ? sprintf('Assigned reviewer: %s', $user->display_name) : 'Cleared assigned reviewer.';

		$this->add_history($application_id, [
			'action' => 'assigned_reviewer',
			'from_status' => '',
			'to_status' => '',
			'note' => $note,
			'actor_user_id' => absint($actor_user_id),
		]);
	}

	public function add_history($application_id, $event) {
		global $wpdb;

		$wpdb->insert(
			$this->history_table_name(),
			[
				'application_id' => absint($application_id),
				'action' => sanitize_key($event['action'] ?? ''),
				'from_status' => sanitize_key($event['from_status'] ?? ''),
				'to_status' => sanitize_key($event['to_status'] ?? ''),
				'note' => wp_kses_post($event['note'] ?? ''),
				'actor_user_id' => absint($event['actor_user_id'] ?? 0),
				'created_at' => current_time('mysql'),
			],
			['%d', '%s', '%s', '%s', '%s', '%d', '%s']
		);
	}

	public function get_history($application_id) {
		global $wpdb;

		$history = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->history_table_name()} WHERE application_id = %d ORDER BY created_at DESC, id DESC",
				absint($application_id)
			),
			ARRAY_A
		);

		return array_map(function ($event) {
			$actor = !empty($event['actor_user_id']) ? get_userdata(absint($event['actor_user_id'])) : null;
			$event['actor_name'] = $actor ? $actor->display_name : 'System';
			return $event;
		}, $history);
	}

	private function decode_json_array($value) {
		$decoded = json_decode((string) $value, true);
		return is_array($decoded) ? $decoded : [];
	}

	public function prepare_normalized_fields_for_display($fields) {
		if (!is_array($fields)) {
			return [];
		}

		$normalized = array_values(array_filter(array_map(function ($field) {
			if (!is_array($field)) {
				return null;
			}

			$field_id = sanitize_key($field['field_id'] ?? $field['field_key'] ?? '');
			$label = sanitize_text_field($field['label'] ?? '');
			if ($field_id === '' || $label === '') {
				return null;
			}

			return [
				'field_id' => $field_id,
				'label' => $label,
				'type' => sanitize_key($field['type'] ?? 'text'),
				'value' => sanitize_textarea_field((string) ($field['value'] ?? '')),
			];
		}, $fields)));

		$builder_fields = method_exists($this->settings, 'get_portal_grant_form_fields')
			? $this->settings->get_portal_grant_form_fields()
			: [];
		if (empty($builder_fields)) {
			return $normalized;
		}

		$order_map = [];
		foreach ($builder_fields as $index => $field) {
			$order_map[sanitize_key($field['field_key'] ?? '')] = $index;
		}

		usort($normalized, static function ($left, $right) use ($order_map) {
			$left_key = sanitize_key($left['field_id'] ?? '');
			$right_key = sanitize_key($right['field_id'] ?? '');
			$left_order = array_key_exists($left_key, $order_map) ? $order_map[$left_key] : 1000;
			$right_order = array_key_exists($right_key, $order_map) ? $order_map[$right_key] : 1000;

			if ($left_order === $right_order) {
				return strcmp($left['label'] ?? '', $right['label'] ?? '');
			}

			return $left_order <=> $right_order;
		});

		return $normalized;
	}

	private function generate_source_entry_id() {
		return (int) round(microtime(true) * 1000);
	}

	private function extract_field_value($fields, $tokens) {
		if (!is_array($fields)) {
			return '';
		}

		$tokens = array_map(static function ($token) {
			return strtolower(trim((string) $token));
		}, (array) $tokens);

		foreach ($fields as $field) {
			$field_id = strtolower(trim((string) ($field['field_id'] ?? '')));
			$label = strtolower(trim((string) ($field['label'] ?? '')));
			foreach ($tokens as $token) {
				if ($token === '' ) {
					continue;
				}
				if ($field_id === $token || $label === $token || false !== strpos($label, $token)) {
					return (string) ($field['value'] ?? '');
				}
			}
		}

		return '';
	}

	private function sanitize_amount($value) {
		$sanitized = preg_replace('/[^0-9.\-]/', '', (string) $value);
		return round((float) $sanitized, 2);
	}

	private function format_amount_for_member($value) {
		$amount = $this->sanitize_amount($value);
		if ($amount <= 0) {
			return '';
		}

		return '$' . number_format($amount, 2);
	}
}
