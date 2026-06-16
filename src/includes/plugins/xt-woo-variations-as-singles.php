<?php

/**
 * XT WooCommerce Variations as Singles — Tema entegrasyonu
 *
 * @version 1.1.0
 *
 * @changelog
 *   1.1.0 - 2026-05-01
 *     - Add: is_favorites ve xt_woovas_exclude flag'li query'lere dokunma
 *   1.0.0 - 2026-04-30
 *     - Add: hide_parent ayarı açıkken variable product parent'larını filtrele
 *
 * HOW TO USE:
 *   Bu dosya variables.php'den otomatik include edilir.
 *   Sadece XT_WOOVAS class'ı mevcutsa yüklenir.
 */

if (!class_exists('XT_WOOVAS')) return;

// WP 6.7+ textdomain too early notice'ını suppress et
// XT WooVAS plugin'inin kendi sorunu — bizim kodumuzda değil
add_filter('doing_it_wrong_trigger_error', function($trigger, $function_name, $message) {
    if (
        $function_name === '_load_textdomain_just_in_time' &&
        is_string($message) &&
        strpos($message, 'xt-woo-variations') !== false
    ) {
        return false;
    }
    return $trigger;
}, 1, 3);

/**
 * XT plugin'inin alter_products_query'sini favorites ve exclude flag'li
 * query'lerde devre dışı bırak.
 */
add_action('woocommerce_product_query', function($q) {
    if ($q->get('is_favorites') || $q->get('xt_woovas_exclude')) {
        // XT plugin'inin bu query'ye eklediği post_type ve post_parent__not_in'i geri al
        $post_type = $q->get('post_type');
        if (is_array($post_type)) {
            $post_type = array_diff($post_type, ['product_variation']);
            if (count($post_type) === 1) $post_type = reset($post_type);
            $q->set('post_type', $post_type);
        }
        $q->set('post_parent__not_in', []);
        $q->set('xt_woovas_query', false);
    }
}, 999); // XT'den (priority 50) sonra çalış

/**
 * hide_parent ayarı açıkken variable product parent'larını filtrele.
 */
add_filter('the_posts', function($posts, $query) {
    if ($query->get('wc_query') !== 'product_query') return $posts;
    if ($query->get('is_favorites')) return $posts;
    if (!$query->get('xt_woovas_query')) return $posts;

    $hide_parent = get_option('xt_woovas_hide_parent', 'no') === 'yes';
    if (!$hide_parent) return $posts;

    return array_values(array_filter($posts, function($post) {
        if ($post->post_type !== 'product') return true;
        $terms = get_the_terms($post->ID, 'product_type');
        if (!$terms || is_wp_error($terms)) return true;
        foreach ($terms as $term) {
            if ($term->slug === 'variable') return false;
        }
        return true;
    }));
}, 100, 2);
