<?php
/**
 * Admin pages class
 */

if (!defined('ABSPATH')) exit;

class WoW_Raid_Admin_Pages {
    
    /**
     * Plugin manager instance
     */
    private $manager;
    
    /**
     * Constructor
     */
    public function __construct($manager) {
        $this->manager = $manager;
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'WoW Raid Forms',
            'Raid Forms',
            'manage_options',
            'wow-raid-forms',
            array($this, 'admin_page'),
            'dashicons-groups',
            105
        );
        
        add_submenu_page(
            'wow-raid-forms',
            'Manage Raids',
            'Manage Raids',
            'manage_options',
            'wow-manage-raids',
            array($this, 'manage_raids_page')
        );
        
        add_submenu_page(
            'wow-raid-forms',
            'Manage Bookings',
            'Manage Bookings',
            'manage_options',
            'wow-manage-bookings',
            array($this, 'manage_bookings_page')
        );
        
        // Add debug page
        add_submenu_page(
            'wow-raid-forms',
            'Debug Info',
            'Debug Info',
            'manage_options',
            'wow-raid-debug',
            array($this, 'debug_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'wow_raid_form_group',
            'wow_raid_form_settings',
            array($this, 'validate_settings')
        );
    }
    
    /**
     * Validate settings
     */
    public function validate_settings($input) {
        $valid = array();
        
        $default_settings = array(
            'allowed_wp_roles' => array('administrator'),
            'allowed_discord_roles' => '',
            'access_denied_message' => 'You do not have permission to create raids.',
            'success_message' => 'Raid created successfully!',
            'booking_wp_roles' => array('administrator', 'editor'),
            'booking_access_message' => 'You do not have permission to create bookings.'
        );
        
        $valid['allowed_wp_roles'] = isset($input['allowed_wp_roles']) && is_array($input['allowed_wp_roles']) 
            ? array_map('sanitize_text_field', $input['allowed_wp_roles']) 
            : $default_settings['allowed_wp_roles'];
            
        $valid['allowed_discord_roles'] = isset($input['allowed_discord_roles']) 
            ? sanitize_textarea_field($input['allowed_discord_roles'])
            : $default_settings['allowed_discord_roles'];
            
        $valid['access_denied_message'] = isset($input['access_denied_message']) 
            ? sanitize_textarea_field($input['access_denied_message'])
            : $default_settings['access_denied_message'];
            
        $valid['success_message'] = isset($input['success_message']) 
            ? sanitize_textarea_field($input['success_message'])
            : $default_settings['success_message'];
            
        $valid['booking_wp_roles'] = isset($input['booking_wp_roles']) && is_array($input['booking_wp_roles']) 
            ? array_map('sanitize_text_field', $input['booking_wp_roles']) 
            : $default_settings['booking_wp_roles'];
            
        $valid['booking_access_message'] = isset($input['booking_access_message']) 
            ? sanitize_textarea_field($input['booking_access_message'])
            : $default_settings['booking_access_message'];
        
        return $valid;
    }
    
    /**
     * Main admin page
     */
    public function admin_page() {
        $settings = $this->manager->get_helpers()->get_settings();
        
        include WOW_RAID_FORM_PATH . 'templates/admin/settings-page.php';
    }
    
    /**
     * Manage raids page
     */
    public function manage_raids_page() {
        global $wpdb;
        $database = $this->manager->get_database();
        $table_name = $database->get_raids_table();
        
        // Handle delete action
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
            $raid_id = intval($_GET['id']);
            $wpdb->delete($table_name, array('id' => $raid_id), array('%d'));
            echo '<div class="notice notice-success"><p>Raid deleted successfully.</p></div>';
        }
        
        // Handle view action
        if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['id'])) {
            $raid_id = intval($_GET['id']);
            $raid = $database->get_raid($raid_id);
            
            if ($raid) {
                include WOW_RAID_FORM_PATH . 'templates/admin/raid-details.php';
                return;
            } else {
                echo '<div class="notice notice-error"><p>Raid not found.</p></div>';
            }
        }
        
        // Get all raids
        $raids = $wpdb->get_results("SELECT * FROM $table_name ORDER BY raid_date DESC, raid_hour DESC");
        
        include WOW_RAID_FORM_PATH . 'templates/admin/manage-raids.php';
    }
    
    /**
     * Manage bookings page
     */
    public function manage_bookings_page() {
        global $wpdb;
        $database = $this->manager->get_database();
        $bookings_table = $database->get_bookings_table();
        $raids_table = $database->get_raids_table();
        
        $stats = array();
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$bookings_table'")) {
            $stats['total_bookings'] = $wpdb->get_var("SELECT COUNT(*) FROM $bookings_table");
            $stats['pending_bookings'] = $wpdb->get_var("SELECT COUNT(*) FROM $bookings_table WHERE booking_status = 'pending'");
            $stats['confirmed_bookings'] = $wpdb->get_var("SELECT COUNT(*) FROM $bookings_table WHERE booking_status = 'confirmed'");
            $stats['completed_bookings'] = $wpdb->get_var("SELECT COUNT(*) FROM $bookings_table WHERE booking_status = 'completed'");
            $stats['cancelled_bookings'] = $wpdb->get_var("SELECT COUNT(*) FROM $bookings_table WHERE booking_status = 'cancelled'");
            $stats['total_revenue'] = $wpdb->get_var("SELECT SUM(total_price) FROM $bookings_table WHERE booking_status IN ('confirmed', 'completed')");
            
            // Get recent bookings
            $recent_bookings = $wpdb->get_results("
                SELECT b.*, r.raid_name, r.raid_date, u.display_name as advertiser_name
                FROM $bookings_table b
                LEFT JOIN $raids_table r ON b.raid_id = r.id
                LEFT JOIN {$wpdb->users} u ON b.advertiser_id = u.ID
                ORDER BY b.created_at DESC
                LIMIT 10
            ");
        }
        
        include WOW_RAID_FORM_PATH . 'templates/admin/manage-bookings.php';
    }
    
    /**
     * Debug page
     */
    public function debug_page() {
        global $wpdb;
        $database = $this->manager->get_database();
        $raids_table = $database->get_raids_table();
        $bookings_table = $database->get_bookings_table();
        
        // Gather debug information
        $debug_info = array(
            'raids_exists' => $wpdb->get_var("SHOW TABLES LIKE '$raids_table'"),
            'bookings_exists' => $wpdb->get_var("SHOW TABLES LIKE '$bookings_table'"),
            'raid_count' => 0,
            'booking_count' => 0,
            'table_created' => get_option('wow_raid_table_created', 'Never'),
            'plugin_version' => WOW_RAID_FORM_VERSION,
            'raids_table' => $raids_table,
            'bookings_table' => $bookings_table,
            'user_logged_in' => is_user_logged_in(),
            'user_id' => get_current_user_id(),
            'user_roles' => wp_get_current_user()->roles,
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version')
        );
        
        if ($debug_info['raids_exists']) {
            $debug_info['raid_count'] = $wpdb->get_var("SELECT COUNT(*) FROM $raids_table");
        }
        
        if ($debug_info['bookings_exists']) {
            $debug_info['booking_count'] = $wpdb->get_var("SELECT COUNT(*) FROM $bookings_table");
        }
        
        include WOW_RAID_FORM_PATH . 'templates/admin/debug-page.php';
    }
}