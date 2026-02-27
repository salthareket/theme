<?php
$required_setting = ENABLE_ECOMMERCE;
$woo_api = Data::get("woo_api");
$args = ["tag" => "103,63"];
            echo json_encode($woo_api->get("products", $args)); //$vars["filters"]));
            die();