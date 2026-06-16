<?php
$required_setting = ENABLE_NOTIFICATIONS;

if ( ! is_user_logged_in() ) {
    $response['error']   = true;
    $response['message'] = 'Not logged in';
    echo json_encode( $response );
    wp_die();
}

$user          = Timber::get_user( wp_get_current_user() );
$notifications = new Notifications( $user );
$view          = $vars['view'] ?? '';

// ── Offcanvas modu: sadece okunmamışları al, okundu işaretle ──────────────
if ( $view === 'offcanvas' ) {
    $posts   = $notifications->get_unseen_notifications();
    $context = Timber::context();
    $context['type']  = 'notifications';
    $context['posts'] = $posts;
    $response['data'] = [ 'count' => 0 ];
    $response['html'] = Timber::compile( 'partials/offcanvas/archive.twig', $context );
    echo json_encode( $response );
    wp_die();
}

// ── my-account / ajax-paginate modu: tüm bildirimleri paginate ile al ─────
$set_seen = ! empty( $vars['set_seen'] );
$result   = $notifications->get_notifications( array_merge( $vars, [ 'set_seen' => $set_seen ] ) );
$posts    = $result['posts'] ?? [];
$html     = '';
$timeAgo  = class_exists( '\\Westsworld\\TimeAgo' ) ? new \Westsworld\TimeAgo() : null;

foreach ( $posts as $row ) {
    $sender = new User( $row->sender_id );
    $url    = '';

    if ( function_exists( 'notification_url_map' ) ) {
        $ndata   = json_decode( $row->data ?? '{}', true );
        $post_id = (int) ( $ndata['post_id'] ?? 0 );
        $user_id = (int) ( $ndata['user_id'] ?? 0 );
        $url     = notification_url_map( $row->event, $post_id, $user_id );
    }

    $time = $row->created_at;
    if ( $timeAgo && method_exists( $user, 'get_local_date' ) ) {
        $time = $timeAgo->inWordsFromStrings(
            $user->get_local_date( $row->created_at, $sender->get_timezone(), $user->get_timezone() )
        );
    }

    $ctx         = Timber::context();
    $ctx['post'] = [
        'id'      => $row->id,
        'status'  => $row->status,
        'event'   => $row->event,
        'message' => strip_tags( $row->message ),
        'url'     => $url ?: '#',
        'time'    => $time,
        'sender'  => [
            'image' => get_avatar( $sender->ID, 40, 'mystery', $sender->get_title() ),
            'name'  => $sender->get_title(),
        ],
    ];
    $ctx['type'] = 'notifications';
    $html       .= Timber::compile( 'my-account/notification-item.twig', $ctx );
}

$response['data'] = $result['data'] ?? [];
$response['html'] = $html;
echo json_encode( $response );
wp_die();
