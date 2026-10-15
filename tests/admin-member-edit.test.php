<?php
// Test production authorization and request construction with the persistence boundary stubbed.
define('ABSPATH', __DIR__);
require __DIR__ . '/../wordpress/aac-member-portal/includes/class-aac-member-portal-member-database.php';
class WP_Error { public function __construct(public $code, public $message, public $data = []) {} }
class WP_REST_Request {
    public $params = [];
    public function __construct($method) {}
    public function set_param($key, $value) { $this->params[$key] = $value; }
}
function absint($value) { return abs((int) $value); }
function current_user_can($cap, $id = null) { return $GLOBALS['permissions'][$cap] ?? false; }
function get_current_user_id() { return 7; }
function get_user_by($field, $id) { return $id === 42 ? (object) ['ID' => 42] : false; }
function check($ok, $label) { if (!$ok) throw new Exception($label); }
$source = file_get_contents(__DIR__ . '/../wordpress/aac-member-portal/includes/class-aac-member-portal-api.php');
$start = strpos($source, '\tpublic function update_profile');
// Match real tabs in the source.
$start = strpos($source, "\tpublic function update_profile(");
$end = strpos($source, "\tprivate function save_profile_for_user(", $start);
check($start !== false && $end !== false, 'Production methods found');
eval('class EditorHarness {
 public $writes = [];
 private function build_profile($id) { return ["account_info" => ["first_name" => "Original", "email" => "member@example.com", "auto_renew" => true, "photo_url" => "keep", "student_university" => "Old", "student_university_id" => "123"]]; }
 private function save_profile_for_user($id, $request) { $this->writes[] = [$id, $request->params]; return end($this->writes); }
' . substr($source, $start, $end - $start) . '}');
$editor = new EditorHarness();
foreach ([[false, false], [true, false], [false, true]] as [$manage, $edit]) {
 $GLOBALS['permissions'] = ['manage_options' => $manage, 'edit_user' => $edit];
 check($editor->admin_update_member(42, ['first_name' => 'New'])->code === 'forbidden', 'Both permissions required');
}
$GLOBALS['permissions'] = ['manage_options' => true, 'edit_user' => true];
check($editor->admin_update_member(99, ['first_name' => 'New'])->code === 'not_found', 'Unknown user rejected');
check($editor->admin_update_member(42, [])->code === 'invalid_input', 'Empty changes rejected');
check($editor->admin_update_member(42, ['email' => ['bad']])->code === 'invalid_input', 'Nested fields rejected');
check(count($editor->writes) === 0, 'Rejected requests never save');
[$id, $params] = $editor->admin_update_member(42, ['first_name' => 'New', 'auto_renew' => false, 'role' => 'administrator', 'student_university' => 'New School']);
$account = $params['account_info'];
check($id === 42 && $account['first_name'] === 'New', 'Selected member updated');
check($account['auto_renew'] === true && !isset($account['role']), 'Unauthorized fields ignored');
check($account['photo_url'] === 'keep' && $account['email'] === 'member@example.com', 'Unedited data preserved');
check($account['student_university_id'] === '', 'Changed school clears obsolete identifier');
check(!isset($params['benefits_info']) && !isset($params['profile_info']), 'Other profile sections untouched');
$request = new WP_REST_Request('PATCH'); $request->set_param('user_id', 42);
[$id] = $editor->update_profile($request);
check($id === 7, 'Member endpoint remains scoped to signed-in user');
echo "PASS admin permissions, target routing, whitelist, preservation, invalid input and member endpoint isolation\n";
