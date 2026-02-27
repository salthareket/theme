<?php

//src/admin/admin.php
//qtranxf_get_admin_page_config()
// for post_type_list support
// add $admin_config["post"]["pages"]["edit.php"]="";

/*
add_filter( 'qtranslate_admin_config', 'qtranxf_add_post_type_support', 10);
function qtranxf_add_post_type_support(){

    
    $config["post"]["pages"]["edit.php"] = "";
    return $config;echo "qtranslate_admin_config";
    global $q_config;
    if ( isset( $q_config['i18n-cache']['admin_page_configs'] ) ) {
        $admin_config = $q_config['i18n-cache']['admin_page_configs'];
    }else{
        $admin_config = $q_config['admin_config'];
    }

$admin_config = apply_filters( 'qtranslate_admin_config', $admin_config);
print_r($admin_config);
return $admin_config;
}

global $q_config;
$admin_config = $q_config['admin_config'];
$admin_config = apply_filters( 'qtranslate_admin_config', array());*/

function qtranxf_setLanguage($lang) {
	global $q_config;
	$q_config['language'] = $lang;
	qtranxf_set_language_cookie($lang);
}

function get_post_type_ml_slug($post_type="", $language=""){
    $data = get_option("qtranslate_module_slugs");
    return $data["post_type_".$post_type][$language];
}

function qtrans_convert_url($lang){
    $url = current_url();
    return qtranxf_convertURL( $url, $lang);    
}



//add_filter('the_title', 'the_title_ml');
function the_title_ml($title) {
    $lang = qtranxf_getLanguage();
    return qtranxf_use(
        $lang,
        $title,
        false,
        false
    );
}

function translateContent($text){
    return qtranxf_use(ml_get_current_language(), $text);
}


add_filter( 'register_post_type_args', 'your_prefix_change_post_type_slug', 10, 2 );
function your_prefix_change_post_type_slug( $args, $post_type ) {
    if(isset($args['labels']['name'])){
        $lang = qtranxf_getLanguage();
        $title = $args['labels']['name'];
        $args['labels']['name'] = qtranxf_use(
            $lang,
            $title,
            false,
            false
        );
    }
    return $args;
}

//add Persian rtl support
function custom_dir_attr($lang){
  if (is_admin()){
     return $lang;
  }
  $dir_attr="";
  if (Data::get("language_rtl")){
      $dir_attr='dir="rtl"';
  }
  return $lang." ".$dir_attr;
}
add_filter('language_attributes','custom_dir_attr');



function get_language_from_url($url="") {
    $lang = get_bloginfo('language');
    foreach (qtranxf_getSortedLanguages() as $language) {
        if (strpos($url, '/' . $language . '/') !== false) {
            $lang = $language;
            continue;
        }
    }
    return $lang;
}


function get_language_slug($lang = "", $type = "", $slug = "") {
    // Gerekli sınıflar yoksa veya parametreler boşsa orijinal slug'ı dön
    if (class_exists("QTX_Module_Slugs") && !empty($slug) && !empty($lang)) {
        global $wpdb;

        // 1. ADIM: Post ID'yi bul (Dinamik tablo ve prepare kullanımı)
        // post_excerpt ve post_type alanlarını güvenli hale getiriyoruz
        $post_id_query = $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_excerpt = %s AND post_type = %s LIMIT 1",
            $slug,
            'acf-' . $type
        );
        $id = $wpdb->get_var($post_id_query);

        if ($id) {
            // 2. ADIM: Meta değerini bul (Dinamik tablo ve prepare kullanımı)
            // meta_key içindeki dil kodu da prepare ile güvenli hale getiriliyor
            $meta_key = 'qtranslate_slug_' . $lang;
            $meta_query = $wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1",
                $id,
                $meta_key
            );
            
            $translated_slug = $wpdb->get_var($meta_query);

            if ($translated_slug) {
                return $translated_slug;
            }
        }
    }
    
    return $slug;
}

//add_filter( 'get_term', "qtrans_term_nane", 9999);
function qtrans_term_nane($term="", $taxonomy=array()){
    $lang = qtranxf_getLanguage();
    $term->name = qtranxf_use($lang, $term->name, false, false);
    return $term;
}

function qtrans_translate($title="", $lang=""){
    return qtranxf_use($lang, $title, false, false);
}



function qtranslate_menu_fixer( $items, $menu, $args ) {
        $menu_order = count($items);
        switch ($menu->name) {
            case "header" :
                if(count( $items ) > 0){
                    foreach ( $items as $key => $item ) {  
                        switch($item->type){
                            case "custom":
                                $title = get_the_title( $item->object_id );
                            break;
                            case "post_type":
                                $title = get_the_title( $item->object_id );
                            break;
                            case "post_type_archive":
                                $title = get_post_type_labels(get_post_type_object( $item->object))->name;
                            break;
                        }
                        $items[$key]->title = $title;
                        $items[$key]->post_title = $title;
                        $items[$key]->name = $title;
                    }
                }
            break;
        }   
        return $items;
}
add_filter( 'wp_get_nav_menu_items', 'qtranslate_menu_fixer', 1, 3 );



if(class_exists("QTX_Module_Slugs") && !is_admin()){

    function qtranslate_rewrite_post_type_slugs($args, $post_type) {
        $current_language = qtranxf_getLanguage();
        $slug_meta_key = 'qtranslate_slug_' . $current_language;
        $slug = get_post_type_qtx_slug($post_type, $slug_meta_key);
        //echo $post_type." - ".$slug."<br>";
        if ($slug) {
            $args['rewrite'] = array('slug' => $slug);
        }
        return $args;
    }
    add_filter('register_post_type_args', 'qtranslate_rewrite_post_type_slugs', 10, 2);

    function get_post_type_qtx_slug($post_type, $meta_key) {
        global $wpdb;
        $query = $wpdb->prepare("
            SELECT pm.meta_value 
            FROM {$wpdb->postmeta} pm
            LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE p.post_type='acf-post-type' and p.post_excerpt = %s AND pm.meta_key = %s
            LIMIT 1
        ", $post_type, $meta_key);
        $result = $wpdb->get_var($query);
        return $result;
    }

}


function get_post_type_qtx_original_slug($post_type, $meta_key) {
}

function qtrans_get_qtx_language_url($language=""){
    global $q_config;
    $current_language = qtranxf_getLanguage();
    $slug_meta_key = 'qtranslate_slug_' . $current_language;
    $slug = getUrlEndpoint(current_url());

    global $wpdb;
    $query = $wpdb->prepare("
            SELECT p.post_excerpt 
            FROM {$wpdb->postmeta} pm
            LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE p.post_type='acf-post-type' AND pm.meta_key = %s AND pm.meta_value = %s
            LIMIT 1
    ", $slug_meta_key, $slug);
    $post_type = $wpdb->get_var($query);
    $post_types = get_post_types([], 'names');
    if(in_array($post_type, $post_types)){
        $lang_slug = get_post_type_qtx_slug($post_type, 'qtranslate_slug_' . $language);
        //return get_site_url()."/".($language==$GLOBALS["language_default"]?"":$language."/").$lang_slug."/";
        return get_site_url()."/".$language."/".$lang_slug."/";
    }else{
        $url = qtranxf_slugs_get_url($language);
        if($language == $q_config['default_language']){
            $url = str_replace(get_site_url(), "", $url);
            $url = get_site_url()."/".$language.$url;
        }
        return $url;
    }
}





function qtranslate_block_content($content) {
    if(ENABLE_MULTILANGUAGE) {
        if(has_blocks($content)){
            $content = qtranxf_use(ml_get_current_language(), $content, false, false);            
        }
    }
    return $content;
}
//add_filter('the_content', 'qtranslate_block_content');










/*
function test_test($var){
    $field=array();
    $languages       = qtranxf_getSortedLanguages( true );
    $values =  QTX_Module_Acf_Extended::decode_language_values( $var );
     foreach ( $languages as $language ){
            $field['value']       = $values[ $language ];

            if ( $field['value'] ) {
                $file = get_post( $field['value'] );
                if ( $file ) {
                    print_r($file);
                }
            }
    }
}

function qtranslate_get_all_files($args = array()){
    $files = array();
    remove_filter('acf/format_value/name=vtt', 'my_acf_format_value', 10,3);
    foreach($GLOBALS["languages"] as $lang){
        $files[] = array(
            "file" => get_field($args["field"], $args["post_id"]),
            "language" => $lang["name"],
            "language_long" => $lang["name_long"],
            "default" => $lang["name"] == $GLOBALS["language_default"]?true:false
        );
    }
    qtranxf_setLanguage( $GLOBALS["language"] );
    add_filter('acf/format_value/name=vtt', 'my_acf_format_value', 10, 3);
    return $files;
}
*/




/*
using acfe dynamic preview please update these lines below: 

path:
qtranslate-xt/modules/acf/src/fields/text.php
qtranslate-xt/modules/acf/src/fields/textarea.php
qtranslate-xt/modules/acf/src/fields/wysiwyg.php

function update_value( $values, $post_id, $field ) {
    add : start
        if(!is_array($values)){
            $values = QTX_Module_Acf_Extended::decode_language_values($values);
        }
    add: end
        if ( is_array( $values ) ) {
           return QTX_Module_Acf_Extended::encode_language_values( $values );
        }
    }


Alttaki hala gecerli olmayabilir, yukarıdaki duzetme yeterli

path:
qtranslate-xt\modules\acf\src\qtx_module_acf_register.php:67

public function encode_language_values( $values ) {
        if(is_array($values)){
            return qtranxf_join_b( $values );
        }else{
            return $values;
        }
}
*/


if (class_exists("acf")) {
    // admin sayfasındaki acf label'ları seçili dilde göstermek için fix code.
    function acf_load_field_translate($field) {
        if (ENABLE_MULTILANGUAGE == "qtranslate_xt" && is_admin()) {
            global $post;
            if(isset($post->ID)){
                if (get_post_type($post->ID) == 'acf-field-group') {
                    return $field;
                }
                $field['label'] = qtranxf_useCurrentLanguageIfNotFoundUseDefaultLanguage($field['label']);  
            }
        }
        return $field;
    }
    add_filter('acf/load_field', 'acf_load_field_translate');

    // ACF options sayfasındaki alanları kaydetmek için filtre
    function load_acf_option_value($value, $post_id, $field) {
        remove_filter('acf/load_value', 'load_acf_option_value', 10, 3);

        $current_lang = qtranxf_getLanguage();
        $default_lang = qtranxf_getSortedLanguages()[0];

        if ($post_id == 'options_'.$current_lang) {

            $option_name = $field['name'];
            $default_option = "options_{$option_name}";
            $default_alt_option = "options_{$default_lang}_{$option_name}";
            $current_option = "options_{$current_lang}_{$option_name}";
            $value = get_option($current_option);

            if (empty($value)) {
                
               global $q_config;
               $q_config['language'] = $default_lang;
               //echo $option_name." > yok aabi<br>";
               $value = QueryCache::get_field($option_name, "options");
               //print_r($value);
               $value = \get_option($default_option);
               //print_r($value);
               //echo "<br>";
               $q_config['language'] = $current_lang;
                /*if (empty($value)) {
                    $value = get_option($default_alt_option);
                }*/
            }
        }
        add_filter('acf/load_value', 'load_acf_option_value', 10, 3);
        return $value;
    }
    add_filter('acf/load_value', 'load_acf_option_value', 10, 3);
}


// qTranslate çeviri fonksiyonu (aktif dili algılar)
function qtranslate_fix($content) {
    if (function_exists('qtranxf_getLanguage') && function_exists('qtranxf_use')) {
        $current_lang = qtranxf_getLanguage(); // Aktif dili al
        return qtranxf_use($current_lang, $content, false, true);
    }
    return $content;
}

// Post başlıklarını çekerken çeviri uygula
add_filter('the_title', 'qtranslate_fix', 10, 1);

// Taxonomy (Kategori, Etiket vs.) başlıklarını çekerken çeviri uygula
add_filter('single_term_title', 'qtranslate_fix', 10, 1);
add_filter('list_cats', 'qtranslate_fix', 10, 1); // Kategori listeleme için

// WordPress opsiyonlarını çekerken çeviri uygula
add_filter('option_blogname', 'qtranslate_fix', 10, 1);
add_filter('option_blogdescription', 'qtranslate_fix', 10, 1);
add_filter('option_my_custom_option', 'qtranslate_fix', 10, 1); // Özel opsiyonlar için

// ACF alanlarını çekerken çeviri uygula
add_filter('acf/load_value', 'qtranslate_fix', 10, 3);
