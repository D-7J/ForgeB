<?php
/**
 * Plugin Name: Discord Integration Enhancer
 * Plugin URI: https://forge-boost.com/
 * Description: Enhances the Discord Integration plugin to assign all user roles from Discord and display them properly
 * Version: 1.0.2
 * Author: d4wood
 * Text Domain: discord-integration-enhancer
 * Requires Plugins: discord-integration
 */

if (!defined('ABSPATH')) exit;

class Discord_Integration_Enhancer {
    // افزونه اصلی Discord Integration
    private $main_plugin_options;
    
    // پیشوند نمایش نقش
    private $role_display_prefix = 'Discord: ';
    
    // اضافه کردن متغیر برای ذخیره رکورد رویدادها (logging)
    private $debug_log = array();
    
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
        add_action('wp_ajax_sync_single_user_roles', array($this, 'ajax_sync_single_user'));
        
        // AJAX endpoint برای همگام‌سازی بهبود یافته
        add_action('wp_ajax_sync_discord_roles_enhanced', array($this, 'ajax_sync_enhanced'));
        
        // جایگزینی فرآیند ورود با دیسکورد
        add_action('init', array($this, 'hook_into_discord_login_process'), 5);
        
        // بارگذاری تنظیمات
        $this->load_settings();
    }
    
    /**
     * بارگذاری تنظیمات افزونه
     */
    private function load_settings() {
        $options = get_option('discord_integration_enhancer_options', array(
            'role_display_prefix' => 'Discord: ',
            'display_roles_in_profile' => 'yes',
            'debug_mode' => 'no'
        ));
        
        $this->role_display_prefix = isset($options['role_display_prefix']) ? $options['role_display_prefix'] : 'Discord: ';
        $this->main_plugin_options = get_option('discord_integration_options', array());
    }
    
    /**
     * ثبت رکورد رویدادها (logging)
     */
    private function log_debug($message) {
        $options = get_option('discord_integration_enhancer_options', array());
        $debug_mode = isset($options['debug_mode']) ? $options['debug_mode'] : 'no';
        
        if ($debug_mode === 'yes') {
            $log_file = WP_CONTENT_DIR . '/uploads/discord-enhancer-debug.txt';
            $timestamp = date('[Y-m-d H:i:s] ');
            file_put_contents($log_file, $timestamp . $message . "\n", FILE_APPEND);
            
            $this->debug_log[] = $timestamp . $message;
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
                echo '<div class="error"><p>Discord Integration Enhancer requires the Discord Integration plugin to be installed and activated.</p></div>';
            });
            
            deactivate_plugins(plugin_basename(__FILE__));
            
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
                
                // دریافت نقش‌های کاربر با استفاده از API دیسکورد
                $this->get_user_discord_roles_directly($user_id, $discord_id);
            }
        }
    }
    
    /**
     * دریافت مستقیم نقش‌های کاربر از دیسکورد با استفاده از API
     */
    public function get_user_discord_roles_directly($user_id, $discord_id) {
        if (empty($this->main_plugin_options['guild_id']) || empty($this->main_plugin_options['bot_token'])) {
            $this->log_debug("Missing guild_id or bot_token in settings");
            return false;
        }
        
        $guild_id = $this->main_plugin_options['guild_id'];
        $bot_token = $this->main_plugin_options['bot_token'];
        
        // درخواست به API دیسکورد برای دریافت اطلاعات عضو
        $response = wp_remote_get("https://discord.com/api/guilds/{$guild_id}/members/{$discord_id}", array(
            'headers' => array(
                'Authorization' => "Bot {$bot_token}",
                'Content-Type' => 'application/json'
            ),
            'timeout' => 15 // افزایش زمان انتظار برای جلوگیری از timeout
        ));
        
        if (is_wp_error($response)) {
            $this->log_debug("Error in API request: " . $response->get_error_message());
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code !== 200) {
            $this->log_debug("Discord API error: HTTP $status_code");
            $this->log_debug("Response: " . wp_remote_retrieve_body($response));
            return false;
        }
        
        $member_data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($member_data['roles']) || !is_array($member_data['roles'])) {
            $this->log_debug("No roles found in member data");
            return false;
        }
        
        // ذخیره تمام نقش‌های دیسکورد
        $discord_roles = $member_data['roles'];
        $this->log_debug("Found " . count($discord_roles) . " roles for user: " . implode(', ', $discord_roles));
        
        // ذخیره نقش‌ها در متادیتای کاربر
        update_user_meta($user_id, 'discord_roles', $discord_roles);
        
        // اعمال همه نقش‌های مرتبط
        $this->enhanced_apply_role_mapping(false, $user_id, $discord_roles);
        
        return true;
    }
    
    /**
     * اضافه کردن منوی مستقل
     */
    public function add_menu_page() {
        add_menu_page(
            'Discord Integration Enhancer',
            'Discord Enhancer',
            'manage_options',
            'discord-integration-enhancer',
            array($this, 'admin_page'),
            'dashicons-superhero',
            101 // بعد از Discord Integration
        );
    }
    
    /**
     * ثبت تنظیمات
     */
    public function register_settings() {
        register_setting(
            'discord_integration_enhancer_group',
            'discord_integration_enhancer_options',
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
        
        return $valid;
    }
    
    /**
     * صفحه تنظیمات افزونه
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('discord_integration_enhancer_group'); ?>
                
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Role Display Prefix</th>
                        <td>
                            <input type="text" name="discord_integration_enhancer_options[role_display_prefix]" value="<?php echo esc_attr($this->role_display_prefix); ?>" class="regular-text">
                            <p class="description">Prefix to add when displaying Discord roles in user profile.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Display Options</th>
                        <td>
                            <label>
                                <input type="checkbox" name="discord_integration_enhancer_options[display_roles_in_profile]" value="yes" <?php checked(get_option('discord_integration_enhancer_options')['display_roles_in_profile'], 'yes'); ?>>
                                Show Discord roles in user profile
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="discord_integration_enhancer_options[debug_mode]" value="yes" <?php checked(get_option('discord_integration_enhancer_options')['debug_mode'] ?? 'no', 'yes'); ?>>
                                Enable debug mode (saves logs to /wp-content/uploads/discord-enhancer-debug.txt)
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
                            <th>WordPress Roles</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user) : 
                            $discord_id = get_user_meta($user->ID, 'discord_user_id', true);
                            $discord_username = get_user_meta($user->ID, 'discord_username', true);
                            $discord_roles = get_user_meta($user->ID, 'discord_roles', true);
                            $role_names = array();
                            
                            if (is_array($discord_roles)) {
                                foreach ($discord_roles as $role_id) {
                                    foreach ($this->main_plugin_options['role_mappings'] as $discord_role_id => $wp_role) {
                                        if ($discord_role_id === $role_id) {
                                            $role_obj = get_role($wp_role);
                                            if ($role_obj) {
                                                $wp_roles = wp_roles();
                                                $role_name = isset($wp_roles->role_names[$wp_role]) ? $wp_roles->role_names[$wp_role] : $wp_role;
                                                $role_name = str_replace($this->role_display_prefix, '', $role_name);
                                                $role_names[] = $role_name;
                                            }
                                        }
                                    }
                                }
                            }
                        ?>
                            <tr>
                                <td><?php echo esc_html($user->ID); ?></td>
                                <td><?php echo esc_html($user->user_login); ?></td>
                                <td><?php echo esc_html($discord_username); ?></td>
                                <td><?php echo is_array($discord_roles) ? count($discord_roles) : 0; ?> roles</td>
                                <td><?php echo !empty($role_names) ? esc_html(implode(', ', $role_names)) : 'None'; ?></td>
                                <td>
                                    <button type="button" class="button sync-single-user" data-user-id="<?php echo esc_attr($user->ID); ?>" data-discord-id="<?php echo esc_attr($discord_id); ?>">Sync Roles</button>
                                    <span class="sync-status" style="display:none; margin-left: 5px;"></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <script type="text/javascript">
                    jQuery(document).ready(function($) {
                        $('.sync-single-user').on('click', function() {
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
                                    action: 'sync_single_user_roles',
                                    user_id: userId,
                                    discord_id: discordId,
                                    nonce: '<?php echo wp_create_nonce('discord_integration_enhancer_nonce'); ?>'
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
            <p>You can manually synchronize all users from the Discord Integration plugin or use the button below:</p>
            
            <div class="sync-actions" style="margin-top: 20px; padding: 15px; background: #f7f7f7; border-left: 4px solid #00a0d2;">
                <button type="button" id="manual-sync-button-enhanced" class="button button-primary">Manually Sync All Users (Enhanced)</button>
                <span id="manual-sync-status-enhanced" style="display:none; margin-left: 10px;"></span>
            </div>
            
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    $('#manual-sync-button-enhanced').on('click', function() {
                        const $button = $(this);
                        const $status = $('#manual-sync-status-enhanced');
                        
                        $button.prop('disabled', true);
                        $status.text('Synchronizing...').show();
                        
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'sync_discord_roles_enhanced',
                                nonce: '<?php echo wp_create_nonce('discord_integration_enhancer_nonce'); ?>'
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
            
            <?php if (get_option('discord_integration_enhancer_options')['debug_mode'] === 'yes') : 
                $log_file = WP_CONTENT_DIR . '/uploads/discord-enhancer-debug.txt';
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
                    <button type="button" id="clear-logs-button" class="button">Clear Logs</button>
                </p>
                
                <script type="text/javascript">
                    jQuery(document).ready(function($) {
                        $('#clear-logs-button').on('click', function() {
                            if (confirm('Are you sure you want to clear the debug logs?')) {
                                $.ajax({
                                    url: ajaxurl,
                                    type: 'POST',
                                    data: {
                                        action: 'clear_discord_enhancer_logs',
                                        nonce: '<?php echo wp_create_nonce('discord_integration_enhancer_nonce'); ?>'
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
     * جایگزین تابع همگام‌سازی یک کاربر از افزونه اصلی
     */
    public function sync_user_roles($user_id, $discord_id = null) {
        $this->log_debug("Starting sync for user $user_id");
        
        if (!$discord_id) {
            $discord_id = get_user_meta($user_id, 'discord_user_id', true);
            
            if (!$discord_id) {
                $this->log_debug("No Discord ID found for user $user_id");
                return false; // کاربر به دیسکورد متصل نیست
            }
        }
        
        return $this->get_user_discord_roles_directly($user_id, $discord_id);
    }
    
    /**
     * AJAX handler برای همگام‌سازی یک کاربر
     */
    public function ajax_sync_single_user() {
        check_ajax_referer('discord_integration_enhancer_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('You do not have sufficient permissions for this operation.');
        }
        
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $discord_id = isset($_POST['discord_id']) ? sanitize_text_field($_POST['discord_id']) : '';
        
        if (!$user_id || !$discord_id) {
            wp_send_json_error('Invalid user ID or Discord ID.');
            return;
        }
        
        $result = $this->sync_user_roles($user_id, $discord_id);
        
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
                $updated = $this->sync_user_roles($user->ID, $discord_id);
                
                if ($updated) {
                    $updated_count++;
                }
            }
        }
        
        $this->log_debug("Completed sync for all users. Updated: $updated_count");
        
        return $updated_count;
    }
    
    /**
     * جایگزین AJAX handler برای همگام‌سازی دستی
     */
    public function ajax_sync_all_users() {
        check_ajax_referer('discord_integration_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('You do not have sufficient permissions for this operation.');
        }
        
        $updated_count = $this->sync_all_users_roles();
        
        wp_send_json_success(array('count' => $updated_count));
    }
    
    /**
     * AJAX handler برای همگام‌سازی بهبود یافته
     */
    public function ajax_sync_enhanced() {
        check_ajax_referer('discord_integration_enhancer_nonce', 'nonce');
        
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
        check_ajax_referer('discord_integration_enhancer_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('You do not have sufficient permissions for this operation.');
        }
        
        $log_file = WP_CONTENT_DIR . '/uploads/discord-enhancer-debug.txt';
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
        $options = get_option('discord_integration_enhancer_options', array('display_roles_in_profile' => 'yes'));
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
        
        // ساخت لیست نام‌های نقش‌های دیسکورد
        $role_names = array();
        foreach ($discord_roles as $role_id) {
            // تلاش برای یافتن نام نقش از مپینگ نقش‌ها
            foreach ($this->main_plugin_options['role_mappings'] as $discord_role_id => $wp_role) {
                if ($discord_role_id === $role_id) {
                    // استخراج نام نقش از slug آن
                    $role_obj = get_role($wp_role);
                    if ($role_obj) {
                        $wp_roles = wp_roles();
                        $role_name = isset($wp_roles->role_names[$wp_role]) ? $wp_roles->role_names[$wp_role] : $wp_role;
                        
                        // حذف پیشوند "Discord: " اگر در نام نقش وجود دارد
                        $role_name = str_replace($this->role_display_prefix, '', $role_name);
                        $role_names[] = $role_name;
                    }
                }
            }
        }
        
        // فقط اگر نقش‌ها پیدا شدند، بخش را نمایش بده
        if (!empty($role_names)) {
            ?>
            <h3>Discord Roles</h3>
            <table class="form-table">
                <tr>
                    <th><label for="discord-roles">Roles</label></th>
                    <td>
                        <p><?php echo esc_html($this->role_display_prefix) . implode(', ', array_map('esc_html', $role_names)); ?></p>
                        <p class="description">Total Discord Roles: <?php echo count($discord_roles); ?></p>
                    </td>
                </tr>
            </table>
            <?php
        }
    }
}

// شروع افزونه
$discord_integration_enhancer = new Discord_Integration_Enhancer();

// اضافه کردن AJAX endpoint برای همگام‌سازی بهبود یافته
add_action('wp_ajax_sync_discord_roles_enhanced', array($discord_integration_enhancer, 'ajax_sync_enhanced'));
add_action('wp_ajax_clear_discord_enhancer_logs', array($discord_integration_enhancer, 'ajax_clear_logs'));

/**
 * جلوگیری از اجرای تابع اصلی افزونه و استفاده از تابع بهبود یافته
 */
if (!function_exists('discord_integration_apply_role_mapping_filter')) {
    function discord_integration_apply_role_mapping_filter($user_id, $discord_roles) {
        return apply_filters('discord_integration_apply_role_mapping', false, $user_id, $discord_roles);
    }
    
    // اضافه کردن فیلتر برای جایگزینی تابع اصلی
    add_filter('discord_integration_apply_role_mapping', 'discord_integration_apply_role_mapping_filter', 5, 2);
}

// افزودن action برای بررسی وجود تابع در افزونه اصلی و جایگزینی آن
function discord_enhancer_check_main_plugin() {
    global $discord_integration_enhancer;
    
    // بررسی اینکه آیا کلاس Discord_Integration وجود دارد
    if (class_exists('Discord_Integration')) {
        // تلاش برای دسترسی به نمونه جهانی افزونه اصلی (اگر وجود داشته باشد)
        global $discord_integration;
        
        if (isset($discord_integration)) {
            // حذف action تابع اصلی ورود و اضافه کردن تابع جدید
            if (method_exists($discord_integration, 'sync_user_roles_on_login')) {
                remove_action('wp_login', array($discord_integration, 'sync_user_roles_on_login'), 10);
                add_action('wp_login', 'discord_enhancer_sync_on_login', 10, 2);
            }
            
            // هوک کردن تابع همگام‌سازی همه کاربران
            if (method_exists($discord_integration, 'sync_all_users_roles')) {
                // این برای همگام‌سازی زمان‌بندی شده است
                remove_action('discord_role_sync_cron', array($discord_integration, 'sync_all_users_roles'));
                add_action('discord_role_sync_cron', array($discord_integration_enhancer, 'sync_all_users_roles'));
            }
        }
    }
}
add_action('plugins_loaded', 'discord_enhancer_check_main_plugin', 20);

// تابع همگام‌سازی در هنگام ورود
function discord_enhancer_sync_on_login($user_login, $user) {
    global $discord_integration_enhancer;
    
    // بررسی اینکه آیا کاربر به دیسکورد متصل است
    $discord_id = get_user_meta($user->ID, 'discord_user_id', true);
    if (!$discord_id) return;
    
    // دریافت نقش‌های دیسکورد به صورت مستقیم از API
    $discord_integration_enhancer->get_user_discord_roles_directly($user->ID, $discord_id);
}