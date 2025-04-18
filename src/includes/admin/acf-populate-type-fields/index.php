<?php

add_action('wp_ajax_get_post_type_taxonomies', 'get_post_type_taxonomies');
add_action('wp_ajax_nopriv_get_post_type_taxonomies', 'get_post_type_taxonomies');
function get_post_type_taxonomies(){
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
    //switch ($_POST["name"]) {

        //case 'menu_item_post_type':
            if( empty($_POST["value"])){
                $taxonomies = get_taxonomies(array(), 'objects' );
            }else{
                $taxonomies = get_object_taxonomies( array( 'post_type' => $_POST["value"] ), 'objects' );
            }
            if($taxonomies){
                $taxonomies = array_filter($taxonomies, function($taxonomy) {
                    return $taxonomy->public;
                });
                $options .= "<option value='' ".(empty($selected)?"sekected":"").">".($taxonomies?"Don't add Taxonomies":"Not found any taxonomy")."</option>"; 
                foreach( $taxonomies as $taxonomy ){
                    $ids[] = $taxonomy;
                    $options .= "<option value='".$taxonomy->name."' ".($selected && $selected == $taxonomy->name?"selected":"").">".$taxonomy->label."</option>";        
                }                
            }  
        //break;

    //}
    $response["html"] = $options;
    $values = array();
    $values["selected"] = $selected;
    $values["ids"] = $ids;
    $values["count"] = $count;
    $response["data"] = $values;
    echo json_encode($response);
    die;
}

function post_type_ui_render_field($field) {
    $js_code = 'if (typeof acf !== "undefined" && typeof acf.add_action !== "undefined") {';
        $js_code .= 'acf.addAction("new_field/key='.$field["key"].'", function(e){';
            $js_code .= 'if(e.$el.closest(".acf-clone").length == 0){';
                 $js_code .= 'debugJS(e);';
                 $js_code .= 'e.$el.attr("data-val", "%s");';
            $js_code .= '}';
        $js_code .= '});';
    $js_code .= '}';
    if(!empty($field["value"])){
        printf('<script>' . $js_code . '</script>', esc_js($field["value"]));        
    }

}
add_action('acf/render_field/name=menu_item_taxonomy', 'post_type_ui_render_field');