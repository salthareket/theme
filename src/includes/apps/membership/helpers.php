<?php

/**
 * Membership Global Helper Functions
 *
 * Namespace olmadan tanımlanır — global scope'da erişilebilir.
 * Eski membership-functions.php'deki fonksiyonların delegate'leri.
 * bootstrap.php'den include edilir.
 */

if ( ! function_exists( 'get_account_endpoint_url' ) ) {
    function get_account_endpoint_url( string $endpoint = '' ): string {
        return \SaltHareket\Membership\MembershipManager::getInstance()->getEndpointUrl( $endpoint );
    }
}

if ( ! function_exists( 'get_login_url' ) ) {
    function get_login_url( string $redirect_to = '' ): string {
        return \SaltHareket\Membership\MembershipManager::getInstance()->getLoginUrl( $redirect_to );
    }
}

if ( ! function_exists( 'getLogoutUrl' ) ) {
    function getLogoutUrl( string $redirect_url = '' ): string {
        return \SaltHareket\Membership\MembershipManager::getInstance()->getLogoutUrl( $redirect_url );
    }
}

if ( ! function_exists( 'get_current_endpoint' ) ) {
    function get_current_endpoint( string $base = '' ): string {
        return \SaltHareket\Membership\MembershipManager::getInstance()->getCurrentEndpoint( $base );
    }
}

if ( ! function_exists( 'get_account_menu_items' ) ) {
    function get_account_menu_items(): array {
        return \SaltHareket\Membership\MembershipManager::getInstance()->getMenuItems();
    }
}

if ( ! function_exists( 'get_account_menu_item_classes' ) ) {
    function get_account_menu_item_classes( string $endpoint = '' ): string {
        return \SaltHareket\Membership\MembershipManager::getInstance()->getMenuItemClasses( $endpoint );
    }
}
if ( ! function_exists( 'get_account_menu' ) ) {
    function get_account_menu(): array {
        return \SaltHareket\Membership\MembershipManager::getInstance()->getMenu();
    }
}

if ( ! function_exists( 'login_required' ) ) {
    function login_required( array $req = [] ): void {
        \SaltHareket\Membership\MembershipManager::getInstance()->loginRequired( $req );
    }
}

/**
 * salt_my_account_links()
 * Eski membership-functions.php compat — tema dosyaları bu fonksiyonu kullanıyor.
 */
if ( ! function_exists( 'salt_my_account_links' ) ) {
    function salt_my_account_links(): array {
        $mm = \SaltHareket\Membership\MembershipManager::getInstance();

        // WC aktifken sadece custom (non-WC) endpoint'leri döndür
        // WC kendi endpoint'lerini zaten yönetiyor
        $wc_endpoints = [ 'orders', 'downloads', 'edit-address', 'payment-methods', 'edit-account', 'dashboard', 'customer-logout' ];

        $default_items = $mm->getDefaultMenuItems();
        $links = [];

        foreach ( $default_items as $endpoint => $label ) {
            if ( \SaltHareket\Membership\MembershipManager::isWooActive() && in_array( $endpoint, $wc_endpoints, true ) ) {
                continue; // WC'nin kendi endpoint'leri — atla
            }
            $links[ $endpoint ] = [
                'title'  => is_array( $label ) ? ( $label['title'] ?? $endpoint ) : $label,
                'menu'   => is_array( $label ) ? ( $label['menu'] ?? $label['title'] ?? $endpoint ) : $label,
                'roles'  => is_array( $label ) ? ( $label['roles'] ?? [] ) : [],
                'source' => 'custom',
            ];
        }
        return $links;
    }
}

// ─── ENDPOINT REGISTRATION ───────────────────────────────────────────────────
// WC aktif olsa da custom endpoint'leri register et.
// Hardcode — MembershipManager'a bağımlı değil, init'te güvenle çalışır.

add_action( 'init', function() {
    if ( ! defined( 'ENABLE_MEMBERSHIP' ) || ! ENABLE_MEMBERSHIP ) return;

    // Custom endpoint'ler — WC'nin kendi endpoint'leri değil
    $custom_endpoints = [];

    if ( defined( 'ENABLE_CHAT' ) && ENABLE_CHAT )                   $custom_endpoints[] = 'messages';
    if ( defined( 'ENABLE_NOTIFICATIONS' ) && ENABLE_NOTIFICATIONS ) $custom_endpoints[] = 'notifications';
    if ( defined( 'ENABLE_REACTIONS' ) && ENABLE_REACTIONS )         $custom_endpoints[] = 'favorites';
    if ( ! ( defined( 'DISABLE_COMMENTS' ) && DISABLE_COMMENTS ) )  $custom_endpoints[] = 'reviews';
    if ( defined( 'ENABLE_PASSWORD_RECOVER' ) && ENABLE_PASSWORD_RECOVER ) $custom_endpoints[] = 'security';
    if ( defined( 'ENABLE_MEMBERSHIP_ACTIVATION' ) && ENABLE_MEMBERSHIP_ACTIVATION ) $custom_endpoints[] = 'not-activated';
    if ( defined( 'ENABLE_LOST_PASSWORD' ) && ENABLE_LOST_PASSWORD ) $custom_endpoints[] = 'renew-password';

    // WC aktif değilse profile de ekle
    if ( ! ( defined( 'ENABLE_ECOMMERCE' ) && ENABLE_ECOMMERCE ) ) {
        $custom_endpoints[] = 'profile';
    }

    $rules       = get_option( 'rewrite_rules', [] );
    $needs_flush = false;

    foreach ( $custom_endpoints as $endpoint ) {
        add_rewrite_endpoint( $endpoint, EP_PAGES );
        if ( ! empty( $rules ) && ! isset( $rules[ $endpoint . '(/(.+))?/?' ] ) ) {
            $needs_flush = true;
        }
    }

    if ( $needs_flush ) {
        flush_rewrite_rules( false );
    }
}, 1 ); // priority 1 — WC'den önce

/**
 * response_login_required()
 *
 * Login gerektiren source (dropdown/offcanvas/modal) AJAX method'larında kullanılır.
 * Hata yerine source-login-prompt.twig'i render edip HTML olarak döndürür.
 * _sourceAjax bu response'daki login_required:true flag'ini görünce
 * response_view() bypass eder, HTML'i container'a yazar.
 *
 * Kullanım (method index.php'de):
 *   if ( ! is_user_logged_in() ) {
 *       echo json_encode( response_login_required( $vars['view'] ?? 'dropdown' ) );
 *       wp_die();
 *   }
 *
 * @param  string $view_type  'dropdown' | 'offcanvas' | 'modal'
 * @param  string $message    Opsiyonel özel mesaj (boşsa translate("Login required"))
 * @return array              ajax_query'nin beklediği standart response array
 */
if ( ! function_exists( 'response_login_required' ) ) {
    function response_login_required( string $view_type = 'dropdown', string $message = '' ): array {

        $login_url    = function_exists( 'get_login_url' ) ? get_login_url() : wp_login_url();
        // Register URL: login sayfasında #signup hash'i ile register step'ini açar
        $register_url = rtrim( $login_url, '/' ) . '#signup';

        $enable_registration = defined('ENABLE_REGISTRATION') ? ENABLE_REGISTRATION : false;

        $context = \Timber\Timber::context();
        $context['view_type']          = $view_type;
        $context['login_url']          = $login_url;
        $context['register_url']       = $register_url;
        $context['message']            = $message;
        $context['enable_registration'] = $enable_registration;

        $html = \Timber\Timber::compile( 'partials/source-login-prompt.twig', $context );

        return [
            'error'         => false,       // error:false — response_view() tetiklenmesin
            'login_required'=> true,        // JS intercept için flag
            'html'          => $html ?: '',
            'data'          => [],
        ];
    }
}

if ( ! function_exists( 'membership_enable_registration' ) ) {
    function membership_enable_registration(): bool {
        return (bool) \SaltHareket\Membership\MembershipSettings::getSetting( 'enable_registration' );
    }
}

if ( ! function_exists( 'membership_generate_password' ) ) {
    function membership_generate_password(): bool {
        return false; // Native modda şifre her zaman kullanıcıdan alınır
    }
}

if ( ! function_exists( 'membership_profile_url_prefix' ) ) {
    /**
     * Kullanıcının profil URL prefix'ini döndürür.
     * MembershipSettings profile_url_type'a göre:
     *   standard  → /profile/
     *   role      → /{role_slug}/
     *   custom    → /{custom_prefix}/
     *
     * @param  \WP_User|null $wp_user  Kullanıcı nesnesi (null = mevcut kullanıcı)
     * @return string  Trailing slash'li prefix, örn: "profile/"
     */
    function membership_profile_url_prefix( $wp_user = null ): string {
        $url_type   = \SaltHareket\Membership\MembershipSettings::getSetting( 'profile_url_type',   'standard' );
        $url_prefix = \SaltHareket\Membership\MembershipSettings::getSetting( 'profile_url_prefix', 'profile'  );

        if ( $url_type === 'role' && $wp_user ) {
            $user    = $wp_user instanceof \WP_User ? $wp_user : get_user_by( 'id', (int) $wp_user );
            $roles   = $user ? (array) $user->roles : [];
            $slug    = ! empty( $roles ) ? reset( $roles ) : 'member';
            return rtrim( $slug, '/' ) . '/';
        }

        if ( $url_type === 'custom' && ! empty( $url_prefix ) ) {
            return rtrim( $url_prefix, '/' ) . '/';
        }

        // standard (default)
        return 'profile/';
    }
}

if ( ! function_exists( 'membership_social_networks' ) ) {
    /**
     * Profil sayfası sosyal medya listesi.
     * email, copy, native hariç tüm network'leri döndürür
     * (sharable olanlar + profil-only olanlar).
     *
     * Social Share butonu için sadece sharable olanları almak istersen:
     *   array_filter( SocialShare::networks(), fn($n) => $n['sharable'] ?? false )
     *
     * @return array [ 'key' => ['label' => ..., 'icon' => ..., 'sharable' => ...], ... ]
     */
    function membership_social_networks(): array {
        $exclude = [ 'email', 'copy', 'native' ];
        $all     = \SaltHareket\SocialShare\SocialShare::networks();
        return array_diff_key( $all, array_flip( $exclude ) );
    }
}


if ( ! function_exists( 'sh_has_reviewed' ) ) {
    /**
     * Kullanıcı belirtilen hedefe daha önce review yazmış mı?
     * Twig'den function() ile çağrılabilir.
     *
     * @param int    $user_id    Review yazan kullanıcı
     * @param int    $target_id  Hedef (post ID veya user ID)
     * @param string $type       'post' | 'user'
     * @return bool
     */
    function sh_has_reviewed( int $user_id, int $target_id, string $type = 'post' ): bool {
        if ( ! class_exists( '\SaltHareket\Reviews\Reviews' ) ) return false;
        $reviews = new \SaltHareket\Reviews\Reviews();
        return (bool) $reviews->has_reviewed( $user_id, $target_id, $type );
    }
}

if ( ! function_exists( 'salt_reactions_for' ) ) {
    /**
     * Object type + subtype için aktif reaction config'i döndür.
     * Twig'den function() ile çağrılabilir.
     * ReactionsSettings::getForObject() wrapper'ı.
     *
     * @example {{ function('salt_reactions_for', 'user', author.get_role()) }}
     */
    function salt_reactions_for( string $object_type, string $subtype = '' ): array {
        if ( ! class_exists( '\SaltHareket\Reactions\ReactionsSettings' ) ) return [];
        return \SaltHareket\Reactions\ReactionsSettings::getForObject( $object_type, $subtype );
    }
}
