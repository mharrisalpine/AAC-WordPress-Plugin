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
require_once AAC_MEMBER_PORTAL_DIR . 'includes/class-aac-member-portal-admin.php';

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
		new AAC_Member_Portal_API();
		new AAC_Member_Portal_Admin();

		add_shortcode(self::SHORTCODE, [$this, 'render_shortcode']);
		add_action('plugins_loaded', [$this, 'maybe_disable_broken_wp_fusion_pmpro_hooks'], 100);
		add_action('init', [$this, 'maybe_shim_broken_wp_fusion_user_service'], 20);
		add_action('init', [$this, 'maybe_disable_broken_wp_fusion_pmpro_hooks'], 1000);
		add_action('profile_update', [$this, 'maybe_shim_broken_wp_fusion_user_service'], 1, 3);
		add_action('pmpro_after_change_membership_level', [$this, 'maybe_shim_broken_wp_fusion_user_service'], 1, 3);
		add_action('wp_enqueue_scripts', [$this, 'register_assets']);
		add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_portal_for_shortcode'], 15);
		add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_shell_styles'], 15);
		add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_frontend_admin_bar_hiding_style'], 99);
		add_action('template_redirect', [$this, 'maybe_disable_frontend_admin_bar'], 0);
		add_action('template_redirect', [$this, 'maybe_render_managed_fullscreen_template'], 0);
		add_action('template_redirect', [$this, 'maybe_redirect_frontend_login_to_portal'], 1);
		add_action('template_redirect', [$this, 'maybe_redirect_pmpro_change_password_to_portal'], 1);
		add_action('init', [$this, 'maybe_seed_pmpro_checkout_username'], 1);
		add_action('init', [$this, 'maybe_apply_partner_family_checkout_level_override'], 2);
		add_action('shutdown', [$this, 'capture_relevant_fatal'], PHP_INT_MAX);
		add_action('wp_footer', [$this, 'maybe_render_frontend_admin_bar_removal_script'], 1001);
		add_filter('the_content', [$this, 'maybe_wrap_managed_pmpro_content'], 20);
		add_action('admin_init', [$this, 'maybe_restore_pmpro_admin_capabilities']);
		add_filter('user_has_cap', [$this, 'maybe_grant_pmpro_admin_capabilities'], 20, 4);
		add_filter('login_url', [$this, 'filter_login_url_to_portal'], 20, 3);
		add_filter('pmpro_required_user_fields', [$this, 'filter_pmpro_required_user_fields']);
		add_filter('pmpro_required_billing_fields', [$this, 'filter_pmpro_required_billing_fields']);
		add_filter('pmpro_checkout_new_user_array', [$this, 'filter_pmpro_checkout_new_user_array']);
		add_action('pmpro_checkout_after_user_fields', [$this, 'render_pmpro_membership_discounts'], 9);
		add_action('pmpro_checkout_after_user_fields', [$this, 'render_pmpro_partner_family_options'], 12);
		add_action('pmpro_checkout_after_user_fields', [$this, 'render_pmpro_magazine_addons']);
		add_filter('pmpro_checkout_level', [$this, 'filter_pmpro_checkout_level_for_magazine_addons']);
		add_action('pmpro_after_checkout', [$this, 'capture_pmpro_checkout_order_breakdown'], 20, 2);
		add_action('pmpro_after_change_membership_level', [$this, 'sync_pmpro_checkout_profile_fields'], 20, 2);
		add_filter('pmpro_confirmation_message', [$this, 'append_pmpro_confirmation_line_items'], 20, 2);
		add_filter('show_admin_bar', [$this, 'maybe_hide_frontend_admin_bar'], 20);
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
		if (!$this->is_partner_family_checkout_level($requested_level_id)) {
			return;
		}

		$target_level_id = $this->should_apply_family_membership_request()
			? $this->get_partner_family_level_id()
			: $this->get_partner_level_id();

		if (!$target_level_id) {
			return;
		}

		$_REQUEST['level'] = $target_level_id;
		$_GET['level'] = $target_level_id;
		$_POST['level'] = $target_level_id;
	}

	public function render_pmpro_membership_discounts() {
		$discount_options = $this->get_membership_discount_catalog();
		if (empty($discount_options)) {
			return;
		}

		$selected_discount = $this->has_membership_discount_request()
			? $this->get_requested_membership_discount_type()
			: '';
		$checkout_level = $this->get_level_at_checkout();
		$base_level_total = max(0, $this->get_level_recurring_total($checkout_level));
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
									<div class="pmpro_form_field pmpro_form_field-radio aac-membership-discounts__field">
										<label class="pmpro_form_label pmpro_form_label-inline aac-membership-discounts__label" for="<?php echo esc_attr('aac_membership_discount_' . $slug); ?>">
											<input
												id="<?php echo esc_attr('aac_membership_discount_' . $slug); ?>"
												class="aac-membership-discounts__input"
												type="radio"
												name="aac_membership_discount"
												value="<?php echo esc_attr($slug); ?>"
												data-aac-membership-discount-rate="<?php echo esc_attr(number_format((float) $discount['rate'], 2, '.', '')); ?>"
												data-aac-membership-discount-label="<?php echo esc_attr($discount['label']); ?>"
												data-aac-toggleable-radio="true"
												<?php checked($selected_discount, $slug); ?>
											/>
											<span class="aac-membership-discounts__card">
												<span class="aac-membership-discounts__icon" aria-hidden="true">
													<?php echo $discount['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
												</span>
												<span class="aac-membership-discounts__body">
													<span class="aac-membership-discounts__copy">
														<strong><?php echo esc_html($discount['label']); ?></strong>
														<span><?php echo esc_html($discount['description']); ?></span>
													</span>
													<span class="aac-membership-discounts__footer">
														<span class="aac-membership-discounts__price"><?php echo esc_html($discount['badge']); ?></span>
													</span>
												</span>
											</span>
										</label>
									</div>
								<?php endforeach; ?>
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
		$base_level_total = max(0, $this->get_level_recurring_total($checkout_level));
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
						<h2 class="pmpro_form_heading pmpro_font-large"><?php esc_html_e('Partner Family Membership', 'aac-member-portal'); ?></h2>
					</legend>
					<div class="pmpro_form_fields">
						<input type="hidden" name="aac_partner_family_present" value="1" />
						<p class="aac-partner-family__intro">
							<?php esc_html_e('Partner memberships can be expanded into a family plan with one discounted adult and up to three dependents.', 'aac-member-portal'); ?>
						</p>
						<div class="aac-partner-family__mode" role="radiogroup" aria-label="<?php esc_attr_e('Partner family selection', 'aac-member-portal'); ?>">
							<label class="aac-partner-family__mode-option" for="aac_partner_family_mode_none">
								<input
									id="aac_partner_family_mode_none"
									type="radio"
									name="aac_partner_family_mode"
									value=""
									<?php checked($family_config['mode'], ''); ?>
								/>
								<span><?php esc_html_e('Individual membership', 'aac-member-portal'); ?></span>
							</label>
							<label class="aac-partner-family__mode-option" for="aac_partner_family_mode_family">
								<input
									id="aac_partner_family_mode_family"
									type="radio"
									name="aac_partner_family_mode"
									value="family"
									<?php checked($family_config['mode'], 'family'); ?>
								/>
								<span><?php esc_html_e('Family membership', 'aac-member-portal'); ?></span>
							</label>
						</div>
						<div class="aac-partner-family__details" data-aac-partner-family-details>
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
										<span><?php esc_html_e('One adult may be added at 40% off the Partner list price.', 'aac-member-portal'); ?></span>
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
		$base_level_total = max(0, $this->get_level_recurring_total($checkout_level) - $selected_addon_total);
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

		$base_membership_total = $this->get_level_recurring_total($level);
		$membership_discount_type = $this->get_requested_membership_discount_type();
		$membership_discount_amount = $this->get_membership_discount_amount($base_membership_total, $membership_discount_type);
		$partner_family_config = $this->get_requested_partner_family_config();
		$partner_family_total = $this->get_partner_family_addon_total($base_membership_total, $partner_family_config);
		$selected_addons = $this->get_requested_magazine_addons();
		$addon_total = $this->get_magazine_addon_total($selected_addons);
		if ($addon_total <= 0 && $membership_discount_amount <= 0 && $partner_family_total <= 0) {
			return $level;
		}

		if (isset($level->initial_payment)) {
			$level->initial_payment = round(max(0, (float) $level->initial_payment - $membership_discount_amount) + $partner_family_total + $addon_total, 2);
		}

		if (isset($level->billing_amount) && (float) $level->billing_amount > 0) {
			$level->billing_amount = round(max(0, (float) $level->billing_amount - $membership_discount_amount) + $partner_family_total + $addon_total, 2);
		}

		return $level;
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
			'phone_type' => $stored_account_info['phone_type'] ?? '',
			'street' => isset($_REQUEST['baddress1']) ? sanitize_text_field(wp_unslash($_REQUEST['baddress1'])) : ($stored_account_info['street'] ?? ''),
			'address2' => isset($_REQUEST['baddress2']) ? sanitize_text_field(wp_unslash($_REQUEST['baddress2'])) : ($stored_account_info['address2'] ?? ''),
			'city' => isset($_REQUEST['bcity']) ? sanitize_text_field(wp_unslash($_REQUEST['bcity'])) : ($stored_account_info['city'] ?? ''),
			'state' => isset($_REQUEST['bstate']) ? sanitize_text_field(wp_unslash($_REQUEST['bstate'])) : ($stored_account_info['state'] ?? ''),
			'zip' => isset($_REQUEST['bzipcode']) ? sanitize_text_field(wp_unslash($_REQUEST['bzipcode'])) : ($stored_account_info['zip'] ?? ''),
			'country' => isset($_REQUEST['bcountry']) ? sanitize_text_field(wp_unslash($_REQUEST['bcountry'])) : ($stored_account_info['country'] ?? ''),
			'size' => isset($_REQUEST['t_shirt']) ? sanitize_text_field(wp_unslash($_REQUEST['t_shirt'])) : ($stored_account_info['size'] ?? 'M'),
			'publication_pref' => $this->normalize_print_digital_value(
				isset($_REQUEST['aac_publication_pref']) ? sanitize_text_field(wp_unslash($_REQUEST['aac_publication_pref'])) : ($stored_account_info['publication_pref'] ?? 'Digital')
			),
			'guidebook_pref' => $this->normalize_print_digital_value(
				isset($_REQUEST['aac_guidebook_pref']) ? sanitize_text_field(wp_unslash($_REQUEST['aac_guidebook_pref'])) : ($stored_account_info['guidebook_pref'] ?? 'Digital')
			),
			'photo_url' => $stored_account_info['photo_url'] ?? get_avatar_url($user_id),
			'auto_renew' => isset($_REQUEST['autorenew_present'])
				? !empty($_REQUEST['autorenew'])
				: !empty($stored_account_info['auto_renew']),
			'payment_method' => $stored_account_info['payment_method'] ?? '',
		];

		$next_account_info['name'] = trim($next_account_info['first_name'] . ' ' . $next_account_info['last_name']);
		if ($next_account_info['name'] === '') {
			$next_account_info['name'] = $stored_account_info['name'] ?? $user->display_name;
		}

		$selected_magazine_addons = $this->has_magazine_addon_request()
			? $this->get_requested_magazine_addons()
			: $this->get_effective_magazine_addon_selection($user_id);
		$membership_discount_type = $this->has_membership_discount_request()
			? $this->get_requested_membership_discount_type()
			: $this->get_effective_membership_discount_type($user_id);
		$partner_family_config = $this->has_partner_family_request()
			? $this->get_requested_partner_family_config()
			: $this->get_effective_partner_family_config($user_id);

		if ($this->has_magazine_addon_request()) {
			update_user_meta($user_id, 'aac_magazine_addons', $selected_magazine_addons);
		}

		if ($this->has_membership_discount_request()) {
			update_user_meta($user_id, 'aac_membership_discount_type', $membership_discount_type);
		}

		if ($this->has_partner_family_request()) {
			update_user_meta($user_id, 'aac_partner_family_config', $partner_family_config);
		}

		update_user_meta($user_id, 'aac_account_info', $next_account_info);
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

		if (isset($_REQUEST['aac_publication_pref'])) {
			$account_info['publication_pref'] = $this->normalize_print_digital_value(sanitize_text_field(wp_unslash($_REQUEST['aac_publication_pref'])));
		}

		if (isset($_REQUEST['aac_guidebook_pref'])) {
			$account_info['guidebook_pref'] = $this->normalize_print_digital_value(sanitize_text_field(wp_unslash($_REQUEST['aac_guidebook_pref'])));
		}

		if (isset($_REQUEST['t_shirt']) && sanitize_text_field(wp_unslash($_REQUEST['t_shirt'])) !== '') {
			$account_info['size'] = sanitize_text_field(wp_unslash($_REQUEST['t_shirt']));
		}

		return [
			'publication_pref' => $account_info['publication_pref'],
			'guidebook_pref' => $account_info['guidebook_pref'],
			'size' => $account_info['size'],
		];
	}

	private function get_membership_discount_catalog() {
		return [
			'student' => [
				'label' => 'Student Discount',
				'description' => 'Apply 15% off your annual membership.',
				'badge' => '15% off membership',
				'rate' => 0.15,
				'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="m2 9 10-5 10 5-10 5-10-5Z"/><path d="M6 11.5v4.5c0 .8 2.7 3 6 3s6-2.2 6-3v-4.5"/><path d="M22 9v6"/></svg>',
			],
			'military' => [
				'label' => 'Military Discount',
				'description' => 'Apply 15% off your annual membership.',
				'badge' => '15% off membership',
				'rate' => 0.15,
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

	private function should_apply_family_membership_request() {
		$config = $this->get_requested_partner_family_config();
		return $config['mode'] === 'family';
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

	private function is_partner_family_checkout_level($level) {
		$level_id = 0;
		$level_name = '';

		if (is_object($level)) {
			$level_id = isset($level->id) ? (int) $level->id : 0;
			$level_name = isset($level->name) ? (string) $level->name : '';
		} else {
			$level_id = (int) $level;
		}

		$partner_level_id = $this->get_partner_level_id();
		$partner_family_level_id = $this->get_partner_family_level_id();

		return in_array($level_id, array_filter([$partner_level_id, $partner_family_level_id]), true)
			|| in_array($level_name, ['Partner', 'Partner Family'], true);
	}

	private function get_partner_family_pricing($base_membership_total) {
		$base_membership_total = max(0, (float) $base_membership_total);

		return [
			'additional_adult_price' => round($base_membership_total * 0.6, 2),
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

		if ($family_config['mode'] !== 'family') {
			delete_user_meta($user_id, 'aac_connected_accounts');
			return;
		}

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
				'status' => in_array(($slot['status'] ?? ''), ['pending', 'connected'], true) ? $slot['status'] : 'pending',
				'invite_code' => sanitize_text_field($slot['invite_code'] ?? $this->generate_family_invite_code()),
				'child_user_id' => absint($slot['child_user_id'] ?? 0),
				'child_name' => sanitize_text_field($slot['child_name'] ?? ''),
				'child_email' => sanitize_email($slot['child_email'] ?? ''),
				'price' => round((float) ($slot['price'] ?? 0), 2),
			];
		}

		$pricing = $this->get_partner_family_pricing($base_membership_total);
		$next_slots = [];

		if (!empty($family_config['additional_adult'])) {
			$next_slots[] = $this->preserve_or_create_family_slot(
				$normalized_existing,
				'adult',
				'Additional adult',
				(float) $pricing['additional_adult_price']
			);
		}

		$dependent_count = max(0, (int) $family_config['dependent_count']);
		for ($dependent_index = 1; $dependent_index <= $dependent_count; $dependent_index++) {
			$next_slots[] = $this->preserve_or_create_family_slot(
				$normalized_existing,
				'dependent',
				sprintf('Dependent %d', $dependent_index),
				(float) $pricing['dependent_price']
			);
		}

		update_user_meta($user_id, 'aac_connected_accounts', $next_slots);
	}

	private function preserve_or_create_family_slot(&$existing_slots, $type, $label, $price) {
		foreach ($existing_slots as $index => $slot) {
			if (($slot['type'] ?? '') !== $type) {
				continue;
			}

			unset($existing_slots[$index]);
			$slot['label'] = $label;
			$slot['price'] = round((float) $price, 2);
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
		];
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
		$base_membership_amount = max(0, $this->get_level_recurring_total($level));
		$membership_discount_type = $this->has_membership_discount_request()
			? $this->get_requested_membership_discount_type()
			: $this->get_effective_membership_discount_type($user_id);
		$membership_discount_catalog = $this->get_membership_discount_catalog();
		$membership_discount_amount = $this->get_membership_discount_amount($base_membership_amount, $membership_discount_type);
		$partner_family_config = $this->has_partner_family_request()
			? $this->get_requested_partner_family_config()
			: $this->get_effective_partner_family_config($user_id);
		$partner_family_pricing = $this->get_partner_family_pricing($base_membership_amount);
		$partner_family_additional_adult_amount = !empty($partner_family_config['additional_adult']) ? (float) $partner_family_pricing['additional_adult_price'] : 0.0;
		$partner_family_dependents_amount = max(0, (int) ($partner_family_config['dependent_count'] ?? 0)) * (float) $partner_family_pricing['dependent_price'];
		$selected_addons = $this->has_magazine_addon_request()
			? $this->get_requested_magazine_addons()
			: $this->get_effective_magazine_addon_selection($user_id);
		$catalog = $this->get_magazine_addon_catalog();
		$magazine_total = $this->get_magazine_addon_total($selected_addons);
		$donation_amount = $this->get_requested_donation_amount();
		$items = [];

		if ($base_membership_amount > 0 || (!$selected_addons && $donation_amount <= 0)) {
			$items[] = [
				'label' => $level_name !== '' ? sprintf('%s membership', $level_name) : 'Membership',
				'amount' => $base_membership_amount > 0 ? $base_membership_amount : $total_amount,
			];
		}

		if ($membership_discount_amount > 0) {
			$items[] = [
				'label' => !empty($membership_discount_catalog[$membership_discount_type]['label'])
					? sprintf('%s (15%%)', $membership_discount_catalog[$membership_discount_type]['label'])
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
				'label' => $level_name !== '' ? sprintf('%s membership', $level_name) : 'Membership',
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

		$billing_amount = isset($level->billing_amount) ? (float) $level->billing_amount : 0.0;
		if ($billing_amount > 0) {
			return $billing_amount;
		}

		return isset($level->initial_payment) ? (float) $level->initial_payment : 0.0;
	}

	public function maybe_disable_frontend_admin_bar() {
		if (is_admin()) {
			return;
		}

		show_admin_bar(false);
		remove_action('wp_body_open', 'wp_admin_bar_render', 0);
		remove_action('wp_footer', 'wp_admin_bar_render', 1000);
	}

	public function maybe_hide_frontend_admin_bar($show_admin_bar) {
		if (is_admin()) {
			return $show_admin_bar;
		}

		return false;
	}

	public function maybe_enqueue_frontend_admin_bar_hiding_style() {
		if (is_admin()) {
			return;
		}

		$handle = 'aac-member-portal-admin-bar-fix';
		if (!wp_style_is($handle, 'registered')) {
			wp_register_style($handle, false, [], AAC_MEMBER_PORTAL_VERSION);
		}

		wp_enqueue_style($handle);
		wp_add_inline_style(
			$handle,
			'html{margin-top:0!important;}' .
			'body{margin-top:0!important;padding-top:0!important;}' .
			'body.admin-bar{margin-top:0!important;padding-top:0!important;}' .
			'#wpadminbar{display:none!important;visibility:hidden!important;opacity:0!important;pointer-events:none!important;}'
		);
	}

	public function maybe_render_frontend_admin_bar_removal_script() {
		if (is_admin()) {
			return;
		}

		?>
		<script id="aac-member-portal-remove-admin-bar">
			(function () {
				function removeAdminBar() {
					var adminBar = document.getElementById('wpadminbar');
					if (adminBar) {
						adminBar.remove();
					}

					document.documentElement.style.marginTop = '0';
					if (document.body) {
						document.body.style.marginTop = '0';
						document.body.style.paddingTop = '0';
						document.body.classList.remove('admin-bar');
					}
				}

				if (document.readyState === 'loading') {
					document.addEventListener('DOMContentLoaded', removeAdminBar, { once: true });
				} else {
					removeAdminBar();
				}
			})();
		</script>
		<?php
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

		return array_merge([
			'first_name' => $user instanceof WP_User ? $user->first_name : '',
			'last_name' => $user instanceof WP_User ? $user->last_name : '',
			'name' => $user instanceof WP_User ? $user->display_name : '',
			'email' => $user instanceof WP_User ? $user->user_email : '',
			'photo_url' => $user_id ? get_avatar_url($user_id) : '',
			'phone' => '',
			'phone_type' => '',
			'street' => '',
			'address2' => '',
			'city' => '',
			'state' => '',
			'zip' => '',
			'country' => 'US',
			'size' => 'M',
			'publication_pref' => 'Digital',
			'guidebook_pref' => 'Digital',
			'magazine_subscriptions' => [],
			'membership_discount_type' => '',
			'partner_family_mode' => '',
			'partner_family_additional_adult' => false,
			'partner_family_dependents' => 0,
			'auto_renew' => false,
			'payment_method' => '',
		], $stored);
	}

	private function normalize_print_digital_value($value, $fallback = 'Digital') {
		return $value === 'Print' ? 'Print' : ($value === 'Digital' ? 'Digital' : $fallback);
	}

	private function sync_reportable_member_fields($user_id, $account_info, $magazine_addons = null, $membership_discount_type = null) {
		$user_id = (int) $user_id;
		if ($user_id <= 0 || !is_array($account_info)) {
			return;
		}

		update_user_meta($user_id, 'aac_tshirt_size', sanitize_text_field($account_info['size'] ?? ''));
		update_user_meta(
			$user_id,
			'aac_publication_pref',
			$this->normalize_print_digital_value($account_info['publication_pref'] ?? 'Digital')
		);
		update_user_meta(
			$user_id,
			'aac_guidebook_pref',
			$this->normalize_print_digital_value($account_info['guidebook_pref'] ?? 'Digital')
		);

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
		return [
			'mountId' => self::MOUNT_ID,
			'routerMode' => 'hash',
			'apiBase' => untrailingslashit(rest_url('aac/v1')),
			'restNonce' => wp_create_nonce('wp_rest'),
			'isLoggedIn' => is_user_logged_in(),
			'portalPageUrl' => untrailingslashit($this->get_portal_page_url()),
			'mainWebsiteBaseUrl' => untrailingslashit(home_url()),
			'portalSettings' => $this->get_portal_ui_settings(),
		];
	}

	public function get_portal_ui_settings() {
		$settings = AAC_Member_Portal_Admin::get_settings();
		$resolved_background_url = $this->get_resolved_sidebar_background_url($settings);

		return [
			'content' => $settings['content'],
			'design' => [
				'sidebarBackgroundUrl' => $resolved_background_url,
				'sidebarOverlayStart' => $settings['design']['sidebar_overlay_start'],
				'sidebarOverlayEnd' => $settings['design']['sidebar_overlay_end'],
				'sidebarButtonBackground' => $settings['design']['sidebar_button_background'],
				'sidebarButtonHoverBackground' => $settings['design']['sidebar_button_hover_background'],
				'sidebarButtonActiveBackground' => $settings['design']['sidebar_button_active_background'],
				'sidebarAccentColor' => $settings['design']['sidebar_accent_color'],
			],
			'navigation' => [
				'topNavSections' => $this->build_top_nav_sections_for_runtime($settings),
				'sidebarSections' => $this->build_sidebar_sections_for_runtime($settings),
			],
		];
	}

	public function get_template_top_nav_sections($portal_url) {
		$settings = AAC_Member_Portal_Admin::get_settings();
		$registry = $this->get_top_nav_item_registry($portal_url);
		$sections = [];

		foreach ($settings['components']['top_nav_items'] as $item_id => $item_settings) {
			if (empty($item_settings['visible']) || empty($registry[$item_id])) {
				continue;
			}

			$section = $registry[$item_id];
			$section['id'] = $item_id;
			$section['label'] = $item_settings['label'];
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

		foreach ($settings['components']['section_titles'] as $section_id => $section_title) {
			$sections[$section_id] = [
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

			$href = untrailingslashit($portal_url) . '/#' . ltrim($registry[$item_id]['route'], '/');
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
		];
	}

	private function build_top_nav_sections_for_runtime($settings) {
		$registry = $this->get_top_nav_item_registry($this->get_portal_page_url());
		$sections = [];

		foreach ($settings['components']['top_nav_items'] as $item_id => $item_settings) {
			if (empty($item_settings['visible']) || empty($registry[$item_id])) {
				continue;
			}

			$section = $registry[$item_id];
			$sections[] = [
				'id' => $item_id,
				'label' => $item_settings['label'],
				'href' => $section['href'],
				'children' => $section['children'],
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

			$sections[$section_id]['items'][] = [
				'id' => $item_id,
				'label' => $item_settings['label'],
				'to' => $registry[$item_id]['route'],
				'icon' => $registry[$item_id]['icon'],
				'order' => (int) $item_settings['order'],
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

	private function get_top_nav_item_registry($portal_url) {
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
					['label' => 'Join', 'href' => $portal_url . '#membership-form'],
					['label' => 'Renew', 'href' => 'https://membership.americanalpineclub.org/renew', 'external' => true],
				],
			],
			'stories_news' => [
				'label' => 'Stories & News',
				'href' => home_url('/stories/'),
				'children' => [
					['label' => 'Articles & News', 'href' => home_url('/stories/')],
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
			'rescue' => ['icon' => 'shield', 'route' => '/rescue'],
			'account' => ['icon' => 'settings', 'route' => '/account'],
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

$GLOBALS['aac_member_portal_plugin'] = new AAC_Member_Portal_Plugin();
