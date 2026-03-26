<?php
$error   = true;
$message = 'Template not found';
$html    = '';
$post_id = 0;

if ( isset($vars['template']) ) {
    $error   = false;
    $message = '';
    $context = Timber::context();

    if ( !empty($vars['id']) ) {
        $post_id          = (int) $vars['id'];
        $context['post']  = Timber::get_post($post_id);
    }

    $context['data'] = $vars;
    $html = Timber::compile([ $vars['template'] . '.twig' ], $context);
}

modal_json_output( $html, modal_get_plugins_req($post_id), $vars, $error, $message );
