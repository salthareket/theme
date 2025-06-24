<div class="content-centered">
    <div class="content-block">
        <div class="content-block-success w-100 text-center">
            <i class="fa-solid fa-check fa-4x text-success"></i>
            <h3 class="title"><?php echo apply_filters( 'woocommerce_thankyou_order_received_text', translate( 'Your order received.' ), $order ); ?></h3>
            <div class="description">
                <?php echo sprintf(translate('Order No: %s') , '<strong>'.$order->get_order_number().'</strong>'); ?>
                <br>
                <?php _e('Thank you for choosing us.'); ?>
            </div>
            <?php echo sprintf(trans('We sent a confirmation mail to %s.') , '<strong>'.$order->get_billing_email().'</strong>'); ?>
            <br>
            <?php echo sprintf(trans('You can contact us from  %s if you have a questions about your order.','ekosportal'), '<a href="mailto:info@ekosportal.com">info@ekosportal.com</a>' ); ?>

            <table class="table table-module table-border table-sm mt-5">
                <thead>
                    <th><?php _e( 'Order', 'woocommerce' ); ?></th>
                    <th><?php _e( 'Date', 'woocommerce' ); ?></th>
                    <th><?php _e( 'Total', 'woocommerce' ); ?></th>
                    <?php if ( $order->get_payment_method_title() ) : ?>
                    <th><?php _e( 'Payment method', 'woocommerce' ); ?></th>
                    <?php endif; ?>
                </thead>
                <tbody>
                    <tr>
                        <td><?php echo $order->get_order_number(); ?></td>
                        <td><?php echo date_i18n( get_option( 'date_format' ), strtotime( $order->get_date_paid() ) ); ?></td>
                        <td><?php echo $order->get_formatted_order_total(); ?></td>
                        <?php if ( $order->get_payment_method_title() ) : ?>
                            <td><?php echo $order->get_payment_method_title(); ?></td>
                        <?php endif; ?>
                    </tr>
                </tbody>
            </table>
            <?php 
                //$products = get_products_by_order_id($order->ID);
                //$application = new Application($products[0]);
                //$session_url = get_permalink($application->parent->ID);
            ?>
            <?php /*<a href="<?php echo $session_url ?>" class="btn btn-lg btn-secondary btn-lg border-0 fw-bold text-uppercase btn-loading-page mt-5">View Your Session</a>*/ ?>
        </div>
    </div>
</div>