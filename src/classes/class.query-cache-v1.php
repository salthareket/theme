<?php
/**
 * QueryCache Engine v3.2 - "The Immortal Guardian"
 * Post Uçma Sorunu Giderildi - Lazy Pilot Optimize Edildi
 */

class QueryCache {

    const PREFIX              = 'qcache_';
    const DEFAULT_TTL         = 30 * DAY_IN_SECONDS;
    const MANIFEST_KEY        = 'qcache_manifest';
    const FIELD_MANIFEST_KEY  = 'qcache_field_manifest';
    const ACF_BULK_OPTION_KEY = 'qcache_options_bulk';
    const WP_BULK_OPTION_KEY  = 'qcache_opt_manifest';
    const LOCK_KEY            = 'qcache_global_lock';

    public static $cache        = 'auto'; // true, false, 'auto', 'manual'
    public static $enable_admin = false;
    public static $lazy_pilot   = true; 
    public static $ttl          = self::DEFAULT_TTL;

    public static $config = [
        'acf_meta'   => true,
        'wp_options' => true,
        'wp_query'   => true,
        'menus'      => true,
        'pll'        => false,
    ];

    protected static $bulk_options        = null;
    protected static $wp_bulk_options     = [];
    protected static $pll_static_storage  = [];
    private static   $is_processing       = false;
    private static   $runtime_manifest    = ['global' => [], 'fields' => []];
    protected static $runtime_transient_cache = [];
    private static $allowed_selectors = [];
    private static $format_depth = 0;

    private static $object_id_to_flush = null;
    private static $is_taxonomy_flush = false;

    public static function init($options = []) {
        // 1. Parametreleri ve Ayarları Yükle
        if (isset($options['cache']))        self::$cache        = $options['cache'];
        if (isset($options['enable_admin'])) self::$enable_admin = $options['enable_admin'];
        if (isset($options['lazy_pilot']))   self::$lazy_pilot   = $options['lazy_pilot'];
        if (isset($options['ttl']))          self::$ttl          = $options['ttl'];
        
        if (isset($options['config']) && is_array($options['config'])) {
            self::$config = array_merge(self::$config, $options['config']);
        }

        // 2. Ana Şalter (Tamamen kapalıysa hiçbir şey yapma)
        if (self::$cache === false) {
            self::purge_all(); 
            return; 
        }

        /**
         * 🎯 KRİTİK BÖLGE: TEMİZLİK MEKANİZMALARI
         * Bu kancalar admin engelinden ÖNCE tanımlanmalıdır.
         * Böylece admin panelinde işlem yaparken (Post silme, statü değişimi vb.)
         * ön yüzdeki cache'ler başarıyla temizlenir.
         */
        
        // Post Değişimleri (Save, Delete, Status Change)
        add_action('save_post', [__CLASS__, 'handle_post_change'], 1, 1);
        add_action('before_delete_post', [__CLASS__, 'handle_post_change'], 10, 1);
        add_action('transition_post_status', function($new, $old, $post) {
            // Eğer eski veya yeni statüden biri 'publish' ise bu post bir yerleri etkiliyordur.
            if ($new === 'publish' || $old === 'publish') {
                error_log(" [QCACHE] Statü Değişimi Tespit Edildi: " . $post->ID . " ($old -> $new)");
                self::handle_post_change($post->ID);
            }
        }, 10, 3);

        // Term (Kategori/Etiket) Değişimleri
        add_action('edited_term', [__CLASS__, 'handle_term_change'], 99, 3);
        add_action('create_term', [__CLASS__, 'handle_term_change'], 99, 3);
        add_action('pre_delete_term', [__CLASS__, 'handle_term_change'], 10, 3);

        // Menü ve Option Temizlikleri
        add_action('wp_update_nav_menu', function() { self::purge_all('menu'); });
        add_action('updated_option', [__CLASS__, 'purge_specific_option_cache'], 10, 1);
        add_action('deleted_option', [__CLASS__, 'purge_specific_option_cache'], 10, 1);
        add_action('acf/save_post', [__CLASS__, 'rebuild_options_bulk'], 20);
        add_action('acf/options_page/save', function($post_id) {
            delete_option(self::ACF_BULK_OPTION_KEY);
            self::$runtime_transient_cache = [];
        }, 10);

        /**
         * 🎯 ADMIN KORUMASI
         * Temizlik kancaları yukarıda kaydedildi. 
         * Buradan sonrası admin panelinde çalışmaz (Performans için).
         */
        if (is_admin() && !self::$enable_admin) return;

        // 3. OPTION PRELOAD
        if (self::$config['wp_options']) {
            self::preload_wp_options();
            add_filter('pre_option', [__CLASS__, 'smart_option_catcher'], 10, 3);
        }

        // 4. Otomatik Mod Kancaları (Auto-Pilot)
        if (self::$cache === 'auto' || self::$cache === true) {
            // WP Query & Terms
            if (self::$config['wp_query']) {
                add_filter('posts_pre_query', [__CLASS__, 'auto_pilot_posts'], 10, 2);
                add_filter('pre_get_terms', [__CLASS__, 'auto_pilot_terms'], 10, 1);
            }
            
            // Menüler
            if (self::$config['menus']) {
                add_filter('pre_wp_nav_menu', [__CLASS__, 'get_menu_cache'], 10, 2);
                add_filter('wp_nav_menu_objects', [__CLASS__, 'set_menu_cache'], 10, 2);
            }
            
            // Polylang
            if (self::$config['pll']) {
                add_action('terms_clauses', [__CLASS__, 'bypass_pll_sql'], 10, 3);
                add_filter('get_terms', [__CLASS__, 'set_pll_static_cache'], 10, 4);
            }
        }

        // 5. Kayıt Mekanizmaları (Shutdown)
        add_action('shutdown', [__CLASS__, 'save_option_manifest'], 99);
        add_action('shutdown', [__CLASS__, 'save_runtime_manifest'], 99);
        add_action('shutdown', [__CLASS__, 'save_acf_bulk_at_end'], 99);
    }

    public static function smart_option_catcher($pre, $option, $default = false) {
        // Admin panelinde veya sistem işlem yaparken karışma
        if (is_admin() || self::$is_processing) return $pre;

        // ACF'in ham dilli verilerini veya sistemin kritik optionlarını (cache anahtarları vb.) hariç tut
        $blacklist = ['qcache_', 'active_plugins', 'siteurl', 'home', 'cron'];
        foreach ($blacklist as $bad) {
            if (strpos($option, $bad) !== false) return $pre;
        }

        // 🎯 Pakette varsa veritabanına sormadan direkt döndür
        if (isset(self::$wp_bulk_options[$option])) {
            return self::$wp_bulk_options[$option];
        }

        // Pakette yoksa, WP veritabanına gitsin ama dönüşte biz onu yakalayıp pakete ekleyelim
        add_filter("option_{$option}", function($value) use ($option) {
            self::$wp_bulk_options[$option] = $value;
            return $value;
        });

        return $pre;
    }

    /**
     * ACF Veriyi Formatladığı Anda Yakalar ve Cache'e Atar.
     * Bu sayede standart get_field() çağrıları da otomatik cache'lenir.
     */

    private static function preload_wp_options() {
        // Paketi çek
        $bulk_package = get_option(self::PREFIX . 'wp_options_bulk');
        
        if ($bulk_package && is_array($bulk_package)) {
            self::$wp_bulk_options = $bulk_package;
            
            foreach (self::$wp_bulk_options as $key => $val) {
                // Priority 1: Herkesten önce biz cevap veriyoruz
                add_filter("pre_option_{$key}", function($pre) use ($val) { 
                    return ($val === '__QC_NF__') ? false : $val; 
                }, 1);
            }
        }
    }

    public static function save_option_manifest() {
        if (empty(self::$wp_bulk_options) || is_admin()) return;

        $current_package = get_option(self::PREFIX . 'wp_options_bulk', []);
        $updated_package = array_merge($current_package, self::$wp_bulk_options);
        
        // Sadece değişiklik varsa tek SQL ile güncelle
        if ($current_package !== $updated_package) {
            update_option(self::PREFIX . 'wp_options_bulk', $updated_package, false);
        }
    }

    public static function wrap($key, $callback, $deps = [], $expiration = null) {
        if (self::$cache === false) {
            error_log(" [QCACHE] {$key} | Cache Kapalı, Direkt Çalışıyor.");
            return $callback();
        }

        $expiration = $expiration ?? self::$ttl;
        $cache_key = self::PREFIX . 'manual_' . $key;

        // 1. RAM Kontrolü
        if (isset(self::$runtime_transient_cache[$cache_key])) {
            error_log(" [QCACHE] {$key} | RAM'den Okundu (Runtime).");
            return self::$runtime_transient_cache[$cache_key];
        }

        // 2. Veritabanı (Transient) Kontrolü
        $cached_data = get_transient($cache_key);
        if ($cached_data !== false) {
            error_log(" [QCACHE] {$key} | Veritabanından (Transient) Okundu.");
            self::$runtime_transient_cache[$cache_key] = $cached_data; // RAM'e de atalım
            return $cached_data;
        }

        // 3. Lock mekanizması (Lazy Pilot)
        if (self::$lazy_pilot && get_transient(self::LOCK_KEY . $key)) {
            error_log(" [QCACHE] {$key} | KİLİTLİ (Lazy Pilot Aktif), Callback Çalışıyor.");
            return $callback();
        }
        
        if (self::$lazy_pilot) set_transient(self::LOCK_KEY . $key, true, 30);

        // 4. Veriyi Oluştur ve Kaydet
        error_log(" [QCACHE] {$key} | CACHEDE YOK! Yeniden Oluşturulup Kaydediliyor...");
        $data = $callback();

        if ($data !== false) {
            set_transient($cache_key, $data, $expiration);
            self::$runtime_transient_cache[$cache_key] = $data;

            if (!empty($deps)) {
                $manifest_deps = [];
                foreach ($deps as $type => $values) {
                    foreach ((array)$values as $val) $manifest_deps[] = "{$type}_{$val}";
                }
                self::register_dependency($cache_key, $manifest_deps);
            }
        }

        if (self::$lazy_pilot) delete_transient(self::LOCK_KEY . $key);
        return $data;
    }

    // --- 🍔 MENU CACHE ---
    public static function get_menu_cache($output, $args) {
        $key = self::PREFIX . 'menu_' . md5(serialize($args));
        return self::$runtime_transient_cache[$key] ?? get_transient($key);
    }

    public static function set_menu_cache($items, $args) {
        $key = self::PREFIX . 'menu_' . md5(serialize($args));
        
        if (!get_transient($key)) {
            // 🎯 Menü item'larını kaydetmeden önce içindeki ACF verilerini formatlayıp içine gömüyoruz
            foreach ($items as &$item) {
                // Menü öğesindeki tüm ACF alanlarını al ve objeye ekle
                $fields = get_fields($item->ID);
                if ($fields) {
                    $item->acf = $fields; // Veriyi item içine gömdük
                }
            }
            
            set_transient($key, $items, self::DEFAULT_TTL);
            self::$runtime_transient_cache[$key] = $items;
        }
        return $items;
    }

    // --- 🛡️ LAZY & LOCK ENGINE (DÜZELTİLDİ) ---
    public static function auto_pilot_posts($posts, $query) {
        if (self::$is_processing || is_admin() || $posts !== null) return $posts;
        $cache_key = self::PREFIX . 'posts_' . md5(serialize($query->query_vars));
        
        // 1. RAM'de varsa dön
        if (isset(self::$runtime_transient_cache[$cache_key])) return self::$runtime_transient_cache[$cache_key];

        // 2. Kilit varsa: VERİYİ ÇEK AMA CACHE YAZMA (Postlar uçmasın diye)
        if (self::$lazy_pilot && get_transient(self::LOCK_KEY)) {
            self::$is_processing = true;
            remove_filter('posts_pre_query', [__CLASS__, 'auto_pilot_posts'], 10);
            $results = $query->get_posts();
            add_filter('posts_pre_query', [__CLASS__, 'auto_pilot_posts'], 10, 2);
            self::$is_processing = false;
            return $results;
        }

        // 3. Normal akış: Kilidi bas ve cache'e yaz
        self::$is_processing = true;
        set_transient(self::LOCK_KEY, '1', 30); 

        remove_filter('posts_pre_query', [__CLASS__, 'auto_pilot_posts'], 10);
        $results = $query->get_posts();
        add_filter('posts_pre_query', [__CLASS__, 'auto_pilot_posts'], 10, 2);
        
        if (!empty($results)) {
            set_transient($cache_key, $results, self::DEFAULT_TTL);
            self::$runtime_transient_cache[$cache_key] = $results;
            self::register_dependency($cache_key, ["post_type_" . (is_array($query->get('post_type')) ? implode('_', $query->get('post_type')) : ($query->get('post_type') ?: 'post'))]);
        }
        
        delete_transient(self::LOCK_KEY);
        self::$is_processing = false;
        return $results;
    }

    public static function auto_pilot_terms($query) {
        if (is_admin()) return;
        $cache_key = self::PREFIX . 'term_' . md5(serialize($query->query_vars));
        if (isset(self::$runtime_transient_cache[$cache_key])) {
            $query->terms = self::$runtime_transient_cache[$cache_key];
            return;
        }
        // Kilit varsa sessizce çık, WP normal devam etsin
        if (self::$lazy_pilot && get_transient(self::LOCK_KEY)) return;

        add_filter('get_terms', function($terms) use ($cache_key) {
            if (!get_transient(self::LOCK_KEY) && !empty($terms)) {
                set_transient($cache_key, $terms, self::DEFAULT_TTL);
                self::$runtime_transient_cache[$cache_key] = $terms;
            }
            return $terms;
        });
    }


    // --- 🧠 MANIFEST & FLUSH ---
    private static function register_dependency($cache_key, $deps) {
        foreach ((array)$deps as $dep) {
            // Eğer $dep bir array olarak geldiyse (iç içe dizi durumu)
            if (is_array($dep)) {
                foreach ($dep as $d) {
                    if (!in_array($cache_key, self::$runtime_manifest['global'][$d] ?? [])) {
                        self::$runtime_manifest['global'][$d][] = $cache_key;
                    }
                }
            } else {
                // Normal string durumu (örn: "post_type_magazalar")
                if (!in_array($cache_key, self::$runtime_manifest['global'][$dep] ?? [])) {
                    self::$runtime_manifest['global'][$dep][] = $cache_key;
                }
            }
        }
    }
    private static function register_field_manifest($target_id, $cache_key) {
        self::$runtime_manifest['fields'][$target_id][] = $cache_key;
    }
    public static function save_runtime_manifest() {
        if (empty(self::$runtime_manifest['global']) && empty(self::$runtime_manifest['fields'])) return;
        if (get_transient('qcache_manifest_lock')) return;
        set_transient('qcache_manifest_lock', '1', 15);
        if (!empty(self::$runtime_manifest['global'])) {
            $manifest = get_option(self::MANIFEST_KEY, []);
            foreach (self::$runtime_manifest['global'] as $dep => $keys) {
                $manifest[$dep] = array_unique(array_merge($manifest[$dep] ?? [], $keys));
            }
            update_option(self::MANIFEST_KEY, $manifest, false);
        }
        if (!empty(self::$runtime_manifest['fields'])) {
            $f_manifest = get_option(self::FIELD_MANIFEST_KEY, []);
            foreach (self::$runtime_manifest['fields'] as $id => $keys) {
                $f_manifest[$id] = array_unique(array_merge($f_manifest[$id] ?? [], $keys));
            }
            update_option(self::FIELD_MANIFEST_KEY, $f_manifest, false);
        }
        delete_transient('qcache_manifest_lock');
    }

    /**
     * Bir terim (category, tag, taxonomy) değiştiğinde veya silindiğinde
     * o taksonomiye ve o terime bağlı tüm cache'leri patlatır.
     */
    public static function handle_term_change($term_id, $tt_id, $taxonomy) {
        // 1. Terimin kendisine ve taksonomisine ait manifest anahtarları
        $to_flush = ["term_{$term_id}", "taxonomy_{$taxonomy}"];
        
        // 2. Eğer Polylang varsa, tüm dillerdeki karşılıklarını da bulup listeye ekle
        if (function_exists('pll_get_term_translations')) {
            $translations = pll_get_term_translations($term_id);
            foreach ($translations as $lang => $t_id) {
                $to_flush[] = "term_{$t_id}";
            }
        }

        self::flush_by_manifest($to_flush);
    }

    public static function handle_post_change($post_id) {
        if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) return;

        // 🎯 1. ADIM: Bulk Paketini Temizle
        // Bizim yeni isimlendirmemiz: self::PREFIX . $type . '_' . $id . '_bulk'
        $post_type = get_post_type($post_id);
        $bulk_key = self::PREFIX . 'post_' . $post_id . '_bulk';
        delete_transient($bulk_key);

        // 🎯 2. ADIM: Eski sistemden kalan veya tekil tutulan cache'leri temizle (Garanticilik)
        global $wpdb;
        $search = self::PREFIX . 'post_' . $post_id . '_%'; // Hem bulk hem tekil her şeyi kapsar
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", 
            "_transient_{$search}", 
            "_transient_timeout_{$search}"
        ));

        // 🎯 3. ADIM: İlişkili Sorguları (Manifest) Temizle
        $to_flush = ["post_id_{$post_id}", "post_type_{$post_type}"];
        $taxs = get_object_taxonomies($post_type);
        foreach ($taxs as $tax) {
            $terms = wp_get_post_terms($post_id, $tax, ['fields' => 'ids']);
            if(!is_wp_error($terms)) {
                foreach ($terms as $tid) {
                    $to_flush[] = "term_{$tid}";
                }
            }
        }
        self::flush_by_manifest($to_flush);
    }

    private static function flush_by_manifest($deps) {
        $manifest = get_option(self::MANIFEST_KEY, []);
        $f_manifest = get_option(self::FIELD_MANIFEST_KEY, []);

        error_log(print_r($manifest, true));
        
        // Log Başlangıcı: Hangi bağımlılıklar için temizlik tetiklendi?
        error_log(" [QCACHE] PURGE tetiklendi! Bağımlılıklar: " . implode(', ', (array)$deps));

        $deleted_keys_count = 0;

        foreach ($deps as $dep) {
            // 1. Global Manifest Kontrolü (Post tipi, taksonomi vb.)
            if (isset($manifest[$dep])) {
                foreach ($manifest[$dep] as $key) {
                    if (delete_transient($key)) {
                        error_log(" [QCACHE] SİLİNDİ (Global): {$key} | Sebep: {$dep}");
                        $deleted_keys_count++;
                    }
                }
                unset($manifest[$dep]);
            }
            
            // 2. Field Manifest Kontrolü (Spesifik ID bazlı ACF alanları vb.)
            if (isset($f_manifest[$dep])) {
                foreach ($f_manifest[$dep] as $key) {
                    if (delete_transient($key)) {
                        error_log(" [QCACHE] SİLİNDİ (Field): {$key} | Sebep: {$dep}");
                        $deleted_keys_count++;
                    }
                }
                unset($f_manifest[$dep]);
            }
        }

        if ($deleted_keys_count > 0) {
            update_option(self::MANIFEST_KEY, $manifest, false);
            update_option(self::FIELD_MANIFEST_KEY, $f_manifest, false);
            error_log(" [QCACHE] PURGE Bitti. Toplam {$deleted_keys_count} anahtar temizlendi.");
        } else {
            error_log(" [QCACHE] PURGE Bitti. Silinecek eşleşen anahtar bulunamadı.");
        }
    }



    // --- ALIASES  --- //
    /**
     * WP_Query Alias - Arşiv, Paging ve Taksonomi Garantili
     */
    public static function wp_query($args) {
        // Benzersiz anahtar oluştur (Tüm paging ve sıralama parametrelerini kapsar)
        $cache_key = 'query_' . md5(serialize($args));
        
        // Bağımlılıkları tespit et
        $deps = [];
        
        // 1. Post Type Bağımlılığı
        $pt = $args['post_type'] ?? 'post';
        if (is_array($pt)) {
            foreach($pt as $p) $deps['post_type'][] = $p;
        } else {
            $deps['post_type'][] = $pt;
        }

        // 2. Taksonomi Bağımlılığı (Eğer tax_query varsa ilgili termleri de bağla)
        if (!empty($args['tax_query'])) {
            foreach ($args['tax_query'] as $tax_item) {
                if (isset($tax_item['taxonomy']) && isset($tax_item['terms'])) {
                    $terms = (array)$tax_item['terms'];
                    foreach ($terms as $t) $deps['term'][] = $t;
                }
            }
        }

        return self::wrap($cache_key, function() use ($args) {
            return new WP_Query($args);
        }, $deps);
    }

    /**
     * get_posts Alias
     */
    public static function get_posts($args) {
        $args['suppress_filters'] = false; // Cache kancalarının çalışması için zorunludur
        $cache_key = 'posts_' . md5(serialize($args));
        
        $pt = $args['post_type'] ?? 'post';
        return self::wrap($cache_key, function() use ($args) {
            return get_posts($args);
        }, [
            'post_type' => (array)$pt
           ]
       );
    }

    /**
     * get_post Alias - Tekil Post & Revizyon Korumalı
     */
    public static function get_post($post_id) {
        if (!$post_id) return null;
        $post_id = (int)$post_id;
        
        return self::wrap('single_post_' . $post_id, function() use ($post_id) {
            return get_post($post_id);
        }, [
            'post_id' => $post_id, 
            'post_type' => get_post_type($post_id)
           ]
        );
    }

    /**
     * get_terms Alias - Taksonomi Değişim Garantili
     */
    public static function get_terms($args) {
        $cache_key = 'terms_' . md5(serialize($args));
        $tax = $args['taxonomy'] ?? 'category';
        
        return self::wrap($cache_key, function() use ($args) {
            return get_terms($args);
        }, [
            'taxonomy' => (array)$tax
           ]
        );
    }

    /**
     * get_term Alias
     */
    public static function get_term($term_id, $taxonomy = '') {
        $term_id = (int)$term_id;
        $cache_key = 'single_term_' . $term_id;
        
        return self::wrap($cache_key, function() use ($term_id, $taxonomy) {
            return get_term($term_id, $taxonomy);
        }, [
            'term' => $term_id
           ]
       );
    }

    /**
     * 1. UNIVERSAL IDENTIFIER - Nesne Tipini ve ID'sini Çözer
     */
    /**
     * 1. RESOLVE_ACF_TYPE - Menü elemanlarını tekil paketlemekten vazgeçiriyoruz
     */
    private static function resolve_acf_type($post_id) {
        if ($post_id === 'options' || $post_id === 'option' || strpos($post_id, 'options_') === 0) {
            return ['type' => 'opt', 'id' => 'global'];
        }

        $type = 'post';
        $identifier = $post_id;

        if (is_string($post_id)) {
            if (strpos($post_id, 'user_') === 0) $type = 'user';
            elseif (strpos($post_id, 'term_') === 0) $type = 'term';
            elseif (strpos($post_id, 'comment_') === 0) $type = 'comm';
        } elseif (is_numeric($post_id)) {
            // 🎯 KRİTİK DEĞİŞİKLİK: Menü elemanlarını 'post' olarak değil 'menu_item' olarak işaretle
            // Ama onlara özel bulk transient OLUŞTURMA (save_acf_after_format içinde engelleyeceğiz)
            if (get_post_type($post_id) === 'nav_menu_item') {
                $type = 'menu_item'; 
            }
        }
        return ['type' => $type, 'id' => $identifier];
    }



    /**
     * 2. GET_FIELD - Artık sadece sarmalayıcı (wrapper) görevinde
     */
    /**
     * 1. ÖZEL GET_FIELD - Sadece bu çağrıldığında paketten veri döner
     */
    public static function get_field_v1($selector, $post_id = null) {
        $post_id = $post_id ?: get_the_ID();

        $resolved = self::resolve_acf_type($post_id);
        $clean_selector = preg_replace('/_[a-z]{2}_[A-Z]{2}$/', '', $selector);

        if (!did_action('acf/init')) {
            $raw_meta = ($resolved['type'] === 'opt') 
                        ? get_option('options_' . $clean_selector) 
                        : get_post_meta($post_id, $clean_selector, true);
            return $raw_meta;
        }

        if (self::$cache === false || (is_admin() && !self::$enable_admin)) {
            return get_field($selector, $post_id);
        }

        $bulk_key = ($resolved['type'] === 'opt') 
                    ? self::ACF_BULK_OPTION_KEY 
                    : self::PREFIX . $resolved['type'] . '_' . $resolved['id'] . '_bulk';

        if (!isset(self::$runtime_transient_cache[$bulk_key])) {
            self::$runtime_transient_cache[$bulk_key] = ($resolved['type'] === 'opt') 
                                                        ? get_option($bulk_key) 
                                                        : get_transient($bulk_key);
        }
        $bundle = self::$runtime_transient_cache[$bulk_key];

        // 🎯 1. PAKETTE VAR MI?
        if (is_array($bundle) && isset($bundle[$clean_selector])) {
            $val = $bundle[$clean_selector];
            
            // 🔥 BURASI KRİTİK: Damga kontrolü
            if ($val === '__QC_NF__') return false; 
            
            return $val;
        }

        // 🎯 2. ALT ALAN MI? (Deep Search mantığı)
        // Senin kodundaki bu kısmı biz 'deep_search' fonksiyonuna emanet etmiştik hatırla.
        // Eğer manuel yaptıysan şuraya da eklemelisin:
        $search_result = self::deep_search($clean_selector, $bundle);
        if ($search_result !== null) {
            if ($search_result === '__QC_NF__') return false; // 🔥 Burada da kontrol şart
            return $search_result;
        }

        self::$allowed_selectors[$post_id . '_' . $clean_selector] = true;
        return get_field($selector, $post_id);
    }

    public static function get_field_v2($selector, $post_id = null) {
        $post_id = $post_id ?: get_the_ID();
        $resolved = self::resolve_acf_type($post_id);
        $clean_selector = preg_replace('/_[a-z]{2}_[A-Z]{2}$/', '', $selector);

        // 1. ADIM: Cache paketini yükle
        $bulk_key = ($resolved['type'] === 'opt') 
                    ? self::ACF_BULK_OPTION_KEY 
                    : self::PREFIX . $resolved['type'] . '_' . $resolved['id'] . '_bulk';

        if (!isset(self::$runtime_transient_cache[$bulk_key])) {
            $data = ($resolved['type'] === 'opt') ? get_option($bulk_key) : get_transient($bulk_key);
            self::$runtime_transient_cache[$bulk_key] = is_array($data) ? $data : [];
        }
        
        $bundle = &self::$runtime_transient_cache[$bulk_key]; // Referans ile bağlandık

        // 2. ADIM: Pakette varsa direkt ver (Deep Search ile)
        $cached_val = self::deep_search($clean_selector, $bundle);
        if ($cached_val !== null) {
            return ($cached_val === '__QC_NF__') ? false : $cached_val;
        }

        // 3. ADIM: Pakette YOKSA, gerçek get_field ile temiz veriyi al
        // self::$is_processing kilidini açıyoruz ki sonsuz döngüye girmesin
        self::$is_processing = true;
        $value = get_field($selector, $post_id);
        self::$is_processing = false;

        // 4. ADIM: Alınan temiz veriyi o anda pakete ekle (Update Et)
        $final_store_value = ($value === null || $value === false || $value === '') ? '__QC_NF__' : $value;
        $bundle[$clean_selector] = $final_store_value;

        // Paketi kalıcı olarak kaydet (İstersen bunu sayfa sonunda tek seferde de yapabilirsin)
        if ($resolved['type'] === 'opt') {
            update_option($bulk_key, $bundle, false);
        } else {
            set_transient($bulk_key, $bundle, DAY_IN_SECONDS);
        }

        return $value;
    }

    public static function get_field($selector, $post_id = null) {
        if(!function_exists("get_field")){
            return;
        }
        $post_id = $post_id ?: get_the_ID();
        $resolved = self::resolve_acf_type($post_id);
        $clean_selector = preg_replace('/_[a-z]{2}_[A-Z]{2}$/', '', $selector);

        // Bulk Paket Key'i (Options ise her dilde aynı anahtarı kullansın diye TR yapıyoruz)
        $bulk_key = ($resolved['type'] === 'opt') 
                    ? self::ACF_BULK_OPTION_KEY 
                    : self::PREFIX . $resolved['type'] . '_' . $resolved['id'] . '_bulk';

        // 1. RAM'de veya Cache'de varsa ver (Dil fark etmeksizin aynı paketi kullanır)
        if (!isset(self::$runtime_transient_cache[$bulk_key])) {
            $data = ($resolved['type'] === 'opt') ? get_option($bulk_key) : get_transient($bulk_key);
            self::$runtime_transient_cache[$bulk_key] = is_array($data) ? $data : [];
        }
        
        if (array_key_exists($clean_selector, self::$runtime_transient_cache[$bulk_key])) {
            return self::$runtime_transient_cache[$bulk_key][$clean_selector];
        }

        // 2. YOKSA: Varsayılan dili zorla ve gerçek değeri çek
        $default_lang = function_exists('pll_default_language') ? pll_default_language() : 'tr';
        $force_lang = function() use ($default_lang) { return $default_lang; };
        
        add_filter('acf/settings/current_language', $force_lang, 999);
        
        self::$is_processing = true;
        $value = get_field($selector, $post_id);
        self::$is_processing = false;
        
        remove_filter('acf/settings/current_language', $force_lang, 999);

        // 3. Değeri pakete ekle (Boş string, false, null fark etmez, olduğu gibi)
        self::$runtime_transient_cache[$bulk_key][$clean_selector] = $value;
        
        return $value;
    }
    public static function save_acf_bulk_at_end() {
        if (empty(self::$runtime_transient_cache)) return;

        foreach (self::$runtime_transient_cache as $key => $bundle) {
            if (!is_array($bundle)) continue;

            if ($key === self::ACF_BULK_OPTION_KEY) {
                update_option($key, $bundle, 'no');
            } else {
                set_transient($key, $bundle, self::$ttl);
            }
        }
    }


   /**
     * 1. GET_ACF_BULK_CACHE - Veriyi Paketten Çıkartan Fedai
     * ACF daha veritabanına gitmeden "Dur, bende hazır paket var" dediğimiz yer.
     */
    public static function get_acf_bulk_cache_v1($value, $post_id, $field) {
        if (is_admin() || self::$is_processing) return $value;

        $selector = $field['name'];
        $clean_selector = preg_replace('/_[a-z]{2}_[A-Z]{2}$/', '', $selector);

        // 🎯 1. ADIM: ID'YI ÇÖZ (Post mu, Option mı?)
        $resolved = self::resolve_acf_type($post_id);
        
        // Options için özel anahtar kullanıyoruz (qcache_options_bulk)
        $bulk_key = ($resolved['type'] === 'opt') 
                    ? self::ACF_BULK_OPTION_KEY 
                    : self::PREFIX . $resolved['type'] . '_' . $resolved['id'] . '_bulk';

        // 🎯 2. ADIM: PAKETİ RAM'E VEYA TRANSIENT'A SOR
        if (!isset(self::$runtime_transient_cache[$bulk_key])) {
            self::$runtime_transient_cache[$bulk_key] = ($resolved['type'] === 'opt') 
                                                        ? get_option($bulk_key) 
                                                        : get_transient($bulk_key);
        }

        $bundle = self::$runtime_transient_cache[$bulk_key];

        // get_acf_bulk_cache fonksiyonu içinde paketten veri dönen yer:
        if (is_array($bundle) && isset($bundle[$clean_selector])) {
            $val = $bundle[$clean_selector];
            // 🎯 Eğer işaretçiyi görürsek ACF'e false dönüyoruz (yani sorgu atma, bu alan boş)
            return ($val === '__QC_NF__') ? false : $val;
        }

        // Alt alan kontrolü (Örn: options_slider_0_image için options_slider'a bak)
        $parent_key = explode('_', $clean_selector)[0];
        if ($parent_key !== $clean_selector && isset($bundle[$parent_key])) {
            $sub_key = str_replace($parent_key . '_', '', $clean_selector);
            if (is_array($bundle[$parent_key]) && isset($bundle[$parent_key][$sub_key])) {
                return $bundle[$parent_key][$sub_key];
            }
        }

        return $value;
    }

    /**
     * 1. GET_ACF_BULK_CACHE - Veriyi Paketten Çıkartan Fedai
     */
    public static function get_acf_bulk_cache($value, $post_id, $field) {
        if (is_admin() || self::$is_processing) return $value;
        if (!did_action('acf/init')) return $value;

        $selector = $field['name'];
        $clean_selector = preg_replace('/_[a-z]{2}_[A-Z]{2}$/', '', $selector);

        $resolved = self::resolve_acf_type($post_id);
        $bulk_key = ($resolved['type'] === 'opt') 
                    ? self::ACF_BULK_OPTION_KEY 
                    : self::PREFIX . $resolved['type'] . '_' . $resolved['id'] . '_bulk';

        if (!isset(self::$runtime_transient_cache[$bulk_key])) {
            self::$runtime_transient_cache[$bulk_key] = ($resolved['type'] === 'opt') 
                ? get_option($bulk_key, []) 
                : (get_transient($bulk_key) ?: []);
        }

        $bundle = self::$runtime_transient_cache[$bulk_key];
        if (!is_array($bundle)) return $value;

        // 1. ADIM: Derin veya Düz Arama Yap
        $search_result = self::deep_search($clean_selector, $bundle);

        // 2. ADIM: Sonucu Kontrol Et ve Temizle
        if ($search_result !== null) {
            // 🎯 BURASI EN ÖNEMLİ YER: 
            // İster deep_search'ten gelsin ister direkt, 
            // eğer değer bizim "Yokluk Damgası" ise mutlaka PHP'nin anlayacağı FALSE değerine dönüştür.
            if ($search_result === '__QC_NF__') {
                return false; 
            }
            
            return $search_result;
        }

        return $value;
    }

    private static function deep_search($selector, $haystack) {
        // 1. Yol: Direkt anahtar varsa (Tam eşleşme)
        if (isset($haystack[$selector])) {
            return $haystack[$selector];
        }

        // 2. Yol: Parçalı arama (Hiyerarşik veya ACF ham index yapısı)
        $parts = explode('_', $selector);
        $current = $haystack;

        foreach ($parts as $part) {
            if (is_array($current)) {
                if (isset($current[$part])) {
                    // Standart array erişimi (örn: ['contact']['accounts'])
                    $current = $current[$part];
                } elseif (is_numeric($part) && isset($current[(int)$part])) {
                    // Sayısal index erişimi (örn: repeater içindeki 0. index)
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

    public static function save_acf_after_format($value, $post_id, $field) {
        // is_admin ve is_processing kontrolleri kalsın
        if (is_admin() || self::$is_processing) return $value;

        $selector = $field['name'];
        $clean_selector = preg_replace('/_[a-z]{2}_[A-Z]{2}$/', '', $selector);

        // Menü item ve alt alan engelleri kalsın...
        if (preg_match('/_[0-9]+_/', $selector)) return $value;
        if (strpos($selector, 'contact_') === 0 && $selector !== 'contact') return $value;

        $resolved = self::resolve_acf_type($post_id);
        $bulk_key = ($resolved['type'] === 'opt') 
                    ? self::ACF_BULK_OPTION_KEY 
                    : self::PREFIX . $resolved['type'] . '_' . $resolved['id'] . '_bulk';

        // Paketi yükle
        if (!isset(self::$runtime_transient_cache[$bulk_key])) {
            self::$runtime_transient_cache[$bulk_key] = ($resolved['type'] === 'opt') 
                ? get_option($bulk_key, []) 
                : (get_transient($bulk_key) ?: []); // 🎯 Burayı paranteze aldık
        }
                
        $bundle = self::$runtime_transient_cache[$bulk_key];
        if (!is_array($bundle)) $bundle = [];

        // 🎯 KRİTİK DEĞİŞİKLİK: Değer yoksa bile 'boş' olarak işaretle
        // false, null, '', 0 gibi değerlerin hepsi artık pakete girecek
        $final_store_value = ($value === null || $value === false || $value === '') ? '__QC_NF__' : $value;

        // Eğer değer değişmişse veya pakette hiç yoksa kaydet
        if (!isset($bundle[$clean_selector]) || $bundle[$clean_selector] !== $final_store_value) {
            $bundle[$clean_selector] = $final_store_value;
            self::$runtime_transient_cache[$bulk_key] = $bundle;

            if ($resolved['type'] === 'opt') {
                update_option($bulk_key, $bundle, 'no');
            } else {
                set_transient($bulk_key, $bundle, self::$ttl);
            }
        }

        return $value;
    }

    public static function rebuild_options_bulk() {
        delete_option(self::ACF_BULK_OPTION_KEY);
        self::$bulk_options = null;
    }


    // --- 🌐 PLL & OPTIONS ---
    public static function bypass_pll_sql($clauses, $taxonomies, $args) {
        if (is_admin()) return $clauses;
        $pll_taxs = ['language', 'post_translations', 'term_language', 'term_translations'];
        if (empty(array_intersect((array)$taxonomies, $pll_taxs))) return $clauses;
        $key = md5(serialize($taxonomies) . serialize($args));
        if (isset(self::$pll_static_storage[$key])) { $clauses['where'] .= " AND 1=0"; }
        return $clauses;
    }
    public static function set_pll_static_cache($terms, $taxonomies, $args) {
        if (is_admin()) return $terms;
        $key = md5(serialize($taxonomies) . serialize($args));
        if (!empty($terms)) self::$pll_static_storage[$key] = $terms;
        elseif (isset(self::$pll_static_storage[$key])) return self::$pll_static_storage[$key];
        return $terms;
    }
    private static function get_option_bulk() {
        if (self::$bulk_options !== null) return self::$bulk_options;

        $cached_bulk = get_option(self::ACF_BULK_OPTION_KEY);
        
        if ($cached_bulk !== false && is_array($cached_bulk)) {
            // EĞER BURAYA GİRİYORSA CACHE ÇALIŞIYORDUR
            // Ekranda görmek istersen şu satırı aç:
            // echo "<script>console.log('QCACHE: Veri RAM/Cache üzerinden geldi.');</script>";
            self::$bulk_options = $cached_bulk;
            return self::$bulk_options;
        }

        if (function_exists('get_fields')) {
            self::$is_processing = true;
            //remove_filter('acf/pre_load_value', [__CLASS__, 'get_acf_bulk_cache'], 10);
            
            $data = get_fields('option');
            
            // --- EKRANA BASMA (DİREKT BROWSER'DA GÖRÜRSÜN) ---
            if (!empty($data)) {
                $size = strlen(serialize($data));
                $kb = round($size / 1024, 2);
                // Bu kod sayfanın en üstünde siyah bir kutu açar
                add_action('wp_head', function() use ($kb) {
                    echo "<div style='position:fixed; top:0; left:0; background:red; color:white; padding:10px; z-index:999999;'>QCACHE: Veri Oluşturuldu ve Yazıldı! Boyut: {$kb} KB</div>";
                });
                
                update_option(self::ACF_BULK_OPTION_KEY, $data, 'no');
            }
            
           // add_filter('acf/pre_load_value', [__CLASS__, 'get_acf_bulk_cache'], 10, 3);
            self::$is_processing = false;
            self::$bulk_options = $data;
        }

        return is_array(self::$bulk_options) ? self::$bulk_options : [];
    }
    // Bu metod sadece manuel çağrılar için kalsın
    public static function get_option($key) {
        $bulk = self::get_option_bulk();
        return $bulk[$key] ?? null;
    }
    /**
     * PURGE ALL - Tüm Cache Sistemini Sıfırlar
     * Hem transientları hem de özel paketlenmiş optionları temizler.
     */
    public static function purge_all($type = '') {
        global $wpdb;
        
        // 1. Transientları temizle (prefix ile başlayanlar)
        $search = self::PREFIX . ($type ? $type . '_' : '');
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", 
            "_transient_{$search}%", 
            "_transient_timeout_{$search}%"
        ));

        // 2. Eğer genel temizlik yapılıyorsa (type boşsa) paketleri de uçur
        if (empty($type)) {
            // Ana manifestleri sil
            delete_option(self::MANIFEST_KEY); 
            delete_option(self::FIELD_MANIFEST_KEY);
            
            // ACF Bulk Option paketini sil
            delete_option(self::ACF_BULK_OPTION_KEY); 
            
            // 🎯 SENDE EKSİK OLAN KISIM: WP Options Bulk paketini sil
            // preload_wp_options içinde 'qcache_wp_options_bulk' olarak kullandığın anahtarı siler
            delete_option(self::PREFIX . 'wp_options_bulk'); 
            
            // Varsa lock/kilit anahtarını sil
            delete_transient(self::LOCK_KEY);
            
            // Runtime RAM cache'lerini de boşaltalım
            self::$bulk_options = null;
            self::$wp_bulk_options = [];
            self::$runtime_transient_cache = [];
        }

        // 3. Varsa Object Cache'i (Redis/Memcached) tetikle
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }
    /**
     * Belirli bir option güncellendiğinde sadece o option'ın cache'ini siler.
     * Böylece tüm cache'i patlatıp siteyi yormayız.
     */
    public static function purge_specific_option_cache($option) {
        // Bizim dinamik cache anahtarını oluştur
        $cache_key = self::PREFIX . 'dyn_opt_' . $option;
        
        // Transient'ı sil
        delete_transient($cache_key);
        
        // RAM'deki veriyi de temizle
        if (isset(self::$wp_bulk_options[$option])) {
            unset(self::$wp_bulk_options[$option]);
        }
    }
}

    QueryCache::init([
        'cache'  => true,//"auto",//"auto", // false yap her şey dursun, 'manual' yap sadece wrap'ler çalışsın
        'config' => [
            'acf_meta'   => true,
            'wp_query'   => false,
            'menus'      => true,
            'pll'        => false,
            "wp_options" => false
        ]
    ]);

/*
 * QUERYCACHE ENGINE v4.0 - CONFIGURATION & USAGE GUIDE
QueryCache::init([
    // ANA ÇALIŞMA MODU
    // false    : Tüm sistemi kapatır.
    // true     : Sistemi açar (Varsayılan 'auto' gibi davranır).
    // 'auto'   : Hem senin wrap'lerini hem de WP'nin kendi sorgularını (Query, Menu, ACF) otomatik cache'ler.
    // 'manual' : WP'nin kendi işine karışmaz, sadece senin bizzat QueryCache::wrap veya get_post ile yazdıklarını tutar.
    'cache' => 'auto', 

    // GLOBAL CACHE ÖMRÜ (Saniye cinsinden)
    // Manuel süre belirtilmeyen tüm cache'ler bu süreyi baz alır.
    // Örn: 3600 (1 Saat), 86400 (1 Gün), 30 * DAY_IN_SECONDS (1 Ay)
    'ttl' => 30 * DAY_IN_SECONDS,

    // ADMIN PANELİ KORUMASI
    // true  : Admin panelinde de cache aktif olur (Tehlikelidir, değişiklikleri anında göremeyebilirsin).
    // false : Admin panelinde sistem devre dışı kalır (Önerilen).
    'enable_admin' => false,

    // LAZY PILOT (Cache Stampede Protection)
    // true  : Aynı anda 1000 kişi gelirse, veritabanına sadece 1 kişiyi gönderir, diğerlerini bekletir.
    // false : Herkesi veritabanına saldırttırır (Sunucuyu yorar).
    'lazy_pilot' => true,

    // OTOMATİK MOD ÖZELLEŞTİRMELERİ
    // 'cache' => 'auto' iken hangi kancaların (hook) çalışacağını belirler.
    'config' => [
        // ACF BULK META OPTİMİZASYONU
        // true  : Bir postun bir field'ını isteyince tüm metası tek SQL ile RAM'e alınır.
        // false : ACF standart yavaşlığında çalışmaya devam eder.
        'acf_meta' => true,

        // WP NATIVE QUERY CACHE
        // true  : WP_Query, get_posts ve get_terms sorgularını otomatik yakalar.
        // false : Standart WP sorguları cache'lenmez.
        'wp_query' => true,

        // MENÜ CACHE
        // true  : wp_nav_menu() çıktılarını ve objelerini saklar.
        // false : Menüler her sayfa yenilendiğinde tekrar oluşturulur.
        'menus' => true,

        // POLYLANG BYPASS
        // true  : Polylang'ın ağır SQL sorgularını statik RAM'e alır.
        // false : Polylang her zamanki gibi veritabanına git-gel yapar.
        'pll' => false,
    ]
]);
*/