<?php

namespace SaltHareket\AssetManager\Concerns;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * HandlesCompile — JS/CSS derleme işlemleri
 *
 * SCSS compile, JS/CSS minify, webpack, font-face relocation,
 * common CSS split ve etkilenen sayfa yenileme işlemlerini kapsar.
 *
 * @package    SaltHareket\AssetManager
 * @version    1.0.0
 * @since      2026-07-08
 *
 * HOW TO USE:
 *   AssetManager sınıfında `use HandlesCompile;` ile kullanılır.
 *   Doğrudan: AssetManager::getInstance()->compile_js_css()
 *   Statik proxy: \SaltHareket\AssetManager\AssetManager::compile_js_css()
 *
 * @example
 *   AssetManager::getInstance()->compile_js_css(true);   // notice'larla
 *   AssetManager::getInstance()->compile_common_css();
 *   AssetManager::getInstance()->refetch_affected_pages(['swiper', 'leaflet']);
 */
trait HandlesCompile
{
    // =========================================================
    //  EXTRACT TRANSLATIONS
    // =========================================================

    // =========================================================
    //  COMPILE FRONTEND/BACKEND METHODS
    // =========================================================

    /**
     * Frontend ve backend PHP/JS method dosyalarını derler.
     *
     * @param  bool $with_notices
     * @return array{success: bool, messages: array}
     */
    public function compile_methods( bool $with_notices = false ): array {
        $result = [ 'success' => true, 'messages' => [] ];

        if ( function_exists( 'rocket_clean_minify' ) ) {
            rocket_clean_minify();
        }

        if ( ! class_exists( 'SaltHareket\MethodClass' ) ) {
            require_once SH_CLASSES_PATH . 'class.methods.php';
        }

        $methods  = new \SaltHareket\MethodClass();
        $frontend = $methods->createFiles( false );
        $admin    = $methods->createFiles( false, 'admin' );

        if ( $frontend || $admin ) {
            $errors = array_merge( $frontend ?: [], $admin ?: [] );
            foreach ( $errors as $err ) {
                $result['messages'][] = [ 'type' => 'error', 'text' => esc_html( $err['message'] ?? '' ) ];
            }
            $result['messages'][] = [ 'type' => 'warning', 'text' => 'Only JS Frontend/Backend methods compiled (PHP errors occurred).' ];
            if ( $with_notices && function_exists( 'add_admin_notice' ) ) {
                foreach ( $errors as $err ) add_admin_notice( $err['message'] ?? '', 'error' );
                add_admin_notice( 'Only JS Frontend/Backend methods compiled!', 'success' );
            }
        } else {
            $result['messages'][] = [ 'type' => 'success', 'text' => 'PHP & JS Frontend/Backend methods compiled!' ];
            if ( $with_notices && function_exists( 'add_admin_notice' ) ) {
                add_admin_notice( 'PHP & JS Frontend/Backend methods compiled!', 'success' );
            }
        }

        return $result;
    }

    // =========================================================
    //  COMPILE JS & CSS
    // =========================================================

    /**
     * SCSS compile + JS/CSS minify + webpack + font-face + common CSS split.
     *
     * @param  bool $with_notices  true → add_admin_notice ile bildir
     * @return array{
     *     success: bool,
     *     messages: array<array{type:string, text:string}>,
     *     updated_plugins: string[],
     *     pages_fetched: int
     * }
     */
    public function compile_js_css( bool $with_notices = false ): array {
        $result = [
            'success'         => true,
            'messages'        => [],
            'updated_plugins' => [],
            'pages_fetched'   => 0,
        ];

        $is_local = function_exists('isLocalhost') ? isLocalhost() : false;

        // ── 1. WP Rocket cache bust ───────────────────────────────────────────
        if ( function_exists( 'rocket_clean_minify' ) ) {
            rocket_clean_minify();
        }

        // ── 2. SCSS compile ───────────────────────────────────────────────────
        if ( class_exists( 'ScssPhp\ScssPhp\Compiler' ) ) {
            $compile_errors = \SaltHareket\Theme::scss_compile();
            if ( $compile_errors ) {
                $result['success']    = false;
                $msg  = '<strong>Compiling Error</strong><br>';
                $msg .= esc_html( $compile_errors[0]['message'] );
                @file_put_contents( WP_CONTENT_DIR . '/compiler_error.log', $compile_errors[0]['message'], FILE_APPEND );
                $result['messages'][] = [ 'type' => 'error', 'text' => $msg ];
                if ( $with_notices && function_exists( 'add_admin_notice' ) ) {
                    add_admin_notice( $msg, 'error' );
                }
            } else {
                $tz  = wp_timezone();
                $now = new \DateTime( 'now', $tz );
                $msg = 'SCSS & JS files compiled on <strong>' . $now->format( 'Y-m-d H:i:s' ) . '</strong>';
                $result['messages'][] = [ 'type' => 'success', 'text' => $msg ];
                if ( $with_notices && function_exists( 'add_admin_notice' ) ) {
                    add_admin_notice( $msg, 'success' );
                }
            }
        } else {
            $msg = 'ScssPhp compiler not installed — SCSS not compiled.';
            $result['messages'][] = [ 'type' => 'error', 'text' => $msg ];
            if ( $with_notices && function_exists( 'add_admin_notice' ) ) {
                add_admin_notice( $msg, 'error' );
            }
        }

        // ── 3. JS/CSS minify ──────────────────────────────────────────────────
        $minifier        = new \SaltHareket\AssetManager\SaltMinifier( false, $is_local );
        $updated_plugins = $minifier->init();
        $result['updated_plugins'] = $updated_plugins ?: [];

        if ( $updated_plugins ) {
            $msg = 'Updated plugins: ' . implode( ', ', $updated_plugins );
            $result['messages'][] = [ 'type' => 'warning', 'text' => $msg ];
            if ( $with_notices && function_exists( 'add_admin_notice' ) ) {
                add_admin_notice( $msg, 'warning' );
            }
        }

        // ── 4. Webpack (sadece local dev) ─────────────────────────────────────
        if ( $is_local ) {
            $this->_run_webpack( $with_notices, $result );
        }

        // ── 5. Font face relocation ───────────────────────────────────────────
        $css_dir = get_stylesheet_directory() . '/static/css/';
        $minifier->relocateFontFaces( $css_dir . 'font-faces.css' );
        $minifier->clearFontfaces( $css_dir . 'main-combined.css' );
        $minifier->clearFontfaces( $css_dir . 'main-combined-rtl.css' );

        // ── 6. Etkilenen sayfaları yeniden fetch ──────────────────────────────
        if ( $updated_plugins ) {
            if ( class_exists( '\\Update' ) && \Update::tasks_completed() ) {
                $msg = 'Plugins updated — please manually re-fetch pages: [' . implode( ', ', $updated_plugins ) . ']';
                $result['messages'][] = [ 'type' => 'info', 'text' => $msg ];
                if ( $with_notices && function_exists( 'add_admin_notice' ) ) {
                    add_admin_notice( $msg, 'success' );
                }
            } else {
                $result_fetch = $this->refetch_affected_pages( $updated_plugins );
                $affected     = $result_fetch['affected_count'] ?? 0;
                $count        = $result_fetch['fetched_count']  ?? 0;
                $result['pages_fetched'] = $count;
                if ( $affected > 0 ) {
                    $msg = $affected . ' pages affected, ' . $count . ' re-fetched for plugin updates.';
                    $result['messages'][] = [ 'type' => 'success', 'text' => $msg ];
                    if ( $with_notices && function_exists( 'add_admin_notice' ) ) {
                        add_admin_notice( $msg, 'success' );
                    }
                }
            }
        }

        // ── 7. Manifest purge ─────────────────────────────────────────────────
        $minifier->purge_page_assets_manifest();

        // ── 8. Common CSS split ───────────────────────────────────────────────
        $this->compile_common_css();

        return $result;
    }

    // =========================================================
    //  COMMON CSS SPLIT
    // =========================================================

    /**
     * Header/footer HTML'i fetch edip bootstrap CSS'ini purge eder,
     * common-all.css üretir.
     *
     * @return void
     */
    public function compile_common_css(): void {
        $site_url = home_url( '/' );

        // _salt_compile_templates=1 parametresi ile fetch yaparken resolve_header/resolve_footer
        // default template'i zorla yukler — anasayfanin kendi template seciminden bagimsiz.
        // X-Internal-Fetch: 1 header'i IS_INTERNAL_FETCH=true yapar (variables.php'de define).
        $fetch_url = add_query_arg( '_salt_compile_templates', '1', $site_url );

        $options  = [
            'http' => [
                'header' => "User-Agent: Mozilla/5.0 (SaltHareket Compiler)\r\nX-Internal-Fetch: 1\r\n",
            ],
        ];
        $context     = stream_context_create( $options );
        $html_output = @file_get_contents( $fetch_url, false, $context );

        if ( ! $html_output ) {
            return;
        }

        $dom   = \voku\helper\HtmlDomParser::str_get_html( $html_output );
        $parts = [
            'header_bs' => [
                // Önceki kaynak: STATIC_PATH . 'css/bootstrap-customized.css'
                // Sadece Bootstrap içeriyordu — tema class'ları (offcanvas, btn-*, component'ler)
                // girmiyor, scope (header#header) zaten filtreli olduğu için boyut sorun değil.
                'css'         => STATIC_PATH . 'css/main-combined.css',
                'selector'    => 'header',
                'filename'    => 'common-header-bs.css',
                'scope'       => 'header#header',
                'ignore_css'  => [],
                'ignore_tags' => true,
                'white_list'  => [],
                'black_list'  => [],
            ],
            'footer_bs' => [
                // Önceki kaynak: STATIC_PATH . 'css/bootstrap-customized.css'
                // Aynı sebep — tema class'ları için main-combined.css kullanılıyor.
                'css'         => STATIC_PATH . 'css/main-combined.css',
                'selector'    => 'footer',
                'filename'    => 'common-footer-bs.css',
                'scope'       => 'footer#footer',
                'ignore_css'  => [],
                'ignore_tags' => true,
                'white_list'  => [],
                'black_list'  => [],
            ],
        ];

        // Offcanvas HTML'ini topla — hidden oldugu icin DOM parser atlayabiliyor.
        // Offcanvas class'larini whitelist ile koru — "DOM'a girmeyen ama sayfada var olan" CSS icin
        // whitelist tam olarak bu amac icin kullanilir.
        $offcanvas_whitelist = [
            '.offcanvas', '.offcanvas-menu', '.offcanvas-body', '.offcanvas-header', '.offcanvas-footer',
            '.offcanvas-top', '.offcanvas-bottom', '.offcanvas-start', '.offcanvas-end',
            '.offcanvas-fullscreen', '.offcanvas-backdrop',
            '.pt-header',
            '.slinky-menu', '.slinky-*',
        ];
        $offcanvas_combined = '';

        $files = [];
        foreach ( $parts as $type => $info ) {
            $element = $dom->find( $info['selector'], 0 );
            if ( ! $element ) continue;

            // Offcanvas'i da HTML'e ekle — header/footer disinda body'de render edilir
            // ama CSS'i header/footer ile birlikte common-all.css'e girmeli.
            $element_html = '<html><body>' . $element->outerHtml() . $offcanvas_combined . '</body></html>';
            $remover      = new \SaltHareket\AssetManager\RemoveUnusedCss(
                $element_html,
                $info['css'],
                '',
                array_merge( $info['white_list'], $offcanvas_whitelist ),
                false,
                [
                    'ignore_whitelist'      => false,
                    'black_list'            => $info['black_list'],
                    'ignore_root_variables' => true,
                    'scope'                 => $info['scope'],
                    'ignore_css'            => $info['ignore_css'],
                    'ignore_tags'           => $info['ignore_tags'],
                ]
            );

            $purged_css  = $remover->process();
            $target_path = STATIC_PATH . 'css/' . $info['filename'];
            file_put_contents( $target_path, $purged_css );
            $files[] = $target_path;
        }

        $files[] = STATIC_PATH . 'css/common.css';

        $merger = new \SaltHareket\AssetManager\MergeCSS(
            $files,
            STATIC_PATH . 'css/common-all.css',
            true,
            true
        );
        $merger->run();

        // ── Static template plugin tespiti ───────────────────────────────────
        // Compile fetch HTML'inden <header> ve <footer> HTML'ini alip plugin listesini
        // tespit et. Bu liste static template kullanılan sayfalarda JS yuklemek icin
        // SITE_ASSETS'a aktarilir (sh_static_template_plugins option'u).
        $this->_detect_and_save_static_template_plugins( $html_output );
    }

    /**
     * Compile fetch HTML'inden static header/footer icin gerekli plugin'leri tespit eder.
     * Sonucu sh_static_template_plugins wp_options key'ine yazar.
     *
     * @param string $html_output compile_common_css() tarafindan alinan tam sayfa HTML'i
     */
    private function _detect_and_save_static_template_plugins( string $html_output ): void {
        if ( ! function_exists( 'compile_files_config' ) ) {
            require_once SH_INCLUDES_PATH . 'minify-rules.php';
        }

        $files_config = compile_files_config( true );
        if ( empty( $files_config['js']['plugins'] ) ) return;

        // Tum sayfa HTML'ini kullan — DOM parse etmek yerine raw string uzerinde arama.
        // Offcanvas, header, footer hepsi dahil. DOM parse'ta hidden elementler veya
        // style=display:none olan elementler kaybolabilir.
        $combined_html = $html_output;

        if ( empty( $combined_html ) ) return;

        $detected_plugins = [];
        foreach ( $files_config['js']['plugins'] as $key => $plugin ) {
            if ( empty( $plugin['c'] ) ) continue; // conditional degilse zaten yukleniyor

            $condition = $plugin['condition'] ?? 1;
            if ( empty( $condition ) ) continue;

            $found = false;

            // Class eslesmesi
            if ( ! empty( $plugin['class'] ) ) {
                foreach ( $plugin['class'] as $class ) {
                    $pattern = '/class\s*=\s*["\'][^"\']*\b' . preg_quote( $class, '/' ) . '\b[^"\']*["\']/i';
                    if ( preg_match( $pattern, $combined_html ) ) {
                        $found = true;
                        break;
                    }
                }
            }

            // Attr eslesmesi
            if ( ! $found && ! empty( $plugin['attrs'] ) ) {
                foreach ( $plugin['attrs'] as $attr ) {
                    if ( strpos( $attr, '=' ) !== false ) {
                        if ( strpos( $combined_html, $attr ) !== false ) {
                            $found = true;
                            break;
                        }
                    } else {
                        $pattern = '/\s' . preg_quote( $attr, '/' ) . '\s*=\s*["\'].*?["\']/i';
                        if ( preg_match( $pattern, $combined_html ) ) {
                            $found = true;
                            break;
                        }
                    }
                }
            }

            if ( $found ) {
                $detected_plugins[] = $key;
            }
        }

        // data-required-js attribute
        if ( preg_match_all( '/\bdata-required-js\s*=\s*(["\'])(.*?)\1/si', $combined_html, $m ) ) {
            foreach ( $m[2] as $attr_val ) {
                $names = preg_split( '/\s*,\s*/', $attr_val, -1, PREG_SPLIT_NO_EMPTY );
                foreach ( $names as $name ) {
                    $name = trim( $name );
                    if ( $name !== '' && isset( $files_config['js']['plugins'][ $name ] ) ) {
                        $detected_plugins[] = $name;
                    }
                }
            }
        }

        $detected_plugins = array_values( array_unique( $detected_plugins ) );

        update_option( 'sh_static_template_plugins', $detected_plugins, false );
    }

    // =========================================================
    //  REFETCH AFFECTED PAGES
    // =========================================================

    /**
     * Belirtilen plugin'leri kullanan sayfaları DB'den bulur ve PAE ile yeniden fetch eder.
     *
     * @param  string[] $updated_plugins
     * @return array{affected_count: int, fetched_count: int, fetched: array}
     */
    public function refetch_affected_pages( array $updated_plugins ): array {
        if ( empty( $updated_plugins ) ) return [ 'affected_count' => 0, 'fetched_count' => 0, 'fetched' => [] ];

        global $wpdb;

        $pages           = [];
        $like_statements = [];
        foreach ( $updated_plugins as $term ) {
            $like_statements[] = $wpdb->prepare( 'meta_value LIKE %s', '%' . $wpdb->esc_like( $term ) . '%' );
        }
        $like_conditions = implode( ' OR ', $like_statements );

        $query   = "
            (SELECT post_id as id, 'post' as type FROM $wpdb->postmeta WHERE meta_key = 'assets' AND ($like_conditions))
            UNION
            (SELECT term_id as id, 'term' as type FROM $wpdb->termmeta WHERE meta_key = 'assets' AND ($like_conditions))
            UNION
            (SELECT comment_id as id, 'comment' as type FROM $wpdb->commentmeta WHERE meta_key = 'assets' AND ($like_conditions))
            UNION
            (SELECT user_id as id, 'user' as type FROM $wpdb->usermeta WHERE meta_key = 'assets' AND ($like_conditions))
        ";
        $results = $wpdb->get_results( $query );

        foreach ( $results as $row ) {
            $pages[] = [ 'id' => intval( $row->id ), 'type' => $row->type ];
        }

        // Archive options
        if ( defined( 'ENABLE_MULTILANGUAGE' ) && ENABLE_MULTILANGUAGE === 'polylang' && function_exists( 'pll_the_languages' ) ) {
            $like_clauses = [];
            foreach ( $updated_plugins as $term ) {
                $like_clauses[] = $wpdb->prepare( 'option_value LIKE %s', '%' . $wpdb->esc_like( $term ) . '%' );
            }
            $languages  = pll_the_languages( [ 'raw' => 1 ] );
            $post_types = get_post_types( [ 'public' => true ], 'objects' );
            foreach ( $languages as $lang ) {
                foreach ( $post_types as $post_type ) {
                    if ( ! $post_type->has_archive ) continue;
                    $option_name = "{$post_type->name}_archive_{$lang['slug']}_assets";
                    $val         = $wpdb->get_var(
                        $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s AND (" . implode( ' OR ', $like_clauses ) . ')', $option_name )
                    );
                    if ( $val ) {
                        $pages[] = [ 'id' => $lang['slug'], 'type' => $post_type->name ];
                    }
                }
            }
        }

        $pages         = array_unique( $pages, SORT_REGULAR );
        $affected_count = count( $pages );

        // URL map oluştur
        $urls = [];
        foreach ( $pages as $page ) {
            if ( is_string( $page['id'] ) ) {
                $url = function_exists( 'pll_get_post_type_archive_link' )
                    ? pll_get_post_type_archive_link( $page['type'], $page['id'] )
                    : get_post_type_archive_link( $page['type'] );
                $urls[ $page['type'] . '_' . $page['id'] ] = [ 'type' => 'archive', 'url' => $url ];
            } else {
                switch ( $page['type'] ) {
                    case 'term':    $url = get_term_link( $page['id'] ); break;
                    case 'user':    $url = get_author_posts_url( $page['id'] ); break;
                    case 'comment': $url = ''; break;
                    default:        $url = get_permalink( $page['id'] ); break;
                }
                $urls[ $page['id'] ] = [ 'type' => $page['type'], 'url' => $url ];
            }
        }

        if ( empty( $urls ) ) {
            return [ 'affected_count' => $affected_count, 'fetched_count' => 0, 'fetched' => [] ];
        }

        $extractor    = \PageAssetsExtractor::get_instance();
        $fetched      = $extractor->fetch_urls( $urls );
        $fetched_count = is_array( $fetched ) ? count( $fetched ) : 0;

        return [
            'affected_count' => $affected_count,
            'fetched_count'  => $fetched_count,
            'fetched'        => $fetched,
        ];
    }

    // =========================================================
    //  PRIVATE: WEBPACK
    // =========================================================

    private function _run_webpack( bool $with_notices, array &$result ): void {
        if ( ! class_exists( 'Symfony\Component\Process\Process' ) ) return;

        $working_dir  = get_stylesheet_directory();
        $current_user = getenv( 'USERNAME' ) ?: getenv( 'USER' );
        $node_path    = 'C:\Program Files\nodejs';
        $npm_path     = 'C:\Users\\' . $current_user . '\AppData\Roaming\npm';

        // Strateji: önce npx dene (normal yol), timeout/fail ederse node + webpack.cmd fallback.
        // npx Windows'ta .ps1 script olduğundan PHP'nin Symfony Process'i bazen çalıştıramaz.
        $env = [ 'PATH' => getenv( 'PATH' ) . ';' . $node_path . ';' . $npm_path ];

        // Fallback: webpack.cmd — önce tema root'unda, yoksa salthub root'unda ara
        $salthub_root  = dirname( dirname( dirname( dirname( $working_dir ) ) ) );
        $webpack_local = $working_dir . '/node_modules/.bin/webpack.cmd';
        $webpack_root  = $salthub_root . '/node_modules/.bin/webpack.cmd';
        $webpack_cmd   = file_exists( $webpack_local ) ? $webpack_local
                       : ( file_exists( $webpack_root ) ? $webpack_root : null );

        // Önce npx ile dene
        $process = new Process( [ 'npx', 'webpack', '--env', 'enable_ecommerce=false' ], $working_dir );
        $process->setEnv( $env );
        $process->setTimeout( 60 ); // 60sn — bitmezse fallback'e geç

        $npx_ok = false;
        try {
            $process->mustRun();
            $npx_ok = true;
        } catch ( \Exception $e ) {
            // npx başarısız — fallback dene
        }

        // Fallback: node + webpack.cmd
        if ( ! $npx_ok && $webpack_cmd ) {
            $process = new Process( [ 'node', $webpack_cmd, '--env', 'enable_ecommerce=false' ], $working_dir );
            $process->setEnv( $env );
            $process->setTimeout( 120 );

            try {
                $process->mustRun();
            } catch ( ProcessFailedException $e ) {
                $msg = 'Webpack failed: ' . $e->getMessage();
                $result['messages'][] = [ 'type' => 'error', 'text' => $msg ];
                if ( $with_notices && function_exists( 'add_admin_notice' ) ) {
                    add_admin_notice( $msg, 'error' );
                }
            }
        } elseif ( ! $npx_ok ) {
            $result['messages'][] = [ 'type' => 'error', 'text' => 'Webpack failed: npx unavailable and no webpack.cmd found.' ];
        }

        // Stray .js ve .txt dosyalarını temizle
        foreach ( array_merge(
            glob( $working_dir . '/static/css/*.js'  ) ?: [],
            glob( $working_dir . '/static/css/*.txt' ) ?: []
        ) as $file ) {
            @unlink( $file );
        }
    }
}
