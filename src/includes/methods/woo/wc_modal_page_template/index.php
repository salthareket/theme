<?php
$required_setting = ENABLE_ECOMMERCE;

global $woocommerce;

$context         = Timber::context();
$context['date'] = date('d.m.Y');
$content         = apply_filters('the_content', get_post_field('post_content', $id));

// Müşteri bilgileri
$customer_data = $woocommerce->cart->get_customer();
$shipping      = $customer_data->shipping;

$context['customer'] = [
    'name'             => trim($customer_data->first_name . ' ' . $customer_data->last_name),
    'shipping_address' => implode(' ', array_filter([
        $shipping['address_1'] ?? '',
        $shipping['city'] ?? '',
        $shipping['state'] ?? '',
        $shipping['postcode'] ?? '',
        $shipping['country'] ?? '',
    ])),
    'phone' => $customer_data->billing['phone'] ?? '',
    'email' => $customer_data->email ?? '',
    'ip'    => $_SERVER['REMOTE_ADDR'] ?? '',
];

// Sepet öğeleri
$cart_items = [];
$tax_total  = 0;

foreach ($woocommerce->cart->get_cart() as $values) {
    $_product = wc_get_product($values['data']->get_id());
    $detail   = wc_get_product($values['product_id']);
    $tax      = $values['line_subtotal_tax'];
    $tax_total += $tax;

    $cart_items[] = [
        'image'       => $detail->get_image('thumbnail'),
        'title'       => $_product->get_title(),
        'price'       => woo_get_currency_with_price(get_post_meta($values['variation_id'], '_price', true)),
        'quantity'    => $values['quantity'],
        'tax'         => woo_get_currency_with_price($tax),
        'total_price' => woo_get_currency_with_price($values['line_subtotal']),
    ];
}

$context['cart']      = $cart_items;
$context['total_tax'] = woo_get_currency_with_price($tax_total);
$context['total']     = woo_get_currency_with_price($woocommerce->cart->total);

Timber::render_string($content, $context);
wp_die();
