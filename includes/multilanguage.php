<?php

//multilanguage
function ml_get_current_language(){
    $language = "";
    if(ENABLE_MULTILANGUAGE){
        switch(ENABLE_MULTILANGUAGE){
            case "qtranslate-xt" :
                $language = qtranxf_getLanguage();
            break;

            case "wpml" :
               $language = ICL_LANGUAGE_CODE;
            break;

            case "polylang" :
                $language = pll_current_language();
            break;

            default : 
                $default_lang = get_option('WPLANG');
                if (empty($default_lang)) {
                    $language = 'en';
                } else {
                    $language = substr($default_lang, 0, 2);
                }
            break;
        }
    }
    return $language;
}

function ml_get_default_language(){
    $language = "";
    if(ENABLE_MULTILANGUAGE){
        switch(ENABLE_MULTILANGUAGE){
            case "qtranslate-xt" :
                global $q_config;
                $language = $q_config['default_language'];
            break;

            case "wpml" :
                $language = apply_filters( 'wpml_default_language', NULL );
            break;

            case "polylang" :
                $language = pll_default_language();
            break;

            default : 
                $default_lang = get_option('WPLANG');
                if (empty($default_lang)) {
                    $language = 'en';
                } else {
                    $language = substr($default_lang, 0, 2);
                }
            break;
        }
    }
    return $language;
}