<?php

//namespace WP_Rocket\Helpers\static_files\exclude\optimized_css_cpt_taxonomy;

defined( 'ABSPATH' ) or die();


add_filter( 'rocket_defer_inline_exclusions', function( $inline_exclusions_list ) {
  if ( ! is_array( $inline_exclusions_list ) ) {
    $inline_exclusions_list = array();
  }
  $inline_exclusions_list[] = 'jquery';
  $inline_exclusions_list[] = 'jquery-js';
  $inline_exclusions_list[] = 'site_config_vars*$';
  $inline_exclusions_list[] = 'site_config_vars';
  $inline_exclusions_list[] = 'site_config_vars-js';
  $inline_exclusions_list[] = 'site_config_vars-js-extra';
  $inline_exclusions_list[] = 'site_config_vars-js-after';
  $inline_exclusions_list[] = 'preload_image';
  $inline_exclusions_list[] = 'map_data';
  return $inline_exclusions_list;
});



function wp_rocket_post_type_url_regex(){
    global $wpdb;
    $urls = [];
    $excluded_post_types = get_option("options_exclude_post_types_from_cache");
    if($excluded_post_types){
        foreach($excluded_post_types as $post_type){
            $post_id = $wpdb->get_var( $wpdb->prepare(
                "
                SELECT ID 
                FROM $wpdb->posts 
                WHERE post_type = %s 
                AND post_status = 'publish' 
                LIMIT 1
                ",
                $post_type
            ));
            $post_id =  $post_id ? (int) $post_id : null;
            if (!is_wp_error($post_id)) {

                $url = get_permalink($post_id);
                $basename = basename($url);
                $path = str_replace(home_url('/'), "",$url);
                $path = str_replace($basename."/", "", $path);
                $path = str_replace($basename, "", $path); 
                $urls[] = getSiteSubfolder().$path."(.*)";
                
                if(ENABLE_MULTILANGUAGE){
                    switch (ENABLE_MULTILANGUAGE) {
                        case 'polylang':
                            $post_language = pll_get_post_language($post_id, "slug");
                            foreach($GLOBALS["languages"] as $language){
                                if($language["name"] != $post_language){
                                    $post_id = pll_get_post( $post_id, $language["name"] );
                                    $url = get_permalink($post_id);
                                    $basename = basename($url);
                                    $path = str_replace(home_url('/'), "",$url);
                                    $path = str_replace($basename."/", "", $path);
                                    $path = str_replace($basename, "", $path); 
                                    if(!empty($path)){
                                      $urls[] = getSiteSubfolder().$path."(.*)";                                      
                                    }
                                }
                            }
                        break;
                    }
                }
            }
        }
    }
    return $urls;
}

function wp_rocket_taxonomy_url_regex(){
    global $wpdb;
    $urls = [];
    $excluded_taxonomies = get_option("options_exclude_taxonomies_from_cache");
    $taxonomy_prefix_remove = (get_option("options_taxonomy_prefix_remove"));
    if ($excluded_taxonomies) {
        foreach ($excluded_taxonomies as $taxonomy) {
            $prefix_remove = false;
            if(in_array($taxonomy, $taxonomy_prefix_remove)){
                $prefix_remove = true;
                $term_ids = $wpdb->get_col($wpdb->prepare(
                    "
                    SELECT term_id 
                    FROM $wpdb->term_taxonomy 
                    WHERE taxonomy = %s
                    ",
                    $taxonomy
                ));               
             }else{
                $term_ids = [];
                $term_ids[] = $wpdb->get_var( $wpdb->prepare(
                    "
                    SELECT term_id 
                    FROM $wpdb->term_taxonomy 
                    WHERE taxonomy = %s 
                    LIMIT 1
                    ",
                    $taxonomy
                ));
            }
            if ($term_ids) {
                $term_ids = array_map('intval', $term_ids);
                foreach ($term_ids as $term_id) {
                    $term_link = get_term_link($term_id, $taxonomy);
                    if (!is_wp_error($term_link)) {
                        $basename = basename($term_link);
                        $path = str_replace(home_url('/'), "", $term_link);
                        if(!$prefix_remove){
                            $path = str_replace($basename . "/", "", $path);
                            $path = str_replace($basename, "", $path);
                            $urls[] = getSiteSubfolder() . $path . "(.*)";                            
                        }else{
                            $urls[] = getSiteSubfolder() .$path;
                        }
                    }

                    // Multilanguage desteği varsa işleyelim
                    if (ENABLE_MULTILANGUAGE) {
                        switch (ENABLE_MULTILANGUAGE) {
                            case 'polylang':
                                $term_language = pll_get_term_language($term_id, "slug");

                                foreach ($GLOBALS["languages"] as $language) {
                                    if ($language["name"] != $term_language) {
                                        $term_id_translated = pll_get_term($term_id, $language["name"]);
                                        $term_link = get_term_link($term_id_translated, $taxonomy);

                                        if (!is_wp_error($term_link)) {
                                            $basename = basename($term_link);
                                            $path = str_replace(home_url('/'), "", $term_link);
                                            if(!$prefix_remove){
                                                $path = str_replace($basename . "/", "", $path);
                                                $path = str_replace($basename, "", $path);
                                                if (!empty($path)) {
                                                    $urls[] = getSiteSubfolder() . $path . "(.*)";
                                                }
                                            }else{
                                                 $urls[] = getSiteSubfolder() . $path;
                                            }
                                        }
                                    }
                                }
                            break;
                        }
                    }
                }
            }
        }
    }
    return $urls;
}


function modify_cache_reject_urls( $urls, $option ) {
    $urls = !$urls ? [] : $urls;
    $urls = array_merge($urls, wp_rocket_post_type_url_regex());
    $urls = array_merge($urls, wp_rocket_taxonomy_url_regex());
    error_log(json_encode($urls));
    return $urls;
}
add_filter( 'pre_get_rocket_option_cache_reject_uri', 'modify_cache_reject_urls', 10, 2 );

function wprocket_is_cached() {
    if (defined("WP_ROCKET_VERSION")) {
        foreach (headers_list() as $header) {
            if (strpos($header, 'x-rocket-nginx-serving-static') !== false) {
                error_log("wprocket_is_cached();");
                return true;
            }
        }        
    }
    return false;
}
function is_wp_rocket_crawling() {
    if (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'WP Rocket') !== false) {
        error_log("is_wp_rocket_crawling();");
        return true; // WP Rocket bu sayfayı önbelleğe almak için ziyaret ediyor
    }
    return false;
}

add_filter('rocket_buffer', function ($buffer) {
    if (!is_admin()) {
        // Sayfanın en altına şunu basar:
        return str_replace('"cached":""', '"cached":"1"', $buffer);
    }
    return $buffer;
});