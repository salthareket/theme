<?php

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
            "url" => "#store-".$post->ID,//get_permalink($post->ID),
            "image" => $image,
        ];
        $output[] = $post_item;
    }
}
echo json_encode($output);
die();
