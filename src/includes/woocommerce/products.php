<?php


function _get_price($post){
    return $post->get_price_html();
}



//last viewed products
function custom_track_product_view() {
    if ( ! is_singular( 'product' ) ) {
        return;
    }
    global $post;
    if ( empty( $_COOKIE['woocommerce_recently_viewed'] ) ){
        $viewed_products = array();
    }else{
        $viewed_products = (array) explode( '|', $_COOKIE['woocommerce_recently_viewed'] );
    }
    if ( ! in_array( $post->ID, $viewed_products )  && !has_term( get_option( 'default_product_cat' ), 'product_cat', $post_id ) ) {
        $viewed_products[] = $post->ID;
    }
    if ( count( $viewed_products ) > 10 ) {
        array_shift( $viewed_products );
    }
    wc_setcookie( 'woocommerce_recently_viewed', implode( '|', $viewed_products ) );
}
if(is_user_logged_in()){
   //add_action( 'template_redirect', 'custom_track_product_view', 20 );
}

function custom_track_product_view_js($post_id) {
    if ( empty( $_COOKIE['woocommerce_recently_viewed'] ) ){
        $viewed_products = array();
    }else{
        $viewed_products = (array) explode( '|', $_COOKIE['woocommerce_recently_viewed'] );
    }
    if ( ! in_array( $post_id, $viewed_products ) && !has_term( get_option( 'default_product_cat' ), 'product_cat', $post_id ) ) {
        $viewed_products[] = $post_id;
    }
    if ( count( $viewed_products ) > 10 ) {
        array_shift( $viewed_products );
    }
    wc_setcookie( 'woocommerce_recently_viewed', implode( '|', $viewed_products ) );
}



add_shortcode( 'recently_viewed_products', 'salt_recently_viewed_shortcode' );
function salt_recently_viewed_shortcode() {
   $viewed_products = ! empty( $_COOKIE['woocommerce_recently_viewed'] ) ? (array) explode( '|', wp_unslash( $_COOKIE['woocommerce_recently_viewed'] ) ) : array();
   $viewed_products = array_reverse( array_filter( array_map( 'absint', $viewed_products ) ) );
   if ( empty( $viewed_products ) ) return;
   $title = '<h3>Recently Viewed Products</h3>';
   $product_ids = implode( ",", $viewed_products );
   return $title . do_shortcode("[products ids='$product_ids']");
}

function salt_recently_viewed_products() {
   $viewed_products = ! empty( $_COOKIE['woocommerce_recently_viewed'] ) ? (array) explode( '|', wp_unslash( $_COOKIE['woocommerce_recently_viewed'] ) ) : array();
   $viewed_products = array_reverse( array_filter( array_map( 'absint', $viewed_products ) ) );
   if ( empty( $viewed_products ) ) return;
   //$product_ids = implode( ",", $viewed_products );
   return $viewed_products;
}







//apply yith discount plugin prices to post object
function woo_product_discount_update($product){
/*
    if ( defined( 'YITH_YWDPD_DIR' ) ) {
        $product_item = new WC_Product($product->id);
        $product_type = WC_Product_Factory::get_product_type($product_item->get_id());
        $product->_regular_price = "";
        $product->_sale_price = "";
        $product->_price = "";
        $currency = get_woocommerce_currency_symbol();

        if($product_type == 'simple' ){
            $product_price = $product_item->get_price_html();
           if(!empty($product_price)){
                $html = str_get_html($product_price);
                $del = $html->find("del", 0);
                if (is_object($del)) {
                    $product->_regular_price = floatval(str_replace(",", ".", str_replace(".", "", str_replace($currency, "", $del->find(".amount", 0)->innertext))));
                    $product->_sale_price    = floatval(str_replace(",", ".", str_replace(".", "", str_replace($currency, "", $html->find(".amount", 1)->innertext))));
                    $product->_price         = $product->_sale_price;
                }else{
                    $product->_regular_price = floatval(str_replace(",", ".", str_replace(".", "", str_replace($currency, "", $html->find(".amount", 0)->innertext))));
                    $product->_price         = $product->_regular_price;
                }
            }
        }

        if($product_type == 'variable'){
            $product_data = new WC_Product_Variable($product_item->get_id());
            $product_price = $product_data->get_price_html();
            $product->_price = array();
            if(!empty($product_price)){
                $html = str_get_html($product_price);
                $del = $html->find("del", 0);
                if (is_object($del)) {
                    $product->_price[]       = floatval(str_replace(",", ".", str_replace(".", "", str_replace($currency, "", $html->find(".amount", 2)->innertext))));
                    $product->_price[]       = floatval(str_replace(",", ".", str_replace(".", "", str_replace($currency, "", $html->find(".amount", 3)->innertext))));
                }else{
                    $product->_price[]       = floatval(str_replace(",", ".", str_replace(".", "", str_replace($currency, "", $html->find(".amount", 0)->innertext))));
                    $product->_price[]       = floatval(str_replace(",", ".", str_replace(".", "", str_replace($currency, "", $html->find(".amount", 1)->innertext))));                    
                }
            }
        }
        
        if($product_type == 'grouped'){
            $product_data = new WC_Product_Grouped($product_item->get_id());
            $children = $product_data->get_children();
            if($children){
                $product->_price = array();
                foreach($children as $child){
                     $child_item = new WC_Product($child);
                     $product_price = $child_item->get_price_html();
                     if(!empty($product_price)){
                        $html = str_get_html($product_price);
                        $del = $html->find("del", 0);
                        if (is_object($del)) {
                            $product->_price[]       = floatval(str_replace(",", ".", str_replace(".", "", str_replace($currency, "", $html->find(".amount", 1)->innertext))));
                        }else{
                            $product->_price[]       = floatval(str_replace(",", ".", str_replace(".", "", str_replace($currency, "", $html->find(".amount", 0)->innertext))));                    
                        }

                    }
                } 
                asort($product->_price);  
            }
        }

        if($product_type == "external"){
            $product_data = new WC_Product_External($product_item->get_id());
            $product->_regular_price = $product->_price = $product_data->get_price();
        }

    } */
    return $product;
}



//product badges
function woo_product_badges($product, $free_shipping_min_amount, $types){

    if(isset($types)){
        $types = explode(",",$types);
    }else{
        return;
    }
    $count               = 0;
    $low_stock           = woo_get_product_low_stock_amount($product->id);
    $stock               = round($product->_stock);
    $price               = $product->_price;
    $sale_price          = $product->_sale_price;
    $badges              = "";
    $price_highest       = $price;
    if(is_array($price)){
       $price_highest    = max($price_highest);
    }

    $low_stock_forced = false; 
    if(!empty($GLOBALS['query_vars'])){
        if(in_array("tukenmek-uzere", array_values($GLOBALS['query_vars']))){
            $low_stock_forced = true;
        }else{
            foreach($GLOBALS['query_vars'] as $var){
                if(!empty($var->slug)){
                    if($var->slug == "durum"){
                        if(in_array("tukenmek-uzere", array_values($var["terms"]))){
                            $low_stock_forced = true;
                        }
                    }                
                }
            }          
        }
    }
    
    if($sale_price && in_array("discount",$types)){
        $badges .= '<div class="product-badge discount" title="İndirimde"><i class="icon icon-discount"></i></div>';
        $count++;
    }

    if((($stock >0 && $stock < $low_stock) || $low_stock_forced) && in_array("stock", $types)){
        $badges .= '<div class="product-badge low-stock">'.sprintf(trans('Son %s ürün'), $stock).'</div>';
        $count++;
    }

    if(($price_highest >= $free_shipping_min_amount && !empty($free_shipping_min_amount))  && in_array("shipping", $types)){
        $badges .= '<div class="product-badge free-shipping" title="Ücretsiz Kargo"><i class="icon icon-cargo"></i></div>';
        $count++;
    }
    if($count>0){
        $badges = "<div class='product-badges'>".$badges."</div>";
    }
    return $badges;
}





function max_grouped_price( $price_this_get_price_suffix, $instance, $child_prices ) { 
    return wc_price(max($child_prices)); 
}; 

//add_filter( 'woocommerce_grouped_price_html', 'max_grouped_price', 10, 3 );

/*
function cw_change_product_price_display( $price ) {
        $price .= ' At Each Item Product';
        return $price;
}
add_filter( 'woocommerce_get_price_html', 'cw_change_product_price_display' );
add_filter( 'woocommerce_cart_item_price', 'cw_change_product_price_display' );
*/







//Display variations dropdowns on shop page for variable products
//add_filter( 'woocommerce_loop_add_to_cart_link', 'woo_display_variation_dropdown_on_shop_page' );
function woo_display_variation_dropdown_on_shop_page() {
     
    global $product;
    if( $product->is_type( 'variable' )) {
    
    $attribute_keys = array_keys( $product->get_attributes() );
    ?>
    
    <form class="variations_form cart" method="post" enctype='multipart/form-data' data-product_id="<?php echo absint( $product->id ); ?>" data-product_variations="<?php echo htmlspecialchars( json_encode( $product->get_available_variations() ) ) ?>">
        <?php do_action( 'woocommerce_before_variations_form' ); ?>
    
        <?php if ( empty( $product->get_available_variations() ) && false !== $product->get_available_variations() ) : ?>
            <p class="stock out-of-stock"><?php _e( 'This product is currently out of stock and unavailable.', 'woocommerce' ); ?></p>
        <?php else : ?>
            <table class="variations" cellspacing="0">
                <tbody>
                    <?php foreach ( $product->get_attributes() as $attribute_name => $options ) : ?>
                        <tr>
                            <td class="label"><label for="<?php echo sanitize_title( $attribute_name ); ?>"><?php echo wc_attribute_label( $attribute_name ); ?></label></td>
                            <td class="value">
                                <?php
                                    $selected = isset( $_REQUEST[ 'attribute_' . sanitize_title( $attribute_name ) ] ) ? wc_clean( urldecode( $_REQUEST[ 'attribute_' . sanitize_title( $attribute_name ) ] ) ) : $product->get_variation_default_attribute( $attribute_name );
                                    wc_dropdown_variation_attribute_options( array( 'options' => $options, 'attribute' => $attribute_name, 'product' => $product, 'selected' => $selected ) );
                                    echo end( $attribute_keys ) === $attribute_name ? apply_filters( 'woocommerce_reset_variations_link', '<a class="reset_variations" href="#">' . __( 'Clear', 'woocommerce' ) . '</a>' ) : '';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach;?>
                </tbody>
            </table>
    
            <?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>
    
            <div class="single_variation_wrap">
                <?php
                    /**
                     * woocommerce_before_single_variation Hook.
                     */
                    do_action( 'woocommerce_before_single_variation' );
    
                    /**
                     * woocommerce_single_variation hook. Used to output the cart button and placeholder for variation data.
                     * @since 2.4.0
                     * @hooked woocommerce_single_variation - 10 Empty div for variation data.
                     * @hooked woocommerce_single_variation_add_to_cart_button - 20 Qty and cart button.
                     */
                    do_action( 'woocommerce_single_variation' );
    
                    /**
                     * woocommerce_after_single_variation Hook.
                     */
                    do_action( 'woocommerce_after_single_variation' );
                ?>
            </div>
    
            <?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>
        <?php endif; ?>
    
        <?php do_action( 'woocommerce_after_variations_form' ); ?>
    </form>
        
    <?php } else {
        
    echo sprintf( '<a rel="nofollow" href="%s" data-quantity="%s" data-product_id="%s" data-product_sku="%s" class="%s">%s</a>',
            esc_url( $product->add_to_cart_url() ),
            esc_attr( isset( $quantity ) ? $quantity : 1 ),
            esc_attr( $product->id ),
            esc_attr( $product->get_sku() ),
            esc_attr( isset( $class ) ? $class : 'button' ),
            esc_html( $product->add_to_cart_text() )
        );
    
    };
}

//disable sku usage
//add_filter( 'wc_product_sku_enabled', '__return_false' );

//disable unique sku usage
//add_filter( 'wc_product_has_unique_sku', '__return_false' ); 

/*add_filter( 'woocommerce_ajax_variation_threshold', 'wc_ninja_ajax_threshold' );
function wc_ninja_ajax_threshold() {
    return 150;
}*/



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



// Featured products displaying first on loop
function featured_products_orderby( $orderby, $query ) {
    global $wpdb;

    if ( 'featured_products' == $query->get( 'orderby' ) ) {
        $featured_product_ids = (array) wc_get_featured_product_ids();
        if ( count( $featured_product_ids ) ) {
            $string_of_ids = '(' . implode( ',', $featured_product_ids ) . ')';
            $orderby = "( {$wpdb->posts}.ID IN {$string_of_ids}) " . $query->get( 'order' )." , post_date DESC";
        }
    }
    return $orderby;
}
add_filter( 'posts_orderby', 'featured_products_orderby', 10, 2 );




//remove breadcrumb
remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );

add_filter( 'woocommerce_show_page_title', '__return_false' );
remove_action( 'woocommerce_before_shop_loop', 'wc_print_notices', 10 );
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );
//add_action('woocommerce_before_shop_loop','catalog_wrapper', 2);
function catalog_wrapper(){
    echo '<h1 class="card-title">';
    woocommerce_result_count();
    echo '</h1><div class="action">';
    woocommerce_catalog_ordering();
    echo '</div>';
}







/*
<nav class="pagination-container">
        <ul class="pagination clearfix">
          {% if pagination.prev %}
              <li class="page-item prev {{pagination.prev.link|length ? '' : 'invisible'}}">
                  <a href="{{pagination.prev.link}}" class="page-link" aria-label="Previous">
                    <span aria-hidden="true"></span>
                  </a>
              </li>
          {% endif %}
          {% for page in pagination.pages %}
             <li class="page-item {{page.current ? 'active' : ''}}">
               {% if page.link %}
                  <a href="{{page.link}}" class="page-link {{page.class}}">{{page.title}}</a>
               {% else %}
                  <span class="page-link {{page.class}}">{{page.title}}</span>
               {% endif %}
             </li>
          {% endfor %}
          {% if pagination.next %}
            <li class="page-item next {{pagination.next.link|length ? '' : 'invisible'}}">
                <a href="{{pagination.next.link}}" class="page-link" aria-label="Next">
                  <span aria-hidden="true"></span>
                </a>
            </li>
          {% endif %}
        </ul>
      </nav>
*/

//remove pagination
remove_action( 'woocommerce_after_shop_loop', 'woocommerce_pagination', 10 );



add_filter('woocommerce_default_catalog_orderby', 'misha_default_catalog_orderby');
function misha_default_catalog_orderby( $sort_by ) {
    return 'price';
}
add_filter('woocommerce_get_catalog_ordering_args', 'misha_woocommerce_catalog_orderby');
function misha_woocommerce_catalog_orderby( $args ) {
    $args['meta_key'] = '_price';
    $args['orderby'] = 'meta_value_num';
    $args['order'] = 'asc'; 
    return $args;
}


function get_product_cover($post, $multiple=false){
    $images = array();
    $product_type = $post->get_type();
    //print_r($post);
    switch($product_type){

        case "simple" :

        break;

        case "variable" :
          //$default_variation_id = woo_get_product_default_variation_id( $post );
          //print_r($default_variation_id);
          //$images = woo_get_product_variation_thumbnails($default_variation_id, "", "", 'full');
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
                       foreach($image_ids as $image_id){
                          $images[] = wp_get_attachment_image_src( $image_id, $size )[0];
                       }
                    }
                }
            }
        break;

        case "variation" :
         $images = woo_get_product_variation_thumbnails($post->ID, "", "", 'full');

        break;

        case "grouped" :

        break;

        case "woosg" : //smart group

        break;

        case "woosb" : //bundle

        break;

        case "bundle" : //bundle

        break;

    }
    return $images;
}