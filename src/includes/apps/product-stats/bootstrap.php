<?php
/**
 * ProductStats Bootstrap
 */

// ENABLE_ECOMMERCE tanımlı değilse veya false ise yükleme
if ( ! defined( 'ENABLE_ECOMMERCE' ) || ! ENABLE_ECOMMERCE ) {
    return;
}

require_once __DIR__ . '/ProductStats.php';
require_once __DIR__ . '/ProductStatsAdmin.php';

// Admin kolonları admin/index.php (compile) üzerinden çalışıyor.
// ProductStatsAdmin::register() buradan çağrılmıyor — duplicate önleme.

// ── Sepete ekleme ──────────────────────────────────────────────────────────────
add_action( 'woocommerce_add_to_cart', function ( $cart_item_key, $product_id, $quantity, $variation_id ) {
    $id = ( $variation_id > 0 ) ? (int) $variation_id : (int) $product_id;
    \SaltHareket\ProductStats::incrementCart( $id );
}, 10, 4 );

// ── Sepetten çıkarma — carts counter'ı geri al ────────────────────────────────
// woocommerce_cart_item_removed: kullanıcı ürünü sepetten çıkardığında
add_action( 'woocommerce_cart_item_removed', function ( $cart_item_key, $cart ) {
    $item = $cart->removed_cart_contents[ $cart_item_key ] ?? null;
    if ( ! $item ) return;
    $product_id   = (int) ( $item['product_id']   ?? 0 );
    $variation_id = (int) ( $item['variation_id'] ?? 0 );
    $id           = $variation_id > 0 ? $variation_id : $product_id;
    if ( $id < 1 ) return;
    \SaltHareket\ProductStats::decrementCart( $id );
}, 10, 2 );

// ── Sepet boşaltıldı — tüm ürünleri geri al ──────────────────────────────────
add_action( 'woocommerce_cart_emptied', function ( $clear_persistent_cart ) {
    // Sepet boşaltılmadan önce içeriği oku
    // woocommerce_before_cart_emptied hook'u yok — cart'ın mevcut içeriğini al
    if ( ! WC()->cart ) return;
    foreach ( WC()->cart->get_cart() as $item ) {
        $product_id   = (int) ( $item['product_id']   ?? 0 );
        $variation_id = (int) ( $item['variation_id'] ?? 0 );
        $id           = $variation_id > 0 ? $variation_id : $product_id;
        if ( $id < 1 ) continue;
        \SaltHareket\ProductStats::decrementCart( $id );
    }
}, 10, 1 );

// ── Sipariş tamamlandı ─────────────────────────────────────────────────────────
add_action( 'woocommerce_order_status_completed', function ( $order_id ) {
    $order = wc_get_order( (int) $order_id );
    if ( ! $order ) return;
    foreach ( $order->get_items() as $item ) {
        $product_id   = (int) $item->get_product_id();
        $variation_id = (int) $item->get_variation_id();
        $quantity     = (int) $item->get_quantity();
        $id           = ( $variation_id > 0 ) ? $variation_id : $product_id;
        \SaltHareket\ProductStats::incrementPurchase( $id, $quantity );
    }
}, 10, 1 );

// ── sh_product_stats action ────────────────────────────────────────────────────
add_action( 'sh_product_stats', function ( $post_id ) {
    if ( ! $post_id ) return;
    $stats = \SaltHareket\ProductStats::getStats( (int) $post_id );
    if ( class_exists( 'Timber\\Timber' ) ) {
        \Timber\Timber::render( 'woo/partials/product-stats.twig', [
            'stats'           => $stats,
            'post_id'         => $post_id,
            'show_views'      => true,
            'show_carts'      => true,
            'show_purchases'  => true,
            'show_favorites'  => true,
        ] );
    }
}, 10, 1 );
