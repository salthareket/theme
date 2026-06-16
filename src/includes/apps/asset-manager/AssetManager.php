<?php

namespace SaltHareket\AssetManager;

use SaltHareket\AssetManager\Concerns\HandlesFrontendCss;
use SaltHareket\AssetManager\Concerns\HandlesFrontendJs;
use SaltHareket\AssetManager\Concerns\HandlesAdminAssets;
use SaltHareket\AssetManager\Concerns\HandlesPreloads;
use SaltHareket\AssetManager\Concerns\HandlesHelpers;

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

    // ─── Hooks ───────────────────────────────────────────────────────────────

    private function initHooks(): void
    {
        add_action( 'init', [ $this, 'handleManualPurge' ] );

        // Preloads
        add_action( 'wp_head', [ $this, 'prepareFontQueue' ], 0 );
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
                $is_production = ENABLE_PRODUCTION;
                if ( $is_production ) return; // Production'da lazy/defer yok

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
}
