<?php

namespace SaltHareket\Notifications\Carriers;

/**
 * SmsContract — Her SMS provider'ının implement etmesi gereken interface.
 *
 * @version 1.0.0
 */
interface SmsContract
{
    /**
     * Provider'ın yeteneklerini döner.
     * Admin UI'da capability badge'leri göstermek için kullanılır.
     *
     * @return array{
     *   sms: bool,
     *   otp: bool,
     *   coverage: 'global'|'local',
     *   regions: string[],
     *   sender_id_type: 'alphanumeric'|'phone'|'header',
     *   sender_id_max_length: int,
     *   auth_fields: array<string, array{label: string, type: string, placeholder: string}>
     * }
     */
    public static function capabilities(): array;

    /**
     * SMS gönder.
     *
     * @param  string[] $recipients  E.164 formatında telefon numaraları ['+905551234567']
     * @param  string   $content     Mesaj içeriği
     * @param  array    $opts        Ek seçenekler (schedule_time, report_url vs.)
     * @return array{error: bool, message: string, data: mixed}
     */
    public function send( array $recipients, string $content, array $opts = [] ): array;

    /**
     * OTP kodu üret ve gönder.
     * capabilities()['otp'] = false ise SmsManager bu metodu çağırmaz.
     *
     * @param  string $recipient  E.164 formatında telefon numarası
     * @param  int    $user_id    WP user ID (OTP meta'sı buraya kaydedilir)
     * @param  string $content    OTP mesaj şablonu — {} yerine kod gelir
     * @return array{error: bool, message: string, data: mixed}
     */
    public function generateOtp( string $recipient, int $user_id, string $content = '' ): array;

    /**
     * OTP kodunu doğrula.
     *
     * @param  string $otp_id    Provider'ın döndürdüğü OTP ID
     * @param  string $otp_code  Kullanıcının girdiği kod
     * @return array{error: bool, message: string, data: mixed}
     */
    public function verifyOtp( string $otp_id, string $otp_code ): array;

    /**
     * OTP kodunu yeniden gönder.
     *
     * @param  string $otp_id   Provider'ın döndürdüğü OTP ID
     * @param  int    $user_id  WP user ID
     * @return array{error: bool, message: string, data: mixed}
     */
    public function resendOtp( string $otp_id, int $user_id ): array;

    /**
     * Bakiye sorgula.
     *
     * @return array{error: bool, message: string, data: mixed}
     */
    public function checkBalance(): array;
}
