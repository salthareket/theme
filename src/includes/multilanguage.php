<?php



/**
 * Mevcut Aktif Dili Döndürür
 */
function ml_get_current_language() {

    $language = Data::get("language");
    if (!empty($language)) {
        return $language;
    }

    if (!defined('ENABLE_MULTILANGUAGE') || !ENABLE_MULTILANGUAGE) {
        return substr(get_locale(), 0, 2); 
    }

    static $current_lang = null; // Cacheleyelim, her çağırdığında switch dönmesin
    if ($current_lang !== null) return $current_lang;

    switch (ENABLE_MULTILANGUAGE) {
        case "qtranslate-xt":
            $current_lang = function_exists('qtranxf_getLanguage') ? qtranxf_getLanguage() : '';
            break;
        case "wpml":
            $current_lang = defined('ICL_LANGUAGE_CODE') ? ICL_LANGUAGE_CODE : '';
            break;
        case "polylang":
            $current_lang = function_exists('pll_current_language') ? pll_current_language() : '';
            break;
        default:
            $current_lang = get_locale();
            break;
    }

    $current_lang = substr(empty($current_lang)?"en":$current_lang, 0, 2);

    return $current_lang ?: 'en';
}

/**
 * Varsayılan (Default) Dili Döndürür
 */
function ml_get_default_language() {
    
    $language_default = Data::get("language_default");
    if (!empty($language_default)) {
        return $language_default;
    }
    
    if (!defined('ENABLE_MULTILANGUAGE') || !ENABLE_MULTILANGUAGE) {
        return substr(get_locale(), 0, 2);
    }

    static $default_lang = null;
    if ($default_lang !== null) return $default_lang;

    switch (ENABLE_MULTILANGUAGE) {
        case "qtranslate-xt":
            global $q_config;
            $default_lang = isset($q_config['default_language']) ? $q_config['default_language'] : 'en';
            break;
        case "wpml":
            $default_lang = apply_filters('wpml_default_language', null);
            break;
        case "polylang":
            $default_lang = function_exists('pll_default_language') ? pll_default_language() : '';
            break;
        default:
            $default_lang = get_locale();
            break;
    }

    $default_lang = substr(empty($default_lang)?"en":$default_lang, 0, 2);

    return $default_lang ?: 'en';
}