<?php
/**
 * بارگذاری ویجت‌های المنتور
 */

if (!defined('ABSPATH')) exit;

class Standalone_GSheets_Elementor_Loader {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        error_log('GSheets Elementor Loader Constructor Called');
        
        // استفاده از hook‌های جدید المنتور
        add_action('elementor/widgets/register', [$this, 'register_widgets'], 10);
        add_action('elementor/elements/categories_registered', [$this, 'register_categories'], 10);
        add_action('elementor/frontend/after_enqueue_styles', [$this, 'enqueue_styles']);
        
        // همچنین سازگاری با نسخه‌های قدیمی
        add_action('elementor/widgets/widgets_registered', [$this, 'register_widgets_legacy'], 10);
        
        error_log('GSheets Elementor hooks registered');
    }
    
    /**
     * ثبت دسته‌بندی ویجت‌ها
     */
    public function register_categories($elements_manager) {
        error_log('Registering Google Sheets category');
        
        $elements_manager->add_category(
            'google-sheets',
            [
                'title' => __('Google Sheets', 'standalone-gsheets'),
                'icon' => 'fa fa-table',
            ]
        );
    }
    
    /**
     * ثبت ویجت‌ها - نسخه جدید
     */
    public function register_widgets($widgets_manager) {
        error_log('=== REGISTERING GSHEETS WIDGETS (NEW METHOD) ===');
        
        // بارگذاری فایل‌های ویجت
        $widget_files = [
            'table-widget.php',
            'field-widget.php'
        ];
        
        foreach ($widget_files as $file) {
            $file_path = STANDALONE_GSHEETS_PATH . 'includes/elementor/widgets/' . $file;
            error_log('Loading widget file: ' . $file_path);
            
            if (file_exists($file_path)) {
                require_once $file_path;
                error_log('Widget file loaded: ' . $file);
            } else {
                error_log('ERROR: Widget file not found: ' . $file_path);
            }
        }
        
        // ثبت ویجت‌ها
        if (class_exists('Standalone_GSheets_Table_Widget')) {
            $widgets_manager->register(new \Standalone_GSheets_Table_Widget());
            error_log('Table widget registered successfully');
        } else {
            error_log('ERROR: Table widget class not found');
        }
        
        if (class_exists('Standalone_GSheets_Field_Widget')) {
            $widgets_manager->register(new \Standalone_GSheets_Field_Widget());
            error_log('Field widget registered successfully');
        } else {
            error_log('ERROR: Field widget class not found');
        }
        
        error_log('=== WIDGETS REGISTRATION COMPLETE ===');
    }
    
    /**
     * ثبت ویجت‌ها - سازگاری با نسخه قدیمی
     */
    public function register_widgets_legacy() {
        error_log('=== REGISTERING GSHEETS WIDGETS (LEGACY METHOD) ===');
        
        // بارگذاری فایل‌های ویجت
        require_once STANDALONE_GSHEETS_PATH . 'includes/elementor/widgets/table-widget.php';
        require_once STANDALONE_GSHEETS_PATH . 'includes/elementor/widgets/field-widget.php';
        
        // ثبت با متد قدیمی
        if (class_exists('\Elementor\Plugin') && isset(\Elementor\Plugin::instance()->widgets_manager)) {
            \Elementor\Plugin::instance()->widgets_manager->register_widget_type(new \Standalone_GSheets_Table_Widget());
            \Elementor\Plugin::instance()->widgets_manager->register_widget_type(new \Standalone_GSheets_Field_Widget());
            error_log('Widgets registered using legacy method');
        }
    }
    
    /**
     * بارگذاری استایل‌ها
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            'gsheets-elementor',
            STANDALONE_GSHEETS_URL . 'assets/css/elementor-widgets.css',
            [],
            STANDALONE_GSHEETS_VERSION
        );
        
        error_log('GSheets Elementor styles enqueued');
    }
}