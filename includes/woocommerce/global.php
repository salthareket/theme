<?php

//remove woocommerce default css
//add_filter( 'woocommerce_enqueue_styles', '__return_false' );

function woo_dequeue_select2() {

        wp_dequeue_style( 'select2' );
        wp_deregister_style( 'select2' );

        wp_dequeue_script( 'selectWoo');
        wp_deregister_script('selectWoo');

}
//add_action( 'wp_enqueue_scripts', 'woo_dequeue_select2', 100 );


//remove woocommerce default css
/*function dequeue_woo_styles( $enqueue_styles ) {
	unset( $enqueue_styles['woocommerce-general'] );	// Remove the gloss
	unset( $enqueue_styles['woocommerce-layout'] );		// Remove the layout
	unset( $enqueue_styles['woocommerce-smallscreen'] );	// Remove the smallscreen optimisation
	unset( $enqueue_styles['woocommerce-inline-inline-css'] );
	return $enqueue_styles;
}*/


add_action("wp_enqueue_scripts---", function(){

	global $post;
	if(isset($post->post_type)){
    	if($post->post_type != "product"){
           wp_dequeue_style('woo-variation-swatches');
    	}
    }
    
	wp_dequeue_style('woocommerce-smallscreen');
    wp_dequeue_style('woocommerce-inline');

    wp_dequeue_style('woocommerce-layout');
    wp_dequeue_style('woocommerce-general');

    wp_dequeue_style('ywdpd_owl');
    wp_dequeue_style('yith_ywdpd_frontend');

});















//change quantity settings
add_filter( 'woocommerce_quantity_input_args', 'bloomer_woocommerce_quantity_changes', 10, 2 );
function bloomer_woocommerce_quantity_changes( $args, $product ) {
	$user = wp_get_current_user();
	$role = empty($user->roles)?"loggedout":$user->roles[0];
	$role_based_quantity =  get_field('role_based_quantity', 'option');
	$has_quantity_settings = false;
	if($role_based_quantity){
		foreach($role_based_quantity as $role_base){
			if(in_array($role, array_values($role_base["role"]))){
				if($role_base["quantity_min"]>0){
		            $quantity_min = $role_base["quantity_min"];
		        }
		        if($role_base["quantity_max"]>0){
		            $quantity_max = $role_base["quantity_max"];
		        }
		        if($role_base["quantity_step"]>0){
		            $quantity_step = $role_base["quantity_step"];
			    }
			    $has_quantity_settings = true;
			    break;
			}
		}
	}
	if($has_quantity_settings){
		$stock_quantity = $product->get_stock_quantity();
		
	    if(!isset($quantity_min) || $quantity_min > $stock_quantity){
	       $quantity_min = 1;
	    }
	    if(!isset($quantity_max) || $quantity_max > $stock_quantity){
	       $quantity_max = $stock_quantity;
	    }
	    if(!isset($quantity_step) || $quantity_step > $stock_quantity){
	       $quantity_step = 1;
	    }

		if ( ! is_cart() ) {
		      /*$args['input_value'] = $quantity_min;//4; // Start from this value (default = 1) 
		      $args['max_value'] = $stock_quantity; // Max quantity (default = -1)
		      $args['min_value'] = $quantity_min;//4; // Min quantity (default = 0)
		      $args['step'] = $quantity_step;//2; // Increment/decrement by this value (default = 1)*/
		       // $args['input_value'] = 9;	// Starting value
	         $args['max_value'] = $quantity_max; 	// Maximum value
	         $args['min_value'] = $quantity_min; 
	         $args['step'] 	 = $quantity_step;//$quantity_step;// $quantity_step ;   // Quantity steps
		} else {
		      // Cart's "min_value" is already 0 and we don't need "input_value"
		      $args['max_value'] = $quantity_max; // Max quantity (default = -1)
		      $args['step'] =  $quantity_step;//$quantity_step;//2; // Increment/decrement by this value (default = 0)
		      // ONLY ADD FOLLOWING IF STEP < MIN_VALUE
		      $args['min_value'] =  $quantity_min;//$quantity_min;//4; // Min quantity (default = 0)
	    }

	}
    return $args;
}

function wc_get_product_by_variation_sku($sku) {
    $args = array(
        'post_type'  => array('product','product_variation'),
        'order' => 'ASC',
		'orderby' => 'title',
		'posts_per_page' => 10,
		'numberposts' => 10,
        'meta_query' => array(
            array(
                'key'   => '_sku',
                'value' => $sku,
            )
        )
    );
    // Get the posts for the sku
    $posts = get_posts( $args);
    if ($posts) {
        return $posts[0]->post_parent;
    } else {
        return false;
    }
}

function wc_get_products_by_variation_sku($sku) {
    $args = array(
        'post_type'  => array('product','product_variation'),
        'order' => 'ASC',
		'orderby' => 'title',
		'posts_per_page' => 10,
		'numberposts' => 10,
        'meta_query' => array(
            array(
                'key'   => '_sku',
                'value' => $sku,
            )
        )
    );
    // Get the posts for the sku
    $posts = get_posts( $args);
    if ($posts) {
        return $posts;//[0]->post_parent;
    } else {
        return false;
    }
}

function sku_where( $where ) {
							global $wpdb, $wp;
							$search_ids = array();
						    $terms = explode(',', $GLOBALS["keyword"] );
						    foreach ($terms as $term) {
						        //Include search by id if admin area.
						        if (is_admin() && is_numeric($term)) {
						            $search_ids[] = $term;
						        }
						        // search variations with a matching sku and return the parent.
						        $sku_to_parent_id = $wpdb->get_col($wpdb->prepare("SELECT p.post_parent as post_id FROM {$wpdb->posts} as p join {$wpdb->postmeta} pm on p.ID = pm.post_id and pm.meta_key='_sku' and pm.meta_value LIKE '%%%s%%' where p.post_parent <> 0 group by p.post_parent", wc_clean($term)));
						        //Search a regular product that matches the sku.
						        $sku_to_id = $wpdb->get_col($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_sku' AND meta_value LIKE '%%%s%%';", wc_clean($term)));
						        $search_ids = array_merge($search_ids, $sku_to_id, $sku_to_parent_id);
						    }
						    $search_ids = array_filter(array_map('absint', $search_ids));
						    if (sizeof($search_ids) > 0) {
						        $where = str_replace(')))', ") OR ({$wpdb->posts}.ID IN (" . implode(',', $search_ids) . "))))", $where);
						    }
						    return $where;
}

function variation_archive_hide_parent($where){
	if(get_option('gmwsvs_hide_parent_product')=='yes' && empty($GLOBALS["keyword"])){
		global $wpdb;
		$where .= " AND  0 = (select count(*) as totalpart from {$wpdb->posts} as oc_posttb where oc_posttb.post_parent = {$wpdb->posts}.ID and oc_posttb.post_type= 'product_variation') ";
	}
	return $where;
}
function variation_archive_woocommerce_product_query ($q) {
		$q->set( 'post_type', array('product','product_variation') );
		$q->set( 'gmwsvsfilter', 'yes' );
		$meta_query = (array) $q->get( 'meta_query' );
		/*$meta_query[] = array(
								'relation' => 'OR',
								array(
											'key' => '_wwsvsc_exclude_product_single',
											'value' => 'yes',
											'compare' => 'NOT EXISTS'
											
										),
								array(
											'key' => '_wwsvsc_exclude_product_single',
											'value' => 'yes',
											
											'compare' => '!=',
										),
							);*/
		$meta_query[] = array(
								'relation' => 'OR',
								array(
											'key' => '_wwsvsc_exclude_product_parent',
											'value' => 'yes',
											'compare' => 'NOT EXISTS'
										),
								array(
											'key' => '_wwsvsc_exclude_product_parent',
											'value' => 'yes',
											'compare' => '!=',
										),
							);
		/*echo '<pre>';
		print_r($meta_query);
		echo '</pre>';*/
		$q->set( 'meta_query', $meta_query );
		
        /*$tax_query = (array) $q->get( 'tax_query' );
        $tax_query[] = array(
            'taxonomy' => 'product_cat',
            'field'    => 'ids',
            'operator' => 'NOT IN',
            'terms'    => array( 25,28 ),
        );
		$q->set( 'tax_query', $tax_query );*/
		return $q;
}

function variation_archive_posts_clauses($clauses, $query) {
		global $wpdb;
		//if(array_key_exists("gmwsvsfilter", $query->query_vars)){
			if($query->query_vars['gmwsvsfilter']=='yes'){
				if(get_option('gmwsvs_hide_parent_product')=='yes'){
					$clauses['where'] .= " AND  0 = (select count(*) as totalpart from {$wpdb->posts} as oc_posttb where oc_posttb.post_parent = {$wpdb->posts}.ID and oc_posttb.post_type= 'product_variation') ";
				}
				$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} as  oc_posttba ON ({$wpdb->posts}.post_parent = oc_posttba.post_id AND oc_posttba.meta_key = '_wwsvsc_exclude_product_single' )";
				$clauses['where'] .= " AND  ( oc_posttba.meta_value IS NULL OR oc_posttba.meta_value!='yes') ";
					/*echo "<pre>";
				print_r($clauses);
				echo "</pre>";*/
				$gmwsvs_exclude_cat = array();
		    	$gmwsvs_exclude_cat = get_option('gmwsvs_exclude_cat');
		    	if(!empty($gmwsvs_exclude_cat)){
		    		$clauses['where'] .= " AND ( ({$wpdb->posts}.post_type='product_variation' AND {$wpdb->posts}.ID NOT IN ( SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN (".implode(",",$gmwsvs_exclude_cat).") )) OR  {$wpdb->posts}.post_type='product') ";
		    	}
			}
		//}
		return $clauses;
}





function set_price_html( $regular_price, $sale_price ) {
	$currency = get_woocommerce_currency();
	$has_discount = false;
	$overstroke = array("","");
	if($sale_price>0 && $sale_price != $regular_price){
       $has_discount = true;
       $overstroke = array('<del aria-hidden="true">','</del>');
	}
    $html = '<div class="price">';
        if($regular_price>0){
        	$html .= $overstroke[0].'<span class="woocommerce-Price-amount amount"><bdi>'.woo_get_currency_with_price($regular_price, $currency).'</bdi></span>'.$overstroke[1];
        }
        if($has_discount){
            $html .= '<ins><span class="woocommerce-Price-amount amount"><bdi>'.woo_get_currency_with_price($sale_price, $currency).'</bdi></span></ins>';
        }
    $html .= '</div>';
    return $html;
}

function variable_product_price($product){
	if(is_int($product)){
	   $product = wc_get_product($product);	
	}
	$regular_price =$product->get_variation_regular_price("min");
    $sale_price = $product->get_variation_sale_price("min");
    return set_price_html( $regular_price, $sale_price );
}

add_filter( 'woocommerce_get_price_html', 'price_html_filter', 100, 2 );
function price_html_filter( $price, $product ){
	switch($product->get_type()){
		case "bundle" :
             return bundled_product_price($product);
		     break;
        case "variable" :
             return variable_product_price($product);
             break;
		default :
		     return $price;
		     break;
	}
}