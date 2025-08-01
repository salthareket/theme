<?php
/**
 * Related Products
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/single-product/related.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see         https://woocommerce.com/document/template-structure/
 * @package     WooCommerce\Templates
 * @version     9.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( $related_products ) : ?>

	<section class="section-home related products bg-secondary">
		<div class="container">
			<div class="card card-products-vr card-reset">
				<div class="card-header">
					<h2 class="card-title text-center">
						<?php
						$heading = apply_filters( 'woocommerce_product_related_products_heading', __( 'Related products', 'woocommerce' ) );
						if ( $heading ) :
							?>
							<?php echo esc_html( $heading ); ?>
						<?php endif; ?>
					</h2>
					<div class="action">
	                   <!--<a href="product-category.html" class="btn-view-all btn btn-base btn-sm btn-extend">VIEW ALL</a>-->
					</div>
                </div>
                <div class="card-body loading">
                	<div class="card-products-slider swiper swiper-container fade">
						<div class="swiper-wrapper">
							<?php woocommerce_product_loop_start(); ?>

								<?php foreach ( $related_products as $related_product ) : ?>

									<?php
									$post_object = get_post( $related_product->get_id() );

									setup_postdata( $GLOBALS['post'] =& $post_object ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, Squiz.PHP.DisallowMultipleAssignments.Found

									wc_get_template_part( 'content', 'product' );
									?>

								<?php endforeach; ?>

							<?php woocommerce_product_loop_end(); ?>
						</div>
					</div>
				</div>
				<div class="card-footer">
					<div class="swiper-pagination"></div>
				    <a href="product-category.html" class="btn-view-all btn btn-base btn-sm btn-extend">VIEW ALL</a>
				</div>
	        </div>
	    </div>
	</section>

<?php endif;

wp_reset_postdata();
