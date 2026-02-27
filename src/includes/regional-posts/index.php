<?php
add_action( 'acf/include_fields', function() {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) {
        return;
    }

    $regional_post_settings = get_option("options_regional_post_settings");//get_field("regional_post_settings", "option");
    if($regional_post_settings){
        $regional_post_types = array_map(function($item) {
            return $item["post_type"];
        }, $regional_post_settings);
        
        if($regional_post_types){
            register_taxonomy('region', $regional_post_types, array(
                'public'        => true,
                'single_value' => false,
                'show_admin_column' => true,
                'labels'        =>array(
                    'name'                      =>'Regions',
                    'singular_name'             =>'Region',
                    'menu_name'                 =>'Regions',
                    'search_items'              =>'Search Regions',
                    'popular_items'             =>'Popular Regions',
                    'all_items'                 =>'All Regions',
                    'edit_item'                 =>'Edit Region',
                    'update_item'               =>'Update Region',
                    'add_new_item'              =>'Add New Proficiency Level',
                    'new_item_name'             =>'New Region',
                    'separate_items_with_commas'=>'Separate Regions with commas',
                    'add_or_remove_items'       =>'Add or remove Region',
                    'choose_from_most_used'     =>'Choose from the most popular Region',
                ),
                'rewrite'       =>array(
                    'with_front'                => false
                ),
                'capabilities'  => array(
                    'manage_terms'              =>'edit_posts',
                    'edit_terms'                =>'edit_posts',
                    'delete_terms'              =>'edit_posts',
                    'assign_terms'              =>'read',
                ),
            ));     
     
            acf_add_local_field_group( array(
                'key' => 'group_646230ff021b6',
                'title' => 'Region Settings',
                'fields' => array(
                    array(
                        'key' => 'field_646230ff1262b',
                        'label' => 'Country',
                        'name' => 'country',
                        'aria-label' => '',
                        'type' => 'select',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array(
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ),
                        'acfe_save_meta' => 0,
                        'choices' => array(),
                        'default_value' => array(
                        ),
                        'return_format' => 'value',
                        'multiple' => 1,
                        'allow_null' => 0,
                        'ui' => 1,
                        'ajax' => 0,
                        'placeholder' => '',
                        'allow_custom' => 0,
                        'search_placeholder' => '',
                    ),
                ),
                'location' => array(
                    array(
                        array(
                            'param' => 'taxonomy',
                            'operator' => '==',
                            'value' => 'region',
                        ),
                    ),
                ),
                'menu_order' => 0,
                'position' => 'acf_after_title',
                'style' => 'default',
                'label_placement' => 'top',
                'instruction_placement' => 'label',
                'hide_on_screen' => array(
                    0 => 'block_editor',
                    1 => 'the_content',
                    2 => 'excerpt',
                    3 => 'discussion',
                    4 => 'comments',
                    5 => 'format',
                    6 => 'featured_image',
                    7 => 'categories',
                    8 => 'tags',
                    9 => 'send-trackbacks',
                ),
                'active' => true,
                'description' => '',
                'show_in_rest' => 0,
                'acfe_display_title' => '',
                'acfe_autosync' => array(
                    0 => 'json',
                ),
                'acfe_form' => 0,
                'acfe_meta' => '',
                'acfe_note' => '',
            ));
        }        
    }

});

function regional_posts_prequery($query){

    if (is_admin()) {
        return $query;
    }

    $post_type = $query->get("post_type");

    if ($query->is_search && $query->is_main_query()) {
        if(ENABLE_REGIONAL_POSTS){
            //regions
            $tax_query = $query->get( 'tax_query' );
            if ( ! is_array( $tax_query ) ) {
                $tax_query = array();
            }
            $tax_query["relation"] = "AND";
            $tax_query[] = array(
                'taxonomy' => 'region',
                'field' => 'term_id',
                'terms' => Data::get("site_config.user_region"),
                'operator' => 'IN'
            );
            $query->set( 'tax_query', $tax_query );
        }
    }

    if(!is_admin() && $query->is_main_query() && ENABLE_REGIONAL_POSTS){
        $regional_post_settings = get_option("options_regional_post_settings");//get_field("regional_post_settings", "option");

        $regional_post_types = array_map(function($item) {
            return $item["post_type"];
        }, $regional_post_settings);

        $regional_taxonomies = array_map(function($item) {
            return $item["taxonomy"];
        }, $regional_post_settings);
        
        $hasMatchingTaxonomy = false;
        foreach ($regional_taxonomies as $regional_taxonomy) {
            if (is_tax($regional_taxonomy)) {
                $hasMatchingTaxonomy = true;
                break;
            }
        }

        if( in_array($post_type, $regional_post_types) || ($regional_taxonomies && (is_archive() && $hasMatchingTaxonomy)) ){
            $tax_query = $query->get( 'tax_query' );
            if ( ! is_array( $tax_query ) ) {
                $tax_query = array();
            }
            $tax_query["relation"] = "AND";
            $tax_query[] = array(
                'taxonomy' => 'region',
                'field' => 'term_id',
                'terms' => Data::get("site_config.user_region"),
                'operator' => 'IN'
            );
            $query->set( 'tax_query', $tax_query );
        }
    }

    return $query;
}
add_action("pre_get_posts", "regional_posts_prequery");



function after_get_terms($terms, $taxonomies, $args, $term_query) {
    if(!is_admin() && ENABLE_REGIONAL_POSTS){

        $regional_post_settings = get_option("options_regional_post_settings");//get_field("regional_post_settings", "option");

        $regional_taxonomies = array_map(function($item) {
            return $item["taxonomy"];
        }, $regional_post_settings);

        $hasMatchingTaxonomy = false;
        foreach ($regional_taxonomies as $regional_taxonomy) {
            if (in_array($regional_taxonomy, $taxonomies)) {
                $hasMatchingTaxonomy = true;
                break;
            }
        }

        if($hasMatchingTaxonomy){
            $remove = array();
            //remove_action("pre_get_posts", "query_all_posts");
            remove_filter( "get_terms", "after_get_terms", 10, 4 );
            foreach($terms as $key => $term){
                $term = new Term($term);
                if(!$term->get_country_post_count()){
                   $remove[] = $key;
                }
            }
            if($remove){
                foreach($remove as $item){
                    unset($terms[$item]);
                }
            }
            //add_action("pre_get_posts", "query_all_posts");
            add_filter( "get_terms", "after_get_terms", 10, 4 );
        }        
    }
    return $terms; 
}
add_filter( "get_terms", "after_get_terms", 10, 4 );


function get_region_by_country_code($code=""){
    if(!ENABLE_REGIONAL_POSTS){
        return;
    }
    $args = array(
        "taxonomy" => "region",
        'hide_empty' => false,
        "meta_query" => array(
            array(
                "key" => "country",
                "value" => serialize(strval(strtoupper($code))),
                "compare" => "LIKE"
            )
        )
    );
    $region = Timber::get_terms($args);
    //print_r($region);
    if ( $region ) {
        if (!is_wp_error($region)){
            $region_id = wp_list_pluck($region, "ID");
        }else{
           $region_id = array(get_option("options_region_main")); 
        }
    }else{
        $region_id = array(get_option("options_region_main"));
    }
    return $region_id;
}