<?php
$required_setting = ENABLE_NOTIFICATIONS;

if (is_user_logged_in()) {
    $messages       = [];
    $messages_count = 0;

    if (ENABLE_CHAT) {
        $messages       = Messenger::notifications('notification');
        $messages_count = Messenger::count();
    }

    $notifications       = new Notifications();
    $notifications_count = (int) $notifications->get_unseen_notifications_count();
    $notifications_posts = $notifications->get_unseen_notifications();

    $response['data'] = [
        'count' => [
            'message'      => $messages_count,
            'notification' => $notifications_count,
        ],
        'notifications' => array_merge($messages, $notifications_posts),
    ];
} else {
    $response['error'] = true;
    $response['message'] = 'Not logged in';
}

echo json_encode($response);
wp_die();
