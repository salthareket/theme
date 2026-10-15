<?php

add_action('wp_print_scripts', function () {
    if (!is_admin()) return;

    // Yoast'ın en ağır scriptlerinden bazılarını gereksiz yerlerde uçur
    wp_dequeue_script('yoast-seo-analysis-package');
    wp_dequeue_script('yoast-seo-ai-frontend-package'); // AI istemiyorsak siktir et
    wp_dequeue_script('yoast-seo-related-keyphrase-suggestions-package');
}, 999);

add_action('admin_enqueue_scripts', function($hook) {
    // Sadece Post/Page düzenleme sayfasındaysak (Anasayfa editi dahil)
    if ( !in_array($hook, ['post.php', 'post-new.php']) ) return;

    // 1. YOAST TEMİZLİĞİ - Analiz yapmıyorsak bunları uçur
    $yoast_gots = [
        'yoast-seo-ai-frontend-package',
        'yoast-seo-analysis-report-package',
        'yoast-seo-related-keyphrase-suggestions-package',
        'yoast-seo-social-metadata-forms-package',
        'yoast-seo-search-metadata-previews-package',
        'yoast-seo-replacement-variable-editor-package'
    ];
    foreach ($yoast_gots as $handle) {
        wp_dequeue_script($handle);
    }
}, 999);



add_action('after_setup_theme', function() {
   add_filter('wpseo_title', '__return_false');
});


// Yoast SEO meta tag kontrolü
add_filter('wpseo_frontend_presenters', function($presenters) {
    global $post;
    if (is_null($post)) {
        return array(); // Boş bir dizi döndürerek Yoast SEO işlevselliğini devre dışı bırak
    }
    return $presenters;
});


function yoast_seo_exclude_posts_from_sitemap($exclude) {
    if ( class_exists( 'WooCommerce' ) ) {
        $exclude[] = get_option("woocommerce_order_received_page_id");
        $exclude[] = get_option("woocommerce_cart_page_id");//get_page_by_path( 'cart' )->ID;
        $exclude[] = get_option("woocommerce_checkout_page_id");//get_page_by_path( 'checkout' )->ID;
    }
    if(class_exists('Newsletter')){
        $exclude[] = get_page_by_path( 'newsletter' )->ID;      
    }
    if(Data::get("sitemap_exclude_post_ids")){
    	$exclude = array_merge($exclude, Data::get("sitemap_exclude_post_ids") );
    }
	return $exclude;
}
add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', 'yoast_seo_exclude_posts_from_sitemap', 10, 1 );


function yoast_seo_exclude_terms_from_sitemap($exclude) {
	if(Data::get("sitemap_exclude_term_ids")){
    	$exclude = array_merge($exclude, Data::get("sitemap_exclude_term_ids") );
    }
    return $exclude;
}
add_filter( 'wpseo_exclude_from_sitemap_by_term_ids', 'yoast_seo_exclude_terms_from_sitemap', 10, 1 );



if(is_admin()){
    function yoast_seo_remove_columns( $columns ) {
        /* remove the Yoast SEO columns */
        unset( $columns['wpseo-score'] );
        unset( $columns['wpseo-title'] );
        unset( $columns['wpseo-metadesc'] );
        unset( $columns['wpseo-focuskw'] );
        unset( $columns['wpseo-score-readability'] );
        unset( $columns['wpseo-links'] );
        return $columns;
    }
    /* remove from posts */
    add_filter ( 'manage_edit-post_columns', 'yoast_seo_remove_columns' );
    /* remove from pages */
    add_filter ( 'manage_edit-page_columns', 'yoast_seo_remove_columns' );
    /* remove from woocommerce product post type */
    add_filter ( 'manage_edit-product_columns', 'yoast_seo_remove_columns' );


    function wpse151723_remove_yoast_seo_posts_filter() {
        global $wpseo_metabox, $wpseo_meta_columns;
        if ( $wpseo_metabox ) {
            remove_action( 'restrict_manage_posts', array( $wpseo_metabox, 'posts_filter_dropdown' ) );
        }
        if ( $wpseo_meta_columns ) {
            remove_action( 'restrict_manage_posts', array( $wpseo_meta_columns , 'posts_filter_dropdown' ) );
            remove_action( 'restrict_manage_posts', array( $wpseo_meta_columns , 'posts_filter_dropdown_readability' ) );
        }    
    }
    add_action( 'admin_init', 'wpse151723_remove_yoast_seo_posts_filter', 20 );    
}



add_filter( 'wpseo_next_rel_link', 'custom_change_wpseo_next' );
add_filter( 'wpseo_prev_rel_link', 'custom_change_wpseo_prev' );
function custom_change_wpseo_prev( $link ) {
	return $link;
}
function custom_change_wpseo_next( $link ) {
	return $link;
}

function rel_next_prev(){
    global $paged;
    if ( get_previous_posts_link() ) { ?>
        <link rel="prev" href="<?php echo get_pagenum_link( $paged - 1 ); ?>" /><?php
    }
    if ( get_next_posts_link() ) { ?>
        <link rel="next" href="<?php echo get_pagenum_link( $paged +1 ); ?>" /><?php
    }
}
add_action( 'wp_head', 'rel_next_prev' );








/**
 * BRIC CORE - ULTIMATE BREADCRUMB MASTER
 * Yoast SEO Breadcrumb hiyerarşisini Menü, Çoklu Dil ve E-Ticaret'e göre tek seferde inşa eder.
 */
add_filter('wpseo_breadcrumb_links', function($links) {
    // --- 0. KONFİGÜRASYON ---
    $is_ecommerce = (defined('ENABLE_ECOMMERCE') && ENABLE_ECOMMERCE);
    $ml_type      = (defined('ENABLED_MULTILANGUAGE')) ? ENABLED_MULTILANGUAGE : false; // polylang, qtranslate, wpml
    
    if (is_admin()) return $links;

    // --- 1. MENÜDEN BREADCRUMB OLUŞTURMA (Logichub Mantığı) ---
    // Eğer opsiyon açıksa, Yoast'ın varsayılan hiyerarşisini tamamen eziyoruz.
    if (\SaltHareket\SaltConfig::get('options_breadcrumb_from_menu')) {
        $links = bric_bc_get_menu_hierarchy($ml_type);
    }

    // --- 2. REPEATER PATH (Özel Sayfa Yolları) ---
    // Post Type bazlı araya sayfa/parent sokma mantığı.
    $links = bric_bc_apply_repeater_path($links, $ml_type);

    // --- 3. E-TİCARET & MARKA (WooCommerce) ---
    if ($is_ecommerce) {
        $links = bric_bc_apply_ecommerce($links);
    }

    // --- 4. MULTILANGUAGE (Dil Çevirileri ve URL Fix) ---
    // Polylang, qTranslate veya WPML'e göre metinleri ve linkleri düzeltir.
    if ($ml_type) {
        $links = bric_bc_apply_ml_fixes($links, $ml_type);
    }

    // --- 5. TAXONOMY PREFIX REMOVAL (taxonomy_prefix_remove ayarı) ---
    // options_taxonomy_prefix_remove listesindeki taxonomy'lerin URL'lerinden prefix kaldır
    $links = bric_bc_apply_taxonomy_prefix_removal($links);

    // --- 6. TEMİZLİK OPERASYONLARI (Home ve Current Page) ---
    $links = bric_bc_apply_cleanup($links);

    return $links;
}, 9999); // En yüksek öncelikle her şeyi en son biz mühürlüyoruz.


/**
 * MANTIK 1: Menü Yapısını Çözen Fonksiyon
 */
function bric_bc_get_menu_hierarchy($ml_type) {
    $location_key = 'header-menu';
    $locations = get_nav_menu_locations();
    $menu_id = $locations[$location_key] ?? 0;
    
    // Polylang Menü Fix
    if ($ml_type === 'polylang' && function_exists('pll_get_term')) {
        $lang = function_exists('pll_current_language') ? pll_current_language() : '';
        $menu_id = pll_get_term($menu_id, $lang) ?: $menu_id;
    }

    $menu_items = wp_get_nav_menu_items($menu_id);
    if (!$menu_items) return [];

    $current_url = (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $new_links = [];

    foreach ($menu_items as $item) {
        if (trailingslashit($item->url) == trailingslashit($current_url)) {
            $temp = $item;
            while ($temp) {
                array_unshift($new_links, ['url' => $temp->url, 'text' => $temp->title]);
                $p_id = $temp->menu_item_parent;
                $temp = $p_id ? current(array_filter($menu_items, fn($i) => $i->ID == $p_id)) : false;
            }
            break;
        }
    }
    return $new_links;
}

/**
 * MANTIK 2: Repeater (Breadcrumb Add Page Path) Mantığı
 */
function bric_bc_apply_repeater_path($links, $ml_type) {
    $path_data = \SaltHareket\SaltConfig::get('options_breadcrumb_add_page_path', []);
    if (empty($path_data) || !is_singular()) return $links;

    $c_post_type = get_post_type();
    $target_page_id = null;

    foreach ($path_data as $item) {
        if (($item['post_type'] ?? '') === $c_post_type) {
            $target_page_id = (int)$item['page'];
            break;
        }
    }

    if ($target_page_id) {
        // Çoklu Dil Çevirisi (ID bazlı)
        if ($ml_type === 'polylang') $target_page_id = pll_get_post($target_page_id) ?: $target_page_id;
        if ($ml_type === 'wpml') $target_page_id = apply_filters('wpml_object_id', $target_page_id, 'page', true);

        $page_obj = get_post($target_page_id);
        if ($page_obj) {
            $path_stack = [];
            $temp_id = $page_obj->ID;
            while ($temp_id) {
                $p = get_post($temp_id);
                array_unshift($path_stack, ['id' => $p->ID, 'url' => get_permalink($p->ID), 'text' => get_the_title($p->ID)]);
                $temp_id = $p->post_parent;
            }
            // Home linkinden hemen sonraya enjekte et
            array_splice($links, 1, 0, $path_stack);
        }
    }
    return $links;
}

/**
 * MANTIK 3: E-Ticaret (Marka ve Kategori) Mantığı
 */
function bric_bc_apply_ecommerce($links) {
    // Marka Ekleme (Kategori Arşivinde)
    if (\SaltHareket\SaltConfig::get('options_breadcrumb_add_product_brand') && is_product_category()) {
        $brand_slug = get_query_var('product_brand');
        if ($brand_slug) {
            $brand = get_term_by('slug', $brand_slug, 'product_brand');
            if ($brand) {
                $temp = [];
                foreach ($links as $l) {
                    $temp[] = $l;
                    if (isset($l['ptarchive']) && $l['ptarchive'] === 'product') {
                        $temp[] = ['term' => $brand, 'text' => $brand->name, 'url' => get_term_link($brand)];
                    }
                }
                $links = $temp;
            }
        }
    }

    // Ürün Sayfasında Kategori Ekleme
    if (get_option('options_breadcrumb_add_product_taxonomy') && is_singular('product')) {
        $cat = wc_get_product_terms(get_the_ID(), 'product_cat')[0] ?? null;
        if ($cat) {
            $brand = wc_get_product_terms(get_the_ID(), 'product_brand')[0] ?? null;
            $url   = get_term_link($cat);
            if ($brand && ! is_wp_error( get_term_link($cat) ) ) {
                $url = $url . "?product_brand=" . $brand->slug;
            }
            $links[] = [
                'text' => $cat->name,
                'url'  => $url,
            ];
        }
    }
    return $links;
}

/**
 * MANTIK 4: Çoklu Dil Çeviri ve qTranslate Fixleri
 */
function bric_bc_apply_ml_fixes($links, $ml_type) {
    foreach ($links as $k => $l) {
        // qTranslate Fix
        if ($ml_type === 'qtranslate' && function_exists('qtranxf_use')) {
            $lang = qtranxf_getLanguage();
            $links[$k]['text'] = qtranxf_use($lang, $l['text'], false, false);
            $links[$k]['url']  = qtranxf_convertURL($l['url'], $lang);
        } 
        // Polylang/WPML Label Fix
        elseif (($ml_type === 'polylang' || $ml_type === 'wpml') && function_exists('pll__')) {
            $links[$k]['text'] = pll__($l['text']);
        }

        // Arşiv Sayfası İsim Fix
        if (isset($l['ptarchive'])) {
            $pt_obj = get_post_type_object($l['ptarchive']);
            if ($pt_obj) $links[$k]['text'] = $pt_obj->labels->singular_name;
        }
    }
    return $links;
}

/**
 * MANTIK 5: Taxonomy Prefix Kaldırma
 * options_taxonomy_prefix_remove listesindeki taxonomy'lerin breadcrumb URL'lerinden
 * prefix kaldırır. Örn: /category/slug/ → /slug/
 */
function bric_bc_apply_taxonomy_prefix_removal( array $links ): array {
    $removals = \SaltHareket\SaltConfig::get( 'options_taxonomy_prefix_remove', [] );
    if ( empty( $removals ) || ! is_array( $removals ) ) return $links;

    // Taxonomy slug → rewrite slug eşlemesi
    $slug_map = [];
    foreach ( $removals as $taxonomy ) {
        if ( empty( $taxonomy ) ) continue;
        $tax_obj = get_taxonomy( $taxonomy );
        if ( ! $tax_obj ) continue;
        $rewrite_slug = ! empty( $tax_obj->rewrite['slug'] ) ? $tax_obj->rewrite['slug'] : $taxonomy;
        $slug_map[ $taxonomy ] = $rewrite_slug;
    }

    if ( empty( $slug_map ) ) return $links;

    foreach ( $links as $k => $link ) {
        $url = $link['url'] ?? '';
        if ( empty( $url ) ) continue;
        foreach ( $slug_map as $rewrite_slug ) {
            // Basit str_replace — regex yok, PHP preg_match hataları yok
            $url = str_replace( '/' . $rewrite_slug . '/', '/', $url );
        }
        $links[$k]['url'] = $url;
    }
    return $links;
}

/**
 * MANTIK 6: Temizlik (Home ve Current Sayfayı Kaldırma)
 */
function bric_bc_apply_cleanup($links) {
    if (empty($links)) return $links;

    // Anasayfayı Kaldır
    if (get_option('options_breadcrumb_remove_home')) {
        if (untrailingslashit($links[0]['url'] ?? '') === untrailingslashit(home_url())) {
            array_shift($links);
        }
    }

    // Mevcut Sayfayı Kaldır — Yoast son elemanı url'siz ekler, direkt pop et
    if (get_option('options_breadcrumb_remove_current')) {
        if (!empty($links)) {
            array_pop($links);
        }
    }
    return array_values($links);
}