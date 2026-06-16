<?php

namespace SaltHareket\Localization\Concerns;

/**
 * HandlesGeo
 *
 * Ülke, şehir, ilçe sorguları + WooCommerce mapping + timezone.
 * location_data_source = 'database' → MySQL tabloları.
 * location_data_source = 'package'  → WooCommerce veya PHP sabit veri fallback.
 * QueryCache ile cache'lenir — aynı sorgu request'te bir kez çalışır.
 *
 * @version 2.0.0
 * @changelog
 *   2.0.0 - 2026-06-16
 *     - Add: location_data_source desteği ('database' | 'package')
 *     - Add: isPackageMode() helper
 *     - Change: countries(), states() — package modda WC veya PHP fallback
 *     - Change: getCountryName() — package modda WC öncelikli
 *     - Change: getCountriesList() — package modda WC öncelikli
 *     - Change: getStatesList() — package modda WC öncelikli
 *     - Note: cities tablosu kaldırıldı (salt-next ile uyumlu)
 *   1.0.0 - 2026-05-09 — Initial release
 */
trait HandlesGeo
{
    // ─── Source Helper ───────────────────────────────────────────────────────

    /**
     * Package modu mu (WC/fallback), database modu mu (MySQL)?
     */
    private function isPackageMode(): bool
    {
        $source = \SaltHareket\Localization\LocationSettings::getSetting( 'location_data_source', 'database' );
        return $source === 'package';
    }

    // ─── Countries ───────────────────────────────────────────────────────────

    /**
     * Ülkeleri listele.
     * @param array $filters ['region', 'subregion', 'iso2', 'id']
     */
    public function countries( array $filters = [] ): array
    {
        // Package modu — WC'den veya PHP fallback'ten
        if ( $this->isPackageMode() ) {
            return $this->countriesFromPackage( $filters );
        }

        global $wpdb;

        $cache_key = 'sh_loc_countries_' . md5( serialize( $filters ) );
        $cached    = wp_cache_get( $cache_key, 'localization' );
        if ( $cached !== false ) return $cached;

        $where = $this->buildWhere( $filters );

        $ct = $wpdb->prefix . 'countries';
        $st = $wpdb->prefix . 'states';

        $sql = "SELECT c.id, c.name, c.iso2, c.phonecode, c.region, c.subregion,
                       c.latitude, c.longitude, c.timezones,
                       COUNT(s.id) AS states
                FROM {$ct} c
                LEFT JOIN {$st} s ON s.country_code = c.iso2
                {$where}
                GROUP BY c.id, c.name, c.iso2, c.phonecode, c.region, c.subregion,
                         c.latitude, c.longitude, c.timezones
                ORDER BY c.name";

        $result = $wpdb->get_results( $sql );
        $data   = $result ? json_decode( json_encode( $result ), true ) : [];

        wp_cache_set( $cache_key, $data, 'localization', 300 );
        return $data;
    }

    /**
     * Package modda ülke listesi — WC öncelikli, yoksa basit fallback.
     */
    private function countriesFromPackage( array $filters = [] ): array
    {
        $cache_key = 'sh_loc_pkg_countries_' . md5( serialize( $filters ) );
        $cached    = wp_cache_get( $cache_key, 'localization' );
        if ( $cached !== false ) return $cached;

        // WooCommerce listesi
        $wc_list = [];
        if ( function_exists( 'WC' ) && WC()->countries ) {
            $wc_list = WC()->countries->get_countries() ?: [];
        }

        if ( empty( $wc_list ) ) {
            // Minimal fallback — yaygın ülkeler
            $wc_list = apply_filters( 'sh_countries_fallback', [
                'TR' => 'Turkey', 'US' => 'United States', 'DE' => 'Germany',
                'GB' => 'United Kingdom', 'FR' => 'France', 'IT' => 'Italy',
                'ES' => 'Spain', 'NL' => 'Netherlands', 'BE' => 'Belgium',
                'AT' => 'Austria', 'CH' => 'Switzerland', 'SE' => 'Sweden',
                'NO' => 'Norway', 'DK' => 'Denmark', 'FI' => 'Finland',
                'PL' => 'Poland', 'CZ' => 'Czech Republic', 'RU' => 'Russia',
                'AE' => 'United Arab Emirates', 'SA' => 'Saudi Arabia',
                'JP' => 'Japan', 'CN' => 'China', 'IN' => 'India', 'AU' => 'Australia',
            ] );
        }

        $data = [];
        foreach ( $wc_list as $iso2 => $name ) {
            // iso2 filtresi
            if ( isset( $filters['iso2'] ) && $filters['iso2'] !== $iso2 ) continue;

            $data[] = [
                'id'        => 0,
                'name'      => $name,
                'iso2'      => $iso2,
                'phonecode' => '',
                'region'    => '',
                'subregion' => '',
                'latitude'  => '',
                'longitude' => '',
                'timezones' => '[]',
                'states'    => 0,
            ];
        }

        wp_cache_set( $cache_key, $data, 'localization', 300 );
        return $data;
    }

    /**
     * Ülke adını döndür.
     * WooCommerce aktifse WC'nin listesini kullanır.
     */
    public function getCountryName( string $key = 'iso2', string $value = '' ): string
    {
        if ( empty( $value ) ) return '';

        // WC her zaman öncelikli — hem package hem database modda
        if ( function_exists( 'WC' ) && WC()->countries ) {
            $wc_name = WC()->countries->countries[ $value ] ?? null;
            if ( $wc_name ) return $wc_name;
        }

        // Package modu — fallback listesinden
        if ( $this->isPackageMode() ) {
            $list = $this->countriesFromPackage();
            foreach ( $list as $c ) {
                if ( $c[ $key ] === $value ) return $c['name'];
            }
            return $value;
        }

        // Database modu
        $result = $this->countries( [ $key => $value ] );
        return $result[0]['name'] ?? $value;
    }

    /**
     * Bölgeleri listele.
     */
    public function regions(): array
    {
        if ( $this->isPackageMode() ) return [];

        global $wpdb;
        $cached = wp_cache_get( 'sh_loc_regions', 'localization' );
        if ( $cached !== false ) return $cached;

        $result = $wpdb->get_col( "SELECT DISTINCT region FROM {$wpdb->prefix}countries WHERE region != '' ORDER BY region" );
        wp_cache_set( 'sh_loc_regions', $result, 'localization', 300 );
        return $result ?: [];
    }

    /**
     * Hiyerarşik ülke listesi (kıta → ülke).
     * WooCommerce aktifse WC'nin listesini kullanır.
     */
    public function getCountriesList( string $continent = '', string $selected = '', bool $all = false ): array
    {
        // WC her zaman öncelikli
        if ( function_exists( 'WC' ) && WC()->countries ) {
            return $this->getCountriesListWoo( $continent, $selected, $all );
        }

        // Package modu — flat liste (kıta bilgisi yok)
        if ( $this->isPackageMode() ) {
            $countries = $this->countriesFromPackage();
            $children  = [];
            if ( $all ) {
                $children[] = [ 'name' => 'All', 'slug' => '', 'selected' => true ];
            }
            foreach ( $countries as $c ) {
                $children[] = [
                    'name'     => $c['name'],
                    'slug'     => $c['iso2'],
                    'selected' => ( $c['iso2'] === $selected ),
                ];
            }
            return [ [ 'name' => 'Countries', 'children' => $children ] ];
        }

        // Database modu
        $data    = [];
        $regions = $this->regions();

        foreach ( $regions as $region ) {
            $countries = $this->countries( [ 'region' => $region ] );
            if ( empty( $countries ) ) continue;

            $children = [];
            if ( $all ) {
                $children[] = [ 'name' => 'All ' . $region, 'slug' => '', 'selected' => true ];
            }
            foreach ( $countries as $c ) {
                $children[] = [
                    'name'     => $c['name'],
                    'slug'     => $c['iso2'],
                    'selected' => ( $c['iso2'] === $selected ),
                ];
            }

            if ( ! empty( $continent ) && $region !== $continent ) continue;
            $data[] = [ 'name' => $region, 'children' => $children ];
        }

        return $data;
    }

    private function getCountriesListWoo( string $continent, string $selected, bool $all ): array
    {
        $data           = [];
        $wc             = new \WC_Countries();
        $country_list   = $wc->__get( 'countries' );
        $continent_list = $wc->get_continents();

        if ( ! empty( $continent ) && isset( $continent_list[ $continent ] ) ) {
            if ( $all ) {
                $data[] = [ 'name' => 'All ' . $continent_list[ $continent ]['name'], 'slug' => '', 'selected' => true ];
            }
            foreach ( $continent_list[ $continent ]['countries'] as $code ) {
                $data[] = [
                    'name'     => $country_list[ $code ] ?? $code,
                    'slug'     => $code,
                    'selected' => ( $code === $selected ),
                ];
            }
        } else {
            foreach ( $continent_list as $cont ) {
                $children = [];
                foreach ( $cont['countries'] as $code ) {
                    $children[] = [ 'name' => $country_list[ $code ] ?? $code, 'slug' => $code ];
                }
                $data[] = [ 'name' => $cont['name'], 'children' => $children ];
            }
        }

        return $data;
    }

    // ─── States / Cities ─────────────────────────────────────────────────────

    /**
     * Şehirleri listele.
     * @param array   $filters         ['country_code', 'id', 'iso2']
     * @param bool    $woo_only        Sadece WooCommerce state'i olanlar
     */
    public function states( array $filters = [], bool $woo_only = false ): array
    {
        // Package modu — WC'den
        if ( $this->isPackageMode() ) {
            return $this->statesFromPackage( $filters, $woo_only );
        }

        global $wpdb;

        $cache_key = 'sh_loc_states_' . md5( serialize( $filters ) . ( $woo_only ? '1' : '0' ) );
        $cached    = wp_cache_get( $cache_key, 'localization' );
        if ( $cached !== false ) return $cached;

        $where    = $this->buildWhere( $filters );
        $woo_cond = '';

        if ( $woo_only ) {
            $woo_cond = stripos( $where, 'where' ) !== false
                ? ' AND c.woo IS NOT NULL'
                : ' WHERE c.woo IS NOT NULL';
        }

        $st = $wpdb->prefix . 'states';

        $sql = "SELECT c.id, c.name, c.country_code, c.iso2, c.fips_code,
                       c.latitude, c.longitude, c.woo
                FROM {$st} c
                {$where} {$woo_cond}
                ORDER BY c.name";

        $result = $wpdb->get_results( $sql );
        $data   = $result ? json_decode( json_encode( $result ), true ) : [];

        wp_cache_set( $cache_key, $data, 'localization', 300 );
        return $data;
    }

    /**
     * Package modda şehir listesi — WC'den.
     */
    private function statesFromPackage( array $filters = [], bool $woo_only = false ): array
    {
        $country_code = strtoupper( $filters['country_code'] ?? '' );
        if ( ! $country_code ) return [];

        $cache_key = 'sh_loc_pkg_states_' . $country_code;
        $cached    = wp_cache_get( $cache_key, 'localization' );
        if ( $cached !== false ) return $cached;

        $wc_states = [];
        if ( function_exists( 'WC' ) && WC()->countries ) {
            $wc_states = WC()->countries->get_states( $country_code ) ?: [];
        }

        $data = [];
        foreach ( $wc_states as $iso2 => $name ) {
            $data[] = [
                'id'           => 0,
                'name'         => $name,
                'country_code' => $country_code,
                'iso2'         => $iso2,
                'fips_code'    => '',
                'latitude'     => '',
                'longitude'    => '',
                'woo'          => $iso2,
            ];
        }

        wp_cache_set( $cache_key, $data, 'localization', 300 );
        return $data;
    }

    /**
     * Şehir adını döndür.
     * WooCommerce aktifse WC'nin state listesini kullanır.
     */
    public function getCityName( string $by = 'id', $id = 0 ): string
    {
        if ( empty( $id ) ) return '';

        $allowed = [ 'id', 'iso2', 'state_code', 'country_code' ];
        if ( ! in_array( $by, $allowed, true ) ) $by = 'id';

        global $wpdb;

        if ( defined( 'ENABLE_ECOMMERCE' ) && ENABLE_ECOMMERCE && function_exists( 'WC' ) ) {
            $row = $wpdb->get_row( $wpdb->prepare(
                "SELECT country_code, iso2 FROM {$wpdb->prefix}states WHERE {$by} = %s ORDER BY name ASC LIMIT 1",
                $id
            ), ARRAY_A );
            if ( $row ) {
                $states = WC()->countries->get_states( $row['country_code'] );
                return $states[ $row['iso2'] ] ?? '';
            }
            return '';
        }

        if ( $this->isPackageMode() ) return (string) $id;

        return $wpdb->get_var( $wpdb->prepare(
            "SELECT name FROM {$wpdb->prefix}states WHERE {$by} = %s ORDER BY name ASC LIMIT 1",
            $id
        ) ) ?: '';
    }

    /**
     * WooCommerce state verisi al (name + woo kodu).
     * Güvenli — boş sonuçta null döner, array index hatası yok.
     */
    public function getStateWooData( int $id ): ?array
    {
        if ( $this->isPackageMode() ) return null;

        global $wpdb;
        $result = $wpdb->get_row( $wpdb->prepare(
            "SELECT name, woo, iso2, country_code FROM {$wpdb->prefix}states WHERE id = %d LIMIT 1",
            $id
        ), ARRAY_A );

        if ( ! $result ) return null;

        // woo alanı boşsa WC'den çözmeye çalış
        if ( empty( $result['woo'] ) && defined( 'ENABLE_ECOMMERCE' ) && ENABLE_ECOMMERCE && function_exists( 'WC' ) ) {
            $states = WC()->countries->get_states( $result['country_code'] ) ?: [];
            foreach ( $states as $woo_code => $state_name ) {
                if ( strtolower( $state_name ) === strtolower( $result['name'] ) ) {
                    $result['woo'] = $woo_code;
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Ülkenin WooCommerce state'i var mı?
     */
    public function hasState( string $country_code, bool $woo_only = false ): bool
    {
        if ( $this->isPackageMode() ) {
            if ( ! function_exists( 'WC' ) ) return false;
            $states = WC()->countries->get_states( $country_code );
            return ! empty( $states );
        }

        global $wpdb;
        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}states WHERE country_code = %s",
            $country_code
        );
        if ( $woo_only ) $sql .= ' AND woo IS NOT NULL';
        return (bool) $wpdb->get_var( $sql );
    }

    /**
     * Şehir listesi (form için).
     */
    public function getCitiesList( string $country_code = '', string $selected = '' ): array
    {
        if ( $this->isPackageMode() ) {
            $states = $this->statesFromPackage( [ 'country_code' => $country_code ] );
            if ( empty( $states ) ) {
                return [ [ 'name' => $this->getCountryName( 'iso2', $country_code ), 'slug' => $country_code ] ];
            }
            return array_map( function ( $row ) use ( $selected ) {
                return [
                    'slug'     => $row['iso2'],
                    'name'     => $row['name'],
                    'selected' => ( $row['iso2'] === $selected ),
                ];
            }, $states );
        }

        global $wpdb;
        $result = $wpdb->get_results( $wpdb->prepare(
            "SELECT id as slug, name FROM {$wpdb->prefix}states WHERE country_code = %s ORDER BY name ASC",
            $country_code
        ) );

        if ( ! $result ) {
            return [ [ 'name' => $this->getCountryName( 'iso2', $country_code ), 'slug' => $country_code ] ];
        }

        return array_map( function ( $row ) use ( $selected ) {
            return [
                'slug'     => $row->slug,
                'name'     => $row->name,
                'selected' => ( (string) $row->slug === (string) $selected ),
            ];
        }, $result );
    }

    /**
     * WooCommerce state listesi.
     */
    public function getStatesList( string $country = '' ): array
    {
        // WC her zaman öncelikli
        if ( function_exists( 'WC' ) && WC()->countries ) {
            return WC()->countries->get_states( $country ) ?: [];
        }

        if ( $this->isPackageMode() ) return [];

        $result = $this->states( [ 'country_code' => $country ] );
        $states = [];
        foreach ( $result as $item ) {
            $states[ $item['country_code'] . $item['iso2'] ] = $item['name'];
        }
        return $states;
    }

    // ─── Districts / Cities ──────────────────────────────────────────────────
    // NOT: cities tablosu kaldırıldı (salt-next ile uyumlu).
    // Bu metodlar geriye uyumluluk için korunuyor ama boş dönüyor.

    /**
     * İlçeleri listele — cities tablosu kaldırıldı, geriye uyumluluk için korunuyor.
     * @deprecated cities tablosu kullanılmıyor
     */
    public function cities( array $filters = [] ): array
    {
        return [];
    }

    /**
     * İlçe adını döndür — cities tablosu kaldırıldı.
     * @deprecated cities tablosu kullanılmıyor
     */
    public function getDistrictName( string $by = 'id', $id = 0 ): string
    {
        return '';
    }

    /**
     * İlçe listesi — cities tablosu kaldırıldı.
     * @deprecated cities tablosu kullanılmıyor
     */
    public function getDistrictsList( string $state = '' ): array
    {
        return [];
    }

    // ─── Post/Term Location Queries ──────────────────────────────────────────

    /**
     * Postmeta'dan kullanılan şehirleri listele.
     */
    public function getAvailableCities( string $post_type = 'post', string $meta_key = 'city', string $country = '' ): array
    {
        global $wpdb;

        if ( empty( $country ) && defined( 'ENABLE_ECOMMERCE' ) && ENABLE_ECOMMERCE && function_exists( 'wc_get_base_country' ) ) {
            $country = wc_get_base_country();
        }

        $result = $wpdb->get_results( $wpdb->prepare(
            "SELECT DISTINCT m.meta_value as city
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id
             WHERE m.meta_key = %s AND p.post_type = %s AND p.post_status = 'publish' AND m.meta_value != ''
             ORDER BY m.meta_value ASC",
            $meta_key, $post_type
        ) );

        $output = [];
        foreach ( $result as $row ) {
            if ( empty( $row->city ) ) continue;
            $output[ $row->city ] = $this->getCityName( 'id', $row->city );
        }
        return $output;
    }

    /**
     * Postmeta'dan kullanılan ilçeleri listele — cities tablosu kaldırıldı.
     * @deprecated
     */
    public function getAvailableDistricts( string $post_type = 'post', string $city = '' ): array
    {
        return [];
    }

    /**
     * Şehir + ilçeye göre postları getir — cities tablosu kaldırıldı.
     * @deprecated
     */
    public function getPostsByDistrict( string $post_type = 'post', string $city = '', string $district = '' ): array
    {
        return [];
    }

    // ─── Timezone ────────────────────────────────────────────────────────────

    /**
     * Koordinat/ülke/şehirden timezone hesapla.
     */
    public function getTimezone( float $lat = 0, float $lng = 0, string $country_code = '', string $city_name = '' ): array
    {
        $timezone_data = [];

        if ( $country_code ) {
            $country_data = $this->countries( [ 'iso2' => $country_code ] );
            if ( $country_data ) {
                $timezones = json_decode( $country_data[0]['timezones'] ?? '[]', true );
                if ( $timezones ) {
                    if ( count( $timezones ) === 1 ) {
                        $timezone_data = [
                            'gmtOffset' => $timezones[0]['gmtOffset'],
                            'gmt'       => str_replace( 'UTC', '', $timezones[0]['gmtOffsetName'] ),
                            'timezone'  => $timezones[0]['zoneName'],
                        ];
                    } elseif ( $city_name ) {
                        $city_slug = str_replace( ' ', '_', ucwords( $city_name ) );
                        foreach ( $timezones as $tz ) {
                            if ( str_contains( $tz['zoneName'], $city_slug ) ) {
                                $timezone_data = [
                                    'gmtOffset' => $tz['gmtOffset'],
                                    'gmt'       => str_replace( 'UTC', '', $tz['gmtOffsetName'] ),
                                    'timezone'  => $tz['zoneName'],
                                ];
                                break;
                            }
                        }
                    }
                }
            }
        }

        // Koordinat bazlı fallback
        if ( ! $timezone_data && $lat && $lng ) {
            $tz_ids = $country_code
                ? DateTimeZone::listIdentifiers( DateTimeZone::PER_COUNTRY, $country_code )
                : DateTimeZone::listIdentifiers();

            if ( $tz_ids ) {
                $best_tz   = '';
                $best_dist = PHP_FLOAT_MAX;

                foreach ( $tz_ids as $tz_id ) {
                    $tz       = new \DateTimeZone( $tz_id );
                    $loc      = $tz->getLocation();
                    $theta    = $lng - $loc['longitude'];
                    $dist     = abs( rad2deg( acos(
                        sin( deg2rad( $lat ) ) * sin( deg2rad( $loc['latitude'] ) )
                        + cos( deg2rad( $lat ) ) * cos( deg2rad( $loc['latitude'] ) ) * cos( deg2rad( $theta ) )
                    ) ) );

                    if ( $dist < $best_dist ) {
                        $best_dist = $dist;
                        $best_tz   = $tz_id;
                    }
                }

                if ( $best_tz ) {
                    $timezone_data = $this->getGmt( $best_tz );
                    $timezone_data['timezone'] = $best_tz;
                }
            }
        }

        return $timezone_data;
    }

    /**
     * Timezone'dan GMT offset hesapla.
     */
    public function getGmt( string $timezone = '' ): array
    {
        if ( empty( $timezone ) ) return [];

        $origin_dtz = new \DateTimeZone( $timezone );
        $remote_dtz = new \DateTimeZone( 'UTC' );
        $origin_dt  = new \DateTime( 'now', $origin_dtz );
        $remote_dt  = new \DateTime( 'now', $remote_dtz );
        $offset     = $origin_dtz->getOffset( $origin_dt ) - $remote_dtz->getOffset( $remote_dt );

        return [
            'gmtOffset' => $offset,
            'gmt'       => $origin_dt->format( 'P' ),
        ];
    }

    // ─── SQL Builder ─────────────────────────────────────────────────────────

    /**
     * Güvenli WHERE clause oluştur.
     * Sadece whitelist'teki kolonlar kabul edilir.
     */
    protected function buildWhere( array $filters ): string
    {
        if ( empty( $filters ) ) return '';

        global $wpdb;

        $allowed = [
            'id', 'iso2', 'name', 'region', 'subregion', 'country_code',
            'state_code', 'fips_code', 'woo', 'state_id',
        ];

        $conditions = [];
        foreach ( $filters as $key => $value ) {
            if ( ! in_array( $key, $allowed, true ) ) continue;
            $conditions[] = $wpdb->prepare( "c.{$key} = %s", $value );
        }

        return $conditions ? 'WHERE ' . implode( ' AND ', $conditions ) : '';
    }
}
