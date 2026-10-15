<?php

/**
 * Menu visibility, post status filter, dynamic menu population.
 *
 * - Menü item'larına ACF ile eklenen koşullar (role, login, language) kontrol edilir
 * - Yayınlanmamış post/page'ler menüden gizlenir
 * - ACF Options'tan tanımlanan dinamik menü doldurma (post type / taxonomy bazlı)
 *
 * @package SaltHareket
 */

// =========================================================================
// VISIBILITY
// =========================================================================

/**
 * Menü elemanının görünürlük şartlarını kontrol eder.
 */
function get_menu_item_visibility( $menu_item ): bool {
    static $cache = [];
    if ( isset( $cache[ $menu_item->ID ] ) ) return $cache[ $menu_item->ID ];

    $user     = Data::get( 'user' );
    $role     = $user->role ?? '';
    $logged   = is_user_logged_in();
    $lang     = Data::get( 'language' );

    $has_cond = get_post_meta( $menu_item->ID, 'has_condition', true );

    if ( $has_cond ) {
        $conditions = QueryCache::get_field( 'conditions', $menu_item );
        if ( ! $conditions ) return $cache[ $menu_item->ID ] = true;

        foreach ( $conditions as $c ) {
            $layout = $c['acf_fc_layout'];
            $vis    = $c['visibility'];
            $pass   = match ( $layout ) {
                'role'     => in_array( $role, (array) $c['role'] ),
                'login'    => ( $c['login'] == $logged ),
                'language' => in_array( $lang, (array) $c['language'] ),
                default    => true,
            };

            if ( ( $vis && ! $pass ) || ( ! $vis && $pass ) ) {
                return $cache[ $menu_item->ID ] = false;
            }
        }
    }

    return $cache[ $menu_item->ID ] = true;
}

/**
 * Görünmeyen ebeveynlerin çocuklarını recursive temizler.
 */
function bric_nav_menu_remove_children( array &$items, int $parent_id ): void {
    foreach ( $items as $key => $item ) {
        if ( (int) $item->menu_item_parent === $parent_id ) {
            unset( $items[ $key ] );
            bric_nav_menu_remove_children( $items, $item->ID );
        }
    }
}


// =========================================================================
// MENU FILTER — Visibility + Post Status
// =========================================================================

add_filter( 'wp_nav_menu_objects', function ( $items, $args ) {
    foreach ( $items as $key => $item ) {
        // Yayınlanmamış post/page gizle
        if ( in_array( $item->object, [ 'post', 'page' ], true ) ) {
            if ( get_post_status( $item->object_id ) !== 'publish' ) {
                unset( $items[ $key ] );
                bric_nav_menu_remove_children( $items, $item->ID );
                continue;
            }
        }

        // ACF visibility koşulları
        if ( ! get_menu_item_visibility( $item ) ) {
            unset( $items[ $key ] );
            bric_nav_menu_remove_children( $items, $item->ID );
        }
    }
    return $items;
}, 10, 2 );


// =========================================================================
// DYNAMIC MENU POPULATION
// =========================================================================

function get_menu_populate(): array {
    $arr   = [];
    // ACF bağımsız — doğrudan wp_options'dan oku
    $value = class_exists('QueryCache') ? \QueryCache::get_option( 'options_menu_populate', [] ) : get_option( 'options_menu_populate', [] );
    if ( ! is_array( $value ) || empty( $value ) ) return $arr;

    foreach ( $value as $item ) {
        $menu      = $item['menu'];
        $post_type = [];
        $taxonomy  = [];

        if ( ! empty( $item['menu_item_post_type'] ) ) {
            $post_type = [
                'post_type'      => $item['menu_item_post_type'],
                'posts_per_page' => $item['all_post_type'] ? -1 : ( $item['post_per_page'] ?? 10 ),
                'orderby'        => $item['orderby_post_type'] ?? 'menu_order',
                'order'          => $item['order_post_type'] ?? 'ASC',
                'replace'        => $item['replace'] ?? false,
            ];
        }

        if ( ! empty( $item['menu_item_taxonomy'] ) ) {
            $taxonomy = [
                'taxonomy' => $item['menu_item_taxonomy'],
                'number'   => $item['all_taxonomy'] ? 0 : ( $item['number'] ?? 10 ),
                'orderby'  => $item['orderby_taxonomy'] ?? 'name',
                'order'    => $item['order_taxonomy'] ?? 'ASC',
                'show_posts' => ! empty( $item['show_posts'] ),
                'hide_empty' => ! empty( $item['hide_empty'] ),
            ];
        }

        $menu_item = [];
        if ( ! empty( $taxonomy['taxonomy'] ) ) {
            // Taxonomy secilmisse, post_type bilgisini koru ama posts_per_page'i
            // sadece leaf term'lerde post gostermek icin kullan
            $menu_item['taxonomy'] = $taxonomy;
            // show_posts aktifse leaf term'lerde post goster
            $leaf_posts_per_page = 0;
            if ( ! empty( $taxonomy['show_posts'] ) ) {
                $leaf_posts_per_page = (int) ( $item['post_per_page'] ?? 0 );
                if ( $leaf_posts_per_page === 0 && ! empty( $item['all_post_type'] ) ) {
                    $leaf_posts_per_page = -1;
                } elseif ( $leaf_posts_per_page === 0 ) {
                    $leaf_posts_per_page = -1; // show_posts aktif, en az -1 olmali
                }
            }
            $menu_item['post_type'] = [
                'post_type' => $post_type['post_type'] ?? '',
                'replace'   => $post_type['replace'] ?? false,
                'posts_per_page' => $leaf_posts_per_page,
                'orderby'   => $post_type['orderby'] ?? 'menu_order',
                'order'     => $post_type['order'] ?? 'ASC',
            ];
        } elseif ( ! empty( $post_type['posts_per_page'] ) && $post_type['posts_per_page'] != 0 ) {
            $menu_item['post_type'] = $post_type;
        } else {
            $menu_item['post_type'] = [
                'post_type' => $post_type['post_type'] ?? '',
                'replace'   => $post_type['replace'] ?? false,
            ];
        }

        $arr[ $menu ][] = $menu_item;
    }

    return $arr;
}


if ( count( (array) \QueryCache::get_option( 'options_menu_populate', [] ) ) > 0 ) {

    add_filter( 'wp_get_nav_menu_items', 'bric_create_custom_menu', 10, 3 );

    // Priority 999 — tüm inject'ler bittikten sonra (custom_menu_items priority 20 dahil)
    // Final item array'indeki tüm taxonomy term ID'lerini 'terms' cache group'una bulk yaz
    // Timber Menu::build() bundan sonra _wp_menu_item_classes_by_context() çağırır
    // get_term_ancestors() → get_term() → WP_Term::get_instance() → wp_cache_get(id, 'terms')
    // Cache'te olan term'ler için DB sorgusu yok → ~1100q tasarruf
    // Priority 5 — Polylang'ın wp_get_nav_menu_items filter'ından ÖNCE çalış
    // Polylang her taxonomy item için pll_get_term() → get_term() yapıyor
    // term'leri bulk yükleyerek WP term cache'i doldur → Polylang 0q çalışır
    // Bu aynı zamanda custom_menu_items inject (priority 20) öncesi de çalışır
    add_filter( 'wp_get_nav_menu_items', function( $items, $menu, $args ) {
        // Gerçek WP nav_menu_item'larının object_id term'lerini bulk yükle
        $term_ids = [];
        $real_item_ids = []; // gerçek nav_menu_item post ID'leri (link_type meta bulk load için)
        foreach ( $items as $item ) {
            if ( ! is_object($item) ) continue;
            if ( ($item->type ?? '') === 'taxonomy' ) {
                $oid = (int)($item->object_id ?? 0);
                if ( $oid && ! wp_cache_get($oid, 'terms') ) {
                    $term_ids[] = $oid;
                }
            }
            // Gerçek item ID'leri — post meta bulk load için
            $iid = (int)($item->ID ?? 0);
            if ( $iid && $iid < 1000000 ) {
                $real_item_ids[] = $iid;
            }
        }
        if ( ! empty($term_ids) ) {
            $terms = get_terms(['include' => array_unique($term_ids), 'hide_empty' => false, 'number' => 0, 'fields' => 'all']);
            if ( is_array($terms) && ! is_wp_error($terms) ) {
                foreach ( $terms as $term ) {
                    if ( $term instanceof \WP_Term ) {
                        wp_cache_set( $term->term_id, $term, 'terms' );
                        wp_cache_set( $term->term_id, $term, $term->taxonomy );
                    }
                }
            }
        }
        // Gerçek nav_menu_item'larının post meta'sını bulk yükle
        // update_meta_cache() WP'nin meta cache sistemini tek sorguda doldurur
        // Sonraki get_post_meta($id, 'link_type') çağrıları 0q ile döner
        if ( ! empty($real_item_ids) ) {
            update_meta_cache( 'post', array_unique($real_item_ids) );
        }
        return $items;
    }, 5, 3 );

    // timber/post/pre_meta filter — fake ID'ler (>=1000000) için boş meta döndür, DB'ye gitme
    add_filter( 'timber/post/pre_meta', function( $meta, $post_id, $args, $post ) {
        if ( $post_id >= 1000000 ) return [];
        return $meta;
    }, 10, 4 );

    // acf/decode_post_id filter — fake ID'ler için ACF'nin get_post() yapmasını önle
    // MenuItem->get_properties → Timber __get → meta() → ACF get_field → decode_post_id → get_post(fake_id)
    // WooOrder::decode_woo_order_id fake ID'leri WooCommerce order sanıyor → get_post_type → get_post
    add_filter( 'acf/decode_post_id', function( $post_id ) {
        if ( is_numeric($post_id) && (int)$post_id >= 1000000 ) {
            return null; // Fake populate item — ACF field yok, decode etme
        }
        return $post_id;
    }, 1 );

    // WP get_post_metadata filter — fake ID'ler için DB'ye gitme
    add_filter( 'get_post_metadata', function( $value, $post_id, $meta_key, $single ) {
        if ( $post_id >= 1000000 ) return $single ? '' : [];
        return $value;
    }, 10, 4 );

    // Fake ID post stub writer — helper closure
    // WP_Post::get_instance() cache'i kontrol eder: wp_cache_get($id, 'posts')
    // 'raw' filter'lı WP_Post → cache hit → sanitize atlanır → direkt return → 0q
    $write_fake_post_stub = function( int $post_id ): void {
        $ps = new \stdClass();
        $ps->ID = $post_id; $ps->post_title = ''; $ps->post_name = '';
        $ps->post_type = 'nav_menu_item'; $ps->post_status = 'publish';
        $ps->post_parent = 0; $ps->guid = ''; $ps->post_content = '';
        $ps->post_excerpt = ''; $ps->post_author = 0;
        $ps->post_date = $ps->post_date_gmt = '0000-00-00 00:00:00';
        $ps->comment_status = $ps->ping_status = 'closed';
        $ps->menu_order = 0; $ps->filter = 'raw';
        $ps->post_mime_type = ''; $ps->comment_count = 0;
        wp_cache_set( $post_id, new \WP_Post( $ps ), 'posts' );
    };

    // clean_post_cache stub'ı silince hemen geri yaz
    add_action( 'clean_post_cache', function( $post_id ) use ( $write_fake_post_stub ) {
        if ( (int)$post_id >= 1000000 ) {
            $write_fake_post_stub( (int)$post_id );
        }
    } );

    // get_post() son savunma — stub cache'te yoksa ve query gidiyorsa yakala
    // clean_post_cache döngüsü aşıyorsa burası 0 satır döndürür → WP_Post::get_instance() false → ama stub zaten yazılmış
    add_filter( 'query', function( $query ) use ( $write_fake_post_stub ) {
        if ( preg_match('/SELECT \* FROM `?(\w+)`? WHERE ID = (\d+) LIMIT/i', $query, $m ) ) {
            $pid = (int)$m[2];
            if ( $pid >= 1000000 ) {
                $write_fake_post_stub( $pid );
                return "SELECT 0 WHERE 0=1";
            }
        }
        return $query;
    }, 1 );


    // Polylang pll_get_post — fake ID için null döndür
    add_filter( 'pll_get_post', function( $post_id, $lang = '' ) {
        if ( (int)$post_id >= 1000000 ) return null;
        return $post_id;
    }, 10, 2 );

    // nav_menu_item_title filter — fake ID'ler için WC/Polylang DB sorgusunu engelle
    // Timber MenuItem::title() → apply_filters('nav_menu_item_title', ..., $this->wp_object, ...)
    // WC veya Polylang bu filter'da get_post(item->ID) yapıyor
    add_filter( 'nav_menu_item_title', function( $title, $item, $args, $depth ) {
        if ( is_object($item) && isset($item->ID) && (int)$item->ID >= 1000000 ) {
            return $item->title ?? $title;
        }
        return $title;
    }, 1, 4 );

    // wp_nav_menu_objects — populate taxonomy term'lerini bulk cache'e al
    // _wp_menu_item_classes_by_context() her term için get_term() çağırıyor → bu önce bulk yükle
    // Polylang varsa fake item'lara _pll_skip işareti ekle (çeviri sorgusu yapmasın)
    add_filter( 'wp_nav_menu_objects', function( $items, $args ) {
        $term_ids      = [];
        $is_multilang  = defined('ENABLE_MULTILANGUAGE') && ENABLE_MULTILANGUAGE;
        foreach ( $items as &$item ) {
            if ( ! is_object($item) ) continue;
            $iid = (int)($item->ID ?? 0);
            if ( $iid >= 1000000 ) {
                // Polylang varsa çeviri sorgusu yapmasın
                if ( $is_multilang && ! isset($item->_pll_skip) ) {
                    $item->_pll_skip = true;
                }
                $oid = (int)($item->object_id ?? 0);
                if ( $oid && ($item->type ?? '') === 'taxonomy' && ! wp_cache_get($oid, 'terms') ) {
                    $term_ids[] = $oid;
                }
            }
        }
        // Bulk term load — tek sorguyla tüm taxonomy term'lerini çek, WP object cache doldur
        // Sonraki get_term() çağrıları cache hit → 0q
        if ( ! empty($term_ids) ) {
            get_terms(['include' => array_unique($term_ids), 'hide_empty' => false, 'number' => 0]);
        }
        return $items;
    }, 1, 2 );

    function bric_create_custom_menu( $items, $menu, $args ) {
        static $cache = [];

        remove_filter( 'wp_get_nav_menu_items', 'bric_create_custom_menu', 10, 3 );

        $dynamic_menus = get_menu_populate();
        $menu_obj      = is_object( $menu ) ? $menu : wp_get_nav_menu_object( $menu );

        if ( ! $menu_obj || ! isset( $dynamic_menus[ $menu_obj->slug ] ) ) {
            // Polylang dilli varyant slug'ı ana slug'a eşitle (header-en → header, header-tr → header)
            $base_slug = '';
            if ( $menu_obj && defined('ENABLE_MULTILANGUAGE') && ENABLE_MULTILANGUAGE === 'polylang' ) {
                $lang_list = function_exists('pll_languages_list') ? ( pll_languages_list(['fields'=>'slug']) ?: [] ) : [];
                if ( $lang_list ) {
                    $base_slug = preg_replace( '/(-(' . implode('|', $lang_list) . '))$/', '', $menu_obj->slug );
                }
            }
            if ( ! $base_slug || ! isset( $dynamic_menus[ $base_slug ] ) ) {
                add_filter( 'wp_get_nav_menu_items', 'bric_create_custom_menu', 10, 3 );
                return $items;
            }
            $menu_obj_slug_key = $base_slug;
        } else {
            $menu_obj_slug_key = $menu_obj->slug;
        }

        $cache_key     = 'bric_menu_pop_' . $menu_obj->slug . '_' . md5( serialize( $dynamic_menus[ $menu_obj_slug_key ] ) );
        $transient_key = 'bric_mp_items_' . md5( $cache_key );

        // ── Request-level cache ───────────────────────────────────────────────
        if ( isset( $cache[ $cache_key ] ) ) {
            // Daha önce bu request'te build edildi — singleton zaten dolu
            add_filter( 'wp_get_nav_menu_items', 'bric_create_custom_menu', 10, 3 );
            return $cache[ $cache_key ];
        }

        // ── Transient cache — hazır item array'i singleton'a inject et ────────
        // Taxonomy/post sorguları yapılmaz, sadece custom_menu_items'a yazılır
        $cached_menu_items = get_transient( $transient_key );
        if ( $cached_menu_items !== false && is_array( $cached_menu_items ) ) {
            $instance = custom_menu_items::get_instance();
            $instance->menus[ $menu_obj->slug ] = $menu_obj->slug;

            $post_ids_to_preload = [];
            foreach ( $cached_menu_items as $mi ) {
                $instance->menu_items[] = $mi;

                $fake_id = (int)( $mi['ID'] ?? 0 );
                $oid     = (int)( $mi['object_id'] ?? 0 );

                // Post type item — gerçek post ID (product vb.)
                // Polylang pll_get_post() → get_post() yapar. WP post cache dolduralım.
                if ( ( $mi['type'] ?? '' ) === 'post_type' && $oid && $oid < 1000000 ) {
                    if ( ! wp_cache_get($oid, 'posts') ) {
                        $post_ids_to_preload[] = $oid;
                    }
                }

                // Fake ID post stub
                if ( $fake_id >= 1000000 && ! wp_cache_get( $fake_id, 'posts' ) ) {
                    $ps = new \stdClass();
                    $ps->ID = $fake_id; $ps->post_title = $mi['title'] ?? '';
                    $ps->post_name = $mi['slug'] ?? ''; $ps->post_type = 'nav_menu_item';
                    $ps->post_status = 'publish'; $ps->post_parent = (int)($mi['post_parent']??0);
                    $ps->guid = $mi['url'] ?? ''; $ps->post_content = $ps->post_excerpt = '';
                    $ps->post_author = 0; $ps->post_date = $ps->post_date_gmt = '0000-00-00 00:00:00';
                    $ps->comment_status = $ps->ping_status = 'closed';
                    $ps->menu_order = (int)($mi['order']??0); $ps->filter = 'raw';
                    wp_cache_set( $fake_id, new \WP_Post( $ps ), 'posts' );
                }

                // Term stub
                if ( ( $mi['type'] ?? '' ) === 'taxonomy' && ! empty( $mi['object_id'] ) && ! empty( $mi['object'] ) ) {
                    $tid = (int) $mi['object_id'];
                    if ( ! wp_cache_get( $tid, 'terms' ) ) {
                        $ts = new \stdClass();
                        $ts->term_id = $tid; $ts->name = $mi['title'] ?? '';
                        $ts->slug = $mi['slug'] ?? ''; $ts->term_group = 0;
                        $ts->term_taxonomy_id = $tid; $ts->taxonomy = $mi['object'];
                        $ts->description = ''; $ts->parent = (int)($mi['post_parent']??0);
                        $ts->count = 0; $ts->filter = 'raw';
                        $wpt = new \WP_Term( $ts );
                        wp_cache_set( $tid, $wpt, 'terms' );
                        wp_cache_set( $tid, $wpt, $mi['object'] );
                    }
                }
            }

            // Gerçek product post'larını bulk yükle — Polylang pll_get_post() 0q yapar
            if ( ! empty($post_ids_to_preload) ) {
                $loaded = get_posts(['post__in' => array_unique($post_ids_to_preload), 'post_type' => 'any', 'posts_per_page' => -1, 'post_status' => 'any', 'suppress_filters' => true]);
                // get_posts WP post cache'e yazıyor
            }

            $cache[ $cache_key ] = $items;
            add_filter( 'wp_get_nav_menu_items', 'bric_create_custom_menu', 10, 3 );
            return $items;
        }

        // ── Cache miss — taxonomy/post sorgularını yap ────────────────────────
        \SaltProfiler::mark('menu_pop:' . $menu_obj->slug . ':start');

        $dynamic_config    = $dynamic_menus[ $menu_obj_slug_key ];
        $menu_order        = count( $items );
        $items_before_push = count( custom_menu_items::get_instance()->menu_items );

        foreach ( $items as $key => $item ) {
            $item_object = $item->object;

            if ( $item->object === 'page' && ! empty( $item->object_id ) ) {
                if ( function_exists( 'wc_get_page_id' ) && (int) $item->object_id === wc_get_page_id( 'shop' ) ) {
                    $item_object = 'product';
                } elseif ( (int) $item->object_id === (int) get_option( 'page_for_posts' ) ) {
                    $item_object = 'post';
                }
            }

            foreach ( $dynamic_config as $config ) {
                $pt = $config['post_type']['post_type'] ?? '';
                if ( empty( $pt ) || $item_object !== $pt ) continue;

                if ( ! empty( $config['post_type']['replace'] ) ) {
                    unset( $items[ $key ] );
                }

                if ( ! empty( $config['taxonomy']['taxonomy'] ) ) {
                    $all_terms_args = [
                        'taxonomy'   => $config['taxonomy']['taxonomy'],
                        'hide_empty' => ! empty( $config['taxonomy']['hide_empty'] ),
                        'number'     => $config['taxonomy']['number'] ?? 0,
                        'orderby'    => $config['taxonomy']['orderby'] ?? 'name',
                        'order'      => $config['taxonomy']['order'] ?? 'ASC',
                    ];
                    $all_terms_flat = class_exists( 'QueryCache' )
                        ? QueryCache::get_timber_terms( $all_terms_args )
                        : Timber::get_terms( $all_terms_args );

                    if ( $all_terms_flat ) {
                        $terms_by_parent = [];
                        foreach ( $all_terms_flat as $t ) {
                            $terms_by_parent[ $t->parent ][] = $t;
                        }
                        $menu_order = bric_menu_build_tree(
                            $terms_by_parent[0] ?? [], $terms_by_parent, $menu_obj, $item,
                            $menu_order, $config, (int) $item->db_id
                        );
                    }
                } elseif ( ( $config['post_type']['posts_per_page'] ?? 0 ) != 0 ) {
                    $posts = class_exists( 'QueryCache' )
                        ? QueryCache::get_timber_posts( $config['post_type'] )
                        : Timber::get_posts( $config['post_type'] );

                    if ( $posts ) {
                        foreach ( $posts as $post ) {
                            $menu_order++;
                            custom_menu_items::add_object(
                                $menu_obj->slug, $post->ID, 'post',
                                $menu_order, (int) $item->db_id,
                                $post->ID, '', '', $post->title
                            );
                        }
                    }
                }
            }
        }

        // Bu run'da eklenen item'ları transient'a yaz (serileştirilebilir plain array)
        $all_items    = custom_menu_items::get_instance()->menu_items;
        $new_items    = array_slice( $all_items, $items_before_push );
        if ( ! empty( $new_items ) ) {
            set_transient( $transient_key, $new_items, DAY_IN_SECONDS );
        }

        add_filter( 'wp_get_nav_menu_items', 'bric_create_custom_menu', 10, 3 );
        \SaltProfiler::mark('menu_pop:' . $menu_obj->slug . ':end');
        $cache[ $cache_key ] = $items;
        return $items;
    }

    function bric_menu_build_tree( array $terms, array $terms_by_parent, $menu, $item, int $menu_order, array $config, int $parent_db_id ): int {
        foreach ( $terms as $term ) {
            $menu_order++;
            $term->db_id = 1000000 + $menu_order;
            $tid = (int) $term->term_id;

            // add_object yerine direkt push — get_term() DB sorgusunu atla (term object elimizde)
            $instance = custom_menu_items::get_instance();
            $instance->menus[ $menu->slug ] = $menu->slug;

            // 'terms' cache group'unu doldur — Timber MenuItem::master_object() → get_term($id)
            // taxonomy parametresiz çağrı 'terms' group'a bakıyor, boşsa DB'ye gidiyor.
            $wp_term = null;
            if ( isset( $term->wp_object ) && $term->wp_object instanceof \WP_Term ) {
                $wp_term = $term->wp_object;
            }
            if ( ! wp_cache_get( $tid, 'terms' ) ) {
                if ( $wp_term ) {
                    wp_cache_set( $tid, $wp_term, 'terms' );
                } else {
                    $stub                   = new \stdClass();
                    $stub->term_id          = $tid;
                    $stub->name             = $term->name;
                    $stub->slug             = $term->slug;
                    $stub->term_group       = 0;
                    $stub->term_taxonomy_id = $tid;
                    $stub->taxonomy         = $term->taxonomy;
                    $stub->description      = '';
                    $stub->parent           = $term->parent ?? 0;
                    $stub->count            = 0;
                    $stub->filter           = 'raw';
                    wp_cache_set( $tid, new \WP_Term( $stub ), 'terms' );
                }
            }

            $instance->menu_items[] = [
                'menu'         => $menu->slug,
                'order'        => $menu_order,
                'parent'       => $parent_db_id,
                'post_parent'  => $term->parent ?? 0,
                'title'        => $term->name,
                'url'          => $term->link,
                'ID'           => $term->db_id,
                'type'         => 'taxonomy',
                'object'       => $term->taxonomy,
                'object_id'    => $tid,
                'classes'      => [],
                'slug'         => $term->slug,
            ];

            // Alt term'ler
            $children = $terms_by_parent[ $tid ] ?? [];
            if ( $children ) {
                $menu_order = bric_menu_build_tree( $children, $terms_by_parent, $menu, $item, $menu_order, $config, $term->db_id );
            }
            // show_posts aktifse leaf term'lerin altına postları ekle
            $show_posts = ! empty( $config['taxonomy']['show_posts'] );
            if ( $show_posts && empty( $children ) && ( $config['post_type']['posts_per_page'] ?? 0 ) != 0 ) {
                $post_args              = $config['post_type'];
                $post_args['tax_query'] = [ [
                    'taxonomy'         => $term->taxonomy,
                    'field'            => 'term_id',
                    'terms'            => [ $tid ],
                    'include_children' => false,
                ] ];
                $posts = class_exists( 'QueryCache' )
                    ? QueryCache::get_timber_posts( $post_args )
                    : Timber::get_posts( $post_args );
                if ( $posts ) {
                    foreach ( $posts as $post ) {
                        $menu_order++;
                        $pid = (int) $post->ID;
                        // add_object yerine direkt push — get_post() + get_permalink() DB sorgusunu atla
                        $instance->menu_items[] = [
                            'menu'        => $menu->slug,
                            'order'       => $menu_order,
                            'parent'      => $term->db_id,
                            'post_parent' => $post->post_parent ?? 0,
                            'title'       => $post->title,
                            'url'         => $post->link,
                            'ID'          => $pid,
                            'type'        => 'post_type',
                            'object'      => $post->post_type ?? 'product',
                            'object_id'   => $pid,
                            'classes'     => [],
                            'slug'        => $post->post_name ?? $post->slug ?? '',
                        ];
                        // Post cache stub — get_post(ID) DB'ye gitmesin
                        if ( ! wp_cache_get( $pid, 'posts' ) ) {
                            $wp_post = $post->wp_object ?? $post->core_object ?? null;
                            if ( $wp_post instanceof \WP_Post ) {
                                wp_cache_set( $pid, $wp_post, 'posts' );
                            }
                        }
                    }
                }
            }
        }
        return $menu_order;
    }

    function bric_custom_menu_loop( $menu, $item, $parent, int $menu_order, array $config ): int {
        if ( ! isset( $parent->taxonomy ) ) return $menu_order;

        $term_args = array_merge( $config['taxonomy'], [
            'hide_empty' => ! empty( $config['taxonomy']['hide_empty'] ),
            'parent'     => $parent->term_id,
        ] );
        unset( $term_args['show_posts'] );

        $children = class_exists( 'QueryCache' )
            ? QueryCache::get_timber_terms( $term_args )
            : Timber::get_terms( $term_args );

        if ( $children ) {
            foreach ( $children as $child ) {
                $menu_order++;
                $child->db_id = 1000000 + $menu_order;
                custom_menu_items::add_object(
                    $menu->slug, $child->term_id, 'term',
                    $menu_order, (int) $parent->db_id,
                    $child->db_id, '', '', $child->name, $child->term_id
                );
                $menu_order   = bric_custom_menu_loop( $menu, $item, $child, $menu_order, $config );
            }
        }

        // show_posts aktifse her term'in altina postlarini ekle
        $show_posts = ! empty( $config['taxonomy']['show_posts'] );
        if ( $show_posts && ( $config['post_type']['posts_per_page'] ?? 0 ) != 0 ) {
            $post_args              = $config['post_type'];
            $post_args['tax_query'] = [ [
                'taxonomy' => $parent->taxonomy,
                'field'    => 'term_id',
                'terms'    => [ $parent->term_id ],
                'include_children' => false,
            ] ];

            $posts = class_exists( 'QueryCache' )
                ? QueryCache::get_timber_posts( $post_args )
                : Timber::get_posts( $post_args );

            if ( $posts ) {
                foreach ( $posts as $post ) {
                    $menu_order++;
                    $pid = (int) $post->ID;
                    $instance->menu_items[] = [
                        'menu'        => $menu->slug,
                        'order'       => $menu_order,
                        'parent'      => (int) $parent->db_id,
                        'post_parent' => $post->post_parent ?? 0,
                        'title'       => $post->title,
                        'url'         => $post->link,
                        'ID'          => $pid,
                        'type'        => 'post_type',
                        'object'      => $post->post_type ?? 'product',
                        'object_id'   => $pid,
                        'classes'     => [],
                        'slug'        => $post->post_name ?? $post->slug ?? '',
                    ];
                    if ( ! wp_cache_get( $pid, 'posts' ) ) {
                        $wp_post = $post->wp_object ?? $post->core_object ?? null;
                        if ( $wp_post instanceof \WP_Post ) {
                            wp_cache_set( $pid, $wp_post, 'posts' );
                        }
                    }
                }
            }
        }

        return $menu_order;
    }
}
