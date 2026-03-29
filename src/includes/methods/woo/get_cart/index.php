<?php
$required_setting = ENABLE_ECOMMERCE;

global $woocommerce;
$cart    = woo_get_cart_object();
$context = Timber::context();
$context['type'] = 'cart';
$context['cart'] = $cart;

$response['data'] = [
    'count' => $woocommerce->cart->get_cart_contents_count(),
];
$response['html'] = Timber::compile('partials/' . ($vars['type'] ?? 'cart') . '/archive.twig', $context);
echo json_encode($response);
wp_die();
