<?php
$required_setting = ENABLE_FOLLOW;

    $salt = new Salt();
    $response = $salt->follow($vars["id"]);
    echo json_encode($response);
    die();