<?php

namespace SaltHareket\Membership\Concerns;

/**
 * HandlesActivation
 *
 * Email/SMS aktivasyon, hesap onay/red işlemleri.
 * Activation state machine: pending → activated → approved/rejected
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-05-09
 *     - Add: sendActivation() — email/SMS dual flow
 *     - Add: verifyEmailCode() — encrypted link doğrulama
 *     - Add: verifyOtp() / resendOtp() / otpStatus() — SMS OTP
 *     - Add: activateUser() — user_status set + do_action('sh_account_activated')
 *     - Add: approveUser() — do_action('sh_account_approved')
 *     - Add: rejectUser() — do_action('sh_account_rejected')
 *     - Add: changeActivationMethod() — email ↔ SMS switch
 *     - Add: getActivationStatus() — pending/activated/approved/rejected
 *     - Add: verifyUserEmail() — email değişikliği doğrulama
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * $mm = MembershipManager::getInstance();
 *
 * // Aktivasyon gönder:
 * $mm->sendActivation($user_id);
 *
 * // Kullanıcıyı aktive et (email link doğrulandıktan sonra):
 * $mm->activateUser($user_id);
 *
 * // Admin onayı:
 * $mm->approveUser($user_id);
 * $mm->rejectUser($user_id);
 *
 * // Durum sorgula:
 * $status = $mm->getActivationStatus($user_id);
 * // 'pending' | 'activated' | 'approved' | 'rejected'
 *
 * ──────────────────────────────────────────────────────────
 *
 * @example
 *   MembershipManager::getInstance()->activateUser(42);
 *   // → user_status = 'activated'
 *   // → do_action('sh_account_activated', 42)
 *   // → Notifications::fire('account-activated', [...])
 *
 * @example
 *   MembershipManager::getInstance()->approveUser(42);
 *   // → do_action('sh_account_approved', 42)
 *
 * @example
 *   MembershipManager::getInstance()->rejectUser(42, 'Eksik bilgi');
 *   // → do_action('sh_account_rejected', 42)
 *
 * @example
 *   $status = MembershipManager::getInstance()->getActivationStatus(42);
 *   // 'pending' | 'activated' | 'approved' | 'rejected'
 *
 * @example
 *   // Hook dinle:
 *   add_action('sh_account_activated', function($user_id) {
 *       // email gönder, log tut vs.
 *   });
 */
trait HandlesActivation
{
    // ─── Activation Status ───────────────────────────────────────────────────

    /**
     * Kullanıcının aktivasyon durumunu döndür.
     * Geriye dönük uyumluluk: user_status = 1 → 'approved' olarak okunur.
     *
     * @return 'pending'|'activated'|'approved'|'rejected'
     */
    public function getActivationStatus( int $user_id ): string
    {
        $status = get_user_meta( $user_id, 'user_status', true );

        // Eski sistem: 0 = pending, 1 = approved
        if ( $status === '' || $status === false ) return 'pending';
        if ( $status === '1' || $status === 1 )    return 'approved';
        if ( $status === '0' || $status === 0 )    return 'pending';

        // Yeni sistem: string değerler
        $valid = [ 'pending', 'activated', 'approved', 'rejected' ];
        return in_array( $status, $valid, true ) ? $status : 'pending';
    }

    /**
     * Kullanıcı aktif mi? (activated veya approved)
     */
    public function isUserActive( int $user_id ): bool
    {
        // Admin her zaman aktif
        $user = get_userdata( $user_id );
        if ( $user && in_array( 'administrator', (array) $user->roles, true ) ) return true;

        if ( ! self::isActivationRequired() ) return true;

        $status = $this->getActivationStatus( $user_id );
        return in_array( $status, [ 'activated', 'approved' ], true );
    }

    // ─── State Transitions ───────────────────────────────────────────────────

    /**
     * Kullanıcıyı aktive et (email/SMS doğrulandı).
     * → user_status = 'activated'
     * → do_action('sh_account_activated', $user_id)
     */
    public function activateUser( int $user_id ): array
    {
        $response = $this->response();

        update_user_meta( $user_id, 'user_status', 'activated' );
        update_user_meta( $user_id, 'activated_at', time() );

        do_action( 'sh_account_activated', $user_id );

        // Notifications
        if ( defined( 'ENABLE_NOTIFICATIONS' ) && ENABLE_NOTIFICATIONS && class_exists( 'Notifications' ) ) {
            $user = get_userdata( $user_id );
            \Notifications::fire( 'account-activated', [
                'user'      => $user,
                'recipient' => $user_id,
            ] );
        }

        $response['message'] = 'Account activated successfully.';
        $response['refresh'] = true;
        return $response;
    }

    /**
     * Admin kullanıcıyı onayladı.
     * → user_status = 'approved'
     * → do_action('sh_account_approved', $user_id)
     */
    public function approveUser( int $user_id ): array
    {
        $response = $this->response();

        update_user_meta( $user_id, 'user_status', 'approved' );
        update_user_meta( $user_id, 'approved_at', time() );
        update_user_meta( $user_id, 'approved_by', get_current_user_id() );

        do_action( 'sh_account_approved', $user_id );

        if ( defined( 'ENABLE_NOTIFICATIONS' ) && ENABLE_NOTIFICATIONS && class_exists( 'Notifications' ) ) {
            $user = get_userdata( $user_id );
            \Notifications::fire( 'account-approved', [
                'user'      => $user,
                'recipient' => $user_id,
            ] );
        }

        $response['message'] = 'Account approved.';
        return $response;
    }

    /**
     * Admin kullanıcıyı reddetti.
     * → user_status = 'rejected'
     * → do_action('sh_account_rejected', $user_id)
     */
    public function rejectUser( int $user_id, string $reason = '' ): array
    {
        $response = $this->response();

        update_user_meta( $user_id, 'user_status', 'rejected' );
        update_user_meta( $user_id, 'rejected_at', time() );
        update_user_meta( $user_id, 'rejected_by', get_current_user_id() );
        if ( $reason ) {
            update_user_meta( $user_id, 'rejection_reason', sanitize_textarea_field( $reason ) );
        }

        do_action( 'sh_account_rejected', $user_id );

        if ( defined( 'ENABLE_NOTIFICATIONS' ) && ENABLE_NOTIFICATIONS && class_exists( 'Notifications' ) ) {
            $user = get_userdata( $user_id );
            \Notifications::fire( 'account-rejected', [
                'user'      => $user,
                'recipient' => $user_id,
                'reason'    => $reason,
            ] );
        }

        $response['message'] = 'Account rejected.';
        return $response;
    }

    // ─── Send Activation ─────────────────────────────────────────────────────

    /**
     * Aktivasyon gönder — email veya SMS, config'e göre otomatik seçer.
     */
    public function sendActivation( int $user_id ): array
    {
        $response = $this->response();

        if ( ! self::isActivationRequired() ) return $response;

        $user            = get_userdata( $user_id );
        $activation_type = defined( 'MEMBERSHIP_ACTIVATION_TYPE' ) ? MEMBERSHIP_ACTIVATION_TYPE : 'email';

        // Kullanıcıya özel tip varsa onu kullan
        $user_type = get_user_meta( $user_id, 'activation_type', true );
        if ( ! empty( $user_type ) ) {
            $activation_type = $user_type;
        }

        switch ( $activation_type ) {
            case 'sms':
                $user_obj = class_exists( 'User' ) ? new \User( $user_id ) : $user;
                $phone    = method_exists( $user_obj, 'get_phone' ) ? $user_obj->get_phone() : '';

                if ( empty( $phone ) ) {
                    // Telefon yoksa email'e düş
                    update_user_meta( $user_id, 'activation_type', 'email' );
                    return $this->sendActivation( $user_id );
                }

                $sms      = new \Sms( [
                    'user_id'   => $user_id,
                    'recipient' => $phone,
                    'content'   => 'Your activation code is {}',
                ] );
                $response = $sms->generate();

                if ( $response['error'] ) {
                    // SMS başarısız → email'e düş
                    update_user_meta( $user_id, 'activation_type', 'email' );
                    return $this->sendActivation( $user_id );
                }

                update_user_meta( $user_id, 'activation_type', 'sms' );
                return $response;

            case 'email':
            default:
                $link      = $this->getActivationLink( $user_id );
                $site_name = get_bloginfo( 'name' );
                $headers   = [
                    'From: ' . $site_name . ' <' . get_bloginfo( 'admin_email' ) . '>',
                    'Content-Type: text/html; charset=UTF-8',
                ];

                $sent = wp_mail(
                    $user->user_email,
                    $site_name . ' — Account Activation',
                    'Please activate your account: <a href="' . esc_url( $link ) . '">' . esc_url( $link ) . '</a>',
                    $headers
                );

                if ( ! $sent ) {
                    $response['error']   = true;
                    $response['message'] = 'Activation email could not be sent.';
                    return $response;
                }

                update_user_meta( $user_id, 'activation_type', 'email' );
                $response['refresh'] = true;
                return $response;
        }
    }

    /**
     * Şifreli aktivasyon linki oluştur.
     */
    public function getActivationLink( int $user_id ): string
    {
        global $wpdb;

        $key  = md5( time() . $user_id );
        $data = [ 'type' => 'activation', 'id' => $user_id, 'code' => $key ];

        $encrypt = new \Encrypt();
        $code    = $encrypt->encrypt( $data );

        $wpdb->update(
            $wpdb->users,
            [ 'user_activation_key' => $key ],
            [ 'ID' => $user_id ]
        );

        return add_query_arg(
            [ 'activation-code' => $code ],
            \Data::get( 'base_urls.profile' )
        );
    }

    /**
     * Email aktivasyon kodunu doğrula (URL'den gelen ?activation-code=xxx).
     * Eski membership-functions.php'deki inline JS kaldırıldı — clean PHP response.
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public function verifyEmailCode( string $code, int $user_id = 0 ): array
    {
        global $wpdb;

        $decrypt = new \Encrypt();
        $data    = $decrypt->decrypt( $code );

        if ( ! $data ) {
            return [ 'success' => false, 'message' => 'Invalid activation code.' ];
        }

        $target_user_id = (int) ( $data['id'] ?? 0 );

        // Kullanıcı ID kontrolü
        if ( $user_id > 0 && $target_user_id !== $user_id ) {
            return [ 'success' => false, 'message' => 'Activation code does not match your account.' ];
        }

        // Zaten aktif mi?
        if ( $this->isUserActive( $target_user_id ) ) {
            return [ 'success' => true, 'message' => 'Your account is already activated.' ];
        }

        // DB'deki key ile karşılaştır
        $stored_key = $wpdb->get_var( $wpdb->prepare(
            "SELECT user_activation_key FROM {$wpdb->users} WHERE ID = %d",
            $target_user_id
        ) );

        if ( $stored_key !== $data['code'] ) {
            return [ 'success' => false, 'message' => 'Activation code is invalid or expired.' ];
        }

        // Aktive et
        $this->activateUser( $target_user_id );

        return [ 'success' => true, 'message' => 'Your account has been activated!' ];
    }

    // ─── SMS OTP ─────────────────────────────────────────────────────────────

    /**
     * OTP kodunu doğrula.
     */
    public function verifyOtp( array $vars = [] ): array
    {
        if ( ! $this->user ) return array_merge( $this->response(), [ 'error' => true, 'message' => 'Not logged in.' ] );

        $sms      = new \Sms( [
            'user_id'  => $this->user->ID,
            'otp_id'   => $vars['otp_id'] ?? '',
            'otp_code' => $vars['otp_code'] ?? '',
        ] );
        $response = $sms->verify();

        if ( isset( $response['data']['status'] ) && $response['data']['status'] === 'APPROVED' ) {
            $this->activateUser( $this->user->ID );
        }

        return $response;
    }

    /**
     * OTP durumunu sorgula.
     */
    public function otpStatus( array $vars = [] ): array
    {
        if ( ! $this->user ) return array_merge( $this->response(), [ 'error' => true ] );

        $sms = new \Sms( [
            'user_id' => $this->user->ID,
            'otp_id'  => $vars['otp_id'] ?? '',
        ] );
        return $sms->otp_status();
    }

    /**
     * OTP'yi yeniden gönder.
     * Süresi dolmuşsa yeni OTP üret.
     */
    public function resendOtp( array $vars = [] ): array
    {
        if ( ! $this->user ) return array_merge( $this->response(), [ 'error' => true ] );

        $sms      = new \Sms( [
            'user_id' => $this->user->ID,
            'otp_id'  => $vars['otp_id'] ?? '',
        ] );
        $response = $sms->resend();

        // Süresi dolmuşsa yeni OTP üret
        if ( isset( $response['data']['status'] ) && $response['data']['status'] === 'EXPIRED' ) {
            $user_obj = class_exists( 'User' ) ? new \User( $this->user->ID ) : $this->user;
            $phone    = method_exists( $user_obj, 'get_phone' ) ? $user_obj->get_phone() : '';

            $sms      = new \Sms( [
                'user_id'   => $this->user->ID,
                'recipient' => $phone,
                'content'   => 'Your activation code is {}',
            ] );
            $response            = $sms->generate();
            $response['refresh'] = true;
        }

        return $response;
    }

    // ─── Activation Method Switch ─────────────────────────────────────────────

    /**
     * Aktivasyon yöntemini değiştir (email ↔ SMS).
     */
    public function changeActivationMethod( array $vars = [] ): array
    {
        $method  = sanitize_text_field( $vars['activation_method'] ?? 'email' );
        $user_id = (int) ( $vars['user_id'] ?? ( $this->user->ID ?? 0 ) );

        update_user_meta( $user_id, 'activation_type', $method );

        $response            = $this->sendActivation( $user_id );
        $response['refresh'] = true;
        return $response;
    }

    // ─── Email Change Verification ───────────────────────────────────────────

    /**
     * Email değişikliği doğrulama linki oluştur.
     */
    public static function getEmailChangeLink( int $user_id ): string
    {
        $email   = get_user_meta( $user_id, '_email_temp', true );
        $data    = [ 'type' => 'email', 'id' => $user_id, 'email' => $email ];
        $encrypt = new \Encrypt();
        $code    = $encrypt->encrypt( $data );

        return add_query_arg(
            [ 'activation-email' => $code ],
            get_account_endpoint_url( 'profile' )
        );
    }

    /**
     * Email değişikliği doğrulama linkini gönder.
     */
    public static function sendEmailChangeLink( int $user_id ): bool
    {
        $email     = get_user_meta( $user_id, '_email_temp', true );
        $link      = self::getEmailChangeLink( $user_id );
        $site_name = get_bloginfo( 'name' );
        $headers   = [
            'From: ' . $site_name . ' <' . get_bloginfo( 'admin_email' ) . '>',
            'Content-Type: text/html; charset=UTF-8',
        ];

        return wp_mail(
            $email,
            $site_name . ' — Email Verification',
            'Please verify your new email: <a href="' . esc_url( $link ) . '">' . esc_url( $link ) . '</a>',
            $headers
        );
    }

    /**
     * Email değişikliği doğrulama kodunu işle.
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public function verifyEmailChange( string $code, int $current_user_id ): array
    {
        $decrypt = new \Encrypt();
        $data    = $decrypt->decrypt( $code );

        if ( ! $data ) {
            return [ 'success' => false, 'message' => 'Invalid verification code.' ];
        }

        $user_id = (int) ( $data['id'] ?? 0 );

        if ( $user_id !== $current_user_id ) {
            return [ 'success' => false, 'message' => 'Verification code does not match your account.' ];
        }

        $user       = get_userdata( $user_id );
        $email_temp = get_user_meta( $user_id, '_email_temp', true );

        if ( empty( $email_temp ) || $user->user_email === $email_temp ) {
            delete_user_meta( $user_id, '_email_temp' );
            return [ 'success' => true, 'message' => 'Email is already verified.' ];
        }

        if ( $data['email'] !== $email_temp ) {
            return [ 'success' => false, 'message' => 'Verification code is invalid or expired.' ];
        }

        // Email güncelle
        wp_update_user( [ 'ID' => $user_id, 'user_email' => $email_temp ] );
        update_user_meta( $user_id, 'user_email', $email_temp );
        update_user_meta( $user_id, 'billing_email', $email_temp );
        delete_user_meta( $user_id, '_email_temp' );

        do_action( 'sh_email_changed', $user_id, $email_temp );

        return [ 'success' => true, 'message' => 'Your new email <b>' . esc_html( $email_temp ) . '</b> has been verified!' ];
    }

    /**
     * Email değişikliği aktivasyonunu sıfırla.
     */
    public static function resetEmailChange( int $user_id ): array
    {
        delete_user_meta( $user_id, '_email_temp' );
        $email = get_userdata( $user_id )->user_email ?? '';
        return [
            'message' => 'Email change cancelled. Your current email is ' . esc_html( $email ) . '.',
            'refresh' => true,
        ];
    }
}
