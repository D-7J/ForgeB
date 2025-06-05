<?php
/**
 * Shortcodes class
 */

if (!defined('ABSPATH')) exit;

class WoW_Raid_Shortcodes {
    
    /**
     * Plugin manager instance
     */
    private $manager;
    
    /**
     * Constructor
     */
    public function __construct($manager) {
        $this->manager = $manager;
        $this->register_shortcodes();
    }
    
    /**
     * Register all shortcodes
     */
    private function register_shortcodes() {
        add_shortcode('wow_raid_form', array($this, 'raid_form_shortcode'));
        add_shortcode('wow_raid_status', array($this, 'raid_status_shortcode'));
        add_shortcode('wow_raid_dashboard', array($this, 'raid_dashboard_shortcode'));
        add_shortcode('wow_booking_form', array($this, 'booking_form_shortcode'));
        add_shortcode('wow_booking_management', array($this, 'booking_management_shortcode'));
    }
    
    /**
     * Raid form shortcode
     */
    public function raid_form_shortcode($atts) {
        $atts = shortcode_atts(array(), $atts);
        
        if (!is_user_logged_in()) {
            return '<div class="raid-form-error">You must be logged in to create raids. <a href="' . wp_login_url(get_permalink()) . '">Login here</a></div>';
        }
        
        $helpers = $this->manager->get_helpers();
        
        if (!$helpers->user_has_access()) {
            $settings = $helpers->get_settings();
            $message = !empty($settings['access_denied_message']) 
                ? $settings['access_denied_message'] 
                : 'You do not have permission to create raids.';
            
            return '<div class="raid-form-error">' . esc_html($message) . '</div>';
        }
        
        ob_start();
        $this->manager->get_forms_renderer()->render_raid_form();
        return ob_get_clean();
    }
    
    /**
     * Raid status shortcode
     */
    public function raid_status_shortcode($atts) {
        global $wpdb;
        $database = $this->manager->get_database();
        $raids_table = $database->get_raids_table();
        $bookings_table = $database->get_bookings_table();
        
        ob_start();
        ?>
        <div style="background: #333; color: #fff; padding: 20px; border-radius: 8px; font-family: monospace;">
            <h3 style="color: #ff6b35;">WoW Raid System Status</h3>
            <p><strong>User ID:</strong> <?php echo get_current_user_id(); ?></p>
            <p><strong>Logged In:</strong> <?php echo is_user_logged_in() ? 'Yes' : 'No'; ?></p>
            <p><strong>Raids Table:</strong> <?php echo $wpdb->get_var("SHOW TABLES LIKE '$raids_table'") ? 'Yes' : 'No'; ?></p>
            <p><strong>Bookings Table:</strong> <?php echo $wpdb->get_var("SHOW TABLES LIKE '$bookings_table'") ? 'Yes' : 'No'; ?></p>
            <p><strong>Total Raids:</strong> <?php echo intval($wpdb->get_var("SELECT COUNT(*) FROM $raids_table")); ?></p>
            <p><strong>Total Bookings:</strong> <?php echo intval($wpdb->get_var("SELECT COUNT(*) FROM $bookings_table")); ?></p>
            <p><strong>Your Raids:</strong> <?php echo intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $raids_table WHERE created_by = %d", get_current_user_id()))); ?></p>
            <p><strong>Your Bookings:</strong> <?php echo intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $bookings_table WHERE advertiser_id = %d", get_current_user_id()))); ?></p>
            <p><strong>AJAX URL:</strong> <?php echo admin_url('admin-ajax.php'); ?></p>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Raid dashboard shortcode
     */
    public function raid_dashboard_shortcode($atts) {
        $atts = shortcode_atts(array(
            'show_form' => 'true',
            'show_raids' => 'true',
            'auto_refresh' => 'true'
        ), $atts);
        
        if (!is_user_logged_in()) {
            return '<div class="raid-form-error">You must be logged in to view raids. <a href="' . wp_login_url(get_permalink()) . '">Login here</a></div>';
        }
        
        $helpers = $this->manager->get_helpers();
        
        ob_start();
        ?>
        <div class="raid-dashboard-container">
            <?php if ($atts['show_form'] === 'true' && $helpers->user_has_access()): ?>
            <div class="raid-form-section">
                <?php $this->manager->get_forms_renderer()->render_raid_form(); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($atts['show_raids'] === 'true'): ?>
            <div class="raid-list-section">
                <div class="raids-header">
                    <h3>Your Raids <span id="raid-count-display"></span></h3>
                    <div class="raids-controls">
                        <button id="refresh-raids-btn" onclick="refreshRaidsList()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                        <button id="toggle-past-btn" onclick="togglePastRaidsList()">
                            <i class="fas fa-eye"></i> Show Past
                        </button>
                    </div>
                </div>
                <div id="raids-list-container">
                    <p>Loading raids...</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Booking form shortcode
     */
    public function booking_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'raid_id' => '',
            'show_raid_list' => 'true'
        ), $atts);
        
        if (!is_user_logged_in()) {
            return '<div class="booking-form-error">You must be logged in to create bookings. <a href="' . wp_login_url(get_permalink()) . '">Login here</a></div>';
        }
        
        $helpers = $this->manager->get_helpers();
        
        if (!$helpers->user_has_booking_access()) {
            $settings = $helpers->get_settings();
            $message = !empty($settings['booking_access_message']) 
                ? $settings['booking_access_message'] 
                : 'You do not have permission to create bookings.';
            
            return '<div class="booking-form-error">' . esc_html($message) . '</div>';
        }
        
        ob_start();
        $this->manager->get_forms_renderer()->render_booking_form($atts['raid_id'], $atts['show_raid_list'] === 'true');
        return ob_get_clean();
    }
    
    /**
     * Booking management shortcode
     */
    public function booking_management_shortcode($atts) {
        $atts = shortcode_atts(array(
            'raid_id' => '',
            'show_management' => 'true'
        ), $atts);
        
        if (!is_user_logged_in()) {
            return '<div class="booking-form-error">You must be logged in to manage bookings. <a href="' . wp_login_url(get_permalink()) . '">Login here</a></div>';
        }
        
        $helpers = $this->manager->get_helpers();
        
        if (!$helpers->user_has_booking_access() && !current_user_can('manage_options')) {
            return '<div class="booking-form-error">You do not have permission to manage bookings.</div>';
        }
        
        ob_start();
        $this->manager->get_forms_renderer()->render_raid_bookings($atts['raid_id'], $atts['show_management'] === 'true');
        return ob_get_clean();
    }
}