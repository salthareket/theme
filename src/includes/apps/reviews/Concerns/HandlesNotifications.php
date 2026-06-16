<?php

namespace SaltHareket\Reviews\Concerns;

/**
 * HandlesNotifications
 * Review notification gönderimi — event mapping, filter desteği.
 *
 * @version 1.0.0
 */
trait HandlesNotifications
{
    /**
     * Notification gönder.
     *
     * Event mapping filter ile tamamen özelleştirilebilir:
     *
     * @example
     *   add_filter('reviews/notification_event', function($event, $ctx) {
     *       if ($ctx['action'] === 'new' && $ctx['target']['type'] === 'post') {
     *           return 'new-review'; // kendi event slug'ın
     *       }
     *       return $event;
     *   }, 10, 2);
     */
    private function sendNotification( string $action, int $comment_id, array $data ): void
    {
        if ( ! $this->notifications_enabled || ! class_exists( 'Notifications' ) ) return;

        $comment = get_comment( $comment_id );
        if ( ! $comment ) return;

        $target    = $this->resolveTarget( $comment );
        $author_id = (int) $comment->user_id;

        // ── Event slug belirleme ──────────────────────────────────────────────
        $event = $this->resolveNotificationEvent( $action, $target, $author_id );
        $event = (string) apply_filters( 'reviews/notification_event', $event, [
            'action'    => $action,
            'comment'   => $comment,
            'target'    => $target,
            'author_id' => $author_id,
        ] );

        if ( empty( $event ) ) return;

        // ── Notification data ─────────────────────────────────────────────────
        $author_user = get_userdata( $author_id ) ?: null;

        $post_obj = $comment->comment_post_ID
            ? get_post( $comment->comment_post_ID )
            : null;

        $notif_data = [
            'user'      => $author_user,
            'recipient' => $target['id'],
            'post'      => $post_obj,
            'rating'    => (int) get_comment_meta( $comment_id, 'rating', true ),
            'comment'   => $comment,
        ];

        $notif_data = (array) apply_filters( 'reviews/notification_data', $notif_data, [
            'action'  => $action,
            'comment' => $comment,
            'target'  => $target,
            'data'    => $data,
        ] );

        try {
            \Notifications::fire( $event, $notif_data );
        } catch ( \Throwable $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[Reviews] Notification error: ' . $e->getMessage() );
            }
        }
    }

    /**
     * Action + target'a göre default event slug üret.
     * Tamamen generic — hardcode role yok.
     *
     * Örnekler:
     *   new    + post → 'new-review'
     *   new    + user → 'new-review'
     *   approved      → 'review-approved'
     *   replied       → 'review-replied'
     */
    private function resolveNotificationEvent( string $action, array $target, int $author_id ): string
    {
        return match ( $action ) {
            'new'      => 'new-review',
            'approved' => 'review-approved',
            'replied'  => 'review-replied',
            default    => '',
        };
    }

    /**
     * Review yazana — reply onay bekliyor bildirimi.
     * Kullanıcı my-account'tan onaylayacak.
     */
    protected function notifyReviewAuthorForReplyApproval( int $comment_id, int $reply_id, int $reply_author_id ): void
    {
        if ( ! $this->notifications_enabled || ! class_exists( 'Notifications' ) ) return;

        $comment = get_comment( $comment_id );
        if ( ! $comment ) return;

        $review_author_id = (int) $comment->user_id;
        if ( $review_author_id < 1 ) return;

        $reply_author = get_userdata( $reply_author_id );

        try {
            \Notifications::fire( 'review-reply-pending', [
                'recipient'    => $review_author_id,
                'reply_id'     => $reply_id,
                'comment_id'   => $comment_id,
                'reply_author' => $reply_author,
            ] );
        } catch ( \Throwable $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[Reviews] Reply approval notification error: ' . $e->getMessage() );
            }
        }
    }
}
