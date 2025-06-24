<?php
/**
 * Login Form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/form-login.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates
 * @version 4.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

do_action( 'woocommerce_before_customer_login_form' ); ?>

<?php if ( 'yes' === get_option( 'woocommerce_enable_myaccount_registration' ) ) : ?>

<div class="page-login-signup page-centered" id="customer_login">
	<div class="container">
        
        <h1 class="title text-light text-center mt-5 mb-5">The World of Callista Beauty</h1>
		<div class="row row-margin row-margin-last">
			 <div class="col-login col-lg-6">


<?php endif; ?>

        
        <form class="form form-validate" id="form-login" autocomplete="off" method="post">

	        <div class="card-login-signup card-login card">
	            <div class="card-header">
	            	<h2 class="title"><?php esc_html_e( 'Login' ); ?></h2>
	            </div>
	       		<div class="card-body">

	       			<div class="row justify-content-center">
	       				<div class="col-md-9">

							<?php do_action( 'woocommerce_login_form_start' ); ?>
							<div class="form-group form-group-xs">
								<label for="username" class="form-label d-none"><?php esc_html_e( 'E-mail' ); ?>&nbsp;<span class="required">*</span></label>
								<input type="text" class="form-control form-control-lg-" name="username" id="username" placeholder="<?php esc_html_e( 'E-mail' ); ?>" autocomplete="username" value="<?php echo ( ! empty( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" required/><?php // @codingStandardsIgnoreLine ?>
							</div>
							<div class="form-group form-group-xs">
								<label for="password" class="form-label d-none"><?php esc_html_e( 'Password' ); ?>&nbsp;<span class="required">*</span></label>
								<input class="form-control form-control-lg- form-control-password-toggle" type="password" name="password" id="password" placeholder="<?php esc_html_e( 'Password' ); ?>" autocomplete="current-password" required/>
							</div>

							<?php do_action( 'woocommerce_login_form' ); ?>
							<div class="form-group form-group-xs">
								<div class="row">
									<div class="col">
										<div class="custom-control custom-checkbox custom-checkbox-reverse-checked">
				                            <input type="checkbox" class="custom-control-input" name="rememberme" type="checkbox" id="rememberme" value="forever"  >
				                            <label class="custom-control-label" for="rememberme"> <?php esc_html_e( 'Remember me' ); ?></label>
				                        </div>
										<?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
									</div>
									<div class="col">
					                    <div class="woocommerce-LostPassword lost_password text-right">
											<!--<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( 'Lost your password?', 'woocommerce' ); ?></a>-->
											<a href="#" class="btn-forgot-password"><?php esc_html_e( 'Lost your password?', 'woocommerce' ); ?></a>
										</div>
									</div>
								</div>
							</div>
							<?php do_action( 'woocommerce_login_form_end' ); ?>

					    </div>
					</div>

				</div>
				<div class="card-footer">
	                 <button type="submit" class="btn btn-base btn-reverse btn-extended btn-md woocommerce-form-login__submit" name="login" value="<?php esc_attr_e( 'GİRİŞ YAP' ); ?>"><?php esc_html_e( 'LOGIN' ); ?></button>
	            </div>
	            <?php do_action( 'woocommerce_login_form_bottom' ); ?>
			</div>
	    </form>
		

<?php if ( 'yes' === get_option( 'woocommerce_enable_myaccount_registration' ) ) : ?>

	</div>

	<div class="col-signup col-lg-6">

	 	<form class="form form-validate" id="form-registraton" autocomplete="off" method="post" <?php do_action( 'woocommerce_register_form_tag' ); ?> >
	        <div class="card-login-signup card-signup card">
	            <div class="card-header">
					<h2 class="title"><?php esc_html_e( 'Create a new account' ); ?></h2>
				</div>

				<?php /*<div class="card-body" data-condition="signup-method !== 'email'">
					<div class="signup-options">
                        <div class="text-center w-100">
                            <ul class="nav d-inline-block">
                                <li class="nav-item mb-2">
                                    <div class="btn-group" data-toggle="buttons">
                                        <input type="radio" id="signup-method-email" class="ignore btn-check" name="signup-method" value="email" autocomplete="off"/>
                                        <label class="btn btn-md btn-social-email w-100 btn-reverse" for="signup-method-email"><span><i class="fa fa-envelope"></i> Signup with Email</span></label>
                                    </div>
                                </li>
                                <li class="nav-item">
                                    <a href="#" class="btn btn-social-facebook btn-md btn-reverse">
                                        <span><i class="fab fa-facebook-square"></i> Signup with Facebook</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
				</div>*/
                                ?>

				<div class="card-body d-none--" data-condition="signup-method === 'email'">

				    <div class="row row-5">
	                <?php do_action( 'woocommerce_register_form_start' ); ?>

						<?php if ( 'no' === get_option( 'woocommerce_registration_generate_username' ) ) : ?>

							<div class="col-md-6">
								<div class="form-group form-group-xs">
									<label for="reg_username" class="form-label d-none"><?php esc_html_e( 'Username', 'woocommerce' ); ?>&nbsp;<span class="required">*</span></label>
									<input type="text" class="form-control form-control-lg-" name="username" id="reg_username" placeholder="<?php esc_html_e( 'Username', 'woocommerce' ); ?>" autocomplete="username" value="<?php echo ( ! empty( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" required/><?php // @codingStandardsIgnoreLine ?>
								</div>
							</div>

						<?php endif; ?>

						<div class="col-md-12">
							<div class="form-group form-group-xs">
								<label for="reg_email" class="form-label d-none"><?php esc_html_e( 'E-mail' ); ?>&nbsp;<span class="required">*</span></label>
								<input type="email" class="form-control form-control-lg-" name="email" id="reg_email" placeholder="<?php esc_html_e( 'E-mail' ); ?>" autocomplete="email" value="<?php echo ( ! empty( $_POST['email'] ) ) ? esc_attr( wp_unslash( $_POST['email'] ) ) : ''; ?>" data-rule="email" data-remote="user_exist" data-remote-param="email" required/><?php // @codingStandardsIgnoreLine ?>
							</div>
						</div>

						<?php if ( 'no' === get_option( 'woocommerce_registration_generate_password' ) ) : ?>

							<div class="col-md-6">
								<div class="form-group form-group-xs">
									<label class="form-label d-none" for="reg_password"><?php esc_html_e( 'Password' ); ?>&nbsp;<span class="required">*</span></label>
									<input type="password" class="form-control form-control-lg- form-control-password-toggle" name="password" id="reg_password" placeholder="<?php esc_html_e( 'Password' ); ?>" autocomplete="new-password" required/>
								</div>
							</div>

							<div class="col-md-6">
								<div class="form-group form-group-xs">
									<label class="form-label d-none" for="reg_password"><?php esc_html_e( 'Password (again)' ); ?>&nbsp;<span class="required">*</span></label>
									<input type="password" class="form-control form-control-lg- form-control-password-toggle" name="password_2" id="reg_password_2" placeholder="<?php esc_html_e( 'Password (again)' ); ?>" data-rule-equalto="#reg_password[name='password']" data-msg="Your passwords should match!" autocomplete="new-password" required/>
								</div>
							</div>

						<?php else : ?>

							<p><?php esc_html_e( 'Your password will send your e-mail address.' ); ?></p>

						<?php endif; ?>
				    
					<?php do_action( 'woocommerce_register_form' ); ?>

					<?php do_action( 'woocommerce_register_form_end' ); ?>

						<div class="col-md-12">
	                        <div class="form-group form-group-md">
	                            <div class="custom-control custom-checkbox custom-checkbox-reverse-checked">
	                                <input type="checkbox" class="custom-control-input" id="newsletter" name="newsletter" value="true">
	                                <label class="custom-control-label" for="newsletter">
	                                    Yes! I'd like to receive Salt's newsletter on travel news & specials.
	                                </label>
	                            </div>
	                            <div class="custom-control custom-checkbox custom-checkbox-reverse-checked">
	                                <input type="checkbox" class="custom-control-input btn-privacy-modal--" id="agreement" name="agreement" value="true" required>
	                                <label class="custom-control-label" for="agreement">
	                                    By clicking "submit" below, I agree to the <u class="btn-link-terms-modal text-underline">Terms of Use</u> and <u class="btn-link-privacy-modal text-underline">Privacy Policy.</u>
	                                </label>
	                            </div>
	                        </div>
	                    </div>

				    </div>
				</div>
				<div class="card-footer d-none--" data-condition="signup-method === 'email'">
					<?php wp_nonce_field( 'woocommerce-register', 'woocommerce-register-nonce' ); ?>
					<button type="submit" class="btn btn-base btn-reverse btn-extended btn-md" name="register" value="<?php esc_attr_e( 'SIGNUP' ); ?>"><?php esc_html_e( 'SIGNUP' ); ?></button>

					<?php /*<div class="seperator-or text-light">OR</div>

                    <div class="text-center w-100">
                        <div class="btn-group" data-toggle="buttons">
                            <input type="radio" id="signup-method-facebook" class="ignore btn-check" name="signup-method" value="facebook" autocomplete="off"/>
                            <label class="btn btn-md btn-social-email w-100 btn-reverse" for="signup-method-facebook"><span><i class="fab fa-facebook-square"></i> Signup with Facebook</span></label>
                        </div>
                    </div>*/
                                ?>

				</div>
				<?php do_action( 'woocommerce_register_form_bottom' ); ?>
			</div>
		</form>

	</div>

</div>
</div>
<?php endif; ?>

<?php do_action( 'woocommerce_after_customer_login_form' ); ?>

<script>
	window.addEventListener("hashchange", function(){
		alert(window.location.hash);
        if(window.location.hash == "#form-registration"){
	       $("#signup-method-email").prop("checked");
	    }
	}, false);
    $( document ).ready(function() {
    	if(window.location.hash == "#form-registration"){
           $("#signup-method-email").prop("checked");
    	}

		$("a.btn-social-facebook").on("click", function(e){
			e.preventDefault();
			var $form = $(this).closest("form");
			//if($form.valid()){
				$form.removeClass("form-changed");
				$("a.facebook-connect").trigger("focus").trigger("click");
			//}else{
				$("input[name='agreement']").removeClass("ignore");
			//}
		});
	});
</script>