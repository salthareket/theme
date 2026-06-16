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
     * Şifre kurtarma — config'e göre renew veya reset.
     * PASSWORD_RECOVER_TYPE: 'renew' (link gönder) | 'reset' (yeni şifre gönder)
     */
    public function passwordRecover( array $vars = [] ): array
    {
        $type = defined( 'PASSWORD_RECOVER_TYPE' ) ? PASSWORD_RECOVER_TYPE : 'renew';

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
