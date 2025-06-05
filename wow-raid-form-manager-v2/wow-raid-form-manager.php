<?php
/**
 * Plugin Name: WoW Raid Form Manager
 * Plugin URI: https://forge-boost.com/
 * Description: Manage WoW raid creation forms with Discord role permissions and advanced booking system
 * Version: 2.2.0
 * Author: d4wood
 * Text Domain: wow-raid-form
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WOW_RAID_FORM_VERSION', '2.2.0');
define('WOW_RAID_FORM_PATH', plugin_dir_path(__FILE__));
define('WOW_RAID_FORM_URL', plugin_dir_url(__FILE__));
define('WOW_RAID_FORM_BASENAME', plugin_basename(__FILE__));

// Check PHP version
if (version_compare(PHP_VERSION, '5.6', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>WoW Raid Form Manager requires PHP 5.6 or higher.</p></div>';
    });
    return;
}

// Autoloader for plugin classes
spl_autoload_register(function ($class) {
    $prefix = 'WoW_Raid_';
    $base_dir = WOW_RAID_FORM_PATH . 'includes/';
    
    // Check if the class uses our prefix
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    // Get the relative class name
    $relative_class = substr($class, $len);
    
    // Replace underscores with hyphens and convert to lowercase
    $file = 'class-' . str_replace('_', '-', strtolower($relative_class)) . '.php';
    
    // Require the file
    if (file_exists($base_dir . $file)) {
        require $base_dir . $file;
    }
});

// Include required files
require_once WOW_RAID_FORM_PATH . 'includes/class-database.php';
require_once WOW_RAID_FORM_PATH . 'includes/class-helpers.php';
require_once WOW_RAID_FORM_PATH . 'includes/class-admin-pages.php';
require_once WOW_RAID_FORM_PATH . 'includes/class-shortcodes.php';
require_once WOW_RAID_FORM_PATH . 'includes/class-ajax-raids.php';
require_once WOW_RAID_FORM_PATH . 'includes/class-ajax-bookings.php';
require_once WOW_RAID_FORM_PATH . 'includes/class-forms-renderer.php';
require_once WOW_RAID_FORM_PATH . 'includes/class-wow-raid-manager.php';

// Activation hook
register_activation_hook(__FILE__, array('WoW_Raid_Database', 'create_tables'));

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Cleanup if needed
});

// Initialize the plugin
add_action('plugins_loaded', function() {
    WoW_Raid_Manager::get_instance();
});