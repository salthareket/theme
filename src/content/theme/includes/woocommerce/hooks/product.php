<?php


// WooCommerce varyasyonlu ürünlerde AJAX varyasyon yükleme eşiğini 150 olarak ayarlar
add_filter( 'woocommerce_ajax_variation_threshold', 'wc_ninja_ajax_threshold' );
function wc_ninja_ajax_threshold() {
    return 150; // Varyasyon sayısı 150 ve üzeri ise AJAX kullan
}





//disable sku usage
//add_filter( 'wc_product_sku_enabled', '__return_false' );

//disable unique sku usage
//add_filter( 'wc_product_has_unique_sku', '__return_false' ); 






function max_grouped_price( $price_this_get_price_suffix, $instance, $child_prices ) { 
    return wc_price(max($child_prices)); 
}; 
//add_filter( 'woocommerce_grouped_price_html', 'max_grouped_price', 10, 3 );





add_filter( 'woocommerce_get_price_html', 'price_html_filter', 100, 2 );
function price_html_filter( $price, $product ){
	switch($product->get_type()){
		case "bundle" :
             return bundled_product_price($product);
		     break;
        case "variable" :
             return variable_product_price($product);
             break;
		default :
		     return $price;
		     break;
	}
}
