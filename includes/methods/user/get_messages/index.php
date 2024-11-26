<?php
$required_setting = ENABLE_CHAT;

$messages = yobro_messages();
            $templates = [$template . ".twig"];
            $context = Timber::context();
            $context["type"] = "messages";
            $context["posts"] = $messages;
            $data = [
                "error" => false,
                "message" => "",
                "data" => [
                    "count" => yobro_unseen_messages_count(),
                ],
            ];
            if (!$template) {
                $template = "partials/offcanvas/archive";
                //$template = "woo/dropdown/archive";
                $templates = [$template . ".twig"];
            }
