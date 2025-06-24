<?php

/* remove my account menu */
remove_action(
    'woocommerce_account_navigation',
    'woocommerce_account_navigation'
);



//modify account menu
add_filter ( 'woocommerce_account_menu_items', 'salt_remove_my_account_links' );
function salt_remove_my_account_links( $menu_links ){
    unset( $menu_links['edit-address'] ); // Addresses
    unset( $menu_links['dashboard'] ); // Dashboard
    unset( $menu_links['payment-methods'] ); // Payment Methods
    unset( $menu_links['orders'] ); // Orders
    unset( $menu_links['downloads'] ); // Downloads
    unset( $menu_links['edit-account'] ); // Account details
    unset( $menu_links['customer-logout'] ); // Logout
    return $menu_links;
}




//get My Account page titles
function wpb_woo_endpoint_title( $title, $id ) {
    if ( is_wc_endpoint_url( 'downloads' ) && in_the_loop() ) { // add your endpoint urls
        $title = "Download MP3s"; // change your entry-title
    }
    elseif ( is_wc_endpoint_url( 'orders' ) && in_the_loop() ) {
        $title = "My Orders";
    }
    elseif ( is_wc_endpoint_url( 'edit-account' ) && in_the_loop() ) {
        $title = "Change My Details";
    }
    return $title;
}
add_filter( 'the_title', 'wpb_woo_endpoint_title', 10, 2 );




add_filter("woocommerce_get_query_vars", function ($vars) {
    $endpoints = array_keys(salt_my_account_links());
    if($endpoints){
        foreach ($endpoints as $e) {
            $vars[$e] = $e;
        }        
    }
    return $vars;
});



// Add new endpoint page inside "my account" page
add_filter ( 'woocommerce_account_menu_items', 'salt_my_account_links_woo', 40 );
function salt_my_account_links_woo( $menu_links ){
    $menu_links_tmp = array();
    $links = salt_my_account_links();
    if($links){
        foreach($links as $key => $link){
            if(!empty($link["menu"])){
                $menu_links_tmp[$key] = $link["menu"];
            }
        }
        $menu_links = $menu_links_tmp;   
    }
    return $menu_links;
}

$salt_my_account_links_woo = salt_my_account_links();
if($salt_my_account_links_woo){
    foreach($salt_my_account_links_woo as $key => $link){
        $endpoint = str_replace("-", "_", $key);
        $funcs = [
            "my_account_custom_content_{$endpoint}",
            "my_account_content_{$endpoint}"
        ];
        foreach($funcs as $func){
            if(function_exists($func)){
                add_action('woocommerce_account_'.$key.'_endpoint', $func);
                break;
            }
        }
    }
}











/*
add_action('woocommerce_account_dashboard', 'salt_dashboard_endpoint_content');
function salt_dashboard_endpoint_content(){
    $user_id = $GLOBALS["user"]->id;
    $templates = array("my-account/dashboard.twig");
    $context = Timber::context();
    $context['type'] = "notifications"; 
    $context['title'] = trans("Notifications");
    $context['description'] = trans("Description text here.");
    Timber::render($templates , $context);
}
*/

//dashboard page
//add_action('woocommerce_account_dashboard', 'salt_custom_dashboard');
function salt_custom_dashboard(){

    $user = wp_get_current_user();

    //cart control
    /*$cart_count =  woo_get_cart_count();
    if($cart_count>0){
        echo "<div class='alert alert-warning'>".wp_kses_post( sprintf( _n( 'Sepetinizde bekleyen %1$s adet ürün var!', 'Sepetinizde bekleyen %1$s adet ürün var!', "zitango" ), $cart_count ) )."</div>";
    }*/

    $vertical = false;

    //menu content
    $endpoint_banned = array("dashboard", "customer-logout", "agents");

    if($vertical){
        echo "<div class='account-dashboard-list card-merged'>";    
    }else{
        echo "<div class='row row-margin'>";    
    }
    
    foreach ( wc_get_account_menu_items() as $endpoint => $label ){
        if(!in_array($endpoint, $endpoint_banned)){
            $url = get_account_endpoint_url( $endpoint );

            if(!$vertical){
               echo "<div class='col-12 col-sm-6'>";
            }

            echo "<div class='card-account-dashboard-item card card-module card-module-solid h-100'>";
                 echo "<div class='card-header header-flex'><h3 class='title card-title'><a href='".$url."' class='btn-loading-page'>".$label ."</a></h3>";
                       if($vertical){
                          echo "<div class='action'><a href='".$url."' class='btn-loading-page btn btn-outline-danger btn-extend'>Edit</a></div>";
                       } 
                 echo "</div>";
                 echo "<div class='card-body'>";
                switch($endpoint){

                    /*  completed badge-success
                        processing badge-info
                        on-hold badge-warning
                        pending badge-default
                        cancelled badge-danger
                        refunded badge-danger
                        failed badge-danger
                    */

                    case "orders":
                        $order_statuses_default = wc_get_order_statuses();
                        $order_statuses = array();
                        $class = "";
                        foreach(array_keys($order_statuses_default) as $key=>$order_status){
                            if($order_status == "wc-completed"){
                               $class = "success";
                            }else if($order_status == "wc-processing"){
                               $class = "info";
                            }else if($order_status == "wc-on-hold"){
                               $class = "warning";
                            }else if($order_status == "wc-pending"){
                               $class = "primary";
                            }else if($order_status == "wc-cancelled"){
                               $class = "danger";
                            }else if($order_status == "wc-refunded"){
                               $class = "danger";
                            }else if($order_status == "wc-failed"){
                               $class = "danger";
                            }
                            $order_statuses[] = array(
                                "name"  => $order_status,
                                "count" => 0,
                                "class" => $class
                            );
                        }
                        $customer_orders = get_posts( array(
                            'numberposts' => 5,
                            'meta_key'    => '_customer_user',
                            'meta_value'  => $user->ID,
                            'post_type'   => wc_get_order_types(),
                            'post_status' => array_keys($order_statuses_default ),
                        ) );
                        if(count($customer_orders)>0){
                            foreach($customer_orders as $order){
                                foreach($order_statuses as $key=>$order_status){
                                    if($order->post_status == $order_status["name"]){
                                       $order_statuses[$key]["count"] = $order_statuses[$key]["count"]+1;
                                    }
                                }
                            }
                            echo "<ul class='list-statuses-dashboard list-statuses-active list-group'>";
                            foreach($order_statuses as $order_status){
                              if($order_status["count"]>0){
                                echo "<li class='list-group-item d-flex justify-content-between align-items-center ".($order_status["count"]>0?"active":"")."'>";
                                    echo "<a href='".$url."' class='btn-loading-page'></a>";
                                    echo $order_statuses_default[$order_status["name"]];
                                    echo "<span class='badge badge-".$order_status["class"]." badge-pill'>".$order_status["count"]."</span>";
                                echo "</li>";
                              }
                            }
                            echo "</ul>";
                            //echo "<a href='".$url."' class='btn btn-base btn-base-outline btn-sm btn-extend'>Listeyi Gör</a>";
                        }else{
                            echo "<div class='content-centered'><div class='content-block'>";
                                    echo "<i class='far fa-calendar'></i>You don't buy any tour yet.";
                            echo "</div></div>";  
                        }
                    break;

                    case "my-favorites":
                        $favorites_count = 0;
                        $favorites = json_decode(get_user_meta($user->ID, 'wpcf_favorites',true));
                        if($favorites){
                           $favorites_count = count($favorites);
                        }
                        $plural = "";
                        if($favorites_count>1){
                            $plural = "s";
                        }
                        echo "<a href='".$url."' class='btn-loading-page'><span>View your favorites</span></a>";
                        echo "<div class='content-centered'><div class='content-block'>";
                             if($favorites_count>0){
                                echo "<i class='count'>".$favorites_count."</i>tour".$plural." in your favorite list!";
                             }else{
                                echo "<i class='far fa-heart'></i>Favorite list is empty!";
                             }
                        echo "</div></div>";    
                    break;

                    case "my-reviews":
                        $comments = getCommentRating(array(
                            "type" => "customer",
                            "id"   => get_current_user_id(),
                            "number" => -1
                        ));
                        $plural = "";
                        if($comments["count"] > 1){
                            $plural = "s";
                        }
                        echo "<a href='".$url."' class='btn-loading-page'><span>View your reviews</span></a>";
                        echo "<div class='content-centered'><div class='content-block'>";
                             if($comments["count"] > 0){
                                echo "<i class='count'>".$comments["count"]."</i>review".$plural."!";
                             }else{
                                echo "<i class='fa fa-comment-slash'></i>No reviews!";
                             }
                        echo "</div></div>";    
                    break;

                    case "messages":
                        $messages_count = yobro_unseen_messages_count();
                        $plural = "";
                        if($messages_count>1){
                            $plural = "s";
                        }
                        echo "<a href='".$url."' class='btn-loading-page'><span>Go to inbox</span></a>";
                        echo "<div class='content-centered'><div class='content-block'>";
                             if($messages_count>0){
                                echo "<i class='count'>".$messages_count."</i>new message".$plural."!";
                             }else{
                                echo "<i class='far fa-envelope'></i>No new message!";
                             }
                        echo "</div></div>";    
                    break;

                    /*case "my-trips":
                        $tour_statuses = array("on-hold", "processing", "completed", "cancelled", "failed");
                        $tour_statuses_arr = array();
                        foreach($tour_statuses as $status){
                            $arr = tour_plan_status_view($status);
                            $arr["count"] = 0;
                            $arr["slug"] = $status;
                            $tour_statuses_arr[$status] = $arr;
                        }

                        $args = array(
                            'post_type' => 'tour-plan',
                            //'author' => $user->ID,
                            //'fields' => 'ids',
                            //'no_found_rows' => true,
                            'meta_query' => array(
                                array(
                                    'key'     => 'tour_plan_status',
                                    'value'   => $tour_statuses,
                                    'compare' => 'IN',
                                ),
                            ),
                        );
                        if ( !in_array( 'administrator', (array) $user->roles ) ) {
                           $args["author"] = $user->ID;
                        }
                        $query = new WP_Query( $args );
                        $tour_plan_count = $query->found_posts;
                        $tour_plans      = $query->posts;

                        $active_plan_count = 0;


                        if($tour_plan_count == 0){

                            echo "<div class='content-centered'><div class='content-block'>";
                                echo "<i class='far fa-calendar'></i>No active tours!";
                            echo "</div></div>";  

                        }else{

                            foreach($tour_statuses_arr as $key=>$tour_status){
                                foreach($tour_plans as $tour_plan){
                                    if($tour_plan->tour_plan_status == $key){
                                        $tour_statuses_arr[$key]["count"] = $tour_statuses_arr[$key]["count"]+1;
                                        if($key == "on-hold" || $key == "processing" || $key == "completed"){
                                           $active_plan_count++;
                                        }
                                    }
                                }
                            }

                            //if($active_plan_count > 0){
                            //  $plural = $active_plan_count>1?"s":"";
                            //    echo "<div class='content-centered'><div class='content-block'>";
                            //        echo "<i class='count'>".$active_plan_count."</i>active tour plan".$plural."!.<div class='content-block-container'></div>";
                            //    echo "</div></div>"; 
                            //}

                            echo "<ul class='list-statuses-dashboard list-statuses-active list-group'>";
                            foreach($tour_statuses_arr as $key=>$tour_status){
                                if($tour_status["count"]>0){
                                    echo "<li class='list-group-item d-flex justify-content-between align-items-center ".($tour_status["count"]>0?"active":"")."'>";
                                        if($tour_status["count"]>0){
                                            echo "<a href='".get_account_endpoint_url( $endpoint )."?tour-status=".$key."' class='btn-loading-page'></a>";                                       
                                        }
                                        echo "<span>";
                                            echo  $tour_status["title"];
                                            echo "<small>".$tour_status["description"]."</small>";
                                        echo "</span>";
                                        echo "<span class='badge badge-".$tour_status["class_status"]." badge-pill'>".$tour_status["count"]."</span>";
                                    echo "</li>";
                                }
                            }
                            echo "</ul>";

                        }
                    break;

                    case "requests":
                        $tour_statuses = array("on-hold", "processing","completed","cancelled","failed");
                        $tour_statuses_arr = array();
                        foreach($tour_statuses as $status){
                            $arr = tour_plan_status_view($status);
                            $arr["count"] = 0;
                            $arr["slug"] = $status;
                            $tour_statuses_arr[$status] = $arr;
                        }

                        $field_value = sprintf( '^%1$s$|s:%2$u:"%1$s";', $user->ID, strlen( $user->ID ) );
                        $args = array(
                                 "post_type" => "tour-plan",
                                 "meta_query" => array(
                                    array(
                                        "key" => "tour_plan_agents",
                                        'value'   => $field_value,
                                        'compare' => 'REGEXP'
                                    ),
                                    array(
                                        "key" => "tour_plan_status",
                                        'value'   => $tour_statuses,
                                        'compare' => 'IN'
                                    ) 
                                 )
                        );

                        $query = new WP_Query( $args );
                        $tour_plan_count = $query->found_posts;
                        $tour_plans      = $query->posts;

                        $active_plan_count = 0;

                        if($tour_plan_count == 0){

                            echo "<div class='content-centered'><div class='content-block'>";
                                echo "<i class='far fa-calendar'></i>No requests!";
                            echo "</div></div>";  

                        }else{

                            foreach($tour_statuses_arr as $key=>$tour_status){
                                foreach($tour_plans as $tour_plan){
                                    if($tour_plan->tour_plan_status == $key){
                                        $tour_statuses_arr[$key]["count"] = $tour_statuses_arr[$key]["count"]+1;
                                        if($key == "on-hold" || $key == "processing" || $key == "completed"){
                                           $active_plan_count++;
                                        }
                                    }
                                }
                            }

                            echo "<ul class='list-statuses-dashboard list-statuses-active list-group'>";
                            foreach($tour_statuses_arr as $key=>$tour_status){
                                if($tour_status["count"]>0){
                                    echo "<li class='list-group-item d-flex justify-content-between align-items-center ".($tour_status["count"]>0?"active":"")."'>";
                                        if($tour_status["count"]>0){
                                            echo "<a href='".get_account_endpoint_url( $endpoint )."?tour-status=".$key."' class='btn-loading-page'></a>";                                       
                                        }
                                        echo "<span>";
                                            echo  $tour_status["title"];
                                            echo "<small>".$tour_status["description"]."</small>";
                                        echo "</span>";
                                        echo "<span class='badge badge-".$tour_status["class_status"]." badge-pill'>".$tour_status["count"]."</span>";
                                    echo "</li>";
                                }
                            }
                            echo "</ul>";

                        }
                    break;

                    case "edit-account" :
                        if ( in_array( 'agent', (array) $user->roles ) ) {
                            $arr = array(
                                array(
                                   'label' => "Agent",
                                   'value' => $user->display_name,
                                   'column' => "col-sm-6"
                               ),
                               array(
                                   'label' => "Authorized person",
                                   'value' => $user->first_name." ".$user->last_name,
                                   'column' => "col-sm-6"
                               ),
                               array(
                                   'label' => "E-mail",
                                   'value' => $user->user_email,
                                   'column' => "col-sm-6"
                               )
                            );
                        }
                        if ( in_array( 'customer', (array) $user->roles ) || in_array( 'administrator', (array) $user->roles ) ) {
                            $arr = array(
                               array(
                                   'label' => "Name & Last name",
                                   'value' => $user->first_name." ".$user->last_name,
                                   'column' => "col-sm-6"
                               ),
                               array(
                                   'label' => "E-mail",
                                   'value' => $user->user_email,
                                   'column' => "col-sm-6"
                               )
                            );
                        }
                            echo "<a href='".$url."' class='btn-loading-page'><span>Edit your account</span></a>";
                            echo "<div class='row'>";
                            foreach($arr as $item){
                                echo "<div class='".$item["column"]."'><div class='form-group form-group-md'>";
                                    echo "<div class='form-control-readonly'>";
                                        echo "<label class='form-label form-label-md mb-0 text-muted'>".$item["label"]."</label>";
                                        echo "<div class='form-control-readonly'>".$item["value"]."</div>";
                                    echo "</div>";
                                echo "</div></div>";
                            }
                            echo "</div>";
                    break;

                    case "edit-address" :
                        if ( ! wc_ship_to_billing_address_only() && wc_shipping_enabled() ) {
                            $get_addresses = apply_filters(
                                'woocommerce_my_account_get_addresses',
                                array(
                                    'billing'  => __( 'Billing address', 'woocommerce' ),
                                    'shipping' => __( 'Shipping address', 'woocommerce' ),
                                ),
                                $user->ID
                            );
                        } else {
                            $get_addresses = apply_filters(
                                'woocommerce_my_account_get_addresses',
                                array(
                                    'billing' => __( 'Billing address', 'woocommerce' ),
                                ),
                                $user->ID
                            );
                        }
                        if ( ! wc_ship_to_billing_address_only() && wc_shipping_enabled() ) :
                            echo '<div class="row">';
                        endif;

                        foreach ( $get_addresses as $name => $address_title ) :
                            $address = wc_get_account_formatted_address( $name );

                            if ( ! wc_ship_to_billing_address_only() && wc_shipping_enabled() ) :
                            echo '<div class="col-sm-6">';
                                echo '<div class="card">';
                                    echo '<div class="card-header">';
                                        echo '<h3 class="title card-title">'.esc_html( $address_title ).'</h3>';
                                    echo '</div>';
                                    echo '<div class="card-body">';
                                    endif;
                                        echo '<address>';
                                                echo $address ? wp_kses_post( $address ) : esc_html_e( 'You have not set up this type of address yet.', 'woocommerce' );
                                        echo '</address>';
                                        echo '<a href="'.  esc_url( wc_get_endpoint_url( 'edit-address', $name ) ).'" class="btn-loading-page"><span>'.($address ? esc_html__( 'Edit', 'woocommerce' ) : esc_html__( 'Add', 'woocommerce' )).'</span></a>';
                                    
                                    if ( ! wc_ship_to_billing_address_only() && wc_shipping_enabled() ) :
                                    echo '</div>';
                                echo '</div>';
                            echo '</div>';
                            endif;

                        endforeach;

                        if ( ! wc_ship_to_billing_address_only() && wc_shipping_enabled() ) :
                            echo '</div>';
                        endif;
                    break;*/

                }
                echo "</div>";
            echo "</div>";

            if(!$vertical){
                echo "</div>";
            }

        }
    };
    echo "</div>";
}