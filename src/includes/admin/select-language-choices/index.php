<?php

/**
 * ACF Language Field — qTranslate dil seçeneklerini populate eder.
 */

if (function_exists('qtranxf_getSortedLanguages')) {
    add_filter('acf/load_field/name=language', function($field) {
        $field['choices'] = [];
        foreach (qtranxf_getSortedLanguages() as $lang) {
            $field['choices'][$lang] = qtranxf_getLanguageName($lang);
        }
        return $field;
    });
}
