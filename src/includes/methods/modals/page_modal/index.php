<?php
if (isset($vars["id"])) {
    if (!is_numeric($vars["id"])) {
        switch ($vars["id"]) {
            case "privacy-policy":
                $post = get_post(get_option("wp_page_for_privacy_policy"));
            break;
            case "terms-conditions":
                $post_id = ENABLE_COMMERCE? wc_terms_and_conditions_page_id() : get_option('wp_page_for_privacy_policy');
                $post = get_post($post_id);
            break;
            default:
                global $wpdb;
                $post_id = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT ID FROM $wpdb->posts WHERE post_name = %s AND post_type= %s AND post_status = 'publish'",
                        $vars["id"],
                        "page"
                    )
                );
                $post = get_post($post_id);
            break;
        }
    } else {
        $post = get_post($vars["id"]);
    }
}

$error = true;
$message = "Content not found";
$post_data = [];
if ($post) {
    $error = false;
    $message = "";
    $post_data = array();
    $post_content = "";

    if (!isset($vars["selector"])) {
        $post = Timber::get_post($post);
        $blocks = $post->get_blocks();
        $post_content = $blocks["html"];
    }else{

        $post_url = get_permalink($post->ID);
        $response = wp_remote_get($post_url);
        $full_html = wp_remote_retrieve_body($response);

        if (is_wp_error($response) || empty($full_html)) {
            $post = Timber::get_post($post);
            $blocks = $post->get_blocks();
            $post_content = $blocks["html"];
            $message = "Sayfa içeriği çekilemedi.";
        } else {

            $selector = $vars["selector"]; // Örn: ".floor-plan-wrap" veya "#main-content"
            $dom = new DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $full_html);
            $xpath = new DOMXPath($dom);
            if (strpos($selector, '.') === 0) {
                $class_name = substr($selector, 1);
                $nodes = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $class_name ')]");
            } elseif (strpos($selector, '#') === 0) {
                $id_name = substr($selector, 1);
                $nodes = $xpath->query("//*[@id='$id_name']");
            } else {
                $nodes = $xpath->query("//$selector");
            }
            if ($nodes->length > 0) {
                $filtered_html = "";
                foreach ($nodes as $node) {
                    $filtered_html .= $dom->saveHTML($node);
                }
                $post_content = $filtered_html; // İçeriği sadece selector'dan gelenle ez
            } else {
                $post = Timber::get_post($post);
                $blocks = $post->get_blocks();
                $post_content = $blocks["html"];
                $message = "Selector ($selector) not found in content";
            }

        }
    }

    $post_data["required_js"] = $blocks["required_js"] ?? [];
    
    if(ENABLE_MULTILANGUAGE){
        switch(ENABLE_MULTILANGUAGE){
            case "qtranslate-xt" :
                $post_data["title"] = qtranxf_use($lang, $post->post_title, false, false);
                $post_data["content"] = qtranxf_use($lang, $post_content, false, false);//nl2br(qtranxf_use($lang, $post->post_content, false, false));
            break;
            case "polylang" :
                $post_data["title"] = $post->post_title;
                $post_data["content"] = $post_content;
            break;
            case "wpml" :
                $post_data["title"] = $post->post_title;
                $post_data["content"] = $post_content;
            break;
        }
    }else{
        $post_data["title"] = $post->post_title;
        $post_data["content"] = $post_content;//nl2br($post->post_content);
    }
}
$output = [
    "error" => $error,
    "message" => $message,
    "data" => $post_data,
    "html" => "",
];
echo json_encode($output);
die();