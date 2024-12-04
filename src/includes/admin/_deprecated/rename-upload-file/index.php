<?php

//rename media file name to sanitized post title
function custom_upload_filter( $file ) {
    if ( ! isset( $_REQUEST['post_id'] ) ) {
        return $file;
    }
    $id           = intval( $_REQUEST['post_id'] );
    $parent_post  = get_post( $id );
    $post_name    = sanitize_title( $parent_post->post_title );
    //$file['name'] = $post_name . '-' . $file['name'];
    $file['name'] = 'img-'. $post_name . '.' . mime2ext($file['type']);
    return $file;
}
//add_filter( 'wp_handle_upload_prefilter', 'custom_upload_filter' );