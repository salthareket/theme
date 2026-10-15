<?php
/**
 * My Addresses
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/my-address.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.3.0
 */

defined( 'ABSPATH' ) || exit;

$customer_id = get_current_user_id();

if ( ! wc_ship_to_billing_address_only() && wc_shipping_enabled() ) {
	$get_addresses = apply_filters(
		'woocommerce_my_account_get_addresses',
		array(
			'billing'  => __( 'Billing address', 'woocommerce' ),
			'shipping' => __( 'Shipping address', 'woocommerce' ),
		),
		$customer_id
	);
} else {
	$get_addresses = apply_filters(
		'woocommerce_my_account_get_addresses',
		array(
			'billing' => __( 'Billing address', 'woocommerce' ),
		),
		$customer_id
	);
}

$has_multiple = ! wc_ship_to_billing_address_only() && wc_shipping_enabled();
?>

<?php if ( $has_multiple ) : ?>
<p>
	<?php echo apply_filters( 'woocommerce_my_account_my_address_description', esc_html__( 'The following addresses will be used on the checkout page by default.', 'woocommerce' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</p>
<?php endif; ?>

<?php if ( $has_multiple ) : ?>
<div class="row g-4">
<?php endif; ?>

<?php foreach ( $get_addresses as $name => $address_title ) : ?>
	<?php $address = wc_get_account_formatted_address( $name ); ?>

	<?php if ( $has_multiple ) : ?>
	<div class="col-sm-6">
	<?php endif; ?>

		<div class="card card-module h-100">
			<div class="card-header d-flex align-items-center justify-content-between">
				<h3 class="card-title mb-0"><?php echo esc_html( $address_title ); ?></h3>
				<a href="<?php echo esc_url( wc_get_endpoint_url( 'edit-address', $name ) ); ?>" class="btn btn-sm btn-outline-secondary">
					<?php
					printf(
						/* translators: %s: Address title */
						$address ? esc_html__( 'Edit %s', 'woocommerce' ) : esc_html__( 'Add %s', 'woocommerce' ),
						esc_html( $address_title )
					);
					?>
				</a>
			</div>
			<div class="card-body">
				<address class="mb-0">
					<?php
					echo $address
						? wp_kses_post( $address )
						: esc_html__( 'You have not set up this type of address yet.', 'woocommerce' );

					/**
					 * Used to output content after core address fields.
					 *
					 * @param string $name Address type.
					 * @since 8.7.0
					 */
					do_action( 'woocommerce_my_account_after_my_address', $name );
					?>
				</address>
			</div>
		</div>

	<?php if ( $has_multiple ) : ?>
	</div>
	<?php endif; ?>

<?php endforeach; ?>

<?php if ( $has_multiple ) : ?>
</div>
<?php endif; ?>
