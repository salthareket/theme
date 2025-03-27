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
//$query = new WP_Query($post_args);
$query = SaltBase::get_cached_query($post_args);
$folder = $post_args["post_type"];
if($args["post_type"] == "any" || is_array($args["post_type"])){
$folder = "search";
}
if($post_args["post_type"] == "any" || is_array($post_args["post_type"])){
$folder = "search";
}
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
$map_service = SaltBase::get_cached_option("map_service");//get_field("map_service", "option");
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
$post = get_post(
get_option("wp_page_for_privacy_policy")
);
break;
case "terms-conditions":
$post = get_post(wc_terms_and_conditions_page_id());
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
case 'custom_track_product_view':
custom_track_product_view_js($vars["post_id"]);
die();
break;
case 'get_cart':
global $woocommerce;
$cart = woo_get_cart_object();
$context = Timber::context();
$context["type"] = "cart";
$context["cart"] = $cart;
$response["data"] = array(
"count" => $woocommerce->cart->get_cart_contents_count()
);
$template = "partials/".$vars["type"]."/archive.twig";
$response["html"] = Timber::compile($template, $context);
echo json_encode($response);
die();
break;
case 'get_products':
if (isset($vars["kategori"])) {
$page_type = "product_cat";
}
if (isset($vars["keyword"])) {
$page_type = "search";
$GLOBALS["keyword"] = $vars["keyword"];
add_filter("posts_where", "sku_where");
}
$templates = [$template . ".twig"];
$context = Timber::context();
//$query = new WP_Query();
$query = [];
$query_response = category_queries_ajax($query, $vars);
$query = $query_response["query"];
$GLOBALS["query_vars"] = woo_sidebar_filter_vars($vars); //$query_response["query_vars"];
$data["query_vars"] = $GLOBALS["query_vars"];
$closure = function ($sql) {
//$role = array_keys($GLOBALS["user"]->roles)[0];
//print_r($GLOBALS['query_vars']);
// remove single quotes around 'mt1.meta_value'
//print_r($sql);
// $sql = str_replace("CAST(mt2.meta_value AS SIGNED)","CAST(mt2.meta_value-(mt2.meta_value/2) AS SIGNED)", $sql);// 50% indirim
return str_replace("'mt2.meta_value'", "mt2.meta_value", $sql);
};
add_filter("posts_request", $closure);
query_posts($query);
//$query = new WP_Query($args);
//$posts = new WP_Query( $query );
remove_filter("posts_request", spl_object_hash($closure));
$posts = Timber::get_posts();
$context["posts"] = $posts; //_new;
//$queried_object = get_queried_object();
if (ENABLE_FAVORITES) {
$context["favorites"] = $GLOBALS["favorites"];
}
$context["pagination_type"] =
$GLOBALS["site_config"]["pagination_type"];
if (isset($GLOBALS["query_vars"])) {
$query_vars = $GLOBALS["query_vars"];
}
global $wp_query;
$post_count = $wp_query->found_posts;
$page_count = $wp_query->max_num_pages;
$page = $wp_query->query_vars["paged"];
$context["post_count"] = $post_count;
$context["page_count"] = $page_count;
$context["page"] = $page;
//if(array_key_exists( "pagination", $context['posts'] )){
$context["pagination"] = Timber::get_pagination(); //$context['posts']->pagination;//Timber::get_pagination();
//}
//$context['pagination'] = Timber::get_pagination();
//print_r($context['posts']);
//$context['page_count'] = 1;//Timber::get_pagination(array(),$context['posts']);//floor(abs(Timber::get_pagination()["total"])/$GLOBALS['ajax_product_count']);//Timber::get_pagination();
//echo $page;//json_encode($query_args);
//echo json_encode(get_posts($query_args));
//die;
if ($vars["product_filters"] && ENABLE_FILTERS) {
$data["sidebar"] = Timber::compile(
"woo/sidebar-product-filter.twig",
woo_sidebar_filters(
$context,
$page_type,
500,
$query,
$vars
)
);
}
wp_reset_postdata();
wp_reset_query();
break;
case 'pay_now':
$salt = new Salt();
$id = $vars["id"];
$salt->remove_cart_content();
//$salt->update_product_price($id);
$salt->add_to_cart($id);
$redirect_url = woo_checkout_url();
$output = [
"error" => false,
"message" => "",
"data" => "",
"html" => "",
"redirect" => $redirect_url,
];
echo json_encode($output);
die();
break;
case 'salt_recently_viewed_products':
$data = [];
$template = $vars["ajax"]["template"];
$data = Timber::get_posts(salt_recently_viewed_products());
$templates = [$template];
//$context = Timber::context();
//print_r($vars);
//$context["vars"] = $vars;
//$context["vars"]["posts"] = $data->to_array();
$vars["posts"] = $data->to_array();
return [
"error" => false,
"message" => "",
"data" =>  $vars,
"html" => "",
];
break;
case 'wc_api_create_product':
echo json_encode(wc_api_create_product($vars["data"]));
die();
break;
case 'wc_api_create_product_variation':
echo json_encode(
wc_api_create_product_variation($vars["data"], $vars["id"])
);
die();
break;
case 'wc_api_filter_products':
$args = ["tag" => "103,63"];
echo json_encode($GLOBALS["woo_api"]->get("products", $args)); //$vars["filters"]));
die();
break;
case 'wc_api_update_product':
echo json_encode(wc_api_update_product($vars["data"]));
die();
break;
case 'wc_cart_clear':
global $woocommerce;
$woocommerce->cart->empty_cart();
die();
break;
case 'wc_cart_item_remove':
global $woocommerce;
$woocommerce->cart->remove_cart_item($vars["key"]);
$cart = woo_get_cart_object();
$context = Timber::context();
$context["type"] = "cart";
$context["cart"] = $cart;
$response["data"] = array(
"count" => $woocommerce->cart->get_cart_contents_count()
);
$template = "partials/".$vars["type"]."/archive.twig";
$response["html"] = Timber::compile($template, $context);
echo json_encode($response);
die();
break;
case 'wc_cart_quantity_update':
global $woocommerce;
$woocommerce->cart->set_quantity($vars["key"], $vars["count"]);
//$woocommerce->cart->get_cart_contents_count();
echo json_encode(woo_get_cart_object());
die();
break;
case 'wc_modal_page_template':
global $woocommerce;
$context = Timber::context();
$context["date"] = date("d.m.Y");
$content = apply_filters(
"the_content",
get_post_field("post_content", $id)
);
$customer_data = $woocommerce->cart->get_customer();
$shipping_data = $customer_data->shipping;
$customer = [
"name" =>
$customer_data->first_name .
" " .
$customer_data->last_name,
"shipping_address" =>
$shipping_data["address_1"] .
" " .
$shipping_data["city"] .
" " .
$shipping_data["state"] .
" " .
$shipping_data["postcode"] .
" " .
$shipping_data["country"],
"phone" => $customer_data->billing["phone"],
"email" => $customer_data->email,
"ip" => $_SERVER["REMOTE_ADDR"],
];
$context["customer"] = $customer;
$cart = [];
$discount_total = 0;
$tax_total = 0;
$items = $woocommerce->cart->get_cart();
foreach ($items as $item => $values) {
$_product = wc_get_product($values["data"]->get_id());
$getProductDetail = wc_get_product($values["product_id"]);
//$price = get_post_meta($values['product_id'] , '_price', true);
//echo "Regular Price: ".get_post_meta($values['product_id'] , '_regular_price', true)."<br>";
//echo "Sale Price: ".get_post_meta($values['product_id'] , '_sale_price', true)."<br>";
$tax = $values["line_subtotal_tax"];
$regular_price = $_product->get_regular_price();
//$sale_price = $_product->get_sale_price();
//$discount = ($regular_price - $sale_price);// * $values['quantity'];
//$discount_total += $discount;
$tax_total += $tax;
$cart_item = [
"image" => $getProductDetail->get_image("thumbnail"),
"title" => $_product->get_title(),
"price" => woo_get_currency_with_price(
get_post_meta($values["variation_id"], "_price", true)
),
"quantity" => $values["quantity"],
"tax" => woo_get_currency_with_price($tax),
"total_price" => woo_get_currency_with_price(
$values["line_subtotal"]
),
];
$cart[] = $cart_item;
}
$context["cart"] = $cart;
$context["total_tax"] = woo_get_currency_with_price($tax_total);
//$context["shipping_price"] = $woocommerce->cart->get_cart_shipping_total();
//$context["discount_price"] = woo_get_currency_with_price($discount_total);
$context["total"] = woo_get_currency_with_price(
$woocommerce->cart->total
);
Timber::render_string($content, $context);
die();
break;
case 'wc_order_list':
$order_number = $vars["order_number"];
woocommerce_order_details_table($order_number);
die();
break;
case 'woo_get_product_variation_thumbnails':
global $woocommerce;
$images = woo_get_product_variation_thumbnails(
$vars["product_id"],
$vars["attr"],
$vars["attr_value"],
$vars["size"]
);
$context = Timber::context();
$context["post"] = wc_get_product($vars["product_id"]);
$context["type"] = $context["post"]->get_type();
$context["images"] = $images;
$template = $vars["template"] . ".twig";
$response["html"] = Timber::compile($template, $context);
echo json_encode($response);
die();
break;
}
