<?php
$required_setting = ENABLE_FOLLOW;

    $salt = \Salt::get_instance();//new Salt();
    $response = $salt->follow($vars["id"]);
    echo json_encode($response);
    die();