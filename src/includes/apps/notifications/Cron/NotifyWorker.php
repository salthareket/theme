<?php

namespace SaltHareket\Notifications\Cron;

use SaltHareket\Notifications\NotifyDispatcher;

/**
 * NotifyWorker
 * WP Cron tabanlı async queue worker.
 * NotifyDispatcher::queue() ile eklenen işleri çalıştırır.
 * Eski log kayıtlarını temizler.
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-05-04 — Initial release
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // Otomatik register edilir
 * NotifyWorker::register();
 *
 * // Async gönderim
 * Notifications::queue('order/new', $data);
 * // → WP Cron 'sh_notify_process_queued' hook'unu tetikler
 * // → NotifyWorker::processQueued() çalışır
 *
 * // Log temizleme (30 günden eski kayıtlar)
 * // Her gün otomatik çalışır
 *
 * ──────────────────────────────────────────────────────────
 */
class NotifyWorker
{
    public static function register(): void
    {
        // Async queue processor
        add_action( 'sh_notify_process_queued', [ self::class, 'processQueued' ], 10, 2 );

        // Günlük log temizleme
        if ( ! wp_next_scheduled( 'sh_notify_cleanup' ) ) {
            wp_schedule_event( time(), 'daily', 'sh_notify_cleanup' );
        }
        add_action( 'sh_notify_cleanup', [ self::class, 'cleanup' ] );
    }

    /**
     * Kuyruktaki notification'ı işle.
     *
     * @param string $event_key
     * @param array  $data
     */
    public static function processQueued( string $event_key, array $data ): void
    {
        NotifyDispatcher::fire( $event_key, $data );
    }

    /**
     * 30 günden eski log kayıtlarını sil.
     */
    public static function cleanup(): void
    {
        global $wpdb;

        // Configurable retention — default 30 gun
        $retention = max( 1, (int) get_option( 'sh_notify_log_retention', 30 ) );

        // Log tablosu
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}notify_log WHERE sent_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $retention
        ) );

        // Okunmuş ve 90 günden eski notification'lar
        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}notifications WHERE status = 'read' AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
        );
    }
}
