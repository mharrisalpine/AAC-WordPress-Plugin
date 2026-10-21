<?php
$source = file_get_contents(__DIR__ . '/../wordpress/aac-member-portal/aac-member-portal.php');
$start = strpos($source, "\tprivate function get_submitted_checkout_tshirt(");
$end = strpos($source, "\tpublic function sync_pmpro_checkout_profile_fields(", $start);
eval('class CheckoutPreferencesTest {' . substr($source, $start, $end - $start) . '
function normalize_tshirt_size_value($value) { return trim($value); }
function is_pmpro_checkout_request() { return true; }
function get_checkout_request_value($keys) { return $_POST["bcountry"] ?? "US"; }
function is_international_country($country) { return $country !== "US"; }
}');
function wp_unslash($value) { return $value; }
$meta = [];
function get_user_meta($id, $key, $single = true) { global $meta; return $meta[$key] ?? ''; }
function update_user_meta($id, $key, $value) { global $meta; $meta[$key] = $value; }
function check($ok) { if (!$ok) throw new Exception('Checkout preference assertion failed'); }
$test = new CheckoutPreferencesTest();
$_SERVER['REQUEST_METHOD'] = 'POST';
$meta = ['aac_sms_marketing_opt_in'=>'1', 'aac_account_info'=>['size'=>'Large', 'first_name'=>'Existing']];
$_POST = ['t_shirt_sizet_shirt'=>'Small', 't_shirt'=>'Large', 'aac_sms_marketing_opt_in_present'=>'1', 'aac_email_marketing_opt_in'=>'1'];
$test->save_checkout_preferences(42);
check($meta['t_shirt'] === 'Small' && $meta['t_shirt_sizet_shirt'] === 'Small');
check($meta['aac_account_info']['size'] === 'Small');
check($meta['aac_sms_marketing_opt_in'] === '0' && $meta['aac_email_marketing_opt_in'] === '1');
check($meta['aac_account_info']['first_name'] === 'Existing');
$_POST = [];
$before = $meta;
$test->save_checkout_preferences(42);
check($meta === $before);
$_POST = ['t_shirt_sizet_shirt'=>'Medium', 'bcountry'=>'CA', 'aac_email_marketing_opt_in'=>'false'];
$test->save_checkout_preferences(42);
check($meta['t_shirt'] === 'No T-shirt' && $meta['aac_email_marketing_opt_in'] === '0');
$_SERVER['REQUEST_METHOD'] = 'GET';
$before = $meta;
$_POST = ['aac_sms_marketing_opt_in'=>'1'];
$test->save_checkout_preferences(42);
check($meta === $before);
check(strpos($source, "'save_checkout_preferences'], 35, 2") !== false);
echo "Checkout preference persistence, explicit opt-out, omitted fields and POST-only tests passed.\n";

// Fresh signup: both communication choices and the configured shirt field persist.
$_SERVER['REQUEST_METHOD'] = 'POST';
$meta = [];
$_POST = ['t_shirt_sizet_shirt'=>'Unisex Medium', 'aac_email_marketing_opt_in'=>'1', 'aac_email_marketing_opt_in_present'=>'1', 'aac_sms_marketing_opt_in'=>'1', 'aac_sms_marketing_opt_in_present'=>'1'];
$test->save_checkout_preferences(42);
check($meta['t_shirt'] === 'Unisex Medium');
check($meta['aac_account_info']['aac_email_marketing_opt_in'] === true);
check($meta['aac_account_info']['aac_sms_marketing_opt_in'] === true);

// Upgrade: changing the shirt must preserve both consents when controls are absent.
$_POST = ['t_shirt_sizet_shirt'=>'Unisex Large'];
$test->save_checkout_preferences(42);
check($meta['t_shirt'] === 'Unisex Large');
check($meta['aac_email_marketing_opt_in'] === '1' && $meta['aac_sms_marketing_opt_in'] === '1');
check($meta['aac_account_info']['aac_email_marketing_opt_in'] === true);
check($meta['aac_account_info']['aac_sms_marketing_opt_in'] === true);

// Both unchecked choices are explicit opt-outs, not omitted fields.
$_POST = ['aac_email_marketing_opt_in_present'=>'1', 'aac_sms_marketing_opt_in_present'=>'1'];
$test->save_checkout_preferences(42);
check($meta['aac_email_marketing_opt_in'] === '0' && $meta['aac_sms_marketing_opt_in'] === '0');
check($meta['t_shirt'] === 'Unisex Large');
echo "Signup, upgrade preservation and dual opt-out regression tests passed.\n";
