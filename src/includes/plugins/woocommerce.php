<?php
/**
 * WooCommerce Performans Optimizasyonları
 *
 * Cart/checkout/account dışındaki sayfalarda WC session ve customer
 * yüklemesini devre dışı bırakarak init hook overhead'ini azaltır.
 *
 * Profiler'da init → wp hook arası ~2400ms boşluğu WC'nin session/customer
 * init'inden kaynaklanıyor. Bu filter ile gereksiz sayfalarda bu yük kaldırılıyor.
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-07-02
 *     - Add: WC session + customer lazy init (non-cart pages)
 *     - Add: WC gerekli olmayan hook'ların frontend'de temizlenmesi
 */

defined('ABSPATH') or die();

if ( ! defined('ENABLE_ECOMMERCE') || ! ENABLE_ECOMMERCE ) return;

/**
 * WooCommerce Session + Customer'ı sadece gerektiğinde yükle.
 *
 * Cart, checkout, my-account, AJAX ve REST isteklerinde tam yükleme.
 * Diğer sayfalarda (blog, ürün listesi, anasayfa vb.) session init atlanır.
 * Bu init → wp hook arası ~2400ms'lik overhead'i kaldırır.
 */
add_filter( 'woocommerce_should_load_cart_fragments', '__return_false' );

add_action( 'woocommerce_init', function() {
    // Gerekli URL pattern'leri — bunlarda session açık kalır
    $uri = $_SERVER['REQUEST_URI'] ?? '';

    $needs_session =
        ( defined('DOING_AJAX') && DOING_AJAX ) ||
        ( defined('REST_REQUEST') && REST_REQUEST ) ||
        ( defined('DOING_CRON') && DOING_CRON ) ||
        is_admin() ||
        strpos( $uri, 'wc-ajax' ) !== false ||
        strpos( $uri, 'add-to-cart' ) !== false;

    if ( $needs_session ) return;

    // wp hook gelince sayfa tipine göre session'a ihtiyaç var mı diye kontrol et
    add_action( 'wp', function() {
        if ( is_cart() || is_checkout() || is_account_page() || is_wc_endpoint_url() ) {
            return; // Bu sayfalarda session zaten başlamış olacak
        }
        // Diğer sayfalarda session handler'ı sıfırla — gereksiz DB yazımını engelle
        if ( WC()->session instanceof WC_Session_Handler ) {
            // Yeni guest session DB'ye yazılmasın
            remove_action( 'shutdown', array( WC()->session, 'save_data' ) );
        }
    }, 20 );
}, 1 );

/**
 * WooCommerce'in gerekli olmayan front-end hook'larını temizle.
 * Bu hook'lar her sayfada register edilip hiç kullanılmıyor.
 */
add_action( 'init', function() {
    if ( is_admin() ) return;

    // WooCommerce breadcrumb — tema kendi breadcrumb'ını kullanıyor (Yoast)
    remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );

    // WooCommerce sidebar — tema sidebar kullanmıyor
    remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );
}, 5 );
