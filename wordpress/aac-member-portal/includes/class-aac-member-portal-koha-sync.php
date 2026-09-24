<?php
/** Two-hour, resumable PMPro entitlement reconciliation with Koha REST v1. */
if (!defined('ABSPATH')) { exit; }

class AAC_Member_Portal_Koha_Sync {
	const MENU_SLUG = 'aac-member-portal-koha-sync';
	const OPTION = 'aac_koha_settings';
	const CRON = 'aac_koha_reconcile';
	const BATCH = 'aac_koha_continue';
	const SCHEMA = 'aac_koha_schema';
	private $token = '';
	private $token_until = 0;

	public function __construct() {
		add_filter('cron_schedules', [$this, 'schedules']);
		add_action('init', [$this, 'init']);
		add_action('admin_menu', [$this, 'menu']);
		add_action('admin_post_aac_koha', [$this, 'handle_admin']);
		add_action(self::CRON, [$this, 'run']);
		add_action(self::BATCH, [$this, 'run']);
	}

	public static function table() { global $wpdb; return $wpdb->prefix . 'aac_koha_members'; }
	public function settings() {
		return array_merge([
			'enabled' => 0, 'url' => '', 'client_id' => '', 'secret' => '', 'library' => '', 'category' => '',
			'levels' => [], 'backfill' => 0, 'adopt_existing' => 0, 'baseline' => null, 'site' => '',
		], (array) get_option(self::OPTION, []));
	}
	public function schedules($s) { $s['aac_koha_two_hours'] = ['interval' => 7200, 'display' => 'Every two hours (Koha)']; return $s; }
	public static function deactivate() { wp_clear_scheduled_hook(self::CRON); wp_clear_scheduled_hook(self::BATCH); }
	public function init() {
		global $wpdb;
		if (get_option(self::SCHEMA) !== '1') {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			$table = self::table();
			dbDelta("CREATE TABLE {$table} (
				user_id bigint(20) unsigned NOT NULL,
				state longtext NOT NULL,
				PRIMARY KEY  (user_id)
			) " . $wpdb->get_charset_collate() . ';');
			if (!$wpdb->last_error) { update_option(self::SCHEMA, '1', false); }
		}
		$s = $this->settings();
		if ($s['enabled'] && $s['site'] === home_url() && !wp_next_scheduled(self::CRON)) {
			wp_schedule_event(time() + 60, 'aac_koha_two_hours', self::CRON);
		} elseif (!$s['enabled'] || $s['site'] !== home_url()) {
			self::deactivate();
		}
	}
	public function menu() {
		add_submenu_page(null, 'Koha Library Sync', 'Koha Library Sync', 'manage_options', self::MENU_SLUG, [$this, 'render']);
	}

	/** Authenticated encryption; a database-only leak must not expose the client secret. */
	public static function seal($secret) {
		if (!function_exists('openssl_encrypt')) { throw new RuntimeException('PHP OpenSSL is required.'); }
		$iv = random_bytes(12);
		$tag = '';
		$cipher = openssl_encrypt($secret, 'aes-256-gcm', hash('sha256', wp_salt('auth'), true), OPENSSL_RAW_DATA, $iv, $tag);
		if ($cipher === false) { throw new RuntimeException('Unable to encrypt credentials.'); }
		return base64_encode($iv . $tag . $cipher);
	}
	public static function unseal($value) {
		$raw = base64_decode($value, true);
		if (!function_exists('openssl_decrypt') || $raw === false || strlen($raw) < 29) { throw new RuntimeException('Enter the Koha client secret again.'); }
		$secret = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', hash('sha256', wp_salt('auth'), true), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
		if ($secret === false) { throw new RuntimeException('Cannot decrypt credentials; re-enter the secret after a WordPress salt change.'); }
		return $secret;
	}
	public static function validate_url($url) {
		$p = wp_parse_url($url);
		if (!$p || ($p['scheme'] ?? '') !== 'https' || empty($p['host']) || isset($p['user']) || isset($p['pass']) || isset($p['query']) || isset($p['fragment']) || !preg_match('~/api/v1$~', $p['path'] ?? '') || !wp_http_validate_url($url)) {
			throw new RuntimeException('Use a public HTTPS Koha API URL ending in /api/v1, without credentials or query parameters.');
		}
	}
	private function validate($s) {
		self::validate_url($s['url']);
		foreach (['client_id', 'secret', 'library', 'category'] as $key) {
			if (empty($s[$key])) { throw new RuntimeException('Client ID, secret, library code and patron category code are required.'); }
		}
		if (strlen($s['library']) > 10 || strlen($s['category']) > 10) { throw new RuntimeException('Koha library and category codes must be at most 10 characters.'); }
		if (!$s['levels']) { throw new RuntimeException('Select the PMPro membership levels that receive library access.'); }
		self::unseal($s['secret']);
		$this->membership_table();
	}
	private function membership_table() {
		global $wpdb;
		if (!function_exists('pmpro_getAllLevels') || empty($wpdb->pmpro_memberships_users)) { throw new RuntimeException('PMPro is unavailable; no patrons were changed.'); }
		return $wpdb->pmpro_memberships_users;
	}
	private function lock() {
		global $wpdb;
		return (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', 'aac_koha_' . md5(self::table()))) === 1;
	}
	private function unlock() {
		global $wpdb;
		$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', 'aac_koha_' . md5(self::table())));
	}
	private function db_check() { global $wpdb; if ($wpdb->last_error) { throw new RuntimeException('Database read/write failed; sync paused safely. Check database health.'); } }
	private function log($message, $user_id = 0) {
		$logs = (array) get_option('aac_koha_logs', []);
		array_unshift($logs, ['time' => gmdate('Y-m-d H:i:s') . ' UTC', 'user' => (int) $user_id, 'message' => $message]);
		update_option('aac_koha_logs', array_slice($logs, 0, 100), false);
	}
	protected function persist($id, $state) {
		global $wpdb;
		$wpdb->replace(self::table(), ['user_id' => $id, 'state' => wp_json_encode($state)], ['%d', '%s']);
		$this->db_check();
	}

	public function handle_admin() {
		if (!current_user_can('manage_options')) { wp_die('Not allowed.'); }
		check_admin_referer('aac_koha');
		$action = sanitize_key(wp_unslash($_POST['operation'] ?? ''));
		if ($action === 'run') {
			$this->run();
		} else {
			if (!$this->lock()) { wp_die('Koha sync is running. Please try again shortly.'); }
			try {
				$s = $this->settings();
				if ($action === 'save') {
					$old = $s;
					foreach (['url', 'client_id', 'library', 'category'] as $key) { $s[$key] = sanitize_text_field(wp_unslash($_POST[$key] ?? '')); }
					$s['url'] = rtrim($s['url'], '/');
					if ($old['baseline'] !== null && $s['url'] !== $old['url']) { throw new RuntimeException('The Koha endpoint is locked after first activation to protect patron mappings. Contact your developer to migrate to another Koha instance.'); }
					$secret = (string) wp_unslash($_POST['secret'] ?? '');
					if ($secret !== '') { $s['secret'] = self::seal($secret); }
					$s['levels'] = array_values(array_filter(array_unique(array_map('absint', (array) ($_POST['levels'] ?? [])))));
					foreach (['enabled', 'backfill', 'adopt_existing'] as $key) { $s[$key] = empty($_POST[$key]) ? 0 : 1; }
					if ($s['enabled']) {
						$this->validate($s);
						if ($old['site'] && $old['site'] !== home_url()) { throw new RuntimeException('This appears to be a cloned WordPress site. Sync is blocked to prevent two sites managing the same Koha patrons.'); }
						if ($s['baseline'] === null) {
							global $wpdb;
							$table = $this->membership_table();
							$s['baseline'] = (int) $wpdb->get_var("SELECT COALESCE(MAX(id),0) FROM {$table}");
							$this->db_check();
							$s['site'] = home_url();
						}
					}
					update_option(self::OPTION, $s, false);
					self::deactivate();
					delete_option('aac_koha_cursor');
					$this->init();
					$this->log('Settings saved. ' . ($s['enabled'] ? 'Sync enabled.' : 'Sync disabled.'));
				} elseif ($action === 'test') {
					$this->validate($s);
					$this->test_connection($s);
					$this->log('Connection, library code, category code and patron read access verified. Write permissions still require a sandbox patron test.');
				}
			} catch (Throwable $e) { $this->log($e->getMessage()); }
			finally { $this->unlock(); }
		}
		wp_safe_redirect(admin_url('admin.php?page=' . self::MENU_SLUG)); exit;
	}
	public function test_connection($s) {
		$library = $this->request($s, 'GET', '/libraries/' . rawurlencode($s['library']));
		if (($library['library_id'] ?? '') !== $s['library']) { throw new RuntimeException('Koha library code was not verified.'); }
		// Categories have a collection route, not a /patron_categories/{id} route.
		$categories = $this->request($s, 'GET', '/patron_categories?' . http_build_query(['q' => wp_json_encode(['patron_category_id' => $s['category']]), '_per_page' => 2]));
		if (count($categories) !== 1 || ($categories[0]['patron_category_id'] ?? '') !== $s['category']) { throw new RuntimeException('Koha patron category code was not verified.'); }
		$result = $this->request($s, 'GET', '/patrons?_per_page=1');
		if (!is_array($result) || (count($result) && !isset($result[0]['patron_id']))) { throw new RuntimeException('Unexpected patron-list response.'); }
	}

	/** Never follow redirects with credentials. No raw API bodies or tokens enter logs. */
	private function http($s, $method, $path, $body = null, $token = '') {
		self::validate_url($s['url']);
		$args = ['method' => $method, 'timeout' => 12, 'redirection' => 0, 'sslverify' => true, 'limit_response_size' => 1048576, 'headers' => ['Accept' => 'application/json']];
		if ($token !== '') { $args['headers']['Authorization'] = 'Bearer ' . $token; }
		if ($body !== null) {
			$args['body'] = $path === '/oauth/token' ? $body : wp_json_encode($body);
			$args['headers']['Content-Type'] = $path === '/oauth/token' ? 'application/x-www-form-urlencoded' : 'application/json';
		}
		$response = wp_safe_remote_request($s['url'] . $path, $args);
		if (is_wp_error($response)) { throw new RuntimeException('Koha network request failed; outcome may be unknown. It will be reconciled on retry.'); }
		$code = (int) wp_remote_retrieve_response_code($response);
		if ($code < 200 || $code >= 300) { throw new RuntimeException('Koha HTTP ' . $code . ' during ' . $method . ' ' . strtok($path, '?') . '. Check credentials, permissions, codes or Koha server logs.', $code); }
		if ($code === 204) { return []; }
		$data = json_decode(wp_remote_retrieve_body($response), true);
		if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) { throw new RuntimeException('Koha returned invalid JSON. No successful sync was recorded.'); }
		return $data;
	}
	protected function request($s, $method, $path, $body = null) {
		if (!$this->token || time() >= $this->token_until) {
			$auth = $this->http($s, 'POST', '/oauth/token', ['grant_type' => 'client_credentials', 'client_id' => $s['client_id'], 'client_secret' => self::unseal($s['secret'])]);
			if (empty($auth['access_token'])) { throw new RuntimeException('Koha did not return an access token.'); }
			$this->token = $auth['access_token'];
			$this->token_until = time() + max(1, (int) ($auth['expires_in'] ?? 60) - 10);
		}
		try { return $this->http($s, $method, $path, $body, $this->token); }
		catch (Throwable $e) { if ($e->getCode() === 401) { $this->token = ''; } throw $e; }
	}

	/** Eligibility comes from current PMPro rows, never cached display snapshots or billing status. */
	public static function entitlement($rows, $levels, $today) {
		$result = ['active' => false, 'expiry' => '', 'row_id' => 0];
		$unlimited = false;
		foreach ($rows as $row) {
			if ($row['status'] !== 'active' || !in_array((int) $row['membership_id'], $levels, true)) { continue; }
			$start = substr((string) ($row['startdate'] ?? ''), 0, 10);
			$end = substr((string) ($row['enddate'] ?? ''), 0, 10);
			if ($start > $today || ($end && $end !== '0000-00-00' && $end < $today)) { continue; }
			$result['active'] = true;
			$result['row_id'] = max($result['row_id'], (int) $row['id']);
			if (!$end || $end === '0000-00-00') { $unlimited = true; }
			else { $result['expiry'] = max($result['expiry'], $end); }
		}
		// No-end-date memberships get a rolling seven-day lease, refreshed daily while active.
		if ($result['active'] && $unlimited) { $result['expiry'] = max($result['expiry'], gmdate('Y-m-d', strtotime($today . ' +7 days'))); }
		return $result;
	}
	protected function identity($id) {
		$user = get_userdata($id);
		$card = trim((string) get_user_meta($id, 'aac_member_id', true));
		if (!$user || !preg_match('/^[0-9]{1,32}$/D', $card)) { throw new RuntimeException('A valid AAC member number is required; WordPress ID will not be substituted.'); }
		$duplicates = get_users(['meta_key' => 'aac_member_id', 'meta_value' => $card, 'fields' => 'ids', 'number' => 2]);
		if (count($duplicates) !== 1 || (int) $duplicates[0] !== (int) $id) { throw new RuntimeException('Duplicate AAC member number; resolve it before syncing.'); }
		$last = trim((string) get_user_meta($id, 'last_name', true));
		if (!$last || !is_email($user->user_email)) { throw new RuntimeException('Last name and a valid email address are required.'); }
		return ['cardnumber' => $card, 'userid' => 'aac-member-' . $card, 'surname' => $last, 'firstname' => (string) get_user_meta($id, 'first_name', true), 'email' => $user->user_email];
	}

	/** Existing matches require explicit adoption unless they bear this integration's deterministic login. */
	public function sync_member($s, $id, $desired, &$state) {
		$active = $desired['active'];
		$previous = $state['observed'] ?? null;
		$state['observed'] = $active;
		if ($active && ($s['backfill'] || $desired['row_id'] > $s['baseline'] || $previous === false)) { $state['eligible'] = true; }
		if (empty($state['patron_id']) && (empty($state['eligible']) || !$active) && empty($state['pending_create'])) { return 'Skipped (not enrolled)'; }
		$expiry = $active ? $desired['expiry'] : ($state['inactive_expiry'] ?? gmdate('Y-m-d', strtotime(current_time('Y-m-d') . ' -1 day')));
		if (!$active) { $state['inactive_expiry'] = $expiry; } else { unset($state['inactive_expiry']); }
		$signature = ($active ? 'active:' : 'inactive:') . $expiry;
		if (!empty($state['patron_id']) && ($state['synced'] ?? '') === $signature) { return 'Unchanged'; }
		$patron = null;
		if (!empty($state['patron_id'])) {
			$patron = $this->request($s, 'GET', '/patrons/' . (int) $state['patron_id']);
			if ((string) ($patron['cardnumber'] ?? '') !== (string) $state['cardnumber']) { throw new RuntimeException('Linked Koha card number changed; manual review required.'); }
		} else {
			$identity = !empty($state['pending_create']) ? $state['pending_create'] : $this->identity($id);
			$matches = $this->request($s, 'GET', '/patrons?' . http_build_query(['q' => wp_json_encode(['cardnumber' => $identity['cardnumber']]), '_per_page' => 2]));
			if (count($matches) > 1) { throw new RuntimeException('Multiple Koha patrons match this member number; manual review required.'); }
			if ($matches) {
				$patron = reset($matches);
				$own = ($patron['userid'] ?? '') === $identity['userid'];
				$verified = strcasecmp(trim((string) ($patron['email'] ?? '')), $identity['email']) === 0 && strcasecmp(trim((string) ($patron['surname'] ?? '')), $identity['surname']) === 0;
				if (($patron['cardnumber'] ?? '') !== $identity['cardnumber'] || !$verified || (!$own && !$s['adopt_existing'])) { throw new RuntimeException('Existing Koha patron requires verified adoption or manual review. No duplicate was created.'); }
			} elseif (!$active) {
				unset($state['pending_create']);
				return 'No patron to expire';
			} else {
				// Durable intent lets a timed-out POST be found and expired even if membership ends before retry.
				$state['pending_create'] = $identity;
				$this->persist($id, $state);
				$patron = $this->request($s, 'POST', '/patrons', array_merge($identity, ['library_id' => $s['library'], 'category_id' => $s['category'], 'expiry_date' => $expiry]));
			}
			if (empty($patron['patron_id']) || (string) ($patron['cardnumber'] ?? '') !== $identity['cardnumber']) { throw new RuntimeException('Koha did not return a matching patron ID/card; retry will look up the member number.'); }
			$state['patron_id'] = (int) $patron['patron_id'];
			$state['cardnumber'] = $identity['cardnumber'];
			unset($state['pending_create']);
			$this->persist($id, $state);
		}
		if (($patron['expiry_date'] ?? '') !== $expiry) {
			// Koha's PUT schema requires these three fields. Preserve their remote values.
			foreach (['surname', 'library_id', 'category_id'] as $field) { if (!isset($patron[$field])) { throw new RuntimeException('Koha patron fields are missing or redacted; no update made.'); } }
			$this->request($s, 'PUT', '/patrons/' . (int) $state['patron_id'], ['surname' => $patron['surname'], 'library_id' => $patron['library_id'], 'category_id' => $patron['category_id'], 'expiry_date' => $expiry]);
		}
		$state['synced'] = $signature;
		return $active ? 'Patron active; expiry ' . $expiry : 'Patron expired; loans and history preserved';
	}

	public function run() {
		global $wpdb;
		$s = $this->settings();
		if (!$s['enabled']) { return; }
		if (!$this->lock()) { return; }
		$start = microtime(true);
		try {
			if ($s['site'] !== home_url()) { throw new RuntimeException('Site URL changed or cloned: sync blocked.'); }
			$this->validate($s);
			$table = $this->membership_table(); $ledger = self::table();
			$cursor = (int) get_option('aac_koha_cursor', 0);
			$ids = $wpdb->get_col($wpdb->prepare("SELECT user_id FROM (SELECT user_id FROM {$table} UNION SELECT user_id FROM {$ledger}) members WHERE user_id > %d ORDER BY user_id LIMIT 250", $cursor));
			$this->db_check();
			$processed = 0;
			foreach ($ids as $id) {
				if (microtime(true) - $start > 18) { break; }
				$id = (int) $id;
				$rows = $wpdb->get_results($wpdb->prepare("SELECT id,membership_id,status,startdate,enddate FROM {$table} WHERE user_id=%d", $id), ARRAY_A);
				$this->db_check();
				$raw = $wpdb->get_var($wpdb->prepare("SELECT state FROM {$ledger} WHERE user_id=%d", $id));
				$this->db_check();
				$state = $raw ? json_decode($raw, true) : [];
				if (!is_array($state)) { throw new RuntimeException('Invalid Koha ledger data; sync paused.'); }
				$desired = self::entitlement($rows, $s['levels'], current_time('Y-m-d'));
				if (!get_userdata($id)) { $desired['active'] = false; }
				try {
					$message = $this->sync_member($s, $id, $desired, $state);
					unset($state['error']);
					if (!in_array($message, ['Unchanged', 'Skipped (not enrolled)'], true)) { $this->log($message, $id); }
				} catch (Throwable $e) {
					$state['error'] = $e->getMessage();
					$this->log($state['error'], $id);
					// Preserve progress but stop on authentication or server-wide availability failures.
					if (in_array($e->getCode(), [401, 403, 429, 500, 502, 503, 504], true)) { $this->persist($id, $state); throw $e; }
				}
				$this->persist($id, $state);
				update_option('aac_koha_cursor', $id, false);
				$processed++;
			}
			if (count($ids) === 250 || $processed < count($ids)) {
				if (!wp_next_scheduled(self::BATCH)) { wp_schedule_single_event(time() + 60, self::BATCH); }
			} else {
				delete_option('aac_koha_cursor');
				update_option('aac_koha_last_scan', gmdate('Y-m-d H:i:s') . ' UTC', false);
				$this->log('Reconciliation complete. Member failures are retained and retried next cycle.');
			}
		} catch (Throwable $e) { $this->log('Scan paused: ' . $e->getMessage()); }
		finally { $this->unlock(); }
	}

	public function render() {
		if (!current_user_can('manage_options')) { return; }
		$s = $this->settings();
		$levels = function_exists('pmpro_getAllLevels') ? pmpro_getAllLevels(true, true) : [];
		?><div class="wrap"><h1>Koha Library Sync</h1>
		<?php AAC_Member_Portal_Member_Database::render_database_tools_nav(self::MENU_SLUG); ?>
		<p>Create Koha patrons for newly active AAC members and expire managed patrons when membership becomes inactive. No patrons, loans or borrowing history are deleted.</p>
		<p><strong>Status:</strong> <?php echo $s['enabled'] && $s['site'] === home_url() ? 'Enabled' : 'Disabled / not configured'; ?> · <strong>Last completed scan:</strong> <?php echo esc_html(get_option('aac_koha_last_scan', 'Not run')); ?> · <strong>Next run:</strong> <?php $next = wp_next_scheduled(self::BATCH) ?: wp_next_scheduled(self::CRON); echo esc_html($next ? gmdate('Y-m-d H:i:s', $next) . ' UTC' : 'Not scheduled'); ?></p>
		<form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
		<input type="hidden" name="action" value="aac_koha"><input type="hidden" name="operation" value="save"><?php wp_nonce_field('aac_koha'); ?>
		<table class="form-table"><tbody>
		<?php foreach (['url' => 'Koha API URL (https://your-koha-host/api/v1)', 'client_id' => 'OAuth client ID', 'library' => 'Library code', 'category' => 'Patron category code'] as $key => $label) : ?>
		<tr><th><label for="koha-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th><td><input class="regular-text" id="koha-<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($s[$key]); ?>" type="text" autocomplete="off"></td></tr>
		<?php endforeach; ?>
		<tr><th><label for="koha-secret">OAuth client secret</label></th><td><input class="regular-text" type="password" id="koha-secret" name="secret" value="" autocomplete="new-password"><p class="description"><?php echo $s['secret'] ? 'A secret is stored. Leave blank to keep it.' : 'Not configured.'; ?> Encrypted using WordPress salts. Re-enter if salts change.</p></td></tr>
		<tr><th>Eligible membership levels</th><td><?php foreach ($levels as $level) : ?><label style="display:block"><input type="checkbox" name="levels[]" value="<?php echo (int) $level->id; ?>" <?php checked(in_array((int) $level->id, $s['levels'], true)); ?>> <?php echo esc_html($level->name); ?></label><?php endforeach; ?><p class="description">Select all levels entitled to library access, including child levels if applicable. Removing a level expires managed patrons with no other eligible active level.</p></td></tr>
		<tr><th>Enrollment</th><td><label><input type="checkbox" name="backfill" value="1" <?php checked($s['backfill']); ?>> Include existing active members</label><p class="description">Off: enroll new PMPro membership records after first activation and observed inactive-to-active transitions. On: also enroll all existing eligible active members.</p>
		<label><input type="checkbox" name="adopt_existing" value="1" <?php checked($s['adopt_existing']); ?>> Allow matching existing Koha patrons</label><p class="description">Requires exact AAC card number, matching email and last name. Otherwise flag for manual review. Previously created AAC patrons are recovered automatically after interrupted requests.</p></td></tr>
		<tr><th>Automatic sync</th><td><label><input type="checkbox" name="enabled" value="1" <?php checked($s['enabled']); ?>> Enable every-two-hour sync</label><p class="description">Requires credentials, codes and eligible levels. WP-Cron runs on traffic; configure a server cron for dependable timing. Site clones are blocked automatically.</p></td></tr>
		</tbody></table><?php submit_button('Save Koha settings'); ?></form>
		<?php foreach (['test' => 'Test connection (read only)', 'run' => 'Run sync now (writes to Koha)'] as $op => $label) : ?>
		<form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" style="display:inline-block;margin-right:12px"><input type="hidden" name="action" value="aac_koha"><input type="hidden" name="operation" value="<?php echo esc_attr($op); ?>"><?php wp_nonce_field('aac_koha'); submit_button($label, 'secondary', 'submit', false, $op === 'run' && !$s['enabled'] ? ['disabled' => 'disabled'] : []); ?></form>
		<?php endforeach; ?>
		<h2>How it works</h2><ul style="list-style:disc;padding-left:24px"><li>AAC member number → Koha card number; login is aac-member-NUMBER. Name and email are sent on creation. WordPress passwords, payment details and contact consent are never sent.</li><li>Active PMPro access, not auto-renew status, controls eligibility. A term ending in the future stays active when auto-renew is turned off.</li><li>Koha expiry follows the membership end date. Active memberships without an end date receive a rolling seven-day expiry, refreshed daily. Inactive patrons expire as of yesterday. Existing library restrictions remain unchanged.</li><li>Use a Koha API service account with RESTOAuth2ClientCredentials enabled and patron list/edit permissions (plus access to library/category endpoints for the connection test). Do not grant deletion permission. Confirm your Koha version and library expiration rules with library staff before enabling.</li><li>No password is copied or assigned. Arrange Koha password-reset/welcome procedures with library staff.</li></ul>
		<h2>Recent sync activity</h2><p>Latest 100 events. Member failures retry each reconciliation; credentials and raw API responses are never logged.</p>
		<table class="widefat striped"><thead><tr><th>Time</th><th>WordPress user ID</th><th>Result</th></tr></thead><tbody><?php foreach ((array) get_option('aac_koha_logs', []) as $log) : ?><tr><td><?php echo esc_html($log['time']); ?></td><td><?php echo (int) $log['user']; ?></td><td><?php echo esc_html($log['message']); ?></td></tr><?php endforeach; ?></tbody></table></div><?php
	}
}
