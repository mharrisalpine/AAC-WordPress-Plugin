<?php
function __($value, $domain = '') { return $value; }
$source = file_get_contents(__DIR__ . '/../wordpress/aac-member-portal/aac-member-portal.php');
$methods = '';
foreach (['separate_upgrade_receipt_adjustments', 'find_receipt_discount_line_item_index', 'is_receipt_discount_line_item'] as $name) {
    $start = strpos($source, "\tprivate function $name(");
    $end = strpos($source, "\n\tprivate function ", $start + 1);
    $methods .= str_replace('private function', 'public function', substr($source, $start, $end - $start));
}
eval('class ReceiptTest {' . $methods . '}');
$test = new ReceiptTest();
$receipt = ['membership_id'=>3, 'total'=>70, 'items'=>[
 ['label'=>'Partner membership','amount'=>125],
 ['label'=>'Student Discount (35%)','amount'=>-70],
 ['label'=>'Donation','amount'=>15],
]];
$pricing = ['level_id'=>3, 'catalog'=>125, 'discount'=>40, 'credit'=>30];
$result = $test->separate_upgrade_receipt_adjustments($receipt, $pricing);
function check($ok) { if (!$ok) { throw new Exception('Receipt assertion failed'); } }
check($result['items'][1] === ['kind'=>'membership_discount','label'=>'Student Discount','amount'=>-40.0]);
check($result['items'][2] === ['kind'=>'proration_credit','label'=>'Prorated Amount','amount'=>-30.0]);
check(array_sum(array_column($result['items'],'amount')) === 70.0);
check($result['total'] === $receipt['total']);
check($test->separate_upgrade_receipt_adjustments($receipt, null) === $receipt);
check($test->separate_upgrade_receipt_adjustments($receipt, array_merge($pricing,['credit'=>20])) === $receipt);
check($test->separate_upgrade_receipt_adjustments($receipt, array_merge($pricing,['level_id'=>11])) === $receipt);
$receipt['items'][1]['amount'] = -125;
$receipt['total'] = 15;
$result = $test->separate_upgrade_receipt_adjustments($receipt, array_merge($pricing,['credit'=>85]));
check(array_sum(array_column($result['items'],'amount')) === 15.0);
echo "Receipt split, reconciliation, donation, capped credit and historical fallback checks passed.\n";

// Exercise the actual ledger adapter with the receipt produced above.
function absint($value) { return abs((int) $value); }
function sanitize_key($value) { return strtolower($value); }
function sanitize_text_field($value) { return $value; }
function get_option($key, $default = null) { return $GLOBALS['ledger_options'][$key] ?? $default; }
$ledger_source = file_get_contents(__DIR__ . '/../wordpress/aac-member-portal/includes/class-aac-member-portal-pmpro.php');
$start = strpos($ledger_source, "\tprivate static function get_membership_transaction_line_items(");
$end = strpos($ledger_source, "\n\tprivate static function ", $start + 1);
eval('class LedgerTest {' . str_replace('private static function', 'public static function', substr($ledger_source, $start, $end - $start)) . '}');
$receipt = ['membership_id'=>3, 'total'=>30, 'items'=>[
 ['label'=>'Partner membership', 'amount'=>125],
 ['label'=>'Student Discount (35%)', 'amount'=>-95],
]];
$split = $test->separate_upgrade_receipt_adjustments($receipt, ['level_id'=>3,'catalog'=>125,'discount'=>40,'credit'=>55]);
$GLOBALS['ledger_options'] = ['aac_pmpro_order_breakdown_id_26'=>$split];
$lines = LedgerTest::get_membership_transaction_line_items((object) ['id'=>26,'code'=>'3FFF855A08','total'=>30], 'Partner');
check($lines === [
 ['label'=>'Partner membership', 'amount'=>125.0],
 ['label'=>'Student Discount', 'amount'=>-40.0],
 ['label'=>'Prorated Amount', 'amount'=>-55.0],
]);
check(array_sum(array_column($lines, 'amount')) === 30.0);
echo "Ledger preserves separate discount and proration lines with the same total paid.\n";
