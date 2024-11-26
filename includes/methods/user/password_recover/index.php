<?php
$required_setting = ENABLE_MEMBERSHIP;

$salt = new Salt();
echo $salt->password_recover($vars);
die();