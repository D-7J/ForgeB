<?php
/**
 * AJAX handlers for booking operations
 */

if (!defined('ABSPATH')) exit;

class WoW_Raid_Ajax_Bookings {
    
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
        // Booking AJAX endpoints
        add_action('wp_ajax_create_wow_booking', array($this, 'ajax_create_booking'));
        add_action('wp_ajax_nopriv_create_wow_booking', array($this, 'ajax_create_booking'));
        add_action('wp_ajax_get_available_raids', array($this, 'ajax_get_available_raids'));
        add_action('wp_ajax_nopriv_get_available_raids', array($this, 'ajax_get_available_raids'));
        add_action('wp_ajax_get_raid_bookings', array($this, 'ajax_get_raid_bookings'));
        add_action('wp_ajax_update_booking_status', array($this, 'ajax_update_booking_status'));
        add_action('wp_ajax_get_armor_queue', array($this, 'ajax_get_armor_queue'));
        add_action('wp_ajax_promote_booking', array($this, 'ajax_promote_booking'));
        add_action('wp_ajax_transfer_waitlist', array($this, 'ajax_transfer_waitlist'));
        add_action('wp_ajax_auto_promote_queue', array($this, 'ajax_auto_promote_queue'));
    }
    
    /**
     * Get available raids AJAX handler
     */
    public function ajax_get_available_raids() {
        $database = $this->manager->get_database();
        $raids = $database->get_available_raids();
        
        if (empty($raids)) {
            wp_send_json_success(array(
                'raids' => array(),
                'message' => 'No available raids found for booking.'
            ));
            return;
        }
        
        $formatted_raids = array();
        foreach ($raids as $raid) {
            $user = get_user_by('id', $raid->created_by);
            
            $formatted_raids[] = array(
                'id' => $raid->id,
                'name' => $raid->raid_name,
                'date' => date('M j, Y', strtotime($raid->raid_date)),
                'time' => str_pad($raid->raid_hour, 2, '0', STR_PAD_LEFT) . ':' . str_pad($raid->raid_minute, 2, '0', STR_PAD_LEFT),
                'difficulty' => $raid->difficulty,
                'loot_type' => $raid->loot_type,
                'boss_count' => $raid->boss_count,
                'spots' => $raid->available_spots,
                'leader' => $raid->raid_leader,
                'gold_collector' => $raid->gold_collector,
                'status' => $raid->status,
                'created_by' => $user ? $user->display_name : 'Unknown'
            );
        }
        
        wp_send_json_success(array(
            'raids' => $formatted_raids
        ));
    }
    
    /**
     * Create booking AJAX handler
     */
    public function ajax_create_booking() {
        if (!wp_verify_nonce($_POST['booking_nonce'], 'create_booking')) {
            wp_send_json_error('Security check failed.');
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in.');
        }
        
        $helpers = $this->manager->get_helpers();
        
        if (!$helpers->user_has_booking_access()) {
            wp_send_json_error('You do not have permission to create bookings.');
        }
        
        // Validate required fields
        $required_fields = array(
            'raid_id' => 'Raid selection',
            'buyer_charname' => 'Character name',
            'buyer_realm' => 'Realm',
            'total_price' => 'Total price'
        );
        
        foreach ($required_fields as $field => $label) {
            if (empty($_POST[$field])) {
                wp_send_json_error("$label is required.");
            }
        }
        
        $raid_id = intval($_POST['raid_id']);
        $armor_type = sanitize_text_field($_POST['armor_type']);
        
        $database = $this->manager->get_database();
        $raid = $database->get_raid($raid_id);
        
        if (!$raid) {
            wp_send_json_error('Raid not found.');
        }
        
        if ($raid->status !== 'active' && $raid->status !== 'locked') {
            wp_send_json_error('This raid is not available for booking.');
        }
        
        // Check total available spots (regardless of armor type)
        global $wpdb;
        $bookings_table = $database->get_bookings_table();
        
        $total_booked = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $bookings_table WHERE raid_id = %d AND booking_status IN ('pending', 'confirmed') AND armor_status = 'primary'", 
            $raid_id
        ));
        
        if ($total_booked >= $raid->available_spots && $raid->loot_type !== 'VIP') {
            wp_send_json_error('No available spots left for this raid.');
        }
        
        // Determine armor status and priority for VIP raids
        $armor_status = 'primary';
        $armor_priority = 1;
        $queue_position = '';
        
        if ($raid->loot_type === 'VIP' && !empty($armor_type)) {
            $result = $helpers->calculate_armor_priority($raid_id, $armor_type);
            $armor_status = $result['status'];
            $armor_priority = $result['priority'];
            $queue_position = $result['message'];
        }
        
        // Prepare booking data
        $booking_data = $_POST;
        $booking_data['armor_status'] = $armor_status;
        $booking_data['armor_priority'] = $armor_priority;
        
        // Insert booking
        $result = $database->insert_booking($booking_data);
        
        if ($result === false) {
            wp_send_json_error('Failed to create booking: ' . $wpdb->last_error);
        }
        
        // Prepare success message
        $message = 'Booking created successfully!';
        if (!empty($queue_position)) {
            $message .= ' ' . $queue_position;
        }
        
        wp_send_json_success(array(
            'message' => $message,
            'booking_id' => $wpdb->insert_id,
            'armor_status' => $armor_status,
            'armor_priority' => $armor_priority,
            'queue_info' => $queue_position
        ));
    }
    
    /**
     * Get raid bookings AJAX handler
     */
    public function ajax_get_raid_bookings() {
        $helpers = $this->manager->get_helpers();
        
        if (!current_user_can('manage_options') && !$helpers->user_has_access()) {
            wp_send_json_error('Insufficient permissions.');
        }
        
        $raid_id = intval($_POST['raid_id']);
        
        global $wpdb;
        $database = $this->manager->get_database();
        $bookings_table = $database->get_bookings_table();
        
        // Get raid info
        $raid = $database->get_raid($raid_id);
        
        if (!$raid) {
            wp_send_json_error('Raid not found.');
        }
        
        // Get bookings with priority information
        $bookings = $wpdb->get_results($wpdb->prepare("
            SELECT b.*, u.display_name as advertiser_name
            FROM $bookings_table b
            LEFT JOIN {$wpdb->users} u ON b.advertiser_id = u.ID
            WHERE b.raid_id = %d
            ORDER BY b.armor_type ASC, b.armor_priority ASC, b.created_at ASC
        ", $raid_id));
        
        $formatted_bookings = array();
        foreach ($bookings as $booking) {
            $selected_bosses = json_decode($booking->selected_bosses, true);
            
            $formatted_bookings[] = array(
                'id' => $booking->id,
                'buyer_charname' => $booking->buyer_charname,
                'buyer_realm' => $booking->buyer_realm,
                'battlenet' => $booking->battlenet,
                'selected_bosses' => $selected_bosses,
                'class' => $booking->class,
                'armor_type' => $booking->armor_type,
                'armor_status' => $booking->armor_status,
                'armor_priority' => $booking->armor_priority,
                'total_price' => $booking->total_price,
                'deposit' => $booking->deposit,
                'booking_type' => $booking->booking_type,
                'additional_info' => $booking->additional_info,
                'status' => $booking->booking_status,
                'advertiser_name' => $booking->advertiser_name,
                'created_at' => date('M j, Y H:i', strtotime($booking->created_at))
            );
        }
        
        // Get armor queue summary
        $armor_summary = array();
        if ($raid->loot_type === 'VIP') {
            $armor_summary = $helpers->get_armor_queue_summary($raid_id);
        }
        
        wp_send_json_success(array(
            'bookings' => $formatted_bookings,
            'raid_info' => array(
                'name' => $raid->raid_name,
                'loot_type' => $raid->loot_type,
                'date' => $raid->raid_date,
                'time' => str_pad($raid->raid_hour, 2, '0', STR_PAD_LEFT) . ':' . str_pad($raid->raid_minute, 2, '0', STR_PAD_LEFT)
            ),
            'armor_summary' => $armor_summary
        ));
    }
    
    /**
     * Get armor queue AJAX handler
     */
    public function ajax_get_armor_queue() {
        $raid_id = intval($_POST['raid_id']);
        $armor_type = sanitize_text_field($_POST['armor_type']);
        
        global $wpdb;
        $database = $this->manager->get_database();
        $bookings_table = $database->get_bookings_table();
        
        $queue = $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, u.display_name as advertiser_name 
             FROM $bookings_table b
             LEFT JOIN {$wpdb->users} u ON b.advertiser_id = u.ID
             WHERE b.raid_id = %d AND b.armor_type = %s AND b.booking_status IN ('pending', 'confirmed')
             ORDER BY b.armor_priority ASC",
            $raid_id, $armor_type
        ));
        
        $formatted_queue = array();
        foreach ($queue as $booking) {
            $formatted_queue[] = array(
                'id' => $booking->id,
                'character' => $booking->buyer_charname,
                'realm' => $booking->buyer_realm,
                'status' => $booking->armor_status,
                'priority' => $booking->armor_priority,
                'advertiser' => $booking->advertiser_name,
                'created_at' => date('M j, H:i', strtotime($booking->created_at))
            );
        }
        
        wp_send_json_success(array('queue' => $formatted_queue));
    }
    
    /**
     * Promote/demote booking AJAX handler
     */
    public function ajax_promote_booking() {
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in.');
        }
        
        $booking_id = intval($_POST['booking_id']);
        $action_type = sanitize_text_field($_POST['action_type']); // 'promote' or 'demote'
        
        $database = $this->manager->get_database();
        $helpers = $this->manager->get_helpers();
        
        $booking = $database->get_booking($booking_id);
        
        if (!$booking) {
            wp_send_json_error('Booking not found.');
        }
        
        // Check permissions
        if (!$helpers->can_manage_booking($booking)) {
            wp_send_json_error('You do not have permission to manage this booking.');
        }
        
        if ($action_type === 'promote') {
            $result = $this->promote_booking($booking);
        } else {
            $result = $this->demote_booking($booking);
        }
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Promote booking helper
     */
    private function promote_booking($booking) {
        global $wpdb;
        $database = $this->manager->get_database();
        $helpers = $this->manager->get_helpers();
        $bookings_table = $database->get_bookings_table();
        
        if ($booking->armor_status === 'backup') {
            // Check if primary slot is available
            $primary_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $bookings_table 
                 WHERE raid_id = %d AND armor_type = %s AND armor_status = 'primary' AND booking_status IN ('pending', 'confirmed')",
                $booking->raid_id, $booking->armor_type
            ));
            
            if ($primary_exists == 0) {
                // Promote to primary
                $wpdb->update(
                    $bookings_table,
                    array('armor_status' => 'primary', 'armor_priority' => 1),
                    array('id' => $booking->id)
                );
                
                return array('success' => true, 'message' => 'Booking promoted to primary status.');
            } else {
                return array('success' => false, 'message' => 'Primary slot is already occupied.');
            }
        } elseif ($booking->armor_status === 'waitlist') {
            // Check if backup slot is available
            $backup_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $bookings_table 
                 WHERE raid_id = %d AND armor_type = %s AND armor_status = 'backup' AND booking_status IN ('pending', 'confirmed')",
                $booking->raid_id, $booking->armor_type
            ));
            
            if ($backup_count < 2) {
                // Promote to backup
                $new_priority = $backup_count + 2;
                $wpdb->update(
                    $bookings_table,
                    array('armor_status' => 'backup', 'armor_priority' => $new_priority),
                    array('id' => $booking->id)
                );
                
                return array('success' => true, 'message' => 'Booking promoted to backup status.');
            } else {
                return array('success' => false, 'message' => 'Backup slots are full.');
            }
        }
        
        return array('success' => false, 'message' => 'Cannot promote this booking.');
    }
    
    /**
     * Demote booking helper
     */
    private function demote_booking($booking) {
        global $wpdb;
        $database = $this->manager->get_database();
        $helpers = $this->manager->get_helpers();
        $bookings_table = $database->get_bookings_table();
        
        if ($booking->armor_status === 'primary') {
            // Demote to backup (if backup slots available)
            $backup_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $bookings_table 
                 WHERE raid_id = %d AND armor_type = %s AND armor_status = 'backup' AND booking_status IN ('pending', 'confirmed')",
                $booking->raid_id, $booking->armor_type
            ));
            
            if ($backup_count < 2) {
                $new_priority = $backup_count + 2;
                $wpdb->update(
                    $bookings_table,
                    array('armor_status' => 'backup', 'armor_priority' => $new_priority),
                    array('id' => $booking->id)
                );
                
                // Auto-promote the first backup to primary
                $helpers->auto_promote_queue($booking->raid_id, $booking->armor_type);
                
                return array('success' => true, 'message' => 'Booking demoted to backup status.');
            } else {
                // Demote to waitlist
                $waitlist_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $bookings_table 
                     WHERE raid_id = %d AND armor_type = %s AND armor_status = 'waitlist' AND booking_status IN ('pending', 'confirmed')",
                    $booking->raid_id, $booking->armor_type
                ));
                
                $new_priority = $waitlist_count + 4;
                $wpdb->update(
                    $bookings_table,
                    array('armor_status' => 'waitlist', 'armor_priority' => $new_priority),
                    array('id' => $booking->id)
                );
                
                // Auto-promote the first backup to primary
                $helpers->auto_promote_queue($booking->raid_id, $booking->armor_type);
                
                return array('success' => true, 'message' => 'Booking demoted to waitlist.');
            }
        } elseif ($booking->armor_status === 'backup') {
            // Demote to waitlist
            $waitlist_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $bookings_table 
                 WHERE raid_id = %d AND armor_type = %s AND armor_status = 'waitlist' AND booking_status IN ('pending', 'confirmed')",
                $booking->raid_id, $booking->armor_type
            ));
            
            $new_priority = $waitlist_count + 4;
            $wpdb->update(
                $bookings_table,
                array('armor_status' => 'waitlist', 'armor_priority' => $new_priority),
                array('id' => $booking->id)
            );
            
            return array('success' => true, 'message' => 'Booking demoted to waitlist.');
        }
        
        return array('success' => false, 'message' => 'Cannot demote this booking further.');
    }
    
    /**
     * Update booking status AJAX handler
     */
    public function ajax_update_booking_status() {
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in.');
        }
        
        $booking_id = intval($_POST['booking_id']);
        $new_status = sanitize_text_field($_POST['status']);
        
        if (!in_array($new_status, array('pending', 'confirmed', 'completed', 'cancelled'))) {
            wp_send_json_error('Invalid status.');
        }
        
        $database = $this->manager->get_database();
        $helpers = $this->manager->get_helpers();
        
        $booking = $database->get_booking($booking_id);
        
        if (!$booking) {
            wp_send_json_error('Booking not found.');
        }
        
        // Check permissions
        $current_user_id = get_current_user_id();
        $can_edit = ($booking->advertiser_id == $current_user_id) || current_user_can('manage_options');
        
        // Raid leaders can also manage bookings for their raids
        if (!$can_edit) {
            $raid = $database->get_raid($booking->raid_id);
            if ($raid && $raid->created_by == $current_user_id) {
                $can_edit = true;
            }
        }
        
        if (!$can_edit) {
            wp_send_json_error('You do not have permission to modify this booking.');
        }
        
        $result = $database->update_booking(
            $booking_id,
            array(
                'booking_status' => $new_status,
                'updated_at' => current_time('mysql')
            ),
            array('%s', '%s')
        );
        
        if ($result === false) {
            wp_send_json_error('Failed to update booking status.');
        }
        
        // Auto-promote queue if a VIP booking is cancelled
        if ($new_status === 'cancelled' && !empty($booking->armor_type)) {
            $raid = $database->get_raid($booking->raid_id);
            
            if ($raid && $raid->loot_type === 'VIP') {
                $helpers->auto_promote_queue($booking->raid_id, $booking->armor_type);
            }
        }
        
        wp_send_json_success(array(
            'message' => 'Booking status updated successfully.',
            'new_status' => $new_status
        ));
    }
    
    /**
     * Transfer waitlist AJAX handler
     */
    public function ajax_transfer_waitlist() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
        }
        
        $from_raid_id = intval($_POST['from_raid_id']);
        $to_raid_id = intval($_POST['to_raid_id']);
        $armor_type = sanitize_text_field($_POST['armor_type']);
        
        global $wpdb;
        $database = $this->manager->get_database();
        $helpers = $this->manager->get_helpers();
        $bookings_table = $database->get_bookings_table();
        
        // Get waitlist bookings from source raid
        $waitlist_bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $bookings_table 
             WHERE raid_id = %d AND armor_type = %s AND armor_status = 'waitlist' AND booking_status = 'pending'
             ORDER BY armor_priority ASC",
            $from_raid_id, $armor_type
        ));
        
        if (empty($waitlist_bookings)) {
            wp_send_json_error('No waitlist bookings found to transfer.');
        }
        
        $transferred = 0;
        foreach ($waitlist_bookings as $booking) {
            // Calculate new priority in target raid
            $priority_result = $helpers->calculate_armor_priority($to_raid_id, $armor_type);
            
            // Update booking to new raid
            $result = $wpdb->update(
                $bookings_table,
                array(
                    'raid_id' => $to_raid_id,
                    'armor_status' => $priority_result['status'],
                    'armor_priority' => $priority_result['priority'],
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $booking->id)
            );
            
            if ($result !== false) {
                $transferred++;
            }
        }
        
        wp_send_json_success(array(
            'message' => "Successfully transferred $transferred bookings to the new raid.",
            'transferred_count' => $transferred
        ));
    }
    
    /**
     * Auto promote queue AJAX handler
     */
    public function ajax_auto_promote_queue() {
        $helpers = $this->manager->get_helpers();
        
        if (!current_user_can('manage_options') && !$helpers->user_has_access()) {
            wp_send_json_error('Insufficient permissions.');
        }
        
        $raid_id = intval($_POST['raid_id']);
        
        $database = $this->manager->get_database();
        $raid = $database->get_raid($raid_id);
        
        if (!$raid || $raid->loot_type !== 'VIP') {
            wp_send_json_error('This raid does not support armor queues.');
        }
        
        $promoted = 0;
        foreach ($this->manager->armor_types as $armor_type) {
            if ($helpers->auto_promote_queue($raid_id, $armor_type)) {
                $promoted++;
            }
        }
        
        wp_send_json_success(array(
            'message' => "Auto-promoted queues for $promoted armor types.",
            'promoted_count' => $promoted
        ));
    }
}