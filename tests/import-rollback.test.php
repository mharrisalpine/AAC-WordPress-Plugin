<?php
/** Standalone behavior tests with an in-memory transactional wpdb test double. */
define('ABSPATH', '/tmp/');
define('ARRAY_A', 'ARRAY_A');
$options = []; $events = []; $cache_cleaned = [];
function add_action(...$args) {}
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value, ...$args) { $GLOBALS['options'][$key] = $value; return true; }
function add_option($key, $value, ...$args) { if (isset($GLOBALS['options'][$key])) { return false; } return update_option($key, $value); }
function delete_option($key) { unset($GLOBALS['options'][$key]); }
function absint($n) { return abs((int) $n); }
function wp_schedule_single_event($time, $hook, ...$args) { $GLOBALS['events'][] = $hook; return true; }
function clean_user_cache($id) { $GLOBALS['cache_cleaned'][] = $id; }
function wp_cache_delete(...$args) {}
class AAC_Member_Portal_Import_Background { const LOCK = 'import_lock'; }
class RollbackDB {
	public $prefix = 'wp_', $users = 'wp_users', $usermeta = 'wp_usermeta', $posts = 'wp_posts', $comments = 'wp_comments', $last_error = '';
	public $data = [], $entry, $backup, $writes = 0, $has_content = false, $fail_insert = false;
	function prepare($sql, ...$args) { foreach ($args as $arg) { $sql = preg_replace('/%[sd]/', is_numeric($arg) ? (string) $arg : "'" . $arg . "'", $sql, 1); } return $sql; }
	function get_row($sql, $format) {
		if (strpos($sql, 'SHOW TABLE STATUS') === 0) { return ['Engine' => 'InnoDB']; }
		if (strpos($sql, 'wp_aac_import_rollback') !== false) { return $this->entry['status'] === 'rolled_back' ? null : $this->entry; }
		throw new Exception('Unexpected get_row: ' . $sql);
	}
	function get_results($sql, $format) {
		if (preg_match('/SHOW COLUMNS FROM `([^`]+)`/', $sql, $m)) { return [['Field' => $m[1] === 'wp_users' ? 'ID' : 'id', 'Key' => 'PRI'], ['Field' => 'user_id', 'Key' => '']]; }
		if (preg_match('/SELECT \* FROM `([^`]+)`/', $sql, $m)) { return array_values($this->data[$m[1]] ?? []); }
		throw new Exception('Unexpected get_results: ' . $sql);
	}
	function get_var($sql) { return $this->has_content ? 1 : null; }
	function query($sql) {
		if ($sql === 'START TRANSACTION') { $this->backup = [$this->data, $this->entry]; }
		if ($sql === 'ROLLBACK' && $this->backup) { [$this->data, $this->entry] = $this->backup; }
		if ($sql === 'COMMIT') { $this->backup = null; }
		return 1;
	}
	function delete($table, $where) {
		$this->writes++;
		$this->data[$table] = array_values(array_filter($this->data[$table], static function ($row) use ($where) { foreach ($where as $k => $v) { if (($row[$k] ?? null) != $v) { return true; } } return false; })); return 1;
	}
	function insert($table, $row) { if ($this->fail_insert) { return false; } $this->writes++; $this->data[$table][] = $row; return 1; }
	function update($table, $values, $where) { if ($table === 'wp_aac_import_rollback') { $this->entry = array_merge($this->entry, $values); } return 1; }
}
require __DIR__ . '/../wordpress/aac-member-portal/includes/class-aac-member-portal-import-rollback.php';
function expect_rollback($value, $message) { if (!$value) { throw new Exception($message); } }
function fixture($new = false) {
	$GLOBALS['options'] = []; $GLOBALS['events'] = [];
	$db = new RollbackDB(); $GLOBALS['wpdb'] = $db;
	$before = ['wp_users' => ['pk' => 'ID', 'rows' => $new ? [] : [['ID' => '10', 'user_email' => 'old@example.invalid']]], 'wp_pmpro_memberships_users' => ['pk' => 'id', 'rows' => []]];
	$after = ['wp_users' => ['pk' => 'ID', 'rows' => [['ID' => '10', 'user_email' => 'new@example.invalid']]], 'wp_pmpro_memberships_users' => ['pk' => 'id', 'rows' => [['id' => '5', 'user_id' => '10']]]];
	foreach ($after as $table => $state) { $db->data[$table] = $state['rows']; }
	$db->entry = ['id' => 1, 'batch_id' => 'batch-a', 'stage_id' => 7, 'status' => 'ready', 'payload' => json_encode(['ids' => [10], 'tables' => array_keys($after), 'before' => $before, 'after' => $after])];
	update_option(AAC_Member_Portal_Import_Rollback::STATE, ['status' => 'running', 'batch_id' => 'batch-a', 'restored' => 0]);
	return [$db, new AAC_Member_Portal_Import_Rollback()];
}
[$db, $worker] = fixture(); $worker->tick();
expect_rollback($db->data['wp_users'][0]['user_email'] === 'old@example.invalid', 'existing user restored');
expect_rollback(!$db->data['wp_pmpro_memberships_users'], 'imported membership removed');
expect_rollback($db->entry['status'] === 'rolled_back', 'journal marked restored');
$worker->tick(); expect_rollback(get_option($worker::STATE)['status'] === 'completed', 'completion notification state');
[$db, $worker] = fixture(true); $worker->tick(); expect_rollback(!$db->data['wp_users'], 'new account removed');
[$db, $worker] = fixture(); $db->data['wp_users'][0]['user_email'] = 'later@example.invalid'; $worker->tick();
expect_rollback($db->writes === 0 && get_option($worker::STATE)['status'] === 'failed', 'newer change protected');
[$db, $worker] = fixture(true); $db->has_content = true; $worker->tick(); expect_rollback($db->writes === 0, 'authored content protected');
[$db, $worker] = fixture(); $db->fail_insert = true; $worker->tick();
expect_rollback($db->data['wp_users'][0]['user_email'] === 'new@example.invalid' && $db->entry['status'] === 'ready', 'failed restoration transaction reversed');
[$db, $worker] = fixture(); $db->entry['status'] = 'pending'; $worker->tick(); expect_rollback($db->writes === 0, 'incomplete checkpoint blocked');
[$db, $worker] = fixture(); add_option(AAC_Member_Portal_Import_Background::LOCK, time()); $worker->tick(); expect_rollback($db->writes === 0, 'overlapping worker blocked');
echo "PASS: existing/new accounts, local memberships, completion, conflict, content protection, transaction recovery, incomplete checkpoints, worker lock.\n";
