<?php

function wcas_set_post_types($args){
	$args["post_type"] = ["product", "product_variation"];
	return $args;
}
add_filter( 'dgwt/wcas/search_query/args', "wcas_set_post_types" );

/*
function wcas_modify_vars($vars, $productID, $product){
	$vars["name"] = "poojhj";
	return $vars;
}
add_filter( 'dgwt/wcas/suggestion_details/product/vars', "wcas_modify_vars", 10, 3 ); 
*/
function wcas_modify_output($output){
	$output["html"] = "test";
	return $output;
}
add_filter( 'dgwt/wcas/suggestion_details/output', "wcas_modify_output");
