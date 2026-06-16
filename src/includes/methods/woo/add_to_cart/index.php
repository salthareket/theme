<?php
$required_setting = ENABLE_ECOMMERCE && ENABLE_CART;

global $woocommerce;

$product_id   = absint($vars['product_id'] ?? $vars['productId'] ?? 0);
$quantity     = max(1, absint($vars['quantity'] ?? 1));
$variation_id = absint($vars['variation_id'] ?? $vars['variationId'] ?? 0);

if (!$product_id) {
    $response['error']   = true;
    $response['message'] = __('Ürün bulunamadı.', 'woocommerce');
    echo json_encode($response);
    wp_die();
}

$product = wc_get_product($variation_id ?: $product_id);
if (!$product || !$product->is_purchasable()) {
    $response['error']   = true;
    $response['message'] = __('Bu ürün satın alınamaz.', 'woocommerce');
    echo json_encode($response);
    wp_die();
}

if (!$product->is_in_stock()) {
    $response['error']   = true;
    $response['message'] = __('Bu ürün stokta yok.', 'woocommerce');
    echo json_encode($response);
    wp_die();
}

$added = $woocommerce->cart->add_to_cart($product_id, $quantity, $variation_id);

if ($added) {
    $cart_item = $woocommerce->cart->get_cart_item($added);
    $_product  = $cart_item['data'];

    $response['error']   = false;
    $response['message'] = sprintf(
        __('<b>%s</b> sepete eklendi.', 'woocommerce'),
        esc_html($_product->get_name())
    );
    $response['data'] = [
        'cart_key'  => $added,
        'count'     => $woocommerce->cart->get_cart_contents_count(),
        'total'     => strip_tags(wc_price($woocommerce->cart->get_cart_contents_total())),
        'product'   => [
            'id'    => $_product->get_id(),
            'name'  => $_product->get_name(),
            'price' => strip_tags(wc_price($_product->get_price())),
            'image' => wp_get_attachment_image_url($_product->get_image_id(), 'thumbnail'),
            'url'   => get_permalink($product_id),
        ],
    ];
} else {
    $response['error']   = true;
    $response['message'] = __('Ürün sepete eklenemedi.', 'woocommerce');
}

echo json_encode($response);
wp_die();
