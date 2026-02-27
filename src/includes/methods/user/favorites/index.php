<?php
$required_setting = ENABLE_FAVORITES;

$salt = \Salt::get_instance();//ew Salt();
$response = $salt->favorites($vars);
if($vars["action"] == "get"){
	$context = Timber::context();
	$context["users"] = $response["posts"];//->get_results();
	$context["tease"] = $vars["tease"];
	$context["class"] = "bg-white p-3 mb-3 rounded-4";
	$context["type"] = "favorites";
	$response["html"] = Timber::compile("experts/archive.twig", $context);
	unset($response["posts"]);
}
echo json_encode($response);
die();   