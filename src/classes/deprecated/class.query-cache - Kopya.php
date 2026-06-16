<?php
/**
 * ============================================================
 *  QueryCache Engine v11.0 — "Precision Invalidation"
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
 *   QueryCache::wp_query([...])              → WP_Query çalıştırıp sonucu cache'ler
 *   QueryCache::get_cached_menu('primary')   → Timber menü cache
 *   QueryCache::get_timber_posts([...])       → Timber::get_posts() cache (ID bazlı)
 *   QueryCache::get_timber_terms([...])       → Timber::get_terms() cache (ID bazlı)
 *   QueryCache::get_timber_post(123)          → Timber::get_post() cache (ID bazlı)
 *   QueryCache::get_timber_term(5)            → Timber::get_term() cache (ID bazlı)
 *   QueryCache::get_timber_users([...])       → Timber::get_users() cache (ID bazlı)
 *   QueryCache::get_timber_comments([...])    → Timber::get_comments() cache (ID bazlı)
 *   wp_nav_menu()                            → otomatik (config menu:true ise)
 *
 * KURALLAR:
 *   - Master switch false → tüm cache silinir, native WP'ye düşer.
 *   - Her tür bağımsız switch. Kapatılırsa o türün cache'i silinir.
 *   - ACF get_field ve WP get_option AYRI bulk'larda saklanır (veri tipi çakışması yok).
 *   - Admin panelinde çalışmaz (enable_admin:true ile açılabilir).
 *   - format=false ile çağrılan get_field ayrı cache key kullanır (çakışma yok).
 *
 * v11.0 DEĞİŞİKLİKLER (v10'dan):
 *   - BUG FIX: get_cached_menu() ve wp_query() eksik metotları eklendi.
 *   - BUG FIX: Timber bridge kaldırıldı (Timber v2'de bu filter'lar yok).
 *   - BUG FIX: save_runtime_manifest() bulk key'lerde $to_save kullanılıyor ($data değil).
 *   - BUG FIX: on_term_change() koşulsuz timber purge → manifest bazlı seçici silme.
 *   - BUG FIX: on_post_change() çift _flush_by_manifest çağrısı birleştirildi.
 *   - BUG FIX: on_option_change() — qcache_ prefix'li kendi key'lerini atlar.
 *   - BUG FIX: get_field() format parametresi cache key'ine dahil edildi.
 *   - PERF: Dirty-flag sistemi — shutdown'da sadece değişen key'ler yazılır.
 *   - PERF: _cleanup_stale_manifest() tek SQL ile toplu kontrol.
 *   - PERF: _ensure_wp_options_bulk_loaded() LIKE pattern daraltıldı.
 *   - PERF: Runtime eviction dirty_keys ile senkron.
 *   - Deprecated ACF_BULK_OPTION_KEY const kaldırıldı.
 * ============================================================
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class QueryCache {

    // =========================================================================
    // SABİTLER
    // =========================================================================

    private const VERSION     = '11.0';
    private const PREFIX      = 'qcache_';
    private const DEFAULT_TTL = 30 * DAY_IN_SECONDS;
    private const NOT_FOUND   = '__QC_NF__';
    private const RUNTIME_LIMIT = 500;
    private const MANIFEST_KEY  = 'qcache_manifest';

    /** ACF get_field("x", "options") sonuçları — format_value geçmiş */
    private const ACF_OPTIONS_BULK_KEY = 'qcache_acf_options_bulk';

    /** WP get_option() sonuçları — ham DB değeri, format_value YOK */
    private const WP_OPTIONS_BULK_KEY = 'qcache_wp_options_bulk';

    /** Her cache türünün transient prefix'i (toptan purge için) */
    private const TYPE_PREFIXES = [
        'get_field'      => 'field_bulk_',
        'get_posts'      => 'posts_',
        'get_post'       => 'post_single_',
        'get_terms'      => 'terms_',
        'get_term'       => 'term_',
        'menu'           => 'menu_',
        'wp_options'     => 'wp_opt_',
        'wrap'           => 'wrap_',
        'timber_posts'   => 'timber_posts_',
        'timber_terms'   => 'timber_terms_',
        'timber_post'    => 'timber_post_',
        'timber_term'    => 'timber_term_',
        'timber_users'   => 'timber_users_',
        'timber_comments'=> 'timber_comments_',
        'wpquery'        => 'wpquery_',
    ];

    // =========================================================================
    // DURUM
    // =========================================================================

    private static bool  $initiated    = false;
    private static bool  $cache        = true;
    private static bool  $enable_admin = false;
    private static int   $ttl          = self::DEFAULT_TTL;

    private static array $config = [
        'wrap'       => true,
        'get_field'  => true,
        'get_posts'  => true,
        'get_post'   => true,
        'get_terms'  => true,
        'get_term'   => true,
        'wp_options' => true,
        'menu'       => true,
        'debug'      => false,
    ];

    /** RAM cache — aynı request içinde DB'ye ikinci kez gitmez */
    private static array $runtime_cache  = [];

    /** DB'den yüklenen key'lerin hash'leri — değişim tespiti için */
    private static array $initial_hashes = [];

    /** Bu request'te değişen key'ler — sadece bunlar shutdown'da yazılır */
    private static array $dirty_keys = [];

    /** ACF / recursion koruması */
    private static bool $is_processing = false;

    /** Shutdown yazımı sırasında hook tetiklenmesini engeller */
    private static bool $is_saving = false;

    /** Bu request'te purge edilen türler (init'te çift purge önleme) */
    private static array $purged_types = [];

    /** Yeni bağımlılıklar — shutdown'da manifest'e eklenir */
    private static array $pending_deps = [];

    /** Manifest cache — request başına tek DB okuması */
    private static ?array $manifest_cache = null;


    // =========================================================================
    // INIT
    // =========================================================================

    public static function init( array $args = [] ): void {
        if ( self::$initiated ) return;

        if ( isset( $args['enable_admin'] ) ) {
            self::$enable_admin = (bool) $args['enable_admin'];
        }

        // Admin'de cache READ/WRITE kapalı ama invalidation hook'ları aktif
        if ( is_admin() && ! self::$enable_admin ) {
            self::$cache     = false;
            self::$initiated = true;
            self::_register_invalidation_hooks();
            return;
        }

        if ( isset( $args['cache'] ) ) {
            self::$cache = (bool) $args['cache'];
        }

        if ( ! self::$cache ) {
            self::_maybe_clear_all();
            self::$initiated = true;
            return;
        }

        if ( isset( $args['ttl'] ) ) {
            self::$ttl = max( 1, (int) $args['ttl'] );
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

        self::_register_invalidation_hooks();

        // Menü kancaları
        if ( self::$config['menu'] ) {
            add_filter( 'pre_wp_nav_menu', [ static::class, 'get_menu_cache' ], 10, 2 );
            add_filter( 'wp_nav_menu',     [ static::class, 'set_menu_cache' ], 10, 2 );
        }

        // Shutdown: dirty key'leri DB'ye yaz + manifest güncelle
        add_action( 'shutdown', [ static::class, 'save_runtime_to_db' ], 999 );

        self::$initiated = true;
    }

    private static function _register_invalidation_hooks(): void {
        $c = static::class;

        add_action( 'updated_option', [ $c, 'on_option_change' ], 10, 1 );
        add_action( 'added_option',   [ $c, 'on_option_change' ], 10, 1 );
        add_action( 'deleted_option', [ $c, 'on_option_change' ], 10, 1 );

        add_action( 'save_post',   [ $c, 'on_post_change' ], 10, 1 );
        add_action( 'delete_post', [ $c, 'on_post_change' ], 10, 1 );
        add_action( 'transition_post_status', static function ( $new, $old, $post ) {
            if ( $new === 'publish' || $old === 'publish' ) {
                static::on_post_change( $post->ID );
            }
        }, 10, 3 );

        add_action( 'created_term', [ $c, 'on_term_change' ], 99, 3 );
        add_action( 'edited_term',  [ $c, 'on_term_change' ], 99, 3 );
        add_action( 'delete_term',  [ $c, 'on_term_change' ], 99, 3 );

        add_action( 'wp_update_nav_menu', [ $c, 'on_menu_change' ] );

        // Menu populate etkilenen olaylar - post/term degisince menu cache de temizlenmeli
        add_action( 'save_post',   [ $c, 'on_menu_change' ], 99 );
        add_action( 'delete_post', [ $c, 'on_menu_change' ], 99 );
        add_action( 'created_term', function() use ($c) { $c::on_menu_change(); }, 100 );
        add_action( 'edited_term',  function() use ($c) { $c::on_menu_change(); }, 100 );
        add_action( 'delete_term',  function() use ($c) { $c::on_menu_change(); }, 100 );
    }

    // =========================================================================
    // PUBLIC ACCESSORS
    // =========================================================================

    public static function is_enabled(): bool {
        return self::$cache;
    }

    public static function get_config( string $key = '' ) {
        if ( $key === '' ) return self::$config;
        return self::$config[ $key ] ?? null;
    }

    // =========================================================================
    // ACF GET_FIELD
    // =========================================================================

    public static function get_field( string $selector, $post_id = null, bool $format = true ) {
        if ( ! self::$cache || ! self::$config['get_field'] || self::$is_processing ) {
            return function_exists( 'get_field' ) ? get_field( $selector, $post_id, $format ) : null;
        }
        if ( ! function_exists( 'get_field' ) ) return null;

        $post_id  = $post_id ?: get_the_ID();
        $resolved = self::_resolve_acf_target( $post_id );

        $clean = preg_replace( '/_[a-z]{2}_[A-Z]{2}$/', '', $selector );

        // format=false → ayrı cache key (ham vs format'lı çakışmasını önler)
        $format_suffix = $format ? '' : ':raw';

        if ( $resolved['type'] === 'opt' ) {
            $bulk_key  = self::ACF_OPTIONS_BULK_KEY;
            $check_key = ( ! str_starts_with( $clean, 'options_' ) ? 'options_' . $clean : $clean ) . $format_suffix;
        } else {
            $bulk_key  = self::PREFIX . 'field_bulk_' . $resolved['id'];
            $check_key = $clean . $format_suffix;
        }

        self::_ensure_field_bulk_loaded( $bulk_key );

        if ( array_key_exists( $check_key, self::$runtime_cache[ $bulk_key ] ) ) {
            $val = self::$runtime_cache[ $bulk_key ][ $check_key ];
            return ( $val === self::NOT_FOUND ) ? false : $val;
        }

        // Miss → ACF'den çek
        self::$is_processing = true;
        $value = get_field( $selector, $post_id, $format );
        self::$is_processing = false;

        self::$runtime_cache[ $bulk_key ][ $check_key ] =
            ( $value === null || $value === false ) ? self::NOT_FOUND : $value;

        self::$dirty_keys[ $bulk_key ] = true;
        self::_manage_runtime_limit();

        return $value;
    }


    // =========================================================================
    // WP GET_OPTION
    // =========================================================================

    public static function get_option( string $option, $default = false ) {
        if (
            ! self::$cache
            || ! self::$config['wp_options']
            || $option === self::ACF_OPTIONS_BULK_KEY
            || $option === self::WP_OPTIONS_BULK_KEY
            || $option === self::MANIFEST_KEY
        ) {
            return get_option( $option, $default );
        }

        self::_ensure_wp_options_bulk_loaded();

        if ( array_key_exists( $option, self::$runtime_cache[ self::WP_OPTIONS_BULK_KEY ] ) ) {
            $val = self::$runtime_cache[ self::WP_OPTIONS_BULK_KEY ][ $option ];
            return ( $val === self::NOT_FOUND ) ? $default : $val;
        }

        $value = get_option( $option, $default );
        self::$runtime_cache[ self::WP_OPTIONS_BULK_KEY ][ $option ] =
            ( $value === null || $value === false ) ? self::NOT_FOUND : $value;

        self::$dirty_keys[ self::WP_OPTIONS_BULK_KEY ] = true;

        return $value;
    }


    // =========================================================================
    // GENERIC WRAP (Herhangi bir callback'i cache'le)
    // =========================================================================

    /**
     * Herhangi bir callable'ı cache key ile sarar.
     *
     * Kullanım:
     *   QueryCache::wrap('kampanyalar', fn() => get_posts([...]), ['post_type' => ['campaign']]);
     */
    public static function wrap( string $key, callable $callback, array $deps = [] ) {
        if ( ! self::$cache || ! ( self::$config['wrap'] ?? true ) ) {
            return $callback();
        }
        return self::_read_or_fetch( 'wrap_' . $key, $callback, $deps );
    }

    // =========================================================================
    // WP GET_POSTS
    // =========================================================================

    public static function get_posts( array $args ): array {
        if ( ! self::$cache || ! self::$config['get_posts'] ) {
            return get_posts( $args ) ?: [];
        }

        $cache_key = 'posts_' . md5( serialize( $args ) );

        $post_type = $args['post_type'] ?? 'post';
        $deps = [ 'post_type' => (array) $post_type ];

        if ( ! empty( $args['tax_query'] ) && is_array( $args['tax_query'] ) ) {
            foreach ( $args['tax_query'] as $tq ) {
                if ( isset( $tq['taxonomy'] ) ) {
                    $deps['taxonomy'][] = $tq['taxonomy'];
                }
            }
        }

        $result = self::_read_or_fetch( $cache_key, static fn() => get_posts( $args ) ?: [], $deps );
        return is_array( $result ) ? $result : [];
    }


    // =========================================================================
    // WP GET_POST
    // =========================================================================

    public static function get_post( $post_id ): ?\WP_Post {
        if ( ! self::$cache || ! self::$config['get_post'] ) {
            return get_post( $post_id ) ?: null;
        }

        $id   = ( $post_id instanceof \WP_Post ) ? $post_id->ID : (int) $post_id;
        $deps = [ 'post_id' => [ $id ] ];

        $result = self::_read_or_fetch(
            'post_single_' . $id,
            static fn() => get_post( $post_id ),
            $deps
        );
        return ( $result instanceof \WP_Post ) ? $result : null;
    }


    // =========================================================================
    // WP GET_TERMS
    // =========================================================================

    public static function get_terms( array $args ) {
        if ( ! self::$cache || ! self::$config['get_terms'] ) {
            return get_terms( $args );
        }

        $cache_key = 'terms_' . md5( serialize( $args ) );
        $taxonomy  = isset( $args['taxonomy'] ) ? (array) $args['taxonomy'] : [];
        $deps      = ! empty( $taxonomy ) ? [ 'taxonomy' => $taxonomy ] : [];

        $result = self::_read_or_fetch( $cache_key, static fn() => get_terms( $args ), $deps );
        return is_wp_error( $result ) ? [] : ( $result ?? [] );
    }


    // =========================================================================
    // WP GET_TERM
    // =========================================================================

    public static function get_term( $term_id, string $taxonomy = '' ) {
        if ( ! self::$cache || ! self::$config['get_term'] ) {
            return get_term( $term_id, $taxonomy );
        }

        $id   = ( $term_id instanceof \WP_Term ) ? $term_id->term_id : (int) $term_id;
        $deps = [ 'term_id' => [ $id ] ];
        if ( $taxonomy ) {
            $deps['taxonomy'][] = $taxonomy;
        }

        return self::_read_or_fetch(
            'term_' . $id,
            static fn() => get_term( $term_id, $taxonomy ),
            $deps
        );
    }


    // =========================================================================
    // WP_QUERY — Backward compat (acf.php'den çağrılıyor)
    // =========================================================================

    /**
     * WP_Query çalıştırıp sonucu cache'ler.
     * get_posts() ile aynı mantık ama WP_Query döner.
     *
     * @param  array $args WP_Query argümanları
     * @return \WP_Query
     */
    public static function wp_query( array $args ): \WP_Query {
        if ( ! self::$cache || ! self::$config['get_posts'] ) {
            return new \WP_Query( $args );
        }

        $cache_key = 'wpquery_' . md5( serialize( $args ) );
        $post_type = $args['post_type'] ?? 'post';
        $deps      = [ 'post_type' => (array) $post_type ];

        $result = self::_read_or_fetch( $cache_key, static fn() => new \WP_Query( $args ), $deps );
        return ( $result instanceof \WP_Query ) ? $result : new \WP_Query( $args );
    }


    // =========================================================================
    // TIMBER CACHE — get_posts / get_terms / get_post
    // =========================================================================

    /**
     * Timber::get_posts() cache'li versiyonu.
     *
     * Strateji: Post ID'lerini + found_posts'u cache'le, Timber objelerini her
     * request'te ID'lerden oluştur. Böylece:
     *   - DB sorgusu (WP_Query) cache'ten gelir → hızlı
     *   - Timber objeleri taze kalır → ACF field'lar, meta vs. güncel
     *   - PostQuery serialize sorunu yok
     *
     * Kullanım:
     *   $posts = QueryCache::get_timber_posts(['post_type' => 'product', 'posts_per_page' => 12]);
     *   // Timber\PostQuery döner — pagination, found_posts vs. çalışır
     */
    public static function get_timber_posts( array $args ): mixed {
        if ( ! self::$cache || ! self::$config['get_posts'] || ! class_exists( 'Timber\\Timber' ) ) {
            return \Timber::get_posts( $args );
        }

        $cache_key = 'timber_posts_' . md5( serialize( $args ) );
        $post_type = $args['post_type'] ?? 'post';
        $deps      = [ 'post_type' => (array) $post_type ];

        if ( ! empty( $args['tax_query'] ) && is_array( $args['tax_query'] ) ) {
            foreach ( $args['tax_query'] as $tq ) {
                if ( isset( $tq['taxonomy'] ) ) {
                    $deps['taxonomy'][] = $tq['taxonomy'];
                }
            }
        }

        $full_key = self::PREFIX . $cache_key;

        // RAM hit
        if ( array_key_exists( $full_key, self::$runtime_cache ) ) {
            $cached = self::$runtime_cache[ $full_key ];
            if ( $cached !== self::NOT_FOUND && is_array( $cached ) && isset( $cached['ids'] ) ) {
                return self::_timber_posts_from_ids( $cached['ids'], $cached['found'] ?? 0, $args );
            }
        }

        // Transient hit
        $transient = get_transient( $full_key );
        if ( $transient !== false && is_array( $transient ) && isset( $transient['ids'] ) ) {
            self::$runtime_cache[ $full_key ]  = $transient;
            self::$initial_hashes[ $full_key ] = md5( serialize( $transient ) );
            if ( ! empty( $deps ) ) {
                self::_register_dep( $full_key, $deps );
            }
            return self::_timber_posts_from_ids( $transient['ids'], $transient['found'] ?? 0, $args );
        }

        // Miss → Timber'dan çek, ID'leri cache'le
        $result = \Timber::get_posts( $args );

        $ids   = [];
        $found = 0;

        if ( $result instanceof \Timber\PostQuery ) {
            foreach ( $result as $post ) {
                if ( isset( $post->ID ) ) {
                    $ids[] = $post->ID;
                }
            }
            // found_posts için underlying WP_Query'ye eriş
            try {
                $pagination = $result->pagination();
                if ( $pagination !== null && isset( $pagination->total ) ) {
                    $found = (int) $pagination->total;
                }
            } catch ( \Throwable ) {}

            if ( $found === 0 ) {
                $found = count( $ids );
            }
        }

        $to_store = ! empty( $ids ) ? [ 'ids' => $ids, 'found' => $found ] : self::NOT_FOUND;
        self::$runtime_cache[ $full_key ] = $to_store;
        self::$dirty_keys[ $full_key ]    = true;

        if ( ! empty( $deps ) ) {
            self::_register_dep( $full_key, $deps );
        }

        self::_manage_runtime_limit();

        return $result;
    }

    /**
     * Timber::get_terms() cache'li versiyonu.
     * Term ID'lerini cache'ler, Timber objelerini ID'lerden oluşturur.
     */
    public static function get_timber_terms( array $args ): mixed {
        if ( ! self::$cache || ! self::$config['get_terms'] || ! class_exists( 'Timber\\Timber' ) ) {
            return \Timber::get_terms( $args );
        }

        $cache_key = 'timber_terms_' . md5( serialize( $args ) );
        $taxonomy  = isset( $args['taxonomy'] ) ? (array) $args['taxonomy'] : [];
        $deps      = ! empty( $taxonomy ) ? [ 'taxonomy' => $taxonomy ] : [];

        $full_key = self::PREFIX . $cache_key;

        // RAM hit
        if ( array_key_exists( $full_key, self::$runtime_cache ) ) {
            $cached = self::$runtime_cache[ $full_key ];
            if ( $cached !== self::NOT_FOUND && is_array( $cached ) ) {
                return \Timber::get_terms( [ 'include' => $cached, 'hide_empty' => false ] );
            }
        }

        // Transient hit
        $transient = get_transient( $full_key );
        if ( $transient !== false && is_array( $transient ) ) {
            self::$runtime_cache[ $full_key ]  = $transient;
            self::$initial_hashes[ $full_key ] = md5( serialize( $transient ) );
            if ( ! empty( $deps ) ) {
                self::_register_dep( $full_key, $deps );
            }
            return \Timber::get_terms( [ 'include' => $transient, 'hide_empty' => false ] );
        }

        // Miss
        $result = \Timber::get_terms( $args );

        $ids = [];
        if ( is_iterable( $result ) ) {
            foreach ( $result as $term ) {
                $id = $term->term_id ?? $term->id ?? null;
                if ( $id ) $ids[] = (int) $id;
            }
        }

        $to_store = ! empty( $ids ) ? $ids : self::NOT_FOUND;
        self::$runtime_cache[ $full_key ] = $to_store;
        self::$dirty_keys[ $full_key ]    = true;

        if ( ! empty( $deps ) ) {
            self::_register_dep( $full_key, $deps );
        }

        self::_manage_runtime_limit();

        return $result;
    }

    /**
     * Cache'lenmiş ID'lerden Timber PostQuery oluşturur.
     * post__in + orderby=post__in ile orijinal sıralama korunur.
     */
    private static function _timber_posts_from_ids( array $ids, int $found, array $original_args ): mixed {
        if ( empty( $ids ) ) {
            return \Timber::get_posts( [ 'post__in' => [ 0 ], 'post_type' => 'any' ] );
        }

        $args = [
            'post_type'      => $original_args['post_type'] ?? 'any',
            'post__in'       => $ids,
            'posts_per_page' => count( $ids ),
            'orderby'        => 'post__in',
            'no_found_rows'  => true,
        ];

        return \Timber::get_posts( $args );
    }

    /**
     * Timber::get_post() cache'li versiyonu.
     * Post ID'yi cache'ler, Timber objesini ID'den oluşturur.
     */
    public static function get_timber_post( $post_id ): mixed {
        if ( ! self::$cache || ! self::$config['get_post'] || ! class_exists( 'Timber\Timber' ) ) {
            return \Timber::get_post( $post_id );
        }
        $id       = ( $post_id instanceof \WP_Post ) ? $post_id->ID : (int) $post_id;
        $full_key = self::PREFIX . 'timber_post_' . $id;
        $deps     = [ 'post_id' => [ $id ] ];

        if ( array_key_exists( $full_key, self::$runtime_cache ) ) {
            $cached = self::$runtime_cache[ $full_key ];
            return ( $cached !== self::NOT_FOUND && is_int( $cached ) ) ? \Timber::get_post( $cached ) : null;
        }
        $transient = get_transient( $full_key );
        if ( $transient !== false && $transient !== self::NOT_FOUND ) {
            self::$runtime_cache[ $full_key ]  = $transient;
            self::$initial_hashes[ $full_key ] = md5( serialize( $transient ) );
            self::_register_dep( $full_key, $deps );
            return is_int( $transient ) ? \Timber::get_post( $transient ) : null;
        }
        $result   = \Timber::get_post( $post_id );
        $to_store = ( $result && isset( $result->ID ) ) ? (int) $result->ID : self::NOT_FOUND;
        self::$runtime_cache[ $full_key ] = $to_store;
        self::$dirty_keys[ $full_key ]    = true;
        self::_register_dep( $full_key, $deps );
        self::_manage_runtime_limit();
        return $result;
    }

    /**
     * Timber::get_term() cache'li versiyonu.
     */
    public static function get_timber_term( $term_id, string $taxonomy = '' ): mixed {
        if ( ! self::$cache || ! self::$config['get_term'] || ! class_exists( 'Timber\Timber' ) ) {
            return \Timber::get_term( $term_id );
        }
        $id       = ( $term_id instanceof \WP_Term ) ? $term_id->term_id : (int) $term_id;
        $full_key = self::PREFIX . 'timber_term_' . $id;
        $deps     = [ 'term_id' => [ $id ] ];
        if ( $taxonomy ) { $deps['taxonomy'][] = $taxonomy; }

        if ( array_key_exists( $full_key, self::$runtime_cache ) ) {
            $cached = self::$runtime_cache[ $full_key ];
            return ( $cached !== self::NOT_FOUND && is_int( $cached ) ) ? \Timber::get_term( $cached ) : null;
        }
        $transient = get_transient( $full_key );
        if ( $transient !== false && $transient !== self::NOT_FOUND ) {
            self::$runtime_cache[ $full_key ]  = $transient;
            self::$initial_hashes[ $full_key ] = md5( serialize( $transient ) );
            self::_register_dep( $full_key, $deps );
            return is_int( $transient ) ? \Timber::get_term( $transient ) : null;
        }
        $result   = \Timber::get_term( $term_id );
        $to_store = self::NOT_FOUND;
        if ( $result ) {
            $to_store = (int) ( $result->term_id ?? $result->id ?? 0 );
            if ( $to_store === 0 ) $to_store = self::NOT_FOUND;
        }
        self::$runtime_cache[ $full_key ] = $to_store;
        self::$dirty_keys[ $full_key ]    = true;
        self::_register_dep( $full_key, $deps );
        self::_manage_runtime_limit();
        return $result;
    }

    /**
     * Timber::get_users() cache'li versiyonu.
     * User ID'lerini cache'ler, Timber objelerini ID'lerden oluşturur.
     */
    public static function get_timber_users( array $args ): mixed {
        if ( ! self::$cache || ! self::$config['get_posts'] || ! class_exists( 'Timber\Timber' ) ) {
            $q = new \WP_User_Query( $args );
            return \Timber::get_users( $q->get_results() );
        }
        $full_key = self::PREFIX . 'timber_users_' . md5( serialize( $args ) );

        if ( array_key_exists( $full_key, self::$runtime_cache ) ) {
            $cached = self::$runtime_cache[ $full_key ];
            return ( $cached !== self::NOT_FOUND && is_array( $cached ) ) ? \Timber::get_users( $cached ) : [];
        }
        $transient = get_transient( $full_key );
        if ( $transient !== false && is_array( $transient ) ) {
            self::$runtime_cache[ $full_key ]  = $transient;
            self::$initial_hashes[ $full_key ] = md5( serialize( $transient ) );
            return \Timber::get_users( $transient );
        }
        $q      = new \WP_User_Query( $args );
        $result = \Timber::get_users( $q->get_results() );
        $ids    = [];
        if ( is_iterable( $result ) ) {
            foreach ( $result as $u ) { if ( $u->ID ?? null ) $ids[] = (int) $u->ID; }
        }
        self::$runtime_cache[ $full_key ] = ! empty( $ids ) ? $ids : self::NOT_FOUND;
        self::$dirty_keys[ $full_key ]    = true;
        self::_manage_runtime_limit();
        return $result;
    }

    /**
     * Timber::get_comments() cache'li versiyonu.
     */
    public static function get_timber_comments( array $args ): mixed {
        if ( ! self::$cache || ! self::$config['get_posts'] || ! class_exists( 'Timber\Timber' ) ) {
            $q = new \WP_Comment_Query( $args );
            return \Timber::get_comments( $q->comments ?? [] );
        }
        $full_key = self::PREFIX . 'timber_comments_' . md5( serialize( $args ) );

        if ( array_key_exists( $full_key, self::$runtime_cache ) ) {
            $cached = self::$runtime_cache[ $full_key ];
            return ( $cached !== self::NOT_FOUND && is_array( $cached ) )
                ? \Timber::get_comments( get_comments( [ 'comment__in' => $cached ] ) ) : [];
        }
        $transient = get_transient( $full_key );
        if ( $transient !== false && is_array( $transient ) ) {
            self::$runtime_cache[ $full_key ]  = $transient;
            self::$initial_hashes[ $full_key ] = md5( serialize( $transient ) );
            return \Timber::get_comments( get_comments( [ 'comment__in' => $transient ] ) );
        }
        $q      = new \WP_Comment_Query( $args );
        $result = \Timber::get_comments( $q->comments ?? [] );
        $ids    = [];
        if ( is_iterable( $result ) ) {
            foreach ( $result as $c ) { if ( $c->comment_ID ?? null ) $ids[] = (int) $c->comment_ID; }
        }
        self::$runtime_cache[ $full_key ] = ! empty( $ids ) ? $ids : self::NOT_FOUND;
        self::$dirty_keys[ $full_key ]    = true;
        self::_manage_runtime_limit();
        return $result;
    }

    public static function get_cached_menu( string $location ): mixed {
        if ( ! self::$cache || ! self::$config['menu'] ) {
            return class_exists( 'Timber\\Menu' ) ? new \Timber\Menu( $location ) : null;
        }

        $lang     = function_exists( 'pll_current_language' ) ? pll_current_language() : get_locale();
        $full_key = self::PREFIX . 'menu_timber_' . md5( $location . '_' . $lang );

        // RAM
        if ( array_key_exists( $full_key, self::$runtime_cache ) ) {
            $cached = self::$runtime_cache[ $full_key ];
            return ( $cached === self::NOT_FOUND ) ? null : $cached;
        }

        // Transient
        $transient = get_transient( $full_key );
        if ( $transient !== false ) {
            self::$runtime_cache[ $full_key ]  = $transient;
            self::$initial_hashes[ $full_key ] = md5( serialize( $transient ) );
            return ( $transient === self::NOT_FOUND ) ? null : $transient;
        }

        // Miss
        $menu = class_exists( 'Timber\\Menu' ) ? new \Timber\Menu( $location ) : null;
        $to_store = $menu ?: self::NOT_FOUND;
        self::$runtime_cache[ $full_key ] = $to_store;
        self::$dirty_keys[ $full_key ]    = true;
        return $menu;
    }

    private static function _make_menu_key( object $args ): string {
        $menu_id = $args->theme_location
            ?? ( is_object( $args->menu ?? null ) ? ( $args->menu->slug ?? 'default' ) : ( $args->menu ?? 'default' ) )
            ?? 'default';
        $lang = function_exists( 'pll_current_language' ) ? pll_current_language() : get_locale();
        return md5( $menu_id . '_' . $lang );
    }

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
            self::$runtime_cache[ $full_key ]  = $cached_html;
            self::$initial_hashes[ $full_key ] = md5( $cached_html );
            return $cached_html;
        }

        return null;
    }

    public static function set_menu_cache( string $output, object $args ): string {
        if ( ! self::$cache || ! self::$config['menu'] || is_admin() || empty( $output ) ) {
            return $output;
        }

        $full_key = self::PREFIX . 'menu_html_' . self::_make_menu_key( $args );

        if ( ! isset( self::$runtime_cache[ $full_key ] ) ) {
            self::$runtime_cache[ $full_key ] = $output;
            self::$dirty_keys[ $full_key ]    = true;
        }

        return $output;
    }


    // =========================================================================
    // SHUTDOWN — sadece dirty key'leri yaz + manifest güncelle
    // =========================================================================

    public static function save_runtime_to_db(): void {
        if ( ! self::$cache || self::$is_saving ) return;

        self::$is_saving = true;
        $debug_written = [];

        // --- 1. Sadece dirty key'leri DB'ye yaz ---
        foreach ( self::$dirty_keys as $key => $_ ) {
            if ( ! array_key_exists( $key, self::$runtime_cache ) ) continue;

            $data    = self::$runtime_cache[ $key ];
            $to_save = ( $data === null || $data === false || $data === '' ) ? self::NOT_FOUND : $data;

            if ( self::_is_bulk_key( $key ) ) {
                update_option( $key, $to_save, 'no' );
            } else {
                set_transient( $key, $to_save, self::$ttl );
            }

            if ( self::$config['debug'] ) {
                $debug_written[] = $key;
            }
        }

        // --- 2. Manifest güncelle ---
        if ( ! empty( self::$pending_deps ) ) {
            $manifest = self::_load_manifest();
            $old_hash = md5( serialize( $manifest ) );

            foreach ( self::$pending_deps as $dep_key => $cache_keys ) {
                $manifest[ $dep_key ] = array_values( array_unique(
                    array_merge( $manifest[ $dep_key ] ?? [], $cache_keys )
                ) );
            }

            // Stale cleanup — her 100 request'te bir, tek SQL ile
            if ( mt_rand( 1, 100 ) === 1 ) {
                $manifest = self::_cleanup_stale_manifest( $manifest );
            }

            if ( $old_hash !== md5( serialize( $manifest ) ) ) {
                update_option( self::MANIFEST_KEY, $manifest, false );
            }
        }

        if ( self::$config['debug'] && ! empty( $debug_written ) ) {
            error_log( '[QueryCache v11] written: ' . implode( ', ', $debug_written ) );
        }

        self::$is_saving = false;
    }


    // =========================================================================
    // INVALIDATION
    // =========================================================================

    public static function on_option_change( string $option ): void {
        if ( self::$is_saving ) return;

        // Kendi cache key'lerimizi atla — sonsuz döngü önleme
        if ( str_starts_with( $option, self::PREFIX ) ) return;

        // ACF options_ prefix'li key'ler → bulk invalidation
        if (
            str_starts_with( $option, 'options_' )
            || $option === self::ACF_OPTIONS_BULK_KEY
            || $option === self::WP_OPTIONS_BULK_KEY
        ) {
            self::_invalidate_options_bulks();

            // Menu icerigini etkileyen option'lar degistiginde menu cache'ini de temizle
            self::purge_type( 'menu' );
            // ACF field cache'ini de temizle - get_field eski deger donmesin
            self::purge_type( 'get_field' );
        }
    }

    public static function on_post_change( $post_id ): void {
        if ( self::$is_saving || ! $post_id ) return;

        $post_id = (int) $post_id;

        if ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $post_id ) ) return;

        // ACF field bulk sil
        $field_bulk = self::PREFIX . 'field_bulk_' . $post_id;
        delete_option( $field_bulk );
        unset( self::$runtime_cache[ $field_bulk ], self::$initial_hashes[ $field_bulk ], self::$dirty_keys[ $field_bulk ] );

        // Tekil post cache sil
        $single = self::PREFIX . 'post_single_' . $post_id;
        delete_transient( $single );
        unset( self::$runtime_cache[ $single ], self::$initial_hashes[ $single ], self::$dirty_keys[ $single ] );

        // Manifest üzerinden seçici flush — tek çağrı (post + timber + wrap hepsi manifest'te)
        $post_type  = get_post_type( $post_id ) ?: '';
        $flush_deps = [ 'post_id:' . $post_id ];
        if ( $post_type ) {
            $flush_deps[] = 'post_type:' . $post_type;
        }
        self::_flush_by_manifest( $flush_deps );

        // Manifest'te kaydı yoksa güvenlik ağı — toptan sil
        if ( $post_type && ! self::_manifest_has( 'post_type:' . $post_type ) ) {
            self::purge_type( 'get_posts' );
            self::purge_type( 'timber_posts' );
            self::purge_type( 'wpquery' );
        }
    }

    public static function on_term_change( $term_id, $tt_id = 0, string $taxonomy = '' ): void {
        if ( self::$is_saving ) return;

        $term_id = (int) $term_id;

        // Tekil term cache sil
        $term_key = self::PREFIX . 'term_' . $term_id;
        delete_transient( $term_key );
        unset( self::$runtime_cache[ $term_key ], self::$initial_hashes[ $term_key ], self::$dirty_keys[ $term_key ] );

        // Manifest üzerinden seçici flush
        $flush_deps = [ 'term_id:' . $term_id ];
        if ( $taxonomy ) {
            $flush_deps[] = 'taxonomy:' . $taxonomy;
        }
        self::_flush_by_manifest( $flush_deps );

        // Manifest'te yoksa güvenlik ağı
        if ( $taxonomy && ! self::_manifest_has( 'taxonomy:' . $taxonomy ) ) {
            self::purge_type( 'get_terms' );
            self::purge_type( 'get_posts' );
            self::purge_type( 'timber_posts' );
            self::purge_type( 'timber_terms' );
        }
    }

    public static function on_menu_change(): void {
        self::purge_type( 'menu' );
    }


    // =========================================================================
    // PURGE
    // =========================================================================

    public static function purge_type( string $type ): void {
        if ( $type === 'get_field' ) {
            delete_option( self::ACF_OPTIONS_BULK_KEY );
            unset(
                self::$runtime_cache[ self::ACF_OPTIONS_BULK_KEY ],
                self::$initial_hashes[ self::ACF_OPTIONS_BULK_KEY ],
                self::$dirty_keys[ self::ACF_OPTIONS_BULK_KEY ]
            );
            self::_purge_db_prefix( 'field_bulk_' );
            self::_purge_runtime_prefix( self::PREFIX . 'field_bulk_' );
            return;
        }
        if ( $type === 'wp_options' ) {
            delete_option( self::WP_OPTIONS_BULK_KEY );
            unset(
                self::$runtime_cache[ self::WP_OPTIONS_BULK_KEY ],
                self::$initial_hashes[ self::WP_OPTIONS_BULK_KEY ],
                self::$dirty_keys[ self::WP_OPTIONS_BULK_KEY ]
            );
            return;
        }
        $prefix = self::TYPE_PREFIXES[ $type ] ?? null;
        if ( $prefix === null ) return;
        self::_purge_db_prefix( $prefix );
        self::_purge_runtime_prefix( self::PREFIX . $prefix );
    }

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

        self::$runtime_cache  = [];
        self::$initial_hashes = [];
        self::$dirty_keys     = [];
        self::$pending_deps   = [];
        self::$manifest_cache = null;

        if ( function_exists( 'wp_cache_flush' ) ) {
            wp_cache_flush();
        }
    }


    // =========================================================================
    // SMART MANIFEST — PRIVATE
    // =========================================================================

    private static function _load_manifest(): array {
        if ( self::$manifest_cache === null ) {
            self::$manifest_cache = get_option( self::MANIFEST_KEY, [] );
            if ( ! is_array( self::$manifest_cache ) ) {
                self::$manifest_cache = [];
            }
        }
        return self::$manifest_cache;
    }

    private static function _manifest_has( string $dep_key ): bool {
        $manifest = self::_load_manifest();
        return ! empty( $manifest[ $dep_key ] );
    }

    private static function _register_dep( string $full_cache_key, array $deps ): void {
        foreach ( $deps as $type => $values ) {
            foreach ( (array) $values as $val ) {
                $dep_key = $type . ':' . $val;
                self::$pending_deps[ $dep_key ] ??= [];
                if ( ! in_array( $full_cache_key, self::$pending_deps[ $dep_key ], true ) ) {
                    self::$pending_deps[ $dep_key ][] = $full_cache_key;
                }
            }
        }
    }

    private static function _flush_by_manifest( array $dep_keys ): void {
        $manifest = self::_load_manifest();
        $changed  = false;

        foreach ( $dep_keys as $dep_key ) {
            if ( empty( $manifest[ $dep_key ] ) ) continue;

            foreach ( $manifest[ $dep_key ] as $cache_key ) {
                unset(
                    self::$runtime_cache[ $cache_key ],
                    self::$initial_hashes[ $cache_key ],
                    self::$dirty_keys[ $cache_key ]
                );
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
     * Stale manifest temizliği — tek SQL ile toplu kontrol.
     * Eski versiyon her key için ayrı get_transient() çağırıyordu.
     */
    private static function _cleanup_stale_manifest( array $manifest ): array {
        global $wpdb;

        // Manifest'teki tüm cache key'lerini topla
        $all_keys = [];
        foreach ( $manifest as $cache_keys ) {
            foreach ( $cache_keys as $ck ) {
                $all_keys[ $ck ] = true;
            }
        }

        if ( empty( $all_keys ) ) return $manifest;

        // Tek SQL ile hangi key'ler hâlâ DB'de var kontrol et
        $key_list    = array_keys( $all_keys );
        $check_names = [];
        foreach ( $key_list as $k ) {
            $check_names[] = '_transient_' . $k;
            $check_names[] = $k; // option olarak da saklanmış olabilir
        }

        $placeholders = implode( ',', array_fill( 0, count( $check_names ), '%s' ) );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $existing = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name IN ({$placeholders})",
                ...$check_names
            )
        );

        $existing_set = array_flip( $existing );

        // Manifest'ten artık DB'de olmayan key'leri çıkar
        foreach ( $manifest as $dep_key => $cache_keys ) {
            $valid = [];
            foreach ( $cache_keys as $ck ) {
                if ( isset( $existing_set[ '_transient_' . $ck ] ) || isset( $existing_set[ $ck ] ) ) {
                    $valid[] = $ck;
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

    private static function _read_or_fetch( string $key, callable $callback, array $deps = [] ) {
        if ( self::$is_processing ) return $callback();

        $full_key = self::PREFIX . $key;

        // 1. RAM
        if ( array_key_exists( $full_key, self::$runtime_cache ) ) {
            $cached = self::$runtime_cache[ $full_key ];
            return ( $cached === self::NOT_FOUND ) ? null : $cached;
        }

        // 2. Transient
        $transient = get_transient( $full_key );
        if ( $transient !== false ) {
            $value = ( $transient === self::NOT_FOUND ) ? null : $transient;
            self::$runtime_cache[ $full_key ]  = $transient;
            self::$initial_hashes[ $full_key ] = md5( serialize( $transient ) );

            if ( ! empty( $deps ) ) {
                self::_register_dep( $full_key, $deps );
            }
            return $value;
        }

        // 3. Miss → callback
        self::$is_processing = true;
        $data = $callback();
        self::$is_processing = false;

        $to_store = ( $data === null || $data === false || $data === '' ) ? self::NOT_FOUND : $data;
        self::$runtime_cache[ $full_key ] = $to_store;
        self::$dirty_keys[ $full_key ]    = true;

        if ( ! empty( $deps ) ) {
            self::_register_dep( $full_key, $deps );
        }

        self::_manage_runtime_limit();

        return $data;
    }

    private static function _ensure_field_bulk_loaded( string $bulk_key ): void {
        if ( array_key_exists( $bulk_key, self::$runtime_cache ) ) return;

        $stored = get_option( $bulk_key, null );

        if ( $stored !== null && is_array( $stored ) ) {
            self::$runtime_cache[ $bulk_key ]  = $stored;
            self::$initial_hashes[ $bulk_key ] = md5( serialize( $stored ) );
            return;
        }

        self::$runtime_cache[ $bulk_key ] = [];
        // initial_hash yok → shutdown'da dirty olarak yazılacak
    }

    private static function _ensure_wp_options_bulk_loaded(): void {
        if ( array_key_exists( self::WP_OPTIONS_BULK_KEY, self::$runtime_cache ) ) return;

        $stored = get_option( self::WP_OPTIONS_BULK_KEY, null );

        if ( $stored !== null && is_array( $stored ) ) {
            self::$runtime_cache[ self::WP_OPTIONS_BULK_KEY ]  = $stored;
            self::$initial_hashes[ self::WP_OPTIONS_BULK_KEY ] = md5( serialize( $stored ) );
            return;
        }

        // DB'de yok → boş başla, lazy load ile dolacak
        self::$runtime_cache[ self::WP_OPTIONS_BULK_KEY ] = [];
    }

    private static function _is_bulk_key( string $key ): bool {
        return $key === self::ACF_OPTIONS_BULK_KEY
            || $key === self::WP_OPTIONS_BULK_KEY
            || str_starts_with( $key, self::PREFIX . 'field_bulk_' );
    }

    private static function _resolve_acf_target( $post_id ): array {
        if (
            is_string( $post_id ) && (
                in_array( $post_id, [ 'options', 'option' ], true )
                || str_starts_with( $post_id, 'options_' )
            )
        ) {
            return [ 'type' => 'opt', 'id' => 'global' ];
        }
        return [ 'type' => 'post', 'id' => (int) $post_id ?: 'global' ];
    }

    private static function _invalidate_options_bulks(): void {
        delete_option( self::ACF_OPTIONS_BULK_KEY );
        delete_option( self::WP_OPTIONS_BULK_KEY );
        unset(
            self::$runtime_cache[ self::ACF_OPTIONS_BULK_KEY ],
            self::$initial_hashes[ self::ACF_OPTIONS_BULK_KEY ],
            self::$dirty_keys[ self::ACF_OPTIONS_BULK_KEY ],
            self::$runtime_cache[ self::WP_OPTIONS_BULK_KEY ],
            self::$initial_hashes[ self::WP_OPTIONS_BULK_KEY ],
            self::$dirty_keys[ self::WP_OPTIONS_BULK_KEY ]
        );
    }

    private static function _maybe_clear_all(): void {
        global $wpdb;
        $has = $wpdb->get_var( $wpdb->prepare(
            "SELECT 1 FROM {$wpdb->options}
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

    private static function _purge_db_prefix( string $search ): void {
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            '_transient_' . self::PREFIX . $search . '%',
            '_transient_timeout_' . self::PREFIX . $search . '%'
        ) );
    }

    private static function _purge_runtime_prefix( string $prefix ): void {
        foreach ( array_keys( self::$runtime_cache ) as $key ) {
            if ( str_starts_with( $key, $prefix ) ) {
                unset(
                    self::$runtime_cache[ $key ],
                    self::$initial_hashes[ $key ],
                    self::$dirty_keys[ $key ]
                );
            }
        }
    }

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
            "SELECT 1 FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 1",
            '_transient_' . self::PREFIX . $prefix . '%'
        ) );
    }

    /**
     * RAM limiti aşılırsa en eski %60'ı at.
     * dirty_keys ile senkron — evict edilen dirty key'ler önce DB'ye yazılır.
     */
    private static function _manage_runtime_limit(): void {
        if ( count( self::$runtime_cache ) <= self::RUNTIME_LIMIT ) return;

        // Evict edilecek key'lerdeki dirty olanları önce yaz
        $keep      = (int) ( self::RUNTIME_LIMIT * 0.4 );
        $to_evict  = array_slice( self::$runtime_cache, 0, -$keep, true );

        self::$is_saving = true;
        foreach ( $to_evict as $key => $data ) {
            if ( isset( self::$dirty_keys[ $key ] ) ) {
                $to_save = ( $data === null || $data === false || $data === '' ) ? self::NOT_FOUND : $data;
                if ( self::_is_bulk_key( $key ) ) {
                    update_option( $key, $to_save, 'no' );
                } else {
                    set_transient( $key, $to_save, self::$ttl );
                }
            }
            unset( self::$dirty_keys[ $key ] );
        }
        self::$is_saving = false;

        self::$runtime_cache  = array_slice( self::$runtime_cache,  -$keep, null, true );
        self::$initial_hashes = array_slice( self::$initial_hashes, -$keep, null, true );
    }


    // =========================================================================
    // PUBLIC YARDIMCILAR
    // =========================================================================

    public static function forget( string $key ): void {
        $full = self::PREFIX . $key;
        delete_transient( $full );
        delete_option( $full );
        unset( self::$runtime_cache[ $full ], self::$initial_hashes[ $full ], self::$dirty_keys[ $full ] );
    }

    public static function status(): array {
        $manifest = self::_load_manifest();
        return [
            'version'        => self::VERSION,
            'cache'          => self::$cache,
            'initiated'      => self::$initiated,
            'ttl'            => self::$ttl,
            'config'         => self::$config,
            'runtime_count'  => count( self::$runtime_cache ),
            'dirty_count'    => count( self::$dirty_keys ),
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
    /** @deprecated v11 — save_runtime_manifest yerine save_runtime_to_db kullan */
    public static function save_runtime_manifest(): void {
        self::save_runtime_to_db();
    }
}


// =============================================================================
// INIT
// =============================================================================

$enable_object_cache = get_option( 'options_enable_object_cache', false );
$object_cache_types  = [];
if ( $enable_object_cache ) {
    $object_cache_types = get_option( 'options_object_cache_types', [] );
    if ( ! is_array( $object_cache_types ) ) $object_cache_types = [];
}

QueryCache::init( [
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
        'wp_options' => in_array( 'wp_options',  $object_cache_types ),
        'debug'      => false,
    ],
] );
