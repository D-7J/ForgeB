<?php
use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Controls_Manager;
use Elementor\Modules\DynamicTags\Module;

class Discord_Avatar_Tag extends Data_Tag {
    public function get_name() {
        return 'discord-avatar';
    }
    
    public function get_title() {
        return esc_html__('Discord Avatar', 'discord-elementor-data');
    }
    
    public function get_group() {
        return 'discord';
    }
    
    public function get_categories() {
        return [Module::IMAGE_CATEGORY];
    }
    
    protected function register_controls() {
        $this->add_control(
            'size',
            [
                'label' => esc_html__('Size', 'discord-elementor-data'),
                'type' => Controls_Manager::SELECT,
                'default' => '128',
                'options' => [
                    '64' => '64px',
                    '128' => '128px',
                    '256' => '256px',
                    '512' => '512px',
                    '1024' => '1024px',
                ],
            ]
        );
        
        $this->add_control(
            'show_fallback',
            [
                'label' => esc_html__('Show Default Image If No Avatar', 'discord-elementor-data'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'no',
            ]
        );
        
        $this->add_control(
            'fallback_image',
            [
                'label' => esc_html__('Default Image', 'discord-elementor-data'),
                'type' => Controls_Manager::MEDIA,
                'default' => [
                    'url' => 'https://cdn.discordapp.com/embed/avatars/0.png',
                ],
                'condition' => [
                    'show_fallback' => 'yes',
                ],
            ]
        );
    }
    
    public function get_value(array $options = []) {
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return $this->get_fallback_image();
        }
        
        $size = $this->get_settings('size');
        
        // استفاده از تابع کمکی سراسری
        $avatar_url = discord_elementor()->get_discord_avatar_url($user_id, $size);
        
        if (empty($avatar_url)) {
            return $this->get_fallback_image();
        }
        
        return [
            'url' => $avatar_url,
            'id' => ''
        ];
    }
    
    private function get_fallback_image() {
        $settings = $this->get_settings_for_display();
        
        if ('yes' === $settings['show_fallback'] && !empty($settings['fallback_image']['url'])) {
            return [
                'url' => $settings['fallback_image']['url'],
                'id' => $settings['fallback_image']['id']
            ];
        }
        
        return [
            'url' => '',
            'id' => ''
        ];
    }
}