<?php

namespace SaltHareket\Notifications\Admin;

use SaltHareket\Notifications\Carriers\WebPushCarrier;
use SaltHareket\Notifications\NotifyPreferences;

/**
 * NotificationsAjax
 * Frontend AJAX handler'ları.
 * Push subscribe/unsubscribe, mark read, preferences, unseen count.
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-05-04 — Initial release
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // Otomatik register edilir — manuel çağrı gerekmez
 * NotificationsAjax::register();
 *
 * // Frontend JS'de kullanım:
 * // wp_ajax_sh_notify_push_subscribe
 * // wp_ajax_sh_notify_push_unsubscribe
 * // wp_ajax_sh_notify_mark_read
 * // wp_ajax_sh_notify_set_preference
 * // wp_ajax_sh_notify_unseen_count
 * // wp_ajax_nopriv_sh_notify_unseen_count (guest için 0 döner)
 *
 * ──────────────────────────────────────────────────────────
 */
class NotificationsAjax
{
    public static function register(): void
    {
        $actions = [
            'sh_notify_push_subscribe'   => [ self::class, 'pushSubscribe' ],
            'sh_notify_push_unsubscribe' => [ self::class, 'pushUnsubscribe' ],
            'sh_notify_push_closed'      => [ self::class, 'pushClosed' ],
            'sh_notify_mark_read'        => [ self::class, 'markRead' ],
            'sh_notify_mark_all_read'    => [ self::class, 'markAllRead' ],
            'sh_notify_set_preference'   => [ self::class, 'setPreference' ],
            'sh_notify_unseen_count'     => [ self::class, 'unseenCount' ],
        ];

        foreach ( $actions as $action => $callback ) {
            add_action( 'wp_ajax_' . $action, $callback );
        }

        // Unseen count guest için de çalışır (0 döner)
        add_action( 'wp_ajax_nopriv_sh_notify_unseen_count', [ self::class, 'unseenCount' ] );
        // Push closed — guest için de (sendBeacon nonce göndermez)
        add_action( 'wp_ajax_nopriv_sh_notify_push_closed', [ self::class, 'pushClosed' ] );
    }

    // ─── PUSH ────────────────────────────────────────────────────────────────

    public static function pushSubscribe(): void
    {
        check_ajax_referer( 'sh_notify_push' );
        $user_id = get_current_user_id();
        if ( ! $user_id ) wp_send_json_error( 'Not logged in' );

        $subscription = sanitize_text_field( wp_unslash( $_POST['subscription'] ?? '' ) );
        if ( empty( $subscription ) ) wp_send_json_error( 'No subscription data' );

        $result = WebPushCarrier::saveSubscription( $user_id, $subscription );
        $result ? wp_send_json_success() : wp_send_json_error( 'Save failed' );
    }

    public static function pushUnsubscribe(): void
    {
        check_ajax_referer( 'sh_notify_push' );
        $user_id  = get_current_user_id();
        $endpoint = sanitize_text_field( wp_unslash( $_POST['endpoint'] ?? '' ) );

        if ( $user_id && $endpoint ) {
            WebPushCarrier::deleteSubscription( $user_id, $endpoint );
        }

        wp_send_json_success();
    }

    /**
     * Push bildirimi kapatıldı — analytics için log.
     * sendBeacon ile gelir, nonce yok, sadece tag kaydedilir.
     */
    public static function pushClosed(): void
    {
        $tag = sanitize_text_field( $_POST['tag'] ?? '' );
        // İleride analytics tablosuna yazılabilir
        // Şimdilik sessizce başarı döndür
        wp_send_json_success();
    }

    // ─── READ ────────────────────────────────────────────────────────────────

    public static function markRead(): void
    {
        check_ajax_referer( 'sh_notify_nonce' );
        $user_id = get_current_user_id();
        if ( ! $user_id ) wp_send_json_error( 'Not logged in' );

        $ids = array_map( 'intval', (array) ( $_POST['ids'] ?? [] ) );
        if ( empty( $ids ) ) wp_send_json_error( 'No IDs' );

        global $wpdb;
        $table   = $wpdb->prefix . 'notifications';
        $ids_sql = implode( ',', $ids );

        // Sadece bu kullanıcının bildirimlerini güncelle
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$table} SET status = 'read' WHERE id IN ({$ids_sql}) AND receiver_id = %d", // phpcs:ignore
            $user_id
        ) );

        wp_send_json_success();
    }

    public static function markAllRead(): void
    {
        check_ajax_referer( 'sh_notify_nonce' );
        $user_id = get_current_user_id();
        if ( ! $user_id ) wp_send_json_error( 'Not logged in' );

        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'notifications',
            [ 'status' => 'read' ],
            [ 'receiver_id' => $user_id, 'status' => 'unread' ]
        );

        wp_send_json_success();
    }

    // ─── PREFERENCES ─────────────────────────────────────────────────────────

    public static function setPreference(): void
    {
        check_ajax_referer( 'sh_notify_nonce' );
        $user_id = get_current_user_id();
        if ( ! $user_id ) wp_send_json_error( 'Not logged in' );

        $event   = sanitize_text_field( $_POST['event']   ?? '' );
        $channel = sanitize_text_field( $_POST['channel'] ?? '' );
        $enabled = (bool) ( $_POST['enabled'] ?? true );

        if ( empty( $event ) || empty( $channel ) ) wp_send_json_error( 'Missing params' );

        NotifyPreferences::set( $user_id, $event, $channel, $enabled );
        wp_send_json_success();
    }

    // ─── COUNT ───────────────────────────────────────────────────────────────

    public static function unseenCount(): void
    {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            wp_send_json_success( [ 'count' => 0 ] );
        }

        global $wpdb;
        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}notifications WHERE receiver_id = %d AND status = 'unread'",
            $user_id
        ) );

        wp_send_json_success( [ 'count' => $count ] );
    }
}
