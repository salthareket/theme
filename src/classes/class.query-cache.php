<?php
/**
 * QueryCache Engine v6.7 - "The DB Crusher"
 * Sorun: Bulk Cache boşken tek tek sorgu atıyordu.
 * Çözüm: Bulk boşsa, SQL ile tüm options_% verilerini tek seferde çeker.
 */

class QueryCache {

    public static $options = [];
    public static $initiated = false; // Guard: Birden fazla init'i engeller

    const PREFIX              = 'qcache_';
    const DEFAULT_TTL         = 30 * DAY_IN_SECONDS;
    const ACF_BULK_OPTION_KEY = 'qcache_options_bulk';
    const NOT_FOUND           = '__QC_NF__'; 

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
        'wp_options' => true,
        'menu'       => true
    ];

    protected static $runtime_cache    = [];
    protected static $initial_hashes   = []; 
    private static   $is_processing    = false; 
    private static   $is_saving        = false; 

    public static function init($args = []) {

        if (self::$initiated) return; // Zaten çalıştıysa dur

        if (isset($args['cache'])) self::$cache = $args['cache'];
        if (isset($args['ttl']))   self::$ttl   = $args['ttl'];
        if (is_admin() && !self::$enable_admin) { self::$cache = false; return; }

        if (isset($options['config']) && is_array($options['config'])) {
            self::$config = array_merge(self::$config, $options['config']);
        }

        $class = get_called_class();

        if (self::$cache === false) {
            self::clear_all_cache();
        }

        if (isset(self::$config['menu']) && self::$config['menu'] === false) {
            self::purge_cache('menu_'); 
        }

        add_action('updated_option', [$class, 'rebuild_options_bulk'], 10, 3);
        add_action('added_option',   [$class, 'rebuild_options_bulk'], 10, 3);
        add_action('deleted_option', [$class, 'rebuild_options_bulk'], 10, 3);

        add_action('save_post', [$class, 'handle_post_change'], 10, 3);
        add_action('delete_post', [$class, 'handle_post_change']);
        add_action('transition_post_status', function($new, $old, $post) use ($class) {
            if ($new === 'publish' || $old === 'publish') $class::handle_post_change($post->ID);
        }, 10, 3);

        add_action('created_term', [$class, 'handle_term_change'], 99, 3);
        add_action('edited_term',  [$class, 'handle_term_change'], 99, 3);
        add_action('delete_term',  [$class, 'handle_term_change'], 99, 3);

        // OTOMATİK MENÜ KANCASI
        /*if (self::$cache && self::$config['menu']) {
            add_filter('pre_wp_nav_menu', [$class, 'get_menu_cache'], 10, 2);
            add_filter('wp_nav_menu', [$class, 'set_menu_cache'], 10, 2);
        }*/

        add_action('shutdown', [$class, 'save_runtime_manifest'], 999);

        self::$initiated = true;
    }

    /**
     * EKSİK OLAN MANTIK BURASI:
     * Eğer bulk cache yoksa, gidip 'options_%' ile başlayan her şeyi tek SQL'de alır.
     */
    private static function ensure_bulk_loaded($bulk_key) {
        if (isset(self::$runtime_cache[$bulk_key])) return;

        // Önce mevcut cache'i kontrol et
        $stored = get_option($bulk_key);

        if (empty($stored) && $bulk_key === self::ACF_BULK_OPTION_KEY) {
            // 🔥 KRİTİK NOKTA: Cache boşsa DB'yi süpür!
            global $wpdb;
            $results = $wpdb->get_results("SELECT option_name, option_value FROM $wpdb->options WHERE option_name LIKE 'options_%'");
            
            $stored = [];
            foreach ($results as $row) {
                // ACF verilerini unserialize et (WordPress get_option mantığıyla)
                $stored[$row->option_name] = maybe_unserialize($row->option_value);
            }
        }

        self::$runtime_cache[$bulk_key] = is_array($stored) ? $stored : [];
        self::$initial_hashes[$bulk_key] = md5(serialize(self::$runtime_cache[$bulk_key]));
    }

    public static function get_field($selector, $post_id = null, $format = true) {
        /*if (!function_exists("get_field") || !self::$cache) return get_field($selector, $post_id, $format);

        $post_id = $post_id ?: get_the_ID();
        $resolved = self::resolve_acf_target($post_id);
        
        $bulk_key = ($resolved['type'] === 'opt') ? self::ACF_BULK_OPTION_KEY : self::PREFIX . $resolved['type'] . '_' . $resolved['id'] . '_bulk';*/

        // Config kapalıysa veya çekim yapılıyorsa direkt ACF'e git, Bulk mekanizmasına hiç girme!
        if (!self::$cache  || !self::$config['get_field'] || self::$is_processing) {
            return get_field($selector, $post_id, $format);
        }

        $post_id = $post_id ?: get_the_ID();
        $resolved = self::resolve_acf_target($post_id);
        
        // Selector temizliği
        $clean_selector = preg_replace('/_[a-z]{2}_[A-Z]{2}$/', '', $selector);
        
        $bulk_key = ($resolved['type'] === 'opt') ? self::ACF_BULK_OPTION_KEY : self::PREFIX . 'post_' . $resolved['id'] . '_bulk';
        
        self::ensure_bulk_loaded($bulk_key);

        $check_key = ($resolved['type'] === 'opt' && strpos($selector, 'options_') !== 0) ? 'options_' . $selector : $selector;

        if (array_key_exists($check_key, self::$runtime_cache[$bulk_key])) {
            $val = self::$runtime_cache[$bulk_key][$check_key];
            return ($val === self::NOT_FOUND) ? false : $val;
        }

        // Eğer hala yoksa (tekil bir field ise bulk içinde olmayabilir)
        self::$is_processing = true;
        $value = get_field($selector, $post_id, $format);
        self::$is_processing = false;

        self::$runtime_cache[$bulk_key][$check_key] = ($value === null || $value === false) ? self::NOT_FOUND : $value;
        return $value;
    }

    /*public static function get_option($option, $default = false) {
        if (!self::$cache || !self::$config['wp_options'] || $option === self::ACF_BULK_OPTION_KEY) return get_option($option, $default);
        
        // Eğer option 'options_' ile başlıyorsa bulk cache'den bak
        if (strpos($option, 'options_') === 0) {
            self::ensure_bulk_loaded(self::ACF_BULK_OPTION_KEY);
            if (array_key_exists($option, self::$runtime_cache[self::ACF_BULK_OPTION_KEY])) {
                $val = self::$runtime_cache[self::ACF_BULK_OPTION_KEY][$option];
                return ($val === self::NOT_FOUND) ? $default : $val;
            }
        }

        return get_option($option, $default);
    }*/
    public static function get_option($option, $default = false) {
        // 1. Şalter ve Bulk Key Kontrolü
        if (!self::$cache || !self::$config['wp_options'] || $option === self::ACF_BULK_OPTION_KEY) {
            return get_option($option, $default);
        }

        // 2. Bulk Cache'i Yükle (Eksikse LIKE 'options_%' ile her şeyi çeker)
        self::ensure_bulk_loaded(self::ACF_BULK_OPTION_KEY);

        // 3. Bulk Dizisi İçinde Var mı?
        if (array_key_exists($option, self::$runtime_cache[self::ACF_BULK_OPTION_KEY])) {
            $val = self::$runtime_cache[self::ACF_BULK_OPTION_KEY][$option];
            return ($val === self::NOT_FOUND) ? $default : $val;
        }

        // 4. Bulk'ta Yoksa: DB'den Çek ve BULK'ın İçine At (get_field mantığının aynısı)
        $value = get_option($option, $default);
        
        // Burası kritik: Tekil transient DEĞİL, bulk dizisinin içine ekliyoruz
        self::$runtime_cache[self::ACF_BULK_OPTION_KEY][$option] = ($value === null || $value === false) ? self::NOT_FOUND : $value;

        return $value;
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

    public static function wrap($key, $callback) {
        if (!self::$cache || self::$is_processing) return $callback();
        $full_key = self::PREFIX . $key;
        if (isset(self::$runtime_cache[$full_key])) return self::$runtime_cache[$full_key];
        $cached = get_transient($full_key);
        if ($cached !== false) {
            self::$runtime_cache[$full_key] = ($cached === self::NOT_FOUND) ? null : $cached;
            self::$initial_hashes[$full_key] = md5(serialize($cached));
            return self::$runtime_cache[$full_key];
        }
        self::$is_processing = true;
        $data = $callback();
        self::$is_processing = false;
        self::$runtime_cache[$full_key] = $data;
        return $data;
    }

    /**
     * MENÜ CACHE GETİR (Dinamik Key)
     */
    public static function get_menu_cache($output, $args) {
        if (!self::$cache || !self::$config['menu'] || self::$is_processing) return $output;

        // Senin özel dil fonksiyonunu buraya çaktım abi
        $lang = function_exists('ml_get_current_language') ? ml_get_current_language() : get_locale();
        $menu_key = 'menu_' . md5(serialize($args)) . '_' . $lang;
        
        $full_key = self::PREFIX . $menu_key;
        $data = get_transient($full_key);

        if ($data !== false) {
            return ($data === self::NOT_FOUND) ? '' : $data;
        }

        return $output;
    }

    /**
     * MENÜ CACHE KAYDET
     */
    public static function set_menu_cache($output, $args) {
        if (!self::$cache || !self::$config['menu'] || self::$is_processing || empty($output)) return $output;

        $lang = function_exists('ml_get_current_language') ? ml_get_current_language() : get_locale();
        $menu_key = 'menu_' . md5(serialize($args)) . '_' . $lang;
        
        self::$runtime_cache[self::PREFIX . $menu_key] = $output;
        return $output;
    }

    /**
     * SHUTDOWN SIRASINDA ARKADAN VERİ BASMASINI ENGELLE
     */
    public static function save_runtime_manifest() {
        // BURASI KRİTİK: Eğer master şalter kapalıysa asla DB'ye dokunma!
        if (!self::$cache || empty(self::$runtime_cache) || self::$is_saving) return;

        // Eğer menu config kapalıysa, çalışma anındaki cache'ten menüleri ayıkla
        if ( self::$config['menu'] === false ) {
            foreach ( self::$runtime_cache as $key => $val ) {
                if ( strpos($key, self::PREFIX . 'menu_') !== false ) {
                    unset(self::$runtime_cache[$key]);
                }
            }
        }
        
        self::$is_saving = true;
        foreach (self::$runtime_cache as $key => $data) {
            $new_hash = md5(serialize($data));
            if (isset(self::$initial_hashes[$key]) && self::$initial_hashes[$key] === $new_hash) continue;
            
            $to_save = ($data === null || $data === false || $data === '') ? self::NOT_FOUND : $data;
            if ($key === self::ACF_BULK_OPTION_KEY) {
                update_option($key, $data, 'no');
            } else {
                set_transient($key, $to_save, self::$ttl);
            }
        }
    }

    private static function resolve_acf_target($post_id) {
        if (is_string($post_id) && (in_array($post_id, ['options', 'option']) || strpos($post_id, 'options_') === 0)) return ['type' => 'opt', 'id' => 'global'];
        return ['type' => 'post', 'id' => $post_id ?: 'global'];
    }

    public static function rebuild_options_bulk($option = null) {
        if (self::$is_saving) return;
        if ($option === null || $option === self::ACF_BULK_OPTION_KEY || (is_string($option) && strpos($option, 'options_') === 0)) {
            delete_option(self::ACF_BULK_OPTION_KEY);
            self::clear_all_cache();
        }
    }

    public static function handle_post_change() { self::rebuild_options_bulk(); }
    public static function handle_term_change() { self::rebuild_options_bulk(); }

    public static function purge_cache($search = '') {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $wpdb->options WHERE option_name LIKE %s OR option_name LIKE %s",
            "_transient_" . self::PREFIX . "{$search}%",
            "_transient_timeout_" . self::PREFIX . "{$search}%"
        ));
    }
    
    public static function clear_all_cache() {
        global $wpdb;
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_qcache_%' OR option_name LIKE '_transient_timeout_qcache_%'");
    }
}

/**
 * 🎯 ÖRNEK VE TAM KAPSAMLI INIT
 */
$enable_object_cache = get_option("options_enable_object_cache", false);
$object_cache_types = [];
if($enable_object_cache){
    $object_cache_types = get_option("options_object_cache_types");    
}

QueryCache::init([
    'cache'        => $enable_object_cache,  // Master Şalter. False yapılırsa her şey durur ve tüm cache temizlenir.
    //'ttl'          => 30 * DAY_IN_SECONDS,
    //'enable_admin' => false, // Admin panelinde cache çalışmasın (Güvenli mod).
    // 'lazy_pilot'   => true,  // Aynı anda gelen 100 isteği tek bir DB sorgusuna düşürür.
    'config' => [
        'wrap'       => in_array('wrap', $object_cache_types),  // QueryCache::wrap() kullanımını açar/kapatır.
        'get_field'  => in_array('get_field', $object_cache_types),  // true: Tüm get_field'ları yakalar. 'manual': Sadece QueryCache::get_field. false: Temizler.
        'get_posts'   => in_array('get_posts', $object_cache_types), // WP'nin orjinal Query'lerini yakalar. Dikkatli kullan, pagination bozabilir.
        'get_post'   => in_array('get_post', $object_cache_types),
        'get_terms'   => in_array('get_terms', $object_cache_types),
        'get_term'   => in_array('get_term', $object_cache_types),
        'menu'       => false,//in_array('menu', $object_cache_types),  // Menüleri ve içindeki ACF alanlarını komple paketler.
        'wp_options' => in_array('wp_options', $object_cache_types)   // Sadece QueryCache::get_option() fonksiyonu ile çağrılanları cacheler.
    ]
]);