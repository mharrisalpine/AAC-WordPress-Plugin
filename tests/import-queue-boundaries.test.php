<?php
require __DIR__.'/import-profile-mode.test.php';
if (!defined('ARRAY_A')) define('ARRAY_A','ARRAY_A');
function get_option($k,$default=false) { return AAC_Member_Portal_Import_Manager::SCHEMA_VERSION; }
class AAC_Member_Portal_Import_Background { public static $pause=false; static function pause_requested(){return self::$pause;} }
class QueueDB {
 public $prefix='wp_', $last_error='', $writes=0, $fail=false, $rows=[];
 function prepare($sql,...$a){return $sql;}
 function get_results(...$a){return $this->rows;}
 function get_var(...$a){return count($this->rows)-$this->writes;}
 function update(...$a){if($this->fail){return false;} $this->writes++;return 1;}
}
$wpdb=new QueueDB();
$wpdb->rows=[['id'=>1,'raw_row'=>json_encode(['aac_member_id'=>'123','email'=>'member@example.invalid'])],['id'=>2,'raw_row'=>json_encode(['aac_member_id'=>'123','email'=>'member@example.invalid'])]];
$result=$m->sync_staged_rows(true,10,'profile',microtime(true)-1);
check_profile($wpdb->writes===0 && $result['remaining']===2,'expired deadline starts no row');
AAC_Member_Portal_Import_Background::$pause=true;
$result=$m->sync_staged_rows(true,10,'profile',microtime(true)+10);
check_profile($wpdb->writes===0,'pause starts no row');
AAC_Member_Portal_Import_Background::$pause=false;
$result=$m->sync_staged_rows(true,10,'profile',microtime(true)+10);
check_profile($wpdb->writes===2 && $result['validated']===2,'successful rows checkpointed');
$wpdb->writes=0; $wpdb->fail=true; $caught=false;
try{$m->sync_staged_rows(true,10,'profile');}catch(RuntimeException $e){$caught=true;}
check_profile($caught,'queue write failure cannot report success');
$wpdb->fail=false; $wpdb->rows[0]['raw_row']='invalid';
$result=$m->sync_staged_rows(true,10,'profile');
check_profile($result['errors']===1 && $wpdb->writes===1,'first invalid row stops chunk');
echo "PASS: deadline, pause, per-row progress, database failure, stop-on-first-error.\n";
