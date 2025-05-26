<?php
/**
 * کلاس بررسی سلامت سیستم
 * 
 * @since 2.0.0
 */

if (!defined('ABSPATH')) exit;

class Standalone_GSheets_Health_Check {
    
    /**
     * بررسی کلی سلامت سیستم
     */
    public function check_all() {
        $checks = [
            'php_version' => $this->check_php_version(),
            'php_extensions' => $this->check_php_extensions(),
            'composer_dependencies' => $this->check_composer_dependencies(),
            'credentials_file' => $this->check_credentials_file(),
            'api_connection' => $this->check_api_connection(),
            'cache_system' => $this->check_cache_system(),
            'file_permissions' => $this->check_file_permissions(),
            'wordpress_compatibility' => $this->check_wordpress_compatibility()
        ];
        
        // تعیین وضعیت کلی
        $overall_status = 'healthy';
        $warnings = 0;
        $errors = 0;
        
        foreach ($checks as $check) {
            if ($check['status'] === 'fail') {
                $errors++;
            } elseif ($check['status'] === 'warning') {
                $warnings++;
            }
        }
        
        if ($errors > 0) {
            $overall_status = 'critical';
        } elseif ($warnings > 2) {
            $overall_status = 'warning';
        }
        
        return [
            'overall_status' => $overall_status,
            'summary' => [
                'total_checks' => count($checks),
                'passed' => count(array_filter($checks, function($c) { return $c['status'] === 'pass'; })),
                'warnings' => $warnings,
                'errors' => $errors
            ],
            'checks' => $checks,
            'timestamp' => time(),
            'recommendations' => $this->get_recommendations($checks)
        ];
    }
    
    /**
     * بررسی نسخه PHP
     */
    private function check_php_version() {
        $current_version = PHP_VERSION;
        $required_version = '8.0.0';
        $recommended_version = '8.2.0';
        
        if (version_compare($current_version, $required_version, '<')) {
            return [
                'status' => 'fail',
                'message' => "نسخه PHP $current_version پشتیبانی نمی‌شود. حداقل $required_version مورد نیاز است.",
                'details' => [
                    'current' => $current_version,
                    'required' => $required_version,
                    'recommended' => $recommended_version
                ]
            ];
        } elseif (version_compare($current_version, $recommended_version, '<')) {
            return [
                'status' => 'warning',
                'message' => "نسخه PHP $current_version کار می‌کند اما $recommended_version توصیه می‌شود.",
                'details' => [
                    'current' => $current_version,
                    'required' => $required_version,
                    'recommended' => $recommended_version
                ]
            ];
        } else {
            return [
                'status' => 'pass',
                'message' => "نسخه PHP $current_version مناسب است.",
                'details' => [
                    'current' => $current_version,
                    'required' => $required_version,
                    'recommended' => $recommended_version
                ]
            ];
        }
    }
    
    /**
     * بررسی افزونه‌های PHP مورد نیاز
     */
    private function check_php_extensions() {
        $required_extensions = [
            'curl' => 'برای ارتباط با Google API',
            'json' => 'برای پردازش داده‌های JSON',
            'openssl' => 'برای ارتباطات امن',
            'mbstring' => 'برای پردازش رشته‌های UTF-8',
            'fileinfo' => 'برای شناسایی نوع فایل‌ها'
        ];
        
        $missing = [];
        $loaded = [];
        
        foreach ($required_extensions as $ext => $description) {
            if (extension_loaded($ext)) {
                $loaded[] = $ext;
            } else {
                $missing[] = ['extension' => $ext, 'description' => $description];
            }
        }
        
        if (!empty($missing)) {
            return [
                'status' => 'fail',
                'message' => 'افزونه‌های PHP مورد نیاز یافت نشدند: ' . implode(', ', array_column($missing, 'extension')),
                'details' => [
                    'missing' => $missing,
                    'loaded' => $loaded
                ]
            ];
        } else {
            return [
                'status' => 'pass',
                'message' => 'تمام افزونه‌های PHP مورد نیاز در دسترس هستند.',
                'details' => [
                    'loaded' => $loaded
                ]
            ];
        }
    }
    
    /**
     * بررسی وابستگی‌های Composer
     */
    private function check_composer_dependencies() {
        $composer_lock_path = STANDALONE_GSHEETS_PATH . 'composer.lock';
        $vendor_path = STANDALONE_GSHEETS_PATH . 'vendor/autoload.php';
        
        if (!file_exists($vendor_path)) {
            return [
                'status' => 'fail',
                'message' => 'پوشه vendor یافت نشد. لطفاً composer install اجرا کنید.',
                'details' => [
                    'vendor_path' => $vendor_path,
                    'exists' => false
                ]
            ];
        }
        
        if (!class_exists('Google_Client')) {
            return [
                'status' => 'fail',
                'message' => 'Google Client library بارگذاری نشده است.',
                'details' => [
                    'google_client_exists' => false
                ]
            ];
        }
        
        // بررسی نسخه Google API Client
        if (defined('Google_Client::LIBVER')) {
            $version = Google_Client::LIBVER;
        } else {
            $version = 'unknown';
        }
        
        $lock_data = [];
        if (file_exists($composer_lock_path)) {
            $lock_content = file_get_contents($composer_lock_path);
            $lock_data = json_decode($lock_content, true);
        }
        
        return [
            'status' => 'pass',
            'message' => 'وابستگی‌های Composer بارگذاری شده‌اند.',
            'details' => [
                'vendor_exists' => true,
                'google_client_version' => $version,
                'composer_lock_exists' => file_exists($composer_lock_path),
                'lock_hash' => isset($lock_data['content-hash']) ? $lock_data['content-hash'] : null
            ]
        ];
    }
    
    /**
     * بررسی فایل اعتبارنامه
     */
    private function check_credentials_file() {
        $credentials_path = standalone_gsheets()->get_setting('credentials_path');
        
        if (empty($credentials_path)) {
            return [
                'status' => 'fail',
                'message' => 'فایل اعتبارنامه Google تنظیم نشده است.',
                'details' => [
                    'path' => null,
                    'exists' => false
                ]
            ];
        }
        
        if (!file_exists($credentials_path)) {
            return [
                'status' => 'fail',
                'message' => 'فایل اعتبارنامه در مسیر مشخص شده یافت نشد.',
                'details' => [
                    'path' => $credentials_path,
                    'exists' => false
                ]
            ];
        }
        
        if (!is_readable($credentials_path)) {
            return [
                'status' => 'fail',
                'message' => 'فایل اعتبارنامه قابل خواندن نیست.',
                'details' => [
                    'path' => $credentials_path,
                    'exists' => true,
                    'readable' => false
                ]
            ];
        }
        
        // بررسی محتوای JSON
        $credentials_content = file_get_contents($credentials_path);
        $credentials = json_decode($credentials_content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'status' => 'fail',
                'message' => 'فایل اعتبارنامه JSON معتبر نیست: ' . json_last_error_msg(),
                'details' => [
                    'path' => $credentials_path,
                    'exists' => true,
                    'readable' => true,
                    'valid_json' => false,
                    'json_error' => json_last_error_msg()
                ]
            ];
        }
        
        // بررسی فیلدهای ضروری
        $required_fields = ['type', 'project_id', 'private_key_id', 'private_key', 'client_email'];
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if (!isset($credentials[$field]) || empty($credentials[$field])) {
                $missing_fields[] = $field;
            }
        }
        
        if (!empty($missing_fields)) {
            return [
                'status' => 'fail',
                'message' => 'فیلدهای ضروری در فایل اعتبارنامه یافت نشدند: ' . implode(', ', $missing_fields),
                'details' => [
                    'path' => $credentials_path,
                    'exists' => true,
                    'readable' => true,
                    'valid_json' => true,
                    'missing_fields' => $missing_fields
                ]
            ];
        }
        
        if ($credentials['type'] !== 'service_account') {
            return [
                'status' => 'warning',
                'message' => 'فایل اعتبارنامه از نوع Service Account نیست.',
                'details' => [
                    'path' => $credentials_path,
                    'type' => $credentials['type'],
                    'expected_type' => 'service_account'
                ]
            ];
        }
        
        // بررسی تاریخ انقضا کلید (اگر موجود باشد)
        $key_age_days = null;
        if (isset($credentials['private_key'])) {
            // استخراج تاریخ از private key اگر ممکن باشد
            // برای سادگی، فقط check کنیم که key خالی نباشد
            if (strlen($credentials['private_key']) < 100) {
                return [
                    'status' => 'warning',
                    'message' => 'Private key خیلی کوتاه به نظر می‌رسد.',
                    'details' => [
                        'key_length' => strlen($credentials['private_key'])
                    ]
                ];
            }
        }
        
        return [
            'status' => 'pass',
            'message' => 'فایل اعتبارنامه Google معتبر است.',
            'details' => [
                'path' => $credentials_path,
                'exists' => true,
                'readable' => true,
                'valid_json' => true,
                'type' => $credentials['type'],
                'project_id' => $credentials['project_id'],
                'client_email' => $credentials['client_email'],
                'file_size' => filesize($credentials_path),
                'last_modified' => filemtime($credentials_path)
            ]
        ];
    }
    
    /**
     * بررسی اتصال API
     */
    private function check_api_connection() {
        $api = standalone_gsheets()->api;
        
        if (!$api || !$api->is_ready()) {
            return [
                'status' => 'fail',
                'message' => 'API Google Sheets آماده نیست.',
                'details' => [
                    'api_initialized' => $api !== null,
                    'api_ready' => $api ? $api->is_ready() : false
                ]
            ];
        }
        
        $spreadsheet_id = standalone_gsheets()->get_setting('spreadsheet_id');
        if (empty($spreadsheet_id)) {
            return [
                'status' => 'warning',
                'message' => 'آی‌دی اسپردشیت پیش‌فرض تنظیم نشده است.',
                'details' => [
                    'api_ready' => true,
                    'default_spreadsheet_set' => false
                ]
            ];
        }
        
        // تست اتصال واقعی
        try {
            $test_result = $api->test_connection($spreadsheet_id);
            
            if ($test_result['success']) {
                return [
                    'status' => 'pass',
                    'message' => 'اتصال به Google Sheets API v4 موفق است.',
                    'details' => array_merge([
                        'api_ready' => true,
                        'connection_successful' => true
                    ], $test_result)
                ];
            } else {
                return [
                    'status' => 'fail',
                    'message' => 'خطا در اتصال به Google Sheets: ' . $test_result['message'],
                    'details' => array_merge([
                        'api_ready' => true,
                        'connection_successful' => false
                    ], $test_result)
                ];
            }
        } catch (Exception $e) {
            return [
                'status' => 'fail',
                'message' => 'خطا در تست اتصال: ' . $e->getMessage(),
                'details' => [
                    'api_ready' => true,
                    'connection_successful' => false,
                    'error' => $e->getMessage()
                ]
            ];
        }
    }
    
    /**
     * بررسی سیستم کش
     */
    private function check_cache_system() {
        $cache_time = standalone_gsheets()->get_setting('cache_time', 300);
        
        // تست نوشتن و خواندن transient
        $test_key = 'gsheets_health_test_' . time();
        $test_value = 'test_data_' . wp_generate_password(8, false);
        
        set_transient($test_key, $test_value, 60);
        $retrieved_value = get_transient($test_key);
        delete_transient($test_key);
        
        if ($retrieved_value !== $test_value) {
            return [
                'status' => 'warning',
                'message' => 'سیستم cache ممکن است درست کار نکند.',
                'details' => [
                    'cache_time_setting' => $cache_time,
                    'transient_test_passed' => false,
                    'expected' => $test_value,
                    'received' => $retrieved_value
                ]
            ];
        }
        
        // بررسی object cache
        $object_cache_available = function_exists('wp_cache_set');
        
        // بررسی تعداد transient های موجود
        global $wpdb;
        $transient_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_standalone_gsheets_%'"
        );
        
        $status = 'pass';
        $message = 'سیستم cache درست کار می‌کند.';
        
        if ($transient_count > 100) {
            $status = 'warning';
            $message = 'تعداد زیادی cache entry وجود دارد، ممکن است نیاز به پاکسازی باشد.';
        }
        
        return [
            'status' => $status,
            'message' => $message,
            'details' => [
                'cache_time_setting' => $cache_time,
                'transient_test_passed' => true,
                'object_cache_available' => $object_cache_available,
                'gsheets_transients_count' => (int)$transient_count
            ]
        ];
    }
    
    /**
     * بررسی مجوزهای فایل
     */
    private function check_file_permissions() {
        $upload_dir = wp_upload_dir();
        $base_upload_dir = $upload_dir['basedir'];
        $credentials_dir = $base_upload_dir . '/standalone-gsheets-credentials';
        
        $checks = [
            'upload_dir_writable' => wp_is_writable($base_upload_dir),
            'credentials_dir_exists' => file_exists($credentials_dir),
            'credentials_dir_writable' => file_exists($credentials_dir) ? wp_is_writable($credentials_dir) : null
        ];
        
        $issues = [];
        
        if (!$checks['upload_dir_writable']) {
            $issues[] = 'پوشه uploads قابل نوشتن نیست';
        }
        
        if ($checks['credentials_dir_exists'] && !$checks['credentials_dir_writable']) {
            $issues[] = 'پوشه credentials قابل نوشتن نیست';
        }
        
        // بررسی .htaccess برای امنیت
        $htaccess_exists = false;
        $htaccess_secure = false;
        
        if ($checks['credentials_dir_exists']) {
            $htaccess_path = $credentials_dir . '/.htaccess';
            $htaccess_exists = file_exists($htaccess_path);
            
            if ($htaccess_exists) {
                $htaccess_content = file_get_contents($htaccess_path);
                $htaccess_secure = (strpos($htaccess_content, 'Deny from all') !== false);
            }
        }
        
        if (!empty($issues)) {
            return [
                'status' => 'fail',
                'message' => 'مشکلات مجوز فایل: ' . implode(', ', $issues),
                'details' => array_merge($checks, [
                    'upload_base_dir' => $base_upload_dir,
                    'credentials_dir' => $credentials_dir,
                    'htaccess_exists' => $htaccess_exists,
                    'htaccess_secure' => $htaccess_secure,
                    'issues' => $issues
                ])
            ];
        }
        
        $status = 'pass';
        $message = 'مجوزهای فایل مناسب است.';
        
        if ($checks['credentials_dir_exists'] && (!$htaccess_exists || !$htaccess_secure)) {
            $status = 'warning';
            $message = 'پوشه credentials محافظت امنیتی کاملی ندارد.';
        }
        
        return [
            'status' => $status,
            'message' => $message,
            'details' => array_merge($checks, [
                'upload_base_dir' => $base_upload_dir,
                'credentials_dir' => $credentials_dir,
                'htaccess_exists' => $htaccess_exists,
                'htaccess_secure' => $htaccess_secure
            ])
        ];
    }
    
    /**
     * بررسی سازگاری با WordPress
     */
    private function check_wordpress_compatibility() {
        global $wp_version;
        
        $required_wp_version = '5.0';
        $recommended_wp_version = '6.0';
        
        $issues = [];
        
        // بررسی نسخه WordPress
        if (version_compare($wp_version, $required_wp_version, '<')) {
            $issues[] = "WordPress $wp_version پشتیبانی نمی‌شود";
        }
        
        // بررسی plugins مرتبط
        $related_plugins = [
            'elementor/elementor.php' => 'Elementor',
            'discord-login/discord-login.php' => 'Discord Login',
            'wp-discord/wp-discord.php' => 'WP Discord'
        ];
        
        $active_related = [];
        $inactive_related = [];
        
        foreach ($related_plugins as $plugin_file => $plugin_name) {
            if (is_plugin_active($plugin_file)) {
                $active_related[] = $plugin_name;
            } elseif (file_exists(WP_PLUGIN_DIR . '/' . $plugin_file)) {
                $inactive_related[] = $plugin_name;
            }
        }
        
        // بررسی theme compatibility
        $current_theme = wp_get_theme();
        $theme_support = [
            'widgets' => current_theme_supports('widgets'),
            'shortcodes' => true, // همیشه پشتیبانی می‌شود
            'custom_css' => current_theme_supports('custom-css')
        ];
        
        $status = 'pass';
        $message = 'سازگاری با WordPress مناسب است.';
        
        if (!empty($issues)) {
            $status = 'fail';
            $message = 'مشکلات سازگاری: ' . implode(', ', $issues);
        } elseif (version_compare($wp_version, $recommended_wp_version, '<')) {
            $status = 'warning';
            $message = "WordPress $wp_version کار می‌کند اما $recommended_wp_version توصیه می‌شود.";
        }
        
        return [
            'status' => $status,
            'message' => $message,
            'details' => [
                'wp_version' => $wp_version,
                'required_version' => $required_wp_version,
                'recommended_version' => $recommended_wp_version,
                'current_theme' => $current_theme->get('Name'),
                'theme_support' => $theme_support,
                'active_related_plugins' => $active_related,
                'inactive_related_plugins' => $inactive_related,
                'multisite' => is_multisite(),
                'debug_mode' => defined('WP_DEBUG') && WP_DEBUG
            ]
        ];
    }
    
    /**
     * دریافت توصیه‌های بهبود
     */
    private function get_recommendations($checks) {
        $recommendations = [];
        
        foreach ($checks as $check_name => $check_result) {
            if ($check_result['status'] === 'fail') {
                switch ($check_name) {
                    case 'php_version':
                        $recommendations[] = 'PHP خود را به نسخه 8.2 یا بالاتر ارتقا دهید.';
                        break;
                    case 'php_extensions':
                        $recommendations[] = 'افزونه‌های PHP مورد نیاز را نصب کنید.';
                        break;
                    case 'composer_dependencies':
                        $recommendations[] = 'در پوشه پلاگین دستور composer install اجرا کنید.';
                        break;
                    case 'credentials_file':
                        $recommendations[] = 'فایل اعتبارنامه Google Service Account معتبر آپلود کنید.';
                        break;
                    case 'api_connection':
                        $recommendations[] = 'تنظیمات API و آی‌دی اسپردشیت را بررسی کنید.';
                        break;
                    case 'file_permissions':
                        $recommendations[] = 'مجوزهای نوشتن پوشه uploads را بررسی کنید.';
                        break;
                }
            } elseif ($check_result['status'] === 'warning') {
                switch ($check_name) {
                    case 'cache_system':
                        $recommendations[] = 'کش قدیمی را پاک کنید یا تنظیمات cache را بهینه کنید.';
                        break;
                    case 'file_permissions':
                        $recommendations[] = 'فایل .htaccess برای محافظت از پوشه credentials اضافه کنید.';
                        break;
                    case 'wordpress_compatibility':
                        $recommendations[] = 'WordPress خود را به آخرین نسخه ارتقا دهید.';
                        break;
                }
            }
        }
        
        // توصیه‌های عمومی
        if (empty($recommendations)) {
            $recommendations[] = 'سیستم در وضعیت مطلوبی است. برای بهینه‌سازی بیشتر cache time را تنظیم کنید.';
        }
        
        return $recommendations;
    }
    
    /**
     * تولید گزارش مفصل
     */
    public function generate_detailed_report() {
        $health_data = $this->check_all();
        
        $report = [
            'plugin_info' => [
                'name' => 'Standalone Google Sheets Reader',
                'version' => STANDALONE_GSHEETS_VERSION,
                'api_version' => STANDALONE_GSHEETS_API_VERSION,
                'plugin_path' => STANDALONE_GSHEETS_PATH,
                'plugin_url' => STANDALONE_GSHEETS_URL
            ],
            'environment' => [
                'php_version' => PHP_VERSION,
                'wordpress_version' => get_bloginfo('version'),
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize')
            ],
            'health_check' => $health_data,
            'generated_at' => date('Y-m-d H:i:s'),
            'generated_by' => get_current_user_id()
        ];
        
        return $report;
    }
}