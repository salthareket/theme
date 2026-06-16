<?php

namespace SaltHareket\Notifications\Carriers\Email\Providers;

use SaltHareket\Notifications\Carriers\Email\EmailContract;

/**
 * PostmarkProvider — Postmark Email API
 * Auth: Server API Token (Header: X-Postmark-Server-Token)
 * Free: 100/ay süresiz
 *
 * @version 1.0.0
 */
class PostmarkProvider implements EmailContract
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
            'label' => 'Postmark',
            'type'  => 'api',
            'auth_fields' => [
                'api_key'    => [ 'label' => 'Server API Token', 'type' => 'password', 'placeholder' => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx' ],
                'from_name'  => [ 'label' => 'From Name',        'type' => 'text',     'placeholder' => 'Site Adı' ],
                'from_email' => [ 'label' => 'From Email',       'type' => 'email',    'placeholder' => 'noreply@domain.com' ],
            ],
            'notes' => [
                '100 email/ay ücretsiz (süresiz).',
                'From email Postmark\'ta verified sender olarak eklenmiş olmalı.',
            ],
        ];
    }

    public function send( string $to, string $subject, string $body, array $headers = [] ): array
    {
        if ( empty( $this->api_key ) ) {
            return [ 'error' => true, 'message' => 'Postmark API Token eksik.' ];
        }

        $payload = [
            'From'     => $this->from_name . ' <' . $this->from_email . '>',
            'To'       => $to,
            'Subject'  => $subject,
            'HtmlBody' => $body,
            'MessageStream' => 'outbound',
        ];

        $response = wp_remote_post( 'https://api.postmarkapp.com/email', [
            'headers' => [
                'X-Postmark-Server-Token' => $this->api_key,
                'Content-Type'            => 'application/json',
                'Accept'                  => 'application/json',
            ],
            'body'    => wp_json_encode( $payload ),
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'error' => true, 'message' => $response->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code === 200 && isset( $data['ErrorCode'] ) && $data['ErrorCode'] === 0 ) {
            return [ 'error' => false, 'message' => 'Sent via Postmark' ];
        }

        $msg = $data['Message'] ?? "HTTP {$code}";
        return [ 'error' => true, 'message' => (string) $msg ];
    }
}
