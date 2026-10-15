<?php

namespace SaltHareket\Membership\Concerns;

use SaltHareket\Membership\MembershipSettings;

/**
 * Handles2FA
 *
 * İki faktörlü kimlik doğrulama (2FA) — SMS OTP ve TOTP (Google Authenticator).
 *
 * Akış:
 *   1. login() başarılı olunca 2FA aktifse wp_set_auth_cookie() YAPILMAZ.
 *   2. Bunun yerine session'a pending_2fa verisi yazılır.
 *   3. Kullanıcı 2FA kodu girer → verify2FA() çağrılır.
 *   4. Doğrulanırsa wp_set_auth_cookie() yapılır ve giriş tamamlanır.
 *
 * TOTP:
 *   - Her kullanıcı için secret key üretilir → user_meta 'totp_secret'
 *   - QR code URL'si döndürülür → kullanıcı uygulamasına ekler
 *   - 'totp_enabled' meta = true olunca aktif
 *
 * @version 1.0.0
 *
 * @changelog
 *   1.0.0 - 2026-09-04
 *     - Add: SMS 2FA — login sonrası OTP adımı
 *     - Add: TOTP (Google Authenticator) — secret üretimi, QR code, doğrulama
 *     - Add: Trusted device — doğrulanmış cihazda 2FA bypass
 *     - Add: Pending 2FA session — WP_Session yerine transient tabanlı
 */
trait Handles2FA
{
    // ─── 2FA Durumu ──────────────────────────────────────────────────────────

    /**
     * Kullanıcı için 2FA aktif mi ve hangi metod?
     * 'none' | 'sms' | 'totp'
     */
    public function get2FAMethod(): string
    {
        return defined('LOGIN_2FA_METHOD') ? LOGIN_2FA_METHOD : 'none';
    }

    /**
     * Kullanıcı için 2FA aktif mi?
     */
    public function is2FAEnabled( int $user_id ): bool
    {
        $method = $this->get2FAMethod();

        if ( $method === 'sms' ) {
            return $this->userHasPhone( $user_id );
        }

        if ( $method === 'totp' ) {
            return (bool) get_user_meta( $user_id, 'totp_enabled', true );
        }

        return false;
    }

    /**
     * Kullanıcının SMS numarası var mı?
     */
    private function userHasPhone( int $user_id ): bool
    {
        $phone = get_user_meta( $user_id, 'billing_phone', true );
        return ! empty( $phone );
    }

    // ─── Pending 2FA Session ─────────────────────────────────────────────────

    /**
     * 2FA bekleyen kullanıcıyı transient'e yaz.
     * Cookie/session yerine transient — stateless, cache-uyumlu.
     *
     * @return string Pending token (JS'e döndürülür, hidden input ile gönderilir)
     */
    public function setPending2FA( int $user_id ): string
    {
        $token = wp_generate_password( 32, false );
        $key   = 'sh_2fa_pending_' . $token;

        set_transient( $key, [
            'user_id'    => $user_id,
            'created_at' => time(),
            'ip'         => $_SERVER['REMOTE_ADDR'] ?? '',
        ], 10 * MINUTE_IN_SECONDS ); // 10 dakika geçerli

        return $token;
    }

    /**
     * Pending token'dan user_id'yi al.
     * Token geçersizse veya süresi dolmuşsa null döner.
     */
    public function getPending2FAUserId( string $token ): ?int
    {
        if ( empty( $token ) ) return null;

        $key  = 'sh_2fa_pending_' . sanitize_text_field( $token );
        $data = get_transient( $key );

        if ( ! $data || empty( $data['user_id'] ) ) return null;

        // IP kontrolü (opsiyonel — proxy arkasında sorun yaratabilir, sadece uyarı)
        // if ( ( $data['ip'] ?? '' ) !== ( $_SERVER['REMOTE_ADDR'] ?? '' ) ) return null;

        return (int) $data['user_id'];
    }

    /**
     * Pending token'ı sil (doğrulama sonrası temizle).
     */
    public function clearPending2FA( string $token ): void
    {
        delete_transient( 'sh_2fa_pending_' . sanitize_text_field( $token ) );
    }

    // ─── SMS 2FA ─────────────────────────────────────────────────────────────

    /**
     * SMS 2FA kodu gönder.
     * login() başarılı olduktan sonra, auth cookie set edilmeden önce çağrılır.
     */
    public function send2FASMS( int $user_id ): array
    {
        $response = $this->response();

        if ( ! ( defined( 'ENABLE_SMS' ) && ENABLE_SMS ) ) {
            $response['error']   = true;
            $response['message'] = 'SMS is not available.';
            return $response;
        }

        $phone      = get_user_meta( $user_id, 'billing_phone', true );
        $phone_code = get_user_meta( $user_id, 'billing_phone_code', true );

        if ( empty( $phone ) ) {
            $response['error']   = true;
            $response['message'] = 'No phone number on file for this account.';
            return $response;
        }

        // Tam numara: ülke kodu + numara
        $full_phone = ! empty( $phone_code )
            ? $phone_code . preg_replace( '/^\+?0*/', '', $phone )
            : $phone;

        return \SaltHareket\Sms\SmsManager::driver()->generateOtp(
            $full_phone,
            $user_id,
            'Your login verification code is {}'
        );
    }

    /**
     * SMS 2FA kodunu doğrula + giriş tamamla.
     *
     * @param array $vars ['pending_token', 'otp_id', 'otp_code', 'remember', 'redirect_url']
     */
    public function verify2FASMS( array $vars = [] ): array
    {
        $response = $this->response();

        $token   = sanitize_text_field( $vars['pending_token'] ?? '' );
        $user_id = $this->getPending2FAUserId( $token );

        if ( ! $user_id ) {
            $response['error']   = true;
            $response['message'] = 'Session expired. Please log in again.';
            return $response;
        }

        $otp_response = \SaltHareket\Sms\SmsManager::driver()->verifyOtp(
            $vars['otp_id']   ?? '',
            $vars['otp_code'] ?? ''
        );

        if ( $otp_response['error'] ?? true ) {
            $response['error']   = true;
            $response['message'] = $otp_response['message'] ?? 'Invalid verification code.';
            return $response;
        }

        $this->clearPending2FA( $token );
        return $this->completLogin( $user_id, $vars );
    }

    // ─── TOTP (Google Authenticator) ─────────────────────────────────────────

    /**
     * Kullanıcı için TOTP secret üret ve kaydet.
     * Henüz 'totp_enabled' = false — kullanıcı QR'ı tarayıp ilk kodu girince aktif olur.
     *
     * @return array ['secret' => string, 'qr_url' => string, 'qr_image' => string (base64 PNG data URI)]
     */
    public function generateTOTPSecret( int $user_id ): array
    {
        $response = $this->response();

        // Temel google2fa sınıfı (qrcode olmadan da çalışır)
        $g2fa_class = class_exists( \PragmaRX\Google2FAQRCode\Google2FA::class )
            ? \PragmaRX\Google2FAQRCode\Google2FA::class
            : ( class_exists( \PragmaRX\Google2FA\Google2FA::class )
                ? \PragmaRX\Google2FA\Google2FA::class
                : null );

        if ( ! $g2fa_class ) {
            $response['error']   = true;
            $response['message'] = 'TOTP library not available.';
            return $response;
        }

        $google2fa = new $g2fa_class();
        $secret    = $google2fa->generateSecretKey();
        $user      = get_userdata( $user_id );
        $site_name = get_bloginfo( 'name' );

        // Secret'ı kaydet (henüz aktif değil)
        update_user_meta( $user_id, 'totp_secret', $secret );
        update_user_meta( $user_id, 'totp_enabled', false );

        // otpauth:// URL — tüm authenticator app'leri bu formatı okur
        $qr_url = $google2fa->getQRCodeUrl(
            $site_name,
            $user->user_email,
            $secret
        );

        // QR image — BaconQrCode v3 API ile üret
        $qr_image = '';
        try {
            $qr_image = $this->generateQrCodeImage( $qr_url );
        } catch ( \Throwable $e ) {
            // QR image üretilemedi — JS fallback QR URL'i gösterir
            error_log( '[TOTP] QR image error: ' . $e->getMessage() );
        }

        $response['data'] = [
            'secret'   => $secret,
            'qr_url'   => $qr_url,
            'qr_image' => $qr_image,
        ];

        return $response;
    }

    /**
     * BaconQrCode v3 ile QR code PNG üret → base64 data URI döndür.
     * GD veya Imagick backend'i otomatik seçer.
     *
     * @throws \Throwable Hata durumunda
     */
    private function generateQrCodeImage( string $data, int $size = 200 ): string
    {
        // BaconQrCode v3 yüklü mü?
        if ( ! class_exists( \BaconQrCode\Writer::class ) ) {
            return '';
        }

        $renderer_style = new \BaconQrCode\Renderer\RendererStyle\RendererStyle( $size );

        // Backend: GD (daha yaygın) veya Imagick
        if ( class_exists( \BaconQrCode\Renderer\Image\SvgImageBackEnd::class ) ) {
            // SVG — en geniş uyumluluk, base64 data URI olarak embed edilebilir
            $backend  = new \BaconQrCode\Renderer\Image\SvgImageBackEnd();
            $renderer = new \BaconQrCode\Renderer\ImageRenderer( $renderer_style, $backend );
            $writer   = new \BaconQrCode\Writer( $renderer );
            $svg      = $writer->writeString( $data );
            return 'data:image/svg+xml;base64,' . base64_encode( $svg );
        }

        if ( class_exists( \BaconQrCode\Renderer\Image\ImagickImageBackEnd::class ) && extension_loaded( 'imagick' ) ) {
            $backend  = new \BaconQrCode\Renderer\Image\ImagickImageBackEnd();
            $renderer = new \BaconQrCode\Renderer\ImageRenderer( $renderer_style, $backend );
            $writer   = new \BaconQrCode\Writer( $renderer );
            $png      = $writer->writeString( $data );
            return 'data:image/png;base64,' . base64_encode( $png );
        }

        // PNG via GD (BaconQrCode v3 GD backend yoksa SVG fallback)
        if ( class_exists( \BaconQrCode\Renderer\Image\EpsImageBackEnd::class ) ) {
            $backend  = new \BaconQrCode\Renderer\Image\EpsImageBackEnd();
            $renderer = new \BaconQrCode\Renderer\ImageRenderer( $renderer_style, $backend );
            $writer   = new \BaconQrCode\Writer( $renderer );
            $eps      = $writer->writeString( $data );
            return 'data:application/postscript;base64,' . base64_encode( $eps );
        }

        return '';
    }

    /**
     * TOTP kodunu doğrula ve aktif et (ilk kurulum).
     * Kullanıcı QR'ı taradı ve uygulamasındaki kodu girdi.
     */
    public function activateTOTP( int $user_id, string $code ): array
    {
        $response = $this->response();
        $secret   = get_user_meta( $user_id, 'totp_secret', true );

        if ( empty( $secret ) ) {
            $response['error']   = true;
            $response['message'] = 'TOTP not set up. Please generate a new secret first.';
            return $response;
        }

        if ( ! $this->verifyTOTPCode( $secret, $code ) ) {
            $response['error']   = true;
            $response['message'] = 'Invalid code. Please check your authenticator app.';
            return $response;
        }

        update_user_meta( $user_id, 'totp_enabled', true );
        $response['message'] = 'Two-factor authentication activated successfully.';
        return $response;
    }

    /**
     * TOTP devre dışı bırak.
     */
    public function disableTOTP( int $user_id ): void
    {
        delete_user_meta( $user_id, 'totp_secret' );
        update_user_meta( $user_id, 'totp_enabled', false );
    }

    /**
     * TOTP kodu doğrula + login tamamla.
     *
     * @param array $vars ['pending_token', 'totp_code', 'remember', 'redirect_url']
     */
    public function verify2FATOTP( array $vars = [] ): array
    {
        $response = $this->response();

        $token   = sanitize_text_field( $vars['pending_token'] ?? '' );
        $user_id = $this->getPending2FAUserId( $token );

        if ( ! $user_id ) {
            $response['error']   = true;
            $response['message'] = 'Session expired. Please log in again.';
            return $response;
        }

        $secret = get_user_meta( $user_id, 'totp_secret', true );

        if ( empty( $secret ) || ! get_user_meta( $user_id, 'totp_enabled', true ) ) {
            $response['error']   = true;
            $response['message'] = 'TOTP is not configured for this account.';
            return $response;
        }

        $code = sanitize_text_field( $vars['totp_code'] ?? '' );

        if ( ! $this->verifyTOTPCode( $secret, $code ) ) {
            $response['error']   = true;
            $response['message'] = 'Invalid authentication code.';
            return $response;
        }

        $this->clearPending2FA( $token );
        return $this->completLogin( $user_id, $vars );
    }

    /**
     * TOTP kodunu doğrula (window: ±1 periyot tolerans).
     */
    private function verifyTOTPCode( string $secret, string $code ): bool
    {
        if ( ! class_exists( \PragmaRX\Google2FAQRCode\Google2FA::class ) ) return false;

        try {
            $google2fa = new \PragmaRX\Google2FAQRCode\Google2FA();
            return (bool) $google2fa->verifyKey( $secret, $code, 1 );
        } catch ( \Throwable $e ) {
            return false;
        }
    }

    // ─── Login Tamamlama ─────────────────────────────────────────────────────

    /**
     * 2FA doğrulandıktan sonra gerçek login'i tamamla.
     * Trusted device cookie'sini set eder.
     */
    private function completLogin( int $user_id, array $vars = [] ): array
    {
        $response = $this->response();
        $remember = ! empty( $vars['remember'] );

        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id, $remember );
        $this->refreshUser( $user_id );

        // Trusted device kaydet
        $days = (int) MembershipSettings::getSetting( 'trusted_device_days', 30 );
        if ( $days > 0 ) {
            $this->setTrustedDevice( $user_id );
        }

        $response['message'] = 'Login successful.';

        if ( ! empty( $vars['redirect_url'] ) ) {
            $response['redirect'] = $vars['redirect_url'];
        } elseif ( \Data::has( 'base_urls.logged_url' ) ) {
            $response['redirect'] = \Data::get( 'base_urls.logged_url' );
        } else {
            $response['redirect'] = $this->getEndpointUrl( 'profile' );
        }

        return $response;
    }
}
