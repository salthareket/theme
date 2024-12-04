<?php

$error = true;
$message = "Content not found";
$html = "";
if (isset($vars["url"])) {
    $error = false;
    $message = "";
    $html = "<iframe src='" .$vars["url"]. "' width='100%' height='" .$vars["height"] . "'/>";
}
$output = [
    "error" => $error,
    "message" => $message,
    "data" => "",
    "html" => $html,
];
echo json_encode($output);
die();