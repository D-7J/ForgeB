<?php
/**
 * AJAX handlers for raid operations
 */

if (!defined('ABSPATH')) exit;

class WoW_Raid_Ajax_Raids {
    
    /**
     * Plugin manager instance
     */
    private $manager;
    
    /**
     * Constructor
     */
    public function __construct($manager) {
        $this->manager = $manager;
        $this->register_ajax_handlers();
    }
    
    /**
     * Register AJAX handlers
     */
    private function register_ajax_handlers() {
        // Raid AJAX endpoints
        add_action('wp_ajax_create_wow_raid', array($this, 'ajax_create_raid'));
        add_action('wp_ajax_nopriv_create_wow_raid', array($this, 'ajax_create_raid'));
        add_action('wp_ajax_get_user_raids', array($this, 'ajax_get_user_raids'));
        add_action('wp_ajax_nopriv_get_user_raids', array($this, 'ajax_get_user_raids'));
        add_action('wp_ajax_update_raid_status', array($this, 'ajax_update_raid_status'));
        add_action('wp_ajax_update_raid', array($this, 'ajax_update_raid'));
        add_action('wp_ajax_test_raid_system', array($this, 'ajax_test_raid_system'));
        add_action('wp_ajax_nopriv_test_raid_system', array($this, 'ajax_test_raid_system'));
        
        // Debug AJAX endpoints
        add_action('wp_ajax_wow_raid_debug', array($this, 'ajax_debug_info'));
        add_action('wp_ajax_nopriv_wow_raid_debug', array($this, 'ajax_debug_info'));
    }
    
    /**
     * Create raid AJAX handler
     */
    public function ajax_create_raid() {
        if (!wp_verify_nonce($_POST['wow_raid_nonce'], 'create_wow_raid')) {
            wp_send_json_error('Security check failed.');
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in.');
        }
        
        $helpers = $this->manager->get_helpers();
        
        if (!$helpers->user_has_access()) {
            wp_send_json_error('You do not have permission to create raids.');
        }
        
        $required_fields = array(
            'raid_date' => 'Raid date',
            'raid_hour' => 'Raid hour', 
            'raid_minute' => 'Raid minute',
            'raid_name' => 'Raid name',
            'loot_type' => 'Loot type',
            'difficulty' => 'Difficulty',
            'boss_count' => 'Number of bosses',
            'available_spots' => 'Available spots',
            'raid_leader' => 'Raid leader',
            'gold_collector' => 'Gold collector'
        );
        
        foreach ($required_fields as $field => $label) {
            if ($field === 'raid_minute' || $field === 'raid_hour') {
                if (!isset($_POST[$field]) || $_POST[$field] === '') {
                    wp_send_json_error("$label is required.");
                }
            } else {
                if (empty($_POST[$field])) {
                    wp_send_json_error("$label is required.");
                }
            }
        }
        
        $raid_hour = intval($_POST['raid_hour']);
        $raid_minute = intval($_POST['raid_minute']);
        
        if ($raid_hour < 0 || $raid_hour > 23) {
            wp_send_json_error('Hour must be between 0 and 23.');
        }
        
        if ($raid_minute < 0 || $raid_minute > 59) {
            wp_send_json_error('Minute must be between 0 and 59.');
        }
        
        $database = $this->manager->get_database();
        
        $result = $database->insert_raid($_POST);
        
        if ($result === false) {
            global $wpdb;
            wp_send_json_error('Failed to create raid. Database error: ' . $wpdb->last_error);
        }
        
        $settings = $helpers->get_settings();
        $success_message = isset($settings['success_message']) ? $settings['success_message'] : 'Raid created successfully!';
        
        wp_send_json_success(array(
            'message' => $success_message,
            'raid_id' => $wpdb->insert_id
        ));
    }
    
    /**
     * Get user raids AJAX handler
     */
    public function ajax_get_user_raids() {
        global $wpdb;
        $database = $this->manager->get_database();
        $helpers = $this->manager->get_helpers();
        
        $table_name = $database->get_raids_table();
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $database->check_tables();
            
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                wp_send_json_error('Database table not found. Please deactivate and reactivate the plugin.');
                return;
            }
        }
        
        $current_user_id = get_current_user_id();
        
        if (!$current_user_id) {
            wp_send_json_success(array(
                'raids' => array(),
                'user_id' => 0,
                'is_admin' => false,
                'message' => 'Please login to see your raids.'
            ));
            return;
        }
        
        // Debug Mode
        if (isset($_POST['debug_mode']) && $_POST['debug_mode'] == 'true') {
            $all_raids = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC LIMIT 10");
            wp_send_json_success(array(
                'debug' => true,
                'all_raids_count' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name"),
                'user_raids_count' => $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE created_by = %d", $current_user_id)),
                'recent_raids' => $all_raids,
                'current_user_id' => $current_user_id,
                'table_name' => $table_name
            ));
            return;
        }
        
        // Show ALL raids if user is admin and requested
        if (current_user_can('administrator') && isset($_POST['show_all_raids']) && $_POST['show_all_raids'] == 'true') {
            $all_raids_query = "SELECT * FROM $table_name 
                              WHERE status != 'deleted'
                              ORDER BY raid_date DESC, raid_hour DESC, raid_minute DESC 
                              LIMIT 50";
            $raids = $wpdb->get_results($all_raids_query);
            
            wp_send_json_success(array(
                'raids' => $helpers->format_raids_array($raids, $current_user_id),
                'user_id' => $current_user_id,
                'is_admin' => true,
                'showing_all' => true,
                'total_count' => count($raids)
            ));
            return;
        }
        
        // Check if user has any raids
        $total_user_raids = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE created_by = %d AND status != 'deleted'",
            $current_user_id
        ));
        
        if ($total_user_raids == 0) {
            wp_send_json_success(array(
                'raids' => array(),
                'user_id' => $current_user_id,
                'is_admin' => current_user_can('administrator'),
                'message' => 'You have not created any raids yet. Use the form to create your first raid!'
            ));
            return;
        }
        
        // Get user raids
        $raids = $database->get_user_raids($current_user_id, 20);
        
        // If no raids and admin, show recent raids from all users
        if (empty($raids) && current_user_can('administrator')) {
            $all_raids_query = "SELECT * FROM $table_name 
                              WHERE status != 'deleted'
                              ORDER BY created_at DESC 
                              LIMIT 20";
            $raids = $wpdb->get_results($all_raids_query);
            
            if (empty($raids)) {
                wp_send_json_success(array(
                    'raids' => array(),
                    'user_id' => $current_user_id,
                    'is_admin' => true,
                    'message' => 'No raids found in the system. Create the first raid!'
                ));
                return;
            }
        }
        
        wp_send_json_success(array(
            'raids' => $helpers->format_raids_array($raids, $current_user_id),
            'user_id' => $current_user_id,
            'is_admin' => current_user_can('administrator')
        ));
    }
    
    /**
     * Update raid status AJAX handler
     */
    public function ajax_update_raid_status() {
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in.');
        }
        
        $raid_id = intval($_POST['raid_id']);
        $new_status = sanitize_text_field($_POST['status']);
        
        if (!in_array($new_status, array('active', 'locked', 'done', 'cancelled', 'delayed'))) {
            wp_send_json_error('Invalid status.');
        }
        
        $database = $this->manager->get_database();
        $raid = $database->get_raid($raid_id);
        
        if (!$raid) {
            wp_send_json_error('Raid not found.');
        }
        
        $current_user_id = get_current_user_id();
        
        if ($raid->created_by != $current_user_id && !current_user_can('administrator')) {
            wp_send_json_error('You do not have permission to modify this raid.');
        }
        
        if ($raid->status == 'locked' && $new_status != 'done' && $new_status != 'cancelled' && !current_user_can('administrator')) {
            wp_send_json_error('This raid is locked. Only administrators can unlock it.');
        }
        
        $result = $database->update_raid(
            $raid_id,
            array('status' => $new_status),
            array('%s')
        );
        
        if ($result === false) {
            wp_send_json_error('Failed to update status.');
        }
        
        wp_send_json_success(array(
            'message' => 'Status updated successfully.',
            'new_status' => $new_status
        ));
    }
    
    /**
     * Update raid AJAX handler
     */
    public function ajax_update_raid() {
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in.');
        }
        
        $raid_id = intval($_POST['raid_id']);
        $database = $this->manager->get_database();
        $raid = $database->get_raid($raid_id);
        
        if (!$raid) {
            wp_send_json_error('Raid not found.');
        }
        
        $current_user_id = get_current_user_id();
        
        if ($raid->created_by != $current_user_id && !current_user_can('administrator')) {
            wp_send_json_error('You do not have permission to edit this raid.');
        }
        
        if ($raid->status == 'locked' && !current_user_can('administrator')) {
            wp_send_json_error('This raid is locked. Only administrators can edit it.');
        }
        
        $update_data = array();
        $update_format = array();
        
        if (isset($_POST['raid_date'])) {
            $update_data['raid_date'] = sanitize_text_field($_POST['raid_date']);
            $update_format[] = '%s';
        }
        
        if (isset($_POST['raid_hour'])) {
            $update_data['raid_hour'] = intval($_POST['raid_hour']);
            $update_format[] = '%d';
        }
        
        if (isset($_POST['raid_minute'])) {
            $update_data['raid_minute'] = intval($_POST['raid_minute']);
            $update_format[] = '%d';
        }
        
        if (isset($_POST['available_spots'])) {
            $update_data['available_spots'] = intval($_POST['available_spots']);
            $update_format[] = '%d';
        }
        
        if (isset($_POST['raid_leader'])) {
            $update_data['raid_leader'] = sanitize_text_field($_POST['raid_leader']);
            $update_format[] = '%s';
        }
        
        if (isset($_POST['gold_collector'])) {
            $update_data['gold_collector'] = sanitize_text_field($_POST['gold_collector']);
            $update_format[] = '%s';
        }
        
        if (empty($update_data)) {
            wp_send_json_error('No data to update.');
        }
        
        $result = $database->update_raid($raid_id, $update_data, $update_format);
        
        if ($result === false) {
            wp_send_json_error('Failed to update raid.');
        }
        
        wp_send_json_success(array(
            'message' => 'Raid updated successfully.'
        ));
    }
    
    /**
     * Test raid system AJAX handler
     */
    public function ajax_test_raid_system() {
        global $wpdb;
        $database = $this->manager->get_database();
        
        $raids_table = $database->get_raids_table();
        $bookings_table = $database->get_bookings_table();
        
        $raids_exists = $wpdb->get_var("SHOW TABLES LIKE '$raids_table'");
        $bookings_exists = $wpdb->get_var("SHOW TABLES LIKE '$bookings_table'");
        
        $total_raids = 0;
        $total_bookings = 0;
        if ($raids_exists) {
            $total_raids = $wpdb->get_var("SELECT COUNT(*) FROM $raids_table");
        }
        if ($bookings_exists) {
            $total_bookings = $wpdb->get_var("SELECT COUNT(*) FROM $bookings_table");
        }
        
        $recent_raids = array();
        if ($raids_exists) {
            $recent_raids = $wpdb->get_results("SELECT * FROM $raids_table ORDER BY id DESC LIMIT 5");
        }
        
        wp_send_json_success(array(
            'raids_table_exists' => $raids_exists ? true : false,
            'bookings_table_exists' => $bookings_exists ? true : false,
            'total_raids' => $total_raids,
            'total_bookings' => $total_bookings,
            'recent_raids' => $recent_raids,
            'current_time' => current_time('mysql'),
            'ajax_url' => admin_url('admin-ajax.php')
        ));
    }
    
    /**
     * Debug info AJAX handler
     */
    public function ajax_debug_info() {
        global $wpdb;
        $database = $this->manager->get_database();
        
        // Handle debug actions
        if (isset($_POST['debug_action'])) {
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
            }
            
            switch ($_POST['debug_action']) {
                case 'recreate_table':
                    WoW_Raid_Database::create_tables();
                    wp_send_json_success(array('message' => 'Tables recreated successfully'));
                    break;
                    
                case 'create_test_raid':
                    $raids_table = $database->get_raids_table();
                    $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : date('Y-m-d', strtotime('+1 day'));
                    
                    $result = $wpdb->insert(
                        $raids_table,
                        array(
                            'raid_date' => $date,
                            'raid_hour' => 20,
                            'raid_minute' => 30,
                            'raid_name' => 'Test Raid - Undermine',
                            'loot_type' => 'VIP',
                            'difficulty' => 'Heroic',
                            'boss_count' => 8,
                            'available_spots' => 20,
                            'raid_leader' => 'Test Leader',
                            'gold_collector' => 'Test Collector',
                            'notes' => 'This is a test VIP raid with priority queue support',
                            'created_by' => get_current_user_id(),
                            'created_at' => current_time('mysql'),
                            'status' => 'active'
                        )
                    );
                    
                    if ($result) {
                        wp_send_json_success(array('message' => 'Test VIP raid created successfully', 'id' => $wpdb->insert_id));
                    } else {
                        wp_send_json_error('Failed to create test raid: ' . $wpdb->last_error);
                    }
                    break;
            }
        }
        
        // Return debug information
        $raids_table = $database->get_raids_table();
        $bookings_table = $database->get_bookings_table();
        $raids_exists = $wpdb->get_var("SHOW TABLES LIKE '$raids_table'");
        $bookings_exists = $wpdb->get_var("SHOW TABLES LIKE '$bookings_table'");
        
        $debug_info = array(
            'plugin_version' => WOW_RAID_FORM_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'raids_table_exists' => $raids_exists ? true : false,
            'bookings_table_exists' => $bookings_exists ? true : false,
            'raids_table_name' => $raids_table,
            'bookings_table_name' => $bookings_table,
            'user_logged_in' => is_user_logged_in(),
            'user_id' => get_current_user_id(),
            'user_roles' => wp_get_current_user()->roles,
            'ajax_url' => admin_url('admin-ajax.php'),
            'current_time' => current_time('mysql'),
            'timezone' => get_option('timezone_string'),
            'site_url' => get_site_url(),
            'plugin_url' => WOW_RAID_FORM_URL,
            'last_error' => $wpdb->last_error
        );
        
        if ($raids_exists) {
            $debug_info['raid_count'] = $wpdb->get_var("SELECT COUNT(*) FROM $raids_table");
            $debug_info['latest_raids'] = $wpdb->get_results("SELECT id, raid_name, raid_date, loot_type, created_at FROM $raids_table ORDER BY id DESC LIMIT 5");
        }
        
        if ($bookings_exists) {
            $debug_info['booking_count'] = $wpdb->get_var("SELECT COUNT(*) FROM $bookings_table");
            $debug_info['latest_bookings'] = $wpdb->get_results("SELECT id, buyer_charname, armor_type, armor_status, created_at FROM $bookings_table ORDER BY id DESC LIMIT 5");
        }
        
        wp_send_json_success($debug_info);
    }
}