<?php
function get_field_wpml( $field_key, $post_id = false, $format_value = true ) {
    $is_cascade   = $post_id == 'option' && $format_value == true ? true : false;
    $format_value = $post_id == 'option' ? true : $format_value; // force $format_value = true for option
    if (!function_exists('icl_get_languages')) {
        return get_field( $field_key, $post_id, $format_value );
    }else{
        // see : http://support.advancedcustomfields.com/forums/topic/wpml-and-acf-options/
        global $sitepress;
        // get field for default language
        if ( ( $sitepress->get_default_language() == ICL_LANGUAGE_CODE ) && ( $ret = get_field( $field_key, $post_id, $format_value ) ) ) {
           return $ret;
        }
        // get field for current language
        elseif ( $ret = get_field( $field_key . '_' . ICL_LANGUAGE_CODE, $post_id, $format_value ) ) {
            return $ret;
        }
        // get field when if not exists for locale by cascade
        elseif ( $is_cascade ) {
            return get_field( $field_key, $post_id, $format_value );
        }
    }
    return false;
}
function have_rows_wpml( $field_key, $post_id = false ) {
    global $sitepress;
    if ( $sitepress->get_default_language() == ICL_LANGUAGE_CODE ) {
       return have_rows( $field_key, $post_id );
    }
    return have_rows( $field_key . '_' . ICL_LANGUAGE_CODE, $post_id );
}