<?php

namespace SaltHareket\Membership\Concerns;

use SaltHareket\Membership\MembershipSettings;

/**
 * HandlesPasswordless
 *
 * Şifresiz giriş — kayıtlı telefon numarası + SMS OTP.
 *
 * Akış:
 *   1. Kullanıcı telefon numarasını girer → requestPasswordlessOTP()
 *   2. Numara DB'de kayıtlı bir kullanıcıya aitse OTP SMS gönderilir
 *   3. Kullanıcı kodu girer → verifyPasswordlessOTP()
 *   4. Doğrulanırsa giriş tamamlanır
 *
 * Güvenlik:
 *   - Numara kayıtlı değilse hata mesajı AYNI — kullanıcı enumeration engeli
 *   - Rate limit: 5 deneme / 15dk (IP bazlı)
 *
 * @version 1.0.0
 *
 * @changelog
 *   1.0.0 - 2026-09-04
 *     - Add: Initial implementation
 */
trait HandlesPasswordless
{
    /**
     * Şifresiz giriş için OTP gönder.
     *
     * @param array $vars ['phone', 'redirect_url']
     */
    public function requestPasswordlessOTP( array $vars = [] ): array
    {
        $response = $this->response();

        if ( ! MembershipSettings::getSetting( 'enable_passwordless', false ) ) {
            $response['error']   = true;
            $response['message'] = 'Passwordless login is not enabled.';
            return $response;
        }

        if ( ! ( defined( 'ENABLE_SMS' ) && ENABLE_SMS ) ) {
            $response['error']   = true;
            $response['message'] = 'SMS service is not available.';
            return $response;
        }

        $phone      = sanitize_text_field( $vars['phone']      ?? '' );
        $phone_code = sanitize_text_field( $vars['phone_code'] ?? '' );

        if ( empty( $phone ) ) {
            $response['error']   = true;
            $response['message'] = 'Phone number is required.';
            return $response;
        }

        // Rate limit (IP bazlı)
        $rate_key = 'sh_pwless_' . md5( ( $_SERVER['REMOTE_ADDR'] ?? '' ) . $phone );
        if ( (int) get_transient( $rate_key ) >= 5 ) {
            $response['error']   = true;
            $response['message'] = 'Too many attempts. Please try again later.';
            return $response;
        }
        set_transient( $rate_key, ( (int) get_transient( $rate_key ) ) + 1, 15 * MINUTE_IN_SECONDS );

        // Telefona göre kullanıcı bul — phone_code ayrı gönderiliyor
        $user_id = $this->findUserByPhone( $phone, $phone_code );

        // DEBUG — geliştirme ortamında kullanıcı bulunamıyorsa logla
        if ( defined('ENABLE_DEVELOPMENT') && ENABLE_DEVELOPMENT && ! $user_id ) {
            $clean_debug = preg_replace( '/\D/', '', $phone );
            global $wpdb;
            $all_phones = $wpdb->get_results(
                "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'billing_phone' LIMIT 10",
                ARRAY_A
            );
            error_log( '[Passwordless] Phone not found. Searched: ' . $clean_debug . ' | DB billing_phone records: ' . json_encode( $all_phones ) );
        }

        // Güvenlik: numara kayıtlı olsa da olmasa da aynı mesaj
        if ( ! $user_id ) {
            // Gerçek hayatta "bulunamadı" bile söylemeyiz — ama geliştirme kolaylığı için yorum:
            // $response['error'] = true; $response['message'] = 'No account found.';
            $response['message'] = 'If this number is registered, you will receive an OTP shortly.';
            return $response;
        }

        // Tam telefon numarası: ülke kodu + numara
        $full_phone = ! empty( $phone_code )
            ? preg_replace( '/\s+/', '', $phone_code ) . preg_replace( '/^0+/', '', preg_replace( '/\D/', '', $phone ) )
            : $phone;

        $sms_response = \SaltHareket\Sms\SmsManager::driver()->generateOtp(
            $full_phone,
            $user_id,
            'Your login code is {}'
        );

        if ( $sms_response['error'] ?? true ) {
            $response['error']   = true;
            $response['message'] = 'Could not send OTP. Please try again.';
            return $response;
        }

        $token      = $this->setPending2FA( $user_id );
        $otp_id     = $sms_response['data']['otp_id'] ?? ( $sms_response['otp_id'] ?? '' );
        $otp_expiry = (int) ( \SaltHareket\Sms\SmsSettings::get()['otp_expiry'] ?? 300 );
        $masked     = substr( $phone, 0, 2 ) . str_repeat( '*', max( 0, strlen( $phone ) - 4 ) ) . substr( $phone, -2 );

        // Sadece data içinde dönüyor — root level'da duplicate yok
        $response['message'] = 'If this number is registered, you will receive an OTP shortly.';
        $response['data']    = [
            'pending_token' => $token,
            'otp_id'        => $otp_id,
            'otp_expiry'    => $otp_expiry,
            'phone'         => $masked,
        ];

        return $response;
    }

    /**
     * Şifresiz giriş OTP'sini doğrula + giriş tamamla.
     *
     * @param array $vars ['pending_token', 'otp_id', 'otp_code', 'remember', 'redirect_url']
     */
    public function verifyPasswordlessOTP( array $vars = [] ): array
    {
        $response = $this->response();

        if ( ! MembershipSettings::getSetting( 'enable_passwordless', false ) ) {
            $response['error']   = true;
            $response['message'] = 'Passwordless login is not enabled.';
            return $response;
        }

        error_log(print_r($vars, true));

        $token   = sanitize_text_field( $vars['pending_token'] ?? '' );
        $user_id = $this->getPending2FAUserId( $token );

        // DEBUG
        if ( defined('ENABLE_DEVELOPMENT') && ENABLE_DEVELOPMENT ) {
            $key  = 'sh_2fa_pending_' . $token;
            $data = get_transient( $key );
            error_log( '[PwlessVerify] token=' . $token . ' | transient_key=' . $key . ' | transient_data=' . json_encode( $data ) );
        }

        if ( ! $user_id ) {
            $response['error']   = true;
            $response['message'] = 'Session expired. Please request a new code.';
            return $response;
        }

        $otp_response = \SaltHareket\Sms\SmsManager::driver()->verifyOtp(
            $vars['otp_id']   ?? '',
            $vars['otp_code'] ?? ''
        );

        if ( $otp_response['error'] ?? true ) {
            $response['error']   = true;
            $response['message'] = $otp_response['message'] ?? 'Invalid or expired code.';
            return $response;
        }

        $this->clearPending2FA( $token );
        return $this->completLogin( $user_id, $vars );
    }

    /**
     * Telefon numarasına göre kullanıcı bul.
     * Standart key: billing_phone (WC uyumlu, profil sayfasıyla sync)
     *
     * @return int|null  user_id veya null
     */
    /**
     * Telefon numarasına göre kullanıcı bul.
     * billing_phone_code (ülke kodu) + billing_phone (numara) ayrı kayıtlı.
     * Form: phone_code=+90, phone=5353584348
     * DB:   billing_phone_code=+90, billing_phone=5353584348
     *
     * @return int|null  user_id veya null
     */
    private function findUserByPhone( string $phone, string $phone_code = '' ): ?int
    {
        // Sadece rakam — her ikisini de normalize et
        $clean_phone = preg_replace( '/\D/', '', $phone );
        if ( empty( $clean_phone ) ) return null;

        global $wpdb;

        // phone_code varsa: billing_phone_code + billing_phone ile eşleştir
        if ( $phone_code !== '' ) {
            $clean_code = preg_replace( '/\D/', '', $phone_code );

            $user_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT p.user_id FROM {$wpdb->usermeta} p
                 JOIN {$wpdb->usermeta} c ON p.user_id = c.user_id
                 WHERE p.meta_key = 'billing_phone'
                   AND REGEXP_REPLACE(p.meta_value, '[^0-9]', '') = %s
                   AND c.meta_key = 'billing_phone_code'
                   AND REGEXP_REPLACE(c.meta_value, '[^0-9]', '') = %s
                 LIMIT 1",
                $clean_phone,
                $clean_code
            ) );

            if ( $user_id ) return (int) $user_id;
        }

        // Fallback: sadece billing_phone ile eşleştir (phone_code bilinmiyorsa)
        $user_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta}
             WHERE meta_key = 'billing_phone'
             AND REGEXP_REPLACE(meta_value, '[^0-9]', '') = %s
             LIMIT 1",
            $clean_phone
        ) );

        return $user_id ? (int) $user_id : null;
    }
}
