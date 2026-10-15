<?php

namespace SaltHareket;

use ScssPhp\ScssPhp\Compiler;

// ── EARLY PERFORMANCE FLAGS ─────────────────────────────────────────────
// Memory limit artır
if (!ini_get('memory_limit') || ini_get('memory_limit') < '2G') {
    ini_set('memory_limit', '2G');
}

set_time_limit(300); // 5 dakika max execution time
// use ScssPhp\ScssPhp\OutputStyle; // v2.0 için hazırlık

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


/**
 * Theme â€” Ana tema class'i. Singleton.
 *
 * Post type/taxonomy register, language settings, global variables,
 * site config, query modifications, admin menu, SCSS compile.
 *
 * KULLANIM:
 *   $theme = Theme::getInstance();
 *   $theme->init();
 *
 *   // Site config (JS'e aktarilan global ayarlar)
 *   $config = Theme::get_site_config();
 *
 *   // SCSS compile (admin'den tetiklenir)
 *   $errors = Theme::scss_compile();
 *
 * @package SaltHareket
 */
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

        add_action("template_redirect", [$this, "site_assets"]);
        add_action("template_redirect", [$this, "global_variables"], 10);
                
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
            ////add_filter('woocommerce_locate_template', [$this, 'wc_multiple_template_paths'], 10, 3);
            add_filter('wc_get_template', [$this, 'wc_smart_template_path'], 10, 5);
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
                maintenance_check_visibility();
            });
        }else{
            if(SH_THEME_EXISTS){
                //add_action("wp_enqueue_scripts", "load_frontend_files", 20);
            }
            add_action('wp_head', [$this, 'site_config_js'], 1 );//add_action( 'wp_enqueue_scripts', [$this, 'site_config_js'], 20 );
            add_filter('body_class', [$this, 'body_class'] );
            add_action("wp", function(){
                maintenance_check_visibility();
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
        // 1. Ana menü oluştur
        add_menu_page(
            'Theme Settings',
            'Theme Settings',
            'manage_options',
            'theme-settings',
            '', // Burası boşsa alt menüden ilkini tıklar
            'dashicons-admin-generic',
            90
        );

        // 2. Theme Update
        add_submenu_page(
            'theme-settings',
            'Theme Update',
            'Theme Update',
            'manage_options',
            'update-theme',
            ['Update', 'render_page'],
            1
        );

        // 3. Plugin Manager
        /*add_submenu_page(
            'theme-settings',
            'Plugin Manager',
            'Plugin Manager',
            'manage_options',
            'plugin-manager',
            [ \SaltHareket\PluginManager\Admin\PluginManagerAdmin::class, 'renderPage' ],
            2
        );*/

        // Video Process → Artık apps/video-processor/ altında (sh-video-processor sayfası)
        // Eski 'video-process' sayfası kaldırıldı

        // Bu fonksiyon zaten admin_menu hook'u içinde çalışıyor.
        // $submenu burada dolu olduğundan doğrudan unset yapabiliriz.
        // İç içe add_action('admin_menu') açmaya gerek yok â€” döngü yaratır.
        global $submenu;
        if ( isset( $submenu['theme-settings'] ) ) {
            unset( $submenu['theme-settings'][0] );
        }
    }

    public function menu_actions(){
        register_nav_menus(get_menu_locations());
        if (function_exists("acf_add_options_page")) {
                $menu = [
                    //"Anasayfa",
                    //"Header",
                    //"Footer",
                    //"Menu",
                    //"Theme Styles",
                    //"Ayarlar",
                    //"Page Assets Update",
                    //"Development",
                ];
                /*if(ENABLE_SEARCH_HISTORY){
                    $menu[] = "Search Ranks";
                }*/
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

        add_editor_style('style.css');
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
        \SaltProfiler::mark('acf_init start');

        $post_pagination = [];
        if (!$this->is_admin) {
            // ACF'ten değil — SaltConfig (wp_options cache) üzerinden oku
            $raw = \SaltHareket\SaltConfig::get('options_post_pagination', []);
            if (is_array($raw) && !empty($raw)) {
                $post_pagination_tmp = [];
                foreach ($raw as $item) {
                    if (!isset($item['post_type'])) continue;
                    $pt = $item['post_type'];
                    $posts_per_page = (!empty($item['paged']))
                        ? intval($item['catalog_rows']) * intval($item['catalog_columns'])
                        : -1;
                    $item['posts_per_page'] = $posts_per_page;
                    unset($item['post_type']);
                    $post_pagination_tmp[$pt] = $item;
                }
                $post_pagination = $post_pagination_tmp;
            }

            // search_pagination — SaltConfig'den oku
            $search_pagination = \SaltHareket\SaltConfig::get('options_search_pagination', []);
            if ( ! empty($search_pagination) && ! empty($search_pagination['paged']) ) {
                $search_pagination['posts_per_page'] = intval($search_pagination['catalog_rows']) * intval($search_pagination['catalog_columns']);
                $post_pagination['search'] = $search_pagination;
            }
        }
        Data::set("post_pagination", $post_pagination);

        // add_sticky_support — SaltConfig'den oku
        $sticky_types = \SaltHareket\SaltConfig::get('options_add_sticky_support', []);
        if ($sticky_types) {
            foreach ((array) $sticky_types as $post_type) {
                if ($post_type) add_post_type_support($post_type, 'sticky');
            }
        }
        \SaltProfiler::mark('acf_init end');
    }

    public function global_variables() {
        \SaltProfiler::mark('global_variables: start');
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
            $user_roles = (array) $current_user->roles;
        } else {
            $timber_user = \Timber::get_user();
            \SaltProfiler::mark('global_variables: after get_user');
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
        if (!Data::has("site_config")) {
            \SaltProfiler::mark('global_variables: get_site_config start');
            Data::set("site_config", self::get_site_config());
            \SaltProfiler::mark('global_variables: get_site_config end');
        }
        $site_config = Data::get("site_config");

        \SaltProfiler::mark('global_variables: after site_config');
        if (!$this->is_admin && ENABLE_IP2COUNTRY) {
            $user->user_country = $site_config["user_country"] ?? '';
            $user->user_country_code = $site_config["user_country_code"] ?? '';
            $user->user_city = $site_config["user_city"] ?? '';
        }

        // 5. Favoriler ve Arama Geçmişi (Global atamalar)
        if (ENABLE_REACTIONS) {
            $favs = $site_config["favorites"] ?? [];
            //$GLOBALS["favorites"] = is_string($favs) ? json_decode($favs, true) : (is_array($favs) ? $favs : []);
            $favorites = is_string($favs) ? json_decode($favs, true) : (is_array($favs) ? $favs : []);
            Data::set("favorites", $favorites);
        }

        if (ENABLE_SEARCH_HISTORY) {
            Data::set("search_history", $site_config["search_history"] ?? []);
        }

        \SaltProfiler::mark('global_variables: after reactions/history');

        // 6. Üyelik ve Lokasyon Mantığı (Sadece Frontend ve Login durumunda)
        if (!$this->is_admin && ENABLE_MEMBERSHIP && $this->is_logged) {
            \SaltProfiler::mark('global_variables: membership block start');
            // IP bazlı lokasyon güncelleme — sadece development modunda çalışır
            // Production'da dış API çağrısı yavaşlattığından devre dışı
            if ( defined('ENABLE_DEVELOPMENT') && ENABLE_DEVELOPMENT && (empty($user->billing_country) || empty($user->billing_state)) && $salt && $salt->is_ip_changed() && ENABLE_IP2COUNTRY) {
                // LocationManager::ipInfo() — eski $salt->localization->ip_info() deprecated
                $login_location = \SaltHareket\Localization\LocationManager::getInstance()->ipInfo();
                
                if ($login_location) {
                    $user->login_location  = $login_location;
                    $user->billing_country = $login_location['country_code'] ?? '';
                    
                    // Şehir/eyalet eşleştirmesi — state varsa ara
                    $state_name = $login_location['state'] ?? '';
                    if ( $state_name ) {
                        global $wpdb;
                        $city_id = $wpdb->get_var( $wpdb->prepare(
                            "SELECT id FROM {$wpdb->prefix}states WHERE name LIKE %s LIMIT 1",
                            $state_name
                        ) );
                        if ( $city_id ) {
                            $user->city          = $city_id;
                            $user->billing_state = $city_id;
                        }
                    }
                }
                if (session_id()) session_write_close();
            }

            // Newsletter ve Mesaj Sayıları
            // Newsletter status çok yavaş (5+ saniye) — tamamen devre dışı bırakıldı
            // Skip ederken bile boolean değer döndürmek yerine hiç işleme
            if (class_exists("Newsletter") && !defined('SKIP_NEWSLETTER_FOR_PERFORMANCE') && false) { // false ekleyerek tamamen kapattık
                \SaltProfiler::mark('global_variables: newsletter start');
                $user->newsletter = $salt->newsletter("status", $user->user_email);
                \SaltProfiler::mark('global_variables: newsletter end');
            } else {
                $user->newsletter = false; // Default değer
            }

            \SaltProfiler::mark('global_variables: messages_count start');
            // Geçici bypass - Messages count çok yavaş (54 saniye)
            // $user->messages_count = (ENABLE_CHAT && class_exists('Messenger')) ? Messenger::count() : 0;
            $user->messages_count = 0;
            \SaltProfiler::mark('global_variables: notification_count start');
            $user->notification_count  = (ENABLE_NOTIFICATIONS && $salt) ? $salt->notification_count() : 0;
            $user->notifications_count = $user->notification_count; // twig uyumu
            \SaltProfiler::mark('global_variables: menu start');
            $user->menu = function_exists('get_account_menu') ? get_account_menu() : [];
            \SaltProfiler::mark('global_variables: menu end');
        }

        // 9. Final Global Atamaları
        if ($salt) $salt->user = $user;
        //$GLOBALS["user"] = $user;
        //$GLOBALS["salt"] = $salt;
        Data::set("user", $user);
        Data::set("salt", $salt);
        \SaltProfiler::mark('global_variables: end');
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
        \SaltProfiler::mark('lang_settings start');
        if (ENABLE_MULTILANGUAGE) {
            $language = ml_get_current_language();
            $language_default = ml_get_default_language();
            $languages = [];
            $language_url_view = true;
            $language_rtl   = false;

            // Cache key: post_type + queried_object_id + language
            // URL bazlı değil â€” aynı post_type'taki sayfalar aynı dil listesini paylaşır.
            // My-account endpoint'leri için endpoint da key'e dahil edilir.
            $queried_obj_id = get_queried_object_id();
            $post_type      = get_post_type() ?: get_query_var('post_type') ?: 'general';

            // My-account virtual endpoint algıla â€” cache key'e ekle
            $current_ep = '';
            if ( defined('ENABLE_MEMBERSHIP') && ENABLE_MEMBERSHIP ) {
                // WC query var üzerinden dene
                if ( defined('ENABLE_ECOMMERCE') && ENABLE_ECOMMERCE && function_exists('WC') && WC()->query ) {
                    $wc_ep = WC()->query->get_current_endpoint();
                    $virtual_eps = ['notifications','favorites','messages','reviews','security','profile','not-activated','renew-password'];
                    if ( $wc_ep && in_array($wc_ep, $virtual_eps, true) ) {
                        $current_ep = $wc_ep;
                    }
                }
                // WC bulamazsa URL'den çıkar
                if ( ! $current_ep ) {
                    $ma_page_id = 0;
                    if ( defined('ENABLE_ECOMMERCE') && ENABLE_ECOMMERCE && function_exists('wc_get_page_id') ) {
                        $ma_page_id = wc_get_page_id('myaccount');
                    }
                    if ( ! $ma_page_id ) $ma_page_id = (int) \QueryCache::get_option('options_myaccount_page_id', 0);
                    if ( $ma_page_id && $queried_obj_id === $ma_page_id ) {
                        // WP'nin ayrıştırdığı path'den endpoint'i çıkar
                        $wp_request  = isset($GLOBALS['wp']->request) ? $GLOBALS['wp']->request : '';
                        // query var üzerinden dene (add_rewrite_endpoint ile eklenenler)
                        $virtual_eps = ['notifications','favorites','messages','reviews','security','profile','not-activated','renew-password'];
                        foreach ( $virtual_eps as $ep ) {
                            if ( get_query_var($ep, null) !== null && strpos($wp_request, $ep) !== false ) {
                                $current_ep = $ep;
                                break;
                            }
                        }
                        // Hiç bulunamadıysa raw path'den çıkar
                        if ( ! $current_ep && function_exists('pll_get_post') ) {
                            $current_lang   = ml_get_current_language();
                            $current_ma_id  = pll_get_post($ma_page_id, $current_lang) ?: $ma_page_id;
                            $current_ma_url = trailingslashit(get_permalink($current_ma_id));
                            $request_uri    = trailingslashit(home_url($wp_request));
                            if ( $current_ma_url && strpos($request_uri, $current_ma_url) === 0 ) {
                                $remainder  = trim(substr($request_uri, strlen($current_ma_url)), '/');
                                $ep_parts   = explode('/', $remainder);
                                $candidate  = $ep_parts[0] ?? '';
                                if ( $candidate && in_array($candidate, $virtual_eps, true) ) {
                                    $current_ep = $candidate;
                                }
                            }
                        }
                    }
                }
            }

            $post_type_key = is_array( $post_type ) ? implode( '_', $post_type ) : $post_type;
            $cache_key = 'sh_lang_cache_' . $language . '_' . $post_type_key . '_' . $queried_obj_id . ($current_ep ? '_' . ( is_array( $current_ep ) ? implode( '_', $current_ep ) : $current_ep ) : '');

            // WC shop/archive sayfalarında bypass_cache kaldırıldı — cache key zaten
            // queried_object_id + post_type içerdiğinden shop/category/tag için unique.
            // get_the_ID() loop riski yok çünkü key post_type+queried_obj_id bazlı.
            // PAE internal fetch sırasında transient'a yazma — yanlış cache üretir
            $is_internal_fetch = defined('IS_INTERNAL_FETCH') && IS_INTERNAL_FETCH;
            $skip_write_cache  = $is_internal_fetch;

            // Okuma her zaman yapılır — sadece PAE fetch sırasında yazma atlanır
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

                    // My-account virtual endpoint tespiti
                    // notifications, profile, favorites gibi slug'lar WP sayfası değil
                    $current_ma_endpoint = '';
                    if ( defined('ENABLE_MEMBERSHIP') && ENABLE_MEMBERSHIP ) {
                        $virtual_endpoints = ['notifications','favorites','messages','reviews','security','profile','not-activated','renew-password'];
                        // WC varsa WC query üzerinden dene
                        if ( defined('ENABLE_ECOMMERCE') && ENABLE_ECOMMERCE && function_exists('WC') && WC()->query ) {
                            $wc_ep = WC()->query->get_current_endpoint();
                            if ( in_array($wc_ep, $virtual_endpoints, true) ) {
                                $current_ma_endpoint = $wc_ep;
                            }
                        }
                        // WC'den bulunamadıysa query vars üzerinden dene
                        if ( empty($current_ma_endpoint) ) {
                            foreach ( $virtual_endpoints as $_ep ) {
                                if ( get_query_var($_ep, null) !== null && get_query_var($_ep, '__not_set__') !== '__not_set__' ) {
                                    $current_ma_endpoint = $_ep;
                                    break;
                                }
                            }
                        }
                        // HÃ¢lÃ¢ bulunamadıysa URL karşılaştırması yap
                        if ( empty($current_ma_endpoint) ) {
                            $ma_page_id = 0;
                            if ( defined('ENABLE_ECOMMERCE') && ENABLE_ECOMMERCE && function_exists('wc_get_page_id') ) {
                                $ma_page_id = wc_get_page_id('myaccount');
                            }
                            if ( ! $ma_page_id ) $ma_page_id = (int) \QueryCache::get_option('options_myaccount_page_id', 0);
                            if ( $ma_page_id ) {
                                $ma_url     = trailingslashit( get_permalink($ma_page_id) );
                                $wp_request = isset($GLOBALS['wp']->request) ? $GLOBALS['wp']->request : '';
                                $req_url    = trailingslashit( home_url($wp_request) );
                                if ( $ma_url && strpos($req_url, $ma_url) === 0 ) {
                                    $remainder = trim( substr($req_url, strlen($ma_url)), '/' );
                                    $candidate = explode('/', $remainder)[0] ?? '';
                                    if ( in_array($candidate, $virtual_endpoints, true) ) {
                                        $current_ma_endpoint = $candidate;
                                    }
                                }
                            }
                        }
                    }

                    foreach (pll_the_languages(['raw' => 1]) as $lang) {
                        $url = '';
                        $is_shop_now = function_exists('is_shop') && is_shop();
                        $wc_shop_id  = function_exists('wc_get_page_id') ? (int) wc_get_page_id('shop') : 0;
                        $is_shop_by_id = $wc_shop_id > 0 && $queried_obj_id === $wc_shop_id;

                        if ( $is_shop_now || $is_shop_by_id ) {
                            $shop_page_id = $wc_shop_id ?: (function_exists('wc_get_page_id') ? wc_get_page_id('shop') : 0);
                            $translated_shop_page_id = pll_get_post($shop_page_id, $lang['slug']);
                            $url = get_permalink($translated_shop_page_id ?: $shop_page_id);
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
                                    $taxonomy_prefix_remove = \SaltHareket\SaltConfig::get('options_taxonomy_prefix_remove', []);
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

                            // $current_ep cache key hesaplanirken zaten set edildi (yukarda)
                            // My-account virtual endpoint varsa hedef dil URL'sine ekle
                            if ( ! empty($current_ep) && $url ) {
                                $ma_page_id_ep = 0;
                                if ( defined('ENABLE_ECOMMERCE') && ENABLE_ECOMMERCE && function_exists('wc_get_page_id') ) {
                                    $ma_page_id_ep = wc_get_page_id('myaccount');
                                }
                                if ( ! $ma_page_id_ep ) $ma_page_id_ep = (int) \QueryCache::get_option('options_myaccount_page_id', 0);
                                if ( $ma_page_id_ep ) {
                                    $target_ma_id  = pll_get_post($ma_page_id_ep, $lang['slug']) ?: $ma_page_id_ep;
                                    $target_ma_url = trailingslashit(get_permalink($target_ma_id));
                                    $url = $target_ma_url . trailingslashit($current_ep);
                                }
                            }
                        }

                        if ($url && $paged && $paged > 1) {
                            $url = trailingslashit($url) . 'page/' . $paged . '/';
                        }

                        $languages[] = [
                            "name"      => $lang['slug'],
                            "name_long" => $lang['name'],
                            "locale"    => $lang['locale'],
                            "url"       => $url,
                            "active"    => $lang['current_lang'] ? true : false,
                            "flag"      => $lang['flag'] ?? '',
                            "flag_url"  => $lang['flag_url'] ?? '',
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

            // ðŸ’¾ SONRAKİ SEFER İÃ‡İN SAKLA (1 Saatlik cache)
            $data_to_cache = [
                "language" => $language,
                "languages" => $languages,
                "language_default" => $language_default,
                "language_url_view" => $language_url_view,
                "language_rtl" => $language_rtl
            ];

            // PAE internal fetch veya bypass durumunda cache'e yazma
            if ( ! $skip_write_cache ) {
                set_transient($cache_key, $data_to_cache, HOUR_IN_SECONDS);
            }
            //error_log("full langıages ayarlandı......");
        }
    }

    public function query_all_posts($query) {
        // 1. ERKEN Ã‡IKIŞ (PERFORMANS): Admin sorguları veya filtreleri bastırılmış sorgulara dokunma
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
                    $query->set("posts_per_page", Data::get("post_pagination.search.posts_per_page"));
                }

                // Arama sonuçlarından sayfaları/dataları hariç tut (Newsletter vb.)
                $exclude_ids = [];
                if (class_exists("Newsletter")) {
                    static $nl_id_cache = null;
                    if ( $nl_id_cache === null ) {
                        $nl_id_cache = (int) \QueryCache::get_option('newsletter_page');
                    }
                    if ($nl_id_cache) $exclude_ids[] = $nl_id_cache;
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
                // Taxonomy sayfasında post_type boş gelebilir â€” taxonomy'den çıkar
                if ((is_tax() || is_category() || is_tag()) && ($post_type === 'post' || empty($post_type))) {
                    $queried = get_queried_object();
                    if ($queried instanceof \WP_Term) {
                        $tax_obj = get_taxonomy($queried->taxonomy);
                        if ($tax_obj && !empty($tax_obj->object_type)) {
                            $post_type = $tax_obj->object_type[0];
                        }
                    }
                }
                // get_post_type_pagination fonksiyonun varsa onu çağırıyoruz
                $pagination = function_exists('get_post_type_pagination') ? get_post_type_pagination($post_type) : null;
                $ppp = (!empty($pagination["paged"]) && isset($pagination["posts_per_page"])) ? intval($pagination["posts_per_page"]) : -1;

                // WooCommerce ürün sayfalarında (product_cat vb.) posts_per_page'e dokunma
                // WooCommerce kendi loop_shop_per_page filter'ını kullansın
                $is_woo_product_query = function_exists('is_woocommerce') && is_woocommerce() && $post_type === 'product';
                if ($ppp > 0) {
                    $query->set("posts_per_page", $ppp);
                } elseif (!$is_woo_product_query) {
                    // WooCommerce dışı sayfalarda -1 set et (tüm postlar)
                    $query->set("posts_per_page", -1);
                }
                // WooCommerce ürün sayfasında ppp=-1 ise hiç dokunma
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
            static $sticky_types_cache = null;
            if ( $sticky_types_cache === null ) {
                $sticky_types_cache = \QueryCache::get_option("options_add_sticky_support") ?: false;
            }
            $sticky_types = $sticky_types_cache;
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
                static $sub_default_cat_id = null;
                if ( $sub_default_cat_id === null ) {
                    $sub_default_cat_id = (int) \QueryCache::get_option('default_category');
                    if ( function_exists('pll_get_term') && $sub_default_cat_id ) {
                        $sub_default_cat_id = (int) pll_get_term( $sub_default_cat_id );
                    }
                }
                if ($sub_default_cat_id) {
                    $tax_query = (array) $query->get('tax_query');
                    $tax_query[] = [
                        'taxonomy' => 'category',
                        'field'    => 'term_id',
                        'terms'    => $sub_default_cat_id,
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

        // 2. RECURSION KORUMASI — pll_get_term() kendi içinde get_terms() çağırıyor.
        // Bu filter tekrar tetiklenirse sonsuz döngüye girer. Static flag ile koruyoruz.
        static $is_running = false;
        if ($is_running) {
            return $args;
        }

        /**
         * 3. STATİK CACHE — dil bazlı (Polylang safe)
         * Her dil için ayrı ID listesi — dil değişince stale olmaz.
         */
        static $excluded_ids_map = [];
        $lang_key = function_exists('ml_get_current_language') ? ml_get_current_language() : 'default';

        if (!isset($excluded_ids_map[$lang_key])) {
            $is_running = true; // pll_get_term() çağrısı süresince döngüyü engelle
            $ids = [];

            // Standart WordPress Kategorisi (Uncategorized vb.)
            $default_cat = (int) \QueryCache::get_option('default_category');
            if ($default_cat) {
                $ids[] = function_exists('pll_get_term') ? (int) pll_get_term($default_cat) : $default_cat;
            }

            // WooCommerce Ürün Kategorisi (Uncategorized product cat vb.)
            if (class_exists('WooCommerce')) {
                $default_prod_cat = (int) \QueryCache::get_option('default_product_cat');
                if ($default_prod_cat) {
                    $ids[] = function_exists('pll_get_term') ? (int) pll_get_term($default_prod_cat) : $default_prod_cat;
                }
            }

            $is_running = false; // Kilidi serbest bırak
            $excluded_ids_map[$lang_key] = array_values(array_filter(array_unique($ids)));
        }

        $excluded_ids = $excluded_ids_map[$lang_key];

        // 4. TAKSONOMİ KONTROLÜ VE FİLTRELEME
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

    public function site_assets($jsLoad = 0, $meta = []){

        // Zaten tanımlıysa döndür
        if (defined('SITE_ASSETS')) {
            return SITE_ASSETS;
        }

        \SaltProfiler::mark('site_assets: start');

        // Erken Ã‡ıkış â€” admin, cron, REST
        if (
            $this->is_admin ||
            (defined("SH_THEME_EXISTS") && !SH_THEME_EXISTS) ||
            defined('DOING_CRON') ||
            (defined('REST_REQUEST') && REST_REQUEST)
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
            
            // WooCommerce shop sayfasi — gercek bir 'page' (wc_get_page_id('shop')).
            // Fetch de bu sayfayi type=post id=<shop_page_id> olarak kaydediyor
            // (PAE log: META SAVED type=post id=8426). Bu yuzden frontend de ayni
            // yerden okumali: post meta. Eski 'archive'/'product_archive_{lang}'
            // option yaklasimi fetch ile uyumsuzdu → css_page bulunamayip
            // main-combined.css fallback'ine dusuyordu.
            if (function_exists('is_shop') && is_shop()) {
                $shop_id = function_exists('wc_get_page_id') ? (int) wc_get_page_id('shop') : 0;
                if ($shop_id > 0) {
                    $meta['type'] = 'post';
                    $meta['id']   = $shop_id;
                } else {
                    // Guvenlik agi — shop page tanimli degilse eski archive davranisi
                    $meta['type'] = 'archive';
                    $lang         = Data::get("language");
                    $meta['id']   = $lang ? "product_archive_{$lang}" : "product_archive";
                }
            } elseif (function_exists('is_account_page') && is_account_page() && is_user_logged_in()) {
                // WC My Account endpoint sayfasi
                $endpoint = function_exists('WC') ? WC()->query->get_current_endpoint() : '';
                if (!empty($endpoint)) {
                    $lang = Data::get("language");
                    $meta['type'] = 'dynamic';
                    $meta['id']   = "woo_account_{$endpoint}_{$lang}";
                } else {
                    // Dashboard (endpoint yok) â€” normal page olarak isle
                    $meta['type'] = 'post';
                    $meta['id']   = get_queried_object_id();
                }
            } elseif (is_singular()) {
                $meta['type'] = 'post';
                $meta['id']   = $obj->ID;
            } elseif ($obj instanceof \WP_Term) {
                $meta['type'] = 'term';
                $meta['id']   = $obj->term_id;
                $meta['tax']  = $obj->taxonomy;
            } elseif (is_post_type_archive()) {
                $meta['type'] = 'archive';
                $pt           = get_post_type();
                $lang         = Data::get("language");//ml_get_current_language();//(defined('ENABLE_MULTILANGUAGE') && ENABLE_MULTILANGUAGE) ? ml_get_current_language() : "";
                $meta['id']   = $lang ? "{$pt}_archive_{$lang}" : "{$pt}_archive";
            } elseif (is_author()) {
                // Public profil sayfası (sh_profile_slug query var) → paylaşılan tek option
                if ( get_query_var('sh_profile_slug') ) {
                    $meta['type'] = 'dynamic';
                    $meta['id']   = 'public_profile';
                } else {
                    $meta['type'] = 'user';
                    $meta['id']   = get_queried_object_id();
                }
            } elseif (is_search()) {
                $meta['type'] = 'dynamic';
                $pt           = "search";
                $lang         = Data::get("language");//ml_get_current_language();//(defined('ENABLE_MULTILANGUAGE') && ENABLE_MULTILANGUAGE) ? ml_get_current_language() : "";
                $meta['id']   = $lang ? "{$pt}_{$lang}" : "{$pt}_archive";
            } elseif (is_404()) {
                $meta['type'] = 'dynamic';
                $pt           = "404";
                $lang         = Data::get("language");//ml_get_current_language();//(defined('ENABLE_MULTILANGUAGE') && ENABLE_MULTILANGUAGE) ? ml_get_current_language() : "";
                $meta['id']   = $lang ? "{$pt}_{$lang}" : "{$pt}_archive";
            }
        }

        // 3. Asset Çekimi (Meta tipine göre tek noktadan)
        if (!empty($meta['id'])) {
            $site_assets = match($meta['type']) {
                'post'    => get_post_meta($meta['id'], 'assets', true),
                'term'    => get_term_meta($meta['id'], 'assets', true),
                'user'    => get_user_meta($meta['id'], 'assets', true),
                'archive' => \QueryCache::get_option($meta['id'] . '_assets') ?: null,
                'dynamic' => \QueryCache::get_option($meta['id'] . '_assets') ?: null,
                default   => null
            };
            // get_term_meta / get_post_meta boş string döndürebilir — normalize et
            if ( $site_assets === '' || $site_assets === false ) {
                $site_assets = null;
            }
        }

        // 4. Asset Yoksa Yeniden Üret (Extractor)
        // Sadece admin değilse ve internal fetch değilse çalıştır — PAE döngüsünü önle
        $is_internal = defined('IS_INTERNAL_FETCH') && IS_INTERNAL_FETCH;

        // ── Auto-Fetch: Toggle açıksa kontrol et ────────────────────────────
        // Toggle kapalıyken hiç çağrılmaz — 0 overhead
        if ( class_exists('SaltHareket\AssetManager\AssetSettings') 
             && \SaltHareket\AssetManager\AssetSettings::getSetting('auto_fetch') ) {
            $site_assets = \SaltHareket\AssetManager\AssetManager::maybeAutoFetch( 
                $site_assets, 
                $meta, 
                $is_internal 
            );
        }

        // Veri hala boşsa default'u bas, doluysa meta'yı güncelle
        if (!empty($site_assets) && is_array($site_assets)) {
            $site_assets["meta"] = $meta;
        } else {
            $assets_defaults["meta"] = $meta;
            $site_assets = $assets_defaults;
        }

        // ── Template plugin merge ────────────────────────────────────────────
        // Aktif header/footer template post'larinin plugin listelerini sayfanin
        // plugin listesiyle birlestir. Template plugin'leri ayri bundle'a yazilir
        // (template_plugin_js / template_plugin_css) — sayfanin assets'ina dokunulmaz.
        if ( ! $is_internal && ! empty( $site_assets ) ) {
            $template_assets = self::_merge_template_plugin_assets( $site_assets );
            if ( ! empty( $template_assets ) ) {
                $site_assets['template_plugin_js']        = $template_assets['plugin_js']        ?? '';
                $site_assets['template_plugin_css']       = $template_assets['plugin_css']       ?? '';
                $site_assets['template_plugin_js_files']  = $template_assets['plugin_js_files']  ?? [];
                $site_assets['template_plugin_css_files'] = $template_assets['plugin_css_files'] ?? [];
                $site_assets['template_css_pages']        = $template_assets['css_pages']        ?? [];
                $site_assets['template_plugins']          = $template_assets['plugins']          ?? [];

                // Template plugin'lerini sayfanin plugin listesine merge et
                // Boylece site_config.conditional_js'e dahil olur, init fonksiyonlari otomatik calisir
                $existing_plugins = (array) ( $site_assets['plugins'] ?? [] );
                $tmpl_plugins     = (array) ( $template_assets['plugins'] ?? [] );
                if ( ! empty( $tmpl_plugins ) ) {
                    $site_assets['plugins'] = array_values( array_unique(
                        array_merge( $existing_plugins, $tmpl_plugins )
                    ) );
                }
            }
        }

        // 5. Global Tanımlama ve LCP Başlatma
        define("SITE_ASSETS", $site_assets);

        \SaltProfiler::mark('site_assets: defined, starting LCP');

        if (!isset($_GET["fetch"]) && class_exists("Lcp") && !is_user_logged_in()) {
            \Lcp::getInstance();
        }
        \SaltProfiler::mark('site_assets: end');
    }

    /**
     * Aktif header/footer template post'larinin plugin listelerini merge eder.
     * Template save edilince PAE zaten fetch yapar ve post_meta('assets') kaydeder.
     * Burada sadece okuma yapilir — bundle olusturma yok, sifir overhead.
     *
     * SITE_ASSETS'a eklenen key'ler:
     *   template_plugin_js  — template plugin JS bundle path
     *   template_plugin_css — template plugin CSS bundle path
     *
     * @param  array $site_assets Mevcut sayfa asset'i
     * @return array|null
     */
    private static function _merge_template_plugin_assets( array $site_assets ): ?array {
        // Header/footer template ID'lerini SaltConfig'den oku — ACF olmadan da calisir
        $template_ids = [];

        $header_tpl = \SaltHareket\SaltConfig::get( 'options_default_header_template', '' );
        $footer_tpl = \SaltHareket\SaltConfig::get( 'options_default_footer_template', '' );

        if ( ! empty( $header_tpl ) && is_numeric( $header_tpl ) && (int) $header_tpl > 0 ) {
            $template_ids[] = (int) $header_tpl;
        }
        if ( ! empty( $footer_tpl ) && is_numeric( $footer_tpl ) && (int) $footer_tpl > 0 ) {
            $template_ids[] = (int) $footer_tpl;
        }

        // ACF options'tan da dene (aktifse)
        if ( function_exists( 'get_field' ) ) {
            foreach ( [ 'header_template', 'footer_template' ] as $field ) {
                $val = get_field( $field, 'options' );
                if ( ! empty( $val ) && is_numeric( $val ) ) $template_ids[] = (int) $val;
            }
        }

        $template_ids = array_values( array_unique( array_filter( $template_ids ) ) );
        if ( empty( $template_ids ) ) {
            // Hic dynamic template secili degil (static twig veya hic template yok).
            // compile_common_css() tarafindan tespit edilen static header/footer plugin'leri
            // sh_static_template_plugins option'unda saklanir. Buradan okuyup return et.
            $static_plugins = get_option( 'sh_static_template_plugins', [] );
            if ( ! empty( $static_plugins ) && is_array( $static_plugins ) ) {
                // Sayfanin zaten yukledigi plugin'leri cikar
                $page_plugins   = (array) ( $site_assets['plugins'] ?? [] );
                $extra_static   = array_values( array_diff( $static_plugins, $page_plugins ) );
                if ( ! empty( $extra_static ) ) {
                    return [
                        'plugin_js'        => '',
                        'plugin_css'       => '',
                        'plugin_js_files'  => [],
                        'plugin_css_files' => [],
                        'css_pages'        => [],
                        'plugins'          => $extra_static,
                        'is_static'        => true,
                    ];
                }
            }
            return null;
        }

        // Sayfanin zaten yukledigini al
        $page_plugins = (array) ( $site_assets['plugins'] ?? [] );
        $is_rtl = function_exists( 'is_rtl' ) && is_rtl();

        // Her template'in PAE assets meta'sini oku
        $merged_js         = [];
        $merged_css        = [];
        $merged_css_pages  = [];
        $all_extra_plugins = [];

        foreach ( $template_ids as $tid ) {
            $tmpl_assets = get_post_meta( $tid, 'assets', true );
            if ( ! is_array( $tmpl_assets ) ) continue;

            // Plugin listesi
            $tmpl_plugins = (array) ( $tmpl_assets['plugins'] ?? [] );
            $extra = array_diff( $tmpl_plugins, $page_plugins );
            if ( ! empty( $extra ) ) {
                $all_extra_plugins = array_merge( $all_extra_plugins, array_values( $extra ) );
            }

            // css_page — template'in inline CSS'i (RTL destegi)
            $css_key     = $is_rtl ? 'css_page_rtl' : 'css_page';
            $css_path    = $tmpl_assets[ $css_key ] ?? $tmpl_assets['css_page'] ?? '';
            if ( ! empty( $css_path )
                 && defined( 'STATIC_PATH' )
                 && file_exists( rtrim( STATIC_PATH, '/' ) . '/' . ltrim( $css_path, '/' ) ) ) {
                $merged_css_pages[] = $css_path;
            }

            // Inline CSS (block CSS vars, --header-total-height vs.)
            // NOT: header-builder ve header-row render.php'de <style data-inline> ile
            // dogrudan template HTML'ine ekleniyor. Buradan okumaya gerek yok.
            // $merged_inline_css artik kullanilmiyor.

            // Hazir bundle path'leri — PAE tarafindan template fetch sirasinda olusturulmus
            if ( ! empty( $tmpl_assets['plugin_js'] )
                 && defined( 'STATIC_PATH' )
                 && file_exists( rtrim( STATIC_PATH, '/' ) . '/' . ltrim( $tmpl_assets['plugin_js'], '/' ) ) ) {
                $merged_js[] = $tmpl_assets['plugin_js'];
            }
            if ( ! empty( $tmpl_assets['plugin_css'] )
                 && defined( 'STATIC_PATH' )
                 && file_exists( rtrim( STATIC_PATH, '/' ) . '/' . ltrim( $tmpl_assets['plugin_css'], '/' ) ) ) {
                $merged_css[] = $tmpl_assets['plugin_css'];
            }
        }

        if ( empty( $merged_js ) && empty( $merged_css ) && empty( $merged_css_pages ) ) return null;

        return [
            'plugin_js'       => $merged_js[0]  ?? '',   // geriye uyumluluk
            'plugin_css'      => $merged_css[0] ?? '',   // geriye uyumluluk
            'plugin_js_files' => $merged_js,              // tum template JS bundle path'leri
            'plugin_css_files'=> $merged_css,             // tum template CSS bundle path'leri
            'css_pages'       => $merged_css_pages,
            'plugins'         => array_values( array_unique( $all_extra_plugins ) ),
        ];
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

        // --- A. TÃœM ÖZELLİK BAYRAKLARI (FLAGS) ---
        $enable_reactions = defined('ENABLE_REACTIONS') && ENABLE_REACTIONS;
        $enable_history   = defined('ENABLE_SEARCH_HISTORY') && ENABLE_SEARCH_HISTORY;
        $enable_ip        = defined('ENABLE_IP2COUNTRY') && ENABLE_IP2COUNTRY;
        $enable_reg       = defined('ENABLE_REGIONAL_POSTS') && ENABLE_REGIONAL_POSTS;
        $path             = function_exists('getSiteSubfolder') ? getSiteSubfolder() : '/';

            // --- B. ANA KONFİGÃœRASYON DİZİSİ ---
            $config = [
                "enable_membership"     => defined('ENABLE_MEMBERSHIP') && ENABLE_MEMBERSHIP,
                "enable_reactions"      => $enable_reactions,
                "enable_favorites"      => $enable_reactions, // backward compat â€” JS favorites objesi hala kullanıyor
                "enable_follow"         => $enable_reactions, // backward compat
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
                "language_default"      => Data::get("language_default"),
                "base_urls"             => Data::get("base_urls") ?? [],
                "lcp" => [
                    "d" => (defined('SITE_ASSETS') && !empty(SITE_ASSETS["lcp"]["desktop"])) ? 1 : 0,
                    "m" => (defined('SITE_ASSETS') && !empty(SITE_ASSETS["lcp"]["mobile"])) ? 1 : 0
                ]
            ];

            if (defined('DOING_ROCKET_CACHE') && DOING_ROCKET_CACHE) {
                $config['cached'] = true;
            }

            // --- C. REACTİONS (Favorites + Follow unified) ---
            if ( $enable_reactions && is_user_logged_in() && class_exists('\SaltHareket\Reactions\Reactions') ) {
                $uid = get_current_user_id();
                // site_config.favorites â€” eski JS favorites objesi backward compat icin
                $config["favorites"] = \SaltHareket\Reactions\Reactions::getByUser( $uid, 'favorite', 'post' );
            }

            // --- D. ARAMA GEÃ‡MİŞİ ---
            if ($enable_history && class_exists('SearchHistory')) {
                $sh_obj = new \SearchHistory();
                $config["search_history"] = $sh_obj->get_user_terms();
            }

            // --- E. GÃœVENLİK (NONCE) ---
            if (!$config["logged"]) {
                $config["nonce"] = wp_create_nonce('ajax');
            }

            // --- F. IP TABANLI LOKALİZASYON VE Ã‡EREZLER ---
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
                    \SaltProfiler::mark('get_site_config: ipInfo start');
                    $lm = \SaltHareket\Localization\LocationManager::getInstance();
                    $data = $lm->ipInfo();
                    \SaltProfiler::mark('get_site_config: ipInfo end');
                    if ($data) {
                        $user_data['country'] = $data['name'] ?? 'Unknown';
                        $user_data['code']    = $data['iso2'] ?? ($data['country_code'] ?? '');
                        $user_data['city']    = $data['state'] ?? ($data['city'] ?? 'Unknown');
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

                // Ã‡erezleri sadece değişmişse yaz (Performans ve Header güvenliği için)
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
            // Cache key'e main.css filemtime dahil — deploy sonrası otomatik invalide
            $js_json_path  = get_stylesheet_directory() . "/static/js/js_files.json";
            $main_css_path = defined('STATIC_PATH') ? STATIC_PATH . 'css/main.css' : '';
            $build_mtime   = $main_css_path && file_exists($main_css_path) ? filemtime($main_css_path) : 0;
            $config["required_js"] = \QueryCache::wrap(
                'js_files_json_' . $build_mtime,
                static function() use ( $js_json_path ) {
                    return file_exists( $js_json_path ) ? json_decode( file_get_contents( $js_json_path ), true ) : [];
                }
            );

            // --- H. URL TANIMLARI ---
            //$config["theme_includes_url"] = defined("SH_INCLUDES_URL") ? SH_INCLUDES_URL : '';
            $config["theme_url"]          = defined("THEME_URL") ? THEME_URL : '';

            // --- I. ÇEVİRİ SÖZLÜĞÜ ---
            // 'sh_translate' option'ına bağlı — Polylang string kaydedilince updated_option tetiklenir → cache temizlenir
            $config["dictionary"] = [];
            if (class_exists("TranslationDictionary")) {
                $lang = Data::get("language") ?: 'tr';
                $config["dictionary"] = \QueryCache::wrap(
                    'translation_dictionary_' . $lang,
                    fn() => (new \TranslationDictionary())->getDictionary(),
                    [ 'post_type' => ['options'] ]  // ACF options save → invalide
                );
            }

            // --- J. META VERİSİ ---
            if (!empty($meta)) {
                $config["meta"] = $meta;
            }

            if($jsLoad){
                $config["loaded"] = true;
            }

            $config = apply_filters('site_config', $config);

            Data::set("site_config", $config);

        //error_log("site config is completed");

        return Data::get("site_config");//$GLOBALS["site_config"];  
    }

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
            $site_config["conditional_js"] = array_values( array_unique( (array)( SITE_ASSETS["plugins"] ?? [] ) ) );
        }

        $site_config["required_js"] = $site_config["required_js"] ?? [];

        // Postcode validation patterns — JS inputmask init'i için
        if ( defined( 'ENABLE_POSTCODE_VALIDATION' ) && ENABLE_POSTCODE_VALIDATION ) {
            $postcodes_file = SH_STATIC_PATH . 'data/postcodes.json';
            if ( file_exists( $postcodes_file ) ) {
                $site_config['postcodes'] = json_decode( file_get_contents( $postcodes_file ), true ) ?: [];
            }
        }

        // Icons registry — JS tarafında fa_icon() kullanımı için
        if ( class_exists( '\\SaltHareket\\IconRegistry' ) ) {
            $site_config["icons"] = \SaltHareket\IconRegistry::getJsIcons();
        }

        // upload_url — wp_upload_dir() pahalı çağrı, Data cache'den al
        $upload_url = Data::has('_upload_url')
            ? Data::get('_upload_url')
            : trailingslashit( wp_upload_dir()['baseurl'] );

        $site_config["ajax"] = [
            'url'             => trailingslashit(home_url()),
            'url_admin'       => admin_url('admin-ajax.php'),
            'site_url'        => home_url('/'),
            'theme_url'       => trailingslashit(get_stylesheet_directory_uri()),
            'upload_url'      => $upload_url,
            'ajax_nonce'      => wp_create_nonce('ajax'),
            'title'           => get_the_title(),
            'enable_ecommerce'=> defined('ENABLE_ECOMMERCE') && ENABLE_ECOMMERCE,
            // CSP violation listener için — CSP aktifse measure-lcp.js dinler
            'csp_active' => class_exists('\SaltHareket\Security\SecuritySettings')
                ? (bool) \SaltHareket\Security\SecuritySettings::getSection('csp')['enabled']
                : false,
        ];

        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

        if (ENABLE_DEVELOPMENT) {
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
        $source_path     = SH_INCLUDES_PATH . $plugin_filename;
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

            // Eklenti tüm post'ları 'closed' yapmış olabilir â€” toplu aç
            // Sadece bir kez çalışsın (transient ile)
            if ( ! get_transient( 'sh_comments_reopened' ) ) {
                global $wpdb;
                $wpdb->query(
                    "UPDATE {$wpdb->posts} 
                     SET comment_status = 'open' 
                     WHERE post_status = 'publish' 
                     AND post_type NOT IN ('attachment','revision','nav_menu_item')"
                );
                set_transient( 'sh_comments_reopened', 1, YEAR_IN_SECONDS );
            }
        }
    }

    public function wc_custom_template_path($path) {
        return '/theme/woocommerce/'; // yani artık şu klasöre bakacak: your-theme/theme/woocommerce/
    }
    public function wc_smart_template_path( $located, $template_name, $args, $template_path, $default_path ) {
        /*error_log(print_r([
            "located" => $located,
            "template_name" => $template_name,
            "args" => $args,
            "template_path" => $template_path,
            "default_path" => $default_path
        ], true));*/

        $root_path = get_stylesheet_directory() . '/theme/woocommerce/' . $template_name;
        if ( file_exists( $root_path ) ) {
            //error_log("Found in ROOT : ".$root_path);
            return $root_path;
        }
        
        $theme_path = SH_PATH . 'woocommerce/' . $template_name;
        if ( file_exists( $theme_path ) ) {
            //error_log("Found in SH-THEME : ".$theme_path);
            return $theme_path;
        }

        return $located;
    }
    public function wc_multiple_template_paths($template, $template_name, $template_path) {
        $paths = [
            get_template_directory() . '/theme/woocommerce/',
            get_template_directory() . '/vendor/salthareket/theme/src/woocommerce/',
        ];
        $target_file = str_replace("_", "-", $template_name);
        foreach ($paths as $dir) {
            $full_path = trailingslashit($dir) . $target_file;
            if ( file_exists( $full_path ) ) {
                //error_log("BULDU -> ".$full_path);
                return $full_path; 
            }
        }
        return $template;
    }

    public function register_post_types(){
        //this is where you can register custom post types
        include SH_INCLUDES_PATH . "register/post-type.php";
        include SH_INCLUDES_PATH . "apps/templates/bootstrap.php";
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
        $wpscss_compiler = new \SaltHareket\AssetManager\SCSSCompiler(
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
                // PluginManager artık bootstrap.php üzerinden plugin-manager app'i ile yükleniyor
                \Update::init();
                new \AvifConverter();
            }
            new \StarterSite();
        }, 20); 
	}
}
