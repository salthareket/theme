<?php

namespace SaltHareket\Sms;

/**
 * SmsEncoding — SMS mesaj encoding yardımcısı.
 *
 * GSM-7 karakter seti dışında herhangi bir karakter içeren mesajlar
 * unicode (UCS-2) encoding gerektirir.
 *
 * @version 1.1.0
 * @changelog
 *   1.1.0 - 2026-06-18
 *     - Move: notifications/Carriers → sms/
 *     - Change: Namespace SaltHareket\Notifications\Carriers → SaltHareket\Sms
 *   1.0.0 - 2026-05-04 — Initial release
 *
 * @package SaltHareket\Sms
 */
class SmsEncoding
{
    private const GSM7_CHARS    = '@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞ\x1BÆæßÉ !"#¤%&\'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà';
    private const GSM7_EXTENDED = '|^€{}[~]\\';

    /**
     * Mesajın unicode encoding gerektirip gerektirmediğini kontrol eder.
     */
    public static function requiresUnicode( string $text ): bool
    {
        if ( $text === '' ) return false;
        $allowed = self::GSM7_CHARS . self::GSM7_EXTENDED;
        $len = mb_strlen( $text, 'UTF-8' );
        for ( $i = 0; $i < $len; $i++ ) {
            $char = mb_substr( $text, $i, 1, 'UTF-8' );
            if ( mb_strpos( $allowed, $char ) === false ) return true;
        }
        return false;
    }

    /**
     * Mesajın encoding türünü string olarak döner: 'unicode' veya 'text'
     */
    public static function type( string $text ): string
    {
        return self::requiresUnicode( $text ) ? 'unicode' : 'text';
    }

    /**
     * Netgsm encoding parametresini döner.
     */
    public static function netgsmEncoding( string $text ): string
    {
        return self::requiresUnicode( $text ) ? 'TR' : '';
    }
}
