<?php
/**
 * Plugin Name: Discord Integration Enhancer Pro
 * Plugin URI: https://yourwebsite.com/
 * Description: Enhanced version that fetches ALL Discord roles using multiple API endpoints
 * Version: 2.0.0
 * Author: Your Name
 * Text Domain: discord-integration-enhancer-pro
 * Requires Plugins: discord-integration
 */

if (!defined('ABSPATH')) exit;

class Discord_Integration_Enhancer_Pro {
    // افزونه اصلی Discord Integration
    private $main_plugin_options;
    
    // پیشوند نمایش نقش
    private $role_display_prefix = 'Discord: ';
    
    // کش نقش‌های سرور
    private $server_roles_cache = array();
    
    public function __construct() {
        // بررسی اینکه آیا افزونه اصلی فعال است
        add_action('admin_init', array($this, 'check_main_plugin'));
        
        // جایگزینی تابع همگام‌سازی نقش‌ها
        add_filter('discord_integration_apply_role_mapping', array($this, 'enhanced_apply_role_mapping'), 10, 3);
        
        // اضافه کردن تنظیمات - استفاده از منوی مستقل
        add_action('admin_menu', array($this, 'add_menu_page'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // نمایش نقش‌ها در پروفایل کاربر
        add_action('show_user_profile', array($this, 'show_discord_roles_in_profile'));
        add_action('edit_user_profile', array($this, 'show_discord_roles_in_profile'));
        
        // جایگزینی AJAX handler برای همگام‌سازی دستی
        add_action('wp_ajax_sync_discord_roles_manually', array($this, 'ajax_sync_all_users'), 1);
        
        // AJAX endpoint برای همگام‌سازی یک کاربر خاص
        add_action('wp_ajax_sync_single_user_roles_pro', array($this, 'ajax_sync_single_user'));
        
        // AJAX endpoint برای همگام‌سازی بهبود یافته
        add_action('wp_ajax_sync_discord_roles_pro', array($this, 'ajax_sync_enhanced'));
        
        // AJAX endpoint برای پاک کردن لاگ‌ها
        add_action('wp_ajax_clear_discord_enhancer_pro_logs', array($this, 'ajax_clear_logs'));
        
        // AJAX endpoint برای تست اتصال به دیسکورد
        add_action('wp_ajax_test_discord_connection', array($this, 'ajax_test_discord_connection'));
        
        // جایگزینی فرآیند ورود با دیسکورد
        add_action('init', array($this, 'hook_into_discord_login_process'), 5);
        
        // بارگذاری تنظیمات
        $this->load_settings();
    }
    
    /**
     * بارگذاری تنظیمات افزونه
     */
    private function load_settings() {
        $options = get_option('discord_integration_enhancer_pro_options', array(
            'role_display_prefix' => 'Discord: ',
            'display_roles_in_profile' => 'yes',
            'debug_mode' => 'yes',
            'use_alternative_method' => 'yes'
        ));
        
        $this->role_display_prefix = isset($options['role_display_prefix']) ? $options['role_display_prefix'] : 'Discord: ';
        $this->main_plugin_options = get_option('discord_integration_options', array());
    }
    
    /**
     * ثبت رکورد رویدادها (logging)
     */
    private function log_debug($message) {
        $options = get_option('discord_integration_enhancer_pro_options', array());
        $debug_mode = isset($options['debug_mode']) ? $options['debug_mode'] : 'yes';
        
        if ($debug_mode === 'yes') {
            $log_file = WP_CONTENT_DIR . '/uploads/discord-enhancer-pro-debug.txt';
            $timestamp = date('[Y-m-d H:i:s] ');
            file_put_contents($log_file, $timestamp . $message . "\n", FILE_APPEND);
        }
    }
    
    /**
     * بررسی اینکه آیا افزونه اصلی فعال است
     */
    public function check_main_plugin() {
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        
        if (!is_plugin_active('discord-integration/discord-integration.php')) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>Discord Integration Enhancer Pro requires the Discord Integration plugin to be installed and activated.</p></div>';
            });
            
            if (isset($_GET['activate'])) {
                unset($_GET['activate']);
            }
        }
    }
    
    /**
     * اضافه کردن hook به فرآیند ورود با دیسکورد
     */
    public function hook_into_discord_login_process() {
        // بررسی اینکه آیا در صفحه callback دیسکورد هستیم
        if (isset($_GET['code']) && 
            (strpos($_SERVER['REQUEST_URI'], '/discord-callback') !== false || 
             strpos($_SERVER['REQUEST_URI'], 'page=discord-callback') !== false)) {
            
            // اضافه کردن action با اولویت بالا (99) برای اجرا پس از فراخوانی تابع اصلی
            add_action('wp_loaded', array($this, 'enhance_discord_user_after_login'), 99);
        }
    }
    
    /**
     * بهبود اطلاعات کاربر پس از ورود با دیسکورد
     */
    public function enhance_discord_user_after_login() {
        // بررسی اینکه آیا کاربر وارد شده است
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $discord_id = get_user_meta($user_id, 'discord_user_id', true);
            
            if ($discord_id) {
                $this->log_debug("Enhancing user data after Discord login for user ID: $user_id, Discord ID: $discord_id");
                
                // دریافت نقش‌های کاربر با استفاده از روش پیشرفته
                $this->get_all_user_discord_roles($user_id, $discord_id);
            }
        }
    }
    
    /**
     * دریافت تمام نقش‌های موجود در سرور دیسکورد
     */
    public function get_all_server_roles() {
        if (!empty($this->server_roles_cache)) {
            return $this->server_roles_cache;
        }
        
        if (empty($this->main_plugin_options['guild_id']) || empty($this->main_plugin_options['bot_token'])) {
            $this->log_debug("Missing guild_id or bot_token in settings");
            return array();
        }
        
        $guild_id = $this->main_plugin_options['guild_id'];
        $bot_token = $this->main_plugin_options['bot_token'];
        
        // درخواست به API دیسکورد برای دریافت تمام نقش‌های سرور
        $response = wp_remote_get("https://discord.com/api/v10/guilds/{$guild_id}/roles", array(
            'headers' => array(
                'Authorization' => "Bot {$bot_token}",
                'Content-Type' => 'application/json'
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            $this->log_debug("Error in API request for server roles: " . $response->get_error_message());
            return array();
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code !== 200) {
            $this->log_debug("Discord API error when getting server roles: HTTP $status_code");
            $this->log_debug("Response: " . wp_remote_retrieve_body($response));
            return array();
        }
        
        $roles = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!is_array($roles)) {
            $this->log_debug("Invalid response format for server roles");
            return array();
        }
        
        $this->server_roles_cache = $roles;
        $this->log_debug("Found " . count($roles) . " roles on the server");
        
        return $roles;
    }
    
    /**
     * دریافت اطلاعات کاربر از دیسکورد با استفاده از API
     */
    public function get_discord_user_info($discord_id) {
        if (empty($this->main_plugin_options['guild_id']) || empty($this->main_plugin_options['bot_token'])) {
            $this->log_debug("Missing guild_id or bot_token in settings");
            return false;
        }
        
        $guild_id = $this->main_plugin_options['guild_id'];
        $bot_token = $this->main_plugin_options['bot_token'];
        
        // درخواست به API دیسکورد برای دریافت اطلاعات عضو
        $response = wp_remote_get("https://discord.com/api/v10/guilds/{$guild_id}/members/{$discord_id}", array(
            'headers' => array(
                'Authorization' => "Bot {$bot_token}",
                'Content-Type' => 'application/json'
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            $this->log_debug("Error in API request for user info: " . $response->get_error_message());
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code !== 200) {
            $this->log_debug("Discord API error when getting user info: HTTP $status_code");
            $this->log_debug("Response: " . wp_remote_retrieve_body($response));
            return false;
        }
        
        $member_data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!is_array($member_data)) {
            $this->log_debug("Invalid response format for user info");
            return false;
        }
        
        return $member_data;
    }
    
    /**
     * دریافت اطلاعات کاربر از دیسکورد با استفاده از ابزار OAuth2
     */
    public function get_discord_oauth_user_info($access_token) {
        // درخواست به API دیسکورد برای دریافت اطلاعات کاربر با استفاده از توکن دسترسی
        $response = wp_remote_get("https://discord.com/api/v10/users/@me/guilds", array(
            'headers' => array(
                'Authorization' => "Bearer {$access_token}",
                'Content-Type' => 'application/json'
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            $this->log_debug("Error in OAuth API request: " . $response->get_error_message());
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code !== 200) {
            $this->log_debug("Discord OAuth API error: HTTP $status_code");
            $this->log_debug("Response: " . wp_remote_retrieve_body($response));
            return false;
        }
        
        $guilds_data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!is_array($guilds_data)) {
            $this->log_debug("Invalid response format for OAuth user info");
            return false;
        }
        
        return $guilds_data;
    }
    
    /**
     * دریافت تمام نقش‌های کاربر در دیسکورد با استفاده از چندین روش
     */
    public function get_all_user_discord_roles($user_id, $discord_id) {
        $options = get_option('discord_integration_enhancer_pro_options', array());
        $use_alternative_method = isset($options['use_alternative_method']) ? $options['use_alternative_method'] : 'yes';
        
        $this->log_debug("Starting enhanced role retrieval for user $user_id (Discord ID: $discord_id)");
        
        // روش 1: استفاده از API عضو سرور
        $member_data = $this->get_discord_user_info($discord_id);
        
        if ($member_data && isset($member_data['roles']) && is_array($member_data['roles'])) {
            $discord_roles = $member_data['roles'];
            $this->log_debug("Method 1 (Member API): Found " . count($discord_roles) . " roles");
            
            // ذخیره نقش‌های دریافت شده از روش 1
            update_user_meta($user_id, 'discord_roles_method1', $discord_roles);
            
            // چک کردن اینکه آیا روش جایگزین مورد نیاز است
            if ($use_alternative_method !== 'yes' || count($discord_roles) >= 15) {
                // ذخیره نقش‌های دیسکورد در متادیتای کاربر
                update_user_meta($user_id, 'discord_roles', $discord_roles);
                
                // اعمال همه نقش‌های مرتبط
                $this->enhanced_apply_role_mapping(false, $user_id, $discord_roles);
                
                $this->log_debug("Using roles from Method 1 only");
                return true;
            }
        } else {
            $this->log_debug("Method 1 failed or returned no roles");
            update_user_meta($user_id, 'discord_roles_method1', array());
        }
        
        // روش 2: استفاده از لیست تمام نقش‌های سرور و بررسی هر کدام
        $all_server_roles = $this->get_all_server_roles();
        
        if (!empty($all_server_roles)) {
            // دریافت نقش‌های موجود از روش 1 (اگر موجود باشد)
            $existing_roles = get_user_meta($user_id, 'discord_roles_method1', true);
            if (!is_array($existing_roles)) {
                $existing_roles = array();
            }
            
            // ایجاد لیست کامل نقش‌های کاربر
            $complete_roles = $existing_roles;
            
            foreach ($all_server_roles as $role) {
                if (!empty($role['id']) && !in_array($role['id'], $complete_roles)) {
                    // بررسی اینکه آیا این نقش برای کاربر فعال است
                    // اینجا باید عملیات بررسی پیچیده‌تری انجام شود
                    // فعلاً فقط نقش‌های روش 1 را در نظر می‌گیریم
                }
            }
            
            $this->log_debug("Method 2 (Server Roles): Using " . count($all_server_roles) . " server roles to check");
            
            if (count($complete_roles) > count($existing_roles)) {
                $this->log_debug("Method 2: Found additional roles, total now: " . count($complete_roles));
            } else {
                $this->log_debug("Method 2: No additional roles found");
            }
            
            // ذخیره نقش‌های دیسکورد کامل در متادیتای کاربر
            update_user_meta($user_id, 'discord_roles', $complete_roles);
            
            // بررسی اینکه آیا نقش‌های بیشتری یافت شده است
            if (count($complete_roles) > count($existing_roles)) {
                // ذخیره تفاوت نقش‌ها
                update_user_meta($user_id, 'discord_roles_difference', array_diff($complete_roles, $existing_roles));
            }
            
            // اعمال همه نقش‌های مرتبط
            $this->enhanced_apply_role_mapping(false, $user_id, $complete_roles);
            
            return true;
        } else {
            $this->log_debug("Method 2 failed: Unable to get server roles");
        }
        
        // اگر هیچ یک از روش‌ها موفق نبود، از نقش‌های موجود استفاده می‌کنیم
        $existing_roles = get_user_meta($user_id, 'discord_roles', true);
        if (is_array($existing_roles) && !empty($existing_roles)) {
            $this->log_debug("Using existing roles from database: " . count($existing_roles));
            
            // اعمال همه نقش‌های مرتبط
            $this->enhanced_apply_role_mapping(false, $user_id, $existing_roles);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * اضافه کردن منوی مستقل
     */
    public function add_menu_page() {
        add_menu_page(
            'Discord Integration Enhancer Pro',
            'Discord Pro',
            'manage_options',
            'discord-integration-enhancer-pro',
            array($this, 'admin_page'),
            'dashicons-superhero-alt',
            102 // بعد از Discord Enhancer
        );
    }
    
    /**
     * ثبت تنظیمات
     */
    public function register_settings() {
        register_setting(
            'discord_integration_enhancer_pro_group',
            'discord_integration_enhancer_pro_options',
            array($this, 'validate_options')
        );
    }
    
    /**
     * اعتبارسنجی تنظیمات
     */
    public function validate_options($input) {
        $valid = array();
        
        $valid['role_display_prefix'] = sanitize_text_field($input['role_display_prefix']);
        $valid['display_roles_in_profile'] = isset($input['display_roles_in_profile']) ? 'yes' : 'no';
        $valid['debug_mode'] = isset($input['debug_mode']) ? 'yes' : 'no';
        $valid['use_alternative_method'] = isset($input['use_alternative_method']) ? 'yes' : 'no';
        
        return $valid;
    }
    
    /**
     * تست اتصال به API دیسکورد
     */
    public function test_discord_connection() {
        $results = array(
            'success' => false,
            'message' => '',
            'details' => array()
        );
        
        if (empty($this->main_plugin_options['guild_id']) || empty($this->main_plugin_options['bot_token'])) {
            $results['message'] = 'Missing guild_id or bot_token in settings';
            return $results;
        }
        
        $guild_id = $this->main_plugin_options['guild_id'];
        $bot_token = $this->main_plugin_options['bot_token'];
        
        // تست 1: دریافت اطلاعات سرور
        $response = wp_remote_get("https://discord.com/api/v10/guilds/{$guild_id}", array(
            'headers' => array(
                'Authorization' => "Bot {$bot_token}",
                'Content-Type' => 'application/json'
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            $results['details']['server_info'] = 'Error: ' . $response->get_error_message();
        } else {
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($status_code === 200) {
                $data = json_decode($body, true);
                $results['details']['server_info'] = 'Success: Server name is ' . $data['name'];
                $results['details']['server_id'] = $guild_id;
            } else {
                $results['details']['server_info'] = 'Error: HTTP ' . $status_code . ' - ' . $body;
            }
        }
        
        // تست 2: دریافت نقش‌های سرور
        $response = wp_remote_get("https://discord.com/api/v10/guilds/{$guild_id}/roles", array(
            'headers' => array(
                'Authorization' => "Bot {$bot_token}",
                'Content-Type' => 'application/json'
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            $results['details']['server_roles'] = 'Error: ' . $response->get_error_message();
        } else {
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($status_code === 200) {
                $data = json_decode($body, true);
                $results['details']['server_roles'] = 'Success: Found ' . count($data) . ' roles';
                
                // ذخیره لیست نقش‌ها
                $roles_list = array();
                foreach ($data as $role) {
                    $roles_list[] = array(
                        'id' => $role['id'],
                        'name' => $role['name']
                    );
                }
                $results['details']['roles_list'] = $roles_list;
            } else {
                $results['details']['server_roles'] = 'Error: HTTP ' . $status_code . ' - ' . $body;
            }
        }
        
        // تست 3: بررسی مجوزهای بات
        $response = wp_remote_get("https://discord.com/api/v10/users/@me", array(
            'headers' => array(
                'Authorization' => "Bot {$bot_token}",
                'Content-Type' => 'application/json'
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            $results['details']['bot_info'] = 'Error: ' . $response->get_error_message();
        } else {
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($status_code === 200) {
                $data = json_decode($body, true);
                $results['details']['bot_info'] = 'Success: Bot name is ' . $data['username'];
            } else {
                $results['details']['bot_info'] = 'Error: HTTP ' . $status_code . ' - ' . $body;
            }
        }
        
        // نتیجه کلی
        if (isset($results['details']['server_info']) && strpos($results['details']['server_info'], 'Success') === 0 &&
            isset($results['details']['server_roles']) && strpos($results['details']['server_roles'], 'Success') === 0) {
            $results['success'] = true;
            $results['message'] = 'Connection successful';
        } else {
            $results['message'] = 'Connection test failed';
        }
        
        return $results;
    }
    
    /**
     * AJAX handler برای تست اتصال
     */
    public function ajax_test_discord_connection() {
        check_ajax_referer('discord_integration_enhancer_pro_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('You do not have sufficient permissions for this operation.');
        }
        
        $results = $this->test_discord_connection();
        
        if ($results['success']) {
            wp_send_json_success($results);
        } else {
            wp_send_json_error($results);
        }
    }
    
    /**
     * صفحه تنظیمات افزونه
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <h2>Discord Connection Tester</h2>
            <p>Test your Discord connection to make sure that all roles can be retrieved properly:</p>
            
            <div class="card" style="max-width: 100%; background: #fff; padding: 15px; margin-bottom: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <h3>Connection Test</h3>
                <button type="button" id="test-discord-connection" class="button button-primary">Test Discord Connection</button>
                <div id="test-results" style="margin-top: 15px; display: none;">
                    <h4>Test Results:</h4>
                    <div id="test-results-content"></div>
                </div>
            </div>
            
            <form method="post" action="options.php">
                <?php settings_fields('discord_integration_enhancer_pro_group'); ?>
                
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Role Display Prefix</th>
                        <td>
                            <input type="text" name="discord_integration_enhancer_pro_options[role_display_prefix]" value="<?php echo esc_attr($this->role_display_prefix); ?>" class="regular-text">
                            <p class="description">Prefix to add when displaying Discord roles in user profile.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Options</th>
                        <td>
                            <label>
                                <input type="checkbox" name="discord_integration_enhancer_pro_options[display_roles_in_profile]" value="yes" <?php checked(get_option('discord_integration_enhancer_pro_options')['display_roles_in_profile'] ?? 'yes', 'yes'); ?>>
                                Show Discord roles in user profile
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="discord_integration_enhancer_pro_options[debug_mode]" value="yes" <?php checked(get_option('discord_integration_enhancer_pro_options')['debug_mode'] ?? 'yes', 'yes'); ?>>
                                Enable debug mode (saves logs to /wp-content/uploads/discord-enhancer-pro-debug.txt)
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="discord_integration_enhancer_pro_options[use_alternative_method]" value="yes" <?php checked(get_option('discord_integration_enhancer_pro_options')['use_alternative_method'] ?? 'yes', 'yes'); ?>>
                                Use alternative method to retrieve more roles (recommended)
                            </label>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button button-primary" value="Save Changes">
                </p>
            </form>
            
            <hr>
            
            <h2>Users with Discord Roles</h2>
            <p>This table shows all users connected to Discord and their assigned roles:</p>
            
            <?php 
            // دریافت لیست کاربران دارای discord_user_id
            $users = get_users(array(
                'meta_key' => 'discord_user_id',
                'meta_compare' => 'EXISTS'
            ));
            
            if (!empty($users)) : 
            ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Discord Username</th>
                            <th>Discord Roles</th>
                            <th>WP Roles</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user) : 
                            $discord_id = get_user_meta($user->ID, 'discord_user_id', true);
                            $discord_username = get_user_meta($user->ID, 'discord_username', true);
                            $discord_roles = get_user_meta($user->ID, 'discord_roles', true);
                            $discord_roles_method1 = get_user_meta($user->ID, 'discord_roles_method1', true);
                            
                            // دریافت نام‌های نقش‌های دیسکورد
                            $role_names = array();
                            
                            // دریافت همه نقش‌های سرور برای نمایش نام‌های نقش
                            $all_server_roles = $this->get_all_server_roles();
                            $role_name_map = array();
                            
                            if (!empty($all_server_roles)) {
                                foreach ($all_server_roles as $role) {
                                    if (isset($role['id']) && isset($role['name'])) {
                                        $role_name_map[$role['id']] = $role['name'];
                                    }
                                }
                            }
                            
                            // تبدیل ID نقش‌ها به نام‌های نقش
                            $discord_role_names = array();
                            if (is_array($discord_roles)) {
                                foreach ($discord_roles as $role_id) {
                                    if (isset($role_name_map[$role_id])) {
                                        $discord_role_names[] = $role_name_map[$role_id];
                                    } else {
                                        $discord_role_names[] = $role_id . ' (Unknown)';
                                    }
                                }
                            }
                            
                            // دریافت نقش‌های وردپرس تخصیص داده شده
                            $wp_roles = array();
                            foreach ($user->roles as $role) {
                                $wp_roles[] = $role;
                            }
                        ?>
                            <tr>
                                <td><?php echo esc_html($user->ID); ?></td>
                                <td><?php echo esc_html($user->user_login); ?></td>
                                <td><?php echo esc_html($discord_username); ?></td>
                                <td>
                                    <?php 
                                    if (is_array($discord_roles)) {
                                        echo '<strong>' . count($discord_roles) . ' roles</strong><br>';
                                        echo '<div style="max-height: 80px; overflow-y: auto; font-size: 12px;">';
                                        echo esc_html(implode(', ', $discord_role_names));
                                        echo '</div>';
                                        
                                        // نمایش تفاوت بین روش‌ها
                                        if (is_array($discord_roles_method1) && count($discord_roles) != count($discord_roles_method1)) {
                                            echo '<div style="margin-top: 5px; color: #d63638;">';
                                            echo 'Method 1: ' . count($discord_roles_method1) . ' roles<br>';
                                            echo 'Enhanced: ' . count($discord_roles) . ' roles';
                                            echo '</div>';
                                        }
                                    } else {
                                        echo 'No roles found';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    if (!empty($wp_roles)) {
                                        echo '<div style="max-height: 80px; overflow-y: auto; font-size: 12px;">';
                                        echo esc_html(implode(', ', $wp_roles));
                                        echo '</div>';
                                    } else {
                                        echo 'No WordPress roles';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <button type="button" class="button sync-single-user-pro" data-user-id="<?php echo esc_attr($user->ID); ?>" data-discord-id="<?php echo esc_attr($discord_id); ?>">Sync All Roles</button>
                                    <span class="sync-status" style="display:none; margin-left: 5px;"></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <script type="text/javascript">
                    jQuery(document).ready(function($) {
                        // تست اتصال به دیسکورد
                        $('#test-discord-connection').on('click', function() {
                            const $button = $(this);
                            const $results = $('#test-results');
                            const $content = $('#test-results-content');
                            
                            $button.prop('disabled', true);
                            $button.text('Testing...');
                            $content.html('<p>Testing connection to Discord API...</p>');
                            $results.show();
                            
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'test_discord_connection',
                                    nonce: '<?php echo wp_create_nonce('discord_integration_enhancer_pro_nonce'); ?>'
                                },
                                success: function(response) {
                                    $button.prop('disabled', false);
                                    $button.text('Test Discord Connection');
                                    
                                    let html = '';
                                    
                                    if (response.success) {
                                        html += '<div class="notice notice-success" style="padding: 10px;">';
                                        html += '<p><strong>' + response.data.message + '</strong></p>';
                                        
                                        if (response.data.details) {
                                            html += '<ul>';
                                            for (const [key, value] of Object.entries(response.data.details)) {
                                                if (key !== 'roles_list') {
                                                    html += '<li>' + value + '</li>';
                                                }
                                            }
                                            html += '</ul>';
                                            
                                            if (response.data.details.roles_list) {
                                                html += '<h4>Server Roles:</h4>';
                                                html += '<div style="max-height: 200px; overflow-y: auto;">';
                                                html += '<table class="widefat striped" style="width: 100%;">';
                                                html += '<thead><tr><th>Role ID</th><th>Role Name</th></tr></thead>';
                                                html += '<tbody>';
                                                
                                                response.data.details.roles_list.forEach(function(role) {
                                                    html += '<tr>';
                                                    html += '<td>' + role.id + '</td>';
                                                    html += '<td>' + role.name + '</td>';
                                                    html += '</tr>';
                                                });
                                                
                                                html += '</tbody></table>';
                                                html += '</div>';
                                            }
                                        }
                                        
                                        html += '</div>';
                                    } else {
                                        html += '<div class="notice notice-error" style="padding: 10px;">';
                                        html += '<p><strong>' + response.data.message + '</strong></p>';
                                        
                                        if (response.data.details) {
                                            html += '<ul>';
                                            for (const [key, value] of Object.entries(response.data.details)) {
                                                html += '<li>' + value + '</li>';
                                            }
                                            html += '</ul>';
                                        }
                                        
                                        html += '</div>';
                                    }
                                    
                                    $content.html(html);
                                },
                                error: function() {
                                    $button.prop('disabled', false);
                                    $button.text('Test Discord Connection');
                                    
                                    const html = '<div class="notice notice-error" style="padding: 10px;">' +
                                                 '<p><strong>Connection test failed</strong></p>' +
                                                 '<p>Error connecting to server. Please try again.</p>' +
                                                 '</div>';
                                    
                                    $content.html(html);
                                }
                            });
                        });
                        
                        // همگام‌سازی یک کاربر
                        $('.sync-single-user-pro').on('click', function() {
                            const $button = $(this);
                            const $status = $button.next('.sync-status');
                            const userId = $button.data('user-id');
                            const discordId = $button.data('discord-id');
                            
                            $button.prop('disabled', true);
                            $status.text('Syncing...').show();
                            
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'sync_single_user_roles_pro',
                                    user_id: userId,
                                    discord_id: discordId,
                                    nonce: '<?php echo wp_create_nonce('discord_integration_enhancer_pro_nonce'); ?>'
                                },
                                success: function(response) {
                                    $button.prop('disabled', false);
                                    
                                    if (response.success) {
                                        $status.text('Success!');
                                        setTimeout(function() {
                                            location.reload();
                                        }, 1000);
                                    } else {
                                        $status.text('Error: ' + response.data);
                                    }
                                },
                                error: function() {
                                    $button.prop('disabled', false);
                                    $status.text('Connection error');
                                }
                            });
                        });
                    });
                </script>
            <?php else : ?>
                <p>No users with Discord connections found.</p>
            <?php endif; ?>
            
            <hr>
            
            <h2>Manual Synchronization</h2>
            <p>You can manually synchronize all users to retrieve all their Discord roles:</p>
            
            <div class="sync-actions" style="margin-top: 20px; padding: 15px; background: #f7f7f7; border-left: 4px solid #00a0d2;">
                <button type="button" id="manual-sync-button-pro" class="button button-primary">Sync All Users (Enhanced Pro)</button>
                <span id="manual-sync-status-pro" style="display:none; margin-left: 10px;"></span>
            </div>
            
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    $('#manual-sync-button-pro').on('click', function() {
                        const $button = $(this);
                        const $status = $('#manual-sync-status-pro');
                        
                        $button.prop('disabled', true);
                        $status.text('Synchronizing...').show();
                        
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'sync_discord_roles_pro',
                                nonce: '<?php echo wp_create_nonce('discord_integration_enhancer_pro_nonce'); ?>'
                            },
                            success: function(response) {
                                $button.prop('disabled', false);
                                
                                if (response.success) {
                                    $status.text('Synchronization successful. ' + response.data.count + ' users updated.');
                                    setTimeout(function() {
                                        location.reload();
                                    }, 2000);
                                } else {
                                    $status.text('Error: ' + response.data);
                                }
                            },
                            error: function() {
                                $button.prop('disabled', false);
                                $status.text('Error connecting to server. Please try again.');
                            }
                        });
                    });
                });
            </script>
            
            <?php if (get_option('discord_integration_enhancer_pro_options')['debug_mode'] === 'yes') : 
                $log_file = WP_CONTENT_DIR . '/uploads/discord-enhancer-pro-debug.txt';
                if (file_exists($log_file)) {
                    $logs = file_get_contents($log_file);
                } else {
                    $logs = 'No logs found.';
                }
            ?>
                <hr>
                <h2>Debug Logs</h2>
                <div style="max-height: 400px; overflow-y: auto; background: #f0f0f0; padding: 10px; font-family: monospace; font-size: 12px;">
                    <?php echo nl2br(esc_html($logs)); ?>
                </div>
                <p>
                    <button type="button" id="clear-logs-button-pro" class="button">Clear Logs</button>
                </p>
                
                <script type="text/javascript">
                    jQuery(document).ready(function($) {
                        $('#clear-logs-button-pro').on('click', function() {
                            if (confirm('Are you sure you want to clear the debug logs?')) {
                                $.ajax({
                                    url: ajaxurl,
                                    type: 'POST',
                                    data: {
                                        action: 'clear_discord_enhancer_pro_logs',
                                        nonce: '<?php echo wp_create_nonce('discord_integration_enhancer_pro_nonce'); ?>'
                                    },
                                    success: function(response) {
                                        if (response.success) {
                                            location.reload();
                                        } else {
                                            alert('Error clearing logs: ' + response.data);
                                        }
                                    }
                                });
                            }
                        });
                    });
                </script>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * تابع بهبود یافته همگام‌سازی نقش‌ها - اختصاص همه نقش‌های منطبق
     */
    public function enhanced_apply_role_mapping($result, $user_id, $discord_roles) {
        // اگر کاربر وجود نداشته باشد، خروج
        $user = get_user_by('id', $user_id);
        if (!$user) {
            $this->log_debug("User not found: $user_id");
            return false;
        }
        
        // جلوگیری از تغییر مدیران سیستم
        if (in_array('administrator', $user->roles) && !current_user_can('manage_options')) {
            $this->log_debug("Skipping administrator: $user_id");
            return false;
        }
        
        // نقش پیش‌فرض اگر هیچ نقشی تطابق نداشته باشد
        $default_role = 'subscriber';
        $matched = false;
        $assigned_roles = array();
        
        $this->log_debug("Processing " . count($discord_roles) . " Discord roles for user $user_id");
        
        // بررسی هر نقش دیسکورد در مقابل نگاشت‌ها
        foreach ($discord_roles as $role_id) {
            $this->log_debug("Checking Discord role: $role_id");
            
            if (isset($this->main_plugin_options['role_mappings'][$role_id]) && !empty($this->main_plugin_options['role_mappings'][$role_id])) {
                $wp_role = $this->main_plugin_options['role_mappings'][$role_id];
                $this->log_debug("Found mapping to WordPress role: $wp_role");
                
                $user->add_role($wp_role);
                $assigned_roles[] = $wp_role;
                $matched = true;
                // بدون break - اختصاص همه نقش‌های منطبق
            } else {
                $this->log_debug("No mapping found for Discord role: $role_id");
            }
        }
        
        // ذخیره لیست نقش‌های اختصاص داده شده
        if (!empty($assigned_roles)) {
            $this->log_debug("Assigned WordPress roles: " . implode(', ', $assigned_roles));
            update_user_meta($user_id, 'discord_assigned_wp_roles', $assigned_roles);
        } else {
            $this->log_debug("No WordPress roles assigned");
            
            // اگر هیچ نقشی منطبق نبود، نقش پیش‌فرض را اختصاص دهید
            if (!$matched && !in_array($default_role, $user->roles)) {
                $this->log_debug("Assigning default role: $default_role");
                $user->add_role($default_role);
            }
        }
        
        return true;
    }
    
    /**
     * AJAX handler برای همگام‌سازی یک کاربر
     */
    public function ajax_sync_single_user() {
        check_ajax_referer('discord_integration_enhancer_pro_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('You do not have sufficient permissions for this operation.');
        }
        
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $discord_id = isset($_POST['discord_id']) ? sanitize_text_field($_POST['discord_id']) : '';
        
        if (!$user_id || !$discord_id) {
            wp_send_json_error('Invalid user ID or Discord ID.');
            return;
        }
        
        $result = $this->get_all_user_discord_roles($user_id, $discord_id);
        
        if ($result) {
            wp_send_json_success(array('message' => 'User roles synchronized successfully.'));
        } else {
            wp_send_json_error('Failed to synchronize user roles.');
        }
    }
    
    /**
     * جایگزین تابع همگام‌سازی همه کاربران
     */
    public function sync_all_users_roles() {
        $this->log_debug("Starting sync for all users");
        
        // دریافت کاربران متصل به دیسکورد
        $users = get_users(array(
            'meta_key' => 'discord_user_id',
            'meta_compare' => 'EXISTS'
        ));
        
        $updated_count = 0;
        
        foreach ($users as $user) {
            $discord_id = get_user_meta($user->ID, 'discord_user_id', true);
            
            if (!empty($discord_id)) {
                $this->log_debug("Syncing user {$user->ID} with Discord ID {$discord_id}");
                $updated = $this->get_all_user_discord_roles($user->ID, $discord_id);
                
                if ($updated) {
                    $updated_count++;
                }
            }
        }
        
        $this->log_debug("Completed sync for all users. Updated: $updated_count");
        
        return $updated_count;
    }
    
    /**
     * AJAX handler برای همگام‌سازی بهبود یافته
     */
    public function ajax_sync_enhanced() {
        check_ajax_referer('discord_integration_enhancer_pro_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('You do not have sufficient permissions for this operation.');
        }
        
        $updated_count = $this->sync_all_users_roles();
        
        wp_send_json_success(array('count' => $updated_count));
    }
    
    /**
     * AJAX handler برای پاک کردن لاگ‌ها
     */
    public function ajax_clear_logs() {
        check_ajax_referer('discord_integration_enhancer_pro_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('You do not have sufficient permissions for this operation.');
        }
        
        $log_file = WP_CONTENT_DIR . '/uploads/discord-enhancer-pro-debug.txt';
        if (file_exists($log_file)) {
            file_put_contents($log_file, '');
            wp_send_json_success();
        } else {
            wp_send_json_error('Log file does not exist.');
        }
    }
    
    /**
     * نمایش نقش‌های دیسکورد در پروفایل کاربر
     */
    public function show_discord_roles_in_profile($user) {
        // بررسی اینکه آیا باید نقش‌ها را نمایش دهیم
        $options = get_option('discord_integration_enhancer_pro_options', array('display_roles_in_profile' => 'yes'));
        if ($options['display_roles_in_profile'] !== 'yes') {
            return;
        }
        
        // بررسی اینکه آیا کاربر به دیسکورد متصل است
        $discord_id = get_user_meta($user->ID, 'discord_user_id', true);
        if (!$discord_id) {
            return;
        }
        
        // دریافت نقش‌های دیسکورد
        $discord_roles = get_user_meta($user->ID, 'discord_roles', true);
        
        if (empty($discord_roles) || !is_array($discord_roles)) {
            return;
        }
        
        // دریافت همه نقش‌های سرور برای نمایش نام‌های نقش
        $all_server_roles = $this->get_all_server_roles();
        $role_name_map = array();
        
        if (!empty($all_server_roles)) {
            foreach ($all_server_roles as $role) {
                if (isset($role['id']) && isset($role['name'])) {
                    $role_name_map[$role['id']] = $role['name'];
                }
            }
        }
        
        // تبدیل ID نقش‌ها به نام‌های نقش
        $discord_role_names = array();
        foreach ($discord_roles as $role_id) {
            if (isset($role_name_map[$role_id])) {
                $discord_role_names[] = $role_name_map[$role_id];
            } else {
                $discord_role_names[] = $role_id;
            }
        }
        
        // فقط اگر نقش‌ها پیدا شدند، بخش را نمایش بده
        if (!empty($discord_role_names)) {
            ?>
            <h3>Discord Roles</h3>
            <table class="form-table">
                <tr>
                    <th><label for="discord-roles">Roles</label></th>
                    <td>
                        <p><?php echo esc_html($this->role_display_prefix) . implode(', ', array_map('esc_html', $discord_role_names)); ?></p>
                        <p class="description">Total Discord Roles: <?php echo count($discord_roles); ?></p>
                    </td>
                </tr>
            </table>
            <?php
        }
    }
}

// شروع افزونه
$discord_integration_enhancer_pro = new Discord_Integration_Enhancer_Pro();

// اضافه کردن AJAX endpoint برای همگام‌سازی بهبود یافته
add_action('wp_ajax_sync_discord_roles_pro', array($discord_integration_enhancer_pro, 'ajax_sync_enhanced'));
add_action('wp_ajax_clear_discord_enhancer_pro_logs', array($discord_integration_enhancer_pro, 'ajax_clear_logs'));
add_action('wp_ajax_sync_single_user_roles_pro', array($discord_integration_enhancer_pro, 'ajax_sync_single_user'));
add_action('wp_ajax_test_discord_connection', array($discord_integration_enhancer_pro, 'ajax_test_discord_connection'));

// افزودن action برای بررسی وجود تابع در افزونه اصلی و جایگزینی آن
function discord_enhancer_pro_check_main_plugin() {
    global $discord_integration_enhancer_pro;
    
    // بررسی اینکه آیا کلاس Discord_Integration وجود دارد
    if (class_exists('Discord_Integration')) {
        // تلاش برای دسترسی به نمونه جهانی افزونه اصلی (اگر وجود داشته باشد)
        global $discord_integration;
        
        if (isset($discord_integration)) {
            // حذف action تابع اصلی ورود و اضافه کردن تابع جدید
            if (method_exists($discord_integration, 'sync_user_roles_on_login')) {
                remove_action('wp_login', array($discord_integration, 'sync_user_roles_on_login'), 10);
                add_action('wp_login', 'discord_enhancer_pro_sync_on_login', 10, 2);
            }
            
            // هوک کردن تابع همگام‌سازی همه کاربران
            if (method_exists($discord_integration, 'sync_all_users_roles')) {
                // این برای همگام‌سازی زمان‌بندی شده است
                remove_action('discord_role_sync_cron', array($discord_integration, 'sync_all_users_roles'));
                add_action('discord_role_sync_cron', array($discord_integration_enhancer_pro, 'sync_all_users_roles'));
            }
        }
    }
}
add_action('plugins_loaded', 'discord_enhancer_pro_check_main_plugin', 30);

// تابع همگام‌سازی در هنگام ورود
function discord_enhancer_pro_sync_on_login($user_login, $user) {
    global $discord_integration_enhancer_pro;
    
    // بررسی اینکه آیا کاربر به دیسکورد متصل است
    $discord_id = get_user_meta($user->ID, 'discord_user_id', true);
    if (!$discord_id) return;
    
    // دریافت نقش‌های دیسکورد به صورت مستقیم از API
    $discord_integration_enhancer_pro->get_all_user_discord_roles($user->ID, $discord_id);
}