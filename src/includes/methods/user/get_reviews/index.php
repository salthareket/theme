<?php
$required_setting = true;

if ( ! is_user_logged_in() ) {
    $response['error']   = true;
    $response['message'] = 'Not logged in';
    echo json_encode( $response );
    wp_die();
}

if ( ! class_exists( 'Reviews' ) ) {
    $response['error']   = true;
    $response['message'] = 'Reviews not available';
    echo json_encode( $response );
    wp_die();
}

$user_id  = (int) ( $vars['user'] ?? get_current_user_id() );
$reviews  = new Reviews( $user_id );

$args = [
    'page'     => (int) ( $vars['page']           ?? 1 ),
    'per_page' => (int) ( $vars['posts_per_page']  ?? 10 ),
    'order'    => sanitize_key( $vars['order']     ?? 'desc' ),
    'status'   => isset( $vars['status'] ) && $vars['status'] === '0' ? 'hold' : 'approve',
];

$result = $reviews->getByAuthor( $user_id, $args );
$posts  = $result['reviews'] ?? [];
$html   = '';

foreach ( $posts as $review ) {
    $ctx                    = Timber::context();
    $ctx['review']          = $review;
    $ctx['reviews_settings'] = \SaltHareket\Reviews\ReviewsSettings::all();
    $ctx['reviews_criteria'] = \SaltHareket\Reviews\ReviewsSettings::getCriteria(
        get_post_type( $review->comment_post_ID ?? 0 ) ?: 'post'
    );
    $html .= Timber::compile( 'my-account/review-item.twig', $ctx );
}

$response['data'] = $result['data'] ?? [];
$response['html'] = $html;
echo json_encode( $response );
wp_die();
