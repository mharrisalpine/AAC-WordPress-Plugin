<?php
// Exercise the production guard without WordPress, accounts or payments.
$source = file_get_contents(__DIR__ . '/../wordpress/aac-member-portal/aac-member-portal.php');
$start = strpos($source, "\tpublic function guard_signed_in_public_signup()");
$end = strpos($source, "\tpublic function enqueue_checkout_security()", $start);
$method = substr($source, $start, $end - $start);
function is_user_logged_in() { return $GLOBALS['signed_in']; }
function nocache_headers() {}
function wp_get_current_user() { return (object) ['display_name' => '<Test Member>', 'user_email' => 'test@example.com']; }
function untrailingslashit($s) { return rtrim($s, '/'); }
function esc_html($s) { return htmlspecialchars($s); }
function esc_url($s) { return htmlspecialchars($s); }
function wp_logout_url($s) { return '/logout?redirect=' . urlencode($s); }
function wp_die($message, $title, $args) { throw new RuntimeException($message); }
eval('class SignupGuardHarness {
  public $checkout = true;
  private function is_pmpro_checkout_request() { return $this->checkout; }
  private function get_portal_page_url() { return "https://example.com/member-profile/"; }
' . $method . '}');
$guard = new SignupGuardHarness();
$cases = [
  [false, true, '1', false], // guest signup remains available
  [true, true, '1', true], // signed-in public signup blocked
  [true, true, null, false], // existing-member renewal untouched
  [true, false, '1', false], // unrelated page untouched
];
foreach ($cases as [$signed_in, $checkout, $signup, $expect_block]) {
  $GLOBALS['signed_in'] = $signed_in;
  $guard->checkout = $checkout;
  $_REQUEST = $signup === null ? [] : ['aac_signup' => $signup];
  $blocked = false;
  try { $guard->guard_signed_in_public_signup(); }
  catch (RuntimeException $e) {
    $blocked = true;
    foreach (['&lt;Test Member&gt;', 'Go to your account', 'Sign out to continue.', '/#/profile'] as $text) {
      if (!str_contains($e->getMessage(), $text)) throw new Exception('Missing safe notice content: ' . $text);
    }
  }
  if ($blocked !== $expect_block) throw new Exception('Unexpected guard result');
}
echo "PASS: guest signup, signed-in signup block, renewal, unrelated pages, escaped identity and account/sign-out links.\n";
