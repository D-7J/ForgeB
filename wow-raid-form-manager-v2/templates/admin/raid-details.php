<?php
/**
 * Raid details admin page template
 */

if (!defined('ABSPATH')) exit;

$user = get_user_by('id', $raid->created_by);
$username = $user ? $user->display_name : 'Unknown';

$datetime = date('F j, Y', strtotime($raid->raid_date)) . ' at ' . 
           str_pad($raid->raid_hour, 2, '0', STR_PAD_LEFT) . ':' . 
           str_pad($raid->raid_minute, 2, '0', STR_PAD_LEFT);
?>

<div class="wrap">
    <h1>Raid Details</h1>
    <a href="?page=wow-manage-raids" class="button">&larr; Back to All Raids</a>
    
    <div class="card raid-details-card" style="margin-top: 20px;">
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
    
    <?php
    // Get bookings for this raid
    global $wpdb;
    $database = $this->manager->get_database();
    $bookings_table = $database->get_bookings_table();
    
    $bookings = $wpdb->get_results($wpdb->prepare(
        "SELECT b.*, u.display_name as advertiser_name 
         FROM $bookings_table b
         LEFT JOIN {$wpdb->users} u ON b.advertiser_id = u.ID
         WHERE b.raid_id = %d
         ORDER BY b.created_at DESC",
        $raid->id
    ));
    
    if (!empty($bookings)) :
    ?>
    <div class="card" style="margin-top: 20px;">
        <h2>Bookings for this Raid (<?php echo count($bookings); ?>)</h2>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Armor Type</th>
                    <th>Status</th>
                    <th>Price</th>
                    <th>Advertiser</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $booking) : ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($booking->buyer_charname); ?></strong><br>
                        <small><?php echo esc_html($booking->buyer_realm); ?></small>
                    </td>
                    <td>
                        <?php if ($booking->armor_type) : ?>
                            <?php echo esc_html($booking->armor_type); ?>
                            <?php if ($booking->armor_status) : ?>
                                <br><small><?php echo ucfirst($booking->armor_status); ?></small>
                            <?php endif; ?>
                        <?php else : ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="status-<?php echo $booking->booking_status; ?>">
                            <?php echo ucfirst($booking->booking_status); ?>
                        </span>
                    </td>
                    <td><?php echo number_format($booking->total_price, 2); ?>g</td>
                    <td><?php echo esc_html($booking->advertiser_name); ?></td>
                    <td><?php echo date('M j, Y H:i', strtotime($booking->created_at)); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>