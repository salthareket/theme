<?php
$required_setting = ENABLE_ECOMMERCE;

$posts = Timber::get_posts(salt_recently_viewed_products());
$vars['posts'] = $posts->to_array();

$response['data'] = $vars;
echo json_encode($response);
wp_die();
