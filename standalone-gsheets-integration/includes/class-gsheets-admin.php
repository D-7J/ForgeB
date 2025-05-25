<?php
/**
 * کلاس بخش مدیریت مستقل - نسخه 2.0.1
 * 
 * @since 2.0.1
 */

if (!defined('ABSPATH')) exit;

class Standalone_GSheets_Admin {
    
    /**
     * سازنده
     */
    public function __construct() {
        // افزودن منوی تنظیمات
        add_action('admin_menu', [$this, 'add_settings_page']);
        
        // ثبت تنظیمات
        add_action('admin_init', [$this, 'register_settings']);
        
        // افزودن لینک تنظیمات در صفحه پلاگین‌ها
        add_filter('plugin_action_links_' . plugin_basename(STANDALONE_GSHEETS_PATH . 'standalone-gsheets-integration.php'), [$this, 'add_settings_link']);
        
        // اسکریپت‌ها و استایل‌های مدیریت
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        
        // AJAX handlers
        add_action('wp_ajax_standalone_gsheets_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_standalone_gsheets_get_sheet_fields', [$this, 'ajax_get_sheet_fields']);
        add_action('wp_ajax_standalone_gsheets_health_check', [$this, 'ajax_health_check']);
        add_action('wp_ajax_standalone_gsheets_clear_cache', [$this, 'ajax_clear_cache']);
        add_action('wp_ajax_standalone_gsheets_advanced_cache_clear', [$this, 'ajax_advanced_cache_clear']);
        add_action('wp_ajax_standalone_gsheets_export_settings', [$this, 'ajax_export_settings']);
        add_action('wp_ajax_standalone_gsheets_import_settings', [$this, 'ajax_import_settings']);
        
        // افزودن dashboard widget
        add_action('wp_dashboard_setup', [$this, 'add_dashboard_widget']);
        
        // نوتیفیکیشن‌های admin
        add_action('admin_notices', [$this, 'show_admin_notices']);
    }
    
    /**
     * افزودن منوی تنظیمات
     */
    public function add_settings_page() {
        $capability = 'manage_options';
        
        // صفحه اصلی تنظیمات
        add_options_page(
            esc_html__('تنظیمات خواندن گوگل شیت', 'standalone-gsheets'),
            esc_html__('گوگل شیت', 'standalone-gsheets'),
            $capability,
            'standalone-gsheets-settings',
            [$this, 'render_settings_page']
        );
        
        // زیرصفحه Health Check
        add_submenu_page(
            'options-general.php',
            esc_html__('بررسی سلامت گوگل شیت', 'standalone-gsheets'),
            esc_html__('سلامت گوگل شیت', 'standalone-gsheets'),
            $capability,
            'standalone-gsheets-health',
            [$this, 'render_health_page']
        );
    }
    
    /**
     * ثبت تنظیمات
     */
    public function register_settings() {
        register_setting(
            'standalone_gsheets_settings',
            'standalone_gsheets_settings',
            [$this, 'validate_settings']
        );
        
        // ثبت settings sections
        add_settings_section(
            'gsheets_general',
            esc_html__('تنظیمات عمومی', 'standalone-gsheets'),
            [$this, 'render_general_section'],
            'standalone_gsheets_settings'
        );
        
        add_settings_section(
            'gsheets_advanced',
            esc_html__('تنظیمات پیشرفته', 'standalone-gsheets'),
            [$this, 'render_advanced_section'],
            'standalone_gsheets_settings'
        );
        
        // فیلدهای تنظیمات
        $this->add_settings_fields();
    }
    
    /**
     * افزودن فیلدهای تنظیمات
     */
    private function add_settings_fields() {
        // فیلدهای عمومی
        add_settings_field(
            'credentials_file',
            esc_html__('فایل اعتبارنامه Google', 'standalone-gsheets'),
            [$this, 'render_credentials_field'],
            'standalone_gsheets_settings',
            'gsheets_general'
        );
        
        add_settings_field(
            'spreadsheet_id',
            esc_html__('آی‌دی اسپردشیت پیش‌فرض', 'standalone-gsheets'),
            [$this, 'render_spreadsheet_id_field'],
            'standalone_gsheets_settings',
            'gsheets_general'
        );
        
        add_settings_field(
            'discord_id_column',
            esc_html__('ستون Discord ID پیش‌فرض', 'standalone-gsheets'),
            [$this, 'render_discord_column_field'],
            'standalone_gsheets_settings',
            'gsheets_general'
        );
        
        // فیلدهای پیشرفته
        add_settings_field(
            'cache_time',
            esc_html__('زمان کش (ثانیه)', 'standalone-gsheets'),
            [$this, 'render_cache_time_field'],
            'standalone_gsheets_settings',
            'gsheets_advanced'
        );
        
        add_settings_field(
            'rate_limit_enabled',
            esc_html__('فعال‌سازی محدودیت نرخ', 'standalone-gsheets'),
            [$this, 'render_rate_limit_field'],
            'standalone_gsheets_settings',
            'gsheets_advanced'
        );
        
        add_settings_field(
            'debug_mode',
            esc_html__('حالت دیباگ', 'standalone-gsheets'),
            [$this, 'render_debug_mode_field'],
            'standalone_gsheets_settings',
            'gsheets_advanced'
        );
    }
    
    /**
     * Handle form submission manually - نسخه اصلاح شده
     */
    private function handle_form_submission() {
        if (!isset($_POST['gsheets_nonce']) || !wp_verify_nonce($_POST['gsheets_nonce'], 'gsheets_settings_nonce')) {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $settings = get_option('standalone_gsheets_settings', []);
        $credentials_uploaded = false;
        $new_credentials_path = '';
        
        // Handle file upload FIRST
        if (isset($_FILES['credentials_file']) && $_FILES['credentials_file']['error'] === UPLOAD_ERR_OK) {
            $upload_result = $this->handle_credentials_upload($_FILES['credentials_file']);
            
            if ($upload_result['success']) {
                $new_credentials_path = $upload_result['path'];
                $credentials_uploaded = true;
                add_settings_error(
                    'standalone_gsheets_settings',
                    'credentials_uploaded',
                    'فایل اعتبارنامه با موفقیت آپلود شد.',
                    'success'
                );
            } else {
                add_settings_error(
                    'standalone_gsheets_settings',
                    'credentials_error',
                    $upload_result['message'],
                    'error'
                );
            }
        }
        
        // Update other settings
        if (isset($_POST['standalone_gsheets_settings'])) {
            foreach ($_POST['standalone_gsheets_settings'] as $key => $value) {
                if ($key !== 'credentials_path') { // Don't overwrite credentials path
                    $settings[$key] = sanitize_text_field($value);
                }
            }
        }
        
        // Set credentials path AFTER processing other settings
        if ($credentials_uploaded) {
            $settings['credentials_path'] = $new_credentials_path;
        }
        
        // Save settings
        update_option('standalone_gsheets_settings', $settings);
        
        // Clear cache if needed
        if (function_exists('standalone_gsheets')) {
            $plugin = standalone_gsheets();
            if ($plugin && $plugin->api) {
                $plugin->api->clear_cache_by_pattern('standalone_gsheets_*');
            }
        }
        
        // Re-initialize API with new credentials
        if ($credentials_uploaded) {
            if (function_exists('standalone_gsheets')) {
                $plugin = standalone_gsheets();
                if ($plugin) {
                    $plugin->init_api();
                }
            }
        }
        
        add_settings_error(
            'standalone_gsheets_settings',
            'settings_updated',
            'تنظیمات ذخیره شد.',
            'success'
        );
    }
    
    /**
     * اعتبارسنجی پیشرفته تنظیمات - نسخه اصلاح شده
     */
    public function validate_settings($input) {
        $valid = [];
        $current_settings = get_option('standalone_gsheets_settings', []);
        
        // حفظ credentials path - فقط اگر در input موجود نباشد
        if (isset($input['credentials_path']) && !empty($input['credentials_path'])) {
            $valid['credentials_path'] = sanitize_text_field($input['credentials_path']);
        } else {
            $valid['credentials_path'] = $current_settings['credentials_path'] ?? '';
        }
        
        // آی‌دی اسپردشیت
        $valid['spreadsheet_id'] = sanitize_text_field($input['spreadsheet_id'] ?? '');
        
        // ستون آی‌دی دیسکورد
        $valid['discord_id_column'] = sanitize_text_field($input['discord_id_column'] ?? 'Discord ID');
        
        // زمان کش
        $cache_time = absint($input['cache_time'] ?? 300);
        $min_cache = defined('STANDALONE_GSHEETS_MIN_CACHE_TIME') ? STANDALONE_GSHEETS_MIN_CACHE_TIME : 60;
        $max_cache = defined('STANDALONE_GSHEETS_MAX_CACHE_TIME') ? STANDALONE_GSHEETS_MAX_CACHE_TIME : 3600;
        $valid['cache_time'] = max($min_cache, min($max_cache, $cache_time));
        
        // تنظیمات پیشرفته
        $valid['rate_limit_enabled'] = isset($input['rate_limit_enabled']) ? true : false;
        $valid['debug_mode'] = isset($input['debug_mode']) ? true : false;
        $valid['api_version'] = 'v4'; // فورس API v4
        
        return $valid;
    }
    
    /**
     * مدیریت آپلود فایل اعتبارنامه با امنیت بالا
     */
    private function handle_credentials_upload($file) {
        try {
            // بررسی نوع فایل
            $file_info = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($file_info, $file['tmp_name']);
            finfo_close($file_info);
            
            if ($mime_type !== 'application/json' && $mime_type !== 'text/plain') {
                throw new Exception('نوع فایل مجاز نیست. فقط فایل‌های JSON پذیرفته می‌شوند.');
            }
            
            // بررسی اندازه فایل (حداکثر 1MB)
            if ($file['size'] > 1024 * 1024) {
                throw new Exception('حجم فایل بیش از حد مجاز است. حداکثر 1MB.');
            }
            
            // بررسی محتوای JSON
            $json_content = file_get_contents($file['tmp_name']);
            $credentials = json_decode($json_content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('فایل JSON معتبر نیست: ' . json_last_error_msg());
            }
            
            // بررسی فیلدهای ضروری Service Account
            $required_fields = ['type', 'project_id', 'private_key_id', 'private_key', 'client_email'];
            foreach ($required_fields as $field) {
                if (!isset($credentials[$field]) || empty($credentials[$field])) {
                    throw new Exception("فیلد ضروری {$field} در فایل اعتبارنامه یافت نشد.");
                }
            }
            
            if ($credentials['type'] !== 'service_account') {
                throw new Exception('فایل باید از نوع Service Account باشد.');
            }
            
            // ایجاد دایرکتوری امن - استفاده از STANDALONE_GSHEETS_PRIVATE_DIR
            $credentials_dir = defined('STANDALONE_GSHEETS_PRIVATE_DIR') ? STANDALONE_GSHEETS_PRIVATE_DIR : WP_CONTENT_DIR . '/private/gsheets-credentials';
            
            if (!file_exists($credentials_dir)) {
                if (!wp_mkdir_p($credentials_dir)) {
                    throw new Exception('نمی‌توان دایرکتوری credentials ایجاد کرد.');
                }
                
                // حفاظت دایرکتوری
                $htaccess_content = "Order deny,allow\nDeny from all\n<Files ~ \"\.json$\">\nOrder deny,allow\nDeny from all\n</Files>";
                file_put_contents($credentials_dir . '/.htaccess', $htaccess_content);
                file_put_contents($credentials_dir . '/index.php', '<?php // Silence is golden');
                
                // تنظیم مجوزهای دایرکتوری
                chmod($credentials_dir, 0700); // فقط owner دسترسی دارد
            }
            
            // حذف فایل قدیمی اگر replace_credentials فعال باشد
            $old_credentials = '';
            if (function_exists('standalone_gsheets')) {
                $plugin = standalone_gsheets();
                if ($plugin) {
                    $old_credentials = $plugin->get_setting('credentials_path');
                }
            }
            
            $should_replace = isset($_POST['replace_credentials']) && $_POST['replace_credentials'] == '1';
            
            if (!empty($old_credentials) && file_exists($old_credentials) && $should_replace) {
                @unlink($old_credentials);
                
                // Log removal if logger is available
                if (function_exists('standalone_gsheets')) {
                    $plugin = standalone_gsheets();
                    if ($plugin && $plugin->logger) {
                        $plugin->logger->info('Old credentials file removed');
                    }
                }
            }
            
            // ایجاد نام فایل امن با hash قوی‌تر
            $filename = 'google-credentials-' . bin2hex(random_bytes(16)) . '.json';
            $credentials_path = $credentials_dir . '/' . $filename;
            
            // رمزنگاری محتوای فایل (اختیاری - برای امنیت بیشتر)
            // $encrypted_content = $this->encrypt_credentials($json_content);
            
            // ذخیره محتوای JSON به صورت مستقیم
            if (!file_put_contents($credentials_path, $json_content)) {
                throw new Exception('خطا در ذخیره فایل.');
            }
            
            // تنظیم مجوزهای امن
            chmod($credentials_path, 0600); // فقط owner بتواند بخواند
            
            // Log successful upload
            if (function_exists('standalone_gsheets')) {
                $plugin = standalone_gsheets();
                if ($plugin && $plugin->logger) {
                    $plugin->logger->info('Credentials uploaded successfully', [
                        'project_id' => $credentials['project_id'],
                        'client_email' => substr($credentials['client_email'], 0, 15) . '...'
                    ]);
                }
            }
            
            // مقداردهی مجدد API با credentials جدید
            if (function_exists('standalone_gsheets')) {
                $plugin = standalone_gsheets();
                if ($plugin) {
                    if ($plugin->api) {
                        $plugin->api->cleanup();
                    }
                    $plugin->init_api();
                }
            }
            
            return [
                'success' => true,
                'path' => $credentials_path,
                'message' => 'فایل اعتبارنامه با موفقیت آپلود شد.'
            ];
            
        } catch (Exception $e) {
            if (function_exists('standalone_gsheets')) {
                $plugin = standalone_gsheets();
                if ($plugin && $plugin->logger) {
                    $plugin->logger->error('Credentials upload failed', ['error' => $e->getMessage()]);
                }
            }
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * متد اختیاری برای رمزنگاری credentials
     */
    private function encrypt_credentials($data) {
        $key = wp_salt('auth');
        $iv = random_bytes(16);
        
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * متد اختیاری برای رمزگشایی credentials
     */
    private function decrypt_credentials($encrypted_data) {
        $key = wp_salt('auth');
        $data = base64_decode($encrypted_data);
        
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
    
    /**
     * افزودن لینک تنظیمات در صفحه پلاگین‌ها
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('options-general.php?page=standalone-gsheets-settings') . '">' . 
                         esc_html__('تنظیمات', 'standalone-gsheets') . '</a>';
        $health_link = '<a href="' . admin_url('options-general.php?page=standalone-gsheets-health') . '">' . 
                       esc_html__('سلامت', 'standalone-gsheets') . '</a>';
        
        array_unshift($links, $settings_link, $health_link);
        return $links;
    }
    
    /**
     * اسکریپت‌ها و استایل‌های مدیریت
     */
    public function enqueue_admin_scripts($hook) {
        // فقط در صفحات مرتبط با پلاگین
        if (strpos($hook, 'standalone-gsheets') === false) {
            return;
        }
        
        wp_enqueue_style(
            'standalone-gsheets-admin',
            STANDALONE_GSHEETS_URL . 'assets/css/admin.css',
            [],
            STANDALONE_GSHEETS_VERSION
        );
        
        wp_enqueue_script(
            'standalone-gsheets-admin',
            STANDALONE_GSHEETS_URL . 'assets/js/admin.js',
            ['jquery'],
            STANDALONE_GSHEETS_VERSION,
            true
        );
        
        wp_localize_script('standalone-gsheets-admin', 'standalone_gsheets_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('standalone_gsheets_admin_nonce'),
            'api_version' => STANDALONE_GSHEETS_API_VERSION,
            'plugin_version' => STANDALONE_GSHEETS_VERSION,
            'strings' => [
                'testing' => esc_html__('در حال تست...', 'standalone-gsheets'),
                'success' => esc_html__('اتصال موفق!', 'standalone-gsheets'),
                'error' => esc_html__('خطا:', 'standalone-gsheets'),
                'connection_error' => esc_html__('خطا در اتصال به سرور', 'standalone-gsheets'),
                'api_v4_connected' => esc_html__('متصل به Google Sheets API v4', 'standalone-gsheets'),
                'health_checking' => esc_html__('در حال بررسی سلامت...', 'standalone-gsheets'),
                'cache_clearing' => esc_html__('در حال پاک کردن کش...', 'standalone-gsheets'),
                'cache_cleared' => esc_html__('کش پاک شد', 'standalone-gsheets')
            ]
        ]);
    }
    
    /**
     * AJAX برای تست اتصال
     */
    public function ajax_test_connection() {
        check_ajax_referer('standalone_gsheets_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(esc_html__('شما دسترسی لازم برای این عملیات را ندارید.', 'standalone-gsheets'));
        }
        
        $plugin = null;
        if (function_exists('standalone_gsheets')) {
            $plugin = standalone_gsheets();
        }
        
        if (!$plugin || !$plugin->api || !$plugin->api->is_ready()) {
            wp_send_json_error(esc_html__('API گوگل شیت آماده نیست. لطفاً اعتبارنامه را بررسی کنید.', 'standalone-gsheets'));
        }
        
        $spreadsheet_id = isset($_POST['spreadsheet_id']) ? 
                         sanitize_text_field($_POST['spreadsheet_id']) : 
                         $plugin->get_setting('spreadsheet_id');
        
        if (empty($spreadsheet_id)) {
            wp_send_json_error(esc_html__('آی‌دی اسپردشیت مشخص نشده است.', 'standalone-gsheets'));
        }
        
        try {
            $result = $plugin->api->test_connection($spreadsheet_id);
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result['message']);
            }
        } catch (Exception $e) {
            wp_send_json_error('خطا در تست اتصال: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX برای دریافت فیلدهای شیت
     */
    public function ajax_get_sheet_fields() {
        check_ajax_referer('standalone_gsheets_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
        }
        
        $spreadsheet_id = sanitize_text_field($_POST['spreadsheet_id'] ?? '');
        $sheet_title = sanitize_text_field($_POST['sheet_title'] ?? '');
        
        if (empty($spreadsheet_id) || empty($sheet_title)) {
            wp_send_json_error('Missing required parameters');
        }
        
        $plugin = null;
        if (function_exists('standalone_gsheets')) {
            $plugin = standalone_gsheets();
        }
        
        if (!$plugin || !$plugin->api || !$plugin->api->is_ready()) {
            wp_send_json_error('API not ready');
        }
        
        try {
            $plugin->api->set_spreadsheet_id($spreadsheet_id);
            $headers = $plugin->api->get_sheet_headers($sheet_title, $spreadsheet_id);
            
            wp_send_json_success($headers);
        } catch (Exception $e) {
            wp_send_json_error('خطا در دریافت فیلدها: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX برای health check
     */
    public function ajax_health_check() {
        check_ajax_referer('standalone_gsheets_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
        }
        
        // ایجاد شی health check
        if (!class_exists('Standalone_GSheets_Health_Check')) {
            $health_check_file = STANDALONE_GSHEETS_PATH . 'includes/class-gsheets-health-check.php';
            if (file_exists($health_check_file)) {
                require_once $health_check_file;
            }
        }
        
        if (!class_exists('Standalone_GSheets_Health_Check')) {
            wp_send_json_error('Health check class not available');
        }
        
        try {
            $health_check = new Standalone_GSheets_Health_Check();
            $health = $health_check->check_all();
            wp_send_json_success($health);
        } catch (Exception $e) {
            wp_send_json_error('خطا در بررسی سلامت: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX برای پاک کردن کش
     */
    public function ajax_clear_cache() {
        check_ajax_referer('standalone_gsheets_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
        }
        
        try {
            $plugin = null;
            if (function_exists('standalone_gsheets')) {
                $plugin = standalone_gsheets();
            }
            
            if ($plugin && $plugin->api) {
                $plugin->api->clear_cache_by_pattern('standalone_gsheets_*');
            }
            
            // پاک کردن object cache
            wp_cache_flush_group('standalone_gsheets');
            
            wp_send_json_success('کش با موفقیت پاک شد');
        } catch (Exception $e) {
            wp_send_json_error('خطا در پاک کردن کش: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX برای پاک کردن کش پیشرفته (شامل OPcache)
     */
    public function ajax_advanced_cache_clear() {
        check_ajax_referer('standalone_gsheets_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
        }
        
        $results = [];
        $success_count = 0;
        
        try {
            // 1. پاک کردن OPcache
            if (function_exists('opcache_reset')) {
                if (opcache_reset()) {
                    $results[] = '✅ OPcache پاک شد';
                    $success_count++;
                } else {
                    $results[] = '⚠️ OPcache پاک نشد';
                }
            } else {
                $results[] = '⚠️ OPcache در دسترس نیست';
            }
            
            // 2. پاک کردن WordPress Object Cache
            if (wp_cache_flush()) {
                $results[] = '✅ WordPress Object Cache پاک شد';
                $success_count++;
            } else {
                $results[] = '⚠️ WordPress Object Cache پاک نشد';
            }
            
            // 3. پاک کردن WP Rocket Cache
            if (function_exists('rocket_clean_domain')) {
                rocket_clean_domain();
                $results[] = '✅ WP Rocket Cache پاک شد';
                $success_count++;
            } elseif (function_exists('rocket_clean_files')) {
                rocket_clean_files();
                $results[] = '✅ WP Rocket Files پاک شد';
                $success_count++;
            } else {
                $results[] = '⚠️ WP Rocket Cache در دسترس نیست';
            }
            
            // 4. پاک کردن کش پلاگین
            $plugin = null;
            if (function_exists('standalone_gsheets')) {
                $plugin = standalone_gsheets();
            }
            
            if ($plugin && $plugin->api) {
                $plugin->api->clear_cache_by_pattern('standalone_gsheets_*');
                $results[] = '✅ کش پلاگین پاک شد';
                $success_count++;
            }
            
            // 5. پاک کردن Transients
            delete_transient('standalone_gsheets_health_check');
            delete_transient('standalone_gsheets_api_info');
            $results[] = '✅ Transients پاک شد';
            $success_count++;
            
            // 6. پاک کردن کش‌های دیگر
            if (function_exists('w3tc_flush_all')) {
                w3tc_flush_all();
                $results[] = '✅ W3 Total Cache پاک شد';
                $success_count++;
            }
            
            if (function_exists('wp_cache_clear_cache')) {
                wp_cache_clear_cache();
                $results[] = '✅ WP Super Cache پاک شد';
                $success_count++;
            }
            
            // 7. پاک کردن User Meta Cache
            if (function_exists('clean_user_cache')) {
                $current_user_id = get_current_user_id();
                if ($current_user_id) {
                    clean_user_cache($current_user_id);
                    $results[] = '✅ User Meta Cache پاک شد';
                    $success_count++;
                }
            }
            
            // نتیجه نهایی
            $message = "<strong>نتیجه پاک کردن کش پیشرفته:</strong><br>";
            $message .= "<ul style='margin: 10px 0; padding-right: 20px;'>";
            foreach ($results as $result) {
                $message .= "<li>{$result}</li>";
            }
            $message .= "</ul>";
            $message .= "<p><strong>تعداد موفق:</strong> {$success_count} از " . count($results) . "</p>";
            
            wp_send_json_success([
                'message' => $message,
                'success_count' => $success_count,
                'total_count' => count($results),
                'results' => $results
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error('خطا در پاک کردن کش: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX برای export تنظیمات
     */
    public function ajax_export_settings() {
        check_ajax_referer('standalone_gsheets_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
        }
        
        $settings = get_option('standalone_gsheets_settings', []);
        
        // حذف مسیر credentials برای امنیت
        unset($settings['credentials_path']);
        
        $export_data = [
            'plugin_version' => STANDALONE_GSHEETS_VERSION,
            'export_date' => date('Y-m-d H:i:s'),
            'settings' => $settings
        ];
        
        wp_send_json_success($export_data);
    }
    
    /**
     * AJAX برای import تنظیمات
     */
    public function ajax_import_settings() {
        check_ajax_referer('standalone_gsheets_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
        }
        
        $import_data = json_decode(stripslashes($_POST['import_data'] ?? ''), true);
        
        if (!$import_data || !isset($import_data['settings'])) {
            wp_send_json_error('داده‌های import معتبر نیست');
        }
        
        $current_settings = get_option('standalone_gsheets_settings', []);
        $new_settings = array_merge($current_settings, $import_data['settings']);
        
        // حفظ مسیر credentials فعلی
        $new_settings['credentials_path'] = $current_settings['credentials_path'] ?? '';
        
        update_option('standalone_gsheets_settings', $new_settings);
        
        wp_send_json_success('تنظیمات با موفقیت import شدند');
    }
    
    /**
     * افزودن Dashboard Widget
     */
    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'gsheets-status',
            esc_html__('وضعیت Google Sheets', 'standalone-gsheets'),
            [$this, 'render_dashboard_widget']
        );
    }
    
    /**
     * رندر Dashboard Widget
     */
    public function render_dashboard_widget() {
        $api = null;
        $settings = [];
        
        if (function_exists('standalone_gsheets')) {
            $plugin = standalone_gsheets();
            if ($plugin) {
                $api = $plugin->api;
                $settings = $plugin->settings ?? [];
            }
        }
        
        echo '<div class="gsheets-dashboard-widget">';
        
        if (!$api || !$api->is_ready()) {
            echo '<p style="color: #d63384;">❌ ' . esc_html__('API آماده نیست', 'standalone-gsheets') . '</p>';
            echo '<a href="' . admin_url('options-general.php?page=standalone-gsheets-settings') . '" class="button">' . 
                 esc_html__('تنظیمات', 'standalone-gsheets') . '</a>';
        } else {
            echo '<p style="color: #198754;">✅ ' . esc_html__('API آماده است', 'standalone-gsheets') . '</p>';
            
            if ($api) {
                $api_info = $api->get_api_info();
                echo '<p><strong>' . esc_html__('نسخه API:', 'standalone-gsheets') . '</strong> ' . esc_html($api_info['version']) . '</p>';
                echo '<p><strong>' . esc_html__('درخواست‌های انجام شده:', 'standalone-gsheets') . '</strong> ' . 
                     esc_html($api_info['performance_stats']['requests_made']) . '</p>';
            }
        }
        
        echo '<hr>';
        echo '<a href="' . admin_url('options-general.php?page=standalone-gsheets-health') . '" class="button">' . 
             esc_html__('بررسی سلامت', 'standalone-gsheets') . '</a> ';
        echo '<a href="' . admin_url('options-general.php?page=standalone-gsheets-settings') . '" class="button">' . 
             esc_html__('تنظیمات', 'standalone-gsheets') . '</a>';
        
        echo '</div>';
    }
    
    /**
     * نمایش اعلان‌های admin
     */
    public function show_admin_notices() {
        $screen = get_current_screen();
        
        // فقط در صفحات مرتبط نمایش ده
        if (!$screen || strpos($screen->id, 'standalone-gsheets') === false) {
            return;
        }
        
        // بررسی اگر API آماده نیست
        $api_ready = false;
        if (function_exists('standalone_gsheets')) {
            $plugin = standalone_gsheets();
            if ($plugin && $plugin->api && $plugin->api->is_ready()) {
                $api_ready = true;
            }
        }
        
        if (!$api_ready) {
            echo '<div class="notice notice-warning">';
            echo '<p><strong>' . esc_html__('توجه:', 'standalone-gsheets') . '</strong> ';
            echo esc_html__('API Google Sheets آماده نیست. لطفاً فایل اعتبارنامه را بررسی کنید.', 'standalone-gsheets');
            echo '</p>';
            echo '</div>';
        }
        
        // بررسی نسخه PHP
        if (version_compare(PHP_VERSION, '8.1.0', '<')) {
            echo '<div class="notice notice-info">';
            echo '<p><strong>' . esc_html__('توصیه:', 'standalone-gsheets') . '</strong> ';
            echo sprintf(
                esc_html__('برای عملکرد بهتر، PHP را به نسخه 8.1+ ارتقا دهید. نسخه فعلی: %s', 'standalone-gsheets'),
                PHP_VERSION
            );
            echo '</p>';
            echo '</div>';
        }
    }
    
    /**
     * رندر بخش عمومی تنظیمات
     */
    public function render_general_section() {
        echo '<p>' . esc_html__('تنظیمات اصلی برای اتصال به Google Sheets API v4', 'standalone-gsheets') . '</p>';
    }
    
    /**
     * رندر بخش پیشرفته تنظیمات
     */
    public function render_advanced_section() {
        echo '<p>' . esc_html__('تنظیمات پیشرفته برای بهینه‌سازی عملکرد', 'standalone-gsheets') . '</p>';
    }
    
    /**
     * رندر فیلد اعتبارنامه
     */
    public function render_credentials_field() {
        $credentials_path = '';
        if (function_exists('standalone_gsheets')) {
            $plugin = standalone_gsheets();
            if ($plugin) {
                $credentials_path = $plugin->get_setting('credentials_path');
            }
        }
        
        if (!empty($credentials_path) && file_exists($credentials_path)) {
            echo '<div class="credentials-status">';
            echo '<p class="description" style="color: green;">✓ ' . 
                 esc_html__('فایل اعتبارنامه آپلود شده است.', 'standalone-gsheets') . '</p>';
            echo '<input type="hidden" name="standalone_gsheets_settings[credentials_path]" value="' . 
                 esc_attr($credentials_path) . '">';
            
            // نمایش اطلاعات فایل
            try {
                $credentials_content = file_get_contents($credentials_path);
                $credentials = json_decode($credentials_content, true);
                if ($credentials) {
                    echo '<p style="font-size: 12px; color: #666;">';
                    echo 'Project ID: <strong>' . esc_html($credentials['project_id'] ?? 'نامشخص') . '</strong><br>';
                    echo 'Client Email: <strong>' . esc_html($credentials['client_email'] ?? 'نامشخص') . '</strong><br>';
                    echo 'آخرین به‌روزرسانی: <strong>' . date_i18n('Y/m/d H:i', filemtime($credentials_path)) . '</strong>';
                    echo '</p>';
                }
            } catch (Exception $e) {
                // Silent fail
            }
            
            echo '<label style="margin-top: 10px; display: block;">';
            echo '<input type="checkbox" name="replace_credentials" value="1"> ';
            echo esc_html__('جایگزینی با فایل جدید', 'standalone-gsheets');
            echo '</label>';
            echo '</div>';
        } else {
            echo '<p class="description" style="color: #d63384;">⚠ ' . 
                 esc_html__('فایل اعتبارنامه آپلود نشده است.', 'standalone-gsheets') . '</p>';
        }
        
        echo '<input type="file" name="credentials_file" accept=".json" id="credentials_file">';
        echo '<p class="description">' . 
             esc_html__('فایل JSON اعتبارنامه Google Service Account را آپلود کنید.', 'standalone-gsheets') . 
             '</p>';
        
        // دکمه بازیابی فایل
        if (!empty($credentials_path) && !file_exists($credentials_path)) {
            echo '<p class="description" style="color: #d63384;">⚠️ ' . 
                 esc_html__('فایل اعتبارنامه در مسیر ذخیره شده یافت نشد. لطفاً فایل جدید آپلود کنید.', 'standalone-gsheets') . 
                 '</p>';
        }
    }
    
    /**
     * رندر فیلد آی‌دی اسپردشیت
     */
    public function render_spreadsheet_id_field() {
        $value = '';
        if (function_exists('standalone_gsheets')) {
            $plugin = standalone_gsheets();
            if ($plugin) {
                $value = $plugin->get_setting('spreadsheet_id');
            }
        }
        
        echo '<input type="text" name="standalone_gsheets_settings[spreadsheet_id]" value="' . 
             esc_attr($value) . '" class="regular-text" placeholder="1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms">';
        echo '<p class="description">' . 
             esc_html__('آی‌دی اسپردشیت Google از URL آن استخراج کنید.', 'standalone-gsheets') . 
             '</p>';
    }
    
    /**
     * رندر فیلد ستون دیسکورد
     */
    public function render_discord_column_field() {
        $value = 'Discord ID';
        if (function_exists('standalone_gsheets')) {
            $plugin = standalone_gsheets();
            if ($plugin) {
                $value = $plugin->get_setting('discord_id_column', 'Discord ID');
            }
        }
        
        echo '<input type="text" name="standalone_gsheets_settings[discord_id_column]" value="' . 
             esc_attr($value) . '" class="regular-text">';
        echo '<p class="description">' . 
             esc_html__('نام ستونی که حاوی Discord ID است.', 'standalone-gsheets') . 
             '</p>';
    }
    
    /**
     * رندر فیلد زمان کش
     */
    public function render_cache_time_field() {
        $value = 300;
        if (function_exists('standalone_gsheets')) {
            $plugin = standalone_gsheets();
            if ($plugin) {
                $value = $plugin->get_setting('cache_time', 300);
            }
        }
        
        echo '<input type="number" name="standalone_gsheets_settings[cache_time]" value="' . 
             esc_attr($value) . '" class="small-text" min="60" max="3600">';
        echo '<p class="description">' . 
             esc_html__('مدت زمان نگهداری داده‌ها در کش (60-3600 ثانیه). توصیه: 300', 'standalone-gsheets') . 
             '</p>';
    }
    
    /**
     * رندر فیلد محدودیت نرخ
     */
    public function render_rate_limit_field() {
        $value = true;
        if (function_exists('standalone_gsheets')) {
            $plugin = standalone_gsheets();
            if ($plugin) {
                $value = $plugin->get_setting('rate_limit_enabled', true);
            }
        }
        
        echo '<label>';
        echo '<input type="checkbox" name="standalone_gsheets_settings[rate_limit_enabled]" value="1"' . 
             checked($value, true, false) . '>';
        echo ' ' . esc_html__('فعال‌سازی محدودیت تعداد درخواست‌ها', 'standalone-gsheets');
        echo '</label>';
        echo '<p class="description">' . 
             esc_html__('برای جلوگیری از اتمام quota API توصیه می‌شود.', 'standalone-gsheets') . 
             '</p>';
    }
    
    /**
     * رندر فیلد حالت دیباگ
     */
    public function render_debug_mode_field() {
        $value = false;
        if (function_exists('standalone_gsheets')) {
            $plugin = standalone_gsheets();
            if ($plugin) {
                $value = $plugin->get_setting('debug_mode', false);
            }
        }
        
        echo '<label>';
        echo '<input type="checkbox" name="standalone_gsheets_settings[debug_mode]" value="1"' . 
             checked($value, true, false) . '>';
        echo ' ' . esc_html__('فعال‌سازی حالت دیباگ', 'standalone-gsheets');
        echo '</label>';
        echo '<p class="description">' . 
             esc_html__('نمایش اطلاعات اضافی برای رفع اشکال. فقط برای توسعه.', 'standalone-gsheets') . 
             '</p>';
    }
    
    /**
     * رندر صفحه تنظیمات اصلی - نسخه اصلاح شده
     */
    public function render_settings_page() {
        // بررسی آپلود فایل - این باید قبل از نمایش HTML باشد
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handle_form_submission();
        }
        ?>
        <div class="wrap standalone-gsheets-admin">
            <h1>
                <?php echo esc_html(get_admin_page_title()); ?>
                <span class="gsheet-api-badge">API v4</span>
            </h1>
            
            <?php settings_errors(); ?>
            
            <form method="post" action="" enctype="multipart/form-data">
                <?php wp_nonce_field('gsheets_settings_nonce', 'gsheets_nonce'); ?>
                <?php 
                settings_fields('standalone_gsheets_settings');
                do_settings_sections('standalone_gsheets_settings');
                submit_button();
                ?>
            </form>
            
            <hr>
            
            <!-- تست اتصال و مدیریت کش -->
            <div class="card">
                <h2><?php esc_html_e('تست اتصال و مدیریت کش', 'standalone-gsheets'); ?></h2>
                <p><?php esc_html_e('تست اتصال به Google Sheets و مدیریت کش‌های سیستم:', 'standalone-gsheets'); ?></p>
                
                <p>
                    <input type="text" id="test-spreadsheet-id" placeholder="<?php esc_attr_e('آی‌دی اسپردشیت (اختیاری)', 'standalone-gsheets'); ?>" class="regular-text">
                </p>
                
                <p>
                    <button type="button" id="test-connection-btn" class="button button-secondary">
                        <?php esc_html_e('تست اتصال', 'standalone-gsheets'); ?>
                    </button>
                    <button type="button" id="clear-cache-btn" class="button">
                        <?php esc_html_e('پاک کردن کش معمولی', 'standalone-gsheets'); ?>
                    </button>
                    <button type="button" id="advanced-cache-clear-btn" class="button button-primary">
                        <?php esc_html_e('پاک کردن کش پیشرفته', 'standalone-gsheets'); ?>
                    </button>
                </p>
                
                <div id="test-result" style="display: none;"></div>
                <div id="cache-result" style="display: none;"></div>
            </div>
            
            <!-- اطلاعات کش -->
            <div class="card">
                <h2><?php esc_html_e('اطلاعات کش سیستم', 'standalone-gsheets'); ?></h2>
                <div class="cache-info">
                    <?php
                    echo '<p><strong>OPcache:</strong> ' . (function_exists('opcache_reset') ? 
                        '<span style="color: green;">✅ فعال</span>' : 
                        '<span style="color: red;">❌ غیرفعال</span>') . '</p>';
                    
                    echo '<p><strong>WP Rocket:</strong> ' . (function_exists('rocket_clean_domain') ? 
                        '<span style="color: green;">✅ فعال</span>' : 
                        '<span style="color: gray;">❌ نصب نشده</span>') . '</p>';
                    
                    echo '<p><strong>W3 Total Cache:</strong> ' . (function_exists('w3tc_flush_all') ? 
                        '<span style="color: green;">✅ فعال</span>' : 
                        '<span style="color: gray;">❌ نصب نشده</span>') . '</p>';
                    
                    echo '<p><strong>WP Super Cache:</strong> ' . (function_exists('wp_cache_clear_cache') ? 
                        '<span style="color: green;">✅ فعال</span>' : 
                        '<span style="color: gray;">❌ نصب نشده</span>') . '</p>';
                    
                    // اطلاعات اضافی
                    if (function_exists('opcache_get_status')) {
                        $opcache_status = opcache_get_status(false);
                        if ($opcache_status && $opcache_status['opcache_enabled']) {
                            echo '<p><strong>OPcache Memory:</strong> ' . 
                                 round($opcache_status['memory_usage']['used_memory'] / 1024 / 1024, 2) . 'MB / ' .
                                 round($opcache_status['memory_usage']['free_memory'] / 1024 / 1024, 2) . 'MB</p>';
                        }
                    }
                    ?>
                </div>
            </div>
            
            <!-- تست Discord ID -->
            <div class="card">
                <h2><?php esc_html_e('بررسی Discord ID', 'standalone-gsheets'); ?></h2>
                <?php 
                $discord_id = '';
                if (function_exists('standalone_gsheets')) {
                    $plugin = standalone_gsheets();
                    if ($plugin) {
                        $discord_id = $plugin->get_current_user_discord_id();
                    }
                }
                
                if ($discord_id) {
                    echo '<p style="color: green;">✅ Discord ID یافت شد: <strong>' . esc_html($discord_id) . '</strong></p>';
                } else {
                    echo '<p style="color: red;">❌ Discord ID یافت نشد</p>';
                    echo '<p class="description">ممکن است کاربر Discord متصل نباشد یا meta key متفاوت باشد.</p>';
                }
                ?>
            </div>
            
            <!-- ابزارهای مدیریت -->
            <div class="card">
                <h2><?php esc_html_e('ابزارهای مدیریت', 'standalone-gsheets'); ?></h2>
                
                <p>
                    <button type="button" id="export-settings-btn" class="button">
                        <?php esc_html_e('صدور تنظیمات', 'standalone-gsheets'); ?>
                    </button>
                    <button type="button" id="import-settings-btn" class="button">
                        <?php esc_html_e('وارد کردن تنظیمات', 'standalone-gsheets'); ?>
                    </button>
                </p>
                
                <textarea id="import-export-data" rows="6" cols="50" placeholder="<?php esc_attr_e('داده‌های JSON برای import/export', 'standalone-gsheets'); ?>" style="display: none; width: 100%;"></textarea>
            </div>
            
            <!-- راهنمای شورت‌کدها -->
            <?php $this->render_shortcodes_guide(); ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            console.log('Google Sheets Admin JS loaded');
            console.log('standalone_gsheets_admin object:', standalone_gsheets_admin);
            
            // دکمه تست اتصال
            $('#test-connection-btn').on('click', function() {
                console.log('Test connection button clicked');
                
                var $button = $(this);
                var $result = $('#test-result');
                var spreadsheet_id = $('#test-spreadsheet-id').val();
                
                console.log('Spreadsheet ID:', spreadsheet_id);
                
                $button.prop('disabled', true).text('در حال تست...');
                $result.hide();
                
                var ajaxData = {
                    action: 'standalone_gsheets_test_connection',
                    nonce: standalone_gsheets_admin.nonce,
                    spreadsheet_id: spreadsheet_id
                };
                
                console.log('Sending AJAX request:', ajaxData);
                
                $.ajax({
                    url: standalone_gsheets_admin.ajax_url,
                    type: 'POST',
                    data: ajaxData,
                    success: function(response) {
                        console.log('AJAX Success:', response);
                        
                        if (response.success) {
                            var html = '<div style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; padding: 15px; color: #155724;">';
                            html += '<h4>✅ اتصال موفقیت‌آمیز!</h4>';
                            html += '<p><strong>عنوان:</strong> ' + (response.data.spreadsheet_title || 'نامشخص') + '</p>';
                            if (response.data.sheets && response.data.sheets.length > 0) {
                                html += '<p><strong>شیت‌ها:</strong> ' + response.data.sheets.join(', ') + '</p>';
                            }
                            html += '</div>';
                            $result.html(html).show();
                        } else {
                            $result.html('<div style="background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; padding: 15px; color: #721c24;">❌ خطا: ' + response.data + '</div>').show();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.log('AJAX Error:', xhr, status, error);
                        $result.html('<div style="background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; padding: 15px; color: #721c24;">❌ خطا در اتصال به سرور: ' + error + '</div>').show();
                    },
                    complete: function() {
                        console.log('AJAX Complete');
                        $button.prop('disabled', false).text('تست اتصال');
                    }
                });
            });
            
            // دکمه پاک کردن کش پیشرفته
            $('#advanced-cache-clear-btn').on('click', function() {
                console.log('Advanced cache clear button clicked');
                
                var $button = $(this);
                var $result = $('#cache-result');
                
                $button.prop('disabled', true).text('در حال پاک کردن...');
                $result.hide();
                
                $.ajax({
                    url: standalone_gsheets_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'standalone_gsheets_advanced_cache_clear',
                        nonce: standalone_gsheets_admin.nonce
                    },
                    success: function(response) {
                        console.log('Cache clear success:', response);
                        
                        if (response.success) {
                            $result.html('<div style="background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 4px; padding: 15px; color: #0c5460;">' + response.data.message + '</div>').show();
                            
                            // نمایش نوتیفیکیشن موفقیت
                            if (response.data.success_count > 0) {
                                $('body').append('<div id="cache-success-notice" style="position: fixed; top: 50px; right: 20px; background: #28a745; color: white; padding: 15px 20px; border-radius: 8px; z-index: 9999; box-shadow: 0 4px 8px rgba(0,0,0,0.2);">✅ ' + response.data.success_count + ' کش پاک شد!</div>');
                                
                                setTimeout(function() {
                                    $('#cache-success-notice').fadeOut(function() {
                                        $(this).remove();
                                    });
                                }, 3000);
                            }
                        } else {
                            $result.html('<div style="background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; padding: 15px; color: #721c24;">خطا: ' + response.data + '</div>').show();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.log('Cache clear error:', xhr, status, error);
                        $result.html('<div style="background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; padding: 15px; color: #721c24;">خطا در اتصال به سرور: ' + error + '</div>').show();
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('پاک کردن کش پیشرفته');
                    }
                });
            });
            
            // دکمه پاک کردن کش معمولی
            $('#clear-cache-btn').on('click', function() {
                console.log('Normal cache clear button clicked');
                
                var $button = $(this);
                var $result = $('#cache-result');
                
                $button.prop('disabled', true).text('در حال پاک کردن...');
                
                $.ajax({
                    url: standalone_gsheets_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'standalone_gsheets_clear_cache',
                        nonce: standalone_gsheets_admin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $result.html('<div style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; padding: 10px; color: #155724;">✅ ' + response.data + '</div>').show();
                        } else {
                            $result.html('<div style="background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; padding: 10px; color: #721c24;">خطا: ' + response.data + '</div>').show();
                        }
                    },
                    error: function(xhr, status, error) {
                        $result.html('<div style="background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; padding: 10px; color: #721c24;">خطا در اتصال به سرور: ' + error + '</div>').show();
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('پاک کردن کش معمولی');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * رندر صفحه بررسی سلامت
     */
    public function render_health_page() {
        ?>
        <div class="wrap standalone-gsheets-admin">
            <h1>
                <?php esc_html_e('بررسی سلامت سیستم', 'standalone-gsheets'); ?>
                <span class="gsheet-api-badge">API v4</span>
            </h1>
            
            <div class="card">
                <p>
                    <button type="button" id="run-health-check-btn" class="button button-primary">
                        <?php esc_html_e('اجرای بررسی سلامت', 'standalone-gsheets'); ?>
                    </button>
                </p>
                
                <div id="health-check-results" style="display: none;"></div>
            </div>
            
            <div class="card">
                <h2><?php esc_html_e('نمایش سریع وضعیت', 'standalone-gsheets'); ?></h2>
                <?php echo do_shortcode('[gsheets_health show_details="yes"]'); ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#run-health-check-btn').on('click', function() {
                var $button = $(this);
                var $results = $('#health-check-results');
                
                $button.prop('disabled', true).text('<?php esc_js(_e('در حال بررسی...', 'standalone-gsheets')); ?>');
                
                $.ajax({
                    url: standalone_gsheets_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'standalone_gsheets_health_check',
                        nonce: standalone_gsheets_admin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            var health = response.data;
                            var html = '<h3>وضعیت کلی: ';
                            
                            if (health.overall_status === 'healthy') {
                                html += '<span style="color: green;">✅ سالم</span>';
                            } else if (health.overall_status === 'warning') {
                                html += '<span style="color: orange;">⚠️ هشدار</span>';
                            } else {
                                html += '<span style="color: red;">❌ مشکل دار</span>';
                            }
                            
                            html += '</h3>';
                            html += '<p><strong>خلاصه:</strong> ' + health.summary.passed + ' موفق، ' + 
                                   health.summary.warnings + ' هشدار، ' + health.summary.errors + ' خطا</p>';
                            
                            html += '<h4>جزئیات بررسی‌ها:</h4><ul>';
                            $.each(health.checks, function(name, check) {
                                var icon = check.status === 'pass' ? '✅' : (check.status === 'warning' ? '⚠️' : '❌');
                                html += '<li>' + icon + ' <strong>' + name + ':</strong> ' + check.message + '</li>';
                            });
                            html += '</ul>';
                            
                            if (health.recommendations && health.recommendations.length > 0) {
                                html += '<h4>توصیه‌ها:</h4><ul>';
                                $.each(health.recommendations, function(i, rec) {
                                    html += '<li>' + rec + '</li>';
                                });
                                html += '</ul>';
                            }
                            
                            $results.html(html).show();
                        } else {
                            $results.html('<p style="color: red;">خطا: ' + response.data + '</p>').show();
                        }
                    },
                    error: function() {
                        $results.html('<p style="color: red;">خطا در اتصال به سرور</p>').show();
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('<?php esc_js(_e('اجرای بررسی سلامت', 'standalone-gsheets')); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * رندر راهنمای شورت‌کدها
     */
    private function render_shortcodes_guide() {
        ?>
        <div class="card">
            <h2><?php esc_html_e('راهنمای شورت‌کدهای API v4', 'standalone-gsheets'); ?></h2>
            
            <div class="shortcode-examples">
                <h3><?php esc_html_e('نمایش داده‌های کاربر', 'standalone-gsheets'); ?></h3>
                <p><?php esc_html_e('نمایش داده‌های کاربر بر اساس Discord ID:', 'standalone-gsheets'); ?></p>
                <pre>[gsheet_user_data spreadsheet_id="YOUR_ID" sheet="Sheet1" field="Column1" discord_id_column="Discord ID"]</pre>
                
                <h3><?php esc_html_e('نمایش فیلد مشخص', 'standalone-gsheets'); ?></h3>
                <p><?php esc_html_e('نمایش فیلد مشخص برای کاربر فعلی:', 'standalone-gsheets'); ?></p>
                <pre>[gsheet_user_field field="character_name" default="شخصیت یافت نشد"]</pre>
                
                <h3><?php esc_html_e('نمایش مقدار سلول', 'standalone-gsheets'); ?></h3>
                <p><?php esc_html_e('نمایش مقدار سلول مشخص:', 'standalone-gsheets'); ?></p>
                <pre>[gsheet_cell spreadsheet_id="YOUR_ID" sheet="Sheet1" cell="A1" label="عنوان"]</pre>
                
                <h3><?php esc_html_e('شورت‌کدهای سریع', 'standalone-gsheets'); ?></h3>
                <p><?php esc_html_e('دسترسی سریع به فیلدهای رایج:', 'standalone-gsheets'); ?></p>
                <div class="quick-shortcodes" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">
                    <code>[gsheet_character_name]</code>
                    <code>[gsheet_discord_id]</code>
                    <code>[gsheet_email]</code>
                    <code>[gsheet_name]</code>
                    <code>[gsheet_realm_name]</code>
                    <code>[gsheet_guild_name]</code>
                    <code>[gsheet_class]</code>
                    <code>[gsheet_level]</code>
                </div>
                
                <h3><?php esc_html_e('شورت‌کدهای تست و دیباگ', 'standalone-gsheets'); ?></h3>
                <pre>[test_discord_id] - نمایش اطلاعات Discord ID کاربر</pre>
                <pre>[test_settings] - نمایش تنظیمات پلاگین (فقط برای ادمین)</pre>
                <pre>[gsheets_health] - نمایش وضعیت سلامت سیستم</pre>
                
                <h3><?php esc_html_e('پارامترهای قابل استفاده', 'standalone-gsheets'); ?></h3>
                <ul>
                    <li><strong>spreadsheet_id:</strong> <?php esc_html_e('آی‌دی اسپردشیت (اختیاری)', 'standalone-gsheets'); ?></li>
                    <li><strong>sheet:</strong> <?php esc_html_e('نام شیت (اختیاری)', 'standalone-gsheets'); ?></li>
                    <li><strong>field:</strong> <?php esc_html_e('نام ستون برای نمایش', 'standalone-gsheets'); ?></li>
                    <li><strong>discord_id_column:</strong> <?php esc_html_e('ستون Discord ID (پیش‌فرض: "Discord ID")', 'standalone-gsheets'); ?></li>
                    <li><strong>label:</strong> <?php esc_html_e('برچسب برای نمایش', 'standalone-gsheets'); ?></li>
                    <li><strong>format:</strong> <?php esc_html_e('فرمت نمایش: "text" یا "table"', 'standalone-gsheets'); ?></li>
                    <li><strong>raw:</strong> <?php esc_html_e('نمایش خام بدون HTML (yes/no)', 'standalone-gsheets'); ?></li>
                    <li><strong>default:</strong> <?php esc_html_e('مقدار پیش‌فرض اگر داده یافت نشود', 'standalone-gsheets'); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }
}