<?php
/**
 * Plugin Name: Blue Sheep Business Manager
 * Plugin URI: https://www.bhutanbluesheeptours.com/
 * Description: Business management system for Bhutan Blue Sheep Tours & Travels.
 * Version: 1.0.0
 * Requires at least: 6.6
 * Requires PHP: 8.2
 * Author: Bhutan Blue Sheep Tours & Travels
 * Text Domain: blue-sheep-business-manager
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin version.
 */
define('BSBM_VERSION', '1.0.0');

/**
 * Plugin directory path.
 */
define('BSBM_PATH', plugin_dir_path(__FILE__));

/**
 * Plugin directory URL.
 */
define('BSBM_URL', plugin_dir_url(__FILE__));

/**
 * Main plugin class.
 */
final class Blue_Sheep_Business_Manager {

    /**
     * Initialize the plugin.
     */
    public function __construct() {
        add_action('plugins_loaded', array($this, 'initialize'));
    }

    /**
     * Initialize plugin functionality.
     */
    public function initialize() {
        // Business modules will be registered here.
    }
}

/**
 * Start the plugin.
 */
function bsbm_start_plugin() {
    new Blue_Sheep_Business_Manager();
}

bsbm_start_plugin();