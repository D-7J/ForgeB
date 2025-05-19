<?php
/**
 * Plugin Name: Discord Data for Elementor
 * Description: نمایش اطلاعات Discord کاربران در Elementor
 * Version: 1.0.1
 * Author: ForgeBoost
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class Discord_Elementor_Data {
    
    // نمونه شی پلاگین
    private static $instance = null;
    
    public function __construct() {
        // افزودن تگ‌های دینامیک به Elementor
        add_action('elementor/dynamic_tags/register', [$this, 'register_dynamic_tags']);
		// اضافه کردن استایل‌های پیش‌فرض
add_action('elementor/frontend/after_enqueue_styles', [$this, 'add_default_styles']);
        
        // افزودن شورتکدها
        add_shortcode('discord_username', [$this, 'discord_username_shortcode']);
        add_shortcode('discord_roles', [$this, 'discord_roles_shortcode']);
        add_shortcode('discord_if_role', [$this, 'discord_if_role_shortcode']);
        
        // افزودن ویجت Elementor
        add_action('elementor/widgets/register', [$this, 'register_widgets']);
    }
    /**
 * اضافه کردن استایل‌های پیش‌فرض
 */
public function add_default_styles() {
    wp_add_inline_style('elementor-frontend', '
        /* استایل‌های پایه برای ویجت Discord Profile */
        .discord-profile {
            display: flex !important;
            flex-direction: column !important; /* همیشه عمودی باشد */
            align-items: center !important; /* همیشه مرکز باشد */
            text-align: center !important; /* متن‌ها همیشه وسط‌چین باشند */
            width: 100%;
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
        }
        
        /* آواتار همیشه بالا باشد */
        .discord-profile .discord-avatar {
            margin: 0 0 15px 0 !important; /* حاشیه فقط پایین */
            order: 1 !important; /* همیشه اول باشد */
        }
        
        /* اطلاعات همیشه زیر آواتار باشند */
        .discord-profile .discord-info {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
            order: 2 !important; /* همیشه دوم باشد */
        }
        
        /* بخش نام کاربری */
        .discord-profile .discord-username-wrapper {
            margin-bottom: 10px;
            text-align: center;
            width: 100%;
        }
        
        /* بخش نقش‌ها */
        .discord-profile .discord-roles-wrapper {
            text-align: center;
            width: 100%;
            margin-top: 5px;
        }
        
        /* ===== حالت کارت ===== */
        .discord-profile.discord-card {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            background-color: #2c2f33;
            color: #ffffff;
            padding: 25px;
            box-sizing: border-box;
        }
        
        /* آواتار در حالت کارت */
        .discord-profile.discord-card .discord-avatar {
            margin-bottom: 20px !important; /* فاصله بیشتر در حالت کارت */
        }
        
        /* استایل برای عکس آواتار */
        .discord-profile .discord-avatar img {
            display: block;
            object-fit: cover;
            border-radius: 50%;
        }
        
        /* افزودن بردر به آواتار در حالت کارت */
        .discord-profile.discord-card .discord-avatar img {
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        /* استایل برای نام کاربری */
        .discord-profile .discord-username {
            font-weight: bold;
            font-size: 16px;
        }
        
        /* رنگ نام کاربری در حالت کارت */
        .discord-profile.discord-card .discord-username {
            color: #e67e22;
            font-size: 18px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        /* استایل برای نقش‌ها */
        .discord-profile .discord-roles {
            font-size: 14px;
            opacity: 0.9;
            line-height: 1.4;
            word-wrap: break-word;
        }
        
        /* رنگ نقش‌ها در حالت کارت */
        .discord-profile.discord-card .discord-roles {
            color: #ccc;
            letter-spacing: 0.3px;
        }
        
        /* ===== حالت بلاک ===== */
        .discord-profile.discord-block {
            padding: 10px 0;
        }
        
        /* ===== حالت اینلاین ===== */
        .discord-profile.discord-inline {
            padding: 5px 0;
        }
        
        /* استایل برچسب‌ها */
        .discord-username-label,
        .discord-roles-label {
            font-weight: bold;
            opacity: 0.8;
        }
    ');
}
    /**
     * متد Singleton برای دسترسی به نمونه این کلاس
     */
	
    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * ثبت تگ‌های دینامیک
     */
    public function register_dynamic_tags($dynamic_tags_manager) {
        // تعریف کلاس‌های تگ‌های دینامیک
        require_once plugin_dir_path(__FILE__) . 'tags/discord-username-tag.php';
        require_once plugin_dir_path(__FILE__) . 'tags/discord-roles-tag.php';
        require_once plugin_dir_path(__FILE__) . 'tags/discord-has-role-tag.php';
        require_once plugin_dir_path(__FILE__) . 'tags/discord-avatar-tag.php';
        
        // ثبت گروه دیسکورد
        \Elementor\Plugin::$instance->dynamic_tags->register_group(
            'discord',
            [
                'title' => 'Discord'
            ]
        );
        
        // ثبت تگ‌های دینامیک
        $dynamic_tags_manager->register(new Discord_Username_Tag());
        $dynamic_tags_manager->register(new Discord_Roles_Tag());
        $dynamic_tags_manager->register(new Discord_Has_Role_Tag());
        $dynamic_tags_manager->register(new Discord_Avatar_Tag());
    }
    
    /**
     * ثبت ویجت‌ها
     */
    public function register_widgets($widgets_manager) {
        require_once plugin_dir_path(__FILE__) . 'widgets/discord-profile-widget.php';
        $widgets_manager->register(new Discord_Profile_Widget());
    }
    
    /**
     * دریافت نام کاربری Discord
     */
    public function get_discord_username($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) return '';
        
        $discord_username = get_user_meta($user_id, 'discord_username', true);
        $discord_discriminator = get_user_meta($user_id, 'discord_discriminator', true);
        
        if (!empty($discord_username)) {
            if (!empty($discord_discriminator)) {
                return $discord_username . '#' . $discord_discriminator;
            }
            return $discord_username;
        }
        
        return '';
    }
    
    /**
     * دریافت نقش‌های Discord
     */
    public function get_discord_roles($user_id = null, $separator = ', ') {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) return '';
        
        $discord_roles = get_user_meta($user_id, 'discord_roles', true);
        if (!is_array($discord_roles) || empty($discord_roles)) {
            return '';
        }
        
        $role_names = $this->get_discord_role_names($discord_roles);
        
        return implode($separator, $role_names);
    }
    
    /**
     * بررسی اینکه آیا کاربر نقش خاصی دارد
     */
    public function user_has_discord_role($role_ids, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) return false;
        
        $discord_roles = get_user_meta($user_id, 'discord_roles', true);
        if (!is_array($discord_roles) || empty($discord_roles)) {
            return false;
        }
        
        if (!is_array($role_ids)) {
            $role_ids = explode(',', $role_ids);
        }
        
        $role_ids = array_map('trim', $role_ids);
        
        foreach ($role_ids as $role_id) {
            if (in_array($role_id, $discord_roles)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * تبدیل شناسه‌های نقش به نام‌های نقش
     */
    public function get_discord_role_names($role_ids) {
        $role_names = [];
        
        // دریافت تنظیمات Discord Integration
        $discord_options = get_option('discord_integration_options', []);
        $role_mappings = isset($discord_options['role_mappings']) ? $discord_options['role_mappings'] : [];
        
        // دریافت نقش‌های واقعی از پلاگین‌های Enhancer (اگر وجود داشته باشند)
        $server_roles = [];
        
        foreach ($role_ids as $role_id) {
            // سعی در یافتن نام نقش از کش نقش‌های سرور
            if (isset($server_roles[$role_id])) {
                $role_names[] = $server_roles[$role_id];
            } 
            // سعی در یافتن نقش WordPress از نگاشت‌ها
            elseif (isset($role_mappings[$role_id])) {
                $wp_role = $role_mappings[$role_id];
                $role_obj = get_role($wp_role);
                if ($role_obj) {
                    $wp_roles = wp_roles();
                    $role_name = isset($wp_roles->role_names[$wp_role]) ? $wp_roles->role_names[$wp_role] : $wp_role;
                    $role_names[] = $role_name;
                }
            }
            // در صورت عدم موفقیت، از شناسه نقش استفاده کنید
            else {
                $role_names[] = 'Role-' . $role_id;
            }
        }
        
        return $role_names;
    }
    
    /**
     * دریافت URL آواتار Discord
     */
    public function get_discord_avatar_url($user_id = null, $size = 128) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) return '';
        
        $discord_id = get_user_meta($user_id, 'discord_user_id', true);
        $avatar = get_user_meta($user_id, 'discord_avatar', true);
        
        if (!empty($discord_id) && !empty($avatar)) {
            return "https://cdn.discordapp.com/avatars/{$discord_id}/{$avatar}.png?size={$size}";
        }
        
        return '';
    }
    
    /**
     * شورتکد نام کاربری Discord
     */
    public function discord_username_shortcode($atts) {
        $atts = shortcode_atts([
            'user_id' => 0
        ], $atts);
        
        $user_id = intval($atts['user_id']) ?: get_current_user_id();
        
        return $this->get_discord_username($user_id);
    }
    
    /**
     * شورتکد نقش‌های Discord
     */
    public function discord_roles_shortcode($atts) {
        $atts = shortcode_atts([
            'user_id' => 0,
            'separator' => ', '
        ], $atts);
        
        $user_id = intval($atts['user_id']) ?: get_current_user_id();
        
        return $this->get_discord_roles($user_id, $atts['separator']);
    }
    
    /**
     * شورتکد شرطی بر اساس نقش Discord
     */
    public function discord_if_role_shortcode($atts, $content = null) {
        $atts = shortcode_atts([
            'role_ids' => '',
            'user_id' => 0
        ], $atts);
        
        $user_id = intval($atts['user_id']) ?: get_current_user_id();
        
        if ($this->user_has_discord_role($atts['role_ids'], $user_id)) {
            return do_shortcode($content);
        }
        
        return '';
    }
}

// ایجاد و ذخیره نمونه اصلی
$GLOBALS['discord_elementor_data'] = Discord_Elementor_Data::get_instance();

// تابع کمکی برای دسترسی به نمونه از سایر فایل‌ها
function discord_elementor() {
    return $GLOBALS['discord_elementor_data'];
}