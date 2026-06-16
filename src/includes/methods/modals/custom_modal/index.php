<?php
$error   = true;
$message = 'Content not found';
$html    = '';

if ( isset($vars['id']) ) {
    $post  = Timber::get_post($vars['id']);
    $error = false;
    $message = '';

    $html = $post->strip_tags($post->content);

    // Harici CSS (post meta'dan — logout+logged merged tek dosya)
    $css = $post->meta('css');
    if ( $css ) {
        $html .= '<link rel="stylesheet" href="' . get_template_directory_uri() . '/theme/templates/_custom/' . $css . '" media="all" crossorigin="anonymous">';
    }
}

modal_json_output( $html, modal_get_plugins_req($vars['id'] ?? 0, $html), $vars, $error, $message );