<?php
/**
 * Orders
 *
 * Shows orders on the account page.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/orders.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates
 * @version 3.7.0
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_account_orders', $has_orders ); ?>

<?php if ( $has_orders ) : ?>

	<table class="table-module table woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table">
		<thead>
			<tr>
				<?php foreach ( wc_get_account_orders_columns() as $column_id => $column_name ) : ?>
					<th class="woocommerce-orders-table__header woocommerce-orders-table__header-<?php echo esc_attr( $column_id ); ?>"><span class="nobr"><?php echo esc_html( $column_name ); ?></span></th>
				<?php endforeach; ?>
			</tr>
		</thead>

		<tbody>
			<?php
			foreach ( $customer_orders->orders as $customer_order ) {
				$order      = wc_get_order( $customer_order ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.OverrideProhibited
				$item_count = $order->get_item_count() - $order->get_item_count_refunded();
				$items = $order->get_items();
				foreach ( $items as $item ) {
					$product_id = $item->get_product_id();
					$product_url = get_permalink(get_field("tour_plan_id", $product_id));
				    $product_name = $item->get_name();
				    break;
				}
				?>
				<tr class="woocommerce-orders-table__row woocommerce-orders-table__row--status-<?php echo esc_attr( $order->get_status() ); ?> order">
					<?php foreach ( wc_get_account_orders_columns() as $column_id => $column_name ) : ?>
						<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-<?php echo esc_attr( $column_id ); ?>" data-title="<?php echo esc_attr( $column_name ); ?>">
							<?php if ( has_action( 'woocommerce_my_account_my_orders_column_' . $column_id ) ) : ?>
								<?php do_action( 'woocommerce_my_account_my_orders_column_' . $column_id, $order ); ?>

							<?php elseif ( 'order-number' === $column_id ) : ?>
								<a href="<?php echo esc_url( $order->get_view_order_url() ); ?>">
									<?php echo esc_html( _x( '#', 'hash before order number', 'woocommerce' ) . $order->get_order_number() ); ?>
								</a>

							<?php elseif ( 'order-date' === $column_id ) : ?>
								<b><a href="<?php echo esc_url($product_url);?>" class="text-primary"><?php echo $product_name; ?></a></b><br>
								<time datetime="<?php echo esc_attr( $order->get_date_created()->date( 'c' ) ); ?>"><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></time>

							<?php elseif ( 'order-status' === $column_id ) : ?>
								<span class="order-status <?php echo esc_attr( $order->get_status()); ?>"><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></span>

							<?php elseif ( 'order-total' === $column_id ) : ?>
								<?php
								/* translators: 1: formatted order total 2: total order items */
								echo wp_kses_post( sprintf( _n( '%1$s for %2$s item', '%1$s for %2$s items', $item_count, 'woocommerce' ), $order->get_formatted_order_total(), $item_count ) );
								?>

							<?php elseif ( 'order-actions' === $column_id ) : ?>
								<?php
								$actions = wc_get_account_orders_actions( $order );
                                
                                if ( ! empty( $actions ) ) {
									if(isset($actions['view'])){
									    $actions['view']["url"] = "#";
									    $actions['view']["class"] = "btn-order-detail btn-outline-primary btn-loading-page";
									    $actions['view']["data"] = [
									        'order-number' => $order->get_order_number()
									    ];
									    $actions['view']["name"] = __( 'View', 'woocommerce' );
									}
									if(isset($actions['pay'])){
	                                   $actions['pay']["class"] = "btn-primary btn-loading-page";
									}
									if(isset($actions['pdf'])){
	                                   $actions['pdf']["class"] = "btn-outline-success";
									}
									?>
                                    <div class="btn-group btn-gap btn-gap-sm">
									<?php
										foreach ( $actions as $key => $action ) {
										    echo '<a href="' . esc_url( $action['url'] ) . '" class="'.(isset($action['class'])?$action['class']:"").' btn btn-sm ' . sanitize_html_class( $key ) . '"';
										    if(isset($action['data']) && is_array($action['data'])){
										        foreach($action['data'] AS $data_attr=>$data_value){
										            echo 'data-' . sanitize_html_class($data_attr) .'="' .esc_html($data_value) . '" ';
										        }
										    }
										    echo '>' . esc_html( $action['name'] ) . '</a>';
		                                }
	                                ?>
                                    </div>
	                                <?php
	                            }
								/*if ( ! empty( $actions ) ) {
									echo "<div class='btn-group btn-group-sm'>";
									foreach ( $actions as $key => $action ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.OverrideProhibited
										echo '<a href="' . esc_url( $action['url'] ) . '" class="btn btn-base-outline btn-sm woocommerce-button- button- ' . sanitize_html_class( $key ) . '">' . esc_html( $action['name'] ) . '</a>';
									}
									echo "</div>";
								}*/
								?>
							<?php endif; ?>
						</td>
					<?php endforeach; ?>
				</tr>
				<?php
			}
			?>
		</tbody>
	</table>

	<?php do_action( 'woocommerce_before_account_orders_pagination' ); ?>

	<?php if ( 1 < $customer_orders->max_num_pages ) : ?>
		<div class="woocommerce-pagination woocommerce-pagination--without-numbers woocommerce-Pagination">
			<?php if ( 1 !== $current_page ) : ?>
				<a class="btn btn-base woocommerce-button woocommerce-button--previous woocommerce-Button woocommerce-Button--previous button" href="<?php echo esc_url( wc_get_endpoint_url( 'orders', $current_page - 1 ) ); ?>"><?php esc_html_e( 'Previous', 'woocommerce' ); ?></a>
			<?php endif; ?>

			<?php if ( intval( $customer_orders->max_num_pages ) !== $current_page ) : ?>
				<a class="btn btn-base woocommerce-button woocommerce-button--next woocommerce-Button woocommerce-Button--next button" href="<?php echo esc_url( wc_get_endpoint_url( 'orders', $current_page + 1 ) ); ?>"><?php esc_html_e( 'Next', 'woocommerce' ); ?></a>
			<?php endif; ?>
		</div>
	<?php endif; ?>



<?php else : ?>



	<div class="container">
		<div class="d-flex align-items-center justify-content-center p-5 my-5 flex-column">
		    <div class="d-flex align-items-center justify-content-center py-5 flex-column text-white">
			    <i class="icon far fa-credit-card fa-4x text-light"></i>
			    <?php esc_html_e( 'No order has been made yet.', 'woocommerce' ); ?>
	            <a class="btn btn-outline-light btn-extend mt-4" href="<?php echo esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ) ); ?>">
					<?php esc_html_e( 'Start Shopping!', 'woocommerce' ); ?>
				</a>
	        </div>
	    </div>
	</div>


<?php endif; ?>


<script>
	function ajax_wc_order_list(response, vars, form){
		_alert(response, "md", "my-orders modal-fullscreen", "Order No:"+vars["order_number"]);
	}
	$( document ).ready(function() {
		$('.btn-order-detail').on('click', function(e){
		    e.preventDefault();
		    var query = new ajax_query();
				query.method = "wc_order_list";
				query.vars   = {
					order_number : $(this).data('order-number')
			    };
				query.form   = "";
				query.request();
	    });
	});
</script>

<?php do_action( 'woocommerce_after_account_orders', $has_orders ); ?>
