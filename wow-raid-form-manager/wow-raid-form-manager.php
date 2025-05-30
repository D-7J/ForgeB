<?php
/**
 * Plugin Name: WoW Raid Form Manager
 * Plugin URI: https://yourwebsite.com/
 * Description: Manage WoW raid creation forms with Discord role permissions
 * Version: 2.2.0
 * Author: ForgeBoost
 * Text Domain: wow-raid-form
 */

if (!defined('ABSPATH')) exit;

define('WOW_RAID_FORM_VERSION', '2.2.0');
define('WOW_RAID_FORM_PATH', plugin_dir_path(__FILE__));
define('WOW_RAID_FORM_URL', plugin_dir_url(__FILE__));

class WoW_Raid_Form_Manager {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_shortcode('wow_raid_form', array($this, 'raid_form_shortcode'));
        add_shortcode('wow_raid_status', array($this, 'raid_status_shortcode'));
        add_shortcode('wow_raid_dashboard', array($this, 'raid_dashboard_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
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
        
        register_activation_hook(__FILE__, array($this, 'create_database_table'));
        
        // Check table on init
        add_action('init', array($this, 'check_database_table'));
    }
    
    public function check_database_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wow_raids';
        
        if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $this->create_database_table();
        }
    }
    
    public function create_database_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wow_raids';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
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
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Log table creation
        update_option('wow_raid_table_created', current_time('mysql'));
    }
    
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
    
    public function debug_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wow_raids';
        ?>
        <div class="wrap">
            <h1>WoW Raid System Debug Information</h1>
            
            <div class="card">
                <h2>System Status</h2>
                <?php
                $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
                $raid_count = $table_exists ? $wpdb->get_var("SELECT COUNT(*) FROM $table_name") : 0;
                $table_created = get_option('wow_raid_table_created', 'Never');
                ?>
                <table class="widefat">
                    <tr>
                        <th>Check</th>
                        <th>Status</th>
                        <th>Details</th>
                    </tr>
                    <tr>
                        <td>Plugin Version</td>
                        <td><?php echo WOW_RAID_FORM_VERSION; ?></td>
                        <td>-</td>
                    </tr>
                    <tr>
                        <td>Database Table</td>
                        <td><?php echo $table_exists ? '<span style="color:green;">✓ Exists</span>' : '<span style="color:red;">✗ Missing</span>'; ?></td>
                        <td>Table name: <?php echo $table_name; ?><br>Created: <?php echo $table_created; ?></td>
                    </tr>
                    <tr>
                        <td>Total Raids</td>
                        <td><?php echo $raid_count; ?></td>
                        <td>-</td>
                    </tr>
                    <tr>
                        <td>Current User</td>
                        <td><?php echo is_user_logged_in() ? 'Logged In' : 'Not Logged In'; ?></td>
                        <td>ID: <?php echo get_current_user_id(); ?><br>
                            Roles: <?php 
                            $user = wp_get_current_user();
                            echo implode(', ', $user->roles);
                            ?></td>
                    </tr>
                    <tr>
                        <td>AJAX URL</td>
                        <td><code><?php echo admin_url('admin-ajax.php'); ?></code></td>
                        <td>-</td>
                    </tr>
                    <tr>
                        <td>WordPress Version</td>
                        <td><?php echo get_bloginfo('version'); ?></td>
                        <td>-</td>
                    </tr>
                    <tr>
                        <td>PHP Version</td>
                        <td><?php echo PHP_VERSION; ?></td>
                        <td>-</td>
                    </tr>
                </table>
            </div>
            
            <div class="card" style="margin-top: 20px;">
                <h2>Test AJAX Endpoints</h2>
                <p>Click the buttons below to test AJAX functionality:</p>
                
                <button class="button" onclick="testAjaxEndpoint('test_raid_system')">Test Raid System</button>
                <button class="button" onclick="testAjaxEndpoint('get_user_raids')">Get User Raids</button>
                <button class="button" onclick="testAjaxEndpoint('wow_raid_debug')">Debug Info</button>
                <button class="button" onclick="clearResults()">Clear Results</button>
                
                <div id="ajax-results" style="margin-top: 20px; padding: 10px; background: #f1f1f1; min-height: 100px; font-family: monospace; white-space: pre-wrap;"></div>
            </div>
            
            <div class="card" style="margin-top: 20px;">
                <h2>Quick Actions</h2>
                <p>
                    <button class="button button-primary" onclick="recreateTable()">Recreate Database Table</button>
                    <button class="button" onclick="createTestRaid()">Create Test Raid</button>
                </p>
                <div id="action-results" style="margin-top: 10px;"></div>
            </div>
            
            <?php if (!$table_exists): ?>
            <div class="notice notice-error">
                <p><strong>Database table is missing!</strong> Click "Recreate Database Table" above to fix this issue.</p>
            </div>
            <?php endif; ?>
            
            <script>
            function testAjaxEndpoint(action) {
                var results = document.getElementById('ajax-results');
                results.innerHTML += '\n\nTesting ' + action + '...\n';
                
                jQuery.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: { action: action },
                    success: function(response) {
                        results.innerHTML += 'SUCCESS: ' + JSON.stringify(response, null, 2) + '\n';
                        results.scrollTop = results.scrollHeight;
                    },
                    error: function(xhr, status, error) {
                        results.innerHTML += 'ERROR: ' + error + '\n';
                        results.innerHTML += 'Status: ' + xhr.status + '\n';
                        results.innerHTML += 'Response: ' + xhr.responseText + '\n';
                        results.scrollTop = results.scrollHeight;
                    }
                });
            }
            
            function clearResults() {
                document.getElementById('ajax-results').innerHTML = 'Results will appear here...';
            }
            
            function recreateTable() {
                var actionResults = document.getElementById('action-results');
                actionResults.innerHTML = '<span style="color: orange;">Recreating table...</span>';
                
                jQuery.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: { 
                        action: 'wow_raid_debug',
                        debug_action: 'recreate_table'
                    },
                    success: function(response) {
                        if (response.success) {
                            actionResults.innerHTML = '<span style="color: green;">✓ ' + response.data.message + '</span>';
                            setTimeout(function() { location.reload(); }, 2000);
                        } else {
                            actionResults.innerHTML = '<span style="color: red;">✗ Failed: ' + response.data + '</span>';
                        }
                    }
                });
            }
            
            function createTestRaid() {
                var actionResults = document.getElementById('action-results');
                actionResults.innerHTML = '<span style="color: orange;">Creating test raid...</span>';
                
                var tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                var dateStr = tomorrow.toISOString().split('T')[0];
                
                jQuery.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: { 
                        action: 'wow_raid_debug',
                        debug_action: 'create_test_raid',
                        date: dateStr
                    },
                    success: function(response) {
                        if (response.success) {
                            actionResults.innerHTML = '<span style="color: green;">✓ ' + response.data.message + '</span>';
                        } else {
                            actionResults.innerHTML = '<span style="color: red;">✗ Failed: ' + response.data + '</span>';
                        }
                    }
                });
            }
            </script>
        </div>
        <?php
    }
    
    public function ajax_debug_info() {
        global $wpdb;
        
        // Handle debug actions
        if (isset($_POST['debug_action'])) {
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
            }
            
            switch ($_POST['debug_action']) {
                case 'recreate_table':
                    $this->create_database_table();
                    wp_send_json_success(array('message' => 'Table recreated successfully'));
                    break;
                    
                case 'create_test_raid':
                    $table_name = $wpdb->prefix . 'wow_raids';
                    $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : date('Y-m-d', strtotime('+1 day'));
                    
                    $result = $wpdb->insert(
                        $table_name,
                        array(
                            'raid_date' => $date,
                            'raid_hour' => 20,
                            'raid_minute' => 30,
                            'raid_name' => 'Test Raid - Undermine',
                            'loot_type' => 'Saved',
                            'difficulty' => 'Heroic',
                            'boss_count' => 8,
                            'available_spots' => 20,
                            'raid_leader' => 'Test Leader',
                            'gold_collector' => 'Test Collector',
                            'notes' => 'This is a test raid created from debug panel',
                            'created_by' => get_current_user_id(),
                            'created_at' => current_time('mysql'),
                            'status' => 'active'
                        )
                    );
                    
                    if ($result) {
                        wp_send_json_success(array('message' => 'Test raid created successfully', 'id' => $wpdb->insert_id));
                    } else {
                        wp_send_json_error('Failed to create test raid: ' . $wpdb->last_error);
                    }
                    break;
            }
        }
        
        // Return debug information
        $table_name = $wpdb->prefix . 'wow_raids';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        
        $debug_info = array(
            'plugin_version' => WOW_RAID_FORM_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'table_exists' => $table_exists ? true : false,
            'table_name' => $table_name,
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
        
        if ($table_exists) {
            $debug_info['raid_count'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            $debug_info['latest_raids'] = $wpdb->get_results("SELECT id, raid_name, raid_date, created_at FROM $table_name ORDER BY id DESC LIMIT 5");
        }
        
        wp_send_json_success($debug_info);
    }
    
    public function register_settings() {
        register_setting(
            'wow_raid_form_group',
            'wow_raid_form_settings',
            array($this, 'validate_settings')
        );
    }
    
    public function validate_settings($input) {
        $valid = array();
        
        $default_settings = array(
            'allowed_wp_roles' => array('administrator'),
            'allowed_discord_roles' => '',
            'access_denied_message' => 'You do not have permission to create raids.',
            'success_message' => 'Raid created successfully!'
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
        
        return $valid;
    }
    
    public function admin_page() {
        $default_settings = array(
            'allowed_wp_roles' => array('administrator'),
            'allowed_discord_roles' => '',
            'access_denied_message' => 'You do not have permission to create raids.',
            'success_message' => 'Raid created successfully!'
        );
        
        $settings = get_option('wow_raid_form_settings', $default_settings);
        $settings = wp_parse_args($settings, $default_settings);
        
        ?>
        
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('wow_raid_form_group'); ?>
                
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Allowed WordPress Roles</th>
                        <td>
                            <?php
                            $wp_roles = wp_roles();
                            $allowed_wp_roles = isset($settings['allowed_wp_roles']) && is_array($settings['allowed_wp_roles']) 
                                ? $settings['allowed_wp_roles'] 
                                : array('administrator');
                            
                            foreach ($wp_roles->role_names as $role_id => $role_name) :
                            ?>
                                <label style="display: block; margin-bottom: 5px;">
                                    <input type="checkbox" 
                                           name="wow_raid_form_settings[allowed_wp_roles][]" 
                                           value="<?php echo esc_attr($role_id); ?>"
                                           <?php checked(in_array($role_id, $allowed_wp_roles)); ?>>
                                    <?php echo esc_html($role_name); ?>
                                </label>
                            <?php endforeach; ?>
                            <p class="description">Select WordPress roles that can create raids.</p>
                        </td>
                    </tr>
                    
                    <tr valign="top">
                        <th scope="row">Allowed Discord Role IDs</th>
                        <td>
                            <textarea name="wow_raid_form_settings[allowed_discord_roles]" 
                                      rows="3" 
                                      cols="50" 
                                      class="regular-text"><?php echo esc_textarea(isset($settings['allowed_discord_roles']) ? $settings['allowed_discord_roles'] : ''); ?></textarea>
                            <p class="description">Enter Discord role IDs separated by commas (e.g., 123456789,987654321). Leave empty to disable Discord role check.</p>
                        </td>
                    </tr>
                    
                    <tr valign="top">
                        <th scope="row">Access Denied Message</th>
                        <td>
                            <textarea name="wow_raid_form_settings[access_denied_message]" 
                                      rows="3" 
                                      cols="50" 
                                      class="regular-text"><?php echo esc_textarea(isset($settings['access_denied_message']) ? $settings['access_denied_message'] : 'You do not have permission to create raids.'); ?></textarea>
                            <p class="description">Message to show when user doesn't have permission.</p>
                        </td>
                    </tr>
                    
                    <tr valign="top">
                        <th scope="row">Success Message</th>
                        <td>
                            <textarea name="wow_raid_form_settings[success_message]" 
                                      rows="3" 
                                      cols="50" 
                                      class="regular-text"><?php echo esc_textarea(isset($settings['success_message']) ? $settings['success_message'] : 'Raid created successfully!'); ?></textarea>
                            <p class="description">Message to show when raid is created successfully.</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <hr>
            
            <h2>Usage</h2>
            <p>Use the shortcode <code>[wow_raid_form]</code> to display the raid creation form on any page or post.</p>
            <p>Use the shortcode <code>[wow_raid_dashboard]</code> to display both form and raids list (optimized for performance).</p>
            
            <h3>Quick Actions</h3>
            <p>
                <a href="<?php echo admin_url('admin.php?page=wow-manage-raids'); ?>" class="button button-secondary">View Created Raids</a>
                <a href="<?php echo admin_url('admin.php?page=wow-raid-debug'); ?>" class="button button-secondary">Debug Information</a>
            </p>
            
            <hr>
            
            <h2>Database Status</h2>
            <?php
            global $wpdb;
            $table_name = $wpdb->prefix . 'wow_raids';
            $raid_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            ?>
            <p>
                <strong>Raids Database:</strong> 
                <?php echo $wpdb->get_var("SHOW TABLES LIKE '$table_name'") ? '✅ Created' : '❌ Not found'; ?>
            </p>
            <p>
                <strong>Total Raids Stored:</strong> <?php echo intval($raid_count); ?>
                <?php if ($raid_count > 0) : ?>
                    - <a href="<?php echo admin_url('admin.php?page=wow-manage-raids'); ?>">View all raids</a>
                <?php endif; ?>
            </p>
        </div>
        
        <?php
    }
    
    private function user_has_access($user_id = null) {
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
        
        if (!empty($settings['allowed_wp_roles']) && is_array($settings['allowed_wp_roles'])) {
            $user_roles = $user->roles;
            $allowed_roles = $settings['allowed_wp_roles'];
            
            if (array_intersect($user_roles, $allowed_roles)) {
                return true;
            }
        }
        
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
    
    public function raid_form_shortcode($atts) {
        $atts = shortcode_atts(array(), $atts);
        
        if (!is_user_logged_in()) {
            return '<div class="raid-form-error">You must be logged in to create raids. <a href="' . wp_login_url(get_permalink()) . '">Login here</a></div>';
        }
        
        if (!$this->user_has_access()) {
            $default_settings = array(
                'allowed_wp_roles' => array('administrator'),
                'allowed_discord_roles' => '',
                'access_denied_message' => 'You do not have permission to create raids.',
                'success_message' => 'Raid created successfully!'
            );
            
            $settings = get_option('wow_raid_form_settings', $default_settings);
            $settings = wp_parse_args($settings, $default_settings);
            
            $message = !empty($settings['access_denied_message']) 
                ? $settings['access_denied_message'] 
                : 'You do not have permission to create raids.';
            
            return '<div class="raid-form-error">' . esc_html($message) . '</div>';
        }
        
        ob_start();
        $this->render_raid_form();
        return ob_get_clean();
    }
    
    public function raid_status_shortcode($atts) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wow_raids';
        
        ob_start();
        ?>
        <div style="background: #333; color: #fff; padding: 20px; border-radius: 8px; font-family: monospace;">
            <h3 style="color: #ff6b35;">WoW Raid System Status</h3>
            <p><strong>User ID:</strong> <?php echo get_current_user_id(); ?></p>
            <p><strong>Logged In:</strong> <?php echo is_user_logged_in() ? 'Yes' : 'No'; ?></p>
            <p><strong>Table Exists:</strong> <?php echo $wpdb->get_var("SHOW TABLES LIKE '$table_name'") ? 'Yes' : 'No'; ?></p>
            <p><strong>Total Raids:</strong> <?php echo intval($wpdb->get_var("SELECT COUNT(*) FROM $table_name")); ?></p>
            <p><strong>Your Raids:</strong> <?php echo intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE created_by = %d", get_current_user_id()))); ?></p>
            <p><strong>AJAX URL:</strong> <?php echo admin_url('admin-ajax.php'); ?></p>
            
            <?php if (current_user_can('administrator')): ?>
                <h4 style="color: #ff6b35; margin-top: 20px;">Recent Raids (Admin View)</h4>
                <?php
                $recent_raids = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC LIMIT 5");
                if ($recent_raids) {
                    echo '<ul>';
                    foreach ($recent_raids as $raid) {
                        echo '<li>' . esc_html($raid->raid_name) . ' - ' . esc_html($raid->raid_date) . ' (User ID: ' . $raid->created_by . ')</li>';
                    }
                    echo '</ul>';
                } else {
                    echo '<p>No raids found in database.</p>';
                }
                ?>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    // Shortcode بهینه برای نمایش dashboard
    public function raid_dashboard_shortcode($atts) {
        $atts = shortcode_atts(array(
            'show_form' => 'true',
            'show_raids' => 'true',
            'auto_refresh' => 'true'
        ), $atts);
        
        if (!is_user_logged_in()) {
            return '<div class="raid-form-error">You must be logged in to view raids. <a href="' . wp_login_url(get_permalink()) . '">Login here</a></div>';
        }
        
        ob_start();
        ?>
        <div class="raid-dashboard-container">
            <?php if ($atts['show_form'] === 'true' && $this->user_has_access()): ?>
            <div class="raid-form-section">
                <?php $this->render_raid_form(); ?>
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
        
        <script>
        // Lightweight JavaScript for optimized performance
        jQuery(document).ready(function($) {
            var showPast = false;
            var currentRaids = [];
            
            // Optimized AJAX function
            window.refreshRaidsList = function() {
                $('#raids-list-container').html('<p class="loading">Loading raids...</p>');
                
                $.ajax({
                    url: '<?php echo admin_url("admin-ajax.php"); ?>',
                    type: 'POST',
                    data: { action: 'get_user_raids' },
                    success: function(response) {
                        if (response.success && response.data.raids) {
                            currentRaids = response.data.raids;
                            renderRaidsList();
                            $('#raid-count-display').text('(' + currentRaids.length + ')');
                        } else {
                            $('#raids-list-container').html('<p>No raids found.</p>');
                        }
                    },
                    error: function() {
                        $('#raids-list-container').html('<p class="error">Failed to load raids.</p>');
                    }
                });
            };
            
            // Optimized render function
            function renderRaidsList() {
                var upcoming = currentRaids.filter(r => r.status !== 'done' && r.status !== 'cancelled');
                var past = currentRaids.filter(r => r.status === 'done' || r.status === 'cancelled');
                
                var html = '';
                
                if (upcoming.length > 0) {
                    html += '<div class="raids-section"><h4>Upcoming Raids</h4>';
                    upcoming.forEach(function(raid) {
                        html += createSimpleRaidCard(raid);
                    });
                    html += '</div>';
                }
                
                if (past.length > 0) {
                    html += '<div class="raids-section past-raids' + (showPast ? '' : ' hidden') + '"><h4>Past Raids</h4>';
                    past.forEach(function(raid) {
                        html += createSimpleRaidCard(raid);
                    });
                    html += '</div>';
                }
                
                $('#raids-list-container').html(html || '<p>No raids scheduled.</p>');
                bindSimpleActions();
            }
            
            // Simplified raid card
            function createSimpleRaidCard(raid) {
                var statusClass = 'raid-status-' + raid.status;
                return '<div class="simple-raid-card ' + statusClass + '" data-id="' + raid.id + '">' +
                       '<div class="raid-title">' + raid.name + ' - ' + raid.date + ' @ ' + raid.time + '</div>' +
                       '<div class="raid-info">' + raid.difficulty + ' | ' + raid.loot_type + ' | ' + raid.spots + ' spots</div>' +
                       '<div class="raid-leaders">RL: ' + raid.leader + ' | GC: ' + raid.gold_collector + '</div>' +
                       (raid.can_edit ? '<div class="raid-actions">' + getActionButtons(raid) + '</div>' : '') +
                       '</div>';
            }
            
            // Simplified action buttons
            function getActionButtons(raid) {
                var buttons = '';
                if (raid.status !== 'done' && raid.status !== 'cancelled') {
                    if (!raid.is_locked) {
                        buttons += '<button class="btn-sm btn-lock" data-id="' + raid.id + '">Lock</button>';
                    } else {
                        buttons += '<button class="btn-sm btn-unlock" data-id="' + raid.id + '">Unlock</button>';
                    }
                    if (raid.is_past_time || raid.is_locked) {
                        buttons += '<button class="btn-sm btn-done" data-id="' + raid.id + '">Done</button>';
                    }
                    buttons += '<button class="btn-sm btn-cancel" data-id="' + raid.id + '">Cancel</button>';
                }
                return buttons;
            }
            
            // Bind actions
            function bindSimpleActions() {
                $('.btn-lock, .btn-unlock, .btn-done, .btn-cancel').off('click').on('click', function() {
                    var $btn = $(this);
                    var raidId = $btn.data('id');
                    var action = $btn.hasClass('btn-lock') ? 'locked' : 
                               $btn.hasClass('btn-unlock') ? 'active' :
                               $btn.hasClass('btn-done') ? 'done' : 'cancelled';
                    
                    if (action === 'cancelled' && !confirm('Cancel this raid?')) return;
                    
                    $btn.prop('disabled', true);
                    
                    $.post('<?php echo admin_url("admin-ajax.php"); ?>', {
                        action: 'update_raid_status',
                        raid_id: raidId,
                        status: action
                    }, function(response) {
                        if (response.success) {
                            refreshRaidsList();
                        } else {
                            alert('Error: ' + response.data);
                            $btn.prop('disabled', false);
                        }
                    });
                });
            }
            
            // Toggle past raids
            window.togglePastRaidsList = function() {
                showPast = !showPast;
                $('.past-raids').toggleClass('hidden');
                $('#toggle-past-btn').html(showPast ? 
                    '<i class="fas fa-eye-slash"></i> Hide Past' : 
                    '<i class="fas fa-eye"></i> Show Past');
            };
            
            // Initial load
            refreshRaidsList();
            
            // Auto refresh (optional)
            <?php if ($atts['auto_refresh'] === 'true'): ?>
            setInterval(refreshRaidsList, 60000);
            <?php endif; ?>
            
            // Listen for form submissions
            $(document).on('raid_created', refreshRaidsList);
        });
        </script>
        
        <style>
        .raid-dashboard-container {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .raid-form-section, .raid-list-section {
            flex: 1;
            min-width: 300px;
        }
        
        .raids-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .raids-controls button {
            margin-left: 5px;
            padding: 5px 10px;
            font-size: 12px;
        }
        
        .simple-raid-card {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 10px;
        }
        
        .raid-status-done {
            opacity: 0.7;
            border-color: #5cb85c;
        }
        
        .raid-status-cancelled {
            opacity: 0.7;
            border-color: #dc3545;
        }
        
        .raid-status-locked {
            border-color: #f0ad4e;
        }
        
        .raid-title {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .raid-info, .raid-leaders {
            font-size: 12px;
            color: #666;
            margin-bottom: 3px;
        }
        
        .raid-actions {
            margin-top: 8px;
        }
        
        .btn-sm {
            padding: 3px 8px;
            font-size: 11px;
            margin-right: 5px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }
        
        .btn-lock { background: #f0ad4e; color: white; }
        .btn-unlock { background: #5cb85c; color: white; }
        .btn-done { background: #5cb85c; color: white; }
        .btn-cancel { background: #dc3545; color: white; }
        
        .hidden { display: none; }
        .loading { font-style: italic; color: #999; }
        .error { color: #dc3545; }
        
        @media (max-width: 768px) {
            .raid-dashboard-container {
                flex-direction: column;
            }
        }
        </style>
        <?php
        return ob_get_clean();
    }
    
    private function render_raid_form() {
        $today = date('Y-m-d');
        ?>
        <div class="wow-raid-form-container">
            <form id="wow-raid-form" class="raid-form-wrapper">
                <h3>Create New Raid</h3>
                
                <div class="form-row">
                    <label for="raid-date">Raid Date*</label>
                    <input type="date" id="raid-date" name="raid_date" min="<?php echo $today; ?>" required>
                </div>
                
                <div class="form-row">
                    <label for="raid-time">Raid Time*</label>
                    <input type="time" id="raid-time" name="raid_time" required>
                    <small class="form-help">Select raid start time (24-hour format)</small>
                </div>
                
                <div class="form-row">
                    <label for="raid-name">Raid Name*</label>
                    <select id="raid-name" name="raid_name" required>
                        <option value="">Select Raid</option>
                        <option value="Undermine" selected>Undermine</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <label for="loot-type">Loot Type*</label>
                    <select id="loot-type" name="loot_type" required>
                        <option value="">Select Loot Type</option>
                        <option value="Saved">Saved</option>
                        <option value="Unsaved">Unsaved</option>
                        <option value="VIP">VIP</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <label for="difficulty">Difficulty*</label>
                    <select id="difficulty" name="difficulty" required>
                        <option value="">Select Difficulty</option>
                        <option value="Normal">Normal</option>
                        <option value="Heroic">Heroic</option>
                        <option value="Mythic">Mythic</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <label for="boss-count">Number of Bosses*</label>
                    <select id="boss-count" name="boss_count" required>
                        <option value="">Select</option>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                        <option value="5">5</option>
                        <option value="6">6</option>
                        <option value="7">7</option>
                        <option value="8">8</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <label for="available-spots">Available Spots*</label>
                    <input type="number" id="available-spots" name="available_spots" min="1" max="30" placeholder="20" required>
                </div>
                
                <div class="form-row">
                    <label for="raid-leader">Raid Leader*</label>
                    <input type="text" id="raid-leader" name="raid_leader" required>
                </div>
                
                <div class="form-row">
                    <label for="gold-collector">Gold Collector*</label>
                    <input type="text" id="gold-collector" name="gold_collector" required>
                </div>
                
                <div class="form-row">
                    <label for="notes">Additional Notes</label>
                    <textarea id="notes" name="notes" placeholder="Any additional information..." rows="4"></textarea>
                </div>
                
                <div class="form-row">
                    <button type="submit" id="submit-raid-btn">Create Raid</button>
                    <div id="form-loading" style="display: none;">Creating raid...</div>
                </div>
                
                <div id="form-messages"></div>
                
                <?php wp_nonce_field('create_wow_raid', 'wow_raid_nonce'); ?>
            </form>
        </div>
        <?php
    }

    public function enqueue_scripts() {
        wp_add_inline_style('wp-block-library', '
            .wow-raid-form-container {
                max-width: 600px;
                margin: 20px 0;
            }
            
            .raid-form-wrapper {
                background: #f9f9f9;
                padding: 20px;
                border-radius: 8px;
                border: 1px solid #ddd;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .raid-form-wrapper h3 {
                margin-top: 0;
                color: #333;
                border-bottom: 2px solid #0073aa;
                padding-bottom: 10px;
                font-size: 20px;
            }
            
            .form-row {
                margin-bottom: 15px;
                clear: both;
            }
            
            .form-row label {
                display: block;
                font-weight: bold;
                margin-bottom: 5px;
                color: #555;
                font-size: 13px;
            }
            
            .form-row input,
            .form-row select,
            .form-row textarea {
                width: 100%;
                padding: 8px;
                border: 2px solid #ddd;
                border-radius: 4px;
                font-size: 14px;
                box-sizing: border-box;
                transition: border-color 0.3s ease;
                background-color: #fff;
                color: #333;
            }
            
            .form-row select {
                background-color: #fff;
                -webkit-appearance: none;
                -moz-appearance: none;
                appearance: none;
                background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\'%3e%3cpolyline points=\'6 9 12 15 18 9\'%3e%3c/polyline%3e%3c/svg%3e");
                background-repeat: no-repeat;
                background-position: right 8px center;
                background-size: 16px;
                padding-right: 32px;
            }
            
            .form-row input:focus,
            .form-row select:focus,
            .form-row textarea:focus {
                border-color: #0073aa;
                outline: none;
                box-shadow: 0 0 4px rgba(0, 115, 170, 0.3);
            }
            
            .form-help {
                display: block;
                font-size: 11px;
                color: #999;
                margin-top: 3px;
                font-style: italic;
            }
            
            #submit-raid-btn {
                background: linear-gradient(135deg, #0073aa, #005a87);
                color: white;
                padding: 12px 24px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 16px;
                font-weight: bold;
                width: 100%;
                transition: all 0.3s ease;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            #submit-raid-btn:hover {
                background: linear-gradient(135deg, #005a87, #004066);
                transform: translateY(-1px);
                box-shadow: 0 3px 8px rgba(0, 115, 170, 0.3);
            }
            
            #submit-raid-btn:disabled {
                background: #ccc;
                cursor: not-allowed;
                transform: none;
                box-shadow: none;
            }
            
            #form-loading {
                text-align: center;
                color: #666;
                font-style: italic;
                margin-top: 10px;
            }
            
            .raid-form-error {
                background: #f8d7da;
                color: #721c24;
                padding: 12px;
                border: 1px solid #f5c6cb;
                border-radius: 4px;
                margin: 12px 0;
                font-weight: bold;
            }
            
            .raid-form-error a {
                color: #721c24;
                text-decoration: underline;
            }
            
            .form-success {
                background: #d4edda;
                color: #155724;
                padding: 12px;
                border: 1px solid #c3e6cb;
                border-radius: 4px;
                margin: 12px 0;
                font-weight: bold;
            }
            
            .form-error {
                background: #f8d7da;
                color: #721c24;
                padding: 12px;
                border: 1px solid #f5c6cb;
                border-radius: 4px;
                margin: 12px 0;
                font-weight: bold;
            }
            
            @media (max-width: 768px) {
                .form-row {
                    margin-bottom: 15px;
                }
            }
        ');
        
        wp_add_inline_script('jquery', '
            jQuery(document).ready(function($) {
                $("#wow-raid-form").on("submit", function(e) {
                    e.preventDefault();
                    
                    const $form = $(this);
                    const $btn = $("#submit-raid-btn");
                    const $loading = $("#form-loading");
                    const $messages = $("#form-messages");
                    
                    $messages.empty();
                    
                    $btn.prop("disabled", true);
                    $loading.show();
                    
                    // Parse time input
                    var timeValue = $("#raid-time").val();
                    var timeParts = timeValue.split(":");
                    var hour = parseInt(timeParts[0]);
                    var minute = parseInt(timeParts[1]);
                    
                    const formData = {
                        action: "create_wow_raid",
                        wow_raid_nonce: $("input[name=wow_raid_nonce]").val(),
                        raid_date: $("#raid-date").val(),
                        raid_hour: hour,
                        raid_minute: minute,
                        raid_name: $("#raid-name").val(),
                        loot_type: $("#loot-type").val(),
                        difficulty: $("#difficulty").val(),
                        boss_count: $("#boss-count").val(),
                        available_spots: $("#available-spots").val(),
                        raid_leader: $("#raid-leader").val(),
                        gold_collector: $("#gold-collector").val(),
                        notes: $("#notes").val()
                    };
                    
                    $.ajax({
                        url: "' . admin_url('admin-ajax.php') . '",
                        type: "POST",
                        data: formData,
                        success: function(response) {
                            $btn.prop("disabled", false);
                            $loading.hide();
                            
                            if (response.success) {
                                $messages.html("<div class=\"form-success\">" + response.data.message + "</div>");
                                $form[0].reset();
                                $("#raid-name").val("Undermine");
                                
                                $(document).trigger("raid_created");
                                
                                if (typeof window.loadRaids === "function") {
                                    setTimeout(window.loadRaids, 1000);
                                }
                            } else {
                                $messages.html("<div class=\"form-error\">" + response.data + "</div>");
                            }
                        },
                        error: function(xhr, status, error) {
                            $btn.prop("disabled", false);
                            $loading.hide();
                            $messages.html("<div class=\"form-error\">Connection error. Please try again.</div>");
                        }
                    });
                });
            });
        ');
    }
    
    public function ajax_create_raid() {
        if (!wp_verify_nonce($_POST['wow_raid_nonce'], 'create_wow_raid')) {
            wp_send_json_error('Security check failed.');
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in.');
        }
        
        if (!$this->user_has_access()) {
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
        
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wow_raids';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'raid_date' => sanitize_text_field($_POST['raid_date']),
                'raid_hour' => $raid_hour,
                'raid_minute' => $raid_minute,
                'raid_name' => sanitize_text_field($_POST['raid_name']),
                'loot_type' => sanitize_text_field($_POST['loot_type']),
                'difficulty' => sanitize_text_field($_POST['difficulty']),
                'boss_count' => intval($_POST['boss_count']),
                'available_spots' => intval($_POST['available_spots']),
                'raid_leader' => sanitize_text_field($_POST['raid_leader']),
                'gold_collector' => sanitize_text_field($_POST['gold_collector']),
                'notes' => sanitize_textarea_field($_POST['notes']),
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql')
            ),
            array(
                '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%s'
            )
        );
        
        if ($result === false) {
            wp_send_json_error('Failed to create raid. Database error: ' . $wpdb->last_error);
        }
        
        $settings = get_option('wow_raid_form_settings', array());
        $success_message = isset($settings['success_message']) ? $settings['success_message'] : 'Raid created successfully!';
        
        wp_send_json_success(array(
            'message' => $success_message,
            'raid_id' => $wpdb->insert_id
        ));
    }
    
    public function ajax_get_user_raids() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wow_raids';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $this->create_database_table();
            
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
        
        // Debug Mode: If $_POST['debug_mode'] is set, return all info
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
        
        // Special case: Show ALL raids if user is admin and requested
        if (current_user_can('administrator') && isset($_POST['show_all_raids']) && $_POST['show_all_raids'] == 'true') {
            $all_raids_query = "SELECT * FROM $table_name 
                              WHERE status != 'deleted'
                              ORDER BY raid_date DESC, raid_hour DESC, raid_minute DESC 
                              LIMIT 50";
            $raids = $wpdb->get_results($all_raids_query);
            
            wp_send_json_success(array(
                'raids' => $this->format_raids_array($raids, $current_user_id),
                'user_id' => $current_user_id,
                'is_admin' => true,
                'showing_all' => true,
                'total_count' => count($raids)
            ));
            return;
        }
        
        // First check if user has any raids at all
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
        
        // Get all user raids (both past and future)
        $query = "SELECT * FROM $table_name 
                  WHERE created_by = %d
                  AND status != 'deleted'
                  ORDER BY raid_date DESC, raid_hour DESC, raid_minute DESC 
                  LIMIT 20";
        
        $raids = $wpdb->get_results($wpdb->prepare($query, $current_user_id));
        
        // If no raids, try showing recent raids from all users (for admin)
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
            'raids' => $this->format_raids_array($raids, $current_user_id),
            'user_id' => $current_user_id,
            'is_admin' => current_user_can('administrator')
        ));
    }
    
    private function format_raids_array($raids, $current_user_id) {
        $formatted_raids = array();
        $now = time();
        
        foreach ($raids as $raid) {
            $user = get_user_by('id', $raid->created_by);
            
            // Determine display status based on time and actual status
            $raid_datetime = strtotime($raid->raid_date . ' ' . $raid->raid_hour . ':' . $raid->raid_minute);
            $is_past_time = ($raid_datetime < $now);
            
            // منطق جدید برای تعیین status
            if ($raid->status == 'done') {
                $status = 'done';
            } elseif ($raid->status == 'cancelled') {
                $status = 'cancelled';
            } elseif ($raid->status == 'locked') {
                $status = 'locked';
            } elseif ($is_past_time && $raid->status == 'active') {
                $status = 'delayed';  // Past time but not completed
            } else {
                $status = 'upcoming';  // Future raids
            }
            
            // اصلاح منطق is_past_raid
            // فقط raids که done یا cancelled هستند به عنوان past در نظر گرفته می‌شوند
            $is_past_raid = ($raid->status == 'done' || $raid->status == 'cancelled');
            
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
                'can_edit' => ($raid->created_by == $current_user_id || current_user_can('administrator'))
            );
        }
        
        return $formatted_raids;
    }
    
    public function ajax_update_raid_status() {
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in.');
        }
        
        $raid_id = intval($_POST['raid_id']);
        $new_status = sanitize_text_field($_POST['status']);
        
        // اضافه کردن cancelled به لیست status های مجاز
        if (!in_array($new_status, array('active', 'locked', 'done', 'cancelled', 'delayed'))) {
            wp_send_json_error('Invalid status.');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'wow_raids';
        
        $raid = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $raid_id));
        
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
        
        $result = $wpdb->update(
            $table_name,
            array('status' => $new_status),
            array('id' => $raid_id),
            array('%s'),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error('Failed to update status.');
        }
        
        wp_send_json_success(array(
            'message' => 'Status updated successfully.',
            'new_status' => $new_status
        ));
    }
    
    public function ajax_update_raid() {
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in.');
        }
        
        $raid_id = intval($_POST['raid_id']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'wow_raids';
        
        $raid = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $raid_id));
        
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
        
        $result = $wpdb->update(
            $table_name,
            $update_data,
            array('id' => $raid_id),
            $update_format,
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error('Failed to update raid.');
        }
        
        wp_send_json_success(array(
            'message' => 'Raid updated successfully.'
        ));
    }
    
    public function ajax_test_raid_system() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wow_raids';
        
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        
        $total_raids = 0;
        if ($table_exists) {
            $total_raids = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        }
        
        $recent_raids = array();
        if ($table_exists) {
            $recent_raids = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC LIMIT 5");
        }
        
        wp_send_json_success(array(
            'table_exists' => $table_exists ? true : false,
            'total_raids' => $total_raids,
            'recent_raids' => $recent_raids,
            'current_time' => current_time('mysql'),
            'ajax_url' => admin_url('admin-ajax.php')
        ));
    }
    
    public function manage_raids_page() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wow_raids';
        
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
            $raid_id = intval($_GET['id']);
            $wpdb->delete($table_name, array('id' => $raid_id), array('%d'));
            echo '<div class="notice notice-success"><p>Raid deleted successfully.</p></div>';
        }
        
        if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['id'])) {
            $raid_id = intval($_GET['id']);
            $raid = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $raid_id));
            
            if ($raid) {
                $this->display_raid_details($raid);
                return;
            } else {
                echo '<div class="notice notice-error"><p>Raid not found.</p></div>';
            }
        }
        
        $raids = $wpdb->get_results("SELECT * FROM $table_name ORDER BY raid_date DESC, raid_hour DESC");
        
        ?>
        <div class="wrap">
            <h1>Manage Raids</h1>
            
            <?php if (empty($raids)) : ?>
                <p>No raids found. <a href="<?php echo admin_url('admin.php?page=wow-raid-forms'); ?>">Configure raid form</a> or create some raids first.</p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Raid Name</th>
                            <th>Loot Type</th>
                            <th>Difficulty</th>
                            <th>Bosses</th>
                            <th>Spots</th>
                            <th>Raid Leader</th>
                            <th>Gold Collector</th>
                            <th>Created By</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($raids as $raid) : 
                            $user = get_user_by('id', $raid->created_by);
                            $username = $user ? $user->display_name : 'Unknown';
                            
                            $datetime = date('Y-m-d', strtotime($raid->raid_date)) . ' ' . 
                                       str_pad($raid->raid_hour, 2, '0', STR_PAD_LEFT) . ':' . 
                                       str_pad($raid->raid_minute, 2, '0', STR_PAD_LEFT);
                            
                            // Determine status color
                            $status_class = '';
                            if ($raid->status == 'done') {
                                $status_class = 'color: #5cb85c;';
                            } elseif ($raid->status == 'cancelled') {
                                $status_class = 'color: #dc3545;';
                            } elseif ($raid->status == 'locked') {
                                $status_class = 'color: #f0ad4e;';
                            }
                        ?>
                            <tr>
                                <td><?php echo esc_html($datetime); ?></td>
                                <td><strong><?php echo esc_html($raid->raid_name); ?></strong></td>
                                <td>
                                    <span class="loot-type loot-<?php echo strtolower($raid->loot_type); ?>">
                                        <?php echo esc_html($raid->loot_type); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="difficulty difficulty-<?php echo strtolower($raid->difficulty); ?>">
                                        <?php echo esc_html($raid->difficulty); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($raid->boss_count); ?></td>
                                <td><?php echo esc_html($raid->available_spots); ?></td>
                                <td><?php echo esc_html($raid->raid_leader); ?></td>
                                <td><?php echo esc_html($raid->gold_collector); ?></td>
                                <td><?php echo esc_html($username); ?></td>
                                <td>
                                    <span style="<?php echo $status_class; ?>"><?php echo ucfirst($raid->status); ?></span>
                                </td>
                                <td>
                                    <a href="?page=wow-manage-raids&action=view&id=<?php echo $raid->id; ?>" class="button button-small">View</a>
                                    <a href="?page=wow-manage-raids&action=delete&id=<?php echo $raid->id; ?>" class="button button-small" onclick="return confirm('Are you sure you want to delete this raid?')">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <p><strong>Total Raids:</strong> <?php echo count($raids); ?></p>
            <?php endif; ?>
            
            <style>
                .loot-saved { color: #46b450; font-weight: bold; }
                .loot-unsaved { color: #dc3232; font-weight: bold; }
                .loot-vip { color: #ffb900; font-weight: bold; }
                
                .difficulty-normal { color: #666; }
                .difficulty-heroic { color: #0073aa; font-weight: bold; }
                .difficulty-mythic { color: #dc3232; font-weight: bold; }
            </style>
        </div>
        <?php
    }
    
    private function display_raid_details($raid) {
        $user = get_user_by('id', $raid->created_by);
        $username = $user ? $user->display_name : 'Unknown';
        
        $datetime = date('F j, Y', strtotime($raid->raid_date)) . ' at ' . 
                   str_pad($raid->raid_hour, 2, '0', STR_PAD_LEFT) . ':' . 
                   str_pad($raid->raid_minute, 2, '0', STR_PAD_LEFT);
        
        ?>
        <div class="wrap">
            <h1>Raid Details</h1>
            <a href="?page=wow-manage-raids" class="button">&larr; Back to All Raids</a>
            
            <div class="card" style="max-width: 600px; margin-top: 20px;">
                <h2><?php echo esc_html($raid->raid_name); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th>Date & Time:</th>
                        <td><?php echo esc_html($datetime); ?></td>
                    </tr>
                    <tr>
                        <th>Loot Type:</th>
                        <td>
                            <span class="loot-type loot-<?php echo strtolower($raid->loot_type); ?>">
                                <?php echo esc_html($raid->loot_type); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Difficulty:</th>
                        <td>
                            <span class="difficulty difficulty-<?php echo strtolower($raid->difficulty); ?>">
                                <?php echo esc_html($raid->difficulty); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Number of Bosses:</th>
                        <td><?php echo esc_html($raid->boss_count); ?></td>
                    </tr>
                    <tr>
                        <th>Available Spots:</th>
                        <td><?php echo esc_html($raid->available_spots); ?></td>
                    </tr>
                    <tr>
                        <th>Status:</th>
                        <td>
                            <?php 
                            $status_color = '#333';
                            if ($raid->status == 'done') $status_color = '#5cb85c';
                            elseif ($raid->status == 'cancelled') $status_color = '#dc3545';
                            elseif ($raid->status == 'locked') $status_color = '#f0ad4e';
                            ?>
                            <span style="color: <?php echo $status_color; ?>; font-weight: bold;">
                                <?php echo ucfirst(esc_html($raid->status)); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Raid Leader:</th>
                        <td><?php echo esc_html($raid->raid_leader); ?></td>
                    </tr>
                    <tr>
                        <th>Gold Collector:</th>
                        <td><?php echo esc_html($raid->gold_collector); ?></td>
                    </tr>
                    <?php if (!empty($raid->notes)) : ?>
                    <tr>
                        <th>Additional Notes:</th>
                        <td><?php echo nl2br(esc_html($raid->notes)); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Created By:</th>
                        <td><?php echo esc_html($username); ?></td>
                    </tr>
                    <tr>
                        <th>Created On:</th>
                        <td><?php echo date('F j, Y \a\t g:i A', strtotime($raid->created_at)); ?></td>
                    </tr>
                </table>
                
                <p>
                    <a href="?page=wow-manage-raids&action=delete&id=<?php echo $raid->id; ?>" 
                       class="button button-secondary" 
                       onclick="return confirm('Are you sure you want to delete this raid?')">
                        Delete Raid
                    </a>
                </p>
            </div>
            
            <style>
                .loot-saved { color: #46b450; font-weight: bold; }
                .loot-unsaved { color: #dc3232; font-weight: bold; }
                .loot-vip { color: #ffb900; font-weight: bold; }
                
                .difficulty-normal { color: #666; }
                .difficulty-heroic { color: #0073aa; font-weight: bold; }
                .difficulty-mythic { color: #dc3232; font-weight: bold; }
            </style>
        </div>
        <?php
    }
}

new WoW_Raid_Form_Manager();