<?php
if (!defined('ABSPATH')) {
	exit;
}

$nav_items = aac_portal_theme_get_primary_navigation();
$actions = aac_portal_theme_get_header_actions();
$logo_url = aac_portal_theme_get_header_logo_url();
$nav_background = aac_portal_theme_get_setting('design', 'nav_background', '#030000');
$nav_text_color = aac_portal_theme_get_setting('design', 'nav_text_color', '#ffffff');
$nav_hover_text_color = aac_portal_theme_get_setting('design', 'nav_hover_text_color', '#f8c235');
$nav_dropdown_background = aac_portal_theme_get_setting('design', 'nav_dropdown_background', 'rgba(11,9,8,0.95)');
$nav_dropdown_text_color = aac_portal_theme_get_setting('design', 'nav_dropdown_text_color', '#f4efe7');
$secondary_action_background = aac_portal_theme_get_setting('design', 'secondary_action_background', '#f8c235');
$secondary_action_text = aac_portal_theme_get_setting('design', 'secondary_action_text', '#000000');
$primary_action_background = aac_portal_theme_get_setting('design', 'primary_action_background', '#8f1515');
$primary_action_text = aac_portal_theme_get_setting('design', 'primary_action_text', '#ffffff');

if (!function_exists('aac_portal_theme_utility_icon_svg')) {
	function aac_portal_theme_utility_icon_svg($icon) {
		$icons = [
			'login' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/></svg>',
			'plus' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14"/><path d="M5 12h14"/></svg>',
			'dollar' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2v20"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
			'shield' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l7 3v6c0 5-3.5 8-7 9-3.5-1-7-4-7-9V6l7-3Z"/></svg>',
			'user' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
		];

		return $icons[$icon] ?? '';
	}
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>
	style="
		--aac-nav-bg: <?php echo esc_attr($nav_background); ?>;
		--aac-nav-text: <?php echo esc_attr($nav_text_color); ?>;
		--aac-nav-hover: <?php echo esc_attr($nav_hover_text_color); ?>;
		--aac-nav-dropdown-bg: <?php echo esc_attr($nav_dropdown_background); ?>;
		--aac-nav-dropdown-text: <?php echo esc_attr($nav_dropdown_text_color); ?>;
		--aac-action-secondary-bg: <?php echo esc_attr($secondary_action_background); ?>;
		--aac-action-secondary-text: <?php echo esc_attr($secondary_action_text); ?>;
		--aac-action-primary-bg: <?php echo esc_attr($primary_action_background); ?>;
		--aac-action-primary-text: <?php echo esc_attr($primary_action_text); ?>;
	"
>
<?php wp_body_open(); ?>
<div class="aac-theme-shell">
	<header class="aac-theme-header">
		<div class="aac-theme-header__desktop">
			<a class="aac-theme-header__logo" href="<?php echo esc_url(home_url('/')); ?>">
				<img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
			</a>

			<div class="aac-theme-header__nav-block">
				<div class="aac-theme-header__utility">
					<?php foreach ($actions as $action) : ?>
						<a class="aac-theme-header__utility-link" href="<?php echo esc_url($action['url']); ?>">
							<span class="aac-theme-header__utility-icon" aria-hidden="true">
								<?php echo aac_portal_theme_utility_icon_svg($action['icon'] ?? ''); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</span>
							<?php echo esc_html($action['label']); ?>
						</a>
					<?php endforeach; ?>
				</div>

				<nav class="aac-site-nav" aria-label="<?php esc_attr_e('Primary navigation', 'aac-portal-theme'); ?>">
					<ul class="aac-site-nav__list">
						<?php aac_portal_theme_render_nav_items($nav_items); ?>
					</ul>
				</nav>
			</div>
		</div>

		<div class="aac-theme-header__mobile">
			<div class="aac-theme-header__mobile-bar">
				<a class="aac-theme-header__logo aac-theme-header__logo--mobile" href="<?php echo esc_url(home_url('/')); ?>">
					<img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
				</a>
				<button class="aac-theme-header__toggle" type="button" data-aac-nav-toggle aria-expanded="false" aria-controls="aac-mobile-nav">
					<span></span>
					<span></span>
					<span></span>
				</button>
			</div>

			<div class="aac-theme-header__mobile-drawer" id="aac-mobile-nav" hidden>
				<nav class="aac-site-nav__mobile" aria-label="<?php esc_attr_e('Mobile primary navigation', 'aac-portal-theme'); ?>">
					<?php aac_portal_theme_render_nav_items($nav_items, true); ?>
				</nav>
				<div class="aac-theme-header__mobile-actions">
					<?php foreach ($actions as $action) : ?>
						<a class="aac-theme-action aac-theme-action--<?php echo esc_attr($action['kind']); ?>" href="<?php echo esc_url($action['url']); ?>">
							<?php echo esc_html($action['label']); ?>
						</a>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</header>

	<main class="aac-theme-main">
