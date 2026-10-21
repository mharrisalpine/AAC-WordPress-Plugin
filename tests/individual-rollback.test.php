<?php
define('ABSPATH','/tmp/');
function maybe_unserialize($v){if(is_array($v))return $v;$a=@unserialize($v,['allowed_classes'=>false]);return $a===false?$v:$a;}
$wpdb=(object)['users'=>'wp_users','usermeta'=>'wp_usermeta'];
require __DIR__.'/../wordpress/aac-member-portal/includes/class-aac-member-portal-import-rollback.php';
require __DIR__.'/../wordpress/aac-member-portal/includes/class-aac-member-portal-individual-rollback.php';
function payload($existing=false,$raw=['row_type'=>'','aac_member_id'=>'313']){
 $after=['wp_users'=>['pk'=>'ID','rows'=>[['ID'=>'10','user_email'=>'member@example.invalid']]],'wp_usermeta'=>['pk'=>'umeta_id','rows'=>[['umeta_id'=>'1','user_id'=>'10','meta_key'=>'aac_imported_member_row','meta_value'=>json_encode($raw)]]]];
 $before=$after;$before['wp_users']['rows']=$existing?[['ID'=>'10','user_email'=>'before@example.invalid']]:[];$before['wp_usermeta']['rows']=[];
 return ['ids'=>[10],'tables'=>array_keys($after),'before'=>$before,'after'=>$after];
}
function expect($ok,$why){if(!$ok)throw new Exception($why);}
function excluded($p){return AAC_Member_Portal_Individual_Rollback::payload_exclusion($p)!=='';}
$p=payload();expect(!excluded($p),'blank row_type eligible');
expect(!excluded(payload(false,['aac_member_id'=>'313'])),'absent row_type treated as blank');
expect(!excluded(payload(false,['row_type'=>'','account_type'=>'individual'])),'individual account type is not confused with family');
foreach(['parent','child','individual'] as $type)expect(excluded(payload(false,['row_type'=>$type])),'nonblank original row type excluded');
foreach(['parent','child','adult','dependent'] as $type){
 $q=$p;$q['before']['wp_usermeta']['rows'][]=['meta_key'=>'aac_family_account_role','meta_value'=>$type];expect(excluded($q),'historical family role protected even after blank profile update');
}
foreach(['parent_member_id','parent_email','parent_user_id'] as $key)expect(excluded(payload(false,['row_type'=>'',$key=>'123'])),'parent matcher protected');
$q=$p;$q['before']['wp_usermeta']['rows'][]=['meta_key'=>'wp_capabilities','meta_value'=>serialize(['administrator'=>true])];expect(excluded($q),'historical administrator protected');
$q=$p;$q['ids']=[10,20];expect(excluded($q),'multi-account checkpoints protected');
$q=$p;$q['after']['wp_pmprogroupacct_group_members']=['rows'=>[['group_child_user_id'=>10]]];expect(excluded($q),'group relationship protected');
$q=$p;$q['before']['wp_usermeta']['rows'][]=['meta_key'=>'aac_connected_accounts','meta_value'=>serialize([['child_user_id'=>20]])];expect(excluded($q),'parent of connected child protected');
$q=$p;$q['after']['wp_usermeta']['rows']=[];expect(excluded($q),'unknown original row excluded');
$entry=['status'=>'ready','payload'=>json_encode($p)];
expect(AAC_Member_Portal_Individual_Rollback::check_chain([$entry],10,$p['after'])==='remove','created account action is remove');
$q=payload(true);$entry2=['status'=>'ready','payload'=>json_encode($q)];
expect(AAC_Member_Portal_Individual_Rollback::check_chain([$entry2],10,$q['after'])==='restore','existing account action is restore');
$new=$p;$new['before']=$p['after'];$new['after']['wp_users']['rows'][0]['user_email']='newer@example.invalid';
$chain=[['status'=>'ready','payload'=>json_encode($new)],$entry];
expect(AAC_Member_Portal_Individual_Rollback::check_chain($chain,10,$new['after'])==='remove','multiple batches simulate newest to oldest');
foreach([
 [[$entry],20,$p['after']],
 [[$entry],10,$q['before']],
 [[['status'=>'pending','payload'=>json_encode($p)]],10,$p['after']],
 [[['status'=>'ready','payload'=>json_encode(payload(false,['row_type'=>'child']))]],10,$p['after']],
 [array_reverse($chain),10,$new['after']],
] as $args){$failed=false;try{AAC_Member_Portal_Individual_Rollback::check_chain(...$args);}catch(RuntimeException $e){$failed=true;}expect($failed,'wrong identity, changed data, incomplete, protected or misordered history must fail closed');}
echo "PASS: exact blank-row scope, historical/current family evidence, protected mixed checkpoints, missing provenance, new versus existing accounts and complete reverse checkpoint chains.\n";
