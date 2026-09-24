<?php
/** Offline contract tests. No WordPress installation, credentials or external requests. */
define('ABSPATH', __DIR__ . '/');
function wp_salt($scheme) { return 'test-only-salt-never-used-for-real-credentials'; }
function wp_json_encode($value) { return json_encode($value); }
function current_time($format) { return '2026-09-17'; }
function wp_parse_url($url) { return parse_url($url); }
function wp_http_validate_url($url) { return strpos($url, 'https://koha.example.org/') === 0; }
require_once __DIR__ . '/../wordpress/aac-member-portal/includes/class-aac-member-portal-koha-sync.php';
function expect($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
function fails($fn, $message) { try { $fn(); } catch (RuntimeException $e) { return; } throw new RuntimeException($message); }
class KohaHarness extends AAC_Member_Portal_Koha_Sync {
	public $calls = [], $responses = [], $saved = [];
	public function __construct() {}
	protected function identity($id) { return ['cardnumber' => '131889', 'userid' => 'aac-member-131889', 'firstname' => 'Test', 'surname' => 'Member', 'email' => 'test@example.com']; }
	protected function persist($id, $state) { $this->saved[$id] = $state; }
	protected function request($s, $method, $path, $body = null) {
		$this->calls[] = [$method, $path, $body];
		if (!$this->responses) { throw new LogicException('Unexpected request ' . $method . ' ' . $path); }
		$result = array_shift($this->responses);
		if ($result instanceof Throwable) { throw $result; }
		return $result;
	}
}
$s = ['baseline' => 100, 'backfill' => 0, 'adopt_existing' => 0, 'library' => 'AAC', 'category' => 'MEMBER'];
$test = new KohaHarness();
$test->responses = [['library_id'=>'AAC'], [['patron_category_id'=>'MEMBER']], []];
$test->test_connection($s);
expect(strpos($test->calls[1][1], '/patron_categories?') === 0, 'Category validation uses collection query, not nonexistent item route');
expect(count(array_filter($test->calls, fn($call) => $call[0] !== 'GET')) === 0, 'Connection test is read-only');
$test->responses = [['library_id'=>'AAC'], []];
fails(fn() => $test->test_connection($s), 'Unknown category must fail connection test');
$active = ['active' => true, 'expiry' => '2027-09-30', 'row_id' => 101];
$inactive = ['active' => false, 'expiry' => '', 'row_id' => 0];
$patron = ['patron_id' => 12, 'cardnumber' => '131889', 'userid' => 'aac-member-131889', 'surname' => 'Member', 'email' => 'test@example.com', 'library_id' => 'AAC', 'category_id' => 'MEMBER', 'expiry_date' => '2027-09-30'];

$cipher = AAC_Member_Portal_Koha_Sync::seal('secret-value');
expect(strpos($cipher, 'secret-value') === false, 'Secret must not be plaintext');
expect(AAC_Member_Portal_Koha_Sync::unseal($cipher) === 'secret-value', 'Encrypted secret roundtrip');
$raw = base64_decode($cipher); $raw[29] = chr(ord($raw[29]) ^ 1);
fails(fn() => AAC_Member_Portal_Koha_Sync::unseal(base64_encode($raw)), 'Tampering must fail');
foreach (['http://koha.example.org/api/v1', 'https://user:pass@koha.example.org/api/v1', 'https://koha.example.org/api/v1?x=y', 'https://127.0.0.1/api/v1', 'https://koha.example.org/opac'] as $url) {
	fails(fn() => AAC_Member_Portal_Koha_Sync::validate_url($url), 'Unsafe URL accepted');
}
AAC_Member_Portal_Koha_Sync::validate_url('https://koha.example.org/api/v1');

$row = ['id' => 101, 'membership_id' => 2, 'status' => 'active', 'startdate' => '2026-01-01', 'enddate' => '2027-09-30 23:59:59'];
$ent = fn($rows) => AAC_Member_Portal_Koha_Sync::entitlement($rows, [2,3], '2026-09-17');
expect($ent([$row]) === $active, 'Active membership with term');
expect(!$ent([array_merge($row, ['status' => 'cancelled'])])['active'], 'Cancelled historical rows cannot reactivate access');
expect(!$ent([array_merge($row, ['enddate' => '2026-09-16'])])['active'], 'Natural expiry detected without hook');
expect($ent([array_merge($row, ['enddate' => '2026-09-17'])])['active'], 'Membership valid through last day');
expect(!$ent([array_merge($row, ['membership_id' => 1])])['active'], 'Noneligible/free level excluded');
expect(!$ent([array_merge($row, ['startdate' => '2026-10-01'])])['active'], 'Future start not yet eligible');
expect($ent([array_merge($row, ['enddate' => null])])['expiry'] === '2026-09-24', 'No end date uses rolling lease');
expect($ent([$row, array_merge($row, ['id' => 102, 'membership_id' => 3, 'enddate' => '2028-01-01'])])['expiry'] === '2028-01-01', 'Multiple levels choose longest active entitlement');

$h = new KohaHarness(); $state = [];
$old = array_merge($active, ['row_id' => 99]);
$h->sync_member($s, 88, $old, $state);
expect(!$h->calls, 'No automatic historical backfill');
$h->sync_member($s, 88, $inactive, $state);
$h->responses = [[], $patron];
$h->sync_member($s, 88, $old, $state);
expect($state['patron_id'] === 12, 'Observed reactivation enrolls older member');
expect($h->calls[1][0] === 'POST', 'Creation uses POST');
expect($h->calls[1][2]['cardnumber'] === '131889', 'AAC card number not WP user ID');
expect(!isset($h->calls[1][2]['password']), 'No password transmission');
$before = count($h->calls);
$h->sync_member($s, 88, $old, $state);
expect(count($h->calls) === $before, 'Unchanged retries do not create duplicates');

$h->responses = [$patron, []];
$h->sync_member($s, 88, $inactive, $state);
$last = end($h->calls);
expect($last[0] === 'PUT' && $last[2]['expiry_date'] === '2026-09-16', 'Inactive expires yesterday, never deletes');
expect(array_keys($last[2]) === ['surname','library_id','category_id','expiry_date'], 'Update does not alter restrictions, fines or credentials');
expect($last[2]['library_id'] === 'AAC', 'Remote library preserved');
$h->responses = [array_merge($patron, ['expiry_date' => '2026-09-16']), []];
$h->sync_member($s, 88, $active, $state);
expect(end($h->calls)[0] === 'PUT' && count(array_filter($h->calls, fn($c) => $c[0] === 'POST')) === 1, 'Renewal reactivates existing patron');

$h = new KohaHarness(); $state = []; $h->responses = [[], new RuntimeException('Simulated timeout')];
fails(function() use ($h,$s,$active,&$state) { $h->sync_member($s, 88, $active, $state); }, 'Expected failed POST');
expect(isset($h->saved[88]['pending_create']), 'Create intent durable before network write');
$state = $h->saved[88]; $h->responses = [[$patron], []];
$h->sync_member($s, 88, $inactive, $state);
expect(end($h->calls)[0] === 'PUT' && $state['synced'] === 'inactive:2026-09-16', 'Unknown successful POST can be recovered and expired');

foreach ([[$patron,$patron], [array_merge($patron,['userid'=>'other-user'])], [array_merge($patron,['email'=>'other@example.com'])]] as $matches) {
	$h = new KohaHarness(); $state = []; $h->responses = [$matches];
	fails(function() use ($h,$s,$active,&$state) { $h->sync_member($s, 88, $active, $state); }, 'Unsafe adoption blocked');
	expect(count($h->calls) === 1, 'Conflict cannot issue writes');
}
$h = new KohaHarness(); $state = []; $h->responses = [[array_merge($patron,['userid'=>'existing-login'])]];
$h->sync_member(array_merge($s,['adopt_existing'=>1]), 88, $active, $state);
expect($state['patron_id'] === 12 && count($h->calls) === 1, 'Opt-in verified adoption');
$h->responses = [array_merge($patron,['cardnumber'=>'999999'])];
fails(function() use ($h,$s,$inactive,&$state) { $h->sync_member($s, 88, $inactive, $state); }, 'Changed remote card requires manual review');

$source = file_get_contents(__DIR__ . '/../wordpress/aac-member-portal/includes/class-aac-member-portal-koha-sync.php');
expect(strpos($source, "'DELETE'") === false, 'No delete request path');
expect(strpos($source, "check_admin_referer('aac_koha')") !== false, 'Admin nonce protection');
expect(strpos($source, "'redirection' => 0") !== false, 'Credential redirect protection');
expect(strpos($source, 'wp_safe_remote_request') !== false, 'SSRF-safe HTTP client');
expect(strpos($source, 'GET_LOCK') !== false, 'Cross-request concurrency lock');
echo "PASS: encryption, URL security, eligibility, term dates, enrollment, creation, expiration, reactivation, idempotency, interrupted POST recovery, duplicate/adoption safeguards.\n";
