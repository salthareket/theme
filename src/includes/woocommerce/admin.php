<?php

/**
 * @snippet       Add Custom Field to Product Variations - WooCommerce
 * @how-to        Watch tutorial @ https://businessbloomer.com/?p=19055
 * @sourcecode    https://businessbloomer.com/?p=73545
 * @author        Rodolfo Melogli
 * @compatible    WooCommerce 3.5.6
 * @donate $9     https://businessbloomer.com/bloomer-armada/
 */
 
// -----------------------------------------
// 1. Add custom field input @ Product Data > Variations > Single Variation
 
add_action( 'woocommerce_variation_options_pricing', 'bbloomer_add_custom_field_to_variations', 10, 3 );
 
function bbloomer_add_custom_field_to_variations( $loop, $variation_data, $variation ) {
	woocommerce_wp_text_input( array(
			'id' => 'variation_title[' . $loop . ']',
			'class' => 'short',
			'label' => __( 'Title', 'woocommerce' ),
			'value' => get_post_meta( $variation->ID, 'variation_title', true )
		)
	);
}
 
// -----------------------------------------
// 2. Save custom field on product variation save
 
add_action( 'woocommerce_save_product_variation', 'bbloomer_save_custom_field_variations', 10, 2 );
 
function bbloomer_save_custom_field_variations( $variation_id, $i ) {
	$custom_field = $_POST['variation_title'][$i];
	if ( isset( $custom_field ) ) update_post_meta( $variation_id, 'variation_title', esc_attr( $custom_field ) );
}
 
// -----------------------------------------
// 3. Store custom field value into variation data
 
add_filter( 'woocommerce_available_variation', 'bbloomer_add_custom_field_variation_data' );
 
function bbloomer_add_custom_field_variation_data( $variations ) {
	$variations['variation_title'] = '<div class="woocommerce_custom_field"><span>' . get_post_meta( $variations[ 'variation_id' ], 'variation_title', true ) . '</span></div>';
	return $variations;
}










/** 
 * @snippet       Show Custom Filter @ WooCommerce Products Admin
 * @how-to        Get CustomizeWoo.com FREE 
 * @sourcecode    https://businessbloomer.com/?p=78136
 * @author        Rodolfo Melogli 
 * @compatible    Woo 3.5.3
 * @donate $9     https://businessbloomer.com/bloomer-armada/
 */
function bbloomer_filter_by_custom_taxonomy_dashboard_products( $output ) {
  global $wp_query;
  $attributes = array("renk", "cinsiyet");
  foreach ($attributes as $key => $attribute) {
  	    $output .= wc_product_dropdown_categories( 
		    array(
			   'show_option_none' => $attribute,
			   'taxonomy' => 'pa_'.$attribute,
			   'name' => 'pa_'.$attribute,
			   'selected' => isset( $wp_query->query_vars['pa_'.$attribute] ) ? $wp_query->query_vars['pa_'.$attribute] : '',
		    )
	    );
  }
  return $output;
}
//add_filter( 'woocommerce_product_filters', 'bbloomer_filter_by_custom_taxonomy_dashboard_products' 









/* Modify comments admin page columns */
//add_filter( 'manage_edit-comments_columns', 'rudr_add_comments_columns' );
function rudr_add_comments_columns( $my_cols ){
	/*$my_cols = array(
		'cb' => '', // do not forget about the CheckBox
		'author' => 'Author',
		'comment' => 'Comment',
		'm_comment_id' => 'ID', // added 
		'm_parent_id' => 'Parent ID', // added
		'response' => 'In reply to',
		'date' => 'Date'
	);*/
	$misha_columns = array(
		'comment_json' => 'Comment',
		'comment_tour' => 'Commented Tour Package'
	);
	$my_cols = array_slice( $my_cols, 0, 3, true ) + $misha_columns + array_slice( $my_cols, 3, NULL, true );
	unset( $my_cols['comment'] );
	unset( $my_cols['response'] );
	return $my_cols;
}
add_action( 'manage_comments_custom_column', 'rudr_add_comment_columns_content', 10, 2 );
function rudr_add_comment_columns_content( $column, $comment_ID ) {
	global $comment;
	switch ( $column ) :
		case 'comment_json' : {
			if(!empty($comment->comment_content)){
				$content = json_decode($comment->comment_content);
				foreach($content as $key => $item){
					echo "<b>".$item->title."</b><br>";
					echo $item->comment;
					if($key < count($content)-1){
						echo "<br><br>";
					}
				}
			}
			break;
		}
		case 'comment_tour' : {
			$product_id = $comment->comment_post_ID;
			$tour_plan_id = get_field('tour_plan_id', $product_id);
			echo "<a href='".get_permalink($tour_plan_id)."' target='_blank'>".get_the_title($tour_plan_id)."</a>";
			break;
		}
	endswitch;
}






//hide shipping detaild from admin profile page
function hide_admin_shipping_details() { 
    global $pagenow;
    if( is_admin() && ($pagenow == "user-edit.php" || $pagenow == "profile.php")) { ?>
         <style>
           #fieldset-billing + h2,
           #fieldset-shipping{ display: none !important } 
        </style>
    <?php 
    }
}

//hide yoast on profile page
function hide_yoast_profile() {
  echo '<style>
          .yoast-settings {
               display: none;
          }
        </style>';
}

function hide_personal_options(){
	echo "\n" . '<script type="text/javascript">jQuery(document).ready(function($) { $(\'form#your-profile > h3:first\').hide(); $(\'form#your-profile > table:first\').hide(); $(\'form#your-profile\').show(); });</script>' . "\n";
}

//hide contact methods on proflile page
function hide_contact_methods( $contact_methods ){
	unset($contact_methods['facebook']);
	unset($contact_methods['instagram']);
	unset($contact_methods['linkedin']);
	unset($contact_methods['tumblr']);
	unset($contact_methods['myspace']);
	unset($contact_methods['pinterest']);
	unset($contact_methods['twitter']);
	unset($contact_methods['soundcloud']);
	unset($contact_methods['youtube']);
	unset($contact_methods['wikipedia']);
	return $contact_methods;
}

//hide personal Options
class hide_biography{
    public static function start(){
        $action = ( IS_PROFILE_PAGE ? 'show' : 'edit' ) . '_user_profile';
        add_action( $action, array ( __CLASS__, 'stop' ) );
        ob_start();
    }
    public static function stop(){
        $html = ob_get_contents();
        ob_end_clean();
        // remove the headline
        $headline = __( IS_PROFILE_PAGE ? 'About Yourself' : 'About the user' );
        $html = str_replace( '<h2>' . $headline . '</h2>', '', $html );
        // remove the table row
        $html = preg_replace( '~<tr>\s*<th><label for="description".*</tr>~imsUu', '', $html );
        $html = preg_replace( '~<tr class="user-description-wrap">\s*.*</tr>~imsUu', '', $html );
        print $html;
    }
}