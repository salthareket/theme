<?php

namespace SaltHareket\Notifications\Carriers\Email\Providers;

use SaltHareket\Notifications\Carriers\Email\EmailContract;

/**
 * SendGridProvider — SendGrid Web API v3
 * Auth: API Key (Bearer)
 * Free: 100/gün süresiz
 *
 * @version 1.0.0
 */
class SendGridProvider implements EmailContract
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
            'label' => 'SendGrid',
            'type'  => 'api',
            'auth_fields' => [
                'api_key'    => [ 'label' => 'API Key',    'type' => 'password', 'placeholder' => 'SG.xxxxxxxx' ],
                'from_name'  => [ 'label' => 'From Name',  'type' => 'text',     'placeholder' => 'Site Adı' ],
                'from_email' => [ 'label' => 'From Email', 'type' => 'email',    'placeholder' => 'noreply@domain.com' ],
            ],
            'notes' => [ '100 email/gün ücretsiz (süresiz).' ],
        ];
    }

    public function send( string $to, string $subject, string $body, array $headers = [] ): array
    {
        if ( empty( $this->api_key ) ) {
            return [ 'error' => true, 'message' => 'SendGrid API Key eksik.' ];
        }

        $payload = [
            'personalizations' => [ [ 'to' => [ [ 'email' => $to ] ] ] ],
            'from'             => [ 'email' => $this->from_email, 'name' => $this->from_name ],
            'subject'          => $subject,
            'content'          => [ [ 'type' => 'text/html', 'value' => $body ] ],
        ];

        $response = wp_remote_post( 'https://api.sendgrid.com/v3/mail/send', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( $payload ),
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'error' => true, 'message' => $response->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $response );

        // SendGrid 202 = accepted
        if ( $code === 202 || $code === 200 ) {
            return [ 'error' => false, 'message' => 'Sent via SendGrid' ];
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        $msg  = $data['errors'][0]['message'] ?? "HTTP {$code}";
        return [ 'error' => true, 'message' => (string) $msg ];
    }
}
