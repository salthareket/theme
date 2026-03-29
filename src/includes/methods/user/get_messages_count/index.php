<?php
$required_setting = ENABLE_CHAT;

$response['data'] = [
    'count' => Messenger::count(),
];
echo json_encode($response);
wp_die();
