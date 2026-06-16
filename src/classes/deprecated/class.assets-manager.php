<?php

/**
 * AssetManager
 * Orijinal mantık korunmuş, performans darboğazları giderilmiş tam versiyon.
 *
 * @version 1.2.0
 *
 * @changelog
 *   1.2.0 - 2026-04-30
 *     - Fix: mass_enqueue() her script'e inline guard ekliyor (YITH inject redeclaration önlendi)
 *     - Fix: Non-production modda pre/main script'lerine wp_add_inline_script guard eklendi
 *     - Fix: render_critical_css_remover() - Critical CSS bulunamadı console.log kaldırıldı
 *     - Fix: YITH WCAN offcanvas inject sırasında JS redeclaration hataları önlendi
 *   1.1.3 - 2026-04-21
 *     - Fix: ENABLE_PRODUCTION modunda main.js pre dosyalarına (root.js) dependency eklendi
 *     - Fix: mass_enqueue() artık handle listesi döndürüyor
 *     - Fix: root is not defined hatası çözüldü - main dosyaları pre'den sonra yükleniyor
 *   1.1.2 - 2026-04-21
 *     - Fix: delay_css_loading ve add_script_attributes admin'de devre dışı
 *     - Fix: Gutenberg block editor bozuluyordu - CSS/JS lazy loading admin'de çalışmamalı
 *     - Fix: style_loader_tag ve script_loader_tag filter'ları is_admin() kontrolüne alındı
 *   1.1.1 - 2026-04-21
 *     - Fix: wp.js'te admin paneli kontrolü eklendi (JSON yükleme engellendi)
 *     - Fix: functions.min.js admin panelinde yüklenir ama JSON fetch yapmaz
 *     - Add: Daha güvenli admin/frontend ayrımı (dosya kaldırma yerine kontrol)
 *     - Fix: Admin panelinde js_files_conditional_set.json 404 hatası önlendi
 *   1.1.0 - 2026-04-21
 *     - Fix: Admin panelinde functions.min.js yüklenmesi engellendi
 *     - Fix: js_files_conditional_set.json admin panelinde 404 hatası çözüldü
 *     - Add: Frontend-only JavaScript dosyaları admin panelinden ayrıldı
 *     - Fix: isLoadedJS fonksiyonu admin panelinde çalışmaz artık
 *   1.0.0 - 2026-04-03
 *     - Add: Initial versioned release
 *
 * @how_to_use
 *   1. Admin Panel Script Yönetimi:
 *      - load_admin_files() tüm gerekli script'leri yükler
 *      - functions.min.js admin panelinde yüklenir ama JSON fetch yapmaz
 *      - wp.js'te admin kontrolü ile js_files_conditional_set.json yüklenmez
 *   
 *   2. Güvenli Admin/Frontend Ayrımı:
 *      - Dosya kaldırma yerine JavaScript kontrolü kullanılır
 *      - functions.min.js'teki diğer fonksiyonlar admin panelinde çalışır
 *      - Sadece JSON yükleme işlemi admin panelinde engellenir
 *   
 *   3. Script Optimizasyonu:
 *      - Admin panelinde gereksiz AJAX istekleri önlenir
 *      - Console hataları giderilir
 *      - Tüm fonksiyonalite korunur
 *
 * @examples
 *   // Admin panelinde yüklenen script'ler (hepsi yüklenir)
 *   wp_enqueue_script('admin', STATIC_URL . 'js/admin.min.js', ['jquery'], '1.0.0', true);
 *   wp_enqueue_script('functions', STATIC_URL . 'js/functions.min.js', ['jquery'], '1.0.0', true);
 *   wp_enqueue_script('plugins-admin', STATIC_URL . 'js/plugins-admin.min.js', ['jquery'], '1.0.0', true);
 *   
 *   // wp.js'te admin kontrolü (JSON yükleme engellenir)
 *   if (typeof window.pagenow !== 'undefined' || document.body.classList.contains('wp-admin')) {
 *       // Admin panelinde JSON yükleme yapma
 *       if (typeof $callback === "function") $callback();
 *       return false;
 *   }
 *   
 *   // Frontend'te normal JSON yükleme
 *   const configUrl = ajax_request_vars.theme_url + "/static/js/js_files_conditional_set.json";
 *   $.ajax({ url: configUrl, dataType: 'json', cache: true, ... });
 *   
 *   // Sonuç: functions.min.js admin panelinde çalışır ama JSON fetch yapmaz
 *
 * How to use:
 *   $am = AssetManager::instance();
 *   // Hook'lar otomatik register edilir (wp_head, wp_enqueue_scripts, admin_enqueue_scripts).
 *   // Preload, font queue, delay CSS, script attributes otomatik çalışır.
 *
 * Examples:
 *   // Tema içinde doğrudan çağrı gerekmez, constructor'da hook'lar bağlanır.
 *   // Manuel purge: /?purge_assets query param ile tetiklenir.
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

    /**
     * Standardize log helper - [AssetManager] prefix ile error_log
     */
    private function log(string $msg, string $level = 'info'): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[AssetManager][{$level}] {$msg}");
        }
    }

    private function init_hooks() {
        add_action('init', [$this, 'handle_manual_purge']);

        // Fontları priority 0'da kuyruğa al — render_preloads'dan önce hazır olsun
        add_action('wp_head', [$this, 'prepare_font_queue'], 0);
        // Preload'ları priority 1'de bas — fontlar zaten kuyruğa girdi
        add_action('wp_head', [$this, 'render_preloads'], 1);
        // Critical CSS temizleme script'ini ekle - en sonda çalışsın
        add_action('wp_head', [$this, 'render_critical_css_remover'], 999);
        
        add_action('wp_enqueue_scripts', [$this, 'load_frontend_assets'], 10);
        // Google Fonts'u frontend'den kes (plugin/tema tarafından yüklenenler dahil)
        add_action('wp_enqueue_scripts', [$this, 'dequeue_google_fonts'], 9999);
        add_action('wp_default_scripts', [$this, 'wp_default_scripts']);
        
        if (is_admin()) {
            add_filter('admin_init', [$this, 'set_data'], 9999);
            add_action('admin_enqueue_scripts', [$this, 'load_admin_files']);
        }else{
            add_filter('wp', [$this, 'set_data'], 9999);
        }

        // Admin'de CSS/JS lazy loading yapma - Gutenberg bozuluyor
        // Production modunda da defer/lazy yapma - zaten combine/minify edilmiş
        if (!is_admin() && (!defined('ENABLE_PRODUCTION') || !ENABLE_PRODUCTION)) {
            add_filter('style_loader_tag', [$this, 'delay_css_loading'], 10, 4);
            add_filter('script_loader_tag', [$this, 'add_script_attributes'], 10, 3);
        }

        // Google Fonts link tag'lerini HTML'den de kes (wp_head ile direkt basılanlar)
        if (!is_admin()) {
            add_filter('style_loader_tag', [$this, 'block_google_fonts_tag'], 9999, 3);
        }
    }

    public function set_data(){
        $this->language = Data::get("language");
        $this->is_rtl = Data::get("language_rtl");
    }

    // --- CORE LOGIC: PRELOADS ---

    public function render_preloads() {
        if (empty(self::$preload_queue)) return;
        
        // Preconnect hints ekle (sadece en önemli 4 domain - PSI uyarısı yüzünden)
        echo "\n<!-- Preconnect hints for critical domains -->\n";
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
        echo '<link rel="preconnect" href="https://tile.openstreetmap.org">' . "\n";
        // YouTube ve i.ytimg.com'u kaldırdık - 4'ten fazla preconnect uyarısı yüzünden
        
        echo "\n<!-- Preload resources -->\n";
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
        $this->inline_css_add("sh-font-faces", STATIC_PATH . 'css/font-faces.css');

        // Locale CSS Check
        $this->smart_enqueue_locale('css', Data::get("language") ?? '');

        wp_enqueue_style('sh-root', STATIC_URL . 'css/root.css', [], $this->version);
        $this->handle_conditional_assets();
        wp_enqueue_style('sh-common', STATIC_URL . 'css/common-all' . ($this->is_rtl ? "-rtl" : "") . '.css', [], $this->version);
        
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
            if ($plugin_css) $this->inline_css_add('sh-conditional', STATIC_PATH . $assets["plugin_css".($this->is_rtl ? "_rtl" : "")], $this->is_rtl);
            $main_path = $css_page ? STATIC_PATH . $assets["css_page".($this->is_rtl ? "_rtl" : "")] : STATIC_PATH . 'css/main-combined' . ($this->is_rtl ? "-rtl" : "") . '.css';
            $this->inline_css_add('sh-main', $main_path, $this->is_rtl);
        } else {
            if ($plugin_css) wp_enqueue_style('sh-conditional', STATIC_URL . $assets["plugin_css".($this->is_rtl ? "_rtl" : "")], [], $this->version);
            $main_url = $css_page ? STATIC_URL . $assets["css_page".($this->is_rtl ? "_rtl" : "")] : STATIC_URL . 'css/main-combined' . ($this->is_rtl ? "-rtl" : "") . '.css';                
            wp_enqueue_style('sh-main', $main_url, [], $this->version);
        }
    }

    // --- INLINE ENGINE (CACHE KORUMALI) ---

    public function inline_css($name, $url) {
        $key        = md5($name . $url . $this->version . get_option('salthareket_theme_version', '1'));
        $cache_dir  = rtrim(STATIC_PATH, '/') . '/css/cache/';
        $cache_file = $cache_dir . $key . '-inline.css';

        if (isset(self::$runtime_cache[$key])) return self::$runtime_cache[$key];

        // Path validation - sadece izin verilen dizinlerden dosya oku
        $allowed_paths = [STATIC_PATH, THEME_STATIC_PATH, SH_STATIC_PATH, get_template_directory() . '/'];
        $is_allowed = false;
        foreach ($allowed_paths as $allowed) {
            if (str_starts_with(realpath($url) ?: $url, realpath($allowed) ?: $allowed)) {
                $is_allowed = true;
                break;
            }
        }
        if (!$is_allowed) {
            $this->log('Blocked unauthorized file read: ' . $url, 'security');
            return '';
        }

        // Cache dosyası var ve kaynak dosyadan daha yeni ise kullan
        if (file_exists($cache_file) && file_exists($url) && filemtime($cache_file) >= filemtime($url)) {
            return self::$runtime_cache[$key] = file_get_contents($cache_file);
        }

        if (!file_exists($url)) return '';
        $css = file_get_contents($url);
        $css = $this->fix_css_paths($css, $url);
        $css = preg_replace('/\s+/', ' ', $css);

        if (!file_exists($cache_dir)) wp_mkdir_p($cache_dir);

        // 7 günden eski cache dosyalarını temizle — sadece %2 ihtimalle (disk şişmesini önle)
        if (mt_rand(1, 50) === 1) {
            foreach (glob($cache_dir . '*-inline.css') ?: [] as $old_file) {
                if (filemtime($old_file) < (time() - 7 * DAY_IN_SECONDS)) {
                    @unlink($old_file);
                }
            }
        }

        if (file_put_contents($cache_file, $css) === false) {
            $this->log('Cache write failed: ' . $cache_file, 'error');
        }
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
        wp_enqueue_script('jquery', STATIC_URL . 'js/jquery.min.js', [], '1.0.0', true); // footer'da yükle - normal
        wp_enqueue_script('image-sizes', SH_STATIC_URL . 'js/image-sizes.js', [], '1.0.0', true); // footer'da yükle
        add_action('wp_footer', [$this, 'load_footer_logic']);
    }

    public function load_footer_logic() {
        if (is_admin()) return;

        $dequeues = ['wc_additional_variation_images_script', 'ywdpd_owl', 'ywdpd_popup', 'ywdpd_frontend', 'acf-osm-frontend'];
        foreach ($dequeues as $h) {
            wp_dequeue_script($h);
            wp_deregister_script($h);
        }

        if (defined('ENABLE_ECOMMERCE') && ENABLE_ECOMMERCE && defined('ENABLE_CART') && !ENABLE_CART) {
            wp_deregister_script("wc-order-attribution");
        }

        $map_key = sanitize_text_field((string) Data::get("google_maps_api_key"));
        if ($map_key) {
            $map_lang = sanitize_key((string) Data::get("language"));
            wp_enqueue_script('googlemaps', "https://maps.googleapis.com/maps/api/js?key={$map_key}&language={$map_lang}", [], null, true);
            if ($map_style = QueryCache::get_option('options_google_maps_style')) {
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
            $function_handles = $this->mass_enqueue($files["js"]["functions"], 'footer-');
            foreach ($files["js"]["plugins"] as $plugin => $file) {
                if (!$file["c"] || (is_array($plugins_conditional) && in_array($plugin, $plugins_conditional))) {
                    $p_handle = 'plugin-' . $plugin;
                    // functions yüklenmeden plugin çalışmamalı (inViewport vs. utility.js'te tanımlı)
                    wp_enqueue_script($p_handle, STATIC_URL . "js/plugins/{$plugin}.js", array_merge(['jquery'], $function_handles), null, true);
                    if (!empty($file["init"])) {
                        wp_enqueue_script("{$p_handle}-init", STATIC_URL . "js/plugins/{$plugin}-init.js", [$p_handle], null, true);
                        $init_functions[$plugin] = $file["init"];
                    }
                }
            }
            $pre_handles = $this->mass_enqueue($files["js"]["pre"], 'pre-');
            foreach ($files["js"]["main"] as $key => $file) {
                $handle = 'main-' . $key;
                // pre dosyaları yüklenmeden main çalışmamalı (root.js dependency)
                wp_enqueue_script($handle, $file, array_merge(['jquery'], $pre_handles, $function_handles), null, true);
                if ($key === 0 && !empty($init_functions)) $this->inject_plugin_inits($handle, $init_functions);
            }
        } else {
            wp_enqueue_script('pre', STATIC_URL . 'js/pre-combined.min.js', ['jquery'], null, true);
            // Guard: YITH gibi eklentiler inject edince tekrar çalışmasın
            wp_add_inline_script('pre', '(function(){if(window.__preLoaded)return;window.__preLoaded=true;})();', 'before');

            if (!empty($assets["plugin_js"]) && !isset($_GET['fetch'])) {
                wp_enqueue_script('plugins-conditional', STATIC_URL . $assets["plugin_js"], ['jquery'], null, true);
            }
            wp_enqueue_script('main', STATIC_URL . 'js/main-combined.min.js', ['jquery', 'pre'], null, true);
            // Guard: YITH gibi eklentiler inject edince tekrar çalışmasın - IIFE ile wrap
            wp_add_inline_script('main', '(function(){if(window.__mainLoaded)return;window.__mainLoaded=true;})();', 'before');
            
            foreach ($files["js"]["plugins"] as $plugin => $file) {
                if ((!$file["c"] || (is_array($plugins_conditional) && in_array($plugin, $plugins_conditional))) && !empty($file["init"])) {
                    $init_functions[$plugin] = $file["init"];
                }
            }                
            if (!empty($init_functions)) $this->inject_plugin_inits('main', $init_functions);
        }

        if (!empty(Data::get("language"))) $this->smart_enqueue_locale('js', Data::get("language"));
    }

    /**
     * Main-combined script'ini lazy load eder - user interaction'dan sonra yükler
     */
    /**
     * Critical CSS temizleme script'ini HTML'e ekler
     */
    public function render_critical_css_remover() {
        if (is_admin()) return;
        
        // Critical CSS inject et (LCP için)
        echo '<style id="critical-css-inline">';
        echo 'img{max-width:100%;height:auto;}'; // Image responsive
        echo '.logo{width:40px;height:40px;}'; // Logo size fix
        echo 'body{font-display:swap;}'; // Font display
        echo '</style>';
        
        ?>
<script id="critical-css-remover">
(function(){
    'use strict';
    
    function removeCriticalCSS(){
        var ids = ['lcp-critical', 'rocket-critical-css', 'wp-rocket-critical-css'];
        var removed = false;
        
        for(var i = 0; i < ids.length; i++){
            var elem = document.getElementById(ids[i]);
            if(elem){
                elem.parentNode.removeChild(elem);
                removed = true;
                console.log('[AssetManager] Critical CSS silindi: ' + ids[i]);
            }
        }
        
        if(!removed){
            // Critical CSS eklentisi yok - normal durum, log gerekmez
        }
    }
    
    if(document.readyState === 'complete'){
        removeCriticalCSS();
    }else{
        if(document.readyState === 'loading'){
            document.addEventListener('DOMContentLoaded', function(){
                setTimeout(removeCriticalCSS, 100);
            });
        }
        window.addEventListener('load', function(){
            setTimeout(removeCriticalCSS, 500);
        });
    }
})();
</script>
        <?php
    }

    /**
     * WP Rocket Critical CSS'i sayfa yüklendikten sonra DOM'dan kaldırır
     * Critical CSS işini bitirdikten sonra asıl CSS'leri ezmemesi için silinir
     * @deprecated Use render_critical_css_remover() instead
     */
    private function remove_critical_css_after_load() {
        // Bu fonksiyon artık kullanılmıyor - render_critical_css_remover() kullanılıyor
    }

    // --- HELPERS ---

    private function mass_enqueue($file_array, $prefix) {
        $handles = [];
        foreach ($file_array as $key => $file) {
            $handle = $prefix . $key;
            wp_enqueue_script($handle, $file, [], null, true);
            // Guard: YITH gibi eklentiler inject edince tekrar çalışmasın
            // IIFE ile wrap - global scope'da return çalışmaz
            $guard_key = 'window.__loaded_' . md5($handle);
            $guard = "(function(){if({$guard_key})return;{$guard_key}=true;})();";
            wp_add_inline_script($handle, $guard, 'before');
            $handles[] = $handle;
        }
        return $handles;
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

        if(defined('ENABLE_ECOMMERCE') && ENABLE_ECOMMERCE){
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
                $has_brand = get_transient('_salt_has_product_brand');
                if ($has_brand === false) {
                    $has_brand = $wpdb->get_var( "
                        SELECT term_taxonomy_id
                        FROM {$wpdb->term_taxonomy}
                        WHERE taxonomy = 'product_brand'
                        LIMIT 1
                    " ) ? '1' : '0';
                    set_transient('_salt_has_product_brand', $has_brand, DAY_IN_SECONDS);
                }
                if ( $has_brand === '0' ) {
                    wp_dequeue_style( 'brands-styles' );
                    wp_deregister_style( 'brands-styles' );
                }
            }   
        }
    }

    public function delay_css_loading($tag, $handle, $href) {
        // Critical CSS varsa ana CSS'leri de async yükle
        $has_critical = defined('SITE_ASSETS') && is_array(SITE_ASSETS) && !empty(SITE_ASSETS['css_critical']);

        // Handle bazlı async listesi (her zaman async)
        $always_async_handles = [
            'locale',
            'newsletter',
            // YITH WooCommerce Ajax Product Filter
            'yith-wcan-frontend',
            'yith-wcan-shortcodes',
            // WooCommerce CSS'leri (render-blocking'i önle)
            'woocommerce-layout',
            'woocommerce-smallscreen', 
            'woocommerce-general',
            'wc-blocks-style',
        ];

        // URL pattern bazlı async (WooCommerce, plugin CSS'leri)
        $async_url_patterns = [
            '/woocommerce/',
            '/wc-blocks/',
            '/wc-',
            'woocommerce.css',
            'checkout-blocks.css',
            'ion.range-slider',
            'shortcodes.css',
            'style.min.css',  // Plugin CSS'leri
        ];

        // YouTube CSS'lerini de async yükle (ağır yükleniyor)
        $youtube_patterns = [
            'youtube.com',
            'ytimg.com',
            'www-player.css',
        ];
        
        $all_patterns = array_merge($async_url_patterns, $youtube_patterns);

        // Critical CSS varsa tema CSS'lerini de async yükle
        $critical_async_handles = $has_critical ? [
            'sh-root',
            'sh-common',
            'sh-conditional',
            'sh-main',
        ] : [];

        $all_async_handles = array_merge($always_async_handles, $critical_async_handles);

        // Handle eşleşmesi
        if (in_array($handle, $all_async_handles)) {
            $safe_href = esc_url($href);
            return "<link id='{$handle}' rel='preload' href='{$safe_href}' as='style' onload=\"this.onload=null;this.rel='stylesheet'\"><noscript><link rel='stylesheet' href='{$safe_href}'></noscript>\n";
        }

        // URL pattern eşleşmesi
        foreach ($all_patterns as $pattern) {
            if (strpos($href, $pattern) !== false) {
                $safe_href = esc_url($href);
                return "<link id='{$handle}' rel='preload' href='{$safe_href}' as='style' onload=\"this.onload=null;this.rel='stylesheet'\"><noscript><link rel='stylesheet' href='{$safe_href}'></noscript>\n";
            }
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

        // 2. Defer (Geciktirme) Kontrolü - Ağır JS'leri defer et
        $defer_handles = [
            'image-sizes',              // Image sizes script
            'plugins-conditional',      // Plugin JS'leri
        ];
        
        // HTML-to-image gibi ağır script'leri lazy load et
        $lazy_load_patterns = [
            'html-to-image',
            'chart',
            'canvas',
        ];
        
        // Lazy load pattern check
        foreach ($lazy_load_patterns as $pattern) {
            if (strpos($handle, $pattern) !== false) {
                // Bu script'i lazy load et - user interaction'da yükle
                return str_replace(' src=', ' data-src=', $tag) . 
                       '<script>document.addEventListener("click",function(){var s=document.querySelector(\'script[data-src*="' . $pattern . '"]\');if(s){s.src=s.dataset.src;s.removeAttribute("data-src");}},{once:true});</script>';
            }
        }
        
        // Handle pattern'e göre defer
        foreach ($defer_handles as $pattern) {
            if (strpos($handle, $pattern) !== false) {
                return str_replace(' src=', ' defer src=', $tag);
            }
        }
        
        // Specific handle check
        if (in_array($handle, $defer_handles)) {
            return str_replace(' src=', ' defer src=', $tag);
        }

        // 3. WP Rocket Özel İstisnaları
        /*
        if ($handle === 'ozel-script') {
            return str_replace(' src=', ' data-nowprocket="true" src=', $tag);
        }
        */

        return $tag;
    }

    public function dequeue_google_fonts() {
        global $wp_styles;
        if ( empty( $wp_styles->registered ) ) return;
        foreach ( $wp_styles->registered as $handle => $style ) {
            if ( ! empty( $style->src ) && strpos( $style->src, 'fonts.googleapis.com' ) !== false ) {
                wp_dequeue_style( $handle );
                wp_deregister_style( $handle );
            }
        }
    }

    public function block_google_fonts_tag( $tag, $handle, $href ) {
        if ( strpos( $href, 'fonts.googleapis.com' ) !== false ) {
            return ''; // Tag'i tamamen sil
        }
        return $tag;
    }

    public function handle_manual_purge() {
        if (isset($_GET['purge_assets']) && current_user_can('manage_options')) {
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_font_inline_cache_%' OR option_name LIKE '_transient_timeout_font_inline_cache_%' OR option_name LIKE '_transient_font_inline_cache_state_%' OR option_name LIKE '_transient_timeout_font_inline_cache_state_%'");
            $cache_folder = rtrim(STATIC_PATH, '/') . '/css/cache';
            if (file_exists($cache_folder)) array_map('unlink', glob("$cache_folder/*.css") ?: []);
            self::$runtime_cache = [];
            self::$preload_queue = [];
            wp_die('Temizlik Tamam Abi!');
        }
    }

    public function load_admin_files() {
        wp_enqueue_style('fontawesome','https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css' , array(),'6.7.2','');
        wp_enqueue_style('bootstrap-admin', STATIC_URL . 'css/bootstrap-admin.css'); 
        wp_enqueue_style('root', STATIC_URL . 'css/root.css');
        wp_enqueue_style('acf-layouts', STATIC_URL . 'css/header-admin.css');
        wp_enqueue_style('main-admin', STATIC_URL . 'css/main-admin.css'); 
        wp_enqueue_style('blocks-admin', STATIC_URL . 'css/blocks-admin.css'); 
        wp_enqueue_style('admin-addon', STATIC_URL . 'css/admin-addon.css');

         // IFRAME İÇİNE CSS PASLAMA (BURASI KRİTİK ABİ)
        // add_editor_style temanın root klasöründen yol bekler, STATIC_PATH/URL yapına göre ayarla
        /*add_editor_style(STATIC_URL . 'css/bootstrap-admin.css');
        add_editor_style(STATIC_URL . 'css/root.css');
        add_editor_style(STATIC_URL . 'css/header-admin.css');
        add_editor_style(STATIC_URL . 'css/main-admin.css');
        add_editor_style(STATIC_URL . 'css/blocks-admin.css');
        add_editor_style(STATIC_URL . 'css/admin-addon.css');*/
        

        wp_enqueue_script('admin', STATIC_URL . 'js/admin.min.js', ['jquery'], '1.0.0', true);
        wp_enqueue_script('functions', STATIC_URL . 'js/functions.min.js', ['jquery'], '1.0.0', true);
        wp_enqueue_script('plugins-admin', STATIC_URL . 'js/plugins-admin.min.js', ['jquery'], '1.0.0', true);
    }
}

// AssetManager artık apps/asset-manager/bootstrap.php tarafından yönetiliyor.
// TODO: Bu dosya silinecek.
// AssetManager::instance();