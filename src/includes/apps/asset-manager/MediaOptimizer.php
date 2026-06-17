<?php

namespace SaltHareket\AssetManager;

/**
 * MediaOptimizer
 *
 * Unoptimized (non-AVIF/WebP) media library images'ı tarar, kuyruğa alır
 * ve arka planda WP Cron ile dönüştürür. Session bazlı istatistik tutar.
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-06-16 — Initial release
 *     - Add: scan() — unoptimized attachment'ları paged olarak listele
 *     - Add: enqueue() — ID listesini dönüştürme kuyruğuna ekle
 *     - Add: processNext() — kuyruktan N item al, dönüştür, stats güncelle
 *     - Add: Session stats — saved_bytes, converted_count, session_id
 *     - Add: WP Cron fallback (sh_media_optimizer_process hook)
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // Unoptimized resimleri tara (sayfa 1, 20'li)
 * $result = MediaOptimizer::scan(1, 20);
 * // → ['items'=>[...], 'total'=>423, 'pages'=>22]
 *
 * // Kuyruğa ekle
 * MediaOptimizer::enqueue([123, 456, 789]);
 *
 * // Sıradakini işle (AJAX'ta çağrılır)
 * $result = MediaOptimizer::processNext(3);
 *
 * // Mevcut session istatistikleri
 * $stats = MediaOptimizer::getSessionStats();
 *
 * ──────────────────────────────────────────────────────────
 */
class MediaOptimizer
{
    const QUEUE_OPTION      = 'sh_media_optimizer_queue';
    const STATS_OPTION      = 'sh_media_optimizer_stats';
    const CRON_HOOK         = 'sh_media_optimizer_process';
    const CRON_INTERVAL     = 30; // saniye
    const BATCH_SIZE        = 3;
    const PER_PAGE          = 20;

    // Dönüştürülecek mime type'lar
    const UNOPTIMIZED_MIMES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/bmp',
        'image/heic',
    ];

    // ─── SCAN ────────────────────────────────────────────────────────────────

    /**
     * Unoptimized attachment'ları paged olarak tara.
     * AVIF veya WebP olan attachment'lar hariç tutulur.
     *
     * @param int $page    1-based sayfa numarası
     * @param int $per_page Sayfa başına item
     * @return array { items: array, total: int, pages: int, total_size: int }
     */
    public static function scan( int $page = 1, int $per_page = self::PER_PAGE ): array
    {
        $offset = ( $page - 1 ) * $per_page;

        // Toplam sayıyı al
        $total = (int) self::countUnoptimized();

        // Sayfa itemlarını al
        $query = new \WP_Query( [
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'post_mime_type' => self::UNOPTIMIZED_MIMES,
            'posts_per_page' => $per_page,
            'offset'         => $offset,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => false,
            'fields'         => 'all',
        ] );

        $items      = [];
        $total_size = 0;

        foreach ( $query->posts as $post ) {
            $file = get_attached_file( $post->ID );
            $size = $file && file_exists( $file ) ? filesize( $file ) : 0;
            $total_size += $size;

            // Alpha channel tespiti — target format belirle
            $has_alpha  = self::hasAlpha( $file );
            $target_fmt = $has_alpha ? 'WebP' : 'AVIF';

            // Küçük thumbnail URL
            $thumb = wp_get_attachment_image_url( $post->ID, 'thumbnail' ) ?: wp_get_attachment_url( $post->ID );

            $items[] = [
                'id'         => $post->ID,
                'title'      => $post->post_title ?: basename( $post->guid ),
                'filename'   => $file ? basename( $file ) : '—',
                'mime'       => $post->post_mime_type,
                'size'       => $size,
                'size_human' => $size > 0 ? size_format( $size, 1 ) : '—',
                'thumb'      => $thumb,
                'target_fmt' => $target_fmt,
                'guid'       => $post->guid,
                'date'       => get_the_date( 'd M Y', $post ),
            ];
        }

        return [
            'items'      => $items,
            'total'      => $total,
            'pages'      => $total > 0 ? (int) ceil( $total / $per_page ) : 0,
            'page'       => $page,
            'per_page'   => $per_page,
            'total_size' => $total_size,
        ];
    }

    /**
     * Toplam unoptimized attachment sayısı.
     */
    public static function countUnoptimized(): int
    {
        $query = new \WP_Query( [
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'post_mime_type' => self::UNOPTIMIZED_MIMES,
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => false,
        ] );
        return (int) $query->found_posts;
    }

    // ─── QUEUE ───────────────────────────────────────────────────────────────

    /**
     * Attachment ID listesini kuyruğa ekle.
     * Mevcut kuyruğa merge eder (duplicate'leri atlar).
     *
     * @param int[]  $ids
     * @param string $email  Tamamlanınca mail gönderilecek adres (opsiyonel)
     */
    public static function enqueue( array $ids, string $email = '' ): void
    {
        $ids = array_map( 'intval', array_filter( $ids ) );
        if ( empty( $ids ) ) return;

        $current = self::getQueue();
        $pending = array_values( array_unique( array_merge( $current['pending'] ?? [], $ids ) ) );

        $queue = [
            'pending'     => $pending,
            'processing'  => false,
            'email'       => $email ?: ( $current['email'] ?? '' ),
            'started_at'  => $current['started_at'] ?? time(),
            'last_update' => time(),
            'total'       => $current['total'] ?? count( $pending ),
        ];

        update_option( self::QUEUE_OPTION, $queue, false );

        // Cron'u planla
        self::scheduleCron();
    }

    /**
     * Kuyruğu temizle.
     */
    public static function clearQueue(): void
    {
        delete_option( self::QUEUE_OPTION );
        wp_clear_scheduled_hook( self::CRON_HOOK );
    }

    /**
     * Mevcut kuyruğu al.
     */
    public static function getQueue(): array
    {
        $q = get_option( self::QUEUE_OPTION, [] );
        return is_array( $q ) ? $q : [];
    }

    /**
     * Kuyruk durumu.
     */
    public static function getQueueStatus(): array
    {
        $q    = self::getQueue();
        $done = (int) ( $q['total'] ?? 0 ) - count( $q['pending'] ?? [] );

        // is_running: pending varsa AND son güncellemeden 10 dk'dan az geçmişse
        // Stale queue koruması: 10 dk üzerinde işlem görülmemişse otomatik sıfırla
        $pending    = $q['pending'] ?? [];
        $is_running = false;
        if ( ! empty( $pending ) ) {
            $last_update = (int) ( $q['last_update'] ?? 0 );
            if ( $last_update > 0 && ( time() - $last_update ) < 600 ) {
                // Son 10 dk içinde aktif işlem var — gerçekten çalışıyor
                $is_running = true;
            } else {
                // last_update yoksa veya 10 dk geçmişse stale — temizle
                self::clearQueue();
            }
        }

        return [
            'total'      => (int) ( $q['total'] ?? 0 ),
            'pending'    => count( $pending ),
            'done'       => max( 0, $done ),
            'processing' => (bool) ( $q['processing'] ?? false ),
            'email'      => $q['email'] ?? '',
            'is_running' => $is_running,
        ];
    }

    // ─── PROCESS ─────────────────────────────────────────────────────────────

    /**
     * Kuyruktan N item al, dönüştür, stats güncelle.
     * AJAX polling veya cron'dan çağrılır.
     *
     * @param int $batch_size Kaç item işlenecek
     * @return array { converted: int, failed: int, remaining: int, done: bool, stats: array }
     */
    public static function processNext( int $batch_size = self::BATCH_SIZE ): array
    {
        $queue = self::getQueue();

        if ( empty( $queue['pending'] ) ) {
            return [ 'converted' => 0, 'failed' => 0, 'remaining' => 0, 'done' => true, 'stats' => self::getSessionStats() ];
        }

        // İlk N item'ı al
        $batch   = array_splice( $queue['pending'], 0, $batch_size );
        $queue['processing'] = true;
        update_option( self::QUEUE_OPTION, $queue, false );

        $converted = 0;
        $failed    = 0;

        foreach ( $batch as $attachment_id ) {
            $result = self::convertAttachment( (int) $attachment_id );
            if ( $result['success'] ) {
                $converted++;
                self::updateStats( $result['saved_bytes'] ?? 0 );
            } else {
                $failed++;
            }
        }

        // Kuyruğu güncelle
        $queue['processing']  = false;
        $queue['last_update'] = time();
        update_option( self::QUEUE_OPTION, $queue, false );

        $remaining = count( $queue['pending'] );

        // Kuyruk bitti mi?
        $done = ( $remaining === 0 );
        if ( $done ) {
            self::onQueueComplete( $queue['email'] ?? '' );
        }

        return [
            'converted' => $converted,
            'failed'    => $failed,
            'remaining' => $remaining,
            'done'      => $done,
            'stats'     => self::getSessionStats(),
        ];
    }

    /**
     * Tek attachment'ı direkt convert et — queue yazmaz, cron planlamaz.
     * Admin'deki tek resim ⚡ butonu için.
     *
     * @return array { success: bool, saved_bytes: int, format: string, error: string }
     */
    public static function convertSingle( int $attachment_id ): array
    {
        $result = self::convertAttachment( $attachment_id );
        if ( $result['success'] ) {
            self::updateStats( $result['saved_bytes'] ?? 0 );
        }
        return $result;
    }

    /**
     * Tek attachment'ı dönüştür.
     * AvifConverter class'ının mantığını kullanır.
     *
     * @return array { success: bool, saved_bytes: int, format: string, error: string }
     */
    private static function convertAttachment( int $attachment_id ): array
    {
        $file = get_attached_file( $attachment_id );
        if ( ! $file || ! file_exists( $file ) ) {
            return [ 'success' => false, 'error' => 'File not found', 'saved_bytes' => 0 ];
        }

        $original_size = filesize( $file );
        $ext           = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
        $allowed       = [ 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'heic' ];

        if ( ! in_array( $ext, $allowed, true ) ) {
            return [ 'success' => false, 'error' => 'Not a convertible format', 'saved_bytes' => 0 ];
        }

        // AvifConverter instance yarat ve process_and_convert_metadata'yı çağır
        if ( ! class_exists( 'AvifConverter' ) ) {
            return [ 'success' => false, 'error' => 'AvifConverter not loaded', 'saved_bytes' => 0 ];
        }

        try {
            $converter = new \AvifConverter();
            $metadata  = wp_get_attachment_metadata( $attachment_id );

            if ( ! is_array( $metadata ) ) {
                $metadata = [];
            }

            // process_and_convert_metadata çağır
            $new_metadata = $converter->process_and_convert_metadata( $metadata, $attachment_id );

            // Metadata güncelle
            wp_update_attachment_metadata( $attachment_id, $new_metadata );

            // Yeni dosya boyutu — orijinal silinmiş olabilir, new_file'dan hesapla
            $new_file    = get_attached_file( $attachment_id );
            $new_size    = $new_file && file_exists( $new_file ) ? filesize( $new_file ) : 0;
            $saved_bytes = max( 0, $original_size - $new_size );

            return [
                'success'     => true,
                'saved_bytes' => $saved_bytes,
                'format'      => strtoupper( pathinfo( $new_file ?? $file, PATHINFO_EXTENSION ) ),
                'error'       => '',
            ];
        } catch ( \Exception $e ) {
            return [ 'success' => false, 'error' => $e->getMessage(), 'saved_bytes' => 0 ];
        }
    }

    // ─── STATS ───────────────────────────────────────────────────────────────

    /**
     * Session istatistiklerini güncelle.
     * Her çağrıda üstüne ekler — session boyunca biriktirir.
     */
    private static function updateStats( int $saved_bytes ): void
    {
        $stats = self::getSessionStats();
        $stats['converted_count']++;
        $stats['saved_bytes'] += $saved_bytes;
        $stats['last_run']    = time();
        update_option( self::STATS_OPTION, $stats, false );
    }

    /**
     * Mevcut session istatistiklerini al.
     */
    public static function getSessionStats(): array
    {
        $defaults = [
            'converted_count' => 0,
            'saved_bytes'     => 0,
            'last_run'        => 0,
            'sessions'        => 0,
        ];
        $s = get_option( self::STATS_OPTION, [] );
        return array_merge( $defaults, is_array( $s ) ? $s : [] );
    }

    /**
     * Stats sıfırla (yeni session başlatır).
     */
    public static function resetStats(): void
    {
        $current = self::getSessionStats();
        update_option( self::STATS_OPTION, [
            'converted_count' => 0,
            'saved_bytes'     => 0,
            'last_run'        => 0,
            'sessions'        => ( $current['sessions'] ?? 0 ) + 1,
        ], false );
    }

    // ─── CRON ────────────────────────────────────────────────────────────────

    /**
     * WP Cron'u planla — background processing için.
     */
    public static function scheduleCron(): void
    {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_single_event( time() + 5, self::CRON_HOOK );
        }
    }

    /**
     * Cron callback — kuyruktan işle, kendini tekrar planla.
     */
    public static function runCron(): void
    {
        $result = self::processNext( self::BATCH_SIZE );

        // Kuyrukta hala item varsa 30 sn sonraya tekrar planla
        if ( ! $result['done'] && $result['remaining'] > 0 ) {
            wp_schedule_single_event( time() + self::CRON_INTERVAL, self::CRON_HOOK );
        }
    }

    /**
     * Kuyruk tamamlandığında.
     */
    private static function onQueueComplete( string $email ): void
    {
        // Queue option'ı temizle — bir sonraki sayfa yüklemesinde stale pending göstermesin
        self::clearQueue();

        if ( $email && is_email( $email ) ) {
            $stats   = self::getSessionStats();
            $subject = sprintf( '[%s] Image optimization complete', get_bloginfo( 'name' ) );
            $message = sprintf(
                "Image optimization completed!\n\n" .
                "Converted: %d images\n" .
                "Space saved: %s\n\n" .
                "Managed by SaltHareket Asset Manager",
                $stats['converted_count'],
                size_format( $stats['saved_bytes'], 2 )
            );
            wp_mail( $email, $subject, $message );
        }
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────

    /**
     * Dosyada alpha channel var mı?
     * PNG binary check veya Imagick kullanır.
     */
    private static function hasAlpha( ?string $path ): bool
    {
        if ( ! $path || ! file_exists( $path ) ) return false;

        if ( class_exists( 'Imagick' ) ) {
            try {
                $im    = new \Imagick( $path );
                $alpha = $im->getImageAlphaChannel();
                $im->destroy();
                return (bool) $alpha;
            } catch ( \Exception $e ) {
                // fallback
            }
        }

        // PNG binary check
        if ( strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) === 'png' ) {
            $f = @fopen( $path, 'rb' );
            if ( $f ) {
                fseek( $f, 25 );
                $type = ord( fread( $f, 1 ) );
                fclose( $f );
                return ( $type === 4 || $type === 6 );
            }
        }

        return false;
    }

    /**
     * Converter desteklenip desteklenmediğini kontrol et.
     */
    public static function isSupported(): bool
    {
        if ( function_exists( 'imageavif' ) || function_exists( 'imagewebp' ) ) return true;
        if ( class_exists( 'Imagick' ) ) {
            try {
                $formats = \Imagick::queryFormats();
                return in_array( 'AVIF', $formats, true ) || in_array( 'WEBP', $formats, true );
            } catch ( \Exception $e ) {
                return false;
            }
        }
        return false;
    }
}
