<?php

namespace SaltHareket;

/**
 * ProductStatsAdmin
 * WooCommerce admin ürün listesine istatistik kolonları ekler.
 * Kolon başlıklarına tıklayarak sort edebilirsin.
 *
 * @version 1.0.0
 */
class ProductStatsAdmin
{
    public static function register(): void
    {
        add_filter( 'manage_product_posts_columns',         [ self::class, 'addColumns' ] );
        add_action( 'manage_product_posts_custom_column',   [ self::class, 'renderColumn' ], 10, 2 );
        add_filter( 'manage_edit-product_sortable_columns', [ self::class, 'sortableColumns' ] );
        add_action( 'pre_get_posts',                        [ self::class, 'sortQuery' ] );
        add_action( 'admin_head',                           [ self::class, 'adminCss' ] );
    }

    public static function addColumns( array $columns ): array
    {
        $new = [];
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            // Fiyat kolonundan sonra ekle
            if ( $key === 'price' ) {
                $new['sh_views']     = '<span title="' . esc_attr__( 'Views', 'salthareket' ) . '">👁</span>';
                $new['sh_carts']     = '<span title="' . esc_attr__( 'Added to Cart', 'salthareket' ) . '">🛒</span>';
                $new['sh_purchases'] = '<span title="' . esc_attr__( 'Purchases', 'salthareket' ) . '">💳</span>';
                $new['sh_favorites'] = '<span title="' . esc_attr__( 'Favorites', 'salthareket' ) . '">⭐</span>';
                $new['sh_rating']    = '<span title="' . esc_attr__( 'Rating', 'salthareket' ) . '">★</span>';
            }
        }
        return $new;
    }

    public static function renderColumn( string $column, int $post_id ): void
    {
        switch ( $column ) {
            case 'sh_views':
                $val = (int) get_post_meta( $post_id, ProductStats::META_VIEWS, true );
                echo $val > 0 ? '<span class="sh-admin-stat">' . number_format( $val ) . '</span>' : '<span class="sh-admin-stat sh-admin-stat--zero">—</span>';
                break;

            case 'sh_carts':
                $val = (int) get_post_meta( $post_id, ProductStats::META_CARTS, true );
                echo $val > 0 ? '<span class="sh-admin-stat">' . number_format( $val ) . '</span>' : '<span class="sh-admin-stat sh-admin-stat--zero">—</span>';
                break;

            case 'sh_purchases':
                $val = (int) get_post_meta( $post_id, ProductStats::META_PURCHASES, true );
                echo $val > 0 ? '<span class="sh-admin-stat sh-admin-stat--purchases">' . number_format( $val ) . '</span>' : '<span class="sh-admin-stat sh-admin-stat--zero">—</span>';
                break;

            case 'sh_favorites':
                $val = 0;
                if ( class_exists( '\\SaltHareket\\Reactions\\Reactions' ) ) {
                    $val = (int) \SaltHareket\Reactions\Reactions::count( 'favorite', $post_id, 'post' );
                }
                echo $val > 0 ? '<span class="sh-admin-stat sh-admin-stat--fav">' . number_format( $val ) . '</span>' : '<span class="sh-admin-stat sh-admin-stat--zero">—</span>';
                break;

            case 'sh_rating':
                $avg   = (float) get_post_meta( $post_id, '_post_rating',       true );
                $count = (int)   get_post_meta( $post_id, '_post_rating_count', true );
                if ( $count > 0 ) {
                    echo '<span class="sh-admin-stat sh-admin-stat--rating">' . number_format( $avg, 1 ) . ' <small>(' . $count . ')</small></span>';
                } else {
                    echo '<span class="sh-admin-stat sh-admin-stat--zero">—</span>';
                }
                break;
        }
    }

    public static function sortableColumns( array $columns ): array
    {
        $columns['sh_views']     = 'sh_views';
        $columns['sh_carts']     = 'sh_carts';
        $columns['sh_purchases'] = 'sh_purchases';
        $columns['sh_rating']    = 'sh_rating';
        return $columns;
    }

    public static function sortQuery( \WP_Query $query ): void
    {
        if ( ! is_admin() || ! $query->is_main_query() ) return;
        if ( $query->get( 'post_type' ) !== 'product' ) return;

        $orderby = $query->get( 'orderby' );

        $meta_map = [
            'sh_views'     => ProductStats::META_VIEWS,
            'sh_carts'     => ProductStats::META_CARTS,
            'sh_purchases' => ProductStats::META_PURCHASES,
            'sh_rating'    => '_post_rating',
        ];

        if ( isset( $meta_map[ $orderby ] ) ) {
            $query->set( 'meta_key', $meta_map[ $orderby ] );
            $query->set( 'orderby', 'meta_value_num' );
        }
    }

    public static function adminCss(): void
    {
        $screen = get_current_screen();
        if ( ! $screen || $screen->id !== 'edit-product' ) return;
        echo '<style>
        .column-sh_views,.column-sh_carts,.column-sh_purchases,.column-sh_favorites,.column-sh_rating{width:40px;text-align:center;}
        .sh-admin-stat{font-size:12px;font-weight:600;color:#1d2327;}
        .sh-admin-stat--zero{color:#ccc;}
        .sh-admin-stat--purchases{color:#0a8c3e;}
        .sh-admin-stat--fav{color:#d97706;}
        .sh-admin-stat--rating{color:#1d4ed8;}
        .sh-admin-stat small{font-weight:400;color:#6b7280;}
        </style>';
    }
}
