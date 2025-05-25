<?php
if (!defined('ABSPATH')) exit;

class Standalone_GSheets_Table_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'gsheets_table';
    }

    public function get_title() {
        return __('Google Sheets Table', 'standalone-gsheets');
    }

    public function get_icon() {
        return 'eicon-table';
    }

    public function get_categories() {
        return ['google-sheets'];
    }

    public function get_keywords() {
        return ['google', 'sheets', 'table', 'data', 'gsheets'];
    }

    // استفاده از متد جدید به جای _register_controls
    protected function register_controls() {
        $this->register_content_controls();
        $this->register_style_controls();
    }

    protected function register_content_controls() {
        // Data Section
        $this->start_controls_section(
            'section_data',
            [
                'label' => __('Data Settings', 'standalone-gsheets'),
            ]
        );

        $this->add_control(
            'data_source',
            [
                'label' => __('Data Source', 'standalone-gsheets'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'user' => __('Current User Data', 'standalone-gsheets'),
                    'sheet' => __('Full Sheet Data', 'standalone-gsheets'),
                ],
                'default' => 'user',
            ]
        );

        $this->add_control(
            'spreadsheet_id',
            [
                'label' => __('Spreadsheet ID', 'standalone-gsheets'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => __('Leave empty for default', 'standalone-gsheets'),
                'label_block' => true,
            ]
        );

        $this->add_control(
            'sheet_name',
            [
                'label' => __('Sheet Name', 'standalone-gsheets'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => 'Sheet1',
                'description' => __('Leave empty to search all sheets', 'standalone-gsheets'),
            ]
        );

        $this->add_control(
            'discord_id_column',
            [
                'label' => __('Discord ID Column', 'standalone-gsheets'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'Discord ID',
                'condition' => [
                    'data_source' => 'user',
                ],
            ]
        );

        // Column Selection
        $this->add_control(
            'show_all_columns',
            [
                'label' => __('Show All Columns', 'standalone-gsheets'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        // Column Repeater
        $repeater = new \Elementor\Repeater();

        $repeater->add_control(
            'field_name',
            [
                'label' => __('Field Name', 'standalone-gsheets'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => __('Column name in sheet', 'standalone-gsheets'),
            ]
        );

        $repeater->add_control(
            'column_title',
            [
                'label' => __('Display Title', 'standalone-gsheets'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => __('Leave empty to use field name', 'standalone-gsheets'),
            ]
        );

        $repeater->add_control(
            'text_align',
            [
                'label' => __('Text Align', 'standalone-gsheets'),
                'type' => \Elementor\Controls_Manager::CHOOSE,
                'options' => [
                    'left' => [
                        'title' => __('Left', 'standalone-gsheets'),
                        'icon' => 'eicon-text-align-left',
                    ],
                    'center' => [
                        'title' => __('Center', 'standalone-gsheets'),
                        'icon' => 'eicon-text-align-center',
                    ],
                    'right' => [
                        'title' => __('Right', 'standalone-gsheets'),
                        'icon' => 'eicon-text-align-right',
                    ],
                ],
                'default' => 'left',
            ]
        );

        $this->add_control(
            'columns',
            [
                'label' => __('Table Columns', 'standalone-gsheets'),
                'type' => \Elementor\Controls_Manager::REPEATER,
                'fields' => $repeater->get_controls(),
                'title_field' => '{{{ column_title || field_name }}}',
                'prevent_empty' => false,
                'condition' => [
                    'show_all_columns' => '',
                ],
            ]
        );

        $this->add_control(
            'empty_message',
            [
                'label' => __('Empty Table Message', 'standalone-gsheets'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('No data found', 'standalone-gsheets'),
                'separator' => 'before',
            ]
        );

        $this->end_controls_section();

        // Table Options
        $this->start_controls_section(
            'section_table_options',
            [
                'label' => __('Table Options', 'standalone-gsheets'),
            ]
        );

        $this->add_control(
            'show_header',
            [
                'label' => __('Show Header', 'standalone-gsheets'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'striped_rows',
            [
                'label' => __('Striped Rows', 'standalone-gsheets'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'hover_effect',
            [
                'label' => __('Hover Effect', 'standalone-gsheets'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'bordered',
            [
                'label' => __('Bordered', 'standalone-gsheets'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->end_controls_section();
    }

    protected function register_style_controls() {
        // Table Style
        $this->start_controls_section(
            'section_table_style',
            [
                'label' => __('Table Style', 'standalone-gsheets'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'table_typography',
                'selector' => '{{WRAPPER}} .gsheets-elementor-table',
            ]
        );

        $this->add_control(
            'table_bg_color',
            [
                'label' => __('Background Color', 'standalone-gsheets'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gsheets-elementor-table' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'table_border',
                'selector' => '{{WRAPPER}} .gsheets-elementor-table',
            ]
        );

        $this->add_responsive_control(
            'table_border_radius',
            [
                'label' => __('Border Radius', 'standalone-gsheets'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .gsheets-elementor-table' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                    '{{WRAPPER}} .gsheets-elementor-table' => 'overflow: hidden;',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'table_box_shadow',
                'selector' => '{{WRAPPER}} .gsheets-elementor-table',
            ]
        );

        $this->end_controls_section();

        // Header Style
        $this->start_controls_section(
            'section_header_style',
            [
                'label' => __('Header Style', 'standalone-gsheets'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_header' => 'yes',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'header_typography',
                'selector' => '{{WRAPPER}} .gsheets-elementor-table th',
            ]
        );

        $this->add_control(
            'header_text_color',
            [
                'label' => __('Text Color', 'standalone-gsheets'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gsheets-elementor-table th' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'header_bg_color',
            [
                'label' => __('Background Color', 'standalone-gsheets'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gsheets-elementor-table th' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'header_padding',
            [
                'label' => __('Padding', 'standalone-gsheets'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .gsheets-elementor-table th' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Cell Style
        $this->start_controls_section(
            'section_cell_style',
            [
                'label' => __('Cell Style', 'standalone-gsheets'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'cell_typography',
                'selector' => '{{WRAPPER}} .gsheets-elementor-table td',
            ]
        );

        $this->add_control(
            'cell_text_color',
            [
                'label' => __('Text Color', 'standalone-gsheets'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gsheets-elementor-table td' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'cell_bg_color',
            [
                'label' => __('Background Color', 'standalone-gsheets'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gsheets-elementor-table td' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'striped_bg_color',
            [
                'label' => __('Striped Row Color', 'standalone-gsheets'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gsheets-elementor-table.striped tr:nth-child(even) td' => 'background-color: {{VALUE}};',
                ],
                'condition' => [
                    'striped_rows' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'hover_bg_color',
            [
                'label' => __('Hover Background Color', 'standalone-gsheets'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gsheets-elementor-table.hover-effect tr:hover td' => 'background-color: {{VALUE}};',
                ],
                'condition' => [
                    'hover_effect' => 'yes',
                ],
            ]
        );

        $this->add_responsive_control(
            'cell_padding',
            [
                'label' => __('Padding', 'standalone-gsheets'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .gsheets-elementor-table td' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'cell_border',
                'selector' => '{{WRAPPER}} .gsheets-elementor-table td',
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        
        // اضافه کردن کلاس‌ها بر اساس تنظیمات
        $table_classes = ['gsheets-elementor-table'];
        
        if ($settings['striped_rows'] === 'yes') {
            $table_classes[] = 'striped';
        }
        
        if ($settings['hover_effect'] === 'yes') {
            $table_classes[] = 'hover-effect';
        }
        
        if ($settings['bordered'] === 'yes') {
            $table_classes[] = 'bordered';
        }
        
        // Build shortcode based on data source
        if ($settings['data_source'] === 'user') {
            // چک کردن اینکه آیا باید ستون‌ها رو فیلتر کنیم
            $show_all_columns = $settings['show_all_columns'] === 'yes';
            $selected_columns = [];
            
            if (!$show_all_columns && !empty($settings['columns'])) {
                foreach ($settings['columns'] as $column) {
                    if (!empty($column['field_name'])) {
                        $selected_columns[] = [
                            'field' => $column['field_name'],
                            'title' => !empty($column['column_title']) ? $column['column_title'] : $column['field_name'],
                            'align' => $column['text_align'] ?? 'left'
                        ];
                    }
                }
            }
            
            // اگر ستون‌های خاصی انتخاب شده، از روش دستی استفاده کن
            if (!empty($selected_columns)) {
                echo '<div class="gsheets-table-wrapper">';
                
                // دریافت داده‌های کاربر
                $plugin = standalone_gsheets();
                if (!$plugin || !$plugin->api || !$plugin->api->is_ready()) {
                    echo '<div class="gsheets-table-empty">' . __('API not ready', 'standalone-gsheets') . '</div>';
                    echo '</div>';
                    return;
                }
                
                $discord_id = $plugin->get_current_user_discord_id();
                if (!$discord_id) {
                    echo '<div class="gsheets-table-empty">' . __('Please login with Discord', 'standalone-gsheets') . '</div>';
                    echo '</div>';
                    return;
                }
                
                $spreadsheet_id = !empty($settings['spreadsheet_id']) ? $settings['spreadsheet_id'] : $plugin->get_setting('spreadsheet_id');
                $sheet_name = $settings['sheet_name'] ?? '';
                $discord_id_column = $settings['discord_id_column'] ?? 'Discord ID';
                
                try {
                    $plugin->api->set_spreadsheet_id($spreadsheet_id);
                    $user_data = $plugin->api->find_user_by_discord_id(
                        $discord_id,
                        $sheet_name ?: null,
                        $discord_id_column,
                        $spreadsheet_id
                    );
                    
                    if (empty($user_data)) {
                        echo '<div class="gsheets-table-empty">' . esc_html($settings['empty_message']) . '</div>';
                        echo '</div>';
                        return;
                    }
                    
                    // ساخت جدول دستی با ستون‌های انتخابی
                    echo '<table class="' . esc_attr(implode(' ', $table_classes)) . '">';
                    
                    // Header
                    if ($settings['show_header'] === 'yes') {
                        echo '<thead><tr>';
                        foreach ($selected_columns as $column) {
                            echo '<th style="text-align: ' . esc_attr($column['align']) . '">' . 
                                 esc_html($column['title']) . '</th>';
                        }
                        echo '</tr></thead>';
                    }
                    
                    // Body
                    echo '<tbody>';
                    foreach ($user_data as $sheet_data) {
                        echo '<tr>';
                        foreach ($selected_columns as $column) {
                            $value = '';
                            
                            // جستجوی case-insensitive
                            foreach ($sheet_data as $key => $val) {
                                if (strcasecmp($key, $column['field']) === 0) {
                                    $value = $val;
                                    break;
                                }
                            }
                            
                            echo '<td style="text-align: ' . esc_attr($column['align']) . '">' . 
                                 esc_html($value) . '</td>';
                        }
                        echo '</tr>';
                    }
                    echo '</tbody>';
                    
                    echo '</table>';
                } catch (Exception $e) {
                    echo '<div class="gsheets-table-empty">' . __('Error loading data', 'standalone-gsheets') . '</div>';
                }
                
                echo '</div>';
            } else {
                // نمایش همه ستون‌ها - روش قبلی
                $shortcode = '[gsheet_user_data';
                
                if (!empty($settings['spreadsheet_id'])) {
                    $shortcode .= ' spreadsheet_id="' . esc_attr($settings['spreadsheet_id']) . '"';
                }
                
                if (!empty($settings['sheet_name'])) {
                    $shortcode .= ' sheet="' . esc_attr($settings['sheet_name']) . '"';
                }
                
                if (!empty($settings['discord_id_column'])) {
                    $shortcode .= ' discord_id_column="' . esc_attr($settings['discord_id_column']) . '"';
                }
                
                $shortcode .= ' format="table"';
                $shortcode .= ']';
                
                // Render
                echo '<div class="gsheets-table-wrapper">';
                
                // اضافه کردن کلاس‌ها به جدول
                ob_start();
                echo do_shortcode($shortcode);
                $table_html = ob_get_clean();
                
                // اضافه کردن کلاس‌ها به جدول
                if (!empty($table_html)) {
                    $table_html = str_replace(
                        'class="gsheet-user-data"',
                        'class="' . esc_attr(implode(' ', $table_classes)) . ' gsheet-user-data"',
                        $table_html
                    );
                    echo $table_html;
                } else {
                    echo '<div class="gsheets-table-empty">' . esc_html($settings['empty_message']) . '</div>';
                }
                
                echo '</div>';
            }
        } else {
            // Full sheet data - برای آینده
            echo '<div class="gsheets-table-wrapper">';
            echo '<p>' . __('Full sheet display coming soon...', 'standalone-gsheets') . '</p>';
            echo '</div>';
        }
    }

    protected function content_template() {
        ?>
        <#
        var tableClasses = ['gsheets-elementor-table'];
        
        if (settings.striped_rows === 'yes') {
            tableClasses.push('striped');
        }
        
        if (settings.hover_effect === 'yes') {
            tableClasses.push('hover-effect');
        }
        
        if (settings.bordered === 'yes') {
            tableClasses.push('bordered');
        }
        #>
        <div class="gsheets-table-wrapper">
            <div class="gsheets-table-preview" style="padding: 40px; background: #f8f9fa; text-align: center; border-radius: 4px;">
                <i class="eicon-table" style="font-size: 48px; color: #6c757d; display: block; margin-bottom: 10px;"></i>
                <# if (settings.data_source === 'user') { #>
                    <p><?php echo __('User data table will be displayed here', 'standalone-gsheets'); ?></p>
                <# } else { #>
                    <p><?php echo __('Sheet data table will be displayed here', 'standalone-gsheets'); ?></p>
                <# } #>
            </div>
        </div>
        <?php
    }
}