<?php
$s = file_get_contents(__DIR__.'/../wordpress/aac-member-portal/aac-member-portal.php');
$a = strpos($s, '\tpublic function signup_password_score_threshold(');
if ($a === false) $a = strpos($s, 'public function signup_password_score_threshold(');
$b = strpos($s, 'public function render_checkout_donation_ui_script()', $a);
eval('class PolicyTest {'.substr($s,$a,$b-$a).'function is_pmpro_checkout_request(){return true;} }');
function is_user_logged_in(){return false;}
function wp_unslash($v){return $v;}
function pmpro_setMessage($m,$t){}
$p=new PolicyTest(); $_GET=['aac_signup'=>'1'];
foreach (['abcdef1!'=>true,'abcd1!'=>false,'abcdefgh'=>false,'abcdef12'=>false,'abcdefg!'=>false,'1234567!'=>false,'abcdef1 '=>false] as $value=>$expected) {
 $_POST=['password'=>$value];
 if ($p->validate_signup_password_policy(true)!==$expected) throw new Exception('Password policy mismatch');
}
if ($p->validate_signup_password_policy(false)!==false) throw new Exception('Previous error overridden');
if ($p->signup_password_score_threshold(2)!==-1) throw new Exception('Signup score gate not replaced');
$_GET=[];
if ($p->signup_password_score_threshold(2)!==2) throw new Exception('Other password policy changed');
echo "Signup password policy tests passed.\n";
