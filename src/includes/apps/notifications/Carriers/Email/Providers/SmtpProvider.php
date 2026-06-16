<?php

namespace SaltHareket\Notifications\Carriers\Email\Providers;

use SaltHareket\Notifications\Carriers\Email\EmailContract;

/**
 * SmtpProvider — PHPMailer ile SMTP gönderimi.
 * WP'nin dahili PHPMailer'ını kullanır (phpmailer_init hook).
 *
 * @version 1.0.0
 */
class SmtpProvider implements EmailContract
{
    private string $host;
    private int    $port;
    private string $encryption;
    private string $username;
    private string $password;
    private string $from_name;
    private string $from_email;

    public function __construct( array $config = [] )
    {
        $this->host       = $config['host']       ?? '';
        $this->port       = (int) ( $config['port'] ?? 587 );
        $this->encryption = $config['encryption']  ?? 'tls';
        $this->username   = $config['username']    ?? '';
        $this->password   = $config['password']    ?? '';
        $this->from_name  = $config['from_name']   ?? get_bloginfo( 'name' );
        $this->from_email = $config['from_email']  ?? get_option( 'admin_email' );
    }

    public static function metadata(): array
    {
        return [
            'label' => 'SMTP',
            'type'  => 'smtp',
            'auth_fields' => [],
            'notes' => [],
        ];
    }

    public function send( string $to, string $subject, string $body, array $headers = [] ): array
    {
        if ( empty( $this->host ) || empty( $this->username ) ) {
            return [ 'error' => true, 'message' => 'SMTP ayarları eksik (host veya username boş).' ];
        }

        $smtp_config = [
            'host'       => $this->host,
            'port'       => $this->port,
            'encryption' => $this->encryption,
            'username'   => $this->username,
            'password'   => $this->password,
            'from_name'  => $this->from_name,
            'from_email' => $this->from_email,
        ];

        // phpmailer_init hook ile WP'nin PHPMailer'ını SMTP moduna al
        $mailer_hook = function( $phpmailer ) use ( $smtp_config ) {
            $phpmailer->isSMTP();
            $phpmailer->Host       = $smtp_config['host'];
            $phpmailer->Port       = $smtp_config['port'];
            $phpmailer->SMTPAuth   = true;
            $phpmailer->Username   = $smtp_config['username'];
            $phpmailer->Password   = $smtp_config['password'];
            $phpmailer->From       = $smtp_config['from_email'];
            $phpmailer->FromName   = $smtp_config['from_name'];

            switch ( $smtp_config['encryption'] ) {
                case 'ssl':
                    $phpmailer->SMTPSecure = 'ssl';
                    break;
                case 'tls':
                    $phpmailer->SMTPSecure = 'tls';
                    break;
                default:
                    $phpmailer->SMTPSecure = '';
                    $phpmailer->SMTPAutoTLS = false;
                    break;
            }
        };

        $from_name_hook  = fn() => $this->from_name;
        $from_email_hook = fn() => $this->from_email;

        add_action( 'phpmailer_init',    $mailer_hook );
        add_filter( 'wp_mail_from',      $from_email_hook );
        add_filter( 'wp_mail_from_name', $from_name_hook );

        // Content-Type header ekle
        $all_headers = array_merge( [ 'Content-Type: text/html; charset=UTF-8' ], $headers );

        $sent = wp_mail( $to, $subject, $body, $all_headers );

        remove_action( 'phpmailer_init',    $mailer_hook );
        remove_filter( 'wp_mail_from',      $from_email_hook );
        remove_filter( 'wp_mail_from_name', $from_name_hook );

        if ( ! $sent ) {
            // WP mail error'ı al
            global $phpmailer;
            $error_msg = isset( $phpmailer ) && method_exists( $phpmailer, 'ErrorInfo' )
                ? $phpmailer->ErrorInfo
                : 'wp_mail returned false';
            return [ 'error' => true, 'message' => $error_msg ];
        }

        return [ 'error' => false, 'message' => 'Sent via SMTP' ];
    }
}
