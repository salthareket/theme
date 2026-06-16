<?php
/**
 * Apply filters button
 *
 * @author  YITH <plugins@yithemes.com>
 * @package YITH\AjaxProductFilter\Templates\Filters
 * @version 4.0.0
 */

/**
 * Variables available for this template:
 *
 * @var $preset YITH_WCAN_Preset
 */

if ( ! defined( 'YITH_WCAN' ) ) {
	exit;
} // Exit if accessed directly

/**
 * APPLY_FILTERS: yith_wcan_filter_button_class
 *
 * Filters "Apply filters" button classes.
 *
 * @param string $classes Button classes. Default: "btn btn-primary apply-filters"
 *
 * @return string
 */
$button_class = apply_filters( 'yith_wcan_filter_button_class', 'btn btn-primary apply-filters' );

?>

<button class="<?php echo esc_attr( $button_class ); ?>">
	<?php
	/**
	 * APPLY_FILTERS: yith_wcan_filter_button
	 *
	 * Filters "Apply filters" button label.
	 *
	 * @param string $label Button label. Default: "Apply filters".
	 *
	 * @return string
	 */
	echo esc_html( apply_filters( 'yith_wcan_filter_button', _x( 'Apply filters', '[FRONTEND] Filter button for preset shortcode', 'yith-woocommerce-ajax-navigation' ) ) );
	?>
</button>
