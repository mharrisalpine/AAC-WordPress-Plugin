<?php
// Run the production proration and promo snapshot methods with a controlled paid term.
define('DAY_IN_SECONDS', 86400);
function is_user_logged_in() { return true; }
function get_current_user_id() { return 3157; }
function sanitize_text_field($v) { return $v; }
function current_time($format) { return $GLOBALS['today']; }
function pmpro_getLevel($id) { return (object)['id'=>$id,'initial_payment'=>125]; }
class AAC_Member_Portal_PMPro {
 static function is_available(){ return true; }
 static function get_primary_membership($id){ return ['level_id'=>11,'tier'=>'Supporter','renewal_date'=>'2027-10-13']; }
 static function get_tier_rank_for_level_id($id){ return $id===11?1:2; }
}
$s=file_get_contents(__DIR__.'/../wordpress/aac-member-portal/aac-member-portal.php');
function method($s,$a,$b){$i=strpos($s,$a);return substr($s,$i,strpos($s,$b,$i)-$i);}
// Markers use real tab/newline characters.
$m=method($s,"\tprivate function get_prorated_upgrade_initial_total_for_checkout","\n\t/**");
$m.=method($s,"\tpublic function capture_validated_checkout_promo_totals","\tpublic function filter_pmpro_checkout_level_for_magazine_addons");
eval('class UpgradeTest { public $checkout_membership_change_context=null; function is_pmpro_checkout_request(){return true;} function get_requested_checkout_level_id(){return 3;} function get_aac_membership_level_base_total($l){return 125;} function get_current_term_parent_membership_paid_amount($u,$l){return 55;} function get_current_membership_term_start_date($u,$l){return "2026-10-13";} public function charge($l){return $this->get_prorated_upgrade_initial_total_for_checkout($l,85,85);} '.$m.'}');
$p=new UpgradeTest();
function check($ok,$msg){if(!$ok)throw new Exception($msg);}
$GLOBALS['today']='2026-10-13';
foreach(['STUDENT','MILITARY'] as $code){
 $l=(object)['id'=>3,'code_id'=>1,'discount_code'=>$code,'initial_payment'=>85,'billing_amount'=>85];
 $p->capture_validated_checkout_promo_totals($l);
 $l->initial_payment=70; // Another proration filter ran after the early snapshot.
 check($p->charge($l)===30.0,"$code discounted charge");
 $l->initial_payment=30;
 check($p->charge($l)===30.0,'Repeated filter does not credit twice');
 check($l->aac_upgrade_pricing['credit']===55.0,'Credit excludes donation');
 $GLOBALS['today']='2027-04-13';
 check($p->charge($l)===57.42,'Partial term credit');
 $GLOBALS['today']='2026-10-13';
}
foreach([0,40,55,125] as $price){
 $l=(object)['id'=>3,'code_id'=>2,'discount_code'=>'PROMO','initial_payment'=>$price,'billing_amount'=>$price];
 check($p->charge($l)===max(0.0,$price-55.0),'Zero or low price remains zero');
}
$l=(object)['id'=>3,'initial_payment'=>125,'billing_amount'=>125];
check($p->charge($l)===70.0,'Undiscounted upgrade unchanged');
echo "PASS upgrade price, student/military, partial term, zero, repeated filters and donation exclusion\n";
