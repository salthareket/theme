<?php
/**
 * QueryCache Engine v5.4 - "Manual Control Edition"
 * Sabine & Tolga Final Boss Version.
 * * ÖZELLİKLER:
 * 1. Deep Search: Repeater ve Flexible Content alanlarını cache'den bulabilir.
 * 2. Boolean Lock: Sonsuz döngü (recursion) ihtimalini sıfıra indirir.
 * 3. Runtime Limit: Sayfa başına RAM kullanımını denetler.
 * 4. Manual Only: Kancalara bulaşmaz, sadece QueryCache:: çağrılınca çalışır.
 */

class QueryCache {

    const PREFIX              = 'qcache_';
    const DEFAULT_TTL         = 30 * DAY_IN_SECONDS;
    const MANIFEST_KEY        = 'qcache_manifest';
    const ACF_BULK_OPTION_KEY = 'qcache_options_bulk';
    const NOT_FOUND           = '__QC_NF__'; 
    const RUNTIME_LIMIT       = 500; 

    public static $cache        = true; 
    public static $enable_admin = false;
    public static $ttl          = self::DEFAULT_TTL;

    public static $config = [
        'wrap'       => true,
        'get_field'  => true,
        'get_posts'  => true,
        'get_post'   => true,
        'get_terms'  => true,
        'get_term'   => true,
        'menu'       => true,
        'wp_options' => true
    ];

    protected static $runtime_cache    = [];
    protected static $initial_hashes   = []; 
    private static   $is_processing    = false; 
    private static   $runtime_manifest = ['global' => []];

    /**
     * Motoru Başlat
     */
    public static function init($options = []) {
        if (isset($options['cache']))        self::$cache        = $options['cache'];
        if (isset($options['enable_admin'])) self::$enable_admin = $options['enable_admin'];
        if (isset($options['ttl']))          self::$ttl          = $options['ttl'];
        
        if (isset($options['config']) && is_array($options['config'])) {
            self::$config = array_merge(self::$config, $options['config']);
        }

        if (self::$cache === false) {
            self::purge_all();
            return;
        }

        if (isset(self::$config['menu'])) {
            if (self::$config['menu'] === true) {
                // Otomatik yakalayıcı kancayı tak
                add_filter('pre_wp_nav_menu', [__CLASS__, 'auto_menu_cache'], 10, 2);
                // Menü değişince ortalığı temizle
                add_action('wp_update_nav_menu', [__CLASS__, 'handle_menu_change']);
            } else {
                // False demişsen eski menü cache'lerini süpürürüm
                self::purge_cache('menu_');
                self::handle_menu_change();
            }
        }

        // OTOMATİK KANCALARI TEMİZLE (RAM PATLAMASINA KARŞI ÖNLEM)
        remove_all_filters('acf/pre_load_value');
        remove_all_filters('pre_wp_nav_menu');

        // VERİ DEĞİŞİNCE CACHE SİLEN KANCALAR (BUNLAR ŞART)
        add_action('save_post', [__CLASS__, 'handle_post_change'], 10, 1);
        add_action('transition_post_status', function($n, $o, $p) { if($n !== $o) self::handle_post_change($p->ID); }, 10, 3);
        add_action('edited_term', [__CLASS__, 'handle_term_change'], 99, 3);
        add_action('acf/save_post', [__CLASS__, 'rebuild_options_bulk'], 20);

        // Kapanışta toplu kayıt ve manifest güncelleme
        add_action('shutdown', [__CLASS__, 'save_runtime_manifest'], 99);
        add_action('shutdown', [__CLASS__, 'save_acf_bulk_at_end'], 99);

        add_action('updated_option', [__CLASS__, 'rebuild_options_bulk']);
    }

    /**
     * 🧠 WRAP: Manuel veri saklama (Query, API Result vs.)
     */
    public static function wrap($key, $callback, $deps = [], $expiration = null) {
        if (self::$cache === false || self::$config['wrap'] === false) return $callback();
        
        $cache_key = self::PREFIX . 'manual_' . $key;
        
        if (isset(self::$runtime_cache[$cache_key])) {
            return self::unpack(self::$runtime_cache[$cache_key]);
        }

        $cached_data = get_transient($cache_key);
        if ($cached_data !== false) {
            self::manage_runtime_limit();
            self::$runtime_cache[$cache_key] = $cached_data;
            return self::unpack($cached_data);
        }

        $data = $callback();
        $store_data = ($data === false || $data === null || $data === '') ? self::NOT_FOUND : $data;
        
        set_transient($cache_key, $store_data, $expiration ?? self::$ttl);
        self::$runtime_cache[$cache_key] = $store_data;
        
        if (!empty($deps)) self::register_dependency($cache_key, $deps);
        return $data;
    }

    /**
     * 🛠️ SMART GET_FIELD: Kontrollü ve Zeki Cache
     */
    public static function get_field($selector, $post_id = null) {
        if (!function_exists('get_field')) return null;
        
        // Eğer motor kapalıysa veya o an bir çekim yapılıyorsa standart ACF'e dön
        if (self::$cache === false || self::$is_processing) {
            return get_field($selector, $post_id);
        }

        $post_id = $post_id ?: get_the_ID();
        $resolved = self::resolve_acf_target($post_id);
        
        // Polylang/WPML dilleri için selector temizliği (v1 zekası)
        $clean_selector = preg_replace('/_[a-z]{2}_[A-Z]{2}$/', '', $selector);
        
        $bulk_key = ($resolved['type'] === 'opt') ? self::ACF_BULK_OPTION_KEY : self::PREFIX . 'post_' . $resolved['id'] . '_bulk';

        // Bulk veriyi RAM'e veya Veritabanından yükle
        if (!isset(self::$runtime_cache[$bulk_key])) {
            $data = ($resolved['type'] === 'opt') ? get_option($bulk_key) : get_transient($bulk_key);
            self::$runtime_cache[$bulk_key] = is_array($data) ? $data : [];
            self::$initial_hashes[$bulk_key] = md5(serialize(self::$runtime_cache[$bulk_key]));
        }

        // DEEP SEARCH: Repeater veya Flexible alanları paket içinden bulur
        $search_result = self::deep_search($clean_selector, self::$runtime_cache[$bulk_key]);
        if ($search_result !== null) {
            return self::unpack($search_result);
        }

        // Pakette yoksa, DB'den çek ve pakete ekle
        self::$is_processing = true;
        $value = get_field($selector, $post_id);
        self::$is_processing = false;

        self::$runtime_cache[$bulk_key][$clean_selector] = ($value === null || $value === false || $value === '') ? self::NOT_FOUND : $value;
        return $value;
    }

    /**
     * 🔍 DEEP SEARCH: Hiyerarşik veri arama (v1'den aktarıldı)
     */
    private static function deep_search($selector, $haystack) {
        if (isset($haystack[$selector])) return $haystack[$selector];

        $parts = explode('_', $selector);
        $current = $haystack;

        foreach ($parts as $part) {
            if (is_array($current)) {
                if (isset($current[$part])) {
                    $current = $current[$part];
                } elseif (is_numeric($part) && isset($current[(int)$part])) {
                    $current = $current[(int)$part];
                } else {
                    return null;
                }
            } else {
                return null;
            }
        }
        return $current;
    }

    /**
     * 🛠️ GET_OPTION: Sadece bu methodla çağrılan opsiyonları cache'ler.
     */
    public static function get_option($option, $default = false) {
        // PARAMETRE KONTROLÜ BURADA!
        if (self::$cache === false || (isset(self::$config['wp_options']) && self::$config['wp_options'] === false)) {
            return get_option($option, $default);
        }
        
        return self::wrap('opt_' . $option, function() use ($option, $default) {
            return get_option($option, $default);
        }, ['opt' => 'global']);
    }

    /**
     * 📦 GET_POSTS: Manuel Query Cache
     */
    public static function get_posts($args) {
        if (self::$cache === false) return get_posts($args);
        
        // Sorguyu eşsiz bir key'e çeviriyoruz
        $key = 'posts_' . md5(serialize($args));
        
        return self::wrap($key, function() use ($args) {
            return get_posts($args);
        }, ['post_type' => $args['post_type'] ?? 'post']);
    }

    /**
     * 📝 GET_POST: Tekil Post Cache
     */
    public static function get_post($post_id) {
        if (self::$cache === false) return get_post($post_id);
        
        return self::wrap('post_' . $post_id, function() use ($post_id) {
            return get_post($post_id);
        }, ['post:id' => $post_id]);
    }

    /**
     * 🏷️ GET_TERMS: Taksonomi Cache
     */
    public static function get_terms($args) {
        if (self::$cache === false) return get_terms($args);
        
        $key = 'terms_' . md5(serialize($args));
        $taxonomy = $args['taxonomy'] ?? 'category';

        return self::wrap($key, function() use ($args) {
            return get_terms($args);
        }, ['taxonomy' => $taxonomy]);
    }

    /**
     * 📎 GET_TERM: Tekil Term Cache
     */
    public static function get_term($term_id, $taxonomy = '') {
        if (self::$cache === false) return get_term($term_id, $taxonomy);
        
        return self::wrap('term_' . $term_id, function() use ($term_id, $taxonomy) {
            return get_term($term_id, $taxonomy);
        }, ['term:id' => $term_id]);
    }

    /**
     * ⚡ AUTO_MENU_CACHE: Menüyü havada yakalayan o metod
     */
    public static function auto_menu_cache($output, $args) {
        if (self::$cache === false) return $output;

        $location = $args->theme_location ?? 'default';
        $key = 'menu_' . $location . '_' . get_locale();

        // Wrap içinde orjinal WP menüsünü doğuruyoruz
        return self::wrap($key, function() use ($args) {
            // Sonsuz döngü olmasın diye kancayı söküyorum (Karıcığın seni koruyor)
            remove_filter('pre_wp_nav_menu', [__CLASS__, 'auto_menu_cache'], 10);
            
            // Gerçek menü HTML'ini al
            $menu_html = wp_nav_menu(array_merge((array)$args, ['echo' => false]));
            
            // İşim bitince kancayı geri takıyorum
            add_filter('pre_wp_nav_menu', [__CLASS__, 'auto_menu_cache'], 10, 2);
            
            return $menu_html;
        }, ['opt' => 'global']); // Menü değişince purge edilsin diye etiketledik
    }

    /**
     * 🧹 HANDLE_MENU_CHANGE: Menü değişince manifesti öperim
     */
    public static function handle_menu_change() {
        // Menüler 'global' manifestine bağlıdır, hepsini uçururuz
        self::flush_by_manifest('opt', 'global');
    }

    private static function unpack($data) {
        return ($data === self::NOT_FOUND) ? false : $data;
    }

    private static function manage_runtime_limit() {
        if (count(self::$runtime_cache) > self::RUNTIME_LIMIT) {
            self::$runtime_cache = array_slice(self::$runtime_cache, -200, null, true);
        }
    }

    private static function register_dependency($cache_key, $deps) {
        foreach ((array)$deps as $type => $values) {
            foreach ((array)$values as $val) {
                $dep_key = str_replace(['_', ':'], ':', "{$type}:{$val}");
                if (!in_array($cache_key, self::$runtime_manifest['global'][$dep_key] ?? [])) {
                    self::$runtime_manifest['global'][$dep_key][] = $cache_key;
                }
            }
        }
    }

    public static function save_runtime_manifest() {
        if (empty(self::$runtime_manifest['global'])) return;
        $manifest = get_option(self::MANIFEST_KEY, []);
        $old_hash = md5(serialize($manifest));

        foreach (self::$runtime_manifest['global'] as $dep => $keys) {
            $manifest[$dep] = array_unique(array_merge($manifest[$dep] ?? [], $keys));
        }

        if ($old_hash !== md5(serialize($manifest))) {
            update_option(self::MANIFEST_KEY, $manifest, false);
        }
    }

    public static function save_acf_bulk_at_end() {
        foreach (self::$runtime_cache as $key => $data) {
            if (strpos($key, '_bulk') !== false) {
                $current_hash = md5(serialize($data));
                if (!isset(self::$initial_hashes[$key]) || self::$initial_hashes[$key] !== $current_hash) {
                    if ($key === self::ACF_BULK_OPTION_KEY) update_option($key, $data, 'no');
                    else set_transient($key, $data, self::$ttl);
                }
            }
        }
    }

    public static function handle_post_change($post_id) {
        if (wp_is_post_revision($post_id)) return;
        delete_transient(self::PREFIX . 'post_' . $post_id . '_bulk');
        self::flush_by_manifest(["post:id:{$post_id}", "post:type:" . get_post_type($post_id)]);
    }

    public static function handle_term_change($term_id, $tt_id, $taxonomy) {
        self::flush_by_manifest(["term:id:{$term_id}", "taxonomy:{$taxonomy}"]);
    }

    private static function flush_by_manifest($deps) {
        $manifest = get_option(self::MANIFEST_KEY, []);
        $changed = false;
        foreach ($deps as $dep) {
            $dep = str_replace(['_', ':'], ':', $dep);
            if (isset($manifest[$dep])) {
                foreach ($manifest[$dep] as $cache_key) delete_transient($cache_key);
                unset($manifest[$dep]);
                $changed = true;
            }
        }
        if ($changed) update_option(self::MANIFEST_KEY, $manifest, false);
    }

    /**
     * ⚠️ PANIC BUTTON: Tüm cache verilerini siler.
     */
    public static function purge_all($type = '') {
        global $wpdb;
        $search = self::PREFIX . ($type ? $type . '_' : '');
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", 
            "_transient_{$search}%", 
            "_transient_timeout_{$search}%"
        ));
        if (empty($type)) {
            delete_option(self::MANIFEST_KEY);
            delete_option(self::ACF_BULK_OPTION_KEY);
        }
    }

    private static function resolve_acf_target($post_id) {
        if ($post_id === 'options' || $post_id === 'option') return ['type' => 'opt', 'id' => 'global'];
        return ['type' => 'post', 'id' => $post_id];
    }

    public static function rebuild_options_bulk() {
        delete_option(self::ACF_BULK_OPTION_KEY);
    }
}

/**
 * 🎯 ÖRNEK VE TAM KAPSAMLI INIT
 */
QueryCache::init([
    'cache'        => true,  // Master Şalter. False yapılırsa her şey durur ve tüm cache temizlenir.
    //'ttl'          => 30 * DAY_IN_SECONDS,
    //'enable_admin' => false, // Admin panelinde cache çalışmasın (Güvenli mod).
   // 'lazy_pilot'   => true,  // Aynı anda gelen 100 isteği tek bir DB sorgusuna düşürür.
    
    'config' => [
        'wrap'       => true,  // QueryCache::wrap() kullanımını açar/kapatır.
        'get_field'  => true,  // true: Tüm get_field'ları yakalar. 'manual': Sadece QueryCache::get_field. false: Temizler.
        'get_posts'   => true, // WP'nin orjinal Query'lerini yakalar. Dikkatli kullan, pagination bozabilir.
        'get_post'   => true,
        'get_terms'   => true,
        'get_term'   => true,
        'menu'       => true,  // Menüleri ve içindeki ACF alanlarını komple paketler.
        'wp_options' => true   // Sadece QueryCache::get_option() fonksiyonu ile çağrılanları cacheler.
    ]
]);