<?php
/**
 * Plugin Name: AAC Salesforce Sync
 * Description: Queue-based Salesforce sync plugin for AAC Member Portal and Paid Memberships Pro.
 * Version: 0.1.0
 * Author: AAC
 */

if (!defined('ABSPATH')) {
	exit;
}

define('AAC_SALESFORCE_SYNC_VERSION', '0.1.0');
define('AAC_SALESFORCE_SYNC_FILE', __FILE__);
define('AAC_SALESFORCE_SYNC_DIR', plugin_dir_path(__FILE__));
define('AAC_SALESFORCE_SYNC_URL', plugin_dir_url(__FILE__));

require_once AAC_SALESFORCE_SYNC_DIR . 'includes/class-aac-salesforce-sync-settings.php';
require_once AAC_SALESFORCE_SYNC_DIR . 'includes/class-aac-salesforce-sync-installer.php';
require_once AAC_SALESFORCE_SYNC_DIR . 'includes/class-aac-salesforce-sync-queue.php';
require_once AAC_SALESFORCE_SYNC_DIR . 'includes/class-aac-salesforce-sync-salesforce-client.php';
require_once AAC_SALESFORCE_SYNC_DIR . 'includes/class-aac-salesforce-sync-worker.php';
require_once AAC_SALESFORCE_SYNC_DIR . 'includes/class-aac-salesforce-sync-rest.php';
require_once AAC_SALESFORCE_SYNC_DIR . 'includes/class-aac-salesforce-sync-admin.php';

final class AAC_Salesforce_Sync_Plugin {
	private static $instance = null;
	private $worker;

	public static function get_instance() {
		if (null === self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		$this->worker = new AAC_Salesforce_Sync_Worker();

		new AAC_Salesforce_Sync_REST($this->worker);
		new AAC_Salesforce_Sync_Admin($this->worker);
	}
}

register_activation_hook(AAC_SALESFORCE_SYNC_FILE, ['AAC_Salesforce_Sync_Installer', 'activate']);
register_deactivation_hook(AAC_SALESFORCE_SYNC_FILE, ['AAC_Salesforce_Sync_Worker', 'deactivate']);

AAC_Salesforce_Sync_Plugin::get_instance();
