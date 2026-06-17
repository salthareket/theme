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
include_once SH_CLASSES_PATH."class.query-cache.php";

// App Settings — define()'lardan ÖNCE yüklenmeli
include_once SH_INCLUDES_PATH . 'apps/membership/MembershipSettings.php';
include_once SH_INCLUDES_PATH . 'apps/notifications/NotificationsSettings.php';
include_once SH_INCLUDES_PATH . 'apps/reactions/ReactionsAppSettings.php';
include_once SH_INCLUDES_PATH . 'apps/search-history/SearchHistorySettings.php';
include_once SH_INCLUDES_PATH . 'apps/reviews/ReviewsSettings.php';
include_once SH_INCLUDES_PATH . 'apps/localization/LocationSettings.php';
include_once SH_INCLUDES_PATH . 'apps/localization/Schema/LocationSchema.php';
include_once SH_INCLUDES_PATH . 'apps/localization/Concerns/HandlesGeo.php';
include_once SH_INCLUDES_PATH . 'apps/localization/Concerns/HandlesIpGeo.php';
include_once SH_INCLUDES_PATH . 'apps/localization/Concerns/HandlesRegionalPosts.php';
include_once SH_INCLUDES_PATH . 'apps/localization/Concerns/HandlesGeoQuery.php';
include_once SH_INCLUDES_PATH . 'apps/localization/LocationManager.php';

function get_sh_config($key, $default = false) {
    return \SaltHareket\SaltConfig::get($key, $default);
}

// Tema kontrolü
define('SH_THEME_EXISTS', (is_array($status = get_sh_config('sh_theme_tasks_status', [])) && array_key_exists("copy_theme", $status)));

// Üretim ve Log Ayarları
define("ENABLE_PRODUCTION",     get_sh_config("options_enable_production"));
define("ENABLE_LOGS",           ENABLE_PRODUCTION && get_sh_config("options_enable_logs"));
define("ENABLE_CONSOLE_LOGS",   ENABLE_PRODUCTION && get_sh_config("options_enable_console_logs"));
define("ENABLE_TWIG_CACHE",     get_sh_config("options_enable_twig_cache"));
define("SEPERATE_CSS",          get_sh_config("options_seperate_css"));
define("SEPERATE_JS",           get_sh_config("options_seperate_js"));
define("INLINE_CSS",            SEPERATE_CSS && (bool) get_sh_config("options_inline_css", 0));
define("INLINE_JS",             SEPERATE_JS && (bool) get_sh_config("options_inline_js", 0));

$exclude_from_search = get_sh_config("options_exclude_from_search", []);
define("EXCLUDE_FROM_SEARCH", is_array($exclude_from_search) ? $exclude_from_search : []);
define("DISABLE_COMMENTS",      false);
define("ENABLE_ECOMMERCE",      class_exists("WooCommerce"));


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
$_pw_recover_type = \SaltHareket\Membership\MembershipSettings::getSetting("password_recover_type");
define("PASSWORD_RECOVER_TYPE", (ENABLE_LOST_PASSWORD || ENABLE_PASSWORD_RECOVER) ? $_pw_recover_type : []);


// SaltReactions — Unified Favorite + Follow + Like + Bookmark sistemi
define("ENABLE_REACTIONS", ENABLE_MEMBERSHIP && \SaltHareket\Reactions\ReactionsAppSettings::getSetting("enable_reactions"));
define("ENABLE_CHAT",           ENABLE_MEMBERSHIP && class_exists("Redq_YoBro") && \SaltHareket\Membership\MembershipSettings::getSetting("enable_chat"));
define("ENABLE_NOTIFICATIONS",  ENABLE_MEMBERSHIP && \SaltHareket\Notifications\NotificationsSettings::getSetting("enable_notifications"));
define("ENABLE_SMS_NOTIFICATIONS", ENABLE_MEMBERSHIP && \SaltHareket\Notifications\NotificationsSettings::getSetting("enable_sms_notifications"));
define("ENABLE_WEB_PUSH", ENABLE_MEMBERSHIP && ENABLE_NOTIFICATIONS && \SaltHareket\Notifications\NotificationsSettings::getSetting("enable_web_push"));

define("ENABLE_SEARCH_HISTORY", \SaltHareket\SearchHistory\SearchHistorySettings::getSetting("enable_search_history"));
define("DISABLE_REVIEW_APPROVE", \SaltHareket\Reviews\ReviewsSettings::isApproveDisabled());
define("ENABLE_POSTCODE_VALIDATION", \SaltHareket\Membership\MembershipSettings::getSetting("enable_postcode_validation"));


// Lokasyon ve Bölgesel Ayarlar
// Localization — LocationSettings + LocationManager okuyor
define("ENABLE_IP2COUNTRY",     \SaltHareket\Localization\LocationSettings::getSetting("enable_ip2country"));
define("ENABLE_IP2COUNTRY_DB",  \SaltHareket\Localization\LocationSettings::getSetting("ip2country_source") === "db");
define("ENABLE_REGIONAL_POSTS", \SaltHareket\Localization\LocationManager::isRegionalActive());
define("ENABLE_LOCATION_DB",    \SaltHareket\Localization\LocationSettings::getSetting("enable_location_db"));
define("REGIONAL_POST_SETTINGS", \SaltHareket\Localization\LocationSettings::getSetting("regional_post_settings", []));


// Under Construction Ayarları
define("ACTIVATE_UNDER_CONSTRUCTION", get_sh_config("underConstructionActivationStatus"));
$white_pages = get_sh_config("options_white_pages", []);
define("WHITE_PAGES_UNDER_CONSTRUCTION", is_array($white_pages) ? $white_pages : []);


// E-Ticaret Ek Ayarlar
define("ENABLE_WOO_API",        get_sh_config("options_enable_woo_api"));
define("ENABLE_CART",           ENABLE_ECOMMERCE && get_sh_config("options_enable_cart"));
define("ENABLE_FILTERS",        defined('YITH_WCAN'));
define("DISABLE_DEFAULT_CAT",   get_sh_config("options_disable_default_cat"));


/**
 * Bakım modu görünürlük kontrolü
 */
function visibility_under_construction() {
    if (defined("VISIBILITY_UNDER_CONSTRUCTION")) return;

    // 1. Ã–NCE PLUGIN AKTÄ°F MÄ° ONA BAKALIM
    // ACTIVATE_UNDER_CONSTRUCTION zaten yukarıda bir yerlerde get_option ile tanımlanmış olmalı.
    // Eğer plugin pasifse, hiÃ§ post_id veya whitelist kontrolüne girmeden Ã§ıkalım.
    if (!ACTIVATE_UNDER_CONSTRUCTION) {
        define("VISIBILITY_UNDER_CONSTRUCTION", true);
        return;
    }

    // 2. ADMIN KONTROLÜ (Ã‡ok hızlıdır, DB yormaz)
    if (current_user_can("administrator")) {
        define("VISIBILITY_UNDER_CONSTRUCTION", true);
        return;
    }

    // 3. WHITELIST KONTROLÜ (Sadece gerekliyse url_to_postid Ã§alıştır)
    // Eğer whitelist boşsa veya ana sayfadaysak url_to_postid'ye gerek kalmayabilir
    $visible = false;
    
    // Global post objesi varsa url_to_postid'ye gerek kalmaz, direkt ID'yi alırız
    global $post;
    if (isset($post->ID) && $post->ID > 0) {
        $page_id = $post->ID;
    } elseif (function_exists('url_to_postid') && function_exists('current_url')) {
        $page_id = url_to_postid(current_url());
    } else {
        $page_id = 0;
    }

    if ($page_id > 0 && in_array($page_id, WHITE_PAGES_UNDER_CONSTRUCTION)) {
        $visible = true;
    }

    define("VISIBILITY_UNDER_CONSTRUCTION", $visible);
    
    // Pluginin davranışını manipüle et
    add_filter("option_underConstructionActivationStatus", function ($status) use ($visible) {
        return ($status == "1" && $visible) ? "0" : $status;
    });
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
        
        if (class_exists('Timber_Acf_Wp_Blocks')) {
            include_once SH_INCLUDES_PATH . "plugins/timber-acf-blocks.php"; 
        }
    }

    // 4. ACF Kontrolü (Sadece front-end render sırasında yönlendir)
    if (!class_exists("ACF") && !$is_ajax && !$is_admin) {
        add_filter("template_include", function () {
            return SH_STATIC_PATH . "no-acf.html";
        });
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
include_once SH_INCLUDES_PATH . "helpers/index.php";
if (SH_THEME_EXISTS){// || file_exists(THEME_INCLUDES_PATH . "globals.php")) {
    include_once THEME_INCLUDES_PATH . "globals.php";
}
include_once SH_INCLUDES_PATH . "blocks.php";

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
if (ENABLE_REACTIONS || $is_admin) {
    include_once SH_INCLUDES_PATH . 'apps/reactions/bootstrap.php';
}
if (ENABLE_SEARCH_HISTORY) include_once SH_INCLUDES_PATH . "apps/search-history/SearchHistory.php";
include_once SH_INCLUDES_PATH . "apps/reviews/bootstrap.php";
// Notifications bootstrap: aktifse her zaman, pasifse sadece admin'de yükle (menü görünsün)
if ( ENABLE_NOTIFICATIONS || $is_admin ) {
    include_once SH_INCLUDES_PATH . "apps/notifications/bootstrap.php";
}
// Membership bootstrap: her zaman yükle â€” global fonksiyonlar (salt_my_account_links vs.)
// tema dosyaları tarafından kullanılıyor. Hook'lar ENABLE_MEMBERSHIP kontrolüne bakıyor.
include_once SH_INCLUDES_PATH . "apps/membership/bootstrap.php";
// Theme Export bootstrap: sadece admin'de yükle
if ( $is_admin ) {
    include_once SH_INCLUDES_PATH . "apps/theme-export/bootstrap.php";
}
// Download Log bootstrap
include_once SH_INCLUDES_PATH . 'apps/download-log/bootstrap.php';

// Localization + Regional Posts bootstrap
if ( (defined('ENABLE_IP2COUNTRY') && ENABLE_IP2COUNTRY) || (defined('ENABLE_LOCATION_DB') && ENABLE_LOCATION_DB) || (defined('ENABLE_REGIONAL_POSTS') && ENABLE_REGIONAL_POSTS) || $is_admin ) {
    include_once SH_INCLUDES_PATH . "apps/localization/bootstrap.php";
}

// App Settings Hooks — ACF'den taşınan side effect'ler
// Admin'de yükle: settings değişiklikleri admin AJAX'ından geliyor
if ( $is_admin ) {
    include_once SH_INCLUDES_PATH . 'apps/hooks.php';
}
if (!empty($GLOBALS["pagenow"]) && $GLOBALS["pagenow"] === "wp-login.php") include_once SH_INCLUDES_PATH . "admin/custom-login.php";

// ACF ve Plugin Entegrasyonları
if (class_exists("ACF")) {
    Data::set("google_maps_api_key", get_sh_config("options_google_maps_api_key"));
    include_once SH_INCLUDES_PATH . "plugins/acf.php";
    if (class_exists('ACFE')) include_once SH_INCLUDES_PATH . "plugins/acfe.php";
    if (class_exists("OpenStreetMap")) include_once SH_INCLUDES_PATH . "plugins/acf-osm.php";
    if ($is_admin) include_once SH_INCLUDES_PATH . "plugins/acf-admin.php";
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
if (class_exists("YABE_WEBFONT") && $is_admin) include_once SH_INCLUDES_PATH . "plugins/yabe-font.php";

if (ENABLE_PRODUCTION) include_once SH_INCLUDES_PATH . "minify-rules.php";

if(get_sh_config('sh_theme_tasks_status')){
    include_once SH_CLASSES_PATH . "class.encrypt.php";
    include_once SH_CLASSES_PATH . "class.paginate.php";
    include_once SH_CLASSES_PATH . "class.lcp.php";
    include_once SH_INCLUDES_PATH . "apps/asset-manager/bootstrap.php";
}

// Admin Tarafı Sınıfları
if ($is_admin) {
    include_once SH_INCLUDES_PATH . "notices.php";
    include_once SH_CLASSES_PATH . "class.avif.php";
    // SCSSCompiler, MergeCSS, RemoveUnusedCss, AssetPacker -> asset-manager bootstrap'ta yukleniyor
    include_once SH_INCLUDES_PATH . "apps/page-assets-extractor/PageAssetsExtractor.php";
    include_once SH_CLASSES_PATH . "class.featured-image.php";
    include_once SH_CLASSES_PATH . "class.ffmpeg.php";
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

// Download Log bootstrap
include_once SH_INCLUDES_PATH . 'apps/download-log/bootstrap.php';

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

define('VARIABLES_LOADED', true);
