<?php

/**
 * WPC Brands — Brand helper + gereksiz CSS dequeue.
 */

function wpc_brand_get_brand($slug) {
    return get_term_by('slug', $slug, 'wpc-brand');
}

add_action('wp_enqueue_scripts', function() {
    wp_dequeue_style('wpcbr-frontend');
    wp_deregister_style('wpcbr-frontend');
}, 999);
