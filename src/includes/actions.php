<?php

// Timber posts kontrolü
add_action('template_redirect', 'check_timber_posts');
function check_timber_posts() {
    global $wp_query;
    if (!isset($wp_query->posts) || empty($wp_query->posts)) {
        $wp_query->posts = array(); // Boş bir dizi olarak ayarla
    }
}

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


function ns_filter_avatar($avatar, $id_or_email, $size, $default, $alt, $args) {
    $headers = @get_headers( $args['url'] );
    if( ! preg_match("|200|", $headers[0] ) ) {
        return;
    }
    return $avatar; 
}
//add_filter('get_avatar','ns_filter_avatar', 10, 6);



//remove dashicons
function wpdocs_dequeue_dashicon() {
    if (current_user_can( 'update_core' )) {
        return;
    }
    wp_deregister_style('dashicons');
}
add_action( 'wp_enqueue_scripts', 'wpdocs_dequeue_dashicon' );



function old_style_name_like_wpse_123298($clauses) {
    remove_filter('term_clauses','old_style_name_like_wpse_123298');
	$pattern = '|(name LIKE )\'{.*?}(.+{.*?})\'|';
	$clauses['where'] = preg_replace($pattern,'$1 \'$2\'',$clauses['where']);
	return $clauses;
}
add_filter('terms_clauses','old_style_name_like_wpse_123298');



// görsel kayudederken gorselin ortalama renk degerini ve bu rengin kontrastını kaydet
function extract_and_save_average_color($post_ID) {
    if (get_post_mime_type($post_ID) === 'image/jpeg' || get_post_mime_type($post_ID) === 'image/png' || get_post_mime_type($post_ID) === 'image/webp') {
        $image_path = get_attached_file($post_ID);
        $colors = get_image_average_color($image_path);
        update_post_meta($post_ID, 'average_color', $colors["average_color"]);
        update_post_meta($post_ID, 'contrast_color', $colors["contrast_color"]);
    }
}
add_action('add_attachment', 'extract_and_save_average_color');


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









function post_prev_next_order ( $order_by, $post, $order ) {
    global $wpdb;
    return "ORDER BY p.post_title ASC LIMIT 1";
}
//add_filter ( 'get_next_post_sort', 'post_prev_next_order', 10, 3 );
//add_filter ( 'get_previous_post_sort', 'post_prev_next_order', 10, 3 );





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
     

//remove empty <p> tags
function remove_empty_p( $content ) {
    $content = force_balance_tags( $content );
    $content = preg_replace( '#<p>\s*+(<br\s*/*>)?\s*</p>#i', '<br/>', $content );
    $content = preg_replace( '~\s?<p>(\s|&nbsp;)+</p>\s?~', '<br/>', $content );
    return $content;
}
//add_filter('the_content', 'remove_empty_p', 20, 1);


/*add responsive classes to embeds*/
function responsive_embed_oembed_html($html, $url, $attr, $post_id) {
      if (strpos($url, 'youtube.')||strpos($url, 'youtu.be')||strpos($url, 'vimeo.')||strpos($url, 'dailymotion.')){
         return '<div class="ratio ratio-16x9">' . $html . '</div>';
      }else{
         return $html;  
      }
}
add_filter('embed_oembed_html', 'responsive_embed_oembed_html', 99, 4);

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



function search_distinct() {
    return "DISTINCT";
}
add_filter("posts_distinct", "search_distinct");



function keep_me_logged_in_for_1_year( $expirein ) {
    return 31556926; // 1 year in seconds
}
add_filter( 'auth_cookie_expiration', 'keep_me_logged_in_for_1_year' );


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








// Admin profil sayfasına Title alanını "Name" başlıklı alana ekleyelim
add_action('show_user_profile', 'add_title_field', 0);
add_action('edit_user_profile', 'add_title_field', 0);

function add_title_field($user) {
    ?>
    <div class="postbox">
        <div class="postbox-header"><h2 id="user-title">User Title</h2></div>
        <table class="form-table m-0">
            <tr>
                <th><label for="title"><?php _e('Title'); ?></label></th>
                <td>
                    <input type="text" name="title" id="title" value="<?php echo esc_attr(get_user_meta($user->ID, 'title', true)); ?>" class="regular-text" /><br />
                </td>
            </tr>
        </table>
    </div>
    <?php
}

// Title alanının kaydedilmesi
add_action('personal_options_update', 'save_title_field');
add_action('edit_user_profile_update', 'save_title_field');

function save_title_field($user_id) {
    if (current_user_can('edit_user', $user_id)) {
        update_user_meta($user_id, 'title', $_POST['title']);
    }
}






function scss_variables_padding($padding=""){
    $padding = trim($padding);
    $padding = str_replace("px", " ", $padding);
    $padding = str_replace("  ", " ", $padding);
    $padding = explode(" ", $padding);
    $padding = trim(implode("px ", $padding))."px";
    $padding = str_replace("pxpx", "px", $padding);
    return $padding;
}
function scss_variables_color($value=""){
    if(empty($value)){
        $value = "transparent";
    }
    return $value;
}
function scss_variables_boolean($value=""){
    if(empty($value)){
        $value = "false";
    }else{
        $value = "true";
    }
    return $value;
}
function scss_variables_image($balue=""){
    if(empty($value)){
        $value = "none";
    }
    return $value;
}
function scss_variables_array($array=array()){
    $temp = array();
    foreach($array as $key => $item){
        $temp[] = $key."---".$item;
    }
    $temp = implode("___", $temp);
    $temp = preg_replace('/\s+/', '', $temp);
    return $temp;
}
function scss_variables_font($font = ""){
    if(!empty($font)){
        $font = str_replace("|", "", $font);
    }
    return $font;
}
function wp_scss_set_variables(){
    $host_url = get_stylesheet_directory_uri();
    if(ENABLE_PUBLISH){
        if(function_exists("WPH_activated")){
                $wph_settings = get_option("wph_settings");
                $new_theme_path = "";
                if(isset($wph_settings["module_settings"]["new_theme_path"])){
                    $new_theme_path = $wph_settings["module_settings"]["new_theme_path"];
                }
                if(!empty($new_theme_path)){
                    $host_url = PUBLISH_URL."/".$new_theme_path;
                }
        }else{
            $host_url = str_replace(get_host_url(), PUBLISH_URL, $host_url);
        }
    }

    $variables = [
        "host_url" => "'" . $host_url . "'",
        "woocommerce" => class_exists("WooCommerce") ? "true" : "false",
        "yobro" => class_exists("Redq_YoBro") ? "true" : "false",
        "mapplic" => class_exists("Mapplic") ? "true" : "false",
        "newsletter" => class_exists("Newsletter") ? "true" : "false",
        "yasr" => function_exists("yasr_fs") ? "true" : "false",
        "apss" => class_exists("APSS_Class") ? "true" : "false",
        "cf7" => class_exists("WPCF7") ? "true" : "false",
        "enable_multilanguage" => boolval(ENABLE_MULTILANGUAGE) ? "true" : "false",
        "enable_favorites" => boolval(ENABLE_FAVORITES) ? "true" : "false",
        "enable_follow" => boolval(ENABLE_FOLLOW) ? "true" : "false",
        "enable_cart" => boolval(ENABLE_CART) ? "true" : "false",
        "enable_filters" => boolval(ENABLE_FILTERS) ? "true" : "false",
        "enable_membership" => boolval(ENABLE_MEMBERSHIP) ? "true" : "false",
        "enable_chat" => boolval(ENABLE_CHAT) ? "true" : "false",
        "enable_notifications" => boolval(ENABLE_NOTIFICATIONS) ? "true" : "false",
        "enable_sms_notifications" => boolval(ENABLE_NOTIFICATIONS) && boolval(ENABLE_SMS_NOTIFICATIONS) ? "true" : "false",
        "search_history" => boolval(ENABLE_SEARCH_HISTORY) ? "true" : "false",
        "logo" => "'" . get_field("logo", "option") . "'",
        "dropdown_notification" => boolval(header_has_dropdown()) ? "true" : "false",
        "node_modules_path" =>  '"' . str_replace('\\', '/', NODE_MODULES_PATH) . '"',
        "theme_static_path" =>  '"' . str_replace('\\', '/', THEME_STATIC_PATH) . '"'

    ];

    error_log(print_r($variables['theme_static_path'], true));

    
    if(file_exists(get_stylesheet_directory() ."/static/js/js_files_all.json")){
        $plugins = file_get_contents(get_stylesheet_directory() ."/static/js/js_files_all.json");
        if($plugins){
           $variables["plugins"] = str_replace(array("[", "]"), "", $plugins);
        }        
    }

    $variables = get_theme_styles($variables);

    return $variables;
}
add_filter("wp_scss_variables", "wp_scss_set_variables");




function get_theme_styles($variables = array()){
    $theme_styles = acf_get_theme_styles();
    if($theme_styles){

        $path = THEME_STATIC_PATH . 'data/theme-styles';
        if(!is_dir($path)){
            mkdir($path, 0755, true);
        }

        // Typography
        $headings_font = scss_variables_font($theme_styles["typography"]["font_family"]);
        $variables["header_font"] = $headings_font;
        $headings = $theme_styles["typography"]["headings"];
        foreach($headings as $key => $heading){
            $variables["typography_".$key."_font"] = $headings_font;
            $variables["typography_".$key."_size"] = acf_units_field_value($heading["font_size"]);
            $variables["typography_".$key."_weight"] = $heading["font_weight"];
        }

        $title_sizes = [];
        $title_mobile_sizes = [];
        $title_line_heights = [];
        $title_mobile_line_heights = [];

        foreach ($theme_styles["typography"]["title"] as $key => $breakpoint) {
            $title_sizes[] = "size: $key, font-size: ".acf_units_field_value($breakpoint);
        }

        foreach ($theme_styles["typography"]["title_mobile"] as $key => $breakpoint) {
            $title_mobile_sizes[] = "size: $key, font-size: ".acf_units_field_value($breakpoint);
        }

        foreach ($theme_styles["typography"]["title_line_height"] as $key => $breakpoint) {
            $line_height = acf_units_field_value($breakpoint);
            $title_line_heights[] = "size: $key, line-height: $line_height";

            $fs = $theme_styles["typography"]["title"][$key]["value"];
            $lh = $breakpoint["value"];
            $mobile_fs = $theme_styles["typography"]["title_mobile"][$key]["value"];

            if (!empty($fs) && !empty($mobile_fs) && !empty($lh)) {
                $mobile_lh = ($mobile_fs * $lh) / $fs;
                $title_mobile_line_heights[] = "size: $key, line-height: ".($mobile_lh)."px";
            }
        }

        $variables["title_sizes"] = "(".implode("), (", $title_sizes).")";
        $variables["title_mobile_sizes"] = "(".implode("), (", $title_mobile_sizes).")";
        $variables["title_line_heights"] = "(".implode("), (", $title_line_heights).")";
        $variables["title_mobile_line_heights"] = "(".implode("), (", $title_mobile_line_heights).")";


        // Body
        $body = $theme_styles["body"];
        $variables["font-primary"] = scss_variables_font($body["primary_font"]);
        $variables["font-secondary"] = scss_variables_font($body["secondary_font"]);
        $variables["base-font-size"] = acf_units_field_value($body["font_size"]);        
        $variables["base-font-weight"] = $body["font_weight"];
        $variables["base-letter-spacing"] = acf_units_field_value($body["letter_spacing"]);
        $variables["base-font-color"] = scss_variables_color($body["color"]);
        $variables["body-bg-color"] = scss_variables_color($body["bg_color"]);
        $variables["body-bg-backdrop"] = scss_variables_color($body["backdrop_color"]);

        // Button Sizes
        $buttons = $theme_styles["buttons"];
        if ($buttons["custom"]) {
            $button_sizes = [];
            foreach ($buttons["custom"] as $key => $size) {
                $button_sizes[] = "size: ".$size['size'].
                                  ", padding_x: ".acf_units_field_value($size['padding_x']).
                                  ", padding_y: ".acf_units_field_value($size['padding_y']).
                                  ", font-size: ".acf_units_field_value($size['font_size']).
                                  ", border-radius: ".acf_units_field_value($size['border_radius']);
            }
            $variables["button-sizes"] = "(".implode("), (", $button_sizes).")";
        }

        // Header
        $header = $theme_styles["header"];
        $header_general = $header["header"];
        //$variables["header-fixed"] = scss_variables_boolean($header_general["fixed"]);
        $variables["header-dropshadow"] = scss_variables_boolean($header_general["dropshadow"]);
        //$variables["header-hide-on-scroll-down"] = scss_variables_boolean($header_general["hide_on_scroll_down"]);
        $variables["header-z-index"] = $header_general["z_index"];
        $variables["header-bg"] = scss_variables_color($header_general["bg_color"]);
        $variables["header-bg-affix"] = scss_variables_color($header_general["bg_color_affix"]);

        $variables["header-height"] = acf_units_field_value($header_general["height"][array_keys($header_general["height"])[0]]);
        foreach($header_general["height"] as $key => $breakpoint){
            $variables["header-height-".$key] = acf_units_field_value($breakpoint);
        }

        $variables["header-height-affix"] = acf_units_field_value($header_general["height_affix"][array_keys($header_general["height_affix"])[0]]);
        foreach($header_general["height_affix"] as $key => $breakpoint){
            $variables["header-height-".$key."-affix"] = acf_units_field_value($breakpoint);;
        }


        // Nav Bar
        $header_navbar = $header["navbar"];
        $variables["header-navbar-bg"] = scss_variables_color($header_navbar["bg_color"]);
        $variables["header-navbar-bg-affix"] = scss_variables_color($header_navbar["bg_color_affix"]);
        $variables["header-navbar-align-hr"] = $header_navbar["align_hr"];
        $variables["header-navbar-align-vr"] = $header_navbar["align_vr"];

            $height_header = $header_navbar["height_header"]; // is same with header

        $variables["header-navbar-height"] = acf_units_field_value($header_navbar["height"][array_keys($header_navbar["height"])[0]]);
        foreach($header_navbar["height"] as $key => $breakpoint){
            $variables["header-navbar-height-".$key] = acf_units_field_value($breakpoint);
        }
        
        $variables["header-navbar-height-affix"] = acf_units_field_value($header_navbar["height_affix"][array_keys($header_navbar["height_affix"])[0]]);
        foreach($header_navbar["height_affix"] as $key => $breakpoint){
            $variables["header-navbar-height-".$key."-affix"] = acf_units_field_value($breakpoint);
        }
       
        $variables["header-navbar-padding"] = $header_navbar["padding"][array_keys($header_navbar["padding"])[0]];
        foreach($header_navbar["padding"] as $key => $breakpoint){
            $variables["header-navbar-padding-".$key] = scss_variables_padding($breakpoint);
        }

        $variables["header-navbar-padding-affix"] = $header_navbar["padding_affix"][array_keys($header_navbar["padding_affix"])[0]];
        foreach($header_navbar["padding_affix"] as $key => $breakpoint){
            $variables["header-navbar-padding-".$key."-affix"] = scss_variables_padding($breakpoint);
        }


        // Nav
        $header_nav = $header["nav"];
        $variables["header-navbar-nav-width"] = $header_nav["width"];
        $variables["header-navbar-nav-margin"] = $header_nav["margin"];

        $variables["header-navbar-nav-align-hr"] = $header_nav["align_hr"][array_keys($header_nav["align_hr"])[0]];
        foreach($header_nav["align_hr"] as $key => $breakpoint){
            $variables["header-navbar-nav-align-hr-".$key] = $breakpoint;
        }

        $variables["header-navbar-nav-align-vr"] = $header_nav["align_vr"][array_keys($header_nav["align_vr"])[0]];
        foreach($header_nav["align_vr"] as $key => $breakpoint){
            $variables["header-navbar-nav-align-vr-".$key] = $breakpoint;
        }

            $height_header = $header_nav["height_header"]; // is same with header

        $variables["header-navbar-nav-height"] = acf_units_field_value($header_nav["height"][array_keys($header_nav["height"])[0]]);
        foreach($header_nav["height"] as $key => $breakpoint){
            $variables["header-navbar-nav-height-".$key] = acf_units_field_value($breakpoint);
        }

        $variables["header-navbar-nav-height-affix"] = acf_units_field_value($header_nav["height_affix"][array_keys($header_nav["height_affix"])[0]]);
        foreach($header_nav["height_affix"] as $key => $breakpoint){
            $variables["header-navbar-nav-height-".$key."-affix"] = acf_units_field_value($breakpoint);
        }


        // Nav Item
        $header_nav_item = $header["nav_item"];
        $variables["header-navbar-nav-font"] = scss_variables_font($header_nav_item["font_family"]);
        $variables["nav_font"] = scss_variables_font($header_nav_item["font_family"]);
        $variables["header-navbar-nav-font-weight"] = $header_nav_item["font_weight"];
        $variables["header-navbar-nav-font-weight-active"] = $header_nav_item["font_weight_active"];
        $variables["header-navbar-nav-font-text-transform"] = $header_nav_item["text_transform"];
        $variables["header-navbar-nav-font-letter-spacing"] = acf_units_field_value($header_nav_item["letter_spacing"]);
        $variables["header-navbar-nav-font-color"] = scss_variables_color($header_nav_item["color"]);
        $variables["header-navbar-nav-font-color-hover"] = scss_variables_color($header_nav_item["color_hover"]);
        $variables["header-navbar-nav-font-color-active"] = scss_variables_color($header_nav_item["color_active"]);
        $variables["header-navbar-nav-bg-color"] = scss_variables_color($header_nav_item["bg_color"]);
        $variables["header-navbar-nav-bg-color-hover"] = scss_variables_color($header_nav_item["bg_color_hover"]);

        $variables["header-navbar-nav-item-padding"] = $header_nav_item["padding"][array_keys($header_nav_item["padding"])[0]];
        foreach($header_nav_item["padding"] as $key => $breakpoint){
            $variables["header-navbar-nav-item-padding-".$key] = scss_variables_padding($breakpoint);
        }

        $variables["header-navbar-nav-font-size"] = acf_units_field_value($header_nav_item["font_size"][array_keys($header_nav_item["font_size"])[0]]);
        foreach($header_nav_item["font_size"] as $key => $breakpoint){
            $variables["header-navbar-nav-font-size-".$key] = acf_units_field_value($breakpoint);
        }


        // Dropdown
        $header_dropdown = $header["dropdown"];
        $header_dropdown_arrow = $header_dropdown["arrow"];
        $variables["header-navbar-nav-dropdown-root-arrow"] = scss_variables_boolean($header_dropdown_arrow["arrow"]);
        $variables["header-navbar-nav-dropdown-root-arrow-top"] = $header_dropdown_arrow["top"];
        $variables["header-navbar-nav-dropdown-root-arrow-left"] = $header_dropdown_arrow["left"];

        $header_dropdown_general = $header_dropdown["dropdown"];
        $variables["header-navbar-nav-dropdown-align"] = $header_dropdown_general["align_vr"];
        $variables["header-navbar-nav-dropdown-bg"] = scss_variables_color($header_dropdown_general["bg_color"]);
        $variables["header-navbar-nav-dropdown-width"] = $header_dropdown_general["width"];
        $variables["header-navbar-nav-dropdown-margin"] = $header_dropdown_general["margin"];
        $variables["header-navbar-nav-dropdown-top"] = $header_dropdown_general["top"];
        $variables["header-navbar-nav-dropdown-padding"] = $header_dropdown_general["padding"];
        $variables["header-navbar-nav-dropdown-border"] = $header_dropdown_general["border"];
        $variables["header-navbar-nav-dropdown-border-radius"] = $header_dropdown_general["border_radius"];

        $header_dropdown_item = $header_dropdown["dropdown_item"];
        $variables["header-navbar-nav-dropdown-font-size"] = acf_units_field_value($header_dropdown_item["font_size"]);
        $variables["header-navbar-nav-dropdown-font-color"] = scss_variables_color($header_dropdown_item["color"]);
        $variables["header-navbar-nav-dropdown-font-color-hover"] = scss_variables_color($header_dropdown_item["color_hover"]);
        $variables["header-navbar-nav-dropdown-font-weight"] = $header_dropdown_item["font_weight"];
        $variables["header-navbar-nav-dropdown-font-weight-hover"] = $header_dropdown_item["font_weight_hover"];
        $variables["header-navbar-nav-dropdown-font-text-transform"] = $header_dropdown_item["text_transform"];
        $variables["header-navbar-nav-dropdown-item-padding"] = $header_dropdown_item["padding"];
        $variables["header-navbar-nav-dropdown-item-bg"] = scss_variables_color($header_dropdown_item["bg_color"]);
        $variables["header-navbar-nav-dropdown-item-bg-hover"] = scss_variables_color($header_dropdown_item["bg_color_hover"]);
        $variables["header-navbar-nav-dropdown-item-border"] = $header_dropdown_item["border"];
        $variables["header-navbar-nav-dropdown-item-border-radius"] = $header_dropdown_item["border_radius"];

        // Logo
        $header_logo = $header["logo"];
        $variables["header-navbar-logo-color"] = scss_variables_color($header_logo["color"]);
        $variables["header-navbar-logo-color-affix"] = scss_variables_color($header_logo["color_affix"]);
        $variables["header-navbar-logo-align-hr"] = $header_logo["align_hr"];
        $variables["header-navbar-logo-align-vr"] = $header_logo["align_vr"];

        $variables["header-navbar-logo-padding"] = $header_logo["padding"][array_keys($header_logo["padding"])[0]];
        foreach($header_logo["padding"] as $key => $breakpoint){
            $variables["header-navbar-logo-padding-".$key] = $breakpoint;
        }

        $variables["header-navbar-logo-padding-affix"] = $header_logo["padding_affix"][array_keys($header_logo["padding_affix"])[0]];
        foreach($header_logo["padding_affix"] as $key => $breakpoint){
            $variables["header-navbar-logo-padding-".$key."-affix"] = $breakpoint;
        }


        // Footer
        $footer = $theme_styles["footer"];
        $variables["footer-height"] = acf_units_field_value($footer["height"]);
        $variables["footer-padding"] = $footer["padding"];
        $variables["footer-color"] = scss_variables_color($footer["color"]);
        $variables["footer-color-link"] = scss_variables_color($footer["link_color"]);
        $variables["footer-color-link-hover"] = scss_variables_color($footer["link_color_hover"]);
        $variables["footer-bg-color"] = scss_variables_color($footer["bg_color"]);
        $variables["footer-bg-image"] = scss_variables_image($footer["bg_image"]);


        // Breadcrumb
        $breadcrumb = $theme_styles["breadcrumb"];
        $variables["breadcrumb-item-font-family"] = scss_variables_font($breadcrumb["font_family"]);
        $variables["breadcrumb-item-font-size"] = acf_units_field_value($breadcrumb["font_size"]);
        $variables["breadcrumb-item-font-weight"] = $breadcrumb["font_weight"];
        $variables["breadcrumb-item-line-height"] = $breadcrumb["line_height"];
        $variables["breadcrumb-item-letter-spacing"] = acf_units_field_value($breadcrumb["letter_spacing"]);
        $variables["breadcrumb-item-text-transform"] = $breadcrumb["text_transform"];
        $variables["breadcrumb-item-color"] = scss_variables_color($breadcrumb["color"]);
        $variables["breadcrumb-item-color-hover"] = scss_variables_color($breadcrumb["color_hover"]);
        $variables["breadcrumb-sep-color"] = scss_variables_color($breadcrumb["seperator_color"]);


        // Pagination
        $pagination = $theme_styles["pagination"];
        $pagination_general = $pagination["pagination"];
        $variables["pagination-align"] = $pagination_general["align_vr"];

        $pagination_item = $pagination["item"];
        $variables["pagination-font-family"] = scss_variables_font($pagination_item["font_family"]);
        $variables["pagination-font-size"] = acf_units_field_value($pagination_item["font_size"]);
        $variables["pagination-font-weight"] = $pagination_item["font_weight"];
        $variables["pagination-font-weight-active"] = $pagination_item["font_weight_active"];
        $variables["pagination-item-color"] = scss_variables_color($pagination_item["color"]);
        $variables["pagination-item-color-hover"] = scss_variables_color($pagination_item["color_hover"]);
        $variables["pagination-item-color-active"] = scss_variables_color($pagination_item["color_active"]);
        $variables["pagination-item-bg-color"] = scss_variables_color($pagination_item["bg_color"]);
        $variables["pagination-item-bg-color-hover"] = scss_variables_color($pagination_item["bg_color_hover"]);
        $variables["pagination-item-bg-color-active"] = scss_variables_color($pagination_item["bg_color_active"]);
        $variables["pagination-item-border"] = $pagination_item["border"];
        $variables["pagination-item-border-hover"] = $pagination_item["border_hover"];
        $variables["pagination-item-border-active"] = $pagination_item["border_active"];
        $variables["pagination-item-border-radius"] = $pagination_item["border_radius"];

        $pagination_nav= $pagination["nav"];
        $variables["pagination-nav-font-family"] = scss_variables_font($pagination_nav["font_family"]);
        $variables["pagination-nav-font-size"] = acf_units_field_value($pagination_nav["font_size"]);
        $variables["pagination-nav-color"] = scss_variables_color($pagination_nav["color"]);
        $variables["pagination-nav-color-hover"] = scss_variables_color($pagination_nav["color_hover"]);
        $variables["pagination-nav-color-disabled"] = scss_variables_color($pagination_nav["color_disabled"]);
        $variables["pagination-nav-bg-color"] = scss_variables_color($pagination_nav["bg_color"]);
        $variables["pagination-nav-bg-color-hover"] = scss_variables_color($pagination_nav["bg_color_hover"]);
        $variables["pagination-nav-border"] = $pagination_nav["border"];
        $variables["pagination-nav-border-hover"] = $pagination_nav["border_hover"];
        $variables["pagination-nav-border-active"] = $pagination_nav["border_active"];
        $variables["pagination-nav-border-radius"] = acf_units_field_value($pagination_nav["border_radius"]);
        $variables["pagination-nav-prev-text"] = $pagination_nav["prev_text"];
        $variables["pagination-nav-next-text"] = $pagination_nav["next_text"];
        $variables["pagination-item-gap"] = acf_units_field_value($pagination_nav["gap"]);


        // Hero
        $hero = $theme_styles["hero"];
        $variables["hero-height"] = acf_units_field_value($hero["height"][array_keys($hero["height"])[0]]);
        foreach($hero["height"] as $key => $breakpoint){
            $variables["hero-height-".$key] = acf_units_field_value($breakpoint);
        }


        // Offcanvas
        $offcanvas = $theme_styles["offcanvas"];
        $offcanvas_general = $offcanvas["offcanvas"];
        $variables["offcanvas-bg"] = scss_variables_color($offcanvas_general["bg_color"]);
        $variables["offcanvas-padding"] = $offcanvas_general["padding"];
        $variables["offcanvas-align-hr"] = $offcanvas_general["align_hr"];
        $variables["offcanvas-align-vr"] = $offcanvas_general["align_vr"];

        $offcanvas_header = $offcanvas["header"];
        $variables["offcanvas-header-font"] = scss_variables_font($offcanvas_header["font_family"]);
        $variables["offcanvas-header-font-size"] = acf_units_field_value($offcanvas_header["font_size"]);
        $variables["offcanvas-header-font-weight"] = $offcanvas_header["font_weight"];
        $variables["offcanvas-header-color"] = scss_variables_color($offcanvas_header["color"]);
        $variables["offcanvas-header-padding"] = $offcanvas_header["padding"];
        $variables["offcanvas-header-icon-font-size"] = acf_units_field_value($offcanvas_header["icon_font_size"]);
        $variables["offcanvas-header-icon-color"] = scss_variables_color($offcanvas_header["icon_color"]);

        $offcanvas_nav_item = $offcanvas["nav_item"];
        $variables["offcanvas-item-font"] = scss_variables_font($offcanvas_nav_item["font_family"]);
        $variables["offcanvas-item-font-size"] = acf_units_field_value($offcanvas_nav_item["font_size"]);
        $variables["offcanvas-item-font-weight"] = $offcanvas_nav_item["font_weight"];
        $variables["offcanvas-item-color"] = scss_variables_color($offcanvas_nav_item["color"]);
        $variables["offcanvas-item-color-hover"] = scss_variables_color($offcanvas_nav_item["color_hover"]);
        $variables["offcanvas-item-bg"] = scss_variables_color($offcanvas_nav_item["bg_color"]);
        $variables["offcanvas-item-bg-hover"] = scss_variables_color($offcanvas_nav_item["bg_color_hover"]);
        $variables["offcanvas-item-padding"] = $offcanvas_nav_item["padding"];
        $variables["offcanvas-item-align-hr"] = $offcanvas_nav_item["align_hr"];

        $offcanvas_nav_sub = $offcanvas["nav_sub"];
        $variables["offcanvas-dropdown-bg"] = scss_variables_color($offcanvas_nav_sub["bg_color"]);
        $variables["offcanvas-dropdown-padding"] = $offcanvas_nav_sub["padding"];

        $offcanvas_nav_sub_item = $offcanvas["nav_sub_item"];
        $variables["offcanvas-dropdown-item-font-size"] = acf_units_field_value($offcanvas_nav_sub_item["font_size"]);
        $variables["offcanvas-dropdown-item-font-color"] = scss_variables_color($offcanvas_nav_sub_item["color"]);
        $variables["offcanvas-dropdown-item-font-color-hover"] = scss_variables_color($offcanvas_nav_sub_item["color_hover"]);
        $variables["offcanvas-dropdown-item-font-weight"] = $offcanvas_nav_sub_item["font_weight"];
        $variables["offcanvas-dropdown-item-font-weight-hover"] = $offcanvas_nav_sub_item["font_weight_hover"];
        $variables["offcanvas-dropdown-item-padding"] = $offcanvas_nav_sub_item["padding"];
        $variables["offcanvas-dropdown-item-bg"] = scss_variables_color($offcanvas_nav_sub_item["bg_color"]);
        $variables["offcanvas-dropdown-item-bg-hover"] = scss_variables_color($offcanvas_nav_sub_item["bg_color_hover"]);
        $variables["offcanvas-dropdown-item-border"] = $offcanvas_nav_sub_item["border"];


        // Header Tools
        $header_tools = $theme_styles["header_tools"];
        $header_tools_general = $header_tools["header_tools"];

            $height_header = $header_tools_general["height_header"]; // is same with header

        $variables["header-tools-height"] = acf_units_field_value($header_tools_general["height"][array_keys($header_tools_general["height"])[0]]);
        foreach($header_tools_general["height"] as $key => $breakpoint){
            $variables["header-tools-height-".$key] = acf_units_field_value($breakpoint);
        }

        $variables["header-tools-height-affix"] = acf_units_field_value($header_tools_general["height_affix"][array_keys($header_tools_general["height_affix"])[0]]);
        foreach($header_tools_general["height_affix"] as $key => $breakpoint){
            $variables["header-tools-height-".$key."-affix"] = acf_units_field_value($breakpoint);
        }

        $variables["header-tools-item-gap"] = acf_units_field_value($header_tools_general["gap"][array_keys($header_tools_general["gap"])[0]]);
        foreach($header_tools_general["gap"] as $key => $breakpoint){
            $variables["header-tools-item-gap-".$key] = acf_units_field_value($breakpoint);
        }

        $header_tools_social = $header_tools["social"];
        $variables["header-social-font"] = scss_variables_font($header_tools_social["font_family"]);
        $variables["header-social-font-size"] = acf_units_field_value($header_tools_social["font_size"]);
        $variables["header-social-color"] = scss_variables_color($header_tools_social["color"]);
        $variables["header-social-color-hover"] = scss_variables_color($header_tools_social["color_hover"]);
        $variables["header-social-gap"] = acf_units_field_value($header_tools_social["gap"]);

        $header_tools_icons = $header_tools["icons"];
        $variables["header-icon-font"] = scss_variables_font($header_tools_icons["font_family"]);
        $variables["header-icon-font-size"] = acf_units_field_value($header_tools_icons["font_size"]);
        $variables["header-icon-color"] = scss_variables_color($header_tools_icons["color"]);
        $variables["header-icon-color-hover"] = scss_variables_color($header_tools_icons["color_hover"]);
        $variables["header-icon-dot-color"] = scss_variables_color($header_tools_icons["dot_color"]);

        $header_tools_link = $header_tools["link"];
        $variables["header-link-font"] = scss_variables_font($header_tools_link["font_family"]);
        $variables["header-link-font-size"] = acf_units_field_value($header_tools_link["font_size"]);
        $variables["header-link-font-weight"] = $header_tools_link["font_weight"];
        $variables["header-link-color"] = scss_variables_color($header_tools_link["color"]);
        $variables["header-link-color-hover"] = scss_variables_color($header_tools_link["color_hover"]);
        $variables["header-link-color-active"] = scss_variables_color($header_tools_link["color_active"]);

        $header_tools_button = $header_tools["button"];
        $variables["header-btn-font"] = scss_variables_font($header_tools_button["font_family"]);
        $variables["header-btn-font-size"] = acf_units_field_value($header_tools_button["font_size"]);
        $variables["header-btn-font-weight"] = $header_tools_button["font_weight"];

        $header_tools_language = $header_tools["language"];
        $variables["header-language-font"] = scss_variables_font($header_tools_language["font_family"]);
        $variables["header-language-font-size"] = acf_units_field_value($header_tools_language["font_size"]);
        $variables["header-language-font-weight"] = $header_tools_language["font_weight"];
        $variables["header-language-color"] = scss_variables_color($header_tools_language["color"]);
        $variables["header-language-color-hover"] = scss_variables_color($header_tools_language["color_hover"]);
        $variables["header-language-color-active"] = scss_variables_color($header_tools_language["color_active"]);

        $header_tools_toggler = $header_tools["toggler"];
        $variables["header-navbar-toggler-color"] = scss_variables_color($header_tools_toggler["color"]);
        $variables["header-navbar-toggler-color-hover"] = scss_variables_color($header_tools_toggler["color_hover"]);

        $header_tools_counter = $header_tools["counter"];
        $variables["notification-count-color"] = scss_variables_color($header_tools_counter["color"]);
        $variables["notification-count-bg-color"] = scss_variables_color($header_tools_counter["bg_color"]);

        $variables["breakpoints"] = "'" . implode(",", array_keys($GLOBALS["breakpoints"])) . "'";

        //Utilities
        $scroll_to_top = $theme_styles["utilities"]["scroll_to_top"];
        $variables["scroll-to-top-active"] = $scroll_to_top["active"];
        if($scroll_to_top["active"]){
            $variables["scroll-to-top-show"] = $scroll_to_top["show"];
            $variables["scroll-to-top-hr"] = $scroll_to_top["position_hr"];
            $variables["scroll-to-top-vr"] = $scroll_to_top["position_vr"];
            $variables["scroll-to-top-bg-color"] = $scroll_to_top["bg_color"];
            $variables["scroll-to-top-bg-color-hover"] = $scroll_to_top["bg_color_hover"];
            $variables["scroll-to-top-color"] = $scroll_to_top["color"];
            $variables["scroll-to-top-color-hover"] = $scroll_to_top["color_hover"];
            $variables["scroll-to-top-width"] = $scroll_to_top["width"];
            $variables["scroll-to-top-height"] = $scroll_to_top["height"];
            $variables["scroll-to-top-radius"] = acf_units_field_value($scroll_to_top["radius"]);
            $variables["scroll-to-top-gap"] = acf_units_field_value($scroll_to_top["gap"]);
            $variables["scroll-to-top-font-size"] = acf_units_field_value($scroll_to_top["font_size"]);
            $variables["scroll-to-top-duration"] = $scroll_to_top["duration"];            
        }

        $pattern = '/class="([^"]*)"/';
        $classes = [];
        if (preg_match($pattern, $scroll_to_top["icon"], $matches)) {
            if (!empty($matches[1])) {
                $classes = explode(' ', $matches[1]);
            }
        }
        update_dynamic_css_whitelist($classes);

    }
    return $variables;
}


function get_pages_need_updates($updated_plugins){
    global $wpdb;
    $pages = [];
    $like_statements = [];
    foreach ($updated_plugins as $term) {
        $like_statements[] = $wpdb->prepare("meta_value LIKE %s", '%' . $wpdb->esc_like($term) . '%');
    }
    $like_conditions = implode(" OR ", $like_statements);

    $query = "
        (SELECT post_id as id, 'post' as type FROM $wpdb->postmeta WHERE meta_key = 'assets' AND ($like_conditions))
        UNION
        (SELECT term_id as id, 'term' as type FROM $wpdb->termmeta WHERE meta_key = 'assets' AND ($like_conditions))
        UNION
        (SELECT comment_id as id, 'comment' as type FROM $wpdb->commentmeta WHERE meta_key = 'assets' AND ($like_conditions))
        UNION
        (SELECT user_id as id, 'user' as type FROM $wpdb->usermeta WHERE meta_key = 'assets' AND ($like_conditions))
    ";
    $results = $wpdb->get_results($query);
    foreach ($results as $result) {
        $pages[] = ["id" => intval($result->id), "type" => $result->type];
    }

    // Archive Control
    $like_clauses = [];
    foreach ($updated_plugins as $term) {
        $like_clauses[] = $wpdb->prepare("option_value LIKE %s", '%' . $wpdb->esc_like($term) . '%');
    }
    
    $results = [];
    if(ENABLE_MULTILANGUAGE){
        if(ENABLE_MULTILANGUAGE == "polylang"){
            $languages = pll_the_languages(['raw' => 1]);
            foreach ($languages as $lang) {
                $post_types = get_post_types(['public' => true], 'objects');
                foreach ($post_types as $post_type) {
                    if ($post_type->has_archive) {
                        $option_name = "{$post_type->name}_{$lang['slug']}_assets";
                        $query = $wpdb->prepare(
                            "SELECT option_value FROM `{$wpdb->options}` 
                            WHERE option_name = %s AND (" . implode(' OR ', $like_clauses) . ")",
                            $option_name
                        );
                        $option_value = $wpdb->get_var($query);
                        if ($option_value) {
                            foreach ($search_terms as $term) {
                                if (stripos($option_value, $term) !== false) {
                                    $pages[] = [
                                        'id' => $lang['slug'],
                                        'type' => $post_type->name
                                    ];
                                }
                            }
                        }
                    }
                }
            }            
        }
    }

    $pages = array_unique($pages, SORT_REGULAR); // Tekrarları kaldır ve sonuçları döndür

    $urls = [];
    foreach($pages as $page){
        if(is_string($page["id"])){
            $url = pll_get_post_type_archive_link($page["type"], $page["id"]);
            $urls[$page["type"]."_".$page["id"]] = [
                "type" => "archive",
                "url"  => $url
            ];
        }else{
            switch($page["type"]){
                case "post" :
                   $url = get_permalink($page["id"]); 
                break;
                case "term":
                    $url = get_term_link($page["id"]); // Term linkini al
                    break;

                case "comment":
                    // Yorumların kendilerine özgü bir bağlantısı yoktur; eğer gerekli bir URL varsa, bunu belirlemelisin
                    $url = ''; // Yorumlar için spesifik bir bağlantı yoksa boş bırak
                    break;

                case "user":
                    $url = get_author_posts_url($page["id"]); // Kullanıcı arşiv sayfasının URL'sini al
                    break;
            }
            $urls[$page["id"]] = [
                "type" => $page["type"],
                "url" => $url
            ];
        }
    }

    $extractor = new PageAssetsExtractor();
    return $extractor->fetch_urls($urls);
}