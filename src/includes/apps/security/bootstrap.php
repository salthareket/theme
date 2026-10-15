<?php

/**
 * Security App Bootstrap
 *
 * @version 1.0.0
 *
 * Yükleme: variables.php'de
 *   require_once SH_INCLUDES_PATH . 'apps/security/bootstrap.php';
 */

require_once __DIR__ . '/SecuritySettings.php';
require_once __DIR__ . '/SecurityHooks.php';

// Frontend hook'ları kaydet (admin'de de bazıları çalışır — send_headers her yerde)
\SaltHareket\Security\SecurityHooks::register();

// Admin: SecurityAdmin
if ( is_admin() ) {
    require_once __DIR__ . '/Admin/SecurityAdmin.php';
    \SaltHareket\Security\Admin\SecurityAdmin::register();
}
