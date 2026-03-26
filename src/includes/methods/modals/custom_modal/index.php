<?php
$error = true;
$message = "";
$html = "";
if (isset($vars["id"])) {
    $error = false;
    $post = Timber::get_post($vars["id"]);
    $html = $post->content;
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
echo json_encode($output);
die();