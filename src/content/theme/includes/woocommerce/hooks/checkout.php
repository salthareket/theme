<?php


// Ödeme bölümünü varsayılan `woocommerce_checkout_order_review` hook'undan çıkarıp, özel `woocommerce_checkout_payment_hook` noktasına taşıyoruz.
remove_action( 'woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20 );
add_action( 'woocommerce_checkout_payment_hook', 'woocommerce_checkout_payment', 10 );



// Kupon formunu ödeme sayfasının başından kaldırıp, ödeme yöntemlerinden önce göstermek için hook düzenlemesi yapıldı.
remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );
add_action( 'woocommerce_review_order_before_payment', 'woocommerce_checkout_coupon_form' );


// Kargo hesaplayıcı formundan belirli alanları kaldırıyoruz:
// - İl (state)
// - İlçe/Şehir (city)
// - Posta Kodu (postcode)
add_filter( 'woocommerce_shipping_calculator_enable_state', '__return_false' );   // 1. İl'i devre dışı bırak
add_filter( 'woocommerce_shipping_calculator_enable_city', '__return_false' );    // 2. İlçe/Şehir'i devre dışı bırak
add_filter( 'woocommerce_shipping_calculator_enable_postcode', '__return_false' );// 3. Posta kodunu devre dışı bırak



// Checkout sayfasından fatura bilgileri alanlarını kaldırıyoruz:
// - Şirket Adı, İlçe/Şehir, Posta Kodu, Ülke, İl, Adres 1 ve Adres 2
add_filter( 'cfw_get_billing_checkout_fields', 'remove_checkout_fields', 9999 );
function remove_checkout_fields( $fields ) {
    unset( $fields['billing_company'] );
    unset( $fields['billing_city'] );
    unset( $fields['billing_postcode'] );
    unset( $fields['billing_country'] );
    unset( $fields['billing_state'] );
    unset( $fields['billing_address_1'] );
    unset( $fields['billing_address_2'] );
    return $fields;
}




// Fatura adresi alanlarının zorunlu olma durumunu kaldırıyoruz:
// - Şirket, İlçe/Şehir, Posta Kodu, Ülke, İl, Adres 1 ve Adres 2 artık zorunlu değil.
add_filter( 'woocommerce_checkout_fields', 'unrequire_checkout_fields' );
function unrequire_checkout_fields( $fields ) {
    $fields['billing']['billing_company']['required']   = false;
    $fields['billing']['billing_city']['required']      = false;
    $fields['billing']['billing_postcode']['required']  = false;
    $fields['billing']['billing_country']['required']   = false;
    $fields['billing']['billing_state']['required']     = false;
    $fields['billing']['billing_address_1']['required'] = false;
    $fields['billing']['billing_address_2']['required'] = false;
    return $fields;
}



// Kullanıcı profil sayfasında WooCommerce kargo adresi (shipping) alanlarını gizliyoruz.
// Eğer fatura adresi (billing) alanları da gizlenmek istenirse ilgili satırın yorum satırını kaldır.
add_filter( 'woocommerce_customer_meta_fields', 'hide_shipping_billing' );
function hide_shipping_billing( $show_fields ) {
    unset( $show_fields['shipping'] );
    // unset( $show_fields['billing'] ); // Fatura adresini de gizlemek için bu satırı aktif et
    return $show_fields;
}




// Checkout alanlarını sadeleştiriyoruz:
// - Kargo (shipping) ve hesap (account) alanlarını tamamen kaldırıyoruz.
// - Fatura (billing) alanları korunuyor (istersen onu da boşaltabilirsin).
function remove_all_checkout_fields( $fields ) {
    // $fields['billing'] = array(); // Tüm fatura alanlarını da kaldırmak istersen bu satırı aç
    $fields['shipping'] = array(); // Kargo alanlarını kaldır
    $fields['account']  = array(); // Hesap oluşturma alanlarını kaldır
    return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'remove_all_checkout_fields', 9999 );




// Sipariş notları bölümünü tamamen kaldırıyoruz:
// - "Ek Bilgiler" başlığı (Additional Information) ve açıklama alanı gizleniyor
add_filter( 'woocommerce_enable_order_notes_field', '__return_false', 9999 );

// Sipariş notları form alanını checkout alanlarından siliyoruz
add_filter( 'woocommerce_checkout_fields', 'remove_order_notes' );
function remove_order_notes( $fields ) {
    unset( $fields['order']['order_comments'] );
    return $fields;
}




// Checkout sayfasındaki ürün adlarının yanındaki "Quantity × X" bilgisini gizliyoruz.
function remove_checkout_item_quantity( $item_quantity, $cart_item_key, $cart_item ) {
    return ''; // Adet bilgisi boş döndürülerek gizleniyor
}
add_filter( 'woocommerce_checkout_cart_item_quantity', 'remove_checkout_item_quantity', 10, 3 );





// "Sipariş Detayları" sayfasında, sipariş tablosunun hemen sonundaki "Siparişi Tekrarla" butonunu kaldırıyor
remove_action( 'woocommerce_order_details_after_order_table', 'woocommerce_order_again_button' );




// Fatura ve kargo adresindeki posta kodu (postcode) alanlarının zorunlu olma şartını kaldırıyor
add_filter( 'woocommerce_checkout_fields', 'bbloomer_alternative_override_postcode_validation' );
function bbloomer_alternative_override_postcode_validation( $fields ) {
	$fields['billing']['billing_postcode']['required'] = false;
	$fields['shipping']['shipping_postcode']['required'] = false;
	return $fields;
}




function order_status_is_pending($order_id) {
    //error_log("$order_id set to PENDING");
}
function order_status_is_failed($order_id) {
    //error_log("$order_id set to FAILED");
}
function order_status_is_hold($order_id) {
    //error_log("$order_id set to ON HOLD");
}
function order_status_is_processing($order_id) {
    //error_log("$order_id set to PROCESSING");
}
function order_status_is_completed($order_id) {
    //error_log("$order_id set to COMPLETED");
}
function order_status_is_refunded($order_id) {
    //error_log("$order_id set to REFUNDED");
}
function order_status_is_cancelled($order_id) {
    //error_log("$order_id set to CANCELLED");
}
add_action( 'woocommerce_order_status_pending', 'order_status_is_pending', 10, 1);
add_action( 'woocommerce_order_status_failed', 'order_status_is_failed', 10, 1);
add_action( 'woocommerce_order_status_on-hold', 'order_status_is_hold', 10, 1);
// Note that it's woocommerce_order_status_on-hold, and NOT on_hold.
add_action( 'woocommerce_order_status_processing', 'order_status_is_processing', 10, 1);
add_action( 'woocommerce_order_status_completed', 'order_status_is_completed', 10, 1);
add_action( 'woocommerce_order_status_refunded', 'order_status_is_refunded', 10, 1);
add_action( 'woocommerce_order_status_cancelled', 'order_status_is_cancelled', 10, 1);







// WooCommerce'de Stripe ile ödeme tamamlandığında
// sipariş durumunu otomatik olarak "completed" yapar
add_action( 'woocommerce_payment_complete', 'woocommerce_auto_complete_stripe' );
function woocommerce_auto_complete_stripe( $order_id ) { 
    if ( ! $order_id ) {
        return;
    }
    $order = wc_get_order( $order_id );
    if ( $order && $order->get_payment_method() === 'stripe' ) {
        $order->update_status( 'completed' );
    }
}






// WooCommerce'de sipariş durumu "processing" olduğunda
// ve ödeme yöntemi Stripe değilse siparişi otomatik "completed" yapar
add_action('woocommerce_order_status_changed', 'ts_auto_complete_by_payment_method');
function ts_auto_complete_by_payment_method($order_id){
    if ( ! $order_id ) {
        return;
    }

    $order = wc_get_order( $order_id );
    if ( $order && $order->get_status() === 'processing' ) {
        $payment_method = $order->get_payment_method();
        if ( $payment_method !== 'stripe' ) {
            $order->update_status( 'completed' );
        }
    }
}
