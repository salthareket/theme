<?php
$required_setting = ENABLE_ECOMMERCE;

echo json_encode(wc_api_create_product($vars["data"]));
            die();