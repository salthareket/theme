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
