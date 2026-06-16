<?php
$required_setting = ENABLE_ECOMMERCE;

global $woocommerce;
$cart    = woo_get_cart_object();
$context = Timber::context();
$context['type'] = 'cart';
$context['cart'] = $cart;

$view = $vars['view'] ?? 'dropdown';
$template = 'partials/' . $view . '/archive.twig';

$response['data'] = [
    'count' => $woocommerce->cart->get_cart_contents_count(),
];
$response['html'] = Timber::compile($template, $context);
echo json_encode($response);
wp_die();
