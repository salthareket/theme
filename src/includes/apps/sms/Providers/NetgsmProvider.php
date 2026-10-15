<?php

namespace SaltHareket\Sms\Providers;

use SaltHareket\Sms\Contracts\SmsContract;
use SaltHareket\Sms\SmsSettings;
use SaltHareket\Sms\SmsEncoding;

/**
 * NetgsmProvider — Netgsm SMS API (Türkiye)
 *
 * @version 1.1.0
 * @changelog
 *   1.1.0 - 2026-06-18 — Move to sms app, namespace SaltHareket\Sms\Providers
 *   1.0.0 - 2026-05-04 — Initial release
 */
class NetgsmProvider implements SmsContract
{
    private string $username;
    private string $password;
    private string $header;
    private bool   $use_otp_channel = false;
    private int    $otp_expiry      = 300;
    private int    $otp_length      = 6;
    private int    $max_resend      = 5;

    public function __construct( array $settings = [] )
    {
        $cfg                   = $settings['netgsm'] ?? $settings;
        $this->username        = $cfg['username']        ?? '';
        $this->password        = $cfg['password']        ?? '';
        $this->header          = $cfg['header']          ?? '';
        $this->use_otp_channel = ! empty( $cfg['use_otp_channel'] );

        $otp = SmsSettings::getOtpConfig();
        $this->otp_expiry = $otp['otp_expiry'];
        $this->otp_length = $otp['otp_length'];
        $this->max_resend = $otp['max_resend'];
    }

    public static function capabilities(): array
    {
        return [
            'sms'                  => true,
            'otp'                  => true,
            'otp_type'             => 'self_managed',
            'coverage'             => 'local',
            'regions'              => [ 'TR' ],
            'sender_id_type'       => 'header',
            'sender_id_max_length' => 11,
            'auth_fields'          => [
                'username'        => [ 'label' => 'Kullanıcı Adı',               'type' => 'text',     'placeholder' => 'Netgsm kullanıcı adı' ],
                'password'        => [ 'label' => 'Şifre',                       'type' => 'password', 'placeholder' => 'Netgsm şifresi' ],
                'header'          => [ 'label' => 'Başlık Kodu (Gönderici)',      'type' => 'text',     'placeholder' => 'MARKAM' ],
                'use_otp_channel' => [ 'label' => 'OTP Kanalı', 'type' => 'checkbox', 'placeholder' => '', 'description' => 'Ayrı OTP kanalını kullan' ],
            ],
        ];
    }

    public function send( array $recipients, string $content, array $opts = [] ): array
    {
        $numbers  = implode( ',', array_map( fn( $n ) => ltrim( $n, '+' ), $recipients ) );
        $encoding = SmsEncoding::netgsmEncoding( $content );

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<mainbody><header>'
            . '<company dil="TR">Netgsm</company>'
            . '<usercode>' . esc_xml( $this->username ) . '</usercode>'
            . '<password>' . esc_xml( $this->password ) . '</password>'
            . '<type>1:n</type>'
            . '<msgheader>' . esc_xml( $this->header ) . '</msgheader>'
            . ( $encoding ? '<encoding>' . $encoding . '</encoding>' : '' )
            . '</header><body>'
            . '<msg><![CDATA[' . $content . ']]></msg>'
            . '<no>' . esc_xml( $numbers ) . '</no>'
            . '</body></mainbody>';

        $response = wp_remote_post( 'https://api.netgsm.com.tr/sms/send/xml', [
            'body'    => $xml,
            'headers' => [ 'Content-Type' => 'text/xml; charset=UTF-8' ],
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'error' => true, 'message' => $response->get_error_message(), 'data' => null ];
        }

        $body = wp_remote_retrieve_body( $response );
        $code = trim( explode( ' ', $body )[0] );

        $error_codes = [
            '20' => 'Mesaj metni boş.',
            '30' => 'Geçersiz kullanıcı adı veya şifre.',
            '40' => 'Mesaj başlığı (header) hatalı.',
            '50' => 'Hesabınızda yeterli kredi yok.',
            '70' => 'Hatalı sorgulama.',
            '85' => 'Uluslararası SMS gönderimi için yetkiniz yok.',
        ];

        if ( isset( $error_codes[$code] ) ) {
            return [ 'error' => true, 'message' => $error_codes[$code], 'data' => $body ];
        }

        return [ 'error' => false, 'message' => 'Sent', 'data' => $body ];
    }

    public function generateOtp( string $recipient, int $user_id, string $content = '' ): array
    {
        $code    = $this->generateCode();
        $message = $content ? str_replace( '{}', $code, $content ) : "Your verification code: {$code}";
        $result  = $this->sendOtpSms( $recipient, $message );

        if ( $result['error'] ) return $result;

        $expiry = time() + $this->otp_expiry;
        update_user_meta( $user_id, 'otp_code',         $code );
        update_user_meta( $user_id, 'otp_expiry',       gmdate( 'Y-m-d H:i:s', $expiry ) );
        update_user_meta( $user_id, 'otp_phone',        $recipient );
        update_user_meta( $user_id, 'otp_resend_count', 0 );
        update_user_meta( $user_id, 'otp_id',           (string) $user_id );

        return [ 'error' => false, 'message' => 'OTP kodu gönderildi.', 'data' => [ 'otp_id' => (string) $user_id, 'expiry' => $this->otp_expiry ] ];
    }

    public function verifyOtp( string $otp_id, string $otp_code ): array
    {
        $user_id       = (int) $otp_id;
        $stored_code   = get_user_meta( $user_id, 'otp_code',   true );
        $stored_expiry = get_user_meta( $user_id, 'otp_expiry', true );

        if ( empty( $stored_code ) ) {
            return [ 'error' => true, 'message' => 'OTP kodu bulunamadı.', 'data' => null ];
        }
        if ( $stored_expiry && strtotime( $stored_expiry ) < time() ) {
            delete_user_meta( $user_id, 'otp_code' );
            return [ 'error' => true, 'message' => 'OTP kodunun süresi dolmuş.', 'data' => [ 'status' => 'EXPIRED' ] ];
        }
        if ( ! hash_equals( (string) $stored_code, (string) $otp_code ) ) {
            return [ 'error' => true, 'message' => 'Geçersiz OTP kodu.', 'data' => [ 'status' => 'INVALID' ] ];
        }

        delete_user_meta( $user_id, 'otp_code' );
        delete_user_meta( $user_id, 'otp_expiry' );

        return [ 'error' => false, 'message' => 'Doğrulama başarılı.', 'data' => [ 'status' => 'APPROVED' ] ];
    }

    public function resendOtp( string $otp_id, int $user_id ): array
    {
        $resend_count = (int) get_user_meta( $user_id, 'otp_resend_count', true );
        if ( $resend_count >= $this->max_resend ) {
            return [ 'error' => true, 'message' => "Maksimum yeniden gönderim limitine ({$this->max_resend}) ulaşıldı.", 'data' => null ];
        }
        $phone = get_user_meta( $user_id, 'otp_phone', true );
        if ( empty( $phone ) ) {
            return [ 'error' => true, 'message' => 'Telefon numarası bulunamadı.', 'data' => null ];
        }
        $result = $this->generateOtp( $phone, $user_id );
        if ( ! $result['error'] ) {
            update_user_meta( $user_id, 'otp_resend_count', $resend_count + 1 );
        }
        return $result;
    }

    public function checkBalance(): array
    {
        $response = wp_remote_get(
            "https://api.netgsm.com.tr/balance/list/get/?usercode={$this->username}&password={$this->password}",
            [ 'timeout' => 15 ]
        );
        if ( is_wp_error( $response ) ) {
            return [ 'error' => true, 'message' => $response->get_error_message(), 'data' => null ];
        }
        return [ 'error' => false, 'message' => 'OK', 'data' => wp_remote_retrieve_body( $response ) ];
    }

    private function sendOtpSms( string $recipient, string $message ): array
    {
        $number   = ltrim( $recipient, '+' );
        $encoding = SmsEncoding::netgsmEncoding( $message );

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<mainbody><header>'
            . '<usercode>' . esc_xml( $this->username ) . '</usercode>'
            . '<password>' . esc_xml( $this->password ) . '</password>'
            . '<msgheader>' . esc_xml( $this->header ) . '</msgheader>'
            . ( $encoding ? '<encoding>' . $encoding . '</encoding>' : '' )
            . '</header><body>'
            . '<msg><![CDATA[' . $message . ']]></msg>'
            . '<no>' . esc_xml( $number ) . '</no>'
            . '</body></mainbody>';

        $response = wp_remote_post( 'https://api.netgsm.com.tr/sms/send/otp', [
            'body'    => $xml,
            'headers' => [ 'Content-Type' => 'text/xml; charset=UTF-8' ],
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'error' => true, 'message' => $response->get_error_message(), 'data' => null ];
        }

        $body = wp_remote_retrieve_body( $response );
        $code = trim( explode( ' ', $body )[0] );
        $error_codes = [ '20' => 'Mesaj metni boş.', '30' => 'Geçersiz kullanıcı adı.', '40' => 'Header hatalı.', '50' => 'OTP paketi bulunamadı.', '70' => 'Hatalı sorgulama.' ];

        if ( isset( $error_codes[$code] ) ) {
            return [ 'error' => true, 'message' => $error_codes[$code], 'data' => $body ];
        }
        return [ 'error' => false, 'message' => 'OTP SMS gönderildi.', 'data' => $body ];
    }

    private function generateCode(): string
    {
        try {
            $code = random_int(
                (int) str_pad( '1', $this->otp_length, '0' ),
                (int) str_pad( '9', $this->otp_length, '9' )
            );
        } catch ( \Exception $e ) {
            $code = mt_rand(
                (int) str_pad( '1', $this->otp_length, '0' ),
                (int) str_pad( '9', $this->otp_length, '9' )
            );
        }
        return str_pad( (string) $code, $this->otp_length, '0', STR_PAD_LEFT );
    }
}
