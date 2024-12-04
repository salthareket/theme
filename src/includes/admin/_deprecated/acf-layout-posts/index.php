<?php

add_action('wp_ajax_get_posts_type_taxonomies', 'get_posts_type_taxonomies');
add_action('wp_ajax_nopriv_get_posts_type_taxonomies', 'get_posts_type_taxonomies');
function get_posts_type_taxonomies(){
    $response = array(
        "error" => false,
        "message" => "",
        "html" => "",
        "data" => ""
    );
    $count = 0;
    $options = "";
    $ids = array();
    $selected = $_POST["selected"];
    switch ($_POST["name"]) {

        case 'post_type':
            if( empty($_POST["value"])){
                $taxonomies = get_taxonomies(array(), 'objects' );
            }else{
                $taxonomies = get_object_taxonomies( array( 'post_type' => $_POST["value"] ), 'objects' );
            }
            if($taxonomies){
                $options .= "<option value='0' ".(empty($selected)?"":"selected").">All Taxonomies</option>"; 
                foreach( $taxonomies as $taxonomy ){
                    $ids[] = $taxonomy;
                    $options .= "<option value='".$taxonomy->name."' ".($selected?"selected":"").">".$taxonomy->label."</option>";        
                }                
            }  
        break;

        case 'taxonomy':
            $terms = Timber::get_terms( array( 'taxonomy' => $_POST["value"] ) );
            if($terms){
                $options .= "<option value='0' ".(empty($selected)?"":"selected").">All Terms</option>"; 
                foreach( $terms as $term ){
                    $options .= "<option value='".$term->term_id."' ".($selected==$term->term_id?"selected":"").">".$term->name."</option>";
                    $ids[] = $term->term_id;          
                }                
            }
        break;

    }
    $response["html"] = $options;
    $values = array();
    $values["selected"] = $selected;
    $values["ids"] = $ids;
    $values["count"] = $count;
    $response["data"] = $values;
    echo json_encode($response);
    die;
}

function block_post_ui_render_field($field) {
    $js_code = 'if (typeof acf !== "undefined" && typeof acf.add_action !== "undefined") {';
    $js_code .= 'acf.addAction("new_field/key='.$field["key"].'", function(e){';
    $js_code .= 'debugJS(e);';
    $js_code .= 'e.$el.attr("data-val", "%s");';
    $js_code .= '});';
    $js_code .= '}';
    printf('<script>' . $js_code . '</script>', esc_js($field["value"]));
}
add_action('acf/render_field/name=taxonomy', 'block_post_ui_render_field');
add_action('acf/render_field/name=terms', 'block_post_ui_render_field');