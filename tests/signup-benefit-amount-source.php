<?php
// Standalone regression check for matrix row-to-coverage mapping.
define('ABSPATH', __DIR__);
function sanitize_text_field($s) { return strip_tags((string) $s); }
function sanitize_key($s) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $s)); }
function untrailingslashit($s) { return rtrim($s, '/'); }
require __DIR__ . '/../wordpress/aac-member-portal/includes/class-aac-member-portal-settings-schema.php';
require __DIR__ . '/../wordpress/aac-member-portal/includes/class-aac-member-portal-runtime-config.php';
class AAC_Member_Portal_Admin {
    public static function get_signup_level_labels() { return ['Leader' => 'Leader']; }
}
function verify($actual, $expected) {
    if ($actual !== $expected) { throw new RuntimeException(var_export([$actual, $expected], true)); }
}
$rows = [
    ['key' => 'mortal_remains_transport', 'label' => 'AAC Lodging Facility Discounts'],
    ['key' => 'rescue_coverage', 'label' => 'Rescue Coverage'],
    ['key' => 'custom_medical', 'label' => 'Medical protection', 'amount_source' => 'medical_amount'],
    ['key' => 'medical_expense_coverage', 'label' => 'Medical Expense Coverage', 'amount_source' => ''],
    ['key' => 'invalid', 'label' => 'Other', 'amount_source' => 'invalid_field'],
];
$normalized = AAC_Member_Portal_Settings_Schema::normalize_signup_benefit_catalog($rows);
verify(array_column($normalized, 'amount_source'), ['', 'rescue_amount', 'medical_amount', '', '']);
verify(AAC_Member_Portal_Settings_Schema::normalize_signup_benefit_catalog($normalized), $normalized);
$config = new AAC_Member_Portal_Runtime_Config('', [], []);
$method = new ReflectionMethod($config, 'build_signup_level_benefits');
if (PHP_VERSION_ID < 80100) { $method->setAccessible(true); }
$settings = ['content' => [
    'signup_benefit_catalog' => $normalized,
    'signup_level_benefits' => ['Leader' => array_column($rows, 'key')],
    'rescue_levels' => [['level_name' => 'Leader', 'rescue_amount' => 300000, 'medical_amount' => 10000, 'mortal_remains_amount' => 5000]],
]];
verify($method->invoke($config, $settings)['Leader'], ['AAC Lodging Facility Discounts', 'Rescue Coverage: $300,000', 'Medical protection: $10,000', 'Medical Expense Coverage', 'Other']);
$settings['content']['signup_benefit_catalog'] = array_reverse($normalized);
verify($method->invoke($config, $settings)['Leader'], ['Other', 'Medical Expense Coverage', 'Medical protection: $10,000', 'Rescue Coverage: $300,000', 'AAC Lodging Facility Discounts']);
echo "Signup benefit mapping regression checks passed.\n";
