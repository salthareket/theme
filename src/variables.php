<?php

if (defined('VARIABLES_LOADED')) {
    return; // Eğer daha önce yüklendiyse tekrar yükleme
}

if (!function_exists("get_home_path")) {
    include_once ABSPATH . "/wp-admin/includes/file.php";
}

// Performans iÃ§in yolları bir kez alalım
$template_uri  = get_template_directory_uri();
$template_path = get_template_directory();
$is_admin      = is_admin() || (defined('REST_REQUEST') && REST_REQUEST) || (defined('DOING_AJAX') && DOING_AJAX);

define('IS_INTERNAL_FETCH', !empty($_SERVER['HTTP_X_INTERNAL_FETCH']));

// SaltHareket/Theme paths - Dinamik URÄ°'ler değişken üzerinden
define('SH_PATH',           __DIR__ . '/');
define('SH_INCLUDES_PATH',  __DIR__ . '/includes/');
define('SH_CLASSES_PATH',   __DIR__ . '/classes/');
define('SH_STATIC_PATH',    __DIR__ . '/static/');

define('SH_URL',            $template_uri . "/vendor/salthareket/theme/");
define('SH_INCLUDES_URL',   $template_uri . "/vendor/salthareket/theme/src/includes/");
define('SH_CLASSES_URL',    $template_uri . "/vendor/salthareket/theme/src/classes/");
define('SH_STATIC_URL',     $template_uri . "/vendor/salthareket/theme/src/static/");

define('STATIC_PATH',       $template_path . '/static/');
define('STATIC_URL',        $template_uri . "/static/");

define('THEME_INCLUDES_PATH', $template_path . '/theme/includes/');
define('THEME_STATIC_PATH',   $template_path . '/theme/static/');

define('THEME_INCLUDES_URL',  $template_uri . '/theme/includes/');
define('THEME_STATIC_URL',    $template_uri . "/theme/static/");

// TODO: Bu token ve secret key environment variable'a tasinmali (wp-config.php veya .env)
// Repo'da acik metin olarak tutulmamali â€” guvenlik riski.
define("SALTHAREKET_TOKEN", "ghp"."_"."vF6wmC6wai3WMgZutFgJiIlYJJO8Ac0a1cja");
define("ENCRYPT_SECRET_KEY", "gV6QaS3zRm4Ei8NkXw0Lp1bBfDy5hTjY");

// Gerekli sınıflar
include_once SH_CLASSES_PATH . "class.config.php";
function get_sh_config( $key, $default = false ) {
    return \SaltHareket\SaltConfig::get( $key, $default );
}
include_once SH_CLASSES_PATH . "class.query-cache.php";
include_once SH_CLASSES_PATH . "class.fa-icon-manager.php";
include_once SH_CLASSES_PATH . "class.icon-registry.php";
include_once SH_CLASSES_PATH . "class.profiler.php";

// Profiler — aktif etmek için wp-config.php'ye ekle: define('SALT_PROFILER', true);
// Admin-ajax, REST ve cron request'lerinde profiler kapalı (log gürültüsü önleme)
$_salt_script = isset($_SERVER['SCRIPT_FILENAME']) ? basename($_SERVER['SCRIPT_FILENAME']) : '';
$_salt_uri    = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
$_salt_profiler_skip = (defined('DOING_AJAX') && DOING_AJAX)
    || (defined('DOING_CRON') && DOING_CRON)
    || (defined('REST_REQUEST') && REST_REQUEST)
    || $_salt_script === 'admin-ajax.php'
    || strpos($_salt_uri, '/wp-json/') !== false
    || strpos($_salt_uri, 'admin-ajax.php') !== false
    // Prefetch/prerender request'lerini atla
    || ( isset($_SERVER['HTTP_X_PURPOSE']) && strtolower($_SERVER['HTTP_X_PURPOSE']) === 'preview' )
    || ( isset($_SERVER['HTTP_X_MOZ']) && strtolower($_SERVER['HTTP_X_MOZ']) === 'prefetch' )
    || ( isset($_SERVER['HTTP_SEC_PURPOSE']) && strtolower($_SERVER['HTTP_SEC_PURPOSE']) === 'prefetch' )
    || ( isset($_SERVER['HTTP_PURPOSE']) && strtolower($_SERVER['HTTP_PURPOSE']) === 'prefetch' )
    // PAE internal fetch
    || ( defined('IS_INTERNAL_FETCH') && IS_INTERNAL_FETCH )
    || ( isset($_SERVER['HTTP_X_INTERNAL_FETCH']) );

if ( defined('SALT_PROFILER') && SALT_PROFILER && ! $_salt_profiler_skip ) {
    SaltProfiler::enable();
    SaltProfiler::mark('variables.php start');
    // Shutdown hook — sadece bu request'e bağla
    add_action( 'shutdown', static function () {
        $url        = $_SERVER['REQUEST_URI'] ?? 'unknown';
        $ua         = substr( $_SERVER['HTTP_USER_AGENT'] ?? 'no-ua', 0, 80 );
        $referer    = $_SERVER['HTTP_REFERER'] ?? '';
        $method     = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $title      = "SALT PROFILER — {$method} {$url}";
        if ( $referer ) $title .= " [ref: {$referer}]";
        $title .= " [ua: {$ua}]";
        \SaltProfiler::mark( 'shutdown' );
        \SaltProfiler::report( $title );
    }, 9998 );
}
unset($_salt_script, $_salt_uri, $_salt_profiler_skip);

// fa_icon() global PHP helper
if ( ! function_exists( 'fa_icon' ) ) {
    function fa_icon( string $name, string $style = 'solid', string $classes = '' ): string {
        return \SaltHareket\FaIconManager::render( $name, $style, $classes );
    }
}

// App Settings — define()'lardan ÖNCE yüklenmeli
include_once SH_INCLUDES_PATH . 'apps/asset-manager/AssetSettings.php'; // ENABLE_DEVELOPMENT vs. için erken yükle
include_once SH_INCLUDES_PATH . 'apps/membership/MembershipSettings.php';
include_once SH_INCLUDES_PATH . 'apps/notifications/NotificationsSettings.php';
include_once SH_INCLUDES_PATH . 'apps/reactions/ReactionsAppSettings.php';
include_once SH_INCLUDES_PATH . 'apps/download-log/DownloadLogSettings.php'; // ENABLE_DOWNLOAD_LOG için erken yükle
// SMS App Settings — ENABLE_SMS define'ından önce yüklenmeli
include_once SH_INCLUDES_PATH . 'apps/sms/Contracts/SmsContract.php';
include_once SH_INCLUDES_PATH . 'apps/sms/SmsEncoding.php';
include_once SH_INCLUDES_PATH . 'apps/sms/SmsSettings.php';
include_once SH_INCLUDES_PATH . 'apps/search-history/SearchHistorySettings.php';
include_once SH_INCLUDES_PATH . 'apps/reviews/ReviewsSettings.php';
include_once SH_INCLUDES_PATH . 'apps/localization/LocationSettings.php';
include_once SH_INCLUDES_PATH . 'apps/localization/Schema/LocationSchema.php';
include_once SH_INCLUDES_PATH . 'apps/localization/Concerns/HandlesGeo.php';
include_once SH_INCLUDES_PATH . 'apps/localization/Concerns/HandlesIpGeo.php';
include_once SH_INCLUDES_PATH . 'apps/localization/Concerns/HandlesRegionalPosts.php';
include_once SH_INCLUDES_PATH . 'apps/localization/Concerns/HandlesGeoQuery.php';
include_once SH_INCLUDES_PATH . 'apps/localization/LocationManager.php';

// Tema kontrolü
define('SH_THEME_EXISTS', (is_array($status = \get_sh_config('sh_theme_tasks_status', [])) && array_key_exists("copy_theme", $status)));

// Üretim ve Log Ayarları — AssetSettings üzerinden okunuyor (ACF'den taşındı)
define("ENABLE_DEVELOPMENT",     \SaltHareket\AssetManager\AssetSettings::getSetting("enable_development"));
define("ENABLE_LOGS",           ENABLE_DEVELOPMENT && \SaltHareket\AssetManager\AssetSettings::getSetting("enable_logs"));
define("ENABLE_CONSOLE_LOGS",   ENABLE_DEVELOPMENT && \SaltHareket\AssetManager\AssetSettings::getSetting("enable_console_logs"));
define("ENABLE_TWIG_CACHE",     \SaltHareket\AssetManager\AssetSettings::getSetting("enable_twig_cache"));
define("SEPERATE_CSS",          \SaltHareket\AssetManager\AssetSettings::getSetting("seperate_css"));
define("SEPERATE_JS",           \SaltHareket\AssetManager\AssetSettings::getSetting("seperate_js"));
define("INLINE_CSS",            SEPERATE_CSS && (bool) \SaltHareket\AssetManager\AssetSettings::getSetting("inline_css"));
define("INLINE_JS",             SEPERATE_JS && (bool) \SaltHareket\AssetManager\AssetSettings::getSetting("inline_js"));

$exclude_from_search = get_sh_config("options_exclude_from_search", []);
define("EXCLUDE_FROM_SEARCH", is_array($exclude_from_search) ? $exclude_from_search : []);
define("DISABLE_COMMENTS",      false);
define("ENABLE_ECOMMERCE",      class_exists("WooCommerce") && !(bool) get_sh_config('options_disable_wc_frontend', false));


// Üyelik Ayarları
// Uyelik Ayarlari — MembershipSettings okuyor (ACF fallback dahil)
define("ENABLE_MEMBERSHIP",             \SaltHareket\Membership\MembershipSettings::getSetting("enable_membership"));
define("ENABLE_MEMBERSHIP_ACTIVATION",  ENABLE_MEMBERSHIP && \SaltHareket\Membership\MembershipSettings::getSetting("enable_membership_activation"));
define("MEMBERSHIP_ACTIVATION_TYPE",    ENABLE_MEMBERSHIP_ACTIVATION ? \SaltHareket\Membership\MembershipSettings::getSetting("membership_activation_type") : "");
define("ENABLE_ACTIVATION_EMAIL_AUTOLOGIN", MEMBERSHIP_ACTIVATION_TYPE == "email" ? \SaltHareket\Membership\MembershipSettings::getSetting("enable_activation_email_autologin") : false);
$enable_registration = true;
if (ENABLE_MEMBERSHIP) {
    if (ENABLE_ECOMMERCE) {
        $enable_registration = get_sh_config("woocommerce_enable_myaccount_registration") == "yes";
    } else {
        $enable_registration = \SaltHareket\Membership\MembershipSettings::getSetting("enable_registration");
    }
}
define("ENABLE_REGISTRATION", $enable_registration);
define("ENABLE_REMEMBER_LOGIN", ENABLE_MEMBERSHIP && \SaltHareket\Membership\MembershipSettings::getSetting("enable_remember_login"));
define("ENABLE_SOCIAL_LOGIN",   ENABLE_MEMBERSHIP && class_exists("NextendSocialLogin") && \SaltHareket\Membership\MembershipSettings::getSetting("enable_social_login"));
define("ENABLE_LOST_PASSWORD",  ENABLE_MEMBERSHIP && \SaltHareket\Membership\MembershipSettings::getSetting("enable_lost_password"));
define("ENABLE_PASSWORD_RECOVER", ENABLE_MEMBERSHIP && \SaltHareket\Membership\MembershipSettings::getSetting("enable_password_recover"));

// SMS App — Login Security'den ÖNCE tanımlanmalı (ENABLE_PASSWORDLESS ve ENABLE_2FA_SMS bağımlı)
define("ENABLE_SMS", (bool) \SaltHareket\Sms\SmsSettings::get()['enabled'] ?? false);

// Login Security — ENABLE_SMS artık tanımlı
define("LOGIN_2FA_METHOD",  ENABLE_MEMBERSHIP ? (string) \SaltHareket\Membership\MembershipSettings::getSetting("login_2fa_method", "none") : "none");
define("ENABLE_2FA_SMS",    LOGIN_2FA_METHOD === "sms"  && ENABLE_SMS);
define("ENABLE_2FA_TOTP",   LOGIN_2FA_METHOD === "totp" && class_exists('\PragmaRX\Google2FAQRCode\Google2FA'));
define("ENABLE_PASSWORDLESS", ENABLE_MEMBERSHIP && ENABLE_SMS && (bool) \SaltHareket\Membership\MembershipSettings::getSetting("enable_passwordless"));
$_pw_recover_type = \SaltHareket\Membership\MembershipSettings::getSetting("password_recover_type");
define("PASSWORD_RECOVER_TYPE", (ENABLE_LOST_PASSWORD || ENABLE_PASSWORD_RECOVER) ? $_pw_recover_type : []);


// SaltReactions — Unified Favorite + Follow + Like + Bookmark sistemi
define("ENABLE_REACTIONS", ENABLE_MEMBERSHIP && \SaltHareket\Reactions\ReactionsAppSettings::getSetting("enable_reactions"));
define("ENABLE_CHAT",           ENABLE_MEMBERSHIP && class_exists("Redq_YoBro") && \SaltHareket\Membership\MembershipSettings::getSetting("enable_chat"));
define("ENABLE_NOTIFICATIONS",  ENABLE_MEMBERSHIP && \SaltHareket\Notifications\NotificationsSettings::getSetting("enable_notifications"));
define("ENABLE_SMS_NOTIFICATIONS", ENABLE_MEMBERSHIP && \SaltHareket\Notifications\NotificationsSettings::getSetting("enable_sms_notifications"));
define("ENABLE_WEB_PUSH", ENABLE_MEMBERSHIP && ENABLE_NOTIFICATIONS && \SaltHareket\Notifications\NotificationsSettings::getSetting("enable_web_push"));
// ENABLE_SMS yukarıda tanımlandı — burada tekrar tanımlanmıyor
// Video Processor App — FFmpeg tabanlı video optimizasyonu
define("ENABLE_VIDEO_PROCESSOR", (bool) get_sh_config('vp_enabled', false));

define("ENABLE_SEARCH_HISTORY", \SaltHareket\SearchHistory\SearchHistorySettings::getSetting("enable_search_history"));
define("DISABLE_REVIEW_APPROVE", \SaltHareket\Reviews\ReviewsSettings::isApproveDisabled());
define("ENABLE_REVIEWS",        (bool) \SaltHareket\Reviews\ReviewsSettings::get('general.enable_reviews'));
define("ENABLE_POSTCODE_VALIDATION", \SaltHareket\Membership\MembershipSettings::getSetting("enable_postcode_validation"));
// Download Log App
define("ENABLE_DOWNLOAD_LOG", (bool) \SaltHareket\DownloadLog\DownloadLogSettings::getSetting('enable_download_log', true));


// Lokasyon ve Bölgesel Ayarlar
// Localization — LocationSettings + LocationManager okuyor
define("ENABLE_IP2COUNTRY",     \SaltHareket\Localization\LocationSettings::getSetting("enable_ip2country"));
define("ENABLE_IP2COUNTRY_DB",  \SaltHareket\Localization\LocationSettings::getSetting("ip2country_source") === "db");
define("ENABLE_REGIONAL_POSTS", \SaltHareket\Localization\LocationManager::isRegionalActive());
define("ENABLE_LOCATION_DB",    \SaltHareket\Localization\LocationSettings::getSetting("enable_location_db"));
define("REGIONAL_POST_SETTINGS", \SaltHareket\Localization\LocationSettings::getSetting("regional_post_settings", []));


// ─── Maintenance App ─────────────────────────────────────────────────────────
// Eski underconstruction plugin'inin yerini alır.
// MaintenanceSettings variables.php'den önce yüklenmeli.
require_once SH_INCLUDES_PATH . 'apps/maintenance/MaintenanceSettings.php';
require_once SH_INCLUDES_PATH . 'apps/maintenance/Maintenance.php';

define( 'MAINTENANCE_MODE',        \SaltHareket\Maintenance\MaintenanceSettings::isActive() );
define( 'MAINTENANCE_WHITE_PAGES', \SaltHareket\Maintenance\MaintenanceSettings::getSetting( 'white_pages', [] ) );


// E-Ticaret Ek Ayarlar
// options_woo_api_local SaltConfig target_keys'de — get_sh_config ile cache'ten oku
define("ENABLE_WOO_API",        (array) get_sh_config('options_woo_api_local', []));
define("ENABLE_CART",           ENABLE_ECOMMERCE && (bool) get_sh_config('options_enable_cart', false));
define("ENABLE_FILTERS",        defined('YITH_WCAN'));
define("DISABLE_DEFAULT_CAT",   get_sh_config("options_disable_default_cat"));

// ── Product Card (tease.twig) ayarları ──────────────────────────────────────
// options_tease_settings SaltConfig cache'ten okunur — her request'te DB sorgusu yok.
(function() {
    $__tease = (array) \SaltHareket\SaltConfig::get('options_tease_settings', []);
    define("TEASE_MULTIPLE_IMAGES",  (bool)   ( $__tease['multiple_images']   ?? false ));
    define("TEASE_SHOW_VARIATIONS",  (bool)   ( $__tease['show_variations']   ?? false ));
    define("TEASE_SWATCH_TOOLTIP",   (bool)   ( $__tease['swatch_tooltip']    ?? false ));
    define("TEASE_SHOW_OOS_SWATCHES",(bool)   ( $__tease['show_oos_swatches'] ?? false ));
    define("TEASE_SWATCH_LIMIT",     (int)    ( $__tease['swatch_limit']      ?? 115   ));
    define("TEASE_SHOW_SALE_BADGE",  (bool)   ( $__tease['show_sale_badge']   ?? true  ));
    define("TEASE_SHOW_FEAT_BADGE",  (bool)   ( $__tease['show_feat_badge']   ?? true  ));
    define("TEASE_SHOW_OOS_BADGE",   (bool)   ( $__tease['show_oos_badge']    ?? true  ));
    define("TEASE_SHOW_CATEGORY",    (bool)   ( $__tease['show_category']     ?? true  ));
    define("TEASE_SHOW_PRICE",       (bool)   ( $__tease['show_price']        ?? true  ));
    define("TEASE_IMG_RATIO",        in_array( $__tease['img_ratio'] ?? '', ['1x1','4x3','3x4','16x9'], true )
                                       ? $__tease['img_ratio'] : '1x1' );
    define("TEASE_DEFAULT_SIZE",     in_array( $__tease['default_size'] ?? '', ['xs','sm','md','lg'], true )
                                       ? $__tease['default_size'] : 'md' );
    define("TEASE_BUNDLE_CTA",       in_array( $__tease['bundle_cta'] ?? '', ['view','cart'], true )
                                       ? $__tease['bundle_cta'] : 'view' );
})();

// ── Product Gallery (single-product) ayarları ────────────────────────────────
// options_gallery_settings SaltConfig cache'ten okunur.
(function() {
    $__g = (array) \SaltHareket\SaltConfig::get('options_gallery_settings', []);
    define("GALLERY_TYPE",             in_array( $__g['type']       ?? '', ['single','swiper','stack','grid'], true )
                                           ? $__g['type'] : 'swiper' );
    define("GALLERY_RATIO",            in_array( $__g['ratio']      ?? '', ['1x1','4x3','3x4','16x9','original'], true )
                                           ? $__g['ratio'] : '4x3' );
    define("GALLERY_OBJECT_FIT",       in_array( $__g['object_fit'] ?? '', ['cover','contain'], true )
                                           ? $__g['object_fit'] : 'cover' );
    define("GALLERY_HOVER",            in_array( $__g['hover']      ?? '', ['none','zoom','lightbox','both'], true )
                                           ? $__g['hover'] : 'lightbox' );
    define("GALLERY_LIGHTBOX",         (bool) ( $__g['lightbox']         ?? true  ));
    define("GALLERY_NAVIGATION",       (bool) ( $__g['navigation']       ?? true  ));
    define("GALLERY_LOOP",             (bool) ( $__g['loop']             ?? false ));
    define("GALLERY_THUMBS",           (bool) ( $__g['thumbs']           ?? true  ));
    define("GALLERY_THUMB_NAV",        (bool) ( $__g['thumb_nav']        ?? true  ));
    define("GALLERY_VARIATION_UPDATE", (bool) ( $__g['variation_update'] ?? true  ));
    define("GALLERY_PAGINATION",       in_array( $__g['pagination']     ?? '', ['none','bullets','fraction','progressbar'], true )
                                           ? $__g['pagination'] : 'bullets' );
    define("GALLERY_SLIDES_PER_VIEW",  max( 1, min( 5, (int) ( $__g['slides_per_view'] ?? 1 ) ) ));
    define("GALLERY_THUMB_COUNT",      max( 2, min( 10, (int) ( $__g['thumb_count']    ?? 4 ) ) ));
    define("GALLERY_STACK_GAP",        max( 0, min( 64, (int) ( $__g['stack_gap']     ?? 8 ) ) ));
})();


/**
 * Maintenance görünürlük kontrolü — MAINTENANCE_VISIBLE define'ı set eder.
 * Twig context'te $context["maintenance_visible"] için kullanılır.
 * Maintenance::isVisible()'a delege eder.
 */
function maintenance_check_visibility(): void {
    if ( defined( 'MAINTENANCE_VISIBLE' ) ) return;

    if ( ! MAINTENANCE_MODE ) {
        define( 'MAINTENANCE_VISIBLE', true );
        return;
    }

    if ( class_exists( '\SaltHareket\Maintenance\Maintenance' ) ) {
        // White page kontrolü dahil tam visibility check
        $visible = \SaltHareket\Maintenance\Maintenance::isVisible();
        if ( ! $visible ) {
            $page_id = \SaltHareket\Maintenance\Maintenance::resolveCurrentPageId();
            if ( $page_id > 0 && \SaltHareket\Maintenance\MaintenanceSettings::isWhitePage( $page_id ) ) {
                $visible = true;
            }
        }
        define( 'MAINTENANCE_VISIBLE', $visible );
        return;
    }

    // Fallback: sınıf yüklenememişse sadece administrator görür
    define( 'MAINTENANCE_VISIBLE', current_user_can( 'administrator' ) );
}

/*add_action('acf/init', function() {
    $regional_post_settings = [];
    if (ENABLE_REGIONAL_POSTS) {
        $regional_post_settings = \SaltHareket\get_option('options_regional_post_settings');
    }
    define("REGIONAL_POST_SETTINGS", $regional_post_settings);
});*/

/* eski hali
add_action('init', function () {
    $theme = wp_get_theme();
    define("TEXT_DOMAIN", $theme->get('TextDomain'));
    $GLOBALS["is_admin"] = is_admin();
    $GLOBALS["language"] = strtolower(substr(get_locale(), 0, 2));
    $GLOBALS["language_default"] = $GLOBALS["language"];
    $GLOBALS["post_id"] = is_singular() ? get_the_ID() : 0;
});
*/

// Timber / Twig Yapılandırması
add_action('after_setup_theme', function () use ($is_admin) {
    
    // 1. Sabit Tanımlamaları
    $theme = wp_get_theme();
    if (!defined("TEXT_DOMAIN")) {
        define("TEXT_DOMAIN", $theme->get('TextDomain'));
    }

    $is_ajax = (defined('DOING_AJAX') && DOING_AJAX);
    $timber_exists = class_exists("Timber");

    // 2. Timber Yoksa: Sadece ekranda (Front/Admin) alert ver, AJAX'ta sessiz kal.
    if (!$timber_exists) {
        if (!$is_ajax) {
            if ($is_admin) {
                add_action("admin_notices", function () {
                    echo '<div class="notice notice-error"><p>Timber not activated...</p></div>';
                });            
            }
            add_filter("template_include", function () {
                return SH_STATIC_PATH . "no-timber.html";
            });
        }
        return; 
    }

    // 3. Timber Varsa: Ayarları Yükle
    // AJAX'ta render yapıyorsan bu ayarlar (templates dizini vb.) şart!
    \Timber\Timber::$dirname = ['theme/templates', 'vendor/salthareket/theme/src/templates', 'templates'];
    \Timber\Timber::$autoescape = false;
    
    // Twig Uzantıları (Cron değilse yükle, AJAX'ta lazım olabilir)
    if (!defined('DOING_CRON') || !DOING_CRON) {
        include_once SH_INCLUDES_PATH . "plugins/twig.php"; 
        include_once SH_INCLUDES_PATH . 'twig-extends.php';
        
        if (defined('SH_THEME_EXISTS') && SH_THEME_EXISTS) {
            include_once THEME_INCLUDES_PATH . "twig-extends.php";
        }
        
        //if (class_exists('Timber_Acf_Wp_Blocks')) {
            include_once SH_INCLUDES_PATH . "plugins/timber-acf-blocks.php"; 
        //}
    }

    // 4. ACF Kontrolü (Sadece front-end render sırasında yönlendir)
    if (!class_exists("ACF") && !$is_ajax && !$is_admin) {
        add_filter("template_include", function () {
            return SH_STATIC_PATH . "no-acf.html";
        });
    }

    // 5. Maintenance Mode — template_redirect ile maintenance sayfasını serve et.
    //    AJAX ($is_ajax=true), Admin ($is_admin=true) ve REST ($is_ajax=true) isteklerinde
    //    bu hook kaydedilmez — bypass Maintenance::isVisible() içinde de var (çift güvence).
    if ( defined('MAINTENANCE_MODE') && MAINTENANCE_MODE && ! $is_ajax && ! $is_admin ) {
        add_action( 'template_redirect', [ '\SaltHareket\Maintenance\Maintenance', 'handleRequest' ], 1 );
    }

}, 1);

$multilanguage = false;
if(function_exists("qtranxf_getSortedLanguages")){
    $multilanguage = "qtranslate-xt";
    include_once SH_INCLUDES_PATH . "plugins/qtranslate-xt.php";
}elseif(class_exists('SitePress')){
    $multilanguage = "wpml";
    include_once SH_INCLUDES_PATH . "plugins/wpml.php";
}elseif(function_exists("pll_the_languages")){
    $multilanguage = "polylang";
    include_once SH_INCLUDES_PATH . "plugins/polylang.php";
}
define("ENABLE_MULTILANGUAGE", $multilanguage);
//if (ENABLE_MULTILANGUAGE){
    include_once SH_INCLUDES_PATH . "multilanguage.php";
//}
Data::set("is_admin", $is_admin);
Data::set("language", ml_get_current_language());
Data::set("language_default", ml_get_default_language());

// Membership helpers â€” WC endpoint registration iÃ§in erken yüklenmeli
if ( defined( 'SH_INCLUDES_PATH' ) && file_exists( SH_INCLUDES_PATH . 'apps/membership/helpers.php' ) ) {
    include_once SH_INCLUDES_PATH . 'apps/membership/helpers.php';
}

// Dahili Dosyalar
SaltProfiler::start('helpers/index');
include_once SH_INCLUDES_PATH . "helpers/index.php";
SaltProfiler::stop('helpers/index');
if (SH_THEME_EXISTS){// || file_exists(THEME_INCLUDES_PATH . "globals.php")) {
    include_once THEME_INCLUDES_PATH . "globals.php";
}
SaltProfiler::start('blocks');
include_once SH_INCLUDES_PATH . "blocks.php";
SaltProfiler::stop('blocks');

// class.otp.php — SmsManager yuklendikten sonra yuklenir (notifications bootstrap sonrasi)
// Bkz: notifications/bootstrap.php sonrasinda yukleniyor

// Güvenlik: Giriş yapmış normal kullanıcıları admin panelinden uzak tut
if (!ENABLE_ECOMMERCE) {
    if (!ENABLE_MEMBERSHIP && is_user_logged_in() && !$is_admin && !current_user_can('manage_options')) {
        if (!defined('DOING_AJAX') && !defined('DOING_CRON')) {
            wp_logout();
            wp_redirect(home_url());
            exit;
        }
    }
}


// Sınıf Yüklemeleri
// Reactions bootstrap her zaman yüklenir:
// - Twig helper'ları (salt_reaction_button vb.) her zaman register edilmeli,
//   aksi hâlde reactions kapalıyken tease.twig gibi şablonlar Fatal error verir.
// - WP hooks (wp_enqueue_scripts, AJAX, admin_menu) bootstrap içinde
//   ENABLE_REACTIONS || $is_admin koşuluyla korunuyor.
SaltProfiler::start('app:reactions');
include_once SH_INCLUDES_PATH . 'apps/reactions/bootstrap.php';
SaltProfiler::stop('app:reactions');

// Social Share — her zaman yükle (shortcode + action hook her sayfada kullanılabilir)
include_once SH_INCLUDES_PATH . 'apps/social-share/bootstrap.php';
// Search History bootstrap: aktifse her zaman, pasifse sadece admin'de yükle (menü görünsün)
if ( ENABLE_SEARCH_HISTORY || $is_admin ) {
    include_once SH_INCLUDES_PATH . "apps/search-history/SearchHistory.php";
    new SearchHistory();
}
SaltProfiler::start('app:reviews');
include_once SH_INCLUDES_PATH . "apps/reviews/bootstrap.php";
SaltProfiler::stop('app:reviews');

// ProductStats bootstrap: WooCommerce aktifse yükle
if ( ENABLE_ECOMMERCE ) {
    include_once SH_INCLUDES_PATH . 'apps/product-stats/bootstrap.php';
}
// SMS App bootstrap: Notifications'dan ÖNCE yüklenmeli
// SmsCarrier notifications pipeline'ında kullanılıyor, SmsManager/SmsSettings burada yükleniyor
if ( ENABLE_SMS || $is_admin ) {
    SaltProfiler::start('app:sms');
    include_once SH_INCLUDES_PATH . "apps/sms/bootstrap.php";
    SaltProfiler::stop('app:sms');
}
// Notifications bootstrap: aktifse her zaman, pasifse sadece admin'de yükle (menü görünsün)
if ( ENABLE_NOTIFICATIONS || $is_admin ) {
    SaltProfiler::start('app:notifications');
    include_once SH_INCLUDES_PATH . "apps/notifications/bootstrap.php";
    SaltProfiler::stop('app:notifications');
}
// Video Processor bootstrap: aktifse her zaman, pasifse sadece admin'de yükle (menü görünsün)
if ( ENABLE_VIDEO_PROCESSOR || $is_admin ) {
    include_once SH_INCLUDES_PATH . "apps/video-processor/bootstrap.php";
}
// Membership bootstrap: her zaman yükle â€” global fonksiyonlar (salt_my_account_links vs.)
// tema dosyaları tarafından kullanılıyor. Hook'lar ENABLE_MEMBERSHIP kontrolüne bakıyor.
include_once SH_INCLUDES_PATH . "apps/membership/bootstrap.php";
// Maintenance App bootstrap: her zaman yükle (frontend + admin)
include_once SH_INCLUDES_PATH . "apps/maintenance/bootstrap.php";
// Theme Export bootstrap: sadece admin'de yükle
if ( $is_admin ) {
    include_once SH_INCLUDES_PATH . "apps/theme-export/bootstrap.php";
}
// Download Log bootstrap — her zaman yükle (Twig helper'lar koşulsuz kayıtlı olmalı)
// ENABLE_DOWNLOAD_LOG=false iken sh_download/sh_download_attrs '' döner, stream/log işlemleri çalışmaz
include_once SH_INCLUDES_PATH . 'apps/download-log/bootstrap.php';

// Localization + Regional Posts bootstrap
if ( (defined('ENABLE_IP2COUNTRY') && ENABLE_IP2COUNTRY) || (defined('ENABLE_LOCATION_DB') && ENABLE_LOCATION_DB) || (defined('ENABLE_REGIONAL_POSTS') && ENABLE_REGIONAL_POSTS) || $is_admin ) {
    include_once SH_INCLUDES_PATH . "apps/localization/bootstrap.php";
}

// App Settings Hooks — ACF'den taşınan side effect'ler
// Admin'de yükle: settings değişiklikleri admin AJAX'ından geliyor
// Page Settings — frontend + admin'de yüklenmeli (resolve() frontend'de çalışır)
include_once SH_INCLUDES_PATH . 'apps/page-settings/bootstrap.php';

// Security App — frontend (send_headers) + admin (Security tab UI)
include_once SH_INCLUDES_PATH . 'apps/security/bootstrap.php';

if ( $is_admin ) {
    include_once SH_INCLUDES_PATH . 'apps/dashboard/bootstrap.php';
    include_once SH_INCLUDES_PATH . 'apps/general-settings/bootstrap.php';
    include_once SH_INCLUDES_PATH . 'apps/plugin-manager/bootstrap.php';
    include_once SH_INCLUDES_PATH . 'apps/hooks.php';
}
if (!empty($GLOBALS["pagenow"]) && $GLOBALS["pagenow"] === "wp-login.php") include_once SH_INCLUDES_PATH . "admin/custom-login.php";

// ACF ve Plugin Entegrasyonları
if (class_exists("ACF")) {
    Data::set("google_maps_api_key", get_sh_config("options_google_maps_api_key"));
    include_once SH_INCLUDES_PATH . "plugins/acf.php";
    if (class_exists('ACFE')) include_once SH_INCLUDES_PATH . "plugins/acfe.php";
    if (class_exists("OpenStreetMap")) include_once SH_INCLUDES_PATH . "plugins/acf-osm.php";
    if ($is_admin) {
        // ── Field Registry (FieldChoices + FieldRegistry) ────────────────
        // acf_add_field_options fonksiyonunun yerini alır.
        // Geri almak için: bu satırı comment'e alıp alttaki satırı aktive et.
        include_once SH_INCLUDES_PATH . "apps/field-registry/bootstrap.php";
        // [ESKI] include_once SH_INCLUDES_PATH . "plugins/acf-admin.php";
        // [ESKI KONUM] plugins/acf-admin.php — içinde acf_add_field_options'ın yerini yukarıdaki aldı
        // Diğer fonksiyonlar (save hooks, map koordinatları vb.) hâlâ acf-admin.php içinde:
        include_once SH_INCLUDES_PATH . "plugins/acf-admin.php";
    }
}

// Diğer Eklentiler
if (class_exists("WPCF7")) include_once SH_INCLUDES_PATH . "plugins/cf7.php";
if (defined("WPSEO_FILE")) {
    include_once SH_CLASSES_PATH . "class.schema_breadcrumbs.php";
    include_once SH_INCLUDES_PATH . "plugins/yoast-seo.php";
}
if (class_exists("Loco_Locale")) include_once SH_INCLUDES_PATH . "plugins/loco-translate.php";
if (function_exists("yasr_fs")) include_once SH_INCLUDES_PATH .  "plugins/yasr-star-rating.php";
if (class_exists("APSS_Class")) include_once SH_INCLUDES_PATH .  "plugins/apps.php";
if (class_exists("Redq_YoBro")) include_once SH_INCLUDES_PATH .  "plugins/yobro.php";
if (class_exists("Newsletter")) include_once SH_INCLUDES_PATH .  "plugins/newsletter.php";

// E-Ticaret Eklenti Kontrolleri
if (ENABLE_ECOMMERCE) {
    if (class_exists("YITH_WC_Dynamic_Discounts")) include_once SH_INCLUDES_PATH . "plugins/yith-dynamic-pricing-and-discounts.php";
    if (class_exists("YITH_WCBR")) include_once SH_INCLUDES_PATH . "plugins/yith-brands-add-on.php";
    if (function_exists('wpcbr_init')) include_once SH_INCLUDES_PATH . "plugins/wpc-brands.php";
    if (defined('YITH_WCAN')) include_once SH_INCLUDES_PATH . "plugins/yith-ajax-product-filter.php";
    if (class_exists('DGWT_WC_Ajax_Search')) include_once SH_INCLUDES_PATH . "plugins/ajax-search-for-woocommerce.php";
    if (class_exists("WC_Bundles")) include_once SH_INCLUDES_PATH . "plugins/product-bundles.php";
    if (function_exists('woosb_init')) include_once SH_INCLUDES_PATH . "plugins/wpc-product-bundles.php";
    if (class_exists("Iconic_WSSV")) include_once SH_INCLUDES_PATH . "plugins/iconic-woo-show-single-variations.php";
    if (class_exists("XT_WOOVAS")) include_once SH_INCLUDES_PATH . "plugins/xt-woo-variations-as-singles.php";
}

if (function_exists("mt_profile_img")) include_once SH_INCLUDES_PATH . "plugins/metronet-profile-picture.php";
if (defined("WP_ROCKET_VERSION")) include_once SH_INCLUDES_PATH . "plugins/wp-rocket.php";
if (class_exists("WP_Socializer")) include_once SH_INCLUDES_PATH . "plugins/wpsr.php";
//if (ENABLE_SOCIAL_LOGIN) include_once SH_INCLUDES_PATH . "plugins/nsl.php";
// Jooosi Fon (eski adı: Yabe Webfont) — plugin ismi değişti, class adı da değişti.
// Yeni: JooosiFon\Plugin | Eski (geriye uyumluluk): YABE_WEBFONT
if ( ( class_exists( 'JooosiFon\Plugin' ) || class_exists( 'YABE_WEBFONT' ) ) && $is_admin ) {
    include_once SH_INCLUDES_PATH . 'plugins/yabe-font.php';
}

if (ENABLE_DEVELOPMENT) include_once SH_INCLUDES_PATH . "minify-rules.php";

if(get_sh_config('sh_theme_tasks_status')){
    include_once SH_CLASSES_PATH . "class.encrypt.php";
    include_once SH_CLASSES_PATH . "class.paginate.php";
    include_once SH_CLASSES_PATH . "class.lcp.php";
    \Lcp::registerAjaxHooks(); // save_lcp_results AJAX — eski konum: actions.php
    include_once SH_INCLUDES_PATH . "apps/asset-manager/bootstrap.php";
}

// Admin Tarafı Sınıfları
if ($is_admin) {
    include_once SH_INCLUDES_PATH . "notices.php";
    include_once SH_CLASSES_PATH . "class.avif.php";
    // SCSSCompiler, MergeCSS, RemoveUnusedCss, AssetPacker -> asset-manager bootstrap'ta yukleniyor
    include_once SH_INCLUDES_PATH . "apps/page-assets-extractor/PageAssetsExtractor.php";
    include_once SH_CLASSES_PATH . "class.featured-image.php";
    // class.ffmpeg.php → Artık apps/video-processor/ altında (VideoProcessor sınıfı)
    include_once SH_CLASSES_PATH . "class.fluidcss.php";
    include_once SH_CLASSES_PATH . "class.columns-thumbnail.php";
    //include_once SH_CLASSES_PATH . "class.theme-export.php";
    include_once SH_INCLUDES_PATH . "actions-admin.php";  
}

// REST API save (Gutenberg) — PAE'yi lazy yükle
// REST_REQUEST variables.php yüklenirken henüz define edilmemiş olabilir
// Bu yüzden rest_api_init hook'unda yüklüyoruz
add_action('rest_api_init', function() {
    // asset-manager tools: bootstrap include_once ile yüklendiği için
    // REST_REQUEST o an false'tı ve RemoveUnusedCss atlandı — burada direkt yüklüyoruz
    if (!class_exists('SaltHareket\AssetManager\RemoveUnusedCss')) {
        $am_base = SH_INCLUDES_PATH . 'apps/asset-manager/';
        require_once $am_base . 'MergeCSS.php';
        require_once $am_base . 'AssetPacker.php';
        require_once $am_base . 'SaltMinifier.php';
        require_once $am_base . 'RemoveUnusedCss.php';
        require_once $am_base . 'SCSSCompiler.php';
    }
    if (!class_exists('PageAssetsExtractor')) {
        include_once SH_INCLUDES_PATH . "apps/page-assets-extractor/PageAssetsExtractor.php";
    }
}, 1);


// Genel Sınıflar ve Helperlar
//include_once SH_CLASSES_PATH 'classes/class.geolocation.query.php';
include_once SH_CLASSES_PATH . "class.oembed-video.php";
include_once SH_CLASSES_PATH . "class.image.php";
include_once SH_CLASSES_PATH . "class.shortcodes.php";
include_once SH_CLASSES_PATH . "class.logger.php";    

include_once SH_INCLUDES_PATH . "rewrite.php";

/*if(get_sh_config('sh_theme_tasks_status')){
    include_once SH_CLASSES_PATH . "class.encrypt.php";
    include_once SH_CLASSES_PATH . "class.paginate.php";
    include_once SH_CLASSES_PATH . "class.lcp.php";
    // Asset Manager â€” apps/asset-manager/ altına taşındı
    include_once SH_INCLUDES_PATH . "apps/asset-manager/bootstrap.php";
    // TODO: class.assets-manager.php silinecek
}*/

include_once SH_INCLUDES_PATH . "ajax.php";
include_once SH_INCLUDES_PATH . "custom.php";


// Localization + Regional Posts bootstrap â€” yukarıda zaten yüklendi
// Eski dosyalar devre dışı:
// TODO: Silinecekler: class.localization.php, class.geolocation.query.php,
//       regional-posts/index.php, admin/regional-posts/index.php, methods/localization/

if (!$is_admin) {
    include_once SH_CLASSES_PATH . "class.custom-menu-items.php";
    include_once SH_INCLUDES_PATH . "menu.php"; 
}

// Tema ve Admin Genişletmeleri
if (SH_THEME_EXISTS){// || file_exists($template_path . "/theme/index.php")) {
    include_once $template_path . "/theme/index.php";
}

if (SH_THEME_EXISTS && $is_admin) {
    include_once THEME_INCLUDES_PATH . "admin/index.php";
    /*if (!function_exists("acf_general_settings_rewrite")) {
        include_once SH_INCLUDES_PATH . "admin/general-settings/index.php";
    }*/
    if(!ENABLE_ECOMMERCE){
        add_action('admin_init', function() {
            new AdminThumbnailColumns();
        });        
    }

}

include_once SH_INCLUDES_PATH . "shortcodes.php";
include_once SH_INCLUDES_PATH . "actions.php";

/*
$GLOBALS["base_urls"] = array();
//if (ENABLE_MEMBERSHIP) {
    $GLOBALS["base_urls"] = [
        "profile" => get_account_endpoint_url("profile"),
        "account" => get_page_url("my-account"),
        "logged_url" => home_url(),
    ];
//}
*/

if(isLocalhost()){
    define('NODE_MODULES_PATH', get_home_path() .'node_modules/'); 
}else{
    define('NODE_MODULES_PATH', SH_STATIC_PATH .'node_modules/'); 
}

if (defined('SH_THEME_EXISTS') && SH_THEME_EXISTS) {
    if (class_exists('\Salt')) {
        $salt = \Salt::get_instance();
    }
} else {
    if (class_exists('\SaltBase')) {
        $salt = \SaltBase::get_instance();
    }
}
if ( ! isset( $salt ) ) {
    $salt = null;
}
Data::set("salt", $salt);

// ─── SaltProfiler — Checkpoint hook'ları ─────────────────────────────────────
// Aktif etmek için wp-config.php'ye ekle: define('SALT_PROFILER', true);
if ( defined('SALT_PROFILER') && SALT_PROFILER && class_exists('SaltProfiler') && SaltProfiler::isEnabled() ) {

    // WP boot checkpoint'leri
    add_action( 'after_setup_theme', fn() => SaltProfiler::mark('after_setup_theme'), PHP_INT_MIN );
    add_action( 'init',              fn() => SaltProfiler::mark('init'),              PHP_INT_MIN );
    add_action( 'init',              fn() => SaltProfiler::mark('init: end'),         PHP_INT_MAX );
    add_action( 'admin_init',        fn() => SaltProfiler::mark('admin_init: start'), PHP_INT_MIN );
    add_action( 'admin_init',        fn() => SaltProfiler::mark('admin_init: end'),   PHP_INT_MAX );
    add_action( 'wp',                fn() => SaltProfiler::mark('wp'),                PHP_INT_MIN );
    add_action( 'template_redirect', fn() => SaltProfiler::mark('template_redirect'), PHP_INT_MIN );
    add_action( 'wp_loaded',         fn() => SaltProfiler::mark('wp_loaded'),         PHP_INT_MIN );
    add_action( 'current_screen',    fn() => SaltProfiler::mark('current_screen'),    PHP_INT_MIN );
    add_action( 'rest_api_init',     fn() => SaltProfiler::mark('rest_api_init'),     PHP_INT_MIN );
    add_action( 'admin_enqueue_scripts', fn() => SaltProfiler::mark('admin_enqueue_scripts'), PHP_INT_MIN );
    add_action( 'admin_head',        fn() => SaltProfiler::mark('admin_head: start'), PHP_INT_MIN );
    add_action( 'admin_head',        fn() => SaltProfiler::mark('admin_head: end'),   PHP_INT_MAX );

    // block_editor_rest_api_preload — profiler mark'ları
    add_filter( 'block_editor_rest_api_preload_paths', function( $paths ) {
        \SaltProfiler::mark( 'rest_preload: paths filter (' . count($paths) . ' paths)' );
        \SaltProfiler::mark( 'rest_preload: after filter (' . count($paths) . ' remain)' );
        return $paths;
    }, PHP_INT_MIN );
    add_action( 'admin_enqueue_scripts', fn() => SaltProfiler::mark('before admin_enqueue_scripts'), PHP_INT_MIN - 1 );

    // Timber context süresi
    add_filter('timber/context', function($ctx) { SaltProfiler::start('timber/context'); return $ctx; }, 1);
    add_filter('timber/context', function($ctx) { SaltProfiler::stop('timber/context');  return $ctx; }, 9999);

    // Timber render bitti
    $profiler_reported = false;
    add_action( 'timber/output', function( $output ) use ( &$profiler_reported ) {
        if ( $profiler_reported ) return $output;
        $profiler_reported = true;
        SaltProfiler::mark('timber/output done');
        return $output;
    }, PHP_INT_MAX );
}

define('VARIABLES_LOADED', true);
