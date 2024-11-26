<?php
echo json_encode(
	get_available_districts($vars["post_type"], $vars["city"])
);
die();