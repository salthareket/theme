<?php

namespace SaltHareket\Membership\Concerns;

use SaltHareket\Membership\MembershipSettings;

/**
 * HandlesDevice
 *
 * Güvenilir cihaz yönetimi — 2FA'yı bilinen cihazlarda bypass eder.
 *
 * Akış:
 *   1. 2FA başarılı → setTrustedDevice() → user_meta + cookie
 *   2. Sonraki login → isTrustedDevice() → true ise 2FA atlanır
 *   3. Cookie süresi dolunca (trusted_device_days) tekrar 2FA istenir
 *
 * Fingerprint:
 *   - User-Agent + IP subnet (/24) + Accept-Language hash
 *   - Tam IP değil çünkü dinamik IP'lerde sorun çıkarır
 *   - Cihaz değişimi = farklı fingerprint = 2FA tekrar istenir
 *
 * @version 1.0.0
 *
 * @changelog
 *   1.0.0 - 2026-09-04
 *     - Add: Initial implementation
 */
trait HandlesDevice
{
    const TRUSTED_COOKIE = 'sh_trusted_device';

    /**
     * Bu cihaz güvenilir mi?
     * trusted_device_days = 0 ise trusted device devre dışı → her zaman false.
     */
    public function isTrustedDevice( int $user_id ): bool
    {
        $days = (int) MembershipSettings::getSetting( 'trusted_device_days', 30 );
        if ( $days <= 0 ) return false;

        $cookie_token = sanitize_text_field( $_COOKIE[ self::TRUSTED_COOKIE ] ?? '' );
        if ( empty( $cookie_token ) ) return false;

        // DB'deki trusted devices listesinden bul
        $devices = $this->getTrustedDevices( $user_id );
        $fp      = $this->getDeviceFingerprint();

        foreach ( $devices as $device ) {
            if ( ( $device['token'] ?? '' ) !== $cookie_token ) continue;
            if ( ( $device['fingerprint'] ?? '' ) !== $fp ) continue;

            // Süre kontrolü
            $created = (int) ( $device['created_at'] ?? 0 );
            if ( $created > 0 && ( time() - $created ) > ( $days * DAY_IN_SECONDS ) ) {
                // Süresi dolmuş — temizle
                $this->revokeTrustedDevice( $user_id, $cookie_token );
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * Bu cihazı güvenilir olarak işaretle.
     * 2FA başarılı login sonrası çağrılır.
     */
    public function setTrustedDevice( int $user_id ): void
    {
        $days  = (int) MembershipSettings::getSetting( 'trusted_device_days', 30 );
        if ( $days <= 0 ) return;

        $token = wp_generate_password( 40, false );
        $fp    = $this->getDeviceFingerprint();

        // Mevcut cihaz listesine ekle
        $devices   = $this->getTrustedDevices( $user_id );
        $devices[] = [
            'token'       => $token,
            'fingerprint' => $fp,
            'created_at'  => time(),
            'user_agent'  => substr( $_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200 ),
        ];

        // Max 10 cihaz tut
        if ( count( $devices ) > 10 ) {
            $devices = array_slice( $devices, -10 );
        }

        update_user_meta( $user_id, 'sh_trusted_devices', $devices );

        // Cookie set et
        $expire = time() + ( $days * DAY_IN_SECONDS );
        setcookie(
            self::TRUSTED_COOKIE,
            $token,
            [
                'expires'  => $expire,
                'path'     => COOKIEPATH,
                'domain'   => COOKIE_DOMAIN,
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Strict',
            ]
        );
    }

    /**
     * Belirli bir cihazı güvenilir listesinden çıkar.
     */
    public function revokeTrustedDevice( int $user_id, string $token ): void
    {
        $devices = $this->getTrustedDevices( $user_id );
        $devices = array_filter( $devices, fn( $d ) => ( $d['token'] ?? '' ) !== $token );
        update_user_meta( $user_id, 'sh_trusted_devices', array_values( $devices ) );
    }

    /**
     * Kullanıcının tüm güvenilir cihazlarını temizle (şifre değişikliği, hesap güvenlik uyarısı gibi durumlarda).
     */
    public function revokeAllTrustedDevices( int $user_id ): void
    {
        delete_user_meta( $user_id, 'sh_trusted_devices' );
    }

    /**
     * Kullanıcının güvenilir cihaz listesini döndür.
     *
     * @return array[]  [ ['token', 'fingerprint', 'created_at', 'user_agent'], ... ]
     */
    public function getTrustedDevices( int $user_id ): array
    {
        $devices = get_user_meta( $user_id, 'sh_trusted_devices', true );
        return is_array( $devices ) ? $devices : [];
    }

    // ─── Fingerprint ─────────────────────────────────────────────────────────

    /**
     * Cihaz parmak izi üret.
     * User-Agent + IP /24 subnet + Accept-Language → MD5 hash.
     * Tam IP kullanılmıyor — dinamik IP'lerde sorun çıkarır.
     */
    private function getDeviceFingerprint(): string
    {
        $ua   = $_SERVER['HTTP_USER_AGENT']    ?? '';
        $lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        $ip   = $_SERVER['REMOTE_ADDR'] ?? '';

        // /24 subnet al — son oktet atılır (192.168.1.x → 192.168.1)
        $ip_parts = explode( '.', $ip );
        $subnet   = count( $ip_parts ) >= 3
            ? $ip_parts[0] . '.' . $ip_parts[1] . '.' . $ip_parts[2]
            : $ip;

        return md5( $ua . '|' . $subnet . '|' . $lang );
    }
}
