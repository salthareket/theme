<?php

use FFMpeg\FFMpeg;
use FFMpeg\Format\Video\X264;
use FFMpeg\Filters\Video\ResizeFilter;
use FFMpeg\Coordinate\Dimension;

class VideoProcessor
{
    private $ffmpegPath;
    private $ffprobePath;
    private $ffmpeg;

    public function __construct()
    {
        $uploadDir = wp_upload_dir();
        $outputDir = $uploadDir['path'];
        $this->setBinaryPaths();
        $this->ffmpeg = FFMpeg::create([
            'ffmpeg.binaries'  => $this->ffmpegPath,
            'ffprobe.binaries' => $this->ffprobePath,
            'timeout'          => 3600,
            'ffmpeg.threads'   => 4,
            'log_level'        => 'error', // Sadece hata loglarını kaydet
            'ffmpeg.log_path'  => $outputDir.'ffmpeg.log', // Log dosyası
        ]);
    }

    private function setBinaryPaths()
    {
        if (stristr(PHP_OS, 'WIN')) {
            $this->ffmpegPath = SH_PATH . '/bin/win/ffmpeg.exe';
            $this->ffprobePath = SH_PATH . '/bin/win/ffprobe.exe';
        } else {
            $this->ffmpegPath = SH_PATH . '/bin/linux/ffmpeg';
            $this->ffprobePath = SH_PATH . '/bin/linux/ffprobe';
        }
    }

    /*public function processVideo($postId, $inputPath, $resize = false, $previewThumbnails = false){
        $uploadDir = wp_upload_dir();
        $outputDir = $uploadDir['path'];
        $baseFileName = $this->sanitizeFileName($postId);
        $results = [];

        // 1. Format Kontrolü ve MP4'e Dönüştürme
        $inputPath = $this->convertToMp4IfNeeded($inputPath, $outputDir, $baseFileName);

        // 2. Çözünürlük Kontrolü
        $video = $this->ffmpeg->open($inputPath);
        $videoInfo = $video->getStreams()->videos()->first();
        $originalWidth = $videoInfo->get('width');
        $originalHeight = $videoInfo->get('height');

        if ($originalHeight === 720) {
            // Eğer video 720p çözünürlüğündeyse
            $optimizedPath = "{$outputDir}/{$baseFileName}-720p.mp4";
            rename($inputPath, $optimizedPath);
            $inputPath = $optimizedPath;
        } else {
            $inputPath = $this->resizeIfLargerThan720p($inputPath, $outputDir, $baseFileName, $originalWidth, $originalHeight);
        }

        // 3. 480p ve 360p'yi Oluştur (Eğer $resize True ise)
        if ($resize) {
            $results[720] = $this->saveToMediaLibrary($postId, $inputPath);
            $results[480] = $this->convertResolution($postId, $inputPath, $outputDir, $baseFileName, 480);
            $results[360] = $this->convertResolution($postId, $inputPath, $outputDir, $baseFileName, 360);
        } else {
            $results[720] = $this->saveToMediaLibrary($postId, $inputPath);
        }

        // 4. Poster Frame Oluştur
        $posterPath = "{$outputDir}/{$baseFileName}-poster.jpg";
        $this->generatePosterFrame($inputPath, $posterPath);
        $results['poster'] = $this->saveToMediaLibrary($postId, $posterPath);

        // 5. Thumbnail ve VTT Dosyası Oluştur
        if ($previewThumbnails) {
            $thumbnailVideo = $resize ? "{$outputDir}/{$baseFileName}-360p.mp4" : $inputPath;
            $thumbnailsDir = "{$outputDir}/thumbnails";
            $spritePath = "{$thumbnailsDir}/{$baseFileName}.jpg";
            $vttPath = "{$thumbnailsDir}/{$baseFileName}.vtt";

            $this->generateThumbnails($thumbnailVideo, $thumbnailsDir, $spritePath, $vttPath);

            $results['thumbnails'] = $this->saveToMediaLibrary($postId, $spritePath);
            $results['vtt'] = $this->saveToMediaLibrary($postId, $vttPath);
        }

        return $results;
    }*/

    public function processVideo($postId, $inputPath, $resize = false, $previewThumbnails = false){
        $uploadDir = wp_upload_dir();
        $outputDir = $uploadDir['path'];
        $baseFileName = $this->sanitizeFileName($postId);
        $results = [];

        // 1. Format Kontrolü ve MP4'e Dönüştürme
        $inputPath = $this->convertToMp4IfNeeded($inputPath, $outputDir, $baseFileName);

        // 2. Çözünürlük Kontrolü
        $video = $this->ffmpeg->open($inputPath);
        $videoInfo = $video->getStreams()->videos()->first();
        $originalWidth = $videoInfo->get('width');
        $originalHeight = $videoInfo->get('height');

        if ($originalHeight === 720) {
            // Eğer video 720p çözünürlüğündeyse
            $optimizedPath = "{$outputDir}/{$baseFileName}-720p.mp4";
            rename($inputPath, $optimizedPath);
            $inputPath = $optimizedPath;
        } else {
            $inputPath = $this->resizeIfLargerThan720p($inputPath, $outputDir, $baseFileName, $originalWidth, $originalHeight);
        }

        // 3. Dinamik Boyutları Oluştur
        if ($resize) {
            $results[720] = $this->saveToMediaLibrary($postId, $inputPath);

            // Eğer $resize bir array ise belirtilen boyutları kullan
            $sizes = is_array($resize) ? $resize : [480, 360];

            foreach ($sizes as $targetHeight) {
                $results[$targetHeight] = $this->convertResolution($postId, $inputPath, $outputDir, $baseFileName, $targetHeight);
            }
        } else {
            $results[720] = $this->saveToMediaLibrary($postId, $inputPath);
        }

        // 4. Poster Frame Oluştur
        $posterPath = "{$outputDir}/{$baseFileName}-poster.jpg";
        $this->generatePosterFrame($inputPath, $posterPath);
        $results['poster'] = $this->saveToMediaLibrary($postId, $posterPath);

        // 5. Thumbnail ve VTT Dosyası Oluştur
        if ($previewThumbnails) {
            $thumbnailVideo = $resize ? "{$outputDir}/{$baseFileName}-360p.mp4" : $inputPath;
            $thumbnailsDir = "{$outputDir}/thumbnails";
            $spritePath = "{$thumbnailsDir}/{$baseFileName}.jpg";
            $vttPath = "{$thumbnailsDir}/{$baseFileName}.vtt";

            $this->generateThumbnails($thumbnailVideo, $thumbnailsDir, $spritePath, $vttPath);

            $results['thumbnails'] = $this->saveToMediaLibrary($postId, $spritePath);
            $results['vtt'] = $this->saveToMediaLibrary($postId, $vttPath);
        }

        return $results;
    }



    private function sanitizeFileName($postId)
    {
        $postType = get_post_type($postId);
        $uniqueString = $postType . '-' . $postId;
        return md5($uniqueString);
    }

    private function convertToMp4IfNeeded($inputPath, $outputDir, $baseFileName)
    {
        $outputPath = "{$outputDir}/{$baseFileName}.mp4";
        $video = $this->ffmpeg->open($inputPath);

        // Video formatı için X264 yapılandırması
        $format = new X264('aac', 'libx264');

        // Ses akışını kontrol et
        $audioStream = $video->getStreams()->audios()->first();
        if (!$audioStream) {
            error_log("Video dosyasının sesi yok. Sessiz video olarak işleniyor.");
            $format->setAudioCodec(null);
        }

        $video->save($format, $outputPath);

        return $outputPath;
    }

    private function resizeIfLargerThan720p($inputPath, $outputDir, $baseFileName, $originalWidth, $originalHeight)
    {
        $targetHeight = 720;
        $targetWidth = (int) round($targetHeight * ($originalWidth / $originalHeight));

        $outputPath = "{$outputDir}/{$baseFileName}-720p.mp4";
        $this->convertResolutionToPath($inputPath, $outputPath, $targetWidth, $targetHeight);
        return $outputPath;
    }

    private function convertResolution($postId, $inputPath, $outputDir, $baseFileName, $targetHeight)
    {
        $video = $this->ffmpeg->open($inputPath);
        $videoInfo = $video->getStreams()->videos()->first();

        $originalWidth = $videoInfo->get('width');
        $originalHeight = $videoInfo->get('height');

        $newWidth = (int) round($targetHeight * ($originalWidth / $originalHeight));
        $newHeight = $targetHeight;

        $newWidth = round($newWidth / 2) * 2;
        $newHeight = round($newHeight / 2) * 2;

        $outputPath = "{$outputDir}/{$baseFileName}-{$targetHeight}p.mp4";
        $this->convertResolutionToPath($inputPath, $outputPath, $newWidth, $newHeight);

        return $this->saveToMediaLibrary($postId, $outputPath);
    }


    private function convertResolutionToPath($inputPath, $outputPath, $width, $height)
    {
        $video = $this->ffmpeg->open($inputPath);

        try {
            // Dimension tanımlaması
            $dimension = new \FFMpeg\Coordinate\Dimension($width, $height);

            // Resize işlemi için ResizeFilter kullanımı
            $video->filters()
                ->resize($dimension, \FFMpeg\Filters\Video\ResizeFilter::RESIZEMODE_INSET, true) // Aspect ratio korunarak
                ->synchronize();

            // X264 formatını oluştur ve ses/video bitrate ayarlarını yap
            $format = new X264('aac', 'libx264');

            // Ses ve video ayarlarını yapılandır
            $format->setAudioChannels(2)
                ->setAudioKiloBitrate(128)
                ->setAdditionalParameters(['-preset', 'veryfast', '-tune', 'zerolatency'])
                ->setKiloBitrate(1000);

            // Videoyu kaydet
            $video->save($format, $outputPath);

            error_log("Encoding başarılı: {$outputPath}");
        } catch (\Exception $e) {
            // Hataları logla
            error_log("Video işlemi sırasında hata: " . $e->getMessage());
        }
    }

    private function generatePosterFrame($inputPath, $posterPath)
    {
        $this->ffmpeg->open($inputPath)
            ->frame(\FFMpeg\Coordinate\TimeCode::fromSeconds(5))
            ->save($posterPath);
    }

    private function generateThumbnails($inputPath, $thumbnailsDir, $spritePath, $vttPath, $thumbnailsPerRow = 15)
    {
        if (!file_exists($thumbnailsDir)) {
            mkdir($thumbnailsDir, 0777, true);
        }

        $video = $this->ffmpeg->open($inputPath);
        $duration = $video->getStreams()->videos()->first()->get('duration'); // Videonun süresi (saniye)

        $frames = [];
        for ($i = 0; $i < $duration; $i++) { // Tüm frameleri işliyoruz
            $framePath = "{$thumbnailsDir}/frame-" . sprintf('%04d', $i) . ".jpg";
            $this->ffmpeg->open($inputPath)->frame(\FFMpeg\Coordinate\TimeCode::fromSeconds($i))->save($framePath);

            // Frame boyutunu küçült
            $this->resizeImage($framePath, 150); // Thumbnail genişliği 150 piksel
            $frames[] = $framePath;
        }

        $spriteWidth = 150; // Her bir thumbnail'in genişliği
        $spriteHeight = 84; // Her bir thumbnail'in yüksekliği
        $vtt = "WEBVTT\n\n";

        foreach ($frames as $index => $frame) {
            $row = floor($index / $thumbnailsPerRow); // Hangi satırda olduğunu hesapla
            $col = $index % $thumbnailsPerRow; // Hangi sütunda olduğunu hesapla

            $x = $col * $spriteWidth;
            $y = $row * $spriteHeight;

            $startTime = sprintf("00:00:%02d.000", $index);
            $endTime = sprintf("00:00:%02d.000", $index + 1);

            $vtt .= ($index + 1) . "\n";
            $vtt .= "{$startTime} --> {$endTime}\n";
            $vtt .= basename($spritePath) . "#xywh={$x},{$y},{$spriteWidth},{$spriteHeight}\n\n";
        }

        file_put_contents($vttPath, $vtt);

        $this->createSprite($frames, $spritePath, $thumbnailsPerRow);

        foreach ($frames as $frame) {
            if (file_exists($frame)) {
                unlink($frame);
            }
        }
    }


    private function resizeImage($filePath, $width)
    {
        list($originalWidth, $originalHeight) = getimagesize($filePath);
        $aspectRatio = $originalHeight / $originalWidth;
        $newHeight = (int) round($width * $aspectRatio);

        $image = imagecreatefromjpeg($filePath);
        $newImage = imagecreatetruecolor($width, $newHeight);

        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $width, $newHeight, $originalWidth, $originalHeight);
        imagejpeg($newImage, $filePath, 75);

        imagedestroy($image);
        imagedestroy($newImage);
    }


    private function createSprite($frames, $spritePath, $thumbnailsPerRow)
    {
        list($thumbnailWidth, $thumbnailHeight) = getimagesize($frames[0]);
        $columns = $thumbnailsPerRow; // Her satırdaki thumbnail sayısı
        $rows = ceil(count($frames) / $columns); // Toplam satır sayısını hesapla

        $spriteWidth = $thumbnailWidth * $columns;
        $spriteHeight = $thumbnailHeight * $rows;

        $spriteImage = imagecreatetruecolor($spriteWidth, $spriteHeight);

        $xOffset = 0;
        $yOffset = 0;

        foreach ($frames as $index => $frame) {
            $image = imagecreatefromjpeg($frame);

            // Thumbnail'in x ve y koordinatlarını hesapla
            $xOffset = ($index % $columns) * $thumbnailWidth;
            $yOffset = floor($index / $columns) * $thumbnailHeight;

            imagecopy($spriteImage, $image, $xOffset, $yOffset, 0, 0, $thumbnailWidth, $thumbnailHeight);
            imagedestroy($image);
        }

        imagejpeg($spriteImage, $spritePath, 75);
        imagedestroy($spriteImage);
    }


    private function saveToMediaLibrary($postId, $filePath)
    {
        $filetype = wp_check_filetype(basename($filePath));
        $attachment = [
            'post_mime_type' => $filetype['type'],
            'post_title'     => sanitize_file_name(basename($filePath)),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attachId = wp_insert_attachment($attachment, $filePath, $postId);

        // WordPress'te diğer boyutların oluşturulmasını engelle
        add_filter('intermediate_image_sizes_advanced', [$this, 'disableIntermediateImageSizes']);

        // Medya meta verisini oluştur
        $metadata = wp_generate_attachment_metadata($attachId, $filePath);
        wp_update_attachment_metadata($attachId, $metadata);

        // Filtreyi kaldır
        remove_filter('intermediate_image_sizes_advanced', [$this, 'disableIntermediateImageSizes']);

        return $attachId;
    }

    public function disableIntermediateImageSizes($sizes)
    {
        // Thumbnail, medium, large gibi boyutları kaldır
        return [];
    }
}
