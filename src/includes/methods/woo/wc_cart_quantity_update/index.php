<?php
$required_setting = ENABLE_ECOMMERCE;

global $woocommerce;
$woocommerce->cart->set_quantity( $vars['key'], (int) $vars['count'] );

$view  = sanitize_key( $vars['view'] ?? 'dropdown' );
$cart  = woo_get_cart_object();
$count = (int) $woocommerce->cart->get_cart_contents_count();

$context            = Timber::context();
$context['source']  = 'cart';
$context['type']    = 'cart';
$context['view']    = $view;
$context['cart']    = $cart;
$context['items']   = $cart['items'] ?? [];
$context['meta']    = [
    'count' => $count,
    'total' => $cart['total'] ?? '',
];

// Standart zarf. data içinde cart nesnesi de korunur (fragment/JS tüketimi için).
$response['data'] = array_merge( (array) $cart, [ 'count' => $count ] );
$response['html'] = Timber::compile(
    [ "partials/source/cart/archive-{$view}.twig", 'partials/source/cart/archive.twig' ],
    $context
);
echo json_encode( $response );
wp_die();
