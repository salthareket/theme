<?php

function trans( $text="", $theme="" ) {
	if($theme==""){
		if(defined("TEXT_DOMAIN")){
			$theme = TEXT_DOMAIN;
		}
	}
	return __($text, $theme);
}
function trans_n_noop( $singular="", $plural="", $theme="" ) {
	if($theme==""){
		if(defined("TEXT_DOMAIN")){
			$theme = TEXT_DOMAIN;
		}
	}
	return _n_noop($singular, $plural, $theme);
}
function trans_plural($singular="", $plural="", $null="", $count=1, $theme=""){
	if($theme==""){
		global $text_domain;
	}else{
		$text_domain = $theme;
	}
	if($count == 0 && !empty($null)){
        return $null;
	}else{
		$pluralized = _n( $singular, $plural, $count, $text_domain );
	    return str_replace('{}', $count, $pluralized);
	}
}

function trans_static($text){
	$text =  preg_replace_callback(
        "/\{\{translate\('([^']+)'\)\}\}/",
        function($matches) {
            return trans($matches[1]);
        },
        $text
    );
    return trans_functions($text);
}

function trans_functions($text) {
    return preg_replace_callback(
        "/\{\{function\('([^']+)'\)\}\}/",
        function($matches) {
            $funcName = $matches[1];
            if (function_exists($funcName)) {
                return $funcName();
            } else {
                return '';
            }
        },
        $text
    );
}


function printf_array($text, $arr){
    return call_user_func_array('sprintf', array_merge((array)$text, $arr));
} 
function trans_arr($text, $arr){
	if(count($arr)>0){
		return printf_array(trans($text), $arr); 
	}else{
		return $text;
	}
}
function trans_lang( $text, $domain = 'default', $the_locale = 'en_US' ){
    global $locale;
    $old_locale = $locale;
    $locale = $the_locale;
    $translated = __( $text, $domain );
    $locale = $old_locale;
    return $translated;
}

function uppertr($text){
	if(function_exists('qtranxf_getLanguage')){
	   if(qtranxf_getLanguage()=="tr"){
	     $text =  str_replace('i','İ',$text); 
	   }
	}
	if(function_exists('icl_get_languages')){
	   if(ICL_LANGUAGE_CODE=="tr"){
	     $text =  str_replace('i','İ',$text); 
	   }
	}
	return mb_convert_case($text, MB_CASE_UPPER, "UTF-8");	
}
function lowertr($text){
	if(function_exists('qtranxf_getLanguage')){
	   if(qtranxf_getLanguage()=="tr"){
	     $text = str_replace('I','ı',$text);
	   }
	}
	if(function_exists('icl_get_languages')){
	   if(ICL_LANGUAGE_CODE=="tr"){
	     $text = str_replace('I','ı',$text);
	   }
	}
    return mb_convert_case($text, MB_CASE_LOWER, "UTF-8");
}
function ucwordstr($text) {
	if(function_exists('qtranxf_getLanguage')){
	   if(qtranxf_getLanguage()=="tr"){
	     $text = str_replace(array(' I',' ı', ' İ', ' i'),array(' I',' I',' İ',' İ'),' '.$text);
	   }
	}
    return ltrim(mb_convert_case($text, MB_CASE_TITLE, "UTF-8"));
}  
function ucfirsttr($text) {
    $metin = in_array(crc32($text[0]),array(1309403428, -797999993, 957143474)) ? array(uppertr(substr($text,0,2)),substr($text,2)) : array(uppertr($text[0]),substr($text,1));
    return $text[0].$text[1];
} 

function ptobr($text){
       $paragraphs = array("<p>","</p>","[p-filter]");
       $noparagraphs = array("","<br>","");
       $text = str_replace( $paragraphs, $noparagraphs, $text );
       return preg_replace('/(<br>)+$/', '', $text);
}

function stripTagsByClass($array_of_id_or_class, $text){
   $name = implode('|', $array_of_id_or_class);
   $regex = '#<(\w+)\s[^>]*(class|id)\s*=\s*[\'"](' . $name .
            ')[\'"][^>]*>.*</\\1>#isU';
   return(preg_replace($regex, '', $text));
}

function truncate($text, $chars = 25) {
    if (strlen($text) <= $chars) {
        return $text;
    }
    $text = $text." ";
    $text = substr($text,0,$chars);
    $text = substr($text,0,strrpos($text,' '));
    $text = $text."...";
    return $text;
}

function truncate_middle($text, $chars = 25) {
	if (strlen($text) <= $chars) {
        return $text;
    }
	$separator = '...';
	$separatorlength = strlen($separator) ;
	$maxlength = $chars - $separatorlength;
	$start = $maxlength / 2 ;
	$trunc =  strlen($text) - $maxlength;
	return substr_replace($text, $separator, $start, $trunc);
}

function removeUrls($text=""){
	$text = preg_replace('/\b((https?|ftp|file):\/\/|www\.)[-A-Z0-9+&@#\/%?=~_|$!:,.;]*[A-Z0-9+&@#\/%=~_|$]/i', ' ', $text);
    return $text;
}

function remove_html_comments($content = '') {
	return preg_replace('/<!--(.|\s)*?-->/', '', $content);
}
function remove_xml_declaration($content = '') {
    // XML tanımlamasını kaldır
    return preg_replace('/^\s*<\?xml[^>]*\?>\s*/', '', $content);
}

function masked_text($text="", $visible_digits = 4) {
    $masked_number = '';
    $text_length = strlen($text);
    if ($text_length <= $visible_digits) {
        return $text;
    }
    $masked_chars = $text_length - $visible_digits;
    $masked_number = str_repeat('*', $masked_chars);
    $visible_part = substr($text, -$visible_digits);
    return $masked_number . $visible_part;
}

function extract_urls($string) {
    $urlPattern = '/\b((?:https?:\/\/|www\.)[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|\/)))/i';
    preg_match_all($urlPattern, $string, $matches);
    return $matches[0];
}

function strip_tags_opposite($content, $tags) {
    $tagsArray = explode('><', trim($tags, '<>'));
    foreach ($tagsArray as $tag) {
        $content = preg_replace('/<'.$tag.'[^>]*>.*?< *\/'.$tag.'>/is', '', $content);
    }
    return $content;
}


function json_attr($json){
	$json = wp_json_encode($json);
	return esc_attr($json);
}


function camel2Dashes($str, $separator = "-"){
    if (empty($str)) {
        return $str;
    }
    $str = lcfirst($str);
    $str = preg_replace("/[A-Z]/", $separator . "$0", $str);
    return strtolower($str);
}
function dashes2Camel($string, $capitalizeFirstCharacter = false) {
	if (empty($string)) {
        return $string;
    }
    $seperator = instr("-", $string)?"-":"_";
    $str = str_replace($seperator, '', ucwords($string, $seperator));
    if (!$capitalizeFirstCharacter) {
        $str = lcfirst($str);
    }
    return $str;
}

function is_hex_color($val): bool {
    return is_string($val) && preg_match('/^#([a-fA-F0-9]{3}|[a-fA-F0-9]{6})$/', $val);
}