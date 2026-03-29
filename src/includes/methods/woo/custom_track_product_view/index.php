<?php
$required_setting = ENABLE_ECOMMERCE;

custom_track_product_view_js($vars['post_id'] ?? 0);
wp_die();
