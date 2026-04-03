<?php

if (!defined('ABSPATH')) {
	exit;
}

class AAC_Member_Portal_API {
	const ROUTE_NAMESPACE = 'aac/v1';

	public function __construct() {
		add_action('rest_api_init', [$this, 'register_routes']);
	}

	public function register_routes() {
		register_rest_route(self::ROUTE_NAMESPACE, '/login', [
			'methods' => 'POST',
			'callback' => [$this, 'login'],
			'permission_callback' => '__return_true',
		]);

		register_rest_route(self::ROUTE_NAMESPACE, '/logout', [
			'methods' => 'POST',
			'callback' => [$this, 'logout'],
			'permission_callback' => [$this, 'is_logged_in'],
		]);

		register_rest_route(self::ROUTE_NAMESPACE, '/register', [
			'methods' => 'POST',
			'callback' => [$this, 'register_member'],
			'permission_callback' => '__return_true',
		]);

		register_rest_route(self::ROUTE_NAMESPACE, '/reset-password', [
			'methods' => 'POST',
			'callback' => [$this, 'reset_password'],
			'permission_callback' => '__return_true',
		]);

		register_rest_route(self::ROUTE_NAMESPACE, '/change-password', [
			'methods' => 'POST',
			'callback' => [$this, 'change_password'],
			'permission_callback' => [$this, 'is_logged_in'],
		]);

		register_rest_route(self::ROUTE_NAMESPACE, '/me', [
			'methods' => 'GET',
			'callback' => [$this, 'me'],
			'permission_callback' => [$this, 'is_logged_in'],
		]);

		register_rest_route(self::ROUTE_NAMESPACE, '/profile', [
			'methods' => 'PATCH',
			'callback' => [$this, 'update_profile'],
			'permission_callback' => [$this, 'is_logged_in'],
		]);

		register_rest_route(self::ROUTE_NAMESPACE, '/contact', [
			'methods' => 'POST',
			'callback' => [$this, 'contact'],
			'permission_callback' => [$this, 'is_logged_in'],
		]);

		register_rest_route(self::ROUTE_NAMESPACE, '/podcasts', [
			'methods' => 'GET',
			'callback' => [$this, 'podcasts'],
			'permission_callback' => [$this, 'is_logged_in'],
		]);

		register_rest_route(self::ROUTE_NAMESPACE, '/transactions', [
			'methods' => 'GET',
			'callback' => [$this, 'transactions'],
			'permission_callback' => [$this, 'is_logged_in'],
		]);

		register_rest_route(self::ROUTE_NAMESPACE, '/debug/last-fatal', [
			'methods' => 'GET',
			'callback' => [$this, 'debug_last_fatal'],
			'permission_callback' => [$this, 'can_manage_options'],
		]);

	}

	public function is_logged_in() {
		return is_user_logged_in();
	}

	public function can_manage_options() {
		return current_user_can('manage_options');
	}

	public function login(WP_REST_Request $request) {
		$email = sanitize_email($request->get_param('email'));
		$password = (string) $request->get_param('password');

		$rate_limit = $this->consume_rate_limit('login', $this->build_rate_limit_identity($request, $email), 8, 15 * MINUTE_IN_SECONDS);
		if (is_wp_error($rate_limit)) {
			return $rate_limit;
		}

		$user = get_user_by('email', $email);

		if (!$user) {
			return new WP_Error('invalid_credentials', 'Invalid email or password.', ['status' => 401]);
		}

		$signon = wp_signon([
			'user_login' => $user->user_login,
			'user_password' => $password,
			'remember' => true,
		], is_ssl());

		if (is_wp_error($signon)) {
			return new WP_Error('invalid_credentials', 'Invalid email or password.', ['status' => 401]);
		}

		wp_set_current_user($signon->ID);

		do_action('aac_member_portal_member_logged_in', $signon->ID, $request);

		return $this->build_auth_response($signon);
	}

	public function logout() {
		wp_logout();
		return rest_ensure_response([
			'success' => true,
			'restNonce' => '',
		]);
	}

	public function debug_last_fatal() {
		return rest_ensure_response(get_option('aac_member_portal_last_fatal', []));
	}

	public function register_member(WP_REST_Request $request) {
		$email = sanitize_email($request->get_param('email'));
		$password = (string) $request->get_param('password');
		$first_name = sanitize_text_field($request->get_param('first_name'));
		$last_name = sanitize_text_field($request->get_param('last_name'));
		$username = sanitize_user($request->get_param('username'), true);

		$rate_limit = $this->consume_rate_limit('register', $this->build_rate_limit_identity($request, $email), 5, HOUR_IN_SECONDS);
		if (is_wp_error($rate_limit)) {
			return $rate_limit;
		}

		if (!$email || !$password) {
			return new WP_Error('invalid_input', 'Email and password are required.', ['status' => 400]);
		}

		if (!is_email($email)) {
			return new WP_Error('invalid_input', 'Please enter a valid email address.', ['status' => 400]);
		}

		if (strlen($password) < 8) {
			return new WP_Error('invalid_input', 'Password must be at least 8 characters long.', ['status' => 400]);
		}

		if (!$username) {
			$email_parts = explode('@', $email);
			$username = sanitize_user($email_parts[0], true);
		}

		$base_username = $username;
		$suffix = 1;
		while (username_exists($username)) {
			$username = sprintf('%s%d', $base_username, $suffix);
			$suffix++;
		}

		$user_id = wp_create_user($username, $password, $email);
		if (is_wp_error($user_id)) {
			return $user_id;
		}

		wp_update_user([
			'ID' => $user_id,
			'first_name' => $first_name,
			'last_name' => $last_name,
			'display_name' => trim($first_name . ' ' . $last_name) ?: $email,
		]);

		update_user_meta($user_id, 'aac_account_info', [
			'first_name' => $first_name,
			'last_name' => $last_name,
			'name' => trim($first_name . ' ' . $last_name),
			'email' => $email,
		]);

		wp_set_current_user($user_id);
		wp_set_auth_cookie($user_id, true);

		do_action('aac_member_portal_member_registered', $user_id, $request);

		return rest_ensure_response(array_merge(
			['requires_email_verification' => false],
			$this->build_auth_response(get_user_by('id', $user_id))
		));
	}

	public function reset_password(WP_REST_Request $request) {
		$email = sanitize_email($request->get_param('email'));

		$rate_limit = $this->consume_rate_limit('reset_password', $this->build_rate_limit_identity($request, $email), 5, 15 * MINUTE_IN_SECONDS);
		if (is_wp_error($rate_limit)) {
			return $rate_limit;
		}

		if (!$email || !is_email($email)) {
			return rest_ensure_response(['success' => true]);
		}

		$user = get_user_by('email', $email);

		if ($user) {
			$key = get_password_reset_key($user);
			if (!is_wp_error($key)) {
				$reset_url = network_site_url("wp-login.php?action=rp&key={$key}&login=" . rawurlencode($user->user_login), 'login');

				wp_mail(
					$user->user_email,
					'Password Reset',
					"Use this link to reset your password:\n\n{$reset_url}"
				);
			}
		}

		return rest_ensure_response(['success' => true]);
	}

	public function change_password(WP_REST_Request $request) {
		$user = wp_get_current_user();
		$current_password = (string) $request->get_param('current_password');
		$new_password = (string) $request->get_param('new_password');
		$confirm_password = (string) $request->get_param('confirm_password');

		if (!$user instanceof WP_User || !$user->exists()) {
			return new WP_Error('not_authenticated', 'You must be logged in to change your password.', ['status' => 401]);
		}

		if ($current_password === '' || $new_password === '' || $confirm_password === '') {
			return new WP_Error('invalid_input', 'Current password, new password, and confirmation are required.', ['status' => 400]);
		}

		if (!wp_check_password($current_password, $user->user_pass, $user->ID)) {
			return new WP_Error('invalid_password', 'Your current password is incorrect.', ['status' => 400]);
		}

		if ($new_password !== $confirm_password) {
			return new WP_Error('password_mismatch', 'New password and confirmation must match.', ['status' => 400]);
		}

		if (strlen($new_password) < 8) {
			return new WP_Error('weak_password', 'New password must be at least 8 characters long.', ['status' => 400]);
		}

		if ($new_password === $current_password) {
			return new WP_Error('password_reuse', 'Choose a new password that is different from your current password.', ['status' => 400]);
		}

		wp_set_password($new_password, $user->ID);
		$fresh_user = get_user_by('id', $user->ID);
		$rest_nonce = $this->establish_fresh_auth_session($user->ID);

		return rest_ensure_response([
			'success' => true,
			'profile' => $this->build_profile($user->ID),
			'restNonce' => $rest_nonce,
			'user' => [
				'id' => $user->ID,
				'email' => $fresh_user ? $fresh_user->user_email : $user->user_email,
			],
		]);
	}

	public function me() {
		return rest_ensure_response($this->build_auth_response(wp_get_current_user()));
	}

	public function update_profile(WP_REST_Request $request) {
		$user_id = get_current_user_id();
		$account_info = $request->get_param('account_info');
		$profile_info = $request->get_param('profile_info');
		$benefits_info = $request->get_param('benefits_info');
		$grant_applications = $request->get_param('grant_applications');

		if (
			!is_array($account_info) &&
			!is_array($profile_info) &&
			!is_array($benefits_info) &&
			!is_array($grant_applications)
		) {
			return new WP_Error('invalid_input', 'At least one profile section must be provided.', ['status' => 400]);
		}

		if ($account_info !== null && !is_array($account_info)) {
			return new WP_Error('invalid_input', 'account_info must be an object.', ['status' => 400]);
		}

		if (is_array($account_info)) {
			$sanitized_account_info = $this->sanitize_account_info($account_info);
			$synced_account_info = $this->sync_wp_user_from_account_info($user_id, $sanitized_account_info);
			if (is_wp_error($synced_account_info)) {
				return $synced_account_info;
			}

			update_user_meta($user_id, 'aac_account_info', $synced_account_info);
			$this->sync_reportable_member_fields($user_id, $synced_account_info);
		}

		if (is_array($profile_info)) {
			update_user_meta($user_id, 'aac_profile_info', $this->sanitize_profile_info($profile_info));
		}

		if (is_array($benefits_info)) {
			update_user_meta($user_id, 'aac_benefits_info', $this->sanitize_benefits_info($benefits_info));
		}

		if (is_array($grant_applications)) {
			update_user_meta($user_id, 'aac_grant_applications', $this->sanitize_member_editable_grant_applications($grant_applications));
		}

		$profile = $this->build_profile($user_id);
		do_action('aac_member_portal_profile_updated', $user_id, $profile, $request);

		return rest_ensure_response([
			'success' => true,
			'profile' => $profile,
		]);
	}

	public function contact(WP_REST_Request $request) {
		$current_user = wp_get_current_user();
		$message = sanitize_textarea_field($request->get_param('message'));
		$sender_name = sanitize_text_field($request->get_param('name'));
		$sender_email = sanitize_email($request->get_param('email')) ?: $current_user->user_email;

		if (!$message) {
			return new WP_Error('invalid_input', 'Message is required.', ['status' => 400]);
		}

		$recipient_email = AAC_Member_Portal_Admin::get_contact_recipient_email();
		$subject = 'AAC Member Portal Contact Message';
		$body = sprintf(
			"Name: %s\nEmail: %s\n\n%s",
			$sender_name,
			$sender_email,
			$message
		);

		$headers = [];
		$from_email = sanitize_email(get_option('admin_email'));
		if ($from_email && is_email($from_email)) {
			$headers[] = sprintf('From: Member Request Message <%s>', $from_email);
		}
		if ($sender_email && is_email($sender_email)) {
			$headers[] = sprintf('Reply-To: %s <%s>', $sender_name ?: $sender_email, $sender_email);
		}

		wp_mail($recipient_email, $subject, $body, $headers);

		return rest_ensure_response(['success' => true]);
	}

	public function podcasts() {
		$podcasts = get_posts([
			'post_type' => 'podcast',
			'post_status' => 'publish',
			'numberposts' => 5,
		]);

		$items = array_map(function ($post) {
			$title = get_the_title($post);
			$description = has_excerpt($post)
				? wp_strip_all_tags(get_the_excerpt($post), true)
				: wp_strip_all_tags(wp_trim_words((string) $post->post_content, 34, '...'), true);
			$embed_url = get_post_meta($post->ID, 'embed_url', true);
			$source_url = get_post_meta($post->ID, 'source_url', true);

			return [
				'title' => $title ? html_entity_decode(wp_strip_all_tags($title), ENT_QUOTES) : '',
				'description' => $description,
				'published_at' => get_post_time('c', true, $post),
				'source_url' => is_string($source_url) ? esc_url_raw($source_url) : '',
				'embed_url' => is_string($embed_url) ? esc_url_raw($embed_url) : '',
			];
		}, $podcasts);

		return rest_ensure_response([
			'podcasts' => array_values(array_filter($items, function ($item) {
				return !empty($item['embed_url']);
			})),
		]);
	}

	public function transactions() {
		$user_id = get_current_user_id();
		$transactions = AAC_Member_Portal_PMPro::is_available()
			? AAC_Member_Portal_PMPro::get_membership_transactions($user_id)
			: [];

		return rest_ensure_response([
			'transactions' => $transactions,
		]);
	}

	private function build_auth_response($user) {
		return [
			'session' => [
				'user' => [
					'id' => $user->ID,
					'email' => $user->user_email,
				],
			],
			'user' => [
				'id' => $user->ID,
				'email' => $user->user_email,
			],
			'profile' => $this->build_profile($user->ID),
			'restNonce' => wp_create_nonce('wp_rest'),
		];
	}

	private function establish_fresh_auth_session($user_id) {
		$remember = true;
		$secure = is_ssl();
		$expiration = time() + (int) apply_filters('auth_cookie_expiration', 14 * DAY_IN_SECONDS, $user_id, $remember);
		$session_manager = WP_Session_Tokens::get_instance($user_id);
		$token = $session_manager->create($expiration);

		wp_set_current_user($user_id);
		wp_set_auth_cookie($user_id, $remember, $secure, $token);

		if (defined('AUTH_COOKIE')) {
			$_COOKIE[AUTH_COOKIE] = wp_generate_auth_cookie($user_id, $expiration, 'auth', $token);
		}

		if (defined('SECURE_AUTH_COOKIE')) {
			$_COOKIE[SECURE_AUTH_COOKIE] = wp_generate_auth_cookie($user_id, $expiration, 'secure_auth', $token);
		}

		if (defined('LOGGED_IN_COOKIE')) {
			$_COOKIE[LOGGED_IN_COOKIE] = wp_generate_auth_cookie($user_id, $expiration, 'logged_in', $token);
		}

		return wp_create_nonce('wp_rest');
	}

	private function build_profile($user_id) {
		$user = get_user_by('id', $user_id);
		$account_info = get_user_meta($user_id, 'aac_account_info', true);
		$account_info = is_array($account_info) ? $account_info : [];
		$stored_profile_info = get_user_meta($user_id, 'aac_profile_info', true);
		$stored_profile_info = is_array($stored_profile_info) ? $stored_profile_info : [];
		$computed_profile_info = $this->build_profile_info($user_id);
		if ($this->has_managed_membership_plugin()) {
			$profile_info = array_merge($stored_profile_info, $computed_profile_info);
		} else {
			$profile_info = array_merge($computed_profile_info, $stored_profile_info);
		}

		$stored_benefits_info = get_user_meta($user_id, 'aac_benefits_info', true);
		$stored_benefits_info = is_array($stored_benefits_info) ? $stored_benefits_info : [];
		$computed_benefits_info = $this->build_benefits_info($profile_info['tier'], $profile_info['status']);
		if ($this->has_managed_membership_plugin()) {
			$benefits_info = $computed_benefits_info;
		} else {
			$benefits_info = array_merge($computed_benefits_info, $stored_benefits_info);
		}

		$account_info = array_merge([
			'first_name' => $user->first_name,
			'last_name' => $user->last_name,
			'name' => $user->display_name,
			'email' => $user->user_email,
			'photo_url' => get_avatar_url($user_id),
			'phone' => '',
			'street' => '',
			'address2' => '',
			'city' => '',
			'state' => '',
			'zip' => '',
			'country' => '',
			'size' => 'M',
			'publication_pref' => 'Digital',
			'phone_type' => '',
			'guidebook_pref' => 'Digital',
			'magazine_subscriptions' => [],
			'membership_discount_type' => '',
			'auto_renew' => false,
			'payment_method' => '',
		], $account_info);

		$account_info['magazine_subscriptions'] = $this->get_member_magazine_subscription_labels($user_id);
		$account_info['membership_discount_type'] = sanitize_key(get_user_meta($user_id, 'aac_membership_discount_type', true));

		$membership_actions = $this->build_membership_actions($user_id, $profile_info);

		if ($this->has_managed_membership_plugin()) {
			$account_info['payment_method'] = AAC_Member_Portal_PMPro::get_payment_method_summary($user_id);
			$account_info['auto_renew'] = AAC_Member_Portal_PMPro::has_active_auto_renewal(
				$user_id,
				$membership_actions['current_level_id'] ?? null
			);
		}

		$grant_applications = get_user_meta($user_id, 'aac_grant_applications', true);
		$grant_applications = is_array($grant_applications)
			? $this->sanitize_grant_applications($grant_applications)
			: [];

		$connected_accounts = get_user_meta($user_id, 'aac_connected_accounts', true);
		$connected_accounts = is_array($connected_accounts)
			? $this->sanitize_connected_accounts($connected_accounts)
			: [];

		$family_membership = get_user_meta($user_id, 'aac_partner_family_config', true);
		$family_membership = is_array($family_membership)
			? $this->sanitize_family_membership($family_membership)
			: ['mode' => '', 'additional_adult' => false, 'dependent_count' => 0];

		$profile = [
			'account_info' => $account_info,
			'profile_info' => $profile_info,
			'benefits_info' => $benefits_info,
			'membership_actions' => $membership_actions,
			'grant_applications' => $grant_applications,
			'connected_accounts' => $connected_accounts,
			'family_membership' => $family_membership,
		];

		return apply_filters('aac_member_portal_profile', $profile, $user_id, $user);
	}

	private function build_profile_info($user_id) {
		$member_id = get_user_meta($user_id, 'aac_member_id', true);

		if (AAC_Member_Portal_PMPro::is_available()) {
			$primary = AAC_Member_Portal_PMPro::get_primary_membership($user_id);
			if ($primary) {
				$status_reference_date = $primary['expiration_date'] ?: $primary['renewal_date'];

				return [
					'member_id' => $member_id ?: sprintf('AAC-%d', $user_id),
					'tier' => $primary['tier'],
					'renewal_date' => $primary['renewal_date'],
					'expiration_date' => $primary['expiration_date'],
					'status' => $this->membership_status_pmpro($status_reference_date),
				];
			}
		}

		return [
			'member_id' => $member_id ?: sprintf('AAC-%d', $user_id),
			'tier' => 'Free',
			'renewal_date' => '',
			'expiration_date' => '',
			'status' => 'Inactive',
		];
	}

	private function build_benefits_info($tier, $status = 'Active') {
		if ($status !== 'Active') {
			return ['rescue_amount' => 0, 'medical_amount' => 0];
		}

		$matrix = [
			'Free' => ['rescue_amount' => 0, 'medical_amount' => 0],
			'Supporter' => ['rescue_amount' => 0, 'medical_amount' => 0],
			'Partner' => ['rescue_amount' => 50000, 'medical_amount' => 5000],
			'Partner Family' => ['rescue_amount' => 50000, 'medical_amount' => 5000],
			'Leader' => ['rescue_amount' => 100000, 'medical_amount' => 10000],
			'Advocate' => ['rescue_amount' => 100000, 'medical_amount' => 10000],
			'GRF' => ['rescue_amount' => 100000, 'medical_amount' => 10000],
			'Lifetime' => ['rescue_amount' => 100000, 'medical_amount' => 10000],
			'' => ['rescue_amount' => 0, 'medical_amount' => 0],
		];

		return $matrix[$tier] ?? $matrix['Free'];
	}

	private function build_membership_actions($user_id, $profile_info) {
		if (AAC_Member_Portal_PMPro::is_available()) {
			return AAC_Member_Portal_PMPro::build_membership_actions($user_id, $profile_info);
		}

		return [
			'account_url' => '',
			'billing_url' => '',
			'cancel_url' => '',
			'current_level_id' => null,
			'current_subscription_id' => null,
			'current_level_checkout_url' => '',
			'levels' => new stdClass(),
		];
	}

	/**
	 * PMPro: empty renewal means no fixed expiration date, which we treat as active.
	 */
	private function membership_status_pmpro($renewal_date) {
		if ($renewal_date === '' || $renewal_date === null) {
			return 'Active';
		}

		return strtotime($renewal_date . ' 23:59:59') >= current_time('timestamp') ? 'Active' : 'Inactive';
	}

	private function has_managed_membership_plugin() {
		return AAC_Member_Portal_PMPro::is_available();
	}

	private function sanitize_account_info($account_info) {
		$first_name = sanitize_text_field($account_info['first_name'] ?? '');
		$last_name = sanitize_text_field($account_info['last_name'] ?? '');
		$name = trim($first_name . ' ' . $last_name);
		if (!$name) {
			$name = sanitize_text_field($account_info['name'] ?? '');
		}

		$phone_type = sanitize_text_field($account_info['phone_type'] ?? '');
		if ($phone_type !== '' && !in_array($phone_type, ['mobile', 'home', 'work'], true)) {
			$phone_type = 'mobile';
		}

		$guidebook_pref = sanitize_text_field($account_info['guidebook_pref'] ?? 'Digital');
		if (!in_array($guidebook_pref, ['Digital', 'Print'], true)) {
			$guidebook_pref = 'Digital';
		}

		$publication_pref = sanitize_text_field($account_info['publication_pref'] ?? 'Digital');
		if (!in_array($publication_pref, ['Digital', 'Print'], true)) {
			$publication_pref = 'Digital';
		}

		$membership_discount_type = sanitize_key($account_info['membership_discount_type'] ?? '');
		if (!in_array($membership_discount_type, ['student', 'military'], true)) {
			$membership_discount_type = '';
		}

		return [
			'first_name' => $first_name,
			'last_name' => $last_name,
			'name' => $name,
			'email' => sanitize_email($account_info['email'] ?? ''),
			'photo_url' => esc_url_raw($account_info['photo_url'] ?? ''),
			'phone' => sanitize_text_field($account_info['phone'] ?? ''),
			'phone_type' => $phone_type,
			'street' => sanitize_text_field($account_info['street'] ?? ''),
			'address2' => sanitize_text_field($account_info['address2'] ?? ''),
			'city' => sanitize_text_field($account_info['city'] ?? ''),
			'state' => sanitize_text_field($account_info['state'] ?? ''),
			'zip' => sanitize_text_field($account_info['zip'] ?? ''),
			'country' => sanitize_text_field($account_info['country'] ?? ''),
			'size' => sanitize_text_field($account_info['size'] ?? 'M'),
			'publication_pref' => $publication_pref,
			'guidebook_pref' => $guidebook_pref,
			'magazine_subscriptions' => array_values(array_filter(array_map('sanitize_text_field', (array) ($account_info['magazine_subscriptions'] ?? [])))),
			'membership_discount_type' => $membership_discount_type,
			'auto_renew' => !empty($account_info['auto_renew']),
			'payment_method' => sanitize_text_field($account_info['payment_method'] ?? ''),
		];
	}

	private function sanitize_profile_info($profile_info) {
		return [
			'member_id' => sanitize_text_field($profile_info['member_id'] ?? ''),
			'tier' => sanitize_text_field($profile_info['tier'] ?? ''),
			'renewal_date' => sanitize_text_field($profile_info['renewal_date'] ?? ''),
			'expiration_date' => sanitize_text_field($profile_info['expiration_date'] ?? ''),
			'status' => sanitize_text_field($profile_info['status'] ?? ''),
		];
	}

	private function sanitize_benefits_info($benefits_info) {
		return [
			'rescue_amount' => intval($benefits_info['rescue_amount'] ?? 0),
			'medical_amount' => intval($benefits_info['medical_amount'] ?? 0),
		];
	}

	private function sanitize_grant_applications($grant_applications) {
		if (!is_array($grant_applications)) {
			return [];
		}

		return array_values(array_filter(array_map(function ($application) {
			if (!is_array($application)) {
				return null;
			}

			$status = sanitize_text_field($application['status'] ?? 'Pending review');
			if (!in_array($status, ['Pending review', 'Approved', 'Rejected'], true)) {
				$status = 'Pending review';
			}

			$application_date = sanitize_text_field($application['application_date'] ?? '');
			if ($application_date === '') {
				$application_date = current_time('c');
			}

			return [
				'id' => sanitize_text_field($application['id'] ?? wp_generate_uuid4()),
				'grant_slug' => sanitize_title($application['grant_slug'] ?? ''),
				'grant_name' => sanitize_text_field($application['grant_name'] ?? ''),
				'category' => sanitize_text_field($application['category'] ?? ''),
				'application_date' => $application_date,
				'status' => $status,
				'project_title' => sanitize_text_field($application['project_title'] ?? ''),
				'requested_amount' => sanitize_text_field($application['requested_amount'] ?? ''),
				'objective_location' => sanitize_text_field($application['objective_location'] ?? ''),
				'discipline' => sanitize_text_field($application['discipline'] ?? ''),
				'team_name' => sanitize_text_field($application['team_name'] ?? ''),
				'summary' => sanitize_textarea_field($application['summary'] ?? ''),
			];
		}, $grant_applications)));
	}

	private function sanitize_family_membership($family_membership) {
		if (!is_array($family_membership)) {
			return ['mode' => '', 'additional_adult' => false, 'dependent_count' => 0];
		}

		$mode = sanitize_key($family_membership['mode'] ?? '');
		if ($mode !== 'family') {
			$mode = '';
		}

		return [
			'mode' => $mode,
			'additional_adult' => !empty($family_membership['additional_adult']) && $mode === 'family',
			'dependent_count' => $mode === 'family' ? max(0, min(3, (int) ($family_membership['dependent_count'] ?? 0))) : 0,
		];
	}

	private function sanitize_connected_accounts($connected_accounts) {
		if (!is_array($connected_accounts)) {
			return [];
		}

		return array_values(array_filter(array_map(function ($account) {
			if (!is_array($account)) {
				return null;
			}

			$type = sanitize_key($account['type'] ?? '');
			if (!in_array($type, ['adult', 'dependent'], true)) {
				$type = 'dependent';
			}

			$status = sanitize_key($account['status'] ?? 'pending');
			if (!in_array($status, ['pending', 'connected'], true)) {
				$status = 'pending';
			}

			return [
				'id' => sanitize_text_field($account['id'] ?? wp_generate_uuid4()),
				'type' => $type,
				'label' => sanitize_text_field($account['label'] ?? 'Family member'),
				'status' => $status,
				'invite_code' => sanitize_text_field($account['invite_code'] ?? ''),
				'child_user_id' => absint($account['child_user_id'] ?? 0),
				'child_name' => sanitize_text_field($account['child_name'] ?? ''),
				'child_email' => sanitize_email($account['child_email'] ?? ''),
				'price' => round((float) ($account['price'] ?? 0), 2),
			];
		}, $connected_accounts)));
	}

	private function sync_wp_user_from_account_info($user_id, $account_info) {
		$user = get_user_by('id', $user_id);
		if (!$user instanceof WP_User) {
			return new WP_Error('invalid_user', 'Unable to update this member account right now.', ['status' => 400]);
		}

		$first_name = $account_info['first_name'] ?? '';
		$last_name = $account_info['last_name'] ?? '';
		$display_name = $account_info['name'] ?? trim($first_name . ' ' . $last_name);
		$email = $account_info['email'] ?? '';

		$user_update = [
			'ID' => $user_id,
			'first_name' => $first_name,
			'last_name' => $last_name,
			'display_name' => $display_name,
		];

		if ($email && is_email($email)) {
			$user_update['user_email'] = $email;
		}

		$result = wp_update_user($user_update);
		if (is_wp_error($result)) {
			return new WP_Error('profile_update_failed', $result->get_error_message(), ['status' => 400]);
		}

		$refreshed_user = get_user_by('id', $user_id);
		if (!$refreshed_user instanceof WP_User) {
			return new WP_Error('profile_update_failed', 'Unable to refresh account information after saving.', ['status' => 500]);
		}

		return array_merge($account_info, [
			'first_name' => $refreshed_user->first_name,
			'last_name' => $refreshed_user->last_name,
			'name' => $refreshed_user->display_name,
			'email' => $refreshed_user->user_email,
		]);
	}

	private function sync_reportable_member_fields($user_id, $account_info) {
		$user_id = (int) $user_id;
		if ($user_id <= 0 || !is_array($account_info)) {
			return;
		}

		update_user_meta($user_id, 'aac_tshirt_size', sanitize_text_field($account_info['size'] ?? ''));
		update_user_meta($user_id, 'aac_publication_pref', sanitize_text_field($account_info['publication_pref'] ?? 'Digital'));
		update_user_meta($user_id, 'aac_guidebook_pref', sanitize_text_field($account_info['guidebook_pref'] ?? 'Digital'));

		$selected_addons = $this->get_member_magazine_subscription_slugs($user_id);
		$labels = $this->get_member_magazine_subscription_labels($user_id);

		update_user_meta($user_id, 'aac_magazine_subscription_labels', implode(', ', $labels));
		update_user_meta($user_id, 'aac_has_alpinist_subscription', in_array('alpinist', $selected_addons, true) ? '1' : '0');
		update_user_meta($user_id, 'aac_has_backcountry_subscription', in_array('backcountry', $selected_addons, true) ? '1' : '0');
	}

	private function get_member_magazine_subscription_slugs($user_id) {
		$stored = get_user_meta((int) $user_id, 'aac_magazine_addons', true);
		$stored = is_array($stored) ? $stored : [];
		$allowed = ['alpinist', 'backcountry'];

		return array_values(array_filter(array_map('sanitize_key', $stored), function ($slug) use ($allowed) {
			return in_array($slug, $allowed, true);
		}));
	}

	private function get_member_magazine_subscription_labels($user_id) {
		$labels_by_slug = [
			'alpinist' => 'Alpinist magazine',
			'backcountry' => 'Backcountry magazine',
		];

		return array_values(array_filter(array_map(function ($slug) use ($labels_by_slug) {
			return $labels_by_slug[$slug] ?? null;
		}, $this->get_member_magazine_subscription_slugs($user_id))));
	}

	private function sanitize_member_editable_grant_applications($grant_applications) {
		$grant_applications = $this->sanitize_grant_applications($grant_applications);

		return array_values(array_map(static function ($application) {
			$application['status'] = 'Pending review';
			return $application;
		}, $grant_applications));
	}

	private function consume_rate_limit($action, $identity, $limit, $window_seconds) {
		$key = 'aac_rate_limit_' . md5($action . '|' . $identity);
		$state = get_transient($key);
		$state = is_array($state) ? $state : ['count' => 0];
		$state['count'] = isset($state['count']) ? (int) $state['count'] + 1 : 1;

		set_transient($key, $state, (int) $window_seconds);

		if ($state['count'] > (int) $limit) {
			return new WP_Error(
				'rate_limited',
				'Too many attempts. Please wait a few minutes and try again.',
				['status' => 429]
			);
		}

		return true;
	}

	private function build_rate_limit_identity(WP_REST_Request $request, $email = '') {
		$email = strtolower(trim((string) $email));
		$ip_address = '';

		if (method_exists($request, 'get_header')) {
			$forwarded = $request->get_header('x_forwarded_for');
			if ($forwarded) {
				$parts = array_map('trim', explode(',', $forwarded));
				$ip_address = (string) ($parts[0] ?? '');
			}

			if ($ip_address === '') {
				$ip_address = (string) $request->get_header('x_real_ip');
			}
		}

		if ($ip_address === '' && !empty($_SERVER['REMOTE_ADDR'])) {
			$ip_address = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
		}

		return $email !== '' ? $ip_address . '|' . $email : $ip_address;
	}
}
