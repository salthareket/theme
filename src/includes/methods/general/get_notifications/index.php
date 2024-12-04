<?php
$required_setting = ENABLE_NOTIFICATIONS;

$user = array();
            if(!isset($vars["user"])){
                $user = wp_get_current_user();
            }
            $notifications = new Notifications($user);
            $result = $notifications->get_notifications($vars);
            if(isset($result["posts"])){
                $template = "partials/notifications/archive.twig";
                $context = Timber::context();
                $context["posts"] = $result["posts"];
                $response["html"] = Timber::compile($template, $context);                
            }
            $response["data"] = array_map("intval", $result["data"]); 
            echo json_encode($response);
            die();