<?php
$required_setting = ENABLE_ECOMMERCE;

global $woocommerce;
$woocommerce->cart->remove_cart_item( $vars['key'] );

$view  = sanitize_key( $vars['view'] ?? 'dropdown' );
$cart  = woo_get_cart_object();
$count = (int) $woocommerce->cart->get_cart_contents_count();

$context            = Timber::context();
$context['source']  = 'cart';
$context['type']    = 'cart';        // geriye dönük uyum
$context['view']    = $view;
$context['cart']    = $cart;
$context['items']   = $cart['items'] ?? [];
$context['meta']    = [
    'count' => $count,
    'total' => $cart['total'] ?? '',
];

// Convention: source/cart/archive.twig (eski partials/{view}/archive.twig değil).
// archive-{view}.twig varsa onu, yoksa archive.twig.
$response['data'] = [ 'count' => $count ];
$response['html'] = Timber::compile(
    [ "partials/source/cart/archive-{$view}.twig", 'partials/source/cart/archive.twig' ],
    $context
);
echo json_encode( $response );
wp_die();
