<?php

if (!defined('ABSPATH')) {
	exit;
}

define('AAC_PORTAL_THEME_VERSION', '0.1.0');

function aac_portal_theme_portal_route($route = '') {
	$route = trim((string) $route, '/');
	return home_url('/membership/' . ($route ? '#/' . $route : ''));
}

function aac_portal_theme_resolve_content_url($path) {
	$path = '/' . ltrim((string) $path, '/');

	if ('/' === $path) {
		return home_url('/');
	}

	$page_id = url_to_postid(home_url($path));
	if ($page_id) {
		return get_permalink($page_id);
	}

	$raw_path = trim($path, '/');
	if ('' === $raw_path) {
		return home_url('/');
	}

	$page = get_page_by_path($raw_path, OBJECT, ['page', 'post']);
	if ($page instanceof WP_Post) {
		return get_permalink($page);
	}

	return null;
}

function aac_portal_theme_filter_navigation_tree($items) {
	$filtered = [];

	foreach ($items as $item) {
		$children = [];
		if (!empty($item['children']) && is_array($item['children'])) {
			$children = aac_portal_theme_filter_navigation_tree($item['children']);
		}

		$url = $item['url'] ?? '';
		if (is_string($url) && '' !== $url && 0 === strpos($url, home_url('/')) && false === strpos($url, '#')) {
			$resolved = aac_portal_theme_resolve_content_url(wp_make_link_relative($url));
			$url = $resolved ?: '';
		}

		if (empty($url) && !empty($children)) {
			$url = $children[0]['url'] ?? '';
		}

		if (empty($url)) {
			continue;
		}

		$item['url'] = $url;
		$item['children'] = $children;
		$filtered[] = $item;
	}

	return $filtered;
}

function aac_portal_theme_setup() {
	add_theme_support('title-tag');
	add_theme_support('post-thumbnails');
	add_theme_support('custom-logo', [
		'height'      => 120,
		'width'       => 360,
		'flex-height' => true,
		'flex-width'  => true,
	]);
	add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);

	register_nav_menus([
		'primary' => __('Primary Navigation', 'aac-portal-theme'),
		'footer'  => __('Footer Navigation', 'aac-portal-theme'),
	]);
}
add_action('after_setup_theme', 'aac_portal_theme_setup');

function aac_portal_theme_enqueue_assets() {
	wp_enqueue_style(
		'aac-portal-theme-typekit',
		'https://use.typekit.net/veb7xhf.css',
		[],
		null
	);

	wp_enqueue_style(
		'aac-portal-theme',
		get_stylesheet_uri(),
		[],
		AAC_PORTAL_THEME_VERSION
	);

	wp_enqueue_style(
		'aac-portal-theme-shell',
		get_template_directory_uri() . '/assets/css/theme.css',
		['aac-portal-theme', 'aac-portal-theme-typekit'],
		filemtime(get_template_directory() . '/assets/css/theme.css')
	);

	wp_enqueue_script(
		'aac-portal-theme-shell',
		get_template_directory_uri() . '/assets/js/theme.js',
		[],
		filemtime(get_template_directory() . '/assets/js/theme.js'),
		true
	);
}
add_action('wp_enqueue_scripts', 'aac_portal_theme_enqueue_assets');

function aac_portal_theme_default_settings() {
	return [
		'content' => [
			'home_hero_kicker'                => 'American Alpine Club',
			'home_hero_title'                 => "United\nWe Climb.",
			'home_hero_description'           => 'AAC advances climbing knowledge, rescue support, advocacy, grants, publications, and community resources for climbers who care deeply about the mountains.',
			'home_primary_cta_label'          => 'Join',
			'home_primary_cta_url'            => aac_portal_theme_portal_route('join'),
			'home_secondary_cta_label'        => 'Donate',
			'home_secondary_cta_url'          => 'https://membership.americanalpineclub.org/donate',
			'home_intro_kicker'               => 'About Our Work',
			'home_intro_title'                => 'Built for climbers. Focused on impact.',
			'home_intro_description'          => 'Since 1902, the American Alpine Club has backed climbers with practical resources, mountain knowledge, and community support. Our work spans advocacy, grants, rescue benefits, publications, library stewardship, lodging, and programs that help people keep showing up for the climbing life.',
			'home_intro_secondary_description'=> 'Across public lands, climbing culture, and member services, the AAC exists to protect access, expand opportunity, preserve knowledge, and strengthen the broader climbing community.',
			'home_intro_button_label'         => 'Explore Our Work',
			'home_intro_button_url'           => home_url('/our-work/'),
			'home_involvement_kicker'         => 'Our Work',
			'home_involvement_title'          => 'How the AAC shows up for climbers',
			'home_involvement_button_label'   => 'View Our Work',
			'home_involvement_button_url'     => home_url('/our-work/'),
			'home_publications_kicker'        => 'Library',
			'home_publications_title'         => 'Go deeper into AAC knowledge and community',
			'home_publications_button_label'  => 'Read More',
			'home_publications_button_url'    => home_url('/stories/'),
			'home_store_kicker'               => 'Store',
			'home_store_title'                => 'Shared visual language',
			'home_store_description'          => 'Header, top navigation, hero layouts, and cards all reuse the same AAC visual DNA.',
			'home_store_button_label'         => 'Visit Store',
			'home_store_button_url'           => 'https://americanalpineclub.myshopify.com/',
			'home_partners_kicker'            => 'Network',
			'home_partners_title'             => '',
			'home_partners_description'       => '',
			'home_involvement_cards'          => [
				[
					'title'        => 'Advocacy',
					'description'  => 'AAC works to protect public lands, defend climbing access, and support policy efforts that keep climbers connected to the places they love.',
					'button_label' => 'Learn More',
					'button_url'   => home_url('/advocacy/'),
					'image_url'    => 'https://images.unsplash.com/photo-1517821365201-7734f463fdbb?auto=format&fit=crop&w=1200&q=80',
				],
				[
					'title'        => 'Publications',
					'description'  => 'The American Alpine Journal, Accidents in North American Climbing, and other editorial work preserve hard-earned mountain knowledge and inspire the next generation.',
					'button_label' => 'Learn More',
					'button_url'   => home_url('/publications/'),
					'image_url'    => 'https://images.unsplash.com/photo-1464823063530-08f10ed1a2dd?auto=format&fit=crop&w=1200&q=80',
				],
				[
					'title'        => 'Lodging',
					'description'  => 'AAC lodging gives climbers welcoming, practical places to stay near iconic destinations, helping members spend more time in the mountains and with each other.',
					'button_label' => 'Learn More',
					'button_url'   => home_url('/lodging/'),
					'image_url'    => 'https://images.unsplash.com/photo-1511497584788-876760111969?auto=format&fit=crop&w=1200&q=80',
				],
				[
					'title'        => 'Grants',
					'description'  => 'AAC grant programs support ambitious climbing objectives, research, creative projects, and community efforts that move climbing forward.',
					'button_label' => 'Learn More',
					'button_url'   => home_url('/grants/'),
					'image_url'    => 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1200&q=80',
				],
				[
					'title'        => 'Climbing Grief Fund',
					'description'  => 'The Climbing Grief Fund helps members access therapeutic support after grief, loss, and trauma connected to climbing, alpinism, and ski mountaineering.',
					'button_label' => 'Learn More',
					'button_url'   => home_url('/grieffund/'),
					'image_url'    => 'https://images.unsplash.com/photo-1493246507139-91e8fad9978e?auto=format&fit=crop&w=1200&q=80',
				],
				[
					'title'        => 'Library',
					'description'  => 'AAC stewards one of the world’s richest climbing and mountaineering libraries, protecting stories, route history, and research for the long haul.',
					'button_label' => 'Learn More',
					'button_url'   => home_url('/library/'),
					'image_url'    => 'https://images.unsplash.com/photo-1507842217343-583bb7270b66?auto=format&fit=crop&w=1200&q=80',
				],
				[
					'title'        => 'Community',
					'description'  => 'Through chapters, events, shared spaces, and member programs, AAC helps climbers find belonging and build lasting connections around the sport.',
					'button_label' => 'Learn More',
					'button_url'   => home_url('/chapters/'),
					'image_url'    => 'https://images.unsplash.com/photo-1527631746610-bca00a040d60?auto=format&fit=crop&w=1200&q=80',
				],
			],
			'home_publication_cards'          => [
				[
					'title'        => 'AAC Publications',
					'description'  => 'Dive into long-form route reporting, accident analysis, and editorial archives that keep mountain knowledge alive and useful.',
					'button_label' => 'Explore Publications',
					'button_url'   => home_url('/publications/'),
					'image_url'    => 'https://images.unsplash.com/photo-1455390582262-044cdead277a?auto=format&fit=crop&w=1200&q=80',
				],
				[
					'title'        => 'Chapters & Community',
					'description'  => 'See how chapters, gatherings, and member programming help turn AAC support into real community on the ground.',
					'button_label' => 'Explore Community',
					'button_url'   => home_url('/chapters/'),
					'image_url'    => 'https://images.unsplash.com/photo-1527631746610-bca00a040d60?auto=format&fit=crop&w=1200&q=80',
				],
			],
			'home_partner_logos'              => [],
		],
		'design' => [
			'page_background'          => '#f7f1e3',
			'panel_background'         => '#fffaf1',
			'panel_border_color'       => '#d7cec0',
			'primary_action_background'=> '#8f1515',
			'primary_action_text'      => '#ffffff',
			'secondary_action_background' => '#f8c235',
			'secondary_action_text'    => '#000000',
			'nav_background'           => '#030000',
			'nav_text_color'           => '#ffffff',
			'nav_hover_text_color'     => '#f8c235',
			'nav_icon_color'           => '#f8c235',
			'nav_dropdown_background'  => 'rgba(11,9,8,0.95)',
			'nav_dropdown_text_color'  => '#f4efe7',
			'home_hero_overlay'        => 'linear-gradient(90deg, rgba(3,0,0,0.9) 0%, rgba(3,0,0,0.74) 38%, rgba(3,0,0,0.44) 64%, rgba(3,0,0,0.6) 100%)',
			'home_hero_video_url'      => 'https://player.vimeo.com/video/1166009381?h=c4c3248b38&background=1&autoplay=1&muted=1&loop=1&autopause=0&controls=0&title=0&byline=0&portrait=0',
			'home_intro_image_url'     => 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?auto=format&fit=crop&w=1600&q=80',
			'home_intro_accent_image_url' => 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1600&q=80',
			'home_store_image_url'     => 'https://images.unsplash.com/photo-1511497584788-876760111969?auto=format&fit=crop&w=1200&q=80',
			'sidebar_background_url'   => '',
			'sidebar_overlay_start'    => '0.18',
			'sidebar_overlay_end'      => '0.30',
		],
	];
}

function aac_portal_theme_get_portal_settings() {
	static $settings = null;

	if (null !== $settings) {
		return $settings;
	}

	$defaults = class_exists('AAC_Member_Portal_Admin')
		? AAC_Member_Portal_Admin::get_defaults()
		: aac_portal_theme_default_settings();

	$stored = get_option('aac_member_portal_settings', []);
	$settings = array_replace_recursive($defaults, is_array($stored) ? $stored : []);

	return $settings;
}

function aac_portal_theme_get_setting($section, $key, $fallback = '') {
	$settings = aac_portal_theme_get_portal_settings();
	return $settings[$section][$key] ?? $fallback;
}

function aac_portal_theme_get_primary_navigation() {
	$locations = get_nav_menu_locations();
	$menu_id = $locations['primary'] ?? 0;

	if ($menu_id) {
		$menu_items = wp_get_nav_menu_items($menu_id);
		if ($menu_items) {
			$indexed = [];
			$tree = [];

			foreach ($menu_items as $menu_item) {
				$indexed[$menu_item->ID] = [
					'id'       => $menu_item->ID,
					'label'    => $menu_item->title,
					'url'      => $menu_item->url,
					'children' => [],
				];
			}

			foreach ($menu_items as $menu_item) {
				if (!empty($menu_item->menu_item_parent) && isset($indexed[$menu_item->menu_item_parent])) {
					$indexed[$menu_item->menu_item_parent]['children'][] = &$indexed[$menu_item->ID];
				} else {
					$tree[] = &$indexed[$menu_item->ID];
				}
			}

			return $tree;
		}
	}

	$fallback = [
		[
			'label' => 'Get Involved',
			'url'   => aac_portal_theme_resolve_content_url('/get-involved/'),
			'children' => [
				['label' => 'Volunteer', 'url' => aac_portal_theme_resolve_content_url('/volunteer/')],
				['label' => 'Donate', 'url' => 'https://membership.americanalpineclub.org/donate'],
				['label' => 'Sign Up', 'url' => aac_portal_theme_portal_route('join')],
			],
		],
		[
			'label' => 'Membership',
			'url'   => aac_portal_theme_resolve_content_url('/membership/'),
			'children' => [
				['label' => 'Benefits', 'url' => aac_portal_theme_resolve_content_url('/benefits/')],
				['label' => 'Join', 'url' => aac_portal_theme_portal_route('join')],
				['label' => 'Renew', 'url' => 'https://membership.americanalpineclub.org/renew'],
			],
		],
		[
			'label' => 'Stories & News',
			'url'   => aac_portal_theme_resolve_content_url('/stories/'),
			'children' => [
				['label' => 'Articles & News', 'url' => aac_portal_theme_resolve_content_url('/stories/')],
				['label' => 'Featured Photographers', 'url' => home_url('/membership/#/photographers')],
				['label' => 'The Prescription', 'url' => aac_portal_theme_resolve_content_url('/prescription/')],
			],
		],
		[
			'label' => 'Lodging',
			'url'   => aac_portal_theme_resolve_content_url('/lodging/'),
			'children' => [
				['label' => 'Grand Teton', 'url' => aac_portal_theme_resolve_content_url('/grand-teton-climbers-ranch/')],
				['label' => 'The Gunks', 'url' => aac_portal_theme_resolve_content_url('/gunks-campground/')],
			],
		],
		[
			'label' => 'Publications',
			'url'   => aac_portal_theme_resolve_content_url('/publications/'),
			'children' => [
				['label' => 'AAJ', 'url' => aac_portal_theme_resolve_content_url('/publications/aaj/')],
				['label' => 'Accidents', 'url' => aac_portal_theme_resolve_content_url('/publications/accidents/')],
				['label' => 'Podcasts', 'url' => aac_portal_theme_resolve_content_url('/the-american-alpine-club-podcast/')],
			],
		],
		[
			'label' => 'Our Work',
			'url'   => aac_portal_theme_resolve_content_url('/our-work/'),
			'children' => [
				['label' => 'Advocacy', 'url' => aac_portal_theme_resolve_content_url('/advocacy/')],
				['label' => 'Grants', 'url' => aac_portal_theme_resolve_content_url('/grants/')],
				['label' => 'Library', 'url' => aac_portal_theme_resolve_content_url('/library/')],
			],
		],
	];

	return aac_portal_theme_filter_navigation_tree($fallback);
}

function aac_portal_theme_get_header_logo_url() {
	$custom_logo_id = get_theme_mod('custom_logo');
	if ($custom_logo_id) {
		$logo = wp_get_attachment_image_src($custom_logo_id, 'full');
		if (!empty($logo[0])) {
			return $logo[0];
		}
	}

	return 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/09/light-header-logo.svg';
}

function aac_portal_theme_get_header_actions() {
	if (is_user_logged_in()) {
		return [
			['label' => __('Account', 'aac-portal-theme'), 'url' => aac_portal_theme_portal_route('profile'), 'kind' => 'primary', 'icon' => 'user'],
			['label' => __('Donate', 'aac-portal-theme'), 'url' => 'https://membership.americanalpineclub.org/donate', 'kind' => 'secondary', 'icon' => 'dollar'],
			['label' => __('Rescue', 'aac-portal-theme'), 'url' => home_url('/rescue/'), 'kind' => 'utility', 'icon' => 'shield'],
		];
	}

	return [
		['label' => __('Sign In', 'aac-portal-theme'), 'url' => aac_portal_theme_portal_route('login'), 'kind' => 'primary', 'icon' => 'login'],
		['label' => __('Join', 'aac-portal-theme'), 'url' => aac_portal_theme_portal_route('join'), 'kind' => 'secondary', 'icon' => 'plus'],
		['label' => __('Donate', 'aac-portal-theme'), 'url' => 'https://membership.americanalpineclub.org/donate', 'kind' => 'secondary', 'icon' => 'dollar'],
		['label' => __('Rescue', 'aac-portal-theme'), 'url' => home_url('/rescue/'), 'kind' => 'utility', 'icon' => 'shield'],
	];
}

function aac_portal_theme_render_nav_items($items, $mobile = false) {
	if (empty($items) || !is_array($items)) {
		return;
	}

	foreach ($items as $item) {
		$label = esc_html($item['label'] ?? '');
		$url = esc_url($item['url'] ?? '#');
		$children = $item['children'] ?? [];

		if ($mobile) {
			if (!empty($children)) {
				echo '<details class="aac-site-nav__mobile-group">';
				echo '<summary class="aac-site-nav__mobile-summary">' . $label . '<span aria-hidden="true">+</span></summary>';
				echo '<div class="aac-site-nav__mobile-panel">';
				aac_portal_theme_render_nav_items($children, true);
				echo '</div>';
				echo '</details>';
			} else {
				echo '<a class="aac-site-nav__mobile-link" href="' . $url . '">' . $label . '</a>';
			}
			continue;
		}

		if (!empty($children)) {
			echo '<li class="aac-site-nav__item aac-site-nav__item--has-children">';
			echo '<a class="aac-site-nav__link" href="' . $url . '"><span>' . $label . '</span><span class="aac-site-nav__plus" aria-hidden="true">+</span></a>';
			echo '<div class="aac-site-nav__dropdown"><div class="aac-site-nav__dropdown-inner">';
			echo '<span class="aac-site-nav__dropdown-title">' . $label . '</span>';
			echo '<ul class="aac-site-nav__dropdown-list">';
			foreach ($children as $child) {
				echo '<li><a class="aac-site-nav__dropdown-link" href="' . esc_url($child['url'] ?? '#') . '">' . esc_html($child['label'] ?? '') . '</a></li>';
			}
			echo '</ul></div></div>';
			echo '</li>';
		} else {
			echo '<li class="aac-site-nav__item"><a class="aac-site-nav__link" href="' . $url . '"><span>' . $label . '</span></a></li>';
		}
	}
}

function aac_portal_theme_body_classes($classes) {
	$classes[] = 'aac-portal-theme';

	if (is_front_page()) {
		$classes[] = 'aac-portal-theme-front-page';
	}

	return $classes;
}
add_filter('body_class', 'aac_portal_theme_body_classes');
