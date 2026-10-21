<?php
 define('ABSPATH', '/tmp/'); define('ARRAY_A', 'ARRAY_A');
 function add_action(...$a) {}
 require __DIR__.'/../wordpress/aac-member-portal/includes/class-aac-member-portal-subscription-cleanup.php';
 function verify_cleanup($v,$m) { if (!$v) { throw new Exception($m); } }
 $target=['gateway'=>'stripe','gateway_environment'=>'sandbox','status'=>'cancelled'];
 verify_cleanup(AAC_Member_Portal_Subscription_Cleanup::matches($target),'target matched');
 foreach ([['gateway_environment'=>'live'],['status'=>'active'],['gateway'=>'paypal'],['gateway_environment'=>''],['status'=>'pending']] as $change) {
  verify_cleanup(!AAC_Member_Portal_Subscription_Cleanup::matches(array_merge($target,$change)),'non-target excluded');
 }
 class CleanupPreviewDB {
  public $prefix='wp_', $last_error='', $queries=[];
  function get_results($sql,$format) {
   $this->queries[]=$sql;
   if (strpos($sql,'`wp_pmpro_subscriptions`')!==false) {
    verify_cleanup(strpos($sql,"gateway = 'stripe' AND gateway_environment = 'sandbox' AND status = 'cancelled'")!==false,'exact source filter');
    return [['id'=>25,'user_id'=>1]+$GLOBALS['target']];
   }
   if (strpos($sql,'`wp_pmpro_subscriptionmeta`')!==false) {
    verify_cleanup(strpos($sql,'pmpro_subscription_id IN (25)')!==false,'metadata scoped to target IDs'); return [];
   }
   return [
    ['id'=>1,'source_record_id'=>25,'raw_record'=>'{}'],
    ['id'=>2,'source_record_id'=>90,'raw_record'=>json_encode($GLOBALS['target'])],
    ['id'=>3,'source_record_id'=>91,'raw_record'=>json_encode(array_merge($GLOBALS['target'],['gateway_environment'=>'live']))],
    ['id'=>4,'source_record_id'=>92,'raw_record'=>'invalid']
   ];
  }
 }
 $wpdb=new CleanupPreviewDB(); $cleanup=new AAC_Member_Portal_Subscription_Cleanup();
 $collect=new ReflectionMethod($cleanup,'collect'); $sets=$collect->invoke($cleanup,true);
 verify_cleanup(array_column($sets['wp_aac_member_db_subscriptions'],'id')===[1,2],'linked and orphan test copies included; live and malformed excluded');
 foreach($wpdb->queries as $sql) { verify_cleanup(str_ends_with($sql,'FOR UPDATE'),'mutation snapshot locks rows'); }
 $wpdb->last_error='database unavailable'; $failed=false;
 try {$collect->invoke($cleanup);}catch(RuntimeException $e){$failed=true;}
 verify_cleanup($failed,'read failures abort');
 echo "PASS: exact filter, metadata scope, linked/orphan copies, live protection, row locking, read failure.\n";
