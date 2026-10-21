<?php
require __DIR__.'/import-profile-mode.test.php';
function username_exists($v){return false;}
function wp_generate_password(...$args){return 'test-generated-password';}
class UpsertDB extends ImportQueueStub {
 public $users='wp_users', $email_ids=[42];
 function get_col($sql){return $this->email_ids;}
}
$wpdb=new UpsertDB();
$r->getProperty('profile_only')->setValue($m,false);
$r->getProperty('profile_upsert')->setValue($m,true);
$meta=['aac_member_id'=>'123','aac_family_account_role'=>'child','aac_linked_parent_user_id'=>77,'aac_connected_accounts'=>[['child_user_id'=>81]],'pmpro_stripe_customerid'=>'cus_existing','aac_account_info'=>['auto_renew'=>true,'membership_discount_type'=>'military']];
$row=['aac_member_id'=>'123','email'=>'member@example.invalid','address_1'=>'Updated street','t_shirt_size'=>'Large','row_type'=>'child','membership_id'=>9,'membership_status'=>'inactive','expiration_date'=>'2000-01-01','parent_member_id'=>'different','role'=>'subscriber','auto_renew'=>'no','stripe_customer_id'=>'cus_changed'];
$result=$sync->invoke($m,$row,false);
check_profile($result['action']==='profile_updated','existing member receives profile-only update');
check_profile($meta['pmpro_saddress1']==='Updated street' && $meta['tshirt_size']==='Large','profile data updated');
check_profile($meta['aac_family_account_role']==='child' && $meta['aac_linked_parent_user_id']===77 && $meta['aac_connected_accounts']===[['child_user_id'=>81]] && !isset($meta['aac_linked_parent_member_id']),'existing family connections unchanged despite supplied replacement');
check_profile($meta['pmpro_stripe_customerid']==='cus_existing' && $meta['aac_account_info']['auto_renew']===true && $meta['aac_account_info']['membership_discount_type']==='military','billing and discounts preserved');
check_profile(!$r->getProperty('profile_only')->getValue($m),'per-row mode restored');
unset($row['parent_member_id'],$row['membership_id']);
check_profile($sync->invoke($m,$row,true)['action']==='dry_run_profile_updated','existing child requires neither level nor repeated parent link');
$wpdb->email_ids=[99];
$emailOnly=['aac_member_id'=>'unmatched','email'=>'admin@example.invalid','address_1'=>'Admin street'];
check_profile($sync->invoke($m,$emailOnly,true)['action']==='dry_run_profile_updated','existing account can match by unique email');
$wpdb->email_ids=[42,99];
check_profile($sync->invoke($m,$emailOnly,true)->code==='upsert_identity_ambiguous','duplicate emails blocked');
$wpdb->email_ids=[99];
check_profile($sync->invoke($m,['aac_member_id'=>'123','email'=>'admin@example.invalid'],true)->code==='identity_conflict','different ID and email owners blocked');
$wpdb->email_ids=[];
$new=['aac_member_id'=>'new','email'=>'new@example.invalid','first_name'=>'New','last_name'=>'Member','membership_id'=>2];
check_profile($sync->invoke($m,$new,true)['action']==='dry_run_created','missing account previewed as creation');
unset($new['membership_id']);
check_profile($sync->invoke($m,$new,true)['action']==='dry_run_created','new account does not require a membership level');
$new['row_type']='child'; $new['membership_id']=3;
check_profile($sync->invoke($m,$new,true)['action']==='dry_run_created','family fields are ignored instead of assigning a family relationship');
$meta=[]; $allow_upsert_creation=true;
$new['role']='administrator'; $new['parent_member_id']='123'; $new['stripe_customer_id']='cus_ignored';
$new['membership_status']='active'; $new['expiration_date']='2029-01-01';
$result=$sync->invoke($m,$new,false);
check_profile($result['user_id']===84 && $result['action']==='created','missing account created with no PMPro membership call');
check_profile($new_user_write['role']==='subscriber' && $meta['aac_member_id']==='new','new subscriber and exact imported member number');
check_profile(!isset($meta['aac_family_account_role'],$meta['aac_linked_parent_member_id'],$meta['pmpro_stripe_customerid']),'new account receives no family or billing assignments');
check_profile(!$r->getProperty('profile_only')->getValue($m),'mode restored after new-account creation');
unset($allow_upsert_creation);
$new['email']='';
check_profile($sync->invoke($m,$new,true)->code==='invalid_email','new account cannot be created without email');
$new['email']='new@example.invalid';
$wpdb->last_error='database unavailable';
check_profile($sync->invoke($m,$new,true)->code==='upsert_lookup_failed','lookup failure never falls through to account creation');
echo "PASS: existing family/profile and billing preservation, email matching, ambiguity rejection, new-member requirements, mode reset and database failure protection.\n";
