<?php
if (!defined('ABSPATH') || !class_exists('PMPro_Member_Edit_Panel_User_Info')) exit;

class AAC_PMPro_Member_Information_Panel extends PMPro_Member_Edit_Panel_User_Info {
	public function __construct() {
		parent::__construct();
		$this->title = 'Member Information';
		$this->submit_text = $this->should_show() ? 'Save Member Information' : '';
		if (!empty($_GET['aac_member_information_saved'])) pmpro_setMessage('Member information saved.', 'pmpro_success');
	}
	public function should_show() {
		return parent::should_show() && current_user_can('manage_options') && current_user_can('edit_user', self::get_user()->ID);
	}
	protected function display_panel_contents() {
		$user = self::get_user();
		echo '<p>Update profile details, addresses, emergency contacts, publications and communication preferences here. Memberships, subscriptions and family links are managed in their own tabs.</p>';
		echo '<table class="form-table"><tbody><tr><th>Username</th><td>' . esc_html($user->user_login) . '</td></tr><tr><th>AAC member number</th><td>' . esc_html(get_user_meta($user->ID, 'aac_member_id', true)) . '</td></tr><tr><th>WordPress role</th><td>' . esc_html(implode(', ', (array) $user->roles)) . '</td></tr></tbody></table>';
		AAC_Member_Portal_Member_Database::get_instance()->render_edit_member_form($user->ID, true);
		echo '<h3>WordPress Profile</h3><table class="form-table"><tbody>';
		foreach (['display_name' => 'Display name', 'nickname' => 'Nickname', 'user_url' => 'Website', 'description' => 'Biographical information', 'user_notes' => 'Member notes'] as $key => $label) {
			$value = $user->$key;
			echo '<tr><th><label for="aac-wp-' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td>';
			if (in_array($key, ['description', 'user_notes'], true)) echo '<textarea class="large-text" rows="4" id="aac-wp-' . esc_attr($key) . '" name="wordpress_profile[' . esc_attr($key) . ']">' . esc_textarea($value) . '</textarea>';
			else echo '<input class="regular-text" type="' . ($key === 'user_url' ? 'url' : 'text') . '" id="aac-wp-' . esc_attr($key) . '" name="wordpress_profile[' . esc_attr($key) . ']" value="' . esc_attr($value) . '">';
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}
	public function save() {
		$user = self::get_user();
		if (!$this->should_show()) { pmpro_setMessage('You cannot edit this member.', 'pmpro_error'); return; }
		if (empty($_POST['pmpro_member_edit_saved_panel_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pmpro_member_edit_saved_panel_nonce'])), 'pmpro_member_edit_saved_panel_user-info')) return;
		$changes = isset($_POST['member']) && is_array($_POST['member']) ? wp_unslash($_POST['member']) : [];
		$extra = isset($_POST['wordpress_profile']) && is_array($_POST['wordpress_profile']) ? wp_unslash($_POST['wordpress_profile']) : [];
		$update = ['ID' => $user->ID];
		$notes = null;
		foreach (['display_name', 'nickname', 'user_url', 'description', 'user_notes'] as $key) {
			if (!array_key_exists($key, $extra)) continue;
			if (!is_scalar($extra[$key])) { pmpro_setMessage('Invalid WordPress profile field.', 'pmpro_error'); return; }
			if ($key === 'user_notes') { $notes = sanitize_textarea_field($extra[$key]); continue; }
			$update[$key] = in_array($key, ['description', 'user_notes'], true) ? sanitize_textarea_field($extra[$key]) : ($key === 'user_url' ? esc_url_raw($extra[$key]) : sanitize_text_field($extra[$key]));
		}
		$result = AAC_Member_Portal_API::get_instance()->admin_update_member($user->ID, $changes, true);
		if (is_wp_error($result)) { pmpro_setMessage(esc_html($result->get_error_message()), 'pmpro_error'); return; }
		if (count($update) > 1) {
			$result = wp_update_user($update);
			if (is_wp_error($result)) { pmpro_setMessage(esc_html($result->get_error_message()), 'pmpro_error'); return; }
		}
		if ($notes !== null) update_user_meta($user->ID, 'user_notes', $notes);
		update_user_meta($user->ID, 'aac_last_admin_profile_edit', ['admin_id' => get_current_user_id(), 'at' => current_time('mysql')]);
		AAC_Member_Portal_API::get_instance()->flush_profile_cache_for_user($user->ID);
		AAC_Member_Portal_Member_Database::get_instance()->sync_member($user->ID);
		wp_safe_redirect(add_query_arg(['page' => 'pmpro-member', 'user_id' => $user->ID, 'aac_member_information_saved' => 1], admin_url('admin.php'))); exit;
	}
}

class AAC_PMPro_Family_Membership_Panel extends PMPro_Member_Edit_Panel_Memberships {
	/** Display only: does not change access dates or billing records. */
	public static function parent_expiration($child_id) {
		$parent_id = absint(get_user_meta($child_id, 'aac_linked_parent_user_id', true));
		if (!$parent_id || $parent_id === (int) $child_id) return null;
		$parent = get_user_by('id', $parent_id);
		if (!$parent) return null;
		$levels = function_exists('pmpro_getMembershipLevelsForUser') ? pmpro_getMembershipLevelsForUser($parent_id) : [];
		$dates = [];
		foreach ((array) $levels as $level) {
			$value = $level->enddate ?? '';
			$timestamp = 0;
			if (is_numeric($value)) $timestamp = (int) $value;
			elseif ($value && strpos($value, '0000-00-00') !== 0) {
				try { $timestamp = (new DateTimeImmutable($value, wp_timezone()))->getTimestamp(); }
				catch (Exception $error) { return ['parent' => $parent, 'date' => 'Parent expiration could not be read']; }
			}
			if (!$timestamp) return ['parent' => $parent, 'date' => 'No expiration date set'];
			$dates[] = $timestamp;
		}
		return ['parent' => $parent, 'date' => $dates ? wp_date(get_option('date_format'), max($dates), wp_timezone()) : 'No active parent membership'];
	}
	protected function display_panel_contents() {
		$info = self::parent_expiration(self::get_user()->ID);
		if ($info) echo '<div class="pmpro_card"><h3>Parent Membership Expiration</h3><p>Parent: <a href="' . esc_url(add_query_arg(['page' => 'pmpro-member', 'user_id' => $info['parent']->ID, 'pmpro_member_edit_panel' => 'memberships'], admin_url('admin.php'))) . '">' . esc_html($info['parent']->display_name) . '</a></p><p><strong>' . esc_html($info['date']) . '</strong></p><p>This is the linked parent’s current membership expiration. The child’s stored membership dates remain listed below.</p></div>';
		parent::display_panel_contents();
	}
}
