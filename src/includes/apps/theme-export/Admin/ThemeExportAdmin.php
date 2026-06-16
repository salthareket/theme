<?php

namespace SaltHareket\ThemeExport\Admin;

use SaltHareket\ThemeExport\ThemeExporter;

/**
 * ThemeExportAdmin
 *
 * Admin UI — Theme Settings altında submenu.
 * sh-admin.css ile stil, inline style yok.
 *
 * @version 1.0.0
 */
class ThemeExportAdmin
{
    public static function register(): void
    {
        add_action( 'admin_menu',             [ self::class, 'addMenuPage' ], 30 );
        add_action( 'admin_head',             [ self::class, 'hideNotices' ] );
        add_action( 'admin_enqueue_scripts',  [ self::class, 'enqueueAssets' ] );
        add_action( 'wp_ajax_' . ThemeExporter::getAjaxExport(),   [ self::class, 'handleExport' ] );
        add_action( 'wp_ajax_' . ThemeExporter::getAjaxDelete(),   [ self::class, 'handleDelete' ] );
        add_action( 'wp_ajax_' . ThemeExporter::getAjaxCancel(),   [ self::class, 'handleCancel' ] );
        add_action( 'wp_ajax_' . ThemeExporter::getAjaxDownload(), [ self::class, 'handleDownload' ] );
        add_action( 'wp_ajax_sh_export_save_settings',             [ self::class, 'handleSaveSettings' ] );
    }

    public static function addMenuPage(): void
    {
        add_submenu_page(
            'theme-settings',
            '📦 Export',
            '📦 Export',
            'manage_options',
            'sh-theme-export',
            [ self::class, 'renderPage' ]
        );
    }

    public static function hideNotices(): void
    {
        if ( ( $_GET['page'] ?? '' ) !== 'sh-theme-export' ) return;
        echo '<style>body .notice:not(.sh-inline),body .updated:not(.sh-inline),body .error:not(.sh-inline){display:none!important;}</style>';
    }

    public static function enqueueAssets( string $hook ): void
    {
        if ( strpos( $hook, 'sh-theme-export' ) === false ) return;

        $css_path = dirname( __DIR__, 2 ) . '/sh-admin.css';
        $css_url  = trailingslashit( SH_INCLUDES_URL ) . 'apps/sh-admin.css';
        if ( file_exists( $css_path ) ) {
            wp_enqueue_style( 'sh-admin-kit', $css_url, [], filemtime( $css_path ) );
        }

        $js_path = __DIR__ . '/export-ui.js';
        $js_url  = trailingslashit( SH_INCLUDES_URL ) . 'apps/theme-export/Admin/export-ui.js';
        if ( file_exists( $js_path ) ) {
            wp_enqueue_script( 'sh-export-ui', $js_url, [ 'jquery' ], filemtime( $js_path ), true );
        }
    }

    // ─── AJAX Proxies ─────────────────────────────────────────────────────────

    public static function handleExport(): void   { ( new ThemeExporter() )->handleExport(); }
    public static function handleDelete(): void   { ( new ThemeExporter() )->handleDelete(); }
    public static function handleCancel(): void   { ( new ThemeExporter() )->handleCancel(); }
    public static function handleDownload(): void { ( new ThemeExporter() )->handleDownload(); }

    public static function handleSaveSettings(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized.' );
        check_ajax_referer( ThemeExporter::getNonceAction(), ThemeExporter::getNonceField() );
        ThemeExporter::saveSettings( $_POST['settings'] ?? [] );
        wp_send_json_success( 'Settings saved.' );
    }

    // ─── Main Page ────────────────────────────────────────────────────────────

    public static function renderPage(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $tab      = sanitize_key( $_GET['tab'] ?? 'export' );
        $history  = ThemeExporter::getHistory();
        $settings = ThemeExporter::getSettings();
        $nonce    = wp_create_nonce( ThemeExporter::getNonceAction() );
        $ajax_url = admin_url( 'admin-ajax.php' );
        ?>
        <div class="sh-wrap" id="sh-export-page">

        <div class="sh-toolbar">
            <h1>📦 Theme Export</h1>
            <?php if ( ! empty( $history ) ) : ?>
                <span class="sh-badge sh-badge-gray"><?php echo count( $history ); ?> export<?php echo count( $history ) !== 1 ? 's' : ''; ?></span>
            <?php endif; ?>
            <div class="sh-toolbar-right">
                <a href="?page=sh-theme-export&tab=export"   class="sh-tab-btn <?php echo $tab === 'export'   ? 'active' : ''; ?>">Export</a>
                <a href="?page=sh-theme-export&tab=history"  class="sh-tab-btn <?php echo $tab === 'history'  ? 'active' : ''; ?>">History<?php if ( ! empty( $history ) ) echo ' <span class="sh-badge sh-badge-blue" style="margin-left:4px">' . count( $history ) . '</span>'; ?></a>
                <a href="?page=sh-theme-export&tab=settings" class="sh-tab-btn <?php echo $tab === 'settings' ? 'active' : ''; ?>">Settings</a>
            </div>
        </div>

        <?php
        $titles = [
            'export'   => [ 'title' => 'Create Export',    'desc' => 'Export your site database, theme, or full WordPress installation.' ],
            'history'  => [ 'title' => 'Export History',   'desc' => 'Previously created exports. Download or delete.' ],
            'settings' => [ 'title' => 'Export Settings',  'desc' => 'Configure export behavior, history limit, and scheduled exports.' ],
        ];
        $cur = $titles[ $tab ] ?? $titles['export'];
        ?>
        <div class="sh-section-title">
            <h2><?php echo esc_html( $cur['title'] ); ?></h2>
            <p><?php echo esc_html( $cur['desc'] ); ?></p>
        </div>

        <?php
        if ( $tab === 'history' )       self::renderHistoryTab( $history, $nonce );
        elseif ( $tab === 'settings' )  self::renderSettingsTab( $settings, $nonce, $ajax_url );
        else                            self::renderExportTab( $settings, $nonce, $ajax_url );
        ?>

        <div id="sh-toast"></div>
        </div>

        <script>
        window.shExport = {
            nonce:      <?php echo wp_json_encode( $nonce ); ?>,
            nonceField: <?php echo wp_json_encode( ThemeExporter::getNonceField() ); ?>,
            ajaxUrl:    <?php echo wp_json_encode( $ajax_url ); ?>,
            actions: {
                export:   <?php echo wp_json_encode( ThemeExporter::getAjaxExport() ); ?>,
                delete:   <?php echo wp_json_encode( ThemeExporter::getAjaxDelete() ); ?>,
                cancel:   <?php echo wp_json_encode( ThemeExporter::getAjaxCancel() ); ?>,
                download: <?php echo wp_json_encode( ThemeExporter::getAjaxDownload() ); ?>,
                settings: 'sh_export_save_settings',
            },
            exportDir: <?php echo wp_json_encode( ThemeExporter::getExportDir() ); ?>,
        };
        </script>
        <?php
    }

    // ─── Export Tab ───────────────────────────────────────────────────────────

    private static function renderExportTab( array $settings, string $nonce, string $ajax_url ): void
    {
        $default_mode = $settings['default_mode'] ?? 'full';
        ?>
        <div class="sh-layout">
        <div class="sh-main">

        <!-- Export Form -->
        <div class="sh-card" id="sh-export-form-card">
            <div class="sh-card-header">
                <strong>Export Configuration</strong>
            </div>
            <div class="sh-card-body">

                <!-- Mode -->
                <div class="sh-field-group">
                    <label class="sh-field-label">Export Mode</label>
                    <div class="sh-mode-cards" id="sh-mode-selector">
                        <?php
                        $modes = [
                            'full'  => [ 'icon' => '🌐', 'title' => 'Full Export',  'desc' => 'DB + WP Core + Theme + wp-content' ],
                            'db'    => [ 'icon' => '🗄️', 'title' => 'Database Only', 'desc' => 'MySQL dump with URL replacement' ],
                            'theme' => [ 'icon' => '🎨', 'title' => 'Theme Only',    'desc' => 'Active theme files only' ],
                        ];
                        foreach ( $modes as $slug => $mode ) :
                        ?>
                        <label class="sh-mode-card <?php echo $default_mode === $slug ? 'active' : ''; ?>">
                            <input type="radio" name="export_mode" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $default_mode, $slug ); ?> class="sh-mode-radio">
                            <span class="sh-mode-icon"><?php echo $mode['icon']; ?></span>
                            <span class="sh-mode-title"><?php echo esc_html( $mode['title'] ); ?></span>
                            <span class="sh-mode-desc"><?php echo esc_html( $mode['desc'] ); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Target URL -->
                <div class="sh-field-group">
                    <label class="sh-field-label">Target URL <span class="sh-field-hint">(optional — replaces current site URL in DB and files)</span></label>
                    <input type="url" id="sh-target-url" class="sh-input" placeholder="https://newsite.com" value="">
                </div>

                <!-- DB Credentials (full/db mode) -->
                <div class="sh-field-group sh-mode-field sh-mode-full sh-mode-db">
                    <label class="sh-field-label">Target DB Credentials <span class="sh-field-hint">(optional — updates wp-config.php)</span></label>
                    <div class="sh-field-row">
                        <input type="text"     id="sh-db-name" class="sh-input" placeholder="Database name">
                        <input type="text"     id="sh-db-user" class="sh-input" placeholder="DB user">
                        <input type="password" id="sh-db-pass" class="sh-input" placeholder="DB password">
                        <input type="text"     id="sh-db-prefix" class="sh-input" placeholder="Table prefix (e.g. wp_)">
                    </div>
                </div>

                <!-- Full mode options -->
                <div class="sh-field-group sh-mode-field sh-mode-full">
                    <label class="sh-field-label">Include</label>
                    <div class="sh-checkbox-group">
                        <label class="sh-checkbox-item">
                            <input type="checkbox" id="sh-inc-wp-content" checked>
                            <span>wp-content</span>
                        </label>
                        <label class="sh-checkbox-item">
                            <input type="checkbox" id="sh-inc-wp-admin">
                            <span>wp-admin</span>
                        </label>
                        <label class="sh-checkbox-item">
                            <input type="checkbox" id="sh-inc-wp-includes">
                            <span>wp-includes</span>
                        </label>
                        <label class="sh-checkbox-item">
                            <input type="checkbox" id="sh-inc-root-files">
                            <span>Root files</span>
                        </label>
                        <label class="sh-checkbox-item">
                            <input type="checkbox" id="sh-inc-wp-config">
                            <span>wp-config.php</span>
                        </label>
                    </div>
                </div>

                <!-- Start Button -->
                <div class="sh-field-group" style="margin-top:24px">
                    <button type="button" id="sh-start-export" class="sh-btn sh-btn-primary sh-btn-lg">
                        <span class="sh-btn-icon">▶</span> Start Export
                    </button>
                </div>

            </div>
        </div>

        <!-- Progress Panel (hidden initially) -->
        <div class="sh-card" id="sh-export-progress" style="display:none">
            <div class="sh-card-header">
                <strong id="sh-progress-title">Exporting...</strong>
                <button type="button" id="sh-cancel-export" class="sh-btn sh-btn-sm" style="margin-left:auto;color:#ef4444;border-color:#fca5a5">✕ Cancel</button>
            </div>
            <div class="sh-card-body">

                <!-- Steps -->
                <div class="sh-steps" id="sh-steps-list"></div>

                <!-- Progress Bar -->
                <div class="sh-progress-wrap">
                    <div class="sh-progress-bar">
                        <div class="sh-progress-fill" id="sh-progress-fill"></div>
                    </div>
                    <div class="sh-progress-label" id="sh-progress-label">0%</div>
                </div>

                <!-- Log Stream -->
                <div class="sh-log-stream" id="sh-log-stream"></div>

                <!-- Done Actions -->
                <div id="sh-export-done" style="display:none">
                    <div class="sh-notice sh-notice-success sh-inline">✓ Export completed successfully!</div>
                    <div style="display:flex;gap:10px;margin-top:12px">
                        <button type="button" id="sh-download-btn" class="sh-btn sh-btn-primary">⬇ Download ZIP</button>
                        <button type="button" id="sh-new-export-btn" class="sh-btn">+ New Export</button>
                    </div>
                </div>

            </div>
        </div>

        </div>
        <div class="sh-sidebar">
            <?php self::renderExportSidebar(); ?>
        </div>
        </div>
        <?php
    }

    // ─── History Tab ──────────────────────────────────────────────────────────

    private static function renderHistoryTab( array $history, string $nonce ): void
    {
        ?>
        <div class="sh-layout">
        <div class="sh-main">

        <?php if ( empty( $history ) ) : ?>
            <div class="sh-empty-box">
                <p style="margin:0 0 4px;font-weight:500;color:#50575e">No exports yet</p>
                <p style="margin:0;font-size:12px;color:#9ca3af">Create your first export from the Export tab.</p>
            </div>
        <?php else : ?>
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden">
                <table style="width:100%;border-collapse:collapse">
                    <thead><tr>
                        <?php foreach ( [ 'File', 'Mode', 'Size', 'Date', 'Actions' ] as $h ) : ?>
                        <th style="padding:10px 18px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #f3f4f6"><?php echo $h; ?></th>
                        <?php endforeach; ?>
                    </tr></thead>
                    <tbody>
                    <?php foreach ( array_reverse( $history ) as $item ) :
                        $mode_colors = [ 'full' => '#3b82f6', 'db' => '#8b5cf6', 'theme' => '#22c55e' ];
                        $col = $mode_colors[ $item['mode'] ?? 'full' ] ?? '#6b7280';
                        $dl_url = add_query_arg( [
                            'action'    => ThemeExporter::getAjaxDownload(),
                            'export_id' => $item['id'],
                            '_wpnonce'  => $nonce,
                        ], admin_url( 'admin-ajax.php' ) );
                    ?>
                    <tr style="border-bottom:1px solid #f9fafb" data-export-id="<?php echo esc_attr( $item['id'] ); ?>">
                        <td style="padding:10px 18px;font-family:Consolas,monospace;font-size:12px;color:#374151">
                            <?php echo esc_html( $item['name'] ?? '' ); ?>
                        </td>
                        <td style="padding:10px 18px">
                            <span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;background:<?php echo $col; ?>22;color:<?php echo $col; ?>">
                                <?php echo esc_html( strtoupper( $item['mode'] ?? 'full' ) ); ?>
                            </span>
                        </td>
                        <td style="padding:10px 18px;color:#6b7280;font-size:13px"><?php echo esc_html( $item['size'] ?? '?' ); ?></td>
                        <td style="padding:10px 18px;color:#6b7280;font-size:13px"><?php echo esc_html( $item['date'] ?? '' ); ?></td>
                        <td style="padding:10px 18px">
                            <div style="display:flex;gap:6px">
                                <a href="<?php echo esc_url( $dl_url ); ?>" class="sh-btn sh-btn-primary sh-btn-sm">⬇ Download</a>
                                <button type="button" class="sh-btn sh-btn-sm sh-delete-export"
                                        style="background:#fee2e2;color:#ef4444;border-color:#fca5a5"
                                        data-id="<?php echo esc_attr( $item['id'] ); ?>"
                                        onclick="return confirm('Delete this export file?')">
                                    🗑 Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        </div>
        <div class="sh-sidebar">
            <?php self::renderExportSidebar(); ?>
        </div>
        </div>
        <?php
    }

    // ─── Settings Tab ─────────────────────────────────────────────────────────

    private static function renderSettingsTab( array $settings, string $nonce, string $ajax_url ): void
    {
        $export_dir = ThemeExporter::getExportDir();
        $dir_exists = is_dir( $export_dir );
        $dir_size   = $dir_exists ? self::getDirSize( $export_dir ) : 0;
        ?>
        <div class="sh-layout">
        <div class="sh-main">

        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-bottom:16px">
            <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6"><strong>Export Settings</strong></div>
            <div style="padding:18px">

                <div class="sh-field-group">
                    <label class="sh-field-label">Max History Count</label>
                    <input type="number" id="sh-max-history" class="sh-input" style="max-width:120px"
                           value="<?php echo (int) $settings['max_history']; ?>" min="1" max="50">
                    <p class="sh-field-hint">How many export files to keep. Oldest are deleted automatically.</p>
                </div>

                <div class="sh-field-group">
                    <label class="sh-field-label">Default Export Mode</label>
                    <select id="sh-default-mode" class="sh-select" style="max-width:200px">
                        <?php foreach ( [ 'full' => 'Full Export', 'db' => 'Database Only', 'theme' => 'Theme Only' ] as $val => $label ) : ?>
                            <option value="<?php echo $val; ?>" <?php selected( $settings['default_mode'], $val ); ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sh-field-group">
                    <label class="sh-field-label">Memory Limit</label>
                    <input type="text" id="sh-memory-limit" class="sh-input" style="max-width:120px"
                           value="<?php echo esc_attr( $settings['memory_limit'] ); ?>" placeholder="2048M">
                    <p class="sh-field-hint">PHP memory limit during export. Default: 2048M</p>
                </div>

                <div class="sh-field-group">
                    <label class="sh-field-label">Scheduled Export</label>
                    <label class="sh-toggle">
                        <input type="checkbox" id="sh-scheduled-export" <?php checked( $settings['scheduled_export'] ); ?>>
                        <span class="sh-toggle-slider"></span>
                    </label>
                    <p class="sh-field-hint">Automatically create exports via WP Cron.</p>
                </div>

                <div class="sh-field-group" id="sh-schedule-freq-wrap" style="<?php echo $settings['scheduled_export'] ? '' : 'display:none'; ?>">
                    <label class="sh-field-label">Schedule Frequency</label>
                    <select id="sh-schedule-freq" class="sh-select" style="max-width:200px">
                        <?php foreach ( [ 'daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly' ] as $val => $label ) : ?>
                            <option value="<?php echo $val; ?>" <?php selected( $settings['schedule_freq'], $val ); ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="margin-top:20px">
                    <button type="button" id="sh-save-settings" class="sh-btn sh-btn-primary">Save Settings</button>
                </div>

            </div>
        </div>

        <!-- Export Directory Info -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden">
            <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6"><strong>Export Directory</strong></div>
            <div style="padding:18px">
                <table style="width:100%;border-collapse:collapse">
                    <tr style="border-bottom:1px solid #f9fafb">
                        <td style="padding:8px 0;color:#6b7280;font-size:13px;width:140px">Path</td>
                        <td style="padding:8px 0;font-family:Consolas,monospace;font-size:12px"><?php echo esc_html( $export_dir ); ?></td>
                    </tr>
                    <tr style="border-bottom:1px solid #f9fafb">
                        <td style="padding:8px 0;color:#6b7280;font-size:13px">Status</td>
                        <td style="padding:8px 0">
                            <?php if ( $dir_exists ) : ?>
                                <span style="color:#22c55e;font-weight:600">✓ Exists</span>
                            <?php else : ?>
                                <span style="color:#f0b429;font-weight:600">⚠ Not created yet</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr style="border-bottom:1px solid #f9fafb">
                        <td style="padding:8px 0;color:#6b7280;font-size:13px">Web Access</td>
                        <td style="padding:8px 0">
                            <?php if ( file_exists( $export_dir . '/.htaccess' ) ) : ?>
                                <span style="color:#22c55e;font-weight:600">✓ Blocked (.htaccess)</span>
                            <?php else : ?>
                                <span style="color:#ef4444;font-weight:600">✗ Not protected</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 0;color:#6b7280;font-size:13px">Total Size</td>
                        <td style="padding:8px 0;font-size:13px"><?php echo $dir_size > 0 ? size_format( $dir_size, 2 ) : '—'; ?></td>
                    </tr>
                </table>
                <div style="margin-top:12px">
                    <button type="button" class="sh-btn sh-btn-sm" onclick="if(confirm('Initialize export directory and security files?')){window.location.href='?page=sh-theme-export&tab=settings&sh_init_dir=1&_wpnonce=<?php echo esc_attr( wp_create_nonce( 'sh_init_export_dir' ) ); ?>'}">
                        🔒 Initialize Directory
                    </button>
                </div>
            </div>
        </div>

        </div>
        <div class="sh-sidebar">
            <?php self::renderExportSidebar(); ?>
        </div>
        </div>
        <?php

        // Init dir action
        if ( isset( $_GET['sh_init_dir'] ) && check_admin_referer( 'sh_init_export_dir' ) ) {
            ThemeExporter::ensureExportDir();
            echo '<div class="sh-notice sh-notice-success sh-inline" style="margin-top:12px">✓ Export directory initialized.</div>';
        }
    }

    // ─── Sidebar ──────────────────────────────────────────────────────────────

    private static function renderExportSidebar(): void
    {
        $history = ThemeExporter::getHistory();
        ?>
        <div class="sh-sidebar-box">
            <h3>Export Modes</h3>
            <ul style="margin:0;padding:0;list-style:none;font-size:12px;color:#6b7280">
                <li style="padding:5px 0;border-bottom:1px solid #f3f4f6"><strong>Full</strong> — DB + Core + wp-content</li>
                <li style="padding:5px 0;border-bottom:1px solid #f3f4f6"><strong>Database</strong> — MySQL dump only</li>
                <li style="padding:5px 0"><strong>Theme</strong> — Active theme files only</li>
            </ul>
        </div>
        <div class="sh-sidebar-box" style="margin-top:12px">
            <h3>Security</h3>
            <ul style="margin:0;padding:0;list-style:none;font-size:12px;color:#6b7280">
                <li style="padding:4px 0">📁 Exports stored in theme directory</li>
                <li style="padding:4px 0">🔒 .htaccess blocks web access</li>
                <li style="padding:4px 0">⬇ Downloads via PHP stream</li>
                <li style="padding:4px 0">🔑 Admin-only access</li>
            </ul>
        </div>
        <?php if ( ! empty( $history ) ) : ?>
        <div class="sh-sidebar-box" style="margin-top:12px">
            <h3>Recent Exports</h3>
            <ul style="margin:0;padding:0;list-style:none;font-size:12px;color:#6b7280">
                <?php foreach ( array_slice( array_reverse( $history ), 0, 3 ) as $item ) : ?>
                <li style="padding:4px 0;border-bottom:1px solid #f3f4f6">
                    <span style="font-weight:600"><?php echo esc_html( strtoupper( $item['mode'] ?? '' ) ); ?></span>
                    — <?php echo esc_html( $item['size'] ?? '' ); ?><br>
                    <span style="color:#9ca3af"><?php echo esc_html( $item['date'] ?? '' ); ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        <?php
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private static function getDirSize( string $dir ): int
    {
        $size = 0;
        if ( ! is_dir( $dir ) ) return 0;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS )
        );
        foreach ( $it as $file ) {
            if ( $file->isFile() ) $size += $file->getSize();
        }
        return $size;
    }
}
