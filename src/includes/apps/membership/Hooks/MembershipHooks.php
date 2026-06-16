<?php

namespace SaltHareket\Membership\Hooks;

use SaltHareket\Membership\MembershipManager;

/**
 * MembershipHooks
 *
 * Tüm WP/WooCommerce hook'larını register eder.
 * bootstrap.php'den çağrılır.
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-05-09
 *     - Add: register() — tüm hook'ları kaydet
 *     - Add: registerPageManagement() — WC activate/deactivate page fix
 *     - Add: Activation URL handler'ları (init hook)
 *     - Add: Template redirect hook'ları
 *     - Add: Login/logout hook'ları
 *     - Add: Online status hook'ları
 *     - Add: Account deletion cron
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // bootstrap.php'de otomatik çağrılır:
 * MembershipHooks::register();
 * MembershipHooks::registerPageManagement();
 *
 * // Bir hook'u devre dışı bırak:
 * add_filter('sh_membership_hooks_disabled', function($disabled) {
 *     $disabled[] = 'redirect_not_activated';
 *     return $disabled;
 * });
 *
 * ──────────────────────────────────────────────────────────
 *
 * @example
 *   // Hook devre dışı bırak:
 *   add_filter('sh_membership_hooks_disabled', fn($d) => array_merge($d, ['online_status']));
 *
 * @example
 *   // Activation URL handler'ı özelleştir:
 *   add_filter('sh_activation_code_response', function($result, $code) {
 *       return $result;
 *   }, 10, 2);
 *
 * @example
 *   // Hesap silme cron'u özelleştir:
 *   add_filter('sh_account_deletion_grace_days', fn() => 14); // 14 güne indir
 *
 * @example
 *   // Login redirect özelleştir:
 *   add_filter('sh_login_redirect_url', function($url, $user_id) {
 *       return get_account_endpoint_url('dashboard');
 *   }, 10, 2);
 *
 * @example
 *   // Profile required endpoints özelleştir:
 *   add_filter('sh_profile_required_endpoints', function($endpoints) {
 *       $endpoints[] = 'my-custom-endpoint';
 *       return $endpoints;
 *   });
 */
class MembershipHooks
{
    public static function register(): void
    {
        if ( ! MembershipManager::isActive() ) return;

        $mm = MembershipManager::getInstance();

        // ── Login / Logout ────────────────────────────────────────────────────
        add_action( 'wp_login',          [ $mm, 'onUserLogin' ], 10, 2 );
        add_action( 'wp_logout',         [ $mm, 'onUserLogout' ], 10, 1 );
        add_action( 'check_admin_referer', [ $mm, 'logoutWithoutConfirmation' ], 1, 2 );

        // ── Online Status ─────────────────────────────────────────────────────
        if ( is_user_logged_in() && ! self::isDisabled( 'online_status' ) ) {
            add_action( 'wp', [ $mm, 'updateOnlineStatus' ] );
        }

        // ── Profile Hooks ─────────────────────────────────────────────────────
        // profile_update: user nesnesini tazele + LCP reset (SaltBase'de kalıyor)
        // update_user_meta: kaldırıldı — gereksiz yük, SaltBase delegate'i yönetiyor

        // ── Template Redirects ────────────────────────────────────────────────
        if ( is_user_logged_in() && ! self::isDisabled( 'redirects' ) ) {
            add_action( 'template_redirect', [ $mm, 'redirectToProfile' ] );
            add_action( 'template_redirect', [ $mm, 'redirectIfNotCompleted' ] );

            if ( MembershipManager::isActivationRequired() ) {
                add_action( 'template_redirect', [ $mm, 'redirectIfNotActivated' ] );
            }
        }

        // ── Activation URL Handlers ───────────────────────────────────────────
        add_action( 'init', [ self::class, 'handleActivationUrls' ], 99999 );

        // ── Endpoint Registration ─────────────────────────────────────────────
        add_action( 'init', [ $mm, 'registerEndpoints' ] );

        // Endpoint content fonksiyonlarını register et
        add_action( 'init', [ $mm, 'registerEndpointContent' ], 5 );

        // ── Social Login ──────────────────────────────────────────────────────
        if ( defined( 'ENABLE_SOCIAL_LOGIN' ) && ENABLE_SOCIAL_LOGIN ) {
            $mm->registerSocialLoginHooks();
        }

        // ── WooCommerce user_register ─────────────────────────────────────────
        if ( MembershipManager::isWooActive() ) {
            add_action( 'user_register', [ $mm, 'userRegisterHook' ], 10, 1 );
        }

        // ── Account Deletion Cron ─────────────────────────────────────────────
        if ( ! self::isDisabled( 'deletion_cron' ) ) {
            self::registerDeletionCron();
        }

        // ── Admin Hooks ───────────────────────────────────────────────────────
        // edit_user_profile_update: admin profil sayfasından status güncelleme
        // SaltBase delegate'i (onAdminUserUpdate) bu işi yapıyor
        // add_action( 'edit_user_profile_update', [ $mm, 'onAdminUserUpdate' ] );
    }

    /**
     * WooCommerce page management hook'larını register et.
     * bootstrap.php'den ayrı çağrılır — her zaman aktif olmalı.
     */
    public static function registerPageManagement(): void
    {
        // Duplicate page koruması: woocommerce_page_myaccount set ve geçerliyse yeniden oluşturma
        add_action('woocommerce_installed', function() {
            self::preventDuplicateMyAccountPage();
        }, 1);

        add_action('after_switch_theme', function() {
            self::preventDuplicateMyAccountPage();
        });

        \SaltHareket\Membership\MembershipManager::registerPageManagementHooks();
    }

    /**
     * My Account duplicate page koruması.
     * woocommerce_page_myaccount option set edilmişse veya
     * template-my-account.php template'li sayfa varsa yeni page oluşturma.
     */
    private static function preventDuplicateMyAccountPage(): void
    {
        // WC'nin my-account page option'ı set ve geçerli mi?
        $wcPageId = (int) get_option('woocommerce_myaccount_page_id', 0);
        if ($wcPageId > 0 && get_post_status($wcPageId) === 'publish') {
            return; // Zaten var, oluşturma
        }

        // template-my-account.php template'li sayfa var mı?
        $pages = get_pages([
            'meta_key'   => '_wp_page_template',
            'meta_value' => 'template-my-account.php',
            'number'     => 1,
        ]);
        if (!empty($pages)) {
            // Bu sayfayı WC'ye ata
            update_option('woocommerce_myaccount_page_id', $pages[0]->ID);
            return;
        }
    }

    // ─── Activation URL Handlers ─────────────────────────────────────────────

    /**
     * URL'deki aktivasyon parametrelerini işle.
     * Eski membership-functions.php'deki inline JS kaldırıldı.
     * Artık clean PHP — JS'e veri wp_head üzerinden geçiyor.
     */
    public static function handleActivationUrls(): void
    {
        $mm = MembershipManager::getInstance();

        // ?activation-code=xxx — email aktivasyon
        if ( isset( $_GET['activation-code'] ) ) {
            $code = sanitize_text_field( $_GET['activation-code'] );

            // Giriş yapılmamışsa autologin dene
            if ( ! is_user_logged_in() && defined( 'ENABLE_ACTIVATION_EMAIL_AUTOLOGIN' ) && ENABLE_ACTIVATION_EMAIL_AUTOLOGIN ) {
                self::tryAutologinFromCode( $code );
            }

            if ( is_user_logged_in() ) {
                $result = $mm->verifyEmailCode( $code, get_current_user_id() );
                self::queueActivationNotice( $result['success'], $result['message'], 'activation-code' );
            } else {
                self::queueActivationNotice( false, 'Please login before using your activation link.', 'activation-code' );
            }
        }

        // ?activation-email=xxx — email değişikliği doğrulama
        if ( isset( $_GET['activation-email'] ) && is_user_logged_in() ) {
            $code   = sanitize_text_field( $_GET['activation-email'] );
            $result = $mm->verifyEmailChange( $code, get_current_user_id() );
            self::queueActivationNotice( $result['success'], $result['message'], 'activation-email' );
        }

        // ?activation-password=xxx — şifre sıfırlama
        if ( isset( $_GET['activation-password'] ) ) {
            $code   = sanitize_text_field( $_GET['activation-password'] );
            $result = $mm->processPasswordReset( $code );
            if ( $result['success'] ) {
                // Şifre sıfırlama formunu göster — session'a email'i koy
                if ( isset( $_SESSION ) ) {
                    $_SESSION['password_reset_email'] = $result['email'];
                }
            }
        }
    }

    /**
     * Aktivasyon kodundan autologin dene.
     */
    private static function tryAutologinFromCode( string $code ): void
    {
        $decrypt = new \Encrypt();
        $data    = $decrypt->decrypt( $code );

        if ( ! $data || empty( $data['id'] ) ) return;

        $user_id = (int) $data['id'];
        $user    = get_userdata( $user_id );
        if ( ! $user ) return;

        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id );
    }

    /**
     * Aktivasyon sonucu mesajını wp_head'e koy (JS ile gösterilecek).
     * Eski inline JS yerine clean approach.
     */
    private static function queueActivationNotice( bool $success, string $message, string $param ): void
    {
        add_action( 'wp_head', function () use ( $success, $message, $param ) {
            echo '<script>window.shActivation=' . wp_json_encode( [
                'success' => $success,
                'message' => $message,
                'param'   => $param,
            ] ) . ';</script>' . "\n";
        }, 1 );
    }

    // ─── Account Deletion Cron ───────────────────────────────────────────────

    /**
     * Hesap silme cron'unu register et.
     * Günlük çalışır, süresi dolan talepleri işler.
     */
    private static function registerDeletionCron(): void
    {
        if ( ! wp_next_scheduled( 'sh_process_account_deletions' ) ) {
            wp_schedule_event( time(), 'daily', 'sh_process_account_deletions' );
        }

        add_action( 'sh_process_account_deletions', [ self::class, 'processPendingDeletions' ] );
    }

    /**
     * Süresi dolan hesap silme taleplerini işle.
     */
    public static function processPendingDeletions(): void
    {
        $grace_days = (int) apply_filters( 'sh_account_deletion_grace_days', 30 );
        $cutoff     = time() - ( $grace_days * DAY_IN_SECONDS );

        $users = get_users( [
            'meta_key'     => 'deletion_requested_at',
            'meta_value'   => $cutoff,
            'meta_compare' => '<=',
            'fields'       => 'ID',
        ] );

        $mm = MembershipManager::getInstance();

        foreach ( $users as $user_id ) {
            $mm->processAccountDeletion( (int) $user_id );
            error_log( '[Membership] Account deleted (cron): user_id=' . $user_id );
        }
    }

    // ─── Helper ──────────────────────────────────────────────────────────────

    private static function isDisabled( string $hook ): bool
    {
        $disabled = apply_filters( 'sh_membership_hooks_disabled', [] );
        return in_array( $hook, (array) $disabled, true );
    }
}
