<?php
$salt = \Salt::get_instance();
echo json_encode($salt->login($vars));
wp_die();
