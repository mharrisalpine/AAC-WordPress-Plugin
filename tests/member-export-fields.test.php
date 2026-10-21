<?php
$s=file_get_contents(__DIR__.'/../wordpress/aac-member-portal/includes/class-aac-member-portal-member-database.php');
$a=strpos($s,'public function build_member_export_row(');$b=strpos($s,'private function get_user_meta_export_values(', $a);
class WP_User {public $user_login='member', $user_email='member@example.invalid', $user_registered='2000-01-01', $display_name='Example Member', $roles=['subscriber'];}
function get_user_by($field,$id){return new WP_User();}
function get_user_meta($id,$key,$single=true){return $key==='aac_member_id'?'313':'';}
function absint($v){return abs((int)$v);}
class AAC_Member_Portal_Group_Accounts {
 static $calls=0;
 static function is_available(){return true;}
 static function get_group_summary_for_parent($id){self::$calls++;return ['id'=>10,'checkout_code'=>'group','total_seats'=>3,'active_members'=>2];}
 static function get_group_summary_for_user($id){self::$calls++;return self::get_group_summary_for_parent($id);}
}
class AAC_Member_Portal_Publication_Preferences {static function get_timestamps($id){return ['aaj_preference_created_at'=>'2000-01-01'];}}
$helpers= <<<'CODE'
public $lookups=[];
function get_profile_row($id){$this->lookups[]='profile';return ['member_id'=>'313','parent_user_id'=>9,'email_opt_in'=>1,'sms_opt_in'=>0,'membership_level'=>'Partner','membership_status'=>'Active','mirrored_at'=>'2026-10-20'];}
function decode_profile_row($row){$this->lookups[]='decode_profile';return ['account_info'=>['first_name'=>'Example','last_name'=>'Member','size'=>'Small'], 'profile_info'=>['member_id'=>'313','level_id'=>3], 'family_membership'=>[], 'connected_accounts'=>[], 'linked_parent_account'=>[], 'raw'=>['account_info'=>['first_name'=>'Example']]];}
function get_latest_pmpro_row($id,$table){$this->lookups[]=$table;return ['id'=>44,'membership_id'=>3,'startdate'=>'2000-01-01','enddate'=>'2099-12-31','modified'=>'2026-01-01','status'=>'active','billing_amount'=>'125.00'];}
function get_user_meta_export_values($id){$this->lookups[]='meta';return ['aac_member_id'=>'313','aac_linked_parent_user_id'=>9,'t_shirt'=>'Small'];}
function get_pmpro_group_account_rows($id){$this->lookups[]='group_rows';return [['id'=>10]];}
function get_discount_code_for_order($row){return 'STUDENT';}
function get_member_export_headers(){return $GLOBALS['export_headers']??[];}
function first_present_value($values){foreach($values as $v){if($v!==''&&$v!==null)return $v;}return '';}
function get_member_last_login_date($id){return '';}
function get_member_login_count($id){return 0;}
function get_pmpro_level_name($id){return 'Partner';}
function get_all_pmpro_rows($id,$table){$this->lookups[]='history_'.$table;return [['id'=>44]];}
function encode_export_json($data){return json_encode($data);}
CODE;
eval('class ExportFieldTest {'.substr($s,$a,$b-$a).$helpers.'}');
$db=new ExportFieldTest();$all=$db->build_member_export_row(1);$GLOBALS['export_headers']=array_keys($all);
foreach($all as $field=>$value){$one=$db->build_member_export_row(1,[$field]);if(array_keys($one)!==[$field] || $one[$field]!==$value)throw new Exception('Selected-field mismatch: '.$field);}
$db->lookups=[];AAC_Member_Portal_Group_Accounts::$calls=0;
$db->build_member_export_row(1,['aac_member_id','first_name','last_name','user_email','t_shirt_size','aaj_preference']);
if(array_diff($db->lookups,['profile','decode_profile','meta']) || AAC_Member_Portal_Group_Accounts::$calls)throw new Exception('Basic export triggered financial/history/group queries');
echo 'PASS: '.count($all)." selected fields match full export; basic exports skip financial, group and history queries.\n";

$db->lookups=[];
$row=$db->build_member_export_row(1,['user_email','display_name']);
if(array_keys($row)!==['user_email','display_name'] || $db->lookups) throw new Exception('Core account export loaded unrelated profile data');
$db->lookups=[];
$db->build_member_export_row(1,['latest_order_id','latest_order_status']);
if($db->lookups!==['pmpro_membership_orders'])throw new Exception('Related columns must share one query and load no other source');
$db->lookups=[];
$row=$db->build_member_export_row(1,['not_a_real_field']);
if($row || $db->lookups)throw new Exception('Unknown fields must not build data');
echo "PASS: selected keys only, account-only export skips mirror/meta entirely, shared sources fetched once, unknown fields ignored.\n";
