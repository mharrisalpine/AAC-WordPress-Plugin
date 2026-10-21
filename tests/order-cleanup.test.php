<?php
define('ABSPATH','/tmp/'); define('ARRAY_A','ARRAY_A');
function add_action(...$a) {}
require __DIR__.'/../wordpress/aac-member-portal/includes/class-aac-member-portal-order-cleanup.php';
class OrderPreviewDB {
 public $prefix='wp_', $users='wp_users', $last_error='', $queries=[];
 function get_results($sql,$format) {
  $this->queries[]=$sql;
  if (str_contains($sql,'SELECT o.*')) {
   if (!str_contains($sql,'o.user_id > 0 AND NOT EXISTS (SELECT 1 FROM `wp_users` u WHERE u.ID = o.user_id)')) {throw new Exception('Must exclude existing users and unassigned orders');}
   if (str_contains($sql,'gateway') || str_contains($sql,'status =')) {throw new Exception('Must include all gateways and statuses');}
   return [['id'=>12,'user_id'=>99]];
  }
  if (!str_contains($sql,'IN (12)')) {throw new Exception('Related rows must match selected order IDs');}
  return [];
 }
}
$wpdb=new OrderPreviewDB();$obj=new AAC_Member_Portal_Order_Cleanup();
$m=new ReflectionMethod($obj,'collect');$rows=$m->invoke($obj,true);
if (count($rows)!==3 || count($rows['wp_pmpro_membership_orders'])!==1) {throw new Exception('Unexpected selection');}
foreach ($wpdb->queries as $sql) {if(!str_ends_with($sql,'FOR UPDATE')) {throw new Exception('Missing row lock');}}
$wpdb->last_error='error';$caught=false;
try{$m->invoke($obj);}catch(RuntimeException $e){$caught=true;}
if(!$caught){throw new Exception('Read error must stop cleanup');}
echo "PASS: deleted-user predicate, unassigned exclusion, all gateways/statuses, related-row scope, locks and read failure.\n";
