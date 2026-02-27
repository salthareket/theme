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

/*function acf_get_theme_styles() {
    // 1. STATİK CACHE: PHP belleğinde varsa (aynı istek içinde ikinci kez çağrılırsa) direkt dön.
    static $cached_styles = null;
    if ($cached_styles !== null) {
        return $cached_styles;
    }

    $theme_styles_latest = get_template_directory() . "/theme/static/data/theme-styles/latest.json";
    $theme_styles_defaults = SH_STATIC_PATH . "data/theme-styles-default.json";
    $theme_styles = [];

    // 2. ÖNCELİK: Güncel JSON dosyası (I/O işlemini tek seferde bitirelim)
    if (file_exists($theme_styles_latest)) {
        $theme_styles = json_decode(file_get_contents($theme_styles_latest), true);
    }

    // 3. FALLBACK: JSON yoksa DB'ye (QueryCache) git
    if (empty($theme_styles)) {
        $theme_styles = get_option("options_theme_styles");
    }

    // 4. SON ÇARE: Default JSON dosyası
    if (empty($theme_styles) && file_exists($theme_styles_defaults)) {
        $theme_styles = json_decode(file_get_contents($theme_styles_defaults), true);
    }

    // 5. SONUÇ: Belleğe kaydet ve gönder
    $cached_styles = $theme_styles;
    return $theme_styles;
}*/
function acf_get_theme_styles() {
    // 1. STATİK CACHE: Aynı request içinde 2. kez çağırmayı engeller.
    static $cached_styles = null;
    if ($cached_styles !== null) {
        return $cached_styles;
    }

    // 2. TRANSIENT: Veritabanı Cache'i (Disk okumasından çok daha hızlıdır)
    // JSON dosyası değişmediği sürece diske hiç bakmayacağız.
    $cached_styles = get_transient('sh_theme_styles_cache');
    if ($cached_styles !== false) {
        return $cached_styles;
    }

    // --- Buradan aşağısı cache patladığında veya ilk kez çalışır ---

    $theme_styles_latest = get_template_directory() . "/theme/static/data/theme-styles/latest.json";
    $theme_styles_defaults = SH_STATIC_PATH . "data/theme-styles-default.json";
    $theme_styles = [];

    // Önce güncel JSON
    if (file_exists($theme_styles_latest)) {
        $theme_styles = json_decode(file_get_contents($theme_styles_latest), true);
    }

    // Fallback: DB (get_option zaten WP tarafından cache'lenir, iyidir)
    if (empty($theme_styles)) {
        $theme_styles = get_option("options_theme_styles");
    }

    // Son çare: Default JSON
    if (empty($theme_styles) && file_exists($theme_styles_defaults)) {
        $theme_styles = json_decode(file_get_contents($theme_styles_defaults), true);
    }

    // 3. CACHE YAZMA: Sonucu 24 saatliğine cache'le
    if (!empty($theme_styles)) {
        set_transient('sh_theme_styles_cache', $theme_styles, DAY_IN_SECONDS);
    }

    $cached_styles = $theme_styles;
    return $theme_styles;
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
/*function acf_get_contacts_v1($type=""){
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
		$args = QueryCache::wp_query($args);
		$posts = Timber::get_posts($args);
		if ($posts->found_posts) { 
			//error_log("post var mı?");
		    $posts = $posts->to_array()[0]; 
		}
	//}
	return $posts;
}*/
function acf_get_contacts($type = "") {
    $posts = array();
    
    // Varsayılan WP_Query argümanları
    $args = array(
        "post_type"      => "contact",
        "posts_per_page" => -1, // Sınırsız çekmek için
        'orderby'        => "menu_order",
        'order'          => 'ASC',
        'fields'         => 'ids'
    );

    // Kategori filtresi varsa ekle
    if (!empty($type)) {
        // Options sayfasından kategori ID'sini alıyoruz
        $category_id = get_option("options_contact_type_" . $type); // ACF genelde başına 'options_' ekler
        
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

    // 1. HATA: Metot adı QueryCache::get_cached_query olmalı
    // 2. DETAY: Timber ile kullanırken 'ids' modunda çekmek en hızlısıdır
    $post_ids = get_posts($args);

    if (!empty($post_ids)) {
        // Timber'a ID listesini verip objeleri alıyoruz
        $posts = Timber::get_posts($post_ids);
        
        // Eğer sadece tek bir post (ilk post) lazımsa:
        if ($type == "main" && !empty($posts)) {
            $posts = $posts[0];
        }
    }

    return $posts;
}
/*function acf_get_contact_related($post_id=0, $post_type="post"){
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
	$posts = QueryCache::wp_query($args);
	$posts = Timber::get_posts($posts);
	if ($posts->found_posts) { 
	    $posts = $posts->to_array()[0]; 
	}
    return $posts;
}*/
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
/*function acf_get_accounts($post=array()){
	$accounts = array();
	if(isset($post->ID)){
		$accounts = get_field("contact_accounts", $post->ID);
	}
    return $accounts;
}*/
function acf_get_accounts($post = array()){
    $accounts = array();
    
    // Eğer $post bir obje ise ID'sini al, değilse gelen değeri kullan
    $post_id = isset($post->ID) ? $post->ID : $post;

    if ($post_id) {
        // 🔥 Sınıfın yeni metodunu çağırıyoruz. 
        // Bu işlem veriyi cache'ler ve manifest'e "post_id" ile bağlar.
        $accounts = get_field("contact_accounts", $post_id);
    }
    
    return $accounts;
}
/*function get_contact_form($slug=""){
	$arr = array();
	$forms = get_option("forms");
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
	$forms = get_option("forms");
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
}*/
/**
 * Tekil bir formu slug ile getirir
 */
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