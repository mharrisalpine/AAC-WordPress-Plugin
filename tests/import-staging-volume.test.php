<?php
require __DIR__ . '/import-profile-mode.test.php';
function get_option($k,$d=false) { return AAC_Member_Portal_Import_Manager::SCHEMA_VERSION; }
class StageDB {
 public $prefix='wp_', $writes=0, $rows=0, $max_rows=0, $fail=false;
 function prepare($sql,$values) { return [$sql,$values]; }
 function query($prepared) {
  if ($this->fail) { return false; }
  [$sql,$values]=$prepared;
  $n=count($values)/16;
  $this->writes++; $this->rows+=$n; $this->max_rows=max($this->max_rows,$n);
  return $n;
 }
}
$wpdb=new StageDB();
$file=tempnam(sys_get_temp_dir(),'aac-volume-');
try {
 $f=fopen($file,'w'); fputcsv($f,['email','membership_id','first_name'], ',', '"', '\\');
 for($i=0;$i<23000;$i++){ fputcsv($f,['volume'.$i.'@example.invalid',2,'Test'], ',', '"', '\\'); } fclose($f);
 $result=$m->stage_csv($file);
 check_profile($result['staged']===23000 && $wpdb->rows===23000,'all rows staged');
 check_profile($wpdb->writes===230 && $wpdb->max_rows===100,'230 bulk writes for 23000 rows');
 $wpdb->fail=true; $failed=$m->stage_csv($file); check_profile(is_wp_error($failed),'database failure reported');
} finally { unlink($file); }
echo "PASS: 23,000 CSV records staged in 230 bounded writes; failed staging reported.\n";
