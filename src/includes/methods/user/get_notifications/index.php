<?php
$required_setting = ENABLE_NOTIFICATIONS;

if ( ! is_user_logged_in() ) {
    echo json_encode( response_login_required( $vars['view'] ?? 'dropdown' ) );
    wp_die();
}

$user          = Timber::get_user( wp_get_current_user() );
$notifications = new Notifications( $user );
$view          = $vars['view'] ?? 'dropdown';
$page          = (int) ( $vars['page'] ?? 1 );
$per_page      = (int) ( $vars['posts_per_page'] ?? 15 );
$set_seen      = ! empty( $vars['set_seen'] );

$result     = $notifications->get_notifications( array_merge( $vars, [
    'set_seen'       => $set_seen,
    'page'           => $page,
    'posts_per_page' => $per_page,
] ) );
$posts      = $result['posts'] ?? [];
$data       = $result['data'] ?? [];
$page_total = (int) ( $data['page_total'] ?? 1 );

// ── Post listesini twig-friendly format'a çevir ───────────────────────────
$timeAgo = class_exists( '\\Westsworld\\TimeAgo' ) ? new \Westsworld\TimeAgo() : null;
$items   = [];

foreach ( $posts as $row ) {
    $sender = new User( $row->sender_id );
    $url    = '#';

    if ( function_exists( 'notification_url_map' ) ) {
        $ndata   = json_decode( $row->data ?? '{}', true );
        $post_id = (int) ( $ndata['post_id'] ?? 0 );
        $uid     = (int) ( $ndata['user_id'] ?? 0 );
        $url     = notification_url_map( $row->event, $post_id, $uid ) ?: '#';
    }

    $time = $row->created_at;
    if ( $timeAgo && method_exists( $user, 'get_local_date' ) ) {
        $time = $timeAgo->inWordsFromStrings(
            $user->get_local_date( $row->created_at, $sender->get_timezone(), $user->get_timezone() )
        );
    }

    $items[] = [
        'id'     => $row->id,
        'event'  => $row->event,
        'message' => strip_tags( $row->message ?? '' ),
        'url'    => $url,
        'time'   => $time,
        'read'   => (bool) ( $row->status ?? false ),
        'sender' => [
            'image' => get_avatar( $sender->ID, 40, 'mystery', $sender->get_title() ),
            'name'  => $sender->get_title(),
        ],
    ];
}

$context           = Timber::context();
$context['source'] = 'notifications';
$context['view']   = $view;
$context['items']  = $items;
// Standart zarf (convention): count = toplam, unread_count = okunmamış (unseen).
// Notifications sınıfı 'count'/'total'/'unread'/'unseen' anahtarlarından hangisini verirse normalize et.
$count        = (int) ( $data['count'] ?? $data['total'] ?? count( $items ) );
$unread_count = (int) ( $data['unread_count'] ?? $data['unseen'] ?? $data['unread'] ?? 0 );
if ( method_exists( $user, 'get_notifications_unseen_count' ) ) {
    $unread_count = (int) $user->get_notifications_unseen_count();
}
unset( $data['total'], $data['unseen'], $data['unread'] );

$data['count']        = $count;
$data['page']         = (int) $page;
$data['page_total']   = (int) $page_total;
$data['per_page']     = (int) $per_page;
$data['unread_count'] = $unread_count;

$context['meta']   = [
    'count'        => $count,
    'page'         => (int) $page,
    'page_total'   => (int) $page_total,
    'per_page'     => (int) $per_page,
    'unread_count' => $unread_count,
];

// Feed modu (my-account): sadece tease'ler. Popup: archive (+ archive-{view} override fallback).
$popup_views = [ 'offcanvas', 'dropdown', 'modal' ];

if ( ! in_array( $view, $popup_views, true ) ) {
    $html = '';
    foreach ( $items as $item ) {
        $html .= Timber::compile( 'partials/source/notifications/tease.twig', array_merge( $context, [ 'item' => $item ] ) );
    }
    $data['loader'] = $vars['loader'] ?? 'ul';
    $response['html'] = $html;
} else {
    $response['html'] = Timber::compile(
        [ "partials/source/notifications/archive-{$view}.twig", 'partials/source/notifications/archive.twig' ],
        $context
    );
}

// data'yı en son set et — loader gibi sonradan eklenen key'ler dahil olsun
$response['data'] = $data;

echo json_encode( $response );
wp_die();
