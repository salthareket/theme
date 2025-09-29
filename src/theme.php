<?php
namespace SaltHareket;

use ScssPhp\SCSSPhp\SCSSCompiler;
use ScssPhp\ScssPhp\OutputStyle;

Class Theme{

    /*
    Bunu kullanırsak kullanım: \SaltBase::getInstance()->
    private static $instance = null;
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }*/

    function __construct(){
        show_admin_bar(false); 
        add_action("after_setup_theme", [$this, "after_setup_theme"]);

        add_action("acf/init", [$this, "menu_actions"]);

        //add_action("init", [$this, "after_setup_theme"]);

        add_action("wp", [$this, "language_settings"]);

        add_action("init", [$this, "global_variables"]);
        add_action("init", [$this, "language_settings"], 1);  
        add_action("init", [$this, "increase_memory_limit"]);
        add_action("init", [$this, "register_post_types"]);
        add_action("init", [$this, "register_taxonomies"]);

        //add_action("wp", [$this, "site_assets"], 1);
        add_action("template_redirect", [$this, "site_assets"], 1);

        add_action("plugins_loaded", [$this, "plugins_loaded"]);
        
        add_action("pre_get_posts", [$this, "query_all_posts"], 10);
        add_filter('get_terms_args', [$this, "query_all_terms"], 10, 2);

        // Sayfalardaki bazı gereksiz ve kullanılmayan bölümlerin kaldırılması
        remove_action('wp_head', 'wp_pingback'); // Pingback linki
        remove_action('wp_head', 'feed_links', 2); // Genel feed linkleri
        remove_action('wp_head', 'feed_links_extra', 3); // Ek feed linkleri (Kategori, Yazar, vb.)
        remove_action('wp_head', 'rsd_link'); // Really Simple Discovery (RSD) linki
        remove_action('wp_head', 'wlwmanifest_link'); // Windows Live Writer manifest linki
        remove_action('wp_head', 'wp_shortlink_wp_head'); // Kısa link (shortlink) linki
        remove_action('wp_head', 'wp_generator'); // WordPress sürüm bilgisi
        remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0); // Önceki ve sonraki yazı linkleri
        remove_action('wp_head', 'wp_oembed_add_discovery_links'); // OEmbed discovery linkleri
        remove_action('admin_color_scheme_picker', 'admin_color_scheme_picker' );

        // WordPress 5.4 ve sonraki sürümler için gizleme
        remove_action('wp_head', 'rest_output_link_wp_head', 10);
        remove_action('wp_head', 'wp_resource_hints', 2);

        if(ENABLE_ECOMMERCE){
            remove_action( 'wp_head', 'wc_generator_tag' ); // WooCommerce sürüm bilgisi
            remove_action( 'wp_head', 'wc_add_generator_meta_tag' ); // WooCommerce meta tag
            remove_action( 'wp_head', 'woocommerce_output_all_notices', 10 ); // WooCommerce hata mesajları
            remove_action( 'wp_head', 'wc_robots' ); // WooCommerce robots meta tag
            remove_action( 'wp_head', 'wc_oembed_add_admin_links' ); // WooCommerce oEmbed linkleri
            remove_action( 'wp_head', 'wc_oembed_add_discovery_links' ); // WooCommerce oEmbed discovery linkleri
            add_filter('woocommerce_template_path', [$this, 'wc_custom_template_path']);
            //add_filter('woocommerce_locate_template', [$this, 'wc_multiple_template_paths'], 10, 3);  
        }


        
        
        if(is_admin()){
            add_action('admin_init', [$this, 'site_config_js'], 20 );   
            if(SH_THEME_EXISTS){
                add_action("admin_init", "load_admin_files");
            }
            add_action("admin_init", [$this, "remove_comments"]);
            add_action('admin_menu', [$this, 'init_theme_settings_menu']);
            add_action("admin_init", function(){
                visibility_under_construction();
            });
        }else{
            if(SH_THEME_EXISTS){
                add_action("wp_enqueue_scripts", "load_frontend_files", 20);
            }
            add_action( 'wp_enqueue_scripts', [$this, 'site_config_js'], 20 );
            add_filter('body_class', [$this, 'body_class'] );
            add_action("wp", function(){
                visibility_under_construction();
                /*if(function_exists("get_or_create_dictionary_cache")){
                    $dict = get_or_create_dictionary_cache($GLOBALS["language"]);
                    $GLOBALS["lang_predefined"] = $dict;                    
                }*/
            });    
        }
    }

    public function increase_memory_limit() {
        // Bellek limitini artır
        @ini_set('memory_limit', '1536M');

        // Eğer WP_MEMORY_LIMIT sabiti tanımlı değilse, tanımla
        if (!defined('WP_MEMORY_LIMIT')) {
            define('WP_MEMORY_LIMIT', '1536M');
        }

        // Eğer admin tarafı için limit gerekiyorsa (isteğe bağlı)
        if (!defined('WP_MAX_MEMORY_LIMIT')) {
            define('WP_MAX_MEMORY_LIMIT', '2048M');
        }
    }

    public static function init_theme_settings_menu() {
        // Ana menü oluştur
        add_menu_page(
            'Theme Settings',
            'Theme Settings',
            'manage_options',
            'theme-settings',
            '', // Ana menü için bir sayfa içeriği yok
            'dashicons-admin-generic', // Menü simgesi
            90 // Menü sırası
        );

        // Theme Update alt menüsünü ekle
        add_submenu_page(
            'theme-settings', // Ana menü slug'ı
            'Theme Update',
            'Theme Update',
            'manage_options',
            'update-theme',
            ['Update', 'render_page'], // Theme Update içeriğini render et
            1
        );

        // Plugin Yönetimi alt menüsünü ekle
        add_submenu_page(
            'theme-settings', // Ana menü slug'ı
            'Plugin Manager',
            'Plugin Manager',
            'manage_options',
            'plugin-manager',
            ["PluginManager", 'render_option_page'], // Plugin Yönetimi içeriğini render et
            2
        );


        add_submenu_page(
            'theme-settings', // Ana menü slug'ı
            'Video Process',
            'Video Process',
            'manage_options',
            'video-process',
            ['Update', 'render_video_process_page'], // Theme Update içeriğini render et
            3
        );

        // Gereksiz alt menüyü kaldır
        add_action('admin_menu', function () {
            global $submenu;
            if (isset($submenu['theme-settings'])) {
                // İlk alt menü olan "Theme Settings" linkini kaldır
                unset($submenu['theme-settings'][0]);
            }
        }, 999); // Geç bir öncelik ile çalıştır
    }

    public function menu_actions(){
        register_nav_menus(get_menu_locations());
        if (function_exists("acf_add_options_page")) {
                $menu = [
                    "Anasayfa",
                    "Header",
                    "Footer",
                    "Menu",
                    "Theme Styles",
                    "Ayarlar",
                    "Page Assets Update",
                    "Development",
                ];
                if(ENABLE_SEARCH_HISTORY){
                    $menu[] = "Search Ranks";
                }
                $options_menu = [
                    "title" => get_bloginfo("name"),
                    "redirect" => true,
                    "children" => $menu,
                ];
                if(class_exists("WPCF7")) {
                    $options_menu["children"][] = "Formlar";
                }
                create_options_menu($options_menu);

                if(ENABLE_NOTIFICATIONS && is_admin()){
                    $notifications_menu = [
                        "title" => "Notifications",
                        "redirect" => false,
                        "children" => [
                            "Notification Events",
                        ],
                    ];
                    create_options_menu($notifications_menu);            
                }
                if(is_admin()){
                    add_action('admin_init', function () use ($menu) {
                        if (!function_exists('pll_current_language')) return;

                        if (isset($_GET['page']) && !isset($_GET['lang'])) {
                            $slug = $_GET['page'];
                            if (in_array($slug, $menu)) {
                                $url = admin_url('admin.php?page=' . $slug . '&lang=all');
                                wp_redirect($url);
                                exit;
                            }
                        }
                    });
                }
        }
    }

    public function theme_supports(){

        // Add default posts and comments RSS feed links to head.
        add_theme_support("automatic-feed-links");
        add_theme_support("menus");
        add_theme_support("custom-logo");
        add_theme_support("widgets");
        add_theme_support("customize-selective-refresh-widgets");
        add_post_type_support( 'page', 'excerpt' );

        /*
         * Let WordPress manage the document title.
         * By adding theme support, we declare that this theme does not use a
         * hard-coded <title> tag in the document head, and expect WordPress to
         * provide it for us.
         */
        add_theme_support("title-tag");

        add_theme_support( 'custom-background' );
        add_theme_support( 'custom-header' );

        /*
         * Enable support for Post Thumbnails on posts and pages.
         *
         * @link https://developer.wordpress.org/themes/functionality/featured-images-post-thumbnails/
         */
        add_theme_support("post-thumbnails");

        /*
         * Switch default core markup for search form, comment form, and comments
         * to output valid HTML5.
         */
        add_theme_support("html5", [
            "comment-form",
            "comment-list",
            "gallery",
            "caption",
        ]);

        /*
         * Enable support for Post Formats.
         *
         * See: https://codex.wordpress.org/Post_Formats
         */
        add_theme_support("post-formats", [
            "aside",
            "image",
            "video",
            "quote",
            "link",
            "gallery",
            "audio",
        ]);  
    }

    public function after_setup_theme(){
        
        //add theme foldere to use php yemplates
        $hierarchy_filters = [
            'index_template_hierarchy',
            '404_template_hierarchy',
            'archive_template_hierarchy',
            'attachment_template_hierarchy',
            'author_template_hierarchy',
            'category_template_hierarchy',
            'date_template_hierarchy',
            'embed_template_hierarchy',
            'frontpage_template_hierarchy',
            'home_template_hierarchy',
            'page_template_hierarchy',
            'paged_template_hierarchy',
            'search_template_hierarchy',
            'single_template_hierarchy',
            'singular_template_hierarchy',
            'tag_template_hierarchy',
            'taxonomy_template_hierarchy',
        ];
        foreach ($hierarchy_filters as $filter) {
            add_filter($filter, function ($templates) {
                $new_templates = [];

                foreach ($templates as $template) {
                    // Önce theme/ klasörü
                    $new_templates[] = 'theme/' . $template;
                    // Sonra orijinal path
                    $new_templates[] = $template;
                }

                return $new_templates;
            });
        }

        $this->theme_supports();

        if (class_exists("WooCommerce")) {
            add_theme_support("woocommerce");
        }

        if (function_exists("yoast_breadcrumb") && class_exists("Schema_Breadcrumbs")) {
            \Schema_Breadcrumbs::instance();
        }
    }
    public function plugins_loaded(){
        load_theme_textdomain(
            TEXT_DOMAIN,
            get_template_directory() . "/languages"
        );
        error_log("plugins_loaded --------------------------------");
    }
    public function global_variables(){

        if (( defined("SH_THEME_EXISTS") && !SH_THEME_EXISTS) ) {
            return [];
        }

        $salt = $GLOBALS["salt"];

        $user = \Timber::get_user();
        /*if(!$user){
            $user = new \stdClass();
        }
        $user->logged = 0;
        $user->role = "";
        if(isset($user->roles)){
            $user->role = array_keys($user->roles)[0];
        }*/

        if (!$user || !is_object($user)) {
            $user = new \stdClass();
            $user->ID = 0;
            $user->roles = [];
        }
        $user->logged = is_user_logged_in() ? 1 : 0;
        $user->role = !empty($user->roles) ? array_keys($user->roles)[0] : '';
        
        error_log("theme.php site_config call");

        if (!isset($GLOBALS["site_config"])) {
            $site_config = self::get_site_config();
            $GLOBALS["site_config"] = $site_config;
        }else{
            $site_config = $GLOBALS["site_config"];
        }

        if(ENABLE_IP2COUNTRY){
            $user->user_country = $GLOBALS["site_config"]["user_country"];
            $user->user_country_code = $GLOBALS["site_config"]["user_country_code"];
            $user->user_city = $GLOBALS["site_config"]["user_city"];              
        }

        if (ENABLE_FAVORITES) {
            $favorites = $GLOBALS["site_config"]["favorites"];
            if (empty($favorites)) {
                $favorites = [];
            } else {
                if (!is_array($favorites)) {
                    $favorites = json_decode($favorites);
                }
            }
            $GLOBALS["favorites"] = $favorites;
        }

        if (ENABLE_SEARCH_HISTORY) {
            $GLOBALS["search_history"] = $GLOBALS["site_config"]["search_history"];
        }

        if (ENABLE_MEMBERSHIP) {
            $account_nav = [];

            if (is_user_logged_in()) {
                $user->logged = 1;
                $user->role = $user->get_role();

                if((empty($user->billing_country) || empty($user->billing_state)) && $salt->is_ip_changed() && ENABLE_IP2COUNTRY){//warning
                    $login_location = $salt->localization->ip_info("visitor", "Location");
                    $user->login_location = $login_location;
                    if($user->login_location && (empty($user->billing_country) || empty($user->billing_state))){
                        $user->billing_country = $user->login_location["country_code"];
                        $user->billing_state = $user->login_location["state"];
                        $user->city = $user->login_location["state"];
                        global $wpdb; 
                        $query = "SELECT id FROM states WHERE name LIKE '".$user->login_location["state"]."'";
                        $city_data = $wpdb->get_var($query);//$wpdb->get_var($query);
                        $user->city = $city_data;
                        $user->billing_state = $city_data;  
                    }
                    session_write_close();
                }

                //date_default_timezone_set($user->get_timezone());

                if (class_exists("Newsletter")) {
                    $user->newsletter = \SaltBase::newsletter(
                        "status",
                        $user->user_email
                    );
                }

                $messages_count = 0;
                $notification_count = 0;
                if(ENABLE_MEMBERSHIP){
                    if(ENABLE_CHAT){
                       $messages_count = yobro_unseen_messages_count();
                    }
                    if(ENABLE_NOTIFICATIONS){
                       $notification_count = $salt->notification_count();
                    }
                }
                $user->messages_count = $messages_count;
                $user->notification_count = $notification_count;
                //$user->profile_completion = array();/

                $user->menu = get_account_menu();
            }
        } else {
            if (is_user_logged_in()) {
                $user->logged = 1;
                $user->role = array_keys($user->roles)[0];
            }
            session_write_close();
        }

        // post pagination settings
        //if(function_exists('get_field')){
            $post_pagination = \SaltBase::get_cached_option("post_pagination");//get_field("post_pagination", "option");//
        //}else{
            //$post_pagination = get_option("options_post_pagination");
        //}
        if(is_array($post_pagination) && count($post_pagination) > 0){
                $post_pagination_tmp = [];
                foreach ($post_pagination as $item) {
                    $post_type = $item["post_type"];
                    $posts_per_page = -1;
                    if($item["paged"]){
                        $posts_per_page = intval($item["catalog_rows"]) * intval($item["catalog_columns"]);
                    }else{
                        $item["catalog_rows"] = $item["catalog_columns"] = 1;
                    }
                    $item["posts_per_page"] = $posts_per_page;
                    unset($item["post_type"]);
                    $post_pagination_tmp[$post_type] = $item;
                }
                $post_pagination = $post_pagination_tmp;
                unset($post_pagination_tmp);       
        }

        // search pagination settings
        //if(function_exists('get_field')){
            $search_pagination = \SaltBase::get_cached_option("search_pagination");//get_field("search_pagination", "option");//
        //}else{
            //$search_pagination = get_option("options_search_pagination");
        //}
        if($search_pagination && $search_pagination["paged"]){
                $posts_per_page = -1;
                if($search_pagination["paged"]){
                    $posts_per_page = intval($search_pagination["catalog_rows"]) * intval($search_pagination["catalog_columns"]);
                }else{
                    $search_pagination["catalog_rows"] = $search_pagination["catalog_columns"] = 1;
                }
                $search_pagination["posts_per_page"] = $posts_per_page;
                $post_pagination["search"] = $search_pagination;
        }
        $GLOBALS["post_pagination"] = $post_pagination;
        error_log("post_pagination oluitu");

        //sticky support
        $sticky_post_types = get_option("options_add_sticky_support");
        if($sticky_post_types){
            foreach($sticky_post_types as $post_type){
                add_post_type_support($post_type, 'sticky');
            }
        }
        
        $salt->user = $user;
        $GLOBALS["user"] = $user;
        $GLOBALS["salt"] = $salt;
    }
    public function language_settings(){
        if(ENABLE_MULTILANGUAGE){
            $languages = [];
            switch(ENABLE_MULTILANGUAGE){

                case "qtranslate-xt" :
                    if(class_exists("QTX_Module_Slugs")){
                        add_action("request", function($query) use (&$languages){
                            foreach (qtranxf_getSortedLanguages() as $language) {
                                $url = qtrans_get_qtx_language_url($language);//qtranxf_slugs_get_url($language);
                                array_push($languages, [
                                    "name" => $language,
                                    "name_long" => qtranxf_getLanguageName($language),
                                    "locale" => $GLOBALS['q_config']['locale'][$language],
                                    "url" => $url,
                                    "active" => boolval($language == qtranxf_getLanguage())
                                        ? true
                                        : false,
                                ]);
                            }
                            global $q_config;
                            $GLOBALS["languages"] = $languages;
                            $GLOBALS["language"] = qtranxf_getLanguage();
                            $GLOBALS["language_default"] = $q_config['default_language'];
                            $GLOBALS["language_url_view"] = $q_config['hide_default_language'] && qtranxf_getLanguage() == $q_config['default_language']?false:true;
                            return $query;
                        }, 9999);
                    }else{
                        foreach (qtranxf_getSortedLanguages() as $language) {
                            $url = qtranxf_convertURL( "", $language, false, true );
                            array_push($languages, [
                                "name" => $language,
                                "name_long" => qtranxf_getLanguageName($language),
                                "locale" => $GLOBALS['q_config']['locale'][$language],
                                "url" => $url,//."/",
                                "active" => boolval($language == qtranxf_getLanguage())
                                    ? true
                                    : false,
                            ]);
                        }
                        global $q_config;
                        $GLOBALS["languages"] = $languages;
                        $GLOBALS["language"] = qtranxf_getLanguage();
                        $GLOBALS["language_default"] = $q_config['default_language'];
                        $GLOBALS["language_url_view"] = $q_config['hide_default_language'] && qtranxf_getLanguage() == $q_config['default_language']?false:true;
                    }
                break;

                case "wpml" :
                    $languages = [];
                    foreach (icl_get_languages("skip_missing=0&orderby=id&order=asc") as $language) {
                        $lang_url = $language["url"];
                        $has_brand = get_query_var("product_brand");
                        if ($has_brand) {
                            $lang_url = add_query_arg(
                                "product_brand",
                                $has_brand,
                                $lang_url
                            );
                        }
                        array_push($languages, [
                            "name" => $language["code"],
                            "name_long" => $language["code"],
                            "locale" => $language['default_locale'],
                            "url" => $lang_url,
                            "active" => boolval($language["active"]) ? "true" : "false",
                        ]);
                    }
                    global $sitepress;
                    $settings = icl_get_settings();
                    $GLOBALS["languages"] = $languages;
                    $GLOBALS["language"] = ICL_LANGUAGE_CODE;
                    $GLOBALS["language_default"] = apply_filters( 'wpml_default_language', NULL );
                    $GLOBALS["language_url_view"] = $settings['current_language'] && ICL_LANGUAGE_CODE == $GLOBALS["language_default"] ? false : true;

                break;

                case "polylang" :

                    $paged = get_query_var('paged');
                    $lang_default = pll_default_language();
                    $lang_current = pll_current_language();
                    $lang_hide_default = PLL()->options['hide_default'];

                    foreach (pll_the_languages(['raw' => 1]) as $language) {

                        if (function_exists('is_shop') && is_shop()) {

                            $shop_page_id = wc_get_page_id('shop');
                            $translated_shop_page_id = pll_get_post($shop_page_id, $language['slug']);
                            $url = get_permalink($translated_shop_page_id);
                            $lang_slug = $lang_default == $language['slug'] && $lang_hide_default ? "" : "/".$language['slug'];

                        } elseif  (is_post_type_archive()) {

                            $post_type = get_query_var('post_type');
                            if ($post_type) {
                                if ( pll_is_translated_post_type( $post_type )) {
                                    $post_type_slug = pll_translate_string( $post_type, $language['slug'] );
                                } else {
                                    $post_type_slug = $post_type;
                                }
                                $post_type_slug = is_array($post_type_slug)?$post_type_slug[0]:$post_type_slug;
                                $lang_slug = $lang_default == $language['slug'] && $lang_hide_default ? "" : "/".$language['slug'];
                                $url = home_url($lang_slug."/".$post_type_slug."/");
                            } else {
                                $url = pll_home_url($language['slug']);
                            }

                        } elseif (is_tax()) {
                            $taxonomy = get_query_var('taxonomy');
                            $term = get_query_var('term');

                            if ($taxonomy && $term) {
                                $term_data = get_term_by('slug', $term, $taxonomy);
                                $term_id = $term_data ? $term_data->term_id : null;

                                // WooCommerce özel taxonomy'si mi kontrol et
                                if (in_array($taxonomy, ['product_cat', 'product_tag'])) {

                                    // Term ID'yi çevir
                                    $translated_term_id = pll_get_term($term_id, $language['slug']);

                                    // Çevrilmiş term varsa URL oluştur
                                    if ($translated_term_id) {
                                        $url = get_term_link($translated_term_id, $taxonomy);
                                    } else {
                                        // fallback olarak aynı URL kullan
                                        $url = get_term_link($term_id, $taxonomy);
                                    }

                                } else {
                                    // Diğer normal taxonomy'ler
                                    if (pll_is_translated_taxonomy($taxonomy)) {
                                        $translated_term_id = pll_get_term($term_id, $language['slug']);
                                        if ($translated_term_id) {
                                            $term_slug = get_term_by('id', $translated_term_id, $taxonomy)->slug;
                                        } else {
                                            $term_slug = $term;
                                        }
                                    } else {
                                        $term_slug = $term;
                                    }

                                    // Eğer taxonomy prefix kaldırılmışsa
                                    $taxonomy_slug = $taxonomy . "/";
                                    $taxonomy_prefix_remove = \SaltBase::get_cached_option("taxonomy_prefix_remove");
                                    if ($taxonomy_prefix_remove && in_array($taxonomy, $taxonomy_prefix_remove)) {
                                        $taxonomy_slug = "";
                                    }

                                    $lang_slug = ($lang_default == $language['slug'] && $lang_hide_default) ? "" : "/" . $language['slug'];
                                    $url = home_url($lang_slug . "/" . $taxonomy_slug . $term_slug . "/");
                                }

                            } else {
                                $url = pll_home_url($language['slug']);
                            }
                            
                        } else {

                            $post_language = pll_get_post(get_the_ID(), $language['slug']);
                            if ($post_language) {
                                $url = get_permalink($post_language);
                            } else {
                                //$url = pll_home_url($language['slug']);
                                $url = pll_home_url($lang_default);
                            }
                        }

                        if (isset($url) && $paged && $paged > 1) {
                            $url = trailingslashit($url) . 'page/' . $paged . '/';
                        }

                        $languages[] = [
                            "name" => $language['slug'],
                            "name_long" => $language['name'],
                            "locale" => $language['locale'],
                            "url" => $url,
                            "active" => $language['current_lang'] ? true : false,
                        ];
                    }
                    $GLOBALS["languages"] = $languages;
                    $GLOBALS["language"] = $lang_current;
                    $GLOBALS["language_default"] = $lang_default;
                    $GLOBALS["language_url_view"] = $lang_hide_default && $lang_current == $lang_default?false:true;

                break;
            }
        }
    }
    public function query_all_posts($query){

        if (is_admin()) {
            return $query;
        }

        if (isset($query->query_vars['suppress_filters']) && $query->query_vars['suppress_filters']) {
            return;
        }
       
        $post_type = $query->get("post_type");
        $post_type = empty($post_type)?$query->get("qpt"):$post_type;
        $post_type = empty($post_type)?"post":$post_type;
        if(is_search() && is_array($post_type) || $post_type == "any"){
            $post_type = "search";
        }
        if( $query->get("post_type") == get_query_var("qpt") && 
            in_array(get_query_var("qpt_settings"), [2]) && 
            $query->get("s") && 
            !$query->is_main_query()){
                $post_type = "search";
        }

        if($query->is_main_query()){

            if($query->is_search()) {
                if(isset($GLOBALS["post_pagination"]["search"])){
                    $posts_per_page = $GLOBALS["post_pagination"]["search"]["posts_per_page"];
                    $query->set("posts_per_page", $posts_per_page);
                }
                $exclude_from_search_result = [];
                if (class_exists("Newsletter")) {
                    $exclude_from_search_result[] = get_option("newsletter_page");
                }
                $query->set("post__not_in", $exclude_from_search_result);

                if (EXCLUDE_FROM_SEARCH) {
                    $post_types = get_post_types(['public' => true], 'names');
                    foreach (EXCLUDE_FROM_SEARCH as $post_type) {
                        if (in_array($post_type, $post_types)) {
                            unset($post_types[$post_type]);
                        }
                    }
                    $query->set('post_type', $post_types);
                }
            }

            if (!is_shop() && !empty($post_type)) {
                $pagination = get_post_type_pagination($post_type);
                $posts_per_page = -1;
                if($pagination){
                    $posts_per_page = $pagination["posts_per_page"];
                }
                if($posts_per_page == -1 || $posts_per_page > 0){
                    $query->set("posts_per_page", $posts_per_page);
                    $query->set("numberposts", $posts_per_page);            
                }
            }

            if (!empty(get_query_var("q"))) {
                if(is_numeric(get_query_var("qpt"))){
                    $qpt_settings = get_query_var("qpt");
                    set_query_var("qpt", "search");
                    set_query_var("qpt_settings", $qpt_settings);
                }
                add_action('wp_footer', 'custom_search_add_term');
            }


            $sticky_post_types = get_option("options_add_sticky_support");
            if ($query->is_post_type_archive() || is_home() && (!empty($post_type) && is_array($sticky_post_types) && in_array($post_type, $sticky_post_types))) {
                // Sticky meta'ya göre sıralama yap
                $meta_query = [
                    'relation' => 'OR',
                    [
                        'key' => '_is_sticky',
                        'value' => 1,
                        'compare' => '=',
                    ],
                    [
                        'key' => '_is_sticky',
                        'value' => 0,
                        'compare' => '=',
                    ]
                ];

                $query->set('meta_query', $meta_query);
                $query->set('orderby', ['meta_value_num' => 'DESC', 'date' => 'DESC']); // Sticky postları öne al, ardından tarihi sırala
            }

        }else{

           if( $query->get("post_type") == get_query_var("qpt") && !empty($query->get("s"))){

            }
            
            if(DISABLE_DEFAULT_CAT){
                $default_cat_id = get_option( 'default_category' );
                if ( function_exists( 'pll_get_term' ) && $default_cat_id ) {
                    $default_cat_id = pll_get_term( $default_cat_id );
                }
                if ($default_cat_id ) {
                    $tax_query = (array) $query->get( 'tax_query' );
                    $tax_query[] = [
                        'taxonomy' => 'category',
                        'field'    => 'term_id',
                        'terms'    => $default_cat_id,
                        'operator' => 'NOT IN',
                    ];
                    $query->set( 'tax_query', $tax_query );
                }
            }

        }

        return $query;
    }
    public function query_all_terms($args, $taxonomies){

        if ( is_admin() ) return $args;
        
        if(DISABLE_DEFAULT_CAT){
            foreach ( (array) $taxonomies as $taxonomy ) {

                if ( ! isset( $args['exclude'] ) || ! is_array( $args['exclude'] ) ) {
                    $args['exclude'] = [];
                }

                // Varsayılan WordPress kategorisi
                if ( $taxonomy === 'category' ) {
                    $default_cat_id = get_option( 'default_category' );

                    if ( function_exists( 'pll_get_term' ) && $default_cat_id ) {
                        $default_cat_id = pll_get_term( $default_cat_id );
                    }

                    if ( $default_cat_id ) {
                        $args['exclude'][] = $default_cat_id;
                    }
                }

                // WooCommerce ürün kategorisi
                if ( $taxonomy === 'product_cat' && class_exists( 'WooCommerce' ) ) {
                    $default_product_cat = get_option( 'default_product_cat' );

                    if ( function_exists( 'pll_get_term' ) && $default_product_cat ) {
                        $default_product_cat = pll_get_term( $default_product_cat );
                    }

                    if ( $default_product_cat ) {
                        $args['exclude'][] = $default_product_cat;
                    }
                }
            }
            if ( ! empty( $args['exclude'] ) ) {
                $args['exclude'] = array_filter( array_unique( (array) $args['exclude'] ) );
            }
        }
        return $args;
    }
    public function body_class( $classes ) {
        if (is_admin()) {
            return $classes;
        }
        if ( is_page_template( 'template-layout.php' ) ) {
            global $post;
            $classes[] = 'page-'.$post->post_name;
        }
        if ( is_page() && ENABLE_MULTILANGUAGE == "polylang") {
            global $post;
            $default_lang_post_id = pll_get_post($post->ID, pll_default_language());
            $default_lang_post = get_post($default_lang_post_id);
            if ($default_lang_post) {
                $classes[] = 'page-' . $default_lang_post->post_name;
            }
        }
        $classes[] = is_user_logged_in()?"logged":"not-logged";
        $classes[] = is_front_page()?"home":"";
        return $classes;
    }

    public function site_assets(){
        if (is_admin() || (defined("SH_THEME_EXISTS") && !SH_THEME_EXISTS)) {
            return;
        }
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }
        if (defined('DOING_CRON') && DOING_CRON) {
            return;
        }
        if (defined('SITE_ASSETS')) {
            return;
        }
        error_log("1. site assets KONTROL");

        if(!defined("SITE_ASSETS")){
            error_log("2. site assets NOT DEFINED");
            $site_assets = [];


            error_log("admiiin:".is_admin()."  ");
            error_log(print_r(self::get_meta(), true));
           
           
            if (is_singular()) {
                $post_id = get_queried_object_id(); // Geçerli sayfanın ID'sini al
                $site_assets = get_post_meta($post_id, 'assets', true);
            } elseif (is_category() || is_tag() || is_tax()) {
                $term = get_queried_object(); // Term objesini al
                $site_assets = get_term_meta($term->term_id, 'assets', true);
            } elseif (is_post_type_archive()) {
                if( ENABLE_MULTILANGUAGE ){
                    $site_assets = get_option(get_post_type().'_archive_'.ml_get_current_language().'_assets', true);
                }else{
                    $site_assets = get_option(get_post_type().'archive_assets', true);
                }
            } elseif(is_single() && comments_open()){
                if (isset($_GET['comment_id'])) {
                    $comment_id = intval($_GET['comment_id']);
                    $comment = get_comment($comment_id);
                    if ($comment) {
                        $site_assets = get_comment_meta($comment_id, 'assets', true);
                    }
                }
            } elseif(is_author()){
                $user_id = get_queried_object_id();
                $site_assets = get_user_meta($user_id, 'assets', true);
            }
            $assets_data = [
                "js" => "", 
                "css" => "", 
                "css_critical" => "", 
                "css_page" => "", 
                "css_page_rtl" => "", 
                "plugins" => "", 
                "plugin_css" => "",
                "plugin_css_rtl" => "",
                "wp_js" => "", 
                "meta" => [], 
                "lcp" => []
            ];

            if(!$site_assets && !isset($_GET["fetch"]) && (SEPERATE_CSS || SEPERATE_JS)){
                //error_log(print_r($site_assets, true));
                error_log("3. site assets META IN DB IS EMPTY -> REGENERATE");
                $meta = self::get_meta();
                if($meta["type"] == "post"){
                    $site_assets = $GLOBALS["salt"]->extractor->on_save_post($meta["id"], [], false);
                }
                if($meta["type"] == "term"){
                    $site_assets = $GLOBALS["salt"]->extractor->on_save_term($meta["id"], "", $meta["tax"]);
                }
            }

            $site_assets = !empty($site_assets) ? $site_assets : $assets_data;

            error_log("4. site assets IS DEFINED....");
            
            define("SITE_ASSETS", $site_assets);
            
            if(!isset($_GET["fetch"])) {
                new \Lcp();
            }

        }else{
            error_log("site assets zaten var");
        }
    }

    public static function get_meta(){
            $type = "";
            $id = "";
            $tax = "";
            if (is_singular()) {
                $type = "post";
                $id = get_queried_object_id(); // Geçerli sayfanın ID'sini al
            } elseif (is_category() || is_tag() || is_tax()) {
                $type = "term";
                $term = get_queried_object(); // Term objesini al
                $id = $term->term_id;
                $tax = $term->taxonomy;
            } elseif (is_post_type_archive()) {
                $type = "archive";
                if( ENABLE_MULTILANGUAGE ){
                    $id = get_post_type()."_archive_".ml_get_current_language();
                }else{
                    $id = get_post_type()."_archive";
                }
            } elseif(is_single() && comments_open()){
                if (isset($_GET['comment_id'])) {
                    $comment_id = intval($_GET['comment_id']);
                    $comment = get_comment($comment_id);
                    if ($comment) {
                        $type = "comment";
                        $id = $comment_id;
                    }
                }
            } elseif(is_author()){
                $type = "user";
                $id = get_queried_object_id();
            }
            return [
                "type" => $type,
                "id" => $id,
                "tax" => $tax
            ];
    }
    public static function get_site_config($jsLoad = 0, $meta = []){

        if (is_admin() || ( defined("SH_THEME_EXISTS") && !SH_THEME_EXISTS) ) {
            return [];
        }

        error_log("get_site_config");

        if(!isset($GLOBALS["site_config"])){

            error_log("site config preparing...");
        
            /*$is_cached = false;
            //if(function_exists("wprocket_is_cached")){
            if (defined("WP_ROCKET_VERSION") && function_exists("is_wp_rocket_crawling")) {
                $is_cached = wprocket_is_cached();// is_wp_rocket_crawling();
            }

            error_log("site config is_cached => ".$is_cached);*/

            $enable_favorites =  boolval(ENABLE_FAVORITES);
            $enable_follow =  boolval(ENABLE_FOLLOW);
            $enable_search_history =  boolval(ENABLE_SEARCH_HISTORY);

            if ($enable_favorites) {
                $favorites_obj = new \Favorites();
                $favorites_obj->update();
                $favorites = $favorites_obj->favorites;
                $favorites = json_encode($favorites, JSON_NUMERIC_CHECK);
                $GLOBALS["favorites"] = $favorites;
            }

            if ($enable_follow) {
                $follow_types = FOLLOW_TYPES;
            }

            if($enable_search_history){
                $search_history_obj = new \SearchHistory();
                $search_history = $search_history_obj->get_user_terms();
            }

            $path = getSiteSubfolder();
            
            $config = array(
                "enable_membership"     => boolval(ENABLE_MEMBERSHIP),
                "enable_favorites"      => $enable_favorites,
                "enable_follow"         => $enable_follow,
                "enable_search_history" => $enable_search_history,
                "enable_cart"           => boolval(ENABLE_CART),
                "enable_filters"        => boolval(ENABLE_FILTERS),
                "enable_chat"           => boolval(ENABLE_CHAT),
                "enable_notifications"  => boolval(ENABLE_NOTIFICATIONS),
                "enable_ecommerce"      => boolval(ENABLE_ECOMMERCE),
                "enable_ip2country"     => boolval(ENABLE_IP2COUNTRY),
                "path"                  => $path,
                "loaded"                => ($jsLoad==1?true:false),
                "cached"                => "",
                "logged"                => is_user_logged_in(),
                "debug"                 => boolval(ENABLE_CONSOLE_LOGS),
                "language_default"      => $GLOBALS['language_default']
            );
            if(isset($GLOBALS['base_urls'])){
                $config["base_urls"] = $GLOBALS['base_urls'];
            }
            if ($enable_favorites) {
               $config["favorites"] = json_decode($favorites, true);
               $config["favorite_types"] = FAVORITE_TYPES;
            }
            if ($enable_follow) {
               $config["follow_types"] = FOLLOW_TYPES;
            }
            if ($enable_search_history) {
               $config["search_history"] = $search_history;//json_decode($search_history, true);
            }
            if(!$config["logged"]){
                $config["nonce"] = wp_create_nonce( 'ajax' );
            }
            if(ENABLE_IP2COUNTRY){
                $user_country = "";
                $user_country_code = "";
                $user_city = "";
                $user_language = "";
                if(isset($_COOKIE['user_country'])){
                    $user_country = $_COOKIE["user_country"];
                }
                if(isset($_COOKIE['user_country_code'])){
                    $user_country_code = $_COOKIE["user_country_code"];
                }
                if(isset($_COOKIE['user_city'])){
                    $user_city = $_COOKIE["user_city"];
                }
                if(isset($_COOKIE['user_language'])){
                    $user_language = $_COOKIE["user_language"];
                }
                if(isset($_COOKIE['user_region'])){
                    $user_region = json_decode($_COOKIE["user_region"]);
                }
                if(empty($user_city) || empty($user_country) || empty($user_country_code)){
                    
                    $data = $this->localization->ip_info();

                    if(empty($user_country)){
                        if(!$data){
                            $user_country = "Unknown";
                        }else{
                            if(isset($data->name)){
                                $user_country = $data->name;
                            }else{
                                $user_country = $data["name"];
                            }
                        }
                    }

                    if(empty($user_country_code)){
                        if(!$data){
                            $user_country_code = "";
                        }else{
                            if(isset($data->iso2)){
                                $user_country_code = $data->iso2;
                            }else{
                                $user_country_code = $data["iso2"];
                            }
                        }
                    }

                    if(empty($user_city)){
                        if(!$data){
                            $user_city = "Unknown";
                        }else{
                            if(isset($data->state)){
                                $user_city = $data->state;
                            }else{
                                $user_city = $data["state"];
                            }
                        }
                    }

                    if(empty($user_language)){
                        $user_language = strtolower( substr( get_locale(), 0, 2 ) );
                        if (function_exists("qtranxf_getSortedLanguages")) {
                            $user_language = qtranxf_getLanguage();
                        }else{
                            $user_language = strtolower( substr( get_locale(), 0, 2 ) );
                        }
                    }

                    if(empty($user_region) && ENABLE_REGIONAL_POSTS){
                        $user_region = get_region_by_country_code($user_country_code);
                    }
                    
                }
                $config["user_country"] = $user_country;
                $config["user_country_code"] = $user_country_code;
                $config["user_city"] = $user_city;
                $config["user_language"] = $user_language;
                /*setcookie('user_country', $user_country, time() + (86400 * 365), $path); 
                setcookie('user_country_code', $user_country_code, time() + (86400 * 365), $path);
                setcookie('user_city', $user_city, time() + (86400 * 365), $path);
                setcookie('user_language', $user_language, time() + (86400 * 365), $path);*/
                if (!isset($_COOKIE['user_country']) || $_COOKIE['user_country'] !== $user_country) {
                    setcookie('user_country', $user_country, time() + (86400 * 365), $path);
                }
                if (!isset($_COOKIE['user_country_code']) || $_COOKIE['user_country_code'] !== $user_country_code) {
                    setcookie('user_country_code', $user_country_code, time() + (86400 * 365), $path);
                }
                if (!isset($_COOKIE['user_city']) || $_COOKIE['user_city'] !== $user_city) {
                    setcookie('user_city', $user_city, time() + (86400 * 365), $path);
                }
                if (!isset($_COOKIE['user_language']) || $_COOKIE['user_language'] !== $user_language) {
                    setcookie('user_language', $user_language, time() + (86400 * 365), $path);
                }

                if(ENABLE_REGIONAL_POSTS){
                    //setcookie('user_region', json_encode($user_region), time() + (86400 * 365), $path);
                    if (!isset($_COOKIE['user_region']) || $_COOKIE['user_region'] !== $user_region) {
                        setcookie('user_region', $user_region, time() + (86400 * 365), $path);
                    }
                }
            }else{
                $user_language = $GLOBALS["language"];
                $config["user_language"] = $user_language;
                //setcookie('user_language', $user_language, time() + (86400 * 365), $path);
            }

            $required_js_file = get_stylesheet_directory() ."/static/js/js_files.json";
            if(file_exists($required_js_file)){
                $required_js = file_get_contents($required_js_file);
            }else{
                $required_js = [];
            }
            if(!is_array($required_js)){
                $required_js = json_decode($required_js, true);
            }
            $config["required_js"] = $required_js;

            if(defined("SH_INCLUDES_URL")){
               $config["theme_includes_url"] = SH_INCLUDES_URL; 
            }

            if(defined("THEME_URL")){
               $config["theme_url"] = THEME_URL; 
            }

            if(!is_admin() && class_exists("TranslationDictionary")){
                $dictionary = new \TranslationDictionary();
                $config["dictionary"] = $dictionary->getDictionary();
            }

            if($meta){
                $config["meta"] = $meta;
            }
            error_log("site config is completed");

            $GLOBALS["site_config"] = $config;
        }else{
            error_log("site config already set");
            if($meta){
                $GLOBALS["site_config"]["meta"] = $meta;
            }
        }
        if($jsLoad){
            $GLOBALS["site_config"]["loaded"] = true;
        }

        return $GLOBALS["site_config"];  
    }
    public function site_config_js(){

        if (is_admin() || ( defined("SH_THEME_EXISTS") && !SH_THEME_EXISTS) ) {
            return;
        }

        error_log("site_config_js();");

            wp_register_script( 'site_config_vars', STATIC_URL . 'js/methods.min.js', array("jquery"), '1.0', false );
            wp_enqueue_script('site_config_vars');

            if(isset($GLOBALS["site_config"])){
                $args = $GLOBALS["site_config"];
            }else{
                $args = self::get_site_config();
            }

            if(defined("SITE_ASSETS") && is_array(SITE_ASSETS) && isset(SITE_ASSETS["meta"])){
                $args["meta"] = SITE_ASSETS["meta"]; 
            }  

            //error_log(print_r($args, true));
            
            wp_localize_script( 'site_config_vars', 'site_config', $args);

            /*$inline_js = "
                document.addEventListener('visibilitychange', function () {
                    if (document.visibilityState === 'hidden') {
                        document.body.classList.remove('loading-process');
                    }
                });
                window.addEventListener('popstate', function () {
                    document.body.classList.remove('loading-process');
                    document.body.style.position = '';
                    document.body.style.overflow = '';
                });
                window.addEventListener('pageshow', function (event) {
                    if (event.persisted) {
                        // Geri dönüldüğünde body'deki sınıfları sıfırla
                        document.body.classList.remove('loading-process');
                        document.body.classList.add('init'); // init class'ını yeniden ekle
                    }
                });
            ";
            wp_add_inline_script('site_config_vars', $inline_js);*/

            //required js files
            //$required = json_decode(file_get_contents(get_stylesheet_directory() ."/static/js/js_files.json"), true);
            wp_localize_script( 'site_config_vars', 'required_js', $args["required_js"]);

            $this->site_assets();
            
            if(defined("SITE_ASSETS") && is_array(SITE_ASSETS) && !is_admin()){
                $conditional = SITE_ASSETS["plugins"];//apply_filters("salt_conditional_plugins", []);
                $conditional = $conditional ? $conditional : [];
                wp_localize_script( 'site_config_vars', 'conditional_js', array_values($conditional));
                /*if(!empty(SITE_ASSETS["js"])){
                    wp_register_script( 'page-scripts', '', array("jquery"), '1.0', true );
                    wp_enqueue_script( 'page-scripts' );
                    wp_add_inline_script( 'page-scripts', SITE_ASSETS["js"]);                    
                }*/

                if(!empty(SITE_ASSETS["css"]) && (!isset($_GET['fetch']) && SEPERATE_CSS)){
                    wp_register_style( 'page-styles', false );
                    wp_enqueue_style( 'page-styles' );
                    $upload_dir = wp_upload_dir();
                    $upload_url = $upload_dir['baseurl']."/";
                    $code = str_replace("{upload_url}", $upload_url, SITE_ASSETS["css"]);
                    $code = str_replace("{home_url}", home_url("/"), $code);
                    //$code .= file_get_contents(STATIC_URL . SITE_ASSETS["css_page"]);
                    wp_add_inline_style( 'page-styles', $code); 
                }
            }

            $args = array(
                'url'         => home_url().'/',//.qtranxf_getLanguage(),
                'url_admin'   => admin_url('admin-ajax.php'),
                'ajax_nonce'  => wp_create_nonce( 'ajax' ),
                'theme_url'  => get_stylesheet_directory_uri()."/",
                'title'       => ''
            );
            if(class_exists("Redq_YoBro")){
                $user = wp_get_current_user();
                $conversations = yobro_get_all_conversations($user->ID);
                if($conversations){
                    $args["conversations"] = $conversations;
                }
            }
            wp_localize_script( 'site_config_vars', 'ajax_request_vars', $args);
    }
    public function remove_comments(){
        if (!is_admin()) {
            return;
        }
        $disable_comments_file = "/remove-comments-absolute.php";
        $disable_comments_path = SH_INCLUDES_PATH . $disable_comments_file;
        $disable_comments_plugin = WP_PLUGIN_DIR . $disable_comments_file;
        if (DISABLE_COMMENTS) {
            if (!class_exists("Remove_Comments_Absolute")) {
                if (copy($disable_comments_path, $disable_comments_plugin)) {
                    echo "File copied! \n";
                    activate_plugin("remove-comments-absolute.php");
                } else {
                    echo "File has not been copied! \n";
                }
            }
        } else {
            if (class_exists("Remove_Comments_Absolute")) {
                function remove_comments_absolute_deactivate(){
                    delete_plugins(["remove-comments-absolute.php"]);
                }
                register_deactivation_hook(
                    "/remove-comments-absolute.php",
                    "remove_comments_absolute_deactivate"
                );
                deactivate_plugins($disable_comments_file);
            }
        }
    }


    public function wc_custom_template_path($path) {
        return '/theme/woocommerce/'; // yani artık şu klasöre bakacak: your-theme/theme/woocommerce/
    }


    public function wc_multiple_template_paths($template, $template_name, $template_path) {
        $paths = [
            get_template_directory() . '/woocommerce/',
            get_template_directory() . '/theme/woocommerce/',
        ];
        $template_name = str_replace("_", "-", $template_name);
        foreach ($paths as $dir) {
            $full_path = trailingslashit($dir) . $template_name;
            if (file_exists($full_path)) {
                return $full_path;  // ilk bulduğunu döndür, Timber gibi
            }
        }
        return $template;
    }


    public function register_post_types(){
        //this is where you can register custom post types
        include SH_INCLUDES_PATH . "register/post-type.php";
        if(SH_THEME_EXISTS){
            include THEME_INCLUDES_PATH . "register/post-type.php";
        }
    }

    public function register_taxonomies(){
        include SH_INCLUDES_PATH . "register/user.php";
        include SH_INCLUDES_PATH . "register/taxonomy.php";
        if(SH_THEME_EXISTS){
            include THEME_INCLUDES_PATH . "register/user.php";
            include THEME_INCLUDES_PATH . "register/taxonomy.php";
        }
    }

    public static function scss_compile(){
        global $wpscss_compiler;
        $wpscss_compiler = new \SCSSCompiler(
            [
                SH_STATIC_PATH."scss/"
            ],
            STATIC_PATH."css/",
            'SOURCE_MAP_NONE',
            "compressed"
        );
        $wpscss_compiler->wp_scss_compile();
        return $wpscss_compiler->get_compile_errors();
    }
	public function init(){
        add_action("init", function () {
            if(is_admin()){
                \PluginManager::init();
                \Update::init();
                new \AvifConverter();
            }
            new \starterSite();
        }); 
	}
}