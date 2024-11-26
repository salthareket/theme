<?php
//include 'woocommerce/functions.php';
include 'woocommerce/global.php';

include 'woocommerce/admin.php';

include 'woocommerce/products.php';
include 'woocommerce/tease-product.php';
include 'woocommerce/single-product.php';
include 'woocommerce/product-category.php';


/*if(ENABLE_MEMBERSHIP){
	 include 'woocommerce/redirect.php';
   include 'woocommerce/my-account.php';
}*/

if(ENABLE_CART){
 include 'woocommerce/custom-thankyou.php';	
 include 'woocommerce/checkout.php';
 include 'woocommerce/cart.php';
}
if(!DISABLE_COMMENTS){
  include 'woocommerce/comments.php';	
}
if(ENABLE_FILTERS){
	function woo_filter_vars($vars) {
	  $vars[] .= 'key';
	  return $vars;
	}
	//add_filter( 'query_vars', 'woo_filter_vars' );
}


/*
$woocommerce_styles_scripts = "---";//shop_only"; //or "none", "both"

if($woocommerce_styles_scripts == "none" || $woocommerce_styles_scripts == "both"){
	  function ca_deregister_woocommerce_block_styles() {
	    wp_deregister_style( 'wc-blocks-style' );
	    wp_dequeue_style( 'wc-blocks-style' );
	  }
	  add_action( 'enqueue_block_assets', 'ca_deregister_woocommerce_block_styles' );
    add_filter( 'woocommerce_enqueue_styles', '__return_false' );	
}

if($woocommerce_styles_scripts == "shop_only" || $woocommerce_styles_scripts == "both"){
	function bt_remove_woocommerce_styles_scripts() {

	        // Skip Woo Pages
	        if ( is_woocommerce() || is_cart() || is_checkout()){// || is_account_page() ) {
	                return;
	        }
	        // Otherwise...
	        remove_action('wp_enqueue_scripts', [WC_Frontend_Scripts::class, 'load_scripts']);
	        remove_action('wp_print_scripts', [WC_Frontend_Scripts::class, 'localize_printed_scripts'], 5);
	        remove_action('wp_print_footer_scripts', [WC_Frontend_Scripts::class, 'localize_printed_scripts'], 5);
	        function ca_deregister_woocommerce_block_styles() {
				    wp_deregister_style( 'wc-blocks-style' );
				    wp_dequeue_style( 'wc-blocks-style' );
				  }
				  add_action( 'enqueue_block_assets', 'ca_deregister_woocommerce_block_styles' );
	}
	add_action( 'template_redirect', 'bt_remove_woocommerce_styles_scripts', 999 );
}*/