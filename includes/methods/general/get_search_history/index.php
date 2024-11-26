<?php
$required_setting = ENABLE_SEARCH_HISTORY;

$user = array();
if(!isset($vars["user"])){
    $user = wp_get_current_user();
}
$search_history = new SearchHistory();
if($vars["history"] == "popular"){
    $title = translate("Popular search terms"); 
    $result = $search_history->get_popular_terms($vars["post_type"]);
}else{
    $title = translate("Your last searches");
    $result = $search_history->get_user_terms($user->ID, $vars["post_type"]);
}
if($result){
    $template = "partials/snippets/search-field-history.twig";
    $context = Timber::context();
    $context["title"] = $title;
    $context["search_terms"] = $result;
    $context["vars"] = $vars;
    $response["html"] = Timber::compile($template, $context);                
} 
echo json_encode($response);
die();