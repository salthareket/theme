<?php

//remove breadcrumb
remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );

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

remove_action( 'yith_wcan_filter_reset_button', 20);


//remove thumbnail
remove_action('woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10);
remove_action('woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5 );
remove_action('woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10 );

//remove product link
remove_action( 'woocommerce_before_shop_loop_item', 'woocommerce_template_loop_product_link_open', 10 );
remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_product_link_close', 5 );

if(!ENABLE_CART){
   remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart');    
}


// Display woocommerce_sale_flash only when there is sale price 
add_filter( 'woocommerce_sale_flash', 'filter_woocommerce_sale_flash', 10, 3 ); 
function filter_woocommerce_sale_flash( $span_class_onsale_esc_html_sale_woocommerce_span, $post, $product ) {
     return $product->get_sale_price() ? $span_class_onsale_esc_html_sale_woocommerce_span : ''; 
}


// To change add to cart text on product archives(Collection) page
add_filter( 'woocommerce_product_add_to_cart_text', 'woocommerce_custom_product_add_to_cart_text' );  
function woocommerce_custom_product_add_to_cart_text() {
    return __( 'Buy Now', 'woocommerce' );
}


//remove pagination
remove_action( 'woocommerce_after_shop_loop', 'woocommerce_pagination', 10 );



// WooCommerce ürün listeleme sıralamasını fiyatın artan (küçükten büyüğe) değerine göre ayarlar
add_filter('woocommerce_get_catalog_ordering_args', 'misha_woocommerce_catalog_orderby');
function misha_woocommerce_catalog_orderby( $args ) {
    $args['meta_key'] = '_price';             // Fiyat meta alanına göre
    $args['orderby'] = 'meta_value_num';      // Sayısal meta değerine göre sıralama
    $args['order'] = 'asc';                    // Artan sıra (küçükten büyüğe)
    return $args;
}




// WooCommerce ürün katalog sıralamasında varsayılan sıralamayı "fiyata göre" yapar
add_filter('woocommerce_default_catalog_orderby', 'misha_default_catalog_orderby');
function misha_default_catalog_orderby( $sort_by ) {
    return 'price';  // Varsayılan sıralama kriterini fiyat yap
}





// 'featured_products' sıralaması seçildiğinde, önce öne çıkarılmış ürünleri öne alır, ardından yayın tarihine göre sıralar
function featured_products_orderby( $orderby, $query ) {
    global $wpdb;

    if ( 'featured_products' == $query->get( 'orderby' ) ) {
        $featured_product_ids = (array) wc_get_featured_product_ids();
        if ( count( $featured_product_ids ) ) {
            $string_of_ids = '(' . implode( ',', $featured_product_ids ) . ')';
            // Öne çıkarılmış ürünlerin ID'leri içinde mi diye kontrol ederek onları önce sırala, sonra tarih
            $orderby = "( {$wpdb->posts}.ID IN {$string_of_ids}) " . $query->get( 'order' )." , post_date DESC";
        }
    }
    return $orderby;
}
add_filter( 'posts_orderby', 'featured_products_orderby', 10, 2 );





add_filter( 'woocommerce_loop_add_to_cart_link', 'woo_display_variation_dropdown_on_shop_page' );
function woo_display_variation_dropdown_on_shop_page() {
    global $product;

    if ( $product->is_type( 'variable' ) ) {
        $attribute_keys = array_keys( $product->get_attributes() );
        ?>
        <form class="variations_form cart" method="post" enctype='multipart/form-data' data-product_id="<?php echo absint( $product->get_id() ); ?>" data-product_variations="<?php echo htmlspecialchars( json_encode( $product->get_available_variations() ) ); ?>">
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
                                        wc_dropdown_variation_attribute_options( array(
                                            'options'   => $options,
                                            'attribute' => $attribute_name,
                                            'product'   => $product,
                                            'selected'  => $selected,
                                        ) );
                                        echo end( $attribute_keys ) === $attribute_name ? apply_filters( 'woocommerce_reset_variations_link', '<a class="reset_variations" href="#">' . __( 'Clear', 'woocommerce' ) . '</a>' ) : '';
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>

                <div class="single_variation_wrap">
                    <?php
                        do_action( 'woocommerce_before_single_variation' );
                        do_action( 'woocommerce_single_variation' );
                        do_action( 'woocommerce_after_single_variation' );
                    ?>
                </div>

                <?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>
            <?php endif; ?>

            <?php do_action( 'woocommerce_after_variations_form' ); ?>
        </form>
        <?php
    } else {
        echo sprintf(
            '<a rel="nofollow" href="%s" data-quantity="%s" data-product_id="%s" data-product_sku="%s" class="%s">%s</a>',
            esc_url( $product->add_to_cart_url() ),
            esc_attr( isset( $quantity ) ? $quantity : 1 ),
            esc_attr( $product->get_id() ),
            esc_attr( $product->get_sku() ),
            esc_attr( isset( $class ) ? $class : 'button' ),
            esc_html( $product->add_to_cart_text() )
        );
    }
}






