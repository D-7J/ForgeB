<?php
/**
 * کلاس شورت‌کدهای مستقل - نسخه 2.0 با API v4 (اصلاح شده - بدون لاگ)
 * 
 * @since 2.0.0
 */

if (!defined('ABSPATH')) exit;

class Standalone_GSheets_Shortcodes {
    // API گوگل شیت v4
    private $api;
    
    // آمار shortcode ها
    private $shortcode_stats = [
        'calls' => 0,
        'cache_hits' => 0,
        'errors' => 0
    ];
    
    /**
     * سازنده
     */
    public function __construct($api) {
        $this->api = $api;
        
        // ثبت شورت‌کد‌های اصلی
        add_shortcode('gsheet_user_data', [$this, 'user_data_shortcode']);
        add_shortcode('gsheet_cell', [$this, 'cell_data_shortcode']);
        add_shortcode('gsheet_user_field', [$this, 'user_field_shortcode']);
        add_shortcode('gsheet_field', [$this, 'field_shortcode']);
        
        // شورت‌کدهای batch (ویژگی جدید API v4)
        add_shortcode('gsheet_user_data_batch', [$this, 'user_data_batch_shortcode']);
        add_shortcode('gsheet_multiple_cells', [$this, 'multiple_cells_shortcode']);
        
        // ثبت شورت‌کد دینامیک برای فیلدها
        add_action('init', [$this, 'register_dynamic_shortcodes']);
        
        // شورت‌کد آمار (برای debug)
        add_shortcode('gsheet_stats', [$this, 'stats_shortcode']);
    }
    
    /**
     * دریافت تنظیمات مستقیماً از دیتابیس با کش
     */
    private function get_db_setting($key, $default = null) {
        static $settings_cache = null;
        
        if ($settings_cache === null) {
            $settings_cache = get_option('standalone_gsheets_settings', []);
        }
        
        return isset($settings_cache[$key]) ? $settings_cache[$key] : $default;
    }
    
    /**
     * ایجاد تغییرات مختلف نام فیلد برای جستجوی بهتر
     */
    private function generate_field_variations($field_name) {
        $variations = [];
        $original = trim($field_name);
        
        // اضافه کردن نام اصلی
        $variations[] = $original;
        
        // تبدیل فاصله‌ها به underscore و برعکس
        $variations[] = str_replace(' ', '_', $original);
        $variations[] = str_replace('_', ' ', $original);
        
        // تبدیل camelCase به snake_case
        $snake_case = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $original));
        $variations[] = $snake_case;
        
        // تبدیل snake_case به PascalCase
        $pascal_case = str_replace(' ', '', ucwords(str_replace('_', ' ', $original)));
        $variations[] = $pascal_case;
        
        // تبدیل به camelCase
        $camel_case = lcfirst($pascal_case);
        $variations[] = $camel_case;
        
        // اضافه کردن حالت‌های مختلف case
        $variations[] = strtoupper($original);
        $variations[] = strtolower($original);
        $variations[] = ucfirst(strtolower($original));
        $variations[] = ucwords(strtolower($original));
        
        // حذف duplicates
        return array_unique($variations);
    }
    
    /**
     * ثبت شورت‌کدهای دینامیک برای فیلدهای مختلف
     */
    public function register_dynamic_shortcodes() {
        $common_fields = [
            'discord_id' => ['Discord ID', 'DiscordID', 'discord_id', 'discord_user_id', 'DISCORD_ID'],
            'character_name' => ['Character Name', 'character_name', 'CharacterName', 'Character_Name', 'char_name'],
            'realm_name' => ['Realm Name', 'realm_name', 'RealmName', 'Realm_Name', 'realm'],
            'guild_name' => ['Guild Name', 'guild_name', 'GuildName', 'Guild_Name', 'guild'],
            'class' => ['Class', 'class', 'Player_Class', 'character_class'],
            'level' => ['Level', 'level', 'Player_Level', 'character_level'],
            'email' => ['Email', 'email', 'E-mail', 'user_email'],
            'name' => ['Name', 'name', 'Player_Name', 'user_name', 'display_name'],
            'race' => ['Race', 'race', 'Player_Race', 'character_race'],
            'faction' => ['Faction', 'faction', 'Player_Faction'],
            'score' => ['Score', 'score', 'points', 'Player_Score'],
            'rank' => ['Rank', 'rank', 'ranking', 'Player_Rank']
        ];
        
        foreach ($common_fields as $shortcode_suffix => $field_variations) {
            $shortcode_name = 'gsheet_' . $shortcode_suffix;
            add_shortcode($shortcode_name, function($atts) use ($field_variations) {
                return $this->get_field_value_by_variations($field_variations, $atts);
            });
        }
    }
    
    /**
     * شورت‌کد نمایش آمار
     */
    public function stats_shortcode($atts) {
        if (!current_user_can('manage_options')) {
            return '';
        }
        
        $atts = shortcode_atts([
            'format' => 'simple'
        ], $atts);
        
        $output = '<div class="gsheet-stats" style="background: #f0f0f0; padding: 10px; border-radius: 5px; font-size: 12px;">';
        $output .= '<strong>📊 Shortcodes Stats (API v4):</strong><br>';
        $output .= 'Calls: ' . $this->shortcode_stats['calls'] . ' | ';
        $output .= 'Cache Hits: ' . $this->shortcode_stats['cache_hits'] . ' | ';
        $output .= 'Errors: ' . $this->shortcode_stats['errors'];
        
        if ($this->api) {
            $api_info = $this->api->get_api_info();
            $output .= '<br><strong>API:</strong> ' . $api_info['version'] . ' | ';
            $output .= 'Requests: ' . $api_info['performance_stats']['requests_made'] . ' | ';
            $output .= 'Batch: ' . $api_info['performance_stats']['batch_requests'];
        }
        
        $output .= '</div>';
        return $output;
    }
    
    /**
     * شورت‌کد field اصلی (اصلاح شده برای prefix/suffix)
     */
    public function field_shortcode($atts) {
        $atts = shortcode_atts([
            'field' => '',
            'spreadsheet_id' => '',
            'sheet' => '',
            'discord_id_column' => '',
            'default' => '',
            'debug' => 'no',
            'cache' => 'yes',
            // استایل و تایپوگرافی
            'typography' => '', // badge, h1-h6, span, div, p, strong
            'class' => '',
            'id' => '',
            'style' => '',
            'color' => '',
            'background' => '',
            'size' => '', // small, medium, large, xlarge, xxlarge
            'weight' => '', // normal, bold, bolder, lighter, 100-900
            'align' => '', // left, center, right, justify
            'transform' => '', // uppercase, lowercase, capitalize
            'decoration' => '', // underline, overline, line-through
            'font' => '', // serif, sans-serif, monospace, cursive
            'padding' => '',
            'margin' => '',
            'border' => '',
            'radius' => '',
            'shadow' => '', // none, small, medium, large
            'animate' => '', // fade, slide, zoom, bounce
            'icon' => '', // emoji or icon class
            'prefix' => '',
            'suffix' => '',
            'wrapper' => 'span', // wrapper element
            // جدید: استایل جداگانه برای prefix/suffix
            'prefix_style' => '',
            'suffix_style' => '',
            'prefix_color' => '',
            'suffix_color' => '',
            'prefix_size' => '',
            'suffix_size' => '',
            'prefix_weight' => '',
            'suffix_weight' => ''
        ], $atts, 'gsheet_field');
        
        $this->shortcode_stats['calls']++;
        
        // بررسی وجود پارامتر field
        if (empty($atts['field'])) {
            $this->shortcode_stats['errors']++;
            return !empty($atts['default']) ? $atts['default'] : '';
        }
        
        // بررسی API
        if (!$this->api || !$this->api->is_ready()) {
            $this->shortcode_stats['errors']++;
            return !empty($atts['default']) ? $atts['default'] : '';
        }
        
        // اگر debug فعال باشد
        if ($atts['debug'] === 'yes') {
            return $this->debug_field_value($atts['field'], $atts);
        }
        
        // دریافت مقدار فیلد
        $value = $this->get_field_value_with_style($atts);
        
        // اگر مقدار خالی بود، مقدار پیش‌فرض را برگردان
        if (empty($value) && !empty($atts['default'])) {
            $value = $atts['default'];
        }
        
        // اگر مقدار هنوز خالی است
        if (empty($value)) {
            return '';
        }
        
        // اعمال استایل و تایپوگرافی
        return $this->apply_field_styling($value, $atts);
    }
    
    /**
     * دریافت مقدار فیلد با در نظر گرفتن استایل
     */
    private function get_field_value_with_style($atts) {
        // دریافت Discord ID کاربر
        $discord_id = standalone_gsheets()->get_current_user_discord_id();
        if (!$discord_id) {
            return '';
        }
        
        // تنظیم آی‌دی اسپردشیت
        $spreadsheet_id = $atts['spreadsheet_id'] ?: $this->get_db_setting('spreadsheet_id');
        if (empty($spreadsheet_id)) {
            return '';
        }
        
        try {
            $this->api->set_spreadsheet_id($spreadsheet_id);
            $sheet_title = $atts['sheet'] ?: null;
            $discord_id_column = $atts['discord_id_column'] ?: $this->get_db_setting('discord_id_column', 'Discord ID');
            
            $user_data = $this->api->find_user_by_discord_id(
                $discord_id,
                $sheet_title,
                $discord_id_column,
                $spreadsheet_id
            );
            
            if (empty($user_data)) {
                return '';
            }
            
            // جستجوی فیلد
            $field_variations = $this->generate_field_variations($atts['field']);
            
            foreach ($user_data as $sheet_data) {
                // Exact match
                foreach ($field_variations as $variation) {
                    if (isset($sheet_data[$variation])) {
                        return $this->sanitize_field_value($sheet_data[$variation]);
                    }
                }
                
                // Case-insensitive match
                foreach ($sheet_data as $key => $value) {
                    foreach ($field_variations as $variation) {
                        if (strtolower($key) === strtolower($variation)) {
                            return $this->sanitize_field_value($value);
                        }
                    }
                }
            }
            
            return '';
            
        } catch (Exception $e) {
            return '';
        }
    }
    
    /**
     * اعمال استایل به فیلد (اصلاح شده برای prefix/suffix)
     */
    private function apply_field_styling($value, $atts) {
        // انتخاب wrapper element
        $wrapper = in_array($atts['wrapper'], ['span', 'div', 'p', 'strong', 'em']) ? $atts['wrapper'] : 'span';
        
        // اگر typography مشخص شده، از آن استفاده کن
        if (!empty($atts['typography'])) {
            switch ($atts['typography']) {
                case 'badge':
                    $wrapper = 'span';
                    $atts['class'] .= ' gsheet-badge';
                    break;
                case 'h1':
                case 'h2':
                case 'h3':
                case 'h4':
                case 'h5':
                case 'h6':
                    $wrapper = $atts['typography'];
                    break;
                case 'strong':
                    $wrapper = 'strong';
                    break;
            }
        }
        
        // ساخت استایل inline برای wrapper
        $wrapper_style = $this->build_element_style($atts);
        
        // استایل‌های جداگانه برای prefix و suffix
        $prefix_style = $this->build_prefix_suffix_style($atts, 'prefix');
        $suffix_style = $this->build_prefix_suffix_style($atts, 'suffix');
        
        // اضافه کردن کلاس‌های CSS
        $classes = ['gsheet-field'];
        
        if (!empty($atts['class'])) {
            $classes[] = $atts['class'];
        }
        
        if (!empty($atts['animate'])) {
            $classes[] = 'gsheet-animate gsheet-animate-' . $atts['animate'];
        }
        
        if ($atts['typography'] === 'badge') {
            $wrapper_style .= 'display: inline-block; padding: 0.25em 0.6em; font-size: 0.875em; font-weight: 700; line-height: 1; text-align: center; white-space: nowrap; vertical-align: baseline; border-radius: 0.25rem;';
            if (empty($atts['background'])) {
                $wrapper_style .= 'background-color: #007cba;';
            }
            if (empty($atts['color'])) {
                $wrapper_style .= 'color: #fff;';
            }
        }
        
        // ساخت HTML
        $html = '<' . $wrapper;
        
        if (!empty($atts['id'])) {
            $html .= ' id="' . esc_attr($atts['id']) . '"';
        }
        
        if (!empty($classes)) {
            $html .= ' class="' . esc_attr(implode(' ', $classes)) . '"';
        }
        
        if (!empty($wrapper_style)) {
            $html .= ' style="' . esc_attr($wrapper_style) . '"';
        }
        
        $html .= '>';
        
        // اضافه کردن icon
        if (!empty($atts['icon'])) {
            $html .= '<span class="gsheet-field-icon">' . $atts['icon'] . '</span> ';
        }
        
        // اضافه کردن prefix با استایل
        if (!empty($atts['prefix'])) {
            $html .= '<span class="gsheet-field-prefix"';
            if (!empty($prefix_style)) {
                $html .= ' style="' . esc_attr($prefix_style) . '"';
            }
            $html .= '>' . esc_html($atts['prefix']) . '</span>';
        }
        
        // مقدار اصلی
        $html .= '<span class="gsheet-field-value">' . esc_html($value) . '</span>';
        
        // اضافه کردن suffix با استایل
        if (!empty($atts['suffix'])) {
            $html .= '<span class="gsheet-field-suffix"';
            if (!empty($suffix_style)) {
                $html .= ' style="' . esc_attr($suffix_style) . '"';
            }
            $html .= '>' . esc_html($atts['suffix']) . '</span>';
        }
        
        $html .= '</' . $wrapper . '>';
        
        return $html;
    }
    
    /**
     * ساخت استایل برای element اصلی
     */
    private function build_element_style($atts) {
        $style = '';
        
        if (!empty($atts['color'])) {
            $style .= 'color: ' . $atts['color'] . ';';
        }
        
        if (!empty($atts['background'])) {
            $style .= 'background-color: ' . $atts['background'] . ';';
        }
        
        if (!empty($atts['size'])) {
            switch ($atts['size']) {
                case 'small':
                    $style .= 'font-size: 0.875em;';
                    break;
                case 'medium':
                    $style .= 'font-size: 1em;';
                    break;
                case 'large':
                    $style .= 'font-size: 1.25em;';
                    break;
                case 'xlarge':
                    $style .= 'font-size: 1.5em;';
                    break;
                case 'xxlarge':
                    $style .= 'font-size: 2em;';
                    break;
                default:
                    if (is_numeric($atts['size'])) {
                        $style .= 'font-size: ' . $atts['size'] . 'px;';
                    }
            }
        }
        
        if (!empty($atts['weight'])) {
            $style .= 'font-weight: ' . $atts['weight'] . ';';
        }
        
        if (!empty($atts['align'])) {
            $style .= 'text-align: ' . $atts['align'] . ';';
            if (in_array($atts['wrapper'] ?? 'span', ['span', 'strong', 'em'])) {
                $style .= 'display: block;';
            }
        }
        
        if (!empty($atts['transform'])) {
            $style .= 'text-transform: ' . $atts['transform'] . ';';
        }
        
        if (!empty($atts['decoration'])) {
            $style .= 'text-decoration: ' . $atts['decoration'] . ';';
        }
        
        if (!empty($atts['font'])) {
            $style .= 'font-family: ' . $atts['font'] . ';';
        }
        
        if (!empty($atts['padding'])) {
            $style .= 'padding: ' . $atts['padding'] . ';';
        }
        
        if (!empty($atts['margin'])) {
            $style .= 'margin: ' . $atts['margin'] . ';';
        }
        
        if (!empty($atts['border'])) {
            $style .= 'border: ' . $atts['border'] . ';';
        }
        
        if (!empty($atts['radius'])) {
            $style .= 'border-radius: ' . $atts['radius'] . ';';
        }
        
        // اضافه کردن shadow
        if (!empty($atts['shadow'])) {
            switch ($atts['shadow']) {
                case 'small':
                    $style .= 'box-shadow: 0 1px 3px rgba(0,0,0,0.12);';
                    break;
                case 'medium':
                    $style .= 'box-shadow: 0 4px 6px rgba(0,0,0,0.1);';
                    break;
                case 'large':
                    $style .= 'box-shadow: 0 10px 15px rgba(0,0,0,0.15);';
                    break;
            }
        }
        
        // ترکیب استایل‌ها
        if (!empty($atts['style'])) {
            $style .= $atts['style'];
        }
        
        return $style;
    }
    
    /**
     * ساخت استایل برای prefix یا suffix
     */
    private function build_prefix_suffix_style($atts, $type) {
        $style = '';
        
        // رنگ
        if (!empty($atts[$type . '_color'])) {
            $style .= 'color: ' . $atts[$type . '_color'] . ';';
        }
        
        // اندازه فونت
        if (!empty($atts[$type . '_size'])) {
            switch ($atts[$type . '_size']) {
                case 'small':
                    $style .= 'font-size: 0.875em;';
                    break;
                case 'medium':
                    $style .= 'font-size: 1em;';
                    break;
                case 'large':
                    $style .= 'font-size: 1.25em;';
                    break;
                case 'xlarge':
                    $style .= 'font-size: 1.5em;';
                    break;
                case 'xxlarge':
                    $style .= 'font-size: 2em;';
                    break;
                default:
                    if (is_numeric($atts[$type . '_size'])) {
                        $style .= 'font-size: ' . $atts[$type . '_size'] . 'px;';
                    }
            }
        }
        
        // وزن فونت
        if (!empty($atts[$type . '_weight'])) {
            $style .= 'font-weight: ' . $atts[$type . '_weight'] . ';';
        }
        
        // استایل سفارشی
        if (!empty($atts[$type . '_style'])) {
            $style .= $atts[$type . '_style'];
        }
        
        // اضافه کردن فاصله پیش‌فرض
        if ($type === 'prefix' && !empty($style)) {
            $style .= 'margin-right: 0.25em;';
        } elseif ($type === 'suffix' && !empty($style)) {
            $style .= 'margin-left: 0.25em;';
        }
        
        return $style;
    }
    
    /**
     * شورت‌کد batch برای چندین سلول (ویژگی API v4)
     */
    public function multiple_cells_shortcode($atts) {
        $atts = shortcode_atts([
            'spreadsheet_id' => '',
            'cells' => '', // A1,B1,C1
            'sheet' => '',
            'format' => 'table',
            'labels' => '', // Label1,Label2,Label3
            'default' => ''
        ], $atts, 'gsheet_multiple_cells');
        
        $this->shortcode_stats['calls']++;
        
        // بررسی API
        if (!$this->api || !$this->api->is_ready()) {
            $this->shortcode_stats['errors']++;
            return '<p style="color: red;">Error: Unable to connect to Google Sheets.</p>';
        }
        
        if (empty($atts['cells']) || empty($atts['sheet'])) {
            $this->shortcode_stats['errors']++;
            return '<p style="color: red;">Parameters cells and sheet are required.</p>';
        }
        
        $spreadsheet_id = $atts['spreadsheet_id'] ?: $this->get_db_setting('spreadsheet_id');
        
        try {
            if (empty($spreadsheet_id)) {
                throw new Exception('Spreadsheet ID not specified.');
            }
            
            $this->api->set_spreadsheet_id($spreadsheet_id);
            
            // آماده‌سازی آرایه سلول‌ها
            $cells = array_map('trim', explode(',', $atts['cells']));
            $labels = !empty($atts['labels']) ? array_map('trim', explode(',', $atts['labels'])) : [];
            
            $cell_ranges = [];
            foreach ($cells as $cell) {
                $cell_ranges[] = $atts['sheet'] . '!' . $cell;
            }
            
            // استفاده از batch operation API v4
            $values = $this->api->get_multiple_cells($cell_ranges, $spreadsheet_id);
            
            if (empty($values)) {
                return !empty($atts['default']) ? esc_html($atts['default']) : '<p>No values found in specified cells.</p>';
            }
            
            // فرمت‌بندی خروجی
            if ($atts['format'] === 'table') {
                $html = '<table class="gsheet-multiple-cells">';
                foreach ($cell_ranges as $index => $range) {
                    $label = isset($labels[$index]) ? $labels[$index] : $cells[$index];
                    $value = isset($values[$range]) ? $values[$range] : '';
                    
                    $html .= '<tr>';
                    $html .= '<th>' . esc_html($label) . '</th>';
                    $html .= '<td>' . esc_html($value) . '</td>';
                    $html .= '</tr>';
                }
                $html .= '</table>';
                return $html;
            } else {
                // فرمت text
                $output = [];
                foreach ($cell_ranges as $index => $range) {
                    $label = isset($labels[$index]) ? $labels[$index] : $cells[$index];
                    $value = isset($values[$range]) ? $values[$range] : '';
                    $output[] = '<strong>' . esc_html($label) . ':</strong> ' . esc_html($value);
                }
                return '<div class="gsheet-multiple-cells">' . implode('<br>', $output) . '</div>';
            }
            
        } catch (Exception $e) {
            $this->shortcode_stats['errors']++;
            return '<p style="color: red;">Error loading data: ' . esc_html($e->getMessage()) . '</p>';
        }
    }
    
    /**
     * شورت‌کد batch برای داده‌های کاربر (ویژگی API v4)
     */
    public function user_data_batch_shortcode($atts) {
        $atts = shortcode_atts([
            'spreadsheet_id' => '',
            'sheets' => '', // Sheet1,Sheet2,Sheet3
            'fields' => '', // field1,field2,field3
            'discord_id_column' => '',
            'format' => 'table',
            'default' => ''
        ], $atts, 'gsheet_user_data_batch');
        
        $this->shortcode_stats['calls']++;
        
        // بررسی API
        if (!$this->api || !$this->api->is_ready()) {
            $this->shortcode_stats['errors']++;
            return '<p style="color: red;">Error: Unable to connect to Google Sheets.</p>';
        }
        
        // دریافت آی‌دی دیسکورد کاربر
        $discord_id = standalone_gsheets()->get_current_user_discord_id();
        
        if (!$discord_id) {
            return '<p style="color: red;">Please login with Discord first.</p>';
        }
        
        $spreadsheet_id = $atts['spreadsheet_id'] ?: $this->get_db_setting('spreadsheet_id');
        
        try {
            if (empty($spreadsheet_id)) {
                throw new Exception('Spreadsheet ID not specified.');
            }
            
            $this->api->set_spreadsheet_id($spreadsheet_id);
            
            $sheets = !empty($atts['sheets']) ? array_map('trim', explode(',', $atts['sheets'])) : [null];
            $fields = !empty($atts['fields']) ? array_map('trim', explode(',', $atts['fields'])) : [];
            $discord_id_column = $atts['discord_id_column'] ?: $this->get_db_setting('discord_id_column', 'Discord ID');
            
            $all_user_data = [];
            
            foreach ($sheets as $sheet_title) {
                $user_data = $this->api->find_user_by_discord_id(
                    $discord_id,
                    $sheet_title,
                    $discord_id_column,
                    $spreadsheet_id
                );
                
                if (!empty($user_data)) {
                    $all_user_data = array_merge($all_user_data, $user_data);
                }
            }
            
            if (empty($all_user_data)) {
                return !empty($atts['default']) ? esc_html($atts['default']) : '<p>No data found for your account.</p>';
            }
            
            // فیلتر کردن فیلدها اگر مشخص شده باشند
            if (!empty($fields)) {
                $filtered_data = [];
                foreach ($all_user_data as $sheet_name => $sheet_data) {
                    $filtered_data[$sheet_name] = [];
                    foreach ($fields as $field) {
                        if (isset($sheet_data[$field])) {
                            $filtered_data[$sheet_name][$field] = $sheet_data[$field];
                        }
                    }
                }
                $all_user_data = $filtered_data;
            }
            
            // فرمت‌بندی خروجی
            return $this->format_user_data_output($all_user_data, $atts['format']);
            
        } catch (Exception $e) {
            $this->shortcode_stats['errors']++;
            return '<p style="color: red;">Error loading data: ' . esc_html($e->getMessage()) . '</p>';
        }
    }
    
    /**
     * شورت‌کد دریافت فیلد کاربر (اصلاح شده برای API v4)
     */
    public function user_field_shortcode($atts) {
        $atts = shortcode_atts([
            'field' => '',
            'spreadsheet_id' => '',
            'sheet' => '',
            'discord_id_column' => '',
            'default' => '',
            'debug' => 'no',
            'cache' => 'yes'
        ], $atts, 'gsheet_user_field');
        
        $this->shortcode_stats['calls']++;
        
        // بررسی وجود پارامتر field
        if (empty($atts['field'])) {
            $this->shortcode_stats['errors']++;
            return !empty($atts['default']) ? $atts['default'] : '';
        }
        
        // بررسی API
        if (!$this->api || !$this->api->is_ready()) {
            $this->shortcode_stats['errors']++;
            return !empty($atts['default']) ? $atts['default'] : '';
        }
        
        // اگر debug فعال باشد
        if ($atts['debug'] === 'yes') {
            return $this->debug_field_value($atts['field'], $atts);
        }
        
        // فراخوانی تابع اصلی دریافت فیلد
        $result = $this->get_field_value_by_variations([$atts['field']], $atts);
        
        // اگر نتیجه خالی بود، مقدار پیش‌فرض را برگردان
        if (empty($result) && !empty($atts['default'])) {
            return $atts['default'];
        }
        
        return $result;
    }
    
    /**
     * تابع debug برای نمایش اطلاعات تشخیص عیوب (بهبود یافته)
     */
    private function debug_field_value($field_name, $atts) {
        if (!current_user_can('manage_options')) {
            return '<p>Debug access for admins only</p>';
        }
        
        $debug_info = '<div style="background: #f0f0f0; padding: 15px; border: 1px solid #ccc; margin: 10px 0; font-family: monospace;">';
        $debug_info .= '<h4>🔍 Debug Info API v4:</h4>';
        
        // نمایش پارامترهای دریافتی
        $debug_info .= '<p><strong>📥 Shortcode Parameters:</strong></p>';
        $debug_info .= '<ul>';
        $debug_info .= '<li><strong>field:</strong> "' . htmlspecialchars($field_name) . '"</li>';
        $debug_info .= '<li><strong>spreadsheet_id:</strong> "' . htmlspecialchars($atts['spreadsheet_id'] ?? '') . '"</li>';
        $debug_info .= '<li><strong>sheet:</strong> "' . htmlspecialchars($atts['sheet'] ?? '') . '"</li>';
        $debug_info .= '<li><strong>discord_id_column:</strong> "' . htmlspecialchars($atts['discord_id_column'] ?? '') . '"</li>';
        $debug_info .= '<li><strong>default:</strong> "' . htmlspecialchars($atts['default'] ?? '') . '"</li>';
        $debug_info .= '</ul>';
        
        // بررسی API
        if (!$this->api || !$this->api->is_ready()) {
            $debug_info .= '<p style="color: red;">❌ API not ready</p>';
            $debug_info .= '</div>';
            return $debug_info;
        }
        
        $debug_info .= '<p style="color: green;">✅ API v4 ready</p>';
        
        // بررسی Discord ID
        $discord_id = standalone_gsheets()->get_current_user_discord_id();
        if (!$discord_id) {
            $debug_info .= '<p style="color: red;">❌ Discord ID not found</p>';
            $debug_info .= '</div>';
            return $debug_info;
        }
        
        $debug_info .= '<p style="color: green;">✅ Discord ID: ' . $discord_id . '</p>';
        
        // نمایش آمار API
        $api_info = $this->api->get_api_info();
        $debug_info .= '<p><strong>🚀 API Info:</strong></p>';
        $debug_info .= '<ul>';
        $debug_info .= '<li>Version: ' . $api_info['version'] . '</li>';
        $debug_info .= '<li>Requests Made: ' . $api_info['performance_stats']['requests_made'] . '</li>';
        $debug_info .= '<li>Batch Requests: ' . $api_info['performance_stats']['batch_requests'] . '</li>';
        $debug_info .= '<li>Cache Hits: ' . $api_info['performance_stats']['cache_hits'] . '</li>';
        $debug_info .= '<li>Rate Limit Remaining: ' . $api_info['rate_limiting']['remaining'] . '</li>';
        $debug_info .= '</ul>';
        
        // بررسی تنظیمات
        $db_settings = get_option('standalone_gsheets_settings', []);
        $spreadsheet_id = $atts['spreadsheet_id'] ?: ($db_settings['spreadsheet_id'] ?? '');
        
        if (empty($spreadsheet_id)) {
            $debug_info .= '<p style="color: red;">❌ Spreadsheet ID not set</p>';
            $debug_info .= '</div>';
            return $debug_info;
        }
        
        $debug_info .= '<p style="color: green;">✅ Spreadsheet ID: ' . substr($spreadsheet_id, 0, 10) . '...</p>';
        
        // تلاش برای دریافت داده‌ها
        try {
            $this->api->set_spreadsheet_id($spreadsheet_id);
            $discord_id_column = $atts['discord_id_column'] ?: ($db_settings['discord_id_column'] ?? 'Discord ID');
            $sheet_title = $atts['sheet'] ?: null;
            
            $debug_info .= '<p><strong>🎯 Searching with parameters:</strong></p>';
            $debug_info .= '<ul>';
            $debug_info .= '<li>Discord ID: ' . $discord_id . '</li>';
            $debug_info .= '<li>Sheet: ' . ($sheet_title ?: 'All sheets') . '</li>';
            $debug_info .= '<li>Discord ID Column: ' . $discord_id_column . '</li>';
            $debug_info .= '<li>Field: ' . $field_name . '</li>';
            $debug_info .= '</ul>';
            
            $start_time = microtime(true);
            $user_data = $this->api->find_user_by_discord_id(
                $discord_id,
                $sheet_title,
                $discord_id_column,
                $spreadsheet_id
            );
            $execution_time = round((microtime(true) - $start_time) * 1000, 2);
            
            $debug_info .= '<p><strong>⏱️ Execution time:</strong> ' . $execution_time . ' ms</p>';
            
            if (empty($user_data)) {
                $debug_info .= '<p style="color: red;">❌ User data not found</p>';
            } else {
                $debug_info .= '<p style="color: green;">✅ User data found</p>';
                $debug_info .= '<p><strong>📋 Available fields:</strong></p>';
                foreach ($user_data as $sheet_name => $sheet_data) {
                    $fields = array_keys($sheet_data);
                    $filtered_fields = array_filter($fields, function($f) { 
                        return !in_array($f, ['_row_index', '_sheet_title', '_last_updated']); 
                    });
                    $debug_info .= '<p><strong>' . $sheet_name . ':</strong> ' . implode(', ', $filtered_fields) . '</p>';
                }
                
                // بررسی وجود فیلد مورد نظر
                $field_found = false;
                $field_value = '';
                foreach ($user_data as $sheet_data) {
                    if (isset($sheet_data[$field_name])) {
                        $field_found = true;
                        $field_value = $sheet_data[$field_name];
                        break;
                    }
                }
                
                if ($field_found) {
                    $debug_info .= '<p style="color: green;">✅ Field "' . $field_name . '" found: <strong>' . htmlspecialchars($field_value) . '</strong></p>';
                } else {
                    $debug_info .= '<p style="color: red;">❌ Field "' . $field_name . '" not found</p>';
                    
                    // تلاش با Case-insensitive search
                    $debug_info .= '<p><strong>🔄 Trying Case-insensitive search:</strong></p>';
                    foreach ($user_data as $sheet_name => $sheet_data) {
                        foreach ($sheet_data as $key => $value) {
                            if (strtolower($key) === strtolower($field_name)) {
                                $debug_info .= '<p style="color: green;">✅ Field found with name "' . $key . '": <strong>' . htmlspecialchars($value) . '</strong></p>';
                                $field_found = true;
                                break 2;
                            }
                        }
                    }
                    
                    if (!$field_found) {
                        $debug_info .= '<p style="color: red;">❌ Not found even with Case-insensitive search</p>';
                    }
                }
            }
        } catch (Exception $e) {
            $debug_info .= '<p style="color: red;">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        
        $debug_info .= '</div>';
        return $debug_info;
    }
    
    /**
     * دریافت مقدار فیلد با تغییرات مختلف نام (اصلاح شده برای API v4)
     */
    private function get_field_value_by_variations($field_variations, $atts = []) {
        $this->shortcode_stats['calls']++;
        
        // بررسی API
        if (!$this->api || !$this->api->is_ready()) {
            $this->shortcode_stats['errors']++;
            return $atts['default'] ?? '';
        }
        
        // دریافت Discord ID کاربر
        $discord_id = standalone_gsheets()->get_current_user_discord_id();
        if (!$discord_id) {
            return $atts['default'] ?? '';
        }
        
        // تنظیم آی‌دی اسپردشیت
        $spreadsheet_id = $atts['spreadsheet_id'] ?? $this->get_db_setting('spreadsheet_id');
        if (empty($spreadsheet_id)) {
            $this->shortcode_stats['errors']++;
            return $atts['default'] ?? '';
        }
        
        // بررسی کش اگر فعال باشد
        $use_cache = ($atts['cache'] ?? 'yes') === 'yes';
        $cache_key = null;
        
        if ($use_cache) {
            $cache_key = 'gsheet_field_' . md5($spreadsheet_id . '_' . $discord_id . '_' . implode('_', $field_variations));
            $cached_value = get_transient($cache_key);
            
            if ($cached_value !== false) {
                $this->shortcode_stats['cache_hits']++;
                return $cached_value ?: ($atts['default'] ?? '');
            }
        }
        
        try {
            $this->api->set_spreadsheet_id($spreadsheet_id);
            $sheet_title = $atts['sheet'] ?? null;
            $discord_id_column = $atts['discord_id_column'] ?? $this->get_db_setting('discord_id_column', 'Discord ID');
            
            $user_data = $this->api->find_user_by_discord_id(
                $discord_id,
                $sheet_title,
                $discord_id_column,
                $spreadsheet_id
            );
            
            if (empty($user_data)) {
                $result = $atts['default'] ?? '';
                if ($use_cache) {
                    set_transient($cache_key, '', 60);
                }
                return $result;
            }
            
            // جستجو با تمام تغییرات نام فیلد (ابتدا exact match)
            foreach ($user_data as $sheet_data) {
                foreach ($field_variations as $field_name) {
                    if (isset($sheet_data[$field_name])) {
                        $result = $this->sanitize_field_value($sheet_data[$field_name]);
                        if ($use_cache) {
                            set_transient($cache_key, $result, $this->get_db_setting('cache_time', 300));
                        }
                        return $result;
                    }
                }
            }
            
            // جستجو بدون حساسیت به بزرگی و کوچکی حروف
            foreach ($user_data as $sheet_data) {
                foreach ($sheet_data as $key => $value) {
                    foreach ($field_variations as $field_name) {
                        if (strtolower($key) === strtolower($field_name)) {
                            $result = $this->sanitize_field_value($value);
                            if ($use_cache) {
                                set_transient($cache_key, $result, $this->get_db_setting('cache_time', 300));
                            }
                            return $result;
                        }
                    }
                }
            }
            
            // جستجو با partial match
            foreach ($user_data as $sheet_data) {
                foreach ($sheet_data as $key => $value) {
                    foreach ($field_variations as $field_name) {
                        if (stripos($key, $field_name) !== false || stripos($field_name, $key) !== false) {
                            $result = $this->sanitize_field_value($value);
                            if ($use_cache) {
                                set_transient($cache_key, $result, $this->get_db_setting('cache_time', 300));
                            }
                            return $result;
                        }
                    }
                }
            }
            
            $result = $atts['default'] ?? '';
            if ($use_cache) {
                set_transient($cache_key, '', 60);
            }
            return $result;
            
        } catch (Exception $e) {
            $this->shortcode_stats['errors']++;
            return $atts['default'] ?? '';
        }
    }
    
    /**
     * پاکسازی مقدار فیلد
     */
    private function sanitize_field_value($value) {
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
     * شورت‌کد نمایش داده‌های کاربر (بهبود یافته برای API v4)
     */
    public function user_data_shortcode($atts) {
        $atts = shortcode_atts([
            'spreadsheet_id' => '',
            'sheet' => '',
            'field' => '',
            'discord_id_column' => '',
            'label' => '',
            'format' => 'text',
            'raw' => 'no',
            'default' => '',
            'cache' => 'yes'
        ], $atts, 'gsheet_user_data');
        
        $this->shortcode_stats['calls']++;
        
        // بررسی API
        if (!$this->api || !$this->api->is_ready()) {
            $this->shortcode_stats['errors']++;
            return '<p style="color: red;">Error: Unable to connect to Google Sheets.</p>';
        }
        
        // دریافت آی‌دی دیسکورد کاربر
        $discord_id = standalone_gsheets()->get_current_user_discord_id();
        
        if (!$discord_id) {
            return '<p style="color: red;">Please login with Discord first.</p>';
        }
        
        $spreadsheet_id = $atts['spreadsheet_id'] ?: $this->get_db_setting('spreadsheet_id');
        
        try {
            if (empty($spreadsheet_id)) {
                throw new Exception('Spreadsheet ID not specified.');
            }
            
            $this->api->set_spreadsheet_id($spreadsheet_id);
            
            $sheet_title = $atts['sheet'] ?: null;
            $discord_id_column = $atts['discord_id_column'] ?: $this->get_db_setting('discord_id_column', 'Discord ID');
            
            $user_data = $this->api->find_user_by_discord_id(
                $discord_id,
                $sheet_title,
                $discord_id_column,
                $spreadsheet_id
            );
            
            if (empty($user_data)) {
                return !empty($atts['default']) ? esc_html($atts['default']) : '<p>No data found for your account.</p>';
            }
            
            // اگر field مشخص شده باشد، از تابع بهبود یافته استفاده کن
            if (!empty($atts['field'])) {
                $value = $this->get_field_value($atts['field'], $atts);
                
                if (empty($value) && !empty($atts['default'])) {
                    $value = $atts['default'];
                }
                
                if ($atts['raw'] === 'yes') {
                    return $value;
                }
                
                $label = !empty($atts['label']) ? $atts['label'] : $atts['field'];
                return $this->format_single_field_output($label, $value, $atts['format']);
            }
            
            // نمایش همه اطلاعات
            return $this->format_user_data_output($user_data, $atts['format']);
            
        } catch (Exception $e) {
            $this->shortcode_stats['errors']++;
            return '<p style="color: red;">Error loading data: ' . esc_html($e->getMessage()) . '</p>';
        }
    }
    
    /**
     * شورت‌کد نمایش مقدار سلول (بهبود یافته)
     */
    public function cell_data_shortcode($atts) {
        $atts = shortcode_atts([
            'spreadsheet_id' => '',
            'sheet' => '',
            'cell' => '',
            'label' => '',
            'format' => 'text',
            'raw' => 'no',
            'default' => '',
            'cache' => 'yes'
        ], $atts, 'gsheet_cell');
        
        $this->shortcode_stats['calls']++;
        
        // بررسی API
        if (!$this->api || !$this->api->is_ready()) {
            $this->shortcode_stats['errors']++;
            return '<p style="color: red;">Error: Unable to connect to Google Sheets.</p>';
        }
        
        if (empty($atts['sheet']) || empty($atts['cell'])) {
            $this->shortcode_stats['errors']++;
            return '<p style="color: red;">Sheet name and cell reference are required.</p>';
        }
        
        $spreadsheet_id = $atts['spreadsheet_id'] ?: $this->get_db_setting('spreadsheet_id');
        
        try {
            if (empty($spreadsheet_id)) {
                throw new Exception('Spreadsheet ID not specified.');
            }
            
            $this->api->set_spreadsheet_id($spreadsheet_id);
            
            $value = $this->api->get_cell_value($atts['sheet'], $atts['cell'], $spreadsheet_id);
            
            if ($value === null) {
                return !empty($atts['default']) ? esc_html($atts['default']) : '<p>No value found in specified cell.</p>';
            }
            
            if ($atts['raw'] === 'yes') {
                return esc_html($value);
            }
            
            $label = !empty($atts['label']) ? $atts['label'] : '';
            
            return $this->format_single_field_output($label, $value, $atts['format']);
            
        } catch (Exception $e) {
            $this->shortcode_stats['errors']++;
            return '<p style="color: red;">Error loading data: ' . esc_html($e->getMessage()) . '</p>';
        }
    }
    
    /**
     * دریافت مقدار فیلد (اصلاح شده و بهبود یافته برای case-insensitive)
     */
    private function get_field_value($field_name, $atts = []) {
        $this->shortcode_stats['calls']++;
        
        // بررسی API
        if (!$this->api || !$this->api->is_ready()) {
            $this->shortcode_stats['errors']++;
            return $atts['default'] ?? '';
        }
        
        // دریافت Discord ID کاربر
        $discord_id = standalone_gsheets()->get_current_user_discord_id();
        if (!$discord_id) {
            return $atts['default'] ?? '';
        }
        
        // تنظیم آی‌دی اسپردشیت
        $spreadsheet_id = $atts['spreadsheet_id'] ?? $this->get_db_setting('spreadsheet_id');
        if (empty($spreadsheet_id)) {
            $this->shortcode_stats['errors']++;
            return $atts['default'] ?? '';
        }
        
        // بررسی کش اگر فعال باشد
        $use_cache = ($atts['cache'] ?? 'yes') === 'yes';
        $cache_key = null;
        
        if ($use_cache) {
            $cache_key = 'gsheet_field_single_' . md5($spreadsheet_id . '_' . $discord_id . '_' . $field_name);
            $cached_value = get_transient($cache_key);
            
            if ($cached_value !== false) {
                $this->shortcode_stats['cache_hits']++;
                return $cached_value ?: ($atts['default'] ?? '');
            }
        }
        
        try {
            $this->api->set_spreadsheet_id($spreadsheet_id);
            $sheet_title = $atts['sheet'] ?? null;
            $discord_id_column = $atts['discord_id_column'] ?? $this->get_db_setting('discord_id_column', 'Discord ID');
            
            $user_data = $this->api->find_user_by_discord_id(
                $discord_id,
                $sheet_title,
                $discord_id_column,
                $spreadsheet_id
            );
            
            if (empty($user_data)) {
                $result = $atts['default'] ?? '';
                if ($use_cache) {
                    set_transient($cache_key, '', 60);
                }
                return $result;
            }
            
            // مرحله 1: جستجو با نام دقیق فیلد (exact match)
            foreach ($user_data as $sheet_data) {
                if (isset($sheet_data[$field_name])) {
                    $result = $this->sanitize_field_value($sheet_data[$field_name]);
                    if ($use_cache) {
                        set_transient($cache_key, $result, $this->get_db_setting('cache_time', 300));
                    }
                    return $result;
                }
            }
            
            // مرحله 2: جستجو بدون حساسیت به بزرگی و کوچکی حروف (case-insensitive)
            $field_name_lower = strtolower(trim($field_name));
            
            foreach ($user_data as $sheet_data) {
                foreach ($sheet_data as $key => $value) {
                    $key_lower = strtolower(trim($key));
                    
                    if ($key_lower === $field_name_lower) {
                        $result = $this->sanitize_field_value($value);
                        if ($use_cache) {
                            set_transient($cache_key, $result, $this->get_db_setting('cache_time', 300));
                        }
                        return $result;
                    }
                }
            }
            
            // مرحله 3: جستجو با تغییرات مختلف نام فیلد
            $field_variations = $this->generate_field_variations($field_name);
            
            foreach ($user_data as $sheet_data) {
                foreach ($field_variations as $variation) {
                    if (isset($sheet_data[$variation])) {
                        $result = $this->sanitize_field_value($sheet_data[$variation]);
                        if ($use_cache) {
                            set_transient($cache_key, $result, $this->get_db_setting('cache_time', 300));
                        }
                        return $result;
                    }
                }
            }
            
            // مرحله 4: جستجو با case-insensitive برای تغییرات نام فیلد
            foreach ($user_data as $sheet_data) {
                foreach ($sheet_data as $key => $value) {
                    $key_lower = strtolower(trim($key));
                    
                    foreach ($field_variations as $variation) {
                        $variation_lower = strtolower(trim($variation));
                        
                        if ($key_lower === $variation_lower) {
                            $result = $this->sanitize_field_value($value);
                            if ($use_cache) {
                                set_transient($cache_key, $result, $this->get_db_setting('cache_time', 300));
                            }
                            return $result;
                        }
                    }
                }
            }
            
            // مرحله 5: جستجو با partial matching (تطبیق جزئی)
            foreach ($user_data as $sheet_data) {
                foreach ($sheet_data as $key => $value) {
                    $key_lower = strtolower(trim($key));
                    
                    // چک کردن اگر فیلد مورد نظر یا تغییرات آن شامل کلید موجود باشد یا برعکس
                    if (stripos($key_lower, $field_name_lower) !== false || 
                        stripos($field_name_lower, $key_lower) !== false) {
                        $result = $this->sanitize_field_value($value);
                        if ($use_cache) {
                            set_transient($cache_key, $result, $this->get_db_setting('cache_time', 300));
                        }
                        return $result;
                    }
                    
                    // چک کردن تغییرات فیلد با partial matching
                    foreach ($field_variations as $variation) {
                        $variation_lower = strtolower(trim($variation));
                        
                        if (stripos($key_lower, $variation_lower) !== false || 
                            stripos($variation_lower, $key_lower) !== false) {
                            $result = $this->sanitize_field_value($value);
                            if ($use_cache) {
                                set_transient($cache_key, $result, $this->get_db_setting('cache_time', 300));
                            }
                            return $result;
                        }
                    }
                }
            }
            
            // اگر هیچ مطابقتی یافت نشد، مقدار پیش‌فرض را برگردان
            $result = $atts['default'] ?? '';
            if ($use_cache) {
                set_transient($cache_key, '', 60);
            }
            return $result;
            
        } catch (Exception $e) {
            $this->shortcode_stats['errors']++;
            return $atts['default'] ?? '';
        }
    }
    
    /**
     * فرمت‌بندی خروجی برای یک فیلد
     */
    private function format_single_field_output($label, $value, $format) {
        if ($format == 'table') {
            return '<table class="gsheet-cell-data">
                <tr>
                    ' . (!empty($label) ? '<th>' . esc_html($label) . '</th>' : '') . '
                    <td>' . esc_html($value) . '</td>
                </tr>
            </table>';
        } else {
            return '<p class="gsheet-cell-data">' . 
                   (!empty($label) ? '<strong>' . esc_html($label) . ':</strong> ' : '') . 
                   esc_html($value) . '</p>';
        }
    }
    
    /**
     * فرمت‌بندی خروجی برای داده‌های کاربر
     */
    private function format_user_data_output($user_data, $format) {
        if ($format === 'json') {
            return '<pre class="gsheet-json-output">' . 
                   json_encode($user_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . 
                   '</pre>';
        }
        
        if ($format === 'list') {
            $html = '<div class="gsheet-user-data-list">';
            foreach ($user_data as $sheet_name => $sheet_data) {
                foreach ($sheet_data as $field => $value) {
                    if (!in_array($field, ['_row_index', '_sheet_title', '_last_updated'])) {
                        $html .= '<div class="gsheet-field-item">';
                        $html .= '<span class="field-name">' . esc_html($field) . ':</span> ';
                        $html .= '<span class="field-value">' . esc_html($value) . '</span>';
                        $html .= '</div>';
                    }
                }
            }
            $html .= '</div>';
            return $html;
        }
        
        // فرمت پیش‌فرض: table
        if (count($user_data) === 1) {
            // یک شیت
            $sheet_data = reset($user_data);
            $html = '<table class="gsheet-user-data">';
            foreach ($sheet_data as $field => $value) {
                if (!in_array($field, ['_row_index', '_sheet_title', '_last_updated'])) {
                    $html .= '<tr>
                        <th>' . esc_html($field) . '</th>
                        <td>' . esc_html($value) . '</td>
                    </tr>';
                }
            }
            $html .= '</table>';
            return $html;
        } else {
            // چند شیت
            $html = '<div class="gsheet-user-data-summary">';
            foreach ($user_data as $sheet_name => $sheet_data) {
                $html .= '<h3>' . esc_html($sheet_name) . '</h3>';
                $html .= '<table class="gsheet-user-data">';
                foreach ($sheet_data as $field => $value) {
                    if (!in_array($field, ['_row_index', '_sheet_title', '_last_updated'])) {
                        $html .= '<tr>
                            <th>' . esc_html($field) . '</th>
                            <td>' . esc_html($value) . '</td>
                        </tr>';
                    }
                }
                $html .= '</table>';
            }
            $html .= '</div>';
            return $html;
        }
    }
    
    /**
     * دریافت آمار shortcode ها
     */
    public function get_stats() {
        return $this->shortcode_stats;
    }
    
    /**
     * ری‌ست آمار
     */
    public function reset_stats() {
        $this->shortcode_stats = [
            'calls' => 0,
            'cache_hits' => 0,
            'errors' => 0
        ];
    }
}