<?php

/**
 * TinyMCE Editor — Bootstrap CSS + admin addon stylesheet.
 */

add_action('admin_init', function() {
    add_editor_style('https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.1/css/bootstrap.min.css');
    add_editor_style(get_stylesheet_directory_uri() . '/static/css/admin-addon.css');
});
