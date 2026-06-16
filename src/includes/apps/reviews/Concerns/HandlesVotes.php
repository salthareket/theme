<?php

namespace SaltHareket\Reviews\Concerns;

/**
 * HandlesVotes
 * "Bu yorum faydalı mıydı?" sistemi.
 * Helpful score sıralamada kullanılır.
 *
 * @version 1.0.0
 */
trait HandlesVotes
{
    /**
     * Oy ver.
     *
     * @param  string $type  'helpful' | 'unhelpful'
     * @return array{success: bool, helpful: int, unhelpful: int, score: int}
     *
     * @example
     *   Reviews::vote(99, get_current_user_id(), 'helpful');
     */
    public static function vote( int $comment_id, int $user_id, string $type = 'helpful' ): array
    {
        if ( $user_id < 1 ) {
            return [ 'success' => false, 'message' => trans( 'Giriş yapmanız gerekiyor.' ) ];
        }

        $type = in_array( $type, [ 'helpful', 'unhelpful' ], true ) ? $type : 'helpful';

        $voted_key = "_voted_{$user_id}";
        $existing  = get_comment_meta( $comment_id, $voted_key, true ) ?: null;

        $helpful   = max( 0, (int) get_comment_meta( $comment_id, 'helpful_count',   true ) );
        $unhelpful = max( 0, (int) get_comment_meta( $comment_id, 'unhelpful_count', true ) );

        if ( $existing === $type ) {
            // Aynı oyu tekrar verince → geri al (toggle off)
            delete_comment_meta( $comment_id, $voted_key );
            if ( $type === 'helpful' )   $helpful   = max( 0, $helpful - 1 );
            else                         $unhelpful = max( 0, $unhelpful - 1 );
            $voted = null;

        } elseif ( $existing ) {
            // Farklı oy → eskiyi geri al, yeniyi ekle
            update_comment_meta( $comment_id, $voted_key, $type );
            if ( $type === 'helpful' ) {
                $helpful   = $helpful + 1;
                $unhelpful = max( 0, $unhelpful - 1 );
            } else {
                $unhelpful = $unhelpful + 1;
                $helpful   = max( 0, $helpful - 1 );
            }
            $voted = $type;

        } else {
            // İlk oy — update_comment_meta ile unique garantisi
            update_comment_meta( $comment_id, $voted_key, $type );
            if ( $type === 'helpful' ) $helpful++;
            else                       $unhelpful++;
            $voted = $type;
        }

        update_comment_meta( $comment_id, 'helpful_count',   $helpful );
        update_comment_meta( $comment_id, 'unhelpful_count', $unhelpful );
        update_comment_meta( $comment_id, 'helpful_score',   $helpful - $unhelpful );

        do_action( 'reviews/voted', $comment_id, $user_id, $type );

        return [
            'success'   => true,
            'helpful'   => $helpful,
            'unhelpful' => $unhelpful,
            'score'     => $helpful - $unhelpful,
            'voted'     => $voted,
        ];
    }

    /**
     * Kullanıcının bu review'a verdiği oyu getir.
     * @return string|null  'helpful' | 'unhelpful' | null
     */
    public static function getUserVote( int $comment_id, int $user_id ): ?string
    {
        $vote = get_comment_meta( $comment_id, "_voted_{$user_id}", true );
        return $vote ?: null;
    }

    /**
     * Review'ın oy sayılarını getir.
     * @return array{helpful: int, unhelpful: int, score: int}
     */
    public static function getVotes( int $comment_id ): array
    {
        return [
            'helpful'   => (int) get_comment_meta( $comment_id, 'helpful_count',   true ),
            'unhelpful' => (int) get_comment_meta( $comment_id, 'unhelpful_count', true ),
            'score'     => (int) get_comment_meta( $comment_id, 'helpful_score',   true ),
        ];
    }
}
