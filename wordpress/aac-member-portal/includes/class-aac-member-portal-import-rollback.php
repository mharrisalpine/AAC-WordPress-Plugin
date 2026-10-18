<?php
if (!defined('ABSPATH')) { exit; }

/** Private database journal. No files containing passwords or member data are exported. */
final class AAC_Member_Portal_Import_Rollback {
	private static $table_cache = null;
	private static $schema_cache = [];
	const STATE = 'aac_import_rollback_state';
	const HOOK = 'aac_import_rollback_tick';
	public function __construct() {
		add_action('admin_post_aac_import_rollback', [$this, 'start']);
		add_action(self::HOOK, [$this, 'tick']);
		add_action('aac_import_background_controls', [$this, 'render']);
		add_action('admin_notices', [$this, 'notice']);
	}
	public static function active() { return (get_option(self::STATE, [])['status'] ?? '') === 'running'; }
	private static function table() { return $GLOBALS['wpdb']->prefix . 'aac_import_rollback'; }
	private static function query($sql) {
		$r = $GLOBALS['wpdb']->query($sql);
		if ($r === false) { throw new RuntimeException('Rollback database operation failed. Check the database error log.'); }
		return $r;
	}
	private static function install() {
		global $wpdb;
		if (is_multisite()) { throw new RuntimeException('Import rollback currently supports single-site WordPress only.'); }
		if (get_option('aac_import_rollback_schema') === '2') { return; }
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t = self::table();
		dbDelta("CREATE TABLE $t (
		 id bigint unsigned NOT NULL AUTO_INCREMENT,
		 stage_id bigint unsigned NOT NULL,
		 batch_id varchar(64) NOT NULL,
		 status varchar(24) NOT NULL DEFAULT 'pending',
		 payload longtext NOT NULL,
		 created_at datetime NOT NULL,
		 PRIMARY KEY  (id),
		 KEY batch_id (batch_id),
		 KEY stage_id (stage_id)
		) ENGINE=InnoDB " . $wpdb->get_charset_collate() . ';');
		if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($t))) !== $t) { throw new RuntimeException('Could not create rollback journal. Import stopped before writing members.'); }
		update_option('aac_import_rollback_schema', '2', false);
	}
	private static function tables() {
		global $wpdb;
		if (self::$table_cache !== null) { return self::$table_cache; }
		$tables = [$wpdb->users, $wpdb->usermeta];
		foreach (['pmpro_', 'pmprogroupacct_', 'aac_member_db_'] as $prefix) {
			$found = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpdb->prefix . $prefix) . '%'));
			foreach ($found as $table) {
				$columns = $wpdb->get_col("SHOW COLUMNS FROM `$table`", 0);
				if (array_intersect(['user_id', 'group_parent_user_id', 'group_child_user_id'], $columns)) { $tables[] = $table; }
			}
		}
		// Metadata tables belong to orders/subscriptions rather than directly to users.
		foreach (['pmpro_membership_ordermeta', 'pmpro_subscriptionmeta'] as $suffix) {
			$table = $wpdb->prefix . $suffix;
			if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table) { $tables[] = $table; }
		}
		return self::$table_cache = array_values(array_unique($tables));
	}
	private static function snapshot($ids, $tables) {
		global $wpdb;
		$ids = array_values(array_filter(array_unique(array_map('absint', $ids))));
		$in = implode(',', $ids ?: [0]);
		$out = [];
		foreach ($tables as $table) {
			if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) { throw new RuntimeException('Unsupported table name.'); }
			if (!isset(self::$schema_cache[$table])) {
			$columns = $wpdb->get_results("SHOW COLUMNS FROM `$table`", ARRAY_A);
			$primary = array_values(array_filter($columns, static function ($c) { return $c['Key'] === 'PRI'; }));
			if (count($primary) !== 1) { throw new RuntimeException('Rollback requires a single-column primary key: ' . $table); }
			$engine = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s', $table), ARRAY_A);
			if (strcasecmp($engine['Engine'] ?? '', 'InnoDB') !== 0) { throw new RuntimeException('Rollback requires InnoDB: ' . $table); }
			self::$schema_cache[$table] = [$columns, $primary];
			}
			[$columns, $primary] = self::$schema_cache[$table];
			$conditions = [];
			foreach ($columns as $column) {
				if (in_array($column['Field'], $table === $wpdb->users ? ['ID'] : ['user_id', 'group_parent_user_id', 'group_child_user_id'], true)) { $conditions[] = '`' . $column['Field'] . "` IN ($in)"; }
			}
			if ($table === $wpdb->prefix . 'pmpro_membership_ordermeta') {
				$fields = array_column($columns, 'Field');
				$foreign = in_array('pmpro_membership_order_id', $fields, true) ? 'pmpro_membership_order_id' : (in_array('order_id', $fields, true) ? 'order_id' : '');
				if ($foreign) { $conditions[] = "`$foreign` IN (SELECT id FROM `{$wpdb->prefix}pmpro_membership_orders` WHERE user_id IN ($in))"; }
			}
			if ($table === $wpdb->prefix . 'pmpro_subscriptionmeta' && in_array('pmpro_subscription_id', array_column($columns, 'Field'), true)) {
				$conditions[] = "pmpro_subscription_id IN (SELECT id FROM `{$wpdb->prefix}pmpro_subscriptions` WHERE user_id IN ($in))";
			}
			if (!$conditions) { throw new RuntimeException('Unsupported rollback ownership columns: ' . $table); }
			$pk = $primary[0]['Field'];
			$rows = $wpdb->get_results("SELECT * FROM `$table` WHERE " . implode(' OR ', $conditions) . " ORDER BY `$pk`", ARRAY_A);
			if ($wpdb->last_error) { throw new RuntimeException('Could not read rollback snapshot: ' . $table); }
			$out[$table] = ['pk' => $pk, 'rows' => $rows];
		}
		return $out;
	}
	public static function begin($stage, $ids) {
		global $wpdb;
		self::install();
		$existing = $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . self::table() . " WHERE stage_id = %d AND status <> 'rolled_back' LIMIT 1", $stage['id']));
		if ($wpdb->last_error || $existing) { throw new RuntimeException('This staged row has an earlier checkpoint or its checkpoint could not be checked. Review before retrying; no member writes performed.'); }
		$tables = self::tables();
		$payload = ['ids' => array_values(array_unique(array_filter(array_map('absint', $ids)))), 'tables' => $tables, 'before' => self::snapshot($ids, $tables)];
		if (!$wpdb->insert(self::table(), ['stage_id' => $stage['id'], 'batch_id' => $stage['batch_id'], 'status' => 'pending', 'payload' => wp_json_encode($payload), 'created_at' => current_time('mysql')])) { throw new RuntimeException('Could not save rollback checkpoint.'); }
		return ['id' => (int) $wpdb->insert_id, 'payload' => $payload];
	}
	public static function seal($checkpoint, $user_id) {
		global $wpdb;
		$p = $checkpoint['payload'];
		if ($user_id) { $p['ids'][] = (int) $user_id; }
		$p['ids'] = array_values(array_unique($p['ids']));
		$p['after'] = self::snapshot($p['ids'], $p['tables']);
		if ($wpdb->update(self::table(), ['payload' => wp_json_encode($p), 'status' => 'ready'], ['id' => $checkpoint['id']]) === false) { throw new RuntimeException('Import wrote data but could not finalize its rollback checkpoint. Stop and review.'); }
	}
	/** Compare complete scoped records; newer activity must never be overwritten. */
	public static function matches($expected, $actual) { return $expected === $actual; }
	public function start() {
		global $wpdb;
		if (!current_user_can('manage_options')) { wp_die('Administrator access required.'); }
		check_admin_referer('aac_import_rollback');
		if (AAC_Member_Portal_Import_Background::active() || self::active()) { wp_die('Pause the import and wait for its worker to finish first.'); }
		self::install();
		$last = $wpdb->get_row('SELECT * FROM ' . self::table() . " WHERE status <> 'rolled_back' ORDER BY id DESC LIMIT 1", ARRAY_A);
		if (!$last || $last['status'] !== 'ready') { wp_die('No complete rollback checkpoint is available. An interrupted row requires manual review.'); }
		if (empty($_POST['confirm_rollback'])) { wp_die('Confirm rollback before continuing.'); }
		$s = ['status' => 'running', 'batch_id' => $last['batch_id'], 'restored' => 0, 'message' => 'Rollback queued.'];
		update_option(self::STATE, $s, false);
		if (!wp_schedule_single_event(time() + 5, self::HOOK)) { $s['status'] = 'failed'; $s['message'] = 'Could not schedule rollback.'; update_option(self::STATE, $s, false); }
		wp_safe_redirect(admin_url('admin.php?page=' . AAC_Member_Portal_Import_Manager::PAGE_SLUG)); exit;
	}
	public function tick() {
		global $wpdb;
		$s = get_option(self::STATE, []);
		if (($s['status'] ?? '') !== 'running') { return; }
		if (!add_option(AAC_Member_Portal_Import_Background::LOCK, time(), '', false)) {
			$s['status'] = 'failed'; $s['message'] = 'Another worker holds the import lock. No rollback performed.'; update_option(self::STATE, $s, false); return;
		}
		$finished = false;
		register_shutdown_function(function () use (&$finished) {
			if ($finished) { return; }
			$GLOBALS['wpdb']->query('ROLLBACK');
			$s = get_option(self::STATE, []); $s['status'] = 'failed'; $s['message'] = 'Rollback interrupted. Review before resuming.';
			update_option(self::STATE, $s, false); delete_option(AAC_Member_Portal_Import_Background::LOCK);
		});
		try {
			$entry = $wpdb->get_row('SELECT * FROM ' . self::table() . " WHERE status <> 'rolled_back' ORDER BY id DESC LIMIT 1", ARRAY_A);
			if (!$entry || $entry['batch_id'] !== $s['batch_id']) { $s['status'] = 'completed'; $s['message'] = 'Rollback completed.'; }
			else {
				if ($entry['status'] !== 'ready') { throw new RuntimeException('Incomplete checkpoint requires manual review.'); }
				$p = json_decode($entry['payload'], true);
				if (!is_array($p) || empty($p['after'])) { throw new RuntimeException('Invalid checkpoint.'); }
				self::query('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
				self::query('START TRANSACTION');
				$current = self::snapshot($p['ids'], $p['tables']);
				if (!self::matches($p['after'], $current)) { throw new RuntimeException('Member or family records changed after import. Rollback stopped to preserve newer data. Stage row ' . (int) $entry['stage_id']); }
				$before_users = array_column($p['before'][$wpdb->users]['rows'], 'ID');
				$new_users = array_diff(array_column($p['after'][$wpdb->users]['rows'], 'ID'), $before_users);
				foreach ($new_users as $id) {
					if ($wpdb->get_var($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_author = %d LIMIT 1", $id)) || $wpdb->get_var($wpdb->prepare("SELECT comment_ID FROM $wpdb->comments WHERE user_id = %d LIMIT 1", $id))) { throw new RuntimeException('New account owns content. Reassign content before rollback.'); }
				}
				// Direct local restoration deliberately bypasses cancellation and mail hooks.
				foreach (array_reverse($p['tables']) as $table) {
					$pk = $p['after'][$table]['pk'];
					foreach ($p['after'][$table]['rows'] as $row) { if ($wpdb->delete($table, [$pk => $row[$pk]]) === false) { throw new RuntimeException('Could not remove imported row.'); } }
				}
				foreach ($p['tables'] as $table) {
					foreach ($p['before'][$table]['rows'] as $row) { if ($wpdb->insert($table, $row) === false) { throw new RuntimeException('Could not restore original row.'); } }
				}
				if ($wpdb->update(self::table(), ['status' => 'rolled_back'], ['id' => $entry['id']]) === false) { throw new RuntimeException('Could not mark checkpoint restored.'); }
				if ($wpdb->update($wpdb->prefix . 'aac_member_portal_import_rows', ['status' => 'rolled_back', 'error_message' => 'Rolled back by administrator.'], ['id' => $entry['stage_id']]) === false) { throw new RuntimeException('Could not update staging record.'); }
				self::query('COMMIT');
				foreach ($p['ids'] as $id) { clean_user_cache($id); wp_cache_delete($id, 'user_meta'); wp_cache_delete($id, 'pmpro_memberships_users'); }
				$s['restored']++; $s['message'] = 'Restored stage row ' . (int) $entry['stage_id'];
			}
		} catch (Throwable $e) { $wpdb->query('ROLLBACK'); $s['status'] = 'failed'; $s['message'] = $e->getMessage(); }
		finally {
			update_option(self::STATE, $s, false); delete_option(AAC_Member_Portal_Import_Background::LOCK); $finished = true;
			if ($s['status'] === 'running' && !wp_schedule_single_event(time() + 5, self::HOOK)) { $s['status'] = 'failed'; $s['message'] = 'Could not schedule next rollback row.'; update_option(self::STATE, $s, false); }
		}
	}
	public function notice() {
		if (!current_user_can('manage_options')) { return; }
		$s = get_option(self::STATE, []);
		if (in_array($s['status'] ?? '', ['completed', 'failed'], true)) { echo '<div class="notice notice-' . ($s['status'] === 'failed' ? 'error' : 'success') . '"><p>Member import rollback: ' . esc_html($s['message']) . '</p></div>'; }
	}
	public function render() {
		global $wpdb;
		$s = get_option(self::STATE, []);
		echo '<h3>Rollback latest member import</h3><p>Restores the latest imported CSV batch in reverse order, including local member and family records. Only imports made after rollback was added are covered. Stops if records changed afterward. Does not reverse Stripe activity, email, Salesforce, or other external integrations. Keep a full site backup.</p>';
		echo '<p>' . esc_html(($s['status'] ?? 'Not started') . ': ' . ($s['message'] ?? '')) . ' Restored rows: ' . (int) ($s['restored'] ?? 0) . '</p>';
		$last = in_array(get_option('aac_import_rollback_schema'), ['1', '2'], true) ? $wpdb->get_row('SELECT batch_id, status, created_at FROM ' . self::table() . " WHERE status <> 'rolled_back' ORDER BY id DESC LIMIT 1", ARRAY_A) : null;
		echo '<p>Latest checkpoint: ' . esc_html($last ? $last['batch_id'] . ' (' . $last['created_at'] . ', ' . $last['status'] . ')' : 'None. Existing imports cannot be rolled back with this tool.') . '</p>';
		if ($last && $last['status'] === 'ready' && !self::active() && !AAC_Member_Portal_Import_Background::active()) {
			echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="aac_import_rollback">';
			wp_nonce_field('aac_import_rollback');
			echo '<label><input type="checkbox" name="confirm_rollback" value="1" required> Restore updated accounts and remove accounts created by the latest imported batch.</label> <button class="button" type="submit">Roll back latest batch</button></form>';
		}
	}
}
