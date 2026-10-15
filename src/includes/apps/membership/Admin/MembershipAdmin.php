<?php
namespace SaltHareket\Membership\Admin;
use SaltHareket\Membership\MembershipManager;

class MembershipAdmin
{
    public static function register(): void
    {
        if ( ! is_admin() ) return;
        add_action( 'admin_enqueue_scripts', [ self::class, 'enqueueAssets' ] );
        add_filter( 'manage_users_columns', [ self::class, 'addUserColumns' ] );
        add_filter( 'manage_users_custom_column', [ self::class, 'renderUserColumn' ], 10, 3 );
        add_filter( 'bulk_actions-users', [ self::class, 'addBulkActions' ] );
        add_filter( 'handle_bulk_actions-users', [ self::class, 'handleBulkActions' ], 10, 3 );
        add_action( 'show_user_profile', [ self::class, 'addUserProfileFields' ] );
        add_action( 'edit_user_profile', [ self::class, 'addUserProfileFields' ] );
        add_action( 'admin_action_sh_approve_user', [ self::class, 'handleSingleApprove' ] );
        add_action( 'admin_action_sh_reject_user', [ self::class, 'handleSingleReject' ] );
        add_action( 'admin_action_sh_impersonate', [ self::class, 'handleImpersonation' ] );
        add_action( 'admin_action_sh_stop_impersonate', [ self::class, 'stopImpersonation' ] );
        // add_action( 'admin_menu', [ self::class, 'addMenuPage' ], 25 ); // Dashboard üzerinden erişiliyor
        add_action( 'admin_head', [ self::class, 'hideNotices' ] );
        add_action( 'wp_ajax_sh_guest_stats', [ self::class, 'ajaxGuestStats' ] );
        add_action( 'wp_ajax_sh_membership_save_settings', [ self::class, 'ajaxSaveSettings' ] );
    }

    public static function addMenuPage(): void
    {
        add_submenu_page( 'theme-settings', '👤 Membership', '👤 Membership', 'manage_options', 'sh-membership', [ self::class, 'renderPage' ] );
    }

    public static function hideNotices(): void
    {
        if ( ( $_GET['page'] ?? '' ) !== 'sh-membership' ) return;
        echo '<style>body .notice:not(.sh-inline),body .updated:not(.sh-inline),body .error:not(.sh-inline){display:none!important;}</style>';
    }

    public static function enqueueAssets( string $hook ): void
    {
        $is_membership = strpos( $hook, 'sh-membership' ) !== false;
        $is_user_page  = in_array( $hook, [ 'user-edit.php', 'profile.php', 'users.php' ], true );
        if ( ! $is_membership && ! $is_user_page ) return;
        $css_path = dirname( __DIR__, 2 ) . '/sh-admin.css';
        $css_url  = trailingslashit( SH_INCLUDES_URL ) . 'apps/sh-admin.css';
        if ( file_exists( $css_path ) ) {
            wp_enqueue_style( 'sh-admin-kit', $css_url, [], filemtime( $css_path ) );
        }
    }

    public static function addUserColumns( array $columns ): array
    {
        $columns['sh_status']        = 'Status';
        $columns['sh_register_type'] = 'Register';
        $columns['sh_actions']       = 'Actions';
        return $columns;
    }

    public static function renderUserColumn( string $output, string $column, int $user_id ): string
    {
        $mm = MembershipManager::getInstance();
        switch ( $column ) {
            case 'sh_status':
                $status = $mm->getActivationStatus( $user_id );
                $colors = [ 'pending' => '#f0b429', 'activated' => '#3b82f6', 'approved' => '#22c55e', 'rejected' => '#ef4444' ];
                $c = $colors[ $status ] ?? '#6b7280';
                return '<span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;background:' . $c . '22;color:' . $c . '">' . esc_html( ucfirst( $status ) ) . '</span>';
            case 'sh_register_type':
                $type = get_user_meta( $user_id, 'register_type', true ) ?: 'email';
                return '<span class="sh-badge sh-badge-gray">' . esc_html( $type ) . '</span>';
            case 'sh_actions':
                return self::renderActionBtns( $user_id );
        }
        return $output;
    }

    private static function renderActionBtns( int $user_id ): string
    {
        $mm     = MembershipManager::getInstance();
        $status = $mm->getActivationStatus( $user_id );
        $nonce  = wp_create_nonce( 'sh_membership_action_' . $user_id );
        $html   = '<div style="display:flex;gap:4px;flex-wrap:wrap">';
        if ( in_array( $status, [ 'activated', 'pending' ], true ) ) {
            $html .= '<a href="' . esc_url( admin_url( 'admin.php?action=sh_approve_user&user_id=' . $user_id . '&_wpnonce=' . $nonce ) ) . '" class="sh-btn sh-btn-primary sh-btn-sm">✓</a>';
            $html .= '<a href="' . esc_url( admin_url( 'admin.php?action=sh_reject_user&user_id=' . $user_id . '&_wpnonce=' . $nonce ) ) . '" class="sh-btn sh-btn-sm" style="background:#fee2e2;color:#ef4444" onclick="return confirm(\'Reject?\')">✗</a>';
        }
        if ( $status === 'rejected' ) {
            $html .= '<a href="' . esc_url( admin_url( 'admin.php?action=sh_approve_user&user_id=' . $user_id . '&_wpnonce=' . $nonce ) ) . '" class="sh-btn sh-btn-primary sh-btn-sm">↩</a>';
        }
        if ( current_user_can( 'manage_options' ) && get_current_user_id() !== $user_id ) {
            $html .= '<a href="' . esc_url( admin_url( 'admin.php?action=sh_impersonate&user_id=' . $user_id . '&_wpnonce=' . $nonce ) ) . '" class="sh-btn sh-btn-sm" title="Login as user">👤</a>';
        }
        $html .= '</div>';
        return $html;
    }

    public static function addBulkActions( array $actions ): array
    {
        $actions['sh_approve']  = 'Approve';
        $actions['sh_reject']   = 'Reject';
        $actions['sh_activate'] = 'Activate';
        return apply_filters( 'sh_membership_bulk_actions', $actions );
    }

    public static function handleBulkActions( string $redirect, string $action, array $user_ids ): string
    {
        if ( ! in_array( $action, [ 'sh_approve', 'sh_reject', 'sh_activate' ], true ) ) {
            do_action( 'sh_membership_bulk_action_' . $action, $user_ids );
            return $redirect;
        }
        $mm = MembershipManager::getInstance();
        $count = 0;
        foreach ( $user_ids as $uid ) {
            $uid = (int) $uid;
            if ( $action === 'sh_approve' )  { $mm->approveUser( $uid );  $count++; }
            if ( $action === 'sh_reject' )   { $mm->rejectUser( $uid );   $count++; }
            if ( $action === 'sh_activate' ) { $mm->activateUser( $uid ); $count++; }
        }
        return add_query_arg( [ 'sh_bulk_done' => $count ], $redirect );
    }

    public static function handleSingleApprove(): void
    {
        $uid = (int) ( $_GET['user_id'] ?? 0 );
        if ( ! $uid || ! check_admin_referer( 'sh_membership_action_' . $uid ) ) wp_die( 'Invalid.' );
        MembershipManager::getInstance()->approveUser( $uid );
        wp_redirect( add_query_arg( [ 'sh_notice' => 'approved' ], admin_url( 'users.php' ) ) ); exit();
    }

    public static function handleSingleReject(): void
    {
        $uid    = (int) ( $_GET['user_id'] ?? 0 );
        $reason = sanitize_text_field( $_GET['reason'] ?? '' );
        if ( ! $uid || ! check_admin_referer( 'sh_membership_action_' . $uid ) ) wp_die( 'Invalid.' );
        MembershipManager::getInstance()->rejectUser( $uid, $reason );
        wp_redirect( add_query_arg( [ 'sh_notice' => 'rejected' ], admin_url( 'users.php' ) ) ); exit();
    }

    public static function handleImpersonation(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized.' );
        $uid = (int) ( $_GET['user_id'] ?? 0 );
        if ( ! $uid || ! check_admin_referer( 'sh_membership_action_' . $uid ) ) wp_die( 'Invalid.' );
        $admin_id = get_current_user_id();
        update_user_meta( $uid, '_sh_impersonated_by', $admin_id );
        update_user_meta( $uid, '_sh_impersonated_at', time() );
        do_action( 'sh_admin_impersonated_user', $admin_id, $uid );
        wp_clear_auth_cookie(); wp_set_current_user( $uid ); wp_set_auth_cookie( $uid );
        wp_redirect( home_url( '/' ) ); exit();
    }

    public static function stopImpersonation(): void
    {
        $uid      = get_current_user_id();
        $admin_id = (int) get_user_meta( $uid, '_sh_impersonated_by', true );
        if ( ! $admin_id ) wp_die( 'Not impersonating.' );
        delete_user_meta( $uid, '_sh_impersonated_by' );
        delete_user_meta( $uid, '_sh_impersonated_at' );
        wp_clear_auth_cookie(); wp_set_current_user( $admin_id ); wp_set_auth_cookie( $admin_id );
        wp_redirect( admin_url( 'users.php' ) ); exit();
    }

    public static function addUserProfileFields( \WP_User $user ): void
    {
        $mm     = MembershipManager::getInstance();
        $status = $mm->getActivationStatus( $user->ID );
        $reg    = get_user_meta( $user->ID, 'register_type', true ) ?: 'email';
        $act_at = get_user_meta( $user->ID, 'activated_at', true );
        $app_at = get_user_meta( $user->ID, 'approved_at', true );
        $rej_at = get_user_meta( $user->ID, 'rejected_at', true );
        $rej_rs = get_user_meta( $user->ID, 'rejection_reason', true );
        $del_rq = get_user_meta( $user->ID, 'deletion_requested_at', true );
        $del_sc = get_user_meta( $user->ID, 'deletion_scheduled_at', true );
        ?>
        <h2>Membership</h2>
        <table class="form-table">
            <tr><th>Status</th><td>
                <select name="sh_user_status">
                    <?php foreach ( [ 'pending', 'activated', 'approved', 'rejected' ] as $s ) : ?>
                        <option value="<?php echo esc_attr($s); ?>" <?php selected($status,$s); ?>><?php echo esc_html(ucfirst($s)); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php wp_nonce_field( 'sh_update_user_status_' . $user->ID, 'sh_status_nonce' ); ?>
            </td></tr>
            <tr><th>Register Type</th><td><?php echo esc_html($reg); ?></td></tr>
            <?php if ($act_at): ?><tr><th>Activated</th><td><?php echo esc_html(date('Y-m-d H:i',$act_at)); ?></td></tr><?php endif; ?>
            <?php if ($app_at): ?><tr><th>Approved</th><td><?php echo esc_html(date('Y-m-d H:i',$app_at)); ?></td></tr><?php endif; ?>
            <?php if ($rej_at): ?><tr><th>Rejected</th><td><?php echo esc_html(date('Y-m-d H:i',$rej_at)); ?><?php if($rej_rs) echo '<br><small>'.esc_html($rej_rs).'</small>'; ?></td></tr><?php endif; ?>
            <?php if ($del_rq): ?><tr><th>Deletion</th><td>Requested: <?php echo esc_html(date('Y-m-d',$del_rq)); ?><?php if($del_sc) echo ' — Scheduled: '.esc_html(date('Y-m-d',$del_sc)); ?></td></tr><?php endif; ?>
        </table>
        <?php
    }

    // ─── MAIN PAGE ────────────────────────────────────────────────────────────

    public static function renderPage(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        $tab         = sanitize_key( $_GET['tab'] ?? 'overview' );
        $stats       = self::getStats();
        $total       = array_sum( $stats );
        $guest_stats = \SaltHareket\Membership\GuestIdentity::getStats();
        ?>
        <div class="sh-wrap" id="sh-membership-page">
        <div class="sh-toolbar">
            <?php $membership_enabled = (bool) \SaltHareket\Membership\MembershipSettings::getSetting('enable_membership'); ?>
            <h1 style="display:inline-flex;align-items:center;gap:8px;">&#128100; Membership
                <span id="sh-membership-header-badge" class="sh-badge <?php echo $membership_enabled ? 'sh-badge-green' : 'sh-badge-gray'; ?>" style="font-size:11px"><?php echo $membership_enabled ? 'Enabled' : 'Disabled'; ?></span>
            </h1>
            <span class="sh-badge sh-badge-blue"><?php echo (int)$total; ?> member<?php echo $total !== 1 ? 's' : ''; ?></span>
            <?php if ( $stats['activated'] > 0 ) : ?>
                <span class="sh-badge sh-badge-blue"><?php echo (int)$stats['activated']; ?> awaiting approval</span>
            <?php endif; ?>
            <div class="sh-toolbar-right">
                <a href="?page=sh-membership&tab=overview" class="sh-tab-btn <?php echo $tab==='overview'?'active':''; ?>">Overview</a>
                <a href="?page=sh-membership&tab=pending"  class="sh-tab-btn <?php echo $tab==='pending' ?'active':''; ?>">Pending<?php if($stats['activated']>0) echo ' ('.(int)$stats['activated'].')'; ?></a>
                <a href="?page=sh-membership&tab=guests"   class="sh-tab-btn <?php echo $tab==='guests'  ?'active':''; ?>">Guests <span style="background:#6b728022;color:#6b7280;padding:1px 6px;border-radius:10px;font-size:10px;margin-left:2px;"><?php echo (int)$guest_stats['total']; ?></span></a>
                <a href="?page=sh-membership&tab=settings" class="sh-tab-btn <?php echo $tab==='settings'?'active':''; ?>">Settings</a>
            </div>
        </div>
        <?php
        if ( isset($_GET['sh_notice']) ) {
            $n = sanitize_text_field($_GET['sh_notice']);
            if ($n==='approved') echo '<div class="sh-notice sh-notice-success sh-inline">&#10003; User approved.</div>';
            if ($n==='rejected') echo '<div class="sh-notice sh-notice-warning sh-inline">&#9888; User rejected.</div>';
        }
        if ( isset($_GET['sh_bulk_done']) ) echo '<div class="sh-notice sh-notice-success sh-inline">&#10003; '.(int)$_GET['sh_bulk_done'].' users updated.</div>';
        if ( isset($_GET['saved']) ) echo '<div class="sh-notice sh-notice-success sh-inline">&#10003; Settings saved.</div>';

        // ── Enable Membership toggle ───────────────────────────────────────
        $mem_nonce  = wp_create_nonce( 'sh_membership_admin' );
        $mem_ajax   = esc_url( admin_url( 'admin-ajax.php' ) );
        ?>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:14px 20px;margin-bottom:20px;display:flex;align-items:center;gap:14px;">
            <strong style="font-size:13px;color:#1d2327;">Enable Membership</strong>
            <label class="sh-toggle">
                <input type="checkbox" id="sh-membership-app-toggle"
                       <?php checked( $membership_enabled ); ?>
                       onchange="shMembershipAppToggle(this.checked)">
                <span class="sh-toggle-slider"></span>
            </label>
            <span id="sh-membership-app-label" style="font-size:12px;color:<?php echo $membership_enabled ? '#00a32a' : '#9ca3af'; ?>">
                <?php echo $membership_enabled ? 'Enabled' : 'Disabled'; ?>
            </span>
            <span id="sh-membership-app-saving" style="display:none;font-size:12px;color:#9ca3af">
                <span style="display:inline-block;width:12px;height:12px;border:2px solid #ddd;border-top-color:#2271b1;border-radius:50%;animation:sh-spin .6s linear infinite;vertical-align:middle;margin-right:4px"></span>Saving...
            </span>
        </div>
        <script>
        function shMembershipAppToggle(enabled) {
            var label  = document.getElementById('sh-membership-app-label');
            var saving = document.getElementById('sh-membership-app-saving');
            var toggle = document.getElementById('sh-membership-app-toggle');
            if (saving) saving.style.display = 'inline-flex';
            if (label)  label.style.display  = 'none';
            var fd = new FormData();
            fd.append('action',   'sh_membership_save_settings');
            fd.append('nonce',    <?php echo wp_json_encode( $mem_nonce ); ?>);
            fd.append('settings[enable_membership]', enabled ? '1' : '0');
            fetch(<?php echo wp_json_encode( $mem_ajax ); ?>, { method:'POST', body:fd, credentials:'same-origin' })
                .then(function(r){ return r.json(); })
                .then(function(res) {
                    if (saving) saving.style.display = 'none';
                    if (label)  label.style.display  = '';
                    if (res.success) {
                        label.textContent = enabled ? 'Enabled' : 'Disabled';
                        label.style.color = enabled ? '#00a32a' : '#9ca3af';
                        var hb = document.getElementById('sh-membership-header-badge');
                        if (hb) { hb.textContent = enabled ? 'Enabled' : 'Disabled'; hb.className = 'sh-badge ' + (enabled ? 'sh-badge-green' : 'sh-badge-gray'); }
                    } else {
                        if (toggle) toggle.checked = !enabled;
                        label.textContent = !enabled ? 'Enabled' : 'Disabled';
                        label.style.color = !enabled ? '#00a32a' : '#9ca3af';
                    }
                })
                .catch(function() {
                    if (saving) saving.style.display = 'none';
                    if (label)  label.style.display  = '';
                    if (toggle) toggle.checked = !enabled;
                });
        }
        </script>
        <?php

        if ( $tab === 'pending' )       self::renderPendingTab();
        elseif ( $tab === 'guests' )    self::renderGuestsTab( $guest_stats );
        elseif ( $tab === 'settings' )  self::renderSettingsTab();
        else                            self::renderOverviewTab( $stats, $total );
        ?>
        <div id="sh-toast"></div>
        </div>
        <?php
    }

    private static function getStats(): array
    {
        global $wpdb;
        $counts = array_fill_keys( [ 'pending', 'activated', 'approved', 'rejected' ], 0 );
        foreach ( array_keys($counts) as $s ) {
            $counts[$s] = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key='user_status' AND meta_value=%s", $s
            ) );
        }
        $counts['approved'] += (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key='user_status' AND meta_value='1'"
        );
        return $counts;
    }

    private static function renderOverviewTab( array $stats, int $total ): void
    {
        $cfg    = [
            'pending'   => [ 'label'=>'Pending',   'color'=>'#f0b429', 'icon'=>'⏳' ],
            'activated' => [ 'label'=>'Activated', 'color'=>'#3b82f6', 'icon'=>'✅' ],
            'approved'  => [ 'label'=>'Approved',  'color'=>'#22c55e', 'icon'=>'👍' ],
            'rejected'  => [ 'label'=>'Rejected',  'color'=>'#ef4444', 'icon'=>'❌' ],
        ];
        $recent = get_users( [ 'number'=>10, 'orderby'=>'registered', 'order'=>'DESC' ] );
        ?>
        <div class="sh-layout"><div class="sh-main">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px;margin-bottom:24px">
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;text-align:center">
                <div style="font-size:28px;font-weight:700;color:#1d2327"><?php echo $total; ?></div>
                <div style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:#6b7280;margin-top:4px">Total</div>
            </div>
            <?php foreach ( $cfg as $slug => $c ) : ?>
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;text-align:center;border-top:3px solid <?php echo $c['color']; ?>">
                <div style="font-size:28px;font-weight:700;color:<?php echo $c['color']; ?>"><?php echo (int)$stats[$slug]; ?></div>
                <div style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:#6b7280;margin-top:4px"><?php echo $c['icon'].' '.$c['label']; ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden">
            <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between">
                <strong>Recent Members</strong>
                <a href="<?php echo esc_url(admin_url('users.php')); ?>" class="sh-btn sh-btn-sm">View All →</a>
            </div>
            <table style="width:100%;border-collapse:collapse"><thead><tr>
                <?php foreach(['User','Email','Status','Registered','Actions'] as $h): ?>
                <th style="padding:10px 18px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #f3f4f6"><?php echo $h; ?></th>
                <?php endforeach; ?>
            </tr></thead><tbody>
            <?php foreach ( $recent as $u ) :
                $mm=$mm??MembershipManager::getInstance(); $status=$mm->getActivationStatus($u->ID);
                $colors=['pending'=>'#f0b429','activated'=>'#3b82f6','approved'=>'#22c55e','rejected'=>'#ef4444'];
                $col=$colors[$status]??'#6b7280'; ?>
            <tr style="border-bottom:1px solid #f9fafb">
                <td style="padding:10px 18px"><strong><?php echo esc_html($u->display_name); ?></strong></td>
                <td style="padding:10px 18px;color:#6b7280;font-size:13px"><?php echo esc_html($u->user_email); ?></td>
                <td style="padding:10px 18px"><span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;background:<?php echo $col; ?>22;color:<?php echo $col; ?>"><?php echo esc_html(ucfirst($status)); ?></span></td>
                <td style="padding:10px 18px;color:#6b7280;font-size:13px"><?php echo esc_html(date('Y-m-d',strtotime($u->user_registered))); ?></td>
                <td style="padding:10px 18px"><?php echo self::renderActionBtns($u->ID); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody></table>
        </div>
        </div><div class="sh-sidebar"><?php self::renderSidebar(); ?></div></div>
        <?php
    }

    private static function renderPendingTab(): void
    {
        $users = get_users(['meta_key'=>'user_status','meta_value'=>'activated','number'=>100,'orderby'=>'registered','order'=>'DESC']);
        ?>
        <div class="sh-layout"><div class="sh-main">
        <?php if (empty($users)) : ?>
            <div class="sh-empty-box"><p style="margin:0 0 4px;font-weight:500">No pending approvals</p><p style="margin:0;font-size:12px;color:#9ca3af">All activated users have been reviewed.</p></div>
        <?php else : ?>
            <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden">
                <thead><tr><?php foreach(['User','Email','Register Type','Activated','Actions'] as $h): ?><th style="padding:10px 18px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #f3f4f6"><?php echo $h; ?></th><?php endforeach; ?></tr></thead>
                <tbody>
                <?php foreach ($users as $u) :
                    $nonce=wp_create_nonce('sh_membership_action_'.$u->ID);
                    $reg=get_user_meta($u->ID,'register_type',true)?:'email';
                    $act_at=get_user_meta($u->ID,'activated_at',true); ?>
                <tr style="border-bottom:1px solid #f9fafb">
                    <td style="padding:10px 18px"><strong><?php echo esc_html($u->display_name); ?></strong><br><small style="color:#9ca3af">#<?php echo $u->ID; ?></small></td>
                    <td style="padding:10px 18px;color:#6b7280;font-size:13px"><?php echo esc_html($u->user_email); ?></td>
                    <td style="padding:10px 18px"><span class="sh-badge sh-badge-gray"><?php echo esc_html($reg); ?></span></td>
                    <td style="padding:10px 18px;color:#6b7280;font-size:13px"><?php echo $act_at?esc_html(date('Y-m-d H:i',$act_at)):'—'; ?></td>
                    <td style="padding:10px 18px"><?php echo self::renderActionBtns($u->ID); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        </div><div class="sh-sidebar"><?php self::renderSidebar(); ?></div></div>
        <?php
    }

    private static function renderGuestsTab( array $guest_stats ): void
    {
        ?>
        <div class="sh-layout"><div class="sh-main">
        <div class="sh-card">
            <div style="padding:14px 20px;border-bottom:1px solid #f3f4f6"><strong>Guest Statistics</strong></div>
            <div style="padding:18px;display:flex;gap:20px;flex-wrap:wrap">
                <?php foreach (['total'=>'Total','active_today'=>'Active Today','inactive'=>'Inactive'] as $k=>$l) : ?>
                <div style="text-align:center;padding:16px 24px;background:#f9fafb;border-radius:8px">
                    <div style="font-size:28px;font-weight:700"><?php echo (int)($guest_stats[$k]??0); ?></div>
                    <div style="font-size:12px;color:#6b7280"><?php echo $l; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="padding:0 18px 18px">
                <button type="button" class="sh-btn" id="sh-purge-guests" style="color:#d63638">🗑 Purge Inactive Guests</button>
            </div>
        </div>
        </div><div class="sh-sidebar"><?php self::renderSidebar(); ?></div></div>
        <script>
        (function(){
            var btn = document.getElementById('sh-purge-guests');
            if (!btn) return;
            btn.addEventListener('click', function() {
                if (!confirm('Purge all inactive guests?')) return;
                btn.disabled = true; btn.textContent = 'Purging...';
                fetch(ajaxurl + '?action=sh_guest_stats', {method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'nonce='+encodeURIComponent('<?php echo wp_create_nonce('sh_membership_admin'); ?>')+'&purge=1'})
                .then(r=>r.json()).then(function(res){
                    btn.disabled = false;
                    btn.textContent = '🗑 Purge Inactive Guests';
                });
            });
        })();
        </script>
        <?php
    }

    // ─── Settings Tab ─────────────────────────────────────────────────────────

    public static function ajaxSaveSettings(): void
    {
        check_ajax_referer( 'sh_membership_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        $data = $_POST['settings'] ?? [];
        if ( isset( $data['custom_roles'] ) && is_string( $data['custom_roles'] ) ) {
            $data['custom_roles'] = json_decode( stripslashes( $data['custom_roles'] ), true ) ?? [];
        }
        if ( isset( $data['selectable_roles'] ) && is_string( $data['selectable_roles'] ) ) {
            $data['selectable_roles'] = json_decode( stripslashes( $data['selectable_roles'] ), true ) ?? [];
        }
        if ( isset( $data['register_fields'] ) && is_string( $data['register_fields'] ) ) {
            $data['register_fields'] = json_decode( stripslashes( $data['register_fields'] ), true ) ?? [];
        }
        \SaltHareket\Membership\MembershipSettings::save( $data );

        $response = [
            'message'  => 'Settings saved.',
            'settings' => \SaltHareket\Membership\MembershipSettings::get(),
        ];

        // Activation type SMS seçildi ama SMS app disabled/unconfigured ise — dependency warning ekle
        $activation_type = sanitize_text_field( $data['membership_activation_type'] ?? '' );
        if (
            $activation_type === 'sms' &&
            class_exists( \SaltHareket\Sms\SmsApp::class ) &&
            ! \SaltHareket\Sms\SmsApp::isAvailable()
        ) {
            $response['warnings'][] = [
                'type'       => 'dependency',
                'dependency' => \SaltHareket\Sms\SmsApp::dependency(),
            ];
        }

        wp_send_json_success( $response );
    }

    private static function renderSettingsTab(): void
    {
        require_once __DIR__ . '/SettingsTabRenderer.php';
        \SaltHareket\Membership\Admin\SettingsTabRenderer::render();
    }

    public static function renderSidebarPublic(): void { self::renderSidebar(); }

    // ─── Guest Stats AJAX ─────────────────────────────────────────────────────

    public static function ajaxGuestStats(): void
    {
        check_ajax_referer( 'sh_membership_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        wp_send_json_success( \SaltHareket\Membership\GuestIdentity::getStats() );
    }

    // ─── Sidebar ──────────────────────────────────────────────────────────────

    private static function renderSidebar(): void
    {
        ?>
        <div class="sh-sidebar-box">
            <h3>Quick Links</h3>
            <ul style="margin:0;padding:0;list-style:none">
                <li style="padding:6px 0;border-bottom:1px solid #f3f4f6"><a href="<?php echo esc_url(admin_url('users.php')); ?>">→ All Users</a></li>
                <li style="padding:6px 0;border-bottom:1px solid #f3f4f6"><a href="<?php echo esc_url(admin_url('user-new.php')); ?>">→ Add New User</a></li>
                <li style="padding:6px 0"><a href="<?php echo esc_url(admin_url('users.php?meta_key=user_status&meta_value=activated')); ?>">→ Pending Approval</a></li>
            </ul>
        </div>
        <div class="sh-sidebar-box" style="margin-top:12px">
            <h3>System</h3>
            <ul style="margin:0;padding:0;list-style:none;font-size:12px;color:#6b7280">
                <li style="padding:4px 0">WooCommerce: <strong><?php echo MembershipManager::isWooActive() ? '✓ Active' : '✗ Inactive'; ?></strong></li>
                <li style="padding:4px 0">Activation: <strong><?php echo MembershipManager::isActivationRequired() ? '✓ Required' : '✗ Not required'; ?></strong></li>
                <li style="padding:4px 0">Social Login: <strong><?php echo (defined('ENABLE_SOCIAL_LOGIN') && ENABLE_SOCIAL_LOGIN) ? '✓ Active' : '✗ Inactive'; ?></strong></li>
            </ul>
        </div>
        <?php
    }
}
