<?php

namespace SaltHareket\Notifications\Carriers;

use SaltHareket\Notifications\NotifyPayload;
use SaltHareket\Notifications\NotifyResult;

/**
 * AlertCarrier
 * In-app alert — wp_notifications tablosuna yazar.
 * Frontend polling veya SSE ile çekilir.
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-05-04 — Initial release
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // Direkt kullanım (genellikle NotifyDispatcher üzerinden çağrılır)
 * $carrier = new AlertCarrier();
 * $result  = $carrier->handle($payload);
 *
 * // Event tanımında alert config
 * NotifyEvent::define('order/new', [
 *     'channels' => ['alert'],
 *     'alert'    => ['body' => 'New order from {{ data.user.name }}'],
 * ]);
 *
 * // Frontend'de okunmamış sayısını al
 * $notif = new Notifications();
 * $count = $notif->get_unseen_count();
 *
 * // Okunmamış alert'leri al (ve okundu işaretle)
 * $alerts = $notif->get_unseen_alerts();
 *
 * ──────────────────────────────────────────────────────────
 *
 * @example
 *   $result = $carrier->handle($payload);
 *   $result->success;    // true
 *   $result->insert_id;  // yeni satırın ID'si
 *
 * @example
 *   // Twig template'de kullanım
 *   // {{ data.user.name }} → payload'daki user objesinin name'i
 *
 * @example
 *   // DB tablosu: wp_notifications
 *   // status: 'unread' | 'read' | 'archived'
 *
 * @example
 *   // Bulk insert — birden fazla receiver için loop
 *   foreach ($receivers as $receiver_id) { ... }
 *
 * @example
 *   // post_id ve user_id data JSON kolonuna yazılır
 */
class AlertCarrier implements NotifyCarrier
{
    public function channel(): string
    {
        return 'alert';
    }

    public function handle( NotifyPayload $payload ): NotifyResult
    {
        global $wpdb;

        $table = $wpdb->prefix . 'notifications';

        $result = $wpdb->insert( $table, [
            'created_at'  => gmdate( 'Y-m-d H:i:s' ),
            'sender_id'   => $payload->sender_id,
            'receiver_id' => $payload->receiver_id,
            'event'       => $payload->event->key,
            'channel'     => 'alert',
            'message'     => $payload->rendered_body,
            'status'      => 'unread',
            'priority'    => $payload->priority->value,
            'data'        => wp_json_encode( [
                'post_id' => $payload->post_id,
                'user_id' => $payload->user_id,
            ] ),
        ] );

        if ( $result === false ) {
            return NotifyResult::fail( 'alert', $wpdb->last_error ?: 'DB insert failed' );
        }

        return NotifyResult::ok( 'alert', (int) $wpdb->insert_id );
    }
}
