<?php

namespace SaltHareket\DownloadLog;

/**
 * DownloadLogSettings
 *
 * Download Log app aktif/pasif ayarını ve genel ayarlarını yönetir.
 * Diğer app Settings sınıflarıyla aynı pattern'i takip eder:
 *   - ReactionsAppSettings → sh_reactions_app_settings → enable_reactions
 *   - MembershipSettings   → sh_membership_settings    → enable_membership
 *   - DownloadLogSettings  → sh_download_log_settings  → enable_download_log
 *
 * variables.php'de define() çağrısından ÖNCE yüklenir:
 *   require_once SH_INCLUDES_PATH . 'apps/download-log/DownloadLogSettings.php';
 *   define("ENABLE_DOWNLOAD_LOG", DownloadLogSettings::getSetting('enable_download_log'));
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-07-25 — Initial release
 *     - App enable/disable toggle
 */
class DownloadLogSettings
{
    public const OPTION_KEY = 'sh_download_log_settings';

    private static ?array $cache = null;

    // ─── Defaults ────────────────────────────────────────────────────────────

    public static function defaults(): array
    {
        return [
            'enable_download_log' => true,  // Varsayılan aktif — mevcut kurulumlar etkilenmesin
        ];
    }

    // ─── Read ─────────────────────────────────────────────────────────────────

    public static function get(): array
    {
        if ( self::$cache !== null ) return self::$cache;

        $saved = get_option( self::OPTION_KEY, [] );
        $saved = is_array( $saved ) ? $saved : [];

        self::$cache = array_merge( self::defaults(), $saved );
        return self::$cache;
    }

    public static function getSetting( string $key, $default = null )
    {
        return self::get()[ $key ] ?? $default ?? self::defaults()[ $key ] ?? null;
    }

    // ─── Write ────────────────────────────────────────────────────────────────

    public static function save( array $data ): void
    {
        $current = self::get();

        $settings = [
            'enable_download_log' => isset( $data['enable_download_log'] )
                ? (bool) $data['enable_download_log']
                : $current['enable_download_log'],
        ];

        update_option( self::OPTION_KEY, $settings, false );
        self::$cache = null;

        do_action( 'sh/download_log/saved', $settings, $current );

        foreach ( $settings as $key => $new_val ) {
            $old_val = $current[ $key ] ?? null;
            if ( $old_val !== $new_val ) {
                do_action( 'sh/download_log/setting_changed', $key, $new_val, $old_val, $settings );
            }
        }
    }

    public static function clearCache(): void
    {
        self::$cache = null;
    }
}
