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



//redirect user edit account page after registration
function wc_redirect_to_account_details( $redirect ) {
    if( is_user_logged_in() && ENABLE_MEMBERSHIP) {
        $redirect = wc_get_account_endpoint_url('edit-account');
    }
    return $redirect;
}
add_filter( 'woocommerce_registration_redirect', 'wc_redirect_to_account_details' );