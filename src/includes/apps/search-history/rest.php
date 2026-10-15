<?php

/**
 * Search History REST API
 *
 * Endpoint'ler: salt/v1/search/*
 * Self-contained — tüm endpoint tanımları ve callback'ler burada.
 *
 * @package SaltHareket\SearchHistory
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-06-26
 *     - Add: GET /search/popular  — popüler arama terimleri
 *     - Add: GET /search/trending — trend arama terimleri
 *     - Add: POST /search/track   — arama terimi kaydet
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', function () {

    register_rest_route( 'salt/v1', '/search/popular', [
        'methods'             => 'GET',
        'callback'            => 'salt_sh_popular',
        'permission_callback' => '__return_true',
        'args'                => [
            'type'  => [ 'default' => 'product', 'sanitize_callback' => 'sanitize_text_field' ],
            'limit' => [ 'default' => 10,         'sanitize_callback' => 'absint' ],
        ],
    ] );

    register_rest_route( 'salt/v1', '/search/trending', [
        'methods'             => 'GET',
        'callback'            => 'salt_sh_trending',
        'permission_callback' => '__return_true',
        'args'                => [
            'days'  => [ 'default' => 7,  'sanitize_callback' => 'absint' ],
            'limit' => [ 'default' => 10, 'sanitize_callback' => 'absint' ],
        ],
    ] );

    register_rest_route( 'salt/v1', '/search/track', [
        'methods'             => 'POST',
        'callback'            => 'salt_sh_track',
        'permission_callback' => '__return_true',
        'args'                => [
            'term'       => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
            'type'       => [ 'default'  => 'product', 'sanitize_callback' => 'sanitize_text_field' ],
            'no_results' => [ 'default'  => false ],
        ],
    ] );

} );

// ─── CALLBACKS ───────────────────────────────────────────────────────────────

/**
 * GET /search/popular
 * Belirli bir post type için en çok aranan terimleri döndürür.
 */
function salt_sh_popular( WP_REST_Request $r ): WP_REST_Response {
    $type  = $r->get_param( 'type' );
    $limit = min( 50, max( 1, $r->get_param( 'limit' ) ) );

    if ( class_exists( 'SearchHistory' ) ) {
        $sh = new SearchHistory();
        return new WP_REST_Response( [ 'terms' => $sh->get_popular_terms( $type, $limit ) ], 200 );
    }

    // Fallback — doğrudan DB sorgusu
    global $wpdb;
    $table = $wpdb->prefix . 'search_terms';

    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
        return new WP_REST_Response( [ 'terms' => [] ], 200 );
    }

    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT term, SUM(count) as total FROM {$table} WHERE type=%s GROUP BY term ORDER BY total DESC LIMIT %d",
        $type, $limit
    ) );

    return new WP_REST_Response( [
        'terms' => array_map( fn( $row ) => [ 'term' => $row->term, 'count' => (int) $row->total ], $rows ),
    ], 200 );
}

/**
 * GET /search/trending
 * Son N gün içindeki trend terimleri döndürür.
 */
function salt_sh_trending( WP_REST_Request $r ): WP_REST_Response {
    $days  = min( 30, max( 1, $r->get_param( 'days' ) ) );
    $limit = min( 50, max( 1, $r->get_param( 'limit' ) ) );

    if ( class_exists( 'SearchHistory' ) ) {
        $sh = new SearchHistory();
        return new WP_REST_Response( [ 'terms' => $sh->get_trending_terms( $days, $limit ) ], 200 );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'search_terms';

    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
        return new WP_REST_Response( [ 'terms' => [] ], 200 );
    }

    $since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
    $rows  = $wpdb->get_results( $wpdb->prepare(
        "SELECT term, SUM(count) as total FROM {$table} WHERE created_at >= %s GROUP BY term ORDER BY total DESC LIMIT %d",
        $since, $limit
    ) );

    return new WP_REST_Response( [
        'terms' => array_map( fn( $row ) => [ 'term' => $row->term, 'count' => (int) $row->total ], $rows ),
    ], 200 );
}

/**
 * POST /search/track
 * Arama terimini kaydeder. Rate limit yok — frontend'den freely çağrılabilir.
 */
function salt_sh_track( WP_REST_Request $r ): WP_REST_Response {
    $term = trim( $r->get_param( 'term' ) );
    $type = $r->get_param( 'type' );
    $nr   = filter_var( $r->get_param( 'no_results' ), FILTER_VALIDATE_BOOLEAN );

    if ( strlen( $term ) < 2 ) {
        return new WP_REST_Response( [ 'success' => false, 'message' => 'Terim çok kısa.' ], 400 );
    }

    if ( class_exists( 'SearchHistory' ) ) {
        $sh = new SearchHistory();
        $sh->set_term( $term, $type, $nr );
        return new WP_REST_Response( [ 'success' => true ], 200 );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'search_terms';

    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
        return new WP_REST_Response( [ 'success' => false, 'message' => 'Tablo bulunamadı.' ], 503 );
    }

    $existing = $wpdb->get_row( $wpdb->prepare(
        "SELECT id, count FROM {$table} WHERE term=%s AND type=%s", $term, $type
    ) );

    if ( $existing ) {
        $wpdb->update(
            $table,
            [ 'count' => $existing->count + 1, 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ],
            [ 'id' => $existing->id ]
        );
    } else {
        $wpdb->insert( $table, [
            'term'       => $term,
            'type'       => $type,
            'count'      => 1,
            'no_results' => $nr ? 1 : 0,
            'created_at' => gmdate( 'Y-m-d H:i:s' ),
            'updated_at' => gmdate( 'Y-m-d H:i:s' ),
        ] );
    }

    return new WP_REST_Response( [ 'success' => true ], 200 );
}
