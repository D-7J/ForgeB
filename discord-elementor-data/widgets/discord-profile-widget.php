<?php
use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Image_Size;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;

class Discord_Profile_Widget extends Widget_Base {
    public function get_name() {
        return 'discord_profile';
    }
    
    public function get_title() {
        return esc_html__('Discord Profile', 'discord-elementor-data');
    }
    
    public function get_icon() {
        return 'eicon-person';
    }
    
    public function get_categories() {
        return ['general'];
    }
    
    protected function register_controls() {
        $this->start_controls_section(
            'content_section',
            [
                'label' => esc_html__('Content', 'discord-elementor-data'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );
        
        $this->add_control(
            'show_avatar',
            [
                'label' => esc_html__('Show Avatar', 'discord-elementor-data'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => esc_html__('Yes', 'discord-elementor-data'),
                'label_off' => esc_html__('No', 'discord-elementor-data'),
                'default' => 'yes',
            ]
        );
        
        $this->add_control(
            'avatar_size',
            [
                'label' => esc_html__('Avatar Size', 'discord-elementor-data'),
                'type' => Controls_Manager::SELECT,
                'default' => '128',
                'options' => [
                    '64' => '64px',
                    '128' => '128px',
                    '256' => '256px',
                    '512' => '512px',
                ],
                'condition' => [
                    'show_avatar' => 'yes',
                ],
            ]
        );
        
        $this->add_control(
            'avatar_position',
            [
                'label' => esc_html__('Avatar Position', 'discord-elementor-data'),
                'type' => Controls_Manager::CHOOSE,
                'options' => [
                    'left' => [
                        'title' => esc_html__('Left', 'discord-elementor-data'),
                        'icon' => 'eicon-h-align-left',
                    ],
                    'top' => [
                        'title' => esc_html__('Top', 'discord-elementor-data'),
                        'icon' => 'eicon-v-align-top',
                    ],
                    'right' => [
                        'title' => esc_html__('Right', 'discord-elementor-data'),
                        'icon' => 'eicon-h-align-right',
                    ],
                ],
                'default' => 'left',
                'prefix_class' => 'discord-avatar-position-',
                'condition' => [
                    'show_avatar' => 'yes',
                ],
            ]
        );
        
        $this->add_control(
            'show_username',
            [
                'label' => esc_html__('Show Username', 'discord-elementor-data'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => esc_html__('Yes', 'discord-elementor-data'),
                'label_off' => esc_html__('No', 'discord-elementor-data'),
                'default' => 'yes',
            ]
        );
        
        $this->add_control(
            'username_label',
            [
                'label' => esc_html__('Username Label', 'discord-elementor-data'),
                'type' => Controls_Manager::TEXT,
                'default' => '',
                'placeholder' => esc_html__('e.g. "Discord Username:"', 'discord-elementor-data'),
                'condition' => [
                    'show_username' => 'yes',
                ],
            ]
        );
        
        $this->add_control(
            'show_roles',
            [
                'label' => esc_html__('Show Roles', 'discord-elementor-data'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => esc_html__('Yes', 'discord-elementor-data'),
                'label_off' => esc_html__('No', 'discord-elementor-data'),
                'default' => 'yes',
            ]
        );
        
        $this->add_control(
            'roles_label',
            [
                'label' => esc_html__('Roles Label', 'discord-elementor-data'),
                'type' => Controls_Manager::TEXT,
                'default' => '',
                'placeholder' => esc_html__('e.g. "Discord Roles:"', 'discord-elementor-data'),
                'condition' => [
                    'show_roles' => 'yes',
                ],
            ]
        );
        
        $this->add_control(
            'roles_separator',
            [
                'label' => esc_html__('Roles Separator', 'discord-elementor-data'),
                'type' => Controls_Manager::TEXT,
                'default' => ', ',
                'condition' => [
                    'show_roles' => 'yes',
                ],
            ]
        );
        
        $this->add_control(
            'layout',
            [
                'label' => esc_html__('Layout', 'discord-elementor-data'),
                'type' => Controls_Manager::SELECT,
                'default' => 'inline',
                'options' => [
                    'inline' => esc_html__('Inline', 'discord-elementor-data'),
                    'block' => esc_html__('Block', 'discord-elementor-data'),
                    'card' => esc_html__('Card', 'discord-elementor-data'),
                ],
                'prefix_class' => 'discord-profile-layout-',
            ]
        );
        
        $this->add_control(
            'alignment',
            [
                'label' => esc_html__('Alignment', 'discord-elementor-data'),
                'type' => Controls_Manager::CHOOSE,
                'options' => [
                    'left' => [
                        'title' => esc_html__('Left', 'discord-elementor-data'),
                        'icon' => 'eicon-text-align-left',
                    ],
                    'center' => [
                        'title' => esc_html__('Center', 'discord-elementor-data'),
                        'icon' => 'eicon-text-align-center',
                    ],
                    'right' => [
                        'title' => esc_html__('Right', 'discord-elementor-data'),
                        'icon' => 'eicon-text-align-right',
                    ],
                ],
                'default' => 'left',
                'selectors' => [
                    '{{WRAPPER}} .discord-profile' => 'text-align: {{VALUE}};',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // بخش استایل آواتار
        $this->start_controls_section(
            'avatar_style_section',
            [
                'label' => esc_html__('Avatar Style', 'discord-elementor-data'),
                'tab' => Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_avatar' => 'yes',
                ],
            ]
        );
        
        $this->add_control(
            'avatar_size_style',
            [
                'label' => esc_html__('Size', 'discord-elementor-data'),
                'type' => Controls_Manager::SLIDER,
                'range' => [
                    'px' => [
                        'min' => 16,
                        'max' => 256,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .discord-avatar img' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_control(
            'avatar_border_radius',
            [
                'label' => esc_html__('Border Radius', 'discord-elementor-data'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .discord-avatar img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'default' => [
                    'top' => '50',
                    'right' => '50',
                    'bottom' => '50',
                    'left' => '50',
                    'unit' => '%',
                    'isLinked' => true,
                ],
            ]
        );
        
        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'avatar_border',
                'selector' => '{{WRAPPER}} .discord-avatar img',
            ]
        );
        
        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'avatar_box_shadow',
                'selector' => '{{WRAPPER}} .discord-avatar img',
            ]
        );
        
        $this->add_responsive_control(
            'avatar_margin',
            [
                'label' => esc_html__('Margin', 'discord-elementor-data'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .discord-avatar' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // بخش استایل نام کاربری
        $this->start_controls_section(
            'username_style_section',
            [
                'label' => esc_html__('Username Style', 'discord-elementor-data'),
                'tab' => Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_username' => 'yes',
                ],
            ]
        );
        
        $this->add_control(
            'username_color',
            [
                'label' => esc_html__('Color', 'discord-elementor-data'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .discord-username' => 'color: {{VALUE}}',
                ],
                'global' => [
                    'default' => Global_Colors::COLOR_TEXT,
                ],
            ]
        );
        
        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'username_typography',
                'selector' => '{{WRAPPER}} .discord-username',
                'global' => [
                    'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
                ],
            ]
        );
        
        $this->add_group_control(
            Group_Control_Text_Shadow::get_type(),
            [
                'name' => 'username_text_shadow',
                'selector' => '{{WRAPPER}} .discord-username',
            ]
        );
        
        $this->add_control(
            'username_label_color',
            [
                'label' => esc_html__('Label Color', 'discord-elementor-data'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .discord-username-label' => 'color: {{VALUE}}',
                ],
                'condition' => [
                    'username_label!' => '',
                ],
            ]
        );
        
        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'username_label_typography',
                'selector' => '{{WRAPPER}} .discord-username-label',
                'condition' => [
                    'username_label!' => '',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'username_spacing',
            [
                'label' => esc_html__('Spacing', 'discord-elementor-data'),
                'type' => Controls_Manager::SLIDER,
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .discord-username-wrapper' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // بخش استایل نقش‌ها
        $this->start_controls_section(
            'roles_style_section',
            [
                'label' => esc_html__('Roles Style', 'discord-elementor-data'),
                'tab' => Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_roles' => 'yes',
                ],
            ]
        );
        
        $this->add_control(
            'roles_color',
            [
                'label' => esc_html__('Color', 'discord-elementor-data'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .discord-roles' => 'color: {{VALUE}}',
                ],
                'global' => [
                    'default' => Global_Colors::COLOR_TEXT,
                ],
            ]
        );
        
        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'roles_typography',
                'selector' => '{{WRAPPER}} .discord-roles',
                'global' => [
                    'default' => Global_Typography::TYPOGRAPHY_TEXT,
                ],
            ]
        );
        
        $this->add_control(
            'roles_label_color',
            [
                'label' => esc_html__('Label Color', 'discord-elementor-data'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .discord-roles-label' => 'color: {{VALUE}}',
                ],
                'condition' => [
                    'roles_label!' => '',
                ],
            ]
        );
        
        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'roles_label_typography',
                'selector' => '{{WRAPPER}} .discord-roles-label',
                'condition' => [
                    'roles_label!' => '',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // بخش استایل کارت
        $this->start_controls_section(
            'card_style_section',
            [
                'label' => esc_html__('Card Style', 'discord-elementor-data'),
                'tab' => Controls_Manager::TAB_STYLE,
                'condition' => [
                    'layout' => 'card',
                ],
            ]
        );
        
        $this->add_control(
            'card_background_color',
            [
                'label' => esc_html__('Background Color', 'discord-elementor-data'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .discord-profile.discord-card' => 'background-color: {{VALUE}}',
                ],
                'default' => '#2c2f33',
            ]
        );
        
        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'card_box_shadow',
                'selector' => '{{WRAPPER}} .discord-profile.discord-card',
            ]
        );
        
        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'card_border',
                'selector' => '{{WRAPPER}} .discord-profile.discord-card',
            ]
        );
        
        $this->add_control(
            'card_border_radius',
            [
                'label' => esc_html__('Border Radius', 'discord-elementor-data'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .discord-profile.discord-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'card_padding',
            [
                'label' => esc_html__('Padding', 'discord-elementor-data'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .discord-profile.discord-card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'default' => [
                    'top' => '20',
                    'right' => '20',
                    'bottom' => '20',
                    'left' => '20',
                    'unit' => 'px',
                    'isLinked' => true,
                ],
            ]
        );
        
        $this->end_controls_section();
    }
    
    protected function render() {
        $settings = $this->get_settings_for_display();
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            echo '<div class="discord-profile">Please log in to view your Discord profile.</div>';
            return;
        }
        
        // بررسی اتصال به Discord
        $discord_id = get_user_meta($user_id, 'discord_user_id', true);
        if (empty($discord_id)) {
            echo '<div class="discord-profile">You are not connected to Discord.</div>';
            return;
        }
        
        // CSS کلاس‌ها بر اساس تنظیمات
        $profile_classes = ['discord-profile'];
        
        if ($settings['layout'] === 'card') {
            $profile_classes[] = 'discord-card';
        } elseif ($settings['layout'] === 'block') {
            $profile_classes[] = 'discord-block';
        } else {
            $profile_classes[] = 'discord-inline';
        }
        
        echo '<div class="' . esc_attr(implode(' ', $profile_classes)) . '">';
        
        // نمایش آواتار
        if ($settings['show_avatar'] === 'yes') {
            $avatar_url = discord_elementor()->get_discord_avatar_url($user_id, $settings['avatar_size']);
            if (!empty($avatar_url)) {
                echo '<div class="discord-avatar">';
                echo '<img src="' . esc_url($avatar_url) . '" alt="Discord Avatar">';
                echo '</div>';
            }
        }
        
        echo '<div class="discord-info">';
        
        // نمایش نام کاربری
        if ($settings['show_username'] === 'yes') {
            $username = discord_elementor()->get_discord_username($user_id);
            if (!empty($username)) {
                echo '<div class="discord-username-wrapper">';
                
                if (!empty($settings['username_label'])) {
                    echo '<span class="discord-username-label">' . esc_html($settings['username_label']) . ' </span>';
                }
                
                echo '<span class="discord-username">' . esc_html($username) . '</span>';
                echo '</div>';
            }
        }
        
        // نمایش نقش‌ها
        if ($settings['show_roles'] === 'yes') {
            $roles = discord_elementor()->get_discord_roles($user_id, $settings['roles_separator']);
            if (!empty($roles)) {
                echo '<div class="discord-roles-wrapper">';
                
                if (!empty($settings['roles_label'])) {
                    echo '<span class="discord-roles-label">' . esc_html($settings['roles_label']) . ' </span>';
                }
                
                echo '<span class="discord-roles">' . esc_html($roles) . '</span>';
                echo '</div>';
            }
        }
        
        echo '</div>'; // .discord-info
        echo '</div>'; // .discord-profile
        
        // CSS برای ساختاربندی
        ?>
        <style>
            .discord-profile {
                display: flex;
                align-items: center;
            }
            
            .discord-profile.discord-block {
                display: block;
            }
            
            .discord-profile.discord-inline {
                display: flex;
            }
            
            .discord-profile.discord-card {
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            }
            
            .discord-avatar-position-left .discord-avatar {
                margin-right: 15px;
            }
            
            .discord-avatar-position-right .discord-avatar {
                margin-left: 15px;
                order: 2;
            }
            
            .discord-avatar-position-right .discord-info {
                order: 1;
            }
            
            .discord-avatar-position-top.discord-profile {
                flex-direction: column;
            }
            
            .discord-avatar-position-top .discord-avatar {
                margin-bottom: 15px;
            }
            
            .discord-avatar img {
                display: block;
                object-fit: cover;
            }
            
            .discord-username-wrapper,
            .discord-roles-wrapper {
                margin-bottom: 5px;
            }
            
            .discord-block .discord-username-wrapper,
            .discord-block .discord-roles-wrapper {
                display: block;
                margin-bottom: 10px;
            }
        </style>
        <?php
    }
}