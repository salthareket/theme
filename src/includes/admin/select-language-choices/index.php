<?php

function acf_load_language_choices( $field ) {
    $field['choices'] = array();
    foreach(qtranxf_getSortedLanguages() as $language) {
        $field['choices'][$language] = qtranxf_getLanguageName($language);
    }   
    return $field;
}
if(function_exists("qtranxf_getSortedLanguages")){
    add_filter('acf/load_field/name=language', 'acf_load_language_choices');
}