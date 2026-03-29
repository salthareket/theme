<?php
namespace SaltHareket;

/**
 * SaltConfig — PHP file-based config cache.
 * ACF options'lari PHP dosyasina cache'ler, DB sorgusunu atlar.
 *
 * KULLANIM:
 *   $val = SaltConfig::get('options_enable_object_cache');
 *   SaltConfig::set('my_key', 'my_value');
 *   SaltConfig::rebuild();  // tum cache'i yeniden olustur
 *
 * @package SaltHareket
 */
class SaltConfig {

    private static $sh_cache_internal = null;
    private static $initiated = false;

    private static $target_keys = [
        "options_disable_default_cat",
        "options_disable_review_approve",
        "options_enable_activation_email_autologin",
        "options_enable_cart",
        "options_enable_chat",
        "options_enable_console_logs",
        "options_enable_favorites",
        "options_enable_follow",
        "options_enable_ip2country",
        "options_enable_location_db",
        "options_enable_logs",
        "options_enable_lost_password",
        "options_enable_membership",
        "options_enable_membership_activation",
        "options_enable_notifications",
        "options_enable_password_recover",
        "options_enable_postcode_validation",
        "options_enable_production",
        "options_enable_regional_posts",
        "options_enable_registration",
        "options_enable_remember_login",
        "options_enable_search_history",
        "options_enable_sms_notifications",
        "options_enable_social_login",
        "options_enable_twig_cache",
        "options_enable_woo_api",
        "options_exclude_from_search",
        "options_favorite_types_post_types",
        "options_favorite_types_taxonomies",
        "options_favorite_types_user_roles",
        "options_follow_types_post_types",
        "options_follow_types_taxonomies",
        "options_follow_types_user_roles",
        "options_google_maps_api_key",
        "options_inline_css",
        "options_inline_js",
        "options_ip2country_settings",
        "options_membership_activation_settings",
        "options_password_recover_settings",
        "options_role_themes",
        "options_seperate_css",
        "options_seperate_js",
        "options_white_pages",
        "sh_theme_tasks_status",
        "underConstructionActivationStatus",
        "woocommerce_enable_myaccount_registration"
    ];

    public function __construct() {
        if (self::$initiated) return;
        self::$initiated = true;

        self::init_cache();

        if (is_admin()) {
            add_action('acf/save_post', [$this, 'handle_options_save'], 30);
        }
    }

    /**
     * Cache dosyasının yolunu döndürür
     */
    private static function get_cache_path() {
        if (!defined('THEME_STATIC_PATH')) return false;
        return THEME_STATIC_PATH . 'data/config-cache.php';
    }

    /**
     * Cache dosyasını veya DB'yi kullanarak belleği doldurur
     */
    private static function init_cache() {
        if (self::$sh_cache_internal !== null) return;

        $cache_file = self::get_cache_path();

        // 1. Dosyadan oku (en hızlı)
        if ($cache_file && file_exists($cache_file)) {
            $data = @include $cache_file;
            if (is_array($data)) {
                self::$sh_cache_internal = $data;
                return;
            }
        }

        // 2. Dosya yoksa veya bozuksa DB'den çek ve dosyayı oluştur
        self::generate();
    }

    /**
     * Dışarıdan veri çağırmak için ana fonksiyon
     */
    public static function get($key, $default = false) {
        self::init_cache();

        if (self::$sh_cache_internal !== null && array_key_exists($key, self::$sh_cache_internal)) {
            return self::$sh_cache_internal[$key];
        }

        // target_keys dışındaki key'ler için DB'ye git
        return get_option($key, $default);
    }

    /**
     * Admin panelinde ACF options sayfası kaydedildiğinde tetiklenir
     */
    public function handle_options_save($post_id) {
        if ($post_id === 'options' || $post_id === 'option') {
            self::generate();
        }
    }

    /**
     * Tüm ayarları DB'den çekip PHP dosyasına yazar (atomic write)
     */
    public static function generate(): array {
        $config = [];
        foreach (self::$target_keys as $key) {
            $config[$key] = get_option($key);
        }

        $cache_file = self::get_cache_path();
        if ($cache_file) {
            $dir = dirname($cache_file);
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
            }

            $content = "<?php\n/** SaltConfig Auto Generated Cache — " . date('Y-m-d H:i:s') . " **/\nreturn " . var_export($config, true) . ";\n";

            // Atomic write: temp dosyaya yaz, sonra rename et
            // Yarıda kalırsa ana cache dosyası bozulmaz
            $tmp_file = $cache_file . '.tmp.' . getmypid();
            if (file_put_contents($tmp_file, $content, LOCK_EX) !== false) {
                rename($tmp_file, $cache_file);

                // OPcache varsa eski versiyonu invalidate et
                if (function_exists('opcache_invalidate')) {
                    opcache_invalidate($cache_file, true);
                }
            } else {
                @unlink($tmp_file);
            }
        }

        // Bellekteki veriyi hemen güncelle
        self::$sh_cache_internal = $config;

        return $config;
    }
}

new \SaltHareket\SaltConfig();
