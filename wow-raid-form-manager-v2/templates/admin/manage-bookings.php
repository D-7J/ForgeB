<?php
/**
 * Manage bookings admin page template
 */

if (!defined('ABSPATH')) exit;
?>

<div class="wrap">
    <h1>Manage Bookings</h1>
    <p>Use the booking management interface in your frontend pages with the <code>[wow_booking_management]</code> shortcode.</p>
    <p>This admin page shows a quick overview of booking statistics.</p>
    
    <?php if (isset($stats) && !empty($stats)) : ?>
    <div class="bookings-summary">
        <div class="summary-card">
            <h4>Total Bookings</h4>
            <div class="number"><?php echo intval($stats['total_bookings']); ?></div>
        </div>
        
        <div class="summary-card">
            <h4>Pending</h4>
            <div class="number"><?php echo intval($stats['pending_bookings']); ?></div>
        </div>
        
        <div class="summary-card">
            <h4>Confirmed</h4>
            <div class="number"><?php echo intval($stats['confirmed_bookings']); ?></div>
        </div>
        
        <div class="summary-card">
            <h4>Completed</h4>
            <div class="number"><?php echo intval($stats['completed_bookings']); ?></div>
        </div>
        
        <div class="summary-card">
            <h4>Cancelled</h4>
            <div class="number"><?php echo intval($stats['cancelled_bookings']); ?></div>
        </div>
        
        <div class="summary-card">
            <h4>Total Revenue</h4>
            <div class="number"><?php echo number_format(floatval($stats['total_revenue']), 0); ?>g</div>
        </div>
    </div>
    
    <?php if (!empty($recent_bookings)) : ?>
    <div class="card recent-bookings-table">
        <h2>Recent Bookings</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Raid</th>
                    <th>Armor Type</th>
                    <th>Status</th>
                    <th>Price</th>
                    <th>Advertiser</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_bookings as $booking) : ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($booking->buyer_charname); ?></strong><br>
                        <small><?php echo esc_html($booking->buyer_realm); ?></small>
                    </td>
                    <td>
                        <?php if ($booking->raid_name && $booking->raid_date) : ?>
                            <?php echo esc_html($booking->raid_name); ?><br>
                            <small><?php echo esc_html($booking->raid_date); ?></small>
                        <?php else : ?>
                            <em>Raid not found</em>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($booking->armor_type) : ?>
                            <?php echo esc_html($booking->armor_type); ?>
                            <?php if ($booking->armor_status) : ?>
                                <br><small><?php echo ucfirst($booking->armor_status); ?></small>
                            <?php endif; ?>
                        <?php else : ?>
                            N/A
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="status-<?php echo $booking->booking_status; ?>">
                            <?php echo ucfirst($booking->booking_status); ?>
                        </span>
                    </td>
                    <td><?php echo number_format($booking->total_price, 0); ?>g</td>
                    <td><?php echo esc_html($booking->advertiser_name ?: 'Unknown'); ?></td>
                    <td><?php echo date('M j, Y H:i', strtotime($booking->created_at)); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else : ?>
    <div class="card">
        <p>No bookings found yet.</p>
    </div>
    <?php endif; ?>
    
    <?php else : ?>
    <div class="notice notice-error">
        <p>Bookings table not found. Please reactivate the plugin.</p>
    </div>
    <?php endif; ?>
</div>