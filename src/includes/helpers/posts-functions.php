<?php



function get_archive_first_post($post_type){
	$args = array(
	    'post_type' => $post_type,
	    'numberposts' => 1
	);
	$post = Timber::get_post($args);
	if($post){
       return  wp_get_attachment_url( get_post_thumbnail_id($post->id) );
	}else{
	   return "";
	}
}
/*post's categories*/
function post_categories($post_id){
    $post_categories = wp_get_post_categories( $post_id );
	$cats = array();
	foreach($post_categories as $c){
		$cat = get_category( $c );
		$cats[] = array( 'id' => $cat->cat_ID, 'name' => $cat->name, 'slug' => $cat->slug , 'url' => get_category_link($cat->cat_ID));
	}
	return $cats;
}

/*post's tags*/
function post_tags($post_id){
    $post_tags = wp_get_post_tags( $post_id );
	$tags = array();
	foreach($post_tags as $c){
		$tag = get_tag( $c );
		$tags[] = array( 'id' => $tag->term_id, 'name' => $tag->name, 'slug' => $tag->slug , 'url' => get_tag_link($tag->term_id));
	}
	return $tags;
}

function get_thumbnail_from_posts($posts){
	foreach($posts as $post){
		if($post->thumbnail){
			return new TimberImage($post->thumbnail->id);
			exit;
		}
	}
}

function get_parent_top($post){
	if ($post->post_parent)	{
		$ancestors=get_post_ancestors($post->ID);
		$root=count($ancestors)-1;
		return $ancestors[$root];
	} else {
		return $post->ID;
	}	
}

function posts_to_menu($items){
	  $menu_order = count($items);
	  $child_items = array();
	  foreach ( $items as $item ) {
	      $item->title = $item->post_title;
	      $item->url =  get_permalink($item->ID);
	      $item->menu_item_parent = $item->post_parent;
	      $item->post_type = 'nav_menu_item';
	      $item->object = 'custom';
	      $item->type = 'custom';
	      $item->menu_order = ++$menu_order;
	      $child_items []= $item;
	  } 
	  return $child_items ;
}

function post_is_exist($id){
    return is_string( get_post_status( $id ) );
}


function get_posts_by_taxonomy_terms($post_type = "post", $ids = [], $orderby = "") {
    // Geçerli orderby değerleri listesi
    $valid_orderby_values = array('none', 'ID', 'author', 'title', 'name', 'type', 'date', 'modified', 'parent', 'rand', 'comment_count', 'relevance', 'menu_order', 'meta_value', 'meta_value_num', 'post__in', 'post_name__in', 'post_parent__in');

    // orderby değerini doğrula
    if (!empty($orderby) && !in_array($orderby, $valid_orderby_values)) {
        return new WP_Error('invalid_orderby', 'Geçersiz orderby değeri: ' . esc_html($orderby));
    }

    // Cache, cekirdek fonksiyonun icindeki Timber::get_terms / Timber::get_posts
    // cagrilarinin QueryCache::get_timber_terms / get_timber_posts'a cevrilmesiyle
    // ID-bazli yapilir. Boylece obje serialize sorunu olmaz, her term sorgusu
    // kendi transient'ina duser, post/term save'de otomatik invalidate olur.
    return _get_posts_by_taxonomy_terms_uncached( $post_type, $ids, $orderby );
}

/**
 * get_posts_by_taxonomy_terms'un cache'siz cekirdegi.
 * Cache QueryCache::wrap ile wrapper fonksiyonda yapilir.
 */
function _get_posts_by_taxonomy_terms_uncached($post_type = "post", $ids = [], $orderby = "") {
    // İlgili post türü için taksonomileri al
    $taxonomies = get_object_taxonomies($post_type, 'objects');
    if($taxonomies){
        $taxonomies = array_filter($taxonomies, function($taxonomy) {
            return $taxonomy->public;
        });
    }

    $results = array();

    $use_cache = class_exists( 'QueryCache' ) && \QueryCache::is_enabled();

    // Her bir taksonomi için terimleri al
    // NOT: terms cache'lenmiyor — get_timber_terms hit'te 'include' ile yeniden
    // kurar ve term_order siralamasi kaybolur. Asil sorgu yuku get_posts'ta.
    foreach ($taxonomies as $taxonomy) {
        $terms = Timber::get_terms(array(
            'taxonomy' => $taxonomy->name,
            'hide_empty' => false,
            'order_by' => "term_order",
            "order" => "ASC"
        ));

        // Her bir terim için ilgili gönderileri al
        foreach ($terms as $term) {

            $term_slug = $term->slug;
            $default_lang = "";

            if (function_exists('pll_is_translated_post_type') && !pll_is_translated_post_type($post_type)) {
                // Varsayılan dilin term slug'ını alıyoruz
                $default_term = get_term(pll_get_term($term->term_id, pll_default_language()));

                // Term'in çevirisi yoksa varsayılan dildeki term'i kullan
                if ($default_term) {
                    $term_slug = $default_term->slug;
                    $default_lang = pll_default_language();
                }
            }
            $args = array(
                'post_type' => $post_type,
                'posts_per_page' => -1,
                'tax_query' => array(
                    array(
                        'taxonomy' => $taxonomy->name,
                        'field' => 'slug',
                        'terms' => $term_slug,
                    ),
                )
            );

            if(!empty($default_lang)){
                $args["lang"] = $default_lang;
            }
  
            if ($ids) {
                $args["post__in"] = $ids;
                // orderby değeri boşsa, post__in kullanılarak sıralama yapılır
                if (empty($orderby)) {
                    $args["orderby"] = 'post__in';
                } else {
                    // orderby değeri atanmışsa, bu değere göre sıralama yapılır
                    $args["orderby"] = $orderby;
                }
            } elseif (!empty($orderby)) {
                // IDs dizisi boşsa ve orderby değeri atanmışsa, sadece orderby kullanılır
                $args["orderby"] = $orderby;
            }

            $posts_query = $use_cache
                ? \QueryCache::get_timber_posts( $args )
                : Timber::get_posts( $args );
            $posts = is_array( $posts_query ) ? $posts_query : $posts_query->to_array();


            if ($posts) {
                $results[] = array(
                    'term' => $term,
                    'posts' => $posts,
                );
            }
        }
    }
    return $results;
}


function sort_posts_by_terms($posts, $term_key = 'terms') {
    usort($posts, function ($a, $b) use ($term_key) {
        $termsA = $a[$term_key];
        $termsB = $b[$term_key];

        // Eğer term yoksa, onu en sona koy
        if (empty($termsA)) return 1;
        if (empty($termsB)) return -1;

        // Termleri alfabetik olarak karşılaştır
        return strcmp(implode(', ', $termsA), implode(', ', $termsB));
    });

    return $posts;
}



function get_post_type_pagination($post_type="post"){
    $post_pagination = Data::get("post_pagination");
    if(is_array($post_pagination) && !empty($post_pagination)){
        if(in_array($post_type, array_keys($post_pagination))){
           return Data::get("post_pagination.{$post_type}");
        }        
    }
    return [];
}


function custom_search_add_term() {
    if ( have_posts() ) {
        $term = get_query_var("q");
        $post_type = get_query_var("qpt");
        // Search history artık SearchHistory::auto_track_search() hook'u ile otomatik kaydediliyor
    }
}

// ─── Contact Helpers ─────────────────────────────────────────────────────────

/**
 * Seçili contact post type'ını döndür.
 * ACF options_contact_post_type field'ından okur.
 *
 * @return string  Post type slug, yoksa boş string
 */
function get_contact_post_types(): string {
    static $cached = null;
    if ( $cached !== null ) return $cached;
    // SaltConfig (config-cache.php) üzerinden oku — ACF bağımlılığı yok
    $pt = \SaltHareket\SaltConfig::get( 'options_contact_post_types', [] );
    if ( is_array($pt) ) {
        // Multiple select — array olarak saklanıyor, ilk değeri al veya tüm arrayi döndür
        // Eski kullanım string bekliyordu — geriye dönük uyumluluk için implode
        return $cached = implode( ',', array_filter($pt) );
    }
    return $cached = (string) $pt;
}

/**
 * Contact postlarını döndür.
 * Seçili post type + isteğe bağlı contact-type taxonomy filtresi.
 *
 * @param string $type    contact-type taxonomy slug (boş = hepsi)
 * @param array  $args    WP_Query extra args
 * @return array          Timber Post array
 *
 * @example
 *   // Tüm contactlar
 *   $contacts = get_contacts();
 *
 *   // Sadece 'main' tipindekiler
 *   $contacts = get_contacts('main');
 *
 *   // Sadece 'standard', limit 5
 *   $contacts = get_contacts('standard', ['posts_per_page' => 5]);
 */
function get_contacts( string $type = '', array $args = [] ): array {
    $post_type = get_contact_post_types();
    if ( ! $post_type ) return [];

    $query_args = array_merge([
        'post_type'      => $post_type,
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'orderby'        => 'menu_order title',
        'order'          => 'ASC',
    ], $args);

    if ( $type ) {
        $query_args['tax_query'] = [[
            'taxonomy' => 'contact-type',
            'field'    => 'slug',
            'terms'    => $type,
        ]];
    }

    return \Timber\Timber::get_posts( $query_args ) ?: [];
}
