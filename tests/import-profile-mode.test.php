<?php
define('ABSPATH', '/tmp/');
class WP_User { public $ID=42, $user_login='member42', $user_email='member@example.invalid', $roles=['subscriber']; }
class WP_Error { public function __construct(public $code, public $message) {} public function get_error_code(){return $this->code;} public function get_error_message(){return $this->message;} }
function is_wp_error($v) { return $v instanceof WP_Error; }
function sanitize_email($v) { return $v; }
function sanitize_text_field($v) { return $v; }
function sanitize_key($v) { return $v; }
function sanitize_user($v) { return $v; }
function is_email($v) { return filter_var($v,FILTER_VALIDATE_EMAIL); }
function absint($v) { return abs((int)$v); }
function get_user_by($field,$v) { if ($v==='admin@example.invalid' || ($field==='id' && (int)$v===99)) { $u=new WP_User(); $u->ID=99; $u->roles=['administrator']; return $u; } return $v==='member@example.invalid' ? new WP_User() : false; }
function get_users($args) {
 if ($args['meta_value']==='123') return [new WP_User()];
 if ($args['meta_value']==='999') return [new WP_User(), new WP_User()];
 if ($args['meta_value']==='456') return [get_user_by('email','admin@example.invalid')];
 return [];
}
function delete_user_meta($id,$key) { unset($GLOBALS['meta'][$key]); }
function wp_update_user($v) { $GLOBALS['user_write']=$v; return 42; }
function wp_insert_user($v) { if (!empty($GLOBALS['allow_upsert_creation'])) { $GLOBALS['new_user_write']=$v; return 84; } throw new Exception('Profile mode must not create accounts'); }
function get_user_meta($id,$key,$single=true) { return $GLOBALS['meta'][$key] ?? ''; }
function update_user_meta($id,$key,$v) { $GLOBALS['meta'][$key]=$v; }
function wp_json_encode($v) { return json_encode($v); }
function current_time($v) { return '2026-10-17 00:00:00'; }
function pmpro_changeMembershipLevel(...$args) { throw new Exception('Profile mode must not modify memberships'); }
require __DIR__.'/../wordpress/aac-member-portal/includes/class-aac-member-portal-import-manager.php';
$r=new ReflectionClass(AAC_Member_Portal_Import_Manager::class); $m=$r->newInstanceWithoutConstructor();
$r->getProperty('profile_only')->setValue($m,true);
$sync=$r->getMethod('sync_single_row');
$meta=['aac_account_info'=>['auto_renew'=>true,'membership_discount_type'=>'military'], 'aac_family_account_role'=>'parent','pmpro_stripe_customerid'=>'cus_existing'];
$row=['aac_member_id'=>'123','email'=>'member@example.invalid','address_1'=>'New street','address_2'=>'Apt 2','birthdate'=>'02/03/1980','member_since_date'=>'06/17/1987','role'=>'subscriber','auto_renew'=>'no','discount_type'=>'student','row_type'=>'child','stripe_customer_id'=>'cus_changed'];
function check_profile($ok,$message) { if (!$ok) { throw new Exception($message); } }
$preview=$sync->invoke($m,$row,true); check_profile($preview['action']==='dry_run_profile_updated' && !isset($user_write),'dry run without writes or level');
$result=$sync->invoke($m,$row,false);
check_profile($result['action']==='profile_updated','profile updated');
check_profile(!isset($user_write['role'],$user_write['first_name'],$user_write['last_name'],$user_write['display_name']),'omitted names and role preserved');
check_profile($meta['pmpro_saddress1']==='New street' && $meta['pmpro_saddress2']==='Apt 2','address imported');
check_profile($meta['birthdate']==='1980-02-03' && $meta['aac_member_since_date']==='1987-06-17' && $meta['aac_member_since_year']==='1987','dates normalized');
check_profile($meta['aac_family_account_role']==='parent' && $meta['pmpro_stripe_customerid']==='cus_existing','family and Stripe preserved');
check_profile($meta['aac_account_info']['auto_renew']===true && $meta['aac_account_info']['membership_discount_type']==='military','billing preferences preserved');
$missing=$sync->invoke($m,['email'=>'missing@example.invalid'],true); check_profile(is_wp_error($missing) && $missing->code==='profile_member_id_required','missing account rejected');
$r->getProperty('profile_only')->setValue($m,false);
$full=$sync->invoke($m,['email'=>'member@example.invalid'],true); check_profile(is_wp_error($full) && $full->code==='missing_level','full mode still requires level');
echo "PASS: profile-only preview, existing-account requirement, profile dates/address, preservation of omitted names, role, family, Stripe and billing; full-mode level validation.\n";

$profile_mode = $r->getProperty('profile_only');
$profile_mode->setValue($m, false);
$write_user = $r->getMethod('create_or_update_user');
foreach ([[], ['role'=>'subscriber'], ['role'=>'administrator']] as $role_row) {
 $write_user->invoke($m, $role_row, new WP_User(10), 'existing', 'member@example.invalid');
 check_profile(!isset($user_write['role']), 'full import preserves existing access roles even when CSV supplies a role');
}
echo "PASS: full member imports preserve existing WordPress roles.\n";

$administrator = new WP_User();
$administrator->roles = ['administrator'];
foreach ([false, true] as $profile_only) {
 $profile_mode->setValue($m, $profile_only);
 foreach ([[], ['role'=>'subscriber'], ['role'=>'author', 'user_status'=>'1', 'status'=>'inactive']] as $csv) {
  unset($GLOBALS['user_write']);
  $written = $write_user->invoke($m, array_merge($csv,['first_name'=>'Updated Admin']), $administrator, 'admin', 'admin@example.invalid');
  check_profile(!is_wp_error($written) && $user_write['first_name']==='Updated Admin' && !isset($user_write['role']), 'administrator profile imports while WordPress role is preserved');
 }
}
echo "PASS: administrator protection in full and profile imports with omitted or explicit roles/status.\n";


$profile_mode->setValue($m,true);
$meta=['aac_account_info'=>[]]; unset($GLOBALS['user_write']);
$updated=$sync->invoke($m,['aac_member_id'=>'456','email'=>'admin@example.invalid','first_name'=>'Updated Admin','address_1'=>'Imported Address'],false);
check_profile($updated['action']==='profile_updated', 'administrator not skipped during profile imports');
check_profile($meta['pmpro_saddress1']==='Imported Address' && $user_write['first_name']==='Updated Admin', 'administrator profile and metadata updated');
check_profile(!isset($user_write['role']), 'administrator role omitted from update');
echo "PASS: administrator profile imports normally and WordPress role is preserved.\n";

// Member number is the sole identity key; omitted emails retain the saved address.
$meta=['aac_member_id'=>'123','aac_account_info'=>[]];
$updated=$sync->invoke($m,['aac_member_id'=>'123','address_1'=>'ID Only Address'],false);
check_profile($updated['action']==='profile_updated' && $meta['pmpro_saddress1']==='ID Only Address', 'ID-only update saves profile');
check_profile($user_write['user_email']==='member@example.invalid', 'omitted email preserved');
foreach (['999','unknown'] as $id) {
 $before=$meta; $result=$sync->invoke($m,['aac_member_id'=>$id,'email'=>'member@example.invalid'],false);
 check_profile(is_wp_error($result) && $result->code==='profile_member_id_match' && $meta===$before, 'duplicate or unmatched ID never falls back to email');
}
$r->getProperty('profile_only')->setValue($m,false);
$result=$sync->invoke($m,['aac_member_id'=>'123'],true);
check_profile(is_wp_error($result) && $result->code==='invalid_email', 'full imports still require email');
echo "PASS: ID-only profile updates, saved email preservation, no email fallback, duplicate rejection and full-import email requirement.\n";

// A bad row must not prevent a later valid row from being validated.
if (!function_exists('get_option')) { function get_option($key) { return '1.0.0'; } }
if (!defined('ARRAY_A')) define('ARRAY_A','ARRAY_A');
class ImportQueueStub {
 public $prefix='wp_', $last_error='', $updates=[], $rows=[];
 function prepare($sql,...$args){return $sql;}
 function get_results($sql,$format){return $this->rows;}
 function update($table,$data,$where,...$rest){$this->updates[$where['id']]=$data;return 1;}
 function get_var($sql){return count($this->rows)-count($this->updates);}
}
$wpdb=new ImportQueueStub();
$wpdb->rows=[
 ['id'=>1,'raw_row'=>json_encode(['aac_member_id'=>'unknown'])],
 ['id'=>2,'raw_row'=>json_encode(['aac_member_id'=>'123'])],
 ['id'=>3,'raw_row'=>json_encode(['aac_member_id'=>'999'])],
];
$stats=$m->sync_staged_rows(true,10,'profile');
check_profile($stats['errors']===2 && $stats['validated']===1 && !$stats['stopped_on_error'] && $stats['remaining']===0,'profile validation errors skipped while valid row completes');
check_profile($wpdb->updates[1]['status']==='error' && $wpdb->updates[2]['status']==='dry_run' && $wpdb->updates[3]['status']==='error','failed rows retained for error CSV');
$wpdb->updates=[]; $wpdb->rows=[['id'=>1,'raw_row'=>'invalid json'],['id'=>2,'raw_row'=>json_encode(['aac_member_id'=>'123'])]];
$stats=$m->sync_staged_rows(true,10,'profile');
check_profile($stats['stopped_on_error'] && $stats['validated']===0 && $stats['remaining']===1,'queue corruption still stops processing');
echo "PASS: invalid profile rows logged and skipped, valid rows finish, corrupt queue still stops.\n";
