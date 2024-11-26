<?php
$required_setting = ENABLE_MEMBERSHIP;

$salt = new Salt();
            $response = $salt->update_profile($vars);
            echo json_encode($response);
            die();