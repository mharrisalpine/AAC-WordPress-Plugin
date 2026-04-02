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
	}

	public function is_logged_in() {
		return is_user_logged_in();
	}

	public function login(WP_REST_Request $request) {
		$email = sanitize_email($request->get_param('email'));
		$password = (string) $request->get_param('password');
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
		return rest_ensure_response(['success' => true]);
	}

	public function register_member(WP_REST_Request $request) {
		$email = sanitize_email($request->get_param('email'));
		$password = (string) $request->get_param('password');
		$first_name = sanitize_text_field($request->get_param('first_name'));
		$last_name = sanitize_text_field($request->get_param('last_name'));
		$username = sanitize_user($request->get_param('username'), true);

		if (!$email || !$password) {
			return new WP_Error('invalid_input', 'Email and password are required.', ['status' => 400]);
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
		$user = get_user_by('email', $email);

		if (!$user) {
			return new WP_Error('not_found', 'No user found for that email.', ['status' => 404]);
		}

		$key = get_password_reset_key($user);
		if (is_wp_error($key)) {
			return $key;
		}

		$reset_url = network_site_url("wp-login.php?action=rp&key={$key}&login=" . rawurlencode($user->user_login), 'login');

		wp_mail(
			$user->user_email,
			'Password Reset',
			"Use this link to reset your password:\n\n{$reset_url}"
		);

		return rest_ensure_response(['success' => true]);
	}

	public function me() {
		return rest_ensure_response($this->build_auth_response(wp_get_current_user()));
	}

	public function update_profile(WP_REST_Request $request) {
		$user_id = get_current_user_id();
		$account_info = $request->get_param('account_info');
		$profile_info = $request->get_param('profile_info');
		$benefits_info = $request->get_param('benefits_info');

		if (!is_array($account_info)) {
			return new WP_Error('invalid_input', 'account_info must be an object.', ['status' => 400]);
		}

		$sanitized_account_info = $this->sanitize_account_info($account_info);
		update_user_meta($user_id, 'aac_account_info', $sanitized_account_info);
		$this->sync_wp_user_from_account_info($user_id, $sanitized_account_info);

		if (is_array($profile_info)) {
			update_user_meta($user_id, 'aac_profile_info', $this->sanitize_profile_info($profile_info));
		}

		if (is_array($benefits_info)) {
			update_user_meta($user_id, 'aac_benefits_info', $this->sanitize_benefits_info($benefits_info));
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

		if (!$message) {
			return new WP_Error('invalid_input', 'Message is required.', ['status' => 400]);
		}

		$admin_email = get_option('admin_email');
		$subject = 'AAC Member Portal Contact Message';
		$body = sprintf(
			"Name: %s\nEmail: %s\n\n%s",
			sanitize_text_field($request->get_param('name')),
			sanitize_email($request->get_param('email')) ?: $current_user->user_email,
			$message
		);

		wp_mail($admin_email, $subject, $body);

		return rest_ensure_response(['success' => true]);
	}

	public function podcasts() {
		$podcasts = get_posts([
			'post_type' => 'podcast',
			'post_status' => 'publish',
			'numberposts' => 5,
		]);

		$items = array_map(function ($post) {
			return [
				'embed_url' => get_post_meta($post->ID, 'embed_url', true),
			];
		}, $podcasts);

		return rest_ensure_response([
			'podcasts' => array_values(array_filter($items, function ($item) {
				return !empty($item['embed_url']);
			})),
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
		];
	}

	private function build_profile($user_id) {
		$user = get_user_by('id', $user_id);
		$account_info = get_user_meta($user_id, 'aac_account_info', true);
		$account_info = is_array($account_info) ? $account_info : [];
		$stored_profile_info = get_user_meta($user_id, 'aac_profile_info', true);
		$stored_profile_info = is_array($stored_profile_info) ? $stored_profile_info : [];
		$profile_info = array_merge($this->build_profile_info($user_id), $stored_profile_info);
		$stored_benefits_info = get_user_meta($user_id, 'aac_benefits_info', true);
		$stored_benefits_info = is_array($stored_benefits_info) ? $stored_benefits_info : [];
		$benefits_info = array_merge($this->build_benefits_info($profile_info['tier']), $stored_benefits_info);

		$profile = [
			'account_info' => array_merge([
				'first_name' => $user->first_name,
				'last_name' => $user->last_name,
				'name' => $user->display_name,
				'email' => $user->user_email,
				'photo_url' => get_avatar_url($user_id),
				'phone' => '',
				'street' => '',
				'city' => '',
				'state' => '',
				'zip' => '',
				'country' => '',
				'size' => 'M',
				'publication_pref' => 'Digital',
				'auto_renew' => false,
				'payment_method' => '',
			], $account_info),
			'profile_info' => $profile_info,
			'benefits_info' => $benefits_info,
		];

		return apply_filters('aac_member_portal_profile', $profile, $user_id, $user);
	}

	private function build_profile_info($user_id) {
		$member_id = get_user_meta($user_id, 'aac_member_id', true);
		$membership = function_exists('pmpro_getMembershipLevelForUser')
			? pmpro_getMembershipLevelForUser($user_id)
			: null;

		$tier = $membership && !empty($membership->name) ? $membership->name : 'Supporter';
		$renewal_date = '';
		if ($membership && !empty($membership->enddate)) {
			$renewal_date = gmdate('Y-m-d', strtotime($membership->enddate));
		}

		return [
			'member_id' => $member_id ?: sprintf('AAC-%d', $user_id),
			'tier' => $tier,
			'renewal_date' => $renewal_date,
			'status' => $this->membership_status($membership, $renewal_date),
		];
	}

	private function build_benefits_info($tier) {
		$matrix = [
			'Supporter' => ['rescue_amount' => 0, 'medical_amount' => 0],
			'Partner' => ['rescue_amount' => 50000, 'medical_amount' => 5000],
			'Leader' => ['rescue_amount' => 100000, 'medical_amount' => 10000],
			'Lifetime' => ['rescue_amount' => 100000, 'medical_amount' => 10000],
			'' => ['rescue_amount' => 0, 'medical_amount' => 0],
		];

		return $matrix[$tier] ?? $matrix['Supporter'];
	}

	private function membership_status($membership, $renewal_date) {
		if (!$membership) {
			return 'Inactive';
		}

		if (!$renewal_date) {
			return 'Active';
		}

		return strtotime($renewal_date) >= current_time('timestamp') ? 'Active' : 'Inactive';
	}

	private function sanitize_account_info($account_info) {
		$first_name = sanitize_text_field($account_info['first_name'] ?? '');
		$last_name = sanitize_text_field($account_info['last_name'] ?? '');
		$name = trim($first_name . ' ' . $last_name);
		if (!$name) {
			$name = sanitize_text_field($account_info['name'] ?? '');
		}

		return [
			'first_name' => $first_name,
			'last_name' => $last_name,
			'name' => $name,
			'email' => sanitize_email($account_info['email'] ?? ''),
			'photo_url' => esc_url_raw($account_info['photo_url'] ?? ''),
			'phone' => sanitize_text_field($account_info['phone'] ?? ''),
			'street' => sanitize_text_field($account_info['street'] ?? ''),
			'city' => sanitize_text_field($account_info['city'] ?? ''),
			'state' => sanitize_text_field($account_info['state'] ?? ''),
			'zip' => sanitize_text_field($account_info['zip'] ?? ''),
			'country' => sanitize_text_field($account_info['country'] ?? ''),
			'size' => sanitize_text_field($account_info['size'] ?? 'M'),
			'publication_pref' => sanitize_text_field($account_info['publication_pref'] ?? 'Digital'),
			'auto_renew' => !empty($account_info['auto_renew']),
			'payment_method' => sanitize_text_field($account_info['payment_method'] ?? ''),
		];
	}

	private function sanitize_profile_info($profile_info) {
		return [
			'member_id' => sanitize_text_field($profile_info['member_id'] ?? ''),
			'tier' => sanitize_text_field($profile_info['tier'] ?? ''),
			'renewal_date' => sanitize_text_field($profile_info['renewal_date'] ?? ''),
			'status' => sanitize_text_field($profile_info['status'] ?? ''),
		];
	}

	private function sanitize_benefits_info($benefits_info) {
		return [
			'rescue_amount' => intval($benefits_info['rescue_amount'] ?? 0),
			'medical_amount' => intval($benefits_info['medical_amount'] ?? 0),
		];
	}

	private function sync_wp_user_from_account_info($user_id, $account_info) {
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

		wp_update_user($user_update);
	}
}
