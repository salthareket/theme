<?php
use Timber\Timber;
use Timber\Loader;

use SaltHareket\Theme;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

/*acf Google Maps key*/
if($GLOBALS["google_maps_api_key"]){
	acf_update_setting('google_api_key', $GLOBALS['google_maps_api_key']);
}


//acf json save & load folders
add_filter('acf/settings/save_json', 'my_acf_json_save_point');
function my_acf_json_save_point( $path ) {
    $path = get_stylesheet_directory() . '/acf-json';
    return $path;  
}
add_filter('acf/settings/load_json', 'my_acf_json_load_point');
function my_acf_json_load_point( $paths ) {
    unset($paths[0]);
    $paths[] = get_stylesheet_directory() . '/acf-json';
    return $paths;
}





// contact main location
function acf_main_location($locations){
	if(!empty($locations)){
	   foreach($locations as $location){
	   	  if($location["contact"]["main"]){
	   	  	 return $location["contact"];
	   	  	 break;
	   	  }
	   }
	}
}
function acf_get_contacts($type=""){
	$posts = array();
	//if($type == "main" || $type == "standard"){
		
		$args = array(
			"post_type" => "contact",
			//"numberposts" => ($type=="main"?1:-1),
			'orderby' => "menu_order"
		);
		if(!empty($type)){
			$category = get_option("contact_type_".$type);
			$args["tax_query"] = array(
				array(
					"taxonomy" => "contact-type",
					"field" => "term_id",
		            "terms" => [$category],
		            "operator" => "IN"
				)
			);
		}
		$posts = Timber::get_posts($args);
		if($posts && $type == "main"){
           $posts = $posts[0];
		}
	//}
	return $posts;
}
function acf_get_contact_related($post_id=0, $post_type="post"){
	$args = array(
			"post_type"   => $post_type,
			'orderby'     => "menu_order",
			"numberposts" => 1,
			"meta_query"  => array(
				array(
					"key" => "contact",
					"value" => array($post_id),
		            "operator" => "IN"
				)
			)
	);
	$posts = Timber::get_posts($args);
	if($posts){
    	$posts = $posts[0];
	}
    return $posts;
}
function acf_get_accounts($post=array()){
	$accounts = array();
	if(isset($post->ID)){
		$accounts = get_field("contact_accounts", $post->ID);
	}
    return $accounts;
}
function get_contact_form($slug=""){
	$arr = array();
	$forms = get_field("forms", "option");
	if($forms){
		foreach($forms as $form){
			if($slug ==$form["slug"]){
				$arr = array(
					"id"          => $form["form"],
		            "title"       => $form["title"],
		            "description" => $form["description"]
				);			
			}
		}		
	}
	return $arr;
}
function get_contact_forms($slug=""){
	if(!empty($slug)){
		return get_contact_form($slug);
	}
	$arr = array();
	$forms = get_field("forms", "option");
	if($forms){
		foreach($forms as $form){
			$arr[$form["slug"]] = array(
				"id"          => $form["form"],
	            "title"       => $form["title"],
	            "description" => $form["description"]
			);
		}
	}
	return $arr;
}




function acf_set_thumbnail_condition($post_id){
	$post_types = get_post_types(); // Tüm kayıtlı post tiplerini al
    $supported_post_types = []; // Thumbnail desteği olanları burada tut
    foreach ($post_types as $post_type) {
        if (post_type_supports($post_type, 'thumbnail')) {
            $supported_post_types[] = $post_type; // Thumbnail desteği varsa listeye ekle
        }
    }
	$post_type = get_post_type( $post_id );
	if(in_array($post_type, $supported_post_types)){
	    return true;
	}else{
	    return false;
	}
}

/*Set as featured image custom image fields value
function acf_set_featured_image( $value, $post_id, $field ){
	error_log("hobeeeen");
		if(acf_set_thumbnail_condition($post_id)){
			if($field['type'] == "qtranslate_image"){
			   $languages = qtranxf_getSortedLanguages();
			   $value = qtranxf_use($languages[0], $value, false, false);
			}
		    if($value != '' && $value != null && !empty($value)){
				delete_post_thumbnail( $post_id);
	            error_log(json_encode($value));
                if(!empty($value)){
					if(is_array($value)){
					 	error_log("array valu:".$value[0]);
						$meta_id = add_post_meta($post_id, '_thumbnail_id', $value[0]);
					}else{
				        $meta_id = add_post_meta($post_id, '_thumbnail_id', $value);
					}
					error_log("meta id:".$meta_id);                        	
                }
		    }else{
				delete_post_thumbnail( $post_id );
			};
		};
	    return $value;
}
if(isset($GLOBALS["acf_featured_image_fields"]) && is_array($GLOBALS["acf_featured_image_fields"]) && count($GLOBALS["acf_featured_image_fields"]) > 0){
	$fields = $GLOBALS["acf_featured_image_fields"];
	foreach($fields as $field){
		add_filter('acf/update_value/name='.$field, 'acf_set_featured_image', 10, 3);
	}
}
*/



function create_options_menu($options){
		if(array_iterable($options)){
			$menu_title = $options['title'];
			$redirect = $options['redirect'];
			acf_add_options_page(array(
				'page_title' 	=> $menu_title,
				'menu_title'	=> $menu_title,
				'menu_slug' 	=> sanitize_title($menu_title),
				'capability'	=> 'edit_posts',
				'redirect'		=> $redirect
			));
			$menu_children=$options['children'];
			if($menu_children){
				create_options_menu_children($menu_title, $menu_children);
			}
		}
};
/*function create_options_menu_children($menu_title, $options) {
    for ($i = 0; $i < count($options); $i++) {
        if (is_array($options[$i])) {
            create_options_menu_children($options[$i]["title"], $options[$i]["children"]);
        } else {
            // ACF options sub-page oluşturuluyor
            $sub_page_slug = sanitize_title($options[$i]);

            // Polylang varsa URL'ye &lang=all parametresi ekle
            if (function_exists('pll_current_language')) {
                $sub_page_slug .= '&lang=all';
            }

            acf_add_options_sub_page(array(
                'page_title'    => $options[$i],
                'menu_title'    => $options[$i],
                'menu_slug'     => $sub_page_slug,
                'parent_slug'   => sanitize_title($menu_title),
            ));
        }
    }
}*/
function create_options_menu_children($menu_title, $options){
	for($i = 0; $i < count($options); $i++){
		if(is_array($options[$i])){
		    create_options_menu_children($options[$i]["title"], $options[$i]["children"]);
		}else{
			acf_add_options_sub_page(array(
				'page_title' 	=> $options[$i],
				'menu_title'	=> $options[$i],
				'menu_slug' 	=> sanitize_title($options[$i]),
				'parent_slug'	=> sanitize_title($menu_title),
			));			
		}
	}
}


function acf_map_data($location, $className="", $id="", $icon=""){
	$result = array();
	if($location){
	    $staticMarker = 'color:red%7C' . $location['lat'] . ',' . $location['lng'];
		if(!empty($icon)){
			$staticMarker = "icon:".$icon."%7C".$location['lat'].",".$location['lng'];
		}
		$result = array(
			       'lng' => $location['lng'],
				   'lat' => $location['lat'],
				   'zoom' => $location['zoom'],
				   'icon' => $icon,
			       'src' => 'http://maps.googleapis.com/maps/api/staticmap?center=' . urlencode( $location['lat'] . ',' . $location['lng'] ). '&zoom='.$location['zoom'].'&size=800x800&maptype=roadmap&sensor=false&markers='.$staticMarker.'&key='.$GLOBALS['google_maps_api_key'],
				   'url' => 'http://www.google.com/maps/@'. $location['address'] ,
				   'url_iframe' => 'https://www.google.com/maps/embed/v1/place?key='.$GLOBALS['google_maps_api_key'].'&q='.$location['lat'] . ',' . $location['lng'],
				   'embed' => '<div id="'.$id.'" class="'.$className.' map-google" data-lat="'.$location['lat'].'" data-lng="'.$location['lng'].'" data-zoom="'.$location['zoom'].'" data-icon="'.$icon.'"></div>'
			   );			
	}
	return $result;
}


function acf_get_field_key( $field_name, $post_id ) {
	global $wpdb;
	$acf_fields = $wpdb->get_results( $wpdb->prepare( "SELECT ID,post_parent,post_name FROM $wpdb->posts WHERE post_excerpt=%s AND post_type=%s" , $field_name , 'acf-field' ) );
	// get all fields with that name.
	switch ( count( $acf_fields ) ) {
		case 0: // no such field
			return false;
		case 1: // just one result. 
			return $acf_fields[0]->post_name;
	}
	// result is ambiguous
	// get IDs of all field groups for this post
	$field_groups_ids = array();
	$field_groups = acf_get_field_groups( array(
		'post_id' => $post_id,
	) );
	foreach ( $field_groups as $field_group )
		$field_groups_ids[] = $field_group['ID'];
	
	// Check if field is part of one of the field groups
	// Return the first one.
	foreach ( $acf_fields as $acf_field ) {
		if ( in_array($acf_field->post_parent,$field_groups_ids) )
			return $acf_field->post_name;
	}
	return false;
}








if(class_exists('ACFE')){
	/*add_action('acfe/validate_save_post/post_type=product', 'my_acfe_validate_save_page', 10, 2);
	function my_acfe_validate_save_page($post_id, $object){
		//set featured image
		$value = get_field('image', $post_id);
		$field = get_field_object('image', $post_id);
		acf_set_featured_image( $value, $post_id, $field );
		
		//set gallery images
		$value = get_field('product_gallery', $post_id);
		$field = get_field_object('gallery', $post_id);
		delete_post_meta($post_id, '_product_image_gallery');
		if($value != '' && $value != null && !empty($value)){
			$value = join(",", $value);
			add_post_meta($post_id, '_product_image_gallery', $value, true);
		}
	}*/
}else{
	//set featured image
	//add_filter('acf/update_value/name=image', 'acf_set_featured_image', 10, 3);            	
}




if (ENABLE_MULTILANGUAGE){

	add_filter('acf/settings/default_language', 'my_acf_settings_default_language');
	function my_acf_settings_default_language( $language ) {
		return ml_get_default_language();//$GLOBALS["language_default"];
	}

	add_filter('acf/settings/current_language', 'my_acf_settings_current_language');
	function my_acf_settings_current_language( $language ) {
		return $GLOBALS["language"];
	}

}



function acf_admin_colors_footer() { 
	$colors = [];
	$colors_file = THEME_STATIC_PATH . 'data/colors_mce.json';
	if(file_exists($colors_file)){
	    $colors = file_get_contents($colors_file);
	    $colors = json_decode($colors, true);
	    if($colors){
	    	$colors = array_keys($colors);
	    }
	}
	?>
	<script type="text/javascript">
	(function($) {
		acf.add_filter('color_picker_args', function( args, $field ){
			<?php 
			if($colors){
			?>
				let colors = <?php echo json_encode($colors);?>;
            <?
			}else{
			?>
				let colors = [];
		        let obj = getComputedStyle(document.documentElement);
		        let custom_colors = obj.getPropertyValue('--salt-colors').trim();
		        if(!IsBlank(custom_colors)){
		        	custom_colors = custom_colors.split(",");
		        	custom_colors.forEach(color => {
					    colors.push(obj.getPropertyValue('--bs-'+color.trim()).trim());
					});
		        }
	        <?php 
	        }
	        ?>
			args.palettes = colors
			return args;
		});
	})(jQuery);
	</script>
<?php }
add_action('acf/input/admin_footer', 'acf_admin_colors_footer');



// admin sayfasındaki acf label'ları seçili dilde göstermek için fix code.
function acf_load_field_translate($field) {
    if (ENABLE_MULTILANGUAGE == "qtranslate_xt" && is_admin()) {
	  	global $post;
	  	if(isset($post->ID)){
		  	if (get_post_type($post->ID) == 'acf-field-group') {
		     	return $field;
		  	}
		  	$field['label'] = qtranxf_useCurrentLanguageIfNotFoundUseDefaultLanguage($field['label']);	
	  	}
    }
  	return $field;
}
add_filter('acf/load_field', 'acf_load_field_translate');


function acf_dynamic_container($class="", $page_settings = array(), $manually = false){
	$offcanvas = false;
	if(isset($page_settings["add_offcanvas"])){
		$offcanvas = $page_settings["add_offcanvas"];
	}
	return $class.($offcanvas?"-fluid":"");
}



// Google maps
add_filter('acf/update_value/name=map_url', 'acf_map_embed_update', 10, 3);
function acf_map_embed_update( $value, $post_id, $field ) {
	if(strpos($value, "<iframe ") !== false){
		$value = preg_replace('/\\\\/', '', $value);
		$value = get_iframe_src( $value );
	}
	return $value;
}

add_action('acf/update_value', 'acf_map_lat_lng', 99, 3 ); 
function acf_map_lat_lng( $value, $post_id, $field ) {
	if( 'google_map' === $field['type'] && 'map' === $field['name'] ) {
		update_post_meta( $post_id, 'lat', $value['lat'] );
		update_post_meta( $post_id, 'lng', $value['lng'] );
	}
	if( 'lat' === $field['name'] && isset($value['lat']) ) {
		update_post_meta( $post_id, 'lat', $value['lat'] );
	}
	if( 'lng' === $field['name'] && isset($value['lng']) ) {
		update_post_meta( $post_id, 'lng', $value['lng'] );
	}
	return $value;
}

function acf_get_coordinates_from_embed_url($url){
	$coordinates = array();
	// Koordinatları çıkarmak için regex deseni
	$pattern = '/!3d([0-9.]+)!2d([0-9.]+)/';

	// Embed kodundan enlem ve boylam koordinatlarını çıkarın
	preg_match($pattern, $url, $matches);

	if (count($matches) >= 3) {
	    $coordinates["lat"] = $matches[1];
	    $coordinates["lng"] = $matches[2];
	    return $coordinates;
	} 
	return false;
}





// generate languages options for select menu
function acf_load_languages_field_choices( $field ) {
    $field['choices'] = array();
    $choices = get_all_languages();
    if( is_array($choices) ) {
        foreach( $choices as $choice ) {
            $field['choices'][ $choice["lang"] ] = $choice["name"];
        }        
    }
    return $field;
}
add_filter('acf/load_field/name=languages', 'acf_load_languages_field_choices');




// General Settings Condition
add_filter('acf/load_field/name=enable_ecommerce', 'acf_general_option_enable_ecommerce');
function acf_general_option_enable_ecommerce($field) {
	if (ENABLE_ECOMMERCE) {
		$field['wrapper']['class'] = 'hidden';
	}else{
		$field['wrapper']['class'] = '';
	}
	return $field;
}

add_filter('acf/load_field/name=enable_cart', 'acf_general_option_enable_cart');
function acf_general_option_enable_cart($field) {
	if (!ENABLE_ECOMMERCE) {
		$field['wrapper']['class'] = 'hidden';
	}else{
		$field['wrapper']['class'] = '';
	}
	return $field;
}

add_filter('acf/load_field/name=enable_woo_api', 'acf_general_option_enable_woo_api');
function acf_general_option_enable_woo_api($field) {
	if (!ENABLE_ECOMMERCE) {
		$field['wrapper']['class'] = 'hidden';
	}else{
		$field['wrapper']['class'] = '';
	}
	return $field;
}

add_filter('acf/load_field/name=breadcrumb_add_product_brand', 'acf_general_option_breadcrumb_add_product_brand');
function acf_general_option_breadcrumb_add_product_brand($field) {
	if (!ENABLE_ECOMMERCE) {
		$field['wrapper']['class'] = 'hidden';
	}else{
		$field['wrapper']['class'] = '';
	}
	return $field;
}

add_filter('acf/load_field/name=breadcrumb_add_product_taxonomy', 'acf_general_option_breadcrumb_add_product_taxonomy');
function acf_general_option_breadcrumb_add_product_taxonomy($field) {
	if (!ENABLE_ECOMMERCE) {
		$field['wrapper']['class'] = 'hidden';
	}else{
		$field['wrapper']['class'] = '';
	}
	return $field;
}

add_filter('acf/load_field/name=remove_woocommerce_styles', 'acf_general_option_remove_woocommerce_styles');
function acf_general_option_remove_woocommerce_styles($field) {
	if (!ENABLE_ECOMMERCE) {
		$field['wrapper']['class'] = 'hidden';
	}else{
		$field['wrapper']['class'] = '';
	}
	return $field;
}





function acf_generate_id($length = 12) {
    $characters = '0123456789abcdef';
    $id = '';
    for ($i = 0; $i < $length; $i++) {
        $random_index = mt_rand(0, strlen($characters) - 1);
        $id .= $characters[$random_index];
    }
    return $id;
}




function acf_get_raw_value($post_id, $field_name, $field_group_name, $index=0){ // $index required for repeater
	if(isset($field_group_name)){
	    $index = isset($index)?$index."_":"";
		$meta_key = $field_group_name."_".$index.$field_name;
	}else{
        $meta_key = $field_name;
	}
	global $wpdb;
	$value = $wpdb->get_var("select meta_value from wp_postmeta where post_id=".$post_id." and meta_key='".$meta_key."'");
	if(!empty($value) && ENABLE_MULTILANGUAGE){
		$lang = qtranxf_getLanguage();
		$value = qtranxf_split($value);
		if(isset($value[$lang])){
			$value = $value[$lang];
		}
	}
	return $value;
}




function get_archive_field($field = "", $post_type = "post"){
	return get_field($field, $post_type.'_options');
}







if(ENABLE_ECOMMERCE){
	//another solutions for below:
	//https://remicorson.com/mastering-woocommerce-products-custom-fields/
	//https://remicorson.com/woocommerce-custom-fields-for-variations/

	// Render fields at the bottom of variations - does not account for field group order or placement.
	add_action( 'woocommerce_product_after_variable_attributes', function( $loop, $variation_data, $variation ) {
	    global $abcdefgh_i; // Custom global variable to monitor index
	    $abcdefgh_i = $loop;
	    // Add filter to update field name
	    add_filter( 'acf/prepare_field', 'acf_prepare_field_update_field_name' );
	    
	    // Loop through all field groups
	    $acf_field_groups = acf_get_field_groups();
	    foreach( $acf_field_groups as $acf_field_group ) {
	        foreach( $acf_field_group['location'] as $group_locations ) {
	            foreach( $group_locations as $rule ) {
	                // See if field Group has at least one post_type = Variations rule - does not validate other rules
	                if( $rule['param'] == 'post_type' && $rule['operator'] == '==' && $rule['value'] == 'product_variation' ) {
	                    // Render field Group
	                    acf_render_fields( $variation->ID, acf_get_fields( $acf_field_group ) );
	                    break 2;
	                }
	            }
	        }
	    }
	    
	    // Remove filter
	    remove_filter( 'acf/prepare_field', 'acf_prepare_field_update_field_name' );
	}, 10, 3 );

	// Filter function to update field names
	function  acf_prepare_field_update_field_name( $field ) {
	    global $abcdefgh_i;
	    $field['name'] = preg_replace( '/^acf\[/', "acf[$abcdefgh_i][", $field['name'] );
	    return $field;
	}
	    
	// Save variation data
	add_action( 'woocommerce_save_product_variation', function( $variation_id, $i = -1 ) {
	    // Update all fields for the current variation
	    if ( ! empty( $_POST['acf'] ) && is_array( $_POST['acf'] ) && array_key_exists( $i, $_POST['acf'] ) && is_array( ( $fields = $_POST['acf'][ $i ] ) ) ) {
	        foreach ( $fields as $key => $val ) {
	            update_field( $key, $val, $variation_id );
	        }
	    }
	}, 10, 2 );	
}



add_filter('acf_osm_marker_icon', function( $icon ) {
    $img = get_field("logo_marker", "option");
    if(empty($img)){
        return $icon;
    }
    if(isset($img["width"]) && isset($img["height"])){
    	$dims = array();
    	$dims["width"] = $img["width"];
    	$dims["height"] = $img["height"];
    }else{
    	$dims = get_attachment_dimensions_by_url($img);
    }
    return array(
        'iconUrl'     => $img,
        'iconSize'    => [ $dims["width"], $dims["height"] ],
        'iconAnchor'  => [ $dims["width"]/2, $dims["height"] ],
    );
});
function update_acf_post_object_field_choices($title, $post, $field, $post_id) {
    if ($field['name'] == 'contact') {
        $contact_types = wp_get_post_terms($post->ID, "contact-type",  array("fields" => "names"));
        if (!empty($contact_types) && !is_wp_error($contact_types)) {
            $contact_type = $contact_types[0];
            $title = $title . ' <strong class="text-primary">(' . $contact_type . ')</strong>';
        }
    }
    return $title ;    
}
add_filter( 'acf/fields/post_object/result', 'update_acf_post_object_field_choices', 10, 4 );


function dynamic_map_service_value($value, $post_id, $field) {
    $google_api_key = acf_get_setting('google_api_key');
    if ( empty( $google_api_key ) ) {
        return 'leaflet';
    }
    return $value;
}
add_filter('acf/load_value/name=map_service', 'dynamic_map_service_value', 10, 3);

function dynamic_map_service_update_value( $value, $post_id, $field ) {

	$previous_value = get_field( 'map_service', 'option' );
	$map_view_value = $_POST["acf"]["field_6735b65411079"];
	$map_view_previous_value = get_field( 'map_view', 'option' );

    if($value != $previous_value || $map_view_value != $map_view_previous_value){
	    update_option('options_map_service', $value);
	    update_option('options_map_view', $map_view_value);

    	$post_types = get_post_types( ['public' => true] );
    	$args = array(
		    'post_type' => $post_types,
		    'meta_query' => array(
		        'relation' => 'OR', // OR ile herhangi birini içeren postları çekiyoruz
		        array(
		            'key' => 'assets',
		            'value' => 'leaflet',
		            'compare' => 'LIKE'
		        ),
		        array(
		            'key' => 'assets',
		            'value' => 'markerclusterer',
		            'compare' => 'LIKE'
		        ),
		        array(
		            'key' => 'has_map',
		            'value' => 1,
		            'compare' => '='
		        )
		    )
		);
		$posts = get_posts($args);
		error_log(count($posts));
		if($posts){
			foreach($posts as $post){
		    	$extractor = new PageAssetsExtractor();
		        $extractor->on_save_post($post->ID, $post, true);				
			}
		}
    }
    return $value;
}
add_filter( 'acf/update_value/name=map_service', 'dynamic_map_service_update_value', 10, 3 );


function google_api_key_conditional_field( $field ) {
    $google_api_key = acf_get_setting('google_api_key');
    if ( empty( $google_api_key ) ) {
        return false;
    }
    return $field;
}
//Google Map field on Contact Details
add_filter('acf/prepare_field/key=field_6731e211669ab', 'google_api_key_conditional_field');

function google_api_key_found_conditional_field( $field ) {
    $google_api_key = acf_get_setting('google_api_key');
    if ( empty( $google_api_key ) ) {
        return true;
    }else{
    	return false;
    }
    return $field;
}
//Google Map field messageon Contact Details
add_filter('acf/prepare_field/key=field_673386f1d3129', 'google_api_key_found_conditional_field');









if(is_admin()){

	// page settings offcanvas menu template -> chhose menu -> chhose menu item for offcanvas menu root
	function acf_load_menu_field_choices( $field ) {
	    $field['choices'] = array();
	    $menus = get_terms('nav_menu', array('hide_empty' => false));
	    if ($menus) {
	    	$field['choices'][""] = __("Menü seçiniz");
	        foreach ($menus as $menu) {
	        	$menu_name = $menu->name;
	        	if(ENABLE_MULTILANGUAGE == "qtranslate_xt"){
	        		$menu_name = translateContent($menu_name);
	        	}
	        	$field['choices'][ $menu->term_id ] = $menu_name;
	        }
	    }
	    populate_menu_items_on_change();
	    return $field;
	}
	add_filter('acf/load_field/key=field_65d5fc059efb9', 'acf_load_menu_field_choices');

	/*function populate_menu_items_on_change($field) {
		if (defined('DOING_AJAX') && DOING_AJAX) {
	        return;
	    }
	    if (defined('DOING_CRON') && DOING_CRON) {
	        return;
	    }
	    ?>
	    <script type="text/javascript"> 
	        $ = jQuery.noConflict();
	        (function ($) {
	            $(document).on('change', '[name="acf[field_6425cbdb1b107][field_6425cae782d9a][field_65d5fc059efb9]"]', function () {
	                var selectedMenuId = $(this).val();
	                $('[name="acf[field_6425cbdb1b107][field_6425cae782d9a][field_65d5fd749efba]"]').html("<option>Loading...</option>");
	                $.ajax({
	                    type: 'POST',
	                    url: ajaxurl,
	                    data: {
	                        action: 'populate_menu_items',
	                        menu_id: selectedMenuId,
	                    },
	                    success: function (response) {
	                        $('[name="acf[field_6425cbdb1b107][field_6425cae782d9a][field_65d5fd749efba]"]').html(response);
	                    }
	                });
	            });
	        })(jQuery);
	    </script>
	    <?php
	}*
	add_action('acf/render_field/key=field_65d5fc059efb9', 'populate_menu_items_on_change');*/

function populate_menu_items_on_change() {
    // JavaScript kodunu inline olarak eklemek
    $script = <<<EOT
jQuery(document).ready(function($) {
    $(document).on('change', '[name="acf[field_6425cbdb1b107][field_6425cae782d9a][field_65d5fc059efb9]"]', function () {
        var selectedMenuId = $(this).val();
        $('[name="acf[field_6425cbdb1b107][field_6425cae782d9a][field_65d5fd749efba]"]').html("<option>Loading...</option>");
        $.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {
                action: 'populate_menu_items',
                menu_id: selectedMenuId,
            },
            success: function (response) {
                $('[name="acf[field_6425cbdb1b107][field_6425cae782d9a][field_65d5fd749efba]"]').html(response);
            }
        });
    });
});
EOT;

    // Bu scripti yalnızca gerekli sayfalarda ekleyin
    if (is_admin()) {
        wp_add_inline_script('jquery', $script);
    }
}
add_action('acf/render_field/key=field_65d5fc059efb9', function() {
    add_action('admin_enqueue_scripts', 'populate_menu_items_on_change');	
});

	


	function populate_menu_items_callback() {
	    $menu_id = $_POST['menu_id'];
	    $menu_items = wp_get_nav_menu_items($menu_id);
	    if ($menu_items) {
	        $levels = array(); // Tüm seviyeleri tutan bir dizi
	        echo '<option value="-1">' . __("Otomatik algıla") .'</option>';
	        echo '<option value="0">' . __("Tüm menüyü göster") .'</option>';
	        foreach ($menu_items as $item) {
	            $level = $item->menu_item_parent > 0 ? $levels[$item->menu_item_parent] + 1 : 0; // Her seviye için uygun level değeri belirleniyor
	            $levels[$item->ID] = $level; // Her itemin seviyesi saklanıyor
	            $indent = str_repeat('&nbsp;', $level * 4); // Her seviye için uygun sayıda boşluk ekleniyor
	            $item_title = $item->title;
	            if(ENABLE_MULTILANGUAGE == "qtranslate-xt"){
	                $item_title = translateContent($item_title);
	            }
	            //echo '<option value="' . $item->ID . '">' . $indent . $item_title .'</option>';
	            echo '<option value="' . $item->object_id . '">' . $indent . $item_title .'</option>';
	        }
	    }
	    die();
	}
	add_action('wp_ajax_populate_menu_items', 'populate_menu_items_callback');
	add_action('wp_ajax_nopriv_populate_menu_items', 'populate_menu_items_callback');

}







// Page Settings -> Offcanvas
function acf_offcanvas_classes($page_settings=array()){
	$classes = "";
	$size = $page_settings["offcanvas"]["size"];
	$width = $page_settings["offcanvas"]["width"];
	switch ($size) {
		case 'xs':
		    $classes = "col-12";
			break;
		case 'sm':
		    $classes = "col-12 col-sm-".$width;
			break;
		case 'md':
		    $classes = "col-12 col-md-".$width;
			break;
		case 'lg':
		    $classes = "col-12 col-lg-".$width;
			break;
		case 'xl':
		    $classes = "col-12 col-xl-".$width;
			break;
		case 'xxl':
		    $classes = "col-12 col-xxl-".$width;
			break;
		case 'xxxl':
		    $classes = "col-12 col-xxxl-".$width;
			break;
	}
	return $classes;
}
function acf_offcanvas_content_classes($page_settings=array()){
	$classes = "";
	$size = $page_settings["offcanvas"]["size"];
	$width = 12 - $page_settings["offcanvas"]["width"];
	switch ($size) {
		case 'xs':
		    $classes = "col-12";
			break;
		case 'sm':
		    $classes = "col-12 col-sm-".$width;
			break;
		case 'md':
		    $classes = "col-12 col-md-".$width;
			break;
		case 'lg':
		    $classes = "col-12 col-lg-".$width;
			break;
		case 'xl':
		    $classes = "col-12 col-xl-".$width;
			break;
		case 'xxl':
		    $classes = "col-12 col-xxl-".$width;
			break;
		case 'xxxl':
		    $classes = "col-12 col-xxxl-".$width;
			break;
	}
	return $classes;
}
add_filter('acf/load_field/key=field_6425cced6668a', 'acf_load_offcanvas_template_files');
function acf_load_offcanvas_template_files( $field ) {
	$handle = get_stylesheet_directory() . "/templates/partials/offcanvas/";
	$templates = array();// scandir($handle);
	if ($handle = opendir($handle)) {
	    while (false !== ($entry = readdir($handle))) {
	        if ($entry != "." && $entry != "..") {
	            $templates[] = $entry;
	        }
	    }
	    closedir($handle);
	}
    $field['choices'] = array();
    if( is_array($templates) ) {
        foreach( $templates as $template ) {
            $field['choices'][ "/templates/partials/offcanvas/".$template ] = $template;
        }        
    }
    return $field;
}


//add_filter( 'bea.aofp.get_default', '__return_false' );


function acf_get_theme_styles(){
	$theme_styles_latest = get_template_directory() . "/theme/static/data/theme-styles/latest.json";
    $theme_styles_defaults = SH_STATIC_PATH . "data/theme-styles-default.json";
        
    $theme_styles = [];
    if(file_exists($theme_styles_latest)){
    	$theme_styles = file_get_contents($theme_styles_latest);
    	$theme_styles = json_decode($theme_styles, true);
    }
    if(!$theme_styles){
    	$theme_styles = get_field("theme_styles", "option");
    }
    if(!$theme_styles && !isset($theme_styles["header"]["themes"]) && file_exists($theme_styles_defaults)){
    	$theme_styles = file_get_contents($theme_styles_defaults);
    	$theme_styles = json_decode($theme_styles, true);
    }
    return $theme_styles;
}


function acf_add_field_options($field) {

	$class = explode(" ", $field["wrapper"]["class"]);

	/* Using classes for fields:
	acf-margin-padding
	acf-font-family
	acf-font-size
	acf-text-transform
	acf-font-weight
	acf-bs-align-hr
	acf-align-hr
	acf-align-vr
	acf-width-height
	acf-heading
	acf-plyr-options
	acf-plyr-settings
	acf-body-classes
	acf-main-classes
	acf-ratio
	acf-language-list
	acf-template-custom || acf-template-custom-default
	acf-wp-themes
	acf-image-blend-mode
	acf-image-filter
	acf-menu-locations
	*/

	if(in_array("acf-breakpoints", $class)){
		$field["allow_custom"] = 0;
		$field["default_value"] = "xl";
		$field["type"] = "select";
		$field["multiple"] = 0;
		$field["allow_null"] = 0;
		$field["ajax"] = 0;
		$field["ui"] = 0;
		$field["search_placeholder"] = "";
		$field["return_format"] = "value";
		$options = array();
		foreach ($GLOBALS["breakpoints"] as $key => $breakpoint) {
			$options[$key] = $key;
		}
		$field['choices'] = array();
		foreach($options as $label) {
		    $field['choices'][$label] = $label;
		}
	}

	if(in_array("acf-columns", $class)){
		$field["allow_custom"] = 0;
	    $field["default_value"] = 1;
		$field["type"] = $field["type"]=="acf_bs_breakpoints"?$field["type"]:"select";
		$field["multiple"] = 0;
		$field["allow_null"] = 0;
		$field["ajax"] = 0;
		$field["ui"] = 0;
		$field["search_placeholder"] = "";
		$field["return_format"] = "value";
		foreach (range(1, 12) as $number) {
			$options[$number] = $number;
		}
		$field['choices'] = array();
		foreach ($options as $label) {
			$field['choices'][$label] = $label;
		}
    }

	if(in_array("acf-gaps", $class)){
		$field["allow_custom"] = 0;
	    $field["default_value"] = 0;
		$field["type"] = $field["type"]=="acf_bs_breakpoints"?$field["type"]:"select";
		$field["multiple"] = 0;
		$field["allow_null"] = 0;
		$field["ajax"] = 0;
		$field["ui"] = 0;
		$field["search_placeholder"] = "";
		$field["return_format"] = "value";
		foreach (range(0, 10) as $number) {
			$options[$number] = $number;
		}
		$field['choices'] = array();
		$field['choices'][0] = "None";
		$field['choices']["auto"] = "Auto";
		foreach ($options as $label) {
			$field['choices'][$label] = $label;
		}
    }

	if(in_array("acf-margin-padding", $class) || in_array("acf-margin-padding-responsive", $class)){
		if(!empty($field["parent"]) && $field["parent"] != 0){
            global $wpdb;
			$parent_name =  $wpdb->get_var($wpdb->prepare("SELECT post_excerpt FROM {$wpdb->posts} WHERE post_type = 'acf-field' AND post_name = %s", $field["parent"]));
				if($parent_name){
					if (in_array($parent_name, ["margin", "padding", "default_margin", "default_padding"])) {
						$field["allow_custom"] = 0;
						$field["default_value"] = "";
						$field["type"] = $field["type"]=="acf_bs_breakpoints"?$field["type"]:"select";
						$field["multiple"] = 0;
						$field["allow_null"] = 0;
						$field["ajax"] = 0;
						$field["ui"] = 0;
						$field["search_placeholder"] = "";
						$field["return_format"] = "value";
						$options = array("auto" => "auto");
						foreach (range(0, 12) as $number) {
							$options[$number] = $number;
						}
						$field['choices'] = array();
						if (in_array($parent_name, ["margin", "padding"])) {
							$field['choices']["default"] = "Default";
						}
						if (in_array("acf-margin-padding-responsive", $class)) {
							$field['choices']["responsive"] = "Responsive";
						}
						$field['choices'][""] = "None";
						foreach ($options as $label) {
							$field['choices'][$label] = $label;
						}
					}
			}	
		}
	}

	if(in_array("acf-heading", $class)){
		$field["allow_custom"] = 0;
		$field["default_value"] = "h3";
		$field["type"] = "select";
		$field["multiple"] = 0;
		$field["allow_null"] = 0;
		$field["ajax"] = 0;
		$field["ui"] = 0;
		$field["search_placeholder"] = "";
		$field["return_format"] = "value";
        $options = array(
        	"h1",
        	"h2", 
        	"h3",
        	"h4",
        	"h5",
        	"h6"
        );
		$field['choices'] = array();
		foreach($options as $label) {
		    $field['choices'][$label] = $label;
		}
	}
	if(in_array("acf-font-family", $class)){
		$field["allow_custom"] = 0;
		$field["default_value"] = "none";
		$field["type"] = "select";
		$field["multiple"] = 0;
		$field["allow_null"] = 0;
		$field["ajax"] = 0;
		$field["ui"] = 0;
		$field["search_placeholder"] = "";
		$field["return_format"] = "value";
	    $font_families = array(
	    	'##System Fonts'                                        => '##System Fonts',
			'Arial, Helvetica, sans-serif'                          => 'Arial, Helvetica, sans-serif',
			'"Arial Black", Gadget, sans-serif'                     => '"Arial Black", Gadget, sans-serif',
			'"Bookman Old Style", serif'                            => '"Bookman Old Style", serif',
			'"Comic Sans MS", cursive'                              => '"Comic Sans MS", cursive',
			'Courier, monospace'                                    => 'Courier, monospace',
			'Garamond, serif'                                       => 'Garamond, serif',
			'Georgia, serif'                                        => 'Georgia, serif',
			'Impact, Charcoal, sans-serif'                          => 'Impact, Charcoal, sans-serif',
			'"Lucida Console", Monaco, monospace'                   => '"Lucida Console", Monaco, monospace',
			'"Lucida Sans Unicode", "Lucida Grande", sans-serif'    => '"Lucida Sans Unicode", "Lucida Grande", sans-serif',
			'"MS Sans Serif", Geneva, sans-serif'                   => '"MS Sans Serif", Geneva, sans-serif',
			'"MS Serif", "New York", sans-serif'                    => '"MS Serif", "New York", sans-serif',
			'"Palatino Linotype", "Book Antiqua", Palatino, serif'  => '"Palatino Linotype", "Book Antiqua", Palatino, serif',
			'Tahoma,Geneva, sans-serif'                             => 'Tahoma, Geneva, sans-serif',
			'"Times New Roman", Times,serif'                        => '"Times New Roman", Times, serif',
			'"Trebuchet MS", Helvetica, sans-serif'                 => '"Trebuchet MS", Helvetica, sans-serif',
			'Verdana, Geneva, sans-serif'                           => 'Verdana, Geneva, sans-serif',
		);

		$fonts = array();
	    $fonts["##Icon Fonts"] = "##Icon Fonts";
		$fonts['Font Awesome 6 Pro'] = 'Font Awesome 6 Pro';
        $fonts['Font Awesome 6 Brands'] = 'Font Awesome 6 Brands';
        $font_families = array_merge( $fonts, $font_families );

		if (class_exists("YABE_WEBFONT")) {
			$custom_fonts = yabe_get_fonts();
			if($custom_fonts){
			   $fonts = array();
			   $fonts["##Custom Fonts"] = "##Custom Fonts";
			   foreach($custom_fonts as $font){
			   	   $name = $font["family"].(!empty($font["selector"])?", ".$font["selector"]:"");
			   	   $fonts[$name] = $font["title"];
			   }
			   $font_families = array_merge( $fonts, $font_families );
			}
		}
		
		$font_families = array_merge( array('##Defaults' => '##Defaults', 'initial' => 'initial', 'inherit' => 'inherit'), $font_families );
        $field['choices'] = array();
		foreach($font_families as $value => $label) {
		   $field['choices'][$value] = $label;
		}
	}
	if(in_array("acf-font-weight", $class)){
		$field["allow_custom"] = 0;
		$field["default_value"] = "400";
		$field["type"] = "select";
		$field["multiple"] = 0;
		$field["allow_null"] = 0;
		$field["ajax"] = 0;
		$field["ui"] = 0;
		$field["search_placeholder"] = "";
		$field["return_format"] = "value";
        $options = ["100", "200", "300", "400", "500", "600", "700", "800", "900"];
		$field['choices'] = array();
		foreach($options as $label) {
		    $field['choices'][$label] = $label;
		}
	}
	if(in_array("acf-font-size", $class)){
		$field["allow_custom"] = 0;
		$field["default_value"] = "";
		$field["type"] = "select";
		$field["multiple"] = 0;
		$field["allow_null"] = 1;
		$field["ajax"] = 0;
		$field["ui"] = 0;
		$field["search_placeholder"] = "";
		$field["return_format"] = "value";
        $types = ["title", "text"];
		$field['choices'] = array();
		
		$typography  = [];
		$theme_styles = acf_get_theme_styles();
	    if($theme_styles){
	        if(isset($theme_styles["typography"])){
		        $typography = $theme_styles["typography"];	        		
	        }
	    }  

		foreach($types as $type) {
			foreach($GLOBALS["breakpoints"] as $key => $breakpoint) {
				$size = "";
				if(isset($typography[$type][$key]) && !empty($typography[$type][$key]["value"])){
                   $size = " - ".$typography[$type][$key]["value"].$typography[$type][$key]["unit"];
				}
			    $field['choices'][$type."-".$key] = $type."-".$key.$size;
			}
		}
	}

	if(in_array("acf-text-transform", $class)){
		$field["allow_custom"] = 0;
		$field["default_value"] = "none";
		$field["type"] = "select";
		$field["multiple"] = 0;
		$field["allow_null"] = 0;
		$field["ajax"] = 0;
		$field["ui"] = 0;
		$field["search_placeholder"] = "";
		$field["return_format"] = "value";
        $options = ["none", "capitalize", "uppercase", "lowercase", "full-width", "full-size-kana", "inherit", "initial", "revert", "revert-layer", "unset"];
		$field['choices'] = array();
		foreach($options as $label) {
		    $field['choices'][$label] = $label;
		}
	}

	if(in_array("acf-bs-align-hr", $class)){
		$options = array(
        	"start"  => "Left",
        	"center" => "Center", 
        	"end"    => "Right"
        );
		$field['choices'] = array();
		foreach($options as $key => $label) {
		    $field['choices'][$key] = $label;
		}
	}

	if(in_array("acf-align-hr", $class) || in_array("acf-align-hr-responsive", $class)){
		$field["allow_custom"] = 0;
		$field["default_value"] = "start";
		$field["type"] = "select";
		$field["multiple"] = 0;
		$field["allow_null"] = 0;
		$field["ajax"] = 0;
		$field["ui"] = 0;
		$field["search_placeholder"] = "";
		$field["return_format"] = "value";
        $options = array(
        	"start"  => "Left",
        	"center" => "Center", 
        	"end"    => "Right"
        );
        if(in_array("acf-align-hr-responsive", $class)){
        	$options["responsive"] = "Responsive";
        }
		$field['choices'] = array();
		foreach($options as $key => $label) {
		    $field['choices'][$key] = $label;
		}
	}

	if(in_array("acf-bs-align-vr", $class)){
		$options = array(
        	"start"  => "Top",
        	"center" => "Center", 
        	"end"    => "Bottom"
        );
		$field['choices'] = array();
		foreach($options as $key => $label) {
		    $field['choices'][$key] = $label;
		}
	}
	if(in_array("acf-align-vr", $class) || in_array("acf-align-vr-none", $class) || in_array("acf-align-vr-responsive", $class)){
		$field["allow_custom"] = 0;
		$field["default_value"] = in_array("acf-align-vr-none", $class)?"center":"start";
		$field["type"] = "select";
		$field["multiple"] = 0;
		$field["allow_null"] = 0;
		$field["ajax"] = 0;
		$field["ui"] = 0;
		$field["search_placeholder"] = "";
		$field["return_format"] = "value";
        $options = array(
        	"start"  => "Top",
        	"center" => "Center", 
        	"end"    => "Bottom"
        );
        if(in_array("acf-align-vr-responsive", $class)){
        	$options["responsive"] = "Responsive";
        }
        if(in_array("acf-align-vr-none", $class)){
        	$options["none"] = "None";
        }
		$field['choices'] = array();
		foreach($options as $key => $label) {
		    $field['choices'][$key] = $label;
		}
	}

	if(in_array("acf-width-height", $class)){
		$field["allow_custom"] = 1;
		$field["default_value"] = "auto";
		$field["type"] = "select";
		$field["multiple"] = 0;
		$field["allow_null"] = 0;
		$field["ajax"] = 0;
		$field["ui"] = 1;
		$field["search_placeholder"] = "";
		$field["return_format"] = "value";
        $options = ["auto", "100%"];
		$field['choices'] = array();
		foreach($options as $label) {
		    $field['choices'][$label] = $label;
		}
	}

    
    if(in_array("acf-plyr-video-options", $class) || in_array("acf-plyr-audio-options", $class)){
    	$field["allow_custom"] = 0;
		$field["default_value"] = "";
		$field["type"] = "select";
		$field["multiple"] = 1;
		$field["allow_null"] = 0;
		$field["ajax"] = 0;
		$field["ui"] = 1;
		$field["search_placeholder"] = "";
		$field["return_format"] = "value";
		if(in_array("acf-plyr-video-options", $class)){
			$options = [
			    'play-large' => 'The large play button in the center',
			    'restart' => 'Restart playback',
			    'rewind' => 'Rewind by the seek time (default 10 seconds)',
			    'play' => 'Play/pause playback',
			    'fast-forward' => 'Fast forward by the seek time (default 10 seconds)',
			    'progress' => 'The progress bar and scrubber for playback and buffering',
			    'current-time' => 'The current time of playback',
			    'duration' => 'The full duration of the media',
			    'mute' => 'Toggle mute',
			    'volume' => 'Volume control',
			    'captions' => 'Toggle captions',
			    'settings' => 'Settings menu',
			    'pip' => 'Picture-in-picture (currently Safari only)',
			    'airplay' => 'Airplay (currently Safari only)',
			    'download' => 'Show a download button with a link to either the current source or a custom URL you specify in your options',
			    'fullscreen' => 'Toggle fullscreen',
			];			
		}
		if(in_array("acf-plyr-audio-options", $class)){
			$options = [
			    'restart' => 'Restart playback',
			    'rewind' => 'Rewind by the seek time (default 10 seconds)',
			    'play' => 'Play/pause playback',
			    'fast-forward' => 'Fast forward by the seek time (default 10 seconds)',
			    'progress' => 'The progress bar and scrubber for playback and buffering',
			    'current-time' => 'The current time of playback',
			    'duration' => 'The full duration of the media',
			    'mute' => 'Toggle mute',
			    'volume' => 'Volume control',
			    'settings' => 'Settings menu',
			    'airplay' => 'Airplay (currently Safari only)',
			    'download' => 'Show a download button with a link to either the current source or a custom URL you specify in your options',
			];			
		}
	    $field['choices'] = array();
		foreach(array_keys($options) as $label) {
			$field['choices'][$label] = $label;
		}
    }
    if(in_array("acf-plyr-video-settings", $class) || in_array("acf-plyr-audio-settings", $class)){
    	$field["allow_custom"] = 0;
		$field["default_value"] = "";
		$field["type"] = "select";
		$field["multiple"] = 1;
		$field["allow_null"] = 0;
		$field["ajax"] = 0;
		$field["ui"] = 1;
		$field["search_placeholder"] = "";
		$field["return_format"] = "value";
		if(in_array("acf-plyr-video-settings", $class)){
			$options = ['captions', 'quality', 'speed', 'loop'];
		}
    	if(in_array("acf-plyr-audio-settings", $class)){
			$options = ['quality', 'speed', 'loop'];
		}
    	$field['choices'] = array();
		foreach($options as $label) {
			$field['choices'][$label] = $label;
		}
	}
    
    if(in_array("acf-body-classes", $class) || in_array("acf-main-classes", $class)){
    	$field["allow_custom"] = 1;
    	$field["multiple"] = 1;
		$field["allow_null"] = 1;
		$field["ui"] = 1;
		$field["ajax"] = 0;
        $field["type"] = "select";
        $field["return_format"] = "value";
    	$field['choices'] = array();

    	$theme_styles = acf_get_theme_styles();

    	if(in_array("acf-body-classes", $class)){
	        if($theme_styles){
	        	if(isset($theme_styles["header"]["themes"])){
		            $header_themes = $theme_styles["header"]["themes"];
		            if($header_themes){
		            	$field['choices'][] = "##Body Classes";
		                foreach($header_themes as $theme){
		                    $theme_class = $theme["class"];
		                    if(!in_array($theme_class, ["body", "html"])){
		                        $field['choices'][$theme_class] = $theme_class;                      
		                    }
		                }
		            }	        		
	        	}
	        }    		
    	}

        $prefixes = array(
        	"##Margin" => "m", 
        	"##Margin Top" => "mt", 
        	"##Margin Bottom" => "mb",
        	"##Margin Left" => "ms", 
        	"##Margin Right" => "me", 
        	"##Margin Left Right" => "mx", 
        	"##Margin Top Bottom" => "my"
        );
        foreach ($prefixes as $key => $prefix) {
        	$field['choices'][] = $key;
	        foreach (range(0, 10) as $number) {
			    $field['choices'][$prefix."-".$number] = $prefix."-".$number;  
			}
		}

		$prefixes = array(
        	"##Padding" => "p", 
        	"##Padding Top" => "pt", 
        	"##Padding Bottom" => "pb",
        	"##Padding Left" => "ps", 
        	"##Padding Right" => "pe", 
        	"##Padding Left Right" => "px", 
        	"##Padding Top Bottom" => "py"
        );
        foreach ($prefixes as $key => $prefix) {
        	$field['choices'][] = $key;
	        foreach (range(0, 10) as $number) {
			    $field['choices'][$prefix."-".$number] = $prefix."-".$number;  
			}
		}

        $colors = array("primary", "secondary", "tertiary", "quaternary", "success", "info", "warning", "danger", "light", "dark");
        $prefixes = array("##Text Color" => "text", "##Background Colors" => "bg");
        if($theme_styles){
	        if(isset($theme_styles["colors"]["custom"])){
		        $colors_custom = $theme_styles["colors"]["custom"];

		        if($colors_custom){
		       		foreach ($colors_custom as $color_custom) {
		       			$colors[] = $color_custom["title"];
		       		}
		        }
		    }
		}
        foreach ($prefixes as $key => $prefix) {
        	$field['choices'][] = $key;
	        foreach ($colors as $color) {
			    $field['choices'][$prefix."-".$color] = $prefix."-".$color;  
			}
		}
    }

    if(in_array("acf-button-size", $class)){
		$field["allow_custom"] = 0;
		$field["default_value"] = "";
		$field["type"] = "select";
		$field["multiple"] = 0;
		$field["allow_null"] = 0;
		$field["ajax"] = 0;
		$field["ui"] = 0;
		$field["search_placeholder"] = "";
		$field["return_format"] = "value";
		$field['choices'] = array();
		$buttons_sizes  = [];
		$theme_styles = acf_get_theme_styles();
	    if($theme_styles){
	        if(isset($theme_styles["buttons"])){
		        $buttons = $theme_styles["buttons"];
		        if($buttons && isset($buttons["custom"]) && $buttons["custom"]){
		        	$buttons_sizes = array_column($buttons["custom"], 'size');
		        }       		
	        }
	    }
	    if($buttons_sizes){
			foreach($buttons_sizes as $size) {
				$field['choices'][$size] = $size;
			}
	    }
	}

    if(in_array("acf-ratio", $class) || in_array("acf-default-ratio", $class) || in_array("acf-ratio-value", $class)){
    	$field["allow_custom"] = 0;
		$field["default_value"] = "";
		$field["type"] = "select";
		$field["multiple"] = 0;
		$field["allow_null"] = 0;
		$field["ajax"] = 0;
		$field["ui"] = 0;
		$field["search_placeholder"] = "";
		$field["return_format"] = "value";
    	$options = array(
    		"1x1" => "1:1 Square",
    		"3x2" => "3:2 35mm Movie",
    		"3x4" => "3:4 Vertical",
    		"4x3" => "4:3 Standart TV",
    		"5x4" => "5:4 Traditional Photo Size",
    		"185x1" => "1.85x1 Standart Widescreen Movie",
    		"235x1" => "2.35x1 Anamorphic Widescreen Movie",
    		"9x16" => "9:16 Vertical - Stories, Reels etc.",
    		"16x9" => "16:9 Widescreen TV, Monitor",
    		"21x9" => "21:9 Ultra Widescreen TV, Monitor",
    		"32x9" => "32:9 Super Ultra Widescreen TV, Monitor",
    	);
    	$field['choices'] = array();
    	if(in_array("acf-ratio", $class)){
    		$field['choices'][] = "None";
    		$field['choices'][""] = "Default";
    	}
		foreach($options as $key => $label) {
			$field['choices'][$key] = $label;
		}
    }

    if(in_array("acf-language-list", $class)){
    	$field["allow_custom"] = 0;
		$field["default_value"] = "";
		$field["type"] = "select";
		$field["multiple"] = 0;
		$field["allow_null"] = 0;
		$field["ajax"] = 0;
		$field["ui"] = 0;
		$field["search_placeholder"] = "";
		$field["return_format"] = "array";
		$options = get_all_languages(true);
		//print_r($options);
		//die;
		foreach($options as $label) {
			$field['choices'][$label["lang"]] = $label["name"];
		}
    }

    if(in_array("acf-template-custom", $class) || in_array("acf-template-custom-default", $class)){
		$handle = get_stylesheet_directory() . '/theme/templates/_custom/';
		$templates = array();// scandir($handle);
		if ($handle = opendir($handle)) {
		    while (false !== ($entry = readdir($handle))) {
		        // Sadece `.twig` uzantılı dosyaları kontrol et
		        if ($entry != "." && $entry != ".." && pathinfo($entry, PATHINFO_EXTENSION) === 'twig') {
		            $templates[] = $entry;
		        }
		    }
		    closedir($handle);
		}
	    $field['choices'] = array();
	    if(in_array("acf-template-custom-default", $class)){
	    	$field['choices'][ 'default' ] = "Default";
	    }
	    if( is_array($templates) && count($templates) > 0 ) {
	        foreach( $templates as $template ) {
	        	$template = str_replace(".twig", "", $template);
	            $field['choices'][ 'theme/templates/_custom/'.$template ] = $template;
	        }        
	    }else{
	    	$field['choices'][ 'templates/post/tease' ] = "Post Tease";
	    }
	}

	if(in_array("acf-template-modal", $class)){
		$handle = get_stylesheet_directory() . "/templates/partials/modals";
		$templates = array();// scandir($handle);
		if ($handle = opendir($handle)) {
		    while (false !== ($entry = readdir($handle))) {
		        if ($entry != "." && $entry != "..") {
		            $templates[] = $entry;
		        }
		    }
		    closedir($handle);
		}
	    $field['choices'] = array();
	    if( is_array($templates) ) {
	        foreach( $templates as $template ) {
	        	$template = str_replace(".twig", "", $template);
	            $field['choices'][ "templates/partials/modals/".$template ] = $template;
	        }        
	    }
	}

	if(in_array("acf-wp-themes", $class)){
    	$field["allow_custom"] = 0;
		$field["default_value"] = "";
		$field["type"] = "select";
		$field["multiple"] = 0;
		$field["allow_null"] = 0;
		$field["ajax"] = 0;
		$field["ui"] = 0;
		$field["search_placeholder"] = "";
		$field["return_format"] = "value";
		$options = array();
		$themes = wp_get_themes();
		foreach ($themes as $theme) {
	    	$options[$theme->get('TextDomain')] = $theme->get('Name');
	    }
    	$field['choices'] = array();
		foreach($options as $key => $label) {
			$field['choices'][$key] = $label;
		}
    }

    if(in_array("acf-image-blend-mode", $class)){
    	$field["allow_custom"] = 0;
		$field["default_value"] = "";
		$field["type"] = "select";
		$field["multiple"] = 0;
		$field["allow_null"] = 0;
		$field["ajax"] = 0;
		$field["ui"] = 0;
		$field["search_placeholder"] = "";
		$field["return_format"] = "value";
    	$options = array(
    		"" => "No",
    		"multiply" => "Multiply",
    		"screen" => "Screen",
    		"overlay" => "Overlay",
    		"darken" => "Darken",
    		"lighten" => "Lighten",
    		"color-dodge" => "Color Dodge",
    		"color-burn" => "Color Burn",
    		"hard-light" => "Hard Light",
    		"soft-light" => "Soft Light",
    		"difference" => "Difference",
    		"exclusion" => "Exclusion",
    		"hue" => "Hue",
    		"saturation" => "Saturation",
    		"color" => "Color",
    		"luminosity" => "Luminosity",
    	);
    	$field['choices'] = array();
		foreach($options as $key => $label) {
			$field['choices'][$key] = $label;
		}
	}
	if(in_array("acf-image-filter", $class)){
    	$field["allow_custom"] = 0;
		$field["default_value"] = "";
		$field["type"] = "select";
		$field["multiple"] = 0;
		$field["allow_null"] = 0;
		$field["ajax"] = 0;
		$field["ui"] = 0;
		$field["search_placeholder"] = "";
		$field["return_format"] = "value";
    	$options = array(
    		"" => "No",
    		"grayscale" => "Grayscale",
    		"sepia" => "Sepia",
    		"blur" => "Blur",
    		"brightness" => "Brightness",
    		"contrast" => "Contrast",
    		"opacity" => "Opacity"
    	);
    	$field['choices'] = array();
		foreach($options as $key => $label) {
			$field['choices'][$key] = $label;
		}
	}


	if(in_array("acf-menu-locations", $class)){
		$field["allow_custom"] = 0;
		$field["default_value"] = "";
		$field["type"] = "select";
		$field["multiple"] = 0;
		$field["allow_null"] = 0;
		$field["ajax"] = 0;
		$field["ui"] = 0;
		$field["search_placeholder"] = "";
		$field["return_format"] = "value";
		$options = get_menu_locations();
	    $field['choices'] = array();
	    foreach($options as $key => $label) {
			$field['choices'][$key] = $label;
		}
	}

	if(in_array("acf-color-classes", $class) || in_array("acf-color-classes-custom", $class)){
		$field["allow_custom"] = 0;
		$field["default_value"] = "";
		$field["type"] = "select";
		$field["multiple"] = 0;
		$field["allow_null"] = 0;
		$field["ajax"] = 0;
		$field["ui"] = 0;
		$field["search_placeholder"] = "";
		$field["return_format"] = "value";
		$colors_list_file = get_template_directory() . '/theme/static/data/colors.json';
		$colors = file_get_contents($colors_list_file);
		$options = json_decode($colors, true);
	    $field['choices'] = array();
	    foreach($options as $label) {
			$field['choices'][$label] = $label;
		}
		if(in_array("acf-color-classes-custom", $class)){
			$field['choices']["custom"] = "Custom";
		}
	}


	if(in_array("acf-contact-accounts", $class)){
		$field["allow_custom"] = 0;
		$field["default_value"] = "";
		$field["type"] = "select";
		$field["multiple"] = 0;
		$field["allow_null"] = 1;
		$field["ajax"] = 0;
		$field["ui"] = 0;
		$field["search_placeholder"] = "";
		$field["return_format"] = "value";
	    $field['choices'] = array();
	    $args = array(
		    'post_type' => 'contact', // Post tipi 'contaxt' olanları seç
		    'posts_per_page' => -1,
		    'meta_query' => array(
		        array(
		            'key' => 'contact_accounts', // 'contact' grubu içindeki 'accounts' alanını seç
		            'value' => '', // Boş olmayanları kontrol etmek için
		            'compare' => '!=' // 'accounts' metası boş değilse
		        )
		    )
		);
		$options = Timber::get_posts($args);
		if($options){
		    foreach($options as $label) {
				$field['choices'][$label->ID] = $label->post_title;
			}
		}else{
			$field["search_placeholder"] = "Not Found";
		}
	}

	 if(in_array("acf-mt", $class) || in_array("acf-mb", $class) || in_array("acf-ms", $class) || in_array("acf-me", $class) || in_array("acf-pt", $class) || in_array("acf-pb", $class) || in_array("acf-ps", $class) || in_array("acf-ee", $class)){
    	$field["allow_custom"] = 0;
		$field["default_value"] = "";
		$field["type"] = "select";
		$field["multiple"] = 0;
		$field["allow_null"] = 0;
		$field["ajax"] = 0;
		$field["ui"] = 0;
		$field["search_placeholder"] = "";
		$field["return_format"] = "array";
		$prefix = "";
		$class_check = implode(" ", $class);
		if(strpos($class_check, "-mt") !== false){
			$prefix = "mt-";
		}elseif(strpos($class_check, "-mb") !== false){
			$prefix = "mb-";
		}elseif(strpos($class_check, "-ms") !== false){
			$prefix = "ms-";
		}elseif(strpos($class_check, "-me") !== false){
			$prefix = "ms-";
		}elseif(strpos($class_check, "-pt") !== false){
			$prefix = "pt-";
		}elseif(strpos($class_check, "-pb") !== false){
			$prefix = "pb-";
		}elseif(strpos($class_check, "-ps") !== false){
			$prefix = "ps-";
		}elseif(strpos($class_check, "-pe") !== false){
			$prefix = "pe-";
		}
		$options = [];
		foreach (range(0, 10) as $number) {
			$field['choices'][$prefix."-".$number] = $prefix."-".$number;  
		};
    }

    if(in_array("acf-post-types", $class) || in_array("acf-post-types-multiple", $class)){
    	$multiple = in_array("acf-post-types-multiple", $class);
    	$field["allow_custom"] = 0;
		$field["default_value"] = "";
		$field["type"] = "select";
		$field["multiple"] = $multiple;
		$field["allow_null"] = 1;
		$field["ajax"] = 0;
		$field["ui"] = $multiple;
		$field["search_placeholder"] = "";
		$field["return_format"] = "value";
		$options = get_post_types(['public' => true], 'objects');
	    $field['choices'] = array();
	    foreach($options as $label) {
			$field['choices'][$label->name] = $label->label;
		}
    }

    if(in_array("acf-taxonomies", $class) || in_array("acf-taxonomies-multiple", $class)){
    	$multiple = in_array("acf-taxonomies-multiple", $class);
    	$field["allow_custom"] = 0;
		$field["default_value"] = "";
		$field["type"] = "select";
		$field["multiple"] = $multiple;
		$field["allow_null"] = 1;
		$field["ajax"] = 0;
		$field["ui"] = $multiple;
		$field["search_placeholder"] = "";
		$field["return_format"] = "value";
		$options = get_taxonomies(['public' => true]);
	    $field['choices'] = array();
	    foreach($options as $label) {
			$field['choices'][$label] = $label;
		}
    }

    if(in_array("acf-map-service", $class)){
    	$field["allow_custom"] = 0;
		$field["default_value"] = "";
		$field["type"] = "select";
		$field["multiple"] = 0;
		$field["allow_null"] = 0;
		$field["ajax"] = 0;
		$field["ui"] = 0;
		$field["search_placeholder"] = "";
		$field["return_format"] = "value";
		$options = array("leaflet" => "Leaflet (OpenSteetMap)");
		/*$google_api_key = acf_get_setting('google_api_key');
        if ( !empty( $google_api_key ) ) {*/
        	$options["google"] = "Google Maps";
        /*}*/
	    $field['choices'] = array();
	    foreach($options as $key => $label) {
			$field['choices'][$key] = $label;
		}
    }

    if(in_array("acf-location-posts", $class)){
    	$map_view = get_option("options_map_view");
    	$field["allow_custom"] = 0;
		$field["default_value"] = "";
		$field["type"] = "select";
		$field["multiple"] = $map_view == "js"?1:0;
		$field["allow_null"] = 0;
		$field["ajax"] = 0;
		$field["ui"] = $map_view == "js"?1:0;;
		$field["search_placeholder"] = "Find posts";
		$field["instructions"] = $map_view == "embed"?"'Map view' is set to 'embed' on settings page, so you can select only one post":"";
		$field["return_format"] = "value";
        $post_types = [];
        $posts = [];
		$args = array(
	        "post_type" => "acf-field-group",
	        "name"      => "group_63e6945ee6760",
	        "posts_per_page" => 1
	    );
	    $field_group = Timber::get_post($args);
	    if ($field_group && $field_group->post_type === 'acf-field-group') {
	        $settings = maybe_unserialize($field_group->post_content);
	        if (!empty($settings['location'])) {
	            foreach ($settings['location'] as $location) {
	                foreach ($location as $rule) {
	                    if ($rule['param'] === 'post_type') {
	                        $post_types[] = $rule['value'];
	                    }
	                }
	            }
	        }
	    }
	    if (!empty($post_types) && is_array($post_types)) {
		    $args = [
		        'post_type'      => $post_types,
		        'posts_per_page' => -1,
		        'post_status'    => 'publish'
		    ];
		    $result = get_posts($args);
		    if($result){
			    foreach ($result as $post) {
			        $posts[$post->ID] = $post->post_title . " (".$post->post_type.")"; // Burada post ID'si anahtar, başlık değeri
			    }		    	
		    }
		}
	    $field['choices'] = array();
	    if($posts){
		    foreach($posts as $key => $label) {
				$field['choices'][$key] = $label;
			}	    	
	    }
    }

    if($field["type"] == "select"){
    	if(in_array("multiple", $class)){
    	    $field["multiple"] = 1;
        }
        if(in_array("ui", $class)){
    	    $field["ui"] = 1;
        }
    }

	return $field;
}


if(is_admin()){
	add_filter('acf/load_field', 'acf_add_field_options');
}

class UpdateFlexibleFieldLayouts {

    public $post_id;
    public $field_name;
    public $field_key;
    public $field_data;
    public $field_layouts;
    public $block_name;
    public $block_data;
    public $migration;

    private $clone;
    private $breakpoints;


    private $cached_field_data = [];
    private $cached_field_layouts = [];

    public function __construct($post_id = 0, $field_name = "", $field_key = "", $block_name = "", $migration = []) {
    	$this->post_id = $post_id;
        $this->field_name = $field_name;
        $this->field_key = $field_key;
        $this->block_name = $block_name;
        $this->migration = $migration;

        $this->clone = array(
            'aria-label' => '',
            'type' => 'clone',
            'instructions' => '',
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => array(
                'width' => '',
                'class' => '',
                'id' => '',
            ),
            'parent_layout' => '',
            'clone' => array(),
            'display' => 'seamless',
            'layout' => 'block',
            'prefix_label' => 0,
            'prefix_name' => 0,
            'acfe_seamless_style' => 0,
            'acfe_clone_modal' => 0,
            'acfe_clone_modal_close' => 0,
            'acfe_clone_modal_button' => '',
            'acfe_clone_modal_size' => 'large',
        );
        $this->breakpoints = array(
            'aria-label' => '',
            'type' => 'acf_bs_breakpoints',
            'instructions' => '',
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => array(
                'width' => '',
                'class' => '',
                'id' => '',
            ),
            'parent_layout' => '',
            'acf_bs_breakpoints_type' => 'number',
            'show_description' => 1,
            'acf_bs_breakpoints_choices' => '',
            'allow_in_bindings' => 0,
            'font_size' => 14,
        );
    }

    public function get_block_field_data($block) {
        $data = [];
        $id = $block->post_name;
        $file = get_stylesheet_directory() . '/acf-json/'.$id.".json";
        $data = file_get_contents($file);
        if ($data) {
            $data = json_decode($data, true);
            $fields = $data["fields"];
            $layouts = [];
            $data = [];
            foreach($fields as $item) {
                if (isset($item["layouts"])) {
                    $layouts = $item["layouts"];
                    continue;
                }
            }
            if ($layouts) {
                foreach($layouts as $layout) {
                    $fields = [];
                    $sub_fields = $layout["sub_fields"];
                    foreach($sub_fields as $sub_field) {
                        $fields[$sub_field["name"]] = $sub_field["key"];
                    }
                    $data[$layout["name"]] = array(
                        "key" => $layout["key"],
                        "sub_fields" => $fields
                    );
                }
            }
        }
        return $data;
    }

    public function get_block_fields() {
        global $wpdb;
        $block_categories = ["block"];
        $taxonomy = 'acf-field-group-category';
        $taxonomy_terms = implode("', '", array_map('esc_sql', $block_categories));
        $sql = "
        SELECT p.*
            FROM { $wpdb->posts } p
        INNER JOIN { $wpdb->term_relationships } tr ON p.ID = tr.object_id
        INNER JOIN { $wpdb->term_taxonomy } tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
        INNER JOIN { $wpdb->terms } t ON tt.term_id = t.term_id
        WHERE p.post_type = 'acf-field-group'
        AND p.post_status = 'publish'
        AND t.slug IN('$taxonomy_terms')
        AND tt.taxonomy = '$taxonomy'
        ORDER BY p.post_title ASC ";

        return $wpdb->get_results($sql);
    }
    /*public function field_data($forced = false) {
        if (!empty($this->field_data) && !$forced) {
            return $this->field_data;
        } else {
            global $wpdb;
            $post_excerpt_value = 'acf_block_columns'; // Aradığınız post_excerpt değeri
            $post_type = 'acf-field'; // Post tipini belirtin, örneğin 'acf-field'

            $post_data = $wpdb->get_row(
                $wpdb->prepare(
                    "
                    SELECT ID, post_content FROM $wpdb->posts WHERE post_excerpt = % s AND post_type = % s LIMIT 1 ", 
                    $post_excerpt_value,
                    $post_type
                ),
                ARRAY_A // Veriyi bir dizi (array) olarak almak için
            );
            $this->field_data = $post_data;
            return $post_data;
        }
    }*/
    public function field_data($forced = false) {
        if (!empty($this->cached_field_data) && !$forced) {
            return $this->cached_field_data;
        }

        global $wpdb;
        $post_excerpt_value = 'acf_block_columns';
        $post_type = 'acf-field';

        $post_data = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT ID, post_content FROM $wpdb->posts WHERE post_excerpt = %s AND post_type = %s LIMIT 1",
                $post_excerpt_value,
                $post_type
            ),
            ARRAY_A
        );

        $this->cached_field_data = $post_data;
        return $post_data;
    }
    /*public function field_layouts() {
        if (!empty($this->field_layouts)) {
            return $this->field_layouts;
        } else {
            $fields_added = [];
            $post_data = $this->field_data();
            if ($post_data) {
                $post_content = unserialize($post_data['post_content']);
                if (isset($post_content["layouts"])) {
                    $layouts = $post_content["layouts"];
                    foreach($post_content["layouts"] as $item) {
                        if (isset($item["sub_fields"][1])) {
                            $fields_added[] = $item["sub_fields"][1]["name"];
                        }
                    }
                }
            }
            $this->field_layouts = $fields_added;
            return $fields_added;
        }
    }*/
    public function field_layouts() {
        if (!empty($this->cached_field_layouts)) {
            return $this->cached_field_layouts;
        }

        $fields_added = [];
        $post_data = $this->field_data();
        if ($post_data) {
            $post_content = unserialize($post_data['post_content']);
            if (isset($post_content['layouts'])) {
                foreach ($post_content['layouts'] as $item) {
                    if (isset($item['sub_fields'][1])) {
                        $fields_added[] = $item['sub_fields'][1]['name'];
                    }
                }
            }
        }

        $this->cached_field_layouts = $fields_added;
        return $fields_added;
    }

    public function get_block_data() {
        if (!empty($this->block_data)) {
            return $this->block_data;
        } else {
            global $wpdb;
            $post_data = $wpdb->get_row(
                $wpdb->prepare(
                    "
                    SELECT *
                    FROM $wpdb->posts WHERE post_excerpt = % s AND post_type = % s AND post_status = 'publish'
                    LIMIT 1 ", 
                    $this->block_name,
                    'acf-field-group'
                ),
                ARRAY_A // Veriyi bir dizi (array) olarak almak için
            );
            $this->block_data = $post_data;
            return $post_data;
        }
    }
    public function block_exists_in_layouts() {
        $layouts = $this->field_layouts();
        $block_name_solid = str_replace("block-", "", $this->block_name);
        //error_log(json_encode($layouts));
        //error_log($block_name_solid." var mı? => ".in_array($block_name_solid, $layouts));
        return in_array($block_name_solid, $layouts);
    }
    public function block_exists_in_db() {
        global $wpdb;
        $post_parent = $this->field_data()["ID"];
        $block_name_solid = str_replace("block-", "", $this->block_name);
        $block = $wpdb->get_var(
            $wpdb->prepare(
                "
                SELECT post_excerpt FROM { $wpdb->posts } WHERE post_type = % s AND post_parent = % d AND post_excerpt = % s ", 
                'acf-field',
                $post_parent,
                $block_name_solid
            )
        );
        return !empty($block) ? true : false;
    }
    public function create_clone($block, $post_parent, $layout_name, $layout_data = []) {
        if ($layout_data && isset($layout_data["sub_fields"]) && in_array($slug, array_values($layout_data["sub_fields"]))) {
            $post_name = $layout_data["sub_fields"][$slug];
        } else {
            $post_name = 'field_'.uniqid();
        }

        $clone = $this->clone;
        $clone["parent_layout"] = $layout_name;
        $clone["parent_id"] = $post_parent;
        $clone["clone"] = array(
            $block["post_name"]
        );
        $post_data = array(
            //'post_title'    => $block["post_title"],
            'post_content' => serialize($clone), // post_content diziyi JSON olarak kaydediyoruz
            'post_status' => 'publish', // Yayınlanmış olarak ayarla
            'post_type' => 'acf-field', // Post tipini acf-field olarak belirle
            'post_name' => $post_name, // Post slug (post_name)
            'post_parent' => $post_parent, // Parent ID, 8293 olarak belirlenmiş
            'post_excerpt' => str_replace("block-", "", $block["post_excerpt"]), // Parent ID, 8293 olarak belirlenmiş
        );
        $post_id = wp_insert_post($post_data);
        return $clone;
    }
    public function create_field($block, $post_parent, $layout_name, $args = array(), $title = "", $slug = "", $layout_data = []) {
        if ($layout_data && isset($layout_data["sub_fields"]) && in_array($slug, array_values($layout_data["sub_fields"]))) {
            $post_name = $layout_data["sub_fields"][$slug];
        } else {
            $post_name = 'field_'.uniqid();
        }
        $args["parent_layout"] = $layout_name;
        $post_data = array(
            'post_title' => $title, //"Breakpoints",
            'post_content' => serialize($args), // post_content diziyi JSON olarak kaydediyoruz
            'post_status' => 'publish', // Yayınlanmış olarak ayarla
            'post_type' => 'acf-field', // Post tipini acf-field olarak belirle
            'post_name' => $post_name, // Post slug (post_name)
            'post_parent' => $post_parent, // Parent ID, 8293 olarak belirlenmiş
            'post_excerpt' => $slug, //"breakpoints",
        );
        $post_id = wp_insert_post($post_data);
        return $args;
    }
    /*public function update() {

        if (!$this->block_exists_in_db()) {

            error_log("+++ Ekleniyor");

            $post_data = $this->field_data();
            if ($post_data) {
                $post_parent = $post_data['ID'];
                $post_content = unserialize($post_data['post_content']);
                $layouts = $post_content["layouts"];

                $block = $this->get_block_data();

                $layout_data = [];
                if ($this->migration && in_array($this->block_name, array_values($this->migration))) {
                    $layout_data = $this->migration[$this->block_name];
                    $layout_name = $layout_data["key"];
                } else {
                    $layout_name = "layout_".uniqid();
                }

                $breakpoints = $this->create_field($block, $post_parent, $layout_name, $this->breakpoints, "Breakpoints", "breakpoints", $layout_data);

                $clone = $this->create_clone($block, $post_parent, $layout_name, $layout_data);

                if ($clone && $breakpoints) { // && $parallax) {

                    $clone["parent_id"] = $post_parent;

                    $subfields = [];

                    $layouts[$layout_name] = array(
                        "key" => $layout_name,
                        "name" => $block["post_excerpt"],
                        "label" => $block["post_title"],
                        "display" => "block",
                        "sub_fields" => $subfields,
                        "min" => "",
                        "max" => "",
                        "acfe_flexible_modal_edit_size" => false,
                        "acfe_flexible_settings" => false,
                        "acfe_flexible_settings_size" => "medium",
                        "acfe_flexible_render_template" => false,
                        "acfe_flexible_render_style" => false,
                        "acfe_flexible_render_script" => false,
                        "acfe_flexible_thumbnail" => false,
                        "acfe_flexible_category" => false,
                    );

                    //update
                    $post_content["layouts"] = $layouts;
                    $post_content = serialize($post_content);
                    global $wpdb;
                    $updated = $wpdb->update(
                        $wpdb->posts,
                        array(
                            'post_content' => $post_content, // Güncellenen değer
                        ),
                        array('ID' => $post_parent) // Güncellenecek postun ID'si
                    );
                }
            }
        } else {
            error_log("--- Eklenmiyor");
        }
    }*/
    public function update() {
        if (!$this->block_exists_in_db()) {
            error_log("+++ Ekleniyor");

            $post_data = $this->field_data();
            if ($post_data) {
                $post_parent = $post_data['ID'];
                $post_content = unserialize($post_data['post_content']);
                $layouts = $post_content['layouts'];

                $block = $this->get_block_data();

                $layout_data = [];
                if ($this->migration && in_array($this->block_name, array_values($this->migration))) {
                    $layout_data = $this->migration[$this->block_name];
                    $layout_name = $layout_data['key'];
                } else {
                    $layout_name = "layout_" . uniqid();
                }

                $breakpoints = $this->create_field($block, $post_parent, $layout_name, $this->breakpoints, "Breakpoints", "breakpoints", $layout_data);
                $clone = $this->create_clone($block, $post_parent, $layout_name, $layout_data);

                if ($clone && $breakpoints) {
                    $clone['parent_id'] = $post_parent;

                    $layouts[$layout_name] = array(
                        'key' => $layout_name,
                        'name' => $block['post_excerpt'],
                        'label' => $block['post_title'],
                        'display' => 'block',
                        'sub_fields' => [],
                        'min' => '',
                        'max' => '',
                    );

                    // Post içeriği güncelleniyor
                    $post_content['layouts'] = $layouts;
                    $post_content = serialize($post_content);

                    global $wpdb;
                    $wpdb->update(
                        $wpdb->posts,
                        ['post_content' => $post_content],
                        ['ID' => $post_parent]
                    );
                }
            }
        } else {
            error_log("--- Eklenmiyor");
        }
    }

    public function update_layouts($post_parent) {
        global $wpdb;
        $posts = $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT ID, post_title FROM { $wpdb->posts } WHERE post_type = % s AND post_parent = % d AND(post_excerpt IS NULL OR post_excerpt = '')
                ", 
                'acf-field',
                $post_parent
            )
        );

        if ($posts) {
            foreach($posts as $post) {
                $new_excerpt = sanitize_title(str_replace('block', '', strtolower($post->post_title)));
                $wpdb->update(
                    $wpdb->posts,
                    array('post_excerpt' => $new_excerpt), // post_excerpt alanını güncelle
                    array('ID' => $post->ID) // ID'ye göre güncelle
                );
            }
        }
    }

    public function update_cache() {
	    if ($this->$post_id) {
	        acf_save_post_block_columns_action($this->$post_id);

	        // ACF Cache'i temizle
	        acf_flush_field_cache();

	        // Alan grubunu yeniden yükle
	        if($this->$field_key){
	        	acf_import_field_group(acf_get_field_group($this->$field_key));	        	
	        }

	        // Alan grubunu manuel kaydet
	        do_action('acf/save_post', $this->$post_id);
	    }
	}
}
function acf_save_post_block_columns_action( $post_id ){
	if(has_term("block", 'acf-field-group-category', $post_id)){ // is block
    	$block = get_post($post_id);

    	remove_action( 'save_post', 'acf_save_post_block_columns', 20 );

    	if($block->post_excerpt != "block-bootstrap-columns"){

	    	$layouts = new UpdateFlexibleFieldLayouts($post_id, "acf_block_columns", $block->post_name, $block->post_excerpt);
	    	$layouts->update();

    	}elseif($block->post_excerpt == "block-bootstrap-columns"){

    		$layouts_check = new UpdateFlexibleFieldLayouts();
    		$blocks = $layouts_check->get_block_fields();
    		if($blocks){
    			$group_field_data = $layouts_check->get_block_field_data($block);
    			error_log("block-bootstrap-columns s a v i n g . . . . . . . . . . . . ");
    			foreach($blocks as $item){
    				error_log("adding:".$item->post_excerpt);
    				$layouts = new UpdateFlexibleFieldLayouts($post_id, "acf_block_columns", $item->post_name, $block->post_excerpt, $group_field_data);
    				$layouts->update();
    			}
    		}

    	}

    	add_action( 'save_post', 'acf_save_post_block_columns', 20 );

    }
}

function acf_save_post_block_columns( $post_id ) {
	if (defined('DOING_AJAX') && DOING_AJAX) {
		return;
	}
	if (defined('DOING_CRON') && DOING_CRON) {
		return;
	}
	if ( wp_is_post_revision( $post_id ) ) {
        return;
    }
    if ( get_post_status( $post_id ) !== 'publish' ) {
        return;
    }
    if ( get_post_type( $post_id ) !== 'acf-field-group' ) {
        return;
    }
    static $has_run = false; // Hook'un iki kere çalışmasını önlemek için flag kullan
    if ($has_run) {
        return;
    }
    $has_run = true;

    acf_save_post_block_columns_action( $post_id );
}
add_action( 'save_post', 'acf_save_post_block_columns', 20, 1);



function unit_value($val=array()){
	$value = "";
	if(isset($val["value"])){
		$value = $val["value"].$val["unit"];
	}
	return $value;
}


function acf_layout_posts_preload($fields = array()){
	if($fields){

		//print_r($fields);

		$vars = $fields;/*array(
            "post_type" => $fields["post_type"],
            "taxonomy" => $fields["taxonomy"],
            "parent" => $fields["terms"],
            "numberposts" => $fields["posts_per_page"],
            "orderby" => $fields["orderby"],
            "order" => $fields["order"],
		);*/
		if($fields["load_type"] == "all"){
		   //$vars["numberposts"] = -1;
		}

		//echo "aa".$vars["posts_per_page_default"];

		$class = "";
		$is_home = boolval(is_front_page());

		$context = Timber::context();
        $template = "partials/posts/archive-acf.twig";
        $templates = array();
        switch($fields["type"]){
        	case "post":
	        	if($is_home){
	        		$templates[] = $vars["post_type"]."/tease-home.twig";
	        	}	
        		$templates[] = $vars["post_type"]."/tease.twig";
        	break;
        	case "taxonomy":
        	    if($is_home){
	        		$templates[] = $vars["taxonomy"]."/tease-home.twig";
	        	}
	            $taxonomy = get_taxonomy($vars["taxonomy"]);
	            $post_types = $taxonomy->object_type;
	            foreach($post_types as $post_type){
	            	if($is_home){
		        		$templates[] = $post_type."/tease-home.twig";
		        	}
	                $templates[] = $post_type."/tease.twig";
	            }
	            $templates[] = $vars["taxonomy"]."/tease.twig";
        	break;
        	case "user":
        	    if($is_home){
	        		$templates[] = $fields["type"]."/tease-home.twig";
	        	}
                $templates[] = $fields["type"]."/tease.twig";
            break;
            case "comment":
                if($is_home){
	        		$templates[] = $fields["type"]."/tease-home.twig";
	        	}
                $templates[] = $fields["type"]."/tease.twig";
            break;
        }
        /*if(isset($vars["post_type"]) && !empty($vars["post_type"])){
            $templates[] = $vars["post_type"]."/tease.twig";
        }
        if(empty($templates) && isset($vars["taxonomy"]) && !empty($vars["taxonomy"])){
            $templates[] = $vars["taxonomy"]."/tease.twig";
            $taxonomy = get_taxonomy($vars["taxonomy"]);
            $post_types = $taxonomy->object_type;
            foreach($post_types as $post_type){
                $templates[] = $post_type."/tease.twig";
            }
        }*/
        $templates[] = "tease.twig";

		$paginate = new Paginate([], $vars);
        $result = $paginate->get_results($fields["type"]);
        //print_r($result);
        $posts = $result["posts"];
        //print_r($posts);
        if(is_wp_error($posts)){
           $posts = array();
        }
        $context["posts"] = $posts;
        $context["templates"] = $templates;
        //$response["data"] = $result["data"];
        //$response["html"] = Timber::compile($templates, $context);

		//$posts = Timber::get_posts($vars);
		
		//$context["posts"] = $posts;
		if(isset($fields["is_preview"])){
			$context["is_preview"] = $fields["is_preview"];			
		}

		return array(
			"posts" => Timber::compile($template, $context),//Timber::compile($fields["post_type"]."/archive-acf.twig", $context),
			"total" => $result["data"]["total"]//count($posts)//count($posts)
		);
	}
}





if( ENABLE_MULTILANGUAGE == "qtranslate-xt"){
    // ACF options sayfasındaki alanları kaydetmek için filtre
    add_filter('acf/load_value', 'load_acf_option_value', 10, 3);
    function load_acf_option_value($value, $post_id, $field) {
    	remove_filter('acf/load_value', 'load_acf_option_value', 10, 3);

        $current_lang = qtranxf_getLanguage();
        $default_lang = qtranxf_getSortedLanguages()[0];

        if ($post_id == 'options_'.$current_lang) {

            $option_name = $field['name'];
            $default_option = "options_{$option_name}";
            $default_alt_option = "options_{$default_lang}_{$option_name}";
            $current_option = "options_{$current_lang}_{$option_name}";
            $value = get_option($current_option);

            if (empty($value)) {
                
               global $q_config;
	           $q_config['language'] = $default_lang;
	           //echo $option_name." > yok aabi<br>";
	           $value = get_field($option_name, "options");
	           //print_r($value);
	           $value = get_option($default_option);
	           //print_r($value);
	           //echo "<br>";
	           $q_config['language'] = $current_lang;
                /*if (empty($value)) {
                    $value = get_option($default_alt_option);
                }*/
            }
        }
        add_filter('acf/load_value', 'load_acf_option_value', 10, 3);
        return $value;
    }
}/**/





function display_search_ranks_table() {
    global $wpdb;

    if ( isset($_GET['delete_id']) ) {
        $delete_id = intval($_GET['delete_id']);
        $wpdb->delete('wp_search_terms', array('id' => $delete_id));
        echo '<meta http-equiv="refresh" content="0; url=' . admin_url('admin.php?page=search-ranks') . '">';
    }

    $results = $wpdb->get_results("SELECT * FROM wp_search_terms ORDER BY rank DESC");

    if ($results) {
        echo '<div class="bg-white rounded-3 p-3 shadow-sm"><table class="table table-hover table-striped" style="width:100%; border-collapse: collapse;background-color:#fff;">';
        echo '<thead><tr style="background-color:#f2f2f2; text-align:left;">';
        echo '<th style="padding:10px; border-bottom: 1px solid #ddd;">ID</th>';
        echo '<th style="padding:10px; border-bottom: 1px solid #ddd;">Name</th>';
        echo '<th style="padding:10px; border-bottom: 1px solid #ddd;">Type</th>';
        echo '<th style="padding:10px; border-bottom: 1px solid #ddd;">Rank</th>';
        echo '<th style="padding:10px; border-bottom: 1px solid #ddd;">Date</th>';
        echo '<th style="padding:10px; border-bottom: 1px solid #ddd;">Last Modified</th>';
        echo '<th style="padding:10px; border-bottom: 1px solid #ddd;">Actions</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        foreach ($results as $row) {
            echo '<tr>';
            echo '<td style="padding:10px; border-bottom: 1px solid #ddd;">' . esc_html($row->id) . '</td>';
            echo '<td style="padding:10px; border-bottom: 1px solid #ddd;">' . esc_html(urldecode($row->name)) . '</td>';
            echo '<td style="padding:10px; border-bottom: 1px solid #ddd;">' . esc_html($row->type) . '</td>';
            echo '<td style="padding:10px; border-bottom: 1px solid #ddd;">' . esc_html($row->rank) . '</td>';
            echo '<td style="padding:10px; border-bottom: 1px solid #ddd;">' . esc_html($row->date) . '</td>';
            echo '<td style="padding:10px; border-bottom: 1px solid #ddd;">' . esc_html($row->date_modified) . '</td>';
            // Silme butonu
            echo '<td style="padding:10px; border-bottom: 1px solid #ddd;">';
            echo '<a href="' . admin_url('admin.php?page=search-ranks&delete_id=' . esc_attr($row->id)) . '" onclick="return confirm(\'Bu kaydı silmek istediğine emin misin?\');" style="color:red; text-decoration:none;">Sil</a>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    } else {
        echo '<p>No data found.</p>';
    }
}
function update_search_ranks_message_field( $field ) {
    ob_start();
    display_search_ranks_table();
    $field['message'] = ob_get_clean();
    return $field;
}
add_filter('acf/prepare_field/key=field_66e9f03698857', 'update_search_ranks_message_field');












function display_page_assets_table() {
    $extractor = new PageAssetsExtractor();
    $urls = $extractor->get_all_urls();
    if($urls){
        $total = count($urls);
        $outputArray = [];
		foreach ($urls as $key => $item) {
		    $item['id'] = $key; // Key'i 'id' olarak ekle
		    $outputArray[] = $item; // Yeni array'e ekle
		}
		$urls = $outputArray;
        $message = "JS & CSS Extraction process completed with <strong>".$total." pages.</strong>";
        $type = "success";
    }else{
    	$urls = [];
        $message = "Not found any pages to extract process.";
        $type = "error";
    }

    if ($urls) {
        echo '<div class="bg-white rounded-3 p-3 shadow-sm"><table class="table-page-assets table table-sm table-hover table-striped" style="width:100%; border-collapse: collapse;background-color:#fff;">';
        echo '<thead><tr style="background-color:#f2f2f2; text-align:left;">';
        echo '<th style="padding:10px; border-bottom: 1px solid #ddd;">ID</th>';
        echo '<th style="padding:10px; border-bottom: 1px solid #ddd;">Type</th>';
        echo '<th style="padding:10px; border-bottom: 1px solid #ddd;">Url</th>';
        echo '<th style="padding:10px; border-bottom: 1px solid #ddd;">Actions</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        foreach ($urls as $index => $row) {
            echo '<tr id="'.$row["type"].'_'.$row["id"].'" data-index="'.$index.'">';
            echo '<td data-id="'.$row["id"].'" style="padding:10px; border-bottom: 1px solid #ddd;">' . esc_html($row["id"]) . '</td>';
            echo '<td data-type="'.$row["type"].'" style="padding:10px; border-bottom: 1px solid #ddd;">' . esc_html($row["post_type"]) . '</td>';
            echo '<td data-url="'.$row["url"].'" style="padding:10px; border-bottom: 1px solid #ddd;">' . esc_html($row["url"]) . '</td>';
            echo '<td class="actions" style="width:50px;padding:10px; border-bottom: 1px solid #ddd;"><a href="#" class="btn-page-assets-single btn btn-success  btn-sm">Fetch</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '<div class="table-page-assets-status text-center py-4">';
        echo '<div class="progress-page-assets progress d-none mb-4" role="progressbar" aria-label="Animated striped example" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%"></div></div>';
        echo '<a href="#" class="btn-page-assets-update btn btn-success btn-lg px-4">Start Mass Update</a>';
        echo '</div>';
    } else {
        echo '<p>No data found.</p>';
    }
    ?>
    <script type="text/javascript">
    	var index = 0;
    	var urls = <?php echo json_encode($urls);?>;
    	jQuery(document).ready(function($) {
	    	$(".btn-page-assets-single").on("click", function(e){
	    		e.preventDefault();
	    		$(this).addClass("disabled");
	    		var $row = $(this).closest("tr");
	    		var $index = $row.attr("data-index");
	    		page_assets_update($index, true);
	        });
	        $(".btn-page-assets-update").on("click", function(e){
	        	e.preventDefault();
	        	$(this).addClass("disabled");
	        	page_assets_update(0, false);
	        });
	    });
        function page_assets_update($index, $single){
        	var $row = $(".table-page-assets").find("tr[data-index='"+$index+"']");
        	$row.find(".actions").empty().addClass("loading loading-xs position-relative");
        	if(!$single){
        		$(".progress-page-assets").removeClass("d-none");
        	}
    		 data = {
	            action: 'page_assets_update',
	            url: urls[$index]
	        };
	        $.ajax({
				url: ajaxurl,
				type: 'post',
				dataType: 'json',
				data: data,
				success: function(response) {
					if (!response.error) {
						$row.find("td").addClass("bg-success text-white");
						$row.find(".actions").removeClass("loading loading-xs").html("<strong>COMPLETED</strong>");
						if(!$single){
							var percent = (($index+1) * 100) / urls.length;
							$(".progress-page-assets .progress-bar").css("width", percent+"%");
						}else{
							$row.find(".btn-page-assets-single").removeClass("disabled");
						}
						if($index < urls.length-1 && !$single){
							$index++;
							page_assets_update($index);
						}else{
							$(".progress-page-assets").remove();
							$(".table-page-assets-status").prepend("<div class='text-success fs-4 fw-bold'>COMPLETED!</div>");
							$(".btn-page-assets-update").removeClass("disabled");
						}
					}
				},
				error: function(xhr, status, error) {
					console.error('AJAX Error: ' + status + ' - ' + error);
				}
	        });
    	}
    </script>
    <?php
}
function update_page_assets_message_field($field){
    ob_start();
    display_page_assets_table();
    echo ob_get_clean();
    return $field;
} 
add_action('acf/render_field/name=page_assets', 'update_page_assets_message_field');
function page_assets_update(){
	$response = array(
        "error" => false,
        "message" => "",
        "html" => "",
        "data" => ""
    );
    $url = $_POST["url"];
    $id = $url["id"];
    $type = $url["type"];
    $url = $url["url"];
    $extractor = new PageAssetsExtractor();
    $extractor->mass = true;
    $extractor->type = $type;
    $response["data"] = $extractor->fetch($url, $id);
    echo json_encode($response);
    wp_die();
}
add_action('wp_ajax_page_assets_update', 'page_assets_update');
add_action('wp_ajax_nopriv_page_assets_update', 'page_assets_update');






function acf_compile_js_css($value=0){
	       if ( function_exists( 'rocket_clean_minify' ) ) {
                rocket_clean_minify();
            }

            $is_development = is_admin() && ($_SERVER["SERVER_ADDR"] == "127.0.0.1" || $_SERVER["SERVER_ADDR"] == "localhost" || $_SERVER["SERVER_ADDR"] == "::1");
    
            
            // compile js files and css files
            if (!function_exists("compile_files_config")) {
                require SH_INCLUDES_PATH . "minify-rules.php";
            }
            require SH_CLASSES_PATH . "class.minify.php";

            if (class_exists('ScssPhp\ScssPhp\Compiler')) {
                $compile_errors = SaltHareket\Theme::scss_compile();
                if($compile_errors){
                    $type = "error";
                    $message = "<strong style='display:block;color:red;'>Compiling Error</strong>";
                    $message .= $compile_errors[0]["message"];
                    file_put_contents( WP_CONTENT_DIR . '/compiler_error.log', $compile_errors[0]["message"], FILE_APPEND);
                }else{
                    $type = "success";
                    $message = "scss files compiled!...";
                    $message .= "<br>js files compiled!...";
                }                
            }else{
                $type = "error";
                $message = "WP-SCSS is not intalled! SCSS is not compiled.";
            }  

            if(function_exists("add_admin_notice") && $value){
                add_admin_notice($message, $type);
            }
            
            // version update or plugin's custom init file update
            $minifier = new SaltMinifier(false, $is_development);
            $updated_plugins = $minifier->init();//compile_files(false, $is_development);
            error_log("updates_plugins: ".json_encode($updated_plugins));

            if($updated_plugins){
                if(function_exists("add_admin_notice") && $value){
                    $message = "Updated plugins or plugin init files: ".implode(",", $updated_plugins);
                    $type = "warning";
                    add_admin_notice($message, $type);
                }
            }

            if($is_development){
                // remove unused css styles
                error_log( "w e b p a c k");
                /*$output = [];
                $returnVar = 0;
                $command = "npx webpack --env enable_ecommerce=false";//.(ENABLE_ECOMMERCE ? 'true' : 'false');
                chdir(get_stylesheet_directory());
                exec($command, $output, $returnVar);//exec('npx webpack', $output, $returnVar);
                error_log( json_encode(implode("\n", $output)));
                if ($returnVar === 0) {
                    //echo 'Webpack successfully executed.';
                } else {
                    $message = 'Webpack execution failed. Error code: ' . $returnVar;
                    if(function_exists("add_admin_notice")){
                        add_admin_notice($message, "error");
                    }
                }

                $workingDir = get_stylesheet_directory();
                $process = Process::fromShellCommandline('npx webpack --env enable_ecommerce=false', $workingDir);

                try {
                    $process->mustRun();
                    error_log($process->getOutput()); 
                } catch (ProcessFailedException $e) {
                    $message = 'Webpack execution failed. Error code: ' .  $e->getMessage();
                    error_log($message);
                    if(function_exists("add_admin_notice")){
                        add_admin_notice($message, "error");
                    }
                }*/

                /**/
                $workingDir = get_stylesheet_directory();
                $command = ['npx', 'webpack', '--env', 'enable_ecommerce=false'];
                $process = new Process($command, $workingDir);

                $currentUser = getenv('USERNAME') ?: getenv('USER'); // Windows için USERNAME, diğer sistemlerde USER
                $nodeJsPath = 'C:\Program Files\nodejs';
                $npmPath = 'C:\Users\\' . $currentUser . '\AppData\Roaming\npm';
                $process->setEnv([
                    'PATH' => getenv('PATH') . ';' . $nodeJsPath . ';' . $npmPath,
                ]);
                $process->setTimeout(null);
                try {
                    $process->mustRun(); // Komutu çalıştır ve başarısız olursa hata fırlat
                    error_log($process->getOutput()); // Çıktıyı kaydet
                    //return true;
                } catch (ProcessFailedException $exception) {
                    error_log('Webpack execution failed: ' . $exception->getMessage());
                    if (function_exists("add_admin_notice")) {
                        add_admin_notice('Webpack execution failed.', 'error');
                    }
                    //return false;
                }

                // .js dosyalarını filtrele ve sil
                $js_files = glob(get_stylesheet_directory() . "/static/css/" . "*.js");
                foreach ($js_files as $js_file) {
                    try {
                        unlink($js_file);
                        //echo "Dosya silindi: $js_file <br>";
                    } catch (Exception $e) {
                        //echo "Dosya silinirken bir hata oluştu: " . $e->getMessage() . "<br>";
                    }
                }

                // TXT dosyalarını sil
                $txt_files = glob(get_stylesheet_directory() . "/static/css/" . "*.txt");
                foreach ($txt_files as $txt_file) {
                    try {
                        unlink($txt_file);
                        //echo "TXT dosyası silindi: $txt_file <br>";
                    } catch (Exception $e) {
                        //echo "TXT dosyası silinirken bir hata oluştu: " . $e->getMessage() . "<br>";
                    }
                }
            }

            if($updated_plugins){
                $pages = get_pages_need_updates($updated_plugins);
                if(function_exists("add_admin_notice") && $pages && $value){
                    $message = count($pages)." pages fetched for plugin updates";
                    $type = "success";
                    add_admin_notice($message, $type);
                }
            }
            if(!$value){
            	//return true;
            }
}
function acf_development_compile_js_css( $value, $post_id, $field, $original ) {
    $is_development = is_admin() && ($_SERVER["SERVER_ADDR"] == "127.0.0.1" || $_SERVER["SERVER_ADDR"] == "localhost" || $_SERVER["SERVER_ADDR"] == "::1");
    if( $value ) {
    	acf_compile_js_css($value);
    }
    return 0;
}
add_filter('acf/update_value/name=enable_compile_js_css', 'acf_development_compile_js_css', 10, 4);


function acf_methods_settings($value=0){
	error_log("acf_methods_settings");
	        if ( function_exists( 'rocket_clean_minify' ) ) {
                rocket_clean_minify();
            }
            if(!class_exists("SaltHareket\MethodClass")){
	            require_once SH_CLASSES_PATH . "class.methods.php";
	        }
	        $methods = new SaltHareket\MethodClass();
            $frontend = $methods->createFiles(false); 
            error_log(json_encode($frontend));
            $admin = $methods->createFiles(false, "admin");
            error_log(json_encode($admin));
            if(function_exists("add_admin_notice") && $value){
                if($frontend || $admin){
                    if($frontend){
                        foreach($frontend as $error){
                           add_admin_notice($error["message"], "error");
                        }
                    }
                    if($admin){
                        foreach($admin as $error){
                           add_admin_notice($error["message"], "error");
                        }
                    }
                    $message = "Only JS Frontend/Backend methods compiled!";
                    $type = "success";
                    add_admin_notice($message, $type);
                }else{
                  $message = "PHP & JS Frontend/Backend methods compiled!";
                  $type = "success";
                  add_admin_notice($message, $type);
                }
            }
            if(!$value){
            	//return true;
            }
}
function acf_development_methods_settings( $value=0, $post_id=0, $field="", $original="" ) {
    if( $value ) {
    	$is_development = is_admin() && ($_SERVER["SERVER_ADDR"] == "127.0.0.1" || $_SERVER["SERVER_ADDR"] == "localhost" || $_SERVER["SERVER_ADDR"] == "::1");
        if ($is_development) {
           acf_methods_settings($value); 
        }
    }
    return 0;
}
add_filter('acf/update_value/name=enable_compile_methods', 'acf_development_methods_settings', 10, 4);



function acf_development_extract_translations( $value=0, $post_id=0, $field="", $original="" ) {
    if( $value ) {
        if (is_admin() && ($_SERVER["SERVER_ADDR"] == "127.0.0.1" || $_SERVER["SERVER_ADDR"] == "localhost" || $_SERVER["SERVER_ADDR"] == "::1")) {
            if ( function_exists( 'rocket_clean_minify' ) ) {
                rocket_clean_minify();
            }

            // Get the text domain of the active theme
            $theme = wp_get_theme();
            $textDomain = $theme->get('TextDomain');

            // Get the path to the current theme's folder
            $themeFolderPath = get_template_directory();

            // Define the name and path of the output file
            $outputDir = $themeFolderPath . '/theme/static/data';
            $outputFile = $outputDir . '/translates.php';


            // Create the output directory if it doesn't exist
            if (!file_exists($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            // Define folders to exclude
            $excludeFolders = ['assets', 'node_modules', 'vendor', 'static', 'languages', 'acf-json'];
            if(!ENABLE_ECOMMERCE){
                $excludeFolders[] = "woo";
                $excludeFolders[] = "woocommerce";
            }
            if(!ENABLE_MEMBERSHIP){
                $excludeFolders[] = "user";
                $excludeFolders[] = "my-account'";
            }

            $excludeFilePaths = [];
            if(!ENABLE_MEMBERSHIP){
                $excludeFilePaths[] = 'template-my-account.php';
                $excludeFilePaths[] = 'templates/partials/base/menu-login.twig';
                $excludeFilePaths[] = 'templates/partials/base/menu-user-menu.twig';
                $excludeFilePaths[] = 'templates/partials/base/offcanvas-user-menu.twig';
                $excludeFilePaths[] = 'templates/partials/base/user-completion.twig';
                $excludeFilePaths[] = 'templates/author.twig';
                $excludeFilePaths[] = 'templates/partials/modals/login.twig';
                $excludeFilePaths[] = 'templates/partials/modals/fields-localization.twig';
                $excludeFilePaths[] = 'templates/partials/modals/list-languages.twig';
                $excludeFilePaths[] = SH_INCLUDES_PATH . 'helpers/membership-functions.php';
            }
            if(!ENABLE_ECOMMERCE){
                $excludeFilePaths[] = 'template-shop.php';
                $excludeFilePaths[] = 'template-checkout.php';
                $excludeFilePaths[] = 'templates/partials/base/menu-cart.twig';
                $excludeFilePaths[] = 'templates/partials/base/offcanvas-cart.twig';
                $excludeFilePaths[] = 'templates/partials/dropdown/cart.twig';
                $excludeFilePaths[] = 'templates/partials/dropdown/cart-empty.twig';
                $excludeFilePaths[] = 'templates/partials/dropdown/cart-footer.twig';
            }
            if(!ENABLE_CHAT){
                $excludeFilePaths[] = 'templates/partials/base/offcanvas-messages.twig';
                $excludeFilePaths[] = 'templates/partials/dropdown/messages.twig';
                $excludeFilePaths[] = 'templates/partials/dropdown/messages-empty.twig';
                $excludeFilePaths[] = 'templates/partials/dropdown/messages-footer.twig';
            }
            if(!ENABLE_FAVORITES){
                $excludeFilePaths[] = 'templates/partials/base/menu-favorites.twig';
                $excludeFilePaths[] = 'templates/partials/base/offcanvas-favorites.twig';
                $excludeFilePaths[] = 'templates/partials/dropdown/favorites.twig';
                $excludeFilePaths[] = 'templates/partials/dropdown/favorites-empty.twig';
                $excludeFilePaths[] = 'templates/partials/dropdown/favorites-footer.twig';
            }
            if(!ENABLE_NOTIFICATIONS){
                $excludeFilePaths[] = 'templates/partials/base/menu-notifications.twig';
                $excludeFilePaths[] = 'templates/partials/base/offcanvas-notifications.twig';
            }
            if (!class_exists("Newsletter")) {
                $excludeFilePaths[] = 'template-newsletter.php';
                $excludeFilePaths[] = 'templates/page-newsletter.twig';
            }

            function scanFolder($folderPath, $excludeFolders, $excludeFilePaths) {
                $files = [];
                $dir = new RecursiveDirectoryIterator($folderPath, RecursiveDirectoryIterator::SKIP_DOTS);
                $iterator = new RecursiveIteratorIterator($dir);
                $regex = new RegexIterator($iterator, '/^.+\.(php|twig)$/i', RecursiveRegexIterator::GET_MATCH);
                foreach ($regex as $file) {
                    $path = str_replace('\\', '/', $file[0]);
                    $exclude = false;
                    foreach ($excludeFolders as $excludeFolder) {
                        if (strpos($path, "/$excludeFolder/") !== false) {
                            $exclude = true;
                            break;
                        }
                    }

                    foreach ($excludeFilePaths as $excludeFilePath) {
                        if (strpos($path, $excludeFilePath) !== false) {
                            $exclude = true;
                            break;
                        }
                    }

                    if (!$exclude) {
                        $files[] = $path;
                    }
                }

                return $files;
            }


            function extractTranslations($filePath) {
                $content = file_get_contents($filePath);

                // Regex for translate with 1 argument
                preg_match_all('/translate\(([^)]+)\)/', $content, $translateMatches);

                // Regex for translate_n_noop with 2 arguments
                preg_match_all('/translate_n_noop\(([^)]+)\)/', $content, $noopMatches);

                return [
                    'translate' => $translateMatches,
                    'translate_n_noop' => $noopMatches
                ];
            }

            // Scan the folder and get all PHP and Twig files
            $files = scanFolder($themeFolderPath, $excludeFolders, $excludeFilePaths);

            $translations = [
                'translate' => [],
                'translate_n_noop' => []
            ];


            /*if (is_plugin_active('multilingual-contact-form-7-with-polylang/plugin.php')) {
                global $wpdb;
                $posts = $wpdb->get_results("
                    SELECT ID, post_content 
                    FROM {$wpdb->posts} 
                    WHERE post_type = 'wpcf7_contact_form'
                ");
                $placeholders = [];
                foreach ($posts as $post) {
                    preg_match_all('/\{([^}]*)\}/', $post->post_content, $matches);
                    if (!empty($matches[1])) {
                        $placeholders[] = $matches[1];
                    }
                }
                $translations["translate"] = $placeholders;
            }*/ 

            // Extract translations from each file
            foreach ($files as $file) {
                $matches = extractTranslations($file);
                
                foreach ($matches['translate'][0] as $index => $match) {
                    // Split arguments by comma
                    $arguments = array_map('trim', explode(',', $matches['translate'][1][$index]));
                    
                    if (count($arguments) === 1) {
                        // translate case
                        $translations['translate'][] = $arguments[0];
                    }
                }
                
                foreach ($matches['translate_n_noop'][0] as $index => $match) {
                    // Split arguments by comma
                    $arguments = array_map('trim', explode(',', $matches['translate_n_noop'][1][$index]));
                    
                    if (count($arguments) === 2) {
                        // translate_n_noop case
                        $translations['translate_n_noop'][] = $arguments;
                    }
                }
            }

            // Remove duplicates
            $translations['translate'] = array_unique($translations['translate']);
            $translations['translate_n_noop'] = array_unique($translations['translate_n_noop'], SORT_REGULAR);

            // Create or overwrite the output file
            $output = fopen($outputFile, 'w');
            fwrite($output, "<"."?"."php\n");
            foreach ($translations['translate'] as $translation) {
                fwrite($output, "__($translation, \"$textDomain\");\n");
            }
            foreach ($translations['translate_n_noop'] as $translationPair) {
                fwrite($output, "_n_noop($translationPair[0], $translationPair[1], \"$textDomain\");\n");
            }
            fwrite($output, "?".">");
            fclose($output);


            $outputLangFile = $outputDir . '/translates.json';
            file_put_contents($outputLangFile, "[]");
            $output = fopen($outputLangFile, 'w');
            if($translations['translate']){
                $translations_new = [];
                foreach ($translations['translate'] as $translation) {
                    $translations_new[] = trim($translation, "\"'");
                }
                $translation_lang = json_encode(array_values($translations_new), JSON_UNESCAPED_UNICODE);
                fwrite($output, $translation_lang);        
            }
            fclose($output);
            $total = count($translations['translate']) + count($translations['translate_n_noop']);

            $message = "Translations file have been updated with ".$total." translations";
            $type = "success";

            if(function_exists("add_admin_notice")){
                add_admin_notice($message, $type);
            } 
        }
    }
    return 0;
}
add_filter('acf/update_value/name=enable_extract_translations', 'acf_development_extract_translations', 10, 4);






add_action('wp_ajax_acf_export_field_groups', 'acf_export_field_groups_to_json');
add_action('wp_ajax_nopriv_acf_export_field_groups', 'acf_export_field_groups_to_json');

function acf_export_field_groups_to_json() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
        exit;
    }

    // ACF field group'ları al ve filtrele
    $theme = wp_get_theme();
    $textDomain = $theme->get('TextDomain');
    $groups = acf_get_field_groups();
    $filtered_groups = array_filter($groups, function ($group) use ($textDomain) {
        if (isset($group['acfe_categories'])) {
            $categories = array_keys($group['acfe_categories']);
            if(in_array($textDomain, $categories)){
            	return false;
            }
            return array_intersect($categories, ['block', 'common', 'general']);
        }
        return false;
    });

    if (!$filtered_groups) {
        wp_send_json_error(['message' => 'No matching field groups found']);
        exit;
    }

    // JSON'ları bir diziye kaydet
	$json_files = [];
	foreach ($filtered_groups as $group) {
	    if (isset($group['local_file']) && file_exists($group['local_file'])) {
	        $json_data = json_decode(file_get_contents($group['local_file']), true);

	        if (!$json_data) {
	            continue; // JSON verisi geçerli değilse atla
	        }

	        // Belirli bir grup için özel düzenleme
	        if ($group['key'] === "group_66e309dc049c4") {
	            if (isset($json_data['fields']) && is_array($json_data['fields'])) {
	                foreach ($json_data['fields'] as &$field) {
	                    if (isset($field['name']) && $field['name'] === 'acf_block_columns') {
	                        if (isset($field['layouts'])) {
	                            $field['layouts'] = (object)[]; // layouts alanını boş bir nesne yap
	                        }
	                    }
	                }
	            }
	        }

	        $file_name = sanitize_title($group['key']) . '.json';
	        $json_path = wp_upload_dir()['basedir'] . '/' . $file_name;

	        // JSON verisini temizle ve yaz
	        file_put_contents($json_path, json_encode($json_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
	        $json_files[] = $json_path;
	    }
	}


    // ZIP dosyası oluştur
    $zip_file = wp_upload_dir()['basedir'] . '/acf-field-groups.zip';
    $zip = new ZipArchive();
    if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        foreach ($json_files as $file) {
            $zip->addFile($file, basename($file));
        }
        $zip->close();
    } else {
        wp_send_json_error(['message' => 'Failed to create ZIP file']);
        exit;
    }

    // JSON dosyalarını sil
    foreach ($json_files as $file) {
        unlink($file);
    }

    // ZIP dosyasını indir
    wp_send_json_success(['zip_url' => wp_upload_dir()['baseurl'] . '/acf-field-groups.zip']);
}
add_action('admin_footer', function () {
    if (!is_admin()) {
        return;
    }

    ?>
    <script>
        jQuery(document).ready(function ($) {
            $('.acf-export-button button').on('click', function (e) {
                e.preventDefault();
                var $button = $(this);
                $button.prop('disabled', true).text('Exporting...');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'acf_export_field_groups'
                    },
                    success: function (response) {
                        if (response.success) {
                            window.location.href = response.data.zip_url;
                        } else {
                            alert('Error: ' + response.data.message);
                        }
                        $button.prop('disabled', false).text('Export');
                    },
                    error: function () {
                        alert('An unexpected error occurred.');
                        $button.prop('disabled', false).text('Export');
                    }
                });
            });
        });
    </script>
    <?php
});




function acf_json_to_db($acf_json_path = "") {
    // ACF JSON klasör yolu
    if(empty($acf_json_path)){
    	$acf_json_path = get_template_directory() . '/acf-json';
    }
  
    // Klasör kontrolü
    if (!is_dir($acf_json_path)) {
        return ['success' => false, 'message' => 'acf-json directory not found'];
    }

    // JSON dosyalarını al
    $json_files = glob($acf_json_path . '/*.json');

    if (empty($json_files)) {
        return ['success' => false, 'message' => 'No JSON files found in acf-json directory'];
    }

    $imported_groups = [];
    foreach ($json_files as $file) {
        // Dosyayı oku ve JSON verisini çözümle
        $json_content = file_get_contents($file);
        $field_group = json_decode($json_content, true);

        if (json_last_error() !== JSON_ERROR_NONE || empty($field_group)) {
            continue; // Geçersiz JSON dosyalarını atla
        }

        if (isset($field_group['key'])) {
        	if(!in_array($field_group['title'], $imported_groups)){
	            // Var olan grup kontrolü
	            $existing_group = acf_get_field_group($field_group['key']);

	            /*if ($existing_group) {
	                // Mevcut grup varsa sil
	                acf_delete_field_group($existing_group['ID']);
	            }*/
	            if ($existing_group) {
				    acf_update_field_group(array_merge($existing_group, $field_group));
				} else {
				    acf_import_field_group($field_group);
				}

	            // Yeni grubu veritabanına ekle
	            //acf_import_field_group($field_group);
	            $imported_groups[] = $field_group['title'];        		
        	}

        }
    }

    if (!empty($imported_groups)) {
        return ['success' => true, 'message' => 'Registered ACF field groups: ' . implode(', ', $imported_groups)];
    } else {
        return ['success' => false, 'message' => 'No field groups were register.'];
    }
}









function acf_units_field_value($value){
    $val = 0;
    if(is_array($value)){
        if(isset($value["value"]) && !empty($value["value"])){
            $val = $value["value"].$value["unit"];
        }
    }
    return $val;
}

function save_theme_styles_colors($theme_styles){
        // Colors
        $colors_list_default = ["primary", "secondary", "tertiary","quaternary", "gray", "danger", "info", "success", "warning", "light", "dark"];
        $colors_list_file = THEME_STATIC_PATH . 'data/colors.json';
        $colors_mce_file = THEME_STATIC_PATH . 'data/colors_mce.json';
        $colors_file = THEME_STATIC_PATH . 'scss/_colors.scss';
        file_put_contents($colors_file, "");
        $colors_code = "";
        $colors_mce = [];
        $custom_colors = "$"."custom-colors: (\n";
        $colors_list = "$"."custom-colors-list: ";
        $colors = $theme_styles["colors"];
        foreach(["primary", "secondary","tertiary", "quaternary"] as $color){
            if(!empty($colors[$color])){
                $colors_code .= "$".$color.": ".scss_variables_color($colors[$color]).";\n";
                $custom_colors .= "\t".$color.": ".scss_variables_color($colors[$color]).",\n";
                $colors_list .= $color.",";
                $colors_mce[scss_variables_color($colors[$color])] = $color;
            }
        }
        if($colors["custom"]){
            foreach($colors["custom"] as $key => $color){
                $colors_code .= "$".$color["title"].": ".scss_variables_color($color["color"]).";\n";
                $custom_colors .= "\t".$color["title"].": ".scss_variables_color($color["color"]).",\n";
                $colors_list .= $color["title"].($key<count($colors["custom"])-1?",":"");
                $colors_list_default[] = $color["title"];
                $colors_mce[scss_variables_color($color["color"])] = $color["title"];
            }
        }
        $custom_colors .= ");\n";
        $colors_list .= ";\n";
        file_put_contents($colors_file, $colors_code.$custom_colors.$colors_list);
        file_put_contents($colors_list_file, json_encode($colors_list_default)); 
        file_put_contents($colors_mce_file, json_encode($colors_mce)); 
}

function save_theme_styles_header_themes($header){
    // Header Themes
        $header_themes_file = THEME_STATIC_PATH . 'scss/_header-themes.scss';
        file_put_contents($header_themes_file, ""); 
        $header_themes = $header["themes"];
        if($header_themes){
            $dom_elements = ["body", "header"];
            $code = "";
            foreach($header["themes"] as $theme){
                $theme["class"] = in_array($theme["class"], $dom_elements)?$theme["class"]:".".$theme["class"];
                $z_index = empty($theme["z-index"])?"null":$theme["z-index"];

                $default = $theme["default"];
                $color = empty($default["color"])?"null":$default["color"];
                $color_active = empty($default["color_active"])?"null":$default["color_active"];
                $bg_color = empty($default["bg_color"])?"null":$default["bg_color"];
                $logo = empty($default["logo"])?"null":$default["logo"];
                
                $affix = $theme["affix"];
                $color_affix = empty($affix["color"])?"null":$affix["color"];
                $color_active_affix = empty($affix["color_active"])?"null":$affix["color_active"];
                $bg_color_affix = empty($affix["bg_color"])?"null":$affix["bg_color"];
                $logo_affix = empty($affix["logo"])?"null":$affix["logo"];
                $btn_reverse = scss_variables_boolean($affix["btn_reverse"]);

                $code .= $theme["class"].":not(.menu-open){\n";
                    $code .= "@include headerTheme(";
                        $code .= $color.",";
                        $code .= $color_active.",";
                        $code .= $bg_color.",";
                        $code .= $logo.",";
                        $code .= $color_affix.",";
                        $code .= $color_active_affix.",";
                        $code .= $bg_color_affix.",";
                        $code .= $logo_affix.",";
                        $code .= $z_index.",";
                        $code .= $btn_reverse;
                    $code .= ");\n";
                $code .= "}\n";
            }
            file_put_contents($header_themes_file, $code); 
        }
}

function acf_save_menu_safelist_classes($post_id) {

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // if (get_post_type($post_id) !== 'your_post_type') return;
    $menu_classes = array();
    if (have_rows('header_tools_start', $post_id)) {
        while (have_rows('header_tools_start', $post_id)) {
            the_row();
            $menu_class = get_sub_field('menu_class');
            $classes = array_map('trim', array_filter(explode(' ', $menu_class)));
            $menu_classes[] = $classes;
        }
    }
    if (have_rows('header_tools_center', $post_id)) {
        while (have_rows('header_tools_center', $post_id)) {
            the_row();
            $menu_class = get_sub_field('menu_class');
            $classes = array_map('trim', array_filter(explode(' ', $menu_class)));
            $menu_classes[] = $classes;
        }
    }
    if (have_rows('header_tools_end', $post_id)) {
        while (have_rows('header_tools_end', $post_id)) {
            the_row();
            $menu_class = get_sub_field('menu_class');
            $classes = array_map('trim', array_filter(explode(' ', $menu_class)));
            $menu_classes[] = $classes;
        }
    }
    if (have_rows('theme_styles', $post_id)) {
        $theme_styles = get_field("theme_styles", $post_id);
        if($theme_styles){
            $header_themes = $theme_styles["header"]["themes"];
            if($header_themes){
                foreach($header_themes as $theme){
                    $class = $theme["class"];
                    if(!in_array($class, ["body", "html"])){
                        $classes = array_map('trim', array_filter(explode(' ', $class)));
                        //$menu_classes[] = $classes;
                        $menu_classes[] = $classes;                            
                    }
                }
            }
        }
    }


    // ACF block'larındaki class'ları kontrol et
    $blocks = parse_blocks(get_post_field('post_content', $post_id));
    if($blocks){
        foreach ($blocks as $block) {

            if ($block['blockName'] === 'acf/social-media') {
                if (isset($block['attrs']['data'])) {
                    $block_data = $block['attrs']['data'];
                    if (isset($block_data['accounts']) && is_numeric($block_data['accounts'])) {
                        $account_count = $block_data['accounts']; // Örneğin 3
                        for ($i = 0; $i < $account_count; $i++) {
                            $account_name = isset($block_data["accounts_{$i}_name"]) ? $block_data["accounts_{$i}_name"] : '';
                            if ($account_name) {
                                $class = "fa-".$account_name;
                                $classes = array_map('trim', array_filter(explode(' ', $class)));
                                $menu_classes[] = $classes;
                            }
                        }
                    }
                }
                break;
            }
            
        }
    }

    if($menu_classes){
        $merged_menu_classes = array_map('trim', array_filter(array_unique(array_merge(...$menu_classes))));
        $json_data = array_values($merged_menu_classes);
        update_dynamic_css_whitelist($json_data);

        /*$merged_menu_classes = array_map('trim', array_filter(array_unique(array_merge(...$menu_classes))));
        $json_data = json_encode(['dynamicSafelist' => array_values($merged_menu_classes)], JSON_PRETTY_PRINT);
        $file_path = HEME_STATIC_PATH . 'data/css_safelist.json';
        file_put_contents($file_path, $json_data);*/
    }
}
add_action('acf/save_post', 'acf_save_menu_safelist_classes', 20);

function acf_theme_styles_save_hook($post_id) {
    if (have_rows('theme_styles', $post_id)) {
        $theme_styles = get_field('theme_styles', 'option');
        //print_r($theme_styles);
        //die;
        if($theme_styles){
            $action = $theme_styles["theme_styles_action"];
            $path = THEME_STATIC_PATH . 'data/theme-styles';
            if(!is_dir($path)){
                mkdir($path, 0755, true);
            }
            switch ($action) {
                case 'revert':
                    $preset_file = SH_STATIC_PATH . 'data/theme-styles-default.json';
                    $json_file = file_get_contents($preset_file);
                    $theme_styles = json_decode($json_file, true);
                    $theme_styles["theme_styles_action"] = "";
                    $theme_styles["theme_styles_filename"] = "";
                    update_field('theme_styles', $theme_styles, $post_id);
                break;
                case 'save':
                    $timestamp = time();
                    $filename = sanitize_title($theme_styles["theme_styles_filename"]);//.".".$timestamp;
                    $preset_file = THEME_STATIC_PATH . 'data/theme-styles/'.$filename.'.json';
                    //$theme_styles["theme_styles_action"] = "";
                    //$theme_styles["theme_styles_filename"] = "";
                    //update_field('theme_styles', $theme_styles, $post_id);
                    $theme_styles["theme_styles_presets"] = "";
                    $json_data = json_encode($theme_styles);
                    file_put_contents($preset_file, $json_data); 
                break;
                case 'load':
                    $filename = $theme_styles["theme_styles_presets"];
                    if(!empty($filename)){
                        $preset_file = THEME_STATIC_PATH . 'data/theme-styles/'.$filename.'.json';
                        $json_file = file_get_contents($preset_file);
                        $theme_styles = json_decode($json_file, true);
                        $theme_styles["theme_styles_action"] = "save";
                        $theme_styles["theme_styles_filename"] = $filename;
                        $theme_styles["theme_styles_presets"] = "";
                        update_field('theme_styles', $theme_styles, $post_id);
                    }
                break;
            }
            // save latest
            $preset_file = THEME_STATIC_PATH . 'data/theme-styles/latest.json';
            $json_data = json_encode($theme_styles);
            file_put_contents($preset_file, $json_data); 
            //save colors
            save_theme_styles_colors($theme_styles);
            save_theme_styles_header_themes($theme_styles["header"]);
        }
    }
}
add_action('acf/save_post', 'acf_theme_styles_save_hook', 10);

function acf_theme_styles_load_presets( $field ) {
    $path = THEME_STATIC_PATH . 'data/theme-styles/';
    if(is_dir($path)){
        $handle = $path;
        $templates = array();// scandir($handle);
        if ($handle = opendir($handle)) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != "..") {
                    $templates[] = $entry;
                }
            }
            closedir($handle);
        }        
    }else{
        $path = SH_STATIC_PATH . 'data/';
        $templates = ["theme-styles-default.json"];
    }
    $field['choices'] = array();
    if( is_array($templates) ) {
        foreach( $templates as $template ) {
            $filepath = $path . $template;
            if (file_exists($filepath)) {
                $save_date = date("d.m.Y H:i", filemtime($filepath));
                $template = str_replace(".json", "", $template);
                $field['choices'][ $template ] = $template." [".$save_date."]";
            }
        }        
    }
    if(count($field["choices"]) == 0){
        $field['choices'][""] = "Not found any preset";
    }
    return $field;
}
add_filter('acf/load_field/name=theme_styles_presets', 'acf_theme_styles_load_presets');





add_filter('acf/update_value/name=modal_home', function ($value, $post_id, $field) {
    $home_id = get_option('page_on_front');
    if ($home_id) {
        wp_update_post([
            'ID' => $home_id,
        ]);
    }
    return $value;
}, 10, 3);
