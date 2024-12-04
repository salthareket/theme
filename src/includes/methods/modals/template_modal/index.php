<?php
$error = true;
            $message = "";
            $html = "";
            if (isset($vars["template"])) {
                $error = false;
                $template = $vars["template"];
                $templates = [$template . ".twig"];
                $data = $vars; //["data"];
                $context = Timber::context();
                $context["data"] = $data;
                $html = Timber::compile($templates, $context);
            }
            $output = [
                "error" => $error,
                "message" => $message,
                "html" => "",
            ];
            if(isset($vars["title"])){
                $output["data"] = array(
                    "title" => $vars["title"],
                    "body" => $html
                );
            }else{
                $output["data"] = array(
                    "content" => $html
                );
            }
            echo json_encode($output);
            die();