<?php 

function query_var_isset($var_name) {
    $array = $GLOBALS['wp_query']->query_vars;
    return array_key_exists($var_name, $array);
}


add_filter('request', 'rudr_change_term_request', 1, 1 );
function rudr_change_term_request($query){
    $taxonomy_prefix_remove = get_option("options_taxonomy_prefix_remove");
    if($taxonomy_prefix_remove){
        $name = "";
        if( isset($query['attachment']) ){
            $include_children = true;
            $name = $query['attachment'];
        }else if(isset($query['name'])){
            $include_children = false;
            $name = $query['name'];
        }
        if(!empty($name)){
            foreach($taxonomy_prefix_remove as $taxonomy){
                $term = get_term_by('slug', $name, $taxonomy); // get the current term to make sure it exists
                if (isset($name) && $term && !is_wp_error($term)){ // check it here
                    
                    if( $include_children ) {
                        unset($query['attachment']);
                        $parent = $term->parent;
                        while( $parent ) {
                            $parent_term = get_term( $parent, $taxonomy);
                            $name = $parent_term->slug . '/' . $name;
                            $parent = $parent_term->parent;
                        }
                    } else {
                        unset($query['name']);
                    }
                    
                    switch( $taxonomy ){
                        case 'category':
                            $query['category_name'] = $name; // for categories
                            break;
                        case 'post_tag':
                            $query['tag'] = $name; // for post tags
                            break;
                        default:
                            $query[$taxonomy] = $name; // for another taxonomies
                            break;
                    }
                }
            }            
        }

    }
    return $query;
}

add_filter( 'term_link', 'rudr_term_permalink', 10, 3 );
function rudr_term_permalink( $url, $term, $taxonomy ){
    $taxonomy_prefix_remove = get_option("options_taxonomy_prefix_remove");
    if($taxonomy_prefix_remove){
        foreach($taxonomy_prefix_remove as $taxonomy){
            $taxonomy_name = $taxonomy;
            $taxonomy_slug = $taxonomy;
            if ( strpos($url, $taxonomy_slug) === FALSE || $taxonomy != $taxonomy_name ) return $url;
            $url = str_replace('/' . $taxonomy_slug, '', $url);
            return $url;
        }
    }
    return $url;
}

add_action('template_redirect', 'rudr_old_term_redirect');
function rudr_old_term_redirect() {
    $taxonomy_prefix_remove = get_option("options_taxonomy_prefix_remove");
    if($taxonomy_prefix_remove){
        foreach($taxonomy_prefix_remove as $taxonomy){
            $taxonomy_name = $taxonomy;
            $taxonomy_slug = $taxonomy;
            if( strpos( $_SERVER['REQUEST_URI'], $taxonomy_slug ) === FALSE){
                return;
            }
            if( ( is_category() && $taxonomy_name == 'category' ) || ( is_tag() && $taxonomy_name == 'post_tag' ) || is_tax( $taxonomy_name ) ){
                wp_redirect( site_url( str_replace($taxonomy_slug, '', $_SERVER['REQUEST_URI']) ), 301 );
                exit();
            }
        }
    }
}



function theme_rewrite_rules() {
    flush_rewrite_rules();

    $regex_start = '^';
    if(ENABLE_MULTILANGUAGE){
        if($GLOBALS["language_url_view"]){
            $regex_start .= $GLOBALS["language"]. '/';
        }
    }

    /*add_rewrite_rule( 
         $regex_start . 'product/([^/]*)/color-([^/]*)/?$',
        'index.php?posttype=product&product=$matches[1]&attribute_pa_color=$matches[2]',
        'top' 
    );*/

    
    add_rewrite_rule(
        $regex_start . 'downloads/([^/]+)/?$',
        'index.php?file_id=$matches[1]',
        'top'
    );

    
    // search in native
    add_rewrite_rule(
        $regex_start . '([^/]+)/search/([^/]+)/page/([0-9]+)/?$',
        'index.php?pagename=$matches[1]&q=$matches[2]&qpt=search&paged=$matches[3]',
        'top'
    );
    add_rewrite_rule(
        $regex_start . '([^/]+)/search/([^/]+)/?$',
        'index.php?pagename=$matches[1]&q=$matches[2]&qpt=search',
        'top'
    );
    
    // search in page with post_type's settings
    add_rewrite_rule(
        $regex_start . '([^/]+)/search/([^/]+)/([^/]+)/([^/]+)/page/([0-9]+)/?$',
        'index.php?pagename=$matches[1]&qpt_settings=$matches[2]&qpt=$matches[3]&q=$matches[4]&paged=$matches[5]',
        'top'
    );
    add_rewrite_rule(
        $regex_start . '([^/]+)/search/([^/]+)/([^/]+)/page/([0-9]+)/?$',
        'index.php?pagename=$matches[1]&qpt_settings=$matches[2]&q=$matches[3]&paged=$matches[4]',
        'top'
    );
    add_rewrite_rule(
        $regex_start . '([^/]+)/search/([^/]+)/([^/]+)/([^/]+)/?$',
        'index.php?pagename=$matches[1]&qpt_settings=$matches[2]&qpt=$matches[3]&q=$matches[4]',
        'top'
    );
    add_rewrite_rule(
        $regex_start . '([^/]+)/search/([^/]+)/([^/]+)/?$',
        'index.php?pagename=$matches[1]&qpt_settings=$matches[2]&q=$matches[3]',
        'top'
    );

    // search in page
    add_rewrite_rule(
        $regex_start . '([^/]+)/search/([^/]+)/([^/]+)/page/([0-9]+)/?$',
        'index.php?pagename=$matches[1]&qpt=$matches[2]&q=$matches[3]&paged=$matches[4]',
        'top'
    );
    add_rewrite_rule(
        $regex_start . '([^/]+)/search/([^/]+)/([^/]+)/?$',
        'index.php?pagename=$matches[1]&qpt=$matches[2]&q=$matches[3]',
        'top'
    );  
}
//add_action('init', 'theme_rewrite_rules');
register_activation_hook(__FILE__, 'theme_rewrite_rules');



function custom_search_template($template) {
    global $wp_query;
    // Eğer "search" parametreleri varsa, normal page şablonunu kullan
    if (isset($wp_query->query_vars['q'])) {
        $new_template = locate_template(array('page.php', 'index.php'));
        if (!empty($new_template)) {
            return $new_template;
        }
    }
    return $template;
}
add_filter('template_include', 'custom_search_template');


function handle_custom_search_redirect() {
    global $wp, $wp_query;

    // Eğer $wp global değişkeni tanımlı değilse, işleme devam etme
    if (!isset($wp) || !isset($wp->request)) {
        return;
    }

    // Mevcut URL'nin path'ini al
    $current_url = home_url(add_query_arg(array(), $wp->request));
    $parsed_url = parse_url($current_url);
    $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';

    $site_url = home_url('/');
    $base_path = parse_url($site_url, PHP_URL_PATH);

    // URL path'inden base path'i kaldır
    if (strpos($path, $base_path) === 0) {
        $path = substr($path, strlen($base_path));
    }
    $path = "/" . $path;
    $path = str_replace("//", "/", $path);


    // URL'nin /search/ içerip içermediğini kontrol et
    //if (preg_match('#^/([^/]+)/search/([^/]+)/([^/]+)/?$#', $path, $matches) || 
    //    preg_match('#^/([^/]+)/search/([^/]+)/?$#', $path, $matches)) {
    if (preg_match('#^/([^/]+)/search/([^/]+)/([^/]+)(/page/([0-9]+))?/?$#', $path, $matches)) {
        $page_slug = $matches[1];  // Sayfa slug'ı
        $post_type = isset($matches[2]) ? $matches[2] : ''; // Post türü
        $search_term = isset($matches[3]) ? $matches[3] : $matches[2]; // Arama terimi
        $paged = isset($matches[5]) ? intval($matches[5]) : 1;
    }
     if (preg_match('#^/([^/]+)/search/([^/]+)(/page/([0-9]+))?/?$#', $path, $matches)) {
        $page_slug = $matches[1];  // Sayfa slug'ı
        $post_type = "search";//isset($matches[2]) ? $matches[2] : ''; // Post türü
        $search_term = isset($matches[2]) ? $matches[2] : ""; // Arama terimi
        $paged = isset($matches[4]) ? intval($matches[4]) : 1;
    }

    if($matches){
        // Sayfanın var olup olmadığını kontrol et
        //if (get_page_by_path($page_slug)) {
            global $post;
            $post = get_page_by_path($page_slug);
            setup_postdata($post);  // Post verilerini yükle

            // Sayfa varsa, arama parametreleri ile sorgu ayarla
            if ($post) {
                $posts_per_page = -1;
                if(isset($GLOBALS["post_pagination"][$post_type])){
                    $posts_per_page = $GLOBALS["post_pagination"][$post_type]["posts_per_page"];
                }
                $args = array(
                    'post_type' => $post_type=="search"?"any":$post_type,
                    's' => $search_term,
                    'paged' => $paged,
                    'posts_per_page' => $posts_per_page
                );
                $query = SaltBase::get_cached_query($args);
                if ( $query->have_posts() ) {
                    $wp_query->posts = $query->posts;//array_merge($wp_query->posts, $query->posts);
                    $wp_query->post_count = count($wp_query->posts);
                    $wp_query->found_posts = $query->found_posts;
                    $wp_query->is_singular = true;
                    $wp_query->is_page = true;
                    //$wp_query->set("posts_per_page", $posts_per_page);
                    //$wp_query->set("numberposts", $posts_per_page);
                    //$wp_query->max_num_pages = 1;
                }
                wp_reset_postdata();
            }

            // Sayfanın 404 görünümünü engelle
            status_header(200);
            $wp_query->is_404 = false;
        //}
    }
}
//add_action('template_redirect', 'handle_custom_search_redirect', 1);














function download_check_file() {
    $file_id = get_query_var('file_id');
    if (isset($file_id)) {
        $post = get_post($file_id);
        if ($post) {
            if ($post->post_type === 'attachment' && strpos($post->post_mime_type, 'image/') === 0) {
                // Resim dosyaları için GUID değerinden indir
                $file_url = $post->guid;
                $upload_dir = wp_upload_dir();
                $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $file_url);
                
                if (!$file_path || !file_exists($file_path)) {
                    wp_redirect(home_url());
                    exit;
                }
                download_get_file($file_path, $post->post_mime_type);
            } else {
                // Diğer dosya türleri için indir
                $file = get_attached_file($file_id);
                if (!$file || !file_exists($file)) {
                    wp_redirect(home_url());
                    exit;
                }

                $file_url = wp_get_attachment_url($file_id);
                $fileName = strtolower($file_url);

                // Dosya türü beyaz listesini genişlet
                $whitelist = apply_filters("download_allowed_file_types", array('jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar', 'txt', 'csv'));

                if (!in_array(pathinfo($fileName, PATHINFO_EXTENSION), $whitelist)) {
                    wp_redirect($file_url);
                    exit;
                }

                if (strpos($file_url, '.php') !== false) {
                    wp_redirect($file_url);
                    exit;
                }

                download_get_file($file, $post->post_mime_type);
            }
        } else {
            wp_redirect(home_url());
            exit;
        }
    }
}
function download_get_file($file = "", $mime_type = "") {
    if ($file) {
        // Güvenlik başlıklarını ekleyelim
        header('Content-Description: File Transfer');
        header('Content-Type: application/force-download'); // Tarayıcıların dosyayı açmasını önlemek için
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));
        
        // Çıktı tamponunu temizleyip zorlayalım
        if (ob_get_length()) {
            ob_end_clean(); // Tüm tamponları temizleyelim
        }
        flush();

        // Dosyayı oku ve indir
        readfile($file);
        exit;
    }
}
function download_redirect_file() {
    global $wp_query;
    if (isset($wp_query->query_vars['file_id']) && !empty($wp_query->query_vars['file_id'])) {
        download_check_file();
    }
}
add_action('template_redirect', 'download_redirect_file');