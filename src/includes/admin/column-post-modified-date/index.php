<?php

/**
 * Admin Post/Page Table — Modified Date column (sortable).
 */

add_filter('manage_posts_columns', 'admin_add_modified_date_column');
add_filter('manage_pages_columns', 'admin_add_modified_date_column');
function admin_add_modified_date_column($columns) {
    $columns['modified_date'] = __('Modified', 'default');
    return $columns;
}

add_action('manage_posts_custom_column', 'admin_show_modified_date_column', 10, 2);
add_action('manage_pages_custom_column', 'admin_show_modified_date_column', 10, 2);
function admin_show_modified_date_column($column_name, $post_id) {
    if ($column_name !== 'modified_date') return;
    echo __('Modified', 'default') . '<br>' . get_post_modified_time('d.m.Y H:i', false, $post_id);
}

add_filter('manage_edit-post_sortable_columns', 'admin_make_modified_date_sortable');
add_filter('manage_edit-page_sortable_columns', 'admin_make_modified_date_sortable');
function admin_make_modified_date_sortable($columns) {
    $columns['modified_date'] = 'modified';
    return $columns;
}
