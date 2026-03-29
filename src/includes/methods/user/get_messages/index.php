<?php
$required_setting = ENABLE_CHAT;

$messages = Messenger::notifications();
$tpl      = !empty($template) ? $template : 'partials/offcanvas/archive';

$context          = Timber::context();
$context['type']  = 'messages';
$context['posts'] = $messages;
$templates        = [$tpl . '.twig'];

$response['data'] = [
    'count' => Messenger::count(),
];
