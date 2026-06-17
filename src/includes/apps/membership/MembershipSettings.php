<?php

namespace SaltHareket\Membership;

/**
 * MembershipSettings
 *
 * Membership ayarlarını yönetir.
 * ACF options'tan okur (geriye uyumluluk) + kendi wp_options key'lerini kullanır.
 * ACF field'ları silinince kendi key'lerinden okumaya devam eder.
 *
 * variables.php'de define() çağrılarından ÖNCE yüklenir:
 *   require_once SH_INCLUDES_PATH . 'apps/membership/MembershipSettings.php';
 *   define("ENABLE_MEMBERSHIP", MembershipSettings::getSetting('enable_membership'));
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-06-16
 *     - Add: Initial release — ACF options'tan taşındı
 *     - Add: Tüm membership ayarları wp_options'a alındı
 *     - Add: ACF fallback (geçiş dönemi — ACF field'ları silinene kadar)
 *     - Add: save() — partial update desteği
 *     - Add: syncToConstants() — variables.php'ye gerek kalmadan runtime define
 */
class MembershipSettings
{
    private const OPTION_KEY = 'sh_membership_settings';

    private static ?array $cache = null;

    // ─── Defaults ────────────────────────────────────────────────────────────

    public static function defaults(): array
    {
        return [
            // Core
            'enable_membership'                  => false,
            // Activation
            'enable_membership_activation'       => false,
            'membership_activation_type'         => 'email',   // 'email' | 'sms'
            'enable_activation_email_autologin'  => false,
            // Registration & Login
            'enable_registration'                => true,
            'enable_remember_login'              => false,
            'enable_lost_password'               => false,
            'enable_password_recover'            => false,
            'password_recover_type'              => 'link',    // 'link' | 'generated'
            // Social & Integrations
            'enable_social_login'                => false,
            'enable_postcode_validation'         => false,
            // Chat (YoBro) — membership'e bağlı ama ayrı plugin
            'enable_chat'                        => false,
        ];
    }

    // ─── Read ─────────────────────────────────────────────────────────────────

    /**
     * Tüm ayarları döndür.
     */
    public static function get(): array
    {
        if ( self::$cache !== null ) return self::$cache;

        $saved = get_option( self::OPTION_KEY, [] );
        $saved = is_array( $saved ) ? $saved : [];

        // ACF fallback — geçiş döneminde ACF field'ları hâlâ varsa oradan oku
        $acf = self::readAcfFallback();

        // Öncelik: saved > acf > defaults
        self::$cache = array_merge( self::defaults(), $acf, $saved );
        return self::$cache;
    }

    /**
     * Tek bir ayarı al.
     */
    public static function getSetting( string $key, $default = null )
    {
        return self::get()[ $key ] ?? $default ?? self::defaults()[ $key ] ?? null;
    }

    // ─── Write ────────────────────────────────────────────────────────────────

    /**
     * Ayarları kaydet — partial update destekler.
     */
    public static function save( array $data ): void
    {
        $current = self::get();

        $settings = [
            'enable_membership'                 => isset( $data['enable_membership'] )
                ? (bool) $data['enable_membership']
                : $current['enable_membership'],
            'enable_membership_activation'      => isset( $data['enable_membership_activation'] )
                ? (bool) $data['enable_membership_activation']
                : $current['enable_membership_activation'],
            'membership_activation_type'        => isset( $data['membership_activation_type'] )
                ? ( in_array( $data['membership_activation_type'], [ 'email', 'sms' ], true ) ? $data['membership_activation_type'] : 'email' )
                : $current['membership_activation_type'],
            'enable_activation_email_autologin' => isset( $data['enable_activation_email_autologin'] )
                ? (bool) $data['enable_activation_email_autologin']
                : $current['enable_activation_email_autologin'],
            'enable_registration'               => isset( $data['enable_registration'] )
                ? (bool) $data['enable_registration']
                : $current['enable_registration'],
            'enable_remember_login'             => isset( $data['enable_remember_login'] )
                ? (bool) $data['enable_remember_login']
                : $current['enable_remember_login'],
            'enable_lost_password'              => isset( $data['enable_lost_password'] )
                ? (bool) $data['enable_lost_password']
                : $current['enable_lost_password'],
            'enable_password_recover'           => isset( $data['enable_password_recover'] )
                ? (bool) $data['enable_password_recover']
                : $current['enable_password_recover'],
            'password_recover_type'             => isset( $data['password_recover_type'] )
                ? ( in_array( $data['password_recover_type'], [ 'link', 'generated' ], true ) ? $data['password_recover_type'] : 'link' )
                : $current['password_recover_type'],
            'enable_social_login'               => isset( $data['enable_social_login'] )
                ? (bool) $data['enable_social_login']
                : $current['enable_social_login'],
            'enable_postcode_validation'        => isset( $data['enable_postcode_validation'] )
                ? (bool) $data['enable_postcode_validation']
                : $current['enable_postcode_validation'],
            'enable_chat'                       => isset( $data['enable_chat'] )
                ? (bool) $data['enable_chat']
                : $current['enable_chat'],
        ];

        // Kural: activation kapalıysa alt ayarları sıfırla
        if ( ! $settings['enable_membership_activation'] ) {
            $settings['enable_activation_email_autologin'] = false;
        }

        // Kural: membership kapalıysa bağımlı özellikler pasif
        if ( ! $settings['enable_membership'] ) {
            $settings['enable_membership_activation']      = false;
            $settings['enable_activation_email_autologin'] = false;
            $settings['enable_social_login']               = false;
            $settings['enable_chat']                       = false;
        }

        update_option( self::OPTION_KEY, $settings, false );
        self::$cache = null;

        // ── Hook: dışarıdan dinlenebilir ──────────────────────────────────────
        do_action( 'sh/membership/saved', $settings, $current );

        // Değişen ayarları tek tek fire et
        foreach ( $settings as $key => $new_val ) {
            $old_val = $current[ $key ] ?? null;
            if ( $old_val !== $new_val ) {
                do_action( 'sh/membership/setting_changed', $key, $new_val, $old_val, $settings );
            }
        }
    }

    /**
     * Cache'i temizle.
     */
    public static function clearCache(): void
    {
        self::$cache = null;
    }

    // ─── ACF Fallback ─────────────────────────────────────────────────────────

    /**
     * ACF options'tan oku — geçiş dönemi.
     * ACF aktif değilse veya field yoksa boş döner.
     */
    private static function readAcfFallback(): array
    {
        // ACF init olmadıysa okuma
        if ( ! function_exists( 'get_field' ) || ! did_action( 'acf/init' ) ) {
            // WP option fallback (ACF'nin kendi option storage'ı)
            return array_filter( [
                'enable_membership'                 => get_option( 'options_enable_membership' ) ?: null,
                'enable_membership_activation'      => get_option( 'options_enable_membership_activation' ) ?: null,
                'membership_activation_type'        => get_option( 'options_membership_activation_settings' ) ?: null,
                'enable_activation_email_autologin' => get_option( 'options_enable_activation_email_autologin' ) ?: null,
                'enable_registration'               => get_option( 'options_enable_registration' ) ?: null,
                'enable_remember_login'             => get_option( 'options_enable_remember_login' ) ?: null,
                'enable_lost_password'              => get_option( 'options_enable_lost_password' ) ?: null,
                'enable_password_recover'           => get_option( 'options_enable_password_recover' ) ?: null,
                'password_recover_type'             => ( function() {
                    $v = get_option( 'options_password_recover_settings' );
                    if ( ! $v ) return null;
                    // ACF'te 'renew' veya 'reset' — yeni sistemde 'link' | 'generated'
                    return $v === 'reset' ? 'generated' : 'link';
                } )(),
                'enable_social_login'               => get_option( 'options_enable_social_login' ) ?: null,
                'enable_postcode_validation'        => get_option( 'options_enable_postcode_validation' ) ?: null,
                'enable_chat'                       => get_option( 'options_enable_chat' ) ?: null,
            ], fn( $v ) => $v !== null );
        }

        return array_filter( [
            'enable_membership'                 => get_field( 'options_enable_membership', 'option' ) ?: null,
            'enable_membership_activation'      => get_field( 'options_enable_membership_activation', 'option' ) ?: null,
            'membership_activation_type'        => get_field( 'options_membership_activation_settings', 'option' ) ?: null,
            'enable_activation_email_autologin' => get_field( 'options_enable_activation_email_autologin', 'option' ) ?: null,
            'enable_registration'               => get_field( 'options_enable_registration', 'option' ) ?: null,
            'enable_remember_login'             => get_field( 'options_enable_remember_login', 'option' ) ?: null,
            'enable_lost_password'              => get_field( 'options_enable_lost_password', 'option' ) ?: null,
            'enable_password_recover'           => get_field( 'options_enable_password_recover', 'option' ) ?: null,
            'password_recover_type'             => ( function() {
                $v = get_field( 'options_password_recover_settings', 'option' );
                if ( ! $v ) return null;
                return $v === 'reset' ? 'generated' : 'link';
            } )(),
            'enable_social_login'               => get_field( 'options_enable_social_login', 'option' ) ?: null,
            'enable_postcode_validation'        => get_field( 'options_enable_postcode_validation', 'option' ) ?: null,
            'enable_chat'                       => get_field( 'options_enable_chat', 'option' ) ?: null,
        ], fn( $v ) => $v !== null );
    }
}
