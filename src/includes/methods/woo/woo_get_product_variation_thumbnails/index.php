<?php
$required_setting = ENABLE_ECOMMERCE;

$images  = woo_get_product_variation_thumbnails($vars['product_id'], $vars['attr'], $vars['attr_value'], $vars['size'] ?? 'medium');
$product = wc_get_product($vars['product_id']);

$context           = Timber::context();
$context['post']   = $product;
$context['type']   = $product->get_type();
$context['images'] = $images;

$response['html'] = Timber::compile($vars['template'] . '.twig', $context);
echo json_encode($response);
wp_die();
