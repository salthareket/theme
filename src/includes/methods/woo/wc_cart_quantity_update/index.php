<?php
$required_setting = ENABLE_ECOMMERCE;

global $woocommerce;
$woocommerce->cart->set_quantity($vars['key'], (int) $vars['count']);
echo json_encode(woo_get_cart_object());
wp_die();
