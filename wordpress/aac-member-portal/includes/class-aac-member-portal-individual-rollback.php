<?php
if (!defined('ABSPATH')) { exit; }

/** Separate, preview-first rollback of individual import histories. */
final class AAC_Member_Portal_Individual_Rollback {
	const STATE = 'aac_individual_import_rollback_state';
	const PAGE = 'aac-individual-import-rollback';
	const AJAX = 'aac_individual_import_rollback_step';
	const HOOK = 'aac_individual_import_rollback_tick';

	public function __construct() {
		add_action('admin_menu', [$this, 'menu']);
		add_action('aac_import_background_controls', [$this, 'link']);
		add_action('admin_post_aac_individual_import_rollback', [$this, 'control']);
		add_action('wp_ajax_' . self::AJAX, [$this, 'ajax']);
		add_action(self::HOOK, [$this, 'tick']);
	}
	public static function active() { return (get_option(self::STATE, [])['status'] ?? '') === 'running'; }
	public static function busy() { return in_array(get_option(self::STATE, [])['status'] ?? '', ['analyzing', 'running'], true); }
	private static function table() { return $GLOBALS['wpdb']->prefix . 'aac_individual_import_rollback'; }
	private static function journal() { return $GLOBALS['wpdb']->prefix . 'aac_import_rollback'; }
	public function menu() { add_submenu_page(null, 'Individual Import Rollback', 'Individual Import Rollback', 'manage_options', self::PAGE, [$this, 'render']); }
	public function link() {
		echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=' . self::PAGE)) . '">Review rollback of individual imports only</a> — blank row_type only; parent and child accounts are protected.</p>';
	}
	private function install() {
		global $wpdb;
		if (get_option('aac_individual_import_rollback_schema') === '1') return;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = self::table();
		dbDelta("CREATE TABLE $table (
		 id bigint unsigned NOT NULL AUTO_INCREMENT,
		 job_id varchar(36) NOT NULL,
		 user_id bigint unsigned NOT NULL,
		 entry_ids longtext NOT NULL,
		 status varchar(24) NOT NULL,
		 message text NOT NULL,
		 restore_action varchar(16) NOT NULL DEFAULT '',
		 PRIMARY KEY  (id),
		 UNIQUE KEY job_user (job_id,user_id),
		 KEY job_status (job_id,status)
		) ENGINE=InnoDB " . $wpdb->get_charset_collate() . ';');
		if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) throw new RuntimeException('Could not create the individual rollback review table.');
		update_option('aac_individual_import_rollback_schema', '1', false);
	}
	private function save($state) { update_option(self::STATE, $state, false); }
	private function assert_idle() {
		if (AAC_Member_Portal_Import_Background::active() || AAC_Member_Portal_Import_Rollback::active() || get_option(AAC_Member_Portal_Import_Background::LOCK)) throw new RuntimeException('Wait for the current import or rollback worker to finish first.');
	}
	public function control() {
		if (!current_user_can('manage_options')) wp_die('Administrator access required.');
		check_admin_referer('aac_individual_import_rollback');
		if (($_POST['command'] ?? '') === 'cancel_review') {
			$state = get_option(self::STATE, []);
			if (($state['status'] ?? '') === 'analyzing') update_option('aac_individual_rollback_cancel', $state['id'], false);
			wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE)); exit;
		}
		try {
			$this->assert_idle();
			$this->install();
			$state = get_option(self::STATE, []);
			$command = sanitize_key(wp_unslash($_POST['command'] ?? ''));
			if ($command === 'analyze' && !self::active()) {
				global $wpdb;
				$exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like(self::journal())));
				if ($exists !== self::journal()) throw new RuntimeException('No import rollback journal exists. No member data has been changed.');
				$ceiling = (int) $wpdb->get_var('SELECT MAX(id) FROM ' . self::journal());
				$state = ['id' => wp_generate_uuid4(), 'status' => 'analyzing', 'phase' => 'journal', 'cursor' => $ceiling + 1, 'ceiling' => $ceiling,
					'owner' => get_current_user_id(), 'unscoped' => [], 'journal_scanned' => 0, 'users_checked' => 0, 'restored' => 0, 'error' => '', 'started' => time()];
				$this->save($state);
			} elseif ($command === 'start') {
				if (($state['status'] ?? '') !== 'ready' || empty($_POST['confirm_individual_rollback']) || ($state['id'] ?? '') !== sanitize_text_field(wp_unslash($_POST['job'] ?? ''))) throw new RuntimeException('Complete and confirm the current rollback preview first.');
				global $wpdb;
				if ((int) $wpdb->get_var('SELECT MAX(id) FROM ' . self::journal()) !== (int) $state['ceiling']) throw new RuntimeException('Imports changed after this preview. Analyze again first.');
				$state['status'] = 'running'; $state['error'] = ''; $this->save($state);
				if (!wp_schedule_single_event(time() + 1, self::HOOK)) throw new RuntimeException('Could not schedule individual rollback.');
			}
		} catch (Throwable $error) { wp_die(esc_html($error->getMessage())); }
		wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE)); exit;
	}

	/** Historical family evidence always overrides a later blank profile-update row. */
	public static function payload_exclusion($payload) {
		global $wpdb;
		$ids = array_values(array_unique(array_filter(array_map('intval', $payload['ids'] ?? []))));
		if (count($ids) !== 1) return 'Checkpoint includes multiple accounts or a family relationship.';
		$raw = null;
		foreach (['before', 'after'] as $phase) {
			foreach (($payload[$phase][$wpdb->usermeta]['rows'] ?? []) as $meta) {
				$key = $meta['meta_key'] ?? ''; $value = $meta['meta_value'] ?? '';
				if (substr($key, -13) === '_capabilities' && !empty(((array) maybe_unserialize($value))['administrator'])) return 'Historical WordPress administrator is protected.';
				if ($key === 'aac_imported_member_row' && $phase === 'after') $raw = json_decode((string) $value, true);
				if (in_array($key, ['aac_family_account_role', 'aac_linked_account_type'], true) && in_array(strtolower(trim((string) $value)), ['parent', 'child', 'adult', 'dependent', 'spouse', 'partner'], true)) return 'Parent or child account in import history.';
				if (in_array($key, ['aac_linked_parent_user_id', 'aac_linked_parent_member_id', 'aac_group_account_group_id', 'aac_group_account_child_level_id'], true) && !empty($value)) return 'Family relationship in import history.';
				if ($key === 'aac_connected_accounts') {
					foreach ((array) maybe_unserialize($value) as $slot) {
						if (is_array($slot) && (!empty($slot['child_user_id']) || !empty($slot['email']))) return 'Connected family members in import history.';
					}
				}
			}
			foreach (($payload[$phase] ?? []) as $table => $data) {
				if ((strpos($table, 'pmprogroupacct_') !== false || strpos($table, 'pmpro_groups') !== false) && !empty($data['rows'])) return 'PMPro Group Accounts history is protected.';
			}
		}
		if (!is_array($raw)) return 'Original imported row is unavailable; review manually.';
		if (trim((string) ($raw['row_type'] ?? '')) !== '') return 'Original imported row has a nonblank row_type.';
		foreach (['parent_member_id','parent_aac_member_id','parent_user_id','parent_email','parent_user_login','aac_linked_parent_user_id','aac_linked_parent_member_id'] as $key) {
			if (!empty($raw[$key])) return 'Original imported row links to a parent.';
		}
		foreach (['family_role', 'aac_family_account_role', 'account_type'] as $key) {
			if (in_array(strtolower(trim((string) ($raw[$key] ?? ''))), ['parent', 'child', 'adult', 'dependent', 'spouse', 'partner'], true)) return 'Original imported row identifies a parent or child.';
		}
		return '';
	}
	private function current_family_ids() {
		global $wpdb;
		static $ids = null;
		if ($ids !== null) return $ids;
		$ids = [];
		$rows = $wpdb->get_results("SELECT user_id,meta_key,meta_value FROM {$wpdb->usermeta} WHERE
			(meta_key='aac_family_account_role' AND meta_value IN ('parent','child','adult','dependent','spouse','partner')) OR
			(meta_key IN ('aac_connected_accounts','aac_linked_parent_user_id','aac_group_account_group_id','aac_group_account_child_level_id') AND meta_value NOT IN ('','0','a:0:{}'))", ARRAY_A);
		if ($wpdb->last_error) throw new RuntimeException('Could not verify family protections.');
		foreach ($rows as $row) {
			$owner = (int) $row['user_id'];
			if ($row['meta_key'] === 'aac_connected_accounts') {
				foreach ((array) maybe_unserialize($row['meta_value']) as $slot) {
					if (!is_array($slot)) continue;
					if (!empty($slot['child_user_id']) || !empty($slot['email'])) $ids[$owner] = true;
					if (!empty($slot['child_user_id'])) $ids[(int) $slot['child_user_id']] = true;
				}
			} else {
				$ids[$owner] = true;
				if ($row['meta_key'] === 'aac_linked_parent_user_id' && (int) $row['meta_value'] > 0) $ids[(int) $row['meta_value']] = true;
			}
		}
		// Protect Group Accounts even when their AAC metadata is incomplete.
		$tables = array_merge(
			(array) $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpdb->prefix . 'pmprogroupacct_') . '%')),
			(array) $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpdb->prefix . 'pmpro_group') . '%'))
		);
		foreach (array_unique($tables) as $table) {
			if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) throw new RuntimeException('Invalid group table name.');
			$columns = $wpdb->get_col("SHOW COLUMNS FROM `$table`", 0);
			foreach (array_intersect(['user_id','parent_user_id','child_user_id','group_parent_user_id','group_child_user_id'], (array) $columns) as $column) {
				foreach ((array) $wpdb->get_col("SELECT DISTINCT `$column` FROM `$table` WHERE `$column` > 0") as $id) $ids[(int) $id] = true;
			}
			if ($wpdb->last_error) throw new RuntimeException('Could not verify current Group Accounts.');
		}
		return $ids;
	}
	private function current_exclusion($user_id) {
		$user = get_user_by('id', $user_id);
		if (!$user instanceof WP_User) return 'Account no longer exists; review manually.';
		if (in_array('administrator', (array) $user->roles, true)) return 'WordPress administrator is protected.';
		if (isset($this->current_family_ids()[$user_id])) return 'Current parent, child or group relationship is protected.';
		$role = strtolower((string) get_user_meta($user_id, 'aac_family_account_role', true));
		if (in_array($role, ['parent', 'child', 'adult', 'dependent', 'spouse', 'partner'], true)) return 'Current parent or child account is protected.';
		foreach (['aac_linked_parent_user_id', 'aac_group_account_group_id', 'aac_group_account_child_level_id'] as $key) {
			if (get_user_meta($user_id, $key, true)) return 'Current group/family relationship is protected.';
		}
		foreach ((array) get_user_meta($user_id, 'aac_connected_accounts', true) as $slot) {
			if (is_array($slot) && (!empty($slot['child_user_id']) || !empty($slot['email']))) return 'Current connected family members are protected.';
		}
		return '';
	}
	private function record($job, $user_id, $entry_id, $reason = '') {
		global $wpdb;
		$table = self::table();
		$status = $reason === '' ? 'candidate' : 'protected';
		$result = $wpdb->query($wpdb->prepare("INSERT INTO $table (job_id,user_id,entry_ids,status,message) VALUES (%s,%d,%s,%s,%s)
			ON DUPLICATE KEY UPDATE entry_ids=IF(VALUES(entry_ids)='' OR FIND_IN_SET(VALUES(entry_ids),entry_ids)>0,entry_ids,CONCAT_WS(',',NULLIF(entry_ids,''),VALUES(entry_ids))),
			message=IF(VALUES(status)='protected',VALUES(message),message),status=IF(status='protected' OR VALUES(status)='protected','protected',status)",
			$job, $user_id, $entry_id ? (string) $entry_id : '', $status, $reason));
		if ($result === false) throw new RuntimeException('Could not save individual rollback preview.');
	}
	private function entries($plan) {
		global $wpdb;
		$ids = array_values(array_filter(array_map('absint', explode(',', $plan['entry_ids']))));
		if (!$ids) throw new RuntimeException('No checkpoints are available for this account.');
		$rows = $wpdb->get_results('SELECT * FROM ' . self::journal() . ' WHERE id IN (' . implode(',', $ids) . ') ORDER BY id DESC', ARRAY_A);
		if ($wpdb->last_error || count($rows) !== count($ids)) throw new RuntimeException('Checkpoint history is incomplete.');
		return $rows;
	}
	/** Check the entire newest-to-oldest chain before proposing any member changes. */
	public static function check_chain($entries, $user_id, $current) {
		global $wpdb;
		$simulated = $current;
		foreach ($entries as $entry) {
			$payload = json_decode($entry['payload'], true);
			if ($entry['status'] !== 'ready' || !is_array($payload) || self::payload_exclusion($payload) !== '') throw new RuntimeException('A checkpoint is incomplete or includes a protected account.');
			$ids = array_values(array_unique(array_map('intval', $payload['ids'] ?? [])));
			if ($ids !== [(int) $user_id]) throw new RuntimeException('Checkpoint belongs to another account.');
			if (!AAC_Member_Portal_Import_Rollback::matches($payload['after'], $simulated)) throw new RuntimeException('Records changed after import, or checkpoints do not form a complete restore chain.');
			$simulated = $payload['before'];
		}
		return empty($simulated[$wpdb->users]['rows']) ? 'remove' : 'restore';
	}
	private function mark($id, $status, $message, $action = '') {
		if ($GLOBALS['wpdb']->update(self::table(), ['status' => $status, 'message' => $message, 'restore_action' => $action], ['id' => $id]) === false) throw new RuntimeException('Could not save rollback review status.');
	}
	public function analyze_step() {
		$this->assert_idle();
		if (!add_option(AAC_Member_Portal_Import_Background::LOCK, time(), '', false)) throw new RuntimeException('Another worker is active. Reload to resume the review.');
		$finished = false;
		register_shutdown_function(static function () use (&$finished) { if (!$finished) delete_option(AAC_Member_Portal_Import_Background::LOCK); });
		try { return $this->analyze_locked(); }
		finally { delete_option(AAC_Member_Portal_Import_Background::LOCK); $finished = true; }
	}
	private function analyze_locked() {
		global $wpdb;
		$state = get_option(self::STATE, []);
		if (($state['status'] ?? '') !== 'analyzing') return $state;
		$deadline = microtime(true) + 5;
		try {
			if ($state['phase'] === 'journal') {
				$rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::journal() . " WHERE id < %d AND status <> 'rolled_back' ORDER BY id DESC LIMIT 150", $state['cursor']), ARRAY_A);
				if ($wpdb->last_error) throw new RuntimeException('Could not read import checkpoints.');
				foreach ($rows as $entry) {
					$payload = json_decode($entry['payload'], true);
					if (!is_array($payload) || empty($payload['ids'])) {
						$state['unscoped'][] = (int) $entry['id'];
						$state['journal_scanned']++; $state['cursor'] = (int) $entry['id'];
						continue; // Never restore an entry whose account ownership cannot be proved.
					}
					$reason = $entry['status'] === 'ready' ? self::payload_exclusion($payload) : 'Incomplete checkpoint; review manually.';
					foreach (array_unique(array_filter(array_map('intval', $payload['ids']))) as $id) $this->record($state['id'], $id, (int) $entry['id'], $reason);
					$state['journal_scanned']++; $state['cursor'] = (int) $entry['id'];
					if (microtime(true) >= $deadline) break;
				}
				if (!$rows) { $state['phase'] = 'untracked'; $state['cursor'] = 0; }
			} elseif ($state['phase'] === 'untracked') {
				$table = self::table();
				$ids = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT u.ID FROM {$wpdb->users} u
					INNER JOIN {$wpdb->usermeta} m ON m.user_id=u.ID AND m.meta_key='aac_import_source' AND m.meta_value='member_import_manager'
					LEFT JOIN $table p ON p.user_id=u.ID AND p.job_id=%s WHERE p.id IS NULL AND u.ID>%d ORDER BY u.ID LIMIT 150", $state['id'], $state['cursor']));
				if ($wpdb->last_error) throw new RuntimeException('Could not check imports without checkpoints.');
				foreach ($ids as $id) {
					$reason = $this->current_exclusion((int) $id);
					$this->record($state['id'], (int) $id, 0, $reason ?: 'No rollback checkpoint exists; restore from a backup or review manually.');
					$state['cursor'] = (int) $id;
					if (microtime(true) >= $deadline) break;
				}
				if (!$ids) { $state['phase'] = 'validate'; $state['cursor'] = 0; }
			} else {
				$rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::table() . " WHERE job_id=%s AND status='candidate' ORDER BY id ASC LIMIT 250", $state['id']), ARRAY_A);
				if ($wpdb->last_error) throw new RuntimeException('Could not read rollback review candidates.');
				foreach ($rows as $plan) {
					$id = (int) $plan['user_id']; $reason = $this->current_exclusion($id);
					if ($reason) $this->mark($plan['id'], 'protected', $reason);
					else {
						try {
							$entries = $this->entries($plan); $last = json_decode($entries[0]['payload'], true);
							$current = AAC_Member_Portal_Import_Rollback::inspect_payload($last);
							$action = self::check_chain($entries, $id, $current);
							if ($action === 'remove' && ($wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_author=%d LIMIT 1", $id)) || $wpdb->get_var($wpdb->prepare("SELECT comment_ID FROM {$wpdb->comments} WHERE user_id=%d LIMIT 1", $id)))) throw new RuntimeException('Account owns website content and is protected.');
							$this->mark($plan['id'], 'ready', '', $action);
						} catch (Throwable $error) { $this->mark($plan['id'], 'conflict', $error->getMessage()); }
					}
					$state['users_checked']++;
					if (microtime(true) >= $deadline) break;
				}
				if (!$rows) { $state['status'] = 'ready'; $state['phase'] = 'review'; }
			}
		} catch (Throwable $error) { $state['status'] = 'failed'; $state['error'] = $error->getMessage(); }
		if (get_option('aac_individual_rollback_cancel') === $state['id']) $state['status'] = 'cancelled';
		$this->save($state); return $state;
	}
	public function counts($job) {
		global $wpdb;
		return $wpdb->get_results($wpdb->prepare('SELECT status,restore_action,COUNT(*) AS total FROM ' . self::table() . ' WHERE job_id=%s GROUP BY status,restore_action', $job), ARRAY_A);
	}
	public function ajax() {
		if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Administrator access required.'], 403);
		check_ajax_referer(self::AJAX);
		$state = get_option(self::STATE, []);
		if (($state['id'] ?? '') !== sanitize_text_field(wp_unslash($_POST['job'] ?? ''))) wp_send_json_error(['message' => 'This review was replaced. Reload the page.'], 409);
		if (($state['status'] ?? '') === 'analyzing') $state = $this->analyze_step();
		wp_send_json_success(['status' => $state['status'], 'phase' => $state['phase'], 'scanned' => $state['journal_scanned'], 'checked' => $state['users_checked'], 'restored' => $state['restored'], 'error' => $state['error']]);
	}
	public function tick() {
		global $wpdb;
		$state = get_option(self::STATE, []);
		if (($state['status'] ?? '') !== 'running') return;
		if (!add_option(AAC_Member_Portal_Import_Background::LOCK, time(), '', false)) { wp_schedule_single_event(time() + 10, self::HOOK); return; }
		$finished = false;
		register_shutdown_function(static function () use (&$finished) {
			if ($finished) return;
			$GLOBALS['wpdb']->query('ROLLBACK');
			$state = get_option(self::STATE, []); $state['status'] = 'failed'; $state['error'] = 'Individual rollback interrupted. Review before continuing.';
			update_option(self::STATE, $state, false); delete_option(AAC_Member_Portal_Import_Background::LOCK);
		});
		$deadline = microtime(true) + 15;
		try {
			$plans = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::table() . " WHERE job_id=%s AND status='ready' ORDER BY id ASC LIMIT 20", $state['id']), ARRAY_A);
			if ($wpdb->last_error) throw new RuntimeException('Could not read the confirmed rollback plan.');
			if (!$plans) $state['status'] = 'completed';
			foreach ($plans as $plan) {
				$id = (int) $plan['user_id']; $reason = $this->current_exclusion($id);
				if ($reason) throw new RuntimeException('User ' . $id . ' is now protected: ' . $reason);
				$entries = $this->entries($plan); $payload = json_decode($entries[0]['payload'], true);
				self::check_chain($entries, $id, AAC_Member_Portal_Import_Rollback::inspect_payload($payload));
				foreach ($entries as $entry) AAC_Member_Portal_Import_Rollback::restore_entry($entry);
				$this->mark($plan['id'], 'completed', 'Individual import history rolled back.', $plan['restore_action']);
				$state['restored']++; $this->save($state);
				if (microtime(true) >= $deadline) break;
			}
		} catch (Throwable $error) { $state['status'] = 'failed'; $state['error'] = $error->getMessage(); }
		finally {
			$this->save($state); delete_option(AAC_Member_Portal_Import_Background::LOCK); $finished = true;
			if ($state['status'] === 'running' && !wp_schedule_single_event(time() + 2, self::HOOK)) { $state['status'] = 'failed'; $state['error'] = 'Could not schedule rollback continuation.'; $this->save($state); }
		}
	}
	public function render() {
		if (!current_user_can('manage_options')) return;
		$state = get_option(self::STATE, []);
		echo '<div class="wrap"><h1>Individual Import Rollback</h1><p>This preview targets imported rows whose original row_type was blank. Parent, child, family-linked and administrator accounts are excluded. Existing accounts are restored; accounts created by the imports are removed only after confirmation.</p>';
		if ($state) {
			echo '<p id="aac-individual-status">Status: ' . esc_html($state['status']) . '. Checkpoints reviewed: ' . (int) $state['journal_scanned'] . '. Accounts checked: ' . (int) $state['users_checked'] . '. Accounts rolled back: ' . (int) $state['restored'] . '.</p>';
			if (!empty($state['unscoped'])) echo '<p>Unscoped checkpoints requiring separate review: ' . count($state['unscoped']) . '. These are excluded.</p>';
			if ($state['error']) echo '<div class="notice notice-error"><p>' . esc_html($state['error']) . '</p></div>';
			if (in_array($state['status'], ['ready', 'running', 'completed', 'failed'], true)) {
				echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th>Category</th><th>Action</th><th>Accounts</th></tr></thead><tbody>';
				foreach ((array) $this->counts($state['id']) as $row) echo '<tr><td>' . esc_html($row['status']) . '</td><td>' . esc_html($row['restore_action']) . '</td><td>' . (int) $row['total'] . '</td></tr>';
				echo '</tbody></table>';
				global $wpdb;
				$eligible = $wpdb->get_results($wpdb->prepare('SELECT user_id,restore_action FROM ' . self::table() . " WHERE job_id=%s AND status='ready' ORDER BY id LIMIT 30", $state['id']), ARRAY_A);
				echo '<h2>Eligible individual accounts (first 30)</h2><table class="widefat striped" style="max-width:900px"><thead><tr><th>WordPress user ID</th><th>Member</th><th>AAC member number</th><th>Action</th></tr></thead><tbody>';
				foreach ((array) $eligible as $row) {
					$user = get_user_by('id', (int) $row['user_id']);
					echo '<tr><td>' . (int) $row['user_id'] . '</td><td>' . esc_html($user instanceof WP_User ? $user->display_name : 'Missing account') . '</td><td>' . esc_html((string) get_user_meta((int) $row['user_id'], 'aac_member_id', true)) . '</td><td>' . esc_html($row['restore_action']) . '</td></tr>';
				}
				echo '</tbody></table>';
				$examples = $wpdb->get_results($wpdb->prepare('SELECT user_id,status,message FROM ' . self::table() . " WHERE job_id=%s AND status IN ('protected','conflict') ORDER BY id LIMIT 30", $state['id']), ARRAY_A);
				echo '<h2>Protected or conflicting records (first 30)</h2><ul>';
				foreach ((array) $examples as $row) echo '<li>User ' . (int) $row['user_id'] . ': ' . esc_html($row['message']) . '</li>';
				echo '</ul>';
			}
		}
		if (!self::active() && ($state['status'] ?? '') !== 'analyzing') {
			echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="aac_individual_import_rollback"><input type="hidden" name="command" value="analyze">';
			wp_nonce_field('aac_individual_import_rollback');
			echo '<p><button class="button" type="submit">Analyze individual imports — no member changes</button></p></form>';
		}
		if (($state['status'] ?? '') === 'analyzing') {
			echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="aac_individual_import_rollback"><input type="hidden" name="command" value="cancel_review">';
			wp_nonce_field('aac_individual_import_rollback');
			echo '<button class="button" type="submit">Cancel review — no member changes</button></form>';
		}
		if (($state['status'] ?? '') === 'ready') {
			echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="aac_individual_import_rollback"><input type="hidden" name="command" value="start"><input type="hidden" name="job" value="' . esc_attr($state['id']) . '">';
			wp_nonce_field('aac_individual_import_rollback');
			echo '<p><label><input type="checkbox" name="confirm_individual_rollback" value="1" required> I have reviewed the counts and have a current database backup. Restore eligible existing individual accounts and remove eligible individual accounts created by import.</label></p><button class="button button-primary" type="submit">Roll back reviewed individual imports</button></form>';
		}
		if (in_array($state['status'] ?? '', ['analyzing', 'running'], true)) {
			$config = ['url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce(self::AJAX), 'job' => $state['id'], 'status' => $state['status']];
			?>
			<script>(() => {
				const config = <?php echo wp_json_encode($config); ?>;
				const status = document.getElementById('aac-individual-status');
				const next = async () => {
					try {
						const response = await fetch(config.url, {method:'POST', credentials:'same-origin', body:new URLSearchParams({action:'<?php echo esc_js(self::AJAX); ?>', _ajax_nonce:config.nonce, job:config.job})});
						const result = await response.json();
						if (!response.ok || !result.success) throw new Error(result.data?.message || 'Review request failed.');
						const data = result.data;
						status.textContent = `Status: ${data.status}; phase: ${data.phase}; checkpoints reviewed: ${data.scanned}; accounts checked: ${data.checked}; accounts rolled back: ${data.restored}. ${data.error || ''}`;
						if (!['analyzing','running'].includes(data.status)) { location.reload(); return; }
						setTimeout(next, data.status === 'running' ? 3000 : 150);
					} catch (error) { status.textContent = 'Review paused: ' + error.message + ' Reload this page to retry. No rollback starts without confirmation.'; }
				}; next();
			})();</script>
			<?php
		}
		echo '</div>';
	}
}
