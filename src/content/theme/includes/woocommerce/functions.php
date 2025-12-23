<?php


function woo_product($post){
    if ( is_woocommerce() ) {
        return Timber::get_post(wc_get_product($post->ID));
    }
}
function timber_set_product___( $id ) {
    if ( is_woocommerce() ) {
        return  wc_get_product( $id );//new WC_Product($id);
    }
}
function timber_set_product( $post ) {
    global $product;
   // if ( is_woocommerce() ) {
        $product = wc_get_product( $post->ID );
   // }
}



/**
 * WooCommerce ücretsiz kargo için minimum harcama tutarını döner
 * 
 * @param float|int $multiply_by Sonucu çarpmak için (default 1)
 * @return float Minimum ücretsiz kargo tutarı
 */
function get_free_shipping_amount( $multiply_by = 1 ) {
    $min_free_shipping_amount = 0;

    if ( class_exists('WC_Shipping_Free_Shipping') ) {
        $current_wc_version = get_option('woocommerce_version', null);

        if ( version_compare($current_wc_version, '2.6.0', '<') ) {
            // WooCommerce eski sürüm
            $free_shipping = new WC_Shipping_Free_Shipping();
            if ( in_array($free_shipping->requires, ['min_amount', 'either', 'both']) ) {
                $min_free_shipping_amount = $free_shipping->min_amount;
            }
        } else {
            // WooCommerce yeni sürüm
            $legacy_free_shipping = new WC_Shipping_Legacy_Free_Shipping();
            if ( 'yes' === $legacy_free_shipping->enabled ) {
                if ( in_array($legacy_free_shipping->requires, ['min_amount', 'either', 'both']) ) {
                    $min_free_shipping_amount = $legacy_free_shipping->min_amount;
                }
            }

            // Eğer yukarıda bulunamadıysa shipping methodlardan kontrol et
            if ( 0 == $min_free_shipping_amount ) {
                if ( function_exists('WC') && ( $wc_shipping = WC()->shipping ) && ( $wc_cart = WC()->cart ) ) {
                    if ( $wc_shipping->enabled ) {
                        if ( $packages = $wc_cart->get_shipping_packages() ) {
                            $shipping_methods = $wc_shipping->load_shipping_methods($packages[0]);
                            foreach ( $shipping_methods as $shipping_method ) {
                                if ( 'yes' === $shipping_method->enabled && 0 != $shipping_method->instance_id ) {
                                    if ( 'WC_Shipping_Free_Shipping' === get_class($shipping_method) ) {
                                        if ( in_array($shipping_method->requires, ['min_amount', 'either', 'both']) ) {
                                            $min_free_shipping_amount = $shipping_method->min_amount;
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    return $min_free_shipping_amount * $multiply_by;
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



function save_role_based_pricing($post_id, $post, $update){

	$post_type = get_post_type($post_id);
    if ( "yith_price_rule" != $post_type ) return;

	//Role Based Pricing
	$args = array(
		    "post_type" => "yith_price_rule",
		    'meta_query' => array(
		    	                array(
								    'key' => '_ywcrbp_active_rule',
								    'value' => 1,
								    'compare' => '='
							    )
							)
	);
	$role_pricing = get_posts($args);
	$role_pricing_data = array();
	foreach($role_pricing as $prole_price){
		$data = get_post_meta($prole_price->ID);
		//values
		$role         = $data["_ywcrbp_role"][0]; //roles
		$type         = $data["_ywcrbp_type_rule"][0];//global, category, tag
		$categories   = $data["_ywcrbp_category_product"];
		$tags         = $data["_ywcrbp_tag_product"];
		$price_type   = $data["_ywcrbp_type_price"][0]; //discount_perc, discount_val, markup_perc, markup_val
		$percent_val  = $data["_ywcrbp_decimal_value"][0]; 
		$price_val    = $data["_ywcrbp_price_value"][0];
		$priority     = $data["_ywcrbp_priority_rule"][0];
		$sql_appendix = "";
		if(strpos($price_type, "perc")>-1){
		   if(strpos($price_type, "discount")>-1){
              $sql_appendix = "/".(100/$percent_val);
		   }else{
              $sql_appendix = "+(mt2.meta_value/".(100/$percent_val).")";
		   }
		}else{
		   if(strpos($price_type, "discount")>-1){
              $sql_appendix = "-".$price_val;
		   }else{
              $sql_appendix = "+".$price_val;
		   }
		}
		if(!array_key_exists($role, $role_pricing_data)){
			$role_pricing_data[$role] = array();
		}
		$category_list = array();
		if($type == "category"){
		   foreach($categories as $category){
              $category_list[] = array(
              	  "id"   => maybe_unserialize($category),
              	  "slug" => get_category(maybe_unserialize($category))->slug
              );
		   }
		}
		$tag_list = array();
		if($type == "tag"){
		   foreach($tags as $tag){
              $tag_list[] = array(
              	  "id"   => maybe_unserialize($tag),
              	  "slug" => get_tag(maybe_unserialize($category))->name
              );
		   }
		}
		$role_pricing_data[$role][] = array(
			"priority"   => $priority,
			"type"       => $type,
			"categories" => $category_list,
			"tags"       => $tag_list,
			"sql_from"   => "CAST(mt2.meta_value AS SIGNED)",
			"sql_to"     => "CAST(mt2.meta_value".$sql_appendix." AS SIGNED)"
		);
	}
	if ( get_option( "role_pricing" ) !== false ) {
         update_option("role_pricing", $role_pricing_data );
    } else {
	     add_option("role_pricing", $role_pricing_data);
    }
}
//add_action( 'save_post', 'save_role_based_pricing', 10, 3 );





/**
 * Ürünün kapak (ana) görsellerini ve varyasyon görsellerini alır.
 * 
 * @param WC_Product|int $product Product nesnesi veya product ID
 * @param bool $multiple Çoklu görsel döndürmek için true (default false)
 * @return array|string Ürün görsel URL’leri dizisi veya tek URL
 */
function get_product_cover($product, $multiple = false) {
    // Eğer ID geldiyse ürün nesnesi alalım
    if (is_numeric($product)) {
        $product = wc_get_product($product);
    }
    if (!$product) {
        return $multiple ? [] : '';
    }

    $images = [];
    $product_type = $product->get_type();

    switch ($product_type) {
        case 'simple':
            $image_id = $product->get_image_id();
            if ($image_id) {
                $images[] = wp_get_attachment_url($image_id);
            }
            break;

        case 'variable':
            // Değişken ürünün varyasyonları
            $variations = $product->get_available_variations();
            foreach ($variations as $variation) {
                $image_id = $variation['image_id'];
                if ($image_id) {
                    $images[] = wp_get_attachment_url($image_id);
                    // Eğer varyasyonun ek görselleri varsa
                    $additional_images = get_post_meta($variation['variation_id'], '_wc_additional_variation_images', true);
                    if ($additional_images) {
                        $additional_ids = array_filter(explode(',', $additional_images));
                        foreach ($additional_ids as $add_id) {
                            $images[] = wp_get_attachment_url($add_id);
                        }
                    }
                }
            }
            break;

        case 'variation':
            $image_id = $product->get_image_id();
            if ($image_id) {
                $images[] = wp_get_attachment_url($image_id);
            }
            break;

        case 'grouped':
            // Grup ürünün ana ürünlerinin görselleri
            $children = $product->get_children();
            foreach ($children as $child_id) {
                $child_product = wc_get_product($child_id);
                if ($child_product) {
                    $image_id = $child_product->get_image_id();
                    if ($image_id) {
                        $images[] = wp_get_attachment_url($image_id);
                    }
                }
            }
            break;

        // Diğer ürün tipleri için özelleştirilebilir
        case 'woosg': // Smart Group
        case 'woosb': // Bundle
        case 'bundle':
            // İstersen burada ekleme yapabilirsin
            break;
    }

    if ($multiple) {
        return $images;
    } else {
        return (!empty($images)) ? $images[0] : '';
    }
}




/**
 * Find matching product variation
 *
 * @param WC_Product $product
 * @param array $attributes
 * @return int Matching variation ID or 0.
 */
function iconic_find_matching_product_variation( $product, $attributes ) {
    foreach( $attributes as $key => $value ) {
        if( strpos( $key, 'attribute_' ) === 0 ) {
            continue;
        }
        unset( $attributes[ $key ] );
        $attributes[ sprintf( 'attribute_%s', $key ) ] = $value;
    }
    if( class_exists('WC_Data_Store') ) {
        $data_store = WC_Data_Store::load( 'product' );
        return $data_store->find_matching_product_variation( $product, $attributes );
    } else {
        return $product->get_matching_variation( $attributes );
    }
}




/**
 * Get variation default attributes
 *
 * @param WC_Product $product
 * @return array
 */
function iconic_get_default_attributes( $product ) {

    if( method_exists( $product, 'get_default_attributes' ) ) {

        return $product->get_default_attributes();

    } else {

        return $product->get_variation_default_attributes();

    }
}





function woo_product_badges( $product, $free_shipping_min_amount, $types ) {
    if ( isset( $types ) ) {
        $types = explode( ",", $types );
    } else {
        return;
    }

    $count      = 0;
    $product_id = is_callable( [ $product, 'get_id' ] ) ? $product->get_id() : $product->id;
    $low_stock  = woo_get_product_low_stock_amount( $product_id );
    $stock      = round( $product->get_stock_quantity() );
    $price      = $product->get_price();
    $sale_price = $product->get_sale_price();
    $badges     = "";
    $price_highest = $price;

    if ( is_array( $price ) ) {
        $price_highest = max( $price_highest );
    }

    $low_stock_forced = false;
    if ( ! empty( $GLOBALS['query_vars'] ) ) {
        if ( in_array( "tukenmek-uzere", array_values( $GLOBALS['query_vars'] ) ) ) {
            $low_stock_forced = true;
        } else {
            foreach ( $GLOBALS['query_vars'] as $var ) {
                if ( ! empty( $var->slug ) && $var->slug === "durum" ) {
                    if ( in_array( "tukenmek-uzere", (array) $var->terms ) ) {
                        $low_stock_forced = true;
                    }
                }
            }
        }
    }

    if ( $sale_price && in_array( "discount", $types ) ) {
        $badges .= '<div class="product-badge discount" title="İndirimde"><i class="icon icon-discount"></i></div>';
        $count++;
    }

    if ( ( ( $stock > 0 && $stock < $low_stock ) || $low_stock_forced ) && in_array( "stock", $types ) ) {
        $badges .= '<div class="product-badge low-stock">' . sprintf( __( 'Son %s ürün', 'woocommerce' ), $stock ) . '</div>';
        $count++;
    }

    if ( $price_highest >= $free_shipping_min_amount && ! empty( $free_shipping_min_amount ) && in_array( "shipping", $types ) ) {
        $badges .= '<div class="product-badge free-shipping" title="Ücretsiz Kargo"><i class="icon icon-cargo"></i></div>';
        $count++;
    }

    if ( $count > 0 ) {
        $badges = "<div class='product-badges'>" . $badges . "</div>";
    }

    return $badges;
}



function variation_url_rewrite($url){
    $query_json = array();
    $parsed_url = parse_url($url);
    //if(array_key_exists("query", $parsed_url)){
    if(isset($parsed_url["query"])){
        $query = $parsed_url["query"];
        if(!empty($query)){
            $url = str_replace("?".$query, "", $url);
            $query=explode("&", $query);
            foreach($query as $item){
                $item=explode("=",$item);
                $query_json[str_replace("attribute_pa_", "", $item[0])] = $item[1];
            }
            if(isset($query_json["color"])){
                $url = $url."color-".$query_json["color"]."/";
            }
            
        }       
    }
    //}
    return $url;
}

function wc_search_url(){
    return esc_url( home_url( '/'  ) );
}

function woo_login_url($redirect_to = ""){
    $myaccount_page_id = get_option( 'woocommerce_myaccount_page_id' );
    if ( $myaccount_page_id ) {
      $login_url = get_permalink( $myaccount_page_id );
      if ( get_option( 'woocommerce_force_ssl_checkout' ) == 'yes' )
        $login_url = str_replace( 'http:', 'https:', $login_url );
    }
    if($redirect_to){
        /*if(!session_id()) {
            session_start();
        }*/
        $_SESSION['referer_url'] = esc_url($redirect_to);
    }
    return $login_url;
}

function woo_logout_url(){
    $myaccount_page_id = get_option( 'woocommerce_myaccount_page_id' );
    if ( $myaccount_page_id ) {
      $logout_url = wp_logout_url( get_permalink( $myaccount_page_id ) );
      if ( get_option( 'woocommerce_force_ssl_checkout' ) == 'yes' )
        $logout_url = str_replace( 'http:', 'https:', $logout_url );
    }
    return $logout_url;
}

function woo_checkout_url(){
    global $woocommerce;
    return function_exists( 'wc_get_cart_url' ) ? wc_get_checkout_url() : $woocommerce->cart->get_checkout_url();
}

function woo_get_cart(){
    global $woocommerce;
    return $woocommerce->cart->get_cart();
}
/*
function woo_get_cart_url(){
    global $woocommerce;
    return $woocommerce->cart-> wc_get_cart_url();
}*/

function woo_get_cart_object(){
    global $woocommerce;
    $items = $woocommerce->cart->get_cart();
    $items_list = array();
    foreach($items as $item => $values) {
            $id = $values['data']->get_id();
            $_product = wc_get_product($id);
            
            //$image = $_product->get_image("thumbnail");//wp_get_attachment_image_url($_product->get_image_id(), "thumbnail");//get_the_post_thumbnail_url($id, "shop_thumbnail"); 
            //$image = wp_get_attachment_image_src( get_post_thumbnail_id( $id ), 'shop-thumbnail' );
            //$image = $_product->get_image("shop_thumbnail");
            $image =wp_get_attachment_image_url($_product->get_image_id(), "thumbnail");
            $title = $_product->get_title();
            $url = get_permalink($id);
            $quantity = $values['quantity'];
            $variations = array();
            foreach($values['variation'] as $key => $variation) {
                array_push($variations, attribute_slug_to_title( $key, $variation));
            }
            $price = $_product->get_price();//$values['data']->price;//get_post_meta($values['product_id'] , '_price', true);
            $price_regular = $_product->get_regular_price();//$_product->_regular_price;//$values['data']->regular_price;//get_post_meta($values['product_id'] , '_regular_price', true);
            $price_sale = $_product->get_sale_price();//$values['data']->sale_price;//get_post_meta($values['product_id'] , '_sale_price', true);
            $cart_item = array(
                 "key"           => $values['key'],
                 "id"            => $values['product_id'],//$getProductDetail->id,
                 "image"         => $image,
                 "title"         => $title,
                 "url"           => $url,
                 "quantity"      => $quantity,
                 "backorders"    => $_product->get_backorders(),//$values['data']->backorders,
                 "price"         => $_product->get_price(),//get_post_meta($id, '_price', true),// ($price),
                 "price_regular" => $price_regular,
                 "price_sale"    => $price_sale,
                 "stock"         => $_product->get_stock_quantity(),//$values['data']->stock_quantity,
                 "variations"    => $variations
            );
            array_push($items_list, $cart_item);
    }
    $cart = array(
           "count" => $woocommerce->cart->get_cart_contents_count(),
           "total" => woo_get_currency_with_price($woocommerce->cart->total),
           "items" => $items_list
    );
    return $cart;
}

function woo_get_cart_count(){
    global $woocommerce;
    return is_object( $woocommerce->cart ) ? $woocommerce->cart->get_cart_contents_count() : 0;
    /*global $WC;
    return WC()->cart->get_cart_contents_count();*/
}
function wc_cart_item_quantity_update($key, $count){
    global $woocommerce;
    return $woocommerce->cart->set_quantity($key, $count);
}
function wc_cart_item_remove($key){
    global $woocommerce;
    return $woocommerce->cart->remove_cart_item($key);
}

function woo_get_price($id){
    $product = new WC_Product($id);
    return $product->get_price_html();
}

function woo_get_price_only($id){
    $product = new WC_Product($id);
    return $product->get_price();
}

function woo_get_currency(){
    global $woocommerce;
    return get_woocommerce_currency_symbol();
}

function woo_get_currency_with_price($price=0, $currency=""){
    global $woocommerce;
    $currency_code = get_woocommerce_currency_symbol($currency);
    return number_format( floatval($price), wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator()).' <span class="currency">'.$currency_code.'</span>';
}

function woo_get_product_type($id){
    return WC_Product_Factory::get_product_type($id);
}

function woo_get_variations($id){
    $product =  wc_get_product($id);
    if($product->product_type == "variable"){
       return $product->get_available_variations();
    }
}


function woo_get_product_attribute($attr){
	global $product;
	return $product->get_attribute( $attr );
}
function woo_get_product_attributes($arr){
	if(count($arr)>0){
	   $attrs = array();
       global $product;
       foreach($arr as $key=>$attr){	
    	    $attrs[$key]=$product->get_attribute( "$key" );
	   };
	   return $attrs;
	}	
}

// attribute slug to title
if ( ! function_exists( 'attribute_slug_to_title' ) ) {
    function attribute_slug_to_title( $attribute ,$slug ) {
        global $woocommerce;
        if ( taxonomy_exists( esc_attr( str_replace( 'attribute_', '', $attribute ) ) ) ) {
            $term = get_term_by( 'slug', $slug, esc_attr( str_replace( 'attribute_', '', $attribute ) ) );
            if ( ! is_wp_error( $term ) && $term->name )
                $value = $term->name;
        } else {
            $value = apply_filters( 'woocommerce_variation_option_name', $value );
        }
        return $value;
    }
}


function woo_get_product_gallery($product_id){
    $gallery=array();
	//$product = new WC_product($product_id);
    $product =  wc_get_product($product_id);
    //print_r($product);
    $attachment_ids = $product->get_gallery_image_ids();
    //print_r($attachment_ids);
    foreach( $attachment_ids as $attachment_id ) {
          $gallery[] = wp_get_attachment_url( $attachment_id );
          // Display Image instead of URL
          //echo wp_get_attachment_image($attachment_id, 'full');
    }/**/
    return $gallery;
}



function woo_get_product_variations_loop($product){
     ///global $product;
    $attributes = $product->get_attributes();
    if ( ! $attributes ) {
        return;
    }
    $terms_out = array();
    foreach ( $attributes as $attribute ) {
        // skip variations
        if ( $attribute->get_variation() ) {
             //continue;
        }
        $name = $attribute->get_name();
        if ( $attribute->is_taxonomy() ) {
            $terms = wp_get_post_terms( $product->get_id(), $name, 'all' );
            // get the taxonomy
            $tax = $terms[0]->taxonomy;
            // get the tax object
            $tax_object = get_taxonomy($tax);
            $tax_name = $tax_object->name;
            $tax_id = $tax_object->id;
            // get tax label
            if ( isset ($tax_object->labels->singular_name) ) {
                $tax_label = $tax_object->labels->singular_name;
            } elseif ( isset( $tax_object->label ) ) {
                $tax_label = $tax_object->label;
                // Trim label prefix since WC 3.0
                if ( 0 === strpos( $tax_label, 'Product ' ) ) {
                   $tax_label = substr( $tax_label, 8 );
                }                
            }

            $tax_terms = array();
            foreach ( $terms as $term ) {
                $term_array = array(
                    "id" => $term->term_id,
                    "name" => esc_html( $term->name ),
                    "color" => get_term_meta( $term->term_id, "color", true)
                );
                array_push( $tax_terms, $term_array );
            }
            $term_data = array(
                "id"  => $tax_id,
                "name"  => $tax_label,
                "slug"  => $tax_name,
                "terms" => $tax_terms
            );
            array_push( $terms_out, $term_data );
        } else {
            //$out .= $name . ': ';
            //$out .= esc_html( implode( ', ', $attribute->get_options() ) ) . '<br />';
        }
    }
    return $terms_out;
}

function woo_get_product_variations_unique($arr){
    //$arr = $arr[0];
    $am = array();
    $unique_terms = array_unique($arr);
    $i=0;
    foreach ( $unique_terms as $unique ) {
        //print_r($unique);
       foreach ( $arr as $arr_item ) {
        
           //array_merge($unique[$i]["terms"], $arr_item[$i]["terms"]);
           foreach ( $arr_item[$i]["terms"] as $arr_term_item ) {
              $unique[$i]["terms"][] = $arr_term_item;
           }

       }
       $unique[$i]["terms"]= array_unique($unique[$i]["terms"]);
       $i++;
       array_push($am, $unique);
    }
    return $am;
}


function woo_get_product_low_stock_amount($product){
    if (!is_object($product)) {
        $product = new WC_Product($product);
    }
   return wc_get_low_stock_amount( $product );
}

function woo_get_available_categories(){
    $kategori = get_query_var("kategori");
    if(empty($kategori)){
       $kategori = get_query_var("product_cat");
    }
    if($kategori){
        $args = array(
            'orderby'    => "menu_order",
            'order'      => "asc",
            'hide_empty' => true,
            //'exclude'    => array(get_term_by( 'slug', $kategori, 'product_cat' )->term_id)
        );
        //$args["exclude"][] = get_option( 'default_product_cat' );
        return Timber::get_terms( 'product_cat', $args );        
    }
}

function woo_get_category_thumbnail($cat){
    if(isset($cat->object_id)){
       $cat_id = $cat->object_id;
    }else{
       $cat_id = $cat->term_id;
    }
    $thumbnail_id = get_term_meta( $cat_id, 'thumbnail_id', true ); 
    return wp_get_attachment_url( $thumbnail_id );
}

function woo_get_product_variations_thumbnails($product_id, $attr, $size){//full, gallery_thumbnail, thumb
    $images = array();
    $variation_colors = array();
    $product = new WC_Product_Variable( $product_id );
    $variations = $product->get_available_variations();
    foreach ( $variations as $variation ) {
        if(array_key_exists("attribute_pa_".$attr, $variation["attributes"])){
            $variation_value = $variation["attributes"]["attribute_pa_".$attr];
            if(!in_array($variation_value, $variation_colors)){
                $images[] = $variation['image'][$size.'_src'];
                $variation_colors[] = $variation_value;            
            }            
        }
    }
    return $images;  
}



function woo_get_product_variation_thumbnails($product_id, $attr, $attr_value, $size='full'){
    $images = array();
    if(empty($attr_value) || !isset($attr_value)){

        $product_type = WC_Product_Factory::get_product_type($product_id);
        if($product_type == "variable" && !empty($attr)){
            $product = new WC_product($product_id);
            $variation_id = woo_get_product_default_variation_id( $product );
            if($variation_id){
                $variation = wc_get_product( $variation_id );
                if(array_key_exists("attribute_pa_".$attr, $variation->get_variation_attributes())){
                   $attr_value = $variation->get_variation_attributes()["attribute_pa_".$attr];
                   return woo_get_product_variation_thumbnails($product_id, $attr, $attr_value, $size);
                }else{
                    return array();
                }
            }else{
                return woo_get_product_variations_thumbnails($product_id, $attr, $size);
            }
        }else{
            $images = woo_get_product_gallery($product_id);
        }

    }else{

        $product = new WC_Product_Variable( $product_id );
        $variations = $product->get_available_variations();
        foreach ( $variations as $variation ) {
            if( $variation["attributes"]["attribute_pa_".$attr] == $attr_value){
                $image_id = $variation["image_id"];
                $image = wp_get_attachment_image_src($image_id, $size);
                $images[] = $image[0];
                $image_ids = get_post_meta( $variation["variation_id"], '_wc_additional_variation_images', true );
                if($image_ids){
                   $image_ids = array_filter( explode( ',', $image_ids ) );
                   //error_log(print_r($image_ids, true));
                   foreach($image_ids as $image_id){
                      $images[] = wp_get_attachment_image_src( $image_id, $size )[0];
                   }
                   //error_log(print_r($images, true));
                }
            }
        }

    }
    if(count($images)==0){
        $images[] = get_template_directory()."/product-image-default.png";
    }
    return $images;  
}

/*
function woo_get_product_single_variation_thumbnails($variation_id){
    $images = array();
    $variation = wc_get_product( $variation_id );

    //print_r( $variation->get_variation_attributes());
    $images[] = $variation['image']['url'];
    $image_ids = get_post_meta( $variation_id, '_wc_additional_variation_images', true );
    if($image_ids){
        $image_ids = array_filter( explode( ',', $image_ids ) );
        foreach($image_ids as $image_id){
            $images[] = wp_get_attachment_image_src( $image_id, 'full' )[0];
        }
    }
    return $images;  
}
*/

function woo_get_product_default_variation_id( $product ) {
    if( method_exists( $product, 'get_default_attributes' ) ) {
        $attributes = $product->get_default_attributes();
    } else {
        $attributes = $product->get_variation_default_attributes();
    }
    foreach( $attributes as $key => $value ) {
        if( strpos( $key, 'attribute_' ) === 0 ) {
            continue;
        }
        unset( $attributes[ $key ] );
        $attributes[ sprintf( 'attribute_%s', $key ) ] = $value;
    }
    if( class_exists('WC_Data_Store') ) {
        $data_store = WC_Data_Store::load( 'product' );
        return $data_store->find_matching_product_variation( $product, $attributes );
    } else {
        return $product->get_matching_variation( $attributes );
    }
}



function get_best_selling_products( $limit = '-1' ){
    global $wpdb;

    $limit_clause = intval($limit) <= 0 ? '' : 'LIMIT '. intval($limit);
    $curent_month = date('Y-m-01 00:00:00');

    return (array) $wpdb->get_results("
        SELECT p.ID as id, COUNT(oim2.meta_value) as count
        FROM {$wpdb->prefix}posts p
        INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim
            ON p.ID = oim.meta_value
        INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim2
            ON oim.order_item_id = oim2.order_item_id
        INNER JOIN {$wpdb->prefix}woocommerce_order_items oi
            ON oim.order_item_id = oi.order_item_id
        INNER JOIN {$wpdb->prefix}posts as o
            ON o.ID = oi.order_id
        WHERE p.post_type = 'product'
        AND p.post_status = 'publish'
        AND o.post_status IN ('wc-prodcessing','wc-completed')
        /*AND o.post_date >= '$curent_month'*/
        AND oim.meta_key = '_product_id'
        AND oim2.meta_key = '_qty'
        GROUP BY p.ID
        ORDER BY COUNT(oim2.meta_value) + 0 DESC
        $limit_clause
    ");
}
//print_r(get_best_selling_products(3));






//returns min max prices from all products
//main query içindeki urunlerin min ve max'ını alır
function get_filtered_price() {
    global $wpdb;

    $args       = wc()->query->get_main_query();

    $tax_query  = isset( $args->tax_query->queries ) ? $args->tax_query->queries : array();
    $meta_query = isset( $args->query_vars['meta_query'] ) ? $args->query_vars['meta_query'] : array();

    foreach ( $meta_query + $tax_query as $key => $query ) {
        if ( ! empty( $query['price_filter'] ) || ! empty( $query['rating_filter'] ) ) {
            unset( $meta_query[ $key ] );
        }
    }

    $meta_query = new \WP_Meta_Query( $meta_query );
    $tax_query  = new \WP_Tax_Query( $tax_query );

    $meta_query_sql = $meta_query->get_sql( 'post', $wpdb->posts, 'ID' );
    $tax_query_sql  = $tax_query->get_sql( $wpdb->posts, 'ID' );

    $sql  = "SELECT min( FLOOR( price_meta.meta_value ) ) as min_price, max( CEILING( price_meta.meta_value ) ) as max_price FROM {$wpdb->posts} ";
    $sql .= " LEFT JOIN {$wpdb->postmeta} as price_meta ON {$wpdb->posts}.ID = price_meta.post_id " . $tax_query_sql['join'] . $meta_query_sql['join'];
    $sql .= "   WHERE {$wpdb->posts}.post_type IN ('product')
            AND {$wpdb->posts}.post_status = 'publish'
            AND price_meta.meta_key IN ('_price')
            AND price_meta.meta_value > '' ";
    $sql .= $tax_query_sql['where'] . $meta_query_sql['where'];

    $search = \WC_Query::get_main_search_query_sql();
    if ( $search ) {
        $sql .= ' AND ' . $search;
    }

    $prices = $wpdb->get_row( $sql ); // WPCS: unprepared SQL ok.

    return array(
        'min' => floor( $prices->min_price ),
        'max' => ceil( $prices->max_price )
    );
}



function get_orders_by_product_id( $product_id, $order_status = array( 'wc-completed' ) ){
    global $wpdb;
    $results = $wpdb->get_col("
        SELECT order_items.order_id
        FROM {$wpdb->prefix}woocommerce_order_items as order_items
        LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta ON order_items.order_item_id = order_item_meta.order_item_id
        LEFT JOIN {$wpdb->posts} AS posts ON order_items.order_id = posts.ID
        WHERE posts.post_type = 'shop_order'
        AND posts.post_status IN ( '" . implode( "','", $order_status ) . "' )
        AND order_items.order_item_type = 'line_item'
        AND order_item_meta.meta_key = '_product_id'
        AND order_item_meta.meta_value = '$product_id'
    ");
    return $results;
}

function get_product_by_order_id($order_id){
    $order = wc_get_order( $order_id );
    $items = $order->get_items();
    $product_id = "";
    if($items){
        foreach ( $items as $item ) {
            $product_id = $item->get_product_id();
            $product_variation_id = $item->get_variation_id();
            break;
        }
    }
    return $product_id;
}

function get_product_data_by_order_id($order_id){
    $order = wc_get_order( $order_id );
    $items = $order->get_items();
    $products = array();
    if($items){
        foreach ( $items as $item ) {
            $product_id = $item->get_product_id();
            $title = $item->get_name();
            $quantity = $item->get_quantity();
            $total = $item->get_total();
            $products[] = array(
                "id" => $product_id,
                "title" => $title,
                "quantity" => $quantity,
                "price"    => $total
            );
        }
    }
    return $products;
}

function get_products_by_order_id($order_id){
    $order = wc_get_order( $order_id );
    $items = $order->get_items();
    $products = array();
    if($items){
        foreach ( $items as $item ) {
            $product_id = $item->get_product_id();
            //$product_variation_id = $item->get_variation_id();
            //break;
            $products[] = $product_id;
        }
    }
    return $products;
}

function get_orders_ids_by_product_id( $product_id ) {
    global $wpdb;
    
    // Define HERE the orders status to include in  <==  <==  <==  <==  <==  <==  <==
    $orders_statuses = "'wc-completed', 'wc-processing', 'wc-on-hold', 'wc-partially-paid', 'wc-pending'";

    # Get All defined statuses Orders IDs for a defined product ID (or variation ID)
    return $wpdb->get_col( "
        SELECT DISTINCT woi.order_id
        FROM {$wpdb->prefix}woocommerce_order_itemmeta as woim, 
             {$wpdb->prefix}woocommerce_order_items as woi, 
             {$wpdb->prefix}posts as p
        WHERE  woi.order_item_id = woim.order_item_id
        AND woi.order_id = p.ID
        AND p.post_status IN ( $orders_statuses )
        AND woim.meta_key IN ( '_product_id', '_variation_id' )
        AND woim.meta_value LIKE '$product_id'
        ORDER BY woi.order_item_id DESC"
    );
}


function get_partial_payments_by_order_id($order_id){
    $order = wc_get_order($order_id);
    $payments_data = array();

    if ($order && $order->get_type() !== 'wcdp_payment') {
        $payment_schedule = $order->get_meta('_wc_deposits_payment_schedule', true);
    }

    if (empty($payment_schedule)) {

    } else {

            foreach ($payment_schedule as $timestamp => $payment) {

                $title = '';
                if (isset($payment['title'])) {
                    $title = $payment['title'];
                } else {
                    if (!is_numeric($timestamp)) {
                        if ($timestamp === 'unlimited') {
                            $title = __('Second Payment', 'woocommerce-deposits');
                        } elseif ($timestamp === 'deposit') {
                            $title = __('Deposit', 'woocommerce-deposits');
                        } else {
                            $title = $timestamp;
                        }
                    } else {
                        $title = date('Y-M-d', $timestamp);
                    }
                }
                $title = apply_filters('wc_deposits_partial_payment_title', $title, $payment);

                $payment_order = false;
                if (isset($payment['id']) && !empty($payment['id'])) $payment_order = wc_get_order($payment['id']);
                if(!$payment_order) continue;
                $gateway = $payment_order ? $payment_order->get_payment_method_title() : '-';
                $payment_id = $payment_order ? $payment_order->get_order_number() : '-';
                $status_value = $payment_order->get_status();
                $status = $payment_order ? wc_get_order_status_name($status_value ) : '-';
                $amount = $payment_order ? $payment_order->get_total() : $payment['total'];
                $date = $payment_order ? $payment_order->get_date_paid() : $payment['date_paid'];
                $billing = $order->get_data()["billing"];
                $price_args = array('currency' => $payment_order->get_currency());

                /*$actions = array();
                if ($payment_order) {
                    $actions['view'] = '<a class="button btn" href="';
                    $actions['view'] .= $payment_order ? esc_url($payment_order->get_edit_order_url()) . '">' : '#">';
                    $actions['view'] .= __('View', 'woocommerce-deposits') . '</a>';
                }

                $actions = apply_filters('wc_deposits_admin_partial_payment_actions', $actions, $payment_order, $order->get_id());
                */
                $actions = array();
                if(strtolower($status_value)  == "completed"){
                    $actions = wc_get_account_orders_actions($order);
                }

                $payments_data[] = array(
                    "title"  => $title,
                    "id"     => $payment_id,
                    "method" => $gateway,
                    "status" => $status,
                    "amount"  => wc_price($amount, $price_args),
                    "amount_price"  => $amount,
                    "date"    => $date,
                    "billing" => $billing,
                    "actions" => $actions
                );
            }
    }
    return $payments_data;
}

function get_payments_by_order_id( $order_id ){
       $order = wc_get_order( $order_id );
       $order_data = $order->get_data(); // The Order data

       //print_r($order);
       //$user = $order->get_user();
       $order_id = $order_data['id'];
       $order_parent_id = $order_data['parent_id'];
       $order_status = $order_data['status'];
       $order_currency = $order_data['currency'];
       $order_version = $order_data['version'];
       $order_payment_method = $order_data['payment_method'];
       $order_payment_method_title = $order_data['payment_method_title'];
       $order_date = $order_data['date_paid'];

       $order_discount_total = $order_data['discount_total'];
       $order_discount_tax = $order_data['discount_tax'];
       $order_shipping_total = $order_data['shipping_total'];
       $order_shipping_tax = $order_data['shipping_tax'];
       $order_total = $order_data['total'];
       $order_total_tax = $order_data['total_tax'];
       $order_customer_id = $order_data['customer_id']; // ... and so on

       ## BILLING INFORMATION:

       $order_billing_first_name = $order_data['billing']['first_name'];
       $order_billing_last_name = $order_data['billing']['last_name'];
       $order_billing_company = $order_data['billing']['company'];
       $order_billing_address_1 = $order_data['billing']['address_1'];
       $order_billing_address_2 = $order_data['billing']['address_2'];
       $order_billing_city = $order_data['billing']['city'];
       $order_billing_state = $order_data['billing']['state'];
       $order_billing_postcode = $order_data['billing']['postcode'];
       $order_billing_country = $order_data['billing']['country'];
       $order_billing_email = $order_data['billing']['email'];
       $order_billing_phone = $order_data['billing']['phone'];

       ## SHIPPING INFORMATION:

       $order_shipping_first_name = $order_data['shipping']['first_name'];
       $order_shipping_last_name = $order_data['shipping']['last_name'];
       $order_shipping_company = $order_data['shipping']['company'];
       $order_shipping_address_1 = $order_data['shipping']['address_1'];
       $order_shipping_address_2 = $order_data['shipping']['address_2'];
       $order_shipping_city = $order_data['shipping']['city'];
       $order_shipping_state = $order_data['shipping']['state'];
       $order_shipping_postcode = $order_data['shipping']['postcode'];
       $order_shipping_country = $order_data['shipping']['country'];

       $price_args = array('currency' => $order_currency );

       $actions = isset($order_data['actions'])?$order_data['actions']:array();//array();
       //if(!$actions){
            if(strtolower($order_status) == "completed"){
                $actions = wc_get_account_orders_actions($order);
            }
       //}

       $payments_data[] = array(
                        "title"   => "Payment",
                        "id"      => $order_id,
                        "method"  => $order_payment_method_title,
                        "status"  => $order_status,
                        "amount"  => wc_price($order_total, $price_args),
                        "amount_price"  => $order_total,
                        "date"    => $order_date,
                        "billing" => $order_data['billing'],
                        "actions" => $actions
        );
       return $payments_data;
}


function get_product_payments($product_id){
    $payments = array();
    $deposit_enabled = get_post_meta( $product_id, '_wc_deposits_enable_deposit');
    if($deposit_enabled){
       $deposit_enabled = $deposit_enabled[0];
    }
    $orders = get_orders_ids_by_product_id($product_id);
    if($orders){
       $order_id = $orders[0];
       if($deposit_enabled == "yes"){
           $payments = get_partial_payments_by_order_id($order_id);
       }else{
           $payments = get_payments_by_order_id($order_id);
       }
    }
    return $payments;
}


function product_deposit_payment_is_complete($product_id){
    $result = 0;
    $payments = get_product_payments($product_id);
    if($payments){
        foreach($payments as $payment){
            if(strtolower($payment['status']) ==  'completed'){
               $result = 1;
               break;
            }
        }
    }
    return $result;
}


function product_payment_is_complete($product_id, $forced=false){ //forced tamamı odenmeiş ve admin onaylı demek.
    $result = 1;
    $payments = get_product_payments($product_id);
    if($payments){
        foreach($payments as $payment){
           if(strtolower($payment['status']) !=  'completed'){
             $result = 0;
             break;
           }
        }
        if($forced){
           $order_id = get_orders_ids_by_product_id($product_id)[0];
           $order_status = get_payments_by_order_id( $order_id );
           if(strtolower($order_status[0]["status"]) != 'completed'){
              $result = 0;
           }
        }
    }else{
        $result = 0;
    }
    return $result;
}






function get_continents(){
    $data = array();
    $WC_Countries = new WC_Countries();
    $continent_list = $WC_Countries->get_continents();
    foreach ($continent_list as $key => $continent) {
        if($key != "AN"){
            $continent = array(
                "name" => $continent["name"],
                "slug" => $key
            );
            $data[] = $continent;            
        }
    }
    return $data;
}

/*
function wc_get_country_name($short_name){
   if(!empty($short_name)){
      return WC()->countries->countries[$short_name];
   }
}*/

function wc_get_base_country(){
    $WC_Countries = new WC_Countries();
    return $WC_Countries->get_base_country();
}

function wc_get_base_city(){
    $WC_Countries = new WC_Countries();
    return $WC_Countries->get_base_city();
}


function role_based_price($user, $post){
    $prices = array();
    if($post->_role_based_price){
       $role = empty($user->roles)?"loggedout":array_keys($user->roles)[0];
       if(array_key_exists($role, $post->_role_based_price)){
          $prices=$post->_role_based_price[$role];
       }
    }
    return $prices;
}

/*
function get_districts($state){
    $districts = In_Class_Il_Ilce_Districts::get_TR_districts();
    return $districts[$state];
}
*/










function refund_order_by_id($order_id) {
    $response = array(
       "error" => false,
       "message" => ""
    );
    // Önce WooCommerce ödeme sınıfını dahil edelim
    if ( ! class_exists( 'WC_Order' ) ) {
        return; // WooCommerce yüklü değilse fonksiyonu sonlandır
    }

    // Order objesini oluşturalım
    $order = wc_get_order( $order_id );

    // Eğer order bulunamadıysa veya order iptal edilmişse işlemi sonlandıralım
    if ( ! is_a( $order, 'WC_Order' ) || $order->get_status() === 'cancelled' ) {
        $response["error"] = true;
        $response["message"] = "Order not found or already canceled.";
        return $response;
    }

    // Daha önce geri ödeme yapılmadığından emin olalım
    if ( ! $order->get_meta( '_refund_total', true ) ) {
        // Order tutarını geri ödeyelim
        $refund_amount = $order->get_total();
        $refund_reason = 'İade talebi alındı.';

        // Geri ödeme işlemini oluşturalım
        $refund_id = wc_create_refund(array(
            'amount'   => $refund_amount,
            'reason'   => $refund_reason,
            'order_id' => $order_id,
        ));

        // Geri ödeme işlemi başarılıysa, order'ın durumunu güncelleyelim
        if ( is_wp_error( $refund_id ) ) {
            // Geri ödeme işlemi başarısız oldu
            // Hata yönetimi burada yapılabilir.
            $response["error"] = true;
            $response["message"] = "The refund process has failed.";
            return $response;
        } else {
            // Geri ödeme başarılı
            $order->update_status('refunded', __('Geri ödeme yapıldı', 'woocommerce'));
            $response["message"] = "Payment refunded successfully!";
        }
    }else{
        $response["message"] = "Refund has already been issued before.";
    }
    return $response;
}

//alıcı
function get_user_orders_by_user_id($user_id, $query=false) {
    // Önce WooCommerce ödeme sınıfını dahil edelim
    if ( ! class_exists( 'WC_Order' ) ) {
        return; // WooCommerce yüklü değilse fonksiyonu sonlandır
    }

    $args = array(
        'numberposts' => -1,
        'post_type'   => 'shop_order',
        'post_status' => array( 'wc-completed', 'wc-processing', 'wc-on-hold', 'wc-refunded' ),
        'meta_key'    => '_customer_user',
        'meta_value'  => $user_id,
    );
    
    if(!$query){
        $orders = get_posts( $args );
        $result = array();
        foreach ( $orders as $order ) {
            $order_id      = $order->ID;
            $order         = wc_get_order( $order_id );
            $order_status  = $order->get_status();
            $payment_total = $order->get_total();
            $payment_date  = $order->get_date_paid() ? $order->get_date_paid()->format( 'Y-m-d H:i:s' ) : '';
            $product_id    = $order->get_meta("_product_id");
            
            /*$products      = array();
            foreach ( $order->get_items() as $item_id => $item ) {
                $products[] = $item->get_product_id();
            }*/

            $result[] = array(
                'id'         => $order_id,
                'total'      => $payment_total,
                'date'       => $payment_date,
                'status'     => $order_status,
                "product_id" => $product_id
            );
        }
    }else{
        $result = $args;
    }


    return $result;
}

//satıcı
function get_orders_by_user_products($user_id, $query=false) {
    // Önce WooCommerce ödeme sınıfını dahil edelim
    if ( ! class_exists( 'WC_Order' ) ) {
        return; // WooCommerce yüklü değilse fonksiyonu sonlandır
    }

    $args = array(
            'numberposts' => -1,
            'post_type'   => 'shop_order',
            'post_status' => array( 'wc-completed', 'wc-processing', 'wc-on-hold', 'wc-refunded' ),
            'meta_query'  => array(
                array(
                    'key'     => '_product_author_id',
                    'value'   => $user_id,
                    'compare' => '='
                )
            )
    );
    
    if(!$query){
        $orders = get_posts( $args );
        $result = array();
        foreach ( $orders as $order ) {
                $order_id      = $order->ID;
                $order         = wc_get_order( $order_id );
                $order_status  = $order->get_status();
                $payment_total = $order->get_total();
                $payment_date  = $order->get_date_paid() ? $order->get_date_paid()->format( 'Y-m-d H:i:s' ) : '';
                $product_id    = $order->get_meta("_product_id");
                
                /*$products      = array();
                foreach ( $order->get_items() as $item_id => $item ) {
                    $products[] = $item->get_product_id();
                }*/

                $result[] = array(
                    'îd'         => $order_id,
                    'total'      => $payment_total,
                    'date'       => $payment_date,
                    'status'     => $order_status,
                    "product_id" => $product_id 
                );
        }        
    }else{
        $result = $args;
    }
    return $result;
}

//satıcının toplam kazancı
function get_user_total_income($user_id) {
    // Önce WooCommerce ödeme sınıfını dahil edelim
    if ( ! class_exists( 'WC_Order' ) ) {
        return 0; // WooCommerce yüklü değilse, toplam geliri 0 olarak döndürelim
    }

    $total_income = 0;

    $args = array(
            'numberposts' => -1,
            'post_type'   => 'shop_order',
            'post_status' => array( 'wc-completed', 'wc-refunded' ),
            'meta_query'  => array(
                array(
                    'key'     => '_product_author_id',
                    'value'   => $user_id,
                    'compare' => '='
                ),
                /*array(
                    'key'     => '_product_id',
                    'value'   => $product->ID,
                    'compare' => '='
                )*/
            )
    );

    $orders = get_posts( $args );

    foreach ( $orders as $order ) {
            $order_id      = $order->ID;
            $order         = wc_get_order( $order_id );
            $order_status  = $order->get_status();
            $payment_total = $order->get_total();

            // Refund işlemi varsa, total gelirden düşelim
            if ( $order_status === 'refunded' ) {
                $refunded_amount = $order->get_total_refunded();
                $total_income -= $refunded_amount;
            }

            // Toplam geliri artıralım
            $total_income += $payment_total;
    }

    return $total_income;
}

//alıcının toplam harcaması
function get_user_total_expenditure($user_id) {
    // Önce WooCommerce ödeme sınıfını dahil edelim
    if ( ! class_exists( 'WC_Order' ) ) {
        return 0; // WooCommerce yüklü değilse, toplam harcama tutarını 0 olarak döndürelim
    }

    // Kullanıcının yaptığı orderları çekelim
    $args = array(
        'numberposts' => -1,
        'post_type'   => 'shop_order',
        'post_status' => array( 'wc-completed', 'wc-processing', 'wc-on-hold', 'wc-refunded' ),
        'meta_key'    => '_customer_user',
        'meta_value'  => $user_id,
    );

    $orders = get_posts( $args );

    // Toplam harcama tutarını saklayacak bir değişken oluşturalım
    $total_expenditure = 0;

    foreach ( $orders as $order ) {
        $order_id      = $order->ID;
        $order         = wc_get_order( $order_id );
        $order_status  = $order->get_status();
        $payment_total = $order->get_total();

        // Refund işlemi varsa, toplam harcama tutarından düşelim
        if ( $order_status === 'refunded' ) {
            $refunded_amount = $order->get_total_refunded();
            $total_expenditure -= $refunded_amount;
        }

        // Toplam harcama tutarını artıralım
        $total_expenditure += $payment_total;
    }

    return $total_expenditure;
}






function user_order_count($user_id=0, $user_meta_key="_customer_user", $status=array()){
    global $wpdb;
    $query = "SELECT COUNT(ID) as count
        FROM {$wpdb->prefix}posts
        WHERE post_type = 'shop_order' ";
        if($status){
            $query .= ' AND (';
            foreach($status as $key => $item){
               $query .=  " post_status = 'wc-".$item."'";
               if($key < count($status)-1){
                $query .=  " or ";
               }
            }
            $query .= ")";
        }
    $query .=" AND ID IN (
                    SELECT post_id
                    FROM {$wpdb->prefix}postmeta
                    WHERE meta_key = '$user_meta_key' AND meta_value = ".$user_id.")";
    return $wpdb->get_var($query);
}



function woo_get_all_product_attributes(){
    $attrs = wc_get_attribute_taxonomies();
    $attributes = array();
    if($attrs){
        foreach($attrs as $attr){
            $attributes[] = $attr->attribute_name;
        }
    }
    return $attributes;
}


function woo_url_pa_parse($product, $variation="") {
    $arr = array();
    if(!empty($variation)){
        $product_attributes = woo_get_all_product_attributes();
        foreach($product_attributes as $attr){
            $variation = str_replace("-".$attr."-", ",".$attr.":", $variation);
            $variation = str_replace($attr."-", $attr.":", $variation);
        }
        $variation = explode(",", $variation);
        
        foreach($variation as $var){
            $var = explode(":", $var);
            $arr[$var[0]] = $var[1];
        }        
    }else{
        $product_attributes = $product->get_default_attributes();
        foreach($product_attributes as $key => $attr){
           $arr[str_replace("pa_", "", $key)] = $attr;
        }
    }
    return $arr;
}

function get_variation_id_by_attribute($product_id, $attribute_name, $attribute_slug) {
    global $wpdb;
    $variation_id = $wpdb->get_var($wpdb->prepare(
        "SELECT ID
        FROM {$wpdb->prefix}posts
        WHERE post_type = 'product_variation'
        AND post_parent = %d
        AND ID IN (
            SELECT tr.object_id
            FROM {$wpdb->prefix}term_relationships tr
            JOIN {$wpdb->prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            JOIN {$wpdb->prefix}terms t ON tr.term_taxonomy_id = t.term_id
            WHERE tt.taxonomy = %s
            AND t.slug = %s
        )
        LIMIT 1",
        $product_id,
        'pa_' . $attribute_name,
        $attribute_slug
    ));
    return $variation_id;
}




function get_add_to_cart_attrs($product_id = 0){
    $product = wc_get_product($product_id);
    $data = array(
        "quantity"     => 1,
        "product_id"   => $product->get_id(),
        "variation_id" => woo_get_product_default_variation_id($product)
    );
    /*foreach($product->get_default_attributes() as $key => $attribute){
        $data["attribute_".$key] = $attribute;
    }*/

    $attrs = "";
    foreach($data as $key => $value){
        $attrs .= "data-".$key."='".$value."' ";
    }
    return $attrs;
}






















//remove woocommerce default css
//add_filter( 'woocommerce_enqueue_styles', '__return_false' );

function woo_dequeue_select2() {

        wp_dequeue_style( 'select2' );
        wp_deregister_style( 'select2' );

        wp_dequeue_script( 'selectWoo');
        wp_deregister_script('selectWoo');

}
//add_action( 'wp_enqueue_scripts', 'woo_dequeue_select2', 100 );


//change quantity settings
add_filter( 'woocommerce_quantity_input_args', 'bloomer_woocommerce_quantity_changes', 10, 2 );
function bloomer_woocommerce_quantity_changes( $args, $product ) {
	$user = wp_get_current_user();
	$role = empty($user->roles)?"loggedout":$user->roles[0];
	$role_based_quantity =  QueryCache::get_cached_option('role_based_quantity');//get_field('role_based_quantity', 'option');
	$has_quantity_settings = false;
	if($role_based_quantity){
		foreach($role_based_quantity as $role_base){
			if(in_array($role, array_values($role_base["role"]))){
				if($role_base["quantity_min"]>0){
		            $quantity_min = $role_base["quantity_min"];
		        }
		        if($role_base["quantity_max"]>0){
		            $quantity_max = $role_base["quantity_max"];
		        }
		        if($role_base["quantity_step"]>0){
		            $quantity_step = $role_base["quantity_step"];
			    }
			    $has_quantity_settings = true;
			    break;
			}
		}
	}
	if($has_quantity_settings){
		$stock_quantity = $product->get_stock_quantity();
		
	    if(!isset($quantity_min) || $quantity_min > $stock_quantity){
	       $quantity_min = 1;
	    }
	    if(!isset($quantity_max) || $quantity_max > $stock_quantity){
	       $quantity_max = $stock_quantity;
	    }
	    if(!isset($quantity_step) || $quantity_step > $stock_quantity){
	       $quantity_step = 1;
	    }

		if ( ! is_cart() ) {
		      /*$args['input_value'] = $quantity_min;//4; // Start from this value (default = 1) 
		      $args['max_value'] = $stock_quantity; // Max quantity (default = -1)
		      $args['min_value'] = $quantity_min;//4; // Min quantity (default = 0)
		      $args['step'] = $quantity_step;//2; // Increment/decrement by this value (default = 1)*/
		       // $args['input_value'] = 9;	// Starting value
	         $args['max_value'] = $quantity_max; 	// Maximum value
	         $args['min_value'] = $quantity_min; 
	         $args['step'] 	 = $quantity_step;//$quantity_step;// $quantity_step ;   // Quantity steps
		} else {
		      // Cart's "min_value" is already 0 and we don't need "input_value"
		      $args['max_value'] = $quantity_max; // Max quantity (default = -1)
		      $args['step'] =  $quantity_step;//$quantity_step;//2; // Increment/decrement by this value (default = 0)
		      // ONLY ADD FOLLOWING IF STEP < MIN_VALUE
		      $args['min_value'] =  $quantity_min;//$quantity_min;//4; // Min quantity (default = 0)
	    }

	}
    return $args;
}

function wc_get_product_by_variation_sku($sku) {
    $args = array(
        'post_type'  => array('product','product_variation'),
        'order' => 'ASC',
		'orderby' => 'title',
		'posts_per_page' => 10,
		'numberposts' => 10,
        'meta_query' => array(
            array(
                'key'   => '_sku',
                'value' => $sku,
            )
        )
    );
    // Get the posts for the sku
    $posts = get_posts( $args);
    if ($posts) {
        return $posts[0]->post_parent;
    } else {
        return false;
    }
}

function wc_get_products_by_variation_sku($sku) {
    $args = array(
        'post_type'  => array('product','product_variation'),
        'order' => 'ASC',
		'orderby' => 'title',
		'posts_per_page' => 10,
		'numberposts' => 10,
        'meta_query' => array(
            array(
                'key'   => '_sku',
                'value' => $sku,
            )
        )
    );
    // Get the posts for the sku
    $posts = get_posts( $args);
    if ($posts) {
        return $posts;//[0]->post_parent;
    } else {
        return false;
    }
}

function sku_where( $where ) {
							global $wpdb, $wp;
							$search_ids = array();
						    $terms = explode(',', $GLOBALS["keyword"] );
						    foreach ($terms as $term) {
						        //Include search by id if admin area.
						        if (is_admin() && is_numeric($term)) {
						            $search_ids[] = $term;
						        }
						        // search variations with a matching sku and return the parent.
						        $sku_to_parent_id = $wpdb->get_col($wpdb->prepare("SELECT p.post_parent as post_id FROM {$wpdb->posts} as p join {$wpdb->postmeta} pm on p.ID = pm.post_id and pm.meta_key='_sku' and pm.meta_value LIKE '%%%s%%' where p.post_parent <> 0 group by p.post_parent", wc_clean($term)));
						        //Search a regular product that matches the sku.
						        $sku_to_id = $wpdb->get_col($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_sku' AND meta_value LIKE '%%%s%%';", wc_clean($term)));
						        $search_ids = array_merge($search_ids, $sku_to_id, $sku_to_parent_id);
						    }
						    $search_ids = array_filter(array_map('absint', $search_ids));
						    if (sizeof($search_ids) > 0) {
						        $where = str_replace(')))', ") OR ({$wpdb->posts}.ID IN (" . implode(',', $search_ids) . "))))", $where);
						    }
						    return $where;
}

/*function variation_archive_hide_parent($where){
	if(get_option('gmwsvs_hide_parent_product') =='yes' && empty($GLOBALS["keyword"])){
		global $wpdb;
		$where .= " AND  0 = (select count(*) as totalpart from {$wpdb->posts} as oc_posttb where oc_posttb.post_parent = {$wpdb->posts}.ID and oc_posttb.post_type= 'product_variation') ";
	}
	return $where;
}
function variation_archive_woocommerce_product_query ($q) {
		$q->set( 'post_type', array('product','product_variation') );
		$q->set( 'gmwsvsfilter', 'yes' );
		$meta_query = (array) $q->get( 'meta_query' );
		$meta_query[] = array(
								'relation' => 'OR',
								array(
											'key' => '_wwsvsc_exclude_product_parent',
											'value' => 'yes',
											'compare' => 'NOT EXISTS'
										),
								array(
											'key' => '_wwsvsc_exclude_product_parent',
											'value' => 'yes',
											'compare' => '!=',
										),
							);
		$q->set( 'meta_query', $meta_query );
		return $q;
}
function variation_archive_posts_clauses($clauses, $query) {
		global $wpdb;
		//if(array_key_exists("gmwsvsfilter", $query->query_vars)){
			if($query->query_vars['gmwsvsfilter']=='yes'){
				if(get_option('gmwsvs_hide_parent_product')=='yes'){
					$clauses['where'] .= " AND  0 = (select count(*) as totalpart from {$wpdb->posts} as oc_posttb where oc_posttb.post_parent = {$wpdb->posts}.ID and oc_posttb.post_type= 'product_variation') ";
				}
				$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} as  oc_posttba ON ({$wpdb->posts}.post_parent = oc_posttba.post_id AND oc_posttba.meta_key = '_wwsvsc_exclude_product_single' )";
				$clauses['where'] .= " AND  ( oc_posttba.meta_value IS NULL OR oc_posttba.meta_value!='yes') ";
				$gmwsvs_exclude_cat = array();
		    	$gmwsvs_exclude_cat = get_option('gmwsvs_exclude_cat');
		    	if(!empty($gmwsvs_exclude_cat)){
		    		$clauses['where'] .= " AND ( ({$wpdb->posts}.post_type='product_variation' AND {$wpdb->posts}.ID NOT IN ( SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN (".implode(",",$gmwsvs_exclude_cat).") )) OR  {$wpdb->posts}.post_type='product') ";
		    	}
			}
		//}
		return $clauses;
}*/





function set_price_html( $regular_price, $sale_price ) {
	$currency = get_woocommerce_currency();
	$has_discount = false;
	$overstroke = array("","");
	if($sale_price>0 && $sale_price != $regular_price){
       $has_discount = true;
       $overstroke = array('<del aria-hidden="true">','</del>');
	}
    $html = '<div class="price">';
        if($regular_price>0){
        	$html .= $overstroke[0].'<span class="woocommerce-Price-amount amount"><bdi>'.woo_get_currency_with_price($regular_price, $currency).'</bdi></span>'.$overstroke[1];
        }
        if($has_discount){
            $html .= '<ins><span class="woocommerce-Price-amount amount"><bdi>'.woo_get_currency_with_price($sale_price, $currency).'</bdi></span></ins>';
        }
    $html .= '</div>';
    return $html;
}

function variable_product_price($product){
	if(is_int($product)){
	   $product = wc_get_product($product);	
	}
	$regular_price =$product->get_variation_regular_price("min");
    $sale_price = $product->get_variation_sale_price("min");
    return set_price_html( $regular_price, $sale_price );
}




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