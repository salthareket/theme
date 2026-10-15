<?php

namespace SaltHareket\Membership\Concerns;

/**
 * HandlesPassword
 *
 * Şifre kurtarma, sıfırlama ve güvenlik işlemleri.
 * MembershipManager tarafından trait olarak kullanılır.
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-05-09
 *     - Add: passwordRecover() — config'e göre renew/reset dispatch
 *     - Add: passwordRenew() — email ile reset linki gönder (clean return, echo yok)
 *     - Add: passwordReset() — yeni şifre üret ve gönder
 *     - Add: getPasswordResetLink() / sendPasswordResetLink() — encrypted link
 *     - Add: processPasswordReset() — link'ten gelen kodu işle
 *     - Add: changePassword() — mevcut şifre doğrulayarak değiştir
 *     - Add: invalidateAllSessions() — şifre değişince tüm oturumları sonlandır
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * $mm = MembershipManager::getInstance();
 *
 * // Şifre sıfırlama isteği:
 * $result = $mm->passwordRecover(['user_login' => 'a@b.com']);
 *
 * // Şifre değiştir (profil sayfasından):
 * $result = $mm->changePassword(['current' => 'old', 'new' => 'new123', 'confirm' => 'new123']);
 *
 * ──────────────────────────────────────────────────────────
 *
 * @example
 *   $result = MembershipManager::getInstance()->passwordRecover(['user_login' => 'a@b.com']);
 *   if (!$result['error']) echo 'Check your email.';
 *
 * @example
 *   // Şifre değişince tüm oturumları sonlandır:
 *   MembershipManager::getInstance()->invalidateAllSessions($user_id);
 *
 * @example
 *   // Reset link'i işle (?activation-password=xxx):
 *   $result = MembershipManager::getInstance()->processPasswordReset($code);
 *
 * @example
 *   add_action('sh_password_changed', function($user_id) {
 *       // log tut, bildirim gönder
 *   });
 *
 * @example
 *   add_action('sh_password_reset_requested', function($user_id, $email) {
 *       // log tut
 *   }, 10, 2);
 */
trait HandlesPassword
{
    /**
     * Şifre kurtarma — config'e göre renew, reset veya forgot_otp dispatch.
     *
     * PASSWORD_RECOVER_TYPE:
     *   'renew'     → link email gönder (varsayılan)
     *   'reset'     → yeni şifre üret + email gönder
     *   'forgot_otp'→ OTP email gönder → inline step akışı
     */
    public function passwordRecover( array $vars = [] ): array
    {
        $type = defined( 'PASSWORD_RECOVER_TYPE' ) ? PASSWORD_RECOVER_TYPE : 'renew';

        if ( $type === 'forgot_otp' ) {
            return $this->sendForgotOtp( $vars );
        }

        return $type === 'reset'
            ? $this->passwordReset( $vars )
            : $this->passwordRenew( $vars );
    }

    /**
     * Şifre sıfırlama linki gönder (renew modu).
     * Eski kod direkt echo yapıyordu — artık clean return.
     */
    public function passwordRenew( array $vars = [] ): array
    {
        $response   = $this->response();
        $user_login = sanitize_text_field( $vars['user_login'] ?? '' );

        if ( empty( $user_login ) ) {
            $response['error']   = true;
            $response['message'] = 'Please enter your email address.';
            return $response;
        }

        if ( ! is_email( $user_login ) ) {
            $response['error']   = true;
            $response['message'] = 'Please enter a valid email address.';
            return $response;
        }

        if ( ! email_exists( $user_login ) ) {
            $response['error']   = true;
            $response['message'] = 'No account found with that email address.';
            return $response;
        }

        $user = get_user_by( 'email', $user_login );
        do_action( 'sh_password_reset_requested', $user->ID, $user_login );

        $sent = $this->sendPasswordResetLink( $user_login );

        if ( ! $sent ) {
            $response['error']   = true;
            $response['message'] = 'Could not send the password reset email. Please try again.';
            return $response;
        }

        $response['message'] = 'Check your email for the password reset link.';
        return $response;
    }

    /**
     * Yeni şifre üret ve gönder (reset modu).
     */
    public function passwordReset( array $vars = [] ): array
    {
        $response   = $this->response();
        $user_login = sanitize_text_field( $vars['user_login'] ?? '' );

        if ( empty( $user_login ) ) {
            $response['error']   = true;
            $response['message'] = 'Please enter your email address or username.';
            return $response;
        }

        $get_by = null;
        if ( is_email( $user_login ) ) {
            if ( email_exists( $user_login ) ) {
                $get_by = 'email';
            } else {
                $response['error']   = true;
                $response['message'] = 'No account found with that email address.';
                return $response;
            }
        } elseif ( validate_username( $user_login ) ) {
            if ( username_exists( $user_login ) ) {
                $get_by = 'login';
            } else {
                $response['error']   = true;
                $response['message'] = 'No account found with that username.';
                return $response;
            }
        } else {
            $response['error']   = true;
            $response['message'] = 'Invalid email address or username.';
            return $response;
        }

        $user         = get_user_by( $get_by, $user_login );
        $new_password = wp_generate_password( 12, true );
        $updated      = wp_update_user( [ 'ID' => $user->ID, 'user_pass' => $new_password ] );

        if ( is_wp_error( $updated ) ) {
            $response['error']   = true;
            $response['message'] = 'Could not update your password. Please try again.';
            return $response;
        }

        $site_name = get_bloginfo( 'name' );
        $headers   = [
            'From: ' . $site_name . ' <' . get_bloginfo( 'admin_email' ) . '>',
            'Content-Type: text/html; charset=UTF-8',
        ];

        $sent = wp_mail(
            $user->user_email,
            $site_name . ' — Your New Password',
            'Your new password is: <strong>' . $new_password . '</strong><br>Please change it after logging in.',
            $headers
        );

        if ( ! $sent ) {
            $response['error']   = true;
            $response['message'] = 'Password updated but could not send the email.';
            return $response;
        }

        $this->invalidateAllSessions( $user->ID );
        do_action( 'sh_password_changed', $user->ID );

        $response['message'] = 'Your new password has been sent to your email.';
        return $response;
    }

    /**
     * Şifreli reset linki oluştur.
     */
    public function getPasswordResetLink( string $email ): string
    {
        $data    = [ 'type' => 'password', 'email' => $email ];
        $encrypt = new \Encrypt();
        $code    = $encrypt->encrypt( $data );

        $base = \Data::get( 'base_urls.account' ) ?: home_url( '/my-account/' );
        return add_query_arg( [ 'activation-password' => $code ], $base );
    }

    /**
     * Reset linkini email ile gönder.
     */
    public function sendPasswordResetLink( string $email ): bool
    {
        $link      = $this->getPasswordResetLink( $email );
        $site_name = get_bloginfo( 'name' );
        $headers   = [
            'From: ' . $site_name . ' <' . get_bloginfo( 'admin_email' ) . '>',
            'Content-Type: text/html; charset=UTF-8',
        ];

        return wp_mail(
            $email,
            $site_name . ' — Password Reset',
            'Click the link to reset your password: <a href="' . esc_url( $link ) . '">' . esc_url( $link ) . '</a>',
            $headers
        );
    }

    /**
     * URL'den gelen reset kodunu işle (?activation-password=xxx).
     *
     * @return array ['success' => bool, 'message' => string, 'email' => string]
     */
    public function processPasswordReset( string $code ): array
    {
        $decrypt = new \Encrypt();
        $data    = $decrypt->decrypt( $code );

        if ( ! $data || empty( $data['email'] ) ) {
            return [ 'success' => false, 'message' => 'Invalid or expired reset link.' ];
        }

        return [ 'success' => true, 'email' => $data['email'], 'message' => '' ];
    }

    /**
     * Mevcut şifreyi doğrulayarak yeni şifre set et (profil sayfasından).
     *
     * @param array $vars ['current', 'new', 'confirm']
     */
    public function changePassword( array $vars = [] ): array
    {
        $response = $this->response();

        if ( ! $this->user ) {
            $response['error']   = true;
            $response['message'] = 'You must be logged in.';
            return $response;
        }

        $current = $vars['current'] ?? '';
        $new     = $vars['new'] ?? '';
        $confirm = $vars['confirm'] ?? '';

        if ( empty( $new ) || strlen( $new ) < 8 ) {
            $response['error']   = true;
            $response['message'] = 'New password must be at least 8 characters.';
            return $response;
        }

        if ( $new !== $confirm ) {
            $response['error']   = true;
            $response['message'] = 'Passwords do not match.';
            return $response;
        }

        // Mevcut şifre doğrulama (password_set = false ise atla — sosyal login)
        $password_set = get_user_meta( $this->user->ID, 'password_set', true );
        if ( $password_set ) {
            $user = get_userdata( $this->user->ID );
            if ( ! wp_check_password( $current, $user->user_pass, $this->user->ID ) ) {
                $response['error']   = true;
                $response['message'] = 'Current password is incorrect.';
                return $response;
            }
        }

        wp_update_user( [ 'ID' => $this->user->ID, 'user_pass' => $new ] );
        update_user_meta( $this->user->ID, 'password_set', true );

        $this->invalidateAllSessions( $this->user->ID );
        do_action( 'sh_password_changed', $this->user->ID );

        $response['message'] = 'Password updated successfully.';
        $response['refresh'] = true;
        return $response;
    }

    /**
     * Forgot password OTP gönder (email tabanlı).
     *
     * password_recover çağrısından sonra OTP modu aktifse bu metod tetiklenir.
     * Akış:
     *   1. Email doğrula + kullanıcı bul
     *   2. 6 haneli OTP üret → email gönder
     *   3. forgot_token (transient) sakla → JS'e döndür
     *
     * @param array $vars ['user_login']
     */
    public function sendForgotOtp( array $vars = [] ): array
    {
        $response   = $this->response();
        $user_login = sanitize_email( $vars['user_login'] ?? '' );

        if ( empty( $user_login ) || ! is_email( $user_login ) ) {
            $response['error']   = true;
            $response['message'] = 'Please enter a valid email address.';
            return $response;
        }

        // Rate limit: IP başına 5 istek / 15 dk
        $rate_key = 'sh_forgot_otp_' . md5( ( $_SERVER['REMOTE_ADDR'] ?? '' ) . $user_login );
        if ( (int) get_transient( $rate_key ) >= 5 ) {
            $response['error']   = true;
            $response['message'] = 'Too many attempts. Please try again later.';
            return $response;
        }
        set_transient( $rate_key, ( (int) get_transient( $rate_key ) ) + 1, 15 * MINUTE_IN_SECONDS );

        // Kullanıcıyı bul — bulunamazsa aynı mesaj (enumeration engeli)
        $user = get_user_by( 'email', $user_login );
        if ( ! $user ) {
            // Güvenlik: gerçek hata vermiyoruz
            $response['message'] = 'If this email is registered, you will receive a verification code.';
            return $response;
        }

        // OTP uzunluğu: SmsSettings varsa oradan, yoksa 6
        $otp_length = 6;
        if ( class_exists( '\SaltHareket\Sms\SmsSettings' ) ) {
            $sms_cfg    = \SaltHareket\Sms\SmsSettings::get();
            $otp_length = (int) ( $sms_cfg['otp_length'] ?? 6 );
        }
        $otp_expiry = 10 * MINUTE_IN_SECONDS; // 10 dakika

        // OTP üret
        $otp_code = '';
        for ( $i = 0; $i < $otp_length; $i++ ) {
            $otp_code .= wp_rand( 0, 9 );
        }

        // Forgot token — JS bu token ile OTP'yi doğrulayacak
        $forgot_token = wp_generate_password( 32, false );

        // Transient: token → [user_id, otp_code, expires]
        set_transient(
            'sh_forgot_otp_' . $forgot_token,
            [
                'user_id'  => $user->ID,
                'otp_code' => $otp_code,
                'email'    => $user->user_email,
                'expires'  => time() + $otp_expiry,
            ],
            $otp_expiry
        );

        // Email gönder
        $site_name = get_bloginfo( 'name' );
        $headers   = [
            'From: ' . $site_name . ' <' . get_bloginfo( 'admin_email' ) . '>',
            'Content-Type: text/html; charset=UTF-8',
        ];

        $body = '<p>Your password reset code is:</p>'
              . '<p style="font-size:32px;font-weight:bold;letter-spacing:.2em;">' . $otp_code . '</p>'
              . '<p>This code expires in 10 minutes. Do not share it with anyone.</p>';

        wp_mail(
            $user->user_email,
            $site_name . ' — Password Reset Code',
            $body,
            $headers
        );

        do_action( 'sh_password_reset_requested', $user->ID, $user->user_email );

        $response['message'] = 'If this email is registered, you will receive a verification code.';
        $response['data']    = [
            'forgot_token' => $forgot_token,
            'otp_expiry'   => $otp_expiry,
            'otp_length'   => $otp_length,
        ];

        return $response;
    }

    /**
     * Forgot OTP doğrula → reset_token döndür.
     *
     * @param array $vars ['forgot_token', 'otp_code']
     */
    public function verifyForgotOtp( array $vars = [] ): array
    {
        $response     = $this->response();
        $forgot_token = sanitize_text_field( $vars['forgot_token'] ?? '' );
        $otp_code     = preg_replace( '/\D/', '', $vars['otp_code'] ?? '' );

        if ( empty( $forgot_token ) || empty( $otp_code ) ) {
            $response['error']   = true;
            $response['message'] = 'Verification code is required.';
            return $response;
        }

        $transient_key = 'sh_forgot_otp_' . $forgot_token;
        $data          = get_transient( $transient_key );

        if ( ! $data || ! is_array( $data ) ) {
            $response['error']   = true;
            $response['message'] = 'Session expired. Please request a new code.';
            return $response;
        }

        // Süre kontrolü (transient zaten expiry ile set edildi, ek kontrol)
        if ( time() > ( $data['expires'] ?? 0 ) ) {
            delete_transient( $transient_key );
            $response['error']   = true;
            $response['message'] = 'Code has expired. Please request a new one.';
            return $response;
        }

        // Brute-force: attempt sayacı
        $attempt_key = 'sh_forgot_attempts_' . $forgot_token;
        $attempts    = (int) get_transient( $attempt_key );
        if ( $attempts >= 5 ) {
            delete_transient( $transient_key );
            delete_transient( $attempt_key );
            $response['error']   = true;
            $response['message'] = 'Too many failed attempts. Please request a new code.';
            return $response;
        }

        // OTP karşılaştır
        if ( ! hash_equals( (string) $data['otp_code'], $otp_code ) ) {
            set_transient( $attempt_key, $attempts + 1, 10 * MINUTE_IN_SECONDS );
            $response['error']   = true;
            $response['message'] = 'Invalid verification code.';
            return $response;
        }

        // Doğru — forgot_token'ı tüket, reset_token üret
        delete_transient( $transient_key );
        delete_transient( $attempt_key );

        $reset_token = wp_generate_password( 48, false );
        set_transient(
            'sh_reset_token_' . $reset_token,
            [
                'user_id' => $data['user_id'],
                'email'   => $data['email'],
            ],
            15 * MINUTE_IN_SECONDS
        );

        $response['message'] = 'Code verified.';
        $response['data']    = [ 'reset_token' => $reset_token ];

        return $response;
    }

    /**
     * Reset token ile yeni şifre set et.
     *
     * @param array $vars ['reset_token', 'new_password', 'confirm_password']
     */
    public function resetPasswordWithToken( array $vars = [] ): array
    {
        $response     = $this->response();
        $reset_token  = sanitize_text_field( $vars['reset_token'] ?? '' );
        $new_password = $vars['new_password'] ?? '';
        $confirm      = $vars['confirm_password'] ?? '';

        if ( empty( $reset_token ) ) {
            $response['error']   = true;
            $response['message'] = 'Invalid or expired session. Please start over.';
            return $response;
        }

        if ( strlen( $new_password ) < 8 ) {
            $response['error']   = true;
            $response['message'] = 'Password must be at least 8 characters.';
            return $response;
        }

        if ( $new_password !== $confirm ) {
            $response['error']   = true;
            $response['message'] = 'Passwords do not match.';
            return $response;
        }

        $transient_key = 'sh_reset_token_' . $reset_token;
        $data          = get_transient( $transient_key );

        if ( ! $data || ! is_array( $data ) || empty( $data['user_id'] ) ) {
            $response['error']   = true;
            $response['message'] = 'Session expired. Please start the password reset again.';
            return $response;
        }

        $user_id = (int) $data['user_id'];
        $user    = get_userdata( $user_id );

        if ( ! $user ) {
            $response['error']   = true;
            $response['message'] = 'User not found.';
            return $response;
        }

        // Token tek kullanımlık — hemen sil
        delete_transient( $transient_key );

        $updated = wp_update_user( [ 'ID' => $user_id, 'user_pass' => $new_password ] );

        if ( is_wp_error( $updated ) ) {
            $response['error']   = true;
            $response['message'] = 'Could not update your password. Please try again.';
            return $response;
        }

        update_user_meta( $user_id, 'password_set', true );
        $this->invalidateAllSessions( $user_id );
        do_action( 'sh_password_changed', $user_id );

        $response['message'] = 'Password updated successfully. You can now sign in.';
        return $response;
    }

    /**
     * Tüm aktif oturumları sonlandır (şifre değişince).
     * WP'nin session token sistemini kullanır.
     */
    public function invalidateAllSessions( int $user_id ): void
    {
        $manager = \WP_Session_Tokens::get_instance( $user_id );
        $manager->destroy_all();

        // sh_active_sessions meta'sını da temizle
        delete_user_meta( $user_id, 'sh_active_sessions' );

        do_action( 'sh_sessions_invalidated', $user_id );
    }
}
