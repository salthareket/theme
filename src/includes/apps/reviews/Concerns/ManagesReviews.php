<?php

namespace SaltHareket\Reviews\Concerns;

/**
 * ManagesReviews
 * Review CRUD işlemleri — create, update, delete, approve, reject.
 *
 * @version 1.0.0
 */
trait ManagesReviews
{
    // =========================================================================
    // CREATE
    // =========================================================================

    public function create( array $data ): array
    {
        $target_id   = (int) ( $data['target_id']   ?? 0 );
        $target_type = sanitize_key( $data['target_type'] ?? 'post' );
        $author_id   = (int) ( $data['author_id']   ?? $this->current_user_id );
        $rating      = $this->sanitizeRating( $data['rating'] ?? 0 );
        $title       = sanitize_text_field( $data['title']   ?? '' );
        $content     = wp_kses_post( $data['content'] ?? '' );
        $image       = (int) ( $data['image'] ?? 0 );
        $images      = array_map( 'intval', (array) ( $data['images'] ?? [] ) );
        $notify      = (bool) ( $data['notify']   ?? true );
        $verified    = (bool) ( $data['verified']  ?? false );

        // ── Approval logic — 4 katmanlı ──────────────────────────────────────
        $approved = $this->resolveApproval( $author_id, $verified, $data['approved'] ?? null );

        // --- Validasyon ---
        if ( $target_id < 1 )    return $this->error( 'invalid_target',  trans( 'Hedef belirtilmedi.' ) );
        if ( $rating < 1 )       return $this->error( 'invalid_rating',  trans( 'Lütfen bir puan verin.' ) );
        if ( empty( $content ) ) return $this->error( 'empty_content',   trans( 'Yorum içeriği boş olamaz.' ) );
        if ( $author_id < 1 )    return $this->error( 'not_logged_in',   trans( 'Giriş yapmanız gerekiyor.' ) );

        // --- Spam / rate limit ---
        if ( $this->isRateLimited( $author_id ) ) {
            return $this->error( 'rate_limited', trans( 'Çok fazla yorum gönderdiniz. Lütfen bekleyin.' ) );
        }

        // --- Permission ---
        if ( ! $this->can_review( $author_id, $target_id, $target_type ) ) {
            return $this->error( 'not_allowed', trans( 'Bu işlem için yetkiniz yok.' ) );
        }

        // --- Duplicate koruması — sadece one_review_per_user açıksa ---
        if ( \SaltHareket\Reviews\ReviewsSettings::get( 'general.one_review_per_user' ) ) {
            $existing = $this->has_reviewed( $author_id, $target_id, $target_type );
            if ( $existing ) {
                return $this->error( 'duplicate', trans( 'Daha önce yorum yapmışsınız.' ), [ 'existing_id' => $existing ] );
            }
        }

        // --- Author ---
        $author = get_userdata( $author_id );
        if ( ! $author ) return $this->error( 'invalid_author', trans( 'Kullanıcı bulunamadı.' ) );

        $post_id = $target_type === 'post' ? $target_id : 0;

        $comment_data = [
            'comment_post_ID'      => $post_id,
            'comment_author'       => $author->display_name,
            'comment_author_email' => $author->user_email,
            'comment_author_url'   => '',
            'comment_content'      => $content,
            'comment_type'         => 'review',
            'comment_parent'       => 0,
            'user_id'              => $author_id,
            'comment_approved'     => $approved ? 1 : 0,
            'comment_date'         => current_time( 'mysql' ),
            'comment_date_gmt'     => current_time( 'mysql', true ),
        ];

        $comment_id = wp_insert_comment( $comment_data );
        if ( ! $comment_id ) return $this->error( 'insert_failed', trans( 'Yorum kaydedilemedi.' ) );

        // --- Core meta ---
        add_comment_meta( $comment_id, 'rating',   $rating, true );
        add_comment_meta( $comment_id, 'verified', $verified ? 1 : 0, true );

        if ( $title !== '' )                        add_comment_meta( $comment_id, 'comment_title',   $title,     true );
        if ( $target_type === 'user' && $target_id ) add_comment_meta( $comment_id, 'comment_profile', $target_id, true );
        if ( $image > 0 )                           add_comment_meta( $comment_id, 'comment_image',   $image,     true );

        // Çoklu medya
        if ( ! empty( $images ) ) {
            add_comment_meta( $comment_id, 'comment_images', array_unique( $images ), true );
        }

        // --- Dinamik meta ---
        $this->saveMeta( $comment_id, $data['meta'] ?? [] );

        // --- Rating cache ---
        if ( $approved ) {
            $this->updateRatingCache( $target_id, $target_type );
        }

        // --- Rate limit kaydı ---
        $this->recordRateLimit( $author_id );

        // --- Notification ---
        if ( $notify && $this->notifications_enabled ) {
            $this->sendNotification( 'new', $comment_id, $data );
        }

        // --- Hook ---
        do_action( 'reviews/created', $comment_id, $data, $this );

        $message = $approved
            ? trans( 'Yorumunuz yayınlandı.' )
            : trans( 'Yorumunuz onay bekliyor.' );

        return $this->success( $comment_id, $message );
    }

    // =========================================================================
    // UPDATE
    // =========================================================================

    public function update( int $comment_id, array $data ): array
    {
        $comment = get_comment( $comment_id );
        if ( ! $comment || $comment->comment_type !== 'review' ) {
            return $this->error( 'not_found', trans( 'Yorum bulunamadı.' ) );
        }
        if ( ! $this->can_edit( $comment ) ) {
            return $this->error( 'not_allowed', trans( 'Bu yorumu düzenleme yetkiniz yok.' ) );
        }

        $update_data = [ 'comment_ID' => $comment_id ];
        if ( isset( $data['content'] ) )  $update_data['comment_content'] = wp_kses_post( $data['content'] );
        if ( isset( $data['approved'] ) ) $update_data['comment_approved'] = $data['approved'] ? 1 : 0;
        if ( count( $update_data ) > 1 )  wp_update_comment( $update_data );

        if ( isset( $data['rating'] ) )   update_comment_meta( $comment_id, 'rating',        $this->sanitizeRating( $data['rating'] ) );
        if ( isset( $data['title'] ) )    update_comment_meta( $comment_id, 'comment_title',  sanitize_text_field( $data['title'] ) );
        if ( isset( $data['image'] ) )    update_comment_meta( $comment_id, 'comment_image',  (int) $data['image'] );
        if ( isset( $data['images'] ) )   update_comment_meta( $comment_id, 'comment_images', array_map( 'intval', (array) $data['images'] ) );
        if ( isset( $data['verified'] ) ) update_comment_meta( $comment_id, 'verified',       $data['verified'] ? 1 : 0 );

        $this->saveMeta( $comment_id, $data['meta'] ?? [] );

        $target      = $this->resolveTarget( $comment );
        $is_approved = (int) ( $data['approved'] ?? $comment->comment_approved );
        if ( $is_approved ) $this->updateRatingCache( $target['id'], $target['type'] );

        if ( $is_approved && $this->notifications_enabled && ( $data['notify'] ?? false ) ) {
            $this->sendNotification( 'approved', $comment_id, $data );
        }

        do_action( 'reviews/updated', $comment_id, $data, $this );

        return $this->success( $comment_id, trans( 'Yorum güncellendi.' ) );
    }

    // =========================================================================
    // DELETE
    // =========================================================================

    public function delete( int $comment_id, bool $force = false ): array
    {
        $comment = get_comment( $comment_id );
        if ( ! $comment || $comment->comment_type !== 'review' ) {
            return $this->error( 'not_found', trans( 'Yorum bulunamadı.' ) );
        }
        if ( ! $this->can_delete( $comment ) ) {
            return $this->error( 'not_allowed', trans( 'Bu yorumu silme yetkiniz yok.' ) );
        }

        $target = $this->resolveTarget( $comment );
        $result = wp_delete_comment( $comment_id, $force );
        if ( ! $result ) return $this->error( 'delete_failed', trans( 'Yorum silinemedi.' ) );

        $this->updateRatingCache( $target['id'], $target['type'] );
        do_action( 'reviews/deleted', $comment_id, $target, $this );

        return $this->success( $comment_id, trans( 'Yorum silindi.' ) );
    }

    // =========================================================================
    // APPROVE / REJECT
    // =========================================================================

    public function approve( int $comment_id ): array
    {
        $result = $this->setStatus( $comment_id, 1 );
        if ( $result ) {
            do_action( 'reviews/approved', $comment_id, $this->resolveTarget( get_comment( $comment_id ) ), $this );
            if ( $this->notifications_enabled ) $this->sendNotification( 'approved', $comment_id, [] );
        }
        return $result
            ? $this->success( $comment_id, trans( 'Yorum onaylandı.' ) )
            : $this->error( 'approve_failed', trans( 'Onaylama başarısız.' ) );
    }

    public function reject( int $comment_id ): array
    {
        $result = $this->setStatus( $comment_id, 0 );
        if ( $result ) {
            do_action( 'reviews/rejected', $comment_id, $this->resolveTarget( get_comment( $comment_id ) ), $this );
        }
        return $result
            ? $this->success( $comment_id, trans( 'Yorum reddedildi.' ) )
            : $this->error( 'reject_failed', trans( 'Reddetme başarısız.' ) );
    }

    // =========================================================================
    // REPLY (post sahibi / hedef kullanıcı cevap verir)
    // =========================================================================

    public function reply( int $comment_id, string $content, int $author_id = 0 ): array
    {
        $comment = get_comment( $comment_id );
        if ( ! $comment || $comment->comment_type !== 'review' ) {
            return $this->error( 'not_found', trans( 'Yorum bulunamadı.' ) );
        }

        $author_id = $author_id ?: $this->current_user_id;
        $author    = get_userdata( $author_id );
        if ( ! $author ) return $this->error( 'invalid_author', trans( 'Kullanıcı bulunamadı.' ) );

        // ── Reply approval — 4 katmanlı ──────────────────────────────────────
        $approved = $this->resolveReplyApproval( $author_id, $comment );

        $reply_id = wp_insert_comment( [
            'comment_post_ID'      => (int) $comment->comment_post_ID,
            'comment_author'       => $author->display_name,
            'comment_author_email' => $author->user_email,
            'comment_content'      => wp_kses_post( $content ),
            'comment_type'         => 'review_reply',
            'comment_parent'       => $comment_id,
            'user_id'              => $author_id,
            'comment_approved'     => $approved ? 1 : 0,
            'comment_date'         => current_time( 'mysql' ),
            'comment_date_gmt'     => current_time( 'mysql', true ),
        ] );

        if ( ! $reply_id ) return $this->error( 'insert_failed', trans( 'Cevap kaydedilemedi.' ) );

        // ── Notification ──────────────────────────────────────────────────────
        // Review yazana bildir
        if ( $this->notifications_enabled ) {
            $review_author_id = (int) $comment->user_id;
            if ( $review_author_id && $review_author_id !== $author_id ) {
                $this->sendNotification( 'replied', $reply_id, [
                    'parent_comment_id' => $comment_id,
                    'reply_author_id'   => $author_id,
                ] );
            }
        }

        // ── Pending ise review yazana onay bildirimi ──────────────────────────
        if ( ! $approved && \SaltHareket\Reviews\ReviewsSettings::get( 'reply.user_approves_reply' ) ) {
            $this->notifyReviewAuthorForReplyApproval( $comment_id, $reply_id, $author_id );
        }

        do_action( 'reviews/replied', $reply_id, $comment_id, $this );

        $message = $approved
            ? trans( 'Cevabınız yayınlandı.' )
            : trans( 'Cevabınız onay bekliyor.' );

        return $this->success( $reply_id, $message, [ 'approved' => $approved ] );
    }

    // =========================================================================
    // TOPLU İŞLEMLER
    // =========================================================================

    public static function deleteForPost( int $post_id ): int
    {
        global $wpdb;
        return (int) $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->comments} WHERE comment_post_ID = %d AND comment_type IN ('review','review_reply')",
            $post_id
        ) );
    }

    public static function deleteForUser( int $user_id ): int
    {
        global $wpdb;

        $deleted = (int) $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->comments} WHERE user_id = %d AND comment_type IN ('review','review_reply')",
            $user_id
        ) );

        $ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT c.comment_ID FROM {$wpdb->comments} c
             INNER JOIN {$wpdb->commentmeta} pm ON pm.comment_id = c.comment_ID
                 AND pm.meta_key = 'comment_profile' AND pm.meta_value = %d
             WHERE c.comment_type = 'review'",
            $user_id
        ) );

        if ( ! empty( $ids ) ) {
            $in = implode( ',', array_map( 'intval', $ids ) );
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $deleted += (int) $wpdb->query( "DELETE FROM {$wpdb->comments} WHERE comment_ID IN ({$in})" );
        }

        return $deleted;
    }

    // =========================================================================
    // PRIVATE
    // =========================================================================

    private function setStatus( int $comment_id, int $status ): bool
    {
        $comment = get_comment( $comment_id );
        if ( ! $comment || $comment->comment_type !== 'review' ) return false;

        $result = wp_update_comment( [ 'comment_ID' => $comment_id, 'comment_approved' => $status ] );
        if ( $result && ! is_wp_error( $result ) ) {
            $target = $this->resolveTarget( $comment );
            $this->updateRatingCache( $target['id'], $target['type'] );
            return true;
        }
        return false;
    }

    private function saveMeta( int $comment_id, array $meta ): void
    {
        foreach ( $meta as $key => $value ) {
            $key = sanitize_key( $key );
            if ( $key !== '' ) update_comment_meta( $comment_id, $key, $value );
        }
    }

    private function isRateLimited( int $user_id ): bool
    {
        $key   = "reviews_rl_{$user_id}";
        $count = (int) get_transient( $key );
        return $count >= apply_filters( 'reviews/rate_limit', 5 ); // 5 review / saat
    }

    private function recordRateLimit( int $user_id ): void
    {
        $key   = "reviews_rl_{$user_id}";
        $count = (int) get_transient( $key );
        set_transient( $key, $count + 1, HOUR_IN_SECONDS );
    }

    // ─── 4 Katmanlı Approval ─────────────────────────────────────────────────

    /**
     * Review için approval kararı.
     *
     * Öncelik sırası:
     *   1. Manuel override ($data['approved'] verilmişse)
     *   2. Admin her zaman otomatik
     *   3. Verified kullanıcı + ayar açıksa otomatik
     *   4. Güvenilir kullanıcı (yeterli onaylı review) + ayar açıksa otomatik
     *   5. Global auto_approve ayarı
     */
    private function resolveApproval( int $author_id, bool $verified, mixed $override = null ): bool
    {
        // 1. Manuel override
        if ( $override !== null ) return (bool) $override;

        // 2. Admin her zaman otomatik
        if ( user_can( $author_id, 'manage_options' ) ) return true;

        // 3. Verified + ayar açık
        if ( $verified && \SaltHareket\Reviews\ReviewsSettings::get( 'general.auto_approve_verified' ) ) {
            return true;
        }

        // 4. Güvenilir kullanıcı
        if ( \SaltHareket\Reviews\ReviewsSettings::get( 'general.auto_approve_trusted' ) ) {
            $threshold = (int) \SaltHareket\Reviews\ReviewsSettings::get( 'general.trusted_threshold' );
            if ( $this->getTrustedScore( $author_id ) >= $threshold ) {
                return true;
            }
        }

        // 5. Global ayar
        return (bool) \SaltHareket\Reviews\ReviewsSettings::get( 'general.auto_approve_reviews' );
    }

    /**
     * Reply için approval kararı.
     *
     * Öncelik sırası:
     *   1. İçerik sahibi reply → ayar açıksa otomatik
     *   2. Admin reply → otomatik
     *   3. user_approves_reply açıksa → pending (review yazan onaylayacak)
     *   4. Global auto_approve
     */
    private function resolveReplyApproval( int $author_id, \WP_Comment $parent_comment ): bool
    {
        // Admin her zaman otomatik
        if ( user_can( $author_id, 'manage_options' ) ) return true;

        // İçerik sahibi mi?
        $target = $this->resolveTarget( $parent_comment );
        $is_owner = false;

        if ( $target['type'] === 'post' ) {
            $post     = get_post( $target['id'] );
            $is_owner = $post && (int) $post->post_author === $author_id;
        } elseif ( $target['type'] === 'user' ) {
            $is_owner = (int) $target['id'] === $author_id;
        }

        if ( $is_owner && \SaltHareket\Reviews\ReviewsSettings::get( 'reply.auto_approve_owner_reply' ) ) {
            return true;
        }

        // user_approves_reply açıksa → pending (review yazan onaylayacak)
        if ( \SaltHareket\Reviews\ReviewsSettings::get( 'reply.user_approves_reply' ) ) {
            return false; // pending
        }

        return (bool) \SaltHareket\Reviews\ReviewsSettings::get( 'general.auto_approve_reviews' );
    }

    /**
     * Kullanıcının güven skoru — onaylı review sayısı.
     */
    private function getTrustedScore( int $user_id ): int
    {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->comments}
             WHERE user_id = %d AND comment_type = 'review' AND comment_approved = '1'",
            $user_id
        ) );
    }
}
