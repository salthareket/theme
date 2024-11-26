<?php

// Yoast SEO meta tag kontrolü
add_filter('wpseo_frontend_presenters', function($presenters) {
    global $post;
    if (is_null($post)) {
        return array(); // Boş bir dizi döndürerek Yoast SEO işlevselliğini devre dışı bırak
    }
    return $presenters;
});





function yoast_seo_exclude_posts_from_sitemap($exclude) {
    if ( class_exists( 'WooCommerce' ) ) {
        $exclude[] = get_option("woocommerce_order_received_page_id");
        $exclude[] = get_option("woocommerce_cart_page_id");//get_page_by_path( 'cart' )->ID;
        $exclude[] = get_option("woocommerce_checkout_page_id");//get_page_by_path( 'checkout' )->ID;
    }
    if(class_exists('Newsletter')){
        $exclude[] = get_page_by_path( 'newsletter' )->ID;      
    }
    if(isset($GLOBALS["sitemap_exclude_post_ids"])){
    	$exclude = array_merge($exclude, $GLOBALS["sitemap_exclude_post_ids"] );
    }
	return $exclude;
}
add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', 'yoast_seo_exclude_posts_from_sitemap', 10, 1 );


function yoast_seo_exclude_terms_from_sitemap($exclude) {
	if(isset($GLOBALS["sitemap_exclude_term_ids"])){
    	$exclude = array_merge($exclude, $GLOBALS["sitemap_exclude_term_ids"] );
    }
    return $exclude;
}
add_filter( 'wpseo_exclude_from_sitemap_by_term_ids', 'yoast_seo_exclude_terms_from_sitemap', 10, 1 );




function yoast_seo_remove_columns( $columns ) {
	/* remove the Yoast SEO columns */
	unset( $columns['wpseo-score'] );
	unset( $columns['wpseo-title'] );
	unset( $columns['wpseo-metadesc'] );
	unset( $columns['wpseo-focuskw'] );
	unset( $columns['wpseo-score-readability'] );
	unset( $columns['wpseo-links'] );
	return $columns;
}
/* remove from posts */
add_filter ( 'manage_edit-post_columns', 'yoast_seo_remove_columns' );
/* remove from pages */
add_filter ( 'manage_edit-page_columns', 'yoast_seo_remove_columns' );
/* remove from woocommerce product post type */
add_filter ( 'manage_edit-product_columns', 'yoast_seo_remove_columns' );


function wpse151723_remove_yoast_seo_posts_filter() {
    global $wpseo_metabox, $wpseo_meta_columns;
    if ( $wpseo_metabox ) {
        remove_action( 'restrict_manage_posts', array( $wpseo_metabox, 'posts_filter_dropdown' ) );
    }
    if ( $wpseo_meta_columns ) {
        remove_action( 'restrict_manage_posts', array( $wpseo_meta_columns , 'posts_filter_dropdown' ) );
        remove_action( 'restrict_manage_posts', array( $wpseo_meta_columns , 'posts_filter_dropdown_readability' ) );
    }    
}
add_action( 'admin_init', 'wpse151723_remove_yoast_seo_posts_filter', 20 );

function fix_yoast_breadcrumb_taxes($links){
	if (array_key_exists('id', $links[count($links)-1])) {
		   $post_id = $links[count($links)-1]['id'];
		   if(count($links)-2>=0){		   
		   if (array_key_exists('term', $links[count($links)-2])) {
		   	  array_pop($links);
		      $taxonomy = $links[count($links)-1]['term']->taxonomy;
		   	  array_pop($links);
			  $args = array(
						    'orderby'           => 'name', 
						    'order'             => 'ASC',
						    'hide_empty'        => false, 
						    'fields'            => 'all',
						    'hierarchical'      => true    
			  );
			  $terms = wp_get_post_terms( $post_id, $taxonomy, $args);
			  $terms_updated=array();
			  $terms = sort_terms_hierarchicaly( $terms, $terms_updated, 0);
			  $terms_updated=array();
			  $terms = sort_terms_hierarchicaly_single($terms, $terms_updated);
			  foreach($terms as $term){
			  	$links[]['term']=$term;
			  }
			  $links[]['id']=$post_id;
		    }
		}
	}
	return $links;
}

/*remove "home page" from breadcrumb*/
function remove_home_from_breadcrumb($links){
	if ($links[0]['url'] == get_site_url()."/") { 
		array_shift($links); 
	}
	return $links;
}

/*fix & add taxonomy hierarch breadcrumb*/
function fix_tax_hierarchy_on_breadcrumb($links){
    return fix_yoast_breadcrumb_taxes($links);
}

/*remove current page/post from breadcrumb*/
function remove_current_from_breadcrumb($links){
	$last_item = array();
	if(count($links)>1){
		$last_item = $links[count($links)-1];
	}else if(count($links) == 1){
		$last_item = $links[0];
	}
	if($last_item){
		if(isset($last_item["taxonomy"]) && is_tax( $last_item["taxonomy"] )){
           array_pop($links);
		}
		if(isset($last_item["id"]) && count($links) > 2 ){
           array_pop($links);
           $last_item = $links[count($links)-2];
			if(isset($last_item["taxonomy"]) && is_tax( $last_item["taxonomy"] )){
	           array_pop($links);
			}
		}
		global $post;
		if(isset($last_item["id"]) && $post->ID ){
           array_pop($links);
		}
		if(isset($last_item["ptarchive"])){
		   array_pop($links);
		}			
	}
	return $links;
}

function add_parents_to_post_breadcrumb( $links ) {
    if ( !class_exists( 'WooCommerce' ) ) {
    	global $post;
		if ( is_product_category() || is_singular("product")) {
				    	$nodes = get_leafnode_object("product", array(), array(), 0);
	                    if(count($nodes)>0){
	                    	$nodes_list = array();
					        foreach($nodes as $key=>$node){
					        	if($key>0){
						        	$breadcrumb = array(
						        	    'url' => $node->url,
						                'text' => $node->title,
						        	);
						        	array_push($nodes_list,$breadcrumb);
					           }
					        }
					        array_reverse($nodes_list);
					        $links=array_merge($nodes_list, $links);
					        $links=array_unique($links);
					    }
		}
	}
	return $links;
}

//add "brand" to breadcrumb
function add_brand_to_breadcrumb($links){
    $brand_name = get_query_var( 'product_brand' );
    if ( is_product_category() && !is_tax( 'product_brand' ) && !empty($brand_name)) {
                     $brand = array(
                     	'term'=>get_term_by("slug",$brand_name,"product_brand")
                     );
				     $links_temp=array();
				     foreach($links as $link){
				   	    $links_temp[]=$link;
				   	    if(!empty($brand) && array_key_exists('ptarchive',$link) ){
				   	       if($link['ptarchive']=='product'){
				   	         $links_temp[]=$brand;
				   	       }
				   	    }
				     }
				     $links=$links_temp;  
    }
    return $links;
}

/*add single product's category to breadcrumb*/
function add_category_to_breadcrumb($links){	
    if (is_singular('product')) {
					global $post;
					//category
					$product_category = wc_get_product_terms( $post->id, 'product_cat', array( 'fields' => 'all' ) );
					if(is_array($product_category)){
						if(count($product_category)>0){
		                     $product_category=$product_category[0];
		                 }
					}
                    //brand
                    $product_brand = wc_get_product_terms( $post->id, 'product_brand', array( 'fields' => 'all' ) );
                    if(count($product_brand)>0){
                      $product_brand=$product_brand[0];
					}
				    $links_temp=array();
				    foreach($links as $link){ 
				   	    $links_temp[]=$link;
				   	    if(count($product_category)>0 && array_key_exists('term',$link) ){
				   	    	if(array_key_exists('taxonomy',$link['term'])){
				   	    		if($link['term']->taxonomy=='product_brand'){
					   	           $links_temp[]=array(
                                          "text"=>$product_category->name,
                                          "url"=>get_term_link( $product_category->term_id, 'product_cat' )."?product_brand=".$product_brand->slug
					   	           	);   
					   	        }
				   	    	}  
				   	    }
				     }
				     $links=$links_temp;
    }
    return $links;
}

function change_shopping_link_on_breadcrumb($links){
	$term_index = count($links)-1;
	if(isset($links[$term_index]["term_id"])){
		$term = get_term_by('ID', $links[$term_index]["term_id"], "magaza-tipleri");
		if(isset($term->taxonomy)){
			if($term->taxonomy == "magaza-tipleri"){
			   $page = get_page_by_path( $term->slug );
	           $links[$term_index]["url"] = get_permalink($page->ID);
			}
		}
	}
	return $links;
}

function fix_translate_on_breadcrumb($links){
	if(function_exists('qtranxf_getSortedLanguages')){
		foreach($links as $key => $link){
			if(isset($link["url"])){
				$text = $link["text"];
				if(isset($link["ptarchive"])){
					$post_type_object = get_post_type_object($link["ptarchive"]); // Post type objesini al
					if ($post_type_object) {
						$text_url = get_post_type_archive_link($link["ptarchive"]); // Arşiv URL'sini al
						$text_translated = $post_type_object->labels->singular_name; // Singular label'ini al
					}
				}elseif(isset($link["taxonomy"])){
					$term = get_term($link["term_id"]);
					$text_url = $term->link;
					$text_translated = $term->name;
				}else{
					$text_translated = qtranxf_use( qtranxf_getLanguage(), $link["text"], false, false);
				    $text_url = qtranxf_convertURL( $link["url"], qtranxf_getLanguage());
				    if($text == $text_translated && isset($link["id"])){
			           $text = get_post_field( "post_title", $link["id"]);
			           $text_translated = qtranxf_use( qtranxf_getLanguage(), $text, false, false);
					}
				}
				$links[$key]["text"] = $text_translated;
				$links[$key]["url"] = $text_url;				
			}
		}
	}
	return $links;
}
 


//add_filter( 'wpseo_breadcrumb_links', 'wpse_100012_override_yoast_breadcrumb_trail', 10, 1 );
function wpse_100012_override_yoast_breadcrumb_trail( $links ) {
	global $wp_query;
	 if ( is_archive() || is_category() || isset($wp_query->query_vars['taxonomy'])) {
	 	if((get_query_var("taxonomy") && get_query_var("term_id")) || isset($wp_query->query_vars['taxonomy'])){
	 		if(isset($wp_query->query_vars['taxonomy'])){
                $page = Timber::get_terms($wp_query->query_vars['taxonomy'], array('slug' => $wp_query->query_vars["term"]));
	 		}else{
	 			$page = Timber::get_terms(get_query_var("taxonomy"), array('term_id' => get_query_var( 'term_id' )));
	 		}
		    $breadcrumb[] = array(
		        'url' => get_term_link($page[0]->term_id),
		        'text' => $page[0]->name,
		        'term_id' => $page[0]->term_id,
		        'taxonomy' => $page[0]->taxonomy
		    );
		    array_splice( $links, 1, -2, $breadcrumb );	 		
	 	}
	}
    return $links;
}



function rel_next_prev(){
    global $paged;
    if ( get_previous_posts_link() ) { ?>
        <link rel="prev" href="<?php echo get_pagenum_link( $paged - 1 ); ?>" /><?php
    }
    if ( get_next_posts_link() ) { ?>
        <link rel="next" href="<?php echo get_pagenum_link( $paged +1 ); ?>" /><?php
    }
}
add_action( 'wp_head', 'rel_next_prev' );




function add_taxonomy_menu_path_breadcrumb( $links ) {
		global $wp_query;
	    $query_vars = $wp_query->query_vars;
	    if(array_key_exists("taxonomy", $query_vars)){
			$taxonomy = $query_vars['taxonomy'];
			if(in_array($taxonomy, $GLOBALS["breadcrumb_taxonomy"])){
		        $term = $query_vars['term'];
		        $term_obj = get_term_by("slug", $term, $taxonomy);
		        $nodes = get_leafnode_object(array("object"=>$taxonomy, "object_id" => $term_obj->term_id));
			    if($nodes){
			        $nodes_list = array();
					foreach($nodes as $key=>$node){
						if($key>0){
							$breadcrumb = array(
								'url' => $node->url,
								'text' => $node->title,
						    );
						    array_push($nodes_list, $breadcrumb);
					    }
					}
					if($nodes_list){
						$nodes_list = array_reverse($nodes_list);
						$links=array_merge($nodes_list, $links);				        	
					}
				}
			}
		}
		return $links;
}
if(isset($GLOBALS["breadcrumb_taxonomy"])){
	if(!is_array($GLOBALS["breadcrumb_taxonomy"])){
          //return;
	}
	if(count($GLOBALS["breadcrumb_taxonomy"]) == 0){
         // return;
	}
   add_filter( 'wpseo_breadcrumb_links', 'add_taxonomy_menu_path_breadcrumb', 10, 1 );	
}

function add_post_type_menu_path_breadcrumb( $links ) {
		global $wp_query;
	    $query_vars = $wp_query->query_vars;
	    $post_type = "";
	    if(array_key_exists("post_type", $query_vars)){
		   $post_type = $query_vars['post_type'];
		}
		if(in_array($post_type, $GLOBALS["breadcrumb_post_type"])){
            $nodes = get_leafnode_object($post_type);
			if($nodes){
			    $nodes_list = array();
				foreach($nodes as $key=>$node){
					if($key>0){
						$breadcrumb = array(
							'url' => $node->url,
							'text' => $node->title,
						);
						array_push($nodes_list, $breadcrumb);
					}
				}
				if($nodes_list){
					$nodes_list = array_reverse($nodes_list);
					$links = array_merge($nodes_list, $links);				        	
				}
			}
		}
		return $links;
	}
if(isset($GLOBALS["breadcrumb_post_type"])){
    add_filter( 'wpseo_breadcrumb_links', 'add_post_type_menu_path_breadcrumb', 10, 1 );	
}



add_filter( 'wpseo_next_rel_link', 'custom_change_wpseo_next' );
add_filter( 'wpseo_prev_rel_link', 'custom_change_wpseo_prev' );
function custom_change_wpseo_prev( $link ) {
	return $link;
}
function custom_change_wpseo_next( $link ) {
	return $link;
}




function custom_breadcrumb_links($links) {
    $menu_name = 'header';
    if(ENABLE_MULTILANGUAGE == "polylang"){
    	$menu_name .= "_".$GLOBALS["language"];
    }
    $menu = wp_get_nav_menu_object($menu_name);
    if($menu){
    	$current_url = "";
    	$breadcrumb_items = [];
        $menu_items = wp_get_nav_menu_items($menu->term_id);
        $singular = false;

        if( is_singular( 'product' ) ) {
			foreach ($menu_items as $item) {
			    if (get_post_meta($item->ID, 'object_type', true) === 'product') {
			        $singular = $item;
			        break;
			    }
			}
			if($singular){
	        	$breadcrumb_items[] = [
	                'url' => $singular->url,
	                'text' => $singular->title
	            ];
	            $current_url = $singular->url;				
			}
        }

        if(empty($current_url)){
			$current_url = (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        }

        foreach ($menu_items as $menu_item) {
		    $item_url = $menu_item->url;
		    if (trailingslashit($item_url) == trailingslashit($current_url)) {
		    	if(!$singular){
			        $breadcrumb_items[] = [
			            'url' => $menu_item->url,
			            'text' => $menu_item->title
			        ];		    		
		    	}
		        while ($menu_item->menu_item_parent) {
		            $parent_menu_items = array_filter($menu_items, function($item) use ($menu_item) {
		                return $item->ID == $menu_item->menu_item_parent;
		            });
		            if ($parent_menu_item = reset($parent_menu_items)) { // İlk öğeyi al
		                array_unshift($breadcrumb_items, [
		                    'url' => $parent_menu_item->url,
		                    'text' => $parent_menu_item->title
		                ]);
		                $menu_item = $parent_menu_item;
		            } else {
		                break;
		            }
		        }
		        break;
		    }
		}
        array_unshift($breadcrumb_items, [
            'url' => home_url(),
            'text' => 'Home'
        ]);
        $links = $breadcrumb_items;    	
    }
    return $links;
}
add_filter('wpseo_breadcrumb_links', 'custom_breadcrumb_links');