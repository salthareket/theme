<?php

/**
 * Theme Export App Bootstrap
 *
 * variables.php'de şu şekilde include edilir:
 *   if ($is_admin) include_once SH_INCLUDES_PATH . 'apps/theme-export/bootstrap.php';
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-05-09
 *     - Add: Initial release — class.theme-export.php refactored
 *     - Add: ACF bağımsız, Theme Settings altında kendi sayfası
 *     - Add: Export klasörü theme/static/data/exports/ — .htaccess korumalı
 *     - Add: PHP stream download — direkt URL erişimi yok
 *     - Add: Export geçmişi (son 10 kayıt)
 *     - Add: Exclude patterns (node_modules, .git, vendor vs.)
 *     - Add: sh-admin.css entegrasyonu
 */

namespace SaltHareket\ThemeExport;

// ─── AUTOLOAD ────────────────────────────────────────────────────────────────

$base = __DIR__ . '/';

require_once $base . 'Concerns/HandlesDatabase.php';
require_once $base . 'Concerns/HandlesFiles.php';
require_once $base . 'ThemeExporter.php';
require_once $base . 'Admin/ThemeExportAdmin.php';

// ─── INIT ────────────────────────────────────────────────────────────────────

Admin\ThemeExportAdmin::register();

// Export klasörünü oluştur (ilk yüklemede)
add_action( 'admin_init', function () {
    static $done = false;
    if ( $done ) return;
    $done = true;
    ThemeExporter::ensureExportDir();
}, 10 );

// Scheduled export (WP Cron)
add_action( 'sh_scheduled_export', function () {
    $settings = ThemeExporter::getSettings();
    if ( empty( $settings['scheduled_export'] ) ) return;

    // Cron export — full mode, no URL replace
    $exporter = new ThemeExporter();
    try {
        $init  = $exporter->runStep( 'init', [], [ 'export_mode' => $settings['default_mode'] ?? 'full' ] );
        // Cron'da step'leri sırayla çalıştır
        // (Bu basit implementasyon — büyük siteler için WP Background Processing önerilir)
        error_log( '[ThemeExport] Scheduled export started.' );
    } catch ( \Throwable $e ) {
        error_log( '[ThemeExport] Scheduled export failed: ' . $e->getMessage() );
    }
} );

// Cron schedule
add_filter( 'cron_schedules', function ( array $schedules ): array {
    $schedules['monthly'] = [
        'interval' => 30 * DAY_IN_SECONDS,
        'display'  => 'Once Monthly',
    ];
    return $schedules;
} );
