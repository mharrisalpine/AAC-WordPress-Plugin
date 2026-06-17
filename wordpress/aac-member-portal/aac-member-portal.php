<?php
/**
 * Plugin Name: AAC Member Portal
 * Description: Embeds the AAC React member portal inside WordPress and exposes REST endpoints for member profile data (Paid Memberships Pro integration).
 * Version: 1.0.0
 * Author: AAC
 */

if (!defined('ABSPATH')) {
	exit;
}

define('AAC_MEMBER_PORTAL_VERSION', '1.0.0');
define('AAC_MEMBER_PORTAL_FILE', __FILE__);
define('AAC_MEMBER_PORTAL_DIR', plugin_dir_path(__FILE__));
define('AAC_MEMBER_PORTAL_URL', plugin_dir_url(__FILE__));

require_once AAC_MEMBER_PORTAL_DIR . 'includes/class-aac-member-portal-pmpro.php';
require_once AAC_MEMBER_PORTAL_DIR . 'includes/class-aac-member-portal-api.php';
require_once AAC_MEMBER_PORTAL_DIR . 'includes/class-aac-member-portal-redpoint-api.php';
require_once AAC_MEMBER_PORTAL_DIR . 'includes/class-aac-member-portal-admin.php';
require_once AAC_MEMBER_PORTAL_DIR . 'includes/class-aac-member-portal-member-database.php';
require_once AAC_MEMBER_PORTAL_DIR . 'includes/class-aac-member-portal-daily-member-export.php';

final class AAC_Member_Portal_Null_WP_Fusion_User {
	public function push_user_meta(...$args) {
		return false;
	}

	public function __call($name, $arguments) {
		return null;
	}
}

final class AAC_Member_Portal_Plugin {
	const SHORTCODE = 'aac_member_portal';
	const SCRIPT_HANDLE = 'aac-member-portal-app';
	const STYLE_HANDLE = 'aac-member-portal-app';
	const MOUNT_ID = 'aac-member-portal-root';
	const ORDER_BREAKDOWN_OPTION_PREFIX = 'aac_pmpro_order_breakdown_';

	private $is_rendering_managed_fullscreen = false;

	public function __construct() {
		// This plugin is juggling three jobs at once:
		// 1. be the backend/API for the React app
		// 2. give staff an admin/settings home
		// 3. keep a mirrored member database around for reporting and review
		// It is a lot, but at least the chaos is organized.
		new AAC_Member_Portal_API();
		new AAC_Member_Portal_Redpoint_API();
		new AAC_Member_Portal_Admin();
		new AAC_Member_Portal_Member_Database();
		new AAC_Member_Portal_Daily_Member_Export();

		add_shortcode(self::SHORTCODE, [$this, 'render_shortcode']);
		add_action('plugins_loaded', [$this, 'maybe_repair_pmpro_user_fields_settings'], 5);
		add_action('plugins_loaded', [$this, 'maybe_disable_broken_wp_fusion_pmpro_hooks'], 100);
		add_action('init', [$this, 'maybe_shim_broken_wp_fusion_user_service'], 20);
		add_action('init', [$this, 'maybe_disable_broken_wp_fusion_pmpro_hooks'], 1000);
		add_action('profile_update', [$this, 'maybe_shim_broken_wp_fusion_user_service'], 1, 3);
		add_action('pmpro_after_change_membership_level', [$this, 'maybe_shim_broken_wp_fusion_user_service'], 1, 3);
		add_action('wp_enqueue_scripts', [$this, 'register_assets']);
		add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_portal_for_shortcode'], 15);
		add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_shell_styles'], 15);
		add_action('send_headers', [$this, 'maybe_send_nocache_headers'], 0);
		add_action('template_redirect', [$this, 'maybe_render_managed_fullscreen_template'], 0);
		add_action('template_redirect', [$this, 'maybe_redirect_frontend_login_to_portal'], 1);
		add_action('template_redirect', [$this, 'maybe_redirect_pmpro_change_password_to_portal'], 1);
		add_action('init', [$this, 'maybe_seed_pmpro_checkout_username'], 1);
		add_action('init', [$this, 'maybe_apply_partner_family_checkout_level_override'], 2);
		add_action('shutdown', [$this, 'capture_relevant_fatal'], PHP_INT_MAX);
		add_filter('the_content', [$this, 'maybe_replace_pmpro_checkout_publication_fields'], 15);
		add_filter('the_content', [$this, 'maybe_wrap_managed_pmpro_content'], 20);
		add_action('admin_init', [$this, 'maybe_restore_pmpro_admin_capabilities']);
		add_filter('user_has_cap', [$this, 'maybe_grant_pmpro_admin_capabilities'], 20, 4);
		add_filter('login_url', [$this, 'filter_login_url_to_portal'], 20, 3);
		add_filter('pmpro_required_user_fields', [$this, 'filter_pmpro_required_user_fields']);
		add_filter('pmpro_required_billing_fields', [$this, 'filter_pmpro_required_billing_fields']);
		add_filter('pmpro_checkout_new_user_array', [$this, 'filter_pmpro_checkout_new_user_array']);
		add_action('pmpro_checkout_after_user_fields', [$this, 'render_pmpro_membership_discounts'], 9);
		add_action('pmpro_checkout_after_user_fields', [$this, 'render_pmpro_checkout_publication_preferences'], 10);
		add_action('pmpro_checkout_after_user_fields', [$this, 'render_pmpro_partner_family_options'], 12);
		add_action('pmpro_checkout_after_user_fields', [$this, 'render_pmpro_magazine_addons']);
		add_filter('pmpro_checkout_level', [$this, 'filter_pmpro_checkout_level_for_magazine_addons']);
		add_filter('pmpro_checkout_start_date', [$this, 'filter_pmpro_checkout_start_date_for_autorenew_reactivation'], 20, 2);
		add_filter('pmpro_level_cost_text', [$this, 'filter_pmpro_level_cost_text_for_autorenew_reactivation'], 20, 4);
		add_action('pmpro_after_checkout', [$this, 'capture_pmpro_checkout_order_breakdown'], 20, 2);
		add_action('pmpro_after_change_membership_level', [$this, 'sync_pmpro_checkout_profile_fields'], 20, 2);
		add_action('show_user_profile', [$this, 'render_pmpro_member_address_fields']);
		add_action('edit_user_profile', [$this, 'render_pmpro_member_address_fields']);
		add_action('personal_options_update', [$this, 'save_pmpro_member_address_fields']);
		add_action('edit_user_profile_update', [$this, 'save_pmpro_member_address_fields']);
		add_filter('pmpro_confirmation_message', [$this, 'append_pmpro_confirmation_line_items'], 20, 2);
		add_filter('template_include', [$this, 'maybe_use_fullscreen_template'], 99);
		add_action('admin_notices', [$this, 'maybe_render_missing_build_notice']);
		add_filter('script_loader_tag', [$this, 'mark_script_as_module'], 10, 3);
	}

	public function register_assets() {
		$asset_files = $this->locate_asset_files();
		if (!$asset_files['script']) {
			return;
		}

		wp_register_script(
			self::SCRIPT_HANDLE,
			$asset_files['script'],
			[],
			AAC_MEMBER_PORTAL_VERSION,
			true
		);
		wp_script_add_data(self::SCRIPT_HANDLE, 'type', 'module');

		if ($asset_files['style']) {
			wp_register_style(
				self::STYLE_HANDLE,
				$asset_files['style'],
				[],
				AAC_MEMBER_PORTAL_VERSION
			);
		}
	}

	/**
	 * Enqueue early when the main post content contains the shortcode so scripts are registered
	 * before aggressive optimizers reorder output (avoids module running before inline config).
	 */
	public function maybe_enqueue_portal_for_shortcode() {
		if (!$this->get_shortcode_post()) {
			return;
		}

		$this->enqueue_portal_assets_and_config();
	}

	public function maybe_enqueue_shell_styles() {
		if (!$this->get_pmpro_shell_post() && !$this->get_public_shell_post()) {
			return;
		}

		$asset_files = $this->locate_asset_files();
		if ($asset_files['style']) {
			wp_enqueue_style(self::STYLE_HANDLE);
		}
	}

	public function maybe_send_nocache_headers() {
		if (!$this->get_shortcode_post() && !$this->get_pmpro_shell_post() && !$this->get_public_shell_post()) {
			return;
		}

		nocache_headers();
		header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
		header('Pragma: no-cache');
		header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
	}

	public function maybe_use_fullscreen_template($template) {
		$post = $this->get_shortcode_post();
		if (!$post) {
			$post = $this->get_pmpro_shell_post();
		}
		if (!$post) {
			$post = $this->get_public_shell_post();
		}

		if (!$post) {
			return $template;
		}

		$use_fullscreen_template = apply_filters(
			'aac_member_portal_use_fullscreen_template',
			true,
			$post
		);

		if (!$use_fullscreen_template) {
			return $template;
		}

		$portal_template = AAC_MEMBER_PORTAL_DIR . 'templates/fullscreen-portal.php';
		if (file_exists($portal_template)) {
			return $portal_template;
		}

		return $template;
	}

	public function maybe_render_managed_fullscreen_template() {
		$post = $this->get_pmpro_shell_post();
		if (!$post) {
			return;
		}

		$portal_template = AAC_MEMBER_PORTAL_DIR . 'templates/fullscreen-portal.php';
		if (!file_exists($portal_template)) {
			return;
		}

		$this->is_rendering_managed_fullscreen = true;
		status_header(200);
		include $portal_template;
		exit;
	}

	public function maybe_wrap_managed_pmpro_content($content) {
		if (is_admin() || !in_the_loop() || !is_main_query()) {
			return $content;
		}

		if ($this->is_rendering_managed_fullscreen) {
			return $content;
		}

		$post = $this->get_pmpro_shell_post();
		if (!$post) {
			return $content;
		}

		$shell_template = AAC_MEMBER_PORTAL_DIR . 'templates/managed-shell-content.php';
		if (!file_exists($shell_template)) {
			return $content;
		}

		$portal_url = untrailingslashit($this->get_portal_page_url()) . '/';
		$account_url = AAC_Member_Portal_PMPro::is_available() && function_exists('pmpro_url') ? pmpro_url('account') : home_url('/membership-account/');
		$billing_url = AAC_Member_Portal_PMPro::is_available() && function_exists('pmpro_url') ? pmpro_url('billing') : home_url('/membership-account/membership-billing/');
		$orders_url = AAC_Member_Portal_PMPro::is_available() && function_exists('pmpro_url') ? pmpro_url('invoice') : home_url('/membership-account/membership-orders/');
		$checkout_url = AAC_Member_Portal_PMPro::is_available() && function_exists('pmpro_url') ? pmpro_url('checkout') : home_url('/membership-checkout/');
		$cancel_url = AAC_Member_Portal_PMPro::is_available() && function_exists('pmpro_url') ? pmpro_url('cancel') : home_url('/membership-account/membership-cancel/');
		$confirmation_url = AAC_Member_Portal_PMPro::is_available() && function_exists('pmpro_url') ? pmpro_url('confirmation') : home_url('/membership-checkout/membership-confirmation/');
		$account_path = untrailingslashit((string) wp_parse_url($account_url, PHP_URL_PATH));
		$current_url = untrailingslashit(get_permalink($post));
		$request_path = '';
		if (!empty($_SERVER['REQUEST_URI'])) {
			$request_path = untrailingslashit((string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH));
		}
		$billing_path = untrailingslashit((string) wp_parse_url($billing_url, PHP_URL_PATH));
		$orders_path = untrailingslashit((string) wp_parse_url($orders_url, PHP_URL_PATH));
		$checkout_path = untrailingslashit((string) wp_parse_url($checkout_url, PHP_URL_PATH));
		$cancel_path = untrailingslashit((string) wp_parse_url($cancel_url, PHP_URL_PATH));
		$confirmation_path = untrailingslashit((string) wp_parse_url($confirmation_url, PHP_URL_PATH));
		$is_account_page = $current_url === untrailingslashit($account_url) || $post->post_name === 'membership-account' || ($account_path && $account_path === $request_path);
		$is_billing_page = $current_url === untrailingslashit($billing_url) || $post->post_name === 'membership-billing' || ($billing_path && $billing_path === $request_path);
		$is_orders_page = $current_url === untrailingslashit($orders_url) || $post->post_name === 'membership-orders' || ($orders_path && $orders_path === $request_path);
		$is_checkout_page = $current_url === untrailingslashit($checkout_url) || $post->post_name === 'membership-checkout' || ($checkout_path && $checkout_path === $request_path);
		$is_cancel_page = $current_url === untrailingslashit($cancel_url) || $post->post_name === 'membership-cancel' || ($cancel_path && $cancel_path === $request_path);
		$is_confirmation_page = $current_url === untrailingslashit($confirmation_url) || $post->post_name === 'membership-confirmation' || ($confirmation_path && $confirmation_path === $request_path);
		$page_title = $is_account_page
			? 'Membership Account'
			: ($is_billing_page
			? 'Membership Billing'
			: ($is_orders_page
				? 'Membership Orders'
			: ($is_cancel_page
				? 'Membership Cancellation'
				: ($is_confirmation_page ? 'Membership Confirmation' : 'Membership Checkout'))));
		$page_kicker = $is_account_page
			? 'Account Overview'
			: ($is_billing_page
			? 'Billing Center'
			: ($is_orders_page
				? 'Order History'
			: ($is_cancel_page
				? 'Membership Options'
				: ($is_confirmation_page ? 'Confirmation' : 'Secure Checkout'))));
		$page_description = $is_account_page
			? 'Review your current membership, billing controls, renewal timing, and PMPro account tools in the same AAC portal shell.'
			: ($is_billing_page
			? 'Manage payment methods, current memberships, and PMPro billing details without leaving the AAC portal experience.'
			: ($is_orders_page
				? 'Review membership invoices, completed renewals, and recent PMPro transactions without leaving the AAC portal shell.'
			: ($is_cancel_page
				? 'Review cancellation options for any membership level without leaving the AAC portal shell.'
				: ($is_confirmation_page
					? 'Review your completed membership order in the same AAC portal shell with quick access back to your profile and account.'
					: 'Complete membership checkout in the same AAC portal shell with quick access back to your profile and account.'))));

		ob_start();
		include $shell_template;
		return ob_get_clean();
	}

	public function maybe_replace_pmpro_checkout_publication_fields($content) {
		if (is_admin() || !in_the_loop() || !is_main_query()) {
			return $content;
		}

		if (!$this->is_pmpro_checkout_request() || trim((string) $content) === '') {
			return $content;
		}

		if (
			strpos($content, 'aaj_preference_div') === false &&
			strpos($content, 'anac_preference_div') === false &&
			strpos($content, 'american_climbing_journal_preference_div') === false &&
			strpos($content, 'guidebook_preferences_div') === false
		) {
			return $content;
		}

		$level = $this->get_level_at_checkout();
		$level_id = isset($level->id) ? (int) $level->id : 0;
		if ($level_id <= 2 || !class_exists('DOMDocument')) {
			return $content;
		}

		$previous_use_internal_errors = libxml_use_internal_errors(true);
		$dom = new DOMDocument('1.0', 'UTF-8');
		$wrapped = '<div id="aac-pmpro-content-root">' . $content . '</div>';
		$loaded = $dom->loadHTML(mb_convert_encoding($wrapped, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		if (!$loaded) {
			libxml_clear_errors();
			libxml_use_internal_errors($previous_use_internal_errors);
			return $content;
		}

		$xpath = new DOMXPath($dom);
		$fieldset_nodes = $xpath->query("//*[@id='pmpro_form_fieldset-member-preferences' or @id='pmpro_form_fieldset-more-information']");
		$fieldset = ($fieldset_nodes instanceof DOMNodeList && $fieldset_nodes->length > 0) ? $fieldset_nodes->item(0) : null;
		if (!$fieldset instanceof DOMElement) {
			libxml_clear_errors();
			libxml_use_internal_errors($previous_use_internal_errors);
			return $content;
		}

		$existing_server_cards = $xpath->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' aac-server-member-preferences ')]", $fieldset);
		if ($existing_server_cards instanceof DOMNodeList && $existing_server_cards->length > 0) {
			libxml_clear_errors();
			libxml_use_internal_errors($previous_use_internal_errors);
			return $content;
		}

		$fields_nodes = $xpath->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' pmpro_form_fields ')]", $fieldset);
		$fields_container = ($fields_nodes instanceof DOMNodeList && $fields_nodes->length > 0) ? $fields_nodes->item(0) : null;
		if (!$fields_container instanceof DOMElement) {
			libxml_clear_errors();
			libxml_use_internal_errors($previous_use_internal_errors);
			return $content;
		}

		$publication_definitions = [
			[
				'id' => 'aaj_preference_div',
				'name' => 'aaj_preference',
				'eyebrow' => 'Annual',
				'title' => 'American Alpine Journal',
				'description' => 'Annual climbing journal. Choose print delivery or digital-only access.',
				'image_key' => 'aaj',
				'theme_class' => 'aac-member-preferences__card--journal',
			],
			[
				'id' => 'anac_preference_div',
				'name' => 'anac_preference',
				'eyebrow' => 'Annual',
				'title' => 'Accidents in North American Climbing',
				'description' => 'Annual accident review. Choose print delivery or digital-only access.',
				'image_key' => 'anac',
				'theme_class' => 'aac-member-preferences__card--accidents',
			],
			[
				'id' => 'american_climbing_journal_preference_div',
				'name' => 'american_climbing_journal_preference',
				'eyebrow' => 'Journal',
				'title' => 'American Climbing Journal',
				'description' => 'Member stories and club updates. Choose print delivery or digital-only access.',
				'image_key' => 'acj',
				'theme_class' => 'aac-member-preferences__card--journal',
			],
			[
				'id' => 'guidebook_preferences_div',
				'name' => 'guidebook_preferences',
				'eyebrow' => 'Quarterly',
				'title' => 'Guidebook to Membership',
				'description' => 'Quarterly member publication. Choose print delivery or digital-only access.',
				'image_key' => 'guidebook',
				'theme_class' => 'aac-member-preferences__card--guidebook',
			],
		];

		$publication_images = $this->get_template_design_settings()['publication_tile_images'] ?? [];
		$fallback_images = [
			'aaj' => 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/08/image-asset-95.jpeg',
			'anac' => 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/08/image-asset-28.jpeg',
			'acj' => 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Calder-Davey-Homepage-Filler-4.jpg',
			'guidebook' => 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Calder-Davey-Homepage-Filler-2.jpg',
		];

		$cards = [];
		foreach ($publication_definitions as $definition) {
			$field_nodes = $xpath->query(".//*[@id='" . $definition['id'] . "']", $fieldset);
			$field_node = ($field_nodes instanceof DOMNodeList && $field_nodes->length > 0) ? $field_nodes->item(0) : null;
			if (!$field_node instanceof DOMElement) {
				continue;
			}

			$select_nodes = $xpath->query(".//select[@name='" . $definition['name'] . "']", $field_node);
			$select_node = ($select_nodes instanceof DOMNodeList && $select_nodes->length > 0) ? $select_nodes->item(0) : null;
			if (!$select_node instanceof DOMElement) {
				continue;
			}

			$selected_value = 'Digital';
			foreach ($select_node->getElementsByTagName('option') as $option_node) {
				if (!$option_node instanceof DOMElement) {
					continue;
				}

				if ($option_node->hasAttribute('selected')) {
					$selected_value = trim((string) $option_node->getAttribute('value')) ?: 'Digital';
					break;
				}
			}

			if ($selected_value !== 'Print') {
				$selected_value = 'Digital';
			}

			$image_url = trim((string) ($publication_images[$definition['image_key']] ?? ''));
			if ($image_url === '') {
				$image_url = $fallback_images[$definition['image_key']] ?? '';
			}

			$cards[] = [
				'name' => $definition['name'],
				'eyebrow' => $definition['eyebrow'],
				'title' => $definition['title'],
				'description' => $definition['description'],
				'image_url' => $image_url,
				'theme_class' => $definition['theme_class'],
				'selected_value' => $selected_value,
			];

			$field_node->parentNode->removeChild($field_node);
		}

		if (empty($cards)) {
			libxml_clear_errors();
			libxml_use_internal_errors($previous_use_internal_errors);
			return $content;
		}

		$fragment = $dom->createDocumentFragment();
		$fragment->appendXML($this->build_pmpro_publication_preferences_markup($cards));
		$fields_container->appendChild($fragment);

		$root_nodes = $xpath->query("//*[@id='aac-pmpro-content-root']");
		$root = ($root_nodes instanceof DOMNodeList && $root_nodes->length > 0) ? $root_nodes->item(0) : null;
		$result = $root instanceof DOMElement ? $this->get_dom_inner_html($root) : $content;

		libxml_clear_errors();
		libxml_use_internal_errors($previous_use_internal_errors);

		return $result;
	}

	public function render_pmpro_checkout_publication_preferences() {
		if (is_admin() || !$this->is_pmpro_checkout_request()) {
			return;
		}

		$level = $this->get_level_at_checkout();
		$level_id = isset($level->id) ? (int) $level->id : 0;
		if ($level_id <= 2) {
			return;
		}

		$cards = $this->get_pmpro_checkout_publication_preference_cards();
		if (empty($cards)) {
			return;
		}

		echo $this->build_pmpro_publication_preferences_markup($cards); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	private function get_pmpro_checkout_publication_preference_cards() {
		$publication_definitions = [
			[
				'name' => 'aaj_preference',
				'eyebrow' => 'Annual',
				'title' => 'American Alpine Journal',
				'description' => 'Annual climbing journal. Choose print delivery or digital-only access.',
				'image_key' => 'aaj',
				'theme_class' => 'aac-member-preferences__card--journal',
			],
			[
				'name' => 'anac_preference',
				'eyebrow' => 'Annual',
				'title' => 'Accidents in North American Climbing',
				'description' => 'Annual accident review. Choose print delivery or digital-only access.',
				'image_key' => 'anac',
				'theme_class' => 'aac-member-preferences__card--accidents',
			],
			[
				'name' => 'american_climbing_journal_preference',
				'eyebrow' => 'Journal',
				'title' => 'American Climbing Journal',
				'description' => 'Member stories and club updates. Choose print delivery or digital-only access.',
				'image_key' => 'acj',
				'theme_class' => 'aac-member-preferences__card--journal',
			],
			[
				'name' => 'guidebook_preferences',
				'eyebrow' => 'Quarterly',
				'title' => 'Guidebook to Membership',
				'description' => 'Quarterly member publication. Choose print delivery or digital-only access.',
				'image_key' => 'guidebook',
				'theme_class' => 'aac-member-preferences__card--guidebook',
			],
		];

		$publication_images = $this->get_template_design_settings()['publication_tile_images'] ?? [];
		$fallback_images = [
			'aaj' => 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/08/image-asset-95.jpeg',
			'anac' => 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/08/image-asset-28.jpeg',
			'acj' => 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Calder-Davey-Homepage-Filler-4.jpg',
			'guidebook' => 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Calder-Davey-Homepage-Filler-2.jpg',
		];

		$current_user_id = get_current_user_id();
		$cards = [];
		foreach ($publication_definitions as $definition) {
			$request_value = '';
			if (isset($_REQUEST[$definition['name']])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$request_value = sanitize_text_field(wp_unslash($_REQUEST[$definition['name']])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}

			$stored_value = $current_user_id ? (string) get_user_meta($current_user_id, $definition['name'], true) : '';
			$selected_value = $request_value !== '' ? $request_value : $stored_value;
			$selected_value = $selected_value === 'Digital' ? 'Digital' : 'Print';

			$image_url = trim((string) ($publication_images[$definition['image_key']] ?? ''));
			if ($image_url === '') {
				$image_url = $fallback_images[$definition['image_key']] ?? '';
			}

			$cards[] = [
				'name' => $definition['name'],
				'eyebrow' => $definition['eyebrow'],
				'title' => $definition['title'],
				'description' => $definition['description'],
				'image_url' => $image_url,
				'theme_class' => $definition['theme_class'],
				'selected_value' => $selected_value,
			];
		}

		return $cards;
	}

	private function build_pmpro_publication_preferences_markup($cards) {
		$markup = '<div class="aac-server-member-preferences">';
		$markup .= '<p class="aac-member-preferences__intro">' . esc_html__('Choose how you would like to receive each AAC publication. Print keeps the mailed edition on your membership, while digital keeps the experience paperless.', 'aac-member-portal') . '</p>';
		$markup .= '<div class="aac-member-preferences__grid">';

		foreach ($cards as $card) {
			$markup .= '<article class="aac-member-preferences__card ' . esc_attr($card['theme_class']) . '">';
			$markup .= '<div class="aac-member-preferences__art">';
			if (!empty($card['image_url'])) {
				$markup .= '<img src="' . esc_url($card['image_url']) . '" alt="' . esc_attr($card['title'] . ' cover') . '" class="aac-member-preferences__cover-image" />';
			}
			$markup .= '</div>';
			$markup .= '<div class="aac-member-preferences__content">';
			$markup .= '<div class="aac-member-preferences__title-block">';
			$markup .= '<span class="aac-member-preferences__eyebrow">' . esc_html($card['eyebrow']) . '</span>';
			$markup .= '<h3 class="aac-member-preferences__title">' . esc_html($card['title']) . '</h3>';
			$markup .= '</div>';
			$markup .= '<p class="aac-member-preferences__description">' . esc_html($card['description']) . '</p>';
			$markup .= '<div class="aac-member-preferences__choices">';

			foreach (['Print', 'Digital'] as $option) {
				$markup .= '<label class="aac-member-preferences__option">';
				$markup .= '<input class="aac-member-preferences__input" type="radio" name="' . esc_attr($card['name']) . '" value="' . esc_attr($option) . '" ' . checked($card['selected_value'], $option, false) . ' required />';
				$markup .= '<span class="aac-member-preferences__choice">' . esc_html($option) . '</span>';
				$markup .= '</label>';
			}

			$markup .= '</div></div></article>';
		}

		$markup .= '</div></div>';
		return $markup;
	}

	private function get_dom_inner_html(DOMNode $node) {
		$html = '';
		foreach ($node->childNodes as $child_node) {
			$html .= $node->ownerDocument->saveHTML($child_node);
		}
		return $html;
	}

	public function normalize_pmpro_checkout_publication_markup($content) {
		if (!is_string($content) || $content === '') {
			return $content;
		}

		if (
			strpos($content, 'aac-server-member-preferences') === false ||
			strpos($content, 'pmpro_form_fieldset-publication-preferences') === false ||
			!class_exists('DOMDocument')
		) {
			return $content;
		}

		$previous_use_internal_errors = libxml_use_internal_errors(true);
		$dom = new DOMDocument('1.0', 'UTF-8');
		$wrapped = '<div id="aac-publication-normalize-root">' . $content . '</div>';
		$loaded = $dom->loadHTML(mb_convert_encoding($wrapped, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		if (!$loaded) {
			libxml_clear_errors();
			libxml_use_internal_errors($previous_use_internal_errors);
			return $content;
		}

		$xpath = new DOMXPath($dom);
		$server_block_nodes = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' aac-server-member-preferences ')]");
		$publication_fieldset_nodes = $xpath->query("//*[@id='pmpro_form_fieldset-publication-preferences']");
		$server_block = ($server_block_nodes instanceof DOMNodeList && $server_block_nodes->length > 0) ? $server_block_nodes->item(0) : null;
		$publication_fieldset = ($publication_fieldset_nodes instanceof DOMNodeList && $publication_fieldset_nodes->length > 0) ? $publication_fieldset_nodes->item(0) : null;

		if (!$server_block instanceof DOMElement || !$publication_fieldset instanceof DOMElement) {
			libxml_clear_errors();
			libxml_use_internal_errors($previous_use_internal_errors);
			return $content;
		}

		$publication_fields_nodes = $xpath->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' pmpro_form_fields ')]", $publication_fieldset);
		$publication_fields = ($publication_fields_nodes instanceof DOMNodeList && $publication_fields_nodes->length > 0) ? $publication_fields_nodes->item(0) : null;
		if (!$publication_fields instanceof DOMElement) {
			libxml_clear_errors();
			libxml_use_internal_errors($previous_use_internal_errors);
			return $content;
		}

		if ($server_block->parentNode) {
			$server_block->parentNode->removeChild($server_block);
		}
		if ($publication_fields->firstChild) {
			$publication_fields->insertBefore($server_block, $publication_fields->firstChild);
		} else {
			$publication_fields->appendChild($server_block);
		}

		$root_nodes = $xpath->query("//*[@id='aac-publication-normalize-root']");
		$root = ($root_nodes instanceof DOMNodeList && $root_nodes->length > 0) ? $root_nodes->item(0) : null;
		$result = $root instanceof DOMElement ? $this->get_dom_inner_html($root) : $content;

		libxml_clear_errors();
		libxml_use_internal_errors($previous_use_internal_errors);

		return $result;
	}

	public function maybe_redirect_frontend_login_to_portal() {
		if (is_admin() || is_user_logged_in()) {
			return;
		}

		if (wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
			return;
		}

		if (!$this->is_frontend_login_request()) {
			return;
		}

		$redirect_to = '';
		$raw_redirect_to = '';
		if (isset($_GET['redirect_to'])) {
			$raw_redirect_to = trim((string) wp_unslash($_GET['redirect_to']));
			$redirect_to = wp_validate_redirect($raw_redirect_to, '');
		}

		$admin_redirect = $redirect_to;
		if (!$admin_redirect && $raw_redirect_to && $this->is_wp_admin_url($raw_redirect_to)) {
			$admin_redirect = $raw_redirect_to;
		}

		if ($this->is_wp_admin_auth_request($admin_redirect) || $this->should_preserve_wp_login_url($this->get_current_request_url(), $admin_redirect)) {
			wp_safe_redirect($this->build_wp_login_url_from_current_request($admin_redirect));
			exit;
		}

		wp_safe_redirect($this->build_portal_login_url($redirect_to));
		exit;
	}

	public function maybe_redirect_pmpro_change_password_to_portal() {
		if (is_admin() || !$this->is_pmpro_change_password_request()) {
			return;
		}

		$target = $this->build_portal_app_url('change-password');
		if (!is_user_logged_in()) {
			$target = $this->build_portal_login_url($target);
		}

		wp_safe_redirect($target);
		exit;
	}

	public function filter_login_url_to_portal($login_url, $redirect, $force_reauth) {
		if (
			is_admin() ||
			wp_doing_ajax() ||
			$force_reauth ||
			$this->is_wp_admin_auth_request($redirect) ||
			$this->should_preserve_wp_login_url($login_url, $redirect)
		) {
			return $login_url;
		}

		if (!$this->should_use_portal_login($redirect)) {
			return $login_url;
		}

		return $this->build_portal_login_url($redirect);
	}

	public function filter_pmpro_required_user_fields($required_fields) {
		if (!is_array($required_fields)) {
			return $required_fields;
		}

		foreach ($required_fields as $index => $field_name) {
			if ($field_name === 't_shirt' || $field_name === 'T-Shirt Size') {
				unset($required_fields[$index]);
			}
		}

		return $required_fields;
	}

	public function filter_pmpro_required_billing_fields($required_fields) {
		return $required_fields;
	}

	public function filter_pmpro_checkout_new_user_array($user_data) {
		if (!is_array($user_data)) {
			return $user_data;
		}

		$email = '';
		if (isset($_REQUEST['bemail'])) {
			$email = sanitize_email(wp_unslash($_REQUEST['bemail']));
		} elseif (!empty($user_data['user_email'])) {
			$email = sanitize_email($user_data['user_email']);
		}

		if ($email) {
			$user_data['user_email'] = $email;
			$user_data['user_login'] = $this->generate_unique_username_from_email($email);
		}

		if (isset($_REQUEST['password'])) {
			$password = (string) wp_unslash($_REQUEST['password']);
			if ($password !== '') {
				$user_data['user_pass'] = $password;
			}
		}

		$first_name = isset($_REQUEST['bfirstname']) ? sanitize_text_field(wp_unslash($_REQUEST['bfirstname'])) : '';
		$last_name = isset($_REQUEST['blastname']) ? sanitize_text_field(wp_unslash($_REQUEST['blastname'])) : '';
		$display_name = trim($first_name . ' ' . $last_name);

		if ($display_name !== '') {
			$user_data['display_name'] = $display_name;
			$user_data['first_name'] = $first_name;
			$user_data['last_name'] = $last_name;
		} elseif ($email && empty($user_data['display_name'])) {
			$user_data['display_name'] = $email;
		}

		return $user_data;
	}

	public function maybe_seed_pmpro_checkout_username() {
		if (is_admin()) {
			return;
		}

		$request_method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : '';
		if ($request_method !== 'POST' || !$this->is_pmpro_checkout_request()) {
			return;
		}

		$current_username = isset($_REQUEST['username']) ? trim((string) wp_unslash($_REQUEST['username'])) : '';
		if ($current_username !== '') {
			return;
		}

		$email = isset($_REQUEST['bemail']) ? sanitize_email(wp_unslash($_REQUEST['bemail'])) : '';
		if ($email === '') {
			return;
		}

		$username = $this->generate_unique_username_from_email($email);
		$_REQUEST['username'] = $username;
		$_POST['username'] = $username;
	}

	public function maybe_apply_partner_family_checkout_level_override() {
		if (is_admin()) {
			return;
		}

		$request_method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : '';
		if ($request_method !== 'POST' || !$this->is_pmpro_checkout_request()) {
			return;
		}

		if (!$this->has_partner_family_request()) {
			return;
		}

		$requested_level_id = $this->get_requested_level_id();
		$partner_family_level_id = $this->get_partner_family_level_id();
		if (!$partner_family_level_id || $requested_level_id !== $partner_family_level_id) {
			return;
		}

		$target_level_id = $this->get_partner_level_id();

		if (!$target_level_id) {
			return;
		}

		$_REQUEST['level'] = $target_level_id;
		$_GET['level'] = $target_level_id;
		$_POST['level'] = $target_level_id;
	}

	public function render_pmpro_membership_discounts() {
		$discount_options = $this->get_membership_discount_catalog();
		$current_user_id = get_current_user_id();
		$family_config = $this->get_effective_partner_family_config($current_user_id);
		if (empty($discount_options) && $family_config['mode'] !== 'family') {
			return;
		}

		$checkout_level = $this->get_level_at_checkout();
		$supports_discount_tiers = $this->supports_discount_tiers($checkout_level);
		$supports_family_plan = $this->supports_family_plan_tiers($checkout_level);
		if (!$supports_discount_tiers && !$supports_family_plan) {
			return;
		}

		$selected_discount = $this->has_membership_discount_request()
			? $this->get_requested_membership_discount_type()
			: '';
		$base_level_total = max(0, $this->get_level_checkout_initial_total($checkout_level));
		?>
		<div
			id="pmpro_form_fieldset-membership-discounts"
			class="pmpro_checkout-fields pmpro_form_fieldset aac-membership-discounts"
			data-aac-membership-base-price="<?php echo esc_attr(number_format($base_level_total, 2, '.', '')); ?>"
		>
			<div class="pmpro_card">
				<div class="pmpro_card_content">
					<legend class="pmpro_form_legend">
						<h2 class="pmpro_form_heading pmpro_font-large"><?php esc_html_e('Membership Discounts', 'aac-member-portal'); ?></h2>
					</legend>
					<div class="pmpro_form_fields">
						<input type="hidden" name="aac_membership_discount_present" value="1" />
						<p class="aac-membership-discounts__intro">
							<?php esc_html_e('Select one discount type if it applies to this membership. Click it again to remove it. Only one discount can be used at a time.', 'aac-member-portal'); ?>
						</p>
						<div class="aac-membership-discounts__picker" role="group" aria-label="<?php esc_attr_e('Membership discount selection', 'aac-member-portal'); ?>">
							<div class="aac-membership-discounts__grid">
								<?php foreach ($discount_options as $slug => $discount) : ?>
									<div class="pmpro_form_field pmpro_form_field-checkbox aac-membership-discounts__field">
										<label class="pmpro_form_label pmpro_form_label-inline aac-membership-discounts__label" for="<?php echo esc_attr('aac_membership_discount_' . $slug); ?>">
											<input
												id="<?php echo esc_attr('aac_membership_discount_' . $slug); ?>"
												class="aac-membership-discounts__input"
												type="checkbox"
												name="aac_membership_discount"
												value="<?php echo esc_attr($slug); ?>"
												data-aac-membership-discount-rate="<?php echo esc_attr(number_format((float) $discount['rate'], 2, '.', '')); ?>"
												data-aac-membership-discount-label="<?php echo esc_attr($discount['label']); ?>"
												data-aac-toggleable-choice="true"
												<?php checked($selected_discount, $slug); ?>
											/>
											<span class="aac-membership-discounts__card">
												<span class="aac-membership-discounts__icon" aria-hidden="true">
													<?php echo $discount['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
												</span>
												<span class="aac-membership-discounts__body">
													<span class="aac-membership-discounts__copy">
														<strong><?php echo esc_html($discount['label']); ?></strong>
													</span>
													<span class="aac-membership-discounts__footer">
														<span class="aac-membership-discounts__price"><?php echo esc_html($discount['badge']); ?></span>
													</span>
												</span>
											</span>
										</label>
									</div>
								<?php endforeach; ?>
								<?php if ($supports_family_plan) : ?>
									<div class="pmpro_form_field pmpro_form_field-checkbox aac-membership-discounts__field">
										<label class="pmpro_form_label pmpro_form_label-inline aac-membership-discounts__label" for="aac_partner_family_shortcut">
											<input
												id="aac_partner_family_shortcut"
												class="aac-membership-discounts__input"
												type="checkbox"
												value="family"
												data-aac-family-shortcut="true"
												<?php checked($family_config['mode'], 'family'); ?>
											/>
											<span class="aac-membership-discounts__card">
												<span class="aac-membership-discounts__icon" aria-hidden="true">
													<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
												</span>
												<span class="aac-membership-discounts__body">
													<span class="aac-membership-discounts__copy">
														<strong><?php esc_html_e('Family Option', 'aac-member-portal'); ?></strong>
														<span><?php esc_html_e('Add one additional adult and up to three dependents to this membership.', 'aac-member-portal'); ?></span>
													</span>
													<span class="aac-membership-discounts__footer">
														<span class="aac-membership-discounts__price"><?php esc_html_e('Family plan pricing', 'aac-member-portal'); ?></span>
													</span>
												</span>
											</span>
										</label>
									</div>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public function render_pmpro_partner_family_options() {
		$checkout_level = $this->get_level_at_checkout();
		if (!$this->is_partner_family_checkout_level($checkout_level)) {
			return;
		}

		$current_user_id = get_current_user_id();
		$family_config = $this->get_effective_partner_family_config($current_user_id);
		$base_level_total = max(0, $this->get_level_checkout_initial_total($checkout_level));
		$pricing = $this->get_partner_family_pricing($base_level_total);
		?>
		<div
			id="pmpro_form_fieldset-partner-family"
			class="pmpro_checkout-fields pmpro_form_fieldset aac-partner-family"
			data-aac-partner-family-base-price="<?php echo esc_attr(number_format($base_level_total, 2, '.', '')); ?>"
			data-aac-partner-family-adult-price="<?php echo esc_attr(number_format((float) $pricing['additional_adult_price'], 2, '.', '')); ?>"
			data-aac-partner-family-dependent-price="<?php echo esc_attr(number_format((float) $pricing['dependent_price'], 2, '.', '')); ?>"
		>
			<div class="pmpro_card">
				<div class="pmpro_card_content">
					<legend class="pmpro_form_legend">
						<h2 class="pmpro_form_heading pmpro_font-large"><?php esc_html_e('Family Membership Options', 'aac-member-portal'); ?></h2>
					</legend>
					<div class="pmpro_form_fields">
						<input type="hidden" name="aac_partner_family_present" value="1" />
						<input type="hidden" id="aac_partner_family_mode" name="aac_partner_family_mode" value="<?php echo esc_attr($family_config['mode']); ?>" />
						<p class="aac-partner-family__intro">
							<?php esc_html_e('Use the Family option above to activate these family plan add-ons for this membership.', 'aac-member-portal'); ?>
						</p>
						<div class="aac-partner-family__details" data-aac-partner-family-details <?php if ($family_config['mode'] !== 'family') : ?>hidden style="display:none;"<?php endif; ?>>
							<label class="aac-partner-family__card" for="aac_partner_family_additional_adult">
								<input
									id="aac_partner_family_additional_adult"
									type="checkbox"
									name="aac_partner_family_additional_adult"
									value="1"
									<?php checked(!empty($family_config['additional_adult'])); ?>
								/>
								<span class="aac-partner-family__card-inner">
									<span class="aac-partner-family__card-copy">
										<strong><?php esc_html_e('Additional adult', 'aac-member-portal'); ?></strong>
										<span><?php esc_html_e('Select one additional adult for $80 per year.', 'aac-member-portal'); ?></span>
									</span>
									<span class="aac-partner-family__card-price">
										<?php echo esc_html($this->format_price($pricing['additional_adult_price'])); ?>
									</span>
								</span>
							</label>
							<div class="aac-partner-family__dependents">
								<label class="pmpro_form_label" for="aac_partner_family_dependents">
									<?php esc_html_e('Dependents', 'aac-member-portal'); ?>
								</label>
								<select
									id="aac_partner_family_dependents"
									name="aac_partner_family_dependents"
									class="pmpro_form_input pmpro_form_input-select"
								>
									<?php for ($dependent_index = 0; $dependent_index <= 3; $dependent_index++) : ?>
										<option value="<?php echo esc_attr((string) $dependent_index); ?>" <?php selected((int) $family_config['dependent_count'], $dependent_index); ?>>
											<?php
											echo esc_html(
												$dependent_index === 1
													? __('1 dependent', 'aac-member-portal')
													: sprintf(__('%d dependents', 'aac-member-portal'), $dependent_index)
											);
											?>
										</option>
									<?php endfor; ?>
								</select>
								<p class="aac-partner-family__dependents-note">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %s price */
											__('Each dependent is billed at %s per year.', 'aac-member-portal'),
											$this->format_price($pricing['dependent_price'])
										)
									);
									?>
								</p>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public function render_pmpro_magazine_addons() {
		$magazine_addons = $this->get_magazine_addon_catalog();
		if (empty($magazine_addons)) {
			return;
		}

		$current_user_id = get_current_user_id();
		$selected_addons = $this->get_effective_magazine_addon_selection($current_user_id);
		$request_selected_addons = $this->get_requested_magazine_addons();
		$selected_addon_total = $this->get_magazine_addon_total(
			$this->has_magazine_addon_request() ? $request_selected_addons : []
		);
		$checkout_level = $this->get_level_at_checkout();
		$base_level_total = max(0, $this->get_level_checkout_initial_total($checkout_level) - $selected_addon_total);
		?>
		<div
			id="pmpro_form_fieldset-magazine-addons"
			class="pmpro_checkout-fields pmpro_form_fieldset aac-magazine-addons"
			data-aac-magazine-base-price="<?php echo esc_attr(number_format($base_level_total, 2, '.', '')); ?>"
		>
			<div class="pmpro_card">
				<div class="pmpro_card_content">
					<legend class="pmpro_form_legend">
						<h2 class="pmpro_form_heading pmpro_font-large"><?php esc_html_e('Magazine Subscriptions', 'aac-member-portal'); ?></h2>
					</legend>
					<div class="pmpro_form_fields">
						<input type="hidden" name="aac_magazine_addons_present" value="1" />
						<p class="aac-magazine-addons__intro">
							<?php esc_html_e('Add an annual magazine subscription to your membership before checkout.', 'aac-member-portal'); ?>
						</p>
						<div class="aac-magazine-addons__grid">
							<?php foreach ($magazine_addons as $slug => $addon) : ?>
								<div class="pmpro_form_field pmpro_form_field-checkbox aac-magazine-addons__field">
									<label class="pmpro_form_label pmpro_form_label-inline aac-magazine-addons__label" for="<?php echo esc_attr('aac_magazine_addons_' . $slug); ?>">
										<input
											id="<?php echo esc_attr('aac_magazine_addons_' . $slug); ?>"
											class="aac-magazine-addons__input"
											type="checkbox"
											name="aac_magazine_addons[]"
											value="<?php echo esc_attr($slug); ?>"
											data-aac-magazine-price="<?php echo esc_attr(number_format((float) $addon['price'], 2, '.', '')); ?>"
											<?php checked(in_array($slug, $selected_addons, true)); ?>
										/>
										<span class="aac-magazine-addons__card">
											<?php if (!empty($addon['cover_image_url'])) : ?>
												<span class="aac-magazine-addons__cover">
													<img
														class="aac-magazine-addons__cover-image"
														src="<?php echo esc_url($addon['cover_image_url']); ?>"
														alt="<?php echo esc_attr(sprintf(__('%s cover', 'aac-member-portal'), $addon['label'])); ?>"
														loading="lazy"
													/>
												</span>
											<?php endif; ?>
											<span class="aac-magazine-addons__body">
												<span class="aac-magazine-addons__copy">
													<strong><?php echo esc_html($addon['label']); ?></strong>
													<span><?php echo esc_html($addon['description']); ?></span>
												</span>
												<span class="aac-magazine-addons__footer">
													<span class="aac-magazine-addons__price"><?php echo esc_html($this->format_price($addon['price'])); ?> / year</span>
													<span class="aac-magazine-addons__selector">
														<span class="aac-magazine-addons__check" aria-hidden="true"></span>
														<span class="aac-magazine-addons__selector-copy"><?php esc_html_e('Add subscription', 'aac-member-portal'); ?></span>
													</span>
												</span>
											</span>
										</span>
									</label>
								</div>
							<?php endforeach; ?>
						</div>
						<div class="aac-magazine-addons__summary" data-aac-magazine-summary>
							<?php esc_html_e('No magazine subscriptions selected.', 'aac-member-portal'); ?>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public function filter_pmpro_checkout_level_for_magazine_addons($level) {
		if (!$level || !is_object($level)) {
			return $level;
		}

		$base_membership_initial_total = max(0, $this->get_level_checkout_initial_total($level));
		$base_membership_recurring_total = max(0, $this->get_level_recurring_total($level));
		$partner_family_config = $this->get_requested_partner_family_config();
		$supports_discount_tiers = $this->supports_discount_tiers($level);
		$supports_family_plan = $this->supports_family_plan_tiers($level);
		$membership_discount_type = $supports_discount_tiers
			? $this->get_requested_membership_discount_type()
			: '';
		if (!$supports_family_plan) {
			$partner_family_config = $this->normalize_partner_family_config([]);
		}
		if (($partner_family_config['mode'] ?? '') === 'family') {
			$membership_discount_type = '';
		}
		$membership_discount_amount_initial = $this->get_membership_discount_amount($base_membership_initial_total, $membership_discount_type);
		$membership_discount_amount_recurring = $this->get_membership_discount_amount($base_membership_recurring_total, $membership_discount_type);
		$partner_family_total = $this->get_partner_family_addon_total($base_membership_recurring_total, $partner_family_config);
		$selected_addons = $this->get_requested_magazine_addons();
		$addon_total = $this->get_magazine_addon_total($selected_addons);
		$checkout_account_info = $this->get_checkout_account_info_from_request();
		$international_surcharge = $this->get_international_print_surcharge_amount($checkout_account_info, isset($level->id) ? (int) $level->id : 0);
		$autorenew_reactivation_context = $this->get_autorenew_reactivation_checkout_context($level);
		if (
			$addon_total <= 0
			&& $membership_discount_amount_initial <= 0
			&& $partner_family_total <= 0
			&& $international_surcharge <= 0
			&& !$autorenew_reactivation_context
		) {
			return $level;
		}

		$adjusted_initial_total = round(
			max(0, $base_membership_initial_total - $membership_discount_amount_initial) + $partner_family_total + $addon_total + $international_surcharge,
			2
		);
		$adjusted_recurring_total = round(
			max(0, $base_membership_recurring_total - $membership_discount_amount_recurring) + $partner_family_total + $addon_total + $international_surcharge,
			2
		);
		if ($autorenew_reactivation_context) {
			// Reactivating auto-renew should not double-charge the already-paid current term.
			$adjusted_initial_total = 0.0;
			$level->startdate = $autorenew_reactivation_context['start_date'];
		}

		if (isset($level->initial_payment)) {
			$level->initial_payment = $adjusted_initial_total;
		}

		if (isset($level->billing_amount) && (float) $level->billing_amount > 0) {
			$level->billing_amount = $adjusted_recurring_total;
		}

		return $level;
	}

	public function filter_pmpro_checkout_start_date_for_autorenew_reactivation($startdate, $user_id = null) {
		$context = $this->get_autorenew_reactivation_checkout_context();
		if (!$context) {
			return $startdate;
		}

		return $context['start_date'];
	}

	public function filter_pmpro_level_cost_text_for_autorenew_reactivation($text, $level, $tags = true, $short = false) {
		$context = $this->get_autorenew_reactivation_checkout_context($level);
		if (!$context) {
			return $text;
		}

		$renewal_amount = $this->format_price($context['renewal_amount']);
		$renewal_date = date_i18n(get_option('date_format'), strtotime($context['start_date']));
		$message = sprintf(
			'$0 today. Your recurring renewal will restart on %1$s at %2$s.',
			$renewal_date,
			$renewal_amount
		);

		if (!$tags) {
			return wp_strip_all_tags($message);
		}

		return sprintf('<span class="pmpro_level-cost">%s</span>', esc_html($message));
	}

	public function sync_pmpro_checkout_profile_fields($level_id, $user_id) {
		$request_method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) wp_unslash($_SERVER['REQUEST_METHOD'])) : '';
		if ($request_method !== 'POST' || !$this->is_pmpro_checkout_request() || !$user_id) {
			return;
		}

		$user = get_user_by('id', $user_id);
		if (!$user instanceof WP_User || !$user->exists()) {
			return;
		}

		$stored_account_info = $this->get_account_info_defaults_for_user($user);
		$next_account_info = [
			'first_name' => isset($_REQUEST['bfirstname']) ? sanitize_text_field(wp_unslash($_REQUEST['bfirstname'])) : ($stored_account_info['first_name'] ?? ''),
			'last_name' => isset($_REQUEST['blastname']) ? sanitize_text_field(wp_unslash($_REQUEST['blastname'])) : ($stored_account_info['last_name'] ?? ''),
			'email' => $user->user_email,
			'phone' => isset($_REQUEST['bphone']) ? sanitize_text_field(wp_unslash($_REQUEST['bphone'])) : ($stored_account_info['phone'] ?? ''),
			'birthdate' => isset($_REQUEST['birthdate']) ? $this->sanitize_birthdate_value(wp_unslash($_REQUEST['birthdate'])) : ($stored_account_info['birthdate'] ?? ''),
			'street' => isset($_REQUEST['baddress1']) ? sanitize_text_field(wp_unslash($_REQUEST['baddress1'])) : ($stored_account_info['street'] ?? ''),
			'address2' => isset($_REQUEST['baddress2']) ? sanitize_text_field(wp_unslash($_REQUEST['baddress2'])) : ($stored_account_info['address2'] ?? ''),
			'city' => isset($_REQUEST['bcity']) ? sanitize_text_field(wp_unslash($_REQUEST['bcity'])) : ($stored_account_info['city'] ?? ''),
			'state' => isset($_REQUEST['bstate']) ? sanitize_text_field(wp_unslash($_REQUEST['bstate'])) : ($stored_account_info['state'] ?? ''),
			'zip' => isset($_REQUEST['bzipcode']) ? sanitize_text_field(wp_unslash($_REQUEST['bzipcode'])) : ($stored_account_info['zip'] ?? ''),
			'country' => isset($_REQUEST['bcountry']) ? sanitize_text_field(wp_unslash($_REQUEST['bcountry'])) : ($stored_account_info['country'] ?? ''),
			'email_opt_out' => isset($_REQUEST['email_opt_out']) ? !empty($_REQUEST['email_opt_out']) : !empty($stored_account_info['email_opt_out']),
			'do_not_call' => isset($_REQUEST['do_not_call']) ? !empty($_REQUEST['do_not_call']) : !empty($stored_account_info['do_not_call']),
			'do_not_contact' => isset($_REQUEST['do_not_contact']) ? !empty($_REQUEST['do_not_contact']) : !empty($stored_account_info['do_not_contact']),
			'size' => isset($_REQUEST['t_shirt'])
				? $this->normalize_tshirt_size_value(wp_unslash($_REQUEST['t_shirt']))
				: $this->normalize_tshirt_size_value($stored_account_info['size'] ?? ''),
			'photo_url' => $stored_account_info['photo_url'] ?? get_avatar_url($user_id),
			'auto_renew' => isset($_REQUEST['autorenew_present'])
				? !empty($_REQUEST['autorenew'])
				: true,
		];

		$next_account_info = array_merge(
			$next_account_info,
			$this->get_checkout_publication_preferences($stored_account_info, 'Digital')
		);

		$next_account_info['name'] = trim($next_account_info['first_name'] . ' ' . $next_account_info['last_name']);
		if ($next_account_info['name'] === '') {
			$next_account_info['name'] = $stored_account_info['name'] ?? $user->display_name;
		}

		$selected_magazine_addons = $this->has_magazine_addon_request()
			? $this->get_requested_magazine_addons()
			: $this->get_effective_magazine_addon_selection($user_id);
		$checkout_level = $this->get_level_at_checkout();
		$checkout_level_supports_discount = $this->supports_discount_tiers($checkout_level);
		$checkout_level_supports_family = $this->supports_family_plan_tiers($checkout_level);
		$partner_family_config = $this->has_partner_family_request()
			? $this->get_requested_partner_family_config()
			: $this->get_effective_partner_family_config($user_id);
		$membership_discount_type = $checkout_level_supports_discount
			? ($this->has_membership_discount_request()
				? $this->get_requested_membership_discount_type()
				: $this->get_effective_membership_discount_type($user_id))
			: '';
		if (!$checkout_level_supports_family) {
			$partner_family_config = $this->normalize_partner_family_config([]);
		}
		if (($partner_family_config['mode'] ?? '') === 'family') {
			$membership_discount_type = '';
		}

		if ($this->has_magazine_addon_request()) {
			update_user_meta($user_id, 'aac_magazine_addons', $selected_magazine_addons);
		}

		if ($this->has_membership_discount_request()) {
			update_user_meta($user_id, 'aac_membership_discount_type', $membership_discount_type);
		}

		if ($this->has_partner_family_request()) {
			update_user_meta($user_id, 'aac_partner_family_config', $partner_family_config);
		}

		update_user_meta($user_id, 'aac_account_info', $this->strip_pmpro_managed_account_fields_for_storage($next_account_info));
		$this->sync_reportable_member_fields($user_id, $next_account_info, $selected_magazine_addons, $membership_discount_type);
		$this->sync_partner_family_member_slots($user_id, $partner_family_config, $this->get_level_recurring_total($this->get_level_at_checkout()));

		wp_update_user([
			'ID' => $user_id,
			'first_name' => $next_account_info['first_name'],
			'last_name' => $next_account_info['last_name'],
			'display_name' => $next_account_info['name'],
		]);
	}

	public function capture_pmpro_checkout_order_breakdown($user_id, $morder) {
		if (!is_object($morder)) {
			return;
		}

		$order_breakdown = $this->build_pmpro_order_breakdown_payload($morder, (int) $user_id);
		if (empty($order_breakdown['items'])) {
			return;
		}

		foreach ($this->get_pmpro_order_breakdown_storage_keys($morder) as $storage_key) {
			update_option($storage_key, $order_breakdown, false);
		}
	}

	public function sync_member_record_to_pmpro_fields($user_id) {
		$user_id = (int) $user_id;
		if ($user_id <= 0) {
			return false;
		}

		$user = get_user_by('id', $user_id);
		if (!$user instanceof WP_User || !$user->exists()) {
			return false;
		}

		$account_info = $this->get_account_info_defaults_for_user($user);
		return $this->sync_account_info_to_pmpro_fields($user_id, $account_info);
	}

	public function sync_account_info_to_pmpro_fields($user_id, $account_info) {
		$user_id = (int) $user_id;
		if ($user_id <= 0 || !is_array($account_info)) {
			return false;
		}

		$user = get_user_by('id', $user_id);
		if (!$user instanceof WP_User || !$user->exists()) {
			return false;
		}

		$selected_magazine_addons = $this->get_effective_magazine_addon_selection($user_id);
		$membership_discount_type = $this->get_effective_membership_discount_type($user_id);

		update_user_meta($user_id, 'aac_account_info', $this->strip_pmpro_managed_account_fields_for_storage($account_info));
		$this->sync_reportable_member_fields($user_id, $account_info, $selected_magazine_addons, $membership_discount_type);

		wp_update_user([
			'ID' => $user_id,
			'first_name' => $account_info['first_name'] ?? '',
			'last_name' => $account_info['last_name'] ?? '',
			'display_name' => $account_info['name'] ?? trim(($account_info['first_name'] ?? '') . ' ' . ($account_info['last_name'] ?? '')),
		]);

		return true;
	}

	public function backfill_pmpro_fields_from_member_database() {
		$user_ids = $this->get_member_ids_for_pmpro_field_backfill();
		$synced = 0;

		foreach (array_chunk($user_ids, 200) as $user_id_batch) {
			foreach ($user_id_batch as $user_id) {
				if ($this->sync_member_record_to_pmpro_fields($user_id)) {
					$synced++;
				}
			}
		}

		return [
			'candidate_count' => count($user_ids),
			'synced_count' => $synced,
		];
	}

	public function append_pmpro_confirmation_line_items($confirmation_message, $pmpro_invoice) {
		if (!is_object($pmpro_invoice)) {
			return $confirmation_message;
		}

		if (is_string($confirmation_message) && strpos($confirmation_message, 'aac-order-summary') !== false) {
			return $confirmation_message;
		}

		$order_breakdown = $this->get_pmpro_order_breakdown_payload($pmpro_invoice);
		if (empty($order_breakdown['items'])) {
			return $confirmation_message;
		}

		$summary_markup = $this->render_pmpro_order_breakdown_markup($order_breakdown);
		if ($summary_markup === '') {
			return $confirmation_message;
		}

		return (string) $confirmation_message . $summary_markup;
	}

	public function get_pmpro_checkout_profile_defaults() {
		$user = wp_get_current_user();
		$account_info = $this->get_account_info_defaults_for_user($user instanceof WP_User && $user->exists() ? $user : null);

		$account_info = array_merge(
			$account_info,
			$this->get_checkout_publication_preferences($account_info, 'Print')
		);

		if (isset($_REQUEST['t_shirt'])) {
			$account_info['size'] = $this->normalize_tshirt_size_value(wp_unslash($_REQUEST['t_shirt']));
		}

		return [
			'publication_pref' => $account_info['publication_pref'],
			'aaj_pref' => $account_info['aaj_pref'],
			'anac_pref' => $account_info['anac_pref'],
			'acj_pref' => $account_info['acj_pref'],
			'guidebook_pref' => $account_info['guidebook_pref'],
			'size' => $account_info['size'],
		];
	}

	public function render_pmpro_member_address_fields($user) {
		if (!$user instanceof WP_User || !$user->exists()) {
			return;
		}

		$address_fields = [
			'baddress1' => ['label' => 'Address Line 1', 'autocomplete' => 'address-line1'],
			'baddress2' => ['label' => 'Address Line 2', 'autocomplete' => 'address-line2'],
			'bcity' => ['label' => 'City', 'autocomplete' => 'address-level2'],
			'bstate' => ['label' => 'State / Province', 'autocomplete' => 'address-level1'],
			'bzipcode' => ['label' => 'Postal Code', 'autocomplete' => 'postal-code'],
			'bcountry' => ['label' => 'Country', 'autocomplete' => 'country-name'],
		];
		?>
		<h2>AAC / PMPro Address</h2>
		<table class="form-table" role="presentation">
			<tbody>
				<?php foreach ($address_fields as $meta_key => $field) : ?>
					<tr>
						<th>
							<label for="<?php echo esc_attr($meta_key); ?>">
								<?php echo esc_html($field['label']); ?>
							</label>
						</th>
						<td>
							<input
								type="text"
								name="<?php echo esc_attr($meta_key); ?>"
								id="<?php echo esc_attr($meta_key); ?>"
								value="<?php echo esc_attr((string) get_user_meta($user->ID, $meta_key, true)); ?>"
								class="regular-text"
								autocomplete="<?php echo esc_attr($field['autocomplete']); ?>"
							/>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	public function save_pmpro_member_address_fields($user_id) {
		$user_id = (int) $user_id;
		if ($user_id <= 0 || !current_user_can('edit_user', $user_id)) {
			return;
		}

		foreach (['baddress1', 'baddress2', 'bcity', 'bstate', 'bzipcode', 'bcountry'] as $meta_key) {
			if (!isset($_POST[$meta_key])) {
				continue;
			}

			update_user_meta(
				$user_id,
				$meta_key,
				sanitize_text_field(wp_unslash($_POST[$meta_key]))
			);
		}
	}

	private function get_membership_discount_catalog() {
		return [
			'student' => [
				'label' => 'Student Discount',
				'description' => 'Apply 35% off your annual membership.',
				'badge' => '35% off membership',
				'rate' => 0.35,
				'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="m2 9 10-5 10 5-10 5-10-5Z"/><path d="M6 11.5v4.5c0 .8 2.7 3 6 3s6-2.2 6-3v-4.5"/><path d="M22 9v6"/></svg>',
			],
			'military' => [
				'label' => 'Military Discount',
				'description' => 'Apply 35% off your annual membership.',
				'badge' => '35% off membership',
				'rate' => 0.35,
				'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4v16"/><path d="M4 5c3-2 6 2 9 0s6 2 7 0v8c-1 2-4-2-7 0s-6-2-9 0"/></svg>',
			],
		];
	}

	private function has_partner_family_request() {
		return isset($_REQUEST['aac_partner_family_present']) && wp_unslash($_REQUEST['aac_partner_family_present']) === '1';
	}

	private function get_requested_partner_family_config() {
		return $this->normalize_partner_family_config([
			'mode' => isset($_REQUEST['aac_partner_family_mode']) ? wp_unslash($_REQUEST['aac_partner_family_mode']) : '',
			'additional_adult' => !empty($_REQUEST['aac_partner_family_additional_adult']),
			'dependent_count' => isset($_REQUEST['aac_partner_family_dependents']) ? wp_unslash($_REQUEST['aac_partner_family_dependents']) : 0,
		]);
	}

	private function get_effective_partner_family_config($user_id = 0) {
		if ($this->has_partner_family_request()) {
			return $this->get_requested_partner_family_config();
		}

		if (!$user_id) {
			return $this->normalize_partner_family_config([]);
		}

		return $this->normalize_partner_family_config(get_user_meta($user_id, 'aac_partner_family_config', true));
	}

	private function normalize_partner_family_config($config) {
		$config = is_array($config) ? $config : [];
		$mode = sanitize_key((string) ($config['mode'] ?? ''));
		$mode = $mode === 'family' ? 'family' : '';
		$additional_adult = !empty($config['additional_adult']) && $mode === 'family';
		$dependent_count = max(0, min(3, (int) ($config['dependent_count'] ?? 0)));

		if ($mode !== 'family') {
			$additional_adult = false;
			$dependent_count = 0;
		}

		return [
			'mode' => $mode,
			'additional_adult' => $additional_adult,
			'dependent_count' => $dependent_count,
		];
	}

	private function get_partner_level_id() {
		return $this->get_level_id_by_name('Partner', 3);
	}

	private function get_partner_family_level_id() {
		return $this->get_level_id_by_name('Partner Family', 6);
	}

	private function get_level_id_by_name($name, $fallback = 0) {
		if (!function_exists('pmpro_getAllLevels')) {
			return (int) $fallback;
		}

		$levels = pmpro_getAllLevels(false, true);
		if (!is_array($levels)) {
			return (int) $fallback;
		}

		foreach ($levels as $level) {
			if (is_object($level) && !empty($level->id) && isset($level->name) && (string) $level->name === $name) {
				return (int) $level->id;
			}
		}

		return (int) $fallback;
	}

	private function get_requested_level_id() {
		if (!isset($_REQUEST['level'])) {
			return 0;
		}

		return absint(wp_unslash($_REQUEST['level']));
	}

	private function supports_discount_tiers($level) {
		$level_id = 0;
		$level_name = '';

		if (is_object($level)) {
			$level_id = isset($level->id) ? (int) $level->id : 0;
			$level_name = isset($level->name) ? sanitize_text_field((string) $level->name) : '';
		} else {
			$level_id = (int) $level;
			if ($level_id > 0 && function_exists('pmpro_getLevel')) {
				$level_object = pmpro_getLevel($level_id);
				if (is_object($level_object) && isset($level_object->name)) {
					$level_name = sanitize_text_field((string) $level_object->name);
				}
			}
		}

		$normalized_name = strtolower(trim($level_name));
		if ($normalized_name !== '') {
			return $normalized_name === 'partner';
		}

		$partner_level_id = $this->get_partner_level_id();
		return $partner_level_id > 0 && $level_id === $partner_level_id;
	}

	private function supports_family_plan_tiers($level) {
		$level_id = 0;
		$level_name = '';

		if (is_object($level)) {
			$level_id = isset($level->id) ? (int) $level->id : 0;
			$level_name = isset($level->name) ? sanitize_text_field((string) $level->name) : '';
		} else {
			$level_id = (int) $level;
			if ($level_id > 0 && function_exists('pmpro_getLevel')) {
				$level_object = pmpro_getLevel($level_id);
				if (is_object($level_object) && isset($level_object->name)) {
					$level_name = sanitize_text_field((string) $level_object->name);
				}
			}
		}

		$normalized_name = strtolower(trim($level_name));
		if ($normalized_name !== '') {
			return $normalized_name === 'partner';
		}

		$partner_level_id = $this->get_partner_level_id();
		return $partner_level_id > 0 && $level_id === $partner_level_id;
	}

	private function is_partner_family_checkout_level($level) {
		return $this->supports_family_plan_tiers($level);
	}

	private function get_partner_family_pricing($base_membership_total) {
		return [
			'additional_adult_price' => 80.0,
			'dependent_price' => 45.0,
		];
	}

	private function get_partner_family_addon_total($base_membership_total, $family_config) {
		$family_config = $this->normalize_partner_family_config($family_config);
		if ($family_config['mode'] !== 'family') {
			return 0.0;
		}

		$pricing = $this->get_partner_family_pricing($base_membership_total);
		$total = 0.0;
		if (!empty($family_config['additional_adult'])) {
			$total += (float) $pricing['additional_adult_price'];
		}

		$total += max(0, (int) $family_config['dependent_count']) * (float) $pricing['dependent_price'];

		return round($total, 2);
	}

	private function sync_partner_family_member_slots($user_id, $family_config, $base_membership_total = 0.0) {
		$user_id = (int) $user_id;
		if ($user_id <= 0) {
			return;
		}

		$family_config = $this->normalize_partner_family_config($family_config);
		update_user_meta($user_id, 'aac_partner_family_config', $family_config);

		$existing_slots = get_user_meta($user_id, 'aac_connected_accounts', true);
		$existing_slots = is_array($existing_slots) ? $existing_slots : [];
		$normalized_existing = [];

		foreach ($existing_slots as $slot) {
			if (!is_array($slot)) {
				continue;
			}

			$normalized_existing[] = [
				'id' => sanitize_text_field($slot['id'] ?? wp_generate_uuid4()),
				'type' => sanitize_key($slot['type'] ?? 'dependent'),
				'label' => sanitize_text_field($slot['label'] ?? 'Family member'),
				'status' => in_array(($slot['status'] ?? ''), ['pending', 'connected', 'removal_pending'], true) ? $slot['status'] : 'pending',
				'invite_code' => sanitize_text_field($slot['invite_code'] ?? $this->generate_family_invite_code()),
				'child_user_id' => absint($slot['child_user_id'] ?? 0),
				'child_name' => sanitize_text_field($slot['child_name'] ?? ''),
				'child_email' => sanitize_email($slot['child_email'] ?? ''),
				'price' => round((float) ($slot['price'] ?? 0), 2),
				'scheduled_removal_date' => sanitize_text_field($slot['scheduled_removal_date'] ?? ''),
			];
		}
		$next_slots = [];
		$pricing = $this->get_partner_family_pricing($base_membership_total);

		if ($family_config['mode'] === 'family') {
			if (!empty($family_config['additional_adult'])) {
				$next_slots[] = $this->preserve_or_create_family_slot(
					$user_id,
					$normalized_existing,
					'adult',
					'Additional adult',
					(float) $pricing['additional_adult_price']
				);
			}

			$dependent_count = max(0, (int) $family_config['dependent_count']);
			for ($dependent_index = 1; $dependent_index <= $dependent_count; $dependent_index++) {
				$next_slots[] = $this->preserve_or_create_family_slot(
					$user_id,
					$normalized_existing,
					'dependent',
					sprintf('Dependent %d', $dependent_index),
					(float) $pricing['dependent_price']
				);
			}
		}

		foreach ($normalized_existing as $slot) {
			$scheduled_slot = $this->schedule_family_slot_for_term_end($user_id, $slot);
			if ($scheduled_slot) {
				$next_slots[] = $scheduled_slot;
			}
		}

		if (empty($next_slots)) {
			delete_user_meta($user_id, 'aac_connected_accounts');
			return;
		}

		update_user_meta($user_id, 'aac_connected_accounts', array_values($next_slots));
	}

	private function preserve_or_create_family_slot($parent_user_id, &$existing_slots, $type, $label, $price) {
		$preferred_slot_index = null;
		$fallback_slot_index = null;

		foreach ($existing_slots as $index => $slot) {
			if (($slot['type'] ?? '') !== $type) {
				continue;
			}

			if (($slot['status'] ?? '') !== 'removal_pending') {
				$preferred_slot_index = $index;
				break;
			}

			if ($fallback_slot_index === null) {
				$fallback_slot_index = $index;
			}
		}

		$target_index = $preferred_slot_index !== null ? $preferred_slot_index : $fallback_slot_index;
		if ($target_index !== null) {
			$slot = $existing_slots[$target_index];
			unset($existing_slots[$target_index]);
			$slot['label'] = $label;
			$slot['price'] = round((float) $price, 2);
			if (($slot['status'] ?? '') === 'removal_pending') {
				$slot = $this->restore_scheduled_family_slot($parent_user_id, $slot);
			}
			return $slot;
		}

		return [
			'id' => wp_generate_uuid4(),
			'type' => $type,
			'label' => $label,
			'status' => 'pending',
			'invite_code' => $this->generate_family_invite_code(),
			'child_user_id' => 0,
			'child_name' => '',
			'child_email' => '',
			'price' => round((float) $price, 2),
			'scheduled_removal_date' => '',
		];
	}

	private function schedule_family_slot_for_term_end($parent_user_id, $slot) {
		if (!is_array($slot)) {
			return null;
		}

		$child_user_id = absint($slot['child_user_id'] ?? 0);
		if ($child_user_id <= 0) {
			return null;
		}

		$existing_scheduled_date = sanitize_text_field((string) ($slot['scheduled_removal_date'] ?? ''));
		if (($slot['status'] ?? '') === 'removal_pending' && $existing_scheduled_date !== '') {
			update_user_meta($child_user_id, 'aac_family_membership_access_until', $existing_scheduled_date);
			update_user_meta($child_user_id, 'aac_family_membership_pending_removal', '1');
			$slot['scheduled_removal_date'] = $existing_scheduled_date;
			return $slot;
		}

		$term_end_date = $this->get_parent_family_term_end_date($parent_user_id);
		if ($term_end_date === '') {
			return null;
		}

		update_user_meta($child_user_id, 'aac_family_membership_access_until', $term_end_date);
		update_user_meta($child_user_id, 'aac_family_membership_pending_removal', '1');

		$slot['status'] = 'removal_pending';
		$slot['scheduled_removal_date'] = $term_end_date;

		return $slot;
	}

	private function restore_scheduled_family_slot($parent_user_id, $slot) {
		if (!is_array($slot)) {
			return $slot;
		}

		$child_user_id = absint($slot['child_user_id'] ?? 0);
		if ($child_user_id > 0) {
			delete_user_meta($child_user_id, 'aac_family_membership_access_until');
			delete_user_meta($child_user_id, 'aac_family_membership_pending_removal');
			update_user_meta($child_user_id, 'aac_linked_parent_user_id', (int) $parent_user_id);
			update_user_meta($child_user_id, 'aac_family_account_role', 'Child');
		}

		$slot['status'] = $child_user_id > 0 ? 'connected' : 'pending';
		$slot['scheduled_removal_date'] = '';
		return $slot;
	}

	private function get_parent_family_term_end_date($user_id) {
		$user_id = (int) $user_id;
		if ($user_id <= 0 || !class_exists('AAC_Member_Portal_PMPro') || !AAC_Member_Portal_PMPro::is_available()) {
			return '';
		}

		$primary_membership = AAC_Member_Portal_PMPro::get_primary_membership($user_id);
		if (!is_array($primary_membership) || empty($primary_membership)) {
			return '';
		}

		$term_end_date = sanitize_text_field((string) ($primary_membership['renewal_date'] ?: $primary_membership['expiration_date']));
		if ($term_end_date === '') {
			return '';
		}

		$timestamp = strtotime($term_end_date);
		return $timestamp ? gmdate('Y-m-d', $timestamp) : '';
	}

	private function generate_family_invite_code() {
		return 'AACF-' . strtoupper(wp_generate_password(8, false, false));
	}

	private function get_magazine_addon_catalog() {
		return [
			'alpinist' => [
				'label' => 'Alpinist magazine',
				'description' => 'Annual subscription add-on',
				'cover_image_url' => 'https://files.coverscdn.com/covers/289691/extralow/0000.jpg',
				'price' => 45.0,
			],
			'backcountry' => [
				'label' => 'Backcountry magazine',
				'description' => 'Annual subscription add-on',
				'cover_image_url' => 'https://files.coverscdn.com/covers/290430/extralow/0000.jpg',
				'price' => 30.0,
			],
		];
	}

	private function has_magazine_addon_request() {
		return isset($_REQUEST['aac_magazine_addons_present']) && wp_unslash($_REQUEST['aac_magazine_addons_present']) === '1';
	}

	private function has_membership_discount_request() {
		return isset($_REQUEST['aac_membership_discount_present']) && wp_unslash($_REQUEST['aac_membership_discount_present']) === '1';
	}

	private function get_requested_membership_discount_type() {
		if (!isset($_REQUEST['aac_membership_discount'])) {
			return '';
		}

		return $this->normalize_membership_discount_type(wp_unslash($_REQUEST['aac_membership_discount']));
	}

	private function get_effective_membership_discount_type($user_id = 0) {
		if ($this->has_membership_discount_request()) {
			return $this->get_requested_membership_discount_type();
		}

		if (!$user_id) {
			return '';
		}

		return $this->normalize_membership_discount_type(get_user_meta($user_id, 'aac_membership_discount_type', true));
	}

	private function normalize_membership_discount_type($value) {
		$type = sanitize_key((string) $value);
		return array_key_exists($type, $this->get_membership_discount_catalog()) ? $type : '';
	}

	private function get_membership_discount_rate($type) {
		$catalog = $this->get_membership_discount_catalog();
		return isset($catalog[$type]['rate']) ? (float) $catalog[$type]['rate'] : 0.0;
	}

	private function get_membership_discount_amount($base_amount, $type) {
		$rate = $this->get_membership_discount_rate($type);
		if ($rate <= 0 || $base_amount <= 0) {
			return 0.0;
		}

		return round((float) $base_amount * $rate, 2);
	}

	private function get_requested_magazine_addons() {
		if (!isset($_REQUEST['aac_magazine_addons'])) {
			return [];
		}

		return $this->normalize_magazine_addon_selection(wp_unslash($_REQUEST['aac_magazine_addons']));
	}

	private function get_effective_magazine_addon_selection($user_id = 0) {
		if ($this->has_magazine_addon_request()) {
			return $this->get_requested_magazine_addons();
		}

		if (!$user_id) {
			return [];
		}

		$stored = get_user_meta($user_id, 'aac_magazine_addons', true);
		return $this->normalize_magazine_addon_selection($stored);
	}

	private function normalize_magazine_addon_selection($selection) {
		$catalog = $this->get_magazine_addon_catalog();
		$allowed = array_keys($catalog);
		$raw_values = is_array($selection) ? $selection : [$selection];
		$normalized = [];

		foreach ($raw_values as $value) {
			$slug = sanitize_key((string) $value);
			if ($slug !== '' && in_array($slug, $allowed, true)) {
				$normalized[] = $slug;
			}
		}

		return array_values(array_unique($normalized));
	}

	private function get_magazine_addon_total($selection) {
		$catalog = $this->get_magazine_addon_catalog();
		$total = 0.0;

		foreach ($this->normalize_magazine_addon_selection($selection) as $slug) {
			$total += isset($catalog[$slug]['price']) ? (float) $catalog[$slug]['price'] : 0.0;
		}

		return round($total, 2);
	}

	private function get_requested_donation_amount() {
		if (!isset($_REQUEST['donation'])) {
			return 0.0;
		}

		return $this->normalize_money_amount(wp_unslash($_REQUEST['donation']));
	}

	private function get_checkout_account_info_from_request($user = null) {
		$user = $user instanceof WP_User && $user->exists() ? $user : null;
		$account_info = $this->get_account_info_defaults_for_user($user);

		$account_info['country'] = isset($_REQUEST['bcountry'])
			? sanitize_text_field(wp_unslash($_REQUEST['bcountry']))
			: ($account_info['country'] ?? 'US');

		return array_merge(
			$account_info,
			$this->get_checkout_publication_preferences($account_info, 'Digital')
		);
	}

	private function get_checkout_publication_preferences($account_info = [], $default = 'Digital') {
		$account_info = is_array($account_info) ? $account_info : [];
		$default = $this->normalize_print_digital_value($default, 'Digital');
		$legacy_fallback = $account_info['aaj_pref'] ?? ($account_info['publication_pref'] ?? $default);

		return $this->get_normalized_publication_preferences([
			'aaj_pref' => isset($_REQUEST['aaj_preference'])
				? sanitize_text_field(wp_unslash($_REQUEST['aaj_preference']))
				: (isset($_REQUEST['aac_aaj_pref'])
					? sanitize_text_field(wp_unslash($_REQUEST['aac_aaj_pref']))
					: ($account_info['aaj_pref'] ?? $legacy_fallback)),
			'anac_pref' => isset($_REQUEST['anac_preference'])
				? sanitize_text_field(wp_unslash($_REQUEST['anac_preference']))
				: (isset($_REQUEST['aac_anac_pref'])
					? sanitize_text_field(wp_unslash($_REQUEST['aac_anac_pref']))
					: ($account_info['anac_pref'] ?? $legacy_fallback)),
			'acj_pref' => isset($_REQUEST['american_climbing_journal_preference'])
				? sanitize_text_field(wp_unslash($_REQUEST['american_climbing_journal_preference']))
				: (isset($_REQUEST['aac_acj_pref'])
					? sanitize_text_field(wp_unslash($_REQUEST['aac_acj_pref']))
					: ($account_info['acj_pref'] ?? $legacy_fallback)),
			'guidebook_pref' => isset($_REQUEST['guidebook_preferences'])
				? sanitize_text_field(wp_unslash($_REQUEST['guidebook_preferences']))
				: (isset($_REQUEST['aac_guidebook_pref'])
					? sanitize_text_field(wp_unslash($_REQUEST['aac_guidebook_pref']))
					: ($account_info['guidebook_pref'] ?? $default)),
		]);
	}

	private function is_international_country($country) {
		$normalized = strtoupper(trim((string) $country));
		return !in_array($normalized, ['', 'US', 'USA', 'UNITED STATES', 'UNITED STATES OF AMERICA'], true);
	}

	private function has_print_publication_selection($account_info) {
		if (!is_array($account_info)) {
			return false;
		}

		$preferences = $this->get_normalized_publication_preferences($account_info);
		foreach (['aaj_pref', 'anac_pref', 'acj_pref', 'guidebook_pref'] as $field) {
			if (($preferences[$field] ?? 'Digital') === 'Print') {
				return true;
			}
		}

		return false;
	}

	public function maybe_repair_pmpro_user_fields_settings() {
		$current_settings = get_option('pmpro_user_fields_settings', null);
		if (!is_array($current_settings)) {
			return;
		}

		$needs_update = false;
		$normalized_settings = [];

		foreach ($current_settings as $group) {
			$group_data = is_object($group) ? get_object_vars($group) : (is_array($group) ? $group : []);
			if (!$group_data) {
				$needs_update = true;
				continue;
			}

			$group_name = sanitize_text_field((string) ($group_data['name'] ?? ''));
			if ($group_name === 'Emergency Contact') {
				$needs_update = true;
				continue;
			}

			$fields = $group_data['fields'] ?? [];
			$normalized_fields = [];
			if (is_array($fields)) {
				foreach ($fields as $field) {
					$field_data = is_object($field) ? get_object_vars($field) : (is_array($field) ? $field : []);
					if (!$field_data) {
						$needs_update = true;
						continue;
					}

					if (is_array($field)) {
						$needs_update = true;
					}

					$normalized_fields[] = (object) $field_data;
				}
			} else {
				$needs_update = true;
			}

			$group_data['fields'] = $normalized_fields;
			if (is_array($group)) {
				$needs_update = true;
			}

			$normalized_settings[] = (object) $group_data;
		}

		if ($needs_update) {
			update_option('pmpro_user_fields_settings', $normalized_settings, false);
		}
	}

	private function should_apply_international_print_surcharge($level_id = 0) {
		return (int) $level_id === 3;
	}

	private function get_international_print_surcharge_amount($account_info, $level_id = 0) {
		if (!$this->should_apply_international_print_surcharge($level_id)) {
			return 0.0;
		}

		if (!$this->is_international_country($account_info['country'] ?? 'US')) {
			return 0.0;
		}

		if (!$this->has_print_publication_selection($account_info)) {
			return 0.0;
		}

		return 30.0;
	}

	private function normalize_money_amount($value) {
		$normalized = preg_replace('/[^0-9.\-]+/', '', (string) $value);
		if (!is_string($normalized) || $normalized === '' || !is_numeric($normalized)) {
			return 0.0;
		}

		return round(max(0, (float) $normalized), 2);
	}

	private function get_pmpro_order_breakdown_storage_keys($morder) {
		$keys = [];
		$order_id = is_object($morder) && isset($morder->id) ? absint($morder->id) : 0;
		$order_code = is_object($morder) && isset($morder->code) ? sanitize_key((string) $morder->code) : '';

		if ($order_id > 0) {
			$keys[] = self::ORDER_BREAKDOWN_OPTION_PREFIX . 'id_' . $order_id;
		}

		if ($order_code !== '') {
			$keys[] = self::ORDER_BREAKDOWN_OPTION_PREFIX . 'code_' . $order_code;
		}

		return array_values(array_unique($keys));
	}

	private function get_pmpro_order_breakdown_payload($morder) {
		foreach ($this->get_pmpro_order_breakdown_storage_keys($morder) as $storage_key) {
			$stored = get_option($storage_key, null);
			if (is_array($stored) && !empty($stored['items'])) {
				return $stored;
			}
		}

		return $this->build_pmpro_order_breakdown_payload($morder, is_object($morder) && isset($morder->user_id) ? (int) $morder->user_id : 0);
	}

	private function build_pmpro_order_breakdown_payload($morder, $user_id = 0) {
		if (!is_object($morder)) {
			return [];
		}

		$total_amount = isset($morder->total) ? round((float) $morder->total, 2) : 0.0;
		$membership_id = isset($morder->membership_id) ? (int) $morder->membership_id : 0;
		$level_name = $this->get_pmpro_level_name($membership_id);
		$level = $membership_id > 0 && function_exists('pmpro_getLevel') ? pmpro_getLevel($membership_id) : null;
		$base_membership_amount = max(0, $this->get_level_checkout_initial_total($level));
		$membership_discount_type = $this->has_membership_discount_request()
			? $this->get_requested_membership_discount_type()
			: $this->get_effective_membership_discount_type($user_id);
		$membership_discount_catalog = $this->get_membership_discount_catalog();
		$partner_family_config = $this->has_partner_family_request()
			? $this->get_requested_partner_family_config()
			: $this->get_effective_partner_family_config($user_id);
		if (($partner_family_config['mode'] ?? '') === 'family') {
			$membership_discount_type = '';
		}
		$membership_discount_amount = $this->get_membership_discount_amount($base_membership_amount, $membership_discount_type);
		$partner_family_pricing = $this->get_partner_family_pricing(max(0, $this->get_level_recurring_total($level)));
		$partner_family_additional_adult_amount = !empty($partner_family_config['additional_adult']) ? (float) $partner_family_pricing['additional_adult_price'] : 0.0;
		$partner_family_dependents_amount = max(0, (int) ($partner_family_config['dependent_count'] ?? 0)) * (float) $partner_family_pricing['dependent_price'];
		$selected_addons = $this->has_magazine_addon_request()
			? $this->get_requested_magazine_addons()
			: $this->get_effective_magazine_addon_selection($user_id);
		$catalog = $this->get_magazine_addon_catalog();
		$magazine_total = $this->get_magazine_addon_total($selected_addons);
		$donation_amount = $this->get_requested_donation_amount();
		$account_info = $this->get_checkout_account_info_from_request($user_id > 0 ? get_user_by('id', $user_id) : null);
		$international_surcharge = $this->get_international_print_surcharge_amount($account_info, $membership_id);
		$items = [];

		$membership_label = $this->format_membership_line_item_label($level_name);

		$membership_line_amount = round(
			max(
				0,
				$total_amount
				- $partner_family_additional_adult_amount
				- $partner_family_dependents_amount
				- $international_surcharge
				- $magazine_total
				- $donation_amount
				+ $membership_discount_amount
			),
			2
		);

		if ($membership_line_amount > 0 || (!$selected_addons && $donation_amount <= 0)) {
			$items[] = [
				'label' => $membership_label,
				'amount' => $membership_line_amount > 0 ? $membership_line_amount : $total_amount,
			];
		}

		if ($membership_discount_amount > 0) {
			$items[] = [
				'label' => !empty($membership_discount_catalog[$membership_discount_type]['label'])
					? sprintf('%s (35%%)', $membership_discount_catalog[$membership_discount_type]['label'])
					: 'Membership discount',
				'amount' => 0 - $membership_discount_amount,
			];
		}

		if ($partner_family_additional_adult_amount > 0) {
			$items[] = [
				'label' => 'Additional adult',
				'amount' => round($partner_family_additional_adult_amount, 2),
			];
		}

		if ($partner_family_dependents_amount > 0) {
			$dependent_count = max(0, (int) ($partner_family_config['dependent_count'] ?? 0));
			$items[] = [
				'label' => sprintf(
					_n('%d dependent', '%d dependents', $dependent_count, 'aac-member-portal'),
					$dependent_count
				),
				'amount' => round($partner_family_dependents_amount, 2),
			];
		}

		if ($international_surcharge > 0) {
			$items[] = [
				'label' => 'International surcharge for print copies',
				'amount' => round($international_surcharge, 2),
			];
		}

		foreach ($selected_addons as $slug) {
			if (empty($catalog[$slug]['price'])) {
				continue;
			}

			$items[] = [
				'label' => $catalog[$slug]['label'],
				'amount' => round((float) $catalog[$slug]['price'], 2),
			];
		}

		if ($donation_amount > 0) {
			$items[] = [
				'label' => 'Donation',
				'amount' => $donation_amount,
			];
		}

		if (empty($items) && $total_amount > 0) {
			$items[] = [
				'label' => $membership_label,
				'amount' => $total_amount,
			];
		}

		return [
			'order_id' => isset($morder->id) ? absint($morder->id) : 0,
			'order_code' => isset($morder->code) ? sanitize_text_field((string) $morder->code) : '',
			'total' => $total_amount,
			'items' => $items,
		];
	}

	private function get_pmpro_level_name($membership_id) {
		$membership_id = (int) $membership_id;
		if ($membership_id <= 0) {
			return '';
		}

		if (function_exists('pmpro_getLevel')) {
			$level = pmpro_getLevel($membership_id);
			if (is_object($level) && !empty($level->name)) {
				return sanitize_text_field((string) $level->name);
			}
		}

		return '';
	}

	private function render_pmpro_order_breakdown_markup($order_breakdown) {
		$items = isset($order_breakdown['items']) && is_array($order_breakdown['items']) ? $order_breakdown['items'] : [];
		if (empty($items)) {
			return '';
		}

		ob_start();
		?>
		<section class="aac-order-summary" aria-label="<?php esc_attr_e('Transaction summary', 'aac-member-portal'); ?>">
			<div class="aac-order-summary__header">
				<h2><?php esc_html_e('Transaction Summary', 'aac-member-portal'); ?></h2>
				<p><?php esc_html_e('This order includes the following line items.', 'aac-member-portal'); ?></p>
			</div>
			<div class="aac-order-summary__rows">
				<?php foreach ($items as $item) : ?>
					<div class="aac-order-summary__row">
						<span><?php echo esc_html((string) ($item['label'] ?? 'Item')); ?></span>
						<strong><?php echo esc_html($this->format_line_item_price((float) ($item['amount'] ?? 0))); ?></strong>
					</div>
				<?php endforeach; ?>
				<div class="aac-order-summary__row aac-order-summary__row--total">
					<span><?php esc_html_e('Total charged', 'aac-member-portal'); ?></span>
					<strong><?php echo esc_html($this->format_price((float) ($order_breakdown['total'] ?? 0))); ?></strong>
				</div>
			</div>
			<?php if (!empty($order_breakdown['order_code'])) : ?>
				<p class="aac-order-summary__meta">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s order code */
							__('Order reference: %s', 'aac-member-portal'),
							(string) $order_breakdown['order_code']
						)
					);
					?>
				</p>
			<?php endif; ?>
		</section>
		<?php

		return (string) ob_get_clean();
	}

	private function format_price($amount) {
		if (function_exists('pmpro_formatPrice')) {
			return pmpro_formatPrice((float) $amount);
		}

		return '$' . number_format((float) $amount, 2);
	}

	private function format_line_item_price($amount) {
		$amount = (float) $amount;
		if ($amount < 0) {
			return '-' . $this->format_price(abs($amount));
		}

		return $this->format_price($amount);
	}

	private function format_membership_line_item_label($level_name) {
		$level_name = trim((string) $level_name);
		if ($level_name === '') {
			return 'Membership';
		}

		$normalized = preg_replace('/\s+membership(?:\s+membership)+$/i', ' Membership', $level_name);
		$normalized = is_string($normalized) ? trim($normalized) : $level_name;

		if (preg_match('/^membership$/i', $normalized)) {
			return 'Membership';
		}

		if (preg_match('/membership$/i', $normalized)) {
			return $normalized;
		}

		return sprintf('%s Membership', $normalized);
	}

	private function get_autorenew_reactivation_checkout_context($level = null) {
		if (!$this->is_pmpro_checkout_request()) {
			return null;
		}

		$flag = isset($_REQUEST['aac_reactivate_autorenew']) ? sanitize_text_field(wp_unslash($_REQUEST['aac_reactivate_autorenew'])) : '';
		if ($flag !== '1') {
			return null;
		}

		$user_id = get_current_user_id();
		if ($user_id <= 0 || !AAC_Member_Portal_PMPro::is_available()) {
			return null;
		}

		$level = is_object($level) ? $level : $this->get_level_at_checkout();
		$level_id = isset($level->id) ? (int) $level->id : 0;
		if ($level_id <= 0) {
			return null;
		}

		$current_membership = AAC_Member_Portal_PMPro::get_primary_membership($user_id);
		if (
			!is_array($current_membership)
			|| (int) ($current_membership['level_id'] ?? 0) !== $level_id
			|| empty($current_membership['expiration_date'])
		) {
			return null;
		}

		if (AAC_Member_Portal_PMPro::has_active_auto_renewal($user_id, $level_id)) {
			return null;
		}

		$start_date = $this->normalize_deferred_checkout_date($current_membership['expiration_date']);
		if ($start_date === '') {
			return null;
		}

		return [
			'level_id' => $level_id,
			'start_date' => $start_date,
			'renewal_amount' => max(0, $this->get_level_recurring_total($level)),
		];
	}

	private function normalize_deferred_checkout_date($date_string) {
		$date_string = sanitize_text_field((string) $date_string);
		$date_string = trim($date_string);
		if ($date_string === '') {
			return '';
		}

		$unix = strtotime($date_string);
		if ($unix === false) {
			return '';
		}

		$today = strtotime(current_time('Y-m-d'));
		if ($today === false || $unix < $today) {
			return '';
		}

		return gmdate('Y-m-d', $unix);
	}

	private function get_level_at_checkout() {
		if (function_exists('pmpro_getLevelAtCheckout')) {
			$level = pmpro_getLevelAtCheckout();
			if (is_object($level)) {
				return $level;
			}
		}

		global $pmpro_level;
		return is_object($pmpro_level) ? $pmpro_level : null;
	}

	private function get_level_recurring_total($level) {
		if (!is_object($level)) {
			return 0.0;
		}

		$aac_membership_total = $this->get_aac_membership_level_base_total($level);
		if ($aac_membership_total !== null) {
			return $aac_membership_total;
		}

		return $this->get_raw_level_recurring_total($level);
	}

	private function get_level_checkout_initial_total($level) {
		if (!is_object($level)) {
			return 0.0;
		}

		if (isset($level->initial_payment) && $level->initial_payment !== '') {
			return max(0, (float) $level->initial_payment);
		}

		return $this->get_raw_level_recurring_total($level);
	}

	private function get_raw_level_recurring_total($level) {
		if (!is_object($level)) {
			return 0.0;
		}

		$billing_amount = isset($level->billing_amount) ? (float) $level->billing_amount : 0.0;
		if ($billing_amount > 0) {
			return $billing_amount;
		}

		return isset($level->initial_payment) ? (float) $level->initial_payment : 0.0;
	}

	private function get_aac_membership_level_base_total($level) {
		if (!is_object($level)) {
			return null;
		}

		$level_name = trim((string) ($level->name ?? ''));
		if ($level_name === '') {
			return null;
		}

		$mapped_totals = [
			'Free' => 0.0,
			'Supporter' => 45.0,
			'Partner' => 100.0,
			'Partner Family' => 100.0,
			'Leader' => 250.0,
			'Advocate' => 500.0,
		];

		return array_key_exists($level_name, $mapped_totals) ? (float) $mapped_totals[$level_name] : null;
	}

	public function capture_relevant_fatal() {
		$error = error_get_last();
		if (!$this->is_fatal_error($error)) {
			return;
		}

		$request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
		if (!$this->should_capture_fatal_for_request($request_uri)) {
			return;
		}

		$post_keys = [];
		if (!empty($_POST) && is_array($_POST)) {
			$post_keys = array_values(array_filter(array_keys($_POST), static function ($key) {
				return !in_array($key, ['password', 'password2', 'CVV', 'AccountNumber'], true);
			}));
		}

		update_option('aac_member_portal_last_fatal', [
			'time' => current_time('mysql'),
			'request_uri' => $request_uri,
			'message' => (string) ($error['message'] ?? ''),
			'file' => (string) ($error['file'] ?? ''),
			'line' => (int) ($error['line'] ?? 0),
			'user_id' => get_current_user_id(),
			'post_keys' => $post_keys,
		], false);
	}

	public function maybe_disable_broken_wp_fusion_pmpro_hooks() {
		if (!$this->is_wp_fusion_pmpro_request_context()) {
			return;
		}

		if (!$this->should_disable_wp_fusion_pmpro_hooks()) {
			return;
		}

		$hooks = [
			'profile_update',
			'pmpro_after_change_membership_level',
		];

		foreach ($hooks as $hook_name) {
			$this->remove_class_callbacks($hook_name, 'WPF_PMPro_Hooks');
		}
	}

	public function maybe_shim_broken_wp_fusion_user_service(...$args) {
		if (!$this->is_wp_fusion_shim_context()) {
			return;
		}

		if (!function_exists('wp_fusion')) {
			return;
		}

		try {
			$fusion = wp_fusion();
		} catch (Throwable $throwable) {
			return;
		}

		if (!is_object($fusion)) {
			return;
		}

		$user = isset($fusion->user) ? $fusion->user : null;
		if (is_object($user) && method_exists($user, 'push_user_meta')) {
			return;
		}

		$fusion->user = new AAC_Member_Portal_Null_WP_Fusion_User();
	}

	public function get_portal_page_url() {
		static $portal_url = null;
		if ($portal_url !== null) {
			return $portal_url;
		}

		$portal_url = home_url('/');

		foreach (['membership', 'member-portal'] as $preferred_slug) {
			$preferred_page = get_page_by_path($preferred_slug, OBJECT, 'page');
			if (!$preferred_page instanceof WP_Post) {
				$preferred_page_id = url_to_postid(home_url('/' . trim($preferred_slug, '/') . '/'));
				$preferred_page = $preferred_page_id ? get_post($preferred_page_id) : null;
			}

			if ($preferred_page instanceof WP_Post && has_shortcode($preferred_page->post_content, self::SHORTCODE)) {
				$portal_url = get_permalink($preferred_page);
				return $portal_url;
			}
		}

		$query = new WP_Query([
			'post_type' => ['page'],
			'post_status' => 'publish',
			'posts_per_page' => -1,
			's' => '[' . self::SHORTCODE,
			'no_found_rows' => true,
		]);

		if (!empty($query->posts)) {
			foreach ($query->posts as $post) {
				if ($post instanceof WP_Post && has_shortcode($post->post_content, self::SHORTCODE)) {
					$portal_url = get_permalink($post);
					break;
				}
			}
		}

		wp_reset_postdata();

		return $portal_url;
	}

	public function render_shortcode() {
		$asset_files = $this->locate_asset_files();
		if (!$asset_files['script']) {
			return '<div class="aac-member-portal-error">AAC Member Portal assets have not been packaged yet.</div>';
		}

		$this->enqueue_portal_assets_and_config();
		$config = $this->get_runtime_config();

		return sprintf(
			'<script>window.AAC_MEMBER_PORTAL_CONFIG = %s;</script><div id="%s" class="aac-member-portal-shell"></div>',
			wp_json_encode($config),
			esc_attr(self::MOUNT_ID)
		);
	}

	/**
	 * @return bool True if portal config was attached (once per request).
	 */
	private function enqueue_portal_assets_and_config() {
		$asset_files = $this->locate_asset_files();
		if (!$asset_files['script']) {
			return false;
		}

		wp_enqueue_script(self::SCRIPT_HANDLE);
		if ($asset_files['style']) {
			wp_enqueue_style(self::STYLE_HANDLE);
		}

		static $config_injected = false;
		if ($config_injected) {
			return true;
		}

		$config_injected = true;

		$config = $this->get_runtime_config();

		wp_add_inline_script(
			self::SCRIPT_HANDLE,
			'window.AAC_MEMBER_PORTAL_CONFIG = ' . wp_json_encode($config) . ';',
			'before'
		);

		return true;
	}

	public function maybe_render_missing_build_notice() {
		if (!current_user_can('activate_plugins')) {
			return;
		}

		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if (!$screen || $screen->base !== 'plugins') {
			return;
		}

		$asset_files = $this->locate_asset_files();
		if ($asset_files['script']) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo esc_html('AAC Member Portal is installed, but the frontend build assets are missing. Run `npm run package:wordpress` in the app project before zipping or deploying the plugin.');
		echo '</p></div>';
	}

	public function maybe_restore_pmpro_admin_capabilities() {
		if (!current_user_can('manage_options') || !AAC_Member_Portal_PMPro::is_available()) {
			return;
		}

		$administrator = get_role('administrator');
		if (!$administrator) {
			return;
		}

		foreach ($this->pmpro_admin_capabilities() as $capability) {
			if (!$administrator->has_cap($capability)) {
				$administrator->add_cap($capability);
			}
		}
	}

	public function maybe_grant_pmpro_admin_capabilities($allcaps, $caps, $args, $user) {
		if (!AAC_Member_Portal_PMPro::is_available() || !($user instanceof WP_User)) {
			return $allcaps;
		}

		if (empty($allcaps['manage_options']) && empty($allcaps['activate_plugins'])) {
			return $allcaps;
		}

		foreach ($this->pmpro_admin_capabilities() as $capability) {
			$allcaps[$capability] = true;
		}

		return $allcaps;
	}

	private function pmpro_admin_capabilities() {
		return [
			'pmpro_addons',
			'pmpro_advancedsettings',
			'pmpro_dashboard',
			'pmpro_discountcodes',
			'pmpro_edit_members',
			'pmpro_emailsettings',
			'pmpro_emailtemplates',
			'pmpro_logincsv',
			'pmpro_manage_pause_mode',
			'pmpro_membershiplevels',
			'pmpro_memberships_menu',
			'pmpro_memberslist',
			'pmpro_memberslistcsv',
			'pmpro_orders',
			'pmpro_orderscsv',
			'pmpro_pagesettings',
			'pmpro_paymentsettings',
			'pmpro_reportcsv',
			'pmpro_reports',
			'pmpro_sales_report_csv',
			'pmpro_updates',
			'pmpro_userfields',
			'pmpro_wizard',
		];
	}

	private function get_account_info_defaults_for_user($user = null) {
		$user_id = $user instanceof WP_User && $user->exists() ? $user->ID : 0;
		$stored = $user_id ? get_user_meta($user_id, 'aac_account_info', true) : [];
		$stored = is_array($stored) ? $stored : [];
		$stored = $this->strip_pmpro_managed_account_fields_for_storage($stored);
		$member_database_fallback = $user_id > 0 ? $this->get_member_database_account_info_fallback($user_id) : [];

		$defaults = [
			'first_name' => $user instanceof WP_User ? $user->first_name : '',
			'last_name' => $user instanceof WP_User ? $user->last_name : '',
			'name' => $user instanceof WP_User ? $user->display_name : '',
			'email' => $user instanceof WP_User ? $user->user_email : '',
			'photo_url' => $user_id ? get_avatar_url($user_id) : '',
			'phone' => '',
			'birthdate' => '',
			'street' => '',
			'address2' => '',
			'city' => '',
			'state' => '',
			'zip' => '',
			'country' => 'US',
			'size' => 'No T-shirt',
			'email_opt_out' => false,
			'do_not_call' => false,
			'do_not_contact' => false,
			'publication_pref' => 'Print',
			'aaj_pref' => 'Print',
			'anac_pref' => 'Print',
			'acj_pref' => 'Print',
			'guidebook_pref' => 'Print',
			'magazine_subscriptions' => [],
			'membership_discount_type' => '',
			'partner_family_mode' => '',
			'partner_family_additional_adult' => false,
			'partner_family_dependents' => 0,
			'auto_renew' => true,
		];

		$merged = array_merge($defaults, $stored);
		if (!empty($member_database_fallback)) {
			$merged = array_merge($merged, array_filter(
				$member_database_fallback,
				static function ($value) {
					if (is_bool($value)) {
						return true;
					}

					if (is_array($value)) {
						return !empty($value);
					}

					return trim((string) $value) !== '';
				}
			));
		}
		unset($merged['phone_type'], $merged['payment_method']);
		if ($user_id > 0) {
			$merged['phone'] = $this->get_preferred_user_meta_value($user_id, ['bphone'], $merged['phone']);
			$merged['birthdate'] = $this->sanitize_birthdate_value(
				$this->get_preferred_user_meta_value($user_id, ['birthdate'], $merged['birthdate'])
			);
			$merged['street'] = $this->get_preferred_user_meta_value($user_id, ['baddress1'], $merged['street']);
			$merged['address2'] = $this->get_preferred_user_meta_value($user_id, ['baddress2'], $merged['address2']);
			$merged['city'] = $this->get_preferred_user_meta_value($user_id, ['bcity'], $merged['city']);
			$merged['state'] = $this->get_preferred_user_meta_value($user_id, ['bstate'], $merged['state']);
			$merged['zip'] = $this->get_preferred_user_meta_value($user_id, ['bzipcode'], $merged['zip']);
			$merged['country'] = $this->get_preferred_user_meta_value($user_id, ['bcountry'], $merged['country']);
			$merged['size'] = $this->get_preferred_user_meta_value($user_id, ['t_shirt'], $merged['size']);
			$merged['email_opt_out'] = $this->get_preferred_user_meta_flag($user_id, ['email_opt_out'], $merged['email_opt_out']);
			$merged['do_not_call'] = $this->get_preferred_user_meta_flag($user_id, ['do_not_call'], $merged['do_not_call']);
			$merged['do_not_contact'] = $this->get_preferred_user_meta_flag($user_id, ['do_not_contact'], $merged['do_not_contact']);
			$merged['aaj_pref'] = $this->get_preferred_user_meta_value($user_id, ['aaj_preference'], $merged['aaj_pref']);
			$merged['anac_pref'] = $this->get_preferred_user_meta_value($user_id, ['anac_preference'], $merged['anac_pref']);
			$merged['acj_pref'] = $this->get_preferred_user_meta_value($user_id, ['american_climbing_journal_preference'], $merged['acj_pref']);
			$merged['guidebook_pref'] = $this->get_preferred_user_meta_value($user_id, ['guidebook_preferences'], $merged['guidebook_pref']);
		}
		$merged['size'] = $this->normalize_tshirt_size_value($merged['size'] ?? 'No T-shirt');

		return array_merge($merged, $this->get_normalized_publication_preferences($merged));
	}

	private function get_member_database_account_info_fallback($user_id) {
		global $wpdb;

		$user_id = (int) $user_id;
		if ($user_id <= 0 || !$wpdb) {
			return [];
		}

		$table = $wpdb->prefix . 'aac_member_db_profiles';
		$table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ($table_exists !== $table) {
			return [];
		}

		$raw_profile = $wpdb->get_var(
			$wpdb->prepare("SELECT raw_profile FROM {$table} WHERE user_id = %d LIMIT 1", $user_id)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$decoded = json_decode((string) $raw_profile, true);
		if (!is_array($decoded)) {
			return [];
		}

		$account_info = is_array($decoded['account_info'] ?? null) ? $decoded['account_info'] : [];
		if (!$account_info) {
			return [];
		}

		return [
			'phone' => sanitize_text_field((string) ($account_info['phone'] ?? '')),
			'birthdate' => $this->sanitize_birthdate_value($account_info['birthdate'] ?? ''),
			'street' => sanitize_text_field((string) ($account_info['street'] ?? '')),
			'address2' => sanitize_text_field((string) ($account_info['address2'] ?? '')),
			'city' => sanitize_text_field((string) ($account_info['city'] ?? '')),
			'state' => sanitize_text_field((string) ($account_info['state'] ?? '')),
			'zip' => sanitize_text_field((string) ($account_info['zip'] ?? '')),
			'country' => sanitize_text_field((string) ($account_info['country'] ?? '')),
			'size' => $this->normalize_tshirt_size_value($account_info['size'] ?? 'No T-shirt'),
			'email_opt_out' => !empty($account_info['email_opt_out']),
			'do_not_call' => !empty($account_info['do_not_call']),
			'do_not_contact' => !empty($account_info['do_not_contact']),
			'aaj_pref' => sanitize_text_field((string) ($account_info['aaj_pref'] ?? '')),
			'anac_pref' => sanitize_text_field((string) ($account_info['anac_pref'] ?? '')),
			'acj_pref' => sanitize_text_field((string) ($account_info['acj_pref'] ?? '')),
			'guidebook_pref' => sanitize_text_field((string) ($account_info['guidebook_pref'] ?? '')),
		];
	}

	private function get_member_ids_for_pmpro_field_backfill() {
		$user_ids = get_users([
			'fields' => 'ids',
			'number' => -1,
			'orderby' => 'ID',
			'order' => 'ASC',
			'count_total' => false,
		]);

		global $wpdb;
		if ($wpdb) {
			$table = $wpdb->prefix . 'aac_member_db_profiles';
			$table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ($table_exists === $table) {
				$mirror_user_ids = $wpdb->get_col("SELECT user_id FROM {$table} WHERE user_id > 0"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				if (is_array($mirror_user_ids) && !empty($mirror_user_ids)) {
					$user_ids = array_merge($user_ids, $mirror_user_ids);
				}
			}
		}

		return array_values(array_unique(array_map('intval', is_array($user_ids) ? $user_ids : [])));
	}

	private function get_pmpro_managed_account_info_keys() {
		return [
			'phone',
			'birthdate',
			'street',
			'address2',
			'city',
			'state',
			'zip',
			'country',
			'size',
			'email_opt_out',
			'do_not_call',
			'do_not_contact',
			'publication_pref',
			'aaj_pref',
			'anac_pref',
			'acj_pref',
			'guidebook_pref',
			'phone_type',
			'payment_method',
		];
	}

	private function strip_pmpro_managed_account_fields_for_storage($account_info) {
		if (!is_array($account_info)) {
			return [];
		}

		foreach ($this->get_pmpro_managed_account_info_keys() as $key) {
			unset($account_info[$key]);
		}

		return $account_info;
	}

	private function get_preferred_user_meta_value($user_id, $keys, $fallback = '') {
		$user_id = (int) $user_id;
		if ($user_id <= 0 || !is_array($keys)) {
			return $fallback;
		}

		foreach ($keys as $key) {
			$key = sanitize_key((string) $key);
			if ($key === '') {
				continue;
			}

			$value = get_user_meta($user_id, $key, true);
			if (is_array($value)) {
				if (!empty($value)) {
					return $value;
				}
				continue;
			}

			if ($value === null) {
				continue;
			}

			if (is_string($value)) {
				if (trim($value) === '') {
					continue;
				}
				return $value;
			}

			if ($value !== '') {
				return $value;
			}
		}

		return $fallback;
	}

	private function get_preferred_user_meta_flag($user_id, $keys, $fallback = false) {
		$value = $this->get_preferred_user_meta_value($user_id, $keys, null);
		if ($value === null) {
			return (bool) $fallback;
		}

		if (is_bool($value)) {
			return $value;
		}

		if (is_numeric($value)) {
			return (int) $value === 1;
		}

		$normalized = strtolower(trim((string) $value));
		return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
	}

	private function normalize_tshirt_size_value($value, $fallback = 'No T-shirt') {
		$normalized = sanitize_text_field((string) $value);
		$normalized = trim($normalized);
		if ($normalized === '') {
			return $fallback;
		}

		$lowered = strtolower($normalized);
		if (in_array($lowered, ['none', 'no t-shirt', 'no t shirt', 'n/a', 'na'], true)) {
			return 'No T-shirt';
		}

		$direct_label_map = [
			'unisex x-small' => 'Unisex X-Small',
			'unisex small' => 'Unisex Small',
			'unisex medium' => 'Unisex Medium',
			'unisex large' => 'Unisex Large',
			'unisex x-large' => 'Unisex X-Large',
			'unisex xx-large' => 'Unisex XX-Large',
		];
		if (isset($direct_label_map[$lowered])) {
			return $direct_label_map[$lowered];
		}

		$compact_size_map = [
			'xs' => 'Unisex X-Small',
			'xsmall' => 'Unisex X-Small',
			's' => 'Unisex Small',
			'm' => 'Unisex Medium',
			'l' => 'Unisex Large',
			'xl' => 'Unisex X-Large',
			'xlarge' => 'Unisex X-Large',
			'xxl' => 'Unisex XX-Large',
			'xxlarge' => 'Unisex XX-Large',
			'2xl' => 'Unisex XX-Large',
		];
		if (isset($compact_size_map[$lowered])) {
			return $compact_size_map[$lowered];
		}

		if (strpos($lowered, 'unisex ') === 0) {
			$compact = str_replace([' ', '-'], '', substr($lowered, 8));
			return $compact_size_map[$compact] ?? $fallback;
		}

		return $fallback;
	}

	private function normalize_print_digital_value($value, $fallback = 'Digital') {
		return $value === 'Print' ? 'Print' : ($value === 'Digital' ? 'Digital' : $fallback);
	}

	private function get_normalized_publication_preferences($values) {
		$values = is_array($values) ? $values : [];
		$legacy_publication_pref = $this->normalize_print_digital_value(
			$values['publication_pref'] ?? '',
			$this->normalize_print_digital_value(
				$values['aaj_pref'] ?? '',
				$this->normalize_print_digital_value(
					$values['anac_pref'] ?? '',
					$this->normalize_print_digital_value(
						$values['acj_pref'] ?? '',
						$this->normalize_print_digital_value($values['guidebook_pref'] ?? 'Digital')
					)
				)
			)
		);
		$guidebook_pref = $this->normalize_print_digital_value($values['guidebook_pref'] ?? 'Digital');

		return [
			'publication_pref' => $legacy_publication_pref,
			'aaj_pref' => $this->normalize_print_digital_value($values['aaj_pref'] ?? $legacy_publication_pref),
			'anac_pref' => $this->normalize_print_digital_value($values['anac_pref'] ?? $legacy_publication_pref),
			'acj_pref' => $this->normalize_print_digital_value($values['acj_pref'] ?? $legacy_publication_pref),
			'guidebook_pref' => $guidebook_pref,
		];
	}

	private function sync_reportable_member_fields($user_id, $account_info, $magazine_addons = null, $membership_discount_type = null) {
		$user_id = (int) $user_id;
		if ($user_id <= 0 || !is_array($account_info)) {
			return;
		}

		update_user_meta($user_id, 't_shirt', sanitize_text_field($account_info['size'] ?? ''));
		update_user_meta($user_id, 'birthdate', $this->sanitize_birthdate_value($account_info['birthdate'] ?? ''));
		update_user_meta($user_id, 'email_opt_out', !empty($account_info['email_opt_out']) ? '1' : '0');
		update_user_meta($user_id, 'do_not_call', !empty($account_info['do_not_call']) ? '1' : '0');
		update_user_meta($user_id, 'do_not_contact', !empty($account_info['do_not_contact']) ? '1' : '0');
		update_user_meta($user_id, 'bphone', sanitize_text_field($account_info['phone'] ?? ''));
		update_user_meta($user_id, 'baddress1', sanitize_text_field($account_info['street'] ?? ''));
		update_user_meta($user_id, 'baddress2', sanitize_text_field($account_info['address2'] ?? ''));
		update_user_meta($user_id, 'bcity', sanitize_text_field($account_info['city'] ?? ''));
		update_user_meta($user_id, 'bstate', sanitize_text_field($account_info['state'] ?? ''));
		update_user_meta($user_id, 'bzipcode', sanitize_text_field($account_info['zip'] ?? ''));
		update_user_meta($user_id, 'bcountry', sanitize_text_field($account_info['country'] ?? ''));
		$this->update_emergency_contact_user_meta($user_id, [
			'emergency_contact_first_name' => sanitize_text_field($account_info['emergency_contact_first_name'] ?? ''),
			'emergency_contact_last_name' => sanitize_text_field($account_info['emergency_contact_last_name'] ?? ''),
			'emergency_contact_phone' => sanitize_text_field($account_info['emergency_contact_phone'] ?? ''),
			'emergency_contact_email' => sanitize_email($account_info['emergency_contact_email'] ?? ''),
			'emergency_contact_relationship' => sanitize_text_field($account_info['emergency_contact_relationship'] ?? ''),
		]);
		update_user_meta($user_id, 'aaj_preference', $this->normalize_print_digital_value($account_info['aaj_pref'] ?? 'Digital'));
		update_user_meta($user_id, 'anac_preference', $this->normalize_print_digital_value($account_info['anac_pref'] ?? 'Digital'));
		update_user_meta($user_id, 'american_climbing_journal_preference', $this->normalize_print_digital_value($account_info['acj_pref'] ?? 'Digital'));
		update_user_meta($user_id, 'guidebook_preferences', $this->normalize_print_digital_value($account_info['guidebook_pref'] ?? 'Digital'));
		delete_user_meta($user_id, 'aac_tshirt_size');
		delete_user_meta($user_id, 'aac_birthdate');
		delete_user_meta($user_id, 'aac_email_opt_out');
		delete_user_meta($user_id, 'aac_do_not_call');
		delete_user_meta($user_id, 'aac_do_not_contact');
		delete_user_meta($user_id, 'aac_publication_pref');
		delete_user_meta($user_id, 'aac_aaj_pref');
		delete_user_meta($user_id, 'aac_anac_pref');
		delete_user_meta($user_id, 'aac_acj_pref');
		delete_user_meta($user_id, 'aac_guidebook_pref');

		$selected_addons = $magazine_addons === null
			? $this->get_effective_magazine_addon_selection($user_id)
			: $this->normalize_magazine_addon_selection($magazine_addons);

		update_user_meta($user_id, 'aac_magazine_addons', $selected_addons);

		$catalog = $this->get_magazine_addon_catalog();
		$labels = [];
		foreach ($selected_addons as $slug) {
			if (!empty($catalog[$slug]['label'])) {
				$labels[] = (string) $catalog[$slug]['label'];
			}
		}

		update_user_meta($user_id, 'aac_magazine_subscription_labels', implode(', ', $labels));
		update_user_meta($user_id, 'aac_has_alpinist_subscription', in_array('alpinist', $selected_addons, true) ? '1' : '0');
		update_user_meta($user_id, 'aac_has_backcountry_subscription', in_array('backcountry', $selected_addons, true) ? '1' : '0');

		$normalized_discount_type = $membership_discount_type === null
			? $this->get_effective_membership_discount_type($user_id)
			: $this->normalize_membership_discount_type($membership_discount_type);
		update_user_meta($user_id, 'aac_membership_discount_type', $normalized_discount_type);

		$family_config = $this->get_effective_partner_family_config($user_id);
		update_user_meta($user_id, 'aac_partner_family_mode', $family_config['mode']);
		update_user_meta($user_id, 'aac_partner_family_additional_adult', !empty($family_config['additional_adult']) ? '1' : '0');
		update_user_meta($user_id, 'aac_partner_family_dependents', max(0, (int) ($family_config['dependent_count'] ?? 0)));
		update_user_meta($user_id, 'aac_family_account_role', $this->get_family_account_role($user_id, $family_config));
	}

	public function get_emergency_contact_meta_key_candidates($logical_key) {
		$fallback_map = [
			'emergency_contact_first_name' => ['emergency_contact_first_name', 'emergency_first_name', 'emergency_first'],
			'emergency_contact_last_name' => ['emergency_contact_last_name', 'emergency_last_name', 'emergency_last'],
			'emergency_contact_phone' => ['emergency_contact_phone', 'emergency_phone', 'emergency_contact_phone_number'],
			'emergency_contact_email' => ['emergency_contact_email', 'emergency_email'],
			'emergency_contact_relationship' => ['emergency_contact_relationship', 'emergency_relationship'],
		];

		$candidates = $fallback_map[$logical_key] ?? [$logical_key];
		$config = $this->get_pmpro_emergency_contact_field_config();
		if (!empty($config[$logical_key]['meta_key'])) {
			array_unshift($candidates, $config[$logical_key]['meta_key']);
		}

		$normalized = [];
		foreach ($candidates as $candidate) {
			$normalized_candidate = sanitize_key((string) $candidate);
			if ($normalized_candidate !== '' && !in_array($normalized_candidate, $normalized, true)) {
				$normalized[] = $normalized_candidate;
			}
		}

		return $normalized;
	}

	public function get_emergency_contact_relationship_options() {
		$config = $this->get_pmpro_emergency_contact_field_config();
		$options = $config['emergency_contact_relationship']['options'] ?? [];

		return array_values(array_filter(array_map(static function ($option) {
			if (is_string($option)) {
				$value = trim($option);
				return $value === '' ? null : ['value' => $value, 'label' => $value];
			}

			if (!is_array($option)) {
				return null;
			}

			$value = sanitize_text_field($option['value'] ?? $option['label'] ?? '');
			$label = sanitize_text_field($option['label'] ?? $option['value'] ?? '');
			if ($value === '' || $label === '') {
				return null;
			}

			return ['value' => $value, 'label' => $label];
		}, $options)));
	}

	private function update_emergency_contact_user_meta($user_id, $account_info) {
		$user_id = (int) $user_id;
		if ($user_id <= 0 || !is_array($account_info)) {
			return;
		}

		foreach ($this->get_pmpro_emergency_contact_field_config() as $logical_key => $field) {
			$meta_key = sanitize_key((string) ($field['meta_key'] ?? ''));
			if ($meta_key === '') {
				continue;
			}

			$value = $account_info[$logical_key] ?? '';
			update_user_meta($user_id, $meta_key, $value);
		}
	}

	private function get_pmpro_emergency_contact_field_config() {
		$config = [
			'emergency_contact_first_name' => ['meta_key' => 'emergency_contact_first_name', 'label' => 'First Name', 'options' => []],
			'emergency_contact_last_name' => ['meta_key' => 'emergency_contact_last_name', 'label' => 'Last Name', 'options' => []],
			'emergency_contact_phone' => ['meta_key' => 'emergency_contact_phone', 'label' => 'Phone Number', 'options' => []],
			'emergency_contact_email' => ['meta_key' => 'emergency_contact_email', 'label' => 'Email', 'options' => []],
			'emergency_contact_relationship' => ['meta_key' => 'emergency_contact_relationship', 'label' => 'Relationship', 'options' => []],
		];

		$groups = get_option('pmpro_user_fields_settings', []);
		if (!is_array($groups)) {
			return $config;
		}

		foreach ($groups as $group) {
			$group_data = is_object($group) ? get_object_vars($group) : (is_array($group) ? $group : []);
			$group_name = sanitize_text_field($group_data['name'] ?? '');
			if (strcasecmp($group_name, 'Emergency Contact') !== 0) {
				continue;
			}

			$fields = $group_data['fields'] ?? [];
			if (is_object($fields)) {
				$fields = get_object_vars($fields);
			}

			if (!is_array($fields)) {
				break;
			}

			foreach ($fields as $field) {
				$field_data = is_object($field) ? get_object_vars($field) : (is_array($field) ? $field : []);
				$meta_key = sanitize_key((string) ($field_data['name'] ?? ''));
				$label = sanitize_text_field($field_data['label'] ?? '');
				if ($meta_key === '' && $label === '') {
					continue;
				}

				$slot = $this->match_emergency_contact_field_slot($meta_key, $label);
				if (!$slot || !isset($config[$slot])) {
					continue;
				}

				if ($meta_key !== '') {
					$config[$slot]['meta_key'] = $meta_key;
				}
				if ($label !== '') {
					$config[$slot]['label'] = $label;
				}
				if ($slot === 'emergency_contact_relationship') {
					$config[$slot]['options'] = $this->normalize_pmpro_field_options($field_data['options'] ?? []);
				}
			}

			break;
		}

		return $config;
	}

	private function match_emergency_contact_field_slot($meta_key, $label) {
		$haystack = strtolower(trim($meta_key . ' ' . $label));
		$haystack = str_replace(['-', '_'], ' ', $haystack);

		if (strpos($haystack, 'relationship') !== false) {
			return 'emergency_contact_relationship';
		}
		if (strpos($haystack, 'phone') !== false) {
			return 'emergency_contact_phone';
		}
		if (strpos($haystack, 'email') !== false) {
			return 'emergency_contact_email';
		}
		if (strpos($haystack, 'last') !== false) {
			return 'emergency_contact_last_name';
		}
		if (strpos($haystack, 'first') !== false) {
			return 'emergency_contact_first_name';
		}

		return null;
	}

	private function normalize_pmpro_field_options($options) {
		if (is_object($options)) {
			$options = get_object_vars($options);
		}

		if (!is_array($options)) {
			return [];
		}

		$normalized = [];
		foreach ($options as $key => $option) {
			if (is_object($option)) {
				$option = get_object_vars($option);
			}

			if (is_array($option)) {
				$value = sanitize_text_field($option['value'] ?? $option['label'] ?? $option['text'] ?? $key);
				$label = sanitize_text_field($option['label'] ?? $option['text'] ?? $option['value'] ?? $key);
			} else {
				$value = sanitize_text_field(is_string($key) ? $key : (string) $option);
				$label = sanitize_text_field((string) $option);
			}

			if ($value === '' || $label === '') {
				continue;
			}

			$normalized[] = ['value' => $value, 'label' => $label];
		}

		return $normalized;
	}

	private function sanitize_birthdate_value($value) {
		$normalized = sanitize_text_field((string) $value);
		$normalized = trim($normalized);
		if ($normalized === '') {
			return '';
		}

		return preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized) ? $normalized : '';
	}

	private function get_family_account_role($user_id, $family_config = null) {
		$user_id = (int) $user_id;
		if ($user_id <= 0) {
			return '';
		}

		if ($this->get_linked_parent_user_id($user_id) > 0) {
			return 'Child';
		}

		$family_config = is_array($family_config) ? $family_config : $this->get_effective_partner_family_config($user_id);
		$connected_accounts = get_user_meta($user_id, 'aac_connected_accounts', true);
		if (($family_config['mode'] ?? '') === 'family' || (is_array($connected_accounts) && !empty($connected_accounts))) {
			return 'Parent';
		}

		return '';
	}

	private function get_linked_parent_user_id($user_id) {
		return absint(get_user_meta((int) $user_id, 'aac_linked_parent_user_id', true));
	}

	private function generate_unique_username_from_email($email) {
		$base_username = sanitize_user(str_replace(['@', '.', '+', '-'], '_', strtolower($email)), true);
		if ($base_username === '') {
			$base_username = 'aac_member';
		}

		$username = $base_username;
		$suffix = 1;

		while (username_exists($username)) {
			$username = sprintf('%s%d', $base_username, $suffix);
			$suffix++;
		}

		return $username;
	}

	public function mark_script_as_module($tag, $handle, $src) {
		if ($handle !== self::SCRIPT_HANDLE) {
			return $tag;
		}

		return sprintf(
			'<script type="module" src="%s" id="%s-js"></script>',
			esc_url($src),
			esc_attr($handle)
		);
	}

	private function locate_asset_files() {
		$asset_dir = AAC_MEMBER_PORTAL_DIR . 'app/assets/';
		$asset_url = AAC_MEMBER_PORTAL_URL . 'app/assets/';
		$index_html_path = AAC_MEMBER_PORTAL_DIR . 'app/index.html';
		$script_path = null;
		$style_path = null;

		if (file_exists($index_html_path) && is_readable($index_html_path)) {
			$index_html = (string) file_get_contents($index_html_path);
			if (preg_match('#src="/?assets/(index-[^"]+\.js)"#', $index_html, $script_match)) {
				$candidate = $asset_dir . $script_match[1];
				if (file_exists($candidate)) {
					$script_path = $candidate;
				}
			}

			if (preg_match('#href="/?assets/(index-[^"]+\.css)"#', $index_html, $style_match)) {
				$candidate = $asset_dir . $style_match[1];
				if (file_exists($candidate)) {
					$style_path = $candidate;
				}
			}
		}

		if (!$script_path) {
			$script_path = $this->first_glob_match($asset_dir . 'index-*.js');
		}

		if (!$style_path) {
			$style_path = $this->first_glob_match($asset_dir . 'index-*.css');
		}

		return [
			'script' => $script_path ? $asset_url . basename($script_path) : null,
			'style' => $style_path ? $asset_url . basename($style_path) : null,
		];
	}

	private function get_runtime_config() {
		// This config blob is the frontend's treasure map. Without it, the React app
		// would have no clue where the API lives, which nonce to use, or what staff
		// just changed in WordPress.
		return [
			'mountId' => self::MOUNT_ID,
			'routerMode' => 'hash',
			'apiBase' => untrailingslashit(rest_url('aac/v1')),
			'restNonce' => wp_create_nonce('wp_rest'),
			'isLoggedIn' => is_user_logged_in(),
			'canManageGrantApprovals' => current_user_can('manage_options'),
			'portalPageUrl' => untrailingslashit($this->get_portal_page_url()),
			'rescuePageUrl' => untrailingslashit($this->get_rescue_page_url()),
			'grantReviewPageUrl' => untrailingslashit($this->get_grant_review_page_url()),
			'mainWebsiteBaseUrl' => untrailingslashit(home_url()),
			'assetBaseUrl' => trailingslashit(AAC_MEMBER_PORTAL_URL . 'app/assets'),
			'pmproSocialLoginHtml' => $this->get_pmpro_social_login_markup(),
			'portalSettings' => $this->get_portal_ui_settings(),
		];
	}

	private function get_pmpro_social_login_markup() {
		$candidate_shortcodes = [
			'pmpro_social_login',
			'pmpro_social_logins',
			'pmprosl_login',
			'pmprosl_social_login',
			'nextend_social_login',
		];

		foreach ($candidate_shortcodes as $shortcode_tag) {
			if (!shortcode_exists($shortcode_tag)) {
				continue;
			}

			$markup = trim((string) do_shortcode('[' . $shortcode_tag . ']'));
			if (strpos($markup, '[nextend_social_login]') !== false && shortcode_exists('nextend_social_login')) {
				$markup = trim((string) do_shortcode($markup));
			}
			if ($markup !== '') {
				return $markup;
			}
		}

		if (shortcode_exists('nextend_social_login')) {
			$markup = trim((string) do_shortcode('[nextend_social_login]'));
			if ($markup !== '') {
				return $markup;
			}
		}

		return '';
	}

	public function get_portal_ui_settings() {
		$settings = AAC_Member_Portal_Admin::get_settings();
		$resolved_background_url = $this->get_resolved_sidebar_background_url($settings);
		// The raw admin settings are very WordPress-shaped. We smooth them into a
		// frontend-friendly structure here so each component does not have to become
		// a tiny translation service.
		$content_settings = array_merge(
			$settings['content'],
			[
				'discountCards' => array_values(isset($settings['content']['discount_cards']) && is_array($settings['content']['discount_cards']) ? $settings['content']['discount_cards'] : []),
				'homeInvolvementCards' => array_values(isset($settings['content']['home_involvement_cards']) && is_array($settings['content']['home_involvement_cards']) ? $settings['content']['home_involvement_cards'] : []),
				'homePublicationCards' => array_values(isset($settings['content']['home_publication_cards']) && is_array($settings['content']['home_publication_cards']) ? $settings['content']['home_publication_cards'] : []),
				'homePartnerLogos' => array_values(isset($settings['content']['home_partner_logos']) && is_array($settings['content']['home_partner_logos']) ? $settings['content']['home_partner_logos'] : []),
				'featuredPhotographers' => array_values(isset($settings['content']['featured_photographers']) && is_array($settings['content']['featured_photographers']) ? $settings['content']['featured_photographers'] : []),
				'grantOpportunities' => array_values(isset($settings['content']['grant_opportunities']) && is_array($settings['content']['grant_opportunities']) ? $settings['content']['grant_opportunities'] : []),
				'grantFormFields' => array_values(isset($settings['content']['grant_form_fields']) && is_array($settings['content']['grant_form_fields']) ? $settings['content']['grant_form_fields'] : []),
				'memberProfileBlocks' => array_values(isset($settings['content']['member_profile_blocks']) && is_array($settings['content']['member_profile_blocks']) ? $settings['content']['member_profile_blocks'] : []),
				'memberProfileCardSections' => isset($settings['content']['member_profile_card_sections']) && is_array($settings['content']['member_profile_card_sections']) ? $settings['content']['member_profile_card_sections'] : [],
				'rescueLevels' => array_values(isset($settings['content']['rescue_levels']) && is_array($settings['content']['rescue_levels']) ? $settings['content']['rescue_levels'] : []),
				'publicationViewUrls' => [
					'aaj' => $settings['content']['publication_view_url_aaj'] ?? '',
					'anac' => $settings['content']['publication_view_url_anac'] ?? '',
					'acj' => $settings['content']['publication_view_url_acj'] ?? '',
					'guidebook' => $settings['content']['publication_view_url_guidebook'] ?? '',
				],
			]
		);

		return [
			'content' => $content_settings,
			'design' => [
				'sidebarBackgroundUrl' => $resolved_background_url,
				'sidebarOverlayStart' => $settings['design']['sidebar_overlay_start'],
				'sidebarOverlayEnd' => $settings['design']['sidebar_overlay_end'],
				'sidebarButtonBackground' => $settings['design']['sidebar_button_background'],
				'sidebarButtonHoverBackground' => $settings['design']['sidebar_button_hover_background'],
				'sidebarButtonActiveBackground' => $settings['design']['sidebar_button_active_background'],
				'sidebarAccentColor' => $settings['design']['sidebar_accent_color'],
				'primaryActionBackground' => $settings['design']['primary_action_background'],
				'primaryActionText' => $settings['design']['primary_action_text'],
				'secondaryActionBackground' => $settings['design']['secondary_action_background'],
				'secondaryActionText' => $settings['design']['secondary_action_text'],
				'pageBackground' => $settings['design']['page_background'],
				'panelBackground' => $settings['design']['panel_background'],
				'panelBorderColor' => $settings['design']['panel_border_color'],
				'heroPanelBackground' => $settings['design']['hero_panel_background'],
				'heroPanelBorderColor' => $settings['design']['hero_panel_border_color'],
				'heroChipBackground' => $settings['design']['hero_chip_background'],
				'heroChipBorderColor' => $settings['design']['hero_chip_border_color'],
				'loginFormBackground' => $settings['design']['login_form_background'],
				'loginOverlay' => $settings['design']['login_overlay'],
				'homeHeroOverlay' => $settings['design']['home_hero_overlay'],
				'homeHeroTintOverlay' => $settings['design']['home_hero_tint_overlay'],
				'joinHeroOverlay' => $settings['design']['join_hero_overlay'],
				'joinHeroTintOverlay' => $settings['design']['join_hero_tint_overlay'],
				'navBackground' => $settings['design']['nav_background'],
				'navTextColor' => $settings['design']['nav_text_color'],
				'navHoverTextColor' => $settings['design']['nav_hover_text_color'],
				'navIconColor' => $settings['design']['nav_icon_color'],
				'navDropdownBackground' => $settings['design']['nav_dropdown_background'],
				'navDropdownTextColor' => $settings['design']['nav_dropdown_text_color'],
				'joinHeroImageUrl' => $settings['design']['join_hero_image_url'],
				'homeHeroVideoUrl' => $settings['design']['home_hero_video_url'],
				'joinHeroVideoUrl' => $settings['design']['join_hero_video_url'],
				'loginBackgroundImageUrl' => $settings['design']['login_background_image_url'],
				'homeIntroImageUrl' => $settings['design']['home_intro_image_url'],
				'homeIntroAccentImageUrl' => $settings['design']['home_intro_accent_image_url'],
				'homeStoreImageUrl' => $settings['design']['home_store_image_url'],
				'publicationTileImages' => [
					'aaj' => $settings['design']['publication_tile_image_aaj'],
					'anac' => $settings['design']['publication_tile_image_anac'],
					'acj' => $settings['design']['publication_tile_image_acj'],
					'guidebook' => $settings['design']['publication_tile_image_guidebook'],
				],
			],
			'navigation' => [
				'topNavSections' => $this->build_top_nav_sections_for_runtime($settings),
				'sidebarSections' => $this->build_sidebar_sections_for_runtime($settings),
			],
			'layout' => [
				'homeSections' => $this->build_home_sections_for_runtime($settings),
			],
		];
	}

	private function build_home_sections_for_runtime($settings) {
		$sections = [];
		foreach ($settings['components']['home_sections'] as $section_id => $section_settings) {
			if (empty($section_settings['visible'])) {
				continue;
			}
			$sections[] = [
				'id' => $section_id,
				'label' => $section_settings['label'],
				'order' => (int) ($section_settings['order'] ?? 0),
			];
		}

		usort($sections, static function ($left, $right) {
			return ($left['order'] ?? 0) <=> ($right['order'] ?? 0);
		});

		return $sections;
	}

	public function get_template_top_nav_sections($portal_url) {
		$settings = AAC_Member_Portal_Admin::get_settings();
		$registry = $this->get_top_nav_item_registry($portal_url);
		$sections = [];

		foreach ($settings['components']['top_nav_items'] as $item_id => $item_settings) {
			if ($item_id === 'get_involved') {
				continue;
			}

			if (empty($item_settings['visible']) || empty($registry[$item_id])) {
				continue;
			}

			$section = $registry[$item_id];
			$section['id'] = $item_id;
			$section['label'] = $item_settings['label'];
			$section['children'] = isset($item_settings['children']) && is_array($item_settings['children']) && !empty($item_settings['children'])
				? array_values($item_settings['children'])
				: $section['children'];
			$section['order'] = (int) $item_settings['order'];
			$sections[] = $section;
		}

		usort($sections, static function ($left, $right) {
			return ($left['order'] ?? 0) <=> ($right['order'] ?? 0);
		});

		return $sections;
	}

	public function get_template_sidebar_sections($portal_url) {
		$settings = AAC_Member_Portal_Admin::get_settings();
		$registry = $this->get_sidebar_item_registry();
		$sections = [];
		$billing_url = AAC_Member_Portal_PMPro::is_available() && function_exists('pmpro_url')
			? pmpro_url('billing')
			: home_url('/membership-account/membership-billing/');

		foreach ($settings['components']['section_titles'] as $section_id => $section_title) {
			$sections[$section_id] = [
				'id' => $section_id,
				'title' => $section_title,
				'items' => [],
			];
		}

		foreach ($settings['components']['sidebar_items'] as $item_id => $item_settings) {
			if (empty($item_settings['visible']) || empty($registry[$item_id])) {
				continue;
			}

			$section_id = $item_settings['section'];
			if (!isset($sections[$section_id])) {
				continue;
			}

			$href = !empty($registry[$item_id]['href'])
				? $registry[$item_id]['href']
				: untrailingslashit($portal_url) . '/#' . ltrim($registry[$item_id]['route'], '/');
			if ($item_id === 'manage') {
				$href = $billing_url;
			}
			$sections[$section_id]['items'][] = [
				'id' => $item_id,
				'label' => $item_settings['label'],
				'href' => $href,
				'icon' => $registry[$item_id]['icon'],
				'order' => (int) $item_settings['order'],
				'active' => false,
			];
		}

		foreach ($sections as &$section) {
			usort($section['items'], static function ($left, $right) {
				return ($left['order'] ?? 0) <=> ($right['order'] ?? 0);
			});
		}
		unset($section);

		return array_values(array_filter($sections, static function ($section) {
			return !empty($section['items']);
		}));
	}

	public function get_template_design_settings() {
		$settings = AAC_Member_Portal_Admin::get_settings();

		return [
			'sidebar_background_url' => $this->get_resolved_sidebar_background_url($settings),
			'sidebar_overlay_start' => $settings['design']['sidebar_overlay_start'],
			'sidebar_overlay_end' => $settings['design']['sidebar_overlay_end'],
			'sidebar_button_background' => $settings['design']['sidebar_button_background'],
			'sidebar_button_hover_background' => $settings['design']['sidebar_button_hover_background'],
			'sidebar_button_active_background' => $settings['design']['sidebar_button_active_background'],
			'sidebar_accent_color' => $settings['design']['sidebar_accent_color'],
			'publication_tile_images' => [
				'aaj' => $settings['design']['publication_tile_image_aaj'],
				'anac' => $settings['design']['publication_tile_image_anac'],
				'acj' => $settings['design']['publication_tile_image_acj'],
				'guidebook' => $settings['design']['publication_tile_image_guidebook'],
			],
		];
	}

	private function build_top_nav_sections_for_runtime($settings) {
		$registry = $this->get_top_nav_item_registry($this->get_portal_page_url());
		$sections = [];

		foreach ($settings['components']['top_nav_items'] as $item_id => $item_settings) {
			if ($item_id === 'get_involved') {
				continue;
			}

			if (empty($item_settings['visible']) || empty($registry[$item_id])) {
				continue;
			}

			$section = $registry[$item_id];
			$sections[] = [
				'id' => $item_id,
				'label' => $item_settings['label'],
				'href' => $section['href'],
				'children' => isset($item_settings['children']) && is_array($item_settings['children']) && !empty($item_settings['children'])
					? array_values($item_settings['children'])
					: $section['children'],
				'order' => (int) $item_settings['order'],
			];
		}

		usort($sections, static function ($left, $right) {
			return ($left['order'] ?? 0) <=> ($right['order'] ?? 0);
		});

		return $sections;
	}

	private function build_sidebar_sections_for_runtime($settings) {
		$registry = $this->get_sidebar_item_registry();
		$sections = [];
		$billing_url = AAC_Member_Portal_PMPro::is_available() && function_exists('pmpro_url')
			? pmpro_url('billing')
			: home_url('/membership-account/membership-billing/');

		foreach ($settings['components']['section_titles'] as $section_id => $section_title) {
			$sections[$section_id] = [
				'id' => $section_id,
				'title' => $section_title,
				'items' => [],
			];
		}

		foreach ($settings['components']['sidebar_items'] as $item_id => $item_settings) {
			if (empty($item_settings['visible']) || empty($registry[$item_id])) {
				continue;
			}

			$section_id = $item_settings['section'];
			if (!isset($sections[$section_id])) {
				continue;
			}

			$item = [
				'id' => $item_id,
				'label' => $item_settings['label'],
				'icon' => $registry[$item_id]['icon'],
				'order' => (int) $item_settings['order'],
			];
			if (!empty($registry[$item_id]['href'])) {
				$item['href'] = $registry[$item_id]['href'];
			} else {
				$item['to'] = $registry[$item_id]['route'];
			}
			if ($item_id === 'manage') {
				$item['href'] = $billing_url;
				unset($item['to']);
			}
			$sections[$section_id]['items'][] = $item;
		}

		foreach ($sections as &$section) {
			usort($section['items'], static function ($left, $right) {
				return ($left['order'] ?? 0) <=> ($right['order'] ?? 0);
			});
		}
		unset($section);

		return array_values(array_filter($sections, static function ($section) {
			return !empty($section['items']);
		}));
	}

	public function get_top_nav_item_registry($portal_url) {
		$portal_url = untrailingslashit((string) $portal_url);

		return [
			'get_involved' => [
				'label' => 'Get Involved',
				'href' => home_url('/get-involved/'),
				'children' => [
					['label' => 'Volunteer', 'href' => home_url('/volunteer/')],
					['label' => 'Donate', 'href' => 'https://membership.americanalpineclub.org/donate', 'external' => true],
					['label' => 'Sign Up', 'href' => 'https://membership.americanalpineclub.org/join', 'external' => true],
				],
			],
			'membership' => [
				'label' => 'Membership',
				'href' => home_url('/membership/'),
				'children' => [
					['label' => 'Benefits', 'href' => home_url('/benefits/')],
					['label' => 'Join', 'href' => $portal_url . '#/join'],
					['label' => 'Renew', 'href' => 'https://membership.americanalpineclub.org/renew', 'external' => true],
				],
			],
			'stories_news' => [
				'label' => 'Stories & News',
				'href' => home_url('/stories/'),
				'children' => [
					['label' => 'Articles & News', 'href' => home_url('/stories/')],
					['label' => 'Featured Photographers', 'href' => $portal_url . '#/photographers'],
					['label' => 'The Prescription', 'href' => home_url('/prescription/')],
					['label' => 'The Line', 'href' => home_url('/line-archive/')],
				],
			],
			'lodging' => [
				'label' => 'Lodging',
				'href' => home_url('/lodging/'),
				'children' => [
					['label' => 'Grand Teton', 'href' => home_url('/grand-teton-climbers-ranch/')],
					['label' => 'The Gunks', 'href' => home_url('/gunks-campground/')],
					['label' => 'Hueco Tanks', 'href' => home_url('/hueco-rock-ranch/')],
					['label' => 'New River Gorge', 'href' => home_url('/new-river-gorge-campground/')],
				],
			],
			'publications' => [
				'label' => 'Publications',
				'href' => home_url('/publications/'),
				'children' => [
					['label' => 'AAJ', 'href' => home_url('/publications/aaj/')],
					['label' => 'Accidents', 'href' => home_url('/publications/accidents/')],
					['label' => 'Podcasts', 'href' => home_url('/the-american-alpine-club-podcast/')],
				],
			],
			'our_work' => [
				'label' => 'Our Work',
				'href' => home_url('/our-work/'),
				'children' => [
					['label' => "Gov't Affairs", 'href' => home_url('/advocacy/')],
					['label' => 'Grants', 'href' => home_url('/grants/')],
					['label' => 'Grief Fund', 'href' => home_url('/grieffund/')],
					['label' => 'Library', 'href' => home_url('/library/')],
					['label' => 'Chapters', 'href' => home_url('/chapters/')],
				],
			],
		];
	}

	private function get_sidebar_item_registry() {
		return [
			'member_profile' => ['icon' => 'user', 'route' => '/profile'],
			'store' => ['icon' => 'store', 'route' => '/store'],
			'rescue' => ['icon' => 'shield', 'href' => $this->get_rescue_page_url()],
			'account' => ['icon' => 'pen', 'route' => '/account'],
			'publications' => ['icon' => 'book', 'route' => '/publications'],
			'manage' => ['icon' => 'settings', 'href' => home_url('/membership-account/membership-billing/')],
			'discounts' => ['icon' => 'tag', 'route' => '/discounts'],
			'podcasts' => ['icon' => 'mic', 'route' => '/podcasts'],
			'events' => ['icon' => 'users', 'route' => '/meetups'],
			'lodging' => ['icon' => 'bed', 'route' => '/lodging'],
			'grants' => ['icon' => 'scroll-text', 'route' => '/grants'],
			'contact' => ['icon' => 'mail', 'route' => '/contact'],
		];
	}

	private function get_resolved_sidebar_background_url($settings) {
		$custom_url = trim((string) ($settings['design']['sidebar_background_url'] ?? ''));
		if ($custom_url !== '') {
			return $custom_url;
		}

		return AAC_MEMBER_PORTAL_URL . 'app/sidebar-topo-v2.svg';
	}

	private function get_rescue_page_url() {
		$rescue_page = get_page_by_path('rescue', OBJECT, 'page');
		if ($rescue_page instanceof WP_Post) {
			return get_permalink($rescue_page);
		}

		return home_url('/rescue/');
	}

	private function get_grant_review_page_url() {
		$page_id = absint(get_option('aac_grants_review_page_id', 0));
		if ($page_id > 0) {
			$page_url = get_permalink($page_id);
			if ($page_url) {
				return $page_url;
			}
		}

		$review_page = get_page_by_path('grant-review', OBJECT, 'page');
		if ($review_page instanceof WP_Post) {
			return get_permalink($review_page);
		}

		return home_url('/grant-review/');
	}

	private function get_shortcode_post() {
		if (!is_singular()) {
			return null;
		}

		$post = get_post();
		if (!$post instanceof WP_Post) {
			return null;
		}

		if (!has_shortcode($post->post_content, self::SHORTCODE)) {
			return null;
		}

		return $post;
	}

	private function get_pmpro_shell_post() {
		if (!AAC_Member_Portal_PMPro::is_available() || !function_exists('pmpro_url')) {
			return null;
		}

		$post = get_post();
		$current_permalink = $post instanceof WP_Post ? untrailingslashit(get_permalink($post)) : '';
		$current_path = $current_permalink ? untrailingslashit((string) wp_parse_url($current_permalink, PHP_URL_PATH)) : '';
		$request_path = '';
		if (!empty($_SERVER['REQUEST_URI'])) {
			$request_path = untrailingslashit((string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH));
		}
		$managed_pages = [
			untrailingslashit(pmpro_url('account')),
			untrailingslashit(pmpro_url('billing')),
			untrailingslashit(pmpro_url('invoice')),
			untrailingslashit(pmpro_url('cancel')),
			untrailingslashit(pmpro_url('checkout')),
			untrailingslashit(pmpro_url('confirmation')),
		];

		foreach ($managed_pages as $managed_page) {
			if (!$managed_page) {
				continue;
			}

			$managed_path = untrailingslashit((string) wp_parse_url($managed_page, PHP_URL_PATH));
			if (
				$managed_page === $current_permalink ||
				($managed_path && $managed_path === $current_path) ||
				($managed_path && $managed_path === $request_path)
			) {
				if ($post instanceof WP_Post) {
					return $post;
				}

				$queried = get_queried_object();
				return $queried instanceof WP_Post ? $queried : null;
			}
		}

		$managed_paths = [
			'membership-account',
			'membership-account/membership-billing',
			'membership-account/membership-orders',
			'membership-account/membership-cancel',
			'membership-checkout',
			'membership-checkout/membership-confirmation',
		];
		$normalized_request_path = ltrim($request_path, '/');
		if ($normalized_request_path) {
			foreach ($managed_paths as $managed_path) {
				if ($normalized_request_path !== $managed_path) {
					continue;
				}

				$managed_post = get_page_by_path($managed_path, OBJECT, 'page');
				if ($managed_post instanceof WP_Post) {
					return $managed_post;
				}
			}
		}

		$managed_slugs = ['membership-account', 'membership-billing', 'membership-orders', 'membership-cancel', 'membership-checkout', 'membership-confirmation'];
		if ($post instanceof WP_Post && in_array($post->post_name, $managed_slugs, true)) {
			return $post;
		}

		return null;
	}

	private function get_public_shell_post() {
		if (!is_singular('page')) {
			return null;
		}

		$post = get_post();
		if (!$post instanceof WP_Post) {
			return null;
		}

		$public_slugs = ['benefits', 'rescue'];
		if (!in_array($post->post_name, $public_slugs, true)) {
			return null;
		}

		return $post;
	}

	private function should_use_portal_login($redirect = '') {
		if ($this->is_wp_admin_auth_request($redirect)) {
			return false;
		}

		if ($this->is_pmpro_frontend_request()) {
			return true;
		}

		return $this->is_pmpro_frontend_url($redirect);
	}

	private function is_frontend_login_request() {
		$login_path = $this->normalize_path(home_url('/login/'));
		$request_path = $this->get_current_request_path();

		return $request_path && $login_path && $request_path === $login_path;
	}

	private function is_pmpro_frontend_request() {
		$request_path = $this->get_current_request_path();
		if (!$request_path) {
			return false;
		}

		foreach ($this->get_pmpro_frontend_paths() as $managed_path) {
			if ($managed_path && $managed_path === $request_path) {
				return true;
			}
		}

		return false;
	}

	private function is_pmpro_frontend_url($url) {
		if (!$url) {
			return false;
		}

		$target_path = $this->normalize_path($url);
		if (!$target_path) {
			return false;
		}

		foreach ($this->get_pmpro_frontend_paths() as $managed_path) {
			if ($managed_path && $managed_path === $target_path) {
				return true;
			}
		}

		return false;
	}

	private function is_wp_admin_auth_request($redirect = '') {
		if (isset($_REQUEST['interim-login']) || isset($_REQUEST['reauth'])) {
			return true;
		}

		$request_path = $this->get_current_request_path();
		if ($request_path && ($this->is_wp_admin_path($request_path) || $request_path === $this->normalize_path($this->get_wp_login_base_url()))) {
			return true;
		}

		if ($redirect && $this->is_wp_admin_url($redirect)) {
			return true;
		}

		return false;
	}

	private function should_preserve_wp_login_url($login_url, $redirect = '') {
		if (!$login_url) {
			return false;
		}

		$query = wp_parse_url($login_url, PHP_URL_QUERY);
		if (!is_string($query) || $query === '') {
			return $redirect && $this->is_wp_admin_url($redirect);
		}

		parse_str($query, $query_args);
		if (!empty($query_args['interim-login']) || !empty($query_args['reauth'])) {
			return true;
		}

		if (!empty($query_args['redirect_to']) && $this->is_wp_admin_url($query_args['redirect_to'])) {
			return true;
		}

		return $redirect && $this->is_wp_admin_url($redirect);
	}

	private function build_wp_login_url_from_current_request($redirect = '') {
		$query_args = [];

		if ($redirect) {
			$query_args['redirect_to'] = $redirect;
		}

		if (isset($_GET['interim-login'])) {
			$query_args['interim-login'] = sanitize_text_field(wp_unslash($_GET['interim-login']));
		}

		if (isset($_GET['reauth'])) {
			$query_args['reauth'] = sanitize_text_field(wp_unslash($_GET['reauth']));
		}

		if (isset($_GET['wp_lang'])) {
			$query_args['wp_lang'] = sanitize_text_field(wp_unslash($_GET['wp_lang']));
		}

		return add_query_arg($query_args, $this->get_wp_login_base_url());
	}

	private function is_wp_admin_url($url) {
		$target_path = $this->normalize_path($url);
		if (!$target_path) {
			return false;
		}

		return $this->is_wp_admin_path($target_path);
	}

	private function is_wp_admin_path($path) {
		$admin_path = $this->normalize_path(admin_url());
		if (!$path || !$admin_path) {
			return false;
		}

		return $path === $admin_path || strpos($path, $admin_path . '/') === 0;
	}

	private function get_pmpro_frontend_paths() {
		$managed_paths = [];
		$pmpro_pages = ['account', 'billing', 'cancel', 'checkout'];

		foreach ($pmpro_pages as $page) {
			$page_url = AAC_Member_Portal_PMPro::is_available() && function_exists('pmpro_url')
				? pmpro_url($page)
				: '';
			$page_path = $this->normalize_path($page_url);
			if ($page_path) {
				$managed_paths[] = $page_path;
			}
		}

		return array_values(array_unique($managed_paths));
	}

	private function build_portal_login_url($redirect_to = '') {
		$portal_url = $this->get_portal_page_url();
		if (!$portal_url) {
			$portal_url = home_url('/membership/');
		}

		$target = $portal_url;
		$validated_redirect = $redirect_to ? wp_validate_redirect($redirect_to, '') : '';
		if ($validated_redirect) {
			$target = add_query_arg('redirect_to', $validated_redirect, $target);
		}

		$separator = (false !== strpos($target, '?') || substr($target, -1) === '/') ? '' : '/';
		return $target . $separator . '#/login';
	}

	private function build_portal_app_url($route = '') {
		$portal_url = untrailingslashit($this->get_portal_page_url());
		$normalized_route = trim((string) $route, '/');

		if ($normalized_route === '') {
			return $portal_url . '/';
		}

		return $portal_url . '/#/' . $normalized_route;
	}

	private function get_current_request_path() {
		if (empty($_SERVER['REQUEST_URI'])) {
			return '';
		}

		return $this->normalize_path(wp_unslash($_SERVER['REQUEST_URI']));
	}

	private function get_current_request_url() {
		if (empty($_SERVER['REQUEST_URI'])) {
			return '';
		}

		return home_url(wp_unslash($_SERVER['REQUEST_URI']));
	}

	private function get_wp_login_base_url() {
		return home_url('/wp-login.php');
	}

	private function normalize_path($url) {
		if (!$url) {
			return '';
		}

		$path = wp_parse_url((string) $url, PHP_URL_PATH);
		if (!is_string($path) || $path === '') {
			return '';
		}

		return untrailingslashit($path);
	}

	private function is_pmpro_change_password_request() {
		$request_path = $this->get_current_request_path();
		$expected_path = $this->normalize_path(home_url('/membership-account/your-profile/'));
		$view = isset($_GET['view']) ? sanitize_text_field(wp_unslash($_GET['view'])) : '';

		return $request_path !== '' && $request_path === $expected_path && $view === 'change-password';
	}

	private function is_pmpro_checkout_request() {
		$request_path = $this->get_current_request_path();
		$checkout_path = AAC_Member_Portal_PMPro::is_available() && function_exists('pmpro_url')
			? $this->normalize_path(pmpro_url('checkout'))
			: $this->normalize_path(home_url('/membership-checkout/'));

		return $request_path !== '' && $checkout_path !== '' && $request_path === $checkout_path;
	}

	private function should_capture_fatal_for_request($request_uri) {
		$request_uri = (string) $request_uri;
		if ($request_uri === '') {
			return false;
		}

		if (strpos($request_uri, '/wp-json/aac/v1/register') !== false) {
			return true;
		}

		$checkout_path = AAC_Member_Portal_PMPro::is_available() && function_exists('pmpro_url')
			? $this->normalize_path(pmpro_url('checkout'))
			: $this->normalize_path(home_url('/membership-checkout/'));
		$request_path = $this->normalize_path($request_uri);

		return $request_path !== '' && $checkout_path !== '' && $request_path === $checkout_path;
	}

	private function is_fatal_error($error) {
		if (!is_array($error) || !isset($error['type'])) {
			return false;
		}

		return in_array((int) $error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true);
	}

	private function should_disable_wp_fusion_pmpro_hooks() {
		if (!class_exists('WPF_PMPro_Hooks')) {
			return false;
		}

		if (!function_exists('wp_fusion')) {
			return true;
		}

		try {
			$fusion = wp_fusion();
		} catch (Throwable $throwable) {
			return true;
		}

		if (!is_object($fusion)) {
			return true;
		}

		$user = isset($fusion->user) ? $fusion->user : null;

		return !is_object($user) || !method_exists($user, 'push_user_meta');
	}

	private function remove_class_callbacks($hook_name, $class_name) {
		if (empty($GLOBALS['wp_filter'][$hook_name])) {
			return;
		}

		$wp_hook = $GLOBALS['wp_filter'][$hook_name];
		$callbacks = is_object($wp_hook) && isset($wp_hook->callbacks) ? $wp_hook->callbacks : [];
		if (!is_array($callbacks)) {
			return;
		}

		foreach ($callbacks as $priority => $group) {
			if (!is_array($group)) {
				continue;
			}

			foreach ($group as $callback_config) {
				$callback = $callback_config['function'] ?? null;
				if (!is_array($callback) || !is_object($callback[0]) || !isset($callback[1])) {
					continue;
				}

				if (get_class($callback[0]) !== $class_name) {
					continue;
				}

				remove_action($hook_name, [$callback[0], $callback[1]], $priority);
			}
		}
	}

	private function is_wp_fusion_pmpro_request_context() {
		if (is_admin()) {
			return false;
		}

		$request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
		if ($request_uri === '') {
			return false;
		}

		if (strpos($request_uri, '/wp-json/aac/v1/register') !== false) {
			return true;
		}

		return $this->is_pmpro_checkout_request();
	}

	private function is_wp_fusion_shim_context() {
		$current_filter = current_filter();
		if (in_array($current_filter, ['profile_update', 'pmpro_after_change_membership_level'], true)) {
			return true;
		}

		return $this->is_wp_fusion_pmpro_request_context();
	}

	/**
	 * Prefer the newest matching file so stale hashed bundles are not chosen when
	 * multiple index-*.js (or .css) files exist after partial uploads.
	 */
	private function first_glob_match($pattern) {
		$matches = glob($pattern);
		if (!$matches) {
			return null;
		}

		usort($matches, static function ($a, $b) {
			$ma = @filemtime($a) ?: 0;
			$mb = @filemtime($b) ?: 0;
			if ($ma === $mb) {
				return strcmp($b, $a);
			}
			return $mb <=> $ma;
		});

		return $matches[0];
	}
}

register_activation_hook(AAC_MEMBER_PORTAL_FILE, ['AAC_Member_Portal_Member_Database', 'activate']);
register_activation_hook(AAC_MEMBER_PORTAL_FILE, ['AAC_Member_Portal_Daily_Member_Export', 'activate']);
register_deactivation_hook(AAC_MEMBER_PORTAL_FILE, ['AAC_Member_Portal_Daily_Member_Export', 'deactivate']);
$GLOBALS['aac_member_portal_plugin'] = new AAC_Member_Portal_Plugin();

function aac_member_portal() {
	return isset($GLOBALS['aac_member_portal_plugin']) && $GLOBALS['aac_member_portal_plugin'] instanceof AAC_Member_Portal_Plugin
		? $GLOBALS['aac_member_portal_plugin']
		: null;
}
