<?php

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Assigns stable AAC member numbers independently of WordPress user IDs.
 */
final class AAC_Member_Portal_Member_Number {
	const VERSION = '1.0.0';
	const VERSION_OPTION = 'aac_member_number_allocator_version';
	const LAST_NUMBER_OPTION = 'aac_member_number_last_assigned';
	const STARTED_AT_OPTION = 'aac_member_number_allocator_started_at';
	const NEW_USER_META = 'aac_member_number_pending';
	const FIRST_NUMBER = 131889;
	const LOCK_NAME = 'aac_member_number_allocator';

	private static $updating = false;

	public function __construct() {
		add_action('init', [$this, 'maybe_install'], 2);
		add_action('user_register', [$this, 'mark_new_user'], 5, 1);
		add_action('aac_member_portal_member_registered', [$this, 'assign_to_registered_member'], 5, 1);
		add_action('pmpro_after_change_membership_level', [$this, 'assign_after_membership_change'], 8, 2);
		add_action('added_user_meta', [$this, 'observe_member_number'], 5, 4);
		add_action('updated_user_meta', [$this, 'observe_member_number'], 5, 4);
	}

	public function maybe_install() {
		if (get_option(self::VERSION_OPTION) === self::VERSION) {
			return;
		}

		if (!get_option(self::STARTED_AT_OPTION)) {
			update_option(self::STARTED_AT_OPTION, current_time('mysql'), false);
		}

		$this->with_lock(function () {
			$last = (int) get_option(self::LAST_NUMBER_OPTION, 0);
			if ($last < self::FIRST_NUMBER) {
				update_option(self::LAST_NUMBER_OPTION, self::FIRST_NUMBER, false);
			}
			$this->seed_vertiigo_member_number();
		});

		update_option(self::VERSION_OPTION, self::VERSION, false);
	}

	public function mark_new_user($user_id) {
		update_user_meta((int) $user_id, self::NEW_USER_META, '1');
	}

	public function assign_to_registered_member($user_id) {
		$this->assign_if_needed((int) $user_id);
	}

	public function assign_after_membership_change($level_id, $user_id) {
		if ((int) $level_id <= 0) {
			return;
		}

		$this->assign_if_needed((int) $user_id);
	}

	public function observe_member_number($meta_id, $user_id, $meta_key, $meta_value) {
		if (self::$updating || $meta_key !== 'aac_member_id') {
			return;
		}

		$number = $this->normalize_number($meta_value);
		if ($number < self::FIRST_NUMBER || $number === (int) $user_id) {
			return;
		}

		$this->with_lock(function () use ($number) {
			if ($number > (int) get_option(self::LAST_NUMBER_OPTION, 0)) {
				update_option(self::LAST_NUMBER_OPTION, $number, false);
			}
		});
	}

	private function assign_if_needed($user_id) {
		if ($user_id <= 0 || !get_user_by('id', $user_id)) {
			return;
		}

		$current = $this->normalize_number(get_user_meta($user_id, 'aac_member_id', true));
		if ($current > 0 && $current !== $user_id) {
			delete_user_meta($user_id, self::NEW_USER_META);
			$this->observe_member_number(0, $user_id, 'aac_member_id', $current);
			return;
		}

		if (get_user_meta($user_id, self::NEW_USER_META, true) !== '1') {
			return;
		}

		$this->with_lock(function () use ($user_id) {
			$current = $this->normalize_number(get_user_meta($user_id, 'aac_member_id', true));
			if ($current > 0 && $current !== $user_id) {
				delete_user_meta($user_id, self::NEW_USER_META);
				return;
			}

			$candidate = max(self::FIRST_NUMBER, (int) get_option(self::LAST_NUMBER_OPTION, self::FIRST_NUMBER)) + 1;
			while ($this->member_number_exists($candidate, $user_id)) {
				$candidate++;
			}

			$this->write_member_number($user_id, $candidate);
			update_option(self::LAST_NUMBER_OPTION, $candidate, false);
			delete_user_meta($user_id, self::NEW_USER_META);
		});
	}

	private function seed_vertiigo_member_number() {
		$user = get_user_by('email', 'vertiigo@therust.com');
		if (!$user instanceof WP_User || (int) $user->ID !== 259262982) {
			return;
		}

		if ($this->member_number_exists(self::FIRST_NUMBER, (int) $user->ID)) {
			return;
		}

		$this->write_member_number((int) $user->ID, self::FIRST_NUMBER);
		delete_user_meta((int) $user->ID, self::NEW_USER_META);
	}

	private function write_member_number($user_id, $number) {
		self::$updating = true;
		update_user_meta($user_id, 'aac_member_id', (string) $number);
		$profile_info = get_user_meta($user_id, 'aac_profile_info', true);
		$profile_info = is_array($profile_info) ? $profile_info : [];
		$profile_info['member_id'] = (string) $number;
		update_user_meta($user_id, 'aac_profile_info', $profile_info);
		self::$updating = false;
	}

	private function member_number_exists($number, $exclude_user_id = 0) {
		$users = get_users([
			'meta_key' => 'aac_member_id',
			'meta_value' => (string) $number,
			'number' => 2,
			'fields' => 'ids',
		]);

		foreach ((array) $users as $user_id) {
			if ((int) $user_id !== (int) $exclude_user_id) {
				return true;
			}
		}

		return false;
	}

	private function normalize_number($value) {
		$value = trim((string) $value);
		return ctype_digit($value) ? (int) $value : 0;
	}

	private function with_lock($callback) {
		global $wpdb;

		if (!$wpdb) {
			return null;
		}

		$acquired = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 10)', self::LOCK_NAME));
		if ($acquired !== 1) {
			return null;
		}

		try {
			return call_user_func($callback);
		} finally {
			$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', self::LOCK_NAME));
		}
	}
}
