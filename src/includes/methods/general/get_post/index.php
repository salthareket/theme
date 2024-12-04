<?php
$error = true;
$message = "Content not found";
$post_data = array();
$html = "";
if (isset($vars["id"])) {
    $post_data = Timber::get_post($vars["id"]);
    $error = false;
    $message = "";
    if ($post_data) {
        $post_content = $post_data->get_blocks();
        if(ENABLE_MULTILANGUAGE){
            switch(ENABLE_MULTILANGUAGE){
                case "qtranslate-xt" :
                    $post_data->title = qtranxf_use($lang, $post_data->post_title, false, false);
                    $post_data->content = qtranxf_use($lang, $post_content, false, false);//nl2br(qtranxf_use($lang, $post->post_content, false, false));
                break;
                case "polylang" :
                    $post_data->title = $post_data->title;
                    $post_data->content = $post_content;
                break;
                case "wpml" :
                    $post_data->title = $post_data->title;
                    $post_data->content = $post_content;
                break;
            }
        }else{
            $post_data->title = $post_data->post_title;
            $post_data->content = $post_content;//nl2br($post->post_content);
        }
        if(isset($vars["template"])){
            $context = Timber::context();
            $context["post"] = $post_data;
            $html = Timber::compile($vars["template"], $context);
        }
    }
}
$output = [
    "error" => $error,
    "message" => $message,
    "data" => $post_data,
    "html" => $html
];
echo json_encode($output);
die();