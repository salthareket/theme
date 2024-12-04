<?php


/**
 * Registers an editor stylesheet for the theme.
 */
function wpdocs_theme_add_editor_styles() {
    add_editor_style( 'https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.1/css/bootstrap.min.css' );
    add_editor_style( get_stylesheet_directory_uri() . '/static/css/admin-addon.css');
}
add_action( 'admin_init', 'wpdocs_theme_add_editor_styles' );