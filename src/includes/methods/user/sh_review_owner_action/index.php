<?php
/**
 * sh_review_owner_action — Review sahibinin approve/reject/delete yapması
 *
 * vars:
 *   id     — comment ID
 *   action — approve | reject | delete
 */
$required_setting = ENABLE_REVIEWS;

if ( ! is_user_logged_in() ) {
    echo json_encode( [ 'success' => false, 'message' => 'Not logged in.' ] );
    wp_die();
}

$comment_id = (int) ( $vars['id'] ?? 0 );
$action     = sanitize_key( $vars['action'] ?? '' );
$user_id    = get_current_user_id();

if ( ! $comment_id || ! in_array( $action, [ 'approve', 'reject', 'delete' ], true ) ) {
    echo json_encode( [ 'success' => false, 'message' => 'Invalid parameters.' ] );
    wp_die();
}

$comment = get_comment( $comment_id );
if ( ! $comment || $comment->comment_type !== 'review' ) {
    echo json_encode( [ 'success' => false, 'message' => 'Review not found.' ] );
    wp_die();
}

// Yetki: kullanıcı bu yorumun hedefi olmalı
//   a) comment_profile meta = bu kullanıcı (profile review)
//   b) comment_post_ID'nin post_author = bu kullanıcı (post review)
$profile_target = (int) get_comment_meta( $comment_id, 'comment_profile', true );
$post_author    = $comment->comment_post_ID ? (int) get_post_field( 'post_author', $comment->comment_post_ID ) : 0;

$is_authorized = ( $profile_target === $user_id ) || ( $post_author === $user_id );

// Admin her zaman yapabilir
if ( current_user_can( 'moderate_comments' ) ) {
    $is_authorized = true;
}

if ( ! $is_authorized ) {
    echo json_encode( [ 'success' => false, 'message' => 'Unauthorized.' ] );
    wp_die();
}

switch ( $action ) {
    case 'approve':
        wp_set_comment_status( $comment_id, 'approve' );
        update_comment_meta( $comment_id, '_sh_review_read', 1 );
        if ( class_exists( '\SaltHareket\Reviews\Reviews' ) ) {
            ( new \SaltHareket\Reviews\Reviews() )->approve( $comment_id );
        }
        echo json_encode( [ 'success' => true, 'message' => 'Approved.' ] );
        break;

    case 'reject':
        wp_set_comment_status( $comment_id, 'hold' );
        if ( class_exists( '\SaltHareket\Reviews\Reviews' ) ) {
            ( new \SaltHareket\Reviews\Reviews() )->reject( $comment_id );
        }
        echo json_encode( [ 'success' => true, 'message' => 'Rejected.' ] );
        break;

    case 'delete':
        if ( class_exists( '\SaltHareket\Reviews\Reviews' ) ) {
            ( new \SaltHareket\Reviews\Reviews() )->delete( $comment_id );
        } else {
            wp_delete_comment( $comment_id, true );
        }
        echo json_encode( [ 'success' => true, 'message' => 'Deleted.' ] );
        break;
}

wp_die();
