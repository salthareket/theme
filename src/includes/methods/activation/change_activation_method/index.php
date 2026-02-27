<?php
$required_setting = ENABLE_MEMBERSHIP_ACTIVATION;

$salt = \Salt::get_instance();//new Salt();
echo json_encode($salt->change_activation_method($vars));
die();