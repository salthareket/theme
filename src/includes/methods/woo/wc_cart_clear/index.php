<?php
$required_setting = ENABLE_ECOMMERCE;

global $woocommerce;
$woocommerce->cart->empty_cart();

// Standart zarf — sepet temizlendi, count = 0.
$response['data'] = [
    'count'      => 0,
    'page'       => 1,
    'page_total' => 1,
];
echo json_encode( $response );
wp_die();
