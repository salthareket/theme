<?php

class QueryCache {

    // --- SABİTLER (CONSTANTS) ---
    const MANIFEST_OPTION_KEY = 'salt_query_cache_manifest';
    const CACHE_KEY_PREFIX = 'custom_query_';
    const ACF_BULK_OPTION_KEY = 'salt_options_bulk';

    /** @var bool Tüm cache sistemini devre dışı bırakır */
    public static $disable_caching = false; // TRUE ise tüm cache'ler bypass edilir

    /** @var array|null ACF option'ları için Ram Cache (Tek veritabanı sorgusu için) */
    protected static $bulk_options = null;
    
    // --- BAŞLANGIÇ VE HOOK KAYITLARI ---

    /**
     * Sınıfın hook'larını WordPress'e kaydeder.
     * Bu metot, sınıf tanımlandıktan sonra bir kere çağrılmalıdır.
     * Örneğin: SaltBase::initialize_hooks();
     */
    public static function initialize_hooks() {
        if (self::$disable_caching) return;

        // Query Cache Manifest Temizleme Hook'ları
        add_action('save_post', [__CLASS__, 'clear_cache_on_post_change'], 20, 2);
        add_action('deleted_post', [__CLASS__, 'clear_cache_on_post_change'], 20, 2);
        add_action('edit_term', [__CLASS__, 'clear_cache_on_term_change'], 20, 3);
        add_action('delete_term', [__CLASS__, 'clear_cache_on_term_change'], 20, 4);

        // ACF Option Cache Yönetimi Hook'ları
        // Bu hook'lar statik olmayan metotları işaret etmeli (Örnek oluşturulacaksa) veya static yapılmalı.
        // Basitlik ve tutarlılık için Hepsini static yapıyoruz.
        add_action('acf/update_value', [__CLASS__, 'clear_cached_option_on_update'], 19, 3);
        add_action('acf/save_post', [__CLASS__, 'rebuild_salt_options_cache'], 99, 1);
        add_filter('acf/load_value', [__CLASS__, 'load_value_to_cache'], 20, 3);
    }
    
    // --- QUERY CACHE İŞLEMLERİ (MANIFEST DESTEKLİ) ---

    /** 🔥 WP_Query sonuçlarını cache'li veya cache'siz getirir */
    public static function get_cached_query($args = [], $mode = 'object') {

        if (self::$disable_caching) {
            return self::run_query($args, $mode);
        } 

        // Cache anahtarını post tipi ve mode bilgisi ile oluşturuyoruz (hata ayıklamada kolaylık)
        $post_type_identifier = isset($args['post_type']) ? (is_array($args['post_type']) ? implode('_', $args['post_type']) : $args['post_type']) : 'any';
        $cache_key = self::CACHE_KEY_PREFIX . $post_type_identifier . '_' . $mode . '_' . md5(serialize($args));
        
        $cached = get_transient($cache_key);

        if ($cached === false) {
            $cached = self::run_query($args, $mode);
            
            // Manifest'e kaydetme: Önbellek hangi post/term değişikliğinde silinecek?
            $dependencies = self::extract_dependencies_from_args($args);
            self::register_cache_key($cache_key, $dependencies);
            
            set_transient($cache_key, $cached, HOUR_IN_SECONDS);
        }

        return $cached;
    }

    /** 🔎 WP_Query çalıştırır ve istenen formata çevirir (QueryCache'ten alındı) */
    protected static function run_query($args, $mode) {
        $query = new WP_Query($args);

        return match ($mode) {
            'posts' => $query->posts,
            'data' => [
                'posts' => $query->posts,
                'found_posts' => $query->found_posts,
                'max_num_pages' => $query->max_num_pages,
                // ... diğer query istatistikleri
            ],
            default => $query,
        };
    }

    // --- MANIFEST İŞLEMLERİ (ÖNBELLEK TEMİZLEME HARİTASI) ---

    private static function get_manifest() {
        return get_option(self::MANIFEST_OPTION_KEY, []);
    }

    private static function save_manifest($manifest) {
        update_option(self::MANIFEST_OPTION_KEY, $manifest);
    }

    private static function register_cache_key($cache_key, $dependencies) {
        $manifest = self::get_manifest();
        
        foreach ($dependencies as $type => $value) {
            if (!isset($manifest[$type])) $manifest[$type] = [];
            if (!isset($manifest[$type][$value])) $manifest[$type][$value] = [];

            if (!in_array($cache_key, $manifest[$type][$value])) {
                $manifest[$type][$value][] = $cache_key;
            }
        }
        self::save_manifest($manifest);
    }

    private static function extract_dependencies_from_args($args) {
        $dependencies = [];

        // 1. Post Tipi Bağımlılığı (Örn: post_type => news)
        $post_type = isset($args['post_type']) ? (array) $args['post_type'] : ['post'];
        $dependencies['post_type'] = $post_type[0]; // Sadece ilk post tipini kullanıyoruz

        // 2. Taksonomi Bağımlılığı (Örn: taxonomy => category:15)
        if (!empty($args['tax_query'])) {
            foreach ($args['tax_query'] as $tax_query) {
                if (isset($tax_query['taxonomy']) && isset($tax_query['terms'])) {
                    $taxonomy = $tax_query['taxonomy'];
                    $terms = (array) $tax_query['terms'];
                    
                    foreach ($terms as $term_id) {
                        $dependencies['taxonomy'] = $taxonomy . ':' . $term_id; 
                    }
                }
            }
        }
        return $dependencies;
    }
    
    // --- QUERY CACHE TEMİZLEME MEKANİZMASI ---

    public static function clear_cache_on_post_change($post_id, $post = null) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;
        if (self::$disable_caching) return;
        
        $dependencies_to_clear = [];
        $post_type = get_post_type($post_id);
        
        // Post Type bağımlılığını ekle
        $dependencies_to_clear['post_type'] = $post_type;

        // İlişkili olduğu tüm taksonomi/term bağımlılıklarını ekle
        $taxonomies = get_object_taxonomies($post_type);
        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_post_terms($post_id, $taxonomy, ['fields' => 'ids']);
            foreach ($terms as $term_id) {
                $dependencies_to_clear['taxonomy'] = $taxonomy . ':' . $term_id; 
            }
        }

        self::process_cache_clearing($dependencies_to_clear);
    }
    
    public static function clear_cache_on_term_change($term_id, $tt_id, $taxonomy) {
        if (self::$disable_caching) return;
        // Sadece bu taksonomi/term bağımlılığını temizle
        self::process_cache_clearing(['taxonomy' => $taxonomy . ':' . $term_id]);
    }

    private static function process_cache_clearing($dependencies_to_clear) {
        $manifest = self::get_manifest();
        $keys_to_delete = [];

        foreach ($dependencies_to_clear as $type => $value) {
            if (isset($manifest[$type][$value])) {
                $keys_to_delete = array_merge($keys_to_delete, $manifest[$type][$value]);
                
                // Temizlenen anahtarları manifest'ten sil (pruning)
                unset($manifest[$type][$value]);
            }
        }
        self::save_manifest($manifest); // Manifest'i güncelle

        $keys_to_delete = array_unique($keys_to_delete);
        foreach ($keys_to_delete as $cache_key) {
            delete_transient($cache_key);
        }
    }
    
    // --- ACF VE OPTION CACHE İŞLEMLERİ (QueryCache'ten alındı) ---

    /** ✅ Tek bir option değerini cache'li ya da doğrudan getirir (Bulk Option Cache) */
    public static function get_cached_option($key, $method = 'auto') {

        if (self::$disable_caching) {
            return ($method === 'acf' || ($method === 'auto' && function_exists('get_field')))
                ? get_field($key, 'option')
                : get_option("options_{$key}");
        }

        // Toplu cache yüklü değilse, veritabanından tek seferde çek (Bulk Load)
        if (self::$bulk_options === null) {
            self::$bulk_options = get_option(self::ACF_BULK_OPTION_KEY, []);
        }

        if (isset(self::$bulk_options[$key])) {
            return self::$bulk_options[$key]; // Ram Cache hit!
        }

        $polylang_filter_found = self::remove_polylang_option_filter($key);

        // Eğer bulk'ta yoksa, normal yoldan çek ve bulk'a ekle
        $value = ($method === 'acf' || ($method === 'auto' && function_exists('get_field')))
            ? get_field($key, 'option')
            : get_option("options_{$key}");

        self::add_polylang_option_filter($key, $polylang_filter_found);

        self::$bulk_options[$key] = $value;
        update_option(self::ACF_BULK_OPTION_KEY, self::$bulk_options);

        return $value;
    }
    
    /** 🧽 ACF option kaydında cache temizler */
    public static function clear_cached_option_on_update($value, $post_id, $field) {
        if ($post_id !== 'options' || self::$disable_caching) return $value;

        // Tekil transient temizliğine gerek yok, sadece Bulk Option'ı silmek yeterli
        delete_option(self::ACF_BULK_OPTION_KEY);

        return $value;
    }

    /** ♻️ ACF option cache yeniden kurar (ACF'nin save_post hook'unda) */
    public static function rebuild_salt_options_cache($post_id) {
        if ($post_id !== 'options' || self::$disable_caching) return;

        $bulk = [];
        // Tüm option field'larını çekip, tek bir option key'ine kaydet
        if (function_exists('get_fields')) {
             foreach (get_fields('option') as $k => $v) {
                $bulk[$k] = $v;
             }
        }
        update_option(self::ACF_BULK_OPTION_KEY, $bulk);
        self::$bulk_options = $bulk; // Ram Cache'i de güncelle
    }
    
    /** 🎯 ACF'den gelen field'ı RAM cache'e alır (Tekil Field Hızlandırma) */
    public static function load_value_to_cache($value, $post_id, $field) {
        if (self::$disable_caching) return $value;
        if (!is_scalar($value) && !is_array($value)) return $value;

        $excluded_types = ['repeater', 'flexible_content', 'gallery', 'post_object', 'file', 'image'];
        if (isset($field['type']) && in_array($field['type'], $excluded_types)) return $value;

        // wp_cache_set kullanarak hızlı Ram Cache'e kaydet
        $key = "acf_field_{$field['name']}_{$post_id}_1";
        wp_cache_set($key, $value, 'acf');

        return $value;
    }

    private static function remove_polylang_option_filter(string $key): bool {
        if (!function_exists('pll_current_language')) {
            return false;
        }
        
        global $polylang;
        
        // Polylang nesnesinin varlığını ve gerekli metodu kontrol et
        if (isset($polylang) && is_object($polylang) && method_exists($polylang, 'filter_options')) {
            // Polylang'ın get_option filtresini kaldır: 'pre_option_{$key}'
            // Bu, Polylang'ın çeviri çekme mantığını atlayarak ACF formatlamasına izin verir.
            if (has_filter('pre_option_' . $key, array($polylang, 'filter_options'))) {
                remove_filter('pre_option_' . $key, array($polylang, 'filter_options'));
                return true;
            }
        }
        return false;
    }
    private static function add_polylang_option_filter(string $key, bool $found): void {
        if (!$found) {
            return; // Filtre bulunup kaldırılmamışsa, geri eklemeye gerek yok.
        }

        global $polylang;
        
        // Polylang nesnesinin varlığını ve gerekli metodu kontrol et
        if (isset($polylang) && is_object($polylang) && method_exists($polylang, 'filter_options')) {
            // Filtreyi orijinal haline geri ekle.
            add_filter('pre_option_' . $key, array($polylang, 'filter_options'));
        }
    }
    
    // --- GENEL TEMİZLİK ---
    
    /** 🧽 Tüm (Query ve Option) cache'i temizler */
    public static function delete_cache() {
        if (self::$disable_caching) return;

        global $wpdb;

        // Query Transient'ları toplu silme
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_custom_query_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_custom_query_%'");

        // Manifest ve Option Cache silme
        delete_option(self::MANIFEST_OPTION_KEY);
        delete_option(self::ACF_BULK_OPTION_KEY);

        // WP Object Cache'i temizle (Ram Cache)
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }
}

// BU KODU functions.php'nizin veya eklenti dosyanızın en altına EKLEYİN
QueryCache::initialize_hooks();