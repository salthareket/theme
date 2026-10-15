<?php
/**
 * Admin Product List — ProductStats kolonları (views, carts, purchases, favorites, rating).
 */

add_filter( 'manage_product_posts_columns', function ( $columns ) {
    $columns['sh_views']     = '<span title="Views">👁</span>';
    $columns['sh_carts']     = '<span title="Added to Cart">🛒</span>';
    $columns['sh_purchases'] = '<span title="Purchases">💳</span>';
    $columns['sh_favorites'] = '<span title="Favorites">⭐</span>';
    $columns['sh_rating']    = '<span title="Rating">★</span>';
    return $columns;
}, 20 );

add_action( 'manage_product_posts_custom_column', function ( $column, $post_id ) {
    $zero = '<span style="color:#ccc;">—</span>';
    // Polylang: her zaman default dildeki ürünün verisini göster
    $data_id = $post_id;
    if ( function_exists( 'pll_get_post' ) && function_exists( 'pll_default_language' ) ) {
        $default_id = pll_get_post( $post_id, pll_default_language() );
        if ( $default_id ) $data_id = $default_id;
    }
    switch ( $column ) {
        case 'sh_views':
            $v = (int) get_post_meta( $data_id, '_product_view_count', true );
            echo $v > 0 ? '<strong>' . number_format( $v ) . '</strong>' : $zero;
            break;
        case 'sh_carts':
            $v = (int) get_post_meta( $data_id, '_product_cart_count', true );
            echo $v > 0 ? '<strong>' . number_format( $v ) . '</strong>' : $zero;
            break;
        case 'sh_purchases':
            $v = (int) get_post_meta( $data_id, '_product_purchase_count', true );
            echo $v > 0 ? '<strong style="color:#0a8c3e;">' . number_format( $v ) . '</strong>' : $zero;
            break;
        case 'sh_favorites':
            $v = 0;
            if ( class_exists( '\SaltHareket\Reactions\Reactions' ) ) {
                $v = (int) \SaltHareket\Reactions\Reactions::count( 'favorite', $data_id, 'post' );
            }
            echo $v > 0 ? '<strong style="color:#d97706;">' . number_format( $v ) . '</strong>' : $zero;
            break;
        case 'sh_rating':
            $avg   = (float) get_post_meta( $data_id, '_post_rating', true );
            $count = (int)   get_post_meta( $data_id, '_post_rating_count', true );
            echo $count > 0
                ? '<strong style="color:#1d4ed8;">' . number_format( $avg, 1 ) . '</strong> <small>(' . $count . ')</small>'
                : $zero;
            break;
    }
}, 10, 2 );

add_filter( 'manage_edit-product_sortable_columns', function ( $columns ) {
    $columns['sh_views']     = 'sh_views';
    $columns['sh_carts']     = 'sh_carts';
    $columns['sh_purchases'] = 'sh_purchases';
    $columns['sh_rating']    = 'sh_rating';
    return $columns;
} );

add_action( 'pre_get_posts', function ( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) return;
    if ( $query->get( 'post_type' ) !== 'product' ) return;
    $map = [
        'sh_views'     => '_product_view_count',
        'sh_carts'     => '_product_cart_count',
        'sh_purchases' => '_product_purchase_count',
        'sh_rating'    => '_post_rating',
    ];
    $orderby = $query->get( 'orderby' );
    if ( isset( $map[ $orderby ] ) ) {
        $query->set( 'meta_key', $map[ $orderby ] );
        $query->set( 'orderby', 'meta_value_num' );
    }
} );

add_action( 'admin_head', function () {
    $screen = get_current_screen();
    if ( ! $screen || $screen->id !== 'edit-product' ) return;
    echo '<style>.column-sh_views,.column-sh_carts,.column-sh_purchases,.column-sh_favorites,.column-sh_rating{width:36px;text-align:center;}</style>';
} );
