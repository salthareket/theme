<?php
$required_setting = ENABLE_MEMBERSHIP;

$salt   = \Salt::get_instance();
$status = $salt->user_exist($vars);

$response['error']   = (bool) $status;
$response['message'] = $status ?: '';
echo json_encode($response);
wp_die();
