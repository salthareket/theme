<?php

namespace SaltHareket\VideoProcessor;

use FFMpeg\FFMpeg;

/**
 * VideoProcessor — FFmpeg tabanlı video işleme sınıfı.
 *
 * @version 2.0.0
 * @changelog
 *   2.0.0 - 2026-06-19
 *     - Move: class.ffmpeg.php'den apps/video-processor/ altına taşındı
 *     - Add: Namespace SaltHareket\VideoProcessor
 *     - Add: Concerns trait'leri — HandlesConversion, HandlesPoster, HandlesThumbnails
 *     - Add: processAttachment() metodu — tüm görevleri orkestre eder
 *     - Add: VideoProcessorLog entegrasyonu — her görev sonrası log
 *     - Add: _vp_status post meta güncellemesi (processing → done | failed)
 *   1.0.0 - 2026-04-03
 *     - Add: Initial versioned release
 *
 * @package SaltHareket\VideoProcessor
 *
 * How to use:
 *   $vp = new VideoProcessor();
 *   if ($vp->available) {
 *       $result = $vp->processAttachment(123, ['resize_720', 'encode_480', 'poster']);
 *   }
 *
 * Examples:
 *   $vp = new VideoProcessor();
 *   $vp->available;  // true/false — FFMpeg binary mevcut mu
 *   $vp->supported;  // true/false — OS destekli mi (Win/Linux)
 *
 *   // Tüm görevler
 *   $result = $vp->processAttachment($id, ['resize_720','encode_480','encode_360','poster','sprite_vtt']);
 *   $result['720']        // orijinal attachment_id (yeniden boyutlandırıldı)
 *   $result['480']        // 480p attachment_id
 *   $result['360']        // 360p attachment_id
 *   $result['poster']     // poster attachment_id
 *   $result['thumbnails'] // sprite attachment_id
 *   $result['vtt']        // VTT attachment_id
 */
class VideoProcessor
{
    use Concerns\HandlesConversion;
    use Concerns\HandlesPoster;
    use Concerns\HandlesThumbnails;

    public bool $available;
    public bool $supported;

    private $ffmpeg = null;
    private string $ffmpegPath;
    private string $ffprobePath;

    public function __construct()
    {
        $this->setBinaryPaths();
        $this->supported = $this->isOsSupported();
        $this->available = $this->checkAvailable();

        if ( $this->available ) {
            try {
                $upload_dir       = wp_upload_dir();
                $this->ffmpeg     = FFMpeg::create( [
                    'ffmpeg.binaries'  => $this->ffmpegPath,
                    'ffprobe.binaries' => $this->ffprobePath,
                    'timeout'          => 3600,
                    'ffmpeg.threads'   => 4,
                    'log_level'        => 'error',
                    'ffmpeg.log_path'  => $upload_dir['basedir'] . '/ffmpeg.log',
                ] );
            } catch ( \Exception $e ) {
                $this->available = false;
                error_log( 'VideoProcessor: FFMpeg init failed — ' . $e->getMessage() );
            }
        }
    }

    // ─── STATIC HELPERS ──────────────────────────────────────────────────────

    /**
     * FFmpeg binary'sinin mevcut olup olmadığını kontrol eder.
     */
    public static function isAvailable(): bool
    {
        $instance = new self();
        return $instance->available;
    }

    /**
     * İşletim sisteminin desteklenip desteklenmediğini kontrol eder.
     */
    public static function isSupported(): bool
    {
        return (bool) ( stristr( PHP_OS, 'WIN' ) || stristr( PHP_OS, 'Linux' ) );
    }

    // ─── ANA İŞLEM ───────────────────────────────────────────────────────────

    /**
     * Bir attachment için belirtilen görevleri sırayla çalıştırır.
     *
     * İşlem başında `_vp_status = processing`, başarıyla tamamlanınca `done`,
     * herhangi bir kritik hata oluşunca `failed` olarak güncellenir.
     *
     * @param  int   $attachment_id  WP attachment ID
     * @param  array $tasks          Etkin görev anahtarları listesi
     *                               ['resize_720', 'encode_480', 'encode_360', 'poster', 'sprite_vtt']
     * @return array                 Üretilen attachment ID'leri haritası
     *                               ['720' => id, '480' => id, '360' => id, 'poster' => id,
     *                                'thumbnails' => id, 'vtt' => id]
     */
    public function processAttachment( int $attachment_id, array $tasks ): array
    {
        if ( ! $this->available ) return [];

        $input_path = get_attached_file( $attachment_id );
        if ( ! $input_path || ! file_exists( $input_path ) ) return [];

        // İşlem başladı
        update_post_meta( $attachment_id, '_vp_status', 'processing' );

        $output_dir = dirname( $input_path );
        $base_name  = pathinfo( $input_path, PATHINFO_FILENAME );
        $results    = [];
        $had_error  = false;

        try {
            // MP4'e çevir (gerekiyorsa)
            if ( strtolower( pathinfo( $input_path, PATHINFO_EXTENSION ) ) !== 'mp4' ) {
                $input_path = $this->convertToMp4( $input_path, $output_dir, $base_name );
            }

            $dimensions = $this->getDimensions( $input_path );

            // ── Görev: resize_720 ────────────────────────────────────────────
            if ( in_array( 'resize_720', $tasks, true ) ) {
                $started = time();
                try {
                    if ( $dimensions['height'] > 720 ) {
                        $input_path     = $this->resizeTo720p( $input_path, $output_dir, $base_name, $dimensions );
                        $results['720'] = $this->saveToMediaLibrary( $attachment_id, $input_path, $attachment_id );
                        // Boyutlar değişti — yeniden al
                        $dimensions = $this->getDimensions( $input_path );
                    } else {
                        $results['720'] = $attachment_id;
                    }
                    VideoProcessorLog::append( $attachment_id, [
                        'task'        => 'resize_720',
                        'started_at'  => $started,
                        'finished_at' => time(),
                        'result'      => 'success',
                        'message'     => '',
                    ] );
                } catch ( \Throwable $e ) {
                    $had_error = true;
                    VideoProcessorLog::append( $attachment_id, [
                        'task'        => 'resize_720',
                        'started_at'  => $started,
                        'finished_at' => time(),
                        'result'      => 'error',
                        'message'     => $e->getMessage(),
                    ] );
                }
            }

            // Sonraki görevler için ana ID
            $main_id = $results['720'] ?? $attachment_id;

            // ── Görev: encode_480 ────────────────────────────────────────────
            if ( in_array( 'encode_480', $tasks, true ) ) {
                $started = time();
                try {
                    $id480          = $this->encodeResolution( $attachment_id, $input_path, $output_dir, $base_name, 480, $dimensions );
                    $results['480'] = $id480;
                    update_post_meta( $main_id, 'tablet', $id480 );
                    VideoProcessorLog::append( $attachment_id, [
                        'task'        => 'encode_480',
                        'started_at'  => $started,
                        'finished_at' => time(),
                        'result'      => 'success',
                        'message'     => '',
                    ] );
                } catch ( \Throwable $e ) {
                    $had_error = true;
                    VideoProcessorLog::append( $attachment_id, [
                        'task'        => 'encode_480',
                        'started_at'  => $started,
                        'finished_at' => time(),
                        'result'      => 'error',
                        'message'     => $e->getMessage(),
                    ] );
                }
            }

            // ── Görev: encode_360 ────────────────────────────────────────────
            if ( in_array( 'encode_360', $tasks, true ) ) {
                $started = time();
                try {
                    $id360          = $this->encodeResolution( $attachment_id, $input_path, $output_dir, $base_name, 360, $dimensions );
                    $results['360'] = $id360;
                    update_post_meta( $main_id, 'phone', $id360 );
                    VideoProcessorLog::append( $attachment_id, [
                        'task'        => 'encode_360',
                        'started_at'  => $started,
                        'finished_at' => time(),
                        'result'      => 'success',
                        'message'     => '',
                    ] );
                } catch ( \Throwable $e ) {
                    $had_error = true;
                    VideoProcessorLog::append( $attachment_id, [
                        'task'        => 'encode_360',
                        'started_at'  => $started,
                        'finished_at' => time(),
                        'result'      => 'error',
                        'message'     => $e->getMessage(),
                    ] );
                }
            }

            // ── Görev: poster ────────────────────────────────────────────────
            if ( in_array( 'poster', $tasks, true ) ) {
                $started = time();
                try {
                    $poster_path       = $this->extractPosterFrame( $input_path, $output_dir, $base_name );
                    $poster_id         = $this->saveToMediaLibrary( $attachment_id, $poster_path );
                    $results['poster'] = $poster_id;
                    update_post_meta( $main_id, 'poster', $poster_id );
                    VideoProcessorLog::append( $attachment_id, [
                        'task'        => 'poster',
                        'started_at'  => $started,
                        'finished_at' => time(),
                        'result'      => 'success',
                        'message'     => '',
                    ] );
                } catch ( \Throwable $e ) {
                    $had_error = true;
                    VideoProcessorLog::append( $attachment_id, [
                        'task'        => 'poster',
                        'started_at'  => $started,
                        'finished_at' => time(),
                        'result'      => 'error',
                        'message'     => $e->getMessage(),
                    ] );
                }
            }

            // ── Görev: sprite_vtt ────────────────────────────────────────────
            if ( in_array( 'sprite_vtt', $tasks, true ) ) {
                $started = time();
                try {
                    // Sprite için en küçük mevcut çözünürlüğü kaynak olarak kullan
                    $sprite_source = $input_path;
                    if ( isset( $results['360'] ) ) {
                        $p = "{$output_dir}/{$base_name}-360p.mp4";
                        if ( file_exists( $p ) ) $sprite_source = $p;
                    } elseif ( isset( $results['480'] ) ) {
                        $p = "{$output_dir}/{$base_name}-480p.mp4";
                        if ( file_exists( $p ) ) $sprite_source = $p;
                    }

                    $sprite_vtt             = $this->generateSpriteVtt( $sprite_source, $output_dir, $base_name );
                    $sprite_id              = $this->saveToMediaLibrary( $attachment_id, $sprite_vtt['sprite_path'] );
                    $vtt_id                 = $this->saveToMediaLibrary( $attachment_id, $sprite_vtt['vtt_path'] );
                    $results['thumbnails']  = $sprite_id;
                    $results['vtt']         = $vtt_id;
                    update_post_meta( $main_id, 'thumbnails', $sprite_id );
                    update_post_meta( $main_id, 'vtt', $vtt_id );
                    VideoProcessorLog::append( $attachment_id, [
                        'task'        => 'sprite_vtt',
                        'started_at'  => $started,
                        'finished_at' => time(),
                        'result'      => 'success',
                        'message'     => '',
                    ] );
                } catch ( \Throwable $e ) {
                    $had_error = true;
                    VideoProcessorLog::append( $attachment_id, [
                        'task'        => 'sprite_vtt',
                        'started_at'  => $started,
                        'finished_at' => time(),
                        'result'      => 'error',
                        'message'     => $e->getMessage(),
                    ] );
                }
            }

            // Son durum: herhangi bir görev hata verdiyse 'failed', aksi halde 'done'
            $final_status = $had_error ? 'failed' : 'done';
            update_post_meta( $attachment_id, '_vp_status', $final_status );
            if ( ! $had_error ) {
                update_post_meta( $attachment_id, '_vp_processed_at', time() );
            }
        } catch ( \Throwable $e ) {
            // Beklenmedik kritik hata (örn: MP4 dönüşümü veya boyut okuma başarısız)
            update_post_meta( $attachment_id, '_vp_status', 'failed' );
            error_log( 'VideoProcessor::processAttachment — ' . $e->getMessage() );
        }

        return $results;
    }

    // ─── YARDIMCI METODLAR ───────────────────────────────────────────────────

    /**
     * Gereksiz ara boyut üretimini devre dışı bırakır.
     * wp_generate_attachment_metadata hook'larında kullanılır.
     */
    public function disableIntermediateImageSizes( array $sizes ): array
    {
        return [];
    }

    // ─── PRIVATE ─────────────────────────────────────────────────────────────

    /**
     * FFmpeg/FFprobe binary yollarını OS'a göre ayarlar.
     */
    private function setBinaryPaths(): void
    {
        $bin = SH_PATH . 'bin/';
        if ( stristr( PHP_OS, 'WIN' ) ) {
            $this->ffmpegPath  = $bin . 'win/ffmpeg.exe';
            $this->ffprobePath = $bin . 'win/ffprobe.exe';
        } else {
            $this->ffmpegPath  = $bin . 'linux/ffmpeg';
            $this->ffprobePath = $bin . 'linux/ffprobe';
        }
    }

    private function isOsSupported(): bool
    {
        return (bool) ( stristr( PHP_OS, 'WIN' ) || stristr( PHP_OS, 'Linux' ) );
    }

    private function checkAvailable(): bool
    {
        return $this->supported &&
               file_exists( $this->ffmpegPath ) &&
               file_exists( $this->ffprobePath );
    }

    /**
     * Dosya yolunu sanitize eder.
     *
     * @param  string $path
     * @return string
     */
    private function sanitizeFilePath( string $path ): string
    {
        return wp_normalize_path( $path );
    }

    /**
     * Üretilen dosyayı WP Media Library'e kaydeder.
     *
     * @param  int         $post_id     Parent attachment ID (ilişkilendirilecek post)
     * @param  string      $file_path   Kaydedilecek dosya yolu
     * @param  int|null    $existing_id Mevcut attachment ID varsa (metadata güncelleme modu)
     * @return int                      Attachment ID
     */
    private function saveToMediaLibrary( int $post_id, string $file_path, ?int $existing_id = null ): int
    {
        $upload_dir = wp_upload_dir();
        $file_url   = $upload_dir['url'] . '/' . basename( $file_path );
        $filetype   = wp_check_filetype( basename( $file_path ) );

        if ( $existing_id !== null && $existing_id > 0 ) {
            $meta = wp_get_attachment_metadata( $existing_id ) ?: [];

            if ( str_contains( (string) $filetype['type'], 'video' ) ) {
                $dims           = $this->getDimensions( $file_path );
                $meta['width']  = $dims['width'];
                $meta['height'] = $dims['height'];
            } elseif ( str_contains( (string) $filetype['type'], 'image' ) ) {
                [ $meta['width'], $meta['height'] ] = getimagesize( $file_path );
            }

            $meta['file'] = str_replace( $upload_dir['basedir'] . '/', '', $file_path );
            wp_update_attachment_metadata( $existing_id, $meta );
            return $existing_id;
        }

        $attachment = [
            'post_mime_type' => $filetype['type'],
            'post_title'     => sanitize_file_name( basename( $file_path ) ),
            'post_content'   => '',
            'post_status'    => 'inherit',
            'guid'           => $file_url,
        ];

        $attach_id = wp_insert_attachment( $attachment, $file_path, $post_id );

        add_filter( 'intermediate_image_sizes_advanced', [ $this, 'disableIntermediateImageSizes' ] );
        $meta = wp_generate_attachment_metadata( $attach_id, $file_path );
        wp_update_attachment_metadata( $attach_id, $meta );
        remove_filter( 'intermediate_image_sizes_advanced', [ $this, 'disableIntermediateImageSizes' ] );

        return $attach_id;
    }
}
