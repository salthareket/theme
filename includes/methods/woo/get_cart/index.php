<?php
$required_setting = ENABLE_ECOMMERCE;

global $woocommerce;
$cart = woo_get_cart_object();
$context = Timber::context();
$context["type"] = "cart";
$context["cart"] = $cart;

$response["data"] = array(
    "count" => $woocommerce->cart->get_cart_contents_count()
);
                
$template = "partials/".$vars["type"]."/archive.twig";
$response["html"] = Timber::compile($template, $context);
echo json_encode($response);
die();        