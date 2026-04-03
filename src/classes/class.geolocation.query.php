<?php

/**
 * GeoLocation_Query
 * Find posts near a lat/lng coordinate within a given radius.
 * Uses Haversine formula for distance calculation.
 *
 * @version 1.0.0
 *
 * @changelog
 *   1.0.0 - 2026-04-03
 *     - Add: Initial versioned release
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // Basic: find published posts within 10 km of a point
 * $posts = GeoLocation_Query(41.0082, 28.9784);
 *
 * // Custom post type, 25 km radius, max 20 results
 * $posts = GeoLocation_Query(41.0082, 28.9784, 'store', 25, 20);
 *
 * // With Timber custom class (v2 uses ClassMap, no need to pass class name)
 * $posts = GeoLocation_Query(41.0082, 28.9784, 'hotel', 50, 100);
 *
 * // Each returned post has a ->distance property (in km)
 * foreach ($posts as $post) {
 *     echo $post->title . ' — ' . round($post->distance, 1) . ' km';
 * }
 *
 * // Custom meta keys (if your lat/lng fields are named differently)
 * $posts = GeoLocation_Query(41.0082, 28.9784, 'store', 25, 20, 'latitude', 'longitude');
 *
 * // Unit: default is 'km'. Use 'miles' for miles.
 * $posts = GeoLocation_Query(41.0082, 28.9784, 'store', 25, 20, 'lat', 'lon', 'miles');
 *
 * ──────────────────────────────────────────────────────────
 */

function GeoLocation_Query(
    $lat,
    $lon,
    $post_type = 'post',
    $distance = 5,
    $limit = 100,
    $lat_key = 'lat',
    $lon_key = 'lon',
    $unit = 'km'
) {
    global $wpdb;

    if (empty($lat) || empty($lon) || !is_numeric($lat) || !is_numeric($lon)) {
        return [];
    }

    $lat = (float) $lat;
    $lon = (float) $lon;
    $distance = (float) $distance;
    $limit = (int) $limit;

    // Haversine formula earth radius
    $earth_radius = ($unit === 'miles') ? 3959 : 6371; // miles or km

    $sql = $wpdb->prepare(
        "SELECT DISTINCT
            p.ID,
            ( %f * ACOS(
                COS( RADIANS( %f ) )
                * COS( RADIANS( map_lat.meta_value ) )
                * COS( RADIANS( map_lon.meta_value ) - RADIANS( %f ) )
                + SIN( RADIANS( %f ) )
                * SIN( RADIANS( map_lat.meta_value ) )
            ) ) AS distance
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} map_lat ON p.ID = map_lat.post_id
        INNER JOIN {$wpdb->postmeta} map_lon ON p.ID = map_lon.post_id
        WHERE p.post_type = %s
            AND p.post_status = 'publish'
            AND map_lat.meta_key = %s
            AND map_lon.meta_key = %s
            AND map_lat.meta_value BETWEEN %f AND %f
            AND map_lon.meta_value BETWEEN %f AND %f
        HAVING distance < %f
        ORDER BY distance ASC
        LIMIT %d",
        $earth_radius,
        $lat,
        $lon,
        $lat,
        $post_type,
        $lat_key,
        $lon_key,
        // Bounding box pre-filter (rough filter before Haversine — huge performance gain)
        $lat - ($distance / 111),          // ~111 km per degree latitude
        $lat + ($distance / 111),
        $lon - ($distance / (111 * cos(deg2rad($lat)))),
        $lon + ($distance / (111 * cos(deg2rad($lat)))),
        $distance,
        $limit
    );

    $ids = $wpdb->get_results($sql);

    if (empty($ids)) return [];

    // Build distance lookup map (ID → distance)
    $distance_map = [];
    foreach ($ids as $row) {
        $distance_map[(int) $row->ID] = (float) $row->distance;
    }

    $post_ids = array_keys($distance_map);

    $args = [
        'post_type'      => $post_type,
        'post__in'       => $post_ids,
        'posts_per_page' => count($post_ids),
        'orderby'        => 'post__in',
    ];

    if (class_exists('Timber')) {
        $posts = \Timber::get_posts($args);
    } else {
        $posts = get_posts($args);
    }

    // Attach distance to each post (by ID, not by index — safe even if order changes)
    foreach ($posts as $post) {
        $id = is_object($post) ? ($post->ID ?? $post->id ?? 0) : 0;
        $post->distance = $distance_map[$id] ?? null;
    }

    return $posts;
}
