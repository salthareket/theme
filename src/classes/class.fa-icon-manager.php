<?php

namespace SaltHareket;

/**
 * FaIconManager — Font Awesome webfont → inline SVG dönüşümü
 *
 * Webfont+CSS yaklaşımını terk eder. Sadece kullanılan ikonlar SVG olarak
 * üretilir ve `static/svg/{style}/{name}.svg` olarak kaydedilir.
 * PHP tarafında `fa_icon()` ile inline <svg> olarak HTML'e eklenir.
 *
 * KAYNAK: FA Pro 7.x JS dosyaları (solid.js, regular.js, light.js, brands.js)
 *   Format: var icons = { "heart": [width, height, aliases[], "unicode", "path_d"] }
 *   Bu dosyalar LOCAL'de bulunur, production'a gitmez.
 *
 * ÇIKIŞ: static/svg/{style}/{name}.svg
 *   Örn: static/svg/solid/heart.svg
 *        static/svg/brands/instagram.svg
 *
 * @package SaltHareket
 * @version 1.1.0
 *
 * @changelog
 *   1.1.0 - 2026-07-01
 *     - Fix: JS parse — balanced brace matching (regex lazy match sorunu)
 *     - Fix: Alias desteği (FA 6→7 rename, örn: shopping-basket → basket-shopping)
 *     - Fix: str_replace() count by reference PHP 8 hatası → preg_replace()
 *     - Fix: Backward compatibility — eski SVG cache'te class yoksa ekle
 *   1.0.0 - 2026-07-01
 *     - Add: Initial release
 *     - Add: JS parse → SVG üretim (extractFromJs, generate)
 *     - Add: HTML içerik taraması (detectInContent)
 *     - Add: PAE entegrasyonu için toplu üretim (generateAll)
 *     - Add: fa_icon() global helper + Twig filter/function
 *
 * HOW TO USE:
 *   // Tek ikon render (PHP):
 *   echo fa_icon('heart', 'solid');
 *   echo fa_icon('instagram', 'brands', 'social-icon text-pink');
 *
 *   // Twig:
 *   {{ fa_icon('heart', 'solid') }}
 *   {{ fa_icon('instagram', 'brands', 'text-pink w-5 h-5') }}
 *   {{ 'heart'|fa_icon }}
 *   {{ 'heart'|fa_icon('solid', 'text-red') }}
 *
 *   // PAE entegrasyonu (save_post sonrası):
 *   $icons = FaIconManager::detectInContent($html);
 *   FaIconManager::generateAll($icons);
 */
class FaIconManager
{
    // ─── Sabitler ────────────────────────────────────────────────────────────

    /** FA JS kaynak dosyalarının bulunduğu path */
    const JS_SOURCE_PATH = SH_STATIC_PATH . 'fonts/fontawesome/js/';

    /** Üretilen SVG dosyalarının yazılacağı path */
    const SVG_OUTPUT_PATH = STATIC_PATH . 'svg/';

    /** Desteklenen style'lar → JS dosya adı eşlemesi */
    const STYLE_MAP = [
        'solid'   => 'solid.js',
        'regular' => 'regular.js',
        'light'   => 'light.js',
        'brands'  => 'brands.js',
    ];

    /** Varsayılan style */
    const DEFAULT_STYLE = 'solid';

    /** Bilinmeyen style gelince kullanılacak fallback style */
    const FALLBACK_STYLE = 'regular';

    // ─── Runtime cache ────────────────────────────────────────────────────────

    /** @var array<string, array> Yüklenmiş icon map'leri — style → [name => [w, h, path]] */
    private static array $iconCache = [];

    // =========================================================================
    //  KAYNAK OKUMA — JS → Icon Map
    // =========================================================================

    /**
     * FA JS dosyasından icon map'i parse eder ve cache'ler.
     *
     * @param string $style solid|regular|light|brands
     * @return array<string, array> ['name' => [width, height, path_d]]
     */
    public static function loadStyle( string $style ): array
    {
        // Bilinmeyen style → fallback
        if ( ! isset( self::STYLE_MAP[ $style ] ) ) {
            $style = self::FALLBACK_STYLE;
        }

        if ( isset( self::$iconCache[ $style ] ) ) {
            return self::$iconCache[ $style ];
        }

        $filename = self::STYLE_MAP[ $style ];
        $js_file  = self::JS_SOURCE_PATH . $filename;

        if ( ! file_exists( $js_file ) ) {
            return [];
        }

        $content = file_get_contents( $js_file );
        if ( ! $content ) {
            return [];
        }

        $icons = self::parseJsIconMap( $content );
        self::$iconCache[ $style ] = $icons;

        return $icons;
    }

    /**
     * JS içeriğinden `var icons = {...}` bloğunu parse eder.
     *
     * FA JS formatı:
     *   var icons = {
     *     "heart": [512, 512, [128153, ...], "f004", "M47.6 300.4..."],
     *     "basket-shopping": [576, 512, ["shopping-basket"], "f291", "M288 0..."],
     *   }
     *
     * entry[2] = alias array (string veya number)
     * entry[4] = path_d (string veya array — duotone için)
     *
     * @param string $content JS dosyası içeriği
     * @return array<string, array> ['name' => ['width', 'height', 'path_d']]
     */
    private static function parseJsIconMap( string $content ): array
    {
        // `var icons = {` başlangıcını bul (FA 7.x format)
        $start_pos = strpos( $content, 'var icons = {' );
        if ( $start_pos === false ) {
            $start_pos = strpos( $content, 'var icons={' );
        }

        if ( $start_pos === false ) {
            return [];
        }

        // `{` karakterinin pozisyonunu bul
        $brace_start = strpos( $content, '{', $start_pos );
        if ( $brace_start === false ) {
            return [];
        }

        // Balanced brace matching — regex lazy match büyük dosyalarda çalışmaz
        $depth    = 0;
        $in_str   = false;
        $escape   = false;
        $json_str = '';
        $len      = strlen( $content );

        for ( $i = $brace_start; $i < $len; $i++ ) {
            $ch = $content[ $i ];

            if ( $escape ) {
                $escape = false;
                $json_str .= $ch;
                continue;
            }

            if ( $ch === '\\' && $in_str ) {
                $escape = true;
                $json_str .= $ch;
                continue;
            }

            if ( $ch === '"' ) {
                $in_str = ! $in_str;
            }

            if ( ! $in_str ) {
                if ( $ch === '{' ) {
                    $depth++;
                } elseif ( $ch === '}' ) {
                    $depth--;
                    if ( $depth === 0 ) {
                        $json_str .= $ch;
                        break;
                    }
                }
            }

            $json_str .= $ch;
        }

        if ( empty( $json_str ) ) {
            return [];
        }

        $data = json_decode( $json_str, true );

        if ( ! is_array( $data ) ) {
            return [];
        }

        $icons = [];
        foreach ( $data as $name => $entry ) {
            // entry: [width, height, alias_array, unicode_str, path_or_paths]
            if ( ! is_array( $entry ) || count( $entry ) < 5 ) {
                continue;
            }

            $width  = (int) ( $entry[0] ?? 512 );
            $height = (int) ( $entry[1] ?? 512 );
            $path   = $entry[4] ?? '';

            // Duotone: path array olabilir → join
            if ( is_array( $path ) ) {
                $path = implode( ' ', $path );
            }

            if ( empty( $path ) ) {
                continue;
            }

            $icon_data = [
                'width'  => $width,
                'height' => $height,
                'path'   => $path,
            ];

            // Canonical adı kaydet
            $icons[ $name ] = $icon_data;

            // Alias'ları da kaydet — entry[2] = alias array (string alias'lar)
            // Örn: "basket-shopping": [576, 512, ["shopping-basket"], "f291", "path..."]
            if ( ! empty( $entry[2] ) && is_array( $entry[2] ) ) {
                foreach ( $entry[2] as $alias ) {
                    if ( is_string( $alias ) && ! empty( $alias ) && ! isset( $icons[ $alias ] ) ) {
                        $icons[ $alias ] = $icon_data;
                    }
                }
            }
        }

        return $icons;
    }

    // =========================================================================
    //  SVG ÜRETME
    // =========================================================================

    /**
     * Belirtilen ikonu JS kaynağından parse ederek SVG string döndürür.
     * İkon istenen style'da yoksa FALLBACK_STYLE → DEFAULT_STYLE sırasıyla arar.
     *
     * @param string $name  İkon adı (örn: 'heart')
     * @param string $style solid|regular|light|brands
     * @return string SVG içeriği veya boş string
     */
    public static function extractFromJs( string $name, string $style = self::DEFAULT_STYLE ): string
    {
        if ( ! isset( self::STYLE_MAP[ $style ] ) ) {
            $style = self::FALLBACK_STYLE;
        }

        $icons = self::loadStyle( $style );
        $icon  = $icons[ $name ] ?? null;

        // İstenen style'da yok → fallback style'da dene
        if ( ! $icon && $style !== self::FALLBACK_STYLE ) {
            $icon = ( self::loadStyle( self::FALLBACK_STYLE ) )[ $name ] ?? null;
        }

        // Fallback'te de yoksa solid'de dene
        if ( ! $icon && $style !== self::DEFAULT_STYLE && self::FALLBACK_STYLE !== self::DEFAULT_STYLE ) {
            $icon = ( self::loadStyle( self::DEFAULT_STYLE ) )[ $name ] ?? null;
        }

        if ( ! $icon ) {
            return '';
        }

        return self::buildSvg( $icon['width'], $icon['height'], $icon['path'], $style );
    }

    /**
     * İkonu SVG dosyası olarak `static/svg/{style}/{name}.svg`'ye yazar.
     * Dosya zaten varsa skip eder (overwrite yok).
     *
     * @param string $name
     * @param string $style
     * @param bool   $force Varsa da üzerine yaz
     * @return bool Başarılı mı
     */
    public static function generate( string $name, string $style = self::DEFAULT_STYLE, bool $force = false ): bool
    {
        $output_dir  = self::SVG_OUTPUT_PATH . $style . '/';
        $output_file = $output_dir . $name . '.svg';

        if ( ! $force && file_exists( $output_file ) ) {
            return true;
        }

        $svg = self::extractFromJs( $name, $style );
        if ( empty( $svg ) ) {
            return false;
        }

        if ( ! is_dir( $output_dir ) ) {
            wp_mkdir_p( $output_dir );
        }

        return (bool) file_put_contents( $output_file, $svg );
    }

    /**
     * Icon listesini toplu üretir. PAE'den çağrılır.
     *
     * @param array $icons [['style' => 'solid', 'name' => 'heart'], ...]
     * @return array ['generated' => int, 'skipped' => int, 'failed' => int]
     */
    public static function generateAll( array $icons ): array
    {
        $result = [ 'generated' => 0, 'skipped' => 0, 'failed' => 0 ];

        foreach ( $icons as $icon ) {
            $name  = $icon['name']  ?? '';
            $style = $icon['style'] ?? self::DEFAULT_STYLE;

            if ( empty( $name ) ) {
                $result['failed']++;
                continue;
            }

            $output_file = self::SVG_OUTPUT_PATH . $style . '/' . $name . '.svg';

            if ( file_exists( $output_file ) ) {
                $result['skipped']++;
                continue;
            }

            if ( self::generate( $name, $style ) ) {
                $result['generated']++;
            } else {
                $result['failed']++;
            }
        }

        return $result;
    }

    // =========================================================================
    //  RENDER
    // =========================================================================

    /**
     * İkonu inline SVG olarak render eder.
     * Önce `static/svg/{style}/{name}.svg` dosyasına bakar.
     * Yoksa JS'ten üretip dosyaya yazar, sonra render eder.
     *
     * @param string $name    İkon adı (örn: 'heart')
     * @param string $style   solid|regular|light|brands
     * @param string $classes Ek CSS class'ları
     * @return string HTML <svg>...</svg>
     */
    public static function render( string $name, string $style = self::DEFAULT_STYLE, string $classes = '' ): string
    {
        if ( empty( $name ) ) {
            return '';
        }

        $svg_file = self::SVG_OUTPUT_PATH . $style . '/' . $name . '.svg';

        // Dosya yoksa üret
        if ( ! file_exists( $svg_file ) ) {
            if ( ! self::generate( $name, $style ) ) {
                return '';
            }
        }

        $svg = file_get_contents( $svg_file );
        if ( ! $svg ) {
            return '';
        }

        // Eski cache'te class yoksa ekle (backward compatibility)
        if ( strpos( $svg, 'class=' ) === false ) {
            $svg = str_replace(
                '<svg ',
                '<svg class="fa-icon fa-' . $style . '" ',
                $svg
            );
        }

        // Ek class'ları ekle — preg_replace kullan (PHP 8: str_replace count by-ref hatası)
        if ( ! empty( $classes ) ) {
            if ( preg_match( '/class="([^"]*)"/', $svg, $match ) ) {
                $existing = $match[1];
                $svg = preg_replace(
                    '/class="' . preg_quote( $existing, '/' ) . '"/',
                    'class="' . $existing . ' ' . esc_attr( $classes ) . '"',
                    $svg,
                    1
                );
            }
        }

        return $svg;
    }

    // =========================================================================
    //  HTML TARAMA — PAE entegrasyonu
    // =========================================================================

    /**
     * HTML içeriğinden FA ikon class'larını tespit eder.
     *
     * Desteklenen syntax:
     *   <i class="fa-solid fa-heart">
     *   <i class="fa-brands fa-instagram">
     *   class="... fa-light fa-bell ..."
     *
     * @param string $html HTML içeriği
     * @return array [['style' => 'solid', 'name' => 'heart'], ...]  tekrar yok
     */
    public static function detectInContent( string $html ): array
    {
        $found = [];

        $style_pattern = implode( '|', array_keys( self::STYLE_MAP ) );
        $pattern       = '/fa-(' . $style_pattern . ')\s[^"\']*fa-([\w-]+)/';

        if ( preg_match_all( $pattern, $html, $matches, PREG_SET_ORDER ) ) {
            foreach ( $matches as $match ) {
                $style = $match[1];
                $name  = $match[2];

                if ( ! isset( self::STYLE_MAP[ $style ] ) || empty( $name ) ) {
                    continue;
                }

                if ( in_array( $name, self::getModifierClasses(), true ) ) {
                    continue;
                }

                $key = $style . ':' . $name;
                if ( ! isset( $found[ $key ] ) ) {
                    $found[ $key ] = [ 'style' => $style, 'name' => $name ];
                }
            }
        }

        return array_values( $found );
    }

    // =========================================================================
    //  YARDIMCILAR
    // =========================================================================

    /**
     * SVG string'i oluşturur.
     * class="fa-icon fa-{style}" eklenir — stil _fa-svg.scss'den gelir.
     */
    private static function buildSvg( int $width, int $height, string $path_d, string $style = self::DEFAULT_STYLE ): string
    {
        $path_d = str_replace( '"', '&quot;', $path_d );

        return sprintf(
            '<svg class="fa-icon fa-%s" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d"'
            . ' aria-hidden="true" focusable="false">'
            . '<path fill="currentColor" d="%s"/>'
            . '</svg>',
            $style,
            $width,
            $height,
            $path_d
        );
    }

    /**
     * FA modifier class'larının listesi — bunlar ikon adı değil.
     *
     * @return string[]
     */
    private static function getModifierClasses(): array
    {
        return [
            'xs', 'sm', 'lg', 'xl', '2xl',
            '1x', '2x', '3x', '4x', '5x', '6x', '7x', '8x', '9x', '10x',
            'fw', 'spin', 'spin-pulse', 'pulse', 'bounce', 'fade', 'beat', 'shake', 'flip',
            'rotate-90', 'rotate-180', 'rotate-270',
            'flip-horizontal', 'flip-vertical', 'flip-both',
            'border', 'pull-left', 'pull-right',
            'stack', 'stack-1x', 'stack-2x', 'inverse',
            'li', 'ul', 'beat-fade', 'spin-reverse',
        ];
    }

    /**
     * SVG output dizinlerini hazırlar.
     */
    public static function ensureDirectories(): void
    {
        foreach ( array_keys( self::STYLE_MAP ) as $style ) {
            $dir = self::SVG_OUTPUT_PATH . $style . '/';
            if ( ! is_dir( $dir ) ) {
                wp_mkdir_p( $dir );
            }
        }
    }

    /**
     * Belirli bir style için tüm ikonları toplu üretir.
     * Admin aracı / debug için.
     *
     * @param string $style solid|regular|light|brands
     * @return int Üretilen dosya sayısı
     */
    public static function generateAllForStyle( string $style ): int
    {
        $icons = self::loadStyle( $style );
        $count = 0;

        self::ensureDirectories();

        foreach ( array_keys( $icons ) as $name ) {
            if ( self::generate( $name, $style ) ) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Runtime cache'i temizler (test/debug için).
     */
    public static function clearCache(): void
    {
        self::$iconCache = [];
    }

    /**
     * Belirtilen style için tüm icon isimlerini döndürür.
     * Admin icon picker için kullanılır.
     *
     * @param string $style solid|regular|light|brands
     * @return string[] Icon isimleri array'i — ['heart', 'star', ...]
     */
    public static function getIconNames( string $style = self::DEFAULT_STYLE ): array
    {
        // Önce statik JSON cache dosyasına bak — JS parse'dan çok daha hızlı
        $cache_file = self::SVG_OUTPUT_PATH . 'icon-names-' . sanitize_key( $style ) . '.json';
        if ( file_exists( $cache_file ) ) {
            $names = json_decode( file_get_contents( $cache_file ), true );
            if ( is_array( $names ) && count( $names ) > 0 ) {
                return $names;
            }
        }

        // Cache yoksa JS'ten parse et ve cache'e yaz
        $icons = self::loadStyle( $style );
        $names = array_keys( $icons );

        // JSON cache'e yaz
        if ( $names ) {
            self::ensureDirectories();
            file_put_contents( $cache_file, json_encode( $names ) );
        }

        return $names;
    }
}
