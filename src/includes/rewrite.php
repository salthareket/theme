<?php 

function query_var_isset($var_name) {
    $array = $GLOBALS['wp_query']->query_vars;
    return array_key_exists($var_name, $array);
}

/**
 * TAXONOMY PREFIX REMOVER - SMART & FAST EDITION
 */
function get_sh_taxonomy_removals() {
    static $removals = null;
    if ($removals === null) {
        $removals = QueryCache::get_option("options_taxonomy_prefix_remove", []);
        if (empty($removals)) {
            $removals = [];
        } else {
            $removals = is_array($removals) ? $removals : (array)$removals;
        }
    }
    return $removals;
}

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
        $term = get_term_by('slug', $slug, $tax);
        
        if ($term && !is_wp_error($term)) {
            if ($is_attachment) unset($query['attachment']);
            else unset($query['name']);

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

            if ($tax === 'category') $query['category_name'] = $final_slug;
            elseif ($tax === 'post_tag') $query['tag'] = $final_slug;
            else $query[$tax] = $final_slug;

            break;
        }
    }
    return $query;
}, 1, 1);

add_filter('term_link', function($url, $term, $taxonomy) {
    $taxonomies = get_sh_taxonomy_removals();
    if (in_array($taxonomy, $taxonomies)) {
        return str_replace('/' . $taxonomy . '/', '/', $url);
    }
    return $url;
}, 10, 3);

add_action('template_redirect', function() {
    if (is_admin()) return;
    $taxonomies = get_sh_taxonomy_removals();
    if (empty($taxonomies)) return;
    $uri = $_SERVER['REQUEST_URI'];
    foreach ($taxonomies as $tax) {
        if (strpos($uri, '/' . $tax . '/') !== false) {
            $new_url = str_replace('/' . $tax . '/', '/', home_url($uri));
            wp_redirect($new_url, 301);
            exit;
        }
    }
});


/**
 * REWRITE RULES — Search + Download
 */
add_action('init', function() {
    $regex_start = '^';
    if (defined('ENABLE_MULTILANGUAGE') && ENABLE_MULTILANGUAGE) {
        if (class_exists('Data') && Data::get("language_url_view")) {
            $regex_start .= Data::get("language") . '/';
        }
    }

    add_rewrite_rule($regex_start . 'downloads/([^/]+)/?$', 'index.php?file_id=$matches[1]', 'top');
    add_rewrite_rule($regex_start . '([^/]+)/search/([^/]+)/([^/]+)/([^/]+)/page/([0-9]+)/?$', 'index.php?pagename=$matches[1]&qpt_settings=$matches[2]&qpt=$matches[3]&q=$matches[4]&paged=$matches[5]', 'top');
    add_rewrite_rule($regex_start . '([^/]+)/search/([^/]+)/([^/]+)/page/([0-9]+)/?$', 'index.php?pagename=$matches[1]&qpt_settings=$matches[2]&q=$matches[3]&paged=$matches[4]', 'top');
    add_rewrite_rule($regex_start . '([^/]+)/search/([^/]+)/page/([0-9]+)/?$', 'index.php?pagename=$matches[1]&q=$matches[2]&qpt=search&paged=$matches[3]', 'top');
    add_rewrite_rule($regex_start . '([^/]+)/search/([^/]+)/?$', 'index.php?pagename=$matches[1]&q=$matches[2]&qpt=search', 'top');
}, 10);

add_filter('query_vars', function($vars) {
    $vars[] = 'file_id';
    $vars[] = 'q';
    $vars[] = 'qpt';
    $vars[] = 'qpt_settings';
    return $vars;
});

/**
 * CUSTOM SEARCH TEMPLATE
 */
add_filter('template_include', function($template) {
    $search_query = get_query_var('q');
    if (!empty($search_query)) {
        $page_template = locate_template(['page.php']);
        if ($page_template) return $page_template;
    }
    return $template;
}, 99);

add_action('pre_get_posts', function($query) {
    if (is_admin() || !$query->is_main_query()) return;

    $q = get_query_var('q');
    $qpt = get_query_var('qpt');
    $qpt_settings = get_query_var('qpt_settings');

    if (!empty($q)) {
        $query->set('s', $q);
        $post_type = ($qpt === 'search' || empty($qpt)) ? 'any' : $qpt;
        $query->set('post_type', $post_type);

        $posts_per_page = -1;
        if (class_exists('Data')) {
            $config_path = "post_pagination." . ($qpt_settings ?: $post_type);
            $posts_per_page = Data::get($config_path . ".posts_per_page") ?: 10;
        }
        $query->set('posts_per_page', $posts_per_page);

        $query->is_404 = false;
        $query->is_search = true;
        $query->is_archive = false;
        $query->is_single = false;
        $query->is_page = true;
    }
});

/**
 * DOWNLOAD SYSTEM — Chunked, secure, whitelist
 */
function download_get_file($file_path = "", $mime_type = "") {
    if (!$file_path || !file_exists($file_path)) {
        wp_redirect(home_url());
        exit;
    }
    while (ob_get_level()) { ob_end_clean(); }
    if (empty($mime_type)) {
        $mime_type = wp_check_filetype($file_path)['type'] ?: 'application/octet-stream';
    }
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_path));
    $file = fopen($file_path, 'rb');
    while (!feof($file)) {
        echo fread($file, 8192);
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

    $file_path = get_attached_file($file_id);
    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $whitelist = apply_filters("download_allowed_file_types", ['jpg','jpeg','png','gif','pdf','doc','docx','xls','xlsx','zip','rar','txt','csv']);

    if (!in_array($ext, $whitelist)) {
        wp_redirect(wp_get_attachment_url($file_id));
        exit;
    }
    if ($ext === 'php' || $ext === 'phtml') {
        wp_redirect(home_url());
        exit;
    }

    download_get_file($file_path, $post->post_mime_type);
});
