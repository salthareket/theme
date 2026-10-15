<?php

namespace SaltHareket;

/**
 * ProductStats
 * Ürün görüntülenme, sepete ekleme ve satın alma sayaçlarını tutar.
 * Post meta tablosuna yazar, spam koruması için session cookie kullanır.
 *
 * Meta keys:
 *   _product_view_count      — görüntülenme (tekil ziyaretçi bazlı)
 *   _product_cart_count      — toplam sepete ekleme
 *   _product_purchase_count  — tamamlanan sipariş adedi
 *
 * Kullanım:
 *   ProductStats::incrementView($post_id);   // woocommerce.php'de
 *   ProductStats::getStats($post_id);        // ['views'=>12, 'carts'=>4, 'purchases'=>2, 'favorites'=>7]
 *   ProductStats::getStatsBulk([1,2,3]);     // archive'de bulk
 *
 * @version 1.0.0
 */
class ProductStats
{
    const META_VIEWS     = '_product_view_count';
    const META_CARTS     = '_product_cart_count';
    const META_PURCHASES = '_product_purchase_count';
    const COOKIE_PREFIX  = 'sh_pv_';        // sh_pv_{post_id} — spam koruması
    const COOKIE_TTL     = 3600;            // 1 saat aynı ürünü tekrar saymaz

    // =========================================================================
    // INCREMENT
    // =========================================================================

    /**
     * Görüntülenme sayacını artır — session cookie ile tekrar sayma koruması.
     * woocommerce.php'de single product render sırasında çağrılır.
     */
    public static function incrementView( int $post_id ): void
    {
        if ( $post_id < 1 ) return;

        // Variation ise parent'ı say
        $target_id = self::resolveParentId( $post_id );

        $cookie_key = self::COOKIE_PREFIX . $target_id;

        // Bu saat içinde zaten sayıldı mı?
        if ( ! empty( $_COOKIE[ $cookie_key ] ) ) return;

        // Sayacı artır
        $current = (int) get_post_meta( $target_id, self::META_VIEWS, true );
        update_post_meta( $target_id, self::META_VIEWS, $current + 1 );

        // Cookie set — headers_sent kontrolü
        if ( ! headers_sent() ) {
            setcookie(
                $cookie_key,
                '1',
                time() + self::COOKIE_TTL,
                COOKIEPATH,
                COOKIE_DOMAIN,
                is_ssl(),
                true
            );
        }
    }

    /**
     * Sepete ekleme sayacını artır.
     * woocommerce_add_to_cart hook'unda çağrılır.
     */
    public static function incrementCart( int $post_id ): void
    {
        if ( $post_id < 1 ) return;
        $target_id = self::resolveParentId( $post_id );
        $current   = (int) get_post_meta( $target_id, self::META_CARTS, true );
        update_post_meta( $target_id, self::META_CARTS, $current + 1 );
    }

    /**
     * Sepetten çıkarma sayacını azalt.
     * woocommerce_cart_item_removed + woocommerce_cart_emptied hook'larında çağrılır.
     * Negatife düşmez — 0'ın altına inmez.
     */
    public static function decrementCart( int $post_id ): void
    {
        if ( $post_id < 1 ) return;
        $target_id = self::resolveParentId( $post_id );
        $current   = (int) get_post_meta( $target_id, self::META_CARTS, true );
        if ( $current > 0 ) {
            update_post_meta( $target_id, self::META_CARTS, $current - 1 );
        }
    }

    /**
     * Satın alma sayacını artır.
     * woocommerce_order_item_quantity_changed veya order_status_completed hook'unda çağrılır.
     */
    public static function incrementPurchase( int $post_id, int $quantity = 1 ): void
    {
        if ( $post_id < 1 ) return;
        $target_id = self::resolveParentId( $post_id );
        $current   = (int) get_post_meta( $target_id, self::META_PURCHASES, true );
        update_post_meta( $target_id, self::META_PURCHASES, $current + $quantity );
    }

    // =========================================================================
    // READ
    // =========================================================================

    /**
     * Tek ürün için tüm istatistikleri döndür.
     * Reactions'dan favorite count da dahil edilir.
     *
     * @return array{views:int, carts:int, purchases:int, favorites:int, reactions:array}
     */
    public static function getStats( int $post_id ): array
    {
        $target_id = self::resolveParentId( $post_id );

        $views     = (int) get_post_meta( $target_id, self::META_VIEWS,     true );
        $carts     = (int) get_post_meta( $target_id, self::META_CARTS,     true );
        $purchases = (int) get_post_meta( $target_id, self::META_PURCHASES, true );

        // Reactions: aktif tiplerin count'larını al
        $reactions = [];
        if ( class_exists( '\\SaltHareket\\Reactions\\Reactions' ) ) {
            $types = class_exists( '\\SaltHareket\\Reactions\\ReactionsSettings' )
                ? array_keys( \SaltHareket\Reactions\ReactionsSettings::getTypes() )
                : [ 'like', 'favorite' ];

            foreach ( $types as $type ) {
                $count = \SaltHareket\Reactions\Reactions::count( $type, $target_id, 'post' );
                if ( $count > 0 ) {
                    $reactions[ $type ] = $count;
                }
            }
        }

        return [
            'views'     => $views,
            'carts'     => $carts,
            'purchases' => $purchases,
            'favorites' => $reactions['favorite'] ?? 0,
            'likes'     => $reactions['like']     ?? 0,
            'reactions' => $reactions,
        ];
    }

    /**
     * Birden fazla ürün için bulk stats (archive sayfaları için).
     * @return array<int, array>  [post_id => stats]
     */
    public static function getStatsBulk( array $post_ids ): array
    {
        $result = [];
        foreach ( $post_ids as $id ) {
            $result[ $id ] = self::getStats( (int) $id );
        }
        return $result;
    }

    // =========================================================================
    // PRIVATE
    // =========================================================================

    /**
     * Variation ise parent ID'sini döndür.
     * Polylang aktifse varsayılan dildeki karşılığını döndür.
     */
    private static function resolveParentId( int $post_id ): int
    {
        if ( ! function_exists( 'wc_get_product' ) ) return $post_id;

        // Önce WC variation → parent
        $product = wc_get_product( $post_id );
        $id      = $post_id;
        if ( $product && $product->is_type( 'variation' ) ) {
            $id = (int) $product->get_parent_id();
        }

        // Sonra Polylang → default dil
        if ( function_exists( 'pll_get_post' ) && function_exists( 'pll_default_language' ) ) {
            $default_id = pll_get_post( $id, pll_default_language() );
            if ( $default_id ) $id = (int) $default_id;
        }

        return $id;
    }
}
