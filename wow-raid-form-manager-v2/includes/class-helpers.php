<?php
/**
 * Helper functions class
 */

if (!defined('ABSPATH')) exit;

class WoW_Raid_Helpers {
    
    /**
     * Check if user has access to create raids
     */
    public function user_has_access($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }
        
        $default_settings = array(
            'allowed_wp_roles' => array('administrator'),
            'allowed_discord_roles' => '',
            'access_denied_message' => 'You do not have permission to create raids.',
            'success_message' => 'Raid created successfully!'
        );
        
        $settings = get_option('wow_raid_form_settings', $default_settings);
        $settings = wp_parse_args($settings, $default_settings);
        
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }
        
        // Check WordPress roles
        if (!empty($settings['allowed_wp_roles']) && is_array($settings['allowed_wp_roles'])) {
            $user_roles = $user->roles;
            $allowed_roles = $settings['allowed_wp_roles'];
            
            if (array_intersect($user_roles, $allowed_roles)) {
                return true;
            }
        }
        
        // Check Discord roles
        if (!empty($settings['allowed_discord_roles'])) {
            $discord_roles = trim($settings['allowed_discord_roles']);
            if (!empty($discord_roles)) {
                $allowed_discord_roles = array_map('trim', explode(',', $discord_roles));
                
                if (function_exists('discord_elementor')) {
                    foreach ($allowed_discord_roles as $role_id) {
                        if (discord_elementor()->user_has_discord_role($role_id, $user_id)) {
                            return true;
                        }
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check if user has access to create bookings
     */
    public function user_has_booking_access($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }
        
        $default_settings = array(
            'booking_wp_roles' => array('administrator', 'editor'),
            'booking_access_message' => 'You do not have permission to create bookings.'
        );
        
        $settings = get_option('wow_raid_form_settings', $default_settings);
        $settings = wp_parse_args($settings, $default_settings);
        
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }
        
        if (!empty($settings['booking_wp_roles']) && is_array($settings['booking_wp_roles'])) {
            $user_roles = $user->roles;
            $allowed_roles = $settings['booking_wp_roles'];
            
            if (array_intersect($user_roles, $allowed_roles)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Format raids array for display
     */
    public function format_raids_array($raids, $current_user_id) {
        $formatted_raids = array();
        $now = time();
        $manager = WoW_Raid_Manager::get_instance();
        
        foreach ($raids as $raid) {
            $user = get_user_by('id', $raid->created_by);
            
            // Determine display status based on time and actual status
            $raid_datetime = strtotime($raid->raid_date . ' ' . $raid->raid_hour . ':' . $raid->raid_minute);
            $is_past_time = ($raid_datetime < $now);
            
            if ($raid->status == 'done') {
                $status = 'done';
            } elseif ($raid->status == 'cancelled') {
                $status = 'cancelled';
            } elseif ($raid->status == 'locked') {
                $status = 'locked';
            } elseif ($is_past_time && $raid->status == 'active') {
                $status = 'delayed';
            } else {
                $status = 'upcoming';
            }
            
            $is_past_raid = ($raid->status == 'done' || $raid->status == 'cancelled');
            
            // Get armor queue summary for VIP raids
            $armor_queue_summary = array();
            if ($raid->loot_type === 'VIP') {
                $armor_queue_summary = $this->get_armor_queue_summary($raid->id);
            }
            
            $formatted_raids[] = array(
                'id' => $raid->id,
                'date' => date('M j', strtotime($raid->raid_date)),
                'time' => str_pad($raid->raid_hour, 2, '0', STR_PAD_LEFT) . ':' . str_pad($raid->raid_minute, 2, '0', STR_PAD_LEFT),
                'name' => $raid->raid_name,
                'difficulty' => $raid->difficulty,
                'loot_type' => $raid->loot_type,
                'boss_count' => $raid->boss_count,
                'spots' => $raid->available_spots,
                'leader' => $raid->raid_leader,
                'gold_collector' => $raid->gold_collector,
                'status' => $status,
                'original_status' => $raid->status,
                'is_past_time' => $is_past_time,
                'is_past_raid' => $is_past_raid,
                'is_locked' => ($raid->status == 'locked'),
                'created_by' => $user ? $user->display_name : 'Unknown',
                'can_edit' => ($raid->created_by == $current_user_id || current_user_can('administrator')),
                'armor_queue_summary' => $armor_queue_summary
            );
        }
        
        return $formatted_raids;
    }
    
    /**
     * Get armor queue summary for a raid
     */
    public function get_armor_queue_summary($raid_id) {
        global $wpdb;
        $manager = WoW_Raid_Manager::get_instance();
        $database = $manager->get_database();
        $bookings_table = $database->get_bookings_table();
        
        $summary = array();
        
        foreach ($manager->armor_types as $armor_type) {
            $counts = $wpdb->get_row($wpdb->prepare(
                "SELECT 
                    SUM(CASE WHEN armor_status = 'primary' THEN 1 ELSE 0 END) as primary_count,
                    SUM(CASE WHEN armor_status = 'backup' THEN 1 ELSE 0 END) as backup_count,
                    SUM(CASE WHEN armor_status = 'waitlist' THEN 1 ELSE 0 END) as waitlist_count
                 FROM $bookings_table 
                 WHERE raid_id = %d AND armor_type = %s AND booking_status IN ('pending', 'confirmed')",
                $raid_id, $armor_type
            ));
            
            if ($counts && ($counts->primary_count > 0 || $counts->backup_count > 0 || $counts->waitlist_count > 0)) {
                $summary[$armor_type] = array(
                    'primary' => intval($counts->primary_count),
                    'backup' => intval($counts->backup_count),
                    'waitlist' => intval($counts->waitlist_count),
                    'total' => intval($counts->primary_count) + intval($counts->backup_count) + intval($counts->waitlist_count)
                );
            }
        }
        
        return $summary;
    }
    
    /**
     * Check if user can manage a booking
     */
    public function can_manage_booking($booking) {
        if (current_user_can('manage_options')) {
            return true;
        }
        
        // Check if user is the raid leader
        global $wpdb;
        $manager = WoW_Raid_Manager::get_instance();
        $database = $manager->get_database();
        $raids_table = $database->get_raids_table();
        
        $raid = $wpdb->get_row($wpdb->prepare("SELECT * FROM $raids_table WHERE id = %d", $booking->raid_id));
        
        if ($raid && $raid->created_by == get_current_user_id()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Calculate armor priority and status for VIP raids
     */
    public function calculate_armor_priority($raid_id, $armor_type) {
        global $wpdb;
        $manager = WoW_Raid_Manager::get_instance();
        $database = $manager->get_database();
        $bookings_table = $database->get_bookings_table();
        
        // Get existing bookings for this armor type in this raid
        $existing_bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT armor_status, armor_priority FROM $bookings_table 
             WHERE raid_id = %d AND armor_type = %s AND booking_status IN ('pending', 'confirmed')
             ORDER BY armor_priority ASC",
            $raid_id, $armor_type
        ));
        
        $primary_count = 0;
        $backup_count = 0;
        $waitlist_count = 0;
        
        foreach ($existing_bookings as $booking) {
            switch ($booking->armor_status) {
                case 'primary':
                    $primary_count++;
                    break;
                case 'backup':
                    $backup_count++;
                    break;
                case 'waitlist':
                    $waitlist_count++;
                    break;
            }
        }
        
        // Determine new booking status and priority
        if ($primary_count == 0) {
            return array(
                'status' => 'primary',
                'priority' => 1,
                'message' => 'You are the primary holder for ' . $armor_type . ' armor.'
            );
        } elseif ($backup_count < 2) { // Allow up to 2 backups
            return array(
                'status' => 'backup',
                'priority' => $backup_count + 2, // Priority 2 and 3 for backups
                'message' => 'You are backup #' . ($backup_count + 1) . ' for ' . $armor_type . ' armor.'
            );
        } else {
            return array(
                'status' => 'waitlist',
                'priority' => $waitlist_count + 4, // Priority 4+ for waitlist
                'message' => 'You are #' . ($waitlist_count + 1) . ' on the waitlist for ' . $armor_type . ' armor.'
            );
        }
    }
    
    /**
     * Auto-promote queue when a spot becomes available
     */
    public function auto_promote_queue($raid_id, $armor_type) {
        global $wpdb;
        $manager = WoW_Raid_Manager::get_instance();
        $database = $manager->get_database();
        $bookings_table = $database->get_bookings_table();
        
        // Check if primary slot is empty
        $primary_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $bookings_table 
             WHERE raid_id = %d AND armor_type = %s AND armor_status = 'primary' AND booking_status IN ('pending', 'confirmed')",
            $raid_id, $armor_type
        ));
        
        if ($primary_exists == 0) {
            // Promote first backup to primary
            $first_backup = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $bookings_table 
                 WHERE raid_id = %d AND armor_type = %s AND armor_status = 'backup' AND booking_status IN ('pending', 'confirmed')
                 ORDER BY armor_priority ASC LIMIT 1",
                $raid_id, $armor_type
            ));
            
            if ($first_backup) {
                $wpdb->update(
                    $bookings_table,
                    array('armor_status' => 'primary', 'armor_priority' => 1),
                    array('id' => $first_backup->id)
                );
                
                // Promote first waitlist to backup
                $first_waitlist = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $bookings_table 
                     WHERE raid_id = %d AND armor_type = %s AND armor_status = 'waitlist' AND booking_status IN ('pending', 'confirmed')
                     ORDER BY armor_priority ASC LIMIT 1",
                    $raid_id, $armor_type
                ));
                
                if ($first_waitlist) {
                    // Find next available backup priority
                    $backup_count = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM $bookings_table 
                         WHERE raid_id = %d AND armor_type = %s AND armor_status = 'backup' AND booking_status IN ('pending', 'confirmed')",
                        $raid_id, $armor_type
                    ));
                    
                    $new_priority = $backup_count + 2;
                    $wpdb->update(
                        $bookings_table,
                        array('armor_status' => 'backup', 'armor_priority' => $new_priority),
                        array('id' => $first_waitlist->id)
                    );
                }
                
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get settings
     */
    public function get_settings() {
        $default_settings = array(
            'allowed_wp_roles' => array('administrator'),
            'allowed_discord_roles' => '',
            'access_denied_message' => 'You do not have permission to create raids.',
            'success_message' => 'Raid created successfully!',
            'booking_wp_roles' => array('administrator', 'editor'),
            'booking_access_message' => 'You do not have permission to create bookings.'
        );
        
        $settings = get_option('wow_raid_form_settings', $default_settings);
        return wp_parse_args($settings, $default_settings);
    }
}