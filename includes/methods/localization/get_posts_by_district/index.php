<?php
$data = [];
            $template = $vars["post_type"] . "/archive-ajax";
            $data = get_posts_by_district(
                $vars["post_type"],
                $vars["city"],
                $vars["district"]
            );
            $templates = [$template . ".twig"];
            $context = Timber::context();
            $context["vars"] = $vars;
            $context["data"] = $data;
            $data = [
                "error" => false,
                "message" => "",
                "data" => $data,
                "html" => "",
            ];