<?php 

Class Paginate{

	public $query;
	public $query_type;
	public $type;
	public $post_type;
	public $taxonomy;
	public $terms;
	public $parent;
	public $role;
	public $page;
	public $paged;
	public $posts_per_page;
	public $number;
	public $max_posts;
	public $posts_per_page_default;
	public $orderby;
	public $order;
	public $vars;
	public $filters;
	public $loader;
	public $load_type;
	public $has_thumbnail;

    function __construct($query="", $vars=array()) {

    	$this->query = !empty($query)?$query:(isset($vars["query"])?$vars["query"]:"");
        
        if(!empty($this->query)){
        	if(is_array($this->query)){
        		$this->query_type = "wp";
        	}else{
        		if(is_numeric($this->query)){
        			$this->query_type = "id";
        		}else{
	        		if (strpos($this->query, ' ') !== false) {
	        			$this->query_type = "sql";
	        		}else{
	                    $this->query_type = "encrypted";
	        		}        			
        		}
        	}
        }

		//print_r($vars);


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
	    		$this->paged = true;
	    	}
	    	if(isset($vars["max_posts"])){
	    		$this->max_posts = $vars["max_posts"];
	    	}
	    	if(isset($vars["posts_per_page_default"])){
	    		$this->posts_per_page_default = $vars["posts_per_page_default"];
	    	}
	    	if(isset($vars["page"])){
	    		$this->page =  (int) $vars["page"];
	    	}
	    	if(isset($vars["paged"])){
	    		$this->paged = $vars["paged"];
	    	}
	    	if(isset($vars["type"])){
	    		$this->type = $vars["type"];
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
    	if(isset($this->posts_per_page) && $this->paged){
    		if(!empty($this->max_posts)){
    			if($this->max_posts < $this->posts_per_page){
    				$this->posts_per_page = $this->max_posts;
    			}
    		}
    		if(!isset($this->page)){
	    	   $page = 0;
			   $page = isset( $_GET['cpage'] ) ? abs( (int) $_GET['cpage'] ) : abs( (int) $this->page);    	
			   $page = $page<1 ? 1 : $page;
			   $this->page = $page;
			}
    	}

    	if(!empty(get_query_var('paged'))){
    		$this->page = get_query_var('paged');
    	}else{
    		$this->page = $this->page==0?1:$this->page;
    	}

    }

    function get_totals($count=0){
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
        	"count" => $count,
        	"count_total" => $total,
        	"page" => 1,
        	"page_total" => 1,
        	"loader" => $this->loader
        );
        if($this->posts_per_page > 0){
        	$result["count"] = $count;
        	$result["page"] = $this->page;
        	$result["page_total"] = $page_total;
        }
        return $result;
    }

    function get_results($type="post"){

    	if($this->query_type == "id"){
    		global $wpdb;
    	    $option_name = $wpdb->get_var( $wpdb->prepare(
			    "SELECT option_name FROM {$wpdb->options} WHERE option_id = %d",
			    $this->query
			));
            $this->query = get_option($option_name);
    	}
    	if($this->query_type == "encrypted"){
    		$enc = new Encrypt();
            $this->query = $enc->decrypt($this->query);
    	}
    	$query = $this->query;

    	if(is_array($this->query)){

    		

    		$post_count_query = $this->type=="post"?"posts_per_page":"number";

	        //if($value["slider"]){
	        //   $query[$post_count_query] = empty($value["max_posts"])?-1:$value["max_posts"];
	        //}else{

	            if($this->paged){
	                $query[$post_count_query] = $this->posts_per_page;
	                if(!empty($this->max_posts) && is_numeric($this->max_posts)){
	                    $max_posts = $this->max_posts < $this->posts_per_page?$this->posts_per_page:$this->max_posts;
	                    if($max_posts > 0){
	                    	$query[$post_count_query] = min($query[$post_count_query], $max_posts - ( $this->page - 1 ) * $query[$post_count_query]);
	                    }
	                    
	                    //$query['no_found_rows'] = true;
	                    $query["paged"] = $this->page;//max(1, get_query_var('paged'));
	         
	                }else{
	                	if(!empty($this->page)){
			    	       $query["paged"] = $this->page;
			    	    }

	                }           
	            }else{
	                $query[$post_count_query] = $this->posts_per_page;//empty($this->max_posts)?-1:$this->max_posts;
	            }


	            if($type == "taxonomy" || $type == "user"){
	            	if(isset($query["paged"])){
	            		if($query["paged"] < 0){
	            			$query["paged"] = 0;
	            		}
	            		$query["offset"] = ($this->page - 1) * $query["number"];
	            		unset($query["paged"]);
	            	}
	            }
	        //}
            if(ENABLE_MULTILANGUAGE == "polylang"){
            	$query["lang"] = pll_current_language();
            }

    	    if($type == "comment" && $query["number"] < 1){
    	    	unset($query["number"]);
    	    	$query["no_found_rows"] = true;
    	    }


            /* 
            echo "<br><b>paginate</b><br>";
    	    print_r($query);
    	    echo "<hr>";*/


    	    $posts = array();
    	    $total = 0;
    	    switch($type){
    	       case "post" :
    	            $query  = QueryCache::get_cached_query($query);
               		$result = Timber::get_posts($query);//new Timber\PostQuery($query, $class);
               		$total  = $result->found_posts;
               		$posts  = $result;//->to_array();//$result->get_posts();
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
               		if($query["no_found_rows"]){
		    	    	$total = count($posts);
		    	    	$count = $total;
		    	    }else{
		    	    	$count = count($posts);
		    	    }
    	   	   break;
    	   	   case "taxonomy" :
               		$result = Timber::get_terms($query);//, $class);
               		//print_r($result->found_posts);
               		if(isset($query["offset"])){
               		   unset($query["offset"]);
               		}
               		if(isset($query["number"])){
               		   unset($query["number"]);
               		}
               		$total = wp_count_terms($query);//$result->found_posts;
               		$posts = $result ;//$result->get_posts();
               		//$page_total = $total/$this->posts_per_page; 
               		//if(isset($result->max_num_pages)){
               			//$page_total = $result->max_num_pages;
               		//}
               break;
    	    }
    	    
    	    $count_total = $total;
    	    $page_total = -1;
    	    $page_count_total = 1;

    	    if(!empty($this->max_posts) && $count_total > 0){
    	    	$count = $count_total > $this->max_posts ? $this->max_posts : $count_total; //max_post varsa sınırlandı
    	    }else{
    	    	$count = $count_total;
    	    }
            
            if($this->paged){
	    	    if($this->posts_per_page > 0 && $page_total < 0 && $count_total <= 0){
		    	    $page_total = 1;
		    	}else if(!empty($this->posts_per_page)){
		    		$page_total = ceil($count_total / $this->posts_per_page);
		    	}            	
            }else{
            	$page_total = 1;
            }

	    	$page_count_total = $page_total;

	    	if($this->paged && !empty($this->max_posts) && $count_total > 0){
	    		 $total_pages = ceil($count_total / $this->posts_per_page);
                 $max_pages = ceil($this->max_posts / $this->posts_per_page);
                 $page_count_total =  min($total_pages, $max_pages);
             }

            return array(
           	    "posts" => $posts,
                "data"  => array(
			        "count" => (int) $count,
			        "count_total" => (int) $count_total,
			        "page" => (int) $this->page,
			        "page_total" => (int) $page_total,
			        "page_count_total" => (int) $page_count_total,
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
           	    "data"  => $this->get_totals(count($results))
            );

        }
    }
}