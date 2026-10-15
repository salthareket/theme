<?php

/**
 * AssetManagerAdmin
 *
 * Asset Manager admin sayfası — tab render, AJAX handler'lar.
 *
 * @version 2.0.0
 *
 * Changelog:
 *   2.0.0 - 2026-07-09
 *     - Add: WooCommerce box — Settings tab'a eklendi (enable_cart, enable_woo_api, api_local, api_live)
 *     - Add: ajaxSaveWoo() — options_enable_cart, options_enable_woo_api, options_woo_api_local, options_woo_api_live kaydeder
 *     - Add: JS — sh-woo-enable-api change handler (API credentials toggle)
 *     - Add: JS — sh-am-save-woo click handler (sh_am_save_woo AJAX)
 *   1.4.0 - 2026-07-05
 *     - Fix: Query Cache toggle label "Query Cache Aktif" → "Aktif"
 *     - Fix: Cache Tipleri (sh-qc-types-wrap) artık sadece toggle açıkken görünür
 *     - Add: JS — sh-qc-enabled change handler (types toggle)
 *     - Add: JS — sh-am-save-qc click handler (sh_am_save_query_cache AJAX)
 *   1.3.0 - 2026-07-04
 *     - Add: Query Cache bölümü Settings tab'a eklendi (enable_object_cache, object_cache_types)
 *     - Add: ajaxSaveQueryCache() — options_enable_object_cache + options_object_cache_types kaydeder
 *     - Add: Cache Manifest bölümü Settings tab'a eklendi
 *     - Add: ajaxSaveCacheManifest() — options_cache_manifest repeater kaydeder
 *     - Add: WP Rocket Cache Exclusions bölümü Settings tab'a eklendi
 *     - Add: ajaxSaveExclusions()
 *   1.2.0
 *     - Add: ajaxCompile(), ajaxCompileMethods(), ajaxExtractTranslations()
 *     - Add: Media Optimizer tab
 *   1.1.0
 *     - Add: ajaxPurgeCache()
 *     - Add: sh-select entegrasyonu
 *   1.0.0
 *     - Initial: Settings, Overview, Debug, Filters tab'ları
 */

namespace SaltHareket\AssetManager\Admin;

use SaltHareket\AssetManager\AssetManager;
use SaltHareket\AssetManager\AssetSettings;
use SaltHareket\AssetManager\MediaOptimizer;
use SaltHareket\AssetManager\Admin\MediaOptimizerAdmin;

class AssetManagerAdmin
{
    public static function register(): void
    {
        if ( ! is_admin() ) return;
        // add_action( 'admin_menu',            [ self::class, 'addMenuPage' ], 27 ); // Dashboard üzerinden erişiliyor
        add_action( 'admin_head',            [ self::class, 'hideNotices' ] );
        add_action( 'admin_enqueue_scripts', [ self::class, 'enqueueAssets' ] );
        add_action( 'wp_ajax_sh_am_save_settings',    [ self::class, 'ajaxSaveSettings' ] );
        add_action( 'wp_ajax_sh_am_purge_cache',       [ self::class, 'ajaxPurgeCache' ] );
        add_action( 'wp_ajax_sh_am_purge_selected',    [ self::class, 'ajaxPurgeSelected' ] );
        add_action( 'wp_ajax_sh_am_save_exclusions',      [ self::class, 'ajaxSaveExclusions' ] );
        add_action( 'wp_ajax_sh_am_save_cache_manifest',  [ self::class, 'ajaxSaveCacheManifest' ] );
        add_action( 'wp_ajax_sh_am_save_query_cache',     [ self::class, 'ajaxSaveQueryCache' ] );
        add_action( 'wp_ajax_sh_am_save_woo',             [ self::class, 'ajaxSaveWoo' ] );
        add_action( 'wp_ajax_sh_am_save_translations',    [ self::class, 'ajaxSaveTranslations' ] );
        add_action( 'wp_ajax_sh_am_save_breadcrumb',      [ self::class, 'ajaxSaveBreadcrumb' ] );
        add_action( 'wp_ajax_sh_am_save_forced_plugins',  [ self::class, 'ajaxSaveForcedPlugins' ] );
        add_action( 'wp_ajax_sh_am_get_groups_for_types', [ self::class, 'ajaxGetGroupsForTypes' ] );
        add_action( 'wp_ajax_sh_am_debug_assets',          [ self::class, 'ajaxDebugAssets' ] );
        add_action( 'wp_ajax_sh_am_compile',               [ self::class, 'ajaxCompile' ] );
        add_action( 'wp_ajax_sh_am_compile_methods',       [ self::class, 'ajaxCompileMethods' ] );
        add_action( 'wp_ajax_sh_am_extract_translations',  [ self::class, 'ajaxExtractTranslations' ] );
        // Auto-Fetch — nopriv dahil (frontend non-blocking request'ten tetiklenir)
        add_action( 'wp_ajax_sh_pae_auto_fetch',        [ self::class, 'ajaxAutoFetch' ] );
        add_action( 'wp_ajax_nopriv_sh_pae_auto_fetch', [ self::class, 'ajaxAutoFetch' ] );

        // Media Optimizer AJAX
        MediaOptimizerAdmin::registerAjax();

        // Cron
        add_action( MediaOptimizer::CRON_HOOK, [ MediaOptimizer::class, 'runCron' ] );
    }

    public static function addMenuPage(): void
    {
        add_submenu_page( 'theme-settings', '⚡ Assets', '⚡ Assets', 'manage_options', 'sh-asset-manager', [ self::class, 'renderPage' ] );
    }

    public static function hideNotices(): void
    {
        if ( ( $_GET['page'] ?? '' ) !== 'sh-asset-manager' ) return;
        echo '<style>body .notice:not(.sh-inline),body .updated:not(.sh-inline),body .error:not(.sh-inline){display:none!important;}
.sh-select-search:focus,.sh-select-search:active{outline:none!important;box-shadow:none!important;border:none!important;}
.sh-select-wrap:focus-within{outline:none!important;box-shadow:none!important;}
#wpcontent,#wpbody-content{padding-bottom:80px!important;}
</style>';
    }

    public static function enqueueAssets( string $hook ): void
    {
        if ( strpos( $hook, 'sh-asset-manager' ) === false ) return;

        $base_path = dirname( __DIR__, 2 );
        $base_url  = trailingslashit( SH_INCLUDES_URL ) . 'apps/';

        $css_path = $base_path . '/sh-admin.css';
        if ( file_exists( $css_path ) ) {
            wp_enqueue_style( 'sh-admin-kit', $base_url . 'sh-admin.css', [], filemtime( $css_path ) );
        }
        $select_css = $base_path . '/sh-select.css';
        if ( file_exists( $select_css ) ) {
            wp_enqueue_style( 'sh-select', $base_url . 'sh-select.css', [], filemtime( $select_css ) );
        }
        $select_js = $base_path . '/sh-select.js';
        if ( file_exists( $select_js ) ) {
            wp_enqueue_script( 'sh-select', $base_url . 'sh-select.js', [ 'jquery' ], filemtime( $select_js ), true );
        }

        // Bootstrap — PAE tablosu icin gerekli (btn, table, alert siniflari)
        $tab = sanitize_key( $_GET['tab'] ?? '' );
    }

    public static function ajaxSaveSettings(): void
    {
        check_ajax_referer( 'sh_am_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized.' );
        AssetSettings::save( $_POST['settings'] ?? [] );
        wp_send_json_success( 'Settings saved.' );
    }

    /**
     * Auto-Fetch AJAX handler.
     * Frontend non-blocking request'ten tetiklenir (nopriv dahil).
     * PAE fetch_post_url / fetch_term_url çalıştırır, WP Rocket cache'ini temizler.
     */
    public static function ajaxAutoFetch(): void
    {
        // Nonce kontrolü
        if ( ! check_ajax_referer( 'sh_pae_auto_fetch', 'nonce', false ) ) {
            wp_send_json_error( 'Invalid nonce.' );
        }

        $meta_type = sanitize_key( $_POST['meta_type'] ?? '' );
        $meta_id   = absint( $_POST['meta_id'] ?? 0 );

        if ( ! $meta_id || ! in_array( $meta_type, [ 'post', 'term' ], true ) ) {
            wp_send_json_error( 'Invalid params.' );
        }

        // PAE yüklü mü?
        if ( ! class_exists( '\PageAssetsExtractor' ) ) {
            wp_send_json_error( 'PAE not available.' );
        }

        $extractor = \PageAssetsExtractor::get_instance();

        if ( $meta_type === 'post' ) {
            $post = get_post( $meta_id );
            if ( ! $post || $post->post_status !== 'publish' ) {
                wp_send_json_error( 'Post not found or not published.' );
            }
            $extractor->type         = 'post';
            $extractor->force_rebuild = true;
            $result = $extractor->fetch_post_url( $meta_id );
        } else {
            // term
            $term = get_term( $meta_id );
            if ( ! $term || is_wp_error( $term ) ) {
                wp_send_json_error( 'Term not found.' );
            }
            $extractor->type         = 'term';
            $extractor->force_rebuild = true;
            $result = $extractor->fetch_term_url( $meta_id, $term->taxonomy );
        }

        // WP Rocket: skip transient temizle + sayfa cache'ini temizle
        $rocket_key = 'pae_skip_rocket_' . $meta_id;
        delete_transient( $rocket_key );

        if ( $result !== false ) {
            if ( $meta_type === 'post' && function_exists( 'rocket_clean_post' ) ) {
                rocket_clean_post( $meta_id );
            } elseif ( $meta_type === 'term' && function_exists( 'rocket_clean_term' ) ) {
                $term = get_term( $meta_id );
                if ( $term && ! is_wp_error( $term ) ) {
                    rocket_clean_term( $term );
                }
            }
        }

        wp_send_json_success( [
            'fetched'    => $result !== false,
            'meta_type'  => $meta_type,
            'meta_id'    => $meta_id,
        ] );
    }

    public static function ajaxPurgeCache(): void
    {
        check_ajax_referer( 'sh_am_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized.' );

        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_font_inline_cache_%' OR option_name LIKE '_transient_timeout_font_inline_cache_%'" );

        $cache_folder = rtrim( STATIC_PATH, '/' ) . '/css/cache';
        if ( file_exists( $cache_folder ) ) {
            array_map( 'unlink', glob( "{$cache_folder}/*.css" ) ?: [] );
        }

        $js_cache_folder = rtrim( STATIC_PATH, '/' ) . '/js/cache';
        if ( file_exists( $js_cache_folder ) ) {
            array_map( 'unlink', glob( "{$js_cache_folder}/*.js" ) ?: [] );
        }

        // ── Manifest dosyalarını da temizle ────────────────────────────
        $manifest_folder = rtrim( STATIC_PATH, '/' ) . '/cache-manifest';
        if ( file_exists( $manifest_folder ) ) {
            array_map( 'unlink', glob( "{$manifest_folder}/*.json" ) ?: [] );
        }
        
        // Plugin manifest (eski konum)
        $old_plugin_manifest = rtrim( STATIC_PATH, '/' ) . '/js/cache/plugin-manifest.json';
        if ( file_exists( $old_plugin_manifest ) ) {
            @unlink( $old_plugin_manifest );
        }

        // ── Meta reset (opsiyonel) ──────────────────────────────────────
        $reset_meta = ! empty( $_POST['reset_meta'] );
        $meta_deleted = 0;
        
        if ( $reset_meta ) {
            // _assets post meta'larını temizle (PAE meta key = 'assets')
            $meta_deleted += $wpdb->query(
                "DELETE FROM {$wpdb->postmeta} WHERE meta_key = 'assets'"
            );
            
            // _assets term meta'larını temizle
            $meta_deleted += $wpdb->query(
                "DELETE FROM {$wpdb->termmeta} WHERE meta_key = 'assets'"
            );
            
            // Dynamic/archive sayfalar (options) — hem 'assets' hem '_assets' pattern
            $dynamic_options = $wpdb->get_col(
                "SELECT option_name FROM {$wpdb->options} 
                 WHERE (option_name LIKE '%_assets' OR option_name LIKE '%assets')
                 AND option_name NOT LIKE '_transient%'
                 AND option_name NOT LIKE 'options_%'
                 AND option_name NOT LIKE 'sh_%'
                 AND option_name NOT LIKE 'pae_%'"
            );
            foreach ( $dynamic_options as $opt_name ) {
                // PAE formatı: lowercase + underscore + tire + _assets sonu
                // Örnekler: woo_account_edit-address_en_assets, search_assets, product_archive_tr_assets
                if ( preg_match( '/^[a-z0-9][a-z0-9_\-]*_assets$/', $opt_name ) ) {
                    delete_option( $opt_name );
                    $meta_deleted++;
                }
            }

            // WP object cache'i temizle — get_post_meta/get_term_meta/get_option
            // meta reset sonrası eski değerlerin cache'den gelmesini önle
            wp_cache_flush();

            // WP internal meta cache'i de temizle
            clean_post_cache( 0 );
        }

        AssetManager::$runtimeCache = [];
        AssetManager::$preloadQueue = [];

        $message = 'Cache cleared.';
        if ( $reset_meta && $meta_deleted > 0 ) {
            $message .= " {$meta_deleted} meta records deleted.";
        }

        wp_send_json_success( $message );
    }

    public static function ajaxPurgeSelected(): void
    {
        check_ajax_referer( 'sh_am_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized.' );

        $types = array_map( 'sanitize_key', (array) ( $_POST['types'] ?? [] ) );
        if ( empty( $types ) ) {
            $types = [ 'query', 'twig', 'opcache', 'wp_rocket' ];
        }

        $results = \SaltHareket\AssetManager\AssetManager::purgeCache( $types );

        wp_send_json_success( [ 'purged' => $types, 'results' => $results ] );
    }

    /**
     * WP Rocket cache exclusion ayarlarını kaydet.
     * Bu 3 key sh_asset_manager_settings içinde değil, kendi wp_options key'lerinde saklanır.
     * WP_Rocket_Dynamic_Excludes constructor'ı update_option hook'larını dinliyor — otomatik sync.
     */
    public static function ajaxSaveCacheManifest(): void {
        check_ajax_referer( 'sh_am_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized.' );

        $rows  = isset( $_POST['manifest'] ) && is_array( $_POST['manifest'] ) ? $_POST['manifest'] : [];
        $clean = [];

        foreach ( $rows as $row ) {
            $type = sanitize_key( $row['type'] ?? '' );
            if ( ! in_array( $type, [ 'post_type', 'taxonomy' ], true ) ) continue;

            $slug = sanitize_key( $row[ $type ] ?? '' );
            if ( ! $slug ) continue;

            $clean[] = [
                'type'    => $type,
                $type     => $slug,
                'self'    => ! empty( $row['self'] ),
                'home'    => ! empty( $row['home'] ),
                'archive' => $type === 'post_type' ? ! empty( $row['archive'] ) : false,
                'pages'   => array_values( array_filter( array_map( 'absint', (array) ( $row['pages'] ?? [] ) ) ) ),
                'terms'   => array_values( array_filter( array_map( 'absint', (array) ( $row['terms'] ?? [] ) ) ) ),
            ];
        }

        update_option( 'options_cache_manifest', $clean, false );

        // Singleton'ı sıfırla — bir sonraki request yeni veriyi okusun
        if ( class_exists( 'WP_Rocket_Manifest_Manager' ) ) {
            try {
                $ref  = new \ReflectionClass( 'WP_Rocket_Manifest_Manager' );
                $prop = $ref->getProperty( 'instance' );
                $prop->setAccessible( true );
                $prop->setValue( null, null );
            } catch ( \Throwable $e ) { /* sessiz */ }
        }

        if ( class_exists( '\\SaltHareket\\SaltConfig' ) ) {
            \SaltHareket\SaltConfig::generate();
        }

        wp_send_json_success( [
            'message' => count( $clean ) . ' manifest rule kaydedildi.',
            'count'   => count( $clean ),
        ] );
    }

    public static function ajaxSaveExclusions(): void {
        check_ajax_referer( 'sh_am_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized.' );

        $exclude_post_types = array_filter( array_map( 'sanitize_key', (array) ( $_POST['exclude_post_types_from_cache'] ?? [] ) ) );
        $exclude_posts      = array_filter( array_map( 'sanitize_key', (array) ( $_POST['exclude_posts_from_cache']      ?? [] ) ) );
        $exclude_taxonomies = array_filter( array_map( 'sanitize_key', (array) ( $_POST['exclude_taxonomies_from_cache'] ?? [] ) ) );

        // update_option çağrısı → WP_Rocket_Dynamic_Excludes::sync_rocket_settings() tetiklenir
        update_option( 'options_exclude_post_types_from_cache', array_values( $exclude_post_types ), false );
        update_option( 'options_exclude_posts_from_cache',      array_values( $exclude_posts ),      false );
        update_option( 'options_exclude_taxonomies_from_cache', array_values( $exclude_taxonomies ), false );

        // SaltConfig cache'ini de yenile
        if ( class_exists( '\\SaltHareket\\SaltConfig' ) ) {
            \SaltHareket\SaltConfig::generate();
        }

        wp_send_json_success( 'Exclusions saved.' );
    }

    public static function ajaxSaveQueryCache(): void {
        check_ajax_referer( 'sh_am_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized.' );

        $enabled = ! empty( $_POST['enable_object_cache'] );

        $valid_types = [ 'wrap', 'get_field', 'get_posts', 'get_post', 'get_terms', 'get_term', 'menu', 'wp_options' ];
        $types = [];
        if ( $enabled ) {
            $raw = (array) ( $_POST['object_cache_types'] ?? [] );
            foreach ( $raw as $t ) {
                $t = sanitize_key( $t );
                if ( in_array( $t, $valid_types, true ) ) {
                    $types[] = $t;
                }
            }
        }

        update_option( 'options_enable_object_cache', $enabled, false );
        update_option( 'options_object_cache_types',  $types,   false );

        // QueryCache'i hemen invalidate et — bir sonraki request temiz başlasın
        if ( class_exists( 'QueryCache' ) ) {
            \QueryCache::clear_all_cache();
        }

        wp_send_json_success( [ 'message' => 'Query Cache ayarları kaydedildi.' ] );
    }

    public static function ajaxSaveWoo(): void {
        check_ajax_referer( 'sh_am_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized.' );

        $enable_cart = ! empty( $_POST['enable_cart'] );
        $enable_api  = ! empty( $_POST['enable_woo_api'] );

        update_option( 'options_enable_cart',    $enable_cart, false );
        update_option( 'options_enable_woo_api', $enable_api,  false );

        $local = [
            'client'        => sanitize_text_field( $_POST['woo_api_local_client']  ?? '' ),
            'client_secret' => sanitize_text_field( $_POST['woo_api_local_secret']  ?? '' ),
        ];
        $live = [
            'client'        => sanitize_text_field( $_POST['woo_api_live_client']   ?? '' ),
            'client_secret' => sanitize_text_field( $_POST['woo_api_live_secret']   ?? '' ),
        ];

        update_option( 'options_woo_api_local', $local, false );
        update_option( 'options_woo_api_live',  $live,  false );

        // SaltConfig cache'i yenile
        if ( class_exists( '\\SaltHareket\\SaltConfig' ) ) {
            \SaltHareket\SaltConfig::generate();
        }

        wp_send_json_success( 'WooCommerce ayarları kaydedildi.' );
    }

    public static function ajaxSaveTranslations(): void {
        check_ajax_referer( 'sh_am_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized.' );

        $untranslatable_taxonomies  = array_values( array_filter( array_map( 'sanitize_key', (array) ( $_POST['untranslatable_taxonomies']   ?? [] ) ) ) );
        $untranslatable_post_types  = array_values( array_filter( array_map( 'sanitize_key', (array) ( $_POST['untranslatable_post_types']   ?? [] ) ) ) );
        $synchronized_pages         = array_values( array_filter( array_map( 'absint',        (array) ( $_POST['synchronized_pages']          ?? [] ) ) ) );
        $sync_taxonomies_to_language= array_values( array_filter( array_map( 'sanitize_key', (array) ( $_POST['sync_taxonomies_to_language'] ?? [] ) ) ) );

        update_option( 'options_untranslatable_taxonomies',      $untranslatable_taxonomies,   false );
        update_option( 'options_untranslatable_post_types',       $untranslatable_post_types,   false );
        update_option( 'options_synchronized_pages',              $synchronized_pages,          false );
        update_option( 'options_sync_taxonomies_to_language',     $sync_taxonomies_to_language, false );

        if ( class_exists( '\\SaltHareket\\SaltConfig' ) ) {
            \SaltHareket\SaltConfig::generate();
        }

        wp_send_json_success( 'Translations ayarları kaydedildi.' );
    }

    public static function ajaxSaveBreadcrumb(): void {
        check_ajax_referer( 'sh_am_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized.' );

        update_option( 'options_breadcrumb_remove_home',          ! empty( $_POST['breadcrumb_remove_home'] ),          false );
        update_option( 'options_breadcrumb_remove_current',       ! empty( $_POST['breadcrumb_remove_current'] ),       false );
        update_option( 'options_breadcrumb_from_menu',            ! empty( $_POST['breadcrumb_from_menu'] ),            false );
        update_option( 'options_breadcrumb_add_product_brand',    ! empty( $_POST['breadcrumb_add_product_brand'] ),    false );
        update_option( 'options_breadcrumb_add_product_taxonomy', ! empty( $_POST['breadcrumb_add_product_taxonomy'] ), false );

        // Repeater: [ {post_type, page}, ... ]
        $rows     = (array) ( $_POST['breadcrumb_add_page_path'] ?? [] );
        $clean    = [];
        foreach ( $rows as $row ) {
            $pt   = sanitize_key( $row['post_type'] ?? '' );
            $page = absint( $row['page'] ?? 0 );
            if ( $pt && $page ) {
                $clean[] = [ 'post_type' => $pt, 'page' => $page ];
            }
        }
        update_option( 'options_breadcrumb_add_page_path', $clean, false );

        if ( class_exists( '\\SaltHareket\\SaltConfig' ) ) {
            \SaltHareket\SaltConfig::generate();
        }

        wp_send_json_success( 'Breadcrumb ayarları kaydedildi.' );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FORCED PLUGINS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Forced Plugins repeater'ını kaydeder.
     * Option: pae_forced_plugins — [{ type: 'post_type'|'taxonomy', key: 'product', plugins: ['swiper','lightgallery'] }, ...]
     */
    public static function ajaxSaveForcedPlugins(): void {
        check_ajax_referer( 'sh_am_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized.' );

        // Geçerli plugin key'lerini doğrula
        $valid_plugins = [];
        if ( function_exists( 'compile_files_config' ) ) {
            $cfg = compile_files_config( true );
            $valid_plugins = array_keys( $cfg['js']['plugins'] ?? [] );
        }

        $rows  = (array) ( $_POST['rows'] ?? [] );
        $clean = [];
        foreach ( $rows as $row ) {
            $type    = in_array( $row['type'] ?? '', [ 'post_type', 'taxonomy' ], true ) ? $row['type'] : '';
            $key     = sanitize_key( $row['key'] ?? '' );
            $plugins = array_values( array_filter( array_map( 'sanitize_key', (array) ( $row['plugins'] ?? [] ) ) ) );
            // Geçerli plugin'lere filtrele
            if ( ! empty( $valid_plugins ) ) {
                $plugins = array_values( array_intersect( $plugins, $valid_plugins ) );
            }
            if ( $type && $key && ! empty( $plugins ) ) {
                $clean[] = [ 'type' => $type, 'key' => $key, 'plugins' => $plugins ];
            }
        }

        update_option( 'pae_forced_plugins', $clean, false );

        // Etkilenen grupların PAE row verilerini döndür — JS fetch tetikleyecek
        $affected_rows = self::getForcedPluginAffectedRows( $clean );

        wp_send_json_success( [
            'message'       => count( $clean ) . ' kural kaydedildi.',
            'affected_rows' => $affected_rows,
        ] );
    }

    /**
     * Forced Plugins için etkilenen PAE gruplarını (fetch edilecek row'ları) döndürür.
     */
    private static function getForcedPluginAffectedRows( array $rules ): array {
        if ( ! class_exists( '\PageAssetsExtractor' ) ) return [];

        $extractor = \PageAssetsExtractor::get_instance();
        if ( ! method_exists( $extractor, 'get_table_data' ) ) return [];

        $table    = $extractor->get_table_data();
        $all_rows = array_merge( $table['rows'] ?? [], $table['woo_pages'] ?? [] );
        $nonce    = wp_create_nonce( 'page_assets_nonce' );

        // Hangi key'ler etkileniyor
        $affected_keys = [];
        foreach ( $rules as $rule ) {
            $affected_keys[ $rule['key'] ] = $rule['type'];
        }

        $result = [];
        foreach ( $all_rows as $row ) {
            $pt      = $row['post_type'] ?? '';
            $context = $row['context']   ?? 'post';
            $type    = $row['type']      ?? 'post';
            $id      = $row['id']        ?? 0;
            $url     = $row['url']       ?? '';
            $label   = $row['label']     ?? $pt;
            $count   = $row['real_count'] ?? $row['count'] ?? 1;

            // post_type kuralı
            if ( isset( $affected_keys[ $pt ] ) && $affected_keys[ $pt ] === 'post_type' && $context === 'post' ) {
                $result[] = [
                    'id'        => $id,
                    'type'      => $type,
                    'context'   => $context,
                    'post_type' => $pt,
                    'url'       => $url,
                    'label'     => $label,
                    'count'     => $count,
                    'grouped'   => true,
                    'nonce'     => $nonce,
                ];
            }
            // taxonomy kuralı
            if ( isset( $affected_keys[ $pt ] ) && $affected_keys[ $pt ] === 'taxonomy' && $context === 'term' ) {
                $result[] = [
                    'id'        => $id,
                    'type'      => $type,
                    'context'   => $context,
                    'post_type' => $pt,
                    'url'       => $url,
                    'label'     => $label,
                    'count'     => $count,
                    'grouped'   => true,
                    'nonce'     => $nonce,
                ];
            }
        }

        return $result;
    }

    /**
     * Post type ve taxonomy listesini döndürür — Forced Plugins UI için.
     */
    public static function ajaxGetGroupsForTypes(): void {
        check_ajax_referer( 'sh_am_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized.' );

        $post_types = [];
        foreach ( get_post_types( [ 'public' => true ], 'objects' ) as $pt ) {
            $post_types[] = [ 'key' => $pt->name, 'label' => $pt->label . ' (' . $pt->name . ')' ];
        }

        $taxonomies = [];
        foreach ( get_taxonomies( [ 'public' => true ], 'objects' ) as $tax ) {
            $taxonomies[] = [ 'key' => $tax->name, 'label' => $tax->label . ' (' . $tax->name . ')' ];
        }

        $plugins = [];
        // Önce derlenmiş js_files_conditional.json dosyasını dene — daha hızlı
        $json_path = defined( 'STATIC_PATH' )
            ? rtrim( STATIC_PATH, '/' ) . '/js/js_files_conditional.json'
            : get_template_directory() . '/static/js/js_files_conditional.json';

        if ( file_exists( $json_path ) ) {
            $json = @file_get_contents( $json_path );
            $keys = $json ? json_decode( $json, true ) : null;
            if ( is_array( $keys ) ) {
                sort( $keys ); // alfabetik sıra
                foreach ( $keys as $key ) {
                    $plugins[] = [ 'key' => $key, 'label' => $key ];
                }
            }
        }

        // Fallback: compile_files_config (c:true olan conditional plugin'ler)
        if ( empty( $plugins ) ) {
            if ( ! function_exists( 'compile_files_config' ) ) {
                $minify_rules = defined( 'SH_INCLUDES_PATH' )
                    ? SH_INCLUDES_PATH . 'minify-rules.php'
                    : dirname( __DIR__, 3 ) . '/includes/minify-rules.php';
                if ( file_exists( $minify_rules ) ) require_once $minify_rules;
            }
            if ( function_exists( 'compile_files_config' ) ) {
                $cfg  = compile_files_config( true );
                $keys = [];
                foreach ( $cfg['js']['plugins'] ?? [] as $key => $def ) {
                    if ( ! empty( $def['c'] ) ) $keys[] = $key;
                }
                sort( $keys );
                foreach ( $keys as $key ) {
                    $plugins[] = [ 'key' => $key, 'label' => $key ];
                }
            }
        }

        wp_send_json_success( compact( 'post_types', 'taxonomies', 'plugins' ) );
    }

    public static function ajaxCompile(): void {
        check_ajax_referer( 'sh_am_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized.' );

        $is_local = function_exists( 'isLocalhost' ) ? isLocalhost() : false;
        if ( ! $is_local ) {
            wp_send_json_error( 'Compile işlemi sadece local development ortamında çalışır.' );
        }

        $step = sanitize_key( $_POST['step'] ?? 'scss' );
        $am   = \SaltHareket\AssetManager\AssetManager::getInstance();

        // updated_plugins step'ler arasında taşınır
        $updated_plugins = (array) ( $_POST['updated_plugins'] ?? [] );
        $updated_plugins = array_map( 'sanitize_text_field', $updated_plugins );

        $steps = [ 'scss', 'minify_and_fonts', 'webpack', 'pages', 'common_css' ];
        $step_labels = [
            'scss'             => 'SCSS derleniyor...',
            'minify_and_fonts' => 'JS/CSS minify + font relocation...',
            'webpack'          => 'Webpack çalışıyor...',
            'pages'            => 'Etkilenen sayfalar fetch ediliyor...',
            'common_css'       => 'Common CSS üretiliyor...',
        ];

        $current_index = array_search( $step, $steps, true );
        $progress      = (int) round( ( ( $current_index + 1 ) / count( $steps ) ) * 100 );
        $next_step     = $steps[ $current_index + 1 ] ?? null;
        $message       = '';
        $type          = 'success';

        // WP Rocket cache bust — her zaman ilk adımda
        if ( $step === 'scss' && function_exists( 'rocket_clean_minify' ) ) {
            rocket_clean_minify();
        }

        switch ( $step ) {
            case 'scss':
                if ( class_exists( 'ScssPhp\ScssPhp\Compiler' ) ) {
                    $errors = \SaltHareket\Theme::scss_compile();
                    if ( $errors ) {
                        $type    = 'error';
                        $message = 'SCSS Error: ' . esc_html( $errors[0]['message'] );
                        @file_put_contents( WP_CONTENT_DIR . '/compiler_error.log', $errors[0]['message'], FILE_APPEND );
                        wp_send_json_error( [ 'message' => $message, 'step' => $step ] );
                    }
                    $tz      = wp_timezone();
                    $now     = new \DateTime( 'now', $tz );
                    $message = 'SCSS compiled — ' . $now->format( 'H:i:s' );
                } else {
                    $type    = 'warning';
                    $message = 'ScssPhp not installed — skipped.';
                }
                break;

            case 'minify_and_fonts':
                $is_dev          = function_exists( 'isLocalhost' ) ? isLocalhost() : false;
                $minifier        = new \SaltHareket\AssetManager\SaltMinifier( false, $is_dev );
                $updated_plugins = $minifier->init() ?: [];
                // Aynı instance ile font relocation — yeni SaltMinifier oluşturma, plugins/ tekrar silinir!
                $css_dir = get_stylesheet_directory() . '/static/css/';
                $minifier->relocateFontFaces( $css_dir . 'font-faces.css' );
                $minifier->clearFontfaces( $css_dir . 'main-combined.css' );
                $minifier->clearFontfaces( $css_dir . 'main-combined-rtl.css' );
                $minifier->purge_page_assets_manifest();
                $message = empty( $updated_plugins )
                    ? 'JS/CSS minified, font faces relocated.'
                    : 'JS/CSS minified — updated: ' . implode( ', ', $updated_plugins ) . '. Font faces relocated.';
                break;

            case 'webpack':
                if ( class_exists( 'Symfony\Component\Process\Process' ) ) {
                    $working_dir  = get_stylesheet_directory();
                    $current_user = getenv( 'USERNAME' ) ?: getenv( 'USER' );
                    $process      = new \Symfony\Component\Process\Process(
                        [ 'npx', 'webpack', '--env', 'enable_ecommerce=false' ],
                        $working_dir
                    );
                    $process->setEnv( [ 'PATH' => getenv('PATH') . ';C:\Program Files\nodejs;C:\Users\\' . $current_user . '\AppData\Roaming\npm' ] );
                    $process->setTimeout( null );
                    try { $process->mustRun(); } catch ( \Exception $e ) { /* log */  }
                    foreach ( array_merge(
                        glob( $working_dir . '/static/css/*.js'  ) ?: [],
                        glob( $working_dir . '/static/css/*.txt' ) ?: []
                    ) as $f ) { @unlink($f); }
                    $message = 'Webpack completed.';
                } else {
                    $type    = 'warning';
                    $message = 'Symfony Process not available — webpack skipped.';
                }
                break;

            case 'pages':
                if ( ! empty( $updated_plugins ) ) {
                    if ( class_exists( '\\Update' ) && \Update::tasks_completed() ) {
                        $message = 'Plugins updated — manually re-fetch pages: ' . implode( ', ', $updated_plugins );
                        $type    = 'warning';
                    } else {
                        $result  = $am->refetch_affected_pages( $updated_plugins );
                        // refetch_affected_pages array veya ['affected'=>N,'fetched'=>N] döner
                        if ( is_array( $result ) && isset( $result['affected_count'] ) ) {
                            $affected = (int) $result['affected_count'];
                            $fetched  = (int) $result['fetched_count'];
                            if ( $affected === 0 ) {
                                $message = 'No pages affected — pages step skipped.';
                                $type    = 'info';
                            } elseif ( $fetched > 0 ) {
                                $message = $affected . ' pages affected, ' . $fetched . ' re-fetched.';
                            } else {
                                $message = $affected . ' pages affected, 0 re-fetched.';
                                $type    = 'warning';
                            }
                        } else {
                            // Eski format uyumluluğu
                            $count   = is_array( $result ) ? count( $result ) : 0;
                            $message = $count > 0 ? $count . ' pages re-fetched.' : 'No pages needed re-fetch.';
                        }
                    }
                } else {
                    $message = 'No plugin changes — pages step skipped.';
                    $type    = 'info';
                }
                break;

            case 'common_css':
                $am->compile_common_css();
                $message = 'Common CSS generated.';
                // Son compile zamanını kaydet
                if ( $next_step === null ) {
                    update_option( 'sh_last_compile_js_css', time(), false );
                }
                $compiled_at = date_i18n( 'd.m.Y H:i' );
                break;

            default:
                wp_send_json_error( 'Unknown step: ' . $step );
        }

        wp_send_json_success( [
            'step'            => $step,
            'label'           => $step_labels[ $step ] ?? $step,
            'message'         => $message,
            'type'            => $type,
            'next_step'       => $next_step,
            'next_label'      => $next_step ? ( $step_labels[ $next_step ] ?? $next_step ) : null,
            'progress'        => $progress,
            'updated_plugins' => $updated_plugins,
            'done'            => $next_step === null,
            'compiled_at'     => $compiled_at ?? null,
        ] );
    }

    public function _run_webpack_step(): void {
        if ( ! class_exists( 'Symfony\Component\Process\Process' ) ) return;
        $working_dir  = get_stylesheet_directory();
        $current_user = getenv( 'USERNAME' ) ?: getenv( 'USER' );
        $process      = new \Symfony\Component\Process\Process(
            [ 'npx', 'webpack', '--env', 'enable_ecommerce=false' ],
            $working_dir
        );
        $process->setEnv( [ 'PATH' => getenv('PATH') . ';C:\Program Files\nodejs;C:\Users\\' . $current_user . '\AppData\Roaming\npm' ] );
        $process->setTimeout( null );
        try { $process->mustRun(); } catch ( \Exception $e ) { /* hata compile sonucunda görünür */ }
        foreach ( array_merge( glob($working_dir.'/static/css/*.js')?: [], glob($working_dir.'/static/css/*.txt')?: [] ) as $f ) {
            @unlink($f);
        }
    }

    public static function ajaxExtractTranslations(): void {
        check_ajax_referer( 'sh_am_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized.' );

        $is_local = function_exists( 'isLocalhost' ) ? isLocalhost() : false;
        if ( ! $is_local ) {
            wp_send_json_error( 'Bu işlem sadece local development ortamında çalışır.' );
        }

        $result = \SaltHareket\AssetManager\AssetManager::getInstance()->extract_translations();
        update_option( 'sh_last_extract_translations', current_time( 'timestamp' ), false );

        wp_send_json_success( [
            'messages' => $result['messages'],
            'total'    => $result['total'],
            'success'  => $result['success'],
        ] );
    }

    public static function ajaxCompileMethods(): void {
        check_ajax_referer( 'sh_am_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized.' );

        $is_local = function_exists( 'isLocalhost' ) ? isLocalhost() : false;
        if ( ! $is_local ) {
            wp_send_json_error( 'Compile işlemi sadece local development ortamında çalışır.' );
        }

        $result = \SaltHareket\AssetManager\AssetManager::getInstance()->compile_methods( false );
        update_option( 'sh_last_compile_methods', time(), false );

        wp_send_json_success( [
            'messages' => $result['messages'],
            'success'  => $result['success'],
        ] );
    }

    public static function renderPage(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $tab      = sanitize_key( $_GET['tab'] ?? 'overview' );
        // Backward compat: eski cache tab URL'i
        if ( $tab === 'cache' ) {
            $tab = 'cache_settings';
        }
        $settings = AssetSettings::get();
        $nonce    = wp_create_nonce( 'sh_am_nonce' );
        $ajax_url = admin_url( 'admin-ajax.php' );
        $is_development  = ! empty( $settings['enable_development'] );
        ?>
        <div class="sh-wrap" id="sh-asset-manager-page">

        <div class="sh-toolbar" style="flex-wrap:wrap; align-items:revert;">
            <div style="display: flex;width: 100%;gap:10px;">
                <h1>⚡ Asset Manager</h1>
                <?php if ( $is_development ) : ?>
                    <span class="sh-badge sh-badge-gray">Development</span>
                <?php else : ?>
                    <span class="sh-badge sh-badge-blue">Production</span>
                <?php endif; ?>
                <?php if ( defined('ENABLE_ECOMMERCE') && ENABLE_ECOMMERCE ) : ?>
                    <span class="sh-badge sh-badge-gray">WooCommerce</span>
                <?php endif; ?>
                <div class="sh-toolbar-right">
                    <a href="?page=sh-asset-manager&tab=overview"      class="sh-tab-btn <?php echo $tab==='overview'     ?'active':''; ?>">Overview</a>
                    <a href="?page=sh-asset-manager&tab=settings"      class="sh-tab-btn <?php echo $tab==='settings'     ?'active':''; ?>">Settings</a>
                    <a href="?page=sh-asset-manager&tab=page_assets"   class="sh-tab-btn <?php echo $tab==='page_assets'  ?'active':''; ?>">Page Assets</a>
                    <a href="?page=sh-asset-manager&tab=filters"       class="sh-tab-btn <?php echo $tab==='filters'      ?'active':''; ?>">Filters</a>
                    <a href="?page=sh-asset-manager&tab=debug"         class="sh-tab-btn <?php echo $tab==='debug'        ?'active':''; ?>">Debug</a>
                    <a href="?page=sh-asset-manager&tab=media"         class="sh-tab-btn <?php echo $tab==='media'        ?'active':''; ?>">🖼️ Media</a>
                </div>
            </div>
            <div id="pae-progress-wrap" style="display:none;background:#fff;border-top:1px solid #e2e8f0;padding:8px 16px 10px;margin:-1px 0 0;width:100%;">
                <div id="pae-progress-label" style="font-size:11px;color:#475569;margin-bottom:4px;">Hazırlanıyor...</div>
                <div class="progress" role="progressbar" style="height:6px;">
                    <div id="pae-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" style="width:0%"></div>
                </div>
            </div>
            <?php // Progress bar — toolbar altinda tam genislikte ?>
        </div>


        <?php
        $titles = [
            'overview'      => [ 'title' => 'Asset Overview',    'desc' => 'Current asset loading state by mode and environment.' ],
            'settings'      => [ 'title' => 'Asset Settings',    'desc' => 'Configure Development mode, inline CSS, lazy loading and cleanup.' ],
            'page_assets'   => [ 'title' => '🗂️ Page Assets',    'desc' => 'Generate and manage per-page CSS/JS cache files.' ],
            'filters'       => [ 'title' => 'Filter Reference',  'desc' => 'All available filters for extending asset loading from outside.' ],
            'cache_settings'=> [ 'title' => 'Cache Management',  'desc' => 'Inline CSS cache, manifest and cache settings.' ],
            'debug'         => [ 'title' => 'Debug — Loaded Assets', 'desc' => 'All currently enqueued CSS and JS with details.' ],
            'media'         => [ 'title' => '🖼️ Media Optimizer', 'desc' => 'Convert unoptimized images to AVIF/WebP. Save bandwidth, boost Core Web Vitals.' ],
        ];
        $cur = $titles[$tab] ?? $titles['overview'];
        ?>
        <div class="sh-section-title" style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap">
            <div>
                <h2><?php echo esc_html($cur['title']); ?></h2>
                <p><?php echo esc_html($cur['desc']); ?></p>
            </div>
            <?php if ( $tab === 'page_assets' ) :
                $sub = sanitize_key( $_GET['sub'] ?? 'generate' );
                $base_url = '?page=sh-asset-manager&tab=page_assets';
            ?>
            <?php endif; ?>
        </div>

        <?php
        switch ( $tab ) {
            case 'settings':       self::renderSettingsTab( $settings, $nonce, $ajax_url ); break;
            case 'page_assets':    self::renderPageAssetsTab( $nonce, $ajax_url ); break;
            case 'filters':        self::renderFiltersTab(); break;
            case 'cache_settings': self::renderCacheTab( $nonce, $ajax_url ); break;
            case 'debug':          self::renderDebugTab(); break;
            case 'media':          MediaOptimizerAdmin::renderTab( $nonce, $ajax_url ); break;
            default:               self::renderOverviewTab( $settings, $is_development ); break;
        }
        ?>

        <div id="sh-toast"></div>
        </div>

        <script>
        (function($){
            var NONCE = <?php echo wp_json_encode( $nonce ); ?>;
            var AJAX  = <?php echo wp_json_encode( $ajax_url ); ?>;

            // ── Development mode toggle: Compile & Logs bölümlerini göster/gizle ──
            function shUpdateDevSections(isDev) {
                if (isDev) {
                    $('#sh-compile-section').show();
                    $('#sh-compile-methods-section').show();
                    $('#sh-logs-section').show();
                    $('#sh-enable-logs, #sh-enable-console-logs').prop('disabled', false);
                } else {
                    $('#sh-compile-section').hide();
                    $('#sh-compile-methods-section').hide();
                    $('#sh-logs-section').hide();
                    $('#sh-enable-logs, #sh-enable-console-logs').prop('disabled', true);
                }
            }

            // Sayfa açılırken mevcut durumu uygula
            shUpdateDevSections($('#sh-enable-development').is(':checked'));

            // Toggle değişince — is(':checked') ile her zaman gerçek durumu oku
            $('#sh-enable-development').on('change', function() {
                var isDev = $(this).is(':checked');
                shUpdateDevSections(isDev);
            });

            // General tab save
            $('#sh-am-save-general').on('click', function() {
                var $btn = $(this).prop('disabled', true).text('Saving...');
                var s = {
                    enable_development:  $('#sh-enable-development').is(':checked') ? 1 : 0,
                    enable_logs:         $('#sh-enable-logs').is(':checked') ? 1 : 0,
                    enable_console_logs: $('#sh-enable-console-logs').is(':checked') ? 1 : 0,
                };
                $.post(AJAX, { action: 'sh_am_save_settings', nonce: NONCE, settings: s }, function(res) {
                    $btn.prop('disabled', false).text('Save Settings');
                    if (res.success) {
                        $('#sh-am-save-general-msg').show().delay(2500).fadeOut();
                        shAmToast('Saved! Reloading...');
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        shAmToast(res.data || 'Error', true);
                    }
                });
            });

            // CSS & JS tab save
            $('#sh-am-save').on('click', function() {
                var $btn = $(this).prop('disabled', true).text('Saving...');
                var s = {
                    // Auto-Fetch
                    auto_fetch:                  $('#sh-auto-fetch').is(':checked') ? 1 : 0,
                    // Block CSS & JS
                    seperate_css:                $('#sh-seperate-css').is(':checked') ? 1 : 0,
                    inline_css:                  $('#sh-inline-css').is(':checked') ? 1 : 0,
                    seperate_js:                 $('#sh-seperate-js').is(':checked') ? 1 : 0,
                    inline_js:                   $('#sh-inline-js').is(':checked') ? 1 : 0,
                    // Cleanup & Performance
                    remove_global_styles:        $('select[name="remove_global_styles"]').val(),
                    remove_block_styles:         $('select[name="remove_block_styles"]').val(),
                    remove_classic_theme_styles: $('#sh-remove-classic').is(':checked') ? 1 : 0,
                    remove_woocommerce_styles:   $('#sh-remove-woo').is(':checked') ? 1 : 0,
                    block_google_fonts:          $('#sh-block-gfonts').is(':checked') ? 1 : 0,
                    css_lazy_load:               $('#sh-css-lazy').is(':checked') ? 1 : 0,
                    js_defer:                    $('#sh-js-defer').is(':checked') ? 1 : 0,
                };
                $.post(AJAX, { action: 'sh_am_save_settings', nonce: NONCE, settings: s }, function(res) {
                    $btn.prop('disabled', false).text('Save Settings');
                    if (res.success) {
                        $('#sh-am-save-msg').show().delay(2500).fadeOut();
                        shAmToast('Settings saved!');
                    } else {
                        shAmToast(res.data || 'Error', true);
                    }
                });
            });

            // Auto-Fetch save — auto_fetch artık CSS & JS tab'ının ana Save Settings butonu ile kaydediliyor

            // Twig Cache save
            $('#sh-am-save-twig-cache').on('click', function() {
                var $btn = $(this).prop('disabled', true).text('Saving...');
                $.post(AJAX, { action: 'sh_am_save_settings', nonce: NONCE, settings: { enable_twig_cache: $('#sh-enable-twig-cache').is(':checked') ? 1 : 0 } }, function(res) {
                    $btn.prop('disabled', false).text('Save');
                    if (res.success) { $('#sh-am-twig-cache-msg').show().delay(2500).fadeOut(); shAmToast('Saved!'); }
                    else shAmToast(res.data || 'Error', true);
                });
            });

            // Purge Selected — seçim zorunluluğu
            function shUpdatePurgeBtn() {
                var any = $('.sh-purge-check:not(:disabled)').is(':checked');
                $('#sh-am-purge-selected').prop('disabled', !any).css({ opacity: any ? 1 : 0.5, cursor: any ? 'pointer' : 'not-allowed' });
                $('#sh-purge-hint').toggle(!any);
            }
            $('.sh-purge-check').on('change', shUpdatePurgeBtn);
            shUpdatePurgeBtn();

            $('#sh-am-purge-selected').on('click', function() {
                var $btn = $(this).prop('disabled', true).text('Purging...');
                var types = [];
                if ($('#sh-purge-query').is(':checked'))  types.push('query');
                if ($('#sh-purge-twig').is(':checked'))   types.push('twig');
                if ($('#sh-purge-opcache').is(':checked') && !$('#sh-purge-opcache').is(':disabled')) types.push('opcache');
                if ($('#sh-purge-rocket').is(':checked')  && !$('#sh-purge-rocket').is(':disabled'))  types.push('wp_rocket');
                if (!types.length) { shUpdatePurgeBtn(); return; }
                $.post(AJAX, { action: 'sh_am_purge_selected', nonce: NONCE, types: types }, function(res) {
                    shUpdatePurgeBtn();
                    $('#sh-am-purge-msg').show().delay(3000).fadeOut();
                }).fail(function() { shUpdatePurgeBtn(); shAmToast('AJAX hatası', true); });
            });

            // Clear Twig Cache
            $('#sh-am-clear-twig').on('click', function() {
                var $btn = $(this).prop('disabled', true).text('...');
                $.post(AJAX, { action: 'sh_am_purge_selected', nonce: NONCE, types: ['twig'] }, function(res) {
                    $btn.prop('disabled', false).text('Clear Cache');
                    if (res.success) { $('#sh-clear-twig-msg').show().delay(2500).fadeOut(); }
                    else { shAmToast(res.data || 'Hata', true); }
                }).fail(function() { $btn.prop('disabled', false).text('Clear Cache'); shAmToast('AJAX hatası', true); });
            });

            // Clear Query Cache
            $('#sh-am-clear-qc').on('click', function() {
                var $btn = $(this).prop('disabled', true).text('...');
                $.post(AJAX, { action: 'sh_am_purge_selected', nonce: NONCE, types: ['query'] }, function(res) {
                    $btn.prop('disabled', false).text('Clear Cache');
                    if (res.success) { $('#sh-clear-qc-msg').show().delay(2500).fadeOut(); }
                    else { shAmToast(res.data || 'Hata', true); }
                }).fail(function() { $btn.prop('disabled', false).text('Clear Cache'); shAmToast('AJAX hatası', true); });
            });

            // Clear OPcache
            $('#sh-am-clear-opcache').on('click', function() {
                var $btn = $(this).prop('disabled', true).text('...');
                $.post(AJAX, { action: 'sh_am_purge_selected', nonce: NONCE, types: ['opcache'] }, function(res) {
                    $btn.prop('disabled', false).text('Clear Cache');
                    if (res.success) { $('#sh-clear-opcache-msg').show().delay(2500).fadeOut(); shAmToast('OPcache temizlendi!'); }
                    else { shAmToast(res.data || 'Hata', true); }
                }).fail(function() { $btn.prop('disabled', false).text('Clear Cache'); shAmToast('AJAX hatası', true); });
            });

            $('#sh-am-purge').on('click', function() {
                var $btn = $(this).prop('disabled', true).text('Clearing...');
                $.post(AJAX, { action: 'sh_am_purge_cache', nonce: NONCE }, function(res) {
                    $btn.prop('disabled', false).text('Clear Cache');
                    if (res.success) shAmToast('Cache cleared!');
                    else shAmToast(res.data || 'Error', true);
                });
            });

            // WP Rocket Exclusions save
            $('#sh-am-save-exclusions').on('click', function() {
                var $btn = $(this).prop('disabled', true).text('Saving...');
                var data = {
                    action:  'sh_am_save_exclusions',
                    nonce:   NONCE,
                    exclude_post_types_from_cache: $('#sh-exclude-post-types-archive').val() || [],
                    exclude_posts_from_cache:      $('#sh-exclude-posts').val() || [],
                    exclude_taxonomies_from_cache: $('#sh-exclude-taxonomies').val() || [],
                };
                $.post(AJAX, data, function(res) {
                    $btn.prop('disabled', false).text('Save Exclusions');
                    if (res.success) {
                        $('#sh-am-excl-msg').show().delay(2500).fadeOut();
                        shAmToast('Exclusions saved!');
                    } else {
                        shAmToast(res.data || 'Error', true);
                    }
                });
            });

            // ── Cache Manifest Repeater ───────────────────────────────────────
            var manifestRowCount = $('#sh-manifest-rows .sh-manifest-row').length;

            function initManifestRow($row) {
                // sh-select init for pages & terms
                $row.find('[data-sh-select]').each(function() {
                    if (typeof window.shSelect !== 'undefined') {
                        window.shSelect.init(this, { placeholder: $(this).data('placeholder') || 'Ara ve seç...' });
                    }
                });

                // Type toggle → post_type / taxonomy
                $row.find('.sh-manifest-type').off('change').on('change', function() {
                    var type = $(this).val();
                    if (type === 'post_type') {
                        $row.find('.sh-manifest-pt-select').show();
                        $row.find('.sh-manifest-tax-select').hide();
                        $row.find('.sh-manifest-pt-label').text('Post Type');
                        $row.find('.sh-manifest-archive-wrap').show();
                    } else {
                        $row.find('.sh-manifest-pt-select').hide();
                        $row.find('.sh-manifest-tax-select').show();
                        $row.find('.sh-manifest-pt-label').text('Taxonomy');
                        $row.find('.sh-manifest-archive-wrap').hide();
                    }
                });

                // Remove row
                $row.find('.sh-manifest-remove').off('click').on('click', function() {
                    $row.fadeOut(200, function() {
                        $row.remove();
                        if ($('#sh-manifest-rows .sh-manifest-row').length === 0) {
                            $('#sh-manifest-empty').show();
                        }
                    });
                });
            }

            // Init existing rows on page load
            $('#sh-manifest-rows .sh-manifest-row').each(function() {
                initManifestRow($(this));
            });

            // Add new row from template
            $('#sh-manifest-add-row').on('click', function() {
                // Template'i clone et — shSelect artefact'larını temizle
                var $tpl = $('#sh-manifest-row-tpl .sh-manifest-row').clone();
                // Clone'daki sh-select-container'ları kaldır (auto-init artık yapmıyor ama güvenlik için)
                $tpl.find('.sh-select-container').remove();
                $tpl.find('[data-sh-select]').each(function() {
                    $(this).removeData('sh-select-init').show();
                });
                $tpl.attr('data-index', manifestRowCount++);
                $('#sh-manifest-empty').hide();
                $('#sh-manifest-rows').append($tpl);
                initManifestRow($tpl);
                $tpl.hide().fadeIn(200);
            });

            // Collect all manifest rows to array
            function collectManifest() {
                var rows = [];
                $('#sh-manifest-rows .sh-manifest-row').each(function() {
                    var $r   = $(this);
                    var type = $r.find('.sh-manifest-type').val();
                    var slug = type === 'post_type'
                        ? $r.find('.sh-manifest-pt-select').val()
                        : $r.find('.sh-manifest-tax-select').val();
                    if (!slug) return;

                    var pages = [], terms = [];
                    $r.find('.sh-manifest-pages option:selected').each(function() { pages.push($(this).val()); });
                    $r.find('.sh-manifest-terms option:selected').each(function() { terms.push($(this).val()); });

                    var entry = {
                        type:    type,
                        self:    $r.find('.sh-manifest-self').is(':checked') ? 1 : 0,
                        home:    $r.find('.sh-manifest-home').is(':checked') ? 1 : 0,
                        archive: $r.find('.sh-manifest-archive').is(':checked') ? 1 : 0,
                        pages:   pages,
                        terms:   terms
                    };
                    entry[type] = slug;
                    rows.push(entry);
                });
                return rows;
            }

            // Save manifest
            $('#sh-am-save-manifest').on('click', function() {
                var $btn = $(this).prop('disabled', true).text('Kaydediliyor...');
                $.post(AJAX, {
                    action:   'sh_am_save_cache_manifest',
                    nonce:    NONCE,
                    manifest: collectManifest()
                }, function(res) {
                    $btn.prop('disabled', false).text('💾 Manifest Kaydet');
                    if (res.success) {
                        $('#sh-am-manifest-msg').text('✓ ' + res.data.message).show().delay(3000).fadeOut();
                        shAmToast(res.data.message);
                    } else {
                        shAmToast(res.data || 'Hata', true);
                    }
                }).fail(function() {
                    $btn.prop('disabled', false).text('💾 Manifest Kaydet');
                    shAmToast('AJAX hatası', true);
                });
            });

            // ── Query Cache toggle — types sadece aktifken görünür ──────────
            $('#sh-qc-enabled').on('change', function() {
                $('#sh-qc-types-wrap').toggle($(this).is(':checked'));
            });

            // ── Query Cache save ──────────────────────────────────────────────
            $('#sh-am-save-qc').on('click', function() {
                var $btn = $(this).prop('disabled', true).text('Kaydediliyor...');
                var isEnabled = $('#sh-qc-enabled').is(':checked');
                var types = isEnabled ? ($('#sh-qc-types').val() || []) : [];
                $.post(AJAX, {
                    action:              'sh_am_save_query_cache',
                    nonce:               NONCE,
                    enable_object_cache: isEnabled ? 1 : 0,
                    object_cache_types:  types
                }, function(res) {
                    $btn.prop('disabled', false).text('💾 Query Cache Kaydet');
                    if (res.success) {
                        $('#sh-am-qc-msg').show().delay(2500).fadeOut();
                        shAmToast(res.data.message || 'Query Cache kaydedildi!');
                    } else {
                        shAmToast(res.data || 'Hata', true);
                    }
                }).fail(function() {
                    $btn.prop('disabled', false).text('💾 Query Cache Kaydet');
                    shAmToast('AJAX hatası', true);
                });
            });

            // SH Select init — tab switcher'a taşındı (renderSettingsTab içinde)
            // Hidden tab içindeyken init olmayan select'ler tab tıklanınca init edilir

            // Copy button — data-code attribute'undan oku
            $(document).on('click', '.sh-am-copy-btn', function() {
                var code = $(this).data('code');
                var $btn = $(this);
                navigator.clipboard.writeText(code).then(function() {
                    $btn.text('✓');
                    setTimeout(function() { $btn.text('Copy'); }, 1500);
                });
            });

            // ── Compile JS & CSS ──────────────────────────────────────────────
            $('#sh-am-compile').on('click', function() {
                var $btn = $(this).prop('disabled', true).text('Derleniyor...');
                $('#sh-compile-result').hide();
                $('#sh-compile-log').empty().show();

                // Sticky header progress bar'ı aktif et (PAE ile aynı)
                $('#pae-progress-wrap').show();
                $('#pae-progress-bar').css('width', '0%');
                $('#pae-progress-label').text('Compile başlatılıyor...');

                var steps = ['scss', 'minify_and_fonts', 'webpack', 'pages', 'common_css'];
                var stepLabels = {
                    scss:            '1/5 — SCSS derleniyor...',
                    minify_and_fonts:'2/5 — JS/CSS minify + font relocation...',
                    webpack:         '3/5 — Webpack çalışıyor...',
                    pages:           '4/5 — Etkilenen sayfalar fetch ediliyor...',
                    common_css:      '5/5 — Common CSS üretiliyor...'
                };
                var totalSteps     = steps.length;
                var updatedPlugins = [];
                var logLines       = [];

                function appendLog(msg, type) {
                    var color = type === 'error' ? '#ef4444' : type === 'warning' ? '#f59e0b' : '#22c55e';
                    logLines.push('<span style="color:' + color + '">' + msg + '</span>');
                    $('#sh-compile-log').html(logLines.join('<br>')).scrollTop(99999);
                }

                function runStep(step) {
                    var idx = steps.indexOf(step);
                    var pct = Math.round(((idx) / totalSteps) * 100);
                    $('#pae-progress-bar').css('width', pct + '%');
                    $('#pae-progress-label').text(stepLabels[step] || step);

                    $.post(AJAX, {
                        action: 'sh_am_compile',
                        nonce:  NONCE,
                        step:   step,
                        updated_plugins: updatedPlugins
                    }, function(res) {
                        if (!res.success) {
                            appendLog('✗ ' + (res.data.message || res.data || 'Hata'), 'error');
                            $('#pae-progress-bar').css('width', '100%').addClass('bg-danger').removeClass('bg-success progress-bar-animated progress-bar-striped');
                            $('#pae-progress-label').text('Hata — ' + step);
                            showResult([{ type: 'error', text: res.data.message || res.data || 'Compile hatası' }]);
                            $btn.prop('disabled', false).text('⚙ Compile');
                            return;
                        }
                        var d = res.data;
                        if (d.updated_plugins && d.updated_plugins.length) {
                            updatedPlugins = d.updated_plugins;
                        }
                        appendLog((d.type === 'error' ? '✗' : d.type === 'warning' ? '⚠' : '✓') + ' ' + d.label + ': ' + d.message, d.type);

                        if (d.done || !d.next_step) {
                            // Tamamlandı — log'a yeşil completed satırı ekle
                            appendLog('✅ Compile tamamlandı!' + (d.compiled_at ? ' — ' + d.compiled_at : ''), 'success');
                            $('#pae-progress-bar').css('width', '100%').removeClass('progress-bar-animated progress-bar-striped');
                            $('#pae-progress-label').text('✓ Compile tamamlandı!');
                            // Tarih bilgisini başlığa yaz
                            if (d.compiled_at) {
                                $('#sh-compile-section strong').first().next('span').remove();
                                $('#sh-compile-section strong').first().after('<span style="font-size:11px;color:#9ca3af"> — Son: ' + d.compiled_at + '</span>');
                            }
                            setTimeout(function() {
                                $('#pae-progress-wrap').hide();
                                $('#pae-progress-bar').css('width','0%').addClass('progress-bar-animated progress-bar-striped').removeClass('bg-danger');
                            }, 2500);
                            showResult(logLines.length ? null : [{ type: 'success', text: 'Compile tamamlandı.' }]);
                            $btn.prop('disabled', false).text('⚙ Compile');
                        } else {
                            runStep(d.next_step);
                        }
                    }).fail(function() {
                        appendLog('✗ AJAX hatası — sunucu yanıt vermedi.', 'error');
                        $('#pae-progress-wrap').hide();
                        showResult([{ type: 'error', text: 'AJAX hatası.' }]);
                        $btn.prop('disabled', false).text('⚙ Compile');
                    });
                }

                function showResult(messages) {
                    if (!messages) { $('#sh-compile-result').hide(); return; }
                    var html = messages.map(function(m) {
                        var bg  = m.type === 'error' ? '#fef2f2' : m.type === 'warning' ? '#fffbeb' : '#f0fdf4';
                        var bd  = m.type === 'error' ? '#fecaca' : m.type === 'warning' ? '#fde68a' : '#86efac';
                        var cl  = m.type === 'error' ? '#dc2626' : m.type === 'warning' ? '#92400e' : '#15803d';
                        return '<div style="background:' + bg + ';border:1px solid ' + bd + ';border-radius:6px;padding:8px 28px 8px 12px;font-size:12px;color:' + cl + ';margin-bottom:4px;position:relative">'
                            + '<button onclick="$(this).closest(\'div\').remove()" style="position:absolute;right:6px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:' + cl + ';font-size:14px;padding:0;line-height:1">×</button>'
                            + m.text + '</div>';
                    }).join('');
                    $('#sh-compile-result').html(html).show();
                }

                runStep(steps[0]);
            });

            // ── Extract Translations ──────────────────────────────────────────
            $('#sh-am-extract-translations').on('click', function() {
                var $btn = $(this).prop('disabled', true).text('Taranıyor...');
                $('#sh-extract-translations-result').hide();
                $('#pae-progress-wrap').show();
                $('#pae-progress-bar').css('width', '40%');
                $('#pae-progress-label').text('Tema dosyaları taranıyor...');

                $.post(AJAX, { action: 'sh_am_extract_translations', nonce: NONCE }, function(res) {
                    $('#pae-progress-bar').css('width', '100%').removeClass('progress-bar-animated progress-bar-striped');
                    $('#pae-progress-label').text(res.success ? '✓ Translations extracted!' : '✗ Hata');
                    setTimeout(function() {
                        $('#pae-progress-wrap').hide();
                        $('#pae-progress-bar').css('width','0%').addClass('progress-bar-animated progress-bar-striped');
                    }, 2000);

                    var messages = res.success ? (res.data.messages || []) : [{ type: 'error', text: res.data || 'Hata' }];
                    if (!messages.length) messages = [{ type: 'success', text: 'Translations extracted.' }];
                    var html = messages.map(function(m) {
                        var bg = m.type==='error'?'#fef2f2':m.type==='warning'?'#fffbeb':'#f0fdf4';
                        var bd = m.type==='error'?'#fecaca':m.type==='warning'?'#fde68a':'#86efac';
                        var cl = m.type==='error'?'#dc2626':m.type==='warning'?'#92400e':'#15803d';
                        return '<div style="background:'+bg+';border:1px solid '+bd+';border-radius:6px;padding:8px 28px 8px 12px;font-size:12px;color:'+cl+';margin-bottom:4px;position:relative">'
                            + '<button onclick="$(this).closest(\'div\').remove()" style="position:absolute;right:6px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:'+cl+';font-size:14px;padding:0;line-height:1">×</button>'
                            + m.text + '</div>';
                    }).join('');
                    // Tarih güncelle
                    if (res.success) {
                        var now = new Date();
                        var dateStr = now.getDate().toString().padStart(2,'0') + '.' + (now.getMonth()+1).toString().padStart(2,'0') + '.' + now.getFullYear() + ' ' + now.getHours().toString().padStart(2,'0') + ':' + now.getMinutes().toString().padStart(2,'0');
                        $('#sh-extract-translations-section strong').first().next('span').remove();
                        $('#sh-extract-translations-section strong').first().after('<span style="font-size:11px;color:#9ca3af"> — Son: ' + dateStr + '</span>');
                    }
                    $('#sh-extract-translations-result').html(html).show();
                    $btn.prop('disabled', false).text('⚙ Extract');
                }).fail(function() {
                    $('#pae-progress-wrap').hide();
                    $('#sh-extract-translations-result').html('<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:6px;padding:8px 12px;font-size:12px;color:#dc2626">AJAX hatası.</div>').show();
                    $btn.prop('disabled', false).text('⚙ Extract');
                });
            });
            $('#sh-seperate-css').on('change', function() {                var checked = $(this).is(':checked');
                $('#sh-inline-css-row').toggle(checked);
                $('#sh-inline-css').prop('disabled', !checked);
                if (!checked) $('#sh-inline-css').prop('checked', false);
            });
            $('#sh-seperate-js').on('change', function() {
                var checked = $(this).is(':checked');
                $('#sh-inline-js-row').toggle(checked);
                $('#sh-inline-js').prop('disabled', !checked);
                if (!checked) $('#sh-inline-js').prop('checked', false);
            });

            // ── Compile Methods ───────────────────────────────────────────────
            $('#sh-am-compile-methods').on('click', function() {
                var $btn = $(this).prop('disabled', true).text('Derleniyor...');
                $('#sh-compile-methods-result').hide();
                $('#pae-progress-wrap').show();
                $('#pae-progress-bar').css('width', '30%');
                $('#pae-progress-label').text('Frontend/Backend methods derleniyor...');

                $.post(AJAX, { action: 'sh_am_compile_methods', nonce: NONCE }, function(res) {
                    $('#pae-progress-bar').css('width', '100%').removeClass('progress-bar-animated progress-bar-striped');
                    $('#pae-progress-label').text(res.success ? '✓ Methods compiled!' : '✗ Hata');
                    setTimeout(function() {
                        $('#pae-progress-wrap').hide();
                        $('#pae-progress-bar').css('width','0%').addClass('progress-bar-animated progress-bar-striped');
                    }, 2000);

                    var messages = res.success ? (res.data.messages || []) : [{ type: 'error', text: res.data || 'Hata' }];
                    if (!messages.length) messages = [{ type: 'success', text: 'Methods compiled successfully.' }];
                    var html = messages.map(function(m) {
                        var bg = m.type==='error'?'#fef2f2':m.type==='warning'?'#fffbeb':'#f0fdf4';
                        var bd = m.type==='error'?'#fecaca':m.type==='warning'?'#fde68a':'#86efac';
                        var cl = m.type==='error'?'#dc2626':m.type==='warning'?'#92400e':'#15803d';
                        return '<div style="background:'+bg+';border:1px solid '+bd+';border-radius:6px;padding:8px 28px 8px 12px;font-size:12px;color:'+cl+';margin-bottom:4px;position:relative">'
                            + '<button onclick="$(this).closest(\'div\').remove()" style="position:absolute;right:6px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:'+cl+';font-size:14px;padding:0;line-height:1">×</button>'
                            + m.text + '</div>';
                    }).join('');
                    $('#sh-compile-methods-result').html(html).show();
                    $btn.prop('disabled', false).text('⚙ Compile Methods');
                }).fail(function() {
                    $('#pae-progress-wrap').hide();
                    $('#sh-compile-methods-result').html('<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:6px;padding:8px 12px;font-size:12px;color:#dc2626">AJAX hatası.</div>').show();
                    $btn.prop('disabled', false).text('⚙ Compile Methods');
                });
            });

            // ── Translations save ─────────────────────────────────────────
            $('#sh-am-save-translations').on('click', function() {
                var $btn = $(this).prop('disabled', true).text('Kaydediliyor...');
                $.post(AJAX, {
                    action:                       'sh_am_save_translations',
                    nonce:                        NONCE,
                    untranslatable_taxonomies:    $('#sh-tr-untrans-tax').val() || [],
                    untranslatable_post_types:    $('#sh-tr-untrans-pt').val()  || [],
                    synchronized_pages:           $('#sh-tr-sync-pages').val()  || [],
                    sync_taxonomies_to_language:  $('#sh-tr-sync-tax').val()    || [],
                }, function(res) {
                    $btn.prop('disabled', false).text('💾 Save');
                    if (res.success) {
                        $('#sh-am-translations-msg').show().delay(2500).fadeOut();
                        shAmToast(res.data || 'Translations ayarları kaydedildi!');
                    } else {
                        shAmToast(res.data || 'Hata', true);
                    }
                }).fail(function() {
                    $btn.prop('disabled', false).text('💾 Save');
                    shAmToast('AJAX hatası', true);
                });
            });

            // ── Breadcrumb repeater ───────────────────────────────────────
            $('#sh-bc-add-row').on('click', function() {
                var $row = $('#sh-bc-row-tpl .sh-bc-row').clone();
                $row.find('.sh-bc-remove').on('click', function() { $(this).closest('.sh-bc-row').remove(); });
                $('#sh-bc-rows').append($row);
            });
            $(document).on('click', '.sh-bc-remove', function() {
                $(this).closest('.sh-bc-row').remove();
            });

            // ── Breadcrumb save ───────────────────────────────────────────
            $('#sh-am-save-bc').on('click', function() {
                var $btn = $(this).prop('disabled', true).text('Kaydediliyor...');
                var rows = [];
                $('#sh-bc-rows .sh-bc-row').each(function() {
                    var pt   = $(this).find('.sh-bc-pt').val();
                    var page = $(this).find('.sh-bc-page').val();
                    if (pt && page) rows.push({ post_type: pt, page: page });
                });
                // Nested array için $.ajax + traditional:false
                $.ajax({
                    url:  AJAX,
                    type: 'POST',
                    data: {
                        action:                          'sh_am_save_breadcrumb',
                        nonce:                           NONCE,
                        breadcrumb_remove_home:          $('#sh-bc-remove-home').is(':checked')    ? 1 : 0,
                        breadcrumb_remove_current:       $('#sh-bc-remove-current').is(':checked') ? 1 : 0,
                        breadcrumb_from_menu:            $('#sh-bc-from-menu').is(':checked')      ? 1 : 0,
                        breadcrumb_add_product_brand:    $('#sh-bc-add-brand').is(':checked')      ? 1 : 0,
                        breadcrumb_add_product_taxonomy: $('#sh-bc-add-tax').is(':checked')        ? 1 : 0,
                        breadcrumb_add_page_path:        rows,
                    },
                    traditional: false,
                    success: function(res) {
                        $btn.prop('disabled', false).text('💾 Save');
                        if (res.success) {
                            $('#sh-am-bc-msg').show().delay(2500).fadeOut();
                            shAmToast(res.data || 'Breadcrumb ayarları kaydedildi!');
                        } else {
                            shAmToast(res.data || 'Hata', true);
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).text('💾 Save');
                        shAmToast('AJAX hatası', true);
                    }
                });
            });

            // ── WooCommerce API toggle ──────────────────────────────────
            $('#sh-woo-enable-api').on('change', function() {
                var checked = $(this).is(':checked');
                $('#sh-woo-api-wrap').toggle(checked);
            });

            // ── WooCommerce save ─────────────────────────────────────────
            $('#sh-am-save-woo').on('click', function() {
                var $btn = $(this).prop('disabled', true).text('Kaydediliyor...');
                $.post(AJAX, {
                    action:               'sh_am_save_woo',
                    nonce:                NONCE,
                    enable_cart:          $('#sh-woo-enable-cart').is(':checked') ? 1 : 0,
                    enable_woo_api:       $('#sh-woo-enable-api').is(':checked') ? 1 : 0,
                    woo_api_local_client: $('#sh-woo-api-local-client').val(),
                    woo_api_local_secret: $('#sh-woo-api-local-secret').val(),
                    woo_api_live_client:  $('#sh-woo-api-live-client').val(),
                    woo_api_live_secret:  $('#sh-woo-api-live-secret').val(),
                }, function(res) {
                    $btn.prop('disabled', false).text('💾 Save');
                    if (res.success) {
                        $('#sh-am-woo-msg').show().delay(2500).fadeOut();
                        shAmToast(res.data || 'WooCommerce ayarları kaydedildi!');
                    } else {
                        shAmToast(res.data || 'Hata', true);
                    }
                }).fail(function() {
                    $btn.prop('disabled', false).text('💾 Save');
                    shAmToast('AJAX hatası', true);
                });
            });

            function shAmToast(msg, isError) {
                var el = document.getElementById('sh-toast');
                if (!el) return;
                var item = document.createElement('div');
                item.className = 'sh-toast-item' + (isError ? ' sh-toast-error' : ' sh-toast-success');
                item.textContent = msg;
                el.appendChild(item);
                setTimeout(function() { item.remove(); }, 3500);
            }

            // ── Forced Plugins ───────────────────────────────────────────
            (function() {
                var FP_NONCE = <?php echo wp_json_encode( $nonce ); ?>;
                var FP_AJAX  = <?php echo wp_json_encode( $ajax_url ); ?>;

                var allPostTypes = [];
                var allTaxonomies = [];
                var allPlugins = [];
                var dataLoaded = false;

                // Veriyi lazy yükle — tab ilk açılınca
                function loadFpData(cb) {
                    if (dataLoaded) { if(cb) cb(); return; }
                    $.post(FP_AJAX, { action: 'sh_am_get_groups_for_types', nonce: FP_NONCE }, function(res) {
                        if (res.success) {
                            allPostTypes  = res.data.post_types;
                            allTaxonomies = res.data.taxonomies;
                            allPlugins    = res.data.plugins;
                            dataLoaded    = true;
                            // Mevcut satırlardaki select'leri doldur
                            $('#sh-fp-rows .sh-fp-row').each(function() { fillFpRow($(this)); });
                            if (cb) cb();
                        }
                    });
                }

                // Bir row'daki key ve plugin select'lerini doldur
                function fillFpRow($row) {
                    var type    = $row.find('.sh-fp-type').val();
                    var curKey  = $row.find('.sh-fp-key').data('current') || $row.find('.sh-fp-key').val();
                    var curPlugins = [];
                    // Önce native select'in mevcut val()'ını oku (reload sonrası seçili olanlar)
                    var $plCur = $row.find('.sh-fp-plugins');
                    var existingVals = $plCur.val() || [];
                    if (Array.isArray(existingVals) && existingVals.length) {
                        curPlugins = existingVals;
                    } else {
                        // Fallback: data-selected attribute'undan oku (PHP'den gelen başlangıç değeri)
                        try { curPlugins = JSON.parse($plCur.attr('data-selected') || '[]'); } catch(e) {}
                    }

                    // Diğer satırlarda seçili key'leri topla — duplicate önle
                    var usedKeys = [];
                    $('#sh-fp-rows .sh-fp-row').not($row).each(function() {
                        var t = $(this).find('.sh-fp-type').val();
                        var k = $(this).find('.sh-fp-key').val();
                        if (t === type && k) usedKeys.push(k);
                    });

                    // Key select
                    var keyOpts = type === 'post_type' ? allPostTypes : allTaxonomies;
                    var $keyEl = $row.find('.sh-fp-key');
                    $keyEl.empty();
                    $keyEl.append('<option value="">— Seç —</option>');
                    keyOpts.forEach(function(o) {
                        var sel = o.key === curKey ? ' selected' : '';
                        var dis = (usedKeys.indexOf(o.key) !== -1 && o.key !== curKey) ? ' disabled' : '';
                        $keyEl.append('<option value="'+o.key+'"'+sel+dis+'>'+o.label+'</option>');
                    });
                    $keyEl.data('current', curKey);

                    // Plugin multiselect — sh-select ile
                    var $plEl = $row.find('.sh-fp-plugins');

                    // Önce mevcut sh-select instance'ını sıfırla
                    if ($plEl.data('sh-select-init')) {
                        $plEl.removeData('sh-select-init');
                        $plEl.show();
                        $plEl.next('.sh-select-container').remove();
                    }

                    // Option'ları yeniden doldur
                    $plEl.empty();
                    allPlugins.forEach(function(p) {
                        var sel = curPlugins.indexOf(p.key) !== -1 ? ' selected' : '';
                        $plEl.append('<option value="'+p.key+'"'+sel+'>'+p.key+'</option>');
                    });

                    // sh-select init
                    if (typeof window.shSelect !== 'undefined') {
                        window.shSelect.init($plEl[0], { placeholder: 'Plugin seç...' });
                    }
                }

                // Tab açılınca yükle — CSS&JS tab'ı açılınca forced plugins verilerini hazırla
                $(document).on('click', '.sh-stab-btn[data-tab="css_js"]', function() {
                    loadFpData();
                });

                // Sayfa zaten css_js tabındaysa hemen yükle
                if (<?php echo wp_json_encode( ( sanitize_key( $_GET['tab'] ?? '' ) === 'css_js' ) ); ?>) {
                    $(document).ready(function() { loadFpData(); });
                }

                // Tip değişince key select'i yenile
                $(document).on('change', '#sh-fp-rows .sh-fp-type', function() {
                    var $row = $(this).closest('.sh-fp-row');
                    $row.find('.sh-fp-key').data('current', '');
                    if (dataLoaded) fillFpRow($row);
                });

                // Key değişince diğer satırlardaki disable durumunu güncelle
                $(document).on('change', '#sh-fp-rows .sh-fp-key', function() {
                    if (!dataLoaded) return;
                    var $changedRow = $(this).closest('.sh-fp-row');
                    $changedRow.find('.sh-fp-key').data('current', $(this).val());
                    $('#sh-fp-rows .sh-fp-row').not($changedRow).each(function() {
                        fillFpRow($(this));
                    });
                });

                // Satır kaldır
                $(document).on('click', '#sh-fp-rows .sh-fp-remove', function() {
                    $(this).closest('.sh-fp-row').remove();
                });

                // Satır ekle
                $('#sh-fp-add').on('click', function() {
                    var idx = $('#sh-fp-rows .sh-fp-row').length;
                    var $row = $('<div class="sh-fp-row sh-card-inset" data-index="'+idx+'" style="margin-bottom:8px">' +
                        '<div style="display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap">' +
                        '<div style="min-width:130px"><label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Tip</label>' +
                        '<select class="sh-fp-type sh-input" style="min-width:130px">' +
                        '<option value="post_type">Post Type</option><option value="taxonomy">Taxonomy</option></select></div>' +
                        '<div style="flex:1;min-width:180px"><label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Post Type / Taxonomy</label>' +
                        '<select class="sh-fp-key sh-input sh-w-full"><option value="">— Seç —</option></select></div>' +
                        '<div style="flex:2;min-width:220px"><label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Zorunlu Plugin\'ler</label>' +
                        '<select class="sh-fp-plugins sh-input sh-w-full" multiple data-sh-select data-placeholder="Plugin seç..." data-selected="[]"><option value="">Yükleniyor...</option></select></div>' +
                        '<button type="button" class="sh-fp-remove sh-btn sh-btn-danger sh-btn-sm" style="padding:5px 10px;flex-shrink:0;margin-bottom:2px">✖</button>' +
                        '</div></div>');
                    $('#sh-fp-rows').append($row);
                    if (dataLoaded) fillFpRow($row);
                });

                // Kaydet & Fetch
                $('#sh-fp-save').on('click', function() {
                    var $btn = $(this).prop('disabled', true).text('Kaydediliyor...');
                    var rows = [];
                    $('#sh-fp-rows .sh-fp-row').each(function() {
                        var $r = $(this);
                        var plugins = [];
                        // sh-select seçimlerini native select'ten oku
                        var vals = $r.find('.sh-fp-plugins').val() || [];
                        if (!Array.isArray(vals)) vals = vals ? [vals] : [];
                        vals.forEach(function(v) { if (v) plugins.push(v); });
                        rows.push({
                            type:    $r.find('.sh-fp-type').val(),
                            key:     $r.find('.sh-fp-key').val(),
                            plugins: plugins
                        });
                    });

                    var postData = { action: 'sh_am_save_forced_plugins', nonce: FP_NONCE };
                    rows.forEach(function(r, i) {
                        postData['rows['+i+'][type]']    = r.type;
                        postData['rows['+i+'][key]']     = r.key;
                        r.plugins.forEach(function(p, j) {
                            postData['rows['+i+'][plugins]['+j+']'] = p;
                        });
                    });

                    $.post(FP_AJAX, postData, function(res) {
                        $btn.prop('disabled', false).text('💾 Kaydet & Fetch Et');
                        if (!res.success) {
                            shAmToast(res.data || 'Kayıt hatası', true);
                            return;
                        }
                        $('#sh-fp-msg').show().delay(2500).fadeOut();
                        var affected = res.data.affected_rows || [];
                        if (!affected.length) {
                            shAmToast('✔ Kaydedildi — Fetch edilecek grup yok.');
                            return;
                        }
                        // Sıralı fetch başlat
                        runFpFetch(affected, 0);
                    }).fail(function() {
                        $btn.prop('disabled', false).text('💾 Kaydet & Fetch Et');
                        shAmToast('AJAX hatası', true);
                    });
                });

                // Sıralı fetch runner
                function runFpFetch(rows, index) {
                    if (index === 0) {
                        $('#sh-fp-progress').show();
                        $('#sh-fp-log').empty();
                        $('#sh-fp-progress-bar').css('width', '0%');
                    }

                    if (index >= rows.length) {
                        $('#sh-fp-progress-bar').css('width', '100%');
                        appendFpLog('✅ Tüm gruplar fetch edildi — <strong>' + rows.length + ' grup</strong>');
                        shAmToast('✔ Fetch tamamlandı (' + rows.length + ' grup)');
                        return;
                    }

                    var row = rows[index];
                    var pct = Math.round(((index) / rows.length) * 100);
                    $('#sh-fp-progress-bar').css('width', pct + '%');
                    appendFpLog('⏳ Fetch: <strong>' + row.label + '</strong> (' + (row.count || 1) + ' sayfa)...');

                    $.post(FP_AJAX, {
                        action:     'page_assets_update',
                        _ajax_nonce: row.nonce,
                        data: {
                            id:        row.id,
                            type:      row.context,
                            url:       row.url,
                            index:     index,
                            total:     rows.length,
                            mass:      true,
                            grouped:   true,
                            post_type: row.post_type,
                            context:   row.context
                        }
                    }, function(res) {
                        var ok = res && !res.error;
                        var gc = res && res.grouped_count ? ' (+' + res.grouped_count + ' apply)' : '';
                        appendFpLog(
                            (ok ? '✔' : '✖') + ' ' + row.label + gc,
                            ok ? 'color:#15803d' : 'color:#dc2626'
                        );
                        setTimeout(function() { runFpFetch(rows, index + 1); }, 300);
                    }).fail(function() {
                        appendFpLog('✖ Hata: ' + row.label, 'color:#dc2626');
                        setTimeout(function() { runFpFetch(rows, index + 1); }, 300);
                    });
                }

                function appendFpLog(html, style) {
                    var $log = $('#sh-fp-log');
                    $log.append('<div style="' + (style||'') + '">' + html + '</div>');
                    $log.scrollTop($log[0].scrollHeight);
                }
            })();

        })(jQuery);
        </script>
        <?php
    }

    // ─── Overview Tab ─────────────────────────────────────────────────────────

    private static function renderOverviewTab( array $settings, bool $is_development ): void
    {
        $mode_cards = [
            [ 'label' => 'Mode',           'value' => $is_development ? 'Development' : 'Production', 'color' => $is_development ? '#f0b429':  '#22c55e' ],
            [ 'label' => 'CSS Loading',    'value' => INLINE_CSS ? 'Inline' : ( $settings['css_lazy_load'] ? 'Lazy' : 'Normal' ), 'color' => '#3b82f6' ],
            [ 'label' => 'JS Loading',     'value' => $settings['js_defer'] ? 'Deferred' : 'Normal', 'color' => '#8b5cf6' ],
            [ 'label' => 'Google Fonts',   'value' => $settings['block_google_fonts'] ? 'Blocked' : 'Allowed', 'color' => $settings['block_google_fonts'] ? '#22c55e' : '#ef4444' ],
            [ 'label' => 'Global Styles',  'value' => $settings['remove_global_styles'] === 'auto' ? 'Auto' : ( $settings['remove_global_styles'] ? 'Removed' : 'Kept' ), 'color' => '#6b7280' ],
            [ 'label' => 'WC Styles',      'value' => $settings['remove_woocommerce_styles'] ? 'Removed' : 'Kept', 'color' => '#6b7280' ],
        ];
        ?>
        <div class="sh-layout">
        <div class="sh-main">

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:24px">
        <?php foreach ( $mode_cards as $c ) : ?>
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:16px;border-top:3px solid <?php echo $c['color']; ?>">
                <div style="font-size:11px;text-transform:uppercase;color:#6b7280;letter-spacing:.5px"><?php echo esc_html($c['label']); ?></div>
                <div style="font-size:18px;font-weight:700;color:<?php echo $c['color']; ?>;margin-top:6px"><?php echo esc_html($c['value']); ?></div>
            </div>
        <?php endforeach; ?>
        </div>

        <!-- What loads in each mode -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-bottom:16px">
            <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6"><strong>CSS Loading — <?php echo $is_development ? 'Production' : 'Development'; ?> Mode</strong></div>
            <table style="width:100%;border-collapse:collapse">
                <thead><tr>
                    <?php foreach(['Handle','File','Method','Status'] as $h): ?>
                    <th style="padding:10px 18px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #f3f4f6"><?php echo $h; ?></th>
                    <?php endforeach; ?>
                </tr></thead>
                <tbody>
                <?php
                $css_items = [
                    [ 'sh-font-faces', 'font-faces.css', 'Inline', true ],
                    [ 'sh-root',       'root.css',       'Normal', true ],
                    [ 'sh-conditional','plugin.css',     INLINE_CSS ? 'Inline' : ( $settings['css_lazy_load'] ? 'Lazy' : 'Normal' ), true ],
                    [ 'sh-main',       'main-combined.min.css', INLINE_CSS ? 'Inline' : ( $settings['css_lazy_load'] ? 'Lazy' : 'Normal' ), true ],
                    [ 'locale',        'locale-{lang}.css', 'Normal', true ],
                    [ 'woocommerce-*', 'WooCommerce CSS', 'Dequeued', $settings['remove_woocommerce_styles'] ],
                    [ 'global-styles', 'WP Global Styles', 'Dequeued (auto)', $settings['remove_global_styles'] !== false ],
                    [ 'wp-block-library', 'Block Library', 'Dequeued (auto)', $settings['remove_block_styles'] !== false ],
                ];
                foreach ( $css_items as [$handle, $file, $method, $active] ) :
                    $col = $active ? '#22c55e' : '#9ca3af';
                    $method_col = match($method) { 'Inline' => '#8b5cf6', 'Lazy' => '#3b82f6', 'Dequeued', 'Dequeued (auto)' => '#ef4444', default => '#6b7280' };
                ?>
                <tr style="border-bottom:1px solid #f9fafb">
                    <td style="padding:8px 18px;font-family:Consolas,monospace;font-size:12px"><?php echo esc_html($handle); ?></td>
                    <td style="padding:8px 18px;font-size:12px;color:#6b7280"><?php echo esc_html($file); ?></td>
                    <td style="padding:8px 18px"><span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;background:<?php echo $method_col; ?>22;color:<?php echo $method_col; ?>"><?php echo esc_html($method); ?></span></td>
                    <td style="padding:8px 18px"><span style="color:<?php echo $col; ?>;font-weight:600"><?php echo $active ? '✓' : '✗'; ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- JS Loading -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden">
            <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6"><strong>JS Loading — <?php echo $is_development ? 'Development' : 'Production'; ?> Mode</strong></div>
            <table style="width:100%;border-collapse:collapse">
                <thead><tr>
                    <?php foreach(['Handle','File','Method','Status'] as $h): ?>
                    <th style="padding:10px 18px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #f3f4f6"><?php echo $h; ?></th>
                    <?php endforeach; ?>
                </tr></thead>
                <tbody>
                <?php
                $js_items = $is_development ? [
                    [ 'jquery',       'jquery.min.js',          'Normal', true ],
                    [ 'footer-*',     'functions/*.js',          'Mass enqueue + guard', true ],
                    [ 'plugin-*',     'plugins/*.js',            'Conditional', true ],
                    [ 'pre-*',        'pre/*.js',                'Mass enqueue', true ],
                    [ 'main-*',       'main/*.js',               'Mass enqueue', true ],
                    [ 'image-sizes',  'image-sizes.js',          'Defer', true ],
                ] : [
                    [ 'jquery',              'jquery.min.js',          'Normal', true ],
                    [ 'pre',                 'pre-combined.min.js',    'Normal + guard', true ],
                    [ 'plugins-conditional', 'plugin_js (conditional)','Normal', true ],
                    [ 'main',                'main-combined.min.js',   'Normal + guard', true ],
                    [ 'image-sizes',         'image-sizes.js',         'Defer', true ],
                    [ 'locale',              'locale/{lang}.js',       'Normal', true ],
                ];
                foreach ( $js_items as [$handle, $file, $method, $active] ) :
                    $method_col = match(true) { str_contains($method,'Defer') => '#3b82f6', str_contains($method,'guard') => '#8b5cf6', default => '#6b7280' };
                ?>
                <tr style="border-bottom:1px solid #f9fafb">
                    <td style="padding:8px 18px;font-family:Consolas,monospace;font-size:12px"><?php echo esc_html($handle); ?></td>
                    <td style="padding:8px 18px;font-size:12px;color:#6b7280"><?php echo esc_html($file); ?></td>
                    <td style="padding:8px 18px"><span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;background:<?php echo $method_col; ?>22;color:<?php echo $method_col; ?>"><?php echo esc_html($method); ?></span></td>
                    <td style="padding:8px 18px"><span style="color:#22c55e;font-weight:600">✓</span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        </div>
        </div>
        <?php
    }

    // ─── Settings Tab ─────────────────────────────────────────────────────────

    private static function renderSettingsTab( array $settings, string $nonce, string $ajax_url ): void
    {
        $is_development    = ! empty( $settings['enable_development'] );
        $has_rocket = function_exists( 'get_rocket_option' ) || class_exists( 'WP_Rocket' );

        // WP Rocket exclusion current values
        $ex_archives  = (array) get_option( 'options_exclude_post_types_from_cache', [] );
        $ex_singles   = (array) get_option( 'options_exclude_posts_from_cache',      [] );
        $ex_taxs      = (array) get_option( 'options_exclude_taxonomies_from_cache', [] );

        // Post types with archive
        $pt_archive_options = [];
        foreach ( get_post_types( [ 'public' => true ], 'objects' ) as $pt ) {
            if ( $pt->has_archive ) {
                $pt_archive_options[ $pt->name ] = $pt->label;
            }
        }
        // All public post types
        $pt_options = [];
        foreach ( get_post_types( [ 'public' => true ], 'objects' ) as $pt ) {
            $pt_options[ $pt->name ] = $pt->label;
        }
        // All public taxonomies
        $tax_options = [];
        foreach ( get_taxonomies( [ 'public' => true ], 'objects' ) as $tax ) {
            $tax_options[ $tax->name ] = $tax->label;
        }
        // ── Duplicate enable toggle'ı kaldır — toolbar'daki toggle zaten var ──
        // Orijinal toggle buraya taşınmıştı, renderPage'deki kaldırıldı.
        // Settings tab içindeki enable-development toggle korunuyor.

        // ── Sub-tab belirleme ─────────────────────────────────────────────
        $sub_tab = sanitize_key( $_GET['sub'] ?? 'general' );

        $sub_tabs = [
            'general'      => 'General',
            'css_js'       => 'CSS & JS',
            'cache'        => 'Cache',
        ];
        if ( ! in_array( $sub_tab, array_keys( $sub_tabs ), true ) ) $sub_tab = 'general';
        ?>
        <!-- ── Beyaz kart: header'da tab butonları ──────────────────── -->
        <div class="sh-card">

            <!-- Kart Header: tab butonları -->
            <div class="sh-card-header">
                <?php foreach ( $sub_tabs as $slug => $label ) :
                    $is_active = $slug === $sub_tab;
                ?>
                <button type="button"
                    class="sh-tab-btn sh-stab-btn<?php echo $is_active ? ' active' : ''; ?>"
                    data-tab="<?php echo esc_attr( $slug ); ?>">
                    <?php echo esc_html( $label ); ?>
                </button>
                <?php endforeach; ?>
            </div>

            <!-- Kart Body: seçili tab içeriği -->
            <div style="padding:20px">

        <!-- ╔══ TAB: general ═══════════════════════════════════════════╗ -->
        <div id="sh-stab-general" class="sh-stab-content" style="<?php echo $sub_tab !== 'general' ? 'display:none' : ''; ?>">

                <!-- ── Development mode ─────────────────────────────────────── -->
                <div class="sh-field-group" style="background:#f9fafb;border-radius:6px;padding:14px 16px;margin-bottom:20px;border:1px solid <?php echo $is_development ? '#bbf7d0' : '#fef08a'; ?>">
                    <label class="sh-field-label" style="font-size:14px;font-weight:600;margin-bottom:4px">
                        <label class="sh-toggle" style="margin-right:10px">
                            <input type="checkbox" id="sh-enable-development" <?php checked( $is_development ); ?>>
                            <span class="sh-toggle-slider"></span>
                        </label>
                        Development Mode
                        <span style="display:inline-block;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600;margin-left:8px;background:<?php echo $is_development ?  '#fef9c3': '#dcfce7'; ?>;color:<?php echo $is_development ? '#92400e' : '#15803d'; ?>">
                            <?php echo $is_development ? '⚠ Development' : '✓ Production'; ?>
                        </span>
                    </label>
                    <p class="sh-field-hint" style="margin:6px 0 0">
                        Development mode aktifken CSS/JS minify edilir, PHP hata logları yazılır ve Twig cache devreye girer.
                        <strong>Kapalıyken</strong> tüm assets ham haliyle yüklenir, debug mesajları görünür.
                    </p>
                </div>

                <!-- ── Compile JS & CSS — sadece Development mode açıkken ───── -->
                <?php
                $last_compile     = get_option( 'sh_last_compile_js_css', 0 );
                $last_compile_str = $last_compile
                    ? '<span style="font-size:11px;color:#9ca3af"> — Son: ' . date_i18n( 'd.m.Y H:i', $last_compile ) . '</span>'
                    : '';
                $last_methods     = get_option( 'sh_last_compile_methods', 0 );
                $last_methods_str = $last_methods
                    ? '<span style="font-size:11px;color:#9ca3af"> — Son: ' . date_i18n( 'd.m.Y H:i', $last_methods ) . '</span>'
                    : '';
                ?>
                <div id="sh-compile-section" style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-bottom:12px;<?php echo $is_development ? '' : 'display:none;'; ?>">
                    <div style="padding:12px 16px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;gap:12px">
                        <div>
                            <strong style="font-size:13px">⚙ Compile JS &amp; CSS</strong><?php echo $last_compile_str; ?>
                            <p style="margin:3px 0 0;font-size:12px;color:#9ca3af">SCSS derle, JS/CSS minify et, font-face taşı, common CSS üret.</p>
                        </div>
                        <button type="button" id="sh-am-compile" class="sh-btn sh-btn-primary sh-btn-sm" style="white-space:nowrap;flex-shrink:0">
                            ⚙ Compile
                        </button>
                    </div>
                    <div id="sh-compile-log" style="display:none;padding:10px 16px;max-height:180px;overflow-y:auto;font-size:11px;font-family:Consolas,monospace;background:#f9fafb;border-bottom:1px solid #f3f4f6;line-height:1.6"></div>
                    <div id="sh-compile-result" style="display:none;padding:10px 16px"></div>
                </div>

                <!-- ── Compile Frontend/Backend Methods ─────────────────────── -->
                <div id="sh-compile-methods-section" style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-bottom:20px;<?php echo $is_development ? '' : 'display:none;'; ?>">
                    <div style="padding:12px 16px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;gap:12px">
                        <div>
                            <strong style="font-size:13px">⚙ Compile Frontend/Backend Methods</strong><?php echo $last_methods_str; ?>
                            <p style="margin:3px 0 0;font-size:12px;color:#9ca3af">PHP &amp; JS method dosyalarını derle/üret.</p>
                        </div>
                        <button type="button" id="sh-am-compile-methods" class="sh-btn sh-btn-primary sh-btn-sm" style="white-space:nowrap;flex-shrink:0">
                            ⚙ Compile Methods
                        </button>
                    </div>
                    <div id="sh-compile-methods-result" style="display:none;padding:10px 16px"></div>
                </div>

                <!-- ── Extract Translations bölümü Translations box'ına taşındı ── -->
                <div id="sh-logs-section" style="background:#f9fafb;border-radius:6px;padding:14px 16px;margin-bottom:20px;<?php echo $is_development ? '' : 'display:none;'; ?>">
                    <div style="font-size:13px;font-weight:600;color:#374151;margin-bottom:12px;border-bottom:1px solid #e5e7eb;padding-bottom:8px">Logs</div>

                    <div class="sh-field-group" style="margin-bottom:10px">
                        <label class="sh-field-label">
                            <label class="sh-toggle" style="margin-right:8px">
                                <input type="checkbox" id="sh-enable-logs" <?php checked( ! empty( $settings['enable_logs'] ) ); ?>>
                                <span class="sh-toggle-slider"></span>
                            </label>
                            PHP Logs
                        </label>
                        <p class="sh-field-hint">
                            Tema içindeki kritik işlemleri PHP <code>error_log()</code> ile loglar.
                            Yalnızca <strong>Development mode açıkken</strong> etkin olur.
                        </p>
                    </div>

                    <div class="sh-field-group">
                        <label class="sh-field-label">
                            <label class="sh-toggle" style="margin-right:8px">
                                <input type="checkbox" id="sh-enable-console-logs" <?php checked( ! empty( $settings['enable_console_logs'] ) ); ?>>
                                <span class="sh-toggle-slider"></span>
                            </label>
                            Console Logs (JS)
                        </label>
                        <p class="sh-field-hint">
                            JS tarafında <code>console.log()</code> debug çıktılarını aktif eder.
                            Yalnızca <strong>Development mode açıkken</strong> etkin olur. Tarayıcı konsolunda tema debug mesajları görünür.
                        </p>
                    </div>
                </div>

                <!-- ── General tab Save ─────────────────────────────── -->
                <div style="margin-top:24px">
                    <button type="button" id="sh-am-save-general" class="sh-btn sh-btn-primary">Save Settings</button>
                    <span id="sh-am-save-general-msg" style="margin-left:12px;font-size:13px;color:#15803d;display:none">✓ Saved</span>
                </div>

        </div><!-- /#sh-stab-general -->

        <!-- ╔══ TAB: css_js ════════════════════════════════════════════╗ -->
        <div id="sh-stab-css_js" class="sh-stab-content" style="<?php echo $sub_tab !== 'css_js' ? 'display:none' : ''; ?>">
            <div>

                <!-- ── Auto Fetch ──────────────────────────────────────────────── -->
                <div class="sh-field-group" style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px 16px;margin-bottom:20px">
                    <label class="sh-field-label" style="display:flex;align-items:center;gap:10px;font-size:13px;font-weight:600;color:#0369a1;cursor:pointer">
                        <label class="sh-toggle" style="margin:0">
                            <input type="checkbox" id="sh-auto-fetch" <?php checked( ! empty( $settings['auto_fetch'] ) ); ?>>
                            <span class="sh-toggle-slider"></span>
                        </label>
                        ⚡ Enable Auto-Fetch
                    </label>
                    <p class="sh-field-hint" style="margin:6px 0 0;color:#0369a1">
                        Sayfa CSS/JS dosyaları silinmişse (cache temizleme sonrası vb.) ziyaret anında
                        arkaplanda otomatik yeniden oluşturur. WP Rocket varsa cache de temizlenir.
                        Bir sonraki ziyarette doğru dosyalar yüklenir.
                    </p>
                </div>

                <!-- ── Build Settings: Block CSS & JS ─────────────────────── -->
                <div style="background:#f9fafb;border-radius:6px;padding:14px 16px;margin-bottom:20px">
                    <div style="font-size:13px;font-weight:600;color:#374151;margin-bottom:4px">Block CSS &amp; JS</div>
                    <p class="sh-field-hint" style="margin-bottom:12px">
                        Her sayfa için kullanılan CSS ve JS'i ayrıştırır. Bu ayarlar
                        <strong>Page Assets Extractor (PAE)</strong> ile birlikte çalışır.
                        Sayfayı ilk kaydettiğinde PAE fetch yaparak hangi CSS/JS'in kullanıldığını tespit eder,
                        sonraki ziyaretlerde sadece o sayfaya özel dosyalar yüklenir.
                    </p>

                    <div class="sh-field-group" style="margin-bottom:10px">
                        <label class="sh-field-label">
                            <label class="sh-toggle" style="margin-right:8px">
                                <input type="checkbox" id="sh-seperate-css" <?php checked( ! empty( $settings['seperate_css'] ) ); ?>>
                                <span class="sh-toggle-slider"></span>
                            </label>
                            Separate Block CSS
                        </label>
                        <p class="sh-field-hint">
                            Her sayfa için sadece kullanılan CSS dosyalarını yükler.
                            Gereksiz CSS'i kaldırarak sayfa boyutunu küçültür. PAE çalışmış olmalı.
                        </p>
                    </div>

                    <div class="sh-field-group" id="sh-inline-css-row" style="margin-bottom:10px;<?php echo empty( $settings['seperate_css'] ) ? 'display:none;' : ''; ?>">
                        <label class="sh-field-label">
                            <label class="sh-toggle" style="margin-right:8px">
                                <input type="checkbox" id="sh-inline-css" <?php checked( ! empty( $settings['inline_css'] ) ); ?> <?php disabled( empty( $settings['seperate_css'] ) ); ?>>
                                <span class="sh-toggle-slider"></span>
                            </label>
                            Inline CSS
                        </label>
                        <p class="sh-field-hint">
                            Ayrıştırılmış CSS'i harici dosya yerine <code>&lt;style&gt;</code> tag'i içinde doğrudan HTML'e gömer.
                            Ekstra HTTP isteğini ortadan kaldırır — küçük/orta sayfalarda faydalı, büyük sayfalarda dikkatli kullan.
                        </p>
                    </div>

                    <div class="sh-field-group" style="margin-bottom:10px">
                        <label class="sh-field-label">
                            <label class="sh-toggle" style="margin-right:8px">
                                <input type="checkbox" id="sh-seperate-js" <?php checked( ! empty( $settings['seperate_js'] ) ); ?>>
                                <span class="sh-toggle-slider"></span>
                            </label>
                            Separate Block JS
                        </label>
                        <p class="sh-field-hint">
                            Her sayfa için sadece kullanılan JS dosyalarını yükler. PAE çalışmış olmalı.
                        </p>
                    </div>

                    <div class="sh-field-group" id="sh-inline-js-row" style="<?php echo empty( $settings['seperate_js'] ) ? 'display:none;' : ''; ?>">
                        <label class="sh-field-label">
                            <label class="sh-toggle" style="margin-right:8px">
                                <input type="checkbox" id="sh-inline-js" <?php checked( ! empty( $settings['inline_js'] ) ); ?> <?php disabled( empty( $settings['seperate_js'] ) ); ?>>
                                <span class="sh-toggle-slider"></span>
                            </label>
                            Inline JS
                        </label>
                        <p class="sh-field-hint">
                            Ayrıştırılmış JS'i <code>&lt;script&gt;</code> tag'i içinde doğrudan HTML'e gömer. HTTP isteğini sıfırlar.
                        </p>
                    </div>
                </div>

                <!-- ── Twig Cache → Cache tab'ına taşındı ────────────────── -->

                <!-- ── Performance ────────────────────────────────────────── -->
                <div class="sh-field-group">
                    <label class="sh-field-label"><label class="sh-toggle" style="margin-right:8px"><input type="checkbox" id="sh-css-lazy" <?php checked($settings['css_lazy_load']); ?>><span class="sh-toggle-slider"></span></label>CSS Lazy Loading</label>
                    <p class="sh-field-hint">Critical olmayan CSS'i asenkron yükler (preload + onload trick). LCP'yi olumsuz etkileyecek CSS'leri buraya almayın.</p>
                </div>

                <div class="sh-field-group">
                    <label class="sh-field-label"><label class="sh-toggle" style="margin-right:8px"><input type="checkbox" id="sh-js-defer" <?php checked($settings['js_defer']); ?>><span class="sh-toggle-slider"></span></label>JS Defer</label>
                    <p class="sh-field-hint">Critical olmayan script'lere <code>defer</code> attribute ekler. Sayfa parse'ını bloklamaz, DOM hazır olunca çalışır.</p>
                </div>

                <hr style="border:none;border-top:1px solid #f3f4f6;margin:20px 0">

                <!-- ── Cleanup ────────────────────────────────────────────── -->
                <div style="font-size:13px;font-weight:600;color:#374151;margin-bottom:12px">Cleanup</div>

                <div class="sh-field-group">
                    <label class="sh-field-label">Remove WP Global Styles</label>
                    <p class="sh-field-hint">WordPress'in eklediği global <code>global-styles</code> CSS'ini kaldırır. Tema kendi stilini yönetiyorsa çoğunlukla gereksizdir.</p>
                    <select name="remove_global_styles" class="sh-select" style="max-width:200px;margin-top:6px">
                        <option value="auto" <?php selected($settings['remove_global_styles'],'auto'); ?>>Auto (remove if no core blocks)</option>
                        <option value="1"    <?php selected($settings['remove_global_styles'],'1'); ?>>Always remove</option>
                        <option value="0"    <?php selected($settings['remove_global_styles'],'0'); ?>>Keep</option>
                    </select>
                </div>

                <div class="sh-field-group">
                    <label class="sh-field-label">Remove Block Library CSS</label>
                    <p class="sh-field-hint">Gutenberg block'larının CSS kütüphanesini kaldırır. ACF blokları kullanıyorsanız genellikle güvenli şekilde kaldırılabilir.</p>
                    <select name="remove_block_styles" class="sh-select" style="max-width:200px;margin-top:6px">
                        <option value="auto" <?php selected($settings['remove_block_styles'],'auto'); ?>>Auto (remove if no core blocks)</option>
                        <option value="1"    <?php selected($settings['remove_block_styles'],'1'); ?>>Always remove</option>
                        <option value="0"    <?php selected($settings['remove_block_styles'],'0'); ?>>Keep</option>
                    </select>
                </div>

                <div class="sh-field-group">
                    <label class="sh-field-label"><label class="sh-toggle" style="margin-right:8px"><input type="checkbox" id="sh-remove-classic" <?php checked($settings['remove_classic_theme_styles']); ?>><span class="sh-toggle-slider"></span></label>Remove Classic Theme Styles</label>
                    <p class="sh-field-hint">WordPress'in classic tema compatibility CSS'ini kaldırır. Blok tabanlı temalar için gereksiz.</p>
                </div>

                <?php if ( defined('ENABLE_ECOMMERCE') && ENABLE_ECOMMERCE ) : ?>
                <div class="sh-field-group">
                    <label class="sh-field-label"><label class="sh-toggle" style="margin-right:8px"><input type="checkbox" id="sh-remove-woo" <?php checked($settings['remove_woocommerce_styles']); ?>><span class="sh-toggle-slider"></span></label>Remove WooCommerce Styles</label>
                    <p class="sh-field-hint">WooCommerce'in varsayılan CSS'lerini kaldırır (woocommerce-layout, woocommerce-general, woocommerce-smallscreen). Tema kendi WC stillerini yönetiyorsa etkinleştirin.</p>
                </div>
                <?php endif; ?>

                <div class="sh-field-group">
                    <label class="sh-field-label"><label class="sh-toggle" style="margin-right:8px"><input type="checkbox" id="sh-block-gfonts" <?php checked($settings['block_google_fonts']); ?>><span class="sh-toggle-slider"></span></label>Block Google Fonts</label>
                    <p class="sh-field-hint">Tüm Google Fonts isteklerini engeller. Self-hosted font kullanıyorsanız aktif edin — harici Google isteği gitmez, GDPR uyumu artar.</p>
                </div>

                <hr style="border:none;border-top:1px solid #f3f4f6;margin:20px 0">

                <div style="margin-bottom:24px">
                    <button type="button" id="sh-am-save" class="sh-btn sh-btn-primary">Save Settings</button>
                    <span id="sh-am-save-msg" style="margin-left:12px;font-size:13px;color:#15803d;display:none">✓ Saved</span>
                </div>

                <!-- ── Forced Plugins ────────────────────────────────────────── -->
                <?php $saved_forced = (array) get_option( 'pae_forced_plugins', [] ); ?>
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px">
                    <div style="font-size:13px;font-weight:600;color:#374151;margin-bottom:6px">🔌 Forced Plugins</div>
                    <p class="sh-field-hint" style="margin-bottom:12px">
                        Her post type veya taxonomy için zorunlu olarak yüklenecek conditional plugin'leri tanımla.
                        Grouped fetch sırasında HTML'de tespit edilmese bile eklenir.
                        <strong>Kaydet'e basınca etkilenen gruplar otomatik yeniden fetch edilir.</strong>
                    </p>

                    <div id="sh-fp-rows" style="margin-bottom:10px">
                        <?php foreach ( $saved_forced as $i => $rule ) :
                            $fp_type    = $rule['type']    ?? 'post_type';
                            $fp_key     = $rule['key']      ?? '';
                            $fp_plugins = $rule['plugins']  ?? [];
                        ?>
                        <div class="sh-fp-row sh-card-inset" data-index="<?php echo $i; ?>" style="margin-bottom:8px">
                            <div style="display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap">
                                <div style="min-width:130px">
                                    <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Tip</label>
                                    <select class="sh-fp-type sh-input" style="min-width:130px">
                                        <option value="post_type" <?php selected($fp_type,'post_type'); ?>>Post Type</option>
                                        <option value="taxonomy"  <?php selected($fp_type,'taxonomy'); ?>>Taxonomy</option>
                                    </select>
                                </div>
                                <div style="flex:1;min-width:180px">
                                    <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Post Type / Taxonomy</label>
                                    <select class="sh-fp-key sh-input sh-w-full" data-current="<?php echo esc_attr($fp_key); ?>">
                                        <option value="">Yükleniyor...</option>
                                    </select>
                                </div>
                                <div style="flex:2;min-width:220px">
                                    <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Zorunlu Plugin'ler</label>
                                    <select class="sh-fp-plugins sh-input sh-w-full" multiple
                                            data-placeholder="Plugin seç..."
                                            data-selected="<?php echo esc_attr(wp_json_encode($fp_plugins)); ?>">
                                        <option value="">Yükleniyor...</option>
                                    </select>
                                </div>
                                <button type="button" class="sh-fp-remove sh-btn sh-btn-danger sh-btn-sm" style="padding:5px 10px;flex-shrink:0;margin-bottom:2px">✖</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <button type="button" id="sh-fp-add" class="sh-btn sh-btn-ghost sh-btn-sm" style="margin-bottom:12px">+ Kural Ekle</button>

                    <!-- Progress / Log alanı -->
                    <div id="sh-fp-progress" style="display:none;margin-bottom:12px">
                        <div style="font-size:13px;font-weight:600;color:#374151;margin-bottom:6px">🔄 Fetch Durumu</div>
                        <div id="sh-fp-progress-bar-wrap" style="background:#e5e7eb;border-radius:4px;height:8px;margin-bottom:8px;overflow:hidden">
                            <div id="sh-fp-progress-bar" style="background:#2271b1;height:8px;width:0%;transition:width .3s"></div>
                        </div>
                        <div id="sh-fp-log" style="font-size:12px;font-family:monospace;max-height:180px;overflow-y:auto;background:#f9fafb;border:1px solid #e5e7eb;border-radius:4px;padding:8px 10px;line-height:1.8"></div>
                    </div>

                    <div style="display:flex;align-items:center;gap:10px">
                        <button type="button" id="sh-fp-save" class="sh-btn sh-btn-primary">💾 Kaydet &amp; Fetch Et</button>
                        <span id="sh-fp-msg" style="font-size:13px;color:#15803d;display:none">✔ Kaydedildi</span>
                    </div>
                </div>


            </div><!-- /.css_js inner padding -->
        </div><!-- /#sh-stab-css_js -->

        <!-- ╔══ TAB: cache ═════════════════════════════════════════════╗ -->
        <div id="sh-stab-cache" class="sh-stab-content" style="<?php echo $sub_tab !== 'cache' ? 'display:none' : ''; ?>">

        <!-- ── Purge Cache ────────────────────────────────────────────── -->
        <?php
        $has_opcache = function_exists('opcache_get_status') || function_exists('opcache_reset') || function_exists('flush_op_cache') || function_exists('opcache_flush');
        $has_rocket  = function_exists( 'get_rocket_option' ) || class_exists( 'WP_Rocket' );

        // Twig cache istatistikleri
        $twig_cache_dir  = defined('STATIC_PATH') ? rtrim(STATIC_PATH, '/') . '/twig_cache' : '';
        $twig_file_count = 0; $twig_total_size = 0;
        if ( $twig_cache_dir && is_dir($twig_cache_dir) ) {
            foreach ( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator($twig_cache_dir, \RecursiveDirectoryIterator::SKIP_DOTS) ) as $f ) {
                if ( $f->isFile() ) { $twig_file_count++; $twig_total_size += $f->getSize(); }
            }
        }

        // Query Cache transient sayısı
        global $wpdb;
        $qc_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_sh_qc_%' OR option_name LIKE '_transient_qc_%'");

        // OPcache istatistikleri
        $opcache_stats = \SaltHareket\AssetManager\AssetManager::getOpcacheStats();
        ?>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;margin-bottom:16px">
            <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6"><strong>🧹 Purge Cache</strong></div>
            <div style="padding:18px">
                <p class="sh-field-hint" style="margin-bottom:12px">Temizlenecek cache tiplerini seçin ve Purge butonuna basın. <strong>En az bir seçim zorunludur.</strong></p>
                <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:16px">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px">
                        <input type="checkbox" id="sh-purge-query" class="sh-purge-check" style="width:15px;height:15px">
                        Query Cache
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px">
                        <input type="checkbox" id="sh-purge-twig" class="sh-purge-check" style="width:15px;height:15px">
                        Twig Cache
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px<?php echo ! $has_opcache ? ';opacity:.5' : ''; ?>">
                        <input type="checkbox" id="sh-purge-opcache" class="sh-purge-check" <?php echo ! $has_opcache ? 'disabled' : ''; ?> style="width:15px;height:15px">
                        OPcache <?php if ( ! $has_opcache ) echo '<span style="font-size:11px;color:#9ca3af">(aktif değil)</span>'; ?>
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px<?php echo ! $has_rocket ? ';opacity:.5' : ''; ?>">
                        <input type="checkbox" id="sh-purge-rocket" class="sh-purge-check" <?php echo ! $has_rocket ? 'disabled' : ''; ?> style="width:15px;height:15px">
                        WP Rocket <?php if ( ! $has_rocket ) echo '<span style="font-size:11px;color:#9ca3af">(plugin yok)</span>'; ?>
                    </label>
                </div>
                <div style="display:flex;align-items:center;gap:10px">
                    <button type="button" id="sh-am-purge-selected" class="sh-btn sh-btn-primary" disabled style="opacity:.5;cursor:not-allowed">🧹 Purge Selected</button>
                    <span id="sh-purge-hint" style="font-size:12px;color:#ef4444">← En az bir seçim yapın</span>
                    <span id="sh-am-purge-msg" style="font-size:13px;color:#15803d;display:none">✓ Cache temizlendi</span>
                </div>
            </div>
        </div>

        <!-- ── OPcache ───────────────────────────────────────────────── -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;margin-bottom:16px">
            <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <strong>⚙ OPcache</strong>
                <?php if ($opcache_stats['enabled']): ?>
                <span style="font-size:11px;background:#dcfce7;color:#15803d;padding:2px 8px;border-radius:10px;font-weight:600">✓ Aktif</span>
                <button type="button" id="sh-am-clear-opcache" class="sh-btn sh-btn-sm" style="margin-left:auto">Clear Cache</button>
                <span id="sh-clear-opcache-msg" style="font-size:12px;color:#15803d;display:none">✓ Temizlendi</span>
                <?php else: ?>
                <span style="font-size:11px;background:#fee2e2;color:#dc2626;padding:2px 8px;border-radius:10px;font-weight:600">✗ Pasif</span>
                <?php endif; ?>
            </div>
            <div style="padding:18px">
            <?php if ($opcache_stats['enabled']): ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px;margin-bottom:10px">
                    <?php $oc_items = [
                        ['Hit Rate',        $opcache_stats['hit_rate'].'%',         $opcache_stats['hit_rate']>=90?'#dcfce7':($opcache_stats['hit_rate']>=70?'#fef9c3':'#fee2e2'), $opcache_stats['hit_rate']>=90?'#15803d':($opcache_stats['hit_rate']>=70?'#92400e':'#dc2626')],
                        ['Kullanılan',      $opcache_stats['memory_used'],           '#eff6ff','#1d4ed8'],
                        ['Boş',             $opcache_stats['memory_free'],           '#f0fdf4','#15803d'],
                        ['Toplam',          $opcache_stats['memory_total'],          '#f9fafb','#374151'],
                        ['Cached Scripts',  $opcache_stats['cached_scripts'],        '#fdf4ff','#7e22ce'],
                        ['Hits',            $opcache_stats['hits'],                  '#f0fdf4','#15803d'],
                        ['Misses',          $opcache_stats['misses'],                '#fff7ed','#c2410c'],
                    ];
                    foreach ($oc_items as [$lbl,$val,$bg,$clr]): ?>
                    <div style="background:<?php echo $bg;?>;border-radius:6px;padding:10px 12px;text-align:center">
                        <div style="font-size:11px;color:#9ca3af;margin-bottom:3px"><?php echo $lbl;?></div>
                        <div style="font-size:15px;font-weight:700;color:<?php echo $clr;?>"><?php echo $val;?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($opcache_stats['revalidate_freq'] !== '—'): ?>
                <p class="sh-field-hint">
                    Revalidate freq: <strong><?php echo $opcache_stats['revalidate_freq'];?>s</strong> ·
                    Max files: <strong><?php echo $opcache_stats['max_files'];?></strong> ·
                    Max memory: <strong><?php echo $opcache_stats['max_memory'];?></strong>
                    <?php if ((int)$opcache_stats['revalidate_freq'] > 0): ?>
                    <br><span style="color:#f59e0b">⚠ Geliştirme için <code>opcache.revalidate_freq=0</code> önerilir (dosya değişiklikleri anında yansır)</span>
                    <?php endif; ?>
                </p>
                <?php endif; ?>
            <?php else: ?>
                <p class="sh-field-hint" style="color:#6b7280"><?php echo esc_html($opcache_stats['reason'] ?? 'OPcache aktif değil.');?></p>
                <p class="sh-field-hint">PHP ini'de <code>opcache.enable=1</code> ve <code>opcache.memory_consumption=128</code> ile aktifleştirin.</p>
            <?php endif; ?>
            </div>
        </div>

        <!-- ── Twig Template Cache ────────────────────────────────────── -->
        <?php $twig_dev_disabled = $is_development; ?>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;margin-bottom:16px<?php echo $twig_dev_disabled ? ';border-color:#fde68a' : ''; ?>">
            <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <strong>🌿 Twig Template Cache</strong>
                <?php if ($twig_dev_disabled): ?>
                <span style="font-size:11px;background:#fef9c3;color:#92400e;padding:2px 8px;border-radius:10px;font-weight:600">⚠ Development modda devre dışı</span>
                <?php elseif ($twig_file_count > 0): ?>
                <span style="font-size:11px;color:#9ca3af"><?php echo $twig_file_count; ?> dosya · <?php echo size_format($twig_total_size, 1); ?></span>
                <?php endif; ?>
                <?php if ($twig_file_count > 0): ?>
                <button type="button" id="sh-am-clear-twig" class="sh-btn sh-btn-sm" style="margin-left:auto">Clear Cache</button>
                <span id="sh-clear-twig-msg" style="font-size:12px;color:#15803d;display:none">✓ Temizlendi</span>
                <?php endif; ?>
            </div>
            <div style="padding:18px">
                <?php if ($twig_dev_disabled): ?>
                <div style="background:#fef9c3;border:1px solid #fde68a;border-radius:6px;padding:10px 14px;margin-bottom:12px;font-size:13px;color:#92400e">
                    Development mode açık olduğu için Twig cache üretilmiyor. Ayar korunuyor — development kapatılınca otomatik devreye girer.
                </div>
                <?php endif; ?>
                <div class="sh-field-group" style="display:flex;align-items:center;gap:12px;margin-bottom:8px<?php echo $twig_dev_disabled ? ';pointer-events:none;opacity:.5' : ''; ?>">
                    <label class="sh-toggle" style="margin:0">
                        <input type="checkbox" id="sh-enable-twig-cache" <?php checked( ! empty( $settings['enable_twig_cache'] ) ); ?> <?php echo $twig_dev_disabled ? 'disabled' : ''; ?>>
                        <span class="sh-toggle-slider"></span>
                    </label>
                    <label for="sh-enable-twig-cache" style="font-size:13px;color:#374151;font-weight:500">Enable Twig Cache</label>
                </div>
                <p class="sh-field-hint">
                    Twig şablonlarını PHP'ye derleyip cache'ler. Her request'te Twig parse overhead'ini ortadan kaldırır.
                    <strong>Development modda otomatik devre dışı</strong> — ayara dokunmaya gerek yok, kapatmayın.
                </p>
                <?php if (!$twig_dev_disabled): ?>
                <div style="margin-top:14px">
                    <button type="button" id="sh-am-save-twig-cache" class="sh-btn sh-btn-primary">Save</button>
                    <span id="sh-am-twig-cache-msg" style="margin-left:12px;font-size:13px;color:#15803d;display:none">✓ Saved</span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ( $has_rocket ) : ?>
        <!-- ── WP Rocket Cache Exclusions ─────────────────────────── -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;margin-top:16px">
            <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;gap:8px">
                <strong>🚀 WP Rocket Cache Exclusions</strong>
                <span style="font-size:11px;background:#fef9c3;color:#854d0e;padding:2px 8px;border-radius:10px;font-weight:600">WP Rocket</span>
            </div>
            <div style="padding:18px">
                <p class="sh-field-hint" style="margin-bottom:16px">
                    Seçilen post type arşivleri, single sayfalar ve taxonomy sayfaları WP Rocket cache'inden hariç tutulur.
                    Kaydet butonuna basılınca WP Rocket ayarları otomatik güncellenir.
                </p>

                <!-- Exclude Post Type Archives -->
                <div class="sh-field-group" style="margin-bottom:16px">
                    <label class="sh-field-label" style="margin-bottom:6px;display:block">
                        Exclude Post Type Archives from Cache
                        <span style="font-size:11px;color:#9ca3af;font-weight:400;margin-left:6px">(arşiv sayfaları)</span>
                    </label>
                    <select id="sh-exclude-post-types-archive" name="exclude_post_types_from_cache[]" multiple data-sh-select data-placeholder="Post type ara ve seç..." style="width:100%">
                        <?php foreach ( $pt_archive_options as $value => $label ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( in_array( $value, $ex_archives, true ) ); ?>>
                                <?php echo esc_html( $label ); ?> (<?php echo esc_html( $value ); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="sh-field-hint" style="margin-top:4px">Sadece <code>has_archive</code> olan post type'lar listelenir.</p>
                </div>

                <!-- Exclude Single Posts -->
                <div class="sh-field-group" style="margin-bottom:16px">
                    <label class="sh-field-label" style="margin-bottom:6px;display:block">
                        Exclude Single Posts from Cache
                        <span style="font-size:11px;color:#9ca3af;font-weight:400;margin-left:6px">(detay sayfaları)</span>
                    </label>
                    <select id="sh-exclude-posts" name="exclude_posts_from_cache[]" multiple data-sh-select data-placeholder="Post type ara ve seç..." style="width:100%">
                        <?php foreach ( $pt_options as $value => $label ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( in_array( $value, $ex_singles, true ) ); ?>>
                                <?php echo esc_html( $label ); ?> (<?php echo esc_html( $value ); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Exclude Taxonomies -->
                <div class="sh-field-group" style="margin-bottom:16px">
                    <label class="sh-field-label" style="margin-bottom:6px;display:block">
                        Exclude Taxonomies from Cache
                        <span style="font-size:11px;color:#9ca3af;font-weight:400;margin-left:6px">(taxonomy sayfaları)</span>
                    </label>
                    <select id="sh-exclude-taxonomies" name="exclude_taxonomies_from_cache[]" multiple data-sh-select data-placeholder="Taxonomy ara ve seç..." style="width:100%">
                        <?php foreach ( $tax_options as $value => $label ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( in_array( $value, $ex_taxs, true ) ); ?>>
                                <?php echo esc_html( $label ); ?> (<?php echo esc_html( $value ); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="button" id="sh-am-save-exclusions" class="sh-btn sh-btn-primary">Save Exclusions</button>
                <span id="sh-am-excl-msg" style="margin-left:12px;font-size:13px;color:#15803d;display:none">✓ Saved</span>
            </div>
        </div>

        <!-- ── Query Cache ───────────────────────────────────────────── -->
        <?php
        $qc_enabled = (bool) get_option( 'options_enable_object_cache', false );
        $qc_types   = (array) get_option( 'options_object_cache_types', [] );
        $qc_all_types = [
            'wrap'       => 'wrap',
            'get_field'  => 'get_field',
            'get_posts'  => 'get_posts',
            'get_post'   => 'get_post',
            'get_terms'  => 'get_terms',
            'get_term'   => 'get_term',
            'menu'       => 'menu',
            'wp_options' => 'wp_options',
        ];
        ?>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;margin-top:16px">
            <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <strong>⚡ Query Cache</strong>
                <span style="font-size:11px;background:#dbeafe;color:#1d4ed8;padding:2px 8px;border-radius:10px;font-weight:600">QueryCache</span>
                <?php if ($qc_count > 0): ?>
                <span style="font-size:11px;color:#9ca3af"><?php echo number_format_i18n($qc_count); ?> transient</span>
                <button type="button" id="sh-am-clear-qc" class="sh-btn sh-btn-sm" style="margin-left:auto">Clear Cache</button>
                <span id="sh-clear-qc-msg" style="font-size:12px;color:#15803d;display:none">✓ Temizlendi</span>
                <?php endif; ?>
            </div>
            <div style="padding:18px">
                <p class="sh-field-hint" style="margin-bottom:16px">
                    ACF field, WP option, post/term sorguları ve nav menüleri transient cache ile önbelleğe alır.
                    Açık tutmanız önerilir — kapatınca tüm cache temizlenir.
                </p>

                <div class="sh-field-group" style="margin-bottom:16px;display:flex;align-items:center;gap:12px">
                    <label class="sh-toggle" style="margin:0">
                        <input type="checkbox" id="sh-qc-enabled" <?php checked( $qc_enabled ); ?>>
                        <span class="sh-toggle-slider"></span>
                    </label>
                    <label for="sh-qc-enabled" style="font-size:13px;color:#374151;cursor:pointer;font-weight:500">
                        Aktif
                    </label>
                </div>

                <div id="sh-qc-types-wrap" style="<?php echo $qc_enabled ? '' : 'display:none'; ?>">
                    <label class="sh-field-label" style="margin-bottom:6px;display:block">
                        Cache Tipleri
                        <span style="font-size:11px;color:#9ca3af;font-weight:400;margin-left:6px">(hangi sorgu tipleri cache'lensin)</span>
                    </label>
                    <select id="sh-qc-types" multiple data-sh-select data-placeholder="Tip seç..." style="width:100%">
                        <?php foreach ( $qc_all_types as $val => $lbl ) : ?>
                            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( in_array( $val, $qc_types, true ) ); ?>>
                                <?php echo esc_html( $lbl ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="sh-field-hint" style="margin-top:4px">Önerilen: tüm tipler seçili. Kapatınca ilgili cache temizlenir.</p>
                </div>

                <div style="margin-top:16px;display:flex;align-items:center;gap:10px">
                    <button type="button" id="sh-am-save-qc" class="sh-btn sh-btn-primary">💾 Query Cache Kaydet</button>
                    <span id="sh-am-qc-msg" style="font-size:13px;color:#15803d;display:none">✓ Kaydedildi</span>
                </div>
            </div>
        </div>

        <!-- ── Cache Manifest ────────────────────────────────────────── -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;margin-top:16px">
            <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;gap:8px">
                <div style="display:flex;align-items:center;gap:8px">
                    <strong>🗂 Cache Manifest</strong>
                    <span style="font-size:11px;background:#fef9c3;color:#854d0e;padding:2px 8px;border-radius:10px;font-weight:600">WP Rocket</span>
                </div>
                <button type="button" id="sh-manifest-add-row" class="sh-btn sh-btn-sm" style="display:flex;align-items:center;gap:4px">
                    <span style="font-size:16px;line-height:1">+</span> Kural Ekle
                </button>
            </div>
            <div style="padding:12px 18px 4px">
                <p class="sh-field-hint" style="margin-bottom:12px">
                    Post veya term güncellendiğinde hangi sayfaların WP Rocket cache'inin temizleneceğini tanımlayın.
                    Kural yoksa sadece değişen içeriğin kendi cache'i temizlenir.
                </p>
            </div>

            <?php
            // Mevcut manifest verisini oku
            $manifest_raw  = get_option( 'options_cache_manifest', [] );
            // Her row'un array olmasını garantile — eski ACF verisi string içerebilir
            $manifest_rows = array_values( array_filter(
                is_array( $manifest_raw ) ? $manifest_raw : [],
                fn( $r ) => is_array( $r )
            ) );

            // Select seçenekleri
            $pt_all = [];
            foreach ( get_post_types( [ 'public' => true ], 'objects' ) as $pt ) {
                $pt_all[ $pt->name ] = $pt->label . ' (' . $pt->name . ')';
            }
            $tax_all = [];
            foreach ( get_taxonomies( [ 'public' => true ], 'objects' ) as $tax ) {
                $tax_all[ $tax->name ] = $tax->label . ' (' . $tax->name . ')';
            }
            // Pages için post_object options — ML varsa sadece default dil
            $default_lang   = function_exists( 'pll_default_language' ) ? pll_default_language() : '';
            $pages_query    = [
                'post_type'      => array_keys( $pt_all ),
                'posts_per_page' => 200,
                'post_status'    => 'publish',
                'orderby'        => 'title',
                'order'          => 'ASC',
            ];
            if ( $default_lang && function_exists( 'pll_get_post' ) ) {
                $pages_query['lang'] = $default_lang;
            }
            $all_pages = get_posts( $pages_query );

            // Terms — ML varsa sadece default dil
            $terms_query = [
                'taxonomy'   => array_keys( $tax_all ),
                'hide_empty' => false,
                'number'     => 200,
            ];
            // Polylang: get_terms 'lang' param desteklemez, pll_get_post_language kullanılmalı
            // Bu yüzden filtresiz çekip Polylang'ın kendi filtrelerine bırakıyoruz
            if ( $default_lang && function_exists( 'pll_current_language' ) ) {
                // PLL'nin term dil filtresi 'get_terms' hook'unda otomatik devreye girer
                // — sadece current language'ı default dile geçici set edip çekiyoruz
                add_filter( 'pll_get_terms_args', function( $args ) use ( $default_lang ) {
                    $args['lang'] = $default_lang;
                    return $args;
                } );
            }
            $all_terms = get_terms( $terms_query );
            // Hook'u hemen temizle — sayfa geri kalanını etkilemesin
            remove_all_filters( 'pll_get_terms_args' );
            ?>

            <div id="sh-manifest-rows" style="padding:0 18px">
                <?php if ( empty( $manifest_rows ) ) : ?>
                    <div id="sh-manifest-empty" style="text-align:center;padding:24px;color:#9ca3af;font-size:13px">
                        Henüz kural eklenmedi. <strong>Kural Ekle</strong> butonuna basarak başla.
                    </div>
                <?php else : ?>
                    <?php foreach ( $manifest_rows as $i => $mrow ) : ?>
                        <?php self::renderManifestRow( $i, $mrow, $pt_all, $tax_all, $all_pages, $all_terms ); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Template row (hidden) -->
            <div id="sh-manifest-row-tpl" style="display:none">
                <?php self::renderManifestRow( '__TPL__', [], $pt_all, $tax_all, $all_pages, $all_terms ); ?>
            </div>

            <div style="padding:14px 18px;border-top:1px solid #f3f4f6;margin-top:8px;display:flex;align-items:center;gap:10px">
                <button type="button" id="sh-am-save-manifest" class="sh-btn sh-btn-primary">💾 Manifest Kaydet</button>
                <span id="sh-am-manifest-msg" style="font-size:13px;color:#15803d;display:none">✓ Kaydedildi</span>
            </div>
        </div>

        <?php endif; ?>

        </div><!-- /#sh-stab-cache -->

            </div><!-- /kart body -->
        </div><!-- /beyaz kart -->
        <div id="sh-stab-woocommerce" class="sh-stab-content" style="<?php echo $sub_tab !== 'woocommerce' ? 'display:none' : ''; ?>">

        <?php if ( defined('ENABLE_ECOMMERCE') && ENABLE_ECOMMERCE ) : ?>
        <?php
        $woo_enable_cart = (bool) get_option( 'options_enable_cart', false );
        $woo_enable_api  = (bool) get_option( 'options_enable_woo_api', false );
        $woo_api_local   = (array) get_option( 'options_woo_api_local', [] );
        $woo_api_live    = (array) get_option( 'options_woo_api_live', [] );
        ?>
        <!-- ── WooCommerce ─── -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px">
            <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;gap:8px">
                <strong>🛒 WooCommerce</strong>
                <span style="font-size:11px;background:#f0fdf4;color:#15803d;padding:2px 8px;border-radius:10px;font-weight:600">WooCommerce</span>
            </div>
            <div style="padding:18px">

                <!-- Enable Cart -->
                <div class="sh-field-group" style="margin-bottom:16px;display:flex;align-items:center;gap:12px">
                    <label class="sh-toggle" style="margin:0">
                        <input type="checkbox" id="sh-woo-enable-cart" <?php checked( $woo_enable_cart ); ?>>
                        <span class="sh-toggle-slider"></span>
                    </label>
                    <div>
                        <label for="sh-woo-enable-cart" style="font-size:13px;color:#374151;cursor:pointer;font-weight:500">Enable Cart</label>
                        <p class="sh-field-hint" style="margin:2px 0 0">Sepet özelliğini aktif eder. <code>ENABLE_CART</code> sabitini tanımlar.</p>
                    </div>
                </div>

                <!-- Enable WooCommerce API -->
                <div class="sh-field-group" style="margin-bottom:16px;display:flex;align-items:center;gap:12px">
                    <label class="sh-toggle" style="margin:0">
                        <input type="checkbox" id="sh-woo-enable-api" <?php checked( $woo_enable_api ); ?>>
                        <span class="sh-toggle-slider"></span>
                    </label>
                    <div>
                        <label for="sh-woo-enable-api" style="font-size:13px;color:#374151;cursor:pointer;font-weight:500">Enable WooCommerce API</label>
                        <p class="sh-field-hint" style="margin:2px 0 0">WooCommerce REST API kimlik doğrulamasını aktif eder.</p>
                    </div>
                </div>

                <!-- API Local + Live — yan yana grid -->
                <div id="sh-woo-api-wrap" style="<?php echo $woo_enable_api ? 'display:grid;' : 'display:none;'; ?>grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">

                    <!-- Local -->
                    <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:14px">
                        <div style="font-size:11px;font-weight:600;color:#374151;margin-bottom:12px;text-transform:uppercase;letter-spacing:.5px">Local</div>
                        <div style="margin-bottom:10px">
                            <label style="display:block;font-size:12px;color:#6b7280;margin-bottom:4px" for="sh-woo-api-local-client">Client ID</label>
                            <input type="text" id="sh-woo-api-local-client"
                                value="<?php echo esc_attr( $woo_api_local['client'] ?? '' ); ?>"
                                style="border:1px solid #e5e7eb;border-radius:6px;padding:6px 10px;font-size:13px;width:100%;box-sizing:border-box">
                        </div>
                        <div>
                            <label style="display:block;font-size:12px;color:#6b7280;margin-bottom:4px" for="sh-woo-api-local-secret">Client Secret</label>
                            <input type="password" id="sh-woo-api-local-secret"
                                value="<?php echo esc_attr( $woo_api_local['client_secret'] ?? '' ); ?>"
                                style="border:1px solid #e5e7eb;border-radius:6px;padding:6px 10px;font-size:13px;width:100%;box-sizing:border-box">
                        </div>
                    </div>

                    <!-- Live -->
                    <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:14px">
                        <div style="font-size:11px;font-weight:600;color:#374151;margin-bottom:12px;text-transform:uppercase;letter-spacing:.5px">Live</div>
                        <div style="margin-bottom:10px">
                            <label style="display:block;font-size:12px;color:#6b7280;margin-bottom:4px" for="sh-woo-api-live-client">Client ID</label>
                            <input type="text" id="sh-woo-api-live-client"
                                value="<?php echo esc_attr( $woo_api_live['client'] ?? '' ); ?>"
                                style="border:1px solid #e5e7eb;border-radius:6px;padding:6px 10px;font-size:13px;width:100%;box-sizing:border-box">
                        </div>
                        <div>
                            <label style="display:block;font-size:12px;color:#6b7280;margin-bottom:4px" for="sh-woo-api-live-secret">Client Secret</label>
                            <input type="password" id="sh-woo-api-live-secret"
                                value="<?php echo esc_attr( $woo_api_live['client_secret'] ?? '' ); ?>"
                                style="border:1px solid #e5e7eb;border-radius:6px;padding:6px 10px;font-size:13px;width:100%;box-sizing:border-box">
                        </div>
                    </div>

                </div>

                <div style="display:flex;align-items:center;gap:10px">
                    <button type="button" id="sh-am-save-woo" class="sh-btn sh-btn-primary">💾 Save</button>
                    <span id="sh-am-woo-msg" style="font-size:13px;color:#15803d;display:none">✓ Kaydedildi</span>
                </div>

            </div>
        </div>
        <?php endif; // ENABLE_ECOMMERCE ?>

        </div><!-- /#sh-stab-woocommerce -->

        <!-- ╔══ TAB: translations ══════════════════════════════════════╗ -->
        <div id="sh-stab-translations" class="sh-stab-content" style="<?php echo $sub_tab !== 'translations' ? 'display:none' : ''; ?>">

        <?php
        // ── Translations ─────────────────────────────────────────────
        $has_polylang = function_exists( 'pll_languages_list' );

        // Mevcut değerleri oku
        // untranslatable_* → ACF options prefix'li (options_ prefix)
        $tr_untrans_tax  = (array) get_option( 'options_untranslatable_taxonomies', [] );
        $tr_untrans_pt   = (array) get_option( 'options_untranslatable_post_types',  [] );
        // synchronized_pages ve sync_taxonomies_to_language → options_ prefix'li (polylang.php fix sonrası)
        $tr_sync_pages   = (array) get_option( 'options_synchronized_pages',              [] );
        $tr_sync_tax     = (array) get_option( 'options_sync_taxonomies_to_language',     [] );

        $last_translations     = get_option( 'sh_last_extract_translations', 0 );
        $last_translations_str = $last_translations
            ? '<span style="font-size:11px;color:#9ca3af"> — Son: ' . wp_date( 'd.m.Y H:i', $last_translations ) . '</span>'
            : '';

        // ── Untranslatable Taxonomies: public + show_ui, TÜM taxonomy'ler listelenir
        // Kullanıcı seçtiklerini "çevrilmez" olarak işaretler
        $untrans_tax_options = [];
        if ( $has_polylang ) {
            $all_tax = get_taxonomies( [ 'public' => true, 'show_ui' => true ], 'objects' );
            foreach ( $all_tax as $slug => $obj ) {
                $untrans_tax_options[ $slug ] = $obj->label . ' (' . $slug . ')';
            }
        }

        // ── Untranslatable Post Types: public, non-builtin, TÜM PT'ler listelenir
        $untrans_pt_options = [];
        $pt_excluded = [ 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset' ];
        if ( $has_polylang ) {
            $all_pts = get_post_types( [ 'public' => true, '_builtin' => false ], 'objects' );
            foreach ( $all_pts as $slug => $obj ) {
                if ( ! in_array( $slug, $pt_excluded, true ) ) {
                    $untrans_pt_options[ $slug ] = $obj->label . ' (' . $slug . ')';
                }
            }
        }

        // ── Sync Taxonomies to Language: public + show_ui, pll_is_translated_taxonomy() === true
        $sync_tax_options = [];
        if ( $has_polylang ) {
            $all_tax = get_taxonomies( [ 'public' => true, 'show_ui' => true ], 'names' );
            foreach ( $all_tax as $tax ) {
                if ( pll_is_translated_taxonomy( $tax ) ) {
                    $obj = get_taxonomy( $tax );
                    $sync_tax_options[ $tax ] = ( $obj ? $obj->label : $tax ) . ' (' . $tax . ')';
                }
            }
        }

        // ── Sync Pages Content: sadece post_type=page, default dil
        $sync_pages_lang  = function_exists( 'pll_default_language' ) ? pll_default_language() : '';
        $sync_pages_query = [
            'post_type'      => 'page',
            'posts_per_page' => 300,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];
        if ( $sync_pages_lang ) {
            $sync_pages_query['lang'] = $sync_pages_lang;
        }
        $all_pages_list = get_posts( $sync_pages_query );
        ?>
        <!-- ── Translations ─── -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px">
            <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;gap:8px">
                <strong>🌍 Translations</strong>
            </div>
            <div style="padding:18px">

                <!-- Extract Translations -->
                <div id="sh-extract-translations-section" style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-bottom:20px">
                    <div style="padding:12px 16px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;gap:12px">
                        <div>
                            <strong style="font-size:13px">⚙ Extract Translations</strong><?php echo $last_translations_str; ?>
                            <p style="margin:3px 0 0;font-size:12px;color:#9ca3af">Tema dosyalarından çeviri stringlerini tara ve <code>translates.php</code> / <code>json</code> dosyalarını güncelle.</p>
                        </div>
                        <button type="button" id="sh-am-extract-translations" class="sh-btn sh-btn-primary sh-btn-sm" style="white-space:nowrap;flex-shrink:0">
                            ⚙ Extract
                        </button>
                    </div>
                    <div id="sh-extract-translations-result" style="display:none;padding:10px 16px"></div>
                </div>

                <?php if ( $has_polylang ) : ?>
                <!-- Polylang bölümü -->
                <div style="border-top:1px solid #f3f4f6;padding-top:16px">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px">
                        <span style="font-size:13px;font-weight:600;color:#374151">Polylang</span>
                        <span style="font-size:11px;background:#ede9fe;color:#7c3aed;padding:2px 8px;border-radius:10px;font-weight:600">Polylang</span>
                    </div>

                    <!-- untranslatable_taxonomies -->
                    <div class="sh-field-group" style="margin-bottom:16px">
                        <label class="sh-field-label" style="margin-bottom:4px;display:block">Untranslatable Taxonomies</label>
                        <p class="sh-field-hint" style="margin-bottom:6px">Seçilen taxonomy'ler çevrilmez — tüm dillerde aynı liste kullanılır. Örn: <code>departman</code> taxonomy'si TR'de de EN'de de ortaktır.</p>
                        <select id="sh-tr-untrans-tax" multiple data-sh-select data-placeholder="Taxonomy seç..." style="width:100%">
                            <?php foreach ( $untrans_tax_options as $val => $lbl ) : ?>
                                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( in_array( $val, $tr_untrans_tax, true ) ); ?>><?php echo esc_html( $lbl ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- untranslatable_post_types -->
                    <div class="sh-field-group" style="margin-bottom:16px">
                        <label class="sh-field-label" style="margin-bottom:4px;display:block">Untranslatable Post Types</label>
                        <p class="sh-field-hint" style="margin-bottom:6px">Seçilen post type'lar çevrilmez — dil değişse de aynı içerik gösterilir. Örn: <code>contact</code> formu her dilde aynı.</p>
                        <select id="sh-tr-untrans-pt" multiple data-sh-select data-placeholder="Post type seç..." style="width:100%">
                            <?php foreach ( $untrans_pt_options as $val => $lbl ) : ?>
                                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( in_array( $val, $tr_untrans_pt, true ) ); ?>><?php echo esc_html( $lbl ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- synchronized_pages -->
                    <div class="sh-field-group" style="margin-bottom:16px">
                        <label class="sh-field-label" style="margin-bottom:4px;display:block">Sync Pages Content</label>
                        <p class="sh-field-hint" style="margin-bottom:6px">Seçilen sayfalar bir dilde düzenlenince içerik diğer dil versiyonlarına da kopyalanır. Örn: Shop sayfasını Türkçe güncellersen İngilizce'si de güncellenir.</p>
                        <select id="sh-tr-sync-pages" multiple data-sh-select data-placeholder="Sayfa seç..." style="width:100%">
                            <?php foreach ( $all_pages_list as $p ) : ?>
                                <option value="<?php echo (int) $p->ID; ?>" <?php selected( in_array( $p->ID, $tr_sync_pages, false ) ); ?>><?php echo esc_html( $p->post_title ); ?> <span style="color:#9ca3af">[<?php echo esc_html( $p->post_type ); ?>]</span></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- sync_taxonomies_to_language -->
                    <div class="sh-field-group" style="margin-bottom:20px">
                        <label class="sh-field-label" style="margin-bottom:4px;display:block">Sync Taxonomies to Current Language</label>
                        <p class="sh-field-hint" style="margin-bottom:6px">Kod içinde sorgu yapılınca bu taxonomy'lerin term'leri otomatik mevcut dil versiyonuna çevrilir. Örn: <code>product_tag</code> sorgusu TR sayfasında TR karşılığını kullanır.</p>
                        <select id="sh-tr-sync-tax" multiple data-sh-select data-placeholder="Taxonomy seç..." style="width:100%">
                            <?php foreach ( $sync_tax_options as $val => $lbl ) : ?>
                                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( in_array( $val, $tr_sync_tax, true ) ); ?>><?php echo esc_html( $lbl ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="display:flex;align-items:center;gap:10px">
                        <button type="button" id="sh-am-save-translations" class="sh-btn sh-btn-primary">💾 Save</button>
                        <span id="sh-am-translations-msg" style="font-size:13px;color:#15803d;display:none">✓ Kaydedildi</span>
                    </div>
                </div>
                <?php endif; // has_polylang ?>

            </div>
        </div>

        </div><!-- /#sh-stab-translations -->

        <!-- ╔══ TAB: breadcrumb ════════════════════════════════════════╗ -->
        <div id="sh-stab-breadcrumb" class="sh-stab-content" style="<?php echo $sub_tab !== 'breadcrumb' ? 'display:none' : ''; ?>">

        <?php
        // ── Breadcrumb ────────────────────────────────────────────────
        $has_yoast   = defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' );
        $has_ecom_bc = defined( 'ENABLE_ECOMMERCE' ) && ENABLE_ECOMMERCE;

        if ( $has_yoast ) :
            // Mevcut değerleri oku — ACF options prefix'li
            $bc_remove_home     = (bool) get_option( 'options_breadcrumb_remove_home',         false );
            $bc_remove_current  = (bool) get_option( 'options_breadcrumb_remove_current',      false );
            $bc_from_menu       = (bool) get_option( 'options_breadcrumb_from_menu',           false );
            $bc_add_brand       = (bool) get_option( 'options_breadcrumb_add_product_brand',    false );
            $bc_add_tax         = (bool) get_option( 'options_breadcrumb_add_product_taxonomy', false );
            $bc_page_path       = (array) get_option( 'options_breadcrumb_add_page_path',      [] );

            // Repeater için post type ve page seçenekleri
            $bc_pt_options = [];
            foreach ( get_post_types( [ 'public' => true, '_builtin' => false ], 'objects' ) as $pt ) {
                $bc_pt_options[ $pt->name ] = $pt->label;
            }
            $bc_default_lang = function_exists( 'pll_default_language' ) ? pll_default_language() : '';
            $bc_pages_query  = [ 'post_type' => 'page', 'posts_per_page' => 200, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC' ];
            if ( $bc_default_lang ) $bc_pages_query['lang'] = $bc_default_lang;
            $bc_pages_list = get_posts( $bc_pages_query );
        ?>
        <!-- ── Breadcrumb ─── -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px">
            <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;gap:8px">
                <strong>🍞 Breadcrumb</strong>
                <span style="font-size:11px;background:#fce7f3;color:#9d174d;padding:2px 8px;border-radius:10px;font-weight:600">Yoast SEO</span>
            </div>
            <div style="padding:18px">

                <!-- Remove Home -->
                <div class="sh-field-group" style="margin-bottom:14px;display:flex;align-items:flex-start;gap:12px">
                    <label class="sh-toggle" style="margin:2px 0 0">
                        <input type="checkbox" id="sh-bc-remove-home" <?php checked( $bc_remove_home ); ?>>
                        <span class="sh-toggle-slider"></span>
                    </label>
                    <div>
                        <label for="sh-bc-remove-home" style="font-size:13px;color:#374151;cursor:pointer;font-weight:500">Remove "home" from breadcrumb</label>
                        <p class="sh-field-hint" style="margin:2px 0 0">Breadcrumb'ın başındaki "Home" linkini kaldırır.</p>
                    </div>
                </div>

                <!-- Remove Current -->
                <div class="sh-field-group" style="margin-bottom:14px;display:flex;align-items:flex-start;gap:12px">
                    <label class="sh-toggle" style="margin:2px 0 0">
                        <input type="checkbox" id="sh-bc-remove-current" <?php checked( $bc_remove_current ); ?>>
                        <span class="sh-toggle-slider"></span>
                    </label>
                    <div>
                        <label for="sh-bc-remove-current" style="font-size:13px;color:#374151;cursor:pointer;font-weight:500">Remove current from breadcrumb</label>
                        <p class="sh-field-hint" style="margin:2px 0 0">Breadcrumb'daki mevcut sayfanın adını kaldırır — sadece üst seviye linkler görünür.</p>
                    </div>
                </div>

                <!-- From Menu -->
                <div class="sh-field-group" style="margin-bottom:20px;display:flex;align-items:flex-start;gap:12px">
                    <label class="sh-toggle" style="margin:2px 0 0">
                        <input type="checkbox" id="sh-bc-from-menu" <?php checked( $bc_from_menu ); ?>>
                        <span class="sh-toggle-slider"></span>
                    </label>
                    <div>
                        <label for="sh-bc-from-menu" style="font-size:13px;color:#374151;cursor:pointer;font-weight:500">Create breadcrumb from menu</label>
                        <p class="sh-field-hint" style="margin:2px 0 0">Breadcrumb'ı sayfa hiyerarşisinden değil, menü yapısından oluşturur.</p>
                    </div>
                </div>

                <!-- WooCommerce fields — sadece WC yüklüyse -->
                <?php if ( $has_ecom_bc ) : ?>
                <div class="sh-field-group" style="margin-bottom:14px;display:flex;align-items:flex-start;gap:12px">
                    <label class="sh-toggle" style="margin:2px 0 0">
                        <input type="checkbox" id="sh-bc-add-brand" <?php checked( $bc_add_brand ); ?>>
                        <span class="sh-toggle-slider"></span>
                    </label>
                    <div>
                        <label for="sh-bc-add-brand" style="font-size:13px;color:#374151;cursor:pointer;font-weight:500">Add brand to breadcrumb</label>
                        <p class="sh-field-hint" style="margin:2px 0 0">Ürün sayfasında breadcrumb'a marka (product_brand) ekler.</p>
                    </div>
                </div>

                <div class="sh-field-group" style="margin-bottom:20px;display:flex;align-items:flex-start;gap:12px">
                    <label class="sh-toggle" style="margin:2px 0 0">
                        <input type="checkbox" id="sh-bc-add-tax" <?php checked( $bc_add_tax ); ?>>
                        <span class="sh-toggle-slider"></span>
                    </label>
                    <div>
                        <label for="sh-bc-add-tax" style="font-size:13px;color:#374151;cursor:pointer;font-weight:500">Add product taxonomy to breadcrumb</label>
                        <p class="sh-field-hint" style="margin:2px 0 0">Ürün sayfasında breadcrumb'a ürün kategorisini ekler.</p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Add Page Path — Repeater -->
                <div style="border-top:1px solid #f3f4f6;padding-top:16px;margin-bottom:16px">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
                        <div>
                            <div style="font-size:13px;font-weight:600;color:#374151">Add Page Path to Breadcrumb</div>
                            <p class="sh-field-hint" style="margin:2px 0 0">Post type bazlı breadcrumb'a sanal bir sayfa yolu ekler. Örn: Ürün sayfalarında "Home &gt; Shop &gt; Ürün" görünsün.</p>
                        </div>
                        <button type="button" id="sh-bc-add-row" class="sh-btn sh-btn-sm" style="display:flex;align-items:center;gap:4px;flex-shrink:0">
                            <span style="font-size:16px;line-height:1">+</span> Ekle
                        </button>
                    </div>

                    <div id="sh-bc-rows">
                        <?php if ( ! empty( $bc_page_path ) ) :
                            foreach ( $bc_page_path as $bc_row ) :
                                $bc_pt_val   = $bc_row['post_type'] ?? '';
                                $bc_page_val = isset( $bc_row['page'] ) ? (int) $bc_row['page'] : 0;
                        ?>
                        <div class="sh-bc-row" style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:12px 14px;margin-bottom:8px;display:grid;grid-template-columns:1fr 1fr auto;gap:12px;align-items:end">
                            <div>
                                <label style="display:block;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.4px;margin-bottom:4px">Post Type</label>
                                <select class="sh-bc-pt" style="width:100%;min-width:100%;padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;box-sizing:border-box">
                                    <option value="">— Seçin —</option>
                                    <?php foreach ( $bc_pt_options as $v => $l ) : ?>
                                        <option value="<?php echo esc_attr($v); ?>" <?php selected( $bc_pt_val, $v ); ?>><?php echo esc_html($l); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="display:block;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.4px;margin-bottom:4px">Page</label>
                                <select class="sh-bc-page" style="width:100%;min-width:100%;padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;box-sizing:border-box">
                                    <option value="">— Seçin —</option>
                                    <?php foreach ( $bc_pages_list as $p ) : ?>
                                        <option value="<?php echo (int)$p->ID; ?>" <?php selected( $bc_page_val, $p->ID ); ?>><?php echo esc_html($p->post_title); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="button" class="sh-bc-remove" style="background:none;border:none;cursor:pointer;color:#9ca3af;font-size:20px;line-height:1;padding:4px;margin-bottom:2px" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#9ca3af'">×</button>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>

                    <!-- Template (hidden) -->
                    <div id="sh-bc-row-tpl" style="display:none">
                        <div class="sh-bc-row" style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:12px 14px;margin-bottom:8px;display:grid;grid-template-columns:1fr 1fr auto;gap:12px;align-items:end">
                            <div>
                                <label style="display:block;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.4px;margin-bottom:4px">Post Type</label>
                                <select class="sh-bc-pt" style="width:100%;min-width:100%;padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;box-sizing:border-box">
                                    <option value="">— Seçin —</option>
                                    <?php foreach ( $bc_pt_options as $v => $l ) : ?>
                                        <option value="<?php echo esc_attr($v); ?>"><?php echo esc_html($l); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="display:block;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.4px;margin-bottom:4px">Page</label>
                                <select class="sh-bc-page" style="width:100%;min-width:100%;padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;box-sizing:border-box">
                                    <option value="">— Seçin —</option>
                                    <?php foreach ( $bc_pages_list as $p ) : ?>
                                        <option value="<?php echo (int)$p->ID; ?>"><?php echo esc_html($p->post_title); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="button" class="sh-bc-remove" style="background:none;border:none;cursor:pointer;color:#9ca3af;font-size:20px;line-height:1;padding:4px;margin-bottom:2px" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#9ca3af'">×</button>
                        </div>
                    </div>
                </div>

                <div style="display:flex;align-items:center;gap:10px">
                    <button type="button" id="sh-am-save-bc" class="sh-btn sh-btn-primary">💾 Save</button>
                    <span id="sh-am-bc-msg" style="font-size:13px;color:#15803d;display:none">✓ Kaydedildi</span>
                </div>

            </div>
        </div>
        <?php endif; // has_yoast ?>

        </div><!-- /#sh-stab-breadcrumb -->

            </div><!-- /kart body -->
        </div><!-- /beyaz kart -->

        </div><!-- /#sh-main wrapper -->

        <script>
        (function() {
            // sh-select'leri (data-sh-select attribute'lu veya ID ile init edilmiş) init et
            // display:none içindeyken init olmamış olabilirler — tab gösterilince çağrılır
            function initSelectsInTab(tabEl) {
                if (typeof window.shSelect === 'undefined') return;
                // data-sh-select attribute'lu tüm select'ler
                tabEl.querySelectorAll('[data-sh-select]').forEach(function(el) {
                    if (!el.dataset.shSelectInit) {
                        window.shSelect.init(el, {
                            placeholder: el.dataset.placeholder || 'Ara ve seç...'
                        });
                    }
                });
                // Cache tab'ındaki doğrudan ID ile init edilenler (data-sh-select attr'u olmayabilir)
                ['sh-exclude-post-types-archive','sh-exclude-posts','sh-exclude-taxonomies'].forEach(function(id) {
                    var el = tabEl.querySelector('#' + id);
                    if (el && !el.dataset.shSelectInit) {
                        window.shSelect.init(el, { placeholder: 'Seçiniz...' });
                    }
                });
            }

            var btns = document.querySelectorAll('.sh-stab-btn');
            var tabs = document.querySelectorAll('.sh-stab-content');

            // Sayfa yüklenince görünür olan tab'daki select'leri hemen init et
            tabs.forEach(function(t) {
                if (t.style.display !== 'none') {
                    initSelectsInTab(t);
                }
            });

            btns.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var target = this.dataset.tab;
                    // tab butonları
                    btns.forEach(function(b) { b.classList.remove('active'); });
                    this.classList.add('active');
                    // tab içerikleri göster/gizle
                    tabs.forEach(function(t) {
                        var isActive = t.id === 'sh-stab-' + target;
                        t.style.display = isActive ? '' : 'none';
                        // Tab ilk kez gösterilince select'leri init et
                        if (isActive) {
                            initSelectsInTab(t);
                        }
                    });
                });
            });
        })();
        </script>

        <?php
    }

    // ─── Page Assets Tab ──────────────────────────────────────────────────────

    private static function renderPageAssetsTab( string $nonce, string $ajax_url ): void
    {
        ?>
        <div class="sh-layout">
        <div class="sh-main">

        <?php
            if ( ! class_exists( '\PageAssetsExtractor' ) ) :
                echo '<div class="sh-card" style="padding:20px;color:#9ca3af">Page Assets Extractor not available.</div>';
            else :
                try {
                    ob_start();
                    \PageAssetsExtractor::get_instance()->display_page_assets_table();
                    $html = ob_get_clean();
                    $html = preg_replace( '/^<div[^>]*\bwrap\b[^>]*>\s*/i', '', $html, 1 );
                    $html = preg_replace( '/^<div[^>]*\bpae-wrap\b[^>]*>\s*/i', '', $html, 1 );
                    $html = preg_replace( '/<\/div>\s*<\/div>\s*$/i', '', $html, 1 );
                    echo $html;
                } catch ( \Throwable $e ) {
                    echo '<p style="color:red;padding:20px">Hata: ' . esc_html( $e->getMessage() ) . '</p>';
                }
            endif;
        ?>

        </div><!-- .sh-main -->
        <div class="sh-sidebar"><?php self::renderSidebar( AssetSettings::get() ); ?></div>
        </div><!-- .sh-layout -->
        <?php
    }

    // ─── Filters Tab ──────────────────────────────────────────────────────────

    private static function renderFiltersTab(): void
    {
        $filters = [
            // CSS
            [ 'sh_frontend_styles',    'filter', 'Add CSS to frontend', "add_filter('sh_frontend_styles', function(\$styles) {\n    \$styles['my-css'] = ['url' => MY_URL . 'style.css', 'deps' => [], 'version' => '1.0'];\n    return \$styles;\n});" ],
            [ 'sh_admin_styles',       'filter', 'Add CSS to admin', "add_filter('sh_admin_styles', function(\$styles) {\n    \$styles['my-admin'] = ['url' => MY_URL . 'admin.css'];\n    return \$styles;\n});" ],
            [ 'sh_lazy_css_handles',   'filter', 'Add handles to lazy load list', "add_filter('sh_lazy_css_handles', fn(\$h) => array_merge(\$h, ['my-heavy-css']));" ],
            [ 'sh_lazy_css_patterns',  'filter', 'Add URL patterns to lazy load', "add_filter('sh_lazy_css_patterns', fn(\$p) => array_merge(\$p, ['my-plugin']));" ],
            [ 'sh_dequeue_styles',     'filter', 'Dequeue CSS handles', "add_filter('sh_dequeue_styles', fn(\$h) => array_merge(\$h, ['plugin-bloated-css']));" ],
            [ 'sh_inline_head_css',    'filter', 'Add inline CSS to <head>', "add_filter('sh_inline_head_css', fn(\$css) => \$css . '.my{color:red}');" ],
            // JS
            [ 'sh_frontend_scripts',   'filter', 'Add JS to frontend footer', "add_filter('sh_frontend_scripts', function(\$scripts) {\n    \$scripts['my-js'] = ['url' => MY_URL . 'script.js', 'footer' => true, 'defer' => true];\n    return \$scripts;\n});" ],
            [ 'sh_admin_scripts',      'filter', 'Add JS to admin', "add_filter('sh_admin_scripts', function(\$scripts) {\n    \$scripts['my-admin-js'] = ['url' => MY_URL . 'admin.js', 'deps' => ['jquery']];\n    return \$scripts;\n});" ],
            [ 'sh_defer_js_handles',   'filter', 'Add handles to defer list', "add_filter('sh_defer_js_handles', fn(\$h) => array_merge(\$h, ['my-heavy-js']));" ],
            [ 'sh_lazy_js_patterns',   'filter', 'Add patterns to lazy JS load', "add_filter('sh_lazy_js_patterns', fn(\$p) => array_merge(\$p, ['my-heavy-lib']));" ],
            [ 'sh_dequeue_scripts',    'filter', 'Dequeue JS handles', "add_filter('sh_dequeue_scripts', fn(\$h) => array_merge(\$h, ['plugin-bloated-js']));" ],
            [ 'sh_inline_footer_js',   'filter', 'Add inline JS to footer', "add_filter('sh_inline_footer_js', fn(\$js) => \$js . 'console.log(\"loaded\");');" ],
            [ 'sh_inline_head_js',     'filter', 'Add inline JS to <head>', "add_filter('sh_inline_head_js', fn(\$js) => \$js . 'var myVar=1;');" ],
            // Preload / Resource hints
            [ 'sh_preconnect_domains', 'filter', 'Add preconnect domains', "add_filter('sh_preconnect_domains', fn(\$d) => array_merge(\$d, ['https://cdn.example.com' => []]));" ],
                        [ 'sh_preload_resources',  'filter', 'Add preload resources', "add_filter('sh_preload_resources', fn(\$r) => array_merge(\$r, [['url' => MY_URL . 'hero.jpg', 'as' => 'image']]));" ],
            [ 'sh_critical_css',       'filter', 'Override critical CSS', "add_filter('sh_critical_css', fn(\$css) => \$css . '.hero{min-height:400px}');" ],
            [ 'sh_inline_admin_head_js','filter', 'Add inline JS to admin head', "add_filter('sh_inline_admin_head_js', fn(\$js) => \$js . 'var adminVar=1;');" ],
        ];
        ?>
        <div class="sh-layout">
        <div class="sh-main">

        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden">
            <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6">
                <strong>Available Filters</strong>
                <p style="margin:4px 0 0;font-size:12px;color:#9ca3af">Add these to your theme's <code>functions.php</code> or any plugin file.</p>
            </div>
            <table style="width:100%;border-collapse:collapse">
                <thead><tr>
                    <th style="padding:10px 18px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #f3f4f6;width:200px">Filter</th>
                    <th style="padding:10px 18px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #f3f4f6;width:200px">Description</th>
                    <th style="padding:10px 18px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #f3f4f6">Example</th>
                </tr></thead>
                <tbody>
                <?php foreach ( $filters as [$name, $type, $desc, $example] ) :
                    $col = '#0ea5e9';
                ?>
                <tr style="border-bottom:1px solid #f9fafb">
                    <td style="padding:10px 18px;vertical-align:top">
                        <code style="font-size:11px;background:#f3f4f6;padding:2px 6px;border-radius:3px;display:block;word-break:break-all"><?php echo esc_html($name); ?></code>
                        <span style="display:inline-block;margin-top:4px;padding:1px 6px;border-radius:20px;font-size:10px;font-weight:600;background:<?php echo $col; ?>22;color:<?php echo $col; ?>"><?php echo $type; ?></span>
                    </td>
                    <td style="padding:10px 18px;font-size:13px;color:#374151;vertical-align:top"><?php echo esc_html($desc); ?></td>
                    <td style="padding:10px 18px;vertical-align:top">
                        <div style="position:relative">
                            <pre style="background:#0d1117;color:#c8d6e5;padding:10px 12px;border-radius:6px;font-size:11px;font-family:Consolas,monospace;margin:0;overflow-x:auto;white-space:pre-wrap"><?php echo esc_html($example); ?></pre>
                            <button type="button" class="sh-am-copy-btn"
                                data-code="<?php echo esc_attr($example); ?>"
                                style="position:absolute;top:6px;right:6px;background:#374151;color:#9ca3af;border:none;border-radius:4px;padding:2px 8px;font-size:10px;cursor:pointer">Copy</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        </div>
        <div class="sh-sidebar"><?php self::renderSidebar(AssetSettings::get()); ?></div>
        </div>
        <?php
    }

    // ─── Cache Tab ────────────────────────────────────────────────────────────

    private static function renderCacheTab( string $nonce, string $ajax_url ): void
    {
        $data = self::getManifestCacheData();
        $manifest   = $data['manifest'];
        $cache_dir  = $data['cache_dir'];
        $static_url = defined('STATIC_URL') ? STATIC_URL : '';
        ?>
        <div class="sh-layout">
        <div class="sh-main">

        <?php /* ── TEMPLATES ─────────────────────────────────────────────── */ ?>
        <?php if ( ! empty( $manifest['templates'] ) ) : ?>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-bottom:16px">
            <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;gap:10px">
                <strong>CSS Templates</strong>
                <span style="font-size:11px;color:#9ca3af"><?php echo count($manifest['templates']); ?> unique template</span>
                <button type="button" id="sh-am-purge" class="sh-btn sh-btn-sm" style="margin-left:auto">Clear Cache</button>
            </div>
            <?php foreach ( $manifest['templates'] as $fp => $tpl ) :
                // Bu template'i kullanan içerikler
                $usages = [];
                foreach ( $manifest['content_usage'] ?? [] as $content_key => $usage ) {
                    if ( ( $usage['structure_fp'] ?? '' ) === $fp ) {
                        $usages[] = $content_key;
                    }
                }
                $plugin_key   = $tpl['plugins'] ?? '';
                // Template'in plugin key'i son fetch'e ait olabilir.
                // content_usage'dan bu template'i kullanan tüm plugin bundle'larını topla
                // ve en zenginini (en fazla plugin içereni) göster.
                $all_plugin_keys = [];
                foreach ( $manifest['content_usage'] ?? [] as $ck => $cu ) {
                    if ( ( $cu['structure_fp'] ?? '' ) === $fp && ! empty( $cu['plugins_key'] ) ) {
                        $pk = $cu['plugins_key'];
                        if ( isset( $manifest['plugins'][ $pk ] ) ) {
                            $all_plugin_keys[ $pk ] = count( $manifest['plugins'][ $pk ]['plugins_list'] ?? [] );
                        }
                    }
                }
                // En fazla plugin içeren bundle'ı seç
                if ( ! empty( $all_plugin_keys ) ) {
                    arsort( $all_plugin_keys );
                    $plugin_key = array_key_first( $all_plugin_keys );
                }
                $plugin_data  = $manifest['plugins'][ $plugin_key ] ?? null;
                $css_file     = $tpl['css'] ?? '';
                $crit_file    = $tpl['critical_css'] ?? '';
                $css_size     = $css_file && file_exists( STATIC_PATH . $css_file ) ? size_format( filesize( STATIC_PATH . $css_file ), 1 ) : '—';
                $crit_size    = $crit_file && file_exists( STATIC_PATH . $crit_file ) ? size_format( filesize( STATIC_PATH . $crit_file ), 1 ) : null;
                // Kaç unique içerik bu template'i kullanıyor?
                $usage_count  = count( $usages );
            ?>
            <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6">
                <div style="display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap">

                    <!-- Fingerprint + dosyalar -->
                    <div style="flex:1;min-width:0">
                        <div style="font-family:Consolas,monospace;font-size:11px;color:#6b7280;margin-bottom:8px">
                            <span style="color:#374151;font-weight:600">Template</span>
                            <span style="margin-left:8px;opacity:.6"><?php echo esc_html( substr($fp,0,12) ); ?>…</span>
                            <?php if ( $usage_count > 0 ) : ?>
                            <span style="margin-left:10px;background:#f3f4f6;color:#374151;border-radius:10px;padding:1px 8px;font-size:10px;font-family:system-ui">
                                <?php echo $usage_count; ?> content<?php echo $usage_count > 1 ? 's' : ''; ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px">
                            <?php if ( $css_file ) : ?>
                            <span style="display:inline-flex;align-items:center;gap:4px;background:#eff6ff;color:#1d4ed8;border-radius:4px;padding:3px 8px;font-size:11px">
                                <span>CSS</span>
                                <strong><?php echo $css_size; ?></strong>
                            </span>
                            <?php endif; ?>
                            <?php if ( $tpl['css_rtl'] ?? '' ) : ?>
                            <span style="display:inline-flex;align-items:center;gap:4px;background:#f5f3ff;color:#6d28d9;border-radius:4px;padding:3px 8px;font-size:11px">
                                CSS RTL
                            </span>
                            <?php endif; ?>
                            <?php if ( $crit_size !== null ) : ?>
                            <span style="display:inline-flex;align-items:center;gap:4px;background:#f0fdf4;color:#15803d;border-radius:4px;padding:3px 8px;font-size:11px">
                                <span>Critical</span>
                                <strong><?php echo $crit_size; ?></strong>
                            </span>
                            <?php endif; ?>
                        </div>

                        <!-- Kullanan içerikler -->
                        <?php if ( ! empty($usages) ) :
                            // Grouped key varsa sadece onu göster — binlerce individual badge gereksiz
                            $has_grouped = false;
                            foreach ( $usages as $uk ) {
                                if ( str_starts_with( $uk, 'grouped:' ) ) { $has_grouped = true; break; }
                            }
                            $max_badges    = $has_grouped ? 999 : 6;
                            $shown_usages  = $has_grouped
                                ? array_filter( $usages, fn($u) => str_starts_with($u,'grouped:') )
                                : array_slice( $usages, 0, $max_badges );
                            $hidden_count  = $has_grouped ? 0 : ( count($usages) - count($shown_usages) );
                        ?>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
                            <?php foreach ( $shown_usages as $uk ) :
                                // content_key format: {post_type}:{id} veya grouped:{context}:{post_type}
                                $label   = $uk;
                                $edit_link = '';

                                if ( str_starts_with( $uk, 'grouped:' ) ) {
                                    // grouped:post:page  →  "📦 88 Ürün" (adet + post type label)
                                    $parts     = explode( ':', $uk );
                                    $post_type = $parts[2] ?? $parts[1] ?? $uk;
                                    $pto       = get_post_type_object( $post_type );
                                    $pt_label  = $pto ? $pto->labels->name : $post_type;
                                    // Bu template'i kullanan toplam content_usage sayısını al
                                    $grouped_count = count( $usages );
                                    $label     = '📦 ' . $grouped_count . ' ' . esc_html( $pt_label );
                                } elseif ( str_starts_with( $uk, 'term:' ) ) {
                                    // term:{id}
                                    $tid   = (int) substr( $uk, 5 );
                                    $term  = get_term( $tid );
                                    $label = $term && ! is_wp_error( $term )
                                        ? esc_html( $term->name )
                                        : "Term #$tid";
                                    if ( $term && ! is_wp_error( $term ) ) {
                                        $edit_link = get_edit_term_link( $term->term_id, $term->taxonomy );
                                    }
                                } elseif ( str_starts_with( $uk, 'dynamic:' ) ) {
                                    // dynamic:search, dynamic:404, dynamic:woo_account_orders_en vb.
                                    $dkey = substr( $uk, 8 );
                                    // PAE'deki bilinen key → human label map
                                    $dynamic_labels = [
                                        'search' => '🔍 Search',
                                        '404'    => '⚠️ 404',
                                    ];
                                    if ( isset( $dynamic_labels[ $dkey ] ) ) {
                                        $label = $dynamic_labels[ $dkey ];
                                    } else {
                                        // woo_account_orders_en → "Orders (en)" gibi
                                        // Son _xx dil suffix'ini ayır
                                        $clean = preg_replace( '/^woo_account_/', '', $dkey );
                                        $lang  = '';
                                        if ( preg_match( '/^(.+)_([a-z]{2})$/', $clean, $lm ) ) {
                                            $clean = $lm[1];
                                            $lang  = ' (' . strtoupper( $lm[2] ) . ')';
                                        }
                                        // snake_case → Title Case
                                        $clean = ucwords( str_replace( [ '_', '-' ], ' ', $clean ) );
                                        $label = '⚡ ' . esc_html( $clean . $lang );
                                    }
                                } elseif ( preg_match( '/^([a-z0-9_-]+):(\d+)$/', $uk, $m ) ) {
                                    // {post_type}:{id}  — post:123, page:456, product:789 vb.
                                    $pid      = (int) $m[2];
                                    $title    = get_the_title( $pid );
                                    $label    = $title ?: ( ucfirst( $m[1] ) . ' #' . $pid );
                                    $edit_link = get_edit_post_link( $pid ) ?: '';
                                }
                            ?>
                            <span style="background:<?php echo str_starts_with($uk,'dynamic:') ? '#f0f9ff' : (str_starts_with($uk,'grouped:') ? '#faf5ff' : '#f3f4f6'); ?>;color:<?php echo str_starts_with($uk,'dynamic:') ? '#0369a1' : (str_starts_with($uk,'grouped:') ? '#6d28d9' : '#374151'); ?>;border-radius:4px;padding:2px 8px;font-size:11px;border:1px solid <?php echo str_starts_with($uk,'dynamic:') ? '#bae6fd' : (str_starts_with($uk,'grouped:') ? '#ddd6fe' : '#e5e7eb'); ?>">
                                <?php if ( $edit_link ) : ?>
                                <a href="<?php echo esc_url($edit_link); ?>" target="_blank" style="color:inherit;text-decoration:none"><?php echo $label; ?></a>
                                <?php else : ?>
                                <?php echo $label; ?>
                                <?php endif; ?>
                            </span>
                            <?php endforeach; ?>
                            <?php if ( $hidden_count > 0 ) : ?>
                            <span style="background:#f3f4f6;color:#6b7280;border-radius:4px;padding:2px 8px;font-size:11px;font-style:italic">+<?php echo $hidden_count; ?> more</span>
                            <?php endif; ?>
                        </div>
                        <?php else : ?>
                        <span style="font-size:11px;color:#d1d5db;font-style:italic">No content mapped yet</span>
                        <?php endif; ?>
                    </div>

                    <!-- Plugin bundle -->
                    <?php if ( $plugin_data ) : ?>
                    <div style="background:#f9fafb;border-radius:6px;padding:10px 14px;min-width:160px;font-size:11px">
                        <div style="font-weight:600;color:#374151;margin-bottom:6px">Plugin Bundle</div>
                        <?php foreach ( $plugin_data['plugins_list'] ?? [] as $pl ) : ?>
                        <span style="display:inline-block;background:#e0e7ff;color:#3730a3;border-radius:3px;padding:1px 6px;margin:1px;font-size:10px"><?php echo esc_html($pl); ?></span>
                        <?php endforeach; ?>
                        <?php if ( $plugin_data['css'] ?? '' ) :
                            $ps = file_exists(STATIC_PATH . $plugin_data['css']) ? size_format(filesize(STATIC_PATH . $plugin_data['css']),1) : '—';
                        ?>
                        <div style="margin-top:6px;color:#6b7280">CSS: <strong><?php echo $ps; ?></strong></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php /* ── ORPHAN / MISC FILES ──────────────────────────────────── */ ?>
        <?php if ( ! empty( $data['misc_files'] ) ) : ?>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-bottom:16px">
            <div style="padding:12px 18px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;gap:8px">
                <strong style="font-size:13px">Other Cache Files</strong>
                <span style="font-size:11px;color:#9ca3af"><?php echo count($data['misc_files']); ?> files · <?php echo $data['misc_total']; ?></span>
            </div>
            <table style="width:100%;border-collapse:collapse">
                <thead><tr>
                    <?php foreach(['File','Type','Size','Modified'] as $h): ?>
                    <th style="padding:8px 18px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #f3f4f6"><?php echo $h; ?></th>
                    <?php endforeach; ?>
                </tr></thead>
                <tbody>
                <?php foreach ( $data['misc_files'] as $mf ) : ?>
                <tr style="border-bottom:1px solid #f9fafb">
                    <td style="padding:8px 18px;font-family:Consolas,monospace;font-size:11px;color:#374151;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo esc_html($mf['name']); ?></td>
                    <td style="padding:8px 18px">
                        <span style="display:inline-block;padding:1px 7px;border-radius:3px;font-size:10px;font-weight:600;background:<?php echo esc_attr($mf['type_color']); ?>22;color:<?php echo esc_attr($mf['type_color']); ?>"><?php echo esc_html($mf['type']); ?></span>
                    </td>
                    <td style="padding:8px 18px;font-size:12px;color:#6b7280"><?php echo esc_html($mf['size']); ?></td>
                    <td style="padding:8px 18px;font-size:12px;color:#6b7280"><?php echo esc_html($mf['modified']); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if ( empty($manifest['templates']) && empty($data['misc_files']) ) : ?>
        <div style="background:#f9fafb;border-radius:8px;padding:32px;text-align:center;color:#9ca3af;font-size:13px">
            No cache files yet. Run PAE to generate page-specific CSS bundles.
        </div>
        <?php endif; ?>

        </div>
        <div class="sh-sidebar"><?php self::renderSidebar(AssetSettings::get()); ?></div>
        </div>

        <script>
        (function() {
            var btn = document.getElementById('sh-am-purge');
            if (!btn) return;
            btn.addEventListener('click', function() {
                btn.disabled = true;
                btn.textContent = 'Clearing...';
                fetch(<?php echo wp_json_encode($ajax_url); ?>, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=sh_am_purge_cache&nonce=' + encodeURIComponent(<?php echo wp_json_encode($nonce); ?>)
                }).then(function(r){ return r.json(); }).then(function(res) {
                    btn.textContent = res.success ? '✓ Cleared' : '✗ Error';
                    if (res.success) setTimeout(function(){ location.reload(); }, 800);
                    else btn.disabled = false;
                }).catch(function() {
                    btn.textContent = '✗ Failed'; btn.disabled = false;
                });
            });
        })();
        </script>
        <?php
    }

    // ─── Debug Tab ────────────────────────────────────────────────────────────

    public static function ajaxDebugAssets(): void {
        check_ajax_referer( 'sh_am_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $url = esc_url_raw( $_POST['url'] ?? '' );
        if ( ! $url ) wp_send_json_error( 'URL gerekli' );

        $response = wp_remote_get( $url, [
            'timeout'    => 15,
            'user-agent' => 'Mozilla/5.0 (SaltHareket Debug)',
            'headers'    => [ 'X-Internal-Fetch' => '1' ],
            'sslverify'  => false,
        ]);

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( $response->get_error_message() );
        }

        $html = wp_remote_retrieve_body( $response );
        $code = wp_remote_retrieve_response_code( $response );

        // <noscript> bloklarını çıkar — lazy load fallback'leri duplicate olarak görünmesin
        $html_clean = preg_replace( '/<noscript[^>]*>.*?<\/noscript>/is', '', $html );

        // CSS — <link rel="stylesheet"> veya rel="preload" as="style" tag'lerini topla
        $css_assets = [];
        preg_match_all( '/<link[^>]+(?:rel=["\']stylesheet["\']|as=["\']style["\'])[^>]*>/i', $html_clean, $link_matches );
        foreach ( $link_matches[0] as $tag ) {
            preg_match( '/href=["\']([^"\']+)["\']/', $tag, $href );
            preg_match( '/id=["\']([^"\']+)["\']/', $tag, $id );
            if ( ! empty( $href[1] ) ) {
                $css_assets[] = [
                    'handle' => isset( $id[1] ) ? preg_replace( '/-css$/', '', $id[1] ) : '—',
                    'url'    => $href[1],
                    'short'  => self::_short_url( $href[1] ),
                    'type'   => 'css',
                ];
            }
        }

        // Inline <style> tag'leri — id olanları topla
        $inline_styles = [];
        preg_match_all( '/<style[^>]*id=["\']([^"\']+)["\'][^>]*>/i', $html_clean, $style_id_matches );
        foreach ( $style_id_matches[1] as $sid ) {
            $inline_styles[] = preg_replace( '/-inline-css$|-css$/', '', $sid );
        }
        $inline_style_count = count( preg_match_all( '/<style[^>]*>/i', $html_clean, $sm ) ? $sm[0] : [] );

        // JS — <script src="..."> tag'lerini topla
        $js_assets = [];
        preg_match_all( '/<script[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html_clean, $script_matches, PREG_SET_ORDER );
        foreach ( $script_matches as $m ) {
            preg_match( '/id=["\']([^"\']+)["\']/', $m[0], $id );
            $js_assets[] = [
                'handle' => isset( $id[1] ) ? preg_replace( '/-js$/', '', $id[1] ) : '—',
                'url'    => $m[1],
                'short'  => self::_short_url( $m[1] ),
                'type'   => 'js',
            ];
        }

        // Inline <script> tag'leri — id olanları topla
        $inline_scripts = [];
        preg_match_all( '/<script(?![^>]*\bsrc\b)[^>]*id=["\']([^"\']+)["\'][^>]*>/i', $html_clean, $script_id_matches );
        foreach ( $script_id_matches[1] as $sid ) {
            $inline_scripts[] = preg_replace( '/-js$/', '', $sid );
        }
        $inline_script_count = count( preg_match_all( '/<script(?![^>]*\bsrc\b)[^>]*>/i', $html_clean, $sm2 ) ? $sm2[0] : [] );

        wp_send_json_success( [
            'status'             => $code,
            'css'                => $css_assets,
            'js'                 => $js_assets,
            'inline_styles'      => $inline_styles,
            'inline_scripts'     => $inline_scripts,
            'inline_css_count'   => $inline_style_count,
            'inline_js_count'    => $inline_script_count,
            'css_count'          => count( $css_assets ),
            'js_count'           => count( $js_assets ),
        ]);
    }

    private static function _short_url( string $url ): string {
        $url = preg_replace( '/^https?:\/\/[^\/]+/', '', $url );
        $url = preg_replace( '/\?.*$/', '', $url );
        if ( strlen( $url ) > 60 ) {
            $url = '...' . substr( $url, -57 );
        }
        return $url;
    }

    private static function renderDebugTab(): void
    {
        $home = trailingslashit( home_url() );
        ?>
        <div class="sh-layout">
        <div class="sh-main">

        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-bottom:16px">
            <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6">
                <strong>Frontend Asset Analizi</strong>
                <p style="margin:4px 0 0;font-size:12px;color:#9ca3af">Bir sayfa URL'i gir — gerçekte yüklenen CSS ve JS dosyaları listelenir.</p>
            </div>
            <div style="padding:16px 18px;display:flex;gap:8px;align-items:center">
                <input type="url" id="sh-debug-url" value="<?php echo esc_attr( $home ); ?>"
                    style="flex:1;padding:7px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px">
                <button type="button" id="sh-debug-analyze" class="sh-btn sh-btn-primary">Analiz Et</button>
            </div>
        </div>

        <div id="sh-debug-result" style="display:none">

            <div style="display:flex;gap:10px;margin-bottom:12px" id="sh-debug-summary"></div>

            <!-- CSS tablosu -->
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-bottom:16px">
                <div style="padding:12px 18px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;gap:8px">
                    <strong>CSS</strong>
                    <span id="sh-css-count" style="background:#f1f5f9;color:#475569;font-size:11px;font-weight:600;padding:2px 8px;border-radius:20px"></span>
                    <span id="sh-inline-css-count" style="background:#ede9fe;color:#7c3aed;font-size:11px;font-weight:600;padding:2px 8px;border-radius:20px;display:none"></span>
                </div>
                <table style="width:100%;border-collapse:collapse" id="sh-css-table">
                    <thead><tr>
                        <th style="padding:8px 18px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #f3f4f6;width:180px">Handle</th>
                        <th style="padding:8px 18px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #f3f4f6">Dosya</th>
                    </tr></thead>
                    <tbody id="sh-css-body"></tbody>
                </table>
            </div>

            <!-- JS tablosu -->
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden">
                <div style="padding:12px 18px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;gap:8px">
                    <strong>JS</strong>
                    <span id="sh-js-count" style="background:#f1f5f9;color:#475569;font-size:11px;font-weight:600;padding:2px 8px;border-radius:20px"></span>
                    <span id="sh-inline-js-count" style="background:#fef9c3;color:#92400e;font-size:11px;font-weight:600;padding:2px 8px;border-radius:20px;display:none"></span>
                </div>
                <table style="width:100%;border-collapse:collapse">
                    <thead><tr>
                        <th style="padding:8px 18px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #f3f4f6;width:180px">Handle</th>
                        <th style="padding:8px 18px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #f3f4f6">Dosya</th>
                    </tr></thead>
                    <tbody id="sh-js-body"></tbody>
                </table>
            </div>
        </div>

        <div id="sh-debug-error" style="display:none;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:14px 18px;color:#dc2626;font-size:13px"></div>
        <div id="sh-debug-loading" style="display:none;padding:20px;text-align:center;color:#9ca3af;font-size:13px">Analiz ediliyor...</div>

        </div><!-- .sh-main -->
        <div class="sh-sidebar"><?php self::renderSidebar( AssetSettings::get() ); ?></div>
        </div>

        <script>
        (function($) {
            var NONCE = <?php echo wp_json_encode( wp_create_nonce('sh_am_nonce') ); ?>;
            var AJAX  = <?php echo wp_json_encode( admin_url('admin-ajax.php') ); ?>;

            function getInlineColor(id) {
                if (/rocket-/.test(id)) return '#ef4444';
                if (/^sh-|^locale|^footer-\d|^pre-\d|^main-\d|^site-config|^critical-css-remover|^data_block_|^map_block_|^config_block_/.test(id)) return '#16a34a';
                if (/woocommerce|wc-|pll_|jquery-|wp-api|wp-i18n|wp-util|woo-/.test(id)) return '#f59e0b';
                if (/^wp-/.test(id)) return '#3b82f6';
                return '#6b7280';
            }

            $('#sh-debug-analyze').on('click', function() {
                var url = $('#sh-debug-url').val().trim();
                if (!url) return;

                $('#sh-debug-result, #sh-debug-error').hide();
                $('#sh-debug-loading').show();

                $.post(AJAX, { action: 'sh_am_debug_assets', nonce: NONCE, url: url }, function(res) {
                    $('#sh-debug-loading').hide();
                    if (!res.success) {
                        $('#sh-debug-error').text('Hata: ' + res.data).show();
                        return;
                    }
                    var d = res.data;

                    // Summary badges
                    var summary = '';
                    summary += '<span style="background:#f1f5f9;border:1px solid #e2e8f0;border-radius:6px;padding:6px 12px;font-size:12px"><strong>' + d.css_count + '</strong> CSS dosyası</span>';
                    summary += '<span style="background:#f1f5f9;border:1px solid #e2e8f0;border-radius:6px;padding:6px 12px;font-size:12px"><strong>' + d.js_count + '</strong> JS dosyası</span>';
                    if (d.inline_css_count) summary += '<span style="background:#ede9fe;border:1px solid #ddd6fe;border-radius:6px;padding:6px 12px;font-size:12px"><strong>' + d.inline_css_count + '</strong> inline &lt;style&gt;</span>';
                    if (d.inline_js_count)  summary += '<span style="background:#fef9c3;border:1px solid #fde68a;border-radius:6px;padding:6px 12px;font-size:12px"><strong>' + d.inline_js_count + '</strong> inline &lt;script&gt;</span>';
                    $('#sh-debug-summary').html(summary);

                    // CSS count badge
                    $('#sh-css-count').text(d.css_count + ' dosya');

                    // CSS rows
                    var cssHtml = '';
                    if (d.css.length) {
                        d.css.forEach(function(item) {
                            var isTheme = item.url.indexOf('/themes/') !== -1;
                            var isPlugin = item.url.indexOf('/plugins/') !== -1;
                            var color = isTheme ? '#16a34a' : (isPlugin ? '#d97706' : '#6b7280');
                            var tag = isTheme ? 'theme' : (isPlugin ? 'plugin' : 'ext');
                            cssHtml += '<tr style="border-bottom:1px solid #f9fafb">';
                            cssHtml += '<td style="padding:7px 18px;font-family:Consolas,monospace;font-size:11px;color:#374151">' + item.handle + '</td>';
                            cssHtml += '<td style="padding:7px 18px;font-size:11px;color:#6b7280">';
                            cssHtml += '<span style="display:inline-block;padding:1px 6px;border-radius:10px;font-size:10px;font-weight:600;background:' + color + '22;color:' + color + ';margin-right:6px">' + tag + '</span>';
                            cssHtml += item.short;
                            cssHtml += '</td></tr>';
                        });
                    } else {
                        cssHtml = '<tr><td colspan="2" style="padding:12px 18px;color:#9ca3af;font-size:12px">CSS dosyası bulunamadı.</td></tr>';
                    }
                    $('#sh-css-body').html(cssHtml);

                    // JS count badge
                    $('#sh-js-count').text(d.js_count + ' dosya');

                    // JS rows
                    var jsHtml = '';
                    if (d.js.length) {
                        d.js.forEach(function(item) {
                            var isTheme = item.url.indexOf('/themes/') !== -1;
                            var isPlugin = item.url.indexOf('/plugins/') !== -1;
                            var color = isTheme ? '#16a34a' : (isPlugin ? '#d97706' : '#6b7280');
                            var tag = isTheme ? 'theme' : (isPlugin ? 'plugin' : 'ext');
                            jsHtml += '<tr style="border-bottom:1px solid #f9fafb">';
                            jsHtml += '<td style="padding:7px 18px;font-family:Consolas,monospace;font-size:11px;color:#374151">' + item.handle + '</td>';
                            jsHtml += '<td style="padding:7px 18px;font-size:11px;color:#6b7280">';
                            jsHtml += '<span style="display:inline-block;padding:1px 6px;border-radius:10px;font-size:10px;font-weight:600;background:' + color + '22;color:' + color + ';margin-right:6px">' + tag + '</span>';
                            jsHtml += item.short;
                            jsHtml += '</td></tr>';
                        });
                    } else {
                        jsHtml = '<tr><td colspan="2" style="padding:12px 18px;color:#9ca3af;font-size:12px">JS dosyası bulunamadı.</td></tr>';
                    }
                    $('#sh-js-body').html(jsHtml);

                    // Inline CSS listesi
                    if (d.inline_styles && d.inline_styles.length) {
                        var inlineCssHtml = d.inline_styles.map(function(id) {
                            var color = getInlineColor(id);
                            return '<span style="display:inline-block;margin:2px 4px 2px 0;padding:2px 8px;border-radius:12px;font-size:11px;font-family:Consolas,monospace;background:' + color + '18;border:1px solid ' + color + '44;color:' + color + '">' + id + '</span>';
                        }).join('');
                        $('#sh-inline-css-count').text(d.inline_css_count + ' inline <style>').show();
                        if ($('#sh-inline-style-list').length) $('#sh-inline-style-list').html(inlineCssHtml);
                        else $('#sh-inline-css-count').after('<div style="padding:6px 18px 10px;border-top:1px solid #f3f4f6;margin-top:4px" id="sh-inline-style-list">' + inlineCssHtml + '</div>');
                    } else {
                        $('#sh-inline-css-count').hide();
                    }

                    // Inline JS listesi
                    if (d.inline_scripts && d.inline_scripts.length) {
                        var inlineJsHtml = d.inline_scripts.map(function(id) {
                            var color = getInlineColor(id);
                            return '<span style="display:inline-block;margin:2px 4px 2px 0;padding:2px 8px;border-radius:12px;font-size:11px;font-family:Consolas,monospace;background:' + color + '18;border:1px solid ' + color + '44;color:' + color + '">' + id + '</span>';
                        }).join('');
                        $('#sh-inline-js-count').text(d.inline_js_count + ' inline <script>').show();
                        if ($('#sh-inline-script-list').length) $('#sh-inline-script-list').html(inlineJsHtml);
                        else $('#sh-inline-js-count').after('<div style="padding:6px 18px 10px;border-top:1px solid #f3f4f6;margin-top:4px" id="sh-inline-script-list">' + inlineJsHtml + '</div>');
                    } else {
                        $('#sh-inline-js-count').hide();
                    }

                    $('#sh-debug-result').show();
                }).fail(function() {
                    $('#sh-debug-loading').hide();
                    $('#sh-debug-error').text('AJAX hatası — sunucu yanıt vermedi.').show();
                });
            });

            // Enter ile tetikle
            $('#sh-debug-url').on('keypress', function(e) {
                if (e.which === 13) $('#sh-debug-analyze').click();
            });
        })(jQuery);
        </script>
        <?php
    }

    // ─── Cache Manifest Row Renderer ──────────────────────────────────────────

    private static function renderManifestRow(
        $index,
        array $row,
        array $pt_all,
        array $tax_all,
        array $all_pages,
        array $all_terms
    ): void {
        $is_tpl    = $index === '__TPL__';
        $type      = $row['type'] ?? 'post_type';
        $pt_val    = $row['post_type'] ?? '';
        $tax_val   = $row['taxonomy'] ?? '';
        $self      = ! empty( $row['self'] );
        $home      = ! empty( $row['home'] );
        $archive   = ! empty( $row['archive'] );
        $pages     = (array) ( $row['pages'] ?? [] );
        $terms_sel = (array) ( $row['terms'] ?? [] );

        $idx = $is_tpl ? '__TPL__' : (int) $index;
        ?>
        <div class="sh-manifest-row" style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:14px 16px;margin-bottom:10px;position:relative" data-index="<?php echo esc_attr( $idx ); ?>">

            <!-- Remove -->
            <button type="button" class="sh-manifest-remove" title="Kuralı kaldır"
                style="position:absolute;top:10px;right:10px;background:none;border:none;cursor:pointer;color:#9ca3af;font-size:18px;line-height:1;padding:2px 6px;border-radius:4px"
                onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#9ca3af'">×</button>

            <div style="display:grid;grid-template-columns:140px 1fr;gap:10px;margin-bottom:10px">
                <!-- Type -->
                <div>
                    <label style="font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:4px">Type</label>
                    <select class="sh-manifest-type" style="width:100%;padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px">
                        <option value="post_type" <?php selected( $type, 'post_type' ); ?>>Post Type</option>
                        <option value="taxonomy"  <?php selected( $type, 'taxonomy' ); ?>>Taxonomy</option>
                    </select>
                </div>
                <!-- Post Type / Taxonomy select -->
                <div>
                    <label style="font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:4px" class="sh-manifest-pt-label">Post Type</label>
                    <select class="sh-manifest-pt-select" style="width:100%;padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;<?php echo $type === 'taxonomy' ? 'display:none' : ''; ?>">
                        <option value="">— Seçin —</option>
                        <?php foreach ( $pt_all as $val => $lbl ) : ?>
                            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $pt_val, $val ); ?>><?php echo esc_html( $lbl ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="sh-manifest-tax-select" style="width:100%;padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;<?php echo $type !== 'taxonomy' ? 'display:none' : ''; ?>">
                        <option value="">— Seçin —</option>
                        <?php foreach ( $tax_all as $val => $lbl ) : ?>
                            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $tax_val, $val ); ?>><?php echo esc_html( $lbl ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Toggles -->
            <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:10px">
                <?php
                $toggles = [
                    'self'    => 'Self',
                    'home'    => 'Home',
                    'archive' => 'Archive',
                ];
                foreach ( $toggles as $key => $label ) :
                    $checked = $$key;
                    $hide_archive = $key === 'archive' && $type === 'taxonomy';
                ?>
                <label class="sh-manifest-toggle-wrap sh-manifest-<?php echo $key; ?>-wrap" style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;color:#374151;<?php echo $hide_archive ? 'display:none!important' : ''; ?>">
                    <label class="sh-toggle" style="margin:0">
                        <input type="checkbox" class="sh-manifest-<?php echo $key; ?>" <?php checked( $checked ); ?>>
                        <span class="sh-toggle-slider"></span>
                    </label>
                    <?php echo esc_html( $label ); ?>
                </label>
                <?php endforeach; ?>
            </div>

            <!-- Pages multi-select -->
            <div style="margin-bottom:10px;position:relative">
                <label style="font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:4px">Pages <span style="font-weight:400;text-transform:none;font-size:11px">(bu sayfaların cache'i de temizlensin)</span></label>
                <select class="sh-manifest-pages" multiple data-sh-select style="width:100%" data-placeholder="Ara ve seç...">
                    <?php foreach ( $all_pages as $p ) : ?>
                        <option value="<?php echo (int) $p->ID; ?>" <?php selected( in_array( $p->ID, $pages, false ) ); ?>>
                            <?php echo esc_html( $p->post_title ); ?> [<?php echo esc_html( $p->post_type ); ?>]
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Terms multi-select -->
            <div style="margin-bottom:4px;position:relative">
                <label style="font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:4px">Terms <span style="font-weight:400;text-transform:none;font-size:11px">(bu term sayfalarının cache'i de temizlensin)</span></label>
                <select class="sh-manifest-terms" multiple data-sh-select data-placeholder="Ara ve seç..." style="width:100%">
                    <?php if ( ! is_wp_error( $all_terms ) ) foreach ( $all_terms as $term ) : ?>
                        <option value="<?php echo (int) $term->term_id; ?>" <?php selected( in_array( $term->term_id, $terms_sel, false ) ); ?>>
                            <?php echo esc_html( $term->name ); ?> [<?php echo esc_html( $term->taxonomy ); ?>]
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php
    }

    // ─── Sidebar ──────────────────────────────────────────────────────────────

    private static function renderSidebar( array $settings ): void
    {
        $is_prod = defined('ENABLE_PRODUCTION') && ENABLE_PRODUCTION;
        ?>
        <div class="sh-sidebar-box">
            <h3>Quick Actions</h3>
            <ul style="margin:0;padding:0;list-style:none;font-size:12px">
                <li style="padding:5px 0;border-bottom:1px solid #f3f4f6"><a href="<?php echo esc_url(add_query_arg('purge_assets', '1')); ?>">→ Purge All Cache</a></li>
                <li style="padding:5px 0;border-bottom:1px solid #f3f4f6"><a href="?page=sh-asset-manager&tab=debug">→ Debug Assets</a></li>
                <li style="padding:5px 0"><a href="?page=sh-asset-manager&tab=filters">→ Filter Reference</a></li>
            </ul>
        </div>

        <div class="sh-sidebar-box" style="margin-top:12px">
            <h3>PAE Attribute Rehberi</h3>
            <ul style="margin:0;padding:0;list-style:none;font-size:11px;line-height:1.6">
                <li style="padding:5px 0;border-bottom:1px solid #f3f4f6">
                    <code style="font-size:10px;background:#f3f4f6;padding:1px 4px;border-radius:3px">data-required-js</code><br>
                    <span style="color:#6b7280">Elementin ihtiyaç duyduğu JS plugin adı. PAE bu değeri okuyarak sayfaya ait JS listesini oluşturur.</span>
                </li>
                <li style="padding:5px 0;border-bottom:1px solid #f3f4f6">
                    <code style="font-size:10px;background:#f3f4f6;padding:1px 4px;border-radius:3px">data-template</code><br>
                    <span style="color:#6b7280">Elemanın kullandığı Twig template yolu. PAE hangi plugin'in nerede kullanıldığını tespit eder.</span>
                </li>
                <li style="padding:5px 0;border-bottom:1px solid #f3f4f6">
                    <code style="font-size:10px;background:#f3f4f6;padding:1px 4px;border-radius:3px">data-inline</code><br>
                    <span style="color:#6b7280">CSS/JS dosyası inline embed edilsin mi. <code>true</code> olunca ayrı istek yapılmaz, HTML içine yazılır.</span>
                </li>
                <li style="padding:5px 0;border-bottom:1px solid #f3f4f6">
                    <code style="font-size:10px;background:#f3f4f6;padding:1px 4px;border-radius:3px">class detection</code><br>
                    <span style="color:#6b7280">PAE sayfadaki class listesini tarar. <code>js_files_conditional.json</code> dosyasındaki class → plugin eşleşmesiyle JS dosyaları eklenir.</span>
                </li>
                <li style="padding:5px 0;border-bottom:1px solid #f3f4f6">
                    <code style="font-size:10px;background:#f3f4f6;padding:1px 4px;border-radius:3px">Forced Plugins</code><br>
                    <span style="color:#6b7280">Sınıf tespitine gerek kalmadan her sayfaya zorla yüklenecek plugin adları (<code>pae_forced_plugins</code> option).</span>
                </li>
                <li style="padding:5px 0;border-bottom:1px solid #f3f4f6">
                    <code style="font-size:10px;background:#f3f4f6;padding:1px 4px;border-radius:3px">data-html-whitelist</code><br>
                    <span style="color:#6b7280">PAE'nin HTML taramasında bu elementin içeriğini whitelist'e alır. Dinamik/JS tarafından doldurulan alanlar yanlış class tespitini önlemek için bununla işaretlenir.</span>
                </li>
                <li style="padding:5px 0">
                    <code style="font-size:10px;background:#f3f4f6;padding:1px 4px;border-radius:3px">Grouped Apply</code><br>
                    <span style="color:#6b7280">Seçili post grubuna aynı asset setini tek seferde uygular. WooCommerce product gallery gibi toplu kayıtlarda kullanılır.</span>
                </li>
            </ul>
        </div>
        <div class="sh-sidebar-box" style="margin-top:12px">
            <h3>Current Mode</h3>
            <ul style="margin:0;padding:0;list-style:none;font-size:12px;color:#6b7280">
                <li style="padding:4px 0">Mode: <strong><?php echo $is_prod ? 'Production' : 'Development'; ?></strong></li>
                <li style="padding:4px 0">CSS: <strong><?php echo INLINE_CSS ? 'Inline' : ($settings['css_lazy_load'] ? 'Lazy' : 'Normal'); ?></strong></li>
                <li style="padding:4px 0">JS: <strong><?php echo $settings['js_defer'] ? 'Deferred' : 'Normal'; ?></strong></li>
                <li style="padding:4px 0">Google Fonts: <strong><?php echo $settings['block_google_fonts'] ? 'Blocked' : 'Allowed'; ?></strong></li>
            </ul>
        </div>
        <?php
    }

    // ─── Cache Info ───────────────────────────────────────────────────────────

    /**
     * Manifest + cache dizini bilgisini döndür.
     */
    private static function getManifestCacheData(): array
    {
        $cache_dir     = rtrim( STATIC_PATH, '/' ) . '/css/cache/';
        $manifest_path = rtrim( STATIC_PATH, '/' ) . '/cache-manifest/assets-manifest.json';

        // Manifest oku
        $manifest = [ 'templates' => [], 'plugins' => [], 'content_usage' => [] ];
        if ( file_exists( $manifest_path ) ) {
            $raw = @file_get_contents( $manifest_path );
            if ( $raw ) {
                $decoded = json_decode( $raw, true );
                if ( is_array( $decoded ) ) $manifest = array_merge( $manifest, $decoded );
            }
        }

        // Manifest'teki tüm bilinen dosyaları topla
        $known_files = [];
        foreach ( $manifest['templates'] ?? [] as $tpl ) {
            foreach ( [ 'css', 'css_rtl', 'critical_css' ] as $k ) {
                if ( ! empty( $tpl[$k] ) ) $known_files[] = basename( $tpl[$k] );
            }
        }
        foreach ( $manifest['plugins'] ?? [] as $p ) {
            foreach ( [ 'css', 'css_rtl' ] as $k ) {
                if ( ! empty( $p[$k] ) ) $known_files[] = basename( $p[$k] );
            }
        }

        // Cache dizinindeki TÜM dosyaları tara — manifest'te olmayanlar = misc
        $all_files  = glob( $cache_dir . '*.css' ) ?: [];
        $misc_files = [];
        $misc_total = 0;

        foreach ( $all_files as $f ) {
            $base = basename( $f );
            if ( in_array( $base, $known_files, true ) ) continue;

            $size = (int) filesize( $f );
            $misc_total += $size;

            // Tip tespiti
            if ( str_ends_with( $base, '-inline.css' ) ) {
                $type = 'inline'; $type_color = '#8b5cf6';
            } elseif ( str_ends_with( $base, '-critical.css' ) ) {
                $type = 'critical'; $type_color = '#0891b2';
            } elseif ( str_starts_with( $base, 'purge-' ) ) {
                $type = 'purge'; $type_color = '#dc2626';
            } else {
                $type = 'cache'; $type_color = '#6b7280';
            }

            $misc_files[] = [
                'name'       => $base,
                'type'       => $type,
                'type_color' => $type_color,
                'size'       => size_format( $size, 1 ),
                'modified'   => wp_date( 'Y-m-d H:i', (int) filemtime( $f ) ),
            ];
        }

        return [
            'manifest'   => $manifest,
            'cache_dir'  => $cache_dir,
            'misc_files' => $misc_files,
            'misc_total' => size_format( $misc_total, 1 ),
        ];
    }
}

