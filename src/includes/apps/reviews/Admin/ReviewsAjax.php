<?php

namespace SaltHareket\Reviews\Admin;

use SaltHareket\Reviews\Reviews;

/**
 * ReviewsAjax
 * Admin AJAX handler'ları — approve, reject, delete, bulk.
 *
 * @version 1.0.0
 */
class ReviewsAjax
{
    public static function register(): void
    {
        add_action( 'wp_ajax_sh_review_approve', [ self::class, 'approve' ] );
        add_action( 'wp_ajax_sh_review_reject',  [ self::class, 'reject' ] );
        add_action( 'wp_ajax_sh_review_delete',  [ self::class, 'delete' ] );
        add_action( 'wp_ajax_sh_review_bulk',    [ self::class, 'bulk' ] );

        // Frontend
        add_action( 'wp_ajax_sh_review_create',         [ self::class, 'create' ] );
        add_action( 'wp_ajax_sh_review_vote',           [ self::class, 'vote' ] );
        add_action( 'wp_ajax_nopriv_sh_review_vote',    [ self::class, 'vote' ] ); // guest vote (helpful)
        add_action( 'wp_ajax_sh_review_reply',          [ self::class, 'reply' ] );
        add_action( 'wp_ajax_sh_review_approve_reply',  [ self::class, 'approveReply' ] );
        add_action( 'wp_ajax_sh_review_reject_reply',   [ self::class, 'rejectReply' ] );

        // Review modal — kullanıcının kendi yorumunu modal'da gör (status + delete)
        add_action( 'wp_ajax_sh_review_modal',         [ self::class, 'reviewModal' ] );
    }

    public static function approve(): void
    {
        self::checkNonce();
        $id      = (int) ( $_POST['id'] ?? 0 );
        $reviews = new Reviews();
        wp_send_json( $reviews->approve( $id ) );
    }

    public static function reject(): void
    {
        self::checkNonce();
        $id      = (int) ( $_POST['id'] ?? 0 );
        $reviews = new Reviews();
        wp_send_json( $reviews->reject( $id ) );
    }

    public static function delete(): void
    {
        self::checkNonce();
        $id      = (int) ( $_POST['id'] ?? 0 );
        $reviews = new Reviews();
        wp_send_json( $reviews->delete( $id, true ) );
    }

    public static function bulk(): void
    {
        self::checkNonce();
        $action = sanitize_key( $_POST['bulk_action'] ?? '' );
        $ids    = array_map( 'intval', (array) ( $_POST['ids'] ?? [] ) );

        if ( empty( $ids ) || ! in_array( $action, [ 'approve', 'reject', 'delete' ], true ) ) {
            wp_send_json( [ 'success' => false, 'message' => 'Invalid request' ] );
        }

        $reviews = new Reviews();
        $results = [];
        foreach ( $ids as $id ) {
            $results[ $id ] = $reviews->$action( $id );
        }

        wp_send_json( [ 'success' => true, 'results' => $results ] );
    }

    public static function approveReply(): void
    {
        self::checkReplyOwnership();
        $id      = (int) ( $_POST['id'] ?? 0 );
        $reviews = new Reviews();
        wp_send_json( $reviews->approve( $id ) );
    }

    public static function rejectReply(): void
    {
        self::checkReplyOwnership();
        $id      = (int) ( $_POST['id'] ?? 0 );
        $reviews = new Reviews();
        wp_send_json( $reviews->reject( $id ) );
    }

    /**
     * Reply'ın parent review'ının sahibi mi kontrol et.
     */
    private static function checkReplyOwnership(): void
    {
        if ( ! is_user_logged_in() ) {
            wp_send_json( [ 'success' => false, 'message' => 'Not logged in' ], 403 );
        }
        if ( ! check_ajax_referer( 'sh_review_reply_action', '_wpnonce', false ) ) {
            wp_send_json( [ 'success' => false, 'message' => 'Invalid nonce' ], 403 );
        }

        $reply_id = (int) ( $_POST['id'] ?? 0 );
        $reply    = get_comment( $reply_id );

        if ( ! $reply || $reply->comment_type !== 'review_reply' ) {
            wp_send_json( [ 'success' => false, 'message' => 'Reply not found' ], 404 );
        }

        // Parent review'ın sahibi mi?
        $parent = get_comment( $reply->comment_parent );
        if ( ! $parent || (int) $parent->user_id !== get_current_user_id() ) {
            // Admin de yapabilir
            if ( ! current_user_can( 'moderate_comments' ) ) {
                wp_send_json( [ 'success' => false, 'message' => 'Unauthorized' ], 403 );
            }
        }
    }

    private static function checkNonce(): void
    {
        if ( ! check_ajax_referer( 'sh_reviews_nonce', '_wpnonce', false ) || ! current_user_can( 'moderate_comments' ) ) {
            wp_send_json( [ 'success' => false, 'message' => 'Unauthorized' ], 403 );
        }
    }

    // =========================================================================
    // FRONTEND AJAX
    // =========================================================================

    public static function create(): void
    {
        if ( ! is_user_logged_in() ) {
            wp_send_json( [ 'success' => false, 'message' => trans( 'Giriş yapmanız gerekiyor.' ) ] );
        }
        if ( ! check_ajax_referer( 'sh_review_create', '_wpnonce', false ) ) {
            wp_send_json( [ 'success' => false, 'message' => 'Invalid nonce' ], 403 );
        }

        $post_id = (int) ( $_POST['post_id'] ?? 0 );
        if ( $post_id < 1 ) {
            wp_send_json( [ 'success' => false, 'message' => trans( 'Geçersiz içerik.' ) ] );
        }

        $target_type = sanitize_key( $_POST['target_type'] ?? 'post' );
        $target_id   = (int) ( $_POST['target_id'] ?? $post_id );

        // Type config kontrolü
        $subtype = '';
        if ( $target_type === 'post' ) {
            $subtype = get_post_type( $target_id ) ?: 'post';
        } elseif ( $target_type === 'user' ) {
            $target_user = get_userdata( $target_id );
            $subtype     = $target_user && ! empty( $target_user->roles ) ? reset( $target_user->roles ) : '';
        }
        if ( ! \SaltHareket\Reviews\ReviewsSettings::canReviewType( $target_type, $subtype ) ) {
            wp_send_json( [ 'success' => false, 'message' => trans( 'Bu içerik için yorum yapılamaz.' ) ] );
        }

        $reviews  = new Reviews();
        $verified = Reviews::checkVerified( get_current_user_id(), $target_id, $target_type );

        $result = $reviews->create( [
            'target_id'   => $target_id,
            'target_type' => $target_type,
            'rating'      => (int) ( $_POST['rating']  ?? 0 ),
            'title'       => sanitize_text_field( $_POST['title']   ?? '' ),
            'content'     => sanitize_textarea_field( $_POST['content'] ?? '' ),
            'verified'    => $verified,
        ] );

        // Kriter bazlı puanları kaydet
        if ( $result['success'] && ! empty( $_POST['criteria'] ) && is_array( $_POST['criteria'] ) ) {
            Reviews::saveMultiRating( $result['id'], array_map( 'intval', $_POST['criteria'] ), $subtype );
        }

        // Medya upload
        if ( $result['success'] && ! empty( $_FILES['review_images']['name'][0] ) ) {
            $upload = Reviews::uploadMedia( $_FILES['review_images'], get_current_user_id() );
            if ( ! empty( $upload['ids'] ) ) {
                Reviews::setMedia( $result['id'], $upload['ids'] );
            }
        }

        wp_send_json( $result );
    }

    public static function vote(): void
    {
        if ( ! check_ajax_referer( 'sh_review_vote', '_wpnonce', false ) ) {
            wp_send_json( [ 'success' => false, 'message' => 'Invalid nonce' ], 403 );
        }

        $id      = (int) ( $_POST['id']   ?? 0 );
        $type    = sanitize_key( $_POST['type'] ?? 'helpful' );
        $user_id = get_current_user_id();

        // Guest için cookie-based unique ID kullan
        if ( $user_id < 1 ) {
            $guest_id = class_exists( '\\SaltHareket\\Membership\\GuestIdentity' )
                ? \SaltHareket\Membership\GuestIdentity::ensureGuest()
                : '';
            if ( ! $guest_id ) {
                wp_send_json( [ 'success' => false, 'message' => trans( 'Kimlik doğrulanamadı.' ) ] );
                return;
            }
            // Guest user_id olarak negatif hash kullan — comment_meta key unique olsun
            $user_id = -1 * abs( crc32( $guest_id ) );
        }

        wp_send_json( Reviews::vote( $id, $user_id, $type ) );
    }

    public static function reply(): void
    {
        if ( ! is_user_logged_in() ) {
            wp_send_json( [ 'success' => false, 'message' => trans( 'Giriş yapmanız gerekiyor.' ) ] );
        }
        if ( ! check_ajax_referer( 'sh_review_reply', '_wpnonce', false ) ) {
            wp_send_json( [ 'success' => false, 'message' => 'Invalid nonce' ], 403 );
        }

        $comment_id = (int) ( $_POST['comment_id'] ?? 0 );
        $content    = sanitize_textarea_field( $_POST['content'] ?? '' );

        $reviews = new Reviews();
        wp_send_json( $reviews->reply( $comment_id, $content ) );
    }

    /**
     * Review modal — kullanıcının kendi yorumunu modal'da göster.
     * Onaylı/onaysız fark etmez — comment_id ile doğrudan DB'den çekilir.
     * Status badge, rating, içerik ve delete butonu gösterir.
     */
    public static function reviewModal(): void
    {
        if ( ! is_user_logged_in() ) {
            wp_send_json( [ 'success' => false, 'message' => 'Login required' ] );
        }

        $comment_id = (int) ( $_POST['id'] ?? 0 );
        if ( $comment_id < 1 ) {
            wp_send_json( [ 'success' => false, 'message' => 'Invalid review ID' ] );
        }

        $comment = get_comment( $comment_id );
        if ( ! $comment ) {
            wp_send_json( [ 'success' => false, 'message' => 'Review not found' ] );
        }

        // Sadece yorumun sahibi veya admin görebilir
        $is_owner = (int) $comment->user_id === get_current_user_id();
        $is_admin = current_user_can( 'moderate_comments' );
        if ( ! $is_owner && ! $is_admin ) {
            wp_send_json( [ 'success' => false, 'message' => 'Unauthorized' ] );
        }

        // Review verisini hazırla
        $rating    = (int) get_comment_meta( $comment_id, 'rating', true );
        $title     = get_comment_meta( $comment_id, 'review_title', true ) ?: '';
        $verified  = (bool) get_comment_meta( $comment_id, 'verified', true );
        $approved  = $comment->comment_approved === '1';
        $post_id   = (int) $comment->comment_post_ID;
        $post_title = $post_id ? get_the_title( $post_id ) : '';
        $post_link  = $post_id ? get_permalink( $post_id ) : '#';

        // Twig render
        $context                 = \Timber\Timber::context();
        $context['review']       = [
            'id'       => $comment_id,
            'content'  => $comment->comment_content,
            'rating'   => $rating,
            'title'    => $title,
            'verified' => $verified,
            'approved' => $approved,
            'date'     => mysql2date( get_option( 'date_format' ), $comment->comment_date ),
            'author'   => $comment->comment_author,
            'avatar'   => get_avatar_url( $comment->comment_author_email, [ 'size' => 64 ] ),
            'post'     => [
                'id'    => $post_id,
                'title' => $post_title,
                'link'  => $post_link,
            ],
            'is_owner' => $is_owner,
            'is_admin' => $is_admin,
        ];

        $html = \Timber\Timber::compile( [ 'partials/modals/review.twig' ], $context );

        wp_send_json( [
            'success' => true,
            'html'    => $html,
            'data'    => [
                'title' => $title ?: ( $post_title ? 'Review on ' . $post_title : 'Your Review' ),
            ],
        ] );
    }
}
