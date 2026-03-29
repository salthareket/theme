<?php
$required_setting = ENABLE_MEMBERSHIP;

$salt   = \Salt::get_instance();
$status = $salt->nickname_exist($vars);

$response['error']   = (bool) $status;
$response['message'] = $status ?: '';
echo json_encode($response);
wp_die();
