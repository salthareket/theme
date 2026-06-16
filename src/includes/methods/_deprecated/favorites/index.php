<?php
$required_setting = ENABLE_FAVORITES;

$salt     = \Salt::get_instance();
$result   = $salt->favorites($vars);
$action   = $vars['action'] ?? '';

if ($action === 'get') {
    $context          = Timber::context();
    $context['posts'] = $result['posts'] ?? [];
    $context['template'] = $vars['template'] ?? '';
    $context['class'] = 'bg-white p-3 mb-3 rounded-4';
    $context['type']  = 'favorites';

    $favorites_obj = new Favorites();
    $context['favorites'] = $favorites_obj->favorites;

    $template = $vars['template'] ?? '';
    $html = '';
    if (!empty($result['posts'])) {
        foreach ($result['posts'] as $index => $post) {
            ob_start();
            $ctx = $context;
            $ctx['post'] = $post;
            $ctx['index'] = $index;
            if (!empty($template)) {
                Timber::render([$template, 'tease.twig'], $ctx);
            } else {
                Timber::render(['tease.twig'], $ctx);
            }
            $html .= ob_get_clean();
        }
    }
    $result['html'] = $html;
    unset($result['posts']);
}

echo json_encode($result);
wp_die();
