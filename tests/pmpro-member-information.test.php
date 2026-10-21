<?php
define('ABSPATH','/tmp/');
class WP_Error {function __construct(public $message){} function get_error_message(){return $this->message;}}
function is_wp_error($v){return $v instanceof WP_Error;}
function current_user_can(...$args){return $GLOBALS['allowed'];}
function wp_verify_nonce(...$args){return $GLOBALS['nonce_valid'];}
function wp_unslash($v){return $v;}
function sanitize_text_field($v){return strip_tags($v);}
function sanitize_textarea_field($v){return strip_tags($v);}
function esc_url_raw($v){return str_starts_with($v,'javascript:')?'':$v;}
function esc_html($v){return htmlspecialchars((string)$v);}
function pmpro_setMessage($message,$type){$GLOBALS['message']=[$message,$type];}
function wp_update_user($data){$GLOBALS['core_writes'][]=$data;return 42;}
function update_user_meta(...$args){$GLOBALS['meta_writes'][]=$args;}
function get_current_user_id(){return 7;}
function current_time($type){return '2026-10-21 10:00:00';}
function add_query_arg(...$args){return '/wp-admin/admin.php?page=pmpro-member&user_id=42';}
function admin_url($path){return '/wp-admin/'.$path;}
class RedirectComplete extends Exception {}
function wp_safe_redirect($url){throw new RedirectComplete($url);}
function absint($v){return abs((int)$v);}
function get_user_meta($id,$key,$single){return $GLOBALS['parent_ids'][$id]??0;}
function get_user_by($field,$id){return $id===77 ? (object)['ID'=>77,'display_name'=>'Parent Member'] : false;}
function pmpro_getMembershipLevelsForUser($id){return $GLOBALS['parent_levels'];}
function wp_timezone(){return new DateTimeZone('America/Denver');}
function get_option($name){return 'Y-m-d';}
function wp_date($format,$stamp,$zone){return (new DateTimeImmutable('@'.$stamp))->setTimezone($zone)->format($format);}
class PMPro_Member_Edit_Panel {
 protected $title,$submit_text; static function get_user(){return (object)['ID'=>42];}
 function should_show(){return true;}
}
class PMPro_Member_Edit_Panel_User_Info extends PMPro_Member_Edit_Panel {function __construct(){} }
class PMPro_Member_Edit_Panel_Memberships extends PMPro_Member_Edit_Panel {protected function display_panel_contents(){} }
class AAC_Member_Portal_API {
 static function get_instance(){return new self;}
 function admin_update_member($id,$changes,$incomplete){$GLOBALS['api_writes'][]=[$id,$changes,$incomplete];return $GLOBALS['api_error']?new WP_Error('Invalid profile'):true;}
 function flush_profile_cache_for_user($id){$GLOBALS['flushed'][]=$id;}
}
class AAC_Member_Portal_Member_Database {
 static function get_instance(){return new self;}
 function sync_member($id){$GLOBALS['mirrored'][]=$id;}
}
require __DIR__.'/../wordpress/aac-member-portal/includes/class-aac-member-portal-pmpro-member-panels.php';
function assert_member_info($ok,$label){if(!$ok)throw new Exception($label);}
$allowed=true;$nonce_valid=true;$api_error=false;$core_writes=[];$api_writes=[];$meta_writes=[];
$_POST=['pmpro_member_edit_saved_panel_nonce'=>'valid','member'=>['phone'=>'123','aac_email_marketing_opt_in'=>'0','aac_sms_marketing_opt_in'=>'1'],'wordpress_profile'=>['display_name'=>'New Name','nickname'=>'Nick','role'=>'administrator','user_pass'=>'unwanted','user_url'=>'javascript:alert(1)','user_notes'=>'Member note']];
$panel=new AAC_PMPro_Member_Information_Panel();
try{$panel->save();}catch(RedirectComplete $e){}
assert_member_info($api_writes[0]===[42,$_POST['member'],true],'targeted profile save supports incomplete imports and explicit false consent');
assert_member_info($core_writes[0]===['ID'=>42,'display_name'=>'New Name','nickname'=>'Nick','user_url'=>''],'core whitelist excludes role/password and unsafe website');
assert_member_info($mirrored===[42] && $flushed===[42],'profile mirror and cache refreshed');
assert_member_info($meta_writes[0]===[42,'user_notes','Member note'],'PMPro member notes persist as metadata');
$core_writes=[];$api_writes=[];$allowed=false;$panel->save();assert_member_info(!$core_writes&&!$api_writes,'unauthorized rejected');
$allowed=true;$nonce_valid=false;$panel->save();assert_member_info(!$api_writes,'nonce required');
$nonce_valid=true;$_POST['wordpress_profile']['description']=['nested'];$panel->save();assert_member_info(!$api_writes,'nested field rejected before saving');unset($_POST['wordpress_profile']['description']);
$api_error=true;$panel->save();assert_member_info(!$core_writes,'profile validation failure prevents core updates');
$parent_ids=[42=>77];$parent_levels=[(object)['enddate'=>'2027-10-31 23:59:59']];
assert_member_info(AAC_PMPro_Family_Membership_Panel::parent_expiration(42)['date']==='2027-10-31','parent date displayed in site timezone');
$parent_levels=[(object)['enddate'=>'2028-02-29 23:59:59']];
assert_member_info(AAC_PMPro_Family_Membership_Panel::parent_expiration(42)['date']==='2028-02-29','parent renewal reflected without changing child record');
$parent_levels=[(object)['enddate'=>'0000-00-00 00:00:00']];assert_member_info(AAC_PMPro_Family_Membership_Panel::parent_expiration(42)['date']==='No expiration date set','unlimited parent explicit');
$parent_levels=[];assert_member_info(AAC_PMPro_Family_Membership_Panel::parent_expiration(42)['date']==='No active parent membership','inactive parent explicit');
$parent_ids=[];assert_member_info(AAC_PMPro_Family_Membership_Panel::parent_expiration(42)===null,'unlinked account has no inherited date');
echo "PASS: authorized shared profile save, consent, core whitelist, nonce, validation, mirror/cache, and live parent expiration including renewals/unlimited/inactive states.\n";
