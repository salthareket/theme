<?php
$required_setting = ENABLE_ECOMMERCE;

$data = [];
            $template = $vars["ajax"]["template"];

            $data = Timber::get_posts(salt_recently_viewed_products());
            $templates = [$template];
            //$context = Timber::context();
            //print_r($vars);
            //$context["vars"] = $vars;
            //$context["vars"]["posts"] = $data->to_array();
            $vars["posts"] = $data->to_array();
            return [
                "error" => false,
                "message" => "",
                "data" =>  $vars,
                "html" => "",
            ];