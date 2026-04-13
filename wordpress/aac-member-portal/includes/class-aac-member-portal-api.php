<?php

if (!defined('ABSPATH')) {
	exit;
}

class AAC_Member_Portal_API {
	const ROUTE_NAMESPACE = 'aac/v1';
	private static $instance = null;

	public function __construct() {
		self::$instance = $this;
		add_action('rest_api_init', [$this, 'register_routes']);
	}

	public static function get_instance() {
		return self::$instance;
	}

	public function register_routes() {
		// Public auth + recovery routes.
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

		register_rest_route(self::ROUTE_NAMESPACE, '/email-availability', [
			'methods' => 'GET',
			'callback' => [$this, 'email_availability'],
			'permission_callback' => '__return_true',
		]);

		register_rest_route(self::ROUTE_NAMESPACE, '/reset-password', [
			'methods' => 'POST',
			'callback' => [$this, 'reset_password'],
			'permission_callback' => '__return_true',
		]);

		register_rest_route(self::ROUTE_NAMESPACE, '/invite-code', [
			'methods' => 'GET',
			'callback' => [$this, 'validate_invite_code'],
			'permission_callback' => '__return_true',
		]);

		register_rest_route(self::ROUTE_NAMESPACE, '/redeem-invite', [
			'methods' => 'POST',
			'callback' => [$this, 'redeem_invite_code'],
			'permission_callback' => '__return_true',
		]);

		// Authenticated member account routes.
		register_rest_route(self::ROUTE_NAMESPACE, '/linked-accounts/remove', [
			'methods' => 'POST',
			'callback' => [$this, 'schedule_linked_account_removal'],
			'permission_callback' => [$this, 'is_logged_in'],
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

		// Admin-only diagnostics.
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
			return new WP_Error('invalid_credentials', 'Incorrect password. Please try again.', ['status' => 401]);
		}

		$signon = wp_signon([
			'user_login' => $user->user_login,
			'user_password' => $password,
			'remember' => true,
		], is_ssl());

		if (is_wp_error($signon)) {
			return new WP_Error('invalid_credentials', 'Incorrect password. Please try again.', ['status' => 401]);
		}

		$rest_nonce = $this->establish_fresh_auth_session($signon->ID);

		do_action('aac_member_portal_member_logged_in', $signon->ID, $request);

		return $this->build_auth_response($signon, $rest_nonce);
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

		if (email_exists($email)) {
			return new WP_Error('email_exists', 'An account with that email already exists.', ['status' => 409]);
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

		$rest_nonce = $this->establish_fresh_auth_session($user_id);

		do_action('aac_member_portal_member_registered', $user_id, $request);

		return rest_ensure_response(array_merge(
			['requires_email_verification' => false],
			$this->build_auth_response(get_user_by('id', $user_id), $rest_nonce)
		));
	}

	public function email_availability(WP_REST_Request $request) {
		$email = sanitize_email($request->get_param('email'));

		if (!$email || !is_email($email)) {
			return rest_ensure_response([
				'valid' => false,
				'available' => false,
				'message' => 'Enter a valid email address.',
			]);
		}

		$exists = (bool) email_exists($email);

		return rest_ensure_response([
			'valid' => true,
			'available' => !$exists,
			'message' => $exists
				? 'An account with this email already exists.'
				: 'Email address is available.',
		]);
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

	public function validate_invite_code(WP_REST_Request $request) {
		$invite_code = $this->normalize_invite_code($request->get_param('code'));

		if ($invite_code === '') {
			return new WP_Error('invalid_invite', 'Enter a valid invite code.', ['status' => 400]);
		}

		$rate_limit = $this->consume_rate_limit('invite_lookup', $this->build_rate_limit_identity($request, $invite_code), 20, 15 * MINUTE_IN_SECONDS);
		if (is_wp_error($rate_limit)) {
			return $rate_limit;
		}

		$match = $this->find_connected_account_slot_by_invite_code($invite_code);
		if (!$match) {
			return new WP_Error('invalid_invite', 'Invite code not found.', ['status' => 404]);
		}

		return rest_ensure_response([
			'success' => true,
			'invite' => $this->build_linked_account_invite_payload($match),
		]);
	}

	public function redeem_invite_code(WP_REST_Request $request) {
		$invite_code = $this->normalize_invite_code($request->get_param('invite_code'));
		if ($invite_code === '') {
			return new WP_Error('invalid_invite', 'Invite code is required.', ['status' => 400]);
		}

		$rate_limit_identity = $this->build_rate_limit_identity($request, sanitize_email($request->get_param('email')) ?: $invite_code);
		$rate_limit = $this->consume_rate_limit('invite_redeem', $rate_limit_identity, 10, 15 * MINUTE_IN_SECONDS);
		if (is_wp_error($rate_limit)) {
			return $rate_limit;
		}

		$match = $this->find_connected_account_slot_by_invite_code($invite_code);
		if (!$match) {
			return new WP_Error('invalid_invite', 'Invite code not found.', ['status' => 404]);
		}

		$parent_user_id = (int) $match['parent_user_id'];
		$slot = $match['account'];

		$current_user = wp_get_current_user();
		$child_user = null;
		$created_user_id = 0;

		if ($current_user instanceof WP_User && $current_user->exists()) {
			$child_user = $current_user;
		} else {
			$email = sanitize_email($request->get_param('email'));
			$password = (string) $request->get_param('password');
			$first_name = sanitize_text_field($request->get_param('first_name'));
			$last_name = sanitize_text_field($request->get_param('last_name'));

			if (!$email || !is_email($email)) {
				return new WP_Error('invalid_input', 'Enter a valid email address to redeem this invite.', ['status' => 400]);
			}

			if ($password === '') {
				return new WP_Error('invalid_input', 'Password is required to redeem this invite.', ['status' => 400]);
			}

			$existing_user = get_user_by('email', $email);
			if ($existing_user instanceof WP_User) {
				$signon = wp_signon([
					'user_login' => $existing_user->user_login,
					'user_password' => $password,
					'remember' => true,
				], is_ssl());

				if (is_wp_error($signon)) {
					return new WP_Error('invalid_credentials', 'Incorrect password. Please try again.', ['status' => 401]);
				}

				$child_user = $signon;
			} else {
				if (strlen($password) < 8) {
					return new WP_Error('invalid_input', 'Password must be at least 8 characters long.', ['status' => 400]);
				}

				$username = $this->generate_unique_username_from_email($email);
				$created_user_id = wp_create_user($username, $password, $email);
				if (is_wp_error($created_user_id)) {
					return $created_user_id;
				}

				wp_update_user([
					'ID' => $created_user_id,
					'first_name' => $first_name,
					'last_name' => $last_name,
					'display_name' => trim($first_name . ' ' . $last_name) ?: $email,
				]);

				update_user_meta($created_user_id, 'aac_account_info', [
					'first_name' => $first_name,
					'last_name' => $last_name,
					'name' => trim($first_name . ' ' . $last_name),
					'email' => $email,
					'size' => 'none',
				]);
				update_user_meta($created_user_id, 'aac_tshirt_size', 'none');

				$child_user = get_user_by('id', $created_user_id);
			}
		}

		if (!$child_user instanceof WP_User || !$child_user->exists()) {
			return new WP_Error('invite_redeem_failed', 'Unable to redeem this invite right now.', ['status' => 500]);
		}

		if ((int) $child_user->ID === $parent_user_id) {
			return new WP_Error('invalid_invite', 'The parent account cannot redeem its own invite code.', ['status' => 400]);
		}

		$existing_parent_link = absint(get_user_meta($child_user->ID, 'aac_linked_parent_user_id', true));
		if ($existing_parent_link > 0 && $existing_parent_link !== $parent_user_id) {
			return new WP_Error('invite_redeem_failed', 'This account is already linked to another family membership.', ['status' => 409]);
		}

		if (($slot['status'] ?? '') === 'connected' && absint($slot['child_user_id'] ?? 0) > 0 && absint($slot['child_user_id']) !== (int) $child_user->ID) {
			return new WP_Error('invite_redeem_failed', 'This invite code has already been redeemed.', ['status' => 409]);
		}

		$parent_accounts = $match['accounts'];
		$parent_accounts[$match['account_index']] = array_merge($slot, [
			'status' => 'connected',
			'child_user_id' => (int) $child_user->ID,
			'child_name' => trim($child_user->first_name . ' ' . $child_user->last_name) ?: $child_user->display_name,
			'child_email' => $child_user->user_email,
		]);
		update_user_meta($parent_user_id, 'aac_connected_accounts', array_values($parent_accounts));

		update_user_meta($child_user->ID, 'aac_linked_parent_user_id', $parent_user_id);
		update_user_meta($child_user->ID, 'aac_linked_account_slot_id', sanitize_text_field($slot['id'] ?? ''));
		update_user_meta($child_user->ID, 'aac_linked_account_invite_code', $invite_code);
		update_user_meta($child_user->ID, 'aac_linked_account_type', sanitize_key($slot['type'] ?? 'dependent'));
		update_user_meta($child_user->ID, 'aac_linked_account_label', sanitize_text_field($slot['label'] ?? 'Family member'));
		update_user_meta($parent_user_id, 'aac_family_account_role', 'Parent');
		update_user_meta($child_user->ID, 'aac_family_account_role', 'Child');
		delete_user_meta($child_user->ID, 'aac_family_membership_access_until');
		delete_user_meta($child_user->ID, 'aac_family_membership_pending_removal');

		$rest_nonce = $this->establish_fresh_auth_session($child_user->ID);

		return rest_ensure_response(array_merge(
			[
				'success' => true,
				'invite' => $this->build_linked_account_invite_payload($this->find_connected_account_slot_by_invite_code($invite_code)),
				'linked_parent_account' => $this->build_linked_parent_account($child_user->ID),
			],
			$this->build_auth_response($child_user, $rest_nonce)
		));
	}

	public function schedule_linked_account_removal(WP_REST_Request $request) {
		$parent_user_id = get_current_user_id();
		if ($parent_user_id <= 0) {
			return new WP_Error('not_authenticated', 'You must be signed in to manage linked accounts.', ['status' => 401]);
		}

		$slot_id = sanitize_text_field((string) $request->get_param('slot_id'));
		if ($slot_id === '') {
			return new WP_Error('invalid_input', 'A linked account selection is required.', ['status' => 400]);
		}

		$accounts = get_user_meta($parent_user_id, 'aac_connected_accounts', true);
		$accounts = is_array($accounts) ? $this->sanitize_connected_accounts($accounts) : [];
		if (empty($accounts)) {
			return new WP_Error('not_found', 'No linked accounts were found for this member.', ['status' => 404]);
		}

		$account_index = null;
		foreach ($accounts as $index => $account) {
			if (($account['id'] ?? '') === $slot_id) {
				$account_index = $index;
				break;
			}
		}

		if ($account_index === null) {
			return new WP_Error('not_found', 'That linked account could not be found.', ['status' => 404]);
		}

		$account = $accounts[$account_index];
		$child_user_id = absint($account['child_user_id'] ?? 0);
		$family_config = get_user_meta($parent_user_id, 'aac_partner_family_config', true);
		$family_config = is_array($family_config) ? $family_config : ['mode' => '', 'additional_adult' => false, 'dependent_count' => 0];
		if (($account['status'] ?? '') === 'removal_pending') {
			return rest_ensure_response([
				'success' => true,
				'profile' => $this->build_profile($parent_user_id),
			]);
		}

		if ($child_user_id <= 0) {
			unset($accounts[$account_index]);
			update_user_meta($parent_user_id, 'aac_connected_accounts', array_values($accounts));
			if (($account['type'] ?? '') === 'adult') {
				$family_config['additional_adult'] = false;
			} elseif (($account['type'] ?? '') === 'dependent') {
				$family_config['dependent_count'] = max(0, ((int) ($family_config['dependent_count'] ?? 0)) - 1);
			}
			if (empty($family_config['additional_adult']) && empty($family_config['dependent_count'])) {
				$family_config['mode'] = '';
			}
			update_user_meta($parent_user_id, 'aac_partner_family_config', $family_config);

			return rest_ensure_response([
				'success' => true,
				'profile' => $this->build_profile($parent_user_id),
			]);
		}

		$access_until = $this->get_family_membership_term_end_date($parent_user_id);
		if ($access_until === '') {
			return new WP_Error(
				'invalid_membership_state',
				'We could not determine the family plan renewal date for this linked account.',
				['status' => 409]
			);
		}

		$accounts[$account_index]['status'] = 'removal_pending';
		$accounts[$account_index]['scheduled_removal_date'] = $access_until;
		update_user_meta($parent_user_id, 'aac_connected_accounts', array_values($accounts));
		if (($account['type'] ?? '') === 'adult') {
			$family_config['additional_adult'] = false;
		} elseif (($account['type'] ?? '') === 'dependent') {
			$family_config['dependent_count'] = max(0, ((int) ($family_config['dependent_count'] ?? 0)) - 1);
		}
		if (empty($family_config['additional_adult']) && empty($family_config['dependent_count'])) {
			$family_config['mode'] = '';
		}
		update_user_meta($parent_user_id, 'aac_partner_family_config', $family_config);

		update_user_meta($child_user_id, 'aac_family_membership_access_until', $access_until);
		update_user_meta($child_user_id, 'aac_family_membership_pending_removal', '1');
		update_user_meta($child_user_id, 'aac_family_account_role', 'Child');

		return rest_ensure_response([
			'success' => true,
			'profile' => $this->build_profile($parent_user_id),
		]);
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

	public function get_profile_for_user($user_id) {
		$user_id = (int) $user_id;
		if ($user_id <= 0) {
			return [];
		}

		return $this->build_profile($user_id);
	}

	private function build_auth_response($user, $rest_nonce = null) {
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
			'restNonce' => $rest_nonce ?: wp_create_nonce('wp_rest'),
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
		$this->prune_expired_connected_accounts($user_id);
		$this->expire_scheduled_family_access_if_needed($user_id);

		$user = get_user_by('id', $user_id);
		$linked_parent_user_id = $this->get_linked_parent_user_id($user_id);
		$membership_owner_user_id = $linked_parent_user_id ?: $user_id;
		$account_info = get_user_meta($user_id, 'aac_account_info', true);
		$account_info = is_array($account_info) ? $account_info : [];
		$stored_profile_info = get_user_meta($user_id, 'aac_profile_info', true);
		$stored_profile_info = is_array($stored_profile_info) ? $stored_profile_info : [];
		$computed_profile_info = $this->build_profile_info($user_id, $membership_owner_user_id);
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
			'aaj_pref' => 'Digital',
			'anac_pref' => 'Digital',
			'acj_pref' => 'Digital',
			'phone_type' => '',
			'guidebook_pref' => 'Digital',
			'magazine_subscriptions' => [],
			'membership_discount_type' => '',
			'auto_renew' => false,
			'payment_method' => '',
		], $account_info, $this->get_normalized_publication_preferences($account_info));

		$account_info['magazine_subscriptions'] = $this->get_member_magazine_subscription_labels($user_id);
		$account_info['membership_discount_type'] = sanitize_key(get_user_meta($user_id, 'aac_membership_discount_type', true));

		$membership_actions = $this->build_membership_actions($membership_owner_user_id, $profile_info);

		if ($this->has_managed_membership_plugin()) {
			$account_info['payment_method'] = AAC_Member_Portal_PMPro::get_payment_method_summary($membership_owner_user_id);
			$account_info['auto_renew'] = AAC_Member_Portal_PMPro::has_active_auto_renewal(
				$membership_owner_user_id,
				$membership_actions['current_level_id'] ?? null
			);
		}

		if ($linked_parent_user_id > 0 && $this->is_family_membership_pending_removal($user_id)) {
			$account_info['auto_renew'] = false;
		}

		$grant_applications = get_user_meta($user_id, 'aac_grant_applications', true);
		$grant_applications = is_array($grant_applications)
			? $this->sanitize_grant_applications($grant_applications)
			: [];

		$connected_accounts = get_user_meta($user_id, 'aac_connected_accounts', true);
		$connected_accounts = is_array($connected_accounts)
			? $this->sanitize_connected_accounts($connected_accounts)
			: [];

		$linked_parent_account = $this->build_linked_parent_account($user_id);

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
			'linked_parent_account' => $linked_parent_account,
		];

		return apply_filters('aac_member_portal_profile', $profile, $user_id, $user);
	}

	private function build_profile_info($user_id, $membership_owner_user_id = null) {
		$member_id = get_user_meta($user_id, 'aac_member_id', true);
		$membership_owner_user_id = $membership_owner_user_id ? (int) $membership_owner_user_id : (int) $user_id;
		$is_linked_child_account = $membership_owner_user_id > 0 && $membership_owner_user_id !== (int) $user_id;
		$family_membership_access_until = $is_linked_child_account
			? $this->get_family_membership_access_until($user_id)
			: '';
		$family_membership_pending_removal = $is_linked_child_account && $this->is_family_membership_pending_removal($user_id);
		$family_membership_active_until = $family_membership_pending_removal
			? $this->normalize_family_membership_access_date($family_membership_access_until)
			: '';
		$is_child_membership_expired = $family_membership_pending_removal
			&& !$this->is_family_membership_active_through($family_membership_active_until);

		if (AAC_Member_Portal_PMPro::is_available() && !$is_child_membership_expired) {
			// Child/family-linked accounts inherit the parent membership timing and
			// status, but are surfaced as Partner in the portal experience.
			$primary = AAC_Member_Portal_PMPro::get_primary_membership($membership_owner_user_id);
			if ($primary) {
				$status_reference_date = $family_membership_pending_removal
					? $family_membership_active_until
					: ($primary['expiration_date'] ?: $primary['renewal_date']);
				$primary_tier = $is_linked_child_account
					? 'Partner'
					: (isset($primary['tier']) && $primary['tier'] === 'Partner Family'
						? 'Partner'
						: $primary['tier']);

				return [
					'member_id' => $member_id ?: sprintf('AAC-%d', $user_id),
					'tier' => $primary_tier,
					'renewal_date' => $family_membership_pending_removal ? '' : $primary['renewal_date'],
					'expiration_date' => $family_membership_pending_removal ? $family_membership_active_until : $primary['expiration_date'],
					'joined_date' => $primary['joined_date'] ?? '',
					'status' => $this->membership_status_pmpro($status_reference_date),
				];
			}
		}

		return [
			'member_id' => $member_id ?: sprintf('AAC-%d', $user_id),
			'tier' => 'Free',
			'renewal_date' => '',
			'expiration_date' => '',
			'joined_date' => '',
			'status' => 'Inactive',
		];
	}

	private function build_benefits_info($tier, $status = 'Active') {
		if ($status !== 'Active') {
			return [
				'rescue_amount' => 0,
				'medical_amount' => 0,
				'mortal_remains_amount' => 0,
				'rescue_reimbursement_process' => false,
			];
		}

		// Rescue benefit values now come from the admin-managed matrix so staff can
		// update coverage without editing PHP every time a level changes.
		$settings = AAC_Member_Portal_Admin::get_settings();
		$rescue_levels = isset($settings['content']['rescue_levels']) && is_array($settings['content']['rescue_levels'])
			? $settings['content']['rescue_levels']
			: AAC_Member_Portal_Admin::get_default_rescue_levels();

		$matrix = [];
		foreach ($rescue_levels as $level) {
			if (!is_array($level)) {
				continue;
			}

			$level_name = sanitize_text_field($level['level_name'] ?? '');
			if ($level_name === '') {
				continue;
			}

			$matrix[strtolower($level_name)] = [
				'rescue_amount' => max(0, (int) ($level['rescue_amount'] ?? 0)),
				'medical_amount' => max(0, (int) ($level['medical_amount'] ?? 0)),
				'mortal_remains_amount' => max(0, (int) ($level['mortal_remains_amount'] ?? 0)),
				'rescue_reimbursement_process' => !empty($level['rescue_reimbursement_process']),
			];
		}

		$fallback = $matrix['free'] ?? [
			'rescue_amount' => 0,
			'medical_amount' => 0,
			'mortal_remains_amount' => 0,
			'rescue_reimbursement_process' => false,
		];

		$normalized_tier = strtolower(trim((string) $tier));
		// Family/linked-account helper tiers should resolve to the same rescue
		// values as their parent published level.
		$tier_aliases = [
			'partner family' => 'partner',
			'partner adult' => 'partner',
			'partner dependent' => 'partner',
		];
		if (isset($tier_aliases[$normalized_tier])) {
			$normalized_tier = $tier_aliases[$normalized_tier];
		}

		return $matrix[$normalized_tier] ?? $fallback;
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

	private function get_normalized_publication_preferences($values) {
		$values = is_array($values) ? $values : [];
		$legacy_publication_pref = $this->normalize_print_digital_value($values['publication_pref'] ?? 'Digital');
		$guidebook_pref = $this->normalize_print_digital_value($values['guidebook_pref'] ?? 'Digital');

		return [
			'publication_pref' => $legacy_publication_pref,
			'aaj_pref' => $this->normalize_print_digital_value($values['aaj_pref'] ?? $legacy_publication_pref),
			'anac_pref' => $this->normalize_print_digital_value($values['anac_pref'] ?? $legacy_publication_pref),
			'acj_pref' => $this->normalize_print_digital_value($values['acj_pref'] ?? $legacy_publication_pref),
			'guidebook_pref' => $guidebook_pref,
		];
	}

	private function normalize_print_digital_value($value, $fallback = 'Digital') {
		return $value === 'Print' ? 'Print' : ($value === 'Digital' ? 'Digital' : $fallback);
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

		$publication_preferences = $this->get_normalized_publication_preferences($account_info);

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
			'publication_pref' => $publication_preferences['publication_pref'],
			'aaj_pref' => $publication_preferences['aaj_pref'],
			'anac_pref' => $publication_preferences['anac_pref'],
			'acj_pref' => $publication_preferences['acj_pref'],
			'guidebook_pref' => $publication_preferences['guidebook_pref'],
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
			'joined_date' => sanitize_text_field($profile_info['joined_date'] ?? ''),
			'status' => sanitize_text_field($profile_info['status'] ?? ''),
		];
	}

	private function sanitize_benefits_info($benefits_info) {
		return [
			'rescue_amount' => intval($benefits_info['rescue_amount'] ?? 0),
			'medical_amount' => intval($benefits_info['medical_amount'] ?? 0),
			'mortal_remains_amount' => intval($benefits_info['mortal_remains_amount'] ?? 0),
			'rescue_reimbursement_process' => !empty($benefits_info['rescue_reimbursement_process']),
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
			if (!in_array($status, ['pending', 'connected', 'removal_pending'], true)) {
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
				'scheduled_removal_date' => $this->normalize_family_membership_access_date($account['scheduled_removal_date'] ?? ''),
			];
		}, $connected_accounts)));
	}

	private function sanitize_linked_parent_account($linked_parent_account) {
		if (!is_array($linked_parent_account)) {
			return null;
		}

		return [
			'parent_user_id' => absint($linked_parent_account['parent_user_id'] ?? 0),
			'parent_name' => sanitize_text_field($linked_parent_account['parent_name'] ?? ''),
			'parent_email' => sanitize_email($linked_parent_account['parent_email'] ?? ''),
			'invite_code' => sanitize_text_field($linked_parent_account['invite_code'] ?? ''),
			'type' => sanitize_key($linked_parent_account['type'] ?? ''),
			'label' => sanitize_text_field($linked_parent_account['label'] ?? ''),
			'status' => sanitize_key($linked_parent_account['status'] ?? 'connected'),
			'scheduled_removal_date' => $this->normalize_family_membership_access_date($linked_parent_account['scheduled_removal_date'] ?? ''),
		];
	}

	private function build_linked_parent_account($user_id) {
		$parent_user_id = $this->get_linked_parent_user_id($user_id);
		if ($parent_user_id <= 0) {
			return null;
		}

		$parent_user = get_user_by('id', $parent_user_id);
		if (!$parent_user instanceof WP_User) {
			return null;
		}

		$slot_label = sanitize_text_field(get_user_meta($user_id, 'aac_linked_account_label', true));
		$slot_type = sanitize_key(get_user_meta($user_id, 'aac_linked_account_type', true));
		$invite_code = sanitize_text_field(get_user_meta($user_id, 'aac_linked_account_invite_code', true));
		$slot_status = $this->is_family_membership_pending_removal($user_id) ? 'removal_pending' : 'connected';
		$scheduled_removal_date = $this->get_family_membership_access_until($user_id);

		return $this->sanitize_linked_parent_account([
			'parent_user_id' => $parent_user_id,
			'parent_name' => trim($parent_user->first_name . ' ' . $parent_user->last_name) ?: $parent_user->display_name,
			'parent_email' => $parent_user->user_email,
			'invite_code' => $invite_code,
			'type' => $slot_type,
			'label' => $slot_label ?: 'Family member',
			'status' => $slot_status,
			'scheduled_removal_date' => $scheduled_removal_date,
		]);
	}

	private function get_linked_parent_user_id($user_id) {
		return absint(get_user_meta($user_id, 'aac_linked_parent_user_id', true));
	}

	private function normalize_invite_code($invite_code) {
		$invite_code = strtoupper(sanitize_text_field((string) $invite_code));
		return preg_replace('/[^A-Z0-9\-]/', '', $invite_code);
	}

	private function find_connected_account_slot_by_invite_code($invite_code) {
		$invite_code = $this->normalize_invite_code($invite_code);
		if ($invite_code === '') {
			return null;
		}

		$users = get_users([
			'meta_key' => 'aac_connected_accounts',
			'number' => -1,
			'fields' => ['ID', 'display_name', 'user_email'],
		]);

		foreach ($users as $user) {
			$accounts = get_user_meta($user->ID, 'aac_connected_accounts', true);
			$accounts = is_array($accounts) ? $this->sanitize_connected_accounts($accounts) : [];

			foreach ($accounts as $index => $account) {
				if ($this->normalize_invite_code($account['invite_code'] ?? '') !== $invite_code) {
					continue;
				}

				return [
					'parent_user_id' => (int) $user->ID,
					'parent_user' => $user,
					'accounts' => $accounts,
					'account' => $account,
					'account_index' => $index,
				];
			}
		}

		return null;
	}

	private function build_linked_account_invite_payload($match) {
		if (!is_array($match) || empty($match['account']) || empty($match['parent_user'])) {
			return null;
		}

		$parent_user = $match['parent_user'];
		$account = $match['account'];

		return [
			'code' => sanitize_text_field($account['invite_code'] ?? ''),
			'label' => sanitize_text_field($account['label'] ?? 'Family member'),
			'type' => sanitize_key($account['type'] ?? 'dependent'),
			'status' => sanitize_key($account['status'] ?? 'pending'),
			'price' => round((float) ($account['price'] ?? 0), 2),
			'parent_name' => trim(($parent_user->first_name ?? '') . ' ' . ($parent_user->last_name ?? '')) ?: $parent_user->display_name,
		];
	}

	private function generate_unique_username_from_email($email) {
		$email_parts = explode('@', (string) $email);
		$username = sanitize_user($email_parts[0] ?? 'aacmember', true);
		if (!$username) {
			$username = 'aacmember';
		}

		$base_username = $username;
		$suffix = 1;
		while (username_exists($username)) {
			$username = sprintf('%s%d', $base_username, $suffix);
			$suffix++;
		}

		return $username;
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

		$publication_preferences = $this->get_normalized_publication_preferences($account_info);
		update_user_meta($user_id, 'aac_tshirt_size', sanitize_text_field($account_info['size'] ?? ''));
		update_user_meta($user_id, 'aac_publication_pref', sanitize_text_field($publication_preferences['publication_pref']));
		update_user_meta($user_id, 'aac_aaj_pref', sanitize_text_field($publication_preferences['aaj_pref']));
		update_user_meta($user_id, 'aac_anac_pref', sanitize_text_field($publication_preferences['anac_pref']));
		update_user_meta($user_id, 'aac_acj_pref', sanitize_text_field($publication_preferences['acj_pref']));
		update_user_meta($user_id, 'aac_guidebook_pref', sanitize_text_field($publication_preferences['guidebook_pref']));

		$selected_addons = $this->get_member_magazine_subscription_slugs($user_id);
		$labels = $this->get_member_magazine_subscription_labels($user_id);

		update_user_meta($user_id, 'aac_magazine_subscription_labels', implode(', ', $labels));
		update_user_meta($user_id, 'aac_has_alpinist_subscription', in_array('alpinist', $selected_addons, true) ? '1' : '0');
		update_user_meta($user_id, 'aac_has_backcountry_subscription', in_array('backcountry', $selected_addons, true) ? '1' : '0');
		update_user_meta($user_id, 'aac_family_account_role', $this->get_family_account_role($user_id));
	}

	private function get_family_account_role($user_id) {
		$user_id = (int) $user_id;
		if ($user_id <= 0) {
			return '';
		}

		if ($this->get_linked_parent_user_id($user_id) > 0) {
			return 'Child';
		}

		$connected_accounts = get_user_meta($user_id, 'aac_connected_accounts', true);
		if (is_array($connected_accounts) && !empty($connected_accounts)) {
			return 'Parent';
		}

		return '';
	}

	private function get_family_membership_term_end_date($user_id) {
		$user_id = (int) $user_id;
		if ($user_id <= 0 || !AAC_Member_Portal_PMPro::is_available()) {
			return '';
		}

		$primary = AAC_Member_Portal_PMPro::get_primary_membership($user_id);
		if (!is_array($primary) || empty($primary)) {
			return '';
		}

		return $this->normalize_family_membership_access_date($primary['renewal_date'] ?: $primary['expiration_date']);
	}

	private function get_family_membership_access_until($user_id) {
		return $this->normalize_family_membership_access_date(get_user_meta((int) $user_id, 'aac_family_membership_access_until', true));
	}

	private function is_family_membership_pending_removal($user_id) {
		return get_user_meta((int) $user_id, 'aac_family_membership_pending_removal', true) === '1';
	}

	private function normalize_family_membership_access_date($value) {
		$value = sanitize_text_field((string) $value);
		if ($value === '') {
			return '';
		}

		$timestamp = strtotime($value);
		if (!$timestamp) {
			return '';
		}

		return gmdate('Y-m-d', $timestamp);
	}

	private function is_family_membership_active_through($value) {
		$normalized = $this->normalize_family_membership_access_date($value);
		if ($normalized === '') {
			return false;
		}

		return strtotime($normalized . ' 23:59:59') >= current_time('timestamp');
	}

	private function clear_family_child_linkage($user_id) {
		$user_id = (int) $user_id;
		if ($user_id <= 0) {
			return;
		}

		delete_user_meta($user_id, 'aac_linked_parent_user_id');
		delete_user_meta($user_id, 'aac_linked_account_slot_id');
		delete_user_meta($user_id, 'aac_linked_account_invite_code');
		delete_user_meta($user_id, 'aac_linked_account_type');
		delete_user_meta($user_id, 'aac_linked_account_label');
		delete_user_meta($user_id, 'aac_family_membership_access_until');
		delete_user_meta($user_id, 'aac_family_membership_pending_removal');
		update_user_meta($user_id, 'aac_family_account_role', '');
	}

	private function prune_expired_connected_accounts($user_id) {
		$user_id = (int) $user_id;
		if ($user_id <= 0) {
			return;
		}

		$accounts = get_user_meta($user_id, 'aac_connected_accounts', true);
		$accounts = is_array($accounts) ? $this->sanitize_connected_accounts($accounts) : [];
		if (empty($accounts)) {
			return;
		}

		$did_prune = false;
		$accounts = array_values(array_filter($accounts, function ($account) use (&$did_prune) {
			$scheduled_removal_date = $this->normalize_family_membership_access_date($account['scheduled_removal_date'] ?? '');
			if (($account['status'] ?? '') !== 'removal_pending' || $this->is_family_membership_active_through($scheduled_removal_date)) {
				return true;
			}

			$this->clear_family_child_linkage(absint($account['child_user_id'] ?? 0));
			$did_prune = true;
			return false;
		}));

		if ($did_prune) {
			if (empty($accounts)) {
				delete_user_meta($user_id, 'aac_connected_accounts');
			} else {
				update_user_meta($user_id, 'aac_connected_accounts', $accounts);
			}
		}
	}

	private function expire_scheduled_family_access_if_needed($user_id) {
		$user_id = (int) $user_id;
		if ($user_id <= 0 || !$this->is_family_membership_pending_removal($user_id)) {
			return;
		}

		$access_until = $this->get_family_membership_access_until($user_id);
		if ($this->is_family_membership_active_through($access_until)) {
			return;
		}

		$parent_user_id = $this->get_linked_parent_user_id($user_id);
		$slot_id = sanitize_text_field(get_user_meta($user_id, 'aac_linked_account_slot_id', true));
		if ($parent_user_id > 0) {
			$accounts = get_user_meta($parent_user_id, 'aac_connected_accounts', true);
			$accounts = is_array($accounts) ? $this->sanitize_connected_accounts($accounts) : [];
			$accounts = array_values(array_filter($accounts, static function ($account) use ($slot_id, $user_id) {
				$account_child_user_id = absint($account['child_user_id'] ?? 0);
				$account_id = sanitize_text_field($account['id'] ?? '');
				return $account_child_user_id !== (int) $user_id && ($slot_id === '' || $account_id !== $slot_id);
			}));
			update_user_meta($parent_user_id, 'aac_connected_accounts', $accounts);
		}

		$this->clear_family_child_linkage($user_id);
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
