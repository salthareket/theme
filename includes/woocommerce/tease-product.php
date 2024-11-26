<?php
//remove thumbnail
remove_action('woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10);

//remove product link
remove_action( 'woocommerce_before_shop_loop_item', 'woocommerce_template_loop_product_link_open', 10 );
remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_product_link_close', 5 );

if(!ENABLE_CART){
   remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart');    
}
