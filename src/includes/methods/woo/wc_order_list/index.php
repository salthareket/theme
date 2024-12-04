<?php
$required_setting = ENABLE_ECOMMERCE;

$order_number = $vars["order_number"];
            woocommerce_order_details_table($order_number);
            die();