<?php
/**
 * ============================================================
 *  QueryCache Engine v10.0 — "Smart Manifest Edition"
 * ============================================================
 *
 * KULLANIM:
 *   QueryCache::get_field('baslik', 123)
 *   QueryCache::get_field('menu_populate', 'options')
 *   QueryCache::get_posts([...])
 *   QueryCache::get_post(123)
 *   QueryCache::get_terms([...])
 *   QueryCache::get_term(5, 'category')
 *   QueryCache::get_option('site_name')
 *   QueryCache::wrap('ozel_key', fn() => expensive_query())
 *   QueryCache::wrap('ozel_key', fn() => ..., ['post_type' => 'product'])
 *
 *   Timber::get_posts(), Timber::get_term() → otomatik (config timber:true ise)
 *   wp_nav_menu()                           → otomatik (config menu:true ise)
 *
 * KURALLAR:
 *   - Master switch false → tüm cache silinir, native WP'ye düşer.
 *   - Her tür bağımsız switch. Kapatılırsa o türün cache'i silinir.
 *   - ACF get_field ve WP get_option AYRI bulk'larda saklanır (veri tipi çakışması yok).
 *   - Admin panelinde çalışmaz (enable_admin:true ile açılabilir).
 *
 * v10.0 YENİLİKLER:
 *   - Smart Manifest sistemi: post/term/taxonomy bazlı seçici invalidation.
 *     Bir ürün güncellendiğinde artık SADECE ürün cache'leri silinir,
 *     blog/menü/diğer post type'lar dokunulmaz.
 *   - get_posts() ve get_terms() artık bağımlılık (dependency) kaydeder.
 *   - wrap() artık isteğe bağlı $deps parametresi alır (post_type, taxonomy vs).
 *   - on_term_change() artık get_posts cache'lerini de temizler (taxonomy bazlı).
 *   - Menü invalidation hook'u eklendi (wp_update_nav_menu).
 *   - wp_is_post_revision() koruması eklendi.
 *   - Runtime cache limiti (500) ile RAM koruması eklendi.
 *   - Manifest DB'ye sadece değişiklik varsa yazılır (hash kontrolü).
 *   - save_runtime_manifest() içindeki error_log çağrıları production'da kapalı.
 *   - status() manifest bilgisini de raporlar.
 * ============================================================
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class QueryCache {

    // =========================================================================
    // SABİTLER
    // =========================================================================

    const VERSION     = '10.0';
    const PREFIX      = 'qcache_';
    const DEFAULT_TTL = 30 * DAY_IN_SECONDS;
    const NOT_FOUND   = '__QC_NF__';

    /**
     * Runtime'da biriken cache girişi üst limiti.
     * Aşılırsa en eski %60'ı atılır — RAM patlamasına karşı koruma.
     */
    const RUNTIME_LIMIT = 500;

    /**
     * Smart Manifest'in saklandığı WP option key'i.
     * Format: [ 'post_type:product' => ['qcache_posts_abc', ...], ... ]
     */
    const MANIFEST_KEY = 'qcache_manifest';

    /**
     * ACF get_field("x", "options") sonuçları.
     * Format_value geçmiş — obje/array döner. Ham DB değeri DEĞİL.
     */
    const ACF_OPTIONS_BULK_KEY = 'qcache_acf_options_bulk';

    /**
     * QueryCache::get_option("options_x") sonuçları.
     * Ham WP option değeri — format_value YOK.
     * ACF_OPTIONS_BULK_KEY ile AYRI: aynı key farklı tipte veri döndürür.
     */
    const WP_OPTIONS_BULK_KEY = 'qcache_wp_options_bulk';

    /**
     * Eski kod için backward compat alias.
     * @deprecated v9.0 — ACF_OPTIONS_BULK_KEY kullan.
     */
    const ACF_BULK_OPTION_KEY = 'qcache_acf_options_bulk';

    /**
     * Her cache türünün transient prefix'i (toptan purge için).
     */
    const TYPE_PREFIXES = [
        'get_field'  => 'field_bulk_',
        'get_posts'  => 'posts_',
        'get_post'   => 'post_single_',
        'get_terms'  => 'terms_',
        'get_term'   => 'term_',
        'menu'       => 'menu_',
        'wp_options' => 'wp_opt_',
        'wrap'       => 'wrap_',
        'timber'     => 'timber_',
    ];

    // =========================================================================
    // DURUM
    // =========================================================================

    public static $initiated    = false;
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
        'menu'       => true,
        'timber'     => true,
        'debug'      => false, // true yapılırsa error_log aktif olur
    ];

    /** RAM cache — aynı request içinde DB'ye ikinci kez gitmez */
    protected static $runtime_cache  = [];

    /** Shutdown'da "değişti mi?" kontrolü için başlangıç hash'leri */
    protected static $initial_hashes = [];

    /** ACF / recursion koruması */
    private static $is_processing = false;

    /** Shutdown yazımı sırasında hook tetiklenmesini engeller */
    private static $is_saving = false;

    /**
     * Bu request'te purge edilen türleri tutar.
     * Her request'te static $config default true'dan başladığı için
     * "açıktan kapalıya geçiş" hatalı algılanmaması için kullanılır.
     */
    private static $purged_types = [];

    /**
     * Bu request'te oluşan yeni bağımlılıklar (shutdown'da manifest'e eklenir).
     * Format: [ 'post_type:product' => ['qcache_posts_abc', ...] ]
     */
    private static $pending_deps = [];

    /**
     * Bu request'te yüklenen manifest (flush_by_manifest için DB'ye tek kez gidilir).
     */
    private static $manifest_cache = null;


    // =========================================================================
    // INIT
    // =========================================================================

    /**
     * @param array $args {
     *     @type bool   $cache         Master switch. false → her şeyi durdur.
     *     @type int    $ttl           Saniye cinsinden TTL.
     *     @type bool   $enable_admin  Admin panelinde çalışsın mı.
     *     @type array  $config        Tür bazlı switch'ler.
     * }
     */
    public static function init( array $args = [] ): void {

        if ( self::$initiated ) return;

        if ( isset( $args['enable_admin'] ) ) {
            self::$enable_admin = (bool) $args['enable_admin'];
        }

        // Admin kontrolü — master switch'ten önce
        // Not: invalidation hook'ları admin'de de çalışmalı, bu yüzden
        // admin koruması sadece cache READ/WRITE'ı kapatır, hook'ları kapatmaz.
        if ( is_admin() && ! self::$enable_admin ) {
            self::$cache     = false;
            self::$initiated = true;
            // Admin'de de invalidation kancalarını bağla
            self::_register_invalidation_hooks( get_called_class() );
            return;
        }

        // Master switch
        if ( isset( $args['cache'] ) ) {
            self::$cache = (bool) $args['cache'];
        }

        // Master switch kapalı → DB'yi temizle (sadece gerekirse), native'e düş
        if ( ! self::$cache ) {
            self::_maybe_clear_all();
            self::$initiated = true;
            return;
        }

        // TTL
        if ( isset( $args['ttl'] ) ) {
            self::$ttl = (int) $args['ttl'];
        }

        // Alt switch'ler
        if ( isset( $args['config'] ) && is_array( $args['config'] ) ) {
            foreach ( $args['config'] as $type => $enabled ) {
                if ( ! array_key_exists( $type, self::$config ) ) continue;
                self::$config[ $type ] = (bool) $enabled;

                if ( ! self::$config[ $type ] && ! in_array( $type, self::$purged_types, true ) ) {
                    if ( self::_has_cached_data( $type ) ) {
                        self::purge_type( $type );
                    }
                    self::$purged_types[] = $type;
                }
            }
        }

        $class = get_called_class();

        // Invalidation kancaları
        self::_register_invalidation_hooks( $class );

        // Timber köprüsü
        if ( self::$config['timber'] && class_exists( 'Timber\Timber' ) ) {
            add_filter( 'timber/post/collection', [ $class, 'timber_bridge' ], 10, 2 );
            add_filter( 'timber/term/collection', [ $class, 'timber_bridge' ], 10, 2 );
            add_filter( 'timber/post/instance',   [ $class, 'timber_bridge' ], 10, 2 );
            add_filter( 'timber/term/instance',   [ $class, 'timber_bridge' ], 10, 2 );
        }

        // Menü kancaları
        if ( self::$config['menu'] ) {
            add_filter( 'pre_wp_nav_menu', [ $class, 'get_menu_cache' ], 10, 2 );
            add_filter( 'wp_nav_menu',     [ $class, 'set_menu_cache' ], 10, 2 );
        }

        // Shutdown: yeni verileri DB'ye yaz + manifest'i güncelle
        add_action( 'shutdown', [ $class, 'save_runtime_manifest' ], 999 );

        self::$initiated = true;
    }

    /**
     * Invalidation hook'larını kaydeder.
     * Admin'de de çalışması gerektiği için init'ten ayrı metoda alındı.
     */
    private static function _register_invalidation_hooks( string $class ): void {
        add_action( 'updated_option', [ $class, 'on_option_change' ], 10, 1 );
        add_action( 'added_option',   [ $class, 'on_option_change' ], 10, 1 );
        add_action( 'deleted_option', [ $class, 'on_option_change' ], 10, 1 );

        add_action( 'save_post',   [ $class, 'on_post_change' ], 10, 1 );
        add_action( 'delete_post', [ $class, 'on_post_change' ], 10, 1 );
        add_action( 'transition_post_status', static function ( $new, $old, $post ) use ( $class ) {
            if ( $new === 'publish' || $old === 'publish' ) {
                $class::on_post_change( $post->ID );
            }
        }, 10, 3 );

        // Term hook'ları — tam imza (term_id, tt_id, taxonomy) ile
        add_action( 'created_term', [ $class, 'on_term_change' ], 99, 3 );
        add_action( 'edited_term',  [ $class, 'on_term_change' ], 99, 3 );
        add_action( 'delete_term',  [ $class, 'on_term_change' ], 99, 3 );

        // Menü değişikliği invalidation
        add_action( 'wp_update_nav_menu', [ $class, 'on_menu_change' ] );
    }


    // =========================================================================
    // ACF GET_FIELD
    // =========================================================================

    /**
     * ACF get_field() cache'li versiyonu.
     *
     * options post_id → ACF_OPTIONS_BULK_KEY (format_value geçmiş — obje/array)
     * sayısal post_id → field_bulk_{id}      (miss'te ACF yazar, shutdown'da DB'ye)
     */
    public static function get_field( string $selector, $post_id = null, bool $format = true ) {
        if ( ! self::$cache || ! self::$config['get_field'] || self::$is_processing ) {
            return function_exists( 'get_field' ) ? get_field( $selector, $post_id, $format ) : null;
        }
        if ( ! function_exists( 'get_field' ) ) return null;

        $post_id  = $post_id ?: get_the_ID();
        $resolved = self::_resolve_acf_target( $post_id );

        // WPML/Polylang dil suffix temizliği (_tr_TR gibi)
        $clean = preg_replace( '/_[a-z]{2}_[A-Z]{2}$/', '', $selector );

        if ( $resolved['type'] === 'opt' ) {
            $bulk_key  = self::ACF_OPTIONS_BULK_KEY;
            $check_key = ( strpos( $clean, 'options_' ) !== 0 ) ? 'options_' . $clean : $clean;
        } else {
            $bulk_key  = self::PREFIX . 'field_bulk_' . $resolved['id'];
            $check_key = $clean;
        }

        self::_ensure_field_bulk_loaded( $bulk_key, $resolved );

        if ( array_key_exists( $check_key, self::$runtime_cache[ $bulk_key ] ) ) {
            $val = self::$runtime_cache[ $bulk_key ][ $check_key ];
            return ( $val === self::NOT_FOUND ) ? false : $val;
        }

        // Miss → ACF'den çek (format_value dahil), bulk'a yaz
        self::$is_processing = true;
        $value = get_field( $selector, $post_id, $format );
        self::$is_processing = false;

        self::$runtime_cache[ $bulk_key ][ $check_key ] = ( $value === null || $value === false )
            ? self::NOT_FOUND : $value;

        self::_manage_runtime_limit();

        return $value;
    }


    // =========================================================================
    // WP GET_OPTION
    // =========================================================================

    /**
     * WP get_option() cache'li versiyonu.
     * ACF bulk'undan TAMAMEN AYRI — ham DB değeri döner.
     */
    public static function get_option( string $option, $default = false ) {
        if (
            ! self::$cache
            || ! self::$config['wp_options']
            || $option === self::ACF_OPTIONS_BULK_KEY
            || $option === self::WP_OPTIONS_BULK_KEY
        ) {
            return get_option( $option, $default );
        }

        self::_ensure_wp_options_bulk_loaded();

        if ( array_key_exists( $option, self::$runtime_cache[ self::WP_OPTIONS_BULK_KEY ] ) ) {
            $val = self::$runtime_cache[ self::WP_OPTIONS_BULK_KEY ][ $option ];
            return ( $val === self::NOT_FOUND ) ? $default : $val;
        }

        $value = get_option( $option, $default );
        self::$runtime_cache[ self::WP_OPTIONS_BULK_KEY ][ $option ] = ( $value === null || $value === false )
            ? self::NOT_FOUND : $value;

        return $value;
    }


    // =========================================================================
    // WP GET_POSTS
    // =========================================================================

    /**
     * get_posts() cache'li versiyonu.
     * post_type bilgisini manifest'e kaydeder → seçici invalidation mümkün olur.
     */
    public static function get_posts( array $args ): array {
        if ( ! self::$cache || ! self::$config['get_posts'] ) {
            return get_posts( $args ) ?: [];
        }

        $cache_key = 'posts_' . md5( serialize( $args ) );

        // Manifest için post_type bağımlılığını hazırla
        $post_type = $args['post_type'] ?? 'post';
        $deps = [ 'post_type' => (array) $post_type ];

        // tax_query varsa taxonomy bağımlılığı da ekle
        if ( ! empty( $args['tax_query'] ) && is_array( $args['tax_query'] ) ) {
            foreach ( $args['tax_query'] as $tq ) {
                if ( isset( $tq['taxonomy'] ) ) {
                    $deps['taxonomy'][] = $tq['taxonomy'];
                }
            }
        }

        $result = self::_read_or_fetch( $cache_key, fn() => get_posts( $args ) ?: [], $deps );
        return is_array( $result ) ? $result : [];
    }


    // =========================================================================
    // WP GET_POST
    // =========================================================================

    /**
     * get_post() cache'li versiyonu.
     * post_id bazlı manifest bağımlılığı kaydeder.
     */
    public static function get_post( $post_id ): ?WP_Post {
        if ( ! self::$cache || ! self::$config['get_post'] ) {
            return get_post( $post_id ) ?: null;
        }

        $id   = ( $post_id instanceof WP_Post ) ? $post_id->ID : (int) $post_id;
        $deps = [ 'post_id' => [ $id ] ];

        $result = self::_read_or_fetch(
            'post_single_' . $id,
            fn() => get_post( $post_id ),
            $deps
        );
        return ( $result instanceof WP_Post ) ? $result : null;
    }


    // =========================================================================
    // WP GET_TERMS
    // =========================================================================

    /**
     * get_terms() cache'li versiyonu.
     * taxonomy bilgisini manifest'e kaydeder → seçici invalidation mümkün olur.
     */
    public static function get_terms( array $args ) {
        if ( ! self::$cache || ! self::$config['get_terms'] ) {
            return get_terms( $args );
        }

        $cache_key = 'terms_' . md5( serialize( $args ) );
        $taxonomy  = isset( $args['taxonomy'] ) ? (array) $args['taxonomy'] : [];
        $deps      = ! empty( $taxonomy ) ? [ 'taxonomy' => $taxonomy ] : [];

        $result = self::_read_or_fetch( $cache_key, fn() => get_terms( $args ), $deps );
        return is_wp_error( $result ) ? [] : ( $result ?? [] );
    }


    // =========================================================================
    // WP GET_TERM
    // =========================================================================

    /**
     * get_term() cache'li versiyonu.
     * term_id ve taxonomy bağımlılığı kaydeder.
     */
    public static function get_term( $term_id, string $taxonomy = '' ) {
        if ( ! self::$cache || ! self::$config['get_term'] ) {
            return get_term( $term_id, $taxonomy );
        }

        $id   = ( $term_id instanceof WP_Term ) ? $term_id->term_id : (int) $term_id;
        $deps = [ 'term_id' => [ $id ] ];
        if ( $taxonomy ) {
            $deps['taxonomy'][] = $taxonomy;
        }

        return self::_read_or_fetch(
            'term_' . $id,
            fn() => get_term( $term_id, $taxonomy ),
            $deps
        );
    }


    // =========================================================================
    // WRAP
    // =========================================================================

    /**
     * Herhangi bir callback'i cache'e alır.
     *
     * @param string   $key      Benzersiz cache anahtarı.
     * @param callable $callback Çalıştırılacak fonksiyon.
     * @param array    $deps     Bağımlılıklar. Manifest'e kaydedilir.
     *                           Örnekler:
     *                             ['post_type' => ['product']]
     *                             ['taxonomy'  => ['category', 'tag']]
     *                             ['term_id'   => [42]]
     *
     * Kullanım:
     *   QueryCache::wrap('kampanyalar', fn() => get_posts([...]), ['post_type' => ['campaign']]);
     */
    public static function wrap( string $key, callable $callback, array $deps = [] ) {
        if ( ! self::$cache || ! self::$config['wrap'] ) {
            return $callback();
        }
        return self::_read_or_fetch( 'wrap_' . $key, $callback, $deps );
    }


    // =========================================================================
    // MENÜ CACHE
    // =========================================================================

    private static function _make_menu_key( object $args ): string {
        $menu_id = $args->theme_location
            ?? ( is_object( $args->menu ?? null ) ? ( $args->menu->slug ?? 'default' ) : ( $args->menu ?? 'default' ) )
            ?? 'default';
        $lang = function_exists( 'pll_current_language' ) ? pll_current_language() : get_locale();
        return md5( $menu_id . '_' . $lang );
    }

    /**
     * pre_wp_nav_menu — cache'te varsa WP render etmez.
     */
    public static function get_menu_cache( ?string $output, object $args ): ?string {
        if ( ! self::$cache || ! self::$config['menu'] || is_admin() ) {
            return $output;
        }

        $full_key = self::PREFIX . 'menu_html_' . self::_make_menu_key( $args );

        if ( isset( self::$runtime_cache[ $full_key ] ) ) {
            return self::$runtime_cache[ $full_key ];
        }

        $cached_html = get_transient( $full_key );
        if ( $cached_html !== false ) {
            self::$runtime_cache[ $full_key ] = $cached_html;
            return $cached_html;
        }

        return null; // Cache yoksa WP render etsin
    }

    /**
     * wp_nav_menu — render sonrası cache'e yazar.
     */
    public static function set_menu_cache( string $output, object $args ): string {
        if ( ! self::$cache || ! self::$config['menu'] || is_admin() || empty( $output ) ) {
            return $output;
        }

        $full_key = self::PREFIX . 'menu_html_' . self::_make_menu_key( $args );

        if ( ! isset( self::$runtime_cache[ $full_key ] ) ) {
            self::$runtime_cache[ $full_key ] = $output;
            set_transient( $full_key, $output, self::$ttl );
        }

        return $output;
    }


    // =========================================================================
    // TIMBER KÖPRÜSÜ
    // =========================================================================

    /**
     * Timber post/term filter'larına bağlanır.
     *
     * Timber veriyi çektikten SONRA bu filter tetiklenir.
     * Bu request'te DB sorgusu zaten yapılmıştır.
     * Bu metot sonucu cache'e alır → bir sonraki request DB'ye gitmez.
     * Ayrıca post listesi için ACF bulk preload yapar.
     */
    public static function timber_bridge( $data, $query_or_args ) {
        if ( ! self::$cache || ! self::$config['timber'] ) return $data;

        $is_taxonomy = isset( $query_or_args['taxonomy'] )
            || ( is_object( $data ) && property_exists( $data, 'taxonomy' ) );

        if ( is_array( $data ) ) {
            if ( $is_taxonomy  && ! self::$config['get_terms'] ) return $data;
            if ( ! $is_taxonomy && ! self::$config['get_posts'] ) return $data;
        } else {
            if ( $is_taxonomy  && ! self::$config['get_term'] ) return $data;
            if ( ! $is_taxonomy && ! self::$config['get_post'] ) return $data;
        }

        $full_key = self::PREFIX . 'timber_'
            . md5( serialize( $query_or_args ) . ( $is_taxonomy ? '_tax' : '_post' ) );

        // Manifest bağımlılıkları — timber için de post_type/taxonomy kaydet
        $deps = [];
        if ( is_array( $query_or_args ) ) {
            if ( isset( $query_or_args['post_type'] ) ) {
                $deps['post_type'] = (array) $query_or_args['post_type'];
            }
            if ( isset( $query_or_args['taxonomy'] ) ) {
                $deps['taxonomy'] = (array) $query_or_args['taxonomy'];
            }
        }

        // RAM'de varsa dön
        if ( array_key_exists( $full_key, self::$runtime_cache ) ) {
            $cached = self::$runtime_cache[ $full_key ];
            if ( $cached !== self::NOT_FOUND ) {
                self::_preload_acf_for_timber( $cached, $is_taxonomy );
                return $cached;
            }
            return $data;
        }

        // Transient'ta varsa dön
        $transient = get_transient( $full_key );
        if ( $transient !== false && $transient !== self::NOT_FOUND ) {
            self::$runtime_cache[ $full_key ]  = $transient;
            self::$initial_hashes[ $full_key ] = md5( serialize( $transient ) );
            if ( ! empty( $deps ) ) {
                self::_register_dep( $full_key, $deps );
            }
            self::_preload_acf_for_timber( $transient, $is_taxonomy );
            return $transient;
        }

        // Miss → gelen $data'yı cache'e al
        $to_store = ( $data === null || $data === false ) ? self::NOT_FOUND : $data;
        self::$runtime_cache[ $full_key ] = $to_store;

        if ( ! empty( $deps ) ) {
            self::_register_dep( $full_key, $deps );
        }

        if ( $to_store !== self::NOT_FOUND && self::$config['get_field'] ) {
            self::_preload_acf_for_timber( $data, $is_taxonomy );
        }

        return $data;
    }


    // =========================================================================
    // SHUTDOWN — kayıt ve manifest güncelleme
    // =========================================================================

    public static function save_runtime_manifest(): void {
        if ( ! self::$cache || self::$is_saving ) return;

        self::$is_saving = true;

        // --- 1. Runtime cache'i DB'ye yaz ---
        $written = $skipped = [];

        foreach ( self::$runtime_cache as $key => $data ) {
            $new_hash = md5( serialize( $data ) );

            if ( isset( self::$initial_hashes[ $key ] ) && self::$initial_hashes[ $key ] === $new_hash ) {
                $skipped[] = $key;
                continue;
            }

            $to_save = ( $data === null || $data === false || $data === '' ) ? self::NOT_FOUND : $data;

            if ( self::_is_bulk_key( $key ) ) {
                update_option( $key, $data, 'no' );
                $written[] = '[BULK] ' . $key;
            } else {
                set_transient( $key, $to_save, self::$ttl );
                $written[] = '[TRANSIENT] ' . $key;
            }
        }

        // --- 2. Manifest'i güncelle (sadece bu request'te yeni dep geldiyse) ---
        if ( ! empty( self::$pending_deps ) ) {
            $manifest  = self::_load_manifest();
            $old_hash  = md5( serialize( $manifest ) );

            foreach ( self::$pending_deps as $dep_key => $cache_keys ) {
                if ( ! isset( $manifest[ $dep_key ] ) ) {
                    $manifest[ $dep_key ] = [];
                }
                $manifest[ $dep_key ] = array_values(
                    array_unique( array_merge( $manifest[ $dep_key ], $cache_keys ) )
                );
            }

            // Manifest büyümesini önlemek için artık var olmayan transient key'leri temizle
            // (Her 100 request'te bir çalışır — her seferinde DB sorgusunu engeller)
            if ( mt_rand( 1, 100 ) === 1 ) {
                $manifest = self::_cleanup_stale_manifest( $manifest );
            }

            if ( $old_hash !== md5( serialize( $manifest ) ) ) {
                update_option( self::MANIFEST_KEY, $manifest, false );
            }
        }

        if ( ! empty( self::$config['debug'] ) ) {
            error_log( '[QueryCache v10] written: ' . implode( ', ', $written ) );
            error_log( '[QueryCache v10] skipped: ' . implode( ', ', $skipped ) );
            error_log( '[QueryCache v10] pending_deps: ' . implode( ', ', array_keys( self::$pending_deps ) ) );
        }

        self::$is_saving = false;
    }


    // =========================================================================
    // INVALIDATION
    // =========================================================================

    /**
     * WP option değiştiğinde tetiklenir.
     * options_ ile başlayan key'ler → ACF + WP options bulk'larını sil.
     */
    public static function on_option_change( string $option ): void {
        if ( self::$is_saving ) return;
        if (
            strpos( $option, 'options_' ) === 0
            || $option === self::ACF_OPTIONS_BULK_KEY
            || $option === self::WP_OPTIONS_BULK_KEY
        ) {
            self::_invalidate_options_bulks();
        }
    }

    /**
     * Post kaydedildiğinde / silindiğinde / statüsü değiştiğinde tetiklenir.
     *
     * v10 farkı: post_type bazlı manifest flush → sadece ilgili listeler silinir.
     */
    public static function on_post_change( $post_id ): void {
        if ( self::$is_saving || ! $post_id ) return;

        $post_id = (int) $post_id;

        // Revision'ları atla
        if ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $post_id ) ) return;

        // O post'un ACF field bulk'unu sil
        $field_bulk = self::PREFIX . 'field_bulk_' . $post_id;
        delete_option( $field_bulk );
        unset( self::$runtime_cache[ $field_bulk ], self::$initial_hashes[ $field_bulk ] );

        // O post'un tekil cache'ini sil
        $single = self::PREFIX . 'post_single_' . $post_id;
        delete_transient( $single );
        unset( self::$runtime_cache[ $single ], self::$initial_hashes[ $single ] );

        // Manifest üzerinden seçici flush
        $post_type = get_post_type( $post_id ) ?: '';
        $flush_deps = [ 'post_id:' . $post_id ];
        if ( $post_type ) {
            $flush_deps[] = 'post_type:' . $post_type;
        }
        self::_flush_by_manifest( $flush_deps );

        // Manifest'te kaydı olmayan eski cache'ler için güvenlik ağı:
        // post_type cache'i manifest'te yoksa (ilk kez güncelleme) toptan sil.
        // Manifest'te varsa seçici silme zaten yapıldı, toptan silmeye gerek yok.
        if ( $post_type && ! self::_manifest_has( 'post_type:' . $post_type ) ) {
            self::purge_type( 'get_posts' );
        }

        // Timber listelerini de temizle
        self::purge_type( 'timber' );
    }

    /**
     * Term oluşturulduğunda / düzenlendiğinde / silindiğinde tetiklenir.
     *
     * v10 farkı:
     *  - taxonomy bazlı manifest flush (hem get_terms hem get_posts etkilenebilir)
     *  - get_posts cache'leri de temizlenir (eski versiyonda eksik olan)
     *
     * @param int    $term_id
     * @param int    $tt_id     (taxonomy term id — kullanılmıyor ama WP imzası gereği alınıyor)
     * @param string $taxonomy
     */
    public static function on_term_change( $term_id, $tt_id = 0, string $taxonomy = '' ): void {
        if ( self::$is_saving ) return;

        $term_id = (int) $term_id;

        // O term'in tekil cache'ini sil
        $term_key = self::PREFIX . 'term_' . $term_id;
        delete_transient( $term_key );
        unset( self::$runtime_cache[ $term_key ], self::$initial_hashes[ $term_key ] );

        // Manifest üzerinden seçici flush
        $flush_deps = [ 'term_id:' . $term_id ];
        if ( $taxonomy ) {
            $flush_deps[] = 'taxonomy:' . $taxonomy;
        }
        self::_flush_by_manifest( $flush_deps );

        // Manifest'te bu taxonomy yoksa güvenlik ağı olarak toptan sil
        if ( $taxonomy && ! self::_manifest_has( 'taxonomy:' . $taxonomy ) ) {
            self::purge_type( 'get_terms' );
            self::purge_type( 'get_posts' ); // taxonomy ile çekilen ürün listelerini de temizle
        }

        self::purge_type( 'timber' );
    }

    /**
     * Menü güncellendiğinde tüm menü cache'lerini temizler.
     */
    public static function on_menu_change(): void {
        self::purge_type( 'menu' );
    }


    // =========================================================================
    // PURGE
    // =========================================================================

    /**
     * Belirli bir türün tüm cache'lerini siler.
     */
    public static function purge_type( string $type ): void {
        if ( $type === 'get_field' ) {
            delete_option( self::ACF_OPTIONS_BULK_KEY );
            unset(
                self::$runtime_cache[ self::ACF_OPTIONS_BULK_KEY ],
                self::$initial_hashes[ self::ACF_OPTIONS_BULK_KEY ]
            );
            self::_purge_db_prefix( 'field_bulk_' );
            self::_purge_runtime_prefix( self::PREFIX . 'field_bulk_' );
            return;
        }
        if ( $type === 'wp_options' ) {
            delete_option( self::WP_OPTIONS_BULK_KEY );
            unset(
                self::$runtime_cache[ self::WP_OPTIONS_BULK_KEY ],
                self::$initial_hashes[ self::WP_OPTIONS_BULK_KEY ]
            );
            return;
        }
        $prefix = self::TYPE_PREFIXES[ $type ] ?? null;
        if ( $prefix === null ) return;
        self::_purge_db_prefix( $prefix );
        self::_purge_runtime_prefix( self::PREFIX . $prefix );
    }

    /**
     * Tüm QueryCache verilerini siler.
     */
    public static function clear_all_cache(): void {
        global $wpdb;
        $p = self::PREFIX;

        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            '_transient_' . $p . '%',
            '_transient_timeout_' . $p . '%'
        ) );

        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->options}
             WHERE option_name = %s OR option_name = %s
                OR option_name = %s OR option_name LIKE %s",
            self::ACF_OPTIONS_BULK_KEY,
            self::WP_OPTIONS_BULK_KEY,
            self::MANIFEST_KEY,
            $p . '%'
        ) );

        self::$runtime_cache   = [];
        self::$initial_hashes  = [];
        self::$pending_deps    = [];
        self::$manifest_cache  = null;

        if ( function_exists( 'wp_cache_flush' ) ) {
            wp_cache_flush();
        }
    }


    // =========================================================================
    // SMART MANIFEST — PRIVATE
    // =========================================================================

    /**
     * Manifest'i yükler (bu request'te sadece bir kez DB'ye gider).
     */
    private static function _load_manifest(): array {
        if ( self::$manifest_cache === null ) {
            self::$manifest_cache = get_option( self::MANIFEST_KEY, [] );
            if ( ! is_array( self::$manifest_cache ) ) {
                self::$manifest_cache = [];
            }
        }
        return self::$manifest_cache;
    }

    /**
     * Verilen dep_key'in manifest'te kaydı var mı?
     */
    private static function _manifest_has( string $dep_key ): bool {
        $manifest = self::_load_manifest();
        return ! empty( $manifest[ $dep_key ] );
    }

    /**
     * Bir cache_key için bağımlılıkları pending_deps'e kaydeder.
     * Shutdown'da manifest'e yazılır.
     *
     * @param string $full_cache_key  Tam cache key (prefix dahil)
     * @param array  $deps            ['post_type' => ['product'], 'taxonomy' => ['category']]
     */
    private static function _register_dep( string $full_cache_key, array $deps ): void {
        foreach ( $deps as $type => $values ) {
            foreach ( (array) $values as $val ) {
                $dep_key = $type . ':' . $val;
                if ( ! isset( self::$pending_deps[ $dep_key ] ) ) {
                    self::$pending_deps[ $dep_key ] = [];
                }
                if ( ! in_array( $full_cache_key, self::$pending_deps[ $dep_key ], true ) ) {
                    self::$pending_deps[ $dep_key ][] = $full_cache_key;
                }
            }
        }
    }

    /**
     * Verilen dep anahtarlarına bağlı tüm cache'leri siler.
     *
     * @param string[] $dep_keys  Örn: ['post_type:product', 'post_id:123']
     */
    private static function _flush_by_manifest( array $dep_keys ): void {
        $manifest = self::_load_manifest();
        $changed  = false;

        foreach ( $dep_keys as $dep_key ) {
            if ( empty( $manifest[ $dep_key ] ) ) continue;

            foreach ( $manifest[ $dep_key ] as $cache_key ) {
                // RAM'den sil
                unset( self::$runtime_cache[ $cache_key ], self::$initial_hashes[ $cache_key ] );
                // DB'den sil (transient veya option olabilir)
                delete_transient( $cache_key );
                delete_option( $cache_key );
            }

            unset( $manifest[ $dep_key ] );
            $changed = true;
        }

        if ( $changed ) {
            self::$manifest_cache = $manifest;
            update_option( self::MANIFEST_KEY, $manifest, false );
        }
    }

    /**
     * Manifest'teki artık var olmayan transient key'leri temizler.
     * save_runtime_manifest içinde %1 ihtimalle çalışır.
     */
    private static function _cleanup_stale_manifest( array $manifest ): array {
        foreach ( $manifest as $dep_key => $cache_keys ) {
            $valid = [];
            foreach ( $cache_keys as $cache_key ) {
                // Transient var mı? (DB'ye sorgu — ama bu zaten %1 ihtimalle çalışır)
                if ( get_transient( $cache_key ) !== false || get_option( $cache_key ) !== false ) {
                    $valid[] = $cache_key;
                }
            }
            if ( empty( $valid ) ) {
                unset( $manifest[ $dep_key ] );
            } else {
                $manifest[ $dep_key ] = $valid;
            }
        }
        return $manifest;
    }


    // =========================================================================
    // PRIVATE YARDIMCILAR
    // =========================================================================

    /**
     * Cache oku / yoksa callback çağır.
     * Deps verilirse manifest'e kaydeder.
     */
    private static function _read_or_fetch( string $key, callable $callback, array $deps = [] ) {
        if ( self::$is_processing ) return $callback();

        $full_key = self::PREFIX . $key;

        // 1. RAM'de var mı?
        if ( array_key_exists( $full_key, self::$runtime_cache ) ) {
            $cached = self::$runtime_cache[ $full_key ];
            return ( $cached === self::NOT_FOUND ) ? null : $cached;
        }

        // 2. Transient'ta var mı?
        $transient = get_transient( $full_key );
        if ( $transient !== false ) {
            $value = ( $transient === self::NOT_FOUND ) ? null : $transient;
            self::$runtime_cache[ $full_key ]  = $transient;
            self::$initial_hashes[ $full_key ] = md5( serialize( $transient ) );
            // Transient var ama pending_deps'e ekle —
            // manifest'te bu dep henüz kayıtlı olmayabilir (ilk deploy vs.)
            if ( ! empty( $deps ) ) {
                self::_register_dep( $full_key, $deps );
            }
            return $value;
        }

        // 3. Miss → DB'den çek
        self::$is_processing = true;
        $data = $callback();
        self::$is_processing = false;

        $to_store = ( $data === null || $data === false || $data === '' ) ? self::NOT_FOUND : $data;
        self::$runtime_cache[ $full_key ] = $to_store;

        // Bağımlılığı kaydet
        if ( ! empty( $deps ) ) {
            self::_register_dep( $full_key, $deps );
        }

        self::_manage_runtime_limit();

        return $data;
    }

    /**
     * ACF field bulk'unu RAM'e yükler.
     */
    private static function _ensure_field_bulk_loaded( string $bulk_key, array $resolved ): void {
        if ( array_key_exists( $bulk_key, self::$runtime_cache ) ) return;

        $stored = get_option( $bulk_key, null );

        if ( $stored !== null && is_array( $stored ) ) {
            self::$runtime_cache[ $bulk_key ]  = $stored;
            self::$initial_hashes[ $bulk_key ] = md5( serialize( $stored ) );
            return;
        }

        // DB'de yok → boş başla.
        // Ham meta PRELOAD ETME — ACF repeater/relation/group gibi field'larda
        // get_post_meta() ham sayı/ID döndürür, ACF'nin format_value'su işlenmez.
        self::$runtime_cache[ $bulk_key ] = [];
        // initial_hash YOK → shutdown'da yazılacak
    }

    /**
     * WP options bulk'unu RAM'e yükler.
     */
    private static function _ensure_wp_options_bulk_loaded(): void {
        if ( array_key_exists( self::WP_OPTIONS_BULK_KEY, self::$runtime_cache ) ) return;

        $stored = get_option( self::WP_OPTIONS_BULK_KEY, null );

        if ( $stored !== null && is_array( $stored ) ) {
            self::$runtime_cache[ self::WP_OPTIONS_BULK_KEY ]  = $stored;
            self::$initial_hashes[ self::WP_OPTIONS_BULK_KEY ] = md5( serialize( $stored ) );
            return;
        }

        // DB'de yok → tek SQL ile çek
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'options\_%'",
            ARRAY_A
        );
        $fresh = [];
        foreach ( $rows as $row ) {
            $fresh[ $row['option_name'] ] = maybe_unserialize( $row['option_value'] );
        }

        self::$runtime_cache[ self::WP_OPTIONS_BULK_KEY ] = $fresh;
        // initial_hash YOK → shutdown'da yazılır
    }

    /**
     * Timber post/term listesi için ACF field'larını toplu preload eder.
     * Template'de {{ post.field }} çağrıları RAM'den gelir.
     */
    private static function _preload_acf_for_timber( $data, bool $is_taxonomy ): void {
        if ( ! function_exists( 'get_fields' ) || self::$is_processing ) return;

        $items = is_array( $data ) ? $data : [ $data ];

        foreach ( $items as $item ) {
            if ( ! is_object( $item ) ) continue;

            if ( $is_taxonomy ) {
                $id       = $item->id ?? $item->term_id ?? null;
                if ( ! $id ) continue;
                $bulk_key = self::PREFIX . 'field_bulk_term_' . $id;
                $acf_id   = 'term_' . $id;
            } else {
                $id       = $item->ID ?? $item->id ?? null;
                if ( ! $id ) continue;
                $bulk_key = self::PREFIX . 'field_bulk_' . $id;
                $acf_id   = $id;
            }

            if ( array_key_exists( $bulk_key, self::$runtime_cache ) ) continue;

            $stored = get_option( $bulk_key, null );
            if ( is_array( $stored ) ) {
                self::$runtime_cache[ $bulk_key ]  = $stored;
                self::$initial_hashes[ $bulk_key ] = md5( serialize( $stored ) );
                continue;
            }

            self::$is_processing = true;
            $fields = get_fields( $acf_id );
            self::$is_processing = false;

            self::$runtime_cache[ $bulk_key ] = is_array( $fields ) ? $fields : [];
            // initial_hash YOK → shutdown'da yazılacak
        }
    }

    /**
     * Bulk key kontrolü (shutdown'da option mu transient mi yazılacak kararı).
     */
    private static function _is_bulk_key( string $key ): bool {
        return $key === self::ACF_OPTIONS_BULK_KEY
            || $key === self::WP_OPTIONS_BULK_KEY
            || strpos( $key, self::PREFIX . 'field_bulk_' ) === 0;
    }

    /**
     * ACF target tipini çözümler.
     */
    private static function _resolve_acf_target( $post_id ): array {
        if (
            is_string( $post_id ) && (
                in_array( $post_id, [ 'options', 'option' ], true )
                || strpos( $post_id, 'options_' ) === 0
            )
        ) {
            return [ 'type' => 'opt', 'id' => 'global' ];
        }
        return [ 'type' => 'post', 'id' => (int) $post_id ?: 'global' ];
    }

    /**
     * ACF ve WP options bulk'larını hem DB'den hem RAM'den siler.
     */
    private static function _invalidate_options_bulks(): void {
        delete_option( self::ACF_OPTIONS_BULK_KEY );
        delete_option( self::WP_OPTIONS_BULK_KEY );
        unset(
            self::$runtime_cache[ self::ACF_OPTIONS_BULK_KEY ],
            self::$initial_hashes[ self::ACF_OPTIONS_BULK_KEY ],
            self::$runtime_cache[ self::WP_OPTIONS_BULK_KEY ],
            self::$initial_hashes[ self::WP_OPTIONS_BULK_KEY ]
        );
    }

    /**
     * Master switch kapalıysa ve DB'de hala veri varsa temizler.
     * Her request'te DELETE sorgusu atmamak için önce varlık kontrolü yapar.
     */
    private static function _maybe_clear_all(): void {
        global $wpdb;
        $has = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->options}
             WHERE option_name LIKE %s OR option_name = %s OR option_name = %s
             LIMIT 1",
            '_transient_' . self::PREFIX . '%',
            self::ACF_OPTIONS_BULK_KEY,
            self::WP_OPTIONS_BULK_KEY
        ) );
        if ( $has ) {
            self::clear_all_cache();
        }
    }

    /**
     * Belirli prefix ile başlayan tüm transient'ları DB'den siler.
     */
    private static function _purge_db_prefix( string $search ): void {
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            '_transient_' . self::PREFIX . $search . '%',
            '_transient_timeout_' . self::PREFIX . $search . '%'
        ) );
    }

    /**
     * Belirli prefix ile başlayan tüm girişleri RAM'den siler.
     */
    private static function _purge_runtime_prefix( string $prefix ): void {
        foreach ( array_keys( self::$runtime_cache ) as $key ) {
            if ( strpos( $key, $prefix ) === 0 ) {
                unset( self::$runtime_cache[ $key ], self::$initial_hashes[ $key ] );
            }
        }
    }

    /**
     * Belirli bir type için DB'de cache verisi var mı?
     * init'te "kapatılırsa temizle" kararını verirken gereksiz DELETE'i önler.
     */
    private static function _has_cached_data( string $type ): bool {
        global $wpdb;
        if ( $type === 'get_field' ) {
            return (bool) get_option( self::ACF_OPTIONS_BULK_KEY, false );
        }
        if ( $type === 'wp_options' ) {
            return (bool) get_option( self::WP_OPTIONS_BULK_KEY, false );
        }
        $prefix = self::TYPE_PREFIXES[ $type ] ?? null;
        if ( ! $prefix ) return false;
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 1",
            '_transient_' . self::PREFIX . $prefix . '%'
        ) );
    }

    /**
     * RAM cache girişi RUNTIME_LIMIT'i aşarsa en eski %60'ı atar.
     * Uzun sayfa yüklemelerinde bellek patlamasını önler.
     */
    private static function _manage_runtime_limit(): void {
        if ( count( self::$runtime_cache ) > self::RUNTIME_LIMIT ) {
            $keep = (int) ( self::RUNTIME_LIMIT * 0.4 );
            self::$runtime_cache  = array_slice( self::$runtime_cache,  -$keep, null, true );
            self::$initial_hashes = array_slice( self::$initial_hashes, -$keep, null, true );
        }
    }


    // =========================================================================
    // PUBLIC YARDIMCILAR
    // =========================================================================

    /**
     * Belirli bir key'i hem DB'den hem RAM'den siler.
     */
    public static function forget( string $key ): void {
        $full = self::PREFIX . $key;
        delete_transient( $full );
        delete_option( $full );
        unset( self::$runtime_cache[ $full ], self::$initial_hashes[ $full ] );
    }

    /**
     * Cache durumunu raporlar. Debug/yönetim paneli için kullanılır.
     */
    public static function status(): array {
        $manifest = self::_load_manifest();
        return [
            'version'        => self::VERSION,
            'cache'          => self::$cache,
            'initiated'      => self::$initiated,
            'ttl'            => self::$ttl,
            'config'         => self::$config,
            'runtime_count'  => count( self::$runtime_cache ),
            'runtime_keys'   => array_keys( self::$runtime_cache ),
            'manifest_deps'  => array_keys( $manifest ),
            'manifest_count' => count( $manifest ),
            'pending_deps'   => array_keys( self::$pending_deps ),
        ];
    }

    // Backward compat
    public static function purge_prefix( string $search = '' ): void {
        self::_purge_db_prefix( $search );
    }
    public static function invalidate_options_bulk(): void {
        self::_invalidate_options_bulks();
    }
}


// =============================================================================
// INIT
// =============================================================================

$enable_object_cache  = get_option( 'options_enable_object_cache', false );
$object_cache_types   = [];
if ( $enable_object_cache ) {
    $object_cache_types = get_option( 'options_object_cache_types', [] );
    if ( ! is_array( $object_cache_types ) ) $object_cache_types = [];
}

QueryCache::init([
    'cache'  => $enable_object_cache,
    // 'ttl'          => 30 * DAY_IN_SECONDS,
    // 'enable_admin' => false,
    'config' => [
        'wrap'       => in_array( 'wrap',       $object_cache_types ),
        'get_field'  => in_array( 'get_field',  $object_cache_types ),
        'get_posts'  => in_array( 'get_posts',  $object_cache_types ),
        'get_post'   => in_array( 'get_post',   $object_cache_types ),
        'get_terms'  => in_array( 'get_terms',  $object_cache_types ),
        'get_term'   => in_array( 'get_term',   $object_cache_types ),
        'menu'       => in_array( 'menu',       $object_cache_types ),
        'wp_options' => in_array( 'wp_options', $object_cache_types ),
        'debug'      => false, // true yapılırsa error_log aktif
    ],
]);
