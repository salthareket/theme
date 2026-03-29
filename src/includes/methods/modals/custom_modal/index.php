<?php
$error   = true;
$message = 'Content not found';
$html    = '';

if ( isset($vars['id']) ) {
    $post  = Timber::get_post($vars['id']);
    $error = false;
    $message = '';

    $html = $post->strip_tags($post->content);

    // Inline CSS (asset'ten)
    $assets = $post->meta('assets');
    if ( !empty($assets['css']) ) {
        $html .= "<style type='text/css'>" . $assets['css'] . "</style>";
    }

    // Harici CSS (post meta'dan)
    $css = $post->meta('css');
    if ( $css ) {
        $html .= '<link rel="stylesheet" href="' . get_template_directory_uri() . '/theme/templates/_custom/' . $css . '" media="all" crossorigin="anonymous">';
    }
}

modal_json_output( $html, modal_get_plugins_req($vars['id'] ?? 0), $vars, $error, $message );
