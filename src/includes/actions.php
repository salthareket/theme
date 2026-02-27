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

/**
 * Pagination için izin verilen anahtarları süzer.
 */
function query_vars_for_pagination($query_vars) {
    $allowed = ["page", "orderby", "order", "post_type", "paged", "meta_query", "tax_query", "posts_per_page", "s"];
    return array_filter($query_vars, fn($key) => in_array($key, $allowed), ARRAY_FILTER_USE_KEY);
}

/**
 * Ana sorgu verilerini toplar. Static cache ile sadece bir kez çalışır.
 */
function pagination_query_request() {
    static $request_cache = null;
    if ($request_cache !== null) return $request_cache;

    global $wp_query;
    $output = ["vars" => [], "request" => []];

    // Ana sorgu geçerli mi?
    $is_valid_main = (is_shop() || is_post_type_archive() || is_search() || is_home()) && $wp_query->is_main_query();
    
    if ($is_valid_main || isset($wp_query->query_vars["post_type"]) || isset($wp_query->query_vars["qpt"])) {

        $query_vars = query_vars_for_pagination($wp_query->query_vars);
        
        // QueryString işlemini bir kez yap
        if (function_exists('queryStringJSON')) {
            $query_vars["querystring"] = json_decode(queryStringJSON(), true);
        }

        // Tax ve Meta Query'leri tek seferde al
        if (!empty($wp_query->tax_query->queries)) {
            $query_vars['tax_query'] = $wp_query->tax_query->queries;
        }
        
        if (!empty($wp_query->meta_query->queries)) {
            $query_vars['meta_query'] = $wp_query->meta_query->queries;
        }

        // Post Type Belirleme
        if ($wp_query->is_posts_page() || empty($query_vars["post_type"])) {
            $query_vars["post_type"] = "post";
        }

        $post_type = is_search() ? "search" : $query_vars["post_type"];

        if (isset($wp_query->query_vars["post_type"])) {
            $qpt = get_query_var("qpt", $post_type);
            $qpt = (is_array($qpt) || empty($qpt) || $qpt == "search" || is_numeric($qpt)) ? "any" : $qpt;
            
            if (defined('EXCLUDE_FROM_SEARCH') && EXCLUDE_FROM_SEARCH && $qpt == "any") {
                $post_types = get_post_types(['public' => true], 'names');
                foreach ((array)EXCLUDE_FROM_SEARCH as $ex_type) {
                    unset($post_types[$ex_type]);
                }
                $qpt = array_values($post_types); // İndisleri sıfırla
            }
            $post_type = $qpt;
            $query_vars["post_type"] = $post_type;
        }

        $lookup_type = ($post_type == "any" || is_array($post_type)) ? "search" : $post_type;
        $pagination = function_exists('get_post_type_pagination') ? get_post_type_pagination($lookup_type) : null;

        if ($pagination) {
            if (empty($pagination["paged"])) {
                return $output; // Paged kapalıysa boş dön
            }
            $query_vars['posts_per_page'] = $pagination["posts_per_page"];
        }

        $output['vars'][$lookup_type] = $query_vars;

        // Gereksiz request datasını sadece filtrelerde tut
        if (isset($_GET["yith_wcan"]) || isset($_GET['orderby'])) {
            $output['request'][$lookup_type] = $wp_query->request;
        }
    }

    $request_cache = $output;
    return $output;
}

/**
 * Şifrelenmiş pagination verisini döner.
 */
function pagination_query() {
    global $wp_query;
    static $final_cache = null;
    if ($final_cache !== null) return $final_cache;

    $pagination_data = pagination_query_request();
    
    $post_type = $wp_query->get('post_type');
    if ($wp_query->is_posts_page() || empty($post_type)) {
        $post_type = "post";
    }

    if (is_search()) $post_type = "search";
    
    $qpt = get_query_var("qpt");
    if (!empty($qpt)) $post_type = $qpt;

    $post_type = is_array($post_type) ? $post_type[0] : (empty($post_type) ? "post" : $post_type);
    
    // "any" veya karmaşık arama durumunda anahtar "search" olmalı
    $lookup_key = ($post_type == "any" || is_search()) ? "search" : $post_type;

    $res_vars = "";
    $res_req  = "";

    if (isset($pagination_data['vars'][$lookup_key]) || isset($pagination_data['request'][$lookup_key])) {
        static $enc = null;
        if (!$enc && class_exists('Encrypt')) $enc = new Encrypt();
        
        if ($enc) {
            if (isset($pagination_data['vars'][$lookup_key])) {
                $res_vars = $enc->encrypt($pagination_data['vars'][$lookup_key]);
            }
            if (isset($pagination_data['request'][$lookup_key])) {
                $res_req = $enc->encrypt($pagination_data['request'][$lookup_key]);
            }
        }
    }

    $final_cache = ["vars" => $res_vars, "request" => $res_req];
    return $final_cache;
}

function custom_result_count() {
    global $wp_query;

    // 1. Post Type Belirle
    $post_type = $wp_query->get('post_type') ?: "post";
    if (is_array($post_type)) $post_type = reset($post_type);

    // 2. Per Page Değerini Al (Statik Cache ile DB'ye vuruşu azaltıyoruz)

    static $default_posts_per_page = null;
    $per_page = Data::get("post_pagination.{$post_type}.posts_per_page") ?? null;
    
    if (!$per_page) {
        if ($default_posts_per_page === null) {
            $default_posts_per_page = (int) get_option('posts_per_page', 10);
        }
        $per_page = $default_posts_per_page;
    }

    // 3. WooCommerce Kontrolü (Zaten varsa ona bırakalım)
    if ($post_type === "product" && function_exists('woocommerce_result_count')) {
        woocommerce_result_count();
        return;
    }

    // 4. Veri Kontrolü
    $total = (int) $wp_query->found_posts;
    $current = max(1, (int) get_query_var('paged', 1));

    // Eğer gösterilecek bir şey yoksa veya tek sayfaysa sessizce çık
    if ($total <= $per_page && $current === 1) return;

    // 5. Hesaplama
    $first = ($per_page * ($current - 1)) + 1;
    $last  = min($total, $per_page * $current);

    echo '<div class="woocommerce-result-count result-count m-0 custom">';
    if ($total <= 1) {
        _e('Showing the single result', 'woocommerce');
    } else {
        printf(
            _nx(
                'Showing %1$d&ndash;%2$d of %3$d result',
                'Showing %1$d&ndash;%2$d of %3$d results',
                $total,
                'with first and last result',
                'woocommerce'
            ),
            $first,
            $last,
            $total
        );
    }
    echo '</div>';
}

/**
 * Bu yardımcı fonksiyonlar artık DB'ye gitmeyecek.
 * header_footer_options içinden gelen hazır veriyi kontrol edecekler.
 */

function header_has_dropdown($header_options = null) {
    if (!$header_options) $header_options = header_footer_options();
    
    foreach (['start', 'center', 'end'] as $pos) {
        $section = $header_options['header'][$pos];
        if ($section['type'] == 'tools' && isset($section['tools'])) {
            // Tools içindeki her bir aracı kontrol et
            foreach ($section['tools'] as $tool) {
                if (isset($tool['menu_type']) && $tool['menu_type'] == 'dropdown') {
                    return true;
                }
            }
        }
    }
    return false;
}
function header_has_navigation($header_options = null) {
    if (!$header_options) $header_options = header_footer_options();

    foreach (['start', 'center', 'end'] as $pos) {
        $section = $header_options['header'][$pos];
        // Direkt navigasyon mu?
        if ($section['type'] == 'navigation') return true;
        
        // Yoksa tools içinde dropdown navigasyon mu?
        if ($section['type'] == 'tools' && isset($section['tools'])) {
            foreach ($section['tools'] as $tool) {
                if (isset($tool['menu_item']) && $tool['menu_item'] == 'navigation' && $tool['menu_type'] == 'dropdown') {
                    return true;
                }
            }
        }
    }
    return false;
}
/**
 * Header ve Footer ayarlarını tek bir pakette toplar.
 * JSON varsa oradan okur, yoksa DB'den çeker ve JSON oluşturur.
 * * @param bool $save True gönderilirse JSON dosyasını zorunlu günceller.
 */
function header_footer_options($save = false) {
    $json_path = THEME_STATIC_PATH . 'data/header-footer-options.json';

    // 1. ADIM: JSON varsa ve güncelleme istenmiyorsa direkt dosyadan oku (0 Sorgu)
    if (!$save && file_exists($json_path)) {
        $content = file_get_contents($json_path);
        return json_decode($content, true);
    }

    // 2. ADIM: JSON yoksa veya $save=true ise DB'den verileri topla
    // get_option zaten arka planda ACF Bulk Option sistemini kullanır.
    
    // --- Header Ayarları ---
    $header_fixed = QueryCache::get_field("header_fixed", "options");//get_option("options_header_fixed");
    $header_fixed = in_array($header_fixed, ["top", "bottom", "bottom-start"]) ? $header_fixed : false;
    
    $header_affix = false;
    if ($header_fixed == "top" || $header_fixed == "bottom-start") {
        $header_affix = boolval(QueryCache::get_field("header_affix", "options"));//boolval(get_option("options_header_affix"));
    }

    $header_hide_on_scroll_down = QueryCache::get_field("header_hide_on_scroll_down","options");//get_option("options_header_hide_on_scroll_down");
    $header_hide_on_scroll_down = ($header_affix && $header_hide_on_scroll_down) ? true : false;

    $header_equal    = QueryCache::get_field("header_equal", "options");//get_option("options_header_equal");
    $header_equal_on = QueryCache::get_field("header_equal_on", "options");//get_option("options_header_equal_on");

    $header_container = QueryCache::get_field("header_container", "options");//get_option("options_header_container");
    $header_container = block_container($header_container);
    $header_container = empty($header_container) ? "vw-100 px-3" : "";

    $header_data = [];
    $sections = ["header_start", "header_center", "header_end"];

    foreach ($sections as $section) {
        $item = QueryCache::get_field($section, "options");
        $key = str_replace('header_', '', $section); // start, center, end
        
        $header_data[$key] = [
            "type"        => "",
            "align"       => "",
            "tools"       => [],
            "class"       => "",
            "menu"        => "",
            "parent_link" => false,
            "logo_height" => false
        ];

        if ($item) {
            $header_data[$key]["type"]  = $item["type"];
            $header_data[$key]["align"] = $item["align"];
            $header_data[$key]["menu"]  = $item["menu"];

            if ($item["type"] == "tools") {
                $tools = isset($item["header_tools"]["header_tools"]) ? $item["header_tools"]["header_tools"] : [];
                $tools["affix"] = $header_affix;
                $header_data[$key]["tools"] = $tools;
            } elseif ($item["type"] == "navigation") {
                $header_data[$key]["parent_link"] = $item["navigation_parent_link"];
            } elseif ($item["type"] == "brand") {
                $header_data[$key]["logo_height"] = $item["logo_height"];
            }
        }
    }

    // Header Class Hesaplamaları
    if ($header_data["center"]["type"] != "empty") {
        $header_data["start"]["class"]  = "flex-shrink-0" . ($header_equal ? " nav-equal nav-equal-".$header_equal_on : "");
        $header_data["center"]["class"] = "flex-grow-1 h-100";
        $header_data["end"]["class"]    = "flex-shrink-0" . ($header_equal ? " nav-equal nav-equal-".$header_equal_on : "");
    } else {
        $header_data["start"]["class"]  = ($header_data["start"]["type"] != "empty" ? "flex-shrink-0" : "flex-grow-1");
        $header_data["center"]["class"] = "flex-grow-1 h-100";
        $header_data["end"]["class"]    = ($header_data["end"]["type"] != "empty" ? "flex-shrink-0" : "flex-grow-1");
    }

    $header_options = [
        "affix"                      => $header_affix,
        "fixed"                      => $header_fixed,
        "header_hide_on_scroll_down" => $header_hide_on_scroll_down,
        "container"                  => $header_container,
        "start"                      => $header_data["start"],
        "center"                     => $header_data["center"],
        "end"                        => $header_data["end"]
    ];
    $header_options["has_dropdown"] = header_has_dropdown(["header" => $header_options]);
    $header_options["has_navigation"] = header_has_navigation(["header" => $header_options]);


   
    // --- Footer Ayarları ---
    $footer_menu_raw = QueryCache::get_field("footer_menu", "options");
    $footer_menu_processed = [];
    if (is_array($footer_menu_raw)) {
        foreach ($footer_menu_raw as $m) {
            $footer_menu_processed[$m["name"]] = $m["menu"];
        }
    }

    $footer_options = [
        "container" => block_container(QueryCache::get_field("footer_container", "options")),//block_container(get_option("footer_container")),
        "text"      => QueryCache::get_field("footer_text", "options"),//get_option("options_footer_text"),
        "logo"      => QueryCache::get_field("logo_footer", "options"),
        "menu"      => $footer_menu_processed,
        "template"  => QueryCache::get_field("footer_template", "options")//get_option("options_footer_template")
    ];

    $result = [
        "header" => $header_options,
        "footer" => $footer_options
    ];

    // 3. ADIM: JSON dosyasını oluştur veya güncelle
    if (!is_dir(dirname($json_path))) {
        mkdir(dirname($json_path), 0755, true);
    }
    file_put_contents($json_path, json_encode($result));

    return $result;
}

// Timber posts kontrolü
function check_timber_posts() {
    global $wp_query;
    if (!isset($wp_query->posts) || empty($wp_query->posts)) {
        $wp_query->posts = array(); // Boş bir dizi olarak ayarla
    }
}
add_action('template_redirect', 'check_timber_posts');

function add_query_vars_filter( $vars ) {
    // 1. GLOBALS içinde veri var mı ve dizi mi? (Tek seferde kontrol)
    $custom_vars = Data::get("url_query_vars") ?? null;

    if ( ! is_array( $custom_vars ) || empty( $custom_vars ) ) {
        return $vars;
    }

    // 2. Foreach yerine array_merge kullanarak tüm diziyi tek seferde çakalım
    // array_values ile de indis çakışmalarını önleyelim
    return array_merge( $vars, array_values( $custom_vars ) );
}
add_filter( 'query_vars', 'add_query_vars_filter' );

function old_style_name_like_wpse_123298($clauses) {
    // Yanlış filtre adı düzeltildi: terms_clauses olmalı
    remove_filter('terms_clauses', 'old_style_name_like_wpse_123298');

    // Performans için: Eğer 'name LIKE' ifadesi yoksa Regex çalıştırma
    if (strpos($clauses['where'], 'name LIKE') === false) {
        return $clauses;
    }

    // Pattern: qTranslate tag'lerini süzüp sadece içeriği LIKE içinde bırakır
    $pattern = '|(name LIKE )\'{.*?}(.+?){.*?}\'|';
    $clauses['where'] = preg_replace($pattern, '$1 \'$2\'', $clauses['where']);

    return $clauses;
}
add_filter('terms_clauses', 'old_style_name_like_wpse_123298');

function optimize_image_output( $content ) {
    // 1. Hızlı Çıkış: Admin panelindeyse veya içerik çok kısaysa işlem yapma
    if ( is_admin() || empty($content) || !is_string($content) ) {
        return $content;
    }

    // 2. Performans Kontrolü: İçinde resim yoksa Regex'e girip CPU'yu yorma
    if ( false === strpos( $content, '<img' ) ) {
        return $content;
    }

    // 3. Regex Callback
    return preg_replace_callback(
        '/<img([^>]+?)>/i',
        function ( $matches ) {
            $img_tag = $matches[0];

            // 4. SRC kontrolü (Eski usul boş resimleri atla)
            if ( false === strpos( $img_tag, 'src=' ) ) {
                return $img_tag;
            }

            // 5. Loading="lazy" ekleme (Eğer yoksa ve decodign yoksa ekle)
            if ( false === strpos( $img_tag, 'loading=' ) ) {
                $img_tag = str_replace( '<img', '<img loading="lazy" decoding="async"', $img_tag );
            }

            // 6. Class yönetimi (Gelişmiş kontrol)
            if ( preg_match( '/class=["\']([^"\']*)["\']/', $img_tag, $class_match ) ) {
                $existing_classes = $class_match[1];
                
                // Zaten img-fluid varsa veya resim bir ikon/küçük objeyse ekleme yapma
                if ( strpos( $existing_classes, 'img-fluid' ) === false ) {
                    $new_classes = trim( $existing_classes . ' img-fluid' );
                    $img_tag = str_replace( $class_match[0], 'class="' . esc_attr( $new_classes ) . '"', $img_tag );
                }
            } else {
                // Hiç class yoksa direkt ekle
                $img_tag = str_replace( '<img', '<img class="img-fluid"', $img_tag );
            }

            return $img_tag;
        },
        $content
    );
}
// Filtreleri koruyoruz
add_filter( 'the_content', 'optimize_image_output', 20 );
add_filter( 'acf/format_value/type=wysiwyg', 'optimize_image_output', 20 );

/* Embed videoları responsive (16x9) yap */
function responsive_embed_oembed_html($html, $url, $attr, $post_id) {
    // 1. Video servislerini bir diziye alalım (Yönetmesi kolay olsun)
    $video_providers = [
        'youtube.', 
        'youtu.be', 
        'vimeo.', 
        'dailymotion.', 
        'ted.com'
    ];

    $is_video = false;
    foreach ($video_providers as $provider) {
        // !== false kontrolü 0. karakterde olsa bile doğru yakalar
        if (strpos($url, $provider) !== false) {
            $is_video = true;
            break;
        }
    }

    if ($is_video) {
        // 2. iframe içine 'embed-responsive-item' class'ını da ekleyelim (Opsiyonel ama garanti olur)
        $html = str_replace('<iframe', '<iframe class="embed-responsive-item"', $html);
        return '<div class="ratio ratio-16x9">' . $html . '</div>';
    }

    return $html;
}
add_filter('embed_oembed_html', 'responsive_embed_oembed_html', 99, 4);

function search_distinct($distinct) {
    // Sadece arama sayfasındaysak ve ana sorgu çalışıyorsa DISTINCT ekle
    if ( is_search() && is_main_query() ) {
        return "DISTINCT";
    }
    
    return $distinct;
}
add_filter("posts_distinct", "search_distinct");


/**
 * Kullanıcı oturum süresini 1 yıla uzatır.
 */
function keep_me_logged_in_for_1_year( $expirein ) {
    // 1 yıl = 365 gün * 24 saat * 60 dk * 60 sn
    return YEAR_IN_SECONDS; 
}
add_filter( 'auth_cookie_expiration', 'keep_me_logged_in_for_1_year' );


function add_img_fluid_to_gutenberg($block_content, $block) {
    // 1. Sadece core/image bloğu mu? (Hızlı çıkış)
    if (isset($block['blockName']) && $block['blockName'] === 'core/image') {
        
        // 2. Zaten img-fluid eklenmiş mi? (Mükerrer eklemeyi engelle)
        if (strpos($block_content, 'img-fluid') !== false) {
            return $block_content;
        }

        // 3. Class varsa içine ekle, yoksa yeni class aç
        if (strpos($block_content, 'class="') !== false) {
            $block_content = str_replace('class="', 'class="img-fluid ', $block_content);
        } else {
            $block_content = str_replace('<img ', '<img class="img-fluid" ', $block_content);
        }
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
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';

    if (!$id || !$type || !isset($_POST['lcp_data'])) {
        wp_send_json_error(['message' => 'Eksik veya geçersiz parametre!']);
    }

    //$id = intval($_POST['id']);
    //$type = trim($_POST['type']);
    $url = trim($_POST['url']);
    $lang = trim($_POST['lang']);
    //$lcp_data = json_decode(stripslashes($_POST['lcp_data']), true);

    $lcp_raw = stripslashes($_POST['lcp_data']);
    $lcp_data = json_decode($lcp_raw, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_send_json_error(['message' => 'JSON formatı bozuk!']);
    }

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
        /*if(defined("SITE_ASSETS") && is_array(SITE_ASSETS)){
            $input .= file_get_contents(STATIC_PATH . SITE_ASSETS["plugin_css"]);
            $input .= file_get_contents(STATIC_PATH . SITE_ASSETS["css_page"]);
        }else{
            $input .= file_get_contents(STATIC_PATH ."css/main-combined.css");
        }

        
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

// 2️⃣ send_headers'de option'dan oku
/*
add_action('send_headers', function () {
    $csp_directives = [
        "default-src" => ["'self'"],
        "style-src"   => ["'self'", "'unsafe-inline'", "https://fonts.googleapis.com", "https://unpkg.com", "https://maps.googleapis.com", "https://maps.gstatic.com", "https://cdnjs.cloudflare.com", "https://cdn.plyr.io", "https://*.facebook.com"],
        "script-src"  => [
            "'self'", "'unsafe-inline'", "'unsafe-eval'", "blob:", 
            "https://unpkg.com", "https://maps.googleapis.com", "https://maps.gstatic.com", 
            "https://www.youtube.com", "https://player.vimeo.com", "https://www.googletagmanager.com", 
            "https://www.google-analytics.com", "https://connect.facebook.net", // Facebook SDK
            "https://*.hotjar.com", "https://*.hotjar.io", // Hotjar ısı haritası
            "https://mc.yandex.ru", // Yandex Metrica
            "https://analytics.tiktok.com", // Tiktok Pixel
        ],
        "worker-src"  => ["'self'", "blob:", "https://*.hotjar.com"],
        "img-src" => [
            "'self'", "data:", "blob:",
            "https://*.google.com", "https://*.google.com.tr", "https://*.google-analytics.com", 
            "https://*.googletagmanager.com", "https://*.googleapis.com", "https://*.gstatic.com", 
            "https://img.youtube.com", "https://i.ytimg.com", "https://*.tile.openstreetmap.org", 
            "https://s.w.org", "https://secure.gravatar.com", "https://*.vimeocdn.com",
            "https://*.cdninstagram.com", "https://*.fbcdn.net", "https://*.facebook.com", 
            "https://www.facebook.com", "https://googleads.g.doubleclick.net", // Google Ads
            "https://mc.yandex.ru" // Yandex görselleri
        ],
        "font-src"    => ["'self'", "data:", "https://fonts.gstatic.com", "https://cdnjs.cloudflare.com", "https://*.hotjar.com"],
        "object-src"  => ["'none'"],
        "base-uri"    => ["'self'"],
        "frame-ancestors" => ["'self'"],
        "frame-src"   => [
            "'self'", "https://www.youtube.com", "https://www.youtube-nocookie.com", 
            "https://player.vimeo.com", "https://*.google.com", "https://www.openstreetmap.org",
            "https://td.doubleclick.net", "https://www.facebook.com", "https://connect.facebook.net",
            "https://vars.hotjar.com"
        ],
        "connect-src" => [
            "'self'", "https://*.google-analytics.com", "https://*.analytics.google.com", 
            "https://*.googletagmanager.com", "https://*.googleapis.com", "https://*.gstatic.com", 
            "https://*.tile.openstreetmap.org", "https://noembed.com", "https://cdn.plyr.io",
            "https://*.facebook.com", "https://*.facebook.net", // Facebook API
            "https://*.hotjar.com", "https://*.hotjar.io", "wss://*.hotjar.com", // Hotjar WebSocket
            "https://mc.yandex.ru", "https://analytics.tiktok.com"
        ]
    ];

    // Orijinal approved_domains döngün aynen kalsın...
    $approved_domains = get_option('csp_approved_domains', []);
    if (is_array($approved_domains)) {
        foreach ($approved_domains as $directive => $domains) {
            if (!isset($csp_directives[$directive]) || !is_array($domains)) continue;
            foreach ($domains as $domain) {
                if (!in_array($domain, $csp_directives[$directive])) {
                    $csp_directives[$directive][] = $domain;
                }
            }
        }
    }

    $csp_string = '';
    foreach ($csp_directives as $key => $values) {
        $csp_string .= $key . ' ' . implode(' ', $values) . '; ';
    }

    header("Content-Security-Policy: " . $csp_string);
});
*/

function add_cache_control_headers() {
    // Admin panelinde veya header zaten gönderildiyse dokunma
    if ( is_admin() || headers_sent() ) {
        return;
    }

    $request_uri = $_SERVER['REQUEST_URI'] ?? '';

    // 1. MP3 DOSYALARI İÇİN ÖZEL HEADER (Streaming Desteği)
    if ( stripos( $request_uri, '.mp3' ) !== false ) {
        header( 'Cache-Control: public, max-age=31536000, immutable' );
        header( 'Content-Type: audio/mpeg' );
        header( 'Accept-Ranges: bytes' );
        header( 'Content-Disposition: inline' );
        return; // MP3 ise burada bitir
    }

    // 2. HTML SAYFALARI İÇİN (Normal Sayfa Yükü)
    // Buraya immutable koyarsan revizyon yapamazsın. 
    // max-age=3600 (1 saat) veya 0 (no-cache) daha güvenlidir.
    // Cloudflare veya cache eklentisi kullanıyorsan burayı onlar yönetmeli.
    if ( ! is_user_logged_in() ) {
        // Giriş yapmamış kullanıcıya 1 saatlik cache hakkı tanıyalım
        header( 'Cache-Control: public, max-age=3600, must-revalidate' );
    } else {
        // Login olmuş kullanıcıya cache verme (Admin bar vb. güncel kalsın)
        header( 'Cache-Control: no-cache, must-revalidate, max-age=0' );
    }
}
add_action('send_headers', 'add_cache_control_headers');


/**
 * Veritabanına kaydedilirken temizlik yapar. 
 * Böylece her görüntülemede (frontend) işlemci yorulmaz.
 */
add_action('save_post', function($post_id) {
    // Güvenlik ve Revizyon Kontrolleri
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if (!current_user_can('edit_post', $post_id)) return;

    // Sadece yazı (post) ve sayfa (page) tiplerinde çalış (Gereksiz tetiklemeyi önle)
    $allowed_types = ['post', 'page'];
    if (!in_array(get_post_type($post_id), $allowed_types)) return;

    $content = get_post_field('post_content', $post_id);

    // Sondaki &nbsp; ve boşlukları temizle
    $clean = preg_replace('/(&nbsp;|\s)+$/u', '', $content);

    if ($clean !== $content) {
        // Sonsuz döngüyü engellemek için filtreyi geçici olarak kaldır
        remove_action('save_post', __FUNCTION__);

        wp_update_post([
            'ID'           => $post_id,
            'post_content' => $clean
        ]);

        // İşlem bittikten sonra tekrar eklemeye gerek yok çünkü bu bir Closure, 
        // ama düzenli bir fonksiyon olsaydı tekrar eklerdik.
    }
}, 10, 1);

/*
add_filter('the_content', function($c){
    return preg_replace('/(&nbsp;|\s)+$/u', '', $c);
}, 20);
*/






/**
 * [Özyinelemeli Fonksiyon] 
 * Sayfanın içeriği boşsa, içeriği olan ilk alt sayfanın ID'sini bulur.
 * Performans için hem Static Cache hem de WP Object Cache kullanır.
 */
function find_first_content_child_id($post_id) {
    // 1. Runtime Cache: Aynı sayfa yüklemesi içinde mükerrer sorguyu engeller.
    static $runtime_cache = [];
    if (isset($runtime_cache[$post_id])) {
        return $runtime_cache[$post_id];
    }

    // 2. WP Object Cache: Redis/Memcached varsa DB vuruşunu tamamen keser.
    $cache_key = 'first_child_content_' . $post_id;
    $cached_val = wp_cache_get($cache_key, 'pages');
    if (false !== $cached_val) {
        $runtime_cache[$post_id] = (int) $cached_val;
        return (int) $cached_val;
    }

    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'page') {
        return $post_id;
    }

    // 3. İçerik Kontrolü: İçerik varsa kendi ID'sini döndür.
    if (!empty(trim($post->post_content))) {
        wp_cache_set($cache_key, $post_id, 'pages', HOUR_IN_SECONDS);
        $runtime_cache[$post_id] = $post_id;
        return $post_id;
    }

    // 4. Alt Sayfaları Sorgula: Sadece ID çekerek hızı artırıyoruz.
    $args = array(
        'post_type'      => 'page',
        'posts_per_page' => 1,
        'post_status'    => 'publish',
        'orderby'        => 'menu_order', 
        'order'          => 'ASC',
        'post_parent'    => $post_id,
        'fields'         => 'ids',
        'no_found_rows'  => true, // Toplam sayı hesaplamasını kapat (Hızlandırır)
    );
    
    $children_ids = get_posts($args);

    if (!empty($children_ids)) {
        // Özyinelemeli olarak alt sayfayı kontrol et
        $final_id = find_first_content_child_id($children_ids[0]);
    } else {
        $final_id = $post_id;
    }

    // 5. Sonucu Cache'e yaz ve döndür.
    wp_cache_set($cache_key, $final_id, 'pages', HOUR_IN_SECONDS);
    $runtime_cache[$post_id] = (int) $final_id;
    return (int) $final_id;
}

/**
 * [FİLTRE] Sayfa linkini, içeriği boşsa alt sayfaya yönlendirir.
 */
add_filter('page_link', function($permalink, $post_id) {
    // Admin panelinde veya Ajax'ta linkleri bozmamak için çıkış.
    if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
        return $permalink;
    }
    
    $final_id = find_first_content_child_id($post_id);
    
    // Eğer farklı bir ID (alt sayfa) bulunduysa linki güncelle.
    return ($final_id !== (int)$post_id) ? get_permalink($final_id) : $permalink;
}, 20, 2);

/**
 * [FİLTRE] Yoast Breadcrumb linklerini günceller.
 */
add_filter('wpseo_breadcrumb_links', function($links) {
    if (is_admin() || empty($links) || !is_array($links)) {
        return $links;
    }

    foreach ($links as $key => $link) {
        if (isset($link['id']) && get_post_type($link['id']) === 'page') {
            $final_id = find_first_content_child_id($link['id']);
            if ($final_id !== (int)$link['id']) {
                $links[$key]['url'] = get_permalink($final_id);
            }
        }
    }
    return $links;
}, 10);

/**
 * [TEMİZLİK] Sayfa güncellendiğinde cache'i temizle.
 */
function clear_page_redirection_cache($post_id) {
    if (get_post_type($post_id) !== 'page') return;

    wp_cache_delete('first_child_content_' . $post_id, 'pages');
    
    $parent_id = wp_get_post_parent_id($post_id);
    if ($parent_id) {
        clear_page_redirection_cache($parent_id);
    }
}
add_action('save_post_page', 'clear_page_redirection_cache');
add_action('before_delete_post', 'clear_page_redirection_cache');


/**
 * [PERFORMANS] Term sorgularını cache'e zorlar ve meta verileri tek seferde yükler.
 * Sorgu sayısını (N+1 problemi) azaltmak için kritiktir.
 */
add_filter('get_terms_args', function($args, $taxonomies) {
    // 1. Admin panelinde veya Ajax'ta cache bazen sorun çıkarabilir, kısıtlayalım.
    if (!is_admin() && !defined('DOING_AJAX')) {
        
        // 2. Sorgu sonuçlarını cache'le
        $args['cache_results'] = true;
        
        // 3. Term meta verilerini (ACF vb.) tek seferde çek (Lazy loading'i engeller)
        $args['update_term_meta_cache'] = true;

        // 4. Eğer çok fazla kategori varsa, sadece gerekli alanları çekerek RAM'i koruyalım
        // Örn: Sadece ID'leri değil tüm objeyi istiyorsak bu kalsın.
    }
    return $args;
}, 10, 2);


add_filter( 'http_request_args', function( $args, $url ) {
    $args['timeout'] = 30; // 5 saniyeyi 30 saniyeye çıkar
    return $args;
}, 10, 2 );