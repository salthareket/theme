<?php

namespace SaltHareket\Reactions;

/**
 * ReactionsAppSettings
 *
 * Reactions app aktif/pasif ayarını yönetir.
 * (ReactionsSettings zaten reaction tiplerini yönetiyor — bu sadece enable toggle)
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-06-16 — Initial release, ACF'ten taşındı
 */
class ReactionsAppSettings
{
    private const OPTION_KEY = 'sh_reactions_app_settings';
    private static ?array $cache = null;

    public static function defaults(): array
    {
        return [ 'enable_reactions' => false ];
    }

    public static function get(): array
    {
        if ( self::$cache !== null ) return self::$cache;
        $saved = get_option( self::OPTION_KEY, [] );
        $acf   = self::readAcf();
        self::$cache = array_merge( self::defaults(), $acf, is_array($saved) ? $saved : [] );
        return self::$cache;
    }

    public static function getSetting( string $key, $default = null )
    {
        return self::get()[ $key ] ?? $default ?? self::defaults()[ $key ] ?? null;
    }

    public static function save( array $data ): void
    {
        $current  = self::get();
        $settings = [
            'enable_reactions' => isset( $data['enable_reactions'] )
                ? (bool) $data['enable_reactions']
                : $current['enable_reactions'],
        ];
        update_option( self::OPTION_KEY, $settings, false );
        self::$cache = null;

        do_action( 'sh/reactions/saved', $settings, $current );
        foreach ( $settings as $key => $new_val ) {
            $old_val = $current[ $key ] ?? null;
            if ( $old_val !== $new_val ) {
                do_action( 'sh/reactions/setting_changed', $key, $new_val, $old_val, $settings );
            }
        }
    }

    public static function clearCache(): void { self::$cache = null; }

    private static function readAcf(): array
    {
        if ( ! function_exists( 'get_field' ) || ! did_action( 'acf/init' ) ) {
            $v = get_option( 'options_enable_reactions' );
            return $v !== false ? [ 'enable_reactions' => (bool) $v ] : [];
        }
        $v = get_field( 'options_enable_reactions', 'option' );
        return $v !== null ? [ 'enable_reactions' => (bool) $v ] : [];
    }
}
