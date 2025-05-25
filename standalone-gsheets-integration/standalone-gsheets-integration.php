<?php
/**
 * Plugin Name: Standalone Google Sheets Reader v4
 * Description: پلاگین مستقل خواندن گوگل شیت v4 برای وردپرس با شورت‌کدهای ساده و قابلیت‌های استایل پیشرفته
 * Version: 2.0.1
 * Author: d4wood
 * Text Domain: standalone-gsheets
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

// تعریف ثابت‌های اصلی
define('STANDALONE_GSHEETS_VERSION', '2.0.1');
define('STANDALONE_GSHEETS_PATH', plugin_dir_path(__FILE__));
define('STANDALONE_GSHEETS_URL', plugin_dir_url(__FILE__));
define('STANDALONE_GSHEETS_API_VERSION', 'v4');

// ثابت‌های جدید برای بهبود
define('STANDALONE_GSHEETS_MAX_CACHE_TIME', 3600);
define('STANDALONE_GSHEETS_MIN_CACHE_TIME', 60);
define('STANDALONE_GSHEETS_DEFAULT_CACHE_TIME', 300);

// مسیر امن برای credentials
define('STANDALONE_GSHEETS_PRIVATE_DIR', WP_CONTENT_DIR . '/private/gsheets-credentials');

/**
 * بارگذاری اتولودر کامپوزر - ابتدا این کار را انجام دهیم
 */
$composer_autoload = STANDALONE_GSHEETS_PATH . 'vendor/autoload.php';
if (file_exists($composer_autoload)) {
    require_once $composer_autoload;
    error_log('Autoload loaded from: ' . $composer_autoload);
    error_log('Google_Client exists: ' . (class_exists('Google_Client') ? 'YES' : 'NO'));
    error_log('Google\Client exists: ' . (class_exists('Google\Client') ? 'YES' : 'NO'));
} else {
    error_log('Autoload NOT found at: ' . $composer_autoload);
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>خطا:</strong> فایل vendor/autoload.php یافت نشد. لطفاً در پوشه پلاگین دستور <code>composer install</code> را اجرا کنید.</p></div>';
    });
    return;
}

/**
 * حالا بررسی وجود کلاس‌های ضروری - بعد از بارگذاری autoloader
 */
if (!class_exists('Google_Client') && !class_exists('Google\Client')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>خطا:</strong> Google Client Library بارگذاری نشد. احتمالاً composer dependencies نصب نشده‌اند. لطفاً <code>composer install</code> را اجرا کنید.</p></div>';
    });
    return;
}

/**
 * بارگذاری فایل‌های کلاس‌ها
 */
$class_files = [
    'class-gsheets-api.php',
    'class-gsheets-shortcodes.php',
    'class-gsheets-admin.php',
    'class-gsheets-logger.php'
];

foreach ($class_files as $file) {
    $file_path = STANDALONE_GSHEETS_PATH . 'includes/' . $file;
    if (file_exists($file_path)) {
        require_once $file_path;
    } else {
        error_log('Required file not found: ' . $file_path);
    }
}

/**
 * کلاس اصلی پلاگین - نسخه 2.0.1
 */
class Standalone_GSheets_Integration {
    private static $instance = null;
    
    public $api = null;
    public $shortcodes = null;
    public $admin = null;
    public $logger = null;
    
    private $settings = [];
    
    /**
     * سازنده
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * دریافت نمونه یکتای پلاگین
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * مقداردهی اولیه پلاگین
     */
    private function init() {
        // مقداردهی logger
        if (class_exists('Standalone_GSheets_Logger')) {
            $this->logger = new Standalone_GSheets_Logger();
        }
        
        // بارگذاری تنظیمات
        $this->load_settings();
        
        // بررسی و ایجاد دایرکتوری امن
        $this->ensure_secure_directory();
        
        // هوک‌های اساسی
        add_action('init', [$this, 'load_textdomain']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        
        // مقداردهی اولیه اجزا
        $this->init_api();
        $this->init_shortcodes();
        $this->init_admin();
        
        // بارگذاری ویجت‌های المنتور - *** تغییر اصلی ***
        $this->init_elementor();
        
        // ثبت AJAX handlers
        $this->register_ajax_handlers();
        
        // اضافه کردن شورت‌کدهای تست و debug
        add_shortcode('gsheet_debug', [$this, 'debug_shortcode']);
        add_shortcode('gsheet_test', [$this, 'test_shortcode']);
		add_shortcode('gsheet_elementor_debug', [$this, 'elementor_debug_shortcode']);
        
        // *** اضافه شده: شورت‌کد بررسی المنتور ***
        add_shortcode('gsheet_check_elementor', [$this, 'check_elementor_shortcode']);
        
        // هوک‌های اضافی
        add_action('wp_head', [$this, 'add_inline_styles']);
        register_activation_hook(__FILE__, [$this, 'activation_hook']);
        register_deactivation_hook(__FILE__, [$this, 'deactivation_hook']);
    }
    
    /**
     * *** تغییر کامل: مقداردهی ویجت‌های المنتور با روش جدید ***
     */
    private function init_elementor() {
        // بررسی اگر المنتور نصب شده باشد
        if (!did_action('elementor/loaded')) {
            // اگر المنتور هنوز لود نشده، منتظر بمان
            add_action('elementor/loaded', [$this, 'load_elementor_widgets']);
            error_log('Waiting for Elementor to load...');
        } else {
            // اگر المنتور قبلاً لود شده
            $this->load_elementor_widgets();
            error_log('Elementor already loaded, initializing widgets...');
        }
    }
    
    /**
     * *** متد جدید: بارگذاری ویجت‌های المنتور ***
     */
    public function load_elementor_widgets() {
        error_log('=== ELEMENTOR WIDGETS LOADING ===');
        
        // بارگذاری کلاس loader المنتور
        $loader_file = STANDALONE_GSHEETS_PATH . 'includes/elementor/class-elementor-loader.php';
        error_log('Loader file path: ' . $loader_file);
        
        if (file_exists($loader_file)) {
            require_once $loader_file;
            error_log('Loader file loaded successfully');
            
            if (class_exists('Standalone_GSheets_Elementor_Loader')) {
                error_log('Loader class exists, initializing...');
                Standalone_GSheets_Elementor_Loader::get_instance();
                error_log('Elementor loader initialized!');
            } else {
                error_log('ERROR: Loader class not found after require');
            }
        } else {
            error_log('ERROR: Loader file not found at: ' . $loader_file);
            
            // لیست کردن محتویات پوشه برای دیباگ
            $includes_path = STANDALONE_GSHEETS_PATH . 'includes/';
            if (is_dir($includes_path)) {
                $contents = scandir($includes_path);
                error_log('Contents of includes folder: ' . implode(', ', $contents));
            }
        }
    }
    
    /**
     * *** شورت‌کد جدید: بررسی وضعیت المنتور ***
     */
    public function check_elementor_shortcode($atts) {
        if (!current_user_can('manage_options')) {
            return '';
        }
        
        $output = '<div style="background: #f0f0f0; padding: 20px; border-radius: 8px; font-family: monospace;">';
        $output .= '<h3>🎨 Elementor Status Check</h3>';
        
        // بررسی نصب المنتور
        $output .= '<p><strong>Elementor Plugin:</strong> ';
        if (defined('ELEMENTOR_VERSION')) {
            $output .= '✅ Installed (v' . ELEMENTOR_VERSION . ')';
        } else {
            $output .= '❌ Not Found';
        }
        $output .= '</p>';
        
        // بررسی فعال بودن المنتور
        $output .= '<p><strong>Elementor Active:</strong> ';
        if (is_plugin_active('elementor/elementor.php')) {
            $output .= '✅ Active';
        } else {
            $output .= '❌ Not Active';
        }
        $output .= '</p>';
        
        // بررسی hook
        $output .= '<p><strong>Elementor Loaded:</strong> ';
        if (did_action('elementor/loaded')) {
            $output .= '✅ Yes (fired ' . did_action('elementor/loaded') . ' times)';
        } else {
            $output .= '❌ No';
        }
        $output .= '</p>';
        
        // بررسی کلاس‌های ویجت
        $output .= '<p><strong>Widget Classes:</strong><br>';
        $output .= '- Standalone_GSheets_Table_Widget: ' . (class_exists('Standalone_GSheets_Table_Widget') ? '✅' : '❌') . '<br>';
        $output .= '- Standalone_GSheets_Field_Widget: ' . (class_exists('Standalone_GSheets_Field_Widget') ? '✅' : '❌') . '<br>';
        $output .= '- Standalone_GSheets_Elementor_Loader: ' . (class_exists('Standalone_GSheets_Elementor_Loader') ? '✅' : '❌') . '</p>';
        
        // بررسی Widgets Manager
        if (class_exists('\Elementor\Plugin')) {
            $output .= '<p><strong>Elementor Plugin Class:</strong> ✅ Available</p>';
            
            if (method_exists('\Elementor\Plugin', 'instance')) {
                $elementor = \Elementor\Plugin::instance();
                if ($elementor && isset($elementor->widgets_manager)) {
                    $output .= '<p><strong>Widgets Manager:</strong> ✅ Available</p>';
                    
                    // لیست ویجت‌های ثبت شده
                    $widgets = $elementor->widgets_manager->get_widget_types();
                    $gsheet_widgets = array_filter(array_keys($widgets), function($name) {
                        return strpos($name, 'gsheet') !== false;
                    });
                    
                    $output .= '<p><strong>Registered GSheets Widgets:</strong> ';
                    if (!empty($gsheet_widgets)) {
                        $output .= implode(', ', $gsheet_widgets);
                    } else {
                        $output .= 'None';
                    }
                    $output .= '</p>';
                }
            }
        }
        
        // بررسی فایل‌های ویجت
        $widgets_path = STANDALONE_GSHEETS_PATH . 'includes/elementor/widgets/';
        $output .= '<p><strong>Widget Files:</strong><br>';
        if (is_dir($widgets_path)) {
            $widget_files = ['table-widget.php', 'field-widget.php'];
            foreach ($widget_files as $file) {
                $exists = file_exists($widgets_path . $file);
                $output .= '- ' . $file . ': ' . ($exists ? '✅ Found' : '❌ Missing') . '<br>';
            }
        } else {
            $output .= '❌ Widgets directory not found!<br>';
        }
        $output .= '</p>';
        
        $output .= '</div>';
        return $output;
    }
    
    /**
     * اطمینان از وجود دایرکتوری امن
     */
    private function ensure_secure_directory() {
        if (!file_exists(STANDALONE_GSHEETS_PRIVATE_DIR)) {
            wp_mkdir_p(STANDALONE_GSHEETS_PRIVATE_DIR);
            
            // ایجاد فایل .htaccess برای محافظت
            $htaccess_content = "Order deny,allow\nDeny from all\n";
            file_put_contents(STANDALONE_GSHEETS_PRIVATE_DIR . '/.htaccess', $htaccess_content);
            
            // ایجاد index.php خالی
            file_put_contents(STANDALONE_GSHEETS_PRIVATE_DIR . '/index.php', '<?php // Silence is golden');
            
            if ($this->logger) {
                $this->logger->info('Secure directory created', ['path' => STANDALONE_GSHEETS_PRIVATE_DIR]);
            }
        }
    }
    
    /**
     * بارگذاری فایل‌های زبان
     */
    public function load_textdomain() {
        load_plugin_textdomain('standalone-gsheets', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * شورت‌کد debug کامل
     */
    public function debug_shortcode($atts) {
        $atts = shortcode_atts([
            'show' => 'all'
        ], $atts);
        
        if (!current_user_can('manage_options')) {
            return '<p style="color: red;">دسترسی غیرمجاز</p>';
        }
        
        $debug = '<div class="gsheet-debug-panel" style="background: #f8f9fa; border: 2px solid #007cba; border-radius: 8px; padding: 20px; margin: 20px 0; font-family: monospace; font-size: 13px;">';
        $debug .= '<h3 style="margin-top: 0; color: #007cba;">🔧 Standalone GSheets v4 Debug Panel</h3>';
        
        // اطلاعات API
        if ($atts['show'] === 'all' || $atts['show'] === 'api') {
            $debug .= '<div style="margin: 15px 0; padding: 10px; background: white; border-radius: 4px;">';
            $debug .= '<h4 style="margin-top: 0; color: #333;">📡 API Status</h4>';
            
            // بررسی کلاس‌های Google
            $debug .= '<p><strong>Google Client Classes:</strong></p>';
            $debug .= '<ul>';
            $debug .= '<li>Google_Client: ' . (class_exists('Google_Client') ? '✅ YES' : '❌ NO') . '</li>';
            $debug .= '<li>Google\Client: ' . (class_exists('Google\Client') ? '✅ YES' : '❌ NO') . '</li>';
            $debug .= '</ul>';
            
            if ($this->api && $this->api->is_ready()) {
                $debug .= '<p style="color: green;">✅ Google Sheets API v4 Ready</p>';
                
                $spreadsheet_id = $this->get_setting('spreadsheet_id');
                if (!empty($spreadsheet_id)) {
                    $test_result = $this->api->test_connection($spreadsheet_id);
                    if ($test_result['success']) {
                        $debug .= '<p style="color: green;">✅ Connection Test: SUCCESS</p>';
                        $debug .= '<p>📊 Spreadsheet: ' . esc_html($test_result['spreadsheet_title']) . '</p>';
                        $debug .= '<p>📄 Sheets: ' . implode(', ', array_map('esc_html', $test_result['sheets'])) . '</p>';
                    } else {
                        $debug .= '<p style="color: red;">❌ Connection Test: ' . esc_html($test_result['message']) . '</p>';
                    }
                }
            } else {
                $debug .= '<p style="color: red;">❌ API Not Ready</p>';
                if (!$this->api) {
                    $debug .= '<p style="color: red;">API object is null</p>';
                }
            }
            $debug .= '</div>';
        }
        
        // اطلاعات کاربر
        if ($atts['show'] === 'all' || $atts['show'] === 'user') {
            $debug .= '<div style="margin: 15px 0; padding: 10px; background: white; border-radius: 4px;">';
            $debug .= '<h4 style="margin-top: 0; color: #333;">👤 User Info</h4>';
            
            $user_id = get_current_user_id();
            $discord_id = $this->get_current_user_discord_id();
            
            $debug .= '<p>🆔 User ID: ' . $user_id . '</p>';
            $debug .= '<p>🎮 Discord ID: ' . ($discord_id ?: 'Not Found') . '</p>';
            $debug .= '<p>🔐 Logged In: ' . (is_user_logged_in() ? 'Yes' : 'No') . '</p>';
            
            if ($user_id && $discord_id) {
                $debug .= '<p style="color: green;">✅ Ready for data retrieval</p>';
            } else {
                $debug .= '<p style="color: orange;">⚠️ Missing Discord ID or not logged in</p>';
            }
            $debug .= '</div>';
        }
        
        // تنظیمات
        if ($atts['show'] === 'all' || $atts['show'] === 'settings') {
            $debug .= '<div style="margin: 15px 0; padding: 10px; background: white; border-radius: 4px;">';
            $debug .= '<h4 style="margin-top: 0; color: #333;">⚙️ Settings</h4>';
            $debug .= '<pre style="background: #f8f9fa; padding: 10px; border-radius: 4px; font-size: 11px; overflow: auto;">';
            $safe_settings = $this->settings;
            if (isset($safe_settings['credentials_path'])) {
                $safe_settings['credentials_path'] = '***HIDDEN***';
            }
            $debug .= print_r($safe_settings, true);
            $debug .= '</pre>';
            $debug .= '</div>';
        }
        
        // بررسی المنتور
        if ($atts['show'] === 'all' || $atts['show'] === 'elementor') {
            $debug .= '<div style="margin: 15px 0; padding: 10px; background: white; border-radius: 4px;">';
            $debug .= '<h4 style="margin-top: 0; color: #333;">🎨 Elementor Status</h4>';
            
            if (did_action('elementor/loaded')) {
                $debug .= '<p style="color: green;">✅ Elementor Loaded</p>';
                
                // بررسی ثبت ویجت‌ها
                if (class_exists('\Elementor\Plugin')) {
                    $widgets_manager = \Elementor\Plugin::instance()->widgets_manager;
                    if ($widgets_manager) {
                        $debug .= '<p style="color: green;">✅ Widgets Manager Available</p>';
                    }
                }
            } else {
                $debug .= '<p style="color: orange;">⚠️ Elementor Not Loaded</p>';
            }
            $debug .= '</div>';
        }
        
        $debug .= '<p style="margin-bottom: 0; font-size: 11px; color: #666;">Generated at: ' . current_time('Y-m-d H:i:s') . ' | Plugin Version: ' . STANDALONE_GSHEETS_VERSION . '</p>';
        $debug .= '</div>';
        
        return $debug;
    }
    
    /**
     * شورت‌کد تست ساده
     */
    public function test_shortcode($atts) {
        $atts = shortcode_atts([
            'field' => 'Character Name'
        ], $atts);
        
        $output = '<div class="gsheet-test-output" style="background: #e3f2fd; border: 1px solid #2196f3; border-radius: 6px; padding: 15px; margin: 15px 0;">';
        $output .= '<h4 style="margin-top: 0; color: #1976d2;">🧪 GSheets Test</h4>';
        
        if (!is_user_logged_in()) {
            $output .= '<p style="color: #f57c00;">⚠️ Please login first</p>';
        } elseif (!$this->get_current_user_discord_id()) {
            $output .= '<p style="color: #f57c00;">⚠️ Discord ID not found</p>';
        } elseif (!$this->api || !$this->api->is_ready()) {
            $output .= '<p style="color: #d32f2f;">❌ API not ready</p>';
        } else {
            $test_value = do_shortcode('[gsheet_field field="' . esc_attr($atts['field']) . '" debug="no"]');
            if (!empty($test_value)) {
                $output .= '<p style="color: #388e3c;">✅ Field "' . esc_html($atts['field']) . '" = ' . $test_value . '</p>';
            } else {
                $output .= '<p style="color: #f57c00;">⚠️ Field "' . esc_html($atts['field']) . '" is empty or not found</p>';
            }
        }
        
        $output .= '</div>';
        return $output;
    }
	/**
 * شورت‌کد debug المنتور
 */
public function elementor_debug_shortcode($atts) {
    if (!current_user_can('manage_options')) {
        return '';
    }
    
    $debug = '<div style="background: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; border-radius: 4px; font-family: monospace; font-size: 12px;">';
    $debug .= '<h4>🎨 Elementor Debug Info:</h4>';
    
    // بررسی المنتور
    $debug .= '<p><strong>Elementor Loaded:</strong> ' . (did_action('elementor/loaded') ? '✅ YES' : '❌ NO') . '</p>';
    
    // بررسی loader class
    $debug .= '<p><strong>Loader Class Exists:</strong> ' . (class_exists('Standalone_GSheets_Elementor_Loader') ? '✅ YES' : '❌ NO') . '</p>';
    
    if (did_action('elementor/loaded')) {
        // بررسی ویجت منیجر
        if (class_exists('\Elementor\Plugin')) {
            $widgets_manager = \Elementor\Plugin::instance()->widgets_manager;
            $debug .= '<p><strong>Widgets Manager:</strong> ' . ($widgets_manager ? '✅ Available' : '❌ Not Available') . '</p>';
            
            if ($widgets_manager) {
                // لیست ویجت‌های ثبت شده
                $registered_widgets = $widgets_manager->get_widget_types();
                $gsheet_widgets = [];
                
                foreach ($registered_widgets as $widget_name => $widget) {
                    if (strpos($widget_name, 'gsheets') !== false) {
                        $gsheet_widgets[] = $widget_name;
                    }
                }
                
                $debug .= '<p><strong>GSheets Widgets:</strong> ' . 
                         (!empty($gsheet_widgets) ? implode(', ', $gsheet_widgets) : 'None found') . '</p>';
            }
        }
        
        // بررسی دسته‌بندی‌ها
        if (class_exists('\Elementor\Plugin')) {
            $elements_manager = \Elementor\Plugin::instance()->elements_manager;
            if ($elements_manager && method_exists($elements_manager, 'get_categories')) {
                $categories = $elements_manager->get_categories();
                $debug .= '<p><strong>Google Sheets Category:</strong> ' . 
                         (isset($categories['google-sheets']) ? '✅ Registered' : '❌ Not Registered') . '</p>';
            }
        }
    }
    
    // بررسی فایل‌های ویجت با جزئیات بیشتر
    $widgets_path = STANDALONE_GSHEETS_PATH . 'includes/elementor/widgets/';
    $debug .= '<p><strong>Widgets Directory:</strong> ' . $widgets_path . '</p>';
    $debug .= '<p><strong>Directory Exists:</strong> ' . (is_dir($widgets_path) ? '✅ YES' : '❌ NO') . '</p>';
    
    if (is_dir($widgets_path)) {
        $debug .= '<p><strong>Directory Contents:</strong></p>';
        $files = scandir($widgets_path);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                $full_path = $widgets_path . $file;
                $debug .= '<p>&nbsp;&nbsp;- ' . $file . ' (' . filesize($full_path) . ' bytes)</p>';
            }
        }
        
        $widget_files = ['table-widget.php', 'field-widget.php'];
        foreach ($widget_files as $file) {
            $full_path = $widgets_path . $file;
            $debug .= '<p><strong>' . $file . ':</strong> ' . 
                     (file_exists($full_path) ? '✅ Exists (' . filesize($full_path) . ' bytes)' : '❌ Not Found') . '</p>';
        }
    }
    
    // بررسی loader file
    $loader_path = STANDALONE_GSHEETS_PATH . 'includes/elementor/class-elementor-loader.php';
    $debug .= '<p><strong>Loader File:</strong> ' . 
             (file_exists($loader_path) ? '✅ Exists (' . filesize($loader_path) . ' bytes)' : '❌ Not Found') . '</p>';
    
    // بررسی پلاگین path
    $debug .= '<p><strong>Plugin Path:</strong> ' . STANDALONE_GSHEETS_PATH . '</p>';
    $debug .= '<p><strong>Plugin URL:</strong> ' . STANDALONE_GSHEETS_URL . '</p>';
    
    $debug .= '</div>';
	$debug .= '<h4>Path Tests:</h4>';

// تست با نام‌های مختلف پوشه
$possible_plugin_names = [
    'standalone-gsheets-integration',
    'standalone-google-sheets-reader-v4',
    'gsheets-reader',
    basename(STANDALONE_GSHEETS_PATH)
];

foreach ($possible_plugin_names as $plugin_name) {
    $test_path = WP_PLUGIN_DIR . '/' . $plugin_name . '/includes/elementor/widgets/';
    $debug .= '<p>Testing: ' . $plugin_name . ' - ' . (is_dir($test_path) ? '✅ Found' : '❌ Not Found') . '</p>';
}

// نمایش مسیر واقعی پلاگین
$debug .= '<p><strong>Actual Plugin Folder:</strong> ' . basename(dirname(STANDALONE_GSHEETS_PATH)) . '</p>';

// لیست تمام فایل‌ها در پوشه includes
$includes_path = STANDALONE_GSHEETS_PATH . 'includes/';
if (is_dir($includes_path)) {
    $debug .= '<p><strong>Contents of includes folder:</strong></p>';
    $items = scandir($includes_path);
    foreach ($items as $item) {
        if ($item != '.' && $item != '..') {
            $debug .= '<p>&nbsp;&nbsp;- ' . $item . (is_dir($includes_path . $item) ? ' (directory)' : '') . '</p>';
        }
    }
}
    return $debug;
}
    
    /**
     * بارگذاری اسکریپت‌ها و استایل‌ها
     */
    public function enqueue_scripts() {
        // استایل‌های فرانت‌اند
        wp_enqueue_style(
            'standalone-gsheets-frontend',
            STANDALONE_GSHEETS_URL . 'assets/css/frontend.css',
            [],
            STANDALONE_GSHEETS_VERSION
        );
        
        // اسکریپت فرانت‌اند
        if (file_exists(STANDALONE_GSHEETS_PATH . 'assets/js/frontend.js')) {
            wp_enqueue_script(
                'standalone-gsheets-frontend',
                STANDALONE_GSHEETS_URL . 'assets/js/frontend.js',
                ['jquery'],
                STANDALONE_GSHEETS_VERSION,
                true
            );
            
            wp_localize_script('standalone-gsheets-frontend', 'standalone_gsheets', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('standalone_gsheets_nonce'),
                'api_version' => STANDALONE_GSHEETS_API_VERSION,
                'loading' => __('Loading...', 'standalone-gsheets'),
                'error' => __('Error:', 'standalone-gsheets'),
                'success' => __('Success!', 'standalone-gsheets'),
                'copy_success' => __('Copied to clipboard!', 'standalone-gsheets'),
                'copy_error' => __('Copy failed', 'standalone-gsheets'),
                'retry_attempts' => 3 // اضافه شده برای retry mechanism
            ]);
        }
    }
    
    /**
     * بارگذاری اسکریپت‌های ادمین
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'standalone-gsheets') === false) {
            return;
        }
        
        wp_enqueue_style(
            'standalone-gsheets-admin',
            STANDALONE_GSHEETS_URL . 'assets/css/admin.css',
            [],
            STANDALONE_GSHEETS_VERSION
        );
        
        if (file_exists(STANDALONE_GSHEETS_PATH . 'assets/js/admin.js')) {
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
                'testing' => __('Testing connection...', 'standalone-gsheets'),
                'success' => __('Connection successful!', 'standalone-gsheets'),
                'error' => __('Error:', 'standalone-gsheets')
            ]);
        }
    }
    
    /**
     * اضافه کردن استایل‌های inline
     */
    public function add_inline_styles() {
        echo '<style id="standalone-gsheets-inline-styles">
        .gsheet-field { transition: all 0.3s ease; }
        .gsheet-field:hover { transform: scale(1.02); }
        .gsheet-loading { opacity: 0.7; }
        .gsheet-error { color: #d32f2f; font-weight: 500; }
        .gsheet-success { color: #388e3c; font-weight: 500; }
        </style>';
    }
    
    /**
     * بارگذاری تنظیمات
     */
    private function load_settings() {
        $default_settings = [
            'credentials_path' => '',
            'spreadsheet_id' => '',
            'discord_id_column' => 'Discord ID',
            'cache_time' => STANDALONE_GSHEETS_DEFAULT_CACHE_TIME,
            'api_version' => 'v4',
            'enable_debug' => false,
            'default_typography' => '',
            'default_wrapper' => 'span'
        ];
        
        $this->settings = wp_parse_args(
            get_option('standalone_gsheets_settings', []),
            $default_settings
        );
        
        // اعتبارسنجی cache_time
        $cache_time = (int) $this->settings['cache_time'];
        if ($cache_time < STANDALONE_GSHEETS_MIN_CACHE_TIME || $cache_time > STANDALONE_GSHEETS_MAX_CACHE_TIME) {
            $this->settings['cache_time'] = STANDALONE_GSHEETS_DEFAULT_CACHE_TIME;
        }
    }
    
    /**
     * مقداردهی اولیه API
     */
    public function init_api() {
        if (class_exists('Standalone_GSheets_API')) {
            $credentials_path = $this->get_setting('credentials_path');
            if (!empty($credentials_path) && file_exists($credentials_path)) {
                try {
                    $this->api = new Standalone_GSheets_API($credentials_path);
                    
                    $spreadsheet_id = $this->get_setting('spreadsheet_id');
                    if (!empty($spreadsheet_id)) {
                        $this->api->set_spreadsheet_id($spreadsheet_id);
                    }
                    
                    if ($this->logger) {
                        $this->logger->info('API initialized successfully');
                    }
                } catch (Exception $e) {
                    if ($this->logger) {
                        $this->logger->error('Failed to initialize API', ['error' => $e->getMessage()]);
                    }
                }
            }
        }
    }
    
    /**
     * مقداردهی اولیه شورت‌کدها
     */
    private function init_shortcodes() {
        if (class_exists('Standalone_GSheets_Shortcodes')) {
            $this->shortcodes = new Standalone_GSheets_Shortcodes($this->api);
        }
    }
    
    /**
     * مقداردهی اولیه بخش ادمین
     */
    private function init_admin() {
        if (is_admin() && class_exists('Standalone_GSheets_Admin')) {
            $this->admin = new Standalone_GSheets_Admin();
        }
    }
    
    /**
     * ثبت AJAX handlers
     */
    private function register_ajax_handlers() {
        $ajax_actions = [
            'get_user_data',
            'get_cell_value',
            'test_connection',
            'get_sheet_fields',
            'clear_cache'
        ];
        
        foreach ($ajax_actions as $action) {
            add_action("wp_ajax_standalone_gsheets_{$action}", [$this, "ajax_{$action}"]);
            add_action("wp_ajax_nopriv_standalone_gsheets_{$action}", [$this, "ajax_{$action}"]);
        }
    }
    
    /**
     * AJAX: دریافت داده‌های کاربر
     */
    public function ajax_get_user_data() {
        check_ajax_referer('standalone_gsheets_nonce', 'nonce');
        
        if (!$this->api || !$this->api->is_ready()) {
            wp_send_json_error('API not ready');
        }
        
        $discord_id = $this->get_current_user_discord_id();
        if (!$discord_id) {
            wp_send_json_error('Discord ID not found');
        }
        
        $spreadsheet_id = sanitize_text_field($_POST['spreadsheet_id'] ?? $this->get_setting('spreadsheet_id'));
        $sheet_title = sanitize_text_field($_POST['sheet_title'] ?? '');
        $field = sanitize_text_field($_POST['field'] ?? '');
        
        if (empty($spreadsheet_id)) {
            wp_send_json_error('Spreadsheet ID not configured');
        }
        
        try {
            $this->api->set_spreadsheet_id($spreadsheet_id);
            $user_data = $this->api->find_user_by_discord_id(
                $discord_id,
                $sheet_title ?: null,
                $this->get_setting('discord_id_column', 'Discord ID'),
                $spreadsheet_id
            );
            
            if ($field && !empty($user_data)) {
                foreach ($user_data as $sheet_data) {
                    if (isset($sheet_data[$field])) {
                        wp_send_json_success($sheet_data[$field]);
                    }
                }
                wp_send_json_error("Field '{$field}' not found");
            }
            
            wp_send_json_success($user_data);
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('AJAX get_user_data error', ['error' => $e->getMessage()]);
            }
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX: دریافت مقدار سلول
     */
    public function ajax_get_cell_value() {
        check_ajax_referer('standalone_gsheets_nonce', 'nonce');
        
        if (!$this->api || !$this->api->is_ready()) {
            wp_send_json_error('API not ready');
        }
        
        $spreadsheet_id = sanitize_text_field($_POST['spreadsheet_id'] ?? $this->get_setting('spreadsheet_id'));
        $sheet_title = sanitize_text_field($_POST['sheet_title'] ?? '');
        $cell = sanitize_text_field($_POST['cell'] ?? '');
        
        if (empty($sheet_title) || empty($cell)) {
            wp_send_json_error('Missing parameters');
        }
        
        try {
            $this->api->set_spreadsheet_id($spreadsheet_id);
            $value = $this->api->get_cell_value($sheet_title, $cell, $spreadsheet_id);
            wp_send_json_success($value);
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('AJAX get_cell_value error', ['error' => $e->getMessage()]);
            }
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX: پاک کردن کش
     */
    public function ajax_clear_cache() {
        check_ajax_referer('standalone_gsheets_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
        }
        
        if ($this->api) {
            $this->api->clear_all_cache();
        }
        
        if ($this->logger) {
            $this->logger->info('Cache cleared via AJAX');
        }
        wp_send_json_success('Cache cleared successfully');
    }
    
    /**
     * دریافت تنظیم
     */
    public function get_setting($key, $default = null) {
        if (empty($this->settings)) {
            $this->load_settings();
        }
        return $this->settings[$key] ?? $default;
    }
    
    /**
     * بروزرسانی تنظیم
     */
    public function update_setting($key, $value) {
        $this->settings[$key] = $value;
        return update_option('standalone_gsheets_settings', $this->settings);
    }
    
    /**
     * دریافت Discord ID کاربر فعلی با validation
     */
    public function get_current_user_discord_id() {
        if (!is_user_logged_in()) {
            return null;
        }
        
        $user_id = get_current_user_id();
        
        $possible_keys = [
            'discord_user_id',
            'discord_id',
            'wpdd_discord_user_id',
            'wpdc_discord_user_id',
            '_discord_userid',
            'discord_userid',
            'user_discord_id'
        ];
        
        foreach ($possible_keys as $key) {
            $value = get_user_meta($user_id, $key, true);
            if (!empty($value)) {
                $discord_id = is_array($value) ? $value[0] : $value;
                
                // Validation: Discord ID باید 17-19 رقم باشد
                if (preg_match('/^\d{17,19}$/', $discord_id)) {
                    if ($this->logger) {
                        $this->logger->debug('Valid Discord ID found', ['user_id' => $user_id, 'discord_id' => $discord_id]);
                    }
                    return $discord_id;
                }
            }
        }
        
        // جستجوی فازی
        $all_meta = get_user_meta($user_id);
        foreach ($all_meta as $key => $value) {
            if (stripos($key, 'discord') !== false && !empty($value)) {
                $discord_value = is_array($value) ? $value[0] : $value;
                
                // Validation
                if (preg_match('/^\d{17,19}$/', $discord_value)) {
                    if ($this->logger) {
                        $this->logger->debug('Discord ID found via fuzzy search', ['user_id' => $user_id, 'key' => $key]);
                    }
                    return $discord_value;
                }
            }
        }
        
        if ($this->logger) {
            $this->logger->warning('No valid Discord ID found', ['user_id' => $user_id]);
        }
        return null;
    }
    
    /**
     * هوک فعال‌سازی
     */
    public function activation_hook() {
        // ایجاد تنظیمات پیش‌فرض
        if (!get_option('standalone_gsheets_settings')) {
            $default_settings = [
                'api_version' => 'v4',
                'cache_time' => STANDALONE_GSHEETS_DEFAULT_CACHE_TIME,
                'discord_id_column' => 'Discord ID',
                'enable_debug' => false
            ];
            update_option('standalone_gsheets_settings', $default_settings);
        }
        
        // ایجاد دایرکتوری امن
        $this->ensure_secure_directory();
        
        // پاک کردن کش‌های قدیمی
        if ($this->api) {
            $this->api->clear_all_cache();
        }
        
        if ($this->logger) {
            $this->logger->info('Plugin activated');
        }
    }
    
    /**
     * هوک غیرفعال‌سازی
     */
    public function deactivation_hook() {
        // پاک کردن کش‌ها
        if ($this->api) {
            $this->api->clear_all_cache();
        }
        
        if ($this->logger) {
            $this->logger->info('Plugin deactivated');
        }
    }
}

/**
 * تابع دسترسی به پلاگین
 */
function standalone_gsheets() {
    return Standalone_GSheets_Integration::get_instance();
}

/**
 * شروع پلاگین
 */
add_action('plugins_loaded', function() {
    standalone_gsheets();
}, 10);

/**
 * توابع کمکی عمومی
 */

/**
 * دریافت مقدار فیلد برای کاربر فعلی
 */
function gsheet_get_field($field_name, $default = '') {
    $plugin = standalone_gsheets();
    if (!$plugin->api || !$plugin->api->is_ready()) {
        return $default;
    }
    
    $discord_id = $plugin->get_current_user_discord_id();
    if (!$discord_id) {
        return $default;
    }
    
    $spreadsheet_id = $plugin->get_setting('spreadsheet_id');
    if (empty($spreadsheet_id)) {
        return $default;
    }
    
    try {
        $plugin->api->set_spreadsheet_id($spreadsheet_id);
        $user_data = $plugin->api->find_user_by_discord_id(
            $discord_id,
            null,
            $plugin->get_setting('discord_id_column', 'Discord ID'),
            $spreadsheet_id
        );
        
        foreach ($user_data as $sheet_data) {
            if (isset($sheet_data[$field_name])) {
                return $sheet_data[$field_name];
            }
        }
        
        return $default;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * بررسی اینکه آیا کاربر Discord ID دارد
 */
function gsheet_has_discord_id() {
    $plugin = standalone_gsheets();
    return !empty($plugin->get_current_user_discord_id());
}

/**
 * پاک کردن کش شورت‌کدها
 */
function gsheet_clear_cache() {
    $plugin = standalone_gsheets();
    if ($plugin->api) {
        $plugin->api->clear_all_cache();
    }
}

// ========== شورت‌کدهای دیباگ کامل ==========

/**
 * شورت‌کد دیباگ کامل برای تشخیص مشکل
 */
add_shortcode('debug_gsheets_full', function($atts) {
    if (!current_user_can('manage_options')) {
        return '<p style="color: red;">دسترسی غیرمجاز</p>';
    }
    
    $debug = '<div style="background: #f8f9fa; border: 2px solid #007cba; border-radius: 8px; padding: 20px; margin: 20px 0; font-family: monospace; font-size: 12px;">';
    $debug .= '<h2 style="margin-top: 0; color: #007cba;">🔧 دیباگ کامل Google Sheets Plugin</h2>';
    
    // 1. بررسی Plugin Instance
    $debug .= '<h3>1. Plugin Instance</h3>';
    $plugin = standalone_gsheets();
    if ($plugin) {
        $debug .= '<p style="color: green;">✅ Plugin instance موجود است</p>';
        
        // بررسی API object
        if (isset($plugin->api)) {
            if ($plugin->api === null) {
                $debug .= '<p style="color: red;">❌ API object null است</p>';
            } else {
                $debug .= '<p style="color: green;">✅ API object موجود است (' . get_class($plugin->api) . ')</p>';
                
                // بررسی is_ready
                if (method_exists($plugin->api, 'is_ready')) {
                    $is_ready = $plugin->api->is_ready();
                    if ($is_ready) {
                        $debug .= '<p style="color: green;">✅ API ready است</p>';
                    } else {
                        $debug .= '<p style="color: red;">❌ API ready نیست</p>';
                    }
                } else {
                    $debug .= '<p style="color: red;">❌ متد is_ready موجود نیست</p>';
                }
            }
        } else {
            $debug .= '<p style="color: red;">❌ Property api موجود نیست</p>';
        }
        
        // بررسی Logger
        if (isset($plugin->logger)) {
            $debug .= '<p style="color: green;">✅ Logger موجود است</p>';
        } else {
            $debug .= '<p style="color: orange;">⚠️ Logger موجود نیست</p>';
        }
    } else {
        $debug .= '<p style="color: red;">❌ Plugin instance موجود نیست</p>';
    }
    
    // 2. بررسی تنظیمات
    $debug .= '<h3>2. تنظیمات</h3>';
    $settings = get_option('standalone_gsheets_settings', []);
    if (empty($settings)) {
        $debug .= '<p style="color: red;">❌ تنظیمات پیدا نشد</p>';
    } else {
        $debug .= '<p style="color: green;">✅ تنظیمات موجود است</p>';
        
        // بررسی credentials path
        $credentials_path = $settings['credentials_path'] ?? '';
        if (empty($credentials_path)) {
            $debug .= '<p style="color: red;">❌ مسیر credentials خالی است</p>';
        } else {
            $debug .= '<p>📁 مسیر credentials: ***HIDDEN***</p>';
            
            if (file_exists($credentials_path)) {
                $debug .= '<p style="color: green;">✅ فایل credentials موجود است</p>';
                
                // بررسی محتوای فایل
                $content = file_get_contents($credentials_path);
                if ($content) {
                    $json = json_decode($content, true);
                    if ($json && json_last_error() === JSON_ERROR_NONE) {
                        $debug .= '<p style="color: green;">✅ فایل JSON معتبر است</p>';
                        $debug .= '<p>🔑 Project ID: ' . esc_html($json['project_id'] ?? 'نامشخص') . '</p>';
                        $debug .= '<p>📧 Client Email: ' . esc_html(substr($json['client_email'] ?? '', 0, 15) . '...') . '</p>';
                    } else {
                        $debug .= '<p style="color: red;">❌ فایل JSON نامعتبر است: ' . json_last_error_msg() . '</p>';
                    }
                } else {
                    $debug .= '<p style="color: red;">❌ نمی‌توان محتوای فایل را خواند</p>';
                }
            } else {
                $debug .= '<p style="color: red;">❌ فایل credentials موجود نیست</p>';
            }
        }
        
        // سایر تنظیمات
        $spreadsheet_id = $settings['spreadsheet_id'] ?? '';
        if (empty($spreadsheet_id)) {
            $debug .= '<p style="color: orange;">⚠️ Spreadsheet ID خالی است</p>';
        } else {
            $debug .= '<p>📊 Spreadsheet ID: ' . esc_html(substr($spreadsheet_id, 0, 20)) . '...</p>';
        }
        
        $cache_time_default = defined('STANDALONE_GSHEETS_DEFAULT_CACHE_TIME') ? STANDALONE_GSHEETS_DEFAULT_CACHE_TIME : 300;
        $debug .= '<p>⏱️ Cache Time: ' . ($settings['cache_time'] ?? $cache_time_default) . ' seconds</p>';
    }
    
    // 3. بررسی دایرکتوری امن
    $debug .= '<h3>3. دایرکتوری امن</h3>';
    if (defined('STANDALONE_GSHEETS_PRIVATE_DIR')) {
        $debug .= '<p>📁 Private Directory: ' . STANDALONE_GSHEETS_PRIVATE_DIR . '</p>';
        if (file_exists(STANDALONE_GSHEETS_PRIVATE_DIR)) {
            $debug .= '<p style="color: green;">✅ دایرکتوری موجود است</p>';
            if (file_exists(STANDALONE_GSHEETS_PRIVATE_DIR . '/.htaccess')) {
                $debug .= '<p style="color: green;">✅ فایل .htaccess موجود است</p>';
            } else {
                $debug .= '<p style="color: orange;">⚠️ فایل .htaccess موجود نیست</p>';
            }
        } else {
            $debug .= '<p style="color: red;">❌ دایرکتوری موجود نیست</p>';
        }
    } else {
        $debug .= '<p style="color: red;">❌ STANDALONE_GSHEETS_PRIVATE_DIR تعریف نشده</p>';
    }
    
    // 4. Discord ID Validation
    $debug .= '<h3>4. Discord ID Validation</h3>';
    if ($plugin) {
        $discord_id = $plugin->get_current_user_discord_id();
        if ($discord_id) {
            $debug .= '<p style="color: green;">✅ Discord ID: ' . esc_html($discord_id) . '</p>';
            $debug .= '<p>✅ Format validation passed (17-19 digits)</p>';
        } else {
            $debug .= '<p style="color: red;">❌ No valid Discord ID found</p>';
        }
    }
    
    // 5. بررسی المنتور
    $debug .= '<h3>5. Elementor Integration</h3>';
    if (did_action('elementor/loaded')) {
        $debug .= '<p style="color: green;">✅ Elementor is loaded</p>';
        
        // بررسی فایل‌های ویجت
        $loader_file = STANDALONE_GSHEETS_PATH . 'includes/elementor/class-elementor-loader.php';
        if (file_exists($loader_file)) {
            $debug .= '<p style="color: green;">✅ Elementor loader file exists</p>';
        } else {
            $debug .= '<p style="color: red;">❌ Elementor loader file missing</p>';
        }
    } else {
        $debug .= '<p style="color: orange;">⚠️ Elementor is not loaded</p>';
    }
    
    $debug .= '<p style="margin-bottom: 0; font-size: 10px; color: #666;">Generated at: ' . current_time('Y-m-d H:i:s') . '</p>';
    $debug .= '</div>';
    
    return $debug;
});