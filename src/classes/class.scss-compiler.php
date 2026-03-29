<?php

use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\ValueConverter;

/**
 * SCSSCompiler — scssphp wrapper.
 *
 * SCSS dosyalarını CSS'e derler. scssphp/scssphp ^1.13 kullanır.
 * Otomatik compile YOKTUR — sadece açıkça çağrıldığında çalışır.
 *
 * KULLANIM:
 *
 *   // Tam compile (dizin bazlı)
 *   $compiler = new SCSSCompiler(
 *       [SH_STATIC_PATH . 'scss/'],   // SCSS kaynak dizinleri
 *       STATIC_PATH . 'css/',          // CSS çıktı dizini
 *       'SOURCE_MAP_NONE',             // sourcemap: SOURCE_MAP_NONE | SOURCE_MAP_INLINE | SOURCE_MAP_FILE
 *       'compressed'                   // output: compressed | expanded | nested | compact
 *   );
 *   $compiler->set_variables(['primary' => '#ff0000', 'font-size' => '16px']);
 *   $compiler->compile();
 *   $errors = $compiler->get_compile_errors();
 *
 *   // String compile (tek seferlik)
 *   $compiler = new SCSSCompiler();
 *   $css = $compiler->compile_string('.btn { color: darken(#fff, 10%); }');
 *
 *   // Theme entegrasyonu (admin'den tetiklenir)
 *   Theme::scss_compile();
 *
 * @package SaltHareket
 * @since   1.0.0
 */

class SCSSCompiler {

    private array    $scss_dirs;
    private string   $css_dir;
    private string   $cache_dir;
    private array    $compile_errors = [];
    private Compiler $scssc;
    private string   $sourcemaps;

    public function __construct(
        array  $scss_dirs = [],
        string $css_dir = '',
        string $sourcemaps = 'SOURCE_MAP_NONE',
        string $compile_method = 'compressed'
    ) {
        $this->scss_dirs = $scss_dirs;
        $this->css_dir   = $css_dir;
        $this->sourcemaps = $sourcemaps;
        $this->cache_dir  = defined( 'STATIC_PATH' ) ? rtrim( STATIC_PATH, '/' ) . '/cache/' : sys_get_temp_dir() . '/scss_cache/';

        $this->scssc = new Compiler();
        $this->scssc->setOutputStyle( $compile_method );

        if ( ! empty( $scss_dirs ) ) {
            $this->scssc->setImportPaths( $scss_dirs );
        }
    }

    public function get_scss_dirs(): array {
        return $this->scss_dirs;
    }

    public function get_css_dir(): string {
        return $this->css_dir;
    }

    public function get_compile_errors(): array {
        return $this->compile_errors;
    }

    // =========================================================================
    // COMPILE — Dizin bazlı
    // =========================================================================

    public function compile(): void {
        $input_files = [];

        foreach ( $this->scss_dirs as $scss_dir ) {
            if ( ! is_dir( $scss_dir ) ) {
                $this->compile_errors[] = [
                    'file'    => $scss_dir,
                    'message' => 'SCSS directory not found.',
                ];
                continue;
            }

            foreach ( new \DirectoryIterator( $scss_dir ) as $file ) {
                if ( $file->isDot() ) continue;
                $name = $file->getFilename();
                // _ ile başlayanlar partial — compile etme
                if ( str_starts_with( $name, '_' ) ) continue;
                if ( $file->getExtension() !== 'scss' ) continue;
                $input_files[] = $scss_dir . $name;
            }
        }

        foreach ( $input_files as $input ) {
            $output_name = pathinfo( $input, PATHINFO_FILENAME ) . '.css';
            $this->compile_file( $input, $this->css_dir . $output_name );
        }

        // Hata yoksa cache'ten css_dir'a taşı
        if ( empty( $this->compile_errors ) ) {
            $this->flush_cache_to_output();
        }
    }

    // =========================================================================
    // COMPILE — String bazlı
    // =========================================================================

    public function compile_string( string $scss_string ): string {
        if ( empty( trim( $scss_string ) ) ) return '';

        try {
            $result = $this->scssc->compileString( $scss_string );
            return $result->getCss();
        } catch ( \Exception $e ) {
            $this->compile_errors[] = [
                'file'    => 'string_input',
                'message' => $e->getMessage(),
            ];
            return '';
        }
    }

    // =========================================================================
    // VARIABLES
    // =========================================================================

    public function set_variables( array $variables ): void {
        // Boş ve array değerleri filtrele
        $clean = [];
        foreach ( $variables as $key => $value ) {
            if ( is_array( $value ) || empty( $value ) || trim( (string) $value ) === '' ) {
                continue;
            }
            $clean[ $key ] = $value;
        }

        if ( ! empty( $clean ) ) {
            $this->scssc->addVariables(
                array_map( 'ScssPhp\ScssPhp\ValueConverter::parseValue', $clean )
            );
        }
    }

    // =========================================================================
    // NEEDS COMPILING — Manuel kontrol
    // =========================================================================

    public function needs_compiling(): bool {
        $latest_scss = 0;
        $latest_css  = 0;

        foreach ( $this->scss_dirs as $scss_dir ) {
            if ( ! is_dir( $scss_dir ) ) continue;
            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator( $scss_dir, \RecursiveDirectoryIterator::SKIP_DOTS )
            );
            foreach ( $iter as $file ) {
                if ( $file->getExtension() === 'scss' ) {
                    $latest_scss = max( $latest_scss, $file->getMTime() );
                }
            }
        }

        if ( ! empty( $this->css_dir ) && is_dir( $this->css_dir ) ) {
            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator( $this->css_dir, \RecursiveDirectoryIterator::SKIP_DOTS )
            );
            foreach ( $iter as $file ) {
                if ( $file->getExtension() === 'css' ) {
                    $latest_css = max( $latest_css, $file->getMTime() );
                }
            }
        }

        return $latest_scss > $latest_css;
    }

    // =========================================================================
    // WP ENTEGRASYONU — Sadece açıkça çağrıldığında
    // =========================================================================

    /**
     * WP filter'larından variable'ları alıp compile eder.
     * Theme::scss_compile() tarafından çağrılır.
     */
    public function wp_scss_compile(): void {
        $variables = apply_filters( 'wp_scss_variables', [] );

        // Boş/array değerleri filtrele
        $clean = [];
        foreach ( $variables as $key => $value ) {
            if ( is_array( $value ) || empty( $value ) || trim( (string) $value ) === '' ) {
                continue;
            }
            $clean[ $key ] = $value;
        }

        $this->set_variables( $clean );
        $this->compile();
    }

    /**
     * @deprecated Otomatik compile kaldırıldı. Doğrudan wp_scss_compile() kullanın.
     */
    public function wp_scss_needs_compiling(): void {
        $needs = apply_filters( 'wp_scss_needs_compiling', $this->needs_compiling() );
        if ( $needs ) {
            $this->wp_scss_compile();
        }
    }

    // =========================================================================
    // PRIVATE
    // =========================================================================

    private function compile_file( string $in, string $out ): void {
        if ( ! file_exists( $in ) ) {
            $this->compile_errors[] = [
                'file'    => basename( $in ),
                'message' => 'Source file not found: ' . $in,
            ];
            return;
        }

        $this->ensure_dir( $this->cache_dir );

        if ( ! is_writable( $this->cache_dir ) ) {
            $this->compile_errors[] = [
                'file'    => $this->cache_dir,
                'message' => 'Cache directory not writable.',
            ];
            return;
        }

        try {
            // Sourcemap ayarları
            $sourcemap_const = $this->resolve_sourcemap_constant();
            $this->scssc->setSourceMap( $sourcemap_const );

            if ( $sourcemap_const !== Compiler::SOURCE_MAP_NONE ) {
                $map = basename( $out ) . '.map';
                $this->scssc->setSourceMapOptions( [
                    'sourceMapWriteTo'  => $this->css_dir . $map,
                    'sourceMapURL'      => $map,
                    'sourceMapBasepath' => defined( 'ABSPATH' ) ? rtrim( ABSPATH, '/' ) : '',
                    'sourceRoot'        => function_exists( 'home_url' ) ? home_url( '/' ) : '/',
                ] );
            }

            $scss_content = file_get_contents( $in );
            if ( $scss_content === false ) {
                throw new \RuntimeException( 'Cannot read file: ' . $in );
            }

            $result = $this->scssc->compileString( $scss_content, $in );
            file_put_contents( $this->cache_dir . basename( $out ), $result->getCss() );

        } catch ( \Exception $e ) {
            $this->compile_errors[] = [
                'file'    => basename( $in ),
                'message' => $e->getMessage(),
            ];
        }
    }

    private function flush_cache_to_output(): void {
        if ( ! is_dir( $this->css_dir ) ) {
            $this->ensure_dir( $this->css_dir );
        }

        if ( ! is_writable( $this->css_dir ) ) {
            $this->compile_errors[] = [
                'file'    => 'CSS Directory',
                'message' => 'CSS directory not writable: ' . $this->css_dir,
            ];
            return;
        }

        if ( ! is_dir( $this->cache_dir ) ) return;

        foreach ( new \DirectoryIterator( $this->cache_dir ) as $file ) {
            if ( $file->isDot() || $file->getExtension() !== 'css' ) continue;
            $name = $file->getFilename();
            file_put_contents( $this->css_dir . $name, file_get_contents( $this->cache_dir . $name ) );
            @unlink( $this->cache_dir . $name );
        }
    }

    private function resolve_sourcemap_constant(): int {
        $map = [
            'SOURCE_MAP_NONE'   => Compiler::SOURCE_MAP_NONE,
            'SOURCE_MAP_INLINE' => Compiler::SOURCE_MAP_INLINE,
            'SOURCE_MAP_FILE'   => Compiler::SOURCE_MAP_FILE,
        ];

        return $map[ $this->sourcemaps ] ?? Compiler::SOURCE_MAP_NONE;
    }

    private function ensure_dir( string $path ): void {
        if ( ! is_dir( $path ) ) {
            @mkdir( $path, 0755, true );
        }
    }
}
