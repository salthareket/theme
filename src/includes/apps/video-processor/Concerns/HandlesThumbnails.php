<?php

namespace SaltHareket\VideoProcessor\Concerns;

/**
 * HandlesThumbnails — Sprite ve WEBVTT oluşturma trait'i.
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-06-19
 *     - Add: Initial release — VideoProcessor'dan ayrıştırıldı
 *
 * @package SaltHareket\VideoProcessor\Concerns
 */
trait HandlesThumbnails
{
    /**
     * Videodan sprite görseli ve WEBVTT dosyası üretir.
     *
     * Her `$interval` saniyede bir kare çıkarır, 150px genişliğe küçültür,
     * tek bir sprite JPEG'ine birleştirir ve eşleşen VTT dosyası yazar.
     *
     * @param  string $input_path  Kaynak video dosya yolu
     * @param  string $output_dir  Çıktı dizini
     * @param  string $base_name   Dosya adı (uzantısız)
     * @return array               ['sprite_path' => string, 'vtt_path' => string]
     */
    private function generateSpriteVtt( string $input_path, string $output_dir, string $base_name ): array
    {
        $interval    = 5;
        $sprite_path = "{$output_dir}/{$base_name}-sprite.jpg";
        $vtt_path    = "{$output_dir}/{$base_name}.vtt";

        $frames   = [];
        $video    = $this->ffmpeg->open( $input_path );
        $duration = (float) $video->getFormat()->get( 'duration' );

        for ( $i = 0; $i < $duration; $i += $interval ) {
            $frame_path = "{$output_dir}/frame-" . sprintf( '%04d', $i ) . '.jpg';
            $video->frame( \FFMpeg\Coordinate\TimeCode::fromSeconds( $i ) )->save( $frame_path );
            $this->resizeFrame( $frame_path, 150 );
            $frames[] = $frame_path;
        }

        if ( ! empty( $frames ) ) {
            $this->createSprite( $frames, $sprite_path );
            $this->createVtt( $frames, $sprite_path, $vtt_path, $interval );

            foreach ( $frames as $frame ) {
                if ( file_exists( $frame ) ) {
                    @unlink( $frame );
                }
            }
        }

        return [
            'sprite_path' => $sprite_path,
            'vtt_path'    => $vtt_path,
        ];
    }

    /**
     * Kare görselini belirtilen genişliğe yeniden boyutlandırır.
     *
     * @param string $frame_path  JPEG dosya yolu (yerinde değiştirilir)
     * @param int    $width       Hedef genişlik (px)
     */
    private function resizeFrame( string $frame_path, int $width ): void
    {
        [ $orig_w, $orig_h ] = getimagesize( $frame_path );
        if ( $orig_w <= 0 ) return;

        $new_h = (int) round( $width * ( $orig_h / $orig_w ) );
        $src   = imagecreatefromjpeg( $frame_path );
        $dst   = imagecreatetruecolor( $width, $new_h );
        imagecopyresampled( $dst, $src, 0, 0, 0, 0, $width, $new_h, $orig_w, $orig_h );
        imagejpeg( $dst, $frame_path, 75 );
        imagedestroy( $src );
        imagedestroy( $dst );
    }

    /**
     * Kare görsellerini tek bir sprite JPEG dosyasına birleştirir.
     *
     * Izgara düzeni: sütun başına 15 kare.
     *
     * @param array  $frames      Kare dosya yolları dizisi
     * @param string $sprite_path Çıktı sprite JPEG yolu
     */
    private function createSprite( array $frames, string $sprite_path ): void
    {
        $cols           = 15;
        [ $w, $h ]      = getimagesize( $frames[0] );
        $rows           = (int) ceil( count( $frames ) / $cols );
        $sprite         = imagecreatetruecolor( $w * $cols, $h * $rows );

        $x = 0;
        $y = 0;

        foreach ( $frames as $i => $frame ) {
            $img = imagecreatefromjpeg( $frame );
            imagecopy( $sprite, $img, $x, $y, 0, 0, $w, $h );
            imagedestroy( $img );
            $x += $w;
            if ( ( $i + 1 ) % $cols === 0 ) {
                $x = 0;
                $y += $h;
            }
        }

        imagejpeg( $sprite, $sprite_path, 65 );
        imagedestroy( $sprite );
    }

    /**
     * Kare ve sprite bilgisinden WEBVTT dosyası oluşturur.
     *
     * @param array  $frames      Kare dosya yolları dizisi
     * @param string $sprite_path Sprite JPEG dosya yolu (VTT'de basename kullanılır)
     * @param string $vtt_path    Çıktı VTT dosya yolu
     * @param int    $interval    Kareler arası süre (saniye)
     */
    private function createVtt( array $frames, string $sprite_path, string $vtt_path, int $interval ): void
    {
        $cols        = 15;
        $vtt         = "WEBVTT\n\n";
        [ $w, $h ]   = getimagesize( $frames[0] );
        $sprite_base = basename( $sprite_path );

        foreach ( $frames as $i => $frame ) {
            $x     = ( $i % $cols ) * $w;
            $y     = (int) floor( $i / $cols ) * $h;
            $start = gmdate( 'H:i:s', $i * $interval );
            $end   = gmdate( 'H:i:s', ( $i + 1 ) * $interval );
            $vtt  .= "{$i}\n{$start}.000 --> {$end}.000\n";
            $vtt  .= "{$sprite_base}#xywh={$x},{$y},{$w},{$h}\n\n";
        }

        file_put_contents( $vtt_path, $vtt );
    }
}
