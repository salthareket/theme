<?php
/**
 * Open mobile filters modal button
 *
 * @author  YITH <plugins@yithemes.com>
 * @package YITH\AjaxProductFilter\Templates\Filters
 * @version 4.0.0
 */

/**
 * Variables available for this template:
 *
 * @var $label  string
 * @var $preset YITH_WCAN_Preset
 */

if ( ! defined( 'YITH_WCAN' ) ) {
	exit;
} // Exit if accessed directly
/**
 * APPLY_FILTERS: yith_wcan_filter_open_button_class
 *
 * Filters "filters" button classes.
 *
 * @param string $classes Button classes. Default: "btn btn-primary yith-wcan-filters-opener"
 *
 * @return string
 */
$button_class = apply_filters( 'yith_wcan_filter_open_button_class', 'btn btn-primary yith-wcan-filters-opener' );

?>

<button type="button" class="<?php echo esc_attr( $button_class ); ?>" data-target="<?php echo $preset ? esc_attr( 'preset_' . $preset->get_id() ) : ''; ?>" >
	<i class="filter-icon"></i>
	<?php echo esc_html( $label ); ?>
</button>
