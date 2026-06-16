<?php

namespace SaltHareket\Reviews\Concerns;

/**
 * CalculatesRating
 * Rating hesaplama, breakdown, stats, cache yönetimi.
 *
 * @version 1.0.0
 */
trait CalculatesRating
{
    // =========================================================================
    // PUBLIC — Static API
    // =========================================================================

    /**
     * Toplam ve ortalama rating.
     * @return array{total: int, average: float}
     */
    public static function rating( int $target_id, string $type = 'post' ): array
    {
        $cache_key = "review_rating_{$type}_{$target_id}";

        if ( class_exists( 'QueryCache' ) ) {
            return \QueryCache::wrap( $cache_key, static fn() => self::queryRating( $target_id, $type ) );
        }

        return self::queryRating( $target_id, $type );
    }

    /**
     * Yıldız bazında dağılım.
     * @return array<int, int>  [5 => 20, 4 => 12, 3 => 5, 2 => 3, 1 => 2]
     */
    public static function ratingBreakdown( int $target_id, string $type = 'post' ): array
    {
        global $wpdb;
        $breakdown = [ 5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0 ];

        if ( $type === 'user' ) {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT CAST(rm.meta_value AS UNSIGNED) as star, COUNT(*) as cnt
                 FROM {$wpdb->comments} c
                 INNER JOIN {$wpdb->commentmeta} pm ON pm.comment_id = c.comment_ID
                     AND pm.meta_key = 'comment_profile' AND pm.meta_value = %d
                 INNER JOIN {$wpdb->commentmeta} rm ON rm.comment_id = c.comment_ID
                     AND rm.meta_key = 'rating'
                 WHERE c.comment_type = 'review' AND c.comment_approved = 1
                 GROUP BY star",
                $target_id
            ) );
        } else {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT CAST(rm.meta_value AS UNSIGNED) as star, COUNT(*) as cnt
                 FROM {$wpdb->comments} c
                 INNER JOIN {$wpdb->commentmeta} rm ON rm.comment_id = c.comment_ID
                     AND rm.meta_key = 'rating'
                 WHERE c.comment_post_ID = %d AND c.comment_type = 'review' AND c.comment_approved = 1
                 GROUP BY star",
                $target_id
            ) );
        }

        foreach ( $rows as $row ) {
            $s = (int) $row->star;
            if ( $s >= 1 && $s <= 5 ) $breakdown[ $s ] = (int) $row->cnt;
        }

        return $breakdown;
    }

    /**
     * Tam istatistik — rating + breakdown + percentage.
     * @return array{total: int, average: float, breakdown: array, percentage: array}
     */
    public static function stats( int $target_id, string $type = 'post' ): array
    {
        $rating    = self::rating( $target_id, $type );
        $breakdown = self::ratingBreakdown( $target_id, $type );
        $total     = $rating['total'];

        $percentage = [];
        foreach ( $breakdown as $star => $count ) {
            $percentage[ $star ] = $total > 0 ? round( ( $count / $total ) * 100 ) : 0;
        }

        return [
            'total'      => $total,
            'average'    => $rating['average'],
            'breakdown'  => $breakdown,
            'percentage' => $percentage,
        ];
    }

    /**
     * Yıldız HTML/emoji çıktısı.
     */
    public static function stars( int $rating, string $format = 'text' ): string
    {
        $rating = max( 1, min( 5, $rating ) );
        return match ( $format ) {
            'emoji' => str_repeat( '⭐', $rating ),
            'html'  => '<span class="sh-stars" data-rating="' . $rating . '">'
                       . str_repeat( '<i class="sh-star sh-star-full">★</i>', $rating )
                       . str_repeat( '<i class="sh-star sh-star-empty">☆</i>', 5 - $rating )
                       . '</span>',
            default => str_repeat( '★', $rating ) . str_repeat( '☆', 5 - $rating ),
        };
    }

    // =========================================================================
    // GERIYE UYUMLU
    // =========================================================================

    /** @deprecated Kullan: ratingBreakdown() */
    public static function rating_breakdown( int $target_id, string $type = 'post' ): array
    {
        return self::ratingBreakdown( $target_id, $type );
    }

    // =========================================================================
    // PRIVATE
    // =========================================================================

    private static function queryRating( int $target_id, string $type ): array
    {
        global $wpdb;

        if ( $type === 'user' ) {
            $row = $wpdb->get_row( $wpdb->prepare(
                "SELECT COUNT(*) as total, CAST(AVG(rm.meta_value) AS DECIMAL(10,1)) as average
                 FROM {$wpdb->comments} c
                 INNER JOIN {$wpdb->commentmeta} pm ON pm.comment_id = c.comment_ID
                     AND pm.meta_key = 'comment_profile' AND pm.meta_value = %d
                 INNER JOIN {$wpdb->commentmeta} rm ON rm.comment_id = c.comment_ID
                     AND rm.meta_key = 'rating'
                 WHERE c.comment_type = 'review' AND c.comment_approved = 1",
                $target_id
            ) );
        } else {
            $row = $wpdb->get_row( $wpdb->prepare(
                "SELECT COUNT(*) as total, CAST(AVG(rm.meta_value) AS DECIMAL(10,1)) as average
                 FROM {$wpdb->comments} c
                 INNER JOIN {$wpdb->commentmeta} rm ON rm.comment_id = c.comment_ID
                     AND rm.meta_key = 'rating'
                 WHERE c.comment_post_ID = %d AND c.comment_type = 'review' AND c.comment_approved = 1",
                $target_id
            ) );
        }

        return [
            'total'   => (int) ( $row->total   ?? 0 ),
            'average' => round( (float) ( $row->average ?? 0 ), 1 ),
        ];
    }

    protected function updateRatingCache( int $target_id, string $type ): void
    {
        if ( $target_id < 1 ) return;

        $r = self::queryRating( $target_id, $type );

        if ( $type === 'user' ) {
            update_user_meta( $target_id, '_user_rating',       $r['average'] );
            update_user_meta( $target_id, '_user_rating_count', $r['total'] );
        } else {
            update_post_meta( $target_id, '_post_rating',       $r['average'] );
            update_post_meta( $target_id, '_post_rating_count', $r['total'] );
        }

        if ( class_exists( 'QueryCache' ) ) {
            \QueryCache::forget( "review_rating_{$type}_{$target_id}" );
        }
    }

    protected function sanitizeRating( mixed $rating ): int
    {
        $max = (int) \SaltHareket\Reviews\ReviewsSettings::get( 'rating.max_rating' ) ?: 5;
        return max( 1, min( $max, (int) $rating ) );
    }

    /**
     * Çok boyutlu rating kaydet — weighted average ile genel puan hesapla.
     * Örn: ['quality' => 5, 'price' => 3, 'service' => 4]
     */
    public static function saveMultiRating( int $comment_id, array $criteria_ratings, string $post_type = '' ): void
    {
        $criteria = \SaltHareket\Reviews\ReviewsSettings::getCriteria( $post_type );
        $saved    = [];

        foreach ( $criteria_ratings as $key => $value ) {
            $key = sanitize_key( $key );
            if ( ! $key ) continue;
            $saved[ $key ] = max( 1, min( 5, (int) $value ) );
        }

        if ( ! empty( $saved ) ) {
            update_comment_meta( $comment_id, 'criteria_ratings', $saved );

            // Weighted average → genel rating
            $weighted_sum = 0.0;
            $weight_total = 0.0;
            $criteria_map = array_column( $criteria, null, 'key' );

            foreach ( $saved as $key => $value ) {
                $weight        = (float) ( $criteria_map[ $key ]['weight'] ?? 1.0 );
                $weighted_sum += $value * $weight;
                $weight_total += $weight;
            }

            $avg = $weight_total > 0 ? round( $weighted_sum / $weight_total ) : 0;
            update_comment_meta( $comment_id, 'rating', max( 1, min( 5, (int) $avg ) ) );
        }
    }

    /**
     * Çok boyutlu rating istatistikleri.
     * @return array<string, array{average: float, total: int}>
     */
    public static function criteriaStats( int $target_id, string $type = 'post' ): array
    {
        global $wpdb;
        $criteria = \SaltHareket\Reviews\ReviewsSettings::get( 'rating.criteria' ) ?: [];
        if ( empty( $criteria ) ) return [];

        $stats = [];
        foreach ( $criteria as $criterion ) {
            $key = $criterion['key'] ?? '';
            if ( ! $key ) continue;

            if ( $type === 'user' ) {
                $row = $wpdb->get_row( $wpdb->prepare(
                    "SELECT COUNT(*) as total, AVG(CAST(JSON_EXTRACT(cm.meta_value, %s) AS DECIMAL(10,1))) as average
                     FROM {$wpdb->comments} c
                     INNER JOIN {$wpdb->commentmeta} pm ON pm.comment_id = c.comment_ID
                         AND pm.meta_key = 'comment_profile' AND pm.meta_value = %d
                     INNER JOIN {$wpdb->commentmeta} cm ON cm.comment_id = c.comment_ID
                         AND cm.meta_key = 'criteria_ratings'
                     WHERE c.comment_type = 'review' AND c.comment_approved = 1",
                    '$.' . $key, $target_id
                ) );
            } else {
                $row = $wpdb->get_row( $wpdb->prepare(
                    "SELECT COUNT(*) as total, AVG(CAST(JSON_EXTRACT(cm.meta_value, %s) AS DECIMAL(10,1))) as average
                     FROM {$wpdb->comments} c
                     INNER JOIN {$wpdb->commentmeta} cm ON cm.comment_id = c.comment_ID
                         AND cm.meta_key = 'criteria_ratings'
                     WHERE c.comment_post_ID = %d AND c.comment_type = 'review' AND c.comment_approved = 1",
                    '$.' . $key, $target_id
                ) );
            }

            $stats[ $key ] = [
                'label'   => $criterion['label'] ?? $key,
                'average' => round( (float) ( $row->average ?? 0 ), 1 ),
                'total'   => (int) ( $row->total ?? 0 ),
            ];
        }

        return $stats;
    }
}
