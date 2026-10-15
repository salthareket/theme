<?php

namespace SaltHareket\VideoProcessor;

/**
 * VideoProcessorLog — Video işlem geçmişini yönetir.
 * _vp_log post meta anahtarında JSON olarak saklar.
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-06-19
 *     - Add: Initial release
 *
 * @package SaltHareket\VideoProcessor
 *
 * How to use:
 *   VideoProcessorLog::append($attachment_id, $entry)  // Log kaydı ekle
 *   VideoProcessorLog::get($attachment_id)              // Logları al
 *   VideoProcessorLog::clear($attachment_id)            // Logları temizle
 *
 * Examples:
 *   VideoProcessorLog::append(123, [
 *       'task'        => 'encode_480',
 *       'started_at'  => time(),
 *       'finished_at' => time() + 30,
 *       'result'      => 'success',
 *       'message'     => '',
 *   ]);
 *
 *   $log = VideoProcessorLog::get(123);
 *   // [['task' => 'encode_480', 'started_at' => ..., 'result' => 'success', ...]]
 */
class VideoProcessorLog
{
    public const META_KEY    = '_vp_log';
    public const MAX_ENTRIES = 50;

    /**
     * Log kaydı ekle.
     *
     * @param int   $attachment_id
     * @param array $entry { task, started_at, finished_at, result: 'success'|'error', message }
     */
    public static function append( int $attachment_id, array $entry ): void
    {
        $log = self::get( $attachment_id );

        $log[] = [
            'task'        => sanitize_key( $entry['task'] ?? 'unknown' ),
            'started_at'  => (int) ( $entry['started_at']  ?? time() ),
            'finished_at' => (int) ( $entry['finished_at'] ?? time() ),
            'result'      => in_array( $entry['result'] ?? '', ['success', 'error'], true )
                                ? $entry['result']
                                : 'error',
            'message'     => sanitize_text_field( $entry['message'] ?? '' ),
            'duration'    => isset( $entry['started_at'], $entry['finished_at'] )
                                ? max( 0, (int) $entry['finished_at'] - (int) $entry['started_at'] )
                                : 0,
        ];

        // Maksimum kayıt sınırı
        if ( count( $log ) > self::MAX_ENTRIES ) {
            $log = array_slice( $log, -self::MAX_ENTRIES );
        }

        update_post_meta( $attachment_id, self::META_KEY, wp_json_encode( $log ) );
    }

    /**
     * Tüm log kayıtlarını döner.
     *
     * @param  int   $attachment_id
     * @return array
     */
    public static function get( int $attachment_id ): array
    {
        $raw = get_post_meta( $attachment_id, self::META_KEY, true );
        if ( empty( $raw ) ) return [];

        $decoded = json_decode( $raw, true );
        return is_array( $decoded ) ? $decoded : [];
    }

    /**
     * Log kayıtlarını temizle.
     */
    public static function clear( int $attachment_id ): void
    {
        delete_post_meta( $attachment_id, self::META_KEY );
    }

    /**
     * Belirli bir görevin son log kaydını döner.
     */
    public static function getLastForTask( int $attachment_id, string $task ): ?array
    {
        $log = array_filter(
            self::get( $attachment_id ),
            fn( $entry ) => ( $entry['task'] ?? '' ) === $task
        );

        return ! empty( $log ) ? end( $log ) : null;
    }

    /**
     * İnsan tarafından okunabilir süre string'i döner.
     * Örn: "2 dakika 30 saniye"
     */
    public static function formatDuration( int $seconds ): string
    {
        if ( $seconds < 60 ) {
            return "{$seconds} saniye";
        }
        $minutes = intdiv( $seconds, 60 );
        $secs    = $seconds % 60;
        if ( $secs > 0 ) {
            return "{$minutes} dakika {$secs} saniye";
        }
        return "{$minutes} dakika";
    }
}
