<?php

namespace SaltHareket\Sms\Providers;

use SaltHareket\Sms\Contracts\SmsContract;
use SaltHareket\Sms\SmsSettings;
use SaltHareket\Sms\SmsEncoding;

/**
 * D7Provider — D7 Networks SMS/OTP API
 *
 * @version 1.1.0
 * @changelog
 *   1.1.0 - 2026-06-18 — Move to sms app, namespace SaltHareket\Sms\Providers
 *   1.0.0 - 2026-05-04 — Initial release
 */
class D7Provider implements SmsContract
{
    private string $token;
    private string $sender_id;
    private int    $otp_expiry = 600;

    public function __construct( array $settings = [] )
    {
        $cfg             = $settings['d7'] ?? $settings;
        $this->token     = $cfg['token']     ?? '';
        $this->sender_id = $cfg['sender_id'] ?? 'Salthareket';

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
                'token'     => [ 'label' => 'API Token (Bearer)', 'type' => 'password', 'placeholder' => 'eyJhbGci...' ],
                'sender_id' => [ 'label' => 'Sender ID', 'type' => 'text', 'placeholder' => 'Salthareket' ],
            ],
        ];
    }

    public function send( array $recipients, string $content, array $opts = [] ): array
    {
        $data_coding = SmsEncoding::type( $content );

        $body = [
            'messages' => [ [
                'channel'     => 'sms',
                'recipients'  => $recipients,
                'content'     => $content,
                'msg_type'    => 'text',
                'data_coding' => $data_coding,
            ] ],
            'message_globals' => [ 'originator' => $this->sender_id ],
        ];

        if ( ! empty( $opts['schedule_time'] ) ) {
            $body['message_globals']['schedule_time'] = $opts['schedule_time'];
        }

        return $this->request( 'POST', 'https://api.d7networks.com/messages/v1/send', $body );
    }

    public function generateOtp( string $recipient, int $user_id, string $content = '' ): array
    {
        $otp          = SmsSettings::getOtpConfig();
        $otp_content  = $content ?: 'Your verification code is {}';
        $data_coding  = SmsEncoding::type( $otp_content );

        $body = [
            'originator'      => $this->sender_id,
            'recipient'       => $recipient,
            'content'         => $otp_content,
            'expiry'          => $this->otp_expiry,
            'data_coding'     => $data_coding,
            'otp_code_length' => $otp['otp_length'],
            'otp_type'        => 'numeric',
            'retry_count'     => $otp['max_resend'],
            'retry_delay'     => 60,
        ];

        $result = $this->request( 'POST', 'https://api.d7networks.com/verify/v1/otp/send-otp', $body );

        if ( ! $result['error'] && ! empty( $result['data']['otp_id'] ) ) {
            $expiry = gmdate( 'Y-m-d H:i:s', time() + $this->otp_expiry );
            update_user_meta( $user_id, 'otp_id',           $result['data']['otp_id'] );
            update_user_meta( $user_id, 'otp_expiry',       $expiry );
            update_user_meta( $user_id, 'otp_resend_count', 0 );
        }

        return $result;
    }

    public function verifyOtp( string $otp_id, string $otp_code ): array
    {
        return $this->request( 'POST', 'https://api.d7networks.com/verify/v1/otp/verify-otp', [
            'otp_id' => $otp_id, 'otp_code' => $otp_code,
        ] );
    }

    public function resendOtp( string $otp_id, int $user_id ): array
    {
        $result = $this->request( 'POST', 'https://api.d7networks.com/verify/v1/otp/resend-otp', [ 'otp_id' => $otp_id ] );

        if ( ! $result['error'] && ! empty( $result['data']['otp_id'] ) ) {
            $expiry = gmdate( 'Y-m-d H:i:s', time() + $this->otp_expiry );
            update_user_meta( $user_id, 'otp_id',           $result['data']['otp_id'] );
            update_user_meta( $user_id, 'otp_expiry',       $expiry );
            update_user_meta( $user_id, 'otp_resend_count', (int) ( $result['data']['resend_count'] ?? 0 ) );
        }

        return $result;
    }

    public function checkBalance(): array
    {
        return $this->request( 'GET', 'https://api.d7networks.com/messages/balance' );
    }

    private function request( string $method, string $url, array $body = [] ): array
    {
        $args = [
            'method'  => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 15,
        ];

        if ( $method !== 'GET' && ! empty( $body ) ) {
            $args['body'] = wp_json_encode( $body );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            return [ 'error' => true, 'message' => $response->get_error_message(), 'data' => null ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code >= 200 && $code < 300 ) {
            return [ 'error' => false, 'message' => 'OK', 'data' => $data ];
        }

        $msg = $data['detail']['message'] ?? $data['detail'] ?? "HTTP {$code}";
        if ( is_array( $msg ) ) $msg = $msg[0]['msg'] ?? "HTTP {$code}";

        return [ 'error' => true, 'message' => (string) $msg, 'data' => $data ];
    }
}
