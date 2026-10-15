<?php
// Exercise production request normalization and markup without a WordPress database.
define('ABSPATH', __DIR__);
require __DIR__ . '/../wordpress/aac-member-portal/includes/class-aac-member-portal-publication-preferences.php';
function absint($value) { return abs((int) $value); }
function pmpro_getLevel($id) {
    $names = [42 => 'Supporter', 2 => 'Partner', 7 => 'Leader', 8 => 'Advocate', 9 => 'Partner Dependent'];
    return isset($names[$id]) ? (object) ['id' => $id, 'name' => $names[$id]] : false;
}
function __($value, $domain = '') { return $value; }
function esc_html($value) { return htmlspecialchars($value); }
function esc_attr($value) { return htmlspecialchars($value); }
function esc_url($value) { return $value; }
function checked($value, $expected, $echo = false) { return $value === $expected ? 'checked="checked"' : ''; }
function check($ok, $message) { if (!$ok) throw new Exception($message); }
$source = file_get_contents(__DIR__ . '/../wordpress/aac-member-portal/aac-member-portal.php');
function methods_between($source, $start, $end) {
    $a = strpos($source, $start);
    $b = strpos($source, $end, $a);
    check($a !== false && $b !== false, 'Production method boundaries');
    return substr($source, $a, $b - $a);
}
$methods = methods_between($source, "\tpublic function is_supporter_signup_checkout", "\tpublic function render_pmpro_checkout_publication_preferences");
$methods .= methods_between($source, "\tprivate function build_pmpro_publication_preferences_markup", "\tprivate function should_offer_magazine_addons_at_checkout");
eval('class SupporterPublicationTest { public $checkout = true; private function is_pmpro_checkout_request() { return $this->checkout; } public function markup($cards) { return $this->build_pmpro_publication_preferences_markup($cards); }' . $methods . '}');
$plugin = new SupporterPublicationTest();
$cards = array_map(fn($field) => ['name' => $field, 'theme_class' => '', 'image_url' => '', 'title' => $field, 'eyebrow' => '', 'description' => '', 'selected_value' => 'Print'], AAC_Member_Portal_Publication_Preferences::FIELDS);
$_SERVER['REQUEST_METHOD'] = 'POST';
foreach ([42, 2, 7, 8, 9, 999] as $id) {
    $_POST = $_REQUEST = array_merge(['level' => $id], array_fill_keys(AAC_Member_Portal_Publication_Preferences::FIELDS, 'Print'));
    $plugin->enforce_supporter_signup_publications();
    foreach (AAC_Member_Portal_Publication_Preferences::FIELDS as $field) {
        check($_POST[$field] === ($id === 42 ? 'Digital' : 'Print'), "Persisted preference for level $id");
        check($_REQUEST[$field] === $_POST[$field], 'Request and POST agree');
    }
    $markup = $plugin->markup($cards);
    check(substr_count($markup, 'data-aac-digital-only="true"') === ($id === 42 ? 4 : 0), 'Only Supporter cards locked');
    check(substr_count($markup, 'value="Print"') === ($id === 42 ? 0 : 4), 'Print unavailable only for Supporter');
    check(substr_count($markup, 'type="hidden"') === ($id === 42 ? 4 : 0), 'Supporter submits fixed Digital values');
    check(substr_count($markup, '<span>Digital</span>') === ($id === 42 ? 4 : 0), 'Locked cards visibly show Digital');
}
foreach ([['level' => 42, 'aac_member_checkout' => '1'], [], ['level' => ['42']]] as $request) {
    $_REQUEST = $request;
    check(!$plugin->is_supporter_signup_checkout(), 'Excluded member checkout/missing/malformed level');
}
$_REQUEST = ['pmpro_level' => 42];
check($plugin->is_supporter_signup_checkout(), 'Alternative level parameter');
$plugin->checkout = false;
check(!$plugin->is_supporter_signup_checkout(), 'No change outside signup checkout');
echo "PASS Supporter-only enforcement, changed level IDs, saved Print overrides, editable other tiers and request scope\n";
