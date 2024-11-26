<?php

function ywdpd_discount_post_type_args( $args, $post_type ) {
    if ( 'ywdpd_discount' === $post_type ) {
    	global $wp_post_types, $wp_rewrite;
    	//print_r($args);
    	$args['labels'] = Array ( 
    		   "name" => "Kampanyalar",//Discounts & Price Rules
    		   "singular_name" => "Kampanya" //Discounts & Price Rule
        );
    	$args['public'] = true;
        $args['has_archive'] = true;
        $args['query_var'] = true;
        $args['rewrite'] =  array('slug' => 'kampanyalar','with_front' => true);
        add_post_type_support('ywdpd_discount',array('editor', 'post-thumbnails'));
    }
    return $args;
}
add_filter( 'register_post_type_args', 'ywdpd_discount_post_type_args', 10, 2 );

function yith_get_discounts(){
	global $wpdb;
	$obj = new YITH_WC_Dynamic_Pricing();
    $discounts = $obj->get_pricing_rules();
    $discounts_list = array();
    foreach($discounts as $key => $discount){
    	$key_parts = explode("_", $key);
        $key_part = "0";
        if(count($key_parts) > 1){
           $key_part = $key_parts[1];
        }
    	if($key_part == "0"){
    	    $key_code = $key_parts[0];
	    	$post_id = $wpdb->get_var( $wpdb->prepare("SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = '_key' and meta_value = %s", $key_code ) );
	    	$post = Timber::get_post($post_id);
	    	$discount = $discounts[$key];
	    	$discounts_list[] = $post;
    	}
    }
    wp_reset_postdata();
    return $discounts_list;/**/
}

function has_discounts($post){
	$obj = new YITH_WC_Dynamic_Pricing();
	return $obj->check_discount($post);
}

function yith_get_product_discounts($product){
    $post_discounts = array();
    $discounts = yith_get_discounts();
    if($discounts){
        foreach($discounts as $discount){
            $has_discount = false;
            $mode = $discount->_discount_mode;
            switch($mode){

                case "discount_whole":
                   $has_discount = true;
                break;

                case "category_discount":
                    $categories = wc_get_product_terms( $product->get_id(), 'product_cat', array('orderby' => "id", 'order' => "DESC", 'fields' => 'ids') );
                    foreach($discount->_quantity_category_discount as $item){
                        if(in_array($item["product_cat"], $categories)){
                            $has_discount = true;
                        }
                    }
                break;

            }
            if($has_discount){
                $post_discounts[] = array(
                    "title" => $discount->title,
                    "link"  => $discount->link,
                    "image" => $discount->image_slider
                );
            }
        }
    }/**/
    wp_reset_postdata();
    return $post_discounts;
}