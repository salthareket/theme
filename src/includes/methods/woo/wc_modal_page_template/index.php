<?php
$required_setting = ENABLE_ECOMMERCE;

global $woocommerce;
            $context = Timber::context();

            $context["date"] = date("d.m.Y");

            $content = apply_filters(
                "the_content",
                get_post_field("post_content", $id)
            );

            $customer_data = $woocommerce->cart->get_customer();
            $shipping_data = $customer_data->shipping;
            $customer = [
                "name" =>
                    $customer_data->first_name .
                    " " .
                    $customer_data->last_name,
                "shipping_address" =>
                    $shipping_data["address_1"] .
                    " " .
                    $shipping_data["city"] .
                    " " .
                    $shipping_data["state"] .
                    " " .
                    $shipping_data["postcode"] .
                    " " .
                    $shipping_data["country"],
                "phone" => $customer_data->billing["phone"],
                "email" => $customer_data->email,
                "ip" => $_SERVER["REMOTE_ADDR"],
            ];
            $context["customer"] = $customer;

            $cart = [];
            $discount_total = 0;
            $tax_total = 0;
            $items = $woocommerce->cart->get_cart();
            foreach ($items as $item => $values) {
                $_product = wc_get_product($values["data"]->get_id());
                $getProductDetail = wc_get_product($values["product_id"]);
                //$price = get_post_meta($values['product_id'] , '_price', true);
                //echo "Regular Price: ".get_post_meta($values['product_id'] , '_regular_price', true)."<br>";
                //echo "Sale Price: ".get_post_meta($values['product_id'] , '_sale_price', true)."<br>";
                $tax = $values["line_subtotal_tax"];
                $regular_price = $_product->get_regular_price();
                //$sale_price = $_product->get_sale_price();
                //$discount = ($regular_price - $sale_price);// * $values['quantity'];
                //$discount_total += $discount;

                $tax_total += $tax;

                $cart_item = [
                    "image" => $getProductDetail->get_image("thumbnail"),
                    "title" => $_product->get_title(),
                    "price" => woo_get_currency_with_price(
                        get_post_meta($values["variation_id"], "_price", true)
                    ),
                    "quantity" => $values["quantity"],
                    "tax" => woo_get_currency_with_price($tax),
                    "total_price" => woo_get_currency_with_price(
                        $values["line_subtotal"]
                    ),
                ];
                $cart[] = $cart_item;
            }
            $context["cart"] = $cart;
            $context["total_tax"] = woo_get_currency_with_price($tax_total);
            //$context["shipping_price"] = $woocommerce->cart->get_cart_shipping_total();
            //$context["discount_price"] = woo_get_currency_with_price($discount_total);
            $context["total"] = woo_get_currency_with_price(
                $woocommerce->cart->total
            );

            Timber::render_string($content, $context);
            die();