<?php
    
remove_action( 'woocommerce_cart_is_empty', 'wc_empty_cart_message', 10 );
/*add_action( 'woocommerce_cart_is_empty', 'custom_empty_cart_message', 10 );
function custom_empty_cart_message() {
    $html  = '<div class="alert alert-success col-12 offset-md-1 col-md-10"><p class="cart-empty">';
    $html .= wp_kses_post( apply_filters( 'wc_empty_cart_message', __( 'Your cart is currently empty.', 'woocommerce' ) ) );
    echo $html . '</p></div>';
}*/


add_action("template_redirect", 'redirect_empty_cart');
function redirect_empty_cart(){
    global $woocommerce;
    if( is_cart() && WC()->cart->cart_contents_count == 0 && !empty($GLOBALS["woo_redirect_empty_cart"])){
        wp_safe_redirect( $GLOBALS["woo_redirect_empty_cart"] );
        exit;
    }
}





//change cross-sell products position
remove_action( 'woocommerce_cart_collaterals', 'woocommerce_cross_sell_display' );
add_action( 'woocommerce_after_cart', 'woocommerce_cross_sell_display', 30 );








function cart_columns_start( ) { 
    echo "<div class='row'><div class='col-xl-9 col-lg-8'>";
}; 
//add_action( 'woocommerce_before_cart', 'cart_columns_start', 10, 1 );


function cart_columns_center( ) { 
    echo "</div><div class='col-xl-3 col-lg-4'>";
}; 
//add_action( 'woocommerce_before_cart_collaterals', 'cart_columns_center', 10, 1 );

function cart_columns_end( ) { 
    echo "</div></div>";
}; 
//add_action( 'woocommerce_after_cart', 'cart_columns_end', 10, 1 );








//add_filter( 'body_class','my_body_classes2' );
function my_body_classes2( $classes ) {
	if(!is_admin()){
		return $classes;
	}
	if ( WC()->cart->get_cart_contents_count() == 0 && is_cart()) {
	    $classes[] = 'full-page';    
	}
	return $classes;
}





add_filter('woocommerce_quantity_input_classes', 'add_classes_to_quantity_field',1);
function add_classes_to_quantity_field($args){
	if(is_cart()){
    	$args[]="size-md";		
	}
	return $args;
}










/**
 * Change the add to cart text on single product pages
 */
//add_filter('woocommerce_product_single_add_to_cart_text', 'woo_custom_cart_button_text');
function woo_custom_cart_button_text() {
	
	foreach( WC()->cart->get_cart() as $cart_item_key => $values ) {
		$_product = $values['data'];
	
		if( get_the_ID() == $_product->id ) {
			return __('Already in cart - Add Again?', 'woocommerce');
		}
	}
	
	return __('Add to cart', 'woocommerce');
}
/**
 * Change the add to cart text on product archives
 */
//add_filter( 'woocommerce_product_add_to_cart_text', 'woo_archive_custom_cart_button_text' );
function woo_archive_custom_cart_button_text() {
	
	foreach( WC()->cart->get_cart() as $cart_item_key => $values ) {
		$_product = $values['data'];
	
		if( get_the_ID() == $_product->id ) {
			return __('Already in cart', 'woocommerce');
		}
	}
	
	return __('Add to cart', 'woocommerce');
}



function get_free_shipping_amount( $multiply_by = 1 ) {
		$min_free_shipping_amount = 0;
		if(class_exists('WC_Shipping_Free_Shipping')){
			$current_wc_version = get_option( 'woocommerce_version', null );
			if ( version_compare( $current_wc_version, '2.6.0', '<' ) ) {
				$free_shipping = new WC_Shipping_Free_Shipping();
				if ( in_array( $free_shipping->requires, array( 'min_amount', 'either', 'both' ) ) ) {
					$min_free_shipping_amount = $free_shipping->min_amount;
				}
			} else {
				$legacy_free_shipping = new WC_Shipping_Legacy_Free_Shipping();
				if ( 'yes' === $legacy_free_shipping->enabled ) {
					if ( in_array( $legacy_free_shipping->requires, array( 'min_amount', 'either', 'both' ) ) ) {
						$min_free_shipping_amount = $legacy_free_shipping->min_amount;
					}
				}
				if ( 0 == $min_free_shipping_amount ) {
					if ( function_exists( 'WC' ) && ( $wc_shipping = WC()->shipping ) && ( $wc_cart = WC()->cart ) ) {
						if ( $wc_shipping->enabled ) {
							if ( $packages = $wc_cart->get_shipping_packages() ) {
								$shipping_methods = $wc_shipping->load_shipping_methods( $packages[0] );
								foreach ( $shipping_methods as $shipping_method ) {
									if ( 'yes' === $shipping_method->enabled && 0 != $shipping_method->instance_id ) {
										if ( 'WC_Shipping_Free_Shipping' === get_class( $shipping_method ) ) {
											if ( in_array( $shipping_method->requires, array( 'min_amount', 'either', 'both' ) ) ) {
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
		return ( $min_free_shipping_amount ) * $multiply_by;
}


