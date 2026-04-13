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
		add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
	}

	public static function get_defaults() {
		// This settings tree is the source of truth for the admin UI and for the
		// runtime config injected into the React app.
		return [
			'content' => [
				'account_settings_title' => 'Account Settings',
				'contact_recipient_email' => 'mharris@americanalpineclub.org',
				'profile_information_title' => 'Profile Information',
				'profile_information_description' => 'Primary contact and profile information used across the AAC portal. You may update your details and preferences in Account Settings.',
				'membership_snapshot_title' => 'Membership Snapshot',
				'membership_snapshot_description' => 'Live membership and benefit details coming from WordPress and Paid Memberships Pro.',
				'linked_accounts_title' => 'Linked Accounts',
				'linked_accounts_description' => 'Manage household members connected to this AAC membership and redeem invite codes for child accounts.',
				'update_profile_button_label' => 'Update Profile Information',
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
				'join_hero_title' => 'United We Climb.',
				'join_hero_description' => 'Join the American Alpine Club to support climbing advocacy, rescue coverage, community grants, publications, events, and a member experience built for the people who keep showing up for the mountains.',
				'join_primary_cta_label' => 'Join Now',
				'join_benefits_cta_label' => 'Member Benefits',
				'join_rescue_cta_label' => 'Rescue Benefits',
				'join_application_kicker' => 'Application',
				'join_application_title' => 'Choose your membership and complete checkout.',
				'join_application_description' => 'Select a membership level above, then complete the real AAC checkout form below.',
				'join_redeem_code_button_label' => 'Redeem Membership Code',
				'login_hero_kicker' => 'Member access',
				'login_hero_title' => 'Sign in to your AAC portal.',
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
				'join_hero_image_url' => 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Calder-Davey-Homepage-Fillers.jpg',
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
				'top_nav_items' => self::get_default_top_nav_items(),
				'sidebar_items' => self::get_default_sidebar_items(),
			],
		];
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
		// Rescue benefits are editable in admin, but these defaults preserve the
		// expected matrix for new installs and for fallback scenarios.
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

	public static function get_default_top_nav_items() {
		return [
			'get_involved' => ['label' => 'Get Involved', 'order' => 10, 'visible' => 1],
			'membership' => ['label' => 'Membership', 'order' => 20, 'visible' => 1],
			'stories_news' => ['label' => 'Stories & News', 'order' => 30, 'visible' => 1],
			'lodging' => ['label' => 'Lodging', 'order' => 40, 'visible' => 1],
			'publications' => ['label' => 'Publications', 'order' => 50, 'visible' => 1],
			'our_work' => ['label' => 'Our Work', 'order' => 60, 'visible' => 1],
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

		// Repeater-style content blocks are sanitized separately because they are
		// stored as list arrays rather than simple scalar fields.
		if (isset($content_input['discount_cards']) && is_array($content_input['discount_cards'])) {
			$settings['content']['discount_cards'] = $this->sanitize_discount_cards($content_input['discount_cards']);
		}

		if (isset($content_input['rescue_levels']) && is_array($content_input['rescue_levels'])) {
			$settings['content']['rescue_levels'] = $this->sanitize_rescue_levels($content_input['rescue_levels']);
		}

		$design_input = isset($input['design']) && is_array($input['design']) ? $input['design'] : [];
		$design_url_fields = [
			'sidebar_background_url',
			'join_hero_image_url',
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

		return self::merge_with_defaults($defaults, $settings);
	}

	public function render_admin_page() {
		if (!current_user_can('manage_options')) {
			return;
		}

		$settings = self::get_settings();
		$tabs = [
			'global' => 'Global',
			'join' => 'Join',
			'login' => 'Login',
			'profile' => 'Profile',
			'discounts' => 'Discounts',
			'publications' => 'Publications',
			'rescue' => 'Rescue',
			'linked_accounts' => 'Linked Accounts',
			'design' => 'Design',
			'navigation' => 'Navigation',
		];
		$tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'global';
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
						case 'join':
							$this->render_join_tab($settings);
							break;
						case 'login':
							$this->render_login_tab($settings);
							break;
						case 'profile':
							$this->render_profile_tab($settings);
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
						case 'design':
							$this->render_design_tab($settings);
							break;
						case 'navigation':
							$this->render_navigation_tab($settings);
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
		</div>
		<?php
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
			<?php $this->render_input_row(self::OPTION_KEY . '[design][join_hero_image_url]', 'Hero image URL', $settings['design']['join_hero_image_url'], 'url'); ?>
		</tbody></table>
		<?php
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
		</tbody></table>
		<?php
		$this->close_panel();
	}

	private function render_profile_tab($settings) {
		$this->open_panel('Member Profile Page', 'Manage the main member profile cards and button labels.');
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
		<?php
		$this->close_panel();
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
		$this->open_panel('Design', 'Update shared portal images and color controls used across the AAC member experience.');
		?>
		<table class="form-table" role="presentation"><tbody>
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
		</tbody></table>
		<?php
		$this->close_panel();
	}

	private function render_navigation_tab($settings) {
		$this->open_panel('Navigation', 'Update section titles and control where each sidebar item appears.');
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
					<th>Order</th>
					<th>Visible</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($settings['components']['top_nav_items'] as $item_id => $item_settings) : ?>
					<tr>
						<td><strong><?php echo esc_html($item_id); ?></strong></td>
						<td><input type="text" class="regular-text" name="<?php echo esc_attr(self::OPTION_KEY . '[components][top_nav_items][' . $item_id . '][label]'); ?>" value="<?php echo esc_attr($item_settings['label']); ?>" /></td>
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
