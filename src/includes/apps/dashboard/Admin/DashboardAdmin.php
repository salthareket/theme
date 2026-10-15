<?php

namespace SaltHareket\Dashboard\Admin;

/**
 * DashboardAdmin
 * Theme Settings altında ilk menü öğesi. 4 kolonlu app grid.
 *
 * @version 1.0.0
 */
class DashboardAdmin
{
    public static function register(): void
    {
        if ( ! is_admin() ) return;
        add_action( 'admin_menu',             [ self::class, 'addMenuPage' ], 11 );
        add_action( 'admin_enqueue_scripts',  [ self::class, 'enqueueAssets' ] );
        add_action( 'admin_head',             [ self::class, 'hideNotices' ] );
        add_action( 'wp_ajax_sh_dash_toggle', [ self::class, 'ajaxToggle' ] );
        add_action( 'wp_ajax_sh_dash_purge',  [ self::class, 'ajaxPurge' ] );
        add_action( 'admin_bar_menu',         [ self::class, 'addAdminBarMenu' ], 100 );
    }

    /**
     * WP Admin Bar - "Theme Settings" node'u ekler.
     * Tiklaninca Dashboard'a gider.
     * Hover'da: Apps (tum app'ler listesi) + Theme Update alt menuleri cikar.
     */
    public static function addAdminBarMenu( \WP_Admin_Bar $wp_admin_bar ): void {
        if ( ! current_user_can( 'manage_options' ) ) return;

        // Ana node: Theme Settings → Dashboard
        $wp_admin_bar->add_node( [
            'id'    => 'sh-theme-settings',
            'title' => '<span class="ab-icon dashicons dashicons-admin-generic" style="margin-top:2px;"></span> Theme Settings',
            'href'  => admin_url( 'admin.php?page=sh-dashboard' ),
            'meta'  => [ 'title' => 'Theme Settings Dashboard' ],
        ] );

        // Alt node: Apps (tiklaninca Dashboard)
        $wp_admin_bar->add_node( [
            'parent' => 'sh-theme-settings',
            'id'     => 'sh-theme-apps',
            'title'  => '🏠 Apps',
            'href'   => admin_url( 'admin.php?page=sh-dashboard' ),
        ] );

        // Admin bar icin hafif app listesi — DB sorgusu yok
        $bar_apps = [
            [ 'key' => 'asset_manager',   'icon' => '⚡', 'title' => 'Asset Manager',     'url' => 'sh-asset-manager' ],
            [ 'key' => 'download_log',    'icon' => '📥', 'title' => 'Download Log',      'url' => 'sh-download-log' ],
            [ 'key' => 'theme_export',    'icon' => '📤', 'title' => 'Export',            'url' => 'sh-theme-export' ],
            [ 'key' => 'general_settings','icon' => '⚙️', 'title' => 'General Settings',  'url' => 'sh-general-settings' ],
            [ 'key' => 'localization',    'icon' => '🌍', 'title' => 'Localization',      'url' => 'sh-localization' ],
            [ 'key' => 'maintenance',     'icon' => '🔧', 'title' => 'Maintenance',       'url' => 'sh-maintenance' ],
            [ 'key' => 'membership',      'icon' => '👤', 'title' => 'Membership',        'url' => 'sh-membership' ],
            [ 'key' => 'notifications',   'icon' => '🔔', 'title' => 'Notifications',     'url' => 'sh-notifications' ],
            [ 'key' => 'plugin_manager',  'icon' => '🔌', 'title' => 'Plugin Manager',    'url' => 'sh-plugin-manager' ],
            [ 'key' => 'reactions',       'icon' => '❤️', 'title' => 'Reactions',         'url' => 'sh-reactions' ],
            [ 'key' => 'reviews',         'icon' => '⭐', 'title' => 'Reviews',           'url' => 'sh-reviews' ],
            [ 'key' => 'search_ranks',    'icon' => '🔍', 'title' => 'Search Ranks',      'url' => 'sh-search-history' ],
            [ 'key' => 'sms',             'icon' => '📱', 'title' => 'SMS',               'url' => 'sh-sms' ],
            [ 'key' => 'templates',       'icon' => '📄', 'title' => 'Templates',         'url' => 'edit.php?post_type=template' ],
            [ 'key' => 'theme_styles',    'icon' => '🎨', 'title' => 'Theme Styles',      'url' => 'sh-theme-styles' ],
            [ 'key' => 'video_processor', 'icon' => '🎬', 'title' => 'Video Processor',   'url' => 'sh-video-processor' ],
        ];

        foreach ( $bar_apps as $app ) {
            $wp_admin_bar->add_node( [
                'parent' => 'sh-theme-apps',
                'id'     => 'sh-app-' . esc_attr( $app['key'] ),
                'title'  => $app['icon'] . ' ' . esc_html( $app['title'] ),
                'href'   => admin_url( 'admin.php?page=' . $app['url'] ),
            ] );
        }

        // Templates'e template-types submenu'leri ekle
        $template_types = get_terms( [
            'taxonomy'   => 'template-types',
            'hide_empty' => false,
            'number'     => 20,
        ] );
        if ( ! is_wp_error( $template_types ) && ! empty( $template_types ) ) {
            foreach ( $template_types as $term ) {
                $wp_admin_bar->add_node( [
                    'parent' => 'sh-app-templates',
                    'id'     => 'sh-tmpl-type-' . esc_attr( $term->slug ),
                    'title'  => esc_html( ucfirst( $term->name ) ) . ' <span style="opacity:.5;font-size:11px">(' . (int)$term->count . ')</span>',
                    'href'   => admin_url( 'edit.php?post_type=template&template-types=' . esc_attr( $term->slug ) ),
                ] );
            }
        }

        // Alt node: Theme Update
        $wp_admin_bar->add_node( [
            'parent' => 'sh-theme-settings',
            'id'     => 'sh-theme-update',
            'title'  => '🔄 Theme Update',
            'href'   => admin_url( 'admin.php?page=update-theme' ),
        ] );
    }

    public static function addMenuPage(): void
    {
        // Dashboard — görünür
        add_submenu_page(
            'theme-settings',
            '🏠 Dashboard',
            '🏠 Dashboard',
            'manage_options',
            'sh-dashboard',
            [ self::class, 'renderPage' ],
            0
        );

        // ─── Hidden submenus — WP erişim kontrolü için kayıtlı ama menüde görünmüyor ───
        // Her app'in sayfası admin.php?page=slug ile erişilebilir olmalı.
        // add_submenu_page sonra $submenu'den siliniyor → menüde yok, ama sayfa erişimi var.
        $hidden_pages = [
            [ 'sh-asset-manager',      'Assets',          '\SaltHareket\AssetManager\Admin\AssetManagerAdmin',    'renderPage' ],
            [ 'sh-general-settings',   'General Settings', '\SaltHareket\GeneralSettings\Admin\GeneralSettingsAdmin', 'renderPage' ],
            [ 'sh-download-log',    'Download Log',    '\SaltHareket\DownloadLog\Admin\DownloadLogAdmin',       'renderPage' ],
            [ 'sh-localization',    'Localization',    '\SaltHareket\Localization\Admin\LocalizationAdmin',     'renderPage' ],
            [ 'sh-maintenance',     'Maintenance',     '\SaltHareket\Maintenance\Admin\MaintenanceAdmin',       'renderPage' ],
            [ 'sh-membership',      'Membership',      '\SaltHareket\Membership\Admin\MembershipAdmin',         'renderPage' ],
            [ 'sh-notifications',   'Notifications',   '\SaltHareket\Notifications\Admin\NotificationsAdmin',   'renderPage' ],
            [ 'sh-plugin-manager',  'Plugin Manager',  '\SaltHareket\PluginManager\Admin\PluginManagerAdmin',   'renderPage' ],
            [ 'sh-reactions',       'Reactions',       '\SaltHareket\Reactions\Admin\ReactionsAdmin',           'renderPage' ],
            [ 'sh-reviews',         'Reviews',         '\SaltHareket\Reviews\Admin\ReviewsAdmin',               'renderPage' ],
            [ 'sh-sms',             'SMS',             '\SaltHareket\Sms\Admin\SmsAdmin',                       'renderPage' ],
            [ 'sh-theme-export',    'Export',          '\SaltHareket\ThemeExport\Admin\ThemeExportAdmin',       'renderPage' ],
            [ 'sh-video-processor', 'Video Processor', '\SaltHareket\VideoProcessor\Admin\VideoProcessorAdmin', 'renderPage' ],
        ];

        foreach ( $hidden_pages as [ $slug, $title, $class, $method ] ) {
            if ( class_exists( $class ) && method_exists( $class, $method ) ) {
                add_submenu_page( 'theme-settings', $title, $title, 'manage_options', $slug, [ $class, $method ] );
            }
        }

        // Theme Styles — instance metodu, ayrı ele alınır
        if ( class_exists( 'Theme_Styles' ) ) {
            $ts = \Theme_Styles::init();
            add_submenu_page( 'theme-settings', 'Theme Styles', 'Theme Styles', 'manage_options', 'sh-theme-styles', [ $ts, 'render_admin_page' ] );
        }

        // Search History — instance metodu, kendi register_admin_page() hook'u aktif (SearchHistory constructor'da)
        // Slug: search-history — $submenu silme işlemi onu da temizleyecek

        // Kayıtlı hidden sayfaları $submenu'den sil — menüde görünmesin
        add_action( 'admin_head', function () {
            global $submenu;
            if ( ! isset( $submenu['theme-settings'] ) ) return;
            $visible_slugs = [ 'sh-dashboard', 'update-theme' ];
            foreach ( $submenu['theme-settings'] as $key => $item ) {
                if ( ! in_array( $item[2] ?? '', $visible_slugs, true ) ) {
                    unset( $submenu['theme-settings'][ $key ] );
                }
            }
        }, 999 );
    }

    public static function hideNotices(): void
    {
        if ( ( $_GET['page'] ?? '' ) !== 'sh-dashboard' ) return;
        echo '<style>body .notice:not(.sh-inline),body .updated:not(.sh-inline),body .error:not(.sh-inline){display:none!important}</style>';
    }

    public static function enqueueAssets( string $hook ): void
    {
        if ( $hook !== 'theme-settings_page_sh-dashboard' ) return;
        $css_path = dirname( __DIR__, 2 ) . '/sh-admin.css';
        $css_url  = trailingslashit( SH_INCLUDES_URL ) . 'apps/sh-admin.css';
        if ( file_exists( $css_path ) ) {
            wp_enqueue_style( 'sh-admin-kit', $css_url, [], filemtime( $css_path ) );
        }
    }

    // ─── Toggle AJAX ──────────────────────────────────────────────────────────

    public static function ajaxToggle(): void
    {
        if ( ! current_user_can('manage_options') ) wp_send_json_error('Unauthorized');
        check_ajax_referer('sh_dash_toggle','nonce');

        $app   = sanitize_key( $_POST['app']   ?? '' );
        $value = (bool)( $_POST['value'] ?? false );

        switch ( $app ) {
            case 'reactions':
                \SaltHareket\Reactions\ReactionsAppSettings::save(['enable_reactions' => $value]);
                break;
            case 'reviews':
                \SaltHareket\Reviews\ReviewsSettings::saveGroup('general',
                    array_merge(\SaltHareket\Reviews\ReviewsSettings::all()['general'],['enable_reviews'=>$value]));
                break;
            case 'notifications':
                \SaltHareket\Notifications\NotificationsSettings::save(['enable_notifications' => $value]);
                break;
            case 'search_ranks':
                \SaltHareket\SearchHistory\SearchHistorySettings::save(['enable_search_history' => $value]);
                break;
            case 'sms':
                $s = \SaltHareket\Sms\SmsSettings::get();
                $s['enabled'] = $value;
                \SaltHareket\Sms\SmsSettings::save($s);
                break;
            case 'membership':
                \SaltHareket\Membership\MembershipSettings::save(['enable_membership' => $value]);
                break;
            case 'download_log':
                \SaltHareket\DownloadLog\DownloadLogSettings::save(['enable_download_log' => $value]);
                break;
            case 'maintenance':
                \SaltHareket\Maintenance\MaintenanceSettings::save(['active' => $value]);
                break;
            case 'localization':
                \SaltHareket\Localization\LocationSettings::save(['enable_localization' => $value]);
                break;
        }
        wp_send_json_success(['enabled' => $value]);
    }

    // ─── Purge AJAX ──────────────────────────────────────────────────────────

    public static function ajaxPurge(): void
    {
        if ( ! current_user_can('manage_options') ) wp_send_json_error('Unauthorized');
        check_ajax_referer('sh_dash_purge','nonce');

        // Sadece runtime/render cache'leri temizle: query + twig + opcache + wp_rocket.
        // CSS/JS asset cache'i (css/cache, js/cache, cache-manifest, _assets meta) BURADA
        // SILINMEZ — onlar Asset Manager'in kendi purge'une aittir (ayri mekanizma).
        if ( class_exists('\SaltHareket\AssetManager\AssetManager') ) {
            \SaltHareket\AssetManager\AssetManager::purgeAll();
        } else {
            // Fallback: AssetManager yoksa temel temizlik
            if ( function_exists('rocket_clean_domain') ) rocket_clean_domain();
            if ( function_exists('opcache_reset') )       opcache_reset();
        }

        wp_send_json_success(['message' => 'Cache temizlendi (twig, query, opcache, wp-rocket).']);
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public static function renderPage(): void
    {
        if ( ! current_user_can('manage_options') ) wp_die('Unauthorized');
        $nonce = wp_create_nonce('sh_dash_toggle');
        $apps  = self::getAppsData();
        ?>
        <div class="sh-wrap" id="sh-dashboard-page">
        <div class="sh-toolbar" style="position:static;top:auto">
            <h1 style="margin:0">🏠 Dashboard</h1>
            <span style="color:#6b7280;font-size:13px">SaltHareket Theme — App Yönetimi</span>
        </div>

        <div id="sh-dash-toast"></div>

        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-top:16px" id="sh-app-grid">
        <?php foreach ( $apps as $app ) : self::renderAppCard( $app, $nonce ); endforeach; ?>
        </div>
        </div>
        <?php
        self::renderStyles();
        self::renderScript();
    }

    private static function renderAppCard( array $app, string $nonce ): void
    {
        $has_toggle  = isset( $app['enabled'] );
        $enabled     = $has_toggle ? (bool) $app['enabled'] : null;
        $settings_url = esc_url( $app['settings_url'] ?? '#' );
        $footer_html  = $app['footer'] ?? '';
        ?>
        <div class="sh-card sh-dash-card" style="padding:0;margin:0;display:flex;flex-direction:column;min-height:200px">

            <!-- Loading overlay -->
            <div class="sh-card-overlay"><div class="sh-spinner"></div></div>

            <!-- Üst kısım: ikon + başlık/açıklama + sağda toggle/settings -->
            <div style="padding:16px;display:flex;gap:14px;align-items:flex-start;flex:1">
                <!-- İkon kutusu -->
                <div style="flex-shrink:0;width:72px;height:72px;background:#e9ebf0;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:32px">
                    <?php echo esc_html( $app['icon'] ); ?>
                </div>

                <!-- Orta: başlık + açıklama -->
                <div style="flex:1;min-width:0">
                    <div style="font-weight:700;font-size:14px;color:#1d2327;margin-bottom:4px">
                        <?php echo esc_html( $app['title'] ); ?>
                        <?php if ( ! empty($app['version']) ): ?>
                        <span style="font-size:10px;font-weight:400;color:#9ca3af;margin-left:4px">v<?php echo esc_html($app['version']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:12px;color:#6b7280;line-height:1.5"><?php echo esc_html( $app['description'] ); ?></div>
                </div>

                <!-- Sağ: toggle (varsa) + Settings butonu -->
                <div style="flex-shrink:0;display:flex;flex-direction:column;align-items:flex-end;gap:14px">
                    <?php if ( $has_toggle ): ?>
                    <div style="display:flex;align-items:center;gap:6px">
                        <span style="font-size:11px;color:<?php echo $enabled ? '#15803d' : '#9ca3af'; ?>;font-weight:600" id="sh-dash-lbl-<?php echo esc_attr($app['key']); ?>"><?php echo $enabled ? 'Enabled' : 'Disabled'; ?></span>
                        <label class="sh-toggle">
                            <input type="checkbox" class="sh-dash-toggle" data-app="<?php echo esc_attr($app['key']); ?>" data-nonce="<?php echo esc_attr($nonce); ?>" <?php checked($enabled); ?>>
                            <span class="sh-toggle-slider"></span>
                        </label>
                    </div>
                    <?php elseif ( !empty($app['badge']) ): ?>
                    <span style="font-size:11px;padding:2px 8px;border-radius:10px;background:<?php echo esc_attr($app['badge_bg'] ?? '#e9ebf0'); ?>;color:<?php echo esc_attr($app['badge_color'] ?? '#555'); ?>;font-weight:600"><?php echo esc_html($app['badge']); ?></span>
                    <?php endif; ?>

                    <?php if ( ! empty($app['settings_url']) ): ?>
                    <a href="<?php echo esc_url($app['settings_url']); ?>" class="button button-small" style="font-size:12px;padding:0 12px;border-radius:5px">Settings</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Footer -->
            <?php if ( $footer_html ): ?>
            <div style="padding:8px 16px;border-top:1px solid #f0f0f1;background:#fafafa;font-size:11px;color:#6b7280;border-radius:0 0 8px 8px;overflow:hidden">
                <?php echo $footer_html; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    // ─── App Data ─────────────────────────────────────────────────────────────

    private static function getAppsData(): array
    {
        global $wpdb;
        return [
            self::appThemeStyles(),
            self::appTemplates(),
            self::appGeneralSettings(),
            self::appReactions(),
            self::appReviews( $wpdb ),
            self::appNotifications(),
            self::appPluginManager(),
            self::appSearchRanks( $wpdb ),
            self::appSms(),
            self::appMembership( $wpdb ),
            self::appDownloadLog( $wpdb ),
            self::appVideoProcessor( $wpdb ),
            self::appAssetManager(),
            self::appMaintenance(),
            self::appLocalization(),
            self::appExport(),
        ];
    }

    private static function appGeneralSettings(): array
    {
        $menu_locations = function_exists('get_field') ? (get_field('menu_locations', 'options') ?: []) : [];
        $menu_populate  = function_exists('get_field') ? (get_field('menu_populate',  'options') ?: []) : [];
        return [
            'key'         => 'general_settings',
            'icon'        => '⚙️',
            'title'       => 'General Settings',
            'version'     => self::getAppVersion('\SaltHareket\GeneralSettings\Admin\GeneralSettingsAdmin'),
            'description' => 'Menü lokasyonları, dinamik menü doldurma ve tema geneli ayarlar.',
            'settings_url'=> admin_url('admin.php?page=sh-general-settings'),
            'footer'      => 'Lokasyon: <strong>' . ( count($menu_locations) + 2 ) . '</strong> &nbsp; Populate Kuralı: <strong>' . count($menu_populate) . '</strong>',
        ];
    }

    private static function appTemplates(): array
    {
        $terms = get_terms([
            'taxonomy'   => 'template-types',
            'hide_empty' => false,
        ]);

        $total = wp_count_posts('template');
        $total_count = isset($total->publish) ? (int)$total->publish : 0;

        // İlk term'in URL'ini default olarak al
        $first_url = admin_url('edit.php?post_type=template');
        if ( ! is_wp_error($terms) && ! empty($terms) ) {
            $first_url = admin_url('edit.php?post_type=template&template-types=' . $terms[0]->slug);
        }

        // Input group: select + Go butonu
        $select_id = 'sh-tmpl-select';
        $arrow = rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="10" height="6"><path d="M0 0l5 6 5-6z" fill="%23555"/></svg>');
        $select_style = 'height:26px;font-size:11px;border-radius:4px 0 0 4px;border:1px solid #ddd;border-right:0;padding:0 22px 0 8px;cursor:pointer;background:#fff url("data:image/svg+xml,' . $arrow . '") no-repeat right 6px center;background-size:10px 6px;-webkit-appearance:none;-moz-appearance:none;appearance:none;box-sizing:border-box;flex-shrink:0';

        $select  = '<select id="' . esc_attr($select_id) . '" style="' . $select_style . '">';
        if ( ! is_wp_error($terms) && ! empty($terms) ) {
            foreach ( $terms as $term ) {
                $url = esc_url( admin_url('edit.php?post_type=template&template-types=' . $term->slug) );
                $select .= sprintf('<option value="%s">%s (%d)</option>', $url, esc_html(ucfirst($term->name)), (int)$term->count);
            }
        }
        $select .= '</select>';
        $btn = '<button type="button" onclick="var s=document.getElementById(\'' . $select_id . '\');if(s&&s.value)window.location.href=s.value;" style="height:32px;padding:0 10px;font-size:11px;line-height:1;background:#2271b1;color:#fff;border:1px solid #2271b1;border-radius:0 4px 4px 0;cursor:pointer;white-space:nowrap;box-sizing:border-box;flex-shrink:0">Go →</button>';

        $footer = '<div style="display:flex;align-items:center;justify-content:space-between;width:100%">'
            . '<span style="white-space:nowrap">Toplam: <strong>' . $total_count . '</strong></span>'
            . '<div style="display:flex;align-items:center">' . $select . $btn . '</div>'
            . '</div>';

        return [
            'key'         => 'templates',
            'icon'        => '🎭',
            'title'       => 'Templates',
            'version'     => '1.0.0',
            'description' => 'Header, footer, modal, offcanvas ve özel şablonları yönet.',
            'footer'      => $footer,
        ];
    }

    private static function appThemeStyles(): array
    {
        $preset = get_option('theme_styles_active_preset','') ?: '—';
        return [
            'key'         => 'theme_styles',
            'icon'        => '🎨',
            'title'       => 'Theme Styles',
            'version'     => self::getAppVersion('Theme_Styles'),
            'description' => 'Renk paleti, tipografi ve global stil değişkenlerini yönet.',
            'settings_url'=> admin_url('admin.php?page=sh-theme-styles&lang=all'),
            'footer'      => 'Aktif preset: <strong>' . esc_html($preset) . '</strong>',
        ];
    }

    private static function appReactions(): array
    {
        $enabled = (bool)\SaltHareket\Reactions\ReactionsAppSettings::getSetting('enable_reactions');
        $types   = array_keys(\SaltHareket\Reactions\ReactionsSettings::getTypes());
        return [
            'key'         => 'reactions',
            'icon'        => '❤️',
            'title'       => 'Reactions',
            'version'     => self::getAppVersion('\SaltHareket\Reactions\Admin\ReactionsAdmin'),
            'description' => 'Like, favorite, follow ve özel reaction tiplerini yönet.',
            'enabled'     => $enabled,
            'settings_url'=> admin_url('admin.php?page=sh-reactions&tab=types'),
            'footer'      => 'Tipler: <strong>' . implode(', ', array_map('esc_html', $types)) . '</strong>',
        ];
    }

    private static function appReviews( $wpdb ): array
    {
        $enabled  = (bool)\SaltHareket\Reviews\ReviewsSettings::get('general.enable_reviews');
        $approved = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type='review' AND comment_approved='1'");
        $pending  = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type='review' AND comment_approved='0'");
        $spam     = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type='review' AND comment_approved='spam'");
        return [
            'key'         => 'reviews',
            'icon'        => '⭐',
            'title'       => 'Reviews',
            'version'     => self::getAppVersion('\SaltHareket\Reviews\Admin\ReviewsAdmin'),
            'description' => 'Kullanıcı yorumları ve puanlama sistemini yönet.',
            'enabled'     => $enabled,
            'settings_url'=> admin_url('admin.php?page=sh-reviews&tab=settings'),
            'footer'      => "Onaylı: <strong>$approved</strong> &nbsp; Bekleyen: <strong>$pending</strong> &nbsp; Spam: <strong>$spam</strong>",
        ];
    }

    private static function appNotifications(): array
    {
        $s        = \SaltHareket\Notifications\NotificationsSettings::get();
        $enabled  = $s['enable_notifications'];
        $email    = $s['enable_notifications'] ? '✓' : '✗';
        $sms      = $s['enable_sms_notifications'] ? '✓' : '✗';
        $push     = $s['enable_web_push'] ? '✓' : '✗';
        $total    = count(\SaltHareket\Notifications\NotifyRegistry::all());
        $custom   = count(json_decode(get_option('sh_notify_overrides','[]'),true) ?: []);
        return [
            'key'         => 'notifications',
            'icon'        => '🔔',
            'title'       => 'Notifications',
            'version'     => self::getAppVersion('\SaltHareket\Notifications\Admin\NotificationsAdmin'),
            'description' => 'Email, SMS ve web push bildirimlerini yönet.',
            'enabled'     => $enabled,
            'settings_url'=> admin_url('admin.php?page=sh-notifications&tab=overview'),
            'footer'      => "Event: <strong>$total</strong> &nbsp; Custom: <strong>$custom</strong> &nbsp; Email: $email &nbsp; SMS: $sms &nbsp; Push: $push",
        ];
    }

    private static function appPluginManager(): array
    {
        $all       = array_merge(\Data::get('plugins',[]),\Data::get('plugins_local',[]));
        $total     = count($all);
        $installed = 0; $active = 0;
        foreach ( $all as $p ) {
            $pi = WP_PLUGIN_DIR.'/'.$p['name'];
            if ( file_exists($pi) ) { $installed++; if ( is_plugin_active($p['name']) ) $active++; }
        }
        $missing = $total - $installed;
        return [
            'key'         => 'plugin_manager',
            'icon'        => '🔌',
            'title'       => 'Plugin Manager',
            'version'     => self::getAppVersion('\SaltHareket\PluginManager\Admin\PluginManagerAdmin'),
            'description' => 'WP repo ve local ZIP plugin kurulum, güncelleme, aktifleştirme.',
            'settings_url'=> admin_url('admin.php?page=sh-plugin-manager'),
            'footer'      => "Toplam: <strong>$total</strong> &nbsp; Yüklü: <strong>$installed</strong> &nbsp; Aktif: <strong>$active</strong> &nbsp; Eksik: <strong>$missing</strong>",
        ];
    }

    private static function appSearchRanks( $wpdb ): array
    {
        $enabled = (bool)\SaltHareket\SearchHistory\SearchHistorySettings::getSetting('enable_search_history');
        $table   = $wpdb->prefix.'search_terms';
        $total   = 0; $no_result = 0; $top = '—';
        if ( $wpdb->get_var("SHOW TABLES LIKE '$table'") ) {
            $total     = (int)$wpdb->get_var("SELECT COUNT(*) FROM `$table`");
            $no_result = (int)$wpdb->get_var("SELECT COUNT(*) FROM `$table` WHERE no_results=1");
            $top_row   = $wpdb->get_row("SELECT `name` FROM `$table` ORDER BY `rank` DESC LIMIT 1");
            $top = $top_row ? esc_html($top_row->name) : '---';
        }
        return [
            'key'         => 'search_ranks',
            'icon'        => '🔍',
            'title'       => 'Search Ranks',
            'version'     => self::getAppVersion('SearchHistory'),
            'description' => 'Arama geçmişi ve trend sorguları takip et.',
            'enabled'     => $enabled,
            'settings_url'=> admin_url('admin.php?page=sh-search-history'),
            'footer'      => "Toplam: <strong>$total</strong> &nbsp; Sonuçsuz: <strong>$no_result</strong> &nbsp; Top: <strong>$top</strong>",
        ];
    }

    private static function appSms(): array
    {
        $s        = \SaltHareket\Sms\SmsSettings::get();
        $enabled  = (bool)($s['enabled'] ?? false);
        $provider = esc_html(strtoupper($s['provider'] ?? 'd7'));
        $providers_active = array_filter(['d7','twilio','vonage','infobip','sinch','netgsm'], fn($p) => !empty($s[$p]['token'] ?? $s[$p]['api_key'] ?? $s[$p]['username'] ?? ''));
        $configured = implode(', ', array_map('strtoupper', array_values($providers_active))) ?: '—';
        return [
            'key'         => 'sms',
            'icon'        => '💬',
            'title'       => 'SMS',
            'version'     => self::getAppVersion('\SaltHareket\Sms\Admin\SmsAdmin'),
            'description' => 'SMS gönderimi ve OTP doğrulama sağlayıcılarını yönet.',
            'enabled'     => $enabled,
            'settings_url'=> admin_url('admin.php?page=sh-sms'),
            'footer'      => "Aktif: <strong>$provider</strong> &nbsp; Yapılandırılmış: $configured",
        ];
    }

    private static function appMembership( $wpdb ): array
    {
        $enabled  = (bool)\SaltHareket\Membership\MembershipSettings::getSetting('enable_membership');
        $members  = (int)count_users()['total_users'];
        $pending  = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key='user_status' AND meta_value='activated'");
        $g_table  = $wpdb->prefix.'guests';
        $guests   = $wpdb->get_var("SHOW TABLES LIKE '$g_table'") ? (int)$wpdb->get_var("SELECT COUNT(*) FROM `$g_table`") : 0;
        return [
            'key'         => 'membership',
            'icon'        => '👤',
            'title'       => 'Membership',
            'version'     => self::getAppVersion('\SaltHareket\Membership\Admin\MembershipAdmin'),
            'description' => 'Kayıt, aktivasyon, rol yönetimi ve üyelik özelliklerini yönet.',
            'enabled'     => $enabled,
            'settings_url'=> admin_url('admin.php?page=sh-membership&tab=settings'),
            'footer'      => "Üye: <strong>$members</strong> &nbsp; Bekleyen: <strong>$pending</strong> &nbsp; Guest: <strong>$guests</strong>",
        ];
    }

    private static function appDownloadLog( $wpdb ): array
    {
        $enabled  = (bool)\SaltHareket\DownloadLog\DownloadLogSettings::getSetting('enable_download_log');
        $table    = $wpdb->prefix.'download_log';
        $total    = 0; $modes = '';
        if ( $wpdb->get_var("SHOW TABLES LIKE '$table'") ) {
            $total  = (int)$wpdb->get_var("SELECT COUNT(*) FROM `$table`");
            $r_pub  = (int)$wpdb->get_var("SELECT COUNT(*) FROM `$table` WHERE mode='public'");
            $r_log  = (int)$wpdb->get_var("SELECT COUNT(*) FROM `$table` WHERE mode='login_required'");
            $r_lead = (int)$wpdb->get_var("SELECT COUNT(*) FROM `$table` WHERE mode='lead_capture'");
            $modes  = "Public: <strong>$r_pub</strong> &nbsp; Login: <strong>$r_log</strong> &nbsp; Lead: <strong>$r_lead</strong>";
        }
        return [
            'key'         => 'download_log',
            'icon'        => '⬇️',
            'title'       => 'Download Log',
            'version'     => self::getAppVersion('\SaltHareket\DownloadLog\Admin\DownloadLogAdmin'),
            'description' => 'Dosya indirme kayıtları ve erişim modlarını yönet.',
            'enabled'     => $enabled,
            'settings_url'=> admin_url('admin.php?page=sh-download-log&tab=settings'),
            'footer'      => "Toplam: <strong>$total</strong>" . ( $modes ? " &nbsp; $modes" : '' ),
        ];
    }

    private static function appVideoProcessor( $wpdb ): array
    {
        $ffmpeg_ok  = false;
        $ffmpeg_txt = 'FFmpeg Yüklü Değil';
        if ( class_exists('\SaltHareket\VideoProcessor\VideoProcessor') ) {
            try { $ffmpeg_ok = \SaltHareket\VideoProcessor\VideoProcessor::isAvailable(); } catch(\Throwable $e){}
        }
        if ( $ffmpeg_ok ) $ffmpeg_txt = 'FFmpeg Yüklü';

        $done = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='_vp_status' AND meta_value='done'");
        return [
            'key'         => 'video_processor',
            'icon'        => '🎬',
            'title'       => 'Video Processor',
            'version'     => self::getAppVersion('\SaltHareket\VideoProcessor\Admin\VideoProcessorAdmin'),
            'description' => 'FFmpeg tabanlı video optimizasyonu ve dönüşümünü yönet.',
            'badge'       => $ffmpeg_txt,
            'badge_bg'    => $ffmpeg_ok ? '#d1fae5' : '#fee2e2',
            'badge_color' => $ffmpeg_ok ? '#065f46' : '#991b1b',
            'settings_url'=> admin_url('admin.php?page=sh-video-processor&tab=settings'),
            'footer'      => "İşlenen video: <strong>$done</strong>",
        ];
    }

    private static function appAssetManager(): array
    {
        $is_dev = (bool)\SaltHareket\AssetManager\AssetSettings::getSetting('enable_development');
        $mode   = $is_dev ? 'Development' : 'Production';
        $bg     = $is_dev ? '#fef3c7' : '#d1fae5';
        $color  = $is_dev ? '#92400e' : '#065f46';
        $sep_css= (bool)\SaltHareket\AssetManager\AssetSettings::getSetting('seperate_css');
        $sep_js = (bool)\SaltHareket\AssetManager\AssetSettings::getSetting('seperate_js');
        $auto   = (bool)\SaltHareket\AssetManager\AssetSettings::getSetting('auto_fetch');
        $footer_left  = "Sep CSS: " . ($sep_css?'✓':'✗') . " &nbsp; Sep JS: " . ($sep_js?'✓':'✗') . " &nbsp; Auto Fetch: " . ($auto?'✓':'✗');
        $footer_right = '<button type="button" class="sh-dash-purge-btn" data-nonce="' . esc_attr( wp_create_nonce('sh_dash_purge') ) . '" style="background:none;border:none;padding:0;font-size:11px;color:#dc2626;cursor:pointer;font-weight:600;text-decoration:underline">Purge All Cache</button>';
        $footer = '<div style="display:flex;align-items:center;justify-content:space-between;width:100%"><span>' . $footer_left . '</span>' . $footer_right . '</div>';
        return [
            'key'         => 'asset_manager',
            'icon'        => '⚡',
            'title'       => 'Asset Manager',
            'version'     => self::getAppVersion('\SaltHareket\AssetManager\Admin\AssetManagerAdmin'),
            'description' => 'CSS/JS derleme, inline, lazy load ve cache yönetimi.',
            'badge'       => $mode,
            'badge_bg'    => $bg,
            'badge_color' => $color,
            'settings_url'=> admin_url('admin.php?page=sh-asset-manager&tab=settings'),
            'footer'      => $footer,
        ];
    }

    private static function appMaintenance(): array
    {
        $active  = \SaltHareket\Maintenance\MaintenanceSettings::isActive();
        $page_id = (int)\SaltHareket\Maintenance\MaintenanceSettings::getSetting('maintenance_page');
        $page_title = $page_id ? get_the_title($page_id) : '—';
        $status  = $active ? '<strong style="color:#b91c1c">🔴 Maintenance</strong>' : '<strong style="color:#15803d">🟢 Live</strong>';
        return [
            'key'         => 'maintenance',
            'icon'        => '🔧',
            'title'       => 'Maintenance',
            'version'     => self::getAppVersion('\SaltHareket\Maintenance\Admin\MaintenanceAdmin'),
            'description' => 'Site bakım modunu ve beyaz liste sayfalarını yönet.',
            'enabled'     => $active,
            'settings_url'=> admin_url('admin.php?page=sh-maintenance'),
            'footer'      => "Durum: $status &nbsp; Sayfa: <strong>" . esc_html($page_title) . "</strong>",
        ];
    }

    private static function appLocalization(): array
    {
        $enabled  = (bool)\SaltHareket\Localization\LocationSettings::getSetting('enable_localization');
        $regional = (bool)\SaltHareket\Localization\LocationSettings::getSetting('enable_regional_posts');
        $ip2c     = (bool)\SaltHareket\Localization\LocationSettings::getSetting('enable_ip2country');
        return [
            'key'         => 'localization',
            'icon'        => '🌍',
            'title'       => 'Localization',
            'version'     => self::getAppVersion('\SaltHareket\Localization\Admin\LocalizationAdmin'),
            'description' => 'Bölgesel içerik ve IP coğrafi konumlama ayarlarını yönet.',
            'enabled'     => $enabled,
            'settings_url'=> admin_url('admin.php?page=sh-localization&tab=settings'),
            'footer'      => "Regional Posts: " . ($regional?'✓':'✗') . " &nbsp; IP Geo: " . ($ip2c?'✓':'✗'),
        ];
    }

    private static function appExport(): array
    {
        $history  = class_exists('\SaltHareket\ThemeExport\ThemeExporter')
            ? \SaltHareket\ThemeExport\ThemeExporter::getHistory() : [];
        $last     = end($history) ?: null;
        $last_date= $last['date'] ?? '—';
        $last_mode= $last ? esc_html(ucfirst($last['mode'])) : '—';
        return [
            'key'         => 'export',
            'icon'        => '📦',
            'title'       => 'Export',
            'version'     => self::getAppVersion('\SaltHareket\ThemeExport\Admin\ThemeExportAdmin'),
            'description' => 'Tema ve veritabanı dışa aktarma işlemlerini yönet.',
            'settings_url'=> admin_url('admin.php?page=sh-theme-export'),
            'footer'      => "Son export: <strong>$last_date</strong> &nbsp; Mod: <strong>$last_mode</strong>",
        ];
    }

    // ─── Version Helper ───────────────────────────────────────────────────────

    private static function getAppVersion( string $class ): string {
        static $cache = [];
        if ( isset( $cache[ $class ] ) ) return $cache[ $class ];
        try {
            if ( ! class_exists( $class ) ) return $cache[ $class ] = '';
            $ref  = new \ReflectionClass( $class );
            $file = $ref->getFileName();
            if ( ! $file || ! file_exists( $file ) ) return $cache[ $class ] = '';
            $content = file_get_contents( $file );
            if ( preg_match( '/@version\s+([\d]+\.[\d]+\.[\d]+)/i', $content, $m ) ) {
                return $cache[ $class ] = $m[1];
            }
        } catch ( \Throwable $e ) {}
        return $cache[ $class ] = '';
    }

    // ─── Styles ───────────────────────────────────────────────────────────────

    private static function renderStyles(): void { ?>
        <style>
        #sh-app-grid { box-sizing:border-box; }
        .sh-dash-card { transition:box-shadow .15s; position:relative; overflow:hidden; }
        .sh-dash-card:hover { box-shadow:0 4px 20px rgba(0,0,0,.1); }

        /* Template select input-group */
        .sh-tmpl-group select {
            -webkit-appearance: auto;
            -moz-appearance: auto;
            appearance: auto;
            padding-right: 20px;
        }

        /* Card overlay */
        .sh-card-overlay {
            position:absolute; inset:0; z-index:10;
            background:rgba(255,255,255,.75);
            display:flex; align-items:center; justify-content:center;
            opacity:0; transition:opacity .15s ease;
            pointer-events:none;
        }
        .sh-card-overlay.active {
            opacity:1; pointer-events:all;
        }
        .sh-card-overlay.error-bg {
            background:rgba(254,226,226,.75);
        }

        /* Spinner */
        .sh-spinner {
            width:28px; height:28px;
            border:3px solid #e5e7eb;
            border-top-color:#2271b1;
            border-radius:50%;
            animation:sh-spin .6s linear infinite;
        }
        @keyframes sh-spin { to { transform:rotate(360deg); } }

        /* Toast */
        #sh-dash-toast {
            position:fixed; bottom:24px; right:24px; z-index:99999;
            min-width:220px; max-width:320px;
            padding:11px 16px; border-radius:8px;
            font-size:13px; font-weight:600;
            box-shadow:0 4px 20px rgba(0,0,0,.18);
            display:none; opacity:0;
            transition:opacity .2s ease;
        }
        #sh-dash-toast.show { display:block; opacity:1; }

        @media (max-width:1200px) { #sh-app-grid { grid-template-columns:repeat(3,1fr)!important; } }
        @media (max-width:900px)  { #sh-app-grid { grid-template-columns:repeat(2,1fr)!important; } }
        @media (max-width:600px)  { #sh-app-grid { grid-template-columns:1fr!important; } }
        </style>
    <?php }

    // ─── Script ───────────────────────────────────────────────────────────────

    private static function renderScript(): void { ?>
        <script>
        (function(){
            var toastTimer = null;

            function toast(msg, ok) {
                var t = document.getElementById('sh-dash-toast');
                if (!t) return;
                // Önceki timer varsa iptal et
                if (toastTimer) { clearTimeout(toastTimer); }
                t.style.background = ok ? '#15803d' : '#dc2626';
                t.style.color = '#fff';
                t.textContent = msg;
                // Önce display:block, sonra opacity 1 (fade-in)
                t.style.display = 'block';
                requestAnimationFrame(function() {
                    requestAnimationFrame(function() { t.classList.add('show'); });
                });
                toastTimer = setTimeout(function(){
                    t.classList.remove('show');
                    setTimeout(function(){ t.style.display = 'none'; }, 220);
                }, 2800);
            }

            function showOverlay(card, isError) {
                var overlay = card.querySelector('.sh-card-overlay');
                if (!overlay) return;
                overlay.classList.remove('error-bg');
                if (isError) overlay.classList.add('error-bg');
                // Spinner reset
                var spinner = overlay.querySelector('.sh-spinner');
                if (spinner) spinner.style.display = isError ? 'none' : 'block';
                overlay.classList.add('active');
            }

            function hideOverlay(card) {
                var overlay = card.querySelector('.sh-card-overlay');
                if (!overlay) return;
                overlay.classList.remove('active');
                setTimeout(function(){
                    overlay.classList.remove('error-bg');
                    var spinner = overlay.querySelector('.sh-spinner');
                    if (spinner) spinner.style.display = 'block';
                }, 200);
            }

            document.querySelectorAll('.sh-dash-toggle').forEach(function(cb){
                cb.addEventListener('change', function(){
                    var app   = this.dataset.app;
                    var nonce = this.dataset.nonce;
                    var val   = this.checked ? 1 : 0;
                    var lbl   = document.getElementById('sh-dash-lbl-' + app);
                    var orig  = this.checked;
                    var card  = this.closest('.sh-dash-card');

                    this.disabled = true;
                    if (card) showOverlay(card, false);

                    var fd = new FormData();
                    fd.append('action','sh_dash_toggle');
                    fd.append('nonce', nonce);
                    fd.append('app',   app);
                    fd.append('value', val);

                    fetch(ajaxurl, { method:'POST', body:fd })
                        .then(function(r){ return r.json(); })
                        .then(function(res){
                            if (res.success) {
                                if (lbl) {
                                    lbl.textContent = res.data.enabled ? 'Enabled' : 'Disabled';
                                    lbl.style.color = res.data.enabled ? '#15803d' : '#9ca3af';
                                }
                                if (card) hideOverlay(card);
                                toast(res.data.enabled ? '✅ Aktifleştirildi' : '⏹ Deaktif edildi', true);
                            } else {
                                if (card) { showOverlay(card, true); setTimeout(function(){ hideOverlay(card); }, 800); }
                                toast('❌ Hata oluştu', false);
                                cb.checked = !orig;
                            }
                        })
                        .catch(function(){
                            if (card) { showOverlay(card, true); setTimeout(function(){ hideOverlay(card); }, 800); }
                            toast('❌ AJAX hatası', false);
                            cb.checked = !orig;
                        })
                        .finally(function(){ cb.disabled = false; });
                });
            });

            // ── Purge All Cache ────────────────────────────────────────
            document.querySelectorAll('.sh-dash-purge-btn').forEach(function(btn){
                btn.addEventListener('click', function(){
                    var nonce = this.dataset.nonce;
                    var self  = this;
                    var orig  = this.textContent;

                    self.disabled = true;
                    self.textContent = 'Purging...';

                    var fd = new FormData();
                    fd.append('action','sh_dash_purge');
                    fd.append('nonce', nonce);

                    fetch(ajaxurl, { method:'POST', body:fd })
                        .then(function(r){ return r.json(); })
                        .then(function(res){
                            if (res.success) {
                                toast('✅ ' + (res.data && res.data.message ? res.data.message : 'Cache temizlendi'), true);
                            } else {
                                toast('❌ ' + (res.data && res.data.message ? res.data.message : 'Hata oluştu'), false);
                            }
                        })
                        .catch(function(){ toast('❌ AJAX hatası', false); })
                        .finally(function(){
                            self.disabled = false;
                            self.textContent = orig;
                        });
                });
            });
        })();
        </script>
    <?php }
}
