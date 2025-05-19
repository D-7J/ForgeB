<?php
use Elementor\Core\DynamicTags\Tag;
use Elementor\Controls_Manager;
use Elementor\Modules\DynamicTags\Module;

class Discord_Roles_Tag extends Tag {
    public function get_name() {
        return 'discord-roles';
    }
    
    public function get_title() {
        return esc_html__('Discord Roles', 'discord-elementor-data');
    }
    
    public function get_group() {
        return 'discord';
    }
    
    public function get_categories() {
        return [Module::TEXT_CATEGORY];
    }
    
    protected function register_controls() {
        $this->add_control(
            'separator',
            [
                'label' => esc_html__('Separator', 'discord-elementor-data'),
                'type' => Controls_Manager::TEXT,
                'default' => ', ',
            ]
        );
    }
    
    public function render() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }
        
        $separator = $this->get_settings('separator');
        
        $discord_roles = get_user_meta($user_id, 'discord_roles', true);
        if (!is_array($discord_roles) || empty($discord_roles)) {
            return;
        }
        
        // استفاده از تابع کمکی سراسری به جای متغیر global
        $role_names = discord_elementor()->get_discord_role_names($discord_roles);
        
        if (!empty($role_names)) {
            echo esc_html(implode($separator, $role_names));
        }
    }
}