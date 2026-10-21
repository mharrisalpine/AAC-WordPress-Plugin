<?php
// Behavior tests: bounded multi-batch continuation, fallback, pause, failure and locks.
define('ABSPATH', '/tmp/');
$options = []; $events = []; $dispatches = []; $filters = [];
function wp_convert_hr_to_bytes($v) { return -1; }
function add_action(...$a) {}
function add_filter($name, $cb, ...$a) { $GLOBALS['filters'][$name] = $cb; }
function wp_cache_delete(...$a) {}
function get_option($k, $d = false) { return $GLOBALS['options'][$k] ?? $d; }
function update_option($k, $v, ...$a) { $GLOBALS['options'][$k] = $v; return true; }
function add_option($k, $v, ...$a) { if (isset($GLOBALS['options'][$k])) { return false; } return update_option($k, $v); }
function delete_option($k) { unset($GLOBALS['options'][$k]); }
function wp_next_scheduled($hook) { return $GLOBALS['events'][$hook] ?? false; }
function wp_schedule_single_event($time, $hook, ...$a) { $GLOBALS['events'][$hook] = $time; return true; }
function wp_clear_scheduled_hook($hook) { unset($GLOBALS['events'][$hook]); }
function admin_url($path) { return 'https://example.invalid/wp-admin/' . $path; }
function wp_remote_post($url, $args) { $GLOBALS['dispatches'][] = [$url, $args]; return $GLOBALS['remote_error'] ?? []; }
function sanitize_text_field($s) { return $s; }
function wp_unslash($s) { return $s; }
function wp_die($s = '', ...$a) { throw new RuntimeException($s); }
class WP_Error { function get_error_message() { return 'test error'; } }
function is_wp_error($v) { return $v instanceof WP_Error; }
require __DIR__ . '/../wordpress/aac-member-portal/includes/class-aac-member-portal-import-background.php';
class TestManager {
 public $remaining = 106, $calls = 0, $fail = false, $pause = false, $mode, $rowErrors = 0, $fatal = false;
 function sync_staged_rows($dry, $limit, $mode, $deadline) {
  $this->calls++; $this->mode = $mode;
  if ($this->fail) { return new WP_Error(); }
  $n = min($limit, $this->remaining); $this->remaining -= $n;
  if ($this->pause) { update_option(AAC_Member_Portal_Import_Background::PAUSE, true); }
  return ['synced' => $dry ? 0 : $n, 'validated' => $dry ? $n : 0, 'errors' => $this->rowErrors, 'stopped_on_error' => $this->fatal, 'family_links' => 0, 'remaining' => $this->remaining];
 }
}
function fixture_worker() {
 $GLOBALS['options'] = []; $GLOBALS['events'] = []; $GLOBALS['dispatches'] = []; $GLOBALS['remote_error'] = null;
 update_option(AAC_Member_Portal_Import_Background::STATE, ['status'=>'running','processed'=>0,'errors'=>0,'mode'=>'profile','token'=>'secret','log'=>[]]);
 $m = new TestManager(); return [$m, new AAC_Member_Portal_Import_Background($m)];
}
function check($ok, $message) { if (!$ok) { throw new Exception($message); } }
[$m,$w] = fixture_worker(); $w->tick(); $s=get_option($w::STATE);
check($s['processed']===100 && $s['remaining']===6 && $m->calls===10, 'bounded worker processes multiple batches');
check(count($dispatches)===1 && wp_next_scheduled($w::TICK), 'direct continuation plus cron fallback');
check(!get_option($w::LOCK) && $m->mode==='profile', 'lock released; profile mode propagated');
check(($filters['pre_wp_mail'])()===true, 'mail suppressed through shutdown');
$w->tick(); check(get_option($w::STATE)['status']==='completed' && count($dispatches)===1, 'completion stops dispatch');
[$m,$w]=fixture_worker(); $m->pause=true; $w->tick(); check(get_option($w::STATE)['status']==='paused' && $m->calls===1 && !$dispatches, 'pause stops continuation');
[$m,$w]=fixture_worker(); $m->fail=true; $w->tick(); check(get_option($w::STATE)['status']==='failed' && !$dispatches && !get_option($w::LOCK), 'failure stops safely');
[$m,$w]=fixture_worker(); add_option($w::LOCK,time()); $w->tick(); check($m->calls===0, 'overlap blocked');
[$m,$w]=fixture_worker(); $GLOBALS['remote_error']=new WP_Error(); $w->tick(); check(wp_next_scheduled($w::TICK) && get_option($w::STATE)['status']==='running', 'blocked loopback retains fallback');
[$m,$w]=fixture_worker(); $_POST=['token'=>'wrong']; try { $w->endpoint(); } catch (RuntimeException $e) { check($e->getMessage()==='Forbidden','invalid token denied'); } check($m->calls===0,'unauthorized endpoint performs no work');
echo "PASS: bounded throughput, continuation, completion, pause, failure, lock, mail suppression, cron fallback, endpoint authorization.\n";

[$m,$w]=fixture_worker(); $m->remaining=20; $m->rowErrors=1; $w->tick(); $s=get_option($w::STATE);
check($s['status']==='completed' && $s['errors']===2 && $m->calls===2, 'logged row errors do not stop background completion');
[$m,$w]=fixture_worker(); $m->rowErrors=1; $m->fatal=true; $w->tick();
check(get_option($w::STATE)['status']==='failed' && $m->calls===1, 'fatal row errors stop background worker');
echo "PASS: background completes with logged row errors and stops on fatal errors.\n";
