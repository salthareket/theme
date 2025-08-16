<?php

use SaltHareket\Image;

function file_get_contents3($url=""){
	$options = [
	  'http' => [
	    'method' => "GET",
	    'header' => "Accept-language: en\r\n" . "Cookie: foo=bar\r\n",
	  ],
	];

	// Create a stream context with the custom headers
	$context = stream_context_create($options);

	// Make an HTTP GET request to a URL that returns data and pass in the stream context
	$file = file_get_contents($url, false, $context);

	// Output the response
	return $file;
}

function file_get_contents2($url=""){
	$contents="";
	$file = fopen($url, "r");
	if($file){
	    $file_size = filesize($url);
	    $contents = fread($file, $file_size);
	    fclose($file);
	}
    return $contents;
}

function file_get_contents1($url) {
    if (function_exists('curl_version')) {
        // Curl mevcutsa curl ile veri al
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    } elseif (ini_get('allow_url_fopen')) {
        // Curl kullanılamıyorsa ve allow_url_fopen aktifse file_get_contents ile veri al
        return file_get_contents($url);
    } else {
        // Her iki seçenek de kullanılamıyorsa hata döndür
        trigger_error('custom_file_get_contents: Curl ve allow_url_fopen kullanılamıyor.', E_USER_WARNING);
        return false;
    }
}

/*
function get_embed_video_data($url){
	$dimensions = array(
       "width"  => "100%",
       "height" => "100%"
	);
	$video = parse_video_uri( $url );
	if ( $video['type'] == 'youtube' ){
        $api_url = 'https://noembed.com/embed?url=' . urlencode($url);	
	}
	if ($video['type'] == 'vimeo'){
		$api_url = 'https://vimeo.com/api/oembed.json?url=' . urlencode($url);	
	}
	$response = json_decode(file_get_contents($api_url));
	if( is_wp_error( $response ) ) {
		return $dimensions;
	} else {
		$response = json_decode(json_encode($response), true);
        return array(
        	"width"  => $response["width"],
        	"height" => $response["height"]
        );
	}
}
*/
function set_embed_lazy($code){
    if(empty($code)){
		return $code;
	}
	$code = str_replace("<iframe ", "<iframe class='lazy' ", $code);
	$code = str_replace("src=", "data-src=", $code);
	return $code;
}



function upload_image($url="", $post_id=0, $featured=false) {
	$attachmentId = "";
	if($url != "") {

		$file = array();
		$file['name'] = $url;
		$file['tmp_name'] = download_url($url);
   
		if (is_wp_error($file['tmp_name'])) {
			@unlink($file['tmp_name']);
			var_dump( $file['tmp_name']->get_error_messages( ) );
		} else {
			$attachmentId = media_handle_sideload($file, $post_id);
			if ( is_wp_error($attachmentId) ) {
				@unlink($file['tmp_name']);
				var_dump( $attachmentId->get_error_messages( ) );
			} else {                
				$image = wp_get_attachment_url( $attachmentId );
			}
			if($featured){
		       set_post_thumbnail( $post_id, $attachmentId );
		    }
		}
	}
    return $attachmentId;
}
function featured_image_from_url($image_url = "", $post_id = 0, $featured = false, $name = "", $name_addition = false){
	      if(empty($image_url)){
	      	  return;
	      }
		  $upload_dir = wp_upload_dir(); // Set upload folder
		  $image_data = file_get_contents($image_url); // Get image data

		  //$filename   = basename($image_url); // Create image file name
		  
		  $info = pathinfo($image_url);
		  //dirname   = File Path
		  //basename  = Filename.Extension
		  //extension = Extension
		  //filename  = Filename

		  if(!empty($name)){
		  	$info['filename'] = $name;
		  }
		  $name_addition_text = "";
		  if($name_addition){
             $name_addition_text = '-'.$post_id.'-'.get_random_number(111,999);
		  }

		  $extension = $info['extension'] ?? "jpg";
		  
		  // Check folder permission and define file location
		  if( wp_mkdir_p( $upload_dir['path'] ) ) {
			  $file = $upload_dir['path'] . '/' . $info['filename'].$name_addition_text.'.'.$extension;
		  } else {
			  $file = $upload_dir['basedir'] . '/' . $info['filename'].$name_addition_text.'.'.$extension;
		  }

		  $filename = basename($file); // Create image file name

		  //error_log("file:".$file);

		  // Create the image  file on the server
		  file_put_contents( $file, $image_data );
		  
		  // Check image file type
		  $wp_filetype = wp_check_filetype( $filename, null );
		  
		  // Set attachment data
		  $attachment = array(
			  'post_mime_type' => $wp_filetype['type'],
			  'post_title'     => sanitize_file_name( $filename ),
			  'post_content'   => '',
			  'post_status'    => 'inherit'
		  );
		  
		  // Create the attachment
		  $attach_id = wp_insert_attachment( $attachment, $file, $post_id );
		  
		  // Include image.php
		  require_once(ABSPATH . 'wp-admin/includes/image.php');
		  
		  // Define attachment metadata
		  $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
		  
		  // Assign metadata to attachment
		  wp_update_attachment_metadata( $attach_id, $attach_data );
		  
		  // And finally assign featured image to post
		  if($featured){
		     set_post_thumbnail( $post_id, $attach_id );
		  }
		  return $attach_id;
}

function insert_attachment($file_handler, $post_id, $setthumb=false) {

  // check to make sure its a successful upload
  //if ($_FILES[$file_handler]['error'] !== UPLOAD_ERR_OK) __return_false();

	//print_r($file_handler);

  require_once(ABSPATH . "wp-admin" . '/includes/image.php');
  require_once(ABSPATH . "wp-admin" . '/includes/file.php');
  require_once(ABSPATH . "wp-admin" . '/includes/media.php');

  $attach_id = media_handle_upload( $file_handler, $post_id );

  if ($setthumb) update_post_meta($post_id,'_thumbnail_id',$attach_id);
  return $attach_id;
}

function _get_all_image_sizes() {
    global $_wp_additional_image_sizes;
    $default_image_sizes = get_intermediate_image_sizes();
    foreach ( $default_image_sizes as $size ) {
        $image_sizes[ $size ][ 'width' ] = intval( get_option( "{$size}_size_w" ) );
        $image_sizes[ $size ][ 'height' ] = intval( get_option( "{$size}_size_h" ) );
        $image_sizes[ $size ][ 'crop' ] = get_option( "{$size}_crop" ) ? get_option( "{$size}_crop" ) : false;
    }
    if ( isset( $_wp_additional_image_sizes ) && count( $_wp_additional_image_sizes ) ) {
        $image_sizes = array_merge( $image_sizes, $_wp_additional_image_sizes );
    }
    return $image_sizes;
}

function inline_svg($url="", $class="", $responsive=false){
	$svg = "";
	if(!empty($url)){
		$svg = file_get_contents1($url);
		$svg = remove_html_comments($svg);
		$svg = remove_xml_declaration($svg);
		if(!empty($class)){
			$svg = str_replace("<svg ", "<svg class='".$class."' ", $svg);
		}
		if($responsive){
			
		}
	}
    return $svg;
}

function get_orientation($w=0, $h=0){
	if ( $w == $h ) {
        return 'square';
    }else{
    	if ( $w > $h ) {
	        return 'landscape';
	    } else {
	        return 'portrait';
	    }
	}
}
function add_orientation_class( $attr, $attachment_id ) {
    $metadata = get_post_meta( $attachment_id, '_wp_attachment_metadata', true);
    if ( empty($metadata['width']) || empty($metadata['height'])) {
        return $attr;
    }
    if ( !isset($attr['class'])) {
        $attr['class'] = '';
    }
    $attr['class'] .= ' '.get_orientation($metadata['width'], $metadata['height']);
    return $attr;
}
function add_orientation_class_filter( $attr, $attachment ) {
    return add_orientation_class( $attr, $attachment->ID);
}
add_filter( 'wp_get_attachment_image_attributes', 'add_orientation_class_filter', 10, 2 );

function get_attachment_id_by_url($image_url) {
    global $wpdb;
    $attachment_id = $wpdb->get_var($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE guid = %s AND post_type = 'attachment'",
        $image_url
    ));
    if ($attachment_id) {
        return $attachment_id;
    }
    $filename = pathinfo($image_url, PATHINFO_FILENAME);
    $attachment_id = $wpdb->get_var($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'attachment'",
        $filename
    ));
    return $attachment_id ?: false; // Eğer bulunamazsa false döndür
}


function get_attachment_dimensions_by_url($image_url) {
	$attachment_id = get_attachment_id_by_url($image_url);
    return get_attachment_dimensions($attachment_id);
}
function get_attachment_dimensions($attachment_id) {
    $data = wp_get_attachment_image_src($attachment_id, 'full');
    if(!$data){
    	return array(
    		"file"=>"",
    		"width"=>0,
    		"height"=>0
    	);
    }
    return array(
    	"file" => $data[0],
    	"width" => $data[1],
    	"height" => $data[2]
    );
}



function webp_is_displayable($result, $path) {
    if ($result === false) {
        $displayable_image_types = array( IMAGETYPE_WEBP );
        $info = @getimagesize( $path );
        if (empty($info)) {
            $result = false;
        } elseif (!in_array($info[2], $displayable_image_types)) {
            $result = false;
        } else {
            $result = true;
        }
    }
    return $result;
}
add_filter('file_is_displayable_image', 'webp_is_displayable', 10, 2);


function enable_mime_types( $upload_mimes ) {
	if(isset($GLOBALS["upload_mimes"])){
		foreach($GLOBALS["upload_mimes"] as $key => $mime){
			$upload_mimes[$key] = $mime;
		}
	}
	return $upload_mimes;
}
add_filter('mime_types', 'enable_mime_types', 10, 1 );
add_filter( 'upload_mimes', 'enable_mime_types', 10, 1 );

add_filter( 'wp_check_filetype_and_ext', function($data, $file, $filename, $mimes) {
  $filetype = wp_check_filetype( $filename, $mimes );
  return [
      'ext'             => $filetype['ext'],
      'type'            => $filetype['type'],
      'proper_filename' => $data['proper_filename']
  ];
}, 10, 4 );


function upload_file($file, $post_id) {
    $attachment_id = 0;

    if (isset($file) && !empty($post_id)) {
        $upload_dir = wp_upload_dir();
        $upload_path = $upload_dir['path'];

        // Dosyanın benzersiz bir isimle kaydedilmesi
        $file_name = $file['name'];
        $new_file_name = wp_unique_filename($upload_path, $file_name);

        // Yüklenen dosyanın yeni adı ile kaydedilmesi
        $file_path = $upload_path . '/' . $new_file_name;
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            $attachment = array(
                'post_mime_type' => mime_content_type($file_path),
                'post_title'     => sanitize_file_name($new_file_name), // Yeni dosya adı
                'post_content'   => '',
                'post_status'    => 'inherit',
                'post_parent'    => $post_id // Gönderiye bağlama
            );

            $attachment_id = wp_insert_attachment($attachment, $file_path);
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attachment_data = wp_generate_attachment_metadata($attachment_id, $file_path);
            wp_update_attachment_metadata($attachment_id, $attachment_data);
        }
    }
    return $attachment_id;
}




// Resize uploaded book cover size to 780x1200px
function wp_handle_upload_resize($image_data){
	  $post_types = array_keys($GLOBALS["upload_resize"]);
	  $post_type = get_post_type($_REQUEST['post_id']); // post_id parametresini kullanarak gönderi türünü al
	  if (!in_array($post_type, $post_types)) {
	    return $image_data; // Dosya nesnesini geri döndür
	  }
	  $post_type_data = $GLOBALS["upload_resize"][$post_type];

	  $resizing_enabled = true;
	  $force_jpeg_recompression = true;
	  $compression_level = $post_type_data["compression"];
	  $max_width  = $post_type_data["width"];
	  $max_height = $post_type_data["height"];
	  $crop = $post_type_data["crop"];
	  $convert_png_to_jpg = true;
	  $convert_gif_to_jpg = true;
	  $convert_bmp_to_jpg = true;

	  if($convert_png_to_jpg && $image_data['type'] == 'image/png' ) {
	    $image_data = wp_handle_upload_convert_image( $image_data, $compression_level );
	  }
	  if($image_data['type'] == 'image/gif' && wp_handle_upload_is_gif($image_data['file'])) {
	    return $image_data;
	  }

	  //---------- In with the old v1.6.2, new v1.7 (WP_Image_Editor) ------------

	  if($resizing_enabled || $force_jpeg_recompression) {

	    $fatal_error_reported = false;
	    $valid_types = array('image/gif','image/png','image/jpeg','image/jpg');

	    if(empty($image_data['file']) || empty($image_data['type'])) { 
	        $fatal_error_reported = true;
	    }else if(!in_array($image_data['type'], $valid_types)) {
	        $fatal_error_reported = true;
	    }

	    $image_editor = wp_get_image_editor($image_data['file']);
	    $image_type = $image_data['type'];


	    if($fatal_error_reported || is_wp_error($image_editor)) {
	    }else {

	      $to_save = false;
	      $resized = false;

	      // Perform resizing if required
	      if($resizing_enabled) {
	        $sizes = $image_editor->get_size();
	        if((isset($sizes['width']) && $sizes['width'] > $max_width) || (isset($sizes['height']) && $sizes['height'] > $max_height)) {
	          $image_editor->resize($max_width, $max_height, $crop);
	          $resized = true;
	          $to_save = true;
	          $sizes = $image_editor->get_size();
	        }
	      }

	      // Regardless of resizing, image must be saved if recompressing
	      if($force_jpeg_recompression && ($image_type=='image/jpg' || $image_type=='image/jpeg')) {
	        $to_save = true;
	      }

	      // Only save image if it has been resized or need recompressing
	      if($to_save) {
	        $image_editor->set_quality($compression_level);
	        $saved_image = $image_editor->save($image_data['file']);
	      }
	    }
	  }
	  return $image_data;
}
function wp_handle_upload_convert_image( $params, $compression_level ){
  $transparent = 0;
  $image = $params['file'];

  $contents = file_get_contents( $image );
  if ( ord ( file_get_contents( $image, false, null, 25, 1 ) ) & 4 ) $transparent = 1;
  if ( stripos( $contents, 'PLTE' ) !== false && stripos( $contents, 'tRNS' ) !== false ) $transparent = 1;

  $transparent_pixel = $img = $bg = false;
  if($transparent) {
    $img = imagecreatefrompng($params['file']);
    $w = imagesx($img); // Get the width of the image
    $h = imagesy($img); // Get the height of the image
    //run through pixels until transparent pixel is found:
    for($i = 0; $i<$w; $i++) {
      for($j = 0; $j < $h; $j++) {
        $rgba = imagecolorat($img, $i, $j);
        if(($rgba & 0x7F000000) >> 24) {
          $transparent_pixel = true;
          break;
        }
      }
    }
  }

  if( !$transparent || !$transparent_pixel) {
    if(!$img) $img = imagecreatefrompng($params['file']);
    $bg = imagecreatetruecolor(imagesx($img), imagesy($img));
    imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
    imagealphablending($bg, 1);
    imagecopy($bg, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
    $newPath = preg_replace("/\.png$/", ".jpg", $params['file']);
    $newUrl = preg_replace("/\.png$/", ".jpg", $params['url']);
    for($i = 1; file_exists($newPath); $i++) {
      $newPath = preg_replace("/\.png$/", "-".$i.".jpg", $params['file']);
    }
    if ( imagejpeg( $bg, $newPath, $compression_level ) ){
      unlink($params['file']);
      $params['file'] = $newPath;
      $params['url'] = $newUrl;
      $params['type'] = 'image/jpeg';
    }
  }

  return $params;
}
function wp_handle_upload_is_gif($filename) {
  if(!($fh = @fopen($filename, 'rb')))
    return false;
  $count = 0;
  //an animated gif contains multiple "frames", with each frame having a
  //header made up of:
  // * a static 4-byte sequence (\x00\x21\xF9\x04)
  // * 4 variable bytes
  // * a static 2-byte sequence (\x00\x2C) (some variants may use \x00\x21 ?)

  // We read through the file til we reach the end of the file, or we've found
  // at least 2 frame headers
  $chunk = false;
  while(!feof($fh) && $count < 2) {
    //add the last 20 characters from the previous string, to make sure the searched pattern is not split.
    $chunk = ($chunk ? substr($chunk, -20) : "") . fread($fh, 1024 * 100); //read 100kb at a time
    $count += preg_match_all('#\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)#s', $chunk, $matches);
  }

  fclose($fh);
  return $count > 1;
}
if(isset($GLOBALS["upload_resize"])){
	if(is_array($GLOBALS["upload_resize"]) && count($GLOBALS["upload_resize"]) > 0){
          add_filter('wp_handle_upload', 'wp_handle_upload_resize');	
	}
}




function remove_custom_image_sizes() {
    if (!empty($GLOBALS["upload_sizes_remove"]) && is_array($GLOBALS["upload_sizes_remove"])) {
        foreach ($GLOBALS["upload_sizes_remove"] as $size) {
            remove_image_size($size);            
        }
    }
}
add_action('init', 'remove_custom_image_sizes');

function upload_sizes_add() {
    if (!empty($GLOBALS["upload_sizes_add"]) && is_array($GLOBALS["upload_sizes_add"])) {
        foreach ($GLOBALS["upload_sizes_add"] as $key => $size) {
            add_image_size($key, $size);            
        }
    }  
}
add_action('after_setup_theme', 'upload_sizes_add');

function upload_sizes_names($sizes) {
    if (!empty($GLOBALS["upload_sizes_add"]) && is_array($GLOBALS["upload_sizes_add"])) {
        $names = array();
        foreach ($GLOBALS["upload_sizes_add"] as $key => $size) {
            $names[$key] = $key;
        }
        return array_merge($sizes, $names);
    }
    return $sizes; // Eğer hiçbir şey eklenmezse mevcut boyutları döndür
}
add_filter('image_size_names_choose', 'upload_sizes_names'); 


function get_image_set($args=array()){
	if(is_admin()){
		$args["preview"] = true;
	}
	if(defined("SITE_ASSETS") && is_array(SITE_ASSETS)){
		if(isset(SITE_ASSETS["lcp"]["desktop"]) && SITE_ASSETS["lcp"]["desktop"] && isset(SITE_ASSETS["lcp"]["mobile"]) && SITE_ASSETS["lcp"]["mobile"]){
			
		}else{
			$args["preview"] = true;
		}
	}
	$image = new SaltHareket\Image($args);
	return $image->init();
    /*
	$defaults = array(
		'src' => '',
		'id' => null,
        'class' => '',
        'lazy' => true,
        'lazy_native' => false,
        'width' => null,
        'height' => null,
        'alt' => '',
        'post' => null,
        'type' => "img", // img, picture
        'resize' => false,
        'lcp' => false,
        'placeholder' => false,
        'placeholder_class' => '',
        'preview' => false,
        'attrs' => []
    );
    */
}
function get_video($video_args=array()){

	$args  = $video_args["src"];
	$type  = $args["video_type"] ?? "file";
	$class = isset($video_args["class"])?$video_args["class"]:"";
	$init  = $video_args["init"] ?? false;
	$lazy  = $video_args["lazy"] ?? true;
	$title = $args["video_title"] ?? "";

	$embed = '<div class="player plyr__video-embed {{class}}" {{config}} {{poster}} {{attrs}}><iframe
	    class="video {{class_lazy}}"
	    title="{{title}}"
	    src="{{src}}"
	    allowfullscreen
	    allowtransparency
	    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
	  ></iframe>{{poster_lazy}}</div>';
	$audio = '<audio class="player video {{class}}" {{controls}} {{autoplay}} {{muted}} {{config}}>
	  <source src="{{src}}" type="audio/mp3" />
	</audio>';
	$file = '<video class="player video {{class}} w-100" playsinline {{controls}} {{autoplay}} {{poster}} {{muted}} {{config}} preload="{{preload}}" {{lazy}} {{attrs}}>
	  <source src="{{src}}" type="video/mp4" />
	  {{poster_lazy}}
	  {{vtt}}
	</video>';
	$file_responsive = '<video class="player video {{class}} w-100" playsinline {{controls}} {{autoplay}} {{poster}} {{muted}} {{config}} preload="{{preload}}" {{lazy}} {{attrs}}>
	  {{poster_lazy}}
	  {{src}}
	  {{vtt}}
	</video>';

	/*<div class="swiper-bg bg-cover swiper-video-url position-absolute-fill loading-hide loading-light" 
	data-video-url="{{data.embed_url}}" 
	data-video-code="{{data.id}}" 
	data-video-type="{{data.type}}" 
	data-video-autoplay="{{slide.video.video_settings.autoplay|boolstr}}" 
	data-video-rel="0" 
	data-video-responsive="false" 
	data-video-loop="{{slide.video.video_settings.loop|boolstr}}" 
	data-video-control="{{slide.video.video_settings.controls|boolstr}}" 
	data-video-muted="{{slide.video.video_settings.muted|boolstr}}" 
	data-video-bg="{{slide.video.video_settings.videoBg|boolstr}}"></div>*/

    /*
	data-video-file="{{slide.video.video_file}}" 
	data-video-poster="{{slide.video.video_settings.video_image.src}}" 
	data-video-bg="{{slide.video.video_settings.videoBg|boolstr}}" 
	data-video-controls="{{slide.video.video_settings.controls|boolstr}}" 
	data-video-react="{{slide.video.video_settings.videoReact|boolstr}}" 
	data-video-muted="{{slide.video.video_settings.muted|boolstr}}" 
	data-video-loop="{{slide.video.video_settings.loop|boolstr}}"  
	data-video-autoplay="{{slide.video.video_settings.autoplay|boolstr}}
	*/

	$code = ${$type};
	if($type != "audio" && $type != "embed" && isset($args["video_file"]) && is_array($args["video_file"])){
		$code = $file_responsive;
	}
	$config = array();
	$settings = isset($args["video_settings"])?$args["video_settings"]:[];
	if($lazy){
		//$code = str_replace("{{lazy}}", "loading='lazy'", $code);
		$code = str_replace("{{preload}}", "none'", $code);
		if($type == "embed"){
	    	$class .= " lazy-container "; 
	    }else{
	    	$class .= "lazy";
	    }
	}
	$code = str_replace("{{lazy}}", "", $code);
	$code = str_replace("{{preload}}", "metadata", $code);
	if(isset($video_args["attrs"])){
		$code = str_replace("{{attrs}}", array2Attrs($video_args["attrs"]), $code);
	}
	$code = str_replace("{{attrs}}", "", $code);


	switch($type){

		case "audio" :
		    if(is_array($args["files"])){
		   	    $code = str_replace("{{src}}", $args["files"][0]["file"], $code);
		    }
		break;

		case "embed" :
		    if(!isset($args["video_url"]) || (isset($args["video_url"]) && empty($args["video_url"]))){
		    	return;
		    }
		    $embed = new OembedVideo($args["video_url"], "1600x900");
		    $data = $embed->get($settings);
		    $poster = isset($settings["custom_video_image"]) && $settings["custom_video_image"] && !empty($settings["video_image"])?$settings["video_image"]:$data["src"];
	        if($lazy){
	        	$code = str_replace('{{class_lazy}}', 'lazy', $code);
	        	$code = str_replace('src="{{src}}"', 'data-src="{{src}}"', $code);
	        	if(image_is_lcp($poster)){
                    $code = str_replace("{{poster_lazy}}", '<div class="plyr__poster plyr__poster-init" style="background-image: url(&quot;'.$poster.'&quot;);"></div>', $code);
	        	}else{
	        		$code = str_replace("{{poster_lazy}}", '<div class="plyr__poster plyr__poster-init lazy" data-bg="'.$poster.'"></div>', $code);
	        	}
	        }else{
	        	$code = str_replace('{{class_lazy}}', '', $code);
	        	$code = str_replace("{{poster_lazy}}", "", $code);
	        }
	        $code = str_replace("{{src}}", $data["embed_url"], $code);
			if(isset($settings["custom_video_image"]) && $settings["custom_video_image"] && !empty($settings["video_image"])){
				$code = str_replace("{{poster}}", "data-poster=".$poster, $code);
	        }else{
	        	$code = str_replace("{{poster}}", "", $code);
	        }
	        $title = empty($title)?$data["title"]:$title;
	        $code = str_replace("{{title}}", $title ?? '', $code);
		break;

		case "file" :
		    if(isset($args["video_file"])){
			    if(is_array($args["video_file"])){
			    	$source_count = 0;
			    	$sources = "";
			    	foreach($args["video_file"] as $key => $source){
			    		$attachment_id = get_attachment_id_by_url($source);
			    		if($source){
			    			$meta = get_post_meta($attachment_id, '_wp_attachment_metadata', true);
			    			if (!is_array($meta)) continue; // 💥 EKLENDİ
			    			$sources .= '<source '.($lazy?"":"").'src="'.$source.'" type="'.$meta["mime_type"].'" size="'.$meta["height"].'" />';
			    			$source_count++;
			    		}
			    	}
			    	if($sources){
						$code = str_replace("{{src}}", $sources, $code);

						if(empty($settings["controls"]) && $source_count > 1){
							$settings["controls"] = 1;
							$settings["controls_options"] = ["settings"];
							$settings["controls_options_settings"] = ["quality"];
							$config["controls"] = ["settings"];
							$config["settings"] = ["quality"];
						}else{
							if(!in_array("settings", $settings["controls_options"])){
								$settings["controls_options"][] = "settings";
								$config["controls"][] = "settings";
							}
							if(!in_array("quality", $settings["controls_options_settings"])){
								$settings["controls_options_settings"][] = "quality";
								$config["settings"][] = "quality";
							}
						}

					}else{
			    		return;
			    	}	
			    }else{
					if($lazy){
						$code = str_replace('src="{{src}}"', 'data-src="'.$args["video_file"].'"', $code);
					}else{
						$code = str_replace("{{src}}", $args["video_file"], $code);
					}		    	
			    }		    	
		    }

			if(isset($settings["video_image"]) && !empty($settings["video_image"])){
				$poster = $settings["video_image"];
				$code = str_replace("{{poster}}", "poster=".$poster." data-poster=".$poster, $code);
				if($lazy){
					/*add_action('wp_head', function() use ($poster) {
				        Salthareket\Image::add_preload_image($poster);
				    });*/
				    $code = str_replace("{{poster_lazy}}", '<div class="plyr__poster opacity-100 lazy" data-bg="'.$poster.'"></div>', $code);		
				}
	        }else{
	        	$code = str_replace("{{poster}}", "", $code);
	        	$code = str_replace("{{poster_lazy}}", "", $code);
	        }
	        $vtt = "";
	        if(isset($settings["vtt"]) && !empty($settings["vtt"])){
	        	$files = $settings["vtt"];
	        	if($files){
	        		foreach($files as $file){
	        			if($file){
	        				$lang = strtolower(substr($file["language_list"]["value"], 0, 2));
	        				$vtt .= '<track kind="captions" label="'.$file["language_list"]["label"].'" src="'.$file["file"].'" srclang="'.$lang.'" '.($lang==$GLOBALS["language"]?"default":"").' />';
	        			}
	        		}
	        	}
	        }
	        $code = str_replace("{{vtt}}", $vtt, $code);
		break;

	}
    
    //$class = "";
    if($init){
    	$class .= " init-me "; 
    }
    
	if($type != "audio" && isset($settings["videoBg"]) && $settings["videoBg"]){
		$config["fullscreen"] = [ "enabled" => false, "fallback" => false, "iosNative" => false, "container" => null ];
		$settings["videoReact"] = false;
		$config["clickToPlay"] = false;
		//$settings["controls"] = false;
		$code = str_replace("{{class}}", "video-bg ".$class, $code);    
	}
	$code = str_replace("{{class}}", $class, $code);

	//$config["youtube"] = [ "noCookie" => true, "rel" => 0, "showinfo" => 0, "iv_load_policy" => 3, "modestbranding" => 1 ];

	if(isset($args["video_settings"]["videoReact"]) && $args["video_settings"]["videoReact"]){
		$config["clickToPlay"] = true;
	}

	if(isset($settings["controls"]) && $settings["controls"]){
		$config["controls"] = $settings["controls_options"];
		if(isset($settings["controls_options_settings"]) && $settings["controls_options_settings"]){
			$config["settings"] = $settings["controls_options_settings"];			
		}
		$code = str_replace("{{controls}}", "controls", $code);
        $config["hideControls"] = isset($settings["controls_hide"])?$settings["controls_hide"]:false;
	}else{
		$config["controls"] = false;
		$config["settings"] = false;
		$code = str_replace("{{controls}}", "", $code);
	}
	if(isset($settings["autoplay"]) && $settings["autoplay"]){
		$code = str_replace("{{autoplay}}", "autoplay", $code);
		$config["autoplay"] = $settings["autoplay"];
	}else{
        $code = str_replace("{{autoplay}}", "", $code);
	}
	if(isset($settings["muted"]) && $settings["muted"]){
		$code = str_replace("{{muted}}", "muted", $code);
		//$config["volume"] = 0;
		$config["muted"] = true;
	}else{
        $code = str_replace("{{muted}}", "", $code);
	}
    //$config["clickToPlay"] = $settings["videoReact"];
    if(!isset($settings["loop"])){
    	$settings["loop"] = false;
    }
	$config["loop"] = [ "active" => $settings["loop"] ];

	if($type != "audio" && isset($settings["ratio"]) && $settings["ratio"]){
		$config["ratio"] = str_replace("235", "2.35", $settings["ratio"]);
		$config["ratio"] = str_replace("185", "1.85", $config["ratio"]);
    	$config["ratio"] = str_replace("x", ":", $config["ratio"]);
	}

    if($type == "file"){
    	if(isset($settings["show_thumbnails"]) && $settings["show_thumbnails"]){
    		if(!empty($settings["vtt_thumbnails"])){
				$config["previewThumbnails"] = ["enabled" => true, "src" => $settings["vtt_thumbnails"]];
				if(empty($settings["controls"])){
					$config["controls"] = ["progress"];
				}else{
					if(!in_array("progress", $settings["controls_options"])){
						$config["controls"][] = "progress";
					}
				}
    		}
    	}
    }

	$config["debug"] = false;
	$config = json_encode($config);
	$config = str_replace("'","", $config);
	//$config = str_replace('"','', $config);
	$data_config = "data-plyr-config='$config'";
	$code = str_replace("{{config}}", $data_config, $code);
    return $code;
}

function getAspectRatio(int $width, int $height){
    // search for greatest common divisor
    $greatestCommonDivisor = static function($width, $height) use (&$greatestCommonDivisor) {
        return ($width % $height) ? $greatestCommonDivisor($height, $width % $height) : $height;
    };
    $divisor = $greatestCommonDivisor($width, $height);
    return $width / $divisor . '/' . $height / $divisor;
}

/*function get_google_optimized_avif_quality() {
    $base_quality = 50; // Varsayılan kalite
    $min_quality = 10;  // En düşük kalite
    $max_resolution_threshold = 1920 * 1080; // Full HD'den büyük görseller sıkıştırılmalı
    $filesize_threshold = 300000; // 300 KB üstünde kaliteyi düşür

    // Son yüklenen dosyayı al
    global $wpdb;
    $attachment = $wpdb->get_row("SELECT ID, guid FROM {$wpdb->posts} WHERE post_type = 'attachment' ORDER BY post_date DESC LIMIT 1");

    if (!$attachment) {
        return $base_quality;
    }

    $file_path = get_attached_file($attachment->ID);
    if (!file_exists($file_path)) {
        return $base_quality;
    }

    $filesize = filesize($file_path);
    $image_info = getimagesize($file_path);

    if (!$image_info) {
        return $base_quality;
    }

    // Görselin genişlik ve yüksekliğini al
    $width = $image_info[0];
    $height = $image_info[1];

    // Görselin piksel yoğunluğunu hesapla
    $resolution = $width * $height;

    // Ortalama renk çeşitliliğini belirlemek için basit bir hesap (renk derinliği)
    $bits_per_pixel = isset($image_info['bits']) ? $image_info['bits'] : 8;
    $color_variation = ($bits_per_pixel / 8) * 100; // Renk çeşitliliğine göre değer üret

    // Eğer çözünürlük 1920x1080'den büyükse kaliteyi düşür
    if ($resolution > $max_resolution_threshold) {
        $resolution_factor = ($resolution / $max_resolution_threshold) * 15; // Büyük çözünürlüklerde kaliteyi sert düşür
        $base_quality -= $resolution_factor;
    }

    // Dosya boyutu çok büyükse kaliteyi daha da düşür
    if ($filesize > $filesize_threshold) {
        $size_factor = ($filesize / $filesize_threshold) * 10; // Büyük dosyalarda ekstra sıkıştırma
        $base_quality -= $size_factor;
    }

    // Eğer görselin renk çeşitliliği düşükse (tek renk, az detay), kaliteyi iyice azalt
    if ($color_variation < 80) { 
        $base_quality -= 15; // Az detaylı resimler için ekstra sıkıştırma
    }

    // Kaliteyi min ve max değerler arasında sınırla
    return max($min_quality, min(80, $base_quality));
}*/

function get_google_optimized_avif_quality($input = null) {
    $base_quality = 50;
    $min_quality = 10;
    $max_quality = 80;
    $max_resolution_threshold = 1920 * 1080;
    $filesize_threshold = 300000;

    // Belirli bir görsel verildiyse (ID veya path)
    if ($input && is_numeric($input)) {
        $file_path = get_attached_file($input);
    } elseif ($input && is_string($input)) {
        $file_path = $input;
    } else {
        // Parametre verilmemişse en son görseli al
        global $wpdb;
        $attachment = $wpdb->get_row("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' ORDER BY post_date DESC LIMIT 1");
        if (!$attachment) return $base_quality;
        $file_path = get_attached_file($attachment->ID);
    }

    if (!file_exists($file_path)) return $base_quality;

    $filesize = filesize($file_path);
    $image_info = getimagesize($file_path);
    if (!$image_info) return $base_quality;

    $width = $image_info[0];
    $height = $image_info[1];
    $resolution = $width * $height;
    $bits_per_pixel = isset($image_info['bits']) ? $image_info['bits'] : 8;
    $color_variation = ($bits_per_pixel / 8) * 100;

    if ($resolution > $max_resolution_threshold) {
        $resolution_factor = ($resolution / $max_resolution_threshold) * 15;
        $base_quality -= $resolution_factor;
    }

    if ($filesize > $filesize_threshold) {
        $size_factor = ($filesize / $filesize_threshold) * 10;
        $base_quality -= $size_factor;
    }

    if ($color_variation < 80) {
        $base_quality -= 15;
    }

    return max($min_quality, min($max_quality, $base_quality));
}


function get_embed_video_title($video_url) {
    if (empty($video_url)) {
        return false;
    }

    // Video servislerini belirle
    if (strpos($video_url, 'youtube.com') !== false || strpos($video_url, 'youtu.be') !== false) {
        $oembed_url = "https://www.youtube.com/oembed?url=" . urlencode($video_url) . "&format=json";
    } elseif (strpos($video_url, 'vimeo.com') !== false) {
        $oembed_url = "https://vimeo.com/api/oembed.json?url=" . urlencode($video_url);
    } elseif (strpos($video_url, 'dailymotion.com') !== false) {
        $oembed_url = "https://www.dailymotion.com/services/oembed?url=" . urlencode($video_url);
    } else {
        return false; // Desteklenmeyen platform
    }

    // API isteği gönder
    $response = wp_remote_get($oembed_url, ['timeout' => 5]);

    if (is_wp_error($response)) {
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    return isset($data['title']) ? $data['title'] : false;
}

function image_is_lcp($image){
	$lcp = new \Lcp();
	return $lcp->is_lcp($image);
}

