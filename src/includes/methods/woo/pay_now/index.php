<?php
$required_setting = ENABLE_ECOMMERCE;

$salt = \Salt::get_instance();
$salt->remove_cart_content();
$salt->add_to_cart($vars['id']);

$response['redirect'] = woo_checkout_url();
echo json_encode($response);
wp_die();
