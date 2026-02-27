<?php
$required_setting = ENABLE_MEMBERSHIP;

$salt = \Salt::get_instance();//new Salt();
echo json_encode($salt->register($vars));
die();
