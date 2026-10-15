<?php

namespace SaltHareket\VideoProcessor\Concerns;

/**
 * HandlesPoster — Poster frame çıkarma trait'i.
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-06-19
 *     - Add: Initial release — VideoProcessor'dan ayrıştırıldı
 *
 * @package SaltHareket\VideoProcessor\Concerns
 */
trait HandlesPoster
{
    /**
     * Videodan poster (önizleme) karesi çıkarır.
     *
     * Videonun 5. saniyesinden (yoksa son yarım saniyeden) bir kare alır.
     *
     * @param  string $input_path  Kaynak video dosya yolu
     * @param  string $output_dir  Çıktı dizini
     * @param  string $base_name   Dosya adı (uzantısız)
     * @return string              Üretilen JPEG dosya yolu
     */
    private function extractPosterFrame( string $input_path, string $output_dir, string $base_name ): string
    {
        $output = "{$output_dir}/{$base_name}-poster.jpg";

        $video    = $this->ffmpeg->open( $input_path );
        $duration = (float) $video->getFormat()->get( 'duration' );
        $time     = min( 5, max( 0, $duration - 0.5 ) );

        $video->frame( \FFMpeg\Coordinate\TimeCode::fromSeconds( $time ) )->save( $output );

        return $output;
    }
}
