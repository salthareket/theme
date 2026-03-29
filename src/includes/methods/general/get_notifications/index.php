<?php
$required_setting = ENABLE_NOTIFICATIONS;

$user = isset($vars['user']) ? $vars['user'] : wp_get_current_user();
$notifications = new Notifications($user);
$result = $notifications->get_notifications($vars);

if (isset($result['posts'])) {
    $context = Timber::context();
    $context['posts'] = $result['posts'];
    $response['html'] = Timber::compile('partials/notifications/archive.twig', $context);
}

$response['data'] = array_map('intval', $result['data'] ?? []);
echo json_encode($response);
wp_die();
