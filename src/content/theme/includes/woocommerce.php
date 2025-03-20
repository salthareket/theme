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





// show only simple products
function custom_product_query($query){
    if (is_admin()) {
        return;
    }

    if ($query->is_main_query() && (is_product_category() || is_shop())) {
            $vars = isset($_POST["vars"]) ? $_POST["vars"] : "";
            if ($vars && isset($vars["page"])) {
                $query->set('paged', $vars["page"]);
            } else {
                $paged = empty(get_query_var('paged')) ? 1 : get_query_var('paged');
                $query->set('paged', $paged);
            }
            $woocommerce_catalog_columns = get_option("woocommerce_catalog_columns");
            $woocommerce_catalog_rows = get_option("woocommerce_catalog_rows");
            $posts_per_page = intval($woocommerce_catalog_columns * $woocommerce_catalog_rows);
            $query->set('posts_per_page', $posts_per_page);
            $query->set('numberposts', $posts_per_page);
            if(!DISABLE_DEFAULT_CAT){
                $tax_query_obj = $query->tax_query;
                $tax_query_obj->queries[] = array(
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => array(
                        get_option('default_product_cat')
                    ) , // Don't display products in the clothing category on the shop page.
                    'operator' => 'NOT IN'
                );
                foreach ($tax_query_obj->queries as $q) {
                    $tax_query[] = $q;
                }
                $query->set('tax_query', $tax_query);                
            }
    }

    if ((is_shop() || is_home() ||  is_post_type_archive( 'product' )) && $query->is_main_query() && !isset($_GET["orderby"]) ) {
        // Varsayılan sıralama özelliklerini ayarla
        $query->set('orderby', 'book_release_date');
        $query->set('order', 'DESC');
    }

    /*if( is_product_category()){
        $woocommerce_catalog_columns = get_option("woocommerce_catalog_columns");
        $woocommerce_catalog_rows = get_option("woocommerce_catalog_rows");
        $posts_per_page = intval($woocommerce_catalog_columns * $woocommerce_catalog_rows);
        $query->set("posts_per_page", $posts_per_page);
    }*/
    
    // add default brand to query
    if( $query->is_main_query() && (is_single() || is_post_type_archive()) ) {
        $brand = get_query_var("wpc-brand");
        if(empty($brand)){
            set_query_var('wpc-brand', 'jaguar');
        }
    }

    if( $query->is_main_query() && is_post_type_archive( 'product' ) ) {
        $action = get_query_var("action");
        $brand = get_query_var("wpc-brand");
        switch($action){
            case "siradakiler":
               $product_type = array("simple");
               $query->set( 'meta_key', 'book_release_date' );
               $query->set( 'meta_value', date('Y-m-d') );
               $query->set( 'meta_compare', '>' );
               $query->set( 'meta_type', 'DATE' );
               $query->set( 'orderby', 'meta_value' );
               $query->set( 'order', 'ASC' );
            break;
            case "yeni":
                $current_date = date( 'Y-m-d' );
                $one_year_ago = date( 'Y-m-d', strtotime( '-2 year' ) );
                $product_type = array("simple");
                $query->set( 'meta_key', 'book_release_date' );
                $query->set( 'meta_value', array( $one_year_ago, $current_date ) );
                $query->set( 'meta_compare', 'BETWEEN' );
                $query->set( 'meta_type', 'DATE' );
                /*if ( empty($_GET['orderby']) ) {
                    $query->set( 'orderby', 'meta_value' );
                    $query->set( 'order', 'ASC' );
                }*/
            break;
            case "seriler":
               $product_type = array("grouped");
            break;
            default :
               $product_type =  array("simple");
            break;
        }
        $query->set( 'post_type', 'product' );
        if($product_type){
            $query->set( 'tax_query', array(
                array(
                    'taxonomy' => 'product_type',
                    'field'    => 'slug',
                    'terms'    =>  $product_type ,
                ),
            ));            
        }
    }

    if( $query->is_main_query() && !is_single() && (is_post_type_archive( 'yazarlar' ) || (is_post_type_archive( 'yazarlar' ) && is_tax('wpc-brand', 'olvido'))) ) {
        $sortby = get_query_var("sortby");
        if(empty($sortby)){
            set_query_var('sortby', 'name');
        }
        switch($sortby){
            case "name":
            default :
               $query->set( 'orderby', 'title' );
               $query->set( 'order', 'ASC' );
            break;
            case "book_count":
               $query->set( 'meta_key', 'book_count' );
               $query->set( 'orderby', 'meta_value' );
               $query->set( 'order', 'DESC' );
            break;
        }
        $query->set("post_type", "yazarlar");
        $query->set("posts_per_page", -1);
        $query->set("numberposts", -1);

        $brand = get_query_var("wpc-brand");
        if($brand){
            $tax_query = array(
                array(
                    'taxonomy' => 'wpc-brand',
                    'field'    => 'slug',
                    'terms'    => array($brand),
                ),
            );
            $query->set('tax_query', $tax_query);            
        }
        $query->is_tax = 0;
        unset($query->query_vars["wc_query"]);
        //die;
    }
}
add_action('pre_get_posts', 'custom_product_query');








function custom_default_catalog_orderby() {
     return 'release_date_desc'; // Can also use title and price
}
add_filter('woocommerce_default_catalog_orderby', 'custom_default_catalog_orderby');

// modify shop sort dropdown
function patricks_woocommerce_catalog_orderby( $orderby ) {
    unset($orderby["price"]);
    unset($orderby["price-desc"]);
    unset($orderby["rating"]);
    unset($orderby["date"]);
    unset($orderby["popularity"]);
    unset($orderby["menu_order"]);
    $orderby['release_date_desc'] = trans( 'Yayımlanma tarihi (Yeniden eskiye)');
    $orderby['release_date_asc'] = trans( 'Yayımlanma tarihi (Eskiden yeniye)');
    $orderby["title_asc"] = trans('Kitap adı - A-Z');
    $orderby["title_desc"] = trans('Kitap adı - Z-A');
    $orderby['author_name_asc'] = trans( 'Yazar adı - A-Z');
    $orderby['author_name_desc'] = trans( 'Yazar adı - Z-A');
    return $orderby;
}
add_filter( "woocommerce_catalog_orderby", "patricks_woocommerce_catalog_orderby", 20 );

function patricks_woocommerce_get_catalog_ordering_args( $args ) {
    global $wp_query;
    if($wp_query->query_vars["post_type"] != "product"){
        return $args;
    }
    $orderby_value = isset( $_GET['orderby'] ) ? wc_clean( $_GET['orderby'] ) : apply_filters( 'woocommerce_default_catalog_orderby', get_option( 'woocommerce_default_catalog_orderby' ) );
    switch( $orderby_value ) {
        case 'title_desc' :
            $args['orderby'] = 'title';
            $args['order']   = 'DESC';
        break;
        case 'title_asc' :
            $args['orderby'] = 'title';
            $args['order']   = 'ASC';
        break;
        case 'release_date_desc' :
            $args['meta_key'] = 'book_release_date';
            $args['orderby'] = 'meta_value';
            $args['order']   = 'DESC';
        break;
        case 'release_date_asc' :
            $args['meta_key'] = 'book_release_date';
            $args['orderby'] = 'meta_value';
            $args['order']   = 'ASC';
        break;
        case 'author_name_asc' :
            $args['meta_key'] = 'book_author_name';
            $args['orderby'] = 'meta_value';
            $args['order']   = 'ASC';
        break;
        case 'author_name_desc' :
            $args['meta_key'] = 'book_author_name';
            $args['orderby'] = 'meta_value';
            $args['order']   = 'DESC';
        break;
    }
    return $args;
}
add_filter( 'woocommerce_get_catalog_ordering_args', 'patricks_woocommerce_get_catalog_ordering_args', 20 );








// Ürün (product) post type'ının isim, slug ve label'larını değiştirme
function custom_change_product_labels() {
    global $wp_post_types;

    // Ürün post type'ını al
    $product_post_type = $wp_post_types['product'];

    // Yeni isim, slug ve label'ları ayarla
    $product_post_type->labels->name = trans('Kitaplar');
    $product_post_type->labels->singular_name = trans('Kitap');
    $product_post_type->labels->add_new = 'Yeni Ekle';
    $product_post_type->labels->add_new_item = 'Yeni Kitap Ekle';
    $product_post_type->labels->edit_item = 'Kitabı Düzenle';
    $product_post_type->labels->new_item = 'Yeni Kitap';
    $product_post_type->labels->view_item = 'Kitabı Görüntüle';
    $product_post_type->labels->search_items = 'Kitap Ara';
    $product_post_type->labels->not_found = 'Kitap Bulunamadı';
    $product_post_type->labels->not_found_in_trash = 'Çöp Kutusunda Kitap Bulunamadı';
    $product_post_type->labels->all_items = 'Tüm Kitaplar';
    $product_post_type->labels->menu_name = trans('Kitaplar');
}
add_action('init', 'custom_change_product_labels');






// Remove unused product types & options / Rename Product Types
function remove_specific_product_types() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }
    $types_to_remove = array( 'downloadable', 'virtual', 'external', 'variable' );
    add_filter( 'product_type_selector', function( $product_types ) use ( $types_to_remove ) {
        foreach ( $types_to_remove as $type ) {
            unset( $product_types[ $type ] );
        }
        $product_types[ "simple" ] = "Kitap";
        $product_types[ "grouped" ] = "Kitap Serisi";
        return $product_types;
    } );
    foreach ( $types_to_remove as $type ) {
        remove_action( 'woocommerce_product_options_general_product_data', 'wc_product_' . $type . '_product_options' );
    }
}
add_action( 'init', 'remove_specific_product_types' );

function remove_downloadable_external_options_for_simple_products( $options ) {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return $options;
    }
    unset( $options['virtual'] );
    unset( $options['downloadable'] );
    unset( $options['external'] );
    return $options;
}
add_filter( 'product_type_options', 'remove_downloadable_external_options_for_simple_products', 10 );




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




function custom_post_permalink($permalink, $post) {
    $brand = get_query_var("wpc-brand");
    if($brand == "olvido"){
        $permalink = str_replace("kitaplar", "olvido/kitaplar", $permalink);
        $permalink = str_replace("yazarlar", "olvido/yazarlar", $permalink);
    }else{
        $terms = get_the_terms($post->ID, 'wpc-brand');
        if ($terms && !is_wp_error($terms)) {
            $term = reset($terms);
            if($term->slug == "olvido"){
                $permalink = str_replace("kitaplar", "olvido/kitaplar", $permalink);
                $permalink = str_replace("yazarlar", "olvido/yazarlar", $permalink);
            }
        }
    }
    return $permalink;
}
//add_filter('post_type_link', 'custom_post_permalink', 10, 2);


function custom_post_type_archive_link($link, $post_type) {
    $brand = get_query_var("wpc-brand");
    if($brand == "olvido"){
        if($post_type == "product"){
            $link = site_url("olvido/");
        }
        if($post_type == "yazarlar"){
            $link = str_replace("yazarlar", "olvido/yazarlar", $link);
        }
    }
    return $link;
}
//add_filter('post_type_archive_link', 'custom_post_type_archive_link', 10, 2);







// Custom Breadcrumb
function custom_breadcrumb_links( $links ) {
    // url, text, id  ptarchive

    // no breadcrumb for olvido
    if(get_query_var("wpc-brand") == "olvido"){
        //return array();
    }
    
    if( is_post_type_archive( 'product' ) ) {
        $links = [];
        $action = get_query_var("action");
        if(!empty($action)){
            if($action == "olvido"){
                $links[] = array(
                    "text" => "Olvido",
                    "url"  => site_url("olvido/")
                );
            }else{
                $labels = get_post_type_labels(get_post_type_object('product'));
                $archive_url = get_post_type_archive_link("product");
                if(!$archive_url){
                    $archive_url = home_url();
                }
                $links[] = array(
                    "text" => $labels->name,
                    "url"  => $archive_url
                );
                switch($action){
                    case "yeni" :
                       $action_title = trans("Yeni");
                    break;
                    case "siradakiler" :
                       $action_title = trans("Sıradakiler");
                    break;
                    case "seriler" :
                       $action_title = trans("Seriler");
                    break;
                }
                $links[] = array(
                    "text" => __($action_title),
                    "url"  => $archive_url."/".$action."/"
                );                
            }
        }else{
            $labels = get_post_type_labels(get_post_type_object('product'));
            $archive_url = get_post_type_archive_link("product");
            if(!$archive_url){
                $archive_url = home_url();
            }
            $links[] = array(
                "text" => $labels->name,
                "url"  => $archive_url
            );
        }
    }

    if( is_singular( 'product' ) ) {
        $action = get_query_var("wpc-brand");//get_query_var("action");
        if(!empty($action)){
            if($action == "olvido"){
                $product = end($links);
                //$product["url"] = str_replace("kitaplar", "olvido/kitaplar", $product["url"]);
                $links = [];
                $links[] = array(
                    "text" => "Olvido",
                    "url"  => site_url("olvido/")
                );
                $links[] = $product;                
            }

        }
    }

    if( is_post_type_archive( 'yazarlar' ) || is_singular("yazarlar") ) {
        if(get_query_var("wpc-brand") == 'olvido'){
            $text = "Olvido";
            $url = site_url("olvido");
            foreach($links as $key => $link){
                $links[$key]["url"] = str_replace("yazarlar", "olvido/yazarlar", $link["url"]);
            }
            $link_first = array(
                "text" => $text,
                "url"  => $url
            );
        }else{
            $labels = get_post_type_labels(get_post_type_object('product'));
            $archive_url = get_post_type_archive_link("product");
            if(!$archive_url){
                $archive_url = home_url();
            }
            $text = $labels->name;
            $url = $archive_url; 
            $link_first = array(
                "text" => $text,
                "url"  => $url
            );      
        }
        array_unshift($links, $link_first);
    }

    return $links;
}
//add_filter( 'wpseo_breadcrumb_links', 'custom_breadcrumb_links' );