<?php

namespace SaltHareket\SearchHistory;

/**
 * SearchHistorySettings
 *
 * Search History app aktif/pasif ayarını yönetir.
 * wp_options'da 'sh_search_history_settings' key'inde saklanır.
 * ACF fallback desteği ile geçiş dönemi uyumu.
 *
 * variables.php'de define()'lardan ÖNCE yüklenir:
 *   require_once SH_INCLUDES_PATH . 'apps/search-history/SearchHistorySettings.php';
 *   define("ENABLE_SEARCH_HISTORY", \SaltHareket\SearchHistory\SearchHistorySettings::getSetting('enable_search_history'));
 *
 * @version 1.0.1
 * @changelog
 *   1.0.1 - 2026-06-16
 *     - Add: Namespace eklendi (SaltHareket\SearchHistory)
 *   1.0.0 - 2026-06-16
 *     - Add: Initial release — ACF'ten taşındı
 */
class SearchHistorySettings
{
    private const OPTION_KEY = 'sh_search_history_settings';
    private static ?array $cache = null;

    public static function defaults(): array
    {
        return [ 'enable_search_history' => false ];
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
            'enable_search_history' => isset( $data['enable_search_history'] )
                ? (bool) $data['enable_search_history']
                : $current['enable_search_history'],
        ];
        update_option( self::OPTION_KEY, $settings, false );
        self::$cache = null;
    }

    public static function clearCache(): void { self::$cache = null; }

    private static function readAcf(): array
    {
        if ( ! function_exists( 'get_field' ) || ! did_action( 'acf/init' ) ) {
            $v = get_option( 'options_enable_search_history' );
            return $v !== false ? [ 'enable_search_history' => (bool) $v ] : [];
        }
        $v = get_field( 'options_enable_search_history', 'option' );
        return $v !== null ? [ 'enable_search_history' => (bool) $v ] : [];
    }
}
