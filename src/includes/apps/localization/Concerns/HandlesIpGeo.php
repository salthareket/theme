<?php

namespace SaltHareket\Localization\Concerns;

/**
 * HandlesIpGeo
 *
 * IP Geolocation — DB tablosu veya geoplugin.net API.
 *
 * @version 1.0.0
 */
trait HandlesIpGeo
{
    /**
     * IP'den lokasyon bilgisi al.
     * DB aktifse DB'den, değilse API'den.
     *
     * @return array|null ['name', 'iso2', 'city', 'state', 'country_code', ...]
     */
    public function ipInfo( ?string $ip = null ): ?array
    {
        $settings = \SaltHareket\Localization\LocationSettings::get();

        if ( $settings['enable_ip2country'] && $settings['ip2country_source'] === 'db' ) {
            return $this->ipFromDb( $ip );
        }

        return $this->ipFromApi( $ip );
    }

    /**
     * IP'den ülke kodu al (hızlı versiyon).
     */
    public function ip2Country( ?string $ip = null ): ?string
    {
        $info = $this->ipInfo( $ip );
        return $info['iso2'] ?? $info['country_code'] ?? null;
    }

    // ─── DB ──────────────────────────────────────────────────────────────────

    private function ipFromDb( ?string $ip ): ?array
    {
        $ip = $this->resolveIp( $ip );
        if ( ! $ip ) return null;

        $ip_long = ip2long( $ip );
        if ( ! is_numeric( $ip_long ) ) return null;

        global $wpdb;

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT c.name, c.iso2
             FROM {$wpdb->prefix}ip2country i
             JOIN {$wpdb->prefix}countries c ON i.country = c.iso2
             WHERE %d >= i.ipfrom AND %d <= i.ipto
             LIMIT 1",
            $ip_long, $ip_long
        ) );

        if ( ! $row ) return null;

        return [
            'name'         => $row->name,
            'iso2'         => $row->iso2,
            'country_code' => $row->iso2,
            'city'         => '',
            'state'        => '',
        ];
    }

    // ─── API ─────────────────────────────────────────────────────────────────

    private function ipFromApi( ?string $ip ): ?array
    {
        $ip = $this->resolveIp( $ip );
        if ( ! $ip ) return null;

        // Runtime cache — aynı request'te tekrar API çağrısı yapma
        static $cache = [];
        if ( isset( $cache[ $ip ] ) ) return $cache[ $ip ];

        $response = wp_remote_get(
            'https://www.geoplugin.net/json.gp?ip=' . urlencode( $ip ),
            [ 'timeout' => 5, 'sslverify' => false ]
        );

        if ( is_wp_error( $response ) ) return null;

        $data = json_decode( wp_remote_retrieve_body( $response ) );
        if ( ! $data || strlen( trim( $data->geoplugin_countryCode ?? '' ) ) !== 2 ) return null;

        $result = [
            'name'         => $data->geoplugin_countryName ?? '',
            'iso2'         => $data->geoplugin_countryCode ?? '',
            'country_code' => $data->geoplugin_countryCode ?? '',
            'city'         => $data->geoplugin_city ?? '',
            'state'        => $data->geoplugin_regionName ?? '',
        ];

        $cache[ $ip ] = $result;
        return $result;
    }

    // ─── Helper ──────────────────────────────────────────────────────────────

    private function resolveIp( ?string $ip ): ?string
    {
        if ( $ip && filter_var( $ip, FILTER_VALIDATE_IP ) ) return $ip;

        // Gerçek IP'yi bul (proxy arkasında)
        foreach ( [ 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR' ] as $key ) {
            $val = $_SERVER[ $key ] ?? '';
            // X-Forwarded-For virgülle ayrılmış olabilir
            $val = trim( explode( ',', $val )[0] );
            if ( filter_var( $val, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                return $val;
            }
        }

        // Localhost/private IP'leri de kabul et (dev ortamı)
        return filter_var( $_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP )
            ? $_SERVER['REMOTE_ADDR']
            : null;
    }
}
