<?php
use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Controls_Manager;
use Elementor\Modules\DynamicTags\Module;

class Discord_Has_Role_Tag extends Data_Tag {
    public function get_name() {
        return 'discord-has-role';
    }
    
    public function get_title() {
        return esc_html__('Discord Has Role', 'discord-elementor-data');
    }
    
    public function get_group() {
        return 'discord';
    }
    
    public function get_categories() {
        return [Module::TEXT_CATEGORY];
    }
    
    protected function register_controls() {
        $this->add_control(
            'role_ids',
            [
                'label' => esc_html__('Discord Role IDs', 'discord-elementor-data'),
                'type' => Controls_Manager::TEXT,
                'description' => esc_html__('Enter Discord role IDs separated by commas. You can find these IDs in Discord Integration > Role Mapping settings.', 'discord-elementor-data'),
            ]
        );
    }
    
    public function get_value(array $options = []) {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return 'false';
        }
        
        $role_ids = $this->get_settings('role_ids');
        if (empty($role_ids)) {
            return 'false';
        }
        
        // استفاده از تابع کمکی سراسری
        if (discord_elementor()->user_has_discord_role($role_ids, $user_id)) {
            return 'true';
        }
        
        return 'false';
    }
}