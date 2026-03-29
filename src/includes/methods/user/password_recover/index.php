<?php
$required_setting = ENABLE_MEMBERSHIP;

$salt = \Salt::get_instance();
echo $salt->password_recover($vars);
wp_die();
