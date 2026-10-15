<?php
/**
 * Product quantity inputs — Custom override
 * Pill/rounded +/- butonlu modern quantity stepper
 *
 * @version 10.1.0 (WC base)
 */

defined( 'ABSPATH' ) || exit;

$label = ! empty( $args['product_name'] )
    ? sprintf( esc_html__( '%s quantity', 'woocommerce' ), wp_strip_all_tags( $args['product_name'] ) )
    : esc_html__( 'Quantity', 'woocommerce' );

do_action( 'woocommerce_before_quantity_input_field' );
?>

<div class="quantity sh-qty-stepper<?php
    // General settings'den stepper class'larını oku
    $qty_cfg = (array) get_option( 'options_qty_stepper', [] );
    $extra_classes = '';
    if ( ! empty( $qty_cfg['size'] ) && $qty_cfg['size'] !== 'md' ) {
        $extra_classes .= ' sh-qty-stepper-' . sanitize_html_class( $qty_cfg['size'] );
    }
    if ( ! empty( $qty_cfg['variant'] ) ) {
        $extra_classes .= ' sh-qty-stepper-' . sanitize_html_class( $qty_cfg['variant'] );
    }
    echo esc_attr( $extra_classes );
?>" data-html-whitelist=".sh-qty-stepper-*,.qty-stepper__btn,.qty-stepper__input"><?php // phpcs:ignore ?>
    <button
        type="button"
        class="qty-stepper__btn qty-stepper__btn--minus"
        aria-label="<?php esc_attr_e( 'Decrease quantity', 'woocommerce' ); ?>"
        data-qty-step="-1"
        tabindex="-1">
        <span aria-hidden="true">&#8722;</span>
    </button>

    <label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_attr( $label ); ?></label>
    <input
        type="text"
        inputmode="numeric"
        pattern="[0-9]*"
        <?php echo $readonly ? 'readonly="readonly"' : ''; ?>
        id="<?php echo esc_attr( $input_id ); ?>"
        class="qty-stepper__input <?php echo esc_attr( join( ' ', (array) $classes ) ); ?>"
        name="<?php echo esc_attr( $input_name ); ?>"
        value="<?php echo esc_attr( $input_value ); ?>"
        aria-label="<?php esc_attr_e( 'Product quantity', 'woocommerce' ); ?>"
        min="<?php echo esc_attr( $min_value ); ?>"
        <?php if ( 0 < $max_value ) : ?>max="<?php echo esc_attr( $max_value ); ?>"<?php endif; ?>
        <?php if ( ! $readonly ) : ?>
            step="<?php echo esc_attr( $step ); ?>"
            placeholder="<?php echo esc_attr( $placeholder ); ?>"
            autocomplete="<?php echo esc_attr( isset( $autocomplete ) ? $autocomplete : 'on' ); ?>"
        <?php endif; ?>
    />

    <button
        type="button"
        class="qty-stepper__btn qty-stepper__btn--plus"
        aria-label="<?php esc_attr_e( 'Increase quantity', 'woocommerce' ); ?>"
        data-qty-step="1"
        tabindex="-1">
        <span aria-hidden="true">&#43;</span>
    </button>
</div>

<?php do_action( 'woocommerce_after_quantity_input_field' ); ?>
