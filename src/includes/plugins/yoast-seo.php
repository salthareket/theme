<?php


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
    if(isset($GLOBALS["sitemap_exclude_post_ids"])){
    	$exclude = array_merge($exclude, $GLOBALS["sitemap_exclude_post_ids"] );
    }
	return $exclude;
}
add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', 'yoast_seo_exclude_posts_from_sitemap', 10, 1 );


function yoast_seo_exclude_terms_from_sitemap($exclude) {
	if(isset($GLOBALS["sitemap_exclude_term_ids"])){
    	$exclude = array_merge($exclude, $GLOBALS["sitemap_exclude_term_ids"] );
    }
    return $exclude;
}
add_filter( 'wpseo_exclude_from_sitemap_by_term_ids', 'yoast_seo_exclude_terms_from_sitemap', 10, 1 );




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




if(get_option("options_breadcrumb_from_menu")){
	function custom_breadcrumb_links($links) {
	    $location_key = 'header-menu'; // Theme location ID
	    $locations = get_nav_menu_locations();
	    $lang = $GLOBALS["language"];
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
			if(!get_option("options_breadcrumb_remove_home")){
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
}

if (ENABLE_ECOMMERCE) {
    if(get_option("options_breadcrumb_add_product_brand")){
        function add_brand_to_breadcrumb($links){
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
        }
        add_filter('wpseo_breadcrumb_links', 'add_brand_to_breadcrumb', 10, 1 );
    }
    if(get_option("options_breadcrumb_add_product_taxonomy")){
        function add_category_to_breadcrumb($links){
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
}

if(get_option("options_breadcrumb_remove_current")){
	function remove_current_from_breadcrumb($links){
		/*$last_item = array();
		if(count($links)>1){
			$last_item = $links[count($links)-1];
		}else if(count($links) == 1){
			$last_item = $links[0];
		}
		if($last_item){
			if(isset($last_item["taxonomy"]) && is_tax( $last_item["taxonomy"] )){
	           array_pop($links);
			}
			if(isset($last_item["id"]) && count($links) > 2 ){
	           array_pop($links);
	           $last_item = $links[count($links)-2];
				if(isset($last_item["taxonomy"]) && is_tax( $last_item["taxonomy"] )){
		           array_pop($links);
				}
			}
			global $post;
			if(isset($last_item["id"]) && $post->ID ){
	           array_pop($links);
			}
			if(isset($last_item["ptarchive"])){
			   array_pop($links);
			}			
		}
		return $links;*/
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
}

if(get_option("options_breadcrumb_remove_home")){
	function remove_home_from_breadcrumb($links){
		if($links){
			if ($links[0]['url'] == get_site_url()."/") { 
				array_shift($links); 
			}			
		}
		return $links;
	}
	add_filter('wpseo_breadcrumb_links', 'remove_home_from_breadcrumb', 9999, 1 );
}

/* Breadcrumb'a eklemek için option'dan alınan sayfa id'si
$breadcrumb_add_page_path = get_option("options_breadcrumb_add_page_path");
if (!empty($breadcrumb_add_page_path)) {
    function add_page_path_to_breadcrumb($links) {
        // Orijinal option'dan page id al
        $orig_page_id = get_option("options_breadcrumb_add_page_path");
        if (!$orig_page_id) return $links;

        $page_id = (int)$orig_page_id;

        // Polylang varsa, o anki dile çevrilmiş versiyonunu al
        if (function_exists('pll_get_post')) {
            $translated = pll_get_post($page_id);
            if ($translated) $page_id = (int)$translated;
        }

        // WPML varsa, çevrilmiş versiyon al (fallback ile)
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

        // Eklenecek sayfa ve parentları hazırla (her link'e id ekliyoruz)
        $page_link = array(
            'id'  => $page->ID,
            'url' => get_permalink($page->ID),
            'text'=> get_the_title($page->ID)
        );

        $parents = array();
        $parent_id = $page->post_parent;
        while ($parent_id) {
            $parent = get_post($parent_id);
            if ($parent) {
                $parents[] = array(
                    'id'  => $parent->ID,
                    'url' => get_permalink($parent->ID),
                    'text'=> get_the_title($parent->ID)
                );
                $parent_id = $parent->post_parent;
            } else {
                break;
            }
        }
        // En üst parent'tan başlayacak şekilde sırala
        $parents = array_reverse($parents);

        // Home tespiti: önce id ile dene (page_on_front)
        $home_id = (int) get_option('page_on_front');
        $home_index = null;

        foreach ($links as $index => $link) {
            // Eğer link id alanı varsa doğrudan karşılaştır
            if (isset($link['id']) && intval($link['id']) === $home_id) {
                $home_index = $index;
                break;
            }

            // Fallback: bazı breadcrumb sağlayıcılar id koymaz, o zaman URL ile kontrol et
            if ($home_index === null && isset($link['url']) && untrailingslashit($link['url']) === untrailingslashit(home_url('/'))) {
                $home_index = $index;
                break;
            }
        }

        // Eklenecek blok (parents + page)
        $to_insert = array_merge($parents, array($page_link));

        if ($home_index !== null) {
            // Home'dan sonra ekle
            array_splice($links, $home_index + 1, 0, $to_insert);
        } else {
            // Home bulunmadıysa başa ekle
            $links = array_merge($to_insert, $links);
        }

        return $links;
    }
    add_filter('wpseo_breadcrumb_links', 'add_page_path_to_breadcrumb', 9999, 1);
}*/

// Breadcrumb'a eklemek için option'dan alınan repeater verisi
add_filter("init", function(){
    $breadcrumb_add_page_path = get_field("breadcrumb_add_page_path", "option"); // repeater
    if (!empty($breadcrumb_add_page_path) && is_array($breadcrumb_add_page_path)) {
        function add_page_path_to_breadcrumb($links) {
            global $post;
            $current_post_type = $post->post_type ?? null;

            if (!$current_post_type) return $links;

            // Option'daki repeater verisini al
            $pages_array = get_field("breadcrumb_add_page_path", "option");
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
    }
});





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
