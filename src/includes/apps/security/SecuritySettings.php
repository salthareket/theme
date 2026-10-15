<?php

namespace SaltHareket\Security;

/**
 * SecuritySettings
 *
 * Security app ayarlarını wp_options'da saklar ve okur.
 *
 * @version 1.0.0
 *
 * @changelog
 *   1.0.0 - 2026-08-26
 *     - Add: Initial — headers, file_protection, wp_hardening, spam_bot, csp, wp_hide bölümleri
 */
class SecuritySettings
{
    const OPTION_KEY = 'sh_security_settings';

    /**
     * Tüm ayarları döndür (defaults ile merge edilmiş).
     */
    public static function get(): array
    {
        $saved = get_option( self::OPTION_KEY, [] );
        return array_replace_recursive( self::defaults(), is_array( $saved ) ? $saved : [] );
    }

    /**
     * Belirli bir bölümün ayarlarını döndür.
     * @param string $section  headers | file_protection | wp_hardening | spam_bot | csp | wp_hide
     */
    public static function getSection( string $section ): array
    {
        $all = self::get();
        return $all[ $section ] ?? [];
    }

    /**
     * Tüm ayarları kaydet.
     */
    public static function save( array $data ): bool
    {
        $clean = self::sanitize( $data );
        return update_option( self::OPTION_KEY, $clean );
    }

    /**
     * Belirli bir bölümü kaydet (diğerlerini dokunmadan bırak).
     */
    public static function saveSection( string $section, array $data ): bool
    {
        $all             = self::get();
        $all[ $section ] = self::sanitizeSection( $section, $data );
        return update_option( self::OPTION_KEY, $all );
    }

    /**
     * WP Hide son uygulama zamanını kaydet.
     */
    public static function saveWphApplied(): void
    {
        $all = self::get();
        $all['wp_hide']['last_applied'] = current_time( 'mysql' );
        update_option( self::OPTION_KEY, $all );
    }

    // ─── Defaults ────────────────────────────────────────────────────────────

    public static function defaults(): array
    {
        return [
            // 1. Security Headers
            'headers' => [
                'x_content_type_options' => true,   // X-Content-Type-Options: nosniff
                'x_frame_options'        => true,   // X-Frame-Options: SAMEORIGIN
                'referrer_policy'        => 'strict-origin-when-cross-origin',
                'permissions_policy'     => 'camera=(), microphone=(), geolocation=(self)',
                'hsts_enabled'           => false,  // HSTS — sadece HTTPS'te aktif et
                'hsts_max_age'           => 31536000,
                'cache_control'          => true,   // Cache-Control header'ı gönder
            ],

            // 2. File & Directory Protection
            'file_protection' => [
                'disable_xmlrpc'         => true,
                'hide_wp_version'        => true,
                'hide_readme'            => true,
                'prevent_dir_listing'    => true,
            ],

            // 3. WordPress Hardening
            'wp_hardening' => [
                'disable_rest_users'     => true,   // /wp-json/wp/v2/users kapat
                'disable_author_enum'    => true,   // /?author=1 engelle
                'remove_generator_meta'  => true,   // <meta name="generator"> kaldır
                'session_expiry_days'    => 365,    // Oturum süresi (gün)
            ],

            // 4. Spam & Bot Protection
            'spam_bot' => [
                'comment_honeypot'       => true,   // Yorum formuna honeypot ekle
                'login_honeypot'         => false,  // Login formuna honeypot ekle
            ],

            // 5. Content Security Policy (CSP)
            'csp' => [
                'enabled'            => false,
                'approved_domains'   => [],  // directive => [domain, ...]
                'report_only'        => false,
            ],

            // 6. WP Hide
            'wp_hide' => [
                'last_applied' => '',
            ],
        ];
    }

    // ─── Sanitize ─────────────────────────────────────────────────────────────

    public static function sanitize( array $data ): array
    {
        $clean = self::defaults();
        foreach ( array_keys( $clean ) as $section ) {
            if ( isset( $data[ $section ] ) && is_array( $data[ $section ] ) ) {
                $clean[ $section ] = self::sanitizeSection( $section, $data[ $section ] );
            }
        }
        return $clean;
    }

    private static function sanitizeSection( string $section, array $data ): array
    {
        $defaults = self::defaults()[ $section ] ?? [];

        switch ( $section ) {
            case 'headers':
                return [
                    'x_content_type_options' => ! empty( $data['x_content_type_options'] ),
                    'x_frame_options'        => ! empty( $data['x_frame_options'] ),
                    'referrer_policy'        => sanitize_text_field( $data['referrer_policy'] ?? $defaults['referrer_policy'] ),
                    'permissions_policy'     => sanitize_textarea_field( $data['permissions_policy'] ?? $defaults['permissions_policy'] ),
                    'hsts_enabled'           => ! empty( $data['hsts_enabled'] ),
                    'hsts_max_age'           => max( 0, (int) ( $data['hsts_max_age'] ?? $defaults['hsts_max_age'] ) ),
                    'cache_control'          => ! empty( $data['cache_control'] ),
                ];

            case 'file_protection':
                return [
                    'disable_xmlrpc'      => ! empty( $data['disable_xmlrpc'] ),
                    'hide_wp_version'     => ! empty( $data['hide_wp_version'] ),
                    'hide_readme'         => ! empty( $data['hide_readme'] ),
                    'prevent_dir_listing' => ! empty( $data['prevent_dir_listing'] ),
                ];

            case 'wp_hardening':
                return [
                    'disable_rest_users'    => ! empty( $data['disable_rest_users'] ),
                    'disable_author_enum'   => ! empty( $data['disable_author_enum'] ),
                    'remove_generator_meta' => ! empty( $data['remove_generator_meta'] ),
                    'session_expiry_days'   => max( 1, (int) ( $data['session_expiry_days'] ?? $defaults['session_expiry_days'] ) ),
                ];

            case 'spam_bot':
                return [
                    'comment_honeypot' => ! empty( $data['comment_honeypot'] ),
                    'login_honeypot'   => ! empty( $data['login_honeypot'] ),
                ];

            case 'csp':
                $approved = [];
                if ( isset( $data['approved_domains'] ) && is_array( $data['approved_domains'] ) ) {
                    foreach ( $data['approved_domains'] as $directive => $domains ) {
                        $directive = sanitize_key( $directive );
                        if ( is_array( $domains ) ) {
                            $approved[ $directive ] = array_values( array_filter( array_map( 'sanitize_text_field', $domains ) ) );
                        }
                    }
                }
                return [
                    'enabled'          => ! empty( $data['enabled'] ),
                    'approved_domains' => $approved,
                    'report_only'      => ! empty( $data['report_only'] ),
                ];

            case 'wp_hide':
                return [
                    'last_applied' => sanitize_text_field( $data['last_applied'] ?? '' ),
                ];

            default:
                return $defaults;
        }
    }
}
