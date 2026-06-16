<?php

include_once  "woocommerce/functions.php";
include_once 'woocommerce/admin.php';
include_once 'woocommerce/custom-fields/index.php';

if (ENABLE_MEMBERSHIP) {
    include_once  "woocommerce/hooks/redirect.php";
    include_once "woocommerce/hooks/my-account.php";
}

include_once 'woocommerce/hooks/loop.php';
include_once 'woocommerce/hooks/product.php';
include_once 'woocommerce/hooks/loop.php';
include_once 'woocommerce/hooks/single-product.php';
include_once 'woocommerce/hooks/product-category.php';
include_once 'woocommerce/hooks/bootstrap/manager.php';

if(ENABLE_CART){
    include_once 'woocommerce/custom-thankyou.php'; 
    include_once 'woocommerce/hooks/checkout.php';
    include_once 'woocommerce/hooks/cart.php';
}

if(!DISABLE_COMMENTS){
    //include_once 'woocommerce/hooks/comments.php';   
}

// ACF post_pagination ayarı varsa WooCommerce'in loop_shop_per_page'ini override et
add_filter('loop_shop_per_page', function($per_page) {
    $product_pagination = function_exists('get_post_type_pagination') ? get_post_type_pagination('product') : [];
    if (!empty($product_pagination['paged']) && !empty($product_pagination['posts_per_page'])) {
        return intval($product_pagination['posts_per_page']);
    }
    return $per_page;
}, 999); // priority 999 — tüm plugin'lerden sonra

// woocommerce_product_query'de de override et — WSSV'den sonra çalışması için priority 99
add_action('woocommerce_product_query', function($q) {
    if ($q->get('is_favorites')) return;
    // Önce Data::get dene
    $product_pagination = function_exists('get_post_type_pagination') ? get_post_type_pagination('product') : [];

    // Boşsa direkt ACF'den oku
    if (empty($product_pagination) && function_exists('get_field')) {
        $raw = get_field('post_pagination', 'options');
        if (is_array($raw)) {
            foreach ($raw as $item) {
                if (isset($item['post_type']) && $item['post_type'] === 'product') {
                    $paged = !empty($item['paged']);
                    $ppp   = $paged ? intval($item['catalog_rows']) * intval($item['catalog_columns']) : -1;
                    if ($paged && $ppp > 0) {
                        $q->set('posts_per_page', $ppp);
                    }
                    return;
                }
            }
        }
        return;
    }

    if (!empty($product_pagination['paged']) && !empty($product_pagination['posts_per_page'])) {
        $q->set('posts_per_page', intval($product_pagination['posts_per_page']));
    }
}, 99); // priority 99 — WSSV (priority 50) ve diğer plugin'lerden sonra çalış

// posts_per_page son kez override edilip edilmediğini kontrol et
add_filter('the_posts', function($posts, $query) {
    if ($query->get('wc_query') !== 'product_query') return $posts;
    if ($query->get('is_favorites')) return $posts; // favorites query'sine dokunma

    $ppp = 0;

    // 1. ACF post_pagination'dan al
    $product_pagination = function_exists('get_post_type_pagination') ? get_post_type_pagination('product') : [];
    if (!empty($product_pagination['paged']) && !empty($product_pagination['posts_per_page'])) {
        $ppp = intval($product_pagination['posts_per_page']);
    }

    // 2. Boşsa loop_shop_per_page filter'ından al
    if (!$ppp) {
        $ppp = intval(apply_filters('loop_shop_per_page', wc_get_default_products_per_row() * wc_get_default_product_rows_per_page()));
    }

    // Sadece max_num_pages'i düzelt — array_slice yapma, YITH filter ile çakışır
    if ($ppp > 0 && $query->get('posts_per_page') != $ppp) {
        $query->set('posts_per_page', $ppp);
        if ($query->found_posts > 0) {
            $query->max_num_pages = ceil($query->found_posts / $ppp);
        }
        // array_slice kaldırıldı — YITH filter sonuçlarını bozuyordu
    }

    return $posts;
}, 99, 2);