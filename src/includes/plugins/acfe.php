<?php
add_action("init", function(){
	$terms_default = array(
        "general" => "General",
        "block"  => "Block",
        "common"  => "Common"
	);
    $theme = wp_get_theme();
    if($theme){
       $terms_default[$theme->get('TextDomain')] = $theme->get('Name');
    }
	foreach($terms_default as $key => $term_default){
        if (empty(term_exists($key, 'acf-field-group-category'))) {
            $term = wp_insert_term($term_default, 'acf-field-group-category', array(
               'description' => '',
               'slug' => $key,
               'parent' => 0
            ));
            if(!is_wp_error($term)){
                add_term_meta( $term["term_id"], 'delete-protect', true, true );
            }
        }else{
            $term = get_term_by("slug", $key, "acf-field-group-category");
            add_term_meta( $term->term_id, 'delete-protect', true, true );
        }
	}
    add_filter( 'create_term', 'on_save_acf_group_category', 10, 3 );
    add_filter( 'edit_term', 'on_save_acf_group_category', 10, 3 );
    add_filter( 'edited_term', 'on_save_acf_group_category', 10, 3 );
    add_filter( 'saved_term', 'on_save_acf_group_category', 10, 3 );
}, 999);

function on_save_acf_group_category($term_id, $tt_id, $taxonomy) {
    if($taxonomy != "acf-field-group-category"){
        return;
    }
    $term = get_term($term_id, $taxonomy);
    add_term_meta( $term->term_id, 'delete-protect', true, true );
}

function acf_get_category_posts($terms = array(), $mustHave = true){
    $args = array(
        "post_type" => "acf-field-group",
        "posts_per_page" => -1
    );
    $tax_query = array();
    if($mustHave && count($terms) > 1){
        $tax_query["relation"] = "AND";
        foreach($terms as $term){
            $tax_query_item = array(
                'taxonomy' => 'acf-field-group-category',
                'field' => 'id',
                'terms' => ($term)
            );
            $tax_query[] = $tax_query_item;   
        }
    }else{
        $tax_query[] = array(
            'taxonomy' => 'acf-field-group-category',
            'field' => 'id',
            'terms' => $terms
        );      
    }
    if($tax_query){
        $args["tax_query"] = $tax_query;        
    }
    print_r($args);
    return get_posts($args);
}
