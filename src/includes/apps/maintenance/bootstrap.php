<?php

/**
 * Maintenance App Bootstrap
 *
 * Yükleme noktaları (variables.php):
 *   - MaintenanceSettings + Maintenance sınıfları variables.php başında require_once ile yüklenir
 *   - MAINTENANCE_MODE define'ı variables.php'de set edilir
 *   - Bu bootstrap her zaman (frontend + admin) yüklenir
 *
 * Sorumluluklar:
 *   - Admin menü kaydı (MaintenanceAdmin)
 *   - Apps Dashboard kaydı (sh_apps_registry filter)
 *   - Frontend template_redirect hook'u — maintenance sayfasını serve eder
 *
 * @version 1.1.0
 * @package SaltHareket\Maintenance
 */

// Sınıflar variables.php'de zaten yüklenmiş olabilir; tekrar yükleme
if ( ! class_exists( '\SaltHareket\Maintenance\Maintenance' ) ) {
    require_once __DIR__ . '/Maintenance.php';
}
if ( ! class_exists( '\SaltHareket\Maintenance\Admin\MaintenanceAdmin' ) ) {
    require_once __DIR__ . '/Admin/MaintenanceAdmin.php';
}

// ─── Admin ────────────────────────────────────────────────────────────────────

\SaltHareket\Maintenance\Admin\MaintenanceAdmin::register();

// Apps Dashboard kaydı
add_filter( 'sh_apps_registry', function ( array $apps ): array {
    $apps[] = [
        'id'          => 'maintenance',
        'label'       => 'Maintenance',
        'icon'        => '🔧',
        'enable_key'  => 'sh_maintenance_active',
        'admin_url'   => admin_url( 'admin.php?page=sh-maintenance' ),
        'enabled'     => \SaltHareket\Maintenance\MaintenanceSettings::isActive(),
        'description' => 'Maintenance mode with white pages, role & IP bypass',
    ];
    return $apps;
} );

// ─── Frontend ─────────────────────────────────────────────────────────────────
// template_redirect hook'u variables.php'deki after_setup_theme'de kayıtlıdır.
// Maintenance aktif değilse hook hiç çalışmaz (MAINTENANCE_MODE false ise after_setup_theme'de skip edilir).
// Burada frontend için ek bir hook kaydı yok — bootstrap.php sadece admin + registry işi yapar.
