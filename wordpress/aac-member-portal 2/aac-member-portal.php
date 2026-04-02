<?php
/**
 * Plugin Name: AAC Member Portal
 * Description: Embeds the AAC React member portal inside WordPress and exposes REST endpoints for member profile data.
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

require_once AAC_MEMBER_PORTAL_DIR . 'includes/class-aac-member-portal-api.php';

final class AAC_Member_Portal_Plugin {
	const SHORTCODE = 'aac_member_portal';
	const SCRIPT_HANDLE = 'aac-member-portal-app';
	const STYLE_HANDLE = 'aac-member-portal-app';
	const MOUNT_ID = 'aac-member-portal-root';

	public function __construct() {
		new AAC_Member_Portal_API();

		add_shortcode(self::SHORTCODE, [$this, 'render_shortcode']);
		add_action('wp_enqueue_scripts', [$this, 'register_assets']);
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

	public function render_shortcode() {
		$asset_files = $this->locate_asset_files();
		if (!$asset_files['script']) {
			return '<div class="aac-member-portal-error">AAC Member Portal assets have not been packaged yet.</div>';
		}

		wp_enqueue_script(self::SCRIPT_HANDLE);
		if ($asset_files['style']) {
			wp_enqueue_style(self::STYLE_HANDLE);
		}

		$config = [
			'mountId' => self::MOUNT_ID,
			'routerMode' => 'hash',
			'apiBase' => untrailingslashit(rest_url('aac/v1')),
			'restNonce' => wp_create_nonce('wp_rest'),
			'isLoggedIn' => is_user_logged_in(),
		];

		wp_add_inline_script(
			self::SCRIPT_HANDLE,
			'window.AAC_MEMBER_PORTAL_CONFIG = ' . wp_json_encode($config) . ';',
			'before'
		);

		return sprintf(
			'<div id="%s" class="aac-member-portal-shell"></div>',
			esc_attr(self::MOUNT_ID)
		);
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

		$script_path = $this->first_glob_match($asset_dir . 'index-*.js');
		$style_path = $this->first_glob_match($asset_dir . 'index-*.css');

		return [
			'script' => $script_path ? $asset_url . basename($script_path) : null,
			'style' => $style_path ? $asset_url . basename($style_path) : null,
		];
	}

	private function first_glob_match($pattern) {
		$matches = glob($pattern);
		if (!$matches) {
			return null;
		}

		sort($matches);
		return $matches[0];
	}
}

new AAC_Member_Portal_Plugin();
