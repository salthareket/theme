<?php

// Add Title for variations
add_action( 'woocommerce_variation_options', 'bbloomer_add_custom_field_to_variations', 0, 3 );
function bbloomer_add_custom_field_to_variations( $loop, $variation_data, $variation ) {
	woocommerce_wp_text_input( array(
			'id' => 'variation_title[' . $loop . ']',
			'class' => 'short',
			'label' => __( 'Title', 'woocommerce' ),
			'value' => get_post_meta( $variation->ID, 'variation_title', true ),
			'wrapper_class' => 'form-row',
		)
	);
}
add_action( 'woocommerce_save_product_variation', 'bbloomer_save_custom_field_variations', 10, 2 );
function bbloomer_save_custom_field_variations( $variation_id, $i ) {
	$custom_field = $_POST['variation_title'][$i];
	if ( isset( $custom_field ) ) update_post_meta( $variation_id, 'variation_title', esc_attr( $custom_field ) );
}
add_filter( 'woocommerce_available_variation', 'bbloomer_add_custom_field_variation_data' );
function bbloomer_add_custom_field_variation_data( $variations ) {
	$variations['variation_title'] = '<div class="woocommerce_custom_field"><span>' . get_post_meta( $variations[ 'variation_id' ], 'variation_title', true ) . '</span></div>';
	return $variations;
}



// add product attribute filters to admin product list
add_filter( 'woocommerce_product_filters', 'bbloomer_filter_by_custom_taxonomy_dashboard_products' );
function bbloomer_filter_by_custom_taxonomy_dashboard_products( $output ) {
	global $wp_query;

	$attribute_taxonomies = wc_get_attribute_taxonomies();
	if ( empty( $attribute_taxonomies ) ) return $output;

	foreach ( $attribute_taxonomies as $attribute ) {
		$taxonomy = 'pa_' . $attribute->attribute_name;

		if ( taxonomy_exists( $taxonomy ) ) {
			$output .= wc_product_dropdown_categories( 
				array(
					'show_option_none' => ucfirst($attribute->attribute_label ?: $attribute->attribute_name),
					'taxonomy'         => $taxonomy,
					'name'             => $taxonomy,
					'selected'         => isset( $_GET[ $taxonomy ] ) ? $_GET[ $taxonomy ] : '',
				)
			);
		}
	}

	return $output;
}
add_action('parse_query', function( $query ) {
	if ( !is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'product' ) return;

	$attributes = wc_get_attribute_taxonomies();

	foreach ( $attributes as $attribute ) {
		$taxonomy = 'pa_' . $attribute->attribute_name;

		if ( isset($_GET[$taxonomy]) && $_GET[$taxonomy] ) {
			$term = sanitize_text_field($_GET[$taxonomy]);

			// Varyasyonlardan parent ürünleri bul
			$variation_ids = get_posts([
				'post_type' => 'product_variation',
				'post_status' => 'publish',
				'numberposts' => -1,
				'fields' => 'ids',
				'tax_query' => [
					[
						'taxonomy' => $taxonomy,
						'field' => 'slug',
						'terms' => $term,
					],
				],
			]);

			$parent_ids = array_unique(array_map('wp_get_post_parent_id', $variation_ids));

			// Simple product’ları bul
			$simple_ids = get_posts([
				'post_type' => 'product',
				'post_status' => 'publish',
				'numberposts' => -1,
				'fields' => 'ids',
				'tax_query' => [
					[
						'taxonomy' => $taxonomy,
						'field' => 'slug',
						'terms' => $term,
					],
				],
			]);

			// Parent + Simple birleştir
			$all_ids = array_unique(array_merge($parent_ids, $simple_ids));

			// Ana sorguya bu ID'leri ver
			$query->set('post__in', $all_ids);
		}
	}
});



//TinyMCE for variation descriptions:
add_action( 'admin_footer', function () {
	global $pagenow;
	if ( $pagenow !== 'post.php' && $pagenow !== 'post-new.php' ) return;
	?>
	<script>
		jQuery(document).ready(function($) {
			function initWYSIWYGIfNeeded($textarea) {
				const id = $textarea.attr('id');
				if (!id || typeof tinymce === 'undefined') return;

				if (tinymce.get(id)) return;

				tinymce.init({
					selector: '#' + id,
					menubar: false,
					plugins: 'link lists',
					toolbar: 'bold italic underline | bullist numlist | link unlink | removeformat',
					branding: false,
					height: 250,
					setup: function(editor) {
						editor.on('change', function() {
							editor.save(); // textarea'ya yansıt
						});
					}
				});
			}

			// İlk açıldığında visible olanları başlat (mesela varsayılan 1. varyasyon)
			$('.woocommerce_variation').each(function(){
				const $description = $(this).find('textarea[id^="variable_description"]');
				if ($description.is(':visible')) {
					initWYSIWYGIfNeeded($description);
				}
			});

			// Toggle collapse açıldığında tetiklenir
			$(document).on('click', '.woocommerce_variation.open > h3', function(){
				const $parent = $(this).next('.woocommerce_variable_attributes');
				const $description = $parent.find('textarea[id^="variable_description"]');

				setTimeout(function(){
					if ($description.length && $description.is(':visible') && !$description.hasClass("inited")) {
						initWYSIWYGIfNeeded($description);
						$description.addClass("inited");
					}
				}, 100); // DOM renderı için ufak gecikme
			});
		});
	</script>
	<?php
});




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
