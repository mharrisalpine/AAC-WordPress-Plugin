<?php
define('ABSPATH', '/tmp/');
class WP_User { public $ID=42, $user_login='member42'; }
class WP_Error { public function __construct(public $code, public $message) {} }
function is_wp_error($v) { return $v instanceof WP_Error; }
function sanitize_email($v) { return $v; }
function sanitize_text_field($v) { return $v; }
function sanitize_key($v) { return $v; }
function sanitize_user($v) { return $v; }
function is_email($v) { return filter_var($v,FILTER_VALIDATE_EMAIL); }
function absint($v) { return abs((int)$v); }
function get_user_by($field,$v) { return $v==='member@example.invalid' ? new WP_User() : false; }
function wp_update_user($v) { $GLOBALS['user_write']=$v; return 42; }
function wp_insert_user($v) { throw new Exception('Profile mode must not create accounts'); }
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
$row=['email'=>'member@example.invalid','address_1'=>'New street','address_2'=>'Apt 2','birthdate'=>'02/03/1980','member_since_date'=>'06/17/1987','role'=>'subscriber','auto_renew'=>'no','discount_type'=>'student','row_type'=>'child','stripe_customer_id'=>'cus_changed'];
function check_profile($ok,$message) { if (!$ok) { throw new Exception($message); } }
$preview=$sync->invoke($m,$row,true); check_profile($preview['action']==='dry_run_profile_updated' && !isset($user_write),'dry run without writes or level');
$result=$sync->invoke($m,$row,false);
check_profile($result['action']==='profile_updated','profile updated');
check_profile(!isset($user_write['role'],$user_write['first_name'],$user_write['last_name'],$user_write['display_name']),'omitted names and role preserved');
check_profile($meta['pmpro_saddress1']==='New street' && $meta['pmpro_saddress2']==='Apt 2','address imported');
check_profile($meta['birthdate']==='1980-02-03' && $meta['aac_member_since_date']==='1987-06-17' && $meta['aac_member_since_year']==='1987','dates normalized');
check_profile($meta['aac_family_account_role']==='parent' && $meta['pmpro_stripe_customerid']==='cus_existing','family and Stripe preserved');
check_profile($meta['aac_account_info']['auto_renew']===true && $meta['aac_account_info']['membership_discount_type']==='military','billing preferences preserved');
$missing=$sync->invoke($m,['email'=>'missing@example.invalid'],true); check_profile(is_wp_error($missing) && $missing->code==='profile_user_missing','missing account rejected');
$r->getProperty('profile_only')->setValue($m,false);
$full=$sync->invoke($m,['email'=>'member@example.invalid'],true); check_profile(is_wp_error($full) && $full->code==='missing_level','full mode still requires level');
echo "PASS: profile-only preview, existing-account requirement, profile dates/address, preservation of omitted names, role, family, Stripe and billing; full-mode level validation.\n";
