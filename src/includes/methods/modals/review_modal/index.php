<?php
/**
 * review_modal — Kullanıcının kendi yorumunu modal'da göster.
 * Onaylı/onaysız fark etmez — comment_id ile doğrudan DB'den çekilir.
 * Status badge, rating, içerik ve delete butonu gösterir.
 *
 * vars:
 *   id — comment ID
 */
$comment_id = (int) ( $vars['id'] ?? 0 );

if ( $comment_id < 1 ) {
    $response['error']   = true;
    $response['message'] = 'Invalid review ID';
    echo json_encode( $response );
    wp_die();
}

$comment = get_comment( $comment_id );

if ( ! $comment ) {
    $response['error']   = true;
    $response['message'] = 'Review not found';
    echo json_encode( $response );
    wp_die();
}

// Sadece yorumun sahibi veya admin görebilir
$is_owner = (int) $comment->user_id === get_current_user_id();
$is_admin = current_user_can( 'moderate_comments' );

if ( ! $is_owner && ! $is_admin ) {
    $response['error']   = true;
    $response['message'] = 'Unauthorized';
    echo json_encode( $response );
    wp_die();
}

$rating     = (int) get_comment_meta( $comment_id, 'rating', true );
$title      = get_comment_meta( $comment_id, 'review_title', true ) ?: '';
$verified   = (bool) get_comment_meta( $comment_id, 'verified', true );
$approved   = $comment->comment_approved === '1';
$post_id    = (int) $comment->comment_post_ID;
$post_title = $post_id ? get_the_title( $post_id ) : '';
$post_link   = $post_id ? get_permalink( $post_id ) : '#';

$context                 = Timber::context();
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

$html = Timber::compile( [ 'partials/modals/review.twig' ], $context );

$response['error']   = false;
$response['message']  = '';
$response['html']     = $html;
$response['data']     = [
    'title' => $title ?: ( $post_title ? 'Review on ' . $post_title : 'Your Review' ),
];
echo json_encode( $response );
wp_die();
