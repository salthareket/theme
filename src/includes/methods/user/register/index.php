<?php
$required_setting = ENABLE_MEMBERSHIP;

$salt = \Salt::get_instance();
echo json_encode($salt->register($vars));
wp_die();
