<?php
define('ABSPATH','/tmp/');
function absint($v){return abs((int)$v);}
function get_user_by($field,$id){return $id===3143 ? (object)['ID'=>3143] : false;}
function get_user_meta($id,$key,$single){return $GLOBALS['meta'][$key] ?? '';}
function add_user_meta($id,$key,$value,$unique){if(array_key_exists($key,$GLOBALS['meta'])) return false; $GLOBALS['meta'][$key]=$value; return true;}
function update_user_meta($id,$key,$value,$previous=null){if($previous!==null && ($GLOBALS['meta'][$key]??null)!==$previous) return false; $GLOBALS['meta'][$key]=$value;return true;}
function wp_generate_uuid4(){return 'slot-'.++$GLOBALS['sequence'];}
function wp_generate_password(...$args){return 'invite-'.++$GLOBALS['sequence'];}
function sanitize_text_field($v){return $v;}
function admin_url($v){return '/wp-admin/'.$v;}
function add_query_arg(...$args){return '/manage-group/';}
function pmpro_changeMembershipLevel(...$args){throw new Exception('Membership changes forbidden');}
function pmpro_getAllLevels(...$args){return [(object)['id'=>3,'name'=>'Partner'],(object)['id'=>7,'name'=>'Partner Dependent']];}
function pmpro_getLevel($id){return (object)['billing_amount'=>65];}
class PMProGroupAcct_Group_Member {}
class PMProGroupAcct_Group {
 public $id=1,$group_total_seats=2,$group_checkout_code='group';
 static function get_group_by_parent_user_id_and_parent_level_id(...$args){return $GLOBALS['group'];}
 function get_active_members($all){return $GLOBALS['active_children'];}
}
require __DIR__.'/../wordpress/aac-member-portal/includes/class-aac-member-portal-group-accounts.php';
function expect_slots($ok,$message){if(!$ok)throw new Exception($message);}
function blocked_slots($message){try{AAC_Member_Portal_Group_Accounts::restore_empty_dependent_slots(3143,2);}catch(RuntimeException $e){return;}throw new Exception($message);}
$group=new PMProGroupAcct_Group(); $active_children=0;$sequence=0;
$meta=['aac_partner_family_config'=>['mode'=>'family','additional_adult'=>false,'dependent_count'=>2],'billing'=>'untouched'];
expect_slots(AAC_Member_Portal_Group_Accounts::restore_empty_dependent_slots(3143,2)===2,'two empty forms restored');
$slots=$meta['aac_connected_accounts'];
expect_slots(count($slots)===2 && $slots[0]['id']!==$slots[1]['id'] && $slots[0]['invite_code']!==$slots[1]['invite_code'],'unique independent slots');
expect_slots($slots[0]['status']==='pending' && $slots[0]['child_user_id']===0 && $slots[0]['price']===65.0,'pending dependent without creating a child');
expect_slots($meta['billing']==='untouched' && $group->group_total_seats===2 && $active_children===0,'billing and group data unchanged');
$before=$meta; blocked_slots('repeat submission should not overwrite'); expect_slots($meta===$before,'existing slots preserved');
unset($meta['aac_connected_accounts']);$active_children=1;blocked_slots('active children protected');
$active_children=0;$group->group_total_seats=1;blocked_slots('capacity must match');
$group->group_total_seats=2;$meta['aac_linked_parent_user_id']=90;blocked_slots('linked children cannot repair parent seats');unset($meta['aac_linked_parent_user_id']);
$meta['aac_partner_family_config']['additional_adult']=true;blocked_slots('adult entitlement is not converted to dependent');
echo "PASS: two missing dependent slots restored; repeat, linked-child, occupied-group, capacity and entitlement guards; no membership or billing changes.\n";
