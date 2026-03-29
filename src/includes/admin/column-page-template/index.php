<?php

/**
 * Admin Pages Table — Template column.
 */

add_filter('manage_pages_columns', function($columns) {
    $columns['col_template'] = 'Template';
    return $columns;
}, 10, 1);

add_action('manage_pages_custom_column', function($column, $post_id) {
    if ($column === 'col_template') {
        echo esc_html(basename(get_page_template()));
    }
}, 10, 2);
