<?php
namespace SaltHareket;

class SaltConfig {

    // Bellekte tutulacak statik değişken
    private static $sh_cache_internal = null;

    // variables.php'de kullanılan tüm spesifik anahtarlar
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
        // Sınıf başlar başlamaz cache'i yükle
        self::init_cache();

        // Sadece admin panelinde kayıt dinleyicisi aktif olsun
        if (is_admin()) {
            add_action('acf/save_post', [$this, 'handle_options_save'], 30);
        }
    }

    /**
     * Cache Dosyasını veya DB'yi kullanarak belleği doldurur
     */
    private static function init_cache() {
        if (self::$sh_cache_internal !== null) {
            return;
        }

        $cache_file = THEME_STATIC_PATH . 'data/config-cache.php';

        // 1. Önce dosyadan okumayı dene (En hızlı yöntem)
        if (file_exists($cache_file)) {
            self::$sh_cache_internal = include $cache_file;
        }

        // 2. Dosya yoksa veya bozuksa DB'den çek ve dosyayı oluştur
        if (!is_array(self::$sh_cache_internal)) {
            self::generate();
        }
    }

    /**
     * Dışarıdan veri çağırmak için ana fonksiyon
     */
    public static function get($key, $default = false) {
        self::init_cache();
        
        if (isset(self::$sh_cache_internal[$key])) {
            return self::$sh_cache_internal[$key];
        }

        // Eğer target_keys içinde yoksa mecbur DB'ye git (Güvenlik önlemi)
        return QueryCache::get_option($key, $default);
    }

    /**
     * Admin panelinde ayarlar kaydedildiğinde tetiklenir
     */
    public function handle_options_save($post_id) {
        // Options sayfası kaydediliyorsa (veya opsiyon anahtarını içeriyorsa)
        if (strpos($post_id, 'options') !== false) {
            self::generate();
        }
    }

    /**
     * Tüm ayarları veritabanından çekip PHP dosyasına yazar
     */
    public static function generate() {
        $config = [];
        foreach (self::$target_keys as $key) {
            // ACF get_field yerine direkt get_option kullanarak en saf veriyi alalım
            $config[$key] = get_option($key);
        }

        $dir = THEME_STATIC_PATH . 'data/';
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }

        // Veriyi PHP array olarak hazırla (Include edildiğinde çok hızlıdır)
        $content = "<?php\n/** SaltConfig Auto Generated Cache **/\nreturn " . var_export($config, true) . ";";
        
        if (file_put_contents($dir . 'config-cache.php', $content)) {
            //error_log("SaltConfig: Ayarlar başarıyla dosyaya yazıldı.");
        } else {
            //error_log("SaltConfig HATA: Dosya yazılamadı! İzinleri kontrol et: " . $dir);
        }

        // Bellekteki veriyi de hemen güncelle
        self::$sh_cache_internal = $config;

        return $config;
    }
}

// SINIFIN KENDİ KENDİNİ ATEŞLEMESİ
new \SaltHareket\SaltConfig();