<?php

namespace SaltHareket\AssetManager;

use SaltHareket\AssetManager\Concerns\HandlesFrontendCss;
use SaltHareket\AssetManager\Concerns\HandlesFrontendJs;
use SaltHareket\AssetManager\Concerns\HandlesAdminAssets;
use SaltHareket\AssetManager\Concerns\HandlesPreloads;
use SaltHareket\AssetManager\Concerns\HandlesHelpers;
use SaltHareket\AssetManager\Concerns\HandlesCompile;

/**
 * AssetManager
 *
 * CSS/JS asset yönetimi — production/dev, inline, lazy, preload, defer.
 * Dışarıdan filter ile genişletilebilir.
 * Geriye uyumlu — eski `AssetManager::instance()` çağrıları kırılmaz.
 *
 * @version 2.0.0
 * @changelog
 *   2.0.0 - 2026-05-09
 *     - Refactor: apps/asset-manager/ altına taşındı
 *     - Add: Filter-based genişletme (sh_frontend_styles, sh_admin_scripts, vs.)
 *     - Add: AssetSettings — ACF'siz ayar yönetimi
 *     - Add: Admin UI (AssetManagerAdmin) — 5 tab
 *     - Add: sh_inline_head_css, sh_inline_footer_js, sh_inline_head_js filter'ları
 *     - Add: sh_preconnect_domains, sh_preload_resources filter'ları
 *     - Add: sh_lazy_css_handles, sh_defer_js_handles, sh_dequeue_styles filter'ları
 *     - Fix: cleanup_wp_global_styles() tek sorguda tüm ayarları alıyor
 *     - Fix: compile_files_config() static cache ile bir kez çalışıyor
 *   1.2.0 - 2026-04-30 — Son eski versiyon
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // Otomatik — bootstrap.php'de init edilir.
 * // Manuel purge: /?purge_assets
 *
 * // CSS ekle (frontend):
 * add_filter('sh_frontend_styles', function($styles) {
 *     $styles['my-css'] = ['url' => MY_URL . 'style.css'];
 *     return $styles;
 * });
 *
 * // JS ekle (frontend footer):
 * add_filter('sh_frontend_scripts', function($scripts) {
 *     $scripts['my-js'] = ['url' => MY_URL . 'script.js', 'footer' => true];
 *     return $scripts;
 * });
 *
 * // Admin'e CSS ekle:
 * add_filter('sh_admin_styles', function($styles) {
 *     $styles['my-admin'] = ['url' => MY_URL . 'admin.css'];
 *     return $styles;
 * });
 *
 * // Inline CSS (head'de):
 * add_filter('sh_inline_head_css', function($css) {
 *     return $css . '.my-element { color: red; }';
 * });
 *
 * // Inline JS (footer'da):
 * add_filter('sh_inline_footer_js', function($js) {
 *     return $js . 'console.log("loaded");';
 * });
 *
 * ──────────────────────────────────────────────────────────
 *
 * @example
 *   add_filter('sh_lazy_css_handles', fn($h) => [...$h, 'my-heavy-css']);
 *
 * @example
 *   add_filter('sh_defer_js_handles', fn($h) => [...$h, 'my-heavy-js']);
 *
 * @example
 *   add_filter('sh_dequeue_styles', fn($h) => [...$h, 'plugin-bloated-css']);
 *
 * @example
 *   add_filter('sh_preconnect_domains', fn($d) => array_merge($d, ['https://cdn.example.com' => []]));
 *
 * @example
 *   add_filter('sh_preload_resources', fn($r) => [...$r, ['url' => MY_URL . 'hero.jpg', 'as' => 'image']]);
 */
class AssetManager
{
    use HandlesFrontendCss;
    use HandlesFrontendJs;
    use HandlesAdminAssets;
    use HandlesPreloads;
    use HandlesHelpers;
    use HandlesCompile;

    // ─── Singleton ───────────────────────────────────────────────────────────

    private static ?self $instance = null;

    public static $runtimeCache = [];
    public static $preloadQueue = [];

    private bool  $is_rtl       = false;
    private bool  $print_inline = false;
    private       $version      = '1.0.0';
    private       $language     = '';

    private function __construct()
    {
        // Settings'i hemen okuma — ACF henüz initialize olmamış olabilir
        // print_inline ve version lazy olarak set edilecek
        $this->version = file_exists( STATIC_PATH . 'css/main.css' )
            ? filemtime( STATIC_PATH . 'css/main.css' )
            : '1.0.0';

        $this->initHooks();
    }

    public function setData(): void
    {
        $this->language     = \Data::get( 'language' ) ?? '';
        $this->is_rtl       = (bool) \Data::get( 'language_rtl' );
        // INLINE_CSS variables.php'de define edilmiş — ACF initialize olduktan sonra güvenle okunur
        $this->print_inline = INLINE_CSS && ! isset( $_GET['fetch'] );
    }

    public static function getInstance(): self
    {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** @deprecated Kullan: getInstance() */
    public static function instance(): self
    {
        return self::getInstance();
    }

    // ─── Auto-Fetch ──────────────────────────────────────────────────────────

    /**
     * Auto-fetch: Dosya varlık kontrolü + PAE tetikleme.
     * 
     * theme.php site_assets() metodundan çağrılır.
     * Eğer meta var ama css_page dosyası silinmişse PAE'yi tetikler ve yeni asset döndürür.
     * 
     * @param  array|null  $site_assets  Mevcut asset verisi
     * @param  array       $meta         ['type' => 'post|term|archive|dynamic', 'id' => ..., 'tax' => ...]
     * @param  bool        $is_internal  IS_INTERNAL_FETCH flag
     * @return array|null                PAE'nin döndürdüğü yeni asset veya null
     * 
     * @example
     *   $site_assets = AssetManager::maybeAutoFetch($site_assets, $meta, $is_internal);
     */
    public static function maybeAutoFetch( $site_assets, array $meta, bool $is_internal = false ): ?array
    {
        // ── 1. Auto-fetch açık mı? ─────────────────────────────────────────
        $auto_fetch_enabled = class_exists( 'SaltHareket\AssetManager\AssetSettings' )
            && ! empty( AssetSettings::getSetting( 'auto_fetch' ) );

        if ( ! $auto_fetch_enabled ) {
            return $site_assets;
        }

        // ── 2. Dosya varlık kontrolü ───────────────────────────────────────
        $assets_missing_file = false;

        if ( ! empty( $site_assets ) && is_array( $site_assets ) && ! $is_internal ) {
            // Archive/dynamic için cache'i refresh et
            if ( in_array( $meta['type'] ?? '', [ 'archive', 'dynamic' ], true ) && ! empty( $meta['id'] ) ) {
                wp_cache_delete( $meta['id'] . '_assets', 'options' );
                wp_cache_delete( 'notoptions', 'options' );
                $fresh = get_option( $meta['id'] . '_assets' );
                if ( is_array( $fresh ) && ! empty( $fresh ) ) {
                    $site_assets = $fresh;
                }
            }

            // CSS dosyası fiziksel olarak var mı?
            $static   = rtrim( STATIC_PATH, '/' ) . '/';
            $css_path = $site_assets['css_page'] ?? '';

            if ( ! empty( $css_path ) ) {
                $full_path = $static . ltrim( $css_path, '/' );
                if ( ! file_exists( $full_path ) ) {
                    $assets_missing_file = true;
                }
            }

            // NOT: plugin_css ve plugin_js kontrolleri kasıtlı olarak YAPILMIYOR.
            // Her PAE çalışmasında farklı hash üretirler (CSS purge HTML'e bağlı + whitelist farklılıkları).
            // Kontrol edilirse sonsuz döngü oluşur.

            if ( $assets_missing_file ) {
                $site_assets = null;
            }
        }

        // ── 3. PAE eligibility kontrolü ────────────────────────────────────
        $is_fetch_request = isset($_GET["fetch"]) 
            || $is_internal 
            || (defined('DOING_CRON') && DOING_CRON);

        $is_pae_eligible = empty($site_assets)
            && !$is_fetch_request
            && (SEPERATE_CSS || SEPERATE_JS);

        if ( ! $is_pae_eligible ) {
            return $site_assets;
        }

        // ── 4. PAE lazy load ────────────────────────────────────────────────
        if ( ! class_exists("PageAssetsExtractor") ) {
            $pae_file = defined('SH_INCLUDES_PATH')
                ? SH_INCLUDES_PATH . "apps/page-assets-extractor/PageAssetsExtractor.php"
                : '';
            if ( $pae_file && file_exists( $pae_file ) ) {
                // Asset Manager bağımlılıkları
                if ( defined('SH_INCLUDES_PATH') ) {
                    $am_base = SH_INCLUDES_PATH . 'apps/asset-manager/';
                    $am_tools = [
                        'SaltHareket\AssetManager\MergeCSS'        => 'MergeCSS.php',
                        'SaltHareket\AssetManager\AssetPacker'      => 'AssetPacker.php',
                        'SaltHareket\AssetManager\SaltMinifier'     => 'SaltMinifier.php',
                        'SaltHareket\AssetManager\RemoveUnusedCss'  => 'RemoveUnusedCss.php',
                        'SaltHareket\AssetManager\SCSSCompiler'     => 'SCSSCompiler.php',
                    ];
                    foreach ( $am_tools as $class => $file ) {
                        if ( ! class_exists( $class ) && file_exists( $am_base . $file ) ) {
                            require_once $am_base . $file;
                        }
                    }
                }
                include_once $pae_file;
            }
        }

        if ( ! class_exists("PageAssetsExtractor") ) {
            return $site_assets;
        }

        // ── 5. PAE tetikleme ────────────────────────────────────────────────
        $pae_reason = $assets_missing_file ? 'auto-fetch: css dosyası silinmiş' : 'assets bulunamadı';
        
        if ( class_exists('\SaltProfiler') ) {
            \SaltProfiler::mark('site_assets: PageAssetsExtractor start (' . $pae_reason . ')');
        }
        
        $pae_start_time = microtime(true);
        $extractor = \PageAssetsExtractor::get_instance();
        
        // Auto-fetch sırasında cascade_rebuild_bundles çalışmamalı.
        // Cascade diğer sayfaların plugin_css/js meta'larını güncelliyor ve mevcut dosyaları siliyor.
        // mass=true ile cascade devre dışı kalır.
        $extractor->mass = true;
        
        if ( $assets_missing_file && ! in_array( $meta['type'] ?? '', [ 'archive', 'dynamic' ], true ) ) {
            $extractor->force_rebuild = true;
        }
        
        // Tip bazlı fetch
        if ($meta["type"] === "post" && !empty($meta["id"])) {
            $wp_post = get_post( (int) $meta["id"] );
            if ( $wp_post instanceof \WP_Post ) {
                $result = $extractor->on_save_post( (int) $meta["id"], $wp_post, false );
                $site_assets = is_array($result) ? $result : null;
                clean_post_cache( (int) $meta["id"] );
            }
        } elseif ($meta["type"] === "term" && !empty($meta["id"])) {
            $result = $extractor->on_save_term( (int) $meta["id"], 0, $meta["tax"] ?? "" );
            $site_assets = is_array($result) ? $result : null;
            clean_term_cache( (int) $meta["id"] );
        } elseif ( in_array( $meta["type"], [ 'archive', 'dynamic' ], true ) && !empty($meta["id"]) ) {
            $archive_url = null;
            if ( is_post_type_archive() ) {
                $archive_url = get_post_type_archive_link( get_post_type() );
            } elseif ( function_exists('is_shop') && is_shop() ) {
                $archive_url = get_permalink( wc_get_page_id('shop') );
            } elseif ( is_search() ) {
                $archive_url = get_search_link( get_search_query() );
            } else {
                $archive_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            }
            if ( $archive_url ) {
                $extractor->type = $meta['type'];
                $site_assets = $extractor->fetch( $archive_url, $meta['id'], $meta['type'] );
            }
        }
        
        if ( class_exists('\SaltProfiler') ) {
            \SaltProfiler::mark('site_assets: PageAssetsExtractor end');
        }

        // ── 6. Başarı kontrolü ─────────────────────────────────────────────
        $pae_success = ! empty( $site_assets ) && is_array( $site_assets ) && ! empty( $site_assets['css_page'] ?? '' );
        
        if ( ! $pae_success ) {
            $page_url = ( is_ssl() ? 'https://' : 'http://' ) . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');
            error_log( "[PAE_FAILED] Sayfa fetch edildi ama CSS oluşturulamadı. URL: {$page_url} | type={$meta['type']} id={$meta['id']}" );
        }

        // ── 7. Cache invalidation (archive/dynamic) ────────────────────────
        if ( ! empty( $site_assets ) && is_array( $site_assets )
             && in_array( $meta['type'] ?? '', [ 'archive', 'dynamic' ], true )
             && ! empty( $meta['id'] ) ) {
            $option_key = $meta['id'] . '_assets';
            if ( class_exists( '\\QueryCache' ) ) {
                \QueryCache::on_option_change( $option_key );
                \QueryCache::invalidate_options_bulk();
            }
            wp_cache_delete( $option_key, 'options' );
            wp_cache_delete( 'notoptions', 'options' );
        }

        return $site_assets;
    }

    // ─── Hooks ───────────────────────────────────────────────────────────────

    private function initHooks(): void
    {
        add_action( 'init', [ $this, 'handleManualPurge' ] );

        // Preloads
        //add_action( 'wp_head', [ $this, 'prepareFontQueue' ], 0 );
        add_action( 'wp_head', [ $this, 'renderPreloads' ], 1 );
        add_action( 'wp_head', [ $this, 'renderCriticalCssRemover' ], 999 );

        // Frontend assets
        add_action( 'wp_enqueue_scripts', [ $this, 'loadFrontendCss' ], 10 );
        add_action( 'wp_enqueue_scripts', [ $this, 'dequeueGoogleFonts' ], 9999 );
        add_action( 'wp_default_scripts', [ $this, 'removeJqueryMigrate' ] );

        if ( is_admin() ) {
            add_filter( 'admin_init', [ $this, 'setData' ], 9999 );
            add_action( 'admin_enqueue_scripts', [ $this, 'loadAdminAssets' ] );
        } else {
            add_filter( 'wp', [ $this, 'setData' ], 9999 );
            // loadFrontendJs hook'a bağlanmalı — direkt çağrılmamalı
            add_action( 'wp_enqueue_scripts', [ $this, 'loadFrontendJs' ], 10 );
        }

        // CSS/JS lazy loading — hook'a bağla, constructor'da settings okuma
        // Settings ACF initialize olduktan sonra okunacak
        if ( ! is_admin() ) {
            add_action( 'wp_enqueue_scripts', function() {
                $settings = AssetSettings::get();
                if ( $settings['css_lazy_load'] ) {
                    add_filter( 'style_loader_tag', [ $this, 'delayCssLoading' ], 10, 4 );
                }
                if ( $settings['js_defer'] ) {
                    add_filter( 'script_loader_tag', [ $this, 'addScriptAttributes' ], 10, 3 );
                }
            }, 1 ); // priority 1 — enqueue'lardan önce filter'ları register et
        }

        if ( ! is_admin() ) {
            add_filter( 'style_loader_tag', [ $this, 'blockGoogleFontsTag' ], 9999, 3 );
        }
    }

    // ─── Cache Cleaner ───────────────────────────────────────────────────────

    /**
     * Tüm cache tiplerini temizler.
     * Parametresiz çağırınca: query + twig + opcache + wp_rocket hepsini temizler.
     *
     * @example AssetManager::purgeAll();
     */
    public static function purgeAll(): array
    {
        return self::purgeCache( [ 'query', 'twig', 'opcache', 'wp_rocket' ] );
    }

    /**
     * Belirtilen cache tiplerini temizler.
     *
     * @param  string[]  $types  'query' | 'twig' | 'opcache' | 'wp_rocket'
     * @return array             ['query'=>bool, 'twig'=>bool, 'opcache'=>bool, 'wp_rocket'=>bool]
     *
     * @example AssetManager::purgeCache(['query']);
     * @example AssetManager::purgeCache(['twig', 'opcache']);
     * @example AssetManager::purgeCache(['query', 'twig', 'opcache', 'wp_rocket']);
     */
    public static function purgeCache( array $types = [] ): array
    {
        $results = [];

        if ( in_array( 'query', $types, true ) ) {
            $results['query'] = self::purgeQueryCache();
        }

        if ( in_array( 'twig', $types, true ) ) {
            $results['twig'] = self::purgeTwigCache();
        }

        if ( in_array( 'opcache', $types, true ) ) {
            $results['opcache'] = self::purgeOpcache();
        }

        if ( in_array( 'wp_rocket', $types, true ) ) {
            $results['wp_rocket'] = self::purgeWpRocket();
        }

        do_action( 'sh/asset/cache_purged', $results, $types );

        return $results;
    }

    /**
     * Query Cache (QueryCache transient + wp_cache) temizle.
     */
    public static function purgeQueryCache(): bool
    {
        global $wpdb;

        // 1. Transient tabanlı QueryCache
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_sh_qc_%'
                OR option_name LIKE '_transient_timeout_sh_qc_%'
                OR option_name LIKE '_transient_qc_%'
                OR option_name LIKE '_transient_timeout_qc_%'"
        );

        // 2. WP object cache group
        if ( function_exists( 'wp_cache_flush_group' ) ) {
            wp_cache_flush_group( 'sh_query_cache' );
            wp_cache_flush_group( 'query_cache' );
        }

        // 3. QueryCache static cache sıfırla
        if ( class_exists( '\\QueryCache' ) && method_exists( '\\QueryCache', 'flush' ) ) {
            \QueryCache::flush();
        }

        // 4. SaltConfig cache
        if ( class_exists( '\\SaltHareket\\SaltConfig' ) ) {
            \SaltHareket\SaltConfig::generate();
        }

        return true;
    }

    /**
     * Twig template cache dosyalarını temizle.
     */
    public static function purgeTwigCache(): bool
    {
        // Gerçek twig cache dizini: STATIC_PATH/twig_cache
        $cache_dir = defined( 'STATIC_PATH' ) ? rtrim( STATIC_PATH, '/' ) . '/twig_cache' : '';

        // Fallback: eski Timber konumları
        if ( ! $cache_dir || ! is_dir( $cache_dir ) ) {
            $cache_dir = WP_CONTENT_DIR . '/cache/timber';
        }
        if ( ! is_dir( $cache_dir ) ) {
            $cache_dir = WP_CONTENT_DIR . '/uploads/timber-cache';
        }

        if ( ! is_dir( $cache_dir ) ) {
            return false;
        }

        $files   = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $cache_dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ( $files as $file ) {
            try {
                if ( $file->isDir() ) {
                    @rmdir( $file->getRealPath() );
                } else {
                    @unlink( $file->getRealPath() );
                }
            } catch ( \Throwable $e ) {
                // sessizce geç
            }
        }

        return true;
    }

    /**
     * OPcache durum bilgilerini döndürür.
     * Admin'de OPcache box için kullanılır.
     */
    public static function getOpcacheStats(): array
    {
        if ( ! function_exists( 'opcache_get_status' ) ) {
            return [ 'enabled' => false, 'reason' => 'opcache_get_status() fonksiyonu yok — OPcache yüklü değil veya CLI modunda' ];
        }

        $status = @opcache_get_status( false );
        if ( ! $status ) {
            return [ 'enabled' => false, 'reason' => 'OPcache devre dışı' ];
        }

        $mem    = $status['memory_usage'] ?? [];
        $stats  = $status['opcache_statistics'] ?? [];
        $config = function_exists( 'opcache_get_configuration' ) ? ( @opcache_get_configuration()['directives'] ?? [] ) : [];

        $used     = $mem['used_memory'] ?? 0;
        $free     = $mem['free_memory'] ?? 0;
        $wasted   = $mem['wasted_memory'] ?? 0;
        $total    = $used + $free + $wasted;
        $pct_used = $total > 0 ? round( $used / $total * 100, 1 ) : 0;
        $pct_hit  = ( isset( $stats['opcache_hit_rate'] ) ) ? round( $stats['opcache_hit_rate'], 2 ) : 0;

        $raw_max_mem = $config['opcache.memory_consumption'] ?? null;
        if ( $raw_max_mem !== null ) {
            $mb = (int) $raw_max_mem;
            // opcache.memory_consumption MB cinsinden geliyor (örn. 256)
            // Eğer bytes geldiyse (>= 1048576) MB'a çevir
            if ( $mb >= 1048576 ) $mb = (int) round( $mb / 1048576 );
            $max_memory_str = $mb >= 1024 ? round( $mb / 1024, 1 ) . ' GB' : $mb . ' MB';
        } else {
            $max_memory_str = '—';
        }

        return [
            'enabled'        => true,
            'memory_total'   => size_format( $total, 1 ),
            'memory_used'    => size_format( $used, 1 ),
            'memory_free'    => size_format( $free, 1 ),
            'memory_wasted'  => size_format( $wasted, 1 ),
            'pct_used'       => $pct_used,
            'hit_rate'       => $pct_hit,
            'hits'           => number_format_i18n( $stats['hits'] ?? 0 ),
            'misses'         => number_format_i18n( $stats['misses'] ?? 0 ),
            'cached_scripts' => number_format_i18n( $stats['num_cached_scripts'] ?? 0 ),
            'max_memory'     => $max_memory_str,
            'max_files'      => $config['opcache.max_accelerated_files'] ?? '—',
            'revalidate_freq'=> $config['opcache.revalidate_freq'] ?? '—',
        ];
    }

    /**
     * OPcache temizle.
     * flush-opcache plugini varsa onu kullanır, yoksa PHP native opcache_reset() çağırır.
     */
    public static function purgeOpcache(): bool
    {
        // 1. Flush OPcache eklentisi (https://wordpress.org/plugins/flush-opcache/)
        if ( function_exists( 'flush_op_cache' ) ) {
            flush_op_cache();
            return true;
        }

        // 2. Diğer yaygın OPcache eklentileri
        if ( function_exists( 'opcache_flush' ) ) {
            opcache_flush(); // bazı eklentiler bu fonksiyonu tanımlar
            return true;
        }

        // 3. PHP native — sadece CLI veya aynı process'te çalışırsa etkili
        if ( function_exists( 'opcache_reset' ) ) {
            return (bool) opcache_reset();
        }

        // 4. WP action hook — eklenti varsa bu hook'u dinleyebilir
        do_action( 'sh/purge_opcache' );

        return false;
    }

    /**
     * WP Rocket cache temizle — HTML cache + minified CSS/JS.
     */
    public static function purgeWpRocket(): bool
    {
        $cleared = false;

        // ── 1. HTML cache (wp-content/cache/wp-rocket/) ─────────────────
        if ( function_exists( 'rocket_clean_domain' ) ) {
            rocket_clean_domain();
            $cleared = true;
        } elseif ( function_exists( 'rocket_clean_home' ) ) {
            // WP Rocket 2.x fallback
            rocket_clean_home();
            $cleared = true;
        } elseif ( has_action( 'rocket_purge_cache_on_update' ) ) {
            do_action( 'rocket_purge_cache_on_update' );
            $cleared = true;
        }

        // ── 2. Minified CSS/JS cache (wp-content/cache/min/) ────────────
        // WP Rocket bu fonksiyonu sağlar
        if ( function_exists( 'rocket_clean_minify' ) ) {
            rocket_clean_minify();
            $cleared = true;
        }

        // ── 3. Cache busting (static file versioning) ───────────────────
        if ( function_exists( 'rocket_clean_cache_busting' ) ) {
            rocket_clean_cache_busting();
        }

        // ── 4. Manuel fallback — WP Rocket min klasörünü doğrudan sil ──
        // WP Rocket fonksiyonları yoksa veya ek güvence için
        $min_paths = [
            WP_CONTENT_DIR . '/cache/min/',
            WP_CONTENT_DIR . '/cache/busting/',
        ];
        foreach ( $min_paths as $path ) {
            if ( is_dir( $path ) ) {
                self::deleteDirectoryContents( $path );
                $cleared = true;
            }
        }

        return $cleared;
    }

    /**
     * Bir klasörün içeriğini özyinelemeli sil (klasörün kendisini silmez).
     */
    private static function deleteDirectoryContents( string $dir ): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ( $items as $item ) {
            try {
                $item->isDir() ? @rmdir( $item->getRealPath() ) : @unlink( $item->getRealPath() );
            } catch ( \Throwable $e ) { /* sessiz */ }
        }
    }
}
