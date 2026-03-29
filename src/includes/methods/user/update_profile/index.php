<?php
$required_setting = ENABLE_MEMBERSHIP;

$salt = \Salt::get_instance();
echo json_encode($salt->update_profile($vars));
wp_die();
