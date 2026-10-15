<?php

namespace SaltHareket\Reviews\Concerns;

/**
 * QueriesReviews
 * Review sorgulama — tekil, liste, paginated.
 *
 * @version 1.0.0
 */
trait QueriesReviews
{
    // =========================================================================
    // TEKİL
    // =========================================================================

    public function get( int $comment_id ): mixed
    {
        $comment = get_comment( $comment_id );
        if ( ! $comment || $comment->comment_type !== 'review' ) return null;
        return $this->wrapComment( $comment );
    }

    public function getReply( int $comment_id ): mixed
    {
        $comment = get_comment( $comment_id );
        if ( ! $comment || $comment->comment_type !== 'review_reply' ) return null;
        return $this->wrapComment( $comment );
    }

    // =========================================================================
    // LİSTELER
    // =========================================================================

    /**
     * Post'a yazılan review'lar.
     * @return array{reviews: array, data: array}
     */
    public function getForPost( int $post_id, array $args = [] ): array
    {
        $q            = $this->buildQuery( $args );
        $q['post_id'] = $post_id;
        return $this->runQuery( $q );
    }

    /**
     * Kullanıcıya yazılan review'lar.
     * @return array{reviews: array, data: array}
     */
    public function getForUser( int $user_id, array $args = [] ): array
    {
        global $wpdb;

        $status   = ( ( $args['status'] ?? 'approve' ) === 'hold' ) ? '0' : '1';
        $per_page = max( 1, (int) ( $args['per_page'] ?? 10 ) );
        $page     = max( 1, (int) ( $args['page'] ?? 1 ) );
        $offset   = ( $page - 1 ) * $per_page;

        // Direkt SQL — WP_Comment_Query comment_post_ID=0 olan yorumları atlıyor
        $ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT c.comment_ID
             FROM {$wpdb->comments} c
             JOIN {$wpdb->commentmeta} m ON c.comment_ID = m.comment_id
             WHERE c.comment_type = 'review'
               AND c.comment_approved = %s
               AND c.comment_parent = 0
               AND m.meta_key = 'comment_profile'
               AND m.meta_value = %d
             ORDER BY c.comment_date_gmt DESC",
            $status,
            $user_id
        ) );

        $total = count( $ids );
        if ( $total === 0 ) {
            return [ 'reviews' => [], 'data' => [ 'count' => 0, 'total' => 0, 'page' => $page, 'page_total' => 1, 'per_page' => $per_page ] ];
        }

        $paged_ids = array_slice( $ids, $offset, $per_page );
        $q = $this->buildQuery( $args );
        $q['comment__in'] = array_map( 'intval', $paged_ids );
        $q['orderby']     = 'comment__in';
        unset( $q['status'] ); // zaten ID ile filtreledik

        $result = $this->runQuery( $q );
        $result['data']['count'] = $total;
        $result['data']['total'] = $total;
        $result['data']['page_total'] = max( 1, (int) ceil( $total / $per_page ) );
        return $result;
    }

    /**
     * Kullanıcının author olduğu post'lara yapılan review'lar.
     * (getReceived'ın sadece post kısmı — profil review'ları hariç)
     */
    public function getForPosts( int $user_id, array $args = [] ): array
    {
        global $wpdb;

        $status   = ( ( $args['status'] ?? 'approve' ) === 'hold' ) ? '0' : '1';
        $per_page = max( 1, (int) ( $args['per_page'] ?? 10 ) );
        $page     = max( 1, (int) ( $args['page'] ?? 1 ) );
        $offset   = ( $page - 1 ) * $per_page;

        $ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT c.comment_ID
             FROM {$wpdb->comments} c
             JOIN {$wpdb->posts} p ON c.comment_post_ID = p.ID
             WHERE c.comment_type = 'review'
               AND c.comment_approved = %s
               AND c.comment_parent = 0
               AND p.post_author = %d
               AND p.post_status = 'publish'
             ORDER BY c.comment_date_gmt DESC",
            $status,
            $user_id
        ) );

        $total = count( $ids );
        if ( $total === 0 ) {
            return [ 'reviews' => [], 'data' => [ 'count' => 0, 'total' => 0, 'page' => $page, 'page_total' => 1, 'per_page' => $per_page ] ];
        }

        $paged_ids = array_slice( $ids, $offset, $per_page );

        if ( empty( $paged_ids ) ) {
            return [ 'reviews' => [], 'data' => [ 'count' => $total, 'total' => $total, 'page' => $page, 'page_total' => max(1,(int)ceil($total/$per_page)), 'per_page' => $per_page ] ];
        }

        // Direkt SQL ile fetch — WP_Comment_Query comment_post_ID=0 bypass
        $placeholders = implode( ',', array_fill( 0, count( $paged_ids ), '%d' ) );
        $comment_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_ID IN ({$placeholders})",
            $paged_ids
        ) );

        // ID sırasını koru
        $comments = [];
        foreach ( $paged_ids as $cid ) {
            $comment = get_comment( (int) $cid );
            if ( $comment ) $comments[] = $comment;
        }

        $reviews = array_map( function( $c ) { return $this->wrapComment( $c ); }, $comments );

        $result = [
            'reviews' => $reviews,
            'data'    => [
                'count'      => $total,
                'total'      => $total,
                'page'       => $page,
                'page_total' => max( 1, (int) ceil( $total / $per_page ) ),
                'per_page'   => $per_page,
            ],
        ];
        return $result;
    }

    /**
     * Kullanıcının yazdığı review'lar.
     * @return array{reviews: array, data: array}
     */
    public function getByAuthor( int $user_id, array $args = [] ): array
    {
        $q            = $this->buildQuery( $args );
        $q['user_id'] = $user_id;
        return $this->runQuery( $q );
    }

    /**
     * Kullanıcıya YAPILAN review'lar (received).
     *
     * Kapsam — badge (getUnreadCount) ile birebir aynı küme:
     *   - Kullanıcının sahibi olduğu (post_author) publish post'lara yazılan review'lar
     *   - Kullanıcının profiline yazılan review'lar (comment_profile meta)
     *
     * Not: reply'lar (comment_type='review_reply') parent=0 filtresiyle liste dışıdır;
     * reply'a ihtiyaç olursa item bazında getReplies() ile çekilir.
     *
     * @param int   $user_id  İçerik/profil sahibi kullanıcı ID
     * @param array $args     buildQuery args (page, per_page, status, order, rating, ...)
     * @return array{reviews: array, data: array}
     */
    public function getReceived( int $user_id, array $args = [] ): array
    {
        global $wpdb;

        // Bu kullanıcıya YAPILMIŞ review comment_ID'lerini topla (post sahibi OR profile).
        $status = ( ( $args['status'] ?? 'approve' ) === 'hold' ) ? '0' : '1';
        $ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT c.comment_ID
             FROM {$wpdb->comments} c
             WHERE c.comment_type = 'review'
               AND c.comment_approved = %s
               AND c.comment_parent = 0
               AND (
                   c.comment_post_ID IN (
                       SELECT ID FROM {$wpdb->posts}
                       WHERE post_author = %d AND post_status = 'publish'
                   )
                   OR
                   c.comment_ID IN (
                       SELECT comment_id FROM {$wpdb->commentmeta}
                       WHERE meta_key = 'comment_profile' AND meta_value = %d
                   )
               )",
            $status,
            $user_id,
            $user_id
        ) );

        $ids = array_map( 'intval', (array) $ids );

        // Hiç yoksa boş sonuç — buildQuery/runQuery'yi boşa çalıştırma.
        if ( empty( $ids ) ) {
            $per_page = max( 1, (int) ( $args['per_page'] ?? 10 ) );
            return [
                'reviews' => [],
                'data'    => [ 'total' => 0, 'page' => 1, 'page_total' => 1, 'per_page' => $per_page ],
            ];
        }

        $q               = $this->buildQuery( $args );
        $q['comment__in'] = $ids;
        // comment__in verildiğinde post_id/user_id kısıtı kalksın — küme zaten $ids.
        unset( $q['post_id'], $q['user_id'] );

        return $this->runQuery( $q );
    }

    /**
     * Onay bekleyen review'lar (admin için).
     * @return array{reviews: array, data: array}
     */
    public function getPending( array $args = [] ): array
    {
        $args['status'] = 'hold';
        return $this->runQuery( $this->buildQuery( $args ) );
    }

    /**
     * Bir review'ın reply'larını getir.
     */
    public function getReplies( int $comment_id ): array
    {
        $comments = get_comments( [
            'parent'  => $comment_id,
            'type'    => 'review_reply',
            'status'  => 'approve',
            'orderby' => 'comment_date_gmt',
            'order'   => 'ASC',
        ] );

        return array_map( fn( $c ) => $this->wrapComment( $c ), $comments );
    }

    // =========================================================================
    // OKUNMAMIŞ REVIEW'LAR
    // =========================================================================

    /**
     * Kullanicinin icerikllerine yazilan okunmamis review sayisi.
     * 
     * Review olusturulunca commentmeta'ya _sh_review_read = 0 yazilir (ManagesReviews::create()).
     * Kullanici reviews sayfasini goruntuluyunca markReviewsAsRead() cagirilarak 1 yapilir.
     * Badge bu sayiyi gosterir — 0 ise d-none.
     *
     * @param int $user_id  Icerik sahibi kullanici ID
     * @return int
     */
    public static function getUnreadCount( int $user_id = 0 ): int {
        $user_id = $user_id ?: (int) get_current_user_id();
        if ( $user_id < 1 ) return 0;

        $cache_key = "sh_reviews_unread_{$user_id}";
        $cached    = wp_cache_get( $cache_key, 'reviews' );
        if ( $cached !== false ) return (int) $cached;

        global $wpdb;

        // Bu kullanicinin post'larina veya profile'ina yazilmis, onaylanmis, okunmamis review'lar
        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(c.comment_ID)
             FROM {$wpdb->comments} c
             LEFT JOIN {$wpdb->commentmeta} m
                ON m.comment_id = c.comment_ID AND m.meta_key = '_sh_review_read'
             WHERE c.comment_type = 'review'
               AND c.comment_approved = '1'
               AND c.comment_parent = 0
               AND (
                   -- Post sahibi bu kullanici
                   c.comment_post_ID IN (
                       SELECT ID FROM {$wpdb->posts}
                       WHERE post_author = %d AND post_status = 'publish'
                   )
                   OR
                   -- Profile review (comment_profile meta)
                   c.comment_ID IN (
                       SELECT comment_id FROM {$wpdb->commentmeta}
                       WHERE meta_key = 'comment_profile' AND meta_value = %d
                   )
               )
               AND ( m.meta_value IS NULL OR m.meta_value = '0' )",
            $user_id,
            $user_id
        ) );

        wp_cache_set( $cache_key, $count, 'reviews', 300 );
        return $count;
    }

    /**
     * Kullanicinin icerikllerine yazilmis review'lari okunmus olarak isaretle.
     * Reviews sayfasi goruntulendikce cagirilir.
     *
     * @param int $user_id
     */
    public static function markReviewsAsRead( int $user_id = 0 ): void {
        $user_id = $user_id ?: (int) get_current_user_id();
        if ( $user_id < 1 ) return;

        global $wpdb;

        // Bu kullanicinin post'larina yazilmis okunmamis review'larin comment_ID'lerini al
        $comment_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT c.comment_ID
             FROM {$wpdb->comments} c
             LEFT JOIN {$wpdb->commentmeta} m
                ON m.comment_id = c.comment_ID AND m.meta_key = '_sh_review_read'
             WHERE c.comment_type = 'review'
               AND c.comment_approved = '1'
               AND c.comment_parent = 0
               AND (
                   c.comment_post_ID IN (
                       SELECT ID FROM {$wpdb->posts}
                       WHERE post_author = %d AND post_status = 'publish'
                   )
                   OR
                   c.comment_ID IN (
                       SELECT comment_id FROM {$wpdb->commentmeta}
                       WHERE meta_key = 'comment_profile' AND meta_value = %d
                   )
               )
               AND ( m.meta_value IS NULL OR m.meta_value = '0' )",
            $user_id,
            $user_id
        ) );

        foreach ( $comment_ids as $id ) {
            update_comment_meta( (int) $id, '_sh_review_read', 1 );
        }

        // Cache temizle
        wp_cache_delete( "sh_reviews_unread_{$user_id}", 'reviews' );
    }

    // =========================================================================
    // GERIYE UYUMLU (eski API)
    // =========================================================================

    /** @deprecated Kullan: getForPost() */
    public function get_for_post( int $post_id, array $args = [] ): array
    {
        return $this->getForPost( $post_id, $args );
    }

    /** @deprecated Kullan: getForUser() */
    public function get_for_user( int $user_id, array $args = [] ): array
    {
        return $this->getForUser( $user_id, $args );
    }

    /** @deprecated Kullan: getByAuthor() */
    public function get_by_author( int $user_id, array $args = [] ): array
    {
        return $this->getByAuthor( $user_id, $args );
    }

    // =========================================================================
    // PRIVATE
    // =========================================================================

    private function buildQuery( array $args ): array
    {
        $defaults = [
            'page'     => 1,
            'per_page' => 10,
            'orderby'  => 'comment_date_gmt',
            'order'    => 'DESC',
            'status'   => 'approve',
            'rating'   => null,
            'verified' => null,
            'sort'     => null, // 'helpful' | 'recent' | 'rating_high' | 'rating_low'
        ];
        $args = array_merge( $defaults, $args );

        $per_page = max( 1, (int) $args['per_page'] );
        $page     = max( 1, (int) $args['page'] );

        $q = [
            'type'          => 'review',
            'parent'        => 0,          // review_reply'ları hariç tut (onların parent'ı var)
            'status'        => $args['status'],
            'number'        => $per_page,
            'offset'        => ( $page - 1 ) * $per_page,
            'orderby'       => 'comment_date_gmt',
            'order'         => 'DESC',
            'meta_query'    => [],
            'no_found_rows' => false,
        ];

        // Rating filtresi
        if ( $args['rating'] !== null ) {
            $q['meta_query'][] = [
                'key'     => 'rating',
                'value'   => array_map( 'intval', (array) $args['rating'] ),
                'compare' => 'IN',
                'type'    => 'NUMERIC',
            ];
        }

        // Verified filtresi
        if ( $args['verified'] !== null ) {
            $q['meta_query'][] = [
                'key'   => 'verified',
                'value' => $args['verified'] ? 1 : 0,
                'type'  => 'NUMERIC',
            ];
        }

        // Sıralama
        switch ( $args['sort'] ) {
            case 'helpful':
                $q['meta_key'] = 'helpful_score';
                $q['orderby']  = 'meta_value_num';
                $q['order']    = 'DESC';
                break;
            case 'rating_high':
                $q['meta_key'] = 'rating';
                $q['orderby']  = 'meta_value_num';
                $q['order']    = 'DESC';
                break;
            case 'rating_low':
                $q['meta_key'] = 'rating';
                $q['orderby']  = 'meta_value_num';
                $q['order']    = 'ASC';
                break;
            case 'recent':
            default:
                $q['orderby'] = sanitize_key( $args['orderby'] );
                $q['order']   = in_array( strtoupper( $args['order'] ), [ 'ASC', 'DESC' ], true )
                                ? strtoupper( $args['order'] ) : 'DESC';
                break;
        }

        return $q;
    }

    /**
     * @return array{reviews: array, data: array}
     */
    private function runQuery( array $q ): array
    {
        // Count
        $count_q          = $q;
        $count_q['count'] = true;
        unset( $count_q['number'], $count_q['offset'] );
        $total = (int) ( new \WP_Comment_Query( $count_q ) )->get_comments();

        // Fetch
        $comments = ( new \WP_Comment_Query( $q ) )->comments ?? [];
        $reviews  = array_map( fn( $c ) => $this->wrapComment( $c ), $comments );

        $per_page   = max( 1, (int) ( $q['number'] ?? 10 ) );
        $page       = (int) floor( ( $q['offset'] ?? 0 ) / $per_page ) + 1;
        $page_total = (int) ceil( $total / $per_page );

        return [
            'reviews' => $reviews,
            'data'    => [
                'total'      => $total,
                'page'       => $page,
                'page_total' => max( 1, $page_total ),
                'per_page'   => $per_page,
            ],
        ];
    }

    private function wrapComment( \WP_Comment $comment ): mixed
    {
        if ( class_exists( 'Review' ) ) {
            try { return new \Review( $comment ); } catch ( \Throwable $e ) {}
        }

        // Timber v2'de Comment::__construct() protected — factory ile dene
        if ( class_exists( '\\Timber\\Timber' ) && method_exists( '\\Timber\\Timber', 'get_comment' ) ) {
            try {
                $wrapped = \Timber\Timber::get_comment( $comment->comment_ID );
                if ( $wrapped ) return $wrapped;
            } catch ( \Throwable $e ) {}
        }

        // Fallback: SaltComment wrapper
        return new \SaltHareket\Reviews\SaltComment( $comment );
    }
}
