<?php

/**
 * General Settings App Bootstrap
 *
 * @version 1.0.0
 */

require_once __DIR__ . '/Admin/GeneralSettingsAdmin.php';

if ( is_admin() ) {
    \SaltHareket\GeneralSettings\Admin\GeneralSettingsAdmin::register();

    // Taxonomy prefix değişince rewrite rules'ı flush et — AJAX dışında, sadece bir kez
    add_action( 'admin_init', function() {
        if ( get_option( 'sh_gs_needs_rewrite_flush' ) ) {
            delete_option( 'sh_gs_needs_rewrite_flush' );
            flush_rewrite_rules( false );
        }
    }, 99 );
}
