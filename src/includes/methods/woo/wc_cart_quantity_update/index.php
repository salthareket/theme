<?php
$required_setting = ENABLE_ECOMMERCE;

global $woocommerce;
            $woocommerce->cart->set_quantity($vars["key"], $vars["count"]);
            //$woocommerce->cart->get_cart_contents_count();
            echo json_encode(woo_get_cart_object());
            die();