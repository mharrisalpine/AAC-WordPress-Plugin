<?php
require __DIR__ . '/import-profile-mode.test.php';
function delete_user_meta($id,$key){unset($GLOBALS['meta'][$key]);}
function get_users($args){return $GLOBALS['number_owners'] ?? [];}
class RepairDB {
 public $users='wp_users',$prefix='wp_',$last_error='', $ids=[42],$conflict=false,$writes=[];
 function prepare($sql,...$args){return $sql;}
 function get_col($sql){return $this->ids;}
 function get_var($sql){
  if(str_contains($sql,'GET_LOCK') || str_contains($sql,'RELEASE_LOCK')){return 1;}
  if(str_contains($sql,'SELECT raw_profile')){return '{"profile_info":{"member_id":"134214"},"account_info":{"auto_renew":true}}';}
  return $this->conflict ? 1 : null;
 }
 function update($table,$data,$where){$this->writes[]=[$table,$data,$where];return 1;}
}
$wpdb=new RepairDB();$number_owners=[];
$r->getProperty('number_repair')->setValue($m,true);
$meta=['aac_member_id'=>'134214','aac_profile_info'=>['member_id'=>'134214','tier'=>'Partner'],'aac_member_number_pending'=>'1','aac_account_info'=>['auto_renew'=>true]];
$row=['email'=>'member@example.invalid','aac_member_id'=>'18537'];
$preview=$sync->invoke($m,$row,true);
check_profile($preview['action']==='number:134214->18537' && $meta['aac_member_id']==='134214','dry run previews without mutation');
$number_owners=[99];$blocked=$sync->invoke($m,$row,false);check_profile(is_wp_error($blocked) && !$wpdb->writes && $meta['aac_member_id']==='134214','foreign owner rejected');
$number_owners=[];$wpdb->ids=[42,99];check_profile(is_wp_error($sync->invoke($m,$row,true)),'duplicate email rejected');
$wpdb->ids=[];check_profile(is_wp_error($sync->invoke($m,$row,true)),'missing email rejected');
$wpdb->ids=[42];$wpdb->conflict=true;check_profile(is_wp_error($sync->invoke($m,$row,true)),'conflicting CSV assignments rejected');
$wpdb->conflict=false;$result=$sync->invoke($m,$row,false);
check_profile($result['user_id']===42 && $meta['aac_member_id']==='18537' && $meta['aac_profile_info']['member_id']==='18537','email-matched number repaired');
check_profile(!isset($meta['aac_member_number_pending']) && $meta['aac_profile_info']['tier']==='Partner' && $meta['aac_account_info']['auto_renew']===true,'allocator flag cleared; membership and billing preserved');
check_profile(count($wpdb->writes)===1 && $wpdb->writes[0][1]['member_id']==='18537','identity-only mirror write');
$r->getProperty('number_repair')->setValue($m,false);
check_profile(is_wp_error($sync->invoke($m,['email'=>'member@example.invalid','user_id'=>'18537'],true)),'ambiguous user_id header blocked');
echo "PASS: email-only repair, dry-run preview, conflicts, duplicate/missing accounts, exact number, allocator protection and identity-only mirror.\n";
