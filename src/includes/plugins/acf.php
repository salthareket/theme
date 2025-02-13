<?php
use Timber\Timber;
use Timber\Loader;

use SaltHareket\Theme;

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
		if ($posts->found_posts && $type == "main") { 
			error_log("post var mı?");
		    $posts = $posts->to_array()[0]; 
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
	if ($posts->found_posts) { 
	    $posts = $posts->to_array()[0]; 
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

function acf_dynamic_container($class="", $page_settings = array(), $manually = false){
	$offcanvas = false;
	if(isset($page_settings["add_offcanvas"])){
		$offcanvas = $page_settings["add_offcanvas"];
	}
	return $class.($offcanvas?"-fluid":"");
}

function get_archive_field($field = "", $post_type = "post"){
	return get_field($field, $post_type.'_options');
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



function unit_value($val=array()){
	$value = "";
	if(isset($val["value"])){
		$value = $val["value"].$val["unit"];
	}
	return $value;
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