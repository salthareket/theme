<?php

/**
 * AccessPress Social Share — Gereksiz CSS dequeue.
 */

add_action('wp_print_styles', function() {
    wp_dequeue_style('apss-font-awesome');
    wp_deregister_style('apss-font-awesome');
    wp_dequeue_style('apss-font-opensans');
    wp_deregister_style('apss-font-opensans');
}, 100);
