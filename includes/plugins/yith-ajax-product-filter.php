<?php

/*
js
var yith_wcan_shortcodes = {"query_param":"yith_wcan","supported_taxonomies":["product_cat","product_tag","pa_beden","pa_brand","pa_color","pa_size"],"content":"#content","change_browser_url":"1","instant_filters":"1","ajax_filters":"","reload_on_back":"1","show_clear_filter":"1","scroll_top":"1","scroll_target":"","modal_on_mobile":"","session_param":"filter_session","show_current_children":"","loader":"","toggles_open_on_modal":"","mobile_media_query":"991","base_url":"http:\/\/localhost:8888\/salt-2023\/color\/red\/","terms_per_page":"10","currency_format":{"symbol":"&#8378;","decimal":",","thousand":".","precision":2,"format":"%s%v"},"labels":{"empty_option":"All","search_placeholder":"Search...","no_items":"No item found","show_more":"Show %d more","close":"Close","save":"Save","show_results":"Show results","clear_selection":"Clear","clear_all_selections":"Clear All"},"instant_horizontal_filter":"1"}; */




// YITH remove filters from filters before
//remove_action('yith_wcan_before_preset_filters', 'active_filters_list');



//remove_action('yith_wcan_after_preset_filters', 'active_filters_list');

/*add_action( 'yith_wcan_after_query', function($query){
    if (is_shop()) {
	    $query_vars = query_vars_for_pagination($query->query_vars);
        
        if(isset($_SESSION['query_pagination_vars'])){
                echo "Siliniyor -> <b>query_pagination_vars</b>:".json_encode($_SESSION['query_pagination_vars'])."<br><br>";
                unset($_SESSION['query_pagination_vars']);
                $_SESSION['query_pagination_vars'] = "";
            }
            if(isset($_SESSION['query_pagination_request'])){
                echo "Siliniyor -> <b>query_pagination_request</b>:".$_SESSION['query_pagination_request']."<br><br>";
                unset($_SESSION['query_pagination_request']);
                $_SESSION['query_pagination_request'] = "";
            }
            $salt = new Salt();
            $salt->log("shop request" , json_encode($query));

        $_SESSION['query_pagination_vars'] = $query_vars;
        echo "Ekleniyor -> <b>query_pagination_vars</b>:".json_encode($_SESSION['query_pagination_vars'])."<br><br>";

        if(isset($_GET["yith_wcan"]) || isset($_GET['orderby'])){
            $_SESSION['query_pagination_request'] = $query->request;
            echo "Ekleniyor -> <b>query_pagination_request</b>:".$_SESSION['query_pagination_request']."<br><br>";
        }
    }
	return $query;
});*/


function yith_wcan_pre_query($query){

	if ($query->is_main_query() && (is_product_category() || is_shop()) && isset($_GET["yith_wcan"])) {
        
        if(isset($_GET["product_cat"])){
		    $tax_query_obj = $query->tax_query;
            $tax_query_obj->queries[] = array(
                'taxonomy' => 'product_cat',
                'field' => 'slug',
				'terms' => array($_GET["product_cat"]),
				'operator' => 'IN'
            );
            foreach ($tax_query_obj->queries as $q) {
                $tax_query[] = $q;
            }
            $query->set('tax_query', $tax_query);
        }

	}
	return $query;
}
//add_action("pre_get_posts", "yith_wcan_pre_query");


function yith_wcan_term_count( $count, $term ) {
    $items = wc_get_products( array(
    	'status' => 'publish',
        'category'    => array($term->slug),
        'return'      => 'objects',
    ) );
    $product_count = 0;
    foreach ( $items as $item ) {
        if ( $item->is_type( 'variable' ) ) {
            $product_count+=1; //variation un kendisi
            $product_count+= count( $item->get_children() );
        } else {
            $product_count++;
        }
    }
    if($product_count > 0){
    	$count = $product_count;
    }
    return $count;
}
add_filter( 'yith_wcan_term_count', 'yith_wcan_term_count', 10, 2 );



function yith_wcan_get_filter_presets(){
	$args = array(
        "post_type" => "yith_wcan_preset",
        "meta_query" => array(
            array(
               "key" => "_enabled",
               "value" => "yes",
               "compare" => "="
            )
        )
	);
	$posts = get_posts($args);
	$output = array();
	foreach($posts as $post){
       $output[$post->post_name] = $post->post_title;
	}
	return $output;
}

add_filter('acf/load_field/key=field_6561da035862e', 'acf_woo_shop_wcan_filters');
function acf_woo_shop_wcan_filters( $field ) {
    $field['choices'] = array();
    $presets = yith_wcan_get_filter_presets();
    if( is_array($presets) ) {
        foreach( $presets as $key => $preset ) {
            $field['choices'][ $key ] = $preset;
        }        
    }
    return $field;
}

/* Term sayfasında filter varsa opsiyonlar seçili olmadıüında shop'a yonlendir*/
function add_custom_js_for_term_page() {
    if (is_tax()) {
        global $wp_query;
        $query_vars = $wp_query->query_vars;
        if (array_key_exists("taxonomy", $query_vars)) {
            $taxonomy = $query_vars['taxonomy'];
            $tax = str_replace("pa_", "", $taxonomy);
            $shop_url = get_permalink(wc_get_page_id('shop'));
            ?>
            <script>
                debugJS('Bu JavaScript kodu terim sayfasında çalışıyor! <?php echo esc_js($tax); ?> <?php echo esc_url($shop_url); ?>');
                /*var input = $(".yith-wcan-filters").find("[data-taxonomy='filter_<?php echo $tax; ?>']").find("input");
                input.on("click", function (e) {
                    var checked = $(".yith-wcan-filters").find("[data-taxonomy='filter_<?php echo $tax; ?>']").find("input[type='checkbox']:checked").length;
                    if (checked == 0) {
                        e.preventDefault();
                        window.location.href = '<?php echo esc_url($shop_url); ?>';
                    }
                })
                input.on("change", function (e) {
                    var checked = $(".yith-wcan-filters").find("[data-taxonomy='filter_<?php echo $tax; ?>']").find("input[type='checkbox']:checked").length;
                    if (checked == 0) {
                        e.preventDefault();
                        window.location.href = '<?php echo esc_url($shop_url); ?>';
                    }
                })
                input.next(".term-label").on("click", function(e){
                    var checked = $(".yith-wcan-filters").find("[data-taxonomy='filter_<?php echo $tax; ?>']").find("input[type='checkbox']:checked").length;
                    if (checked == 1) {
                        e.preventDefault();
                        window.location.href = '<?php echo esc_url($shop_url); ?>';
                    }
                });*/
            </script>
            <?php
        }
    }
}

//add_action('wp_footer', 'add_custom_js_for_term_page');
