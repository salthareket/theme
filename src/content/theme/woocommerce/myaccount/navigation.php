<?php
/**
 * My Account navigation
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/navigation.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates
 * @version 2.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'woocommerce_before_account_navigation' );
?>

 <div id="sidebar" class="sidebar-product-filters col navbar-offcanvas-sidebar offcanvas-sm">
 	<a href="#" class="offcanvas-close d-lg-none d-md-block d-sm-block" data-toggle="offcanvas" data-target=".navbar-offcanvas-sidebar" data-canvas="body" data-exclude=".navbar-offcanvas-main" data-backdrop="true">
        <i class="icon-arrow-left text-success"></i> KAPAT
    </a>
	<div class="product-filters card-collapse card-merged" id="product-filters">
		<?php foreach ( wc_get_account_menu_items() as $endpoint => $label ) : ?>
	    <div class="product-filter-item card">
            <div class="card-header show-arrow no-border">
                <h5 class="title">
				    <a href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>"><?php echo esc_html( $label ); ?></a>
				</h5>
			</div>
		</div>
		<?php endforeach; ?>
	</div>
</div>

<?php do_action( 'woocommerce_after_account_navigation' ); ?>
