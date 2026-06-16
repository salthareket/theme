<?php

namespace SaltHareket\DownloadLog\Concerns;

/**
 * HandlesLeadCapture
 *
 * CF7 form entegrasyonu — lead data al, cookie yaz, download başlat.
 * Lead data wp_guests tablosuna yazılır — her log row'unda tekrar tutulmaz.
 * Email/name index detection extractLeadIndex() ile yapılır.
 *
 * @version 2.0.0
 * @changelog
 *   2.0.0 - 2026-05-13
 *     - Add: extractLeadIndex() — HandlesLog'dan buraya taşındı
 *     - Change: lead data artık wp_guests'e yazılıyor (GuestIdentity::updateProfile)
 *     - Remove: lead_data log tablosuna yazılmıyor
 *   1.1.0 - 2026-05-13
 *     - Change: extractLeadData() artık raw CF7 POST verisini döndürür (field map yok)
 *     - Remove: getFieldMap() — field map settings kaldırıldı
 *     - Add: sanitizePostedData() — CF7 internal field'larını temizler
 *   1.0.0 - 2026-05-12 — Initial release
 *
 * @package SaltHareket\DownloadLog\Concerns
 */
trait HandlesLeadCapture {

    // ─── LEAD DATA ───────────────────────────────────────

    /**
     * CF7 submission data'sından raw lead data üret.
     * Tüm field'lar olduğu gibi saklanır — form değişse bile çalışır.
     * CF7 internal field'ları (_wpcf7*, _wpnonce, action vs.) temizlenir.
     *
     * @param  array $posted_data  CF7 $_POST data
     * @return array  Raw field => value map
     */
    public static function extractLeadData( array $posted_data ): array {
        return self::sanitizePostedData( $posted_data );
    }

    /**
     * Raw CF7 POST verisinden email ve name'i otomatik tespit et.
     * wp_guests profiline yazılacak index alanları için kullanılır.
     *
     * EMAIL: field adında 'email' veya 'eposta' veya 'e-posta' geçiyorsa
     *        + filter_var ile format doğrulaması
     *
     * NAME:  Önce exact match listesi, sonra suffix match (entity kelimeleri hariç).
     *
     * @param  array  $data  Raw CF7 posted data
     * @return array  ['email' => '...', 'name' => '...']
     */
    public static function extractLeadIndex( array $data ): array {
        $email = '';
        $name  = '';

        // ── EMAIL ─────────────────────────────────────────
        $email_keywords = [ 'email', 'eposta', 'e-posta', 'e_posta', 'mail' ];

        foreach ( $data as $field => $value ) {
            if ( ! is_string( $value ) || empty( $value ) ) continue;
            $field_lower = strtolower( $field );

            foreach ( $email_keywords as $kw ) {
                if ( strpos( $field_lower, $kw ) !== false ) {
                    $clean = sanitize_email( trim( $value ) );
                    if ( is_email( $clean ) ) {
                        $email = $clean;
                        break 2;
                    }
                }
            }
        }

        // ── NAME ──────────────────────────────────────────
        $name_exact = [
            'your-name', 'yourname', 'name', 'isim', 'ad', 'adi', 'ad-soyad',
            'adsoyad', 'full-name', 'fullname', 'first-name', 'firstname',
            'ad_soyad', 'isim-soyisim', 'isim_soyisim',
            'kullanici-adi', 'kullanici_adi',
        ];

        foreach ( $name_exact as $exact ) {
            if ( isset( $data[ $exact ] ) && is_string( $data[ $exact ] ) && ! empty( $data[ $exact ] ) ) {
                $name = sanitize_text_field( trim( $data[ $exact ] ) );
                break;
            }
        }

        if ( ! $name ) {
            $entity_prefixes = [
                'company', 'sirket', 'firma', 'partner', 'business', 'brand',
                'product', 'urun', 'site', 'web', 'project', 'proje', 'org',
                'organization', 'kurum', 'store', 'magaza',
            ];

            foreach ( $data as $field => $value ) {
                if ( ! is_string( $value ) || empty( $value ) ) continue;
                $field_lower = strtolower( $field );

                if ( ! preg_match( '/[-_]name$/', $field_lower ) ) continue;

                $has_entity = false;
                foreach ( $entity_prefixes as $prefix ) {
                    if ( strpos( $field_lower, $prefix ) !== false ) {
                        $has_entity = true;
                        break;
                    }
                }
                if ( $has_entity ) continue;

                $name = sanitize_text_field( trim( $value ) );
                break;
            }
        }

        if ( ! $name ) {
            $name_keywords   = [ 'isim', 'adınız', 'adiniz' ];
            $entity_prefixes = [ 'company', 'sirket', 'firma', 'partner', 'business', 'brand', 'product', 'site' ];

            foreach ( $data as $field => $value ) {
                if ( ! is_string( $value ) || empty( $value ) ) continue;
                $field_lower = strtolower( $field );

                $has_kw = false;
                foreach ( $name_keywords as $kw ) {
                    if ( strpos( $field_lower, $kw ) !== false ) { $has_kw = true; break; }
                }
                if ( ! $has_kw ) continue;

                $has_entity = false;
                foreach ( $entity_prefixes as $prefix ) {
                    if ( strpos( $field_lower, $prefix ) !== false ) { $has_entity = true; break; }
                }
                if ( $has_entity ) continue;

                $name = sanitize_text_field( trim( $value ) );
                break;
            }
        }

        return [ 'email' => $email, 'name' => $name ];
    }

    /**
     * CF7 internal field'larını ve WP nonce'larını temizle.
     * Geriye sadece form field'ları kalır.
     */
    private static function sanitizePostedData( array $data ): array {
        $skip_prefixes = [ '_wpcf7', '_wpnonce', 'action', '_wp_http_referer' ];
        $result        = [];

        foreach ( $data as $key => $value ) {
            $key = sanitize_key( $key );
            if ( ! $key ) continue;

            // CF7 internal field'larını atla
            $skip = false;
            foreach ( $skip_prefixes as $prefix ) {
                if ( strpos( $key, $prefix ) === 0 ) { $skip = true; break; }
            }
            if ( $skip ) continue;

            // Değeri sanitize et
            if ( is_array( $value ) ) {
                $result[ $key ] = array_map( 'sanitize_text_field', $value );
            } else {
                $result[ $key ] = sanitize_text_field( (string) $value );
            }
        }

        return $result;
    }

    /**
     * Lead data'yı doğrula.
     * En azından bir field dolu olmalı.
     *
     * @return true|string  true = geçerli, string = hata mesajı
     */
    public static function validateLeadData( array $lead_data ) {
        if ( empty( $lead_data ) ) {
            return 'missing_required_fields';
        }
        // En az bir non-empty değer olmalı
        foreach ( $lead_data as $v ) {
            if ( ! empty( $v ) ) return true;
        }
        return 'missing_required_fields';
    }

    // ─── CF7 FORM HTML ───────────────────────────────────

    /**
     * CF7 formunu modal için render et.
     * Shortcode yerine direkt PHP ile — AJAX response'a gömülür.
     *
     * @param int $form_id  CF7 post ID
     * @return string  HTML
     */
    public static function renderCF7Form( int $form_id ): string {
        if ( ! class_exists( 'WPCF7_ContactForm' ) ) return '';
        if ( $form_id < 1 ) return '';

        $form = \WPCF7_ContactForm::get_instance( $form_id );
        if ( ! $form ) return '';

        // CF7 assets'lerini enqueue et (modal'da lazım)
        if ( function_exists( 'wpcf7_enqueue_scripts' ) ) {
            wpcf7_enqueue_scripts();
        }
        if ( function_exists( 'wpcf7_enqueue_styles' ) ) {
            wpcf7_enqueue_styles();
        }

        ob_start();
        echo do_shortcode( '[contact-form-7 id="' . $form_id . '"]' );
        return ob_get_clean();
    }

    // ─── NOTIFICATION ────────────────────────────────────

    /**
     * Lead capture sonrası notification gönder.
     * Notifications app entegrasyonu.
     */
    public static function fireLeadNotification( int $log_id, array $lead_data ): void {
        if ( ! class_exists( 'Notifications' ) ) return;

        try {
            \Notifications::fire( 'lead-captured', [
                'log_id'    => $log_id,
                'lead_data' => $lead_data,
            ] );
        } catch ( \Throwable $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[DownloadLog] Lead notification error: ' . $e->getMessage() );
            }
        }
    }
}
