<?php
/**
 * Plugin Name: AAC Signup Alignment Hotfix
 * Description: Keeps PMPro signup account fields aligned and autocomplete results visible.
 * Version: 1.0.9
 * Author: AAC
 */

if (!defined('ABSPATH')) {
	exit;
}

add_action('template_redirect', static function () {
	$request_path = isset($_SERVER['REQUEST_URI'])
		? (string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH)
		: '';
	if (untrailingslashit($request_path) !== '/membership-checkout') {
		return;
	}

	if (!defined('DONOTCACHEPAGE')) {
		define('DONOTCACHEPAGE', true);
	}
	nocache_headers();
}, 0);

add_action('wp_head', static function () {
	if (is_admin()) {
		return;
	}
	?>
	<style id="aac-signup-alignment-hotfix">
		#pmpro_user_fields #aac-signup-credentials {
			display: grid !important;
			grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) !important;
			gap: 20px !important;
			width: 100% !important;
		}
		#pmpro_user_fields #aac-signup-credentials > .pmpro_form_field {
			display: flex !important; flex-direction: column !important;
			width: 100% !important; min-width: 0 !important; margin: 0 !important;
			grid-column: auto !important; grid-row: auto !important;
		}
		#pmpro_user_fields #aac-signup-credentials label {
			line-height: 24px !important; min-height: 24px !important; margin: 0 0 8px !important;
		}
		#pmpro_user_fields #aac-signup-credentials input,
		#pmpro_user_fields #aac-signup-credentials .aac-password-input-wrap {
			box-sizing: border-box !important; height: 52px !important; margin-top: 0 !important;
		}
		#aac-signup-communications { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:24px; grid-column:1 / -1; margin-top:28px; width:100%; }
		#aac-signup-communications[hidden] { display:none !important; }
		#aac-signup-communications .aac-communication-row { display:flex !important; align-items:flex-start; gap:12px; padding:16px 0; cursor:pointer; font-size:16px; font-weight:400; line-height:1.6; text-transform:none; }
		input.aac-consent-checkbox { appearance:none !important; -webkit-appearance:none !important; position:relative !important; flex:0 0 1.05rem; width:1.05rem !important; height:1.05rem !important; min-height:0 !important; padding:0 !important; margin:5px 0 0 !important; border:1.5px solid #292524 !important; border-radius:0.3rem !important; background:#fff !important; cursor:pointer; }
		input.aac-consent-checkbox:checked { background:#8f1515 !important; border-color:#8f1515 !important; }
		input.aac-consent-checkbox::before { content:none !important; }
		input.aac-consent-checkbox::after { content:''; display:block; position:absolute; top:1px; left:4px; width:0.28rem; height:0.58rem; border-right:2px solid #fff; border-bottom:2px solid #fff; transform:rotate(45deg) scale(0); }
		input.aac-consent-checkbox:checked::after { transform:rotate(45deg) scale(1); }
		input.aac-consent-checkbox:focus-visible { outline:2px solid #8f1515; outline-offset:3px; }
		@media(max-width:640px) { #aac-signup-communications { grid-template-columns:minmax(0,1fr); } }
		@media (max-width:640px) { #pmpro_user_fields #aac-signup-credentials { grid-template-columns:minmax(0,1fr) !important; } }
		body.pmpro-checkout .aac-managed-card #pmpro_user_fields > .pmpro_card > .pmpro_card_content > .pmpro_form_fields {
			display: block !important;
		}

		body.pmpro-checkout .aac-managed-card #pmpro_user_fields > .pmpro_card > .pmpro_card_content > .pmpro_form_fields > .pmpro_cols-2.aac-managed-two-up {
			display: grid !important;
			grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
			align-items: start !important;
			gap: 0.85rem 1rem !important;
			width: 100% !important;
		}

		body.pmpro-checkout .aac-managed-card #pmpro_user_fields .aac-managed-two-up > .pmpro_form_field {
			display: flex !important;
			flex-direction: column !important;
			align-self: start !important;
			margin: 0 !important;
			min-width: 0 !important;
		}

		body.pmpro-checkout .aac-managed-card #pmpro_user_fields .aac-managed-two-up > .pmpro_form_field-bemail {
			grid-column: 1 !important;
			grid-row: 1 !important;
		}

		body.pmpro-checkout .aac-managed-card #pmpro_user_fields .aac-managed-two-up > .pmpro_form_field-password {
			grid-column: 2 !important;
			grid-row: 1 !important;
		}

		body.pmpro-checkout .aac-managed-card #pmpro_user_fields .aac-managed-two-up > .pmpro_form_field > .pmpro_form_label {
			display: block !important;
			box-sizing: border-box !important;
			min-height: 1.4rem !important;
			margin: 0 0 0.45rem !important;
			line-height: 1.4rem !important;
		}

		body.pmpro-checkout .aac-managed-card #pmpro_user_fields .aac-managed-two-up input,
		body.pmpro-checkout .aac-managed-card #pmpro_user_fields .aac-password-input-wrap {
			box-sizing: border-box !important;
			height: 3.25rem !important;
			min-height: 3.25rem !important;
			margin-top: 0 !important;
		}

		body.pmpro-checkout .aac-managed-card #pmpro_user_fields .aac-password-input-wrap {
			margin-top: 0 !important;
		}

		@media (max-width: 640px) {
			body.pmpro-checkout .aac-managed-card #pmpro_user_fields > .pmpro_card > .pmpro_card_content > .pmpro_form_fields > .pmpro_cols-2.aac-managed-two-up {
				grid-template-columns: minmax(0, 1fr) !important;
			}

			body.pmpro-checkout .aac-managed-card #pmpro_user_fields .aac-managed-two-up > .pmpro_form_field-bemail {
				grid-column: 1 !important;
				grid-row: 1 !important;
			}

			body.pmpro-checkout .aac-managed-card #pmpro_user_fields .aac-managed-two-up > .pmpro_form_field-password {
				grid-column: 1 !important;
				grid-row: 2 !important;
			}
		}

		body.pmpro-checkout #pmpro_form_fieldset-membership-discounts,
		body.pmpro-checkout #pmpro_form_fieldset-membership-discounts > .pmpro_card,
		body.pmpro-checkout #pmpro_form_fieldset-membership-discounts .pmpro_card_content,
		body.pmpro-checkout #pmpro_form_fieldset-membership-discounts .pmpro_form_fields,
		body.pmpro-checkout [data-aac-checkout-discount-details] {
			position: relative !important;
			z-index: 10001 !important;
			overflow: visible !important;
		}

		body.pmpro-checkout .aac-student-university-field {
			z-index: 10002 !important;
			overflow: visible !important;
		}

		body.pmpro-checkout .aac-student-university-dropdown {
			z-index: 10002 !important;
		}

		body.pmpro-checkout #pmpro_form_fieldset-donation {
			position: relative !important;
			z-index: 1 !important;
		}
	</style>
	<?php
}, 999);

add_action('wp_footer', static function () {
	if (is_admin()) {
		return;
	}
	?>
	<script id="aac-signup-layout-cache-bust">
		(function () {
			const refreshSignupEmbed = function () {
				document.querySelectorAll('iframe[src*="aac_signup=1"]').forEach(function (iframe) {
					const url = new URL(iframe.src, window.location.href);
					if (url.searchParams.get('aac_layout_fix') === '109') {
						return;
					}
					url.searchParams.set('aac_layout_fix', '109');
					iframe.src = url.toString();
				});
			};

			refreshSignupEmbed();
			const arrangeSignup = function () {
				if (!new URLSearchParams(location.search).has('aac_signup')) return;
				const account = document.getElementById('pmpro_user_fields');
				const email = document.getElementById('bemail')?.closest('.pmpro_form_field');
				const password = document.getElementById('password')?.closest('.pmpro_form_field');
				const fields = account?.querySelector('.pmpro_form_fields');
				if (fields && email && password) {
					let row = document.getElementById('aac-signup-credentials');
					if (!row) { row = document.createElement('div'); row.id = 'aac-signup-credentials'; fields.append(row); }
					if (email.parentElement !== row || password.parentElement !== row) row.append(email, password);
				}
				// Keep consent inside the actual Member Information step, not the
				// legacy billing bridge or an unowned node visible on every step.
				const memberFields = document.querySelector('#aac_pmpro_native_member_information_fields .pmpro_form_fields')
					|| document.querySelector('[data-aac-native-member-info="true"] .pmpro_form_fields')
					|| document.querySelector('#pmpro_billing_address_fields:not([data-aac-legacy-billing-bridge="true"]) .pmpro_form_fields');
				const communications = document.getElementById('aac-signup-communications');
				if (communications) {
					if (memberFields && memberFields.lastElementChild !== communications) memberFields.append(communications);
					communications.hidden = !memberFields;
				}
			};
			arrangeSignup();
			document.addEventListener('DOMContentLoaded', arrangeSignup);
			window.addEventListener('load', arrangeSignup);
			let pending = false;
			new MutationObserver(function () {
				if (pending) return;
				pending = true;
				requestAnimationFrame(function () { pending = false; arrangeSignup(); });
			}).observe(document.documentElement, { childList: true, subtree: true });
			new MutationObserver(refreshSignupEmbed).observe(document.documentElement, {
				childList: true,
				subtree: true,
			});
		}());
	</script>
	<?php
}, 999);

add_action('pmpro_checkout_after_billing_fields', static function () {
	if (empty($_GET['aac_signup'])) return;
	echo '<div id="aac-signup-communications" hidden>';
	wp_nonce_field('aac_signup_communications', 'aac_communications_nonce');
	foreach (aac_signup_consent_fields() as $key => $label) {
		$value = isset($_POST['aac_communications_nonce']) ? ($_POST[$key] ?? '0') : get_user_meta(get_current_user_id(), $key, true);
		$checked = $value === '1';
		echo '<label class="aac-communication-row"><input class="aac-consent-checkbox" type="checkbox" name="' . esc_attr($key) . '" value="1" ' . checked($checked, true, false) . '><span>' . esc_html($label) . '</span></label>';
	}
	echo '</div>';
});

// Render the configured PMPro fields, not a second set of consent definitions.
function aac_signup_consent_fields() {
	$fields = [];
	foreach ((array) get_option('pmpro_user_fields_settings', []) as $group) {
		$group = (array) $group;
		foreach (($group['fields'] ?? []) as $field) {
			$field = (array) $field;
			$key = $field['meta_key'] ?? $field['name'] ?? '';
			if (in_array($key, ['aac_email_marketing_opt_in', 'aac_sms_marketing_opt_in'], true)) $fields[$key] = $field['label'];
		}
	}
	return $fields;
}

add_action('pmpro_after_checkout', static function ($user_id) {
	if (empty($_POST['aac_communications_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['aac_communications_nonce'])), 'aac_signup_communications')) return;
	foreach (aac_signup_consent_fields() as $field => $label) {
		update_user_meta($user_id, $field, isset($_POST[$field]) && $_POST[$field] === '1' ? '1' : '0');
	}
}, 200);
