<?php

function kia_user_taxonomy_args( $args, $query_id, $atts ){
    // taxonomy params, this is not supported by WP_User_Query, but we're faking it with pre_user_query
    if ( $atts['taxonomy'] && $atts['terms'] ) {
        $terms = explode("|", $atts['terms'] );
        
        $args['tax_query'] = array(
            array(
                'taxonomy' => $atts['taxonomy'],
                'field'    => 'slug',
                'terms'    => $terms,
            ),
        );
    }
    //print_r($args);

    return $args;
}
add_filter( 'sul_user_query_args', 'kia_user_taxonomy_args', 10, 3 );           


/**
 * Fake a "tax_query"
 * @param obj $query - by reference
 * @param str $query_id
 */
function kia_user_taxonomy_query( $query ) { 
   global $wpdb;

    // fake a tax query
    if ( isset( $query->query_vars['tax_query'] ) && is_array( $query->query_vars['tax_query'] ) ) {

        $sql = get_tax_sql( $query->query_vars['tax_query'], $wpdb->prefix . 'users', 'ID' );
        
        if( isset( $sql['join'] ) ){
            $query->query_from .= $sql['join'];
        }
        
        if( isset( $sql['where'] ) ){
            $query->query_where .= $sql['where'];
        }
        
    }
}
add_action( 'pre_user_query', 'kia_user_taxonomy_query' );


function get_terms_for_user( $user = false, $taxonomy = '', $args = ['fields' => 'all_with_object_id'] ) {

    // Verify user ID
    $user_id = is_object( $user )
        ? $user->ID
        : absint( $user );

    // Bail if empty
    if ( empty( $user_id ) ) {
        return false;
    }

    // Return user terms
    return wp_get_object_terms( $user_id, $taxonomy, $args);
}
