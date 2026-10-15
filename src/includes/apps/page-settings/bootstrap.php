<?php

/**
 * Page Settings App Bootstrap
 *
 * @version 1.0.0
 */

require_once __DIR__ . '/PageSettings.php';

if ( is_admin() ) {
    require_once __DIR__ . '/Admin/PageSettingsAdmin.php';
    \SaltHareket\PageSettings\Admin\PageSettingsAdmin::register();
}

// Frontend: render modal/offcanvas components at wp_footer
if ( ! is_admin() ) {
    add_action( 'wp_footer', [ '\SaltHareket\PageSettings\PageSettings', 'renderComponents' ], 5 );
}
