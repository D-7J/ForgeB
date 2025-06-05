<?php
/**
 * Debug page admin template
 */

if (!defined('ABSPATH')) exit;
?>

<div class="wrap">
    <h1>WoW Raid System Debug Information</h1>
    
    <div class="card">
        <h2>System Status</h2>
        <table class="widefat debug-table">
            <tr>
                <th>Check</th>
                <th>Status</th>
                <th>Details</th>
            </tr>
            <tr>
                <td>Plugin Version</td>
                <td><?php echo esc_html($debug_info['plugin_version']); ?></td>
                <td>-</td>
            </tr>
            <tr>
                <td>WordPress Version</td>
                <td><?php echo esc_html($debug_info['wp_version']); ?></td>
                <td>-</td>
            </tr>
            <tr>
                <td>PHP Version</td>
                <td><?php echo esc_html($debug_info['php_version']); ?></td>
                <td>-</td>
            </tr>
            <tr>
                <td>Raids Table</td>
                <td><?php echo $debug_info['raids_exists'] ? '<span style="color:green;">✓ Exists</span>' : '<span style="color:red;">✗ Missing</span>'; ?></td>
                <td>
                    Table: <?php echo esc_html($debug_info['raids_table']); ?><br>
                    Created: <?php echo esc_html($debug_info['table_created']); ?>
                </td>
            </tr>
            <tr>
                <td>Bookings Table</td>
                <td><?php echo $debug_info['bookings_exists'] ? '<span style="color:green;">✓ Exists</span>' : '<span style="color:red;">✗ Missing</span>'; ?></td>
                <td>Table: <?php echo esc_html($debug_info['bookings_table']); ?></td>
            </tr>
            <tr>
                <td>Total Raids</td>
                <td><?php echo intval($debug_info['raid_count']); ?></td>
                <td>-</td>
            </tr>
            <tr>
                <td>Total Bookings</td>
                <td><?php echo intval($debug_info['booking_count']); ?></td>
                <td>-</td>
            </tr>
            <tr>
                <td>Current User</td>
                <td><?php echo $debug_info['user_logged_in'] ? 'Logged In' : 'Not Logged In'; ?></td>
                <td>
                    ID: <?php echo intval($debug_info['user_id']); ?><br>
                    Roles: <?php echo implode(', ', $debug_info['user_roles']); ?>
                </td>
            </tr>
        </table>
    </div>
    
    <div class="card" style="margin-top: 20px;">
        <h2>Database Actions</h2>
        <p>Use these actions with caution!</p>
        
        <p>
            <button class="button button-primary" onclick="recreateTables()">Recreate Database Tables</button>
            <button class="button button-secondary" onclick="createTestRaid()">Create Test VIP Raid</button>
        </p>
        
        <div id="action-results" style="margin-top: 20px;"></div>
    </div>
    
    <div class="card" style="margin-top: 20px;">
        <h2>Test AJAX Endpoints</h2>
        <p>Click the buttons below to test AJAX functionality:</p>
        
        <button class="button" onclick="testAjaxEndpoint('test_raid_system')">Test Raid System</button>
        <button class="button" onclick="testAjaxEndpoint('get_user_raids')">Get User Raids</button>
        <button class="button" onclick="testAjaxEndpoint('get_available_raids')">Get Available Raids</button>
        <button class="button" onclick="testAjaxEndpoint('wow_raid_debug')">Debug Info</button>
        <button class="button" onclick="clearResults()">Clear Results</button>
        
        <div id="ajax-results">Results will appear here...</div>
    </div>
    
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
    
    function recreateTables() {
        if (!confirm('This will recreate the database tables. Are you sure?')) {
            return;
        }
        
        var results = document.getElementById('action-results');
        results.innerHTML = '<p>Recreating tables...</p>';
        
        jQuery.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: { 
                action: 'wow_raid_debug',
                debug_action: 'recreate_table'
            },
            success: function(response) {
                if (response.success) {
                    results.innerHTML = '<div class="notice notice-success"><p>' + response.data.message + '</p></div>';
                    setTimeout(function() { location.reload(); }, 2000);
                } else {
                    results.innerHTML = '<div class="notice notice-error"><p>Error: ' + response.data + '</p></div>';
                }
            },
            error: function() {
                results.innerHTML = '<div class="notice notice-error"><p>Connection error.</p></div>';
            }
        });
    }
    
    function createTestRaid() {
        var results = document.getElementById('action-results');
        var tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        var dateStr = tomorrow.toISOString().split('T')[0];
        
        results.innerHTML = '<p>Creating test raid...</p>';
        
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
                    results.innerHTML = '<div class="notice notice-success"><p>' + response.data.message + ' (ID: ' + response.data.id + ')</p></div>';
                } else {
                    results.innerHTML = '<div class="notice notice-error"><p>Error: ' + response.data + '</p></div>';
                }
            },
            error: function() {
                results.innerHTML = '<div class="notice notice-error"><p>Connection error.</p></div>';
            }
        });
    }
    </script>
    
    <?php if (!$debug_info['raids_exists'] || !$debug_info['bookings_exists']) : ?>
    <div class="card" style="margin-top: 20px; background-color: #fff3cd; border-color: #ffeaa7;">
        <h2 style="color: #856404;">⚠️ Warning</h2>
        <p>One or more database tables are missing. Click the "Recreate Database Tables" button above to fix this issue.</p>
    </div>
    <?php endif; ?>
</div>