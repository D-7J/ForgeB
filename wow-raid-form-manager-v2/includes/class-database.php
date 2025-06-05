<?php
/**
 * Database operations class
 */

if (!defined('ABSPATH')) exit;

class WoW_Raid_Database {
    
    /**
     * Get raids table name
     */
    public function get_raids_table() {
        global $wpdb;
        return $wpdb->prefix . 'wow_raids';
    }
    
    /**
     * Get bookings table name
     */
    public function get_bookings_table() {
        global $wpdb;
        return $wpdb->prefix . 'wow_bookings';
    }
    
    /**
     * Check if tables exist
     */
    public function check_tables() {
        global $wpdb;
        $raids_table = $this->get_raids_table();
        $bookings_table = $this->get_bookings_table();
        
        if($wpdb->get_var("SHOW TABLES LIKE '$raids_table'") != $raids_table || 
           $wpdb->get_var("SHOW TABLES LIKE '$bookings_table'") != $bookings_table) {
            $this->create_tables();
        }
    }
    
    /**
     * Create database tables
     */
    public static function create_tables() {
        $instance = new self();
        $instance->create_database_tables();
    }
    
    /**
     * Create database tables implementation
     */
    private function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Create raids table
        $raids_table = $this->get_raids_table();
        $raids_sql = "CREATE TABLE $raids_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            raid_date date NOT NULL,
            raid_hour tinyint(2) NOT NULL,
            raid_minute tinyint(2) NOT NULL,
            raid_name varchar(255) NOT NULL,
            loot_type varchar(50) NOT NULL,
            difficulty varchar(50) NOT NULL,
            boss_count tinyint(2) NOT NULL,
            available_spots tinyint(3) NOT NULL,
            raid_leader varchar(255) NOT NULL,
            gold_collector varchar(255) NOT NULL,
            notes text,
            created_by bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            status varchar(20) DEFAULT 'active',
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Create bookings table with priority queue support
        $bookings_table = $this->get_bookings_table();
        $bookings_sql = "CREATE TABLE $bookings_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            raid_id mediumint(9) NOT NULL,
            buyer_charname varchar(100) NOT NULL,
            buyer_realm varchar(50) NOT NULL,
            battlenet varchar(100),
            selected_bosses longtext,
            class varchar(50),
            armor_type varchar(50),
            armor_priority tinyint(2) DEFAULT 1,
            armor_status enum('primary', 'backup', 'waitlist') DEFAULT 'primary',
            total_price decimal(10,2),
            deposit decimal(10,2),
            booking_type varchar(50),
            additional_info text,
            advertiser_id bigint(20) NOT NULL,
            booking_status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY raid_id (raid_id),
            KEY advertiser_id (advertiser_id),
            KEY booking_status (booking_status),
            KEY armor_priority_idx (raid_id, armor_type, armor_priority),
            KEY armor_status_idx (armor_status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Create both tables
        dbDelta($raids_sql);
        dbDelta($bookings_sql);
        
        // Log table creation
        update_option('wow_raid_table_created', current_time('mysql'));
        update_option('wow_bookings_table_created', current_time('mysql'));
        
        // Update version to 2.1 for priority queue support
        update_option('wow_raid_form_db_version', '2.1');
        
        // Migrate existing bookings to have proper armor status
        $this->migrate_existing_bookings();
    }
    
    /**
     * Migration function for existing bookings
     */
    private function migrate_existing_bookings() {
        global $wpdb;
        $bookings_table = $this->get_bookings_table();
        
        // Check if migration is needed
        $migrated = get_option('wow_bookings_priority_migrated', false);
        if ($migrated) {
            return;
        }
        
        // Set all existing bookings as primary
        $wpdb->query("UPDATE $bookings_table SET armor_priority = 1, armor_status = 'primary' WHERE armor_priority IS NULL OR armor_priority = 0");
        
        // Mark migration as complete
        update_option('wow_bookings_priority_migrated', true);
    }
    
    /**
     * Get raid by ID
     */
    public function get_raid($raid_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->get_raids_table()} WHERE id = %d",
            $raid_id
        ));
    }
    
    /**
     * Get booking by ID
     */
    public function get_booking($booking_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->get_bookings_table()} WHERE id = %d",
            $booking_id
        ));
    }
    
    /**
     * Insert raid
     */
    public function insert_raid($data) {
        global $wpdb;
        
        return $wpdb->insert(
            $this->get_raids_table(),
            array(
                'raid_date' => sanitize_text_field($data['raid_date']),
                'raid_hour' => intval($data['raid_hour']),
                'raid_minute' => intval($data['raid_minute']),
                'raid_name' => sanitize_text_field($data['raid_name']),
                'loot_type' => sanitize_text_field($data['loot_type']),
                'difficulty' => sanitize_text_field($data['difficulty']),
                'boss_count' => intval($data['boss_count']),
                'available_spots' => intval($data['available_spots']),
                'raid_leader' => sanitize_text_field($data['raid_leader']),
                'gold_collector' => sanitize_text_field($data['gold_collector']),
                'notes' => sanitize_textarea_field($data['notes']),
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%s')
        );
    }
    
    /**
     * Update raid
     */
    public function update_raid($raid_id, $data, $formats = array()) {
        global $wpdb;
        
        return $wpdb->update(
            $this->get_raids_table(),
            $data,
            array('id' => $raid_id),
            $formats,
            array('%d')
        );
    }
    
    /**
     * Insert booking
     */
    public function insert_booking($data) {
        global $wpdb;
        
        // Process selected bosses
        $selected_bosses = array();
        if (!empty($data['selected_bosses']) && is_array($data['selected_bosses'])) {
            $selected_bosses = array_map('sanitize_text_field', $data['selected_bosses']);
        }
        
        return $wpdb->insert(
            $this->get_bookings_table(),
            array(
                'raid_id' => intval($data['raid_id']),
                'buyer_charname' => sanitize_text_field($data['buyer_charname']),
                'buyer_realm' => sanitize_text_field($data['buyer_realm']),
                'battlenet' => sanitize_text_field($data['battlenet']),
                'selected_bosses' => json_encode($selected_bosses),
                'class' => sanitize_text_field($data['class']),
                'armor_type' => sanitize_text_field($data['armor_type']),
                'armor_priority' => intval($data['armor_priority']),
                'armor_status' => sanitize_text_field($data['armor_status']),
                'total_price' => floatval($data['total_price']),
                'deposit' => floatval($data['deposit']),
                'booking_type' => sanitize_text_field($data['booking_type']),
                'additional_info' => sanitize_textarea_field($data['additional_info']),
                'advertiser_id' => get_current_user_id(),
                'booking_status' => 'pending',
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%f', '%f', '%s', '%s', '%d', '%s', '%s')
        );
    }
    
    /**
     * Update booking
     */
    public function update_booking($booking_id, $data, $formats = array()) {
        global $wpdb;
        
        return $wpdb->update(
            $this->get_bookings_table(),
            $data,
            array('id' => $booking_id),
            $formats,
            array('%d')
        );
    }
    
    /**
     * Get raids for user
     */
    public function get_user_raids($user_id, $limit = 20) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->get_raids_table()} 
             WHERE created_by = %d
             AND status != 'deleted'
             ORDER BY raid_date DESC, raid_hour DESC, raid_minute DESC 
             LIMIT %d",
            $user_id,
            $limit
        ));
    }
    
    /**
     * Get available raids for booking
     */
    public function get_available_raids() {
        global $wpdb;
        $today = date('Y-m-d');
        $current_time = date('H:i');
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->get_raids_table()} 
             WHERE status IN ('active', 'locked') 
             AND (raid_date > %s OR (raid_date = %s AND CONCAT(raid_hour, ':', raid_minute) >= %s))
             ORDER BY raid_date ASC, raid_hour ASC, raid_minute ASC",
            $today, $today, $current_time
        ));
    }
}