<?php
/**
 * Edit address form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/form-edit-address.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates
 * @version 3.6.0
 */

defined( 'ABSPATH' ) || exit;

$page_title = ( 'billing' === $load_address ) ? esc_html__( 'Billing address', 'woocommerce' ) : esc_html__( 'Shipping address', 'woocommerce' );

if ( ! wc_ship_to_billing_address_only() && wc_shipping_enabled() ){

}else{
	if($load_address == ""){
       $page_title = esc_html__( 'Billing address', 'woocommerce' );
	}
}
?>
<div class="container-md">
		<?php

		do_action( 'woocommerce_before_edit_account_address_form' ); ?>

		<?php if ( ! $load_address ) : ?>

			<div class="card card-module">
				<div class="card-header header-flex">
					<h3 class="card-title"><?php echo apply_filters( 'woocommerce_my_account_edit_address_title', $page_title, $load_address ); ?></h3>
					<div class="action"><a href="<?php echo esc_url( wc_get_endpoint_url( 'edit-address', "billing" ) ); ?>" class="btn btn-outline-danger btn-extend edit">Edit</a></div>
				</div>
				<div class="card-body --">
			        <?php wc_get_template( 'myaccount/my-address.php' ); ?>
			    </div>
			</div>

		<?php else : ?>

			<form class="form form-validate" method="post">
				<div class="card card-module">
					<div class="card-header">
						<h3 class="card-title"><?php echo apply_filters( 'woocommerce_my_account_edit_address_title', $page_title, $load_address ); ?></h3>
					</div>
					<div class="card-body woocommerce-address-fields">

						<?php do_action( "woocommerce_before_edit_address_form_{$load_address}" ); ?>

						<div class="row--">
							<?php
							foreach ( $address as $key => $field ) {
								woocommerce_form_field( $key, $field, wc_get_post_data_by_key( $key, $field['value'] ) );
							}
							?>
						</div>

						<?php do_action( "woocommerce_after_edit_address_form_{$load_address}" ); ?>

					</div>
					<div class="card-footer text-center">
						<button type="submit" class="btn btn-base btn-extend btn-lg" name="save_address" value="<?php esc_attr_e( 'Save address', 'woocommerce' ); ?>"><?php esc_html_e( 'Save address', 'woocommerce' ); ?></button>
						<?php wp_nonce_field( 'woocommerce-edit_address', 'woocommerce-edit-address-nonce' ); ?>
						<input type="hidden" name="action" value="edit_address" />
					</div>
				</div>
			</form>

		<?php endif; ?>

		<?php do_action( 'woocommerce_after_edit_account_address_form' ); ?>
</div>
