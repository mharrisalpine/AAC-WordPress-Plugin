<?php
/**
 * Fullscreen template for AAC Member Portal and managed PMPro shell pages.
 *
 * @package AAC_Member_Portal
 */

if (!defined('ABSPATH')) {
	exit;
}

$post = get_post();
$is_portal_page = $post instanceof WP_Post && has_shortcode($post->post_content, AAC_Member_Portal_Plugin::SHORTCODE);
$portal_plugin = $GLOBALS['aac_member_portal_plugin'] ?? null;
$portal_url = $portal_plugin instanceof AAC_Member_Portal_Plugin ? $portal_plugin->get_portal_page_url() : home_url('/');
$portal_url = untrailingslashit($portal_url) . '/';
$current_url = $post instanceof WP_Post ? untrailingslashit(get_permalink($post)) : '';
$request_path = '';
if (!empty($_SERVER['REQUEST_URI'])) {
	$request_path = untrailingslashit((string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH));
}
$account_url = AAC_Member_Portal_PMPro::is_available() && function_exists('pmpro_url') ? pmpro_url('account') : home_url('/membership-account/');
$billing_url = AAC_Member_Portal_PMPro::is_available() && function_exists('pmpro_url') ? pmpro_url('billing') : home_url('/membership-account/membership-billing/');
$orders_url = AAC_Member_Portal_PMPro::is_available() && function_exists('pmpro_url') ? pmpro_url('invoice') : home_url('/membership-account/membership-orders/');
$cancel_url = AAC_Member_Portal_PMPro::is_available() && function_exists('pmpro_url') ? pmpro_url('cancel') : home_url('/membership-account/membership-cancel/');
$checkout_url = AAC_Member_Portal_PMPro::is_available() && function_exists('pmpro_url') ? pmpro_url('checkout') : home_url('/membership-checkout/');
$confirmation_url = AAC_Member_Portal_PMPro::is_available() && function_exists('pmpro_url') ? pmpro_url('confirmation') : home_url('/membership-checkout/membership-confirmation/');
$account_path = untrailingslashit((string) wp_parse_url($account_url, PHP_URL_PATH));
$billing_path = untrailingslashit((string) wp_parse_url($billing_url, PHP_URL_PATH));
$orders_path = untrailingslashit((string) wp_parse_url($orders_url, PHP_URL_PATH));
$cancel_path = untrailingslashit((string) wp_parse_url($cancel_url, PHP_URL_PATH));
$checkout_path = untrailingslashit((string) wp_parse_url($checkout_url, PHP_URL_PATH));
$confirmation_path = untrailingslashit((string) wp_parse_url($confirmation_url, PHP_URL_PATH));
$is_account_page = $current_url === untrailingslashit($account_url) || ($account_path && $account_path === $request_path);
$is_billing_page = $current_url === untrailingslashit($billing_url) || ($billing_path && $billing_path === $request_path);
$is_orders_page = $current_url === untrailingslashit($orders_url) || ($orders_path && $orders_path === $request_path);
$is_cancel_page = $current_url === untrailingslashit($cancel_url) || ($cancel_path && $cancel_path === $request_path);
$is_checkout_page = $current_url === untrailingslashit($checkout_url) || ($checkout_path && $checkout_path === $request_path);
$is_confirmation_page = $current_url === untrailingslashit($confirmation_url) || ($confirmation_path && $confirmation_path === $request_path);
$is_managed_pmpro_page = $is_account_page || $is_billing_page || $is_orders_page || $is_cancel_page || $is_checkout_page || $is_confirmation_page;
$is_embed_request = isset($_GET['aac_embed']) && sanitize_text_field(wp_unslash($_GET['aac_embed'])) === '1';
$is_embedded_checkout = $is_checkout_page && $is_embed_request;
$is_embedded_confirmation = $is_confirmation_page && $is_embed_request;
$is_embedded_pmpro_page = $is_embedded_checkout || $is_embedded_confirmation;
$public_shell_slugs = ['benefits', 'rescue'];
$is_public_shell_page = $post instanceof WP_Post && in_array($post->post_name, $public_shell_slugs, true);
$is_logged_in = is_user_logged_in();
$public_home_url = $portal_url . '#/home';
$public_login_url = $portal_url . '#/login';
$public_post_purchase_login_url = $portal_url . '#/login?purchase_success=1';
$public_profile_url = $portal_url . '#/profile';
$public_donate_url = $portal_url . '#/donate';
$portal_ui_settings = $portal_plugin instanceof AAC_Member_Portal_Plugin ? $portal_plugin->get_portal_ui_settings() : [];
$portal_design_settings = $portal_plugin instanceof AAC_Member_Portal_Plugin
	? $portal_plugin->get_template_design_settings()
	: [
		'sidebar_background_url' => AAC_MEMBER_PORTAL_URL . 'app/sidebar-topo-v2.svg',
		'sidebar_overlay_start' => '0.18',
		'sidebar_overlay_end' => '0.30',
		'sidebar_button_background' => '#000000',
		'sidebar_button_hover_background' => '#111111',
		'sidebar_button_active_background' => '#000000',
		'sidebar_accent_color' => '#f8c235',
		'publication_tile_images' => [
			'aaj' => '',
			'anac' => '',
			'acj' => '',
			'guidebook' => '',
		],
	];

if (!function_exists('aac_member_portal_sidebar_icon_svg')) {
	function aac_member_portal_sidebar_icon_svg($icon) {
		$icons = [
			'user' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
			'store' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 9.5 3.6 4h16.8L22 9.5"/><path d="M4 10v10a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V10"/><path d="M8 14h8"/><path d="M9 18h6"/></svg>',
			'shield' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 13c0 5-3.5 7.5-8 9-4.5-1.5-8-4-8-9V6l8-3 8 3z"/></svg>',
			'settings' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1 1.54V21a2 2 0 1 1-4 0v-.09a1.7 1.7 0 0 0-1-1.54 1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.7 1.7 0 0 0 .34-1.87 1.7 1.7 0 0 0-1.54-1H3a2 2 0 1 1 0-4h.09a1.7 1.7 0 0 0 1.54-1 1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.7 1.7 0 0 0 1.87.34H9A1.7 1.7 0 0 0 10 3.09V3a2 2 0 1 1 4 0v.09a1.7 1.7 0 0 0 1 1.54 1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.7 1.7 0 0 0-.34 1.87V9c0 .67.39 1.28 1 1.54.18.08.37.13.57.13H21a2 2 0 1 1 0 4h-.09a1.7 1.7 0 0 0-1.54 1Z"/></svg>',
			'credit-card' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M6 15h2"/><path d="M10 15h4"/></svg>',
			'receipt' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 3h16v18l-2-1.5-2 1.5-2-1.5-2 1.5-2-1.5-2 1.5-2-1.5-2 1.5Z"/><path d="M8 7h8"/><path d="M8 11h8"/><path d="M8 15h5"/></svg>',
			'tag' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.6 13.4L13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8Z"/><circle cx="8.5" cy="8.5" r="1.5"/></svg>',
			'pen' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 1 1 3 3L7 19l-4 1 1-4Z"/></svg>',
			'book' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 7v14"/><path d="M3 18.5A2.5 2.5 0 0 1 5.5 16H12v5H5.5A2.5 2.5 0 0 1 3 18.5Z"/><path d="M21 18.5a2.5 2.5 0 0 0-2.5-2.5H12v5h6.5A2.5 2.5 0 0 0 21 18.5Z"/><path d="M5.5 16V5a2 2 0 0 1 2-2H12v13H5.5Z"/><path d="M18.5 16V5a2 2 0 0 0-2-2H12v13h6.5Z"/></svg>',
			'mic' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19v3"/><path d="M8 22h8"/><rect x="9" y="2" width="6" height="11" rx="3"/><path d="M5 10a7 7 0 0 0 14 0"/></svg>',
			'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
			'scroll-text' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 21h8"/><path d="M12 17v4"/><path d="M7 4V2"/><path d="M17 4V2"/><path d="M5 8h14"/><path d="M6 4h12a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1Z"/><path d="M9 12h6"/><path d="M9 15h4"/></svg>',
			'mail' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>',
			'bed' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 18v-7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v7"/><path d="M3 13h18"/><path d="M7 13V9"/><path d="M17 13V9"/><path d="M3 18v3"/><path d="M21 18v3"/></svg>',
		];

		return $icons[$icon] ?? $icons['user'];
	}
}

$top_nav = $portal_plugin instanceof AAC_Member_Portal_Plugin
	? $portal_plugin->get_template_top_nav_sections($portal_url)
	: [];

$portal_sections = $portal_plugin instanceof AAC_Member_Portal_Plugin
	? $portal_plugin->get_template_sidebar_sections($portal_url)
	: [];

$current_member = $is_logged_in ? wp_get_current_user() : null;
$current_member_id = $current_member instanceof WP_User && $current_member->exists() ? (int) $current_member->ID : 0;
$current_primary_membership = $current_member_id ? AAC_Member_Portal_PMPro::get_primary_membership($current_member_id) : null;
$current_membership_actions = ($current_member_id && $current_primary_membership)
	? AAC_Member_Portal_PMPro::build_membership_actions($current_member_id, ['tier' => $current_primary_membership['tier']])
	: [
		'account_url' => $account_url,
		'billing_url' => $billing_url,
		'cancel_url' => $cancel_url,
		'current_level_id' => null,
		'current_subscription_id' => null,
		'current_level_checkout_url' => '',
		'levels' => new stdClass(),
	];
$managed_billing_url = !empty($current_membership_actions['billing_url'])
	? $current_membership_actions['billing_url']
	: (!empty($current_membership_actions['current_level_checkout_url']) ? $current_membership_actions['current_level_checkout_url'] : $account_url);
$current_auto_renew = $current_member_id && !empty($current_membership_actions['current_level_id'])
	? AAC_Member_Portal_PMPro::has_active_auto_renewal($current_member_id, (int) $current_membership_actions['current_level_id'])
	: false;
$current_renewal_date = is_array($current_primary_membership) ? ($current_primary_membership['renewal_date'] ?? '') : '';
$current_expiration_date = is_array($current_primary_membership) ? ($current_primary_membership['expiration_date'] ?? '') : '';
$page_title = $is_account_page ? 'Membership Account' : ($is_billing_page ? 'Membership Billing' : ($is_orders_page ? 'Membership Orders' : ($is_cancel_page ? 'Membership Cancellation' : ($is_confirmation_page ? 'Membership Confirmation' : ($is_checkout_page ? 'Membership Checkout' : get_the_title($post))))));
$page_kicker = $is_account_page ? 'Account Overview' : ($is_billing_page ? 'Billing Center' : ($is_orders_page ? 'Order History' : ($is_cancel_page ? 'Membership Options' : ($is_confirmation_page ? 'Confirmation' : ($is_checkout_page ? 'Secure Checkout' : 'Member Portal')))));
$page_description = $is_account_page
	? 'Review your current membership, renewal timing, and account tools in the same AAC portal shell.'
	: ($is_billing_page
	? 'Manage payment methods, current memberships, and PMPro billing details without leaving the AAC portal experience.'
	: ($is_orders_page
		? 'Review membership invoices, completed renewals, and recent PMPro transactions without leaving the AAC portal shell.'
	: ($is_cancel_page
		? 'Review cancellation options for any membership level without leaving the AAC portal shell.'
		: ($is_confirmation_page
			? 'Review your completed membership order in the same AAC portal shell with quick access back to your profile and account.'
			: ($is_checkout_page
				? 'Complete membership checkout in the same AAC portal shell with quick access back to your profile and account.'
				: 'Access your AAC member tools in a dedicated full-page portal experience.')))));
$checkout_profile_defaults = $portal_plugin instanceof AAC_Member_Portal_Plugin
	? $portal_plugin->get_pmpro_checkout_profile_defaults()
	: [
		'publication_pref' => 'Print',
		'aaj_pref' => 'Print',
		'anac_pref' => 'Print',
		'acj_pref' => 'Print',
		'guidebook_pref' => 'Print',
		'size' => 'No T-shirt',
	];
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<style>
		@import url('https://use.typekit.net/veb7xhf.css');

		html,
		body {
			min-height: 100%;
			margin: 0;
			background: #f3efe6;
		}

		body.aac-member-portal-fullscreen {
			min-height: 100vh;
		}

		body.aac-member-portal-public-shell {
			min-height: 100vh;
			background:
				radial-gradient(circle at 15% 10%, rgba(248, 194, 53, 0.16), transparent 24%),
				radial-gradient(circle at 85% 15%, rgba(3, 0, 0, 0.06), transparent 20%),
				linear-gradient(180deg, rgba(255, 255, 255, 0.45), rgba(245, 239, 228, 0.7)),
				repeating-linear-gradient(120deg, rgba(3, 0, 0, 0.045) 0 1px, transparent 1px 22px);
			color: #030000;
			font-family: futura-pt, Futura, 'Futura PT', 'Century Gothic', 'Trebuchet MS', 'Gill Sans', ui-sans-serif, sans-serif;
			letter-spacing: 0.02em;
		}

		body.aac-member-portal-embed {
			background: transparent;
			min-height: 0;
			overflow: hidden;
		}

		#aac-member-portal-root {
			min-height: 100vh;
		}

		body.aac-member-portal-public-shell h1,
		body.aac-member-portal-public-shell h2,
		body.aac-member-portal-public-shell h3,
		body.aac-member-portal-public-shell h4,
		body.aac-member-portal-public-shell h5,
		body.aac-member-portal-public-shell h6 {
			font-family: futura-pt-bold, futura-pt, Futura, 'Futura PT', 'Century Gothic', 'Trebuchet MS', 'Gill Sans', ui-sans-serif, sans-serif;
			font-weight: 700;
			letter-spacing: 0.02em;
		}

		body.aac-member-portal-public-shell p,
		body.aac-member-portal-public-shell li,
		body.aac-member-portal-public-shell a,
		body.aac-member-portal-public-shell span,
		body.aac-member-portal-public-shell strong,
		body.aac-member-portal-public-shell em,
		body.aac-member-portal-public-shell .wp-element-button,
		body.aac-member-portal-public-shell .wp-block-button__link {
			font-family: futura-pt, Futura, 'Futura PT', 'Century Gothic', 'Trebuchet MS', 'Gill Sans', ui-sans-serif, sans-serif;
		}

		.aac-managed-shell {
			min-height: 100vh;
			background:
				linear-gradient(180deg, rgba(255, 255, 255, 0.56), rgba(246, 241, 232, 0.74)),
				radial-gradient(circle at 16% 10%, rgba(248, 194, 53, 0.12), transparent 24%),
				radial-gradient(circle at 84% 14%, rgba(3, 0, 0, 0.04), transparent 19%),
				url('<?php echo esc_url(AAC_MEMBER_PORTAL_URL . 'app/app-page-topo.svg'); ?>') center top / 1120px auto repeat;
		}

		.aac-managed-header {
			position: sticky;
			top: 0;
			z-index: 50;
			border-bottom: 1px solid rgba(255, 255, 255, 0.1);
			background: rgba(3, 0, 0, 0.96);
			backdrop-filter: blur(14px);
		}

		.aac-managed-header__inner {
			width: 100%;
			margin: 0;
			padding: calc(env(safe-area-inset-top, 0px) + 0.45rem) 0 0;
		}

		.aac-managed-header__row,
		.aac-managed-header__bar {
			display: grid;
			grid-template-columns: auto minmax(0, 1fr) auto;
			align-items: stretch;
			gap: 0;
			width: 100%;
		}

		.aac-managed-logo {
			display: flex;
			align-items: center;
			padding: 0.85rem 1rem;
			border-right: 1px solid rgba(255, 255, 255, 0.1);
		}

		.aac-managed-logo img {
			display: block;
			width: auto;
			height: 48px;
		}

		.aac-managed-actions,
		.aac-managed-topnav {
			display: flex;
			flex-wrap: nowrap;
			align-items: stretch;
			gap: 0;
		}

		.aac-managed-topnav {
			min-width: 0;
			overflow-x: auto;
			overflow-y: visible;
			scrollbar-width: none;
		}

		.aac-managed-topnav::-webkit-scrollbar {
			display: none;
		}

		.aac-managed-actions {
			padding: 0.7rem 1rem;
			border-left: 1px solid rgba(255, 255, 255, 0.1);
		}

		.aac-managed-pill,
		.aac-managed-topnav a {
			text-decoration: none;
			transition: color 0.2s ease, background-color 0.2s ease, border-color 0.2s ease;
		}

		.aac-managed-pill {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			min-height: 3rem;
			padding: 0 1.15rem;
			border-radius: 0;
			font-size: 0.76rem;
			font-weight: 700;
			letter-spacing: 0.14em;
			text-transform: uppercase;
		}

		.aac-managed-pill--icon {
			width: 4rem;
			padding: 0;
		}

		.aac-managed-pill--icon svg {
			width: 1.45rem;
			height: 1.45rem;
		}

		.aac-managed-shell button,
		.aac-managed-shell input[type="submit"],
		.aac-managed-shell input[type="button"],
		.aac-managed-shell input[type="reset"],
		.aac-managed-shell .button,
		.aac-managed-shell .pmpro_btn,
		.aac-managed-shell .pmpro_btn-submit,
		.aac-managed-shell .pmpro_btn-select,
		.aac-managed-shell .wp-block-button__link,
		.aac-managed-shell .wp-element-button,
		.aac-member-portal-public-shell button,
		.aac-member-portal-public-shell input[type="submit"],
		.aac-member-portal-public-shell input[type="button"],
		.aac-member-portal-public-shell input[type="reset"],
		.aac-member-portal-public-shell .button,
		.aac-member-portal-public-shell .wp-block-button__link,
		.aac-member-portal-public-shell .wp-element-button {
			border-radius: 0 !important;
		}

		.aac-managed-pill--ghost {
			border: 1px solid rgba(255, 255, 255, 0.1);
			background: rgba(255, 255, 255, 0.03);
			color: rgba(255, 255, 255, 0.86);
		}

		.aac-managed-pill--ghost:hover {
			border-color: rgba(248, 194, 53, 0.45);
			color: #f8c235;
		}

		.aac-managed-pill--primary {
			background: #f8c235;
			color: #000;
		}

		.aac-managed-pill--primary:hover {
			background: #e1ae14;
		}

		.aac-managed-pill--danger {
			background: #8f1515;
			color: #fff;
		}

		.aac-managed-pill--danger:hover {
			background: #6b1010;
		}

		.aac-managed-topnav__item {
			position: relative;
			display: flex;
			align-items: center;
		}

		.aac-managed-topnav__trigger {
			display: inline-flex;
			align-items: center;
			gap: 0.6rem;
			min-height: 4.75rem;
			padding: 0 0.95rem;
			color: rgba(255, 255, 255, 0.84);
			font-size: 0.88rem;
			font-weight: 700;
			letter-spacing: 0.14em;
			text-transform: uppercase;
			white-space: nowrap;
		}

		.aac-managed-topnav__caret {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			min-width: 1rem;
			color: #f8c235;
			font-size: 1.35rem;
			font-weight: 500;
			line-height: 1;
			opacity: 0.92;
		}

		.aac-managed-topnav__trigger:hover,
		.aac-managed-topnav__item:focus-within .aac-managed-topnav__trigger {
			color: #f8c235;
		}

		.aac-managed-topnav__panel {
			position: absolute;
			left: 0;
			top: 100%;
			z-index: 90;
			visibility: hidden;
			min-width: 18rem;
			max-width: 22rem;
			padding-top: 0.75rem;
			opacity: 0;
			transition: opacity 0.15s ease, visibility 0.15s ease;
		}

		.aac-managed-topnav__item:hover .aac-managed-topnav__panel,
		.aac-managed-topnav__item:focus-within .aac-managed-topnav__panel {
			visibility: visible;
			opacity: 1;
		}

		.aac-managed-topnav__panel-inner {
			border: 1px solid rgba(255, 255, 255, 0.12);
			border-radius: 0;
			background: rgba(11, 9, 8, 0.95);
			padding: 1.25rem;
			box-shadow: 0 28px 80px rgba(0, 0, 0, 0.45);
			backdrop-filter: blur(14px);
		}

		.aac-managed-topnav__panel-title {
			display: block;
			margin-bottom: 0.75rem;
			padding: 0 1rem;
			color: #f8c235;
			font-size: 0.68rem;
			font-weight: 600;
			letter-spacing: 0.25em;
			text-transform: uppercase;
		}

		.aac-managed-topnav__panel ul {
			list-style: none;
			margin: 0;
			padding: 0;
		}

		.aac-managed-topnav__panel li + li {
			margin-top: 0.25rem;
		}

		.aac-managed-topnav__link {
			display: block;
			border-radius: 1rem;
			padding: 0.8rem 1rem;
			color: #f4efe7;
			font-size: 0.95rem;
			font-weight: 500;
			letter-spacing: normal;
			text-transform: none;
		}

		.aac-managed-topnav__link:hover {
			background: rgba(255, 255, 255, 0.08);
			color: #f8c235;
		}

		.aac-managed-topnav__link--overview {
			font-weight: 700;
			color: #fff;
		}

		.aac-managed-layout {
			display: flex;
			flex-wrap: nowrap;
			align-items: stretch;
			gap: 0;
			min-height: calc(100vh - (env(safe-area-inset-top, 0px) + 4.75rem));
		}

		.aac-managed-sidebar {
			position: sticky;
			top: calc(env(safe-area-inset-top, 0px) + 4.75rem);
			align-self: stretch;
			width: 5.25rem;
			height: calc(100vh - (env(safe-area-inset-top, 0px) + 4.75rem));
			min-height: calc(100vh - (env(safe-area-inset-top, 0px) + 4.75rem));
			max-height: calc(100vh - (env(safe-area-inset-top, 0px) + 4.75rem));
			overflow: visible;
			border-right: 1px solid rgba(0, 0, 0, 0.08);
			background-color: #030000;
			background-image:
				linear-gradient(180deg, rgba(5, 2, 2, <?php echo esc_attr($portal_design_settings['sidebar_overlay_start']); ?>), rgba(5, 2, 2, <?php echo esc_attr($portal_design_settings['sidebar_overlay_end']); ?>)),
				url('<?php echo esc_url($portal_design_settings['sidebar_background_url']); ?>');
			background-position: center center, center top;
			background-repeat: no-repeat, repeat;
			background-size: cover, 760px auto;
			color: #fff;
			padding: 1rem 0.75rem;
			box-sizing: border-box;
			box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05);
			z-index: 4;
		}

		.aac-managed-sidebar::before {
			content: '';
			position: absolute;
			inset: 0;
			background:
				linear-gradient(180deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0.035)),
				radial-gradient(circle at top left, rgba(248, 194, 53, 0.04), transparent 24%);
			pointer-events: none;
		}

		.aac-managed-sidebar__section + .aac-managed-sidebar__section {
			margin-top: 1.5rem;
		}

		.aac-managed-sidebar__section-title {
			margin: 0 0 0.55rem;
			padding: 0 0.75rem;
			color: rgba(255, 255, 255, 0.8);
			font-size: 0.82rem;
			font-weight: 700;
			letter-spacing: 0.22em;
			text-transform: uppercase;
			opacity: 0;
			max-height: 0;
			overflow: hidden;
			transform: translateX(-6px);
			white-space: nowrap;
			margin-bottom: 0;
			transition: opacity 0.18s ease, max-height 0.18s ease, margin-bottom 0.18s ease, transform 0.18s ease;
		}

		.aac-managed-sidebar ul {
			list-style: none;
			margin: 0;
			padding: 0;
		}

		.aac-managed-sidebar a {
			display: flex;
			align-items: center;
			gap: 0.75rem;
			justify-content: center;
			position: relative;
			padding: 0.85rem 0.75rem;
			border-bottom: 1px solid rgba(255, 255, 255, 0.09);
			color: #fff;
			font-size: 1.05rem;
			font-weight: 500;
			text-decoration: none;
			transition: all 0.2s ease;
		}

		.aac-managed-sidebar a:hover {
			border-color: <?php echo esc_html($portal_design_settings['sidebar_accent_color']); ?>;
			color: <?php echo esc_html($portal_design_settings['sidebar_accent_color']); ?>;
		}

		.aac-managed-sidebar__icon {
			display: inline-flex;
			width: 1.25rem;
			height: 1.25rem;
			flex: 0 0 auto;
			color: #fff;
		}

		.aac-managed-sidebar__icon svg {
			width: 100%;
			height: 100%;
		}

		.aac-managed-sidebar > * {
			position: relative;
			z-index: 1;
		}

		.aac-managed-sidebar__label {
			position: absolute;
			left: calc(100% + 0.9rem);
			top: 50%;
			z-index: 5;
			display: inline-flex;
			align-items: center;
			min-height: 2.65rem;
			padding: 0.55rem 0.9rem;
			border: 1px solid rgba(248, 194, 53, 0.28);
			background: rgba(8, 5, 5, 0.94);
			box-shadow: 0 18px 36px rgba(0, 0, 0, 0.34);
			white-space: nowrap;
			opacity: 0;
			pointer-events: none;
			transform: translate3d(-10px, -50%, 0);
			transition: opacity 0.18s ease, transform 0.18s ease;
		}

		.aac-managed-sidebar a:hover .aac-managed-sidebar__label,
		.aac-managed-sidebar a:focus-visible .aac-managed-sidebar__label,
		.aac-managed-sidebar a:focus-within .aac-managed-sidebar__label {
			opacity: 1;
			transform: translate3d(0, -50%, 0);
		}

		.aac-managed-sidebar a[aria-current="page"] .aac-managed-sidebar__icon {
			color: <?php echo esc_html($portal_design_settings['sidebar_accent_color']); ?>;
		}

		.aac-managed-sidebar a[aria-current="page"] {
			border-color: <?php echo esc_html($portal_design_settings['sidebar_accent_color']); ?>;
			color: <?php echo esc_html($portal_design_settings['sidebar_accent_color']); ?>;
		}

		.aac-managed-main {
			flex: 1;
			min-width: 0;
			padding: 1.5rem 1rem 2rem;
			box-sizing: border-box;
		}

		.aac-managed-main__inner {
			max-width: 80rem;
			margin: 0 auto;
		}

		.aac-managed-hero {
			border: 1px solid rgba(0, 0, 0, 0.08);
			background: #030000;
			color: #fff;
			border-radius: 30px;
			padding: 1.75rem 1.5rem;
			box-shadow: 0 24px 70px rgba(3, 0, 0, 0.18);
		}

		.aac-managed-hero__kicker {
			margin: 0;
			color: #f8c235;
			font-size: 0.72rem;
			font-weight: 700;
			letter-spacing: 0.3em;
			text-transform: uppercase;
		}

		.aac-managed-hero h1 {
			margin: 0.75rem 0 0;
			font-size: clamp(2rem, 4vw, 2.75rem);
			line-height: 1.1;
		}

		.aac-managed-hero p {
			max-width: 46rem;
			margin: 0.85rem 0 0;
			color: rgba(255, 255, 255, 0.76);
			font-size: 1rem;
			line-height: 1.75;
		}

		.aac-managed-actions-row {
			display: flex;
			flex-wrap: wrap;
			gap: 0.75rem;
			margin-top: 1.25rem;
		}

		.aac-managed-card {
			margin-top: 1.5rem;
			border: 1px solid rgba(0, 0, 0, 0.08);
			border-radius: 0;
			background: linear-gradient(180deg, rgba(255, 255, 255, 0.92), rgba(255, 255, 255, 0.82));
			padding: 1.5rem;
			box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
		}

		.aac-managed-card--embed {
			margin: 0;
			border-radius: 0;
			border: 0;
			background: transparent;
			box-shadow: none;
			padding: 0;
		}

		.aac-managed-card .entry-content,
		.aac-managed-card .pmpro {
			color: #1c1917;
		}

		.aac-managed-card .pmpro_section,
		.aac-managed-card .pmpro_card,
		.aac-managed-card .pmpro_message,
		.aac-managed-card form.pmpro_form,
		.aac-managed-card .pmpro_checkout_gateway,
		.aac-managed-card .pmpro_invoice,
		.aac-managed-card .pmpro_checkout-fields {
			border: 1px solid rgba(0, 0, 0, 0.08);
			border-radius: 0;
			background: rgba(255, 255, 255, 0.9);
			padding: 1.2rem;
		}

		.aac-managed-card .pmpro_section + .pmpro_section,
		.aac-managed-card .pmpro_card + .pmpro_card,
		.aac-managed-card .pmpro_section + .pmpro_actions_nav,
		.aac-managed-card .pmpro_checkout-fields + .pmpro_checkout-fields {
			margin-top: 1rem;
		}

		.aac-managed-card .pmpro_section_title,
		.aac-managed-card .pmpro_card_title,
		.aac-managed-card h2,
		.aac-managed-card h3 {
			color: #0c0a09;
		}

		.aac-managed-card .pmpro_card_actions,
		.aac-managed-card .pmpro_actions_nav {
			display: flex;
			flex-wrap: wrap;
			gap: 0.75rem;
			align-items: center;
		}

		body.pmpro-cancel .aac-managed-card .pmpro,
		body.pmpro-cancel .aac-managed-card .pmpro_section,
		body.pmpro-cancel .aac-managed-card .pmpro_card,
		body.pmpro-cancel .aac-managed-card form.pmpro_form,
		body.pmpro-cancel .aac-managed-card .pmpro_card_content {
			margin: 0;
			border: 0;
			border-radius: 0;
			background: transparent;
			box-shadow: none;
			padding: 0;
		}

		body.pmpro-cancel .aac-managed-card .pmpro_form_submit {
			margin-top: 1.25rem;
			padding-top: 0;
		}

		body.pmpro-billing .aac-managed-card .pmpro,
		body.pmpro-billing .aac-managed-card .pmpro_section,
		body.pmpro-billing .aac-managed-card .pmpro_card,
		body.pmpro-billing .aac-managed-card .pmpro_card_content {
			border: 0;
			border-radius: 0;
			background: transparent;
			box-shadow: none;
		}

		body.pmpro-billing .aac-managed-card .pmpro {
			padding: 0;
		}

		body.pmpro-billing .aac-managed-card .pmpro_section,
		body.pmpro-billing .aac-managed-card .pmpro_card,
		body.pmpro-billing .aac-managed-card .pmpro_card_content {
			padding: 0;
		}

		body.pmpro-billing .aac-managed-card .pmpro_spacer {
			display: none;
		}

		body.pmpro-billing .aac-managed-card .pmpro_section + .pmpro_section,
		body.pmpro-billing .aac-managed-card .pmpro_card + .pmpro_card,
		body.pmpro-billing .aac-managed-card .pmpro_actions_nav {
			margin-top: 1rem;
		}

		body.pmpro-billing .aac-managed-card .pmpro_section_title,
		body.pmpro-billing .aac-managed-card .pmpro_card_title {
			margin-bottom: 0.7rem;
		}

		body.pmpro-billing .aac-managed-card .pmpro_card_actions {
			margin-top: 0.75rem;
			padding-top: 0;
		}

		body.pmpro-checkout .aac-managed-card {
			border: 0 !important;
			background: transparent !important;
			box-shadow: none !important;
			padding: 0 !important;
		}

		body.pmpro-checkout .aac-managed-card .pmpro,
		body.pmpro-checkout .aac-managed-card .pmpro_section,
		body.pmpro-checkout .aac-managed-card form.pmpro_form,
		body.pmpro-checkout .aac-managed-card .pmpro_checkout_gateway,
		body.pmpro-checkout .aac-managed-card .pmpro_invoice {
			border: 0;
			border-radius: 0;
			background: transparent;
			box-shadow: none;
			padding: 0;
		}

		body.pmpro-checkout .aac-managed-card .pmpro_form_fieldset {
			margin: 0;
			padding: 0;
			border: 0;
			border-radius: 0 !important;
			background: transparent !important;
			box-shadow: none !important;
		}

		body.pmpro-checkout .aac-managed-card .pmpro_card,
		body.pmpro-checkout .aac-managed-card .pmpro_card_content,
		body.pmpro-checkout .aac-managed-card .pmpro_form_fieldset > .pmpro_card,
		body.pmpro-checkout .aac-managed-card .pmpro_checkout-fields > .pmpro_card,
		body.pmpro-checkout .aac-managed-card .pmpro_form_fieldset > .pmpro_card > .pmpro_card_content,
		body.pmpro-checkout .aac-managed-card .pmpro_checkout-fields > .pmpro_card > .pmpro_card_content,
		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__card,
		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__card,
		body.pmpro-checkout .aac-managed-card .aac-partner-family__card-inner,
		body.pmpro-checkout .aac-managed-card .aac-partner-family__dependents,
		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__summary,
		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__promo,
		body.pmpro-checkout .aac-managed-card .aac-donation-option,
		body.pmpro-checkout .aac-managed-card .aac-order-summary,
		body.pmpro-checkout .aac-managed-card .aac-order-summary__row {
			border-radius: 0 !important;
		}

		body.pmpro-checkout .aac-managed-card .pmpro_form_fieldset > .pmpro_card,
		body.pmpro-checkout .aac-managed-card .pmpro_checkout-fields > .pmpro_card,
		body.pmpro-checkout .aac-managed-card .pmpro_form_fieldset > .pmpro_card > .pmpro_card_content,
		body.pmpro-checkout .aac-managed-card .pmpro_checkout-fields > .pmpro_card > .pmpro_card_content {
			border-radius: 0 !important;
			box-shadow: none !important;
		}

		body.pmpro-checkout .aac-managed-card .pmpro_form_fieldset > .pmpro_card,
		body.pmpro-checkout .aac-managed-card .pmpro_checkout-fields > .pmpro_card {
			overflow: hidden;
		}

		body.pmpro-checkout .aac-managed-card .pmpro_form_fieldset > .pmpro_card > .pmpro_card_content,
		body.pmpro-checkout .aac-managed-card .pmpro_checkout-fields > .pmpro_card > .pmpro_card_content {
			border-radius: 0 !important;
			padding: 1.15rem 1.2rem !important;
		}

		body.pmpro-checkout .aac-managed-card .pmpro_form_fieldset > .pmpro_card > .pmpro_card_content {
			display: grid;
			gap: 0.7rem;
			align-content: start;
		}

		html:has(body.pmpro-checkout),
		body.pmpro-checkout,
		body.pmpro-checkout #page,
		body.pmpro-checkout .site,
		body.pmpro-checkout .site-content,
		body.pmpro-checkout .entry-content,
		body.pmpro-checkout #aac-member-portal-root,
		body.pmpro-checkout .aac-managed-shell,
		body.pmpro-checkout .aac-managed-card {
			background: #fff !important;
			background-image: none !important;
		}

		body.pmpro-checkout .aac-managed-card .pmpro,
		body.pmpro-checkout .aac-managed-card .pmpro_section,
		body.pmpro-checkout .aac-managed-card form.pmpro_form,
		body.pmpro-checkout .aac-managed-card .pmpro_checkout_gateway,
		body.pmpro-checkout .aac-managed-card .pmpro_invoice,
		body.pmpro-checkout .aac-managed-card .pmpro_checkout-fields,
		body.pmpro-checkout .aac-managed-card .pmpro_form_fieldset,
		body.pmpro-checkout .aac-managed-card .pmpro_card,
		body.pmpro-checkout .aac-managed-card .pmpro_card_content,
		body.pmpro-checkout .aac-managed-card #pmpro_payment_information_fields,
		body.pmpro-checkout .aac-managed-card #pmpro_payment_information_fields > .pmpro_card,
		body.pmpro-checkout .aac-managed-card #pmpro_payment_information_fields > .pmpro_card > .pmpro_card_content,
		body.pmpro-checkout .aac-managed-card #pmpro_payment_information_fields .pmpro_card_fields,
		body.pmpro-checkout .aac-managed-card #pmpro_payment_information_fields .pmpro_payment-discount-code,
		body.pmpro-checkout .aac-managed-card #pmpro_payment_information_fields .pmpro_form_fields,
		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__card,
		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__card,
		body.pmpro-checkout .aac-managed-card .aac-partner-family__card-inner,
		body.pmpro-checkout .aac-managed-card .aac-partner-family__dependents,
		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__summary,
		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__promo,
		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__summary-row,
		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__summary-row--total,
		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__pricing-note,
		body.pmpro-checkout .aac-managed-card .aac-checkout-autorenew,
		body.pmpro-checkout .aac-managed-card #pmpro_form_fieldset-donation #pmprodon_donation_input,
		body.pmpro-checkout .aac-managed-card .aac-order-summary,
		body.pmpro-checkout .aac-managed-card .aac-order-summary__row,
		body.pmpro-checkout .aac-managed-card .aac-order-summary__row--total {
			background: #fff !important;
			background-image: none !important;
		}

		body.pmpro-checkout .aac-managed-card .pmpro_form_legend {
			display: block;
			width: 100%;
			max-width: none;
			margin: 0 !important;
			padding: 0 !important;
		}

		body.pmpro-checkout .aac-managed-card .pmpro_form_legend:first-child,
		body.pmpro-checkout .aac-managed-card .pmpro_card_content > :first-child {
			margin-top: 0 !important;
			padding-top: 0 !important;
		}

		body.pmpro-checkout .aac-managed-card #pmpro_pricing_fields {
			margin-bottom: 1.1rem;
			padding-bottom: 1.1rem;
			border-bottom: 1px solid rgba(0, 0, 0, 0.08);
		}

		body.pmpro-checkout .aac-managed-card .pmpro_form_fieldset + .pmpro_form_fieldset,
		body.pmpro-checkout .aac-managed-card #pmpro_pricing_fields + .pmpro_form_fieldset {
			margin-top: 0.95rem;
			padding-top: 0.95rem;
			border-top: 1px solid rgba(0, 0, 0, 0.08);
		}

		body.pmpro-checkout .aac-managed-card .pmpro_form_heading,
		body.pmpro-checkout .aac-managed-card .pmpro_card_title,
		body.pmpro-checkout .aac-managed-card .pmpro_section_title {
			margin-top: 0;
			margin-bottom: 0.8rem;
			color: #0c0a09;
		}

		body.pmpro-checkout .aac-managed-card #pmpro_payment_information_fields .pmpro_form_legend {
			display: none;
		}

		body.pmpro-checkout .aac-managed-card #pmpro_payment_information_fields,
		body.pmpro-checkout .aac-managed-card #pmpro_payment_information_fields > .pmpro_card,
		body.pmpro-checkout .aac-managed-card #pmpro_payment_information_fields > .pmpro_card > .pmpro_card_content {
			background: #fff;
			border-radius: 0;
			box-shadow: none;
			color: #1c1917;
		}

		body.pmpro-checkout .aac-managed-card #pmpro_payment_information_fields > .pmpro_card > .pmpro_card_content,
		body.pmpro-checkout .aac-managed-card #pmpro_payment_information_fields .pmpro_card_fields,
		body.pmpro-checkout .aac-managed-card #pmpro_payment_information_fields .pmpro_payment-discount-code,
		body.pmpro-checkout .aac-managed-card #pmpro_payment_information_fields .pmpro_form_fields {
			gap: 0.45rem;
			background: #fff;
			border: 1px solid rgba(12, 10, 9, 0.1);
			border-radius: 0;
			box-shadow: none;
			color: #1c1917;
		}

		body.pmpro-checkout .aac-managed-card #pmpro_payment_information_fields .pmpro_payment-request-button,
		body.pmpro-checkout .aac-managed-card #pmpro_payment_information_fields .pmpro_payment-request-button .pmpro_form_heading {
			margin-top: 0;
			margin-bottom: 0.45rem;
		}

		body.pmpro-checkout .aac-managed-card #pmpro_social_login {
			display: none !important;
		}

		body.pmpro-checkout .aac-managed-card #pmpro_user_fields {
			display: block !important;
		}

		body.pmpro-checkout .aac-managed-card .pmpro_card_actions,
		body.pmpro-checkout .aac-managed-card .pmpro_form_submit {
			display: flex;
			justify-content: center;
			align-items: center;
			width: 100%;
			margin-top: 0.9rem;
			padding-top: 0;
		}

		body.pmpro-checkout .aac-managed-card .pmpro_message {
			padding: 0.95rem 1rem;
			border-radius: 0;
		}

		body.pmpro-checkout .aac-managed-card .pmpro_form_fields.pmpro_cols-2 {
			display: grid;
			grid-template-columns: repeat(2, minmax(0, 1fr));
			gap: 0.85rem 1rem;
		}

		body.pmpro-checkout .aac-managed-card .pmpro_cols-2 {
			display: grid;
			grid-template-columns: repeat(2, minmax(0, 1fr));
			gap: 0.85rem 1rem;
			width: 100%;
			align-items: start;
		}

		body.pmpro-checkout .aac-managed-card .pmpro_form_field {
			margin: 0;
			min-width: 0;
			width: 100% !important;
			max-width: none !important;
			float: none !important;
			clear: none !important;
			display: flex;
			flex-direction: column;
			align-self: start;
		}

		body.pmpro-checkout .aac-managed-card .pmpro_form_fields {
			gap: 0.85rem 1rem;
		}

		body.pmpro-checkout .aac-managed-card .aac-managed-two-up {
			display: grid;
			grid-template-columns: repeat(2, minmax(0, 1fr));
			gap: 0.85rem 1rem;
			width: 100%;
			align-items: start;
		}

		body.pmpro-checkout .aac-managed-card .pmpro_cols-2 > * {
			min-width: 0;
			width: 100% !important;
			max-width: none !important;
			margin: 0 !important;
			float: none !important;
		}

		body.pmpro-checkout .aac-managed-card .pmpro_cols-2::before,
		body.pmpro-checkout .aac-managed-card .pmpro_cols-2::after,
		body.pmpro-checkout .aac-managed-card .aac-managed-two-up::before,
		body.pmpro-checkout .aac-managed-card .aac-managed-two-up::after {
			display: none !important;
			content: none !important;
		}

		body.pmpro-checkout .aac-managed-card .pmpro_form_label {
			display: block;
			width: 100%;
			margin: 0 0 0.45rem;
			color: #0c0a09;
		}

		body.pmpro-checkout .aac-managed-card .pmpro_form_input,
		body.pmpro-checkout .aac-managed-card input[type="text"],
		body.pmpro-checkout .aac-managed-card input[type="email"],
		body.pmpro-checkout .aac-managed-card input[type="password"],
		body.pmpro-checkout .aac-managed-card input[type="tel"],
		body.pmpro-checkout .aac-managed-card input[type="date"],
		body.pmpro-checkout .aac-managed-card input[type="number"],
		body.pmpro-checkout .aac-managed-card select,
		body.pmpro-checkout .aac-managed-card textarea {
			min-height: 3rem;
			width: 100%;
			border-radius: 0;
			border: 1px solid #d6d3d1;
			background: #fff;
			box-shadow: none;
			padding: 0.85rem 0.95rem;
		}

		body.pmpro-checkout .aac-managed-card .pmpro_form_field .select2-container {
			width: 100% !important;
		}

		body.pmpro-checkout .aac-managed-card .select2-container--default .select2-selection--single {
			min-height: 3rem;
			border-radius: 0;
			border: 1px solid #d6d3d1;
			background: #fff;
		}

		body.pmpro-checkout .aac-managed-card .select2-container--default .select2-selection--single .select2-selection__rendered {
			padding-left: 1rem;
			line-height: 3rem;
			color: #0c0a09;
		}

		body.pmpro-checkout .aac-managed-card .select2-container--default .select2-selection--single .select2-selection__arrow {
			height: 3rem;
			right: 0.65rem;
		}

		body.pmpro-checkout .aac-managed-card .aac-email-availability {
			margin: 0.45rem 0 0;
			font-size: 0.9rem;
			line-height: 1.45;
		}

		body.pmpro-checkout .aac-managed-card .aac-email-availability[data-state="available"] {
			color: #166534;
		}

		body.pmpro-checkout .aac-managed-card .aac-email-availability[data-state="unavailable"] {
			color: #8f1515;
		}

		body.pmpro-checkout .aac-managed-card .aac-email-availability[data-state="checking"],
		body.pmpro-checkout .aac-managed-card .aac-email-availability[data-state="idle"] {
			color: #57534e;
		}

		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__intro {
			margin: 0 0 0.2rem;
			color: #57534e;
		}

		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__picker {
			display: grid;
			gap: 0.85rem;
		}

		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__none {
			display: inline-flex;
			align-items: center;
			gap: 0.55rem;
			width: fit-content;
			font-size: 0.95rem;
			font-weight: 700;
			color: #292524;
			cursor: pointer;
		}

		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__none input {
			margin: 0;
		}

		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__grid {
			display: grid;
			grid-template-columns: repeat(3, minmax(0, 1fr));
			gap: 1rem;
		}

		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__field {
			margin: 0;
			height: 100%;
		}

		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__label {
			display: block;
			height: 100%;
			margin: 0;
			cursor: pointer;
		}

		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__input {
			position: absolute;
			opacity: 0;
			pointer-events: none;
		}

		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__card {
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: flex-start;
			gap: 1rem;
			height: 100%;
			min-height: 16.5rem;
			padding: 1rem 1.05rem;
			border: 1px solid rgba(12, 10, 9, 0.1);
			border-radius: 0;
			background: #fff;
			box-shadow: none;
			color: #292524;
			transition: border-color 160ms ease, box-shadow 160ms ease, transform 160ms ease;
		}

		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__label:hover .aac-membership-discounts__card {
			transform: translateY(-2px);
			box-shadow: none;
		}

		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__input:focus-visible + .aac-membership-discounts__card {
			outline: 2px solid rgba(143, 21, 21, 0.28);
			outline-offset: 3px;
		}

		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__input:checked + .aac-membership-discounts__card {
			border-color: rgba(143, 21, 21, 0.92);
			box-shadow: none;
			background: #fff;
		}

		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__icon {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 4.5rem;
			height: 4.5rem;
			border-radius: 999px;
			background: rgba(143, 21, 21, 0.08);
			color: #8f1515;
		}

		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__icon svg {
			width: 2rem;
			height: 2rem;
		}

		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__input:checked + .aac-membership-discounts__card .aac-membership-discounts__icon {
			background: rgba(143, 21, 21, 0.08);
			color: #b71c1c;
		}

		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__body {
			display: flex;
			flex: 1 1 auto;
			flex-direction: column;
			gap: 0.7rem;
			width: 100%;
		}

		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__copy {
			display: grid;
			gap: 0.32rem;
			color: #57534e;
			text-align: center;
		}

		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__copy strong {
			color: #0c0a09;
			font-size: 1.02rem;
			line-height: 1.2;
		}

		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__footer {
			margin-top: auto;
			display: flex;
			justify-content: center;
		}

		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__price {
			display: inline-flex;
			align-items: center;
			gap: 0.4rem;
			width: fit-content;
			padding: 0.35rem 0.65rem;
			border-radius: 999px;
			background: rgba(143, 21, 21, 0.08);
			color: #8f1515;
			font-size: 0.82rem;
			font-weight: 700;
			text-transform: uppercase;
			letter-spacing: 0.08em;
		}

		body.pmpro-checkout .aac-managed-card #pmpro_form_fieldset-magazine-addons .pmpro_form_fields {
			gap: 0.8rem;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__intro {
			margin: 0 0 0.2rem;
			color: #57534e;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__grid {
			display: grid;
			grid-template-columns: repeat(2, minmax(0, 1fr));
			gap: 1rem;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__field {
			margin: 0;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__label {
			display: block;
			margin: 0;
			cursor: pointer;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__input {
			position: absolute;
			opacity: 0;
			pointer-events: none;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__card {
			display: grid;
			grid-template-rows: auto 1fr;
			height: 100%;
			overflow: hidden;
			border: 1px solid rgba(12, 10, 9, 0.1);
			border-radius: 0;
			background: rgba(255, 255, 255, 0.9);
			box-shadow: none;
			transition: border-color 160ms ease, box-shadow 160ms ease, transform 160ms ease;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__label:hover .aac-magazine-addons__card {
			transform: translateY(-2px);
			box-shadow: none;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__input:focus-visible + .aac-magazine-addons__card {
			outline: 2px solid rgba(143, 21, 21, 0.28);
			outline-offset: 3px;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__input:checked + .aac-magazine-addons__card {
			border-color: rgba(143, 21, 21, 0.52);
			box-shadow: none;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__cover {
			display: flex;
			align-items: center;
			justify-content: center;
			min-height: 15.5rem;
			padding: 1rem 1rem 0.35rem;
			background: linear-gradient(180deg, rgba(245, 240, 231, 0.98), rgba(236, 229, 215, 0.92));
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__cover-image {
			display: block;
			width: auto;
			max-width: 100%;
			height: 13.75rem;
			max-height: 100%;
			object-fit: contain;
			object-position: center top;
			filter: drop-shadow(0 14px 24px rgba(12, 10, 9, 0.12));
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__body {
			display: grid;
			gap: 0.95rem;
			padding: 1rem 1rem 1.05rem;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__copy {
			display: grid;
			gap: 0.35rem;
			color: #57534e;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__copy strong {
			color: #0c0a09;
			font-size: 1rem;
			line-height: 1.2;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__footer {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 0.75rem;
			flex-wrap: wrap;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__price {
			font-weight: 700;
			color: #8f1515;
			white-space: nowrap;
		}

		body.pmpro-checkout .aac-managed-card .aac-member-preferences__intro {
			margin: 1rem 0 0;
			color: #57534e;
			line-height: 1.7;
			text-align: center;
		}

		body.pmpro-checkout .aac-managed-card .aac-member-preferences__grid {
			display: grid;
			grid-template-columns: repeat(4, minmax(0, 1fr));
			justify-content: center;
			gap: 1rem;
			margin-top: 1.5rem;
			align-items: stretch;
			width: 100%;
			max-width: 100%;
			margin-left: auto;
			margin-right: auto;
		}

		body.pmpro-checkout .aac-managed-card .aac-member-preferences__card {
			display: grid;
			grid-template-rows: auto 1fr;
			height: 100%;
			max-width: none;
			padding: 0;
			border-radius: 0;
			overflow: hidden;
			border: 1px solid rgba(12, 10, 9, 0.1);
			box-shadow: none;
			background: #fff;
			color: #0c0a09;
			margin: 0 auto;
			width: 100%;
		}

		body.pmpro-checkout .aac-managed-card .aac-member-preferences__art {
			display: flex;
			align-items: center;
			justify-content: center;
			min-height: 12.75rem;
			padding: 0.75rem 0.75rem 0.25rem;
			background: linear-gradient(180deg, rgba(245, 240, 231, 0.98), rgba(236, 229, 215, 0.92));
			border-bottom: 1px solid rgba(12, 10, 9, 0.08);
		}

		body.pmpro-checkout .aac-managed-card .aac-member-preferences__content {
			display: grid;
			gap: 0.75rem;
			padding: 0.85rem 0.85rem 0.95rem;
		}

		body.pmpro-checkout .aac-managed-card .aac-member-preferences__cover-image {
			display: block;
			width: auto;
			max-width: 100%;
			height: 11.25rem;
			max-height: 100%;
			object-fit: contain;
			object-position: center top;
			filter: drop-shadow(0 10px 18px rgba(12, 10, 9, 0.1));
		}

		body.pmpro-checkout .aac-managed-card .aac-member-preferences__title-block {
			display: grid;
			gap: 0.35rem;
		}

		body.pmpro-checkout .aac-managed-card .aac-member-preferences__eyebrow {
			display: inline-block;
			font-size: 0.68rem;
			font-weight: 700;
			letter-spacing: 0.22em;
			text-transform: uppercase;
			color: #78716c;
		}

		body.pmpro-checkout .aac-managed-card .aac-member-preferences__title {
			margin: 0;
			font-size: 1rem;
			line-height: 1.2;
			font-weight: 700;
			color: #0c0a09;
		}

		body.pmpro-checkout .aac-managed-card .aac-member-preferences__description {
			margin: 0;
			color: #57534e;
			font-size: 0.92rem;
			line-height: 1.5;
		}

		body.pmpro-checkout .aac-managed-card .aac-member-preferences__choices {
			display: grid;
			grid-template-columns: repeat(2, minmax(0, 1fr));
			gap: 0.65rem;
			margin-top: auto;
			justify-items: center;
		}

		body.pmpro-checkout .aac-managed-card .aac-member-preferences__option {
			display: block;
			cursor: pointer;
		}

		body.pmpro-checkout .aac-managed-card .aac-member-preferences__input {
			position: absolute;
			opacity: 0;
			pointer-events: none;
		}

		body.pmpro-checkout .aac-managed-card .aac-member-preferences__choice {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 100%;
			min-height: 2.7rem;
			padding: 0.8rem 1.4rem;
			border-radius: 0;
			border: 1px solid rgba(12, 10, 9, 0.18);
			background: #fff;
			color: #292524;
			font-weight: 700;
			cursor: pointer;
			transition: background 160ms ease, border-color 160ms ease, transform 160ms ease, color 160ms ease;
		}

		body.pmpro-checkout .aac-managed-card .aac-member-preferences__choice:hover {
			transform: translateY(-1px);
			border-color: rgba(143, 21, 21, 0.55);
		}

		body.pmpro-checkout .aac-managed-card .aac-member-preferences__option:hover .aac-member-preferences__choice {
			transform: translateY(-1px);
			border-color: rgba(143, 21, 21, 0.55);
		}

		body.pmpro-checkout .aac-managed-card .aac-member-preferences__choice.is-active {
			background: #8f1515;
			border-color: #8f1515;
			color: #fff;
		}

		body.pmpro-checkout .aac-managed-card .aac-member-preferences__input:checked + .aac-member-preferences__choice {
			background: #8f1515;
			border-color: #8f1515;
			color: #fff;
		}

		body.pmpro-checkout #aaj_preference_div,
		body.pmpro-checkout #anac_preference_div,
		body.pmpro-checkout #american_climbing_journal_preference_div,
		body.pmpro-checkout #guidebook_preferences_div,
		body.pmpro-checkout #publications_preference_div,
		body.pmpro-checkout #birthdate_div {
			display: none !important;
		}

		body.pmpro-checkout .aac-managed-card .pmpro_form_field-password {
			position: relative;
		}

		body.pmpro-checkout .aac-managed-card .pmpro_form_field-password .pmpro_form_input-password {
			padding-right: 5.25rem;
		}

		body.pmpro-checkout .aac-managed-card .pmpro_form_field-password .pmpro_form_field-password-toggle {
			position: absolute;
			right: 0.95rem;
			bottom: 0.95rem;
			margin: 0;
		}

		body.pmpro-checkout .aac-managed-card .pmpro_form_field-password .pmpro_btn-password-toggle {
			border: 0 !important;
			background: transparent !important;
			box-shadow: none !important;
			padding: 0 !important;
			min-height: 0;
			color: #8f1515 !important;
			font-size: 0.72rem;
			font-weight: 700;
			letter-spacing: 0.14em;
			text-transform: uppercase;
		}

		body.pmpro-checkout .aac-managed-card .pmpro_form_field-password .pmpro_btn-password-toggle .pmpro_icon {
			display: none;
		}

		@media (max-width: 900px) {
			body.pmpro-checkout .aac-managed-card .pmpro_form_fields.pmpro_cols-2 {
				grid-template-columns: 1fr;
			}

			body.pmpro-checkout .aac-managed-card .pmpro_cols-2 {
				grid-template-columns: 1fr;
			}

			body.pmpro-checkout .aac-managed-card .aac-member-preferences__grid {
				grid-template-columns: repeat(2, minmax(0, 1fr));
				width: min(94vw, 38rem);
			}
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__selector {
			display: inline-flex;
			align-items: center;
			gap: 0.55rem;
			padding: 0.55rem 0.8rem;
			border: 1px solid rgba(12, 10, 9, 0.14);
			border-radius: 999px;
			background: rgba(12, 10, 9, 0.03);
			color: #292524;
			font-size: 0.92rem;
			font-weight: 700;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__check {
			position: relative;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 1.05rem;
			height: 1.05rem;
			border: 1.5px solid currentColor;
			border-radius: 0.3rem;
			background: #fff;
			color: inherit;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__check::after {
			content: '';
			width: 0.28rem;
			height: 0.58rem;
			border-right: 2px solid #fff;
			border-bottom: 2px solid #fff;
			transform: rotate(45deg) scale(0);
			transition: transform 160ms ease;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__input:checked + .aac-magazine-addons__card .aac-magazine-addons__selector {
			border-color: #8f1515;
			background: rgba(143, 21, 21, 0.1);
			color: #8f1515;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__input:checked + .aac-magazine-addons__card .aac-magazine-addons__check {
			background: #8f1515;
			color: #8f1515;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__input:checked + .aac-magazine-addons__card .aac-magazine-addons__check::after {
			transform: rotate(45deg) scale(1);
		}

		body.pmpro-checkout .aac-managed-card .aac-partner-family__intro {
			margin: 0 0 1rem;
			color: #57534e;
		}

		body.pmpro-checkout .aac-managed-card .aac-partner-family__mode {
			display: flex;
			flex-wrap: wrap;
			gap: 0.75rem;
		}

		body.pmpro-checkout .aac-managed-card .aac-partner-family__mode-option {
			display: inline-flex;
			align-items: center;
			gap: 0.55rem;
			padding: 0.8rem 1rem;
			border: 1px solid rgba(12, 10, 9, 0.12);
			border-radius: 999px;
			background: rgba(255, 255, 255, 0.92);
			color: #292524;
			font-weight: 600;
			cursor: pointer;
		}

		body.pmpro-checkout .aac-managed-card .aac-partner-family__details {
			display: grid;
			gap: 1rem;
			margin-top: 1rem;
		}

		body.pmpro-checkout .aac-managed-card .aac-partner-family__card {
			display: block;
			cursor: pointer;
		}

		body.pmpro-checkout .aac-managed-card .aac-partner-family__card input {
			position: absolute;
			opacity: 0;
			pointer-events: none;
		}

		body.pmpro-checkout .aac-managed-card .aac-partner-family__card-inner,
		body.pmpro-checkout .aac-managed-card .aac-partner-family__dependents {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 1rem;
			padding: 1rem 1.05rem;
			border: 1px solid rgba(12, 10, 9, 0.08);
			border-radius: 1rem;
			background: rgba(255, 255, 255, 0.92);
		}

		body.pmpro-checkout .aac-managed-card .aac-partner-family__card-copy {
			display: grid;
			gap: 0.25rem;
			color: #57534e;
		}

		body.pmpro-checkout .aac-managed-card .aac-partner-family__card-copy strong {
			color: #0c0a09;
		}

		body.pmpro-checkout .aac-managed-card .aac-partner-family__card-price {
			white-space: nowrap;
			font-weight: 700;
			color: #8f1515;
		}

		body.pmpro-checkout .aac-managed-card .aac-partner-family__card input:checked + .aac-partner-family__card-inner {
			border-color: rgba(143, 21, 21, 0.35);
			background: rgba(143, 21, 21, 0.08);
		}

		body.pmpro-checkout .aac-managed-card .aac-partner-family__dependents {
			align-items: flex-start;
		}

		body.pmpro-checkout .aac-managed-card .aac-partner-family__dependents .pmpro_form_label {
			margin: 0 0 0.45rem;
			font-size: 0.78rem;
			letter-spacing: 0.16em;
			text-transform: uppercase;
			color: #78716c;
		}

		body.pmpro-checkout .aac-managed-card .aac-partner-family__dependents select {
			min-width: 12rem;
		}

		body.pmpro-checkout .aac-managed-card .aac-partner-family__dependents-note {
			margin: 0.4rem 0 0;
			font-size: 0.9rem;
			color: #57534e;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__summary {
			margin: 0;
			padding: 1rem 1.05rem;
			border: 1px solid rgba(12, 10, 9, 0.08);
			border-radius: 1rem;
			background: linear-gradient(180deg, rgba(250, 249, 246, 0.98), rgba(245, 239, 228, 0.98));
			color: #292524;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__summary-header {
			display: grid;
			gap: 0.2rem;
			margin-bottom: 0.8rem;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__summary-title {
			margin: 0;
			font-size: 1rem;
			font-weight: 700;
			color: #0c0a09;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__summary-caption {
			margin: 0;
			color: #57534e;
			font-size: 0.92rem;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__promo {
			margin: 0 0 0.85rem;
			padding: 1rem;
			border: 1px solid rgba(12, 10, 9, 0.08);
			border-radius: 1rem;
			background: rgba(255, 255, 255, 0.7);
			display: grid;
			gap: 0.75rem;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__promo-copy {
			display: grid;
			gap: 0.2rem;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__promo-label {
			margin: 0;
			font-size: 0.82rem;
			font-weight: 700;
			letter-spacing: 0.12em;
			text-transform: uppercase;
			color: #0c0a09;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__promo-copy p {
			margin: 0;
			font-size: 0.92rem;
			color: #57534e;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__promo-form {
			display: grid;
			grid-template-columns: minmax(0, 1fr) auto;
			gap: 0.7rem;
			align-items: center;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__promo-input {
			width: 100%;
			min-height: 48px;
			padding: 0.8rem 1rem;
			border-radius: 0;
			border: 1px solid rgba(12, 10, 9, 0.14);
			background: #fff;
			color: #0c0a09;
			font: inherit;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__promo-button {
			min-height: 48px;
			padding: 0.8rem 1.2rem;
			border: 0;
			border-radius: 0;
			background: #000;
			color: #fff;
			font: inherit;
			font-weight: 700;
			cursor: pointer;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__promo-button:hover {
			background: #171717;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__promo-applied {
			display: flex;
			flex-wrap: wrap;
			align-items: center;
			gap: 0.6rem;
			font-size: 0.92rem;
			color: #57534e;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__promo-clear {
			padding: 0;
			border: 0;
			background: transparent;
			color: #8f1515;
			font: inherit;
			font-weight: 700;
			cursor: pointer;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__summary-rows {
			display: grid;
			gap: 0.5rem;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__summary-row {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 1rem;
			padding: 0.75rem 0.85rem;
			border: 1px solid rgba(12, 10, 9, 0.12);
			border-radius: 0;
			background: #fff;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__summary-row strong {
			color: #0c0a09;
			white-space: nowrap;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__summary-row--discount {
			color: #8f1515;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__summary-row--discount strong {
			color: #8f1515;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__summary-row--total {
			border-color: rgba(143, 21, 21, 0.45);
			background: #fff;
			color: #6b1010;
			font-weight: 700;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__summary-row--total strong {
			color: #8f1515;
		}

		@media (max-width: 720px) {
			body.pmpro-checkout .aac-managed-card .aac-magazine-addons__promo-form {
				grid-template-columns: 1fr;
			}
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__pricing-note {
			margin: 0;
			padding: 0.9rem 1rem;
			border: 1px solid rgba(143, 21, 21, 0.25);
			border-radius: 0;
			background: #fff;
			color: #6b1010;
			font-weight: 600;
		}

		body.pmpro-checkout .aac-managed-card #pmpro_autorenewal_checkbox .pmpro_form_fields {
			display: block;
		}

		body.pmpro-checkout .aac-managed-card .aac-checkout-autorenew {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 1rem;
			padding: 1rem 1.05rem;
			border: 1px solid rgba(12, 10, 9, 0.1);
			border-radius: 0;
			background: #fff;
			color: #1c1917;
		}

		body.pmpro-checkout .aac-managed-card .aac-checkout-autorenew__copy {
			display: grid;
			gap: 0.25rem;
		}

		body.pmpro-checkout .aac-managed-card .aac-checkout-autorenew__copy strong {
			color: #0c0a09;
		}

		body.pmpro-checkout .aac-managed-card .aac-checkout-autorenew__copy span {
			color: #57534e;
			font-size: 0.92rem;
			line-height: 1.45;
		}

		body.pmpro-checkout .aac-managed-card #pmpro_form_fieldset-donation .pmpro_form_fields-inline {
			display: grid;
			gap: 0.85rem;
		}

		body.pmpro-checkout .aac-managed-card #pmpro_form_fieldset-donation #donation_dropdown {
			display: none;
		}

		body.pmpro-checkout .aac-managed-card .aac-donation-picker {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(6.5rem, 1fr));
			gap: 0.6rem;
		}

		body.pmpro-checkout .aac-managed-card .aac-donation-option {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			min-height: 3rem;
			border: 1px solid rgba(143, 21, 21, 0.78);
			border-radius: 0;
			background: #b71c1c;
			color: #fff;
			font-size: 0.92rem;
			font-weight: 700;
			letter-spacing: 0.01em;
			text-transform: none;
			padding: 0 0.95rem;
			transition: background-color 160ms ease, border-color 160ms ease, color 160ms ease, transform 160ms ease;
		}

		body.pmpro-checkout .aac-managed-card .aac-donation-option:hover {
			transform: translateY(-1px);
			background: #8f1515;
			border-color: #8f1515;
		}

		body.pmpro-checkout .aac-managed-card .aac-donation-option[data-selected="true"] {
			border-color: #6f1010;
			background: #6f1010;
			color: #fff;
		}

		body.pmpro-checkout .aac-managed-card #pmpro_form_fieldset-donation #pmprodon_donation_input {
			display: none;
			align-items: center;
			gap: 0.55rem;
			margin-top: 0;
			padding: 0.85rem 0.95rem;
			border: 1px solid rgba(12, 10, 9, 0.1);
			border-radius: 1rem;
			background: rgba(255, 255, 255, 0.84);
		}

		body.pmpro-checkout .aac-managed-card #pmpro_form_fieldset-donation[data-aac-donation-mode="custom"] #pmprodon_donation_input {
			display: inline-flex;
		}

		body.pmpro-checkout .aac-managed-card #pmpro_form_fieldset-donation #pmprodon_donation_input input {
			width: 100%;
			max-width: 11rem;
			margin-top: 0;
		}

		body.pmpro-checkout .aac-managed-card .aac-donation-helper {
			margin: 0.35rem 0 0;
			color: #57534e;
		}

		.aac-managed-card .aac-order-summary {
			margin: 0 0 1.25rem;
			padding: 1.1rem 1.2rem;
			border: 1px solid rgba(12, 10, 9, 0.1);
			border-radius: 0;
			background: #fff;
		}

		.aac-managed-card .aac-order-summary__header {
			display: grid;
			gap: 0.25rem;
			margin-bottom: 0.9rem;
		}

		.aac-managed-card .aac-order-summary__header h2 {
			margin: 0;
			font-size: 1.05rem;
			color: #0c0a09;
		}

		.aac-managed-card .aac-order-summary__header p,
		.aac-managed-card .aac-order-summary__meta {
			margin: 0;
			color: #57534e;
		}

		.aac-managed-card .aac-order-summary__rows {
			display: grid;
			gap: 0.5rem;
		}

		.aac-managed-card .aac-order-summary__row {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 1rem;
			padding: 0.75rem 0.9rem;
			border: 1px solid rgba(12, 10, 9, 0.1);
			border-radius: 0;
			background: #fff;
			color: #1c1917;
		}

		.aac-managed-card .aac-order-summary__row strong {
			color: #0c0a09;
			white-space: nowrap;
		}

		.aac-managed-card .aac-order-summary__row--total {
			border-color: rgba(183, 28, 28, 0.82);
			background: #fff;
			color: #8f1515;
			font-weight: 700;
		}

		.aac-managed-card .aac-order-summary__row--total strong {
			color: #ef4444;
		}

		.aac-managed-card .aac-order-summary__meta {
			margin-top: 0.8rem;
			font-size: 0.92rem;
		}

		@media (max-width: 760px) {
			body.pmpro-checkout .aac-managed-card .aac-membership-discounts__grid {
				grid-template-columns: minmax(0, 1fr);
			}

			body.pmpro-checkout .aac-managed-card .aac-magazine-addons__grid {
				grid-template-columns: minmax(0, 1fr);
			}

			body.pmpro-checkout .aac-managed-card .aac-member-preferences__grid {
				grid-template-columns: minmax(0, 1fr);
			}
		}

		@media (max-width: 1100px) {
			body.pmpro-checkout .aac-managed-card .aac-membership-discounts__grid {
				grid-template-columns: repeat(2, minmax(0, 1fr));
			}

			body.pmpro-checkout .aac-managed-card .aac-member-preferences__grid {
				grid-template-columns: repeat(2, minmax(0, 1fr));
			}
		}

		@media (max-width: 760px) {
			body.pmpro-checkout .aac-managed-card .aac-member-preferences__grid {
				grid-template-columns: minmax(0, 1fr);
			}

			body.pmpro-checkout .aac-managed-card .aac-member-preferences__choices {
				grid-template-columns: minmax(0, 1fr);
				gap: 0.5rem;
				justify-items: stretch;
			}

			body.pmpro-checkout .aac-managed-card .aac-member-preferences__choice {
				min-height: 2.45rem;
				min-width: 0;
				padding: 0.7rem 0.85rem;
				font-size: 0.88rem;
				line-height: 1.15;
			}
		}

		.aac-managed-card a {
			color: #8f1515;
		}

		.aac-managed-card a:hover {
			color: #6b1010;
		}

		.aac-managed-account-summary {
			display: grid;
			gap: 1rem;
			margin-bottom: 1.5rem;
			padding: 1.35rem;
			border: 1px solid rgba(3, 0, 0, 0.08);
			border-radius: 1.5rem;
			background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(255, 248, 238, 0.94));
			box-shadow: 0 18px 40px rgba(16, 10, 7, 0.06);
		}

		.aac-managed-account-summary__grid {
			display: grid;
			gap: 0.9rem;
			grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
		}

		.aac-managed-account-summary__item {
			padding: 1rem 1.1rem;
			border: 1px solid rgba(12, 10, 9, 0.08);
			border-radius: 1.15rem;
			background: rgba(255, 255, 255, 0.82);
		}

		.aac-managed-account-summary__label {
			display: block;
			margin-bottom: 0.35rem;
			color: #57534e;
			font-size: 0.68rem;
			font-weight: 700;
			letter-spacing: 0.18em;
			text-transform: uppercase;
		}

		.aac-managed-account-summary__value {
			color: #0c0a09;
			font-size: 1.05rem;
			font-weight: 700;
			line-height: 1.35;
		}

		.aac-managed-account-summary__toggle {
			display: flex;
			flex-wrap: wrap;
			align-items: center;
			justify-content: space-between;
			gap: 1rem;
			padding: 1rem 1.1rem;
			border: 1px solid rgba(12, 10, 9, 0.08);
			border-radius: 1.15rem;
			background: rgba(3, 0, 0, 0.02);
		}

		.aac-managed-account-summary__toggle-copy strong {
			display: block;
			margin-bottom: 0.25rem;
			color: #0c0a09;
			font-size: 0.98rem;
		}

		.aac-managed-account-summary__toggle-copy span {
			color: #57534e;
			font-size: 0.9rem;
			line-height: 1.55;
		}

		.aac-managed-toggle {
			display: inline-flex;
			align-items: center;
			gap: 0.75rem;
			cursor: pointer;
		}

		.aac-managed-toggle input {
			position: absolute;
			opacity: 0;
			pointer-events: none;
		}

		.aac-managed-toggle__track {
			position: relative;
			width: 3.35rem;
			height: 2rem;
			border-radius: 999px;
			background: rgba(12, 10, 9, 0.18);
			transition: background-color 0.2s ease;
		}

		.aac-managed-toggle__track::after {
			content: '';
			position: absolute;
			top: 0.2rem;
			left: 0.2rem;
			width: 1.6rem;
			height: 1.6rem;
			border-radius: 50%;
			background: #fff;
			box-shadow: 0 6px 14px rgba(0, 0, 0, 0.18);
			transition: transform 0.2s ease;
		}

		.aac-managed-toggle input:checked + .aac-managed-toggle__track {
			background: #8f1515;
		}

		.aac-managed-toggle input:checked + .aac-managed-toggle__track::after {
			transform: translateX(1.35rem);
		}

		.aac-managed-toggle__state {
			color: #0c0a09;
			font-size: 0.82rem;
			font-weight: 700;
			letter-spacing: 0.12em;
			text-transform: uppercase;
		}

		.aac-managed-card input[type="text"],
		.aac-managed-card input[type="email"],
		.aac-managed-card input[type="password"],
		.aac-managed-card input[type="tel"],
		.aac-managed-card input[type="number"],
		.aac-managed-card select,
		.aac-managed-card textarea {
			width: 100%;
			margin-top: 0.35rem;
			border: 1px solid #d6d3d1;
			border-radius: 0.8rem;
			background: #fff;
			color: #0c0a09;
			padding: 0.8rem 0.95rem;
			box-sizing: border-box;
		}

		.aac-managed-card input[type="submit"],
		.aac-managed-card button,
		.aac-managed-card .pmpro_btn,
		.aac-managed-card .button {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			min-height: 2.85rem;
			border: 0;
			border-radius: 0;
			background: #b71c1c;
			color: #fff;
			font-weight: 700;
			letter-spacing: 0.08em;
			text-transform: uppercase;
			padding: 0 1.2rem;
			cursor: pointer;
		}

		.aac-managed-card input[type="submit"]:hover,
		.aac-managed-card button:hover,
		.aac-managed-card .pmpro_btn:hover,
		.aac-managed-card .button:hover {
			background: #8f1515;
			color: #fff;
		}

		.aac-managed-card table {
			width: 100%;
			border-collapse: collapse;
		}

		.aac-managed-card th,
		.aac-managed-card td {
			padding: 0.8rem 0.35rem;
			border-bottom: 1px solid rgba(0, 0, 0, 0.08);
			text-align: left;
		}

		.aac-managed-card .pmpro_message:last-child,
		.aac-managed-card .pmpro_form_submit:last-child,
		.aac-managed-card form.pmpro_form > .pmpro_form_submit:last-child {
			margin-bottom: 0;
		}

		.aac-managed-card .pmpro_form_submit {
			padding-bottom: 0;
		}

		.aac-managed-card--embed .pmpro {
			margin-bottom: 0;
		}

		.aac-managed-card--embed .pmpro,
		.aac-managed-card--embed .pmpro_section,
		.aac-managed-card--embed .pmpro_card,
		.aac-managed-card--embed .pmpro_message,
		.aac-managed-card--embed form.pmpro_form,
		.aac-managed-card--embed .pmpro_checkout_gateway,
		.aac-managed-card--embed .pmpro_invoice,
		.aac-managed-card--embed .pmpro_checkout-fields,
		.aac-managed-card--embed .pmpro_card_content {
			margin: 0;
			border: 0;
			border-radius: 0;
			background: transparent;
			box-shadow: none;
			padding: 0;
		}

		.aac-managed-card--embed .pmpro_section + .pmpro_section,
		.aac-managed-card--embed .pmpro_card + .pmpro_card,
		.aac-managed-card--embed .pmpro_checkout-fields + .pmpro_checkout-fields,
		.aac-managed-card--embed .pmpro_message + form.pmpro_form,
		.aac-managed-card--embed .pmpro_message + .pmpro_invoice {
			margin-top: 1.25rem;
		}

		.aac-managed-card--embed .pmpro_section_title,
		.aac-managed-card--embed .pmpro_card_title {
			margin-top: 0;
			margin-bottom: 0.85rem;
		}

		.aac-managed-card--embed form.pmpro_form {
			padding-bottom: 0.35rem;
		}

		.aac-managed-card--embed .pmpro_form_submit {
			padding-bottom: 0;
		}

		.aac-public-main {
			padding: 0 0 4rem;
		}

		.aac-public-content-wrap {
			max-width: none;
			margin: 0 auto;
			padding: 0 1.5rem 4rem;
		}

		.aac-public-content > * + * {
			margin-top: 1.5rem;
		}

		.aac-public-content .is-layout-constrained > :where(:not(.alignleft):not(.alignright):not(.alignfull)) {
			max-width: none !important;
			margin-left: 0 !important;
			margin-right: 0 !important;
		}

		.aac-public-content .is-layout-constrained > .alignwide,
		.aac-public-content .is-layout-constrained > .alignfull {
			max-width: none !important;
		}

		.aac-public-content .wp-block-group,
		.aac-public-content .wp-block-columns,
		.aac-public-content .wp-block-cover,
		.aac-public-content .wp-block-buttons,
		.aac-public-content .wp-block-list {
			margin-top: 0;
			margin-bottom: 0;
		}

		.aac-public-content > .wp-block-group:first-child {
			margin-left: -1.5rem;
			margin-right: -1.5rem;
		}

		.aac-public-content > .wp-block-group:first-child > .wp-block-cover {
			position: relative;
			overflow: hidden;
			min-height: 440px !important;
			padding-top: 4rem !important;
			padding-right: min(3rem, 5vw) !important;
			padding-bottom: 5rem !important;
			padding-left: min(3rem, 5vw) !important;
			border-radius: 0 !important;
			box-shadow: 0 30px 90px rgba(3, 0, 0, 0.24);
			background-color: #f3ecde !important;
			background-position: center right !important;
			background-repeat: no-repeat !important;
			background-size: cover !important;
		}

		.aac-public-page--benefits .aac-public-content > .wp-block-group:first-child > .wp-block-cover {
			background-image:
				linear-gradient(90deg, rgba(255, 251, 244, 0.98) 0%, rgba(255, 251, 244, 0.94) 44%, rgba(255, 251, 244, 0.36) 100%),
				url('https://static1.squarespace.com/static/55830fd9e4b0ec758c892f81/t/68091665002095413034d056/1745426021790/FDenney_-216.jpg?format=1500w') !important;
		}

		.aac-public-page--rescue .aac-public-content > .wp-block-group:first-child > .wp-block-cover {
			background-image:
				url('https://static1.squarespace.com/static/55830fd9e4b0ec758c892f81/t/603d35eb8c227a557e29b607/1614624239865/AAC_NMM_SocialAds_Illustrations_Rec-11.jpg?format=1500w') !important;
			background-position: center center !important;
		}

		.aac-public-page--rescue .aac-public-content > .wp-block-group:first-child h1,
		.aac-public-page--rescue .aac-public-content > .wp-block-group:first-child p {
			display: none !important;
		}

		.aac-public-content > .wp-block-group:first-child > .wp-block-cover::after {
			content: '';
			position: absolute;
			left: 0;
			right: 0;
			bottom: -1px;
			height: 28px;
			background:
				linear-gradient(135deg, transparent 0 46%, rgba(255, 251, 244, 1) 46% 54%, transparent 54% 100%);
			opacity: 0.9;
		}

		.aac-public-content > .wp-block-group:first-child .wp-block-cover__background {
			background: transparent !important;
		}

		.aac-public-content > .wp-block-group:first-child .wp-block-cover__inner-container {
			max-width: none;
			margin: 0 auto;
			padding: 0;
		}

		.aac-public-content > .wp-block-group:first-child .wp-block-cover__inner-container > * {
			max-width: 56rem;
		}

		.aac-public-content > .wp-block-group:first-child p {
			color: #030000 !important;
			font-size: clamp(1rem, 0.92rem + 0.25vw, 1.15rem) !important;
			line-height: 1.7 !important;
			text-shadow: none !important;
		}

		.aac-public-content > .wp-block-group:first-child p.has-accent-color {
			color: #8f1515 !important;
			font-size: 0.72rem !important;
			font-weight: 700;
			letter-spacing: 0.3em !important;
			text-transform: uppercase;
		}

		.aac-public-content > .wp-block-group:first-child h1 {
			margin-top: 1rem;
			font-size: clamp(3rem, 2.2rem + 3vw, 5.75rem) !important;
			line-height: 0.95 !important;
			color: #030000 !important;
			text-shadow: none !important;
		}

		.aac-public-content .wp-block-buttons {
			display: flex;
			flex-wrap: wrap;
			gap: 0.75rem;
		}

		.aac-public-content .wp-block-button__link {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			min-height: 3rem;
			border: 1px solid transparent;
			border-radius: 999px !important;
			background: #8f1515;
			color: #fff !important;
			font-size: 0.76rem;
			font-weight: 700;
			letter-spacing: 0.16em;
			text-transform: uppercase;
			padding: 0 1.4rem;
			text-decoration: none !important;
			transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease;
		}

		.aac-public-content .wp-block-button__link:hover {
			background: #6b1010;
			color: #fff;
		}

		.aac-public-content > .wp-block-group:first-child .wp-block-button:not(.is-style-outline) .wp-block-button__link {
			background: #f8c235 !important;
			color: #000 !important;
		}

		.aac-public-content > .wp-block-group:first-child .wp-block-button:not(.is-style-outline) .wp-block-button__link:hover {
			background: #e1ae14;
		}

		.aac-public-content .wp-block-button.is-style-outline .wp-block-button__link {
			border-color: rgba(3, 0, 0, 0.12);
			background: rgba(255, 255, 255, 0.84);
			color: #030000 !important;
		}

		.aac-public-content .wp-block-button.is-style-outline .wp-block-button__link:hover {
			border-color: rgba(143, 21, 21, 0.45);
			background: #fff;
			color: #8f1515;
		}

		.aac-public-content > .wp-block-group:first-child .wp-block-button.is-style-outline .wp-block-button__link {
			border-color: rgba(255, 255, 255, 0.2);
			background: rgba(255, 255, 255, 0.03);
			color: #fff !important;
		}

		.aac-public-content > .wp-block-group:first-child .wp-block-button.is-style-outline .wp-block-button__link:hover {
			border-color: rgba(255, 255, 255, 0.48);
			background: rgba(255, 255, 255, 0.08);
			color: #fff;
		}

		.aac-public-content .wp-block-spacer {
			height: 1.5rem !important;
		}

		.aac-public-content .wp-block-group.has-background:not(.wp-block-cover) {
			background:
				linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(255, 250, 243, 0.98)),
				linear-gradient(135deg, #fffefe 0%, #f5efe4 100%) !important;
			border: 1px solid rgba(3, 0, 0, 0.1) !important;
			box-shadow: 0 24px 80px rgba(0, 0, 0, 0.08);
		}

		.aac-public-content .wp-block-group.has-accent-5-background-color {
			background:
				linear-gradient(180deg, rgba(255, 247, 226, 0.98), rgba(252, 242, 213, 0.98)),
				linear-gradient(135deg, #fff8e7 0%, #f8e8bc 100%) !important;
		}

		.aac-public-content .wp-block-column > .wp-block-group {
			height: 100%;
		}

		.aac-public-content h2,
		.aac-public-content h3 {
			margin-top: 0;
			margin-bottom: 0.75rem;
			color: #030000 !important;
			line-height: 1.06;
		}

		.aac-public-content h2 {
			font-size: clamp(1.8rem, 1.4rem + 1vw, 2.65rem);
		}

		.aac-public-content h3 {
			font-size: clamp(1.2rem, 1.08rem + 0.4vw, 1.5rem);
		}

		.aac-public-content p,
		.aac-public-content li {
			color: #39312d !important;
			font-size: 1rem;
			line-height: 1.8;
		}

		.aac-public-content ul,
		.aac-public-content ol {
			padding-left: 1.2rem;
		}

		.aac-public-content li + li {
			margin-top: 0.55rem;
		}

		.aac-public-content a {
			color: #8f1515;
		}

		.aac-public-content a:hover {
			color: #6b1010;
		}

		.aac-public-content .has-text-color,
		.aac-public-content .has-base-color,
		.aac-public-content .has-primary-color,
		.aac-public-content .has-secondary-color,
		.aac-public-content .has-contrast-2-color,
		.aac-public-content .has-contrast-3-color {
			color: #1f1a17 !important;
		}

		.aac-public-content .has-background:not(.wp-block-cover),
		.aac-public-content .has-base-background-color:not(.wp-block-cover),
		.aac-public-content .has-accent-5-background-color:not(.wp-block-cover) {
			color: #1f1a17 !important;
		}

		.aac-public-content > .wp-block-group:first-child .has-text-color,
		.aac-public-content > .wp-block-group:first-child .has-base-color,
		.aac-public-content > .wp-block-group:first-child .wp-block-heading,
		.aac-public-content > .wp-block-group:first-child .wp-block-paragraph,
		.aac-public-content > .wp-block-group:first-child li {
			color: #030000 !important;
		}

		.aac-public-content > .wp-block-group:first-child h1,
		.aac-public-content > .wp-block-group:first-child h2,
		.aac-public-content > .wp-block-group:first-child h3 {
			color: #030000 !important;
		}

		.aac-public-content > .wp-block-group:first-child p.has-accent-color {
			color: #8f1515 !important;
		}

		.aac-public-content .wp-block-button__link.has-text-color,
		.aac-public-content .wp-block-button__link.has-base-color,
		.aac-public-content .wp-block-button__link.has-contrast-color {
			color: #fff !important;
		}

		.aac-public-content .wp-block-button:not(.is-style-outline) .wp-block-button__link.has-text-color,
		.aac-public-content .wp-block-button:not(.is-style-outline) .wp-block-button__link.has-base-color {
			background: #8f1515 !important;
			color: #fff !important;
		}

		.aac-public-content > .wp-block-group:first-child .wp-block-button:not(.is-style-outline) .wp-block-button__link.has-text-color,
		.aac-public-content > .wp-block-group:first-child .wp-block-button:not(.is-style-outline) .wp-block-button__link.has-contrast-color {
			background: #f8c235 !important;
			color: #000 !important;
		}

		.aac-public-content .wp-block-button.is-style-outline .wp-block-button__link.has-text-color {
			background: rgba(255, 255, 255, 0.84) !important;
			color: #030000 !important;
		}

		.aac-public-content > .wp-block-group:first-child .wp-block-button.is-style-outline .wp-block-button__link.has-text-color {
			background: rgba(255, 255, 255, 0.72) !important;
			color: #030000 !important;
		}

		@media (max-width: 960px) {
			.aac-managed-header__row,
			.aac-managed-header__bar {
				display: flex;
				flex-direction: column;
				align-items: stretch;
			}

			.aac-managed-logo,
			.aac-managed-actions {
				padding: 1rem 1rem 0;
				border: 0;
			}

			.aac-managed-topnav {
				flex-wrap: wrap;
				padding: 0.75rem 1rem 1rem;
			}

			.aac-managed-layout {
				display: block;
			}

			.aac-managed-sidebar {
				position: static;
				width: auto;
				height: auto;
				overflow: visible;
				border-right: 0;
				border-bottom: 1px solid rgba(0, 0, 0, 0.08);
				padding: 1rem;
			}

			.aac-managed-sidebar__section-title {
				opacity: 1;
				max-height: none;
				transform: none;
				margin-bottom: 0.55rem;
			}

			.aac-managed-sidebar a {
				justify-content: flex-start;
				padding: 0.75rem;
			}

			.aac-managed-sidebar__label {
				position: static;
				min-height: 0;
				padding: 0;
				border: 0;
				background: transparent;
				box-shadow: none;
				opacity: 1;
				pointer-events: auto;
				transform: none;
			}

			.aac-managed-main {
				padding-top: 1rem;
			}

			.aac-public-content > .wp-block-group:first-child {
				margin-left: -1.5rem;
				margin-right: -1.5rem;
			}

			.aac-public-content > .wp-block-group:first-child > .wp-block-cover {
				min-height: 360px !important;
				padding-top: 3rem !important;
				padding-bottom: 4rem !important;
				background-position: 68% center !important;
			}

			.aac-public-content > .wp-block-group:first-child .wp-block-cover__inner-container > * {
				max-width: 100%;
			}
		}
	</style>
<?php wp_head(); ?>
</head>
<body <?php body_class($is_embedded_pmpro_page ? 'aac-member-portal-embed' : (($is_portal_page && !$is_managed_pmpro_page) ? 'aac-member-portal-fullscreen' : ($is_public_shell_page ? 'aac-member-portal-fullscreen aac-member-portal-public-shell aac-public-page--' . sanitize_html_class($post->post_name) : 'aac-member-portal-fullscreen aac-member-portal-managed-shell'))); ?>>
<?php wp_body_open(); ?>
<?php if ($is_portal_page && !$is_managed_pmpro_page) : ?>
	<?php
	while (have_posts()) :
		the_post();
		echo do_shortcode('[' . AAC_Member_Portal_Plugin::SHORTCODE . ']');
	endwhile;
	?>
<?php elseif ($is_embedded_pmpro_page) : ?>
	<section class="aac-managed-card aac-managed-card--embed">
		<?php
		while (have_posts()) :
			the_post();
			ob_start();
			the_content();
			$fullscreen_content = ob_get_clean();
			if (isset($portal_plugin) && $portal_plugin instanceof AAC_Member_Portal_Plugin) {
				$fullscreen_content = $portal_plugin->normalize_pmpro_checkout_publication_markup($fullscreen_content);
			}
			echo $fullscreen_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		endwhile;
		?>
	</section>
	<script>
		(function () {
			const messageType = 'aac-pmpro-checkout-height';
			const redirectTarget = <?php echo wp_json_encode($public_post_purchase_login_url); ?>;
			const shouldRedirectToPortal = <?php echo $is_embedded_confirmation ? 'true' : 'false'; ?>;
			const contentRoot =
				document.querySelector('.aac-managed-card--embed') ||
				document.querySelector('.pmpro') ||
				document.body;

			const postHeight = () => {
				if (!contentRoot) {
					return;
				}

				const rect = contentRoot.getBoundingClientRect();
				const height = Math.ceil(rect.height);

				if (window.parent && window.parent !== window) {
					window.parent.postMessage({ type: messageType, height }, window.location.origin);
				}
			};

			const redirectParentToPortal = () => {
				if (!shouldRedirectToPortal || !redirectTarget) {
					return;
				}

				try {
					if (window.top && window.top !== window) {
						window.top.location.assign(redirectTarget);
						return;
					}
				} catch (error) {
					// Fall back to the current window below if the parent cannot be reached.
				}

				window.location.assign(redirectTarget);
			};

			window.addEventListener('load', postHeight);
			window.addEventListener('resize', postHeight);

			if (typeof ResizeObserver !== 'undefined') {
				const observer = new ResizeObserver(postHeight);
				if (document.body) {
					observer.observe(document.body);
				}
			}

			setTimeout(postHeight, 150);
			setTimeout(postHeight, 600);

			if (shouldRedirectToPortal) {
				setTimeout(redirectParentToPortal, 250);
			}
		}());
	</script>
<?php elseif ($is_public_shell_page) : ?>
	<div class="aac-managed-header">
		<div class="aac-managed-header__inner">
			<div class="aac-managed-header__row">
				<a class="aac-managed-logo" href="<?php echo esc_url($public_home_url); ?>">
					<img
						src="https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/09/light-header-logo.svg"
						alt="American Alpine Club Logo"
					>
				</a>

				<div class="aac-managed-actions">
					<a class="aac-managed-pill aac-managed-pill--ghost" href="<?php echo esc_url(home_url('/search/')); ?>">Search</a>
					<a class="aac-managed-pill aac-managed-pill--danger" href="<?php echo esc_url($public_donate_url); ?>">Donate</a>
					<?php if ($is_logged_in) : ?>
						<a class="aac-managed-pill aac-managed-pill--ghost" href="<?php echo esc_url($public_profile_url); ?>">Member Profile</a>
						<a class="aac-managed-pill aac-managed-pill--primary" href="<?php echo esc_url(wp_logout_url($portal_url . '#/login')); ?>">Log Out</a>
					<?php else : ?>
						<a class="aac-managed-pill aac-managed-pill--primary" href="<?php echo esc_url($public_login_url); ?>">Login</a>
					<?php endif; ?>
				</div>
			</div>

			<nav class="aac-managed-topnav" aria-label="Primary">
				<?php foreach ($top_nav as $item) : ?>
					<div class="aac-managed-topnav__item">
						<a class="aac-managed-topnav__trigger" href="<?php echo esc_url($item['href']); ?>">
							<span><?php echo esc_html($item['label']); ?></span>
							<span class="aac-managed-topnav__caret" aria-hidden="true">+</span>
						</a>
							<div class="aac-managed-topnav__panel">
								<div class="aac-managed-topnav__panel-inner">
									<span class="aac-managed-topnav__panel-title"><?php echo esc_html($item['label']); ?></span>
									<ul>
										<?php foreach ($item['children'] as $child) : ?>
											<li>
												<a class="aac-managed-topnav__link" href="<?php echo esc_url($child['href']); ?>">
													<?php echo esc_html($child['label']); ?>
											</a>
										</li>
									<?php endforeach; ?>
								</ul>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</nav>
		</div>
	</div>

	<main class="aac-public-main">
		<div class="aac-public-content-wrap">
			<div class="aac-public-content entry-content">
				<?php
				while (have_posts()) :
					the_post();
					ob_start();
					the_content();
					$public_shell_content = ob_get_clean();
					if (isset($portal_plugin) && $portal_plugin instanceof AAC_Member_Portal_Plugin) {
						$public_shell_content = $portal_plugin->normalize_pmpro_checkout_publication_markup($public_shell_content);
					}
					echo $public_shell_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				endwhile;
				?>
			</div>
		</div>
	</main>
<?php else : ?>
	<div class="aac-managed-shell">
		<header class="aac-managed-header">
			<div class="aac-managed-header__inner">
				<div class="aac-managed-header__bar">
					<a class="aac-managed-logo" href="<?php echo esc_url($public_home_url); ?>">
						<img
							src="https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/09/light-header-logo.svg"
							alt="American Alpine Club Logo"
						>
					</a>

					<nav class="aac-managed-topnav" aria-label="Primary">
						<?php foreach ($top_nav as $item) : ?>
							<div class="aac-managed-topnav__item">
								<a class="aac-managed-topnav__trigger" href="<?php echo esc_url($item['href']); ?>">
									<span><?php echo esc_html($item['label']); ?></span>
									<span class="aac-managed-topnav__caret" aria-hidden="true">+</span>
								</a>
								<div class="aac-managed-topnav__panel">
									<div class="aac-managed-topnav__panel-inner">
										<span class="aac-managed-topnav__panel-title"><?php echo esc_html($item['label']); ?></span>
										<ul>
											<?php foreach ($item['children'] as $child) : ?>
												<li>
													<a class="aac-managed-topnav__link" href="<?php echo esc_url($child['href']); ?>">
														<?php echo esc_html($child['label']); ?>
													</a>
												</li>
											<?php endforeach; ?>
										</ul>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</nav>

					<div class="aac-managed-actions">
						<a
							class="aac-managed-pill aac-managed-pill--primary aac-managed-pill--icon"
							href="<?php echo esc_url(wp_logout_url($portal_url . '#/login')); ?>"
							aria-label="<?php esc_attr_e('Log Out', 'aac-member-portal'); ?>"
							title="<?php esc_attr_e('Log Out', 'aac-member-portal'); ?>"
						>
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg>
						</a>
					</div>
				</div>
			</div>
		</header>

		<div class="aac-managed-layout">
			<aside class="aac-managed-sidebar" aria-label="Member portal navigation">
				<?php foreach ($portal_sections as $section) : ?>
					<section class="aac-managed-sidebar__section">
						<p class="aac-managed-sidebar__section-title"><?php echo esc_html($section['title']); ?></p>
						<ul>
							<?php foreach ($section['items'] as $item) : ?>
								<li>
									<a href="<?php echo esc_url($item['href']); ?>"<?php echo !empty($item['active']) ? ' aria-current="page"' : ''; ?>>
										<span class="aac-managed-sidebar__icon" aria-hidden="true"><?php echo aac_member_portal_sidebar_icon_svg($item['icon'] ?? 'user'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										<span class="aac-managed-sidebar__label"><?php echo esc_html($item['label']); ?></span>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endforeach; ?>
			</aside>

			<main class="aac-managed-main">
				<div class="aac-managed-main__inner">
					<section class="aac-managed-hero">
						<p class="aac-managed-hero__kicker"><?php echo esc_html($page_kicker); ?></p>
						<h1><?php echo esc_html($page_title); ?></h1>
						<p><?php echo esc_html($page_description); ?></p>
							<div class="aac-managed-actions-row">
								<a class="aac-managed-pill <?php echo $is_account_page ? 'aac-managed-pill--primary' : 'aac-managed-pill--ghost'; ?>" href="<?php echo esc_url($account_url); ?>">Account</a>
								<a class="aac-managed-pill <?php echo $is_billing_page ? 'aac-managed-pill--primary' : 'aac-managed-pill--ghost'; ?>" href="<?php echo esc_url($managed_billing_url); ?>">Billing</a>
								<a class="aac-managed-pill <?php echo $is_orders_page ? 'aac-managed-pill--primary' : 'aac-managed-pill--ghost'; ?>" href="<?php echo esc_url($orders_url); ?>">Orders</a>
								<a class="aac-managed-pill <?php echo $is_cancel_page ? 'aac-managed-pill--primary' : 'aac-managed-pill--ghost'; ?>" href="<?php echo esc_url($cancel_url); ?>">Cancel</a>
								<a class="aac-managed-pill <?php echo $is_confirmation_page ? 'aac-managed-pill--primary' : 'aac-managed-pill--ghost'; ?>" href="<?php echo esc_url($confirmation_url); ?>">Confirmation</a>
						</div>
					</section>

					<?php if ($is_account_page && $current_member_id > 0 && $current_primary_membership) : ?>
						<section class="aac-managed-account-summary">
							<div class="aac-managed-account-summary__grid">
								<div class="aac-managed-account-summary__item">
									<span class="aac-managed-account-summary__label">Membership Level</span>
									<span class="aac-managed-account-summary__value"><?php echo esc_html($current_primary_membership['tier'] ?: 'Free'); ?></span>
								</div>
								<div class="aac-managed-account-summary__item">
									<span class="aac-managed-account-summary__label">Renewal Date</span>
									<span class="aac-managed-account-summary__value">
										<?php
										echo esc_html(
											$current_auto_renew && !empty($current_renewal_date)
												? date_i18n(get_option('date_format'), strtotime($current_renewal_date))
												: 'Not scheduled'
										);
										?>
									</span>
								</div>
								<div class="aac-managed-account-summary__item">
									<span class="aac-managed-account-summary__label">Expiration Date</span>
									<span class="aac-managed-account-summary__value">
										<?php
										echo esc_html(
											!$current_auto_renew && !empty($current_expiration_date)
												? date_i18n(get_option('date_format'), strtotime($current_expiration_date))
												: 'Not scheduled'
										);
										?>
									</span>
								</div>
							</div>
							<div class="aac-managed-account-summary__toggle">
								<div class="aac-managed-account-summary__toggle-copy">
									<strong>Automatic Renewals</strong>
									<span>Use the toggle to manage recurring billing for this membership. Turning it off takes you to cancellation; turning it on sends you to the membership billing or checkout flow.</span>
								</div>
								<label class="aac-managed-toggle">
									<input
										type="checkbox"
										<?php checked($current_auto_renew); ?>
										data-aac-autorenew-toggle
										data-enable-url="<?php echo esc_url($current_membership_actions['billing_url'] ?: ($current_membership_actions['current_level_checkout_url'] ?: $checkout_url)); ?>"
										data-disable-url="<?php echo esc_url($current_membership_actions['cancel_url'] ?: $cancel_url); ?>"
									/>
									<span class="aac-managed-toggle__track" aria-hidden="true"></span>
									<span class="aac-managed-toggle__state"><?php echo $current_auto_renew ? 'On' : 'Off'; ?></span>
								</label>
							</div>
						</section>
					<?php endif; ?>

					<section class="aac-managed-card">
						<?php
						while (have_posts()) :
							the_post();
							ob_start();
							the_content();
							$managed_shell_content = ob_get_clean();
							if (isset($portal_plugin) && $portal_plugin instanceof AAC_Member_Portal_Plugin) {
								$managed_shell_content = $portal_plugin->normalize_pmpro_checkout_publication_markup($managed_shell_content);
							}
							echo $managed_shell_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						endwhile;
						?>
					</section>
				</div>
			</main>
		</div>
	</div>
<?php endif; ?>
<script>
	(function () {
		const buildPreferredLoggedInName = () => {
			const nameCandidates = [];
			const currentUserFirstName = String(window.AAC_CURRENT_USER_FIRST_NAME || '').trim();
			const currentUserLastName = String(window.AAC_CURRENT_USER_LAST_NAME || '').trim();
			const runtimeFullName = [currentUserFirstName, currentUserLastName].filter(Boolean).join(' ').trim();
			if (runtimeFullName) {
				nameCandidates.push(runtimeFullName);
			}

			const checkoutFirstName = String(document.querySelector('input[name="bfirstname"]')?.value || '').trim();
			const checkoutLastName = String(document.querySelector('input[name="blastname"]')?.value || '').trim();
			const checkoutFullName = [checkoutFirstName, checkoutLastName].filter(Boolean).join(' ').trim();
			if (checkoutFullName) {
				nameCandidates.push(checkoutFullName);
			}

			const accountName = String(document.querySelector('input[name="name"]')?.value || '').trim();
			if (accountName) {
				nameCandidates.push(accountName);
			}

			if (currentUserDisplayName) {
				nameCandidates.push(currentUserDisplayName);
			}

			return nameCandidates.find((candidate) => candidate && candidate.includes(' ')) || nameCandidates.find(Boolean) || '';
		};

		const currentUserEmail = <?php echo wp_json_encode($is_logged_in ? wp_get_current_user()->user_email : ''); ?>;
		const currentUserDisplayName = <?php
			if ($is_logged_in) {
				$current_user = wp_get_current_user();
				$account_info = get_user_meta($current_user->ID, 'aac_account_info', true);
				$account_first_name = is_array($account_info) ? trim((string) ($account_info['first_name'] ?? '')) : '';
				$account_last_name = is_array($account_info) ? trim((string) ($account_info['last_name'] ?? '')) : '';
				$account_name = is_array($account_info) ? trim((string) ($account_info['name'] ?? '')) : '';
				$display_name = trim($account_first_name . ' ' . $account_last_name);
				if ($display_name === '' && $account_name !== '') {
					$display_name = $account_name;
				}
				if ($display_name === '') {
					$display_name = trim(($current_user->first_name ?? '') . ' ' . ($current_user->last_name ?? ''));
				}
				if ($display_name === '') {
					$display_name = $current_user->display_name ?: $current_user->user_email;
				}
				echo wp_json_encode($display_name);
			} else {
				echo wp_json_encode('');
			}
		?>;
		const emailAvailabilityEndpoint = new URL('/wp-json/aac/v1/email-availability', window.location.origin).toString();

		const buildUsernameFromEmail = (value) => {
			const normalized = String(value || '')
				.trim()
				.toLowerCase()
				.replace(/[@.+-]+/g, '_')
				.replace(/[^a-z0-9_]+/g, '_')
				.replace(/^_+|_+$/g, '');

			return normalized || 'aac_member';
		};

		const formatUsd = (value) => new Intl.NumberFormat('en-US', {
			style: 'currency',
			currency: 'USD',
			minimumFractionDigits: 2,
			maximumFractionDigits: 2,
		}).format(Number.isFinite(value) ? value : 0);
		const checkoutProfileDefaults = <?php echo wp_json_encode($checkout_profile_defaults); ?>;
		const publicationCardImages = <?php echo wp_json_encode($portal_design_settings['publication_tile_images'] ?? []); ?>;
		const defaultPublicationCardImages = {
			aaj: 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/08/image-asset-95.jpeg',
			anac: 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/08/image-asset-28.jpeg',
			acj: 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Calder-Davey-Homepage-Filler-4.jpg',
			guidebook: 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Calder-Davey-Homepage-Filler-2.jpg',
		};

		const escapeHtml = (value) => String(value ?? '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');

		const parseCurrencyValue = (value) => {
			const match = String(value || '').match(/\$([\d,]+(?:\.\d{2})?)/);
			if (!match) {
				return null;
			}

			const parsed = Number.parseFloat(match[1].replace(/,/g, ''));
			return Number.isFinite(parsed) ? parsed : null;
		};

		const getNativeDiscountCodeInputs = () => Array.from(document.querySelectorAll('#pmpro_discount_code, #pmpro_other_discount_code'));

		const getNativeDiscountCodeButton = () =>
			document.getElementById('discount_code_button')
			|| document.getElementById('other_discount_code_button');

		const getNativeDiscountCodeMessage = () => document.getElementById('discount_code_message');

		const getDiscountCodeState = () => {
			const populatedInput = getNativeDiscountCodeInputs().find((input) => (input?.value || '').trim() !== '');
			if (populatedInput) {
				return (populatedInput.value || '').trim();
			}

			const summaryInput = document.querySelector('[data-aac-discount-code-form] input[name="discount_code"]');
			if (summaryInput && (summaryInput.value || '').trim() !== '') {
				return summaryInput.value.trim();
			}

			return String(window.__aacAppliedDiscountCode || '').trim();
		};

		const getPmproMembershipAmount = (fallbackAmount) => {
			const codeLevel = window.pmpropbc?.code_level || null;
			const nocodeLevel = window.pmpropbc?.nocode_level || null;
			const nocodeInitialPayment = Number.parseFloat(nocodeLevel?.initial_payment ?? '');
			const codeInitialPayment = Number.parseFloat(codeLevel?.initial_payment ?? '');
			if (
				Number.isFinite(codeInitialPayment)
				&& codeInitialPayment >= 0
				&& Number.isFinite(nocodeInitialPayment)
				&& Math.abs(codeInitialPayment - nocodeInitialPayment) >= 0.01
			) {
				return codeInitialPayment;
			}

			const nocodeBillingAmount = Number.parseFloat(nocodeLevel?.billing_amount ?? '');
			const codeBillingAmount = Number.parseFloat(codeLevel?.billing_amount ?? '');
			if (
				Number.isFinite(codeBillingAmount)
				&& codeBillingAmount >= 0
				&& Number.isFinite(nocodeBillingAmount)
				&& Math.abs(codeBillingAmount - nocodeBillingAmount) >= 0.01
			) {
				return codeBillingAmount;
			}

			const priceText = document.querySelector('#pmpro_level_cost .pmpro_level_cost_text strong')?.textContent
				|| document.querySelector('#pmpro_level_cost')?.textContent
				|| '';
			return parseCurrencyValue(priceText) ?? fallbackAmount;
		};

		const buildDiscountCodeMarkup = () => {
			const appliedCode = getDiscountCodeState();
			return `
				<div class="aac-magazine-addons__promo" data-aac-discount-code>
					<div class="aac-magazine-addons__promo-copy">
						<p class="aac-magazine-addons__promo-label">Promo or Discount Code</p>
						<p>Apply a PMPro-generated discount code before payment.</p>
					</div>
					<div class="aac-magazine-addons__promo-form" data-aac-discount-code-form>
						<input
							type="text"
							name="discount_code"
							class="aac-magazine-addons__promo-input"
							placeholder="Enter code"
							value="${escapeHtml(appliedCode)}"
							autocomplete="off"
						/>
						<button type="button" class="aac-magazine-addons__promo-button" data-aac-discount-code-apply>Apply Code</button>
					</div>
					<p class="pmpro_message" data-aac-discount-code-message style="display: none;"></p>
					${appliedCode ? `
						<div class="aac-magazine-addons__promo-applied">
							<span>Applied code: <strong>${escapeHtml(appliedCode)}</strong></span>
							<button type="button" class="aac-magazine-addons__promo-clear" data-aac-discount-code-clear>Remove code</button>
						</div>
					` : ''}
				</div>
			`;
		};

		const bindDiscountCodeForm = (summary) => {
			const wrapper = summary?.querySelector('[data-aac-discount-code-form]');
			if (wrapper && wrapper.dataset.aacBound !== 'true') {
				const applyDiscountCode = () => {
					const nextCode = (wrapper.querySelector('input[name="discount_code"]')?.value || '').trim();
					window.__aacAppliedDiscountCode = nextCode;
					getNativeDiscountCodeInputs().forEach((input) => {
						input.value = nextCode;
					});
					getNativeDiscountCodeButton()?.click();
					window.setTimeout(syncMagazineAddonSummary, 250);
					window.setTimeout(syncMagazineAddonSummary, 900);
				};

				wrapper.querySelector('[data-aac-discount-code-apply]')?.addEventListener('click', applyDiscountCode);
				wrapper.querySelector('input[name="discount_code"]')?.addEventListener('keydown', (event) => {
					if (event.key !== 'Enter') {
						return;
					}

					event.preventDefault();
					applyDiscountCode();
				});
				wrapper.dataset.aacBound = 'true';
			}

			const clearButton = summary?.querySelector('[data-aac-discount-code-clear]');
			if (clearButton && clearButton.dataset.aacBound !== 'true') {
				clearButton.addEventListener('click', () => {
					window.__aacAppliedDiscountCode = '';
					getNativeDiscountCodeInputs().forEach((input) => {
						input.value = '';
					});
					window.location.reload();
				});
				clearButton.dataset.aacBound = 'true';
			}

			const summaryMessage = summary?.querySelector('[data-aac-discount-code-message]');
			const nativeMessage = getNativeDiscountCodeMessage();
			if (summaryMessage && nativeMessage) {
				const messageText = (nativeMessage.textContent || '').trim();
				summaryMessage.textContent = messageText;
				summaryMessage.className = nativeMessage.className ? `pmpro_message ${nativeMessage.className}` : 'pmpro_message';
				summaryMessage.style.display = messageText ? '' : 'none';
			}
		};

		const getCurrentCheckoutLevelId = () => Number.parseInt(document.getElementById('pmpro_level')?.value || '0', 10) || 0;

		const getCurrentCheckoutLevelName = () => {
			const levelId = getCurrentCheckoutLevelId();
			const levels = window.pmpro?.all_levels || window.pmpro?.all_levels_formatted_text || {};
			const preferredName =
				window.pmpropbc?.nocode_level?.name?.trim()
				|| levels[String(levelId)]?.name?.trim()
				|| document.querySelector('.pmpro_level_name_text strong')?.textContent?.trim()
				|| '';
			return preferredName && !/^membership$/i.test(preferredName) ? preferredName : 'Membership';
		};

		const currentLevelSupportsDiscountTiers = () => {
			const levelName = String(getCurrentCheckoutLevelName() || '').trim().toLowerCase();
			if (!levelName || levelName === 'membership') {
				return false;
			}

			return levelName === 'partner';
		};

		const getCurrentCheckoutBasePrice = () => {
			const datasetBasePrice = [
				document.getElementById('pmpro_form_fieldset-membership-discounts')?.dataset?.aacMembershipBasePrice,
				document.getElementById('pmpro_form_fieldset-partner-family')?.dataset?.aacPartnerFamilyBasePrice,
				document.getElementById('pmpro_form_fieldset-magazine-addons')?.dataset?.aacMagazineBasePrice,
			]
				.map((value) => Number.parseFloat(value || ''))
				.find((value) => Number.isFinite(value) && value >= 0);
			if (Number.isFinite(datasetBasePrice)) {
				return datasetBasePrice;
			}

			const levelId = getCurrentCheckoutLevelId();
			const levels = window.pmpro?.all_levels || window.pmpro?.all_levels_formatted_text || {};
			const level = levels[String(levelId)] || null;
			const initialPayment = Number.parseFloat(level?.initial_payment ?? '');
			if (Number.isFinite(initialPayment) && initialPayment >= 0) {
				return initialPayment;
			}

			const billingAmount = Number.parseFloat(level?.billing_amount ?? '');
			if (Number.isFinite(billingAmount) && billingAmount >= 0) {
				return billingAmount;
			}

			return null;
		};

		const buildMembershipLineItemLabel = (membershipName) => {
			const normalized = String(membershipName || '')
				.replace(/\s+membership(?:\s+membership)+$/i, ' Membership')
				.trim();
			if (!normalized || /^membership$/i.test(normalized)) {
				return 'Membership';
			}

			return /membership$/i.test(normalized) ? normalized : `${normalized} Membership`;
		};

		const getProratedMembershipSummaryLabel = (membershipName) => {
			const membershipLabel = buildMembershipLineItemLabel(membershipName);
			return isCurrentCheckoutProrated()
				? `${membershipLabel} (prorated amount due today)`
				: membershipLabel;
		};

		const isCurrentCheckoutProrated = () => {
			const levelId = getCurrentCheckoutLevelId();
			const levels = window.pmpro?.all_levels || window.pmpro?.all_levels_formatted_text || {};
			const level = levels[String(levelId)] || null;
			const initialPayment = Number.parseFloat(level?.initial_payment ?? '');
			const billingAmount = Number.parseFloat(level?.billing_amount ?? '');
			return Number.isFinite(initialPayment) && Number.isFinite(billingAmount) && Math.abs(initialPayment - billingAmount) >= 0.01;
		};

		const buildMemberPreferenceCards = (fieldset, currentLevelId) => {
			if (!fieldset) {
				return;
			}

			if (fieldset.querySelector('.aac-server-member-preferences')) {
				return;
			}

			const tshirtField = document.getElementById('t_shirt_div');
			const legacyPublicationField =
				document.getElementById('publications_preference_div') ||
				fieldset.querySelector('.pmpro_form_field-publications_preference');
			const aajField =
				document.getElementById('aaj_preference_div') ||
				fieldset.querySelector('.pmpro_form_field-aaj_preference');
			const anacField =
				document.getElementById('anac_preference_div') ||
				fieldset.querySelector('.pmpro_form_field-anac_preference');
			const acjField =
				document.getElementById('american_climbing_journal_preference_div') ||
				fieldset.querySelector('.pmpro_form_field-american_climbing_journal_preference');
			const guidebookField =
				document.getElementById('guidebook_preferences_div') ||
				fieldset.querySelector('.pmpro_form_field-guidebook_preferences');

			const showTshirtPreference = currentLevelId >= 2;
			const showPublicationPreferences = currentLevelId > 2;
			let intro = fieldset.querySelector('.aac-member-preferences__intro');
			if (!intro) {
				intro = document.createElement('p');
				intro.className = 'aac-member-preferences__intro';
				intro.textContent = 'Choose how you would like to receive each AAC publication. Print keeps the mailed edition on your membership, while digital keeps the experience paperless.';
			}

			let cardsGrid = fieldset.querySelector('.aac-member-preferences__grid');
			if (!cardsGrid) {
				cardsGrid = document.createElement('div');
				cardsGrid.className = 'aac-member-preferences__grid';
			}

			const hideOriginalField = (field) => {
				if (!field) {
					return;
				}
				field.hidden = true;
				field.style.display = 'none';
			};

			hideOriginalField(legacyPublicationField);
			hideOriginalField(aajField);
			hideOriginalField(anacField);
			hideOriginalField(acjField);
			hideOriginalField(guidebookField);

			if (tshirtField) {
				tshirtField.hidden = !showTshirtPreference;
				tshirtField.style.display = showTshirtPreference ? '' : 'none';
			}

			if (!showPublicationPreferences) {
				intro.remove();
				cardsGrid.remove();
				return;
			}

			const legacyPublicationSelect = legacyPublicationField?.querySelector('select');
			const aajSelect = aajField?.querySelector('select');
			const anacSelect = anacField?.querySelector('select');
			const acjSelect = acjField?.querySelector('select');
			const guidebookSelect = guidebookField?.querySelector('select');
			const resolvedPublicationCardImages = {
				aaj: publicationCardImages.aaj || defaultPublicationCardImages.aaj,
				anac: publicationCardImages.anac || defaultPublicationCardImages.anac,
				acj: publicationCardImages.acj || defaultPublicationCardImages.acj,
				guidebook: publicationCardImages.guidebook || defaultPublicationCardImages.guidebook,
			};

			if (!intro.parentNode) {
				if (tshirtField) {
					tshirtField.insertAdjacentElement('afterend', intro);
				} else {
					fieldset.querySelector('.pmpro_form_fields')?.prepend(intro);
				}
			}

			if (!cardsGrid.parentNode) {
				intro.insertAdjacentElement('afterend', cardsGrid);
			}

			const createPreferenceCard = ({ themeClass, eyebrow, title, description, fieldName, selectElement, imageUrl, onChange }) => {
				if (!selectElement) {
					return null;
				}

				const card = document.createElement('article');
				card.className = `aac-member-preferences__card ${themeClass}`;
				card.dataset.aacPrefSource = fieldName;
				if (imageUrl) {
					card.style.setProperty('--aac-member-pref-image', `url("${String(imageUrl).replace(/"/g, '&quot;')}")`);
				}
				card.innerHTML = `
					<div class="aac-member-preferences__art">${imageUrl ? `<img src="${String(imageUrl).replace(/"/g, '&quot;')}" alt="${title} cover" class="aac-member-preferences__cover-image" />` : ''}</div>
					<div class="aac-member-preferences__content">
						<div class="aac-member-preferences__title-block">
							<span class="aac-member-preferences__eyebrow">${eyebrow}</span>
							<h3 class="aac-member-preferences__title">${title}</h3>
						</div>
						<p class="aac-member-preferences__description">${description}</p>
						<div class="aac-member-preferences__choices">
							<button type="button" class="aac-member-preferences__choice" data-value="Print">Print</button>
							<button type="button" class="aac-member-preferences__choice" data-value="Digital">Digital</button>
						</div>
					</div>
				`;

				const syncCardState = () => {
					const nextValue = (selectElement.value || 'Digital').trim() === 'Print' ? 'Print' : 'Digital';
					selectElement.value = nextValue;
					card.querySelectorAll('.aac-member-preferences__choice').forEach((choice) => {
						choice.classList.toggle('is-active', choice.dataset.value === nextValue);
					});
				};

				card.querySelectorAll('.aac-member-preferences__choice').forEach((choice) => {
					choice.addEventListener('click', () => {
						selectElement.value = choice.dataset.value;
						selectElement.dispatchEvent(new Event('change', { bubbles: true }));
						if (typeof onChange === 'function') {
							onChange(choice.dataset.value);
						}
						syncMagazineAddonSummary();
						document.querySelectorAll(`[data-aac-pref-source="${fieldName}"]`).forEach((node) => {
							node.dispatchEvent(new CustomEvent('aac:sync-card-state'));
						});
					});
				});

				if (selectElement.dataset.aacCardSyncBound !== 'true') {
					selectElement.addEventListener('change', syncCardState);
					selectElement.dataset.aacCardSyncBound = 'true';
				}

				card.addEventListener('aac:sync-card-state', syncCardState);
				syncCardState();

				return card;
			};

			cardsGrid.innerHTML = '';
			[
				createPreferenceCard({
					themeClass: 'aac-member-preferences__card--journal',
					eyebrow: 'Annual',
					title: 'American Alpine Journal',
					description: 'Annual climbing journal. Choose print delivery or digital-only access.',
					fieldName: 'aaj_preference',
					selectElement: aajSelect,
					imageUrl: resolvedPublicationCardImages.aaj,
					onChange: (value) => {
						if (legacyPublicationSelect) {
							legacyPublicationSelect.value = value;
							legacyPublicationSelect.dispatchEvent(new Event('change', { bubbles: true }));
						}
					},
				}),
				createPreferenceCard({
					themeClass: 'aac-member-preferences__card--accidents',
					eyebrow: 'Annual',
					title: 'Accidents in North American Climbing',
					description: 'Annual accident review. Choose print delivery or digital-only access.',
					fieldName: 'anac_preference',
					selectElement: anacSelect,
					imageUrl: resolvedPublicationCardImages.anac,
				}),
				createPreferenceCard({
					themeClass: 'aac-member-preferences__card--journal',
					eyebrow: 'Journal',
					title: 'American Climbing Journal',
					description: 'Member stories and club updates. Choose print delivery or digital-only access.',
					fieldName: 'american_climbing_journal_preference',
					selectElement: acjSelect,
					imageUrl: resolvedPublicationCardImages.acj,
				}),
				createPreferenceCard({
					themeClass: 'aac-member-preferences__card--guidebook',
					eyebrow: 'Quarterly',
					title: 'Guidebook to Membership',
					description: 'Quarterly member publication. Choose print delivery or digital-only access.',
					fieldName: 'guidebook_preferences',
					selectElement: guidebookSelect,
					imageUrl: resolvedPublicationCardImages.guidebook,
				}),
			].filter(Boolean).forEach((card) => cardsGrid.appendChild(card));
		};

		const enhancePmproProfileInformation = () => {
			const socialLoginFieldset = document.getElementById('pmpro_social_login');
			const socialLoginActions = document.getElementById('pmpro_card_actions-social_login');
			const pricingFieldset = document.getElementById('pmpro_pricing_fields');
			const userFieldsFieldset = document.getElementById('pmpro_user_fields');
			const billingFieldset = document.getElementById('pmpro_billing_address_fields');
			if (!billingFieldset || billingFieldset.dataset.aacProfileEnhanced === 'true') {
				return;
			}

			const billingFields = billingFieldset.querySelector('.pmpro_form_fields');
			if (!billingFields) {
				return;
			}

			billingFieldset.dataset.aacProfileEnhanced = 'true';

			if (userFieldsFieldset) {
				userFieldsFieldset.hidden = false;
				userFieldsFieldset.style.display = 'block';
			}

			document.querySelectorAll('style').forEach((styleNode) => {
				if (styleNode.textContent && styleNode.textContent.includes('#pmpro_user_fields')) {
					styleNode.textContent = styleNode.textContent.replace(/#pmpro_user_fields\s*\{[^}]*\}/g, '');
				}
			});

			if (socialLoginActions) {
				socialLoginActions.remove();
			}

			if (socialLoginFieldset) {
				socialLoginFieldset.remove();
			}

			if (pricingFieldset) {
				pricingFieldset.hidden = true;
				pricingFieldset.style.display = 'none';
			}

			const accountHeading = userFieldsFieldset?.querySelector('.pmpro_form_heading');
			if (accountHeading) {
				accountHeading.textContent = 'Create Account';
			}

			const accountFields = userFieldsFieldset?.querySelector('.pmpro_form_fields');
			const emailInput = userFieldsFieldset?.querySelector('input[name="bemail"]');
			const confirmEmailInput = userFieldsFieldset?.querySelector('input[name="bconfirmemail"]');
			const passwordInput = userFieldsFieldset?.querySelector('input[name="password"]');
			const confirmPasswordInput = userFieldsFieldset?.querySelector('input[name="password2"]');
			const birthdateField = document.getElementById('birthdate_div');
			const tshirtField = document.getElementById('t_shirt_div');
			const personalDetailsFieldset = document.getElementById('pmpro_form_fieldset-personal-details');
			const emailField = emailInput?.closest('.pmpro_form_field');
			const confirmEmailField = confirmEmailInput?.closest('.pmpro_form_field');
			const passwordField = passwordInput?.closest('.pmpro_form_field');
			const confirmPasswordField = confirmPasswordInput?.closest('.pmpro_form_field');
			if (
				accountFields &&
				emailField &&
				confirmEmailField &&
				passwordField &&
				confirmPasswordField &&
				accountFields.dataset.aacAccountRowsBuilt !== '1'
			) {
				const firstRow = document.createElement('div');
				firstRow.className = 'pmpro_cols-2 aac-managed-two-up';
				firstRow.append(emailField, passwordField);
				const secondRow = document.createElement('div');
				secondRow.className = 'pmpro_cols-2 aac-managed-two-up';
				secondRow.append(confirmEmailField, confirmPasswordField);
				accountFields.append(firstRow, secondRow);
				Array.from(accountFields.querySelectorAll('.pmpro_cols-2')).forEach((row) => {
					if (!row.children.length) {
						row.remove();
					}
				});
				accountFields.dataset.aacAccountRowsBuilt = '1';
			}

			const billingHeading = billingFieldset.querySelector('.pmpro_form_heading');
			if (billingHeading) {
				billingHeading.textContent = 'Contact Information';
			}

			if (birthdateField) {
				birthdateField.remove();
			}

			[tshirtField].filter(Boolean).forEach((field) => {
				billingFields.appendChild(field);
			});

			if (billingFields && billingFields.dataset.aacContactRowsBuilt !== '1') {
				const buildTwoUpRow = (fieldIds) => {
					const fields = fieldIds
						.map((fieldId) => document.getElementById(fieldId))
						.filter(Boolean);
					if (!fields.length) {
						return;
					}
					const row = document.createElement('div');
					row.className = 'pmpro_cols-2 aac-managed-two-up';
					fields.forEach((field) => row.appendChild(field));
					billingFields.appendChild(row);
				};

				[
					['first_name_div', 'last_name_div'],
					['baddress1_div', 'baddress2_div'],
					['bcity_div', 'bstate_div'],
					['bzipcode_div', 'bcountry_div'],
					['bphone_div', 't_shirt_div'],
				].forEach(buildTwoUpRow);

				Array.from(billingFields.querySelectorAll('.pmpro_cols-2')).forEach((row) => {
					if (!row.children.length) {
						row.remove();
					}
				});

				billingFields.dataset.aacContactRowsBuilt = '1';
			}

			if (personalDetailsFieldset) {
				const personalFields = personalDetailsFieldset.querySelector('.pmpro_form_fields');
				if (!personalFields || !personalFields.children.length) {
					personalDetailsFieldset.remove();
				}
			}

			const memberPreferencesFieldset =
				document.getElementById('pmpro_form_fieldset-publication-preferences') ||
				document.getElementById('pmpro_form_fieldset-member-preferences') ||
				document.getElementById('pmpro_form_fieldset-more-information');
			const memberPreferencesFields = memberPreferencesFieldset?.querySelector('.pmpro_form_fields');
			const memberPreferencesHeading = memberPreferencesFieldset?.querySelector('.pmpro_form_heading');

			if (memberPreferencesFieldset && memberPreferencesHeading) {
				memberPreferencesHeading.textContent = 'Publication Preferences';
			}

			const moreInformationFieldset = document.getElementById('pmpro_form_fieldset-more-information');
			if (
				moreInformationFieldset &&
				memberPreferencesFieldset &&
				moreInformationFieldset !== memberPreferencesFieldset &&
				memberPreferencesFields
			) {
				const moreInformationFields = moreInformationFieldset.querySelector('.pmpro_form_fields');
				if (moreInformationFields) {
					Array.from(moreInformationFields.children).forEach((field) => {
						memberPreferencesFields.appendChild(field);
					});
				}
				moreInformationFieldset.remove();
			}

			const discountFieldset = document.getElementById('pmpro_form_fieldset-membership-discounts');
			if (discountFieldset?.parentNode && billingFieldset.parentNode === discountFieldset.parentNode) {
				discountFieldset.parentNode.insertBefore(discountFieldset, billingFieldset);
			}

			const familyFieldset = document.getElementById('pmpro_form_fieldset-partner-family');
			if (familyFieldset?.parentNode && billingFieldset.parentNode === familyFieldset.parentNode) {
				familyFieldset.parentNode.insertBefore(familyFieldset, billingFieldset);
			}

			if (memberPreferencesFieldset?.parentNode && billingFieldset.parentNode === memberPreferencesFieldset.parentNode) {
				billingFieldset.parentNode.insertBefore(memberPreferencesFieldset, billingFieldset.nextSibling);
			}

			const magazineFieldset = document.getElementById('pmpro_form_fieldset-magazine-addons');
			if (magazineFieldset) {
				magazineFieldset.hidden = true;
				magazineFieldset.style.display = 'none';
			}

			const levelInput = document.getElementById('pmpro_level');
			const currentLevelId = Number.parseInt(levelInput?.value || '0', 10) || 0;
			if (discountFieldset) {
				const showMembershipDiscounts = currentLevelSupportsDiscountTiers();
				discountFieldset.hidden = !showMembershipDiscounts;
				discountFieldset.style.display = showMembershipDiscounts ? '' : 'none';
				if (!showMembershipDiscounts) {
					discountFieldset.querySelectorAll('input[name="aac_membership_discount"]').forEach((input) => {
						input.checked = false;
						input.removeAttribute('checked');
					});
				}
			}

			const familyAccountFieldset = document.getElementById('pmprogroupacct_parent_fields');
			if (familyAccountFieldset) {
				familyAccountFieldset.hidden = true;
				familyAccountFieldset.style.display = 'none';
			}

			buildMemberPreferenceCards(memberPreferencesFieldset, currentLevelId);

			const donationFieldset = document.getElementById('pmpro_form_fieldset-donation');
			const autoRenewFieldset = document.getElementById('pmpro_autorenewal_checkbox');
			const paymentInformationFieldset = document.getElementById('pmpro_payment_information_fields');
			const checkoutSummary = document.querySelector('[data-aac-magazine-summary]');
			const autoRenewHeading = autoRenewFieldset?.querySelector('.pmpro_form_heading');
			const nativeDiscountCodePrompt = document.getElementById('other_discount_code_p');
			const nativeDiscountCodeFields = document.getElementById('other_discount_code_fields');
			const nativeDiscountCodePaymentField = document.querySelector('.pmpro_payment-discount-code')?.closest('.pmpro_cols-2') || document.querySelector('.pmpro_payment-discount-code');

			if (autoRenewHeading) {
				autoRenewHeading.textContent = 'Automatic Renewals';
			}

			[nativeDiscountCodePrompt, nativeDiscountCodeFields, nativeDiscountCodePaymentField].forEach((node) => {
				if (!node) {
					return;
				}
				node.hidden = true;
				node.style.display = 'none';
			});

			if (paymentInformationFieldset?.parentNode) {
				const checkoutSectionParent = paymentInformationFieldset.parentNode;
				const paymentLegend = paymentInformationFieldset.querySelector('.pmpro_form_legend');

				if (paymentLegend) {
					paymentLegend.remove();
				}

				if (donationFieldset && donationFieldset.parentNode === checkoutSectionParent) {
					checkoutSectionParent.insertBefore(donationFieldset, paymentInformationFieldset);
				}

				if (autoRenewFieldset && autoRenewFieldset.parentNode === checkoutSectionParent) {
					checkoutSectionParent.insertBefore(autoRenewFieldset, paymentInformationFieldset);
				}

				if (checkoutSummary) {
					checkoutSectionParent.insertBefore(checkoutSummary, paymentInformationFieldset);
				}
			}

		};

		const syncMagazineAddonSummary = () => {
			const fieldset = document.getElementById('pmpro_form_fieldset-magazine-addons');
			if (!fieldset) {
				return;
			}

			const checkboxInputs = Array.from(fieldset.querySelectorAll('input[name="aac_magazine_addons[]"]'));
			if (!checkboxInputs.length) {
				return;
			}

			const basePrice = getCurrentCheckoutBasePrice()
				?? (Number.parseFloat(fieldset.dataset.aacMagazineBasePrice || '0') || 0);
			const addonTotal = checkboxInputs.reduce((total, input) => {
				if (!input.checked) {
					return total;
				}

				return total + (Number.parseFloat(input.dataset.aacMagazinePrice || '0') || 0);
			}, 0);
			const updatedTotal = basePrice + addonTotal;
			const summary = document.querySelector('[data-aac-magazine-summary]');
			const currentLevelId = getCurrentCheckoutLevelId();
			const membershipName = getCurrentCheckoutLevelName();
			const familyModeValue = String(
				document.querySelector('input[name="aac_partner_family_mode"]')?.value ||
				document.querySelector('input[name="aac_partner_family_mode"]:checked')?.value ||
				''
			).trim();
			const familyMode = familyModeValue === 'family' ? 'family' : '';
			const familyFieldset = document.getElementById('pmpro_form_fieldset-partner-family');
			const familyAdultInput = document.getElementById('aac_partner_family_additional_adult');
			const familyDependentsInput = document.getElementById('aac_partner_family_dependents');
			const familyAdultPrice = Number.parseFloat(familyFieldset?.dataset.aacPartnerFamilyAdultPrice || '0') || 0;
			const familyDependentPrice = Number.parseFloat(familyFieldset?.dataset.aacPartnerFamilyDependentPrice || '0') || 0;
			const familyAdultAmount = familyMode === 'family' && familyAdultInput?.checked ? familyAdultPrice : 0;
			const familyDependentCount = familyMode === 'family' ? Math.max(0, Number.parseInt(familyDependentsInput?.value || '0', 10) || 0) : 0;
			const familyDependentsAmount = familyDependentCount * familyDependentPrice;
			const selectedDiscountInput = document.querySelector('input[name="aac_membership_discount"]:checked');
			const discountRate = Number.parseFloat(selectedDiscountInput?.dataset.aacMembershipDiscountRate || '0') || 0;
			const discountAmount = Math.round(basePrice * discountRate * 100) / 100;
			const discountLabel = selectedDiscountInput?.dataset.aacMembershipDiscountLabel
				? `${selectedDiscountInput.dataset.aacMembershipDiscountLabel} (35%)`
				: '';
			const donationAmount = Math.max(0, Number.parseFloat(document.getElementById('donation')?.value || '0') || 0);
			const readPublicationPreferenceValue = (fallbackSelector) => {
				const fallbackValue = (document.querySelector(fallbackSelector)?.value || '').trim();
				return fallbackValue === 'Print' ? 'Print' : 'Digital';
			};
			const countryValue = String(document.getElementById('bcountry')?.value || 'US').trim().toUpperCase();
			const isInternationalCountry = !['', 'US', 'USA', 'UNITED STATES', 'UNITED STATES OF AMERICA'].includes(countryValue);
			const hasPrintPublicationSelection = [
				readPublicationPreferenceValue('#aaj_preference_div select'),
				readPublicationPreferenceValue('#anac_preference_div select'),
				readPublicationPreferenceValue('#american_climbing_journal_preference_div select'),
				readPublicationPreferenceValue('#guidebook_preferences_div select'),
			].includes('Print');
			const internationalSurcharge = currentLevelId === 3 && isInternationalCountry && hasPrintPublicationSelection ? 30 : 0;
			const selectedAddons = checkboxInputs
				.filter((input) => input.checked)
				.map((input) => ({
					label: input.closest('.aac-magazine-addons__card')?.querySelector('.aac-magazine-addons__copy strong')?.textContent?.trim() || 'Magazine subscription',
					amount: Number.parseFloat(input.dataset.aacMagazinePrice || '0') || 0,
				}));
			const pmproMembershipAmount = getPmproMembershipAmount(basePrice);
			const promoDiscountAmount = Math.max(0, Math.round((basePrice - pmproMembershipAmount) * 100) / 100);
			const promoDiscountCode = getDiscountCodeState();
			const membershipSummaryLabel = getProratedMembershipSummaryLabel(membershipName);
			const lineItems = [
				{ label: membershipSummaryLabel, amount: basePrice },
				...(promoDiscountAmount > 0 ? [{ label: promoDiscountCode ? `Promo code (${promoDiscountCode})` : 'Promo code discount', amount: 0 - promoDiscountAmount, isDiscount: true }] : []),
				...(discountAmount > 0 && discountLabel ? [{ label: discountLabel, amount: 0 - discountAmount, isDiscount: true }] : []),
				...(familyAdultAmount > 0 ? [{ label: 'Additional adult', amount: familyAdultAmount }] : []),
				...(familyDependentsAmount > 0 ? [{ label: `${familyDependentCount} ${familyDependentCount === 1 ? 'dependent' : 'dependents'}`, amount: familyDependentsAmount }] : []),
				...(internationalSurcharge > 0 ? [{ label: 'International surcharge for print copies', amount: internationalSurcharge }] : []),
				...(donationAmount > 0 ? [{ label: 'Donation', amount: donationAmount }] : []),
				...selectedAddons,
			];
			const grandTotal = lineItems.reduce((total, item) => total + (Number.isFinite(item.amount) ? item.amount : 0), 0);
			if (summary) {
				summary.innerHTML = `
					<div class="aac-magazine-addons__summary-header">
						<p class="aac-magazine-addons__summary-title">Order summary</p>
						<p class="aac-magazine-addons__summary-caption">Review everything included before entering payment details.</p>
					</div>
					${buildDiscountCodeMarkup()}
					<div class="aac-magazine-addons__summary-rows">
						${lineItems.map((item) => `
							<div class="aac-magazine-addons__summary-row${item.isDiscount ? ' aac-magazine-addons__summary-row--discount' : ''}">
								<span>${item.label}</span>
								<strong>${formatUsd(item.amount)}</strong>
							</div>
						`).join('')}
						<div class="aac-magazine-addons__summary-row aac-magazine-addons__summary-row--total">
							<span>Grand total</span>
							<strong>${formatUsd(grandTotal)}</strong>
						</div>
					</div>
				`;
				bindDiscountCodeForm(summary);
			}

			const priceText = document.querySelector('#pmpro_level_cost .pmpro_level-price');
			if (priceText) {
				const baseText = priceText.dataset.aacBaseText || (priceText.textContent || '').trim();
				if (!priceText.dataset.aacBaseText) {
					priceText.dataset.aacBaseText = baseText;
				}

				priceText.textContent = baseText;

				let note = document.getElementById('aac-magazine-total-note');
				if (note) {
					note.remove();
				}
			}

			checkboxInputs.forEach((input) => {
				if (input.dataset.aacMagazineBound === 'true') {
					return;
				}

				input.addEventListener('change', syncMagazineAddonSummary);
				input.dataset.aacMagazineBound = 'true';
			});

			document.querySelectorAll('input[name="aac_membership_discount"]').forEach((input) => {
				if (input.dataset.aacMembershipDiscountBound === 'true') {
					return;
				}

				input.addEventListener('change', syncMagazineAddonSummary);
				input.dataset.aacMembershipDiscountBound = 'true';
			});

			if (familyAdultInput && familyAdultInput.dataset.aacPartnerFamilyBound !== 'true') {
				familyAdultInput.addEventListener('change', syncMagazineAddonSummary);
				familyAdultInput.dataset.aacPartnerFamilyBound = 'true';
			}

			if (familyDependentsInput && familyDependentsInput.dataset.aacPartnerFamilyBound !== 'true') {
				familyDependentsInput.addEventListener('change', syncMagazineAddonSummary);
				familyDependentsInput.dataset.aacPartnerFamilyBound = 'true';
			}

			const countryField = document.getElementById('bcountry');
			if (countryField && countryField.dataset.aacOrderSummaryBound !== 'true') {
				countryField.addEventListener('change', syncMagazineAddonSummary);
				countryField.dataset.aacOrderSummaryBound = 'true';
			}

			document.querySelectorAll('#publications_preference_div select, #aaj_preference_div select, #anac_preference_div select, #american_climbing_journal_preference_div select, #guidebook_preferences_div select').forEach((select) => {
				if (select.dataset.aacOrderSummaryBound === 'true') {
					return;
				}

				select.addEventListener('change', syncMagazineAddonSummary);
				select.dataset.aacOrderSummaryBound = 'true';
			});

			const nativeDiscountMessage = getNativeDiscountCodeMessage();
			if (nativeDiscountMessage && nativeDiscountMessage.dataset.aacSummaryObserved !== 'true') {
				new MutationObserver(() => {
					window.setTimeout(syncMagazineAddonSummary, 50);
				}).observe(nativeDiscountMessage, {
					childList: true,
					subtree: true,
					characterData: true,
					attributes: true,
				});
				nativeDiscountMessage.dataset.aacSummaryObserved = 'true';
			}

			const priceContainer = document.getElementById('pmpro_level_cost');
			if (priceContainer && priceContainer.dataset.aacSummaryObserved !== 'true') {
				new MutationObserver(() => {
					window.setTimeout(syncMagazineAddonSummary, 50);
				}).observe(priceContainer, {
					childList: true,
					subtree: true,
					characterData: true,
				});
				priceContainer.dataset.aacSummaryObserved = 'true';
			}
		};

			const bindToggleableMembershipDiscounts = () => {
				document.querySelectorAll('input[name="aac_membership_discount"][data-aac-toggleable-choice="true"]').forEach((input) => {
					if (input.dataset.aacToggleableBound === 'true') {
						return;
					}

					const clearFamilySelection = () => {
						const familyShortcut = document.getElementById('aac_partner_family_shortcut');
						const modeInput = document.getElementById('aac_partner_family_mode');
						const familyFieldset = document.getElementById('pmpro_form_fieldset-partner-family');
						const details = document.querySelector('[data-aac-partner-family-details]');
						const familyAdultInput = document.getElementById('aac_partner_family_additional_adult');
						const familyDependentsInput = document.getElementById('aac_partner_family_dependents');

						if (familyShortcut) {
							familyShortcut.checked = false;
							familyShortcut.removeAttribute('checked');
						}

						if (modeInput) {
							modeInput.value = '';
						}

						if (familyFieldset) {
							familyFieldset.hidden = true;
							familyFieldset.style.display = 'none';
						}

						if (details) {
							details.hidden = true;
							details.style.display = 'none';
						}

						if (familyAdultInput) {
							familyAdultInput.checked = false;
							familyAdultInput.removeAttribute('checked');
						}

						if (familyDependentsInput) {
							familyDependentsInput.value = '0';
						}
					};

					const syncExclusiveDiscountSelection = () => {
						if (input.checked) {
							document.querySelectorAll(`input[name="${input.name}"]`).forEach((candidate) => {
								if (candidate === input) {
									candidate.setAttribute('checked', 'checked');
							} else {
								candidate.checked = false;
									candidate.removeAttribute('checked');
								}
							});

							clearFamilySelection();
						} else {
							input.removeAttribute('checked');
						}

						syncMagazineAddonSummary();
				};

				input.addEventListener('click', () => {
					window.setTimeout(syncExclusiveDiscountSelection, 0);
				});

				input.addEventListener('change', syncExclusiveDiscountSelection);

				input.dataset.aacToggleableBound = 'true';
			});
		};

		const bindFamilySelectionShortcut = () => {
			const shortcut = document.getElementById('aac_partner_family_shortcut');
			const modeInput = document.getElementById('aac_partner_family_mode');
			const familyFieldset = document.getElementById('pmpro_form_fieldset-partner-family');
			const details = document.querySelector('[data-aac-partner-family-details]');
			if (!shortcut || !modeInput || !details || !familyFieldset) {
				return;
			}
			const familyAdultInput = document.getElementById('aac_partner_family_additional_adult');
			const familyDependentsInput = document.getElementById('aac_partner_family_dependents');

			const syncFamilyState = () => {
				const active = shortcut.checked;
				if (active) {
					document.querySelectorAll('input[name="aac_membership_discount"][data-aac-toggleable-choice="true"]').forEach((input) => {
						input.checked = false;
						input.removeAttribute('checked');
					});
				} else {
					if (familyAdultInput) {
						familyAdultInput.checked = false;
						familyAdultInput.removeAttribute('checked');
					}
					if (familyDependentsInput) {
						familyDependentsInput.value = '0';
					}
				}
				modeInput.value = active ? 'family' : '';
				familyFieldset.hidden = !active;
				familyFieldset.style.display = active ? '' : 'none';
				details.hidden = !active;
				details.style.display = active ? 'grid' : 'none';
			};

			if (shortcut.dataset.aacFamilyShortcutBound !== 'true') {
				shortcut.addEventListener('change', () => {
					syncFamilyState();
					syncMagazineAddonSummary();
				});
				shortcut.dataset.aacFamilyShortcutBound = 'true';
			}

			syncFamilyState();
		};

		const enhancePublicationPreferenceCards = () => {
			const memberPreferencesFieldset =
				document.getElementById('pmpro_form_fieldset-publication-preferences') ||
				document.getElementById('pmpro_form_fieldset-member-preferences') ||
				document.getElementById('pmpro_form_fieldset-more-information');
			if (!memberPreferencesFieldset) {
				return;
			}

			const serverBlock =
				memberPreferencesFieldset.querySelector('.aac-server-member-preferences') ||
				document.querySelector('.aac-server-member-preferences');
			const targetFields = memberPreferencesFieldset.querySelector('.pmpro_form_fields');
			if (serverBlock && targetFields && !targetFields.contains(serverBlock)) {
				targetFields.prepend(serverBlock);
			}

			if (memberPreferencesFieldset.querySelector('.aac-server-member-preferences')) {
				memberPreferencesFieldset.querySelectorAll('#publications_preference_div select, #aaj_preference_div select, #anac_preference_div select, #american_climbing_journal_preference_div select, #guidebook_preferences_div select').forEach((select) => {
					select.disabled = true;
				});
				return;
			}

			const levelInput = document.getElementById('pmpro_level');
			const currentLevelId = Number.parseInt(levelInput?.value || '0', 10) || 0;
			buildMemberPreferenceCards(memberPreferencesFieldset, currentLevelId);
		};

		const syncStandaloneFamilyVisibility = () => {
			const shortcut = document.getElementById('aac_partner_family_shortcut');
			const modeInput = document.getElementById('aac_partner_family_mode');
			const familyFieldset = document.getElementById('pmpro_form_fieldset-partner-family');
			const details = document.querySelector('[data-aac-partner-family-details]');
			if (!shortcut || !modeInput || !familyFieldset || !details) {
				return;
			}

			const familyAdultInput = document.getElementById('aac_partner_family_additional_adult');
			const familyDependentsInput = document.getElementById('aac_partner_family_dependents');
			const active = shortcut.checked;
			modeInput.value = active ? 'family' : '';
			familyFieldset.hidden = !active;
			familyFieldset.style.display = active ? '' : 'none';
			details.hidden = !active;
			details.style.display = active ? 'grid' : 'none';

			if (!active) {
				if (familyAdultInput) {
					familyAdultInput.checked = false;
					familyAdultInput.removeAttribute('checked');
				}
				if (familyDependentsInput) {
					familyDependentsInput.value = '0';
				}
			}
		};

		const relabelTShirtSizeOptions = () => {
			const tshirtValueMap = {
				'none': 'No T-shirt',
				'no t-shirt': 'No T-shirt',
				'xs': 'Unisex X-Small',
				's': 'Unisex Small',
				'm': 'Unisex Medium',
				'l': 'Unisex Large',
				'xl': 'Unisex X-Large',
				'xxl': 'Unisex XX-Large',
				'2xl': 'Unisex XX-Large',
				'unisex x-small': 'Unisex X-Small',
				'unisex small': 'Unisex Small',
				'unisex medium': 'Unisex Medium',
				'unisex large': 'Unisex Large',
				'unisex x-large': 'Unisex X-Large',
				'unisex xx-large': 'Unisex XX-Large',
			};
			const normalizeTshirtValue = (value) => {
				const rawValue = String(value || '').trim();
				if (!rawValue) {
					return 'No T-shirt';
				}

				const lowered = rawValue.toLowerCase();
				if (tshirtValueMap[lowered]) {
					return tshirtValueMap[lowered];
				}

				if (lowered.startsWith('unisex ')) {
					const compact = lowered.replace(/^unisex\s+/, '').replace(/[\s-]+/g, '');
					return tshirtValueMap[compact] || 'No T-shirt';
				}

				return 'No T-shirt';
			};

			document.querySelectorAll('select[name="t_shirt"]').forEach((select) => {
				if (select.dataset.aacTshirtEnhanced !== 'true') {
					select.required = false;
					select.classList.remove('pmpro_form_input-required');

					const field = select.closest('.pmpro_form_field');
					field?.classList.remove('pmpro_form_field-required');
					field?.querySelector('.pmpro_asterisk')?.remove();

					select.querySelectorAll('option').forEach((option) => {
						if ((option.value || '').trim() === '') {
							option.remove();
							return;
						}

						const normalizedValue = normalizeTshirtValue(option.value || option.textContent || '');
						option.value = normalizedValue;
						option.textContent = normalizedValue;
					});

					const seenValues = new Set();
					Array.from(select.options).forEach((option) => {
						if (seenValues.has(option.value)) {
							option.remove();
							return;
						}
						seenValues.add(option.value);
					});

					const allowedValues = new Set([
						'No T-shirt',
						'Unisex X-Small',
						'Unisex Small',
						'Unisex Medium',
						'Unisex Large',
						'Unisex X-Large',
						'Unisex XX-Large',
					]);

					Array.from(select.options).forEach((option) => {
						if (!allowedValues.has(option.value)) {
							option.remove();
						}
					});

					const desiredTshirtValue = normalizeTshirtValue(checkoutProfileDefaults.size || 'No T-shirt');
					if (select.querySelector(`option[value="${desiredTshirtValue}"]`)) {
						select.value = desiredTshirtValue;
					} else {
						select.value = 'No T-shirt';
					}

					select.dispatchEvent(new Event('change', { bubbles: true }));
					select.dataset.aacTshirtEnhanced = 'true';
				}
			});
		};

		const syncPmproStateDropdown = () => {
			const countryField = document.getElementById('bcountry');
			const stateField = document.getElementById('bstate');
			const stateMap = window.pmprosd_states;
			if (!countryField || !stateField || !stateMap || typeof stateMap !== 'object') {
				return;
			}

			const labelMap = window.pmpro_state_labels || {};
			const currentCountry = countryField.value || (window.pmpro_state_dropdowns && window.pmpro_state_dropdowns.bcountry) || 'US';
			const countryStates = stateMap[currentCountry] || {};
			const hasDropdownOptions = typeof countryStates === 'object' && Object.keys(countryStates).length > 0;
			const currentValue = stateField.value || (window.pmpro_state_dropdowns && window.pmpro_state_dropdowns.bstate) || '';
			const wrapper = stateField.closest('.pmpro_form_field');
			if (!wrapper) {
				return;
			}

			wrapper.querySelectorAll('.select2-container').forEach((node) => node.remove());

			const buildSelect = () => {
				const select = document.createElement('select');
				select.id = 'bstate';
				select.name = 'bstate';
				select.className = stateField.className.replace(/\bpmpro_form_input-text\b/g, ' ').trim();
				select.classList.add('pmpro_form_input-select');
				if (stateField.required) {
					select.required = true;
					select.classList.add('pmpro_form_input-required');
				}
				if (stateField.autocomplete) {
					select.autocomplete = stateField.autocomplete;
				}

				const placeholderOption = document.createElement('option');
				placeholderOption.value = '';
				placeholderOption.textContent = labelMap.region || 'Select state';
				select.appendChild(placeholderOption);

				Object.entries(countryStates).forEach(([value, label]) => {
					const option = document.createElement('option');
					option.value = value;
					option.textContent = label;
					select.appendChild(option);
				});

				if (Object.prototype.hasOwnProperty.call(countryStates, currentValue)) {
					select.value = currentValue;
				} else {
					const matchingEntry = Object.entries(countryStates).find(([, label]) => label === currentValue);
					if (matchingEntry) {
						select.value = matchingEntry[0];
					}
				}

				return select;
			};

			const buildInput = () => {
				const input = document.createElement('input');
				input.id = 'bstate';
				input.name = 'bstate';
				input.type = 'text';
				input.className = stateField.className.replace(/\bpmpro_form_input-select\b/g, ' ').trim();
				input.value = currentValue;
				if (stateField.required) {
					input.required = true;
					input.classList.add('pmpro_form_input-required');
				}
				if (stateField.autocomplete) {
					input.autocomplete = stateField.autocomplete;
				}
				return input;
			};

			if (hasDropdownOptions && stateField.tagName !== 'SELECT') {
				stateField.replaceWith(buildSelect());
			} else if (!hasDropdownOptions && stateField.tagName === 'SELECT') {
				stateField.replaceWith(buildInput());
			} else if (hasDropdownOptions && stateField.tagName === 'SELECT') {
				stateField.classList.add('pmpro_form_input-select');
			}

			if (!countryField.dataset.aacStateDropdownBound) {
				countryField.addEventListener('change', () => {
					window.requestAnimationFrame(syncPmproStateDropdown);
				});
				countryField.dataset.aacStateDropdownBound = 'true';
			}
		};

		const enhancePmproDonationFieldset = () => {
			const fieldset = document.getElementById('pmpro_form_fieldset-donation');
			const dropdown = document.getElementById('donation_dropdown');
			const amountInput = document.getElementById('donation');
			const amountWrapper = document.getElementById('pmprodon_donation_input');
			if (!fieldset || !dropdown || !amountInput || !amountWrapper) {
				return;
			}

			const presetValues = Array.from(dropdown.options)
				.map((option) => option.value)
				.filter((value) => value !== '' && value !== 'other');
			const hasSelectedAttribute = Array.from(dropdown.options).some((option) => option.hasAttribute('selected'));
			const currentAmount = Number.parseFloat(amountInput.value || '0') || 0;
			const defaultPluginAmount = 10;

			if (!dropdown.querySelector('option[value="0"]')) {
				const noDonationOption = document.createElement('option');
				noDonationOption.value = '0';
				noDonationOption.textContent = 'No thank you';
				dropdown.insertBefore(noDonationOption, dropdown.firstChild);
			}

			if (!dropdown.querySelector('option[value="other"]')) {
				const customOption = document.createElement('option');
				customOption.value = 'other';
				customOption.textContent = 'Custom amount';
				dropdown.appendChild(customOption);
			}

			if (!fieldset.querySelector('.aac-donation-helper')) {
				const helper = document.createElement('p');
				helper.className = 'aac-donation-helper';
				helper.textContent = 'Choose a preset gift, enter a custom amount, or opt out of adding a donation.';
				const formFields = fieldset.querySelector('.pmpro_form_fields');
				formFields?.appendChild(helper);
			}

			const inlineWrapper = dropdown.closest('.pmpro_form_fields-inline');
			if (!inlineWrapper) {
				return;
			}

			const visibleOptions = Array.from(dropdown.options)
				.filter((option) => option.value !== 'other')
				.map((option) => ({
					value: option.value,
					label: option.value === '0' ? 'No thanks' : option.textContent.trim(),
				}));

			amountInput.inputMode = 'decimal';
			amountInput.min = '0';
			amountInput.step = '0.01';
			amountInput.placeholder = 'Enter amount';

			const syncDonationMode = () => {
				const selectedValue = dropdown.value;
				fieldset.dataset.aacDonationMode = selectedValue === 'other' ? 'custom' : 'preset';

				if (selectedValue === 'other') {
					if (Number.parseFloat(amountInput.value || '0') < 0) {
						amountInput.value = '0';
					}
					return;
				}

				amountInput.value = selectedValue;
			};

			const syncDonationButtons = () => {
				const selectedValue = dropdown.value;
				fieldset.querySelectorAll('[data-aac-donation-value]').forEach((button) => {
					button.dataset.selected = button.getAttribute('data-aac-donation-value') === selectedValue ? 'true' : 'false';
				});
			};

			if (fieldset.dataset.aacDonationEnhanced !== 'true') {
				const shouldDefaultToNone = !hasSelectedAttribute && (currentAmount <= 0 || currentAmount === defaultPluginAmount);
				const shouldUseCustom = !hasSelectedAttribute && currentAmount > 0 && !presetValues.includes(String(currentAmount));

				if (!inlineWrapper.querySelector('.aac-donation-picker')) {
					const picker = document.createElement('div');
					picker.className = 'aac-donation-picker';

					visibleOptions.forEach((option) => {
						const button = document.createElement('button');
						button.type = 'button';
						button.className = 'aac-donation-option';
						button.textContent = option.label;
						button.setAttribute('data-aac-donation-value', option.value);
						button.addEventListener('click', () => {
							dropdown.value = option.value;
							amountInput.value = option.value;
							dropdown.dispatchEvent(new Event('change', { bubbles: true }));
							amountInput.dispatchEvent(new Event('change', { bubbles: true }));
						});
						picker.appendChild(button);
					});

					const customButton = document.createElement('button');
					customButton.type = 'button';
					customButton.className = 'aac-donation-option';
					customButton.textContent = 'Custom amount';
					customButton.setAttribute('data-aac-donation-value', 'other');
					customButton.addEventListener('click', () => {
						dropdown.value = 'other';
						if (!amountInput.value || Number.parseFloat(amountInput.value || '0') === 0) {
							amountInput.value = '';
						}
						dropdown.dispatchEvent(new Event('change', { bubbles: true }));
						window.requestAnimationFrame(() => amountInput.focus());
					});
					picker.appendChild(customButton);

					inlineWrapper.insertBefore(picker, inlineWrapper.firstChild);
				}

				if (shouldUseCustom) {
					dropdown.value = 'other';
				} else if (shouldDefaultToNone || !dropdown.value) {
					dropdown.value = '0';
				}

				dropdown.addEventListener('change', () => {
					syncDonationMode();
					syncDonationButtons();
					syncMagazineAddonSummary();
				});
				amountInput.addEventListener('input', () => {
					if (dropdown.value === 'other' && !(Number.parseFloat(amountInput.value || '0') >= 0)) {
						amountInput.value = '0';
					}
					if (dropdown.value === 'other') {
						amountInput.dispatchEvent(new Event('change', { bubbles: true }));
					}
					syncMagazineAddonSummary();
				});
				fieldset.dataset.aacDonationEnhanced = 'true';
			}

			syncDonationMode();
			syncDonationButtons();

			if (fieldset.dataset.aacDonationInitialized !== 'true') {
				dropdown.dispatchEvent(new Event('change', { bubbles: true }));
				fieldset.dataset.aacDonationInitialized = 'true';
			}
		};

		const syncPmproUsernameFromEmail = () => {
			const usernameInput = document.querySelector('input[name="username"]');
			const emailInput = document.querySelector('input[name="bemail"]');
			if (!usernameInput || !emailInput) {
				return;
			}

			const syncValue = () => {
				usernameInput.value = buildUsernameFromEmail(emailInput.value);
			};

			syncValue();
			usernameInput.type = 'hidden';

			const usernameField = usernameInput.closest('.pmpro_form_field-username');
			if (usernameField) {
				usernameField.hidden = true;
				usernameField.style.display = 'none';
			}

			const checkoutForm = usernameInput.form || document.querySelector('form.pmpro_form');
			if (checkoutForm && !checkoutForm.dataset.aacUsernameSyncBound) {
				checkoutForm.addEventListener('submit', syncValue);
				checkoutForm.dataset.aacUsernameSyncBound = 'true';
			}

			emailInput.addEventListener('input', syncValue);
			emailInput.addEventListener('change', syncValue);
		};

		const bindEmailAvailabilityCheck = () => {
			if (currentUserEmail) {
				return;
			}

			const emailInput = document.querySelector('input[name="bemail"]');
			if (!emailInput || emailInput.dataset.aacEmailAvailabilityBound === 'true') {
				return;
			}

			const emailField = emailInput.closest('.pmpro_form_field');
			if (!emailField) {
				return;
			}

			let statusNode = emailField.querySelector('.aac-email-availability');
			if (!statusNode) {
				statusNode = document.createElement('p');
				statusNode.className = 'aac-email-availability';
				statusNode.dataset.state = 'idle';
				emailField.appendChild(statusNode);
			}

			let requestCounter = 0;
			let debounceTimer = null;

			const setStatus = (state, message) => {
				statusNode.dataset.state = state;
				statusNode.textContent = message || '';
			};

			const runAvailabilityCheck = async () => {
				const email = String(emailInput.value || '').trim();
				emailInput.setCustomValidity('');

				if (!email) {
					setStatus('idle', '');
					return;
				}

				if (!emailInput.checkValidity()) {
					setStatus('idle', 'Enter a valid email address.');
					return;
				}

				const currentRequest = ++requestCounter;
				setStatus('checking', 'Checking email availability...');

				try {
					const url = new URL(emailAvailabilityEndpoint);
					url.searchParams.set('email', email);

					const response = await fetch(url.toString(), {
						credentials: 'same-origin',
						headers: {
							Accept: 'application/json',
						},
					});

					if (!response.ok) {
						throw new Error(`Email check failed with status ${response.status}`);
					}

					const result = await response.json();
					if (currentRequest !== requestCounter) {
						return;
					}

					if (result?.valid && result?.available) {
						emailInput.setCustomValidity('');
						setStatus('available', result.message || 'Email address is available.');
						return;
					}

					const message = result?.message || 'An account with this email already exists.';
					emailInput.setCustomValidity(message);
					setStatus('unavailable', message);
				} catch (error) {
					if (currentRequest !== requestCounter) {
						return;
					}

					emailInput.setCustomValidity('');
					setStatus('idle', 'Unable to check email availability right now.');
				}
			};

			const scheduleAvailabilityCheck = () => {
				window.clearTimeout(debounceTimer);
				debounceTimer = window.setTimeout(runAvailabilityCheck, 280);
			};

			emailInput.addEventListener('input', scheduleAvailabilityCheck);
			emailInput.addEventListener('change', runAvailabilityCheck);
			emailInput.dataset.aacEmailAvailabilityBound = 'true';
		};

		const enhanceCheckoutAutoRenewFieldset = () => {
			const fieldset = document.getElementById('pmpro_autorenewal_checkbox');
			if (!fieldset) {
				return;
			}

			const checkbox = fieldset.querySelector('input[type="checkbox"]');
			const checkoutForm = fieldset.closest('form');
			if (!checkbox || !checkoutForm) {
				return;
			}

			let presentInput = checkoutForm.querySelector('input[name="autorenew_present"]');
			if (!presentInput) {
				presentInput = document.createElement('input');
				presentInput.type = 'hidden';
				presentInput.name = 'autorenew_present';
				presentInput.value = '1';
				checkoutForm.appendChild(presentInput);
			}

			const storageKey = `aacCheckoutAutoRenewChoice:${window.location.pathname}:${new URLSearchParams(window.location.search).get('level') || ''}`;
			const originalField = checkbox.closest('.pmpro_form_field');
			if (originalField) {
				originalField.hidden = true;
				originalField.style.display = 'none';
			}

			let toggle = fieldset.querySelector('[data-aac-checkout-autorenew-toggle]');
			if (!toggle) {
				const wrapper = document.createElement('div');
				wrapper.className = 'aac-checkout-autorenew';
				wrapper.innerHTML = `
					<div class="aac-checkout-autorenew__copy">
						<strong>Automatic Renewals</strong>
						<span>Keep this membership active with recurring annual renewal.</span>
					</div>
					<label class="aac-managed-toggle">
						<input type="checkbox" data-aac-checkout-autorenew-toggle />
						<span class="aac-managed-toggle__track" aria-hidden="true"></span>
						<span class="aac-managed-toggle__state">On</span>
					</label>
				`;
				fieldset.querySelector('.pmpro_form_fields')?.appendChild(wrapper);
				toggle = wrapper.querySelector('[data-aac-checkout-autorenew-toggle]');
			}

			const stateNode = fieldset.querySelector('.aac-managed-toggle__state');
			let storedChoice = '';
			try {
				storedChoice = window.sessionStorage.getItem(storageKey) || '';
			} catch (error) {
				storedChoice = '';
			}

			const syncState = (checked) => {
				checkbox.checked = checked;
				if (checked) {
					checkbox.setAttribute('checked', 'checked');
				} else {
					checkbox.removeAttribute('checked');
				}
				toggle.checked = checked;
				if (stateNode) {
					stateNode.textContent = checked ? 'On' : 'Off';
				}
			};

			if (!fieldset.dataset.aacCheckoutAutoRenewInitialized) {
				syncState(storedChoice ? storedChoice === 'on' : true);
				fieldset.dataset.aacCheckoutAutoRenewInitialized = 'true';
			}

			if (toggle.dataset.aacCheckoutAutoRenewBound !== 'true') {
				toggle.addEventListener('change', () => {
					syncState(toggle.checked);
					try {
						window.sessionStorage.setItem(storageKey, toggle.checked ? 'on' : 'off');
					} catch (error) {
						// Ignore storage write failures.
					}
				});
				toggle.dataset.aacCheckoutAutoRenewBound = 'true';
			}
		};

		const replacePmproLoggedInAccountUsername = () => {
			const preferredDisplayName = buildPreferredLoggedInName();
			if (!preferredDisplayName) {
				return;
			}

			const accountFieldset = document.getElementById('pmpro_user_fields');
			if (!accountFieldset || accountFieldset.dataset.aacLoggedInDisplayPatched === 'true') {
				return;
			}

			const accountParagraphs = accountFieldset.querySelectorAll('p');
			for (const paragraph of accountParagraphs) {
				const text = (paragraph.textContent || '').trim();
				if (!/You are logged in as/i.test(text) || !/different account/i.test(text)) {
					continue;
				}

				const logoutLink = paragraph.querySelector('a[href*="logout"], a[href*="log-out"], a[href*="action=logout"]');
				const logoutHref = logoutLink?.getAttribute('href') || '';
				const logoutText = (logoutLink?.textContent || 'log out now').trim();
				const escapedName = String(preferredDisplayName)
					.replace(/&/g, '&amp;')
					.replace(/</g, '&lt;')
					.replace(/>/g, '&gt;')
					.replace(/"/g, '&quot;')
					.replace(/'/g, '&#039;');

				paragraph.innerHTML = logoutHref
					? `You are logged in as <strong>${escapedName}</strong>. If you would like to use a different account for this membership, <a href="${logoutHref}">${logoutText}</a>.`
					: `You are logged in as <strong>${escapedName}</strong>. If you would like to use a different account for this membership, log out now.`;
				accountFieldset.dataset.aacLoggedInDisplayPatched = 'true';
				break;
			}
		};

		const bindManagedAutoRenewToggle = () => {
			const toggle = document.querySelector('[data-aac-autorenew-toggle]');
			if (!toggle || toggle.dataset.aacBound === 'true') {
				return;
			}

			toggle.addEventListener('change', () => {
				const targetUrl = toggle.checked
					? toggle.getAttribute('data-enable-url')
					: toggle.getAttribute('data-disable-url');

				if (targetUrl) {
					window.location.assign(targetUrl);
					return;
				}

				toggle.checked = !toggle.checked;
			});

			toggle.dataset.aacBound = 'true';
		};

		const removePmproMemberLinksSection = () => {
			const managedCard = document.querySelector('.aac-managed-card');
			if (!managedCard) {
				return;
			}

			const candidateNodes = managedCard.querySelectorAll('h1, h2, h3, h4, h5, h6, legend, strong, p');
			for (const candidate of candidateNodes) {
				const text = (candidate.textContent || '').trim().toLowerCase();
				if (text !== 'member links') {
					continue;
				}

				let removableSection = candidate;
				while (removableSection && removableSection.parentElement && removableSection.parentElement !== managedCard) {
					removableSection = removableSection.parentElement;
				}

				if (removableSection && removableSection !== managedCard) {
					removableSection.remove();
				}
			}
		};

		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', () => {
				syncPmproUsernameFromEmail();
				bindEmailAvailabilityCheck();
				enhancePmproProfileInformation();
				enhanceCheckoutAutoRenewFieldset();
				enhancePmproDonationFieldset();
				enhancePublicationPreferenceCards();
				bindToggleableMembershipDiscounts();
				bindFamilySelectionShortcut();
				syncStandaloneFamilyVisibility();
				relabelTShirtSizeOptions();
				syncMagazineAddonSummary();
				syncPmproStateDropdown();
				replacePmproLoggedInAccountUsername();
				bindManagedAutoRenewToggle();
				removePmproMemberLinksSection();
			});
		} else {
			syncPmproUsernameFromEmail();
			bindEmailAvailabilityCheck();
			enhancePmproProfileInformation();
			enhanceCheckoutAutoRenewFieldset();
			enhancePmproDonationFieldset();
			enhancePublicationPreferenceCards();
			bindToggleableMembershipDiscounts();
			bindFamilySelectionShortcut();
			syncStandaloneFamilyVisibility();
			relabelTShirtSizeOptions();
			syncMagazineAddonSummary();
			syncPmproStateDropdown();
			replacePmproLoggedInAccountUsername();
			bindManagedAutoRenewToggle();
			removePmproMemberLinksSection();
		}

		window.addEventListener('load', syncPmproUsernameFromEmail);
		window.addEventListener('load', bindEmailAvailabilityCheck);
		window.addEventListener('load', enhancePmproProfileInformation);
		window.addEventListener('load', enhanceCheckoutAutoRenewFieldset);
		window.addEventListener('load', enhancePmproDonationFieldset);
		window.addEventListener('load', enhancePublicationPreferenceCards);
		window.addEventListener('load', bindToggleableMembershipDiscounts);
		window.addEventListener('load', bindFamilySelectionShortcut);
		window.addEventListener('load', syncStandaloneFamilyVisibility);
		window.addEventListener('load', relabelTShirtSizeOptions);
		window.addEventListener('load', syncMagazineAddonSummary);
		window.addEventListener('load', syncPmproStateDropdown);
		window.addEventListener('load', replacePmproLoggedInAccountUsername);
		window.addEventListener('load', bindManagedAutoRenewToggle);
		window.addEventListener('load', removePmproMemberLinksSection);
	}());
</script>
<?php wp_footer(); ?>
</body>
</html>
