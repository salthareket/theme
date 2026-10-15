<?php

namespace SaltHareket\Localization;

/**
 * LocationSettings
 *
 * Localization + Regional Posts ayarlarını yönetir.
 * ACF options sayfasından okur (geriye uyumluluk) + kendi wp_options key'lerini kullanır.
 * ACF field'ları silinince kendi key'lerinden okumaya devam eder.
 *
 * @version 2.0.2
 * @changelog
 *   2.0.2 - 2026-07-04
 *     - PERF: readAcf() içindeki get_option() SaltConfig::get() ile değiştirildi.
 *             ACF init olmadan önce ~5-6 DB query → 0 query.
 *   2.0.1 - 2026-06-17
 *     - Add: save() sonrası do_action('sh/localization/saved') + do_action('sh/localization/setting_changed')
 *   2.0.0 - 2026-06-16
 *     - Add: enable_localization — ana switch
 *     - Add: location_data_source — 'database' | 'package' (WC/JSON fallback)
 *     - Change: save() — tüm alanlar zorunlu değil, merge ile partial update desteği
 *     - Fix: readAcf() — ACF henüz init olmadıysa get_option fallback
 *   1.0.0 - 2026-05-09 — Initial release
 */
class LocationSettings
{
    private const OPTION_KEY = 'sh_localization_settings';

    private static ?array $cache = null;

    /**
     * Tüm ayarları döndür.
     */
    public static function get(): array
    {
        if ( self::$cache !== null ) return self::$cache;

        $defaults = [
            'enable_localization'     => false,
            'location_data_source'    => 'database',
            'enable_ip2country'       => false,
            'ip2country_source'       => 'api',
            'enable_location_db'      => false,
            'enable_regional_posts'   => false,
            'regional_post_settings'  => [],
            'region_main'             => [],
            'woo_state_mapping'       => true,
        ];

        $saved = get_option( self::OPTION_KEY, [] );

        self::$cache = array_merge( $defaults, is_array( $saved ) ? $saved : [] );

        return self::$cache;
    }

    /**
     * Ayarları kaydet.
     * Partial update destekler — gönderilmeyen alanlar mevcut değerle korunur.
     */
    public static function save( array $data ): void
    {
        $current = self::get();

        $settings = [
            'enable_localization'    => isset( $data['enable_localization'] )
                ? ! empty( $data['enable_localization'] )
                : $current['enable_localization'],
            'location_data_source'   => isset( $data['location_data_source'] )
                ? ( in_array( $data['location_data_source'], [ 'database', 'package' ], true ) ? $data['location_data_source'] : 'database' )
                : $current['location_data_source'],
            'enable_ip2country'      => isset( $data['enable_ip2country'] )
                ? ! empty( $data['enable_ip2country'] )
                : $current['enable_ip2country'],
            'ip2country_source'      => isset( $data['ip2country_source'] )
                ? ( in_array( $data['ip2country_source'], [ 'db', 'api' ], true ) ? $data['ip2country_source'] : 'api' )
                : $current['ip2country_source'],
            'enable_location_db'     => isset( $data['enable_location_db'] )
                ? ! empty( $data['enable_location_db'] )
                : $current['enable_location_db'],
            'enable_regional_posts'  => isset( $data['enable_regional_posts'] )
                ? ! empty( $data['enable_regional_posts'] )
                : $current['enable_regional_posts'],
            'regional_post_settings' => isset( $data['regional_post_settings'] )
                ? self::sanitizePostSettings( $data['regional_post_settings'] )
                : $current['regional_post_settings'],
            'region_main'            => isset( $data['region_main'] )
                ? array_map( 'intval', (array) $data['region_main'] )
                : $current['region_main'],
            'woo_state_mapping'      => isset( $data['woo_state_mapping'] )
                ? ! empty( $data['woo_state_mapping'] )
                : $current['woo_state_mapping'],
        ];

        update_option( self::OPTION_KEY, $settings, false );
        self::$cache = null;

        // ── Hook: dışarıdan dinlenebilir ──────────────────────────────────────
        do_action( 'sh/localization/saved', $settings, $current );

        foreach ( $settings as $key => $new_val ) {
            $old_val = $current[ $key ] ?? null;
            if ( $old_val !== $new_val ) {
                do_action( 'sh/localization/setting_changed', $key, $new_val, $old_val, $settings );
            }
        }

        self::syncConstants( $settings );
        Schema\LocationSchema::clearCache();
    }

    /**
     * Tek bir ayarı al.
     */
    public static function getSetting( string $key, $default = null )
    {
        return self::get()[ $key ] ?? $default;
    }

    /**
     * WP constants'larını ayarlarla senkronize et.
     */
    private static function syncConstants( array $settings ): void
    {
        if ( defined( 'ENABLE_IP2COUNTRY' ) && ENABLE_IP2COUNTRY !== $settings['enable_ip2country'] ) {
            error_log( '[Localization] ENABLE_IP2COUNTRY constant mismatch — page reload required.' );
        }
    }

    /**
     * Post settings'i sanitize et.
     */
    private static function sanitizePostSettings( array $raw ): array
    {
        $clean = [];
        foreach ( $raw as $item ) {
            $pt  = sanitize_key( $item['post_type'] ?? '' );
            $tax = sanitize_key( $item['taxonomy'] ?? '' );
            if ( $pt && $tax ) {
                $clean[] = [ 'post_type' => $pt, 'taxonomy' => $tax ];
            }
        }
        return $clean;
    }

    /**
     * ACF option'ından oku — ACF field'ları kaldırıldı.
     * @deprecated
     */
    private static function readAcf( string $key )
    {
        return null; // ACF field'ları silindiğinden devre dışı
    }

    /**
     * Cache'i temizle.
     */
    public static function clearCache(): void
    {
        self::$cache = null;
    }
}
