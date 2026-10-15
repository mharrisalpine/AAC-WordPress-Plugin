<?php
// Exercise the same option-specific hooks WordPress invokes after saving.
define('ABSPATH', __DIR__);
$hooks = [];
function add_action($hook, $callback, $priority = 10, $args = 1) { global $hooks; $hooks[$hook][] = $callback; }
function add_filter($hook, $callback, $priority = 10, $args = 1) {}
require __DIR__ . '/../wordpress/aac-member-portal/includes/class-aac-member-portal-admin.php';
class WpeCommon {
    public static $purges = 0;
    public static function purge_varnish_cache() { self::$purges++; }
}
function check($ok) { if (!$ok) throw new RuntimeException('Cache refresh regression'); }
$admin = new AAC_Member_Portal_Admin();
check(isset($hooks['update_option_aac_member_portal_settings'], $hooks['add_option_aac_member_portal_settings']));
check(!isset($hooks['updated_option']));
$hooks['update_option_aac_member_portal_settings'][0]();
$hooks['add_option_aac_member_portal_settings'][0]();
check(WpeCommon::$purges === 0 && count($hooks['shutdown']) === 1);
$hooks['shutdown'][0]();
check(WpeCommon::$purges === 1);
$admin->refresh_settings_page_cache();
check(WpeCommon::$purges === 1);
echo "Settings cache refresh regression checks passed.\n";
