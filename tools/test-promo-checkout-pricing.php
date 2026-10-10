<?php
// Run the production pricing filter with isolated WordPress/context dependencies.
$source = file_get_contents(__DIR__ . '/../wordpress/aac-member-portal/aac-member-portal.php');
$code = '';
foreach (['get_validated_promo_checkout_totals', 'filter_pmpro_checkout_level_for_magazine_addons'] as $method) {
    preg_match('/\n\t(?:public|private) function ' . $method . '\(/', $source, $match, PREG_OFFSET_CAPTURE);
    $start = $match[0][1];
    preg_match('/\n\t(?:public|private|protected) (?:static )?function /', $source, $next, PREG_OFFSET_CAPTURE, $start + strlen($match[0][0]));
    $code .= substr($source, $start, $next[0][1] - $start);
}
function sanitize_text_field($s) { return $s; }
function wp_unslash($s) { return $s; }
eval('class PromoPricingHarness {
    public function __call($name, $args) {
        switch ($name) {
            case "get_aac_membership_level_base_total": return 45.0;
            case "get_level_checkout_initial_total": return (float) $args[0]->initial_payment;
            case "get_level_recurring_total": return 45.0;
            case "get_requested_partner_family_config":
            case "normalize_partner_family_config": return ["mode" => "individual"];
            case "get_requested_immediate_upgrade_checkout_level":
            case "get_country_routed_partner_level_for_checkout":
            case "get_prorated_upgrade_initial_total_for_checkout":
            case "get_autorenew_reactivation_checkout_context":
            case "get_add_dependent_checkout_context": return null;
			case "get_prepaid_renewal_checkout_context": return !empty($_REQUEST["aac_test_prepaid_renewal"]) ? ["renewal_amount" => 45.0] : null;
            case "is_international_checkout_request":
            case "supports_family_plan_tiers":
            case "should_preserve_prorated_checkout_initial_total":
            case "is_checkout_autorenew_disabled_request": return false;
            case "get_partner_family_addon_total":
            case "get_international_print_surcharge_amount": return 0.0;
            case "get_requested_membership_discount_type": return $_REQUEST["aac_membership_discount"] ?? "";
            case "get_membership_discount_code": return ($args[0] ?? "") === "student" ? "STUDENT" : (($args[0] ?? "") === "military" ? "MILITARY" : "");
            case "get_requested_membership_discount_amount": return ($_REQUEST["aac_membership_discount"] ?? "") === "student" ? round((float) $args[0] * 0.35, 2) : 0.0;
            case "get_checkout_account_info_from_request": return [];
            default: throw new RuntimeException("Unexpected dependency: " . $name);
        }
    }
' . $code . '}');
function check($actual, $expected, $message) {
    if (abs((float) $actual - (float) $expected) > 0.00001) throw new RuntimeException($message . ': ' . var_export($actual, true));
}
$h = new PromoPricingHarness();
$_REQUEST = ['aac_signup' => '1', 'pmpro_discount_code' => 'teststripe', 'aac_membership_discount' => 'student'];
$base = (object) ['id' => 11, 'initial_payment' => 45.0, 'billing_amount' => 45.0];
$promo = clone $base;
$promo->code_id = 1;
$promo->discount_code = 'teststripe';
$promo->initial_payment = $promo->billing_amount = 1.0;
$result = $h->filter_pmpro_checkout_level_for_magazine_addons($promo);
check($result->initial_payment, 1.0, 'Valid promo initial amount');
check($result->billing_amount, 1.0, 'Valid promo recurring amount');

unset($_REQUEST['aac_membership_discount']);
// Simulate Donations (priority 99) adding $5 after AAC (priority 20).
$result->initial_payment += 5;
$result = $h->filter_pmpro_checkout_level_for_magazine_addons($result);
check($result->initial_payment + 5, 6.0, 'Repeated pass adds donation only once');
$result = $h->filter_pmpro_checkout_level_for_magazine_addons(clone $base);
check($result->initial_payment, 45.0, 'Request code alone cannot discount pricing');
check($result->billing_amount, 45.0, 'Invalid code does not discount renewals');
unset($_REQUEST['pmpro_discount_code']);
$result = $h->filter_pmpro_checkout_level_for_magazine_addons(clone $base);
check($result->initial_payment, 45.0, 'Removing promo restores catalog');

$_REQUEST = ['aac_signup' => '1', 'aac_membership_discount' => 'student'];
$studentPromo = clone $base;
$studentPromo->code_id = 2;
$studentPromo->discount_code = 'STUDENT';
$studentPromo->initial_payment = $studentPromo->billing_amount = 29.25;
$result = $h->filter_pmpro_checkout_level_for_magazine_addons($studentPromo);
check($result->initial_payment, 29.25, 'Student PMPro promo is not discounted twice');
check($result->billing_amount, 29.25, 'Student PMPro recurring promo is not discounted twice');

$_REQUEST = ['aac_signup' => '1', 'aac_membership_discount' => 'military'];
$militaryPromo = clone $base;
$militaryPromo->code_id = 3;
$militaryPromo->discount_code = 'MILITARY';
$militaryPromo->initial_payment = $militaryPromo->billing_amount = 29.25;
$result = $h->filter_pmpro_checkout_level_for_magazine_addons($militaryPromo);
check($result->initial_payment, 29.25, 'Military PMPro promo is not discounted twice');
check($result->billing_amount, 29.25, 'Military PMPro recurring promo is not discounted twice');

$_REQUEST = ['aac_signup' => '1', 'aac_membership_discount' => 'student'];
$result = $h->filter_pmpro_checkout_level_for_magazine_addons(clone $base);
check($result->initial_payment, 45.0, 'Unvalidated Student code cannot alter pricing');
check($result->billing_amount, 45.0, 'Unvalidated Student code cannot alter renewal pricing');

$_REQUEST = [
	'aac_test_prepaid_renewal' => '1',
];
$result = $h->filter_pmpro_checkout_level_for_magazine_addons(clone $base);
check($result->initial_payment, 45.0, 'Manual non-auto-renew renewal charges membership only');
check($result->billing_amount, 0.0, 'Manual non-auto-renew renewal does not create a recurring charge');

$_REQUEST = [];
foreach ([[0.0, 45.0], [1.0, 45.0], [1.0, 0.0]] as [$initial, $recurring]) {
    $level = clone $base;
    $level->code_id = 2;
    $level->discount_code = 'OTHER';
    $level->initial_payment = $initial;
    $level->billing_amount = $recurring;
    $result = $h->filter_pmpro_checkout_level_for_magazine_addons($level);
    check($result->initial_payment, $initial, 'Zero and first-payment-only promos');
    check($result->billing_amount, $recurring, 'Distinct recurring promo amount preserved');
}
echo "PASS: Supporter 45 to 1, renewal pricing, repeat donation pass, unvalidated code, code removal, free and initial-only discounts.\n";

// The visible PMPro promo field must survive final form submission under the
// key read by pmpro_getLevelAtCheckout(), and it must take precedence over an
// AAC eligibility discount selected earlier in the same checkout.
$requestSource = file_get_contents(__DIR__ . '/../wordpress/aac-member-portal/aac-member-portal.php');
$requestCode = '';
foreach (['maybe_apply_submitted_pmpro_discount_code_to_request', 'maybe_apply_membership_discount_code_to_request'] as $method) {
    preg_match('/\n\tpublic function ' . $method . '\(/', $requestSource, $match, PREG_OFFSET_CAPTURE);
    $start = $match[0][1];
    preg_match('/\n\t(?:public|private|protected) (?:static )?function /', $requestSource, $next, PREG_OFFSET_CAPTURE, $start + strlen($match[0][0]));
    $requestCode .= substr($requestSource, $start, $next[0][1] - $start);
}
eval('class PromoRequestHarness {
    private function is_pmpro_checkout_request() { return true; }
    private function get_uppercase_request_value($key) { return isset($_REQUEST[$key]) ? strtoupper(sanitize_text_field(wp_unslash($_REQUEST[$key]))) : ""; }
    private function has_membership_discount_request() { return !empty($_REQUEST["aac_membership_discount"]); }
    private function get_level_at_checkout() { return (object) ["id" => 11]; }
    private function supports_discount_tiers($level) { return true; }
    private function has_partner_family_request() { return false; }
    private function normalize_partner_family_config($config) { return ["mode" => "individual"]; }
    private function get_requested_membership_discount_type() { return $_REQUEST["aac_membership_discount"] ?? ""; }
    private function get_membership_discount_code($type) { return $type === "student" ? "STUDENT" : ($type === "military" ? "MILITARY" : ""); }
' . $requestCode . '}');
function is_admin() { return false; }
$_SERVER['REQUEST_METHOD'] = 'POST';
$_REQUEST = $_POST = [
    'pmpro_other_discount_code' => 'teststripe',
    'aac_membership_discount' => 'student',
];
$requestHarness = new PromoRequestHarness();
$requestHarness->maybe_apply_submitted_pmpro_discount_code_to_request();
$requestHarness->maybe_apply_membership_discount_code_to_request();
if (($_POST['pmpro_discount_code'] ?? '') !== 'TESTSTRIPE') {
    throw new RuntimeException('Separate PMPro promo was not preserved for authoritative checkout pricing.');
}
if (($_POST['discount_code'] ?? '') !== 'TESTSTRIPE') {
    throw new RuntimeException('PMPro promo was not copied to the legacy checkout key.');
}
echo "PASS: submitted PMPro promo is normalized server-side and takes precedence over AAC eligibility discounts.\n";

$_REQUEST = $_POST = ['aac_membership_discount' => 'military'];
$requestHarness->maybe_apply_membership_discount_code_to_request();
if (($_POST['pmpro_discount_code'] ?? '') !== 'MILITARY') {
    throw new RuntimeException('Military eligibility did not submit the MILITARY PMPro promo code.');
}
echo "PASS: Military eligibility submits the MILITARY PMPro promo code.\n";
