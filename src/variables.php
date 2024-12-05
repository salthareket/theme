<?php

define("PUBLISH_URL", get_option("options_publish_url"));
define("ENABLE_PUBLISH", !empty(PUBLISH_URL) && get_option("options_enable_publish"));

define("ENABLE_PRODUCTION", !ENABLE_PUBLISH && get_option("options_enable_production"));
define("ENABLE_LOGS", ENABLE_PRODUCTION && get_option("options_enable_logs"));
define("ENABLE_CONSOLE_LOGS", ENABLE_PRODUCTION && get_option("options_enable_console_logs"));

$exclude_from_search = get_option("options_exclude_from_search");
$exclude_from_search = $exclude_from_search?$exclude_from_search:[];
define("EXCLUDE_FROM_SEARCH", $exclude_from_search);

define("DISABLE_COMMENTS", true);
define("DISABLE_REVIEW_APPROVE", get_option("options_disable_review_approve"));
define("ENABLE_SEARCH_HISTORY", get_option("options_enable_search_history"));

define("ENABLE_ECOMMERCE", class_exists("WooCommerce"));

define("ENABLE_MEMBERSHIP", get_option("options_enable_membership"));
define("ENABLE_MEMBERSHIP_ACTIVATION", ENABLE_MEMBERSHIP && get_option("options_enable_membership_activation"));
define("MEMBERSHIP_ACTIVATION_TYPE", ENABLE_MEMBERSHIP_ACTIVATION?get_option("options_membership_activation_settings"):"");
define("ENABLE_ACTIVATION_EMAIL_AUTOLOGIN", MEMBERSHIP_ACTIVATION_TYPE == "email" ? get_option("options_enable_activation_email_autologin") : false);

$enable_registration = true;
if(ENABLE_MEMBERSHIP){
   if(ENABLE_ECOMMERCE){
      $enable_registration = get_option("woocommerce_enable_myaccount_registration")=="yes"?true:false;
   }else{
      $enable_registration = get_option("options_enable_registration");
   }
}
define("ENABLE_REGISTRATION", $enable_registration);

define("ENABLE_REMEMBER_LOGIN",  ENABLE_MEMBERSHIP && get_option("options_enable_remember_login"));
define("ENABLE_SOCIAL_LOGIN",  ENABLE_MEMBERSHIP && class_exists("NextendSocialLogin") && get_option("options_enable_social_login"));

define("ENABLE_LOST_PASSWORD",  ENABLE_MEMBERSHIP && get_option("options_enable_lost_password"));
define("ENABLE_PASSWORD_RECOVER",  ENABLE_MEMBERSHIP && get_option("options_enable_password_recover"));
define("PASSWORD_RECOVER_TYPE", ENABLE_LOST_PASSWORD||ENABLE_PASSWORD_RECOVER?get_option("options_password_recover_settings"):array());

define("ENABLE_FAVORITES", ENABLE_MEMBERSHIP && get_option("options_enable_favorites"));
$favorite_types = array(
    "post_types" => array(),
    "taxonomies" => array(),
    "roles"      => array()
);
if(ENABLE_FAVORITES){
    $favorite_post_types = get_option("options_favorite_types_post_types");
    if($favorite_post_types){
       $favorite_types["post_types"] = $favorite_post_types;
    }
    $favorite_taxonomies = get_option("options_favorite_types_taxonomies");
    if($favorite_taxonomies){
       $favorite_types["taxonomies"] = $favorite_taxonomies;
    }
    $favorite_user_roles = get_option("options_favorite_types_user_roles");
    if($favorite_user_roles){
       $favorite_types["roles"] = $favorite_user_roles;
    }
}
define("FAVORITE_TYPES", $favorite_types);
define("ENABLE_FOLLOW", ENABLE_MEMBERSHIP && get_option("options_enable_follow"));
$follow_types = array(
    "post_types" => array(),
    "taxonomies" => array(),
    "roles"      => array()
);
if(ENABLE_FOLLOW){
    $follow_post_types = get_option("options_follow_types_post_types");
    if($follow_post_types){
       $follow_types["post_types"] = $follow_post_types;
    }
    $follow_taxonomies = get_option("options_follow_types_taxonomies");
    if($follow_taxonomies){
       $follow_types["taxonomies"] = $follow_taxonomies;
    }
    $follow_user_roles = get_option("options_follow_types_user_roles");
    if($follow_user_roles){
       $follow_types["roles"] = $follow_user_roles;
    }
}
define("FOLLOW_TYPES", $follow_types);
define("ENABLE_CHAT", ENABLE_MEMBERSHIP && class_exists("Redq_YoBro") && get_option("options_enable_chat"));
define("ENABLE_NOTIFICATIONS", ENABLE_MEMBERSHIP && get_option("options_enable_notifications"));
define("ENABLE_SMS_NOTIFICATIONS", ENABLE_MEMBERSHIP && get_option("options_enable_sms_notifications"));

define("ENABLE_ROLE_THEMES", ENABLE_MEMBERSHIP && get_option("options_role_themes") && is_user_logged_in());

define("ENABLE_IP2COUNTRY", get_option("options_enable_ip2country"));
define("ENABLE_IP2COUNTRY_DB", get_option("options_ip2country_settings")=="db"?true:false);
define("ENABLE_REGIONAL_POSTS", ENABLE_IP2COUNTRY && get_option("options_enable_regional_posts"));
add_action('acf/init', function(){
    $regional_post_settings = array();
    if(ENABLE_REGIONAL_POSTS){
       $regional_post_settings = get_field("regional_post_settings", "option");
    }
    define("REGIONAL_POST_SETTINGS", $regional_post_settings);
});

define("ENABLE_LOCATION_DB", get_option("options_enable_location_db"));

define("ACTIVATE_UNDER_CONSTRUCTION", get_option("underConstructionActivationStatus"));
$white_pages = get_option("options_white_pages");
define("WHITE_PAGES_UNDER_CONSTRUCTION", is_array($white_pages)?$white_pages:array());
function visibility_under_construction(){
    if(defined("VISIBILITY_UNDER_CONSTRUCTION")){
        return;
    }
    $page_id = url_to_postid(current_url()); 
    $visibility_under_construction = false;
    if(isset($GLOBALS["user"]->ID)){
        if($GLOBALS["user"]->get_role() != "administrator"){
            if(ACTIVATE_UNDER_CONSTRUCTION){
               if(in_array($page_id, WHITE_PAGES_UNDER_CONSTRUCTION)){
                    $visibility_under_construction = true;
                }
            }else{
                $visibility_under_construction = true;
            }  
        }else{
            $visibility_under_construction = true;
        }        
    }else{
        if(ACTIVATE_UNDER_CONSTRUCTION){
            if(in_array($page_id, WHITE_PAGES_UNDER_CONSTRUCTION)){
                $visibility_under_construction = true;
            }
        }else{
            $visibility_under_construction = true;
        }
    }
    define("VISIBILITY_UNDER_CONSTRUCTION", $visibility_under_construction);
    add_filter( 'option_underConstructionActivationStatus', function( $status ){
        if($status == "1"){
            if(VISIBILITY_UNDER_CONSTRUCTION && !is_admin()){
                $status = "0";
            }
        }
        return $status;
    });
}

define("ENABLE_WOO_API", get_option("options_enable_woo_api"));
define("ENABLE_CART", ENABLE_ECOMMERCE && get_option("options_enable_cart"));
define("PAYMENT_EXPIRE_HOURS", get_option("options_payment_expire_hours"));
define("ENABLE_FILTERS", defined( 'YITH_WCAN' ));
define("DISABLE_DEFAULT_CAT", true);
define("ENABLE_POSTCODE_VALIDATION", get_option("options_enable_postcode_validation"));

$multilanguage = false;
if(function_exists("qtranxf_getSortedLanguages")){
    $multilanguage = "qtranslate-xt";
    include_once "includes/plugins/qtranslate-xt.php";
}elseif(class_exists('SitePress')){
    $multilanguage = "wpml";
    include_once "includes/plugins/wpml.php";
}elseif(function_exists("pll_the_languages")){
    $multilanguage = "polylang";
    include_once "includes/plugins/polylang.php";
}
define("ENABLE_MULTILANGUAGE", $multilanguage);
if (ENABLE_MULTILANGUAGE){
    include_once "includes/multilanguage.php";
}

define("ENCRYPT_SECRET_KEY", "gV6QaS3zRm4Ei8NkXw0Lp1bBfDy5hTjY");

$theme = wp_get_theme();
define("TEXT_DOMAIN", $theme->get('TextDomain'));
$GLOBALS["is_admin"] = is_admin();
$GLOBALS["language"] = strtolower(substr(get_locale(), 0, 2));

$GLOBALS["post_id"] = get_the_ID();

if (class_exists("acf")) {
    $GLOBALS["google_maps_api_key"] = get_option("options_google_maps_api_key"); //get_post_meta
}

if (!class_exists("Timber")) {
    add_action("admin_notices", function () {
        echo '<div class="alert alert-danger text-center"><p>Timber not activated. Make sure you activate the plugin in <a href="' .
            esc_url(admin_url("plugins.php#timber")) .
            '">' .
            esc_url(admin_url("plugins.php")) .
            "</a></p></div>";
    });
    add_filter("template_include", function ($template) {
        return get_stylesheet_directory() . "/static/no-timber.html";
    });
    return;
} else {
    Timber::$dirname = array( 'theme/templates', 'templates' );
    Timber::$autoescape = false;
    Timber::$cache = false;
    include_once "includes/plugins/twig.php"; 
    include_once 'includes/twig-extends.php';
    include_once "theme/includes/twig-extends.php";
    if ( class_exists( 'Timber_Acf_Wp_Blocks' ) ) {
        include_once "includes/plugins/timber-acf-blocks.php"; 
    }
}

$required_plugins = array(
    'acf-extended/acf-extended.php',
    'contact-form-7/wp-contact-form-7.php',
    'post-smtp/postman-smtp.php',
    'favicon-by-realfavicongenerator/favicon-by-realfavicongenerator.php',
    'featured-image-admin-thumb-fiat/featured-image-admin-thumb.php',
    'google-site-kit/google-site-kit.php',
    'post-type-archive-links/post-type-archive-links.php',
    'simple-custom-post-order/simple-custom-post-order.php',
    //'webp-converter-for-media/webp-converter-for-media.php',
    'yabe-webfont/yabe-webfont.php',
    'wordpress-seo/wp-seo.php',
);
if(ACTIVATE_UNDER_CONSTRUCTION){
    $required_plugins[] = 'underconstruction/underConstruction.php';
}
if(ENABLE_MEMBERSHIP){
    $required_plugins[] = 'one-user-avatar/one-user-avatar.php';
}
if(ENABLE_PUBLISH){
    $required_plugins[] = 'wp-scss/wp-scss.php';
}
$GLOBALS["plugins"] = $required_plugins;

include_once "includes/helpers/index.php";
include_once "theme/includes/globals.php";
include_once "includes/blocks.php";
include_once "includes/styles-scripts.php";
//include_once "includes/install-plugins.php";

if (ENABLE_MEMBERSHIP) {
   include_once "classes/class.otp.php";
}

if (!ENABLE_ECOMMERCE) {
    $current_page = $_SERVER['REQUEST_URI']; 
    $admin_path = '/wp-admin/';
    if (defined('WP_ADMIN_DIR')) {
        $admin_path = '/' . trim(WP_ADMIN_DIR, '/') . '/';
    }
    if (!ENABLE_MEMBERSHIP && is_user_logged_in() && !is_admin() && strpos($current_page, $admin_path) === false && !current_user_can('manage_options')) {
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }
        if (defined('DOING_CRON') && DOING_CRON) {
            return;
        }
        wp_logout();
        wp_redirect(home_url());
        exit;
    }
}

if (ENABLE_FAVORITES) {
    include_once "classes/class.favorites.php";
}

if (ENABLE_SEARCH_HISTORY) {
    include_once "classes/class.search-history.php";
}

if (ENABLE_NOTIFICATIONS) {
    include_once "classes/class.notifications.php";
}

if ($GLOBALS["pagenow"] === "wp-login.php") {
    include_once "includes/admin/custom-login.php";
}

if (is_admin()) {
    include_once "includes/admin/index.php";
    if(!function_exists("acf_general_settings_rewrite")){
        include_once "includes/admin/general-settings/index.php";
    }
}

if (class_exists("ACF")) {
    include_once "includes/plugins/acf.php";
    if(class_exists('ACFE')){
       include_once "includes/plugins/acfe.php";
    }
    if(class_exists("OpenStreetMap")){
        die;
        include_once "includes/plugins/acf-osm.php";
    }
}else{
    //include_once "includes/plugins/acf-fallback.php";
}

if (class_exists("WPCF7")) {
    include_once "includes/plugins/cf7.php";
}

if (defined("WPSEO_FILE")) {
    include_once "classes/class.schema_breadcrumbs.php";
    include_once "includes/plugins/yoast-seo.php";
}

if (class_exists("Loco_Locale")) {
    include_once "includes/plugins/loco-translate.php";
}

if (function_exists("yasr_fs")) {
    include_once "includes/plugins/yasr-star-rating.php";
}

if (class_exists("APSS_Class")) {
    include_once "includes/plugins/apps.php";
}

if (class_exists("Redq_YoBro")) {
    include_once "includes/plugins/yobro.php";
}

if (class_exists("Newsletter")) {
    include_once "includes/plugins/newsletter.php";
}

if (ENABLE_ECOMMERCE) {
    if (class_exists("YITH_WC_Dynamic_Discounts")) {
        include_once "includes/plugins/yith-dynamic-pricing-and-discounts.php";
    }

    if (class_exists("YITH_WCBR")) {
        include_once "includes/plugins/yith-brands-add-on.php";
    }

    if ( function_exists( 'wpcbr_init' ) ) {
        include_once "includes/plugins/wpc-brands.php";
    }

    if ( defined( 'YITH_WCAN' ) ) {
        include_once "includes/plugins/yith-ajax-product-filter.php";
    }

    if ( class_exists( 'DGWT_WC_Ajax_Search' )){
        include_once "includes/plugins/ajax-search-for-woocommerce.php";
    }

    if (class_exists("WC_Bundles")) {
        include_once "includes/plugins/product-bundles.php";
    }

    if ( function_exists( 'woosb_init' ) ) {
        include_once "includes/plugins/wpc-product-bundles.php";
    }
}

if (function_exists("mt_profile_img")) {
    include_once "includes/plugins/metronet-profile-picture.php";
}

if (defined("WP_ROCKET_VERSION")) {
    include_once "includes/plugins/wp-rocket.php";
}
if (class_exists("WP_Socializer")) {
    include_once "includes/plugins/wpsr.php";
}

if(ENABLE_SOCIAL_LOGIN){
    include_once "includes/plugins/nsl.php";
}

if (class_exists("YABE_WEBFONT")) {
    include_once "includes/plugins/yabe-font.php";
}

if (!function_exists("get_home_path")) {
    include_once ABSPATH . "/wp-admin/includes/file.php";
}

if (ENABLE_PRODUCTION) {
    include_once "theme/includes/minify-rules.php";
}

include_once "classes/class.shortcodes.php";
include_once "classes/class.logger.php";    
include_once "classes/class.encrypt.php";
include_once "classes/class.paginate.php";
include_once "classes/class.localization.php";
include_once "classes/class.page-assets-extractor.php"; 
//include 'classes/class.geolocation.query.php';


include_once "includes/actions.php";
include_once "includes/notices.php";
include_once "includes/rewrite.php";
include_once "includes/ajax.php";
include_once "includes/custom.php";
echo "kkoook";

if(!is_admin()){
   include_once "classes/class.custom-menu-items.php";
   include_once "includes/menu.php"; 
}

if(ENABLE_REGIONAL_POSTS){
    include_once "includes/regional-posts/index.php";
}

if (ENABLE_ECOMMERCE) {
    if (ENABLE_MEMBERSHIP) {
        include_once "includes/woocommerce/redirect.php";
        include_once "includes/woocommerce/my-account.php";
    }
    include_once "includes/woocommerce/functions.php";
    //include_once "includes/woocommerce.php";
}

// extend with theme files
include_once "theme/index.php";
include_once "includes/shortcodes.php";
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