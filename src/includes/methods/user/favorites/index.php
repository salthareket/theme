<?php
$required_setting = ENABLE_FAVORITES;

$salt     = \Salt::get_instance();
$result   = $salt->favorites($vars);
$action   = $vars['action'] ?? '';

if ($action === 'get') {
    $context          = Timber::context();
    $context['users'] = $result['posts'] ?? [];
    $context['tease'] = $vars['tease'] ?? '';
    $context['class'] = 'bg-white p-3 mb-3 rounded-4';
    $context['type']  = 'favorites';
    $result['html']   = Timber::compile('experts/archive.twig', $context);
    unset($result['posts']);
}

echo json_encode($result);
wp_die();
