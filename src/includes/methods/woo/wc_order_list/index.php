<?php
$required_setting = ENABLE_ECOMMERCE;

$order_number = $vars['order_number'] ?? '';

ob_start();
woocommerce_order_details_table($order_number);
$html = ob_get_clean();

$response['error']   = false;
$response['message'] = '';
$response['data']    = ['order_number' => $order_number];
$response['html']    = $html;
echo json_encode($response);
wp_die();
