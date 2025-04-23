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
                    if($item->object == $object){
                        array_push($leaf_node, $item);
                        if($item->menu_item_parent > 0){
                           return get_leafnode_object($menu, "", $leaf_node, $menu_items, $item->menu_item_parent);
                           break;
                        }else{
                           return $leaf_node;
                           break;
                        }
                    }                    
                }else{
                     if($item->object == $object["object"] && $item->object_id == $object["object_id"]){
                        array_push($leaf_node, $item);
                        if($item->menu_item_parent > 0){
                           return get_leafnode_object($menu, "", $leaf_node, $menu_items, $item->menu_item_parent);
                           break;
                        }else{
                           return $leaf_node;
                           break;
                        }
                    } 
                }

    		}else{
                if($menu_item_parent>0){
	    	        if($item->ID == $menu_item_parent){
	    	      	    array_push($leaf_node, $item);
	    	      	    if($item->menu_item_parent > 0){
		                   return get_leafnode_object($menu, "", $leaf_node, $menu_items, $item->menu_item_parent);
		                   break;
		                }else{
		               	   return $leaf_node;
		               	   break;
		                }
	    	        }
    	        }
    		}
    	}
    }
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





// sayfa içi shoetcut linkler oluşturmak için
function get_recursive_child_menu_items($menu_items, $page_id) {
    $result = array();
    foreach ($menu_items as $menu_item) {
        if ($menu_item->object_id == $page_id) {
            $result = $menu_item;
            break;
        } else {
            $children = $menu_item->children();
            if (!empty($children)) {
              $submenu = get_recursive_child_menu_items($children, $page_id);
                if (!empty($submenu)) {
                    $result = $submenu;
                    break;
                }
            }
        }
    }
    return $result;
}
function get_child_menu_for_page($menu_name, $page_id) {
    $menu = new Timber\Menu($menu_name);
    return get_recursive_child_menu_items($menu->get_items(), $page_id);
}








// menu item'ini baz alarak root parent altındaki tum linklerden yeni bir menu oluşturmak için
function get_taxonomy_menu_child($menu_items, $id) {
    $result = array();
    foreach ($menu_items as $menu_item) {
        if ($menu_item->object == $taxonomy) {
            $result = $menu_item;
            break;
        } else {
            $children = $menu_item->children();
            if (!empty($children)) {
                $submenu = get_taxonomy_menu_child($children, $taxonomy);
                if (!empty($submenu)) {
                    $result = $submenu;
                    break;
                }
            }
        }
    }
    return $result;
}
function get_post_menu_child($menu_items, $post_type) {
    $result = array();
    if(!$menu_items){
        return $result;
    }
    foreach ($menu_items as $menu_item) {
        if ($menu_item->object == $post_type) {
            $result = $menu_item;
            break;
        } else {
            $children = $menu_item->children();
            if (!empty($children)) {
              $submenu = get_post_menu_child($children, $post_type);
                if (!empty($submenu)) {
                    $result = $submenu;
                    break;
                }
            }
        }
    }
    return $result;
}
function get_page_menu_child($menu_items, $page_id) {
    $result = array();
    if(!$menu_items){
        return $result;
    }
    foreach ($menu_items as $menu_item) {
        if ($menu_item->object_id == $page_id) {
            $result = $menu_item;
            break;
        } else {
            $children = $menu_item->children();
            if (!empty($children)) {
                $submenu = get_page_menu_child($children, $page_id);
                 if (!empty($submenu)) {
                    $result = $submenu;
                    break;
                }
            }
        }
    }
    return $result;
}
function get_root_menu_item($menu, $id, $type="object_id"){
    $result = array();
    foreach ($menu as $item) {
        if ($item->{$type} == $id) {
            $result = $item;
            break;
        } else {
            $children = $item->children();
            if (!empty($children)) {
              $submenu = get_root_menu_item($children, $id, $type);
                if (!empty($submenu)) {
                    $result = $submenu;
                    break;
                }
            }
        }
    }
    return $result;
}
function get_parent_menu_item($menu, $id, $type="post_parent"){
    $result = array();
    foreach ($menu as $item) {
        if ($item->{$type} == $id) {
            $result = $item;
            break;
        } else {
            $children = $item->children();
            if (!empty($children)) {
              $submenu = get_parent_menu_item($children, $id, $type);
                if (!empty($submenu)) {
                    $result = $submenu;
                    break;
                }
            }
        }
    }
    return $result;
}
function get_root_menu_for_page($menu, $page_id=-1) {
    //print("<pre>".print_r(wp_get_nav_menu_items("header"), true)."</pre>");
    $menu_items = array(
       "nodes" => array(),
       "items" => array()
    );
    if(empty($menu)){
        $menu = "header";
    }
    if(is_numeric($menu)){
        $menu = wp_get_nav_menu_object($menu);
        if ($menu) {
            $menu =  $menu->name;
        }
    }
    if(empty($page_id)){
        $page_id = -1;
    }

    global $post;
    $menu = Timber::get_menu($menu);

    //$page_id = intval($page_id);

    if($page_id < 0){
        $page_id = $post->ID;
    }else{
        if($page_id == 0){
            $menu_items["nodes"] = array($post->ID);
            $menu_items["items"] = $menu->get_items();
            return $menu_items;
        }else{
            $menu_item = get_page_menu_child($menu->children(), $page_id);
            $ancestors = get_post_ancestors($menu_item);
            if($ancestors){
                $id = end($ancestors);
                $menu_items["nodes"] = $ancestors;
                $menu_items["items"] = get_root_menu_item($menu->get_items(), $page_id)->children();
            }else{
                $menu_items["nodes"] = array($page_id);
                if($menu_item){
                    $menu_items["items"] = $menu_item->children();
                }else{
                    $menu_item = get_page_menu_child($menu->get_items(), $page_id);
                    if($menu_item){
                        $menu_items["items"] = $menu_item->children();
                    }else{
                        $menu_items["items"] = array();
                    }
                }
            }
            return $menu_items;
        }
    }


    if(is_post_type_archive()){

        global $wp_query;
        $query_vars = $wp_query->query_vars;
        if(array_key_exists("post_type", $query_vars)){
           $post_type = $query_vars['post_type'];
        }
        $nodes = get_leafnode_object($post_type);
        $menu_items["nodes"] = wp_list_pluck($nodes, "db_id");
        $menu_items["items"] = get_root_menu_item($menu->get_items()->children(), end($nodes)->db_id, "db_id");

    }elseif(is_single()){

        global $wp_query;
        $query_vars = $wp_query->query_vars;
        if(array_key_exists("post_type", $query_vars)){
           $post_type = $query_vars['post_type'];
        }
        $nodes = get_leafnode_object($post_type);
        $menu_items["nodes"] = wp_list_pluck($nodes, "db_id");
        $menu_items["items"] = get_root_menu_item($menu->get_items()->children(), end($nodes)->db_id, "db_id");

    }elseif(is_tax()){

        global $wp_query;
        $query_vars = $wp_query->query_vars;
        if(array_key_exists("taxonomy", $query_vars)){
           $taxonomy = $query_vars['taxonomy'];
           $term = $query_vars['term'];
           $term_obj = get_term_by("slug", $term, $taxonomy);
           $nodes = get_leafnode_object(array("object"=>$taxonomy, "object_id" => $term_obj->term_id));
           $menu_items["nodes"] = wp_list_pluck($nodes, "object_id");
           if($menu_items["nodes"]){
              if(end($menu_items["nodes"]) == 0){
                $menu_items["nodes"] = wp_list_pluck($nodes, "db_id");
                $menu_items["items"] = get_root_menu_item($menu->get_items()->children(), end($nodes)->db_id, "db_id");
              }else{
                $menu_items["items"] = get_root_menu_item($menu->get_items()->children(), end($nodes)->object_id);
              }
           }
        }

    }else{

        $menu_item = get_page_menu_child($menu->get_items(), $page_id);
        $ancestors = get_post_ancestors($menu_item);
        if($ancestors){
            $id = end($ancestors);
            $menu_items["nodes"] = $ancestors;//wp_list_pluck($nodes, "object_id");
            $menu_items["items"] = get_root_menu_item($menu->get_items(), $id)->children();
        }else{
            $menu_items["nodes"] = array($page_id);
            if($menu_item){
                $menu_items["items"] = $menu_item->children();
            }else{
                $menu_item = get_page_menu_child($menu->get_items(), $page_id);
                if($menu_item){
                    $menu_items["items"] = $menu_item->children();
                }else{
                    $menu_items["items"] = array();
                }
            }
        }
        
    }
    return $menu_items;//get_root_menu_item($menu->get_items(), $id);
}







function wp_query_addition($args, $vars){
	if(isset($vars["taxonomy"])){
		if(!isset($args["tax_query"])){
			$args["tax_query"] = array();
		}
		$args["tax_query"]["relation"] = "AND";
		foreach($vars["taxonomy"] as $key => $tax){
			$tax_field = "slug";
			if(is_array($tax)){
				if(is_numeric($tax[0]) || ctype_digit($tax[0])){
					$tax_field = "term_id";
				}
			}
			$args["tax_query"][] = array(
				'taxonomy' => $key,
				'field'    => $tax_field,
				'terms'    => $tax,
				'operator' => 'IN',
				'include_children' => 0
			);                
		}
	}
	if(isset($vars["meta"])){
		if(!isset($args["meta_query"])){
			$args["meta_query"] = array();
		}
		$args["meta_query"]["relation"] = "AND";
		foreach($vars["meta"] as $key => $meta){
			$args["meta_query"][] = array(
				array(
					'key' => $key,
					'value' => $meta,
					'compare' => '='
				)
			);                
		}
	}
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
    if($nodes){
        $breadcrumb = '<div class="breadcrumb-container">'.
            '<ul class="breadcrumb">';
            $index = 1;
            foreach($nodes as $key => $node){
                $breadcrumb .= '<li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem" class="'.($key = count($nodes)-1?"breadcrumb_last":"").'">';
                                if($link){
                                    $breadcrumb .= '<a href="'.(isset($node["link"])?$node["link"]:get_permalink($node["ID"])).'">';
                                }
                                    $breadcrumb .= '<span itemprop="name">'.(ENABLE_MULTILANGUAGE?qtranxf_use($GLOBALS["language"], $node["post_title"], false):$node["post_title"]).'</span>' .
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
    global $wp_query;
    $loop = 'notfound';

    if ( $wp_query->is_page ) {
        $loop = is_front_page() ? 'front' : 'page';
    } elseif ( $wp_query->is_home ) {
        $loop = 'home';
    } elseif ( $wp_query->is_single ) {
        $loop = ( $wp_query->is_attachment ) ? 'attachment' : 'single';
    } elseif ( $wp_query->is_category ) {
        $loop = 'category';
    } elseif ( $wp_query->is_tag ) {
        $loop = 'tag';
    } elseif ( $wp_query->is_tax ) {
        $loop = 'tax';
    } elseif ( $wp_query->is_archive ) {
        if ( $wp_query->is_day ) {
            $loop = 'day';
        } elseif ( $wp_query->is_month ) {
            $loop = 'month';
        } elseif ( $wp_query->is_year ) {
            $loop = 'year';
        } elseif ( $wp_query->is_author ) {
            $loop = 'author';
        } else {
            $loop = 'archive';
        }
    } elseif ( $wp_query->is_search ) {
        $loop = 'search';
    } elseif ( $wp_query->is_404 ) {
        $loop = 'notfound';
    }

    return $loop;
}


function get_page_type(){
    $page_type = "";
        if(is_single()){
            $page_type = "post";
        }else if(is_page()){
            $page_type = "page";
        }else if(is_tag()){
            $page_type = "tag";
        }else if(is_category()){
            $page_type = "category";
        }else if(is_tax()){
            $page_type = "tax"; 
        }else if(is_archive()){
            $page_type = "tax_archive";
        }else if(is_home()){
            $page_type = "home";
        }else if(is_front_page()){
            $page_type = "front";
        }else if(is_search()){
            $page_type = "search";
        }else if(is_404()){
            $page_type = "404";
        }else if(is_author()){
            $page_type = "author";
        }else if(is_post_type_archive()){
            $page_type = "post_archive";
        }else if(ENABLE_ECOMMERCE){
            if(is_shop()){
                $page_type = "shop";
            }elseif(is_singular('product')){
                $page_type = "product";
            }elseif(is_product_category()){
                $page_type = "product_cat";
            }elseif(is_checkout()){
                $page_type = "checkout";
            }elseif(is_page_template('favorites')){
                $page_type = "favorites";
            }elseif(is_tax("product_brand")){
                $page_type = "product_brand";
            }elseif(is_tax()){
                $page_type = "product_tax";
            }
        }
    return $page_type;
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
    $query = QueryCache::get_cached_query($args, "data");
    return $query["found_posts"] ?? 0;
    /*
    if ($query->have_posts()) {
        return count($query->posts);
    } else {
        return 0;
    }*/
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

    $altSayfalar = get_posts($args);

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
    $value = QueryCache::get_cached_option("menu_locations");//get_cached_field("menu_locations", "option");
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
    $value = QueryCache::get_cached_option("menu_populate");//get_cached_field("menu_populate", "option");
    if($value){
        foreach($value as $item){
            $menu = $item["menu"];
            $post_type = [];
            $taxonomy = [];

            if(!empty($item["menu_item_post_type"])){
                $post_type["post_type"] = $item["menu_item_post_type"];
                $post_type["posts_per_page"] = $item["all_post_type"] ? -1 : $item["post_per_page"];
                $post_type["orderby"] = $item["orderby_post_type"];
                $post_type["order"] = $item["order_post_type"];
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
                $menu_item["post_type"] = ["post_type" => $post_type["post_type"]];
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
        }
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










