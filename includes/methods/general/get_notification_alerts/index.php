<?php
$required_setting = ENABLE_NOTIFICATIONS;

    $data = $response;
    if(is_user_logged_in() ) {
        $messages = [];
        $messages_count = 0;
        if(ENABLE_CHAT){
            $messages = yobro_notification_messages("notification");
            $messages_count = intval(yobro_unseen_messages_count());            
        }
        $notifications = new Notifications();//$GLOBALS["user"]);
        $notifications_count = intVal($notifications->get_unseen_notifications_count());
        $notifications_posts = $notifications->get_unseen_notifications();
        $all_notifications = array_merge($messages, $notifications_posts);
        $results = array(
            "count" => array(
                "message" => $messages_count,
                "notification" => $notifications_count,
            ),
            "notifications" => $all_notifications,
        );
        $data["data"] = $results;
    }else{
        $data["error"] = true;      
    }
    echo json_encode($data); //json_encode(yobro_notification_messages());
    die();