<?php

// ACF yüklü değilse get_field için fallback fonksiyon
if (!function_exists('get_field') && !class_exists("ACF")) {
    function get_field($key, $post_id = false) {
        // Eğer $post_id 'option' ise get_option() fonksiyonunu kullan
        if ($post_id === 'option' || $post_id === 'options') {
            return get_option($key);
        }

        // Eğer $post_id bir post ID ise (numeric), post meta verisini getir
        if (is_numeric($post_id)) {
            return get_post_meta($post_id, $key, true);
        }

        // Eğer bir term ID ise (term metaları için kontrol)
        if (strpos($post_id, 'term_') === 0) {
            $term_id = str_replace('term_', '', $post_id);
            return get_term_meta($term_id, $key, true);
        }

        // Eğer bir user ID ise (user metaları için kontrol)
        if (strpos($post_id, 'user_') === 0) {
            $user_id = str_replace('user_', '', $post_id);
            return get_user_meta($user_id, $key, true);
        }

        // Eğer bir comment ID ise (comment metaları için kontrol)
        if (strpos($post_id, 'comment_') === 0) {
            $comment_id = str_replace('comment_', '', $post_id);
            return get_comment_meta($comment_id, $key, true);
        }

        // Varsayılan olarak null döndür
        return null;
    }
}

/*
// ACF yüklü değilse the_field için fallback fonksiyon
if (!function_exists('the_field') && !class_exists("ACF")) {
    function the_field($key, $post_id = false) {
        $value = get_field($key, $post_id);
        
        if ($value) {
            echo esc_html($value);
        } else {
            echo '';
        }
    }
}*/