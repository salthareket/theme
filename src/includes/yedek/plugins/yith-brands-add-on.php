<?php

/**
 * YITH Brands Add-on — Ürün başlığından otomatik brand oluşturma.
 */

function rasa_set_brands($offset = 0) {
    $products = get_posts([
        'post_type'        => 'product',
        'numberposts'      => -1,
        'orderby'          => 'date',
        'order'            => 'desc',
        'suppress_filters' => false,
    ]);
    foreach ($products as $product) {
        rasa_set_brand($product);
    }
}

function rasa_set_brand($product) {
    $taxonomy   = 'yith_product_brand';
    $term_title = explode(' ', $product->post_title)[0];
    $term_slug  = sanitize_title($term_title);
    $term_id    = term_exists($term_slug, $taxonomy);

    if (!$term_id) {
        $result = wp_insert_term($term_title, $taxonomy, ['slug' => $term_slug]);
        if (!is_wp_error($result)) {
            wp_set_post_terms($product->ID, $result['term_id'], $taxonomy, false);
        }
    } else {
        $tid = is_array($term_id) ? $term_id['term_id'] : $term_id;
        wp_set_post_terms($product->ID, $tid, $taxonomy, false);
    }
}

function rasa_set_brands_func() {
    rasa_set_brands(0);
}

// Mass update — ihtiyaç olduğunda aktif et
// add_action('init', 'rasa_set_brands_func');
