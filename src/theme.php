<?php
namespace SaltHareket;

use ScssPhp\SCSSPhp\SCSSCompiler;
use ScssPhp\ScssPhp\OutputStyle;

Class Theme{

    function __construct(){
        //echo "Theme->__construct()<br>";
        show_admin_bar(false);
        add_action("after_setup_theme", [$this, "after_setup_theme"]);
        add_action("init", [$this, "global_variables"]);
        add_action("wp", [$this, "language_settings"]);
        add_action("wp", [$this, "site_assets"]);
        add_action("init", [$this, "language_settings"], 1);
        add_action("init", [$this, "increase_memory_limit"]);
        add_action("pre_get_posts", [$this, "query_all_posts"], 10);
        if(SH_THEME_EXISTS){
            add_action("wp_enqueue_scripts", "load_frontend_files", 20);
        }
        add_filter('body_class', [$this, 'body_class'] );
        
        if(is_admin()){
            if(SH_THEME_EXISTS){
                add_action("admin_init", "load_admin_files");
            }
            add_action("admin_init", [$this, "remove_comments"]);
            add_action('admin_menu', [$this, 'init_theme_settings_menu']);
            add_action("admin_init", function(){
                visibility_under_construction();
            });
        }else{
            add_action("wp", function(){
                visibility_under_construction();
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

        // Plugin Yönetimi alt menüsünü ekle
        add_submenu_page(
            'theme-settings', // Ana menü slug'ı
            'Plugin Manager',
            'Plugin Manager',
            'manage_options',
            'plugin-manager',
            ["PluginManager", 'render_option_page'] // Plugin Yönetimi içeriğini render et
        );

        // Theme Update alt menüsünü ekle
        add_submenu_page(
            'theme-settings', // Ana menü slug'ı
            'Theme Update',
            'Theme Update',
            'manage_options',
            'update-theme',
            ['Update', 'render_page'] // Theme Update içeriğini render et
        );

        add_submenu_page(
            'theme-settings', // Ana menü slug'ı
            'Video Process',
            'Video Process',
            'manage_options',
            'video-process',
            ['Update', 'render_video_process_page'] // Theme Update içeriğini render et
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


    public function after_setup_theme(){
        if (class_exists("WooCommerce")) {
            add_theme_support("woocommerce");
        }

        if (function_exists("yoast_breadcrumb") && class_exists("Schema_Breadcrumbs")) {
            \Schema_Breadcrumbs::instance();
        }

        add_action("acf/init", function(){
            register_nav_menus(get_menu_locations());
        });

        /*add options pages to admin*/
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
        }
    }
    public function global_variables(){

        //error_log( var_export( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ), true ) );

        $salt = $GLOBALS["salt"];
 
        //check_and_load_translation(TEXT_DOMAIN);
        load_theme_textdomain(
            TEXT_DOMAIN,
            get_template_directory() . "/languages"
        );
        lang_predefined();

        $user = \Timber::get_user();
        if(!$user){
            $user = new \stdClass();
        }
        $user->logged = 0;
        $user->role = "";
        if(isset($user->roles)){
            $user->role = array_keys($user->roles)[0];
        }

        if (!isset($GLOBALS["site_config"])) {
            $site_config = $salt->get_site_config();
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
                    $user->newsletter = Salt::newsletter(
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
        if(function_exists('get_field')){
            $post_pagination = get_field("post_pagination", "option");//
        }else{
            $post_pagination = get_option("options_post_pagination");
        }
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
        if(function_exists('get_field')){
            $search_pagination = get_field("search_pagination", "option");//
        }else{
            $search_pagination = get_option("options_search_pagination");
        }
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

                    foreach (pll_the_languages(['raw' => 1]) as $language) {
                        if (is_post_type_archive()) {
                            $post_type = get_query_var('post_type');
                            if ($post_type) {
                                if ( pll_is_translated_post_type( $post_type )) {
                                    $post_type_slug = pll_translate_string( $post_type, $language['slug'] );
                                } else {
                                    $post_type_slug = $post_type;
                                }
                                $hide_default = PLL()->options['hide_default'];
                                $lang_slug = pll_default_language() == $language['slug'] && $hide_default ? "" : "/".$language['slug'];
                                $url = home_url($lang_slug."/".$post_type_slug."/");
                            } else {
                                $url = pll_home_url($language['slug']);
                            }

                        } elseif (is_tax()) {  // Taxonomy sayfası için ekleme

                            $taxonomy = get_query_var('taxonomy');
                            $term = get_query_var('term');
                            if ($taxonomy && $term) {
                                $term_data = get_term_by('slug', $term, $taxonomy);
                                $term_id = $term_data ? $term_data->term_id : null;
                                if (pll_is_translated_taxonomy($taxonomy)) {
                                    $taxonomy_slug = pll_translate_string($taxonomy, $language['slug']);
                                    $term_id = pll_get_term($term_id, $language['slug']);//pll_translate_string($term, $language['slug']);
                                    if($term_id){
                                        $term_slug = get_term_by('id', $term_id, $taxonomy)->slug;
                                    }else{
                                        $term_slug = $term;
                                    }
                                } else {
                                    $taxonomy_slug = $taxonomy;
                                    $term_slug = $term;
                                }
                                $taxonomy_slug = $taxonomy_slug."/";
                                $taxonomy_prefix_remove = get_field("taxonomy_prefix_remove", "option");
                                if($taxonomy_prefix_remove && in_array($taxonomy, get_field("taxonomy_prefix_remove", "option"))){
                                   $taxonomy_slug = "";
                                }
                                $hide_default = PLL()->options['hide_default'];
                                $lang_slug = pll_default_language() == $language['slug'] && $hide_default ? "" : "/".$language['slug'];
                                $url = home_url($lang_slug."/".$taxonomy_slug.$term_slug."/");
                            } else {
                                $url = pll_home_url($language['slug']);
                            }
                        } else {
                            $post_language = pll_get_post(get_the_ID(), $language['slug']);
                            if ($post_language) {
                                $url = get_permalink($post_language);
                            } else {
                                //$url = pll_home_url($language['slug']);
                                $url = pll_home_url(pll_default_language());
                            }
                        }
                        $languages[] = [
                            "name" => $language['slug'],
                            "name_long" => $language['name'],
                            "url" => $url,
                            "active" => $language['current_lang'] ? true : false,
                        ];
                    }
                    $GLOBALS["languages"] = $languages;
                    $GLOBALS["language"] = pll_current_language();
                    $GLOBALS["language_default"] = pll_default_language();
                    $GLOBALS["language_url_view"] = PLL()->options['hide_default'] && pll_current_language() == pll_default_language()?false:true;

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

            if ($query->is_post_type_archive() || is_home()) {
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

        }

        return $query;
    }
    public function body_class( $classes ) {
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
        if (is_singular()) {
            $post_id = get_queried_object_id(); // Geçerli sayfanın ID'sini al
            $site_assets = get_post_meta($post_id, 'assets', true);
        } elseif (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object(); // Term objesini al
            $site_assets = get_term_meta($term->term_id, 'assets', true);
        } elseif (is_post_type_archive()) {
            $site_assets = get_option(get_post_type().'_'.ml_get_current_language().'_assets', true);
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
        $site_assets = !empty($site_assets) ? $site_assets : ["js" => "", "css" => "", "plugins" => "", "wp_js" => ""];
        define("SITE_ASSETS", $site_assets);
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
        if(is_admin()){
            add_action("init", function(){
                \PluginManager::init();
                \Update::init();
                new \AvifConverter(50);
            });            
        }
        new \starterSite(); 
	}
}