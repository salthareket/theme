<?php

namespace SaltHareket\Localization\Concerns;

/**
 * HandlesGeoQuery
 *
 * Yakın lokasyon sorgusu — Haversine formula.
 * Bounding box pre-filter ile performans optimize edilmiş.
 *
 * @version 1.0.0
 */
trait HandlesGeoQuery
{
    /**
     * Koordinata yakın postları getir.
     *
     * @param float  $lat       Enlem
     * @param float  $lng       Boylam
     * @param string $post_type Post type
     * @param float  $distance  Mesafe (km veya mil)
     * @param int    $limit     Maksimum sonuç
     * @param string $lat_key   Lat meta key
     * @param string $lng_key   Lng meta key
     * @param string $unit      'km' veya 'miles'
     * @return array            Timber/WP post'ları, her birinde ->distance property
     */
    public function getNearestLocations(
        float  $lat,
        float  $lng,
        string $post_type = 'post',
        float  $distance  = 5,
        int    $limit     = 100,
        string $lat_key   = 'lat',
        string $lng_key   = 'lon',
        string $unit      = 'km'
    ): array {
        global $wpdb;

        if ( ! $lat || ! $lng ) return [];

        $earth_radius = $unit === 'miles' ? 3959 : 6371;

        // Bounding box pre-filter (SQL'de ön filtreleme — büyük performans kazancı)
        $lat_delta = $distance / 111;
        $lng_delta = $distance / ( 111 * cos( deg2rad( $lat ) ) );

        $sql = $wpdb->prepare(
            "SELECT DISTINCT p.ID,
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
            $lat, $lng, $lat,
            $post_type,
            $lat_key, $lng_key,
            $lat - $lat_delta, $lat + $lat_delta,
            $lng - $lng_delta, $lng + $lng_delta,
            $distance,
            $limit
        );

        $rows = $wpdb->get_results( $sql );
        if ( empty( $rows ) ) return [];

        // Distance map: ID → distance
        $distance_map = [];
        foreach ( $rows as $row ) {
            $distance_map[ (int) $row->ID ] = (float) $row->distance;
        }

        $post_ids = array_keys( $distance_map );

        $args = [
            'post_type'      => $post_type,
            'post__in'       => $post_ids,
            'posts_per_page' => count( $post_ids ),
            'orderby'        => 'post__in',
        ];

        $posts = class_exists( 'Timber' )
            ? \Timber\Timber::get_posts( $args )
            : get_posts( $args );

        // Her post'a distance property ekle
        foreach ( $posts as $post ) {
            $id            = is_object( $post ) ? ( $post->ID ?? $post->id ?? 0 ) : 0;
            $post->distance = round( $distance_map[ $id ] ?? 0, 2 );
        }

        return $posts;
    }
}
