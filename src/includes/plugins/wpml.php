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

add_action('current_screen', function () {
    if (!is_admin() || !function_exists('wpml_get_default_language')) return;

    $screen = get_current_screen();
    $default_lang = apply_filters('wpml_default_language', null);
    $current_lang = apply_filters('wpml_current_language', null);

    if ($default_lang === $current_lang) return;

    // === Post Type listeleri ===
    if ($screen && $screen->base === 'edit') {
        add_filter('the_title', function ($title, $post_id) use ($default_lang) {
            if (get_post_type($post_id) === 'revision') return $title;

            $default_post_id = apply_filters('wpml_object_id', $post_id, get_post_type($post_id), false, $default_lang);
            if ($default_post_id && $default_post_id != $post_id) {
                $default_title = get_the_title($default_post_id);
                if ($default_title && $default_title !== $title) {
                    $title .= ' (' . $default_title . ')';
                }
            }

            return $title;
        }, 10, 2);
    }

    // === Taxonomy (term) listeleri ===
    if ($screen && $screen->base === 'edit-tags') {
        add_filter('get_terms', function ($terms, $taxonomies, $args, $term_query) use ($default_lang, $current_lang) {
            foreach ($terms as &$term) {
                $default_term_id = apply_filters('wpml_object_id', $term->term_id, $term->taxonomy, false, $default_lang);

                if (!$default_term_id || $default_term_id == $term->term_id) continue;

                $default_term = get_term($default_term_id, $term->taxonomy);
                if ($default_term && $default_term->name !== $term->name) {
                    $term->name .= ' (' . $default_term->name . ')';
                }
            }
            return $terms;
        }, 10, 4);
    }
});
