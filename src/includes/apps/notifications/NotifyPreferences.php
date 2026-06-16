<?php

namespace SaltHareket\Notifications;

/**
 * NotifyPreferences
 * Kullanıcı bazlı notification tercihleri.
 * Hangi event'i, hangi kanaldan almak istediğini saklar.
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-05-04 — Initial release
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // Kullanıcı email'i kapattı
 * NotifyPreferences::set($user_id, 'order/new', 'email', false);
 *
 * // Kullanıcı tüm push'ları kapattı
 * NotifyPreferences::setAll($user_id, 'push', false);
 *
 * // Kullanıcı bu event'i tamamen kapattı
 * NotifyPreferences::disableEvent($user_id, 'promo/flash');
 *
 * // Kontrol — dispatcher bunu otomatik yapar
 * NotifyPreferences::isEnabled($user_id, 'order/new', 'email'); // true/false
 *
 * // Kullanıcının tüm tercihlerini al
 * $prefs = NotifyPreferences::getAll($user_id);
 *
 * // Quiet hours ayarla (gece 23:00 - 08:00 arası bildirim yok)
 * NotifyPreferences::setQuietHours($user_id, '23:00', '08:00', 'Europe/Istanbul');
 *
 * // Quiet hours kontrolü
 * NotifyPreferences::isQuietHour($user_id); // true/false
 *
 * ──────────────────────────────────────────────────────────
 *
 * @example
 *   NotifyPreferences::set(5, 'order/new', 'email', false);
 *   NotifyPreferences::isEnabled(5, 'order/new', 'email'); // false
 *
 * @example
 *   NotifyPreferences::setAll(5, 'push', false); // tüm push'ları kapat
 *
 * @example
 *   NotifyPreferences::disableEvent(5, 'promo/flash'); // event'i tamamen kapat
 *
 * @example
 *   $prefs = NotifyPreferences::getAll(5);
 *   // ['order/new' => ['email' => false, 'alert' => true], ...]
 *
 * @example
 *   NotifyPreferences::setQuietHours(5, '23:00', '08:00', 'Europe/Istanbul');
 *   NotifyPreferences::isQuietHour(5); // true gece 23:00-08:00 arası
 */
class NotifyPreferences
{
    private const QUIET_HOURS_OPTION = 'sh_notify_quiet_hours_';

    /**
     * Belirli event+channel için tercihi kaydet.
     */
    public static function set( int $user_id, string $event, string $channel, bool $enabled ): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'notify_preferences';

        $wpdb->replace( $table, [
            'user_id'    => $user_id,
            'event'      => $event,
            'channel'    => $channel,
            'enabled'    => $enabled ? 1 : 0,
            'updated_at' => gmdate( 'Y-m-d H:i:s' ),
        ] );

        wp_cache_delete( "sh_notify_prefs_{$user_id}", 'sh_notify' );
    }

    /**
     * Tüm event'ler için belirli channel'ı aç/kapat.
     */
    public static function setAll( int $user_id, string $channel, bool $enabled ): void
    {
        foreach ( NotifyRegistry::keys() as $event_key ) {
            self::set( $user_id, $event_key, $channel, $enabled );
        }
        // set() her çağrıda cache'i temizliyor, ama toplu işlemde bir kez daha garantile
        wp_cache_delete( "sh_notify_prefs_{$user_id}", 'sh_notify' );
    }

    /**
     * Bir event'i tüm kanallardan kapat.
     */
    public static function disableEvent( int $user_id, string $event ): void
    {
        $ev = NotifyRegistry::get( $event );
        if ( ! $ev ) return;

        foreach ( $ev->channels as $channel ) {
            self::set( $user_id, $event, $channel, false );
        }
        wp_cache_delete( "sh_notify_prefs_{$user_id}", 'sh_notify' );
    }

    /**
     * Belirli event+channel için tercih aktif mi?
     * Default: true (kayıt yoksa aktif kabul edilir).
     */
    public static function isEnabled( int $user_id, string $event, string $channel ): bool
    {
        $prefs = self::getAll( $user_id );
        return (bool) ( $prefs[$event][$channel] ?? true );
    }

    /**
     * Kullanıcının tüm tercihlerini al.
     * WP object cache ile korumalı.
     *
     * @return array<string, array<string, bool>>
     */
    public static function getAll( int $user_id ): array
    {
        $cache_key = "sh_notify_prefs_{$user_id}";
        $cached    = wp_cache_get( $cache_key, 'sh_notify' );
        if ( $cached !== false ) return $cached;

        global $wpdb;
        $table = $wpdb->prefix . 'notify_preferences';
        $rows  = $wpdb->get_results( $wpdb->prepare(
            "SELECT event, channel, enabled FROM {$table} WHERE user_id = %d",
            $user_id
        ) );

        $prefs = [];
        foreach ( $rows as $row ) {
            $prefs[$row->event][$row->channel] = (bool) $row->enabled;
        }

        wp_cache_set( $cache_key, $prefs, 'sh_notify', 300 );
        return $prefs;
    }

    // ─── QUIET HOURS ─────────────────────────────────────────────────────────

    /**
     * Sessiz saatleri ayarla.
     *
     * @param int    $user_id
     * @param string $start     'HH:MM' formatında (örn: '23:00')
     * @param string $end       'HH:MM' formatında (örn: '08:00')
     * @param string $timezone  PHP timezone string (örn: 'Europe/Istanbul')
     */
    public static function setQuietHours( int $user_id, string $start, string $end, string $timezone = '' ): void
    {
        update_user_meta( $user_id, 'sh_notify_quiet_hours', [
            'start'    => $start,
            'end'      => $end,
            'timezone' => $timezone ?: wp_timezone_string(),
            'enabled'  => true,
        ] );
    }

    /**
     * Sessiz saatleri kaldır.
     */
    public static function clearQuietHours( int $user_id ): void
    {
        delete_user_meta( $user_id, 'sh_notify_quiet_hours' );
    }

    /**
     * Şu an kullanıcı için sessiz saat mi?
     */
    public static function isQuietHour( int $user_id ): bool
    {
        $config = get_user_meta( $user_id, 'sh_notify_quiet_hours', true );
        if ( empty( $config['enabled'] ) ) return false;

        try {
            $tz  = new \DateTimeZone( $config['timezone'] ?? wp_timezone_string() );
            $now = new \DateTime( 'now', $tz );

            $start = \DateTime::createFromFormat( 'H:i', $config['start'], $tz );
            $end   = \DateTime::createFromFormat( 'H:i', $config['end'],   $tz );

            if ( ! $start || ! $end ) return false;

            $now_ts   = (int) $now->format( 'Hi' );
            $start_ts = (int) $start->format( 'Hi' );
            $end_ts   = (int) $end->format( 'Hi' );

            // Gece yarısını geçen aralık (örn: 23:00 - 08:00)
            if ( $start_ts > $end_ts ) {
                return $now_ts >= $start_ts || $now_ts < $end_ts;
            }

            return $now_ts >= $start_ts && $now_ts < $end_ts;

        } catch ( \Throwable ) {
            return false;
        }
    }
}
