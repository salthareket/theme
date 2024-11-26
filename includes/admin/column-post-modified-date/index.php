<?php

// Admin panelde liste sayfasına yeni bir sütun ekleme
function add_modified_date_column($columns) {
    $columns['modified_date'] = __('Modified', 'default'); // "Modified" terimi default WP stilinde
    return $columns;
}
add_filter('manage_posts_columns', 'add_modified_date_column');
add_filter('manage_pages_columns', 'add_modified_date_column');

// Değiştirilme tarihi sütununa verileri ekleme
function show_modified_date_column_content($column_name, $post_id) {
    if ($column_name === 'modified_date') {
        // Değiştirilme tarihini dd.mm.yyyy formatında göster
        $modified_date = get_post_modified_time('d.m.Y H:i', false, $post_id);
        echo __('Modified', 'default')."<br>".$modified_date;
    }
}
add_action('manage_posts_custom_column', 'show_modified_date_column_content', 10, 2);
add_action('manage_pages_custom_column', 'show_modified_date_column_content', 10, 2);

// Sütunları sıralanabilir hale getirme
function make_modified_date_sortable($columns) {
    $columns['modified_date'] = 'modified';
    return $columns;
}
add_filter('manage_edit-post_sortable_columns', 'make_modified_date_sortable');
add_filter('manage_edit-page_sortable_columns', 'make_modified_date_sortable');
