<?php
/**
 * کلاس Logger برای ثبت لاگ‌ها
 * 
 * @since 2.0.1
 */

if (!defined('ABSPATH')) exit;

class Standalone_GSheets_Logger {
    
    private $log_file;
    private $debug_enabled;
    
    /**
     * سازنده
     */
    public function __construct() {
        $this->log_file = WP_CONTENT_DIR . '/debug-gsheets.log';
        $this->debug_enabled = defined('WP_DEBUG') && WP_DEBUG;
    }
    
    /**
     * ثبت لاگ با سطح info
     */
    public function info($message, $context = []) {
        $this->log('INFO', $message, $context);
    }
    
    /**
     * ثبت لاگ با سطح warning
     */
    public function warning($message, $context = []) {
        $this->log('WARNING', $message, $context);
    }
    
    /**
     * ثبت لاگ با سطح error
     */
    public function error($message, $context = []) {
        $this->log('ERROR', $message, $context);
    }
    
    /**
     * ثبت لاگ با سطح debug
     */
    public function debug($message, $context = []) {
        if ($this->debug_enabled) {
            $this->log('DEBUG', $message, $context);
        }
    }
    
    /**
     * ثبت لاگ
     */
    private function log($level, $message, $context = []) {
        if (!$this->debug_enabled && $level === 'DEBUG') {
            return;
        }
        
        $timestamp = current_time('Y-m-d H:i:s');
        $user_id = get_current_user_id();
        
        $log_entry = sprintf(
            "[%s] [%s] [User: %d] %s",
            $timestamp,
            $level,
            $user_id,
            $message
        );
        
        if (!empty($context)) {
            $log_entry .= ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        
        $log_entry .= PHP_EOL;
        
        // ثبت در فایل
        error_log($log_entry, 3, $this->log_file);
        
        // اگر سطح ERROR بود، در error_log پیش‌فرض WordPress هم ثبت کن
        if ($level === 'ERROR') {
            error_log('Standalone GSheets Error: ' . $message . ' | ' . json_encode($context));
        }
    }
    
    /**
     * پاک کردن لاگ‌ها
     */
    public function clear_logs() {
        if (file_exists($this->log_file)) {
            return unlink($this->log_file);
        }
        return true;
    }
    
    /**
     * دریافت لاگ‌ها
     */
    public function get_logs($lines = 100) {
        if (!file_exists($this->log_file)) {
            return [];
        }
        
        $logs = [];
        $file = new SplFileObject($this->log_file, 'r');
        $file->seek(PHP_INT_MAX);
        $total_lines = $file->key();
        
        $start_line = max(0, $total_lines - $lines);
        
        for ($i = $start_line; $i <= $total_lines; $i++) {
            $file->seek($i);
            $line = $file->current();
            if (!empty(trim($line))) {
                $logs[] = trim($line);
            }
        }
        
        return array_reverse($logs);
    }
}