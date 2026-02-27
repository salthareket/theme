<?php

namespace SaltHareket;

use ScssPhp\SCSSPhp\SCSSCompiler;
use ScssPhp\ScssPhp\OutputStyle;

class Data {
    private static $storage = [];
    private static $keyCache = []; // Noktalı yolların parçalanmış halini saklar

    /**
     * String anahtarı parçalar ve cache'e alır (Performans İçin)
     */
    private static function getSegments($key) {
        return self::$keyCache[$key] ?? (self::$keyCache[$key] = explode('.', $key));
    }

    /**
     * Veri Kaydet (Hibrit: Array ve Object uyumlu)
     */
    public static function set($key, $val) {
        $storage = &self::$storage;
        $keys = self::getSegments($key);

        while (count($keys) > 1) {
            $segment = array_shift($keys);

            if (is_array($storage)) {
                if (!isset($storage[$segment]) || (!is_array($storage[$segment]) && !is_object($storage[$segment]))) {
                    $storage[$segment] = [];
                }
                $storage = &$storage[$segment];
            } elseif (is_object($storage)) {
                if (!isset($storage->{$segment}) || (!is_array($storage->{$segment}) && !is_object($storage->{$segment}))) {
                    $storage->{$segment} = new \stdClass();
                }
                $storage = &$storage->{$segment};
            }
        }

        $last = array_shift($keys);
        if (is_array($storage)) {
            $storage[$last] = $val;
        } elseif (is_object($storage)) {
            $storage->{$last} = $val;
        }
    }

    /**
     * Veri Getir (Hibrit: Array ve Object uyumlu)
     */
    public static function get($key, $default = null) {
        $data = self::$storage;
        foreach (self::getSegments($key) as $segment) {
            if (is_array($data) && array_key_exists($segment, $data)) {
                $data = $data[$segment];
            } elseif (is_object($data) && isset($data->{$segment})) {
                $data = $data->{$segment};
            } else {
                return $default;
            }
        }
        return $data;
    }

    /**
     * Varlık Kontrolü (Hibrit: Array ve Object uyumlu)
     */
    public static function has($key) {
        $data = self::$storage;
        foreach (self::getSegments($key) as $segment) {
            if (is_array($data) && array_key_exists($segment, $data)) {
                $data = $data[$segment];
            } elseif (is_object($data) && isset($data->{$segment})) {
                $data = $data->{$segment};
            } else {
                return false;
            }
        }
        return true;
    }

    /**
     * Veriyi Al ve Sil (Flash Message mantığı)
     */
    public static function pull($key, $default = null) {
        $val = self::get($key, $default);
        self::purge($key);
        return $val;
    }

    /**
     * Tek Seferlik Hesapla ve Kaydet (Cache mantığı)
     */
    public static function once($key, $callback) {
        $val = self::get($key, '___NOT_FOUND___');
        if ($val === '___NOT_FOUND___') {
            $val = $callback();
            self::set($key, $val);
        }
        return $val;
    }

    /**
     * Sayaç Artır
     */
    public static function increment($key, $amount = 1) {
        $current = self::get($key, 0);
        self::set($key, $current + $amount);
    }

    /**
     * Diziye/Objeye Eleman Ekle
     */
    public static function push($key, $val) {
        $current = self::get($key, []);
        if (is_array($current)) {
            $current[] = $val;
            self::set($key, $current);
        }
    }

    /**
     * Yüzeysel Birleştirme (Hibrit)
     */
    public static function merge($key, $newVal) {
        $current = self::get($key);
        if ($current === null) {
            self::set($key, $newVal);
            return;
        }

        if (is_array($current) && is_array($newVal)) {
            self::set($key, array_merge($current, $newVal));
        } elseif (is_object($current)) {
            $newObjData = (object) $newVal;
            foreach ($newObjData as $k => $v) { $current->{$k} = $v; }
            self::set($key, $current);
        }
    }

    /**
     * Derinlemesine Birleştirme (Recursive)
     */
    public static function extend($key, array $newValues) {
        $current = self::get($key, []);
        if (is_array($current)) {
            self::set($key, array_replace_recursive($current, $newValues));
        }
    }

    /**
     * Veri Sil (Hibrit: Array ve Object uyumlu)
     */
    public static function purge($key = null) {
        if ($key === null) {
            self::$storage = [];
            self::$keyCache = []; // Komple temizlikte yol cache'ini de sıfırla
            return;
        }

        $storage = &self::$storage;
        $keys = self::getSegments($key);

        while (count($keys) > 1) {
            $segment = array_shift($keys);
            if (is_array($storage) && array_key_exists($segment, $storage)) {
                $storage = &$storage[$segment];
            } elseif (is_object($storage) && isset($storage->{$segment})) {
                $storage = &$storage->{$segment};
            } else {
                return;
            }
        }

        $last = array_shift($keys);
        if (is_array($storage)) { unset($storage[$last]); } 
        elseif (is_object($storage)) { unset($storage->{$last}); }
    }

    /**
     * Tüm Depoyu Dök (Debug)
     */
    public static function all() {
        return self::$storage;
    }
}
class_alias('\SaltHareket\Data', 'Data');


Class Theme{

    private static $instance = null; // Canlı örneği burada saklıyoruz

    //public $is_rtl = false;
    public $is_admin = false;
    public $is_logged = false;
    public $upload_url = "";

    // getInstance: Dışarıdan objeyi çağırma kapısı
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        
        add_action("init", function() {
            //$this->is_rtl = is_rtl();
            $this->is_admin = is_admin();
            $this->is_logged = is_user_logged_in();
        }, 1);

        show_admin_bar(false);

        add_action("plugins_loaded", [$this, "plugins_loaded"]);
        
        add_action("after_setup_theme", [$this, "after_setup_theme"]);

        add_action("init", [$this, "register_post_types"]);
        add_action("init", [$this, "register_taxonomies"]);

        //add_action("wp", [$this, "language_settings"]);
        //add_action("init", [$this, "language_settings"], 1); 

        if (is_admin()) {
            add_action("admin_init", [$this, "language_settings"]);
        } else {
            add_action("wp", [$this, "language_settings"]);
        }

        add_action("acf/init", [$this, "acf_init"]);

        add_action("template_redirect", [$this, "site_assets"]);//add_action("template_redirect", [$this, "site_assets"], 1);
        add_action("template_redirect", [$this, "global_variables"], 999);//template_redirect idi after_setup_theme yapıldı (get_field dattaları gelmiyodu)
                
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

        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_print_styles', 'print_emoji_styles');

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
            add_action("init", [$this, "increase_memory_limit"]);
            add_action("admin_menu", [$this, "menu_actions"]);
            //add_action('admin_init', [$this, 'site_config_js'], 20 );
            add_action('admin_head', function() {
                $screen = get_current_screen();

                if ($screen && $screen->base === 'post') {
                    $this->site_config_js();
                }
            });
            if(SH_THEME_EXISTS){
                //add_action("admin_init", "load_admin_files");
            }
            add_action("admin_init", [$this, "remove_comments"]);
            add_action('admin_menu', [$this, 'init_theme_settings_menu']);
            add_action("admin_init", function(){
                visibility_under_construction();
            });
        }else{
            if(SH_THEME_EXISTS){
                //add_action("wp_enqueue_scripts", "load_frontend_files", 20);
            }
            add_action('wp_head', [$this, 'site_config_js'], 1 );//add_action( 'wp_enqueue_scripts', [$this, 'site_config_js'], 20 );
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

    // Clonlamayı engelle (güvenlik için)
    private function __clone() {}

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
                if(function_exists("create_options_menu")){
                    create_options_menu($options_menu);
                }
            
                if(ENABLE_NOTIFICATIONS && is_admin()){
                    $notifications_menu = [
                        "title" => "Notifications",
                        "redirect" => false,
                        "children" => [
                            "Notification Events",
                        ],
                    ];
                    if(function_exists("create_options_menu")){
                        create_options_menu($notifications_menu);
                    }          
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

    public function after_setup_theme() {

        // Tüm hiyerarşileri tek bir fonksiyonla yönetelim
        $hierarchy_filters = [
            'index_template_hierarchy', '404_template_hierarchy', 'archive_template_hierarchy',
            'attachment_template_hierarchy', 'author_template_hierarchy', 'category_template_hierarchy',
            'date_template_hierarchy', 'embed_template_hierarchy', 'frontpage_template_hierarchy',
            'home_template_hierarchy', 'page_template_hierarchy', 'paged_template_hierarchy',
            'search_template_hierarchy', 'single_template_hierarchy', 'singular_template_hierarchy',
            'tag_template_hierarchy', 'taxonomy_template_hierarchy',
        ];

        // Anonim fonksiyon yerine tek bir metot çağıralım (Hafıza dostu)
        foreach ($hierarchy_filters as $filter) {
            add_filter($filter, [$this, 'map_theme_folder_templates']);
        }

        $this->theme_supports();

        if (class_exists("WooCommerce")) {
            add_theme_support("woocommerce");
        }

        // Schema_Breadcrumbs kontrolü güzel, gereksiz yere yüklenmiyor.
        if (function_exists("yoast_breadcrumb") && class_exists("Schema_Breadcrumbs")) {
            \Schema_Breadcrumbs::instance();
        }
    }
    public function map_theme_folder_templates($templates) {
        $new_templates = [];
        foreach ($templates as $template) {
            $new_templates[] = 'theme/' . $template;
            $new_templates[] = $template;
        }
        return $new_templates;
    }

    public function plugins_loaded(){
        load_theme_textdomain(
            TEXT_DOMAIN,
            get_template_directory() . "/languages"
        );
        //error_log("plugins_loaded --------------------------------");
    }

    public function acf_init(){

        $post_pagination = [];
        if (!$this->is_admin) {
            $post_pagination = \QueryCache::get_field("post_pagination", "options");
            if (is_array($post_pagination) && !empty($post_pagination)) {
                $post_pagination_tmp = [];
                foreach ($post_pagination as $item) {
                    if (!isset($item["post_type"])) continue;
                    
                    $pt = $item["post_type"];
                    $posts_per_page = ($item["paged"]) ? intval($item["catalog_rows"]) * intval($item["catalog_columns"]) : -1;
                    
                    $item["posts_per_page"] = $posts_per_page;
                    unset($item["post_type"]);
                    $post_pagination_tmp[$pt] = $item;
                }
                $post_pagination = $post_pagination_tmp;
            }
            $search_pagination = \QueryCache::get_field("search_pagination", "options");//get_field_default("search_pagination", "options");
            if ($search_pagination && isset($search_pagination["paged"]) && $search_pagination["paged"]) {
                $search_pagination["posts_per_page"] = intval($search_pagination["catalog_rows"]) * intval($search_pagination["catalog_columns"]);
                $post_pagination["search"] = $search_pagination;
            }
        }
        Data::set("post_pagination", $post_pagination);

        $sticky_types = \QueryCache::get_field("add_sticky_support", "options");
        if ($sticky_types) {
            foreach ((array)$sticky_types as $post_type) {
                if ($post_type) add_post_type_support($post_type, 'sticky');
            }
        }

    }

    public function global_variables() {
        // 1. Tema kontrolü
        if (defined("SH_THEME_EXISTS") && !SH_THEME_EXISTS) {
            return [];
        }

        $salt = Data::get("salt");

        // 2. Kullanıcı Objesi Oluşturma
        if ($this->is_admin) {
            $current_user = wp_get_current_user();
            $user = new \stdClass();
            $user->ID = $current_user->ID;
            $user->roles = (array) $current_user->roles;
            $user->user_email = $current_user->user_email;
        } else {
            $timber_user = \Timber::get_user();
            if ($timber_user) {
                $user = $timber_user;
                // Hata buradaydı: Overloaded property'yi direkt resetleyemezsin. 
                // Önce normal bir array'e kopyalıyoruz.
                $user_roles = (array) $user->roles; 
            } else {
                $user = new \stdClass();
                $user->ID = 0;
                $user_roles = [];
                $user->roles = [];
            }
        }

        $user->logged = $this->is_logged ? 1 : 0;

        // Hatayı çözen mermi gibi satır:
        $user->role = !empty($user_roles) ? reset($user_roles) : '';


        // 3. Site Config Cache Kontrolü
        //if (!isset($GLOBALS["site_config"])) {
        if (!Data::has("site_config")) {
            Data::set("site_config", self::get_site_config());//$GLOBALS["site_config"] = self::get_site_config();
        }
        $site_config = Data::get("site_config");//$GLOBALS["site_config"];


        // 4. IP ve Ülke Bilgileri (Sadece Frontend)
        if (!$this->is_admin && ENABLE_IP2COUNTRY) {
            $user->user_country = $site_config["user_country"] ?? '';
            $user->user_country_code = $site_config["user_country_code"] ?? '';
            $user->user_city = $site_config["user_city"] ?? '';
        }

        // 5. Favoriler ve Arama Geçmişi (Global atamalar)
        if (ENABLE_FAVORITES) {
            $favs = $site_config["favorites"] ?? [];
            //$GLOBALS["favorites"] = is_string($favs) ? json_decode($favs, true) : (is_array($favs) ? $favs : []);
            $favorites = is_string($favs) ? json_decode($favs, true) : (is_array($favs) ? $favs : []);
            Data::set("site_config", $favorites);
        }

        if (ENABLE_SEARCH_HISTORY) {
            //$GLOBALS["search_history"] = $site_config["search_history"] ?? [];
            Data::set("site_config", $site_config["search_history"] ?? []);
        }

        // 6. Üyelik ve Lokasyon Mantığı (Sadece Frontend ve Login durumunda)
        if (!$this->is_admin && ENABLE_MEMBERSHIP && $this->is_logged) {
            
            // Eğer fatura bilgileri eksikse ve IP değişmişse DB/API sorgusu yap
            if ((empty($user->billing_country) || empty($user->billing_state)) && $salt->is_ip_changed() && ENABLE_IP2COUNTRY) {
                $login_location = $salt->localization->ip_info("visitor", "Location");
                
                if ($login_location) {
                    $user->login_location = $login_location;
                    $user->billing_country = $login_location["country_code"];
                    
                    // SQL sorgusunu sadece buraya girince yap (Safe query)
                    global $wpdb;
                    $state_name = $login_location["state"];
                    $city_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM states WHERE name LIKE %s LIMIT 1",
                        $state_name
                    ));
                    
                    $user->city = $city_id;
                    $user->billing_state = $city_id;
                }
                if (session_id()) session_write_close(); // Session kilidini bırak
            }

            // Newsletter ve Mesaj Sayıları
            if (class_exists("Newsletter")) {
                $user->newsletter = \SaltBase::newsletter("status", $user->user_email);
            }

            $user->messages_count = (ENABLE_CHAT && function_exists('yobro_unseen_messages_count')) ? yobro_unseen_messages_count() : 0;
            $user->notification_count = (ENABLE_NOTIFICATIONS) ? $salt->notification_count() : 0;
            $user->menu = function_exists('get_account_menu') ? get_account_menu() : [];
        }

        // 9. Final Global Atamaları
        $salt->user = $user;
        //$GLOBALS["user"] = $user;
        //$GLOBALS["salt"] = $salt;
        Data::set("user", $user);
        Data::set("salt", $salt);
    }

    public function is_rtl($lang) {
        if (empty($lang)) return false;

        if(is_rtl()){
            return true;
        }

        $rtl_codes = [
            'ar', 'ara', 'ary', 'arz', // Arapça varyantları
            'fa', 'per', 'fas', 'jpr', // Farsça varyantları
            'ur', 'urd',               // Urduca
            'he', 'heb', 'iw',         // İbranice
            'ps', 'pus',               // Peştuca
            'sd', 'snd',               // Sindhi
            'ku', 'kur', 'ckb',        // Kürtçe (Sorani)
            'ug', 'uig',               // Uygurca
            'dv', 'div',               // Dhivehi
            'yi', 'yid'                // Yidiş
        ];
        $lang = strtolower($lang);
        foreach ($rtl_codes as $code) {
            if (strpos($lang, $code) === 0) {
                return true;
            }
        }

        return false;
    }

    public function language_settings_basic() {
        //if (ENABLE_MULTILANGUAGE) {

            $language =  ml_get_current_language();
            $language_default = ml_get_default_language();
            $languages = [];
            $language_url_view = false;
            $language_rtl   = $this->is_rtl($language);

            Data::set("language", $language);
            Data::set("language_default", $language_default);
            Data::set("languages", $languages);
            Data::set("language_url_view", $language_url_view);
            Data::set("language_rtl", $language_rtl);

        //}
    }
    public function language_settings() {
        if (ENABLE_MULTILANGUAGE) {
            $language = ml_get_current_language();
            $language_default = ml_get_default_language();
            $languages = [];
            $language_url_view = true;
            $language_rtl   = false;

            // 🚀 MERMİ GİBİ CACHE: Her sayfa ve her dil için ayrı cache anahtarı
            $queried_obj_id = get_queried_object_id();
            $cache_key = 'sh_lang_cache_' . $language . '_' . $queried_obj_id;
            $cached_data = get_transient($cache_key);

            if (false !== $cached_data) {
                Data::set("language",  $cached_data['language']);
                Data::set("languages", $cached_data['languages']);
                Data::set("language_default", $cached_data['language_default']);
                Data::set("language_url_view",  $cached_data['language_url_view']);
                Data::set("language_rtl",  $cached_data['language_rtl']);
                return; // Cache varsa fonksiyondan çık, veritabanına dokunma!
            }

            switch (ENABLE_MULTILANGUAGE) {

                case "qtranslate-xt":
                    global $q_config;
                    if (class_exists("QTX_Module_Slugs")) {
                        foreach (qtranxf_getSortedLanguages() as $lang) {
                            $url = qtrans_get_qtx_language_url($lang);
                            $languages[] = [
                                "name" => $lang,
                                "name_long" => qtranxf_getLanguageName($lang),
                                "locale" => $q_config['locale'][$lang],
                                "url" => $url,
                                "active" => ($lang == $language)
                            ];
                        }
                    } else {
                        global $q_config;
                        foreach (qtranxf_getSortedLanguages() as $lang) {
                            $url = qtranxf_convertURL("", $lang, false, true);
                            $languages[] = [
                                "name" => $lang,
                                "name_long" => qtranxf_getLanguageName($lang),
                                "locale" => $q_config['locale'][$lang],
                                "url" => $url,
                                "active" => ($lang == $language)
                            ];
                        }
                    }
                    $language_url_view = $q_config['hide_default_language'] && $language == $language_default ? false : true;
                    break;

                case "wpml":
                    foreach (icl_get_languages("skip_missing=0&orderby=id&order=asc") as $lang) {
                        $lang_url = $lang["url"];
                        if ($has_brand = get_query_var("product_brand")) {
                            $lang_url = add_query_arg("product_brand", $has_brand, $lang_url);
                        }
                        $languages[] = [
                            "name" => $lang["code"],
                            "name_long" => $lang["code"],
                            "locale" => $lang['default_locale'],
                            "url" => $lang_url,
                            "active" => (bool)$lang["active"]
                        ];
                    }
                    $settings = icl_get_settings();
                    $language_url_view = $settings['current_language'] && $language == $language_default ? false : true;
                    break;

                case "polylang":
                    $paged = get_query_var('paged');
                    $lang_hide_default = PLL()->options['hide_default'];

                    foreach (pll_the_languages(['raw' => 1]) as $lang) {
                        $url = '';
                        if (function_exists('is_shop') && is_shop()) {
                            $shop_page_id = wc_get_page_id('shop');
                            $translated_shop_page_id = pll_get_post($shop_page_id, $lang['slug']);
                            $url = get_permalink($translated_shop_page_id);
                        } elseif (is_post_type_archive()) {
                            $post_type = get_query_var('post_type');
                            if ($post_type) {
                                $post_type_slug = pll_is_translated_post_type($post_type) ? pll_translate_string($post_type, $lang['slug']) : $post_type;
                                $post_type_slug = is_array($post_type_slug) ? $post_type_slug[0] : $post_type_slug;
                                $lang_slug = ($language_default == $lang['slug'] && $lang_hide_default) ? "" : "/" . $lang['slug'];
                                $url = home_url($lang_slug . "/" . $post_type_slug . "/");
                            } else {
                                $url = pll_home_url($lang['slug']);
                            }
                        } elseif (is_tax()) {
                            $taxonomy = get_query_var('taxonomy');
                            $term = get_query_var('term');
                            if ($taxonomy && $term) {
                                $term_data = get_term_by('slug', $term, $taxonomy);
                                $term_id = $term_data ? $term_data->term_id : null;
                                if (in_array($taxonomy, ['product_cat', 'product_tag'])) {
                                    $translated_term_id = pll_get_term($term_id, $lang['slug']);
                                    $url = get_term_link($translated_term_id ?: $term_id, $taxonomy);
                                } else {
                                    if (pll_is_translated_taxonomy($taxonomy)) {
                                        $translated_term_id = pll_get_term($term_id, $lang['slug']);
                                        $term_slug = $translated_term_id ? get_term_by('id', $translated_term_id, $taxonomy)->slug : $term;
                                    } else {
                                        $term_slug = $term;
                                    }
                                    $taxonomy_slug = $taxonomy . "/";
                                    $taxonomy_prefix_remove = \QueryCache::get_option("options_taxonomy_prefix_remove");
                                    if ($taxonomy_prefix_remove && in_array($taxonomy, $taxonomy_prefix_remove)) $taxonomy_slug = "";
                                    $lang_slug = ($language_default == $lang['slug'] && $lang_hide_default) ? "" : "/" . $lang['slug'];
                                    $url = home_url($lang_slug . "/" . $taxonomy_slug . $term_slug . "/");
                                }
                            } else {
                                $url = pll_home_url($lang['slug']);
                            }
                        } else {
                            $post_language = pll_get_post(get_the_ID(), $lang['slug']);
                            $url = $post_language ? get_permalink($post_language) : pll_home_url($language_default);
                        }

                        if ($url && $paged && $paged > 1) {
                            $url = trailingslashit($url) . 'page/' . $paged . '/';
                        }

                        $languages[] = [
                            "name" => $lang['slug'],
                            "name_long" => $lang['name'],
                            "locale" => $lang['locale'],
                            "url" => $url,
                            "active" => $lang['current_lang'] ? true : false,
                        ];
                    }
                    $language_url_view = ($lang_hide_default && $language == $language_default) ? false : true;
                    break;
            }

            $language_rtl = $this->is_rtl($language);

            Data::set("language",  $language);
            Data::set("languages", $languages);
            Data::set("language_default", $language_default);
            Data::set("language_url_view",  $language_url_view);
            Data::set("language_rtl",  $language_rtl);

            // 💾 SONRAKİ SEFER İÇİN SAKLA (1 Saatlik cache)
            $data_to_cache = [
                "language" => $language,
                "languages" => $languages,
                "language_default" => $language_default,
                "language_url_view" => $language_url_view,
                "language_rtl" => $language_rtl
            ];

            set_transient($cache_key, $data_to_cache, HOUR_IN_SECONDS);
            //error_log("full langıages ayarlandı......");
        }
    }

    public function query_all_posts($query) {
        // 1. ERKEN ÇIKIŞ (PERFORMANS): Admin sorguları veya filtreleri bastırılmış sorgulara dokunma
        if (is_admin() || (isset($query->query_vars['suppress_filters']) && $query->query_vars['suppress_filters'])) {
            return $query;
        }

        // Değişkenleri Hazırla
        $post_type = $query->get("post_type") ?: ($query->get("qpt") ?: "post");
        $is_main = $query->is_main_query();

        // 2. SEARCH MANTIĞI VE ESKİ TANIMLARI KORUMA
        // (post_type search tanımını burada yapıyoruz ki aşağıda pagination doğru çalışsın)
        $is_search_context = $query->is_search() || $post_type == "any" || (is_search() && is_array($post_type));
        
        // Eski kodundaki özel qpt_settings kontrolü
        $is_custom_ajax_search = (
            $query->get("post_type") == get_query_var("qpt") && 
            in_array(get_query_var("qpt_settings"), [2]) && 
            $query->get("s") && 
            !$is_main
        );

        if ($is_search_context || $is_custom_ajax_search) {
            $post_type = "search";
        }

        // 3. ANA SORGU (MAIN QUERY) İŞLEMLERİ
        if ($is_main) {
            
            // ARAMA OPTİMİZASYONU
            if ($query->is_search()) {
                // Pagination
                /*if (isset($GLOBALS["post_pagination"]["search"])) {
                    $query->set("posts_per_page", $GLOBALS["post_pagination"]["search"]["posts_per_page"]);
                }*/
                if (Data::has("post_pagination.search")) {
                    $query->set("posts_per_page", Data::has("post_pagination.search.posts_per_page"));
                }

                // Arama sonuçlarından sayfaları/dataları hariç tut (Newsletter vb.)
                $exclude_ids = [];
                if (class_exists("Newsletter")) {
                    $nl_id = get_option("newsletter_page");
                    if ($nl_id) $exclude_ids[] = (int)$nl_id;
                }
                if (!empty($exclude_ids)) {
                    $query->set("post__not_in", $exclude_ids);
                }

                // İstenmeyen Post Tiplerini Aramadan At (EXCLUDE_FROM_SEARCH sabiti kullanılıyor)
                if (defined('EXCLUDE_FROM_SEARCH') && is_array(EXCLUDE_FROM_SEARCH)) {
                    $public_types = get_post_types(['public' => true], 'names');
                    foreach (EXCLUDE_FROM_SEARCH as $ex_type) {
                        unset($public_types[$ex_type]);
                    }
                    $query->set('post_type', array_values($public_types));
                }
            }

            // GENEL PAGINATION (Shop değilse)
            if (!is_shop() && !empty($post_type)) {
                // get_post_type_pagination fonksiyonun varsa onu çağırıyoruz
                $pagination = function_exists('get_post_type_pagination') ? get_post_type_pagination($post_type) : null;
                $ppp = (isset($pagination["posts_per_page"])) ? $pagination["posts_per_page"] : -1;

                if ($ppp == -1 || $ppp > 0) {
                    $query->set("posts_per_page", $ppp);
                }
            }

            // Q PARAMETRESİ VE FOOTER ACTION (Eski yapın)
            if (!empty(get_query_var("q"))) {
                if (is_numeric(get_query_var("qpt"))) {
                    $qpt_settings = get_query_var("qpt");
                    set_query_var("qpt", "search");
                    set_query_var("qpt_settings", $qpt_settings);
                }
                add_action('wp_footer', 'custom_search_add_term');
            }

            // STICKY SUPPORT (Meta Query Optimizasyonu)
            $sticky_types = \QueryCache::get_option("options_add_sticky_support");
            if (($query->is_post_type_archive() || is_home()) && is_array($sticky_types) && in_array($post_type, $sticky_types)) {
                $query->set('meta_query', [
                    'relation' => 'OR',
                    ['key' => '_is_sticky', 'value' => 1, 'compare' => '='],
                    ['key' => '_is_sticky', 'value' => 0, 'compare' => '=']
                ]);
                $query->set('orderby', ['meta_value_num' => 'DESC', 'date' => 'DESC']);
            }

        } 
        // 4. ALT SORGU (SUB QUERY) VEYA ÖZEL FİLTRELER
        else {
            // Varsayılan Kategoriyi (Uncategorized) Gizleme
            if (defined('DISABLE_DEFAULT_CAT') && DISABLE_DEFAULT_CAT) {
                $default_cat_id = get_option('default_category');
                if (function_exists('pll_get_term') && $default_cat_id) {
                    $default_cat_id = pll_get_term($default_cat_id);
                }
                if ($default_cat_id) {
                    $tax_query = (array) $query->get('tax_query');
                    $tax_query[] = [
                        'taxonomy' => 'category',
                        'field'    => 'term_id',
                        'terms'    => (int)$default_cat_id,
                        'operator' => 'NOT IN',
                    ];
                    $query->set('tax_query', $tax_query);
                }
            }
        }

        return $query;
    }
    public function query_all_terms($args, $taxonomies) {
        // 1. ERKEN ÇIKIŞ: Admin panelindeysek veya özellik kapalıysa PHP'yi yorma, direkt dön.
        if (is_admin() || !defined('DISABLE_DEFAULT_CAT') || !DISABLE_DEFAULT_CAT) {
            return $args;
        }

        /**
         * 2. STATİK CACHE (BELLEK YÖNETİMİ)
         * WordPress bir sayfada onlarca kez terim sorgusu atar. 
         * Veritabanına her seferinde "varsayılan kategori neydi?" diye sormamak için 
         * sonucu bir kez hesaplayıp statik değişkende saklıyoruz.
         */
        static $excluded_ids = null;

        if ($excluded_ids === null) {
            $excluded_ids = [];

            // Standart WordPress Kategorisi (Uncategorized vb.)
            $default_cat = get_option('default_category');
            if ($default_cat) {
                // Polylang desteği: Eğer çok dilliyse o dildeki karşılığını al, yoksa direkt ID'yi kullan.
                $excluded_ids[] = function_exists('pll_get_term') ? (int)pll_get_term($default_cat) : (int)$default_cat;
            }

            // WooCommerce Ürün Kategorisi (Uncategorized product cat vb.)
            if (class_exists('WooCommerce')) {
                $default_prod_cat = get_option('default_product_cat');
                if ($default_prod_cat) {
                    $excluded_ids[] = function_exists('pll_get_term') ? (int)pll_get_term($default_prod_cat) : (int)$default_prod_cat;
                }
            }

            // Boş değerleri, sıfırları temizle ve sadece benzersiz ID'leri tut
            $excluded_ids = array_values(array_filter(array_unique($excluded_ids)));
        }

        // 3. TAKSONOMİ KONTROLÜ VE FİLTRELEME
        // Sadece 'category' veya 'product_cat' sorgulanıyorsa işlem yapalım.
        $target_taxonomies = ['category', 'product_cat'];
        $current_taxonomies = (array)$taxonomies;

        // Eğer sorgu bizim hedeflediğimiz taksonomilerden birini içeriyorsa ve hariç tutulacak ID varsa
        if (!empty($excluded_ids) && array_intersect($target_taxonomies, $current_taxonomies)) {
            
            // Eğer daha önceden tanımlanmış bir exclude varsa onu bozma, bizimkileri üstüne ekle.
            if (!isset($args['exclude']) || !is_array($args['exclude'])) {
                $args['exclude'] = [];
            }

            // Mevcut exclude listesiyle bizim sistem listesini birleştir
            $args['exclude'] = array_merge($args['exclude'], $excluded_ids);
            
            // Tekrar edenleri temizle ki SQL sorgusu şişmesin
            $args['exclude'] = array_unique($args['exclude']);
        }

        return $args;
    }
    public function body_class( $classes ) {
        // 1. Admin tarafında body class ile işimiz olmaz
        if ($this->is_admin) {
            return $classes;
        }

        global $post;

        // 2. Sayfa Şablonu ve Polylang Slug Eşleşmesi
        // Amacımız: Farklı dillerdeki aynı sayfaya, varsayılan dildeki slug'ı sınıf olarak basmak.
        if ( is_page() ) {
            
            if ( is_page_template( 'template-layout.php' ) ) {
                $classes[] = 'page-' . $post->post_name;
            }

            if ( ENABLE_MULTILANGUAGE === "polylang" && function_exists('pll_get_post') ) {
                $default_lang = ml_get_default_language();
                
                // Eğer şu anki dil varsayılan dil değilse ana postun slug'ını bulalım
                if ( ml_get_current_language() !== $default_lang ) {
                    $default_lang_post_id = pll_get_post($post->ID, $default_lang);
                    
                    if ( $default_lang_post_id && $default_lang_post_id !== $post->ID ) {
                        // get_post yerine veritabanından sadece slug'ı (post_name) çekmek daha hızlıdır
                        $default_slug = get_post_field( 'post_name', $default_lang_post_id );
                        if ( $default_slug ) {
                            $classes[] = 'page-' . $default_slug;
                        }
                    }
                } else {
                    // Zaten varsayılan dildeysek direkt ekle (eğer yukarıda eklenmediyse)
                    if ( !in_array('page-' . $post->post_name, $classes) ) {
                        $classes[] = 'page-' . $post->post_name;
                    }
                }
            }
        }

        // 3. Login Durumu (is_user_logged_in zaten $this->is_logged içinde var)
        $classes[] = $this->is_logged ? "logged" : "not-logged";

        // 4. Front Page Kontrolü (Boş string eklemeyelim, sadece varsa ekleyelim)
        if ( is_front_page() ) {
            $classes[] = "home";
        }

        // Boşlukları ve tekrarları temizleyip gönderelim
        return array_filter( array_unique( $classes ) );
    }

    public function site_assets($jsLoad = 0, $meta = []){ // id ile olusturtucaz ajax için

        // 1. Zaten tanımlıysa, hesaplanmış olanı döndür (PERFORMANS)
        if (defined('SITE_ASSETS')) {
            return SITE_ASSETS;
        }

        // 1. Erken Çıkış
        if ($this->is_admin || 
            (defined("SH_THEME_EXISTS") && !SH_THEME_EXISTS) || 
            //defined('DOING_AJAX') || 
            defined('DOING_CRON') || 
            defined('SITE_ASSETS') ||
            defined('REST_REQUEST') && REST_REQUEST
        ) {
            return;
        }

        $site_assets = null;
        
        // 3. Varsayılan Asset Şablonu
        $assets_defaults = [
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

        // Meta bilgilerini tutacak array (get_meta'nın görevini devralıyor)
        if(!$meta){
            $meta = [
                "type" => "",
                "id"   => "",
                "tax"  => ""
            ];            
        }

        // 2. Kimlik Tespiti (Eğer meta id boşsa WP'den bulmaya çalış)
        if (empty($meta['id'])) {
            $obj = get_queried_object();
            
            if (is_singular()) {
                $meta['type'] = 'post';
                $meta['id']   = $obj->ID;
            } elseif ($obj instanceof \WP_Term) {
                $meta['type'] = 'term';
                $meta['id']   = $obj->term_id;
                $meta['tax']  = $obj->taxonomy;
            } elseif (is_post_type_archive()) {
                $meta['type'] = 'archive';
                $pt           = get_post_type();
                $lang         = (defined('ENABLE_MULTILANGUAGE') && ENABLE_MULTILANGUAGE) ? ml_get_current_language() : "";
                $meta['id']   = $lang ? "{$pt}_archive_{$lang}" : "{$pt}_archive";
            } elseif (is_author()) {
                $meta['type'] = 'user';
                $meta['id']   = get_queried_object_id();
            } elseif (is_search()) {
                $meta['type'] = 'dynamic';
                $pt           = "search";
                $lang         = (defined('ENABLE_MULTILANGUAGE') && ENABLE_MULTILANGUAGE) ? ml_get_current_language() : "";
                $meta['id']   = $lang ? "{$pt}_{$lang}" : "{$pt}_archive";
            } elseif (is_404()) {
                $meta['type'] = 'dynamic';
                $pt           = "404";
                $lang         = (defined('ENABLE_MULTILANGUAGE') && ENABLE_MULTILANGUAGE) ? ml_get_current_language() : "";
                $meta['id']   = $lang ? "{$pt}_{$lang}" : "{$pt}_archive";
            }
        }

        // 3. Asset Çekimi (Meta tipine göre tek noktadan)
        if (!empty($meta['id'])) {
            $site_assets = match($meta['type']) {
                'post'    => get_post_meta($meta['id'], 'assets', true),
                'term'    => get_term_meta($meta['id'], 'assets', true),
                'user'    => get_user_meta($meta['id'], 'assets', true),
                'archive' => get_option($meta['id'] . "_assets", true),
                'dynamic' => get_option($meta['id'] . "_assets", true),
                default   => null
            };
        }

        // 4. Asset Yoksa Yeniden Üret (Extractor)
        if (empty($site_assets) && !isset($_GET["fetch"]) && (SEPERATE_CSS || SEPERATE_JS) && class_exists("PageAssetsExtractor")) {
            $extractor = \PageAssetsExtractor::get_instance();
            error_log("assets bulunamadı theme.php de bastan uretiliyor PageAssetsExtractor ile...");
            if ($meta["type"] === "post") {
                $site_assets = $extractor->on_save_post($meta["id"], [], false);
            } elseif ($meta["type"] === "term") {
                $site_assets = $extractor->on_save_term($meta["id"], "", $meta["tax"]);
            }
        }

        // Veri hala boşsa default'u bas, doluysa meta'yı güncelle
        if (!empty($site_assets) && is_array($site_assets)) {
            $site_assets["meta"] = $meta;
        } else {
            $assets_defaults["meta"] = $meta;
            $site_assets = $assets_defaults;
        }

        // 5. Global Tanımlama ve LCP Başlatma
        define("SITE_ASSETS", $site_assets);

        if (!isset($_GET["fetch"]) && class_exists("Lcp")) {
            \Lcp::getInstance();
        }
    }
    public static function get_site_config($jsLoad = 0, $meta = []){

        //$is_admin = self::getInstance()->is_admin;
        if ($jsLoad !== 1) {
            // 1. URL'DEN TEŞHİS (WP'nin uyanmasını beklemiyoruz)
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            $is_rest_url  = (strpos($uri, '/wp-json/') !== false);
            $is_admin_url = (strpos($uri, '/wp-admin/') !== false);

            if (
                $is_rest_url || 
                $is_admin_url || 
                is_admin() || 
                (defined('REST_REQUEST') && REST_REQUEST) || 
                //(defined('DOING_AJAX') && DOING_AJAX) || 
                (defined('DOING_CRON') && DOING_CRON)
            ) {
                // Gereksiz yükleme, boş dönüyoruz.
                return [];
            }
        }

        /*if (isset($GLOBALS["site_config"])) {
            // Eğer varsa ve yeni meta geldiyse güncelle
            if ($meta) { $GLOBALS["site_config"]["meta"] = $meta; }
            // Eğer JS yüklemesi geldiyse bayrağı dik
            if ($jsLoad) { $GLOBALS["site_config"]["loaded"] = true; }
            
            //error_log("site config already exist, RAM'den çekildi.");
            return $GLOBALS["site_config"];
        }*/
        if (Data::has("site_config")) {
            if ($meta) { 
                Data::set("site_config.meta", $meta);
            }
            if ($jsLoad) { 
                Data::set("site_config.loaded", true);
            }
            return Data::get("site_config");
        }

        if (!defined('SITE_ASSETS')) {
            self::getInstance()->site_assets($jsLoad, $meta);
        }

        //error_log("site config preparing...");
        
        /*$is_cached = false;
        //if(function_exists("wprocket_is_cached")){
        if (defined("WP_ROCKET_VERSION") && function_exists("is_wp_rocket_crawling")) {
            $is_cached = wprocket_is_cached();// is_wp_rocket_crawling();
        }
        error_log("site config is_cached => ".$is_cached);*/

        // --- A. TÜM ÖZELLİK BAYRAKLARI (FLAGS) ---
        $enable_favs    = defined('ENABLE_FAVORITES') && ENABLE_FAVORITES;
        $enable_follow  = defined('ENABLE_FOLLOW') && ENABLE_FOLLOW;
        $enable_history = defined('ENABLE_SEARCH_HISTORY') && ENABLE_SEARCH_HISTORY;
        $enable_ip      = defined('ENABLE_IP2COUNTRY') && ENABLE_IP2COUNTRY;
        $enable_reg     = defined('ENABLE_REGIONAL_POSTS') && ENABLE_REGIONAL_POSTS;
        $path           = function_exists('getSiteSubfolder') ? getSiteSubfolder() : '/';

            // --- B. ANA KONFİGÜRASYON DİZİSİ ---
            $config = [
                "enable_membership"     => defined('ENABLE_MEMBERSHIP') && ENABLE_MEMBERSHIP,
                "enable_favorites"      => $enable_favs,
                "enable_follow"         => $enable_follow,
                "enable_search_history" => $enable_history,
                "enable_ip2country"     => $enable_ip,
                "enable_cart"           => defined('ENABLE_CART') && ENABLE_CART,
                "enable_filters"        => defined('ENABLE_FILTERS') && ENABLE_FILTERS,
                "enable_chat"           => defined('ENABLE_CHAT') && ENABLE_CHAT,
                "enable_notifications"  => defined('ENABLE_NOTIFICATIONS') && ENABLE_NOTIFICATIONS,
                "enable_ecommerce"      => defined('ENABLE_ECOMMERCE') && ENABLE_ECOMMERCE,
                "path"                  => $path,
                "loaded"                => (bool)$jsLoad,
                "logged"                => is_user_logged_in(),
                "cached"                => false,
                "debug"                 => defined('ENABLE_CONSOLE_LOGS') && ENABLE_CONSOLE_LOGS,
                "language_default"      => Data::get("language_default"),//$GLOBALS['language_default'] ?? 'en',
                "base_urls"             => Data::get("base_urls") ?? [],//$GLOBALS['base_urls'] ?? [],
                "lcp" => [
                    "d" => (defined('SITE_ASSETS') && !empty(SITE_ASSETS["lcp"]["desktop"])) ? 1 : 0,
                    "m" => (defined('SITE_ASSETS') && !empty(SITE_ASSETS["lcp"]["mobile"])) ? 1 : 0
                ]
            ];

            if (defined('DOING_ROCKET_CACHE') && DOING_ROCKET_CACHE) {
                $config['cached'] = true;
            }

            // --- C. FAVORİLER VE TAKİP SİSTEMİ ---
            if ($enable_favs && class_exists('Favorites')) {
                $fav_obj = new \Favorites();
                $fav_obj->update();
                $config["favorites"] = $fav_obj->favorites;
                $config["favorite_types"] = defined('FAVORITE_TYPES') ? FAVORITE_TYPES : [];
            }

            if ($enable_follow) {
                $config["follow_types"] = defined('FOLLOW_TYPES') ? FOLLOW_TYPES : [];
            }

            // --- D. ARAMA GEÇMİŞİ ---
            if ($enable_history && class_exists('SearchHistory')) {
                $sh_obj = new \SearchHistory();
                $config["search_history"] = $sh_obj->get_user_terms();
            }

            // --- E. GÜVENLİK (NONCE) ---
            if (!$config["logged"]) {
                $config["nonce"] = wp_create_nonce('ajax');
            }

            // --- F. IP TABANLI LOKALİZASYON VE ÇEREZLER ---
            if ($enable_ip) {
                $user_data = [
                    'country' => $_COOKIE['user_country'] ?? '',
                    'code'    => $_COOKIE['user_country_code'] ?? '',
                    'city'    => $_COOKIE['user_city'] ?? '',
                    'lang'    => $_COOKIE['user_language'] ?? '',
                    'region'  => $_COOKIE['user_region'] ?? ''
                ];

                // Çerezler eksikse IP'den bul
                if (empty($user_data['city']) || empty($user_data['code'])) {
                    global $salt; 
                    if(isset($salt)){
                        $data = (isset($salt->localization)) ? $salt->localization->ip_info() : null;
                        if ($data) {
                            $user_data['country'] = $data->name ?? ($data['name'] ?? 'Unknown');
                            $user_data['code']    = $data->iso2 ?? ($data['iso2'] ?? '');
                            $user_data['city']    = $data->state ?? ($data['state'] ?? 'Unknown');
                        }
                    }
                }

                // Dil tespiti (Senin universal fonksiyonun)
                if (empty($user_data['lang'])) {
                    $user_data['lang'] = ml_get_current_language();
                }

                // Bölgesel içerik aktifse
                if ($enable_reg && empty($user_data['region']) && !empty($user_data['code'])) {
                    $user_data['region'] = function_exists('get_region_by_country_code') ? get_region_by_country_code($user_data['code']) : '';
                }

                // Çerezleri sadece değişmişse yaz (Performans ve Header güvenliği için)
                $cookie_time = time() + (86400 * 365);
                foreach ($user_data as $key => $val) {
                    $c_name = "user_" . ($key == 'lang' ? 'language' : $key);
                    if (!isset($_COOKIE[$c_name]) || $_COOKIE[$c_name] !== (string)$val) {
                        if (!headers_sent()) {
                            setcookie($c_name, (string)$val, $cookie_time, $path);
                        }
                    }
                    $config[$c_name] = $val;
                }
            } else {
                // IP localization kapalıysa sadece dili al
                $config["user_language"] = Data::get("language");//$GLOBALS["language"];//ml_get_current_language();
            }

            // --- G. STATİK DOSYALAR (JSON) ---
            static $cached_js = null;
            if ($cached_js === null) {
                $js_json_path = get_stylesheet_directory() . "/static/js/js_files.json";
                $cached_js = file_exists($js_json_path) ? json_decode(file_get_contents($js_json_path), true) : [];
            }
            $config["required_js"] = $cached_js;

            // --- H. URL TANIMLARI ---
            //$config["theme_includes_url"] = defined("SH_INCLUDES_URL") ? SH_INCLUDES_URL : '';
            $config["theme_url"]          = defined("THEME_URL") ? THEME_URL : '';

            // --- I. ÇEVİRİ SÖZLÜĞÜ ---
            $config["dictionary"] = [];
            if (class_exists("TranslationDictionary")) {
                $dictionary = new \TranslationDictionary();
                $config["dictionary"] = $dictionary->getDictionary();
            }

            // --- J. META VERİSİ ---
            if (!empty($meta)) {
                $config["meta"] = $meta;
            }

            if($jsLoad){
                $config["loaded"] = true;
            }

            $config = apply_filters('site_config', $config);


            // Global'e yazalım
            //$GLOBALS["site_config"] = $config;
            Data::set("site_config", $config);

        //error_log("site config is completed");

        return Data::get("site_config");//$GLOBALS["site_config"];  
    }
    /*public function site_config_js() {
        // 1. Admin veya tema yoksa çık
        if (is_admin() || (defined("SH_THEME_EXISTS") && !SH_THEME_EXISTS)) {
            return [];
        }

        // 2. Config Verilerini Hazırla
        // Eğer get_site_config() içinde zaten hesaplandıysa global'den çek, yoksa oluştur.
        //$site_config = isset($GLOBALS["site_config"]) ? $GLOBALS["site_config"] : self::get_site_config();
        $site_config = Data::has("site_config") ? Data::get("site_config") : self::get_site_config();

        // 3. Assetleri Hazırla (Önce bu çalışmalı ki SITE_ASSETS define edilsin)
        //$this->site_assets();
        if (!defined('SITE_ASSETS')) {
            $this->site_assets();
        }

        // 4. Meta Verilerini Eşitle
        if (defined("SITE_ASSETS") && is_array(SITE_ASSETS) && isset(SITE_ASSETS["meta"])) {
            $site_config["meta"] = SITE_ASSETS["meta"];
        }

        // 5. JavaScript Değişkenlerini Oluştur (Inline Script)
        // wp_localize_script yerine wp_add_inline_script kullanıyoruz çünkü daha performanslı ve kontrol bizde.
        wp_register_script('site_config_vars', false);
        wp_enqueue_script('site_config_vars');

        $js_data = 'var site_config = ' . json_encode($site_config) . ';' . PHP_EOL;
        $js_data .= 'var required_js = ' . json_encode($site_config["required_js"] ?? []) . ';' . PHP_EOL;

        // 6. Conditional Plugins (Sadece sayfaya özel olanlar)
        if (defined("SITE_ASSETS") && is_array(SITE_ASSETS)) {
            $conditional = SITE_ASSETS["plugins"] ?? [];
            $js_data .= 'var conditional_js = ' . json_encode(array_values((array)$conditional)) . ';' . PHP_EOL;
        }

        // 7. Ajax ve Path Ayarları
        $upload_dir = wp_upload_dir();
        $upload_url = trailingslashit($upload_dir['baseurl']);

        $ajax_vars = [
            'url'        => trailingslashit(home_url()),
            'url_admin'  => admin_url('admin-ajax.php'),
            'site_url'   => get_option('home'),
            'theme_url'  => trailingslashit(get_stylesheet_directory_uri()),
            'upload_url' => $upload_url,
            'ajax_nonce' => wp_create_nonce('ajax'),
            'title'      => get_the_title() // Boş kalmasın, o anki sayfa başlığını verelim
        ];

        // YoBro Chat entegrasyonu
        if (class_exists("Redq_YoBro")) {
            $user_id = get_current_user_id();
            if ($user_id) {
                $conversations = yobro_get_all_conversations($user_id);
                if ($conversations) {
                    $ajax_vars["conversations"] = $conversations;
                }
            }
        }

        $js_data .= 'var ajax_request_vars = ' . json_encode($ajax_vars) . ';' . PHP_EOL;
        
        // JS verisini bas
        wp_add_inline_script('site_config_vars', $js_data);

        // 8. Sayfaya Özel Dinamik CSS (Separate CSS aktifse)
        if (defined("SITE_ASSETS") && !empty(SITE_ASSETS["css"]) && !isset($_GET['fetch']) && defined('SEPERATE_CSS') && SEPERATE_CSS) {
            wp_register_style('page-styles', false);
            wp_enqueue_style('page-styles');

            // URL yerleştirmelerini yapalım
            $css_code = str_replace(
                ["{upload_url}", "{home_url}"],
                [$upload_url, home_url("/")],
                SITE_ASSETS["css"]
            );

            wp_add_inline_style('page-styles', $css_code);
        }
    }*/

    public function site_config_js() {

        if (is_admin() || (defined("SH_THEME_EXISTS") && !SH_THEME_EXISTS)) {
            return;
        }

        $site_config = Data::has("site_config")
            ? Data::get("site_config")
            : self::get_site_config();

        if (!defined('SITE_ASSETS')) {
            $this->site_assets();
        }

        if (defined("SITE_ASSETS") && is_array(SITE_ASSETS)) {
            $site_config["meta"] = SITE_ASSETS["meta"] ?? null;
            $site_config["conditional_js"] = array_values((array)(SITE_ASSETS["plugins"] ?? []));
        }

        $site_config["required_js"] = $site_config["required_js"] ?? [];

        $upload_dir = wp_upload_dir();
        $upload_url = trailingslashit($upload_dir['baseurl']);

        $site_config["ajax"] = [
            'url'        => trailingslashit(home_url()),
            'url_admin'  => admin_url('admin-ajax.php'),
            'site_url'   => get_option('home'),
            'theme_url'  => trailingslashit(get_stylesheet_directory_uri()),
            'upload_url' => $upload_url,
            'ajax_nonce' => wp_create_nonce('ajax'),
            'title'      => get_the_title()
        ];

        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

        if (ENABLE_PRODUCTION) {
            $flags |= JSON_PRETTY_PRINT;
        }

        echo '<script type="application/json" id="site-config">';
        echo wp_json_encode($site_config, $flags);
        echo '</script>';
        
        ?>

        <script>
        (function(){
            const el = document.getElementById('site-config');
            if(!el) return;

            const config = JSON.parse(el.textContent);

            Object.defineProperty(window, 'site_config', {
                value: config,
                writable: false,
                configurable: false
            });

            window.required_js = config.required_js || [];
            window.conditional_js = config.conditional_js || [];
            window.ajax_request_vars = config.ajax || {};
        })();
        </script>

        <?php

        if (defined("SITE_ASSETS") && !empty(SITE_ASSETS["css"]) && !isset($_GET['fetch']) && defined('SEPERATE_CSS') && SEPERATE_CSS) {
            wp_register_style('page-styles', false);
            wp_enqueue_style('page-styles');

            // URL yerleştirmelerini yapalım
            $css_code = str_replace(
                ["{upload_url}", "{home_url}"],
                [$upload_url, home_url("/")],
                SITE_ASSETS["css"]
            );

            wp_add_inline_style('page-styles', $css_code);
        }
    }





    public function remove_comments() {
        // 1. Sadece admin panelinde ve gerekli sabit tanımlıysa çalış
        if (!is_admin() || !defined('DISABLE_COMMENTS')) {
            return;
        }

        // WordPress eklenti fonksiyonlarını garantiye alalım
        if (!function_exists('activate_plugin') || !function_exists('deactivate_plugins')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }

        $plugin_filename = "remove-comments-absolute.php";
        $source_path     = SH_INCLUDES_PATH . "/" . $plugin_filename;
        $destination_path = WP_PLUGIN_DIR . "/" . $plugin_filename;

        if (DISABLE_COMMENTS) {
            // Eklenti sınıfı yoksa ve dosya kaynakta mevcutsa
            if (!class_exists("Remove_Comments_Absolute")) {
                
                // Eğer dosya plugin klasöründe yoksa kopyala
                if (!file_exists($destination_path)) {
                    if (file_exists($source_path)) {
                        copy($source_path, $destination_path);
                    }
                }

                // Dosya artık oradaysa eklentiyi aktifleştir
                if (file_exists($destination_path)) {
                    activate_plugin($plugin_filename);
                }
            }
        } else {
            // Devre dışı bırakılmak isteniyorsa
            if (class_exists("Remove_Comments_Absolute") || is_plugin_active($plugin_filename)) {
                
                // Önce pasif yap
                deactivate_plugins($plugin_filename);
                
                // Sonra dosyayı sil (Eklentiler listesinde kalabalık yapmasın)
                if (file_exists($destination_path)) {
                    unlink($destination_path);
                }
            }
        }
    }

    public function wc_custom_template_path($path) {
        return '/theme/woocommerce/'; // yani artık şu klasöre bakacak: your-theme/theme/woocommerce/
    }
    public function wc_multiple_template_paths($template, $template_name, $template_path) {
        // 1. Bakılacak klasörleri tanımla
        $paths = [
            get_template_directory() . '/woocommerce/',
            get_template_directory() . '/theme/woocommerce/',
        ];

        // 2. Dosya ismindeki alt çizgileri tireye çevir (Standartlaştırma)
        $clean_template_name = str_replace("_", "-", $template_name);

        // 3. Hiyerarşik kontrol (Hangi klasörde varsa onu çek)
        foreach ($paths as $dir) {
            $full_path = trailingslashit($dir) . $clean_template_name;
            
            if (file_exists($full_path)) {
                return $full_path; 
            }
        }

        // 4. Hiçbirinde yoksa orijinal template yoluna dön
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
        }, 20); 
	}
}