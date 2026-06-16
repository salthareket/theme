<?php

namespace SaltHareket\Notifications\Carriers\Email\Providers;

use SaltHareket\Notifications\Carriers\Email\EmailContract;

/**
 * BrevoProvider — Brevo (Sendinblue) Transactional Email API v3
 * Auth: API Key (Header: api-key)
 * Free: 300/gün süresiz — en cömert ücretsiz plan
 *
 * @version 1.0.0
 */
class BrevoProvider implements EmailContract
{
    private string $api_key;
    private string $from_name;
    private string $from_email;

    public function __construct( array $config = [] )
    {
        $this->api_key    = $config['api_key']    ?? '';
        $this->from_name  = $config['from_name']  ?? get_bloginfo( 'name' );
        $this->from_email = $config['from_email'] ?? get_option( 'admin_email' );
    }

    public static function metadata(): array
    {
        return [
            'label' => 'Brevo (Sendinblue)',
            'type'  => 'api',
            'auth_fields' => [
                'api_key'    => [ 'label' => 'API Key',    'type' => 'password', 'placeholder' => 'xkeysib-xxxxxxxx' ],
                'from_name'  => [ 'label' => 'From Name',  'type' => 'text',     'placeholder' => 'Site Adı' ],
                'from_email' => [ 'label' => 'From Email', 'type' => 'email',    'placeholder' => 'noreply@domain.com' ],
            ],
            'notes' => [ '300 email/gün ücretsiz (süresiz) — en cömert ücretsiz plan.' ],
        ];
    }

    public function send( string $to, string $subject, string $body, array $headers = [] ): array
    {
        if ( empty( $this->api_key ) ) {
            return [ 'error' => true, 'message' => 'Brevo API Key eksik.' ];
        }

        $payload = [
            'sender'     => [ 'name' => $this->from_name, 'email' => $this->from_email ],
            'to'         => [ [ 'email' => $to ] ],
            'subject'    => $subject,
            'htmlContent' => $body,
        ];

        $response = wp_remote_post( 'https://api.brevo.com/v3/smtp/email', [
            'headers' => [
                'api-key'      => $this->api_key,
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body'    => wp_json_encode( $payload ),
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'error' => true, 'message' => $response->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code === 201 ) {
            return [ 'error' => false, 'message' => 'Sent via Brevo' ];
        }

        $msg = $data['message'] ?? "HTTP {$code}";
        return [ 'error' => true, 'message' => (string) $msg ];
    }
}
