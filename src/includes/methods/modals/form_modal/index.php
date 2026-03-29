<?php
if (function_exists('wpcf7_enqueue_scripts'))  wpcf7_enqueue_scripts();
if (function_exists('wpcf7_enqueue_styles'))   wpcf7_enqueue_styles();
if (function_exists('wpcf7cf_enqueue_scripts')) wpcf7cf_enqueue_scripts();
if (function_exists('wpcf7cf_enqueue_styles'))  wpcf7cf_enqueue_styles();

$form_id = absint($vars['id'] ?? 0);

$response['error']   = false;
$response['message'] = '';
$response['data']    = [
    'title'   => $vars['title'] ?? '',
    'content' => do_shortcode('[contact-form-7 id="' . $form_id . '"]'),
];
echo json_encode($response);
wp_die();
