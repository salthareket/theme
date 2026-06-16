<?php

namespace SaltHareket\AssetManager;

/**
 * AssetSettings
 *
 * Asset Manager ayarlarını yönetir.
 * ACF options sayfasından okur (geriye uyumluluk) + kendi wp_options key'lerini kullanır.
 * ACF field'ları silinince kendi key'lerinden okumaya devam eder.
 *
 * @version 1.0.0
 */
class AssetSettings
{
    private const OPTION_KEY = 'sh_asset_manager_settings';

    private static ?array $cache = null;

    /**
     * Tüm ayarları döndür.
     */
    public static function get(): array
    {
        if ( self::$cache !== null ) return self::$cache;

        $defaults = [
            // Aşağıdaki ayarlar ACF'den geliyor — tema genelinde, buraya taşımıyoruz:
            // - options_seperate_css  → SEPERATE_CSS constant
            // - options_inline_css   → INLINE_CSS constant
            // - options_seperate_js  → SEPERATE_JS constant
            // - options_inline_js    → INLINE_JS constant
            // - options_enable_production → ENABLE_PRODUCTION constant

            // Cleanup
            'remove_global_styles'        => 'auto',  // 'auto' | true | false
            'remove_block_styles'         => 'auto',
            'remove_classic_theme_styles' => false,
            'remove_woocommerce_styles'   => false,
            'block_google_fonts'          => true,

            // Lazy / Defer — default kapalı (eski davranış)
            'css_lazy_load'         => false,
            'js_defer'              => false,

            // Extra handles/patterns (filter ile de eklenebilir)
            'lazy_css_handles'      => [],
            'defer_js_handles'      => [],
            'dequeue_styles'        => [],
            'dequeue_scripts'       => [],
        ];

        // Önce kendi option'ımızdan oku
        $saved = get_option( self::OPTION_KEY, [] );

        // ACF'den fallback — sadece app'e özgü ayarlar
        // seperate_css, inline_css, seperate_js, inline_js ACF'de kalıyor
        $acf = [
            'remove_global_styles'        => self::readAcf( 'remove_global_styles' ),
            'remove_block_styles'         => self::readAcf( 'remove_block_styles' ),
            'remove_classic_theme_styles' => self::readAcf( 'remove_classic_theme_styles' ),
            'remove_woocommerce_styles'   => self::readAcf( 'remove_woocommerce_styles' ),
        ];

        // Merge: saved > acf > defaults
        $merged = $defaults;
        foreach ( $acf as $key => $val ) {
            if ( $val !== null && $val !== false && $val !== '' && ! isset( $saved[ $key ] ) ) {
                $merged[ $key ] = $val;
            }
        }
        foreach ( $saved as $key => $val ) {
            $merged[ $key ] = $val;
        }

        self::$cache = $merged;
        return self::$cache;
    }

    /**
     * Tek bir ayarı al.
     */
    public static function getSetting( string $key, $default = null )
    {
        return self::get()[ $key ] ?? $default;
    }

    /**
     * Ayarları kaydet.
     */
    public static function save( array $data ): void
    {
        $current = self::get();

        $settings = [
            // ACF'de kalan ayarlar buraya kaydedilmiyor:
            // enable_production, seperate_css, inline_css, seperate_js, inline_js
            'remove_global_styles'        => in_array( $data['remove_global_styles'] ?? '', [ 'auto', '1', '0' ], true ) ? $data['remove_global_styles'] : 'auto',
            'remove_block_styles'         => in_array( $data['remove_block_styles'] ?? '', [ 'auto', '1', '0' ], true ) ? $data['remove_block_styles'] : 'auto',
            'remove_classic_theme_styles' => ! empty( $data['remove_classic_theme_styles'] ),
            'remove_woocommerce_styles'   => ! empty( $data['remove_woocommerce_styles'] ),
            'block_google_fonts'          => ! empty( $data['block_google_fonts'] ),
            'css_lazy_load'               => ! empty( $data['css_lazy_load'] ),
            'js_defer'                    => ! empty( $data['js_defer'] ),
            'lazy_css_handles'            => array_filter( array_map( 'sanitize_key', (array) ( $data['lazy_css_handles'] ?? [] ) ) ),
            'defer_js_handles'            => array_filter( array_map( 'sanitize_key', (array) ( $data['defer_js_handles'] ?? [] ) ) ),
            'dequeue_styles'              => array_filter( array_map( 'sanitize_key', (array) ( $data['dequeue_styles'] ?? [] ) ) ),
            'dequeue_scripts'             => array_filter( array_map( 'sanitize_key', (array) ( $data['dequeue_scripts'] ?? [] ) ) ),
        ];

        update_option( self::OPTION_KEY, $settings, false );
        self::$cache = null;

        // WP constants runtime sync (sayfa reload gerektirir ama log'la)
        if ( defined( 'ENABLE_PRODUCTION' ) && ENABLE_PRODUCTION !== $settings['enable_production'] ) {
            error_log( '[AssetManager] ENABLE_PRODUCTION mismatch — page reload required.' );
        }
    }

    /**
     * Cache'i temizle.
     */
    public static function clearCache(): void
    {
        self::$cache = null;
    }

    private static function readAcf( string $key )
    {
        // get_field() ACF initialize olmadan çağrılamaz — sadece get_option() kullan
        // ACF options sayfası değerleri wp_options'da 'options_{key}' veya direkt key olarak saklanır
        $val = get_option( $key );
        if ( $val !== false ) return $val;

        // 'options_' prefix ile de dene (ACF options sayfası bazen bu şekilde saklar)
        $val = get_option( 'options_' . $key );
        if ( $val !== false ) return $val;

        return null;
    }
}
