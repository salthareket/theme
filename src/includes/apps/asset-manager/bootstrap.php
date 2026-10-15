<?php

/**
 * Asset Manager App Bootstrap
 *
 * variables.php'de şu şekilde include edilir:
 *
 *   if (get_sh_config('sh_theme_tasks_status')) {
 *       include_once SH_INCLUDES_PATH . 'apps/asset-manager/bootstrap.php';
 *   }
 *
 * @version 2.0.0
 * @changelog
 *   2.0.1 - 2026-05-18
 *     - Fix: REST_REQUEST koşulu eklendi — Gutenberg save'de RemoveUnusedCss yükleniyor
 *   2.0.0 - 2026-05-09
 *     - Add: apps/asset-manager/ altına taşındı
 *     - Add: AssetSettings — ACF'siz ayar yönetimi
 *     - Add: Filter-based genişletme (sh_frontend_styles, sh_admin_scripts, vs.)
 *     - Add: Admin UI (AssetManagerAdmin) — 5 tab
 *     - Add: sh_inline_head_css, sh_inline_footer_js, sh_inline_head_js
 *     - Add: sh_preconnect_domains, sh_preload_resources
 *     - Add: sh_lazy_css_handles, sh_defer_js_handles, sh_dequeue_styles
 *   1.2.0 - 2026-04-30 — Son eski versiyon (class.assets-manager.php)
 */

namespace SaltHareket\AssetManager;

// ─── AUTOLOAD ────────────────────────────────────────────────────────────────

$am_base = __DIR__ . '/';

require_once $am_base . 'AssetSettings.php';
require_once $am_base . 'Concerns/HandlesFrontendCss.php';
require_once $am_base . 'Concerns/HandlesFrontendJs.php';
require_once $am_base . 'Concerns/HandlesAdminAssets.php';
require_once $am_base . 'Concerns/HandlesPreloads.php';
require_once $am_base . 'Concerns/HandlesHelpers.php';
require_once $am_base . 'Concerns/HandlesCompile.php';
require_once $am_base . 'AssetManager.php';
require_once $am_base . 'MediaOptimizer.php';
require_once $am_base . 'Admin/AssetManagerAdmin.php';
require_once $am_base . 'Admin/MediaOptimizerAdmin.php';

// ─── TOOLS ───────────────────────────────────────────────────────────────────
// CSS/JS işleme araçları — admin'de yüklenir

if ( is_admin() || ( defined('DOING_AJAX') && DOING_AJAX ) || ( defined('REST_REQUEST') && REST_REQUEST ) ) {
    require_once $am_base . 'MergeCSS.php';
    require_once $am_base . 'AssetPacker.php';
    require_once $am_base . 'SaltMinifier.php';
    require_once $am_base . 'RemoveUnusedCss.php';
    require_once $am_base . 'SCSSCompiler.php';
}

// ─── INIT ────────────────────────────────────────────────────────────────────

// Admin sayfası
Admin\AssetManagerAdmin::register();

// AssetManager singleton — hook'ları otomatik register eder
AssetManager::getInstance();

// ─── BLOCK EDITOR — IFRAME CSS INJECT ────────────────────────────────────────
// Gutenberg iframed editor'e admin'de zaten yuklenen CSS'leri iframe icine de inject et.
// wp_enqueue_style() iframe disina gider — block_editor_settings_all ile iframe icine giriyor.
add_filter( 'block_editor_settings_all', function ( array $settings ): array {
    if ( ! defined( 'STATIC_PATH' ) ) return $settings;

    $styles = $settings['styles'] ?? [];

    // Admin'de zaten yuklu olan dosyaların aynıları iframe'e de ekleniyor
    $files = [
        STATIC_PATH . 'css/root.css',         // CSS variables
        STATIC_PATH . 'css/bootstrap-admin.css', // Bootstrap admin overrides
        STATIC_PATH . 'css/blocks-admin.css',  // Block admin styles
        STATIC_PATH . 'css/admin-addon.css',   // Admin addon styles
        STATIC_PATH . 'css/main-admin.css',    // Ana tema CSS (main.css kopyası)
    ];

    foreach ( $files as $file ) {
        if ( file_exists( $file ) ) {
            $css = @file_get_contents( $file );
            if ( $css ) {
                $styles[] = [ 'css' => $css ];
            }
        }
    }

    // Gutenberg editor-icine ozgu override'lar
    // .block-editor-block-list__block margin'leri ve layout bozukluklarini duzelt
    $styles[] = [ 'css' => '
        /* Header Builder - editor layout fix */

        /* Gutenberg block wrapper margin/padding sifirlama */
        [data-type="acf/header-builder"] .block-editor-block-list__layout,
        [data-type="acf/header-column"] .block-editor-block-list__layout,
        [data-type="acf/header-tools"] .block-editor-block-list__layout {
            display: flex !important;
            flex-direction: row !important;
            flex-wrap: nowrap !important;
            align-items: center !important;
            gap: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        /* Header block wrapper margin sifirlama */
        [data-type="acf/header-builder"] .block-editor-block-list__block,
        [data-type="acf/header-column"] .block-editor-block-list__block,
        [data-type="acf/header-tools"] .block-editor-block-list__block {
            margin: 0 !important;
            padding: 0 !important;
        }

        /* header-rows-wrapper full width */
        .header-rows-wrapper {
            width: 100% !important;
            display: flex !important;
            flex-direction: column !important;
        }

        /* header-builder icindeki header-row block wrapper full width ve alt alta */
        [data-type="acf/header-builder"] > * > [data-type="acf/header-row"],
        .header-rows-wrapper > [data-type="acf/header-row"],
        .header-rows-wrapper > .block-editor-block-list__layout > [data-type="acf/header-row"] {
            width: 100% !important;
            max-width: 100% !important;
            flex: 0 0 100% !important;
        }

        /* header-row block-editor wrapper full width */
        [data-type="acf/header-row"] {
            width: 100% !important;
        }

        /* header-builder block-list layout - rows alt alta */
        [data-type="acf/header-builder"] .block-editor-block-list__layout {
            display: flex !important;
            flex-direction: column !important;
            width: 100% !important;
        }
        [data-type="acf/header-builder"] .acf-innerblocks-container,
        [data-type="acf/header-builder"] .header-rows-wrapper {
            display: flex !important;
            flex-direction: column !important;
            width: 100% !important;
        }

        /* acf-innerblocks-container - header-builder container row */
        [data-type="acf/header-builder"] .acf-innerblocks-container {
            display: flex !important;
            flex-direction: column !important;
            width: 100% !important;
        }

        /* header-column acf-innerblocks-container */
        [data-type="acf/header-column"] .acf-innerblocks-container {
            display: flex !important;
            flex-direction: row !important;
            align-items: center !important;
            height: 100% !important;
        }

        /* header-tools acf-innerblocks-container */
        [data-type="acf/header-tools"] .acf-innerblocks-container {
            display: flex !important;
            flex-direction: row !important;
            flex-wrap: nowrap !important;
            align-items: center !important;
        }

        /* header-row acf-innerblocks-container — kolonlari yan yana */
        [data-type="acf/header-row"] .acf-innerblocks-container {
            display: flex !important;
            flex-direction: row !important;
            align-items: center !important;
            justify-content: space-between !important;
            width: 100% !important;
            min-height: 60px;
        }

        /* header-row icindeki block wrapper margin sifirlama */
        [data-type="acf/header-row"] .block-editor-block-list__layout {
            display: flex !important;
            flex-direction: row !important;
            align-items: center !important;
            justify-content: space-between !important;
            gap: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
        }
        [data-type="acf/header-row"] .block-editor-block-list__block {
            margin: 0 !important;
            padding: 0 !important;
            flex: 1 !important;
        }
        /* start/end kolonlar shrink, center grow */
        [data-type="acf/header-row"] .block-editor-block-list__block:first-child,
        [data-type="acf/header-row"] .block-editor-block-list__block:last-child {
            flex: 0 0 auto !important;
        }
        [data-type="acf/header-row"] .block-editor-block-list__block:not(:first-child):not(:last-child) {
            flex: 1 1 auto !important;
        }

        /* editor-styles-wrapper default padding kaldir */
        .editor-styles-wrapper {
            padding: 0 !important;
        }

        /* acf-block-component default margin kaldir */
        .acf-block-component.acf-block-body {
            margin-top: 0 !important;
            margin-bottom: 0 !important;
        }

        /* container margin auto koru - header navbar-top icin */
        [data-type="acf/header-builder"] .container,
        [data-type="acf/header-builder"] [class*="container-"] {
            margin-left: auto !important;
            margin-right: auto !important;
        }
    ' ];

    $settings['styles'] = $styles;
    return $settings;
} );

// ─── WP ROCKET — AUTO FETCH SKIP ─────────────────────────────────────────────
// PAE auto-fetch tetiklendiğinde ilgili sayfayı fetch tamamlanana kadar cache'leme.
// maybeScheduleAutoFetch() set_transient('pae_skip_rocket_{id}') yazar.
// ajaxAutoFetch() tamamlanınca transient silinir + rocket_clean_post() çağrılır.
if ( function_exists( 'get_rocket_option' ) || class_exists( 'WP_Rocket' ) ) {
    add_filter( 'do_rocket_cache_page', function ( bool $can_cache ): bool {
        if ( ! $can_cache ) return false;
        $obj = get_queried_object();
        $id  = 0;
        if ( $obj instanceof \WP_Post ) $id = $obj->ID;
        elseif ( $obj instanceof \WP_Term ) $id = $obj->term_id;
        if ( $id && get_transient( 'pae_skip_rocket_' . $id ) ) {
            return false;
        }
        return true;
    } );
}
