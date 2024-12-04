<?php 

Class Paginate{

	public $query;
	public $post_type;
	public $taxonomy;
	public $terms;
	public $parent;
	public $role;
	public $page;
	public $posts_per_page;
	public $posts_per_page_default;
	public $orderby;
	public $order;
	public $vars;
	public $filters;
	public $loader;
	public $load_type;
	public $has_thumbnail;

    function __construct($query="", $vars=array()) {

    	//print_r($vars);
    	//print_r($query);

    	$this->query = $query;
    	if(isset($vars)){
	    	if(isset($vars["orderby"])){
	    		$this->orderby = $vars["orderby"];
	    	}
	    	if(isset($vars["order"])){
	    		$this->order = $vars["order"];
	    	}
	    	$this->posts_per_page = -1;
	    	if(isset($vars["posts_per_page"])){
	    		$this->posts_per_page = $vars["posts_per_page"];
	    	}
	    	if(isset($vars["posts_per_page_default"])){
	    		$this->posts_per_page_default = $vars["posts_per_page_default"];
	    	}
	    	if(isset($vars["page"])){
	    		$this->page = $vars["page"];
	    	}
	    	if(isset($vars["post_type"])){
	    		$this->post_type = $vars["post_type"];
	    	}
	    	if(isset($vars["taxonomy"])){
	    		$this->taxonomy = $vars["taxonomy"];
	    	}
	    	if(isset($vars["terms"])){
	    		$terms = json_validate_custom(stripslashes($vars["terms"]));
	    		if($terms){
	    			$this->terms = $terms;
	    		}else{
	    			$this->terms = array($vars["terms"]);
	    		}
	    		if($this->terms && $this->terms[0] == 0){
	    			$this->terms = get_terms(array(
					    'taxonomy'   => $this->taxonomy,
					    'hide_empty' => false,
					    'fields'     => 'ids'
					));
	    		}
	    	}
	    	if(isset($vars["parent"])){
	    		$this->parent = $vars["parent"];
	    		if(empty($this->parent)){
	    			$this->parent = 0;
	    		}
	    	}
	    	if(isset($vars["roles"])){
	    		$this->roles = $vars["roles"];
	    	}
	    	if(isset($vars["filters"])){
	    		$filters = str_replace("\\", "", $vars["filters"]);
                $filters = json_decode($filters, true);
	    		$this->filters = $filters;
	    	}
	    	if(isset($vars["loader"])){
	    		$this->loader = $vars["loader"];
	    	}
	    	if(isset($vars["load_type"])){
	    		$this->load_type = $vars["load_type"];
	    	}
	    	if(isset($vars["has_thumbnail"])){
	    		$this->has_thumbnail = $vars["has_thumbnail"];
	    	}
    	}
    	if(isset($this->posts_per_page)){
    		if(!isset($this->page)){
	    	   $page = 0;
			   $page = isset( $_GET['cpage'] ) ? abs( (int) $_GET['cpage'] ) : abs( (int) $this->page);    	
			   $page = $page<1 ? 1 : $page;
			   $this->page = $page;
			}
    	}else{
    		$this->posts_per_page = -1;
    	}
    	if(isset($this->page)){
    		$this->page = $this->page==0?1:$this->page;
    	}else{
    		$this->page = 1;
    	}
    }

    function get_totals(){
    	global $wpdb;
    	$query = $this->query;
        if(strpos($query, " * ")){
    	   $query = str_replace(" * ", " count(*) as count ", $query);
    	}
    	//print_r("SELECT combined_table.count FROM (${query}) AS combined_table");
        $total = $wpdb->get_var( "SELECT combined_table.count FROM ({$query}) AS combined_table" );
        if($this->posts_per_page > 0){
        	$page_total = ceil($total / $this->posts_per_page);
        }
        $result = array(
        	"total" => $total,
        	"page" => 1,
        	"page_total" => 1,
        	"loader" => $this->loader
        );
        if($this->posts_per_page > 0){
        	$result["page"] = $this->page;
        	$result["page_total"] = $page_total;
        }
        return $result;
    }

    function get_results($type="post"){

    	$query = $this->query;

    	//print_r($query);
        if(empty($query)){
	    	if($type == "post"){
	    		if(empty($query)){
		    	    $query = array(
		    	   	    "post_type" => $this->post_type,
		    	    );    			
	    		}else{
	    			if(is_array($query)){
		    			if(!isset($query["post_type"])){
		    				if(!empty($this->post_type)){
		    					$query["post_type"] = $this->post_type;
		    				}else{
		                        $query["post_type"] = "post";
		    				}
		    			}
		    			if(isset($query["include"])){
		                   $query["post__in"] = $query["include"];
		                   unset($query["include"]);
		    			}
		    		}
	    		}

	            if($this->has_thumbnail){
		    	    /*$query['meta_query'][] = array(
				            'key' => '_thumbnail_id',
				            'compare' => 'EXISTS', // _thumbnail_id anahtarının varlığını kontrol eder
				    );*/
				    $query['meta_query'][] = array(
				            'key' => '_thumbnail_id',
				            "value" => "",
				            'compare' => '!='
				    );
		    	}

	    	   if($this->parent > 0){
	    	   	    $term = get_term( $this->parent );
					if(isset($term->taxonomy)){
		    	   	    $query['tax_query'] = array(
						    array(
						      'taxonomy' => $term->taxonomy,
						      'field' => 'term_id', 
						      'terms' => $this->parent,
						      'include_children' => false
						    )
						);
		    	   	}
	    	    }else{
	    	    	if(!empty($this->taxonomy) && !empty($this->terms)){
	    	    		$query['tax_query'] = array(
						    array(
						      'taxonomy' => $this->taxonomy,
						      'field' => 'term_id', 
						      'terms' => $this->terms,
						      'include_children' => false
						    )
						);
	    	    	}
	    	    }
	    	}
	    	if(empty($query) && $type == "taxonomy"){
	    		$query = array(
	    	   	   "taxonomy" => $this->taxonomy,
	    	   	   "parent"   => $this->parent
	    	    );
	    	}
	    	if(empty($query) && $type == "user"){
	    		if(isset($this->roles)){
		    		$query = array(
		    	   	   "role" => $this->roles,
		    	    );    			
	    		}
	    	}

	    	if(isset($this->filters)){

	    		// keyword filter
	    		if(isset($this->filters["keyword"]) && $type == "user"){
	    			$query_keyword = array(
			    		/*'search'         => '*'.esc_attr( $this->filters["keyword"] ).'*',
					    'search_columns' => array(
					        'user_login',
					        'user_nicename',
					        'user_email',
					        'user_url',
					        'first_name',
					        'last_name'
					    ),*/ 
					    'meta_query' => array(
					        'relation' => 'OR',
					        array(
					            'key'     => 'first_name',
					            'value'   => $this->filters["keyword"],
					            'compare' => 'LIKE'
					        ),
					        array(
					            'key'     => 'last_name',
					            'value'   => $this->filters["keyword"],
					            'compare' => 'LIKE'
					        )
					    )   				
	    			);
	    			$query = array_merge($query, $query_keyword);
				}

	    		// taxonomy filters
	    		if(isset($this->filters["taxonomies"])){
	    		   $tax_queries = array();
	    		   foreach($this->filters["taxonomies"] as $key => $terms){
	    		   	   $tax_queries[] = array(
		                    "taxonomy" => $key,
		                    "field"    => "id",
		                    "terms" => $terms
		                );
	    		   }
	    		   $query["tax_query"] = array(
		    			"relation" => "AND",
		                $tax_queries
			        );
	    		}

	    		if(isset($this->filters["country"])){
	    		    $country_query = array(
	                    'key'           => 'billing_country',
	                    'value'         => $this->filters["country"],
	                    'compare'       => '='
	                );
	    			if(isset($query["meta_query"])){
	                $query["meta_query"][] = $country_query;
	    			}else{
	    			    $query["meta_query"] = array(
		    				"relation" => "AND",
		                     $country_query
		             );
			      }
	         }

	         if(isset($this->filters["city"])){
	    		$city_query = array(
	                    //'key'           => 'billing_city',
	    		      	'key'           => 'city',
	                    'value'         => $this->filters["city"],
	                    'compare'       => '='
	            );
	    		if(isset($query["meta_query"])){
	                $query["meta_query"][] = $city_query;
	    		}else{
	    			$query["meta_query"] = array(
		    			"relation" => "AND",
		                $city_query
		            );
			    }
	         }
	    	}        	
        }


        //$this->query = $query;
    	

    	if(is_array($this->query)){

    	    if($this->posts_per_page > 0){
    	       $query[$type=="post"?"posts_per_page":"number"] = $this->posts_per_page;
    	       if(empty($this->post_type) && $this->taxonomy){
    	       	  $query["offset"] = ( $this->page * $this->posts_per_page ) - $this->posts_per_page;//( $this->page-1 ) * $this->posts_per_page;
    	       }
    	    }else{
    	       if($this->posts_per_page < 0){
    	          $query[$type=="post"?"posts_per_page":"number"] = $this->posts_per_page;
    	          if(empty($this->post_type) && $this->taxonomy){
    	          	  unset($query["number"]);
    	          }
    	       }
    	    }
    	    if(isset($this->page)){
    	       $query["paged"] = $this->page;
    	    }

    	    if(isset($this->order)){
    	       $query["order"] = $this->order;
    	    }
    	    if(isset($this->orderby)){
    	       $query["orderby"] = $this->orderby;
    	    }

    	    if($this->load_type == "count" || $this->load_type == "all"){
    	    	$query["suppress_filters"] = true;
    	    	$query["posts_per_page"] = $this->posts_per_page;
    	    	unset($query["paged"]);
    	    	//unset($query["posts_per_page"]);
    	    }
    
    	    if(!$this->posts_per_page_default || $this->posts_per_page_default == "false"){
    	    	$query["suppress_filters"] = true;
    	    	$query["posts_per_page"] = $this->posts_per_page;
    	    }

            
    	    print_r($query);


    	    $posts = array();
    	    $total = 0;
    	    $page_total = -1;
    	    switch($type){
    	       case "post" :
               		$result = Timber::get_posts($query);//new Timber\PostQuery($query, $class);
               		$total = $result->found_posts;
               		$posts = $result->to_array();//$result->get_posts();;
               		if(isset($result->max_num_pages)){
               			$page_total = $result->max_num_pages;
               		}
               break;
    	       case "user" :
    	   	   		$result = new WP_User_Query($query);
    	   	   		$total = $result->get_total();
    	   	   		$posts = $result->get_results();
    	   	   		$posts = Timber::get_users($posts);
    	   	   break;
    	   	   case "comment" :
               		$result = new WP_Comment_Query($query);
               		$total = $result->found_comments;
               		$posts = $result->comments;
               		if(isset($result->max_num_pages)){
               			$page_total = $result->max_num_pages;
               		}
               		$posts = Timber::get_comments($posts);
    	   	   break;
    	   	   case "taxonomy" :
               		$result = Timber::get_terms($query);//, $class);
               		//print_r($query);
               		$total = wp_count_terms($query);//$result->found_posts;
               		$posts = $result ;//$result->get_posts();
               		//$page_total = $total/$this->posts_per_page; 
               		//if(isset($result->max_num_pages)){
               			//$page_total = $result->max_num_pages;
               		//}
               break;
    	    }
    	    //echo($this->posts_per_page." - ".$page_total." - ".$total);
    	    if($this->posts_per_page > 0 && $page_total < 0 && $total <= 0){
	    	    $page_total = 1;
	    	}else{
	    		$page_total = ceil($total / $this->posts_per_page);
	    	}
            return array(
           	    "posts" => $posts,
                "data"  => array(
			        "total" => $total,
			        "page" => $this->page,
			        "page_total" => $page_total,
			        "loader" => $this->loader
			    )
            );

        }else{

        	// manuel sql query
        	global $wpdb;
	        if($this->posts_per_page > 0){
	        	$posts_per_page = $this->posts_per_page;
		        $offset = ( $this->page * $this->posts_per_page ) - $this->posts_per_page;
		        if(isset($this->orderby) && isset($this->order)){
		           $orderby = $this->orderby;
	    	       $order = $this->order;
                   $query .= " ORDER BY {$orderby} {$order} LIMIT {$offset}, {$posts_per_page}";
		        }else{
                   $query .= " LIMIT {$offset}, {$posts_per_page}";
		        }
		    }else{
		    	if(isset($this->orderby) && isset($this->order)){
		    		$orderby = $this->orderby;
	    	        $order = $this->order;
		    	    $query . " ORDER BY {$orderby} {$order}";
		        }
		    }
		    $results = $wpdb->get_results( $query );
	        return array(
           	    "posts" => $results,
           	    "data"  => $this->get_totals()
            );

        }
    }
}