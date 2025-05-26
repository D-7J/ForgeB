<?php
if (!defined('ABSPATH')) exit;

class Standalone_GSheets_Field_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'gsheets_field';
    }

    public function get_title() {
        return __('Google Sheets Field', 'standalone-gsheets');
    }

    public function get_icon() {
        return 'eicon-text-field';
    }

    public function get_categories() {
        return ['google-sheets'];
    }

    public function get_keywords() {
        return ['google', 'sheets', 'field', 'data', 'gsheets'];
    }

    // استفاده از متد جدید به جای _register_controls
    protected function register_controls() {
        $this->register_content_controls();
        $this->register_style_controls();
    }

    protected function register_content_controls() {
        // Content Section
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Content', 'standalone-gsheets'),
            ]
        );

        $this->add_control(
            'field_name',
            [
                'label' => __('Field Name', 'standalone-gsheets'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => 'Character Name',
                'description' => __('Enter the field name from your Google Sheet', 'standalone-gsheets'),
                'label_block' => true,
            ]
        );

        $this->add_control(
            'prefix',
            [
                'label' => __('Prefix', 'standalone-gsheets'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => __('e.g. Name:', 'standalone-gsheets'),
            ]
        );

        $this->add_control(
            'suffix',
            [
                'label' => __('Suffix', 'standalone-gsheets'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => __('e.g. points', 'standalone-gsheets'),
            ]
        );

        $this->add_control(
            'default_value',
            [
                'label' => __('Default Value', 'standalone-gsheets'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => __('Not Found', 'standalone-gsheets'),
                'description' => __('Value to show if field is empty', 'standalone-gsheets'),
            ]
        );

        $this->add_control(
            'html_tag',
            [
                'label' => __('HTML Tag', 'standalone-gsheets'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'div' => 'DIV',
                    'span' => 'SPAN',
                    'p' => 'P',
                    'h1' => 'H1',
                    'h2' => 'H2',
                    'h3' => 'H3',
                    'h4' => 'H4',
                    'h5' => 'H5',
                    'h6' => 'H6',
                ],
                'default' => 'div',
            ]
        );

        // Advanced settings
        $this->add_control(
            'spreadsheet_id',
            [
                'label' => __('Spreadsheet ID (Optional)', 'standalone-gsheets'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => __('Leave empty for default', 'standalone-gsheets'),
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'sheet_name',
            [
                'label' => __('Sheet Name (Optional)', 'standalone-gsheets'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => __('Leave empty for all sheets', 'standalone-gsheets'),
            ]
        );

        $this->end_controls_section();
    }

    protected function register_style_controls() {
        // Style Section
        $this->start_controls_section(
            'section_style',
            [
                'label' => __('Style', 'standalone-gsheets'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'typography',
                'selector' => '{{WRAPPER}} .gsheets-field-value',
            ]
        );

        $this->add_control(
            'text_color',
            [
                'label' => __('Text Color', 'standalone-gsheets'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gsheets-field-value' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'text_align',
            [
                'label' => __('Alignment', 'standalone-gsheets'),
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
                'selectors' => [
                    '{{WRAPPER}} .gsheets-field-wrapper' => 'text-align: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Text_Shadow::get_type(),
            [
                'name' => 'text_shadow',
                'selector' => '{{WRAPPER}} .gsheets-field-value',
            ]
        );

        $this->add_responsive_control(
            'padding',
            [
                'label' => __('Padding', 'standalone-gsheets'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .gsheets-field-wrapper' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name' => 'background',
                'types' => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} .gsheets-field-wrapper',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'border',
                'selector' => '{{WRAPPER}} .gsheets-field-wrapper',
            ]
        );

        $this->add_responsive_control(
            'border_radius',
            [
                'label' => __('Border Radius', 'standalone-gsheets'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .gsheets-field-wrapper' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'box_shadow',
                'selector' => '{{WRAPPER}} .gsheets-field-wrapper',
            ]
        );

        $this->end_controls_section();

        // Prefix/Suffix Style
        $this->start_controls_section(
            'section_prefix_suffix_style',
            [
                'label' => __('Prefix & Suffix', 'standalone-gsheets'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        // Heading برای Prefix
        $this->add_control(
            'prefix_heading',
            [
                'label' => __('Prefix', 'standalone-gsheets'),
                'type' => \Elementor\Controls_Manager::HEADING,
            ]
        );

        $this->add_control(
            'prefix_color',
            [
                'label' => __('Prefix Color', 'standalone-gsheets'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .field-prefix' => 'color: {{VALUE}};',
                ],
            ]
        );

        // تایپوگرافی Prefix
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'prefix_typography',
                'label' => __('Prefix Typography', 'standalone-gsheets'),
                'selector' => '{{WRAPPER}} .field-prefix',
            ]
        );

        // Separator
        $this->add_control(
            'prefix_suffix_separator',
            [
                'type' => \Elementor\Controls_Manager::DIVIDER,
            ]
        );

        // Heading برای Suffix
        $this->add_control(
            'suffix_heading',
            [
                'label' => __('Suffix', 'standalone-gsheets'),
                'type' => \Elementor\Controls_Manager::HEADING,
            ]
        );

        $this->add_control(
            'suffix_color',
            [
                'label' => __('Suffix Color', 'standalone-gsheets'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .field-suffix' => 'color: {{VALUE}};',
                ],
            ]
        );

        // تایپوگرافی Suffix
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'suffix_typography',
                'label' => __('Suffix Typography', 'standalone-gsheets'),
                'selector' => '{{WRAPPER}} .field-suffix',
            ]
        );

        // Separator
        $this->add_control(
            'spacing_separator',
            [
                'type' => \Elementor\Controls_Manager::DIVIDER,
            ]
        );

        // Spacing
        $this->add_responsive_control(
            'prefix_suffix_spacing',
            [
                'label' => __('Spacing', 'standalone-gsheets'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                    ],
                    'em' => [
                        'min' => 0,
                        'max' => 5,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .field-prefix' => 'margin-right: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .field-suffix' => 'margin-left: {{SIZE}}{{UNIT}};',
                    'body.rtl {{WRAPPER}} .field-prefix' => 'margin-left: {{SIZE}}{{UNIT}}; margin-right: 0;',
                    'body.rtl {{WRAPPER}} .field-suffix' => 'margin-right: {{SIZE}}{{UNIT}}; margin-left: 0;',
                ],
            ]
        );

        // اختیاری: Text Shadow برای Prefix/Suffix
        $this->add_group_control(
            \Elementor\Group_Control_Text_Shadow::get_type(),
            [
                'name' => 'prefix_suffix_text_shadow',
                'label' => __('Text Shadow', 'standalone-gsheets'),
                'selector' => '{{WRAPPER}} .field-prefix, {{WRAPPER}} .field-suffix',
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        
        if (empty($settings['field_name'])) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div class="gsheets-field-error" style="padding: 20px; background: #f8d7da; color: #721c24; border-radius: 4px;">' . 
                     __('Please select a field name', 'standalone-gsheets') . 
                     '</div>';
            }
            return;
        }
        
        // ساخت shortcode
        $shortcode_atts = [
            'field' => $settings['field_name'],
            'default' => $settings['default_value'],
        ];
        
        if (!empty($settings['spreadsheet_id'])) {
            $shortcode_atts['spreadsheet_id'] = $settings['spreadsheet_id'];
        }
        
        if (!empty($settings['sheet_name'])) {
            $shortcode_atts['sheet'] = $settings['sheet_name'];
        }
        
        $shortcode = '[gsheet_field';
        foreach ($shortcode_atts as $key => $value) {
            $shortcode .= ' ' . $key . '="' . esc_attr($value) . '"';
        }
        $shortcode .= ']';
        
        // Render
        $tag = $settings['html_tag'];
        
        echo '<' . $tag . ' class="gsheets-field-wrapper">';
        
        if (!empty($settings['prefix'])) {
            echo '<span class="field-prefix">' . esc_html($settings['prefix']) . '</span>';
        }
        
        echo '<span class="gsheets-field-value">';
        echo do_shortcode($shortcode);
        echo '</span>';
        
        if (!empty($settings['suffix'])) {
            echo '<span class="field-suffix">' . esc_html($settings['suffix']) . '</span>';
        }
        
        echo '</' . $tag . '>';
    }

    protected function content_template() {
        ?>
        <#
        var tag = settings.html_tag || 'div';
        #>
        <{{{ tag }}} class="gsheets-field-wrapper">
            <# if ( settings.prefix ) { #>
                <span class="field-prefix">{{{ settings.prefix }}}</span>
            <# } #>
            
            <span class="gsheets-field-value">
                <# if ( settings.field_name ) { #>
                    {{{ settings.field_name }}}
                <# } else { #>
                    <?php echo __('Select a field...', 'standalone-gsheets'); ?>
                <# } #>
            </span>
            
            <# if ( settings.suffix ) { #>
                <span class="field-suffix">{{{ settings.suffix }}}</span>
            <# } #>
        </{{{ tag }}}>
        <?php
    }
}