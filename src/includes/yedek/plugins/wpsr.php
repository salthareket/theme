<?php

/**
 * WP Socializer — Conditional asset loading.
 */

add_action('wp_enqueue_scripts', function() {
    wp_dequeue_style('wpsr_fa_icons');
    wp_deregister_style('wpsr_fa_icons');

    if (!defined('SITE_ASSETS') || !is_array(SITE_ASSETS) || !isset(SITE_ASSETS['wp_js'])) return;

    if (!in_array('wpsr_share_icons', SITE_ASSETS['wp_js'])) {
        wp_dequeue_style('wpsr_main_css');
        wp_deregister_style('wpsr_main_css');
    }
}, 999);
