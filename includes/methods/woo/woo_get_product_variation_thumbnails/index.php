<?php
$required_setting = ENABLE_ECOMMERCE;
global $woocommerce;
            $images = woo_get_product_variation_thumbnails(
                $vars["product_id"],
                $vars["attr"],
                $vars["attr_value"],
                $vars["size"]
            );
            $context = Timber::context();
            $context["post"] = wc_get_product($vars["product_id"]);
            $context["type"] = $context["post"]->get_type();
            
            $context["images"] = $images;
            $template = $vars["template"] . ".twig";
            $response["html"] = Timber::compile($template, $context);
echo json_encode($response);
die();   
        