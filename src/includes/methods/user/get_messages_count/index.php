<?php
$required_setting = ENABLE_CHAT;

$data = [
	"error" => false,
	"message" => "",
	"data" => [
		"count" => yobro_unseen_messages_count(),
	],
];
echo json_encode($data);
die();