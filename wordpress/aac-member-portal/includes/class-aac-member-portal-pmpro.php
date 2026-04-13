<?php
/**
 * Paid Memberships Pro integration for tier, renewal, benefits, and membership URLs.
 *
 * Name PMPro levels to match benefit tiers where possible:
 * Supporter, Partner, Leader, Advocate.
 *
 * @package AAC_Member_Portal
 */

if (!defined('ABSPATH')) {
	exit;
}

class AAC_Member_Portal_PMPro {

	public static function is_available() {
		return function_exists('pmpro_getMembershipLevelForUser') && function_exists('pmpro_url');
	}

	/**
	 * Primary active membership for portal tier display.
	 *
	 * @return array{level_id:int,tier:string,renewal_date:string,expiration_date:string,joined_date:string}|null
	 */
	public static function get_primary_membership($user_id) {
		$user_id = (int) $user_id;
		if ($user_id <= 0 || !self::is_available()) {
			return null;
		}

		$membership = pmpro_getMembershipLevelForUser($user_id);
		if (!$membership || empty($membership->id)) {
			return null;
		}

		$level_id = (int) $membership->id;
		$expiration_date = self::normalize_subscription_date_value($membership->enddate ?? '');
		if ($expiration_date === '') {
			$expiration_date = self::get_membership_end_date($user_id, $level_id);
		}

		$renewal_date = '';
		$subscription_id = self::find_subscription_id($user_id, $level_id, ['active', 'trialing']);
		if ($subscription_id) {
			$renewal_date = self::get_subscription_next_payment_date($subscription_id);
		}

		$joined_date = self::get_first_membership_start_date($user_id);

		return [
			'level_id' => $level_id,
			'tier' => !empty($membership->name) ? (string) $membership->name : 'Supporter',
			'renewal_date' => $renewal_date,
			'expiration_date' => $expiration_date,
			'joined_date' => $joined_date,
		];
	}

	/**
	 * Membership URLs for the React app based on PMPro pages and checkout levels.
	 *
	 * @param int   $user_id
	 * @param array $profile_info
	 * @return array
	 */
	public static function build_membership_actions($user_id, $profile_info) {
		$empty = [
			'account_url' => '',
			'billing_url' => '',
			'cancel_url' => '',
			'current_level_id' => null,
			'current_subscription_id' => null,
			'current_level_checkout_url' => '',
			'levels' => new stdClass(),
		];

		if (!self::is_available()) {
			return $empty;
		}

		$levels = [];
		foreach (self::get_all_levels() as $level) {
			$level_id = isset($level->id) ? (int) $level->id : 0;
			$name = isset($level->name) ? (string) $level->name : '';
			if ($level_id <= 0 || $name === '') {
				continue;
			}

			$levels[$name] = [
				'checkout_url' => self::pmpro_page_url('checkout', ['level' => $level_id]),
			];
		}

		$primary = self::get_primary_membership($user_id);
		$current_level_id = $primary ? (int) $primary['level_id'] : null;
		$current_subscription_id = self::get_current_subscription_id($user_id, $current_level_id);

		return [
			'account_url' => self::pmpro_page_url('account'),
			'billing_url' => $current_subscription_id
				? self::pmpro_page_url('billing', ['pmpro_subscription_id' => $current_subscription_id])
				: self::pmpro_page_url('billing'),
			'cancel_url' => $current_level_id ? self::pmpro_page_url('cancel', ['levelstocancel' => $current_level_id]) : self::pmpro_page_url('cancel'),
			'current_level_id' => $current_level_id,
			'current_subscription_id' => $current_subscription_id,
			'current_level_checkout_url' => $current_level_id ? self::pmpro_page_url('checkout', ['level' => $current_level_id]) : '',
			'levels' => (object) $levels,
		];
	}

	/**
	 * Get a member-friendly payment summary for the card currently on file in PMPro.
	 *
	 * PMPro stores card details on membership orders. We prefer the newest order with
	 * a masked account number, and gracefully fall back to a non-card payment type.
	 *
	 * @param int $user_id
	 * @return string
	 */
	public static function get_payment_method_summary($user_id) {
		$user_id = (int) $user_id;
		if ($user_id <= 0 || !self::is_available()) {
			return '';
		}

		$order = self::get_latest_payment_order($user_id);
		if (empty($order) || !is_object($order)) {
			return '';
		}

		$card_type = self::normalize_card_label($order->card_type ?? '');
		$last4 = self::normalize_last4($order->accountnumber ?? '');
		$expiration_month = self::normalize_expiration_month($order->expirationmonth ?? '');
		$expiration_year = self::normalize_expiration_year($order->expirationyear ?? '');
		$payment_type = sanitize_text_field((string) ($order->payment_type ?? ''));

		if ($last4 !== '') {
			$summary = trim(($card_type !== '' ? $card_type : 'Card') . ' ending in ' . $last4);
			$expiration = trim($expiration_month . ($expiration_year !== '' ? '/' . $expiration_year : ''), '/');

			if ($expiration !== '') {
				$summary .= ' exp ' . $expiration;
			}

			return $summary;
		}

		return $payment_type;
	}

	/**
	 * Whether the member currently has an active recurring PMPro subscription.
	 *
	 * @param int      $user_id
	 * @param int|null $level_id
	 * @return bool
	 */
	public static function has_active_auto_renewal($user_id, $level_id = null) {
		$user_id = (int) $user_id;
		$level_id = $level_id !== null ? (int) $level_id : 0;

		if ($user_id <= 0 || !self::is_available()) {
			return false;
		}

		return (bool) self::find_subscription_id($user_id, $level_id, ['active', 'trialing']);
	}

	/**
	 * Membership purchase transactions for the account register.
	 *
	 * @param int $user_id
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_membership_transactions($user_id) {
		$user_id = (int) $user_id;
		if ($user_id <= 0 || !self::is_available()) {
			return [];
		}

		global $wpdb;
		if (!$wpdb || empty($wpdb->pmpro_membership_orders)) {
			return [];
		}

		$table = $wpdb->pmpro_membership_orders;
		$statuses = ['success', 'pending', 'review', 'refunded'];
		$status_placeholders = implode(', ', array_fill(0, count($statuses), '%s'));
		$query = $wpdb->prepare(
			"SELECT id, code, membership_id, total, status, gateway, payment_transaction_id, subscription_transaction_id, timestamp
			FROM {$table}
			WHERE user_id = %d
				AND membership_id > 0
				AND status IN ({$status_placeholders})
			ORDER BY timestamp DESC, id DESC
			LIMIT 50",
			array_merge([$user_id], $statuses)
		);

		if (!is_string($query) || $query === '') {
			return [];
		}

		$rows = $wpdb->get_results($query); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
		if (!is_array($rows) || !$rows) {
			return [];
		}

		$levels_by_id = self::get_level_names_by_id();
		$transactions = [];

		foreach ($rows as $row) {
			if (!is_object($row)) {
				continue;
			}

			$membership_id = isset($row->membership_id) ? (int) $row->membership_id : 0;
			$level_name = $levels_by_id[$membership_id] ?? ($membership_id > 0 ? sprintf('Level %d', $membership_id) : 'Membership');
			$reference_id = sanitize_text_field((string) ($row->payment_transaction_id ?: $row->subscription_transaction_id ?: $row->code ?: $row->id));

			$transactions[] = [
				'id' => 'pmpro_order_' . intval($row->id),
				'kind' => 'Membership',
				'amount' => isset($row->total) ? (float) $row->total : 0,
				'description' => sprintf('%s membership', $level_name),
				'referenceId' => $reference_id,
				'status' => self::normalize_transaction_status($row->status ?? ''),
				'createdAt' => self::normalize_transaction_timestamp($row->timestamp ?? ''),
				'metadata' => [
					'pmpro_order_id' => intval($row->id),
					'gateway' => sanitize_text_field((string) ($row->gateway ?? '')),
					'membership_id' => $membership_id,
				],
			];
		}

		return $transactions;
	}

	private static function get_latest_payment_order($user_id) {
		global $wpdb;

		if (!$wpdb || empty($wpdb->pmpro_membership_orders)) {
			return null;
		}

		$table = $wpdb->pmpro_membership_orders;
		$statuses = ['success', 'token', 'review', 'pending'];
		$status_placeholders = implode(', ', array_fill(0, count($statuses), '%s'));

		$query = $wpdb->prepare(
			"SELECT card_type, accountnumber, expirationmonth, expirationyear, payment_type
			FROM {$table}
			WHERE user_id = %d
				AND status IN ({$status_placeholders})
				AND (
					(accountnumber IS NOT NULL AND accountnumber <> '')
					OR (payment_type IS NOT NULL AND payment_type <> '')
				)
			ORDER BY id DESC
			LIMIT 1",
			array_merge([$user_id], $statuses)
		);

		if (!is_string($query) || $query === '') {
			return null;
		}

		$order = $wpdb->get_row($query); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
		return is_object($order) ? $order : null;
	}

	private static function get_current_subscription_id($user_id, $level_id = null) {
		$user_id = (int) $user_id;
		$level_id = $level_id !== null ? (int) $level_id : 0;

		if ($user_id <= 0) {
			return null;
		}

		$subscription_id = self::find_subscription_id($user_id, $level_id, ['active', 'trialing']);
		if ($subscription_id) {
			return $subscription_id;
		}

		if ($level_id > 0) {
			$subscription_id = self::find_subscription_id($user_id, $level_id);
			if ($subscription_id) {
				return $subscription_id;
			}
		}

		return self::find_subscription_id($user_id, 0);
	}

	private static function get_first_membership_start_date($user_id) {
		global $wpdb;

		$user_id = (int) $user_id;
		if ($user_id <= 0 || !$wpdb || empty($wpdb->pmpro_memberships_users)) {
			return '';
		}

		$table = $wpdb->pmpro_memberships_users;
		$available_columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- no user input.
		$available_columns = is_array($available_columns) ? array_map('strval', $available_columns) : [];

		if (!in_array('startdate', $available_columns, true)) {
			return '';
		}

		$query = $wpdb->prepare(
			"SELECT startdate
			FROM {$table}
			WHERE user_id = %d
				AND startdate IS NOT NULL
				AND startdate <> ''
				AND startdate <> '0000-00-00 00:00:00'
				AND startdate <> '0000-00-00'
			ORDER BY startdate ASC, id ASC
			LIMIT 1",
			$user_id
		);

		if (!is_string($query) || $query === '') {
			return '';
		}

		$startdate = $wpdb->get_var($query); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
		$startdate = sanitize_text_field((string) $startdate);
		if ($startdate === '') {
			return '';
		}

		$timestamp = strtotime($startdate);
		if ($timestamp === false) {
			return '';
		}

		return gmdate('Y-m-d', $timestamp);
	}

	private static function get_membership_end_date($user_id, $level_id = 0) {
		global $wpdb;

		$user_id = (int) $user_id;
		$level_id = (int) $level_id;
		if ($user_id <= 0 || !$wpdb || empty($wpdb->pmpro_memberships_users)) {
			return '';
		}

		$table = $wpdb->pmpro_memberships_users;
		$available_columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- no user input.
		$available_columns = is_array($available_columns) ? array_map('strval', $available_columns) : [];

		if (!in_array('enddate', $available_columns, true)) {
			return '';
		}

		$where = [
			'user_id = %d',
			'enddate IS NOT NULL',
			"enddate <> ''",
			"enddate <> '0000-00-00 00:00:00'",
			"enddate <> '0000-00-00'",
		];
		$params = [$user_id];

		if ($level_id > 0 && in_array('membership_id', $available_columns, true)) {
			$where[] = 'membership_id = %d';
			$params[] = $level_id;
		}

		$query = $wpdb->prepare(
			"SELECT enddate
			FROM {$table}
			WHERE " . implode(' AND ', $where) . '
			ORDER BY enddate DESC, id DESC
			LIMIT 1',
			$params
		);

		if (!is_string($query) || $query === '') {
			return '';
		}

		$enddate = $wpdb->get_var($query); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
		return self::normalize_subscription_date_value($enddate);
	}

	private static function get_subscription_next_payment_date($subscription_id) {
		global $wpdb;

		$subscription_id = (int) $subscription_id;
		if ($subscription_id <= 0 || !$wpdb || empty($wpdb->pmpro_subscriptions)) {
			return '';
		}

		$table = $wpdb->pmpro_subscriptions;
		$date_columns = self::get_subscription_date_columns();
		if (!$date_columns) {
			return '';
		}

		$selected_columns = implode(', ', array_map(function ($column) {
			return '`' . esc_sql($column) . '`';
		}, $date_columns));

		$query = $wpdb->prepare(
			"SELECT {$selected_columns}
			FROM {$table}
			WHERE id = %d
			LIMIT 1",
			$subscription_id
		);

		if (!is_string($query) || $query === '') {
			return '';
		}

		$row = $wpdb->get_row($query, ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
		if (!is_array($row) || !$row) {
			return '';
		}

		foreach ($date_columns as $column) {
			$normalized = self::normalize_subscription_date_value($row[$column] ?? '');
			if ($normalized !== '') {
				return $normalized;
			}
		}

		return '';
	}

	private static function find_subscription_id($user_id, $level_id = 0, $statuses = []) {
		global $wpdb;

		if (!$wpdb || empty($wpdb->pmpro_subscriptions)) {
			return null;
		}

		$user_id = (int) $user_id;
		$level_id = (int) $level_id;
		$statuses = array_values(array_filter(array_map('sanitize_text_field', (array) $statuses)));

		$table = $wpdb->pmpro_subscriptions;
		$where = ['user_id = %d'];
		$params = [$user_id];

		if ($level_id > 0) {
			$where[] = 'membership_level_id = %d';
			$params[] = $level_id;
		}

		if ($statuses) {
			$status_placeholders = implode(', ', array_fill(0, count($statuses), '%s'));
			$where[] = "status IN ({$status_placeholders})";
			$params = array_merge($params, $statuses);
		}

		$query = $wpdb->prepare(
			"SELECT id
			FROM {$table}
			WHERE " . implode(' AND ', $where) . "
			ORDER BY id DESC
			LIMIT 1",
			$params
		);

		if (!is_string($query) || $query === '') {
			return null;
		}

		$subscription_id = $wpdb->get_var($query); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
		$subscription_id = absint($subscription_id);

		return $subscription_id > 0 ? $subscription_id : null;
	}

	private static function get_level_names_by_id() {
		$levels_by_id = [];
		foreach (self::get_all_levels() as $level) {
			$level_id = isset($level->id) ? (int) $level->id : 0;
			$name = isset($level->name) ? (string) $level->name : '';
			if ($level_id > 0 && $name !== '') {
				$levels_by_id[$level_id] = $name;
			}
		}

		return $levels_by_id;
	}

	private static function get_subscription_date_columns() {
		static $columns = null;

		if (is_array($columns)) {
			return $columns;
		}

		global $wpdb;
		if (!$wpdb || empty($wpdb->pmpro_subscriptions)) {
			$columns = [];
			return $columns;
		}

		$table = $wpdb->pmpro_subscriptions;
		$available = $wpdb->get_col("SHOW COLUMNS FROM {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- no user input.
		$available = is_array($available) ? array_map('strval', $available) : [];
		$candidates = ['next_payment_date', 'next_payment', 'billing_next_payment', 'cycle_enddate'];

		$columns = array_values(array_filter($candidates, function ($candidate) use ($available) {
			return in_array($candidate, $available, true);
		}));

		return $columns;
	}

	private static function normalize_subscription_date_value($value) {
		$value = sanitize_text_field((string) $value);
		if ($value === '' || $value === '0000-00-00 00:00:00' || $value === '0000-00-00') {
			return '';
		}

		$timestamp = strtotime($value);
		if ($timestamp === false) {
			return '';
		}

		return gmdate('Y-m-d', $timestamp);
	}

	private static function normalize_card_label($card_type) {
		$card_type = sanitize_text_field((string) $card_type);
		if ($card_type === '') {
			return '';
		}

		$compact = strtolower(preg_replace('/[^a-z0-9]+/', '', $card_type));
		$map = [
			'americanexpress' => 'American Express',
			'amex' => 'American Express',
			'mastercard' => 'Mastercard',
			'mastercarddebit' => 'Mastercard',
			'visa' => 'Visa',
			'discover' => 'Discover',
		];

		if (isset($map[$compact])) {
			return $map[$compact];
		}

		return ucwords(strtolower($card_type));
	}

	private static function normalize_last4($value) {
		$digits = preg_replace('/\D+/', '', (string) $value);
		if (!is_string($digits) || $digits === '') {
			return '';
		}

		return substr($digits, -4);
	}

	private static function normalize_expiration_month($value) {
		$month = preg_replace('/\D+/', '', (string) $value);
		if (!is_string($month) || $month === '') {
			return '';
		}

		return str_pad(substr($month, -2), 2, '0', STR_PAD_LEFT);
	}

	private static function normalize_expiration_year($value) {
		$year = preg_replace('/\D+/', '', (string) $value);
		if (!is_string($year) || $year === '') {
			return '';
		}

		return strlen($year) > 2 ? substr($year, -2) : str_pad($year, 2, '0', STR_PAD_LEFT);
	}

	private static function normalize_transaction_status($status) {
		$status = sanitize_text_field((string) $status);
		$map = [
			'success' => 'Paid',
			'pending' => 'Pending',
			'review' => 'Under Review',
			'refunded' => 'Refunded',
		];

		return $map[$status] ?? ($status !== '' ? ucwords(str_replace(['_', '-'], ' ', strtolower($status))) : 'Processed');
	}

	private static function normalize_transaction_timestamp($timestamp) {
		$timestamp = sanitize_text_field((string) $timestamp);
		$unix = strtotime($timestamp);
		if ($unix === false) {
			return gmdate('c');
		}

		return gmdate('c', $unix);
	}

	private static function pmpro_page_url($page, $args = []) {
		if (!function_exists('pmpro_url')) {
			return '';
		}

		$query = '';
		if (!empty($args)) {
			$query = '?' . http_build_query($args, '', '&');
		}

		$url = pmpro_url($page, $query);
		return is_string($url) ? $url : '';
	}

	private static function get_all_levels() {
		if (function_exists('pmpro_getAllLevels')) {
			$levels = pmpro_getAllLevels(false, true);
			if (is_array($levels)) {
				return $levels;
			}
		}

		global $wpdb;
		if (!$wpdb || empty($wpdb->pmpro_membership_levels)) {
			return [];
		}

		$table = $wpdb->pmpro_membership_levels;
		$query = "SELECT id, name FROM {$table} ORDER BY id ASC";

		$levels = $wpdb->get_results($query); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- no user input.
		return is_array($levels) ? $levels : [];
	}
}
