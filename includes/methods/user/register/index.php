<?php
$required_setting = ENABLE_MEMBERSHIP;

$salt = new Salt();
echo json_encode($salt->register($vars));
die();
