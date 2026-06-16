<?php

namespace SaltHareket\Notifications\Carriers\Email\Providers;

use SaltHareket\Notifications\Carriers\Email\EmailContract;

/**
 * MailgunProvider — Mailgun Messages API
 * Auth: API Key (HTTP Basic: api:{key})
 * Free: 1.000/ay (3 ay trial)
 * Region: US (api.mailgun.net) veya EU (api.eu.mailgun.net)
 *
 * @version 1.0.0
 */
class MailgunProvider implements EmailContract
{
    private string $api_key;
    private string $domain;
    private string $region;
    private string $from_name;
    private string $from_email;

    public function __construct( array $config = [] )
    {
        $this->api_key    = $config['api_key']    ?? '';
        $this->domain     = $config['domain']     ?? '';
        $this->region     = $config['region']     ?? 'us';
        $this->from_name  = $config['from_name']  ?? get_bloginfo( 'name' );
        $this->from_email = $config['from_email'] ?? get_option( 'admin_email' );
    }

    public static function metadata(): array
    {
        return [
            'label' => 'Mailgun',
            'type'  => 'api',
            'auth_fields' => [
                'api_key'    => [ 'label' => 'API Key',    'type' => 'password', 'placeholder' => 'key-xxxxxxxx' ],
                'domain'     => [ 'label' => 'Domain',     'type' => 'text',     'placeholder' => 'mg.yourdomain.com' ],
                'region'     => [ 'label' => 'Region',     'type' => 'select',   'options' => [ 'us' => 'US', 'eu' => 'EU (Europe)' ] ],
                'from_name'  => [ 'label' => 'From Name',  'type' => 'text',     'placeholder' => 'Site Adı' ],
                'from_email' => [ 'label' => 'From Email', 'type' => 'email',    'placeholder' => 'noreply@mg.yourdomain.com' ],
            ],
            'notes' => [
                '1.000 email/ay ücretsiz (3 ay).',
                'Domain doğrulaması gerekli (DNS kayıtları).',
            ],
        ];
    }

    public function send( string $to, string $subject, string $body, array $headers = [] ): array
    {
        if ( empty( $this->api_key ) || empty( $this->domain ) ) {
            return [ 'error' => true, 'message' => 'Mailgun API Key veya Domain eksik.' ];
        }

        $base = $this->region === 'eu'
            ? 'https://api.eu.mailgun.net'
            : 'https://api.mailgun.net';

        $url = "{$base}/v3/{$this->domain}/messages";

        $response = wp_remote_post( $url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode( 'api:' . $this->api_key ),
            ],
            'body' => [
                'from'    => $this->from_name . ' <' . $this->from_email . '>',
                'to'      => $to,
                'subject' => $subject,
                'html'    => $body,
            ],
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'error' => true, 'message' => $response->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code === 200 ) {
            return [ 'error' => false, 'message' => 'Sent via Mailgun' ];
        }

        $msg = $data['message'] ?? "HTTP {$code}";
        return [ 'error' => true, 'message' => (string) $msg ];
    }
}
