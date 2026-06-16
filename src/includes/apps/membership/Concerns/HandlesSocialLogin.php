<?php

namespace SaltHareket\Membership\Concerns;

/**
 * HandlesSocialLogin
 *
 * Sosyal login adapter katmanı.
 * Plugin bağımsız — bugün NSL, yarın başka bir plugin olabilir.
 * Dışarıdan bakan kod hangi plugin kullanıldığını bilmez.
 *
 * Desteklenen plugin'ler:
 *   - NextEnd Social Login (NSL) — class_exists('NextendSocialLogin')
 *   - Genişletilebilir: add_filter('sh_social_login_providers', ...)
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-05-09
 *     - Add: registerHooks() — aktif plugin'e göre hook'ları bağla
 *     - Add: onSocialRegister() — kayıt sonrası role set, log
 *     - Add: onSocialLogin() — login log
 *     - Add: getLoginErrorMessage() — sosyal hesap bilgisi göster
 *     - Add: getProviders() — aktif provider listesi
 *     - Add: isConnected() — kullanıcı sosyal hesap bağlamış mı
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // bootstrap.php'de otomatik register edilir.
 * // Dışarıdan provider ekle:
 * add_filter('sh_social_login_providers', function($providers) {
 *     $providers[] = 'twitter';
 *     return $providers;
 * });
 *
 * ──────────────────────────────────────────────────────────
 *
 * @example
 *   // Kullanıcı sosyal hesap bağlamış mı:
 *   $providers = MembershipManager::getInstance()->getSocialProviders($user_id);
 *   // ['google', 'facebook']
 *
 * @example
 *   // Login error mesajını özelleştir:
 *   add_filter('sh_social_login_error_message', function($msg, $providers) {
 *       return 'Please use ' . implode(' or ', $providers) . ' to login.';
 *   }, 10, 2);
 *
 * @example
 *   // Sosyal kayıt sonrası hook:
 *   add_action('sh_social_user_registered', function($user_id, $provider) {
 *       // log tut, hoşgeldin emaili gönder
 *   }, 10, 2);
 *
 * @example
 *   // Sosyal login sonrası hook:
 *   add_action('sh_social_user_login', function($user_id, $provider) {
 *       // last_login güncelle
 *   }, 10, 2);
 *
 * @example
 *   // Farklı plugin için adapter ekle:
 *   add_action('sh_social_login_register_hooks', function() {
 *       add_filter('my_social_plugin_register', function($user_id) {
 *           do_action('sh_social_user_registered', $user_id, 'my_plugin');
 *       });
 *   });
 */
trait HandlesSocialLogin
{
    /**
     * Sosyal login hook'larını register et.
     * Hangi plugin aktifse onun hook'larını bağlar.
     * MembershipHooks::register()'dan çağrılır.
     */
    public function registerSocialLoginHooks(): void
    {
        // NSL (NextEnd Social Login)
        if ( class_exists( 'NextendSocialLogin' ) || defined( 'NEXTEND_SOCIAL_LOGIN_VERSION' ) ) {
            $this->registerNslHooks();
        }

        // Diğer plugin'ler için genişletme noktası
        do_action( 'sh_social_login_register_hooks' );

        // Login error mesajı — plugin bağımsız
        add_filter( 'login_errors', [ $this, 'getSocialLoginErrorMessage' ] );
    }

    // ─── NSL Adapter ─────────────────────────────────────────────────────────

    /**
     * NSL hook'larını bağla.
     * nsl.php'deki eski kod buraya taşındı — plugin varsa çalışır, yoksa sessiz.
     */
    private function registerNslHooks(): void
    {
        add_filter( 'nsl_register_new_user', [ $this, 'onSocialRegister' ], 999999, 2 );
        add_filter( 'nsl_login', [ $this, 'onSocialLogin' ], 199990, 2 );
    }

    // ─── Callbacks ───────────────────────────────────────────────────────────

    /**
     * Sosyal kayıt callback — plugin'den gelen user_id ve provider.
     * NSL: nsl_register_new_user($user_id, $provider)
     */
    public function onSocialRegister( int $user_id, $provider ): int
    {
        $provider_name = $this->resolveProviderName( $provider );

        update_user_meta( $user_id, 'register_type', $provider_name );

        if ( class_exists( 'Logger' ) ) {
            $log = new \Logger();
            $log->logAction( 'social_register', $provider_name . ' | user_id:' . $user_id );
        }

        do_action( 'sh_social_user_registered', $user_id, $provider_name );

        return $user_id;
    }

    /**
     * Sosyal login callback.
     * NSL: nsl_login($user_id, $provider)
     */
    public function onSocialLogin( int $user_id, $provider ): int
    {
        $provider_name = $this->resolveProviderName( $provider );

        update_user_meta( $user_id, 'last_login', time() );

        if ( class_exists( 'Logger' ) ) {
            $log = new \Logger();
            $log->logAction( 'social_login', $provider_name . ' | user_id:' . $user_id );
        }

        do_action( 'sh_social_user_login', $user_id, $provider_name );

        return $user_id;
    }

    /**
     * Login error mesajı — sosyal hesap varsa bilgi göster.
     * login_errors filter callback.
     */
    public function getSocialLoginErrorMessage( string $error ): string
    {
        $email = isset( $_POST['username'] ) ? sanitize_email( $_POST['username'] ) : '';
        if ( empty( $email ) ) return $error;

        $user = get_user_by( 'email', $email );
        if ( ! $user ) return $error;

        $providers = $this->getSocialProviders( $user->ID );
        if ( empty( $providers ) ) return $error;

        $list = '<b>' . implode( '</b> or <b>', array_map( 'esc_html', $providers ) ) . '</b>';

        $message = 'Since you registered using your ' . $list . ' account' .
                   ( count( $providers ) > 1 ? 's' : '' ) .
                   ', please log in using those accounts.<br><br>' .
                   'Then, define your password through the <u>Profile → Security</u> page to log in with email and password.';

        return apply_filters( 'sh_social_login_error_message', $message, $providers );
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Kullanıcının bağlı sosyal provider'larını döndür.
     * NSL'nin wp_social_users tablosunu okur.
     * Plugin yoksa boş array döner.
     */
    public function getSocialProviders( int $user_id ): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'social_users';

        // Tablo var mı kontrol et
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
            return [];
        }

        $results = $wpdb->get_col( $wpdb->prepare(
            "SELECT type FROM {$table} WHERE ID = %d",
            $user_id
        ) );

        return $results ?: [];
    }

    /**
     * Kullanıcı herhangi bir sosyal hesap bağlamış mı?
     */
    public function hasSocialLogin( int $user_id ): bool
    {
        return ! empty( $this->getSocialProviders( $user_id ) );
    }

    /**
     * Provider nesnesinden/string'inden isim çıkar.
     * NSL provider nesnesi veya string olabilir.
     */
    private function resolveProviderName( $provider ): string
    {
        if ( is_string( $provider ) ) return $provider;
        if ( is_object( $provider ) ) {
            // NSL provider nesnesi: getName() veya getDbSlug() metodu
            if ( method_exists( $provider, 'getName' ) )   return $provider->getName();
            if ( method_exists( $provider, 'getDbSlug' ) ) return $provider->getDbSlug();
            if ( isset( $provider->name ) )                return $provider->name;
        }
        return 'social';
    }
}
