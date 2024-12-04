<?php
$html = "";
$map_service = get_field("map_service", "option");
$id = isset($vars["id"])?$vars["id"]:0;
$ids = isset($vars["ids"])?$vars["ids"]:[];
$lat = isset($vars["lat"])?$vars["lat"]:"";
$lng = isset($vars["lng"])?$vars["lng"]:"";
$title = isset($vars["title"])?$vars["title"]:get_bloginfo("name");
$popup = isset($vars["popup"])?$vars["popup"]:[];

$skeleton = array(
    "map_type" => "",
    "map_settings" => array(
        "lat" => "",
        "lng" => "",
        "zoom" => "",
        "map" => array(
            "markers" => array()
        ),
        "posts" => array(),
        "zoom_position" => $map_service=="leaflet"?"topleft":"TOP_LEFT",
        "buttons_position" => "",
        "buttons" => array(),
        "marker" => array(),
        "popup_active" => false,
        "popup_type" => "hover",
        "popup_template" => "",
        "popup_ajax" => false,
        "popup_width" => ""
    ),
);

if($popup){
    $skeleton["map_settings"]["popup_active"] = true;
    $skeleton["map_settings"]["popup_type"] = $popup["type"];
    $skeleton["map_settings"]["popup_template"] = "default";
}


if($id){
    $post = Timber::get_post($id);
    $post_data = $post->get_map_data();
    $skeleton["map_type"] = "static";
    $skeleton["map_settings"]["lat"] = $post_data["lat"];
    $skeleton["map_settings"]["lng"] = $post_data["lng"];
    $skeleton["map_settings"]["zoom"] = $post_data["zoom"];
    $skeleton["map_settings"]["map"]["markers"][] = $post_data;
    $html = get_map_config($skeleton);//get_map_config($post->get_map_data());
}else if($ids){
    $map_data = [];
    $posts = Timber::get_posts($ids);
    if($posts){
        $skeleton["map_type"] = "dynamic";
        $skeleton["map_settings"]["posts"] = $posts;
        $html = get_map_config($skeleton);
        /*$map_data = array(
           "map_type" => "dynamic",
           "map_settings" => array(
                "posts"    => $posts
           )
        );
        $html = get_map_config($map_data);*/
    }
}else if(!empty($lat) && !empty($lng)){
    $skeleton["map_type"] = "static";
    $skeleton["map_settings"]["lat"] = $lat;
    $skeleton["map_settings"]["lng"] = $lng;
    $map_data = array(
        "id"    => "marker_".unique_code(4),
        "title" => $popup?$popup["title"]:$title,
        "lat"   => $lat,
        "lng"   => $lng,
    );
    $skeleton["map_settings"]["map"]["markers"][] = $map_data;
    $html = get_map_config($skeleton);
    //$html = get_map_config($map_data);
}
$output = [
    "error" => false,
    "message" => "",
    "data" => [
        "title" => $title,
        "content" => $html,
    ],
    "html" => "",
];
echo json_encode($output);
die();