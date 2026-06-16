<?php

namespace SaltHareket\Notifications\Carriers\Providers;

use SaltHareket\Notifications\Carriers\SmsContract;

/**
 * SinchProvider — Sinch SMS/Verification API
 * Auth: App Key + App Secret (HTTP Basic)
 * OTP: ✅ Verification API
 * Coverage: Global
 * Sender ID: Alphanumeric (ülkeye göre)
 *
 * @version 1.0.0
 */
class SinchProvider implements SmsContract
{
    private string $app_key;
    private string $app_secret;
    private string $sender_id;
    private int    $otp_expiry = 300;

    public function __construct( array $settings = [] )
    {
        $cfg              = $settings['sinch'] ?? $settings;
        $this->app_key    = $cfg['app_key']    ?? '';
        $this->app_secret = $cfg['app_secret'] ?? '';
        $this->sender_id  = $cfg['sender_id']  ?? '';

        $otp = \SaltHareket\Notifications\Carriers\SmsSettings::getOtpConfig();
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
                'app_key'    => [ 'label' => 'App Key',    'type' => 'text',     'placeholder' => 'Sinch App Key' ],
                'app_secret' => [ 'label' => 'App Secret', 'type' => 'password', 'placeholder' => 'Sinch App Secret' ],
                'sender_id'  => [ 'label' => 'Sender ID',  'type' => 'text',     'placeholder' => 'BrandName' ],
            ],
        ];
    }

    public function send( array $recipients, string $content, array $opts = [] ): array
    {
        $errors = [];
        foreach ( $recipients as $to ) {
            $result = $this->request( 'POST', 'https://sms.api.sinch.com/xms/v1/' . $this->app_key . '/batches', [
                'from'     => $this->sender_id,
                'to'       => [ $to ],
                'body'     => $content,
                // Sinch: encoding parametresi — unicode karakterler için
                'encoding' => \SaltHareket\Notifications\Carriers\SmsEncoding::requiresUnicode( $content ) ? 'UCS2' : 'GSM7',
            ] );
            if ( $result['error'] ) {
                $errors[] = $result['message'];
            }
        }

        if ( ! empty( $errors ) ) {
            return [ 'error' => true, 'message' => implode( '; ', $errors ), 'data' => null ];
        }

        return [ 'error' => false, 'message' => 'Sent', 'data' => null ];
    }

    public function generateOtp( string $recipient, int $user_id, string $content = '' ): array
    {
        $result = $this->request( 'POST', 'https://verification.api.sinch.com/verification/v1/verifications', [
            'identity' => [ 'type' => 'number', 'endpoint' => $recipient ],
            'method'   => 'sms',
        ] );

        if ( ! $result['error'] ) {
            $expiry = gmdate( 'Y-m-d H:i:s', time() + $this->otp_expiry );
            // Sinch'te verification ID = id field
            $id = $result['data']['id'] ?? '';
            update_user_meta( $user_id, 'otp_id',           $id );
            update_user_meta( $user_id, 'otp_expiry',       $expiry );
            update_user_meta( $user_id, 'otp_resend_count', 0 );
        }

        return $result;
    }

    public function verifyOtp( string $otp_id, string $otp_code ): array
    {
        // Sinch: verify by number
        $phone = get_user_meta( (int) $otp_id, 'phone', true ) ?: $otp_id;
        return $this->request(
            'PUT',
            "https://verification.api.sinch.com/verification/v1/verifications/number/{$phone}",
            [ 'method' => 'sms', 'sms' => [ 'code' => $otp_code ] ]
        );
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
        // Sinch'te balance endpoint yok — account API ayrı
        return [ 'error' => false, 'message' => 'Balance check not supported by Sinch SMS API.', 'data' => null ];
    }

    // ─── HTTP ────────────────────────────────────────────────

    private function request( string $method, string $url, array $body = [] ): array
    {
        $args = [
            'method'  => $method,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode( $this->app_key . ':' . $this->app_secret ),
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

        $msg = $data['message'] ?? $data['error'] ?? "HTTP {$code}";
        return [ 'error' => true, 'message' => (string) $msg, 'data' => $data ];
    }
}
