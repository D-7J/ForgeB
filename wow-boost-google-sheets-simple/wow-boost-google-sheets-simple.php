<?php
/**
 * Plugin Name: WoW Boost Google Sheets Simple
 * Plugin URI: https://yourwebsite.com/
 * Description: Simple Google Sheets integration
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: wow-boost-gsheets-simple
 */

if (!defined('ABSPATH')) exit;

class WoW_Boost_GSheets_Simple {
    private $plugin_path;
    private $settings;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->plugin_path = plugin_dir_path(__FILE__);
        
        // Load settings
        $this->settings = get_option('wow_boost_gsheets_simple_settings', array(
            'spreadsheet_id' => '',
            'credentials_path' => ''
        ));
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // AJAX handlers
        add_action('wp_ajax_wow_boost_gsheets_simple_test', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_wow_boost_gsheets_simple_data', array($this, 'ajax_get_data'));
        
        // Include autoloader
        if (file_exists($this->plugin_path . 'vendor/autoload.php')) {
            require_once $this->plugin_path . 'vendor/autoload.php';
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'Google Sheets Simple',
            'GSheets Simple',
            'manage_options',
            'wow-boost-gsheets-simple',
            array($this, 'admin_page'),
            'dashicons-google',
            104
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'wow_boost_gsheets_simple_group',
            'wow_boost_gsheets_simple_settings',
            array($this, 'validate_settings')
        );
    }
    
    /**
     * Validate settings
     */
    public function validate_settings($input) {
        $valid = array();
        
        $valid['spreadsheet_id'] = sanitize_text_field($input['spreadsheet_id']);
        
        // Handle credentials file upload
        if (isset($_FILES['credentials_file']) && $_FILES['credentials_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = wp_upload_dir();
            $credentials_dir = $upload_dir['basedir'] . '/wow-boost-credentials';
            
            if (!file_exists($credentials_dir)) {
                wp_mkdir_p($credentials_dir);
                
                // Protect the directory
                $htaccess_content = "Order deny,allow\nDeny from all";
                file_put_contents($credentials_dir . '/.htaccess', $htaccess_content);
            }
            
            $credentials_path = $credentials_dir . '/google-simple-credentials.json';
            
            if (move_uploaded_file($_FILES['credentials_file']['tmp_name'], $credentials_path)) {
                $valid['credentials_path'] = $credentials_path;
            }
        } else {
            $valid['credentials_path'] = isset($this->settings['credentials_path']) ? $this->settings['credentials_path'] : '';
        }
        
        return $valid;
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>Google Sheets Simple</h1>
            
            <form method="post" action="options.php" enctype="multipart/form-data">
                <?php settings_fields('wow_boost_gsheets_simple_group'); ?>
                
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Google API Credentials</th>
                        <td>
                            <?php if (!empty($this->settings['credentials_path']) && file_exists($this->settings['credentials_path'])) : ?>
                                <p class="description">Credentials file is uploaded. <span style="color: green;">✓</span></p>
                            <?php else : ?>
                                <p class="description">No credentials file uploaded.</p>
                            <?php endif; ?>
                            
                            <input type="file" name="credentials_file" accept=".json">
                            <p class="description">Upload your Google Service Account credentials JSON file.</p>
                        </td>
                    </tr>
                    
                    <tr valign="top">
                        <th scope="row">Spreadsheet ID</th>
                        <td>
                            <input type="text" name="wow_boost_gsheets_simple_settings[spreadsheet_id]" value="<?php echo esc_attr($this->settings['spreadsheet_id']); ?>" class="regular-text">
                            <p class="description">The ID of your Google Spreadsheet from the URL.</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button button-primary" value="Save Settings">
                </p>
            </form>
            
            <hr>
            
            <h2>Test Connection</h2>
            <p>Test your connection to Google Sheets:</p>
            
            <div class="card" style="max-width: 100%; background: #fff; padding: 15px; margin-bottom: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <button type="button" id="test-connection-btn" class="button button-secondary">Test Connection</button>
                <div id="test-result" style="margin-top: 15px; display: none;"></div>
            </div>
            
            <h2>Sheet Data</h2>
            <p>View data from your Google Sheets:</p>
            
            <div class="card" style="max-width: 100%; background: #fff; padding: 15px; margin-bottom: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <button type="button" id="get-data-btn" class="button button-secondary">Get Sheet Data</button>
                <div id="data-result" style="margin-top: 15px; display: none;"></div>
            </div>
            
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    // Test connection
                    $('#test-connection-btn').on('click', function() {
                        const $button = $(this);
                        const $result = $('#test-result');
                        
                        $button.prop('disabled', true);
                        $button.text('Testing...');
                        $result.html('<p>Testing connection to Google Sheets...</p>').show();
                        
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'wow_boost_gsheets_simple_test',
                                nonce: '<?php echo wp_create_nonce('wow_boost_gsheets_simple_nonce'); ?>'
                            },
                            success: function(response) {
                                $button.prop('disabled', false);
                                $button.text('Test Connection');
                                
                                if (response.success) {
                                    $result.html('<div class="notice notice-success" style="padding: 10px;">' +
                                               '<p><strong>Success!</strong> ' + response.data.message + '</p>' +
                                               '</div>');
                                } else {
                                    $result.html('<div class="notice notice-error" style="padding: 10px;">' +
                                               '<p><strong>Error:</strong> ' + response.data + '</p>' +
                                               '</div>');
                                }
                            },
                            error: function() {
                                $button.prop('disabled', false);
                                $button.text('Test Connection');
                                
                                $result.html('<div class="notice notice-error" style="padding: 10px;">' +
                                           '<p><strong>Error:</strong> Failed to connect to server.</p>' +
                                           '</div>');
                            }
                        });
                    });
                    
                    // Get sheet data
                    $('#get-data-btn').on('click', function() {
                        const $button = $(this);
                        const $result = $('#data-result');
                        
                        $button.prop('disabled', true);
                        $button.text('Loading...');
                        $result.html('<p>Fetching data from Google Sheets...</p>').show();
                        
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'wow_boost_gsheets_simple_data',
                                nonce: '<?php echo wp_create_nonce('wow_boost_gsheets_simple_nonce'); ?>'
                            },
                            success: function(response) {
                                $button.prop('disabled', false);
                                $button.text('Get Sheet Data');
                                
                                if (response.success) {
                                    let html = '<div class="notice notice-success" style="padding: 10px;">' +
                                              '<p><strong>Success!</strong> Retrieved data from ' + response.data.sheets.length + ' sheets.</p>' +
                                              '</div>';
                                    
                                    html += '<div style="margin-top: 15px;">';
                                    
                                    // For each sheet
                                    response.data.sheets.forEach(function(sheet) {
                                        html += '<h3>' + sheet.title + '</h3>';
                                        
                                        if (sheet.data && sheet.data.length > 0) {
                                            // Get headers
                                            const headers = sheet.data[0];
                                            
                                            html += '<div style="overflow-x: auto;">';
                                            html += '<table class="widefat" style="margin-bottom: 20px;">';
                                            
                                            // Table headers
                                            html += '<thead><tr>';
                                            headers.forEach(function(header) {
                                                html += '<th>' + header + '</th>';
                                            });
                                            html += '</tr></thead>';
                                            
                                            // Table body (limit to 10 rows for display)
                                            html += '<tbody>';
                                            
                                            const maxRows = Math.min(sheet.data.length - 1, 10);
                                            for (let i = 1; i <= maxRows; i++) {
                                                html += '<tr>';
                                                sheet.data[i].forEach(function(cell) {
                                                    html += '<td>' + cell + '</td>';
                                                });
                                                html += '</tr>';
                                            }
                                            
                                            html += '</tbody></table></div>';
                                            
                                            if (sheet.data.length > 11) {
                                                html += '<p>Showing 10 of ' + (sheet.data.length - 1) + ' rows.</p>';
                                            }
                                        } else {
                                            html += '<p>No data found in this sheet.</p>';
                                        }
                                    });
                                    
                                    html += '</div>';
                                    
                                    $result.html(html);
                                } else {
                                    $result.html('<div class="notice notice-error" style="padding: 10px;">' +
                                               '<p><strong>Error:</strong> ' + response.data + '</p>' +
                                               '</div>');
                                }
                            },
                            error: function() {
                                $button.prop('disabled', false);
                                $button.text('Get Sheet Data');
                                
                                $result.html('<div class="notice notice-error" style="padding: 10px;">' +
                                           '<p><strong>Error:</strong> Failed to connect to server.</p>' +
                                           '</div>');
                            }
                        });
                    });
                });
            </script>
        </div>
        <?php
    }
    
    /**
     * Initialize Google API Client
     */
    private function init_google_client() {
        if (empty($this->settings['credentials_path']) || !file_exists($this->settings['credentials_path'])) {
            throw new Exception('Google API credentials file not found.');
        }
        
        // Initialize Google Client
        $client = new Google_Client();
        $client->setApplicationName('WoW Boost Google Sheets Simple');
        $client->setScopes([Google_Service_Sheets::SPREADSHEETS_READONLY]);
        $client->setAuthConfig($this->settings['credentials_path']);
        $client->setAccessType('offline');
        
        return $client;
    }
    
    /**
     * Test connection to Google Sheets
     */
    public function test_connection() {
        try {
            // Verify required settings
            if (empty($this->settings['spreadsheet_id'])) {
                return array('success' => false, 'message' => 'Spreadsheet ID is missing.');
            }
            
            // Initialize Google client
            $client = $this->init_google_client();
            
            // Create Google Sheets service
            $service = new Google_Service_Sheets($client);
            
            // Attempt to get spreadsheet data
            $spreadsheet = $service->spreadsheets->get($this->settings['spreadsheet_id']);
            
            $sheets = array();
            foreach ($spreadsheet->getSheets() as $sheet) {
                $sheets[] = $sheet->getProperties()->getTitle();
            }
            
            return array(
                'success' => true,
                'message' => 'Connected to Google Sheets successfully! Found ' . count($sheets) . ' sheets.',
                'spreadsheet_title' => $spreadsheet->getProperties()->getTitle(),
                'sheets' => $sheets
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * AJAX handler for testing connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('wow_boost_gsheets_simple_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('You do not have sufficient permissions.');
        }
        
        $result = $this->test_connection();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Get sheet data
     */
    public function get_sheet_data() {
        try {
            // Verify required settings
            if (empty($this->settings['spreadsheet_id'])) {
                return array('success' => false, 'message' => 'Spreadsheet ID is missing.');
            }
            
            // Initialize Google client
            $client = $this->init_google_client();
            
            // Create Google Sheets service
            $service = new Google_Service_Sheets($client);
            
            // Get all sheets
            $spreadsheet = $service->spreadsheets->get($this->settings['spreadsheet_id']);
            $sheets_list = $spreadsheet->getSheets();
            
            $sheets_data = array();
            
            // Process each sheet
            foreach ($sheets_list as $sheet) {
                $sheet_title = $sheet->getProperties()->getTitle();
                $sheet_id = $sheet->getProperties()->getSheetId();
                
                // Get sheet data (limit to first 100 rows for performance)
                $range = "$sheet_title!A1:Z100";
                $response = $service->spreadsheets_values->get($this->settings['spreadsheet_id'], $range);
                $values = $response->getValues();
                
                if (!empty($values)) {
                    $sheets_data[] = array(
                        'title' => $sheet_title,
                        'id' => $sheet_id,
                        'data' => $values
                    );
                } else {
                    $sheets_data[] = array(
                        'title' => $sheet_title,
                        'id' => $sheet_id,
                        'data' => array()
                    );
                }
            }
            
            return array(
                'success' => true,
                'sheets' => $sheets_data
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * AJAX handler for getting sheet data
     */
    public function ajax_get_data() {
        check_ajax_referer('wow_boost_gsheets_simple_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('You do not have sufficient permissions.');
        }
        
        $result = $this->get_sheet_data();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
}

// Initialize the plugin
$wow_boost_gsheets_simple = new WoW_Boost_GSheets_Simple();