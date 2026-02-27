<?php

/**
 * Class AssetManager
 * Orijinal mantık korunmuş, performans darboğazları giderilmiş tam versiyon.
 */

class AssetManager {
    private static $instance = null;
    private static $runtime_cache = [];
    private static $preload_queue = []; 
    private $is_rtl;
    private $print_inline;
    private $version;
    private $language;

    public static function instance() {
        if (is_null(self::$instance)) self::$instance = new self();
        return self::$instance;
    }

    public function __construct() {

        //$this->language = Data::get("language");
        //$this->is_rtl = Data::get("language_rtl");//(is_rtl() || (isset($this->language) && $GLOBALS["language"] == "fa"));
        $this->print_inline = (defined('INLINE_CSS') && INLINE_CSS && !isset($_GET['fetch']));
        $this->version = file_exists(STATIC_PATH . 'css/main.css') ? filemtime(STATIC_PATH . 'css/main.css') : '1.0.0';
        
        $this->init_hooks();
    }

    private function init_hooks() {
        // Purge & Utility
        add_action('init', [$this, 'handle_manual_purge']);

        // Preload & Header Styles
        // Fontları priority 5'te kuyruğa alıyoruz
        add_action('wp_head', [$this, 'prepare_font_queue'], 5); 
        // Preload'ları priority 0'da basıyoruz (render_preloads içinde queue_font kontrolü var)
        add_action('wp_head', [$this, 'render_preloads'], 0);
        
        add_action('wp_enqueue_scripts', [$this, 'load_frontend_assets'], 10);
        add_action('wp_default_scripts', [$this, 'wp_default_scripts']);
        
        if (is_admin()) {
            add_filter('admin_init', [$this, 'set_data'], 9999);
            add_action('admin_enqueue_scripts', [$this, 'load_admin_files']);
        }else{
            add_filter('wp', [$this, 'set_data'], 9999);
        }

        // Filters
        add_filter('style_loader_tag', [$this, 'delay_css_loading'], 10, 4);
        add_filter('script_loader_tag', [$this, 'add_script_attributes'], 10, 3);
    }

    public function set_data(){
        $this->language = Data::get("language");
        $this->is_rtl = Data::get("language_rtl");
    }

    // --- CORE LOGIC: PRELOADS ---

    public function render_preloads() {
        // Fontlar henüz kuyruğa girmediyse zorla sok (Priority 0 durumu için garanti)
        if (empty(self::$preload_queue)) {
            $this->prepare_font_queue();
        }

        if (empty(self::$preload_queue)) return;

        echo "\n\n";
        echo implode("\n", array_unique(self::$preload_queue));
        echo "\n\n\n";
    }

    public function add_to_preload($url, $as = 'font', $attr = []) {
        $key = md5($url . $as);
        if (isset(self::$runtime_cache['preloaded_'.$key])) return;

        $default_attr = ($as === 'font') ? ['crossorigin' => 'anonymous'] : [];
        $final_attr = array_merge($default_attr, $attr);
        
        $attr_string = '';
        foreach ($final_attr as $name => $value) {
            $attr_string .= ($value === true) ? " {$name}" : " {$name}=\"{$value}\"";
        }

        self::$preload_queue[$key] = sprintf('<link rel="preload" href="%s" as="%s"%s>', $url, $as, $attr_string);
        self::$runtime_cache['preloaded_'.$key] = true;
    }

    public function prepare_font_queue() {
        $this->queue_font_preloads(STATIC_PATH . 'css/font-faces.css', true);
    }

    public function queue_font_preloads($css_path, $relative = true) {
        if (!file_exists($css_path)) return;

        $css_hash = md5($css_path);
        $state_key = 'font_inline_cache_state_' . $css_hash;
        $master_cache_key = 'font_inline_cache_' . $css_hash;

        // 1. ADIM: Ayar değişikliği kontrolü
        $last_state = get_transient($state_key); // Son kaydedilen relative tercihi (true/false)
        
        // Eğer hafızadaki relative tercihi, şimdiki tercihten farklıysa her şeyi uçur
        if ($last_state !== false && (bool)$last_state !== (bool)$relative) {
            // Eski listeyi sil
            delete_transient($master_cache_key);
            
            // Bu CSS'e bağlı cache'lenmiş URL'leri temizlemek için master listeyi çekip tek tek silmemiz gerekir
            // Ama v8 gibi versiyon artışı veya direkt üzerine yazma bu işi zaten çözer.
            // Yine de tercihi güncelleyelim:
            delete_transient($state_key);
        }

        // 2. ADIM: Ana liste cache kontrolü
        $final_preloads = get_transient($master_cache_key);

        if ($final_preloads === false) {
            $content = file_get_contents($css_path);
            preg_match_all('/src:\s*url\([\'"]?([^)]+?)[\'"]?\)\s*format\([\'"]?([^"\')]+)[\'"]?\)/i', $content, $matches, PREG_SET_ORDER);

            $final_preloads = [];
            foreach ($matches as $match) {
                $raw_url = strtok($match[1], '?#');
                $type = 'font/' . str_replace(['truetype', 'opentype'], ['ttf', 'otf'], $match[2]);

                // Her bir URL'yi fixle (Burada suffix kullanmıyoruz, tercihe göre anlık oluşuyor)
                $fixed_url = $this->fix_single_url($raw_url, $css_path, $relative);

                $final_preloads[] = [
                    'url'  => $fixed_url,
                    'type' => $type
                ];
            }

            // Listeyi ve yeni durumu (relative durumunu) kaydet
            set_transient($master_cache_key, $final_preloads, WEEK_IN_SECONDS);
            set_transient($state_key, (int)$relative, WEEK_IN_SECONDS); 
        }

        // 3. ADIM: Kuyruğa bas
        foreach ($final_preloads as $item) {
            $this->add_to_preload($item['url'], 'font', ['type' => $item['type']]);
        }
    }

    // --- CORE LOGIC: STYLES ---

    public function load_frontend_assets() {
        if (is_admin()) return;

        $page_type = function_exists('get_page_type') ? get_page_type() : '';
        $has_core_block = false;

        if (in_array($page_type, ["post", "page", "home", "front"])) {
            global $post;
            if ($post) {
                $has_core_block = get_post_meta($post->ID, 'has_core_block', true);
                if (isset($post->post_type) && defined('ENABLE_ECOMMERCE') && ENABLE_ECOMMERCE) {
                    if ($post->post_type != "product") {
                        wp_dequeue_style('woo-variation-swatches');
                        wp_deregister_script("woo-variation-swatches");
                    }
                }
            }
        }

        $this->cleanup_wp_global_styles($has_core_block);
        $this->inline_css_add("font-faces", STATIC_PATH . 'css/font-faces.css');

        // Locale CSS Check
        $this->smart_enqueue_locale('css', Data::get("language") ?? '');

        wp_enqueue_style('root', STATIC_URL . 'css/root.css', [], $this->version);
        $this->handle_conditional_assets();
        wp_enqueue_style('common-css', STATIC_URL . 'css/common-all' . ($this->is_rtl ? "-rtl" : "") . '.css', [], $this->version);
        
        $this->load_frontend_scripts();
    }

    private function handle_conditional_assets() {
        $plugin_css = $css_page = false;
        $assets = defined('SITE_ASSETS') ? SITE_ASSETS : [];
        if($assets && !isset($_GET['fetch'])){
            $plugin_css = isset($assets['plugin_css'.($this->is_rtl ? "_rtl" : "")]) && !empty($assets['plugin_css'.($this->is_rtl ? "_rtl" : "")]) && file_exists(STATIC_PATH . $assets["plugin_css".($this->is_rtl ? "_rtl" : "")]);
            $css_page = isset($assets['css_page'.($this->is_rtl ? "_rtl" : "")]) && !empty($assets['css_page'.($this->is_rtl ? "_rtl" : "")]) && file_exists(STATIC_PATH . $assets["css_page".($this->is_rtl ? "_rtl" : "")]);
        }

        if ($this->print_inline) {
            if ($plugin_css) $this->inline_css_add('css-conditional', STATIC_PATH . $assets["plugin_css".($this->is_rtl ? "_rtl" : "")], $this->is_rtl);
            $main_path = $css_page ? STATIC_PATH . $assets["css_page".($this->is_rtl ? "_rtl" : "")] : STATIC_PATH . 'css/main-combined' . ($this->is_rtl ? "-rtl" : "") . '.css';
            $this->inline_css_add('main', $main_path, $this->is_rtl);
        } else {
            if ($plugin_css) wp_enqueue_style('css-conditional', STATIC_URL . $assets["plugin_css".($this->is_rtl ? "_rtl" : "")], [], $this->version);
            $main_url = $css_page ? STATIC_URL . $assets["css_page".($this->is_rtl ? "_rtl" : "")] : STATIC_URL . 'css/main-combined' . ($this->is_rtl ? "-rtl" : "") . '.css';                
            wp_enqueue_style('main', $main_url, [], $this->version);
        }
    }

    // --- INLINE ENGINE (CACHE KORUMALI) ---

    public function inline_css($name, $url) {
        $key = md5($name . $url . $this->version);
        $cache_file = rtrim(STATIC_PATH, '/') . '/css/cache/' . $key . '-inline.css';

        if (isset(self::$runtime_cache[$key])) return self::$runtime_cache[$key];
        if (file_exists($cache_file)) return self::$runtime_cache[$key] = file_get_contents($cache_file);

        if (!file_exists($url)) return '';
        $css = file_get_contents($url);
        $css = $this->fix_css_paths($css, $url); 
        $css = preg_replace('/\s+/', ' ', $css); 

        if (!file_exists(dirname($cache_file))) wp_mkdir_p(dirname($cache_file));
        file_put_contents($cache_file, $css);
        
        return self::$runtime_cache[$key] = $css;
    }

    public function inline_css_add($name, $url, $rtl = false) {
        $handle = $name . ($rtl ? "-rtl" : "");
        $content = $this->inline_css($handle, $url);
        if ($content) {
            wp_register_style($handle, false);
            wp_enqueue_style($handle);
            wp_add_inline_style($handle, $content);
        }
    }

    // --- SCRIPTS LOGIC ---

    public function wp_default_scripts($scripts) {
        if (!is_admin() && isset($scripts->registered['jquery'])) {
            $script = $scripts->registered['jquery'];
            if ($script->deps) {
                $script->deps = array_diff($script->deps, ['jquery-migrate']);
            }
        }
    }

    public function load_frontend_scripts() {
        wp_deregister_script('jquery');
        wp_enqueue_script('jquery', STATIC_URL . 'js/jquery.min.js', [], '1.0.0', false);
        wp_enqueue_script('image-sizes', SH_STATIC_URL . 'js/image-sizes.js', [], '1.0.0', false);
        add_action('wp_footer', [$this, 'load_footer_logic']);
    }

    public function load_footer_logic() {
        if (is_admin()) return;

        $dequeues = ['wc_additional_variation_images_script', 'ywdpd_owl', 'ywdpd_popup', 'ywdpd_frontend', 'acf-osm-frontend'];
        foreach ($dequeues as $h) {
            wp_dequeue_script($h);
            wp_deregister_script($h);
        }

        if (defined('ENABLE_ECOMMERCE') && ENABLE_ECOMMERCE && !ENABLE_CART) {
            wp_deregister_script("wc-order-attribution");
        }

        //$map_key = $GLOBALS['google_maps_api_key'] ?? '';
        $map_key = Data::get("language_rtl");
        if ($map_key) {
            wp_enqueue_script('googlemaps', "https://maps.googleapis.com/maps/api/js?key={$map_key}&language=" . Data::get("language"), [], null, true);
            if ($map_style = get_option('options_google_maps_style')) {
                $style = json_encode(json_decode(strip_tags($map_style)));
                wp_add_inline_script('googlemaps', "var map_style = {$style};", 'after');
            }
        }

        if (!function_exists("compile_files_config")) require_once SH_INCLUDES_PATH . "minify-rules.php";
        
        $files = compile_files_config(true);
        $init_functions = [];
        $assets = defined('SITE_ASSETS') ? SITE_ASSETS : [];
        $plugins_conditional = $assets["plugins"] ?? [];

        if ((defined('ENABLE_PRODUCTION') && ENABLE_PRODUCTION)) {
            $this->mass_enqueue($files["js"]["functions"], 'footer-');
            foreach ($files["js"]["plugins"] as $plugin => $file) {
                if (!$file["c"] || (is_array($plugins_conditional) && in_array($plugin, $plugins_conditional))) {
                    $p_handle = 'plugin-' . $plugin;
                    wp_enqueue_script($p_handle, STATIC_URL . "js/plugins/{$plugin}.js", [], null, true);
                    if (!empty($file["init"])) {
                        wp_enqueue_script("{$p_handle}-init", STATIC_URL . "js/plugins/{$plugin}-init.js", [$p_handle], null, true);
                        $init_functions[$plugin] = $file["init"];
                    }
                }
            }
            $this->mass_enqueue($files["js"]["pre"], 'pre-');
            foreach ($files["js"]["main"] as $key => $file) {
                $handle = 'main-' . $key;
                wp_enqueue_script($handle, $file, [], null, true);
                if ($key === 0 && !empty($init_functions)) $this->inject_plugin_inits($handle, $init_functions);
            }
        } else {
            wp_enqueue_script('pre', STATIC_URL . 'js/pre-combined.min.js', [], null, true);
            if (!empty($assets["plugin_js"]) && !isset($_GET['fetch'])) {
                wp_enqueue_script('plugins-conditional', STATIC_URL . $assets["plugin_js"], ['jquery'], null, true);
            }
            wp_enqueue_script('main', STATIC_URL . 'js/main-combined.min.js', [], null, true);
            foreach ($files["js"]["plugins"] as $plugin => $file) {
                if ((!$file["c"] || (is_array($plugins_conditional) && in_array($plugin, $plugins_conditional))) && !empty($file["init"])) {
                    $init_functions[$plugin] = $file["init"];
                }
            }                
            if (!empty($init_functions)) $this->inject_plugin_inits('main', $init_functions);
        }

        if (!empty(Data::get("language"))) $this->smart_enqueue_locale('js', Data::get("language"));
    }

    // --- HELPERS ---

    private function mass_enqueue($file_array, $prefix) {
        foreach ($file_array as $key => $file) {
            wp_enqueue_script($prefix . $key, $file, [], null, true);
        }
    }

    private function inject_plugin_inits($handle, $init_functions) {
        $script = 'function init_plugins(){';
        foreach ($init_functions as $plugin => $func) {
            $script .= sprintf('function_secure("%s","%s");', esc_js($plugin), esc_js($func));
        }
        $script .= '}';
        wp_add_inline_script($handle, $script);
    }

    private function smart_enqueue_locale($type, $lang) {
        $rel_path = ($type === 'css') ? "css/locale-{$lang}.css" : "js/locale/{$lang}.js";
        if (file_exists(STATIC_PATH . $rel_path)) {
            if ($type === 'css') wp_enqueue_style('locale', STATIC_URL . $rel_path, [], $this->version);
            else wp_enqueue_script('locale', STATIC_URL . $rel_path, [], null, true);
        }
    }

    private function fix_single_url($url_path, $base_file_path, $relative = true) {
        $raw_home_url = network_site_url(); // Örn: http://localhost/212outlet-2025/
        $subfolder = rtrim(parse_url($raw_home_url, PHP_URL_PATH) ?: '', '/'); // Örn: /212outlet-2025
        $theme_dir = wp_normalize_path(get_template_directory());
        $theme_name = basename($theme_dir);
        $base_dir = wp_normalize_path(dirname($base_file_path));

        // 1. Durum: URL zaten /wp-content/ içeriyor mu? (Senin font-faces.css içindeki gibi)
        if (strpos($url_path, '/wp-content/') !== false) {
            // Eğer zaten subfolder ile başlıyorsa dokunma
            if ($subfolder !== '' && strpos($url_path, $subfolder) === 0) {
                $clean_path = $url_path;
            } else {
                // Sadece /wp-content/ kısmından sonrasını al ve subfolder ekle
                $clean_path = $subfolder . strstr($url_path, '/wp-content/');
            }
        } else {
            // 2. Durum: Göreceli yol (./fonts/ vs) - senin eski mantık
            $abs_path = wp_normalize_path(realpath($base_dir . DIRECTORY_SEPARATOR . $url_path));
            if (!$abs_path || !str_starts_with($abs_path, $theme_dir)) return $url_path;
            
            $rel_path = ltrim(str_replace(['\\', $theme_dir], ['/', ''], $abs_path), '/');
            $clean_path = $subfolder . "/wp-content/themes/{$theme_name}/{$rel_path}";
        }

        // Domain ekleme kısmı
        if ($relative) {
            return $clean_path; // /212outlet-2025/wp-content/...
        } else {
            $host = rtrim(str_replace($subfolder, '', $raw_home_url), '/'); // http://localhost
            return $host . $clean_path; // http://localhost/212outlet-2025/wp-content/...
        }
    }

    private function fix_css_paths($css, $url) {
        return preg_replace_callback('/url\((["\']?)(?!https?:|data:)([^)\'"]+)\1\)/i', function ($m) use ($url) {
            return "url({$m[1]}" . $this->fix_single_url($m[2], $url) . "{$m[1]})";
        }, $css);
    }

    public function cleanup_wp_global_styles($has_core_block) {

        $remove_global_styles = QueryCache::get_field("remove_global_styles", "options");//get_option("options_remove_global_styles");
        if(($remove_global_styles == "auto" || $remove_global_styles) && !$has_core_block){
            wp_deregister_style('global-styles');
            wp_deregister_style('global-styles-inline');
            wp_dequeue_style('global-styles');
            wp_dequeue_style( 'global-styles-inline' );
            remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
            remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
            remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );
        }
        
        $remove_block_styles = QueryCache::get_field("remove_block_styles", "options");//get_option("options_remove_block_styles");
        if(($remove_block_styles == "auto" || $remove_block_styles) && !$has_core_block){
            wp_dequeue_style( 'wp-block-library' );
            wp_dequeue_style( 'wc-blocks-style' ); 
        }
         
        $remove_classic_theme_styles = QueryCache::get_field("remove_classic_theme_styles", "options");//get_option("options_remove_classic_theme_styles");
        if($remove_classic_theme_styles){
            wp_deregister_style('classic-theme-styles-inline');
            wp_deregister_style('classic-theme-styles');
            wp_dequeue_style('classic-theme-styles-inline');
            wp_dequeue_style('classic-theme-styles');
        }

        wp_dequeue_style('toggle-switch');
        wp_dequeue_style('font-awesome');
        wp_dequeue_style('font-for-body');
        wp_dequeue_style('font-for-new');
        wp_dequeue_style('google-fonts-roboto');

        if(ENABLE_ECOMMERCE){
            $remove_woocommerce_styles = QueryCache::get_field("remove_woocommerce_styles", "options");//get_option("options_remove_woocommerce_styles");
            if($remove_woocommerce_styles){
                wp_dequeue_style('woocommerce-smallscreen');
                wp_dequeue_style('woocommerce-inline');
                wp_dequeue_style('woocommerce-layout');
                wp_dequeue_style('woocommerce-general');
            }

            wp_dequeue_style('ywdpd_owl');
            wp_dequeue_style('yith_ywdpd_frontend');

            if ( get_option( 'woocommerce_coming_soon' ) !== 'yes' ) {
                wp_dequeue_style( 'woocommerce-coming-soon' );
                wp_deregister_style( 'woocommerce-coming-soon' );
            }

            global $wpdb;
            $taxonomy_exists = taxonomy_exists( 'product_brand' );
            if ($taxonomy_exists ) {
                $has_brand = $wpdb->get_var( "
                    SELECT term_taxonomy_id
                    FROM {$wpdb->term_taxonomy}
                    WHERE taxonomy = 'product_brand'
                    LIMIT 1
                " );
                if ( ! $has_brand ) {
                    wp_dequeue_style( 'brands-styles' );
                    wp_deregister_style( 'brands-styles' );
                }
            }   
        }
    }

    public function delay_css_loading($tag, $handle, $href) {
        if (in_array($handle, ['locale', 'newsletter'])) {
            return "<link id='{$handle}' rel='preload' href='{$href}' as='style' onload=\"this.onload=null;this.rel='stylesheet'\"><noscript><link rel='stylesheet' href='{$href}'></noscript>\n";
        }
        return $tag;
    }

    /**
     * Script etiketlerini tek noktadan yöneten birleştirilmiş fonksiyon
     */
    public function add_script_attributes($tag, $handle, $src) {
        
        // 1. Modern Modül (type="module") Kontrolü
        $modules = ['text-module']; 
        if (in_array($handle, $modules)) {
            return '<script type="module" src="' . esc_url($src) . '"></script>';
        }

        // 2. Defer (Geciktirme) Kontrolü
        // Eğer handle 'image-sizes' içeriyorsa defer ekle
        if (strpos($handle, 'image-sizes') !== false) {
            return str_replace(' src=', ' defer src=', $tag);
        }

        // 3. WP Rocket Özel İstisnaları (İstersen buraya daha önce konuştuğumuz data-nowprocket gibi şeyleri de ekleyebilirsin)
        /*
        if ($handle === 'ozel-script') {
            return str_replace(' src=', ' data-nowprocket="true" src=', $tag);
        }
        */

        return $tag;
    }

    public function handle_manual_purge() {
        if (isset($_GET['purge_assets']) && current_user_can('manage_options')) {
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_font_preloads_%'");
            $cache_folder = rtrim(STATIC_PATH, '/') . '/css/cache';
            if (file_exists($cache_folder)) array_map('unlink', glob("$cache_folder/*.css"));
            wp_die('Temizlik Tamam Abi!');
        }
    }

    public function load_admin_files() {
        wp_enqueue_style('fontawesome','https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css' , array(),'5.13.0','');
        wp_enqueue_style('bootstrap-admin', STATIC_URL . 'css/bootstrap-admin.css'); 
        wp_enqueue_style('root', STATIC_URL . 'css/root.css');
        wp_enqueue_style('acf-layouts', STATIC_URL . 'css/header-admin.css');
        wp_enqueue_style('main-admin', STATIC_URL . 'css/main-admin.css'); 
        wp_enqueue_style('blocks-admin', STATIC_URL . 'css/blocks-admin.css'); 
        wp_enqueue_style('admin-addon', STATIC_URL . 'css/admin-addon.css'); 

        wp_enqueue_script('admin', STATIC_URL . 'js/admin.min.js', ['jquery'], '1.0.0', true);
        wp_enqueue_script('functions', STATIC_URL . 'js/functions.min.js', ['jquery'], '1.0.0', true);
        wp_enqueue_script('plugins-admin', STATIC_URL . 'js/plugins-admin.min.js', ['jquery'], '1.0.0', true);
    }
}

AssetManager::instance();