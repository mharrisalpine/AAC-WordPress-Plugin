<?php
define('ABSPATH', '/tmp/');
define('ARRAY_A', 'ARRAY_A');
class WP_Error {
 public function __construct(public $code, public $message) {}
 function get_error_code(){return $this->code;}
 function get_error_message(){return $this->message;}
}
function is_wp_error($v){return $v instanceof WP_Error;}
function sanitize_email($v){return $v;}
function absint($v){return abs((int)$v);}
function sanitize_text_field($v){return $v;}
function is_email($v){return filter_var($v,FILTER_VALIDATE_EMAIL);}
function get_option($key){return '1.0.0';}
function current_time($v){return '2026-10-20 00:00:00';}
function get_user_meta(...$args){return '999';}
function get_users($args){return $args['meta_value']==='300' ? [99] : [];}
class RepairQueueDB {
 public $prefix='wp_', $users='wp_users', $last_error='', $rows=[], $updates=[], $sql=[], $lookupFailure=false;
 function prepare($sql,...$args){return $sql.' /* '.json_encode($args).' */';}
 function get_results($sql,$type){return $this->rows;}
 function get_col($sql){
  $this->last_error=$this->lookupFailure ? 'database unavailable' : '';
  return str_contains($sql,'missing@example.com') ? [] : [42];
 }
 function get_var($sql){
  $this->last_error='';
  $this->sql[]=$sql;
  if(str_contains($sql,'COUNT(*)')) return count($this->rows)-count($this->updates);
  return str_contains($sql,'400') ? 1 : null;
 }
 function update($table,$data,$where,...$args){$this->updates[$where['id']]=$data;return 1;}
}
require __DIR__.'/../wordpress/aac-member-portal/includes/class-aac-member-portal-import-manager.php';
$manager=(new ReflectionClass(AAC_Member_Portal_Import_Manager::class))->newInstanceWithoutConstructor();
function verify_repair($ok,$message){if(!$ok) throw new RuntimeException($message);}
$wpdb=new RepairQueueDB();
$cases=[
 ['email'=>'missing@example.com','aac_member_id'=>'100'],
 ['email'=>'valid@example.com','aac_member_id'=>'200'],
 ['email'=>'valid@example.com','aac_member_id'=>'bad'],
 ['email'=>'invalid','aac_member_id'=>'201'],
 ['email'=>'valid@example.com','aac_member_id'=>'300'],
 ['email'=>'valid@example.com','aac_member_id'=>'400'],
 ['email'=>'valid@example.com','aac_member_id'=>'500'],
];
foreach($cases as $i=>$row) $wpdb->rows[]=['id'=>$i+1,'raw_row'=>json_encode($row)];
$result=$manager->sync_staged_rows(true,20,'member_number');
verify_repair($result['errors']===5 && $result['validated']===2 && !$result['stopped_on_error'] && $result['remaining']===0,'bad rows skipped and later valid repairs finish');
verify_repair($wpdb->updates[1]['status']==='error' && $wpdb->updates[7]['status']==='dry_run','errors retained for CSV and final valid row processed');
verify_repair((bool)array_filter($wpdb->sql,fn($sql)=>str_contains($sql,"('staged','dry_run','error')")),'failed assignments remain in conflict checks');
$wpdb=new RepairQueueDB(); $wpdb->lookupFailure=true;
$wpdb->rows=[['id'=>1,'raw_row'=>json_encode($cases[1])],['id'=>2,'raw_row'=>json_encode($cases[6])]];
$result=$manager->sync_staged_rows(true,20,'member_number');
verify_repair($result['stopped_on_error'] && $result['validated']===0 && count($wpdb->updates)===1,'database failure stops instead of being silently skipped');
echo "PASS: invalid, missing and conflicting repairs logged; valid rows finish; failed assignments stay protected; database failures stop.\n";
