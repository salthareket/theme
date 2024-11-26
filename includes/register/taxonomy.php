<?php
/*register_taxonomy('employment-type', array('user'), array(
        'public'        => true,
        'single_value' => false,
        'show_admin_column' => true,
        'hierarchical'      => true,
        'labels'        =>array(
            'name'                      =>'Employment Types',
            'singular_name'             =>'Employment Type',
            'menu_name'                 =>'Employment Type',
            'search_items'              =>'Search Employment Type',
            'popular_items'             =>'Popular Employment Types',
            'all_items'                 =>'All Employment Types',
            'edit_item'                 =>'Edit Employment Type',
            'update_item'               =>'Update Employment Type',
            'add_new_item'              =>'Add New Employment Type',
            'new_item_name'             =>'New Employment Type Name',
            'separate_items_with_commas'=>'Separate Employment Types with commas',
            'add_or_remove_items'       =>'Add or remove Employment Type',
            'choose_from_most_used'     =>'Choose from the most popular Employment Types',
        ),
        'rewrite'       =>array(
            'with_front'                => true,
            'slug'                      =>'author/employment-type',
        ),
        'capabilities'  => array(
            'manage_terms'              =>'edit_users',
            'edit_terms'                =>'edit_users',
            'delete_terms'              =>'edit_users',
            'assign_terms'              =>'read',
        ),
));*/

   
















// Contact Categories
$labels = array(
        'name'                      =>'Contact Types',
        'singular_name'             =>'Contact Type',
        'menu_name'                 =>'Contact Type',
        'search_items'              =>'Search Contact Type',
        'popular_items'             =>'Popular Contact Types',
        'all_items'                 =>'All Contact Types',
        'edit_item'                 =>'Edit Contact Type',
        'update_item'               =>'Update Contact Type',
        'add_new_item'              =>'Add New Contact Type',
        'new_item_name'             =>'New Contact Type Name',
        'separate_items_with_commas'=>'Separate Contact Type with commas',
        'add_or_remove_items'       =>'Add or remove Contact Type',
        'choose_from_most_used'     =>'Choose from the most popular Contact Types',
);
$args = array(
        'public'        => true,
        'single_value' => false,
        'show_admin_column' => true,
        'labels'        => $labels,
        'hierarchical' => true,
        'rewrite'       =>  array(
            'with_front'                => true,
            'slug'                      =>'contact/contact-type',
        ),
        'capabilities'  => array(
            'manage_terms'              =>'edit_users',
	        'edit_terms'                =>'edit_users',
	        'delete_terms'              =>'edit_users',
	        'assign_terms'              =>'read',
        ),
);
register_taxonomy('contact-type', array('contact'), $args);

add_action("init", function(){
        if (empty(term_exists('main', 'contact-type'))) {
            $term = wp_insert_term('Ana Lokasyon', 'contact-type', array(
               'description' => '',
               'slug' => 'main',
               'parent' => 0
            ));
            if(!is_wp_error($term) || empty(get_option( 'contact_type_main'))){
                add_term_meta( $term["term_id"], 'delete-protect', true, true );
                add_option( 'contact_type_main', $term["term_id"]);
            }
        }else{
            $term = get_term_by("slug", "main", "contact-type");
            if(empty(get_option( 'contact_type_main'))){
                add_term_meta( $term->term_id, 'delete-protect', true, true );
                add_option( 'contact_type_main', $term->term_id);
            }
        }
        if (empty(term_exists('standard', 'contact-type'))) {
            $term = wp_insert_term('Standart Lokasyon', 'contact-type', array(
               'description' => '',
               'slug' => 'standard',
               'parent' => 0
            ));
            if(!is_wp_error($term) || empty(get_option( 'contact_type_standard'))){
                add_term_meta( $term["term_id"], 'delete-protect', true, true );
                add_option( 'contact_type_standard', $term["term_id"]);
            }
        }else{
            $term = get_term_by("slug", "standard", "contact-type");
            if(empty(get_option( 'contact_type_standard'))){
                add_term_meta( $term->term_id, 'delete-protect', true, true );
                add_option( 'contact_type_standard', $term->term_id);
            }
        }
        add_filter( 'create_term', 'on_save_contact_type', 10, 3 );
        add_filter( 'edit_term', 'on_save_contact_type', 10, 3 );
        add_filter( 'edited_term', 'on_save_contact_type', 10, 3 );
        add_filter( 'saved_term', 'on_save_contact_type', 10, 3 );
}, 999);

function on_save_contact_type($term_id, $tt_id, $taxonomy) {
    if($taxonomy != "contact-type"){
        return;
    }
    $term = get_term($term_id, $taxonomy);
    if(empty(get_option( 'contact_type_'.$term->slug))){
        add_term_meta( $term->term_id, 'delete-protect', true, true );
        add_option( 'contact_type_'.$term->slug, $term->term_id);
    }
}









// prevent delete protected terms

add_action( 'category_edit_form', 'remove_delete_edit_term_form', 10, 2);
function remove_delete_edit_term_form ($term, $taxonomy){
    $delete_protected = get_term_meta ($term->term_id, 'delete-protect', true);
    if ($delete_protected){
        echo '<style type="text/css">#tag-'.$term->term_id.' .delete {display: none !important;}</style>';
    }
}
add_action ('pre_delete_term', 'taxonomy_delete_protection', 10, 1 );
function taxonomy_delete_protection ( $term_id ){
    $delete_protected = get_term_meta($term_id, 'delete-protect', true);
    if ($delete_protected){
        $term = get_term ($term_id);
        $error = new WP_Error ();
        $error->add (1, '<h2>Delete Protection Active!</h2>You cannot delete "' . $term->name . '"<br><a href="javascript:history.back()">Go Back</a>');
        wp_die ($error);
    }
}