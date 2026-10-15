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



//redirect user after registration — login_redirect_type settings'i kullan
function wc_redirect_to_account_details( $redirect ) {
    if ( ! is_user_logged_in() || ! ENABLE_MEMBERSHIP ) return $redirect;

    $type    = \SaltHareket\Membership\MembershipSettings::getSetting( 'login_redirect_type', 'dashboard' );
    $page_id = (int) \SaltHareket\Membership\MembershipSettings::getSetting( 'login_redirect_page_id', 0 );

    switch ( $type ) {
        case 'profile':
            $redirect = wc_get_account_endpoint_url( 'edit-account' );
            break;
        case 'home':
            $redirect = home_url( '/' );
            break;
        case 'custom':
            if ( $page_id > 0 ) {
                if ( function_exists( 'pll_get_post' ) ) {
                    $user_lang     = function_exists( 'pll_current_language' ) ? pll_current_language() : pll_default_language();
                    $translated_id = pll_get_post( $page_id, $user_lang );
                    $target_id     = ( $translated_id && $translated_id !== $page_id ) ? $translated_id : $page_id;
                } else {
                    $target_id = $page_id;
                }
                $redirect = (string) get_permalink( $target_id );
            } else {
                $redirect = wc_get_account_endpoint_url( 'dashboard' );
            }
            break;
        case 'referer':
            // Registration'da referer genellikle anlamsız — dashboard'a git
        case 'dashboard':
        default:
            $redirect = wc_get_account_endpoint_url( 'dashboard' );
            break;
    }

    return (string) apply_filters( 'sh_login_redirect_url', $redirect, get_current_user_id() );
}
add_filter( 'woocommerce_registration_redirect', 'wc_redirect_to_account_details' );



// ── Product URL conflict fix ──────────────────────────────────────────────────
// Aynı slug'da hem product hem term olduğunda WP term'i önceliklendirir.
// request filter ile /product/ prefix'i olan URL'leri product single'a zorla yönlendiririz.
add_filter( 'request', function( $query_vars ) {
    if ( ! isset( $_SERVER['REQUEST_URI'] ) ) return $query_vars;

    $uri = $_SERVER['REQUEST_URI'];

    // WooCommerce product permalink base'i al
    $woo_permalinks  = get_option( 'woocommerce_permalinks', [] );
    $product_base    = trim( $woo_permalinks['product_base'] ?? 'product', '/' );

    if ( empty( $product_base ) ) return $query_vars;

    // URL'de /product-base/slug/ pattern'ı var mı?
    $pattern = '#/' . preg_quote( $product_base, '#' ) . '/([^/?]+)#';
    if ( ! preg_match( $pattern, $uri, $matches ) ) return $query_vars;

    $slug = $matches[1];

    // Bu slug'da bir product var mı?
    $product = get_page_by_path( $slug, OBJECT, 'product' );
    if ( ! $product ) return $query_vars;

    // Query'yi product single'a zorla yönlendir
    $query_vars = [
        'post_type' => 'product',
        'name'      => $slug,
        'product'   => $slug,
    ];

    // Canonical redirect'i engelle
    add_filter( 'redirect_canonical', '__return_false' );

    return $query_vars;
}, 1 );