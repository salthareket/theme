<?php


/*add_action('wp', function () {
    if (!function_exists('pll_get_languages') || is_admin()) {
        return;
    }

    global $post;

    $current_lang = pll_current_language(); // Mevcut dili al
    $default_lang = pll_default_language(); // Varsayılan dili al
    $available_languages = pll_get_languages(['fields' => 'slug']); // Tüm aktif dillerin slug'larını al

    // Eğer log çalışıyor mu test etmek istiyorsan buraya bir test logu ekle
    error_log("TEST: Polylang yönlendirme kontrolü başlatıldı.");

    // Eğer mevcut dil Polylang'da yoksa veya içerik bulunamıyorsa
    if (!in_array($current_lang, $available_languages) || (is_singular() && empty($post))) {
        error_log("POLYLANG HATA: '{$current_lang}' dili mevcut değil veya içerik bulunamadı!");

        if (!empty($default_lang)) {
            $redirect_url = home_url('/' . $default_lang . '/');
            error_log("YÖNLENDİRME: {$redirect_url}");
            wp_redirect($redirect_url, 301);
            exit;
        } else {
            status_header(404);
            include(get_template_directory() . '/404.php');
            exit;
        }
    }
});*/




function query_vars_for_pagination($query_vars){
    $args = array();
    $allowed = ["page", "orderby", "order", "post_type", "paged", "meta_query", "tax_query", "posts_per_page", "s"];
    foreach($query_vars as $key => $var){
        if(in_array($key, $allowed)){
            $args[$key] = $var;
        }
    }
    return $args;
}
function pagination_query_request() {
    global $wp_query;
    $output = array(
        "vars" => array(),
        "request" => array()
    );
    if ( ((is_shop() || is_post_type_archive() || is_search() || is_home() ) && $wp_query->is_main_query()) || isset($wp_query->query_vars["post_type"]) || isset($wp_query->query_vars["qpt"])) {

        /*if(isset($wp_query->query_vars["ajax"])){
            if($wp_query->query_vars["ajax"] == "query"){
                return $output;
            }
        }
        if(is_admin()){
            return $output;
        }
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return $output;
        }
        if (defined('DOING_CRON') && DOING_CRON) {
            return;
        }
        if(is_prefetch_request()){
            return $output;
        }*/

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
            $qpt = get_query_var("qpt");
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
            $post_type = get_query_var("qpt");
        }
        
        //$pagination_type = $post_type=="any"?"search":$post_type;

        if(isset($pagination_query['vars'][$post_type]) || isset($pagination_query['request'][$post_type])){
            $enc = new Encrypt();
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
    // WooCommerce bağımlılıklarını kontrol et
    //if ( ! function_exists( 'wc_get_loop_prop' ) || ! function_exists( 'woocommerce_products_will_display' ) ) {
        //return;
    //}
    global $wp_query;
    $post_type = $wp_query->get( 'post_type' );
    if($post_type == "product" && function_exists( 'woocommerce_result_count' )){
        woocommerce_result_count();
        return;
    }
    
    $per_page = get_option( 'posts_per_page' );
    if(isset($GLOBALS["post_pagination"][$post_type])){
        $post_pagination = $GLOBALS["post_pagination"][$post_type];
        if($post_pagination["paged"]){
            $per_page = $post_pagination['posts_per_page'];
        }else{
            return;
        }
    }

    // Sayfalama özelliklerini al
    //$is_paginated = get_query_var( 'page' ) > 1;
    $total = $GLOBALS['wp_query']->found_posts;
    $current = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;

    // Sayfalama kontrolü
    if ( $total <= $per_page ) {
        return;
    }

    // Result count çıktısını oluştur
    echo '<div class="woocommerce-result-count result-count m-0 custom">';
    
    if ( 1 === $total ) {
        _e( 'Showing the single result', 'woocommerce' );
    } elseif ( $total <= $per_page || -1 === $per_page ) {
        /* translators: %d: total results */
        printf( _n( 'Showing all %d result', 'Showing all %d results', $total, 'woocommerce' ), $total );
    } else {
        $first = ( $per_page * $current ) - $per_page + 1;
        $last  = min( $total, $per_page * $current );
        /* translators: 1: first result 2: last result 3: total results */
        printf( _nx( 'Showing %1$d&ndash;%2$d of %3$d result', 'Showing %1$d&ndash;%2$d of %3$d results', $total, 'with first and last result', 'woocommerce' ), $first, $last, $total );
    }

    echo '</div>';
}


function header_has_dropdown(){
    $header_tools_dropdown = false;
    $header_contents = ["header_start", "header_center", "header_end"];
    foreach($header_contents as $header_content){
        $header_item = get_field($header_content, "options");
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
    $header_navigation = false;
    $header_contents = ["header_start", "header_center", "header_end"];
    foreach($header_contents as $header_content){
        $header_item = get_field($header_content, "options");
        if($header_item){
            if($header_item["type"] == "navigation" && !$header_navigation){
                $header_navigation = true;
            }
            if($header_item["type"] == "tools" && !$header_navigation){
                $header_tools = $header_item["header_tools"];
                $header_tools = $header_tools["header_tools"];
                if($header_tools){
                    foreach($header_tools as $header_tool){
                        if($header_tool["menu_type"] == "navigation"){
                            $header_navigation = true;
                            continue;
                        }
                    }
                }
            }
        }
    }
    return $header_navigation;
}
function header_footer_options(){
        // Header Options //
        $header_fixed = get_field("header_fixed", "options");
        $header_fixed = in_array($header_fixed, ["top","bottom","bottom-start"]) ? $header_fixed : false;
        if($header_fixed == "top"){
            $header_affix = get_field("header_affix", "options");
        }else{
            $header_affix = false;
        }

        $header_hide_on_scroll_down = get_field("header_hide_on_scroll_down", "options");
        $header_hide_on_scroll_down = $header_affix && $header_hide_on_scroll_down ? true : false;

        $header_container = get_field("header_container", "options");
        $header_container = block_container($header_container);//$header_container == "default" ? "" : $header_container;
        
        $header_start_type = "";
        $header_center_type = "";
        $header_end_type = "";

        $header_contents = ["header_start", "header_center", "header_end"];
        foreach($header_contents as $header_content){
            $header_item = get_field($header_content, "options");

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

        if($header_center_type != "empty"){
            $header_start_class = ($header_start_type != "empty" ? "flex-grow-0" : "flex-grow-0"). " flex-auto nav-equal nav-equal-{{equalize}}";
            $header_center_class = "flex-grow-1 h-100";
            $header_end_class = ($header_end_type != "empty" ? "flex-grow-0" : "flex-grow-0"). " flex-auto nav-equal nav-equal-{{equalize}}";
        }else{
            $header_start_class = ($header_start_type != "empty" ? "flex-shrink-1 -flex-grow-0" : "flex-grow-1"). " flex-auto";
            $header_center_class = "flex-grow-1 h-100";
            $header_end_class = ($header_end_type != "empty" ? "flex-shrink-1 -flex-grow-0" : "flex-grow-1"). " flex-auto";
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
        $footer_container = get_field("footer_container", "options");
        $footer_container = block_container($footer_container);//$footer_container == "default" ? "" : $footer_container;
        $footer_text = get_field("footer_text", "options");
        $footer_logo = get_field("logo_footer", "option");
        $footer_menu = get_field("footer_menu", "option");
        

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
            "menu" => $footer_menu
        );

        return array(
            "header" => $header_options,
            "footer" => $footer_options
        );
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

function add_lazyload_to_images($content) {
    if (is_single() || is_page()) {
        // srcset'i data-srcset olarak değiştir
        $content = preg_replace('/<img([^>]*)srcset=["\'](.*?)["\'](.*?)>/i', '<img$1data-srcset="$2"$3>', $content);
        
        // src özniteliğini data-src olarak güncelle
        $content = preg_replace_callback('/<img([^>]*)src=["\'](.*?)["\'](.*?)>/i', function($matches) {
            // Eski src ve diğer öznitelikler
            $old_attributes = $matches[1] . 'src="' . $matches[2] . '"' . $matches[3];
            
            // Yeni src ve öznitelikler
            $new_attributes = $matches[1] . 'data-src="' . $matches[2] . '"' . $matches[3];
            
            // Değiştir ve döndür
            return str_replace($old_attributes, $new_attributes, $matches[0]);
        }, $content);

        // Class özniteliğini güncelle
        $content = preg_replace('/<img([^>]*)class=["\'](.*?)["\'](.*?)>/i', '<img$1class="$2 lazy"$3>', $content);
    }
    return $content;
}
add_filter('the_content', 'add_lazyload_to_images', 99);

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
