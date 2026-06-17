<?php

namespace SaltHareket\Notifications;

/**
 * NotificationsSettings
 *
 * Notifications sistemi ayarlarını yönetir.
 * wp_options'da 'sh_notifications_settings' key'inde saklanır.
 * ACF fallback desteği ile geçiş dönemi uyumu.
 *
 * variables.php'de define()'lardan ÖNCE yüklenir:
 *   require_once SH_INCLUDES_PATH . 'apps/notifications/NotificationsSettings.php';
 *   define("ENABLE_NOTIFICATIONS", NotificationsSettings::getSetting('enable_notifications'));
 *
 * @version 1.0.1
 * @changelog
 *   1.0.1 - 2026-06-16
 *     - Fix: save() — alt kanal kuralı (enable_notifications=false → sıfırla) artık
 *            sadece enable_notifications bu çağrıda açıkça false gönderilirse çalışır.
 *            Partial update'de (örn: sadece enable_web_push gönderilir) kural devreye
 *            girip enable_web_push'u sıfırlamıyordu.
 *   1.0.0 - 2026-06-16
 *     - Add: Initial release — ACF options'tan taşındı
 *     - Add: enable_notifications, enable_sms_notifications, enable_web_push
 *     - Add: ACF fallback (geçiş dönemi)
 *     - Add: save() — partial update desteği, kural bazlı bağımlılıklar
 */
class NotificationsSettings
{
    private const OPTION_KEY = 'sh_notifications_settings';

    private static ?array $cache = null;

    // ─── Defaults ────────────────────────────────────────────────────────────

    public static function defaults(): array
    {
        return [
            'enable_notifications'     => false,
            'enable_sms_notifications' => false,
            'enable_web_push'          => false,
        ];
    }

    // ─── Read ─────────────────────────────────────────────────────────────────

    public static function get(): array
    {
        if ( self::$cache !== null ) return self::$cache;

        $saved = get_option( self::OPTION_KEY, [] );
        $saved = is_array( $saved ) ? $saved : [];

        $acf = self::readAcfFallback();

        self::$cache = array_merge( self::defaults(), $acf, $saved );
        return self::$cache;
    }

    public static function getSetting( string $key, $default = null )
    {
        return self::get()[ $key ] ?? $default ?? self::defaults()[ $key ] ?? null;
    }

    // ─── Write ────────────────────────────────────────────────────────────────

    public static function save( array $data ): void
    {
        $current = self::get();

        $settings = [
            'enable_notifications'     => isset( $data['enable_notifications'] )
                ? (bool) $data['enable_notifications']
                : $current['enable_notifications'],
            'enable_sms_notifications' => isset( $data['enable_sms_notifications'] )
                ? (bool) $data['enable_sms_notifications']
                : $current['enable_sms_notifications'],
            'enable_web_push'          => isset( $data['enable_web_push'] )
                ? (bool) $data['enable_web_push']
                : $current['enable_web_push'],
        ];

        // Notifications kapalıysa alt kanallar da kapalı —
        // AMA SADECE bu çağrıda enable_notifications açıkça false gönderildiyse.
        // Partial update'de (sadece enable_web_push veya enable_sms gönderilirse)
        // enable_notifications'ı $current'tan okuruz ve sıfırlamayız.
        if ( isset( $data['enable_notifications'] ) && ! $settings['enable_notifications'] ) {
            $settings['enable_sms_notifications'] = false;
            $settings['enable_web_push']          = false;
        }

        update_option( self::OPTION_KEY, $settings, false );
        self::$cache = null;

        do_action( 'sh/notifications/saved', $settings, $current );
        foreach ( $settings as $key => $new_val ) {
            $old_val = $current[ $key ] ?? null;
            if ( $old_val !== $new_val ) {
                do_action( 'sh/notifications/setting_changed', $key, $new_val, $old_val, $settings );
            }
        }
    }

    public static function clearCache(): void
    {
        self::$cache = null;
    }

    // ─── ACF Fallback ─────────────────────────────────────────────────────────

    private static function readAcfFallback(): array
    {
        if ( ! function_exists( 'get_field' ) || ! did_action( 'acf/init' ) ) {
            return array_filter( [
                'enable_notifications'     => get_option( 'options_enable_notifications' ) ?: null,
                'enable_sms_notifications' => get_option( 'options_enable_sms_notifications' ) ?: null,
                'enable_web_push'          => get_option( 'options_enable_web_push' ) ?: null,
            ], fn( $v ) => $v !== null );
        }

        return array_filter( [
            'enable_notifications'     => get_field( 'options_enable_notifications', 'option' ) ?: null,
            'enable_sms_notifications' => get_field( 'options_enable_sms_notifications', 'option' ) ?: null,
            'enable_web_push'          => get_field( 'options_enable_web_push', 'option' ) ?: null,
        ], fn( $v ) => $v !== null );
    }
}
