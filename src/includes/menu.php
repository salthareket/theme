<?php

function get_menu_item_visibility($menu_item) {
    $user_role = isset($GLOBALS["user"]->role)?$GLOBALS["user"]->role:"";
    $is_logged_in = is_user_logged_in();
    $user_language = function_exists('qtranxf_getLanguage') ? qtranxf_getLanguage() : $GLOBALS["language"];

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
        if ( 'publish' === $post_status ) {
            continue;
        }
        unset( $items[ $key ] );
    }
    return $items;
}
add_filter( 'wp_nav_menu_objects', 'pst_nav_menu_objects', 10, 2 );




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
					if (isset($dynamic_menu_item["post_type"]) && $item->object == $dynamic_menu_item["post_type"]["post_type"] ) {
						if(isset($dynamic_menu_item["taxonomy"]["taxonomy"]) && !empty($dynamic_menu_item["taxonomy"]["taxonomy"])){
							$taxonomy = $dynamic_menu_item["taxonomy"];
							$args = $taxonomy;
							$args = array_merge($args, array( 'hide_empty' => false, 'parent' => 0 ));
							$terms = Timber::get_terms($args);
							foreach ( $terms as $term ) {
								custom_menu_items::add_object($menu->name, $term->term_id, 'term', $menu_order, $item->db_id, '', '', '', $term->name);
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
					custom_menu_items::add_object($menu->name, $child->term_id, 'term', $menu_order, $parent->db_id, '', '', '', $child->name);
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
if(get_option("options_menu_populate") > 0){
	add_filter( 'wp_get_nav_menu_items', 'create_custom_menu', 10, 3 );
}