<?php

//remove cf7 style & scripts
add_filter( 'wpcf7_load_js', '__return_false' );
add_filter( 'wpcf7_load_css', '__return_false' );
if(class_exists("CF7CF")){
    add_filter( 'wpcf7cf_load_js', '__return_false'  );
    add_filter( 'wpcf7cf_load_css', '__return_false'  );
}

//load js & css if page content contains 'contact-form-7' shortcode
function cf7_load(){
    if(!defined("SITE_ASSETS")){
        return;
    }
    if(!is_array(SITE_ASSETS) || !isset(SITE_ASSETS["wp_js"]) || !is_array(SITE_ASSETS["wp_js"])){
        return;
    }
    if(in_array('contact-form-7', SITE_ASSETS["wp_js"]) || in_array('contact_form', SITE_ASSETS["wp_js"])){
        if ( function_exists( 'wpcf7_enqueue_scripts' ) ) {
            wpcf7_enqueue_scripts();
        }
        if ( function_exists( 'wpcf7_enqueue_styles' ) ) {
            wpcf7_enqueue_styles();
        }
        if ( function_exists( 'wpcf7cf_enqueue_scripts' ) ) {
            wpcf7cf_enqueue_scripts();
        }
        if ( function_exists( 'wpcf7cf_enqueue_styles' ) ) {
            wpcf7cf_enqueue_styles();
        }
        
        if ( function_exists( 'dnd_cf7_scripts' ) ) {
            //dnd_cf7_scripts();
        }
    } 
}
//add_action('timber/compile/done', 'cf7_load');
add_action('wp_enqueue_scripts', 'cf7_load', 20, 0 );


function cf7_add_post_id(){
    global $post;
    return $post->ID;
}
add_shortcode('CF7_ADD_POST_ID', 'cf7_add_post_id');


function wpcf7_autop_return_false() {
    return false;
} 
add_filter('wpcf7_autop_or_not', 'wpcf7_autop_return_false');


function my_wpcf7_form_elements($html) {
    $text = 'Please Choose...';
    $html = str_replace('<option value="">---</option>', '<option value="">' . $text . '</option>', $html);
    return $html;
}
//add_filter('wpcf7_form_elements', 'my_wpcf7_form_elements');


//Dynamic content shortcodes for Contact Form 7
function cf7_cpt_select_menu ( $tag, $unused ) {  

    $type = $tag['basetype']; // Field type (select, checkbox, radio)
    if(!in_array($type, ["select", "checkbox", "radio"]) ){
        return $tag;
    }

    $value_type = "value";
    $options = (array) $tag['options'];

    foreach ( $options as $option ) {  
        if ( preg_match( '%^taxonomy:([-0-9a-zA-Z_]+)$%', $option, $matches ) ) {
             $taxonomy = $matches[1];
        }
        if ( preg_match( '%^hide_empty:([-0-9a-zA-Z_]+)$%', $option, $matches ) ) {
             $hide_empty = $matches[1];
        }
        if ( preg_match( '%^post_type:([-0-9a-zA-Z_]+)$%', $option, $matches ) ) {
             $post_type = $matches[1];
        }
        if ( preg_match( '%^post_id:([-0-9a-zA-Z_]+)$%', $option, $matches ) ) {
             $post_id = $matches[1];
        }
        if ( preg_match( '%^value_type:([-0-9a-zA-Z_]+)$%', $option, $matches ) ) {
             $value_type = $matches[1];
        }
    }

    // Check if post_type or taxonomy is set
    if(!isset($taxonomy) && !isset($post_type)) {
        return $tag;
    }

    // If taxonomy is set, get taxonomy terms
    if(isset($taxonomy)) {
        $args = array(
            "taxonomy" => $taxonomy
        );
        if(isset($hide_empty)) {
            $args["hide_empty"] = $hide_empty;
        }
        $terms = Timber::get_terms($args); 

        if ( ! $terms )  
            return $tag;  

        $terms_sorted = $terms;
        $added_items = array();
        $group_name = "";

        foreach ( $terms_sorted as $term ) {
            if(count($added_items)==0){
                $group_name = $term->name;
            }
            if(count($added_items)>0 && $term->children){
                $tag['raw_values'][] = "endoptgroup";  
                $tag['values'][] = "";  
                $tag['labels'][] = "endoptgroup";
                $group_name = $term->name;
            }
            $label_name = ($term->children?"optgroup-":"").$term->name;
            if(!in_array($label_name, $added_items)){
                $value = $value_type=="value"?$term->term_id:$label_name;
                $tag['raw_values'][] = $value;  
                $tag['values'][] = count($term->children)==0?$value:$group_name;
                $tag['labels'][] = $label_name;
                $tag['options'][] = "class:cf7-optgroup";
                $added_items[] = $label_name;
            }   
        }

        /*foreach ( $terms_sorted as $term ) {
            $label_name = $term->name;

            // Depending on type, add checkbox, radio, or select options
            if ($type === 'select') {
                $tag['raw_values'][] = $term->name;  
                $tag['values'][] = $term->term_id;
                $tag['labels'][] = $term->name;
            }
            elseif ($type === 'checkbox' || $type === 'radio') {
                $tag['raw_values'][] = $term->term_id;
                $tag['values'][] = $term->term_id;
                $tag['labels'][] = $term->name;
            }
        }*/
    }

    // If post_type is set, get posts
    if(isset($post_type)) {
       $items = Timber::get_posts('post_type='.$post_type.'&numberposts=-1');
       foreach ( $items as $item ) {
            $value = $value_type=="value"?$item->ID:$item->title;
            if ($type === 'select') {
                $tag['raw_values'][] = $value ;  
                $tag['values'][] = $value ;  
                $tag['labels'][] = $item->title;
            }
            elseif ($type === 'checkbox' || $type === 'radio') {
                $tag['raw_values'][] = $value ;
                $tag['values'][] = $value ;
                $tag['labels'][] = $item->title;
            }
        }
    }

    return $tag;
}
add_filter( 'wpcf7_form_tag', 'cf7_cpt_select_menu', 10, 2);



function cf7_custom_form_elements( $form ) {
    /*$form = str_replace('wpcf7-list-item', 'wpcf7-list-item form-check', $form);
    $form = str_replace('type="checkbox"', 'type="checkbox" class="form-check-input"', $form);
    $form = str_replace('type="radio"', 'type="radio" class="form-check-input"', $form);
    $form = str_replace('wpcf7-list-item-label', 'wpcf7-list-item-label form-check-label', $form);*/
    $form = preg_replace_callback(
        '/(<input [^>]*type="(checkbox|radio)"[^>]*>)(<span class="wpcf7-list-item-label">)(.*?)(<\/span>)/',
        function( $matches ) {
            $id = 'cf7-' . $matches[2] . '-' . unique_code(5);
            return '<div class="form-check">' .
                        '<input type="' . $matches[2] . '" id="' . $id . '" class="form-check-input" ' . preg_replace('/\s+/', ' ', $matches[1]) .
                        '<label for="' . $id . '" class="form-check-label">' . $matches[4] . '</label>' .
                    '</div>';
        },
        $form
    );
    return $form;
}
add_filter( 'wpcf7_form_elements', 'cf7_custom_form_elements' );


function custom_shortcode_atts_wpcf7_filter( $out, $pairs, $atts ) {
    $my_attr = 'defaults';
    if ( isset( $atts[$my_attr] ) ) {
        $out[$my_attr] = $atts[$my_attr];
    }
    return $out;
}
add_filter( 'shortcode_atts_wpcf7', 'custom_shortcode_atts_wpcf7_filter', 10, 3 );


function get_cf7_forms($id=0){
    $forms = array();
    if (class_exists("WPCF7")) {
        $acf_forms = QueryCache::get_cached_option("forms");//get_field("forms", "options");
        if(empty($acf_forms) || !$acf_forms) {
            return null;
        }
        if(is_array($acf_forms)){
            if($id > 0){
                foreach($acf_forms as $form) {
                    if($id === intval($form['form'])) {
                        return $form;
                    }
                }
            }else{
                foreach($acf_forms as $form){
                    $slug = $form["slug"];
                    unset($form["slug"]);
                    $forms[$slug] = $form;
                }                
            }
        }
    }
    return $forms;
}



function cf7_modify_api_response($contact_form, &$abort, $submission) {
    $id = $contact_form->id();
    $form_meta = get_cf7_forms($id);
    if($form_meta){
        $modal   = $form_meta["modal"];
        $message = $form_meta["message"];
        if ($modal) {
            if(!empty($message)){
                $properties = $contact_form->get_properties();
                $message = str_replace("[response]", $properties['messages']['mail_sent_ok'], $message);
                $properties['messages']['mail_sent_ok'] = esc_html($message);
                $contact_form->set_properties($properties);
            }
        }        
    }
    return $contact_form;
}
add_action('wpcf7_before_send_mail', 'cf7_modify_api_response', 10, 3);


function cf7_modify_signup_value( $array ) { 
    if(isset($array['signup'])){
        $value = $array['signup'];
        if( !empty( $value ) ){
            $array['signup'] = "active";
        }else{
            $array['signup'] = "inactive";
        }        
    }
    return $array;
}; 
add_filter( 'wpcf7_posted_data', 'cf7_modify_signup_value', 10, 1 );




// recaptcha v2 for ajax forms
if(class_exists("IQFix_WPCF7_Deity")){
    function enqueue_google_recaptcha_script() {
        wp_enqueue_script('google-recaptcha');
    }
    add_action('wp_enqueue_scripts', 'enqueue_google_recaptcha_script', 60); // 60:    
}


if(!class_exists('mlcf7pll\\')){
    add_filter('wpcf7_form_elements', 'remove_curly_braces_from_cf7_placeholders');
    function remove_curly_braces_from_cf7_placeholders($form) {
        $form = preg_replace('/\{([^}]*)\}/', '$1', $form);
        return $form;
    }
}