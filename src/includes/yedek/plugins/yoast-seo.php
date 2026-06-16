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
    if (QueryCache::get_field("breadcrumb_from_menu", "options")) {
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

    // --- 5. TEMİZLİK OPERASYONLARI (Home ve Current Page) ---
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
    $path_data = QueryCache::get_field("breadcrumb_add_page_path", "options");
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
    if (QueryCache::get_field("breadcrumb_add_product_brand", "options") && is_product_category()) {
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

    // Ürün Sayfasında Kategori + Marka Kombini
    if (QueryCache::get_field("breadcrumb_add_product_taxonomy", "options") && is_singular('product')) {
        $cat = wc_get_product_terms(get_the_ID(), 'product_cat')[0] ?? null;
        $brand = wc_get_product_terms(get_the_ID(), 'product_brand')[0] ?? null;
        if ($cat && $brand) {
            $links[] = [
                'text' => $cat->name, 
                'url' => get_term_link($cat) . "?product_brand=" . $brand->slug
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
 * MANTIK 5: Temizlik (Home ve Current Sayfayı Kaldırma)
 */
function bric_bc_apply_cleanup($links) {
    if (empty($links)) return $links;

    // Anasayfayı Kaldır
    if (QueryCache::get_field("breadcrumb_remove_home", "options")) {
        if (untrailingslashit($links[0]['url'] ?? '') === untrailingslashit(home_url())) {
            array_shift($links);
        }
    }

    // Mevcut Sayfayı Kaldır (Son eleman)
    if (QueryCache::get_field("breadcrumb_remove_current", "options")) {
        $current_url = (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        foreach ($links as $k => $l) {
            if (trailingslashit($l['url'] ?? '') === trailingslashit($current_url)) {
                unset($links[$k]);
            }
        }
    }
    return array_values($links);
}










/*
function custom_breadcrumb_links($links) {
        if(!get_field("breadcrumb_from_menu", "options")){
            return $links;
        }
	    $location_key = 'header-menu'; // Theme location ID
	    $locations = get_nav_menu_locations();
	    $lang = Data::get("language");
	    $menu_id = $locations[$location_key] ?? 0;
	    if (function_exists('pll_get_term') && $menu_id && $lang) {
	        $menu_id_lang = pll_get_term($menu_id, $lang);
	        if ($menu_id_lang) {
	            $menu_id = $menu_id_lang;
	        }
	    }
	    $menu = wp_get_nav_menu_object($menu_id);
	    if($menu){
	    	$current_url = "";
	    	$current_post_type = get_post_type(); // Geçerli post type'ı al
	    	$breadcrumb_items = [];
	        $menu_items = wp_get_nav_menu_items($menu->term_id);
	        $singular = false;

	        if (is_singular() && $current_post_type != "page") {

			    foreach ($menu_items as $item) {
			        if (get_post_meta($item->ID, 'object_type', true) === $current_post_type) {
			            $singular = $item;
			            break;
			        }
			    }

			    if ($singular) {
			        $breadcrumb_items[] = [
			            'url' => $singular->url,
			            'text' => $singular->title
			        ];
			        $current_url = $singular->url;
			    }
			}

	        if(empty($current_url)){
				$current_url = (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	        }

	        foreach ($menu_items as $menu_item) {
			    $item_url = $menu_item->url;
			    if (trailingslashit($item_url) == trailingslashit($current_url)) {
			    	if(!$singular){
				        $breadcrumb_items[] = [
				            'url' => $menu_item->url,
				            'text' => $menu_item->title
				        ];		    		
			    	}
			        while ($menu_item->menu_item_parent) {
			            $parent_menu_items = array_filter($menu_items, function($item) use ($menu_item) {
			                return $item->ID == $menu_item->menu_item_parent;
			            });
			            if ($parent_menu_item = reset($parent_menu_items)) { // İlk öğeyi al
			                array_unshift($breadcrumb_items, [
			                    'url' => $parent_menu_item->url,
			                    'text' => $parent_menu_item->title
			                ]);
			                $menu_item = $parent_menu_item;
			            } else {
			                break;
			            }
			        }
			        break;
			    }
			}
			//if(!get_option("options_breadcrumb_remove_home")){
            if(!QueryCache::get_field("breadcrumb_remove_home", "options")){
			    $homepage_id = get_option('page_on_front');
			    $homepage_title = $homepage_id ? get_the_title($homepage_id) : get_bloginfo('name');
			    if (function_exists('pll__') && $homepage_id) {
			        $homepage_title = pll__(get_the_title($homepage_id));
			    }
			    array_unshift($breadcrumb_items, [
			        'url' => get_home_url(),
			        'text' => $homepage_title
			    ]);
		    }
	        $links = $breadcrumb_items;    	
	    }
	    return $links;
}
add_filter('wpseo_breadcrumb_links', 'custom_breadcrumb_links', 10, 1);


if (ENABLE_ECOMMERCE) {
    //if(get_option("options_breadcrumb_add_product_brand")){
    
        function add_brand_to_breadcrumb($links){
            if(!QueryCache::get_field("breadcrumb_add_product_brand", "options")){
                return $links;
            }
            $brand_name = get_query_var( 'product_brand' );
            if ( is_product_category() && !is_tax( 'product_brand' ) && !empty($brand_name)) {
                $brand = array(
                    'term'=>get_term_by("slug",$brand_name,"product_brand")
                );
                $links_temp=array();
                foreach($links as $link){
                    $links_temp[]=$link;
                    if(!empty($brand) && array_key_exists('ptarchive',$link) ){
                        if($link['ptarchive']=='product'){
                            $links_temp[]=$brand;
                        }
                    }
                }
                $links=$links_temp;  
            }
            return $links;
        
        add_filter('wpseo_breadcrumb_links', 'add_brand_to_breadcrumb', 10, 1 );
    }
    //if(get_option("options_breadcrumb_add_product_taxonomy")){
    
        function add_category_to_breadcrumb($links){
            if(!QueryCache::get_field("breadcrumb_add_product_taxonomy", "options")){
                return $links;
            }
            if (is_singular('product')) {
                            global $post;
                            //category
                            $product_category = wc_get_product_terms( $post->id, 'product_cat', array( 'fields' => 'all' ) );
                            if(is_array($product_category)){
                                if(count($product_category)>0){
                                     $product_category=$product_category[0];
                                 }
                            }
                            //brand
                            $product_brand = wc_get_product_terms( $post->id, 'product_brand', array( 'fields' => 'all' ) );
                            if(count($product_brand)>0){
                              $product_brand=$product_brand[0];
                            }
                            $links_temp=array();
                            foreach($links as $link){ 
                                $links_temp[]=$link;
                                if(count($product_category)>0 && array_key_exists('term',$link) ){
                                    if(array_key_exists('taxonomy',$link['term'])){
                                        if($link['term']->taxonomy=='product_brand'){
                                           $links_temp[]=array(
                                                  "text"=>$product_category->name,
                                                  "url"=>get_term_link( $product_category->term_id, 'product_cat' )."?product_brand=".$product_brand->slug
                                            );   
                                        }
                                    }  
                                }
                             }
                             $links=$links_temp;
            }
            return $links;
        }
        add_filter('wpseo_breadcrumb_links', 'add_category_to_breadcrumb', 10, 1 );
  
}



function remove_current_from_breadcrumb($links){
        if(!QueryCache::get_field("breadcrumb_remove_current", "options")){
            return $links;
        }
		foreach ($links as $key => $link) {
	        // Mevcut sayfa, post veya termi kontrol et ve kaldır
	        if (is_singular() && isset($link['url']) && $link['url'] === get_permalink()) {
	            unset($links[$key]);
	        }
	        if (is_category() && isset($link['url']) && $link['url'] === get_category_link(get_queried_object_id())) {
	            unset($links[$key]);
	        }
	        // Daha fazla koşul eklenebilir...
	    }

	    return array_values($links); // Dizi indekslerini yeniden düzenle
}
add_filter('wpseo_breadcrumb_links', 'remove_current_from_breadcrumb', 9999, 1 );



function remove_home_from_breadcrumb($links){
        if(!QueryCache::get_field("breadcrumb_remove_home", "options")){
            return $links;
        }
		if($links){
			if ($links[0]['url'] == get_site_url()."/") { 
				array_shift($links); 
			}			
		}
		return $links;
}
	
add_filter('wpseo_breadcrumb_links', 'remove_home_from_breadcrumb', 9999, 1 );



// Breadcrumb'a eklemek için option'dan alınan repeater verisi
function add_page_path_to_breadcrumb($links) {
            $breadcrumb_add_page_path = QueryCache::get_field("breadcrumb_add_page_path", "options"); // repeater
            if (empty($breadcrumb_add_page_path) || !is_array($breadcrumb_add_page_path)) {
                return $links;
            }
            global $post;
            $current_post_type = $post->post_type ?? null;

            if (!$current_post_type) return $links;

            // Option'daki repeater verisini al
            $pages_array = QueryCache::get_field("breadcrumb_add_page_path", "options");
            if (!$pages_array || !is_array($pages_array)) return $links;

            $page_id = null;

            // Bulunduğumuz post_type için eşleşen page id'yi al
            foreach ($pages_array as $item) {
                if (isset($item['post_type'], $item['page']) && $item['post_type'] === $current_post_type) {
                    $page_id = (int)$item['page'];
                    break;
                }
            }

            if (!$page_id) return $links;

            // Polylang varsa çevir
            if (function_exists('pll_get_post')) {
                $translated = pll_get_post($page_id);
                if ($translated) $page_id = (int)$translated;
            }

            // WPML varsa çevir
            if (function_exists('icl_object_id') || has_filter('wpml_object_id')) {
                if (has_filter('wpml_object_id')) {
                    $curr_lang = defined('ICL_LANGUAGE_CODE') ? ICL_LANGUAGE_CODE : (function_exists('wpml_current_language') ? wpml_current_language() : null);
                    if ($curr_lang) {
                        $translated = apply_filters('wpml_object_id', $page_id, 'page', false, $curr_lang);
                        if ($translated) $page_id = (int)$translated;
                    }
                } elseif (function_exists('icl_object_id')) {
                    $curr_lang = defined('ICL_LANGUAGE_CODE') ? ICL_LANGUAGE_CODE : null;
                    if ($curr_lang) {
                        $translated = icl_object_id($page_id, 'page', false, $curr_lang);
                        if ($translated) $page_id = (int)$translated;
                    }
                }
            }

            $page = get_post($page_id);
            if (!$page) return $links;

            // Parentları hazırla
            $page_link = [
                'id'  => $page->ID,
                'url' => get_permalink($page->ID),
                'text'=> get_the_title($page->ID)
            ];

            $parents = [];
            $parent_id = $page->post_parent;
            while ($parent_id) {
                $parent = get_post($parent_id);
                if ($parent) {
                    $parents[] = [
                        'id'  => $parent->ID,
                        'url' => get_permalink($parent->ID),
                        'text'=> get_the_title($parent->ID)
                    ];
                    $parent_id = $parent->post_parent;
                } else break;
            }
            $parents = array_reverse($parents);

            // Home tespiti
            $home_id = (int) get_option('page_on_front');
            $home_index = null;
            foreach ($links as $index => $link) {
                if (isset($link['id']) && intval($link['id']) === $home_id) {
                    $home_index = $index;
                    break;
                }
                if ($home_index === null && isset($link['url']) && untrailingslashit($link['url']) === untrailingslashit(home_url('/'))) {
                    $home_index = $index;
                    break;
                }
            }

            $to_insert = array_merge($parents, [$page_link]);
            if ($home_index !== null) {
                array_splice($links, $home_index + 1, 0, $to_insert);
            } else {
                $links = array_merge($to_insert, $links);
            }

            return $links;
}
add_filter('wpseo_breadcrumb_links', 'add_page_path_to_breadcrumb', 9999, 1);






if (ENABLE_MULTILANGUAGE =="qtranslate") {

    function fix_translate_on_breadcrumb($links){
            foreach($links as $key => $link){
                if(isset($link["url"])){
                    $text = $link["text"];
                    if(isset($link["ptarchive"])){
                        $post_type_object = get_post_type_object($link["ptarchive"]); // Post type objesini al
                        if ($post_type_object) {
                            $text_url = get_post_type_archive_link($link["ptarchive"]); // Arşiv URL'sini al
                            $text_translated = $post_type_object->labels->singular_name; // Singular label'ini al
                        }
                    }elseif(isset($link["taxonomy"])){
                        $term = get_term($link["term_id"]);
                        $text_url = $term->link;
                        $text_translated = $term->name;
                    }else{
                        $text_translated = qtranxf_use( qtranxf_getLanguage(), $link["text"], false, false);
                        $text_url = qtranxf_convertURL( $link["url"], qtranxf_getLanguage());
                        if($text == $text_translated && isset($link["id"])){
                           $text = get_post_field( "post_title", $link["id"]);
                           $text_translated = qtranxf_use( qtranxf_getLanguage(), $text, false, false);
                        }
                    }
                    $links[$key]["text"] = $text_translated;
                    $links[$key]["url"] = $text_url;                
                }
            }
        return $links;
    }
    add_filter('wpseo_breadcrumb_links', 'fix_translate_on_breadcrumb', 10, 1 );

}
*/