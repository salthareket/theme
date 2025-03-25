<?php

//redirects
$GLOBALS["woo_redirect_empty_cart"] = "";
$GLOBALS["woo_redirect_not_logged"] = get_account_endpoint_url('my-account');

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





/*
function variation_url_rewrite($link){
    return $link;
}
function  woo_url_pa_parse($product, $variation=""){
    return array();
}
function woo_get_product_attribute($attr){
    return array();
}
*/



//woocommerce_shop_page_display : empty, subcategories, both
//woocommerce_category_archive_display : empty, subcategories, both

//remove_action('woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail');




//shop page

// remove pagination 
remove_action( 'woocommerce_after_shop_loop', 'woocommerce_pagination', 10 );

// remove breadcrumb
remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );

remove_action( 'yith_wcan_filter_reset_button', 20);



add_filter( 'woocommerce_show_page_title', '__return_false' );
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_output_all_notices', 10 );
remove_action( 'woocommerce_before_shop_loop', 'wc_print_notices', 10 );
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );
/*add_action('woocommerce_before_shop_loop','catalog_wrapper', 2);
function catalog_wrapper(){
    echo '<h1 class="card-title">';
    woocommerce_result_count();
    echo '</h1><div class="action">';
    woocommerce_catalog_ordering();
    echo '</div>';
}
*/


/*
function variation_url_rewrite($link){
    return $link;
}
function  woo_url_pa_parse($product, $variation=""){
    return array();
}
function woo_get_product_attribute($attr){
    return array();
}
*/


function woo_archive_grid($min_col=2, $desired=array()){
    $cols = intval(get_option("woocommerce_catalog_columns", 4));
    $rows = intval(get_option("woocommerce_catalog_rows", 3));
    $diff = round(($cols - $min_col)/4);
    function woo_archive_grid_checker($val){
        if($val < $min_col){
           $val = $min_col;
        }
        return $val;
    }
    $steps = array();
    $breakpoints = ["xxl", "xl", "lg", "md", "sm", ""];
    $start = $cols;
    foreach($breakpoints as $key => $breakpoint){
        if($desired && isset($desired[$breakpoint])){
            $val = $desired[$breakpoint];
        }else{
            if($key == 0){
                $val = $cols;
            }else if($key == count($breakpoints)-1){
                $val = $min_col;
            }else{
                $start -= $diff;
                $val = $start;
            }
            if($val < $min_col){
               $val = $min_col;
            }            
        }
        $steps[] = "row-cols-".(!empty($breakpoint)?$breakpoint."-":"").$val;
    }
    return implode(" ", $steps);
}


//woocommerce_shop_page_display : empty, subcategories, both
//woocommerce_category_archive_display : empty, subcategories, both

//remove_action('woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail');




//shop page

// remove pagination 
remove_action( 'woocommerce_after_shop_loop', 'woocommerce_pagination', 10 );

// remove breadcrumb
remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );

remove_action( 'yith_wcan_filter_reset_button', 20);



add_filter( 'woocommerce_show_page_title', '__return_false' );
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_output_all_notices', 10 );
remove_action( 'woocommerce_before_shop_loop', 'wc_print_notices', 10 );
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );
/*add_action('woocommerce_before_shop_loop','catalog_wrapper', 2);
function catalog_wrapper(){
    echo '<h1 class="card-title">';
    woocommerce_result_count();
    echo '</h1><div class="action">';
    woocommerce_catalog_ordering();
    echo '</div>';
}
*/