<?php

namespace SaltHareket\Notifications\Carriers\Providers;

use SaltHareket\Notifications\Carriers\SmsContract;

/**
 * TwilioProvider — Twilio SMS/Verify API
 * Auth: Account SID + Auth Token (HTTP Basic)
 * OTP: ✅ Verify API v2
 * Coverage: Global
 * Sender ID: Alphanumeric (ülkeye göre kayıt gerekebilir) veya telefon numarası
 *
 * @version 1.0.0
 */
class TwilioProvider implements SmsContract
{
    private string $account_sid;
    private string $auth_token;
    private string $sender_id;
    private string $verify_service_sid;
    private int    $otp_expiry = 600;

    public function __construct( array $settings = [] )
    {
        $cfg              = $settings['twilio'] ?? $settings;
        $this->account_sid        = $cfg['account_sid']        ?? '';
        $this->auth_token         = $cfg['auth_token']         ?? '';
        $this->sender_id          = $cfg['sender_id']          ?? '';
        $this->verify_service_sid = $cfg['verify_service_sid'] ?? '';

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
                'account_sid'        => [ 'label' => 'Account SID',         'type' => 'text',     'placeholder' => 'ACxxxxxxxxxxxxxxxx' ],
                'auth_token'         => [ 'label' => 'Auth Token',           'type' => 'password', 'placeholder' => 'your_auth_token' ],
                'sender_id'          => [ 'label' => 'From (Twilio Number)', 'type' => 'text', 'placeholder' => '+12345678901 — Twilio Console\'dan aldığın numara (trial\'da kendi numaranı girme)' ],
                'verify_service_sid' => [ 'label' => 'Verify Service SID (OTP için)', 'type' => 'text', 'placeholder' => 'VAxxxxxxxxxxxxxxxx' ],
            ],
        ];
    }

    public function send( array $recipients, string $content, array $opts = [] ): array
    {
        $errors = [];
        foreach ( $recipients as $to ) {
            $result = $this->request(
                'POST',
                "https://api.twilio.com/2010-04-01/Accounts/{$this->account_sid}/Messages.json",
                [
                    'To'           => $to,
                    'From'         => $this->sender_id,
                    'Body'         => $content,
                    'SmartEncoded' => 'true', // GSM-7 dışı karakterleri otomatik unicode'a çevirir
                ],
                'form'
            );
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
        if ( empty( $this->verify_service_sid ) ) {
            return [ 'error' => true, 'message' => 'Verify Service SID eksik. Twilio Console\'dan oluşturun.', 'data' => null ];
        }

        $result = $this->request(
            'POST',
            "https://verify.twilio.com/v2/Services/{$this->verify_service_sid}/Verifications",
            [ 'To' => $recipient, 'Channel' => 'sms' ],
            'form'
        );

        if ( ! $result['error'] ) {
            $expiry = gmdate( 'Y-m-d H:i:s', time() + $this->otp_expiry );
            // Twilio Verify'da otp_id = SID
            $sid = $result['data']['sid'] ?? '';
            update_user_meta( $user_id, 'otp_id',           $sid );
            update_user_meta( $user_id, 'otp_expiry',       $expiry );
            update_user_meta( $user_id, 'otp_resend_count', 0 );
        }

        return $result;
    }

    public function verifyOtp( string $otp_id, string $otp_code ): array
    {
        // Twilio Verify: otp_id = recipient phone (verification check by phone)
        // otp_id olarak telefon numarasını saklıyoruz
        return $this->request(
            'POST',
            "https://verify.twilio.com/v2/Services/{$this->verify_service_sid}/VerificationCheck",
            [ 'To' => $otp_id, 'Code' => $otp_code ],
            'form'
        );
    }

    public function resendOtp( string $otp_id, int $user_id ): array
    {
        // Twilio'da resend = yeni verification başlat
        $phone = get_user_meta( $user_id, 'phone', true ) ?: $otp_id;
        return $this->generateOtp( $phone, $user_id );
    }

    public function checkBalance(): array
    {
        return $this->request(
            'GET',
            "https://api.twilio.com/2010-04-01/Accounts/{$this->account_sid}/Balance.json"
        );
    }

    // ─── HTTP ────────────────────────────────────────────────

    private function request( string $method, string $url, array $body = [], string $body_type = 'json' ): array
    {
        $args = [
            'method'  => $method,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode( $this->account_sid . ':' . $this->auth_token ),
            ],
            'timeout' => 15,
        ];

        if ( $method !== 'GET' && ! empty( $body ) ) {
            if ( $body_type === 'form' ) {
                $args['body']                    = $body;
                $args['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
            } else {
                $args['body']                    = wp_json_encode( $body );
                $args['headers']['Content-Type'] = 'application/json';
            }
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

        $msg = $data['message'] ?? $data['error_message'] ?? "HTTP {$code}";
        // Debug: tam response'u da ekle
        if ( defined('WP_DEBUG') && WP_DEBUG && $code === 401 ) {
            error_log('[TwilioProvider] 401 Auth fail. SID length: ' . strlen($this->account_sid) . ', Token length: ' . strlen($this->auth_token));
        }
        return [ 'error' => true, 'message' => (string) $msg, 'data' => $data ];
    }
}
