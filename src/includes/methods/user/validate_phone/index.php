<?php
$required_setting = ENABLE_MEMBERSHIP;

$salt   = \Salt::get_instance();
$status = $salt->validate_phone($vars['phone'] ?? '', $vars['country'] ?? '', $vars['phone_code'] ?? '');
echo json_encode($status);
wp_die();
