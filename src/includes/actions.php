<?php
// =============================================================================
// ACTIONS — Frontend Hooks & Filters
// =============================================================================
// @version 1.1.0
//
// @changelog
//   1.1.0 - 2026-08-21
//     - Remove: header_footer_options() ~130 satır deprecated fonksiyon kaldırıldı.
//     - Remove: header_has_dropdown(), header_has_navigation() kaldırıldı.
//     - Remove: acf/save_post hook (header_footer_options JSON sistemi) kaldırıldı.
//     - Remove: restrict_author_pages(), remove_empty_p(), post_prev_next_order(),
//              ns_filter_avatar() devre dışı fonksiyon tanımları kaldırıldı.
//     - Remove: CSP send_headers comment bloğu bırakıldı (ileride aktif edilebilir).
//     - Remove: get_terms_args filter comment (Polylang bug - kasıtlı disable).
//     - Note: prepare_logo_field() hala aktif - logo alanları için kullanılıyor.
//   1.0.0 - 2026-05-16
//     - Add: Initial actions file
// =============================================================================

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
    $is_valid_main = (is_shop() || is_post_type_archive() || is_tax() || is_category() || is_tag() || is_search() || is_home()) && $wp_query->is_main_query();
    
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

        // Taxonomy sayfasında post_type boş gelir — taxonomy'den post type'ı çıkar
        if ((is_tax() || is_category() || is_tag()) && empty($query_vars["post_type"])) {
            $queried = get_queried_object();
            if ($queried instanceof \WP_Term) {
                $tax_obj = get_taxonomy($queried->taxonomy);
                if ($tax_obj && !empty($tax_obj->object_type)) {
                    $query_vars["post_type"] = $tax_obj->object_type[0];
                }
            }
        }

        $post_type = is_search() ? "search" : $query_vars["post_type"];

        // qpt sadece arama/custom query context'inde geçerli — taxonomy sayfasında uygulama
        if (!is_tax() && !is_category() && !is_tag() && isset($wp_query->query_vars["post_type"])) {
            $qpt = get_query_var("qpt", $post_type);
            $qpt = (is_array($qpt) || empty($qpt) || $qpt == "search" || is_numeric($qpt)) ? "any" : $qpt;
            
            if (defined('EXCLUDE_FROM_SEARCH') && EXCLUDE_FROM_SEARCH && $qpt == "any") {
                $post_types = get_post_types(['public' => true], 'names');
                foreach ((array)EXCLUDE_FROM_SEARCH as $ex_type) {
                    unset($post_types[$ex_type]);
                }
                $qpt = array_values($post_types);
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

    // Taxonomy sayfasında post_type'ı taxonomy'den çıkar
    if ((is_tax() || is_category() || is_tag()) && !is_search()) {
        $queried = get_queried_object();
        if ($queried instanceof \WP_Term) {
            $tax_obj = get_taxonomy($queried->taxonomy);
            if ($tax_obj && !empty($tax_obj->object_type)) {
                $post_type = $tax_obj->object_type[0];
            }
        }
    }
    
    // qpt sadece taxonomy dışı sayfalarda geçerli
    if (!is_tax() && !is_category() && !is_tag()) {
        $qpt = get_query_var("qpt");
        if (!empty($qpt)) $post_type = $qpt;
    }

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
 * Logo alanını döndürür, URL'yi relative path'e dönüştürür.
 */
function prepare_logo_field(string $field, string $post_id = 'options'): array|string|null {
    $data = QueryCache::get_field($field, $post_id);
    if (is_array($data) && !empty($data['url'])) {
        $data['url'] = to_relative_url($data['url']);
    } elseif (is_string($data)) {
        $data = to_relative_url($data);
    }
    return $data;
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


// ── Taşındı (2026-08-26) ──────────────────────────────────────────────────────
// save_lcp_results AJAX handler → Lcp::ajaxSaveLcpResults() (class.lcp.php)
// Kayıt: variables.php'de Lcp::registerAjaxHooks() çağrısı.
// ─────────────────────────────────────────────────────────────────────────────

// ── Taşındı (2026-08-26) ──────────────────────────────────────────────────────
// CSP ve security header'ları → apps/security/SecurityHooks.php::sendSecurityHeaders()
// ─────────────────────────────────────────────────────────────────────────────
/*
add_action('send_headers', function () {
// ── Taşındı (2026-08-26) ──────────────────────────────────────────────────────
// CSP ve security header'ları → apps/security/SecurityHooks.php::sendSecurityHeaders()
// add_cache_control_headers() → SecurityHooks tarafından yönetiliyor.
// CSP direktifleri → SecuritySettings::getSection('csp') + SecurityHooks::buildCspString()
// ─────────────────────────────────────────────────────────────────────────────


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


add_filter( 'http_request_args', function( $args, $url ) {
    $args['timeout'] = 30;
    return $args;
}, 10, 2 );

