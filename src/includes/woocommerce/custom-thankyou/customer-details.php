<div class="card card-module">
    <header class="card-header">
        <h2 class="card-title"><?php _e( 'Customer details', 'woocommerce' ); ?></h2>
    </header>
    <div class="card-body">
        <dl class="customer_details">
        <?php

            if ( $order->get_billing_email() ) echo '<dt>' . __( 'Email:', 'woocommerce' ) . '</dt><dd>' . $order->get_billing_email() . '</dd>';
            if ( $order->get_billing_phone() ) echo '<dt>' . __( 'Telephone:', 'woocommerce' ) . '</dt><dd>' . $order->get_billing_phone() . '</dd>';

            // Additional customer details hookş
            do_action( 'woocommerce_order_details_after_customer_details', $order );
        ?>
        </dl>

        <?php if ( ! wc_ship_to_billing_address_only() && $order->needs_shipping_address() && get_option( 'woocommerce_calc_shipping' ) !== 'no' ) : ?>

        <div class="row col2-set-- addresses">

            <div class="col-sm-6 col-1--">

        <?php endif; ?>

                <header class="title">
                    <h3><?php _e( 'Billing Address', 'woocommerce' ); ?></h3>
                </header>
                <address>
                    <?php
                        if ( ! $order->get_formatted_billing_address() ) _e( 'N/A', 'woocommerce' ); else echo $order->get_formatted_billing_address();
                    ?>
                </address>

        <?php if ( ! wc_ship_to_billing_address_only() && $order->needs_shipping_address() && get_option( 'woocommerce_calc_shipping' ) !== 'no' ) : ?>

            </div><!-- /.col-1 -->

            <div class="col-sm-6col-2--">

                <header class="title">
                    <h3><?php _e( 'Shipping Address', 'woocommerce' ); ?></h3>
                </header>
                <address>
                    <?php
                        if ( ! $order->get_formatted_shipping_address() ) _e( 'N/A', 'woocommerce' ); else echo $order->get_formatted_shipping_address();
                    ?>
                </address>

            </div><!-- /.col-2 -->

        </div><!-- /.col2-set -->

        <?php endif; ?>
    </div>
</div>