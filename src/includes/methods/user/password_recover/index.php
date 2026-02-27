<?php
$required_setting = ENABLE_MEMBERSHIP;

$salt = \Salt::get_instance();//new Salt();
echo $salt->password_recover($vars);
die();