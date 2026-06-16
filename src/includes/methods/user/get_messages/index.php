<?php
$required_setting = ENABLE_CHAT;

$messages = Messenger::notifications();

$view = $vars['view'] ?? 'offcanvas';
$template = 'partials/' . $view . '/archive.twig';

$context          = Timber::context();
$context['type']  = 'messages';
$context['posts'] = $messages;

$response['data'] = [
    'count' => Messenger::count(),
];
$response['html'] = Timber::compile($template, $context);
echo json_encode($response);
wp_die();
