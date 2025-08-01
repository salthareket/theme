<?php
/**
 * My Account Dashboard
 *
 * Shows the first intro screen on the account dashboard.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/dashboard.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 4.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$allowed_html = array(
	'a' => array(
		'href' => array(),
	),
);
?>

<div class="card-account-dashboard card card-layout">
	<div class="card-header">
		<h3 class="card-title">
	<?php
	/* translators: 1: user display name 2: logout url */
	printf(
		__( 'Hello %1$s <small>not %1$s? <a href="%2$s">Log out</a></small>', 'woocommerce' ),
		'<strong>' . esc_html( $current_user->display_name ) . '</strong>',
		esc_url( wc_logout_url( wc_get_page_permalink( 'myaccount' ) ) )
	);
	echo "</h3>";
	/*echo "<div class='action'>";
		if ( in_array( 'agent', (array) $current_user->roles ) ) {
	       echo "<h3><span class='badge badge-warning'>".esc_html_e('Travel Agent')."</span></h3>";
		}
		if ( in_array( 'customer', (array) $current_user->roles ) ) {
	       echo "<span class='badge badge-success'>".esc_html_e('Customer')."</span>";
		}
		if ( in_array( 'administrator', (array) $current_user->roles ) ) {
	       echo "<span class='badge badge-primary'>".esc_html_e('Administrator')."</span>";
		}
	echo "</div>";*/
    ?></div>

    <div class="card-module card-module-solid card-body"><?php
	printf(
		__( 'From your account dashboard you can view your <a href="%1$s">recent orders</a>, manage your <a href="%2$s">shipping and billing addresses</a>, and <a href="%3$s">edit your password and account details</a>.', 'woocommerce' ),
		esc_url( wc_get_endpoint_url( 'orders' ) ),
		esc_url( wc_get_endpoint_url( 'edit-address' ) ),
		esc_url( wc_get_endpoint_url( 'edit-account' ) )
	);
    ?></div>

<?php
    echo "<div class='card-body'>";
		/**
	 * My Account dashboard.
	 *
	 * @since 2.6.0
	 */
	do_action( 'woocommerce_account_dashboard' );

	/**
	 * Deprecated woocommerce_before_my_account action.
	 *
	 * @deprecated 2.6.0
	 */
	do_action( 'woocommerce_before_my_account' );

	/**
	 * Deprecated woocommerce_after_my_account action.
	 *
	 * @deprecated 2.6.0
	 */
	do_action( 'woocommerce_after_my_account' );
    
    echo "</div></div>";
/* Omit closing PHP tag at the end of PHP files to avoid "headers already sent" issues. */
