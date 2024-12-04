<?php

/**
 * Add ACF thumbnail columns to Linen Category custom taxonomy
 */
function add_thumbnail_columns($columns) {
    $columns['image'] = __('Thumbnail');
    $new = array();
    foreach($columns as $key => $value) {
        if ($key=='name') // Put the Thumbnail column before the Name column
            $new['image'] = 'Thumbnail';
        $new[$key] = $value;
    }
    return $new;
}
//add_filter('manage_edit-product-color_columns', 'add_thumbnail_columns');

/**
 * Output ACF thumbnail content in Linen Category custom taxonomy columns
 */
function thumbnail_columns_content($content, $column_name, $term_id) {
    if ('image' == $column_name) {
        $term = get_term($term_id);
        $linen_thumbnail_var = get_field('image', $term);
        $content = '<img src="'.$linen_thumbnail_var.'" width="60" />';
    }
    return $content;
}
//add_filter('manage_product-color_custom_column' , 'thumbnail_columns_content', 10, 3);