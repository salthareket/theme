<?php
namespace SaltHareket\DownloadLog\Admin;

use SaltHareket\DownloadLog\DownloadRules;
use SaltHareket\DownloadLog\Concerns\HandlesLog;

/**
 * DownloadLogAdmin
 * Admin UI — 4 tab: Log | Rules | Settings | Analytics
 * Cron rapor sistemi — haftalık/aylık/özel periyod XLSX/CSV mail
 *
 * @version 1.0.0
 */
class DownloadLogAdmin {

    use HandlesLog;

    const MENU_SLUG = 'salt-download-log';
    const NONCE_KEY = 'sh_download_admin';

    // ─── MENU ────────────────────────────────────────────

    public static function addMenuPage(): void {
        add_submenu_page(
            'theme-settings',
            '📥 Download Log',
            '📥 Download Log',
            'manage_options',
            self::MENU_SLUG,
            [ self::class, 'renderPage' ]
        );
    }

    public static function hideNotices(): void {
        if ( ( $_GET['page'] ?? '' ) !== self::MENU_SLUG ) return;
        echo '<style>body .notice:not(.sh-inline),body .updated:not(.sh-inline),body .error:not(.sh-inline){display:none!important;}</style>';
    }

    public static function enqueueAssets( string $hook ): void {
        if ( strpos( $hook, self::MENU_SLUG ) === false ) return;
        $css_path = dirname( __DIR__, 2 ) . '/sh-admin.css';
        $css_url  = trailingslashit( SH_INCLUDES_URL ) . 'apps/sh-admin.css';
        if ( file_exists( $css_path ) ) {
            wp_enqueue_style( 'sh-admin-kit', $css_url, [], filemtime( $css_path ) );
        }
        add_action( 'admin_head', function() {
            echo '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>';
        }, 1 );
        $js_path = __DIR__ . '/download-log-admin.js';
        $js_url  = trailingslashit( SH_INCLUDES_URL ) . 'apps/download-log/Admin/download-log-admin.js';
        if ( file_exists( $js_path ) ) {
            wp_enqueue_script( 'sh-download-log-admin', $js_url, [ 'jquery' ], filemtime( $js_path ), true );
            wp_localize_script( 'sh-download-log-admin', 'shDlAdmin', [
                'nonce'      => wp_create_nonce( self::NONCE_KEY ),
                'ajax'       => admin_url( 'admin-ajax.php' ),
                'post_types' => DownloadRules::getPostTypes(),
                'taxonomies' => DownloadRules::getTaxonomies(),
                'cf7_forms'  => DownloadRules::getCF7Forms(),
            ] );
        }
    }

    // ─── RENDER PAGE ─────────────────────────────────────

    public static function renderPage(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        $tab   = sanitize_key( $_GET['tab'] ?? 'log' );
        $nonce = wp_create_nonce( self::NONCE_KEY );
        echo '<div class="wrap sh-wrap" id="sh-download-log-page">';
        echo '<div class="sh-toolbar"><h1>&#128229; Download Log</h1>';
        echo '<div class="sh-toolbar-right">';
        foreach ( [ 'log' => '&#128229; Log', 'rules' => '&#9881; Rules', 'settings' => '&#9881; Settings', 'analytics' => '&#128200; Analytics' ] as $t => $l ) {
            echo '<a href="?page=' . self::MENU_SLUG . '&tab=' . $t . '" class="sh-tab-btn ' . ( $tab === $t ? 'active' : '' ) . '">' . $l . '</a>';
        }
        echo '</div></div>';
        if ( isset( $_GET['saved'] ) ) echo '<div class="sh-notice sh-notice-success sh-inline">&#10003; Saved.</div>';
        switch ( $tab ) {
            case 'rules':     self::renderRulesTab( $nonce );     break;
            case 'settings':  self::renderSettingsTab( $nonce );  break;
            case 'analytics': self::renderAnalyticsTab();         break;
            default:          self::renderLogTab( $nonce );       break;
        }
        echo '<div id="sh-toast"></div></div>';
    }

    // ─── LOG TAB ─────────────────────────────────────────

    private static function renderLogTab( string $nonce ): void {
        $per_page  = 50;
        $page      = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
        $date_from = sanitize_text_field( $_GET['date_from'] ?? '' );
        $date_to   = sanitize_text_field( $_GET['date_to'] ?? '' );
        $mode_f    = sanitize_key( $_GET['mode_f'] ?? '' );
        $search    = sanitize_text_field( $_GET['search'] ?? '' );

        $result = self::getLogs( compact( 'per_page', 'page', 'date_from', 'date_to', 'search' ) + [ 'mode' => $mode_f ] );
        $items  = $result['items'];
        $total  = $result['total'];
        $pages  = (int) ceil( $total / $per_page );

        // Export URL
        $export_base = add_query_arg( [
            'action'    => 'sh_download_export',
            'date_from' => $date_from,
            'date_to'   => $date_to,
            'mode'      => $mode_f,
            '_wpnonce'  => wp_create_nonce( 'sh_download_export' ),
        ], admin_url( 'admin-post.php' ) );
        ?>
        <div class="sh-layout">
        <div class="sh-main">

        <!-- Filters -->
        <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:16px;">
            <input type="hidden" name="page" value="<?php echo self::MENU_SLUG; ?>">
            <input type="hidden" name="tab" value="log">
            <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" class="sh-input" style="width:140px;" placeholder="From">
            <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" class="sh-input" style="width:140px;" placeholder="To">
            <select name="mode_f" class="sh-select" style="width:160px;">
                <option value="">All modes</option>
                <option value="public" <?php selected($mode_f,'public'); ?>>Public</option>
                <option value="login_required" <?php selected($mode_f,'login_required'); ?>>Login Required</option>
                <option value="lead_capture" <?php selected($mode_f,'lead_capture'); ?>>Lead Capture</option>
            </select>
            <input type="text" name="search" value="<?php echo esc_attr($search); ?>" class="sh-input" style="width:180px;" placeholder="Search file / guest ID">
            <button type="submit" class="sh-btn sh-btn-primary">Filter</button>
            <a href="<?php echo esc_url( add_query_arg( 'format', 'xlsx', $export_base ) ); ?>" class="sh-btn sh-btn-secondary">&#8595; XLSX</a>
            <a href="<?php echo esc_url( add_query_arg( 'format', 'csv', $export_base ) ); ?>" class="sh-btn sh-btn-secondary">&#8595; CSV</a>
        </form>

        <!-- Table -->
        <div class="sh-table-wrap">
            <table class="sh-table">
                <thead><tr>
                    <th>#</th><th>File</th><th>User / Guest</th><th>Type</th><th>Mode</th>
                    <th>Info</th><th>Source</th><th>IP</th><th>Lang</th><th>Date</th>
                </tr></thead>
                <tbody>
                <?php foreach ( $items as $row ) :
                    // ── User / Guest display ──
                    $user_obj     = $row->user_id ? get_userdata( $row->user_id ) : null;
                    $is_logged    = (bool) $row->user_id && $user_obj;
                    $user_display = '';
                    $type_badge   = '';

                    if ( $is_logged ) {
                        $user_display = '<strong>' . esc_html( $user_obj->display_name ) . '</strong>';
                        // Role badge
                        $roles      = (array) $user_obj->roles;
                        $role_label = ! empty( $roles ) ? ucfirst( $roles[0] ) : 'User';
                        $type_badge = '<span class="sh-type" style="background:#2271b118;color:#2271b1;">' . esc_html( $role_label ) . '</span>';
                    } elseif ( $row->guest_id ) {
                        $user_display = '<span style="color:#9ca3af;font-size:11px;" title="' . esc_attr( $row->guest_id ) . '">' . esc_html( substr( $row->guest_id, 0, 16 ) ) . '...</span>';
                        $type_badge   = '<span class="sh-type" style="background:#f59e0b18;color:#f59e0b;">Guest</span>';
                    }

                    // ── Info column (logged + guest) ──
                    $info_name  = '';
                    $info_email = '';
                    $info_phone = '';
                    $info_extra = [];

                    if ( $is_logged ) {
                        $info_name  = $user_obj->display_name;
                        $info_email = $user_obj->user_email;
                        $info_phone = get_user_meta( $row->user_id, 'phone', true ) ?: '';
                        $info_extra = array_filter( [
                            'role'       => implode( ', ', (array) $user_obj->roles ),
                            'registered' => wp_date( 'd M Y', strtotime( $user_obj->user_registered ) ),
                        ] );
                    } else {
                        $info_name  = $row->guest_name  ?? '';
                        $info_email = $row->guest_email ?? '';
                        $info_phone = $row->guest_phone ?? '';
                        $guest_meta = ! empty( $row->guest_meta ) ? json_decode( $row->guest_meta, true ) : [];
                        if ( is_array( $guest_meta ) ) {
                            $info_extra = $guest_meta;
                        }
                    }

                    $mode_colors = [ 'public' => '#10b981', 'login_required' => '#2271b1', 'lead_capture' => '#f59e0b' ];
                    $mc = $mode_colors[ $row->mode ] ?? '#6b7280';
                ?>
                <tr>
                    <td style="color:#9ca3af;font-size:11px;"><?php echo $row->id; ?></td>
                    <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <?php
                        $dl_url = wp_get_attachment_url( (int) $row->file_id );
                        if ( $dl_url ) :
                        ?><a href="<?php echo esc_url( $dl_url ); ?>" target="_blank" title="<?php echo esc_attr( $row->file_name ); ?>"><?php echo esc_html( $row->file_name ); ?></a><?php
                        else :
                            echo esc_html( $row->file_name );
                        endif;
                        ?>
                    </td>
                    <td><?php echo $user_display; ?></td>
                    <td><?php echo $type_badge; ?></td>
                    <td><span class="sh-type" style="background:<?php echo esc_attr($mc); ?>18;color:<?php echo esc_attr($mc); ?>;"><?php echo esc_html( $row->mode ); ?></span></td>
                    <td style="font-size:11px;">
                        <?php
                        if ( $info_name )  echo esc_html( $info_name ) . '<br>';
                        if ( $info_email ) echo '<a href="mailto:' . esc_attr( $info_email ) . '">' . esc_html( $info_email ) . '</a>';
                        if ( $info_phone ) echo '<br><span style="color:#9ca3af">' . esc_html( $info_phone ) . '</span>';

                        if ( $info_name || $info_email || $info_phone || ! empty( $info_extra ) ) {
                            $more_data = array_filter( [
                                'name'  => $info_name,
                                'email' => $info_email,
                                'phone' => $info_phone,
                            ] );
                            if ( ! empty( $info_extra ) ) {
                                $more_data = array_merge( $more_data, $info_extra );
                            }
                            echo ' <button type="button" class="sh-btn sh-btn-secondary sh-dl-lead-more" '
                               . 'data-lead="' . esc_attr( wp_json_encode( $more_data, JSON_UNESCAPED_UNICODE ) ) . '" '
                               . 'data-guest="' . esc_attr( $row->guest_id ?? '' ) . '" '
                               . 'style="padding:1px 6px;font-size:10px;margin-left:4px;">More</button>';
                        }
                        ?>
                    </td>
                    <td style="font-size:11px;"><?php echo $row->source_post ? esc_html( get_the_title( $row->source_post ) ) : '—'; ?></td>
                    <td style="font-size:11px;color:#9ca3af;"><?php echo esc_html( $row->ip ); ?></td>
                    <td style="font-size:11px;"><?php echo esc_html( $row->language ); ?></td>
                    <td style="font-size:11px;color:#9ca3af;white-space:nowrap;"><?php echo esc_html( wp_date( 'd M Y H:i', strtotime( $row->created_at ) ) ); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if ( empty( $items ) ) : ?>
                    <tr><td colspan="9" style="text-align:center;color:#9ca3af;padding:24px;">No downloads yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ( $pages > 1 ) : ?>
        <div class="sh-pag" style="margin-top:12px;">
            <?php for ( $i = 1; $i <= $pages; $i++ ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'paged', $i ) ); ?>"
                   class="sh-pg-btn <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

        </div><!-- .sh-main -->

        <div class="sh-sidebar">
            <div class="sh-card">
                <h3 style="margin:0 0 8px;font-size:13px;font-weight:600;">Total</h3>
                <div style="font-size:28px;font-weight:700;color:#2271b1;"><?php echo number_format_i18n( $total ); ?></div>
                <div style="font-size:11px;color:#9ca3af;">downloads in filter</div>
            </div>
        </div>
        </div>
        <?php
    }

    // ─── RULES TAB ───────────────────────────────────────

    private static function renderRulesTab( string $nonce ): void {
        $rules     = DownloadRules::getRules();
        $cf7_forms = DownloadRules::getCF7Forms();
        $post_types = DownloadRules::getPostTypes();
        $taxonomies = DownloadRules::getTaxonomies();
        ?>
        <div class="sh-layout">
        <div class="sh-main">

        <div class="sh-filter-bar">
            <span class="sh-count-label"><?php echo count($rules); ?> rule<?php echo count($rules) !== 1 ? 's' : ''; ?></span>
            <button type="button" class="sh-btn sh-btn-primary" id="sh-dl-add-rule" style="margin-left:auto;">+ Add Rule</button>
        </div>

        <div id="sh-dl-rules-list">
        <?php foreach ( $rules as $rule ) : self::renderRuleCard( $rule, $cf7_forms, $post_types, $taxonomies ); endforeach; ?>
        <?php if ( empty( $rules ) ) : ?>
            <div class="sh-empty-box">
                <p style="margin:0 0 4px;font-weight:500;">No rules yet</p>
                <p style="font-size:12px;color:#9ca3af;margin:0 0 16px;">Rules define download access per post, term, post type or globally.</p>
                <button type="button" class="sh-btn sh-btn-primary" id="sh-dl-add-rule2">+ Add Rule</button>
            </div>
        <?php endif; ?>
        </div>

        </div>
        <div class="sh-sidebar">

            <div class="sh-card">
                <h3 style="margin:0 0 10px;font-size:13px;font-weight:600;">Priority Order</h3>
                <div style="font-size:12px;color:#6b7280;line-height:2;">
                    <span class="sh-type" style="background:#2271b118;color:#2271b1;">post</span> highest<br>
                    <span class="sh-type" style="background:#f59e0b18;color:#f59e0b;">term</span><br>
                    <span class="sh-type" style="background:#10b98118;color:#10b981;">post_type</span><br>
                    <span class="sh-type" style="background:#6b728018;color:#6b7280;">global</span> lowest
                </div>
            </div>

            <div class="sh-card" style="margin-top:12px;">
                <h3 style="margin:0 0 10px;font-size:13px;font-weight:600;">Modes</h3>
                <div style="font-size:12px;color:#6b7280;line-height:2.2;">
                    <span class="sh-type" style="background:#10b98118;color:#10b981;">public</span> — log only<br>
                    <span class="sh-type" style="background:#2271b118;color:#2271b1;">login_required</span> — must be logged in<br>
                    <span class="sh-type" style="background:#f59e0b18;color:#f59e0b;">lead_capture</span> — CF7 form, then cookie
                </div>
            </div>

            <div class="sh-card" style="margin-top:12px;">
                <h3 style="margin:0 0 12px;font-size:13px;font-weight:600;">&#128196; Twig Usage</h3>
                <p style="font-size:11px;color:#9ca3af;margin:0 0 8px;">Rule'dan otomatik mod — tıkla kopyala</p>

                <?php
                $twig_examples = [
                    [
                        'label' => 'Auto (rule\'dan gelir)',
                        'code'  => "{{ sh_download(post.brochure_file) }}",
                    ],
                    [
                        'label' => 'Login required override',
                        'code'  => "{{ sh_download(post.brochure_file, {\n  'mode': 'login_required',\n  'label': 'PDF İndir'\n}) }}",
                    ],
                    [
                        'label' => 'Lead capture override',
                        'code'  => "{{ sh_download(post.brochure_file, {\n  'mode': 'lead_capture',\n  'label': 'Ücretsiz İndir'\n}) }}",
                    ],
                    [
                        'label' => 'Public override + custom class',
                        'code'  => "{{ sh_download(post.brochure_file, {\n  'mode': 'public',\n  'label': 'İndir',\n  'class': 'btn btn-primary'\n}) }}",
                    ],
                    [
                        'label' => 'Download count',
                        'code'  => "{{ sh_download_count(post.brochure_file) }}",
                    ],
                ];
                foreach ( $twig_examples as $ex ) : ?>
                <div style="margin-bottom:10px;">
                    <div style="font-size:10px;font-weight:600;color:#9ca3af;text-transform:uppercase;margin-bottom:3px;"><?php echo esc_html( $ex['label'] ); ?></div>
                    <div class="sh-twig-wrap" style="display:flex;align-items:flex-start;gap:4px;">
                        <code class="sh-twig-code sh-dl-copy-code" style="background:#f0f0f1;padding:5px 8px;border-radius:4px;font-size:10px;font-family:Consolas,monospace;flex:1;cursor:pointer;white-space:pre;display:block;line-height:1.5;"><?php echo esc_html( $ex['code'] ); ?></code>
                        <button type="button" class="sh-copy-btn sh-icon-btn sh-dl-copy-btn" title="Copy" style="color:#2271b1;flex-shrink:0;margin-top:2px;">&#128203;</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="sh-card" style="margin-top:12px;">
                <h3 style="margin:0 0 12px;font-size:13px;font-weight:600;">&#128196; PHP Usage</h3>
                <?php
                $php_examples = [
                    [
                        'label' => 'Auto (rule\'dan gelir)',
                        'code'  => "sh_download(get_field('brochure_file'));",
                    ],
                    [
                        'label' => 'Tüm options ile',
                        'code'  => "sh_download(\n  get_field('brochure_file'),\n  [\n    'mode'  => 'lead_capture',\n    'label' => 'PDF İndir',\n    'class' => 'btn btn-primary',\n    'icon'  => 'fa-file-pdf',\n  ]\n);",
                    ],
                    [
                        'label' => 'Download count',
                        'code'  => "sh_download_count(\$attachment_id);",
                    ],
                ];
                foreach ( $php_examples as $ex ) : ?>
                <div style="margin-bottom:10px;">
                    <div style="font-size:10px;font-weight:600;color:#9ca3af;text-transform:uppercase;margin-bottom:3px;"><?php echo esc_html( $ex['label'] ); ?></div>
                    <div class="sh-twig-wrap" style="display:flex;align-items:flex-start;gap:4px;">
                        <code class="sh-twig-code sh-dl-copy-code" style="background:#f0f0f1;padding:5px 8px;border-radius:4px;font-size:10px;font-family:Consolas,monospace;flex:1;cursor:pointer;white-space:pre;display:block;line-height:1.5;"><?php echo esc_html( $ex['code'] ); ?></code>
                        <button type="button" class="sh-copy-btn sh-icon-btn sh-dl-copy-btn" title="Copy" style="color:#2271b1;flex-shrink:0;margin-top:2px;">&#128203;</button>
                    </div>
                </div>
                <?php endforeach; ?>

                <div style="margin-top:12px;padding-top:12px;border-top:1px solid #f0f0f1;">
                    <div style="font-size:10px;font-weight:600;color:#9ca3af;text-transform:uppercase;margin-bottom:6px;">Options Reference</div>
                    <table style="width:100%;font-size:11px;border-collapse:collapse;">
                        <?php foreach ( [
                            [ 'mode',        'public | login_required | lead_capture' ],
                            [ 'label',       'Buton metni (string)' ],
                            [ 'class',       'Ekstra CSS class' ],
                            [ 'icon',        'FA icon class (fa-download)' ],
                            [ 'show_size',   'true — dosya boyutunu göster' ],
                            [ 'source_post', 'Rule resolver için post ID' ],
                        ] as [$opt, $desc] ) : ?>
                        <tr>
                            <td style="padding:3px 6px 3px 0;font-family:Consolas,monospace;color:#2271b1;white-space:nowrap;"><?php echo esc_html($opt); ?></td>
                            <td style="padding:3px 0;color:#6b7280;"><?php echo esc_html($desc); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>

        </div>
        </div>
        <?php
    }

    private static function renderRuleCard( array $rule, array $cf7_forms, array $post_types, array $taxonomies ): void {
        $id      = $rule['id'] ?? '';
        $mode    = $rule['mode'] ?? 'public';
        $scope   = $rule['scope'] ?? 'global';
        $form_id = (int) ( $rule['form_id'] ?? 0 );

        $mode_colors  = [ 'public' => '#10b981', 'login_required' => '#2271b1', 'lead_capture' => '#f59e0b' ];
        $scope_colors = [ 'post' => '#2271b1', 'term' => '#f59e0b', 'post_type' => '#10b981', 'global' => '#6b7280' ];
        $mc = $mode_colors[$mode]   ?? '#6b7280';
        $sc = $scope_colors[$scope] ?? '#6b7280';

        $scope_label = $scope;
        if ( $scope === 'post'      && ! empty( $rule['post_title'] ) ) $scope_label = $rule['post_title'];
        elseif ( $scope === 'term'  && ! empty( $rule['term_name']  ) ) $scope_label = $rule['term_name'] . ' (' . ( $rule['tax'] ?? '' ) . ')';
        elseif ( $scope === 'post_type' && ! empty( $rule['post_type'] ) ) $scope_label = $rule['post_type'];
        ?>
        <div class="sh-rule-card" data-rule-id="<?php echo esc_attr($id); ?>">
            <div class="sh-rule-header">
                <div class="sh-rule-meta">
                    <span class="sh-rule-meta-inner">
                        <span class="sh-type" style="background:<?php echo esc_attr($mc); ?>18;color:<?php echo esc_attr($mc); ?>;"><?php echo esc_html($mode); ?></span>
                        <span class="sh-type" style="background:<?php echo esc_attr($sc); ?>18;color:<?php echo esc_attr($sc); ?>;"><?php echo esc_html($scope); ?></span>
                        <strong><?php echo esc_html($scope_label); ?></strong>
                        <?php if ( $mode === 'lead_capture' && $form_id ) : ?>
                            <span class="sh-ch-badge"><?php echo esc_html( $cf7_forms[$form_id] ?? '#'.$form_id ); ?></span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="sh-rule-actions">
                    <div class="sh-rule-btns">
                        <button type="button" class="sh-rule-btn sh-rule-btn-edit sh-dl-rule-edit-btn" title="Edit"><span class="dashicons dashicons-edit"></span></button>
                        <button type="button" class="sh-rule-btn sh-rule-btn-delete sh-dl-rule-delete-btn" data-id="<?php echo esc_attr($id); ?>" title="Delete"><span class="dashicons dashicons-trash"></span></button>
                    </div>
                </div>
            </div>
            <div class="sh-rule-form" style="display:none;">
                <?php self::renderRuleForm( $rule, $cf7_forms, $post_types, $taxonomies ); ?>
            </div>
        </div>
        <?php
    }

    private static function renderRuleForm( array $rule, array $cf7_forms, array $post_types, array $taxonomies ): void {
        $mode    = $rule['mode']    ?? 'public';
        $scope   = $rule['scope']   ?? 'global';
        $form_id = (int) ( $rule['form_id'] ?? 0 );
        ?>
        <div class="sh-form-row">
            <div class="sh-form-col sh-form-col-sm">
                <label>Mode</label>
                <select class="sh-select sh-dl-rule-mode">
                    <option value="public" <?php selected($mode,'public'); ?>>Public</option>
                    <option value="login_required" <?php selected($mode,'login_required'); ?>>Login Required</option>
                    <option value="lead_capture" <?php selected($mode,'lead_capture'); ?>>Lead Capture</option>
                </select>
            </div>
            <div class="sh-form-col sh-form-col-sm sh-dl-form-wrap<?php echo $mode !== 'lead_capture' ? ' sh-hidden' : ''; ?>">
                <label>CF7 Form</label>
                <select class="sh-select sh-dl-rule-form-id">
                    <option value="">— Select Form —</option>
                    <?php foreach ( $cf7_forms as $fid => $ftitle ) : ?>
                        <option value="<?php echo esc_attr($fid); ?>" <?php selected($form_id,$fid); ?>><?php echo esc_html($ftitle); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sh-form-col sh-form-col-sm">
                <label>Scope</label>
                <select class="sh-select sh-dl-rule-scope">
                    <option value="global" <?php selected($scope,'global'); ?>>Global (all site)</option>
                    <option value="post_type" <?php selected($scope,'post_type'); ?>>Post Type</option>
                    <option value="term" <?php selected($scope,'term'); ?>>Term / Taxonomy</option>
                    <option value="post" <?php selected($scope,'post'); ?>>Specific Post</option>
                </select>
            </div>
            <div class="sh-form-col sh-dl-scope-post_type<?php echo $scope !== 'post_type' ? ' sh-hidden' : ''; ?>">
                <label>Post Type</label>
                <select class="sh-select sh-dl-rule-post-type">
                    <option value="">— Select —</option>
                    <?php foreach ( $post_types as $slug => $label ) : ?>
                        <option value="<?php echo esc_attr($slug); ?>" <?php selected($rule['post_type']??'',$slug); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sh-form-col sh-dl-scope-term<?php echo $scope !== 'term' ? ' sh-hidden' : ''; ?>">
                <label>Taxonomy</label>
                <select class="sh-select sh-dl-rule-taxonomy">
                    <option value="">— Select —</option>
                    <?php foreach ( $taxonomies as $slug => $label ) : ?>
                        <option value="<?php echo esc_attr($slug); ?>" <?php selected($rule['tax']??'',$slug); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sh-form-col sh-dl-scope-term<?php echo $scope !== 'term' ? ' sh-hidden' : ''; ?>">
                <label>Term</label>
                <select class="sh-select sh-dl-rule-term-select">
                    <option value="">— Select Taxonomy First —</option>
                </select>
                <input type="hidden" class="sh-dl-rule-term-id" value="<?php echo esc_attr($rule['term_id']??''); ?>">
            </div>
            <div class="sh-form-col sh-dl-scope-post<?php echo $scope !== 'post' ? ' sh-hidden' : ''; ?>">
                <label>Post</label>
                <div style="position:relative;">
                    <input type="text" class="sh-input sh-dl-rule-post-search" placeholder="Type to search..." value="<?php echo esc_attr($rule['post_title']??''); ?>">
                    <input type="hidden" class="sh-dl-rule-post-id" value="<?php echo esc_attr($rule['post_id']??''); ?>">
                    <div class="sh-dl-autocomplete-results" style="display:none;position:absolute;top:100%;left:0;background:#fff;border:1px solid #ddd;border-radius:4px;z-index:9999;max-height:200px;overflow-y:auto;min-width:220px;box-shadow:0 4px 12px rgba(0,0,0,.1);"></div>
                </div>
            </div>
        </div>
        <div class="sh-form-footer">
            <button type="button" class="sh-btn sh-btn-primary sh-dl-rule-save-btn" data-id="<?php echo esc_attr($rule['id']??''); ?>">Save</button>
            <button type="button" class="sh-btn sh-btn-ghost sh-dl-rule-cancel-btn">Cancel</button>
        </div>
        <?php
    }

    // ─── SETTINGS TAB ────────────────────────────────────

    private static function renderSettingsTab( string $nonce ): void {
        $recipients    = get_option( 'sh_download_report_recipients', '' );
        $schedule      = get_option( 'sh_download_report_schedule', 'weekly' );
        $mail_limit    = (int) get_option( 'sh_download_report_mail_limit', 50 );
        $log_ip        = (bool) get_option( 'sh_download_log_ip', true );
        $default_mode  = get_option( 'sh_download_default_mode', 'public' );
        $cookie_days   = (int) get_option( 'sh_download_lead_cookie_days', 365 );
        $rate_limit    = (bool) get_option( 'sh_download_rate_limit', false );
        $rate_max      = (int) get_option( 'sh_download_rate_limit_max', 20 );
        $field_map     = get_option( 'sh_download_field_map', [] );
        $cf7_forms     = \SaltHareket\DownloadLog\DownloadRules::getCF7Forms();

        // Next cron times
        $next_weekly  = wp_next_scheduled( 'sh_download_report_cron' );
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'sh_download_save_settings' ); ?>
        <input type="hidden" name="action" value="sh_download_save_settings">

        <div class="sh-layout">
        <div class="sh-main">

        <!-- Report Settings -->
        <div class="sh-table-wrap" style="margin-bottom:20px;">
            <div style="padding:14px 16px;border-bottom:1px solid #e5e7eb;">
                <h2 style="margin:0;font-size:15px;font-weight:600;">&#128229; Scheduled Reports</h2>
            </div>
            <div style="padding:20px;">
                <div class="sh-form-row">
                    <div class="sh-form-col">
                        <label>Recipients <span style="font-size:11px;color:#9ca3af;">(comma separated emails)</span></label>
                        <input type="text" name="report_recipients" value="<?php echo esc_attr($recipients); ?>" class="sh-input" placeholder="email@example.com, email2@example.com" style="width:100%;">
                    </div>
                    <div class="sh-form-col sh-form-col-sm">
                        <label>Schedule</label>
                        <select name="report_schedule" class="sh-select">
                            <option value="disabled" <?php selected($schedule,'disabled'); ?>>Disabled</option>
                            <option value="daily" <?php selected($schedule,'daily'); ?>>Daily</option>
                            <option value="weekly" <?php selected($schedule,'weekly'); ?>>Weekly (Monday 07:00)</option>
                            <option value="biweekly" <?php selected($schedule,'biweekly'); ?>>Every 2 Weeks</option>
                            <option value="monthly" <?php selected($schedule,'monthly'); ?>>Monthly (1st of month)</option>
                        </select>
                    </div>
                    <div class="sh-form-col sh-form-col-sm">
                        <label>Max rows in email body</label>
                        <input type="number" name="report_mail_limit" value="<?php echo esc_attr($mail_limit); ?>" class="sh-input" min="10" max="500" style="width:100px;">
                        <p style="font-size:11px;color:#9ca3af;margin:4px 0 0;">Full data always attached as XLSX</p>
                    </div>
                </div>
                <?php if ( $next_weekly ) : ?>
                <p style="font-size:12px;color:#6b7280;margin:8px 0 0;">
                    Next scheduled: <strong><?php echo esc_html( wp_date( 'd M Y H:i', $next_weekly ) ); ?></strong>
                    &nbsp;|&nbsp;
                    <a href="<?php echo esc_url( add_query_arg( [ 'page' => self::MENU_SLUG, 'tab' => 'settings', 'test_report' => '1', '_wpnonce' => wp_create_nonce('sh_dl_test_report') ] ) ); ?>">Send test now</a>
                </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- General Settings -->
        <div class="sh-table-wrap" style="margin-bottom:20px;">
            <div style="padding:14px 16px;border-bottom:1px solid #e5e7eb;">
                <h2 style="margin:0;font-size:15px;font-weight:600;">&#9881; General</h2>
            </div>
            <div style="padding:20px;">
                <div class="sh-form-row">
                    <div class="sh-form-col sh-form-col-sm">
                        <label>Default Mode <span style="font-size:11px;color:#9ca3af;">(no rule match)</span></label>
                        <select name="default_mode" class="sh-select">
                            <option value="public" <?php selected($default_mode,'public'); ?>>Public</option>
                            <option value="login_required" <?php selected($default_mode,'login_required'); ?>>Login Required</option>
                            <option value="lead_capture" <?php selected($default_mode,'lead_capture'); ?>>Lead Capture</option>
                        </select>
                    </div>
                    <div class="sh-form-col sh-form-col-sm">
                        <label>Lead Cookie Duration (days)</label>
                        <input type="number" name="lead_cookie_days" value="<?php echo esc_attr($cookie_days); ?>" class="sh-input" min="1" max="3650" style="width:100px;">
                    </div>
                    <div class="sh-form-col sh-form-col-sm">
                        <label>Log IP Addresses</label>
                        <label class="sh-toggle" style="margin-top:6px;">
                            <input type="checkbox" name="log_ip" value="1" <?php checked($log_ip); ?>>
                            <span class="sh-toggle-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="sh-form-row">
                    <div class="sh-form-col sh-form-col-sm">
                        <label>Rate Limiting</label>
                        <label class="sh-toggle" style="margin-top:6px;">
                            <input type="checkbox" name="rate_limit" value="1" id="sh_rate_limit_toggle" <?php checked($rate_limit); ?>>
                            <span class="sh-toggle-slider"></span>
                        </label>
                    </div>
                    <div class="sh-form-col sh-form-col-sm" id="sh_rate_limit_max_col" style="<?php echo $rate_limit ? '' : 'display:none;'; ?>">
                        <label>Max downloads / IP / hour</label>
                        <input type="number" name="rate_limit_max" value="<?php echo esc_attr($rate_max); ?>" class="sh-input" min="1" max="1000" style="width:100px;">
                    </div>
                </div>
                <script>
                document.getElementById('sh_rate_limit_toggle').addEventListener('change', function() {
                    document.getElementById('sh_rate_limit_max_col').style.display = this.checked ? '' : 'none';
                });
                </script>
            </div>
        </div>

        <!-- Lead data wp_guests tablosunda saklanır — her log row'unda tekrar tutulmaz.
             GuestIdentity::updateProfile() ile email/name/phone/meta güncellenir. -->

        </div><!-- .sh-main -->
        <div class="sh-sidebar">
            <div class="sh-card">
                <h3 style="margin:0 0 10px;font-size:13px;font-weight:600;">Cron Schedules</h3>
                <div style="font-size:12px;color:#6b7280;line-height:2;">
                    <strong>daily</strong> — every day 07:00<br>
                    <strong>weekly</strong> — Monday 07:00<br>
                    <strong>biweekly</strong> — every 14 days<br>
                    <strong>monthly</strong> — 1st of month
                </div>
            </div>
        </div>
        </div>

        <div style="padding:16px 0;">
            <button type="submit" class="sh-btn sh-btn-primary">Save Settings</button>
        </div>
        </form>

        <!-- Danger Zone -->
        <div class="sh-table-wrap" style="margin-top:20px;border-color:#fecaca;">
            <div style="padding:14px 16px;border-bottom:1px solid #fecaca;background:#fff5f5;">
                <h2 style="margin:0;font-size:15px;font-weight:600;color:#dc2626;">&#9888; Danger Zone</h2>
            </div>
            <div style="padding:20px;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;">
                    <div>
                        <strong style="font-size:13px;">Clear All Download Logs</strong>
                        <p style="margin:4px 0 0;font-size:12px;color:#6b7280;">Tüm download log kayıtlarını siler. Bu işlem geri alınamaz.</p>
                    </div>
                    <button type="button" id="sh-dl-clear-logs" class="sh-btn" style="background:#dc2626;color:#fff;border-color:#dc2626;white-space:nowrap;flex-shrink:0;">
                        &#128465; Clear All Logs
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    public static function saveSettings(): void {
        check_admin_referer( 'sh_download_save_settings' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        update_option( 'sh_download_report_recipients', sanitize_text_field( $_POST['report_recipients'] ?? '' ) );
        update_option( 'sh_download_report_schedule',   sanitize_key( $_POST['report_schedule'] ?? 'weekly' ) );
        update_option( 'sh_download_report_mail_limit', (int) ( $_POST['report_mail_limit'] ?? 50 ) );
        update_option( 'sh_download_default_mode',      sanitize_key( $_POST['default_mode'] ?? 'public' ) );
        update_option( 'sh_download_lead_cookie_days',  (int) ( $_POST['lead_cookie_days'] ?? 365 ) );
        update_option( 'sh_download_log_ip',            ! empty( $_POST['log_ip'] ) );
        update_option( 'sh_download_rate_limit',        ! empty( $_POST['rate_limit'] ) );
        update_option( 'sh_download_rate_limit_max',    (int) ( $_POST['rate_limit_max'] ?? 20 ) );

        // Cron'u yeniden planla
        self::rescheduleCron( sanitize_key( $_POST['report_schedule'] ?? 'weekly' ) );

        wp_redirect( add_query_arg( [ 'page' => self::MENU_SLUG, 'tab' => 'settings', 'saved' => '1' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    // ─── ANALYTICS TAB ───────────────────────────────────

    private static function renderAnalyticsTab(): void {
        // Date range — JS range picker veya query param
        $date_from = sanitize_text_field( $_GET['date_from'] ?? '' );
        $date_to   = sanitize_text_field( $_GET['date_to']   ?? '' );
        $days      = 30;
        if ( $date_from && $date_to ) {
            $days = max( 1, (int) ceil( ( strtotime( $date_to ) - strtotime( $date_from ) ) / DAY_IN_SECONDS ) );
        } elseif ( isset( $_GET['days'] ) && in_array( (int) $_GET['days'], [ 7, 30, 90, 365 ], true ) ) {
            $days = (int) $_GET['days'];
        } else {
            $date_from = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );
            $date_to   = gmdate( 'Y-m-d' );
        }

        $stats     = self::getStats();
        $top_files = self::getTopFiles( 20, $days );
        $chart_data = [];
        global $wpdb;
        $table = $wpdb->prefix . 'download_log';

        // Tüm period'lar için pre-built chart data — JS reload olmadan period değiştirebilir
        $periods = [ 7, 30, 90, 365 ];
        $all_chart_data = [];
        foreach ( $periods as $p ) {
            $dates = [];
            for ( $i = $p - 1; $i >= 0; $i-- ) {
                $dates[] = gmdate( 'Y-m-d', strtotime( "-{$i} days" ) );
            }
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT DATE(created_at) as day, COUNT(*) as cnt FROM {$table}
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
                 GROUP BY day",
                $p
            ) );
            $sparse = [];
            foreach ( $rows as $row ) { $sparse[ $row->day ] = (int) $row->cnt; }
            $all_chart_data[ $p ] = [
                'labels'  => $dates,
                'counts'  => array_map( fn($d) => $sparse[$d] ?? 0, $dates ),
            ];
        }

        // Mevcut view için chart data (date_from/date_to varsa custom)
        $chart_data = $all_chart_data[ $days ] ?? $all_chart_data[30];

        // Custom aralık varsa ayrıca hesapla
        if ( $date_from && $date_to && ! in_array( $days, [ 7, 30, 90, 365 ], true ) ) {
            $custom_dates = [];
            $cur = strtotime( $date_from );
            $end = strtotime( $date_to );
            while ( $cur <= $end ) {
                $custom_dates[] = gmdate( 'Y-m-d', $cur );
                $cur = strtotime( '+1 day', $cur );
            }
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT DATE(created_at) as day, COUNT(*) as cnt FROM {$table}
                 WHERE DATE(created_at) BETWEEN %s AND %s GROUP BY day",
                $date_from, $date_to
            ) );
            $sparse = [];
            foreach ( $rows as $row ) { $sparse[ $row->day ] = (int) $row->cnt; }
            $all_chart_data['custom'] = [
                'labels' => $custom_dates,
                'counts' => array_map( fn($d) => $sparse[$d] ?? 0, $custom_dates ),
            ];
        }
        $chart_json = wp_json_encode( $chart_data );
        ?>
        <div class="sh-cards">
            <?php foreach ( [
                [ 'Today',          $stats['today'],         'Son 24 saat' ],
                [ 'This Week',      $stats['week'],          'Son 7 gun' ],
                [ 'This Month',     $stats['month'],         'Son 30 gun' ],
                [ 'Total',          $stats['total'],         'Tum zamanlar' ],
                [ 'Lead Captures',  $stats['leads'],         'Form dolduruldu' ],
                [ 'Conversions',    $stats['converted'],     'Guest → User' ],
            ] as [$title, $val, $sub] ) : ?>
            <div class="sh-card">
                <h3><?php echo $title; ?></h3>
                <div class="sh-card-val"><?php echo number_format_i18n( $val ); ?></div>
                <div class="sh-card-sub"><?php echo $sub; ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="sh-chart-wrap">
            <div class="sh-chart-header">
                <h2>Son <?php echo $days; ?> Gun Indirme Hacmi</h2>
            </div>
            <canvas id="sh-dl-chart" height="80"></canvas>
        </div>

        <script>
        // Reactions analytics pattern — tüm period'lar pre-built, JS reload olmadan değiştirir
        window.shDlCPD      = <?php echo wp_json_encode( $all_chart_data ); ?>;
        window.shDlDays     = <?php echo (int) $days; ?>;
        window.shDlIsCustom = <?php echo ( isset( $all_chart_data['custom'] ) ? 'true' : 'false' ); ?>;
        window.shDlDateFrom = <?php echo wp_json_encode( $date_from ); ?>;
        window.shDlDateTo   = <?php echo wp_json_encode( $date_to ); ?>;
        </script>

        <div class="sh-table-wrap">
            <div style="padding:14px 16px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;">
                <h2 style="margin:0;font-size:15px;font-weight:600;">&#128229; En Çok İndirilenler</h2>
                <div class="sh-dl-period-btns" data-table="top-files" style="display:flex;gap:4px;">
                    <button class="sh-btn sh-dl-period-btn active" data-days="0"  style="padding:2px 8px;font-size:11px;">All Time</button>
                    <button class="sh-btn sh-dl-period-btn"        data-days="7"  style="padding:2px 8px;font-size:11px;">7G</button>
                    <button class="sh-btn sh-dl-period-btn"        data-days="30" style="padding:2px 8px;font-size:11px;">30G</button>
                    <button class="sh-btn sh-dl-period-btn"        data-days="90" style="padding:2px 8px;font-size:11px;">90G</button>
                </div>
            </div>
            <table class="sh-table" id="sh-dl-top-files-table">
                <thead><tr><th>#</th><th>File</th><th>Downloads</th><th>Last</th></tr></thead>
                <tbody>
                <?php foreach ( $top_files as $i => $row ) :
                    $max = $top_files[0]->cnt ?? 1;
                    $bar = $max > 0 ? round( ( $row->cnt / $max ) * 80 ) : 0;
                ?>
                <tr>
                    <td><span class="sh-analytics-rank <?php echo $i===0?'sh-analytics-rank-1':($i===1?'sh-analytics-rank-2':($i===2?'sh-analytics-rank-3':'')); ?>"><?php echo $i+1; ?></span></td>
                    <td>
                        <?php
                        $dl_url = wp_get_attachment_url( (int) $row->file_id );
                        if ( $dl_url ) :
                        ?><a href="<?php echo esc_url( $dl_url ); ?>" target="_blank"><?php echo esc_html( $row->file_name ); ?></a><?php
                        else :
                            echo esc_html( $row->file_name );
                        endif;
                        ?>
                    </td>
                    <td>
                        <strong style="color:#2271b1;"><?php echo number_format_i18n( $row->cnt ); ?></strong>
                        <span class="sh-analytics-bar" style="background:#2271b1;width:<?php echo $bar; ?>px;margin-left:6px;"></span>
                    </td>
                    <td style="font-size:11px;color:#9ca3af;"><?php echo esc_html( wp_date( 'd M Y', strtotime( $row->last_at ) ) ); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if ( empty( $top_files ) ) : ?>
                    <tr><td colspan="4" style="text-align:center;color:#9ca3af;padding:24px;">No data yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <script>
        // chart_data JS'e geçirildi — admin JS redrawChart() ile çizer
        // Bu inline script artık sadece fallback — admin JS yüklenmezse çalışır
        (function(){
            var data = <?php echo $chart_json; ?>;
            function draw(){
                if(typeof Chart==='undefined'){setTimeout(draw,100);return;}
                // Admin JS zaten çizmişse tekrar çizme
                if(window.shDlChartDrawn){return;}
                var ctx=document.getElementById('sh-dl-chart');
                if(!ctx)return;
                new Chart(ctx,{type:'line',data:{
                    labels: data.labels,
                    datasets:[{data: data.counts,
                        borderColor:'#2271b1',backgroundColor:'rgba(34,113,177,0.06)',
                        fill:true,tension:0.3,borderWidth:2,pointRadius:3}]
                },options:{responsive:true,plugins:{legend:{display:false}},
                    scales:{y:{beginAtZero:true,ticks:{precision:0}},x:{ticks:{maxTicksLimit:10}}}}});
            }
            draw();
        })();
        </script>
        <?php
        // ── En Çok İndirenler tablosu ──────────────────────────────────────
        $top_downloaders = self::getTopDownloaders( 20, $days );
        $export_nonce    = wp_create_nonce( 'sh_download_export' );
        ?>

        <div class="sh-table-wrap" style="margin-top:24px;">
            <div style="padding:14px 16px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;">
                <h2 style="margin:0;font-size:15px;font-weight:600;">&#128100; En Çok İndirenler</h2>
                <div class="sh-dl-period-btns" data-table="top-downloaders" style="display:flex;gap:4px;">
                    <button class="sh-btn sh-dl-period-btn active" data-days="0"  style="padding:2px 8px;font-size:11px;">All Time</button>
                    <button class="sh-btn sh-dl-period-btn"        data-days="7"  style="padding:2px 8px;font-size:11px;">7G</button>
                    <button class="sh-btn sh-dl-period-btn"        data-days="30" style="padding:2px 8px;font-size:11px;">30G</button>
                    <button class="sh-btn sh-dl-period-btn"        data-days="90" style="padding:2px 8px;font-size:11px;">90G</button>
                </div>
            </div>
            <table class="sh-table" id="sh-dl-top-downloaders-table">
                <thead><tr>
                    <th>#</th><th>User / Guest</th><th>Type</th><th>Email</th>
                    <th>Downloads</th><th>Last</th><th>Actions</th>
                </tr></thead>
                <tbody>
                <?php foreach ( $top_downloaders as $i => $row ) :
                    $is_logged  = $row->type === 'logged';
                    $identifier = $row->identifier;
                    $type_badge = $is_logged
                        ? '<span class="sh-type" style="background:#2271b118;color:#2271b1;">Logged</span>'
                        : '<span class="sh-type" style="background:#f59e0b18;color:#f59e0b;">Guest</span>';
                    $max_dl = $top_downloaders[0]->cnt ?? 1;
                    $bar    = $max_dl > 0 ? round( ( $row->cnt / $max_dl ) * 80 ) : 0;
                    $export_base_user = add_query_arg( [
                        'action'     => 'sh_download_export_user',
                        'type'       => $row->type,
                        'identifier' => $identifier,
                        '_wpnonce'   => $export_nonce,
                    ], admin_url( 'admin-post.php' ) );
                ?>
                <tr>
                    <td><span class="sh-analytics-rank <?php echo $i===0?'sh-analytics-rank-1':($i===1?'sh-analytics-rank-2':($i===2?'sh-analytics-rank-3':'')); ?>"><?php echo $i+1; ?></span></td>
                    <td><strong><?php echo esc_html( $row->display_name ?: $identifier ); ?></strong></td>
                    <td><?php echo $type_badge; ?></td>
                    <td style="font-size:11px;">
                        <?php if ( $row->email ) : ?>
                            <a href="mailto:<?php echo esc_attr( $row->email ); ?>"><?php echo esc_html( $row->email ); ?></a>
                        <?php else : ?>
                            <span style="color:#9ca3af">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong style="color:#2271b1;"><?php echo number_format_i18n( $row->cnt ); ?></strong>
                        <span class="sh-analytics-bar" style="background:#2271b1;width:<?php echo $bar; ?>px;margin-left:6px;"></span>
                    </td>
                    <td style="font-size:11px;color:#9ca3af;"><?php echo esc_html( wp_date( 'd M Y', strtotime( $row->last_at ) ) ); ?></td>
                    <td style="white-space:nowrap;">
                        <button type="button"
                                class="sh-btn sh-btn-secondary sh-dl-history-btn"
                                data-type="<?php echo esc_attr( $row->type ); ?>"
                                data-id="<?php echo esc_attr( $identifier ); ?>"
                                data-name="<?php echo esc_attr( $row->display_name ?: $identifier ); ?>"
                                style="padding:2px 8px;font-size:11px;">&#128229; History</button>
                        <a href="<?php echo esc_url( add_query_arg( 'format', 'csv',  $export_base_user ) ); ?>" class="sh-btn sh-btn-secondary" style="padding:2px 8px;font-size:11px;margin-left:4px;">&#8595; CSV</a>
                        <a href="<?php echo esc_url( add_query_arg( 'format', 'xlsx', $export_base_user ) ); ?>" class="sh-btn sh-btn-secondary" style="padding:2px 8px;font-size:11px;margin-left:4px;">&#8595; XLSX</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if ( empty( $top_downloaders ) ) : ?>
                    <tr><td colspan="7" style="text-align:center;color:#9ca3af;padding:24px;">No data yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- History Modal -->
        <div id="sh-dl-history-modal" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.5);align-items:center;justify-content:center;">
            <div style="background:#fff;border-radius:8px;width:680px;max-width:95vw;max-height:85vh;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,.3);">
                <div style="padding:16px 20px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;">
                    <h3 id="sh-dl-history-title" style="margin:0;font-size:15px;font-weight:600;">Download History</h3>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <a id="sh-dl-history-csv"  href="#" class="sh-btn sh-btn-secondary" style="font-size:11px;padding:3px 10px;">&#8595; CSV</a>
                        <a id="sh-dl-history-xlsx" href="#" class="sh-btn sh-btn-secondary" style="font-size:11px;padding:3px 10px;">&#8595; XLSX</a>
                        <button type="button" onclick="document.getElementById('sh-dl-history-modal').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;color:#9ca3af;line-height:1;">&#10005;</button>
                    </div>
                </div>
                <div id="sh-dl-history-body" style="overflow-y:auto;padding:0;">
                    <table class="sh-table" style="margin:0;">
                        <thead><tr><th>#</th><th>File</th><th>Mode</th><th>Source</th><th>Lang</th><th>Date</th></tr></thead>
                        <tbody id="sh-dl-history-rows"></tbody>
                    </table>
                </div>
                <div id="sh-dl-history-footer" style="padding:12px 20px;border-top:1px solid #e5e7eb;font-size:12px;color:#9ca3af;text-align:right;"></div>
            </div>
        </div>

        <script>
        (function(){
            var nonce       = '<?php echo esc_js( wp_create_nonce( self::NONCE_KEY ) ); ?>';
            var ajaxUrl     = '<?php echo esc_js( admin_url( "admin-ajax.php" ) ); ?>';
            var exportNonce = '<?php echo esc_js( $export_nonce ); ?>';
            var adminPost   = '<?php echo esc_js( admin_url( "admin-post.php" ) ); ?>';

            document.querySelectorAll('.sh-dl-history-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var type = btn.dataset.type, id = btn.dataset.id, name = btn.dataset.name;
                    document.getElementById('sh-dl-history-title').textContent = name + ' — Download History';
                    document.getElementById('sh-dl-history-rows').innerHTML = '<tr><td colspan="6" style="text-align:center;padding:20px;color:#9ca3af;">Loading...</td></tr>';
                    document.getElementById('sh-dl-history-footer').textContent = '';
                    document.getElementById('sh-dl-history-modal').style.display = 'flex';
                    var base = adminPost + '?action=sh_download_export_user&type=' + encodeURIComponent(type) + '&identifier=' + encodeURIComponent(id) + '&_wpnonce=' + exportNonce;
                    document.getElementById('sh-dl-history-csv').href  = base + '&format=csv';
                    document.getElementById('sh-dl-history-xlsx').href = base + '&format=xlsx';
                    var fd = new FormData();
                    fd.append('action', 'sh_download_user_history');
                    fd.append('nonce', nonce);
                    fd.append('type', type);
                    fd.append('identifier', id);
                    fetch(ajaxUrl, { method: 'POST', body: fd })
                        .then(function(r){ return r.json(); })
                        .then(function(res) {
                            var rows = document.getElementById('sh-dl-history-rows');
                            if (!res.success || !res.data.items.length) {
                                rows.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:20px;color:#9ca3af;">No downloads found.</td></tr>';
                                return;
                            }
                            rows.innerHTML = res.data.items.map(function(row, i) {
                                return '<tr>'
                                    + '<td style="color:#9ca3af;font-size:11px;">' + (i+1) + '</td>'
                                    + '<td style="font-size:12px;">' + (row.file_url ? '<a href="' + row.file_url + '" target="_blank">' + row.file_name + '</a>' : row.file_name) + '</td>'
                                    + '<td><span class="sh-type" style="font-size:10px;">' + row.mode + '</span></td>'
                                    + '<td style="font-size:11px;color:#9ca3af;">' + (row.source_title || '—') + '</td>'
                                    + '<td style="font-size:11px;">' + row.language + '</td>'
                                    + '<td style="font-size:11px;color:#9ca3af;white-space:nowrap;">' + row.created_at + '</td>'
                                    + '</tr>';
                            }).join('');
                            document.getElementById('sh-dl-history-footer').textContent = res.data.total + ' download record';
                        });
                });
            });

            document.getElementById('sh-dl-history-modal').addEventListener('click', function(e) {
                if (e.target === this) this.style.display = 'none';
            });

            // ── Period butonları ──────────────────────────────────────────
            var periodNonce = '<?php echo esc_js( wp_create_nonce( self::NONCE_KEY ) ); ?>';

            document.querySelectorAll('.sh-dl-period-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var group = btn.closest('.sh-dl-period-btns');
                    var tableType = group.dataset.table;
                    var days = parseInt( btn.dataset.days, 10 );

                    // Active stil — siyah
                    group.querySelectorAll('.sh-dl-period-btn').forEach(function(b) {
                        b.style.background  = '';
                        b.style.color       = '';
                        b.style.fontWeight  = '';
                        b.classList.remove('active');
                    });
                    btn.style.background = '#1d2327';
                    btn.style.color      = '#fff';
                    btn.style.fontWeight = '600';
                    btn.classList.add('active');

                    // Tablo ID
                    var tableId = tableType === 'top-files' ? 'sh-dl-top-files-table' : 'sh-dl-top-downloaders-table';
                    var tbody   = document.querySelector('#' + tableId + ' tbody');
                    if (!tbody) return;
                    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:16px;color:#9ca3af;">Loading...</td></tr>';

                    var fd = new FormData();
                    fd.append('action', tableType === 'top-files' ? 'sh_download_top_files' : 'sh_download_top_downloaders');
                    fd.append('nonce', periodNonce);
                    fd.append('days', days);

                    fetch(ajaxUrl, { method: 'POST', body: fd })
                        .then(function(r){ return r.json(); })
                        .then(function(res) {
                            if (!res.success) {
                                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:16px;color:#dc2626;">Error loading data.</td></tr>';
                                return;
                            }
                            tbody.innerHTML = res.data.html || '<tr><td colspan="7" style="text-align:center;padding:16px;color:#9ca3af;">No data.</td></tr>';
                        });
                });
            });

            // Default: All Time butonlarını siyah yap
            document.querySelectorAll('.sh-dl-period-btn[data-days="0"]').forEach(function(btn) {
                btn.style.background = '#1d2327';
                btn.style.color      = '#fff';
                btn.style.fontWeight = '600';
            });
        })();
        </script>
        <?php
    }

    // ─── TOP TABLES AJAX ─────────────────────────────────

    public static function ajaxTopFiles(): void
    {
        check_ajax_referer( self::NONCE_KEY, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Unauthorized' ); }

        $days  = max( 0, (int) ( $_POST['days'] ?? 0 ) );
        $items = self::getTopFiles( 20, $days );

        ob_start();
        foreach ( $items as $i => $row ) :
            $max = $items[0]->cnt ?? 1;
            $bar = $max > 0 ? round( ( $row->cnt / $max ) * 80 ) : 0;
            $dl_url = wp_get_attachment_url( (int) $row->file_id );
            ?>
            <tr>
                <td><span class="sh-analytics-rank <?php echo $i===0?'sh-analytics-rank-1':($i===1?'sh-analytics-rank-2':($i===2?'sh-analytics-rank-3':'')); ?>"><?php echo $i+1; ?></span></td>
                <td><?php echo $dl_url ? '<a href="' . esc_url($dl_url) . '" target="_blank">' . esc_html($row->file_name) . '</a>' : esc_html($row->file_name); ?></td>
                <td><strong style="color:#2271b1;"><?php echo number_format_i18n($row->cnt); ?></strong><span class="sh-analytics-bar" style="background:#2271b1;width:<?php echo $bar; ?>px;margin-left:6px;"></span></td>
                <td style="font-size:11px;color:#9ca3af;"><?php echo esc_html(wp_date('d M Y', strtotime($row->last_at))); ?></td>
            </tr>
            <?php
        endforeach;
        if ( empty( $items ) ) echo '<tr><td colspan="4" style="text-align:center;color:#9ca3af;padding:24px;">No data.</td></tr>';
        $html = ob_get_clean();

        wp_send_json_success( [ 'html' => $html ] );
    }

    public static function ajaxTopDownloaders(): void
    {
        check_ajax_referer( self::NONCE_KEY, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Unauthorized' ); }

        $days  = max( 0, (int) ( $_POST['days'] ?? 0 ) );
        $items = self::getTopDownloaders( 20, $days );
        $export_nonce = wp_create_nonce( 'sh_download_export' );

        ob_start();
        foreach ( $items as $i => $row ) :
            $is_logged  = $row->type === 'logged';
            $identifier = $row->identifier;
            $type_badge = $is_logged
                ? '<span class="sh-type" style="background:#2271b118;color:#2271b1;">Logged</span>'
                : '<span class="sh-type" style="background:#f59e0b18;color:#f59e0b;">Guest</span>';
            $max_dl = $items[0]->cnt ?? 1;
            $bar    = $max_dl > 0 ? round( ( $row->cnt / $max_dl ) * 80 ) : 0;
            $export_base = add_query_arg( [
                'action'     => 'sh_download_export_user',
                'type'       => $row->type,
                'identifier' => $identifier,
                '_wpnonce'   => $export_nonce,
            ], admin_url( 'admin-post.php' ) );
            ?>
            <tr>
                <td><span class="sh-analytics-rank <?php echo $i===0?'sh-analytics-rank-1':($i===1?'sh-analytics-rank-2':($i===2?'sh-analytics-rank-3':'')); ?>"><?php echo $i+1; ?></span></td>
                <td><strong><?php echo esc_html($row->display_name ?: $identifier); ?></strong></td>
                <td><?php echo $type_badge; ?></td>
                <td style="font-size:11px;"><?php echo $row->email ? '<a href="mailto:' . esc_attr($row->email) . '">' . esc_html($row->email) . '</a>' : '<span style="color:#9ca3af">—</span>'; ?></td>
                <td><strong style="color:#2271b1;"><?php echo number_format_i18n($row->cnt); ?></strong><span class="sh-analytics-bar" style="background:#2271b1;width:<?php echo $bar; ?>px;margin-left:6px;"></span></td>
                <td style="font-size:11px;color:#9ca3af;"><?php echo esc_html(wp_date('d M Y', strtotime($row->last_at))); ?></td>
                <td style="white-space:nowrap;">
                    <button type="button" class="sh-btn sh-btn-secondary sh-dl-history-btn" data-type="<?php echo esc_attr($row->type); ?>" data-id="<?php echo esc_attr($identifier); ?>" data-name="<?php echo esc_attr($row->display_name ?: $identifier); ?>" style="padding:2px 8px;font-size:11px;">&#128229; History</button>
                    <a href="<?php echo esc_url(add_query_arg('format','csv',$export_base)); ?>" class="sh-btn sh-btn-secondary" style="padding:2px 8px;font-size:11px;margin-left:4px;">&#8595; CSV</a>
                    <a href="<?php echo esc_url(add_query_arg('format','xlsx',$export_base)); ?>" class="sh-btn sh-btn-secondary" style="padding:2px 8px;font-size:11px;margin-left:4px;">&#8595; XLSX</a>
                </td>
            </tr>
            <?php
        endforeach;
        if ( empty( $items ) ) echo '<tr><td colspan="7" style="text-align:center;color:#9ca3af;padding:24px;">No data.</td></tr>';
        $html = ob_get_clean();

        wp_send_json_success( [ 'html' => $html ] );
    }

    // ─── USER HISTORY AJAX ───────────────────────────────

    public static function ajaxUserHistory(): void
    {
        check_ajax_referer( self::NONCE_KEY, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Unauthorized' ); }

        $type       = sanitize_key( $_POST['type']       ?? '' );
        $identifier = sanitize_text_field( $_POST['identifier'] ?? '' );
        if ( ! $type || ! $identifier ) { wp_send_json_error( 'Missing params' ); }

        $items = self::getUserHistory( $type, $identifier );

        if ( empty( $items ) ) {
            wp_send_json_success( [ 'items' => [], 'total' => 0 ] );
            return;
        }

        $formatted = array_map( function( $row ) {
            return [
                'id'           => $row->id,
                'file_name'    => $row->file_name,
                'file_url'     => $row->file_url ?: '',
                'mode'         => $row->mode,
                'source_title' => $row->source_post ? get_the_title( $row->source_post ) : '',
                'language'     => $row->language,
                'created_at'   => wp_date( 'd M Y H:i', strtotime( $row->created_at ) ),
            ];
        }, $items );

        wp_send_json_success( [ 'items' => $formatted, 'total' => count( $formatted ) ] );
    }

    // ─── USER EXPORT ─────────────────────────────────────

    public static function handleUserExport(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized', 403 );
        if ( ! check_admin_referer( 'sh_download_export' ) ) wp_die( 'Invalid nonce', 403 );

        $type       = sanitize_key( $_GET['type']       ?? '' );
        $identifier = sanitize_text_field( $_GET['identifier'] ?? '' );
        $format     = sanitize_key( $_GET['format'] ?? 'csv' );
        if ( ! $type || ! $identifier ) wp_die( 'Missing params', 400 );

        $items   = self::getUserHistory( $type, $identifier );
        $display = $identifier;
        if ( $type === 'user' || $type === 'logged' ) {
            $u = get_userdata( (int) $identifier );
            if ( $u ) $display = $u->display_name;
        }
        $filename = 'download-history-' . sanitize_file_name( $display ) . '-' . gmdate( 'Y-m-d' );

        if ( $format === 'xlsx' ) {
            self::exportUserXlsx( $items, $filename );
        } else {
            self::exportUserCsv( $items, $filename );
        }
    }

    private static function exportUserCsv( array $items, string $filename ): void
    {
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '.csv"' );
        header( 'Pragma: no-cache' );
        $out = fopen( 'php://output', 'w' );
        fputs( $out, "\xEF\xBB\xBF" );
        fputcsv( $out, [ '#', 'File', 'Mode', 'Source', 'Language', 'Date' ] );
        foreach ( $items as $i => $row ) {
            fputcsv( $out, [
                $i + 1, $row->file_name, $row->mode,
                $row->source_post ? get_the_title( $row->source_post ) : '',
                $row->language, $row->created_at,
            ] );
        }
        fclose( $out );
        exit;
    }

    private static function exportUserXlsx( array $items, string $filename ): void
    {
        // Fallback: HTML table → .xls
        header( 'Content-Type: application/vnd.ms-excel; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '.xls"' );
        echo "\xEF\xBB\xBF";
        echo '<table border="1"><tr><th>#</th><th>File</th><th>Mode</th><th>Source</th><th>Language</th><th>Date</th></tr>';
        foreach ( $items as $i => $row ) {
            echo '<tr>'
                . '<td>' . esc_html( $i + 1 ) . '</td>'
                . '<td>' . esc_html( $row->file_name ) . '</td>'
                . '<td>' . esc_html( $row->mode ) . '</td>'
                . '<td>' . esc_html( $row->source_post ? get_the_title( $row->source_post ) : '' ) . '</td>'
                . '<td>' . esc_html( $row->language ) . '</td>'
                . '<td>' . esc_html( $row->created_at ) . '</td>'
                . '</tr>';
        }
        echo '</table>';
        exit;
    }

    // ─── CRON ────────────────────────────────────────────

    public static function registerCronSchedules( array $schedules ): array {
        $schedules['biweekly'] = [ 'interval' => 14 * DAY_IN_SECONDS, 'display' => 'Every 2 Weeks' ];
        return $schedules;
    }

    public static function rescheduleCron( string $schedule ): void {
        wp_clear_scheduled_hook( 'sh_download_report_cron' );
        if ( $schedule === 'disabled' ) return;

        $times = [
            'daily'    => strtotime( 'tomorrow 07:00' ),
            'weekly'   => strtotime( 'next monday 07:00' ),
            'biweekly' => strtotime( '+14 days 07:00' ),
            'monthly'  => strtotime( 'first day of next month 07:00' ),
        ];

        $timestamp = $times[$schedule] ?? strtotime( 'next monday 07:00' );
        wp_schedule_event( $timestamp, $schedule === 'biweekly' ? 'biweekly' : $schedule, 'sh_download_report_cron' );
    }

    public static function sendScheduledReport(): void {
        $schedule   = get_option( 'sh_download_report_schedule', 'weekly' );
        $recipients = get_option( 'sh_download_report_recipients', '' );
        if ( ! $recipients || $schedule === 'disabled' ) return;

        $days_map = [ 'daily' => 1, 'weekly' => 7, 'biweekly' => 14, 'monthly' => 30 ];
        $days     = $days_map[$schedule] ?? 7;

        $date_from = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );
        $date_to   = gmdate( 'Y-m-d' );

        $result = self::getLogs( [ 'per_page' => 99999, 'page' => 1, 'date_from' => $date_from, 'date_to' => $date_to ] );
        $items  = $result['items'];

        if ( empty( $items ) ) return;

        $label     = ucfirst( $schedule );
        $mail_limit = (int) get_option( 'sh_download_report_mail_limit', 50 );

        // XLSX attachment — gerçek XLSX (ZIP+XML, sıfır dependency)
        $upload_dir = wp_upload_dir();
        $filename   = "download-report-{$date_from}-to-{$date_to}";
        $filepath   = $upload_dir['basedir'] . '/' . $filename . '.xlsx';

        $headers = [ 'ID', 'File', 'User', 'Type', 'Mode', 'Email', 'Name', 'Phone', 'IP', 'Source', 'Date' ];
        $xlsRows = [];
        foreach ( $items as $row ) {
            $is_logged = ! empty( $row->user_id );
            $user_obj  = $is_logged ? get_userdata( (int) $row->user_id ) : null;

            if ( $is_logged && $user_obj ) {
                $user_display = $user_obj->display_name;
                $roles        = (array) $user_obj->roles;
                $type_label   = ucfirst( $roles[0] ?? 'User' );
                $email        = $user_obj->user_email;
                $name         = $user_obj->display_name;
            } else {
                $user_display = $row->guest_id ? substr( $row->guest_id, 0, 16 ) . '...' : '';
                $type_label   = 'Guest';
                $email        = $row->guest_email ?? '';
                $name         = $row->guest_name  ?? '';
            }

            $xlsRows[] = [
                $row->id,
                $row->file_name,
                $user_display,
                $type_label,
                $row->mode,
                $email,
                $name,
                $row->guest_phone ?? '',
                $row->ip,
                $row->source_post ? get_the_title( $row->source_post ) : '',
                $row->created_at,
            ];
        }

        // XLSX dosyasını diske yaz (mail attachment için)
        self::writeXlsxToFile( $filepath, $headers, $xlsRows );

        // Mail body — ilk N kayıt tablo olarak
        $display_items = array_slice( $items, 0, $mail_limit );
        $readable_from = wp_date( 'd.m.Y', strtotime( $date_from ) );
        $readable_to   = wp_date( 'd.m.Y', strtotime( $date_to ) );

        ob_start();
        ?>
        <div style="font-family:Arial,sans-serif;color:#333;max-width:900px;">
            <h2 style="color:#2271b1;">&#128229; <?php echo esc_html($label); ?> Download Report</h2>
            <p><strong>Period:</strong> <?php echo esc_html($readable_from); ?> — <?php echo esc_html($readable_to); ?></p>
            <p>Total downloads: <strong><?php echo count($items); ?></strong>. Full data attached as XLSX.</p>
            <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG ) ); ?>" style="background:#2271b1;color:#fff;padding:8px 20px;border-radius:4px;text-decoration:none;display:inline-block;">View in Admin</a></p>
            <table border="1" cellpadding="6" cellspacing="0" style="width:100%;border-collapse:collapse;border:1px solid #ddd;margin-top:20px;">
                <tr style="background:#f8f9fa;">
                    <th>File</th><th>User/Guest</th><th>Type</th><th>Mode</th><th>Email</th><th>Name</th><th>Date</th>
                </tr>
                <?php foreach ( $display_items as $row ) :
                    $is_logged  = ! empty( $row->user_id );
                    $user_obj   = $is_logged ? get_userdata( $row->user_id ) : null;

                    // User/Guest display
                    if ( $is_logged && $user_obj ) {
                        $user_display = $user_obj->display_name;
                    } elseif ( $row->guest_id ) {
                        $user_display = substr( $row->guest_id, 0, 12 ) . '...';
                    } else {
                        $user_display = '—';
                    }

                    // Type
                    if ( $is_logged && $user_obj ) {
                        $roles = (array) $user_obj->roles;
                        $type_display = ucfirst( $roles[0] ?? 'User' );
                        $type_color   = '#2271b1';
                    } else {
                        $type_display = 'Guest';
                        $type_color   = '#f59e0b';
                    }

                    // Email & Name — logged için user bilgisi, guest için lead data
                    if ( $is_logged && $user_obj ) {
                        $info_email = $user_obj->user_email;
                        $info_name  = $user_obj->display_name;
                    } else {
                        $info_email = $row->guest_email ?? '';
                        $info_name  = $row->guest_name  ?? '';
                    }

                    // File link
                    $file_url = wp_get_attachment_url( (int) $row->file_id );
                ?>
                <tr>
                    <td><?php echo $file_url ? '<a href="' . esc_url($file_url) . '" style="color:#2271b1;">' . esc_html($row->file_name) . '</a>' : esc_html($row->file_name); ?></td>
                    <td><?php echo esc_html( $user_display ); ?></td>
                    <td><span style="background:<?php echo esc_attr($type_color); ?>22;color:<?php echo esc_attr($type_color); ?>;padding:2px 6px;border-radius:4px;font-size:11px;"><?php echo esc_html($type_display); ?></span></td>
                    <td><?php echo esc_html( $row->mode ); ?></td>
                    <td><?php echo $info_email ? '<a href="mailto:' . esc_attr($info_email) . '" style="color:#2271b1;">' . esc_html($info_email) . '</a>' : '—'; ?></td>
                    <td><?php echo esc_html( $info_name ?: '—' ); ?></td>
                    <td><?php echo esc_html( wp_date( 'd.m.Y H:i', strtotime( $row->created_at ) ) ); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php if ( count($items) > $mail_limit ) : ?>
            <p style="font-size:12px;color:#6b7280;margin-top:12px;"><em>* Showing first <?php echo $mail_limit; ?> of <?php echo count($items); ?> records. Full data in attachment.</em></p>
            <?php endif; ?>
        </div>
        <?php
        $body = ob_get_clean();

        $to_list = array_map( 'trim', explode( ',', $recipients ) );
        $to_list = array_filter( $to_list, 'is_email' );
        if ( empty( $to_list ) ) return;

        $subject = get_bloginfo('name') . " — {$label} Download Report ({$readable_from} - {$readable_to})";
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];

        wp_mail( implode( ',', $to_list ), $subject, $body, $headers, [ $filepath ] );

        // Temizle
        @unlink( $filepath );
    }

    public static function clearLogs(): void {
        check_ajax_referer( 'sh_download_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        global $wpdb;
        $table   = $wpdb->prefix . 'download_log';
        $deleted = $wpdb->query( "TRUNCATE TABLE {$table}" );

        wp_send_json_success( [ 'message' => 'All logs cleared.' ] );
    }

    // ─── EXPORT HANDLER ──────────────────────────────────

    public static function handleExport(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized', 403 );
        if ( ! check_admin_referer( 'sh_download_export' ) ) wp_die( 'Invalid nonce', 403 );

        $format    = sanitize_key( $_GET['format'] ?? 'xlsx' );
        $date_from = sanitize_text_field( $_GET['date_from'] ?? '' );
        $date_to   = sanitize_text_field( $_GET['date_to'] ?? '' );
        $mode      = sanitize_key( $_GET['mode'] ?? '' );

        $result   = self::getLogs( [ 'per_page' => 99999, 'page' => 1, 'date_from' => $date_from, 'date_to' => $date_to, 'mode' => $mode ] );
        $items    = $result['items'];
        $filename = 'download-log-' . ( $date_from ?: gmdate( 'Y-m-d' ) ) . '-to-' . ( $date_to ?: gmdate( 'Y-m-d' ) );

        $headers = [ 'ID', 'File', 'User', 'Guest ID', 'Mode', 'Lead Email', 'Lead Name', 'Lead Phone', 'IP', 'Source', 'Language', 'Date' ];

        $rows = [];
        foreach ( $items as $row ) {
            $user   = $row->user_id ? ( get_userdata( $row->user_id )->display_name ?? (string) $row->user_id ) : '';
            $source = $row->source_post ? get_the_title( $row->source_post ) : '';
            $rows[] = [
                $row->id,
                $row->file_name,
                $user,
                $row->guest_id    ?? '',
                $row->mode,
                $row->guest_email ?? '',
                $row->guest_name  ?? '',
                $row->guest_phone ?? '',
                $row->ip,
                $source,
                $row->language,
                $row->created_at,
            ];
        }

        if ( $format === 'csv' ) {
            self::exportAsCsv( $filename, $headers, $rows );
        } else {
            self::exportAsXlsx( $filename, $headers, $rows );
        }
    }

    // ─── CSV ─────────────────────────────────────────────

    private static function exportAsCsv( string $filename, array $headers, array $rows ): void {
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '.csv"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $out = fopen( 'php://output', 'w' );
        fputs( $out, "\xEF\xBB\xBF" ); // BOM — Excel Türkçe uyumu
        fputcsv( $out, $headers );
        foreach ( $rows as $row ) {
            fputcsv( $out, $row );
        }
        fclose( $out );
        exit;
    }

    // ─── XLSX ────────────────────────────────────────────

    /**
     * Gerçek XLSX üret — sıfır dependency (ZIP + XML).
     * PhpSpreadsheet gerekmez.
     */
    private static function exportAsXlsx( string $filename, array $headers, array $rows ): void {
        // PhpSpreadsheet varsa onu kullan
        if ( class_exists( '\PhpOffice\PhpSpreadsheet\Spreadsheet' ) ) {
            self::exportAsXlsxNative( $filename, $headers, $rows );
            return;
        }

        if ( ! class_exists( 'ZipArchive' ) ) {
            self::exportAsCsv( $filename, $headers, $rows );
            return;
        }

        $tmpFile = tempnam( sys_get_temp_dir(), 'sh_xlsx_' );
        self::buildXlsxZip( $tmpFile, $headers, $rows );

        header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '.xlsx"' );
        header( 'Content-Length: ' . filesize( $tmpFile ) );
        header( 'Cache-Control: max-age=0' );

        readfile( $tmpFile ); // phpcs:ignore
        unlink( $tmpFile );
        exit;
    }

    /**
     * PhpSpreadsheet ile XLSX — varsa kullan.
     */
    private static function exportAsXlsxNative( string $filename, array $headers, array $rows ): void {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle( 'Download Log' );
        $sheet->fromArray( $headers, null, 'A1' );

        $headerStyle = [
            'font' => [ 'bold' => true, 'color' => [ 'rgb' => 'FFFFFF' ] ],
            'fill' => [ 'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => [ 'rgb' => '2271B1' ] ],
        ];
        $sheet->getStyle( 'A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( count( $headers ) ) . '1' )
              ->applyFromArray( $headerStyle );

        $rowNum = 2;
        foreach ( $rows as $row ) {
            $sheet->fromArray( $row, null, 'A' . $rowNum++ );
        }

        foreach ( range( 1, count( $headers ) ) as $ci ) {
            $sheet->getColumnDimensionByColumn( $ci )->setAutoSize( true );
        }

        header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '.xlsx"' );
        header( 'Cache-Control: max-age=0' );

        ( new \PhpOffice\PhpSpreadsheet\Writer\Xlsx( $spreadsheet ) )->save( 'php://output' );
        exit;
    }

    /**
     * Kolon indeksini Excel harf notasyonuna çevir (0→A, 25→Z, 26→AA).
     */
    private static function xlsxColLetter( int $index ): string {
        $letter = '';
        $index++;
        while ( $index > 0 ) {
            $index--;
            $letter = chr( 65 + ( $index % 26 ) ) . $letter;
            $index  = (int) ( $index / 26 );
        }
        return $letter;
    }

    /**
     * XLSX dosyasını diske yaz — cron rapor mail attachment için.
     */
    private static function writeXlsxToFile( string $filepath, array $headers, array $rows ): void {
        if ( ! class_exists( 'ZipArchive' ) ) {
            // Fallback: CSV olarak yaz
            $fp = fopen( $filepath . '.csv', 'w' );
            fputs( $fp, "\xEF\xBB\xBF" );
            fputcsv( $fp, $headers );
            foreach ( $rows as $row ) fputcsv( $fp, $row );
            fclose( $fp );
            return;
        }

        // Geçici dosyaya yaz, sonra taşı
        $tmp = tempnam( sys_get_temp_dir(), 'sh_xlsx_' );
        self::buildXlsxZip( $tmp, $headers, $rows );
        rename( $tmp, $filepath );
    }

    /**
     * ZIP+XML XLSX dosyası oluştur — ortak mantık.
     */
    private static function buildXlsxZip( string $tmpFile, array $headers, array $rows ): void {
        $sharedStrings = [];
        $siIndex       = [];

        $getSi = function ( string $val ) use ( &$sharedStrings, &$siIndex ): int {
            if ( ! isset( $siIndex[ $val ] ) ) {
                $siIndex[ $val ] = count( $sharedStrings );
                $sharedStrings[] = $val;
            }
            return $siIndex[ $val ];
        };

        $sheetRows = '';

        $sheetRows .= '<row r="1">';
        foreach ( $headers as $ci => $h ) {
            $col        = self::xlsxColLetter( $ci );
            $si         = $getSi( (string) $h );
            $sheetRows .= '<c r="' . $col . '1" t="s" s="1"><v>' . $si . '</v></c>';
        }
        $sheetRows .= '</row>';

        foreach ( $rows as $ri => $row ) {
            $rowNum     = $ri + 2;
            $sheetRows .= '<row r="' . $rowNum . '">';
            foreach ( $row as $ci => $val ) {
                $col = self::xlsxColLetter( $ci );
                $val = (string) $val;
                if ( $ci === 0 && is_numeric( $val ) ) {
                    $sheetRows .= '<c r="' . $col . $rowNum . '"><v>' . esc_attr( $val ) . '</v></c>';
                } else {
                    $si         = $getSi( $val );
                    $sheetRows .= '<c r="' . $col . $rowNum . '" t="s"><v>' . $si . '</v></c>';
                }
            }
            $sheetRows .= '</row>';
        }

        $lastCol = self::xlsxColLetter( count( $headers ) - 1 );
        $lastRow = count( $rows ) + 1;

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<dimension ref="A1:' . $lastCol . $lastRow . '"/>'
            . '<sheetData>' . $sheetRows . '</sheetData>'
            . '</worksheet>';

        $ssItems = '';
        foreach ( $sharedStrings as $s ) {
            $ssItems .= '<si><t xml:space="preserve">' . htmlspecialchars( $s, ENT_XML1, 'UTF-8' ) . '</t></si>';
        }
        $ssXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count( $sharedStrings ) . '" uniqueCount="' . count( $sharedStrings ) . '">'
            . $ssItems . '</sst>';

        $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"/></cellXfs>'
            . '</styleSheet>';

        $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Download Log" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';

        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';

        $pkgRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';

        $zip = new \ZipArchive();
        $zip->open( $tmpFile, \ZipArchive::OVERWRITE );
        $zip->addFromString( '[Content_Types].xml',        $contentTypes );
        $zip->addFromString( '_rels/.rels',                $pkgRels );
        $zip->addFromString( 'xl/workbook.xml',            $workbookXml );
        $zip->addFromString( 'xl/_rels/workbook.xml.rels', $wbRels );
        $zip->addFromString( 'xl/worksheets/sheet1.xml',   $sheetXml );
        $zip->addFromString( 'xl/sharedStrings.xml',       $ssXml );
        $zip->addFromString( 'xl/styles.xml',              $stylesXml );
        $zip->close();
    }

}
