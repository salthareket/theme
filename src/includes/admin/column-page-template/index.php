<?php

add_filter( 'manage_pages_columns', 'table_template_columns', 10, 1 );
add_action( 'manage_pages_custom_column', 'table_template_column', 10, 2 );
function table_template_columns( $columns ) {
    $custom_columns = array(
        'col_template' => 'Template'
    );
    $columns = array_merge( $columns, $custom_columns );
    return $columns;
}
function table_template_column( $column, $post_id ) {
    if ( $column == 'col_template' ) {
        echo basename( get_page_template() );
    }
}