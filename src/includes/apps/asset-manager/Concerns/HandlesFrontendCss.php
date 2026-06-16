<?php

namespace SaltHareket\AssetManager\Concerns;

use SaltHareket\AssetManager\AssetSettings;

/**
 * HandlesFrontendCss
 *
 * Frontend CSS yükleme, inline engine, lazy loading, preload.
 *
 * @version 1.0.0
 */
trait HandlesFrontendCss
{
    // ─── Main CSS Load ────────────────────────────────────────────────────────

    public function loadFrontendCss(): void
    {
        if ( is_admin() ) return;

        $settings    = AssetSettings::get();
        // SEPERATE_CSS, INLINE_CSS, SEPERATE_JS, INLINE_JS variables.php'de define edilmiş
        $inline_css  = INLINE_CSS && ! isset( $_GET['fetch'] );
        $is_rtl      = $this->is_rtl ?? false;

        // Sayfa tipine göre core block kontrolü
        $has_core_block = $this->detectCoreBlock();

        // WP global styles temizle
        $this->cleanupWpStyles( $has_core_block, $settings );

        // Font faces inline
        $this->inlineCssAdd( 'sh-font-faces', STATIC_PATH . 'css/font-faces.css' );

        // Locale CSS
        $this->smartEnqueueLocale( 'css', \Data::get( 'language' ) ?? '' );

        // Root CSS
        wp_enqueue_style( 'sh-root', STATIC_URL . 'css/root.css', [], $this->version );

        // Conditional assets (page-specific)
        $this->handleConditionalCss( $settings, $is_rtl, $inline_css );

        // Common CSS
        wp_enqueue_style(
            'sh-common',
            STATIC_URL . 'css/common-all' . ( $is_rtl ? '-rtl' : '' ) . '.css',
            [],
            $this->version
        );

        // Dışarıdan eklenen CSS'ler
        $extra_styles = apply_filters( 'sh_frontend_styles', [] );
        foreach ( $extra_styles as $handle => $args ) {
            $condition = $args['condition'] ?? '';
            if ( $condition && function_exists( $condition ) && ! $condition() ) continue;
            wp_enqueue_style(
                $handle,
                $args['url'] ?? '',
                $args['deps'] ?? [],
                $args['version'] ?? $this->version,
                $args['media'] ?? 'all'
            );
        }
    }

    // ─── Conditional Assets ───────────────────────────────────────────────────

    private function handleConditionalCss( array $settings, bool $is_rtl, bool $inline_css = false ): void
    {
        $assets      = defined( 'SITE_ASSETS' ) ? SITE_ASSETS : [];
        $rtl_suffix  = $is_rtl ? '_rtl' : '';
        $plugin_key  = 'plugin_css' . $rtl_suffix;
        $page_key    = 'css_page' . $rtl_suffix;

        $plugin_css = isset( $assets[ $plugin_key ] ) && ! empty( $assets[ $plugin_key ] )
            && file_exists( STATIC_PATH . $assets[ $plugin_key ] );
        $css_page   = isset( $assets[ $page_key ] ) && ! empty( $assets[ $page_key ] )
            && file_exists( STATIC_PATH . $assets[ $page_key ] );

        if ( $inline_css ) {
            if ( $plugin_css ) {
                $this->inlineCssAdd( 'sh-conditional', STATIC_PATH . $assets[ $plugin_key ], $is_rtl );
            }
            $main_path = $css_page
                ? STATIC_PATH . $assets[ $page_key ]
                : STATIC_PATH . 'css/main-combined' . ( $is_rtl ? '-rtl' : '' ) . '.css';
            $this->inlineCssAdd( 'sh-main', $main_path, $is_rtl );
        } else {
            if ( $plugin_css ) {
                wp_enqueue_style( 'sh-conditional', STATIC_URL . $assets[ $plugin_key ], [], $this->version );
            }
            $main_url = $css_page
                ? STATIC_URL . $assets[ $page_key ]
                : STATIC_URL . 'css/main-combined' . ( $is_rtl ? '-rtl' : '' ) . '.css';
            wp_enqueue_style( 'sh-main', $main_url, [], $this->version );
        }
    }

    // ─── Cleanup ──────────────────────────────────────────────────────────────

    public function cleanupWpStyles( bool $has_core_block, array $settings ): void
    {
        // Global styles
        $remove_global = $settings['remove_global_styles'];
        if ( ( $remove_global === 'auto' || $remove_global ) && ! $has_core_block ) {
            wp_deregister_style( 'global-styles' );
            wp_deregister_style( 'global-styles-inline' );
            wp_dequeue_style( 'global-styles' );
            wp_dequeue_style( 'global-styles-inline' );
            remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
            remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
            remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );
        }

        // Block styles
        $remove_block = $settings['remove_block_styles'];
        if ( ( $remove_block === 'auto' || $remove_block ) && ! $has_core_block ) {
            wp_dequeue_style( 'wp-block-library' );
            wp_dequeue_style( 'wc-blocks-style' );
        }

        // Classic theme styles
        if ( $settings['remove_classic_theme_styles'] ) {
            wp_deregister_style( 'classic-theme-styles-inline' );
            wp_deregister_style( 'classic-theme-styles' );
            wp_dequeue_style( 'classic-theme-styles-inline' );
            wp_dequeue_style( 'classic-theme-styles' );
        }

        // Sabit dequeue'lar
        foreach ( [ 'toggle-switch', 'font-awesome', 'font-for-body', 'font-for-new', 'google-fonts-roboto' ] as $h ) {
            wp_dequeue_style( $h );
        }

        // WooCommerce styles
        if ( defined( 'ENABLE_ECOMMERCE' ) && ENABLE_ECOMMERCE ) {
            if ( $settings['remove_woocommerce_styles'] ) {
                foreach ( [ 'woocommerce-smallscreen', 'woocommerce-inline', 'woocommerce-layout', 'woocommerce-general' ] as $h ) {
                    wp_dequeue_style( $h );
                }
            }
            foreach ( [ 'ywdpd_owl', 'yith_ywdpd_frontend' ] as $h ) {
                wp_dequeue_style( $h );
            }
            if ( get_option( 'woocommerce_coming_soon' ) !== 'yes' ) {
                wp_dequeue_style( 'woocommerce-coming-soon' );
                wp_deregister_style( 'woocommerce-coming-soon' );
            }
            $this->maybeDequeueBrandStyles();
        }

        // Google Fonts
        if ( $settings['block_google_fonts'] ) {
            add_action( 'wp_enqueue_scripts', [ $this, 'dequeueGoogleFonts' ], 9999 );
            add_filter( 'style_loader_tag', [ $this, 'blockGoogleFontsTag' ], 9999, 3 );
        }

        // Dışarıdan eklenen dequeue listesi
        $extra_dequeue = apply_filters( 'sh_dequeue_styles', $settings['dequeue_styles'] ?? [] );
        foreach ( $extra_dequeue as $handle ) {
            wp_dequeue_style( $handle );
            wp_deregister_style( $handle );
        }
    }

    private function maybeDequeueBrandStyles(): void
    {
        if ( ! taxonomy_exists( 'product_brand' ) ) return;

        $has_brand = get_transient( '_salt_has_product_brand' );
        if ( $has_brand === false ) {
            global $wpdb;
            $has_brand = $wpdb->get_var(
                "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'product_brand' LIMIT 1"
            ) ? '1' : '0';
            set_transient( '_salt_has_product_brand', $has_brand, DAY_IN_SECONDS );
        }

        if ( $has_brand === '0' ) {
            wp_dequeue_style( 'brands-styles' );
            wp_deregister_style( 'brands-styles' );
        }
    }

    // ─── Lazy Loading ─────────────────────────────────────────────────────────

    /**
     * CSS lazy load — preload + onload trick.
     * Admin'de ve production modunda devre dışı.
     */
    public function delayCssLoading( string $tag, string $handle, string $href ): string
    {
        $settings = AssetSettings::get();

        if ( ! $settings['css_lazy_load'] ) return $tag;

        $has_critical = defined( 'SITE_ASSETS' ) && is_array( SITE_ASSETS ) && ! empty( SITE_ASSETS['css_critical'] );

        // Sabit lazy handles
        $lazy_handles = [
            'locale', 'newsletter',
            'yith-wcan-frontend', 'yith-wcan-shortcodes',
            'woocommerce-layout', 'woocommerce-smallscreen', 'woocommerce-general', 'wc-blocks-style',
        ];

        // Critical CSS varsa tema CSS'leri de lazy
        if ( $has_critical ) {
            $lazy_handles = array_merge( $lazy_handles, [ 'sh-root', 'sh-common', 'sh-conditional', 'sh-main' ] );
        }

        // Ayarlardan + filter'dan gelen ekstra handle'lar
        $extra = apply_filters( 'sh_lazy_css_handles', $settings['lazy_css_handles'] ?? [] );
        $lazy_handles = array_unique( array_merge( $lazy_handles, $extra ) );

        // URL pattern'leri
        $lazy_patterns = [
            '/woocommerce/', '/wc-blocks/', '/wc-', 'woocommerce.css',
            'checkout-blocks.css', 'ion.range-slider', 'shortcodes.css',
            'style.min.css', 'youtube.com', 'ytimg.com', 'www-player.css',
        ];
        $lazy_patterns = apply_filters( 'sh_lazy_css_patterns', $lazy_patterns );

        $should_lazy = in_array( $handle, $lazy_handles, true );
        if ( ! $should_lazy ) {
            foreach ( $lazy_patterns as $pattern ) {
                if ( str_contains( $href, $pattern ) ) { $should_lazy = true; break; }
            }
        }

        if ( ! $should_lazy ) return $tag;

        $safe = esc_url( $href );
        return "<link id='{$handle}' rel='preload' href='{$safe}' as='style' onload=\"this.onload=null;this.rel='stylesheet'\"><noscript><link rel='stylesheet' href='{$safe}'></noscript>\n";
    }

    // ─── Google Fonts ─────────────────────────────────────────────────────────

    public function dequeueGoogleFonts(): void
    {
        global $wp_styles;
        if ( empty( $wp_styles->registered ) ) return;
        foreach ( $wp_styles->registered as $handle => $style ) {
            if ( ! empty( $style->src ) && str_contains( $style->src, 'fonts.googleapis.com' ) ) {
                wp_dequeue_style( $handle );
                wp_deregister_style( $handle );
            }
        }
    }

    public function blockGoogleFontsTag( string $tag, string $handle, string $href ): string
    {
        return str_contains( $href, 'fonts.googleapis.com' ) ? '' : $tag;
    }

    // ─── Inline CSS Engine ────────────────────────────────────────────────────

    public function inlineCss( string $name, string $url ): string
    {
        $key        = md5( $name . $url . $this->version . get_option( 'salthareket_theme_version', '1' ) );
        $cache_dir  = rtrim( STATIC_PATH, '/' ) . '/css/cache/';
        $cache_file = $cache_dir . $key . '-inline.css';

        if ( isset( self::$runtimeCache[ $key ] ) ) return self::$runtimeCache[ $key ];

        // Path validation
        $allowed = [ STATIC_PATH, THEME_STATIC_PATH, SH_STATIC_PATH, get_template_directory() . '/' ];
        $is_allowed = false;
        foreach ( $allowed as $a ) {
            if ( str_starts_with( realpath( $url ) ?: $url, realpath( $a ) ?: $a ) ) {
                $is_allowed = true;
                break;
            }
        }
        if ( ! $is_allowed ) {
            error_log( '[AssetManager] Blocked unauthorized file: ' . $url );
            return '';
        }

        // Cache hit
        if ( file_exists( $cache_file ) && file_exists( $url ) && filemtime( $cache_file ) >= filemtime( $url ) ) {
            return self::$runtimeCache[ $key ] = file_get_contents( $cache_file );
        }

        if ( ! file_exists( $url ) ) return '';

        $css = file_get_contents( $url );
        $css = $this->fixCssPaths( $css, $url );
        $css = preg_replace( '/\s+/', ' ', $css );

        if ( ! file_exists( $cache_dir ) ) wp_mkdir_p( $cache_dir );

        // Eski cache temizle (%2 ihtimalle)
        if ( mt_rand( 1, 50 ) === 1 ) {
            foreach ( glob( $cache_dir . '*-inline.css' ) ?: [] as $old ) {
                if ( filemtime( $old ) < ( time() - 7 * DAY_IN_SECONDS ) ) @unlink( $old );
            }
        }

        file_put_contents( $cache_file, $css );
        return self::$runtimeCache[ $key ] = $css;
    }

    public function inlineCssAdd( string $name, string $url, bool $rtl = false ): void
    {
        $handle  = $name . ( $rtl ? '-rtl' : '' );
        $content = $this->inlineCss( $handle, $url );
        if ( $content ) {
            wp_register_style( $handle, false );
            wp_enqueue_style( $handle );
            wp_add_inline_style( $handle, $content );
        }
    }

    // ─── Inline Head CSS (filter-based) ──────────────────────────────────────

    public function renderInlineHeadCss(): void
    {
        $css = apply_filters( 'sh_inline_head_css', '' );
        if ( $css ) {
            echo '<style id="sh-inline-head-css">' . $css . '</style>' . "\n";
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function detectCoreBlock(): bool
    {
        global $post;
        if ( ! $post ) return false;
        return (bool) get_post_meta( $post->ID, 'has_core_block', true );
    }
}
