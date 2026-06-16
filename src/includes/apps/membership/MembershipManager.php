<?php

namespace SaltHareket\Membership;

use SaltHareket\Membership\Concerns\HandlesAuth;
use SaltHareket\Membership\Concerns\HandlesRegistration;
use SaltHareket\Membership\Concerns\HandlesActivation;
use SaltHareket\Membership\Concerns\HandlesProfile;
use SaltHareket\Membership\Concerns\HandlesPassword;
use SaltHareket\Membership\Concerns\HandlesSocialLogin;
use SaltHareket\Membership\Concerns\HandlesMyAccount;

/**
 * MembershipManager
 *
 * Tüm membership işlemlerinin tek giriş noktası.
 * WooCommerce var mı yok mu, dışarıdan bakan kod bilmez — her şey bu facade üzerinden.
 *
 * SaltBase (custom.php) bu class'a delegate eder — geriye dönük uyumluluk korunur.
 * İleride SaltBase'deki membership metodları silinebilir.
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-05-09
 *     - Add: Initial release
 *     - Add: Singleton pattern
 *     - Add: Tüm Concerns trait'leri entegre edildi
 *     - Add: WooCommerce / non-WooCommerce dual flow abstraction
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * $mm = MembershipManager::getInstance();
 *
 * // Auth
 * $mm->login(['username' => 'a@b.com', 'password' => '123']);
 * $mm->logout();
 *
 * // Register
 * $mm->register(['email' => 'a@b.com', 'password' => '123', 'role' => 'customer']);
 *
 * // Activation
 * $mm->sendActivation($user_id);
 * $mm->activateUser($user_id);   // → do_action('sh_account_activated', $user_id)
 * $mm->approveUser($user_id);    // → do_action('sh_account_approved', $user_id)
 * $mm->rejectUser($user_id);     // → do_action('sh_account_rejected', $user_id)
 *
 * // Profile
 * $mm->updateProfile($vars);
 *
 * // Password
 * $mm->passwordRecover($vars);
 *
 * // My Account
 * $mm->getEndpointUrl('profile');
 * $mm->getMenuItems();
 * $mm->getMenu();
 *
 * ──────────────────────────────────────────────────────────
 *
 * @example
 *   $mm = MembershipManager::getInstance();
 *   $result = $mm->login(['username' => 'test@test.com', 'password' => 'pass']);
 *   if (!$result['error']) wp_redirect($result['redirect']);
 *
 * @example
 *   $mm = MembershipManager::getInstance();
 *   $mm->activateUser(get_current_user_id());
 *
 * @example
 *   // Twig'de endpoint URL al:
 *   // {{ fn('get_account_endpoint_url', 'profile') }}
 *   // — arkada MembershipManager::getInstance()->getEndpointUrl('profile') çağrılır
 *
 * @example
 *   // Menüye item ekle (dışarıdan, herhangi bir dosyada):
 *   add_filter('sh_membership_menu_items', function($items) {
 *       $items['my-orders'] = ['title' => 'Siparişlerim', 'menu' => 'Siparişlerim', 'roles' => []];
 *       return $items;
 *   });
 *
 * @example
 *   // Hesap onaylandı event'ini dinle:
 *   add_action('sh_account_approved', function($user_id) {
 *       // bildirim gönder, email at vs.
 *   });
 */
class MembershipManager
{
    use HandlesAuth;
    use HandlesRegistration;
    use HandlesActivation;
    use HandlesProfile;
    use HandlesPassword;
    use HandlesSocialLogin;
    use HandlesMyAccount;

    // ─── Singleton ───────────────────────────────────────────────────────────

    private static ?self $instance = null;

    /** @var \User|\Timber\User|null */
    public $user = null;

    private function __construct()
    {
        // Mevcut kullanıcıyı yükle
        if ( is_user_logged_in() ) {
            $this->user = class_exists( 'User' )
                ? new \User( wp_get_current_user() )
                : \Timber\Timber::get_user( wp_get_current_user() );
        }
    }

    public static function getInstance(): self
    {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Standart AJAX response array'i döndür.
     */
    public function response(): array
    {
        return [
            'error'       => false,
            'message'     => '',
            'description' => '',
            'data'        => '',
            'resubmit'    => false,
            'redirect'    => '',
            'refresh'     => false,
            'html'        => '',
            'template'    => '',
        ];
    }

    /**
     * Kullanıcı nesnesini güncelle (kayıt/login sonrası).
     */
    public function refreshUser( int $user_id = 0 ): void
    {
        if ( $user_id > 0 ) {
            $this->user = class_exists( 'User' )
                ? new \User( $user_id )
                : \Timber\Timber::get_user( $user_id );
        }
    }

    /**
     * WooCommerce aktif mi?
     */
    public static function isWooActive(): bool
    {
        return defined( 'ENABLE_ECOMMERCE' ) && ENABLE_ECOMMERCE;
    }

    /**
     * Membership aktif mi?
     */
    public static function isActive(): bool
    {
        return defined( 'ENABLE_MEMBERSHIP' ) && ENABLE_MEMBERSHIP;
    }

    /**
     * Aktivasyon zorunlu mu?
     */
    public static function isActivationRequired(): bool
    {
        return defined( 'ENABLE_MEMBERSHIP_ACTIVATION' ) && ENABLE_MEMBERSHIP_ACTIVATION;
    }
}
