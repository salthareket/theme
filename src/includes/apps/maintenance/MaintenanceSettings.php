<?php

namespace SaltHareket\Maintenance;

/**
 * MaintenanceSettings — Maintenance mode ayarlarını yönetir.
 * wp_options'da saklar, ACF bağımlılığı yok.
 * Eski underconstruction plugin'inin yerini alır.
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-06-22
 *     - Add: Initial release
 *     - Replaces: underconstruction plugin
 *
 * @package SaltHareket\Maintenance
 */
class MaintenanceSettings
{
    private const OPTION_KEY = 'sh_maintenance_settings';

    private static ?array $cache = null;

    // ─── Defaults ────────────────────────────────────────────────────────────

    public static function defaults(): array
    {
        return [
            'active'             => false,
            'maintenance_page'   => 0,        // WP page ID
            'white_pages'        => [],        // Bypass edilecek page ID'leri (default lang)
            'bypass_roles'       => ['administrator', 'editor'], // Bu roller siteyi görebilir
            'ip_whitelist'       => [],        // IP adresleri listesi
            'http_status'        => 503,       // 200 | 301 | 404 | 503
            'redirect_url'       => '',        // 301 seçilirse buraya yönlendir
            'show_admin_bar'     => false,     // Admin bar'ı göster/gizle
        ];
    }

    // ─── Read ─────────────────────────────────────────────────────────────────

    public static function get(): array
    {
        if ( self::$cache !== null ) return self::$cache;

        $saved = get_option( self::OPTION_KEY, [] );
        $saved = is_array( $saved ) ? $saved : [];

        // Eski plugin'den migration — ilk çalıştırmada
        if ( empty( $saved ) ) {
            $saved = self::migrateFromPlugin();
        }

        self::$cache = array_merge( self::defaults(), $saved );
        return self::$cache;
    }

    public static function getSetting( string $key, $default = null )
    {
        return self::get()[ $key ] ?? $default ?? self::defaults()[ $key ] ?? null;
    }

    public static function isActive(): bool
    {
        return (bool) self::getSetting( 'active' );
    }

    // ─── Write ────────────────────────────────────────────────────────────────

    public static function save( array $data ): void
    {
        $current = self::get();

        $settings = [
            'active'           => isset( $data['active'] ) ? (bool) $data['active'] : $current['active'],
            'maintenance_page' => isset( $data['maintenance_page'] ) ? (int) $data['maintenance_page'] : $current['maintenance_page'],
            'white_pages'      => isset( $data['white_pages'] ) && is_array( $data['white_pages'] )
                ? array_map( 'intval', $data['white_pages'] )
                : $current['white_pages'],
            'bypass_roles'     => isset( $data['bypass_roles'] ) && is_array( $data['bypass_roles'] )
                ? array_map( 'sanitize_key', $data['bypass_roles'] )
                : $current['bypass_roles'],
            'ip_whitelist'     => isset( $data['ip_whitelist'] ) && is_array( $data['ip_whitelist'] )
                ? array_filter( array_map( 'sanitize_text_field', $data['ip_whitelist'] ) )
                : $current['ip_whitelist'],
            'http_status'      => isset( $data['http_status'] )
                ? ( in_array( (int) $data['http_status'], [ 200, 301, 404, 503 ], true ) ? (int) $data['http_status'] : 503 )
                : $current['http_status'],
            'redirect_url'     => isset( $data['redirect_url'] ) ? esc_url_raw( $data['redirect_url'] ) : $current['redirect_url'],
            'show_admin_bar'   => isset( $data['show_admin_bar'] ) ? (bool) $data['show_admin_bar'] : $current['show_admin_bar'],
        ];

        update_option( self::OPTION_KEY, $settings, false );
        self::$cache = null;

        // SaltConfig cache'ini yenile
        if ( class_exists( '\SaltHareket\SaltConfig' ) ) {
            \SaltHareket\SaltConfig::generate();
        }

        do_action( 'sh/maintenance/saved', $settings, $current );
    }

    public static function clearCache(): void
    {
        self::$cache = null;
    }

    // ─── White Pages — Multilang aware ────────────────────────────────────────

    /**
     * Mevcut sayfa ID'sinin white_pages listesinde olup olmadığını kontrol eder.
     * Multilang sitede: sayfanın tüm çevirilerini de kontrol eder.
     *
     * @param int $page_id Kontrol edilecek WP page ID
     * @return bool
     */
    public static function isWhitePage( int $page_id ): bool
    {
        $white = self::getSetting( 'white_pages', [] );
        if ( empty( $white ) || ! is_array( $white ) ) return false;

        // int olarak normalize et (DB'den string gelebilir)
        $white = array_map( 'intval', $white );

        // Direkt eşleşme
        if ( in_array( $page_id, $white, true ) ) return true;

        // Multilang — Polylang: white_pages listesindeki default-lang ID'lerinin
        // tüm dil çevirilerini de kontrol et
        if ( function_exists( 'pll_get_post_translations' ) ) {
            foreach ( $white as $white_id ) {
                $translations = pll_get_post_translations( $white_id );
                if ( in_array( $page_id, array_map( 'intval', array_values( $translations ) ), true ) ) {
                    return true;
                }
            }
        }

        // Multilang — WPML
        if ( function_exists( 'icl_object_id' ) && defined( 'ICL_LANGUAGE_CODE' ) ) {
            foreach ( $white as $white_id ) {
                $translated = (int) icl_object_id( $white_id, 'page', false, ICL_LANGUAGE_CODE );
                if ( $translated > 0 && $translated === $page_id ) return true;
            }
        }

        return false;
    }

    /**
     * White pages için default dildeki sayfaları döner.
     * Admin UI'da sayfa listesi gösterirken kullanılır.
     *
     * @return array WP_Post[]
     */
    public static function getPagesForSelection(): array
    {
        // Polylang varsa sadece default dildeki sayfaları listele
        if ( function_exists( 'pll_default_language' ) ) {
            $lang = pll_default_language( 'slug' );
            $pages = get_posts( [
                'post_type'      => 'page',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
                'lang'           => $lang,
            ] );
            return $pages;
        }

        // WPML varsa default dildeki sayfalar
        if ( function_exists( 'icl_object_id' ) && defined( 'ICL_LANGUAGE_CODE' ) ) {
            global $sitepress;
            $default_lang = $sitepress ? $sitepress->get_default_language() : '';
            if ( $default_lang ) {
                do_action( 'wpml_switch_language', $default_lang );
                $pages = get_posts( [
                    'post_type'      => 'page',
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'orderby'        => 'title',
                    'order'          => 'ASC',
                ] );
                do_action( 'wpml_switch_language', ICL_LANGUAGE_CODE );
                return $pages;
            }
        }

        // Multilang yok — tüm sayfalar
        return get_posts( [
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );
    }

    // ─── Migration from old plugin ───────────────────────────────────────────

    private static function migrateFromPlugin(): array
    {
        $data = [];

        // Eski plugin aktif mi?
        $old_status = get_option( 'underConstructionActivationStatus' );
        if ( $old_status !== false ) {
            $data['active'] = (bool) $old_status;
        }

        // Eski maintenance page
        $old_page = get_option( 'under-construction-page' );
        if ( $old_page ) {
            $data['maintenance_page'] = (int) $old_page;
        }

        // Eski white pages (ACF options_white_pages)
        $old_white = get_option( 'options_white_pages' );
        if ( ! empty( $old_white ) && is_array( $old_white ) ) {
            $data['white_pages'] = array_map( 'intval', $old_white );
        }

        return $data;
    }
}
