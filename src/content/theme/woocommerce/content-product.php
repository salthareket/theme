<?php
/**
 * The template for displaying product content within loops
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/content-product.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.4.0
 */

defined( 'ABSPATH' ) || exit;

global $product, $wp_query, $woocommerce_loop;

// Check if the product is a valid WooCommerce product and ensure its visibility before proceeding.
if ( ! is_a( $product, WC_Product::class ) || ! $product->is_visible() ) {
	return;
}

if ( empty( $product ) || ! $product->is_visible() ) {
	echo "hatalı urun";
	return;
}

if (empty($woocommerce_loop['loop'])) {
    $woocommerce_loop['loop'] = 0;
}
$woocommerce_loop['loop']++;



$page = "";
$page_no = "";
if(isset($GLOBALS["pagination_page"]) && !empty($GLOBALS["pagination_page"])){
	$page = "data-page='".$GLOBALS["pagination_page"]."'";
	$page_no = $GLOBALS["pagination_page"];
}else{
	if ($wp_query->max_num_pages > 1) {
    	$page = max(1, get_query_var('paged'));
    	$page_no = $page;
    	$page = "data-page='".$page."'";
	}
}
?>
<div class="col content=product.php" <?php echo $page;?>>
	<?php
	$product_id = $product->get_id();
	/*$has_attribute = false;
	$attributes = woo_get_all_product_attributes();
	foreach ($attributes as $attribute) {
		if(isset($_GET["filter_".$attribute])){
	   	    if($product->get_type() == "variable"){
	   	  	    $product_id = get_variation_id_by_attribute($product_id , $attribute, $_GET["filter_".$attribute]);
	   	  	    if($product_id ){
	   	  	 	   //$post = get_post($post_id);
	   	  	 	   //$product = wc_get_product($product_id );
	   	  	    }
	   	  	}
	    }
	}
	if(!$has_attribute){
		//$product = wc_get_product( $product->get_id() );
		//$post = Timber::get_post($post->ID);
	}*/
	   
	$context = Timber::context();
    $context["post"] = Timber::get_post($product_id);
	$context["product"] = $product;
	$context["page"] = $page_no;
	$context["index"] = $woocommerce_loop['loop'];
	$querystring = "";
	if(!isset($_SESSION['query_pagination_vars'])){
		$_SESSION['query_pagination_vars'] = array();
	}else{
		if(isset($_SESSION['query_pagination_vars'][$product->post_type]["querystring"]))
		  $querystring = $_SESSION['query_pagination_vars'][$product->post_type]["querystring"];
	}
	$context["querystring"] = $querystring;
	Timber::render("product/tease.twig", $context);
?>
</div>
