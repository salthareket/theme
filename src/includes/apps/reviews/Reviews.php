<?php

namespace SaltHareket\Reviews;

use SaltHareket\Reviews\Concerns\ManagesReviews;
use SaltHareket\Reviews\Concerns\QueriesReviews;
use SaltHareket\Reviews\Concerns\CalculatesRating;
use SaltHareket\Reviews\Concerns\ChecksPermissions;
use SaltHareket\Reviews\Concerns\HandlesNotifications;
use SaltHareket\Reviews\Concerns\HandlesMedia;
use SaltHareket\Reviews\Concerns\HandlesVotes;

/**
 * Reviews
 * WP Comments tabanlı, trait modüler review/rating sistemi.
 * Post'lara veya kullanıcılara review yazılabilir.
 * Geriye uyumlu — eski `new Reviews()` çağrıları kırılmaz.
 *
 * @version 2.0.0
 * @changelog
 *   2.0.0 - 2026-05-05
 *     - Refactor: Trait tabanlı modüler yapı (PAE/SearchHistory pattern)
 *     - Add: Helpful votes sistemi (HandlesVotes)
 *     - Add: Çoklu medya desteği (HandlesMedia)
 *     - Add: Reply/response sistemi
 *     - Add: Verified flag + checkVerified() filter
 *     - Add: Rate limiting (transient bazlı)
 *     - Add: Flexible sort (helpful, rating_high, rating_low, recent)
 *     - Add: Verified filtresi sorgularda
 *     - Add: Admin UI (ReviewsAdmin) — sticky toolbar, bulk işlemler
 *     - Fix: Notification event hardcode kaldırıldı — generic + filter
 *     - Fix: stars() HTML format eklendi
 *   1.0.0 - 2026-04-03 — Initial release
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // ── OLUŞTUR ──
 * $reviews = new Reviews();
 * $result  = $reviews->create([
 *     'target_id'   => 123,
 *     'target_type' => 'post',       // 'post' | 'user'
 *     'rating'      => 5,
 *     'title'       => 'Harika!',
 *     'content'     => 'Çok beğendim.',
 *     'verified'    => Reviews::checkVerified(get_current_user_id(), 123),
 *     'images'      => [456, 789],   // attachment ID'leri
 *     'meta'        => ['custom_field' => 'value'],
 * ]);
 *
 * // ── GÜNCELLE / SİL / ONAYLA ──
 * $reviews->update(99, ['rating' => 4]);
 * $reviews->delete(99);
 * $reviews->approve(99);
 * $reviews->reject(99);
 *
 * // ── CEVAP VER ──
 * $reviews->reply(99, 'Teşekkürler!');
 *
 * // ── SORGULA ──
 * $result = $reviews->getForPost(123, ['page' => 1, 'per_page' => 10, 'sort' => 'helpful']);
 * $result = $reviews->getForUser(456);
 * $result = $reviews->getByAuthor(456);
 * $result = $reviews->getPending();
 *
 * // ── RATING ──
 * $rating    = Reviews::rating(123, 'post');       // ['total' => 42, 'average' => 4.3]
 * $breakdown = Reviews::ratingBreakdown(123);      // [5 => 20, 4 => 12, ...]
 * $stats     = Reviews::stats(123);                // rating + breakdown + percentage
 *
 * // ── VOTES ──
 * Reviews::vote(99, get_current_user_id(), 'helpful');
 * Reviews::getVotes(99);                           // ['helpful' => 5, 'unhelpful' => 1, 'score' => 4]
 *
 * // ── MEDYA ──
 * Reviews::setMedia(99, [123, 456]);
 * Reviews::getMedia(99);
 * $upload = Reviews::uploadMedia($_FILES['images'], get_current_user_id());
 *
 * // ── VERIFIED ──
 * $verified = Reviews::checkVerified(get_current_user_id(), 123, 'post');
 *
 * // ── TOPLU SİL ──
 * Reviews::deleteForPost(123);
 * Reviews::deleteForUser(456);
 *
 * // ── YILDIZ ──
 * Reviews::stars(5);           // ★★★★★
 * Reviews::stars(3, 'emoji');  // ⭐⭐⭐
 * Reviews::stars(4, 'html');   // <span class="sh-stars">...
 *
 * ─── HOOKLAR ──────────────────────────────────────────────
 *
 *   do_action('reviews/created',  $comment_id, $data, $reviews);
 *   do_action('reviews/updated',  $comment_id, $data, $reviews);
 *   do_action('reviews/deleted',  $comment_id, $target, $reviews);
 *   do_action('reviews/approved', $comment_id, $target, $reviews);
 *   do_action('reviews/rejected', $comment_id, $target, $reviews);
 *   do_action('reviews/replied',  $reply_id, $comment_id, $reviews);
 *   do_action('reviews/voted',    $comment_id, $user_id, $type);
 *
 * ─── FİLTRELER ────────────────────────────────────────────
 *
 *   add_filter('reviews/can_review',          fn($can, $uid, $tid, $type) => $can, 10, 4);
 *   add_filter('reviews/check_verified',      fn($v, $uid, $tid, $type) => $v, 10, 4);
 *   add_filter('reviews/notification_event',  fn($event, $ctx) => $event, 10, 2);
 *   add_filter('reviews/notification_data',   fn($data, $ctx) => $data, 10, 2);
 *   add_filter('reviews/rate_limit',          fn($limit) => 10);
 *   add_filter('reviews/max_rating',          fn($max) => 5);
 *   add_filter('reviews/max_media',           fn($max) => 5);
 *   add_filter('reviews/max_media_size',      fn($size) => 5 * MB_IN_BYTES);
 *   add_filter('reviews/allowed_media_types', fn($types) => $types);
 *
 * @example
 *   $reviews = new Reviews();
 *   $result  = $reviews->create(['target_id' => 123, 'rating' => 5, 'content' => 'Great!']);
 *
 * @example
 *   $stats = Reviews::stats(123, 'post');
 *   echo $stats['average']; // 4.3
 *
 * @example
 *   Reviews::vote(99, get_current_user_id(), 'helpful');
 *
 * @example
 *   add_filter('reviews/check_verified', function($v, $uid, $tid, $type) {
 *       return $type === 'post' && wc_customer_bought_product('', $uid, $tid);
 *   }, 10, 4);
 *
 * @example
 *   add_filter('reviews/notification_event', function($event, $ctx) {
 *       return $ctx['action'] === 'new' ? 'new-review' : $event;
 *   }, 10, 2);
 *
 * @package SaltHareket
 * @since   2.0.0
 */
class Reviews
{
    use ManagesReviews;
    use QueriesReviews;
    use CalculatesRating;
    use ChecksPermissions;
    use HandlesNotifications;
    use HandlesMedia;
    use HandlesVotes;

    protected int  $current_user_id      = 0;
    protected bool $auto_approve         = false;
    protected bool $notifications_enabled = false;

    public function __construct( int $user_id = 0 )
    {
        $this->current_user_id       = $user_id ?: (int) get_current_user_id();
        $this->auto_approve          = defined( 'DISABLE_REVIEW_APPROVE' ) && DISABLE_REVIEW_APPROVE;
        $this->notifications_enabled = defined( 'ENABLE_NOTIFICATIONS' ) && ENABLE_NOTIFICATIONS;
    }

    // =========================================================================
    // GERIYE UYUMLU — eski static metodlar
    // =========================================================================

    /** @deprecated Kullan: deleteForPost() */
    public static function delete_for_post( int $post_id ): int
    {
        return self::deleteForPost( $post_id );
    }

    /** @deprecated Kullan: deleteForUser() */
    public static function delete_for_user( int $user_id ): int
    {
        return self::deleteForUser( $user_id );
    }

    /**
     * Twig'den static çağrı için — getReplies() instance metod olduğundan
     * Twig function() ile çağrılamaz, bu wrapper kullanılır.
     */
    public static function getRepliesStatic( int $comment_id ): array
    {
        $instance = new self();
        return $instance->getReplies( $comment_id );
    }

    // =========================================================================
    // PRIVATE — Shared helpers (trait'ler tarafından kullanılır)
    // =========================================================================

    protected function resolveTarget( \WP_Comment $comment ): array
    {
        $profile = (int) get_comment_meta( $comment->comment_ID, 'comment_profile', true );
        if ( $profile > 0 ) return [ 'id' => $profile, 'type' => 'user' ];
        return [ 'id' => (int) $comment->comment_post_ID, 'type' => 'post' ];
    }

    protected function success( int $id, string $message, array $extra = [] ): array
    {
        return array_merge( [ 'success' => true, 'id' => $id, 'message' => $message ], $extra );
    }

    protected function error( string $code, string $message, array $extra = [] ): array
    {
        return array_merge( [ 'success' => false, 'error' => $code, 'message' => $message ], $extra );
    }
}
