<?php
namespace SaltHareket;

/**
 * SaltConfig — PHP file-based config cache.
 * ACF options'lari PHP dosyasina cache'ler, DB sorgusunu atlar.
 *
 * @version 1.2.0
 *
 * @changelog
 *   1.3.0 - 2026-07-09
 *     - Add: target_keys genişletildi — breadcrumb, polylang, woo API key'leri eklendi
 *     - Add: CACHE_VERSION = 6 — yeni key'ler için cache yenilenir
 *   1.4.0 - 2026-07-09
 *     - Add: target_keys'e `vp_enabled` eklendi (ENABLE_VIDEO_PROCESSOR)
 *     - Add: generate() docblock güncellendi — çağrı kaynakları ve sh_maintenance_settings notu
 *     - Change: CACHE_VERSION = 7 — vp_enabled için cache yenilenir
 *   1.5.0 - 2026-07-09
 *     - Add: target_keys'e Settings sınıfları eklendi (sh_asset_manager_settings)
 *     - PERF: sh_asset_manager_settings PHP OPcache'ten okunuyor (0q)
 *     - Change: CACHE_VERSION = 8
 *   1.6.0 - 2026-07-09
 *     - Add: target_keys'e map key'leri eklendi:
 *            options_map_service, options_map_view, options_map_marker, options_logo_marker
 *     - FIX: get_map_data() bug — $map_service boşsa leaflet default
 *     - FIX: _get_option() twig helper SaltConfig fallback eklendi
 *     - Change: CACHE_VERSION = 9
 *     - Add: handle_options_save() → ACF options save'de wrap cache temizleme
 *   1.2.0 - 2026-07-05
 *     - PERF: target_keys temizlendi — ACF field'ları kaldırıldığı için
 *             app'lerin kendi option key'leri artık SaltConfig'de tutulmuyor.
 *             (sh_membership_settings, sh_notifications_settings, sh_localization_settings vs.)
 *     - Remove: options_role_themes, options_white_pages, underConstructionActivationStatus,
 *               sh_maintenance_settings, options_enable_membership ve tüm app option key'leri
 *     - Add: cfg_or_option() static helper — Settings sınıfları için ortak SaltConfig okuma
 *     - Add: CACHE_VERSION = 4 — versiyon artırıldı, eski cache otomatik invalidate
 *   1.1.0 - 2026-07-04
 *     - Add: CACHE_VERSION mekanizması
 *     - Add: target_keys genişletildi
 *     - PERF: Settings sınıflarının ACF fallback metodları SaltConfig::get() kullanmaya başladı
 *   1.0.0 - 2026-04-03
 *     - Add: Initial versioned release
 *
 * How to use:
 *   $val = SaltConfig::get('options_enable_development');
 *   SaltConfig::rebuild();  // tum cache'i yeniden olustur
 *
 * @package SaltHareket
 */
class SaltConfig {

    private static $sh_cache_internal = null;
    private static $initiated = false;

    /** Key listesi değişince bu versiyonu artır → cache otomatik yenilenir */
    private const CACHE_VERSION = 14;

    private static $target_keys = [
        // variables.php'de get_sh_config() ile direkt kullanılanlar
        "options_disable_default_cat",      // DISABLE_DEFAULT_CAT
        "options_enable_cart",              // ENABLE_CART
        "options_disable_wc_frontend",      // ENABLE_ECOMMERCE override
        "options_enable_woo_api",           // ENABLE_WOO_API
        "options_exclude_from_search",      // EXCLUDE_FROM_SEARCH
        "options_taxonomy_prefix_remove",   // GeneralSettings — taxonomy URL prefix kaldırma
        "options_add_sticky_support",       // GeneralSettings — sticky post type'lar
        "options_contact_post_types",       // GeneralSettings — contact field group ek post type'lar
        "options_post_pagination",          // GeneralSettings — post type pagination ayarları
        "options_search_pagination",        // GeneralSettings — search pagination ayarları
        "options_google_maps_api_key",      // Data::set("google_maps_api_key")
        "sh_theme_tasks_status",            // SH_THEME_EXISTS

        // WooCommerce API credentials
        "options_woo_api_local",
        "options_woo_api_live",

        // Video Processor App
        "vp_enabled",                       // ENABLE_VIDEO_PROCESSOR

        // Breadcrumb (yoast-seo.php — wpseo_breadcrumb_links filter)
        "options_breadcrumb_remove_home",
        "options_breadcrumb_remove_current",
        "options_breadcrumb_from_menu",
        "options_breadcrumb_add_page_path",
        "options_breadcrumb_add_product_brand",
        "options_breadcrumb_add_product_taxonomy",

        // Polylang (polylang.php — her request'te hook içinde)
        "options_untranslatable_taxonomies",
        "options_untranslatable_post_types",
        "options_synchronized_pages",
        "options_sync_taxonomies_to_language",

        // ── Settings sınıfları — her request'te get_option() yapıyorlar ──────
        // NOT: Sadece küçük, scalar değerli key'ler buraya girmeli.
        // Büyük nested array'ler (membership, reviews, localization, sms vb.)
        // WP object cache'den okumak daha hızlı — SaltConfig'e alınmadı.
        "sh_asset_manager_settings",    // AssetSettings::get() — production, logs, twig cache vs.

        // Logos (options — WP image array formatında saklanır)
        "options_logo",
        "options_logo_affix",
        "options_logo_footer",
        "options_logo_mobile",
        "options_logo_mobile_breakpoint",
        "options_logo_icon",
        "options_logo_marker",
        "options_logo_not_found",

        // Defaults Tab (General Settings)
        "options_default_container",
        "options_default_margin_top",
        "options_default_margin_bottom",
        "options_default_margin_left",
        "options_default_margin_right",
        "options_default_padding_top",
        "options_default_padding_bottom",
        "options_default_padding_left",
        "options_default_padding_right",
        "options_default_ratio",
        "options_default_title_size",
        "options_default_text_size",
        "options_default_footer_template",

        // Map ayarları — get_map_data() ve get_map_config() her post için çağrılıyor
        "options_map_service",          // map_service: leaflet | google | embed
        "options_map_view",             // map_view: dynamic | embed
        "options_map_marker",           // Global map marker icon
        "options_logo_marker",          // Logo marker (fallback)

        // Product Card (tease.twig) ayarları
        "options_tease_settings",       // array — multiple_images, show_variations, swatch_* vb.

        // Product Gallery (single-product) ayarları
        "options_gallery_settings",     // array — type, ratio, hover, swiper params vb.
    
        // "options_exclude_post_types_from_cache",    // PAE
        // "options_exclude_posts_from_cache",         // PAE
        // "options_exclude_taxonomies_from_cache",    // PAE
        //"woocommerce_enable_myaccount_registration" // ENABLE_REGISTRATION (WC mode)
        // "options_disable_review_approve" — ReviewsSettings::isApproveDisabled() sh_reviews_settings'ten okuyor, SaltConfig gereksiz
        // "options_enable_console_logs"    — AssetSettings'e taşındı
        // "options_enable_logs"            — AssetSettings'e taşındı
        // "options_enable_development"      — AssetSettings'e taşındı
        // "options_enable_twig_cache"      — AssetSettings'e taşındı
        // "options_inline_css"             — AssetSettings'e taşındı
        // "options_inline_js"              — AssetSettings'e taşındı
        // "options_seperate_css"           — AssetSettings'e taşındı
        // "options_seperate_js"            — AssetSettings'e taşındı
        // Kaldırılanlar (ACF field'ları silindi, app'ler kendi option'larını kullanıyor):
        // "options_enable_membership"           → sh_membership_settings
        // "options_enable_membership_activation" → sh_membership_settings
        // "options_enable_activation_email_autologin" → sh_membership_settings
        // "options_enable_registration"         → sh_membership_settings
        // "options_enable_remember_login"        → sh_membership_settings
        // "options_enable_lost_password"         → sh_membership_settings
        // "options_enable_password_recover"      → sh_membership_settings
        // "options_membership_activation_settings" → sh_membership_settings
        // "options_password_recover_settings"    → sh_membership_settings
        // "options_enable_social_login"          → sh_membership_settings
        // "options_enable_postcode_validation"   → sh_membership_settings
        // "options_enable_chat"                  → sh_membership_settings
        // "options_enable_notifications"         → sh_notifications_settings
        // "options_enable_sms_notifications"     → sh_notifications_settings
        // "options_enable_web_push"              → sh_notifications_settings
        // "options_enable_reactions"             → sh_reactions_app_settings
        // "options_enable_search_history"        → sh_search_history_settings
        // "options_enable_ip2country"            → sh_localization_settings
        // "options_enable_location_db"           → sh_localization_settings
        // "options_enable_regional_posts"        → sh_localization_settings
        // "options_ip2country_settings"          → sh_localization_settings
        // "options_ip2country_source"            → sh_localization_settings
        // "options_regional_post_settings"       → sh_localization_settings
        // "options_region_main"                  → sh_localization_settings
        // "options_role_themes"                  → hiç kullanılmıyor
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
     * Cache dosyasını veya DB'yi kullanarak belleği doldurur.
     * CACHE_VERSION değişirse dosya yeniden oluşturulur.
     */
    private static function init_cache() {
        if (self::$sh_cache_internal !== null) return;

        $cache_file = self::get_cache_path();

        // 1. Dosyadan oku (en hızlı)
        if ($cache_file && file_exists($cache_file)) {
            $data = @include $cache_file;
            if (is_array($data) && isset($data['__version']) && $data['__version'] === self::CACHE_VERSION) {
                self::$sh_cache_internal = $data;
                return;
            }
            // Versiyon uyuşmuyor → yeniden oluştur
        }

        // 2. Dosya yoksa veya versiyonu eskiyse DB'den çek ve dosyayı oluştur
        self::generate();
    }

    /**
     * Dışarıdan veri çağırmak için ana fonksiyon
     */
    public static function get($key, $default = false) {
        self::init_cache();

        if (self::$sh_cache_internal !== null && array_key_exists($key, self::$sh_cache_internal)) {
            // __version internal key'i dışarıya sızdırma
            if ($key === '__version') return $default;
            return self::$sh_cache_internal[$key];
        }

        // target_keys dışındaki key'ler için DB'ye git
        return get_option($key, $default);
    }

    /**
     * Settings sınıfları için ACF fallback helper.
     * SaltConfig PHP cache'inden okur — DB sorgusu yok.
     * ACF init olmadan önce çağrılır (readAcfFallback içinde).
     *
     * Kullanım (Settings sınıflarında):
     *   $v = SaltConfig::cfg_or_option('options_enable_membership');
     */
    public static function cfg_or_option( string $key ) {
        self::init_cache();
        if ( self::$sh_cache_internal !== null && array_key_exists( $key, self::$sh_cache_internal ) ) {
            return self::$sh_cache_internal[ $key ];
        }
        return get_option( $key );
    }

    /**
     * Admin panelinde ACF options sayfası kaydedildiğinde tetiklenir
     */
    public function handle_options_save($post_id) {
        if ($post_id === 'options' || $post_id === 'option') {
            self::generate();

            // header_footer_opts_* wrap cache'ini temizle — options sayfası kaydedilince
            // header/footer ayarları değişmiş olabilir, wrap transient'ları stale kalmasın.
            // JSON yenileme actions.php'deki acf/save_post hook'unda yapılıyor.
            if ( class_exists('QueryCache') ) {
                \QueryCache::purge_type('wrap');
            }
        }
    }

    /**
     * Tüm ayarları DB'den çekip PHP dosyasına yazar (atomic write).
     *
     * Çağrı kaynakları:
     *  - init_cache()                  : cache dosyası yok veya __version uyuşmuyor
     *  - handle_options_save()         : ACF options sayfası kaydedildiğinde (acf/save_post)
     *  - MaintenanceSettings::save()   : sh_maintenance_settings güncellendiğinde —
     *                                    sh_maintenance_settings target_keys'de YOK,
     *                                    bu çağrı diğer ACF option key'lerini taze tutmak içindir
     *  - AssetSettings::save()         : Asset Manager settings kaydedildiğinde
     *
     * Not: sh_maintenance_settings target_keys'e EKLENMEMELİDİR.
     *      MaintenanceSettings kendi static $cache ile request başına tek get_option() yapıyor.
     */
    public static function generate(): array {
        $config = ['__version' => self::CACHE_VERSION];
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