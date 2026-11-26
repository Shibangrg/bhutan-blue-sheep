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

define('BSBM_VERSION', '1.0.0');
define('BSBM_PATH', plugin_dir_path(__FILE__));
define('BSBM_URL', plugin_dir_url(__FILE__));

/**
 * Load Composer dependencies.
 */
$composer_autoload = BSBM_PATH . 'vendor/autoload.php';

if (file_exists($composer_autoload)) {
    require_once $composer_autoload;
} else {
    error_log(
        'Blue Sheep Business Manager: Composer autoloader not found.'
    );
}

/**
 * Load and register the plugin autoloader.
 */
require_once BSBM_PATH . 'includes/Core/Autoloader.php';

\BlueSheep\BusinessManager\Core\Autoloader::register();

/**
 * Initialize the plugin.
 */
function bsbm_start_plugin(): void
{
    $plugin = new \BlueSheep\BusinessManager\Core\Plugin();
    $plugin->init();
}

add_action('plugins_loaded', 'bsbm_start_plugin');
