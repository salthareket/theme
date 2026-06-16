<?php

namespace SaltHareket\Notifications\Carriers;

/**
 * SmsEncoding — SMS mesaj encoding yardımcısı.
 *
 * GSM-7 karakter seti dışında herhangi bir karakter içeren mesajlar
 * unicode (UCS-2) encoding gerektirir. Bu sınıf bunu tespit eder.
 *
 * GSM-7 dışı örnekler:
 *   - Türkçe: ş, ğ, ü, ö, ç, ı, İ, Ğ, Ş, Ü, Ö, Ç
 *   - Danca/Norveçce: ø, æ, å, Ø, Æ, Å
 *   - Almanca: ä, ö, ü, Ä, Ö, Ü, ß
 *   - Arapça, Japonca, Çince, Kiril vb.
 *
 * NOT: Unicode SMS'ler 160 yerine 70 karakter/mesaj sığdırır.
 * Uzun mesajlar otomatik bölünür (çoğu provider handle eder).
 *
 * @version 1.0.0
 */
class SmsEncoding
{
    /**
     * GSM-7 temel karakter seti (extended dahil).
     * Bu karakterlerin dışında herhangi bir karakter varsa unicode gerekir.
     */
    private const GSM7_CHARS = '@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞ\x1BÆæßÉ !"#¤%&\'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà';

    /**
     * GSM-7 extended karakter seti (escape ile gönderilir).
     */
    private const GSM7_EXTENDED = '|^€{}[~]\\';

    /**
     * Mesajın unicode encoding gerektirip gerektirmediğini kontrol eder.
     *
     * Türkçe, Danca, Almanca, Arapça, Japonca vb. tüm GSM-7 dışı
     * karakterler için true döner.
     *
     * @param  string $text  Kontrol edilecek mesaj
     * @return bool          true = unicode gerekli, false = GSM-7 yeterli
     *
     * @example
     *   SmsEncoding::requiresUnicode('Hello World');  // false
     *   SmsEncoding::requiresUnicode('Mesajım');      // true (ı harfi)
     *   SmsEncoding::requiresUnicode('Hej verden');   // false (Danca ama GSM-7'de var)
     *   SmsEncoding::requiresUnicode('Hej Søren');    // true (ø GSM-7'de yok)
     *   SmsEncoding::requiresUnicode('مرحبا');        // true (Arapça)
     */
    public static function requiresUnicode( string $text ): bool
    {
        if ( $text === '' ) return false;

        $allowed = self::GSM7_CHARS . self::GSM7_EXTENDED;

        // Her karakteri kontrol et
        $len = mb_strlen( $text, 'UTF-8' );
        for ( $i = 0; $i < $len; $i++ ) {
            $char = mb_substr( $text, $i, 1, 'UTF-8' );
            if ( mb_strpos( $allowed, $char ) === false ) {
                return true; // GSM-7 dışı karakter bulundu
            }
        }

        return false;
    }

    /**
     * Mesajın encoding türünü string olarak döner.
     * Provider'ların API parametrelerine doğrudan geçilebilir.
     *
     * @param  string $text
     * @return string  'unicode' veya 'text'
     *
     * @example
     *   SmsEncoding::type('Hello');    // 'text'
     *   SmsEncoding::type('Mesajım'); // 'unicode'
     */
    public static function type( string $text ): string
    {
        return self::requiresUnicode( $text ) ? 'unicode' : 'text';
    }

    /**
     * Netgsm encoding parametresini döner.
     * Netgsm XML API'de encoding=TR Türkçe için, encoding=default diğerleri için.
     * Ancak genel unicode için encoding=TR yeterli değil — tüm unicode için
     * Netgsm'in desteklediği encoding parametresini döner.
     *
     * @param  string $text
     * @return string  'TR' (Türkçe dahil tüm unicode için Netgsm'in kabul ettiği değer)
     *                 '' (encoding parametresi eklenmez = GSM-7)
     *
     * @example
     *   SmsEncoding::netgsmEncoding('Hello');    // ''
     *   SmsEncoding::netgsmEncoding('Mesajım'); // 'TR'
     *   SmsEncoding::netgsmEncoding('مرحبا');   // 'TR'
     */
    public static function netgsmEncoding( string $text ): string
    {
        return self::requiresUnicode( $text ) ? 'TR' : '';
    }
}
