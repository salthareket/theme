<?php

//custom thank you
add_action( 'template_redirect', 'wc_custom_redirect_after_purchase' );
function wc_custom_redirect_after_purchase() {
    global $wp;
    $page_id = get_option("woocommerce_order_received_page_id");
    $page = get_post( $page_id );
    if($page){
        $slug = $page->post_name;//"order-received";
        if ( is_checkout() && !empty( $wp->query_vars[$slug] ) ) {
            $order_id  = absint( $wp->query_vars[$slug] );
            $order_key = wc_clean( $_GET['key'] );
            $redirect  = get_permalink( $page->ID );
            $redirect .= get_option( 'permalink_structure' ) === '' ? '&' : '?';
            $redirect .= 'order=' . $order_id . '&key=' . $order_key;
            //wc_create_meet( $order_id, $redirect_url );
            wp_redirect( $redirect );
            exit;
        }
    }
}

add_filter( 'the_content', 'wc_custom_thankyou' );
function wc_custom_thankyou( $content ) {
    $page_id = get_option("woocommerce_order_received_page_id");
    $page = get_post( $page_id );
    // Check if is the correct page
    if ( ! isset( $page->ID ) ) {
        return $content;
    }
    // check if the order ID exists
    if ( ! isset( $_GET['key'] ) || ! isset( $_GET['order'] ) ) {
        return $content;
    }
    $order_id  = apply_filters( 'woocommerce_thankyou_order_id', absint( $_GET['order'] ) );
    $order_key = apply_filters( 'woocommerce_thankyou_order_key', empty( $_GET['key'] ) ? '' : wc_clean( $_GET['key'] ) );
    $order     = wc_get_order( $order_id );

    if($GLOBALS["user"]->ID != $order->get_customer_id()){
        header("HTTP/1.1 301 Moved Permanently");
        header("Location: ".get_home_url());
        exit;
    }
    if ( $order->get_id() != $order_id || $order->get_order_key() != $order_key ) {
        return $content;
    }
    ob_start();
    // Check that the order is valid
    if ( ! $order ) {
        // The order can't be returned by WooCommerce - Just say thank you
        ?><h1><?php echo apply_filters( 'woocommerce_thankyou_order_received_text', __( 'Your order has been received.', 'woocommerce' ), null ); ?></h1><?php
    } else {
        if ( $order->has_status( 'failed' ) ) {
            // Order failed - Print error messages and ask to pay again
            // @hooked wc_custom_thankyou_failed - 10
            do_action( 'wc_custom_thankyou_failed', $order );
        } else {
            // The order is successfull - print the complete order review
            // @hooked wc_custom_thankyou_header - 10
            // @hooked wc_custom_thankyou_table - 20
            // @hooked wc_custom_thankyou_customer_details - 30
            do_action( 'wc_custom_thankyou_successful', $order );
        }
    }
    $content .= ob_get_contents();
    ob_end_clean();
    return $content;
}

add_action( 'wc_custom_thankyou_failed', 'wc_custom_thankyou_failed', 10 );
function wc_custom_thankyou_failed( $order ) {
    wc_get_template( 'includes/woocommerce/custom-thankyou/failed.php', array( 'order' => $order ) );
}

add_action( 'wc_custom_thankyou_successful', 'wc_custom_thankyou_header', 10 );
function wc_custom_thankyou_header( $order ) {
    wc_get_template( 'includes/woocommerce/custom-thankyou/header.php', array( 'order' => $order ) );
}

//add_action( 'wc_custom_thankyou_successful', 'wc_custom_thankyou_table', 20 );
function wc_custom_thankyou_table( $order ) {
    wc_get_template( 'includes/woocommerce/custom-thankyou/table.php', array( 'order' => $order ) );
}

//add_action( 'wc_custom_thankyou_successful', 'wc_custom_thankyou_customer_details', 30 );
function wc_custom_thankyou_customer_details( $order ) {
    wc_get_template( 'includes/woocommerce/custom-thankyou/customer-details.php', array( 'order' => $order ) );
}