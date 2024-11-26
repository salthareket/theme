<?php
$required_setting = ENABLE_ECOMMERCE;

global $woocommerce;
            $woocommerce->cart->empty_cart();
            die();