<?php
/**
 * Manage raids admin page template
 */

if (!defined('ABSPATH')) exit;
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
                            <a href="?page=wow-manage-raids&action=delete&id=<?php echo $raid->id; ?>" 
                               class="button button-small" 
                               onclick="return confirm('Are you sure you want to delete this raid?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <p><strong>Total Raids:</strong> <?php echo count($raids); ?></p>
    <?php endif; ?>
</div>