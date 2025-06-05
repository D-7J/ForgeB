<?php
/**
 * Main plugin manager class
 */

if (!defined('ABSPATH')) exit;

class WoW_Raid_Manager {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Plugin components
     */
    private $database;
    private $helpers;
    private $admin_pages;
    private $shortcodes;
    private $ajax_raids;
    private $ajax_bookings;
    private $forms_renderer;
    
    /**
     * Game data
     */
    public $wow_classes = array(
        'Death Knight', 'Demon Hunter', 'Druid', 'Evoker', 'Hunter', 'Mage', 
        'Monk', 'Paladin', 'Priest', 'Rogue', 'Shaman', 'Warlock', 'Warrior'
    );
    
    public $armor_types = array(
        'Cloth', 'Leather', 'Mail', 'Plate'
    );
    
    public $booking_types = array(
        'Full Clear', 'Partial Clear', 'Specific Bosses', 'Last Boss Only'
    );
    
    public $undermine_bosses = array(
        'Zekvir', 'The Coaglamation', 'The Underkeep', 'Sikran', 
        'Rasha\'nan', 'Ovinax', 'Nexus-Princess Ky\'veza', 'The Silken Court'
    );
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_components();
        $this->init_hooks();
    }
    
    /**
     * Initialize plugin components
     */
    private function init_components() {
        $this->database = new WoW_Raid_Database();
        $this->helpers = new WoW_Raid_Helpers();
        $this->admin_pages = new WoW_Raid_Admin_Pages($this);
        $this->shortcodes = new WoW_Raid_Shortcodes($this);
        $this->ajax_raids = new WoW_Raid_Ajax_Raids($this);
        $this->ajax_bookings = new WoW_Raid_Ajax_Bookings($this);
        $this->forms_renderer = new WoW_Raid_Forms_Renderer($this);
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Admin menu
        add_action('admin_menu', array($this->admin_pages, 'add_admin_menu'));
        add_action('admin_init', array($this->admin_pages, 'register_settings'));
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Check database on init
        add_action('init', array($this->database, 'check_tables'));
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        if ($this->should_load_assets()) {
            wp_enqueue_style(
                'wow-raid-frontend',
                WOW_RAID_FORM_URL . 'assets/css/frontend.css',
                array(),
                WOW_RAID_FORM_VERSION
            );
            
            wp_enqueue_script(
                'wow-raid-frontend',
                WOW_RAID_FORM_URL . 'assets/js/frontend.js',
                array('jquery'),
                WOW_RAID_FORM_VERSION,
                true
            );
            
            wp_localize_script('wow-raid-frontend', 'wow_raid_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wow_raid_ajax')
            ));
        }
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'wow-raid') !== false || strpos($hook, 'wow-manage') !== false) {
            wp_enqueue_style(
                'wow-raid-admin',
                WOW_RAID_FORM_URL . 'assets/css/admin.css',
                array(),
                WOW_RAID_FORM_VERSION
            );
        }
    }
    
    /**
     * Check if we should load assets
     */
    private function should_load_assets() {
        global $post;
        
        if (!$post) {
            return false;
        }
        
        $shortcodes = array(
            'wow_raid_form',
            'wow_raid_status',
            'wow_raid_dashboard',
            'wow_booking_form',
            'wow_booking_management'
        );
        
        foreach ($shortcodes as $shortcode) {
            if (has_shortcode($post->post_content, $shortcode)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get component instances
     */
    public function get_database() {
        return $this->database;
    }
    
    public function get_helpers() {
        return $this->helpers;
    }
    
    public function get_admin_pages() {
        return $this->admin_pages;
    }
    
    public function get_shortcodes() {
        return $this->shortcodes;
    }
    
    public function get_ajax_raids() {
        return $this->ajax_raids;
    }
    
    public function get_ajax_bookings() {
        return $this->ajax_bookings;
    }
    
    public function get_forms_renderer() {
        return $this->forms_renderer;
    }
}