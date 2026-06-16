<?php

namespace SaltHareket\Membership\Concerns;

/**
 * HandlesRegistration
 *
 * Kullanıcı kayıt işlemleri.
 * MembershipManager tarafından trait olarak kullanılır.
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-05-09
 *     - Add: register() — wp_insert_user + user_register_hook
 *     - Add: userRegisterHook() — role set, activation trigger, notification
 *     - Add: userExist() / nicknameExist() — validation helpers
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * $mm = MembershipManager::getInstance();
 * $result = $mm->register([
 *     'email'      => 'a@b.com',
 *     'password'   => '123456',
 *     'first_name' => 'Ali',
 *     'last_name'  => 'Veli',
 *     'role'       => 'customer',
 * ]);
 *
 * ──────────────────────────────────────────────────────────
 *
 * @example
 *   $result = MembershipManager::getInstance()->register($vars);
 *   if ($result['error']) echo $result['message'];
 *
 * @example
 *   $exists = MembershipManager::userExist(['email' => 'a@b.com']);
 *   if ($exists) echo $exists; // hata mesajı döner
 *
 * @example
 *   $exists = MembershipManager::nicknameExist(['nickname' => 'aliVeli']);
 *
 * @example
 *   // Kayıt sonrası hook dinle:
 *   add_action('sh_user_registered', function($user_id, $role) { ... }, 10, 2);
 *
 * @example
 *   // Kayıt rolünü filtrele:
 *   add_filter('sh_register_default_role', function($role, $vars) {
 *       return 'subscriber';
 *   }, 10, 2);
 */
trait HandlesRegistration
{
    /**
     * Yeni kullanıcı kaydı oluştur.
     *
     * @param array  $vars     ['email', 'password', 'first_name', 'last_name', 'role']
     * @param string $callback Opsiyonel callback slug (eski uyumluluk)
     * @param string $role     Varsayılan rol
     * @return array           Standard response array
     */
    public function register( array $vars = [], string $callback = '', string $role = 'author' ): array
    {
        $response = $this->response();

        $email      = sanitize_email( $vars['email'] ?? '' );
        $first_name = sanitize_text_field( $vars['first_name'] ?? '' );
        $last_name  = sanitize_text_field( $vars['last_name'] ?? '' );
        $password   = $vars['password'] ?? '';
        $user_role  = sanitize_text_field( $vars['role'] ?? '' );

        if ( ! empty( $user_role ) ) {
            $role = $user_role;
        }

        // Filter ile rol override edilebilir
        $role = apply_filters( 'sh_register_default_role', $role, $vars );

        if ( empty( $email ) || ! is_email( $email ) ) {
            $response['error']   = true;
            $response['message'] = 'Valid email address is required.';
            return $response;
        }

        $user_data = [
            'user_login'   => $email,
            'user_email'   => $email,
            'user_pass'    => $password,
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'user_nicename'=> strtolower( $email ),
            'display_name' => trim( $first_name . ' ' . $last_name ) ?: $email,
            'role'         => $role,
        ];

        $user_id = wp_insert_user( $user_data );

        if ( is_wp_error( $user_id ) ) {
            $errors = $user_id->errors;
            if ( isset( $errors['existing_user_email'] ) ) {
                $response['message'] = 'This email address is already registered.';
            } elseif ( isset( $errors['existing_user_login'] ) ) {
                $response['message'] = 'This username is already taken.';
            } elseif ( isset( $errors['empty_user_login'] ) ) {
                $response['message'] = 'Email address is required.';
            } else {
                $response['message'] = 'Registration failed. Please check your details.';
            }
            $response['error'] = true;
            return $response;
        }

        $this->refreshUser( $user_id );
        $response = $this->userRegisterHook( $user_id, $vars );

        return $response;
    }

    /**
     * Kayıt sonrası hook — rol set, aktivasyon, notification, autologin.
     * WooCommerce user_register hook'undan da çağrılır.
     *
     * @param int   $user_id
     * @param array $vars    POST vars (opsiyonel — WC'den gelince boş olabilir)
     * @return array
     */
    public function userRegisterHook( int $user_id, array $vars = [] ): array
    {
        $response = $this->response();

        // Register type (email / social provider adı)
        $register_type = 'email';
        $qs = json_decode( function_exists( 'queryStringJSON' ) ? queryStringJSON() : '{}', true );
        if ( ! empty( $qs['loginSocial'] ) ) {
            $register_type = sanitize_text_field( $qs['loginSocial'] );
        }
        update_user_meta( $user_id, 'register_type', $register_type );

        // Rol belirle
        $role = sanitize_text_field( $vars['role'] ?? 'default' );
        if ( empty( $role ) ) $role = 'default';

        // Rolü WP'ye set et
        $user_obj = new \WP_User( $user_id );
        $user_obj->set_role( $role );
        wp_update_user( [ 'ID' => $user_id, 'role' => $role ] );

        $this->refreshUser( $user_id );

        $password_set = false;

        if ( $role !== 'default' ) {
            $password_set = true;

            if ( self::isActivationRequired() || ( defined( 'ENABLE_SMS_NOTIFICATIONS' ) && ENABLE_SMS_NOTIFICATIONS ) ) {
                $activation_type = defined( 'MEMBERSHIP_ACTIVATION_TYPE' ) ? MEMBERSHIP_ACTIVATION_TYPE : 'email';

                if ( $activation_type === 'sms' || ( defined( 'ENABLE_SMS_NOTIFICATIONS' ) && ENABLE_SMS_NOTIFICATIONS ) ) {
                    // SMS gereksinimlerini kaydet
                    $sms_vars            = $vars;
                    $sms_vars['action']  = 'save_sms_requirements';
                    $sms_vars['refresh'] = true;
                    $response            = $this->updateProfile( $sms_vars );
                    $this->refreshUser( $user_id );

                    // Aktivasyon yoksa direkt new-account notification
                    if ( ! $response['error'] && ! self::isActivationRequired() ) {
                        $this->fireAccountEvent( 'new-account', $user_id );
                    }
                } else {
                    // Email aktivasyon gönder
                    $response = $this->sendActivation( $user_id );
                }
            } else {
                // Aktivasyon yok — direkt new-account
                $this->fireAccountEvent( 'new-account', $user_id );
            }
        }

        update_user_meta( $user_id, 'password_set', $password_set );

        // Autologin
        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id );

        // Hook: dışarıdan dinlenebilir
        do_action( 'sh_user_registered', $user_id, $role );

        // AJAX'ta response dön, değilse redirect
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            if ( empty( $response['redirect'] ) ) {
                $response['redirect'] = $this->getEndpointUrl( 'profile' );
            }
            return $response;
        }

        wp_redirect( $this->getEndpointUrl( 'profile' ) );
        exit();
    }

    /**
     * Email adresi kayıtlı mı kontrol et.
     *
     * @param array $vars ['email', 'exclude' => 'bu email hariç tut']
     * @return string|false Hata mesajı veya false
     */
    public static function userExist( array $vars = [] )
    {
        $email = sanitize_email( $vars['email'] ?? '' );

        if ( ! empty( $vars['exclude'] ) && $vars['exclude'] === $email ) {
            return false;
        }

        return email_exists( $email )
            ? 'That email address is already registered.'
            : false;
    }

    /**
     * Nickname/kullanıcı adı kayıtlı mı kontrol et.
     *
     * @param array $vars ['nickname', 'exclude', 'user_id']
     * @return string|false
     */
    public static function nicknameExist( array $vars = [] )
    {
        global $wpdb;

        $nickname = sanitize_text_field( $vars['nickname'] ?? '' );
        $user_id  = (int) ( $vars['user_id'] ?? get_current_user_id() );

        if ( ! empty( $vars['exclude'] ) && $vars['exclude'] === $nickname ) {
            return false;
        }

        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(ID) FROM {$wpdb->users} u
             JOIN {$wpdb->usermeta} m ON u.ID = m.user_id
             WHERE m.meta_key = 'nickname' AND m.meta_value = %s AND u.ID <> %d",
            $nickname,
            $user_id
        ) );

        return $exists ? 'That username is already taken.' : false;
    }

    /**
     * Account event'i tetikle — Notifications app'e bildir.
     * Hem eski Notifications::on() hem yeni Notifications::fire() desteklenir.
     */
    private function fireAccountEvent( string $event, int $user_id ): void
    {
        if ( ! $this->user ) $this->refreshUser( $user_id );

        $role = $this->user ? $this->user->get_role() : 'subscriber';

        // Yeni sistem
        if ( defined( 'ENABLE_NOTIFICATIONS' ) && ENABLE_NOTIFICATIONS && class_exists( 'Notifications' ) ) {
            \Notifications::fire( $event, [
                'user'      => $this->user,
                'recipient' => $user_id,
            ] );
        }

        do_action( 'sh_membership_event', $event, $user_id );
    }
}
