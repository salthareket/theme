<?php
$required_setting = ENABLE_MEMBERSHIP;

$salt = new Salt();
$status = $salt->nickname_exist($vars);
$error = false;
$message = "";
if ($status) {
	$error = true;
	$message = $status;
}
$output = [
	"error" => $error,
	"message" => $message,
];
echo json_encode($output);
die();