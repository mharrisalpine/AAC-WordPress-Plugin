<?php

if (!defined('ABSPATH')) {
	exit;
}

class AAC_Member_Portal_Admin {
	const OPTION_KEY = 'aac_member_portal_settings';
	const MENU_SLUG = 'aac-member-portal-settings';
	const DISCOUNT_CARD_IMPORT_VERSION = '2026-04-09-discounts-table-v2';

	public function __construct() {
		add_action('init', [$this, 'maybe_seed_discount_cards'], 20);
		add_action('admin_menu', [$this, 'register_admin_page']);
		add_action('admin_init', [$this, 'register_settings']);
		add_action('admin_post_aac_member_portal_backfill_pmpro_fields', [$this, 'handle_backfill_pmpro_fields']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
	}

	public static function get_defaults() {
		// This settings tree is grand central station for admin-controlled portal
		// content. The admin UI edits it, and the React app reads the cleaned version.
		return [
			'content' => [
				'home_hero_kicker' => 'Home',
				'home_hero_title' => "United\nWe Climb.",
				'home_hero_description' => 'Explore AAC membership, rescue coverage, publications, grants, and community resources through the same member-focused experience that powers the portal.',
				'home_primary_cta_label' => 'Join',
				'home_primary_cta_url' => '/join',
				'home_secondary_cta_label' => 'Renew',
				'home_secondary_cta_url' => 'https://membership.americanalpineclub.org/renew',
				'home_tertiary_cta_label' => 'Learn More About Membership',
				'home_tertiary_cta_url' => 'https://americanalpine.wpenginepowered.com/learn-more/',
				'home_membership_chip_kicker' => 'Membership',
				'home_membership_chip_description' => 'Climbing advocacy, rescue coverage, publications, events, and member resources all live here.',
				'home_intro_kicker' => 'Since 1902',
				'home_intro_title' => 'Built for climbers.',
				'home_intro_description' => 'Founded in 1902, the American Alpine Club is a nonprofit that champions climbing knowledge, inspiration, advocacy, and community support for people who care deeply about the mountains.',
				'home_intro_secondary_description' => 'From rescue benefits and member publications to grants, events, and lodging, the Club keeps building practical resources that help climbers stay connected and better supported.',
				'home_intro_button_label' => 'Learn More About The AAC',
				'home_intro_button_url' => 'https://americanalpine.wpenginepowered.com/learn-more/',
				'home_involvement_kicker' => 'Explore',
				'home_involvement_title' => 'How To Get Involved',
				'home_involvement_button_label' => 'Join the Club',
				'home_involvement_button_url' => '/join',
				'home_publications_kicker' => 'Library',
				'home_publications_title' => 'Our Publications',
				'home_publications_button_label' => 'All Publications',
				'home_publications_button_url' => 'https://americanalpine.wpenginepowered.com/publications/',
				'home_store_kicker' => 'Store',
				'home_store_title' => 'Shop AAC Store',
				'home_store_description' => 'Browse featured AAC apparel, gear, and member merchandise from the Club store.',
				'home_store_button_label' => 'AAC Store',
				'home_store_button_url' => 'https://americanalpineclub.myshopify.com/',
				'home_partners_kicker' => 'Network',
				'home_partners_title' => 'Our Partners',
				'home_partners_description' => 'Partner brands and community collaborators help AAC extend member value across climbing gear, publications, events, and advocacy work.',
				'photographers_page_kicker' => 'Featured Photographers',
				'photographers_page_title' => 'The people behind the mountain images.',
				'photographers_page_description' => 'Highlight AAC photographers, their work, and the landscapes they keep bringing back to the community.',
				'grants_page_kicker' => 'AAC Grants',
				'grants_page_title' => 'Support ambitious climbing, research, and community projects.',
				'grants_page_description' => 'Review current AAC grant opportunities, choose the best fit, and submit your application from inside the member portal.',
				'home_involvement_cards' => self::get_default_home_involvement_cards(),
				'home_publication_cards' => self::get_default_home_publication_cards(),
				'home_partner_logos' => self::get_default_home_partner_logos(),
				'featured_photographers' => self::get_default_featured_photographers(),
				'grant_opportunities' => self::get_default_grant_opportunities(),
				'grant_form_fields' => self::get_default_grant_form_fields(),
				'account_settings_title' => 'Account Settings',
				'contact_recipient_email' => 'mharris@americanalpineclub.org',
				'profile_information_title' => 'Profile Information',
				'profile_information_description' => 'Primary contact and profile information used across the AAC portal. You may update your details and preferences in Account Settings.',
				'membership_snapshot_title' => 'Membership Snapshot',
				'membership_snapshot_description' => 'Live membership and benefit details coming from WordPress and Paid Memberships Pro.',
				'linked_accounts_title' => 'Linked Accounts',
				'linked_accounts_description' => 'Manage household members connected to this AAC membership and redeem invite codes for child accounts.',
				'update_profile_button_label' => 'Update Profile Information',
				'member_profile_card_sections' => self::get_default_member_profile_card_sections(),
				'member_profile_blocks' => [],
				'publications_title' => 'Publications',
				'publications_description' => 'Access the current AAC publication library and open each issue directly from the member portal.',
				'publications_locked_title' => 'Publications Unlock at Partner',
				'publications_locked_description' => 'The AAC publication library is available to Partner members and above. Upgrade your membership to open digital issues and manage your publication preferences.',
				'publications_upgrade_button_label' => 'Upgrade Membership',
				'publication_view_url_aaj' => 'https://aac-publications.s3.us-east-1.amazonaws.com/aaj/AAJ+2025.pdf',
				'publication_view_url_anac' => 'https://aac-publications.s3.us-east-1.amazonaws.com/ANAC+2025+Book_Digital_reduced.pdf',
				'publication_view_url_acj' => 'https://americanalpineclub.org/publications/',
				'publication_view_url_guidebook' => 'https://www.flipsnack.com/americanalpineclub/guidebook-xv/full-view.html',
				'join_hero_kicker' => 'Membership',
				'join_hero_title' => "United\nWe Climb.",
				'join_hero_description' => 'Join the American Alpine Club to support climbing advocacy, rescue coverage, community grants, publications, events, and a member experience built for the people who keep showing up for the mountains.',
				'join_primary_cta_label' => 'Join Now',
				'join_benefits_cta_label' => 'Member Benefits',
				'join_rescue_cta_label' => 'Rescue Benefits',
				'join_application_kicker' => 'Application',
				'join_application_title' => 'Choose your membership and complete checkout.',
				'join_application_description' => 'Select a membership level above, then complete the real AAC checkout form below.',
				'join_redeem_code_button_label' => 'Redeem Membership Code',
				'login_hero_kicker' => 'Member access',
				'login_hero_title' => "United\nWe Climb.",
				'login_hero_description' => 'Access your membership details, rescue information, discounts, store purchases, and account settings in one place.',
				'login_form_kicker' => 'Login',
				'login_form_title' => 'Welcome back.',
				'login_submit_label' => 'Sign in',
				'login_forgot_password_label' => 'Forgot your password?',
				'login_join_link_label' => 'Need to join?',
				'login_purchase_success_message' => 'Purchase successful. Please sign in to access your member profile.',
				'rescue_title' => 'Rescue Insurance',
				'rescue_coverage_title' => 'RedPoint Rescue Coverage',
				'rescue_emergency_title' => 'Emergency Contact',
				'rescue_claim_forms_title' => 'Claim Forms',
				'rescue_inactive_title' => 'Membership Inactive',
				'rescue_inactive_description' => 'Redpoint rescue and medical benefits are only available to active members.',
				'rescue_upgrade_title' => 'Unlock Rescue Benefits',
				'rescue_upgrade_description' => 'Upgrade your membership to unlock crucial rescue and medical coverage.',
				'rescue_manage_button_label' => 'Manage Membership',
				'rescue_levels' => self::get_default_rescue_levels(),
				'linked_accounts_page_title' => 'Linked Accounts',
				'linked_accounts_page_description' => 'Enter a family invite code to create or claim a connected household account. If the email already has an AAC account, we will link that existing account after verifying the password.',
				'linked_accounts_lookup_button_label' => 'Check Code',
				'linked_accounts_redeem_button_label' => 'Redeem Invite Code',
				'linked_accounts_success_message' => 'Invite redeemed successfully. Redirecting to your member profile...',
				'discounts_title' => 'Partner Discounts',
				'discounts_locked_title' => 'Discounts Locked',
				'discounts_locked_description' => 'Discounts are available to active members only. Renew or rejoin your membership to unlock partner offers.',
				'discounts_free_locked_description' => 'Free memberships include portal preview access and promo emails, but partner discounts unlock with a paid membership.',
				'discounts_upgrade_hint' => 'Upgrade from Free to Supporter or above whenever you are ready.',
				'discounts_button_label' => 'Visit Website',
				'discount_cards' => self::get_default_discount_cards(),
				'portal_preferences_title' => 'Portal Preferences',
				'portal_preferences_description' => 'Settings the portal is currently storing for your member record.',
				'quick_actions_title' => 'Quick Actions',
				'quick_actions_description' => 'Jump straight into the next member task.',
				'grant_applications_description' => 'Recent AAC grant submissions tied to your member record.',
			],
			'design' => [
				'sidebar_background_url' => '',
				'sidebar_overlay_start' => '0.18',
				'sidebar_overlay_end' => '0.30',
				'sidebar_button_background' => '#000000',
				'sidebar_button_hover_background' => '#111111',
				'sidebar_button_active_background' => '#000000',
				'sidebar_accent_color' => '#f8c235',
				'primary_action_background' => '#8f1515',
				'primary_action_text' => '#ffffff',
				'secondary_action_background' => '#f8c235',
				'secondary_action_text' => '#000000',
				'page_background' => '#f7f1e3',
				'panel_background' => '#ffffff',
				'panel_border_color' => '#d6d3d1',
				'hero_panel_background' => 'rgba(0,0,0,0.34)',
				'hero_panel_border_color' => 'rgba(255,255,255,0.14)',
				'hero_chip_background' => 'rgba(0,0,0,0.38)',
				'hero_chip_border_color' => 'rgba(255,255,255,0.18)',
				'login_form_background' => 'rgba(247,241,232,0.94)',
				'login_overlay' => 'linear-gradient(180deg,rgba(3,0,0,0.24),rgba(3,0,0,0.72)),radial-gradient(circle_at_top,rgba(248,194,53,0.12),transparent 24%)',
				'home_hero_overlay' => 'linear-gradient(90deg,rgba(3,0,0,0.88) 0%,rgba(3,0,0,0.72) 38%,rgba(3,0,0,0.4) 62%,rgba(3,0,0,0.58) 100%)',
				'home_hero_tint_overlay' => 'linear-gradient(to top, rgba(3,0,0,0.5), transparent, rgba(3,0,0,0.16))',
				'join_hero_overlay' => 'linear-gradient(90deg,rgba(3,0,0,0.88) 0%,rgba(3,0,0,0.72) 38%,rgba(3,0,0,0.4) 62%,rgba(3,0,0,0.58) 100%)',
				'join_hero_tint_overlay' => 'linear-gradient(to top, rgba(3,0,0,0.56), transparent, rgba(3,0,0,0.18))',
				'nav_background' => '#030000',
				'nav_text_color' => '#ffffff',
				'nav_hover_text_color' => '#f8c235',
				'nav_icon_color' => '#f8c235',
				'nav_dropdown_background' => 'rgba(11,9,8,0.95)',
				'nav_dropdown_text_color' => '#f4efe7',
				'join_hero_image_url' => 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Calder-Davey-Homepage-Fillers.jpg',
				'home_hero_video_url' => 'https://player.vimeo.com/video/1166009381?h=c4c3248b38&background=1&autoplay=1&muted=1&loop=1&autopause=0&controls=0&title=0&byline=0&portrait=0',
				'join_hero_video_url' => 'https://player.vimeo.com/video/1166009381?h=c4c3248b38&background=1&autoplay=1&muted=1&loop=1&autopause=0&controls=0&title=0&byline=0&portrait=0',
				'login_background_image_url' => '',
				'home_intro_image_url' => 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Calder-Davey-Homepage-Filler-2.jpg',
				'home_intro_accent_image_url' => 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Calder-Davey-Homepage-Filler-3.jpg',
				'home_store_image_url' => 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/AAC-Navy-Hat.jpg',
				'publication_tile_image_aaj' => '',
				'publication_tile_image_anac' => '',
				'publication_tile_image_acj' => '',
				'publication_tile_image_guidebook' => '',
			],
			'components' => [
				'section_titles' => [
					'your_portal' => 'Your portal',
					'explore' => 'Explore',
				],
				'home_sections' => self::get_default_home_sections(),
				'top_nav_items' => self::get_default_top_nav_items(),
				'sidebar_items' => self::get_default_sidebar_items(),
			],
		];
	}

	public static function get_default_home_sections() {
		return [
			'hero' => ['label' => 'Hero', 'order' => 10, 'visible' => 1],
			'intro' => ['label' => 'Intro', 'order' => 20, 'visible' => 1],
			'involvement' => ['label' => 'Get Involved', 'order' => 30, 'visible' => 1],
			'publications' => ['label' => 'Publications', 'order' => 40, 'visible' => 1],
			'store' => ['label' => 'Store', 'order' => 50, 'visible' => 1],
			'partners' => ['label' => 'Partners', 'order' => 60, 'visible' => 1],
		];
	}

	public static function get_default_home_involvement_cards() {
		return [
			[
				'title' => 'Join the Club',
				'description' => 'Membership supports AAC advocacy, rescue benefits, climbing knowledge, grants, and the wider climbing community.',
				'button_label' => 'Join Now',
				'button_url' => '/join',
				'image_url' => 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Calder-Davey-Homepage-Filler-4.jpg',
				'accent_style' => 'gold',
			],
			[
				'title' => 'Attend an Event',
				'description' => 'Connect with the AAC community through upcoming events, member gatherings, and shared learning in climbing spaces.',
				'button_label' => 'See Events',
				'button_url' => 'https://americanalpine.wpenginepowered.com/events/',
				'image_url' => '',
				'accent_style' => 'light',
			],
			[
				'title' => 'Stay at AAC Lodging',
				'description' => 'Explore climber lodging destinations and plan your next trip through AAC campgrounds and ranch properties.',
				'button_label' => 'Explore Lodging',
				'button_url' => 'https://americanalpine.wpenginepowered.com/lodging/',
				'image_url' => '',
				'accent_style' => 'sand',
			],
		];
	}

	public static function get_default_home_publication_cards() {
		return [
			[
				'title' => 'American Alpine Journal',
				'description' => 'Long-form reporting on major climbs around the world, presented in AAC’s flagship publication.',
				'button_label' => 'View Publication',
				'button_url' => 'https://americanalpine.wpenginepowered.com/publications/aaj/',
				'image_url' => 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/08/image-asset-95.jpeg',
				'accent_color' => '#f8c235',
			],
			[
				'title' => 'Accidents in North American Climbing',
				'description' => 'Annual accident analysis and takeaways that help climbers learn from the year’s most important incidents.',
				'button_label' => 'View Publication',
				'button_url' => 'https://americanalpine.wpenginepowered.com/publications/accidents/',
				'image_url' => 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/08/image-asset-28.jpeg',
				'accent_color' => '#b20710',
			],
		];
	}

	public static function get_default_home_partner_logos() {
		return [
			[
				'name' => 'American Alpine Club',
				'image_url' => 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/09/dark-header-logo.svg',
				'link_url' => 'https://americanalpine.wpenginepowered.com/',
			],
			[
				'name' => 'Backcountry',
				'image_url' => 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Filler-Logo-2.png',
				'link_url' => '',
			],
			[
				'name' => 'Black Diamond',
				'image_url' => 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Filler-Logo-1.png',
				'link_url' => '',
			],
		];
	}

	public static function get_default_featured_photographers() {
		return [
			[
				'name' => 'Avery Ridge',
				'short_bio' => 'Avery chases storm light, ridgelines, and the quiet moments that happen after a long approach. Their work leans into alpine scale without losing the human story inside it.',
				'website_url' => 'https://example.com/avery-ridge',
				'instagram_url' => 'https://instagram.com/averyridgephoto',
				'facebook_url' => '',
				'x_url' => '',
				'profile_image_url' => 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=900&q=80',
				'gallery_items' => [
					['image_url' => 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?auto=format&fit=crop&w=1200&q=80', 'caption' => 'Alpenglow over a granite ridge'],
					['image_url' => 'https://images.unsplash.com/photo-1519681393784-d120267933ba?auto=format&fit=crop&w=1200&q=80', 'caption' => 'Dawn clouds spilling over the pass'],
					['image_url' => 'https://images.unsplash.com/photo-1454496522488-7a8e488e8606?auto=format&fit=crop&w=1200&q=80', 'caption' => 'Snow blowing across a summit plateau'],
					['image_url' => 'https://images.unsplash.com/photo-1469474968028-56623f02e42e?auto=format&fit=crop&w=1200&q=80', 'caption' => 'Blue hour in the cirque'],
					['image_url' => 'https://images.unsplash.com/photo-1464820453369-31d2c0b651af?auto=format&fit=crop&w=1200&q=80', 'caption' => 'A high basin after fresh snow'],
					['image_url' => 'https://images.unsplash.com/photo-1441974231531-c6227db76b6e?auto=format&fit=crop&w=1200&q=80', 'caption' => 'Treeline giving way to rock'],
				],
			],
			[
				'name' => 'Morgan Vale',
				'short_bio' => 'Morgan focuses on climbing culture, big terrain, and the texture of expedition life. The frame is usually full of weather, movement, and one very committed pair of boots.',
				'website_url' => 'https://example.com/morgan-vale',
				'instagram_url' => 'https://instagram.com/morganvale.photo',
				'facebook_url' => '',
				'x_url' => '',
				'profile_image_url' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=900&q=80',
				'gallery_items' => [
					['image_url' => 'https://images.unsplash.com/photo-1501785888041-af3ef285b470?auto=format&fit=crop&w=1200&q=80', 'caption' => 'A valley opening into the range'],
					['image_url' => 'https://images.unsplash.com/photo-1482192596544-9eb780fc7f66?auto=format&fit=crop&w=1200&q=80', 'caption' => 'Switchbacks below dark granite walls'],
					['image_url' => 'https://images.unsplash.com/photo-1508261305436-4f659d0743eb?auto=format&fit=crop&w=1200&q=80', 'caption' => 'Cold light on a glacier edge'],
					['image_url' => 'https://images.unsplash.com/photo-1464823063530-08f10ed1a2dd?auto=format&fit=crop&w=1200&q=80', 'caption' => 'Jagged skyline at first light'],
					['image_url' => 'https://images.unsplash.com/photo-1458668383970-8ddd3927deed?auto=format&fit=crop&w=1200&q=80', 'caption' => 'Storm shadows racing across the basin'],
					['image_url' => 'https://images.unsplash.com/photo-1463694775559-eea25626346b?auto=format&fit=crop&w=1200&q=80', 'caption' => 'Camp below layered peaks'],
				],
			],
			[
				'name' => 'Sierra Ash',
				'short_bio' => 'Sierra works in cold morning color, long shadows, and the kind of trailhead starts that feel half-asleep until the range suddenly lights up. Their galleries tend to hold equal parts weather and wonder.',
				'website_url' => 'https://example.com/sierra-ash',
				'instagram_url' => 'https://instagram.com/sierraash.studio',
				'facebook_url' => '',
				'x_url' => '',
				'profile_image_url' => 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?auto=format&fit=crop&w=900&q=80',
				'gallery_items' => [
					['image_url' => 'https://images.unsplash.com/photo-1464820453369-31d2c0b651af?auto=format&fit=crop&w=1200&q=80', 'caption' => 'Sunbreak on a high alpine shelf'],
					['image_url' => 'https://images.unsplash.com/photo-1426604966848-d7adac402bff?auto=format&fit=crop&w=1200&q=80', 'caption' => 'Cloud bands lifting off the ridge'],
					['image_url' => 'https://images.unsplash.com/photo-1500534314209-a25ddb2bd429?auto=format&fit=crop&w=1200&q=80', 'caption' => 'Evening light over dark evergreens'],
					['image_url' => 'https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=1200&q=80', 'caption' => 'Still water below a sharp skyline'],
					['image_url' => 'https://images.unsplash.com/photo-1464823063530-08f10ed1a2dd?auto=format&fit=crop&w=1200&q=80', 'caption' => 'A serrated horizon at first light'],
					['image_url' => 'https://images.unsplash.com/photo-1465146344425-f00d5f5c8f07?auto=format&fit=crop&w=1200&q=80', 'caption' => 'Wildflowers leading into the mountain wall'],
				],
			],
			[
				'name' => 'Parker Stone',
				'short_bio' => 'Parker leans toward bold terrain and small human scale, with a style that makes cliffs, glaciers, and camp life all feel part of the same larger story. There is usually one tiny person somewhere in the frame doing something ambitious.',
				'website_url' => 'https://example.com/parker-stone',
				'instagram_url' => 'https://instagram.com/parkerstone.images',
				'facebook_url' => '',
				'x_url' => '',
				'profile_image_url' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=900&q=80',
				'gallery_items' => [
					['image_url' => 'https://images.unsplash.com/photo-1501785888041-af3ef285b470?auto=format&fit=crop&w=1200&q=80', 'caption' => 'A broad valley pulling toward the peaks'],
					['image_url' => 'https://images.unsplash.com/photo-1511497584788-876760111969?auto=format&fit=crop&w=1200&q=80', 'caption' => 'Tent light under an early alpine dusk'],
					['image_url' => 'https://images.unsplash.com/photo-1443890923422-7819ed4101c0?auto=format&fit=crop&w=1200&q=80', 'caption' => 'Cloud shadows over broken granite'],
					['image_url' => 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?auto=format&fit=crop&w=1200&q=80', 'caption' => 'Steep walls rising above the basin'],
					['image_url' => 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=1200&q=80', 'caption' => 'Blue shadows on glacier ice'],
					['image_url' => 'https://images.unsplash.com/photo-1504203700686-0f64f89a1f2d?auto=format&fit=crop&w=1200&q=80', 'caption' => 'Wind crossing a snowy ridgeline'],
				],
			],
			[
				'name' => 'Juniper North',
				'short_bio' => 'Juniper photographs mountain travel with a documentary eye, favoring clean compositions, quiet trail moments, and weather that looks one decision away from becoming a whole new plan.',
				'website_url' => 'https://example.com/juniper-north',
				'instagram_url' => 'https://instagram.com/junipernorth.photo',
				'facebook_url' => '',
				'x_url' => '',
				'profile_image_url' => 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&w=900&q=80',
				'gallery_items' => [
					['image_url' => 'https://images.unsplash.com/photo-1464820453369-31d2c0b651af?auto=format&fit=crop&w=1200&q=80', 'caption' => 'Morning haze over a glacial cirque'],
					['image_url' => 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1200&q=80', 'caption' => 'Rock bands glowing under soft light'],
					['image_url' => 'https://images.unsplash.com/photo-1463694775559-eea25626346b?auto=format&fit=crop&w=1200&q=80', 'caption' => 'Base camp beneath layered summits'],
					['image_url' => 'https://images.unsplash.com/photo-1454496522488-7a8e488e8606?auto=format&fit=crop&w=1200&q=80', 'caption' => 'Spindrift crossing a broad face'],
					['image_url' => 'https://images.unsplash.com/photo-1482192596544-9eb780fc7f66?auto=format&fit=crop&w=1200&q=80', 'caption' => 'An approach trail under huge stone walls'],
					['image_url' => 'https://images.unsplash.com/photo-1469474968028-56623f02e42e?auto=format&fit=crop&w=1200&q=80', 'caption' => 'Blue twilight settling into the range'],
				],
			],
		];
	}

	private static function merge_featured_photographers_with_defaults($photographers) {
		$photographers = is_array($photographers) ? array_values($photographers) : [];
		$defaults = self::get_default_featured_photographers();
		$existing_names = [];

		foreach ($photographers as $photographer) {
			$name = sanitize_text_field($photographer['name'] ?? '');
			if ($name !== '') {
				$existing_names[$name] = true;
			}
		}

		foreach ($defaults as $default_photographer) {
			$name = sanitize_text_field($default_photographer['name'] ?? '');
			if ($name === '' || isset($existing_names[$name])) {
				continue;
			}
			$photographers[] = $default_photographer;
			$existing_names[$name] = true;
		}

		return array_values($photographers);
	}

	public static function get_default_discount_cards() {
		$seed_path = __DIR__ . '/data/discount-cards-seed.json';
		if (!file_exists($seed_path)) {
			return [];
		}

		$seed_cards = json_decode((string) file_get_contents($seed_path), true);
		return is_array($seed_cards) ? $seed_cards : [];
	}

	public static function get_default_rescue_levels() {
		// Rescue benefits are editable in admin, but these defaults make sure a fresh
		// install still has a sane matrix instead of a blank screen and some panic.
		return [
			[
				'level_name' => 'Free',
				'rescue_amount' => 0,
				'medical_amount' => 0,
				'mortal_remains_amount' => 0,
				'rescue_reimbursement_process' => false,
			],
			[
				'level_name' => 'Supporter',
				'rescue_amount' => 0,
				'medical_amount' => 0,
				'mortal_remains_amount' => 0,
				'rescue_reimbursement_process' => false,
			],
			[
				'level_name' => 'Partner',
				'rescue_amount' => 7500,
				'medical_amount' => 5000,
				'mortal_remains_amount' => 15000,
				'rescue_reimbursement_process' => true,
			],
			[
				'level_name' => 'Leader',
				'rescue_amount' => 300000,
				'medical_amount' => 5000,
				'mortal_remains_amount' => 15000,
				'rescue_reimbursement_process' => true,
			],
			[
				'level_name' => 'Advocate',
				'rescue_amount' => 300000,
				'medical_amount' => 5000,
				'mortal_remains_amount' => 15000,
				'rescue_reimbursement_process' => true,
			],
			[
				'level_name' => 'GRF',
				'rescue_amount' => 300000,
				'medical_amount' => 5000,
				'mortal_remains_amount' => 15000,
				'rescue_reimbursement_process' => true,
			],
			[
				'level_name' => 'Lifetime',
				'rescue_amount' => 300000,
				'medical_amount' => 5000,
				'mortal_remains_amount' => 15000,
				'rescue_reimbursement_process' => true,
			],
		];
	}

	public static function get_default_grant_opportunities() {
		return [
			[
				'slug' => 'climbing-grief-grant',
				'name' => 'Climbing Grief Grant',
				'category' => 'Wellbeing',
				'award' => 'Up to $600',
				'fit' => 'Therapeutic support for members directly impacted by climbing, alpinism, or ski mountaineering grief and trauma.',
				'summary' => 'Support for therapy or professional programs that help members work through grief, loss, or trauma related to mountain sports.',
				'highlights' => [
					'Focused on grief, loss, and trauma recovery',
					'Designed for U.S. applicants with demonstrated need',
					'Best for applicants with a clear care plan and provider',
				],
				'source_url' => 'https://theamericanalpineclub.submittable.com/submit',
			],
			[
				'slug' => 'catalyst-adventure-grants-for-change',
				'name' => 'CATALYST: Adventure Grants for Change',
				'category' => 'Access',
				'award' => 'AAC grant support',
				'fit' => 'Applicants or teams facing barriers to climbing access who are advancing a specific, attainable U.S. objective.',
				'summary' => 'A grant aimed at expanding access to climbing by supporting underrepresented communities and closing opportunity gaps across climbing disciplines.',
				'highlights' => [
					'AAC members only',
					'Supports individuals or teams of 2 to 4',
					'Objective must be in the United States',
				],
				'source_url' => 'https://theamericanalpineclub.submittable.com/submit',
			],
			[
				'slug' => 'momentum-grant',
				'name' => 'Momentum Grant',
				'category' => 'Alpine Progression',
				'award' => 'AAC grant support',
				'fit' => 'Intermediate to advanced alpine climbers or ski-alpinists pursuing a meaningful step up in North America.',
				'summary' => 'Created to back climbers who are growing their mountain craft through ambitious alpine objectives, new lines, or significant repeats.',
				'highlights' => [
					'North America projects only',
					'Strong fit for ice, mixed, rock, and ski-alpinist objectives',
					'Best for applicants showing a clear progression in skill and ambition',
				],
				'source_url' => 'https://theamericanalpineclub.submittable.com/submit',
			],
			[
				'slug' => 'live-your-dream-2026',
				'name' => 'Live Your Dream',
				'category' => 'Exploration',
				'award' => 'AAC grant support',
				'fit' => 'Climbers with personally ambitious goals who want to grow their abilities and share exploration with their communities.',
				'summary' => 'A broad-based grant for climbers across ages, experience levels, and disciplines who are pursuing meaningful next-step adventures.',
				'highlights' => [
					'Open across climbing disciplines',
					'Encourages ambitious but personally relevant goals',
					'Community impact and storytelling matter',
				],
				'source_url' => 'https://theamericanalpineclub.submittable.com/submit',
			],
			[
				'slug' => 'research-grants',
				'name' => 'Research Grants',
				'category' => 'Science & Stewardship',
				'award' => 'AAC research funding',
				'fit' => 'Researchers studying climbing landscapes, ecosystems, land management, or community health connected to climbing.',
				'summary' => 'Supports scientific work that improves understanding of climbing environments and helps protect the landscapes and communities climbers depend on.',
				'highlights' => [
					'Strong fit for climbing-landscape research',
					'Projects should address timely issues affecting climbers or crags',
					'Useful for academic and field-based work',
				],
				'source_url' => 'https://theamericanalpineclub.submittable.com/submit',
			],
		];
	}

	public static function get_default_grant_form_fields() {
		return [
			[
				'field_key' => 'project_title',
				'label' => 'Project Title',
				'type' => 'text',
				'required' => 1,
				'placeholder' => 'Example: Wind River Granite Objectives',
				'help_text' => '',
				'options' => '',
			],
			[
				'field_key' => 'requested_amount',
				'label' => 'Amount Requested',
				'type' => 'number',
				'required' => 1,
				'placeholder' => '$2,500',
				'help_text' => '',
				'options' => '',
			],
			[
				'field_key' => 'objective_location',
				'label' => 'Objective / Project Location',
				'type' => 'text',
				'required' => 0,
				'placeholder' => 'Wind River Range, Wyoming',
				'help_text' => '',
				'options' => '',
			],
			[
				'field_key' => 'discipline',
				'label' => 'Discipline',
				'type' => 'text',
				'required' => 0,
				'placeholder' => 'Alpine, Ice, Research, Community program…',
				'help_text' => '',
				'options' => '',
			],
			[
				'field_key' => 'team_name',
				'label' => 'Team / Partners',
				'type' => 'text',
				'required' => 0,
				'placeholder' => 'List the climbers, researchers, or collaborators involved',
				'help_text' => '',
				'options' => '',
			],
			[
				'field_key' => 'summary',
				'label' => 'Project Summary',
				'type' => 'textarea',
				'required' => 1,
				'placeholder' => 'Describe the objective, why this grant fits, what the funding unlocks, and how the project serves the AAC community.',
				'help_text' => '',
				'options' => '',
			],
		];
	}

	public static function get_default_top_nav_items() {
		return [
			'membership' => ['label' => 'Membership', 'order' => 20, 'visible' => 1, 'children' => []],
			'stories_news' => ['label' => 'Stories & News', 'order' => 30, 'visible' => 1, 'children' => []],
			'lodging' => ['label' => 'Lodging', 'order' => 40, 'visible' => 1, 'children' => []],
			'publications' => ['label' => 'Publications', 'order' => 50, 'visible' => 1, 'children' => []],
			'our_work' => ['label' => 'Our Work', 'order' => 60, 'visible' => 1, 'children' => []],
		];
	}

	public static function get_default_member_profile_card_sections() {
		return [
			'membership_card' => ['label' => 'Membership Card', 'visible' => 1],
			'profile_information' => ['label' => 'Profile Information', 'visible' => 1],
			'membership_snapshot' => ['label' => 'Membership Snapshot', 'visible' => 1],
			'redpoint_benefits' => ['label' => 'Redpoint Benefits', 'visible' => 1],
			'linked_accounts' => ['label' => 'Linked Accounts', 'visible' => 1],
			'my_grants' => ['label' => 'My Grants', 'visible' => 1],
			'custom_blocks' => ['label' => 'Custom Member Profile Blocks', 'visible' => 1],
		];
	}

	public static function get_default_sidebar_items() {
		return [
			'member_profile' => ['label' => 'Member Profile', 'section' => 'your_portal', 'order' => 10, 'visible' => 1],
			'store' => ['label' => 'Store', 'section' => 'your_portal', 'order' => 20, 'visible' => 1],
			'rescue' => ['label' => 'Rescue', 'section' => 'your_portal', 'order' => 30, 'visible' => 1],
			'account' => ['label' => 'Profile Information', 'section' => 'your_portal', 'order' => 40, 'visible' => 1],
			'manage' => ['label' => 'Manage', 'section' => 'your_portal', 'order' => 50, 'visible' => 1],
			'publications' => ['label' => 'Publications', 'section' => 'your_portal', 'order' => 45, 'visible' => 1],
			'discounts' => ['label' => 'Discounts', 'section' => 'explore', 'order' => 10, 'visible' => 1],
			'podcasts' => ['label' => 'Podcasts', 'section' => 'explore', 'order' => 20, 'visible' => 1],
			'events' => ['label' => 'Events', 'section' => 'explore', 'order' => 30, 'visible' => 1],
			'lodging' => ['label' => 'Lodging', 'section' => 'explore', 'order' => 40, 'visible' => 1],
			'grants' => ['label' => 'Grants', 'section' => 'explore', 'order' => 50, 'visible' => 1],
			'contact' => ['label' => 'Contact Us', 'section' => 'explore', 'order' => 60, 'visible' => 1],
		];
	}

	public static function get_settings() {
		$stored = get_option(self::OPTION_KEY, []);
		$stored = is_array($stored) ? $stored : [];
		$settings = self::merge_with_defaults(self::get_defaults(), $stored);

		if (
			isset($settings['components']['sidebar_items']['account']['label']) &&
			in_array($settings['components']['sidebar_items']['account']['label'], ['Account', 'Member Details'], true)
		) {
			$settings['components']['sidebar_items']['account']['label'] = 'Profile Information';
		}

		$settings['content']['rescue_levels'] = isset($settings['content']['rescue_levels']) && is_array($settings['content']['rescue_levels']) && !empty($settings['content']['rescue_levels'])
			? array_values($settings['content']['rescue_levels'])
			: self::get_default_rescue_levels();
		$settings['content']['home_involvement_cards'] = isset($settings['content']['home_involvement_cards']) && is_array($settings['content']['home_involvement_cards']) && !empty($settings['content']['home_involvement_cards'])
			? array_values($settings['content']['home_involvement_cards'])
			: self::get_default_home_involvement_cards();
		$settings['content']['home_publication_cards'] = isset($settings['content']['home_publication_cards']) && is_array($settings['content']['home_publication_cards']) && !empty($settings['content']['home_publication_cards'])
			? array_values($settings['content']['home_publication_cards'])
			: self::get_default_home_publication_cards();
		$settings['content']['home_partner_logos'] = isset($settings['content']['home_partner_logos']) && is_array($settings['content']['home_partner_logos']) && !empty($settings['content']['home_partner_logos'])
			? array_values($settings['content']['home_partner_logos'])
			: self::get_default_home_partner_logos();
		$settings['content']['featured_photographers'] = isset($settings['content']['featured_photographers']) && is_array($settings['content']['featured_photographers']) && !empty($settings['content']['featured_photographers'])
			? self::merge_featured_photographers_with_defaults($settings['content']['featured_photographers'])
			: self::get_default_featured_photographers();
		$settings['content']['grant_opportunities'] = isset($settings['content']['grant_opportunities']) && is_array($settings['content']['grant_opportunities']) && !empty($settings['content']['grant_opportunities'])
			? array_values($settings['content']['grant_opportunities'])
			: self::get_default_grant_opportunities();
		$settings['content']['grant_form_fields'] = isset($settings['content']['grant_form_fields']) && is_array($settings['content']['grant_form_fields']) && !empty($settings['content']['grant_form_fields'])
			? array_values($settings['content']['grant_form_fields'])
			: self::get_default_grant_form_fields();

		return $settings;
	}

	public static function get_contact_recipient_email() {
		$settings = self::get_settings();
		$recipient_email = sanitize_email($settings['content']['contact_recipient_email'] ?? '');

		if ($recipient_email && is_email($recipient_email)) {
			return $recipient_email;
		}

		return sanitize_email(get_option('admin_email'));
	}

	public function maybe_seed_discount_cards() {
		if (get_option('aac_member_portal_discount_cards_seed_version') === self::DISCOUNT_CARD_IMPORT_VERSION) {
			return;
		}

		$seed_cards = self::get_default_discount_cards();
		if (empty($seed_cards)) {
			return;
		}

		$settings = self::get_settings();
		$existing_cards = isset($settings['content']['discount_cards']) && is_array($settings['content']['discount_cards'])
			? array_values($settings['content']['discount_cards'])
			: [];
		$existing_cards_by_brand = [];
		foreach ($existing_cards as $existing_card) {
			$existing_brand = sanitize_text_field($existing_card['brand'] ?? '');
			if ($existing_brand !== '') {
				$existing_cards_by_brand[$existing_brand] = $existing_card;
			}
		}

		$settings['content']['discount_cards'] = array_map(
			static function ($seed_card) use ($existing_cards_by_brand) {
				$brand = sanitize_text_field($seed_card['brand'] ?? '');
				if ($brand === '' || !isset($existing_cards_by_brand[$brand])) {
					return $seed_card;
				}

				$existing_card = $existing_cards_by_brand[$brand];
				if (!empty($existing_card['image_url'])) {
					$seed_card['image_url'] = esc_url_raw($existing_card['image_url']);
				}

				return $seed_card;
			},
			$seed_cards
		);

		update_option(self::OPTION_KEY, $settings, false);
		update_option('aac_member_portal_discount_cards_seed_version', self::DISCOUNT_CARD_IMPORT_VERSION, false);
	}

	public function register_admin_page() {
		add_menu_page(
			'AAC Portal Settings',
			'AAC Portal',
			'manage_options',
			self::MENU_SLUG,
			[$this, 'render_admin_page'],
			'dashicons-admin-generic',
			56
		);

		add_submenu_page(
			self::MENU_SLUG,
			'Member Portal Settings',
			'Member Portal Settings',
			'manage_options',
			self::MENU_SLUG,
			[$this, 'render_admin_page']
		);
	}

	public function register_settings() {
		register_setting(
			'aac_member_portal_settings_group',
			self::OPTION_KEY,
			[$this, 'sanitize_settings']
		);
	}

	public function enqueue_admin_assets($hook_suffix) {
		if ($hook_suffix !== 'toplevel_page_' . self::MENU_SLUG) {
			return;
		}

		wp_enqueue_media();
	}

	public function sanitize_settings($input) {
		$defaults = self::get_defaults();
		$current = self::get_settings();
		$input = is_array($input) ? $input : [];
		$settings = self::merge_with_defaults($defaults, $current);

		$content_input = isset($input['content']) && is_array($input['content']) ? $input['content'] : [];
		$text_fields = [
			'home_hero_kicker',
			'home_hero_title',
			'home_primary_cta_label',
			'home_secondary_cta_label',
			'home_tertiary_cta_label',
			'home_membership_chip_kicker',
			'home_intro_kicker',
			'home_intro_title',
			'home_intro_button_label',
			'home_involvement_kicker',
			'home_involvement_title',
			'home_involvement_button_label',
			'home_publications_kicker',
			'home_publications_title',
			'home_publications_button_label',
			'home_store_kicker',
			'home_store_title',
			'home_store_button_label',
			'home_partners_kicker',
			'home_partners_title',
			'photographers_page_kicker',
			'photographers_page_title',
			'grants_page_kicker',
			'grants_page_title',
			'account_settings_title',
			'profile_information_title',
			'membership_snapshot_title',
			'linked_accounts_title',
			'discounts_title',
			'discounts_locked_title',
			'discounts_button_label',
			'update_profile_button_label',
			'publications_title',
			'publications_locked_title',
			'publications_upgrade_button_label',
			'join_hero_kicker',
			'join_hero_title',
			'join_primary_cta_label',
			'join_benefits_cta_label',
			'join_rescue_cta_label',
			'join_application_kicker',
			'join_application_title',
			'join_redeem_code_button_label',
			'login_hero_kicker',
			'login_hero_title',
			'login_form_kicker',
			'login_form_title',
			'login_submit_label',
			'login_forgot_password_label',
			'login_join_link_label',
			'rescue_title',
			'rescue_coverage_title',
			'rescue_emergency_title',
			'rescue_claim_forms_title',
			'rescue_inactive_title',
			'rescue_upgrade_title',
			'rescue_manage_button_label',
			'linked_accounts_page_title',
			'linked_accounts_lookup_button_label',
			'linked_accounts_redeem_button_label',
			'portal_preferences_title',
			'quick_actions_title',
		];
		foreach ($text_fields as $field) {
			if (array_key_exists($field, $content_input)) {
				$settings['content'][$field] = sanitize_text_field($content_input[$field]);
			}
		}

		$textarea_fields = [
			'home_hero_description',
			'home_membership_chip_description',
			'home_intro_description',
			'home_intro_secondary_description',
			'home_store_description',
			'home_partners_description',
			'photographers_page_description',
			'grants_page_description',
			'profile_information_description',
			'membership_snapshot_description',
			'linked_accounts_description',
			'discounts_locked_description',
			'discounts_free_locked_description',
			'discounts_upgrade_hint',
			'publications_description',
			'publications_locked_description',
			'join_hero_description',
			'join_application_description',
			'login_hero_description',
			'login_purchase_success_message',
			'rescue_inactive_description',
			'rescue_upgrade_description',
			'linked_accounts_page_description',
			'linked_accounts_success_message',
			'portal_preferences_description',
			'quick_actions_description',
			'grant_applications_description',
		];
		foreach ($textarea_fields as $field) {
			if (array_key_exists($field, $content_input)) {
				$settings['content'][$field] = sanitize_textarea_field($content_input[$field]);
			}
		}

		if (array_key_exists('contact_recipient_email', $content_input)) {
			$contact_email = sanitize_email($content_input['contact_recipient_email']);
			$settings['content']['contact_recipient_email'] = $contact_email && is_email($contact_email)
				? $contact_email
				: $defaults['content']['contact_recipient_email'];
		}

		$url_fields = [
			'home_primary_cta_url',
			'home_secondary_cta_url',
			'home_tertiary_cta_url',
			'home_intro_button_url',
			'home_involvement_button_url',
			'home_publications_button_url',
			'home_store_button_url',
			'publication_view_url_aaj',
			'publication_view_url_anac',
			'publication_view_url_acj',
			'publication_view_url_guidebook',
		];
		foreach ($url_fields as $field) {
			if (array_key_exists($field, $content_input)) {
				$settings['content'][$field] = esc_url_raw($content_input[$field]);
			}
		}

		// Repeater fields are the feral cousins of simple text fields. They come in
		// as list arrays, so we sanitize them in their own lane before saving.
		if (isset($content_input['discount_cards']) && is_array($content_input['discount_cards'])) {
			$settings['content']['discount_cards'] = $this->sanitize_discount_cards($content_input['discount_cards']);
		}

		if (isset($content_input['rescue_levels']) && is_array($content_input['rescue_levels'])) {
			$settings['content']['rescue_levels'] = $this->sanitize_rescue_levels($content_input['rescue_levels']);
		}
		if (isset($content_input['home_involvement_cards']) && is_array($content_input['home_involvement_cards'])) {
			$settings['content']['home_involvement_cards'] = $this->sanitize_home_involvement_cards($content_input['home_involvement_cards']);
		}
		if (isset($content_input['home_publication_cards']) && is_array($content_input['home_publication_cards'])) {
			$settings['content']['home_publication_cards'] = $this->sanitize_home_publication_cards($content_input['home_publication_cards']);
		}
		if (isset($content_input['home_partner_logos']) && is_array($content_input['home_partner_logos'])) {
			$settings['content']['home_partner_logos'] = $this->sanitize_home_partner_logos($content_input['home_partner_logos']);
		}
		if (isset($content_input['featured_photographers']) && is_array($content_input['featured_photographers'])) {
			$settings['content']['featured_photographers'] = $this->sanitize_featured_photographers($content_input['featured_photographers']);
		}
		if (isset($content_input['grant_opportunities']) && is_array($content_input['grant_opportunities'])) {
			$settings['content']['grant_opportunities'] = $this->sanitize_grant_opportunities($content_input['grant_opportunities']);
		}
		if (isset($content_input['grant_form_fields']) && is_array($content_input['grant_form_fields'])) {
			$settings['content']['grant_form_fields'] = $this->sanitize_grant_form_fields($content_input['grant_form_fields']);
		}
		if (isset($content_input['member_profile_blocks']) && is_array($content_input['member_profile_blocks'])) {
			$settings['content']['member_profile_blocks'] = $this->sanitize_member_profile_blocks($content_input['member_profile_blocks']);
		}
		if (isset($content_input['member_profile_card_sections']) && is_array($content_input['member_profile_card_sections'])) {
			$settings['content']['member_profile_card_sections'] = $this->sanitize_member_profile_card_sections($content_input['member_profile_card_sections']);
		}

		$design_input = isset($input['design']) && is_array($input['design']) ? $input['design'] : [];
		$design_url_fields = [
			'sidebar_background_url',
			'join_hero_image_url',
			'home_hero_video_url',
			'join_hero_video_url',
			'login_background_image_url',
			'home_intro_image_url',
			'home_intro_accent_image_url',
			'home_store_image_url',
			'publication_tile_image_aaj',
			'publication_tile_image_anac',
			'publication_tile_image_acj',
			'publication_tile_image_guidebook',
		];
		foreach ($design_url_fields as $field) {
			if (array_key_exists($field, $design_input)) {
				$settings['design'][$field] = esc_url_raw($design_input[$field]);
			}
		}

		$color_fields = [
			'sidebar_button_background',
			'sidebar_button_hover_background',
			'sidebar_button_active_background',
			'sidebar_accent_color',
			'primary_action_background',
			'primary_action_text',
			'secondary_action_background',
			'secondary_action_text',
		];
		foreach ($color_fields as $field) {
			if (array_key_exists($field, $design_input)) {
				$settings['design'][$field] = $this->sanitize_hex_color_or_default($design_input[$field], $defaults['design'][$field]);
			}
		}

		$token_fields = [
			'page_background',
			'panel_background',
			'panel_border_color',
			'hero_panel_background',
			'hero_panel_border_color',
			'hero_chip_background',
			'hero_chip_border_color',
			'login_form_background',
			'login_overlay',
			'home_hero_overlay',
			'home_hero_tint_overlay',
			'join_hero_overlay',
			'join_hero_tint_overlay',
			'nav_background',
			'nav_text_color',
			'nav_hover_text_color',
			'nav_icon_color',
			'nav_dropdown_background',
			'nav_dropdown_text_color',
		];
		foreach ($token_fields as $field) {
			if (array_key_exists($field, $design_input)) {
				$settings['design'][$field] = sanitize_text_field($design_input[$field]);
			}
		}

		if (array_key_exists('sidebar_overlay_start', $design_input)) {
			$settings['design']['sidebar_overlay_start'] = $this->sanitize_opacity($design_input['sidebar_overlay_start']);
		}
		if (array_key_exists('sidebar_overlay_end', $design_input)) {
			$settings['design']['sidebar_overlay_end'] = $this->sanitize_opacity($design_input['sidebar_overlay_end']);
		}

		$components_input = isset($input['components']) && is_array($input['components']) ? $input['components'] : [];
		$section_titles = isset($components_input['section_titles']) && is_array($components_input['section_titles']) ? $components_input['section_titles'] : null;
		if ($section_titles !== null) {
			foreach ($defaults['components']['section_titles'] as $section_id => $default_title) {
				if (array_key_exists($section_id, $section_titles)) {
					$settings['components']['section_titles'][$section_id] = sanitize_text_field($section_titles[$section_id]);
				}
			}
		}

		$top_nav_items = isset($components_input['top_nav_items']) && is_array($components_input['top_nav_items']) ? $components_input['top_nav_items'] : null;
		if ($top_nav_items !== null) {
			foreach ($defaults['components']['top_nav_items'] as $item_id => $item_defaults) {
				$item_input = isset($top_nav_items[$item_id]) && is_array($top_nav_items[$item_id]) ? $top_nav_items[$item_id] : [];
				$settings['components']['top_nav_items'][$item_id] = [
					'label' => sanitize_text_field($item_input['label'] ?? $settings['components']['top_nav_items'][$item_id]['label']),
					'order' => isset($item_input['order']) ? (int) $item_input['order'] : (int) $settings['components']['top_nav_items'][$item_id]['order'],
					'visible' => empty($item_input['visible']) ? 0 : 1,
					'children' => $this->sanitize_top_nav_children(isset($item_input['children_text']) ? (string) $item_input['children_text'] : (isset($item_input['children']) && is_array($item_input['children']) ? $item_input['children'] : [])),
				];
			}
		}

		$sidebar_items = isset($components_input['sidebar_items']) && is_array($components_input['sidebar_items']) ? $components_input['sidebar_items'] : null;
		if ($sidebar_items !== null) {
			foreach ($defaults['components']['sidebar_items'] as $item_id => $item_defaults) {
				$item_input = isset($sidebar_items[$item_id]) && is_array($sidebar_items[$item_id]) ? $sidebar_items[$item_id] : [];
				$section = sanitize_key($item_input['section'] ?? $settings['components']['sidebar_items'][$item_id]['section']);
				if (!isset($defaults['components']['section_titles'][$section])) {
					$section = $item_defaults['section'];
				}

				$settings['components']['sidebar_items'][$item_id] = [
					'label' => sanitize_text_field($item_input['label'] ?? $settings['components']['sidebar_items'][$item_id]['label']),
					'section' => $section,
					'order' => isset($item_input['order']) ? (int) $item_input['order'] : (int) $settings['components']['sidebar_items'][$item_id]['order'],
					'visible' => empty($item_input['visible']) ? 0 : 1,
				];
			}
		}

		$home_sections = isset($components_input['home_sections']) && is_array($components_input['home_sections']) ? $components_input['home_sections'] : null;
		if ($home_sections !== null) {
			foreach ($defaults['components']['home_sections'] as $section_id => $section_defaults) {
				$section_input = isset($home_sections[$section_id]) && is_array($home_sections[$section_id]) ? $home_sections[$section_id] : [];
				$settings['components']['home_sections'][$section_id] = [
					'label' => sanitize_text_field($section_input['label'] ?? $settings['components']['home_sections'][$section_id]['label']),
					'order' => isset($section_input['order']) ? (int) $section_input['order'] : (int) $settings['components']['home_sections'][$section_id]['order'],
					'visible' => empty($section_input['visible']) ? 0 : 1,
				];
			}
		}

		return self::merge_with_defaults($defaults, $settings);
	}

	public function render_admin_page() {
		if (!current_user_can('manage_options')) {
			return;
		}

		$settings = self::get_settings();
		$tabs = [
			'global' => 'Global',
			'home' => 'Home',
			'photographers' => 'Photographers',
			'grants' => 'Grants',
			'experience' => 'Experience',
			'discounts' => 'Discounts',
			'publications' => 'Publications',
			'rescue' => 'Rescue',
			'linked_accounts' => 'Linked Accounts',
		];
		$tab_aliases = [
			'join' => 'experience',
			'login' => 'experience',
			'profile' => 'experience',
			'design' => 'experience',
			'navigation' => 'experience',
			'layout' => 'experience',
		];
		$tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'global';
		if (isset($tab_aliases[$tab])) {
			$tab = $tab_aliases[$tab];
		}
		if (!isset($tabs[$tab])) {
			$tab = 'global';
		}
		?>
		<div class="wrap">
			<h1>AAC Portal Settings</h1>
			<p>Manage member portal copy, page images, colors, and navigation. Settings are organized by portal page so content updates are easier to manage over time.</p>

			<nav class="nav-tab-wrapper" style="margin-bottom:20px;">
				<?php foreach ($tabs as $tab_key => $tab_label) : ?>
					<a class="nav-tab <?php echo $tab === $tab_key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(add_query_arg(['page' => self::MENU_SLUG, 'tab' => $tab_key], admin_url('admin.php'))); ?>">
						<?php echo esc_html($tab_label); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<form method="post" action="options.php">
				<?php settings_fields('aac_member_portal_settings_group'); ?>
				<div style="display:grid;gap:24px;max-width:1100px;">
					<?php
					switch ($tab) {
						case 'home':
							$this->render_home_tab($settings);
							break;
						case 'photographers':
							$this->render_photographers_tab($settings);
							break;
						case 'grants':
							$this->render_grants_tab($settings);
							break;
						case 'discounts':
							$this->render_discounts_tab($settings);
							break;
						case 'publications':
							$this->render_publications_tab($settings);
							break;
						case 'rescue':
							$this->render_rescue_tab($settings);
							break;
						case 'linked_accounts':
							$this->render_linked_accounts_tab($settings);
							break;
						case 'experience':
							$this->render_experience_tab($settings);
							break;
						case 'global':
						default:
							$this->render_global_tab($settings);
							break;
					}
					?>
				</div>
				<?php submit_button('Save Portal Settings'); ?>
			</form>
			<?php $this->render_shared_admin_scripts(); ?>
		</div>
		<?php
	}

	private function render_experience_tab($settings) {
		$this->render_join_tab($settings);
		$this->render_login_tab($settings);
		$this->render_profile_tab($settings);
		$this->render_design_tab($settings);
		$this->render_navigation_tab($settings);
		$this->render_layout_tab($settings);
	}

	private function get_pmpro_backfill_notice() {
		if (empty($_GET['aac_pmpro_backfill']) || wp_unslash($_GET['aac_pmpro_backfill']) !== '1') {
			return '';
		}

		$candidate_count = isset($_GET['aac_pmpro_backfill_candidates']) ? (int) wp_unslash($_GET['aac_pmpro_backfill_candidates']) : 0;
		$synced_count = isset($_GET['aac_pmpro_backfill_synced']) ? (int) wp_unslash($_GET['aac_pmpro_backfill_synced']) : 0;

		return sprintf(
			'PMPro backfill complete. Synced %1$d of %2$d candidate member records into PMPro fields and PMPro User Fields.',
			$synced_count,
			$candidate_count
		);
	}

	private function render_global_tab($settings) {
		$this->open_panel('Global Portal Content', 'Settings used across the member portal regardless of page.');
		?>
		<table class="form-table" role="presentation"><tbody>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][account_settings_title]', 'Account Settings title', $settings['content']['account_settings_title']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][contact_recipient_email]', 'Contact form recipient email', $settings['content']['contact_recipient_email'], 'email', 'Messages from the member app Contact form will be sent to this address.'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][portal_preferences_title]', 'Portal Preferences title', $settings['content']['portal_preferences_title']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][portal_preferences_description]', 'Portal Preferences description', $settings['content']['portal_preferences_description']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][quick_actions_title]', 'Quick Actions title', $settings['content']['quick_actions_title']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][quick_actions_description]', 'Quick Actions description', $settings['content']['quick_actions_description']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][grant_applications_description]', 'Grant Applications description', $settings['content']['grant_applications_description']); ?>
		</tbody></table>
		<?php
		$this->close_panel();
	}

	private function render_home_tab($settings) {
		$this->open_panel('Home Page', 'Control the public homepage hero, supporting sections, logos, and card content from WordPress.');
		$involvement_cards = isset($settings['content']['home_involvement_cards']) && is_array($settings['content']['home_involvement_cards'])
			? array_values($settings['content']['home_involvement_cards'])
			: self::get_default_home_involvement_cards();
		$publication_cards = isset($settings['content']['home_publication_cards']) && is_array($settings['content']['home_publication_cards'])
			? array_values($settings['content']['home_publication_cards'])
			: self::get_default_home_publication_cards();
		$partner_logos = isset($settings['content']['home_partner_logos']) && is_array($settings['content']['home_partner_logos'])
			? array_values($settings['content']['home_partner_logos'])
			: self::get_default_home_partner_logos();
		?>
		<table class="form-table" role="presentation"><tbody>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_hero_kicker]', 'Hero kicker', $settings['content']['home_hero_kicker']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_hero_title]', 'Hero title', $settings['content']['home_hero_title']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][home_hero_description]', 'Hero description', $settings['content']['home_hero_description']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_primary_cta_label]', 'Primary CTA label', $settings['content']['home_primary_cta_label']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_primary_cta_url]', 'Primary CTA URL', $settings['content']['home_primary_cta_url'], 'url'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_secondary_cta_label]', 'Secondary CTA label', $settings['content']['home_secondary_cta_label']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_secondary_cta_url]', 'Secondary CTA URL', $settings['content']['home_secondary_cta_url'], 'url'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_tertiary_cta_label]', 'Tertiary CTA label', $settings['content']['home_tertiary_cta_label']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_tertiary_cta_url]', 'Tertiary CTA URL', $settings['content']['home_tertiary_cta_url'], 'url'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_membership_chip_kicker]', 'Hero supporting kicker', $settings['content']['home_membership_chip_kicker']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][home_membership_chip_description]', 'Hero supporting description', $settings['content']['home_membership_chip_description']); ?>
			<?php $this->render_media_row(self::OPTION_KEY . '[design][home_hero_video_url]', 'Hero video URL', $settings['design']['home_hero_video_url'], 'Paste a Vimeo background URL or another embeddable media URL.'); ?>
			<?php $this->render_media_row(self::OPTION_KEY . '[design][home_intro_image_url]', 'Intro image URL', $settings['design']['home_intro_image_url']); ?>
			<?php $this->render_media_row(self::OPTION_KEY . '[design][home_intro_accent_image_url]', 'Intro accent image URL', $settings['design']['home_intro_accent_image_url']); ?>
			<?php $this->render_media_row(self::OPTION_KEY . '[design][home_store_image_url]', 'Store image URL', $settings['design']['home_store_image_url']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_intro_kicker]', 'Intro kicker', $settings['content']['home_intro_kicker']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_intro_title]', 'Intro title', $settings['content']['home_intro_title']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][home_intro_description]', 'Intro description', $settings['content']['home_intro_description']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][home_intro_secondary_description]', 'Intro secondary description', $settings['content']['home_intro_secondary_description']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_intro_button_label]', 'Intro button label', $settings['content']['home_intro_button_label']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_intro_button_url]', 'Intro button URL', $settings['content']['home_intro_button_url'], 'url'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_involvement_kicker]', 'Get involved kicker', $settings['content']['home_involvement_kicker']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_involvement_title]', 'Get involved title', $settings['content']['home_involvement_title']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_involvement_button_label]', 'Get involved button label', $settings['content']['home_involvement_button_label']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_involvement_button_url]', 'Get involved button URL', $settings['content']['home_involvement_button_url'], 'url'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_publications_kicker]', 'Publications kicker', $settings['content']['home_publications_kicker']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_publications_title]', 'Publications title', $settings['content']['home_publications_title']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_publications_button_label]', 'Publications button label', $settings['content']['home_publications_button_label']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_publications_button_url]', 'Publications button URL', $settings['content']['home_publications_button_url'], 'url'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_store_kicker]', 'Store kicker', $settings['content']['home_store_kicker']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_store_title]', 'Store title', $settings['content']['home_store_title']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][home_store_description]', 'Store description', $settings['content']['home_store_description']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_store_button_label]', 'Store button label', $settings['content']['home_store_button_label']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_store_button_url]', 'Store button URL', $settings['content']['home_store_button_url'], 'url'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_partners_kicker]', 'Partners kicker', $settings['content']['home_partners_kicker']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_partners_title]', 'Partners title', $settings['content']['home_partners_title']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][home_partners_description]', 'Partners description', $settings['content']['home_partners_description']); ?>
		</tbody></table>

		<h3 style="margin:24px 0 12px;">Get Involved Cards</h3>
		<div id="aac-home-involvement-cards" class="aac-home-repeater-list">
			<?php foreach ($involvement_cards as $index => $card) : ?>
				<?php $this->render_home_involvement_card_editor($index, $card); ?>
			<?php endforeach; ?>
		</div>
		<p style="margin-top:16px;"><button type="button" class="button button-secondary" id="aac-add-home-involvement-card">Add Involvement Card</button></p>

		<h3 style="margin:28px 0 12px;">Publication Cards</h3>
		<div id="aac-home-publication-cards" class="aac-home-repeater-list">
			<?php foreach ($publication_cards as $index => $card) : ?>
				<?php $this->render_home_publication_card_editor($index, $card); ?>
			<?php endforeach; ?>
		</div>
		<p style="margin-top:16px;"><button type="button" class="button button-secondary" id="aac-add-home-publication-card">Add Publication Card</button></p>

		<h3 style="margin:28px 0 12px;">Partner Logos</h3>
		<div id="aac-home-partner-logos" class="aac-home-repeater-list">
			<?php foreach ($partner_logos as $index => $logo) : ?>
				<?php $this->render_home_partner_logo_editor($index, $logo); ?>
			<?php endforeach; ?>
		</div>
		<p style="margin-top:16px;"><button type="button" class="button button-secondary" id="aac-add-home-partner-logo">Add Partner Logo</button></p>
		<?php
		$this->render_home_repeater_templates();
		$this->close_panel();
	}

	private function render_join_tab($settings) {
		$this->open_panel('Join Page', 'Edit the public AAC membership signup experience.');
		?>
		<table class="form-table" role="presentation"><tbody>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][join_hero_kicker]', 'Hero kicker', $settings['content']['join_hero_kicker']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][join_hero_title]', 'Hero title', $settings['content']['join_hero_title']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][join_hero_description]', 'Hero description', $settings['content']['join_hero_description']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][join_primary_cta_label]', 'Primary CTA label', $settings['content']['join_primary_cta_label']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][join_benefits_cta_label]', 'Benefits CTA label', $settings['content']['join_benefits_cta_label']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][join_rescue_cta_label]', 'Rescue CTA label', $settings['content']['join_rescue_cta_label']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][join_application_kicker]', 'Application kicker', $settings['content']['join_application_kicker']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][join_application_title]', 'Application title', $settings['content']['join_application_title']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][join_application_description]', 'Application description', $settings['content']['join_application_description']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][join_redeem_code_button_label]', 'Redeem code button label', $settings['content']['join_redeem_code_button_label']); ?>
			<?php $this->render_media_row(self::OPTION_KEY . '[design][join_hero_image_url]', 'Hero image URL', $settings['design']['join_hero_image_url']); ?>
			<?php $this->render_media_row(self::OPTION_KEY . '[design][join_hero_video_url]', 'Hero video URL', $settings['design']['join_hero_video_url'], 'Use a Vimeo background URL when you want motion instead of a still image.'); ?>
		</tbody></table>
		<?php
		$this->close_panel();
	}

	private function render_photographers_tab($settings) {
		$this->open_panel('Featured Photographers', 'Manage photographer profiles, social links, and six-image galleries for the featured photographers page.');
		$photographers = isset($settings['content']['featured_photographers']) && is_array($settings['content']['featured_photographers'])
			? array_values($settings['content']['featured_photographers'])
			: self::get_default_featured_photographers();
		?>
		<table class="form-table" role="presentation"><tbody>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][photographers_page_kicker]', 'Page kicker', $settings['content']['photographers_page_kicker']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][photographers_page_title]', 'Page title', $settings['content']['photographers_page_title']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][photographers_page_description]', 'Page description', $settings['content']['photographers_page_description']); ?>
		</tbody></table>

		<h3 style="margin:24px 0 12px;">Photographer Blocks</h3>
		<p class="description" style="margin-bottom:12px;">Each photographer includes a profile photo, short bio, website/social links, and a gallery shown in grid order. Use the arrow buttons to rearrange both photographers and gallery images.</p>
		<div id="aac-featured-photographers" class="aac-photographer-admin__list">
			<?php foreach ($photographers as $index => $photographer) : ?>
				<?php $this->render_featured_photographer_editor($index, $photographer); ?>
			<?php endforeach; ?>
		</div>
		<p style="margin-top:16px;"><button type="button" class="button button-secondary" id="aac-add-featured-photographer">Add Photographer</button></p>
		<?php
		$this->render_featured_photographer_templates();
		$this->close_panel();
	}

	private function render_grants_tab($settings) {
		$this->open_panel('Grants Builder', 'Control the grant opportunities and the member-facing application fields from one admin screen, then feed that same shape into the grants review workflow.');
		$grant_opportunities = isset($settings['content']['grant_opportunities']) && is_array($settings['content']['grant_opportunities'])
			? array_values($settings['content']['grant_opportunities'])
			: self::get_default_grant_opportunities();
		$grant_form_fields = isset($settings['content']['grant_form_fields']) && is_array($settings['content']['grant_form_fields'])
			? array_values($settings['content']['grant_form_fields'])
			: self::get_default_grant_form_fields();
		?>
		<table class="form-table" role="presentation"><tbody>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][grants_page_kicker]', 'Page kicker', $settings['content']['grants_page_kicker']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][grants_page_title]', 'Page title', $settings['content']['grants_page_title']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][grants_page_description]', 'Page description', $settings['content']['grants_page_description']); ?>
		</tbody></table>

		<h3 style="margin:24px 0 12px;">Grant Opportunities</h3>
		<p class="description" style="margin-bottom:12px;">These cards drive the opportunities selector on the member-facing grants page. Add or remove programs here without cracking open the frontend.</p>
		<div id="aac-grant-opportunities" class="aac-home-repeater-list">
			<?php foreach ($grant_opportunities as $index => $opportunity) : ?>
				<?php $this->render_grant_opportunity_editor($index, $opportunity); ?>
			<?php endforeach; ?>
		</div>
		<p style="margin-top:16px;"><button type="button" class="button button-secondary" id="aac-add-grant-opportunity">Add Grant Opportunity</button></p>

		<h3 style="margin:28px 0 12px;">Application Fields</h3>
		<p class="description" style="margin-bottom:12px;">These fields drive the member application form and are forwarded into the grants approval plugin. Use stable field keys like <code>project_title</code> and <code>requested_amount</code> for the fields that should populate reviewer summaries.</p>
		<div id="aac-grant-form-fields" class="aac-home-repeater-list">
			<?php foreach ($grant_form_fields as $index => $field) : ?>
				<?php $this->render_grant_form_field_editor($index, $field); ?>
			<?php endforeach; ?>
		</div>
		<p style="margin-top:16px;"><button type="button" class="button button-secondary" id="aac-add-grant-form-field">Add Grant Field</button></p>
		<?php
		$this->render_grants_builder_templates();
		$this->close_panel();
	}

	private function render_login_tab($settings) {
		$this->open_panel('Login Page', 'Control the member sign-in copy and post-purchase sign-in messaging.');
		?>
		<table class="form-table" role="presentation"><tbody>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][login_hero_kicker]', 'Hero kicker', $settings['content']['login_hero_kicker']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][login_hero_title]', 'Hero title', $settings['content']['login_hero_title']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][login_hero_description]', 'Hero description', $settings['content']['login_hero_description']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][login_form_kicker]', 'Form kicker', $settings['content']['login_form_kicker']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][login_form_title]', 'Form title', $settings['content']['login_form_title']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][login_submit_label]', 'Submit button label', $settings['content']['login_submit_label']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][login_forgot_password_label]', 'Forgot password label', $settings['content']['login_forgot_password_label']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][login_join_link_label]', 'Join link label', $settings['content']['login_join_link_label']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][login_purchase_success_message]', 'Purchase success message', $settings['content']['login_purchase_success_message']); ?>
			<?php $this->render_media_row(self::OPTION_KEY . '[design][login_background_image_url]', 'Background image URL', $settings['design']['login_background_image_url']); ?>
		</tbody></table>
		<?php
		$this->close_panel();
	}

	private function render_profile_tab($settings) {
		$this->open_panel('Member Profile Page', 'Manage the main member profile cards and button labels.');
		$member_profile_blocks = isset($settings['content']['member_profile_blocks']) && is_array($settings['content']['member_profile_blocks'])
			? array_values($settings['content']['member_profile_blocks'])
			: [];
		$member_profile_card_sections = isset($settings['content']['member_profile_card_sections']) && is_array($settings['content']['member_profile_card_sections'])
			? $settings['content']['member_profile_card_sections']
			: self::get_default_member_profile_card_sections();
		?>
		<table class="form-table" role="presentation"><tbody>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][profile_information_title]', 'Profile Information title', $settings['content']['profile_information_title']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][profile_information_description]', 'Profile Information description', $settings['content']['profile_information_description']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][membership_snapshot_title]', 'Membership Snapshot title', $settings['content']['membership_snapshot_title']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][membership_snapshot_description]', 'Membership Snapshot description', $settings['content']['membership_snapshot_description']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][linked_accounts_title]', 'Linked Accounts title', $settings['content']['linked_accounts_title']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][linked_accounts_description]', 'Linked Accounts description', $settings['content']['linked_accounts_description']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][update_profile_button_label]', 'Update profile button label', $settings['content']['update_profile_button_label']); ?>
		</tbody></table>

		<?php $backfill_notice = $this->get_pmpro_backfill_notice(); ?>
		<h3 style="margin:24px 0 12px;">PMPro Field Backfill</h3>
		<p class="description" style="margin-bottom:12px;">Copy legacy member-profile values from the member database pathway into PMPro core fields and PMPro User Fields, then keep the sync aligned going forward.</p>
		<?php if ($backfill_notice) : ?>
			<div class="notice notice-success inline"><p><?php echo esc_html($backfill_notice); ?></p></div>
		<?php endif; ?>
		<p style="margin:12px 0 18px;">
			<button
				type="submit"
				class="button button-secondary"
				formmethod="post"
				formaction="<?php echo esc_url(admin_url('admin-post.php?action=aac_member_portal_backfill_pmpro_fields')); ?>"
				name="aac_member_portal_backfill_submit"
				value="1"
			>
				Backfill PMPro Fields From Member Database
			</button>
		</p>
		<?php wp_nonce_field('aac_member_portal_backfill_pmpro_fields', 'aac_member_portal_backfill_nonce'); ?>

		<h3 style="margin:24px 0 12px;">Hide / Show Profile Cards</h3>
		<p class="description" style="margin-bottom:12px;">Control which built-in cards appear on the member profile page.</p>
		<table class="widefat striped" style="margin-top:16px;">
			<thead>
				<tr>
					<th>Card</th>
					<th>Visible</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($member_profile_card_sections as $section_id => $section_settings) : ?>
					<tr>
						<td><strong><?php echo esc_html($section_settings['label'] ?? $section_id); ?></strong></td>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY . '[content][member_profile_card_sections][' . $section_id . '][visible]'); ?>" value="1" <?php checked(!empty($section_settings['visible'])); ?> /> Visible</label>
							<input type="hidden" name="<?php echo esc_attr(self::OPTION_KEY . '[content][member_profile_card_sections][' . $section_id . '][label]'); ?>" value="<?php echo esc_attr($section_settings['label'] ?? $section_id); ?>" />
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h3 style="margin:24px 0 12px;">Custom Member Profile Blocks</h3>
		<p class="description" style="margin-bottom:12px;">Add, remove, and edit custom cards and the entries inside them. These blocks appear on the member profile beneath the built-in AAC profile cards.</p>
		<div class="aac-discount-admin">
			<div id="aac-member-profile-blocks" class="aac-discount-admin__list">
				<?php foreach ($member_profile_blocks as $index => $block) : ?>
					<?php $this->render_member_profile_block_editor($index, $block); ?>
				<?php endforeach; ?>
			</div>
			<p style="margin-top:16px;">
				<button type="button" class="button button-secondary" id="aac-add-member-profile-block">Add Profile Block</button>
			</p>
		</div>
		<?php
		$this->render_member_profile_block_templates();
		$this->close_panel();
	}

	public function handle_backfill_pmpro_fields() {
		if (!current_user_can('manage_options')) {
			wp_die('You do not have permission to run this sync.');
		}

		check_admin_referer('aac_member_portal_backfill_pmpro_fields', 'aac_member_portal_backfill_nonce');

		if (function_exists('set_time_limit')) {
			@set_time_limit(0);
		}

		$plugin = function_exists('aac_member_portal') ? aac_member_portal() : null;
		$result = is_object($plugin) && method_exists($plugin, 'backfill_pmpro_fields_from_member_database')
			? $plugin->backfill_pmpro_fields_from_member_database()
			: ['candidate_count' => 0, 'synced_count' => 0];

		$redirect_url = add_query_arg(
			[
				'page' => self::MENU_SLUG,
				'tab' => 'experience',
				'aac_pmpro_backfill' => '1',
				'aac_pmpro_backfill_candidates' => isset($result['candidate_count']) ? (int) $result['candidate_count'] : 0,
				'aac_pmpro_backfill_synced' => isset($result['synced_count']) ? (int) $result['synced_count'] : 0,
			],
			admin_url('admin.php')
		);

		wp_safe_redirect($redirect_url);
		exit;
	}

	private function render_discounts_tab($settings) {
		$this->open_panel('Discounts Page', 'Manage the partner discount cards shown in the member portal.');
		$discount_cards = isset($settings['content']['discount_cards']) && is_array($settings['content']['discount_cards'])
			? array_values($settings['content']['discount_cards'])
			: [];
		if (empty($discount_cards)) {
			$discount_cards = self::get_default_discount_cards();
		}
		?>
		<table class="form-table" role="presentation"><tbody>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][discounts_title]', 'Page title', $settings['content']['discounts_title']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][discounts_locked_title]', 'Locked-state title', $settings['content']['discounts_locked_title']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][discounts_locked_description]', 'Locked-state description', $settings['content']['discounts_locked_description']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][discounts_free_locked_description]', 'Free-tier locked description', $settings['content']['discounts_free_locked_description']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][discounts_upgrade_hint]', 'Upgrade hint', $settings['content']['discounts_upgrade_hint']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][discounts_button_label]', 'Card button label', $settings['content']['discounts_button_label']); ?>
		</tbody></table>

		<h3 style="margin:24px 0 12px;">Discount Cards</h3>
		<p class="description" style="margin-bottom:12px;">Add, remove, and edit the member discount cards. Each card includes a brand, member-facing code text, level-specific discount percents, display text, website link, and image.</p>
		<div class="aac-discount-admin">
			<div id="aac-discount-cards" class="aac-discount-admin__list">
				<?php foreach ($discount_cards as $index => $card) : ?>
					<?php $this->render_discount_card_editor($index, $card); ?>
				<?php endforeach; ?>
			</div>
			<p style="margin-top:16px;">
				<button type="button" class="button button-secondary" id="aac-add-discount-card">Add Discount Card</button>
			</p>
		</div>
		<?php
		$this->render_discount_card_template();
		$this->close_panel();
	}

	private function render_publications_tab($settings) {
		$this->open_panel('Publications Page', 'Update member publication copy, view links, and locked-state messaging.');
		?>
		<table class="form-table" role="presentation"><tbody>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][publications_title]', 'Page title', $settings['content']['publications_title']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][publications_description]', 'Page description', $settings['content']['publications_description']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][publications_locked_title]', 'Locked-state title', $settings['content']['publications_locked_title']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][publications_locked_description]', 'Locked-state description', $settings['content']['publications_locked_description']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][publications_upgrade_button_label]', 'Upgrade button label', $settings['content']['publications_upgrade_button_label']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][publication_view_url_aaj]', 'AAJ View URL', $settings['content']['publication_view_url_aaj'], 'url'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][publication_view_url_anac]', 'ANAC View URL', $settings['content']['publication_view_url_anac'], 'url'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][publication_view_url_acj]', 'American Climbing Journal View URL', $settings['content']['publication_view_url_acj'], 'url'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][publication_view_url_guidebook]', 'Guidebook View URL', $settings['content']['publication_view_url_guidebook'], 'url'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][publication_tile_image_aaj]', 'AAJ tile image URL', $settings['design']['publication_tile_image_aaj'], 'url'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][publication_tile_image_anac]', 'ANAC tile image URL', $settings['design']['publication_tile_image_anac'], 'url'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][publication_tile_image_acj]', 'American Climbing Journal tile image URL', $settings['design']['publication_tile_image_acj'], 'url'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][publication_tile_image_guidebook]', 'Guidebook tile image URL', $settings['design']['publication_tile_image_guidebook'], 'url'); ?>
		</tbody></table>
		<?php
		$this->close_panel();
	}

	private function render_rescue_tab($settings) {
		$this->open_panel('Rescue Page', 'Control rescue page titles, locked/inactive messaging, and rescue benefit values by membership level.');
		$rescue_levels = isset($settings['content']['rescue_levels']) && is_array($settings['content']['rescue_levels'])
			? array_values($settings['content']['rescue_levels'])
			: self::get_default_rescue_levels();
		?>
		<table class="form-table" role="presentation"><tbody>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][rescue_title]', 'Page title', $settings['content']['rescue_title']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][rescue_coverage_title]', 'Coverage card title', $settings['content']['rescue_coverage_title']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][rescue_emergency_title]', 'Emergency card title', $settings['content']['rescue_emergency_title']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][rescue_claim_forms_title]', 'Claim forms title', $settings['content']['rescue_claim_forms_title']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][rescue_inactive_title]', 'Inactive title', $settings['content']['rescue_inactive_title']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][rescue_inactive_description]', 'Inactive description', $settings['content']['rescue_inactive_description']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][rescue_upgrade_title]', 'Upgrade title', $settings['content']['rescue_upgrade_title']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][rescue_upgrade_description]', 'Upgrade description', $settings['content']['rescue_upgrade_description']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][rescue_manage_button_label]', 'Manage/upgrade button label', $settings['content']['rescue_manage_button_label']); ?>
		</tbody></table>

		<h3 style="margin:24px 0 12px;">Rescue Benefit Values By Membership Level</h3>
		<p class="description" style="margin-bottom:12px;">Add one row per membership level. These values feed the member-profile Rescue page and the membership benefits data shown in the portal.</p>
		<div class="aac-rescue-level-admin">
			<div id="aac-rescue-levels" class="aac-rescue-level-admin__list">
				<?php foreach ($rescue_levels as $index => $level) : ?>
					<?php $this->render_rescue_level_editor($index, $level); ?>
				<?php endforeach; ?>
			</div>
			<p style="margin-top:16px;">
				<button type="button" class="button button-secondary" id="aac-add-rescue-level">Add Membership Level</button>
			</p>
		</div>
		<?php
		$this->render_rescue_level_template();
		$this->close_panel();
	}

	private function render_linked_accounts_tab($settings) {
		$this->open_panel('Linked Accounts Page', 'Update family invite redemption labels and success messaging.');
		?>
		<table class="form-table" role="presentation"><tbody>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][linked_accounts_page_title]', 'Page title', $settings['content']['linked_accounts_page_title']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][linked_accounts_page_description]', 'Page description', $settings['content']['linked_accounts_page_description']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][linked_accounts_lookup_button_label]', 'Check code button label', $settings['content']['linked_accounts_lookup_button_label']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][linked_accounts_redeem_button_label]', 'Redeem button label', $settings['content']['linked_accounts_redeem_button_label']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][linked_accounts_success_message]', 'Success message', $settings['content']['linked_accounts_success_message']); ?>
		</tbody></table>
		<?php
		$this->close_panel();
	}

	private function render_design_tab($settings) {
		$this->open_panel('Design', 'Update shared portal images, color controls, overlays, and navigation styling used across the AAC member experience.');
		?>
		<table class="form-table" role="presentation"><tbody>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][page_background]', 'Page background', $settings['design']['page_background'], 'text', 'Used for the lighter page background areas.'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][panel_background]', 'Panel background', $settings['design']['panel_background'], 'text', 'Default background for cards and surfaces.'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][panel_border_color]', 'Panel border color', $settings['design']['panel_border_color'], 'text'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][sidebar_background_url]', 'Sidebar background image URL', $settings['design']['sidebar_background_url'], 'url', 'Leave blank to use the bundled topo background.'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][sidebar_overlay_start]', 'Sidebar overlay start opacity', $settings['design']['sidebar_overlay_start'], 'number', 'Lower values make the topo lines more visible.', '0', '1', '0.01'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][sidebar_overlay_end]', 'Sidebar overlay end opacity', $settings['design']['sidebar_overlay_end'], 'number', 'Used for the darker lower part of the overlay.', '0', '1', '0.01'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][sidebar_button_background]', 'Sidebar button background', $settings['design']['sidebar_button_background'], 'text'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][sidebar_button_hover_background]', 'Sidebar button hover background', $settings['design']['sidebar_button_hover_background'], 'text'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][sidebar_button_active_background]', 'Sidebar button active background', $settings['design']['sidebar_button_active_background'], 'text'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][sidebar_accent_color]', 'Sidebar accent color', $settings['design']['sidebar_accent_color'], 'text'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][primary_action_background]', 'Primary action background', $settings['design']['primary_action_background'], 'text'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][primary_action_text]', 'Primary action text', $settings['design']['primary_action_text'], 'text'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][secondary_action_background]', 'Secondary action background', $settings['design']['secondary_action_background'], 'text'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][secondary_action_text]', 'Secondary action text', $settings['design']['secondary_action_text'], 'text'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][hero_panel_background]', 'Hero text block background', $settings['design']['hero_panel_background'], 'text'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][hero_panel_border_color]', 'Hero text block border color', $settings['design']['hero_panel_border_color'], 'text'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][hero_chip_background]', 'Hero supporting chip background', $settings['design']['hero_chip_background'], 'text'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][hero_chip_border_color]', 'Hero supporting chip border color', $settings['design']['hero_chip_border_color'], 'text'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][login_form_background]', 'Login form background', $settings['design']['login_form_background'], 'text'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][login_overlay]', 'Login page overlay CSS', $settings['design']['login_overlay'], 'text', 'Advanced: accepts a CSS background value.'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][home_hero_overlay]', 'Home hero overlay CSS', $settings['design']['home_hero_overlay'], 'text'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][home_hero_tint_overlay]', 'Home hero tint CSS', $settings['design']['home_hero_tint_overlay'], 'text'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][join_hero_overlay]', 'Join hero overlay CSS', $settings['design']['join_hero_overlay'], 'text'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][join_hero_tint_overlay]', 'Join hero tint CSS', $settings['design']['join_hero_tint_overlay'], 'text'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][nav_background]', 'Top nav background', $settings['design']['nav_background'], 'text'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][nav_text_color]', 'Top nav text color', $settings['design']['nav_text_color'], 'text'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][nav_hover_text_color]', 'Top nav hover color', $settings['design']['nav_hover_text_color'], 'text'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][nav_icon_color]', 'Top nav icon color', $settings['design']['nav_icon_color'], 'text'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][nav_dropdown_background]', 'Nav dropdown background', $settings['design']['nav_dropdown_background'], 'text'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][nav_dropdown_text_color]', 'Nav dropdown text color', $settings['design']['nav_dropdown_text_color'], 'text'); ?>
		</tbody></table>
		<?php
		$this->close_panel();
	}

	private function render_navigation_tab($settings) {
		$this->open_panel('Navigation', 'Update section titles and control where each sidebar item appears.');
		$portal_page_url = function_exists('aac_member_portal') && aac_member_portal() && method_exists(aac_member_portal(), 'get_portal_page_url')
			? aac_member_portal()->get_portal_page_url()
			: home_url('/membership/');
		$top_nav_registry = function_exists('aac_member_portal') && aac_member_portal()
			? aac_member_portal()->get_top_nav_item_registry($portal_page_url)
			: [];
		?>
		<table class="form-table" role="presentation">
			<tbody>
				<?php foreach ($settings['components']['section_titles'] as $section_id => $title) : ?>
					<?php $this->render_input_row(self::OPTION_KEY . '[components][section_titles][' . $section_id . ']', sprintf('Section title: %s', $section_id), $title); ?>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h3 style="margin:24px 0 12px;">Top Navigation</h3>
		<table class="widefat striped" style="margin-top:16px;">
			<thead>
				<tr>
					<th>Section</th>
					<th>Label</th>
					<th>Subnavigation</th>
					<th>Order</th>
					<th>Visible</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($settings['components']['top_nav_items'] as $item_id => $item_settings) : ?>
					<?php
					$current_children = isset($item_settings['children']) && is_array($item_settings['children']) && !empty($item_settings['children'])
						? $item_settings['children']
						: (isset($top_nav_registry[$item_id]['children']) && is_array($top_nav_registry[$item_id]['children']) ? $top_nav_registry[$item_id]['children'] : []);
					?>
					<tr>
						<td><strong><?php echo esc_html($item_id); ?></strong></td>
						<td><input type="text" class="regular-text" name="<?php echo esc_attr(self::OPTION_KEY . '[components][top_nav_items][' . $item_id . '][label]'); ?>" value="<?php echo esc_attr($item_settings['label']); ?>" /></td>
						<td style="min-width:340px;">
							<textarea class="large-text code" rows="5" name="<?php echo esc_attr(self::OPTION_KEY . '[components][top_nav_items][' . $item_id . '][children_text]'); ?>" placeholder="One item per line: Label | URL | external"><?php echo esc_textarea($this->format_top_nav_children_for_textarea($current_children)); ?></textarea>
							<p class="description" style="margin:6px 0 0;">Format: <code>Label | URL | external</code>. The third value is optional.</p>
						</td>
						<td><input type="number" name="<?php echo esc_attr(self::OPTION_KEY . '[components][top_nav_items][' . $item_id . '][order]'); ?>" value="<?php echo esc_attr($item_settings['order']); ?>" style="width:90px;" /></td>
						<td><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY . '[components][top_nav_items][' . $item_id . '][visible]'); ?>" value="1" <?php checked(!empty($item_settings['visible'])); ?> /> Visible</label></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h3 style="margin:24px 0 12px;">Sidebar Navigation</h3>
		<table class="widefat striped" style="margin-top:16px;">
			<thead>
				<tr>
					<th>Component</th>
					<th>Label</th>
					<th>Section</th>
					<th>Order</th>
					<th>Visible</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($settings['components']['sidebar_items'] as $item_id => $item_settings) : ?>
					<tr>
						<td><strong><?php echo esc_html($item_id); ?></strong></td>
						<td><input type="text" class="regular-text" name="<?php echo esc_attr(self::OPTION_KEY . '[components][sidebar_items][' . $item_id . '][label]'); ?>" value="<?php echo esc_attr($item_settings['label']); ?>" /></td>
						<td>
							<select name="<?php echo esc_attr(self::OPTION_KEY . '[components][sidebar_items][' . $item_id . '][section]'); ?>">
								<?php foreach ($settings['components']['section_titles'] as $section_id => $section_title) : ?>
									<option value="<?php echo esc_attr($section_id); ?>" <?php selected($item_settings['section'], $section_id); ?>>
										<?php echo esc_html($section_title); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
						<td><input type="number" name="<?php echo esc_attr(self::OPTION_KEY . '[components][sidebar_items][' . $item_id . '][order]'); ?>" value="<?php echo esc_attr($item_settings['order']); ?>" style="width:90px;" /></td>
						<td><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY . '[components][sidebar_items][' . $item_id . '][visible]'); ?>" value="1" <?php checked(!empty($item_settings['visible'])); ?> /> Visible</label></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		$this->close_panel();
	}

	private function render_layout_tab($settings) {
		$this->open_panel('Layout and Section Order', 'Rearrange homepage sections and control which big blocks show up. This gives the admin a little more furniture-moving power without opening the code editor.');
		?>
		<h3 style="margin:0 0 12px;">Homepage Sections</h3>
		<table class="widefat striped" style="margin-top:16px;">
			<thead>
				<tr>
					<th>Section</th>
					<th>Label</th>
					<th>Order</th>
					<th>Visible</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($settings['components']['home_sections'] as $section_id => $section_settings) : ?>
					<tr>
						<td><strong><?php echo esc_html($section_id); ?></strong></td>
						<td><input type="text" class="regular-text" name="<?php echo esc_attr(self::OPTION_KEY . '[components][home_sections][' . $section_id . '][label]'); ?>" value="<?php echo esc_attr($section_settings['label']); ?>" /></td>
						<td><input type="number" style="width:90px;" name="<?php echo esc_attr(self::OPTION_KEY . '[components][home_sections][' . $section_id . '][order]'); ?>" value="<?php echo esc_attr($section_settings['order']); ?>" /></td>
						<td><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY . '[components][home_sections][' . $section_id . '][visible]'); ?>" value="1" <?php checked(!empty($section_settings['visible'])); ?> /> Visible</label></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		$this->close_panel();
	}

	private function open_panel($title, $description = '') {
		?>
		<section style="background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:24px;">
			<h2 style="margin-top:0;"><?php echo esc_html($title); ?></h2>
			<?php if ($description) : ?>
				<p><?php echo esc_html($description); ?></p>
			<?php endif; ?>
		<?php
	}

	private function close_panel() {
		echo '</section>';
	}

	private function render_discount_card_editor($index, $card = []) {
		$base_name = self::OPTION_KEY . '[content][discount_cards][' . (int) $index . ']';
		$brand = $card['brand'] ?? '';
		$discount_percent = $card['discount_percent'] ?? '';
		$discount_code_text = $card['discount_code_text'] ?? '';
		$discount_code_text_supporter = $card['discount_code_text_supporter'] ?? '';
		$discount_code_text_partner = $card['discount_code_text_partner'] ?? '';
		$discount_code_text_leader = $card['discount_code_text_leader'] ?? '';
		$discount_code_text_advocate = $card['discount_code_text_advocate'] ?? '';
		$discount_percent_supporter = $card['discount_percent_supporter'] ?? '';
		$discount_percent_partner = $card['discount_percent_partner'] ?? '';
		$discount_percent_leader = $card['discount_percent_leader'] ?? '';
		$discount_percent_advocate = $card['discount_percent_advocate'] ?? '';
		$display_text = $card['display_text'] ?? '';
		$button_url = $card['button_url'] ?? '';
		$image_url = $card['image_url'] ?? '';
		?>
		<div class="aac-discount-card-editor" data-aac-discount-card>
			<div class="aac-discount-card-editor__header">
				<h4>Discount Card</h4>
				<button type="button" class="button-link-delete" data-aac-remove-discount-card>Remove</button>
			</div>
			<div class="aac-discount-card-editor__grid">
				<p>
					<label>
						<strong>Brand</strong><br />
						<input type="text" class="regular-text" name="<?php echo esc_attr($base_name . '[brand]'); ?>" value="<?php echo esc_attr($brand); ?>" />
					</label>
				</p>
				<p>
					<label>
						<strong>Fallback Discount %</strong><br />
						<input type="text" class="regular-text" name="<?php echo esc_attr($base_name . '[discount_percent]'); ?>" value="<?php echo esc_attr($discount_percent); ?>" placeholder="20%" />
					</label>
				</p>
				<p>
					<label>
						<strong>Supporter %</strong><br />
						<input type="text" class="regular-text" name="<?php echo esc_attr($base_name . '[discount_percent_supporter]'); ?>" value="<?php echo esc_attr($discount_percent_supporter); ?>" placeholder="15%" />
					</label>
				</p>
				<p>
					<label>
						<strong>Partner %</strong><br />
						<input type="text" class="regular-text" name="<?php echo esc_attr($base_name . '[discount_percent_partner]'); ?>" value="<?php echo esc_attr($discount_percent_partner); ?>" placeholder="20%" />
					</label>
				</p>
				<p>
					<label>
						<strong>Leader %</strong><br />
						<input type="text" class="regular-text" name="<?php echo esc_attr($base_name . '[discount_percent_leader]'); ?>" value="<?php echo esc_attr($discount_percent_leader); ?>" placeholder="25%" />
					</label>
				</p>
				<p>
					<label>
						<strong>Advocate %</strong><br />
						<input type="text" class="regular-text" name="<?php echo esc_attr($base_name . '[discount_percent_advocate]'); ?>" value="<?php echo esc_attr($discount_percent_advocate); ?>" placeholder="30%" />
					</label>
				</p>
				<p class="aac-discount-card-editor__full">
					<label>
						<strong>Fallback Discount Code / Text</strong><br />
						<textarea rows="2" class="large-text" name="<?php echo esc_attr($base_name . '[discount_code_text]'); ?>" placeholder="Use code AACMEMBER at checkout."><?php echo esc_textarea($discount_code_text); ?></textarea>
					</label>
				</p>
				<p>
					<label>
						<strong>Supporter Code / Text</strong><br />
						<textarea rows="2" class="large-text" name="<?php echo esc_attr($base_name . '[discount_code_text_supporter]'); ?>" placeholder="Supporter discount details."><?php echo esc_textarea($discount_code_text_supporter); ?></textarea>
					</label>
				</p>
				<p>
					<label>
						<strong>Partner Code / Text</strong><br />
						<textarea rows="2" class="large-text" name="<?php echo esc_attr($base_name . '[discount_code_text_partner]'); ?>" placeholder="Partner discount details."><?php echo esc_textarea($discount_code_text_partner); ?></textarea>
					</label>
				</p>
				<p>
					<label>
						<strong>Leader Code / Text</strong><br />
						<textarea rows="2" class="large-text" name="<?php echo esc_attr($base_name . '[discount_code_text_leader]'); ?>" placeholder="Leader discount details."><?php echo esc_textarea($discount_code_text_leader); ?></textarea>
					</label>
				</p>
				<p>
					<label>
						<strong>Advocate Code / Text</strong><br />
						<textarea rows="2" class="large-text" name="<?php echo esc_attr($base_name . '[discount_code_text_advocate]'); ?>" placeholder="Advocate discount details."><?php echo esc_textarea($discount_code_text_advocate); ?></textarea>
					</label>
				</p>
				<p class="aac-discount-card-editor__full">
					<label>
						<strong>Display Text</strong><br />
						<textarea rows="3" class="large-text" name="<?php echo esc_attr($base_name . '[display_text]'); ?>"><?php echo esc_textarea($display_text); ?></textarea>
					</label>
				</p>
				<p class="aac-discount-card-editor__full">
					<label>
						<strong>Button URL</strong><br />
						<input type="url" class="large-text" name="<?php echo esc_attr($base_name . '[button_url]'); ?>" value="<?php echo esc_attr($button_url); ?>" />
					</label>
				</p>
				<div class="aac-discount-card-editor__full">
					<label>
						<strong>Card Image</strong><br />
						<input type="url" class="large-text aac-discount-card-editor__image-input" name="<?php echo esc_attr($base_name . '[image_url]'); ?>" value="<?php echo esc_attr($image_url); ?>" />
					</label>
					<p style="margin:8px 0 0;">
						<button type="button" class="button button-secondary" data-aac-select-discount-image>Select Image</button>
					</p>
					<div class="aac-discount-card-editor__preview">
						<?php if ($image_url) : ?>
							<img src="<?php echo esc_url($image_url); ?>" alt="" />
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	private function render_discount_card_template() {
		ob_start();
		$this->render_discount_card_editor('__INDEX__', []);
		$template = ob_get_clean();
		?>
		<template id="aac-discount-card-template"><?php echo str_replace('__INDEX__', '__INDEX__', $template); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></template>
		<style>
			.aac-discount-card-editor{border:1px solid #dcdcde;border-radius:12px;padding:16px;background:#fff;margin-bottom:16px}
			.aac-discount-card-editor__header{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:12px}
			.aac-discount-card-editor__header h4{margin:0}
			.aac-discount-card-editor__grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
			.aac-discount-card-editor__full{grid-column:1 / -1}
			.aac-discount-card-editor__preview{margin-top:12px;min-height:64px}
			.aac-discount-card-editor__preview img{display:block;max-width:220px;width:100%;height:auto;border-radius:8px;border:1px solid #dcdcde}
			@media (max-width: 782px){.aac-discount-card-editor__grid{grid-template-columns:1fr}}
		</style>
		<script>
			document.addEventListener('DOMContentLoaded', function () {
				const list = document.getElementById('aac-discount-cards');
				const template = document.getElementById('aac-discount-card-template');
				const addButton = document.getElementById('aac-add-discount-card');
				if (!list || !template || !addButton) {
					return;
				}

				const refreshIndexes = () => {
					list.querySelectorAll('[data-aac-discount-card]').forEach((card, index) => {
						card.querySelectorAll('[name]').forEach((field) => {
							field.name = field.name.replace(/\[discount_cards\]\[[^\]]+\]/, '[discount_cards][' + index + ']');
						});
					});
				};

				const updatePreview = (card) => {
					const input = card.querySelector('.aac-discount-card-editor__image-input');
					const preview = card.querySelector('.aac-discount-card-editor__preview');
					if (!input || !preview) {
						return;
					}

					const nextUrl = String(input.value || '').trim();
					preview.innerHTML = nextUrl ? '<img src=\"' + nextUrl.replace(/\"/g, '&quot;') + '\" alt=\"\" />' : '';
				};

				const bindCard = (card) => {
					const removeButton = card.querySelector('[data-aac-remove-discount-card]');
					const selectButton = card.querySelector('[data-aac-select-discount-image]');
					const imageInput = card.querySelector('.aac-discount-card-editor__image-input');

					if (removeButton) {
						removeButton.addEventListener('click', () => {
							card.remove();
							refreshIndexes();
						});
					}

					if (imageInput) {
						imageInput.addEventListener('input', () => updatePreview(card));
					}

					if (selectButton && window.wp && window.wp.media) {
						selectButton.addEventListener('click', () => {
							const frame = window.wp.media({
								title: 'Select discount card image',
								button: { text: 'Use image' },
								multiple: false,
							});

							frame.on('select', () => {
								const attachment = frame.state().get('selection').first().toJSON();
								if (imageInput) {
									imageInput.value = attachment.url || '';
									updatePreview(card);
								}
							});

							frame.open();
						});
					}
				};

				list.querySelectorAll('[data-aac-discount-card]').forEach(bindCard);

				addButton.addEventListener('click', () => {
					const nextIndex = list.querySelectorAll('[data-aac-discount-card]').length;
					const html = template.innerHTML.replace(/__INDEX__/g, String(nextIndex));
					const wrapper = document.createElement('div');
					wrapper.innerHTML = html.trim();
					const card = wrapper.firstElementChild;
					if (!card) {
						return;
					}
					list.appendChild(card);
					bindCard(card);
					refreshIndexes();
				});
			});
		</script>
		<?php
	}

	private function render_rescue_level_editor($index, $level = []) {
		$base_name = self::OPTION_KEY . '[content][rescue_levels][' . (int) $index . ']';
		$level_name = $level['level_name'] ?? '';
		$rescue_amount = isset($level['rescue_amount']) ? (int) $level['rescue_amount'] : 0;
		$medical_amount = isset($level['medical_amount']) ? (int) $level['medical_amount'] : 0;
		$mortal_remains_amount = isset($level['mortal_remains_amount']) ? (int) $level['mortal_remains_amount'] : 0;
		$rescue_reimbursement_process = !empty($level['rescue_reimbursement_process']);
		?>
		<div class="aac-rescue-level-editor" data-aac-rescue-level>
			<div class="aac-rescue-level-editor__header">
				<h4>Membership Level</h4>
				<button type="button" class="button-link-delete" data-aac-remove-rescue-level>Remove</button>
			</div>
			<div class="aac-rescue-level-editor__grid">
				<p>
					<label>
						<strong>Level Name</strong><br />
						<input type="text" class="regular-text" name="<?php echo esc_attr($base_name . '[level_name]'); ?>" value="<?php echo esc_attr($level_name); ?>" placeholder="Partner" />
					</label>
				</p>
				<p>
					<label>
						<strong>Rescue Coverage Amount</strong><br />
						<input type="number" class="regular-text" min="0" step="1" name="<?php echo esc_attr($base_name . '[rescue_amount]'); ?>" value="<?php echo esc_attr($rescue_amount); ?>" placeholder="7500" />
					</label>
				</p>
				<p>
					<label>
						<strong>Medical Expense Amount</strong><br />
						<input type="number" class="regular-text" min="0" step="1" name="<?php echo esc_attr($base_name . '[medical_amount]'); ?>" value="<?php echo esc_attr($medical_amount); ?>" placeholder="5000" />
					</label>
				</p>
				<p>
					<label>
						<strong>Mortal Remains Transport Amount</strong><br />
						<input type="number" class="regular-text" min="0" step="1" name="<?php echo esc_attr($base_name . '[mortal_remains_amount]'); ?>" value="<?php echo esc_attr($mortal_remains_amount); ?>" placeholder="15000" />
					</label>
				</p>
				<p class="aac-rescue-level-editor__full">
					<label>
						<input type="checkbox" name="<?php echo esc_attr($base_name . '[rescue_reimbursement_process]'); ?>" value="1" <?php checked($rescue_reimbursement_process); ?> />
						<strong> Rescue reimbursement process included</strong>
					</label>
				</p>
			</div>
		</div>
		<?php
	}

	private function render_rescue_level_template() {
		ob_start();
		$this->render_rescue_level_editor('__INDEX__', []);
		$template = ob_get_clean();
		?>
		<template id="aac-rescue-level-template"><?php echo str_replace('__INDEX__', '__INDEX__', $template); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></template>
		<style>
			.aac-rescue-level-editor{border:1px solid #dcdcde;border-radius:12px;padding:16px;background:#fff;margin-bottom:16px}
			.aac-rescue-level-editor__header{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:12px}
			.aac-rescue-level-editor__header h4{margin:0}
			.aac-rescue-level-editor__grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
			.aac-rescue-level-editor__full{grid-column:1 / -1}
			@media (max-width: 782px){.aac-rescue-level-editor__grid{grid-template-columns:1fr}}
		</style>
		<script>
			document.addEventListener('DOMContentLoaded', function () {
				const list = document.getElementById('aac-rescue-levels');
				const template = document.getElementById('aac-rescue-level-template');
				const addButton = document.getElementById('aac-add-rescue-level');
				if (!list || !template || !addButton) {
					return;
				}

				const refreshIndexes = () => {
					list.querySelectorAll('[data-aac-rescue-level]').forEach((card, index) => {
						card.querySelectorAll('[name]').forEach((field) => {
							field.name = field.name.replace(/\[rescue_levels\]\[[^\]]+\]/, '[rescue_levels][' + index + ']');
						});
					});
				};

				const bindCard = (card) => {
					const removeButton = card.querySelector('[data-aac-remove-rescue-level]');
					if (removeButton) {
						removeButton.addEventListener('click', () => {
							card.remove();
							refreshIndexes();
						});
					}
				};

				list.querySelectorAll('[data-aac-rescue-level]').forEach(bindCard);

				addButton.addEventListener('click', () => {
					const nextIndex = list.querySelectorAll('[data-aac-rescue-level]').length;
					const html = template.innerHTML.replace(/__INDEX__/g, String(nextIndex));
					const wrapper = document.createElement('div');
					wrapper.innerHTML = html.trim();
					const card = wrapper.firstElementChild;
					if (!card) {
						return;
					}
					list.appendChild(card);
					bindCard(card);
					refreshIndexes();
				});
			});
		</script>
		<?php
	}

	private function render_featured_photographer_editor($index, $photographer = []) {
		$base_name = self::OPTION_KEY . '[content][featured_photographers][' . (int) $index . ']';
		$gallery_items = isset($photographer['gallery_items']) && is_array($photographer['gallery_items'])
			? array_values($photographer['gallery_items'])
			: [];
		?>
		<div class="aac-photographer-editor" data-aac-featured-photographer>
			<div class="aac-photographer-editor__header">
				<h4>Photographer</h4>
				<div class="aac-photographer-editor__actions">
					<button type="button" class="button button-secondary" data-aac-photographer-move-up>↑</button>
					<button type="button" class="button button-secondary" data-aac-photographer-move-down>↓</button>
					<button type="button" class="button-link-delete" data-aac-remove-featured-photographer>Remove</button>
				</div>
			</div>
			<div class="aac-photographer-editor__grid">
				<p><label><strong>Name</strong><br /><input type="text" class="regular-text" name="<?php echo esc_attr($base_name . '[name]'); ?>" value="<?php echo esc_attr($photographer['name'] ?? ''); ?>" /></label></p>
				<p><label><strong>Website URL</strong><br /><input type="url" class="large-text" name="<?php echo esc_attr($base_name . '[website_url]'); ?>" value="<?php echo esc_attr($photographer['website_url'] ?? ''); ?>" /></label></p>
				<p><label><strong>Instagram URL</strong><br /><input type="url" class="large-text" name="<?php echo esc_attr($base_name . '[instagram_url]'); ?>" value="<?php echo esc_attr($photographer['instagram_url'] ?? ''); ?>" /></label></p>
				<p><label><strong>Facebook URL</strong><br /><input type="url" class="large-text" name="<?php echo esc_attr($base_name . '[facebook_url]'); ?>" value="<?php echo esc_attr($photographer['facebook_url'] ?? ''); ?>" /></label></p>
				<p><label><strong>X / Twitter URL</strong><br /><input type="url" class="large-text" name="<?php echo esc_attr($base_name . '[x_url]'); ?>" value="<?php echo esc_attr($photographer['x_url'] ?? ''); ?>" /></label></p>
				<div class="aac-photographer-editor__full">
					<label><strong>Profile Image URL</strong><br /><input type="url" class="large-text aac-photographer-editor__image-input" name="<?php echo esc_attr($base_name . '[profile_image_url]'); ?>" value="<?php echo esc_attr($photographer['profile_image_url'] ?? ''); ?>" /></label>
					<p style="margin:8px 0 0;"><button type="button" class="button button-secondary" data-aac-select-photographer-image>Select Profile Image</button></p>
					<div class="aac-photographer-editor__preview"><?php if (!empty($photographer['profile_image_url'])) : ?><img src="<?php echo esc_url($photographer['profile_image_url']); ?>" alt="" /><?php endif; ?></div>
				</div>
				<p class="aac-photographer-editor__full"><label><strong>Short Bio</strong><br /><textarea rows="4" class="large-text" name="<?php echo esc_attr($base_name . '[short_bio]'); ?>"><?php echo esc_textarea($photographer['short_bio'] ?? ''); ?></textarea></label></p>
			</div>
			<div class="aac-photographer-editor__gallery">
				<div class="aac-photographer-editor__gallery-header">
					<h5>Gallery Images</h5>
					<button type="button" class="button button-secondary" data-aac-add-photographer-gallery-item>Add Gallery Image</button>
				</div>
				<p class="description" style="margin:0 0 12px;">The page shows up to six images per photographer. Order here becomes top-left to bottom-right in the gallery grid.</p>
				<div class="aac-photographer-gallery-list" data-aac-photographer-gallery-list>
					<?php foreach ($gallery_items as $gallery_index => $gallery_item) : ?>
						<?php $this->render_featured_photographer_gallery_item_editor($index, $gallery_index, $gallery_item); ?>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
	}

	private function render_featured_photographer_gallery_item_editor($photographer_index, $gallery_index, $gallery_item = []) {
		$base_name = self::OPTION_KEY . '[content][featured_photographers][' . $photographer_index . '][gallery_items][' . $gallery_index . ']';
		?>
		<div class="aac-photographer-gallery-item" data-aac-photographer-gallery-item>
			<div class="aac-photographer-gallery-item__header">
				<strong>Gallery Image</strong>
				<div class="aac-photographer-editor__actions">
					<button type="button" class="button button-secondary" data-aac-gallery-move-up>↑</button>
					<button type="button" class="button button-secondary" data-aac-gallery-move-down>↓</button>
					<button type="button" class="button-link-delete" data-aac-remove-gallery-item>Remove</button>
				</div>
			</div>
			<p>
				<label><strong>Image URL</strong><br /><input type="url" class="large-text aac-photographer-gallery-item__image-input" name="<?php echo esc_attr($base_name . '[image_url]'); ?>" value="<?php echo esc_attr($gallery_item['image_url'] ?? ''); ?>" /></label>
			</p>
			<p style="margin-top:8px;">
				<button type="button" class="button button-secondary" data-aac-select-gallery-image>Select Image</button>
			</p>
			<p style="margin-top:12px;">
				<label><strong>Caption</strong><br /><input type="text" class="large-text" name="<?php echo esc_attr($base_name . '[caption]'); ?>" value="<?php echo esc_attr($gallery_item['caption'] ?? ''); ?>" /></label>
			</p>
			<div class="aac-photographer-gallery-item__preview"><?php if (!empty($gallery_item['image_url'])) : ?><img src="<?php echo esc_url($gallery_item['image_url']); ?>" alt="" /><?php endif; ?></div>
		</div>
		<?php
	}

	private function render_featured_photographer_templates() {
		ob_start();
		$this->render_featured_photographer_editor('__INDEX__', []);
		$photographer_template = ob_get_clean();
		ob_start();
		$this->render_featured_photographer_gallery_item_editor('__P_INDEX__', '__G_INDEX__', []);
		$gallery_template = ob_get_clean();
		?>
		<template id="aac-featured-photographer-template"><?php echo str_replace('__INDEX__', '__INDEX__', $photographer_template); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></template>
		<template id="aac-featured-photographer-gallery-item-template"><?php echo str_replace(['__P_INDEX__', '__G_INDEX__'], ['__P_INDEX__', '__G_INDEX__'], $gallery_template); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></template>
		<style>
			.aac-photographer-editor{border:1px solid #dcdcde;border-radius:16px;padding:18px;background:#fff;margin-bottom:18px}
			.aac-photographer-editor__header,.aac-photographer-editor__gallery-header{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:12px}
			.aac-photographer-editor__header h4,.aac-photographer-editor__gallery-header h5{margin:0}
			.aac-photographer-editor__actions{display:flex;align-items:center;gap:8px}
			.aac-photographer-editor__grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
			.aac-photographer-editor__full{grid-column:1 / -1}
			.aac-photographer-editor__preview,.aac-photographer-gallery-item__preview{margin-top:12px;min-height:72px}
			.aac-photographer-editor__preview img,.aac-photographer-gallery-item__preview img{display:block;max-width:220px;width:100%;height:auto;border-radius:10px;border:1px solid #dcdcde}
			.aac-photographer-editor__gallery{margin-top:18px;padding-top:18px;border-top:1px solid #e7e5e4}
			.aac-photographer-gallery-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
			.aac-photographer-gallery-item{border:1px solid #e7e5e4;border-radius:14px;padding:14px;background:#fafaf9}
			.aac-photographer-gallery-item__header{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:12px}
			@media (max-width: 782px){.aac-photographer-editor__grid,.aac-photographer-gallery-list{grid-template-columns:1fr}}
		</style>
		<script>
			document.addEventListener('DOMContentLoaded', function () {
				const list = document.getElementById('aac-featured-photographers');
				const template = document.getElementById('aac-featured-photographer-template');
				const galleryTemplate = document.getElementById('aac-featured-photographer-gallery-item-template');
				const addButton = document.getElementById('aac-add-featured-photographer');
				if (!list || !template || !galleryTemplate || !addButton) {
					return;
				}

				const openMediaFrame = (callback) => {
					if (!(window.wp && window.wp.media)) {
						return;
					}
					const frame = window.wp.media({
						title: 'Select image',
						button: { text: 'Use image' },
						multiple: false,
					});
					frame.on('select', () => {
						const attachment = frame.state().get('selection').first().toJSON();
						callback(attachment);
					});
					frame.open();
				};

				const moveNode = (node, direction) => {
					if (!node || !node.parentNode) {
						return;
					}
					const sibling = direction === 'up' ? node.previousElementSibling : node.nextElementSibling;
					if (!sibling) {
						return;
					}
					if (direction === 'up') {
						node.parentNode.insertBefore(node, sibling);
					} else {
						node.parentNode.insertBefore(sibling, node);
					}
				};

				const updateImagePreview = (input, preview) => {
					if (!input || !preview) {
						return;
					}
					const nextUrl = String(input.value || '').trim();
					preview.innerHTML = nextUrl ? '<img src="' + nextUrl.replace(/"/g, '&quot;') + '" alt="" />' : '';
				};

				const refreshIndexes = () => {
					list.querySelectorAll('[data-aac-featured-photographer]').forEach((card, pIndex) => {
						card.querySelectorAll('[name]').forEach((field) => {
							field.name = field.name.replace(/\[featured_photographers\]\[[^\]]+\]/, '[featured_photographers][' + pIndex + ']');
						});
						const galleryList = card.querySelector('[data-aac-photographer-gallery-list]');
						if (!galleryList) {
							return;
						}
						galleryList.querySelectorAll('[data-aac-photographer-gallery-item]').forEach((galleryItem, gIndex) => {
							galleryItem.querySelectorAll('[name]').forEach((field) => {
								field.name = field.name.replace(/\[gallery_items\]\[[^\]]+\]/, '[gallery_items][' + gIndex + ']');
							});
						});
					});
				};

				const bindGalleryItem = (galleryItem) => {
					const removeButton = galleryItem.querySelector('[data-aac-remove-gallery-item]');
					const moveUpButton = galleryItem.querySelector('[data-aac-gallery-move-up]');
					const moveDownButton = galleryItem.querySelector('[data-aac-gallery-move-down]');
					const imageInput = galleryItem.querySelector('.aac-photographer-gallery-item__image-input');
					const preview = galleryItem.querySelector('.aac-photographer-gallery-item__preview');
					const selectButton = galleryItem.querySelector('[data-aac-select-gallery-image]');

					if (removeButton) {
						removeButton.addEventListener('click', () => {
							galleryItem.remove();
							refreshIndexes();
						});
					}
					if (moveUpButton) {
						moveUpButton.addEventListener('click', () => {
							moveNode(galleryItem, 'up');
							refreshIndexes();
						});
					}
					if (moveDownButton) {
						moveDownButton.addEventListener('click', () => {
							moveNode(galleryItem, 'down');
							refreshIndexes();
						});
					}
					if (imageInput) {
						imageInput.addEventListener('input', () => updateImagePreview(imageInput, preview));
					}
					if (selectButton) {
						selectButton.addEventListener('click', () => {
							openMediaFrame((attachment) => {
								if (imageInput) {
									imageInput.value = attachment.url || '';
									updateImagePreview(imageInput, preview);
								}
							});
						});
					}
				};

				const bindPhotographerCard = (card) => {
					const removeButton = card.querySelector('[data-aac-remove-featured-photographer]');
					const moveUpButton = card.querySelector('[data-aac-photographer-move-up]');
					const moveDownButton = card.querySelector('[data-aac-photographer-move-down]');
					const imageInput = card.querySelector('.aac-photographer-editor__image-input');
					const preview = card.querySelector('.aac-photographer-editor__preview');
					const selectButton = card.querySelector('[data-aac-select-photographer-image]');
					const addGalleryButton = card.querySelector('[data-aac-add-photographer-gallery-item]');
					const galleryList = card.querySelector('[data-aac-photographer-gallery-list]');

					if (removeButton) {
						removeButton.addEventListener('click', () => {
							card.remove();
							refreshIndexes();
						});
					}
					if (moveUpButton) {
						moveUpButton.addEventListener('click', () => {
							moveNode(card, 'up');
							refreshIndexes();
						});
					}
					if (moveDownButton) {
						moveDownButton.addEventListener('click', () => {
							moveNode(card, 'down');
							refreshIndexes();
						});
					}
					if (imageInput) {
						imageInput.addEventListener('input', () => updateImagePreview(imageInput, preview));
					}
					if (selectButton) {
						selectButton.addEventListener('click', () => {
							openMediaFrame((attachment) => {
								if (imageInput) {
									imageInput.value = attachment.url || '';
									updateImagePreview(imageInput, preview);
								}
							});
						});
					}
					if (galleryList) {
						galleryList.querySelectorAll('[data-aac-photographer-gallery-item]').forEach(bindGalleryItem);
					}
					if (addGalleryButton && galleryList) {
						addGalleryButton.addEventListener('click', () => {
							if (galleryList.querySelectorAll('[data-aac-photographer-gallery-item]').length >= 6) {
								window.alert('Each photographer can display up to 6 gallery images.');
								return;
							}
							const nextPhotographerIndex = Array.from(list.querySelectorAll('[data-aac-featured-photographer]')).indexOf(card);
							const nextGalleryIndex = galleryList.querySelectorAll('[data-aac-photographer-gallery-item]').length;
							const html = galleryTemplate.innerHTML
								.replace(/__P_INDEX__/g, String(nextPhotographerIndex))
								.replace(/__G_INDEX__/g, String(nextGalleryIndex));
							const wrapper = document.createElement('div');
							wrapper.innerHTML = html.trim();
							const galleryItem = wrapper.firstElementChild;
							if (!galleryItem) {
								return;
							}
							galleryList.appendChild(galleryItem);
							bindGalleryItem(galleryItem);
							refreshIndexes();
						});
					}
				};

				list.querySelectorAll('[data-aac-featured-photographer]').forEach(bindPhotographerCard);

				addButton.addEventListener('click', () => {
					const nextIndex = list.querySelectorAll('[data-aac-featured-photographer]').length;
					const html = template.innerHTML.replace(/__INDEX__/g, String(nextIndex));
					const wrapper = document.createElement('div');
					wrapper.innerHTML = html.trim();
					const card = wrapper.firstElementChild;
					if (!card) {
						return;
					}
					list.appendChild(card);
					bindPhotographerCard(card);
					refreshIndexes();
				});
			});
		</script>
		<?php
	}

	private function render_media_row($name, $label, $value, $help = '') {
		$field_id = sanitize_title($name);
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr($field_id); ?>"><?php echo esc_html($label); ?></label></th>
			<td>
				<div style="display:flex;gap:8px;align-items:center;max-width:720px;">
					<input type="url" id="<?php echo esc_attr($field_id); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>" class="large-text" data-aac-media-input />
					<button type="button" class="button button-secondary" data-aac-select-media data-target="<?php echo esc_attr($field_id); ?>">Select Media</button>
				</div>
				<?php if ($help) : ?>
					<p class="description"><?php echo esc_html($help); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	private function render_input_row($name, $label, $value, $type = 'text', $help = '', $min = null, $max = null, $step = null) {
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr($name); ?>"><?php echo esc_html($label); ?></label></th>
			<td>
				<input
					type="<?php echo esc_attr($type); ?>"
					id="<?php echo esc_attr($name); ?>"
					name="<?php echo esc_attr($name); ?>"
					value="<?php echo esc_attr($value); ?>"
					class="regular-text"
					<?php echo $min !== null ? 'min="' . esc_attr($min) . '"' : ''; ?>
					<?php echo $max !== null ? 'max="' . esc_attr($max) . '"' : ''; ?>
					<?php echo $step !== null ? 'step="' . esc_attr($step) . '"' : ''; ?>
				/>
				<?php if ($help) : ?>
					<p class="description"><?php echo esc_html($help); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	private function render_textarea_row($name, $label, $value) {
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr($name); ?>"><?php echo esc_html($label); ?></label></th>
			<td>
				<textarea id="<?php echo esc_attr($name); ?>" name="<?php echo esc_attr($name); ?>" rows="3" class="large-text"><?php echo esc_textarea($value); ?></textarea>
			</td>
		</tr>
		<?php
	}

	private function render_home_involvement_card_editor($index, $card = []) {
		$base_name = self::OPTION_KEY . '[content][home_involvement_cards][' . (int) $index . ']';
		?>
		<div class="aac-home-card-editor" data-aac-home-involvement-card>
			<div class="aac-home-card-editor__header">
				<h4>Involvement Card</h4>
				<button type="button" class="button-link-delete" data-aac-remove-home-card>Remove</button>
			</div>
			<div class="aac-home-card-editor__grid">
				<p><label><strong>Title</strong><br /><input type="text" class="regular-text" name="<?php echo esc_attr($base_name . '[title]'); ?>" value="<?php echo esc_attr($card['title'] ?? ''); ?>" /></label></p>
				<p><label><strong>Accent Style</strong><br />
					<select name="<?php echo esc_attr($base_name . '[accent_style]'); ?>">
						<?php foreach (['gold' => 'Gold', 'light' => 'Light', 'sand' => 'Sand', 'dark' => 'Dark'] as $style_value => $style_label) : ?>
							<option value="<?php echo esc_attr($style_value); ?>" <?php selected($card['accent_style'] ?? '', $style_value); ?>><?php echo esc_html($style_label); ?></option>
						<?php endforeach; ?>
					</select>
				</label></p>
				<p class="aac-home-card-editor__full"><label><strong>Description</strong><br /><textarea rows="3" class="large-text" name="<?php echo esc_attr($base_name . '[description]'); ?>"><?php echo esc_textarea($card['description'] ?? ''); ?></textarea></label></p>
				<p><label><strong>Button Label</strong><br /><input type="text" class="regular-text" name="<?php echo esc_attr($base_name . '[button_label]'); ?>" value="<?php echo esc_attr($card['button_label'] ?? ''); ?>" /></label></p>
				<p><label><strong>Button URL</strong><br /><input type="url" class="large-text" name="<?php echo esc_attr($base_name . '[button_url]'); ?>" value="<?php echo esc_attr($card['button_url'] ?? ''); ?>" /></label></p>
				<div class="aac-home-card-editor__full">
					<label><strong>Image URL</strong><br /><input type="url" class="large-text aac-home-card-editor__image-input" name="<?php echo esc_attr($base_name . '[image_url]'); ?>" value="<?php echo esc_attr($card['image_url'] ?? ''); ?>" /></label>
					<p style="margin:8px 0 0;"><button type="button" class="button button-secondary" data-aac-select-home-image>Select Image</button></p>
					<div class="aac-home-card-editor__preview"><?php if (!empty($card['image_url'])) : ?><img src="<?php echo esc_url($card['image_url']); ?>" alt="" /><?php endif; ?></div>
				</div>
			</div>
		</div>
		<?php
	}

	private function render_home_publication_card_editor($index, $card = []) {
		$base_name = self::OPTION_KEY . '[content][home_publication_cards][' . (int) $index . ']';
		?>
		<div class="aac-home-card-editor" data-aac-home-publication-card>
			<div class="aac-home-card-editor__header">
				<h4>Publication Card</h4>
				<button type="button" class="button-link-delete" data-aac-remove-home-card>Remove</button>
			</div>
			<div class="aac-home-card-editor__grid">
				<p><label><strong>Title</strong><br /><input type="text" class="regular-text" name="<?php echo esc_attr($base_name . '[title]'); ?>" value="<?php echo esc_attr($card['title'] ?? ''); ?>" /></label></p>
				<p><label><strong>Accent Color</strong><br /><input type="text" class="regular-text" name="<?php echo esc_attr($base_name . '[accent_color]'); ?>" value="<?php echo esc_attr($card['accent_color'] ?? ''); ?>" placeholder="#f8c235" /></label></p>
				<p class="aac-home-card-editor__full"><label><strong>Description</strong><br /><textarea rows="3" class="large-text" name="<?php echo esc_attr($base_name . '[description]'); ?>"><?php echo esc_textarea($card['description'] ?? ''); ?></textarea></label></p>
				<p><label><strong>Button Label</strong><br /><input type="text" class="regular-text" name="<?php echo esc_attr($base_name . '[button_label]'); ?>" value="<?php echo esc_attr($card['button_label'] ?? ''); ?>" /></label></p>
				<p><label><strong>Button URL</strong><br /><input type="url" class="large-text" name="<?php echo esc_attr($base_name . '[button_url]'); ?>" value="<?php echo esc_attr($card['button_url'] ?? ''); ?>" /></label></p>
				<div class="aac-home-card-editor__full">
					<label><strong>Image URL</strong><br /><input type="url" class="large-text aac-home-card-editor__image-input" name="<?php echo esc_attr($base_name . '[image_url]'); ?>" value="<?php echo esc_attr($card['image_url'] ?? ''); ?>" /></label>
					<p style="margin:8px 0 0;"><button type="button" class="button button-secondary" data-aac-select-home-image>Select Image</button></p>
					<div class="aac-home-card-editor__preview"><?php if (!empty($card['image_url'])) : ?><img src="<?php echo esc_url($card['image_url']); ?>" alt="" /><?php endif; ?></div>
				</div>
			</div>
		</div>
		<?php
	}

	private function render_home_partner_logo_editor($index, $logo = []) {
		$base_name = self::OPTION_KEY . '[content][home_partner_logos][' . (int) $index . ']';
		?>
		<div class="aac-home-card-editor" data-aac-home-partner-logo>
			<div class="aac-home-card-editor__header">
				<h4>Partner Logo</h4>
				<button type="button" class="button-link-delete" data-aac-remove-home-card>Remove</button>
			</div>
			<div class="aac-home-card-editor__grid">
				<p><label><strong>Name</strong><br /><input type="text" class="regular-text" name="<?php echo esc_attr($base_name . '[name]'); ?>" value="<?php echo esc_attr($logo['name'] ?? ''); ?>" /></label></p>
				<p><label><strong>Link URL</strong><br /><input type="url" class="large-text" name="<?php echo esc_attr($base_name . '[link_url]'); ?>" value="<?php echo esc_attr($logo['link_url'] ?? ''); ?>" /></label></p>
				<div class="aac-home-card-editor__full">
					<label><strong>Logo Image URL</strong><br /><input type="url" class="large-text aac-home-card-editor__image-input" name="<?php echo esc_attr($base_name . '[image_url]'); ?>" value="<?php echo esc_attr($logo['image_url'] ?? ''); ?>" /></label>
					<p style="margin:8px 0 0;"><button type="button" class="button button-secondary" data-aac-select-home-image>Select Image</button></p>
					<div class="aac-home-card-editor__preview"><?php if (!empty($logo['image_url'])) : ?><img src="<?php echo esc_url($logo['image_url']); ?>" alt="" /><?php endif; ?></div>
				</div>
			</div>
		</div>
		<?php
	}

	private function render_grant_opportunity_editor($index, $opportunity = []) {
		$base_name = self::OPTION_KEY . '[content][grant_opportunities][' . (int) $index . ']';
		$highlights = isset($opportunity['highlights']) && is_array($opportunity['highlights'])
			? implode("\n", array_filter(array_map('sanitize_text_field', $opportunity['highlights'])))
			: '';
		?>
		<div class="aac-home-card-editor" data-aac-grant-opportunity>
			<div class="aac-home-card-editor__header">
				<h4>Grant Opportunity</h4>
				<button type="button" class="button-link-delete" data-aac-remove-home-card>Remove</button>
			</div>
			<div class="aac-home-card-editor__grid">
				<p><label><strong>Grant name</strong><br /><input type="text" class="regular-text" name="<?php echo esc_attr($base_name . '[name]'); ?>" value="<?php echo esc_attr($opportunity['name'] ?? ''); ?>" /></label></p>
				<p><label><strong>Slug</strong><br /><input type="text" class="regular-text" name="<?php echo esc_attr($base_name . '[slug]'); ?>" value="<?php echo esc_attr($opportunity['slug'] ?? ''); ?>" placeholder="momentum-grant" /></label></p>
				<p><label><strong>Category</strong><br /><input type="text" class="regular-text" name="<?php echo esc_attr($base_name . '[category]'); ?>" value="<?php echo esc_attr($opportunity['category'] ?? ''); ?>" /></label></p>
				<p><label><strong>Award label</strong><br /><input type="text" class="regular-text" name="<?php echo esc_attr($base_name . '[award]'); ?>" value="<?php echo esc_attr($opportunity['award'] ?? ''); ?>" placeholder="Up to $5,000" /></label></p>
				<p class="aac-home-card-editor__full"><label><strong>Fit guidance</strong><br /><textarea rows="2" class="large-text" name="<?php echo esc_attr($base_name . '[fit]'); ?>"><?php echo esc_textarea($opportunity['fit'] ?? ''); ?></textarea></label></p>
				<p class="aac-home-card-editor__full"><label><strong>Summary</strong><br /><textarea rows="3" class="large-text" name="<?php echo esc_attr($base_name . '[summary]'); ?>"><?php echo esc_textarea($opportunity['summary'] ?? ''); ?></textarea></label></p>
				<p class="aac-home-card-editor__full"><label><strong>Highlights</strong><br /><textarea rows="4" class="large-text" name="<?php echo esc_attr($base_name . '[highlights]'); ?>" placeholder="One highlight per line"><?php echo esc_textarea($highlights); ?></textarea></label></p>
				<p class="aac-home-card-editor__full"><label><strong>Source URL</strong><br /><input type="url" class="large-text" name="<?php echo esc_attr($base_name . '[source_url]'); ?>" value="<?php echo esc_attr($opportunity['source_url'] ?? ''); ?>" /></label></p>
			</div>
		</div>
		<?php
	}

	private function render_grant_form_field_editor($index, $field = []) {
		$base_name = self::OPTION_KEY . '[content][grant_form_fields][' . (int) $index . ']';
		$type = sanitize_key($field['type'] ?? 'text');
		if (!in_array($type, ['text', 'email', 'number', 'textarea', 'select'], true)) {
			$type = 'text';
		}
		?>
		<div class="aac-home-card-editor" data-aac-grant-form-field>
			<div class="aac-home-card-editor__header">
				<h4>Grant Field</h4>
				<button type="button" class="button-link-delete" data-aac-remove-home-card>Remove</button>
			</div>
			<div class="aac-home-card-editor__grid">
				<p><label><strong>Field key</strong><br /><input type="text" class="regular-text" name="<?php echo esc_attr($base_name . '[field_key]'); ?>" value="<?php echo esc_attr($field['field_key'] ?? ''); ?>" placeholder="project_title" /></label></p>
				<p><label><strong>Label</strong><br /><input type="text" class="regular-text" name="<?php echo esc_attr($base_name . '[label]'); ?>" value="<?php echo esc_attr($field['label'] ?? ''); ?>" /></label></p>
				<p><label><strong>Field type</strong><br />
					<select name="<?php echo esc_attr($base_name . '[type]'); ?>">
						<?php foreach (['text' => 'Text', 'email' => 'Email', 'number' => 'Number', 'textarea' => 'Textarea', 'select' => 'Select'] as $type_value => $type_label) : ?>
							<option value="<?php echo esc_attr($type_value); ?>" <?php selected($type, $type_value); ?>><?php echo esc_html($type_label); ?></option>
						<?php endforeach; ?>
					</select>
				</label></p>
				<p><label><strong>Required</strong><br /><label><input type="checkbox" name="<?php echo esc_attr($base_name . '[required]'); ?>" value="1" <?php checked(!empty($field['required'])); ?> /> Required field</label></label></p>
				<p class="aac-home-card-editor__full"><label><strong>Placeholder</strong><br /><input type="text" class="large-text" name="<?php echo esc_attr($base_name . '[placeholder]'); ?>" value="<?php echo esc_attr($field['placeholder'] ?? ''); ?>" /></label></p>
				<p class="aac-home-card-editor__full"><label><strong>Help text</strong><br /><textarea rows="2" class="large-text" name="<?php echo esc_attr($base_name . '[help_text]'); ?>"><?php echo esc_textarea($field['help_text'] ?? ''); ?></textarea></label></p>
				<p class="aac-home-card-editor__full"><label><strong>Select options</strong><br /><textarea rows="3" class="large-text" name="<?php echo esc_attr($base_name . '[options]'); ?>" placeholder="One option per line"><?php echo esc_textarea($field['options'] ?? ''); ?></textarea></label></p>
			</div>
		</div>
		<?php
	}

	private function render_member_profile_block_editor($index, $block = []) {
		$base_name = self::OPTION_KEY . '[content][member_profile_blocks][' . $index . ']';
		$entries = isset($block['entries']) && is_array($block['entries']) ? array_values($block['entries']) : [];
		$icon = sanitize_key($block['icon'] ?? 'receipt');
		if (!in_array($icon, ['receipt', 'user', 'shield', 'users', 'heart', 'credit-card', 'calendar'], true)) {
			$icon = 'receipt';
		}
		?>
		<div class="aac-home-card-editor" data-aac-member-profile-block>
			<div class="aac-home-card-editor__header">
				<h4>Member Profile Block</h4>
				<button type="button" class="button-link-delete" data-aac-remove-profile-block>Remove</button>
			</div>
			<div class="aac-home-card-editor__grid">
				<p><label><strong>Title</strong><br /><input type="text" class="regular-text" name="<?php echo esc_attr($base_name . '[title]'); ?>" value="<?php echo esc_attr($block['title'] ?? ''); ?>" /></label></p>
				<p><label><strong>Icon</strong><br />
					<select name="<?php echo esc_attr($base_name . '[icon]'); ?>">
						<?php foreach (['receipt' => 'Receipt', 'user' => 'User', 'shield' => 'Shield', 'users' => 'Users', 'heart' => 'Heart', 'credit-card' => 'Credit Card', 'calendar' => 'Calendar'] as $icon_value => $icon_label) : ?>
							<option value="<?php echo esc_attr($icon_value); ?>" <?php selected($icon, $icon_value); ?>><?php echo esc_html($icon_label); ?></option>
						<?php endforeach; ?>
					</select>
				</label></p>
				<p class="aac-home-card-editor__full"><label><strong>Description</strong><br /><textarea rows="2" class="large-text" name="<?php echo esc_attr($base_name . '[description]'); ?>"><?php echo esc_textarea($block['description'] ?? ''); ?></textarea></label></p>
				<p><label><strong>Button label</strong><br /><input type="text" class="regular-text" name="<?php echo esc_attr($base_name . '[button_label]'); ?>" value="<?php echo esc_attr($block['button_label'] ?? ''); ?>" /></label></p>
				<p><label><strong>Button URL</strong><br /><input type="url" class="regular-text" name="<?php echo esc_attr($base_name . '[button_url]'); ?>" value="<?php echo esc_attr($block['button_url'] ?? ''); ?>" /></label></p>
			</div>

			<div class="aac-profile-block-entries">
				<h5 style="margin:18px 0 10px;">Entries</h5>
				<div class="aac-profile-block-entries__list" data-aac-profile-entry-list>
					<?php foreach ($entries as $entry_index => $entry) : ?>
						<?php $this->render_member_profile_block_entry_editor($index, $entry_index, $entry); ?>
					<?php endforeach; ?>
				</div>
				<p style="margin-top:12px;">
					<button type="button" class="button button-secondary" data-aac-add-profile-entry>Add Entry</button>
				</p>
			</div>
		</div>
		<?php
	}

	private function render_member_profile_block_entry_editor($block_index, $entry_index, $entry = []) {
		$base_name = self::OPTION_KEY . '[content][member_profile_blocks][' . $block_index . '][entries][' . $entry_index . ']';
		?>
		<div class="aac-profile-entry-editor" data-aac-member-profile-entry>
			<div class="aac-profile-entry-editor__header">
				<h5>Entry</h5>
				<button type="button" class="button-link-delete" data-aac-remove-profile-entry>Remove</button>
			</div>
			<div class="aac-home-card-editor__grid">
				<p><label><strong>Label</strong><br /><input type="text" class="regular-text" name="<?php echo esc_attr($base_name . '[label]'); ?>" value="<?php echo esc_attr($entry['label'] ?? ''); ?>" /></label></p>
				<p><label><strong>Value</strong><br /><input type="text" class="regular-text" name="<?php echo esc_attr($base_name . '[value]'); ?>" value="<?php echo esc_attr($entry['value'] ?? ''); ?>" /></label></p>
				<p class="aac-home-card-editor__full"><label><strong>Description</strong><br /><textarea rows="2" class="large-text" name="<?php echo esc_attr($base_name . '[description]'); ?>"><?php echo esc_textarea($entry['description'] ?? ''); ?></textarea></label></p>
			</div>
		</div>
		<?php
	}

	private function render_home_repeater_templates() {
		ob_start();
		$this->render_home_involvement_card_editor('__INDEX__', []);
		$involvement_template = ob_get_clean();
		ob_start();
		$this->render_home_publication_card_editor('__INDEX__', []);
		$publication_template = ob_get_clean();
		ob_start();
		$this->render_home_partner_logo_editor('__INDEX__', []);
		$partner_template = ob_get_clean();
		ob_start();
		$this->render_grant_opportunity_editor('__INDEX__', []);
		$grant_opportunity_template = ob_get_clean();
		ob_start();
		$this->render_grant_form_field_editor('__INDEX__', []);
		$grant_form_field_template = ob_get_clean();
		?>
		<template id="aac-home-involvement-card-template"><?php echo str_replace('__INDEX__', '__INDEX__', $involvement_template); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></template>
		<template id="aac-home-publication-card-template"><?php echo str_replace('__INDEX__', '__INDEX__', $publication_template); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></template>
		<template id="aac-home-partner-logo-template"><?php echo str_replace('__INDEX__', '__INDEX__', $partner_template); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></template>
		<template id="aac-grant-opportunity-template"><?php echo str_replace('__INDEX__', '__INDEX__', $grant_opportunity_template); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></template>
		<template id="aac-grant-form-field-template"><?php echo str_replace('__INDEX__', '__INDEX__', $grant_form_field_template); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></template>
		<style>
			.aac-home-card-editor{border:1px solid #dcdcde;border-radius:12px;padding:16px;background:#fff;margin-bottom:16px}
			.aac-home-card-editor__header{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:12px}
			.aac-home-card-editor__header h4{margin:0}
			.aac-home-card-editor__grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
			.aac-home-card-editor__full{grid-column:1 / -1}
			.aac-home-card-editor__preview{margin-top:12px;min-height:64px}
			.aac-home-card-editor__preview img{display:block;max-width:220px;width:100%;height:auto;border-radius:8px;border:1px solid #dcdcde}
			@media (max-width: 782px){.aac-home-card-editor__grid{grid-template-columns:1fr}}
		</style>
		<?php
	}

	private function render_member_profile_block_templates() {
		ob_start();
		$this->render_member_profile_block_editor('__INDEX__', []);
		$block_template = ob_get_clean();
		ob_start();
		$this->render_member_profile_block_entry_editor('__INDEX__', '__ENTRY_INDEX__', []);
		$entry_template = ob_get_clean();
		?>
		<template id="aac-member-profile-block-template"><?php echo str_replace('__INDEX__', '__INDEX__', $block_template); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></template>
		<template id="aac-member-profile-entry-template"><?php echo str_replace(['__INDEX__', '__ENTRY_INDEX__'], ['__INDEX__', '__ENTRY_INDEX__'], $entry_template); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></template>
		<style>
			.aac-profile-block-entries{margin-top:18px;border-top:1px solid #dcdcde;padding-top:16px}
			.aac-profile-entry-editor{border:1px solid #dcdcde;border-radius:12px;padding:14px;background:#f8f8f8;margin-bottom:12px}
			.aac-profile-entry-editor__header{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:10px}
			.aac-profile-entry-editor__header h5{margin:0}
		</style>
		<?php
	}

	private function render_grants_builder_templates() {
		$this->render_home_repeater_templates();
	}

	private function render_shared_admin_scripts() {
		?>
		<script>
			document.addEventListener('DOMContentLoaded', function () {
				const openMediaFrame = (onSelect) => {
					if (!(window.wp && window.wp.media)) {
						return;
					}
					const frame = window.wp.media({
						title: 'Select media',
						button: { text: 'Use media' },
						multiple: false,
					});
					frame.on('select', () => {
						const attachment = frame.state().get('selection').first().toJSON();
						onSelect(attachment);
					});
					frame.open();
				};

				document.querySelectorAll('[data-aac-select-media]').forEach((button) => {
					button.addEventListener('click', () => {
						const target = document.getElementById(button.getAttribute('data-target'));
						if (!target) {
							return;
						}
						openMediaFrame((attachment) => {
							target.value = attachment.url || '';
							target.dispatchEvent(new Event('input', { bubbles: true }));
						});
					});
				});

				const bindImagePickerList = ({ listId, addButtonId, templateId, marker, replacePattern }) => {
					const list = document.getElementById(listId);
					const template = document.getElementById(templateId);
					const addButton = document.getElementById(addButtonId);
					if (!list || !template || !addButton) {
						return;
					}

					const refreshIndexes = () => {
						list.querySelectorAll(marker).forEach((card, index) => {
							card.querySelectorAll('[name]').forEach((field) => {
								field.name = field.name.replace(replacePattern, '$1[' + index + ']');
							});
						});
					};

					const updatePreview = (card) => {
						const input = card.querySelector('.aac-home-card-editor__image-input');
						const preview = card.querySelector('.aac-home-card-editor__preview');
						if (!input || !preview) {
							return;
						}
						const nextUrl = String(input.value || '').trim();
						preview.innerHTML = nextUrl ? '<img src="' + nextUrl.replace(/"/g, '&quot;') + '" alt="" />' : '';
					};

					const bindCard = (card) => {
						const removeButton = card.querySelector('[data-aac-remove-home-card]');
						const imageInput = card.querySelector('.aac-home-card-editor__image-input');
						const selectButton = card.querySelector('[data-aac-select-home-image]');

						if (removeButton) {
							removeButton.addEventListener('click', () => {
								card.remove();
								refreshIndexes();
							});
						}

						if (imageInput) {
							imageInput.addEventListener('input', () => updatePreview(card));
						}

						if (selectButton) {
							selectButton.addEventListener('click', () => {
								openMediaFrame((attachment) => {
									if (imageInput) {
										imageInput.value = attachment.url || '';
										updatePreview(card);
									}
								});
							});
						}
					};

					list.querySelectorAll(marker).forEach(bindCard);
					addButton.addEventListener('click', () => {
						const nextIndex = list.querySelectorAll(marker).length;
						const html = template.innerHTML.replace(/__INDEX__/g, String(nextIndex));
						const wrapper = document.createElement('div');
						wrapper.innerHTML = html.trim();
						const card = wrapper.firstElementChild;
						if (!card) {
							return;
						}
						list.appendChild(card);
						bindCard(card);
						refreshIndexes();
					});
				};

				bindImagePickerList({
					listId: 'aac-home-involvement-cards',
					addButtonId: 'aac-add-home-involvement-card',
					templateId: 'aac-home-involvement-card-template',
					marker: '[data-aac-home-involvement-card]',
					replacePattern: /(\[home_involvement_cards\])\[[^\]]+\]/,
				});
				bindImagePickerList({
					listId: 'aac-home-publication-cards',
					addButtonId: 'aac-add-home-publication-card',
					templateId: 'aac-home-publication-card-template',
					marker: '[data-aac-home-publication-card]',
					replacePattern: /(\[home_publication_cards\])\[[^\]]+\]/,
				});
				bindImagePickerList({
					listId: 'aac-home-partner-logos',
					addButtonId: 'aac-add-home-partner-logo',
					templateId: 'aac-home-partner-logo-template',
					marker: '[data-aac-home-partner-logo]',
					replacePattern: /(\[home_partner_logos\])\[[^\]]+\]/,
				});
				bindImagePickerList({
					listId: 'aac-grant-opportunities',
					addButtonId: 'aac-add-grant-opportunity',
					templateId: 'aac-grant-opportunity-template',
					marker: '[data-aac-grant-opportunity]',
					replacePattern: /(\[grant_opportunities\])\[[^\]]+\]/,
				});
				bindImagePickerList({
					listId: 'aac-grant-form-fields',
					addButtonId: 'aac-add-grant-form-field',
					templateId: 'aac-grant-form-field-template',
					marker: '[data-aac-grant-form-field]',
					replacePattern: /(\[grant_form_fields\])\[[^\]]+\]/,
				});

				const bindMemberProfileBlocks = () => {
					const list = document.getElementById('aac-member-profile-blocks');
					const template = document.getElementById('aac-member-profile-block-template');
					const entryTemplate = document.getElementById('aac-member-profile-entry-template');
					const addButton = document.getElementById('aac-add-member-profile-block');
					if (!list || !template || !entryTemplate || !addButton) {
						return;
					}

					const refreshIndexes = () => {
						list.querySelectorAll('[data-aac-member-profile-block]').forEach((block, blockIndex) => {
							block.querySelectorAll('[name]').forEach((field) => {
								field.name = field.name.replace(/(\[member_profile_blocks\])\[[^\]]+\]/, '$1[' + blockIndex + ']');
							});
							block.querySelectorAll('[data-aac-member-profile-entry]').forEach((entry, entryIndex) => {
								entry.querySelectorAll('[name]').forEach((field) => {
									field.name = field.name.replace(/(\[entries\])\[[^\]]+\]/, '$1[' + entryIndex + ']');
								});
							});
						});
					};

					const bindEntry = (entry) => {
						const removeButton = entry.querySelector('[data-aac-remove-profile-entry]');
						if (removeButton) {
							removeButton.addEventListener('click', () => {
								const block = entry.closest('[data-aac-member-profile-block]');
								entry.remove();
								if (block) {
									refreshIndexes();
								}
							});
						}
					};

					const addEntryToBlock = (block) => {
						const entryList = block.querySelector('[data-aac-profile-entry-list]');
						if (!entryList) {
							return;
						}
						const nextEntryIndex = entryList.querySelectorAll('[data-aac-member-profile-entry]').length;
						const html = entryTemplate.innerHTML
							.replace(/__INDEX__/g, '__INDEX__')
							.replace(/__ENTRY_INDEX__/g, String(nextEntryIndex));
						const wrapper = document.createElement('div');
						wrapper.innerHTML = html.trim();
						const entry = wrapper.firstElementChild;
						if (!entry) {
							return;
						}
						entryList.appendChild(entry);
						bindEntry(entry);
						refreshIndexes();
					};

					const bindBlock = (block) => {
						const removeButton = block.querySelector('[data-aac-remove-profile-block]');
						const addEntryButton = block.querySelector('[data-aac-add-profile-entry]');
						if (removeButton) {
							removeButton.addEventListener('click', () => {
								block.remove();
								refreshIndexes();
							});
						}
						if (addEntryButton) {
							addEntryButton.addEventListener('click', () => addEntryToBlock(block));
						}
						block.querySelectorAll('[data-aac-member-profile-entry]').forEach(bindEntry);
					};

					list.querySelectorAll('[data-aac-member-profile-block]').forEach(bindBlock);
					addButton.addEventListener('click', () => {
						const nextIndex = list.querySelectorAll('[data-aac-member-profile-block]').length;
						const html = template.innerHTML
							.replace(/__INDEX__/g, String(nextIndex))
							.replace(/__ENTRY_INDEX__/g, '0');
						const wrapper = document.createElement('div');
						wrapper.innerHTML = html.trim();
						const block = wrapper.firstElementChild;
						if (!block) {
							return;
						}
						list.appendChild(block);
						bindBlock(block);
						refreshIndexes();
					});
				};

				bindMemberProfileBlocks();
			});
		</script>
		<?php
	}

	private function sanitize_discount_cards($cards) {
		$sanitized_cards = [];
		foreach ($cards as $card) {
			if (!is_array($card)) {
				continue;
			}

			$brand = sanitize_text_field($card['brand'] ?? '');
			$discount_percent = sanitize_text_field($card['discount_percent'] ?? '');
			$discount_code_text = sanitize_textarea_field($card['discount_code_text'] ?? '');
			$discount_code_text_supporter = sanitize_textarea_field($card['discount_code_text_supporter'] ?? '');
			$discount_code_text_partner = sanitize_textarea_field($card['discount_code_text_partner'] ?? '');
			$discount_code_text_leader = sanitize_textarea_field($card['discount_code_text_leader'] ?? '');
			$discount_code_text_advocate = sanitize_textarea_field($card['discount_code_text_advocate'] ?? '');
			$discount_percent_supporter = sanitize_text_field($card['discount_percent_supporter'] ?? '');
			$discount_percent_partner = sanitize_text_field($card['discount_percent_partner'] ?? '');
			$discount_percent_leader = sanitize_text_field($card['discount_percent_leader'] ?? '');
			$discount_percent_advocate = sanitize_text_field($card['discount_percent_advocate'] ?? '');
			$display_text = sanitize_textarea_field($card['display_text'] ?? '');
			$button_url = esc_url_raw($card['button_url'] ?? '');
			$image_url = esc_url_raw($card['image_url'] ?? '');

			if (
				$brand === '' &&
				$discount_percent === '' &&
				$discount_code_text === '' &&
				$discount_code_text_supporter === '' &&
				$discount_code_text_partner === '' &&
				$discount_code_text_leader === '' &&
				$discount_code_text_advocate === '' &&
				$discount_percent_supporter === '' &&
				$discount_percent_partner === '' &&
				$discount_percent_leader === '' &&
				$discount_percent_advocate === '' &&
				$display_text === '' &&
				$button_url === '' &&
				$image_url === ''
			) {
				continue;
			}

			$fallback_percent = $discount_percent;

			$sanitized_cards[] = [
				'brand' => $brand,
				'discount_percent' => $fallback_percent,
				'discount_code_text' => $discount_code_text,
				'discount_code_text_supporter' => $discount_code_text_supporter !== '' ? $discount_code_text_supporter : $discount_code_text,
				'discount_code_text_partner' => $discount_code_text_partner !== '' ? $discount_code_text_partner : $discount_code_text,
				'discount_code_text_leader' => $discount_code_text_leader !== '' ? $discount_code_text_leader : $discount_code_text,
				'discount_code_text_advocate' => $discount_code_text_advocate !== '' ? $discount_code_text_advocate : $discount_code_text,
				'discount_percent_supporter' => $discount_percent_supporter !== '' ? $discount_percent_supporter : $fallback_percent,
				'discount_percent_partner' => $discount_percent_partner !== '' ? $discount_percent_partner : $fallback_percent,
				'discount_percent_leader' => $discount_percent_leader !== '' ? $discount_percent_leader : $fallback_percent,
				'discount_percent_advocate' => $discount_percent_advocate !== '' ? $discount_percent_advocate : $fallback_percent,
				'display_text' => $display_text,
				'button_url' => $button_url,
				'image_url' => $image_url,
			];
		}

		return $sanitized_cards;
	}

	private function sanitize_home_involvement_cards($cards) {
		$sanitized = [];
		foreach ($cards as $card) {
			if (!is_array($card)) {
				continue;
			}

			$title = sanitize_text_field($card['title'] ?? '');
			$description = sanitize_textarea_field($card['description'] ?? '');
			$button_label = sanitize_text_field($card['button_label'] ?? '');
			$button_url = esc_url_raw($card['button_url'] ?? '');
			$image_url = esc_url_raw($card['image_url'] ?? '');
			$accent_style = sanitize_key($card['accent_style'] ?? 'gold');
			if (!in_array($accent_style, ['gold', 'light', 'sand', 'dark'], true)) {
				$accent_style = 'gold';
			}

			if ($title === '' && $description === '' && $button_label === '' && $button_url === '' && $image_url === '') {
				continue;
			}

			$sanitized[] = [
				'title' => $title,
				'description' => $description,
				'button_label' => $button_label,
				'button_url' => $button_url,
				'image_url' => $image_url,
				'accent_style' => $accent_style,
			];
		}

		return !empty($sanitized) ? $sanitized : self::get_default_home_involvement_cards();
	}

	private function sanitize_home_publication_cards($cards) {
		$sanitized = [];
		foreach ($cards as $card) {
			if (!is_array($card)) {
				continue;
			}

			$title = sanitize_text_field($card['title'] ?? '');
			$description = sanitize_textarea_field($card['description'] ?? '');
			$button_label = sanitize_text_field($card['button_label'] ?? '');
			$button_url = esc_url_raw($card['button_url'] ?? '');
			$image_url = esc_url_raw($card['image_url'] ?? '');
			$accent_color = sanitize_text_field($card['accent_color'] ?? '');

			if ($title === '' && $description === '' && $button_label === '' && $button_url === '' && $image_url === '') {
				continue;
			}

			$sanitized[] = [
				'title' => $title,
				'description' => $description,
				'button_label' => $button_label,
				'button_url' => $button_url,
				'image_url' => $image_url,
				'accent_color' => $accent_color,
			];
		}

		return !empty($sanitized) ? $sanitized : self::get_default_home_publication_cards();
	}

	private function sanitize_home_partner_logos($logos) {
		$sanitized = [];
		foreach ($logos as $logo) {
			if (!is_array($logo)) {
				continue;
			}

			$name = sanitize_text_field($logo['name'] ?? '');
			$image_url = esc_url_raw($logo['image_url'] ?? '');
			$link_url = esc_url_raw($logo['link_url'] ?? '');

			if ($name === '' && $image_url === '' && $link_url === '') {
				continue;
			}

			$sanitized[] = [
				'name' => $name,
				'image_url' => $image_url,
				'link_url' => $link_url,
			];
		}

		return !empty($sanitized) ? $sanitized : self::get_default_home_partner_logos();
	}

	private function sanitize_grant_opportunities($opportunities) {
		$sanitized = [];
		foreach ($opportunities as $opportunity) {
			if (!is_array($opportunity)) {
				continue;
			}

			$name = sanitize_text_field($opportunity['name'] ?? '');
			$slug = sanitize_title($opportunity['slug'] ?? '');
			$category = sanitize_text_field($opportunity['category'] ?? '');
			$award = sanitize_text_field($opportunity['award'] ?? '');
			$fit = sanitize_textarea_field($opportunity['fit'] ?? '');
			$summary = sanitize_textarea_field($opportunity['summary'] ?? '');
			$source_url = esc_url_raw($opportunity['source_url'] ?? '');
			$raw_highlights = preg_split('/\r\n|\r|\n/', (string) ($opportunity['highlights'] ?? ''));
			$highlights = array_values(array_filter(array_map('sanitize_text_field', is_array($raw_highlights) ? $raw_highlights : [])));

			if ($name === '' && $slug === '' && $category === '' && $award === '' && $fit === '' && $summary === '' && empty($highlights) && $source_url === '') {
				continue;
			}

			if ($slug === '') {
				$slug = sanitize_title($name);
			}

			if ($name === '' || $slug === '') {
				continue;
			}

			$sanitized[] = [
				'slug' => $slug,
				'name' => $name,
				'category' => $category,
				'award' => $award,
				'fit' => $fit,
				'summary' => $summary,
				'highlights' => $highlights,
				'source_url' => $source_url,
			];
		}

		return !empty($sanitized) ? $sanitized : self::get_default_grant_opportunities();
	}

	private function sanitize_member_profile_blocks($blocks) {
		$sanitized = [];
		foreach ($blocks as $block) {
			if (!is_array($block)) {
				continue;
			}

			$title = sanitize_text_field($block['title'] ?? '');
			$description = sanitize_textarea_field($block['description'] ?? '');
			$button_label = sanitize_text_field($block['button_label'] ?? '');
			$button_url = esc_url_raw($block['button_url'] ?? '');
			$icon = sanitize_key($block['icon'] ?? 'receipt');
			if (!in_array($icon, ['receipt', 'user', 'shield', 'users', 'heart', 'credit-card', 'calendar'], true)) {
				$icon = 'receipt';
			}

			$entries = [];
			if (isset($block['entries']) && is_array($block['entries'])) {
				foreach ($block['entries'] as $entry) {
					if (!is_array($entry)) {
						continue;
					}

					$label = sanitize_text_field($entry['label'] ?? '');
					$value = sanitize_text_field($entry['value'] ?? '');
					$entry_description = sanitize_textarea_field($entry['description'] ?? '');
					if ($label === '' && $value === '' && $entry_description === '') {
						continue;
					}

					$entries[] = [
						'label' => $label,
						'value' => $value,
						'description' => $entry_description,
					];
				}
			}

			if ($title === '' && $description === '' && $button_label === '' && $button_url === '' && empty($entries)) {
				continue;
			}

			$sanitized[] = [
				'title' => $title,
				'description' => $description,
				'button_label' => $button_label,
				'button_url' => $button_url,
				'icon' => $icon,
				'entries' => $entries,
			];
		}

		return $sanitized;
	}

	private function sanitize_member_profile_card_sections($sections) {
		$sanitized = self::get_default_member_profile_card_sections();

		foreach ($sanitized as $section_id => $defaults) {
			$section_input = isset($sections[$section_id]) && is_array($sections[$section_id]) ? $sections[$section_id] : [];
			$sanitized[$section_id] = [
				'label' => sanitize_text_field($section_input['label'] ?? $defaults['label']),
				'visible' => empty($section_input['visible']) ? 0 : 1,
			];
		}

		return $sanitized;
	}

	private function sanitize_top_nav_children($children_input) {
		if (is_string($children_input)) {
			$children_input = $this->parse_top_nav_children_textarea($children_input);
		}

		if (!is_array($children_input)) {
			return [];
		}

		$sanitized = [];
		foreach ($children_input as $child) {
			if (!is_array($child)) {
				continue;
			}

			$label = sanitize_text_field($child['label'] ?? '');
			$href = esc_url_raw($child['href'] ?? '');
			$external = !empty($child['external']) ? 1 : 0;

			if ($label === '' || $href === '') {
				continue;
			}

			$sanitized[] = [
				'label' => $label,
				'href' => $href,
				'external' => $external,
			];
		}

		return $sanitized;
	}

	private function parse_top_nav_children_textarea($value) {
		$lines = preg_split('/\r\n|\r|\n/', (string) $value);
		$children = [];

		foreach ((array) $lines as $line) {
			$line = trim((string) $line);
			if ($line === '') {
				continue;
			}

			$parts = array_map('trim', explode('|', $line));
			$label = $parts[0] ?? '';
			$href = $parts[1] ?? '';
			$external_flag = strtolower($parts[2] ?? '');

			if ($label === '' || $href === '') {
				continue;
			}

			$children[] = [
				'label' => $label,
				'href' => $href,
				'external' => in_array($external_flag, ['1', 'yes', 'true', 'external'], true) ? 1 : 0,
			];
		}

		return $children;
	}

	private function format_top_nav_children_for_textarea($children) {
		if (!is_array($children) || empty($children)) {
			return '';
		}

		$lines = [];
		foreach ($children as $child) {
			if (!is_array($child)) {
				continue;
			}

			$label = sanitize_text_field($child['label'] ?? '');
			$href = esc_url_raw($child['href'] ?? '');
			if ($label === '' || $href === '') {
				continue;
			}

			$line = $label . ' | ' . $href;
			if (!empty($child['external'])) {
				$line .= ' | external';
			}
			$lines[] = $line;
		}

		return implode("\n", $lines);
	}

	private function sanitize_grant_form_fields($fields) {
		$sanitized = [];
		foreach ($fields as $field) {
			if (!is_array($field)) {
				continue;
			}

			$field_key = sanitize_key($field['field_key'] ?? '');
			$label = sanitize_text_field($field['label'] ?? '');
			$type = sanitize_key($field['type'] ?? 'text');
			$required = !empty($field['required']) ? 1 : 0;
			$placeholder = sanitize_text_field($field['placeholder'] ?? '');
			$help_text = sanitize_textarea_field($field['help_text'] ?? '');
			$options_lines = preg_split('/\r\n|\r|\n/', (string) ($field['options'] ?? ''));
			$options = array_values(array_filter(array_map('sanitize_text_field', is_array($options_lines) ? $options_lines : [])));

			if (!in_array($type, ['text', 'email', 'number', 'textarea', 'select'], true)) {
				$type = 'text';
			}

			if ($field_key === '' && $label === '' && $placeholder === '' && $help_text === '' && empty($options)) {
				continue;
			}

			if ($field_key === '') {
				$field_key = sanitize_key(str_replace('-', '_', sanitize_title($label)));
			}

			if ($field_key === '' || $label === '') {
				continue;
			}

			$sanitized[] = [
				'field_key' => $field_key,
				'label' => $label,
				'type' => $type,
				'required' => $required,
				'placeholder' => $placeholder,
				'help_text' => $help_text,
				'options' => implode("\n", $options),
			];
		}

		return !empty($sanitized) ? $sanitized : self::get_default_grant_form_fields();
	}

	private function sanitize_featured_photographers($photographers) {
		$sanitized = [];
		foreach ($photographers as $photographer) {
			if (!is_array($photographer)) {
				continue;
			}

			$name = sanitize_text_field($photographer['name'] ?? '');
			$short_bio = sanitize_textarea_field($photographer['short_bio'] ?? '');
			$website_url = esc_url_raw($photographer['website_url'] ?? '');
			$instagram_url = esc_url_raw($photographer['instagram_url'] ?? '');
			$facebook_url = esc_url_raw($photographer['facebook_url'] ?? '');
			$x_url = esc_url_raw($photographer['x_url'] ?? '');
			$profile_image_url = esc_url_raw($photographer['profile_image_url'] ?? '');
			$gallery_items = [];

			if (isset($photographer['gallery_items']) && is_array($photographer['gallery_items'])) {
				foreach (array_slice(array_values($photographer['gallery_items']), 0, 6) as $gallery_item) {
					if (!is_array($gallery_item)) {
						continue;
					}
					$image_url = esc_url_raw($gallery_item['image_url'] ?? '');
					$caption = sanitize_text_field($gallery_item['caption'] ?? '');
					if ($image_url === '' && $caption === '') {
						continue;
					}
					$gallery_items[] = [
						'image_url' => $image_url,
						'caption' => $caption,
					];
				}
			}

			if (
				$name === '' &&
				$short_bio === '' &&
				$website_url === '' &&
				$instagram_url === '' &&
				$facebook_url === '' &&
				$x_url === '' &&
				$profile_image_url === '' &&
				empty($gallery_items)
			) {
				continue;
			}

			$sanitized[] = [
				'name' => $name,
				'short_bio' => $short_bio,
				'website_url' => $website_url,
				'instagram_url' => $instagram_url,
				'facebook_url' => $facebook_url,
				'x_url' => $x_url,
				'profile_image_url' => $profile_image_url,
				'gallery_items' => $gallery_items,
			];
		}

		return !empty($sanitized) ? $sanitized : self::get_default_featured_photographers();
	}

	private function sanitize_rescue_levels($levels) {
		$sanitized_levels = [];
		foreach ($levels as $level) {
			if (!is_array($level)) {
				continue;
			}

			$level_name = sanitize_text_field($level['level_name'] ?? '');
			$rescue_amount = max(0, (int) ($level['rescue_amount'] ?? 0));
			$medical_amount = max(0, (int) ($level['medical_amount'] ?? 0));
			$mortal_remains_amount = max(0, (int) ($level['mortal_remains_amount'] ?? 0));
			$rescue_reimbursement_process = !empty($level['rescue_reimbursement_process']);

			if (
				$level_name === '' &&
				$rescue_amount === 0 &&
				$medical_amount === 0 &&
				$mortal_remains_amount === 0 &&
				!$rescue_reimbursement_process
			) {
				continue;
			}

			if ($level_name === '') {
				continue;
			}

			$sanitized_levels[] = [
				'level_name' => $level_name,
				'rescue_amount' => $rescue_amount,
				'medical_amount' => $medical_amount,
				'mortal_remains_amount' => $mortal_remains_amount,
				'rescue_reimbursement_process' => $rescue_reimbursement_process,
			];
		}

		return !empty($sanitized_levels) ? $sanitized_levels : self::get_default_rescue_levels();
	}

	private function sanitize_opacity($value) {
		$value = is_scalar($value) ? (float) $value : 0.18;
		$value = max(0, min(1, $value));
		return number_format($value, 2, '.', '');
	}

	private function sanitize_hex_color_or_default($value, $default) {
		$sanitized = sanitize_hex_color($value);
		return $sanitized ? $sanitized : $default;
	}

	private static function merge_with_defaults($defaults, $values) {
		foreach ($defaults as $key => $default_value) {
			if (is_array($default_value)) {
				if (self::is_list_array($default_value)) {
					$values[$key] = isset($values[$key]) && is_array($values[$key]) ? array_values($values[$key]) : $default_value;
					continue;
				}

				$values[$key] = self::merge_with_defaults($default_value, isset($values[$key]) && is_array($values[$key]) ? $values[$key] : []);
				continue;
			}

			if (!array_key_exists($key, $values)) {
				$values[$key] = $default_value;
			}
		}

		return $values;
	}

	private static function is_list_array($value) {
		if (!is_array($value)) {
			return false;
		}

		if (function_exists('array_is_list')) {
			return array_is_list($value);
		}

		return array_keys($value) === range(0, count($value) - 1);
	}
}
