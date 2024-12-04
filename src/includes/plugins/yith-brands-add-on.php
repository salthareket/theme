<?php

function rasa_set_brands($offset=0){
	$args = array( 
		'post_type' => 'product',
		//'offset' => $offset,
		'numberposts' => -1,
		'orderby'          => 'date',
        'order'            => 'desc',
        'suppress_filters' => false
	);
	$products = get_posts($args);
	foreach ( $products as $product) {
        rasa_set_brand($product);
   }
}
function rasa_set_brand($product){
	    $taxonomy = "yith_product_brand";
        $title = $product->post_title;
        $term_title = explode(" ",$title)[0];
        $term_slug = sanitize_title($term_title);
        $term_id = term_exists($term_slug, $taxonomy);
        if ( $term_id == 0 || $term_id == null || !$term_id) {
        	$term_id = wp_insert_term( $term_title, $taxonomy, array(
				'description' => '',
				'parent'      => 0,
				'slug'        => $term_slug
			));
			if( ! is_wp_error($term_id) ){
        	   $term_id = $term_id["term_id"];
        	   wp_set_post_terms( $product->ID, $term_id, $taxonomy, false );
			}
        }else{
        	$term_id = $term_id["term_id"];
        	wp_set_post_terms( $product->ID, $term_id, $taxonomy, false );
        }
}
function rasa_set_brands_func(){
	rasa_set_brands(0);
}

// mass update
//add_action( 'init', 'rasa_set_brands_func' );