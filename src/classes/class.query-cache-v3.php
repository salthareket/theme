<?php
/**
 * QueryCache Engine v5.5 - "The Love & Fury Edition"
 * Sabine & Tolga Final Boss - Full Automated & Parameter Controlled
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
     * Motoru Başlat ve Kancaları Tak
     */
    public static function init($args = []) {
        if (isset($args['cache'])) self::$cache = $args['cache'];
        if (isset($args['ttl']))   self::$ttl   = $args['ttl'];
        if (isset($args['enable_admin'])) self::$enable_admin = $args['enable_admin'];
        
        if (isset($args['config'])) {
            self::$config = array_merge(self::$config, $args['config']);
        }

        // MASTER ŞALTER: Kapalıysa her şeyi sil ve dur
        if (self::$cache === false) {
            self::clear_all_cache();
            return;
        }

        // Admin koruması
        if (is_admin() && !self::$enable_admin) {
            self::$cache = false;
            return;
        }

        // OTOMATİK MENÜ KANCASI
        if (isset(self::$config['menu']) && self::$config['menu'] === true) {
            add_filter('pre_wp_nav_menu', [__CLASS__, 'auto_menu_cache'], 10, 2);
            add_action('wp_update_nav_menu', [__CLASS__, 'handle_menu_change']);
        } else {
            // Sadece menü cache'i varsa sil, yoksa her seferinde DB'ye gitme!
            if (get_option('_transient_timeout_qcache_menu_default_tr') || get_option('qcache_menu_manifest')) {
                 self::purge_cache('menu_');
            }
        }

        // OPTIONS GÜNCELLEME KANCASI
        add_action('updated_option', [__CLASS__, 'rebuild_options_bulk']);
        
        // POST DEĞİŞİKLİK KANCALARI
        add_action('save_post', [__CLASS__, 'handle_post_change'], 10, 3);
        add_action('delete_post', [__CLASS__, 'handle_post_change']);

        // Kapanışta manifesti yaz
        add_action('shutdown', [__CLASS__, 'save_runtime_manifest']);
    }

    /**
     * ⚡ WRAP: Her şeyin babası
    */
    public static function wrap($key, $callback, $dependencies = []) {
        if (!self::$cache || self::$is_processing) return $callback();

        $full_key = self::PREFIX . $key;
        if (isset(self::$runtime_cache[$full_key])) return self::$runtime_cache[$full_key];

        $cached_data = get_transient($full_key);
        
        // Hata kontrolü: Eğer data string ise ve 'O:11:"Timber\Menu"' gibi başlıyorsa 
        // ve unserialize edilemiyorsa false dönmesini sağla
        if ($cached_data !== false) {
            self::$runtime_cache[$full_key] = ($cached_data === self::NOT_FOUND) ? null : $cached_data;
            return self::$runtime_cache[$full_key];
        }

        self::$is_processing = true;
        $data = $callback();
        self::$is_processing = false;

        // Timber objesi gelirse bunu serialize etmeden önce kontrol et
        $store_data = ($data === null || $data === false || $data === '') ? self::NOT_FOUND : $data;
        
        set_transient($full_key, $store_data, self::$ttl);
        self::$runtime_cache[$full_key] = $data;

        return $data;
    }

    public static function auto_menu_cache($output, $args) {
        return $output;

        if (!self::$cache || !self::$config['menu']) return $output;
        
        $location = $args->theme_location ?? 'default';
        $lang = function_exists('ml_get_current_language') ? ml_get_current_language() : get_locale();
        $key = 'menu_' . $location . '_' . $lang;

        return self::wrap($key, function() use ($args) {
            remove_filter('pre_wp_nav_menu', [get_called_class(), 'auto_menu_cache'], 10);
            $menu_html = wp_nav_menu(array_merge((array)$args, ['echo' => false]));
            add_filter('pre_wp_nav_menu', [get_called_class(), 'auto_menu_cache'], 10, 2);
            return $menu_html;
        });
    }

    public static function get_posts($args) {
        if (!self::$cache || !self::$config['get_posts']) return get_posts($args);
        $key = 'posts_' . md5(serialize($args));
        $type = $args['post_type'] ?? 'post';
        return self::wrap($key, function() use ($args) { return get_posts($args); }, ['post_type' => $type]);
    }

    public static function get_post($post_id) {
        if (!self::$cache || !self::$config['get_post']) return get_post($post_id);
        return self::wrap('post_' . $post_id, function() use ($post_id) { return get_post($post_id); }, ['post' => $post_id]);
    }

    public static function get_terms($args) {
        if (!self::$cache || !self::$config['get_terms']) return get_terms($args);
        $key = 'terms_' . md5(serialize($args));
        $tax = $args['taxonomy'] ?? 'category';
        return self::wrap($key, function() use ($args) { return get_terms($args); }, ['tax' => $tax]);
    }

    /**
     * 🎯 EKSİKSİZ GET_FIELD (v5.5 Fixed - ml_ Functions Edition)
     * Bu sürüm hem v5.5'in runtime yapısını korur hem de v1'in options/dil zekasını ekler.
     */
    public static function get_field($selector, $post_id = null, $format = true) {
        if (!function_exists("get_field") || !self::$cache) return get_field($selector, $post_id, $format);

        $post_id = $post_id ?: get_the_ID();
        $resolved = self::resolve_acf_target($post_id);
        $clean_selector = preg_replace('/_[a-z]{2}_[A-Z]{2}$/', '', $selector);

        
        $bulk_key = ($resolved['type'] === 'opt') ? self::ACF_BULK_OPTION_KEY : self::PREFIX . $resolved['type'] . '_' . $resolved['id'] . '_bulk';

        if (!isset(self::$runtime_cache[$bulk_key])) {
            $stored = ($resolved['type'] === 'opt') ? get_option($bulk_key) : get_transient($bulk_key);
            self::$runtime_cache[$bulk_key] = is_array($stored) ? $stored : [];
            self::$initial_hashes[$bulk_key] = md5(serialize(self::$runtime_cache[$bulk_key]));
        }

        $check_key = $clean_selector;//($resolved['type'] === 'opt' && strpos($selector, 'options_') !== 0) ? 'options_' . $selector : $selector;

        if (array_key_exists($check_key, self::$runtime_cache[$bulk_key])) {
            $val = self::$runtime_cache[$bulk_key][$check_key];
            return ($val === self::NOT_FOUND) ? false : $val;
        }

        self::$is_processing = true;
        $value = get_field($selector, $post_id, $format);
        self::$is_processing = false;

        // NULL veya False gelirse NOT_FOUND yaz ki DB'ye tekrar gitmesin
        $final_val = ($value === null || $value === false || $value === '') ? self::NOT_FOUND : $value;
        self::$runtime_cache[$bulk_key][$check_key] = $final_val;

        return $value;
    }

    private static function resolve_acf_target($post_id) {
        // Namespace/Slash farketmez, options'ı yakala
        if (is_string($post_id) && ($post_id === 'options' || $post_id === 'option' || strpos($post_id, 'options_') === 0)) {
            return ['type' => 'opt', 'id' => 'global'];
        }
        $type = 'post';
        if (is_string($post_id)) {
            if (strpos($post_id, 'user_') === 0) $type = 'user';
            elseif (strpos($post_id, 'term_') === 0) $type = 'term';
        }
        return ['type' => $type, 'id' => $post_id];
    }

    public static function get_option($option, $default = false) {
        if (!self::$cache || !self::$config['wp_options']) return get_option($option, $default);
        return self::wrap('opt_' . $option, function() use ($option, $default) { return get_option($option, $default); }, ['opt' => 'global']);
    }

    // --- YARDIMCI VE TEMİZLİK METODLARI ---

    private static function register_dependency($key, $type, $id) {
        if (!isset(self::$runtime_manifest[$type])) self::$runtime_manifest[$type] = [];
        
        // Eğer $id bir array ise (örn: get_posts'tan gelen birden fazla post type)
        $ids = is_array($id) ? $id : [$id];

        foreach ($ids as $single_id) {
            if (!isset(self::$runtime_manifest[$type][$single_id])) {
                self::$runtime_manifest[$type][$single_id] = [];
            }
            if (!in_array($key, self::$runtime_manifest[$type][$single_id])) {
                self::$runtime_manifest[$type][$single_id][] = $key;
            }
        }
    }

    public static function handle_post_change($post_id) {
        // Basit temizlik
        delete_option(self::ACF_BULK_OPTION_KEY);
    }

    public static function handle_menu_change() {
        self::flush_by_manifest('opt', 'global');
        self::purge_cache('menu_');
    }

    public static function flush_by_manifest($type, $id) {
        $manifest = get_option(self::MANIFEST_KEY, []);
        if (isset($manifest[$type][$id])) {
            foreach ($manifest[$type][$id] as $cache_key) {
                delete_transient($cache_key);
            }
            unset($manifest[$type][$id]);
            update_option(self::MANIFEST_KEY, $manifest);
        }
    }

    public static function save_runtime_manifest() {
        if (!self::$cache || empty(self::$runtime_cache)) return;

        foreach (self::$runtime_cache as $key => $data) {
            // Options paketini her zaman yaz, boş olsa bile (NF olarak)
            if ($key === self::ACF_BULK_OPTION_KEY) {
                update_option($key, $data, 'no');
                continue; 
            }

            if (!is_array($data) && $key != self::ACF_BULK_OPTION_KEY) {
                 // Diğer verileri transient olarak yaz
                 $new_hash = md5(serialize($data));
                 if (isset(self::$initial_hashes[$key]) && self::$initial_hashes[$key] === $new_hash) continue;
                 set_transient($key, $data, self::$ttl);
            }
        }
    }

    public static function purge_cache($search = '') {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $wpdb->options WHERE option_name LIKE %s OR option_name LIKE %s",
            "_transient_" . self::PREFIX . "{$search}%",
            "_transient_timeout_" . self::PREFIX . "{$search}%"
        ));
    }

    public static function clear_all_cache() {
        self::purge_cache('');
        delete_option(self::MANIFEST_KEY);
        delete_option(self::ACF_BULK_OPTION_KEY);
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