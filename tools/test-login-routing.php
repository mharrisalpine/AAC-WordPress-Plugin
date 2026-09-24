<?php
// Execute the production routing methods without credentials or a WordPress server.
$source = file_get_contents(__DIR__ . '/../wordpress/aac-member-portal/aac-member-portal.php');
$methods = ['maybe_redirect_frontend_login_to_portal', 'filter_login_url_to_portal', 'filter_administrator_login_redirect', 'is_frontend_login_request', 'is_wp_admin_auth_request', 'should_preserve_wp_login_url', 'build_wp_login_url_from_current_request', 'is_wp_admin_url', 'is_wp_admin_path', 'build_portal_login_url', 'get_current_request_path', 'get_current_request_url', 'get_wp_login_base_url', 'normalize_path'];
$code = '';
foreach ($methods as $method) {
	preg_match('/\n\t(?:public|private) function ' . $method . '\(/', $source, $match, PREG_OFFSET_CAPTURE);
	$start = $match[0][1];
	preg_match('/\n\t(?:public|private|protected) (?:static )?function /', $source, $next, PREG_OFFSET_CAPTURE, $start + strlen($match[0][0]));
	$code .= substr($source, $start, $next[0][1] - $start);
}
class WP_Post { public $post_content = ''; }
class WP_User { public function has_cap($cap) { return true; } }
class TestRedirect extends RuntimeException {}
function home_url($path = '') { return 'https://aac.example' . $path; }
function admin_url() { return home_url('/wp-admin/'); }
function is_admin() { return $GLOBALS['admin'] ?? false; }
function is_user_logged_in() { return false; }
function wp_doing_ajax() { return false; }
function get_queried_object() { return new WP_Post(); }
function get_option($key) { return 77; }
function is_page($id) { return parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) === '/custom-member-access/'; }
function has_shortcode($content,$shortcode) { return str_contains($content, '[' . $shortcode . ']'); }
function wp_unslash($s) { return $s; }
function wp_parse_url($url,$component = -1) { return parse_url($url,$component); }
function untrailingslashit($s) { return rtrim($s,'/'); }
function sanitize_key($s) { return strtolower(preg_replace('/[^a-zA-Z0-9_-]/','',$s)); }
function sanitize_text_field($s) { return trim(strip_tags($s)); }
function wp_validate_redirect($s,$fallback) { return str_starts_with($s, home_url('/')) || (str_starts_with($s,'/') && !str_starts_with($s,'//')) ? $s : $fallback; }
function nocache_headers() {}
function wp_safe_redirect($url) { throw new TestRedirect($url); }
function add_query_arg($key, $value, $url = null) {
	if (is_array($key)) { $url = $value; $args = $key; } else { $args = [$key=>$value]; }
	$parts = parse_url($url); parse_str($parts['query'] ?? '',$existing);
	$query = http_build_query(array_merge($existing,$args));
	return strtok($url,'?') . ($query ? '?' . $query : '');
}
eval('class LoginRoutingHarness { const LOGIN_SHORTCODE = "aac_member_login"; private function get_portal_page_url() { return home_url("/member-profile/"); } private function is_pmpro_confirmation_url($url) { return false; }' . $code . '}');
$h = new LoginRoutingHarness();
function check($ok,$message) { if (!$ok) { throw new RuntimeException($message); } }
function visit($h,$path,$query = [],$method = 'GET') {
	$_GET = $_REQUEST = $query; $_SERVER['REQUEST_URI'] = $path . ($query ? '?' . http_build_query($query) : ''); $_SERVER['REQUEST_METHOD'] = $method;
	try { $h->maybe_redirect_frontend_login_to_portal(); return null; } catch (TestRedirect $e) { return $e->getMessage(); }
}
foreach (['/login/','/login-2/','/sign-in/','/signin/','/custom-member-access/'] as $path) {
	check(visit($h,$path) === home_url('/member-profile/#/login'), 'Public alias uses canonical sign-in: ' . $path);
	$target = home_url('/wp-admin/admin.php?page=aac-member-portal-koha-sync');
	$url = visit($h,$path,['redirect_to'=>$target]); parse_str(parse_url($url,PHP_URL_QUERY),$args);
	check(parse_url($url,PHP_URL_PATH) === '/wp-login.php' && $args['redirect_to'] === $target, 'Admin uses native auth with exact return');
}
check(visit($h,'/unrelated/') === null,'Unrelated page untouched');
check(visit($h,'/login-2/',['redirect_to'=>admin_url()],'POST') === null,'POST never redirected or discarded');
check(visit($h,'/login-2/',['redirect_to'=>'https://evil.example/']) === home_url('/member-profile/#/login'),'Reject external return');
$url = visit($h,'/login-2/',['action'=>'reset_pass','key'=>'token-test','login'=>'test-user']); parse_str(parse_url($url,PHP_URL_QUERY),$args);
check($args === ['action'=>'rp','key'=>'token-test','login'=>'test-user'],'Reset token preserved and PMPro action mapped');
$url = visit($h,'/login-2/',['action'=>'reset_pass']);
check(str_contains($url,'action=lostpassword'),'Lost password mapping');
$_GET = $_REQUEST = []; $_SERVER['REQUEST_URI'] = '/wp-admin/'; $GLOBALS['admin'] = true;
$url = $h->filter_login_url_to_portal(home_url('/login-2/'),admin_url(),false);
check(parse_url($url,PHP_URL_PATH) === '/wp-login.php','Override PMPro late login URL filter');
$url = $h->filter_login_url_to_portal(home_url('/login-2/'),admin_url(),true);
check(str_contains($url,'reauth=1'),'Forced reauthentication preserved');
$GLOBALS['admin'] = false; $_SERVER['REQUEST_URI'] = '/stories/';
check($h->filter_login_url_to_portal(home_url('/login-2/'),'',false) === home_url('/member-profile/#/login'),'Generic member links use canonical page');
$_GET = ['action'=>'lostpassword'];
check(!str_contains($h->filter_login_url_to_portal(home_url('/login-2/'),admin_url(),false),'lostpassword'),'Current reset action must not leak into fresh login link');
$target = admin_url() . 'plugins.php';
check($h->filter_administrator_login_redirect(admin_url(),$target,new WP_User()) === $target,'Admin subpage not replaced with dashboard');
check(str_contains($source,"'filter_login_url_to_portal'], 100, 3"),'Run after PMPro priority 50');
echo "PASS: login aliases, PMPro configured page, admin and member destinations, native POST, external redirects, reset tokens, reauthentication and filter order.\n";
