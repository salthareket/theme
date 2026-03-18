<?php
/**
 * QueryCache Engine v9.0
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
 *
 *   Timber::get_posts(), Timber::get_term() → otomatik (config timber:true ise)
 *   wp_nav_menu()                           → otomatik (config menu:true ise)
 *
 * KURALLAR:
 *   - Master switch false → tüm cache silinir, native WP'ye düşer.
 *   - Her tür bağımsız switch. Kapatılırsa o türün cache'i silinir.
 *   - ACF get_field ve WP get_option AYRI bulk'larda saklanır (veri tipi çakışması yok).
 *   - Admin panelinde çalışmaz (enable_admin:true ile açılabilir).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class QueryCache {

    // =========================================================================
    // SABİTLER
    // =========================================================================

    const VERSION     = '9.0';
    const PREFIX      = 'qcache_';
    const DEFAULT_TTL = 30 * DAY_IN_SECONDS;
    const NOT_FOUND   = '__QC_NF__';

    /**
     * ACF get_field("x", "options") sonuçları.
     * Format_value geçmiş — obje/array döner.
     * Ham DB değeri DEĞİL.
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
     * Her cache türünün transient prefix'i (purge için).
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
    ];

    protected static $runtime_cache  = [];
    protected static $initial_hashes = [];
    private   static $is_processing  = false;
    private   static $is_saving      = false;

    /**
     * Bu request'te purge edilen türleri tutar.
     * Her request'te static $config default true'dan başladığı için
     * "açıktan kapalıya geçiş" hatalı algılanmaması için kullanılır.
     */
    private static $purged_types = [];

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
        if ( is_admin() && ! self::$enable_admin ) {
            self::$cache     = false;
            self::$initiated = true;
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
        // ÖNEMLİ: static $config her request'te default true'dan başlar.
        // "false geldi + DB'de gerçekten veri var" kontrolü yapılır.
        // Yoksa her sayfada boşu boşuna purge DELETE sorgusu gider.
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

        add_action( 'created_term', [ $class, 'on_term_change' ], 99, 1 );
        add_action( 'edited_term',  [ $class, 'on_term_change' ], 99, 1 );
        add_action( 'delete_term',  [ $class, 'on_term_change' ], 99, 1 );

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

        // Shutdown: yeni verileri DB'ye yaz
        add_action( 'shutdown', [ $class, 'save_runtime_manifest' ], 999 );

        self::$initiated = true;
    }


    // =========================================================================
    // ACF GET_FIELD
    // =========================================================================

    /**
     * ACF get_field() cache'li versiyonu.
     *
     * options post_id → ACF_OPTIONS_BULK_KEY (format_value geçmiş, obje/array)
     * sayısal post_id → field_bulk_{id}      (ham meta preload + miss'te ACF yazar)
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

    public static function get_posts( array $args ): array {
        if ( ! self::$cache || ! self::$config['get_posts'] ) {
            return get_posts( $args ) ?: [];
        }
        $result = self::_read_or_fetch(
            'posts_' . md5( serialize( $args ) ),
            fn() => get_posts( $args ) ?: []
        );
        return is_array( $result ) ? $result : [];
    }

    // =========================================================================
    // WP GET_POST
    // =========================================================================

    public static function get_post( $post_id ): ?WP_Post {
        if ( ! self::$cache || ! self::$config['get_post'] ) {
            return get_post( $post_id ) ?: null;
        }
        $id     = ( $post_id instanceof WP_Post ) ? $post_id->ID : (int) $post_id;
        $result = self::_read_or_fetch(
            'post_single_' . $id,
            fn() => get_post( $post_id )
        );
        return ( $result instanceof WP_Post ) ? $result : null;
    }

    // =========================================================================
    // WP GET_TERMS
    // =========================================================================

    public static function get_terms( array $args ) {
        if ( ! self::$cache || ! self::$config['get_terms'] ) {
            return get_terms( $args );
        }
        $result = self::_read_or_fetch(
            'terms_' . md5( serialize( $args ) ),
            fn() => get_terms( $args )
        );
        return is_wp_error( $result ) ? [] : ( $result ?? [] );
    }

    // =========================================================================
    // WP GET_TERM
    // =========================================================================

    public static function get_term( $term_id, string $taxonomy = '' ) {
        if ( ! self::$cache || ! self::$config['get_term'] ) {
            return get_term( $term_id, $taxonomy );
        }
        $id = ( $term_id instanceof WP_Term ) ? $term_id->term_id : (int) $term_id;
        return self::_read_or_fetch(
            'term_' . $id,
            fn() => get_term( $term_id, $taxonomy )
        );
    }

    // =========================================================================
    // WRAP
    // =========================================================================

    /**
     * Herhangi bir callback'i cache'e alır.
     *
     * $data = QueryCache::wrap('my_key_' . $id, function() use ($id) {
     *     return my_expensive_query($id);
     * });
     */
    public static function wrap( string $key, callable $callback ) {
        if ( ! self::$cache || ! self::$config['wrap'] ) {
            return $callback();
        }
        return self::_read_or_fetch( 'wrap_' . $key, $callback );
    }

    // =========================================================================
    // MENÜ CACHE
    // =========================================================================

    private static function _make_menu_key( object $args ): string {
    // Sadece menü konumu (theme_location) veya menü slug'ı + dil
    $menu_id = $args->theme_location ?? (is_object($args->menu) ? $args->menu->slug : $args->menu) ?? 'default';
    $lang = function_exists('pll_current_language') ? pll_current_language() : get_locale();
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

    // 1. Önce RAM'e bak
    if ( isset( self::$runtime_cache[ $full_key ] ) ) {
        return self::$runtime_cache[ $full_key ];
    }

    // 2. Transient (DB) kontrolü
    $cached_html = get_transient( $full_key );
    if ( $cached_html !== false ) {
        self::$runtime_cache[ $full_key ] = $cached_html;
        return $cached_html;
    }

    return null; // Cache yoksa WP render etsin
}

public static function set_menu_cache( string $output, object $args ): string {
    if ( ! self::$cache || ! self::$config['menu'] || is_admin() || empty( $output ) ) {
        return $output;
    }

    $full_key = self::PREFIX . 'menu_html_' . self::_make_menu_key( $args );

    if ( ! isset( self::$runtime_cache[ $full_key ] ) ) {
        self::$runtime_cache[ $full_key ] = $output;
        // Direkt veritabanına HTML olarak gömüyoruz
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
     *
     * Ek olarak: post listesi için ACF bulk preload yapar.
     * Template'de {{ post.field }} çağrıları RAM'den gelir.
     
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
            self::_preload_acf_for_timber( $transient, $is_taxonomy );
            return $transient;
        }

        // Miss → gelen $data'yı cache'e al
        $to_store = ( $data === null || $data === false ) ? self::NOT_FOUND : $data;
        self::$runtime_cache[ $full_key ] = $to_store;
        // initial_hash yok → shutdown'da yazılacak

        if ( $to_store !== self::NOT_FOUND && self::$config['get_field'] ) {
            self::_preload_acf_for_timber( $data, $is_taxonomy );
        }

        return $data;
    }*/
    public static function timber_bridge( $data, $query_or_args ) {
        if ( ! self::$cache || ! self::$config['timber'] ) return $data;

        // Sorgu argümanlarından eşsiz bir anahtar üret
        $key_content = is_object($query_or_args) ? spl_object_hash($query_or_args) : serialize($query_or_args);
        $full_key = self::PREFIX . 'timber_' . md5($key_content);

        if ( array_key_exists( $full_key, self::$runtime_cache ) ) {
            $cached = self::$runtime_cache[ $full_key ];
            
            // KRİTİK NOKTA: Eğer cache'ten dönen veri bir ArrayObject ise 
            // ve biz tekil post bekliyorsak Timber bazen şaşırıyor.
            return $cached;
        }

        // DB kontrolü
        $transient = get_transient( $full_key );
        if ( $transient !== false ) {
            self::$runtime_cache[ $full_key ] = $transient;
            return $transient;
        }

        // Eğer veri yoksa sakla (Ama nesne kopyası olarak)
        if ( $data ) {
            self::$runtime_cache[ $full_key ] = $data;
        }

        return $data;
    }

    // =========================================================================
    // SHUTDOWN
    // =========================================================================

    public static function save_runtime_manifest(): void {
        error_log( '[QueryCache] shutdown fired — cache:' . (int)self::$cache . ' is_saving:' . (int)self::$is_saving . ' runtime_count:' . count(self::$runtime_cache) );

        if ( ! self::$cache || empty( self::$runtime_cache ) || self::$is_saving ) return;

        self::$is_saving = true;

        $written  = [];
        $skipped  = [];

        foreach ( self::$runtime_cache as $key => $data ) {
            $new_hash = md5( serialize( $data ) );

            if ( isset( self::$initial_hashes[ $key ] ) && self::$initial_hashes[ $key ] === $new_hash ) {
                $skipped[] = $key;
                continue;
            }

            $to_save = ( $data === null || $data === false || $data === '' ) ? self::NOT_FOUND : $data;

            if ( self::_is_bulk_key( $key ) ) {
                $result = update_option( $key, $data, 'no' );
                $written[] = '[BULK] ' . $key . ' → ' . ( $result ? 'OK' : 'FAIL/UNCHANGED' );
            } else {
                $result = set_transient( $key, $to_save, self::$ttl );
                $written[] = '[TRANSIENT] ' . $key . ' → ' . ( $result ? 'OK' : 'FAIL' );
            }
        }

        error_log( '[QueryCache] save_runtime_manifest — written: ' . implode( ', ', $written ) );
        error_log( '[QueryCache] save_runtime_manifest — skipped: ' . implode( ', ', $skipped ) );

        self::$is_saving = false;
    }

    // =========================================================================
    // INVALIDATION
    // =========================================================================

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

    public static function on_post_change( $post_id ): void {
        if ( self::$is_saving || ! $post_id ) return;
        $post_id = (int) $post_id;

        $field_bulk = self::PREFIX . 'field_bulk_' . $post_id;
        delete_option( $field_bulk );
        unset( self::$runtime_cache[ $field_bulk ], self::$initial_hashes[ $field_bulk ] );

        $single = self::PREFIX . 'post_single_' . $post_id;
        delete_transient( $single );
        unset( self::$runtime_cache[ $single ], self::$initial_hashes[ $single ] );

        self::purge_type( 'get_posts' );
        self::purge_type( 'timber' );
    }

    public static function on_term_change( $term_id ): void {
        if ( self::$is_saving ) return;
        $term_id = (int) $term_id;

        $term_key = self::PREFIX . 'term_' . $term_id;
        delete_transient( $term_key );
        unset( self::$runtime_cache[ $term_key ], self::$initial_hashes[ $term_key ] );

        self::purge_type( 'get_terms' );
        self::purge_type( 'timber' );
    }

    // =========================================================================
    // PURGE
    // =========================================================================

    public static function purge_type( string $type ): void {
        if ( $type === 'get_field' ) {
            delete_option( self::ACF_OPTIONS_BULK_KEY );
            unset( self::$runtime_cache[ self::ACF_OPTIONS_BULK_KEY ], self::$initial_hashes[ self::ACF_OPTIONS_BULK_KEY ] );
            self::_purge_db_prefix( 'field_bulk_' );
            self::_purge_runtime_prefix( self::PREFIX . 'field_bulk_' );
            return;
        }
        if ( $type === 'wp_options' ) {
            delete_option( self::WP_OPTIONS_BULK_KEY );
            unset( self::$runtime_cache[ self::WP_OPTIONS_BULK_KEY ], self::$initial_hashes[ self::WP_OPTIONS_BULK_KEY ] );
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
            "DELETE FROM {$wpdb->options} WHERE option_name = %s OR option_name = %s OR option_name LIKE %s",
            self::ACF_OPTIONS_BULK_KEY,
            self::WP_OPTIONS_BULK_KEY,
            $p . '%'
        ) );

        self::$runtime_cache  = [];
        self::$initial_hashes = [];

        if ( function_exists( 'wp_cache_flush' ) ) {
            wp_cache_flush();
        }
    }

    // =========================================================================
    // PRIVATE YARDIMCILAR
    // =========================================================================

    private static function _read_or_fetch( string $key, callable $callback ) {
        if ( self::$is_processing ) return $callback();

        $full_key = self::PREFIX . $key;

        if ( array_key_exists( $full_key, self::$runtime_cache ) ) {
            $cached = self::$runtime_cache[ $full_key ];
            return ( $cached === self::NOT_FOUND ) ? null : $cached;
        }

        $transient = get_transient( $full_key );
        if ( $transient !== false ) {
            $value = ( $transient === self::NOT_FOUND ) ? null : $transient;
            self::$runtime_cache[ $full_key ]  = $transient;
            self::$initial_hashes[ $full_key ] = md5( serialize( $transient ) );
            return $value;
        }

        self::$is_processing = true;
        $data = $callback();
        self::$is_processing = false;

        $to_store = ( $data === null || $data === false || $data === '' ) ? self::NOT_FOUND : $data;
        self::$runtime_cache[ $full_key ] = $to_store;

        return $data;
    }

    private static function _ensure_field_bulk_loaded( string $bulk_key, array $resolved ): void {
        if ( array_key_exists( $bulk_key, self::$runtime_cache ) ) return;

        $stored = get_option( $bulk_key, null );

        if ( $stored !== null && is_array( $stored ) ) {
            // DB'den geldi (daha önce cache'lenmişti) → hash set et, değişmezse yazma
            self::$runtime_cache[ $bulk_key ]  = $stored;
            self::$initial_hashes[ $bulk_key ] = md5( serialize( $stored ) );
            return;
        }

        // DB'de yok → boş başla.
        // Ham meta PRELOAD ETME — ACF repeater/relation/group gibi field'larda
        // get_post_meta() ham sayı/ID döndürür, ACF'nin format_value'su işlenmez.
        // Miss'lerde get_field() ACF'den çekip formatlanmış haliyle buraya yazar.
        self::$runtime_cache[ $bulk_key ] = [];
        // initial_hash YOK → shutdown'da yazılacak
    }

    private static function _ensure_wp_options_bulk_loaded(): void {
        if ( array_key_exists( self::WP_OPTIONS_BULK_KEY, self::$runtime_cache ) ) return;

        $stored = get_option( self::WP_OPTIONS_BULK_KEY, null );

        if ( $stored !== null && is_array( $stored ) ) {
            // DB'den geldi → hash set et
            self::$runtime_cache[ self::WP_OPTIONS_BULK_KEY ]  = $stored;
            self::$initial_hashes[ self::WP_OPTIONS_BULK_KEY ] = md5( serialize( $stored ) );
            return;
        }

        // DB'de yok → tek SQL ile çek, initial_hash KOYMA → shutdown'da yazılacak
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
                // DB'den geldi → hash set et
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

    private static function _is_bulk_key( string $key ): bool {
        return $key === self::ACF_OPTIONS_BULK_KEY
            || $key === self::WP_OPTIONS_BULK_KEY
            || strpos( $key, self::PREFIX . 'field_bulk_' ) === 0;
    }

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
            if ( strpos( $key, $prefix ) === 0 ) {
                unset( self::$runtime_cache[ $key ], self::$initial_hashes[ $key ] );
            }
        }
    }

    /*private static function _make_menu_key( object $args ): string {
        $lang = function_exists( 'ml_get_current_language' )
            ? ml_get_current_language()
            : get_locale();
        return md5( serialize( $args ) ) . '_' . $lang;
    }*/


    // =========================================================================
    // PUBLIC YARDIMCILAR
    // =========================================================================

    public static function forget( string $key ): void {
        $full = self::PREFIX . $key;
        delete_transient( $full );
        delete_option( $full );
        unset( self::$runtime_cache[ $full ], self::$initial_hashes[ $full ] );
    }

    public static function status(): array {
        return [
            'version'       => self::VERSION,
            'cache'         => self::$cache,
            'initiated'     => self::$initiated,
            'ttl'           => self::$ttl,
            'config'        => self::$config,
            'runtime_count' => count( self::$runtime_cache ),
            'runtime_keys'  => array_keys( self::$runtime_cache ),
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
        'menu'       => in_array('menu', $object_cache_types),  // Menüleri ve içindeki ACF alanlarını komple paketler.
        'wp_options' => in_array('wp_options', $object_cache_types)   // Sadece QueryCache::get_option() fonksiyonu ile çağrılanları cacheler.
    ]
]);