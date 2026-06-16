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
        add_action( 'admin_menu', [ self::class, 'addMenuPage' ], 25 );
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
        $nonce  = wp_create_nonce( 'sh_membership_action_' . $user->ID );
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

        $tab   = sanitize_key( $_GET['tab'] ?? 'overview' );
        $stats = self::getStats();
        $total = array_sum( $stats );
        $guest_stats = \SaltHareket\Membership\GuestIdentity::getStats();
        ?>
        <div class="sh-wrap" id="sh-membership-page">

        <div class="sh-toolbar">
            <h1>👤 Membership</h1>
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
        $titles = [
            'overview' => [ 'title' => 'Membership Overview',  'desc' => 'Member statistics and account status breakdown.' ],
            'pending'  => [ 'title' => 'Pending Approvals',    'desc' => 'Users who activated their account and are awaiting admin approval.' ],
            'guests'   => [ 'title' => 'Guest Management',     'desc' => 'Anonymous visitors tracked via cookie. Cleanup inactive guests to keep the database lean.' ],
            'settings' => [ 'title' => 'Membership Settings',  'desc' => 'Current membership configuration and available hooks.' ],
        ];
        $cur = $titles[$tab] ?? $titles['overview'];
        ?>
        <div class="sh-section-title">
            <h2><?php echo esc_html($cur['title']); ?></h2>
            <p><?php echo esc_html($cur['desc']); ?></p>
        </div>

        <?php if ( isset($_GET['sh_notice']) ) :
            $n = sanitize_text_field($_GET['sh_notice']);
            if ($n==='approved') echo '<div class="sh-notice sh-notice-success sh-inline">&#10003; User approved.</div>';
            if ($n==='rejected') echo '<div class="sh-notice sh-notice-warning sh-inline">&#9888; User rejected.</div>';
        endif; ?>
        <?php if ( isset($_GET['sh_bulk_done']) ) : ?>
            <div class="sh-notice sh-notice-success sh-inline">&#10003; <?php echo (int)$_GET['sh_bulk_done']; ?> users updated.</div>
        <?php endif; ?>
        <?php if ( isset($_GET['saved']) ) : ?>
            <div class="sh-notice sh-notice-success sh-inline">&#10003; Settings saved.</div>
        <?php endif; ?>

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

    // ─── Stats ────────────────────────────────────────────────────────────────

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

    // ─── Overview Tab ─────────────────────────────────────────────────────────

    private static function renderOverviewTab( array $stats, int $total ): void
    {
        $cfg = [
            'pending'   => [ 'label' => 'Pending',   'color' => '#f0b429', 'icon' => '⏳' ],
            'activated' => [ 'label' => 'Activated', 'color' => '#3b82f6', 'icon' => '✅' ],
            'approved'  => [ 'label' => 'Approved',  'color' => '#22c55e', 'icon' => '👍' ],
            'rejected'  => [ 'label' => 'Rejected',  'color' => '#ef4444', 'icon' => '❌' ],
        ];
        $recent = get_users( [ 'number' => 10, 'orderby' => 'registered', 'order' => 'DESC' ] );
        ?>
        <div class="sh-layout">
        <div class="sh-main">

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
                <a href="<?php echo esc_url(admin_url('users.php')); ?>" class="sh-btn sh-btn-sm" style="font-size:12px">View All →</a>
            </div>
            <table style="width:100%;border-collapse:collapse">
                <thead><tr>
                    <?php foreach(['User','Email','Status','Registered','Actions'] as $h): ?>
                    <th style="padding:10px 18px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #f3f4f6"><?php echo $h; ?></th>
                    <?php endforeach; ?>
                </tr></thead>
                <tbody>
                <?php foreach ( $recent as $u ) :
                    $mm     = MembershipManager::getInstance();
                    $status = $mm->getActivationStatus( $u->ID );
                    $nonce  = wp_create_nonce( 'sh_membership_action_' . $u->ID );
                    $colors = [ 'pending'=>'#f0b429','activated'=>'#3b82f6','approved'=>'#22c55e','rejected'=>'#ef4444' ];
                    $col    = $colors[$status] ?? '#6b7280';
                ?>
                <tr style="border-bottom:1px solid #f9fafb">
                    <td style="padding:10px 18px"><strong><?php echo esc_html($u->display_name); ?></strong></td>
                    <td style="padding:10px 18px;color:#6b7280;font-size:13px"><?php echo esc_html($u->user_email); ?></td>
                    <td style="padding:10px 18px"><span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;background:<?php echo $col; ?>22;color:<?php echo $col; ?>"><?php echo esc_html(ucfirst($status)); ?></span></td>
                    <td style="padding:10px 18px;color:#6b7280;font-size:13px"><?php echo esc_html(date('Y-m-d',strtotime($u->user_registered))); ?></td>
                    <td style="padding:10px 18px"><?php echo self::renderActionBtns($u->ID); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        </div>
        <div class="sh-sidebar"><?php self::renderSidebar(); ?></div>
        </div>
        <?php
    }

    // ─── Pending Tab ──────────────────────────────────────────────────────────

    private static function renderPendingTab(): void
    {
        $users = get_users( [ 'meta_key'=>'user_status','meta_value'=>'activated','number'=>100,'orderby'=>'registered','order'=>'DESC' ] );
        ?>
        <div class="sh-layout">
        <div class="sh-main">
        <?php if ( empty($users) ) : ?>
            <div class="sh-empty-box">
                <p style="margin:0 0 4px;font-weight:500;color:#50575e">No pending approvals</p>
                <p style="margin:0;font-size:12px;color:#9ca3af">All activated users have been reviewed.</p>
            </div>
        <?php else : ?>
            <div class="sh-filter-bar">
                <span class="sh-count-label"><?php echo count($users); ?> user<?php echo count($users)!==1?'s':''; ?> awaiting approval</span>
            </div>
            <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden">
                <thead><tr>
                    <?php foreach(['User','Email','Register Type','Activated','Actions'] as $h): ?>
                    <th style="padding:10px 18px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #f3f4f6"><?php echo $h; ?></th>
                    <?php endforeach; ?>
                </tr></thead>
                <tbody>
                <?php foreach ( $users as $u ) :
                    $nonce  = wp_create_nonce( 'sh_membership_action_' . $u->ID );
                    $reg    = get_user_meta( $u->ID, 'register_type', true ) ?: 'email';
                    $act_at = get_user_meta( $u->ID, 'activated_at', true );
                ?>
                <tr style="border-bottom:1px solid #f9fafb">
                    <td style="padding:10px 18px"><strong><?php echo esc_html($u->display_name); ?></strong><br><small style="color:#9ca3af">#<?php echo $u->ID; ?></small></td>
                    <td style="padding:10px 18px;color:#6b7280;font-size:13px"><?php echo esc_html($u->user_email); ?></td>
                    <td style="padding:10px 18px"><span class="sh-badge sh-badge-gray"><?php echo esc_html($reg); ?></span></td>
                    <td style="padding:10px 18px;color:#6b7280;font-size:13px"><?php echo $act_at ? esc_html(date('Y-m-d H:i',$act_at)) : '—'; ?></td>
                    <td style="padding:10px 18px"><?php echo self::renderActionBtns($u->ID); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        </div>
        <div class="sh-sidebar"><?php self::renderSidebar(); ?></div>
        </div>
        <?php
    }

    // ─── Settings Tab ─────────────────────────────────────────────────────────

    public static function ajaxSaveSettings(): void
    {
        check_ajax_referer( 'sh_membership_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $data = $_POST['settings'] ?? [];

        \SaltHareket\Membership\MembershipSettings::save( [
            'enable_membership'                 => ! empty( $data['enable_membership'] ),
            'enable_membership_activation'      => ! empty( $data['enable_membership_activation'] ),
            'membership_activation_type'        => sanitize_key( $data['membership_activation_type'] ?? 'email' ),
            'enable_activation_email_autologin' => ! empty( $data['enable_activation_email_autologin'] ),
            'enable_registration'               => ! empty( $data['enable_registration'] ),
            'enable_remember_login'             => ! empty( $data['enable_remember_login'] ),
            'enable_lost_password'              => ! empty( $data['enable_lost_password'] ),
            'enable_password_recover'           => ! empty( $data['enable_password_recover'] ),
            'password_recover_type'             => sanitize_key( $data['password_recover_type'] ?? 'link' ),
            'enable_social_login'               => ! empty( $data['enable_social_login'] ),
            'enable_postcode_validation'        => ! empty( $data['enable_postcode_validation'] ),
            'enable_chat'                       => ! empty( $data['enable_chat'] ),
        ] );

        wp_send_json_success( [
            'message'  => 'Settings saved. Page will reload.',
            'settings' => \SaltHareket\Membership\MembershipSettings::get(),
        ] );
    }

    private static function renderSettingsTab(): void
    {
        $s     = \SaltHareket\Membership\MembershipSettings::get();
        $nonce = wp_create_nonce( 'sh_membership_admin' );

        $act_type = $s['membership_activation_type'] ?? 'email';
        $pw_type  = $s['password_recover_type'] ?? 'link';
        ?>
        <div class="sh-layout">
        <div class="sh-main">
        <div id="sh-membership-toast" style="position:fixed;bottom:24px;right:24px;z-index:9999"></div>

        <div class="sh-card">

            <!-- Core toggle -->
            <div style="padding:16px 20px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;gap:14px">
                <span style="font-size:15px">👤</span>
                <strong style="font-size:14px">Membership</strong>
                <label class="sh-toggle" style="margin-left:4px">
                    <input type="checkbox" id="shm-enable-membership" <?php checked( $s['enable_membership'] ); ?> onchange="shmToggle('membership',this.checked)">
                    <span class="sh-toggle-slider"></span>
                </label>
                <span style="font-size:12px;color:#9ca3af">Üyelik sistemini aç/kapat</span>
            </div>

            <!-- Options wrap -->
            <div id="shm-membership-opts" style="<?php echo ! $s['enable_membership'] ? 'display:none' : ''; ?>">

            <!-- Account section -->
            <div style="padding:14px 20px;border-bottom:1px solid #f3f4f6">
                <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:#9ca3af;margin-bottom:12px">Account</div>
                <?php
                $social_ok = class_exists( 'NextendSocialLogin' );
                foreach ( [
                    [ 'shm-enable-registration',   'enable_registration',        'Enable Registration',       '',                                          true,  '' ],
                    [ 'shm-enable-remember-login',  'enable_remember_login',     'Enable Remember Login',     '',                                          true,  '' ],
                    [ 'shm-enable-social-login',    'enable_social_login',       'Enable Social Login',       'NextendSocialLogin plugin gerekli',          $social_ok, 'NextendSocialLogin' ],
                    [ 'shm-enable-postcode',        'enable_postcode_validation','Enable Postcode Validation','',                                          true,  '' ],
                ] as [ $id, $key, $label, $desc, $plugin_ok, $plugin_name ] ) :
                    $is_disabled = ! $plugin_ok;
                    $is_checked  = $plugin_ok && ( $s[$key] ?? false );
                ?>
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px;<?php echo $is_disabled ? 'opacity:.55' : ''; ?>">
                    <label class="sh-toggle"><input type="checkbox" id="<?php echo $id; ?>" <?php checked( $is_checked ); ?> <?php echo $is_disabled ? 'disabled' : ''; ?>><span class="sh-toggle-slider"></span></label>
                    <div>
                        <span style="font-size:13px;font-weight:500"><?php echo esc_html($label); ?></span>
                        <?php if ($desc): ?><br><span style="font-size:11px;color:<?php echo $is_disabled ? '#e11d48' : '#9ca3af'; ?>"><?php echo esc_html($desc); ?></span><?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Activation section -->
            <div style="padding:14px 20px;border-bottom:1px solid #f3f4f6">
                <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:#9ca3af;margin-bottom:12px">Activation</div>
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
                    <label class="sh-toggle"><input type="checkbox" id="shm-enable-membership-activation" <?php checked($s['enable_membership_activation']); ?> onchange="shmToggle('activation',this.checked)"><span class="sh-toggle-slider"></span></label>
                    <div><span style="font-size:13px;font-weight:500">Enable Membership Activation</span><br><span style="font-size:11px;color:#9ca3af">Kayıt sonrası aktivasyon gereksin mi?</span></div>
                </div>
                <div id="shm-activation-opts" style="margin-left:44px;<?php echo ! $s['enable_membership_activation'] ? 'display:none' : ''; ?>">
                    <div style="display:flex;gap:20px;margin-bottom:10px">
                        <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer"><input type="radio" name="shm_activation_type" value="email" <?php checked($act_type,'email'); ?> onchange="shmToggle('activation_type','email')"> by Email</label>
                        <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer"><input type="radio" name="shm_activation_type" value="sms" <?php checked($act_type,'sms'); ?> onchange="shmToggle('activation_type','sms')"> by SMS</label>
                    </div>
                    <div id="shm-autologin-wrap" style="<?php echo $act_type!=='email' ? 'display:none' : ''; ?>">
                        <div style="display:flex;align-items:center;gap:12px">
                            <label class="sh-toggle"><input type="checkbox" id="shm-enable-activation-email-autologin" <?php checked($s['enable_activation_email_autologin']); ?>><span class="sh-toggle-slider"></span></label>
                            <span style="font-size:13px;font-weight:500">Enable Auto-Login with activation code</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Password section -->
            <div style="padding:14px 20px;border-bottom:1px solid #f3f4f6">
                <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:#9ca3af;margin-bottom:12px">Password</div>
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
                    <label class="sh-toggle"><input type="checkbox" id="shm-enable-lost-password" <?php checked($s['enable_lost_password']); ?>><span class="sh-toggle-slider"></span></label>
                    <span style="font-size:13px;font-weight:500">Enable Lost Password</span>
                </div>
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
                    <label class="sh-toggle"><input type="checkbox" id="shm-enable-password-recover" <?php checked($s['enable_password_recover']); ?> onchange="shmToggle('password_recover',this.checked)"><span class="sh-toggle-slider"></span></label>
                    <span style="font-size:13px;font-weight:500">Enable Password Recover</span>
                </div>
                <div id="shm-pw-recover-opts" style="margin-left:44px;<?php echo ! $s['enable_password_recover'] ? 'display:none' : ''; ?>">
                    <div style="display:flex;gap:20px">
                        <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer"><input type="radio" name="shm_pw_recover_type" value="link" <?php checked($pw_type,'link'); ?>> Send link to create new one</label>
                        <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer"><input type="radio" name="shm_pw_recover_type" value="generated" <?php checked($pw_type,'generated'); ?>> Send a new generated password</label>
                    </div>
                </div>
            </div>

            <!-- Integrations section -->
            <div style="padding:14px 20px;border-bottom:1px solid #f3f4f6">
                <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:#9ca3af;margin-bottom:12px">Integrations</div>
                <?php
                $chat_ok       = class_exists( 'Redq_YoBro' );
                $chat_disabled = ! $chat_ok;
                $chat_checked  = $chat_ok && ( $s['enable_chat'] ?? false );
                ?>
                <div style="display:flex;align-items:center;gap:12px;<?php echo $chat_disabled ? 'opacity:.55' : ''; ?>">
                    <label class="sh-toggle"><input type="checkbox" id="shm-enable-chat" <?php checked( $chat_checked ); ?> <?php echo $chat_disabled ? 'disabled' : ''; ?>><span class="sh-toggle-slider"></span></label>
                    <div>
                        <span style="font-size:13px;font-weight:500">Enable Chat</span><br>
                        <span style="font-size:11px;color:<?php echo $chat_disabled ? '#e11d48' : '#9ca3af'; ?>">YoBro plugin gerekli</span>
                    </div>
                </div>
            </div>

            <!-- Save -->
            <div style="padding:16px 20px">
                <button type="button" id="shm-save-settings" class="sh-btn sh-btn-primary">💾 Save Settings</button>
            </div>

            </div><!-- /shm-membership-opts -->
        </div><!-- .sh-card -->

        <!-- Hooks & Filters -->
        <div class="sh-card" style="margin-top:20px">
            <div style="padding:12px 20px;border-bottom:1px solid #f3f4f6"><strong style="font-size:13px">Hooks & Filters</strong></div>
            <table style="width:100%;border-collapse:collapse"><tbody>
            <?php foreach ( [
                [ 'sh_account_activated',     'action', 'Hesap aktive edilince' ],
                [ 'sh_account_approved',      'action', 'Admin onaylayınca' ],
                [ 'sh_account_rejected',      'action', 'Admin reddedince' ],
                [ 'sh_user_registered',       'action', 'Yeni kullanıcı kayıtlı' ],
                [ 'sh_profile_updated',       'action', 'Profil güncellendi' ],
                [ 'sh_password_changed',      'action', 'Şifre değiştirildi' ],
                [ 'sh_membership_menu_items', 'filter', 'Hesap menüsüne item ekle' ],
                [ 'sh_register_default_role', 'filter', 'Kayıt rolünü override et' ],
            ] as [ $name, $type, $desc ] ) :
                $col = $type === 'action' ? '#8b5cf6' : '#0ea5e9'; ?>
            <tr style="border-bottom:1px solid #f9fafb">
                <td style="padding:8px 20px;font-family:Consolas,monospace;font-size:12px"><?php echo esc_html($name); ?></td>
                <td style="padding:8px 20px"><span style="padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;background:<?php echo $col; ?>22;color:<?php echo $col; ?>"><?php echo $type; ?></span></td>
                <td style="padding:8px 20px;font-size:12px;color:#6b7280"><?php echo esc_html($desc); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody></table>
        </div>

        </div><!-- .sh-main -->
        <div class="sh-sidebar"><?php self::renderSidebar(); ?></div>
        </div><!-- .sh-layout -->

        <script>
        function shmToggle(type, val) {
            var map = { membership:'shm-membership-opts', activation:'shm-activation-opts', password_recover:'shm-pw-recover-opts' };
            if (map[type]) { document.getElementById(map[type]).style.display = val ? '' : 'none'; }
            else if (type === 'activation_type') { document.getElementById('shm-autologin-wrap').style.display = (val==='email') ? '' : 'none'; }
        }
        function shmToast(msg, isError) {
            var el = document.getElementById('sh-membership-toast');
            if (!el) return;
            var item = document.createElement('div');
            item.className = 'sh-toast-item' + (isError ? ' sh-toast-error' : ' sh-toast-success');
            item.textContent = msg; el.appendChild(item);
            setTimeout(function(){ item.remove(); }, 3500);
        }
        jQuery(function($) {
            $('#shm-save-settings').on('click', function() {
                var $btn = $(this).prop('disabled', true).text('Saving...');
                $.post(ajaxurl, { action:'sh_membership_save_settings', nonce:'<?php echo esc_js($nonce); ?>', settings:{
                    enable_membership:                 $('#shm-enable-membership').is(':checked')?1:0,
                    enable_registration:               $('#shm-enable-registration').is(':checked')?1:0,
                    enable_remember_login:             $('#shm-enable-remember-login').is(':checked')?1:0,
                    enable_social_login:               $('#shm-enable-social-login').is(':checked')?1:0,
                    enable_postcode_validation:        $('#shm-enable-postcode').is(':checked')?1:0,
                    enable_membership_activation:      $('#shm-enable-membership-activation').is(':checked')?1:0,
                    membership_activation_type:        $('input[name="shm_activation_type"]:checked').val()||'email',
                    enable_activation_email_autologin: $('#shm-enable-activation-email-autologin').is(':checked')?1:0,
                    enable_lost_password:              $('#shm-enable-lost-password').is(':checked')?1:0,
                    enable_password_recover:           $('#shm-enable-password-recover').is(':checked')?1:0,
                    password_recover_type:             $('input[name="shm_pw_recover_type"]:checked').val()||'link',
                    enable_chat:                       $('#shm-enable-chat').is(':checked')?1:0,
                }}, function(res) {
                    $btn.prop('disabled',false).text('💾 Save Settings');
                    if (res.success) { shmToast('✓ Saved. Reloading...',false); setTimeout(function(){ location.reload(); },1200); }
                    else shmToast('✗ '+(res.data||'Error'),true);
                }).fail(function(){ $btn.prop('disabled',false).text('💾 Save Settings'); shmToast('✗ Request failed.',true); });
            });
        });
        </script>
        <?php
    }

    // ─── Guests Tab ───────────────────────────────────────────────────────────

    private static function renderGuestsTab( array $gs ): void
    {
        $cleanup_days = (int) get_option( 'sh_guest_cleanup_days', 90 );
        $next_cron    = wp_next_scheduled( 'sh_guest_cleanup_cron' );
        $nonce        = wp_create_nonce( 'sh_membership_admin' );
        ?>
        <div class="sh-layout">
        <div class="sh-main">

        <!-- Stats cards -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px;margin-bottom:24px;">
            <?php
            $cards = [
                [ 'Total Guests',    $gs['total'],      '#6b7280' ],
                [ 'With Lead Data',  $gs['with_email'], '#2271b1' ],
                [ 'Merged to User',  $gs['merged'],     '#22c55e' ],
                [ 'Inactive',        $gs['inactive'],   '#f59e0b' ],
                [ 'Purgeable',       $gs['purgeable'],  '#ef4444' ],
            ];
            foreach ( $cards as [ $label, $val, $color ] ) : ?>
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;text-align:center;border-top:3px solid <?php echo $color; ?>;">
                <div style="font-size:28px;font-weight:700;color:<?php echo $color; ?>;"><?php echo number_format_i18n( $val ); ?></div>
                <div style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:#6b7280;margin-top:4px;"><?php echo $label; ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Cleanup info -->
        <div class="sh-table-wrap" style="margin-bottom:20px;">
            <div style="padding:14px 16px;border-bottom:1px solid #e5e7eb;">
                <h2 style="margin:0;font-size:15px;font-weight:600;">&#128465; Guest Cleanup</h2>
            </div>
            <div style="padding:20px;">
                <p style="margin:0 0 12px;font-size:13px;color:#6b7280;">
                    Guests are purged if they are <strong>merged to a user</strong> OR have <strong>no lead data</strong> and haven't been active for <strong><?php echo $cleanup_days; ?> days</strong>.
                </p>

                <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                    <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:10px 16px;font-size:13px;">
                        <strong style="color:#ef4444;"><?php echo number_format_i18n( $gs['purgeable'] ); ?></strong>
                        <span style="color:#6b7280;"> guest<?php echo $gs['purgeable'] !== 1 ? 's' : ''; ?> ready to purge</span>
                        <span style="color:#9ca3af;font-size:11px;margin-left:8px;">(<?php echo number_format_i18n( $gs['merged'] ); ?> merged + <?php echo number_format_i18n( $gs['inactive'] ); ?> inactive)</span>
                    </div>

                    <button type="button" id="sh-purge-guests-btn"
                            class="sh-btn"
                            style="background:#ef4444;color:#fff;border-color:#ef4444;<?php echo $gs['purgeable'] < 1 ? 'opacity:.5;cursor:not-allowed;' : ''; ?>"
                            <?php echo $gs['purgeable'] < 1 ? 'disabled' : ''; ?>
                            data-nonce="<?php echo esc_attr( $nonce ); ?>"
                            data-days="<?php echo esc_attr( $cleanup_days ); ?>">
                        &#128465; Purge Inactive Guests
                    </button>
                </div>

                <div id="sh-purge-result" style="margin-top:12px;display:none;"></div>

                <?php if ( $next_cron ) : ?>
                <p style="margin:16px 0 0;font-size:12px;color:#9ca3af;">
                    &#128337; Next automatic cleanup: <strong><?php echo esc_html( wp_date( 'd M Y H:i', $next_cron ) ); ?></strong>
                </p>
                <?php else : ?>
                <p style="margin:16px 0 0;font-size:12px;color:#f59e0b;">
                    &#9888; Cron not scheduled. Save settings to activate.
                </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Settings -->
        <div class="sh-table-wrap">
            <div style="padding:14px 16px;border-bottom:1px solid #e5e7eb;">
                <h2 style="margin:0;font-size:15px;font-weight:600;">&#9881; Cleanup Settings</h2>
            </div>
            <div style="padding:20px;">
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'sh_save_guest_settings' ); ?>
                    <input type="hidden" name="action" value="sh_save_guest_settings">
                    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                        <label style="font-size:13px;font-weight:500;">Purge inactive guests after</label>
                        <input type="number" name="guest_cleanup_days"
                               value="<?php echo esc_attr( $cleanup_days ); ?>"
                               class="sh-input" min="7" max="3650" style="width:80px;">
                        <span style="font-size:13px;color:#6b7280;">days of inactivity</span>
                        <button type="submit" class="sh-btn sh-btn-primary">Save</button>
                    </div>
                    <p style="margin:8px 0 0;font-size:11px;color:#9ca3af;">
                        Only guests with no lead data (no email) are auto-purged. Guests with email are kept regardless.
                    </p>
                </form>
            </div>
        </div>

        </div><!-- .sh-main -->
        <div class="sh-sidebar"><?php self::renderSidebar(); ?></div>
        </div>

        <script>
        (function(){
            var btn = document.getElementById('sh-purge-guests-btn');
            if (!btn) return;
            btn.addEventListener('click', function() {
                if (!confirm('Purge ' + <?php echo (int)$gs['purgeable']; ?> + ' inactive guest(s)? This cannot be undone.')) return;
                btn.disabled = true;
                btn.textContent = 'Purging...';
                var fd = new FormData();
                fd.append('action', 'sh_purge_guests');
                fd.append('nonce', btn.dataset.nonce);
                fd.append('days', btn.dataset.days);
                fetch('<?php echo esc_js( admin_url("admin-ajax.php") ); ?>', { method: 'POST', body: fd })
                    .then(function(r){ return r.json(); })
                    .then(function(res) {
                        var el = document.getElementById('sh-purge-result');
                        el.style.display = 'block';
                        if (res.success) {
                            var s = res.data.stats;
                            el.innerHTML = '<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;padding:12px 16px;font-size:13px;color:#166534;">'
                                + '&#10003; ' + res.data.message
                                + '<br><span style="font-size:11px;color:#6b7280;margin-top:4px;display:block;">'
                                + 'Remaining: ' + s.total + ' total &nbsp;|&nbsp; '
                                + s.with_email + ' with lead data &nbsp;|&nbsp; '
                                + s.merged + ' merged &nbsp;|&nbsp; '
                                + s.inactive + ' inactive'
                                + '</span></div>';
                            // Purgeable sayısını güncelle
                            btn.disabled = s.purgeable < 1;
                            btn.style.opacity = s.purgeable < 1 ? '.5' : '1';
                            btn.textContent = '\uD83D\uDDD1\uFE0F Purge Inactive Guests';
                        } else {
                            el.innerHTML = '<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:6px;padding:12px 16px;font-size:13px;color:#991b1b;">Error: ' + (res.data || 'Unknown error') + '</div>';
                            btn.disabled = false;
                            btn.textContent = '\uD83D\uDDD1\uFE0F Purge Inactive Guests';
                        }
                    });
            });
        })();
        </script>
        <?php
    }

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
