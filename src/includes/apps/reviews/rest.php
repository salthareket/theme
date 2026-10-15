<?php

/**
 * Reviews REST API
 *
 * Endpoint'ler: salt/v1/reviews/*
 * Self-contained — tüm endpoint tanımları ve callback'ler burada.
 *
 * @package SaltHareket\Reviews
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-06-26
 *     - Add: GET/POST /reviews      — review listele / yeni review oluştur
 *     - Add: POST /reviews/{id}/vote — helpful/unhelpful oyu
 *     - Add: GET /reviews/stats      — rating istatistikleri
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', function () {

    // ── Review listele + oluştur ──────────────────────────────────────────────
    register_rest_route( 'salt/v1', '/reviews', [
        [
            'methods'             => 'GET',
            'callback'            => 'salt_reviews_list',
            'permission_callback' => '__return_true',
            'args'                => [
                'target_id'   => [ 'required' => true,  'sanitize_callback' => 'absint' ],
                'target_type' => [ 'default'  => 'post', 'sanitize_callback' => 'sanitize_text_field' ],
                'page'        => [ 'default'  => 1,      'sanitize_callback' => 'absint' ],
                'per_page'    => [ 'default'  => 10,     'sanitize_callback' => 'absint' ],
                'sort'        => [ 'default'  => 'recent','sanitize_callback' => 'sanitize_text_field' ],
                'verified'    => [ 'default'  => false ],
            ],
        ],
        [
            'methods'             => 'POST',
            'callback'            => 'salt_reviews_create',
            'permission_callback' => 'is_user_logged_in',
        ],
    ] );

    // ── Oy ver ────────────────────────────────────────────────────────────────
    register_rest_route( 'salt/v1', '/reviews/(?P<id>\d+)/vote', [
        'methods'             => 'POST',
        'callback'            => 'salt_reviews_vote',
        'permission_callback' => 'is_user_logged_in',
        'args'                => [
            'id'   => [ 'required' => true, 'sanitize_callback' => 'absint' ],
            'type' => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
        ],
    ] );

    // ── Rating istatistikleri ─────────────────────────────────────────────────
    register_rest_route( 'salt/v1', '/reviews/stats', [
        'methods'             => 'GET',
        'callback'            => 'salt_reviews_stats',
        'permission_callback' => '__return_true',
        'args'                => [
            'target_id'   => [ 'required' => true,  'sanitize_callback' => 'absint' ],
            'target_type' => [ 'default'  => 'post', 'sanitize_callback' => 'sanitize_text_field' ],
        ],
    ] );

} );

// ─── CALLBACKS ───────────────────────────────────────────────────────────────

/**
 * GET /reviews
 * Belirli bir post/kullanıcıya ait review'ları sayfalı döndürür.
 */
function salt_reviews_list( WP_REST_Request $r ): WP_REST_Response {
    $tid  = $r->get_param( 'target_id' );
    $tt   = $r->get_param( 'target_type' );
    $page = max( 1, $r->get_param( 'page' ) );
    $pp   = min( 50, max( 1, $r->get_param( 'per_page' ) ) );
    $sort = $r->get_param( 'sort' );
    $ver  = filter_var( $r->get_param( 'verified' ), FILTER_VALIDATE_BOOLEAN );

    // Sıralama
    $orderby = 'comment_date';
    $order   = 'DESC';
    if ( $sort === 'rating_high' ) { $orderby = 'meta_value_num'; $order = 'DESC'; }
    if ( $sort === 'rating_low' )  { $orderby = 'meta_value_num'; $order = 'ASC'; }

    $args = [
        'post_id'    => $tt === 'post' ? $tid : 0,
        'type'       => 'review',
        'status'     => 'approve',
        'number'     => $pp,
        'offset'     => ( $page - 1 ) * $pp,
        'orderby'    => $orderby,
        'order'      => $order,
        'meta_query' => [],
    ];

    if ( $tt === 'user' ) {
        $args['post_id']       = 0;
        $args['meta_query'][]  = [ 'key' => 'comment_profile', 'value' => $tid, 'compare' => '=' ];
    }

    if ( $ver ) {
        $args['meta_query'][] = [ 'key' => 'verified', 'value' => '1', 'compare' => '=' ];
    }

    $comments = get_comments( $args );
    $total    = (int) get_comments( array_merge( $args, [ 'count' => true, 'number' => 0, 'offset' => 0 ] ) );

    $reviews = array_map( function ( $c ) {
        $images_raw = get_comment_meta( $c->comment_ID, 'review_images', true );
        $images     = is_array( $images_raw )
            ? array_map( fn( $id ) => wp_get_attachment_image_url( $id, 'medium' ), $images_raw )
            : [];

        return [
            'id'       => (int) $c->comment_ID,
            'author'   => $c->comment_author,
            'avatar'   => get_avatar_url( $c->comment_author_email, [ 'size' => 48 ] ),
            'rating'   => (int) get_comment_meta( $c->comment_ID, 'rating', true ),
            'title'    => get_comment_meta( $c->comment_ID, 'review_title', true ) ?: '',
            'content'  => $c->comment_content,
            'date'     => $c->comment_date,
            'verified' => (bool) get_comment_meta( $c->comment_ID, 'verified', true ),
            'helpful'  => (int) get_comment_meta( $c->comment_ID, 'helpful_count', true ),
            'images'   => $images,
        ];
    }, $comments );

    return new WP_REST_Response( [
        'reviews'     => $reviews,
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $pp,
        'total_pages' => (int) ceil( $total / $pp ),
    ], 200 );
}

/**
 * POST /reviews
 * Yeni review oluşturur. Giriş yapmış kullanıcı gerekli.
 */
function salt_reviews_create( WP_REST_Request $r ): WP_REST_Response {
    $uid     = get_current_user_id();
    $tid     = absint( $r->get_param( 'target_id' ) );
    $rating  = min( 5, max( 1, absint( $r->get_param( 'rating' ) ) ) );
    $content = sanitize_textarea_field( $r->get_param( 'content' ) );
    $title   = sanitize_text_field( $r->get_param( 'title' ) );
    $tt      = sanitize_text_field( $r->get_param( 'target_type' ) ?: 'post' );

    if ( ! $tid || ! $content ) {
        return new WP_REST_Response( [ 'success' => false, 'message' => 'Eksik alanlar.' ], 400 );
    }

    // Rate limit — aynı kullanıcı aynı içeriğe 10 dakika içinde tekrar yorum yapamaz
    $tk = 'review_limit_' . $uid . '_' . $tid;
    if ( get_transient( $tk ) ) {
        return new WP_REST_Response( [ 'success' => false, 'message' => 'Çok sık yorum yapıyorsunuz.' ], 429 );
    }

    $user = get_userdata( $uid );
    $cid  = wp_insert_comment( [
        'comment_post_ID'      => $tt === 'post' ? $tid : 0,
        'comment_author'       => $user->display_name,
        'comment_author_email' => $user->user_email,
        'comment_content'      => $content,
        'comment_type'         => 'review',
        'comment_approved'     => 0,
        'user_id'              => $uid,
    ] );

    if ( ! $cid ) {
        return new WP_REST_Response( [ 'success' => false, 'message' => 'Yorum kaydedilemedi.' ], 500 );
    }

    update_comment_meta( $cid, 'rating', $rating );
    if ( $title ) update_comment_meta( $cid, 'review_title', $title );
    if ( $tt === 'user' ) update_comment_meta( $cid, 'comment_profile', $tid );

    // WooCommerce verified satın alma kontrolü
    if ( function_exists( 'wc_customer_bought_product' ) && $tt === 'post' ) {
        $verified = wc_customer_bought_product( $user->user_email, $uid, $tid ) ? '1' : '0';
        update_comment_meta( $cid, 'verified', $verified );
    }

    set_transient( $tk, 1, 10 * MINUTE_IN_SECONDS );

    return new WP_REST_Response( [
        'success' => true,
        'id'      => $cid,
        'message' => 'Yorumunuz onay bekliyor.',
    ], 201 );
}

/**
 * POST /reviews/{id}/vote
 * Review'a helpful/unhelpful oyu verir.
 */
function salt_reviews_vote( WP_REST_Request $r ): WP_REST_Response {
    $cid  = absint( $r->get_param( 'id' ) );
    $type = sanitize_text_field( $r->get_param( 'type' ) );
    $uid  = get_current_user_id();

    if ( ! in_array( $type, [ 'helpful', 'unhelpful' ], true ) ) {
        return new WP_REST_Response( [ 'success' => false, 'message' => 'Geçersiz oy tipi.' ], 400 );
    }

    $voted_key = "review_voted_{$type}_{$uid}";
    if ( get_comment_meta( $cid, $voted_key, true ) ) {
        return new WP_REST_Response( [ 'success' => false, 'message' => 'Zaten oy verdiniz.' ], 409 );
    }

    $count_key = "{$type}_count";
    $current   = (int) get_comment_meta( $cid, $count_key, true );

    update_comment_meta( $cid, $count_key, $current + 1 );
    update_comment_meta( $cid, $voted_key, 1 );

    return new WP_REST_Response( [ 'success' => true, 'count' => $current + 1 ], 200 );
}

/**
 * GET /reviews/stats
 * Belirli bir post/kullanıcının rating dağılımını döndürür.
 */
function salt_reviews_stats( WP_REST_Request $r ): WP_REST_Response {
    $tid = $r->get_param( 'target_id' );
    $tt  = $r->get_param( 'target_type' );

    $ids = get_comments( [
        'post_id' => $tt === 'post' ? $tid : 0,
        'type'    => 'review',
        'status'  => 'approve',
        'fields'  => 'ids',
        'number'  => 0,
    ] );

    if ( empty( $ids ) ) {
        return new WP_REST_Response( [ 'total' => 0, 'average' => 0, 'breakdown' => [] ], 200 );
    }

    $breakdown = [ 5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0 ];
    $sum       = 0;

    foreach ( $ids as $id ) {
        $rv = (int) get_comment_meta( $id, 'rating', true );
        if ( $rv >= 1 && $rv <= 5 ) {
            $breakdown[ $rv ]++;
            $sum += $rv;
        }
    }

    $total = count( $ids );

    return new WP_REST_Response( [
        'total'     => $total,
        'average'   => $total > 0 ? round( $sum / $total, 1 ) : 0,
        'breakdown' => $breakdown,
    ], 200 );
}
