<?php
    
    $query_pagination_vars = array();
    $query_pagination_request = "";
    if(!empty($vars["query_pagination_vars"]) || !empty($vars["query_pagination_request"])){
        $enc = new Encrypt();
        if(!empty($vars["query_pagination_vars"])){
             $query_pagination_vars = $enc->decrypt($vars["query_pagination_vars"]);
        }
        if(!empty($vars["query_pagination_request"])){
             $query_pagination_request = $enc->decrypt($vars["query_pagination_request"]);
        }
    }
    $args = $query_pagination_vars;// $_SESSION['query_pagination_vars'][$vars["post_type"]];

    if(isset($vars['posts_per_page'])){
        $args["posts_per_page"] = $vars['posts_per_page'];
    }

    //if(isset($_SESSION['query_pagination_request'][$vars["post_type"]])){
    if(!empty($query_pagination_request)){

        //$request = $_SESSION['query_pagination_request'][$vars["post_type"]];
        $request = $query_pagination_request;
        $request = explode("LIMIT", $request)[0];
        $request .= " LIMIT ".($args['posts_per_page'] * ($vars['page']-1)).", ".$args['posts_per_page'];

        //echo "<div class='col-12 alert alert-success'>".$request."</div>";

        global $wpdb;
        $results = $wpdb->get_results($request);
        if ($results) {
            $results = wp_list_pluck($results, "ID");
            $post_args = array(
                "post_type" => $args["post_type"]=="search"?"any":$args["post_type"],
                "post__in" => $results,
                "posts_per_page" => -1,
                //"order" => "ASC",
                "orderby" => "post__in",
                'suppress_filters' => true
            );
        }
    }else{
        $post_args = $args;
        $post_args['paged'] = $vars['page'];
        $post_args['post_type'] = $args["post_type"]=="search"?"any":$args["post_type"];
        //$post_args['page'] = $vars['page'];
    }
    $GLOBALS["pagination_page"] = $vars['page'];

    unset($post_args["querystring"]);
    unset($post_args["page"]);
    if(isset($post_args["s"])){
        if(empty($post_args["s"])){
            unset($post_args["s"]);
        }
    }



    //echo "<div class='col-12 alert alert-success'>".json_encode($post_args)."</div>";

    $html = "";

    $query = QueryCache::get_cached_query($post_args);

    $folder = $post_args["post_type"];
    if($args["post_type"] == "any" || is_array($args["post_type"])){
        $folder = "search";
    }
    if($post_args["post_type"] == "any" || is_array($post_args["post_type"])){
        $folder = "search";
    }

    if($args["post_type"] == "product"){
        if ($query->have_posts()) :
            while ($query->have_posts()) : $query->the_post();
                ob_start(); // Çıktıyı tampona al
                wc_get_template_part('content', 'product');
                $html .= ob_get_clean(); // Tampondaki çıktıyı $am değişkenine ekle
                $GLOBALS["pagination_page"] = "";
            endwhile;
        endif;        
    }else{
        if ($query->have_posts()){

            $index = ($vars['page'] ) * $args['posts_per_page']; // Mevcut sayfa için ofset hesaplama

            $query = Timber::get_posts($query);

            //foreach($query->posts as $post){
            foreach($query as $post){
                ob_start();
                $context = Timber::context();
                $index++;
                $context['index'] = $index;
                $context['post'] = $post;//Timber::get_post($post);
                Timber::render([$folder."/tease.twig", "tease.twig"], $context);
                $html .= ob_get_clean();
                $context = null;
                $GLOBALS["pagination_page"] = "";
            }
        }
    }

    wp_reset_query();

    $data = $response;
    $data["html"] = minify_html($html);

    $total = (int) $vars["total"];
    $per_page = (int) $args["posts_per_page"];
    $current = (int) $vars["page"];
    $initial = (int) $vars["initial"];

    if (1 === $total) {
        $data["data"] = _e('Showing the single result', 'woocommerce');
    } elseif ($total <= $per_page || -1 === $per_page) {
        /* translators: %d: total results */
        $data["data"] = sprintf(_n('Showing all %d result', 'Showing all %d results', $total, 'woocommerce'), $total)." - ".$total." - ".$per_page;
    } else {
        $first = ($per_page * $current) - $per_page + 1;
        $last  = min($total, $per_page * $current);
        if(!empty($initial) && $initial > 0){
            if($current < $initial){
                $last = min($total, $per_page * $initial);
            }else{
                $first = ($per_page * $initial) - $per_page + 1;   
            }
        }
        /* translators: 1: first result 2: last result 3: total results */
        $data["data"] = sprintf(_nx('Showing %1$d&ndash;%2$d of %3$d result', 'Showing %1$d&ndash;%2$d of %3$d results', $total, 'with first and last result', 'woocommerce'), $first, $last, $total);

    }
    echo json_encode($data);
    wp_die();