<?php
$error = true;
$message = "";
$html = "";
$plugins_req = [];
if (isset($vars["id"])) {
    $error = false;
    $post = Timber::get_post($vars["id"]);
    $html = $post->strip_tags($post->content);
    $assets = $post->meta("assets");
    $plugins = $assets["plugins"];
    
    if(!empty($plugins)){
        $plugins_all = compile_files_config()["js"]["plugins"];
        foreach ($plugins as $plugin) {
            $plugins_req[$plugin] = $plugins_all[$plugin]["init"];
        }
    }
    $vars["plugins"] = $plugins_req;
    if($assets && !empty($assets["css"])){
        $html .= "<style type='text/css'>".$assets["css"]."</style>";
    }
    $css = $post->meta("css");
    if($css){
        $html .= '<link id="template-'.$vars["id"].'-css" rel="stylesheet" href="'. get_template_directory_uri() .'/theme/templates/_custom/'.$css.'" as="style" media="all" crossorigin="anonymous">';
    }
    $data = $vars; //["data"];
}
$output = [
    "error" => $error,
    "message" => $message,
    "html" => "",
];
if(isset($vars["title"])){
    $output["data"] = array(
        "title" => $vars["title"],
        "body" => $html
    );
}else{
    $output["data"] = array(
        "content" => $html
    );
}
$output["data"]["plugins"] = $plugins_req;
echo json_encode($output);
die();