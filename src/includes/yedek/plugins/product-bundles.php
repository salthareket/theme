<?php

function bundle_product_price($price, $product) {
    global $post;
    if(isset($post->ID)){
	    $post_id = $post->ID;
	    $product_type = $product->get_type();
	    if($product_type == "bundle"){
	        $price = 0;
	        $bundled_items = $product->get_bundled_items();
	        foreach($bundled_items as $bundled_item){
	           $optional = $bundled_item->item_data["optional"];
	           if($optional == "no"){
		       	   $quantity = $bundled_item->item_data["quantity_default"];
		       	   $bundled_item_price = $bundled_item->product->get_price();
		       	   $price += ($quantity * $bundled_item_price);           	
		       	}
	        }
	    }    	
    }
    return $price;
}
add_filter('woocommerce_product_get_price', 'bundle_product_price', 10, 2);



function bundled_product_price($product, $info=0){
    $regular_price = 0;
    $sale_price = 0;
    if(is_int($product) || is_numeric($product)){
       $product = wc_get_product($product);    
    }
    if($product->get_type() == "bundle"){
        $items = $product->get_bundled_items();
        foreach($items as $item){
            $bundle = $item->get_product();
            $bundle_data = $item->get_data();
            if($bundle_data["optional"] == "no"){
                $quantity = $bundle_data["quantity_default"];
                if(empty($quantity)){
                  $quantity = 0;
                }
                $regular_price_value = floatval($bundle->get_regular_price());
                $sale_price_value = floatval($bundle->get_sale_price());

                $price = $regular_price_value * $quantity;

                if($sale_price_value != $price && $sale_price_value > 0){
                    $price_discounted = $sale_price_value * $quantity;
                }else{
                    $price_discounted = 0;
                }
                $regular_price += $price;
                if($price_discounted == 0){
                   $sale_price += $regular_price;
                }else{
                   $sale_price += $price_discounted;
                }            
            }
        }
        if($info){
            return array( "regular_price" => $regular_price, "sale_price" => $sale_price );
        }else{
            return set_price_html( $regular_price, $sale_price );
        }
    }
}

function get_bundled_product_ids($bundle_id){
	global $wpdb;
	$table = $wpdb->prefix . 'woocommerce_bundled_items';
	$results = $wpdb->get_results($wpdb->prepare(
		"SELECT product_id FROM {$table} WHERE bundle_id = %d ORDER BY menu_order ASC",
		$bundle_id
	));
	return $results ? wp_list_pluck($results, 'product_id') : [];
}

function get_bundle_product_id($product_id){
	global $wpdb;
	$table = $wpdb->prefix . 'woocommerce_bundled_items';
	return $wpdb->get_var($wpdb->prepare("SELECT bundle_id FROM {$table} WHERE product_id = %d", $product_id));
}







function update_bundled_products_price(){
	remove_action( 'pre_get_posts', 'query_all_posts' );
	$args = array(
	   'post_type' => 'product',
	   'numberposts' => -1,
	   'tax_query' => array(
	   	   //"relation" => "and",
	        array(
	            'taxonomy' => 'product_type',
	            'field'    => 'slug',
	            'terms'    => array('bundle'), 
	        )
	    )
	);
	$products = get_posts($args);
	foreach($products as $key=>$product){
		update_bundled_product_price($product->ID);
	}
	add_action( 'pre_get_posts', 'query_all_posts' );
}
//add_action( 'init', 'update_bundled_product_price' );

function update_bundled_product_price($product_id){
	    remove_action( 'pre_get_posts', 'query_all_posts' );
        $prices = bundled_product_price($product_id, 1);
        if(!empty($prices["sale_price"]) && $prices["sale_price"] > 0){
            update_post_meta( $product_id, '_sale_price', $prices["sale_price"] );
            update_post_meta( $product_id, '_price', $prices["sale_price"] );
            update_post_meta( $product_id, '_wc_pb_base_sale_price', $prices["sale_price"] );
            update_post_meta( $product_id, '_wc_pb_base_price', $prices["sale_price"] );
            update_post_meta( $product_id, '_wc_sw_max_price', $prices["sale_price"] );
            update_post_meta( $product_id, '_wc_pb_base_regular_price', $prices["regular_price"] );
            update_post_meta( $product_id, '_wc_sw_max_regular_price', $prices["regular_price"] );
        }else{
            update_post_meta( $product_id, '_price', $prices["regular_price"] );
            update_post_meta( $product_id, '_regular_price', $prices["regular_price"] );
            update_post_meta( $product_id, '_wc_pb_base_regular_price', $prices["regular_price"] );
            update_post_meta( $product_id, '_wc_sw_max_regular_price', $prices["regular_price"] );
            update_post_meta( $product_id, '_wc_pb_base_price', $prices["regular_price"] );
            update_post_meta( $product_id, '_wc_sw_max_price', $prices["regular_price"] );
        }
        add_action( 'pre_get_posts', 'query_all_posts' ); 
}
