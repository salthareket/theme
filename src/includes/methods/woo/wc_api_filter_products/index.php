<?php
$required_setting = ENABLE_ECOMMERCE;

$woo_api = Data::get('woo_api');
echo json_encode($woo_api->get('products', $vars['filters'] ?? ['tag' => '103,63']));
wp_die();
