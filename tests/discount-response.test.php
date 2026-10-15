<?php
// Exercise the real methods without booting WordPress or contacting Stripe.
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
$source = file_get_contents(__DIR__ . '/../wordpress/aac-member-portal/aac-member-portal.php');
$start = strpos($source, "\tpublic function discount_code_ajax_timeout");
$end = strpos($source, "\tpublic function ajax_validate_pmpro_discount_code", $start);
eval('class DiscountResponseTest {' . substr($source, $start, $end - $start) . '}');
$plugin = new DiscountResponseTest();
function check($ok, $message) { if (!$ok) { throw new Exception($message); } }
check($plugin->discount_code_ajax_timeout(5000, 'applydiscountcode') === 60000, 'Discount timeout');
check($plugin->discount_code_ajax_timeout(60000, 'applydiscountcode') === 60000, 'Preserve longer timeout');
check($plugin->discount_code_ajax_timeout(5000, 'other') === 5000, 'Unrelated action unchanged');
foreach ([false, (object) ['initial_payment' => '1.00000000'], (object) ['initial_payment' => 0]] as $level) {
    ob_start();
    $plugin->render_applied_discount_summary_result('TEST</script>', 1, 3, $level);
    $js = ob_get_clean();
    check(!str_contains($js, '</script>'), 'Escape script boundary');
    preg_match('/detail: (.*) \}\)\);$/', $js, $match);
    $data = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
    check($data['valid'] === is_object($level), 'Native validation result');
    check($data['level_id'] === 3, 'Level identity');
    if (is_object($level)) check($data['initial_payment'] == $level->initial_payment, 'Native price preserved');
}
echo "PASS native discount response, free price, escaping and timeout scope\n";
