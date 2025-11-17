<?php
$salt = new Salt();
echo json_encode($salt->login($vars));
die();
