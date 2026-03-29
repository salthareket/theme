<?php
$required_setting = ENABLE_ECOMMERCE;

woocommerce_order_details_table($vars['order_number'] ?? '');
wp_die();
