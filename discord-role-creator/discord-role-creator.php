<?php
/**
 * Plugin Name: Discord Role Creator for WordPress
 * Plugin URI: https://yourwebsite.com/
 * Description: Automatically creates WordPress roles based on Discord roles
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: discord-role-creator
 */

if (!defined('ABSPATH')) exit;

class Discord_Role_Creator {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_submenu_page'));
        add_action('admin_init', array($this, 'create_roles_if_requested'));
        add_action('admin_notices', array($this, 'display_admin_notices'));
    }
    
    public function add_submenu_page() {
        // اضافه کردن زیرمنو به افزونه Discord Integration
        add_submenu_page(
            'discord-integration',
            'Create Discord Roles',
            'Create Discord Roles',
            'manage_options',
            'discord-role-creator',
            array($this, 'admin_page')
        );
    }
    
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <p>This tool allows you to automatically create WordPress roles for each role in your Discord server.</p>
            
            <form method="post" action="">
                <?php wp_nonce_field('create_discord_roles', 'discord_role_nonce'); ?>
                
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Role Prefix</th>
                        <td>
                            <input type="text" name="role_prefix" value="discord_" class="regular-text">
                            <p class="description">Prefix to add to WordPress role ID (to avoid conflicts with existing roles).</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Role Display Name Prefix</th>
                        <td>
                            <input type="text" name="display_prefix" value="Discord: " class="regular-text">
                            <p class="description">Prefix to add to the display name of WordPress roles. Make sure to include a space at the end if needed.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Options</th>
                        <td>
                            <label>
                                <input type="checkbox" name="overwrite_existing" value="1">
                                Overwrite existing roles with the same name
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="add_mappings" value="1" checked>
                                Automatically add role mappings to Discord Integration
                            </label>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Base Capabilities</th>
                        <td>
                            <label>
                                <input type="checkbox" name="caps[read]" value="1" checked>
                                read - Allow users to read posts/pages
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="caps[edit_posts]" value="1">
                                edit_posts - Allow users to edit their own posts
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="caps[upload_files]" value="1">
                                upload_files - Allow users to upload files
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="caps[publish_posts]" value="1">
                                publish_posts - Allow users to publish posts
                            </label>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="create_discord_roles" class="button button-primary" value="Create WordPress Roles from Discord">
                </p>
            </form>
            
            <hr>
            
            <h2>Discord Role to WordPress Role Mapping</h2>
            <p>After creating the roles, you can use the Discord Integration plugin to map Discord roles to WordPress roles.</p>
            <p><a href="<?php echo esc_url(admin_url('admin.php?page=discord-integration&tab=roles')); ?>" class="button">Go to Role Mapping</a></p>
        </div>
        <?php
    }
    
    public function create_roles_if_requested() {
        if (isset($_POST['create_discord_roles']) && 
            isset($_POST['discord_role_nonce']) && 
            wp_verify_nonce($_POST['discord_role_nonce'], 'create_discord_roles')) {
            
            $overwrite = isset($_POST['overwrite_existing']) ? true : false;
            $add_mappings = isset($_POST['add_mappings']) ? true : false;
            $role_prefix = sanitize_text_field($_POST['role_prefix']);
            $display_prefix = sanitize_text_field($_POST['display_prefix']);
            
            // دریافت قابلیت‌های پایه از فرم
            $base_caps = array();
            if (isset($_POST['caps']) && is_array($_POST['caps'])) {
                foreach ($_POST['caps'] as $cap => $value) {
                    $base_caps[sanitize_key($cap)] = (bool) $value;
                }
            }
            
            $result = $this->create_roles($overwrite, $add_mappings, $role_prefix, $display_prefix, $base_caps);
            
            // ذخیره پیام‌ها برای نمایش
            update_option('discord_role_creator_messages', $result);
            
            // ریدایرکت برای جلوگیری از ارسال مجدد فرم
            wp_redirect(add_query_arg('page', 'discord-role-creator', admin_url('admin.php')));
            exit;
        }
    }
    
    public function display_admin_notices() {
        $messages = get_option('discord_role_creator_messages', array());
        
        if (!empty($messages) && isset($_GET['page']) && $_GET['page'] === 'discord-role-creator') {
            foreach ($messages as $message) {
                echo '<div class="notice notice-' . esc_attr($message['type']) . ' is-dismissible"><p>' . esc_html($message['message']) . '</p></div>';
            }
            
            // پاک کردن پیام‌ها بعد از نمایش
            delete_option('discord_role_creator_messages');
        }
    }
    
    private function create_roles($overwrite = false, $add_mappings = true, $role_prefix = 'discord_', $display_prefix = 'Discord: ', $base_caps = array()) {
        // دریافت تنظیمات افزونه Discord Integration
        $options = get_option('discord_integration_options', array());
        $messages = array();
        
        // بررسی اینکه آیا اطلاعات لازم وجود دارد
        if (empty($options['guild_id']) || empty($options['bot_token'])) {
            $messages[] = array(
                'type' => 'error',
                'message' => 'Guild ID or Bot Token not configured in Discord Integration plugin.'
            );
            return $messages;
        }
        
        // اگر هیچ قابلیتی انتخاب نشده باشد، حداقل قابلیت خواندن را اضافه کنید
        if (empty($base_caps)) {
            $base_caps = array('read' => true);
        }
        
        $guild_id = $options['guild_id'];
        $bot_token = $options['bot_token'];
        
        // دریافت نقش‌های دیسکورد از API
        $response = wp_remote_get("https://discord.com/api/guilds/{$guild_id}/roles", array(
            'headers' => array('Authorization' => "Bot {$bot_token}")
        ));
        
        if (is_wp_error($response)) {
            $messages[] = array(
                'type' => 'error',
                'message' => 'Error connecting to Discord API: ' . $response->get_error_message()
            );
            return $messages;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $messages[] = array(
                'type' => 'error',
                'message' => 'Discord API error: HTTP ' . $status_code
            );
            return $messages;
        }
        
        $roles = json_decode(wp_remote_retrieve_body($response), true);
        
        // حذف نقش @everyone
        $roles = array_filter($roles, function($role) {
            return $role['name'] !== '@everyone';
        });
        
        // دریافت نقش‌های فعلی وردپرس
        $wp_roles = wp_roles();
        $created_roles = array();
        $updated_roles = array();
        $role_mappings = isset($options['role_mappings']) ? $options['role_mappings'] : array();
        
        // ایجاد نقش در وردپرس برای هر نقش دیسکورد
        foreach ($roles as $role) {
            $sanitized_name = sanitize_title($role['name']);
            $role_name = $role_prefix . $sanitized_name; // پیشوند برای جلوگیری از تداخل
            
            // اطمینان از وجود فاصله بین پیشوند و نام نقش
            $display_prefix_with_space = $display_prefix;
            if (!empty($display_prefix) && substr($display_prefix, -1) !== ' ' && !empty($role['name'])) {
                $display_prefix_with_space .= ' ';
            }
            $role_display_name = $display_prefix_with_space . $role['name'];
            
            // بررسی اینکه آیا نقش قبلاً وجود دارد
            if (!$wp_roles->is_role($role_name)) {
                // ایجاد نقش جدید با قابلیت‌های پایه
                add_role($role_name, $role_display_name, $base_caps);
                $created_roles[] = $role_display_name;
                
                // اضافه کردن نگاشت نقش به تنظیمات Discord Integration
                if ($add_mappings) {
                    $role_mappings[$role['id']] = $role_name;
                }
            } elseif ($overwrite) {
                // بروزرسانی نقش موجود
                $current_role = get_role($role_name);
                $current_caps = $current_role->capabilities;
                
                // حذف نقش موجود و ایجاد مجدد آن
                remove_role($role_name);
                
                // ترکیب قابلیت‌های موجود با قابلیت‌های جدید
                $combined_caps = array_merge($current_caps, $base_caps);
                add_role($role_name, $role_display_name, $combined_caps);
                
                $updated_roles[] = $role_display_name;
                
                // اضافه کردن نگاشت نقش به تنظیمات Discord Integration
                if ($add_mappings) {
                    $role_mappings[$role['id']] = $role_name;
                }
            }
        }
        
        // بروزرسانی نگاشت نقش‌های Discord Integration
        if ($add_mappings && (!empty($created_roles) || !empty($updated_roles))) {
            $options['role_mappings'] = $role_mappings;
            update_option('discord_integration_options', $options);
        }
        
        // ایجاد پیام‌های موفقیت‌آمیز
        if (!empty($created_roles) || !empty($updated_roles)) {
            if (!empty($created_roles)) {
                $messages[] = array(
                    'type' => 'success',
                    'message' => sprintf(
                        'Created %d new WordPress roles: %s',
                        count($created_roles),
                        implode(', ', $created_roles)
                    )
                );
            }
            
            if (!empty($updated_roles)) {
                $messages[] = array(
                    'type' => 'success',
                    'message' => sprintf(
                        'Updated %d existing WordPress roles: %s',
                        count($updated_roles),
                        implode(', ', $updated_roles)
                    )
                );
            }
            
            if ($add_mappings) {
                $messages[] = array(
                    'type' => 'success',
                    'message' => 'Role mappings have been automatically added to Discord Integration.'
                );
            }
        } else {
            $messages[] = array(
                'type' => 'info',
                'message' => 'No new WordPress roles needed to be created.'
            );
        }
        
        return $messages;
    }
}

// شروع افزونه
new Discord_Role_Creator();