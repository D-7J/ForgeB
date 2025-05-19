<?php
use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;

class Discord_Username_Tag extends Tag {
    public function get_name() {
        return 'discord-username';
    }
    
    public function get_title() {
        return esc_html__('Discord Username', 'discord-elementor-data');
    }
    
    public function get_group() {
        return 'discord';
    }
    
    public function get_categories() {
        return [Module::TEXT_CATEGORY];
    }
    
    public function render() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }
        
        $discord_username = get_user_meta($user_id, 'discord_username', true);
        $discord_discriminator = get_user_meta($user_id, 'discord_discriminator', true);
        
        if (!empty($discord_username)) {
            echo esc_html($discord_username);
            if (!empty($discord_discriminator)) {
                echo '#' . esc_html($discord_discriminator);
            }
        }
    }
}