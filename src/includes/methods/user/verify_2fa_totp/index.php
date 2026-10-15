<?php
/**
 * verify_2fa_totp — TOTP kodunu doğrula, login'i tamamla.
 *
 * vars:
 *   pending_token — setPending2FA()'dan gelen token
 *   totp_code     — authenticator app'den gelen 6 haneli kod
 *   remember      — (opsiyonel) bool
 *   redirect_url  — (opsiyonel) string
 */
$required_setting = ENABLE_MEMBERSHIP;

if ( ! $required_setting ) {
    $response['error']   = true;
    $response['message'] = 'Membership is not enabled.';
    echo json_encode( $response );
    wp_die();
}

$mm = \SaltHareket\Membership\MembershipManager::getInstance();
echo json_encode( $mm->verify2FATOTP( $vars ) );
wp_die();
