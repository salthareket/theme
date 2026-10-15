<?php

namespace SaltHareket\Sms;

/**
 * SmsApp — SMS app dependency facade.
 *
 * Başka app'ler SMS kullanmadan önce bunu çağırır.
 * SMS disabled ise WP_Error döner; caller bunu admin notice'a çevirir.
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-06-22
 *     - Add: isAvailable() — ENABLE_SMS ve provider config kontrolü
 *     - Add: require() — WP_Error döndüren dependency check
 *     - Add: dependency() — admin notice için hazır data array'i
 *
 * @package SaltHareket\Sms
 *
 * How to use (PHP):
 *   $dep = SmsApp::require();
 *   if ( is_wp_error( $dep ) ) {
 *       // AJAX context'te:
 *       wp_send_json_error([
 *           'message'     => $dep->get_error_message(),
 *           'dependency'  => $dep->get_error_data(),
 *       ], 422);
 *   }
 *   SmsManager::driver()->send( [...], $msg );
 *
 * How to use (JS):
 *   if ( res.data?.dependency ) {
 *       shShowDependencyNotice( res.data.dependency );
 *   }
 */
class SmsApp
{
    // ─── Public API ──────────────────────────────────────────────────────────

    /**
     * SMS app aktif ve kullanılabilir mi?
     * ENABLE_SMS define'ını VE SmsSettings provider config'ini kontrol eder.
     */
    public static function isAvailable(): bool
    {
        if ( ! defined( 'ENABLE_SMS' ) || ! ENABLE_SMS ) {
            return false;
        }

        // SmsManager yüklü mü?
        if ( ! class_exists( SmsManager::class ) ) {
            return false;
        }

        // Provider config dolu mu? — Her provider kendi sub-array içinde credentials tutar.
        $settings = SmsSettings::get();
        $provider = $settings['provider'] ?? '';

        if ( empty( $provider ) ) {
            return false;
        }

        // Provider credential'larından en az bir tanesi dolu mu?
        $config = $settings[ $provider ] ?? [];
        $has_credentials = ! empty( array_filter( array_values( $config ), fn( $v ) => $v !== '' ) );

        return $has_credentials;
    }

    /**
     * SMS dependency'yi doğrula.
     * Kullanılabilir ise null döner.
     * Kullanılamaz ise WP_Error döner — error data içinde admin notice bilgisi var.
     *
     * @return \WP_Error|null
     */
    public static function require(): ?\WP_Error
    {
        if ( self::isAvailable() ) {
            return null;
        }

        return new \WP_Error(
            'sms_unavailable',
            __( 'SMS is currently disabled or not configured.', 'salthareket' ),
            self::dependency()
        );
    }

    /**
     * Admin notice için hazır dependency data döndür.
     * JS tarafı bu array'i shShowDependencyNotice() fonksiyonuna geçirir.
     *
     * @return array{
     *     app_id: string,
     *     label: string,
     *     reason: string,
     *     admin_url: string,
     *     admin_label: string,
     * }
     */
    public static function dependency(): array
    {
        $reason = ! defined( 'ENABLE_SMS' ) || ! ENABLE_SMS
            ? __( 'SMS app is disabled.', 'salthareket' )
            : __( 'SMS provider is not configured.', 'salthareket' );

        return [
            'app_id'      => 'sms',
            'label'       => 'SMS',
            'reason'      => $reason,
            'admin_url'   => admin_url( 'admin.php?page=sh-sms' ),
            'admin_label' => __( 'Go to SMS Settings', 'salthareket' ),
        ];
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * AJAX handler'larında kullanım kolaylığı için:
     * Dependency check yap, fail ise wp_send_json_error() ile çık.
     *
     * @example
     *   SmsApp::requireOrFail();  // disabled ise die() + JSON error
     *   SmsManager::driver()->send([...], $msg);
     */
    public static function requireOrFail(): void
    {
        $dep = self::require();
        if ( $dep === null ) return;

        wp_send_json_error( [
            'message'    => $dep->get_error_message(),
            'dependency' => $dep->get_error_data( 'sms_unavailable' ),
        ], 422 );
    }
}
