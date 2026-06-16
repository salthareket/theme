<?php

namespace SaltHareket\Notifications\Carriers\Email;

/**
 * EmailManager — Email gönderim facade.
 *
 * mode = 'wp'     → wp_mail() (plugin varsa onun üzerinden)
 * mode = 'custom' → SmtpProvider veya API provider
 *
 * @version 1.0.0
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 *   EmailManager::send('user@example.com', 'Subject', '<p>Body</p>');
 *
 *   // Driver'ı direkt al
 *   EmailManager::driver()->send(...);
 *
 * ──────────────────────────────────────────────────────────
 */
class EmailManager
{
    private static ?EmailContract $instance = null;

    private static array $apiProviders = [
        'sendgrid' => Providers\SendGridProvider::class,
        'mailgun'  => Providers\MailgunProvider::class,
        'brevo'    => Providers\BrevoProvider::class,
        'postmark' => Providers\PostmarkProvider::class,
    ];

    /**
     * Aktif driver'ı döner.
     * mode = 'wp' ise null döner (wp_mail kullanılacak).
     */
    public static function driver(): ?EmailContract
    {
        $settings = EmailSettings::get();

        if ( $settings['mode'] === 'wp' ) {
            return null; // wp_mail() kullanılacak
        }

        if ( self::$instance !== null ) {
            return self::$instance;
        }

        $type = $settings['custom_type'] ?? 'smtp';

        if ( $type === 'smtp' ) {
            self::$instance = new Providers\SmtpProvider( $settings['smtp'] ?? [] );
        } else {
            $provider = $settings['api']['provider'] ?? 'sendgrid';
            $class    = self::$apiProviders[ $provider ] ?? Providers\SendGridProvider::class;
            if ( class_exists( $class ) ) {
                self::$instance = new $class( $settings['api'] ?? [] );
            }
        }

        return self::$instance;
    }

    /**
     * Email gönder — mode'a göre wp_mail veya custom provider.
     *
     * @param  string   $to
     * @param  string   $subject
     * @param  string   $body     HTML
     * @param  string[] $headers
     * @return array{error: bool, message: string}
     */
    public static function send( string $to, string $subject, string $body, array $headers = [] ): array
    {
        $settings = EmailSettings::get();

        // WP mod → wp_mail()
        if ( $settings['mode'] === 'wp' ) {
            $sent = wp_mail( $to, $subject, $body, $headers );
            return $sent
                ? [ 'error' => false, 'message' => 'Sent via wp_mail' ]
                : [ 'error' => true,  'message' => 'wp_mail returned false' ];
        }

        // Custom mod → driver
        try {
            $driver = self::driver();
            if ( ! $driver ) {
                // Fallback
                $sent = wp_mail( $to, $subject, $body, $headers );
                return $sent
                    ? [ 'error' => false, 'message' => 'Sent via wp_mail (fallback)' ]
                    : [ 'error' => true,  'message' => 'wp_mail fallback failed' ];
            }
            return $driver->send( $to, $subject, $body, $headers );
        } catch ( \Throwable $e ) {
            return [ 'error' => true, 'message' => $e->getMessage() ];
        }
    }

    /**
     * Instance cache'ini temizle.
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
