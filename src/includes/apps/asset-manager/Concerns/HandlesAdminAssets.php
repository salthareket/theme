<?php

namespace SaltHareket\AssetManager\Concerns;

use SaltHareket\AssetManager\AssetSettings;

/**
 * HandlesAdminAssets
 *
 * Admin CSS/JS yükleme — filter ile genişletilebilir.
 *
 * @version 1.0.0
 */
trait HandlesAdminAssets
{
    public function loadAdminAssets(): void
    {
        // Sabit admin CSS'ler
        $admin_styles = [
            'fontawesome'    => [ 'url' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css', 'ver' => '6.7.2' ],
            'bootstrap-admin'=> [ 'url' => STATIC_URL . 'css/bootstrap-admin.css' ],
            'root'           => [ 'url' => STATIC_URL . 'css/root.css' ],
            'acf-layouts'    => [ 'url' => STATIC_URL . 'css/header-admin.css' ],
            'blocks-admin'   => [ 'url' => STATIC_URL . 'css/blocks-admin.css' ],
            'admin-addon'    => [ 'url' => STATIC_URL . 'css/admin-addon.css' ],
        ];

        // main-admin.css sadece block editor kullanan sayfalarda yükle
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        $is_block_editor_page = $screen && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor();
        if ( $is_block_editor_page ) {
            $admin_styles['main-admin'] = [ 'url' => STATIC_URL . 'css/main-admin.css' ];
        }

        // Filter ile dışarıdan ekle/çıkar
        $admin_styles = apply_filters( 'sh_admin_styles', $admin_styles );

        foreach ( $admin_styles as $handle => $args ) {
            wp_enqueue_style(
                $handle,
                $args['url'],
                $args['deps'] ?? [],
                $args['ver'] ?? false,
                $args['media'] ?? 'all'
            );
        }

        // Sabit admin JS'ler
        // functions.min.js kaldırıldı — içeriği AjaxInitManager (frontend plugin lazy-loader).
        // Admin'de .swiper/.plyr/.btn-favorite gibi frontend widget'ları yok,
        // js_files_conditional_set.json'dan plugin config okuyup init yapmaya gerek yok.
        $admin_scripts = [
            'admin'          => [ 'url' => STATIC_URL . 'js/admin.min.js',         'deps' => [ 'jquery' ], 'footer' => true ],
            'plugins-admin'  => [ 'url' => STATIC_URL . 'js/plugins-admin.min.js', 'deps' => [ 'jquery' ], 'footer' => true ],
        ];

        // Filter ile dışarıdan ekle/çıkar
        $admin_scripts = apply_filters( 'sh_admin_scripts', $admin_scripts );

        foreach ( $admin_scripts as $handle => $args ) {
            wp_enqueue_script(
                $handle,
                $args['url'],
                $args['deps'] ?? [ 'jquery' ],
                $args['ver'] ?? '1.0.0',
                $args['footer'] ?? true
            );
        }

        // Inline head JS (filter-based)
        $inline_head_js = apply_filters( 'sh_inline_admin_head_js', '' );
        if ( $inline_head_js ) {
            wp_add_inline_script( 'admin', $inline_head_js, 'before' );
        }
    }

    /**
     * jQuery migrate'i kaldır.
     */
    public function removeJqueryMigrate( \WP_Scripts $scripts ): void
    {
        if ( ! is_admin() && isset( $scripts->registered['jquery'] ) ) {
            $script = $scripts->registered['jquery'];
            if ( $script->deps ) {
                $script->deps = array_diff( $script->deps, [ 'jquery-migrate' ] );
            }
        }
    }
}
