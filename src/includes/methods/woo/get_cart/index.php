<?php
$required_setting = ENABLE_ECOMMERCE;

global $woocommerce;

$view    = $vars['view'] ?? 'dropdown';
$cart    = woo_get_cart_object();
$items   = $cart['items'] ?? [];
$count   = $woocommerce->cart->get_cart_contents_count();

$context            = Timber::context();
$context['source']  = 'cart';
$context['view']    = $view;
$context['items']   = $items;
$context['meta']    = [
    'count'      => $count,
    'page'       => 1,
    'page_total' => 1,
    'per_page'   => $count > 0 ? $count : 1,
    'total'      => $cart['total'] ?? '',
];

// Standart zarf. Cart paginate edilmez → page=1, page_total=1.
$response['data'] = [
    'count'      => $count,
    'page'       => 1,
    'page_total' => 1,
];
// archive-{view}.twig varsa onu, yoksa archive.twig (cart .load-container istisnası korunur).
$response['html'] = Timber::compile(
    [ "partials/source/cart/archive-{$view}.twig", 'partials/source/cart/archive.twig' ],
    $context
);
echo json_encode( $response );
wp_die();
