<?php

// Thumbnail sütunu term listesine checkbox'tan sonra ekle
add_filter('manage_edit-category_columns', function($columns) {
    $new_columns = [];
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'cb') { // Checkbox sütununu kontrol et
            $new_columns['thumbnail'] = __('Thumbnail'); // Thumbnail sütununu checkbox'tan sonra ekle
        }
    }
    return $new_columns;
});

// Term listesi için thumbnail içeriği
add_filter('manage_category_custom_column', function($content, $column_name, $term_id) {
    if ($column_name === 'thumbnail') {
        $thumbnail_id = get_term_meta($term_id, 'thumbnail_id', true);
        if ($thumbnail_id) {
            $image_url = wp_get_attachment_image_url($thumbnail_id, 'thumbnail');
            $content = '<img src="' . esc_url($image_url) . '" style="width:75px;height:auto;border-radius:6px;">';
        } else {
            $content = __('No Thumbnail');
        }
    }
    return $content;
}, 10, 3);

// Thumbnail sütununu post listesine checkbox'tan sonra ekle
add_filter('manage_post_posts_columns', 'add_thumbnail_column_to_posts');
add_filter('manage_page_posts_columns', 'add_thumbnail_column_to_posts');
function add_thumbnail_column_to_posts($columns) {
    $new_columns = [];
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'cb') { // Checkbox sütununu kontrol et
            $new_columns['thumbnail'] = __('Thumbnail'); // Thumbnail sütununu checkbox'tan sonra ekle
        }
    }
    return $new_columns;
}

// Post listesi için thumbnail içeriği
add_action('manage_posts_custom_column', 'add_thumbnail_to_post_column', 10, 2);
add_action('manage_pages_custom_column', 'add_thumbnail_to_post_column', 10, 2);
function add_thumbnail_to_post_column($column, $post_id) {
    if ($column === 'thumbnail') {
        if (has_post_thumbnail($post_id)) {
            $image_url = get_the_post_thumbnail_url($post_id, 'thumbnail');
            echo '<img src="' . esc_url($image_url) . '" style="width:75px;height:auto;border-radius:6px;">';
        } else {
            echo __('No Thumbnail');
        }
    }
}
