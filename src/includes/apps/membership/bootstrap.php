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
require_once $membership_base . 'Concerns/Handles2FA.php';
require_once $membership_base . 'Concerns/HandlesPasswordless.php';
require_once $membership_base . 'Concerns/HandlesDevice.php';
require_once $membership_base . 'Concerns/HandlesPhone.php';
require_once $membership_base . 'MembershipManager.php';
require_once $membership_base . 'Hooks/MembershipHooks.php';
require_once $membership_base . 'Admin/MembershipAdmin.php';

// ─── REST API ────────────────────────────────────────────────────────────────
require_once $membership_base . 'rest.php';

// ─── INIT ────────────────────────────────────────────────────────────────────

Admin\MembershipAdmin::register();
Hooks\MembershipHooks::register();
Hooks\MembershipHooks::registerPageManagement();

// ─── WP default_role ↔ MembershipSettings::default_role SYNC ────────────────
// WP Settings → General'dan değiştirilirse bizim ayar da güncellenir.
add_action( 'update_option_default_role', function ( $old, $new ) {
    $s = \SaltHareket\Membership\MembershipSettings::get();
    if ( $s['default_role'] !== $new ) {
        \SaltHareket\Membership\MembershipSettings::save( [ 'default_role' => $new ] );
    }
}, 10, 2 );

// ─── CUSTOM ROLE — Admin Panel Erişim Engeli ─────────────────────────────────
// sh_managed_roles listesindeki roller wp-admin'e girince ana siteye yönlendirilir.
add_action( 'admin_init', function () {
    if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) return;
    if ( ! is_user_logged_in() ) return;

    $user         = wp_get_current_user();
    $managed      = get_option( 'sh_managed_roles', [] );
    $user_roles   = (array) $user->roles;
    $has_managed  = ! empty( array_intersect( $user_roles, $managed ) );
    $has_admin    = user_can( $user, 'manage_options' );

    if ( $has_managed && ! $has_admin ) {
        wp_redirect( home_url( '/' ) );
        exit();
    }
} );

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

// ─── TURBO API HANDLER ───────────────────────────────────────────────────────
// Tüm membership TurboAPI metodları burada handle edilir.
// methods/activation/ ve methods/user/ klasörlerindeki membership dosyaları deprecated.
// Yeni metodlar sadece buraya eklenir — compiled index.php'ye dokunmak gerekmez.
//
// Kapsanan metodlar:
//   Activation: send_activation, resend_otp, verify_otp, change_activation_method,
//               send_email_activation, reset_email_activation
//   Auth:       login, register, password_recover
//   2FA:        verify_2fa_sms, verify_2fa_totp, generate_totp_secret, activate_totp,
//               disable_totp, passwordless_request
//   Device:     revoke_trusted_device, revoke_all_trusted_devices
//   Profile:    update_profile (Salt::get_instance() - eski path korunuyor)

add_filter( 'turbo_api_handle', function ( $handled, string $method, array $vars ) {

    if ( $handled !== null ) return $handled; // başka app handle ettiyse geç

    $mm = \SaltHareket\Membership\MembershipManager::getInstance();

    switch ( $method ) {

        case 'update_profile':
            if ( ! defined('ENABLE_MEMBERSHIP') || ! ENABLE_MEMBERSHIP ) return null;
            $current_user_id = get_current_user_id();
            if ( ! $current_user_id ) {
                return [ 'error' => true, 'message' => 'Not logged in.' ];
            }
            if ( ! $mm->user ) {
                $mm->user = class_exists( 'User' )
                    ? new \User( $current_user_id )
                    : \Timber\Timber::get_user( $current_user_id );
            }
            return $mm->updateProfile( $vars );

        case 'update_profile_photo':
            // update_profile_photo compiled methods/user/update_profile_photo/index.php'de handle ediliyor
            return null;

        // ── Auth ─────────────────────────────────────────────────────────────

        case 'login':
            if ( ! defined('ENABLE_MEMBERSHIP') || ! ENABLE_MEMBERSHIP ) return null;
            return $mm->login( $vars );

        case 'logout':
            if ( ! defined('ENABLE_MEMBERSHIP') || ! ENABLE_MEMBERSHIP ) return null;
            $mm->logout();
            return [ 'error' => false, 'message' => 'Logged out.', 'redirect' => home_url('/') ];

        case 'register':
            if ( ! defined('ENABLE_MEMBERSHIP') || ! ENABLE_MEMBERSHIP ) return null;
            if ( ! ( defined('ENABLE_REGISTRATION') && ENABLE_REGISTRATION ) ) {
                return [ 'error' => true, 'message' => 'Registration is disabled.' ];
            }
            return $mm->register( $vars );

        case 'password_recover':
            if ( ! defined('ENABLE_MEMBERSHIP') || ! ENABLE_MEMBERSHIP ) return null;
            return $mm->passwordRecover( $vars );

        case 'password_renew':
            if ( ! defined('ENABLE_MEMBERSHIP') || ! ENABLE_MEMBERSHIP ) return null;
            return $mm->passwordRenew( $vars );

        case 'password_reset':
            if ( ! defined('ENABLE_MEMBERSHIP') || ! ENABLE_MEMBERSHIP ) return null;
            return $mm->passwordReset( $vars );

        case 'verify_forgot_otp':
            if ( ! defined('ENABLE_MEMBERSHIP') || ! ENABLE_MEMBERSHIP ) return null;
            return $mm->verifyForgotOtp( $vars );

        case 'reset_password_with_token':
            if ( ! defined('ENABLE_MEMBERSHIP') || ! ENABLE_MEMBERSHIP ) return null;
            return $mm->resetPasswordWithToken( $vars );

        // ── Activation ───────────────────────────────────────────────────────

        case 'change_activation_method':
            if ( ! defined('ENABLE_MEMBERSHIP') || ! ENABLE_MEMBERSHIP ) return null;
            return $mm->changeActivationMethod( $vars );

        case 'send_activation':
            if ( ! defined('ENABLE_MEMBERSHIP') || ! ENABLE_MEMBERSHIP ) return null;
            $user_id = (int) ( $vars['id'] ?? get_current_user_id() );
            return $mm->sendActivation( $user_id );

        case 'resend_otp':
            if ( ! defined('ENABLE_MEMBERSHIP') || ! ENABLE_MEMBERSHIP ) return null;
            return $mm->resendOtp( $vars );

        case 'verify_otp':
            if ( ! defined('ENABLE_MEMBERSHIP') || ! ENABLE_MEMBERSHIP ) return null;
            $result = $mm->verifyOtp( $vars );
            // Başarıyla aktive edildiyse sayfayı yenile
            if ( ! ( $result['error'] ?? true ) ) {
                $result['refresh'] = true;
            }
            return $result;

        case 'send_email_activation':
            if ( ! defined('ENABLE_MEMBERSHIP') || ! ENABLE_MEMBERSHIP ) return null;
            $user_id = (int) ( $vars['user_id'] ?? get_current_user_id() );
            update_user_meta( $user_id, 'activation_type', 'email' );
            return $mm->sendActivation( $user_id );

        case 'reset_email_activation':
            if ( ! defined('ENABLE_MEMBERSHIP') || ! ENABLE_MEMBERSHIP ) return null;
            $user_id = get_current_user_id();
            delete_user_meta( $user_id, 'email_activation_pending' );
            return [ 'error' => false, 'message' => 'Email activation reset.' ];

        // ── 2FA ──────────────────────────────────────────────────────────────

        case 'verify_2fa_sms':
            if ( ! defined('ENABLE_MEMBERSHIP') || ! ENABLE_MEMBERSHIP ) return null;
            return $mm->verify2FASMS( $vars );

        case 'verify_2fa_totp':
            if ( ! defined('ENABLE_MEMBERSHIP') || ! ENABLE_MEMBERSHIP ) return null;
            return $mm->verify2FATOTP( $vars );

        case 'generate_totp_secret':
            if ( ! defined('ENABLE_MEMBERSHIP') || ! ENABLE_MEMBERSHIP ) return null;
            if ( ! is_user_logged_in() ) {
                return [ 'error' => true, 'message' => 'Not logged in.' ];
            }
            return $mm->generateTOTPSecret( get_current_user_id() );

        case 'activate_totp':
            if ( ! defined('ENABLE_MEMBERSHIP') || ! ENABLE_MEMBERSHIP ) return null;
            if ( ! is_user_logged_in() ) {
                return [ 'error' => true, 'message' => 'Not logged in.' ];
            }
            $code   = sanitize_text_field( $vars['totp_code'] ?? '' );
            $result = $mm->activateTOTP( get_current_user_id(), $code );
            // Başarıysa sayfayı yenile — data-ajax-method sistemi refresh:true ile reload eder
            if ( ! ( $result['error'] ?? true ) ) {
                $result['refresh'] = true;
            }
            return $result;

        case 'disable_totp':
            if ( ! defined('ENABLE_MEMBERSHIP') || ! ENABLE_MEMBERSHIP ) return null;
            if ( ! is_user_logged_in() ) {
                return [ 'error' => true, 'message' => 'Not logged in.' ];
            }
            $mm->disableTOTP( get_current_user_id() );
            return [ 'error' => false, 'message' => 'Two-factor authentication disabled.', 'refresh' => true ];

        // ── Passwordless ─────────────────────────────────────────────────────

        case 'passwordless_request':
            if ( ! defined('ENABLE_PASSWORDLESS') || ! ENABLE_PASSWORDLESS ) return null;
            return $mm->requestPasswordlessOTP( $vars );

        case 'passwordless_verify':
            if ( ! defined('ENABLE_PASSWORDLESS') || ! ENABLE_PASSWORDLESS ) return null;
            return $mm->verifyPasswordlessOTP( $vars );

        // ── Trusted Devices ──────────────────────────────────────────────────

        case 'revoke_trusted_device':
            if ( ! is_user_logged_in() ) {
                return [ 'error' => true, 'message' => 'Not logged in.' ];
            }
            $token = sanitize_text_field( $vars['token'] ?? '' );
            if ( ! $token ) return [ 'error' => true, 'message' => 'Token required.' ];
            $mm->revokeTrustedDevice( get_current_user_id(), $token );
            return [ 'error' => false, 'message' => 'Device removed.', 'refresh' => true ];

        case 'revoke_all_trusted_devices':
            if ( ! is_user_logged_in() ) {
                return [ 'error' => true, 'message' => 'Not logged in.' ];
            }
            $mm->revokeAllTrustedDevices( get_current_user_id() );
            return [ 'error' => false, 'message' => 'All trusted devices removed.', 'refresh' => true ];

        // ── Localization ─────────────────────────────────────────────────────

        case 'get_states':
            $country_code = sanitize_text_field( $vars['country_code'] ?? '' );
            if ( empty( $country_code ) ) {
                return [ 'error' => true, 'message' => 'country_code required.' ];
            }
            global $wpdb;
            $table = $wpdb->prefix . 'states';
            $exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );
            if ( ! $exists ) {
                return [ 'error' => true, 'message' => 'States table not found.' ];
            }
            $states = $wpdb->get_results( $wpdb->prepare(
                "SELECT id, name FROM {$table} WHERE country_code = %s ORDER BY name ASC",
                strtoupper( $country_code )
            ), ARRAY_A );
            return [ 'error' => false, 'data' => $states ?: [] ];

        case 'validate_phone':
            if ( ! defined('ENABLE_MEMBERSHIP') || ! ENABLE_MEMBERSHIP ) return null;
            return $mm->validatePhoneNumber( $vars );

    }

    return null; // bu app handle etmedi

}, 10, 3 );

// ─── JS: Login requires_2fa trigger ─────────────────────────────────────────
// TurboAPI login yanıtı requires_2fa:true içeriyorsa frontend'e event gönder.
// ajax_hooks['login'].after veya site_config.after callback'inde tetiklenir.
// NOT: Bu filter frontend JS üzerinden çalışmaz; methods/index.js'deki
// ajax_hooks['login'] after callback'ine eklenmesi gerekir.
// Bkz: form-login-native.twig → $(document).on('sh:login:requires_2fa', ...)
