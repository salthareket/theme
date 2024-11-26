<?php

function wpc_brand_get_brand($slug){
	return Timber::get_term_by('slug', $slug, 'wpc-brand');
}

function wpc_brand_enqueue_scripts() {
    wp_dequeue_style('wpcbr-frontend'); // Dequeue işlemi için stil adını belirtin
    wp_deregister_style('wpcbr-frontend'); // Register işlemi için stil adını belirtin
}
add_action('wp_enqueue_scripts', 'wpc_brand_enqueue_scripts', 999);