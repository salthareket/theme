<?php

if (!function_exists('wp_doing_rest')) {
    function wp_doing_rest() {
        return defined('REST_REQUEST') && REST_REQUEST;
    }
}

function is_main_query_valid() {
    global $wp_query;
    if (is_admin()) return false;
    if (defined('DOING_AJAX') && DOING_AJAX) return false;
    if (defined('DOING_CRON') && DOING_CRON) return false;
    if (wp_doing_rest()) return false;
    return isset($wp_query) && $wp_query->is_main_query();
}

function query_vars_for_pagination($query_vars) {
    $allowed = ["page", "orderby", "order", "post_type", "paged", "meta_query", "tax_query", "posts_per_page", "s"];
    return array_filter($query_vars, fn($key) => in_array($key, $allowed), ARRAY_FILTER_USE_KEY);
}
function pagination_query_request() {
    global $wp_query;
    $output = array(
        "vars" => array(),
        "request" => array()
    );
    if ( ((is_shop() || is_post_type_archive() || is_search() || is_home() ) && $wp_query->is_main_query()) || isset($wp_query->query_vars["post_type"]) || isset($wp_query->query_vars["qpt"])) {

        $query_vars = query_vars_for_pagination($wp_query->query_vars);
        $query_vars["querystring"] = json_decode(queryStringJSON(), true);

        $tax_query = $wp_query->tax_query;
        if ( $tax_query && is_array( $tax_query->queries ) && ! empty( $tax_query->queries ) ) {
            $query_vars['tax_query'] = $tax_query->queries;
        }
        
        $meta_query = $wp_query->meta_query;
        if ( $meta_query && is_array( $meta_query->queries ) && ! empty( $meta_query->queries ) ) {
            $query_vars['meta_query'] = $meta_query->queries;
        }

        if($wp_query->is_posts_page() || empty($query_vars["post_type"])){
            $query_vars["post_type"] = "post";
        }
        $post_type = $query_vars["post_type"];
        if(is_search()){
            $post_type = "search";
        }
        if(isset($wp_query->query_vars["post_type"])){
            $qpt = get_query_var("qpt", $post_type);
            $qpt = is_array($qpt)||empty($qpt)||$qpt=="search"||is_numeric($qpt)?"any":$qpt;
            if (EXCLUDE_FROM_SEARCH && $qpt == "any") {
                $post_types = get_post_types(['public' => true], 'names');
                foreach (EXCLUDE_FROM_SEARCH as $post_type) {
                    if (in_array($post_type, $post_types)) {
                        unset($post_types[$post_type]);
                    }
                }
                $qpt = $post_types;
            }
            $post_type = $qpt;
            $query_vars["post_type"] = $post_type ;
        }

        $post_type = $post_type == "any" || is_array($post_type) ? "search" : $post_type;

        $pagination = get_post_type_pagination($post_type);

        if($pagination){
            if(!$pagination["paged"]){ // && !$GLOBALS["post_pagination"][$post_type]["ajax"]){
                return $output;
           }else{
                $query_vars['posts_per_page'] = $pagination["posts_per_page"];
           }
        }else{

        }

        $output['vars'][$post_type] = $query_vars;

        if(isset($_GET["yith_wcan"]) || isset($_GET['orderby'])){
            $output['request'][$post_type] = $wp_query->request;
        }

    }
    return $output;
}
function pagination_query(){
     global $wp_query;
        $pagination_query = pagination_query_request();
        $query_pagination_vars = "";
        $query_pagination_request = "";
        if($wp_query->is_posts_page() || empty($wp_query->query_vars["post_type"])){
            $wp_query->query_vars["post_type"] = "post";
        }
        $post_type = $wp_query->query_vars["post_type"];

        if(is_search()){
            $post_type = "search";
        }
        if(isset($wp_query->query_vars["post_type"])){
            $post_type = get_query_var("qpt", $post_type);
        }

        $post_type = empty($post_type)?"post":$post_type;
        $post_type = is_array($post_type)?$post_type[0]:$post_type;

        //$pagination_type = $post_type=="any"?"search":$post_type;

        //error_log("hoooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooop");
        
        //error_log(print_r($post_type, true));
        //error_log(print_r($pagination_query, true));

        if(isset($pagination_query['vars'][$post_type]) || isset($pagination_query['request'][$post_type])){
            static $enc = null;
            if (!$enc) $enc = new Encrypt();
            //$enc = new Encrypt();
            if(isset($pagination_query['vars'][$post_type])){
                $query_pagination_vars = $enc->encrypt($pagination_query['vars'][$post_type]);
            }
            if(isset($pagination_query['request'][$post_type])){
                $query_pagination_request = $enc->encrypt($pagination_query['request'][$post_type]);
            }
        }

        return array(
            "vars" => $query_pagination_vars,
            "request" => $query_pagination_request
        );
}

function custom_result_count() {
    global $wp_query;

    $post_type = $wp_query->get('post_type') ?? "post";
    $per_page = $GLOBALS["post_pagination"][$post_type]['posts_per_page'] ?? get_option('posts_per_page');
    
    if (!$per_page || ($post_type === "product" && function_exists('woocommerce_result_count'))) {
        woocommerce_result_count();
        return;
    }

    $total = $wp_query->found_posts;
    $current = max(1, get_query_var('paged', 1));

    if ($total <= $per_page) return;

    echo '<div class="woocommerce-result-count result-count m-0 custom">';
    printf(
        _nx(
            'Showing %1$d&ndash;%2$d of %3$d result',
            'Showing %1$d&ndash;%2$d of %3$d results',
            $total,
            'with first and last result',
            'woocommerce'
        ),
        ($per_page * $current) - $per_page + 1,
        min($total, $per_page * $current),
        $total
    );
    echo '</div>';
}

function header_has_dropdown(){
    $header_tools_dropdown = false;
    $header_contents = ["header_start", "header_center", "header_end"];
    foreach($header_contents as $header_content){
        $header_item = QueryCache::get_cached_option($header_content);//get_field($header_content, "options");
        if(isset($header_item["type"]) && $header_item["type"] == "tools" && !$header_tools_dropdown){
            $header_tools = $header_item["header_tools"];
            $header_tools = $header_tools["header_tools"];
            if($header_tools){
                foreach($header_tools as $header_tool){
                    if($header_tool["menu_type"] == "dropdown"){
                        $header_tools_dropdown = true;
                        continue;
                    }
                }
            }
        }
    }
    return $header_tools_dropdown;
}
function header_has_navigation(){
    $header_contents = ["header_start", "header_center", "header_end"];
    foreach($header_contents as $header_content){
        $header_item = QueryCache::get_cached_option($header_content);//get_field($header_content, "options");
        if($header_item){
            if($header_item["type"] == "navigation"){
                return true;
            }
            if($header_item["type"] == "tools"){
                $header_tools = $header_item["header_tools"];
                $header_tools = $header_tools["header_tools"];
                if($header_tools){
                    foreach($header_tools as $header_tool){
                        if($header_tool["menu_item"] == "navigation" && $header_tool["menu_type"] == "dropdown"){
                            return true;
                        }
                    }
                }
            }
        }
    }
    return false;
}
function header_footer_options($save = false){

    $header_footer_options = THEME_STATIC_PATH . 'data/header-footer-options.json';

    if(file_exists($header_footer_options) && !$save){
        $header_footer_options = file_get_contents($header_footer_options);
        $header_footer_options = json_decode($header_footer_options, true);

        return $header_footer_options;
    }


        // Header Options //
        $header_fixed = QueryCache::get_cached_option("header_fixed");//get_field("header_fixed", "options");
        $header_fixed = in_array($header_fixed, ["top","bottom","bottom-start"]) ? $header_fixed : false;
        if($header_fixed == "top" || $header_fixed == "bottom-start"){
            $header_affix = QueryCache::get_cached_option("header_affix");//get_field("header_affix", "options");
        }else{
            $header_affix = false;
        }

        $header_hide_on_scroll_down = QueryCache::get_cached_option("header_hide_on_scroll_down");//get_field("header_hide_on_scroll_down", "options");
        $header_hide_on_scroll_down = $header_affix && $header_hide_on_scroll_down ? true : false;

        $header_equal = QueryCache::get_cached_option("header_equal");
        $header_equal_on = QueryCache::get_cached_option("header_equal_on");

        $header_container = QueryCache::get_cached_option("header_container");//get_field("header_container", "options");
        $header_container = block_container($header_container);//$header_container == "default" ? "" : $header_container;
        $header_container = empty($header_container)?"vw-100 px-3":"";
        
        $header_start_type = "";
        $header_center_type = "";
        $header_end_type = "";

        $header_contents = ["header_start", "header_center", "header_end"];
        foreach($header_contents as $header_content){
            $header_item = QueryCache::get_cached_option($header_content);//get_field($header_content, "options");

            ${$header_content."_type"} = "";
            ${$header_content."_align"} = "";
            ${$header_content."_menu"} = "";
            ${$header_content."_logo_height"} = "";
            ${$header_content."_navigation_parent_link"} = "";
            ${$header_content."_tools"} = [];     

            if($header_item){
                ${$header_content."_type"} = $header_item["type"];
                ${$header_content."_align"} = $header_item["align"];
                ${$header_content."_menu"} = $header_item["menu"];
                ${$header_content."_navigation_parent_link"} = false;
                ${$header_content."_logo_height"} = false;
                if(${$header_content."_type"} == "tools"){
                    $header_tools = $header_item["header_tools"];
                    $header_tools = $header_tools["header_tools"];
                    $header_tools["affix"] = $header_affix;
                }else{
                    $header_tools = array();
                    if(${$header_content."_type"} == "navigation"){
                       ${$header_content."_navigation_parent_link"} = $header_item["navigation_parent_link"];
                    }
                    if(${$header_content."_type"} == "brand"){
                       ${$header_content."_logo_height"} = $header_item["logo_height"];
                    }
                }  
                ${$header_content."_tools"} = $header_tools;                 
            }

        }



        /*if($header_center_type != "empty"){
            $header_start_class = ($header_start_type != "empty" ? "flex-grow-0" : "flex-grow-0"). " flex-auto--";//" nav-equal nav-equal-{{equalize}}";
            $header_center_class = "flex-grow-1 h-100";
            $header_end_class = ($header_end_type != "empty" ? "flex-grow-0" : "flex-grow-0"). " flex-auto--";//" nav-equal nav-equal-{{equalize}}";
        }else{
            $header_start_class = ($header_start_type != "empty" ? "flex-shrink-1 -flex-grow-0" : "flex-grow-1"). " flex-auto--";
            $header_center_class = "flex-grow-1 h-100";
            $header_end_class = ($header_end_type != "empty" ? "flex-shrink-1 -flex-grow-0" : "flex-grow-1"). " flex-auto--";
        }*/

        if($header_center_type != "empty"){
            $header_start_class = "flex-shrink-0" . ($header_equal ? " nav-equal nav-equal-".$header_equal_on : "");
            $header_center_class = "flex-grow-1 h-100";
            $header_end_class = "flex-shrink-0" . ($header_equal ? " nav-equal nav-equal-".$header_equal_on : "");
        }else{
            $header_start_class = ($header_start_type != "empty" ? "flex-shrink-0" : "flex-grow-1");
            $header_center_class = "flex-grow-1 h-100";
            $header_end_class = ($header_end_type != "empty" ? "flex-shrink-0" : "flex-grow-1");
        }

        $header_options = array(
            "affix" => $header_affix,
            "fixed" => $header_fixed,
            "header_hide_on_scroll_down" => $header_hide_on_scroll_down,
            "container" => $header_container,
            "start" => array(
                "type" => $header_start_type,
                "align" => $header_start_align,
                "tools" => $header_start_tools,
                "class" => $header_start_class,
                "menu" => $header_start_menu,
                "parent_link" => $header_start_navigation_parent_link,
                "logo_height" => $header_start_logo_height
            ),
            "center" => array(
                "type" => $header_center_type,
                "align" => $header_center_align,
                "tools" => $header_center_tools,
                "class" => $header_center_class,
                "menu" => $header_center_menu,
                "parent_link" => $header_center_navigation_parent_link,
                "logo_height" => $header_center_logo_height
            ),
            "end" => array(
                "type" => $header_end_type,
                "align" => $header_end_align,
                "tools" => $header_end_tools,
                "class" => $header_end_class,
                "menu" => $header_end_menu,
                "parent_link" => $header_end_navigation_parent_link,
                "logo_height" => $header_end_logo_height
            )
        );

        // Footer Options //
        $footer_container = QueryCache::get_cached_option("footer_container");//get_field("footer_container", "options");
        $footer_container = block_container($footer_container);//$footer_container == "default" ? "" : $footer_container;
        $footer_text = QueryCache::get_cached_option("footer_text");//get_field("footer_text", "options");
        $footer_logo = QueryCache::get_cached_option("logo_footer");//get_field("logo_footer", "option");
        $footer_menu = QueryCache::get_cached_option("footer_menu");//get_field("footer_menu", "option");
        $footer_template = QueryCache::get_cached_option("footer_template");//get_field("footer_template", "option");

        if($footer_menu){
            $arr = [];
            foreach($footer_menu as $menu){
                $arr[$menu["name"]] = $menu["menu"];
            }
            $footer_menu = $arr;
        }

        $footer_options = array(
            "container" => $footer_container,
            "text" => $footer_text,
            "logo" => $footer_logo,
            "menu" => $footer_menu,
            "template" => $footer_template
        );

        $header_footer_options = array(
            "header" => $header_options,
            "footer" => $footer_options
        );

        return $header_footer_options;
}

// Timber posts kontrolü
function check_timber_posts() {
    global $wp_query;
    if (!isset($wp_query->posts) || empty($wp_query->posts)) {
        $wp_query->posts = array(); // Boş bir dizi olarak ayarla
    }
}
add_action('template_redirect', 'check_timber_posts');

function add_query_vars_filter( $vars ){
  	if(isset($GLOBALS["url_query_vars"])){
        $query_vars = $GLOBALS["url_query_vars"];
        if(!is_array($query_vars)){
            return;
        }
        if(count($query_vars) == 0){
            return;
        }
  	    foreach($query_vars as $query_var){
  		   $vars[] = $query_var;
  		}
  	}
  	return $vars;
}
add_filter( 'query_vars', 'add_query_vars_filter' );

function old_style_name_like_wpse_123298($clauses) {
    remove_filter('term_clauses','old_style_name_like_wpse_123298');
	$pattern = '|(name LIKE )\'{.*?}(.+{.*?})\'|';
	$clauses['where'] = preg_replace($pattern,'$1 \'$2\'',$clauses['where']);
	return $clauses;
}
add_filter('terms_clauses','old_style_name_like_wpse_123298');

/*
function bootstrap_gallery( $output = '', $atts = array(), $instance = '' ) {
    if ( !isset( $atts['columns'] ) ) {
        $columns = 3;
    } else {
        $columns = str_replace( "u0022", "", $atts['columns'] );
    }
    $images = str_replace( "u0022", "", $atts['ids'] );
    $images = explode( ',', $images );

    $return = '<div class="lightgallery init-me row row-cols-lg-' . $columns . ' g-3 content-gallery" data-masonry=\'{"percentPosition": true}\'>';
    $i = 0;
    foreach ( $images as $key => $value ) {
        if ( $i % $columns == 0 && $i > 0 ) {
            //$return .= '</div><div class="row g-3 mt-1 content-gallery" data-masonry=\'{"percentPosition": true}\'>';
        }
        if ( empty( $value ) ) {
            continue;
        }

        // Resim verilerini al
        $image_attributes = wp_get_attachment_image_src( $value, 'full' );
        $image_alt = get_post_meta( $value, '_wp_attachment_image_alt', true ); // Alt text
        $image_title = get_the_title( $value ); // Image title
        
        if ( empty( $image_attributes ) ) {
            continue;
        }

        $return .= '
            <div class="col">
                <a href="' . esc_url( $image_attributes[0] ) . '" title="' . esc_attr( $image_title ) . '">
                    <img src="' . esc_url( $image_attributes[0] ) . '" alt="' . esc_attr( $image_alt ) . '" class="img-fluid" loading="lazy">
                    <span itemtype="http://schema.org/ImageObject" itemscope="">
                        <meta content="' . esc_url( $image_attributes[0] ) . '" itemprop="contentUrl">
                    </span>
                </a>
            </div>';
        $i++;
    }
    $return .= '</div>';
    //$return .= '<script type="text/javascript" src="https://unpkg.com/masonry-layout@4.2.2/dist/masonry.pkgd.min.js"></script>';
    return $return;
}
add_filter( 'post_gallery', 'bootstrap_gallery', 10, 4 );
*/

/*function add_lazyload_to_images($content) {
    if (!is_singular()) return $content;

    // srcset -> data-srcset, src -> data-src
    $content = preg_replace('/<img([^>]*)srcset=["\'](.*?)["\'](.*?)>/i', '<img$1data-srcset="$2"$3>', $content);
    $content = preg_replace('/<img([^>]*)src=["\'](.*?)["\'](.*?)>/i', '<img$1data-src="$2"$3>', $content);

    // Lazy load için class ekleme
    $content = preg_replace('/<img([^>]*)class=["\'](.*?)["\'](.*?)>/i', '<img$1class="$2 lazy"$3>', $content);

    return $content;
}
add_filter('the_content', 'add_lazyload_to_images', 99);

function add_img_fluid_class($content) {
    $content = preg_replace('/<img(.*?)class=["\']([^"\']*)["\'](.*?)>/', '<img$1class="$2 img-fluid"$3>', $content);
    $content = preg_replace('/<img(?!.*\bclass=)(.*?)>/', '<img class="img-fluid"$1>', $content);
    return $content;
}
add_filter('the_content', 'add_img_fluid_class');*/

function optimize_image_output( $content ) {
    if ( ! did_action( 'template_redirect' ) || is_admin() ) {
        return $content;
    }

    // img tag’lerini bul
    return preg_replace_callback(
        '/<img([^>]+?)>/i',
        function ( $matches ) {
            $img_tag = $matches[0];

            // src yoksa atla
            if ( ! preg_match( '/src=["\']([^"\']+)["\']/', $img_tag ) ) {
                return $img_tag;
            }

            // loading yoksa lazy ekle
            if ( ! preg_match( '/loading=["\']/', $img_tag ) ) {
                $img_tag = str_replace( '<img', '<img loading="lazy"', $img_tag );
            }

            // class varsa içine ekle, yoksa class oluştur
            if ( preg_match( '/class=["\']([^"\']*)["\']/', $img_tag, $class_match ) ) {
                $existing_classes = $class_match[1];
                if ( strpos( $existing_classes, 'img-fluid' ) === false ) {
                    $new_classes = trim( $existing_classes . ' img-fluid' );
                    $img_tag = str_replace( $class_match[0], 'class="' . esc_attr( $new_classes ) . '"', $img_tag );
                }
            } else {
                $img_tag = str_replace( '<img', '<img class="img-fluid"', $img_tag );
            }

            return $img_tag;
        },
        $content
    );
}
add_filter( 'the_content', 'optimize_image_output', 20 );
add_filter( 'acf/format_value/type=wysiwyg', 'optimize_image_output', 20 );

/*add responsive classes to embeds*/
function responsive_embed_oembed_html($html, $url, $attr, $post_id) {
      if (strpos($url, 'youtube.')||strpos($url, 'youtu.be')||strpos($url, 'vimeo.')||strpos($url, 'dailymotion.')){
         return '<div class="ratio ratio-16x9">' . $html . '</div>';
      }else{
         return $html;  
      }
}
add_filter('embed_oembed_html', 'responsive_embed_oembed_html', 99, 4);

function search_distinct() {
    return "DISTINCT";
}
add_filter("posts_distinct", "search_distinct");

function keep_me_logged_in_for_1_year( $expirein ) {
    return 31556926; // 1 year in seconds
}
add_filter( 'auth_cookie_expiration', 'keep_me_logged_in_for_1_year' );


//add responsive image classes to images who added from text editor
function add_image_responsive_class($content) {
       global $post;
       $pattern ="/<img(.*?)class=\"(.*?)\"(.*?)>/i";
       $replacement = '<img$1class="$2 img-fluid lazy" itemprop="image" $3>';
       $content = preg_replace($pattern, $replacement, $content);
       
       /*add imageobject*/
       $pattern ="/<img(.*?)src=\"(.*?)\"(.*?)>/i";
       $replacement = '<a href="$2" data-fancybox><img$1data-src="$2"$3><span itemtype="http://schema.org/ImageObject" itemscope=""><meta content="$2" itemprop="contentUrl"></span></a>';
       $content = preg_replace($pattern, $replacement, $content);
       return $content;
}
//add_filter('the_content', 'add_image_responsive_class');




function add_img_fluid_to_gutenberg($block_content, $block) {
    if (!isset($block['blockName']) || empty($block['blockName'])) {
        return $block_content;
    }

    if (strpos($block['blockName'], 'core/image') !== false) {
        $block_content = preg_replace('/<img(.*?)class=["\']([^"\']*)["\'](.*?)>/', '<img$1class="$2 img-fluid"$3>', $block_content);
        $block_content = preg_replace('/<img(?!.*\bclass=)(.*?)>/', '<img class="img-fluid"$1>', $block_content);
    }
    return $block_content;
}
add_filter('render_block', 'add_img_fluid_to_gutenberg', 10, 2);



function restrict_author_pages() {
    if (is_author()) {
        $allowed_role = 'administrator';
        if (!current_user_can($allowed_role)) {
            wp_redirect(home_url());
            exit;
        }
    }
}
//add_action('template_redirect', 'restrict_author_pages');

//remove empty <p> tags
function remove_empty_p( $content ) {
    $content = force_balance_tags( $content );
    $content = preg_replace( '#<p>\s*+(<br\s*/*>)?\s*</p>#i', '<br/>', $content );
    $content = preg_replace( '~\s?<p>(\s|&nbsp;)+</p>\s?~', '<br/>', $content );
    return $content;
}
//add_filter('the_content', 'remove_empty_p', 20, 1);

function post_prev_next_order ( $order_by, $post, $order ) {
    global $wpdb;
    return "ORDER BY p.post_title ASC LIMIT 1";
}
//add_filter ( 'get_next_post_sort', 'post_prev_next_order', 10, 3 );
//add_filter ( 'get_previous_post_sort', 'post_prev_next_order', 10, 3 );

function ns_filter_avatar($avatar, $id_or_email, $size, $default, $alt, $args) {
    $headers = @get_headers( $args['url'] );
    if( ! preg_match("|200|", $headers[0] ) ) {
        return;
    }
    return $avatar; 
}
//add_filter('get_avatar','ns_filter_avatar', 10, 6);


add_action('wp_ajax_save_lcp_results', 'save_lcp_results');
add_action('wp_ajax_nopriv_save_lcp_results', 'save_lcp_results');
function save_lcp_results() {
    if (!isset($_POST['type']) || !isset($_POST['id']) || !isset($_POST['lcp_data'])) {
        wp_send_json_error(['message' => 'Eksik parametreler!']);
    }

    $id = intval($_POST['id']);
    $type = trim($_POST['type']);
    $url = trim($_POST['url']);
    $lang = trim($_POST['lang']);
    $lcp_data = json_decode(stripslashes($_POST['lcp_data']), true);

    if (!$id || !$type || !$lcp_data) {
        wp_send_json_error(['message' => 'Geçersiz veri!']);
    }

    $selectors = [];
    foreach ($lcp_data as $device => &$data) { 
        if (!empty($data['selectors']) && is_array($data['selectors'])) {
            $selectors = array_merge($selectors, $data['selectors']);
            unset($data['selectors']);
        }
    }
    unset($data); 

    $critical_css = "";
    $structure_fp = ""; 
    $existing_meta = [];

    // 1. Existing meta verisini çekin (structure_fp'yi almak için)
    if ($type !== "archive") {
        $meta_function_get = "get_{$type}_meta";
        $existing_meta = call_user_func($meta_function_get, $id, 'assets', true);
    } else {
        $option_name = $id . '_archive_'.$lang.'_assets'; 
        $existing_meta = get_option($option_name);
    }
    
    // structure_fp'yi al
    if (!empty($existing_meta['structure_fp'])) {
        $structure_fp = $existing_meta['structure_fp'];
    }

    // EĞER structure_fp YOKSA, İŞLEMİ İPTAL ET (Manifest kaydı yoksa dosya yetim kalır)
    if (empty($structure_fp)) {
         wp_send_json_error(['message' => 'Critical CSS oluşturulamadı: structure_fp bulunamadı.']);
    }

    $selectors = array_unique($selectors);
    if($selectors){
        $cache_dir = STATIC_PATH . 'css/cache/';
        
        // YENİ KOD: Dosya adını structure_fp ile belirleyin
        $output = $cache_dir . $structure_fp . '-critical.css'; 

        $input = "";
        if(defined("SITE_ASSETS") && is_array(SITE_ASSETS)){
            $input .= file_get_contents(STATIC_PATH . SITE_ASSETS["plugin_css"]);
            $input .= file_get_contents(STATIC_PATH . SITE_ASSETS["css_page"]);
        }else{
            $input .= file_get_contents(STATIC_PATH ."css/main-combined.css");
        }

        /*
        >>> We decided to use wp rockets's critical css function...
        $remover = new RemoveUnusedCss($url, $input, $output, [], true);
        $remover->generate_critical_css($selectors);
        $critical_css = $output;
        $critical_css = str_replace(STATIC_PATH, '', $critical_css);*/
    }

    // LCP verilerini ve Critical CSS yolunu meta veriye kaydetme (Mevcut mantık)
    foreach ($lcp_data as $key => $lcp) {
        if(isset($lcp["url"]) && !empty($lcp["url"])){
            if(is_local($lcp["url"])){
                $lcp_data[$key]["id"] = get_attachment_id_by_url($lcp["url"]);
            }
        }
    }

    if($type != "archive"){
        $meta_function_update = "update_{$type}_meta";
        $meta_function_add = "add_{$type}_meta";
        
        if ($existing_meta) {
            $existing_meta["lcp"] = array_merge($existing_meta["lcp"], $lcp_data);
            if($critical_css){
                $existing_meta["css_critical"] = $critical_css;
            }
            $return = call_user_func($meta_function_update, $id, 'assets', $existing_meta); // Güncelle
        } // Add mantığı eksik, ama update'i kullanıyoruz.
    }else{
        $option_name = $id . '_archive_'.$lang.'_assets';
        
        if ($existing_meta) {
            $existing_meta["lcp"] = array_merge($existing_meta["lcp"], $lcp_data);
            if($critical_css){
                $existing_meta["css_critical"] = $critical_css;
            }
            $return = update_option($option_name, $existing_meta); // Güncelle
        }
    }

    wp_send_json_success(['message' => 'LCP verileri kaydedildi!', 'data' => $lcp_data, 'status' => $return]);
}
/*function save_lcp_results() {
    if (!isset($_POST['type']) || !isset($_POST['id']) || !isset($_POST['lcp_data'])) {
        wp_send_json_error(['message' => 'Eksik parametreler!']);
    }

    $id = intval($_POST['id']);
    $type = trim($_POST['type']);
    $url = trim($_POST['url']);
    $lang = trim($_POST['lang']);
    $lcp_data = json_decode(stripslashes($_POST['lcp_data']), true);

    if (!$id || !$type || !$lcp_data) {
        wp_send_json_error(['message' => 'Geçersiz veri!']);
    }

    $critical_css = "";
    $structure_fp = "";

    $selectors = [];
    foreach ($lcp_data as $device => &$data) { // <-- referansla al!
        if (!empty($data['selectors']) && is_array($data['selectors'])) {
            $selectors = array_merge($selectors, $data['selectors']);
            unset($data['selectors']); // orijinal $lcp_data'dan siler
        }
    }
    unset($data); // referansı kır, klasik PHP kuralı

    $selectors = array_unique($selectors);
    $critical_css = "";
    if($selectors){
        $cache_dir = STATIC_PATH . 'css/cache/';
        $css_page_hash = md5($type."-".$id);
        $output = $cache_dir . $css_page_hash . '-critical.css';

        $input = "";//file_get_contents(STATIC_PATH ."css/root.css");
        if(defined("SITE_ASSETS") && is_array(SITE_ASSETS)){
            $input .= file_get_contents(STATIC_PATH . SITE_ASSETS["plugin_css"]);
            $input .= file_get_contents(STATIC_PATH . SITE_ASSETS["css_page"]);
        }else{
            $input .= file_get_contents(STATIC_PATH ."css/main-combined.css");
        }

        $remover = new RemoveUnusedCss($url, $input, $output, [], true);
        $remover->generate_critical_css($selectors);
        $critical_css = $output;
        $critical_css = str_replace(STATIC_PATH, '', $critical_css);
    }

    $return = "";
    foreach ($lcp_data as $key => $lcp) {
        if(isset($lcp["url"]) && !empty($lcp["url"])){
            if(is_local($lcp["url"])){
                $lcp_data[$key]["id"] = get_attachment_id_by_url($lcp["url"]);
            }//else{
                //$lcp_data[$key]["id"] = $lcp["url"];
            //}
        }
    }

    $existing_meta = [];
    if($type != "archive"){
        $meta_function_get = "get_{$type}_meta";
        $meta_function_update = "update_{$type}_meta";
        $meta_function_add = "add_{$type}_meta";
        $existing_meta = call_user_func($meta_function_get, $id, 'assets', true);
        if ($existing_meta) {
            $existing_meta["lcp"] = array_merge($existing_meta["lcp"], $lcp_data);
            if($critical_css){
                $existing_meta["css_critical"] = $critical_css;
            }
            $return = call_user_func($meta_function_update, $id, 'assets', $existing_meta); // Güncelle
        }
    }else{
        $option_name = $id . '_archive_'.$lang.'_assets'; // Option name oluştur
        $existing_meta = get_option($option_name); // Var olan option'u kontrol et
        if ($existing_meta) {
            $existing_meta["lcp"] = array_merge($existing_meta["lcp"], $lcp_data);
            if($critical_css){
                $existing_meta["css_critical"] = $critical_css;
            }
            $return = update_option($option_name, $existing_meta); // Güncelle
        }
    }

    wp_send_json_success(['message' => 'LCP verileri kaydedildi!', 'data' => $lcp_data, 'status' => $return]);
}*/


/*
function add_cache_control_headers() {
    if (is_singular() || is_archive()) { // Sadece tekil ve arşiv sayfalarında
        header("Cache-Control: public, max-age=31536000");
        header("Expires: " . gmdate("D, d M Y H:i:s", time() + 31536000) . " GMT");
    } else {
        header("Cache-Control: public, max-age=2592000");
        header("Expires: " . gmdate("D, d M Y H:i:s", time() + 2592000) . " GMT");
    }
}
add_action('send_headers', 'add_cache_control_headers');

function add_mp3_cache_headers() {
    if (isset($_SERVER['REQUEST_URI']) && preg_match('/\.mp3$/', $_SERVER['REQUEST_URI'])) {
        header("Cache-Control: public, max-age=31536000, immutable");
        header("Expires: " . gmdate("D, d M Y H:i:s", time() + 31536000) . " GMT");
    }
}
add_action('send_headers', 'add_mp3_cache_headers');
*/


/*add_action('send_headers', function () {
    $csp_directives = [
        "default-src 'self'",
        
        // DÜZELTME: Font Awesome ve Bootstrap için 'https://cdnjs.cloudflare.com' eklendi.
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://unpkg.com https://maps.googleapis.com https://maps.gstatic.com https://cdnjs.cloudflare.com",
        
        // DÜZELTME: script-src-elem için de kaynakları belirtmek daha modern bir yaklaşımdır.
        "script-src 'self' 'unsafe-inline' blob: https://unpkg.com https://maps.googleapis.com https://maps.gstatic.com https://www.youtube.com",
        
        "worker-src 'self' blob:",
        
        // DÜZELTME: Gravatar avatarları için 'https://secure.gravatar.com' eklendi.
        "img-src 'self' data: https://img.youtube.com https://i.ytimg.com https://maps.googleapis.com https://maps.gstatic.com https://*.tile.openstreetmap.org https://tile.openstreetmap.org https://s.w.org https://secure.gravatar.com",
        
        // DÜZELTME: Font Awesome font dosyaları için 'https://cdnjs.cloudflare.com' eklendi.
        "font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com",
        
        "object-src 'none'",
        "base-uri 'self'",
        "frame-ancestors 'self'",
        "frame-src 'self' https://www.youtube.com https://www.youtube-nocookie.com https://www.google.com https://www.google.com/maps https://www.openstreetmap.org",
        "connect-src 'self' https://maps.googleapis.com https://maps.gstatic.com https://*.tile.openstreetmap.org https://tile.openstreetmap.org https://noembed.com https://cdn.plyr.io"
    ];

    $csp_header = implode('; ', $csp_directives) . ';';
    header("Content-Security-Policy: $csp_header");
});*/


// 2️⃣ send_headers'de option'dan oku
add_action('send_headers', function () {
    $csp_directives = [
        "default-src" => ["'self'"],
        "style-src"   => ["'self'", "'unsafe-inline'", "https://fonts.googleapis.com", "https://unpkg.com", "https://maps.googleapis.com", "https://maps.gstatic.com", "https://cdnjs.cloudflare.com"],
        "script-src"  => ["'self'", "'unsafe-inline'", "blob:", "https://unpkg.com", "https://maps.googleapis.com", "https://maps.gstatic.com", "https://www.youtube.com"],
        "worker-src"  => ["'self'", "blob:"],
        "img-src"     => ["'self'", "data:", "https://img.youtube.com", "https://i.ytimg.com", "https://maps.googleapis.com", "https://maps.gstatic.com", "https://*.tile.openstreetmap.org", "https://tile.openstreetmap.org", "https://s.w.org", "https://secure.gravatar.com"],
        "font-src"    => ["'self'", "data:", "https://fonts.gstatic.com", "https://cdnjs.cloudflare.com"],
        "object-src"  => ["'none'"],
        "base-uri"    => ["'self'"],
        "frame-ancestors" => ["'self'"],
        "frame-src"   => ["'self'", "https://www.youtube.com", "http://www.youtube.com", "https://www.youtube-nocookie.com", "https://www.google.com", "https://www.google.com/maps", "https://www.openstreetmap.org"],
        "connect-src" => ["'self'", "https://maps.googleapis.com", "https://maps.gstatic.com", "https://*.tile.openstreetmap.org", "https://tile.openstreetmap.org", "https://noembed.com", "https://cdn.plyr.io"]
    ];

    // DB'den domainleri al
    $approved_domains = get_option('csp_approved_domains', []);
    if (!is_array($approved_domains)) {
        $approved_domains = [];
    }

    // Direkt CSP direktif adıyla ekle
    foreach ($approved_domains as $directive => $domains) {
        if (!isset($csp_directives[$directive]) || !is_array($domains)) continue;
        foreach ($domains as $domain) {
            if (!in_array($domain, $csp_directives[$directive])) {
                $csp_directives[$directive][] = $domain;
            }
        }
    }

    // CSP header stringe çevir
    $csp_string = '';
    foreach ($csp_directives as $key => $values) {
        $csp_string .= $key . ' ' . implode(' ', $values) . '; ';
    }

    header("Content-Security-Policy: " . $csp_string);
});



function add_cache_control_headers() {
    if ( is_admin() || headers_sent() ) {
        return;
    }

    // Genel dosyalar (HTML, JS, CSS vb.) için cache
    header( 'Cache-Control: public, max-age=31536000, immutable' );

    // MP3 özel kontrolü
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    if ( stripos( $request_uri, '.mp3' ) !== false ) {
        header( 'Content-Type: audio/mpeg' );
        header( 'Accept-Ranges: bytes' );
        header( 'Content-Disposition: inline' );
    }
}
add_action('send_headers', 'add_cache_control_headers');



add_action('save_post', function($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;

    $content = get_post_field('post_content', $post_id);

    // Sondaki &nbsp; ve boşlukları temizle
    $clean = preg_replace('/(&nbsp;|\s)+$/u', '', $content);

    if ($clean !== $content) {
        // Closure olduğu için remove_action + add_action kullanma
        // Sonsuz döngüyü engellemek için flag kullan
        if (!defined('CLEANING_POST_CONTENT')) {
            define('CLEANING_POST_CONTENT', true);

            wp_update_post([
                'ID' => $post_id,
                'post_content' => $clean
            ]);
        }
    }
}, 10, 1);


add_filter('the_content', function($c){
    return preg_replace('/(&nbsp;|\s)+$/u', '', $c);
}, 20);







/**
 * [Özyinelemeli Fonksiyon] 
 * Sayfanın içeriği boşsa, içeriği olan ilk alt sayfanın ID'sini bulur.
 *
 * @param int $post_id Kontrol edilecek sayfanın ID'si.
 * @return int Bulunan alt sayfanın ID'si (veya orijinal ID).
 */
function find_first_content_child_id($post_id) {
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'page') {
        return $post_id;
    }
    if (!empty(trim($post->post_content))) {
        return $post_id;
    }
    $args = array(
        'post_type'      => 'page',
        'posts_per_page' => 1,
        'post_status'    => 'publish',
        'orderby'        => 'menu_order', 
        'order'          => 'ASC',
        'post_parent'    => $post_id,
        'fields'         => 'ids',
    );
    $children_ids = get_posts($args);
    if (!empty($children_ids)) {
        $first_child_id = $children_ids[0];
        return find_first_content_child_id($first_child_id);
    }
    return $post_id;
}
/**
 * [FİLTRE] Sayfa linkini, içeriği boşsa, içeriği olan ilk alt sayfanın linkiyle değiştirir.
 * Öncelik 20, menü sistemi gibi yerlerde çalışmasını garanti eder.
 *
 * @param string $permalink Post'un orijinal linki.
 * @param int $post_id Post'un ID'si.
 * @return string Değiştirilmiş veya orijinal link.
 */
add_filter('page_link', 'override_page_link_if_empty_content_general', 20, 2);
function override_page_link_if_empty_content_general($permalink, $post_id) {
    $post = get_post($post_id);
    if (!($post instanceof \WP_Post) || $post->post_type !== 'page') {
        return $permalink;
    }
    if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
        return $permalink;
    }
    $final_id = find_first_content_child_id($post_id);
    if ($final_id !== $post_id) {
        return get_permalink($final_id);
    }
    return $permalink;
}

/**
 * Yoast Breadcrumb Linklerini, içeriği boş olan sayfalarda alt sayfalara yönlendirir.
 * * @param array $links Breadcrumb link dizisi.
 * @return array Değiştirilmiş link dizisi.
 */
add_filter('wpseo_breadcrumb_links', 'override_yoast_breadcrumb_links', 10);
function override_yoast_breadcrumb_links($links) {
    if (!class_exists('WPSEO_Breadcrumbs')) {
        return $links;
    }
    foreach ($links as $key => $link) {
        if (isset($link['id']) && get_post_type($link['id']) === 'page') {
            $post_id = $link['id'];
            $final_id = find_first_content_child_id($post_id);
            if ($final_id !== $post_id) {
                $new_permalink = get_permalink($final_id);
                $links[$key]['url'] = $new_permalink;
            }
        }
    }
    return $links;
}