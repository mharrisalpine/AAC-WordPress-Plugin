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
$is_embedded_checkout = $is_checkout_page && isset($_GET['aac_embed']) && sanitize_text_field(wp_unslash($_GET['aac_embed'])) === '1';
$public_shell_slugs = ['benefits', 'rescue'];
$is_public_shell_page = $post instanceof WP_Post && in_array($post->post_name, $public_shell_slugs, true);
$is_logged_in = is_user_logged_in();
$public_login_url = $portal_url . '#/login';
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
	];

if (!function_exists('aac_member_portal_sidebar_icon_svg')) {
	function aac_member_portal_sidebar_icon_svg($icon) {
		$icons = [
			'user' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg>',
			'store' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9l1.5-5h15L21 9"/><path d="M4 9h16v10a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1Z"/><path d="M9 20v-6h6v6"/></svg>',
			'shield' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l7 3v6c0 5-3.5 8-7 9-3.5-1-7-4-7-9V6l7-3Z"/></svg>',
			'settings' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1 1.54V21a2 2 0 1 1-4 0v-.09a1.7 1.7 0 0 0-1-1.54 1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.7 1.7 0 0 0 .34-1.87 1.7 1.7 0 0 0-1.54-1H3a2 2 0 1 1 0-4h.09a1.7 1.7 0 0 0 1.54-1 1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.7 1.7 0 0 0 1.87.34H9A1.7 1.7 0 0 0 10 3.09V3a2 2 0 1 1 4 0v.09a1.7 1.7 0 0 0 1 1.54 1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.7 1.7 0 0 0-.34 1.87V9c0 .67.39 1.28 1 1.54.18.08.37.13.57.13H21a2 2 0 1 1 0 4h-.09a1.7 1.7 0 0 0-1.54 1Z"/></svg>',
			'credit-card' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M6 15h2"/><path d="M10 15h4"/></svg>',
			'receipt' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 3h16v18l-2-1.5-2 1.5-2-1.5-2 1.5-2-1.5-2 1.5-2-1.5-2 1.5Z"/><path d="M8 7h8"/><path d="M8 11h8"/><path d="M8 15h5"/></svg>',
			'tag' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.6 13.4L13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8Z"/><circle cx="8.5" cy="8.5" r="1.5"/></svg>',
			'mic' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="2" width="6" height="11" rx="3"/><path d="M5 10a7 7 0 0 0 14 0"/><path d="M12 17v4"/><path d="M8 21h8"/></svg>',
			'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="9.5" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-3-3.87"/><path d="M15 3.13a4 4 0 0 1 0 7.75"/></svg>',
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
		'publication_pref' => 'Digital',
		'guidebook_pref' => 'Digital',
		'size' => 'M',
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
			max-width: 1600px;
			margin: 0 auto;
			padding: calc(env(safe-area-inset-top, 0px) + 1rem) 1.5rem 1rem;
		}

		.aac-managed-header__row {
			display: flex;
			flex-wrap: wrap;
			align-items: center;
			justify-content: space-between;
			gap: 1rem;
			padding-bottom: 0.9rem;
			border-bottom: 1px solid rgba(255, 255, 255, 0.1);
		}

		.aac-managed-logo img {
			display: block;
			width: auto;
			height: 56px;
		}

		.aac-managed-actions,
		.aac-managed-topnav {
			display: flex;
			flex-wrap: wrap;
			align-items: center;
			gap: 0.75rem;
		}

		.aac-managed-topnav {
			justify-content: center;
			padding-top: 1rem;
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
			min-height: 2.75rem;
			padding: 0 1.2rem;
			border-radius: 999px;
			font-size: 0.82rem;
			font-weight: 700;
			letter-spacing: 0.14em;
			text-transform: uppercase;
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
			gap: 0.35rem;
			color: rgba(255, 255, 255, 0.84);
			font-size: 0.72rem;
			font-weight: 600;
			letter-spacing: 0.22em;
			text-transform: uppercase;
			padding: 0.75rem 0;
		}

		.aac-managed-topnav__caret {
			font-size: 0.95rem;
			line-height: 1;
			opacity: 0.8;
			transition: transform 0.2s ease;
		}

		.aac-managed-topnav__item:hover .aac-managed-topnav__caret,
		.aac-managed-topnav__item:focus-within .aac-managed-topnav__caret {
			transform: rotate(180deg);
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
			border-radius: 1.75rem;
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
			min-height: calc(100vh - 132px);
		}

		.aac-managed-sidebar {
			position: sticky;
			top: calc(env(safe-area-inset-top, 0px) + 6.75rem);
			align-self: flex-start;
			width: 18.5rem;
			height: calc(100vh - 6.75rem);
			overflow-y: auto;
			border-right: 1px solid rgba(0, 0, 0, 0.08);
			background-color: #030000;
			background-image:
				linear-gradient(180deg, rgba(5, 2, 2, <?php echo esc_attr($portal_design_settings['sidebar_overlay_start']); ?>), rgba(5, 2, 2, <?php echo esc_attr($portal_design_settings['sidebar_overlay_end']); ?>)),
				url('<?php echo esc_url($portal_design_settings['sidebar_background_url']); ?>');
			background-position: center center, center top;
			background-repeat: no-repeat, repeat-y;
			background-size: 100% 100%, 100% auto;
			color: #fff;
			padding: 1rem;
			box-sizing: border-box;
			box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05);
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
			padding: 0.75rem;
			border: 1px solid rgba(255, 255, 255, 0.1);
			border-radius: 1.1rem;
			background: <?php echo esc_html($portal_design_settings['sidebar_button_background']); ?>;
			color: #fff;
			font-size: 0.875rem;
			font-weight: 500;
			text-decoration: none;
			box-shadow: 0 10px 24px rgba(0, 0, 0, 0.28);
			transition: all 0.2s ease;
		}

		.aac-managed-sidebar a:hover {
			border-color: <?php echo esc_html($portal_design_settings['sidebar_accent_color']); ?>;
			background: <?php echo esc_html($portal_design_settings['sidebar_button_hover_background']); ?>;
			color: #fff;
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

		.aac-managed-sidebar a[aria-current="page"] .aac-managed-sidebar__icon {
			color: <?php echo esc_html($portal_design_settings['sidebar_accent_color']); ?>;
		}

		.aac-managed-sidebar a[aria-current="page"] {
			border-color: <?php echo esc_html($portal_design_settings['sidebar_accent_color']); ?>;
			background: <?php echo esc_html($portal_design_settings['sidebar_button_active_background']); ?>;
			box-shadow: 0 12px 28px rgba(0, 0, 0, 0.42);
			color: #fff;
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
			border-radius: 28px;
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
			border-radius: 24px;
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
			padding: 1.35rem;
		}

		body.pmpro-checkout .aac-managed-card .pmpro,
		body.pmpro-checkout .aac-managed-card .pmpro_section,
		body.pmpro-checkout .aac-managed-card .pmpro_card,
		body.pmpro-checkout .aac-managed-card form.pmpro_form,
		body.pmpro-checkout .aac-managed-card .pmpro_card_content,
		body.pmpro-checkout .aac-managed-card .pmpro_checkout_gateway,
		body.pmpro-checkout .aac-managed-card .pmpro_checkout-fields,
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
		}

		body.pmpro-checkout .aac-managed-card .pmpro_form_fieldset > .pmpro_card > .pmpro_card_content {
			display: grid;
			gap: 0.7rem;
			align-content: start;
			padding-top: 0 !important;
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
		}

		body.pmpro-checkout .aac-managed-card #pmpro_payment_information_fields .pmpro_form_legend {
			display: none;
		}

		body.pmpro-checkout .aac-managed-card #pmpro_payment_information_fields > .pmpro_card > .pmpro_card_content {
			gap: 0.45rem;
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
			margin-top: 0.9rem;
			padding-top: 0;
		}

		body.pmpro-checkout .aac-managed-card .pmpro_message {
			padding: 0.95rem 1rem;
			border-radius: 1rem;
		}

		body.pmpro-checkout .aac-managed-card .pmpro_form_field .select2-container {
			width: 100% !important;
		}

		body.pmpro-checkout .aac-managed-card .select2-container--default .select2-selection--single {
			min-height: 3rem;
			border-radius: 0.95rem;
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
			grid-template-columns: repeat(2, minmax(0, 1fr));
			gap: 1rem;
		}

		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__field {
			margin: 0;
		}

		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__label {
			display: block;
			margin: 0;
			cursor: pointer;
		}

		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__input {
			position: absolute;
			opacity: 0;
			pointer-events: none;
		}

		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__card {
			display: grid;
			grid-template-columns: auto 1fr;
			gap: 0.9rem;
			align-items: center;
			height: 100%;
			padding: 1rem 1.05rem;
			border: 1px solid rgba(12, 10, 9, 0.1);
			border-radius: 1.1rem;
			background: rgba(255, 255, 255, 0.92);
			box-shadow: 0 14px 34px rgba(12, 10, 9, 0.08);
			transition: border-color 160ms ease, box-shadow 160ms ease, transform 160ms ease;
		}

		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__label:hover .aac-membership-discounts__card {
			transform: translateY(-2px);
			box-shadow: 0 18px 42px rgba(12, 10, 9, 0.1);
		}

		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__input:focus-visible + .aac-membership-discounts__card {
			outline: 2px solid rgba(143, 21, 21, 0.28);
			outline-offset: 3px;
		}

		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__input:checked + .aac-membership-discounts__card {
			border-color: rgba(143, 21, 21, 0.52);
			box-shadow: 0 20px 40px rgba(143, 21, 21, 0.12);
			background: rgba(255, 248, 234, 0.92);
		}

		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__icon {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 3rem;
			height: 3rem;
			border-radius: 999px;
			background: rgba(143, 21, 21, 0.08);
			color: #8f1515;
		}

		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__icon svg {
			width: 1.45rem;
			height: 1.45rem;
		}

		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__body {
			display: grid;
			gap: 0.4rem;
		}

		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__copy {
			display: grid;
			gap: 0.22rem;
			color: #57534e;
		}

		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__copy strong {
			color: #0c0a09;
			font-size: 1rem;
			line-height: 1.2;
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
			border-radius: 1.1rem;
			background: rgba(255, 255, 255, 0.9);
			box-shadow: 0 14px 34px rgba(12, 10, 9, 0.08);
			transition: border-color 160ms ease, box-shadow 160ms ease, transform 160ms ease;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__label:hover .aac-magazine-addons__card {
			transform: translateY(-2px);
			box-shadow: 0 18px 42px rgba(12, 10, 9, 0.1);
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__input:focus-visible + .aac-magazine-addons__card {
			outline: 2px solid rgba(143, 21, 21, 0.28);
			outline-offset: 3px;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__input:checked + .aac-magazine-addons__card {
			border-color: rgba(143, 21, 21, 0.52);
			box-shadow: 0 20px 40px rgba(143, 21, 21, 0.12);
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
			border-radius: 0.85rem;
			background: rgba(255, 255, 255, 0.85);
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
			background: rgba(143, 21, 21, 0.08);
			color: #6b1010;
			font-weight: 700;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__summary-row--total strong {
			color: #8f1515;
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__pricing-note {
			margin: 0;
			padding: 0.9rem 1rem;
			border-radius: 1rem;
			background: rgba(143, 21, 21, 0.06);
			color: #6b1010;
			font-weight: 600;
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
			border: 1px solid rgba(12, 10, 9, 0.12);
			border-radius: 999px;
			background: rgba(255, 255, 255, 0.82);
			color: #292524;
			font-size: 0.92rem;
			font-weight: 700;
			letter-spacing: 0.01em;
			text-transform: none;
			padding: 0 0.95rem;
			transition: background-color 160ms ease, border-color 160ms ease, color 160ms ease, transform 160ms ease;
		}

		body.pmpro-checkout .aac-managed-card .aac-donation-option:hover {
			transform: translateY(-1px);
			background: rgba(12, 10, 9, 0.05);
		}

		body.pmpro-checkout .aac-managed-card .aac-donation-option[data-selected="true"] {
			border-color: #f8c235;
			background: #f8c235;
			color: #030000;
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
			border: 1px solid rgba(12, 10, 9, 0.08);
			border-radius: 1.1rem;
			background: linear-gradient(180deg, rgba(250, 249, 246, 0.98), rgba(245, 239, 228, 0.98));
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
			border-radius: 0.85rem;
			background: rgba(255, 255, 255, 0.82);
			color: #292524;
		}

		.aac-managed-card .aac-order-summary__row strong {
			color: #0c0a09;
			white-space: nowrap;
		}

		.aac-managed-card .aac-order-summary__row--total {
			background: rgba(143, 21, 21, 0.08);
			color: #6b1010;
			font-weight: 700;
		}

		.aac-managed-card .aac-order-summary__row--total strong {
			color: #8f1515;
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
			background: #f8c235;
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
			border-radius: 999px;
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
			.aac-managed-layout {
				display: block;
			}

			.aac-managed-sidebar {
				position: static;
				width: auto;
				height: auto;
				border-right: 0;
				border-bottom: 1px solid rgba(0, 0, 0, 0.08);
			}

			.aac-managed-main {
				padding-top: 1rem;
			}

			.aac-managed-header__inner {
				padding-left: 1rem;
				padding-right: 1rem;
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
<body <?php body_class($is_embedded_checkout ? 'aac-member-portal-embed' : (($is_portal_page && !$is_managed_pmpro_page) ? 'aac-member-portal-fullscreen' : ($is_public_shell_page ? 'aac-member-portal-fullscreen aac-member-portal-public-shell aac-public-page--' . sanitize_html_class($post->post_name) : 'aac-member-portal-fullscreen aac-member-portal-managed-shell'))); ?>>
<?php wp_body_open(); ?>
<?php if ($is_portal_page && !$is_managed_pmpro_page) : ?>
	<?php
	while (have_posts()) :
		the_post();
		echo do_shortcode('[' . AAC_Member_Portal_Plugin::SHORTCODE . ']');
	endwhile;
	?>
<?php elseif ($is_embedded_checkout) : ?>
	<section class="aac-managed-card aac-managed-card--embed">
		<?php
		while (have_posts()) :
			the_post();
			the_content();
		endwhile;
		?>
	</section>
	<script>
		(function () {
			const messageType = 'aac-pmpro-checkout-height';
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
		}());
	</script>
<?php elseif ($is_public_shell_page) : ?>
	<div class="aac-managed-header">
		<div class="aac-managed-header__inner">
			<div class="aac-managed-header__row">
				<a class="aac-managed-logo" href="<?php echo esc_url($portal_url); ?>">
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
						<a class="aac-managed-pill aac-managed-pill--primary" href="<?php echo esc_url(wp_logout_url(get_permalink($post))); ?>">Log Out</a>
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
							<span class="aac-managed-topnav__caret" aria-hidden="true">▾</span>
						</a>
						<div class="aac-managed-topnav__panel">
							<div class="aac-managed-topnav__panel-inner">
								<span class="aac-managed-topnav__panel-title"><?php echo esc_html($item['label']); ?></span>
								<ul>
									<li>
										<a class="aac-managed-topnav__link aac-managed-topnav__link--overview" href="<?php echo esc_url($item['href']); ?>">
											View all
										</a>
									</li>
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
					the_content();
				endwhile;
				?>
			</div>
		</div>
	</main>
<?php else : ?>
	<div class="aac-managed-shell">
		<header class="aac-managed-header">
			<div class="aac-managed-header__inner">
				<div class="aac-managed-header__row">
					<a class="aac-managed-logo" href="<?php echo esc_url($portal_url); ?>">
						<img
							src="https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/09/light-header-logo.svg"
							alt="American Alpine Club Logo"
						>
					</a>

					<div class="aac-managed-actions">
						<a class="aac-managed-pill aac-managed-pill--danger" href="<?php echo esc_url($portal_url . '#/donate'); ?>">Donate</a>
						<a class="aac-managed-pill aac-managed-pill--ghost" href="<?php echo esc_url($portal_url . '#/profile'); ?>">Member Profile</a>
						<a class="aac-managed-pill aac-managed-pill--primary" href="<?php echo esc_url(wp_logout_url(get_permalink())); ?>">Log Out</a>
					</div>
				</div>

				<nav class="aac-managed-topnav" aria-label="Primary">
					<?php foreach ($top_nav as $item) : ?>
						<div class="aac-managed-topnav__item">
							<a class="aac-managed-topnav__trigger" href="<?php echo esc_url($item['href']); ?>">
								<span><?php echo esc_html($item['label']); ?></span>
								<span class="aac-managed-topnav__caret" aria-hidden="true">▾</span>
							</a>
							<div class="aac-managed-topnav__panel">
								<div class="aac-managed-topnav__panel-inner">
									<span class="aac-managed-topnav__panel-title"><?php echo esc_html($item['label']); ?></span>
									<ul>
										<li>
											<a class="aac-managed-topnav__link aac-managed-topnav__link--overview" href="<?php echo esc_url($item['href']); ?>">
												View all
											</a>
										</li>
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
										<span><?php echo esc_html($item['label']); ?></span>
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
								<a class="aac-managed-pill aac-managed-pill--ghost" href="<?php echo esc_url($portal_url . '#/account'); ?>">Edit Profile</a>
								<a class="aac-managed-pill <?php echo $is_account_page ? 'aac-managed-pill--primary' : 'aac-managed-pill--ghost'; ?>" href="<?php echo esc_url($account_url); ?>">Account</a>
								<a class="aac-managed-pill <?php echo $is_billing_page ? 'aac-managed-pill--primary' : 'aac-managed-pill--ghost'; ?>" href="<?php echo esc_url($billing_url); ?>">Billing</a>
								<a class="aac-managed-pill <?php echo $is_orders_page ? 'aac-managed-pill--primary' : 'aac-managed-pill--ghost'; ?>" href="<?php echo esc_url($orders_url); ?>">Orders</a>
								<a class="aac-managed-pill <?php echo $is_cancel_page ? 'aac-managed-pill--primary' : 'aac-managed-pill--ghost'; ?>" href="<?php echo esc_url($cancel_url); ?>">Cancel</a>
								<a class="aac-managed-pill <?php echo $is_checkout_page ? 'aac-managed-pill--primary' : 'aac-managed-pill--ghost'; ?>" href="<?php echo esc_url($checkout_url); ?>">Checkout</a>
								<a class="aac-managed-pill <?php echo $is_confirmation_page ? 'aac-managed-pill--primary' : 'aac-managed-pill--ghost'; ?>" href="<?php echo esc_url($confirmation_url); ?>">Confirmation</a>
						</div>
					</section>

					<?php if ($is_account_page && $current_member_id > 0 && $current_primary_membership) : ?>
						<section class="aac-managed-account-summary">
							<div class="aac-managed-account-summary__grid">
								<div class="aac-managed-account-summary__item">
									<span class="aac-managed-account-summary__label">Tier</span>
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
							the_content();
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
		const currentUserEmail = <?php echo wp_json_encode($is_logged_in ? wp_get_current_user()->user_email : ''); ?>;

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

		const enhancePmproProfileInformation = () => {
			const socialLoginFieldset = document.getElementById('pmpro_social_login');
			const socialLoginActions = document.getElementById('pmpro_card_actions-social_login');
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

			const billingHeading = billingFieldset.querySelector('.pmpro_form_heading');
			if (billingHeading) {
				billingHeading.textContent = 'Profile Information';
			}

			const memberPreferencesFieldset =
				document.getElementById('pmpro_form_fieldset-member-preferences') ||
				document.getElementById('pmpro_form_fieldset-more-information');
			const memberPreferencesFields = memberPreferencesFieldset?.querySelector('.pmpro_form_fields');
			const memberPreferencesHeading = memberPreferencesFieldset?.querySelector('.pmpro_form_heading');

			if (memberPreferencesFieldset && memberPreferencesHeading) {
				memberPreferencesHeading.textContent = 'Member Preferences';
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

			if (memberPreferencesFieldset?.parentNode && billingFieldset.parentNode === memberPreferencesFieldset.parentNode) {
				billingFieldset.parentNode.insertBefore(memberPreferencesFieldset, billingFieldset.nextSibling);
			}

			const discountFieldset = document.getElementById('pmpro_form_fieldset-membership-discounts');
			if (discountFieldset?.parentNode && memberPreferencesFieldset?.parentNode === discountFieldset.parentNode) {
				discountFieldset.parentNode.insertBefore(discountFieldset, memberPreferencesFieldset.nextSibling);
			}

			const familyFieldset = document.getElementById('pmpro_form_fieldset-partner-family');
			if (familyFieldset?.parentNode && discountFieldset?.parentNode === familyFieldset.parentNode) {
				familyFieldset.parentNode.insertBefore(familyFieldset, discountFieldset.nextSibling);
			}

			const magazineFieldset = document.getElementById('pmpro_form_fieldset-magazine-addons');
			if (magazineFieldset?.parentNode && familyFieldset?.parentNode === magazineFieldset.parentNode) {
				magazineFieldset.parentNode.insertBefore(magazineFieldset, familyFieldset.nextSibling);
			} else if (magazineFieldset?.parentNode && discountFieldset?.parentNode === magazineFieldset.parentNode) {
				magazineFieldset.parentNode.insertBefore(magazineFieldset, discountFieldset.nextSibling);
			} else if (magazineFieldset?.parentNode && memberPreferencesFieldset?.parentNode === magazineFieldset.parentNode) {
				magazineFieldset.parentNode.insertBefore(magazineFieldset, memberPreferencesFieldset.nextSibling);
			}

			const donationFieldset = document.getElementById('pmpro_form_fieldset-donation');
			const autoRenewFieldset = document.getElementById('pmpro_autorenewal_checkbox');
			const paymentInformationFieldset = document.getElementById('pmpro_payment_information_fields');
			const checkoutSummary = document.querySelector('[data-aac-magazine-summary]');
			const autoRenewHeading = autoRenewFieldset?.querySelector('.pmpro_form_heading');

			if (autoRenewHeading) {
				autoRenewHeading.textContent = 'Automatic Renewals';
			}

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

			const basePrice = Number.parseFloat(fieldset.dataset.aacMagazineBasePrice || '0') || 0;
			const addonTotal = checkboxInputs.reduce((total, input) => {
				if (!input.checked) {
					return total;
				}

				return total + (Number.parseFloat(input.dataset.aacMagazinePrice || '0') || 0);
			}, 0);
			const updatedTotal = basePrice + addonTotal;
			const summary = document.querySelector('[data-aac-magazine-summary]');
			const membershipName = document.querySelector('#pmpro_level_name_text strong')?.textContent?.trim() || 'Membership';
			const familyModeInput = document.querySelector('input[name="aac_partner_family_mode"]:checked');
			const familyMode = familyModeInput?.value === 'family' ? 'family' : '';
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
				? `${selectedDiscountInput.dataset.aacMembershipDiscountLabel} (15%)`
				: '';
			const donationAmount = Math.max(0, Number.parseFloat(document.getElementById('donation')?.value || '0') || 0);
			const selectedAddons = checkboxInputs
				.filter((input) => input.checked)
				.map((input) => ({
					label: input.closest('.aac-magazine-addons__card')?.querySelector('.aac-magazine-addons__copy strong')?.textContent?.trim() || 'Magazine subscription',
					amount: Number.parseFloat(input.dataset.aacMagazinePrice || '0') || 0,
				}));
			const membershipLabel = familyMode === 'family' ? 'Partner Family membership' : `${membershipName} membership`;
			const lineItems = [
				{ label: membershipLabel, amount: basePrice },
				...(discountAmount > 0 && discountLabel ? [{ label: discountLabel, amount: 0 - discountAmount, isDiscount: true }] : []),
				...(familyAdultAmount > 0 ? [{ label: 'Additional adult', amount: familyAdultAmount }] : []),
				...(familyDependentsAmount > 0 ? [{ label: `${familyDependentCount} ${familyDependentCount === 1 ? 'dependent' : 'dependents'}`, amount: familyDependentsAmount }] : []),
				...(donationAmount > 0 ? [{ label: 'Donation', amount: donationAmount }] : []),
				...selectedAddons,
			];
			const grandTotal = basePrice - discountAmount + familyAdultAmount + familyDependentsAmount + addonTotal + donationAmount;
			if (summary) {
				summary.innerHTML = `
					<div class="aac-magazine-addons__summary-header">
						<p class="aac-magazine-addons__summary-title">Order summary</p>
						<p class="aac-magazine-addons__summary-caption">Review everything included before entering payment details.</p>
					</div>
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
			}

			const priceText = document.querySelector('#pmpro_level_cost .pmpro_level-price');
			if (priceText) {
				const baseText = priceText.dataset.aacBaseText || (priceText.textContent || '').trim();
				if (!priceText.dataset.aacBaseText) {
					priceText.dataset.aacBaseText = baseText;
				}

				const moneyMatches = baseText.match(/\$[0-9,]+(?:\.[0-9]{2})?/g) || [];
				if (moneyMatches.length === 1) {
					priceText.textContent = baseText.replace(moneyMatches[0], formatUsd(grandTotal));
				}

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

			document.querySelectorAll('input[name="aac_partner_family_mode"]').forEach((input) => {
				if (input.dataset.aacPartnerFamilyBound === 'true') {
					return;
				}

				input.addEventListener('change', syncMagazineAddonSummary);
				input.dataset.aacPartnerFamilyBound = 'true';
			});

			if (familyAdultInput && familyAdultInput.dataset.aacPartnerFamilyBound !== 'true') {
				familyAdultInput.addEventListener('change', syncMagazineAddonSummary);
				familyAdultInput.dataset.aacPartnerFamilyBound = 'true';
			}

			if (familyDependentsInput && familyDependentsInput.dataset.aacPartnerFamilyBound !== 'true') {
				familyDependentsInput.addEventListener('change', syncMagazineAddonSummary);
				familyDependentsInput.dataset.aacPartnerFamilyBound = 'true';
			}
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

		const replacePmproLoggedInAccountUsername = () => {
			if (!currentUserEmail) {
				return;
			}

			const accountFieldset = document.getElementById('pmpro_user_fields');
			if (!accountFieldset || accountFieldset.dataset.aacLoggedInEmailPatched === 'true') {
				return;
			}

			const accountParagraphs = accountFieldset.querySelectorAll('p');
			for (const paragraph of accountParagraphs) {
				const text = (paragraph.textContent || '').trim();
				if (!/You are logged in as/i.test(text) || !/different account/i.test(text)) {
					continue;
				}

				const usernameStrong = paragraph.querySelector('strong');
				if (usernameStrong) {
					usernameStrong.textContent = currentUserEmail;
					accountFieldset.dataset.aacLoggedInEmailPatched = 'true';
				}
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

		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', () => {
				syncPmproUsernameFromEmail();
				enhancePmproProfileInformation();
				enhancePmproDonationFieldset();
				syncMagazineAddonSummary();
				syncPmproStateDropdown();
				replacePmproLoggedInAccountUsername();
				bindManagedAutoRenewToggle();
			});
		} else {
			syncPmproUsernameFromEmail();
			enhancePmproProfileInformation();
			enhancePmproDonationFieldset();
			syncMagazineAddonSummary();
			syncPmproStateDropdown();
			replacePmproLoggedInAccountUsername();
			bindManagedAutoRenewToggle();
		}

		window.addEventListener('load', syncPmproUsernameFromEmail);
		window.addEventListener('load', enhancePmproProfileInformation);
		window.addEventListener('load', enhancePmproDonationFieldset);
		window.addEventListener('load', syncMagazineAddonSummary);
		window.addEventListener('load', syncPmproStateDropdown);
		window.addEventListener('load', replacePmproLoggedInAccountUsername);
		window.addEventListener('load', bindManagedAutoRenewToggle);
	}());
</script>
<?php wp_footer(); ?>
</body>
</html>
