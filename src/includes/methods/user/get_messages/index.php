<?php
$required_setting = ENABLE_CHAT;

if ( ! is_user_logged_in() ) {
    echo json_encode( response_login_required( $vars['view'] ?? 'dropdown' ) );
    wp_die();
}

$view          = sanitize_key( $vars['view'] ?? 'dropdown' );
$page          = max( 1, (int) ( $vars['page'] ?? 1 ) );
$per_page      = max( 1, (int) ( $vars['posts_per_page'] ?? 15 ) );

$raw_messages  = Messenger::notifications();
$total         = count( $raw_messages );
$offset        = ( $page - 1 ) * $per_page;
$paged         = array_slice( $raw_messages, $offset, $per_page );
$page_total    = $total > 0 ? (int) ceil( $total / $per_page ) : 1;

// ── Twig-friendly format ──────────────────────────────────────────────────
$items = [];
foreach ( $paged as $msg ) {
    $items[] = [
        'id'      => $msg->id ?? null,
        'title'   => $msg->title ?? '',
        'message' => $msg->message ?? strip_tags( $msg->body ?? '' ),
        'time'    => $msg->time ?? $msg->created_at ?? '',
        'url'     => $msg->url ?? get_account_endpoint_url( 'messages' ),
        'read'    => (bool) ( $msg->status ?? false ),
        'sender'  => [
            'name'  => $msg->sender_name ?? $msg->name ?? '',
            'image' => $msg->sender_image ?? $msg->image ?? '',
        ],
    ];
}

$context           = Timber::context();
$context['source'] = 'messages';
$context['view']   = $view;
$context['items']  = $items;
// Standart zarf (convention): count = toplam mesaj, unread_count = okunmamış (Messenger::count()).
$unread_count = (int) Messenger::count();
$context['meta']   = [
    'count'        => (int) $total,
    'page'         => (int) $page,
    'page_total'   => (int) $page_total,
    'per_page'     => (int) $per_page,
    'unread_count' => $unread_count,
];

$response['data'] = [
    'count'        => (int) $total,
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
        $html .= Timber::compile( 'partials/source/messages/tease.twig', array_merge( $context, [ 'item' => $item ] ) );
    }
    $response['html'] = $html;
} else {
    $response['html'] = Timber::compile(
        [ "partials/source/messages/archive-{$view}.twig", 'partials/source/messages/archive.twig' ],
        $context
    );
}

echo json_encode( $response );
wp_die();
