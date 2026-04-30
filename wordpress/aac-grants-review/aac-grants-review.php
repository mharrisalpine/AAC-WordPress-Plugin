<?php
/**
 * Plugin Name: AAC Grants Review
 * Description: Route AAC grant submissions from the custom portal form builder into a reviewer-friendly approval workflow.
 * Version: 0.3.0
 * Author: AAC
 */

if (!defined('ABSPATH')) {
	exit;
}

define('AAC_GRANTS_REVIEW_VERSION', '0.3.0');
define('AAC_GRANTS_REVIEW_FILE', __FILE__);
define('AAC_GRANTS_REVIEW_DIR', plugin_dir_path(__FILE__));
define('AAC_GRANTS_REVIEW_URL', plugin_dir_url(__FILE__));

require_once AAC_GRANTS_REVIEW_DIR . 'includes/class-aac-grants-review-installer.php';
require_once AAC_GRANTS_REVIEW_DIR . 'includes/class-aac-grants-review-settings.php';
require_once AAC_GRANTS_REVIEW_DIR . 'includes/class-aac-grants-review-repository.php';
require_once AAC_GRANTS_REVIEW_DIR . 'includes/class-aac-grants-review-admin.php';
require_once AAC_GRANTS_REVIEW_DIR . 'includes/class-aac-grants-review-frontend.php';

final class AAC_Grants_Review_Plugin {
	private static $instance = null;
	private $repository;
	private $settings;
	private $admin;
	private $frontend;

	public static function get_instance() {
		if (null === self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		$this->settings = new AAC_Grants_Review_Settings();
		$this->repository = new AAC_Grants_Review_Repository($this->settings);
		$this->admin = new AAC_Grants_Review_Admin($this->repository, $this->settings);
		$this->frontend = new AAC_Grants_Review_Frontend($this->repository, $this->settings);

		add_action('plugins_loaded', ['AAC_Grants_Review_Installer', 'maybe_install_schema']);
	}
}

register_activation_hook(AAC_GRANTS_REVIEW_FILE, ['AAC_Grants_Review_Installer', 'activate']);

AAC_Grants_Review_Plugin::get_instance();
