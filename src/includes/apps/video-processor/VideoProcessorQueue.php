<?php

namespace SaltHareket\VideoProcessor;

/**
 * VideoProcessorQueue — Video işlem kuyruğunu yönetir.
 * FIFO sıra, WP option ile saklanır, WP Cron ile işlenir.
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-06-19
 *     - Add: Initial release
 *
 * @package SaltHareket\VideoProcessor
 *
 * How to use:
 *   VideoProcessorQueue::enqueue(123, ['resize_720', 'encode_480'])
 *   VideoProcessorQueue::processNext()
 *   VideoProcessorQueue::getStatus()
 *   VideoProcessorQueue::isQueued(123)
 */
class VideoProcessorQueue
{
    public const OPTION_QUEUE   = 'vp_queue';
    public const OPTION_RUNNING = 'vp_running';
    public const CRON_HOOK      = 'vp_process_next';
    public const STATUS_META    = '_vp_status';

    // ─── KUYRUK YÖNETİMİ ─────────────────────────────────────────────────────

    /**
     * Attachment'ı kuyruğa ekle.
     *
     * @param  int   $attachment_id
     * @param  array $tasks  Görev listesi: ['resize_720', 'encode_480', ...]
     * @return bool  true = eklendi, false = zaten kuyrukta
     */
    public static function enqueue( int $attachment_id, array $tasks ): bool
    {
        if ( self::isQueued( $attachment_id ) ) return false;

        // FFmpeg kurulu değilse kuyruğa ekleme
        if ( ! VideoProcessor::isAvailable() ) return false;

        $queue   = self::getQueue();
        $queue[] = [
            'attachment_id' => $attachment_id,
            'tasks'         => $tasks,
            'queued_at'     => time(),
        ];

        update_option( self::OPTION_QUEUE, $queue, false );
        update_post_meta( $attachment_id, self::STATUS_META, 'queued' );

        // Cron henüz planlanmamışsa planla
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_single_event( time() + 5, self::CRON_HOOK );
        }

        return true;
    }

    /**
     * Kuyruktaki bir sonraki işi çalıştır.
     * WP Cron hook'u olarak çağrılır.
     */
    public static function processNext(): void
    {
        // Çift çalışma kilidi
        if ( get_option( self::OPTION_RUNNING ) ) return;

        $queue = self::getQueue();
        if ( empty( $queue ) ) return;

        update_option( self::OPTION_RUNNING, true, false );

        $job           = array_shift( $queue );
        $attachment_id = (int) ( $job['attachment_id'] ?? 0 );
        $tasks         = (array) ( $job['tasks'] ?? [] );

        // Kuyruğu güncelle (bu iş çıkarıldı)
        update_option( self::OPTION_QUEUE, $queue, false );

        if ( $attachment_id <= 0 ) {
            update_option( self::OPTION_RUNNING, false, false );
            self::scheduleNextIfNeeded();
            return;
        }

        update_post_meta( $attachment_id, self::STATUS_META, 'processing' );
        update_post_meta( $attachment_id, '_vp_processed_at', time() );

        $started  = time();
        $success  = false;
        $results  = [];

        try {
            $vp      = new VideoProcessor();
            $results = $vp->processAttachment( $attachment_id, $tasks );
            $success = true;
            update_post_meta( $attachment_id, self::STATUS_META, 'done' );
        } catch ( \Throwable $e ) {
            VideoProcessorLog::append( $attachment_id, [
                'task'        => 'queue',
                'started_at'  => $started,
                'finished_at' => time(),
                'result'      => 'error',
                'message'     => $e->getMessage(),
            ] );
            update_post_meta( $attachment_id, self::STATUS_META, 'failed' );
        }

        update_option( self::OPTION_RUNNING, false, false );

        // Bildirim gönder
        if ( $success ) {
            self::maybeNotify( $attachment_id, $tasks, time() - $started );
        }

        // Sıradaki işi planla
        self::scheduleNextIfNeeded();
    }

    // ─── DURUM ───────────────────────────────────────────────────────────────

    /**
     * Kuyruk durumunu döner.
     *
     * @return array { total, pending, done, is_running, current_attachment_id }
     */
    public static function getStatus(): array
    {
        $queue      = self::getQueue();
        $is_running = (bool) get_option( self::OPTION_RUNNING );
        $current    = null;

        if ( $is_running && ! empty( $queue ) ) {
            $current = (int) ( $queue[0]['attachment_id'] ?? null );
        }

        // Kuyruk boşsa ve işlem yoksa, stale "queued" status'larını temizle
        if ( ! $is_running && empty( $queue ) ) {
            global $wpdb;
            $wpdb->query(
                "UPDATE {$wpdb->postmeta} SET meta_value = 'none'
                 WHERE meta_key = '_vp_status' AND meta_value = 'queued'"
            );
        }

        return [
            'total'                 => count( $queue ),
            'pending'               => count( $queue ),
            'is_running'            => $is_running,
            'current_attachment_id' => $current,
        ];
    }

    /**
     * Attachment zaten kuyrukta mı?
     */
    public static function isQueued( int $attachment_id ): bool
    {
        $status = get_post_meta( $attachment_id, self::STATUS_META, true );
        if ( in_array( $status, ['queued', 'processing'], true ) ) return true;

        $queue = self::getQueue();
        foreach ( $queue as $job ) {
            if ( (int) ( $job['attachment_id'] ?? 0 ) === $attachment_id ) return true;
        }
        return false;
    }

    /**
     * Kuyruğu tamamen temizle.
     */
    public static function clear(): void
    {
        update_option( self::OPTION_QUEUE, [], false );
        update_option( self::OPTION_RUNNING, false, false );
    }

    // ─── PRIVATE ─────────────────────────────────────────────────────────────

    private static function getQueue(): array
    {
        $q = get_option( self::OPTION_QUEUE, [] );
        return is_array( $q ) ? $q : [];
    }

    private static function scheduleNextIfNeeded(): void
    {
        $queue = self::getQueue();
        if ( ! empty( $queue ) && ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_single_event( time() + 10, self::CRON_HOOK );
        }
    }

    private static function maybeNotify( int $attachment_id, array $tasks, int $duration ): void
    {
        $settings = VideoProcessorSettings::get();
        if ( empty( $settings['notify_on_done'] ) ) return;
        if ( ! defined('ENABLE_NOTIFICATIONS') || ! ENABLE_NOTIFICATIONS ) return;
        if ( ! class_exists( '\SaltHareket\Notifications\Notifications' ) ) return;

        $recipient = $settings['notify_recipient'] ?? 'admin';
        if ( $recipient === 'admin' ) {
            $admin = get_users( ['role' => 'administrator', 'number' => 1, 'fields' => 'ID'] );
            $recipient_id = ! empty( $admin ) ? (int) $admin[0] : 1;
        } elseif ( $recipient === 'uploader' ) {
            $post = get_post( $attachment_id );
            $recipient_id = $post ? (int) $post->post_author : 1;
        } else {
            $recipient_id = (int) $recipient;
        }

        $filename = basename( get_attached_file( $attachment_id ) ?? '' );

        \SaltHareket\Notifications\Notifications::fire(
            'video-processor/job-complete',
            [
                'recipient'          => $recipient_id,
                'attachment_id'      => $attachment_id,
                'dosya_adi'          => $filename,
                'tamamlanan_gorevler'=> $tasks,
                'sure_saniye'        => $duration,
            ]
        );
    }
}
