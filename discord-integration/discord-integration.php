<?php
/**
 * Plugin Name: Discord Integration for WordPress
 * Plugin URI: https://forge-boost.com/
 * Description: Comprehensive Discord integration - Login with Discord, assign and sync WordPress roles based on Discord roles
 * Version: 1.0.0
 * Author: d4wood
 * Text Domain: discord-integration
 */

if (!defined('ABSPATH')) exit;

class Discord_Integration {
    // Plugin properties
    private $options;
    private $plugin_name = 'discord-integration';
    private $version = '1.0.0';
    
    // Discord OAuth2 properties
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    private $bot_token;
    private $guild_id;
    private $role_mappings = array();
    private $redirect_after_login;

    /**
     * Constructor - Set up the plugin
     */
    public function __construct() {
        // Set up necessary hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // Login with Discord
        add_shortcode('discord_login', array($this, 'discord_login_button'));
        add_action('init', array($this, 'process_discord_login'));
        add_shortcode('discord_auth_callback', array($this, 'discord_callback_shortcode'));
        
        // Role synchronization
        add_action('wp_login', array($this, 'sync_user_roles_on_login'), 10, 2);
        add_action('discord_role_sync_cron', array($this, 'sync_all_users_roles'));
        
        // AJAX handlers
        add_action('wp_ajax_get_discord_roles', array($this, 'ajax_get_discord_roles'));
        add_action('wp_ajax_sync_discord_roles_manually', array($this, 'ajax_sync_all_users'));
        add_action('wp_ajax_clear_discord_integration_logs', array($this, 'ajax_clear_logs'));
        
        // Register activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Add settings link to plugin page
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
        
        // Load settings
        $this->load_settings();
    }

    /**
     * Discord callback shortcode for the callback page
     */
    public function discord_callback_shortcode() {
        // This shortcode doesn't output anything visible
        // It's just a marker for the callback page
        return '<div id="discord-auth-callback" style="display:none;">Processing Discord authentication...</div>';
    }

    /**
     * Load plugin settings
     */
    private function load_settings() {
        $this->options = get_option('discord_integration_options', array(
            'client_id' => '',
            'client_secret' => '',
            'redirect_uri' => site_url('/discord-callback'),
            'bot_token' => '',
            'guild_id' => '',
            'role_mappings' => array(),
            'sync_frequency' => 'daily',
            'redirect_after_login' => home_url(),
            'debug_mode' => false
        ));
        
        $this->client_id = !empty($this->options['client_id']) ? $this->options['client_id'] : '';
        $this->client_secret = !empty($this->options['client_secret']) ? $this->options['client_secret'] : '';
        $this->redirect_uri = !empty($this->options['redirect_uri']) ? $this->options['redirect_uri'] : site_url('/discord-callback');
        $this->bot_token = !empty($this->options['bot_token']) ? $this->options['bot_token'] : '';
        $this->guild_id = !empty($this->options['guild_id']) ? $this->options['guild_id'] : '';
        $this->role_mappings = !empty($this->options['role_mappings']) && is_array($this->options['role_mappings']) ? $this->options['role_mappings'] : array();
        $this->redirect_after_login = !empty($this->options['redirect_after_login']) ? $this->options['redirect_after_login'] : home_url();
    }

    /**
     * Debug logging
     */
    private function log_debug($message) {
        if (!empty($this->options['debug_mode'])) {
            $log_file = WP_CONTENT_DIR . '/uploads/discord-integration-debug.txt';
            file_put_contents($log_file, date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
            error_log('[DiscordIntegration] ' . $message);
        }
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Schedule cron job
        if (!wp_next_scheduled('discord_role_sync_cron')) {
            wp_schedule_event(time(), 'daily', 'discord_role_sync_cron');
        }
        
        // Create a dedicated page for Discord callback if it doesn't exist
        $page_id = get_option('discord_integration_callback_page');
        if (!$page_id) {
            $page_id = wp_insert_post(array(
                'post_title' => 'Discord Authentication',
                'post_content' => '[discord_auth_callback]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'comment_status' => 'closed',
                'ping_status' => 'closed'
            ));
            if (!is_wp_error($page_id)) {
                update_option('discord_integration_callback_page', $page_id);
                update_option('discord_integration_options', array_merge($this->options, array(
                    'redirect_uri' => get_permalink($page_id)
                )));
            }
        }
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Remove cron job
        wp_clear_scheduled_hook('discord_role_sync_cron');
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Discord Integration', 'discord-integration'),
            __('Discord Integration', 'discord-integration'),
            'manage_options',
            'discord-integration',
            array($this, 'admin_page_content'),
            'dashicons-share',
            100
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting(
            'discord_integration_group',
            'discord_integration_options',
            array($this, 'validate_options')
        );
    }

    /**
     * Validate options
     */
    public function validate_options($input) {
        $valid = array();
        
        $valid['client_id'] = sanitize_text_field($input['client_id']);
        $valid['client_secret'] = sanitize_text_field($input['client_secret']);
        $valid['redirect_uri'] = esc_url_raw($input['redirect_uri']);
        $valid['bot_token'] = sanitize_text_field($input['bot_token']);
        $valid['guild_id'] = sanitize_text_field($input['guild_id']);
        $valid['redirect_after_login'] = esc_url_raw($input['redirect_after_login']);
        $valid['sync_frequency'] = sanitize_text_field($input['sync_frequency']);
        $valid['debug_mode'] = isset($input['debug_mode']) ? (bool) $input['debug_mode'] : false;
        
        // Validate role mappings
        if (isset($input['role_mappings']) && is_array($input['role_mappings'])) {
            foreach ($input['role_mappings'] as $discord_role_id => $wp_role) {
                $valid['role_mappings'][sanitize_text_field($discord_role_id)] = sanitize_text_field($wp_role);
            }
        } else {
            $valid['role_mappings'] = array();
        }
        
        // Reschedule cron if frequency changed
        if (isset($this->options['sync_frequency']) && $this->options['sync_frequency'] !== $valid['sync_frequency']) {
            wp_clear_scheduled_hook('discord_role_sync_cron');
            if ($valid['sync_frequency'] !== 'never') {
                wp_schedule_event(time(), $valid['sync_frequency'], 'discord_role_sync_cron');
            }
        }
        
        return $valid;
    }

    /**
     * Add settings link
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="admin.php?page=discord-integration">' . __('Settings') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Admin page content
     */
    public function admin_page_content() {
        ?>
        <div class="wrap">
            <h1><?php _e('Discord Integration Settings', 'discord-integration'); ?></h1>
            
            <nav class="nav-tab-wrapper">
                <a href="#tab-general" class="nav-tab nav-tab-active"><?php _e('General Settings', 'discord-integration'); ?></a>
                <a href="#tab-roles" class="nav-tab"><?php _e('Role Mapping', 'discord-integration'); ?></a>
                <a href="#tab-sync" class="nav-tab"><?php _e('Synchronization', 'discord-integration'); ?></a>
                <a href="#tab-logs" class="nav-tab"><?php _e('Logs', 'discord-integration'); ?></a>
            </nav>
            
            <div class="tab-content">
                <form method="post" action="options.php">
                    <?php settings_fields('discord_integration_group'); ?>
                    
                    <div id="tab-general" class="tab-pane active">
                        <h2><?php _e('General Settings', 'discord-integration'); ?></h2>
                        <p><?php _e('Configure the Discord OAuth2 application settings.', 'discord-integration'); ?></p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Client ID', 'discord-integration'); ?></th>
                                <td>
                                    <input type="text" name="discord_integration_options[client_id]" value="<?php echo esc_attr($this->options['client_id']); ?>" class="regular-text" />
                                    <p class="description"><?php _e('Your Discord application client ID.', 'discord-integration'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Client Secret', 'discord-integration'); ?></th>
                                <td>
                                    <input type="password" name="discord_integration_options[client_secret]" value="<?php echo esc_attr($this->options['client_secret']); ?>" class="regular-text" />
                                    <p class="description"><?php _e('Your Discord application client secret.', 'discord-integration'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Redirect URI', 'discord-integration'); ?></th>
                                <td>
                                    <input type="text" name="discord_integration_options[redirect_uri]" value="<?php echo esc_url($this->options['redirect_uri']); ?>" class="regular-text" />
                                    <p class="description"><?php _e('The redirect URI for OAuth2 authentication.', 'discord-integration'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Bot Token', 'discord-integration'); ?></th>
                                <td>
                                    <input type="password" name="discord_integration_options[bot_token]" value="<?php echo esc_attr($this->options['bot_token']); ?>" class="regular-text" />
                                    <p class="description"><?php _e('Your Discord bot token with permissions to read members and roles.', 'discord-integration'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Guild ID', 'discord-integration'); ?></th>
                                <td>
                                    <input type="text" name="discord_integration_options[guild_id]" value="<?php echo esc_attr($this->options['guild_id']); ?>" class="regular-text" />
                                    <p class="description"><?php _e('Your Discord server\'s Guild ID.', 'discord-integration'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Redirect After Login', 'discord-integration'); ?></th>
                                <td>
                                    <input type="text" name="discord_integration_options[redirect_after_login]" value="<?php echo esc_url($this->options['redirect_after_login']); ?>" class="regular-text" />
                                    <p class="description"><?php _e('The page to redirect users to after successful login.', 'discord-integration'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Debug Mode', 'discord-integration'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="discord_integration_options[debug_mode]" value="1" <?php checked($this->options['debug_mode'], true); ?> />
                                        <?php _e('Enable debug logging', 'discord-integration'); ?>
                                    </label>
                                    <p class="description"><?php _e('Log detailed debug information to wp-content/uploads/discord-integration-debug.txt', 'discord-integration'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div id="tab-roles" class="tab-pane">
                        <h2><?php _e('Role Mapping', 'discord-integration'); ?></h2>
                        <p><?php _e('Map Discord roles to WordPress user roles.', 'discord-integration'); ?></p>
                        
                        <div id="discord-roles-loader">
                            <button type="button" id="load-discord-roles" class="button button-secondary"><?php _e('Load Discord Roles', 'discord-integration'); ?></button>
                            <span id="roles-loading" style="display:none; margin-left: 10px;"><?php _e('Loading...', 'discord-integration'); ?></span>
                            <div id="discord-roles-list" style="margin-top: 15px;"></div>
                        </div>
                        
                        <h3><?php _e('Current Role Mappings', 'discord-integration'); ?></h3>
                        <table class="wp-list-table widefat fixed striped" id="role-mappings-table">
                            <thead>
                                <tr>
                                    <th><?php _e('Discord Role', 'discord-integration'); ?></th>
                                    <th><?php _e('WordPress Role', 'discord-integration'); ?></th>
                                    <th><?php _e('Actions', 'discord-integration'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (!empty($this->role_mappings)) {
                                    $wp_roles = get_editable_roles();
                                    foreach ($this->role_mappings as $discord_role_id => $wp_role) {
                                        echo '<tr>';
                                        echo '<td><span class="discord-role-name" data-id="' . esc_attr($discord_role_id) . '">' . esc_html($discord_role_id) . '</span></td>';
                                        echo '<td>';
                                        echo '<select name="discord_integration_options[role_mappings][' . esc_attr($discord_role_id) . ']">';
                                        echo '<option value="">' . __('--- Select WordPress Role ---', 'discord-integration') . '</option>';
                                        foreach ($wp_roles as $role_id => $role_info) {
                                            echo '<option value="' . esc_attr($role_id) . '" ' . selected($wp_role, $role_id, false) . '>' . esc_html($role_info['name']) . '</option>';
                                        }
                                        echo '</select>';
                                        echo '</td>';
                                        echo '<td><button type="button" class="button button-small remove-role-mapping" data-id="' . esc_attr($discord_role_id) . '">' . __('Remove', 'discord-integration') . '</button></td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="3">' . __('No role mappings configured. Click "Load Discord Roles" to get started.', 'discord-integration') . '</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div id="tab-sync" class="tab-pane">
                        <h2><?php _e('Synchronization Settings', 'discord-integration'); ?></h2>
                        <p><?php _e('Configure how and when Discord roles should be synchronized.', 'discord-integration'); ?></p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Sync Frequency', 'discord-integration'); ?></th>
                                <td>
                                    <select name="discord_integration_options[sync_frequency]">
                                        <option value="hourly" <?php selected($this->options['sync_frequency'], 'hourly'); ?>><?php _e('Hourly', 'discord-integration'); ?></option>
                                        <option value="twicedaily" <?php selected($this->options['sync_frequency'], 'twicedaily'); ?>><?php _e('Twice Daily', 'discord-integration'); ?></option>
                                        <option value="daily" <?php selected($this->options['sync_frequency'], 'daily'); ?>><?php _e('Daily', 'discord-integration'); ?></option>
                                        <option value="never" <?php selected($this->options['sync_frequency'], 'never'); ?>><?php _e('Disable Automatic Sync', 'discord-integration'); ?></option>
                                    </select>
                                    <p class="description"><?php _e('How often roles should be automatically synchronized.', 'discord-integration'); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <div class="sync-actions" style="margin-top: 20px; padding: 15px; background: #f7f7f7; border-left: 4px solid #00a0d2;">
                            <h3><?php _e('Manual Synchronization', 'discord-integration'); ?></h3>
                            <p><?php _e('To manually synchronize all users, click the button below:', 'discord-integration'); ?></p>
                            <button type="button" id="manual-sync-button" class="button button-primary"><?php _e('Manually Sync All Users', 'discord-integration'); ?></button>
                            <span id="manual-sync-status" style="display:none; margin-left: 10px;"></span>
                            
                            <h4 style="margin-top: 20px;"><?php _e('Next Scheduled Sync', 'discord-integration'); ?></h4>
                            <?php
                            $next_sync = wp_next_scheduled('discord_role_sync_cron');
                            if ($next_sync) {
                                echo '<p>' . sprintf(__('Next automatic synchronization: %s', 'discord-integration'), date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_sync)) . '</p>';
                            } else {
                                echo '<p>' . __('Automatic synchronization is disabled.', 'discord-integration') . '</p>';
                            }
                            ?>
                        </div>
                    </div>
                    
                    <div id="tab-logs" class="tab-pane">
                        <h2><?php _e('Debug Logs', 'discord-integration'); ?></h2>
                        <p><?php _e('View recent debug logs (requires Debug Mode to be enabled).', 'discord-integration'); ?></p>
                        
                        <?php
                        $log_file = WP_CONTENT_DIR . '/uploads/discord-integration-debug.txt';
                        if (file_exists($log_file) && $this->options['debug_mode']) {
                            $logs = file_get_contents($log_file);
                            $logs = !empty($logs) ? $logs : __('No logs available.', 'discord-integration');
                            echo '<div class="log-container" style="max-height: 400px; overflow-y: scroll; background: #f0f0f0; padding: 10px; font-family: monospace;">';
                            echo nl2br(esc_html($logs));
                            echo '</div>';
                            echo '<p><button type="button" id="clear-logs" class="button">' . __('Clear Logs', 'discord-integration') . '</button></p>';
                        } else {
                            if (!$this->options['debug_mode']) {
                                echo '<p>' . __('Debug mode is currently disabled. Enable it in the General Settings tab to view logs.', 'discord-integration') . '</p>';
                            } else {
                                echo '<p>' . __('No logs available.', 'discord-integration') . '</p>';
                            }
                        }
                        ?>
                    </div>
                    
                    <?php submit_button(); ?>
                </form>
            </div>
        </div>
        
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Tab navigation
                $('.nav-tab').on('click', function(e) {
                    e.preventDefault();
                    
                    // Hide all tab panes
                    $('.tab-pane').removeClass('active').hide();
                    
                    // Show the selected tab pane
                    $($(this).attr('href')).addClass('active').show();
                    
                    // Update active tab
                    $('.nav-tab').removeClass('nav-tab-active');
                    $(this).addClass('nav-tab-active');
                });
                
                // Initialize tabs
                $('.tab-pane').hide();
                $('#tab-general').show();
                
                // Load Discord roles
                $('#load-discord-roles').on('click', function() {
                    const guildId = $('input[name="discord_integration_options[guild_id]"]').val();
                    const botToken = $('input[name="discord_integration_options[bot_token]"]').val();
                    
                    if (!guildId || !botToken) {
                        alert('<?php _e('Please enter Guild ID and Bot Token in the General Settings tab.', 'discord-integration'); ?>');
                        return;
                    }
                    
                    $('#roles-loading').show();
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'get_discord_roles',
                            guild_id: guildId,
                            bot_token: botToken,
                            nonce: '<?php echo wp_create_nonce('discord_integration_nonce'); ?>'
                        },
                        success: function(response) {
                            $('#roles-loading').hide();
                            
                            if (response.success) {
                                let rolesHtml = '<h3><?php _e('Available Discord Roles', 'discord-integration'); ?></h3>';
                                rolesHtml += '<table class="wp-list-table widefat fixed striped">';
                                rolesHtml += '<thead><tr><th><?php _e('Role Name', 'discord-integration'); ?></th><th><?php _e('Role ID', 'discord-integration'); ?></th><th><?php _e('Action', 'discord-integration'); ?></th></tr></thead><tbody>';
                                
                                response.data.forEach(function(role) {
                                    rolesHtml += '<tr>';
                                    rolesHtml += '<td>' + role.name + '</td>';
                                    rolesHtml += '<td>' + role.id + '</td>';
                                    rolesHtml += '<td><button type="button" class="button add-role-mapping" data-id="' + role.id + '" data-name="' + role.name + '"><?php _e('Add to Mapping', 'discord-integration'); ?></button></td>';
                                    rolesHtml += '</tr>';
                                });
                                
                                rolesHtml += '</tbody></table>';
                                $('#discord-roles-list').html(rolesHtml);
                                
                                // Update Discord role names in form
                                response.data.forEach(function(role) {
                                    $('.discord-role-name[data-id="' + role.id + '"]').text(role.name);
                                });
                                
                                // Add new mapping button
                                $('.add-role-mapping').on('click', function() {
                                    const roleId = $(this).data('id');
                                    const roleName = $(this).data('name');
                                    
                                    // Check if this role has already been added
                                    if ($('select[name="discord_integration_options[role_mappings][' + roleId + ']"]').length > 0) {
                                        alert('<?php _e('This role has already been added to the mapping list.', 'discord-integration'); ?>');
                                        return;
                                    }
                                    
                                    // Get WordPress roles
                                    const wpRoles = <?php echo json_encode(get_editable_roles()); ?>;
                                    let wpRoleOptions = '<option value=""><?php _e('--- Select WordPress Role ---', 'discord-integration'); ?></option>';
                                    
                                    for (const [roleId, roleInfo] of Object.entries(wpRoles)) {
                                        wpRoleOptions += '<option value="' + roleId + '">' + roleInfo.name + '</option>';
                                    }
                                    
                                    // Add new row to the mapping table
                                    const newRow = '<tr>' +
                                                  '<td><span class="discord-role-name" data-id="' + roleId + '">' + roleName + '</span></td>' +
                                                  '<td><select name="discord_integration_options[role_mappings][' + roleId + ']">' + wpRoleOptions + '</select></td>' +
                                                  '<td><button type="button" class="button button-small remove-role-mapping" data-id="' + roleId + '"><?php _e('Remove', 'discord-integration'); ?></button></td>' +
                                                  '</tr>';
                                    
                                    if ($('#role-mappings-table tbody tr:first td').length === 1) {
                                        $('#role-mappings-table tbody').html(newRow);
                                    } else {
                                        $('#role-mappings-table tbody').append(newRow);
                                    }
                                    
                                    // Add event listener for remove button
                                    attachRemoveButtonHandler();
                                });
                            } else {
                                alert('<?php _e('Error loading Discord roles: ', 'discord-integration'); ?>' + response.data);
                            }
                        },
                        error: function() {
                            $('#roles-loading').hide();
                            alert('<?php _e('Error connecting to server. Please try again.', 'discord-integration'); ?>');
                        }
                    });
                });
                
                // Remove role mapping
                function attachRemoveButtonHandler() {
                    $('.remove-role-mapping').off('click').on('click', function() {
                        const roleId = $(this).data('id');
                        $(this).closest('tr').remove();
                        
                        // If no mappings left, show placeholder
                        if ($('#role-mappings-table tbody tr').length === 0) {
                            $('#role-mappings-table tbody').html('<tr><td colspan="3"><?php _e('No role mappings configured. Click "Load Discord Roles" to get started.', 'discord-integration'); ?></td></tr>');
                        }
                    });
                }
                
                attachRemoveButtonHandler();
                
                // Manual synchronization
                $('#manual-sync-button').on('click', function() {
                    const $button = $(this);
                    const $status = $('#manual-sync-status');
                    
                    $button.prop('disabled', true);
                    $status.text('<?php _e('Synchronizing...', 'discord-integration'); ?>').show();
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'sync_discord_roles_manually',
                            nonce: '<?php echo wp_create_nonce('discord_integration_nonce'); ?>'
                        },
                        success: function(response) {
                            $button.prop('disabled', false);
                            
                            if (response.success) {
                                $status.text('<?php _e('Synchronization successful. ', 'discord-integration'); ?>' + response.data.count + ' <?php _e('users updated.', 'discord-integration'); ?>');
                                setTimeout(function() {
                                    $status.fadeOut();
                                }, 5000);
                            } else {
                                $status.text('<?php _e('Error: ', 'discord-integration'); ?>' + response.data);
                            }
                        },
                        error: function() {
                            $button.prop('disabled', false);
                            $status.text('<?php _e('Error connecting to server. Please try again.', 'discord-integration'); ?>');
                        }
                    });
                });
                
                // Clear logs
                $('#clear-logs').on('click', function() {
                    if (confirm('<?php _e('Are you sure you want to clear the logs?', 'discord-integration'); ?>')) {
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'clear_discord_integration_logs',
                                nonce: '<?php echo wp_create_nonce('discord_integration_nonce'); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    $('.log-container').html('<?php _e('Logs cleared.', 'discord-integration'); ?>');
                                } else {
                                    alert('<?php _e('Error clearing logs: ', 'discord-integration'); ?>' + response.data);
                                }
                            },
                            error: function() {
                                alert('<?php _e('Error connecting to server. Please try again.', 'discord-integration'); ?>');
                            }
                        });
                    }
                });
            });
        </script>
        <?php
    }

    /**
     * Discord login button shortcode
     */
    public function discord_login_button($atts) {
        $atts = shortcode_atts(array(
            'class' => 'button button-primary',
            'text' => __('Login with Discord', 'discord-integration')
        ), $atts, 'discord_login');
        
        if (empty($this->client_id)) {
            return '<p class="error">' . __('Discord login is not properly configured. Client ID is missing.', 'discord-integration') . '</p>';
        }
        
        if (empty($this->redirect_uri)) {
            $this->redirect_uri = site_url('/discord-callback');
        }
        
        $auth_url = 'https://discord.com/api/oauth2/authorize?' . http_build_query(array(
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'response_type' => 'code',
            'scope' => 'identify guilds.members.read email'
        ));
        
        return '<a href="' . esc_url($auth_url) . '" class="' . esc_attr($atts['class']) . '">' . esc_html($atts['text']) . '</a>';
    }

    /**
     * Process Discord login
     */
    public function process_discord_login() {
        // Check if we're on the callback page
        if (isset($_GET['code']) && 
            (strpos($_SERVER['REQUEST_URI'], '/discord-callback') !== false || 
             strpos($_SERVER['REQUEST_URI'], 'page=discord-callback') !== false)) {
            
            $code = sanitize_text_field($_GET['code']);
            
            // Exchange code for token
            $token_response = wp_remote_post('https://discord.com/api/oauth2/token', array(
                'body' => array(
                    'client_id' => $this->client_id,
                    'client_secret' => $this->client_secret,
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $this->redirect_uri,
                    'scope' => 'identify guilds.members.read email'
                ),
                'headers' => array('Content-Type' => 'application/x-www-form-urlencoded')
            ));
            
            if (is_wp_error($token_response)) {
                $this->log_debug("Error in token response: " . $token_response->get_error_message());
                wp_redirect(home_url('/?login=failed&reason=token_error'));
                exit;
            }
            
            $tokens = json_decode(wp_remote_retrieve_body($token_response), true);
            
            $access_token = isset($tokens['access_token']) ? $tokens['access_token'] : null;
            
            if (!$access_token) {
                $this->log_debug("No access token received.");
                wp_redirect(home_url('/?login=failed&reason=no_token'));
                exit;
            }
            
            // Get user info from Discord
            $user_response = wp_remote_get('https://discord.com/api/users/@me', array(
                'headers' => array('Authorization' => 'Bearer ' . $access_token)
            ));
            
            if (is_wp_error($user_response)) {
                $this->log_debug("Error getting user info: " . $user_response->get_error_message());
                wp_redirect(home_url('/?login=failed&reason=user_info_error'));
                exit;
            }
            
            $user_info = json_decode(wp_remote_retrieve_body($user_response), true);
            
            if (!isset($user_info['id'])) {
                $this->log_debug("User info not found or invalid.");
                wp_redirect(home_url('/?login=failed&reason=invalid_user_info'));
                exit;
            }
            
            $discord_id = $user_info['id'];
            $username = isset($user_info['username']) ? $user_info['username'] : '';
            $discriminator = isset($user_info['discriminator']) ? $user_info['discriminator'] : '';
            $avatar = isset($user_info['avatar']) ? $user_info['avatar'] : '';
            $email = isset($user_info['email']) ? $user_info['email'] : '';
            
            // If no email is provided, create a fallback email
            if (empty($email)) {
                $email = $discord_id . '@discord.user';
            }
            
            // Check if user exists by Discord ID meta
            $existing_users = get_users(array(
                'meta_key' => 'discord_user_id',
                'meta_value' => $discord_id,
                'number' => 1
            ));
            
            if (!empty($existing_users)) {
                $user = $existing_users[0];
            } else {
                // Check if user exists by email
                $user = get_user_by('email', $email);
                
                if ($user) {
                    // User exists by email, update Discord ID
                    update_user_meta($user->ID, 'discord_user_id', $discord_id);
                } else {
                    // Generate a username that's not taken
                    $base_username = sanitize_user($username, true);
                    if (empty($base_username)) {
                        $base_username = 'discord_user';
                    }
                    
                    $username_check = $base_username;
                    $counter = 1;
                    
                    while (username_exists($username_check)) {
                        $username_check = $base_username . $counter;
                        $counter++;
                    }
                    
                    // Create a new user
                    $random_password = wp_generate_password(12, false);
                    $user_id = wp_insert_user(array(
                        'user_login' => $username_check,
                        'user_pass' => $random_password,
                        'user_email' => $email,
                        'display_name' => $username,
                        'role' => 'subscriber' // Default role
                    ));
                    
                    if (is_wp_error($user_id)) {
                        $this->log_debug("Error creating user: " . $user_id->get_error_message());
                        wp_redirect(home_url('/?login=failed&reason=user_creation_error'));
                        exit;
                    }
                    
                    $user = get_user_by('id', $user_id);
                    
                    // Store Discord user ID
                    update_user_meta($user->ID, 'discord_user_id', $discord_id);
                }
            }
            
            // Update Discord info
            update_user_meta($user->ID, 'discord_username', $username);
            if ($discriminator) {
                update_user_meta($user->ID, 'discord_discriminator', $discriminator);
            }
            if ($avatar) {
                update_user_meta($user->ID, 'discord_avatar', $avatar);
            }
            
            // Get Discord guild member info to get roles
            $guild_id = $this->guild_id;
            
            if (!empty($guild_id) && !empty($this->bot_token)) {
                // Using the bot token to get guild member info
                $member_response = wp_remote_get("https://discord.com/api/guilds/{$guild_id}/members/{$discord_id}", array(
                    'headers' => array('Authorization' => 'Bot ' . $this->bot_token)
                ));
                
                if (!is_wp_error($member_response)) {
                    $status_code = wp_remote_retrieve_response_code($member_response);
                    
                    if ($status_code === 200) {
                        $member_info = json_decode(wp_remote_retrieve_body($member_response), true);
                        
                        if (isset($member_info['roles']) && is_array($member_info['roles'])) {
                            // Store Discord roles
                            update_user_meta($user->ID, 'discord_roles', $member_info['roles']);
                            
                            // Apply WordPress role based on Discord roles
                            $this->apply_role_mapping($user->ID, $member_info['roles']);
                        }
                    }
                }
            }
            
            // Log in the user
            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID);
            do_action('wp_login', $user->user_login, $user);
            
            // Redirect after login
            $redirect_url = !empty($this->redirect_after_login) ? $this->redirect_after_login : home_url();
            
            wp_redirect($redirect_url);
            exit;
        }
    }

    /**
     * Apply role mapping to user
     */
    public function apply_role_mapping($user_id, $discord_roles) {
        $user = get_user_by('id', $user_id);
        if (!$user) return false;
        
        // Prevent modifying administrators
        if (in_array('administrator', $user->roles) && !current_user_can('manage_options')) {
            return false;
        }
        
        // Default role if no mapping matches
        $wp_role = 'subscriber';
        $matched = false;
        
        // Check each Discord role against our mapping
        foreach ($discord_roles as $role_id) {
            if (isset($this->role_mappings[$role_id]) && !empty($this->role_mappings[$role_id])) {
                $wp_role = $this->role_mappings[$role_id];
                $matched = true;
                break; // Use the first matching role
            }
        }
        
        if ($matched) {
            // Remove existing roles defined in mappings (to prevent having multiple mapped roles)
            $mapped_wp_roles = array_values($this->role_mappings);
            foreach ($mapped_wp_roles as $mapped_role) {
                if (!empty($mapped_role) && $mapped_role !== $wp_role && in_array($mapped_role, $user->roles)) {
                    $user->remove_role($mapped_role);
                }
            }
            
            // Add the new role
            $user->add_role($wp_role);
            return true;
        }
        
        return false;
    }

    /**
     * Sync user roles on login
     */
    public function sync_user_roles_on_login($user_login, $user) {
        // Check if user is connected to Discord
        $discord_id = get_user_meta($user->ID, 'discord_user_id', true);
        
        if (!$discord_id) {
            return; // User not connected to Discord
        }
        
        // Get Discord roles
        $discord_roles = get_user_meta($user->ID, 'discord_roles', true);
        
        if (empty($discord_roles) || !is_array($discord_roles)) {
            // If no roles stored or invalid, try to get them from Discord
            $this->sync_user_roles($user->ID, $discord_id);
        } else {
            // Apply roles based on stored Discord roles
            $this->apply_role_mapping($user->ID, $discord_roles);
        }
    }

    /**
     * Sync roles for a user
     */
    public function sync_user_roles($user_id, $discord_id = null) {
        if (!$discord_id) {
            $discord_id = get_user_meta($user_id, 'discord_user_id', true);
            
            if (!$discord_id) {
                $this->log_debug("User $user_id not connected to Discord.");
                return false; // User not connected to Discord
            }
        }
        
        if (empty($this->guild_id) || empty($this->bot_token)) {
            $this->log_debug("Guild ID or Bot Token not configured.");
            return false; // Incomplete settings
        }
        
        // Get guild member info to get roles
        $member_response = wp_remote_get("https://discord.com/api/guilds/{$this->guild_id}/members/{$discord_id}", array(
            'headers' => array('Authorization' => 'Bot ' . $this->bot_token)
        ));
        
        if (is_wp_error($member_response)) {
            $this->log_debug("Error getting member info for user $user_id: " . $member_response->get_error_message());
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($member_response);
        if ($status_code !== 200) {
            $this->log_debug("Discord API error for user $user_id: HTTP $status_code");
            return false;
        }
        
        $member_data = json_decode(wp_remote_retrieve_body($member_response), true);
        
        if (!isset($member_data['roles']) || !is_array($member_data['roles'])) {
            $this->log_debug("No roles found for user $user_id in Discord guild.");
            return false; // No access to roles
        }
        
        // Store Discord roles
        update_user_meta($user_id, 'discord_roles', $member_data['roles']);
        
        // Apply WordPress role based on Discord roles
        $updated = $this->apply_role_mapping($user_id, $member_data['roles']);
        
        return $updated;
    }

    /**
     * Sync all users
     */
    public function sync_all_users_roles() {
        $this->log_debug("Starting sync for all users");
        
        // Get users connected to Discord
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
     * AJAX handler for getting Discord roles
     */
    public function ajax_get_discord_roles() {
        check_ajax_referer('discord_integration_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('You do not have sufficient permissions for this operation.');
        }
        
        $guild_id = isset($_POST['guild_id']) ? sanitize_text_field($_POST['guild_id']) : '';
        $bot_token = isset($_POST['bot_token']) ? sanitize_text_field($_POST['bot_token']) : '';
        
        if (empty($guild_id) || empty($bot_token)) {
            wp_send_json_error('Guild ID and Bot Token are required.');
        }
        
        // Get Discord server roles
        $response = wp_remote_get("https://discord.com/api/guilds/{$guild_id}/roles", array(
            'headers' => array('Authorization' => "Bot {$bot_token}")
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error('Error connecting to Discord API: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code !== 200) {
            wp_send_json_error('Discord error: ' . $status_code);
        }
        
        $roles = json_decode(wp_remote_retrieve_body($response), true);
        
        // Remove @everyone role
        $roles = array_filter($roles, function($role) {
            return $role['name'] !== '@everyone';
        });
        
        wp_send_json_success(array_values($roles));
    }

    /**
     * AJAX handler for manual synchronization
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
     * AJAX handler for clearing logs
     */
    public function ajax_clear_logs() {
        check_ajax_referer('discord_integration_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('You do not have sufficient permissions for this operation.');
        }
        
        $log_file = WP_CONTENT_DIR . '/uploads/discord-integration-debug.txt';
        if (file_exists($log_file)) {
            file_put_contents($log_file, '');
            wp_send_json_success();
        } else {
            wp_send_json_error('Log file does not exist.');
        }
    }
}

// Initialize the plugin
$discord_integration = new Discord_Integration();