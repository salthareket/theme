<?php

namespace SaltHareket\Notifications\Carriers\Providers;

use SaltHareket\Notifications\Carriers\SmsContract;

/**
 * InfobipProvider — Infobip SMS/2FA API
 * Auth: API Key (Header: Authorization: App {key})
 * OTP: ✅ 2FA API
 * Coverage: Global + Türkiye yerel
 * Sender ID: Alphanumeric
 *
 * @version 1.0.0
 */
class InfobipProvider implements SmsContract
{
    private string $api_key;
    private string $base_url;
    private string $sender_id;
    private string $app_id;
    private string $message_id;
    private int    $otp_expiry = 600;

    public function __construct( array $settings = [] )
    {
        $cfg              = $settings['infobip'] ?? $settings;
        $this->api_key    = $cfg['api_key']    ?? '';
        $this->base_url   = rtrim( $cfg['base_url'] ?? 'https://api.infobip.com', '/' );
        $this->sender_id  = $cfg['sender_id']  ?? '';
        $this->app_id     = $cfg['app_id']     ?? '';
        $this->message_id = $cfg['message_id'] ?? '';

        $otp = \SaltHareket\Notifications\Carriers\SmsSettings::getOtpConfig();
        $this->otp_expiry = $otp['otp_expiry'];
    }

    public static function capabilities(): array
    {
        return [
            'sms'                  => true,
            'otp'                  => true,
            'coverage'             => 'global',
            'regions'              => [ 'TR' ],
            'sender_id_type'       => 'alphanumeric',
            'sender_id_max_length' => 11,
            'auth_fields'          => [
                'api_key'    => [ 'label' => 'API Key',    'type' => 'password', 'placeholder' => 'Infobip API Key' ],
                'base_url'   => [ 'label' => 'Base URL',   'type' => 'text',     'placeholder' => 'https://xxxxx.api.infobip.com' ],
                'sender_id'  => [ 'label' => 'Sender ID',  'type' => 'text',     'placeholder' => 'BrandName' ],
                'app_id'     => [ 'label' => '2FA App ID (OTP için)',     'type' => 'text', 'placeholder' => 'Infobip 2FA Application ID' ],
                'message_id' => [ 'label' => '2FA Message ID (OTP için)', 'type' => 'text', 'placeholder' => 'Infobip 2FA Message Template ID' ],
            ],
        ];
    }

    public function send( array $recipients, string $content, array $opts = [] ): array
    {
        // GSM-7 dışı karakter varsa unicode encoding
        $requires_unicode = \SaltHareket\Notifications\Carriers\SmsEncoding::requiresUnicode( $content );

        $messages = array_map( fn( $to ) => [
            'destinations' => [ [ 'to' => $to ] ],
            'from'         => $this->sender_id,
            'text'         => $content,
            // Infobip: transliteration=NONE unicode karakterleri korur
            'transliteration' => $requires_unicode ? 'NONE' : 'DEFAULT',
        ], $recipients );

        return $this->request( 'POST', '/sms/3/messages', [ 'messages' => $messages ] );
    }

    public function generateOtp( string $recipient, int $user_id, string $content = '' ): array
    {
        if ( empty( $this->app_id ) || empty( $this->message_id ) ) {
            return [ 'error' => true, 'message' => 'Infobip 2FA App ID ve Message ID gerekli. Infobip Console\'dan oluşturun.', 'data' => null ];
        }

        $result = $this->request( 'POST', '/2fa/2/pin', [
            'applicationId' => $this->app_id,
            'messageId'     => $this->message_id,
            'to'            => $recipient,
        ] );

        if ( ! $result['error'] && ! empty( $result['data']['pinId'] ) ) {
            $expiry = gmdate( 'Y-m-d H:i:s', time() + $this->otp_expiry );
            update_user_meta( $user_id, 'otp_id',           $result['data']['pinId'] );
            update_user_meta( $user_id, 'otp_expiry',       $expiry );
            update_user_meta( $user_id, 'otp_resend_count', 0 );
        }

        return $result;
    }

    public function verifyOtp( string $otp_id, string $otp_code ): array
    {
        return $this->request( 'POST', "/2fa/2/pin/{$otp_id}/verify", [ 'pin' => $otp_code ] );
    }

    public function resendOtp( string $otp_id, int $user_id ): array
    {
        $result = $this->request( 'POST', "/2fa/2/pin/{$otp_id}/resend" );

        if ( ! $result['error'] ) {
            $count = (int) get_user_meta( $user_id, 'otp_resend_count', true );
            update_user_meta( $user_id, 'otp_resend_count', $count + 1 );
        }

        return $result;
    }

    public function checkBalance(): array
    {
        return $this->request( 'GET', '/account/1/balance' );
    }

    // ─── HTTP ────────────────────────────────────────────────

    private function request( string $method, string $path, array $body = [] ): array
    {
        $url  = $this->base_url . $path;
        $args = [
            'method'  => $method,
            'headers' => [
                'Authorization' => 'App ' . $this->api_key,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
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

        $msg = $data['requestError']['serviceException']['text']
            ?? $data['requestError']['policyException']['text']
            ?? "HTTP {$code}";

        return [ 'error' => true, 'message' => (string) $msg, 'data' => $data ];
    }
}
