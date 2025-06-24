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




// WooCommerce'de çapraz satış ürünlerini sepetin altından kaldırıp
// sepetteki ürün listesinden sonra (after_cart) gösterir
remove_action( 'woocommerce_cart_collaterals', 'woocommerce_cross_sell_display' );
add_action( 'woocommerce_after_cart', 'woocommerce_cross_sell_display', 30 );




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
    foreach( WC()->cart->get_cart() as $cart_item_key => $values ) {
        $_product = $values['data'];
        if( get_the_ID() === $_product->get_id() ) {
            return __('Zaten sepette - Tekrar ekle?', 'woocommerce');
        }
    }
    return __('Sepete Ekle', 'woocommerce');
}





