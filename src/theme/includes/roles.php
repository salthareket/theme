<?php
/*
if ( !function_exists( 'populate_roles' ) ) {
  require_once( ABSPATH . 'wp-admin/includes/schema.php' );
}
populate_roles();
*/


function wps_remove_role() {
    remove_role( 'editor' );
    remove_role( 'contributor' );
    remove_role( 'subscriber' );
    remove_role( 'wpseo_manager' );
    remove_role( 'wpseo_editor' );
    remove_role( 'translator' );
    remove_role( 'shop_manager' );
    remove_role( 'translator' );
    remove_role( 'client' );
}
add_action( 'init', 'wps_remove_role' );


function wps_add_role() {
    
    /*add_role( 'client', 'Client', 
            array(
                'read' => 1
            )
    );
    add_role( 'default', 'Default', 
            array(
                'read' => 1
            )
    );*/
}
add_action( 'init', 'wps_add_role' );

add_filter('pre_option_default_role', function($default_role){
    // You can also add conditional tags here and return whatever
    return 'default'; // This is changed
    return $default_role; // This allows default
});



