<?php

/*give slug get id*/
function slug2Id($slug) {
    global $wpdb;

    // İlk olarak postlarda ara
    $post_id = $wpdb->get_var($wpdb->prepare(
        "SELECT ID FROM $wpdb->posts WHERE post_name = %s",
        $slug
    ));

    // Eğer post bulunmazsa, term (kategori, etiket vb.) araması yap
    if (!$post_id) {
        $term = $wpdb->get_var($wpdb->prepare(
            "SELECT term_id FROM $wpdb->terms WHERE slug = %s",
            $slug
        ));

        // Term bulunursa term_id döndür
        if ($term) {
            return $term;
        }
    }

    // Eğer ikisi de bulunmazsa, null döndür
    return null;
}


function get_leafnode_object($menu, $object, $leaf_node=array(), $menu_items=array(), $menu_item_parent=0){
	if(count($menu_items)==0){
		$menu = wp_get_nav_menu_object( $menu );
	    $menu_items = wp_get_nav_menu_items($menu->term_id);
        //print_r($menu_items);
    }
    if($menu_items){

    	foreach($menu_items as $key=>$item){

    		if($object != ""){
                if(is_string($object)){
                    //print_r($item);
                    //echo $item->object_type ."==". $object;
                    if($item->object == $object || $item->object_type == $object){
                        array_push($leaf_node, $item);
                        if($item->menu_item_parent > 0){
                           return get_leafnode_object($menu, "", $leaf_node, $menu_items, $item->menu_item_parent);
                           //break;
                        }else{
                           return $leaf_node;
                           //break;
                        }
                    }                    
                }else{
                     if($item->object == $object["object"] && $item->object_id == $object["object_id"]){
                        array_push($leaf_node, $item);
                        if($item->menu_item_parent > 0){
                           return get_leafnode_object($menu, "", $leaf_node, $menu_items, $item->menu_item_parent);
                           //break;
                        }else{
                           return $leaf_node;
                           //break;
                        }
                    } 
                }

    		}else{
                if($menu_item_parent>0){
	    	        if($item->ID == $menu_item_parent){
	    	      	    array_push($leaf_node, $item);
	    	      	    if($item->menu_item_parent > 0){
		                   return get_leafnode_object($menu, "", $leaf_node, $menu_items, $item->menu_item_parent);
		                   //break;
		                }else{
		               	   return $leaf_node;
		               	   //break;
		                }
	    	        }
    	        }
    		}
    	}
    }
    return $leaf_node;
}


function qtranslatePostOrder($posts, $order = "asc"){
        $found_posts = array();
        foreach ( $posts as $k=>$post ) {
            $found_posts[ sanitize_title($post->title) ] = $post;
        }
        if($order=="asc"){
           ksort($found_posts);
        }else{
           krsort($found_posts);
        }
        $posts=array();
        foreach ($found_posts as $k=>$post) {
            $posts[] = $post;
        }
        return $posts;
}

function qtranslateTermOrder($terms, $order = "asc"){
	    $found_posts = array();
        foreach ( $terms as $k=>$term ) {
            $found_posts[ sanitize_title($term->name) ] = $term;
        }
        if($order=="asc"){
           ksort($found_posts);
        }else{
           krsort($found_posts);
        }
        $terms=array();
        foreach ($found_posts as $k=>$term) {
            $terms[] = $term;
        }
        return $terms;
}

function _custom_nav_menu_item( $title, $url, $order, $parent = 0 ){
	  $item = new stdClass();
	  $item->ID = 1000000 + $order + $parent;
	  $item->db_id = $item->ID;
	  $item->title = $title;
	  $item->url = $url;
	  $item->menu_order = $order;
	  $item->menu_item_parent = $parent;
	  $item->type = '';
	  $item->object = '';
	  $item->object_id = '';
	  $item->classes = array();
	  $item->target = '';
	  $item->attr_title = '';
	  $item->description = '';
	  $item->xfn = '';
	  $item->status = '';
	  return $item;
}


function safe_children($menu_item) {
    if (is_object($menu_item)) {
        if (method_exists($menu_item, 'children')) return $menu_item->children();
        if (property_exists($menu_item, 'children')) return $menu_item->children; // alternatif
    }
    return [];
}
function get_recursive_child_menu_items($menu_items, $page_id) {
    foreach ($menu_items as $menu_item) {
        if (isset($menu_item->object_id) && $menu_item->object_id == $page_id) {
            return $menu_item;
        }
        $children = safe_children($menu_item);
        if (!empty($children)) {
            $submenu = get_recursive_child_menu_items($children, $page_id);
            if (!empty($submenu)) return $submenu;
        }
    }
    return null;
}
function get_child_menu_for_page($menu_name, $page_id) {
    $menu = new Timber\Menu($menu_name);
    return get_recursive_child_menu_items($menu->get_items(), $page_id);
}
function get_taxonomy_menu_child($menu_items, $taxonomy) {
    foreach ($menu_items as $menu_item) {
        if (isset($menu_item->object) && $menu_item->object == $taxonomy) return $menu_item;
        $children = safe_children($menu_item);
        if (!empty($children)) {
            $submenu = get_taxonomy_menu_child($children, $taxonomy);
            if (!empty($submenu)) return $submenu;
        }
    }
    return null;
}
function get_post_menu_child($menu_items, $post_type) {
    foreach ($menu_items as $menu_item) {
        if (isset($menu_item->object) && $menu_item->object == $post_type) return $menu_item;
        $children = safe_children($menu_item);
        if (!empty($children)) {
            $submenu = get_post_menu_child($children, $post_type);
            if (!empty($submenu)) return $submenu;
        }
    }
    return null;
}
function get_page_menu_child($menu_items, $page_id) {
    return get_recursive_child_menu_items($menu_items, $page_id);
}
function get_root_menu_item($menu, $id, $type="object_id") {
    foreach ($menu as $item) {
        if (isset($item->{$type}) && $item->{$type} == $id) return $item;
        $children = safe_children($item);
        if (!empty($children)) {
            $submenu = get_root_menu_item($children, $id, $type);
            if (!empty($submenu)) return $submenu;
        }
    }
    return null;
}
function get_parent_menu_item($menu, $id, $type="post_parent") {
    foreach ($menu as $item) {
        if (isset($item->{$type}) && $item->{$type} == $id) return $item;
        $children = safe_children($item);
        if (!empty($children)) {
            $submenu = get_parent_menu_item($children, $id, $type);
            if (!empty($submenu)) return $submenu;
        }
    }
    return null;
}
/*function get_root_menu_for_page($menu, $page_id=-1) {
    $menu_items = ["nodes" => [], "items" => []];

    $initial_menu_name = $menu;
    if (empty($initial_menu_name)) $initial_menu_name = "header-menu";

    $locations = get_nav_menu_locations();
    $menu_id_or_name = $locations[$initial_menu_name] ?? $initial_menu_name;

    if (is_numeric($menu_id_or_name)) {
        $menu_obj = wp_get_nav_menu_object($menu_id_or_name);
        if ($menu_obj) $menu_id_or_name = $menu_obj->name;
    }
    
    global $post, $wp_query;
    $menu = Timber::get_menu($menu_id_or_name);
    if (!$menu || empty($menu->get_items())) {
        return $menu_items;
    }

    $all_menu_items = $menu->get_items(); // Tüm üst seviye menü öğeleri
    $current_menu_name = $menu->name; // get_leafnode_object için menü adını al

    if ($page_id < 0) $page_id = $post->ID;

    // Page 0 fallback (Ana sayfa)
    if ($page_id === 0) {
        $menu_items["nodes"] = [$post->ID];
        $menu_items["items"] = $all_menu_items; // Tüm üst seviyeyi döndür
        return $menu_items;
    }
    if (is_post_type_archive() || is_single()) {
        $post_type = $wp_query->query_vars['post_type'] ?? $post->post_type ?? null;
        if ($post_type) {
            $nodes = get_leafnode_object($current_menu_name, $post_type);
            if (is_array($nodes) && count($nodes) > 0) {
                $last_node = end($nodes);
                $menu_items["nodes"] = wp_list_pluck($nodes, "db_id"); 
                $root_menu_item = get_root_menu_item($all_menu_items, $last_node->db_id, "db_id");
                $children = safe_children($root_menu_item) ?? [];
                if($children){
                    $menu_items["items"] = $children;
                }else{
                    $menu_items["items"] = $all_menu_items;
                }
            }else{
                $menu_items["items"] = $all_menu_items;
            }
        }
        return $menu_items;
    }

    if (is_tax()) {
        $taxonomy = $wp_query->query_vars['taxonomy'] ?? null;
        $term = $wp_query->query_vars['term'] ?? null;
        if ($taxonomy && $term) {
            $term_obj = get_term_by("slug", $term, $taxonomy);
            $nodes = get_leafnode_object($current_menu_name, ["object"=>$taxonomy, "object_id" => $term_obj->term_id]);
            if ($nodes) {
                $last_node = end($nodes);
                $menu_items["nodes"] = wp_list_pluck($nodes, "object_id"); 
                $root_menu_item = get_root_menu_item($all_menu_items, $last_node->db_id, "db_id"); 
                $menu_items["items"] = safe_children($root_menu_item) ?? [];
            }
        }
        return $menu_items;
    }

    $menu_item = get_page_menu_child($all_menu_items, $page_id);        
    if ($menu_item) {
        $ancestors = get_post_ancestors($menu_item->object_id);
        if ($ancestors) {
            $root_page_id = end($ancestors); 
            $menu_items["nodes"] = $ancestors;
            $root_menu_item = get_root_menu_item($all_menu_items, $root_page_id, "object_id"); 
            $menu_items["items"] = safe_children($root_menu_item) ?? [];
        } else {
            $menu_items["nodes"] = [$page_id];
            $menu_items["items"] = safe_children($menu_item) ?? [];
        }
    } else {
        $menu_items["nodes"] = [];
        $menu_items["items"] = $all_menu_items ?? [];
    }
    return $menu_items;
}*/
function get_root_menu_for_page($menu, $page_id=-1) {
    $menu_items = ["nodes" => [], "items" => []];

    $initial_menu_name = $menu;
    if (empty($initial_menu_name)) $initial_menu_name = "header-menu";

    $locations = get_nav_menu_locations();
    $menu_id_or_name = $locations[$initial_menu_name] ?? $initial_menu_name;

    if (is_numeric($menu_id_or_name)) {
        $menu_obj = wp_get_nav_menu_object($menu_id_or_name);
        if ($menu_obj) $menu_id_or_name = $menu_obj->name;
    }
    
    global $post, $wp_query;
    $menu = Timber::get_menu($menu_id_or_name);
    if (!$menu || empty($menu->get_items())) {
        return $menu_items;
    }

    $all_menu_items = $menu->get_items(); // Tüm üst seviye menü öğeleri
    $current_menu_name = $menu->name; 
    
    if ($page_id === -1) {
        $menu_items["nodes"] = [];
        $menu_items["items"] = $all_menu_items;
        return $menu_items;
    }

    if ($page_id < 0) $page_id = $post->ID;

    if ((int)$page_id === 0) {
        $menu_items["nodes"] = [$post->ID];
        $menu_items["items"] = $all_menu_items; // Tüm üst seviyeyi döndür
        return $menu_items;
    }

    $menu_item = get_page_menu_child($all_menu_items, $page_id);
    if ($menu_item) {
        $ancestors = get_post_ancestors($menu_item->object_id);
        if ($ancestors) {
            $root_page_id = end($ancestors);  
            $menu_items["nodes"] = $ancestors;
            $root_menu_item = get_root_menu_item($all_menu_items, $root_page_id, "object_id");  
            if($root_menu_item){
                $menu_items["items"] = safe_children($root_menu_item) ?? [];
            }else{
                $menu_items["items"] = $all_menu_items ?? [];
            }
        } else {
            $menu_items["nodes"] = [$page_id];
            $menu_items["items"] = safe_children($menu_item) ?? [];
        }
    } else {
        $menu_items["nodes"] = [];
        $menu_items["items"] = $all_menu_items ?? [];
    }

    return $menu_items;
}




/**
 * WP_Query argümanlarına esnek taksonomi ve meta filtreleri ekler.
 *
 * @param array $args WP_Query argümanları dizisi.
 * @param array $vars Harici filtre değişkenleri dizisi (taxonomy ve meta içerir).
 * @return array Güncellenmiş WP_Query argümanları.
 */
function wp_query_addition(array $args, array $vars): array {

    // ===============================================================
    // 1. TAKSONOMİ FİLTRESİ İŞLEME (ESNEKLİK VE PLL UYUMLULUĞU)
    // ===============================================================
    if (isset($vars["taxonomy"]) && is_array($vars["taxonomy"])) {
        
        // Eğer tax_query yoksa başlat, varsa relation'ı AND olarak ayarla.
        if (!isset($args["tax_query"]) || !is_array($args["tax_query"])) {
            $args["tax_query"] = array('relation' => 'AND');
        } else {
             // Var olan bir tax_query varsa ve relation belirlenmemişse, AND olarak ayarla
             if (!isset($args["tax_query"]['relation'])) {
                 $args["tax_query"]['relation'] = 'AND';
             }
        }
        
        foreach ($vars["taxonomy"] as $taxonomy_key => $terms) {
            
            if (empty($terms)) continue; // Boş terim listelerini atla

            $tax_field = "slug";
            $term_ids  = [];
            
            // 💡 Güvenlik ve Esneklik: Tek bir değer de gelse dizi haline getir.
            $term_values = is_array($terms) ? $terms : [$terms];
            
            // Varsayılan olarak 'slug' ile başlar. İlk terim sayısal mı kontrol et.
            // Sayısal kontrolü `is_int` yerine `is_numeric` veya `ctype_digit` ile yapmak daha doğru.
            if (!empty($term_values) && (is_numeric($term_values[0]) || ctype_digit((string)$term_values[0]))) {
                $tax_field = "term_id";
                $term_ids  = array_map('intval', $term_values); // Güvenlik: Tamsayıya dönüştür
            } else {
                // Eğer slug geliyorsa, Polylang'ın filtrelemesini yoksayarak Term ID'lerini bul.
                // Bu, dil değişiminde bile TR slug'ına karşılık gelen term ID'yi bulmayı sağlar.
                
                foreach ($term_values as $slug) {
                    // get_term_by'a 'suppress_filter' ekleyerek PLL müdahalesini engelle
                    $term = get_term_by('slug', sanitize_title($slug), $taxonomy_key, OBJECT, [ 'suppress_filter' => true ]);
                    
                    if ($term && !is_wp_error($term)) {
                        $term_ids[] = (int) $term->term_id;
                    }
                }
                
                // Slug kullanmak yerine, bulunan ID'leri kullanmak daha güvenli ve performanslıdır.
                if (!empty($term_ids)) {
                    $tax_field = "term_id";
                    $term_values = $term_ids;
                } else {
                    // Hiçbir terim ID'si bulunamazsa bu sorgu parçasını atla
                    continue;
                }
            }

            // Tax Query Clause'u ekle
            $args["tax_query"][] = array(
                'taxonomy'         => $taxonomy_key,
                'field'            => $tax_field,
                'terms'            => $term_values,
                'operator'         => 'IN',
                'include_children' => 0 // Varsayılan olarak çocukları dahil etme (performans)
            );
        }
    }

    // ---------------------------------------------------------------

    // ===============================================================
    // 2. META FİLTRESİ İŞLEME (GÜVENLİK VE STANDARTLAŞTIRMA)
    // ===============================================================
    if (isset($vars["meta"]) && is_array($vars["meta"])) {
        
        // Eğer meta_query yoksa başlat, varsa relation'ı AND olarak ayarla.
        if (!isset($args["meta_query"]) || !is_array($args["meta_query"])) {
            $args["meta_query"] = array('relation' => 'AND');
        } else {
             // Var olan bir meta_query varsa ve relation belirlenmemişse, AND olarak ayarla
             if (!isset($args["meta_query"]['relation'])) {
                 $args["meta_query"]['relation'] = 'AND';
             }
        }
        
        foreach ($vars["meta"] as $key => $meta) {
            
            if (is_array($meta) && isset($meta['key']) && isset($meta['value'])) {
                // Gelişmiş Meta Sorgu formatı bekleniyorsa (key, value, compare, type vb. içeriyorsa)
                $meta_clause = array_merge(['compare' => '=', 'type' => 'CHAR'], $meta);
                
            } elseif (!is_array($meta)) {
                // Basit Eşitlik Sorgusu (key => value)
                $meta_clause = array(
                    'key'     => $key,
                    'value'   => sanitize_text_field($meta), // Güvenlik: Alanı temizle
                    'compare' => '='
                );
            } else {
                // Hata: Meta formatı anlaşılamadı. Atla.
                continue;
            }

            $args["meta_query"][] = $meta_clause;
        }
    }
    
    // ---------------------------------------------------------------

    return $args;
}

function get_page_url($slug){
	return get_permalink( get_page_by_path( $slug ) );
}

function post2Root($post_id, $fields = array(), $nodes = array()){
	$post = get_post($post_id);
	if(!$fields){
	   $nodes[] = $post_id;	   
    }else{
    	$item = array();
    	foreach($fields as $field){
    		if(isset($post->{$field})){
    			$item[$field] = $post->{$field};    			
    		}
    	}
        $nodes[] = $item;
	}
	if($post->post_parent > 0){
	   return post2Root($post->post_parent, $fields, $nodes);
	}else{
	   return array_reverse($nodes);
	}
}

function post2Breadcrumb($post_id = 0, $link = 1){
     echo $link;
	$nodes = post2Root($post_id, array("ID", "post_title"));
    return generate_breadcrumb($nodes, $link);
	/*$breadcrumb = "";
	if($nodes){
		$breadcrumb = '<div class="breadcrumb-container">'.
			'<ul class="breadcrumb">';
			$index = 1;
            foreach($nodes as $key => $node){
				$breadcrumb .= '<li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem" class="'.($key = count($nodes)-1?"breadcrumb_last":"").'">' .
							        '<a href="'.get_permalink($node["ID"]).'">' .
							            '<span itemprop="name">'.qtranxf_use($GLOBALS["language"], $node["post_title"], false).'</span>' .
							            '<meta itemprop="position" content="'.$index.'">' .
							        '</a>' .
							    '</li>';
            }
		$breadcrumb .= '</ul>' .
	    '</div>';		
	}
	return $breadcrumb;*/
}


function generate_breadcrumb($nodes=array(), $link=1){
    $breadcrumb = "";
    $language = Data::get("language");
    if($nodes){
        $breadcrumb = '<div class="breadcrumb-container">'.
            '<ul class="breadcrumb">';
            $index = 1;
            foreach($nodes as $key => $node){
                $breadcrumb .= '<li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem" class="'.($key = count($nodes)-1?"breadcrumb_last":"").'">';
                                if($link){
                                    $breadcrumb .= '<a href="'.(isset($node["link"])?$node["link"]:get_permalink($node["ID"])).'">';
                                }
                                $breadcrumb .= '<span itemprop="name">'.(ENABLE_MULTILANGUAGE?qtranxf_use($language, $node["post_title"], false):$node["post_title"]).'</span>' .
                                        '<meta itemprop="position" content="'.$index.'">';
                                if($link){
                                    $breadcrumb .='</a>';
                                }
                                $breadcrumb .= '</li>';
            }
        $breadcrumb .= '</ul>' .
        '</div>';       
    }
    return $breadcrumb;
}


function menuItemHasActiveChild($children=array(), $post_id=0){
	$active = false;
	foreach($children as $child){
		if($child->current || $child->object_id == $post_id){
			$active = true;
			break;
		}
		if($child->children){
			$active = menuItemHasActiveChild($child->children, $post_id);
		}
	}
	return $active;
}


function get_current_page_type() {
    static $current_type = null;
    if ( null !== $current_type ) return $current_type;

    global $wp_query;
    
    if ( $wp_query->is_front_page )             return $current_type = 'front';
    if ( $wp_query->is_home )                   return $current_type = 'home'; // Blog sayfası
    if ( $wp_query->is_page )                   return $current_type = 'page';
    if ( $wp_query->is_single )                 return $current_type = ( $wp_query->is_attachment ) ? 'attachment' : 'single';
    
    // Arşiv alt kırılımları (is_archive'den önce kontrol edilmeli)
    if ( $wp_query->is_category )               return $current_type = 'category';
    if ( $wp_query->is_tag )                    return $current_type = 'tag';
    if ( $wp_query->is_tax )                    return $current_type = 'tax';
    if ( $wp_query->is_author )                 return $current_type = 'author';
    if ( $wp_query->is_day )                    return $current_type = 'day';
    if ( $wp_query->is_month )                  return $current_type = 'month';
    if ( $wp_query->is_year )                   return $current_type = 'year';
    if ( $wp_query->is_post_type_archive )      return $current_type = 'archive'; // CPT Arşivleri
    
    if ( $wp_query->is_search )                 return $current_type = 'search';
    if ( $wp_query->is_404 )                    return $current_type = 'notfound';
    
    return $current_type = 'notfound';
}
function get_page_type() {
    static $page_type_cache = null;
    if ( null !== $page_type_cache ) return $page_type_cache;

    // E-Ticaret Kontrolleri (Daha spesifik oldukları için en üste aldık)
    if ( defined('ENABLE_ECOMMERCE') && ENABLE_ECOMMERCE ) {
        if ( is_shop() )                            return $page_type_cache = "shop";
        if ( is_singular('product') )               return $page_type_cache = "product";
        if ( is_product_category() )                return $page_type_cache = "product_cat";
        if ( is_checkout() )                        return $page_type_cache = "checkout";
        if ( is_tax("product_brand") )              return $page_type_cache = "product_brand";
        if ( is_tax() )                             return $page_type_cache = "product_tax";
    }

    // Özel Template Kontrolü
    if ( is_page_template('favorites') )            return $page_type_cache = "favorites";

    // Standart WP Kontrolleri
    if ( is_front_page() )                          return $page_type_cache = "front";
    if ( is_home() )                                return $page_type_cache = "home";
    if ( is_single() )                              return $page_type_cache = "post";
    if ( is_page() )                                return $page_type_cache = "page";
    if ( is_category() )                            return $page_type_cache = "category";
    if ( is_tag() )                                 return $page_type_cache = "tag";
    if ( is_tax() )                                 return $page_type_cache = "tax";
    if ( is_post_type_archive() )                   return $page_type_cache = "post_archive";
    if ( is_archive() )                             return $page_type_cache = "tax_archive";
    if ( is_search() )                              return $page_type_cache = "search";
    if ( is_author() )                              return $page_type_cache = "author";
    if ( is_404() )                                 return $page_type_cache = "404";

    return $page_type_cache = "";
}

function get_post_type_object_labels($post_type="post"){
    $post_type_object = get_post_type_object($post_type);
    if ($post_type_object && isset($post_type_object->labels)) {
        return (array) $post_type_object->labels;
    }
    return null;
}

function get_post_types_with_taxonomies() {
    $post_types = get_post_types(); // Tüm post tiplerini al
    $post_types_data = array();
    foreach ($post_types as $post_type) {
        $post_type_object = get_post_type_object($post_type); // Post type objesini al
        $taxonomies = get_object_taxonomies($post_type); // Post type ile ilişkili taxonomyleri al
        $post_types_data[$post_type] = array(
            'taxonomies' => $taxonomies,
        );
    }
    return $post_types_data;
}


function is_post_type_taxonomy($post_type, $taxonomy){
    return in_array($taxonomy, get_object_taxonomies($post_type));
}

function get_user_role($user_id=0) {
    if (is_user_logged_in()) {
        if($user_id){
            $user = get_user_by("ID", $user_id);
        }else{
            $user = wp_get_current_user();
        }
        $user_roles = $user->roles;
        
        if (count($user_roles) === 1) {
            return $user_roles[0]; // Kullanıcının sadece bir rolü varsa, bu rolü döndür
        } else {
            return $user_roles; // Kullanıcının birden fazla rolü varsa, rol dizisini döndür
        }
    } else {
        return false; // Kullanıcı oturum açmamışsa false döndür
    }
}


function wp_count_posts_by_query($args) {
    $query = new WP_Query($args);
    if ($query->have_posts()) {
        return count($query->posts);
    } else {
        return 0;
    }
}


function get_page_id_by_slug($slug){
    global $wpdb;
    $query = "SELECT ID FROM $wpdb->posts WHERE post_name = '$slug' AND post_type = 'page'";
    return $wpdb->get_var($query);
}


function get_page_deeper_link($sayfaID) {
    $args = array(
        'post_type'      => 'page',
        'post_parent'    => $sayfaID,
        'order'          => 'ASC',
        'orderby'        => 'menu_order'
    );

    $altSayfalar = get_posts($args);//get_posts($args);

    if ($altSayfalar) {
        // İlk child sayfasının ID'sini al
        $ilkChildID = $altSayfalar[0]->ID;

        // İlk child sayfasının altında daha fazla sayfa var mı kontrol et
        return get_page_deeper_link($ilkChildID);
    } else {
        // Alt sayfa bulunamazsa veya hata oluşursa
        return get_permalink($sayfaID);
    }
}


function update_or_add_post_meta($post_id, $meta_key, $new_value) {
    if (metadata_exists('post', $post_id, $meta_key)) {
        update_post_meta($post_id, $meta_key, $new_value);
    } else {
        add_post_meta($post_id, $meta_key, $new_value);
    }
}

function get_menu_locations() {
    $locations = [
        'header-menu' => 'Header Menu',
        'footer-menu' => 'Footer Menu',
    ];
    $value = get_field("menu_locations", "options");//get_cached_field("menu_locations", "option");
    if (empty($value)) {
        return $locations;
    }
    $formatted_locations = $locations;
    foreach ($value as $location) {
        $menu_name = $location["name"]; // Array'deki ilk değeri alır
        $key = sanitize_title($menu_name); // 'Header Menu' => 'header-menu'
        $formatted_locations[$key] = $menu_name;
    }
    return $formatted_locations;
}

function get_menu_populate(){
    $arr = [];
    //$value = get_option("options_menu_populate");//get_cached_field("menu_populate", "option");
    //if($value){
        foreach($value as $item){
            $menu = $item["menu"];
            $post_type = [];
            $taxonomy = [];

            if(!empty($item["menu_item_post_type"])){
                $post_type["post_type"] = $item["menu_item_post_type"];
                $post_type["posts_per_page"] = $item["all_post_type"] ? -1 : $item["post_per_page"];
                $post_type["orderby"] = $item["orderby_post_type"];
                $post_type["order"] = $item["order_post_type"];
                $post_type["replace"] = $item["replace"];
            }

            if(!empty($item["menu_item_taxonomy"])){
                $taxonomy["taxonomy"] = $item["menu_item_taxonomy"];
                $taxonomy["number"] = $item["all_taxonomy"] ? 0 : $item["number"];
                $taxonomy["orderby"] = $item["orderby_taxonomy"];
                $taxonomy["order"] = $item["order_taxonomy"];
            }

            $menu_item = [];
            if(isset($post_type["posts_per_page"]) && $post_type["posts_per_page"] != 0){
                $menu_item["post_type"] = $post_type;
            }else{
                $menu_item["post_type"] = ["post_type" => $post_type["post_type"], "replace" => $post_type["replace"]];
            }
            if(isset($taxonomy["taxonomy"])){
                $menu_item["taxonomy"] = $taxonomy;
            }

            // Eğer aynı menu isminde daha önce bir item eklenmişse, array'e ekleyelim.
            if(isset($arr[$menu])){
                $arr[$menu][] = $menu_item;
            } else {
                // Eğer yoksa, yeni bir array oluşturup ekleyelim.
                $arr[$menu][] = $menu_item;
            }
        //}
    }
    return $arr;
}


function wp_query_to_sql($type = "post", $query = [], $helper = []) {
    global $wpdb;

    // Başlangıç SQL sorgusu
    $sql = "SELECT ";

    // Koşulları tutacak dizi
    $conditions = [];

    // Meta ve Tax Query işlemleri
    $meta_conditions = [];
    $tax_conditions = [];

    switch ($type) {
        case 'post':
            // SQL sorgusunun başlangıcı ve tablo
            $sql .= "p.* FROM {$wpdb->posts} p";
            
            // Post type kontrolü
            if (isset($query['post_type'])) {
                $post_type = esc_sql($query['post_type']);
                $conditions[] = "p.post_type = '$post_type'";
            }
            $meta_table = $wpdb->postmeta;
            $meta_where = "pm.post_id = p.ID";
            break;

        case 'comment_v1':
            // SQL sorgusunun başlangıcı ve tablo
            $sql .= "c.* FROM {$wpdb->comments} c";

            // Status ve comment_type kontrolü
            if (isset($query['status'])) {
                if(!empty($query['status'])){
                    $status = esc_sql($query['status']);
                    $conditions[] = "c.comment_approved = '$status'";                    
                }
            }
            if (isset($query['comment_type'])) {
                if(!empty($query['comment_type'])){
                    $comment_type = esc_sql($query['comment_type']);
                    $conditions[] = "c.comment_type = '$comment_type'";
                }
            }
            if (isset($query['post__in'])) {
                if(!empty($query['post__in'])){
                    $post_ids = implode(', ', array_map('intval', $query['post__in']));
                    $conditions[] = "c.comment_post_ID IN ($post_ids)";                    
                }

            }
            if (isset($query['user_id'])) {
                if(!empty($query['user_id'])){
                    $user_id = intval($query['user_id']);
                    $conditions[] = "c.user_id = $user_id";                    
                }
            }
            $meta_table = $wpdb->commentmeta;
            $meta_where = "pm.comment_id = c.comment_ID";
            break;

        case 'comment':
            $sql .= "c.* FROM {$wpdb->comments} c";
            if (isset($query['post__in']) && empty($helper)) {
                $post_ids = implode(', ', array_map('intval', $query['post__in']));
                $conditions[] = "c.comment_post_ID IN ($post_ids)";
            } else {
                if (isset($helper['comment_post_type'])) {
                    $post_type = esc_sql($helper['comment_post_type']);
                    $conditions[] = "p.post_type = '$post_type'";
                }
                if (isset($helper['comment_taxonomy']) && isset($helper['comment_terms'])) {
                    $taxonomy = esc_sql($helper['comment_taxonomy']);
                    $terms = implode(', ', array_map('intval', $helper['comment_terms']));
                    $conditions[] = "c.comment_post_ID IN (
                        SELECT p.ID FROM {$wpdb->posts} p
                        INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                        INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                        WHERE p.post_type = '$post_type' AND tt.taxonomy = '$taxonomy' AND tt.term_id IN ($terms)
                    )";
                }
            }
            if (isset($query['post_id']) && !empty($query['post_id'])) {
                $post_id = intval($query['post_id']);
                $conditions[] = "c.comment_post_ID = $post_id";
            }
            if (isset($query['status']) && !empty($query['status'])) {
                $status = esc_sql($query['status']);
                $conditions[] = "c.comment_approved = '$status'";
            }
            if (isset($query['comment_type']) && !empty($query['comment_type'])) {
                $comment_type = esc_sql($query['comment_type']);
                $conditions[] = "c.comment_type = '$comment_type'";
            }
            if (isset($query['user_id']) && !empty($query['user_id'])) {
                $user_id = intval($query['user_id']);
                $conditions[] = "c.user_id = $user_id";
            }
            $meta_table = $wpdb->commentmeta;
            $meta_where = "pm.comment_id = c.comment_ID";
        break;

        case 'user':
            // SQL sorgusunun başlangıcı ve tablo
            $sql .= "u.* FROM {$wpdb->users} u";

            // Role kontrolü
            if (isset($query['role'])) {
                $role = esc_sql($query['role']);
                $conditions[] = "u.ID IN (
                    SELECT user_id FROM {$wpdb->usermeta}
                    WHERE meta_key = '{$wpdb->prefix}capabilities' AND meta_value LIKE '%$role%'
                )";
            }
            $meta_table = $wpdb->usermeta;
            $meta_where = "pm.user_id = u.ID";
            break;

        case 'taxonomy':
            // SQL sorgusunun başlangıcı ve tablo
            $sql .= "t.* FROM {$wpdb->terms} t";

            // Taxonomy kontrolü
            if (isset($query['taxonomy'])) {
                $taxonomy = $query['taxonomy'];
                if(!is_array($taxonomy)){
                   $taxonomy = explode(",", $taxonomy);
                }
                $taxonomies = array_map('esc_sql', $taxonomy);
                $taxonomy_list = implode(', ', array_map(function($tax) { return "'$tax'"; }, $taxonomies));                    
                $conditions[] = "t.term_id IN (
                    SELECT term_id FROM {$wpdb->term_taxonomy}
                    WHERE taxonomy IN ($taxonomy_list)
                )";
            }

            // Include kontrolü
            if (isset($query['include'])) {
                $term_ids = implode(', ', array_map('intval', $query['include']));
                $conditions[] = "t.term_id IN ($term_ids)";
            }

            // Hide Empty kontrolü
            if (isset($query['hide_empty']) && $query['hide_empty']) {
                $conditions[] = "t.term_id IN (
                    SELECT term_id FROM {$wpdb->term_taxonomy}
                    WHERE count > 0
                )";
            }
            $meta_table = $wpdb->termmeta;
            $meta_where = "pm.term_id = t.term_id";
            break;
    }

    // Meta Query işlemleri
    if (isset($query['meta_query'])) {
        $meta_conditions = [];
        $relation = isset($query['meta_query']['relation']) ? esc_sql($query['meta_query']['relation']) : 'AND';

        //print_r($query['meta_query']);
        foreach ($query['meta_query'] as $meta) {
            if (isset($meta['key']) && isset($meta['value'])) {
                $key = esc_sql($meta['key']);
                $value = $meta['value'];
                $compare = esc_sql($meta['compare'] ?? '=');

                if (is_array($value) || $compare == "IN" || $compare == "NOT IN") {
                    if (!is_array($value)) {
                        $value = explode(",", $value);
                    }
                    $arr = "";
                    foreach($value as $val_key =>  $val){
                        if(is_numeric($val)){
                            $arr .= $val;
                        }else{
                            $arr .= "'".$val."'";
                        }
                        if($val_key < count($value)-1){
                            $arr .= ",";
                        }

                    }
                    $meta_conditions[] = $wpdb->prepare(
                        "EXISTS (
                            SELECT 1 FROM {$meta_table} pm
                            WHERE ".$meta_where." 
                            AND pm.meta_key = %s
                            AND pm.meta_value IN ($arr)
                        )",
                        $key
                    );
                } else {
                    if (is_numeric($value)) {
                        $value = intval($value); // Sayısal değer
                    } else {
                        $value = esc_sql($value); // Metin değeri
                    }

                    if ($compare == 'LIKE') {
                        $meta_conditions[] = $wpdb->prepare(
                            "EXISTS (
                                SELECT 1 FROM {$meta_table} pm
                                WHERE ".$meta_where." 
                                AND pm.meta_key = %s
                                AND pm.meta_value LIKE %s
                            )",
                            $key,
                            '%' . $value . '%'
                        );
                    } else {
                        if (is_numeric($value)) {
                            $meta_conditions[] = $wpdb->prepare(
                                "EXISTS (
                                    SELECT 1 FROM {$meta_table} pm
                                    WHERE ".$meta_where." 
                                    AND pm.meta_key = %s
                                    AND pm.meta_value $compare %d
                                )",
                                $key,
                                $value
                            );
                        } else {
                            $meta_conditions[] = $wpdb->prepare(
                                "EXISTS (
                                    SELECT 1 FROM {$meta_table} pm
                                    WHERE ".$meta_where." 
                                    AND pm.meta_key = %s
                                    AND pm.meta_value $compare %s
                                )",
                                $key,
                                $value
                            );
                        }
                    }
                }
            }
        }
        if ($meta_conditions) {
            $conditions[] = '(' . implode(" $relation ", $meta_conditions) . ')';
        }
    }

    // Tax Query işlemleri
    if (isset($query['tax_query'])) {
        $relation = isset($query['tax_query']['relation']) ? esc_sql($query['tax_query']['relation']) : 'AND';
        $tax_query_conditions = [];

        foreach ($query['tax_query'] as $tax) {
            if (is_array($tax)) {
                $taxonomy = esc_sql($tax['taxonomy']);
                $terms = implode(', ', array_map('intval', $tax['terms']));
                $tax_query_conditions[] = $wpdb->prepare(
                    "EXISTS (
                        SELECT 1 FROM {$wpdb->term_relationships} tr
                        INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                        INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                        WHERE tt.taxonomy = %s
                        AND t.term_id IN ($terms)
                        AND tr.object_id = p.ID
                    )",
                    $taxonomy
                );
            }
        }

        if (!empty($tax_query_conditions)) {
            $tax_conditions[] = '(' . implode(" $relation ", $tax_query_conditions) . ')';
        }
    }

    // Koşulları ekle
    if (!empty($meta_conditions)) {
        $conditions[] = '(' . implode(' AND ', $meta_conditions) . ')';
    }
    if (!empty($tax_conditions)) {
        $conditions[] = '(' . implode(' AND ', $tax_conditions) . ')';
    }

    // WHERE koşulunu oluştur
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }

    // ORDER BY koşulunu ekle
    if (isset($query['orderby'])) {
        $orderby = esc_sql($query['orderby']);
        $order = isset($query['order']) ? esc_sql($query['order']) : 'ASC';
        switch($type){
            case "post" :
               $sql .= " ORDER BY p.$orderby $order";
            break;
            case "taxonomy":
                $sql .= " ORDER BY t.$orderby $order";
            break;
            case "user":
                $sql .= " ORDER BY u.$orderby $order";
            break;
        }
        
    }

    return $sql;
}


function get_title_from_url($url) {

    if(empty($url)){
        return "";
    }
    
    // URL'den sayfa veya post ID'sini almak için
    $page_id = url_to_postid($url);

    if ($page_id) {
        // Post ID varsa, post type'ı alıyoruz
        $post_type = get_post_type($page_id);

        // Eğer bu bir sayfa ise başlığı döndür
        if ($post_type) {
            return get_the_title($page_id);
        } elseif ($post_type) {
            // Eğer bu bir custom post type ise, onun single label'ını döndür
            $post_type_object = get_post_type_object($post_type);
            return $post_type_object->labels->singular_name;
        }
    } else {
        // Eğer page_id yoksa, yani arşiv ya da taksonomi ise:

        $endpoint = getUrlEndpoint($url);

        // URL'deki parçaları alalım
        
        if (!empty($endpoint)) {

            // İlk segmenti post type archive olarak kabul edelim
            $post_type = $endpoint;

            // Eğer bu bir post type arşivi ise
            if (post_type_exists($post_type)) {
                $post_type_object = get_post_type_object($post_type);
                return $post_type_object->labels->singular_name;
            }

            // Kategoriler, etiketler ve diğer custom taksonomileri kontrol edelim
            // Kategori ise
            if (term_exists($endpoint, 'category')) {
                return get_term_by('slug', $endpoint, 'category')->name;
            }

            // Etiket ise
            if (term_exists($endpoint, 'post_tag')) {
                return get_term_by('slug', $endpoint, 'post_tag')->name;
            }

            // Diğer custom taksonomiler için kontrol edelim
            $taxonomies = get_taxonomies([], 'names');
            foreach ($taxonomies as $taxonomy) {
                if (term_exists($endpoint, $taxonomy)) {
                    return get_term_by('slug', $endpoint, $taxonomy)->name;
                }
            }
        }

        // Eğer hiç bir şey bulunamazsa varsayılan başlığı döndür
        return 'Başlık bulunamadı';
    }
}










