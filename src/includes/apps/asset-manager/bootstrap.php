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
require_once $am_base . 'AssetManager.php';
require_once $am_base . 'Admin/AssetManagerAdmin.php';

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
