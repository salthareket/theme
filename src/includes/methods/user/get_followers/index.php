<?php
$required_setting = ENABLE_FOLLOW;

$args = array(
                //"role" => array("expert", "client"),
                "meta_query" => array(
                    "relation" => "AND"
                )
            );
            $args["meta_query"][] = array(
                'key'     => "following_user",
                'value'   => $vars["id"],
                'compare' => 'LIKE'
            );
            $paginate = new Paginate($args, $vars);
            $result = $paginate->get_results("user");
            //$response["posts"] = $result["posts"];
            $response["data"] = $result["data"];

            $context = Timber::context();
            $context["users"] = $result["posts"];
            $context["data"] = $result["data"];
            $context["vars"] = $vars;
            $response["html"] = Timber::compile("user/archive.twig", $context);
            // unset($response["posts"]);
            echo json_encode($response);
            die();