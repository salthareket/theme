<?php

// WooCommerce'de boş sepet mesajını kaldırır ve yerine özel mesaj gösterir
remove_action( 'woocommerce_cart_is_empty', 'wc_empty_cart_message', 10 );
add_action( 'woocommerce_cart_is_empty', 'custom_empty_cart_message', 10 );
function custom_empty_cart_message() {
    $html  = '<div class="alert alert-success col-12 offset-md-1 col-md-10"><p class="cart-empty">';
    $html .= wp_kses_post( apply_filters( 'wc_empty_cart_message', __( 'Your cart is currently empty.', 'woocommerce' ) ) );
    $html .= '</p></div>';  // Kapatma etiketi eklendi
    echo $html;
}




// WooCommerce cross-sell native render'ı kaldır
// Kendi product-list.twig ile render ediyoruz
remove_action( 'woocommerce_cart_collaterals', 'woocommerce_cross_sell_display' );
remove_action( 'woocommerce_after_cart', 'woocommerce_cross_sell_display', 30 );


// ─────────────────────────────────────────────────────────────────────────────
// [sh_new_in_store] shortcode — boş sepet için eski yaklaşım, devre dışı
// Artık template-cart.php context'i + page-cart.twig after_main bloğu kullanılıyor
// ─────────────────────────────────────────────────────────────────────────────

// Cross-sell + Recently Viewed → kendi product-list.twig ile
add_action( 'woocommerce_after_cart', function() {
    if ( ! class_exists( 'Timber\Timber' ) ) return;

    $pl_cfg          = (array) get_option( 'options_product_lists', [] );
    $crosssell_cfg   = (array) ( $pl_cfg['crosssell']       ?? [] );
    $rv_cfg          = (array) ( $pl_cfg['recently_viewed'] ?? [] );

    // Cross-sell ürünleri
    $crosssell_products = [];
    $cs_enabled  = ! empty( $crosssell_cfg['enabled'] );
    $cs_location = $crosssell_cfg['location'] ?? 'both';
    $cs_on_cart  = $cs_enabled && in_array( $cs_location, [ 'cart_page', 'both' ], true );

    if ( $cs_on_cart ) {
        $cs_count      = max( 1, (int) ( $crosssell_cfg['count'] ?? 8 ) );
        $crosssell_ids = WC()->cart ? array_slice( WC()->cart->get_cross_sells(), 0, $cs_count ) : [];
        foreach ( $crosssell_ids as $id ) {
            $p = \Timber\Timber::get_post( $id );
            if ( $p ) $crosssell_products[] = $p;
        }
    }

    // Recently Viewed ürünleri
    $recently_viewed_products = [];
    $rv_enabled = ! empty( $rv_cfg['enabled'] );

    if ( $rv_enabled ) {
        $rv_count   = max( 1, (int) ( $rv_cfg['count'] ?? 8 ) );
        $viewed_ids = array_filter( array_map( 'absint',
            explode( '|', wp_unslash( $_COOKIE['woocommerce_recently_viewed'] ?? '' ) )
        ) );
        if ( ! empty( $viewed_ids ) ) {
            $cart_ids   = WC()->cart
                ? array_column( array_map( fn( $i ) => [ 'id' => $i['product_id'] ], array_values( WC()->cart->get_cart() ) ), 'id' )
                : [];
            $viewed_ids = array_slice( array_diff( $viewed_ids, $cart_ids ), 0, $rv_count );
            foreach ( $viewed_ids as $id ) {
                $p = \Timber\Timber::get_post( $id );
                if ( $p ) $recently_viewed_products[] = $p;
            }
        }
    }

    if ( empty( $crosssell_products ) && empty( $recently_viewed_products ) ) return;

    $context = [
        'pl_cfg'                   => $pl_cfg,
        'crosssell_products'       => $crosssell_products,
        'recently_viewed_products' => $recently_viewed_products,
    ];

    if ( ! empty( $crosssell_products ) ) {
        \Timber\Timber::render( 'woo/partials/product-list.twig',
            array_merge( $context, [ 'list_type' => 'crosssell' ] )
        );
    }

    if ( ! empty( $recently_viewed_products ) ) {
        \Timber\Timber::render( 'woo/partials/product-list.twig',
            array_merge( $context, [ 'list_type' => 'recently_viewed' ] )
        );
    }
}, 20 );




// Sepet tablosu ve yanındaki alan için bootstrap row ve col açar
function cart_columns_start() { 
    echo "<div class='row'><div class='col-xl-9 col-lg-8'>";
}; 
add_action( 'woocommerce_before_cart', 'cart_columns_start', 10 );

// Sepet tablosunu kapatıp, yan sütunu açar
function cart_columns_center() { 
    echo "</div><div class='col-xl-3 col-lg-4'>";
}; 
add_action( 'woocommerce_before_cart_collaterals', 'cart_columns_center', 10 );

// Bootstrap row ve sütunları kapatır
function cart_columns_end() { 
    echo "</div></div>";
}; 
add_action( 'woocommerce_after_cart', 'cart_columns_end', 10 );






// WooCommerce sepette miktar inputuna "size-md" sınıfı ekler
add_filter('woocommerce_quantity_input_classes', 'add_classes_to_quantity_field', 10);
function add_classes_to_quantity_field($classes){
    if ( is_cart() ) {
        $classes[] = 'size-md';
    }
    return $classes;
}






// Ürün sepetteyse sepete ekle buton metnini değiştirir
add_filter('woocommerce_product_single_add_to_cart_text', 'woo_custom_cart_button_text');
function woo_custom_cart_button_text() {
    if ( ! WC()->cart ) return __('Sepete Ekle', 'woocommerce');
    foreach( WC()->cart->get_cart() as $cart_item_key => $values ) {
        $_product = $values['data'];
        if( get_the_ID() === $_product->get_id() ) {
            return __('Zaten sepette - Tekrar ekle?', 'woocommerce');
        }
    }
    return __('Sepete Ekle', 'woocommerce');
}





