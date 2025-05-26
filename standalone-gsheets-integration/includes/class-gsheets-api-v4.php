<?php
/**
 * کلاس API گوگل شیت - نسخه 4 اصلاح شده
 * 
 * @since 2.0.0
 */

if (!defined('ABSPATH')) exit;

class Standalone_GSheets_API_V4 {
    // کلاینت گوگل
    private $client = null;
    
    // سرویس گوگل شیت
    private $service = null;
    
    // آی‌دی اسپردشیت فعلی
    private $spreadsheet_id = null;
    
    // شمارنده درخواست‌ها برای rate limiting
    private $request_count = 0;
    private $last_request_time = 0;
    private $max_requests_per_100_seconds = 100; // API v4 limit
    
    // آمار عملکرد
    private $performance_stats = [
        'requests_made' => 0,
        'cache_hits' => 0,
        'batch_requests' => 0,
        'errors' => 0
    ];
    
    /**
     * سازنده
     */
    public function __construct($credentials_path) {
        $this->init_google_client($credentials_path);
    }
    
    /**
     * مقداردهی اولیه کلاینت گوگل با بهینه‌سازی‌های API v4
     */
    private function init_google_client($credentials_path) {
        try {
            if (empty($credentials_path) || !file_exists($credentials_path)) {
                throw new Exception('فایل اعتبارنامه گوگل پیدا نشد: ' . $credentials_path);
            }
            
            // بررسی وجود کلاس Google
            if (!class_exists('\Google\Client') && !class_exists('Google_Client')) {
                throw new Exception('Google Client library یافت نشد. لطفاً composer install اجرا کنید.');
            }
            
            // بررسی دسترسی فایل
            if (!is_readable($credentials_path)) {
                throw new Exception('دسترسی خواندن فایل اعتبارنامه وجود ندارد.');
            }
            
            // اعتبارسنجی محتوای JSON
            $credentials_content = file_get_contents($credentials_path);
            $credentials = json_decode($credentials_content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('فایل اعتبارنامه JSON معتبر نیست: ' . json_last_error_msg());
            }
            
            // بررسی فیلدهای ضروری Service Account
            $required_fields = ['type', 'project_id', 'private_key_id', 'private_key', 'client_email'];
            foreach ($required_fields as $field) {
                if (!isset($credentials[$field]) || empty($credentials[$field])) {
                    throw new Exception("فیلد ضروری {$field} در فایل اعتبارنامه یافت نشد.");
                }
            }
            
            if ($credentials['type'] !== 'service_account') {
                throw new Exception('فایل باید Service Account باشد.');
            }
            
            // سازگاری با نسخه‌های مختلف Google Client
            $client_class = class_exists('\Google\Client') ? '\Google\Client' : 'Google_Client';
            $client = new $client_class();
            
            $client->setApplicationName('Standalone Google Sheets Reader V4');
            
            // تنظیم scope های API v4
            if (class_exists('Google\Service\Sheets')) {
                $client->setScopes([
                    Google\Service\Sheets::SPREADSHEETS_READONLY,
                    Google\Service\Sheets::DRIVE_READONLY
                ]);
                $service_class = 'Google\Service\Sheets';
            } else {
                $client->setScopes([
                    Google_Service_Sheets::SPREADSHEETS_READONLY,
                    Google_Service_Sheets::DRIVE_READONLY
                ]);
                $service_class = 'Google_Service_Sheets';
            }
            
            $client->setAuthConfig($credentials_path);
            $client->setAccessType('offline');
            
            // تنظیمات بهینه‌سازی برای عملکرد بهتر
            $http_client_options = [
                'timeout' => 30,
                'connect_timeout' => 10,
                'verify' => true,
                'headers' => [
                    'User-Agent' => 'Standalone-GSheets-Reader/2.0 (WordPress)'
                ]
            ];
            
            if (class_exists('GuzzleHttp\Client') && method_exists($client, 'setHttpClient')) {
                $client->setHttpClient(new GuzzleHttp\Client($http_client_options));
            }
            
            // تست اتصال اولیه
            $service = new $service_class($client);
            
            $this->client = $client;
            $this->service = $service;
            
            return true;
            
        } catch (Exception $e) {
            error_log('Standalone GSheets V4 Error: ' . $e->getMessage());
            $this->performance_stats['errors']++;
            return false;
        }
    }
    
    /**
     * بررسی اینکه آیا API آماده است
     */
    public function is_ready() {
        return ($this->client !== null && $this->service !== null);
    }
    
    /**
     * تنظیم آی‌دی اسپردشیت
     */
    public function set_spreadsheet_id($spreadsheet_id) {
        $this->spreadsheet_id = $spreadsheet_id;
    }
    
    /**
     * Rate limiting بهینه برای API v4
     */
    private function check_rate_limit() {
        $current_time = time();
        
        // Reset counter every 100 seconds (API v4 window)
        if ($current_time - $this->last_request_time >= 100) {
            $this->request_count = 0;
            $this->last_request_time = $current_time;
        }
        
        if ($this->request_count >= $this->max_requests_per_100_seconds) {
            // صبر کردن تا reset شدن window
            $wait_time = 100 - ($current_time - $this->last_request_time);
            if ($wait_time > 0) {
                sleep(min($wait_time, 5)); // حداکثر 5 ثانیه صبر
                $this->request_count = 0;
                $this->last_request_time = time();
            }
        }
        
        $this->request_count++;
        $this->performance_stats['requests_made']++;
    }
    
    /**
     * تست اتصال پیشرفته برای API v4 (اصلاح شده)
     */
    public function test_connection($spreadsheet_id = null) {
        try {
            if ($spreadsheet_id === null) {
                $spreadsheet_id = $this->spreadsheet_id;
            }
            
            if (empty($spreadsheet_id)) {
                throw new Exception('آی‌دی اسپردشیت مشخص نشده است.');
            }
            
            $this->check_rate_limit();
            
            // دریافت metadata کامل (بهینه‌تر از خواندن داده)
            $spreadsheet = $this->service->spreadsheets->get(
                $spreadsheet_id,
                [
                    'includeGridData' => false,
                    'fields' => 'properties,sheets.properties'
                ]
            );
            
            $properties = $spreadsheet->getProperties();
            $sheets = [];
            $sheet_details = [];
            
            foreach ($spreadsheet->getSheets() as $sheet) {
                $sheet_props = $sheet->getProperties();
                $grid_props = $sheet_props->getGridProperties();
                
                $sheets[] = $sheet_props->getTitle();
                $sheet_details[] = [
                    'title' => $sheet_props->getTitle(),
                    'id' => $sheet_props->getSheetId(),
                    'index' => $sheet_props->getIndex(),
                    'type' => $sheet_props->getSheetType(),
                    'rows' => $grid_props ? $grid_props->getRowCount() : 0,
                    'columns' => $grid_props ? $grid_props->getColumnCount() : 0,
                    'frozen_rows' => $grid_props ? $grid_props->getFrozenRowCount() : 0,
                    'frozen_columns' => $grid_props ? $grid_props->getFrozenColumnCount() : 0,
                    'hidden' => $sheet_props->getHidden() ?? false
                ];
            }
            
            // تست خواندن یک سلول برای اطمینان از دسترسی read
            if (!empty($sheets)) {
                $this->check_rate_limit();
                $test_range = $sheets[0] . '!A1:A1';
                $this->service->spreadsheets_values->get($spreadsheet_id, $test_range);
            }
            
            // ساخت اطلاعات spreadsheet با بررسی وجود متدها
            $spreadsheet_info = [
                'title' => $properties->getTitle() ?? 'نامشخص'
            ];
            
            // بررسی امن متدهای مختلف
            if (method_exists($properties, 'getTimeZone')) {
                $spreadsheet_info['timezone'] = $properties->getTimeZone();
            }
            
            if (method_exists($properties, 'getLocale')) {
                $spreadsheet_info['locale'] = $properties->getLocale();
            }
            
            if (method_exists($properties, 'getAutoRecalc')) {
                $spreadsheet_info['auto_recalc'] = $properties->getAutoRecalc();
            }
            
            // تاریخ‌ها ممکن است در نسخه‌های جدید موجود نباشند
            if (method_exists($properties, 'getCreatedTime')) {
                $spreadsheet_info['created_time'] = $properties->getCreatedTime();
            }
            
            if (method_exists($properties, 'getUpdatedTime')) {
                $spreadsheet_info['updated_time'] = $properties->getUpdatedTime();
            }
            
            return [
                'success' => true,
                'message' => 'اتصال موفق به Google Sheets API v4',
                'api_version' => 'v4',
                'spreadsheet_info' => $spreadsheet_info,
                'sheets' => $sheets,
                'sheet_details' => $sheet_details,
                'total_sheets' => count($sheets),
                'visible_sheets' => count(array_filter($sheet_details, function($s) { return !$s['hidden']; })),
                'features' => [
                    'batch_operations' => true,
                    'formatting_support' => true,
                    'metadata_access' => true,
                    'advanced_search' => true,
                    'conditional_formatting' => true,
                    'charts_support' => true,
                    'pivot_tables' => true
                ],
                'performance' => $this->performance_stats
            ];
            
        } catch (Exception $e) {
            $this->performance_stats['errors']++;
            
            // بررسی نوع خطا
            $error_message = $e->getMessage();
            $error_code = $e->getCode();
            
            // اگر Google Service Exception باشد
            if (get_class($e) === 'Google_Service_Exception' || get_class($e) === 'Google\Service\Exception') {
                try {
                    $error_details = json_decode($e->getMessage(), true);
                    if (isset($error_details['error']['message'])) {
                        $error_message = $error_details['error']['message'];
                    }
                } catch (Exception $parse_error) {
                    // اگر پارس JSON نشد، همان پیام اصلی را نگه دار
                }
            }
            
            return [
                'success' => false,
                'message' => 'خطای Google API v4: ' . $error_message,
                'error_code' => $error_code,
                'api_version' => 'v4',
                'debug_info' => [
                    'spreadsheet_id' => $spreadsheet_id,
                    'error_class' => get_class($e)
                ]
            ];
        }
    }
    
    /**
     * دریافت لیست شیت‌ها با جزئیات کامل
     */
    public function get_sheets($spreadsheet_id = null) {
        try {
            if ($spreadsheet_id === null) {
                $spreadsheet_id = $this->spreadsheet_id;
            }
            
            if (empty($spreadsheet_id)) {
                throw new Exception('آی‌دی اسپردشیت مشخص نشده است.');
            }
            
            $this->check_rate_limit();
            
            $spreadsheet = $this->service->spreadsheets->get(
                $spreadsheet_id,
                ['fields' => 'sheets.properties']
            );
            
            $sheets = [];
            foreach ($spreadsheet->getSheets() as $sheet) {
                $sheet_props = $sheet->getProperties();
                $grid_props = $sheet_props->getGridProperties();
                
                $sheets[] = [
                    'id' => $sheet_props->getSheetId(),
                    'title' => $sheet_props->getTitle(),
                    'index' => $sheet_props->getIndex(),
                    'type' => $sheet_props->getSheetType(),
                    'hidden' => $sheet_props->getHidden() ?? false,
                    'grid_properties' => [
                        'row_count' => $grid_props ? $grid_props->getRowCount() : 0,
                        'column_count' => $grid_props ? $grid_props->getColumnCount() : 0,
                        'frozen_row_count' => $grid_props ? $grid_props->getFrozenRowCount() : 0,
                        'frozen_column_count' => $grid_props ? $grid_props->getFrozenColumnCount() : 0
                    ]
                ];
            }
            
            return $sheets;
            
        } catch (Exception $e) {
            error_log('Standalone GSheets V4: Error getting sheets: ' . $e->getMessage());
            $this->performance_stats['errors']++;
            return [];
        }
    }
    
    /**
     * دریافت headers با batch operation (ویژگی API v4)
     */
    public function get_multiple_sheet_headers($sheet_titles, $spreadsheet_id = null) {
        try {
            if ($spreadsheet_id === null) {
                $spreadsheet_id = $this->spreadsheet_id;
            }
            
            if (empty($sheet_titles)) {
                return [];
            }
            
            $this->check_rate_limit();
            $this->performance_stats['batch_requests']++;
            
            $ranges = [];
            foreach ($sheet_titles as $title) {
                $ranges[] = $title . '!1:1';
            }
            
            $response = $this->service->spreadsheets_values->batchGet(
                $spreadsheet_id,
                [
                    'ranges' => $ranges,
                    'valueRenderOption' => 'FORMATTED_VALUE'
                ]
            );
            
            $headers = [];
            $value_ranges = $response->getValueRanges();
            
            foreach ($value_ranges as $index => $range) {
                $values = $range->getValues();
                $sheet_title = $sheet_titles[$index];
                $headers[$sheet_title] = !empty($values[0]) ? $values[0] : [];
            }
            
            return $headers;
            
        } catch (Exception $e) {
            error_log('Standalone GSheets V4: Error getting headers: ' . $e->getMessage());
            $this->performance_stats['errors']++;
            return [];
        }
    }
    
    /**
     * دریافت هدرهای یک شیت (سطر اول)
     */
    public function get_sheet_headers($sheet_title, $spreadsheet_id = null) {
        $headers = $this->get_multiple_sheet_headers([$sheet_title], $spreadsheet_id);
        return isset($headers[$sheet_title]) ? $headers[$sheet_title] : [];
    }
    
    /**
     * جستجوی بهینه‌شده Discord ID با API v4
     */
    public function find_user_by_discord_id($discord_id, $sheet_title = null, $discord_id_column = 'Discord ID', $spreadsheet_id = null) {
    if (empty($discord_id)) {
        throw new Exception('Discord ID is not specified.');
    }
    
    // Validation: Discord ID باید 17-19 رقم باشد
    if (!preg_match('/^\d{17,19}$/', $discord_id)) {
        error_log('Invalid Discord ID format: ' . $discord_id);
        return [];
    }
    
    if ($spreadsheet_id === null) {
        $spreadsheet_id = $this->spreadsheet_id;
    }
    
    if (empty($spreadsheet_id)) {
        throw new Exception('Spreadsheet ID is not specified.');
    }
    
    // بهینه‌سازی Cache key - کوتاه‌تر و بهینه‌تر
    $cache_key = 'sg_v4_u_' . substr(md5($spreadsheet_id . '_' . $discord_id . '_' . $sheet_title . '_' . $discord_id_column), 0, 20);
    $cached_data = get_transient($cache_key);
    
    if ($cached_data !== false) {
        $this->performance_stats['cache_hits']++;
        return $cached_data;
    }
    
    try {
        $this->check_rate_limit();
        
        // دریافت metadata برای تشخیص sheets
        $spreadsheet = $this->service->spreadsheets->get(
            $spreadsheet_id,
            ['fields' => 'sheets.properties', 'includeGridData' => false]
        );
        
        $sheets_to_search = [];
        if ($sheet_title === null) {
            foreach ($spreadsheet->getSheets() as $sheet) {
                $props = $sheet->getProperties();
                if (!($props->getHidden() ?? false)) { // فقط sheets غیر مخفی
                    $sheets_to_search[] = $props->getTitle();
                }
            }
        } else {
            $sheets_to_search[] = $sheet_title;
        }
        
        if (empty($sheets_to_search)) {
            throw new Exception('No sheets found to search.');
        }
        
        // Batch request برای headers
        $headers = $this->get_multiple_sheet_headers($sheets_to_search, $spreadsheet_id);
        
        // پیدا کردن sheets که Discord ID column دارند
        $valid_sheets = [];
        foreach ($headers as $sheet_name => $header_row) {
            $discord_column_index = array_search($discord_id_column, $header_row);
            if ($discord_column_index !== false) {
                $valid_sheets[$sheet_name] = [
                    'headers' => $header_row,
                    'discord_column' => $this->numberToColumnLetter($discord_column_index + 1),
                    'discord_index' => $discord_column_index
                ];
            }
        }
        
        if (empty($valid_sheets)) {
            $result = [];
            set_transient($cache_key, $result, 60); // کش کوتاه برای نتایج منفی
            return $result;
        }
        
        // جستجوی optimized در ستون‌های Discord ID
        $user_data = [];
        foreach ($valid_sheets as $sheet_name => $sheet_info) {
            $this->check_rate_limit();
            
            // فقط ستون Discord ID را بخوان (بهینه‌سازی مهم)
            $discord_column_range = $sheet_name . '!' . $sheet_info['discord_column'] . ':' . $sheet_info['discord_column'];
            
            $response = $this->service->spreadsheets_values->get(
                $spreadsheet_id,
                $discord_column_range,
                ['valueRenderOption' => 'UNFORMATTED_VALUE'] // سریع‌تر برای جستجو
            );
            
            $values = $response->getValues();
            if (!$values) continue;
            
            // پیدا کردن ردیف
            foreach ($values as $row_index => $row) {
                if ($row_index === 0) continue; // Skip header
                
                if (isset($row[0]) && $row[0] == $discord_id) {
                    // پیدا شد! کل ردیف را بخوان
                    $actual_row = $row_index + 1;
                    $full_row_range = $sheet_name . '!' . $actual_row . ':' . $actual_row;
                    
                    $this->check_rate_limit();
                    $full_row_response = $this->service->spreadsheets_values->get(
                        $spreadsheet_id,
                        $full_row_range,
                        ['valueRenderOption' => 'FORMATTED_VALUE']
                    );
                    
                    $full_row_data = $full_row_response->getValues();
                    if (!empty($full_row_data[0])) {
                        $row_data = [];
                        foreach ($sheet_info['headers'] as $col_index => $header) {
                            $row_data[$header] = $full_row_data[0][$col_index] ?? '';
                        }
                        
                        $row_data['_row_index'] = $actual_row;
                        $row_data['_sheet_title'] = $sheet_name;
                        $row_data['_last_updated'] = time();
                        
                        $user_data[$sheet_name] = $row_data;
                    }
                    
                    if ($sheet_title !== null) {
                        break 2; // اگر sheet مشخص باشد، اولین نتیجه کافیه
                    }
                }
            }
        }
        
        // Cache result
        $cache_time = standalone_gsheets()->get_setting('cache_time', STANDALONE_GSHEETS_DEFAULT_CACHE_TIME);
        set_transient($cache_key, $user_data, $cache_time);
        
        return $user_data;
        
	} catch (Exception $e) {
        error_log('Standalone GSheets V4: Error finding user: ' . $e->getMessage());
        $this->performance_stats['errors']++;
        return [];
    }
}

/**
 * دریافت مقدار سلول با API v4 - بهینه شده
 */
public function get_cell_value($sheet_title, $cell, $spreadsheet_id = null) {
    try {
        if ($spreadsheet_id === null) {
            $spreadsheet_id = $this->spreadsheet_id;
        }
        
        if (empty($spreadsheet_id)) {
            throw new Exception('آی‌دی اسپردشیت مشخص نشده است.');
        }
        
        // بهینه‌سازی cache key
        $cache_key = 'sg_v4_c_' . substr(md5($spreadsheet_id . '_' . $sheet_title . '_' . $cell), 0, 20);
        $cached_value = get_transient($cache_key);
        
        if ($cached_value !== false) {
            $this->performance_stats['cache_hits']++;
            return $cached_value;
        }
        
        $this->check_rate_limit();
        
        $range = "$sheet_title!$cell";
        $response = $this->service->spreadsheets_values->get(
            $spreadsheet_id,
            $range,
            [
                'valueRenderOption' => 'FORMATTED_VALUE',
                'dateTimeRenderOption' => 'FORMATTED_STRING'
            ]
        );
        
        $values = $response->getValues();
        $value = isset($values[0][0]) ? $values[0][0] : null;
        
        $cache_time = standalone_gsheets()->get_setting('cache_time', STANDALONE_GSHEETS_DEFAULT_CACHE_TIME);
        set_transient($cache_key, $value, $cache_time);
        
        return $value;
        
    } catch (Exception $e) {
        error_log('Standalone GSheets V4: Error getting cell value: ' . $e->getMessage());
        $this->performance_stats['errors']++;
        return null;
    }
}
    
    /**
     * دریافت چندین سلول با batch operation (ویژگی API v4)
     */
    public function get_multiple_cells($cells_array, $spreadsheet_id = null) {
        try {
            if ($spreadsheet_id === null) {
                $spreadsheet_id = $this->spreadsheet_id;
            }
            
            if (empty($cells_array)) {
                return [];
            }
            
            $this->check_rate_limit();
            $this->performance_stats['batch_requests']++;
            
            $response = $this->service->spreadsheets_values->batchGet(
                $spreadsheet_id,
                [
                    'ranges' => $cells_array,
                    'valueRenderOption' => 'FORMATTED_VALUE',
                    'dateTimeRenderOption' => 'FORMATTED_STRING'
                ]
            );
            
            $results = [];
            $value_ranges = $response->getValueRanges();
            
            foreach ($value_ranges as $index => $range) {
                $values = $range->getValues();
                $results[$cells_array[$index]] = isset($values[0][0]) ? $values[0][0] : null;
            }
            
            return $results;
            
        } catch (Exception $e) {
            error_log('Standalone GSheets V4: Error getting multiple cells: ' . $e->getMessage());
            $this->performance_stats['errors']++;
            return [];
        }
    }
    
    /**
     * تبدیل شماره ستون به حرف
     */
    private function numberToColumnLetter($number) {
        $letter = '';
        while ($number > 0) {
            $number--;
            $letter = chr(65 + ($number % 26)) . $letter;
            $number = intval($number / 26);
        }
        return $letter;
    }
    
    /**
     * دریافت آمار API و عملکرد
     */
    public function get_api_info() {
        return [
            'version' => 'v4',
            'client_ready' => $this->is_ready(),
            'current_spreadsheet' => $this->spreadsheet_id,
            'rate_limiting' => [
                'requests_made' => $this->request_count,
                'max_per_100_seconds' => $this->max_requests_per_100_seconds,
                'remaining' => max(0, $this->max_requests_per_100_seconds - $this->request_count),
                'reset_time' => $this->last_request_time + 100
            ],
            'performance_stats' => $this->performance_stats,
            'features' => [
                'batch_operations' => true,
                'formatting_support' => true,
                'metadata_access' => true,
                'conditional_formatting' => true,
                'charts_support' => true,
                'pivot_tables' => true,
                'protected_ranges' => true,
                'developer_metadata' => true,
                'real_time_collaboration' => true
            ],
            'limits' => [
                'requests_per_100_seconds' => 100,
                'requests_per_100_seconds_per_user' => 100,
                'read_requests_per_100_seconds' => 300,
                'write_requests_per_100_seconds' => 300
            ]
        ];
    }
    
    /**
     * بررسی سلامت API
     */
    public function health_check() {
        $issues = [];
        
        // بررسی client
        if (!$this->is_ready()) {
            $issues[] = 'Google Client not initialized';
        }
        
        // بررسی rate limit
        if ($this->request_count >= $this->max_requests_per_100_seconds * 0.9) {
            $issues[] = 'Approaching rate limit';
        }
        
        // بررسی errors
        if ($this->performance_stats['errors'] > 10) {
            $issues[] = 'High error rate detected';
        }
        
        // بررسی آخرین درخواست
        if ($this->last_request_time > 0 && (time() - $this->last_request_time) > 3600) {
            $issues[] = 'No recent API activity';
        }
        
        return [
            'status' => empty($issues) ? 'healthy' : (count($issues) > 2 ? 'critical' : 'warning'),
            'issues' => $issues,
            'stats' => $this->performance_stats,
            'last_check' => time()
        ];
    }
    
    /**
     * پاک کردن کش‌ها با الگوی مشخص
     */
    public function clear_cache_by_pattern($pattern) {
        global $wpdb;
        
        if (strpos($pattern, '*') === false) {
            delete_transient($pattern);
            return true;
        }
        
        $pattern_sql = str_replace('*', '%', $pattern);
        
        // استفاده از prepared statement برای امنیت
        $sql = $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            '_transient_' . $pattern_sql,
            '_transient_timeout_' . $pattern_sql
        );
        
        $deleted = $wpdb->query($sql);
        
        // پاک کردن object cache نیز
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('standalone_gsheets');
        }
        
        return $deleted !== false;
    }
    
    /**
     * پاکسازی مقدار سلول
     */
    private function sanitize_cell_value($value) {
        if (is_string($value)) {
            // پاک کردن کاراکترهای مضر
            $value = strip_tags($value);
            $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            
            // حد مقدار طول رشته
            if (strlen($value) > 1000) {
                $value = substr($value, 0, 997) . '...';
            }
        }
        
        return $value;
    }
    
    /**
     * ری‌ست کردن آمار عملکرد
     */
    public function reset_performance_stats() {
        $this->performance_stats = [
            'requests_made' => 0,
            'cache_hits' => 0,
            'batch_requests' => 0,
            'errors' => 0
        ];
        
        $this->request_count = 0;
        $this->last_request_time = 0;
    }
    
    /**
     * تابع cleanup برای memory management
     */
    public function cleanup() {
        // پاک کردن منابع
        $this->client = null;
        $this->service = null;
        
        // پاک کردن آمار
        $this->reset_performance_stats();
    }
    
    /**
     * Destructor
     */
    public function __destruct() {
        $this->cleanup();
    }
}
