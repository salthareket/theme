<?php

$templates = array();
$context = Timber::context();
//$template = "acf-query-field/archive.twig";
//$template = 'acf-query-field-archive.twig';
//$vars["page"] = $vars["page"] + 1;
$template = 'acf-query-field/loop.twig';
$paginate = new Paginate([], $vars);
$result = $paginate->get_results($vars["type"]);
$context["slider"] = $vars["slider"];
$context["heading"] = $vars["heading"];
$context["posts"] = $result["posts"];
$templates = [];
if(!is_array($vars["templates"])){
    $templates = json_decode(stripslashes($vars["templates"]), true);       
}else{
    $templates = $vars["templates"];
}
$context["templates"] = $templates;
$context["is_preview"] = is_admin();
$response["data"] = $result["data"];
$response["html"] = Timber::compile($template, $context);
echo json_encode($response);
die();