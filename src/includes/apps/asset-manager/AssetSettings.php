<?php

namespace SaltHareket\AssetManager;

/**
 * AssetSettings
 *
 * Asset Manager ayarlarını yönetir — Development mode, logging, CSS/JS build ve cleanup.
 * wp_options'da 'sh_asset_manager_settings' key'inde saklanır.
 *
 * @version 1.1.0
 *
 * @changelog
 *   1.1.0 - 2026-07-05
 *     - Add: enable_development, enable_logs, enable_console_logs, enable_twig_cache,
 *            seperate_css, seperate_js, inline_css, inline_js — ACF'den taşındı
 *     - Add: save() → sh/asset/saved + sh/asset/setting_changed hook'larını fire eder
 *     - Add: readAcfLegacy() — SaltConfig üzerinden migration
 *
 * ── CONSTANT'LAR (variables.php'de define edilir) ──────────────────────────
 *
 *   ENABLE_DEVELOPMENT   — Development mode açık mı
 *   ENABLE_LOGS         — PHP error_log() ile tema logları (ENABLE_DEVELOPMENT gerektirir)
 *   ENABLE_CONSOLE_LOGS — site_config["debug"] = true gönderir (ENABLE_DEVELOPMENT gerektirir)
 *   ENABLE_TWIG_CACHE   — Twig template cache
 *   SEPERATE_CSS        — Sayfa bazlı CSS ayrıştırma (PAE gerekli)
 *   SEPERATE_JS         — Sayfa bazlı JS ayrıştırma (PAE gerekli)
 *   INLINE_CSS          — Ayrıştırılmış CSS'i inline et (SEPERATE_CSS gerektirir)
 *   INLINE_JS           — Ayrıştırılmış JS'i inline et (SEPERATE_JS gerektirir)
 *
 * ── KULLANIM ───────────────────────────────────────────────────────────────
 *
 * PHP log (tema içinde):
 *   if (defined('ENABLE_LOGS') && ENABLE_LOGS) {
 *       error_log('[MyModule] Something happened: ' . $value);
 *   }
 *
 * JS console log (site_config["debug"] üzerinden):
 *   // site_config["debug"] === true olunca JS tarafında tema kendi log'larını yazar
 *   // theme.js veya custom JS'te:
 *   if (window.shConfig && window.shConfig.debug) {
 *       console.log('[MyModule] Debug info:', data);
 *   }
 *
 * NOT: console.log("herhangi bir şey") yazsanız her zaman görünür.
 * ENABLE_CONSOLE_LOGS sadece tema'nın kendi debug çıktılarını kontrol eder.
 *
 * ── HOOK'LAR ───────────────────────────────────────────────────────────────
 *
 *   sh/asset/saved              ($settings, $previous)
 *   sh/asset/setting_changed    ($key, $new_val, $old_val, $all_settings)
 *
 *   Örnek:
 *   add_action('sh/asset/setting_changed', function($key, $new, $old, $all) {
 *       if ($key === 'enable_development' && $new) {
 *           // production açıldı → minify başlatabilirsin
 *       }
 *   }, 10, 4);
 *
 * @package SaltHareket\AssetManager
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
            // ── Production & Logging ──────────────────────────────────────────────
            'enable_development'   => false,  // Development mode (minify, cache, logs etkinleşir)
            'enable_logs'         => false,  // PHP error_log() ile theme logları
            'enable_console_logs' => false,  // JS console.log() debug çıktısı
            'enable_twig_cache'   => false,  // Twig template cache

            // ── CSS/JS Separation (PAE ile sayfa-özel asset) ─────────────────────
            'seperate_css'        => false,  // Sayfa bazlı CSS ayrıştırma (PAE gerekli)
            'seperate_js'         => false,  // Sayfa bazlı JS ayrıştırma (PAE gerekli)
            'inline_css'          => false,  // Ayrıştırılmış CSS'i inline inject et
            'inline_js'           => false,  // Ayrıştırılmış JS'i inline inject et

            // ── Cleanup ───────────────────────────────────────────────────────────
            'remove_global_styles'        => 'auto',  // 'auto' | true | false
            'remove_block_styles'         => 'auto',
            'remove_classic_theme_styles' => false,
            'remove_woocommerce_styles'   => false,
            'block_google_fonts'          => true,

            // ── Lazy / Defer ──────────────────────────────────────────────────────
            'css_lazy_load'         => false,
            'js_defer'              => false,

            // ── Auto Fetch ────────────────────────────────────────────────────────
            'auto_fetch'            => false,  // Sayfa CSS/JS dosyaları silinmişse ziyarette otomatik yeniden oluştur

            // Extra handles/patterns (filter ile de eklenebilir)
            'lazy_css_handles'      => [],
            'defer_js_handles'      => [],
            'dequeue_styles'        => [],
            'dequeue_scripts'       => [],
        ];

        // SaltConfig PHP cache'inden oku — DB sorgusu yok (sh_asset_manager_settings target_keys'de)
        $saved = \SaltHareket\SaltConfig::cfg_or_option( self::OPTION_KEY );
        $saved = is_array( $saved ) ? $saved : [];

        // ACF fallback — sadece henüz app'e taşınmamış legacy key'ler
        $acf = [
            // Production flags — ACF'den migration (field'lar silinince bu da kalkar)
            'enable_development'   => self::readAcfLegacy( 'options_enable_development' ),
            'enable_logs'         => self::readAcfLegacy( 'options_enable_logs' ),
            'enable_console_logs' => self::readAcfLegacy( 'options_enable_console_logs' ),
            'enable_twig_cache'   => self::readAcfLegacy( 'options_enable_twig_cache' ),
            'seperate_css'        => self::readAcfLegacy( 'options_seperate_css' ),
            'seperate_js'         => self::readAcfLegacy( 'options_seperate_js' ),
            'inline_css'          => self::readAcfLegacy( 'options_inline_css' ),
            'inline_js'           => self::readAcfLegacy( 'options_inline_js' ),
            // Cleanup
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

        $enable_development = ! empty( $data['enable_development'] );

        // Logs backup/restore mekanizması:
        // development false olunca logs değerlerini sıfırla ama backup'la
        // development tekrar true olunca backup'tan geri yükle
        $raw_backup = get_option( self::OPTION_KEY . '_logs_backup', null );
        $logs_backup = is_array( $raw_backup ) ? $raw_backup : null;

        if ( ! $enable_development ) {
            // Development kapatılıyor — mevcut log değerlerini yedekle (sadece gerçekten true olanları)
            $backup_logs  = ! empty( $data['enable_logs'] )         ? true : ( $current['enable_logs']         ?? false );
            $backup_cons  = ! empty( $data['enable_console_logs'] ) ? true : ( $current['enable_console_logs'] ?? false );
            // Önceki backup yoksa ya da şu an açıksa yedekle
            if ( $backup_logs || $backup_cons || ! $logs_backup ) {
                update_option( self::OPTION_KEY . '_logs_backup', [
                    'enable_logs'         => $backup_logs,
                    'enable_console_logs' => $backup_cons,
                ], false );
            }
            // Development kapalıyken loglar her zaman false
            $enable_logs         = false;
            $enable_console_logs = false;
        } else {
            // Development açılıyor — backup varsa geri yükle
            if ( $logs_backup ) {
                $enable_logs         = (bool) ( $logs_backup['enable_logs']         ?? false );
                $enable_console_logs = (bool) ( $logs_backup['enable_console_logs'] ?? false );
                delete_option( self::OPTION_KEY . '_logs_backup' );
            } else {
                $enable_logs         = ! empty( $data['enable_logs'] );
                $enable_console_logs = ! empty( $data['enable_console_logs'] );
            }
        }

        $settings = [
            // Production & Logging
            'enable_development'           => $enable_development,
            'enable_logs'                 => $enable_logs,
            'enable_console_logs'         => $enable_console_logs,
            // enable_twig_cache — sadece açıkça gönderilmişse değiştir, yoksa mevcut değeri koru
            'enable_twig_cache'           => isset( $data['enable_twig_cache'] ) ? ! empty( $data['enable_twig_cache'] ) : (bool) ( $current['enable_twig_cache'] ?? false ),
            // CSS/JS Separation — sadece açıkça gönderilmişse değiştir, yoksa mevcut değeri koru
            'seperate_css'                => isset( $data['seperate_css'] ) ? ! empty( $data['seperate_css'] ) : (bool) ( $current['seperate_css'] ?? false ),
            'seperate_js'                 => isset( $data['seperate_js'] ) ? ! empty( $data['seperate_js'] ) : (bool) ( $current['seperate_js'] ?? false ),
            'inline_css'                  => isset( $data['inline_css'] ) ? ! empty( $data['inline_css'] ) : (bool) ( $current['inline_css'] ?? false ),
            'inline_js'                   => isset( $data['inline_js'] ) ? ! empty( $data['inline_js'] ) : (bool) ( $current['inline_js'] ?? false ),
            // Cleanup — sadece açıkça gönderilmişse değiştir
            'remove_global_styles'        => isset( $data['remove_global_styles'] ) && in_array( $data['remove_global_styles'], [ 'auto', '1', '0' ], true ) ? $data['remove_global_styles'] : ( $current['remove_global_styles'] ?? 'auto' ),
            'remove_block_styles'         => isset( $data['remove_block_styles'] ) && in_array( $data['remove_block_styles'], [ 'auto', '1', '0' ], true ) ? $data['remove_block_styles'] : ( $current['remove_block_styles'] ?? 'auto' ),
            'remove_classic_theme_styles' => isset( $data['remove_classic_theme_styles'] ) ? ! empty( $data['remove_classic_theme_styles'] ) : (bool) ( $current['remove_classic_theme_styles'] ?? false ),
            'remove_woocommerce_styles'   => isset( $data['remove_woocommerce_styles'] ) ? ! empty( $data['remove_woocommerce_styles'] ) : (bool) ( $current['remove_woocommerce_styles'] ?? false ),
            'block_google_fonts'          => isset( $data['block_google_fonts'] ) ? ! empty( $data['block_google_fonts'] ) : (bool) ( $current['block_google_fonts'] ?? true ),
            'css_lazy_load'               => isset( $data['css_lazy_load'] ) ? ! empty( $data['css_lazy_load'] ) : (bool) ( $current['css_lazy_load'] ?? false ),
            'js_defer'                    => isset( $data['js_defer'] ) ? ! empty( $data['js_defer'] ) : (bool) ( $current['js_defer'] ?? false ),
            'auto_fetch'                  => isset( $data['auto_fetch'] ) ? ! empty( $data['auto_fetch'] ) : (bool) ( $current['auto_fetch'] ?? false ),
            'lazy_css_handles'            => isset( $data['lazy_css_handles'] ) ? array_filter( array_map( 'sanitize_key', (array) $data['lazy_css_handles'] ) ) : ( $current['lazy_css_handles'] ?? [] ),
            'defer_js_handles'            => isset( $data['defer_js_handles'] ) ? array_filter( array_map( 'sanitize_key', (array) $data['defer_js_handles'] ) ) : ( $current['defer_js_handles'] ?? [] ),
            'dequeue_styles'              => isset( $data['dequeue_styles'] ) ? array_filter( array_map( 'sanitize_key', (array) $data['dequeue_styles'] ) ) : ( $current['dequeue_styles'] ?? [] ),
            'dequeue_scripts'             => isset( $data['dequeue_scripts'] ) ? array_filter( array_map( 'sanitize_key', (array) $data['dequeue_scripts'] ) ) : ( $current['dequeue_scripts'] ?? [] ),
        ];

        update_option( self::OPTION_KEY, $settings, false );
        self::$cache = null;

        // SaltConfig cache'ini de yenile (ENABLE_DEVELOPMENT vs. orada da var)
        if ( class_exists( '\\SaltHareket\\SaltConfig' ) ) {
            \SaltHareket\SaltConfig::generate();
        }

        // Hook'ları fire et
        do_action( 'sh/asset/saved', $settings, $current );

        foreach ( $settings as $key => $new_val ) {
            $old_val = $current[ $key ] ?? null;
            if ( $old_val !== $new_val ) {
                do_action( 'sh/asset/setting_changed', $key, $new_val, $old_val, $settings );
            }
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
        $val = get_option( $key );
        if ( $val !== false ) return $val;
        $val = get_option( 'options_' . $key );
        if ( $val !== false ) return $val;
        return null;
    }

    /**
     * ACF options'tan eski key'leri oku (migration için).
     * ACF field'ı silinince null döner, saved değer devreye girer.
     */
    private static function readAcfLegacy( string $options_key )
    {
        // SaltConfig PHP cache'inden oku — DB sorgusu yok
        if ( class_exists( '\\SaltHareket\\SaltConfig' ) ) {
            $val = \SaltHareket\SaltConfig::get( $options_key );
            if ( $val !== false && $val !== null ) return $val;
        }
        return null;
    }
}
