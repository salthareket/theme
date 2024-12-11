<?php
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

function set_embed_lazy($code){
    if(empty($code)){
		return $code;
	}
	$code = str_replace("<iframe ", "<iframe class='lazy' ", $code);
	$code = str_replace("src=", "data-src=", $code);
	return $code;
}

/* Pull apart OEmbed video link to get thumbnails out*/
function get_video_thumbnail_uri( $video_uri = "", $image_size=0 ) {
    if(empty($video_uri)){
    	return;
    }
	$thumbnail_uri = '';
	$video = parse_video_uri( $video_uri );		
	
	// get youtube thumbnail
	if ( $video['type'] == 'youtube' )
		$thumbnail_uri = 'http://img.youtube.com/vi/' . $video['id'] . '/maxresdefault.jpg';
	
	// get vimeo thumbnail
	if( $video['type'] == 'vimeo' )
		$thumbnail_uri = get_vimeo_thumbnail_uri( $video['id'], $image_size );

	// get default/placeholder thumbnail
	if( empty( $thumbnail_uri ) || is_wp_error( $thumbnail_uri ) )
		$thumbnail_uri = ''; 
	
	//return thumbnail uri
	return $thumbnail_uri;
}


/* Parse the video uri/url to determine the video type/source and the video id */
function parse_video_uri( $url = "") {
	if(empty($url)){
		return false;
	}

	// Parse the url 
	$parse = parse_url( $url );

	// Set blank variables
	$video_type = '';
	$video_id = '';
	
	// Url is http://youtu.be/xxxx
	if ( $parse['host'] == 'youtu.be' ) {
		$video_type = 'youtube';
		$video_id = ltrim( $parse['path'],'/' );	
	}
	
	// Url is http://www.youtube.com/watch?v=xxxx 
	// or http://www.youtube.com/watch?feature=player_embedded&v=xxx
	// or http://www.youtube.com/embed/xxxx
	if ( ( $parse['host'] == 'youtube.com' ) || ( $parse['host'] == 'www.youtube.com' ) ) {
	
		$video_type = 'youtube';

		parse_str( $parse['query'], $output);

		if ( !empty( $output["feature"] ) ){
			$video_id = explode( 'v=', $parse['query'] ) ;
			$video_id = end( $video_id );
		}
			
		if ( strpos( $parse['path'], 'embed' ) == 1 ){
			$video_id = explode( '/', $parse['path'] );
			$video_id = end( $video_id );
		}

		if(empty($video_id)){
		   $video_id = $output["v"];
		}
	}

	// Url is http://www.vimeo.com
	if ( ( $parse['host'] == 'vimeo.com' ) || ( $parse['host'] == 'www.vimeo.com' ) || ( $parse['host'] == 'player.vimeo.com' )  ) {
		$video_type = 'vimeo';
		$video_id = ltrim( $parse['path'],'/' );					
	}
	//$host_names = explode(".", $parse['host'] );
	//$rebuild = ( ! empty( $host_names[1] ) ? $host_names[1] : '') . '.' . ( ! empty($host_names[2] ) ? $host_names[2] : '');
	
	if ( !empty( $video_type ) ) {
		$video_array = array(
			'type' => $video_type,
			'id' => $video_id
		);
		return $video_array;
	} else {
		return false;
	}
}


/* Takes a Vimeo video/clip ID and calls the Vimeo API v2 to get the large thumbnail URL.*/
function get_vimeo_thumbnail_uri( $clip_id="", $image_size=0 ) {
	//$vimeo_api_uri = 'http://vimeo.com/api/v2/' . $clip_id . '.php';
	$vimeo_api_uri = 'https://vimeo.com/api/oembed.json?url=https%3A//vimeo.com/' . $clip_id;
	$vimeo_response = @file_get_contents($vimeo_api_uri);//wp_remote_get( $vimeo_api_uri );


	/*$vimeo_response = @file_get_contents($vimeo_api_uri); // @ işareti hata bastırır
    if ($vimeo_response === false) {
        error_log('Vimeo API isteği başarısız oldu: ' . $vimeo_api_uri);
        return 'Bir hata oluştu, Vimeo thumbnail alınamadı.';
    }*/


	if ($vimeo_response === false) {//if( is_wp_error( $vimeo_response ) ) {
		return $vimeo_response;
	} else {
		$vimeo_response = json_decode($vimeo_response);//wp_remote_get( $vimeo_api_uri );
		$url = $vimeo_response->thumbnail_url;
		if(!empty($image_size)){
			$url = str_split($url, stripos($url,'_'));
			return $url[0].'_'.$image_size.'.jpg';
		}else{
		   return $url;
		}
	}
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
function featured_image_from_url($image_url="", $post_id=0, $featured=false, $name="", $name_addition=true){
	      if(empty($image_url)){
	      	  return;
	      }
		  $upload_dir = wp_upload_dir(); // Set upload folder
		  $image_data = file_get_contents($image_url); // Get image data

		  $filename   = basename($image_url); // Create image file name
		  
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
		  
		  // Check folder permission and define file location
		  if( wp_mkdir_p( $upload_dir['path'] ) ) {
			  $file = $upload_dir['path'] . '/' . $info['filename'].$name_addition_text.'.'.$info['extension'];
		  } else {
			  $file = $upload_dir['basedir'] . '/' . $info['filename'].$name_addition_text.'.'.$info['extension'];
		  }

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
    $prefix = $wpdb->prefix;
    $attachment = $wpdb->get_col("SELECT ID FROM " . $prefix . "posts" . " WHERE guid='" . $image_url . "';");
    if($attachment){
    	return $attachment[0];
    }
    return false;
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
	$image = new \Image($args);
	return $image->init();/**/
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
    $args = array_merge($defaults, $args);

    $sizes = array_reverse(array_keys($GLOBALS["breakpoints"]));//array("xxxl", "xxl","xl","lg","md","sm","xs");

    if(empty($args["src"])){
    	if($args["placeholder"]){
			return '<div class="img-placeholder '.$args["placeholder_class"].' img-not-found"></div>';
		}else{
			return;
		}
    }

    if($args["lcp"]){
    	$args["lazy"] = false;
    	$args["lazy_native"] = false;
    }

    $attrs = array();
	$prefix = $args["lazy"]?"data-":"";

	if($args["lcp"]){
		$attrs["fetchpriority"] = "high";
	}

	$has_breakpoints = false;
	$is_single = false;

	if(is_array($args["src"]) && in_array(array_keys($args["src"])[0], $sizes)){
		$values = remove_empty_items(array_values($args["src"]));
	
		if(count($values) == 1){
			$args["src"] = $values;
			$has_breakpoints = true;
		    $is_single = true;
		}
	}

	if(is_array($args["src"])){ // mobile ve desktop için ayrı görsel içeriyorsa

		// start new
		$lastValue = null;
		$empties = [];
		$counter = 0;
		foreach ($args["src"] as $key => $value) {

			if ($value !== "") {
		        $lastValue = $value;
		        if($empties){
		        	foreach ($empties as $empty) {
		        		$args["src"][$empty] = $value;
		        	}
		        	$empties = [];
		        }
		    } else {
		    	if ($lastValue) {
		    		$args["src"][$key] = $lastValue;
		    		$empties = [];
		    	}else{
			    	$empties[] = $key;
			        if($counter == count($args["src"])-1){
			        	if($empties){
				        	foreach ($empties as $empty) {
				        		$args["src"][$empty] = $lastValue;
				        	}
				        }
			        }		    		
		    	}
			}
			$counter++;
		}

		$args["src"] = remove_empty_items($args["src"]);

		if(isset($args["src"]->sizes)){
			$args["src"] = Timber::image($args["src"]);
		}

		//$has_breakpoints = false;
		//$is_single = false;
		if(isset(array_keys($args["src"])[0]) && in_array(array_keys($args["src"])[0], $sizes)){

			foreach($args["src"] as $key => $item){
				if(!empty($item)){
					if(!post_is_exist($item)){
						$args["src"][$key] = "";
					}					
				}
			}
			$args["src"] = remove_empty_items($args["src"]);
			if(!$args["src"]){
				return;
			}
			$has_breakpoints = true;

		}

		if(count($args["src"]) == 1 || isset($args["src"]["ID"])){
			$is_single = true;
		}

		if(count($args["src"]) > 1 || $has_breakpoints){

			$args_responsive = array();
			foreach($args["src"] as $key => $item){
				$args_temp = $args;
				$args_temp["src"] = $item;
				$args_temp = get_image_set_post($args_temp);
				if($args_temp["post"]){
					$args_responsive[] = array(
						"post"   => $args_temp["post"],
		                "meta"   => wp_get_attachment_metadata($args_temp["id"]),
						"srcset" => $args_temp["post"]->srcset(),
						"prefix" => $prefix,
						"breakpoint" => $key
					);
				}
			}
		
			$args["src"]    = $args_responsive[0]["post"];
			$args["post"]    = $args_responsive[0]["post"];
			$args = get_image_set_post($args);
			$args["srcset"] = get_image_set_multiple($args_responsive, $has_breakpoints);
			$args["type"]   = $is_single?"img":"picture";

		}elseif (count($args["src"]) == 1 ){

			$args["src"] = $args["src"][0]; // sadece tekini içeriyorsa
			$args = get_image_set_post($args);


		}elseif (count($args["src"]) == 0){
			
			if($args["placeholder"]){
				return '<div class="img-placeholder '.$args["placeholder_class"].' img-not-found"></div>';
			}else{
				return;
			}

		}

	}elseif (is_string($args["src"]) || is_numeric($args["src"]) || is_object($args["src"]) ){

		$args = get_image_set_post($args);

	}else{

		if($args["placeholder"]){
			return '<div class="img-placeholder '.$args["placeholder_class"].' img-not-found"></div>';
		}else{
			return;
		}

	}

	if(!$args["post"]){
		if($args["placeholder"]){
			return '<div class="img-placeholder '.$args["placeholder_class"].' img-not-found"></div>';
		}else{
			return;
		}
	}

	$attrs["width"] = $args["width"];
	$attrs["height"] = $args["height"];
	$attrs["alt"] = $args["alt"];

	$html = "";

	if(!$args["lazy"] && $args["lazy_native"]){
		$attrs["loading"] = "lazy";
	}

	//error_log(json_encode($args));

	if($args["type"] == "img"){

		if($has_breakpoints && $is_single){
			$attrs[$prefix."src"] = $args["post"]->src();
		}else{
			
			$srcset = $args["post"]->srcset();
			if(!empty($srcset)){
				$srcset = reorder_srcset($srcset);
				$attrs[$prefix."srcset"] = $srcset;
				$attrs[$prefix."sizes"] = "auto";//create_sizes_attribute($srcset);//$args["post"]->img_sizes();
				$attrs[$prefix."src"] = $args["post"]->src("thumbnail");
			}else{
				$attrs[$prefix."src"] = $args["post"]->src();
			}        
			
		}
		
		if($args["post"]->post_mime_type == "image/svg+xml"){
			$args["class"] = str_replace("-cover", "-contain", $args["class"]);
		}
        
		$attrs["class"] = "img-fluid".($args["lazy"]?" lazy":"") . (!empty($args["class"])?" ".$args["class"]:"");

		if(!$args["lazy"] && $args["lcp"] && (ENABLE_MEMBERSHIP && is_user_logged_in())){
			//$urls = $attrs[$prefix."srcset"];
			//if(!empty($urls)){
			    add_action('wp_head', function() use ($attrs) {
			        add_preload_image($attrs);
			    });				
			//}
		}
        
        $attrs = array_merge($attrs, $args["attrs"]);
		$attrs = array2Attrs($attrs);
		$html .= "<img $attrs />";

	}elseif($args["type"] == "picture"){

        if($is_single){
        	$attrs[$prefix."src"] = $args["post"]->src();
        }else{
        	$attrs[$prefix."src"] = $args["post"]->src("thumbnail");
        }
        
		$attrs["class"] = "img-fluid".($args["lazy"]?" lazy":"") . (!empty($args["class"])?" ".$args["class"]:"");
        
        if(!$args["lazy"] && $args["lcp"] && (ENABLE_MEMBERSHIP && is_user_logged_in())){
			add_action('wp_head', function() use ($attrs) {
			    add_preload_image($attrs);
			});
        }
        $attrs = array_merge($attrs, $args["attrs"]);
        $attrs = array2Attrs($attrs);
		$html .= "<picture ".(!empty($args["class"])?"class='".$args["class"]."'":"").">".$args["srcset"]."<img $attrs /></picture>";

	}

	if($args["placeholder"]){
		$html = '<div class="img-placeholder '.$args["placeholder_class"].' '. ($args["lazy"] && !$args["preview"]?"loading":"").'"  style="background-color:'.$args["post"]->meta("average_color").';">' . $html . '</div>';
	}

	return $html;*/
}
/*
function get_image_set_post($args=array()){

	if (is_numeric($args["src"])) {
		//echo $args["src"]." numeric";

		$args["id"] = intval($args["src"]);
	    $args["post"] = Timber::get_image($args["id"]);

	} elseif (is_string($args["src"])) {

		//echo $args["src"]." string";
        
	    $args["id"] = get_attachment_id_by_url($args["src"]);
	    $args["post"] = Timber::get_image($args["id"]);

	} elseif (is_object($args["src"])) {
		//echo $args["src"]." object";

	    if($args["src"]->post_type == "attachment"){
	       $args["id"] = $args["src"]->ID;
           $args["post"] = $args["src"];
	    }else{
	    	if($args["src"]->thumbnail){
		       $args["id"] = $args["src"]->id;
		       $args["post"] = $args["src"]->thumbnail;	
		    }else{
		    	return;
		    }
	    }

	}

	if(empty($args["width"]) && isset($args["post"])){
        $args["width"] = $args["post"]->width();
	}
	if(empty($args["height"]) && isset($args["post"])){
	    $args["height"] = $args["post"]->height();
	}

	if(empty($args["alt"])){
		if (isset($args["post"]) && !empty($args["post"]->alt())){
			$args["alt"] = $args["post"]->alt();
		}else{
			global $post;
			$args["alt"] = $post->post_title;
		}
	}

	return $args;
}
function get_image_set_multiple($args=array(), $has_breakpoints = false){
	if(!$has_breakpoints){
		$src = array();
		if(isset($args[1]["meta"]["width"])){
			$mobile_start = $args[1]["meta"]["width"];
			foreach($args as $key => $set){
				$set =  explode(",", $set["srcset"]);
				foreach($set as $item){
				    $a = explode(" ", trim($item));
				    $width = str_replace('w', '', $a[1]);
				    if($width < 576){
		               continue;
				    }
				    if($key == 0 && $width > $mobile_start){
				    	$src[$width] = $a[0]; 
				    }elseif ($key == 1 && $width <= $mobile_start){
				    	$src[$width] = $a[0]; 
				    }
				}
			}			
		}

	    $srcset = "";
		if($src){
			uksort($src, function($a, $b) {
			    return $b - $a;
			});
			$counter = 0;
			$keys = array_keys($src);
			$last_key = end($keys);
		    foreach ($src as $width => $item) {
		    	$query_w = "max";
		    	$w = $width;
		    	if($width != $last_key){
		    	    $query_w = "min";
					$w = $keys[$counter+1] + 1;
		    	}
		        $srcset .= '<source media="('.$query_w.'-width: '.$w.'px)" '.$args[0]["prefix"].'srcset="'.$item.'">';
		        $counter++;
		    }
		}

	}else{

		$breakpoints = array_reverse($GLOBALS["breakpoints"]);
		$html = "";
		$last_image = [];

        $values = array_values($breakpoints);
        $length = count($breakpoints);
        $breakpoint_index = 0;

	    foreach ($breakpoints as $breakpoint => $min_width) {
		    $value = $values[$breakpoint_index];
	    	$query_w = $breakpoint == "xs" ? "max": "min";
	    	//$query_w = $breakpoint == "xxxl" ? "min": "max";
	    	if($breakpoint_index < $length-1){
	    		$min_width = $values[$breakpoint_index+1];
	    	}
	    	$breakpoint = $breakpoint == "xs"?"sm":$breakpoint;
	        $best_image = find_best_image_for_breakpoint($args, $breakpoint, array_keys($breakpoints));
	        if ($best_image) {
	        	//print_r("best image for ".$breakpoint." = ".$best_image["image"]."\n");
	            $html .= '<source media="('.$query_w.'-width: '.$min_width.'px)" '.$args[0]["prefix"].'srcset="'.$best_image["image"].'">' . "\n";
	            $last_image = $best_image;
	        }else{
	        	if($last_image){
	        		//print_r("last image for ".$breakpoint." = ".$last_image["image"]."\n");
	        		$html .= '<source media="('.$query_w.'-width: '.$min_width.'px)" '.$args[0]["prefix"].'srcset="'.$last_image["src"]->src($breakpoint).'">' . "\n";
	        	}
	        }
	        $breakpoint_index++;
	    }
	    $srcset = $html;
	}

    return $srcset;
}
function find_best_image_for_breakpoint($images, $breakpoint, $breakpoints) {
    $current_index = array_search($breakpoint, $breakpoints);
    $index = array_search2d_by_field($breakpoint, $images, "breakpoint");

    //echo $breakpoint." indexi : ".$current_index."\n";
    //echo $index." = image ın bu breakpoint\n";

    if($index>-1){

    	$item = $images[$index]["post"];

	    return array(
	    	"src" => $item,
		    "image" => $item->src(),//$breakpoint == "xxxl" ? $item->src() : $item->src($breakpoints[$index-1]),
		    "sizes" => $item->img_sizes()
		);

    }else{

	    for ($i = $current_index; $i >= 0; $i--) {
		    $current_breakpoint = $breakpoints[$i];
		    $index = array_search2d_by_field($current_breakpoint, $images, "breakpoint");
			if($index){
			  	$item = $images[$index]["post"];
			   	return array(
			   		"src" => $item,
			    	"image" => $item->src(),//$breakpoint == "xxxl" ? $item->src() : $item->src($breakpoints[$i-1]),
			    	"sizes" => $item->img_sizes()
			    );
			}
    	}

	}
    return [];
}
function reorder_srcset($srcset) {// img için
    // Her bir kaynağı virgülle ayır
    $sources = explode(', ', $srcset);
    
    // Her bir kaynağı genişlik değeri ile birlikte bir diziye dönüştür
    $sources_array = [];
    foreach ($sources as $source) {
        // Kaynağı boşlukla ayır ve genişlik değerini al
        $parts = explode(' ', $source);
        $url = $parts[0];
        $width = intval($parts[1]);
        
        // Diziyi genişlik değeri ile birleştir
        $sources_array[] = ['url' => $url, 'width' => $width];
    }
    
    // Genişlik değerine göre sıralama yap
    usort($sources_array, function($a, $b) {
        return $a['width'] - $b['width'];
    });
    
    // Sıralanmış kaynakları yeniden stringe dönüştür
    $sorted_srcset = '';
    foreach ($sources_array as $source) {
        $sorted_srcset .= $source['url'] . ' ' . $source['width'] . 'w, ';
    }
    
    // Son virgülü kaldır
    return rtrim($sorted_srcset, ', ');
}
function create_sizes_attribute($srcset) {
    // Her bir kaynağı virgülle ayır
    $sources = explode(', ', $srcset);
    
    // Her bir kaynağı genişlik değeri ile birlikte bir diziye dönüştür
    $sources_array = [];
    foreach ($sources as $source) {
        // Kaynağı boşlukla ayır ve genişlik değerini al
        $parts = explode(' ', $source);
        $width = intval($parts[1]);
        
        // Diziyi genişlik değeri ile birleştir
        $sources_array[] = ['width' => $width];
    }
    
    // Genişlik değerine göre sıralama yap
    usort($sources_array, function($a, $b) {
        return $a['width'] - $b['width'];
    });
    
    // `sizes` attribute'unu oluştur
    $sizes = '';
    foreach ($sources_array as $source) {
        $sizes .= '(max-width: ' . $source['width'] . 'px) ' . $source['width'] . 'px, ';
    }
    
    // Son virgülü kaldır
    return rtrim($sizes, ', ');
}
function add_preload_image($attrs){
	error_log("add_preload_image ".json_encode($attrs));
	if(empty($attrs)){
		return;
	}
	if(is_array($attrs)){
		if(isset($attrs["srcset"])){
			echo '<link rel="preload" as="image" href="'.$attrs["src"].'" imagesrcset="'.$attrs["srcset"].'" imagesizes="'.$attrs["sizes"].'">'."\n";
		}else{
			error_log(json_encode($attrs["src"]));
			echo '<link rel="preload" href="'.$attrs["src"].'" as="image">'."\n";
		}		
	}else{
		echo '<link rel="preload" href="'.$attrs.'" as="image">'."\n";
	}
}*/

function get_video($video_args=array()){

	$args  = $video_args["src"];
	$type  = $video_args["src"]["video_type"] ?? "file";
	$class = isset($video_args["class"])?$video_args["class"]:"";
	$init  = $video_args["init"] ?? false;
	$lazy  = $video_args["lazy"] ?? false;
	
	$embed = '<div class="player plyr__video-embed {{class}}" {{config}} {{controls}} {{autoplay}} {{poster}} {{muted}} {{attrs}}><iframe
	    class="video"
	    src="{{src}}"
	    allowfullscreen
	    allowtransparency
	    allow="autoplay"
	  ></iframe></div>';
	$audio = '<audio class="player video {{class}}" {{controls}} {{autoplay}} {{muted}} {{config}}>
	  <source src="{{src}}" type="audio/mp3" />
	</audio>';
	$file = '<video class="player video {{class}} w-100" playsinline {{controls}} {{autoplay}} {{poster}} {{muted}} {{config}} preload="{{preload}}" {{lazy}} {{attrs}}>
	  <source src="{{src}}" type="video/mp4" />
	  {{vtt}}
	</video>';
	$file_responsive = '<video class="player video {{class}} w-100" playsinline {{controls}} {{autoplay}} {{poster}} {{muted}} {{config}} preload="{{preload}}" {{lazy}} {{attrs}}>
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
		//$class .= "lazy";
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
		   	    //error_log(json_encode($args["files"]));
		   	    $code = str_replace("{{src}}", $args["files"][0]["file"], $code);
		    }
		break;

		case "embed" :
		    if(!isset($args["video_url"]) || (isset($args["video_url"]) && empty($args["video_url"]))){
		    	return;
		    }
		    $data = acf_oembed_data($args["video_url"]);
			$code = str_replace("{{src}}", $data["embed_url"], $code);
			if(isset($settings["custom_video_image"]) && $settings["custom_video_image"] && !empty($settings["video_image"])){
				$code = str_replace("{{poster}}", "data-poster=".$settings["video_image"], $code);
	        }else{
	        	$code = str_replace("{{poster}}", "", $code);
	        }
		break;

		case "file" :
		    if(isset($args["video_file"])){
			    if(is_array($args["video_file"])){
			    	$sources = "";
			    	foreach($args["video_file"] as $key => $source){
			    		$attachment_id = get_attachment_id_by_url($source);
			    		if($source){
			    			$meta = get_post_meta($attachment_id, '_wp_attachment_metadata', true);
			    			$sources .= '<source '.($lazy?"":"").'src="'.$source.'" type="'.$meta["mime_type"].'" size="'.$meta["height"].'" />';
			    		}
			    	}
			    	if($sources){
						$code = str_replace("{{src}}", $sources, $code);
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
					add_action('wp_head', function() use ($poster) {
				        add_preload_image($poster);
				    });					
				}
	        }else{
	        	$code = str_replace("{{poster}}", "", $code);
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
	if(isset($settings["videoBg"]) && $settings["videoBg"]){
		$config["fullscreen"] = [ "enabled" => false, "fallback" => false, "iosNative" => false, "container" => null ];
		$settings["videoReact"] = false;
		$config["clickToPlay"] = false;
		//$settings["controls"] = false;
		$code = str_replace("{{class}}", "video-bg ".$class, $code);    
	}
	$code = str_replace("{{class}}", $class, $code);

	if(isset($settings["controls"]) && $settings["controls"]){
		$config["controls"] = $settings["controls_options"];
		if($settings["controls_options_settings"]){
			$config["settings"] = $settings["controls_options_settings"];			
		}
		$code = str_replace("{{controls}}", "controls", $code);
        $config["hideControls"] = $settings["controls_hide"];
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

	if(isset($settings["ratio"]) && $settings["ratio"]){
		$config["ratio"] = str_replace("235", "2.35", $settings["ratio"]);
		$config["ratio"] = str_replace("185", "1.85", $config["ratio"]);
    	$config["ratio"] = str_replace("x", ":", $config["ratio"]);
	}

	$config["debug"] = false;
	$config = json_encode($config);
	$config = str_replace("'","", $config);
	//$config = str_replace('"','', $config);
	$data_config = "data-plyr-config='$config'";
	$code = str_replace("{{config}}", $data_config, $code);
    return $code;
}