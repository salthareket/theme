<?php
function timber_get_menu($name) {
    global $wpdb;
    
    // 1. RUNTIME CACHE (RAM): Sayfa içinde mükerrer sorguyu engeller
    static $runtime_menu_cache = [];
    
    $lang = function_exists('ml_get_current_language') ? ml_get_current_language() : get_locale();
    $key = '_transient_qcache_menu_' . $name . '_' . $lang;

    // Eğer bu menü bu sayfa yüklenirken zaten çekildiyse, DB'ye gitme, RAM'den ver
    if (isset($runtime_menu_cache[$key])) {
        // error_log("RAM'DEN GELDİ BELEŞ: " . $key);
        return $runtime_menu_cache[$key];
    }

    // 2. Şalter Kontrolü
    if (\QueryCache::$cache === false || (\QueryCache::$config['menu'] ?? true) === false) {
        return Timber::get_menu($name);
    }

    // 3. DB'DEN ÇEK (Eğer RAM'de yoksa)
    $cached_val = $wpdb->get_var($wpdb->prepare(
        "SELECT option_value FROM $wpdb->options WHERE option_name = %s", 
        $key
    ));
    
    if ($cached_val) {
        $menu_obj = unserialize($cached_val);
        // Gelecek sefer için RAM'e at
        $runtime_menu_cache[$key] = $menu_obj;
        return $menu_obj;
    }

    // 4. YOKSA TIMBER İLE OLUŞTUR VE DB'YE GÖM
    $menu = Timber::get_menu($name);

    if ($menu) {
        $serialized_menu = serialize($menu);
        $wpdb->query($wpdb->prepare(
            "INSERT INTO $wpdb->options (option_name, option_value, autoload) 
             VALUES (%s, %s, 'no') 
             ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)",
            $key, $serialized_menu
        ));
        // RAM'e de yazalım
        $runtime_menu_cache[$key] = $menu;
    }

    return $menu;
}
function timber_image($id){
	return new Timber\Image($id);
}
/*function _get_term($args, $taxonomy, $class="TimberTerm"){
	return Timber::get_term($args, $taxonomy, $class);
}
function _get_terms($args){
	return Timber::get_terms($args);
}
function _get_post($args){
	return Timber::get_post($args);
}
function _get_posts($args){
	return Timber::get_posts($args);
}
function _query_posts($args){
	return Timber::query_posts($args);
}*/
function _post_query($args){
	return new Timber\PostQuery($args);
}
function _get_field($field, $post_id){
	return \QueryCache::get_field($field, $post_id);
}
function _get_option($field){
	return \QueryCache::get_option($field);//get_field($field, 'option');
}
function _get_option_cpt($field, $post_type){
	return get_field($field, $post_type.'_options');
}
function _get_widgets($widget){
	return Timber::get_widgets( $widget );
}
function _get_page($slug=""){
	$page = get_page_by_path($slug);
	return Timber::get_post($page->ID);
}
function _get_meta($key, $posts){
	$keys = array();
	$post_ids = array();
	if(is_array($posts) ){
	    if(isset($posts[0]["ID"])){
           $post_ids = wp_list_pluck($posts, "ID");
        }else{
   	       $post_ids = $posts;
        }
	}else{
	    $post_ids[] = $posts;
	}
	foreach($post_ids as $post_id){
		$keys[] = get_post_meta( $post_id, $key, true);
	}
    return $keys;
}
function _get_tax_posts($post_type,$taxonomy,$taxonomy_id,$post_count=-1){
  $args=array(
      'post_type' => $post_type,
      'numberposts' => $post_count,
      'tax_query' => array( 
            array(
                'taxonomy' => $taxonomy,
                'field'    => 'id',
                'terms'    => $taxonomy_id
            )
       )
  );
  return Timber::get_posts($args);
}
function division($a, $b) {
    $c = @(a/b); 
    if($b === 0) {
      $c = null;
    }
    return $c;
}

function get_menu_parent($menu){
	foreach($menu as $item){
		if($item->object=='page'){
			if($item->post_parent>0){
			   return  new Timber\Post($item->post_parent);
			   break;
			}
	    }
	}
}

function get_bs_grid($sizes){
	//print_r($sizes);
	$class = array();
	if($sizes){
		foreach($sizes as $key=>$size){
			if(isset($size)){
				if($key == "xs"){
				   $key = "-";
				}else{
				   $key = "-".$key."-";
				}
				$count = 12/$size;
				if (is_int($count)) {
		           $class[] = "col".$key.$count; 
				}else{
				   $class[] = "col".$key."1".$size;
				}
			}
		}		
	}
	return implode(" ", $class);
}

function get_bs_grid_gap($sizes){
	$class = array();
	if($sizes){
		foreach($sizes as $key=>$size){
			if(isset($size)){
				if($key == "xs"){
				   $key = "-";
				}else{
				   $key = "-".$key."-";
				}
		        $class[] = "row".$key.$size; 			
			}
		}		
	}

	return implode(" ", $class);
}

function _get_template($post){
	set_query_var('template_post_id', $post->ID );
	$template = get_template_directory().'/'.$post->_wp_page_template;
	if (file_exists($template)) {
	   return load_template($template, 0);
	}	
}

function _addClass($code, $find, $contains='', $class=''){
	if(empty($code)){
		return $code;
	}
	$html = new simple_html_dom();
    $html->load($code);
    $ul = $html->find($find, 0);
    if($ul){
       if($contains){
       	  if($ul->find($contains,0)){
             $ul->class = $class;
       	  }
	    }else{
	      $ul->class = $class;
	    }
    }
    return $html;
}

function pluralize($count, $singular="", $plural="", $null="", $theme=""){
	return trans_plural($singular, $plural, $null, $count, $theme);
}


function get_offcanvas_toggler($id="", $class="", $content="", $title=""){
    return '<button type="button" class="'.$class.'" data-bs-toggle="offcanvas" data-bs-target="#'.$id.'" aria-label="'.$title.'">'.$content.'</button>';
}


function array_shuffle($array=array()){
	shuffle($array);
    return $array;
}

function timber_add_filter($filter, $value){
	add_filter($filter, function() use ($value){
		echo "ooohhh";
		return $value;
	});
}

function get_timber_template_path( $path ) {
    $locations = \Timber::$dirname;
    foreach ( $locations as $location ) {
        $base_path = trailingslashit( get_stylesheet_directory() ) . trailingslashit( $location );
        $full_path = $base_path . $path;
        if ( file_exists( $full_path ) && pathinfo( $full_path, PATHINFO_EXTENSION ) === 'twig' ) {
            return $full_path;
        }
        if ( is_dir( $full_path ) ) {
            $files = glob( trailingslashit( $full_path ) . '*.twig' );
            if ( ! empty( $files ) ) {
                return $full_path;
            }
        }
    }
    return false;
}


function localization(){
	$localization = new Localization();
    $localization->woocommerce_support = true;
    return $localization;
}