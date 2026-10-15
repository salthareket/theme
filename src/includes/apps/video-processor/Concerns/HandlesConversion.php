<?php

namespace SaltHareket\VideoProcessor\Concerns;

use FFMpeg\Format\Video\X264;
use FFMpeg\Filters\Video\ResizeFilter;
use FFMpeg\Coordinate\Dimension;

/**
 * HandlesConversion — Video format dönüşümü ve çözünürlük kodlama trait'i.
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-06-19
 *     - Add: Initial release — VideoProcessor'dan ayrıştırıldı
 *
 * @package SaltHareket\VideoProcessor\Concerns
 */
trait HandlesConversion
{
    /**
     * Videoyu MP4 formatına çevirir.
     *
     * @param  string $input       Girdi dosya yolu
     * @param  string $output_dir  Çıktı dizini
     * @param  string $base_name   Dosya adı (uzantısız)
     * @return string              Yeni MP4 dosya yolu
     */
    private function convertToMp4( string $input, string $output_dir, string $base_name ): string
    {
        $output = "{$output_dir}/{$base_name}.mp4";
        $video  = $this->ffmpeg->open( $input );
        $format = new X264( 'aac', 'libx264' );
        if ( ! $video->getStreams()->audios()->first() ) {
            $format->setAdditionalParameters( ['-an'] );
        }
        $video->save( $format, $output );
        return $output;
    }

    /**
     * Videoyu 720p'ye yeniden boyutlandırır (orijinalin üzerine yazar).
     *
     * @param  string $input       Girdi dosya yolu
     * @param  string $output_dir  Çıktı dizini
     * @param  string $base_name   Dosya adı (uzantısız)
     * @param  array  $dims        ['width' => int, 'height' => int]
     * @return string              Çıktı dosya yolu
     */
    private function resizeTo720p( string $input, string $output_dir, string $base_name, array $dims ): string
    {
        $target_h  = 720;
        $target_w  = $this->ensureEvenDimension( (int) round( $target_h * ( $dims['width'] / $dims['height'] ) ) );
        $output    = "{$output_dir}/{$base_name}.mp4";
        $this->encodeToPath( $input, $output, $target_w, $target_h );
        return $output;
    }

    /**
     * Belirli bir çözünürlüğe encode eder ve Media Library'e kaydeder.
     *
     * @param  int    $post_id     Parent attachment ID
     * @param  string $input       Girdi dosya yolu
     * @param  string $output_dir  Çıktı dizini
     * @param  string $base_name   Dosya adı (uzantısız)
     * @param  int    $height      Hedef yükseklik (px)
     * @param  array  $dims        Orijinal boyutlar
     * @return int                 Yeni attachment ID
     */
    private function encodeResolution( int $post_id, string $input, string $output_dir, string $base_name, int $height, array $dims ): int
    {
        $width  = $this->ensureEvenDimension( (int) round( $height * ( $dims['width'] / $dims['height'] ) ) );
        $output = "{$output_dir}/{$base_name}-{$height}p.mp4";
        $this->encodeToPath( $input, $output, $width, $height );
        return $this->saveToMediaLibrary( $post_id, $output );
    }

    /**
     * Encode işlemini belirtilen çıktı yoluna gerçekleştirir.
     *
     * @param  string $input   Girdi dosya yolu
     * @param  string $output  Çıktı dosya yolu
     * @param  int    $width   Hedef genişlik
     * @param  int    $height  Hedef yükseklik
     */
    private function encodeToPath( string $input, string $output, int $width, int $height ): void
    {
        $video   = $this->ffmpeg->open( $input );
        $info    = $video->getStreams()->videos()->first()->all();
        $bitrate = isset( $info['bit_rate'] ) ? (int) $info['bit_rate'] / 1000 : 2000;
        $crf     = match ( true ) {
            $height >= 720 => 23,
            $height >= 480 => 28,
            default        => 32,
        };

        $scaled_bitrate = (int) ( $bitrate * ( $height / max( 1, $info['height'] ?? $height ) ) );
        $dimension      = new Dimension( $width, $height );
        $video->filters()->resize( $dimension, ResizeFilter::RESIZEMODE_INSET, true )->synchronize();

        $format = new X264( 'aac', 'libx264' );
        $format->setAdditionalParameters( [
            '-crf', (string) $crf,
            '-preset', 'slow',
            '-tune', 'film',
            '-movflags', '+faststart',
        ] )
        ->setKiloBitrate( $scaled_bitrate )
        ->setAudioChannels( 2 )
        ->setAudioKiloBitrate( 128 );

        $video->save( $format, $output );
    }

    /**
     * Verilen değeri en yakın çift sayıya yuvarlar (FFmpeg gerekliliği).
     * Validates: Requirements 11.4
     *
     * @param  int $value  Düzenlenecek değer
     * @return int         Çift sayı
     */
    private function ensureEvenDimension( int $value ): int
    {
        return (int) ( round( $value / 2 ) * 2 );
    }

    /**
     * Videonun boyutlarını döner.
     *
     * @param  string $path  Video dosya yolu
     * @return array         ['width' => int, 'height' => int]
     */
    private function getDimensions( string $path ): array
    {
        $video = $this->ffmpeg->open( $path );
        $info  = $video->getStreams()->videos()->first();
        return [
            'width'  => (int) $info->get( 'width' ),
            'height' => (int) $info->get( 'height' ),
        ];
    }
}
