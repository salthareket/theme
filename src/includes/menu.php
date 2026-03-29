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
    $value = QueryCache::get_field( 'menu_populate', 'options' );
    if ( ! $value ) return $arr;

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
            ];
        }

        $menu_item = [];
        if ( ! empty( $post_type['posts_per_page'] ) && $post_type['posts_per_page'] != 0 ) {
            $menu_item['post_type'] = $post_type;
        } else {
            $menu_item['post_type'] = [
                'post_type' => $post_type['post_type'] ?? '',
                'replace'   => $post_type['replace'] ?? false,
            ];
        }
        if ( ! empty( $taxonomy['taxonomy'] ) ) {
            $menu_item['taxonomy'] = $taxonomy;
        }

        $arr[ $menu ][] = $menu_item;
    }

    return $arr;
}


if ( QueryCache::get_option( 'options_menu_populate' ) > 0 ) {

    add_filter( 'wp_get_nav_menu_items', 'bric_create_custom_menu', 10, 3 );

    function bric_create_custom_menu( $items, $menu, $args ) {
        remove_filter( 'wp_get_nav_menu_items', 'bric_create_custom_menu', 10, 3 );

        $dynamic_menus = get_menu_populate();
        $menu_obj      = is_object( $menu ) ? $menu : wp_get_nav_menu_object( $menu );

        if ( ! $menu_obj || ! isset( $dynamic_menus[ $menu_obj->slug ] ) ) {
            add_filter( 'wp_get_nav_menu_items', 'bric_create_custom_menu', 10, 3 );
            return $items;
        }

        $menu_order     = count( $items );
        $dynamic_config = $dynamic_menus[ $menu_obj->slug ];

        foreach ( $items as $key => $item ) {
            foreach ( $dynamic_config as $config ) {
                $pt = $config['post_type']['post_type'] ?? '';
                if ( empty( $pt ) ) continue;

                // object veya object_type eşleşmesi (object_type array olabilir)
                $object_match = ( $item->object === $pt )
                    || ( is_array( $item->object_type ) && in_array( $pt, $item->object_type, true ) )
                    || ( is_string( $item->object_type ) && $item->object_type === $pt );

                if ( ! $object_match ) continue;

                if ( ! empty( $config['post_type']['replace'] ) ) {
                    unset( $items[ $key ] );
                }

                // Taxonomy bazlı doldurma
                if ( ! empty( $config['taxonomy']['taxonomy'] ) ) {
                    $term_args = array_merge( $config['taxonomy'], [
                        'hide_empty' => true,
                        'parent'     => 0,
                    ] );

                    // QueryCache ile cache'li — post/term değişikliğinde otomatik invalidate
                    $terms = class_exists( 'QueryCache' )
                        ? QueryCache::get_timber_terms( $term_args )
                        : Timber::get_terms( $term_args );

                    if ( $terms ) {
                        foreach ( $terms as $term ) {
                            $menu_order++;
                            custom_menu_items::add_object(
                                $menu_obj->name, $term->term_id, 'term',
                                $menu_order, (int) $item->db_id,
                                $term->term_id, '', '', $term->name
                            );
                            $term->db_id = 1000000 + $menu_order;
                            $menu_order  = bric_custom_menu_loop( $menu_obj, $item, $term, $menu_order, $config );
                        }
                    }
                }
                // Post type bazlı doldurma
                elseif ( ( $config['post_type']['posts_per_page'] ?? 0 ) != 0 ) {
                    $posts = class_exists( 'QueryCache' )
                        ? QueryCache::get_timber_posts( $config['post_type'] )
                        : Timber::get_posts( $config['post_type'] );

                    if ( $posts ) {
                        foreach ( $posts as $post ) {
                            $menu_order++;
                            custom_menu_items::add_object(
                                $menu_obj->name, $post->ID, 'post',
                                $menu_order, (int) $item->db_id,
                                $post->ID, '', '', $post->title
                            );
                        }
                    }
                }
            }
        }

        add_filter( 'wp_get_nav_menu_items', 'bric_create_custom_menu', 10, 3 );
        return $items;
    }

    function bric_custom_menu_loop( $menu, $item, $parent, int $menu_order, array $config ): int {
        if ( ! isset( $parent->taxonomy ) ) return $menu_order;

        $term_args = array_merge( $config['taxonomy'], [
            'hide_empty' => false,
            'parent'     => $parent->term_id,
        ] );

        $children = class_exists( 'QueryCache' )
            ? QueryCache::get_timber_terms( $term_args )
            : Timber::get_terms( $term_args );

        if ( $children ) {
            foreach ( $children as $child ) {
                $menu_order++;
                custom_menu_items::add_object(
                    $menu->name, $child->term_id, 'term',
                    $menu_order, (int) $parent->db_id,
                    $child->term_id, '', '', $child->name
                );
                $child->db_id = 1000000 + $menu_order;
                $menu_order   = bric_custom_menu_loop( $menu, $item, $child, $menu_order, $config );
            }
        } elseif ( ( $config['post_type']['posts_per_page'] ?? 0 ) != 0 ) {
            $post_args              = $config['post_type'];
            $post_args['tax_query'] = [ [
                'taxonomy' => $parent->taxonomy,
                'field'    => 'term_id',
                'terms'    => [ $parent->term_id ],
            ] ];

            $posts = class_exists( 'QueryCache' )
                ? QueryCache::get_timber_posts( $post_args )
                : Timber::get_posts( $post_args );

            if ( $posts ) {
                foreach ( $posts as $post ) {
                    $menu_order++;
                    custom_menu_items::add_object(
                        $menu->name, $post->ID, 'post',
                        $menu_order, (int) $parent->db_id,
                        $post->ID, '', '', $post->title
                    );
                }
            }
        }

        return $menu_order;
    }
}
