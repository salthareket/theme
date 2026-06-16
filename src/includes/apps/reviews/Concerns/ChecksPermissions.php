<?php

namespace SaltHareket\Reviews\Concerns;

/**
 * ChecksPermissions
 * Review izin kontrolleri — can_review, can_edit, can_delete, has_reviewed, verified check.
 *
 * @version 1.0.0
 */
trait ChecksPermissions
{
    /**
     * Kullanıcı bu hedefe review yazabilir mi?
     */
    public function can_review( int $user_id, int $target_id, string $type = 'post' ): bool
    {
        if ( $user_id < 1 ) return false;

        // Admin her zaman yazabilir (test için)
        if ( current_user_can( 'manage_options' ) ) {
            return (bool) apply_filters( 'reviews/can_review', true, $user_id, $target_id, $type );
        }

        // Kendine review yazamaz
        if ( $type === 'user' && $user_id === $target_id ) return false;

        if ( $type === 'post' ) {
            $post = get_post( $target_id );
            if ( ! $post || $post->post_status !== 'publish' ) return false;
            // Kendi postuna review yazamaz
            if ( (int) $post->post_author === $user_id ) return false;
        }

        return (bool) apply_filters( 'reviews/can_review', true, $user_id, $target_id, $type );
    }

    /**
     * Kullanıcı bu review'ı düzenleyebilir mi?
     */
    public function can_edit( \WP_Comment $comment ): bool
    {
        if ( current_user_can( 'moderate_comments' ) ) return true;
        return (int) $comment->user_id === $this->current_user_id;
    }

    /**
     * Kullanıcı bu review'ı silebilir mi?
     */
    public function can_delete( \WP_Comment $comment ): bool
    {
        if ( current_user_can( 'moderate_comments' ) ) return true;
        return (int) $comment->user_id === $this->current_user_id;
    }

    /**
     * Kullanıcı daha önce bu hedefe review yazmış mı?
     * @return false|int  false veya mevcut comment_id
     */
    public function has_reviewed( int $user_id, int $target_id, string $type = 'post' ): false|int
    {
        global $wpdb;

        if ( $type === 'user' ) {
            $id = $wpdb->get_var( $wpdb->prepare(
                "SELECT c.comment_ID FROM {$wpdb->comments} c
                 INNER JOIN {$wpdb->commentmeta} pm ON pm.comment_id = c.comment_ID
                     AND pm.meta_key = 'comment_profile' AND pm.meta_value = %d
                 WHERE c.user_id = %d AND c.comment_type = 'review' LIMIT 1",
                $target_id, $user_id
            ) );
        } else {
            $id = $wpdb->get_var( $wpdb->prepare(
                "SELECT comment_ID FROM {$wpdb->comments}
                 WHERE comment_post_ID = %d AND user_id = %d AND comment_type = 'review' LIMIT 1",
                $target_id, $user_id
            ) );
        }

        return $id ? (int) $id : false;
    }

    /**
     * Kullanıcının bu hedefle gerçek bir etkileşimi var mı?
     * (satın alma, rezervasyon, tamamlanmış session vs.)
     * Dışarıdan filter ile özelleştirilebilir.
     *
     * @example
     *   add_filter('reviews/check_verified', function($verified, $user_id, $target_id, $type) {
     *       // WooCommerce: ürünü satın almış mı?
     *       if ($type === 'post') {
     *           return wc_customer_bought_product('', $user_id, $target_id);
     *       }
     *       return false;
     *   }, 10, 4);
     */
    public static function checkVerified( int $user_id, int $target_id, string $type = 'post' ): bool
    {
        return (bool) apply_filters( 'reviews/check_verified', false, $user_id, $target_id, $type );
    }
}
