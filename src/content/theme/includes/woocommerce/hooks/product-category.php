<?php

function category_queries_vars($term, $vars){
	if(isset($vars) && !empty($vars)){
	  if($GLOBALS["ajax"]){
	  	if(array_key_exists($term, $vars)){
	  	   return $vars[$term];
	    }
	  }else{
		  foreach($vars as $var){
		    if($var["slug"] == $term){
	    	  	 return implode(",", $var["terms"]);
	    	  }
		  }	  	
	  }
	}else{
       return get_query_var($term);
	}
}
function category_queries_ajax($query=array(), $vars=array()){

	            if(empty($vars)){
	               //$vars =  woo_sidebar_filter_vars();
	            }

                // Create Query
	            $query['post_type']      = array('product','product_variation');
	            $query['posts_per_page'] = $GLOBALS["site_config"]["pagination_count"];
			    $query['numberposts']    = $GLOBALS["site_config"]["pagination_count"];
			    $query['order']          = "DESC";
			    $query['orderby']        = "publish_date";

			    //$query['gmwsvsfilter'] = 'yes';

			    $keyword = category_queries_vars('keyword', $vars);
				if(!empty($keyword)){
                   $query['s'] = $keyword;
				}

                $meta_query = array();
                $tax_query = array();

			    //show only instock
			    $meta_query[] = array( 
			        array(
			            'key' => '_stock_status',
			            'value' => 'instock',
			            'compare' => '=',
			        ),
			        array(
			            'key' => '_backorders',
			            'value' => 'no',
			            'compare' => '=',
			        ),
			    );

			    $query['meta_query'] = $meta_query;
			    $query['tax_query']  = $tax_query;

			    $query['paged'] = category_queries_vars('page', $vars);
			    
			    if(!$query['paged']){
			      // $query['is_paged'] = 1;
			    }



                
	            return array(
	            	"query" => $query,
	            	"query_vars" => $vars//$query_vars
	            );
}

if(DISABLE_DEFAULT_CAT){
	// exclude uncategorized category from results
	add_filter( 'woocommerce_product_categories_widget_args', 'custom_woocommerce_product_subcategories_args' );
	add_filter( 'woocommerce_product_subcategories_args', 'custom_woocommerce_product_subcategories_args' );
	function custom_woocommerce_product_subcategories_args( $args ) {
	    $default_cat = get_option( 'default_product_cat' );
	   
	    if ( function_exists( 'pll_get_term' ) && $default_cat ) {
	        $default_cat = pll_get_term( $default_cat );
	    }
	    if ( $default_cat ) {
	        $args['exclude'] = $default_cat;
	    }
	    return $args;
	}

	add_action( 'woocommerce_product_query', 'ts_custom_pre_get_posts_query' );
	function ts_custom_pre_get_posts_query( $q ) {
	    $default_cat = get_option( 'default_product_cat' );

	    // Dil bazlı kategori ID'si al
	    if ( function_exists( 'pll_get_term' ) && $default_cat ) {
	        $default_cat = pll_get_term( $default_cat );
	    }

	    if ( $default_cat ) {
	        $tax_query = (array) $q->get( 'tax_query' );
	        $tax_query[] = array(
	            'taxonomy' => 'product_cat',
	            'field'    => 'term_id',
	            'terms'    => $default_cat,
	            'operator' => 'NOT IN',
	        );
	        $q->set( 'tax_query', $tax_query );
	    }
	}	
}

