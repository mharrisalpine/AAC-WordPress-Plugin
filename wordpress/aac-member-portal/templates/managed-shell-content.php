<?php
/**
 * Inline managed shell used to wrap managed PMPro account pages inside the theme page.
 *
 * Expected variables:
 * - string $content
 * - string $portal_url
 * - string $billing_url
 * - string $orders_url
 * - string $cancel_url
 * - string $checkout_url
 * - string $confirmation_url
 * - string $page_title
 * - string $page_kicker
 * - string $page_description
 * - bool $is_account_page
 * - bool $is_billing_page
 * - bool $is_orders_page
 * - bool $is_cancel_page
 * - bool $is_checkout_page
 * - bool $is_confirmation_page
 */

if (!defined('ABSPATH')) {
	exit;
}

$portal_plugin = $GLOBALS['aac_member_portal_plugin'] ?? null;
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
$checkout_profile_defaults = $portal_plugin instanceof AAC_Member_Portal_Plugin
	? $portal_plugin->get_pmpro_checkout_profile_defaults()
	: [
		'publication_pref' => 'Print',
		'aaj_pref' => 'Print',
		'anac_pref' => 'Print',
		'acj_pref' => 'Print',
		'guidebook_pref' => 'Print',
		'size' => 'none',
	];
$is_logged_in = is_user_logged_in();
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

if (!function_exists('aac_member_portal_sidebar_icon_svg')) {
	function aac_member_portal_sidebar_icon_svg($icon) {
		$icons = [
			'user' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg>',
			'store' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9l1.5-5h15L21 9"/><path d="M4 9h16v10a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1Z"/><path d="M9 20v-6h6v6"/></svg>',
			'shield' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l7 3v6c0 5-3.5 8-7 9-3.5-1-7-4-7-9V6l7-3Z"/></svg>',
			'settings' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1 1.54V21a2 2 0 1 1-4 0v-.09a1.7 1.7 0 0 0-1-1.54 1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.7 1.7 0 0 0 .34-1.87 1.7 1.7 0 0 0-1.54-1H3a2 2 0 1 1 0-4h.09a1.7 1.7 0 0 0 1.54-1 1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.7 1.7 0 0 0 1.87.34H9A1.7 1.7 0 0 0 10 3.09V3a2 2 0 1 1 4 0v.09a1.7 1.7 0 0 0 1 1.54 1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.7 1.7 0 0 0-.34 1.87V9c0 .67.39 1.28 1 1.54.18.08.37.13.57.13H21a2 2 0 1 1 0 4h-.09a1.7 1.7 0 0 0-1.54 1Z"/></svg>',
			'pen' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 1 1 3 3L7 19l-4 1 1-4Z"/></svg>',
			'book' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 7v14"/><path d="M3 18.5A2.5 2.5 0 0 1 5.5 16H12v5H5.5A2.5 2.5 0 0 1 3 18.5Z"/><path d="M21 18.5a2.5 2.5 0 0 0-2.5-2.5H12v5h6.5A2.5 2.5 0 0 0 21 18.5Z"/><path d="M5.5 16V5a2 2 0 0 1 2-2H12v13H5.5Z"/><path d="M18.5 16V5a2 2 0 0 0-2-2H12v13h6.5Z"/></svg>',
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
?>
<style>
	.wp-site-blocks > header,
	.wp-site-blocks > footer,
	.wp-site-blocks > main > .wp-block-group > .wp-block-group > .wp-block-post-title,
	.wp-site-blocks > main .wp-block-post-title,
	.wp-site-blocks > main .entry-title {
		display: none !important;
	}

	.wp-site-blocks > main,
	.wp-site-blocks > main .wp-block-group,
	.wp-site-blocks > main .wp-block-columns,
	.wp-site-blocks > main .wp-block-column {
		margin: 0 !important;
		padding: 0 !important;
		max-width: none !important;
		background: transparent !important;
	}

	.wp-site-blocks > main .entry-content {
		margin: 0 !important;
		max-width: none !important;
	}

	.aac-managed-shell {
		width: 100%;
		max-width: 100%;
		margin-left: 0;
		min-height: 100vh;
		overflow-x: clip;
		background:
			linear-gradient(180deg, rgba(255, 255, 255, 0.56), rgba(246, 241, 232, 0.74)),
			radial-gradient(circle at 16% 10%, rgba(248, 194, 53, 0.12), transparent 24%),
			radial-gradient(circle at 84% 14%, rgba(3, 0, 0, 0.04), transparent 19%),
			url('<?php echo esc_url(AAC_MEMBER_PORTAL_URL . 'app/app-page-topo.svg'); ?>') center top / 1120px auto repeat;
		color: #0c0a09;
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

	.aac-managed-header__row,
	.aac-managed-actions,
	.aac-managed-topnav,
	.aac-managed-actions-row,
	.aac-managed-layout,
	.aac-managed-card .pmpro_actions_nav,
	.aac-managed-card .pmpro_card_actions {
		display: flex;
		flex-wrap: wrap;
		gap: 0.75rem;
	}

	.aac-managed-header__row {
		align-items: center;
		justify-content: space-between;
		padding-bottom: 0.9rem;
		border-bottom: 1px solid rgba(255, 255, 255, 0.1);
	}

	.aac-managed-logo img {
		display: block;
		height: 56px;
		width: auto;
	}

	.aac-managed-topnav {
		justify-content: flex-end;
		padding-top: 1rem;
	}

	.aac-managed-topnav a,
	.aac-managed-pill {
		text-decoration: none;
		transition: color 0.2s ease, background-color 0.2s ease, border-color 0.2s ease;
	}

	.aac-managed-topnav__item {
		position: relative;
		display: flex;
		align-items: center;
	}

	.aac-managed-topnav__trigger {
		display: inline-flex;
		align-items: center;
		gap: 0.55rem;
		color: rgba(255, 255, 255, 0.84);
		font-size: 0.84rem;
		font-weight: 600;
		letter-spacing: 0.22em;
		text-transform: uppercase;
		padding: 0.75rem 0;
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

	.aac-managed-pill {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		min-height: 2.75rem;
		padding: 0 1.2rem;
		border-radius: 0;
		font-size: 0.82rem;
		font-weight: 700;
		letter-spacing: 0.14em;
		text-transform: uppercase;
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
	.aac-managed-shell .wp-element-button {
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

	.aac-managed-layout {
		display: flex;
		flex-wrap: nowrap;
		align-items: flex-start;
		gap: 0;
		min-height: calc(100vh - 132px);
	}

	.aac-managed-sidebar {
		position: sticky;
		top: calc(env(safe-area-inset-top, 0px) + 6.75rem);
		align-self: flex-start;
		width: 18.5rem;
		height: calc(100vh - (env(safe-area-inset-top, 0px) + 6.75rem));
		max-height: calc(100vh - (env(safe-area-inset-top, 0px) + 6.75rem));
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

	.aac-managed-card {
		margin-top: 1.5rem;
		border: 1px solid rgba(0, 0, 0, 0.08);
		border-radius: 28px;
		background: linear-gradient(180deg, rgba(255, 255, 255, 0.92), rgba(255, 255, 255, 0.82));
		padding: 1.5rem;
		box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
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
	.aac-managed-card .pmpro_checkout-fields + .pmpro_checkout-fields {
		margin-top: 1rem;
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

	body.pmpro-checkout .aac-managed-card .aac-member-preferences__intro {
		margin: 1rem 0 0;
		color: #57534e;
		line-height: 1.7;
		text-align: center;
	}

	body.pmpro-checkout .aac-managed-card .aac-member-preferences__grid {
		display: grid;
		grid-template-columns: repeat(4, minmax(0, 1fr));
		gap: 1rem;
		margin-top: 1.5rem;
		align-items: stretch;
	}

	body.pmpro-checkout .aac-managed-card .aac-member-preferences__card {
		position: relative;
		display: flex;
		flex-direction: column;
		min-height: 23rem;
		padding: 0;
		border-radius: 1.3rem;
		overflow: hidden;
		border: 1px solid rgba(255, 255, 255, 0.16);
		box-shadow: 0 18px 42px rgba(12, 10, 9, 0.16);
		color: #fff;
		background: linear-gradient(180deg, #1c1714 0%, #0f0c0a 100%);
	}

	body.pmpro-checkout .aac-managed-card .aac-member-preferences__card::before {
		content: '';
		position: absolute;
		inset: 0;
		background:
			radial-gradient(circle at 18% 20%, rgba(255, 255, 255, 0.18), transparent 34%),
			radial-gradient(circle at 80% 18%, rgba(255, 255, 255, 0.1), transparent 28%),
			linear-gradient(180deg, rgba(255, 255, 255, 0.06), transparent 36%);
		pointer-events: none;
	}

	body.pmpro-checkout .aac-managed-card .aac-member-preferences__card > * {
		position: relative;
		z-index: 1;
	}

	body.pmpro-checkout .aac-managed-card .aac-member-preferences__art {
		min-height: 13.25rem;
		background:
			linear-gradient(160deg, rgba(247, 241, 228, 0.96), rgba(226, 214, 192, 0.92));
		background-repeat: no-repeat;
		background-position: center center;
		background-size: contain;
		border-bottom: 1px solid rgba(255, 255, 255, 0.14);
	}

	body.pmpro-checkout .aac-managed-card .aac-member-preferences__content {
		display: flex;
		flex: 1 1 auto;
		flex-direction: column;
		gap: 0.85rem;
		padding: 1rem;
		background: linear-gradient(180deg, rgba(16, 12, 10, 0.88), rgba(10, 8, 7, 0.96));
	}

	body.pmpro-checkout .aac-managed-card .aac-member-preferences__card--journal {
		background: linear-gradient(180deg, #223041 0%, #111111 100%);
	}

	body.pmpro-checkout .aac-managed-card .aac-member-preferences__card--accidents {
		background: linear-gradient(180deg, #4d2020 0%, #111111 100%);
	}

	body.pmpro-checkout .aac-managed-card .aac-member-preferences__card--guidebook {
		background: linear-gradient(180deg, #5a4431 0%, #111111 100%);
	}

	body.pmpro-checkout .aac-managed-card .aac-member-preferences__title-block {
		padding: 0.85rem 0.95rem;
		border-radius: 1rem;
		background: rgba(0, 0, 0, 0.52);
		backdrop-filter: blur(4px);
		box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.08);
	}

	body.pmpro-checkout .aac-managed-card .aac-member-preferences__eyebrow {
		display: inline-flex;
		width: fit-content;
		padding: 0.3rem 0.65rem;
		border-radius: 999px;
		background: rgba(255, 255, 255, 0.14);
		font-size: 0.72rem;
		font-weight: 700;
		letter-spacing: 0.16em;
		text-transform: uppercase;
		color: #fff;
	}

	body.pmpro-checkout .aac-managed-card .aac-member-preferences__title {
		margin: 0.7rem 0 0;
		font-size: 1.55rem;
		line-height: 1.08;
		font-weight: 800;
		letter-spacing: 0.01em;
		color: #fff;
	}

	body.pmpro-checkout .aac-managed-card .aac-member-preferences__description {
		margin: 0;
		color: rgba(255, 255, 255, 0.82);
		font-size: 0.95rem;
		line-height: 1.5;
	}

	body.pmpro-checkout .aac-managed-card .aac-member-preferences__choices {
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: 0.65rem;
		margin-top: auto;
	}

	body.pmpro-checkout .aac-managed-card .aac-member-preferences__choice {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		min-height: 3rem;
		padding: 0.7rem 0.9rem;
		border-radius: 0;
		border: 1px solid rgba(255, 255, 255, 0.38);
		background: rgba(12, 10, 9, 0.18);
		color: rgba(255, 255, 255, 0.92);
		font-weight: 700;
		cursor: pointer;
		transition: background 160ms ease, border-color 160ms ease, transform 160ms ease, color 160ms ease;
	}

	body.pmpro-checkout .aac-managed-card .aac-member-preferences__choice:hover {
		transform: translateY(-1px);
		border-color: rgba(248, 194, 53, 0.68);
	}

	body.pmpro-checkout .aac-managed-card .aac-member-preferences__choice.is-active {
		background: rgba(248, 194, 53, 0.96);
		border-color: rgba(248, 194, 53, 0.96);
		color: #0c0a09;
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

	@media (max-width: 720px) {
		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__promo-form {
			grid-template-columns: 1fr;
		}
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__pricing-note {
		margin: 0;
		padding: 0.9rem 1rem;
		border-radius: 1rem;
		background: rgba(143, 21, 21, 0.06);
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
		border: 1px solid rgba(12, 10, 9, 0.08);
		border-radius: 1rem;
		background: rgba(255, 255, 255, 0.92);
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

	.aac-managed-card .pmpro_message:last-child,
	.aac-managed-card .pmpro_form_submit:last-child,
	.aac-managed-card form.pmpro_form > .pmpro_form_submit:last-child {
		margin-bottom: 0;
	}

	.aac-managed-card .pmpro_form_submit {
		padding-bottom: 0;
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
	}
</style>

<div class="aac-managed-shell">
	<header class="aac-managed-header">
		<div class="aac-managed-header__inner">
			<div class="aac-managed-header__row">
				<a class="aac-managed-logo" href="<?php echo esc_url($portal_url . '#/home'); ?>">
					<img src="https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/09/light-header-logo.svg" alt="American Alpine Club Logo">
				</a>

				<div class="aac-managed-actions">
					<a class="aac-managed-pill aac-managed-pill--danger" href="<?php echo esc_url($portal_url . '#/donate'); ?>">Donate</a>
					<a class="aac-managed-pill aac-managed-pill--ghost" href="<?php echo esc_url($portal_url . '#/profile'); ?>">Member Profile</a>
					<a class="aac-managed-pill aac-managed-pill--primary" href="<?php echo esc_url(wp_logout_url($portal_url . '#/login')); ?>">Log Out</a>
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
							<a class="aac-managed-pill <?php echo !empty($is_account_page) ? 'aac-managed-pill--primary' : 'aac-managed-pill--ghost'; ?>" href="<?php echo esc_url($account_url); ?>">Account</a>
							<a class="aac-managed-pill <?php echo $is_billing_page ? 'aac-managed-pill--primary' : 'aac-managed-pill--ghost'; ?>" href="<?php echo esc_url($billing_url); ?>">Billing</a>
							<a class="aac-managed-pill <?php echo $is_orders_page ? 'aac-managed-pill--primary' : 'aac-managed-pill--ghost'; ?>" href="<?php echo esc_url($orders_url); ?>">Orders</a>
							<a class="aac-managed-pill <?php echo $is_cancel_page ? 'aac-managed-pill--primary' : 'aac-managed-pill--ghost'; ?>" href="<?php echo esc_url($cancel_url); ?>">Cancel</a>
							<a class="aac-managed-pill <?php echo $is_confirmation_page ? 'aac-managed-pill--primary' : 'aac-managed-pill--ghost'; ?>" href="<?php echo esc_url($confirmation_url); ?>">Confirmation</a>
						</div>
					</section>

				<?php if (!empty($is_account_page) && $current_member_id > 0 && $current_primary_membership) : ?>
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
					<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</section>
			</div>
		</main>
	</div>
</div>
<script>
	(function () {
		const currentUserEmail = <?php echo wp_json_encode($is_logged_in ? wp_get_current_user()->user_email : ''); ?>;
		const currentUserDisplayName = <?php
			if ($is_logged_in) {
				$current_user = wp_get_current_user();
				$display_name = trim(($current_user->first_name ?? '') . ' ' . ($current_user->last_name ?? ''));
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

	const ensureHiddenPreferenceInput = (form, name, value) => {
		if (!form) {
			return null;
		}

		let input = form.querySelector(`input[name="${name}"]`);
		if (!input) {
			input = document.createElement('input');
			input.type = 'hidden';
			input.name = name;
			form.appendChild(input);
		}

		input.value = value;
		return input;
	};

	const buildMemberPreferenceCards = (fieldset, currentLevelId) => {
		if (!fieldset) {
			return;
		}

		const form = fieldset.closest('form');
		const tshirtField = document.getElementById('t_shirt_div');
		const publicationField =
			document.getElementById('publications_preference_div') ||
			fieldset.querySelector('.pmpro_form_field-publications_preference');
		const guidebookField =
			document.getElementById('guidebook_preferences_div') ||
			fieldset.querySelector('.pmpro_form_field-guidebook_preferences');

		if (!tshirtField) {
			return;
		}

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

		hideOriginalField(publicationField);
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

		const readPreferenceValue = (name, fallback) => {
			const existingValue = form?.querySelector(`input[name="${name}"]`)?.value;
			return existingValue === 'Print' || existingValue === 'Digital' ? existingValue : fallback;
		};

		const publicationSelect = publicationField?.querySelector('select');
		const guidebookSelect = guidebookField?.querySelector('select');
		const legacyPublicationValue = checkoutProfileDefaults.publication_pref || publicationSelect?.value || 'Print';
		const aajHiddenInput = ensureHiddenPreferenceInput(form, 'aac_aaj_pref', readPreferenceValue('aac_aaj_pref', checkoutProfileDefaults.aaj_pref || legacyPublicationValue));
		const anacHiddenInput = ensureHiddenPreferenceInput(form, 'aac_anac_pref', readPreferenceValue('aac_anac_pref', checkoutProfileDefaults.anac_pref || legacyPublicationValue));
		const acjHiddenInput = ensureHiddenPreferenceInput(form, 'aac_acj_pref', readPreferenceValue('aac_acj_pref', checkoutProfileDefaults.acj_pref || legacyPublicationValue));
		const guidebookHiddenInput = ensureHiddenPreferenceInput(form, 'aac_guidebook_pref', readPreferenceValue('aac_guidebook_pref', checkoutProfileDefaults.guidebook_pref || guidebookSelect?.value || 'Print'));
		const legacyPublicationHiddenInput = ensureHiddenPreferenceInput(form, 'aac_publication_pref', readPreferenceValue('aac_publication_pref', legacyPublicationValue));

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

		const createPreferenceCard = ({ themeClass, eyebrow, title, description, hiddenInput, imageUrl, legacySelect, onChange }) => {
			if (!hiddenInput) {
				return null;
			}

			const card = document.createElement('article');
			card.className = `aac-member-preferences__card ${themeClass}`;
			card.dataset.aacPrefSource = hiddenInput.name;
			if (imageUrl) {
				card.style.setProperty('--aac-member-pref-image', `url("${String(imageUrl).replace(/"/g, '&quot;')}")`);
			}
			card.innerHTML = `
				<div class="aac-member-preferences__art" style="${imageUrl ? `background-image: var(--aac-member-pref-image);` : ''}"></div>
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
				const nextValue = (hiddenInput.value || 'Digital').trim() === 'Print' ? 'Print' : 'Digital';
				hiddenInput.value = nextValue;
				if (legacySelect) {
					legacySelect.value = nextValue;
				}
				card.querySelectorAll('.aac-member-preferences__choice').forEach((choice) => {
					choice.classList.toggle('is-active', choice.dataset.value === nextValue);
				});
			};

			card.querySelectorAll('.aac-member-preferences__choice').forEach((choice) => {
				choice.addEventListener('click', () => {
					hiddenInput.value = choice.dataset.value;
					if (legacySelect) {
						legacySelect.value = choice.dataset.value;
						legacySelect.dispatchEvent(new Event('change', { bubbles: true }));
					}
					if (typeof onChange === 'function') {
						onChange(choice.dataset.value);
					}
					syncMagazineAddonSummary();
					document.querySelectorAll(`[data-aac-pref-source="${hiddenInput.name}"]`).forEach((node) => {
						node.dispatchEvent(new CustomEvent('aac:sync-card-state'));
					});
				});
			});

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
				hiddenInput: aajHiddenInput,
				imageUrl: publicationCardImages.aaj,
				legacySelect: publicationSelect,
				onChange: (value) => {
					legacyPublicationHiddenInput.value = value;
				},
			}),
			createPreferenceCard({
				themeClass: 'aac-member-preferences__card--accidents',
				eyebrow: 'Annual',
				title: 'Accidents in North American Climbing',
				description: 'Annual accident review. Choose print delivery or digital-only access.',
				hiddenInput: anacHiddenInput,
				imageUrl: publicationCardImages.anac,
			}),
			createPreferenceCard({
				themeClass: 'aac-member-preferences__card--journal',
				eyebrow: 'Journal',
				title: 'American Climbing Journal',
				description: 'Member stories and club updates. Choose print delivery or digital-only access.',
				hiddenInput: acjHiddenInput,
				imageUrl: publicationCardImages.acj,
			}),
			createPreferenceCard({
				themeClass: 'aac-member-preferences__card--guidebook',
				eyebrow: 'Quarterly',
				title: 'Guidebook to Membership',
				description: 'Quarterly member publication. Choose print delivery or digital-only access.',
				hiddenInput: guidebookHiddenInput,
				imageUrl: publicationCardImages.guidebook,
				legacySelect: guidebookSelect,
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
			const passwordInput = userFieldsFieldset?.querySelector('input[name="password"]');
			const emailRow = emailInput?.closest('.pmpro_cols-2');
			const passwordRow = passwordInput?.closest('.pmpro_cols-2');
			if (accountFields && emailRow && passwordRow && emailRow !== passwordRow) {
				accountFields.insertBefore(emailRow, passwordRow);
			}

			const billingHeading = billingFieldset.querySelector('.pmpro_form_heading');
			if (billingHeading) {
				billingHeading.textContent = 'Contact Information';
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
		const readPublicationPreferenceValue = (inputName, fallbackSelector) => {
			const hiddenValue = (document.querySelector(`input[name="${inputName}"]`)?.value || '').trim();
			if (hiddenValue === 'Print' || hiddenValue === 'Digital') {
				return hiddenValue;
			}

			const fallbackValue = (document.querySelector(fallbackSelector)?.value || '').trim();
			return fallbackValue === 'Print' ? 'Print' : 'Digital';
		};
		const countryValue = String(document.getElementById('bcountry')?.value || 'US').trim().toUpperCase();
		const isInternationalCountry = !['', 'US', 'USA', 'UNITED STATES', 'UNITED STATES OF AMERICA'].includes(countryValue);
		const hasPrintPublicationSelection = [
			readPublicationPreferenceValue('aac_aaj_pref', '#publications_preference_div select'),
			readPublicationPreferenceValue('aac_anac_pref', '#publications_preference_div select'),
			readPublicationPreferenceValue('aac_acj_pref', '#publications_preference_div select'),
			readPublicationPreferenceValue('aac_guidebook_pref', '#guidebook_preferences_div select'),
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

		document.querySelectorAll('#publications_preference_div select, #guidebook_preferences_div select').forEach((select) => {
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

			input.addEventListener('change', () => {
				if (input.checked) {
					document.querySelectorAll(`input[name="${input.name}"]`).forEach((candidate) => {
						if (candidate === input) {
							candidate.setAttribute('checked', 'checked');
						} else {
							candidate.checked = false;
							candidate.removeAttribute('checked');
						}
					});

					const familyShortcut = document.getElementById('aac_partner_family_shortcut');
					if (familyShortcut) {
						familyShortcut.checked = false;
						familyShortcut.removeAttribute('checked');
					}
				} else {
					input.removeAttribute('checked');
				}

				syncMagazineAddonSummary();
			});

			input.dataset.aacToggleableBound = 'true';
		});
	};

	const bindFamilySelectionShortcut = () => {
		const shortcut = document.getElementById('aac_partner_family_shortcut');
		const modeInput = document.getElementById('aac_partner_family_mode');
		const details = document.querySelector('[data-aac-partner-family-details]');
		if (!shortcut || !modeInput || !details) {
			return;
		}

		const syncFamilyState = () => {
			const active = shortcut.checked;
			if (active) {
				document.querySelectorAll('input[name="aac_membership_discount"][data-aac-toggleable-choice="true"]').forEach((input) => {
					input.checked = false;
					input.removeAttribute('checked');
				});
			}
			modeInput.value = active ? 'family' : '';
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

	const relabelTShirtSizeOptions = () => {
		document.querySelectorAll('select[name="t_shirt"]').forEach((select) => {
			if (select.dataset.aacTshirtEnhanced !== 'true') {
				select.required = false;
				select.classList.remove('pmpro_form_input-required');

				const field = select.closest('.pmpro_form_field');
				field?.classList.remove('pmpro_form_field-required');
				field?.querySelector('.pmpro_asterisk')?.remove();

				let noTshirtOption = select.querySelector('option[value="none"]');
				if (!noTshirtOption) {
					noTshirtOption = document.createElement('option');
					noTshirtOption.value = 'none';
					noTshirtOption.textContent = 'No T-shirt';
					select.insertBefore(noTshirtOption, select.firstChild);
				}

				select.querySelectorAll('option').forEach((option) => {
					if ((option.value || '').trim() === '') {
						option.remove();
					}
				});

				const desiredTshirtValue = (checkoutProfileDefaults.size || 'none').trim() || 'none';
				if (select.querySelector(`option[value="${desiredTshirtValue}"]`)) {
					select.value = desiredTshirtValue;
				} else if (!select.value) {
					select.value = 'none';
				}

				if (select.value === 'none') {
					noTshirtOption.selected = true;
					select.dispatchEvent(new Event('change', { bubbles: true }));
				}

				select.dataset.aacTshirtEnhanced = 'true';
			}

			Array.from(select.options).forEach((option) => {
				const rawValue = (option.value || option.textContent || '').trim();
				if (rawValue === 'none' || rawValue === 'No T-shirt') {
					option.textContent = 'No T-shirt';
					return;
				}

				option.textContent = rawValue.startsWith('Unisex ') ? rawValue : `Unisex ${rawValue}`;
			});
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
			if (!currentUserDisplayName) {
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
				const escapedName = String(currentUserDisplayName)
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
			if (!toggle || toggle.dataset.aacAutoRenewBound === 'true') {
				return;
			}

			toggle.addEventListener('change', () => {
				const enableUrl = toggle.dataset.enableUrl || '';
				const disableUrl = toggle.dataset.disableUrl || '';
				const targetUrl = toggle.checked ? enableUrl : disableUrl;

				if (targetUrl) {
					window.location.assign(targetUrl);
					return;
				}

				toggle.checked = !toggle.checked;
			});

			toggle.dataset.aacAutoRenewBound = 'true';
		};

		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', () => {
				syncPmproUsernameFromEmail();
				bindEmailAvailabilityCheck();
				enhancePmproProfileInformation();
				enhanceCheckoutAutoRenewFieldset();
				enhancePmproDonationFieldset();
				bindToggleableMembershipDiscounts();
				bindFamilySelectionShortcut();
				relabelTShirtSizeOptions();
				syncMagazineAddonSummary();
				syncPmproStateDropdown();
				replacePmproLoggedInAccountUsername();
				bindManagedAutoRenewToggle();
			});
		} else {
			syncPmproUsernameFromEmail();
			bindEmailAvailabilityCheck();
			enhancePmproProfileInformation();
			enhanceCheckoutAutoRenewFieldset();
			enhancePmproDonationFieldset();
			bindToggleableMembershipDiscounts();
			bindFamilySelectionShortcut();
			relabelTShirtSizeOptions();
			syncMagazineAddonSummary();
			syncPmproStateDropdown();
			replacePmproLoggedInAccountUsername();
			bindManagedAutoRenewToggle();
		}

		window.addEventListener('load', syncPmproUsernameFromEmail);
		window.addEventListener('load', bindEmailAvailabilityCheck);
		window.addEventListener('load', enhancePmproProfileInformation);
		window.addEventListener('load', enhanceCheckoutAutoRenewFieldset);
		window.addEventListener('load', enhancePmproDonationFieldset);
		window.addEventListener('load', bindToggleableMembershipDiscounts);
		window.addEventListener('load', bindFamilySelectionShortcut);
		window.addEventListener('load', relabelTShirtSizeOptions);
		window.addEventListener('load', syncMagazineAddonSummary);
		window.addEventListener('load', syncPmproStateDropdown);
		window.addEventListener('load', replacePmproLoggedInAccountUsername);
		window.addEventListener('load', bindManagedAutoRenewToggle);
	}());
</script>
