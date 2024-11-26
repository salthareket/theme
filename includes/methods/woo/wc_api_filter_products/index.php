<?php
$required_setting = ENABLE_ECOMMERCE;

$args = ["tag" => "103,63"];
            echo json_encode($GLOBALS["woo_api"]->get("products", $args)); //$vars["filters"]));
            die();