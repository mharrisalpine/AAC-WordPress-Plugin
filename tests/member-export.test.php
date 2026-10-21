<?php
// Exercise the actual file/checkpoint worker without WordPress or member writes.
define('ABSPATH', '/nonexistent-wordpress/');
define('DAY_IN_SECONDS', 86400);
class WP_Error { function __construct(public $code,public $message){} function get_error_message(){return $this->message;} }
function is_wp_error($v){return $v instanceof WP_Error;}
$options=[]; $uuid=0; $events=[];
function add_action(...$a){}
function add_option($key,$value,...$rest){if(isset($GLOBALS['options'][$key]))return false;$GLOBALS['options'][$key]=$value;return true;}
function get_option($key,$default=[]){return $GLOBALS['options'][$key]??$default;}
function update_option($key,$value,$autoload=false){$GLOBALS['options'][$key]=$value;return true;}
function delete_option($key){unset($GLOBALS['options'][$key]);}
function wp_cache_delete(...$a){}
function wp_generate_uuid4(){return 'test-job-'.++$GLOBALS['uuid'];}
function wp_schedule_single_event(...$a){$GLOBALS['events'][]=$a;}
function current_user_can($cap){return false;}
function wp_send_json_error($data,$status){throw new RuntimeException('denied '.$status);}
function wp_die($message){throw new RuntimeException($message);}
require __DIR__.'/../wordpress/aac-member-portal/includes/class-aac-member-portal-member-export.php';
class ExportRows {
 public $calls=[]; public $fail=false;
 function build_member_export_row($id,$headers){$this->calls[]=[$id,$headers];if($this->fail)throw new RuntimeException('read failure');return $id===17?[]:['aac_member_id'=>(string)$id,'user_email'=>'member,'.$id.'@example.invalid','name'=>"A\nB"];}
}
function verify($condition,$message){if(!$condition)throw new RuntimeException($message);}
$db=new ExportRows();$export=new AAC_Member_Portal_Member_Export($db);
$headers=['aac_member_id','user_email','name'];
$j=$export->start(42,range(1,623),$headers);
verify(!is_wp_error($j),'start');
verify((fileperms($j['path'])&0777)===0600,'private file permissions');
verify($export->start(42,[999],['name'])['id']===$j['id'],'in-progress export reused');
verify(is_wp_error($export->step(43,$j['id'])),'other owner denied');
verify(is_wp_error($export->step(42,'different')),'other job denied');
$j=$export->step(42,$j['id']);verify($j['next']===250 && $j['rows']===249,'bounded batch, deleted user omitted');
// Simulate termination after writing uncommitted bytes: retry removes the partial tail.
file_put_contents($j['path'],"partial,uncommitted\n",FILE_APPEND);
$lock=fopen($j['path'],'rb');flock($lock,LOCK_EX);
$blocked=$export->step(42,$j['id']);verify($blocked['next']===250,'concurrent worker cannot append');
flock($lock,LOCK_UN);fclose($lock);
$j=$export->step(42,$j['id']);$j=$export->step(42,$j['id']);
verify($j['status']==='ready' && $j['next']===623 && $j['rows']===622,'resumed export completes');
$h=fopen($j['path'],'rb');$rows=[];while(($r=fgetcsv($h,0,',','"',''))!==false)$rows[]=$r;fclose($h);
verify($rows[0]===$headers && count($rows)===623,'one header, expected data row count');
verify($rows[1]===['1','member,1@example.invalid',"A\nB"],'CSV quotes delimiters and newlines');
verify(count(array_unique(array_column(array_slice($rows,1),0)))===622,'no duplicated rows after retry');
verify($db->calls[0][1]===$headers,'selected field snapshot passed to builder');
$before=count($db->calls);$export->step(42,$j['id']);verify(count($db->calls)===$before,'ready file never rebuilt');
try{$export->handle_step();throw new Exception('AJAX allowed');}catch(RuntimeException $e){verify($e->getMessage()==='denied 403','AJAX capability enforced');}
try{$export->download();throw new Exception('download allowed');}catch(RuntimeException $e){verify($e->getMessage()==='Permission denied.','download capability enforced');}
$old=$j;$j=$export->start(42,[1],$headers);verify(!is_file($old['path']),'old completed file removed');
$db->fail=true;$j=$export->step(42,$j['id']);verify($j['status']==='failed' && $j['error']==='read failure','failed batch never shown ready');
$j['expires']=time()-1;update_option('aac_member_export_job_42',$j);
verify(is_wp_error($export->step(42,$j['id'])),'expired export denied');
$export->cleanup(42,'other-job');verify(is_file($j['path']),'cleanup cannot remove newer job');
$export->cleanup(42,$j['id']);verify(!is_file($j['path']) && !$export->job(42),'expired file and state removed');
echo "PASS: bounded generation, resume without duplicates, CSV quoting, ownership, capability, private storage, immutable fields, failure and cleanup.\n";
