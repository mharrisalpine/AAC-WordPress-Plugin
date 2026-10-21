<?php
if (!defined('ABSPATH')) exit;

/** Native PMPro panels, loaded only after PMPro has defined its panel classes. */
add_filter('pmpro_member_edit_panels', static function ($panels) {
	if (!class_exists('PMPro_Member_Edit_Panel_User_Info') || !class_exists('PMPro_Member_Edit_Panel_Memberships')) return $panels;
	require_once __DIR__ . '/class-aac-member-portal-pmpro-member-panels.php';
	foreach ($panels as $key => $panel) {
		if (get_class($panel) === 'PMPro_Member_Edit_Panel_User_Info' && !empty(PMPro_Member_Edit_Panel::get_user()->ID) && current_user_can('manage_options') && current_user_can('edit_user', PMPro_Member_Edit_Panel::get_user()->ID)) $panels[$key] = new AAC_PMPro_Member_Information_Panel();
		if (get_class($panel) === 'PMPro_Member_Edit_Panel_Memberships') $panels[$key] = new AAC_PMPro_Family_Membership_Panel();
	}
	return $panels;
});
