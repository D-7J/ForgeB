<?php
/**
 * Admin settings page template
 */

if (!defined('ABSPATH')) exit;
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <form method="post" action="options.php">
        <?php settings_fields('wow_raid_form_group'); ?>
        
        <h2>Raid Creation Permissions</h2>
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
                    <p class="description">Message to show when user doesn't have permission to create raids.</p>
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
        
        <h2>Booking Creation Permissions</h2>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">Booking WordPress Roles</th>
                <td>
                    <?php
                    $booking_wp_roles = isset($settings['booking_wp_roles']) && is_array($settings['booking_wp_roles']) 
                        ? $settings['booking_wp_roles'] 
                        : array('administrator', 'editor');
                    
                    foreach ($wp_roles->role_names as $role_id => $role_name) :
                    ?>
                        <label style="display: block; margin-bottom: 5px;">
                            <input type="checkbox" 
                                   name="wow_raid_form_settings[booking_wp_roles][]" 
                                   value="<?php echo esc_attr($role_id); ?>"
                                   <?php checked(in_array($role_id, $booking_wp_roles)); ?>>
                            <?php echo esc_html($role_name); ?>
                        </label>
                    <?php endforeach; ?>
                    <p class="description">Select WordPress roles that can create bookings.</p>
                </td>
            </tr>
            
            <tr valign="top">
                <th scope="row">Booking Access Denied Message</th>
                <td>
                    <textarea name="wow_raid_form_settings[booking_access_message]" 
                              rows="3" 
                              cols="50" 
                              class="regular-text"><?php echo esc_textarea(isset($settings['booking_access_message']) ? $settings['booking_access_message'] : 'You do not have permission to create bookings.'); ?></textarea>
                    <p class="description">Message to show when user doesn't have permission to create bookings.</p>
                </td>
            </tr>
        </table>
        
        <?php submit_button(); ?>
    </form>
    
    <hr>
    
    <div class="usage-instructions">
        <h2>Usage</h2>
        <p><strong>Raid Forms:</strong></p>
        <ul>
            <li><code>[wow_raid_form]</code> - Display the raid creation form</li>
            <li><code>[wow_raid_dashboard]</code> - Display both form and raids list</li>
        </ul>
        
        <p><strong>Booking Forms:</strong></p>
        <ul>
            <li><code>[wow_booking_form]</code> - Display the booking creation form</li>
            <li><code>[wow_booking_management]</code> - Display booking management interface</li>
        </ul>
    </div>
    
    <div class="quick-actions">
        <h3>Quick Actions</h3>
        <p>
            <a href="<?php echo admin_url('admin.php?page=wow-manage-raids'); ?>" class="button button-secondary">View Created Raids</a>
            <a href="<?php echo admin_url('admin.php?page=wow-manage-bookings'); ?>" class="button button-secondary">View Bookings</a>
            <a href="<?php echo admin_url('admin.php?page=wow-raid-debug'); ?>" class="button button-secondary">Debug Information</a>
        </p>
    </div>
    
    <hr>
    
    <div class="database-status">
        <h2>Database Status</h2>
        <?php
        global $wpdb;
        $raids_table = $wpdb->prefix . 'wow_raids';
        $bookings_table = $wpdb->prefix . 'wow_bookings';
        $raid_count = $wpdb->get_var("SELECT COUNT(*) FROM $raids_table");
        $booking_count = $wpdb->get_var("SELECT COUNT(*) FROM $bookings_table");
        ?>
        <p>
            <strong>Raids Database:</strong> 
            <?php echo $wpdb->get_var("SHOW TABLES LIKE '$raids_table'") ? '✅ Created' : '❌ Not found'; ?>
            - Total Raids: <?php echo intval($raid_count); ?>
        </p>
        <p>
            <strong>Bookings Database:</strong> 
            <?php echo $wpdb->get_var("SHOW TABLES LIKE '$bookings_table'") ? '✅ Created' : '❌ Not found'; ?>
            - Total Bookings: <?php echo intval($booking_count); ?>
        </p>
    </div>
</div>