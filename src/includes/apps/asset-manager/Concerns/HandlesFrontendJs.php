<?php

namespace SaltHareket\AssetManager\Concerns;

use SaltHareket\AssetManager\AssetSettings;

/**
 * HandlesFrontendJs
 *
 * Frontend JS yükleme — production/dev mod, mass_enqueue, guard, defer.
 *
 * @version 1.0.0
 */
trait HandlesFrontendJs
{
    public function loadFrontendJs(): void
    {
        // jQuery override
        wp_deregister_script( 'jquery' );
        wp_enqueue_script( 'jquery', STATIC_URL . 'js/jquery.min.js', [], '1.0.0', true );
        wp_enqueue_script( 'image-sizes', SH_STATIC_URL . 'js/image-sizes.js', [], '1.0.0', true );

        add_action( 'wp_footer', [ $this, 'loadFooterJs' ] );
    }

    public function loadFooterJs(): void
    {
        if ( is_admin() ) return;

        // ENABLE_PRODUCTION variables.php'de define edilmiş
        $is_production = ENABLE_PRODUCTION;
        $settings      = AssetSettings::get();

        // Gereksiz script'leri dequeue et
        $dequeues = [ 'wc_additional_variation_images_script', 'ywdpd_owl', 'ywdpd_popup', 'ywdpd_frontend', 'acf-osm-frontend' ];
        foreach ( $dequeues as $h ) {
            wp_dequeue_script( $h );
            wp_deregister_script( $h );
        }

        if ( defined( 'ENABLE_ECOMMERCE' ) && ENABLE_ECOMMERCE && defined( 'ENABLE_CART' ) && ! ENABLE_CART ) {
            wp_deregister_script( 'wc-order-attribution' );
        }

        // Dışarıdan eklenen dequeue listesi
        $extra_dequeue = apply_filters( 'sh_dequeue_scripts', $settings['dequeue_scripts'] ?? [] );
        foreach ( $extra_dequeue as $handle ) {
            wp_dequeue_script( $handle );
            wp_deregister_script( $handle );
        }

        // Google Maps
        $map_key = sanitize_text_field( (string) \Data::get( 'google_maps_api_key' ) );
        if ( $map_key ) {
            $map_lang = sanitize_key( (string) \Data::get( 'language' ) );
            wp_enqueue_script( 'googlemaps', "https://maps.googleapis.com/maps/api/js?key={$map_key}&language={$map_lang}", [], null, true );
            if ( $map_style = \QueryCache::get_option( 'options_google_maps_style' ) ) {
                $style = json_encode( json_decode( strip_tags( $map_style ) ) );
                wp_add_inline_script( 'googlemaps', "var map_style = {$style};", 'after' );
            }
        }

        if ( ! function_exists( 'compile_files_config' ) ) {
            require_once SH_INCLUDES_PATH . 'minify-rules.php';
        }

        $files               = compile_files_config( true );
        $init_functions      = [];
        $assets              = defined( 'SITE_ASSETS' ) ? SITE_ASSETS : [];
        // plugins key string veya array olabilir — her zaman array'e çevir
        $plugins_conditional = (array) ( $assets['plugins'] ?? [] );

        if ( $is_production ) {
            $this->loadProductionJs( $files, $plugins_conditional, $init_functions );
        } else {
            $this->loadDevJs( $files, $plugins_conditional, $init_functions, $assets );
        }

        // Locale JS
        $this->smartEnqueueLocale( 'js', \Data::get( 'language' ) ?? '' );

        // Dışarıdan eklenen JS'ler
        $extra_scripts = apply_filters( 'sh_frontend_scripts', [] );
        foreach ( $extra_scripts as $handle => $args ) {
            $condition = $args['condition'] ?? '';
            if ( $condition && function_exists( $condition ) && ! $condition() ) continue;
            wp_enqueue_script(
                $handle,
                $args['url'] ?? '',
                $args['deps'] ?? [ 'jquery' ],
                $args['version'] ?? null,
                $args['footer'] ?? true
            );
        }

        // Inline footer JS (filter-based)
        $inline_js = apply_filters( 'sh_inline_footer_js', '' );
        if ( $inline_js ) {
            wp_add_inline_script( 'jquery', $inline_js, 'after' );
        }
    }

    private function loadProductionJs( array $files, array $plugins_conditional, array &$init_functions ): void
    {
        $function_handles = $this->massEnqueue( $files['js']['functions'] ?? [], 'footer-' );

        foreach ( $files['js']['plugins'] ?? [] as $plugin => $file ) {
            if ( ! $file['c'] || ( is_array( $plugins_conditional ) && in_array( $plugin, $plugins_conditional, true ) ) ) {
                $p_handle = 'plugin-' . $plugin;
                wp_enqueue_script( $p_handle, STATIC_URL . "js/plugins/{$plugin}.js", array_merge( [ 'jquery' ], $function_handles ), null, true );
                if ( ! empty( $file['init'] ) ) {
                    wp_enqueue_script( "{$p_handle}-init", STATIC_URL . "js/plugins/{$plugin}-init.js", [ $p_handle ], null, true );
                    $init_functions[ $plugin ] = $file['init'];
                }
            }
        }

        $pre_handles = $this->massEnqueue( $files['js']['pre'] ?? [], 'pre-' );

        foreach ( $files['js']['main'] ?? [] as $key => $file ) {
            $handle = 'main-' . $key;
            wp_enqueue_script( $handle, $file, array_merge( [ 'jquery' ], $pre_handles, $function_handles ), null, true );
            if ( $key === 0 && ! empty( $init_functions ) ) {
                $this->injectPluginInits( $handle, $init_functions );
            }
        }
    }

    private function loadDevJs( array $files, array $plugins_conditional, array &$init_functions, array $assets ): void
    {
        wp_enqueue_script( 'pre', STATIC_URL . 'js/pre-combined.min.js', [ 'jquery' ], null, true );
        wp_add_inline_script( 'pre', '(function(){if(window.__preLoaded)return;window.__preLoaded=true;})();', 'before' );

        if ( ! empty( $assets['plugin_js'] ) && ! isset( $_GET['fetch'] ) ) {
            wp_enqueue_script( 'plugins-conditional', STATIC_URL . $assets['plugin_js'], [ 'jquery' ], null, true );
        }

        wp_enqueue_script( 'main', STATIC_URL . 'js/main-combined.min.js', [ 'jquery', 'pre' ], null, true );
        wp_add_inline_script( 'main', '(function(){if(window.__mainLoaded)return;window.__mainLoaded=true;})();', 'before' );

        foreach ( $files['js']['plugins'] ?? [] as $plugin => $file ) {
            if ( ( ! $file['c'] || ( is_array( $plugins_conditional ) && in_array( $plugin, $plugins_conditional, true ) ) ) && ! empty( $file['init'] ) ) {
                $init_functions[ $plugin ] = $file['init'];
            }
        }

        if ( ! empty( $init_functions ) ) {
            $this->injectPluginInits( 'main', $init_functions );
        }
    }

    // ─── Script Attributes (defer/module) ────────────────────────────────────

    public function addScriptAttributes( string $tag, string $handle, string $src ): string
    {
        $settings = AssetSettings::get();

        // Module
        if ( in_array( $handle, [ 'text-module' ], true ) ) {
            return '<script type="module" src="' . esc_url( $src ) . '"></script>';
        }

        // Defer handles
        $defer_handles = [ 'image-sizes', 'plugins-conditional' ];
        $extra_defer   = apply_filters( 'sh_defer_js_handles', $settings['defer_js_handles'] ?? [] );
        $defer_handles = array_unique( array_merge( $defer_handles, $extra_defer ) );

        if ( in_array( $handle, $defer_handles, true ) ) {
            return str_replace( ' src=', ' defer src=', $tag );
        }

        // Lazy load patterns
        $lazy_patterns = apply_filters( 'sh_lazy_js_patterns', [ 'html-to-image', 'chart', 'canvas' ] );
        foreach ( $lazy_patterns as $pattern ) {
            if ( str_contains( $handle, $pattern ) ) {
                return str_replace( ' src=', ' data-src=', $tag )
                    . '<script>document.addEventListener("click",function(){var s=document.querySelector(\'script[data-src*="' . esc_js( $pattern ) . '"]\');if(s){s.src=s.dataset.src;s.removeAttribute("data-src");}},{once:true});</script>';
            }
        }

        return $tag;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function massEnqueue( array $file_array, string $prefix ): array
    {
        $handles = [];
        foreach ( $file_array as $key => $file ) {
            $handle    = $prefix . $key;
            $guard_key = 'window.__loaded_' . md5( $handle );
            wp_enqueue_script( $handle, $file, [], null, true );
            wp_add_inline_script( $handle, "(function(){if({$guard_key})return;{$guard_key}=true;})();", 'before' );
            $handles[] = $handle;
        }
        return $handles;
    }

    private function injectPluginInits( string $handle, array $init_functions ): void
    {
        $script = 'function init_plugins(){';
        foreach ( $init_functions as $plugin => $func ) {
            $script .= sprintf( 'function_secure("%s","%s");', esc_js( $plugin ), esc_js( $func ) );
        }
        $script .= '}';
        wp_add_inline_script( $handle, $script );
    }
}
