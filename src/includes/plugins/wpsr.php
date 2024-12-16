<?php

function wpsr_enqueue_scripts() {
    $shortcode = 'wpsr_share_icons';
    wp_dequeue_style('wpsr_fa_icons');
    wp_deregister_style('wpsr_fa_icons');
    // remove styles if shortcode is not used
    if(!defined("SITE_ASSETS")){
        return;
    }
    if(!in_array($shortcode, SITE_ASSETS["wp_js"])){
        wp_dequeue_style('wpsr_main_css');
        wp_deregister_style('wpsr_main_css');
    }
}
add_action('wp_enqueue_scripts', 'wpsr_enqueue_scripts', 999);
