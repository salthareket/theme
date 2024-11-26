<?php

function custom_product_permalink($url, $post) {
    // Sadece "product" türünde bir gönderi için URL özelleştirmesi yapalım
    if(is_int($post)){
        $post = get_post($post);
    }
    if ($post->post_type === 'product') {
        $id = $post->post_parent;
        $session = new Session($id);
        $custom_url = $session->link;
        return $custom_url;
    }
    return $url;
}
//add_filter('post_type_link', 'custom_product_permalink', 10, 2);


//redirect dashboard page to profile page
add_action('template_redirect', 'misha_redirect_to_orders_from_dashboard' );
function misha_redirect_to_orders_from_dashboard(){
    if( is_user_logged_in() && ENABLE_MEMBERSHIP) {
        if( is_account_page() && empty( WC()->query->get_current_endpoint()) ){
            wp_safe_redirect( wc_get_account_endpoint_url( 'sessions' ) );
            exit;
        }
    }
}


//redirect user edit account page after registration
function wc_redirect_to_account_details( $redirect ) {
    if( is_user_logged_in() && ENABLE_MEMBERSHIP) {
        $redirect = wc_get_account_endpoint_url('edit-account');
    }
    return $redirect;
}
add_filter( 'woocommerce_registration_redirect', 'wc_redirect_to_account_details' );



//add_action('template_redirect', 'misha_redirect_to_orders_from_dashboard' );
/*function misha_redirect_to_orders_from_dashboard(){
    if( is_account_page() && empty( WC()->query->get_current_endpoint() ) ){
        if( is_user_logged_in() ) {
            $user = wp_get_current_user();
            switch($user->roles[0]){
                case "customer" :
                   $endpoint = "my-trips";
                   break;
                case "agent" :
                   $endpoint = "requests";
                   break;
                case "administrator" :
                   $endpoint = "edit-account";
                   break;
            }
        }
        wp_safe_redirect( wc_get_account_endpoint_url( $endpoint ) );
        exit;
    }
}*/