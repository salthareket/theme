<?php

/**
 * Localization App Bootstrap
 *
 * variables.php'de şu şekilde include edilir:
 *
 *   if (ENABLE_IP2COUNTRY || ENABLE_LOCATION_DB || $is_admin) {
 *       include_once SH_INCLUDES_PATH . 'apps/localization/bootstrap.php';
 *   }
 *
 * @version 2.0.0
 * @changelog
 *   2.0.0 - 2026-06-16
 *     - Add: REST API endpoint'leri (/ip, /countries, /states, /cities, /postcodes)
 *     - Add: REST'te location_data_source dikkate alınır (package → WC, database → DB)
 *     - Change: Regional posts hook — LocationSettings'den fallback (constant olmasa da çalışır)
 *     - Change: Twig/global helper get_cities → getCitiesList (cities DB tablosu yok)
 *   1.0.0 - 2026-05-09 — Initial release
 */

namespace SaltHareket\Localization;

// ─── AUTOLOAD ────────────────────────────────────────────────────────────────

$loc_base = __DIR__ . '/';

require_once $loc_base . 'LocationSettings.php';
require_once $loc_base . 'Schema/LocationSchema.php';
require_once $loc_base . 'Concerns/HandlesGeo.php';
require_once $loc_base . 'Concerns/HandlesIpGeo.php';
require_once $loc_base . 'Concerns/HandlesRegionalPosts.php';
require_once $loc_base . 'Concerns/HandlesGeoQuery.php';
require_once $loc_base . 'LocationManager.php';
require_once $loc_base . 'Admin/LocalizationAdmin.php';

// ─── INIT ────────────────────────────────────────────────────────────────────

// Admin sayfası
Admin\LocalizationAdmin::register();

// DB tablolarını kur (admin_init'te — tablo garantili hazır olur)
add_action( 'admin_init', function () {
    static $done = false;
    if ( $done ) return;
    $done = true;
    Schema\LocationSchema::install();
}, 5 );

// Regional posts hook'larını register et
if ( LocationManager::isRegionalActive() ) {
    LocationManager::getInstance()->registerRegionalPosts();
}

// ─── REST API ────────────────────────────────────────────────────────────────

add_action( 'rest_api_init', function () {

    $lm = LocationManager::getInstance();

    // ── IP → Geo ─────────────────────────────────────────────────────────────
    register_rest_route( 'salt/v1', '/localization/ip', [
        'methods'             => 'GET',
        'callback'            => function ( \WP_REST_Request $r ) use ( $lm ) {
            $ip      = sanitize_text_field( $r->get_param( 'ip' ) ?: '' );
            $result  = $lm->ipInfo( $ip ?: null );

            if ( $result ) {
                return new \WP_REST_Response( $result, 200 );
            }

            // ip-api.com fallback
            $target = $ip ?: ( $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '' );
            $target = trim( explode( ',', $target )[0] );

            if ( ! $target || in_array( $target, [ '127.0.0.1', '::1' ], true ) ) {
                return new \WP_REST_Response( [ 'error' => 'local_ip', 'message' => 'Local IP detected' ], 200 );
            }

            $api_url  = 'http://ip-api.com/json/' . urlencode( $target ) . '?fields=status,country,countryCode,regionName,region,city,lat,lon,timezone,currency';
            $response = wp_remote_get( $api_url, [ 'timeout' => 5 ] );

            if ( is_wp_error( $response ) ) {
                return new \WP_REST_Response( [], 200 );
            }

            $data = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( empty( $data ) || ( $data['status'] ?? '' ) !== 'success' ) {
                return new \WP_REST_Response( [], 200 );
            }

            return new \WP_REST_Response( [
                'ip'           => $target,
                'iso2'         => $data['countryCode'] ?? '',
                'country_code' => $data['countryCode'] ?? '',
                'name'         => $data['country']     ?? '',
                'city'         => $data['city']         ?? '',
                'state'        => $data['regionName']   ?? '',
                'region'       => $data['regionName']   ?? '',
                'lat'          => $data['lat']           ?? 0,
                'lng'          => $data['lon']           ?? 0,
                'timezone'     => $data['timezone']     ?? '',
                'currency'     => $data['currency']     ?? '',
            ], 200 );
        },
        'permission_callback' => '__return_true',
    ] );

    // ── Countries ─────────────────────────────────────────────────────────────
    register_rest_route( 'salt/v1', '/localization/countries', [
        'methods'             => 'GET',
        'callback'            => function ( \WP_REST_Request $r ) use ( $lm ) {
            $continent = strtoupper( sanitize_text_field( $r->get_param( 'continent' ) ?? '' ) );
            $list      = $lm->getCountriesList( $continent, '', true );

            // Flat array formatına dönüştür
            $result = [];
            foreach ( $list as $group ) {
                if ( isset( $group['children'] ) ) {
                    foreach ( $group['children'] as $c ) {
                        if ( $c['slug'] ) {
                            $result[] = [ 'code' => $c['slug'], 'name' => $c['name'], 'continent' => $group['name'] ?? '' ];
                        }
                    }
                } elseif ( isset( $group['slug'] ) && $group['slug'] ) {
                    $result[] = [ 'code' => $group['slug'], 'name' => $group['name'] ];
                }
            }
            return new \WP_REST_Response( $result, 200 );
        },
        'permission_callback' => '__return_true',
    ] );

    // ── States ────────────────────────────────────────────────────────────────
    register_rest_route( 'salt/v1', '/localization/states', [
        'methods'             => 'GET',
        'callback'            => function ( \WP_REST_Request $r ) use ( $lm ) {
            $country = strtoupper( sanitize_text_field( $r->get_param( 'country' ) ?? '' ) );
            if ( ! $country ) return new \WP_REST_Response( [], 200 );

            $states = $lm->states( [ 'country_code' => $country ] );
            $result = [];
            foreach ( $states as $s ) {
                $result[] = [
                    'code'        => $s['iso2'] ?? '',
                    'name'        => $s['name'] ?? '',
                    'countryCode' => $s['country_code'] ?? $country,
                    'lat'         => (float) ( $s['latitude']  ?? 0 ),
                    'lng'         => (float) ( $s['longitude'] ?? 0 ),
                ];
            }
            return new \WP_REST_Response( $result, 200 );
        },
        'permission_callback' => '__return_true',
    ] );

    // ── Cities (states alias — cities tablosu yok) ─────────────────────────
    register_rest_route( 'salt/v1', '/localization/cities', [
        'methods'             => 'GET',
        'callback'            => function ( \WP_REST_Request $r ) use ( $lm ) {
            $country = strtoupper( sanitize_text_field( $r->get_param( 'country' ) ?? '' ) );
            if ( ! $country ) return new \WP_REST_Response( [], 200 );
            // cities tablosu yok — states listesini döndür
            $states = $lm->states( [ 'country_code' => $country ] );
            $result = [];
            foreach ( $states as $s ) {
                $result[] = [
                    'code'        => $s['iso2'] ?? '',
                    'name'        => $s['name'] ?? '',
                    'countryCode' => $s['country_code'] ?? $country,
                    'lat'         => (float) ( $s['latitude']  ?? 0 ),
                    'lng'         => (float) ( $s['longitude'] ?? 0 ),
                ];
            }
            return new \WP_REST_Response( $result, 200 );
        },
        'permission_callback' => '__return_true',
    ] );

    // ── Postcodes ─────────────────────────────────────────────────────────────
    register_rest_route( 'salt/v1', '/localization/postcodes', [
        'methods'             => 'GET',
        'callback'            => function () {
            $file = SH_STATIC_PATH . 'data/postcodes.json';
            if ( ! file_exists( $file ) ) {
                return new \WP_REST_Response( [], 200 );
            }
            $data = json_decode( file_get_contents( $file ), true );
            return new \WP_REST_Response( $data ?: [], 200 );
        },
        'permission_callback' => '__return_true',
    ] );

} );

// ─── WP ROCKET ENTEGRASYONU ──────────────────────────────────────────────────
// Regional post type'lar ve taxonomy'leri WP Rocket cache'ten dışla.
// Mevcut WP_Rocket_Dynamic_Excludes class'ı bunu zaten yapıyor (wp-rocket.php).
// Burada sadece regional post type'ları dinamik olarak ekliyoruz.

add_filter( 'pre_get_rocket_option_cache_reject_uri', function ( $urls ) {
    if ( ! defined( 'ENABLE_REGIONAL_POSTS' ) || ! ENABLE_REGIONAL_POSTS ) return $urls;

    $lm    = LocationManager::getInstance();
    $types = $lm->getRegionalPostTypes();
    $taxes = $lm->getRegionalTaxonomies();

    foreach ( $types as $pt ) {
        $obj = get_post_type_object( $pt );
        if ( ! $obj ) continue;
        $slug  = $obj->rewrite['slug'] ?? $pt;
        $urls[] = '/' . trim( $slug, '/' ) . '/';       // archive
        $urls[] = '/' . trim( $slug, '/' ) . '/(.+)';   // single
    }

    foreach ( $taxes as $tax ) {
        $obj = get_taxonomy( $tax );
        if ( ! $obj ) continue;
        $slug  = $obj->rewrite['slug'] ?? $tax;
        $urls[] = '/' . trim( $slug, '/' ) . '/(.*)';   // all
    }

    return array_unique( array_filter( $urls ) );
}, 15 );

// ─── TURBO API AJAX ENDPOINTS ─────────────────────────────────────────────────

add_filter( 'turbo_api_handle', function ( $handled, string $method, array $vars ) {
    if ( $handled !== null ) return $handled;

    $lm = LocationManager::getInstance();

    switch ( $method ) {

        case 'change_localization':
            $path = function_exists( 'getSiteSubfolder' ) ? getSiteSubfolder() : '/';
            $ttl  = time() + YEAR_IN_SECONDS;

            if ( function_exists( 'qtranxf_setLanguage' ) ) {
                qtranxf_setLanguage( $vars['language'] ?? '' );
            }

            // Cookie'leri temizle ve yenile
            foreach ( [ 'user_country', 'user_country_code', 'user_language' ] as $c ) {
                setcookie( $c, '', time() - 3600, $path );
            }

            $country_code = sanitize_text_field( $vars['countryCode'] ?? '' );
            setcookie( 'user_country',      sanitize_text_field( $vars['country'] ?? '' ), $ttl, $path );
            setcookie( 'user_country_code', $country_code, $ttl, $path );
            setcookie( 'user_language',     sanitize_text_field( $vars['language'] ?? '' ), $ttl, $path );
            setcookie( 'user_region',       json_encode( $lm->getRegionByCountryCode( $country_code ) ), $ttl, $path );

            $redirect = '';
            if ( function_exists( 'qtrans_convert_url' ) ) {
                $redirect = qtrans_convert_url( $vars['language'] ?? '' );
            }

            return [ 'error' => false, 'redirect' => $redirect ];

        case 'get_city_options':
            $country = sanitize_text_field( $vars['country'] ?? '' );
            return $lm->states( [ 'country_code' => $country ], false );

        case 'get_country_options':
            return $lm->getCountriesList(
                sanitize_text_field( $vars['continent'] ?? '' ),
                sanitize_text_field( $vars['selected'] ?? '' ),
                ! empty( $vars['all'] )
            );

        case 'get_districts':
            return $lm->getDistrictsList( sanitize_text_field( $vars['city'] ?? '' ) );

        case 'get_available_districts':
            return $lm->getAvailableDistricts(
                sanitize_key( $vars['post_type'] ?? 'post' ),
                sanitize_text_field( $vars['city'] ?? '' )
            );

        case 'get_posts_by_city':
            return $lm->getAvailableCities(
                sanitize_key( $vars['post_type'] ?? 'post' ),
                'city',
                sanitize_text_field( $vars['city'] ?? '' )
            );

        case 'get_posts_by_district':
            $post_type = sanitize_key( $vars['post_type'] ?? 'post' );
            $data      = $lm->getPostsByDistrict(
                $post_type,
                sanitize_text_field( $vars['city'] ?? '' ),
                sanitize_text_field( $vars['district'] ?? '' )
            );
            $context          = \Timber\Timber::context();
            $context['vars']  = $vars;
            $context['data']  = $data;
            $html = \Timber\Timber::compile( [ $post_type . '/archive-ajax.twig' ], $context );
            return [ 'error' => false, 'html' => $html ];

        case 'get_nearest_locations':
            $locations = $lm->getNearestLocations(
                (float) ( $vars['lat'] ?? 0 ),
                (float) ( $vars['lng'] ?? 0 ),
                sanitize_key( $vars['post_type'] ?? 'post' ),
                (float) ( $vars['distance'] ?? 5 ),
                (int)   ( $vars['limit'] ?? 10 )
            );

            $output   = (array) ( $vars['output'] ?? [ 'posts' ] );
            $response = [ 'error' => false ];

            if ( in_array( 'posts', $output, true ) ) {
                $context          = \Timber\Timber::context();
                $context['posts'] = $locations;
                $response['html'] = \Timber\Timber::compile( ( $vars['template'] ?? 'archive' ) . '.twig', $context );
            }

            if ( in_array( 'markers', $output, true ) ) {
                $salt = \Salt::get_instance();
                $response['data'] = $salt->get_markers( $locations );
            }

            return $response;
    }

    return null;
}, 10, 3 );

// ─── GLOBAL WRAPPER FUNCTIONS (geriye uyumluluk) ─────────────────────────────

if ( ! function_exists( 'get_country_name' ) ) {
    function get_country_name( string $key = 'iso2', string $value = '' ): string {
        return \SaltHareket\Localization\LocationManager::getInstance()->getCountryName( $key, $value );
    }
}

if ( ! function_exists( 'get_city_name' ) ) {
    function get_city_name( string $by = 'id', $id = 0 ): string {
        return \SaltHareket\Localization\LocationManager::getInstance()->getCityName( $by, $id );
    }
}

if ( ! function_exists( 'get_district_name' ) ) {
    function get_district_name( string $by = 'id', $id = 0 ): string {
        return \SaltHareket\Localization\LocationManager::getInstance()->getDistrictName( $by, $id );
    }
}

if ( ! function_exists( 'get_countries' ) ) {
    function get_countries( string $continent = '', string $selected = '', bool $all = false ): array {
        return \SaltHareket\Localization\LocationManager::getInstance()->getCountriesList( $continent, $selected, $all );
    }
}

if ( ! function_exists( 'get_cities' ) ) {
    function get_cities( string $country = '', string $selected = '' ): array {
        return \SaltHareket\Localization\LocationManager::getInstance()->getCitiesList( $country, $selected );
    }
}

if ( ! function_exists( 'get_states' ) ) {
    function get_states( string $country = '' ): array {
        return \SaltHareket\Localization\LocationManager::getInstance()->getStatesList( $country );
    }
}

if ( ! function_exists( 'get_districts' ) ) {
    function get_districts( string $state = '' ): array {
        return \SaltHareket\Localization\LocationManager::getInstance()->getDistrictsList( $state );
    }
}

if ( ! function_exists( 'get_region_by_country_code' ) ) {
    function get_region_by_country_code( string $code = '' ): array {
        return \SaltHareket\Localization\LocationManager::getInstance()->getRegionByCountryCode( $code );
    }
}

if ( ! function_exists( 'get_nearest_locations' ) ) {
    function get_nearest_locations( float $lat, float $lng, string $post_type = 'post', float $distance = 5, int $limit = 10 ): array {
        return \SaltHareket\Localization\LocationManager::getInstance()->getNearestLocations( $lat, $lng, $post_type, $distance, $limit );
    }
}

// GeoLocation_Query — eski fonksiyon adı korunuyor
if ( ! function_exists( 'GeoLocation_Query' ) ) {
    function GeoLocation_Query( $lat, $lon, $post_type = 'post', $distance = 5, $limit = 100, $lat_key = 'lat', $lon_key = 'lon', $unit = 'km' ): array {
        return \SaltHareket\Localization\LocationManager::getInstance()->getNearestLocations(
            (float) $lat, (float) $lon, $post_type, (float) $distance, (int) $limit, $lat_key, $lon_key, $unit
        );
    }
}

// ─── TWIG HELPERS ─────────────────────────────────────────────────────────────

add_filter( 'timber/twig', function ( \Twig\Environment $twig ) {

    $lm = \SaltHareket\Localization\LocationManager::getInstance();

    // {{ fn('get_country_name', 'iso2', 'TR') }}
    $twig->addFunction( new \Twig\TwigFunction( 'get_country_name', 'get_country_name' ) );

    // {{ fn('get_city_name', 'id', post.city) }}
    $twig->addFunction( new \Twig\TwigFunction( 'get_city_name', 'get_city_name' ) );

    // {{ fn('get_district_name', 'id', post.district) }}
    $twig->addFunction( new \Twig\TwigFunction( 'get_district_name', 'get_district_name' ) );

    // {% set countries = fn('get_countries', 'Europe') %}
    $twig->addFunction( new \Twig\TwigFunction( 'get_countries', 'get_countries' ) );

    // {% set cities = fn('get_cities', 'TR') %}
    $twig->addFunction( new \Twig\TwigFunction( 'get_cities', 'get_cities' ) );

    // {% set states = fn('get_states', 'TR') %}
    $twig->addFunction( new \Twig\TwigFunction( 'get_states', 'get_states' ) );

    // {% set districts = fn('get_districts', 'TR34') %}
    $twig->addFunction( new \Twig\TwigFunction( 'get_districts', 'get_districts' ) );

    // {% set nearby = fn('get_nearest_locations', post.lat, post.lng, 'store', 10) %}
    $twig->addFunction( new \Twig\TwigFunction( 'get_nearest_locations', 'get_nearest_locations' ) );

    // {% set region_ids = fn('get_region_by_country_code', 'TR') %}
    $twig->addFunction( new \Twig\TwigFunction( 'get_region_by_country_code', 'get_region_by_country_code' ) );

    return $twig;
} );

// ─── TIMBER EXTEND HELPERS ────────────────────────────────────────────────────
// Post ve User extend'lerine lokasyon metodları eklenir.
// extends/post.php ve extends/user.php'deki metodlar LocationManager'a delegate eder.
// Aşağıdaki hook'lar Timber context'ine lokasyon verilerini ekler.

add_filter( 'timber/context', function ( array $context ) {
    if ( ! defined( 'ENABLE_IP2COUNTRY' ) || ! ENABLE_IP2COUNTRY ) return $context;

    $lm = \SaltHareket\Localization\LocationManager::getInstance();

    // Kullanıcının ülke kodu (cookie'den)
    $country_code = $_COOKIE['user_country_code'] ?? '';

    // site_config'e lokasyon verilerini ekle
    $site_config = $context['site_config'] ?? [];
    if ( ! isset( $site_config['user_country'] ) ) {
        $site_config['user_country']      = $_COOKIE['user_country'] ?? '';
        $site_config['user_country_code'] = $country_code;
        $site_config['user_region']       = json_decode( stripslashes( $_COOKIE['user_region'] ?? '[]' ), true ) ?: [];
        $context['site_config']           = $site_config;
    }

    return $context;
} );
