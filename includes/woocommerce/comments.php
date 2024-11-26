<?php

/*
add_action('init','yoursite_init',11); //11=lower priority
function yoursite_init() {
  remove_action('template_redirect','redirect_canonical');
  add_action('template_redirect','yoursite_redirect_canonical');
}

add_action('template_redirect','yoursite_redirect_canonical');
function yoursite_redirect_canonical($requested_url=null, $do_redirect=true) {
  $cpage = get_query_var('cpage');
  set_query_var('cpage',false);
  print_r($requested_url);
  print_r($do_redirect);
  redirect_canonical($requested_url, current_url());
  set_query_var('cpage',$cpage);
}
*/

/*
add_filter('posts_where', function ($where, $query) {
    //$label = $query->query['query_label'] ?? '';
    //if($label === 'our_cat_massage_query') {

    if(isset($query->query['review_keyword']) ){//&& $query->query['comment_type'] == 'review'){
        //print_r($query);
        //echo $query->query['review_keyword'];
        if(!empty($query->query['review_keyword__'])){
            global $wpdb;
            $where .= " comment_content LIKE '%".$query->query['review_keyword']."%'";
        }        
    }

    return $where;
}, 10, 2);
*/

function TimberComment($comment_id){
	$comment = new Timber\Comment(intval($comment_id));
	return $comment;
}

function average_rating() {
    global $wpdb;
    $post_id = get_the_ID();
    $ratings = $wpdb->get_results("
        SELECT $wpdb->commentmeta.meta_value
        FROM $wpdb->commentmeta
        INNER JOIN $wpdb->comments on $wpdb->comments.comment_id=$wpdb->commentmeta.comment_id and $wpdb->comments.meta_key='comment_profile' and  $wpdb->comments.meta_value=36
        WHERE $wpdb->commentmeta.meta_key='rating' 
        AND $wpdb->comments.comment_post_id=$post_id 
        AND $wpdb->comments.comment_approved=1");
    $counter = 0;
    $average_rating = 0;    
    if ($ratings) {
        foreach ($ratings as $rating) {
            $average_rating = $average_rating + $rating->meta_value;
            $counter++;
        } 
        //round the average to the nearast 1/2 point
        return (round(($average_rating/$counter)*2,0)/2);  
    } else {
        //no ratings
        return 'no rating';
    }
}


function getCommentRating($vars=array()){
    //print_r($vars);
    /*{
        type : agent,
        id : user_id,

        type :customer
        id : user_id

        type : destinations,
        id : array(destinations),

        orderby : latest, oldest, highest, lowest,
        order : ASC,
        number : 2,
    }*/
    if(!isset($vars['type']) || empty($vars["type"])){
        $vars["type"] = "agent";
    }
    if(!isset($vars['sort']) || empty($vars["sort"])){
        $vars["sort"] = "latest";
    }
    if(!isset($vars['number']) || empty($vars["number"])){
        if($vars["number"] != 0){
           $vars["number"] = -1;            
        }
    }
    if(!isset($vars['perpage']) || empty($vars["perpage"])){
        //$vars["perpage"] = 6;
    }
    $defaults = array(
        'status'       => 'approve',
        'post_status'  => 'publish',
        'post_type'    => 'product',
        'comment_type' => 'review',
    );
    switch($vars["type"]){
        case "agent" :
            $defaults["meta_query"] = array(
                'relation' => 'AND',
                array(
                    'key'   => 'comment_profile',
                    'value' => $vars["id"],
                    'compare' => '='
                )
            );
        break;

        case "destinations" :
            $destinations = array();
            $defaults["meta_query"] = array(
                'relation' => 'OR'
            );
            if(isset($vars["tax"])){
                if($vars["tax"]->destination_type == "continent"){
                    $destinations = get_terms(array(
                            'taxonomy' => 'destinations',
                            'fields'   => 'ids',
                            'child_of' => $vars["tax"]->id,
                            'meta_key' => 'destination_type',
                            'meta_value' => 'country'
                    ));
                }else{
                    $destinations = array($vars["tax"]->id, $vars["tax"]->parent); 
                }
            }else{
                if(isset($vars["id"])){
                    if(!is_array($vars["id"])){
                        //$destination = get_term_by("term_id", $vars["id"], 'destinations');
                       $children = get_terms(array(
                            'taxonomy' => 'destinations',
                            'fields'   => 'ids',
                            'child_of' => $vars["id"],
                            'meta_key' => 'destination_type',
                            'meta_value' => 'country'
                        ));
                        if($children){
                           $destinations = $children;  
                        }else{
                           $destinations = array($vars["id"]);  
                        }
                    }else{
                        $destinations = $vars["id"];  
                    }
                }                
            }
            if($destinations){
                foreach($destinations as $destination) {
                    if(!empty($destination)){
                        array_push ($defaults["meta_query"],array(
                            'key'     => 'comment_destination',
                            'value'   => '"'.$destination.'"',
                            'compare' => 'LIKE'
                        ));            
                    }
                }    
            }else{
                unset($defaults["meta_query"]);
            }
        break;

        case "customer" :
           $defaults["status"]  =  array('approve', 'hold');
           $defaults["user_id"] = $vars["id"];
        break;
    }

    //get rating
    $args = $defaults;
    $args["fields"] = "ids";
    $query = new WP_Comment_Query($args);
    $count = count($query->comments);
    $rating = 0;
    if($count > 0 ){
        foreach($query->comments as $comment){
            $rating = $rating + number_format(get_comment_meta($comment, 'rating', true), 2);
        }     
        $rating = $rating/$count;
    }



    //get comments
    $comments = array();
    $pagination = array();
    
    if(isset($vars["number"])){
        if($vars["number"] > 0 || $vars["number"] == -1){

            if(isset($vars["keyword"])){
                if(!empty($vars["keyword"])){
                    $defaults['search'] = $vars["keyword"];
                }
            }

            switch($vars["sort"]){
                case "latest" :
                     $defaults['orderby'] = 'comment_date';
                     $defaults['order'] = "DESC";
                break;

                case "oldest" :
                     $defaults['orderby'] = 'comment_date';
                     $defaults['order'] = "ASC";
                break;

                case "highest" :
                     $defaults['meta_key'] = 'rating';
                     $defaults['orderby'] = 'meta_value_num';
                     $defaults['order'] = "DESC";
                break;

                case "lowest" :
                     $defaults['meta_key'] = 'rating';
                     $defaults['orderby'] = 'meta_value_num';
                     $defaults['order'] = "ASC";
                break;
            }

            if(isset($vars["perpage"])){
                $comments_per_page =  $vars["perpage"]; 
                $comments_per_page = $count < $comments_per_page ? $count : $comments_per_page;
                $number = $vars["number"]; 
                $comments_count = $count;
                $page = 1;
                $page_var = get_query_var('cpage');
                if(!empty($page_var)){
                   $page = filter_var($page_var, FILTER_SANITIZE_NUMBER_INT);
                   if(!is_numeric($page)){
                      $page = 1;
                   }
                }
                $offset = $comments_count - ($comments_per_page * $page);
                if ( $offset < 0 ) {
                    $comments_last_page = $comments_count % $comments_per_page;
                    $offset = $offset + $comments_per_page - $comments_last_page;
                    $number = $comments_last_page; 
                }
                $defaults['number'] = $number;
                $defaults['offset'] = $offset;
            }
            


            $args = $defaults;
            $comments_query = new WP_Comment_Query($args);
            $comments = $comments_query->get_comments();

            //print_r($comments_query);

            //if($comments_query->found_comments > $number){
            if(isset($vars["perpage"])){
                    global $wp_rewrite;
                    $total = 1;
                    if($comments_count>0){
                      $total = round($comments_count/$comments_per_page);
                    }
                    $args = array(
                        'base'         => add_query_arg( 'cpage', '%#%' ),
                        'format'       => '?paged=%#%',
                        'total'        => $total,
                        'current'      => $page,
                        'echo'         => true,
                        'type'         => 'array',
                        'prev_text'    => '&laquo;',
                        'next_text'    => '&raquo;',
                        'add_fragment' => '#reviews',
                    );
                    //print_r($args);
                    if (!$wp_rewrite->using_permalinks() ) {
                        $url = get_permalink();
                        if(empty($url)){
                           $url = current_url();
                        }
                        $args['base'] = user_trailingslashit( trailingslashit( $url ) . $wp_rewrite->comments_pagination_base . '/%#%', 'cpage' );
                    }
                    //$args       = wp_parse_args( $args, $defaults );
                    $pagination = paginate_links( $args );                
            }

        }
    }
    return array(
        "comments"   => $comments,
        //"comments_count" => $comments_query->found_comments,
        "pagination" => $pagination,
        "rating"     => $rating,
        "count"      => $count
    );
}