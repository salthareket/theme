<?php
/**
 * get_reactions — Kullanıcının belirli bir reaction tipindeki içeriklerini döndürür.
 *
 * vars:
 *   reaction_type  — favorite | like | follow | bookmark | clap | ...
 *   object_type    — post | user | term | comment  (default: post)
 *   view           — offcanvas | dropdown | modal   (default: dropdown)
 *   page           — sayfa numarası (default: 1)
 *   posts_per_page — sayfa başı öğe (default: 12)
 *   post_type      — (object_type=post)    post type filtresi (örn: product)
 *   comment_type   — (object_type=comment) comment type filtresi (örn: review)
 */
$required_setting = ENABLE_REACTIONS;

if ( ! is_user_logged_in() ) {
    echo json_encode( response_login_required( $vars['view'] ?? 'dropdown' ) );
    wp_die();
}

$reaction_type = sanitize_key( $vars['reaction_type'] ?? 'favorite' );
$object_type   = sanitize_key( $vars['object_type']   ?? 'post' );
$view          = sanitize_key( $vars['view']           ?? 'dropdown' );
$page          = max( 1, (int) ( $vars['page']           ?? 1 ) );
$per_page      = max( 1, (int) ( $vars['posts_per_page'] ?? 12 ) );
$user_id       = get_current_user_id();

// ── Subtype filtresi — object_type'a göre doğru filtreyi seç ─────────────
$post_type_filter    = sanitize_key( $vars['post_type']    ?? '' );
$comment_type_filter = sanitize_key( $vars['comment_type'] ?? '' );

$object_subtype_filter = '';
if ( $object_type === 'post' && $post_type_filter !== '' ) {
    $object_subtype_filter = $post_type_filter;
} elseif ( $object_type === 'comment' && $comment_type_filter !== '' ) {
    $object_subtype_filter = $comment_type_filter;
}

// ── Reaction ID'lerini çek (subtype filtresiyle) ──────────────────────────
$all_ids = \SaltHareket\Reactions\Reactions::getByUser( $user_id, $reaction_type, $object_type, $object_subtype_filter );
$total   = count( $all_ids );

// Paginate
$offset     = ( $page - 1 ) * $per_page;
$paged_ids  = array_slice( $all_ids, $offset, $per_page );
$page_total = $total > 0 ? (int) ceil( $total / $per_page ) : 1;

// ── Object'leri yükle ─────────────────────────────────────────────────────
$items = [];

if ( ! empty( $paged_ids ) ) {
    if ( $object_type === 'post' ) {
        // product seçilmişse product_variation'ı da dahil et
        $query_post_type = 'any';
        if ( $post_type_filter === 'product' ) {
            $query_post_type = [ 'product', 'product_variation' ];
        } elseif ( $post_type_filter !== '' ) {
            $query_post_type = $post_type_filter;
        }

        $timber_posts = Timber::get_posts( [
            'post__in'       => $paged_ids,
            'orderby'        => 'post__in',
            'posts_per_page' => $per_page,
            'post_type'      => $query_post_type,
            'post_status'    => 'publish',
            'lang'           => '', // Polylang: tüm dillerdeki postları getir
        ] );
        if ( $timber_posts ) {
            foreach ( $timber_posts as $p ) {
                $items[] = $p;
            }
        }

    } elseif ( $object_type === 'user' ) {
        foreach ( $paged_ids as $uid ) {
            $timber_user = Timber::get_user( $uid );
            if ( $timber_user ) $items[] = $timber_user;
        }

    } elseif ( $object_type === 'term' ) {
        foreach ( $paged_ids as $term_id ) {
            $timber_term = Timber::get_term( $term_id );
            if ( $timber_term ) $items[] = $timber_term;
        }

    } elseif ( $object_type === 'comment' ) {
        foreach ( $paged_ids as $comment_id ) {
            $comment = get_comment( $comment_id );
            if ( $comment ) $items[] = $comment;
        }
    }
}

$context                     = Timber::context();
$context['source']           = 'reaction:' . $reaction_type;
$context['reaction_type']    = $reaction_type;
$context['object_type']      = $object_type;
$context['post_type_filter'] = $post_type_filter;     // pagination'da filtre korunur
$context['comment_type_filter'] = $comment_type_filter;
$context['view']             = $view;
$context['items']            = $items;
$context['meta']             = [
    'count'      => $total,
    'page'       => $page,
    'page_total' => $page_total,
    'per_page'   => $per_page,
];

// Standart zarf (convention): data = { count, page, page_total, per_page }.
// reaction'da "okunmamış" kavramı yok → unread_count YOK.
$response['data'] = [
    'count'      => (int) $total,
    'page'       => (int) $page,
    'page_total' => (int) $page_total,
    'per_page'   => (int) $per_page,
];

// Feed modu (my-account): sadece tease'ler. Popup (offcanvas/dropdown/modal): archive.
// archive-{view}.twig varsa onu, yoksa archive.twig'i kullan (view override fallback).
$popup_views = [ 'offcanvas', 'dropdown', 'modal' ];

if ( ! in_array( $view, $popup_views, true ) ) {
    $html = '';
    foreach ( $items as $item ) {
        $html .= Timber::compile( 'partials/source/reactions/tease.twig', array_merge( $context, [ 'item' => $item ] ) );
    }
    $response['html'] = $html;
} else {
    $response['html'] = Timber::compile(
        [ "partials/source/reactions/archive-{$view}.twig", 'partials/source/reactions/archive.twig' ],
        $context
    );
}

echo json_encode( $response );
wp_die();
