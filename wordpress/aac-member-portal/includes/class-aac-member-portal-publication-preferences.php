<?php

if (!defined('ABSPATH')) {
	exit;
}

final class AAC_Member_Portal_Publication_Preferences {
	const FIELDS = [
		'aaj_preference',
		'anac_preference',
		'american_climbing_journal_preference',
		'guidebook_preferences',
	];

	/**
	 * Store audit timestamps for each PMPro publication preference.
	 *
	 * The created timestamp is immutable. The updated timestamp is only written
	 * after an existing preference changes, so initial creation is not reported
	 * as an edit.
	 */
	public static function sync_timestamps($user_id, $preferences) {
		$user_id = (int) $user_id;
		if ($user_id <= 0 || !is_array($preferences)) {
			return;
		}

		$timestamp = current_time('mysql', true);
		foreach (self::FIELDS as $meta_key) {
			if (!array_key_exists($meta_key, $preferences)) {
				continue;
			}

			$new_value = sanitize_text_field((string) $preferences[$meta_key]);
			if ($new_value === '') {
				continue;
			}

			$created_key = $meta_key . '_created_at';
			$updated_key = $meta_key . '_updated_at';
			$existing_value = (string) get_user_meta($user_id, $meta_key, true);
			$created_at = (string) get_user_meta($user_id, $created_key, true);

			if ($created_at === '') {
				add_user_meta($user_id, $created_key, $timestamp, true);
			}

			if ($existing_value !== '' && $existing_value !== $new_value) {
				update_user_meta($user_id, $updated_key, $timestamp);
			}
		}
	}

	public static function get_timestamps($user_id) {
		$user_id = (int) $user_id;
		$timestamps = [];
		if ($user_id <= 0) {
			return $timestamps;
		}

		foreach (self::FIELDS as $meta_key) {
			$created_at = (string) get_user_meta($user_id, $meta_key . '_created_at', true);
			$updated_at = (string) get_user_meta($user_id, $meta_key . '_updated_at', true);
			$timestamps[$meta_key . '_created_at'] = $created_at;
			$timestamps[$meta_key . '_updated_at'] = $updated_at;
			$timestamps[$meta_key . '_last_updated'] = $updated_at !== '' ? $updated_at : $created_at;
		}

		return $timestamps;
	}
}
