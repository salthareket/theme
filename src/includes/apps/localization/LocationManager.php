<?php

namespace SaltHareket\Localization;

use SaltHareket\Localization\Concerns\HandlesGeo;
use SaltHareket\Localization\Concerns\HandlesIpGeo;
use SaltHareket\Localization\Concerns\HandlesRegionalPosts;
use SaltHareket\Localization\Concerns\HandlesGeoQuery;

/**
 * LocationManager
 *
 * Tüm lokasyon işlemlerinin tek giriş noktası.
 * WooCommerce var mı yok mu, dışarıdan bakan kod bilmez.
 *
 * @version 2.0.0
 * @changelog
 *   2.0.0 - 2026-06-16
 *     - Fix: isRegionalActive() — ENABLE_REGIONAL_POSTS constant yoksa LocationSettings'den okur
 *     - Fix: isActive() — enable_localization settings'den de okur
 *   1.0.0 - 2026-05-09
 *     - Add: Initial release — class.localization.php + regional-posts refactored
 *     - Add: Singleton pattern
 *     - Add: HandlesGeo, HandlesIpGeo, HandlesRegionalPosts, HandlesGeoQuery traits
 *     - Add: QueryCache entegrasyonu (opsiyonel)
 *     - Add: WooCommerce state mapping güvenli hale getirildi
 *     - Add: Twig helper fonksiyonları
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * $lm = LocationManager::getInstance();
 *
 * // Ülkeler
 * $lm->countries(['region' => 'Europe'])
 * $lm->getCountryName('iso2', 'TR')
 *
 * // Şehirler
 * $lm->states(['country_code' => 'TR'])
 * $lm->getCityName('id', 34)
 * $lm->getStateWooData(34)  // WC mapping
 *
 * // IP Geo
 * $lm->ipInfo()
 * $lm->ip2Country()
 *
 * // Regional Posts
 * $lm->getRegionByCountryCode('TR')
 * $lm->resolveUserRegion()
 *
 * // Yakın lokasyonlar
 * $lm->getNearestLocations(41.0, 29.0, 'store', 10)
 *
 * ──────────────────────────────────────────────────────────
 *
 * @example
 *   // Twig'de:
 *   {{ fn('get_country_name', 'iso2', 'TR') }}
 *   {{ fn('get_city_name', 'id', post.city) }}
 *   {% set cities = fn('get_cities', 'TR') %}
 *   {% set nearby = fn('get_nearest_locations', user.lat, user.lng, 'store', 10) %}
 *   {{ post.regions|join(', ') }}
 *   {{ user.country_name }}
 *
 * @example
 *   // PHP'de:
 *   $lm = LocationManager::getInstance();
 *   $woo = $lm->getStateWooData($city_id);
 *   update_user_meta($user_id, 'billing_state', $woo['woo'] ?? '');
 *
 * @example
 *   // Menüye item ekle (dışarıdan):
 *   add_filter('sh_localization_menu_items', function($items) {
 *       $items['my-tab'] = 'My Tab';
 *       return $items;
 *   });
 *
 * @example
 *   // Regional post type ekle (dışarıdan):
 *   add_filter('sh_regional_post_types', function($types) {
 *       $types[] = 'my-post-type';
 *       return $types;
 *   });
 *
 * @example
 *   // Lokasyon değişince hook:
 *   add_action('sh_user_location_changed', function($user_id, $country_code) {
 *       // bildirim gönder, cache temizle vs.
 *   }, 10, 2);
 */
class LocationManager
{
    use HandlesGeo;
    use HandlesIpGeo;
    use HandlesRegionalPosts;
    use HandlesGeoQuery;

    private static ?self $instance = null;

    private function __construct() {}

    public static function getInstance(): self
    {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Localization aktif mi?
     */
    public static function isActive(): bool
    {
        return defined( 'ENABLE_IP2COUNTRY' ) && ENABLE_IP2COUNTRY
            || defined( 'ENABLE_LOCATION_DB' ) && ENABLE_LOCATION_DB
            || (bool) \SaltHareket\Localization\LocationSettings::getSetting( 'enable_localization', false );
    }

    /**
     * Regional posts aktif mi?
     */
    public static function isRegionalActive(): bool
    {
        return ( defined( 'ENABLE_REGIONAL_POSTS' ) && ENABLE_REGIONAL_POSTS )
            || (bool) \SaltHareket\Localization\LocationSettings::getSetting( 'enable_regional_posts', false );
    }
}
