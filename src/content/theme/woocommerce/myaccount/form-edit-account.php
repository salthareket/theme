<?php
/**
 * Edit account form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/form-edit-account.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates
 * @version 3.5.0
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_edit_account_form' ); ?>

<div class="container-md">
<form class="form form-validate woocommerce-EditAccountForm edit-account" action="" method="post" autocomplete="off" <?php do_action( 'woocommerce_edit_account_form_tag' ); ?> >
    <div class="card card-layout">
    	<div class="card-body">
			<?php do_action( 'woocommerce_edit_account_form_start' ); ?>

			<div class="card card-module mb-4">
            	<div class="card-header">
            		<h3 class="card-title">Details</h3>
                </div>
                <div class="card-body">
					<div class="row">
				    	<div class="col-sm-6">
							<div class="form-group">
								<label class="form-label form-label-md mb-2" for="account_first_name"><?php esc_html_e( 'First name', 'woocommerce' ); ?>&nbsp;<span class="required">*</span></label>
								<input type="text" class="form-control" name="account_first_name" id="account_first_name" autocomplete="off" value="<?php echo esc_attr( $user->first_name ); ?>" required/>
							</div>
					    </div>
					    <div class="col-sm-6">
							<div class="form-group">
								<label class="form-label form-label-md mb-2" for="account_last_name"><?php esc_html_e( 'Last name', 'woocommerce' ); ?>&nbsp;<span class="required">*</span></label>
								<input type="text" class="form-control" name="account_last_name" id="account_last_name" autocomplete="off" value="<?php echo esc_attr( $user->last_name ); ?>" required/>
							</div>
						</div>
						<!--<div class="col-sm-6">
							<div class="form-group">
								<label class="form-label" for="account_display_name"><?php esc_html_e( 'Display name', 'woocommerce' ); ?>&nbsp;<span class="required">*</span></label>
								<input type="text" class="form-control" name="account_display_name" id="account_display_name" value="<?php echo esc_attr( $user->display_name ); ?>" /> 
								<small  class="form-text text-muted"><?php esc_html_e( 'This will be how your name will be displayed in the account section and in reviews', 'woocommerce' ); ?></small>
							</div>
						</div>-->
						<div class="col-sm-12">
							<div class="form-group">
								<label class="form-label form-label-md mb-2" for="account_email"><?php esc_html_e( 'Email address', 'woocommerce' ); ?>&nbsp;<span class="required">*</span></label>
								<input type="email" class="form-control" name="account_email" id="account_email" autocomplete="off" value="<?php echo esc_attr( $user->user_email ); ?>" required/>
							</div>
						</div>
						<div class="col-sm-12">
							<div class="form-group">
								<label class="form-label form-label-md mb-2" for="billing_mobile_phone"><?php _e( 'Mobile phone', 'woocommerce' ); ?>&nbsp;<span class="required">*</span></label>
								<input type="text" class="form-control" name="billing_mobile_phone" id="billing_mobile_phone" value="<?php echo esc_attr( $user->billing_mobile_phone ); ?>" required/>
							</div>
						</div>
					</div>
			    </div>
			</div>

			<?php do_action( 'woocommerce_edit_account_form' ); ?>

            <div class="card card-module">
            	<div class="card-header">
            		<h3 class="card-title"><?php esc_html_e( 'Password change', 'woocommerce' ); ?></h3>
                </div>
                <div class="card-body">

					<div class="row">
				    	<div class="col-sm-12">
							<div class="form-group">
								<label class="form-label form-label-md mb-2" for="password_current"><?php esc_html_e( 'Current password', 'woocommerce' ); ?></label>
								<input type="password" class="form-control" name="password_current" id="password_current" value="" autocomplete="new-password" />
								<script> window.load() = function(){document.getElementById("password_current").value = "";}</script>
							</div>
					    </div>
						<div class="col-sm-6">
							<div class="form-group">
								<label class="form-label form-label-md mb-2" for="password_1"><?php esc_html_e( 'New password', 'woocommerce' ); ?></label>
								<input type="password" class="form-control" name="password_1" id="password_1" value="" autocomplete="off" />
							</div>
						</div>
						<div class="col-sm-6">
							<div class="form-group">
								<label class="form-label form-label-md mb-2" for="password_2"><?php esc_html_e( 'Confirm new password', 'woocommerce' ); ?></label>
								<input type="password" class="form-control" name="password_2" id="password_2" value="" autocomplete="off" data-rule-equalto="input[name='password_1']" data-msg="Your passwords should match!" />
							</div>
						</div>
					</div>

			    </div>
			</div>

        </div>
		<div class="card-footer text-center">
			<?php wp_nonce_field( 'save_account_details', 'save-account-details-nonce' ); ?>
			<button type="submit" class="btn btn-base btn-extend btn-lg" name="save_account_details" value="<?php esc_attr_e( 'Save changes', 'woocommerce' ); ?>"><?php esc_html_e( 'Save changes', 'woocommerce' ); ?></button>
			<input type="hidden" name="action" value="save_account_details" />
		</div>
    </div>
	<?php do_action( 'woocommerce_edit_account_form_end' ); ?>
</form>
</div>

<?php do_action( 'woocommerce_after_edit_account_form' ); ?>
