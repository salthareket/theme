<?php
/**
 * Lost password reset form.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/form-reset-password.php.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.2.0
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_reset_password_form' );
?>

<div class="page-login-signup page-centered text-center">
	<div class="container-xs">

		<form method="post" class="form form-validate woocommerce-ResetPassword lost_reset_password" novalidate>

			<p class="mb-4 text-muted"><?php echo apply_filters( 'woocommerce_reset_password_message', esc_html__( 'Enter a new password below.', 'woocommerce' ) ); ?></p>

			<div class="form-group mb-3 text-start">
				<label class="form-label" for="password_1">
					<?php esc_html_e( 'New password', 'woocommerce' ); ?>&nbsp;<span class="required text-danger" aria-hidden="true">*</span>
				</label>
				<input type="password"
				       class="form-control form-control-lg"
				       name="password_1"
				       id="password_1"
				       autocomplete="new-password"
				       minlength="8"
				       required
				       aria-required="true" />
				<div class="form-text text-muted small"><?php esc_html_e( 'Minimum 8 characters.', 'woocommerce' ); ?></div>
			</div>

			<div class="form-group mb-4 text-start">
				<label class="form-label" for="password_2">
					<?php esc_html_e( 'Re-enter new password', 'woocommerce' ); ?>&nbsp;<span class="required text-danger" aria-hidden="true">*</span>
				</label>
				<input type="password"
				       class="form-control form-control-lg"
				       name="password_2"
				       id="password_2"
				       autocomplete="new-password"
				       data-rule-equalto="#password_1"
				       data-msg="<?php esc_attr_e( 'Passwords do not match.', 'woocommerce' ); ?>"
				       required
				       aria-required="true" />
			</div>

			<input type="hidden" name="reset_key"   value="<?php echo esc_attr( $args['key'] ); ?>" />
			<input type="hidden" name="reset_login" value="<?php echo esc_attr( $args['login'] ); ?>" />
			<input type="hidden" name="wc_reset_password" value="true" />

			<?php do_action( 'woocommerce_resetpassword_form' ); ?>

			<?php wp_nonce_field( 'reset_password', 'woocommerce-reset-password-nonce' ); ?>

			<button type="submit" class="btn btn-base btn-reverse btn-lg btn-extend">
				<?php esc_html_e( 'Save', 'woocommerce' ); ?>
			</button>

		</form>

	</div>
</div>

<?php
do_action( 'woocommerce_after_reset_password_form' );
