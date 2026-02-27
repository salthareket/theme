<?php
$required_setting = ENABLE_FAVORITES;

global $wp_query;
$favorites = new Favorites();
$favorites = $favorites->favorites;
if (!$template) {
	$template = "product/dropdown/archive.twig";
}
$templates = [$template . ".twig"];
$context = Timber::context();
$context["type"] = "favorites";
$user = Data::get("user");

$posts = [];
if ($favorites) {
	$args = [
		"post_type" => "product",
		"posts_per_page" => -1,
		"post__in" => $favorites,
                    /*'meta_query' => array(
						    	'relation' => "or",
						        array(
						            'key' => '_stock_status',
						            'value' => 'instock'
						        ),
						        array(
						            'key' => '_stock_status',
						            'value' => 'outofstock'
						        ),
						    )*/
    ];
    $posts = Timber::get_posts($args);
}
if (
	count($posts) != count($favorites) &&
	isset($user->ID)
) {
	$ids = wp_list_pluck($posts, "ID");
	update_user_meta(
		$user->ID,
		"wpcf_favorites",
		unicode_decode(json_encode($ids, JSON_NUMERIC_CHECK))
	);
}
$post_count = count($posts);
$context["posts"] = $posts;