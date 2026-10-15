<?php

namespace SaltHareket\Membership\Concerns;

/**
 * HandlesAuth
 *
 * Login, logout, session ve online status işlemleri.
 * MembershipManager tarafından trait olarak kullanılır.
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-05-09
 *     - Add: login() — role kontrolü, redirect, clean return (echo yok)
 *     - Add: logout() — online status güncelle
 *     - Add: onUserLogin() / onUserLogout() — WP hook callback'leri
 *     - Add: logoutWithoutConfirmation() — WP logout nonce bypass fix
 *     - Add: updateOnlineStatus() / updateOnlineStatusLogout() — transient-based
 *     - Add: isUserOnline() — static helper
 *     - Add: generateRobotsTxt() — admin login'de robots.txt yenile
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * $mm = MembershipManager::getInstance();
 * $result = $mm->login(['username' => 'a@b.com', 'password' => '123']);
 * if (!$result['error']) wp_redirect($result['redirect']);
 *
 * ──────────────────────────────────────────────────────────
 *
 * @example
 *   $result = MembershipManager::getInstance()->login(['username' => 'a@b.com', 'password' => 'pass']);
 *
 * @example
 *   // Sadece belirli role izin ver:
 *   $result = MembershipManager::getInstance()->login($vars, 'customer');
 *
 * @example
 *   MembershipManager::getInstance()->logout();
 *
 * @example
 *   $online = MembershipManager::isUserOnline(42); // true/false
 *
 * @example
 *   // WP hook'larında kullanım (MembershipHooks.php'de register edilir):
 *   add_action('wp_login', [MembershipManager::getInstance(), 'onUserLogin'], 10, 2);
 */
trait HandlesAuth
{
    // ─── Login ───────────────────────────────────────────────────────────────

    /**
     * Kullanıcı girişi yap.
     *
     * @param array  $vars  ['username', 'password', 'remember', 'redirect_url', 'role']
     * @param string $role  Zorunlu rol filtresi (opsiyonel)
     * @return array        Standard response array
     */
    public function login( array $vars = [], string $role = '' ): array
    {
        $response = $this->response();

        $username = sanitize_text_field( $vars['username'] ?? '' );
        $password = $vars['password'] ?? '';
        $remember = ! empty( $vars['remember'] );

        if ( isset( $vars['role'] ) ) {
            $role = $vars['role'];
        }

        // Role kontrolü
        if ( ! empty( $role ) ) {
            $user_data = get_user_by( 'email', $username );
            if ( $user_data && ! in_array( $role, (array) $user_data->roles, true ) ) {
                $response['error']   = true;
                $response['message'] = 'Please use your ' . $role . ' account.';
                return $response;
            }
        }

        // Rate limit kontrolü
        if ( $this->isLoginRateLimited( $username ) ) {
            $response['error']   = true;
            $response['message'] = 'Too many login attempts. Please try again later.';
            return $response;
        }

        $signon = wp_signon(
            [
                'user_login'    => $username,
                'user_password' => $password,
                'remember'      => $remember,
            ],
            false
        );

        if ( is_wp_error( $signon ) ) {
            $this->recordFailedLogin( $username );
            $response['error']   = true;
            $response['message'] = 'Wrong username or password.';
            return $response;
        }

        // Başarılı login — 2FA aktifse auth cookie set etme, önce doğrulama yap
        $this->clearFailedLogins( $username );

        // Trusted device kontrolü — güvenilir cihazsa 2FA atla
        $needs2FA = $this->is2FAEnabled( $signon->ID ) && ! $this->isTrustedDevice( $signon->ID );

        if ( $needs2FA ) {
            $method = $this->get2FAMethod();
            $token  = $this->setPending2FA( $signon->ID );

            if ( $method === 'sms' ) {
                $sms = $this->send2FASMS( $signon->ID );
                $response['requires_2fa'] = true;
                $response['method']       = 'sms';
                $response['pending_token'] = $token;
                $response['otp_id']       = $sms['data']['otp_id'] ?? ( $sms['otp_id'] ?? '' );
                $response['message']      = 'Verification code sent to your phone.';
            } elseif ( $method === 'totp' ) {
                $response['requires_2fa']  = true;
                $response['method']        = 'totp';
                $response['pending_token'] = $token;
                $response['message']       = 'Enter the code from your authenticator app.';
            }

            return $response;
        }

        // 2FA yok veya trusted device → direkt giriş
        // Aktivasyon kontrolü — aktive edilmemişse cookie set etme
        if ( self::isActivationRequired() && ! $this->isUserActive( $signon->ID ) ) {
            // Cookie set et ki not-activated sayfası görünsün (WooCommerce endpoint erişimi için)
            wp_set_current_user( $signon->ID );
            wp_set_auth_cookie( $signon->ID, $remember );
            $this->refreshUser( $signon->ID );

            $response['message']  = 'Please activate your account.';
            $response['redirect'] = $this->getEndpointUrl( 'not-activated' );
            return $response;
        }

        wp_set_current_user( $signon->ID );
        wp_set_auth_cookie( $signon->ID, $remember );
        $this->refreshUser( $signon->ID );

        $response['message'] = 'Login successful.';

        // ── Redirect URL belirle ──────────────────────────────────────────────
        // Öncelik: form'dan gelen redirect_url > settings > fallback
        if ( ! empty( $vars['redirect_url'] ) ) {
            // Form veya çağıran tarafından belirtilmişse direkt kullan
            $redirect_url = esc_url_raw( $vars['redirect_url'] );
        } else {
            $redirect_type   = \SaltHareket\Membership\MembershipSettings::getSetting( 'login_redirect_type', 'dashboard' );
            $redirect_page_id = (int) \SaltHareket\Membership\MembershipSettings::getSetting( 'login_redirect_page_id', 0 );

            switch ( $redirect_type ) {
                case 'referer':
                    // Session'da saklanan referer (woo_login_url() tarafından set edilir)
                    $referer = isset( $_SESSION['referer_url'] ) ? esc_url_raw( $_SESSION['referer_url'] ) : '';
                    if ( $referer ) {
                        unset( $_SESSION['referer_url'] );
                    }
                    $redirect_url = $referer ?: $this->getEndpointUrl( 'dashboard' );
                    break;

                case 'profile':
                    $redirect_url = $this->getEndpointUrl( 'profile' );
                    break;

                case 'home':
                    $redirect_url = home_url( '/' );
                    break;

                case 'custom':
                    if ( $redirect_page_id > 0 ) {
                        // Polylang aktifse kullanıcının aktif dili için çeviri sayfasını al
                        if ( function_exists( 'pll_get_post' ) ) {
                            $user_lang        = function_exists( 'pll_current_language' ) ? pll_current_language() : pll_default_language();
                            $translated_id    = pll_get_post( $redirect_page_id, $user_lang );
                            $target_id        = ( $translated_id && $translated_id !== $redirect_page_id ) ? $translated_id : $redirect_page_id;
                        } else {
                            $target_id = $redirect_page_id;
                        }
                        $redirect_url = (string) get_permalink( $target_id );
                    } else {
                        $redirect_url = $this->getEndpointUrl( 'dashboard' );
                    }
                    break;

                case 'dashboard':
                default:
                    $redirect_url = $this->getEndpointUrl( 'dashboard' );
                    break;
            }
        }

        // ── Filter: dışarıdan override edilebilir ─────────────────────────────
        $redirect_url = (string) apply_filters( 'sh_login_redirect_url', $redirect_url, $signon->ID );

        $response['redirect'] = $redirect_url;

        return $response;
    }

    // ─── Rate Limiting ───────────────────────────────────────────────────────

    /**
     * Login rate limit kontrolü — transient-based, plugin gerektirmez.
     * 5 başarısız denemeden sonra 15 dakika blok.
     */
    private function isLoginRateLimited( string $username ): bool
    {
        $key     = 'sh_login_fail_' . md5( $username . ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
        $attempts = (int) get_transient( $key );
        return $attempts >= 5;
    }

    private function recordFailedLogin( string $username ): void
    {
        $key      = 'sh_login_fail_' . md5( $username . ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
        $attempts = (int) get_transient( $key );
        set_transient( $key, $attempts + 1, 15 * MINUTE_IN_SECONDS );
    }

    private function clearFailedLogins( string $username ): void
    {
        $key = 'sh_login_fail_' . md5( $username . ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
        delete_transient( $key );
    }

    // ─── Logout ──────────────────────────────────────────────────────────────

    /**
     * Kullanıcı çıkışı yap.
     */
    public function logout(): void
    {
        $user_id = get_current_user_id();
        if ( $user_id ) {
            $this->updateOnlineStatusLogout( $user_id );
        }
        wp_logout();
    }

    // ─── WP Hook Callbacks ───────────────────────────────────────────────────

    /**
     * wp_login hook callback.
     */
    public function onUserLogin( string $user_login, \WP_User $user ): void
    {
        update_user_meta( $user->ID, 'last_login', time() );
        $this->generateRobotsTxt( $user );
    }

    /**
     * wp_logout hook callback.
     */
    public function onUserLogout( int $user_id ): void
    {
        $this->updateOnlineStatusLogout( $user_id );
    }

    /**
     * WP logout nonce bypass — nonce olmadan logout çalışsın.
     */
    public function logoutWithoutConfirmation( string $action, $result ): void
    {
        if ( ! $result && $action === 'log-out' ) {
            wp_safe_redirect( $this->getLogoutUrl() );
            exit();
        }
    }

    // ─── Online Status ───────────────────────────────────────────────────────

    /**
     * Kullanıcının online durumunu güncelle (her sayfa yüklemesinde).
     * 15 dakika içinde aktivite varsa online sayılır.
     */
    public function updateOnlineStatus(): void
    {
        if ( ! is_user_logged_in() ) return;

        $users        = get_transient( 'sh_users_online' ) ?: [];
        $user_id      = get_current_user_id();
        $current_time = current_time( 'timestamp' );

        if (
            ! isset( $users[ $user_id ] ) ||
            $users[ $user_id ] < ( $current_time - 15 * MINUTE_IN_SECONDS )
        ) {
            $users[ $user_id ] = $current_time;
            set_transient( 'sh_users_online', $users, 30 * MINUTE_IN_SECONDS );
        }
    }

    /**
     * Kullanıcıyı offline yap (logout'ta).
     */
    public function updateOnlineStatusLogout( int $user_id ): void
    {
        if ( $user_id < 1 ) return;

        update_user_meta( $user_id, 'last_logout', time() );

        $users = get_transient( 'sh_users_online' ) ?: [];
        unset( $users[ $user_id ] );
        set_transient( 'sh_users_online', $users, 30 * MINUTE_IN_SECONDS );
    }

    /**
     * Kullanıcı online mı?
     */
    public static function isUserOnline( int $user_id ): bool
    {
        $users = get_transient( 'sh_users_online' ) ?: [];
        return isset( $users[ $user_id ] ) &&
               $users[ $user_id ] > ( current_time( 'timestamp' ) - 15 * MINUTE_IN_SECONDS );
    }

    // ─── Robots.txt ──────────────────────────────────────────────────────────

    /**
     * Admin login olduğunda robots.txt'i yenile.
     */
    private function generateRobotsTxt( \WP_User $user ): void
    {
        if ( ! user_can( $user, 'manage_options' ) ) return;

        $content = "User-agent: *\nDisallow:\n";

        if ( function_exists( 'wpseo_sitemap_url' ) ) {
            $content .= 'Sitemap: ' . wpseo_sitemap_url() . "\n";
        } else {
            $content .= 'Sitemap: ' . home_url( '/sitemap.xml' ) . "\n";
        }

        foreach ( [ 'llms.txt', 'ssms.txt' ] as $file ) {
            if ( file_exists( ABSPATH . $file ) ) {
                $content .= 'Sitemap: ' . home_url( '/' . $file ) . "\n";
            }
        }

        file_put_contents( ABSPATH . 'robots.txt', $content );
    }
}
