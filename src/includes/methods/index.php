<?php
switch ($method) {
case 'acf_layout_posts':
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
break;
case 'comment_product':
$salt = new Salt();
echo $salt->comment_product($vars);
die();
break;
case 'comment_product_detail':
$salt = new Salt();
echo $salt->comment_product_detail($vars);
die();
break;
case 'comment_product_modal':
$modal = "";
$data = [];
$template = "tour-plan/comment-modal";
$comment = new Timber\Comment(intval($vars["id"]));
$title = $comment->comment_title;
$comments = json_decode($comment->comment_content);
$author = $comment->comment_author;
//$rating = '<div class="star-rating-readonly-ui" data-stars="5" data-value="'.$comment->rating.'"></div>';
$image = wp_get_attachment_image_url(
$comment->comment_image,
"medium_large"
);
$tour_plan_id = $comment->meta("comment_tour");
$tour_plan_offer_id = get_field(
"tour_plan_offer_id",
$tour_plan_id
);
$agent_id = get_post_field("post_author", $tour_plan_offer_id);
$agent = get_user_by("id", $agent_id);
$destinations = get_terms(
"taxonomy=destinations&include=" .
join(",", $comment->meta("comment_destination"))
);
$destinations = wp_list_pluck($destinations, "name");
/*$destination_list = '<ul class="list-inline mb-0">';
foreach($destination as $item){
$destination_list .= '<li class="list-inline-item mb-2"><div class="btn btn-warning btn-unlinked rounded-pill">'.$item.'</div></li>';
}
$destination_list .= '</ul>';*/
$templates = [$template . ".twig"];
$context = Timber::context();
$context["title"] = $title;
$context["comments"] = $comments;
$context["author"] = $author;
$context["image"] = $image;
$context["agent"] = $agent;
$context["destinations"] = $destinations;
$context["vars"] = $vars;
$data = [
"error" => false,
"message" => "",
"data" => $data,
"html" => "",
];
break;
case 'autocomplete_terms':
$error = false;
$response = [];
$response["results"] = [];
if (isset($vars["type"])) {
if(isset( $vars["keyword"])){
$keyword = $vars["keyword"];
}
if(!isset($keyword)){
$keyword = $_POST["keyword"];
}
if($vars["type"] == "user"){
$user = true;
$taxonomy = false;
$post_type = false;
}else{
$user = false;
$taxonomy = taxonomy_exists($vars["type"]);
$post_type = post_type_exists($vars["type"]);
}
if (!isset($vars["response_type"])) {
$vars["response_type"] = "select2";
}
if (!isset($vars["count"])) {
$vars["count"] = 10;
}
if (!isset($vars["page"])) {
$vars["page"] = 1;
}
$offset = ($vars["page"] - 1) * $vars["count"];
if ($taxonomy) {
$args = [
"taxonomy" => $vars["type"],
"hide_empty" => false,
"number" => $vars["count"],
"offset" => $offset,
"fields" => "id=>name",
];
if (isset($vars["value"])) {
$args["include"] = $vars["value"];
}
if (isset($vars["selected"])) {
$args["exclude"] = $vars["selected"];
}
if (!empty($keyword)) {
$args["search"] = $keyword;
$total_terms = wp_count_terms($args);
} else {
$total_terms = wp_count_terms($vars["type"]);
}
$total_pages = ceil($total_terms / $vars["page"]);
$terms = get_terms($args);
}
if ($post_type) {
$args = [
"post_type" => $vars["type"],
"posts_per_page" => $vars["count"],
"offset" => $offset,
"fields" => "id=>title",
];
if (!empty($keyword)) {
$args["s"] = $keyword;
$total_terms = wp_count_posts_by_query($args);
} else {
$total_terms = wp_count_posts($vars["type"])->publish;
}
$total_pages = ceil($total_terms / $vars["page"]);
$terms = Timber::get_posts($args)->to_array();
}
if($user){
$search_string = esc_attr( trim( $keyword ) );
$parts = explode( ' ', $search_string );
$args = array(
//'search'         => "*{$search_string}*",
/* 'search_columns' => array(
'user_login',
'user_nicename',
'user_email',
'user_url',
),*/
);
if( ! empty( $parts ) ){
$args['meta_query'] = [];
$args['meta_query']['relation'] = 'OR';
foreach( $parts as $part ){
$args['meta_query'][] = array(
'key'     => 'first_name',
'value'   => $part,
'compare' => 'LIKE'
);
$args['meta_query'][] = array(
'key'     => 'last_name',
'value'   => $part,
'compare' => 'LIKE'
);
}
}
$users = new WP_User_Query( $args );
//print_r($users);
$terms = $users->get_results();
//print_r($terms);
}
switch ($vars["response_type"]) {
case "select2":
if ($taxonomy) {
foreach ($terms as $key => $term) {
$response["results"][] = [
"id" => $key,
"text" => $term
];
}
}
if ($post_type) {
foreach ($terms as $key => $term) {
$text = $term->post_title;
if(!empty($vars["response_extra"])){
$extras = explode(",", $vars["response_extra"]);
foreach($extras as $extra){
$extra = Trim($extra);
switch($extra){
case "author" :
$text .= " - ".$term->author->display_name;
break;
default:
$text .= " - ".$term->{$extra};
break;
}
}
}
$response["results"][] = [
"id" => $term->ID,
"text" => $text
];
}
}
if ($user) {
foreach ($terms as $key => $term) {
$response["results"][] = [
"id" => $term->ID,
"text" => $term->first_name." ".$term->last_name,
];
}
}
if ($vars["page"] < $total_pages && $terms) {
$response["pagination"]["more"] = true;
} else {
$response["pagination"]["more"] = false;
}
break;
case "autocomplete":
if ($taxonomy) {
foreach ($terms as $key => $term) {
$response["results"][$key] =  $term;
}
}
if ($post_type) {
foreach ($terms as $key => $term) {
$response["results"][$term->ID] = $term->post_title;
}
}
if ($user) {
foreach ($terms as $key => $term) {
$response["results"][$term->ID] = $term->first_name." ".$term->last_name;
}
}
break;
}
$data = $response;
} else {
$error = true;
$message = "Please provide a type";
}
$output = [
"error" => $error,
"message" => $message,
"data" => $data,
"html" => $html,
"redirect" => $redirect_url,
];
if($vars["response_type"] == "autocomplete"){
$output = $output["data"]["results"];
}
echo json_encode($output);
die();
break;
case 'get_post':
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
break;
case 'pagination_ajax':
$query_pagination_vars = array();
$query_pagination_request = "";
if(!empty($vars["query_pagination_vars"]) || !empty($vars["query_pagination_request"])){
$enc = new Encrypt();
if(!empty($vars["query_pagination_vars"])){
$query_pagination_vars = $enc->decrypt($vars["query_pagination_vars"]);
}
if(!empty($vars["query_pagination_request"])){
$query_pagination_request = $enc->decrypt($vars["query_pagination_request"]);
}
}
$args = $query_pagination_vars;// $_SESSION['query_pagination_vars'][$vars["post_type"]];
if(isset($vars['posts_per_page'])){
$args["posts_per_page"] = $vars['posts_per_page'];
}
//if(isset($_SESSION['query_pagination_request'][$vars["post_type"]])){
if(!empty($query_pagination_request)){
//$request = $_SESSION['query_pagination_request'][$vars["post_type"]];
$request = $query_pagination_request;
$request = explode("LIMIT", $request)[0];
$request .= " LIMIT ".($args['posts_per_page'] * ($vars['page']-1)).", ".$args['posts_per_page'];
//echo "<div class='col-12 alert alert-success'>".$request."</div>";
global $wpdb;
$results = $wpdb->get_results($request);
if ($results) {
$results = wp_list_pluck($results, "ID");
$post_args = array(
"post_type" => $args["post_type"]=="search"?"any":$args["post_type"],
"post__in" => $results,
"posts_per_page" => -1,
//"order" => "ASC",
"orderby" => "post__in",
'suppress_filters' => true
);
}
}else{
$post_args = $args;
$post_args['paged'] = $vars['page'];
$post_args['post_type'] = $args["post_type"]=="search"?"any":$args["post_type"];
//$post_args['page'] = $vars['page'];
}
$GLOBALS["pagination_page"] = $vars['page'];
unset($post_args["querystring"]);
unset($post_args["page"]);
if(isset($post_args["s"])){
if(empty($post_args["s"])){
unset($post_args["s"]);
}
}
//echo "<div class='col-12 alert alert-success'>".json_encode($post_args)."</div>";
$html = "";
$query = SaltBase::get_cached_query($post_args);
$folder = $post_args["post_type"];
if($args["post_type"] == "any" || is_array($args["post_type"])){
$folder = "search";
}
if($post_args["post_type"] == "any" || is_array($post_args["post_type"])){
$folder = "search";
}
error_log(print_r($args, true));
error_log(print_r($query, true));
if($args["post_type"] == "product"){
if ($query->have_posts()) :
while ($query->have_posts()) : $query->the_post();
ob_start(); // Çıktıyı tampona al
wc_get_template_part('content', 'product');
$html .= ob_get_clean(); // Tampondaki çıktıyı $am değişkenine ekle
$GLOBALS["pagination_page"] = "";
endwhile;
endif;
}else{
if ($query->have_posts()){
$index = ($vars['page'] ) * $args['posts_per_page']; // Mevcut sayfa için ofset hesaplama
$query = Timber::get_posts($query);
//foreach($query->posts as $post){
foreach($query as $post){
ob_start();
$context = Timber::context();
$index++;
$context['index'] = $index;
$context['post'] = $post;//Timber::get_post($post);
Timber::render([$folder."/tease.twig", "tease.twig"], $context);
$html .= ob_get_clean();
$context = null;
$GLOBALS["pagination_page"] = "";
}
}
}
wp_reset_query();
$data = $response;
$data["html"] = minify_html($html);
$total = (int) $vars["total"];
$per_page = (int) $args["posts_per_page"];
$current = (int) $vars["page"];
$initial = (int) $vars["initial"];
if (1 === $total) {
$data["data"] = _e('Showing the single result', 'woocommerce');
} elseif ($total <= $per_page || -1 === $per_page) {
/* translators: %d: total results */
$data["data"] = sprintf(_n('Showing all %d result', 'Showing all %d results', $total, 'woocommerce'), $total)." - ".$total." - ".$per_page;
} else {
$first = ($per_page * $current) - $per_page + 1;
$last  = min($total, $per_page * $current);
if(!empty($initial) && $initial > 0){
if($current < $initial){
$last = min($total, $per_page * $initial);
}else{
$first = ($per_page * $initial) - $per_page + 1;
}
}
/* translators: 1: first result 2: last result 3: total results */
$data["data"] = sprintf(_nx('Showing %1$d&ndash;%2$d of %3$d result', 'Showing %1$d&ndash;%2$d of %3$d results', $total, 'with first and last result', 'woocommerce'), $first, $last, $total);
}
echo json_encode($data);
wp_die();
break;
case 'site_config':
$meta = [];
if(isset($vars["meta"])) {
$meta = $vars["meta"];
}
echo json_encode(SaltHareket\Theme::get_site_config(1, $meta));
die();
break;
case 'twig_render':
$template = $vars["template"];
$templates = [$template . ".twig"];
$data = $vars["data"];
$context = Timber::context();
$context["data"] = $data;
echo Timber::compile($templates, $context);
die();
break;
case 'get_available_districts':
echo json_encode(
get_available_districts($vars["post_type"], $vars["city"])
);
die();
break;
case 'get_city_options':
if(!isset($vars["country"])){
$check = array_column($vars, 'country');
if($check){
$vars["country"] = $check[0];
}
}
$localization = new Localization();
$localization->woocommerce_support(false);
echo json_encode($localization->states([
"country_code" => $vars["country"]
]));
//echo json_encode(get_cities($vars["country"], $vars["selected"]));
die();
break;
case 'get_country_options':
echo json_encode(
get_countries(
$vars["continent"],
$vars["selected"],
isset($vars["all"])?$vars["all"]:false
)
);
die();
break;
case 'get_districts':
echo json_encode(get_districts($vars["city"]));
die();
break;
case 'get_nearest_locations':
//$geolocation = new Geolocation_Query($vars);
$locations = GeoLocation_Query(
$vars["lat"],
$vars["lng"],
$vars["post_type"],
$vars["distance"],
$vars["limit"],
"ThemePost"
);
if(in_array("posts", $vars["output"])){
$context = Timber::context();
$context["posts"] = $locations;
$response["html"] = Timber::compile($vars["template"].".twig", $context);
}
if(in_array("markers", $vars["output"])){
$response["data"] = $GLOBALS["salt"]->get_markers($locations);
}
echo json_encode($response);
die();
break;
case 'get_posts_by_city':
echo json_encode(
get_posts_by_city($vars["post_type"], $vars["city"])
);
die();
break;
case 'get_posts_by_district':
$data = [];
$template = $vars["post_type"] . "/archive-ajax";
$data = get_posts_by_district(
$vars["post_type"],
$vars["city"],
$vars["district"]
);
$templates = [$template . ".twig"];
$context = Timber::context();
$context["vars"] = $vars;
$context["data"] = $data;
$data = [
"error" => false,
"message" => "",
"data" => $data,
"html" => "",
];
break;
case 'get_states':
$woo_countries = new WC_Countries();
$states = $woo_countries->get_states($vars["id"]);
echo json_encode($states);
die();
break;
case 'form_modal':
require_once( ABSPATH . 'wp-load.php' );
if ( function_exists( 'wpcf7_enqueue_scripts' ) ) {
wpcf7_enqueue_scripts();
}
if ( function_exists( 'wpcf7_enqueue_styles' ) ) {
wpcf7_enqueue_styles();
}
if ( function_exists( 'wpcf7cf_enqueue_scripts' ) ) {
wpcf7cf_enqueue_scripts();
}
if ( function_exists( 'wpcf7cf_enqueue_styles' ) ) {
wpcf7cf_enqueue_styles();
}
$output = [
"error" => false,
"message" => "",
"data" => [
"title" => $vars["title"],
"content" => do_shortcode(
'[contact-form-7 id="' . $vars["id"] . '"]'
),
],
"html" => "",
];
echo json_encode($output);
die();
break;
case 'iframe_modal':
$error = true;
$message = "Content not found";
$html = "";
if (isset($vars["url"])) {
$error = false;
$message = "";
$html = "<iframe src='" .$vars["url"]. "' width='100%' height='" .$vars["height"] . "'/>";
}
$output = [
"error" => $error,
"message" => $message,
"data" => "",
"html" => $html,
];
echo json_encode($output);
die();
break;
case 'map_modal':
$html = "";
$map_service = SaltBase::get_cached_option("map_service");//get_cached_field("map_service", "option");
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
$args = array(
'post__in' => $ids,
'posts_per_page' => -1,
'orderby' => 'post__in',
);
$posts = SaltBase::get_cached_query($args);
$posts = Timber::get_posts($posts);
//$posts = Timber::get_posts($ids);
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
break;
case 'page_modal':
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
//if(has_blocks($post->post_content)){
$post = Timber::get_post($post);
$post_content = $post->get_blocks();
//}else{
//$post_content = $post->post_content;
//}
if(ENABLE_MULTILANGUAGE){
switch(ENABLE_MULTILANGUAGE){
case "qtranslate-xt" :
$post_data["title"] = qtranxf_use($lang, $post->post_title, false, false);
$post_data["content"] = qtranxf_use($lang, $post_content, false, false);//nl2br(qtranxf_use($lang, $post->post_content, false, false));
break;
case "polylang" :
$post_data["title"] = $post->title;
$post_data["content"] = $post_content;
break;
case "wpml" :
$post_data["title"] = $post->title;
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
break;
case 'template_modal':
$error = true;
$message = "";
$html = "";
if (isset($vars["template"])) {
$error = false;
$template = $vars["template"];
$templates = [$template . ".twig"];
$data = $vars; //["data"];
$context = Timber::context();
$context["data"] = $data;
$html = Timber::compile($templates, $context);
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
break;
case 'get_floors':
$search_filters = array();
$vars_new = array();
$vars_new["ignore_empty_floor"] = true;
if(isset($vars["magaza_tipleri"])){
if($vars["magaza_tipleri"]){
if(!isset($vars_new["taxonomy"])){
$vars_new["taxonomy"] = array();
}
$vars_new["taxonomy"]["magaza-tipleri"] = $vars["magaza_tipleri"];
}
}
if(isset($vars["katlar"])){
if($vars["katlar"]){
$vars_new["id"] = $vars["katlar"];
$search_filters["katlar"] = get_the_title( $vars["katlar"] );
}
}
if(isset($vars["hizmetler"])){
if($vars["hizmetler"]){
if(!isset($vars_new["taxonomy"])){
$vars_new["taxonomy"] = array();
}
$vars_new["taxonomy"]["hizmetler"] = $vars["hizmetler"];
$search_filters["hizmetler"] = get_term_by("slug", $vars["hizmetler"], "hizmetler")->name;
}
}
if(isset($vars["kampanya"])){
$vars_new["campaign"] = boolval($vars["kampanya"]);
$search_filters["kampanya"] = $vars_new["campaign"];
}
$outlet = new Project();
$output = $outlet->katlar($vars_new);
if(!$template){
$template = "partials/floors";
}
$templates = array( $template.'.twig' );
$context = Timber::context();
$context['floors'] = $output["data"];
$context['store_count'] = $output["count"];
/*if($search_filters){
$search_filters_text = "";
foreach($search_filters as $key => $filter){
switch($key){
case "katlar" :
$search_filters_text .= $filter." içinde ";
break;
case "hizmetler" :
$search_filters_text .= $filter." kategorisinde ";
break;
case "kampanya" :
if($filter){
$search_filters_text .= "kampanyalı ";
}
$search_filters_text .= "<b>".$output["count"]."</b> mağaza bulundu.";
break;
}
}
}*/
if($search_filters){
$search_filters_text = "";
$trans_arr = array();
foreach($search_filters as $key => $filter){
switch($key){
case "katlar" :
$trans_arr["%floor"] = "<b>".$filter."</b>";
$search_filters_text .= "%floor içinde ";
break;
case "hizmetler" :
$trans_arr["%category"] = "<b>".$filter."</b>";
$search_filters_text .= "%category kategorisinde ";
break;
case "kampanya" :
if($filter){
$trans_arr["%campaign"] = "<b>kampanyalı</b> ";
$search_filters_text .= "%campaign ";
}
break;
}
}
if($search_filters_text){
$trans_arr["%count"] = "<b>".$output["count"]."</b>";
$singular = $search_filters_text."toplam %count adet mağaza bulundu.";
$plural = "";//$search_filters_text."toplam %counts adet mağaza bulundu.";
//replacement
$search_filters_text = trans_plural($singular, $plural, $output["count"]);//trans( $search_filters_text, "", $output["count"] );
$find       = array_keys($trans_arr);
$replace    = array_values($trans_arr);
$search_filters_text = str_ireplace($find, $replace, $search_filters_text);
$context['search_filters_text'] = $search_filters_text;
}
}
break;
case 'get_store_modal':
$outlet = new Project();
$output = $outlet->store($vars);
//if(!$template){
$template = "magazalar/single-modal";
//}
$templates = array( $template.'.twig' );
$context = Timber::context();
$context['post'] = $output;
break;
case 'get_stories':
$outlet = new Project();
$stories = $outlet->kampanyalar($vars)["data"];
$language = array(
"unmute" => trans('Sesi açmak için dokun'),
"keyboardTip" => trans('Geçmek için "Boşluk" tuşuna tıklayın'),
"visitLink" => trans("Linke git"),
"time" => array(
"ago" => trans('önce'),
"hour" => trans('saat'),
"hours" => trans('saat'),
"minute" => trans('dakika'),
"minutes" => trans('dakika'),
"fromnow" => trans('şu andan beri'),
"seconds" => trans('saniye'),
"yesterday" => trans('dün'),
"tomorrow" => trans('yarın'),
"days" => trans('gün'),
)
);
$output = array(
"stories" => $stories,
"language" => $language
);
echo json_encode($output);
die;
//print_r($output);
/*if(!$template){
$template = "partials/stories";
}
$templates = array( $template.'.twig' );
$context = Timber::get_context();
$context['posts'] = $output["data"];*/
break;
case 'search_store':
$output = [];
if (isset($vars["keyword"])) {
$args = [
"post_type" => ["magazalar"],
"order" => "ASC",
"orderby" => "title",
"posts_per_page" => 10,
//'numberposts' => 10,
//'nopaging' => true,
"s" => $vars["keyword"],
];
$args = wp_query_addition($args, $vars);
$posts = get_posts($args);
if (!$posts) {
unset($args["s"]);
$term_ids = get_terms([
"name__like" => $vars["keyword"],
"fields" => "ids",
]);
if (!isset($args["tax_query"])) {
$args["tax_query"] = [];
}
$args["tax_query"][] = [
"taxonomy" => "hizmetler",
"field" => "term_id",
"terms" => $term_ids,
];
$posts = get_posts($args);
} else {
/*global $wpdb;
$keys = [];
foreach ($posts as $key => $post) {
$results = $wpdb->get_results(
$wpdb->prepare(
"SELECT count(*) as count, post_id as floor FROM {$wpdb->prefix}postmeta WHERE meta_key like 'stores_%_store' and meta_value = %s",
$post->ID
)
);
if (
$results[0]->count == 0 ||
get_post_status($results[0]->floor) != "publish"
) {
$keys[] = $key;
}
}
if (count($keys) > 0) {
foreach ($keys as $key) {
unset($posts[$key]);
}
}*/
/* global $wpdb;
$ids = wp_list_pluck($posts, 'ID');
print_r($ids);
if ($ids) {
$ph = implode(',', array_fill(0, count($ids), '%d'));
$sql = $wpdb->prepare(
"SELECT DISTINCT pm.meta_value AS store_id
FROM {$wpdb->postmeta} pm
JOIN {$wpdb->posts} p
ON p.ID = pm.post_id
AND p.post_status = 'publish'
WHERE pm.meta_key LIKE 'stores_%_store'
AND pm.meta_value IN ($ph)",
$ids
);
$found_ids = array_map('intval', (array) $wpdb->get_col($sql));
print_r($found_ids);
// sadece bulunanları bırak
$posts = array_values(array_filter($posts, function($p) use ($found_ids) {
return in_array((int)$p->ID, $found_ids, true);
}));
}*/
}
foreach ($posts as $post) {
$image = get_field("logo", $post->ID);
$post_item = [
"id" => $post->ID,
"name" => $post->post_title,
"url" => get_permalink($post->ID),
"image" => $image,
];
$output[] = $post_item;
}
}
echo json_encode($output);
die();
break;
}
