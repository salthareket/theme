<?php

namespace SaltHareket\Notifications\Carriers;

/**
 * SmsManager — SMS provider facade.
 *
 * Config'den aktif provider'ı okur, doğru driver'ı yükler.
 * Çağıran kod provider'dan bağımsız — her zaman aynı API.
 *
 * @version 1.0.0
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 *   // SMS gönder
 *   SmsManager::driver()->send(['+905551234567'], 'Mesajın');
 *
 *   // OTP üret
 *   SmsManager::driver()->generateOtp('+905551234567', $user_id);
 *
 *   // OTP doğrula
 *   SmsManager::driver()->verifyOtp($otp_id, $otp_code);
 *
 *   // Aktif provider'ın capabilities'ini al
 *   SmsManager::capabilities();
 *
 *   // Tüm provider listesi
 *   SmsManager::providers();
 *
 * ──────────────────────────────────────────────────────────
 */
class SmsManager
{
    /** @var SmsContract|null */
    private static ?SmsContract $instance = null;

    /** Kayıtlı provider class'ları */
    private static array $providers = [
        'd7'      => Providers\D7Provider::class,
        'twilio'  => Providers\TwilioProvider::class,
        'vonage'  => Providers\VonageProvider::class,
        'infobip' => Providers\InfobipProvider::class,
        'sinch'   => Providers\SinchProvider::class,
        'netgsm'  => Providers\NetgsmProvider::class,
    ];

    /**
     * Aktif driver'ı döner.
     * OTP desteklemeyen provider seçilmişse generateOtp() çağrısında hata döner.
     *
     * @throws \RuntimeException Provider bulunamazsa
     */
    public static function driver(): SmsContract
    {
        if ( self::$instance !== null ) {
            return self::$instance;
        }

        $settings = SmsSettings::get();
        $provider = $settings['provider'] ?? 'd7';

        if ( ! isset( self::$providers[ $provider ] ) ) {
            throw new \RuntimeException( "[SmsManager] Unknown provider: {$provider}" );
        }

        $class = self::$providers[ $provider ];

        if ( ! class_exists( $class ) ) {
            throw new \RuntimeException( "[SmsManager] Provider class not found: {$class}" );
        }

        self::$instance = new $class( $settings );
        return self::$instance;
    }

    /**
     * Instance cache'ini temizle (settings değişince çağrılır).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Aktif provider'ın capabilities'ini döner.
     */
    public static function capabilities(): array
    {
        $settings = SmsSettings::get();
        $provider = $settings['provider'] ?? 'd7';
        $class    = self::$providers[ $provider ] ?? null;

        if ( ! $class || ! class_exists( $class ) ) {
            return [];
        }

        return $class::capabilities();
    }

    /**
     * Tüm provider'ların listesini capabilities ile döner.
     * Admin UI'da provider seçim kartları için kullanılır.
     *
     * @return array<string, array{label: string, capabilities: array}>
     */
    public static function providers(): array
    {
        $list = [];
        $labels = [
            'd7'      => 'D7 Networks',
            'twilio'  => 'Twilio',
            'vonage'  => 'Vonage (Nexmo)',
            'infobip' => 'Infobip',
            'sinch'   => 'Sinch',
            'netgsm'  => 'Netgsm',
        ];

        foreach ( self::$providers as $key => $class ) {
            if ( class_exists( $class ) ) {
                $list[ $key ] = [
                    'label'        => $labels[ $key ] ?? $key,
                    'capabilities' => $class::capabilities(),
                ];
            }
        }

        return $list;
    }

    /**
     * OTP desteklenip desteklenmediğini kontrol eder.
     * Desteklenmiyorsa hata array'i döner.
     */
    public static function assertOtpSupport(): ?array
    {
        $caps = self::capabilities();
        if ( empty( $caps['otp'] ) ) {
            return [
                'error'   => true,
                'message' => sprintf(
                    __( 'Seçili SMS provider (%s) OTP desteklemiyor. Lütfen D7, Twilio, Vonage, Infobip veya Sinch seçin.', 'salthareket' ),
                    SmsSettings::get()['provider'] ?? 'd7'
                ),
                'data'    => null,
            ];
        }
        return null;
    }
}
