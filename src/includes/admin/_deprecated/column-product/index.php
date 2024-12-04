<?php

//saran-group
add_filter( 'manage_product_posts_columns', 'product_posts_columns' );
add_action( 'manage_product_posts_custom_column', 'product_posts_custom_column', 10, 2 );
add_filter( 'manage_edit-product_sortable_columns', 'product_posts_custom_column_sortable' );
function product_posts_columns ( $columns ) {
    return array_merge ( $columns, array ( 
        'application_status' => __( 'Application Status' ),
        'client' => __( 'Client' ),
        'expert' => __( 'Expert' ),
        'platform' => __( 'Platform' )
    ));
}
function product_posts_custom_column ( $column, $post_id ) {
        switch ( $column ) {
            case 'application_status':
                echo get_field( 'application_status', $post_id );
            break;
            case 'client':
                $application = new Application($post_id);
                $client = $application->parent->author;
                $client_date = $application->get_session_date($client);
                echo $application->parent->author->get_title()."<br>";
                if($client_date["start"]["date"] == $client_date["end"]["date"]){
                   echo $client_date["start"]["date"]." ".$client_date["start"]["time"]."-".$client_date["end"]["time"];
                }else{
                   echo $client_date["start"]["date"]." ".$client_date["start"]["time"]."<br>";
                   echo $client_date["end"]["date"]." ".$client_date["end"]["time"];
                }
            break;
            case 'expert':
                $application = new Application($post_id);
                $expert = $application->author;
                $expert_date = $application->get_session_date($expert);
                echo $application->author->get_title()."<br>";
                if($expert_date["start"]["date"] == $expert_date["end"]["date"]){
                   echo $expert_date["start"]["date"]." ".$expert_date["start"]["time"]."-".$expert_date["end"]["time"];
                }else{
                   echo $expert_date["start"]["date"]." ".$expert_date["start"]["time"]."<br>";
                   echo $expert_date["end"]["date"]." ".$expert_date["end"]["time"]."<br>";
                }
            break;
            case 'platform':
                $application = new Application($post_id);
                echo $application->_platform;
            break;
        }
}
function product_posts_custom_column_sortable( $columns ) {
        $columns['application_status'] = 'application_status';
        return $columns;
}