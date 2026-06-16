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

        // Başarılı login
        $this->clearFailedLogins( $username );
        wp_set_current_user( $signon->ID );
        wp_set_auth_cookie( $signon->ID, $remember );
        $this->refreshUser( $signon->ID );

        $response['message'] = 'Login successful.';

        // Redirect
        if ( ! empty( $vars['redirect_url'] ) ) {
            $response['redirect'] = $vars['redirect_url'];
        } elseif ( \Data::has( 'base_urls.logged_url' ) ) {
            $response['redirect'] = \Data::get( 'base_urls.logged_url' );
        } else {
            $response['redirect'] = $this->getEndpointUrl( 'profile' );
        }

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
