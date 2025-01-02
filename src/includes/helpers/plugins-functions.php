<?php

$GLOBALS["lazy_breakpoints"] = array(
    'xs'    => '(max-width: 575px)',
    'sm'    => '(min-width: 576px) and (max-width: 767px)',
    'sm_ls' => '(min-width: 576px) and (max-width: 767px) and (orientation: landscape)',
    'md'    => '(min-width: 768px) and (max-width: 991px)',
    'lg'    => '(min-width: 992px) and (max-width: 1199px)',
    'xl'    => '(min-width: 1200px) and (max-width: 1399px)',
    'xxl'   => '(min-width: 1400px) and (max-width: 1599px)',
    'xxxl'  => '(min-width: 1600px)'
);


function lazySizes($sizes){
	$code='data-bgset="';
	$sizes_count=count($sizes);
	$counter=0;
	foreach($sizes as $key=>$size){	
	    if ($counter%3 == 0){
		    $code.=$sizes[$key].' '.$sizes[$key.'-width'].'w '.($counter<$sizes_count-3?', ':'');
	    }
	    $counter++;
	};
	$code.='" data-sizes="auto"';
	return $code;
}

function lazySizesResponsive($sizes){
    $code='data-bgset="';
    $imgs = array();
    foreach($sizes as $key=>$size){	
    	if($key != 'lg'){
		    $imgs[]=$sizes[$key].'  [--'.$key.']';
	    }
	};
    $imgs[]=$sizes['lg'];
	return 'data-bgset="'.join(' | ', $imgs).'"';
}

function lazySizesResponsiveBgSet($sizes){
    $code='data-bg-set="';
    $imgs = array();
    foreach($sizes as $key=>$size){	
    	if($key != 'lg'){
		    $imgs[]="url(".$sizes[$key].') '.$GLOBALS["lazy_breakpoints"][$key];
	    }
	};
	$imgs[]=$sizes['lg'];
	return 'data-bg-set="'.join(', ', $imgs).'"';
}

function lazySizesImageResponsive_old($sizes){
	$upload_dir = wp_upload_dir()["url"]."/";
    $code='data-srcset="';
    $imgs = array();
    $sizes_new = array(
        'xs' => '0w',
        'sm' => '576w',
        'sm_ls' => '768w',
        'md' => '768w',
        'lg' => '992w',
        'xl' => '1200w',
        'xxl' => '1400w',
        'xxxl' => '1600w'
    );
    foreach($sizes as $key=>$size){	
    	    if($key != 'lg'){
    	       if(is_array( $sizes[$key])){
    	          if(array_key_exists("file", $sizes[$key])){
                     $imgs[] = $upload_dir.$sizes[$key]["file"].' '.$sizes_new[$key];//.' [--'.$key.']'; 
    	          }	
    	       }else{
    	       	  $sizes[$key] = str_replace($upload_dir, "", $sizes[$key]);
    	       	  $sizes[$key] = $upload_dir . $sizes[$key];
   		          $imgs[]=$sizes[$key].' '.$sizes_new[$key];//.' [--'.$key.']';    	       	
    	       }
	        }
	};
	if(array_key_exists("file", $sizes["lg"])){
      $imgs[]=$upload_dir.$sizes['lg']["file"];		
	}else{
   	  $imgs[]=$sizes['lg'];		
	}

	return 'data-srcset="'.join(',', $imgs).'"';
}

function lazySizesImageResponsive($sizes){
	$upload_dir = wp_upload_dir()["url"]."/";
    $code='data-srcset="';
    $imgs = array();
    $sizes_new = array(
        'xs' => '0w',
        'sm' => '576w',
        'sm_ls' => '768w',
        'md' => '768w',
        'lg' => '992w',
        'xl' => '1200w',
        'xxl' => '1400w',
        'xxxl' => '1600w'
    );
    foreach($sizes as $key=>$size){	
    	    if($key != 'lg'){
    	       if(is_array( $sizes[$key])){
    	          //if(array_key_exists("file", $sizes[$key])){
                     $imgs[] = $upload_dir.$sizes[$key].' '.$sizes_new[$key];//.' [--'.$key.']'; 
    	          //}	
    	       }else{
    	       	  $sizes[$key] = str_replace($upload_dir, "", $sizes[$key]);
    	       	  $sizes[$key] = $upload_dir . $sizes[$key];
   		          $imgs[]=$sizes[$key].' '.$sizes_new[$key];//.' [--'.$key.']';    	       	
    	       }
	        }
	};
	return 'data-srcset="'.join(',', $imgs).'"';
}

function lazySizesPictureResponsive($sizes=array(), $class=""){
	$upload_dir = wp_upload_dir()["url"]."/";
    $code = "<picture class='bg-cover ".$class."'>";
	foreach($sizes as $key=>$size){
	    if(is_array($sizes[$key])){
	        if(array_key_exists("file", $sizes[$key])){
	            $img=$upload_dir.$sizes[$key]["file"];	
            }else{
                $img=$sizes[$key];	
            }
		}else{
			if(strpos($sizes[$key], $upload_dir)>-1){
			   $img = $sizes[$key];
			}else{
			   $img=$upload_dir.$sizes[$key];
			}
		}
		//$query = explode("_", $key);
	    //$code .= "<source data-srcset='".$img."' media='(".$query[0]."-width: ".$query[1]."px)' />";
	    $code .= "<source data-srcset='".$img."' media='--".$key."' />";
	}
    $code .= '<img src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==" data-src="'.$img.'" class="img-fluid lazyload" alt="" />';
    $code .= '</picture>';
    return  $code;
}

function posts_to_slider($posts){
    $slides = array();
	if($posts){
	   $slider_settings = array(
		   	'navigation' => 1,
		   	'navigation_thumbs' => 1,
		   	'autoplay'   => 1,
		   	'delay'      => 4,
		   	'effect'     => 'slide'
	   );
	   foreach($posts as $post){
	      $slides[] = array(
	           "media" => array(
	               "slide_type" => "image",
	               "image"      => array(
	                   "desktop" => get_field("desktop", $post->ID),
	                   "mobile"  => get_field("mobile", $post->ID),
	               ),
	                "add_filter" => 0,
                    "filter_color" => "#be1e2d",
                    "add_opacity" => 1,
                    "opacity_value" => 50,
                    "opacity_color" => "#be1e2d",
                    "color"         => ""
	           ),
	           "content" => array(
                    "title" => "<ul class='list-label list-label-md  list-label-extended list-inline'><li class='list-inline-item'><div class='label'>".$post->category."</div></li></ul>".$post->post_title,
                    "title_color" => "#ffffff",
                    "title_bg_color" => "",
                    "description" => Timber\Twig::time_ago($post->post_date_gmt),//Salt::timeago($post->post_date_gmt),
                    "description_color" => "rgba(255,255,255,.8)",
                    "description_bg_color" => "",
                    "bg_color" => "",
                    "bg_color_opacity" => 100,
                    "align_hr" => "center",
                    "align_vr" => "center",
                    "text_align" => "center",
                    "class_name" => ""
	           ),
	           "url" => array(
                    "url_type" => "multiple",
                    "url_out" => "", 
                    "url_in" => "",
                    "add_button" => 0,
                    "text" => "",
                    "button_bg_color" => "",
                    "button_text_color" => "",
                    "buttons" => array(
                    	array(
                    		"url" => get_permalink($post->ID),
                    		"text" => trans("Devamı"),
                    		"style" => array(
                    			"size" => "btn-lg",
                    			"class" => "light",
                    			"outline" => 1
                    		)
                    	)
                    )
                )
	       );
	   }
	}
	return  array(
			     "slider" => $slides,
			     "settings" => $slider_settings
		    );
}

function dateEstToPst($date){
	$time = new DateTime($date, new DateTimeZone('America/New_York'));
	$time->setTimezone(new DateTimeZone('America/Los_Angeles'));
	return $time->format('h:i a');
}

function get_blog_categories(){
	$args = array(
		'taxonomy' =>  'category',
	    'hide_empty' => false,
	    'exclude' => get_option( 'default_category' ),
	    'orderby' => "name",
	    'order' => 'asc'
	);
	return Timber::get_terms($args);	
}
function get_blog_tags(){
	$args = array(
		'taxonomy' =>  'post_tag',
	    'hide_empty' => false,
	    'orderby' => "name",
	    'order' => 'asc'
	);
	return Timber::get_terms($args);	
}
function get_blog_tag_cloud(){
	$args = array(
	   'number' => 20,
	   'post_type' => 'post',
	   'echo' => false
	);
    return wp_tag_cloud($args);	
}

/*
function updateSearchRank($id, $type){
	if($type == "post"){
		$value = get_post_meta( $id, 'wpcf_search_rank', true );
	    $value = empty($value)||$value==null?0:$value;
	    update_post_meta($id, 'wpcf_search_rank', $value + 1 ); 		
	}else{
		$value = get_term_meta( $id, 'wpcf_search_rank', true );
	    $value = empty($value)||$value==null?0:$value;
	    update_term_meta($id, 'wpcf_search_rank', $value + 1 ); 	
	}
}
*/


function dateIsPast($date){
	$result = false;
	if(!is_object($date)){
        $date = strtotime($date);
	}else{
		$date = date_timestamp_get($date);
	}
    if(intval($date) < intval(time())) {
      $result = true;
    }


	/*print_r($date);

	$result = false;
	
	$date = strtotime($date);  
    //converts seconds into a specific format  
    //$date = date ("Y/d/m H:i", $sec);  
	//$date = new DateTime($date);
	print_r($date);

	//print_r(now());
    $now = new DateTime();
    echo "-----";
    print_r($now);
    echo "-----";
	if($date < $now) {
	    $result = true;
	}*/
	return $result;
}
function datesHasWeekend($start, $end) {
	if(!is_object($start)){
        $start = new DateTime($start);
	}
	if(!is_object($end)){
        $end = new DateTime($end);
	}
    return $start->diff($end)->format('%a') + $start->format('w') >= 6;
}
function datesWeekendDays($start, $end){
	if(!is_object($start)){
        $start = new DateTime($start);
	}
	if(!is_object($end)){
        $end = new DateTime($end);
	}
	$end->modify('+1 day');
    $interval = $end->diff($start);
	// total days
	$days = $interval->days;
	// create an iterateable period of date (P1D equates to 1 day)
	$period = new DatePeriod($start, new DateInterval('P1D'), $end);
	// best stored as array, so you can add more than one
	//$holidays = array('2012-09-07');
	foreach($period as $dt) {
	    $curr = $dt->format('D');
	    // substract if Saturday or Sunday
	    if ($curr != 'Sat' && $curr != 'Sun') {
	        $days--;
	    }
	    // (optional) for the updated question
	    /*elseif (in_array($dt->format('Y-m-d'), $holidays)) {
	        $days--;
	    }*/
	}
	return $days;
}


function class_salt($vars=array()){
	$salt = new Salt();
	$output = "";
	if(isset($vars["function"])){
		$function = $vars["function"];
	    unset($vars["function"]);
	    $output = $salt->$function($vars);
	}
    if(isset($vars["var"])){
		$var = $vars["var"];
	    unset($vars["var"]);
	    $output = $salt->$var;
	}
	return $output;
}

function paginate($paged, $total_pages){
    echo '<nav class="pagination-container pagination-builtin">' .paginate_links(array(  
                  'base' => get_pagenum_link(1) . '%_%',  
                  'format' => '?paged=%#%',  
                  'current' => $paged,  
                  'total' => $total_pages,  
                  'prev_text' => '',  
                  'next_text' => '',
                  'type'     => 'list',
                )).'</nav>';
}

function get_all_languages($native=false){
	require_once ABSPATH . 'wp-admin/includes/translation-install.php';
    $translations = wp_get_available_translations();
   
    $languages = array();
    foreach($translations as $key=>$lang){
    	//$languages[$lang["language"]] = $lang[$native?"native_name":"english_name"];
    	$languages[] = array(
    		"lang" => $lang["language"],
    		"name" => $lang[$native?"native_name":"english_name"]
    	);
    }
    $languages[] = array(
    	"lang" => "en_US",
    	"name" => "English (USA)"
    );

    usort($languages, function($a, $b) {
    	return strcmp($a["name"], $b["name"]);
    });

    return $languages;
}

function get_timezones($field=array()){
	$utc = new DateTimeZone('UTC');
    $dt = new DateTime('now', $utc);
    $fieldValue = "";
    if($field){
	    $fieldValue = trim($field['value']);
		if(!$fieldValue && $field['default_time_zone']){
			$fieldValue = trim($field['default_time_zone']);
		}    	
    }
	$timezones_filtered = array();
	$timezones = \DateTimeZone::listIdentifiers();
	foreach ($timezones as $tz) {
        $current_tz = new \DateTimeZone($tz);
        $transition = $current_tz->getTransitions($dt->getTimestamp(), $dt->getTimestamp());
        $abbr = $transition[0]['abbr'];
        $is_selected = $fieldValue === trim($tz) ? ' selected="selected"' : '';
        $timezones_filtered[$tz] = $tz . ' (' . $abbr . ')';
    }
    return $timezones_filtered;
}

function change_user_login($user_id, $user_login=""){
	if(!empty($user_id) && !empty($user_login)){
		global $wpdb;
		$wpdb->update(
		    $wpdb->users, 
		    ['user_login' => $user_login], 
		    ['ID' => $user_id]
		);
	}
}

function secure_string($string, $base){
   $ajax_nonce = wp_create_nonce( $string . "-" . $base );
}

function isBase64Encoded_old(string $s) : bool{
        if ((bool) preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $s) === false) {
            return false;
        }
        $decoded = base64_decode($s, true);
        if ($decoded === false) {
            return false;
        }
        $encoding = mb_detect_encoding($decoded);
        if (! in_array($encoding, ['UTF-8', 'ASCII'], true)) {
            return false;
        }
        return $decoded !== false && base64_encode($decoded) === $s;
}

function isBase64Encoded($data){
    if (preg_match('%^[a-zA-Z0-9/+]*={0,2}$%', $data)) {
       return true;
    } else {
       return false;
    }
}


function wrap_slabtext_in_content($content) {
    if (preg_match('/<[^>]*?class="slab-text-container[^"]*"[^>]*>(.*?)<\/[^>]*>/s', $content, $match)) {
        $container_content = $match[1];
        $items = preg_split('/(<p>|<br\s*\/?>|\r\n|\n)/', $container_content, -1, PREG_SPLIT_NO_EMPTY);
        $wrapped_items = array_map(function ($item) {
            return '<span class="slabtext">' . $item . '</span>';
        }, $items);
        $new_content = implode('', $wrapped_items);
        $content = str_replace($match[1], $new_content, $content);
    }
    return $content;
}
add_filter('the_content', 'wrap_slabtext_in_content');


function get_map_embed_url($type="leaflet", $location=[]) {
	$lat = 0;
	$lng = 0;
	$zoom = 14;
	if(isset($location["lat"])){
       $lat = $location["lat"];
	}
	if(isset($location["lng"])){
       $lng = $location["lng"];
	}
	if(isset($location["zoom"])){
       $zoom = $location["zoom"];
	}
	switch($type){
		case "leaflet" :
			// Bbox değerlerini marker etrafında küçük bir alan belirleyerek otomatik olarak ayarlıyoruz
		    $bbox_margin = 0.1; // Bu değeri gerektiğinde değiştirebilirsin
		    
		    $left_long = $lng - $bbox_margin;
		    $bottom_lat = $lat - $bbox_margin;
		    $right_long = $lng + $bbox_margin;
		    $top_lat = $lat + $bbox_margin;
		    $url = "https://www.openstreetmap.org/export/embed.html?bbox={$left_long}%2C{$bottom_lat}%2C{$right_long}%2C{$top_lat}&layer=mapnik&marker={$lat}%2C{$lng}";
		break;
		case "google" :
		    /*$google_api_key = acf_get_setting('google_api_key');
    		if ( empty( $google_api_key ) ) {
				$url = 'https://www.google.com/maps/embed/v1/place?key='.$GLOBALS['google_maps_api_key'].'&q='.$lat . ',' . $lng;
			}else{*/
				if (isset($location["url"]) && !empty($location["url"])) {
			        $url = str_replace("!1sen", "!1s" . $GLOBALS["language"], $location["url"]) . "&hl=".$GLOBALS["language"];
			    }else{
			    	$url = 'https://maps.google.com/maps?q='. $lat . ',' . $lng .'&hl='.$GLOBALS['language'].'&z='.$zoom.'&output=embed';
			    }
			/*}*/
		break;
	}
    return $url;
}
/*https://maps.google.com/maps?q=53.3,55.5&hl=en&z=14&output=embed
https://www.google.com/maps/embed?origin=mfe&pb=!1m3!2m1!1s'.$lat.','.$lng.'!6i'.$zoom.'!3m1!1s'.$GLOBALS['language'].'!5m1!1'.$GLOBALS['language']
*/

function get_map_config($fields = array(), $block_meta = array()){
	$html = "";
	$map_service = get_option("options_map_service");
	$map_view = get_option("options_map_view");

	$map_type = isset($fields["map_type"])?$fields["map_type"]:"static";
	$settings = isset($fields['map_settings'])?$fields['map_settings']:[];

	if(!$settings){
		$settings['map'] = array(
			"markers" => [$fields]
		);
	}
	if(empty($block_meta)){
		$block_meta["id"] = "custom_".unique_code(8);
	}
    
    $config = ['locations' => [], 'buttons' => [], 'popup' => [], 'callback' => '' ];
    $map_config = str_replace(['#', '-'], ['', '_'], $block_meta['id']) . '_map_config';
    $map_callback = str_replace(['#', '-'], ['', '_'], $block_meta['id']) . '_map_callback';

    $location_data = [];
    if($map_type == "static"){

    	if (isset($settings['map']["markers"])){
		    foreach ($settings['map']["markers"] as $item) {
		    	$data = array(
		    		"id"    => isset($item["uuid"])?$item["uuid"]:$item["id"],
		    		"title" => isset($item["label"])?$item["label"]:$item["title"],
		    		"lat"   => $item["lat"],
		    		"lng"   => $item["lng"],
		    	);
		    	if(isset($settings['marker']) && $settings['marker']){
		    		$marker = $settings['marker'];
		    	}else{
		    		$marker = get_field("map_marker", "option");
		    		if(!$marker){
		    			$marker = get_field("logo_marker", "option");
		    		}
		    	}
		    	if($marker){
	                $data["marker"] = array(
	                    "icon" => isset($marker["url"])?$marker["url"]:"",
	                    "width" => isset($marker["width"])?$marker["width"]:0,
	                    "height" => isset($marker["height"])?$marker["height"]:0,
	                );
	            }
		    	$location_data[] = $data;
		    }
		}

    }elseif($map_type == "dynamic"){

		if (!empty($settings['posts'])){
		    foreach ($settings['posts']->to_array() as $item) {
		        $map_data = $item->get_map_data();
		        if (!empty($map_data)) {
		            //$map_data['id'] = $item->id;
		            $location_data[] = $map_data;
		        }
		    }			
		}

	}
    $config['locations'] = $location_data;

    if($map_view == "embed" && $location_data){
    	$embed_url = get_map_embed_url($map_service, $location_data[0]);
	    return "<iframe src='" . $embed_url . "' frameborder='0' class='map-embed w-100 h-container' style='border:0;' allowfullscreen='' loading='lazy' referrerpolicy='no-referrer-when-downgrade'></iframe>";
    }

	$buttons = [];
	if (isset($settings['zoom_position']) && $settings['zoom_position'] !== 'topleft') {
		$buttons['zoom_position'] = $settings['zoom_position'];
	}

	if (!empty($settings['buttons'])) {
		$buttons_items = [];
		$buttons['position'] = $settings['buttons_position'];

		foreach ($settings['buttons'] as $item) {
			$attributes = $item['attributes'] ?? null;
			$onclick = isset($item['onclick']) ? str_replace('"', "'", $item['onclick']) : null;

			$buttons_items[] = [
				'title' => $item['title'],
				'class' => $item['class'],
				'attributes' => $attributes,
				'onclick' => $onclick
			];
		}
		$buttons['items'] = $buttons_items;
	}
	$config['buttons'] = $buttons;

	$popup = [
		'active' => false,
		'type' => 'hover',
		'ajax' => false,
		'template' => '',
		'width' => 160
	];

	if (isset($settings['popup_active']) && !empty($settings['popup_active'])) {
		$popup = array_merge($popup, [
			'active' => true,
			'type' => $settings['popup_type'],
			'ajax' => $settings['popup_ajax'],
			'template' => $settings['popup_template'] . 
				($settings['popup_template'] !== 'default' ? '.twig' : ''),
			'width' => $settings['popup_width'] ?? 160
		]);
	}
	$config['popup'] = $popup;

	if ((isset($settings['callback']) && !empty($settings['callback'])) && (isset($settings['popup_active']) && empty($settings['popup_active']))) {
		$config['callback'] = $map_callback;
	}

	switch($map_service){
		case "leaflet":
			$html .= '<div class="leaflet-custom '.(($config['popup']['active'] && !$config['popup']['ajax']) ? 'leaflet-custom-popup' : '').' ratio-- z-0 viewport" data-height="400" data-map="leaflet" data-config="'.$map_config.'"></div>';
		break;
		case "google":
			$html .= '<div class="googlemaps-custom '.(($config['popup']['active'] && !$config['popup']['ajax']) ? 'googlemaps-custom-popup' : '').' ratio-- z-0 viewport" data-height="400" data-map="google" data-config="'.$map_config.'"></div>';
		break;
	}

    if (!empty($map_config)){
        $html .= '<script id="map_data_'.$block_meta["id"].'" data-inline="true">' .
            'var '.$map_config .' = '.json_encode($config).';';
            if (!empty($config['callback'])){
                $html .= 'var '. $config['callback'] .' = '. json_encode($settings['callback']).';';
            }
        $html .= '</script>';
    }

	return $html;
}


function uploadUrlMigration($old_url="", $new_url="", $url=""){
	if(!empty($old_url) && !empty($new_url) && !empty($url)){
		if($old_url != $new_url){
			$url = str_replace($old_url, $new_url, $url);
		}
	}
	return $url;
}

function lightGallerySource($fields) {

	$upload_dir = wp_upload_dir();
    $upload_url = $upload_dir['baseurl'];

    $sources = [];
    $gallery = isset($fields["gallery"]) && $fields["gallery"]?$fields["gallery"]:[];
    $videos  = isset($fields["videos"])  && $fields["videos"]?$fields["videos"]:[];

    if ($gallery && $videos && $fields["add_videos"] && $fields["add_type"] == "mixed") {
        $gallery = array_merge($gallery, $videos);
        shuffle($gallery);  // `array_shuffle` yerine `shuffle` kullanılmalı
    }

    // Video başlangıçta eklenecekse
    if ($videos && $fields["add_videos"] && $fields["add_type"] == "start") {
        foreach ($videos as $item) {
            if ($item["type"] == "embed") {
                $video_thumb = get_video_thumbnail_uri($item["url"]);
                $sources[] = [
                	"type"      => $item["type"],
                    "lg-size"   => "1280-720",
                    "src"       => $item["url"],
                    "poster"    => $video_thumb,
                    "sub-html"  => "",
                    "img-src"   => $video_thumb
                ];
            } elseif ($item["type"] == "file") {
                $sources[] = [
                	"type"      => $item["type"],
                    "lg-size"   => "1280-720",
                    "src"       => $item["file"],//uploadUrlMigration($fields["upload_url"], $upload_url, $item["file"]),
                    "poster"    => $item["image"]["sizes"]["medium_large"],//uploadUrlMigration($fields["upload_url"], $upload_url, $item["image"]["sizes"]["medium_large"]),
                    "img-src"    => $item["image"]["url"],
                    "sub-html"  => "",
                    "video"     => ["source" => [["src" => $item["file"], "type" => "video/mp4"]], "attributes" => ["preload" => false, "controls" => true]]
                    /*"video"     => ["source" => [["src" => uploadUrlMigration($fields["upload_url"], $upload_url, $item["file"]), "type" => "video/mp4"]], "attributes" => ["preload" => false, "controls" => true]]*/
                ];
            }
        }
    }

    // Galeri elemanlarını işleyelim
    foreach ($gallery as $item) {
        if (in_array($item["type"], ["embed", "file"])) {
            if ($item["type"] == "embed") {
                $video_thumb = get_video_thumbnail_uri($item["url"]);
                $sources[] = [
                	"type"      => $item["type"],
                    "lg-size"   => "1280-720",
                    "src"       => $item["url"],
                    "poster"    => $video_thumb,
                    "sub-html"  => "",
                    "img-src"   => $video_thumb
                ];
            } elseif ($item["type"] == "file") {
                $sources[] = [
                	"type"      => $item["type"],
                    "lg-size"   => "1280-720",
                    "src"       => $item["file"],//uploadUrlMigration($fields["upload_url"], $upload_url, $item["file"]),
                    "poster"    => $item["image"]["sizes"]["medium_large"],//uploadUrlMigration($fields["upload_url"], $upload_url, $item["image"]["sizes"]["medium_large"]),
                    "img-src"    => $item["image"]["url"],
                    "sub-html"  => "",
                    "video"     => ["source" => [["src" => $item["file"], "type" => "video/mp4"]], "attributes" => ["preload" => false, "controls" => true]]
                    /*"video"     => ["source" => [["src" => uploadUrlMigration($fields["upload_url"], $upload_url, $item["file"]), "type" => "video/mp4"]], "attributes" => ["preload" => false, "controls" => true]]*/
                ];
            }
        } else {
            // Image türündeki öğeler
            $sources[] = [
            	"type"      => $item["type"],
                "href"      => $item["url"],//uploadUrlMigration($fields["upload_url"], $upload_url, $item["url"]),
                "title"     => $item["alt"],
                "src"       => $item["sizes"]["medium_large"],//uploadUrlMigration($fields["upload_url"], $upload_url, $item["sizes"]["medium_large"]),
                "img-src"   => $item["url"],
                "width"     => $item["width"],
                "height"    => $item["height"]
            ];
        }
    }

    // Videolar sona eklenecekse
    if ($videos && $fields["add_videos"] && $fields["add_type"] == "end") {
        foreach ($videos as $item) {
            if ($item["type"] == "embed") {
                $video_thumb = get_video_thumbnail_uri($item["url"]);
                $sources[] = [
                	"type"      => $item["type"],
                    "lg-size"   => "1280-720",
                    "src"       => $item["url"],
                    "poster"    => $video_thumb,
                    "sub-html"  => "",
                    "img-src"   => $video_thumb
                ];
            } elseif ($item["type"] == "file") {
                $sources[] = [
                	"type"      => $item["type"],
                    "lg-size"   => "1280-720",
                    "src"       => $item["file"]["source"][0],//uploadUrlMigration($fields["upload_url"], $upload_url, $item["file"]["source"][0]),
                    "img-src"   => $item["image"]["url"],
                    "poster"    => $item["image"]["sizes"]["medium_large"],//uploadUrlMigration($fields["upload_url"], $upload_url, $item["image"]["sizes"]["medium_large"]),
                    "sub-html"  => "",
                    "video"     => ["source" => [["src" => $item["file"], "type" => "video/mp4"]], "attributes" => ["preload" => false, "controls" => true]]                    
                    /*"video"     => ["source" => [["src" => uploadUrlMigration($fields["upload_url"], $upload_url, $item["file"]), "type" => "video/mp4"]], "attributes" => ["preload" => false, "controls" => true]]*/
                ];
            }
        }
    }

    if (isset($fields["settings"]) && $fields["settings"]["type"] == "dynamic") {
	    $sources_filtered = array_map(function($item) {
	        // Geçerli öğenin tipine göre işlemler yapılır
	        switch ($item['type']) {
	            case 'embed':
	                // 'embed' türü için sadece belirttiğiniz alanları döndür
	                return [
	                    'src' => $item['src'] ?? null,
	                    'poster' => $item['poster'] ?? null,
	                    'sub-html' => $item['sub-html'] ?? null
	                ];
	            case 'file':
	                // 'file' türü için sadece belirttiğiniz alanları döndür
	                return [
	                    'video'    => $item['video'] ?? null,//uploadUrlMigration($fields["upload_url"], $upload_url, $item['video']) ?? null,
	                    'poster'   => $item['poster'] ?? null,//uploadUrlMigration($fields["upload_url"], $upload_url, $item['poster']) ?? null,
	                    'sub-html' => $item['sub-html'] ?? null
	                ];
	            case 'image':
	                // 'image' türü için sadece belirttiğiniz alanları döndür
	                return [
	                    'src' => $item['img-src'] ?? null,//uploadUrlMigration($fields["upload_url"], $upload_url, $item['src']) ?? null,
	                    'sub-html' => $item['sub-html'] ?? null
	                ];
	            default:
	                return null;
	        }
	    }, $sources);

	    // Geçersiz (null) değerleri filtrele
	    $sources_filtered = array_filter($sources_filtered, function($item) {
	        return $item !== null; // null olan öğeleri at
	    });

	    // Filtrelenmiş veriyi yazdır
	    $sources = $sources_filtered;
	}


    
    return $sources;
}