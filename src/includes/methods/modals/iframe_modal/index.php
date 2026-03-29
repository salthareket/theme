<?php
$error   = true;
$message = 'Content not found';
$html    = '';

if (!empty($vars['url'])) {
    $error   = false;
    $message = '';
    $url     = esc_url($vars['url']);
    $height  = (int) ($vars['height'] ?? 500);
    $html    = "<iframe src=\"{$url}\" width=\"100%\" height=\"{$height}\" frameborder=\"0\" allowfullscreen></iframe>";
}

$response['error']   = $error;
$response['message'] = $message;
$response['html']    = $html;
echo json_encode($response);
wp_die();
