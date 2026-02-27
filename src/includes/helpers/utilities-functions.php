<?php

if (!function_exists('boolval')) {
	function boolval($val) {
	   return (bool) $val;
	}
}

function boolstr($val = false){
    return boolval($val)?"true":"false";
}

function is_true($val, $return_null=false){
    $boolval = ( is_string($val) ? filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : (bool) $val );
    return ( $boolval===null && !$return_null ? false : $boolval );
}

function get_random_number($min,$max){
	return rand($min,$max);
}

function unique_code($limit){
    return substr(base_convert(sha1(uniqid(mt_rand())), 16, 36), 0, $limit);
}

function unicode_decode($str) {
    return preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($match) {
    return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
}, $str);
}

/*function hex2rgb($hex) {
   $hex = trim(str_replace("#", "", $hex));
   if(strlen($hex) == 3) {
      $r = hexdec(substr($hex,0,1).substr($hex,0,1));
      $g = hexdec(substr($hex,1,1).substr($hex,1,1));
      $b = hexdec(substr($hex,2,1).substr($hex,2,1));
   } else {
    //error_log($hex);
      $r = hexdec(substr($hex,0,2));
      $g = hexdec(substr($hex,2,2));
      $b = hexdec(substr($hex,4,2));
   }
   $rgb = array($r, $g, $b);
   //return implode(",", $rgb); // returns the rgb values separated by commas
   return $rgb; // returns an array with the rgb values
}*/
function hex2rgb($hex) {
    $hex = trim(str_replace("#", "", strtolower($hex)));

    // "transparent" gibi özel CSS renk değerleri kontrolü
    if ($hex === 'transparent') {
        return [0, 0, 0]; // rgba(0, 0, 0, 0) gibi davranır
    }

    // 3 karakterli hex (örn: #fff)
    if(strlen($hex) === 3) {
        $r = hexdec(str_repeat(substr($hex, 0, 1), 2));
        $g = hexdec(str_repeat(substr($hex, 1, 1), 2));
        $b = hexdec(str_repeat(substr($hex, 2, 1), 2));
    }
    // 6 karakterli hex (örn: #ffffff)
    elseif(strlen($hex) === 6) {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    }
    else {
        //error_log("hex2rgb() geçersiz renk: " . $hex);
        return null;
    }

    return [$r, $g, $b];
}

function make_rgba($hex, $alpha) {
	  $alpha = !isset($alpha)?1:$alpha;
	  return 'rgba('.implode(",",hex2rgb($hex)).','.$alpha.')';
}
function hex2rgbValues($hex){
    return implode(",", hex2rgb($hex));
}

function make_gradient_vertical($color_1,$color_2,$color_1_alpha,$color_2_alpha){
	if(isset($color_1_alpha)){
	   $color_1=make_rgba($color_1,$color_1_alpha);
	}
	if(isset($color_2_alpha)){
	   $color_2=make_rgba($color_2,$color_2_alpha);
	}
	 return 'background: '.$color_1.';' .
			'background: -moz-linear-gradient(top, '.$color_1.' 0%, '.$color_2.' 100%);'.
			'background: -webkit-gradient(linear, left top, left bottom, color-stop(0%,'.$color_1.'), color-stop(100%,'.$color_2.'));' .
			'background: -webkit-linear-gradient(top, '.$color_1.' 0%,'.$color_2.' 100%);' .
			'background: -o-linear-gradient(top, '.$color_1.' 0%,'.$color_2.' 100%);' .
			'background: -ms-linear-gradient(top, '.$color_1.' 0%,'.$color_2.' 100%);' .
			'background: linear-gradient(to bottom, '.$color_1.' 0%,'.$color_2.' 100%);' .
			'filter: progid:DXImageTransform.Microsoft.gradient( startColorstr="'.$color_1.'", endColorstr="'.$color_2.'",GradientType=0 );';
}
function phpcolors($color, $method="getRgb", $amount=0){
	$color = new Mexitek\PHPColors\Color($color);
    return $color->$method();
}




function get_image_average_color($image_path) {
    // Dosya uzantısını kontrol et
    $extension = strtolower(pathinfo($image_path, PATHINFO_EXTENSION));

    // Görüntüyü oluştur
    switch ($extension) {
        case 'webp':
            if (function_exists('imagecreatefromwebp')) {
                $image = @imagecreatefromwebp($image_path);
            } else {
                return false;
            }
            break;
        case 'jpeg':
        case 'jpg':
            $image = @imagecreatefromjpeg($image_path);
            break;
        case 'png':
            $image = @imagecreatefrompng($image_path);
            break;
        case 'avif':
            if (class_exists('Imagick')) {
                try {
                    $imagick = new Imagick($image_path);
                    $imagick->transformImageColorspace(Imagick::COLORSPACE_RGB);
                    $image = imagecreatefromstring($imagick->getImageBlob());
                    $imagick->destroy();
                } catch (Exception $e) {
                    //error_log('AVIF renk alma hatası: ' . $e->getMessage());
                    return false;
                }
            } elseif (function_exists('imageavif')) {
                $image = imagecreatefromstring(file_get_contents($image_path));
                if (!$image) return false;
            } elseif (shell_exec('which avifdec')) {
                $temp_png = str_replace('.avif', '.png', $image_path);
                shell_exec("avifdec $image_path $temp_png");
                if (file_exists($temp_png)) {
                    $image = imagecreatefrompng($temp_png);
                    unlink($temp_png);
                } else {
                    return false;
                }
            } else {
                return false;
            }
            break;
        default:
            return false;
    }

    if (!$image) {
        return false;
    }

    $width = imagesx($image);
    $height = imagesy($image);

    $total_red = $total_green = $total_blue = 0;

    for ($x = 0; $x < $width; $x++) {
        for ($y = 0; $y < $height; $y++) {
            $rgb = imagecolorat($image, $x, $y);
            $total_red += ($rgb >> 16) & 0xFF;
            $total_green += ($rgb >> 8) & 0xFF;
            $total_blue += $rgb & 0xFF;
        }
    }

    $total_pixels = $width * $height;
    $average_red = round($total_red / $total_pixels);
    $average_green = round($total_green / $total_pixels);
    $average_blue = round($total_blue / $total_pixels);

    $average_color = sprintf('#%02x%02x%02x', $average_red, $average_green, $average_blue);
    $contrast_color = calculate_contrast_color($average_color);

    return array(
        'average_color' => $average_color,
        'contrast_color' => $contrast_color,
    );
}
function calculate_contrast_color($color) {
    // Renk kodunu parçala
    list($r, $g, $b) = sscanf($color, "#%02x%02x%02x");

    // Luminance hesapla
    $luminance = 0.299 * $r + 0.587 * $g + 0.114 * $b;

    // Kontrast oranına göre siyah veya beyaz rengi seç
    return ($luminance > 128) ? '#000000' : '#FFFFFF';
}
function calculate_contrast_color_mode($color) {
    return calculate_contrast_color($color) == "#000000" ? "light" : "dark";
}





function phone_link($phone, $class="", $title=""){
	$title = !isset($title)||empty($title)?$phone:$title;
	$phone_url=filter_var($phone, FILTER_SANITIZE_NUMBER_INT);
	return "<a href='tel:".$phone_url."' class='".$class."'>".$title."</a>";
}

function email_link($email, $class="", $title=""){
	$title = !isset($title)||empty($title)?$email:$title;
	return "<a href='mailto:".$email."' class='".$class."'>".$title."</a>";
}

function url_link($link= "", $target="_self", $title="", $remove_protocol=false, $remove_www=false, $class="", $text="", $domain_only=false){
	$attr = "";
    $link = rtrim($link, '/');
	$title = !isset($title)||empty($title)?$link:$title;

	if($remove_protocol){
	   $title = str_replace("https://", "", $title);
	   $title = str_replace("http://", "", $title);
	}
	if($remove_www){
	   $title = str_replace("www.", "", $title);
	}
    if($domain_only){
        $parsed = parse_url($link);
        $title = $parsed["host"];
    }
	if($target == "_blank"){
	   $attr = " rel='nofollow' ";
	}
	return "<a href='".$link."' class='".$class."' target='".$target."' ".$attr.">".$title.$text."</a>";
}

function list_social_accounts($accounts=array(), $class="", $hover=false){
	$code = "";
	if(isset($accounts) && $accounts){
		$code = '<ul class="'.$class.' list-social list-inline">';
	    foreach($accounts as $account){
	    	$link = $account["url"];
	    	if($account["name"] == "whatsapp"){
	    		$link = "https://wa.me/".str_replace("+", "", filter_var($link, FILTER_SANITIZE_NUMBER_INT));
	    	}
	    	$code .= '<li class="list-inline-item"><a href="'.$link.'" class="'.($hover?'btn-social-'.$account["name"].'-hover':'').'" title="'.$account["name"].'" target="_blank" rel="nofollow" itemprop="sameAs"><i class="fab fa-'.$account["name"].($account["name"]=="facebook"?"-f":"").' fa-fw"></i></a></li>';
		}
		$code .= "</ul>";
	}
	return $code;
}

function wrap_last($text, $tag){
	if(isset($text)){
		$code = str_replace("  ", " ", $text);
		$arr = explode(" ", $code);
		if(count($arr)>1){
			$text = "";
		    foreach($arr as $key => $item){
		    	if($key == count($arr)-1){
	               $text .= "<".$tag.">".$item."</".$tag.">";
		    	}else{
	               $text .= " ".$item;
		    	}
			}			
		}
	}
	return $text;
}

function date_to_iso8601($date="", $timezone="") {
    $date = date_create($date);
    if(!empty($timezone)){
        $date = $date->setTimezone(new DateTimeZone($timezone));
    }
    return date_format($date, 'c');
}

function time_to_iso8601_duration($time) {
	$time=strtotime($time, 0);
    $units = array(
        "Y" => 365*24*3600,
        "D" =>     24*3600,
        "H" =>        3600,
        "M" =>          60,
        "S" =>           1,
    );

    $str = "P";
    $istime = false;

    foreach ($units as $unitName => &$unit) {
        $quot  = intval($time / $unit);
        $time -= $quot * $unit;
        $unit  = $quot;
        if ($unit > 0) {
            if (!$istime && in_array($unitName, array("H", "M", "S"))) { // There may be a better way to do this
                $str .= "T";
                $istime = true;
            }
            $str .= strval($unit) . $unitName;
        }
    }

    return $str;
}


function array2List($array=array(), $class="", $tag="ul"){
	$list = "";
	if($array){
	   $list = "<".$tag." class='".$class."'>";
	   foreach($array as $item){
          $list .= "<li class='".($class?$class."-item":"")."'>".$item."</li>";
	   }
	   $list .= "</".$tag.">";
	}
	return $list;
}


function mime2ext($mime) {
        $mime_map = [
            'video/3gpp2'                                                               => '3g2',
            'video/3gp'                                                                 => '3gp',
            'video/3gpp'                                                                => '3gp',
            'application/x-compressed'                                                  => '7zip',
            'audio/x-acc'                                                               => 'aac',
            'audio/ac3'                                                                 => 'ac3',
            'application/postscript'                                                    => 'ai',
            'audio/x-aiff'                                                              => 'aif',
            'audio/aiff'                                                                => 'aif',
            'audio/x-au'                                                                => 'au',
            'video/x-msvideo'                                                           => 'avi',
            'video/msvideo'                                                             => 'avi',
            'video/avi'                                                                 => 'avi',
            'application/x-troff-msvideo'                                               => 'avi',
            'application/macbinary'                                                     => 'bin',
            'application/mac-binary'                                                    => 'bin',
            'application/x-binary'                                                      => 'bin',
            'application/x-macbinary'                                                   => 'bin',
            'image/bmp'                                                                 => 'bmp',
            'image/x-bmp'                                                               => 'bmp',
            'image/x-bitmap'                                                            => 'bmp',
            'image/x-xbitmap'                                                           => 'bmp',
            'image/x-win-bitmap'                                                        => 'bmp',
            'image/x-windows-bmp'                                                       => 'bmp',
            'image/ms-bmp'                                                              => 'bmp',
            'image/x-ms-bmp'                                                            => 'bmp',
            'application/bmp'                                                           => 'bmp',
            'application/x-bmp'                                                         => 'bmp',
            'application/x-win-bitmap'                                                  => 'bmp',
            'application/cdr'                                                           => 'cdr',
            'application/coreldraw'                                                     => 'cdr',
            'application/x-cdr'                                                         => 'cdr',
            'application/x-coreldraw'                                                   => 'cdr',
            'image/cdr'                                                                 => 'cdr',
            'image/x-cdr'                                                               => 'cdr',
            'zz-application/zz-winassoc-cdr'                                            => 'cdr',
            'application/mac-compactpro'                                                => 'cpt',
            'application/pkix-crl'                                                      => 'crl',
            'application/pkcs-crl'                                                      => 'crl',
            'application/x-x509-ca-cert'                                                => 'crt',
            'application/pkix-cert'                                                     => 'crt',
            'text/css'                                                                  => 'css',
            'text/x-comma-separated-values'                                             => 'csv',
            'text/comma-separated-values'                                               => 'csv',
            'application/vnd.msexcel'                                                   => 'csv',
            'application/x-director'                                                    => 'dcr',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'   => 'docx',
            'application/x-dvi'                                                         => 'dvi',
            'message/rfc822'                                                            => 'eml',
            'application/x-msdownload'                                                  => 'exe',
            'video/x-f4v'                                                               => 'f4v',
            'audio/x-flac'                                                              => 'flac',
            'video/x-flv'                                                               => 'flv',
            'image/gif'                                                                 => 'gif',
            'application/gpg-keys'                                                      => 'gpg',
            'application/x-gtar'                                                        => 'gtar',
            'application/x-gzip'                                                        => 'gzip',
            'application/mac-binhex40'                                                  => 'hqx',
            'application/mac-binhex'                                                    => 'hqx',
            'application/x-binhex40'                                                    => 'hqx',
            'application/x-mac-binhex40'                                                => 'hqx',
            'text/html'                                                                 => 'html',
            'image/x-icon'                                                              => 'ico',
            'image/x-ico'                                                               => 'ico',
            'image/vnd.microsoft.icon'                                                  => 'ico',
            'text/calendar'                                                             => 'ics',
            'application/java-archive'                                                  => 'jar',
            'application/x-java-application'                                            => 'jar',
            'application/x-jar'                                                         => 'jar',
            'image/jp2'                                                                 => 'jp2',
            'video/mj2'                                                                 => 'jp2',
            'image/jpx'                                                                 => 'jp2',
            'image/jpm'                                                                 => 'jp2',
            'image/jpeg'                                                                => 'jpg',
            'image/pjpeg'                                                               => 'jpg',
            'application/x-javascript'                                                  => 'js',
            'application/json'                                                          => 'json',
            'text/json'                                                                 => 'json',
            'application/vnd.google-earth.kml+xml'                                      => 'kml',
            'application/vnd.google-earth.kmz'                                          => 'kmz',
            'text/x-log'                                                                => 'log',
            'audio/x-m4a'                                                               => 'm4a',
            'application/vnd.mpegurl'                                                   => 'm4u',
            'audio/midi'                                                                => 'mid',
            'application/vnd.mif'                                                       => 'mif',
            'video/quicktime'                                                           => 'mov',
            'video/x-sgi-movie'                                                         => 'movie',
            'audio/mpeg'                                                                => 'mp3',
            'audio/mpg'                                                                 => 'mp3',
            'audio/mpeg3'                                                               => 'mp3',
            'audio/mp3'                                                                 => 'mp3',
            'video/mp4'                                                                 => 'mp4',
            'video/mpeg'                                                                => 'mpeg',
            'application/oda'                                                           => 'oda',
            'audio/ogg'                                                                 => 'ogg',
            'video/ogg'                                                                 => 'ogg',
            'application/ogg'                                                           => 'ogg',
            'application/x-pkcs10'                                                      => 'p10',
            'application/pkcs10'                                                        => 'p10',
            'application/x-pkcs12'                                                      => 'p12',
            'application/x-pkcs7-signature'                                             => 'p7a',
            'application/pkcs7-mime'                                                    => 'p7c',
            'application/x-pkcs7-mime'                                                  => 'p7c',
            'application/x-pkcs7-certreqresp'                                           => 'p7r',
            'application/pkcs7-signature'                                               => 'p7s',
            'application/pdf'                                                           => 'pdf',
            'application/octet-stream'                                                  => 'pdf',
            'application/x-x509-user-cert'                                              => 'pem',
            'application/x-pem-file'                                                    => 'pem',
            'application/pgp'                                                           => 'pgp',
            'application/x-httpd-php'                                                   => 'php',
            'application/php'                                                           => 'php',
            'application/x-php'                                                         => 'php',
            'text/php'                                                                  => 'php',
            'text/x-php'                                                                => 'php',
            'application/x-httpd-php-source'                                            => 'php',
            'image/png'                                                                 => 'png',
            'image/x-png'                                                               => 'png',
            'application/powerpoint'                                                    => 'ppt',
            'application/vnd.ms-powerpoint'                                             => 'ppt',
            'application/vnd.ms-office'                                                 => 'ppt',
            'application/msword'                                                        => 'doc',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'application/x-photoshop'                                                   => 'psd',
            'image/vnd.adobe.photoshop'                                                 => 'psd',
            'audio/x-realaudio'                                                         => 'ra',
            'audio/x-pn-realaudio'                                                      => 'ram',
            'application/x-rar'                                                         => 'rar',
            'application/rar'                                                           => 'rar',
            'application/x-rar-compressed'                                              => 'rar',
            'audio/x-pn-realaudio-plugin'                                               => 'rpm',
            'application/x-pkcs7'                                                       => 'rsa',
            'text/rtf'                                                                  => 'rtf',
            'text/richtext'                                                             => 'rtx',
            'video/vnd.rn-realvideo'                                                    => 'rv',
            'application/x-stuffit'                                                     => 'sit',
            'application/smil'                                                          => 'smil',
            'text/srt'                                                                  => 'srt',
            'image/svg+xml'                                                             => 'svg',
            'application/x-shockwave-flash'                                             => 'swf',
            'application/x-tar'                                                         => 'tar',
            'application/x-gzip-compressed'                                             => 'tgz',
            'image/tiff'                                                                => 'tiff',
            'text/plain'                                                                => 'txt',
            'text/x-vcard'                                                              => 'vcf',
            'application/videolan'                                                      => 'vlc',
            'text/vtt'                                                                  => 'vtt',
            'audio/x-wav'                                                               => 'wav',
            'audio/wave'                                                                => 'wav',
            'audio/wav'                                                                 => 'wav',
            'application/wbxml'                                                         => 'wbxml',
            'video/webm'                                                                => 'webm',
            'audio/x-ms-wma'                                                            => 'wma',
            'application/wmlc'                                                          => 'wmlc',
            'video/x-ms-wmv'                                                            => 'wmv',
            'video/x-ms-asf'                                                            => 'wmv',
            'application/xhtml+xml'                                                     => 'xhtml',
            'application/excel'                                                         => 'xl',
            'application/msexcel'                                                       => 'xls',
            'application/x-msexcel'                                                     => 'xls',
            'application/x-ms-excel'                                                    => 'xls',
            'application/x-excel'                                                       => 'xls',
            'application/x-dos_ms_excel'                                                => 'xls',
            'application/xls'                                                           => 'xls',
            'application/x-xls'                                                         => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'         => 'xlsx',
            'application/vnd.ms-excel'                                                  => 'xlsx',
            'application/xml'                                                           => 'xml',
            'text/xml'                                                                  => 'xml',
            'text/xsl'                                                                  => 'xsl',
            'application/xspf+xml'                                                      => 'xspf',
            'application/x-compress'                                                    => 'z',
            'application/x-zip'                                                         => 'zip',
            'application/zip'                                                           => 'zip',
            'application/x-zip-compressed'                                              => 'zip',
            'application/s-compressed'                                                  => 'zip',
            'multipart/x-zip'                                                           => 'zip',
            'text/x-scriptzsh'                                                          => 'zsh',
        ];

        return isset($mime_map[$mime]) === true ? $mime_map[$mime] : false;
}

/**
* Grab all iframe src from a string
*/
function get_iframe_src( $input ) {
	preg_match_all('/<iframe[^>]+src="([^"]+)"/', $input, $output );
	$return = array();
	if( isset( $output[1][0] ) ) {
	   $return = $output[1][0];
	}
	return $return;
}

function get_extension($file) {
	if(!empty($file) && is_string($file)){
		$extension = explode(".", mb_strtolower($file));
		$extension = end($extension);
		return $extension ? $extension : false;	 	
	}
}

// ACHTUNG : Klasor içindeki herşeyi siler!
function rmdir_all($dir) {
    if (file_exists($dir)) {
        $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it,
                     RecursiveIteratorIterator::CHILD_FIRST);
        foreach($files as $file) {
            if ($file->isDir()){
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        rmdir($dir);
    }
}

function is_odd($num){
    if ( $num & 1 ) { 
       return true;
    } else { 
       return false;
    }
}

function is_even($num){
    if ( $num & 1 ) { 
       return false;
    } else { 
       return true;
    }
}

function vars_fix($vars=array(), $var = "", $is_array = false, $seperator=""){
    $var = isset($vars[$var])?$vars[$var]:"";
    if(!empty($var)){
        if($is_array){
            if(!is_array($var)){
                $var = [$var];
            }        
        }else{
            if(!empty($seperator)){
                $var = explode($seperator, $var);
            }
        }        
    }
    return $var;
}


function hour_to_timestamp($hour="00:00"){
    $hour = explode(":", $hour);
    return mktime($hour[0], $hour[1], 0, 0, 0, 0);
}
function hour_to_date($hour="00:00"){
    return date('Y-m-d H:i', hour_to_timestamp($hour));
}


function convert_filesize($bytes="", $decimals = 2){
    $bytes_len = 0;
    if(!empty($bytes)){
        $bytes_len = strlen($bytes);
    }
    $size = array('B','kB','MB','GB','TB','PB','EB','ZB','YB');
    $factor = floor(($bytes_len - 1) / 3);
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$size[$factor];
}



function convertCoordinate($coordinates) {
    // Koordinatı boşluk karakterinden ayır
    $parts = explode(" ", $coordinates);

    // Latitude ve Longitude değerlerini ayrı ayrı işle
    $latitude = $parts[0];
    $longitude = $parts[1];

    // Latitude ve Longitude'u ayrı ayrı dönüştür
    $latitude = convertToDecimal($latitude);
    $longitude = convertToDecimal($longitude);

    // Sonuçları bir dizi olarak döndür
    return [
        'lat' => $latitude,
        'lng' => $longitude
    ];
}

function convertToDecimal($coordinate) {
    // Derece, dakika, saniye değerlerini ayır
    preg_match('/(\d+)°(\d+)\'([\d\.]+)"/', $coordinate, $matches);

    $degree = intval($matches[1]);
    $minute = intval($matches[2]);
    $second = floatval($matches[3]);

    // Derece, dakika, saniye değerlerini decimal dereceye dönüştür
    $decimalDegree = $degree + ($minute / 60) + ($second / 3600);

    return $decimalDegree;
}


function decimalToDMS($lat, $lon) {
    $latDirection = ($lat >= 0) ? 'N' : 'S';
    $lonDirection = ($lon >= 0) ? 'E' : 'W';

    $latAbs = abs($lat);
    $lonAbs = abs($lon);

    $latDegrees = floor($latAbs);
    $latMinutes = floor(($latAbs - $latDegrees) * 60);
    $latSeconds = ($latAbs - $latDegrees - ($latMinutes / 60)) * 3600;

    $lonDegrees = floor($lonAbs);
    $lonMinutes = floor(($lonAbs - $lonDegrees) * 60);
    $lonSeconds = ($lonAbs - $lonDegrees - ($lonMinutes / 60)) * 3600;

    $dms = sprintf(
        "%02d°%02d'%04.1f\"%s %03d°%02d'%04.1f\"%s",
        $latDegrees, $latMinutes, $latSeconds, $latDirection,
        $lonDegrees, $lonMinutes, $lonSeconds, $lonDirection
    );

    return $dms;
}

function isJson($string) {
    // JSON veriyi d8önüştürmeye çalış
    $decoded = json_decode($string);

    // JSON hatalarını kontrol et
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        return false; // Geçerli bir JSON değil
    }

    return true; // JSON formatında
}

function objectSort($obj, $sortBy, $sort = "desc", $numeric = false) {
    $sorted = array();
    $direction = ($sort === "asc") ? 1 : -1;

    usort($obj, function ($a, $b) use ($sortBy, $direction, $numeric) {
        if ($numeric) {
            // Eğer sayısal sıralama yapılacaksa, sayısal karşılaştırma yapın
            $aValue = is_numeric($a->$sortBy) ? $a->$sortBy : 0;
            $bValue = is_numeric($b->$sortBy) ? $b->$sortBy : 0;

            return ($aValue - $bValue) * $direction;
        } else {
            $collation = setlocale(LC_COLLATE, 'tr_TR.utf8', 'tr_TR.utf-8', 'tr_TR', 'turkish');
        
            if ($collation === false) {
                $collation = 'en_US.utf8';
            }
        
            $result = strcoll(mb_strtolower($a->$sortBy, 'UTF-8'), mb_strtolower($b->$sortBy, 'UTF-8'));
        
            if ($result === 0) {
                return 0;
            }
        
            return ($result < 0) ? -1 * $direction : 1 * $direction;
        }
    });

    return $obj;
}


function removeRangesFromDay($specifiedIntervals = array()) {// array[{start:,end:}, {start:, end:}]
    $allIntervals = array();
    $result = array();

    // Tüm saat aralıklarını oluştur
    for ($hour = 0; $hour < 24; $hour++) {
        for ($minute = 0; $minute < 60; $minute++) {
            $time = sprintf("%02d:%02d", $hour, $minute);
            $allIntervals[] = $time;
        }
    }

    // Belirtilen saat aralıklarını çıkar
    foreach ($specifiedIntervals as $interval) {
        $start = strtotime($interval["start"]);
        $end = strtotime($interval["end"]);

        for ($time = $start; $time <= $end; $time += 60) {
            $index = date("H:i", $time);
            unset($allIntervals[array_search($index, $allIntervals)]);
        }
    }

    // Kalan saat aralıklarını oluştur
    $currentInterval = null;
    foreach ($allIntervals as $interval) {
        if ($currentInterval === null) {
            $currentInterval = array("start" => $interval, "end" => $interval);
        } else {
            $currentTime = strtotime($interval);
            $endTime = strtotime($currentInterval["end"]);

            if ($currentTime == $endTime + 60) {
                $currentInterval["end"] = $interval;
            } else {
                $result[] = $currentInterval;
                $currentInterval = array("start" => $interval, "end" => $interval);
            }
        }
    }

    if ($currentInterval !== null) {
        $result[] = $currentInterval;
    }

    return $result;
}


function mergeRangeConflicts($zaman_araligi){

    $unique = array();
    foreach ($zaman_araligi as $zaman) {
        if ($zaman["start"] != $zaman["end"]) {
            $unique[] = $zaman;
        }
    }
    $zaman_araligi = $unique;

    $sonuc = [];
    $item_sayisi = count($zaman_araligi);

    if ($item_sayisi > 1) {
        $sonuc[] = $zaman_araligi[0]; // İlk öğeyi direk ekleyelim
        for ($index = 1; $index < $item_sayisi; $index++) {
            $onceki_zaman = end($sonuc);
            $zaman = $zaman_araligi[$index];
            if ($onceki_zaman['end'] == $zaman['start'] || $onceki_zaman['end'] > $zaman['start']) {
                // Birleştir
                $sonuc[count($sonuc) - 1]['end'] = $zaman['end'];
            } else {
                // Birleştirme gerekmiyor, doğrudan sonuca ekle
                $sonuc[] = $zaman;
            }
        }
    } else {
        // Sadece bir item varsa direkt sonuca ekle
        if($zaman_araligi[0]["start"] != $zaman_araligi[0]["end"]){
           $sonuc = $zaman_araligi;            
        }
    }
    return $sonuc;
}



function hour_to_number($time) {
    // Saati parçala
    list($hour, $minute) = explode(':', $time);

    // Saat ve dakikayı birleştirerek bir tam sayı elde et
    $value = intval($hour) * 60 + intval($minute);

    return $value;
}


function getMonthName($ayRakami, $format="MMM") {
    $locale = get_locale();
    setlocale(LC_TIME, $locale);
    try {
        $date = new DateTime("2024-$ayRakami-01");
        $formatter = new IntlDateFormatter($locale, IntlDateFormatter::FULL, IntlDateFormatter::NONE);
        $formatter->setPattern($format);
        return $formatter->format($date);
    } catch (Exception $e) {
        return '';
    }
}

function getDayName($gunRakami, $format = "EEEE") {
    $locale = get_locale();
    setlocale(LC_TIME, $locale);
    try {
        $date = new DateTime("2024-01-$gunRakami"); // Burada sabit bir ay (Ocak) kullanıldı, günlük ismi almak istediğiniz ayı belirtmek için gerekirse değiştirebilirsiniz.
        $formatter = new IntlDateFormatter($locale, IntlDateFormatter::FULL, IntlDateFormatter::NONE);
        $formatter->setPattern($format);
        return $formatter->format($date);
    } catch (Exception $e) {
        return '';
    }
}

function convertToLink($inputString) {
    // Dosya linkini bulmak için düzenli ifade kullanılır
    $pattern = '/\b(?:https?|ftp):\/\/\S+\.(jpg|jpeg|png|gif|pdf|docx|xlsx)\b/i';
    preg_match($pattern, $inputString, $matches);

    // Eğer dosya linki bulunduysa
    if (!empty($matches)) {
        $link = $matches[0];
        $extension = $matches[1];

        // Bağlantıyı oluştur
        $outputString = '<a href="' . $link . '" target="_blank" class="text-primary"><i class="icon fal fa-file-' . $extension . ' fa-2x"></i></a>';
        
        return $outputString;
    } else {
        // Dosya linki bulunamazsa orijinal metni döndür
        return $inputString;
    }
}

function get_post_read_time($icerik = "", $birim = "min") {
    $kelime_sayisi = str_word_count(strip_tags($icerik));
    $okuma_hizi = 250; // Ortalama dakikada okunan kelime sayısı
    
    if(!in_array($birim, ["sec", "min"])){
        $birim = "min";
    }
    if(ceil($kelime_sayisi / $okuma_hizi) <= 1){
        $birim = "sec";
    }

    if ($birim === "sec") {
        return ceil(($kelime_sayisi * 60) / $okuma_hizi) . " sec"; // Saniyeye dönüştürme
    } else {
        return ceil($kelime_sayisi / $okuma_hizi) . " min";
    }
}


function update_dynamic_css_whitelist($arr = array()){
    $file_path = get_template_directory() . '/theme/static/data/css_safelist.json';
    if(!file_exists($file_path)){
        $json_data = json_encode(['dynamicSafelist' => []], JSON_PRETTY_PRINT);
        file_put_contents($file_path, $json_data); 
    }
    if(file_exists($file_path)){
        $data = file_get_contents($file_path);
        $data = json_decode($data, true);
        $data = $data["dynamicSafelist"];
        $data = array_merge($data, $arr);
        $data = remove_duplicated_items($data);
        $json_data = json_encode(['dynamicSafelist' => $data], JSON_PRETTY_PRINT);
        file_put_contents($file_path, $json_data); 
    }   
}


function get_page_number($link) {
    if (preg_match('/page\/([0-9]+)/', $link, $matches)) {
        return intval($matches[1]);
    }
}


function did_you_mean_search($input = "", $max_distance = 2) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'search_terms';
    $terms = $wpdb->get_results("SELECT name FROM $table_name ORDER BY rank DESC", ARRAY_A);
    
    $closest = '';
    $shortest = $max_distance + 1; // Başlangıçta en yüksek mesafeyi ayarla

    foreach ($terms as $term) {
        // Levenshtein mesafesini hesapla
        $lev = levenshtein($input, $term['name']);

        // Tam eşleşmeleri atla
        if ($lev == 0) {
            continue;
        }

        // En kısa mesafeyi bul ve yakın terimi ata
        if ($lev <= $max_distance && $lev < $shortest) {
            $closest = $term['name'];
            $shortest = $lev;
        }
    }

    return $closest;
}

function search_suggestions($term = "", $count = 5) {
    global $wpdb;

    // Arama terimi boş ise geri dön
    if (empty($term)) {
        return [];
    }

    // Term'i küçük harfe çevir ve boşlukları temizle
    $term = trim(strtolower($term));
    $table_name = $wpdb->prefix . 'search_terms';

    // Tüm terimleri veritabanından çek
    $results = $wpdb->get_results("SELECT name FROM $table_name");

    $suggestions = [];
    foreach ($results as $row) {
        $name = strtolower($row->name);
        if ($name !== $term) {
            $distance = levenshtein($term, $name);
            // Mesafe kriterine göre öneri ekle
            if ($distance <= 6) { // Mesafeyi buradaki değere göre ayarlayabilirsin
                $suggestions[$name] = $distance;
            }
        }
    }

    // Mesafeye göre sıralama ve sonuçları döndür
    asort($suggestions);
    return array_slice(array_keys($suggestions), 0, $count);
}

function check_and_load_translation($textdomain, $locale = null) {
    //error_log("check_and_load_translation");
    if (!$locale) {
        $locale = determine_locale(); // WordPress 5.0+ için
    }
    $paths = [
        WP_LANG_DIR . "/themes/$textdomain-$locale.mo", // Global languages dizini
        get_template_directory() . "/languages/$locale.mo", // Tema languages dizini
        get_stylesheet_directory() . "/languages/$locale.mo", // Child tema dizini
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            load_textdomain($textdomain, $path);
        }
    }
}




function copyFolder($src, $dest, $exclude = []){
    $dir = opendir($src);

    if (!is_dir($dest)) {
        mkdir($dest, 0755, true);
    }

    while (false !== ($file = readdir($dir))) {
        if ($file == '.' || $file == '..') {
            continue; // Geçerli ve üst dizini atla
        }

        $srcPath = $src . DIRECTORY_SEPARATOR . $file;
        $destPath = $dest . DIRECTORY_SEPARATOR . $file;
            // Hariç tutulacak klasör kontrolü
        if (is_dir($srcPath) && in_array($file, $exclude)) {
            continue; // Hariç tutulan klasörü atla
        }

        if (is_dir($srcPath)) {
            copyFolder($srcPath, $destPath, $exclude);
        } else {
            copy($srcPath, $destPath);
        }
    }
    closedir($dir);
}
function copyFile($source, $destination) {
        if (!file_exists($source)) {
            return;
        }
        $destinationDir = dirname($destination);
        if (!file_exists($destinationDir)) {
            if (!mkdir($destinationDir, 0777, true)) {
                return;
            }
        }
        if (copy($source, $destination)) {

        } else {
            return;
        }
}
function moveFolder($src, $dst) {
    if (!is_dir($src)) {
        //error_log("Kaynak klasör bulunamadı: $src");
        return false;
    }
    try {
        copyFolder($src, $dst);
        deleteFolder($src);
        return true;
    } catch (Exception $e) {
        //error_log("Taşıma işlemi başarısız: " . $e->getMessage());
        return false;
    }
}
function deleteFolder($dir) {
    if (!is_dir($dir)) return;
    $items = array_diff(scandir($dir), ['.', '..']);
    foreach ($items as $item) {
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        is_dir($path) ? deleteFolder($path) : unlink($path);
    }
    rmdir($dir);
}