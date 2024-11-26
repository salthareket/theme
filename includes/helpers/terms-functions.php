<?php


function get_term_url($term_id) {
        return get_term_link((int)$term_id);
}

function get_taxonomy_first_post($post){
	    $id=$post->object_id;
	    $taxonomy=$post->object;
        $terms = Timber::get_terms($taxonomy, array(
		    'taxonomy' => $taxonomy,
		    'hide_empty' => false,
		    'parent' => $id
		));
		if(!$terms){
           $terms = Timber::get_term($id,$taxonomy);
		}else{
		   $terms=$terms[0];
		}
		return get_terms_first_post($terms);
}

function get_terms_first_post($term){
	$count = 0;
	if(isset($term->children)){
	   $count = count($term->children);
	}
	if($count>0){
       return get_terms_first_post($term->children[0]);
	}else{
        $args = array(		        
			        'tax_query' => array(
			            array(
			                'taxonomy' => $term->taxonomy,
			                'field' => 'term_id',
			                'terms' => $term->term_id
			            )
			        )
			    );
        return Timber::get_post($args);
	}
}


function get_terms_first_post_image($taxonomy, $term_id, $size="medium"){
	    $image = "";
        $args = array(
                    'posts_per_page' => 1,		        
			        'tax_query' => array(
			            array(
			                'taxonomy' => $taxonomy,
			                'field' => 'term_id',
			                'terms' => $term_id
			            )
			        ),
				    'meta_query' => array(
				        array(
				         'key' => '_thumbnail_id',
				         'compare' => 'EXISTS'
				        ),
				    )
			    );
        $post =  Timber::get_post($args);
        if($post){
           $image =  wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), $size )[0];
        }
        return $image;
}




function strip_tags_content($text, $tags = '', $invert = FALSE) {
	  preg_match_all('/<(.+?)[\s]*\/?[\s]*>/si', trim($tags), $tags);
	  $tags = array_unique($tags[1]);
	  if(is_array($tags) AND count($tags) > 0) {
	    if($invert == FALSE) {
	      return preg_replace('@<(?!(?:'. implode('|', $tags) .')\b)(\w+)\b.*?>.*?</\1>@si', '', $text);
	    }
	    else {
	      return preg_replace('@<('. implode('|', $tags) .')\b.*?>.*?</\1>@si', '', $text);
	    }
	  }
	  elseif($invert == FALSE) {
	    return preg_replace('@<(\w+)\b.*?>.*?</\1>@si', '', $text);
	  }
	  return $text;
}

/*
function sort_terms_hierarchicaly(Array &$cats = array(), Array &$into = array(), $parentId = 0, $menu_order = 0, $menu_parent = 0){
		foreach ($cats as $i => $cat) {
			if ($cat->parent == $parentId) {
				$into[] = $cat;
				unset($cats[$i]);
			}
		}
		usort($cats, function($a, $b) {return strcmp($a->name, $b->name);});
		foreach ($into as $topCat) {
				   $topCat->children = array();
				   sort_terms_hierarchicaly($cats, $topCat->children, $topCat->term_id);
		}
		if($menu_order>0 && $menu_parent > 0){
			$container=array();
            return sorted_terms_to_menu($into, $menu_order, $menu_parent);
		}else{
			return $into;
		}	
}
*/
function sort_terms_hierarchicaly(Array &$cats, Array &$into = array(), $parentId = 0, $menu_order = 0, $menu_parent = 0) {
    foreach ($cats as $i => $cat) {
        if ($cat->parent == $parentId) {
            $into[] = $cat;
            unset($cats[$i]);
        }
    }
    
    // Parent değeri 0 olan term'leri name özelliğine göre sırala
    usort($into, function($a, $b) {
        return strcmp($a->name, $b->name);
    });

    foreach ($into as $topCat) {
        $topCat->children = array();
        sort_terms_hierarchicaly($cats, $topCat->children, $topCat->term_id);
    }

    if ($menu_order > 0 && $menu_parent > 0) {
        $container = array();
        return sorted_terms_to_menu($into, $menu_order, $menu_parent);
    } else {
        return $into;
    }
}


function sorted_terms_to_menu($terms, $menu_order, $menu_parent){
	   foreach ( $terms as $term ) {
			$menu_order++;
			$term->ID = 2000000 + $menu_order +  $term->term_id;
			$term->menu_item_parent = $menu_parent;
			$term->post_type = 'nav_menu_item';
			$term->menu_order = $menu_order;//$term->term_order;
			$term->title = $term->name;
			$term->object = $term->taxonomy;
			$term->type = "taxonomy";
			$term->db_id = -1*$term->term_id;
			$term->url = get_term_url($term->term_id);
			//$GLOBALS['menu_array'][] = $term;
			if($term->children){
               sorted_terms_to_menu($term->children, $menu_order, $term->term_id);//$term->term_id);
			}	
		}
}


function sort_terms_hierarchicaly_single($arr=array(), $arr_new=array()){
	if($arr){
	    foreach ($arr as $item) {
	     	$childs = $item->children;
	     	unset($item->children);
			$arr_new[] = $item;
			$arr_new = sort_terms_hierarchicaly_single($childs, $arr_new);	   
		}
		return $arr_new;	 	
	}
}

function get_parent_terms($taxonomy, $terms){
		$parent_ids=array();
		foreach($terms as $term) {
			$parent_ids[]=$term->parent;
		}
		return get_terms( $taxonomy, array(
										'include' => array_unique($parent_ids),
									 )
		);
}

function get_post_terms_with_common_tax($post_type, $taxonomy_a, $taxonomy_b, $term_b_id, $orderby="name", $order="ASC"){
	global $wpdb;
	$query = $wpdb->prepare(
	    "SELECT DISTINCT
	        terms.*
	    FROM
	        `wp_terms` terms
	    INNER JOIN
	        `wp_term_taxonomy` tt1 ON
	            tt1.term_id = terms.term_id
	    INNER JOIN
	        `wp_term_relationships` tr1 ON
	            tr1.term_taxonomy_id = tt1.term_taxonomy_id
	    INNER JOIN
	        `wp_posts` p ON
	            p.ID = tr1.object_id
	    INNER JOIN 
	        `wp_term_relationships` tr2 ON
	            tr2.object_ID = p.ID
	    INNER JOIN 
	        `wp_term_taxonomy` tt2 ON
	            tt2.term_taxonomy_id = tr2.term_taxonomy_id
	    WHERE
	        p.post_type = %s AND
	        p.post_status = 'publish' AND
	        tt1.taxonomy = %s AND
	        tt2.taxonomy = %s AND
	        tt2.term_id = %d order by terms.$orderby $order",
	    [
	        $post_type,
	        $taxonomy_a,
	        $taxonomy_b,
	        $term_b_id,
	    ]
	);
	$results = $wpdb->get_results( $query );
	$terms = array();
	if ( $results ) {
	    $terms = array_map( 'get_term', $results );
	    usort($terms, function($a, $b) {
		   return strcasecmp( 
		                $a->name, 
		                $b->name 
		            );
		});
	}
	return $terms;
}

function get_other_posts( $post_id=0, $count=5, $orderby = "date", $order = "DESC" ) {
        $args = array(
            'post_type'      => get_post_type( $post_id ),
            'posts_per_page' => $count,
            'post_status'    => 'publish',
            'post__not_in'   => array( $post_id ),
            'orderby'        => $orderby,
            'order'        => $order
        );
        return Timber::get_posts($args);
}


function get_related_posts( $post_id, $related_count, $args = array() ) {
		$args = wp_parse_args( (array) $args, array(
			'orderby' => 'menu_order',//'rand',
			'return'  => 'query', // Valid values are: 'query' (WP_Query object), 'array' (the arguments array),
			'forced'  => true
		) );

		$related_args = array(
			'post_type'      => get_post_type( $post_id ),
			'posts_per_page' => $related_count,
			'post_status'    => 'publish',
			'post__not_in'   => array( $post_id ),
			'orderby'        => $args['orderby'],
			'tax_query'      => array()
		);

		$post       = get_post( $post_id );
		$taxonomies = get_object_taxonomies( $post, 'names' );

		if($taxonomies){
			foreach ( $taxonomies as $taxonomy ) {
				$terms = get_the_terms( $post_id, $taxonomy );
				if ( empty( $terms ) ) {
					continue;
				}
				$term_list                   = wp_list_pluck( $terms, 'slug' );
				$related_args['tax_query'][] = array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => $term_list
				);
			}

			if ( count( $related_args['tax_query'] ) > 1 ) {
				$related_args['tax_query']['relation'] = 'OR';
			}			
		}

		//print_r($related_args);
		//print_r(Timber::get_posts($related_args)->to_array());



		if ( $args['return'] == 'query' ) {
			return Timber::get_posts($related_args)->to_array();
			/*$query = new WP_Query( $related_args );
			if(isset($query->posts)){
				return $query->posts;
			}*/
		} else {
			return $related_args;
		}
}

function get_term_root( $term="", $taxonomy="" ) {
    $parent  = Timber::get_term( $term, $taxonomy );
    if(!is_wp_error($parent)){
	    // Climb up the hierarchy until we reach a term with parent = '0'
	    while ( $parent->parent != '0' ) {
	        $term_id = $parent->parent;
	        $parent  = Timber::get_term( $term_id, $taxonomy);
	    }
	    return $parent;    	
    }
    return;
}


function wpse342309_search_terms( $query, $taxonomy ) {
    $per_page = absint( $query->get( 'posts_per_page' ) );
    if ( ! $per_page ) {
        $per_page = max( 10, get_option( 'posts_per_page' ) );
    }

    $paged = max( 1, $query->get( 'paged' ) );
    $offset = absint( ( $paged - 1 ) * $per_page );
    $args = [
        'taxonomy'   => $taxonomy,
//      'hide_empty' => '0',
        'search'     => $query->get( 's' ),
        'number'     => $per_page,
        'offset'     => $offset,
    ];

    $query->terms = [];
    $terms = get_terms( $args );
    if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
        $query->terms = $terms;
    }

    $args['offset'] = 0; // always 0
    $args['fields'] = 'count';
    $query->found_terms = get_terms( $args );

    $query->term_count = count( $query->terms );
    $query->terms_per_page = $per_page; // for WP_Query::$max_num_pages
    $query->is_all_terms = ( (int) $per_page === $query->term_count );

    $query->set( 'posts_per_page', max( 1, $per_page - $query->term_count ) );
    $query->set( 'offset', $query->term_count ? 0 :
        max( 0, $offset - $query->found_terms ) );
}



function get_term_hierarchy($taxonomy="", $term_id=0, $array=array()){
		$term = get_term_by("id", $term_id, $taxonomy);
		if($term){
			$array[]=$term->name;
			if($term->parent>0){
				return get_term_hierarchy($taxonomy, $term->parent, $array);
			}else{
				$array = array_reverse($array);
				return $array;
			}			
		}else{
			return $array;
		}
}

function get_category_total_post_count($taxonomy = "category", $term_id = 0){
	$query = new WP_Query( array(
	    'tax_query' => array(
	        array(
	            'taxonomy' => $taxonomy,
	            'field' => 'id',
	            'terms' => $term_id,
	            'include_children' => true,
	        ),
	    ),
	    'nopaging' => true,
	    'fields' => 'ids',
	));
	return $query->post_count;
}

function get_term_slugs_to_ids($slugs=array(), $taxonomy=""){
    global $wpdb;
    $results = array();
    if($slugs && !empty($taxonomy)){
        if(!is_array($slugs)){
           $slugs = [$slugs];
        }
        $slug_where = " and (";
        foreach($slugs as $key => $slug){
            $slug_where .= "t.slug = '$slug'";
            if($key < count($slugs)-1){
               $slug_where .= " or ";
            }
        }
        $slug_where .= ")";
        $query = "SELECT DISTINCT t.term_id as id FROM wp_term_taxonomy tt
                    INNER JOIN wp_terms AS t ON (t.term_id = tt.term_id)
                    WHERE tt.taxonomy = '$taxonomy' $slug_where";
        $results = $wpdb->get_results($query);
        if($results){
           $results = wp_list_pluck($results, "id");
        }
    }
    return $results;
}