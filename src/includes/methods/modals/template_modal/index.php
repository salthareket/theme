<?php
$error      = true;
$message    = "";
$html       = "";
$plugins_req = [];

if (isset($vars["template"])) {
    $error    = false;
    $template = $vars["template"];
    $context  = Timber::context();

    if (!empty($vars["id"])) {
        $post = Timber::get_post($vars["id"]);
        $context["post"] = $post;

        // Post'un asset'lerinden plugin listesini çek
        $assets  = $post->meta("assets");
        $plugins = $assets["plugins"] ?? [];

        if (!empty($plugins)) {
            $plugins_all = compile_files_config()["js"]["plugins"];
            foreach ($plugins as $plugin) {
                if (isset($plugins_all[$plugin]["init"])) {
                    $plugins_req[$plugin] = $plugins_all[$plugin]["init"];
                }
            }
        }
    }

    $context["data"] = $vars;
    $html = Timber::compile([$template . ".twig"], $context);
}

$output = [
    "error"   => $error,
    "message" => $message,
    "html"    => "",
];

if (isset($vars["title"])) {
    $output["data"] = [
        "title"   => $vars["title"],
        "body"    => $html,
        "plugins" => $plugins_req,
    ];
} else {
    $output["data"] = [
        "content" => $html,
        "plugins" => $plugins_req,
    ];
}

echo json_encode($output);
die();
