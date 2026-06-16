<?php

namespace SaltHareket\AssetManager\Concerns;

/**
 * HandlesHelpers
 *
 * Shared helper metodlar — URL fix, locale, critical CSS remover, cache purge.
 *
 * @version 1.0.0
 */
trait HandlesHelpers
{
    // ─── Critical CSS Remover ─────────────────────────────────────────────────

    public function renderCriticalCssRemover(): void
    {
        if ( is_admin() ) return;

        // Minimal critical CSS — sadece LCP için
        echo '<style id="critical-css-inline">';
        echo apply_filters( 'sh_critical_css', 'img{max-width:100%;height:auto;}body{font-display:swap;}' );
        echo '</style>' . "\n";

        // Inline head JS (filter-based)
        $inline_head_js = apply_filters( 'sh_inline_head_js', '' );
        if ( $inline_head_js ) {
            echo '<script id="sh-inline-head-js">' . $inline_head_js . '</script>' . "\n";
        }

        ?>
<script id="critical-css-remover">
(function(){
    'use strict';
    function removeCriticalCSS(){
        ['lcp-critical','rocket-critical-css','wp-rocket-critical-css'].forEach(function(id){
            var el = document.getElementById(id);
            if (el) el.parentNode.removeChild(el);
        });
    }
    if (document.readyState === 'complete') {
        removeCriticalCSS();
    } else {
        document.addEventListener('DOMContentLoaded', function(){ setTimeout(removeCriticalCSS, 100); });
        window.addEventListener('load', function(){ setTimeout(removeCriticalCSS, 500); });
    }
})();
</script>
        <?php
    }

    // ─── Cache Purge ──────────────────────────────────────────────────────────

    public function handleManualPurge(): void
    {
        if ( ! isset( $_GET['purge_assets'] ) || ! current_user_can( 'manage_options' ) ) return;

        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_font_inline_cache_%'
                OR option_name LIKE '_transient_timeout_font_inline_cache_%'
                OR option_name LIKE '_transient_font_inline_cache_state_%'
                OR option_name LIKE '_transient_timeout_font_inline_cache_state_%'"
        );

        $cache_folder = rtrim( STATIC_PATH, '/' ) . '/css/cache';
        if ( file_exists( $cache_folder ) ) {
            array_map( 'unlink', glob( "{$cache_folder}/*.css" ) ?: [] );
        }

        self::$runtimeCache = [];
        self::$preloadQueue = [];

        wp_die( '✅ Asset cache cleared!' );
    }

    // ─── Locale ───────────────────────────────────────────────────────────────

    public function smartEnqueueLocale( string $type, string $lang ): void
    {
        if ( empty( $lang ) ) return;

        $rel_path = $type === 'css'
            ? "css/locale-{$lang}.css"
            : "js/locale/{$lang}.js";

        if ( ! file_exists( STATIC_PATH . $rel_path ) ) return;

        if ( $type === 'css' ) {
            wp_enqueue_style( 'locale', STATIC_URL . $rel_path, [], $this->version );
        } else {
            wp_enqueue_script( 'locale', STATIC_URL . $rel_path, [], null, true );
        }
    }

    // ─── URL Helpers ──────────────────────────────────────────────────────────

    public function fixSingleUrl( string $url_path, string $base_file_path, bool $relative = true ): string
    {
        $raw_home_url = network_site_url();
        $subfolder    = rtrim( parse_url( $raw_home_url, PHP_URL_PATH ) ?: '', '/' );
        $theme_dir    = wp_normalize_path( get_template_directory() );
        $theme_name   = basename( $theme_dir );
        $base_dir     = wp_normalize_path( dirname( $base_file_path ) );

        if ( str_contains( $url_path, '/wp-content/' ) ) {
            $clean_path = ( $subfolder !== '' && str_starts_with( $url_path, $subfolder ) )
                ? $url_path
                : $subfolder . strstr( $url_path, '/wp-content/' );
        } else {
            $abs_path = wp_normalize_path( realpath( $base_dir . DIRECTORY_SEPARATOR . $url_path ) );
            if ( ! $abs_path || ! str_starts_with( $abs_path, $theme_dir ) ) return $url_path;
            $rel_path   = ltrim( str_replace( [ '\\', $theme_dir ], [ '/', '' ], $abs_path ), '/' );
            $clean_path = $subfolder . "/wp-content/themes/{$theme_name}/{$rel_path}";
        }

        if ( $relative ) return $clean_path;

        $host = rtrim( str_replace( $subfolder, '', $raw_home_url ), '/' );
        return $host . $clean_path;
    }

    public function fixCssPaths( string $css, string $url ): string
    {
        return preg_replace_callback(
            '/url\((["\']?)(?!https?:|data:)([^)\'"]+)\1\)/i',
            function ( $m ) use ( $url ) {
                return "url({$m[1]}" . $this->fixSingleUrl( $m[2], $url ) . "{$m[1]})";
            },
            $css
        );
    }

    // ─── Cache Info ───────────────────────────────────────────────────────────

    /**
     * Inline CSS cache bilgisi döndür.
     */
    public static function getCacheInfo(): array
    {
        $cache_dir = rtrim( STATIC_PATH, '/' ) . '/css/cache/';
        $files     = glob( $cache_dir . '*-inline.css' ) ?: [];
        $total     = 0;
        foreach ( $files as $f ) $total += filesize( $f );

        return [
            'count'      => count( $files ),
            'total_size' => size_format( $total, 2 ),
            'dir'        => $cache_dir,
            'files'      => array_map( fn( $f ) => [
                'name'     => basename( $f ),
                'size'     => size_format( filesize( $f ), 1 ),
                'modified' => wp_date( 'Y-m-d H:i', filemtime( $f ) ),
            ], $files ),
        ];
    }
}
