<?php
$salt = \Salt::get_instance();//new Salt();
echo json_encode($salt->login($vars));
die();
