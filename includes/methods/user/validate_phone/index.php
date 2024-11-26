<?php
$required_setting = ENABLE_MEMBERSHIP;

$status = $GLOBALS["salt"]->validate_phone($vars["phone"], $vars["country"], $vars["phone_code"]);
echo json_encode($status);
die();