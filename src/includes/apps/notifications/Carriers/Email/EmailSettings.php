<?php

namespace SaltHareket\Notifications\Carriers\Email;

/**
 * EmailSettings — Email gönderim ayarlarını yönetir.
 * wp_options'da saklar, ACF bağımlılığı yok.
 *
 * @version 1.0.0
 */
class EmailSettings
{
    private const OPTION_KEY = 'sh_email_settings';

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
            // 'wp' = wp_mail() kullan (plugin varsa onun üzerinden)
            // 'custom' = kendi ayarlarımızı kullan
            'mode'         => 'wp',

            // Custom mod seçilince: 'smtp' veya 'api'
            'custom_type'  => 'smtp',

            // SMTP ayarları
            'smtp' => [
                'preset'     => 'custom',  // gmail, outlook, yahoo, yandex, godaddy, custom...
                'host'       => '',
                'port'       => 587,
                'encryption' => 'tls',     // tls, ssl, none
                'username'   => '',
                'password'   => '',
                'from_name'  => '',
                'from_email' => '',
            ],

            // API ayarları
            'api' => [
                'provider'   => 'sendgrid', // sendgrid, mailgun, brevo, postmark
                'api_key'    => '',
                'domain'     => '',         // Mailgun için
                'region'     => 'us',       // Mailgun için: us, eu
                'from_name'  => '',
                'from_email' => '',
            ],
        ];

        $saved  = get_option( self::OPTION_KEY, [] );
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
     * Ayarları kaydet.
     */
    public static function save( array $data ): void
    {
        $settings = [
            'mode'        => in_array( $data['mode'] ?? 'wp', [ 'wp', 'custom' ], true ) ? $data['mode'] : 'wp',
            'custom_type' => in_array( $data['custom_type'] ?? 'smtp', [ 'smtp', 'api' ], true ) ? $data['custom_type'] : 'smtp',
        ];

        // SMTP
        if ( isset( $data['smtp'] ) && is_array( $data['smtp'] ) ) {
            $smtp = $data['smtp'];
            $settings['smtp'] = [
                'preset'     => sanitize_key( $smtp['preset']     ?? 'custom' ),
                'host'       => sanitize_text_field( $smtp['host']       ?? '' ),
                'port'       => max( 1, min( 65535, (int) ( $smtp['port'] ?? 587 ) ) ),
                'encryption' => in_array( $smtp['encryption'] ?? 'tls', [ 'tls', 'ssl', 'none' ], true ) ? $smtp['encryption'] : 'tls',
                'username'   => sanitize_text_field( $smtp['username']   ?? '' ),
                'password'   => $smtp['password'] ?? '',  // şifre sanitize edilmez
                'from_name'  => sanitize_text_field( $smtp['from_name']  ?? '' ),
                'from_email' => sanitize_email( $smtp['from_email'] ?? '' ),
            ];
        }

        // API
        if ( isset( $data['api'] ) && is_array( $data['api'] ) ) {
            $api = $data['api'];
            $settings['api'] = [
                'provider'   => sanitize_key( $api['provider']   ?? 'sendgrid' ),
                'api_key'    => sanitize_text_field( $api['api_key']    ?? '' ),
                'domain'     => sanitize_text_field( $api['domain']     ?? '' ),
                'region'     => in_array( $api['region'] ?? 'us', [ 'us', 'eu' ], true ) ? $api['region'] : 'us',
                'from_name'  => sanitize_text_field( $api['from_name']  ?? '' ),
                'from_email' => sanitize_email( $api['from_email'] ?? '' ),
            ];
        }

        update_option( self::OPTION_KEY, $settings, false );
        self::$cache = null;
        EmailManager::reset();
    }

    /**
     * Cache'i temizle.
     */
    public static function clearCache(): void
    {
        self::$cache = null;
    }

    /**
     * SMTP preset'lerini döner.
     * Seçilince host/port/encryption otomatik dolar.
     */
    public static function smtpPresets(): array
    {
        return [
            'custom'      => [ 'label' => 'Custom / Hosting',    'host' => '',                          'port' => 587, 'encryption' => 'tls' ],
            'gmail'       => [ 'label' => 'Gmail',               'host' => 'smtp.gmail.com',            'port' => 587, 'encryption' => 'tls',
                               'note' => 'Gmail App Password gerekli. Google hesabında 2FA açık olmalı → Google Account → Security → App Passwords.' ],
            'outlook'     => [ 'label' => 'Outlook / Hotmail',   'host' => 'smtp-mail.outlook.com',     'port' => 587, 'encryption' => 'tls' ],
            'yahoo'       => [ 'label' => 'Yahoo Mail',          'host' => 'smtp.mail.yahoo.com',       'port' => 587, 'encryption' => 'tls',
                               'note' => 'Yahoo App Password gerekli.' ],
            'yandex'      => [ 'label' => 'Yandex Mail',         'host' => 'smtp.yandex.com',           'port' => 587, 'encryption' => 'tls' ],
            'godaddy'     => [ 'label' => 'GoDaddy',             'host' => 'smtpout.secureserver.net',  'port' => 465, 'encryption' => 'ssl' ],
            'turhost'     => [ 'label' => 'Turhost',             'host' => 'mail.turhost.com',          'port' => 587, 'encryption' => 'tls' ],
            'natro'       => [ 'label' => 'Natro',               'host' => 'mail.natro.com',            'port' => 587, 'encryption' => 'tls' ],
            'isimtescil'  => [ 'label' => 'İsimtescil',          'host' => 'mail.isimtescil.net',       'port' => 587, 'encryption' => 'tls' ],
            'office365'   => [ 'label' => 'Office 365',          'host' => 'smtp.office365.com',        'port' => 587, 'encryption' => 'tls' ],
            'zoho'        => [ 'label' => 'Zoho Mail',           'host' => 'smtp.zoho.com',             'port' => 587, 'encryption' => 'tls' ],
        ];
    }

    /**
     * API provider'larını döner.
     */
    public static function apiProviders(): array
    {
        return [
            'sendgrid' => [
                'label'  => 'SendGrid',
                'free'   => '100/gün süresiz',
                'fields' => [
                    'api_key'    => [ 'label' => 'API Key', 'type' => 'password', 'placeholder' => 'SG.xxxxxxxx' ],
                    'from_name'  => [ 'label' => 'From Name', 'type' => 'text', 'placeholder' => 'Site Adı' ],
                    'from_email' => [ 'label' => 'From Email', 'type' => 'email', 'placeholder' => 'noreply@domain.com' ],
                ],
            ],
            'mailgun'  => [
                'label'  => 'Mailgun',
                'free'   => '1.000/ay (3 ay)',
                'fields' => [
                    'api_key'    => [ 'label' => 'API Key',    'type' => 'password', 'placeholder' => 'key-xxxxxxxx' ],
                    'domain'     => [ 'label' => 'Domain',     'type' => 'text',     'placeholder' => 'mg.yourdomain.com' ],
                    'region'     => [ 'label' => 'Region',     'type' => 'select',   'options' => [ 'us' => 'US', 'eu' => 'EU (Europe)' ] ],
                    'from_name'  => [ 'label' => 'From Name',  'type' => 'text',     'placeholder' => 'Site Adı' ],
                    'from_email' => [ 'label' => 'From Email', 'type' => 'email',    'placeholder' => 'noreply@mg.yourdomain.com' ],
                ],
            ],
            'brevo'    => [
                'label'  => 'Brevo (Sendinblue)',
                'free'   => '300/gün süresiz',
                'fields' => [
                    'api_key'    => [ 'label' => 'API Key',    'type' => 'password', 'placeholder' => 'xkeysib-xxxxxxxx' ],
                    'from_name'  => [ 'label' => 'From Name',  'type' => 'text',     'placeholder' => 'Site Adı' ],
                    'from_email' => [ 'label' => 'From Email', 'type' => 'email',    'placeholder' => 'noreply@domain.com' ],
                ],
            ],
            'postmark' => [
                'label'  => 'Postmark',
                'free'   => '100/ay süresiz',
                'fields' => [
                    'api_key'    => [ 'label' => 'Server API Token', 'type' => 'password', 'placeholder' => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx' ],
                    'from_name'  => [ 'label' => 'From Name',        'type' => 'text',     'placeholder' => 'Site Adı' ],
                    'from_email' => [ 'label' => 'From Email',       'type' => 'email',    'placeholder' => 'noreply@domain.com' ],
                ],
            ],
        ];
    }

    /**
     * Aktif WP SMTP plugin'ini tespit et.
     *
     * @return array{found: bool, name: string, settings_url: string}
     */
    public static function detectWpSmtpPlugin(): array
    {
        $plugins = [
            [
                'check'        => fn() => defined( 'FLUENTMAIL' ) || class_exists( 'FluentSmtp\App' ),
                'name'         => 'FluentSMTP',
                'settings_url' => admin_url( 'options-general.php?page=fluent-mail' ),
            ],
            [
                'check'        => fn() => class_exists( 'PostmanOptions' ) || defined( 'POSTMAN_PLUGIN_VERSION' ),
                'name'         => 'Post SMTP',
                'settings_url' => admin_url( 'admin.php?page=postman' ),
            ],
            [
                'check'        => fn() => class_exists( 'WPMailSMTP\Core' ) || defined( 'WPMS_PLUGIN_VER' ),
                'name'         => 'WP Mail SMTP',
                'settings_url' => admin_url( 'admin.php?page=wp-mail-smtp' ),
            ],
            [
                'check'        => fn() => class_exists( 'EasyWPSMTP\Core\Core' ) || defined( 'EASY_WP_SMTP_VERSION' ),
                'name'         => 'Easy WP SMTP',
                'settings_url' => admin_url( 'options-general.php?page=easy-wp-smtp' ),
            ],
            [
                'check'        => fn() => class_exists( 'MailerLite\Includes\Mailer' ) || defined( 'MAILERLITE_WP_PLUGIN_VERSION' ),
                'name'         => 'MailerLite',
                'settings_url' => admin_url( 'admin.php?page=mailerlite-settings' ),
            ],
        ];

        foreach ( $plugins as $plugin ) {
            if ( ( $plugin['check'] )() ) {
                return [
                    'found'        => true,
                    'name'         => $plugin['name'],
                    'settings_url' => $plugin['settings_url'],
                ];
            }
        }

        return [ 'found' => false, 'name' => '', 'settings_url' => '' ];
    }
}
