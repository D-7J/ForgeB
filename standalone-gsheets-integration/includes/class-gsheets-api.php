<?php
/**
 * کلاس Wrapper برای Google Sheets API
 * این کلاس به عنوان رابط بین پلاگین و API v4 عمل می‌کند
 * 
 * @since 2.0.0
 */

if (!defined('ABSPATH')) exit;

// بارگذاری کلاس API v4
require_once STANDALONE_GSHEETS_PATH . 'includes/class-gsheets-api-v4.php';

/**
 * کلاس اصلی API که از v4 استفاده می‌کند
 */
class Standalone_GSheets_API extends Standalone_GSheets_API_V4 {
    
    /**
     * سازنده - فقط credentials_path را به parent پاس می‌دهد
     */
    public function __construct($credentials_path) {
        parent::__construct($credentials_path);
    }
    
    /**
     * پاک کردن همه کش‌ها
     */
    public function clear_all_cache() {
        return $this->clear_cache_by_pattern('standalone_gsheets_*');
    }
}