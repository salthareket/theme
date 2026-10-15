<?php
$required_setting = true;

// Login zorunlu değil — public profil sayfasında herkes review görebilir
// Yazma işlemi ayrıca kontrol edilir

if ( ! class_exists( '\SaltHareket\Reviews\Reviews' ) ) {
    $response['error']   = true;
    $response['message'] = 'Reviews not available';
    echo json_encode( $response );
    wp_die();
}

$view      = sanitize_key( $vars['view'] ?? 'list' );
$user_id   = (int) ( $vars['user'] ?? $vars['user_id'] ?? 0 );
$page      = max( 1, (int) ( $vars['page'] ?? 1 ) );
$per_page  = max( 1, (int) ( $vars['posts_per_page'] ?? 10 ) );

$reviews   = new \SaltHareket\Reviews\Reviews( $user_id );
// Hangi review kümesi?
$popup_views  = [ 'offcanvas', 'dropdown', 'modal' ];
$default_mode = in_array( $view, $popup_views, true ) ? 'received' : 'authored';
$mode         = sanitize_key( $vars['mode'] ?? $default_mode );

$args      = [
    'page'     => $page,
    'per_page' => $per_page,
    'order'    => sanitize_key( $vars['order'] ?? 'desc' ),
    'status'   => ( isset( $vars['status'] ) && (string) $vars['status'] === '0' ) ? 'hold' : 'approve',
];

// authored mode'da status parametresi gelmediyse tüm yorumlar
if ( $mode === 'authored' && ! isset( $vars['status'] ) ) {
    $args['status'] = 'all';
}

$result     = ( $mode === 'authored' )
    ? $reviews->getByAuthor( $user_id, $args )
    : ( ( $mode === 'profile' || $mode === 'on_me' )
        ? $reviews->getForUser( $user_id, $args )
        : ( $mode === 'on_posts'
            ? $reviews->getForPosts( $user_id, $args )
            : $reviews->getReceived( $user_id, $args ) ) );
$raw        = $result['reviews'] ?? [];
$data       = $result['data'] ?? [];
$page_total = (int) ( $data['page_total'] ?? 1 );

// data.count normalize — runQuery 'total' döndürür, diğer source'lar 'count' kullanır.
// unread_count her zaman badge kümesiyle (getUnreadCount) tutarlı olsun.
$data['count']        = (int) ( $data['count'] ?? $data['total'] ?? count( $raw ) );
$data['unread_count'] = \SaltHareket\Reviews\Reviews::getUnreadCount( get_current_user_id() );
unset( $data['total'] );

// ── Twig-friendly format ──────────────────────────────────────────────────
$items = [];
foreach ( $raw as $review ) {
    $post_id    = (int) ( $review->comment_post_ID ?? 0 );
    $post_title = $post_id ? get_the_title( $post_id ) : '';
    $post_link  = $post_id ? get_permalink( $post_id ) : '#';

    $author_id     = (int) ( $review->user_id ?? 0 );
    $author_name   = $review->comment_author ?? '';
    $author_avatar = $author_id ? get_avatar_url( $author_id, [ 'size' => 40 ] ) : '';

    $items[] = [
        'id'       => $review->comment_ID,
        'content'  => wp_strip_all_tags( $review->comment_content ?? '' ),
        'rating'   => (int) get_comment_meta( $review->comment_ID, 'rating', true ),
        'date'     => mysql2date( get_option( 'date_format' ), $review->comment_date ),
        'read'     => (bool) get_comment_meta( $review->comment_ID, '_sh_review_read', true ),
        'approved' => $review->comment_approved === '1',
        'verified' => (bool) get_comment_meta( $review->comment_ID, 'verified', true ),
        'post'    => [
            'id'    => $post_id,
            'title' => $post_title,
            'link'  => $post_link,
        ],
        'author'  => [
            'name'   => $author_name,
            'avatar' => $author_avatar,
        ],
    ];
}

$context           = Timber::context();
$context['source'] = 'reviews';
$context['view']   = $view;
$context['mode']   = $mode;
$context['items']  = $items;
$context['meta']   = [
    'count'        => $data['count'],
    'page'         => $page,
    'page_total'   => $page_total,
    'unread_count' => $data['unread_count'],
];

// Feed modu: sadece tease'ler, popup modu: tam archive ($popup_views yukarıda tanımlı)
if ( ! in_array( $view, $popup_views, true ) ) {
    $html = '';
    foreach ( $items as $item ) {
        $html .= Timber::compile( 'partials/source/reviews/tease.twig', array_merge( $context, [ 'item' => $item ] ) );
    }
    // Boşsa not-found mesajı
    if ( empty( $items ) ) {
        $html = Timber::compile( 'partials/not-found/reviews.twig', $context );
    }
    $data['loader'] = $vars['loader'] ?? 'ul';
    $response['html'] = $html;
} else {
    // archive-{view}.twig varsa onu, yoksa archive.twig (view override fallback).
    $response['html'] = Timber::compile(
        [ "partials/source/reviews/archive-{$view}.twig", 'partials/source/reviews/archive.twig' ],
        $context
    );
}

// data'yı en son set et — loader dahil
$response['data'] = $data;

echo json_encode( $response );
wp_die();
