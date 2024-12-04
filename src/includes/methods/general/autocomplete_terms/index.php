<?php
$error = false;
            $response = [];
            $response["results"] = [];
            if (isset($vars["type"])) {
                if(isset( $vars["keyword"])){
                   $keyword = $vars["keyword"];
                }
                if(!isset($keyword)){
                    $keyword = $_POST["keyword"];
                }
                if($vars["type"] == "user"){
                    $user = true;
                    $taxonomy = false;
                    $post_type = false;
                }else{
                    $user = false;
                    $taxonomy = taxonomy_exists($vars["type"]);
                    $post_type = post_type_exists($vars["type"]);                    
                }
                if (!isset($vars["response_type"])) {
                    $vars["response_type"] = "select2";
                }
                if (!isset($vars["count"])) {
                    $vars["count"] = 10;
                }
                if (!isset($vars["page"])) {
                    $vars["page"] = 1;
                }
                $offset = ($vars["page"] - 1) * $vars["count"];
                if ($taxonomy) {
                    $args = [
                        "taxonomy" => $vars["type"],
                        "hide_empty" => false,
                        "number" => $vars["count"],
                        "offset" => $offset,
                        "fields" => "id=>name",
                    ];
                    if (isset($vars["value"])) {
                        $args["include"] = $vars["value"];
                    }
                    if (isset($vars["selected"])) {
                        $args["exclude"] = $vars["selected"];
                    }
                    if (!empty($keyword)) {
                        $args["search"] = $keyword;
                        $total_terms = wp_count_terms($args);
                    } else {
                        $total_terms = wp_count_terms($vars["type"]);
                    }
                    $total_pages = ceil($total_terms / $vars["page"]);
                    $terms = get_terms($args);
                }
                if ($post_type) {
                    $args = [
                        "post_type" => $vars["type"],
                        "posts_per_page" => $vars["count"],
                        "offset" => $offset,
                        "fields" => "id=>title",
                    ];
                    if (!empty($keyword)) {
                        $args["s"] = $keyword;
                        $total_terms = wp_count_posts_by_query($args);
                    } else {
                        $total_terms = wp_count_posts($vars["type"])->publish;
                    }
                    $total_pages = ceil($total_terms / $vars["page"]);
                    $terms = Timber::get_posts($args)->to_array();
                }
                if($user){
                    $search_string = esc_attr( trim( $keyword ) );
                    $parts = explode( ' ', $search_string );

                    $args = array(
                        //'search'         => "*{$search_string}*",
                        /* 'search_columns' => array(
                           'user_login',
                            'user_nicename',
                            'user_email',
                            'user_url',
                        ),*/
                    );
                    if( ! empty( $parts ) ){
                        $args['meta_query'] = [];
                        $args['meta_query']['relation'] = 'OR';
                        foreach( $parts as $part ){
                            $args['meta_query'][] = array(
                                'key'     => 'first_name',
                                'value'   => $part,
                                'compare' => 'LIKE'
                            );
                            $args['meta_query'][] = array(
                                'key'     => 'last_name',
                                'value'   => $part,
                                'compare' => 'LIKE'
                            );
                        }
                    }
                    $users = new WP_User_Query( $args );
                    //print_r($users);
                    $terms = $users->get_results();
                    //print_r($terms);
                }
                switch ($vars["response_type"]) {
                    case "select2":
                        if ($taxonomy) {
                            foreach ($terms as $key => $term) {
                                $response["results"][] = [
                                    "id" => $key,
                                    "text" => $term
                                ];
                            }
                        }
                        if ($post_type) {
                            foreach ($terms as $key => $term) {
                                $text = $term->post_title;
                                if(!empty($vars["response_extra"])){
                                    $extras = explode(",", $vars["response_extra"]);
                                    foreach($extras as $extra){
                                        $extra = Trim($extra);
                                        switch($extra){
                                            case "author" :
                                               $text .= " - ".$term->author->display_name;
                                            break;
                                            default:
                                               $text .= " - ".$term->{$extra};
                                            break;
                                        }
                                    }
                                }
                                $response["results"][] = [
                                    "id" => $term->ID,
                                    "text" => $text
                                ];
                            }
                        }
                        if ($user) {
                            foreach ($terms as $key => $term) {
                                $response["results"][] = [
                                    "id" => $term->ID,
                                    "text" => $term->first_name." ".$term->last_name,
                                ];
                            }
                        }
                        if ($vars["page"] < $total_pages && $terms) {
                            $response["pagination"]["more"] = true;
                        } else {
                            $response["pagination"]["more"] = false;
                        }
                        break;
                    case "autocomplete":
                        if ($taxonomy) {
                            foreach ($terms as $key => $term) {
                                $response["results"][$key] =  $term;
                            }
                        }
                        if ($post_type) {
                            foreach ($terms as $key => $term) {
                                $response["results"][$term->ID] = $term->post_title;
                            }
                        }
                        if ($user) {
                            foreach ($terms as $key => $term) {
                                $response["results"][$term->ID] = $term->first_name." ".$term->last_name;
                            }
                        }
                        break;
                }
                $data = $response;
            } else {
                $error = true;
                $message = "Please provide a type";
            }
            $output = [
                "error" => $error,
                "message" => $message,
                "data" => $data,
                "html" => $html,
                "redirect" => $redirect_url,
            ];
            if($vars["response_type"] == "autocomplete"){
                $output = $output["data"]["results"];
            }
            echo json_encode($output);
            die();