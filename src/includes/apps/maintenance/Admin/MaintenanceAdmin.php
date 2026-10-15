<?php

namespace SaltHareket\Maintenance\Admin;

use SaltHareket\Maintenance\MaintenanceSettings;
use SaltHareket\Maintenance\Maintenance;

/**
 * MaintenanceAdmin
 *
 * @version 1.1.0
 * @package SaltHareket\Maintenance\Admin
 */
class MaintenanceAdmin
{
    public static function register(): void
    {
        // add_action( 'admin_menu',            [ self::class, 'addMenuPage' ], 28 ); // Dashboard üzerinden erişiliyor
        add_action( 'admin_head',            [ self::class, 'hideNotices' ] );
        add_action( 'admin_enqueue_scripts', [ self::class, 'enqueueAssets' ] );
        add_action( 'wp_ajax_sh_maintenance_save',   [ self::class, 'ajaxSave' ] );
        add_action( 'wp_ajax_sh_maintenance_toggle', [ self::class, 'ajaxToggle' ] );
    }

    public static function addMenuPage(): void
    {
        add_submenu_page( 'theme-settings', '🔧 Maintenance', '🔧 Maintenance', 'manage_options', 'sh-maintenance', [ self::class, 'renderPage' ] );
    }

    public static function hideNotices(): void
    {
        if ( ( $_GET['page'] ?? '' ) !== 'sh-maintenance' ) return;
        echo '<style>body .notice:not(.sh-inline),body .updated:not(.sh-inline),body .error:not(.sh-inline){display:none!important;}</style>';
    }

    public static function enqueueAssets( string $hook ): void
    {
        if ( strpos( $hook, 'sh-maintenance' ) === false ) return;

        $apps_path = dirname( __DIR__, 2 );  // Admin/ → apps/
        $apps_url  = trailingslashit( SH_INCLUDES_URL ) . 'apps/';

        $css_path = $apps_path . '/sh-admin.css';
        if ( file_exists( $css_path ) ) {
            wp_enqueue_style( 'sh-admin-kit', $apps_url . 'sh-admin.css', [], filemtime( $css_path ) );
        }
        $select_css = $apps_path . '/sh-select.css';
        if ( file_exists( $select_css ) ) {
            wp_enqueue_style( 'sh-select', $apps_url . 'sh-select.css', [], filemtime( $select_css ) );
        }
        $select_js = $apps_path . '/sh-select.js';
        if ( file_exists( $select_js ) ) {
            wp_enqueue_script( 'sh-select', $apps_url . 'sh-select.js', [ 'jquery' ], filemtime( $select_js ), true );
        }
    }

    // ─── AJAX ─────────────────────────────────────────────────────────────────

    public static function ajaxSave(): void
    {
        check_ajax_referer( 'sh_maintenance_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $data = $_POST['settings'] ?? [];

        if ( isset( $data['white_pages'] ) && is_string( $data['white_pages'] ) ) {
            $data['white_pages'] = json_decode( stripslashes( $data['white_pages'] ), true ) ?? [];
        }
        if ( isset( $data['bypass_roles'] ) && is_string( $data['bypass_roles'] ) ) {
            $data['bypass_roles'] = json_decode( stripslashes( $data['bypass_roles'] ), true ) ?? [];
        }
        if ( isset( $data['ip_whitelist'] ) && is_string( $data['ip_whitelist'] ) ) {
            $data['ip_whitelist'] = array_values( array_filter( array_map( 'trim', explode( "\n", $data['ip_whitelist'] ) ) ) );
        }

        $was_active = MaintenanceSettings::isActive();
        MaintenanceSettings::save( $data );
        $is_active = MaintenanceSettings::isActive();

        // Sayfa yönetimi: aktifleşince oluştur/yayınla, pasifleşince draft'a al
        if ( ! $was_active && $is_active ) {
            self::ensureMaintenancePage();
        } elseif ( $was_active && ! $is_active ) {
            self::draftMaintenancePage();
        }

        wp_send_json_success( [ 'message' => 'Saved.', 'active' => $is_active ] );
    }

    public static function ajaxToggle(): void
    {
        check_ajax_referer( 'sh_maintenance_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $was_active = MaintenanceSettings::isActive();
        $active     = (bool) ( $_POST['active'] ?? false );
        MaintenanceSettings::save( [ 'active' => $active ] );

        if ( ! $was_active && $active ) self::ensureMaintenancePage();
        elseif ( $was_active && ! $active ) self::draftMaintenancePage();

        wp_send_json_success( [ 'active' => $active ] );
    }

    // ─── Page Management ──────────────────────────────────────────────────────

    /**
     * Maintenance sayfası yoksa oluştur, draft ise yayınla.
     * Sayfa ID'sini settings'e yazar.
     */
    private static function ensureMaintenancePage(): void
    {
        $settings = MaintenanceSettings::get();
        $page_id  = (int) ( $settings['maintenance_page'] ?? 0 );

        // Mevcut sayfayı kontrol et
        if ( $page_id > 0 ) {
            $post = get_post( $page_id );
            if ( $post ) {
                if ( $post->post_status !== 'publish' ) {
                    wp_update_post( [ 'ID' => $page_id, 'post_status' => 'publish' ] );
                }
                return;
            }
        }

        // "Under Construction" slug'lı sayfa var mı?
        $existing = get_page_by_path( 'under-construction' );
        if ( ! $existing ) {
            $existing = get_page_by_path( 'maintenance' );
        }

        if ( $existing ) {
            if ( $existing->post_status !== 'publish' ) {
                wp_update_post( [ 'ID' => $existing->ID, 'post_status' => 'publish' ] );
            }
            MaintenanceSettings::save( [ 'maintenance_page' => $existing->ID ] );
            return;
        }

        // Sayfa yok — oluştur
        $new_id = wp_insert_post( [
            'post_title'     => 'Under Construction',
            'post_name'      => 'under-construction',
            'post_content'   => '',
            'post_status'    => 'publish',
            'post_type'      => 'page',
            'page_template'  => 'under-construction.php',
        ] );

        if ( $new_id && ! is_wp_error( $new_id ) ) {
            MaintenanceSettings::save( [ 'maintenance_page' => $new_id ] );
        }
    }

    /**
     * Maintenance sayfasını draft'a al (app disabled).
     */
    private static function draftMaintenancePage(): void
    {
        $settings = MaintenanceSettings::get();
        $page_id  = (int) ( $settings['maintenance_page'] ?? 0 );
        if ( $page_id > 0 && get_post_status( $page_id ) === 'publish' ) {
            wp_update_post( [ 'ID' => $page_id, 'post_status' => 'draft' ] );
        }
    }

    // ─── PAGE ─────────────────────────────────────────────────────────────────

    public static function renderPage(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $s        = MaintenanceSettings::get();
        $nonce    = wp_create_nonce( 'sh_maintenance_nonce' );
        $ajax_url = admin_url( 'admin-ajax.php' );
        $active   = (bool) ( $s['active'] ?? false );
        $all_roles = wp_roles()->roles;
        $pages    = MaintenanceSettings::getPagesForSelection();
        $ip_str   = implode( "\n", $s['ip_whitelist'] ?? [] );
        $user_ip  = Maintenance::getUserIp();
        ?>
        <div class="sh-wrap" id="sh-maintenance-page">

        <!-- Toolbar: sadece başlık ve save butonu -->
        <div class="sh-toolbar">
            <h1>🔧 Maintenance
                <span id="sh-maint-h1-badge" class="sh-badge <?php echo $active ? 'sh-badge-red' : 'sh-badge-gray'; ?>" style="font-size:11px"><?php echo $active ? 'ON' : 'OFF'; ?></span>
            </h1>
            <span style="font-size:12px;color:#9ca3af">Maintenance mode management</span>
            <div class="sh-toolbar-right">
                <button type="button" id="sh-maint-save" class="sh-btn sh-btn-primary">💾 Save Settings</button>
            </div>
        </div>

        <div id="sh-maint-toast" style="position:fixed;bottom:24px;right:24px;z-index:9999"></div>

        <!-- Enable toggle card — her zaman görünür -->
        <div class="sh-card" style="margin-bottom:16px">
            <div style="padding:14px 20px;display:flex;align-items:center;gap:14px">
                <span style="font-size:15px">🔧</span>
                <strong style="font-size:14px">Maintenance Mode</strong>
                <label class="sh-toggle" style="margin-left:4px">
                    <input type="checkbox" id="sh-maint-toggle" <?php checked( $active ); ?> onchange="shMaintToggle(this.checked)">
                    <span class="sh-toggle-slider"></span>
                </label>
                <span id="sh-maint-status-label" style="font-size:12px;color:<?php echo $active ? '#d63638' : '#00a32a'; ?>;font-weight:600">
                    <?php echo $active ? '● ON — Visitors see maintenance page' : '○ OFF — Site is live'; ?>
                </span>
            </div>
        </div>

        <!-- Settings: sadece aktifken görünür -->
        <div id="sh-maint-settings-wrap" style="<?php echo ! $active ? 'display:none' : ''; ?>">

        <?php if ( $active ) : ?>
        <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:12px 18px;margin-bottom:16px;display:flex;align-items:center;gap:10px">
            <span>⚠️</span>
            <span style="font-size:13px;font-weight:500">Maintenance mode is <strong>active</strong>. Visitors see the maintenance page.</span>
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" class="sh-btn sh-btn-sm" style="margin-left:auto">View Site →</a>
        </div>
        <?php endif; ?>

        <div class="sh-layout">
        <div class="sh-main">

        <!-- Maintenance Page -->
        <div class="sh-card" style="margin-bottom:20px">
            <div class="sh-card-header"><strong>Maintenance Page</strong></div>
            <div class="sh-card-body">
                <p style="font-size:12px;color:#6b7280;margin:0 0 12px">Page shown to visitors during maintenance. Leave empty to show a blank 503 page.</p>
                <select id="sh-maint-page" style="width:100%;max-width:400px;padding:7px 10px;border:1px solid #ddd;border-radius:4px;font-size:13px">
                    <option value="0">— No page (HTTP status only) —</option>
                    <?php foreach ( $pages as $page ) : ?>
                    <option value="<?php echo (int) $page->ID; ?>" <?php selected( $s['maintenance_page'] ?? 0, $page->ID ); ?>>
                        <?php echo esc_html( $page->post_title ); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- HTTP Status -->
        <div class="sh-card" style="margin-bottom:20px">
            <div class="sh-card-header"><strong>HTTP Status Code</strong></div>
            <div class="sh-card-body">
                <?php $statuses = [ 200=>'200 — OK', 301=>'301 — Redirect', 404=>'404 — Not Found', 503=>'503 — Service Unavailable (recommended)' ];
                foreach ( $statuses as $code => $label ) : ?>
                <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;margin-bottom:8px">
                    <input type="radio" name="sh_maint_http" value="<?php echo $code; ?>" <?php checked( $s['http_status'] ?? 503, $code ); ?> onchange="shMaintHttpChange(<?php echo $code; ?>)">
                    <?php echo $label; ?>
                </label>
                <?php endforeach; ?>
                <div id="sh-maint-redirect-wrap" style="margin-top:10px;margin-left:24px;<?php echo ( $s['http_status'] ?? 503 ) !== 301 ? 'display:none' : ''; ?>">
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Redirect URL</label>
                    <input type="url" id="sh-maint-redirect-url" style="width:100%;max-width:400px;padding:6px 10px;border:1px solid #ddd;border-radius:4px" value="<?php echo esc_attr( $s['redirect_url'] ?? '' ); ?>" placeholder="https://example.com" />
                </div>
            </div>
        </div>

        <!-- White Pages -->
        <div class="sh-card" style="margin-bottom:20px">
            <div class="sh-card-header"><strong>White Pages</strong></div>
            <div class="sh-card-body">
                <p style="font-size:12px;color:#6b7280;margin:0 0 10px">Pages accessible during maintenance<?php echo ( defined( 'ENABLE_MULTILANGUAGE' ) && ENABLE_MULTILANGUAGE ) ? ' (translations included automatically)' : ''; ?>.</p>
                <select id="sh-maint-white-pages" multiple data-sh-select data-placeholder="Sayfa ara ve seç..." style="width:100%;max-width:500px">
                    <?php foreach ( $pages as $page ) :
                        $selected = in_array( (int) $page->ID, $s['white_pages'] ?? [], true );
                    ?>
                    <option value="<?php echo (int) $page->ID; ?>" <?php echo $selected ? 'selected' : ''; ?>>
                        <?php echo esc_html( $page->post_title ); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Bypass Roles -->
        <div class="sh-card" style="margin-bottom:20px">
            <div class="sh-card-header"><strong>Bypass Roles</strong></div>
            <div class="sh-card-body">
                <p style="font-size:12px;color:#6b7280;margin:0 0 12px">Users with these roles see the site normally. Administrator always bypasses.</p>
                <div style="display:flex;flex-wrap:wrap;gap:14px">
                    <?php foreach ( $all_roles as $slug => $role ) :
                        $checked  = in_array( $slug, $s['bypass_roles'] ?? [], true );
                        $disabled = $slug === 'administrator';
                    ?>
                    <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;<?php echo $disabled ? 'opacity:.6' : ''; ?>">
                        <input type="checkbox" class="sh-maint-bypass-role" value="<?php echo esc_attr( $slug ); ?>"
                               <?php checked( $checked || $disabled ); ?>
                               <?php echo $disabled ? 'disabled' : ''; ?>>
                        <?php echo esc_html( translate_user_role( $role['name'] ) ); ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- IP Whitelist -->
        <div class="sh-card" style="margin-bottom:20px">
            <div class="sh-card-header"><strong>IP Whitelist</strong></div>
            <div class="sh-card-body">
                <p style="font-size:12px;color:#6b7280;margin:0 0 8px">One IP per line. These IPs bypass maintenance mode.</p>
                <?php if ( $user_ip ) : ?>
                <p style="font-size:12px;color:#2271b1;margin:0 0 10px">
                    Your IP: <strong><?php echo esc_html( $user_ip ); ?></strong>
                    <button type="button" onclick="shMaintAddMyIp('<?php echo esc_js( $user_ip ); ?>')" style="margin-left:8px;font-size:11px;padding:2px 8px;background:#eff6ff;border:1px solid #2271b1;border-radius:3px;cursor:pointer;color:#2271b1">+ Add</button>
                </p>
                <?php endif; ?>
                <textarea id="sh-maint-ip-list" rows="4" style="width:100%;max-width:400px;font-family:monospace;font-size:12px;padding:8px;border:1px solid #ddd;border-radius:4px" placeholder="192.168.1.1&#10;10.0.0.1"><?php echo esc_textarea( $ip_str ); ?></textarea>
            </div>
        </div>

        </div><!-- .sh-main -->
        <div class="sh-sidebar"><?php self::renderSidebar( $s, $active ); ?></div>
        </div><!-- .sh-layout -->

        </div><!-- #sh-maint-settings-wrap -->
        </div><!-- .sh-wrap -->

        <script>
        var shMaintNonce   = '<?php echo esc_js( $nonce ); ?>';
        var shMaintAjaxUrl = '<?php echo esc_js( $ajax_url ); ?>';

        function shMaintToast(msg, isError) {
            var el = document.getElementById('sh-maint-toast');
            if (!el) return;
            var item = document.createElement('div');
            item.className = 'sh-toast-item' + (isError ? ' sh-toast-error' : ' sh-toast-success');
            item.textContent = msg;
            el.appendChild(item);
            setTimeout(function(){ item.remove(); }, 3500);
        }

        function shMaintToggle(active) {
            var wrap  = document.getElementById('sh-maint-settings-wrap');
            var label = document.getElementById('sh-maint-status-label');
            var badge = document.getElementById('sh-maint-h1-badge');
            if (wrap) wrap.style.display = active ? '' : 'none';
            if (label) {
                label.textContent = active ? '● Maintenance ON' : '○ Site Live';
                label.style.color = active ? '#d63638' : '#00a32a';
            }
            if (badge) {
                badge.textContent = active ? 'ON' : 'OFF';
                badge.className   = 'sh-badge ' + (active ? 'sh-badge-red' : 'sh-badge-gray');
            }
            jQuery.post(shMaintAjaxUrl, {
                action: 'sh_maintenance_toggle',
                nonce:  shMaintNonce,
                active: active ? 1 : 0,
            }, function(res) {
                shMaintToast(active ? '🔧 Maintenance ON' : '✅ Site Live');
            });
        }

        function shMaintHttpChange(code) {
            var wrap = document.getElementById('sh-maint-redirect-wrap');
            if (wrap) wrap.style.display = (code === 301) ? '' : 'none';
        }

        function shMaintAddMyIp(ip) {
            var ta = document.getElementById('sh-maint-ip-list');
            if (!ta) return;
            var lines = ta.value.split('\n').map(function(l){ return l.trim(); }).filter(Boolean);
            if (!lines.includes(ip)) { lines.push(ip); ta.value = lines.join('\n'); }
        }

        jQuery(function($) {
            $('#sh-maint-save').on('click', function() {
                var $btn = $(this).prop('disabled', true).text('Saving...');

                // White pages — selected values
                var whitePages = $('#sh-maint-white-pages').val() || [];
                whitePages = whitePages.map(function(v){ return parseInt(v); });

                var bypassRoles = ['administrator'];
                document.querySelectorAll('.sh-maint-bypass-role:checked:not([disabled])').forEach(function(el){ bypassRoles.push(el.value); });

                $.post(shMaintAjaxUrl, {
                    action: 'sh_maintenance_save',
                    nonce:  shMaintNonce,
                    settings: {
                        active:           $('#sh-maint-toggle').is(':checked') ? 1 : 0,
                        maintenance_page: parseInt($('#sh-maint-page').val()) || 0,
                        white_pages:      JSON.stringify(whitePages),
                        bypass_roles:     JSON.stringify(bypassRoles),
                        ip_whitelist:     $('#sh-maint-ip-list').val(),
                        http_status:      parseInt($('input[name="sh_maint_http"]:checked').val()) || 503,
                        redirect_url:     $('#sh-maint-redirect-url').val() || '',
                    },
                }, function(res) {
                    $btn.prop('disabled', false).text('💾 Save Settings');
                    if (res.success) { shMaintToast('✅ Settings saved.'); }
                    else { shMaintToast('✗ Save failed.', true); }
                }).fail(function(){
                    $btn.prop('disabled', false).text('💾 Save Settings');
                    shMaintToast('✗ Request failed.', true);
                });
            });
        });
        </script>
        <?php
    }

    private static function renderSidebar( array $s, bool $active ): void
    {
        ?>
        <div class="sh-sidebar-box">
            <h3>Status</h3>
            <div style="text-align:center;padding:16px">
                <div style="font-size:32px"><?php echo $active ? '🔧' : '✅'; ?></div>
                <div style="font-size:13px;font-weight:600;margin-top:6px;color:<?php echo $active ? '#d63638' : '#00a32a'; ?>">
                    <?php echo $active ? 'Maintenance ON' : 'Site Live'; ?>
                </div>
                <?php if ( $active && ! empty( $s['maintenance_page'] ) ) : ?>
                <div style="font-size:11px;color:#6b7280;margin-top:4px">
                    Page: <?php echo esc_html( get_the_title( $s['maintenance_page'] ) ); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="sh-sidebar-box" style="margin-top:12px">
            <h3>Quick Links</h3>
            <ul style="margin:0;padding:0;list-style:none;font-size:12px">
                <li style="padding:5px 0;border-bottom:1px solid #f3f4f6"><a href="<?php echo esc_url( home_url('/') ); ?>" target="_blank">→ View Site</a></li>
                <li style="padding:5px 0;border-bottom:1px solid #f3f4f6"><a href="<?php echo esc_url( admin_url('edit.php?post_type=page') ); ?>">→ All Pages</a></li>
                <li style="padding:5px 0"><a href="<?php echo esc_url( admin_url('post-new.php?post_type=page') ); ?>">→ New Page</a></li>
            </ul>
        </div>
        <div class="sh-sidebar-box" style="margin-top:12px">
            <h3>Info</h3>
            <ul style="margin:0;padding:0;list-style:none;font-size:12px;color:#6b7280">
                <li style="padding:4px 0">HTTP: <strong><?php echo (int) ( $s['http_status'] ?? 503 ); ?></strong></li>
                <li style="padding:4px 0">White pages: <strong><?php echo count( $s['white_pages'] ?? [] ); ?></strong></li>
                <li style="padding:4px 0">Bypass roles: <strong><?php echo count( $s['bypass_roles'] ?? [] ); ?></strong></li>
                <li style="padding:4px 0">IP whitelist: <strong><?php echo count( $s['ip_whitelist'] ?? [] ); ?></strong></li>
            </ul>
        </div>
        <?php
    }
}
