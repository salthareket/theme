<?php
/**
 * Simple product add to cart
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/single-product/add-to-cart/simple.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 7.0.1
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! $product->is_purchasable() ) {
	 return;
}

echo wc_get_stock_html( $product ); // WPCS: XSS ok.

if(ENABLE_FAVORITES){
    $is_favorite  = in_array($product->get_id(), $GLOBALS['favorites']);
    $favorite_count = (get_post_meta($product->get_id(),"wpcf_favorites_count", true));
    if(empty($favorite_count) || $favorite_count < 0){
       $favorite_count = 0; 
    }
    
    $favorite_text = "";
    /*$favorite_text .= '<span class="info">';
    $favorite_text .= !$is_favorite?trans("Favorilerine Ekle"):trans("Kaldır");
    $favorite_text .= !empty($favorite_count)?esc_attr(sprintf(trans("%s kişi bu ürünü ekledi"), $favorite_count)):"";
    $favorite_text .= '</span>';
    */
}

if ( $product->is_in_stock() ) : ?>

	<?php do_action( 'woocommerce_before_add_to_cart_form' ); ?>
    <div class="product-buy mt-4">
		<form class="cart" action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>" method="post" enctype='multipart/form-data'>
			<div class="row g-3 justify-content-center justify-content-lg-start">
				<?php do_action( 'woocommerce_before_add_to_cart_button' ); 
              if(ENABLE_CART){
				?>
               <?php if($product->get_min_purchase_quantity() != $product->get_max_purchase_quantity()){ ?>
                <div class="col-6 col-lg-3">
					<?php
					do_action( 'woocommerce_before_add_to_cart_quantity' );

					woocommerce_quantity_input( array(
						'min_value'   => apply_filters( 'woocommerce_quantity_input_min', $product->get_min_purchase_quantity(), $product ),
						'max_value'   => apply_filters( 'woocommerce_quantity_input_max', $product->get_max_purchase_quantity(), $product ),
						'input_value' => isset( $_POST['quantity'] ) ? wc_stock_amount( wp_unslash( $_POST['quantity'] ) ) : $product->get_min_purchase_quantity(), // WPCS: CSRF ok, input var ok.
					) );

					do_action( 'woocommerce_after_add_to_cart_quantity' );
					?>
			    </div>
			    <?php } ?>
			    <div class="<?php if($product->get_min_purchase_quantity() != $product->get_max_purchase_quantity()){ ?>col-6 col-lg-auto<?php }else{ ?>col-auto<?php } ?>">
					    <button type="submit" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>" class="btn btn-lg btn-loading-page btn-extend btn-outline-light single_add_to_cart_button button-- alt--"><?php echo esc_html( $product->single_add_to_cart_text() ); ?></button>
				</div>
				<?php 
				  } 
				 ?>
			   <div class="col-auto">
			    	<?php
			        if(ENABLE_FAVORITES){?>
			        <a href="#" class="btn-favorite btn-product-action btn-favorite-text-- <?php if($is_favorite){?>active<?php }?>" data-id="<?php echo $product->get_id(); ?>">
			           <?php echo $GLOBALS["icons"]["favorite"]; ?>
			           <?php  echo $favorite_text; ?>
			        </a>
			        <?php
			        }
			        ?>
		        </div>
				<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>
		    </div>
		</form>
    </div>

	<?php do_action( 'woocommerce_after_add_to_cart_form' ); ?>

<?php endif; ?>