<?php
use Timber\Timber;
use Timber\Loader;

use SaltHareket\Theme;


/*acf Google Maps key*/
if(Data::get("google_maps_api_key")){
	acf_update_setting('google_api_key', Data::get("google_maps_api_key"));
}

if (ENABLE_MULTILANGUAGE){

	add_filter('acf/settings/default_language', 'my_acf_settings_default_language');
	function my_acf_settings_default_language( $language ) {
	    static $default_lang = null;
	    if ($default_lang === null) {
	        $default_lang = ml_get_default_language();
	    }
	    return $default_lang;
	}

	add_filter('acf/settings/current_language', 'my_acf_settings_current_language');
	function my_acf_settings_current_language( $language ) {
	    static $current_lang = null;
	    if ($current_lang === null) {
	        $current_lang = ml_get_current_language();
	    }
	    return $current_lang;
	}
	
}

/*
 * acf_get_theme_styles() — Eski ACF tabanlı sistem kaldırıldı.
 * Yeni sistem: theme-styles app (Theme_Styles class).
 * Fonksiyon adı geriye uyumluluk için korundu.
 */
if ( ! function_exists( 'acf_get_theme_styles' ) ) {
function acf_get_theme_styles(): array {
    // Static cache — aynı request içinde tekrar okuma
    static $cache = null;
    if ( $cache !== null ) return $cache;

    // 1. Yeni sistem: Theme_Styles
    if ( class_exists( 'Theme_Styles' ) ) {
        $data = Theme_Styles::init()->get_data();
        if ( ! empty( $data ) ) {
            $cache = $data;
            return $cache;
        }
    }

    // 2. Yeni sistemin JSON dosyası (class yüklü değilse)
    $new_json = get_template_directory() . '/theme/static/data/theme-styles/latest.json';
    if ( file_exists( $new_json ) ) {
        $data = json_decode( file_get_contents( $new_json ), true );
        if ( ! empty( $data ) ) {
            $cache = $data;
            return $cache;
        }
    }

    $cache = [];
    return $cache;
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

function acf_get_contacts($type = "") {
    // 1. STATİK DEPO: Fonksiyonun hafızasını oluşturuyoruz
    static $contacts_cache = [];


    // 2. KONTROL: Eğer bu 'type' daha önce çekildiyse, DB'ye gitme, hafızadan ver
    if (isset($contacts_cache[$type])) {
        return $contacts_cache[$type];
    }

    $posts = array();
    
    // Varsayılan WP_Query argümanları
    $args = array(
        "post_type"      => "contact",
        "posts_per_page" => -1,
        'orderby'        => "menu_order",
        'order'          => 'ASC',
        'fields'         => 'ids'
    );

    if (!empty($type)) {
        $category_id = QueryCache::get_option("options_contact_type_" . $type);
        if ($category_id) {
            $args["tax_query"] = array(
                array(
                    "taxonomy" => "contact-type",
                    "field"    => "term_id",
                    "terms"    => [$category_id],
                    "operator" => "IN"
                )
            );
        }
    }


    $post_ids = QueryCache::get_posts($args);

    if (!empty($post_ids)) {
        // BURAYI DEĞİŞTİR: Timber'ı wrap içine alıyoruz
        $posts = QueryCache::wrap('timber_contacts_' . md5(serialize($post_ids)), function() use ($post_ids) {
            return Timber::get_posts($post_ids);
        });
        
        if ($type == "main" && !empty($posts)) {
            // Timber v2 uyumlu ilk eleman alma
            if ($posts instanceof \Timber\PostArrayObject || is_array($posts)) {
                $posts = $posts[0]; 
            }
        }
    }


    // 3. KAYIT: Çekilen veriyi hafızaya (cache) yaz
    $contacts_cache[$type] = $posts;

    return $posts;
}
function acf_get_contact_related($post_id = 0, $post_type = "post") {
    if (!$post_id) return false;

    $args = array(
        "post_type"      => $post_type,
        "posts_per_page" => 1, // numberposts yerine posts_per_page kullanmak daha standarttır
        "orderby"        => "menu_order",
        "order"          => "ASC",
        "fields"         => "ids",
        "meta_query"     => array(
            array(
                "key"     => "contact",
                "value"   => '"' . $post_id . '"', // ACF relationship formatı için ("123")
                "compare" => "LIKE"
            )
        )
    );

    // 1. Senin yeni isimlendirmenle çağırıyoruz ve 'ids' modunda çekiyoruz
    // Dönen veri düz bir ID array'idir: [12, 45, 67]
    $post_ids = new WP_Query($args);

    // 2. Timber kontrolü
    if (!empty($post_ids)) {
        $timber_posts = Timber::get_posts($post_ids);
        
        // found_posts objede olmaz çünkü get_query array döndürür.
        // Dizi doluysa ilkini veriyoruz.
        return (!empty($timber_posts)) ? $timber_posts[0] : false;
    }

    return false;
}
function acf_get_accounts($post = array()){
    $accounts = array();
    
    // Eğer $post bir obje ise ID'sini al, değilse gelen değeri kullan
    $post_id = isset($post->ID) ? $post->ID : $post;

    if ($post_id) {
        // 🔥 Sınıfın yeni metodunu çağırıyoruz. 
        // Bu işlem veriyi cache'ler ve manifest'e "post_id" ile bağlar.
        $accounts = QueryCache::get_field("contact_accounts", $post_id);
    }
    
    return $accounts;
}

function get_contact_form($slug = "") {
    if (empty($slug)) return array();

    $forms = QueryCache::get_field("forms", "options");
    
    if (is_array($forms)) {
        foreach ($forms as $form) {
            if ($slug === ($form["slug"] ?? '')) {
                return array(
                    "id"          => $form["form"] ?? "",
                    "title"       => $form["title"] ?? "",
                    "description" => $form["description"] ?? ""
                );            
            }
        }        
    }
    
    return array();
}

/**
 * Tüm formları listeler veya tek bir formu slug ile döndürür
 */
function get_contact_forms($slug = "") {
    // Eğer slug varsa direkt diğer fonksiyonu çalıştır (Kod tekrarını önleriz)
    if (!empty($slug)) {
        return get_contact_form($slug);
    }

    $arr = array();
    $forms = QueryCache::get_field("forms", "options");

    if (is_array($forms)) {
        foreach ($forms as $form) {
            $f_slug = $form["slug"] ?? "";
            if ($f_slug) {
                $arr[$f_slug] = array(
                    "id"          => $form["form"] ?? "",
                    "title"       => $form["title"] ?? "",
                    "description" => $form["description"] ?? ""
                );
            }
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
			       'src' => 'http://maps.googleapis.com/maps/api/staticmap?center=' . urlencode( $location['lat'] . ',' . $location['lng'] ). '&zoom='.$location['zoom'].'&size=800x800&maptype=roadmap&sensor=false&markers='.$staticMarker.'&key='.Data::get("google_maps_api_key"),
				   'url' => 'http://www.google.com/maps/@'. $location['address'] ,
				   'url_iframe' => 'https://www.google.com/maps/embed/v1/place?key='.Data::get("google_maps_api_key").'&q='.$location['lat'] . ',' . $location['lng'],
				   'embed' => '<div id="'.$id.'" class="'.$className.' map-google" data-lat="'.$location['lat'].'" data-lng="'.$location['lng'].'" data-zoom="'.$location['zoom'].'" data-icon="'.$icon.'"></div>'
			   );			
	}
	return $result;
}

function acf_dynamic_container($class="", $page_settings = array(), $manually = false){
	$offcanvas = false;
	if(isset($page_settings["add_offcanvas"])){
		$offcanvas = $page_settings["add_offcanvas"];
	}
	return $class.($offcanvas?"-fluid":"");
}

function get_archive_field($field = "", $post_type = "post"){
	return QueryCache::get_field($field, $post_type.'_options');
}

add_filter('acf_osm_marker_icon', function( $icon ) {
    $img = QueryCache::get_field("logo_marker", "option");
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
function acf_offcanvas_content_classes($page_settings = []) {
    $classes = "";
    $size = $page_settings["offcanvas"]["size"] ?? 'md';
    $width = $page_settings["offcanvas"]["width"] ?? 12;

    // Numeric değilse integer’a çevir
    if (!is_numeric($width)) {
        $width = 12 - 0; // default fallback
    } else {
        $width = 12 - (int)$width; // numeric ise dönüştür
    }

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

if(!function_exists("get_field_default")){
	function get_field_default($field_name, $id = 'options'){
		return QueryCache::get_field($field_name, 'options');
	}
}