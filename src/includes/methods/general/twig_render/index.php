<?php
$template = $vars["template"];
            $templates = [$template . ".twig"];
            $data = $vars["data"];
            $context = Timber::context();
            $context["data"] = $data;
            echo Timber::compile($templates, $context);
            die();