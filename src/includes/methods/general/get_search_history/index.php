<?php
/**
 * get_search_history — AJAX handler
 *
 * Kullanıcının son aramaları veya popüler aramaları döner.
 * Yeni format: { terms: string[], title: string }
 * Eski format (html) de desteklenir — geriye uyumluluk için.
 *
 * POST params:
 *   history   = 'user' | 'popular'
 *   post_type = 'search' | 'product' | ...
 *   lang      = '' | 'tr' | 'en' (opsiyonel, ML siteler için)
 *   format    = 'json' | 'html' (default: json)
 */

$required_setting = ENABLE_SEARCH_HISTORY;

$user         = is_user_logged_in() ? wp_get_current_user() : null;
$search_history = new SearchHistory();

$history_type = sanitize_key( $vars['history']   ?? 'popular' );
$post_type    = sanitize_key( $vars['post_type']  ?? 'search' );
$lang         = sanitize_key( $vars['lang']       ?? '' );
$format       = sanitize_key( $vars['format']     ?? 'json' );

if ( $history_type === 'popular' ) {
    $title  = trans( 'Popular search terms' );
    $result = $search_history->get_popular_terms( $post_type, 10, $lang );
} else {
    $title  = trans( 'Your last searches' );
    $result = $user
        ? $search_history->get_user_terms( $user->ID, $post_type, 10 )
        : [];
}

// Yeni format: terms array — JS dropdown tarafından render edilir
$response['terms'] = array_values( $result ?: [] );
$response['title'] = $title;
$response['type']  = $history_type;

// Geriye uyumluluk: format=html isterse eski twig'i de derle
if ( $format === 'html' && ! empty( $result ) ) {
    $context                 = Timber::context();
    $context['title']        = $title;
    $context['search_terms'] = $result;
    $context['vars']         = $vars;
    $response['html'] = Timber::compile( 'partials/snippets/search-field-history.twig', $context );
}

echo json_encode( $response );
wp_die();
