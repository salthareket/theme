<?php
$error   = true;
$message = 'Content not found';
$post_data = [];
$html    = '';

if (isset($vars['id'])) {
    $post_data = Timber::get_post($vars['id']);
    $error     = false;
    $message   = '';

    if ($post_data) {
        $post_content = $post_data->get_blocks()['html'];

        if (ENABLE_MULTILANGUAGE && ENABLE_MULTILANGUAGE === 'qtranslate-xt') {
            $post_data->title   = qtranxf_use($lang, $post_data->post_title, false, false);
            $post_data->content = qtranxf_use($lang, $post_content, false, false);
        } else {
            $post_data->title   = $post_data->post_title;
            $post_data->content = $post_content;
        }

        if (!empty($vars['template'])) {
            $context         = Timber::context();
            $context['post'] = $post_data;
            $html = Timber::compile($vars['template'], $context);
        }
    }
}

$response['error']   = $error;
$response['message'] = $message;
$response['data']    = $post_data;
$response['html']    = $html;
echo json_encode($response);
wp_die();
