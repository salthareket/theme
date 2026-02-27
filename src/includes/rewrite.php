<?php 

function query_var_isset($var_name) {
    $array = $GLOBALS['wp_query']->query_vars;
    return array_key_exists($var_name, $array);
}

/**
 * TAXONOMY PREFIX REMOVER - SMART & FAST EDITION
 */

// 1. Ayarları tek seferde çekip static cache'e alalım
function get_sh_taxonomy_removals() {
    static $removals = null;
    if ($removals === null) {
        error_log("options_taxonomy_prefix_remove bırda cekliyo");
        $removals = QueryCache::get_option("options_taxonomy_prefix_remove", []);
    }
    return $removals;
}

// REQUEST: URL'den gelen temiz slug'ı WP'nin anlayacağı dile çevir (DB Dostu)
add_filter('request', function($query) {
    $taxonomies = get_sh_taxonomy_removals();
    if (empty($taxonomies) || is_admin()) return $query;

    $slug = "";
    $is_attachment = false;

    if (isset($query['attachment'])) {
        $slug = $query['attachment'];
        $is_attachment = true;
    } elseif (isset($query['name'])) {
        $slug = $query['name'];
    }

    if (empty($slug)) return $query;

    foreach ($taxonomies as $tax) {
        // DB'ye gitmeden önce WP'nin object cache'ini kullanan get_term_by
        $term = get_term_by('slug', $slug, $tax);
        
        if ($term && !is_wp_error($term)) {
            // Gerekli unset işlemlerini yap
            if ($is_attachment) unset($query['attachment']);
            else unset($query['name']);

            // Hiyerarşik taksonomiler için parent slug'ları birleştir (Sadece gerekiyorsa)
            $final_slug = $slug;
            if ($is_attachment && $term->parent) {
                $ancestors = get_ancestors($term->term_id, $tax);
                $slug_parts = [];
                foreach (array_reverse($ancestors) as $ancestor_id) {
                    $ancestor = get_term($ancestor_id, $tax);
                    $slug_parts[] = $ancestor->slug;
                }
                $slug_parts[] = $slug;
                $final_slug = implode('/', $slug_parts);
            }

            // Query var atamaları
            if ($tax === 'category') $query['category_name'] = $final_slug;
            elseif ($tax === 'post_tag') $query['tag'] = $final_slug;
            else $query[$tax] = $final_slug;

            break; // Bulduk, diğer taksonomilere bakmaya gerek yok amk
        }
    }
    return $query;
}, 1, 1);

// TERM LINK: URL üretilirken prefix'i şak diye sil
add_filter('term_link', function($url, $term, $taxonomy) {
    $taxonomies = get_sh_taxonomy_removals();
    if (in_array($taxonomy, $taxonomies)) {
        // Sadece ilgili taksonomi slug'ını URL'den uçur
        return str_replace('/' . $taxonomy . '/', '/', $url);
    }
    return $url;
}, 10, 3);

// REDIRECT: Eski (prefixli) URL gelirse yenisine (prefixsiz) fırlat
add_action('template_redirect', function() {
    if (is_admin()) return;

    $taxonomies = get_sh_taxonomy_removals();
    if (empty($taxonomies)) return;

    $uri = $_SERVER['REQUEST_URI'];
    foreach ($taxonomies as $tax) {
        // URL'de "/category/test" gibi bir yapı var mı?
        if (strpos($uri, '/' . $tax . '/') !== false) {
            $new_url = str_replace('/' . $tax . '/', '/', home_url($uri));
            wp_redirect($new_url, 301);
            exit;
        }
    }
});


add_action('init', function() {
    // 1. Regex başlangıcını senin Data mantığına göre kuruyoruz
    $regex_start = '^';
    if (defined('ENABLE_MULTILANGUAGE') && ENABLE_MULTILANGUAGE) {
        // Senin Data class'ından dili çekiyoruz, pll falan yok siktir et onları
        if (class_exists('Data') && Data::get("language_url_view")) {
            $regex_start .= Data::get("language") . '/';
        }
    }

    // 2. DOWNLOAD KURALI
    add_rewrite_rule(
        $regex_start . 'downloads/([^/]+)/?$',
        'index.php?file_id=$matches[1]',
        'top'
    );

    // 3. ARAMA KURALLARI (Uzundan kısaya sıralama hayati!)
    // settings + post_type + query + pagination
    add_rewrite_rule(
        $regex_start . '([^/]+)/search/([^/]+)/([^/]+)/([^/]+)/page/([0-9]+)/?$',
        'index.php?pagename=$matches[1]&qpt_settings=$matches[2]&qpt=$matches[3]&q=$matches[4]&paged=$matches[5]',
        'top'
    );

    // settings + query + pagination
    add_rewrite_rule(
        $regex_start . '([^/]+)/search/([^/]+)/([^/]+)/page/([0-9]+)/?$',
        'index.php?pagename=$matches[1]&qpt_settings=$matches[2]&q=$matches[3]&paged=$matches[4]',
        'top'
    );

    // Native search + pagination
    add_rewrite_rule(
        $regex_start . '([^/]+)/search/([^/]+)/page/([0-9]+)/?$',
        'index.php?pagename=$matches[1]&q=$matches[2]&qpt=search&paged=$matches[3]',
        'top'
    );

    // Düz search
    add_rewrite_rule(
        $regex_start . '([^/]+)/search/([^/]+)/?$',
        'index.php?pagename=$matches[1]&q=$matches[2]&qpt=search',
        'top'
    );

    // DİKKAT: flush_rewrite_rules() BURADA OLMAYACAK!
    // Sadece admin panelinden bir ayar değiştiğinde tetikle ya da bir kere manuel yap.
}, 10);

// Query monitor'de bu değişkenleri gör diye ekle
add_filter('query_vars', function($vars) {
    $vars[] = 'file_id';
    $vars[] = 'q';
    $vars[] = 'qpt';
    $vars[] = 'qpt_settings';
    return $vars;
});

/**
 * CUSTOM SEARCH TEMPLATE REDIRECT - PERFORMANCE EDITION
 */
add_filter('template_include', function($template) {
    // 1. Get query var (Global yerine get_query_var kullanmak daha "smart")
    $search_query = get_query_var('q');
    
    // 2. Sadece bizim özel arama parametremiz varsa devreye gir
    if (!empty($search_query)) {
        
        // Bu bir arama sayfasıdır diye WP'ye fısılda (bazı eklentiler anlasın)
        // is_search() true dönerse search.php'ye gitmek ister, o yüzden manuel zorluyoruz.
        
        $page_template = locate_template(['page.php']);
        
        if ($page_template) {
            return $page_template;
        }
    }

    return $template;
}, 99); // Önceliği yüksek tutalım ki diğer eklentiler araya girmesin


/**
 * CUSTOM SEARCH REDIRECT - OPTIMIZED VERSION
 * Artık regex ile URL parçalamıyoruz, WP'nin ayırdığı query_vars'ı kullanıyoruz.
 */
add_action('pre_get_posts', function($query) {
    // Sadece ana sorguda ve admin panelinde değilsek çalış
    if (is_admin() || !$query->is_main_query()) return;

    // Bizim rewrite kurallarından gelen değişkenleri kontrol et
    $q = get_query_var('q');
    $qpt = get_query_var('qpt');
    $qpt_settings = get_query_var('qpt_settings');

    if (!empty($q)) {
        // 1. Arama terimini ayarla
        $query->set('s', $q);

        // 2. Post tipini belirle
        $post_type = ($qpt === 'search' || empty($qpt)) ? 'any' : $qpt;
        $query->set('post_type', $post_type);

        // 3. Sayfalama (Pagination) ayarı
        $posts_per_page = -1;
        if (class_exists('Data')) {
            $config_path = "post_pagination." . ($qpt_settings ?: $post_type);
            $posts_per_page = Data::get($config_path . ".posts_per_page") ?: 10;
        }
        $query->set('posts_per_page', $posts_per_page);

        // 4. 404'ü engelle (Çünkü bu bir arama sayfası)
        $query->is_404 = false;
        $query->is_search = true;
        $query->is_archive = false;
        $query->is_single = false;
        $query->is_page = true; // Sayfa şablonunda kalması için
    }
});







/**
 * HIGH-PERFORMANCE & SECURE DOWNLOAD SYSTEM
 */
function download_get_file($file_path = "", $mime_type = "") {
    if (!$file_path || !file_exists($file_path)) {
        wp_redirect(home_url());
        exit;
    }

    // 1. Çıktı tamponu temizliği (Hayati!)
    while (ob_get_level()) {
        ob_end_clean();
    }

    // 2. Mime Type Ayarı
    if (empty($mime_type)) {
        $mime_type = wp_check_filetype($file_path)['type'] ?: 'application/octet-stream';
    }

    // 3. Headerlar (Tarayıcıyı ikna etme)
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_path));

    // 4. RAM DOSTU OKUMA (Sikiş burada): readfile yerine chunked okuma
    $file = fopen($file_path, 'rb');
    while (!feof($file)) {
        echo fread($file, 8192); // 8KB'lık parçalarla oku, RAM'i şişirme
        flush();
    }
    fclose($file);
    exit;
}

add_action('template_redirect', function() {
    $file_id = get_query_var('file_id');
    if (!$file_id) return;

    $post = get_post($file_id);
    if (!$post || $post->post_type !== 'attachment') {
        wp_redirect(home_url());
        exit;
    }

    // Güvenli dosya yolu alımı (GUID yerine bu!)
    $file_path = get_attached_file($file_id);
    
    // Beyaz liste kontrolü (Daha temiz)
    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $whitelist = apply_filters("download_allowed_file_types", ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar', 'txt', 'csv']);

    if (!in_array($ext, $whitelist)) {
        // İzin yoksa direkt dosya URL'sine salla, tarayıcı baksın başının çaresine
        wp_redirect(wp_get_attachment_url($file_id));
        exit;
    }

    // PHP dosyası falan sızmasın diye ekstra koruma
    if ($ext === 'php' || $ext === 'phtml') {
        wp_redirect(home_url());
        exit;
    }

    download_get_file($file_path, $post->post_mime_type);
});



/*
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
}*/


/*
function theme_rewrite_rules() {
    flush_rewrite_rules();

    $regex_start = '^';
    if(ENABLE_MULTILANGUAGE){
        if(Data::get("language_url_view")){
            $regex_start .= Data::get("language"). '/';
        }
    }

    //add_rewrite_rule( 
    //     $regex_start . 'product/([^/]*)/color-([^/]*)/?$',
    //    'index.php?posttype=product&product=$matches[1]&attribute_pa_color=$matches[2]',
    //    'top' 
    //);

    
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
                if(Data::get("post_pagination.{$post_type}")){
                    $posts_per_page = Data::get("post_pagination.{$post_type}.posts_per_page");
                }
                $args = array(
                    'post_type' => $post_type=="search"?"any":$post_type,
                    's' => $search_term,
                    'paged' => $paged,
                    'posts_per_page' => $posts_per_page
                );
                $query = new WP_Query($args);
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
*/