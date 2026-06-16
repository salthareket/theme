<?php

namespace SaltHareket\Notifications\Carriers;

/**
 * SmsSettings — SMS provider ayarlarını yönetir.
 * wp_options'da saklar, ACF bağımlılığı yok.
 *
 * @version 1.0.0
 */
class SmsSettings
{
    private const OPTION_KEY = 'sh_sms_settings';

    /** OTP default değerleri — settings'te override edilebilir */
    public const OTP_DEFAULTS = [
        'otp_expiry'    => 300,  // saniye (5 dakika)
        'otp_length'    => 6,    // kod uzunluğu
        'max_resend'    => 5,    // max yeniden gönderim
    ];

    private static ?array $cache = null;

    /**
     * Tüm ayarları döner.
     */
    public static function get(): array
    {
        if ( self::$cache !== null ) {
            return self::$cache;
        }

        $defaults = [
            'enabled'   => false,
            'provider'  => 'd7',
            // OTP global ayarları
            'otp_expiry'  => self::OTP_DEFAULTS['otp_expiry'],
            'otp_length'  => self::OTP_DEFAULTS['otp_length'],
            'max_resend'  => self::OTP_DEFAULTS['max_resend'],
            // Her provider kendi key'lerini kullanır
            // Ortak alanlar:
            'd7'      => [ 'token' => '', 'sender_id' => '' ],
            'twilio'  => [ 'account_sid' => '', 'auth_token' => '', 'sender_id' => '' ],
            'vonage'  => [ 'api_key' => '', 'api_secret' => '', 'sender_id' => '' ],
            'infobip' => [ 'api_key' => '', 'base_url' => '', 'sender_id' => '' ],
            'sinch'   => [ 'app_key' => '', 'app_secret' => '', 'sender_id' => '' ],
            'netgsm'  => [ 'username' => '', 'password' => '', 'header' => '' ],
        ];

        $saved = get_option( self::OPTION_KEY, [] );

        // Deep merge — provider sub-array'leri de merge et
        $merged = $defaults;
        foreach ( $saved as $key => $val ) {
            if ( is_array( $val ) && isset( $merged[ $key ] ) && is_array( $merged[ $key ] ) ) {
                $merged[ $key ] = array_merge( $merged[ $key ], $val );
            } else {
                $merged[ $key ] = $val;
            }
        }

        self::$cache = $merged;
        return self::$cache;
    }

    /**
     * Global OTP ayarlarını döner.
     * Provider constructor'larında kullanılır.
     *
     * @return array{otp_expiry: int, otp_length: int, max_resend: int}
     */
    public static function getOtpConfig(): array
    {
        $s = self::get();
        return [
            'otp_expiry' => (int) ( $s['otp_expiry'] ?? self::OTP_DEFAULTS['otp_expiry'] ),
            'otp_length' => (int) ( $s['otp_length'] ?? self::OTP_DEFAULTS['otp_length'] ),
            'max_resend' => (int) ( $s['max_resend'] ?? self::OTP_DEFAULTS['max_resend'] ),
        ];
    }

    /**
     * Aktif provider'ın credential'larını döner.
     */
    public static function getProviderConfig( string $provider = '' ): array
    {
        $settings = self::get();
        $provider = $provider ?: ( $settings['provider'] ?? 'd7' );
        return $settings[ $provider ] ?? [];
    }

    /**
     * Ayarları kaydet.
     */
    public static function save( array $data ): void
    {
        $current = self::get();

        $settings = [
            'enabled'  => ! empty( $data['enabled'] ),
            'provider' => sanitize_key( $data['provider'] ?? 'd7' ),
            // OTP global ayarları
            'otp_expiry' => max( 60, min( 3600, (int) ( $data['otp_expiry'] ?? self::OTP_DEFAULTS['otp_expiry'] ) ) ),
            'otp_length' => max( 4,  min( 10,   (int) ( $data['otp_length'] ?? self::OTP_DEFAULTS['otp_length'] ) ) ),
            'max_resend' => max( 1,  min( 20,   (int) ( $data['max_resend'] ?? self::OTP_DEFAULTS['max_resend'] ) ) ),
        ];

        // Her provider'ın credential'larını kaydet
        $providers = [ 'd7', 'twilio', 'vonage', 'infobip', 'sinch', 'netgsm' ];
        foreach ( $providers as $p ) {
            if ( isset( $data[ $p ] ) && is_array( $data[ $p ] ) ) {
                $settings[ $p ] = array_map( 'sanitize_text_field', $data[ $p ] );
            } else {
                $settings[ $p ] = $current[ $p ] ?? [];
            }
        }

        update_option( self::OPTION_KEY, $settings, false );
        self::$cache = null;

        // SmsManager instance'ını sıfırla — yeni config yüklensin
        SmsManager::reset();
    }

    /**
     * Cache'i temizle.
     */
    public static function clearCache(): void
    {
        self::$cache = null;
    }
}
