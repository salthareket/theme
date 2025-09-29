<?php 

function custom_rewrite_rules() {
    flush_rewrite_rules();

    //$home_id = get_option('page_on_front');

    /*add_rewrite_rule(
        '^basin-bulteni/arsiv/?$',
        'index.php?post_type=basin-bulteni&action=arsiv',
        'top'
    );*/

    if(ENABLE_MEMBERSHIP){
        global $wp_roles;
        if ( ! isset( $wp_roles ) ){
            $wp_roles = new WP_Roles();
        }
        $roles = implode("|", array_keys($wp_roles->get_names()));
        add_rewrite_rule(
            '^('.$roles.')/([^/]+)/?$',
            'index.php?author_name=$matches[2]',
            'top'
        );        
    }

}
add_action('init', 'custom_rewrite_rules');




add_filter( 'author_link', 'wpse17106_author_link', 10, 2 );
function wpse17106_author_link( $link, $author_id ){
    //$user = new User($author_id);
    //$link = str_replace( 'author', $user->get_role(), $link );
    $role = get_user_role($author_id);
    $link = str_replace( 'author', $role, $link );
    return $link;
}