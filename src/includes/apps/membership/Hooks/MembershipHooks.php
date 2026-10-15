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

        // ── Public Profile Rewrite Rule ───────────────────────────────────────
        // profile/{slug} → author archive (author.php + author.twig)
        add_action( 'init', [ self::class, 'registerPublicProfileRewrite' ] );
        add_filter( 'query_vars', function( $vars ) {
            $vars[] = 'sh_profile_slug';
            return $vars;
        } );
        add_action( 'template_redirect', [ self::class, 'handlePublicProfileRedirect' ], 5 );

        // ── Endpoint Registration ─────────────────────────────────────────────
        add_action( 'init', [ $mm, 'registerEndpoints' ] );

        // Endpoint content fonksiyonlarını register et
        add_action( 'init', [ $mm, 'registerEndpointContent' ], 5 );

        // ── Register Form Field Actions ───────────────────────────────────────
        // sh_register_form_field_* action'larını register et — sadece enabled field'lar
        add_action( 'init', [ $mm, 'registerFormFieldActions' ], 10 );

        // ── Menü: not-activated item'ını aktive olmuş kullanıcıdan gizle ─────
        add_filter( 'sh_membership_menu_items', function( array $items ) use ( $mm ) {
            // edit-account (Account Details) — profile sayfası tüm bilgileri kapsıyor
            unset( $items['edit-account'] );
            // Eski hardcoded favorites'ı kaldır — yerini private reaction type'lar aldı
            unset( $items['favorites'] );

            if ( ! $mm->user ) return $items;
            // Kullanıcı aktive olmuşsa 'not-activated' menüden kaldır
            $user_id = is_object( $mm->user ) && isset( $mm->user->ID )
                ? (int) $mm->user->ID
                : get_current_user_id();
            if ( $user_id && $mm->isUserActive( $user_id ) ) {
                unset( $items['not-activated'] );
            }
            return $items;
        }, 20 );

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

        // ── WC menüsünden eski favorites'ı kaldır ────────────────────────────
        add_filter( 'woocommerce_account_menu_items', function( array $items ) {
            unset( $items['favorites'] );
            return $items;
        }, 20 );
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

    /**
     * Public profil URL rewrite kurallarını kaydet.
     * /profile/{slug} → ?sh_profile_slug={slug}
     * /role/{slug}    → author archive (role-based URL)
     */
    public static function registerPublicProfileRewrite(): void
    {
        if ( ! \SaltHareket\Membership\MembershipSettings::getSetting( 'enable_public_profile' ) ) return;

        $url_type   = \SaltHareket\Membership\MembershipSettings::getSetting( 'profile_url_type', 'standard' );
        $url_prefix = \SaltHareket\Membership\MembershipSettings::getSetting( 'profile_url_prefix', 'profile' );

        if ( $url_type === 'role' ) {
            // Tüm WP rolleri için tek rule — rewrite.php mantığı
            global $wp_roles;
            if ( ! isset( $wp_roles ) ) $wp_roles = new \WP_Roles();
            $roles = implode( '|', array_keys( $wp_roles->get_names() ) );
            if ( $roles ) {
                add_rewrite_rule(
                    '^(' . $roles . ')/([^/]+)/?$',
                    'index.php?sh_profile_slug=$matches[2]',
                    'top'
                );
            }
        } else {
            // Standard veya custom prefix
            add_rewrite_rule(
                '^' . preg_quote( $url_prefix, '/' ) . '/([^/]+)/?$',
                'index.php?sh_profile_slug=$matches[1]',
                'top'
            );
        }

        // author_link filter — /author/ → /profile/ veya /{role}/
        add_filter( 'author_link', function( $link, $author_id ) use ( $url_type, $url_prefix ) {
            if ( $url_type === 'role' ) {
                $role = function_exists( 'get_user_role' ) ? get_user_role( $author_id ) : '';
                if ( is_array( $role ) ) $role = ! empty( $role ) ? reset( $role ) : '';
                if ( $role ) $link = str_replace( '/author/', '/' . $role . '/', $link );
            } else {
                $link = str_replace( '/author/', '/' . $url_prefix . '/', $link );
            }
            return $link;
        }, 10, 2 );

        // Flush sadece yeni rule yoksa
        $rules   = get_option( 'rewrite_rules', [] );
        $pattern = $url_type === 'role' ? '^[^/]+/([^/]+)/?$' : '^' . preg_quote( $url_prefix, '/' ) . '/([^/]+)/?$';
        if ( is_array( $rules ) && ! isset( $rules[ $pattern ] ) ) {
            flush_rewrite_rules( false );
        }
    }

    /**
     * sh_profile_slug query var'ı gelince author archive'e yönlendir.
     */
    public static function handlePublicProfileRedirect(): void
    {
        $slug = get_query_var( 'sh_profile_slug' );
        if ( ! $slug ) return;

        // nicename veya nickname ile kullanıcı bul
        $user = get_user_by( 'slug', $slug ); // user_nicename
        if ( ! $user ) {
            global $wpdb;
            $user_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'nickname' AND meta_value = %s LIMIT 1",
                $slug
            ) );
            if ( $user_id ) $user = get_userdata( $user_id );
        }

        if ( ! $user ) {
            global $wp_query;
            $wp_query->set_404();
            status_header( 404 );
            nocache_headers();
            return;
        }

        // WP query'sini author archive gibi ayarla
        global $wp_query;
        $wp_query->is_author   = true;
        $wp_query->is_archive  = true;
        $wp_query->is_404      = false;
        $wp_query->is_home     = false;
        $wp_query->is_singular = false;
        $wp_query->queried_object    = $user;
        $wp_query->queried_object_id = $user->ID;
        $wp_query->set( 'author_name', $user->user_nicename );
        $wp_query->set( 'author', $user->ID );

        // Template'i doğrudan author.php'ye yönlendir
        add_filter( 'template_include', function( $template ) {
            $author_tpl = locate_template( 'author.php' );
            return $author_tpl ?: $template;
        }, 99 );
    }
}
