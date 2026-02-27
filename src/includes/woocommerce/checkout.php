<?php




//redirect checkout & cart page to login page if not logged
function wpse_131562_redirect() {
    $woo_redirect_not_logged = Data::get("woo_redirect_not_logged");
    if (! is_user_logged_in() && (is_cart() || is_checkout()) && !empty($woo_redirect_not_logged)) {
        wp_redirect($woo_redirect_not_logged);
        exit;
    }
}
add_action('template_redirect', 'wpse_131562_redirect');




//seperate order review and paymeny methods
// Detaching `payment` from `woocommerce_checkout_order_review` hook
remove_action('woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20 );
// Attaching `payment` to my `woocommerce_checkout_payment_hook`
add_action('woocommerce_checkout_payment_hook', 'woocommerce_checkout_payment', 10 ); 




//checkout coupon replace
remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );
add_action( 'woocommerce_review_order_before_payment', 'woocommerce_checkout_coupon_form' );




//Shipping clculator remove
// 1 Disable State
add_filter( 'woocommerce_shipping_calculator_enable_state', '__return_false' );
// 2 Disable City
add_filter( 'woocommerce_shipping_calculator_enable_city', '__return_false' );
// 3 Disable Postcode
add_filter( 'woocommerce_shipping_calculator_enable_postcode', '__return_false' );




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

// Set billing address fields to not required
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




add_filter( 'woocommerce_customer_meta_fields', 'hide_shipping_billing' );
function hide_shipping_billing( $show_fields ) {
    unset( $show_fields['shipping'] );
    //unset( $show_fields['billing'] );
    return $show_fields;
}





function remove_all_checkout_fields($fields) {
    // Tüm alanları kaldıralım
    //$fields = array();
    //$fields[ 'billing' ] = array();
    $fields[ 'shipping' ] = array();
    $fields[ 'account' ] = array();
    return $fields;
}
add_filter('woocommerce_checkout_fields', 'remove_all_checkout_fields', 9999);



// Removes Order Notes Title - Additional Information & Notes Field
add_filter( 'woocommerce_enable_order_notes_field', '__return_false', 9999 );

// Remove Order Notes Field
add_filter( 'woocommerce_checkout_fields' , 'remove_order_notes' );
function remove_order_notes( $fields ) {
     unset($fields['order']['order_comments']);
     return $fields;
}








// Ürün adının yanındaki "Quantity" kısmını kaldırmak için
function remove_checkout_item_quantity($item_quantity, $cart_item_key, $cart_item) {
    return '';
}
add_filter('woocommerce_checkout_cart_item_quantity', 'remove_checkout_item_quantity', 10, 3);

function product_thumbnail_on_checkout_order_review( $product_name, $cart_item, $cart_item_key ) {
    // Returns true on the checkout page.
    if ( is_checkout() ) {
        // Get product
        $product = $cart_item['data'];
        $product = Timber::get_post($product->get_id());
        $session = $product->parent();
        //$session_block = $session->get_session_block("name");
        $author = Timber::get_user($product->author);

        $thumbnail = $author->get_avatar(50, "rounded-circle me-3");//"<img src='".$avatar."' class='rounded-circle' alt='".$author->get_title()."'/>";               
        $product_name_link = '<div><a href="' . $product->get_permalink() . '" class="text-primary d-block lh-1">' . $product_name . '</a><div class="text-secondary"></div></div>';

        $product_name = '<div class="d-flex align-items-center mt-4">' . $thumbnail . $product_name_link . '</div>';   
    }
    return $product_name;
}
add_filter( 'woocommerce_cart_item_name', 'product_thumbnail_on_checkout_order_review', 20, 3 );














// hide coupon field on checkout page
function hide_coupon_field_on_checkout( $enabled ) {
    if ( is_checkout() ) {
        $enabled = false;
    }
    return $enabled;
}
//add_filter( 'woocommerce_coupons_enabled', 'hide_coupon_field_on_checkout' );






/* Hide shipping rates when free shipping is available.
 * Updated to support WooCommerce 2.6 Shipping Zones.
 *
 * @param array $rates Array of rates found for the package.
 * @return array
 */
function bbloomer_unset_shipping_when_free_is_available_all_zones( $rates, $package ) {   
    $all_free_rates = array();   
    foreach ( $rates as $rate_id => $rate ) {
        if ( 'free_shipping' === $rate->method_id ) {
            $all_free_rates[ $rate_id ] = $rate;
            break;
        }
    } 
    if ( empty( $all_free_rates )) {
         return $rates;
    } else {
        return $all_free_rates;
    } 
}
//add_filter( 'woocommerce_package_rates', 'bbloomer_unset_shipping_when_free_is_available_all_zones', 10, 2 );






function free_shipping_remaining_amount(){
    $value = "";
    if(function_exists("alg_wc_get_left_to_free_shipping")){
        $value = alg_wc_get_left_to_free_shipping( "%amount_left_for_free_shipping% left for free shipping" );        
    }
    if(!empty($value)){
        $price_left = filter_var($value, FILTER_SANITIZE_NUMBER_INT);
        $alertType = !empty($price_left)?"warning":"success";
        echo( "<div class='alert alert-".$alertType."'>". $value ."</div>");
    }
}
function free_shipping_remaining_amount_notice(){
    $value = "";
    if(function_exists("alg_wc_get_left_to_free_shipping")){
        $value = alg_wc_get_left_to_free_shipping( "%amount_left_for_free_shipping% left for free shipping" );        
    }
    if(!empty($value)){
       wc_add_notice($value);
    }
}
//add_action( 'wp', 'free_shipping_remaining_amount_notice' );
//add_action( 'woocommerce_cart_totals_before_order_total', 'free_shipping_remaining_amount');
//add_filter( 'woocommerce_package_rates', 'free_shipping_remaining_amount', 10, 2 ); causes problem on cart ajax when chabe address




/**
 * Conditionally show gift add-ons if shipping address differs from billing
**/
function wc_checkout_add_ons_conditionally_show_gift_add_on() {

    wc_enqueue_js( "
        $( 'input[name=dff528b]' ).change( function () {
            if ( $( this ).is( ':checked' ) ) {
                $( '#f443ada_field' ).removeClass('d-none');
            } else {
                $( '#f443ada_field' ).addClass('d-none');
            }
        } ).change();
    " );
}
///add_action( 'wp_enqueue_scripts', 'wc_checkout_add_ons_conditionally_show_gift_add_on' );




remove_action( 'woocommerce_order_details_after_order_table', 'woocommerce_order_again_button' );






//add_action( 'woocommerce_customer_save_address', 'jsforwp_update_address_for_orders', 10, 2 );
function jsforwp_update_address_for_orders( $user_id, $load_address ) {
    $customer_meta = get_user_meta( $user_id );
    $customer_orders = get_posts( array(
        'numberposts' => -1,
        'meta_key'    => '_customer_user',
        'meta_value'  => $user_id,
        'post_type'   => wc_get_order_types(),
        'post_status' => array_keys( wc_get_order_statuses() )
    ) );
    foreach( $customer_orders as $order ) {
          update_post_meta( $order->ID, '_billing_first_name', $customer_meta['billing_first_name'][0] );
          update_post_meta( $order->ID, '_billing_last_name', $customer_meta['billing_last_name'][0] );
          update_post_meta( $order->ID, '_billing_company', $customer_meta['billing_company'][0] );
          update_post_meta( $order->ID, '_billing_address_1', $customer_meta['billing_address_1'][0] );
          update_post_meta( $order->ID, '_billing_address_2', $customer_meta['billing_address_2'][0] );
          update_post_meta( $order->ID, '_billing_city', $customer_meta['billing_city'][0] );
          update_post_meta( $order->ID, '_billing_state', $customer_meta['billing_state'][0] );
          update_post_meta( $order->ID, '_billing_postcode', $customer_meta['billing_postcode'][0] );
          update_post_meta( $order->ID, '_billing_country', $customer_meta['billing_country'][0] );
          update_post_meta( $order->ID, '_billing_email', $customer_meta['billing_email'][0] );
          update_post_meta( $order->ID, '_billing_phone', $customer_meta['billing_phone'][0] );
    }
};




/*disable zip code validation on checkout */
add_filter( 'woocommerce_checkout_fields' , 'bbloomer_alternative_override_postcode_validation' );
function bbloomer_alternative_override_postcode_validation( $fields ) {
    $fields['billing']['billing_postcode']['required'] = false;
    $fields['shipping']['shipping_postcode']['required'] = false;
    return $fields;
}












function mysite_pending($order_id) {
    //error_log("$order_id set to PENDING");
}
function mysite_failed($order_id) {
    //error_log("$order_id set to FAILED");
}
function mysite_hold($order_id) {
    //error_log("$order_id set to ON HOLD");
}
function mysite_processing($order_id) {
    //error_log("$order_id set to PROCESSING");
}
function mysite_completed($order_id) {
    $application = new Application(get_product_by_order_id($order_id));
    update_field("application_status", "paid", $application->ID);
    
    $session = new Session($application->parent);
    /*$paid_term = get_term_by("slug", "paid", "session-status");
    wp_set_object_terms( $application->ID, $paid_term->ID, 'session-status', false );
    wp_set_object_terms( $session->ID, $paid_term->ID, 'session-status', false );*/

    $salt = Salt::get_instance();//new Salt();
    $salt->notification(
        "client/payment-completed",
        array(
            "user" => $application->author,
            "recipient" => $session->author->ID,
            "post" => $application
        )
    );
    $salt->notification(
        "expert/payment-completed",
        array(
            "user" => $session->author,
            "recipient" => $application->author->ID,
            "post" => $application
        )
    );

    $date_gmt = new DateTime();
    $date_gmt->setTimezone(new DateTimeZone('GMT'));
    $date_gmt->modify("+10 minutes");
    $date_gmt_formatted = $date_gmt->format("Y-m-d H:i");
    if($application->date_start <= $date_gmt_formatted){
        update_field("started", true, $application->ID);
        $this->notification(
            "client/started-session",
            array(
                "recipient" => $session->author->ID,
                "post" => $application
            )
        );

        $this->notification(
            "expert/started-session",
            array(
                "recipient" => $application->author->ID,
                "post" => $application
            )
        );
    }
    
    $order = wc_get_order( $order_id );
    $order->update_meta_data('_product_id', $application->ID);
    $order->update_meta_data('_product_author_id', $application->author->ID);
    $order->update_meta_data('_product_title', $application->title);
    $order->save();
}
function mysite_refunded($order_id) {
    //error_log("$order_id set to REFUNDED");
}
function mysite_cancelled($order_id) {
    //error_log("$order_id set to CANCELLED");
}

add_action( 'woocommerce_order_status_pending', 'mysite_pending', 10, 1);
add_action( 'woocommerce_order_status_failed', 'mysite_failed', 10, 1);
add_action( 'woocommerce_order_status_on-hold', 'mysite_hold', 10, 1);
// Note that it's woocommerce_order_status_on-hold, and NOT on_hold.
add_action( 'woocommerce_order_status_processing', 'mysite_processing', 10, 1);
add_action( 'woocommerce_order_status_completed', 'mysite_completed', 10, 1);
add_action( 'woocommerce_order_status_refunded', 'mysite_refunded', 10, 1);
add_action( 'woocommerce_order_status_cancelled', 'mysite_cancelled', 10, 1);



add_action( 'woocommerce_payment_complete', 'woocommerce_auto_complete_stripe' );
function woocommerce_auto_complete_stripe( $order_id ) { 
    if ( ! $order_id ) {
        return;
    }
    $order = wc_get_order( $order_id );
    if( $order->get_payment_method() == 'stripe' ) {
        $order->update_status( 'completed' );
    } else {
        
    }
}

/**
 * Auto Complete all WooCommerce orders based on Payment Method
 */
add_action('woocommerce_order_status_changed', 'ts_auto_complete_by_payment_method');
function ts_auto_complete_by_payment_method($order_id){
    if ( ! $order_id ) {
        return;
    }
    global $product;
    $order = wc_get_order( $order_id );
    if ($order->data['status'] == 'processing') {
        $payment_method = $order->get_payment_method();
        if ($payment_method != "stripe"){
            $order->update_status( 'completed' );
        }
    }
}








//add_action( 'woocommerce_before_calculate_totals', 'custom_cart_items_prices', 10, 1 );
function custom_cart_items_prices( $cart ) {

    if ( is_admin() && ! defined( 'DOING_AJAX' ) )
        return;

    if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 )
        return;

    foreach ( $cart->get_cart() as $cart_item ) {
        // Get the product id (or the variation id)
        $product_id = $cart_item['data']->get_id();
        $currency = $cart_item['data']->get_meta("tour_currency");
        if($cart_item['data']->get_meta("_wc_deposits_enable_deposit") == "yes"){
            $tour_plan_offer_id = $cart_item['data']->get_meta("tour_plan_offer_id");
            if($currency != "USD"){
                $deposit_paid = product_deposit_payment_is_complete($product_id);
                $total_amount =  $cart_item['data']->get_meta("tour_price");
                $deposit_amount = $cart_item['data']->get_meta("_wc_deposits_deposit_amount");
                if(!$deposit_paid){
                   $price = $total_amount;//($total_amount/100) * $deposit_amount;
                   $new_price = currencyConvert($price, $currency, "USD");
                   $cart_item['data']->set_price( $new_price );        
                }else{
                   //$new_price = 69;
                }
                        
            }
        }else{
            if($currency != "USD"){
                $price = $cart_item['data']->get_meta("tour_price");
                $new_price = currencyConvert($price, $currency, "USD");
                $cart_item['data']->set_price( $new_price ); 
            }
        }
    }
}




if(PAYMENT_EXPIRE_HOURS > 0){
    // Ürünün satın alınabilirliğini kontrol eden fonksiyon
    function is_product_purchasable($purchasable, $product) {
        // Ürünün satın alınabilirlik durumunu başlangıçta "true" olarak ayarlayalım.
        $is_purchasable = true;

        // Eğer $product bir tamsayı (integer) ise, bir WC_Product nesnesine dönüştürelim.
        if (is_int($product)) {
            $product_id = $product;
        }else{
            $product = get_post($product);
            $product_id = $product->ID;
        }

        // $product bir WC_Product nesnesi değilse, işlemi durdurarak hatayı önleyelim.
        if (!($product instanceof WC_Product)) {
            //return $is_purchasable;
        }

        // "upcoming" terimi "session-status" taksonomisine ait mi diye kontrol edelim.
        if (has_term('upcoming', 'session-status', $product_id)) {
            // Ürüne eklendiği tarihi meta veriden alalım.
            $term_added_time = get_post_meta($product_id, 'pay_expire_start', true);
            
            // Eğer ekleme tarihini alamazsak veya geçerli bir tarih değilse, ürünü satın alınabilir yapalım.
            if (!$term_added_time || !is_numeric($term_added_time)) {
                $is_purchasable = true;
            } else {
                $current_time = gmdate('U');//current_time('timestamp');
                $time_difference = $current_time - $term_added_time;
                if ($time_difference >= PAYMENT_EXPIRE_HOURS * 60 * 60) {
                    $is_purchasable = false;
                }
            }
        }
        return $is_purchasable;
    }
    //add_filter('woocommerce_is_purchasable', 'is_product_purchasable', 10, 2);
}



function check_product_purchasable() {
    if (is_checkout()) { // Sadece checkout sayfasındayken kontrol edelim.
        
        $is_purchasable = true;
        $expire_date = "";
        $product_ids = array();

        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product_ids[] = $cart_item['product_id'];
        }
        $product_id = reset($product_ids);

        // "upcoming" terimi "session-status" taksonomisine ait mi diye kontrol edelim.
        if (has_term('upcoming', 'session-status', $product_id)) {
            // Ürüne eklendiği tarihi meta veriden alalım.
            $term_added_time = get_post_meta($product_id, 'pay_expire_start', true);
            
            // Eğer ekleme tarihini alamazsak veya geçerli bir tarih değilse, ürünü satın alınabilir yapalım.
            if ($term_added_time and is_numeric($term_added_time)) {
                $current_time = gmdate('U');//current_time('timestamp');
                $time_difference = $current_time - $term_added_time;
                if ($time_difference >= PAYMENT_EXPIRE_HOURS * 60 * 60) {
                    $is_purchasable = false;
                    $expire_date = $term_added_time + (PAYMENT_EXPIRE_HOURS * 60 * 60);
                }
            }
        }

        if(!$is_purchasable){
            $user = new User(get_current_user());

            //print_r($expire_date);
            $expire_date = $user->get_local_date($expire_date, "GMT", $user->get_timezone());
            //print_r($expire_date);

            WC()->cart->empty_cart();
            $product_permalink = get_permalink(get_post($product_id));

            //redirect_notice('Session payment deadline expired on '.$expire_date);
            add_admin_notice('Session payment deadline expired on '.$expire_date, "error");
            wp_safe_redirect($product_permalink);
            exit;
        }

    }
}
add_action('wp', 'check_product_purchasable');




function check_product_purchase_once_on_checkout() {
    if (is_checkout()) { // Sadece checkout sayfasındayken kontrol edelim.
        // Sepetteki ürünleri kontrol edelim
        $user = Timber::get_user();
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product_id = $cart_item['product_id'];
            // Ürünün daha önce satın alınıp alınmadığını kontrol edelim
            if (wc_customer_bought_product($user->user_email, $user->ID, $product_id)) {
                // Eğer ürün daha önce satın alınmışsa, checkout sayfasından kaldırıp mesaj verelim
                WC()->cart->remove_cart_item($cart_item_key);
                redirect_notice("You already paid this session");
                $product = get_post($product_id);
                wp_safe_redirect($product->link);
            }
        }
    }
}
add_action('wp', 'check_product_purchase_once_on_checkout');





