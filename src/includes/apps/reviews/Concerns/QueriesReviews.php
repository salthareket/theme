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
        $q               = $this->buildQuery( $args );
        $q['meta_query'][] = [
            'key'   => 'comment_profile',
            'value' => $user_id,
            'type'  => 'NUMERIC',
        ];
        return $this->runQuery( $q );
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
