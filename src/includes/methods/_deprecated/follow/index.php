<?php
$required_setting = ENABLE_FOLLOW;

$salt = \Salt::get_instance();
echo json_encode($salt->follow($vars['id']));
wp_die();
