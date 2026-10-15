<?php

namespace SaltHareket\Sms\Providers;

use SaltHareket\Sms\Contracts\SmsContract;
use SaltHareket\Sms\SmsSettings;
use SaltHareket\Sms\SmsEncoding;

/**
 * VonageProvider — Vonage (Nexmo) SMS/Verify API
 *
 * @version 1.1.0
 * @changelog
 *   1.1.0 - 2026-06-18 — Move to sms app, namespace SaltHareket\Sms\Providers
 *   1.0.0 - 2026-05-04 — Initial release
 */
class VonageProvider implements SmsContract
{
    private string $api_key;
    private string $api_secret;
    private string $sender_id;
    private string $brand;
    private int    $otp_expiry = 300;

    public function __construct( array $settings = [] )
    {
        $cfg              = $settings['vonage'] ?? $settings;
        $this->api_key    = $cfg['api_key']    ?? '';
        $this->api_secret = $cfg['api_secret'] ?? '';
        $this->sender_id  = $cfg['sender_id']  ?? '';
        $this->brand      = $cfg['brand']      ?? $this->sender_id;

        $otp = SmsSettings::getOtpConfig();
        $this->otp_expiry = $otp['otp_expiry'];
    }

    public static function capabilities(): array
    {
        return [
            'sms'                  => true,
            'otp'                  => true,
            'coverage'             => 'global',
            'regions'              => [],
            'sender_id_type'       => 'alphanumeric',
            'sender_id_max_length' => 11,
            'auth_fields'          => [
                'api_key'    => [ 'label' => 'API Key',    'type' => 'text',     'placeholder' => 'abc12345' ],
                'api_secret' => [ 'label' => 'API Secret', 'type' => 'password', 'placeholder' => 'your_api_secret' ],
                'sender_id'  => [ 'label' => 'Sender ID',  'type' => 'text',     'placeholder' => 'BrandName' ],
                'brand'      => [ 'label' => 'Brand Name (OTP için)', 'type' => 'text', 'placeholder' => 'Salthareket' ],
            ],
        ];
    }

    public function send( array $recipients, string $content, array $opts = [] ): array
    {
        $errors = [];
        $type   = SmsEncoding::type( $content );

        foreach ( $recipients as $to ) {
            $result = $this->request( 'POST', 'https://rest.nexmo.com/sms/json', [
                'api_key'    => $this->api_key,
                'api_secret' => $this->api_secret,
                'from'       => $this->sender_id,
                'to'         => ltrim( $to, '+' ),
                'text'       => $content,
                'type'       => $type,
            ], 'form' );
            if ( $result['error'] ) $errors[] = $result['message'];
        }

        if ( ! empty( $errors ) ) {
            return [ 'error' => true, 'message' => implode( '; ', $errors ), 'data' => null ];
        }

        return [ 'error' => false, 'message' => 'Sent', 'data' => null ];
    }

    public function generateOtp( string $recipient, int $user_id, string $content = '' ): array
    {
        $result = $this->request( 'POST', 'https://api.nexmo.com/v2/verify', [
            'brand'    => $this->brand ?: $this->sender_id,
            'workflow' => [ [ 'channel' => 'sms', 'to' => $recipient ] ],
        ] );

        if ( ! $result['error'] && ! empty( $result['data']['request_id'] ) ) {
            $expiry = gmdate( 'Y-m-d H:i:s', time() + $this->otp_expiry );
            update_user_meta( $user_id, 'otp_id',           $result['data']['request_id'] );
            update_user_meta( $user_id, 'otp_expiry',       $expiry );
            update_user_meta( $user_id, 'otp_resend_count', 0 );
        }

        return $result;
    }

    public function verifyOtp( string $otp_id, string $otp_code ): array
    {
        return $this->request( 'POST', "https://api.nexmo.com/v2/verify/{$otp_id}", [ 'code' => $otp_code ] );
    }

    public function resendOtp( string $otp_id, int $user_id ): array
    {
        $phone = get_user_meta( $user_id, 'phone', true );
        if ( empty( $phone ) ) {
            return [ 'error' => true, 'message' => 'Telefon numarası bulunamadı.', 'data' => null ];
        }
        return $this->generateOtp( $phone, $user_id );
    }

    public function checkBalance(): array
    {
        return $this->request( 'GET', "https://rest.nexmo.com/account/get-balance?api_key={$this->api_key}&api_secret={$this->api_secret}" );
    }

    private function request( string $method, string $url, array $body = [], string $body_type = 'json' ): array
    {
        $args = [
            'method'  => $method,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode( $this->api_key . ':' . $this->api_secret ),
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 15,
        ];

        if ( $method !== 'GET' && ! empty( $body ) ) {
            if ( $body_type === 'form' ) {
                $args['body']                    = $body;
                $args['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
            } else {
                $args['body'] = wp_json_encode( $body );
            }
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            return [ 'error' => true, 'message' => $response->get_error_message(), 'data' => null ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code >= 200 && $code < 300 ) {
            if ( isset( $data['messages'][0]['status'] ) && $data['messages'][0]['status'] != 0 ) {
                return [ 'error' => true, 'message' => $data['messages'][0]['error-text'] ?? 'Unknown error', 'data' => $data ];
            }
            return [ 'error' => false, 'message' => 'OK', 'data' => $data ];
        }

        return [ 'error' => true, 'message' => $data['title'] ?? $data['error_text'] ?? "HTTP {$code}", 'data' => $data ];
    }
}
