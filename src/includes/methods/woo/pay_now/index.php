<?php
$required_setting = ENABLE_ECOMMERCE;

$salt = \Salt::get_instance();//new Salt();
            $id = $vars["id"];
            $salt->remove_cart_content();
            //$salt->update_product_price($id);

            $salt->add_to_cart($id);

            $redirect_url = woo_checkout_url();
            $output = [
                "error" => false,
                "message" => "",
                "data" => "",
                "html" => "",
                "redirect" => $redirect_url,
            ];
            echo json_encode($output);
            die();