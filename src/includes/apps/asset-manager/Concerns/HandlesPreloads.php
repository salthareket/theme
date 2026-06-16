<?php

namespace SaltHareket\AssetManager\Concerns;

/**
 * HandlesPreloads
 *
 * Font preload, preconnect, resource hints.
 * Filter ile dışarıdan eklenebilir.
 *
 * @version 1.0.0
 */
trait HandlesPreloads
{
    public function prepareFontQueue(): void
    {
        $this->queueFontPreloads( STATIC_PATH . 'css/font-faces.css', true );
    }

    public function renderPreloads(): void
    {
        // Preconnect hints
        $preconnects = apply_filters( 'sh_preconnect_domains', [
            'https://fonts.gstatic.com' => [ 'crossorigin' => true ],
            'https://tile.openstreetmap.org' => [],
        ] );

        if ( $preconnects ) {
            echo "\n<!-- Preconnect hints -->\n";
            foreach ( $preconnects as $domain => $attrs ) {
                $attr_str = isset( $attrs['crossorigin'] ) ? ' crossorigin' : '';
                echo '<link rel="preconnect" href="' . esc_url( $domain ) . '"' . $attr_str . '>' . "\n";
            }
        }

        // Preload resources (font + filter ile eklenenler)
        $extra_preloads = apply_filters( 'sh_preload_resources', [] );
        foreach ( $extra_preloads as $item ) {
            $url  = esc_url( $item['url'] ?? '' );
            $as   = esc_attr( $item['as'] ?? 'fetch' );
            $type = isset( $item['type'] ) ? ' type="' . esc_attr( $item['type'] ) . '"' : '';
            $co   = ! empty( $item['crossorigin'] ) ? ' crossorigin' : '';
            echo '<link rel="preload" href="' . $url . '" as="' . $as . '"' . $type . $co . '>' . "\n";
        }

        if ( empty( self::$preloadQueue ) ) return;

        echo "\n<!-- Preload resources -->\n";
        echo implode( "\n", array_unique( self::$preloadQueue ) );
        echo "\n\n";
    }

    public function addToPreload( string $url, string $as = 'font', array $attr = [] ): void
    {
        $key = md5( $url . $as );
        if ( isset( self::$runtimeCache[ 'preloaded_' . $key ] ) ) return;

        $default_attr = ( $as === 'font' ) ? [ 'crossorigin' => 'anonymous' ] : [];
        $final_attr   = array_merge( $default_attr, $attr );

        $attr_string = '';
        foreach ( $final_attr as $name => $value ) {
            $attr_string .= ( $value === true ) ? " {$name}" : " {$name}=\"{$value}\"";
        }

        self::$preloadQueue[ $key ]                    = sprintf( '<link rel="preload" href="%s" as="%s"%s>', $url, $as, $attr_string );
        self::$runtimeCache[ 'preloaded_' . $key ]     = true;
    }

    public function queueFontPreloads( string $css_path, bool $relative = true ): void
    {
        if ( ! file_exists( $css_path ) ) return;

        $css_hash        = md5( $css_path );
        $state_key       = 'font_inline_cache_state_' . $css_hash;
        $master_cache_key = 'font_inline_cache_' . $css_hash;

        $last_state = get_transient( $state_key );
        if ( $last_state !== false && (bool) $last_state !== (bool) $relative ) {
            delete_transient( $master_cache_key );
            delete_transient( $state_key );
        }

        $final_preloads = get_transient( $master_cache_key );

        if ( $final_preloads === false ) {
            $content = file_get_contents( $css_path );
            preg_match_all( '/src:\s*url\([\'"]?([^)]+?)[\'"]?\)\s*format\([\'"]?([^"\')]+)[\'"]?\)/i', $content, $matches, PREG_SET_ORDER );

            $final_preloads = [];
            foreach ( $matches as $match ) {
                $raw_url = strtok( $match[1], '?#' );
                $type    = 'font/' . str_replace( [ 'truetype', 'opentype' ], [ 'ttf', 'otf' ], $match[2] );
                $final_preloads[] = [
                    'url'  => $this->fixSingleUrl( $raw_url, $css_path, $relative ),
                    'type' => $type,
                ];
            }

            set_transient( $master_cache_key, $final_preloads, WEEK_IN_SECONDS );
            set_transient( $state_key, (int) $relative, WEEK_IN_SECONDS );
        }

        foreach ( $final_preloads as $item ) {
            $this->addToPreload( $item['url'], 'font', [ 'type' => $item['type'] ] );
        }
    }
}
