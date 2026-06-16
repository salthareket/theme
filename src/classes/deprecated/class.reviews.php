<?php

/**
 * Reviews Engine
 * WP Comments tabanlı review/rating sistemi.
 * Post'lara veya kullanıcılara review yazılabilir.
 * Notification, permission, duplicate koruması, hook sistemi,
 * QueryCache entegrasyonu dahil.
 *
 * @version 1.0.0
 *
 * @changelog
 *   1.0.0 - 2026-04-03
 *     - Add: Initial versioned release
 *
 * ─── KULLANIM ─────────────────────────────────────────────
 *
 *   $reviews = new Reviews();
 *
 *   // ── OLUŞTUR ──
 *   $result = $reviews->create([
 *       'target_id'   => 123,
 *       'target_type' => 'user',       // 'post' veya 'user' (default: 'post')
 *       'rating'      => 5,
 *       'title'       => 'Harika!',
 *       'content'     => 'Çok beğendim.',
 *       'image'       => 456,          // attachment ID (opsiyonel)
 *       'notify'      => true,         // notification gönder (default: true)
 *       'meta'        => [             // dinamik meta (opsiyonel)
 *           'custom_field' => 'value',
 *       ],
 *   ]);
 *   // $result = ['success' => true, 'id' => 99, 'message' => '...']
 *   // $result = ['success' => false, 'error' => 'duplicate', 'message' => '...']
 *
 *   // ── GÜNCELLE ──
 *   $reviews->update(99, ['rating' => 4, 'content' => 'Güncellendi']);
 *
 *   // ── SİL ──
 *   $reviews->delete(99);
 *
 *   // ── ONAYLA / REDDET ──
 *   $reviews->approve(99);
 *   $reviews->reject(99);
 *
 *   // ── TEKİL ──
 *   $review = $reviews->get(99);  // Review (Timber\Comment) veya null
 *
 *   // ── LİSTELER (paginated) ──
 *   $result = $reviews->get_for_post(123, ['page' => 1, 'per_page' => 10, 'rating' => [4,5]]);
 *   // $result['reviews'] = Review[], $result['data'] = pagination meta
 *
 *   $result = $reviews->get_for_user(456);     // kullanıcıya yazılan
 *   $result = $reviews->get_by_author(456);    // kullanıcının yazdığı
 *
 *   // ── RATING ──
 *   $rating = Reviews::rating(123, 'post');
 *   // ['total' => 42, 'average' => 4.3]
 *
 *   $breakdown = Reviews::rating_breakdown(123, 'post');
 *   // [5 => 20, 4 => 12, 3 => 5, 2 => 3, 1 => 2]
 *
 *   $stats = Reviews::stats(123, 'post');
 *   // ['total' => 42, 'average' => 4.3, 'breakdown' => [...]]
 *
 *   // ── KONTROLLER ──
 *   $reviews->can_review(456, 123, 'post');   // bool
 *   $reviews->has_reviewed(456, 123, 'post'); // false veya comment_id
 *
 *   // ── YILDIZ ──
 *   Reviews::stars(5);           // ★★★★★
 *   Reviews::stars(3, 'emoji');  // ⭐⭐⭐
 *
 * ─── HOOKLAR ──────────────────────────────────────────────
 *
 *   do_action('reviews/created',  $comment_id, $data, $reviews);
 *   do_action('reviews/updated',  $comment_id, $data, $reviews);
 *   do_action('reviews/deleted',  $comment_id, $target, $reviews);
 *   do_action('reviews/approved', $comment_id, $target, $reviews);
 *   do_action('reviews/rejected', $comment_id, $target, $reviews);
 *
 *   // Notification özelleştirme:
 *   add_filter('reviews/notification_event', fn($event, $data) => $event, 10, 2);
 *   add_filter('reviews/notification_data',  fn($notif_data, $data) => $notif_data, 10, 2);
 *   add_filter('reviews/can_review',         fn($can, $user_id, $target_id, $type) => $can, 10, 4);
 *
 * ─── CORE META KEYS (otomatik) ───────────────────────────
 *
 *   rating           : int 1-5
 *   comment_title    : string
 *   comment_profile  : int (target_type='user' ise hedef user ID)
 *   comment_image    : int (attachment ID)
 *
 * ─── EK META ─────────────────────────────────────────────
 *
 *   $data['meta'] array'i ile dinamik meta eklenebilir.
 *   Örn: ['comment_tour' => 789, 'source' => 'mobile_app']
 *
 * @package SaltHareket
 * @since   2.0.0
 */

class Reviews {

    private int  $current_user_id = 0;
    private bool $auto_approve    = false;
    private bool $notifications   = true;

    public function __construct( int $user_id = 0 ) {
        $this->current_user_id = $user_id ?: (int) get_current_user_id();
        $this->auto_approve    = defined( 'DISABLE_REVIEW_APPROVE' ) && DISABLE_REVIEW_APPROVE;
        $this->notifications   = defined( 'ENABLE_NOTIFICATIONS' ) && ENABLE_NOTIFICATIONS;
    }

    // =========================================================================
    // CREATE
    // =========================================================================

    public function create( array $data ): array {
        $target_id   = (int) ( $data['target_id'] ?? 0 );
        $target_type = $data['target_type'] ?? 'post';
        $author_id   = (int) ( $data['author_id'] ?? $this->current_user_id );
        $rating      = self::sanitize_rating( $data['rating'] ?? 0 );
        $title       = sanitize_text_field( $data['title'] ?? '' );
        $content     = wp_kses_post( $data['content'] ?? '' );
        $image       = (int) ( $data['image'] ?? 0 );
        $approved    = (bool) ( $data['approved'] ?? $this->auto_approve );
        $notify      = (bool) ( $data['notify'] ?? true );

        // --- Validasyon ---
        if ( $target_id < 1 ) {
            return self::error( 'invalid_target', trans( 'Hedef belirtilmedi.' ) );
        }
        if ( $rating < 1 ) {
            return self::error( 'invalid_rating', trans( 'Lütfen bir puan verin.' ) );
        }
        if ( empty( $content ) ) {
            return self::error( 'empty_content', trans( 'Yorum içeriği boş olamaz.' ) );
        }
        if ( $author_id < 1 ) {
            return self::error( 'not_logged_in', trans( 'Giriş yapmanız gerekiyor.' ) );
        }

        // --- Permission ---
        if ( ! $this->can_review( $author_id, $target_id, $target_type ) ) {
            return self::error( 'not_allowed', trans( 'Bu işlem için yetkiniz yok.' ) );
        }

        // --- Duplicate koruması ---
        $existing = $this->has_reviewed( $author_id, $target_id, $target_type );
        if ( $existing ) {
            return self::error( 'duplicate', trans( 'Daha önce yorum yapmışsınız.' ), [ 'existing_id' => $existing ] );
        }

        // --- Author bilgileri ---
        $author = get_userdata( $author_id );
        if ( ! $author ) {
            return self::error( 'invalid_author', trans( 'Kullanıcı bulunamadı.' ) );
        }

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

        if ( ! $comment_id ) {
            return self::error( 'insert_failed', trans( 'Yorum kaydedilemedi.' ) );
        }

        // --- Core meta ---
        add_comment_meta( $comment_id, 'rating', $rating, true );
        if ( $title !== '' ) {
            add_comment_meta( $comment_id, 'comment_title', $title, true );
        }
        if ( $target_type === 'user' && $target_id > 0 ) {
            add_comment_meta( $comment_id, 'comment_profile', $target_id, true );
        }
        if ( $image > 0 ) {
            add_comment_meta( $comment_id, 'comment_image', $image, true );
        }

        // --- Dinamik meta ---
        $this->save_meta( $comment_id, $data['meta'] ?? [] );

        // --- Rating cache ---
        if ( $approved ) {
            self::update_rating_cache( $target_id, $target_type );
        }

        // --- Notification ---
        if ( $notify && $this->notifications ) {
            $this->send_notification( 'new', $comment_id, $data );
        }

        // --- Hook ---
        do_action( 'reviews/created', $comment_id, $data, $this );

        $message = $approved
            ? trans( 'Yorumunuz yayınlandı.' )
            : trans( 'Yorumunuz onay bekliyor.' );

        return self::success( $comment_id, $message );
    }


    // =========================================================================
    // UPDATE
    // =========================================================================

    public function update( int $comment_id, array $data ): array {
        $comment = get_comment( $comment_id );
        if ( ! $comment || $comment->comment_type !== 'review' ) {
            return self::error( 'not_found', trans( 'Yorum bulunamadı.' ) );
        }

        if ( ! $this->can_edit( $comment ) ) {
            return self::error( 'not_allowed', trans( 'Bu yorumu düzenleme yetkiniz yok.' ) );
        }

        $update_data = [ 'comment_ID' => $comment_id ];

        if ( isset( $data['content'] ) ) {
            $update_data['comment_content'] = wp_kses_post( $data['content'] );
        }
        if ( isset( $data['approved'] ) ) {
            $update_data['comment_approved'] = $data['approved'] ? 1 : 0;
        }

        if ( count( $update_data ) > 1 ) {
            wp_update_comment( $update_data );
        }

        if ( isset( $data['rating'] ) ) {
            update_comment_meta( $comment_id, 'rating', self::sanitize_rating( $data['rating'] ) );
        }
        if ( isset( $data['title'] ) ) {
            update_comment_meta( $comment_id, 'comment_title', sanitize_text_field( $data['title'] ) );
        }
        if ( isset( $data['image'] ) ) {
            update_comment_meta( $comment_id, 'comment_image', (int) $data['image'] );
        }

        $this->save_meta( $comment_id, $data['meta'] ?? [] );

        // Rating cache
        $target = self::resolve_target( $comment );
        $is_approved = (int) ( $data['approved'] ?? $comment->comment_approved );
        if ( $is_approved ) {
            self::update_rating_cache( $target['id'], $target['type'] );
        }

        // Notification
        if ( $is_approved && $this->notifications && ( $data['notify'] ?? false ) ) {
            $this->send_notification( 'approved', $comment_id, $data );
        }

        do_action( 'reviews/updated', $comment_id, $data, $this );

        return self::success( $comment_id, trans( 'Yorum güncellendi.' ) );
    }


    // =========================================================================
    // DELETE
    // =========================================================================

    public function delete( int $comment_id, bool $force = false ): array {
        $comment = get_comment( $comment_id );
        if ( ! $comment || $comment->comment_type !== 'review' ) {
            return self::error( 'not_found', trans( 'Yorum bulunamadı.' ) );
        }

        if ( ! $this->can_delete( $comment ) ) {
            return self::error( 'not_allowed', trans( 'Bu yorumu silme yetkiniz yok.' ) );
        }

        $target = self::resolve_target( $comment );

        $result = wp_delete_comment( $comment_id, $force );
        if ( ! $result ) {
            return self::error( 'delete_failed', trans( 'Yorum silinemedi.' ) );
        }

        self::update_rating_cache( $target['id'], $target['type'] );

        do_action( 'reviews/deleted', $comment_id, $target, $this );

        return self::success( $comment_id, trans( 'Yorum silindi.' ) );
    }


    // =========================================================================
    // APPROVE / REJECT
    // =========================================================================

    public function approve( int $comment_id ): array {
        $result = $this->set_status( $comment_id, 1 );
        if ( $result ) {
            do_action( 'reviews/approved', $comment_id, self::resolve_target( get_comment( $comment_id ) ), $this );
            if ( $this->notifications ) {
                $this->send_notification( 'approved', $comment_id, [] );
            }
        }
        return $result
            ? self::success( $comment_id, trans( 'Yorum onaylandı.' ) )
            : self::error( 'approve_failed', trans( 'Onaylama başarısız.' ) );
    }

    public function reject( int $comment_id ): array {
        $result = $this->set_status( $comment_id, 0 );
        if ( $result ) {
            do_action( 'reviews/rejected', $comment_id, self::resolve_target( get_comment( $comment_id ) ), $this );
        }
        return $result
            ? self::success( $comment_id, trans( 'Yorum reddedildi.' ) )
            : self::error( 'reject_failed', trans( 'Reddetme başarısız.' ) );
    }

    private function set_status( int $comment_id, int $status ): bool {
        $comment = get_comment( $comment_id );
        if ( ! $comment || $comment->comment_type !== 'review' ) return false;

        $result = wp_update_comment( [
            'comment_ID'       => $comment_id,
            'comment_approved' => $status,
        ] );

        if ( $result && ! is_wp_error( $result ) ) {
            $target = self::resolve_target( $comment );
            self::update_rating_cache( $target['id'], $target['type'] );
            return true;
        }
        return false;
    }


    // =========================================================================
    // READ — Tekil
    // =========================================================================

    public function get( int $comment_id ): ?\Timber\Comment {
        $comment = get_comment( $comment_id );
        if ( ! $comment || $comment->comment_type !== 'review' ) return null;
        return class_exists( 'Review' ) ? new \Review( $comment ) : new \Timber\Comment( $comment );
    }


    // =========================================================================
    // READ — Listeler (paginated)
    // =========================================================================

    /**
     * @return array{reviews: array, data: array}
     */
    public function get_for_post( int $post_id, array $args = [] ): array {
        $q = $this->build_query( $args );
        $q['post_id'] = $post_id;
        return $this->run_query( $q );
    }

    public function get_for_user( int $user_id, array $args = [] ): array {
        $q = $this->build_query( $args );
        $q['meta_query'][] = [
            'key'   => 'comment_profile',
            'value' => $user_id,
            'type'  => 'NUMERIC',
        ];
        return $this->run_query( $q );
    }

    public function get_by_author( int $user_id, array $args = [] ): array {
        $q = $this->build_query( $args );
        $q['user_id'] = $user_id;
        return $this->run_query( $q );
    }


    // =========================================================================
    // RATING — Static (QueryCache uyumlu)
    // =========================================================================

    /**
     * @return array{total: int, average: float}
     */
    public static function rating( int $target_id, string $type = 'post' ): array {
        // QueryCache wrap — aynı request'te tekrar DB'ye gitmez
        $cache_key = "review_rating_{$type}_{$target_id}";

        if ( class_exists( 'QueryCache' ) ) {
            return \QueryCache::wrap( $cache_key, static fn() => self::_query_rating( $target_id, $type ) );
        }

        return self::_query_rating( $target_id, $type );
    }

    private static function _query_rating( int $target_id, string $type ): array {
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
            'total'   => (int) ( $row->total ?? 0 ),
            'average' => round( (float) ( $row->average ?? 0 ), 1 ),
        ];
    }

    /**
     * @return array<int, int> [5 => 20, 4 => 12, ...]
     */
    public static function rating_breakdown( int $target_id, string $type = 'post' ): array {
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

    public static function stats( int $target_id, string $type = 'post' ): array {
        return [
            ...self::rating( $target_id, $type ),
            'breakdown' => self::rating_breakdown( $target_id, $type ),
        ];
    }


    // =========================================================================
    // PERMISSION
    // =========================================================================

    public function can_review( int $user_id, int $target_id, string $type = 'post' ): bool {
        if ( $user_id < 1 ) return false;

        // Kendine review yazamaz
        if ( $type === 'user' && $user_id === $target_id ) return false;

        if ( $type === 'post' ) {
            $post = get_post( $target_id );
            if ( ! $post || $post->post_status !== 'publish' ) return false;
            if ( (int) $post->post_author === $user_id ) return false;
        }

        // Filtrelenebilir — dışarıdan ek kurallar eklenebilir
        return (bool) apply_filters( 'reviews/can_review', true, $user_id, $target_id, $type );
    }

    public function can_edit( \WP_Comment $comment ): bool {
        if ( current_user_can( 'moderate_comments' ) ) return true;
        return (int) $comment->user_id === $this->current_user_id;
    }

    public function can_delete( \WP_Comment $comment ): bool {
        if ( current_user_can( 'moderate_comments' ) ) return true;
        return (int) $comment->user_id === $this->current_user_id;
    }

    /**
     * @return false|int  false veya mevcut comment_id
     */
    public function has_reviewed( int $user_id, int $target_id, string $type = 'post' ): false|int {
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


    // =========================================================================
    // TOPLU İŞLEMLER — Static
    // =========================================================================

    public static function delete_for_post( int $post_id ): int {
        global $wpdb;
        return (int) $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->comments} WHERE comment_post_ID = %d AND comment_type = 'review'",
            $post_id
        ) );
    }

    public static function delete_for_user( int $user_id ): int {
        global $wpdb;

        $deleted = (int) $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->comments} WHERE user_id = %d AND comment_type = 'review'",
            $user_id
        ) );

        $target_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT c.comment_ID FROM {$wpdb->comments} c
             INNER JOIN {$wpdb->commentmeta} pm ON pm.comment_id = c.comment_ID
                 AND pm.meta_key = 'comment_profile' AND pm.meta_value = %d
             WHERE c.comment_type = 'review'",
            $user_id
        ) );

        if ( ! empty( $target_ids ) ) {
            $placeholders = implode( ',', array_map( 'intval', $target_ids ) );
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $deleted += (int) $wpdb->query( "DELETE FROM {$wpdb->comments} WHERE comment_ID IN ({$placeholders})" );
        }

        return $deleted;
    }


    // =========================================================================
    // NOTIFICATION — Private
    // =========================================================================

    private function send_notification( string $action, int $comment_id, array $data ): void {
        if ( ! $this->notifications || ! class_exists( 'Notifications' ) ) return;

        $comment = get_comment( $comment_id );
        if ( ! $comment ) return;

        $target    = self::resolve_target( $comment );
        $author_id = (int) $comment->user_id;

        // Notification event belirleme
        $event = '';
        if ( $target['type'] === 'user' ) {
            $target_user = class_exists( 'User' ) ? new \User( $target['id'] ) : get_userdata( $target['id'] );
            $role = '';
            if ( $target_user instanceof \User ) {
                $role = $target_user->get_role();
            } elseif ( $target_user ) {
                $roles = $target_user->roles ?? [];
                $role = $roles[0] ?? '';
            }

            $event = match( $action ) {
                'new'      => $role . '/new-review',
                'approved' => $role . '/review-approved',
                default    => '',
            };
        }

        $event = apply_filters( 'reviews/notification_event', $event, [
            'action'    => $action,
            'comment'   => $comment,
            'target'    => $target,
            'author_id' => $author_id,
        ] );

        if ( empty( $event ) ) return;

        // Notification data
        $author_user = class_exists( 'User' ) ? new \User( $author_id ) : get_userdata( $author_id );
        $post_obj    = $comment->comment_post_ID ? get_post( $comment->comment_post_ID ) : null;

        $notif_data = [
            'user'      => $author_user,
            'recipient' => $target['id'],
            'post'      => $post_obj,
        ];

        $notif_data = apply_filters( 'reviews/notification_data', $notif_data, [
            'action'  => $action,
            'comment' => $comment,
            'target'  => $target,
            'data'    => $data,
        ] );

        try {
            $notif = new \Notifications();
            $notif->on( $event, $notif_data );
        } catch ( \Throwable $e ) {
            // Notification hatası review işlemini engellemez
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[Reviews] Notification error: ' . $e->getMessage() );
            }
        }
    }


    // =========================================================================
    // PRIVATE — Query
    // =========================================================================

    private function build_query( array $args ): array {
        $defaults = [
            'page'     => 1,
            'per_page' => 10,
            'orderby'  => 'comment_date_gmt',
            'order'    => 'DESC',
            'status'   => 'approve',
            'rating'   => null,
        ];
        $args = array_merge( $defaults, $args );

        $q = [
            'type'          => 'review',
            'status'        => $args['status'],
            'number'        => max( 1, (int) $args['per_page'] ),
            'offset'        => ( max( 1, (int) $args['page'] ) - 1 ) * max( 1, (int) $args['per_page'] ),
            'orderby'       => sanitize_key( $args['orderby'] ),
            'order'         => in_array( strtoupper( $args['order'] ), [ 'ASC', 'DESC' ], true )
                               ? strtoupper( $args['order'] ) : 'DESC',
            'meta_query'    => [],
            'no_found_rows' => false,
        ];

        if ( $args['rating'] !== null ) {
            $ratings = array_map( 'intval', (array) $args['rating'] );
            $q['meta_query'][] = [
                'key'     => 'rating',
                'value'   => $ratings,
                'compare' => 'IN',
                'type'    => 'NUMERIC',
            ];
        }

        if ( $args['orderby'] === 'rating' ) {
            $q['meta_key'] = 'rating';
            $q['orderby']  = 'meta_value_num';
        }

        return $q;
    }

    /**
     * @return array{reviews: Review[], data: array}
     */
    private function run_query( array $q ): array {
        // Count
        $count_q = $q;
        unset( $count_q['number'], $count_q['offset'] );
        $count_q['count'] = true;
        $total = (int) ( new \WP_Comment_Query( $count_q ) )->get_comments();

        // Fetch
        $query    = new \WP_Comment_Query( $q );
        $comments = $query->comments ?? [];

        $reviews = [];
        $use_review_class = class_exists( 'Review' );
        foreach ( $comments as $c ) {
            $reviews[] = $use_review_class ? new \Review( $c ) : new \Timber\Comment( $c );
        }

        $per_page   = max( 1, (int) ( $q['number'] ?? 10 ) );
        $page       = (int) floor( ( $q['offset'] ?? 0 ) / $per_page ) + 1;
        $page_total = $per_page > 0 ? (int) ceil( $total / $per_page ) : 1;

        return [
            'reviews' => $reviews,
            'data'    => [
                'total'      => $total,
                'page'       => $page,
                'page_total' => $page_total,
                'per_page'   => $per_page,
            ],
        ];
    }


    // =========================================================================
    // PRIVATE — Helpers
    // =========================================================================

    private function save_meta( int $comment_id, array $meta ): void {
        if ( empty( $meta ) ) return;
        foreach ( $meta as $key => $value ) {
            $key = sanitize_key( $key );
            if ( $key !== '' ) {
                update_comment_meta( $comment_id, $key, $value );
            }
        }
    }

    private static function sanitize_rating( $rating ): int {
        return max( 1, min( 5, (int) $rating ) );
    }

    private static function resolve_target( \WP_Comment $comment ): array {
        $profile = (int) get_comment_meta( $comment->comment_ID, 'comment_profile', true );
        if ( $profile > 0 ) {
            return [ 'id' => $profile, 'type' => 'user' ];
        }
        return [ 'id' => (int) $comment->comment_post_ID, 'type' => 'post' ];
    }

    private static function update_rating_cache( int $target_id, string $type ): void {
        if ( $target_id < 1 ) return;

        $r = self::_query_rating( $target_id, $type );

        if ( $type === 'user' ) {
            update_user_meta( $target_id, '_user_rating', $r['average'] );
            update_user_meta( $target_id, '_user_rating_count', $r['total'] );
        } else {
            update_post_meta( $target_id, '_post_rating', $r['average'] );
            update_post_meta( $target_id, '_post_rating_count', $r['total'] );
        }

        // QueryCache varsa wrap cache'ini invalidate et
        if ( class_exists( 'QueryCache' ) ) {
            \QueryCache::forget( "review_rating_{$type}_{$target_id}" );
        }
    }


    // =========================================================================
    // STATIC — Yardımcılar
    // =========================================================================

    public static function stars( int $rating, string $format = 'text' ): string {
        $rating = max( 1, min( 5, $rating ) );
        if ( $format === 'emoji' ) {
            return str_repeat( '⭐', $rating );
        }
        return str_repeat( '★', $rating ) . str_repeat( '☆', 5 - $rating );
    }

    // =========================================================================
    // RESPONSE FORMAT
    // =========================================================================

    private static function success( int $id, string $message, array $extra = [] ): array {
        return array_merge( [
            'success' => true,
            'id'      => $id,
            'message' => $message,
        ], $extra );
    }

    private static function error( string $code, string $message, array $extra = [] ): array {
        return array_merge( [
            'success' => false,
            'error'   => $code,
            'message' => $message,
        ], $extra );
    }
}
