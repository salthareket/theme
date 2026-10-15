<?php

namespace SaltHareket\Membership\Concerns;

/**
 * HandlesPhone
 *
 * Telefon numarası doğrulama.
 * İki mod desteklenir — öncelik libphonenumber (lokal), fallback API.
 *
 * Mod seçimi:
 *   - PHONE_VALIDATOR_KEYS sabiti tanımlı değilse → libphonenumber (composer paketi)
 *   - PHONE_VALIDATOR_KEYS tanımlıysa → önce libphonenumber dene, başarısız olursa API
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-09-09
 *     - Add: validatePhone() — libphonenumber + legacy API mod
 *     - Add: validatePhoneWithLib() — giggsey/libphonenumber-for-php
 *     - Add: validatePhoneWithApi() — RapidAPI (eski sistem, korundu)
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * $mm = MembershipManager::getInstance();
 *
 * // Doğrula:
 * $result = $mm->validatePhone([
 *     'phone'      => '5321234567',
 *     'phone_code' => '+90',
 *     'country'    => 'TR',   // ISO2 — opsiyonel ama önerilir
 * ]);
 *
 * if ($result['error']) echo $result['message'];
 * else echo $result['data']['e164']; // +905321234567
 *
 * ──────────────────────────────────────────────────────────
 */
trait HandlesPhone
{
    /**
     * Telefon numarasını doğrula.
     * Önce libphonenumber dener; kütüphane yoksa veya PHONE_VALIDATOR_KEYS tanımlıysa API'ye düşer.
     *
     * @param array $vars ['phone', 'phone_code', 'country']
     */
    public function validatePhoneNumber( array $vars = [] ): array
    {
        $response = $this->response();

        $phone      = sanitize_text_field( $vars['phone']      ?? '' );
        $phone_code = sanitize_text_field( $vars['phone_code'] ?? '' );
        $country    = strtoupper( sanitize_text_field( $vars['country'] ?? '' ) );

        if ( empty( $phone ) ) {
            $response['error']   = true;
            $response['message'] = 'Phone number is required.';
            return $response;
        }

        // Tam numara: phone_code + phone
        $full_phone = '';
        if ( ! empty( $phone_code ) ) {
            $full_phone = preg_replace( '/\s+/', '', $phone_code )
                        . preg_replace( '/^0+/', '', preg_replace( '/\D/', '', $phone ) );
        } else {
            $full_phone = $phone;
        }

        if ( strlen( preg_replace( '/\D/', '', $full_phone ) ) < 5 ) {
            $response['error']   = true;
            $response['message'] = 'Phone number is too short.';
            return $response;
        }

        // ── libphonenumber var mı? ────────────────────────────────────────────
        $lib_available = $this->isLibPhoneNumberAvailable();

        if ( $lib_available ) {
            return $this->validatePhoneWithLib( $full_phone, $country, $response );
        }

        // ── API modu (fallback veya tek seçenek) ─────────────────────────────
        if ( defined( 'PHONE_VALIDATOR_KEYS' ) && is_array( PHONE_VALIDATOR_KEYS ) ) {
            return $this->validatePhoneWithApi( $phone, $country, $phone_code, $response );
        }

        // ── Hiçbiri yoksa temel kontrol ──────────────────────────────────────
        $response['message'] = 'Phone number accepted (no validator configured).';
        $response['data']    = [ 'phone' => $full_phone, 'e164' => $full_phone ];
        return $response;
    }

    /**
     * libphonenumber-for-php ile doğrula.
     *
     * @param string $full_phone  Tam numara (+905321234567 veya 05321234567)
     * @param string $country     ISO2 ülke kodu (TR, US …) — opsiyonel
     * @param array  $response    Base response array
     */
    private function validatePhoneWithLib( string $full_phone, string $country, array $response ): array
    {
        // Autoload — tema vendor klasörü
        $autoload = dirname( __DIR__, 5 ) . '/vendor/autoload.php'; // theme/src → theme → salthareket/theme → salthareket → vendor/
        if ( ! class_exists( \libphonenumber\PhoneNumberUtil::class ) ) {
            if ( file_exists( $autoload ) ) {
                require_once $autoload;
            } else {
                // Autoload bulunamadı — soft fail
                $response['message'] = 'Phone number accepted (library not loaded).';
                $response['data']    = [ 'phone' => $full_phone, 'e164' => $full_phone ];
                return $response;
            }
        }

        try {
            $util   = \libphonenumber\PhoneNumberUtil::getInstance();
            $region = ! empty( $country ) ? $country : null;
            $parsed = $util->parse( $full_phone, $region );

            $is_valid = $region
                ? $util->isValidNumberForRegion( $parsed, $region )
                : $util->isValidNumber( $parsed );

            if ( ! $is_valid ) {
                // Daha spesifik hata
                $reason = $util->isPossibleNumberWithReason( $parsed );

                $msg_map = [
                    \libphonenumber\ValidationResult::TOO_SHORT          => 'Phone number is too short.',
                    \libphonenumber\ValidationResult::TOO_LONG           => 'Phone number is too long.',
                    \libphonenumber\ValidationResult::INVALID_LENGTH     => 'Phone number length is invalid.',
                    \libphonenumber\ValidationResult::INVALID_COUNTRY_CODE => 'Invalid country code.',
                ];

                $response['error']   = true;
                $response['message'] = $msg_map[ $reason ] ?? 'Phone number is not valid.';
                return $response;
            }

            // Başarı
            $e164          = $util->format( $parsed, \libphonenumber\PhoneNumberFormat::E164 );
            $international = $util->format( $parsed, \libphonenumber\PhoneNumberFormat::INTERNATIONAL );
            $national      = $util->format( $parsed, \libphonenumber\PhoneNumberFormat::NATIONAL );
            $type          = $util->getNumberType( $parsed );

            $type_labels = [
                \libphonenumber\PhoneNumberType::MOBILE      => 'MOBILE',
                \libphonenumber\PhoneNumberType::FIXED_LINE  => 'FIXED_LINE',
                \libphonenumber\PhoneNumberType::VOIP        => 'VOIP',
                \libphonenumber\PhoneNumberType::TOLL_FREE   => 'TOLL_FREE',
            ];

            $response['message'] = 'Phone number is valid.';
            $response['data']    = [
                'phone'         => $full_phone,
                'e164'          => $e164,
                'international' => $international,
                'national'      => $national,
                'type'          => $type_labels[ $type ] ?? 'UNKNOWN',
                'country'       => $country,
            ];

            return $response;

        } catch ( \libphonenumber\NumberParseException $e ) {
            $parse_errors = [
                \libphonenumber\NumberParseException::INVALID_COUNTRY_CODE => 'Invalid country code.',
                \libphonenumber\NumberParseException::NOT_A_NUMBER         => 'Please enter a valid phone number.',
                \libphonenumber\NumberParseException::TOO_SHORT_AFTER_IDD  => 'Phone number is too short.',
                \libphonenumber\NumberParseException::TOO_SHORT_NSN        => 'Phone number is too short.',
                \libphonenumber\NumberParseException::TOO_LONG             => 'Phone number is too long.',
            ];

            $response['error']   = true;
            $response['message'] = $parse_errors[ $e->getErrorType() ] ?? 'Phone number is not valid.';
            return $response;
        }
    }

    /**
     * RapidAPI tabanlı doğrulama — eski sistem, korundu.
     * PHONE_VALIDATOR_KEYS sabiti gerekli:
     *   define('PHONE_VALIDATOR_KEYS', [
     *       'url'           => 'https://...{phone}...{country}...',
     *       'X-RapidAPI-Key'  => '...',
     *       'X-RapidAPI-Host' => '...',
     *   ]);
     *
     * @param string $phone      Ham numara (prefix'siz)
     * @param string $country    ISO2 ülke kodu
     * @param string $phone_code Ülke telefon kodu (+90)
     * @param array  $response   Base response array
     */
    private function validatePhoneWithApi( string $phone, string $country, string $phone_code, array $response ): array
    {
        $full = ! empty( $phone_code )
            ? $phone_code . $phone
            : $phone;

        $keys = PHONE_VALIDATOR_KEYS;
        $url  = $keys['url'] ?? '';
        $url  = str_replace( '{phone}',   rawurlencode( $full ),    $url );
        $url  = str_replace( '{country}', rawurlencode( $country ), $url );

        $args = [
            'headers' => [
                'X-RapidAPI-Key'  => $keys['X-RapidAPI-Key']  ?? '',
                'X-RapidAPI-Host' => $keys['X-RapidAPI-Host'] ?? '',
            ],
            'timeout' => 8,
        ];

        $result = wp_remote_get( $url, $args );

        if ( is_wp_error( $result ) ) {
            $response['error']   = true;
            $response['message'] = 'Could not reach phone validation service.';
            return $response;
        }

        $body = wp_remote_retrieve_body( $result );
        $data = json_decode( $body, true );

        if ( isset( $data['isValidNumber'] ) ) {
            if ( ! $data['isValidNumber'] ) {
                $response['error']   = true;
                $response['message'] = 'Phone number is not valid.';
                return $response;
            }

            $response['message'] = 'Phone number is valid.';
            $response['data']    = [
                'phone'   => $full,
                'e164'    => $full,
                'type'    => $data['numberType'] ?? '',
                'carrier' => $data['carrier']    ?? '',
            ];
            return $response;
        }

        // isPossibleNumberWithReason fallback
        $response['error']   = true;
        $reason_msg_map = [
            'TOO_SHORT'      => 'Phone number is too short.',
            'INVALID_LENGTH' => 'Phone number length is invalid.',
        ];
        $reason = $data['isPossibleNumberWithReason'] ?? '';
        $response['message'] = $reason_msg_map[ $reason ] ?? 'Phone number is not valid.';
        return $response;
    }

    /**
     * libphonenumber-for-php paketinin yüklü olup olmadığını kontrol et.
     */
    private function isLibPhoneNumberAvailable(): bool
    {
        if ( class_exists( \libphonenumber\PhoneNumberUtil::class ) ) {
            return true;
        }

        $autoload = dirname( __DIR__, 5 ) . '/vendor/autoload.php';
        if ( file_exists( $autoload ) ) {
            require_once $autoload;
            return class_exists( \libphonenumber\PhoneNumberUtil::class );
        }

        return false;
    }
}
