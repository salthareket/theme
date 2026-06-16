<?php

/**
 * Membership App Bootstrap
 *
 * @version 1.0.0
 */

namespace SaltHareket\Membership;

// ─── AUTOLOAD ────────────────────────────────────────────────────────────────

$membership_base = __DIR__ . '/';

require_once $membership_base . 'GuestIdentity.php';
require_once $membership_base . 'Concerns/HandlesAuth.php';
require_once $membership_base . 'Concerns/HandlesRegistration.php';
require_once $membership_base . 'Concerns/HandlesActivation.php';
require_once $membership_base . 'Concerns/HandlesProfile.php';
require_once $membership_base . 'Concerns/HandlesPassword.php';
require_once $membership_base . 'Concerns/HandlesSocialLogin.php';
require_once $membership_base . 'Concerns/HandlesMyAccount.php';
require_once $membership_base . 'MembershipManager.php';
require_once $membership_base . 'Hooks/MembershipHooks.php';
require_once $membership_base . 'Admin/MembershipAdmin.php';

// ─── INIT ────────────────────────────────────────────────────────────────────

Admin\MembershipAdmin::register();
Hooks\MembershipHooks::register();
Hooks\MembershipHooks::registerPageManagement();

// ─── GLOBAL HELPERS ──────────────────────────────────────────────────────────
// helpers.php helpers/index.php'den erken yükleniyor — burada tekrar yükleme yok.
// require_once $membership_base . 'helpers.php'; // helpers/index.php'de yükleniyor

// ─── GUEST IDENTITY — DB INSTALL ─────────────────────────────────────────────

add_action( 'admin_init', function () {
    \SaltHareket\Membership\GuestIdentity::createTable();
}, 5 );

// ─── GUEST → USER MERGE ──────────────────────────────────────────────────────
// Login/signup olunca GuestIdentity::mergeToUser() çağrılır.
// wp_guests.merged_to = user_id set edilir.
// wp_download_log / wp_reactions → user_id güncellenir.
// Cookie silinir.

add_action( 'wp_login', function ( string $user_login, \WP_User $user ) {
    \SaltHareket\Membership\GuestIdentity::mergeToUser( $user->ID );
}, 10, 2 );

add_action( 'user_register', function ( int $user_id ) {
    \SaltHareket\Membership\GuestIdentity::mergeToUser( $user_id );
} );

// ─── GUEST CLEANUP CRON ──────────────────────────────────────────────────────
// Günlük çalışır — inactive + merged guest'leri temizler.
// Kaç gün sonra temizleneceği: sh_guest_cleanup_days option (default: 90)

add_action( 'sh_guest_cleanup_cron', function () {
    \SaltHareket\Membership\GuestIdentity::cleanup();
} );

add_action( 'admin_init', function () {
    if ( ! wp_next_scheduled( 'sh_guest_cleanup_cron' ) ) {
        wp_schedule_event( strtotime( 'tomorrow 03:00' ), 'daily', 'sh_guest_cleanup_cron' );
    }
}, 10 );

// Manuel temizleme AJAX
add_action( 'wp_ajax_sh_purge_guests', function () {
    check_ajax_referer( 'sh_membership_admin', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $days  = (int) ( $_POST['days'] ?? 0 );
    $count = \SaltHareket\Membership\GuestIdentity::cleanup( $days );
    $stats = \SaltHareket\Membership\GuestIdentity::getStats();

    wp_send_json_success( [
        'deleted' => $count,
        'stats'   => $stats,
        'message' => $count > 0
            ? "{$count} inactive guest(s) deleted."
            : 'No guests to purge.',
    ] );
} );

// Guest cleanup days ayarını kaydet
add_action( 'admin_post_sh_save_guest_settings', function () {
    check_admin_referer( 'sh_save_guest_settings' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

    $days = max( 1, (int) ( $_POST['guest_cleanup_days'] ?? 90 ) );
    update_option( 'sh_guest_cleanup_days', $days );

    wp_redirect( add_query_arg( [ 'page' => 'sh-membership', 'tab' => 'guests', 'saved' => '1' ], admin_url( 'admin.php' ) ) );
    exit;
} );
