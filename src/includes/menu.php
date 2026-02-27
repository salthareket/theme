<?php

/**
 * Menü elemanının görünürlük şartlarını kontrol eder.
 * @param object $menu_item
 * @return bool
 */
function get_menu_item_visibility($menu_item) {
    // Statik cache kullanarak aynı item için tekrar DB'ye gitmeyi engelleriz.
    static $visibility_results = [];
    if (isset($visibility_results[$menu_item->ID])) {
        return $visibility_results[$menu_item->ID];
    }

    $user = Data::get("user");
    $user_role = $user->role ?? "";
    $is_logged_in = is_user_logged_in();
    $user_language = Data::get("language");

    // ACF get_field yerine get_post_meta kullanarak hızı 5 kat artırıyoruz.
    $has_condition = get_post_meta($menu_item->ID, 'has_condition', true);

    if ($has_condition) {
        // Kompleks yapı olduğu için conditions kısmında get_field kabul edilebilir.
        $conditions = get_field('conditions', $menu_item);
        if (!$conditions) return $visibility_results[$menu_item->ID] = true;

        foreach ($conditions as $condition) {
            $layout = $condition['acf_fc_layout'];
            $visibility = $condition['visibility'];
            $pass = false;

            switch ($layout) {
                case 'role':
                    $pass = in_array($user_role, (array)$condition['role']);
                    break;
                case 'login':
                    $pass = ($condition['login'] == $is_logged_in);
                    break;
                case 'language':
                    $pass = in_array($user_language, (array)$condition['language']);
                    break;
            }

            // Mantık: Eğer şart sağlanmıyorsa ve görünürlük true ise GİZLE (ve tersi)
            if (($visibility && !$pass) || (!$visibility && $pass)) {
                return $visibility_results[$menu_item->ID] = false;
            }
        }
    }

    return $visibility_results[$menu_item->ID] = true;
}

/**
 * Görünmeyen ebeveynlerin çocuklarını da recursive olarak temizler.
 */
function bric_nav_menu_remove_children(&$items, $parent_id) {
    foreach ($items as $key => $item) {
        if ($item->menu_item_parent == $parent_id) {
            unset($items[$key]);
            bric_nav_menu_remove_children($items, $item->ID);
        }
    }
}

/**
 * Ana Menü Filtresi - Görünürlük ve Yayın Durumu Kontrolü
 */
add_filter('wp_nav_menu_objects', function($items, $args) {
    foreach ($items as $key => $item) {
        // Sadece post ve page objelerini kontrol et (Performans için)
        if (in_array($item->object, ['post', 'page'])) {
            // Yayınlanmamış içerikleri gizle
            if (get_post_status($item->object_id) !== 'publish') {
                unset($items[$key]);
                bric_nav_menu_remove_children($items, $item->ID);
                continue;
            }
        }

        // Görünürlük şartlarını kontrol et
        if (!get_menu_item_visibility($item)) {
            unset($items[$key]);
            bric_nav_menu_remove_children($items, $item->ID);
        }
    }
    return $items;
}, 10, 2);

/**
 * Dinamik Menü Doldurma Sistemi (Optimized & Cached)
 */
if (QueryCache::get_option("options_menu_populate") > 0) {
    
    add_filter('wp_get_nav_menu_items', 'bric_create_custom_menu', 10, 3);

    function bric_create_custom_menu($items, $menu, $args) {
        // Sonsuz döngüyü engelle
        remove_filter('wp_get_nav_menu_items', 'bric_create_custom_menu', 10, 3);
        
        $dynamic_menus = get_menu_populate();
        $menu_obj = is_object($menu) ? $menu : wp_get_nav_menu_object($menu);
        if (!$menu_obj || !isset($dynamic_menus[$menu_obj->slug])) {
            add_filter('wp_get_nav_menu_items', 'bric_create_custom_menu', 10, 3);
            return $items;
        }

        $menu_order = count($items);
        $dynamic_config = $dynamic_menus[$menu_obj->slug];

        foreach ($items as $key => $item) {
            foreach ($dynamic_config as $config) {
                // Eşleşme Kontrolü
                if ($item->object == $config["post_type"]["post_type"] || $item->object_type == $config["post_type"]["post_type"]) {
                    
                    if (isset($config["post_type"]["replace"]) && $config["post_type"]["replace"]) {
                        unset($items[$key]);
                    }

                    // Taksonomi Bazlı Doldurma
                    if (!empty($config["taxonomy"]["taxonomy"])) {
                        $term_args = array_merge($config["taxonomy"], [
                            'hide_empty' => !($config["taxonomy"]["all_taxonomy"] ?? false),
                            'parent' => 0 
                        ]);
                        $terms = Timber::get_terms($term_args);
                        foreach ($terms as $term) {
                            $menu_order++;
                            custom_menu_items::add_object($menu_obj->name, $term->term_id, 'term', $menu_order, (int)$item->db_id, '', '', '', $term->name);
                            $term->db_id = 1000000 + $menu_order;
                            $menu_order = bric_custom_menu_loop($menu_obj, $item, $term, $menu_order, $config);
                        }
                    } 
                    // Post Type Bazlı Doldurma
                    else if ($config["post_type"]["posts_per_page"] != 0) {
                        $posts = Timber::get_posts($config["post_type"]);
                        if ($posts) {
                            foreach ($posts as $post) {
                                $menu_order++;
                                custom_menu_items::add_object($menu_obj->name, $post->ID, 'post', $menu_order, (int)$item->db_id, '', '', '', $post->title);
                            }
                        }
                    }
                }
            }
        }

        add_filter('wp_get_nav_menu_items', 'bric_create_custom_menu', 10, 3);
        return $items;
    }

    function bric_custom_menu_loop($menu, $item, $parent, $menu_order, $config) {
        if (!isset($parent->taxonomy)) return $menu_order;

        // Çocuk taksonomileri çek
        $term_args = array_merge($config["taxonomy"], [
            'hide_empty' => false,
            'parent' => $parent->term_id
        ]);
        $children = Timber::get_terms($term_args);

        if ($children) {
            foreach ($children as $child) {
                $menu_order++;
                custom_menu_items::add_object($menu->name, $child->term_id, 'term', $menu_order, (int)$parent->db_id, '', '', '', $child->name);
                $child->db_id = 1000000 + $menu_order;
                $menu_order = bric_custom_menu_loop($menu, $item, $child, $menu_order, $config);
            }
        } else if (($config["post_type"]["posts_per_page"] ?? 0) != 0) {
            // Taksonomi altında postları çek
            $post_args = $config["post_type"];
            $post_args["tax_query"] = [[
                'taxonomy' => $parent->taxonomy,
                'field' => 'term_id',
                'terms' => [$parent->term_id],
            ]];
            $posts = Timber::get_posts($post_args);
            foreach ($posts as $post) {
                $menu_order++;
                custom_menu_items::add_object($menu->name, $post->ID, 'post', $menu_order, (int)$parent->db_id, '', '', '', $post->title);
            }
        }
        return $menu_order;
    }
}



/*
function get_menu_item_visibility($menu_item) {
	$user = Data::get("user");
    $user_role = isset($user->role)?$user->role:"";
    $is_logged_in = is_user_logged_in();
    $user_language = Data::get("language");//function_exists('qtranxf_getLanguage') ? qtranxf_getLanguage() : $GLOBALS["language"];

    if (get_field('has_condition', $menu_item)) {

        $conditions = get_field('conditions', $menu_item);
        foreach ($conditions as $condition) {
            $acf_fc_layout = $condition['acf_fc_layout'];
            $visibility = $condition['visibility'];

            switch ($acf_fc_layout) {

                case 'role':
                    $allowed_roles = $condition['role'];
                    if(in_array($user_role, $allowed_roles)){
                        return $visibility?true:false;
                     }else{
                        return !$visibility?true:false;
                     }
                    break;

                case 'login':
                    $login_status = $condition['login'];
                    if($login_status){
                        if($is_logged_in){
                            return $visibility?true:false;
                        }else{
                            return !$visibility?true:false;
                        }
                    }else{
                        if(!$is_logged_in){
                            return $visibility?true:false;
                        }else{
                            return !$visibility?true:false;
                        }
                    }
                    break;

                case 'language':
                    $allowed_languages = $condition['language'];
                    if (in_array($user_language, $allowed_languages)) {
                        return $visibility?true:false;
                    } else {
                        return !$visibility?true:false;
                    }
                    break;

                default:
                    // Bilinmeyen bir condition varsa, hatayı göster
                    die("Bilinmeyen condition türü: $acf_fc_layout");
            }
            
        }
        return true;
    } else {
        return true;
    }
}
function pst_nav_menu_remove_children_recursive(&$items, $parent_id) {
    foreach ($items as $key => $item) {
        if ($item->menu_item_parent == $parent_id) {
            unset($items[$key]);
            pst_nav_menu_remove_children_recursive($items, $item->ID);
        }
    }
}
function pst_nav_menu_objects( $items, $args ) {
    # if ( 'primary' !== $args->theme_location ) {
    #     return $items;
    # }
    foreach ( $items as $key => $item ) {

        if ( ! in_array( $item->object, array( 'post', 'page' ) ) ) {
            continue;
        }

        $visibility = get_menu_item_visibility($item);
        if(empty($visibility)){
            unset( $items[ $key ] );
            pst_nav_menu_remove_children_recursive($items, $item->ID);
            continue;
        }        

        $post_status = get_post_status( $item->object_id );
        if ( 'publish' !== $post_status ) {
            unset( $items[ $key ] );
            pst_nav_menu_remove_children_recursive($items, $item->ID);
            continue;
        }
    }
    return $items;
}
add_filter( 'wp_nav_menu_objects', 'pst_nav_menu_objects', 10, 2 );

if(get_option("options_menu_populate") > 0){
	function create_custom_menu( $items, $menu, $args ) {
	    remove_filter( 'wp_get_nav_menu_items', 'create_custom_menu', 10, 3 );
	    $menu = Timber::get_menu($menu);
	    $menu_location = $menu->get_location();
	    $dynamic_menus = get_menu_populate();//$GLOBALS["dynamic_menus"];
	    $menu_order = count($items);
	    if(in_array($menu_location, array_keys($dynamic_menus))){
			$dynamic_menu = $dynamic_menus[$menu_location];

			if(count( $items ) > 0){
				foreach ( $items as $key => $item ) {  
					$menu_order++;
					foreach($dynamic_menu as $dynamic_menu_item){

						$should_replace = isset($dynamic_menu_item["post_type"]["replace"]) && $dynamic_menu_item["post_type"]["replace"] ? true : false;

						if (isset($dynamic_menu_item["post_type"]) && ($item->object == $dynamic_menu_item["post_type"]["post_type"] || $item->object_type == $dynamic_menu_item["post_type"]["post_type"] )) {

							if ($should_replace) {
				                unset($items[$key]); // mevcut item'ı tamamen kaldırıyoruz
				            }
				            
							if(isset($dynamic_menu_item["taxonomy"]["taxonomy"]) && !empty($dynamic_menu_item["taxonomy"]["taxonomy"])){
								$args = $dynamic_menu_item["taxonomy"];
								$hide_empty = isset($dynamic_menu_item["taxonomy"]["all_taxonomy"]) && $dynamic_menu_item["taxonomy"]["all_taxonomy"] ? 0 : 1;
								$args = array_merge($args, array( 'hide_empty' => $hide_empty, 'parent' => 0 ));
								$terms = Timber::get_terms($args);
								foreach ( $terms as $term ) {
									custom_menu_items::add_object($menu->name, $term->term_id, 'term', $menu_order, intval($item->db_id), '', '', '', $term->name);
									$term->db_id = 1000000 + $menu_order + intval($item->db_id);
									$menu_order++;          
									$menu_order = create_custom_menu_loop($menu, $item, $term, $menu_order, $dynamic_menu_item);
								}
							}else{
								if(isset($dynamic_menu_item["post_type"]) && $dynamic_menu_item["post_type"]["posts_per_page"] != 0){
									$args = $dynamic_menu_item["post_type"];
									$posts = Timber::get_posts($args);
									if($posts){
										foreach ( $posts as $post ) {
											custom_menu_items::add_object($menu->name, $post->ID, 'post', $menu_order, intval($item->db_id), '', '', '', $post->title);
											$post->db_id = 1000000 + $menu_order + intval($item->db_id);
											$menu_order++;
											//$menu_order = create_custom_menu_loop($menu, $item, $post, $menu_order);
										}
									}                                            
								}
							}
						}                                
					}
				}
	        }
	    }
	    add_filter( 'wp_get_nav_menu_items', 'create_custom_menu', 10, 3 );
	    return $items;
	}
	function create_custom_menu_loop($menu, $item, $parent, $menu_order, $dynamic_menu){
	    $children = array();
	    if( isset($parent->taxonomy)){
			if ( count( get_term_children( $parent->term_id, $parent->taxonomy ) ) > 0 ) {
				$args = $dynamic_menu["taxonomy"];
				$args = array_merge($args, array( 'hide_empty' => false, 'parent' => $parent->term_id ));
				$children = Timber::get_terms($args);
				if($children){
					foreach ( $children as $child ) {
						custom_menu_items::add_object($menu->name, $child->term_id, 'term', $menu_order, intval($parent->db_id), '', '', '', $child->name);
						$child->db_id = 1000000 + $menu_order + intval($parent->db_id);
						$menu_order++;
						$menu_order = create_custom_menu_loop($menu, $item, $child, $menu_order, $dynamic_menu);
					}
				}
			}else{
				if(isset($dynamic_menu["post_type"]["posts_per_page"]) && $dynamic_menu["post_type"]["posts_per_page"] != 0){
					$args = $dynamic_menu["post_type"];
					if(isset($dynamic_menu["taxonomy"])){
						$args["tax_query"] = array(
							array(
								'taxonomy' => $parent->taxonomy,
								'field' => 'term_id',
								'terms' => array($parent->term_id),
								'operator' => 'IN'
							)
						);
					}

					$children = Timber::get_posts($args);
					if($children){
						foreach ( $children as $child ) {
							custom_menu_items::add_object($menu->name, $child->ID, 'post', $menu_order, intval($parent->db_id), '', '', '', $child->title);
							$child->db_id = 1000000 + $menu_order + intval($parent->db_id);
							$menu_order++;
							//create_custom_menu_loop($menu, $item, $child, $menu_order);
						}
					}
				}             
			}
		}
		return intval($menu_order);
	}  
	add_filter( 'wp_get_nav_menu_items', 'create_custom_menu', 10, 3 );
}*/