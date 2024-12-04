<div class="content-centered">
    <div class="content-block">
        <div class="content-block-failed">
            <i class="fa fa-check"></i>
            <h3 class="title">Failed</h3>
            <div class="description">
                <?php _e( 'Unfortunately your order cannot be processed as the originating bank/merchant has declined your transaction.', 'woocommerce' ); ?>
            </div>
            <?php
			    if ( is_user_logged_in() )
			        _e( 'Please attempt your purchase again or go to your account page.', 'woocommerce' );
			    else
			        _e( 'Please attempt your purchase again.', 'woocommerce' );
			?>
			<hr>
			<a href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>" class="btn btn-primary btn-extend pay"><?php _e( 'Pay', 'woocommerce' ) ?></a>
		    <?php if ( is_user_logged_in() ) : ?>
		    <a href="<?php echo esc_url( get_permalink( wc_get_page_id( 'myaccount' ) ) ); ?>" class="btn btn-primary btn-extend pay"><?php _e( 'My Account', 'woocommerce' ); ?></a>
		    <?php endif; ?>
        </div>
    </div>
</div>
