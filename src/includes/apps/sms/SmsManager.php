<?php

namespace SaltHareket\Sms;

use SaltHareket\Sms\Contracts\SmsContract;

/**
 * SmsManager — SMS provider facade.
 *
 * @version 1.1.0
 * @changelog
 *   1.1.0 - 2026-06-18
 *     - Move: notifications/Carriers → sms/
 *     - Change: Namespace SaltHareket\Notifications\Carriers → SaltHareket\Sms
 *   1.0.0 - 2026-05-04 — Initial release
 *
 * @package SaltHareket\Sms
 *
 * How to use:
 *   SmsManager::driver()->send(['+905551234567'], 'Mesaj');
 *   SmsManager::driver()->generateOtp('+905551234567', $user_id);
 *   SmsManager::driver()->verifyOtp($otp_id, $otp_code);
 *   SmsManager::capabilities();
 *   SmsManager::providers();
 */
class SmsManager
{
    private static ?SmsContract $instance = null;

    private static array $providers = [
        'd7'      => Providers\D7Provider::class,
        'twilio'  => Providers\TwilioProvider::class,
        'vonage'  => Providers\VonageProvider::class,
        'infobip' => Providers\InfobipProvider::class,
        'sinch'   => Providers\SinchProvider::class,
        'netgsm'  => Providers\NetgsmProvider::class,
    ];

    public static function driver(): SmsContract
    {
        if ( self::$instance !== null ) return self::$instance;

        $settings = SmsSettings::get();
        $provider = $settings['provider'] ?? 'd7';

        if ( ! isset( self::$providers[$provider] ) ) {
            throw new \RuntimeException( "[SmsManager] Unknown provider: {$provider}" );
        }

        $class = self::$providers[$provider];
        if ( ! class_exists( $class ) ) {
            throw new \RuntimeException( "[SmsManager] Provider class not found: {$class}" );
        }

        self::$instance = new $class( $settings );
        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    public static function capabilities(): array
    {
        $settings = SmsSettings::get();
        $provider = $settings['provider'] ?? 'd7';
        $class    = self::$providers[$provider] ?? null;

        if ( ! $class || ! class_exists( $class ) ) return [];

        return $class::capabilities();
    }

    public static function providers(): array
    {
        $list   = [];
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
                $list[$key] = [
                    'label'        => $labels[$key] ?? $key,
                    'capabilities' => $class::capabilities(),
                ];
            }
        }

        return $list;
    }

    public static function assertOtpSupport(): ?array
    {
        $caps = self::capabilities();
        if ( empty( $caps['otp'] ) ) {
            return [
                'error'   => true,
                'message' => sprintf(
                    __( 'Seçili SMS provider (%s) OTP desteklemiyor.', 'salthareket' ),
                    SmsSettings::get()['provider'] ?? 'd7'
                ),
                'data' => null,
            ];
        }
        return null;
    }
}
