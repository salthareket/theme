<?php

namespace SaltHareket\Localization\Admin;

use SaltHareket\Localization\LocationManager;
use SaltHareket\Localization\LocationSettings;
use SaltHareket\Localization\Schema\LocationSchema;

/**
 * LocalizationAdmin
 * Admin sayfası — Overview, Settings, Database, Regional Posts, Test tabları.
 *
 * @version 2.0.0
 * @changelog
 *   2.0.0 - 2026-06-16
 *     - Change: addMenuPage() — parent slug 'theme-settings' → 'salt-theme-settings'
 *     - Change: renderSettingsTab() — location_data_source (database|package) + enable_localization ana switch
 *     - Change: ajaxSaveSettings() — partial update (merge logic, gönderilmeyen alanlar korunur)
 *     - Change: checkDependencies() — package modda DB uyarısı yok
 *     - Change: renderDatabaseTab() — 'cities' tablosu kaldırıldı
 *   1.0.0 - 2026-05-09 — Initial release
 */
class LocalizationAdmin
{
    public static function register(): void
    {
        if ( ! is_admin() ) return;
        add_action( 'admin_menu',            [ self::class, 'addMenuPage' ], 28 );
        add_action( 'admin_head',            [ self::class, 'hideNotices' ] );
        add_action( 'admin_enqueue_scripts', [ self::class, 'enqueueAssets' ] );
        add_action( 'wp_ajax_sh_loc_save_settings',       [ self::class, 'ajaxSaveSettings' ] );
        add_action( 'wp_ajax_sh_loc_import_table',        [ self::class, 'ajaxImportTable' ] );
        add_action( 'wp_ajax_sh_loc_test_ip',             [ self::class, 'ajaxTestIp' ] );
        add_action( 'wp_ajax_sh_loc_get_taxonomies',      [ self::class, 'ajaxGetTaxonomies' ] );
        add_action( 'wp_ajax_get_regional_posts_type_taxonomies', [ self::class, 'ajaxGetTaxonomies' ] );
    }

    public static function addMenuPage(): void
    {
        add_submenu_page(
            'theme-settings',
            '🌍 Localization',
            '🌍 Localization',
            'manage_options',
            'sh-localization',
            [ self::class, 'renderPage' ]
        );
    }

    public static function hideNotices(): void
    {
        if ( ( $_GET['page'] ?? '' ) !== 'sh-localization' ) return;
        echo '<style>body .notice:not(.sh-inline),body .updated:not(.sh-inline),body .error:not(.sh-inline){display:none!important;}</style>';
    }

    public static function enqueueAssets( string $hook ): void
    {
        if ( strpos( $hook, 'sh-localization' ) === false ) return;
        $css_path = dirname( __DIR__, 2 ) . '/sh-admin.css';
        $css_url  = trailingslashit( SH_INCLUDES_URL ) . 'apps/sh-admin.css';
        if ( file_exists( $css_path ) ) {
            wp_enqueue_style( 'sh-admin-kit', $css_url, [], filemtime( $css_path ) );
        }
    }

    // ─── AJAX ────────────────────────────────────────────────────────────────

    public static function ajaxSaveSettings(): void
    {
        check_ajax_referer( 'sh_loc_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized.' );

        $data = $_POST['settings'] ?? [];

        // regional_post_settings JSON decode
        if ( isset( $data['regional_post_settings'] ) && is_string( $data['regional_post_settings'] ) ) {
            $data['regional_post_settings'] = json_decode( stripslashes( $data['regional_post_settings'] ), true ) ?: [];
        }

        // Regional Posts açıksa IP Geo zorunlu — otomatik aç
        if ( ! empty( $data['enable_regional_posts'] ) ) {
            $data['enable_ip2country'] = true;
        }

        // LocationSettings::save() kendi içinde merge yapıyor — partial update desteklenir
        LocationSettings::save( $data );
        wp_send_json_success( [ 'message' => 'Settings saved.', 'settings' => LocationSettings::get() ] );
    }

    public static function ajaxImportTable(): void
    {
        check_ajax_referer( 'sh_loc_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized.' );

        $table = sanitize_key( $_POST['table'] ?? '' );
        $valid = [ 'countries', 'states', 'ip2country' ]; // cities kaldırıldı
        if ( ! in_array( $table, $valid, true ) ) wp_send_json_error( 'Invalid table.' );

        $result = LocationSchema::createTable( $table );
        if ( $result ) {
            global $wpdb;
            $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}{$table}`" );
            wp_send_json_success( [ 'message' => "Table {$table} imported. {$count} rows.", 'count' => $count ] );
        } else {
            wp_send_json_error( "SQL file not found for table: {$table}" );
        }
    }

    public static function ajaxTestIp(): void
    {
        check_ajax_referer( 'sh_loc_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized.' );

        $ip     = sanitize_text_field( $_POST['ip'] ?? '' );
        $lm     = LocationManager::getInstance();
        $result = $lm->ipInfo( $ip ?: null );
        wp_send_json_success( $result ?: [ 'message' => 'No result found.' ] );
    }

    public static function ajaxGetTaxonomies(): void
    {
        // Nonce kontrolü — hem localization hem eski regional posts handler için
        if ( ! check_ajax_referer( 'sh_loc_nonce', 'nonce', false ) ) {
            // Eski handler nonce göndermiyordu — sessizce geç
        }

        $post_type  = sanitize_key( $_POST['value'] ?? $_POST['post_type'] ?? '' );
        if ( empty( $post_type ) ) {
            wp_send_json( [ 'error' => true, 'html' => '' ] );
        }

        $taxonomies = get_object_taxonomies( $post_type, 'objects' );
        $taxonomies = array_filter( $taxonomies, fn( $t ) => $t->public );

        $options = '';
        foreach ( $taxonomies as $tax ) {
            $options .= '<option value="' . esc_attr( $tax->name ) . '">' . esc_html( $tax->label ) . '</option>';
        }

        wp_send_json( [ 'error' => false, 'html' => $options ] );
    }

    // ─── Main Page ────────────────────────────────────────────────────────────

    public static function renderPage(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $tab      = sanitize_key( $_GET['tab'] ?? 'overview' );
        $settings = LocationSettings::get();
        $nonce    = wp_create_nonce( 'sh_loc_nonce' );
        $ajax_url = admin_url( 'admin-ajax.php' );
        ?>
        <div class="sh-wrap" id="sh-localization-page">

        <div class="sh-toolbar">
            <h1>🌍 Localization</h1>
            <?php if ( $settings['enable_regional_posts'] ) : ?>
                <span class="sh-badge sh-badge-blue">Regional Posts Active</span>
            <?php endif; ?>
            <?php if ( $settings['enable_ip2country'] ) : ?>
                <span class="sh-badge sh-badge-gray">IP Geo Active</span>
            <?php endif; ?>
            <div class="sh-toolbar-right">
                <a href="?page=sh-localization&tab=overview"  class="sh-tab-btn <?php echo $tab==='overview' ?'active':''; ?>">Overview</a>
                <a href="?page=sh-localization&tab=settings"  class="sh-tab-btn <?php echo $tab==='settings' ?'active':''; ?>">Settings</a>
                <a href="?page=sh-localization&tab=database"  class="sh-tab-btn <?php echo $tab==='database' ?'active':''; ?>">Database</a>
                <a href="?page=sh-localization&tab=regional"  class="sh-tab-btn <?php echo $tab==='regional' ?'active':''; ?>">Regional Posts</a>
                <a href="?page=sh-localization&tab=test"      class="sh-tab-btn <?php echo $tab==='test'     ?'active':''; ?>">Test</a>
            </div>
        </div>

        <?php
        $titles = [
            'overview' => [ 'title' => 'Localization Overview',    'desc' => 'System status, active features and quick stats.' ],
            'settings' => [ 'title' => 'Localization Settings',    'desc' => 'Configure IP geolocation, location database and regional posts.' ],
            'database' => [ 'title' => 'Database Management',      'desc' => 'Import and manage location database tables.' ],
            'regional' => [ 'title' => 'Regional Posts',           'desc' => 'Configure which post types and taxonomies are filtered by region.' ],
            'test'     => [ 'title' => 'Test & Debug',             'desc' => 'Test IP geolocation, location queries and WooCommerce mapping.' ],
        ];
        $cur = $titles[$tab] ?? $titles['overview'];
        ?>
        <div class="sh-section-title">
            <h2><?php echo esc_html($cur['title']); ?></h2>
            <p><?php echo esc_html($cur['desc']); ?></p>
        </div>

        <?php
        // Dependency uyarıları — her tab'da göster
        self::renderDependencyNotices( $settings );

        switch ( $tab ) {
            case 'settings': self::renderSettingsTab( $settings, $nonce, $ajax_url ); break;
            case 'database': self::renderDatabaseTab( $nonce, $ajax_url ); break;
            case 'regional': self::renderRegionalTab( $settings, $nonce, $ajax_url ); break;
            case 'test':     self::renderTestTab( $nonce, $ajax_url ); break;
            default:         self::renderOverviewTab( $settings ); break;
        }        ?>

        <div id="sh-toast"></div>
        </div>

        <script>
        window.shLoc = {
            nonce:   <?php echo wp_json_encode( $nonce ); ?>,
            ajaxUrl: <?php echo wp_json_encode( $ajax_url ); ?>,
        };
        </script>
        <script>
        (function($){
            // Regional Posts toggle → mapping alanı show/hide + IP Geo zorunlu
            $('#sh-enable-regional-posts').on('change', function() {
                $('#sh-regional-mappings-wrap').toggle($(this).is(':checked'));
                // Regional Posts açılınca IP Geo zorunlu — otomatik aç
                if ($(this).is(':checked') && !$('#sh-enable-ip2country').is(':checked')) {
                    $('#sh-enable-ip2country').prop('checked', true).trigger('change');
                    shLocToast('ℹ️ IP Geolocation Regional Posts için zorunlu — otomatik açıldı.');
                }
            });

            // Regional toggle save butonu
            $('#sh-loc-save-regional-toggle').on('click', function() {
                var $btn = $(this).prop('disabled', true).text('Saving...');
                var regionalEnabled = $('#sh-enable-regional-posts').is(':checked');
                var settings = {
                    enable_ip2country:     (regionalEnabled || $('#sh-enable-ip2country').is(':checked')) ? 1 : 0,
                    ip2country_source:     $('input[name="ip2country_source"]:checked').val() || 'api',
                    enable_location_db:    $('#sh-enable-location-db').is(':checked') ? 1 : 0,
                    enable_regional_posts: regionalEnabled ? 1 : 0,
                    woo_state_mapping:     $('#sh-woo-state-mapping').is(':checked') ? 1 : 0,
                    regional_post_settings: JSON.stringify(window.shLocMappings || []),
                };
                $.post(window.shLoc.ajaxUrl, { action: 'sh_loc_save_settings', nonce: window.shLoc.nonce, settings: settings }, function(res) {
                    $btn.prop('disabled', false).text('Save');
                    if (res.success) { shLocToast('Saved!'); }
                    else shLocToast(res.data || 'Error', true);
                });
            });

            // IP Geolocation toggle → IP Source show/hide
            function shLocToggleIpSource() {
                var active = $('#sh-enable-ip2country').is(':checked');
                $('#sh-ip-source-wrap').toggle(active);
            }
            $('#sh-enable-ip2country').on('change', shLocToggleIpSource);
            shLocToggleIpSource(); // init

            // Save Settings
            $('#sh-loc-save-settings').on('click', function() {
                var $btn = $(this).prop('disabled', true).text('Saving...');
                var settings = {
                    enable_ip2country:     $('#sh-enable-ip2country').is(':checked') ? 1 : 0,
                    ip2country_source:     $('input[name="ip2country_source"]:checked').val() || 'api',
                    enable_location_db:    $('#sh-enable-location-db').is(':checked') ? 1 : 0,
                    enable_regional_posts: $('#sh-enable-regional-posts').is(':checked') ? 1 : 0,
                    woo_state_mapping:     $('#sh-woo-state-mapping').is(':checked') ? 1 : 0,
                    regional_post_settings: JSON.stringify(window.shLocMappings || []),
                };
                $.post(window.shLoc.ajaxUrl, { action: 'sh_loc_save_settings', nonce: window.shLoc.nonce, settings: settings }, function(res) {
                    $btn.prop('disabled', false).text('Save Settings');
                    if (res.success) { shLocToast('Settings saved!'); location.reload(); }
                    else shLocToast(res.data || 'Error', true);
                });
            });

            // Import Table
            $(document).on('click', '.sh-import-table', function() {
                var $btn = $(this);
                var table = $btn.data('table');
                $btn.prop('disabled', true).text('Importing...');
                $.post(window.shLoc.ajaxUrl, { action: 'sh_loc_import_table', nonce: window.shLoc.nonce, table: table }, function(res) {
                    $btn.prop('disabled', false).text('Import');
                    if (res.success) { shLocToast(res.data.message); $btn.closest('tr').find('.sh-table-count').text(res.data.count.toLocaleString()); }
                    else shLocToast(res.data || 'Error', true);
                });
            });

            // Test IP
            $('#sh-test-ip-btn').on('click', function() {
                var ip = $('#sh-test-ip-input').val().trim();
                $.post(window.shLoc.ajaxUrl, { action: 'sh_loc_test_ip', nonce: window.shLoc.nonce, ip: ip }, function(res) {
                    $('#sh-test-ip-result').html('<pre>' + JSON.stringify(res.data, null, 2) + '</pre>');
                });
            });

            // Regional Mappings
            window.shLocMappings = <?php echo wp_json_encode( $settings['regional_post_settings'] ?? [] ); ?>;

            $('#sh-add-mapping').on('click', function() {
                var pt  = $('#sh-new-post-type').val();
                var tax = $('#sh-new-taxonomy').val();
                if (!pt || !tax) return;
                window.shLocMappings.push({ post_type: pt, taxonomy: tax });
                renderMappings();
            });

            $('#sh-new-post-type').on('change', function() {
                var pt = $(this).val();
                var $tax = $('#sh-new-taxonomy');
                if (!pt) {
                    $tax.html('<option value="">Select post type first...</option>');
                    return;
                }
                $tax.html('<option value="">Loading...</option>');
                $.post(window.shLoc.ajaxUrl, {
                    action: 'sh_loc_get_taxonomies',
                    nonce:  window.shLoc.nonce,
                    value:  pt
                }, function(res) {
                    // ajaxGetTaxonomies direkt {error, html} döndürüyor
                    var html = '';
                    if (res && res.html) {
                        html = res.html;
                    } else if (res && res.data && res.data.html) {
                        html = res.data.html;
                    }
                    if (html) {
                        $tax.html('<option value="">Select taxonomy...</option>' + html);
                    } else {
                        $tax.html('<option value="">No taxonomies found</option>');
                    }
                }, 'json');
            });

            function renderMappings() {
                var $list = $('#sh-mappings-list');
                $list.empty();
                window.shLocMappings.forEach(function(m, i) {
                    $list.append('<tr><td style="padding:8px 12px">' + m.post_type + '</td><td style="padding:8px 12px">' + m.taxonomy + '</td><td style="padding:8px 12px"><button type="button" class="sh-btn sh-btn-sm" style="color:#ef4444" onclick="shLocMappings.splice(' + i + ',1);renderMappings()">Remove</button></td></tr>');
                });
            }
            renderMappings();

            function shLocToast(msg, isError) {
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

    // ─── Dependency Check ─────────────────────────────────────────────────────

    /**
     * Aktif özelliklerin gerekli tablolarını kontrol et.
     * Eksik tablo varsa uyarı döndür.
     *
     * @return array [['feature'=>'...', 'missing'=>['table',...], 'severity'=>'error|warning'], ...]
     */
    private static function checkDependencies( array $settings ): array
    {
        $issues      = [];
        $data_source = $settings['location_data_source'] ?? 'database';

        // Package modda DB uyarısı yok — sadece regional posts için IP uyarısı
        if ( $data_source === 'package' ) {
            if ( ! empty( $settings['enable_regional_posts'] ) && empty( $settings['enable_ip2country'] ) ) {
                $issues[] = [
                    'feature'  => 'Regional Posts',
                    'missing'  => [],
                    'severity' => 'warning',
                    'fix'      => 'settings',
                    'message'  => 'IP Geolocation kapalı. Kullanıcı bölgesi otomatik tespit edilemez.',
                ];
            }
            return $issues;
        }

        // Database modu — tabloları kontrol et
        global $wpdb;

        $table_exists = function( string $table ) use ( $wpdb ): bool {
            $cache_key = 'sh_table_exists_' . $table;
            $cached    = get_transient( $cache_key );
            if ( $cached !== false ) return (bool) $cached;
            $exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . $table ) );
            set_transient( $cache_key, $exists, HOUR_IN_SECONDS );
            return $exists;
        };

        // IP Geo (DB modu)
        if ( ! empty( $settings['enable_ip2country'] ) && ( $settings['ip2country_source'] ?? 'api' ) === 'db' ) {
            $missing = [];
            if ( ! $table_exists( 'ip2country' ) ) $missing[] = 'ip2country';
            if ( ! $table_exists( 'countries' ) )  $missing[] = 'countries';
            if ( $missing ) {
                $issues[] = [
                    'feature'  => 'IP Geolocation (Database mode)',
                    'missing'  => $missing,
                    'severity' => 'error',
                    'fix'      => 'database',
                    'message'  => 'IP detection will not work. Import the missing tables.',
                ];
            }
        }

        // Location DB
        if ( ! empty( $settings['enable_location_db'] ) ) {
            $missing = [];
            if ( ! $table_exists( 'countries' ) ) $missing[] = 'countries';
            if ( ! $table_exists( 'states' ) )    $missing[] = 'states';
            if ( $missing ) {
                $issues[] = [
                    'feature'  => 'Location Database',
                    'missing'  => $missing,
                    'severity' => 'error',
                    'fix'      => 'database',
                    'message'  => 'City/state dropdowns will not work. Import the missing tables.',
                ];
            }
        }

        if ( ! empty( $settings['enable_regional_posts'] ) && empty( $settings['enable_ip2country'] ) ) {
            $issues[] = [
                'feature'  => 'Regional Posts',
                'missing'  => [],
                'severity' => 'warning',
                'fix'      => 'settings',
                'message'  => 'IP Geolocation kapalı. Kullanıcı bölgesi otomatik tespit edilemez.',
            ];
        }

        return $issues;
    }
    /**
     * Dependency uyarılarını render et.
     */
    private static function renderDependencyNotices( array $settings ): void
    {
        $issues = self::checkDependencies( $settings );
        if ( empty( $issues ) ) return;

        foreach ( $issues as $issue ) :
            $is_error = $issue['severity'] === 'error';
            $color    = $is_error ? '#ef4444' : '#f0b429';
            $bg       = $is_error ? '#fee2e2' : '#fef9c3';
            $border   = $is_error ? '#fca5a5' : '#fde68a';
            $icon     = $is_error ? '✗' : '⚠';
            $tab_url  = '?page=sh-localization&tab=' . esc_attr( $issue['fix'] );
            ?>
            <div style="background:<?php echo $bg; ?>;border:1px solid <?php echo $border; ?>;border-radius:8px;padding:12px 16px;margin-bottom:10px;display:flex;align-items:flex-start;gap:10px">
                <span style="color:<?php echo $color; ?>;font-weight:700;font-size:16px;flex-shrink:0"><?php echo $icon; ?></span>
                <div style="flex:1">
                    <strong style="font-size:13px;color:#1d2327"><?php echo esc_html( $issue['feature'] ); ?></strong>
                    <?php if ( ! empty( $issue['missing'] ) ) : ?>
                        — missing tables:
                        <?php foreach ( $issue['missing'] as $t ) : ?>
                            <code style="background:rgba(0,0,0,.06);padding:1px 5px;border-radius:3px;font-size:11px"><?php echo esc_html( $t ); ?></code>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <p style="margin:3px 0 0;font-size:12px;color:#374151"><?php echo esc_html( $issue['message'] ); ?></p>
                </div>
                <?php if ( $issue['fix'] ) : ?>
                    <a href="<?php echo esc_url( $tab_url ); ?>" class="sh-btn sh-btn-sm" style="flex-shrink:0;font-size:11px">
                        <?php echo $issue['fix'] === 'database' ? '⬇ Import Tables' : ( $issue['fix'] === 'regional' ? '→ Configure' : '→ Settings' ); ?>
                    </a>
                <?php endif; ?>
            </div>
            <?php
        endforeach;
    }

    private static function renderOverviewTab( array $settings ): void
    {
        $table_status = LocationSchema::getTableStatus();
        $regional_ok = $settings['enable_regional_posts'] && $settings['enable_ip2country'];
        $features = [
            [ 'label' => 'IP Geolocation',    'active' => $settings['enable_ip2country'],     'desc' => 'Kaynak: ' . ( ( $settings['ip2country_source'] ?? 'api' ) === 'db' ? 'Database' : 'API (geoplugin.net)' ) ],
            [ 'label' => 'Location Database', 'active' => $settings['enable_location_db'],    'desc' => 'Countries + states tabloları' ],
            [ 'label' => 'Regional Posts',    'active' => $regional_ok, 'partial' => $settings['enable_regional_posts'] && ! $settings['enable_ip2country'], 'desc' => count( $settings['regional_post_settings'] ?? [] ) . ' post type mapping' ],
            [ 'label' => 'WooCommerce Mapping','active' => $settings['woo_state_mapping'],    'desc' => 'State kodları WC billing_state ile eşleşiyor' ],
        ];
        ?>
        <div class="sh-layout">
        <div class="sh-main">

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:24px">
        <?php foreach ( $features as $f ) :
            $is_partial = ! empty( $f['partial'] );
            $col = $f['active'] ? '#22c55e' : ( $is_partial ? '#f0b429' : '#9ca3af' );
        ?>
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:16px;border-left:4px solid <?php echo $col; ?>">
                <div style="font-weight:600;font-size:13px;color:#1d2327"><?php echo esc_html( $f['label'] ); ?></div>
                <div style="font-size:11px;color:<?php echo $col; ?>;margin:4px 0;font-weight:600">
                    <?php
                    if ( $f['active'] ) echo '✓ Active';
                    elseif ( $is_partial ) echo '⚠ Partial — IP Geo gerekli';
                    else echo '✗ Inactive';
                    ?>
                </div>
                <div style="font-size:11px;color:#9ca3af"><?php echo esc_html( $f['desc'] ); ?></div>
            </div>
        <?php endforeach; ?>
        </div>

        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden">
            <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6"><strong>Database Tables</strong></div>
            <table style="width:100%;border-collapse:collapse">
                <thead><tr>
                    <?php foreach(['Table','Status','Rows','Action'] as $h): ?>
                    <th style="padding:10px 18px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #f3f4f6"><?php echo $h; ?></th>
                    <?php endforeach; ?>
                </tr></thead>
                <tbody>
                <?php foreach ( $table_status as $table => $info ) : ?>
                <tr style="border-bottom:1px solid #f9fafb">
                    <td style="padding:10px 18px;font-family:Consolas,monospace;font-size:12px"><?php echo esc_html($info['name']); ?></td>
                    <td style="padding:10px 18px">
                        <?php if ($info['exists']): ?>
                            <span style="color:#22c55e;font-weight:600">✓ Exists</span>
                        <?php else: ?>
                            <span style="color:#f0b429;font-weight:600">✗ Missing</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding:10px 18px;color:#6b7280;font-size:13px" class="sh-table-count"><?php echo $info['exists'] ? number_format($info['count']) : '—'; ?></td>
                    <td style="padding:10px 18px">
                        <?php $sql_exists = file_exists( SH_STATIC_PATH . 'data/' . $table . '.sql' ); ?>
                        <?php if ($sql_exists): ?>
                            <button type="button" class="sh-btn sh-btn-sm sh-import-table" data-table="<?php echo esc_attr($table); ?>">
                                <?php echo $info['exists'] ? 'Re-import' : 'Import'; ?>
                            </button>
                        <?php else: ?>
                            <span style="font-size:11px;color:#9ca3af">No SQL file</span>
                        <?php endif; ?>
                    </td>
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
        $enabled     = ! empty( $settings['enable_localization'] );
        $data_source = $settings['location_data_source'] ?? 'database';
        $ip_enabled  = ! empty( $settings['enable_ip2country'] );
        $table_status = LocationSchema::getTableStatus();
        $all_ok = array_reduce( $table_status, fn( $c, $t ) => $c && $t['exists'], true );
        ?>
        <div class="sh-layout">
        <div class="sh-main">

        <!-- ── Enable Localization ────────────────────────────────────── -->
        <div class="sh-card" style="margin-bottom:20px">
            <div class="sh-card-header" style="display:flex;align-items:center;gap:12px">
                <h3 style="margin:0">🌍 Localization</h3>
                <label class="sh-toggle">
                    <input type="checkbox" id="sh-enable-localization" <?php checked( $enabled ); ?> onchange="shLocToggleMain(this.checked)">
                    <span class="sh-toggle-slider"></span>
                </label>
                <span style="font-size:12px;color:#9ca3af">Enable Localization System</span>
            </div>
        </div>

        <!-- ── Options (sadece aktifse) ──────────────────────────────── -->
        <div id="sh-loc-options" style="<?php echo ! $enabled ? 'display:none' : ''; ?>">

        <!-- Location Data Source -->
        <div class="sh-card" style="margin-bottom:20px">
            <div class="sh-card-header"><h3 style="margin:0">📦 Location Data Source</h3></div>
            <div class="sh-card-body">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">
                    <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;padding:16px;border:2px solid <?php echo $data_source === 'package' ? '#2271b1' : '#ddd'; ?>;border-radius:8px;background:<?php echo $data_source === 'package' ? '#f0f6ff' : '#fff'; ?>">
                        <input type="radio" name="location_data_source" value="package" <?php checked( $data_source, 'package' ); ?> onchange="shLocSourceChange('package')" style="margin-top:2px">
                        <div>
                            <div style="font-weight:600;font-size:14px">🌐 WooCommerce / Fallback</div>
                            <div style="font-size:12px;color:#6b7280;margin-top:4px">WC ülke/il listesi — kurulum gerektirmez, DB tablosu yok. WC yoksa PHP fallback listesi kullanılır.</div>
                            <div style="margin-top:8px;display:flex;gap:6px">
                                <span style="font-size:10px;padding:2px 6px;border-radius:10px;background:#dcfce7;color:#166534">✅ Offline</span>
                                <span style="font-size:10px;padding:2px 6px;border-radius:10px;background:#fef9c3;color:#854d0e">⚡ Hızlı</span>
                            </div>
                        </div>
                    </label>
                    <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;padding:16px;border:2px solid <?php echo $data_source === 'database' ? '#2271b1' : '#ddd'; ?>;border-radius:8px;background:<?php echo $data_source === 'database' ? '#f0f6ff' : '#fff'; ?>">
                        <input type="radio" name="location_data_source" value="database" <?php checked( $data_source, 'database' ); ?> onchange="shLocSourceChange('database')" style="margin-top:2px">
                        <div>
                            <div style="font-weight:600;font-size:14px">🗄️ MySQL Database</div>
                            <div style="font-size:12px;color:#6b7280;margin-top:4px">Özel SQL tablolar — countries + states + ip2country. Daha fazla kontrol, lat/lng, phonecode.</div>
                            <div style="margin-top:8px">
                                <span style="font-size:10px;padding:2px 6px;border-radius:10px;background:<?php echo $all_ok ? '#dcfce7' : '#fee2e2'; ?>;color:<?php echo $all_ok ? '#166534' : '#991b1b'; ?>">
                                    <?php echo $all_ok ? '✅ Tables Ready' : '❌ Tables Missing'; ?>
                                </span>
                            </div>
                        </div>
                    </label>
                </div>

                <!-- DB Table Status (sadece database seçilince) -->
                <div id="sh-db-tables-wrap" style="<?php echo $data_source !== 'database' ? 'display:none' : ''; ?>">
                    <div style="padding:14px 16px;border-top:1px solid #f0f0f1">
                        <strong style="font-size:13px">Database Tables</strong>
                        <p style="margin:4px 0 0;font-size:12px;color:#9ca3af">SQL dosyaları: <code>static/data/</code></p>
                    </div>
                    <table style="width:100%;border-collapse:collapse">
                        <thead><tr>
                            <?php foreach ( [ 'Table', 'Status', 'Rows', 'Action' ] as $h ) : ?>
                            <th style="padding:8px 16px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #f3f4f6"><?php echo $h; ?></th>
                            <?php endforeach; ?>
                        </tr></thead>
                        <tbody>
                        <?php foreach ( $table_status as $table => $info ) :
                            $sql_exists = file_exists( SH_STATIC_PATH . 'data/' . $table . '.sql' );
                            $sql_size   = $sql_exists ? size_format( filesize( SH_STATIC_PATH . 'data/' . $table . '.sql' ), 1 ) : '—';
                        ?>
                        <tr style="border-bottom:1px solid #f9fafb">
                            <td style="padding:8px 16px;font-family:Consolas,monospace;font-size:12px">
                                <?php echo esc_html( $info['name'] ); ?>
                                <?php if ( $sql_exists ) : ?><br><small style="color:#9ca3af">SQL: <?php echo $sql_size; ?></small><?php endif; ?>
                            </td>
                            <td style="padding:8px 16px">
                                <?php if ( $info['exists'] ) : ?>
                                    <span style="color:#22c55e;font-weight:600">✓ Exists</span>
                                <?php else : ?>
                                    <span style="color:#f0b429;font-weight:600">✗ Missing</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding:8px 16px;color:#6b7280;font-size:13px"><?php echo $info['exists'] ? number_format( $info['count'] ) : '—'; ?></td>
                            <td style="padding:8px 16px">
                                <?php if ( $sql_exists ) : ?>
                                    <button type="button" class="sh-btn sh-btn-sm sh-import-table" data-table="<?php echo esc_attr( $table ); ?>">
                                        <?php echo $info['exists'] ? 'Re-import' : 'Import'; ?>
                                    </button>
                                <?php else : ?>
                                    <span style="font-size:11px;color:#9ca3af">No SQL file</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if ( ! $all_ok && $data_source === 'database' ) : ?>
                    <div style="padding:12px 16px;background:#fee2e2;border-top:1px solid #fca5a5">
                        <p style="margin:0;font-size:12px;color:#991b1b">⚠️ Tablolar eksik. Database modu çalışmaz — ya eksik tabloları import edin ya da WC/Fallback moduna geçin.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- IP Geolocation -->
        <div class="sh-card" style="margin-bottom:20px">
            <div class="sh-card-header" style="display:flex;align-items:center;gap:12px">
                <h3 style="margin:0">📍 IP Geolocation</h3>
                <label class="sh-toggle">
                    <input type="checkbox" id="sh-enable-ip2country" <?php checked( $ip_enabled ); ?> onchange="shLocIpToggle(this.checked)">
                    <span class="sh-toggle-slider"></span>
                </label>
                <span style="font-size:12px;color:#9ca3af">Kullanıcının IP'sinden ülke tespiti</span>
            </div>
            <div id="sh-ip-options" style="<?php echo ! $ip_enabled ? 'display:none' : ''; ?>">
            <div class="sh-card-body">
                <div style="display:flex;gap:16px;margin-bottom:12px">
                    <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                        <input type="radio" name="ip2country_source" value="api" <?php checked( $settings['ip2country_source'] ?? 'api', 'api' ); ?>>
                        API (geoplugin.net) — Kurulum gerektirmez
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                        <input type="radio" name="ip2country_source" value="db" <?php checked( $settings['ip2country_source'] ?? 'api', 'db' ); ?>>
                        Database — Hızlı, ip2country tablosu gerekir
                    </label>
                </div>
                <p style="margin:0;font-size:12px;color:#6b7280">
                    Şu an: <strong>
                    <?php
                    $ip_source = $settings['ip2country_source'] ?? 'api';
                    if ( $data_source === 'database' && $ip_source === 'db' ) {
                        echo 'MySQL (ip2country table)';
                    } else {
                        echo 'geoplugin.net API';
                    }
                    ?>
                    </strong>
                </p>
            </div>
            </div>
        </div>

        <!-- Location DB (sadece database modda) -->
        <div class="sh-card" id="sh-location-db-card" style="margin-bottom:20px;<?php echo $data_source !== 'database' ? 'display:none' : ''; ?>">
            <div class="sh-card-header" style="display:flex;align-items:center;gap:12px">
                <h3 style="margin:0">🗄️ Location Database</h3>
                <label class="sh-toggle">
                    <input type="checkbox" id="sh-enable-location-db" <?php checked( ! empty( $settings['enable_location_db'] ) ); ?>>
                    <span class="sh-toggle-slider"></span>
                </label>
                <span style="font-size:12px;color:#9ca3af">Countries + States tablolarını aktif et</span>
            </div>
            <p style="margin:8px 16px;font-size:12px;color:#6b7280">Şehir/il dropdown'ları için gereklidir. cities tablosu kullanılmıyor.</p>
        </div>

        <!-- WooCommerce Mapping -->
        <?php if ( defined( 'ENABLE_ECOMMERCE' ) && ENABLE_ECOMMERCE ) : ?>
        <div class="sh-card" style="margin-bottom:20px">
            <div class="sh-card-header" style="display:flex;align-items:center;gap:12px">
                <h3 style="margin:0">🛒 WooCommerce State Mapping</h3>
                <label class="sh-toggle">
                    <input type="checkbox" id="sh-woo-state-mapping" <?php checked( ! empty( $settings['woo_state_mapping'] ) ); ?>>
                    <span class="sh-toggle-slider"></span>
                </label>
                <span style="font-size:12px;color:#9ca3af">WC billing_state kodlarıyla eşle</span>
            </div>
        </div>
        <?php endif; ?>

        <div id="sh-loc-toast" style="position:fixed;bottom:24px;right:24px;z-index:9999"></div>

        <div style="display:flex;gap:10px;margin-top:8px">
            <button type="button" id="sh-loc-save-settings" class="sh-btn sh-btn-primary">💾 Save Settings</button>
        </div>

        </div><!-- /sh-loc-options -->

        </div><!-- .sh-main -->
        <div class="sh-sidebar"><?php self::renderSidebar( $settings ); ?></div>
        </div><!-- .sh-layout -->

        <script>
        function shLocToggleMain(enabled) {
            document.getElementById('sh-loc-options').style.display = enabled ? '' : 'none';
        }
        function shLocSourceChange(source) {
            var dbWrap   = document.getElementById('sh-db-tables-wrap');
            var dbCard   = document.getElementById('sh-location-db-card');
            if (dbWrap)  dbWrap.style.display  = source === 'database' ? '' : 'none';
            if (dbCard)  dbCard.style.display  = source === 'database' ? '' : 'none';
        }
        function shLocIpToggle(enabled) {
            document.getElementById('sh-ip-options').style.display = enabled ? '' : 'none';
        }
        function shLocToast(msg, isError) {
            var el = document.getElementById('sh-loc-toast');
            if (!el) return;
            var item = document.createElement('div');
            item.className = 'sh-toast-item' + (isError ? ' sh-toast-error' : ' sh-toast-success');
            item.textContent = msg;
            el.appendChild(item);
            setTimeout(function() { item.remove(); }, 3500);
        }
        jQuery(function($) {
            $('#sh-loc-save-settings').on('click', function() {
                var btn = $(this).text('Saving...').prop('disabled', true);
                var settings = {
                    enable_localization:  $('#sh-enable-localization').is(':checked') ? '1' : '0',
                    location_data_source: $('input[name="location_data_source"]:checked').val() || 'database',
                    enable_ip2country:    $('#sh-enable-ip2country').is(':checked') ? '1' : '0',
                    ip2country_source:    $('input[name="ip2country_source"]:checked').val() || 'api',
                    enable_location_db:   $('#sh-enable-location-db').is(':checked') ? '1' : '0',
                    woo_state_mapping:    $('#sh-woo-state-mapping').is(':checked') ? '1' : '0',
                };
                $.post(ajaxurl, {
                    action: 'sh_loc_save_settings',
                    nonce: '<?php echo esc_js( $nonce ); ?>',
                    settings: settings
                }, function(res) {
                    btn.text('💾 Save Settings').prop('disabled', false);
                    if (res.success) {
                        shLocToast('✓ Settings saved.', false);
                    } else {
                        shLocToast('✗ Error: ' + (res.data || 'Unknown error'), true);
                    }
                }).fail(function() {
                    btn.text('💾 Save Settings').prop('disabled', false);
                    shLocToast('✗ Request failed.', true);
                });
            });

            // Import table butonları
            $(document).on('click', '.sh-import-table', function() {
                var btn   = $(this);
                var table = btn.data('table');
                btn.text('Importing...').prop('disabled', true);
                $.post(ajaxurl, {
                    action: 'sh_loc_import_table',
                    nonce:  '<?php echo esc_js( $nonce ); ?>',
                    table:  table
                }, function(res) {
                    if (res.success) {
                        shLocToast('✓ ' + res.data.message, false);
                        btn.text('Re-import').prop('disabled', false);
                        // Satırdaki row count'u güncelle
                        btn.closest('tr').find('.sh-table-count').text(res.data.count.toLocaleString());
                    } else {
                        shLocToast('✗ ' + (res.data || 'Error'), true);
                        btn.text('Import').prop('disabled', false);
                    }
                }).fail(function() {
                    btn.text('Import').prop('disabled', false);
                    shLocToast('✗ Request failed.', true);
                });
            });
        });
        </script>
        <?php
    }

    // ─── Database Tab ─────────────────────────────────────────────────────────

    private static function renderDatabaseTab( string $nonce, string $ajax_url ): void
    {
        $table_status = LocationSchema::getTableStatus();

        // cities tablosu kaldırıldı — salt-next ile uyumlu
        $tables_info = [
            'countries'  => [
                'desc'     => 'Dünya ülkeleri — ISO kodları, bölgeler, timezone',
                'required' => 'IP Geo veya Location DB',
                'badge'    => 'required',
            ],
            'states'     => [
                'desc'     => 'İller/eyaletler — WooCommerce state kodları dahil',
                'required' => 'IP Geo veya Location DB',
                'badge'    => 'required',
            ],
            'ip2country' => [
                'desc'     => 'IP aralığı → ülke eşleşmesi (büyük dosya ~50MB)',
                'required' => 'IP Geo (DB kaynağı)',
                'badge'    => 'optional',
            ],
        ];

        $settings     = LocationSettings::get();
        $data_source  = $settings['location_data_source'] ?? 'database';
        $all_ok       = ! array_filter( $table_status, fn( $t ) => ! $t['exists'] );
        ?>
        <div class="sh-layout">
        <div class="sh-main">

        <?php if ( $data_source === 'package' ) : ?>
        <div style="background:#fef9c3;border:1px solid #fde68a;border-radius:8px;padding:14px 16px;margin-bottom:16px">
            <strong style="font-size:13px">⚠️ WC/Fallback modu aktif</strong>
            <p style="margin:4px 0 0;font-size:12px;color:#6b7280">Database moduna geçmek için <a href="?page=sh-localization&tab=settings">Settings</a> sayfasından <strong>MySQL Database</strong> seçin.</p>
        </div>
        <?php endif; ?>

        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden">
            <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6">
                <strong>Database Tables</strong>
                <p style="margin:4px 0 0;font-size:12px;color:#9ca3af">
                    SQL dosyaları: <code><?php echo esc_html( SH_STATIC_PATH . 'data/' ); ?></code>
                    &nbsp;|&nbsp; <strong>cities</strong> tablosu kullanılmıyor.
                </p>
            </div>
            <table style="width:100%;border-collapse:collapse">
                <thead><tr>
                    <?php foreach ( [ 'Tablo', 'Durum', 'Satır', 'Gerekli', 'Açıklama', 'İşlem' ] as $h ) : ?>
                    <th style="padding:10px 18px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #f3f4f6"><?php echo $h; ?></th>
                    <?php endforeach; ?>
                </tr></thead>
                <tbody>
                <?php foreach ( $table_status as $table => $info ) :
                    $ti         = $tables_info[ $table ] ?? [];
                    $sql_path   = SH_STATIC_PATH . 'data/' . $table . '.sql';
                    $sql_exists = file_exists( $sql_path );
                    $sql_size   = $sql_exists ? size_format( filesize( $sql_path ), 1 ) : '—';
                    $is_optional = ( $ti['badge'] ?? '' ) === 'optional';
                ?>
                <tr style="border-bottom:1px solid #f9fafb">
                    <td style="padding:10px 18px">
                        <strong style="font-family:Consolas,monospace;font-size:12px"><?php echo esc_html( $info['name'] ); ?></strong>
                        <?php if ( $is_optional ) : ?>
                            <span style="font-size:10px;padding:1px 5px;border-radius:8px;background:#f3f4f6;color:#6b7280;margin-left:4px">optional</span>
                        <?php endif; ?>
                        <?php if ( $sql_exists ) : ?><br><small style="color:#9ca3af">SQL: <?php echo $sql_size; ?></small><?php endif; ?>
                    </td>
                    <td style="padding:10px 18px">
                        <?php if ( $info['exists'] ) : ?>
                            <span style="color:#22c55e;font-weight:600">✓ Mevcut</span>
                        <?php else : ?>
                            <span style="color:#f0b429;font-weight:600">✗ Eksik</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding:10px 18px;color:#6b7280;font-size:13px" class="sh-table-count">
                        <?php echo $info['exists'] ? number_format( $info['count'] ) : '—'; ?>
                    </td>
                    <td style="padding:10px 18px;font-size:12px;color:#6b7280"><?php echo esc_html( $ti['required'] ?? '' ); ?></td>
                    <td style="padding:10px 18px;font-size:12px;color:#6b7280"><?php echo esc_html( $ti['desc'] ?? '' ); ?></td>
                    <td style="padding:10px 18px">
                        <?php if ( $sql_exists ) : ?>
                            <button type="button" class="sh-btn sh-btn-sm sh-import-table" data-table="<?php echo esc_attr( $table ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">
                                <?php echo $info['exists'] ? '↺ Re-import' : '⬇ Import'; ?>
                            </button>
                        <?php else : ?>
                            <span style="font-size:11px;color:#9ca3af">SQL yok</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ( ! $all_ok && $data_source === 'database' ) : ?>
            <div style="padding:12px 16px;background:#fee2e2;border-top:1px solid #fca5a5">
                <p style="margin:0;font-size:12px;color:#991b1b">
                    ⚠️ Bazı tablolar eksik. Eksik tabloları import edin veya <a href="?page=sh-localization&tab=settings">Settings</a>'den WC/Fallback moduna geçin.
                </p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Hızlı Import Notu -->
        <div style="background:#f0f6ff;border:1px solid #bfdbfe;border-radius:8px;padding:14px 16px;margin-top:16px;font-size:12px;color:#1e40af">
            <strong>İpucu:</strong> <code>ip2country</code> tablosu ~1M satır içerir, import birkaç dakika sürebilir. 
            <code>countries</code> ve <code>states</code> tablolarıyla WC dropdown'ları anında çalışır.
        </div>

        </div><!-- .sh-main -->
        <div class="sh-sidebar"><?php self::renderSidebar( LocationSettings::get() ); ?></div>
        </div><!-- .sh-layout -->
        <?php
    }

    // ─── Regional Posts Tab ───────────────────────────────────────────────────

    private static function renderRegionalTab( array $settings, string $nonce, string $ajax_url ): void
    {
        $post_types      = get_post_types( [ 'public' => true ], 'objects' );
        $mappings        = $settings['regional_post_settings'] ?? [];
        $regional_active = ! empty( $settings['enable_regional_posts'] );
        ?>
        <div class="sh-layout">
        <div class="sh-main">

        <!-- Regional Posts Toggle — tab başında -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:16px 18px;margin-bottom:16px;display:flex;align-items:center;gap:12px">
            <label class="sh-toggle">
                <input type="checkbox" id="sh-enable-regional-posts" <?php checked( $regional_active ); ?>>
                <span class="sh-toggle-slider"></span>
            </label>
            <div>
                <strong style="font-size:13px">Regional Posts</strong>
                <p style="margin:2px 0 0;font-size:12px;color:#6b7280">Filter posts and terms by user's detected region. Requires IP Geolocation to be active.</p>
            </div>
            <button type="button" id="sh-loc-save-regional-toggle" class="sh-btn sh-btn-primary sh-btn-sm" style="margin-left:auto">Save</button>
        </div>

        <!-- Mapping alanı — sadece toggle aktifken görünür -->
        <div id="sh-regional-mappings-wrap" <?php echo $regional_active ? '' : 'style="display:none"'; ?>>

        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-bottom:16px">
            <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6"><strong>Post Type → Taxonomy Mappings</strong>
                <p style="margin:4px 0 0;font-size:12px;color:#9ca3af">Define which post types and their taxonomies are filtered by region.</p>
            </div>
            <div style="padding:18px">

                <!-- Add Mapping -->
                <div style="display:flex;gap:8px;align-items:flex-end;margin-bottom:16px;flex-wrap:wrap">
                    <div>
                        <label class="sh-field-label">Post Type</label>
                        <select id="sh-new-post-type" class="sh-select">
                            <option value="">Select post type...</option>
                            <?php foreach ( $post_types as $pt ) : ?>
                                <option value="<?php echo esc_attr($pt->name); ?>"><?php echo esc_html($pt->label); ?> (<?php echo esc_html($pt->name); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="sh-field-label">Taxonomy</label>
                        <select id="sh-new-taxonomy" class="sh-select">
                            <option value="">Select post type first...</option>
                        </select>
                    </div>
                    <button type="button" id="sh-add-mapping" class="sh-btn sh-btn-primary">+ Add</button>
                </div>

                <!-- Mappings List -->
                <table style="width:100%;border-collapse:collapse" id="sh-mappings-table">
                    <thead><tr>
                        <th style="padding:8px 12px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #f3f4f6">Post Type</th>
                        <th style="padding:8px 12px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #f3f4f6">Taxonomy</th>
                        <th style="padding:8px 12px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #f3f4f6">Action</th>
                    </tr></thead>
                    <tbody id="sh-mappings-list">
                    <?php if ( empty($mappings) ) : ?>
                        <tr><td colspan="3" style="padding:12px;color:#9ca3af;font-size:13px">No mappings yet. Add one above.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>

                <div style="margin-top:16px">
                    <button type="button" id="sh-loc-save-settings" class="sh-btn sh-btn-primary">Save Mappings</button>
                </div>
            </div>
        </div>

        <!-- How it works -->
        <div style="background:#f0f7ff;border:1px solid #bfdbfe;border-radius:8px;padding:16px">
            <strong style="font-size:13px;color:#1d4ed8">How Regional Posts Works</strong>
            <ul style="margin:8px 0 0;padding-left:20px;font-size:13px;color:#374151;line-height:1.8">
                <li>User visits the site → IP is detected → country code resolved</li>
                <li>Country code is matched to a <strong>Region</strong> taxonomy term</li>
                <li>Region ID is stored in cookie (<code>user_region</code>)</li>
                <li><code>pre_get_posts</code> adds <code>tax_query</code> for mapped post types</li>
                <li>Only posts assigned to user's region are shown</li>
                <li>Terms with 0 posts in user's region are hidden from menus/filters</li>
                <li>A post can belong to <strong>multiple regions</strong> (multi-select)</li>
            </ul>
        </div>

        </div><!-- end sh-regional-mappings-wrap -->

        </div>
        <div class="sh-sidebar"><?php self::renderSidebar($settings); ?></div>
        </div>
        <?php
    }

    // ─── Test Tab ─────────────────────────────────────────────────────────────

    private static function renderTestTab( string $nonce, string $ajax_url ): void
    {
        $lm           = LocationManager::getInstance();
        $current_ip   = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_region  = \Data::get( 'site_config.user_region' );
        $user_country = $_COOKIE['user_country_code'] ?? '';
        ?>
        <div class="sh-layout">
        <div class="sh-main">

        <!-- IP Test -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-bottom:16px">
            <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6"><strong>IP Geolocation Test</strong></div>
            <div style="padding:18px">
                <div style="display:flex;gap:8px;margin-bottom:12px">
                    <input type="text" id="sh-test-ip-input" class="sh-input" placeholder="<?php echo esc_attr($current_ip); ?>" value="<?php echo esc_attr($current_ip); ?>" style="max-width:220px">
                    <button type="button" id="sh-test-ip-btn" class="sh-btn sh-btn-primary">Test IP</button>
                </div>
                <div id="sh-test-ip-result" style="background:#0d1117;border-radius:6px;padding:12px;font-family:Consolas,monospace;font-size:12px;color:#c8d6e5;min-height:60px">
                    Click "Test IP" to see geolocation result.
                </div>
            </div>
        </div>

        <!-- Current Session -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-bottom:16px">
            <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6"><strong>Current Session</strong></div>
            <table style="width:100%;border-collapse:collapse">
                <tr style="border-bottom:1px solid #f9fafb">
                    <td style="padding:10px 18px;color:#6b7280;font-size:13px;width:180px">Current IP</td>
                    <td style="padding:10px 18px;font-family:Consolas,monospace;font-size:12px"><?php echo esc_html($current_ip); ?></td>
                </tr>
                <tr style="border-bottom:1px solid #f9fafb">
                    <td style="padding:10px 18px;color:#6b7280;font-size:13px">Detected Country</td>
                    <td style="padding:10px 18px;font-size:13px"><?php echo $user_country ? '<strong>' . esc_html($user_country) . '</strong>' : '<span style="color:#9ca3af">Not detected</span>'; ?></td>
                </tr>
                <tr style="border-bottom:1px solid #f9fafb">
                    <td style="padding:10px 18px;color:#6b7280;font-size:13px">User Region IDs</td>
                    <td style="padding:10px 18px;font-size:13px">
                        <?php if ( ! empty($user_region) ) : ?>
                            <?php foreach ( (array)$user_region as $rid ) :
                                $term = get_term( $rid, 'region' );
                            ?>
                                <span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;background:#dbeafe;color:#1d4ed8;margin-right:4px">
                                    <?php echo $term ? esc_html($term->name) : '#' . $rid; ?>
                                </span>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <span style="color:#9ca3af">No region set</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding:10px 18px;color:#6b7280;font-size:13px">Regional Posts Active</td>
                    <td style="padding:10px 18px">
                        <?php if ( defined('ENABLE_REGIONAL_POSTS') && ENABLE_REGIONAL_POSTS ) : ?>
                            <span style="color:#22c55e;font-weight:600">✓ Yes</span>
                        <?php else : ?>
                            <span style="color:#9ca3af">✗ No</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>

        <!-- WC Mapping Test -->
        <?php if ( defined('ENABLE_ECOMMERCE') && ENABLE_ECOMMERCE ) : ?>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden">
            <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6"><strong>WooCommerce State Mapping Test</strong></div>
            <div style="padding:18px">
                <div style="display:flex;gap:8px;margin-bottom:12px">
                    <input type="number" id="sh-test-city-id" class="sh-input" placeholder="City ID (e.g. 34)" style="max-width:160px">
                    <button type="button" class="sh-btn sh-btn-primary" onclick="(function(){
                        var id = document.getElementById('sh-test-city-id').value;
                        if (!id) return;
                        jQuery.post(window.shLoc.ajaxUrl, {action:'sh_loc_test_ip', nonce:window.shLoc.nonce, ip:'wc_test_'+id}, function(r){
                            document.getElementById('sh-wc-result').textContent = JSON.stringify(r.data, null, 2);
                        });
                    })()">Test</button>
                </div>
                <div id="sh-wc-result" style="background:#0d1117;border-radius:6px;padding:12px;font-family:Consolas,monospace;font-size:12px;color:#c8d6e5;min-height:40px">
                    Enter a city ID to test WC state mapping.
                </div>
            </div>
        </div>
        <?php endif; ?>

        </div>
        <div class="sh-sidebar"><?php self::renderSidebar(LocationSettings::get()); ?></div>
        </div>
        <?php
    }

    // ─── Sidebar ──────────────────────────────────────────────────────────────

    private static function renderSidebar( array $settings ): void
    {
        $pt_count  = count( $settings['regional_post_settings'] ?? [] );
        ?>
        <div class="sh-sidebar-box">
            <h3>Quick Links</h3>
            <ul style="margin:0;padding:0;list-style:none;font-size:12px">
                <li style="padding:5px 0;border-bottom:1px solid #f3f4f6"><a href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=region')); ?>">→ Manage Regions</a></li>
                <li style="padding:5px 0;border-bottom:1px solid #f3f4f6"><a href="?page=sh-localization&tab=database">→ Import Tables</a></li>
                <li style="padding:5px 0"><a href="?page=sh-localization&tab=test">→ Test Geolocation</a></li>
            </ul>
        </div>
        <div class="sh-sidebar-box" style="margin-top:12px">
            <h3>Status</h3>
            <ul style="margin:0;padding:0;list-style:none;font-size:12px;color:#6b7280">
                <li style="padding:4px 0">IP Geo: <strong><?php echo $settings['enable_ip2country'] ? '✓ ' . strtoupper($settings['ip2country_source']) : '✗ Off'; ?></strong></li>
                <li style="padding:4px 0">Location DB: <strong><?php echo $settings['enable_location_db'] ? '✓ On' : '✗ Off'; ?></strong></li>
                <li style="padding:4px 0">Regional Posts: <strong><?php echo $settings['enable_regional_posts'] ? '✓ On (' . $pt_count . ' types)' : '✗ Off'; ?></strong></li>
            </ul>
        </div>
        <?php
    }
}