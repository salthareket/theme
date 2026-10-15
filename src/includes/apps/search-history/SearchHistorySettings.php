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
 * @version 1.0.2
 * @changelog
 *   1.0.2 - 2026-07-04
 *     - PERF: readAcf() içindeki get_option() SaltConfig::get() ile değiştirildi. ~1q → 0q.
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
        self::$cache = array_merge( self::defaults(), is_array($saved) ? $saved : [] );
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

        // SaltConfig cache'ini güncelle — hooks.php'deki sh/search_history/saved listener
        do_action( 'sh/search_history/saved', $settings, $current );
    }

    public static function clearCache(): void { self::$cache = null; }

    /**
     * ACF options'tan oku — ACF field'ları kaldırıldı.
     * @deprecated
     */
    private static function readAcf(): array
    {
        return []; // ACF field'ları silindiğinden devre dışı
    }
}
