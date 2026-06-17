<?php

namespace SaltHareket\AssetManager\Admin;

use SaltHareket\AssetManager\AssetManager;
use SaltHareket\AssetManager\AssetSettings;
use SaltHareket\AssetManager\MediaOptimizer;
use SaltHareket\AssetManager\Admin\MediaOptimizerAdmin;

class AssetManagerAdmin
{
    public static function register(): void
    {
        if ( ! is_admin() ) return;
        add_action( 'admin_menu',            [ self::class, 'addMenuPage' ], 27 );
        add_action( 'admin_head',            [ self::class, 'hideNotices' ] );
        add_action( 'admin_enqueue_scripts', [ self::class, 'enqueueAssets' ] );
        add_action( 'wp_ajax_sh_am_save_settings', [ self::class, 'ajaxSaveSettings' ] );
        add_action( 'wp_ajax_sh_am_purge_cache',   [ self::class, 'ajaxPurgeCache' ] );

        // Media Optimizer AJAX
        MediaOptimizerAdmin::registerAjax();

        // Cron
        add_action( MediaOptimizer::CRON_HOOK, [ MediaOptimizer::class, 'runCron' ] );
    }

    public static function addMenuPage(): void
    {
        add_submenu_page( 'theme-settings', '⚡ Assets', '⚡ Assets', 'manage_options', 'sh-asset-manager', [ self::class, 'renderPage' ] );
    }

    public static function hideNotices(): void
    {
        if ( ( $_GET['page'] ?? '' ) !== 'sh-asset-manager' ) return;
        echo '<style>body .notice:not(.sh-inline),body .updated:not(.sh-inline),body .error:not(.sh-inline){display:none!important;}</style>';
    }

    public static function enqueueAssets( string $hook ): void
    {
        if ( strpos( $hook, 'sh-asset-manager' ) === false ) return;
        $css_path = dirname( __DIR__, 2 ) . '/sh-admin.css';
        $css_url  = trailingslashit( SH_INCLUDES_URL ) . 'apps/sh-admin.css';
        if ( file_exists( $css_path ) ) {
            wp_enqueue_style( 'sh-admin-kit', $css_url, [], filemtime( $css_path ) );
        }
    }

    public static function ajaxSaveSettings(): void
    {
        check_ajax_referer( 'sh_am_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized.' );
        AssetSettings::save( $_POST['settings'] ?? [] );
        wp_send_json_success( 'Settings saved.' );
    }

    public static function ajaxPurgeCache(): void
    {
        check_ajax_referer( 'sh_am_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized.' );

        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_font_inline_cache_%' OR option_name LIKE '_transient_timeout_font_inline_cache_%'" );

        $cache_folder = rtrim( STATIC_PATH, '/' ) . '/css/cache';
        if ( file_exists( $cache_folder ) ) {
            array_map( 'unlink', glob( "{$cache_folder}/*.css" ) ?: [] );
        }

        AssetManager::$runtimeCache = [];
        AssetManager::$preloadQueue = [];

        wp_send_json_success( 'Cache cleared.' );
    }

    public static function renderPage(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $tab      = sanitize_key( $_GET['tab'] ?? 'overview' );
        $settings = AssetSettings::get();
        $nonce    = wp_create_nonce( 'sh_am_nonce' );
        $ajax_url = admin_url( 'admin-ajax.php' );
        $is_prod  = ENABLE_PRODUCTION;
        ?>
        <div class="sh-wrap" id="sh-asset-manager-page">

        <div class="sh-toolbar">
            <h1>⚡ Asset Manager</h1>
            <?php if ( $is_prod ) : ?>
                <span class="sh-badge sh-badge-blue">Production</span>
            <?php else : ?>
                <span class="sh-badge sh-badge-gray">Development</span>
            <?php endif; ?>
            <?php if ( defined('ENABLE_ECOMMERCE') && ENABLE_ECOMMERCE ) : ?>
                <span class="sh-badge sh-badge-gray">WooCommerce</span>
            <?php endif; ?>
            <div class="sh-toolbar-right">
                <a href="?page=sh-asset-manager&tab=overview"  class="sh-tab-btn <?php echo $tab==='overview' ?'active':''; ?>">Overview</a>
                <a href="?page=sh-asset-manager&tab=settings"  class="sh-tab-btn <?php echo $tab==='settings' ?'active':''; ?>">Settings</a>
                <a href="?page=sh-asset-manager&tab=filters"   class="sh-tab-btn <?php echo $tab==='filters'  ?'active':''; ?>">Filters</a>
                <a href="?page=sh-asset-manager&tab=cache"     class="sh-tab-btn <?php echo $tab==='cache'    ?'active':''; ?>">Cache</a>
                <a href="?page=sh-asset-manager&tab=debug"     class="sh-tab-btn <?php echo $tab==='debug'    ?'active':''; ?>">Debug</a>
                <a href="?page=sh-asset-manager&tab=media"     class="sh-tab-btn <?php echo $tab==='media'    ?'active':''; ?>">🖼️ Media</a>
            </div>
        </div>

        <?php
        $titles = [
            'overview' => [ 'title' => 'Asset Overview',    'desc' => 'Current asset loading state by mode and environment.' ],
            'settings' => [ 'title' => 'Asset Settings',    'desc' => 'Configure production mode, inline CSS, lazy loading and cleanup.' ],
            'filters'  => [ 'title' => 'Filter Reference',  'desc' => 'All available filters for extending asset loading from outside.' ],
            'cache'    => [ 'title' => 'Cache Management',  'desc' => 'Inline CSS cache and font preload cache.' ],
            'debug'    => [ 'title' => 'Debug — Loaded Assets', 'desc' => 'All currently enqueued CSS and JS with details.' ],
            'media'    => [ 'title' => '🖼️ Media Optimizer', 'desc' => 'Convert unoptimized images to AVIF/WebP. Save bandwidth, boost Core Web Vitals.' ],
        ];
        $cur = $titles[$tab] ?? $titles['overview'];
        ?>
        <div class="sh-section-title">
            <h2><?php echo esc_html($cur['title']); ?></h2>
            <p><?php echo esc_html($cur['desc']); ?></p>
        </div>

        <?php
        switch ( $tab ) {
            case 'settings': self::renderSettingsTab( $settings, $nonce, $ajax_url ); break;
            case 'filters':  self::renderFiltersTab(); break;
            case 'cache':    self::renderCacheTab( $nonce, $ajax_url ); break;
            case 'debug':    self::renderDebugTab(); break;
            case 'media':    MediaOptimizerAdmin::renderTab( $nonce, $ajax_url ); break;
            default:         self::renderOverviewTab( $settings, $is_prod ); break;
        }
        ?>

        <div id="sh-toast"></div>
        </div>

        <script>
        (function($){
            var NONCE = <?php echo wp_json_encode( $nonce ); ?>;
            var AJAX  = <?php echo wp_json_encode( $ajax_url ); ?>;

            $('#sh-am-save').on('click', function() {
                var $btn = $(this).prop('disabled', true).text('Saving...');
                var s = {
                    // ACF'de kalan ayarlar buradan kaydedilmiyor:
                    // enable_production, seperate_css, inline_css, seperate_js, inline_js
                    remove_global_styles:        $('select[name="remove_global_styles"]').val(),
                    remove_block_styles:         $('select[name="remove_block_styles"]').val(),
                    remove_classic_theme_styles: $('#sh-remove-classic').is(':checked') ? 1 : 0,
                    remove_woocommerce_styles:   $('#sh-remove-woo').is(':checked') ? 1 : 0,
                    block_google_fonts:          $('#sh-block-gfonts').is(':checked') ? 1 : 0,
                    css_lazy_load:               $('#sh-css-lazy').is(':checked') ? 1 : 0,
                    js_defer:                    $('#sh-js-defer').is(':checked') ? 1 : 0,
                };
                $.post(AJAX, { action: 'sh_am_save_settings', nonce: NONCE, settings: s }, function(res) {
                    $btn.prop('disabled', false).text('Save Settings');
                    if (res.success) { shAmToast('Settings saved!'); }
                    else shAmToast(res.data || 'Error', true);
                });
            });

            $('#sh-am-purge').on('click', function() {
                var $btn = $(this).prop('disabled', true).text('Clearing...');
                $.post(AJAX, { action: 'sh_am_purge_cache', nonce: NONCE }, function(res) {
                    $btn.prop('disabled', false).text('Clear Cache');
                    if (res.success) shAmToast('Cache cleared!');
                    else shAmToast(res.data || 'Error', true);
                });
            });

            // Copy button — data-code attribute'undan oku
            $(document).on('click', '.sh-am-copy-btn', function() {
                var code = $(this).data('code');
                var $btn = $(this);
                navigator.clipboard.writeText(code).then(function() {
                    $btn.text('✓');
                    setTimeout(function() { $btn.text('Copy'); }, 1500);
                });
            });

            // Inline CSS toggle — sadece Separate CSS aktifse görünür
            $('#sh-seperate-css').on('change', function() {
                $('#sh-inline-css-wrap').toggle($(this).is(':checked'));
            });
            $('#sh-seperate-js').on('change', function() {
                $('#sh-inline-js-wrap').toggle($(this).is(':checked'));
            });

            function shAmToast(msg, isError) {
                var el = document.getElementById('sh-toast');
                if (!el) return;
                var item = document.createElement('div');
                item.className = 'sh-toast-item' + (isError ? ' sh-toast-error' : ' sh-toast-success');
                item.textContent = msg;
                el.appendChild(item);
                setTimeout(function() { item.remove(); }, 3500);
            }
        })(jQuery);
        </script>
        <?php
    }

    // ─── Overview Tab ─────────────────────────────────────────────────────────

    private static function renderOverviewTab( array $settings, bool $is_prod ): void
    {
        $mode_cards = [
            [ 'label' => 'Mode',           'value' => $is_prod ? 'Production' : 'Development', 'color' => $is_prod ? '#22c55e' : '#f0b429' ],
            [ 'label' => 'CSS Loading',    'value' => INLINE_CSS ? 'Inline' : ( $settings['css_lazy_load'] ? 'Lazy' : 'Normal' ), 'color' => '#3b82f6' ],
            [ 'label' => 'JS Loading',     'value' => $settings['js_defer'] ? 'Deferred' : 'Normal', 'color' => '#8b5cf6' ],
            [ 'label' => 'Google Fonts',   'value' => $settings['block_google_fonts'] ? 'Blocked' : 'Allowed', 'color' => $settings['block_google_fonts'] ? '#22c55e' : '#ef4444' ],
            [ 'label' => 'Global Styles',  'value' => $settings['remove_global_styles'] === 'auto' ? 'Auto' : ( $settings['remove_global_styles'] ? 'Removed' : 'Kept' ), 'color' => '#6b7280' ],
            [ 'label' => 'WC Styles',      'value' => $settings['remove_woocommerce_styles'] ? 'Removed' : 'Kept', 'color' => '#6b7280' ],
        ];
        ?>
        <div class="sh-layout">
        <div class="sh-main">

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:24px">
        <?php foreach ( $mode_cards as $c ) : ?>
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:16px;border-top:3px solid <?php echo $c['color']; ?>">
                <div style="font-size:11px;text-transform:uppercase;color:#6b7280;letter-spacing:.5px"><?php echo esc_html($c['label']); ?></div>
                <div style="font-size:18px;font-weight:700;color:<?php echo $c['color']; ?>;margin-top:6px"><?php echo esc_html($c['value']); ?></div>
            </div>
        <?php endforeach; ?>
        </div>

        <!-- What loads in each mode -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-bottom:16px">
            <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6"><strong>CSS Loading — <?php echo $is_prod ? 'Production' : 'Development'; ?> Mode</strong></div>
            <table style="width:100%;border-collapse:collapse">
                <thead><tr>
                    <?php foreach(['Handle','File','Method','Status'] as $h): ?>
                    <th style="padding:10px 18px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #f3f4f6"><?php echo $h; ?></th>
                    <?php endforeach; ?>
                </tr></thead>
                <tbody>
                <?php
                $css_items = [
                    [ 'sh-font-faces', 'font-faces.css', 'Inline', true ],
                    [ 'sh-root',       'root.css',       'Normal', true ],
                    [ 'sh-conditional','plugin.css',     INLINE_CSS ? 'Inline' : ( $settings['css_lazy_load'] ? 'Lazy' : 'Normal' ), true ],
                    [ 'sh-main',       'main-combined.min.css', INLINE_CSS ? 'Inline' : ( $settings['css_lazy_load'] ? 'Lazy' : 'Normal' ), true ],
                    [ 'locale',        'locale-{lang}.css', 'Normal', true ],
                    [ 'woocommerce-*', 'WooCommerce CSS', 'Dequeued', $settings['remove_woocommerce_styles'] ],
                    [ 'global-styles', 'WP Global Styles', 'Dequeued (auto)', $settings['remove_global_styles'] !== false ],
                    [ 'wp-block-library', 'Block Library', 'Dequeued (auto)', $settings['remove_block_styles'] !== false ],
                ];
                foreach ( $css_items as [$handle, $file, $method, $active] ) :
                    $col = $active ? '#22c55e' : '#9ca3af';
                    $method_col = match($method) { 'Inline' => '#8b5cf6', 'Lazy' => '#3b82f6', 'Dequeued', 'Dequeued (auto)' => '#ef4444', default => '#6b7280' };
                ?>
                <tr style="border-bottom:1px solid #f9fafb">
                    <td style="padding:8px 18px;font-family:Consolas,monospace;font-size:12px"><?php echo esc_html($handle); ?></td>
                    <td style="padding:8px 18px;font-size:12px;color:#6b7280"><?php echo esc_html($file); ?></td>
                    <td style="padding:8px 18px"><span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;background:<?php echo $method_col; ?>22;color:<?php echo $method_col; ?>"><?php echo esc_html($method); ?></span></td>
                    <td style="padding:8px 18px"><span style="color:<?php echo $col; ?>;font-weight:600"><?php echo $active ? '✓' : '✗'; ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- JS Loading -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden">
            <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6"><strong>JS Loading — <?php echo $is_prod ? 'Production' : 'Development'; ?> Mode</strong></div>
            <table style="width:100%;border-collapse:collapse">
                <thead><tr>
                    <?php foreach(['Handle','File','Method','Status'] as $h): ?>
                    <th style="padding:10px 18px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #f3f4f6"><?php echo $h; ?></th>
                    <?php endforeach; ?>
                </tr></thead>
                <tbody>
                <?php
                $js_items = $is_prod ? [
                    [ 'jquery',       'jquery.min.js',          'Normal', true ],
                    [ 'footer-*',     'functions/*.js',          'Mass enqueue + guard', true ],
                    [ 'plugin-*',     'plugins/*.js',            'Conditional', true ],
                    [ 'pre-*',        'pre/*.js',                'Mass enqueue', true ],
                    [ 'main-*',       'main/*.js',               'Mass enqueue', true ],
                    [ 'image-sizes',  'image-sizes.js',          'Defer', true ],
                ] : [
                    [ 'jquery',              'jquery.min.js',          'Normal', true ],
                    [ 'pre',                 'pre-combined.min.js',    'Normal + guard', true ],
                    [ 'plugins-conditional', 'plugin_js (conditional)','Normal', true ],
                    [ 'main',                'main-combined.min.js',   'Normal + guard', true ],
                    [ 'image-sizes',         'image-sizes.js',         'Defer', true ],
                    [ 'locale',              'locale/{lang}.js',       'Normal', true ],
                ];
                foreach ( $js_items as [$handle, $file, $method, $active] ) :
                    $method_col = match(true) { str_contains($method,'Defer') => '#3b82f6', str_contains($method,'guard') => '#8b5cf6', default => '#6b7280' };
                ?>
                <tr style="border-bottom:1px solid #f9fafb">
                    <td style="padding:8px 18px;font-family:Consolas,monospace;font-size:12px"><?php echo esc_html($handle); ?></td>
                    <td style="padding:8px 18px;font-size:12px;color:#6b7280"><?php echo esc_html($file); ?></td>
                    <td style="padding:8px 18px"><span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;background:<?php echo $method_col; ?>22;color:<?php echo $method_col; ?>"><?php echo esc_html($method); ?></span></td>
                    <td style="padding:8px 18px"><span style="color:#22c55e;font-weight:600">✓</span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        </div>
        <div class="sh-sidebar"><?php self::renderSidebar($settings); ?></div>
        </div>
        <?php
    }

    // ─── Settings Tab ─────────────────────────────────────────────────────────

    private static function renderSettingsTab( array $settings, string $nonce, string $ajax_url ): void
    {
        ?>
        <div class="sh-layout">
        <div class="sh-main">
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden">
            <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6"><strong>Asset Settings</strong></div>
            <div style="padding:18px">

                <!-- Production Mode — ACF'den geliyor, burada sadece göster -->
                <div class="sh-field-group" style="background:#f9fafb;border-radius:6px;padding:12px 14px;margin-bottom:20px">
                    <div style="display:flex;align-items:center;gap:10px">
                        <?php $is_prod = defined('ENABLE_PRODUCTION') && ENABLE_PRODUCTION; ?>
                        <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;background:<?php echo $is_prod ? '#dcfce7' : '#fef9c3'; ?>;color:<?php echo $is_prod ? '#15803d' : '#92400e'; ?>">
                            <?php echo $is_prod ? '✓ Production Mode' : '⚠ Development Mode'; ?>
                        </span>
                        <span style="font-size:12px;color:#6b7280">Managed in <strong>ACF Options → General Settings</strong> (options_enable_production)</span>
                    </div>
                </div>

                <!-- ACF'den gelen build ayarları — sadece göster -->
                <div style="background:#f9fafb;border-radius:6px;padding:12px 14px;margin-bottom:20px">
                    <div style="font-size:12px;font-weight:600;color:#374151;margin-bottom:8px">Build Settings <span style="font-weight:400;color:#9ca3af">(managed in ACF Options → General Settings)</span></div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <?php
                        $build_items = [
                            [ 'Seperate Block Inline CSS', SEPERATE_CSS ],
                            [ 'Add Inline CSS',            INLINE_CSS   ],
                            [ 'Seperate Block Inline JS',  SEPERATE_JS  ],
                            [ 'Add Inline JS',             INLINE_JS    ],
                        ];
                        foreach ( $build_items as [$label, $active] ) :
                            $col = $active ? '#22c55e' : '#9ca3af';
                        ?>
                            <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;background:<?php echo $col; ?>22;color:<?php echo $col; ?>">
                                <?php echo $active ? '✓' : '✗'; ?> <?php echo esc_html($label); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="sh-field-group">
                    <label class="sh-field-label"><label class="sh-toggle" style="margin-right:8px"><input type="checkbox" id="sh-css-lazy" <?php checked($settings['css_lazy_load']); ?>><span class="sh-toggle-slider"></span></label>CSS Lazy Loading</label>
                    <p class="sh-field-hint">Load non-critical CSS asynchronously (preload + onload trick).</p>
                </div>

                <div class="sh-field-group">
                    <label class="sh-field-label"><label class="sh-toggle" style="margin-right:8px"><input type="checkbox" id="sh-js-defer" <?php checked($settings['js_defer']); ?>><span class="sh-toggle-slider"></span></label>JS Defer</label>
                    <p class="sh-field-hint">Add defer attribute to non-critical scripts.</p>
                </div>

                <hr style="border:none;border-top:1px solid #f3f4f6;margin:20px 0">

                <div class="sh-field-group">
                    <label class="sh-field-label">Remove WP Global Styles</label>
                    <select name="remove_global_styles" class="sh-select" style="max-width:200px">
                        <option value="auto" <?php selected($settings['remove_global_styles'],'auto'); ?>>Auto (remove if no core blocks)</option>
                        <option value="1"    <?php selected($settings['remove_global_styles'],'1'); ?>>Always remove</option>
                        <option value="0"    <?php selected($settings['remove_global_styles'],'0'); ?>>Keep</option>
                    </select>
                </div>

                <div class="sh-field-group">
                    <label class="sh-field-label">Remove Block Library CSS</label>
                    <select name="remove_block_styles" class="sh-select" style="max-width:200px">
                        <option value="auto" <?php selected($settings['remove_block_styles'],'auto'); ?>>Auto (remove if no core blocks)</option>
                        <option value="1"    <?php selected($settings['remove_block_styles'],'1'); ?>>Always remove</option>
                        <option value="0"    <?php selected($settings['remove_block_styles'],'0'); ?>>Keep</option>
                    </select>
                </div>

                <div class="sh-field-group">
                    <label class="sh-field-label"><label class="sh-toggle" style="margin-right:8px"><input type="checkbox" id="sh-remove-classic" <?php checked($settings['remove_classic_theme_styles']); ?>><span class="sh-toggle-slider"></span></label>Remove Classic Theme Styles</label>
                </div>

                <?php if ( defined('ENABLE_ECOMMERCE') && ENABLE_ECOMMERCE ) : ?>
                <div class="sh-field-group">
                    <label class="sh-field-label"><label class="sh-toggle" style="margin-right:8px"><input type="checkbox" id="sh-remove-woo" <?php checked($settings['remove_woocommerce_styles']); ?>><span class="sh-toggle-slider"></span></label>Remove WooCommerce Styles</label>
                    <p class="sh-field-hint">Removes woocommerce-layout, woocommerce-general, woocommerce-smallscreen.</p>
                </div>
                <?php endif; ?>

                <div class="sh-field-group">
                    <label class="sh-field-label"><label class="sh-toggle" style="margin-right:8px"><input type="checkbox" id="sh-block-gfonts" <?php checked($settings['block_google_fonts']); ?>><span class="sh-toggle-slider"></span></label>Block Google Fonts</label>
                    <p class="sh-field-hint">Dequeue and block all Google Fonts (use self-hosted fonts instead).</p>
                </div>

                <div style="margin-top:24px">
                    <button type="button" id="sh-am-save" class="sh-btn sh-btn-primary">Save Settings</button>
                </div>
            </div>
        </div>
        </div>
        <div class="sh-sidebar"><?php self::renderSidebar($settings); ?></div>
        </div>
        <?php
    }

    // ─── Filters Tab ──────────────────────────────────────────────────────────

    private static function renderFiltersTab(): void
    {
        $filters = [
            // CSS
            [ 'sh_frontend_styles',    'filter', 'Add CSS to frontend', "add_filter('sh_frontend_styles', function(\$styles) {\n    \$styles['my-css'] = ['url' => MY_URL . 'style.css', 'deps' => [], 'version' => '1.0'];\n    return \$styles;\n});" ],
            [ 'sh_admin_styles',       'filter', 'Add CSS to admin', "add_filter('sh_admin_styles', function(\$styles) {\n    \$styles['my-admin'] = ['url' => MY_URL . 'admin.css'];\n    return \$styles;\n});" ],
            [ 'sh_lazy_css_handles',   'filter', 'Add handles to lazy load list', "add_filter('sh_lazy_css_handles', fn(\$h) => array_merge(\$h, ['my-heavy-css']));" ],
            [ 'sh_lazy_css_patterns',  'filter', 'Add URL patterns to lazy load', "add_filter('sh_lazy_css_patterns', fn(\$p) => array_merge(\$p, ['my-plugin']));" ],
            [ 'sh_dequeue_styles',     'filter', 'Dequeue CSS handles', "add_filter('sh_dequeue_styles', fn(\$h) => array_merge(\$h, ['plugin-bloated-css']));" ],
            [ 'sh_inline_head_css',    'filter', 'Add inline CSS to <head>', "add_filter('sh_inline_head_css', fn(\$css) => \$css . '.my{color:red}');" ],
            // JS
            [ 'sh_frontend_scripts',   'filter', 'Add JS to frontend footer', "add_filter('sh_frontend_scripts', function(\$scripts) {\n    \$scripts['my-js'] = ['url' => MY_URL . 'script.js', 'footer' => true, 'defer' => true];\n    return \$scripts;\n});" ],
            [ 'sh_admin_scripts',      'filter', 'Add JS to admin', "add_filter('sh_admin_scripts', function(\$scripts) {\n    \$scripts['my-admin-js'] = ['url' => MY_URL . 'admin.js', 'deps' => ['jquery']];\n    return \$scripts;\n});" ],
            [ 'sh_defer_js_handles',   'filter', 'Add handles to defer list', "add_filter('sh_defer_js_handles', fn(\$h) => array_merge(\$h, ['my-heavy-js']));" ],
            [ 'sh_lazy_js_patterns',   'filter', 'Add patterns to lazy JS load', "add_filter('sh_lazy_js_patterns', fn(\$p) => array_merge(\$p, ['my-heavy-lib']));" ],
            [ 'sh_dequeue_scripts',    'filter', 'Dequeue JS handles', "add_filter('sh_dequeue_scripts', fn(\$h) => array_merge(\$h, ['plugin-bloated-js']));" ],
            [ 'sh_inline_footer_js',   'filter', 'Add inline JS to footer', "add_filter('sh_inline_footer_js', fn(\$js) => \$js . 'console.log(\"loaded\");');" ],
            [ 'sh_inline_head_js',     'filter', 'Add inline JS to <head>', "add_filter('sh_inline_head_js', fn(\$js) => \$js . 'var myVar=1;');" ],
            // Preload / Resource hints
            [ 'sh_preconnect_domains', 'filter', 'Add preconnect domains', "add_filter('sh_preconnect_domains', fn(\$d) => array_merge(\$d, ['https://cdn.example.com' => []]));" ],
                        [ 'sh_preload_resources',  'filter', 'Add preload resources', "add_filter('sh_preload_resources', fn(\$r) => array_merge(\$r, [['url' => MY_URL . 'hero.jpg', 'as' => 'image']]));" ],
            [ 'sh_critical_css',       'filter', 'Override critical CSS', "add_filter('sh_critical_css', fn(\$css) => \$css . '.hero{min-height:400px}');" ],
            [ 'sh_inline_admin_head_js','filter', 'Add inline JS to admin head', "add_filter('sh_inline_admin_head_js', fn(\$js) => \$js . 'var adminVar=1;');" ],
        ];
        ?>
        <div class="sh-layout">
        <div class="sh-main">

        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden">
            <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6">
                <strong>Available Filters</strong>
                <p style="margin:4px 0 0;font-size:12px;color:#9ca3af">Add these to your theme's <code>functions.php</code> or any plugin file.</p>
            </div>
            <table style="width:100%;border-collapse:collapse">
                <thead><tr>
                    <th style="padding:10px 18px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #f3f4f6;width:200px">Filter</th>
                    <th style="padding:10px 18px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #f3f4f6;width:200px">Description</th>
                    <th style="padding:10px 18px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #f3f4f6">Example</th>
                </tr></thead>
                <tbody>
                <?php foreach ( $filters as [$name, $type, $desc, $example] ) :
                    $col = '#0ea5e9';
                ?>
                <tr style="border-bottom:1px solid #f9fafb">
                    <td style="padding:10px 18px;vertical-align:top">
                        <code style="font-size:11px;background:#f3f4f6;padding:2px 6px;border-radius:3px;display:block;word-break:break-all"><?php echo esc_html($name); ?></code>
                        <span style="display:inline-block;margin-top:4px;padding:1px 6px;border-radius:20px;font-size:10px;font-weight:600;background:<?php echo $col; ?>22;color:<?php echo $col; ?>"><?php echo $type; ?></span>
                    </td>
                    <td style="padding:10px 18px;font-size:13px;color:#374151;vertical-align:top"><?php echo esc_html($desc); ?></td>
                    <td style="padding:10px 18px;vertical-align:top">
                        <div style="position:relative">
                            <pre style="background:#0d1117;color:#c8d6e5;padding:10px 12px;border-radius:6px;font-size:11px;font-family:Consolas,monospace;margin:0;overflow-x:auto;white-space:pre-wrap"><?php echo esc_html($example); ?></pre>
                            <button type="button" class="sh-am-copy-btn"
                                data-code="<?php echo esc_attr($example); ?>"
                                style="position:absolute;top:6px;right:6px;background:#374151;color:#9ca3af;border:none;border-radius:4px;padding:2px 8px;font-size:10px;cursor:pointer">Copy</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        </div>
        <div class="sh-sidebar"><?php self::renderSidebar(AssetSettings::get()); ?></div>
        </div>
        <?php
    }

    // ─── Cache Tab ────────────────────────────────────────────────────────────

    private static function renderCacheTab( string $nonce, string $ajax_url ): void
    {
        $cache_info = \SaltHareket\AssetManager\Concerns\HandlesHelpers::getCacheInfo();
        ?>
        <div class="sh-layout">
        <div class="sh-main">

        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-bottom:16px">
            <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center">
                <strong>Inline CSS Cache</strong>
                <button type="button" id="sh-am-purge" class="sh-btn sh-btn-sm" style="margin-left:auto">Clear Cache</button>
            </div>
            <div style="padding:18px">
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:16px">
                    <div style="background:#f9fafb;border-radius:6px;padding:14px;text-align:center">
                        <div style="font-size:24px;font-weight:700;color:#1d2327"><?php echo (int)$cache_info['count']; ?></div>
                        <div style="font-size:11px;color:#6b7280;margin-top:4px">Cached Files</div>
                    </div>
                    <div style="background:#f9fafb;border-radius:6px;padding:14px;text-align:center">
                        <div style="font-size:24px;font-weight:700;color:#1d2327"><?php echo esc_html($cache_info['total_size']); ?></div>
                        <div style="font-size:11px;color:#6b7280;margin-top:4px">Total Size</div>
                    </div>
                    <div style="background:#f9fafb;border-radius:6px;padding:14px;text-align:center">
                        <div style="font-size:24px;font-weight:700;color:#1d2327">7d</div>
                        <div style="font-size:11px;color:#6b7280;margin-top:4px">TTL</div>
                    </div>
                </div>

                <?php if ( ! empty($cache_info['files']) ) : ?>
                <table style="width:100%;border-collapse:collapse">
                    <thead><tr>
                        <th style="padding:8px 12px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #f3f4f6">File</th>
                        <th style="padding:8px 12px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #f3f4f6">Size</th>
                        <th style="padding:8px 12px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #f3f4f6">Modified</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ( $cache_info['files'] as $f ) : ?>
                    <tr style="border-bottom:1px solid #f9fafb">
                        <td style="padding:8px 12px;font-family:Consolas,monospace;font-size:11px;color:#374151"><?php echo esc_html($f['name']); ?></td>
                        <td style="padding:8px 12px;font-size:12px;color:#6b7280"><?php echo esc_html($f['size']); ?></td>
                        <td style="padding:8px 12px;font-size:12px;color:#6b7280"><?php echo esc_html($f['modified']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else : ?>
                    <p style="color:#9ca3af;font-size:13px">No cached files yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <div style="background:#f0f7ff;border:1px solid #bfdbfe;border-radius:8px;padding:14px 16px">
            <strong style="font-size:13px;color:#1d4ed8">Cache Info</strong>
            <ul style="margin:8px 0 0;padding-left:20px;font-size:13px;color:#374151;line-height:1.8">
                <li>Cache files are stored in <code>static/css/cache/</code></li>
                <li>Files are invalidated automatically when source CSS changes (filemtime check)</li>
                <li>Old files (7+ days) are cleaned up automatically on 2% of requests</li>
                <li>Manual purge: add <code>?purge_assets</code> to any URL</li>
            </ul>
        </div>

        </div>
        <div class="sh-sidebar"><?php self::renderSidebar(AssetSettings::get()); ?></div>
        </div>
        <?php
    }

    // ─── Debug Tab ────────────────────────────────────────────────────────────

    private static function renderDebugTab(): void
    {
        $settings     = AssetSettings::get();
        $is_prod      = ENABLE_PRODUCTION;
        $inline_css   = INLINE_CSS;
        $seperate_css = SEPERATE_CSS;
        $seperate_js  = SEPERATE_JS;
        ?>
        <div class="sh-layout">
        <div class="sh-main">

        <!-- Frontend CSS — mevcut ayarlara göre ne yüklenir -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-bottom:16px">
            <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6">
                <strong>Frontend CSS — Mevcut Ayarlara Göre</strong>
                <p style="margin:4px 0 0;font-size:12px;color:#9ca3af">
                    Mode: <strong><?php echo $is_prod ? 'Production' : 'Development'; ?></strong> |
                    Seperate Block Inline CSS: <strong><?php echo $seperate_css ? 'Evet' : 'Hayır'; ?></strong> |
                    Add Inline CSS: <strong><?php echo $inline_css ? 'Evet' : 'Hayır'; ?></strong>
                </p>
            </div>
            <table style="width:100%;border-collapse:collapse">
                <thead><tr>
                    <?php foreach(['Handle','Dosya','Yükleme Yöntemi','Açıklama'] as $h): ?>
                    <th style="padding:10px 18px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #f3f4f6"><?php echo $h; ?></th>
                    <?php endforeach; ?>
                </tr></thead>
                <tbody>
                <?php
                $css_method = $inline_css ? 'Inline (HTML içinde)' : ( $settings['css_lazy_load'] && ! $is_prod ? 'Lazy (preload+onload)' : 'Normal <link>' );
                $css_method_col = $inline_css ? '#8b5cf6' : ( $settings['css_lazy_load'] && ! $is_prod ? '#3b82f6' : '#6b7280' );

                $frontend_css = [
                    [ 'sh-font-faces',    'font-faces.css',                    'Inline (HTML içinde)', '#8b5cf6', 'Font face tanımları — her zaman inline' ],
                    [ 'sh-root',          'css/root.css',                      'Normal <link>',        '#6b7280', 'CSS değişkenleri, root stiller' ],
                    [ 'sh-conditional',   'css/plugin_css (SITE_ASSETS)',      $css_method,            $css_method_col, 'Plugin CSS — PAE tarafından üretilir' ],
                    [ 'sh-main',          $seperate_css ? 'css/css_page (SITE_ASSETS)' : 'css/main-combined.min.css', $css_method, $css_method_col, 'Ana CSS — PAE tarafından üretilir' ],
                    [ 'locale',           'css/locale-{lang}.css',             'Normal <link>',        '#6b7280', 'Dile özgü stiller (varsa)' ],
                    [ 'woocommerce-*',    'WooCommerce CSS',                   $settings['remove_woocommerce_styles'] ? '✗ Dequeue edildi' : 'Normal <link>', $settings['remove_woocommerce_styles'] ? '#ef4444' : '#6b7280', 'WC stilleri' ],
                    [ 'global-styles',    'WP Global Styles',                  $settings['remove_global_styles'] !== false ? '✗ Dequeue (auto)' : 'Normal <link>', $settings['remove_global_styles'] !== false ? '#ef4444' : '#6b7280', 'Core block yoksa kaldırılır' ],
                    [ 'wp-block-library', 'Block Library CSS',                 $settings['remove_block_styles'] !== false ? '✗ Dequeue (auto)' : 'Normal <link>', $settings['remove_block_styles'] !== false ? '#ef4444' : '#6b7280', 'Core block yoksa kaldırılır' ],
                    [ 'Google Fonts',     'fonts.googleapis.com/*',            $settings['block_google_fonts'] ? '✗ Engellendi' : 'Normal <link>', $settings['block_google_fonts'] ? '#ef4444' : '#6b7280', 'Tüm Google Fonts istekleri' ],
                ];
                foreach ( $frontend_css as [$handle, $file, $method, $col, $desc] ) :
                ?>
                <tr style="border-bottom:1px solid #f9fafb">
                    <td style="padding:8px 18px;font-family:Consolas,monospace;font-size:12px"><?php echo esc_html($handle); ?></td>
                    <td style="padding:8px 18px;font-size:11px;color:#6b7280"><?php echo esc_html($file); ?></td>
                    <td style="padding:8px 18px"><span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;background:<?php echo $col; ?>22;color:<?php echo $col; ?>"><?php echo esc_html($method); ?></span></td>
                    <td style="padding:8px 18px;font-size:11px;color:#9ca3af"><?php echo esc_html($desc); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Frontend JS -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-bottom:16px">
            <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6">
                <strong>Frontend JS — Mevcut Ayarlara Göre</strong>
                <p style="margin:4px 0 0;font-size:12px;color:#9ca3af">
                    Mode: <strong><?php echo $is_prod ? 'Production' : 'Development'; ?></strong> |
                    Seperate Block Inline JS: <strong><?php echo $seperate_js ? 'Evet' : 'Hayır'; ?></strong>
                </p>
            </div>
            <table style="width:100%;border-collapse:collapse">
                <thead><tr>
                    <?php foreach(['Handle','Dosya','Yükleme Yöntemi','Açıklama'] as $h): ?>
                    <th style="padding:10px 18px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #f3f4f6"><?php echo $h; ?></th>
                    <?php endforeach; ?>
                </tr></thead>
                <tbody>
                <?php
                $js_items = $is_prod ? [
                    [ 'jquery',       'js/jquery.min.js',           'Normal',              '#6b7280', 'jQuery — kendi versiyonu' ],
                    [ 'footer-*',     'js/functions/*.js',           'Mass enqueue + guard','#8b5cf6', 'Function dosyaları — IIFE guard ile' ],
                    [ 'plugin-*',     'js/plugins/*.js',             'Conditional',         '#3b82f6', 'Plugin JS — SITE_ASSETS koşuluna göre' ],
                    [ 'pre-*',        'js/pre/*.js',                 'Mass enqueue',        '#8b5cf6', 'Pre dosyaları' ],
                    [ 'main-*',       'js/main/*.js',                'Mass enqueue',        '#8b5cf6', 'Ana JS dosyaları' ],
                    [ 'image-sizes',  'js/image-sizes.js',           'Defer',               '#3b82f6', 'Resim boyutları' ],
                ] : [
                    [ 'jquery',              'js/jquery.min.js',          'Normal',         '#6b7280', 'jQuery — kendi versiyonu' ],
                    [ 'pre',                 'js/pre-combined.min.js',    'Normal + guard', '#8b5cf6', 'Pre combined — IIFE guard ile' ],
                    [ 'plugins-conditional', 'js/plugin_js (SITE_ASSETS)','Conditional',   '#3b82f6', 'Plugin JS — SITE_ASSETS koşuluna göre' ],
                    [ 'main',                'js/main-combined.min.js',   'Normal + guard', '#8b5cf6', 'Ana JS — IIFE guard ile' ],
                    [ 'image-sizes',         'js/image-sizes.js',         'Defer',          '#3b82f6', 'Resim boyutları' ],
                    [ 'locale',              'js/locale/{lang}.js',       'Normal',         '#6b7280', 'Dile özgü JS (varsa)' ],
                ];
                foreach ( $js_items as [$handle, $file, $method, $col, $desc] ) :
                ?>
                <tr style="border-bottom:1px solid #f9fafb">
                    <td style="padding:8px 18px;font-family:Consolas,monospace;font-size:12px"><?php echo esc_html($handle); ?></td>
                    <td style="padding:8px 18px;font-size:11px;color:#6b7280"><?php echo esc_html($file); ?></td>
                    <td style="padding:8px 18px"><span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;background:<?php echo $col; ?>22;color:<?php echo $col; ?>"><?php echo esc_html($method); ?></span></td>
                    <td style="padding:8px 18px;font-size:11px;color:#9ca3af"><?php echo esc_html($desc); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        </div>
        <div class="sh-sidebar"><?php self::renderSidebar(AssetSettings::get()); ?></div>
        </div>
        <?php
    }

    // ─── Sidebar ──────────────────────────────────────────────────────────────

    private static function renderSidebar( array $settings ): void
    {
        $is_prod = defined('ENABLE_PRODUCTION') && ENABLE_PRODUCTION;
        ?>
        <div class="sh-sidebar-box">
            <h3>Quick Actions</h3>
            <ul style="margin:0;padding:0;list-style:none;font-size:12px">
                <li style="padding:5px 0;border-bottom:1px solid #f3f4f6"><a href="<?php echo esc_url(add_query_arg('purge_assets', '1')); ?>">→ Purge All Cache</a></li>
                <li style="padding:5px 0;border-bottom:1px solid #f3f4f6"><a href="?page=sh-asset-manager&tab=debug">→ Debug Assets</a></li>
                <li style="padding:5px 0"><a href="?page=sh-asset-manager&tab=filters">→ Filter Reference</a></li>
            </ul>
        </div>
        <div class="sh-sidebar-box" style="margin-top:12px">
            <h3>Current Mode</h3>
            <ul style="margin:0;padding:0;list-style:none;font-size:12px;color:#6b7280">
                <li style="padding:4px 0">Mode: <strong><?php echo $is_prod ? 'Production' : 'Development'; ?></strong></li>
                <li style="padding:4px 0">CSS: <strong><?php echo INLINE_CSS ? 'Inline' : ($settings['css_lazy_load'] ? 'Lazy' : 'Normal'); ?></strong></li>
                <li style="padding:4px 0">JS: <strong><?php echo $settings['js_defer'] ? 'Deferred' : 'Normal'; ?></strong></li>
                <li style="padding:4px 0">Google Fonts: <strong><?php echo $settings['block_google_fonts'] ? 'Blocked' : 'Allowed'; ?></strong></li>
            </ul>
        </div>
        <?php
    }
}

