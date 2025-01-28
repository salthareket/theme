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
    public $available;
    public $supported;

    public function __construct(){
        $uploadDir = wp_upload_dir();
        $outputDir = $uploadDir['path'];
        $this->setBinaryPaths();
        $this->supported = $this->is_supported();
        $this->available = $this->is_available();
        if($this->available){
            $this->ffmpeg = FFMpeg::create([
                'ffmpeg.binaries'  => $this->ffmpegPath,
                'ffprobe.binaries' => $this->ffprobePath,
                'timeout'          => 3600,
                'ffmpeg.threads'   => 4,
                'log_level'        => 'error',
                'ffmpeg.log_path'  => $outputDir.'ffmpeg.log',
            ]);            
        }
    }

    private function setBinaryPaths(){
        if (stristr(PHP_OS, 'WIN')) {
            $this->ffmpegPath = SH_PATH . '/bin/win/ffmpeg.exe';
            $this->ffprobePath = SH_PATH . '/bin/win/ffprobe.exe';
        } else {
            $this->ffmpegPath = SH_PATH . '/bin/linux/ffmpeg';
            $this->ffprobePath = SH_PATH . '/bin/linux/ffprobe';
        }
    }
    
    public function is_supported(){
        return stristr(PHP_OS, 'WIN') || stristr(PHP_OS, 'Linux');
    }

    public function is_available(){
        if($this->supported){
            return file_exists($this->ffmpegPath) && file_exists($this->ffprobePath);
        }
        return false;
    }

    public function processVideo($postId, $inputPath, $resize = false, $previewThumbnails = false, $poster = false){
        if(!$this->available){
            return [];
        }
        $uploadDir = wp_upload_dir();
        $outputDir = $uploadDir['path'];
        $baseFileName = $this->sanitizeFileName($postId);
        $results = [];

        $inputPath = $this->convertToMp4IfNeeded($inputPath, $outputDir, $baseFileName);

        $video = $this->ffmpeg->open($inputPath);
        $videoInfo = $video->getStreams()->videos()->first();
        $originalWidth = $videoInfo->get('width');
        $originalHeight = $videoInfo->get('height');

        if ($originalHeight === 720) {
            $optimizedPath = "{$outputDir}/{$baseFileName}-720p.mp4";
            rename($inputPath, $optimizedPath);
            $inputPath = $optimizedPath;
        } else {
            $inputPath = $this->resizeIfLargerThan720p($inputPath, $outputDir, $baseFileName, $originalWidth, $originalHeight);
        }

        if ($resize) {
            $results[720] = $this->saveToMediaLibrary($postId, $inputPath);
            $resolutions = is_array($resize) ? $resize : [480, 360];

            foreach ($resolutions as $height) {
                $width = (int) round($height * ($originalWidth / $originalHeight));
                $width = round($width / 2) * 2;
                $height = round($height / 2) * 2;
                $results[$height] = $this->convertResolution($postId, $inputPath, $outputDir, $baseFileName, $width, $height);
            }
        } else {
            $results[720] = $this->saveToMediaLibrary($postId, $inputPath);
        }
        
        if($poster){
            $posterPath = "{$outputDir}/{$baseFileName}-poster.jpg";
            $this->generatePosterFrame($inputPath, $posterPath);
            $results['poster'] = $this->saveToMediaLibrary($postId, $posterPath);            
        }

        if ($previewThumbnails) {
            $thumbnailVideo = $resize ? "{$outputDir}/{$baseFileName}-360p.mp4" : $inputPath;
            $thumbnailsDir = "{$outputDir}";//"{$outputDir}/thumbnails";
            $spritePath = "{$thumbnailsDir}/{$baseFileName}.jpg";
            $vttPath = "{$thumbnailsDir}/{$baseFileName}.vtt";

            $this->generateThumbnails($thumbnailVideo, $thumbnailsDir, $spritePath, $vttPath);

            $results['thumbnails'] = $this->saveToMediaLibrary($postId, $spritePath);
            $results['vtt'] = $this->saveToMediaLibrary($postId, $vttPath);
        }

        return $results;
    }

    private function sanitizeFileName($postId){
        $postType = get_post_type($postId);
        $uniqueString = $postType . '-' . $postId;
        return md5($uniqueString);
    }

    private function convertToMp4IfNeeded($inputPath, $outputDir, $baseFileName){
        $outputPath = "{$outputDir}/{$baseFileName}.mp4";

        $video = $this->ffmpeg->open($inputPath);

        $format = new X264('aac', 'libx264');
        $audioStream = $video->getStreams()->audios()->first();
        if (!$audioStream) {
            error_log("Sessiz video işleniyor.");
            $format->setAudioCodec("copy");
        }

        $video->save($format, $outputPath);

        return $outputPath;
    }

    private function resizeIfLargerThan720p($inputPath, $outputDir, $baseFileName, $originalWidth, $originalHeight){
        $targetHeight = 720;
        $targetWidth = (int) round($targetHeight * ($originalWidth / $originalHeight));

        $outputPath = "{$outputDir}/{$baseFileName}-720p.mp4";
        $this->convertResolutionToPath($inputPath, $outputPath, $targetWidth, $targetHeight);
        return $outputPath;
    }

    private function convertResolution($postId, $inputPath, $outputDir, $baseFileName, $width, $height){
        $outputPath = "{$outputDir}/{$baseFileName}-{$height}p.mp4";
        $this->convertResolutionToPath($inputPath, $outputPath, $width, $height);
        return $this->saveToMediaLibrary($postId, $outputPath);
    }

    private function convertResolutionToPath($inputPath, $outputPath, $width, $height){
        $video = $this->ffmpeg->open($inputPath);

        // Çözünürlük tanımlaması
        $dimension = new Dimension($width, $height);
        $video->filters()
            ->resize($dimension, ResizeFilter::RESIZEMODE_INSET, true)
            ->synchronize();
        $kbps = 2000;
        // Video bitrate ayarı
        if ($height === 720) {
            $kbps = 2000;
        } elseif ($height === 480) {
            $kbps = 1000; // 480p için
        } elseif ($height === 360) {
            $kbps = 500; // 360p için
        }

        // X264 formatını oluştur
        $format = new X264('aac', 'libx264');
        $format->setVideoCodec('libx264');
        $format
            ->setKiloBitrate($kbps)
            ->setAudioChannels(2)
            ->setAudioKiloBitrate(128) // Ses bitrate
            ->setAdditionalParameters(['-preset', 'veryfast', '-tune', 'zerolatency']);


        // FFmpeg komutunu log ile yazdır
        $cmd = $video->getFinalCommand($format, $outputPath);
        error_log(print_r($cmd, true));

        // Video dosyasını kaydet
        $video->save($format, $outputPath);

        // İşlem sonrası çözünürlük ve bitrate kontrolü
        $this->logVideoDetails($outputPath);
    }


    private function logVideoDetails($filePath){
        $ffprobe = $this->ffmpeg->getFFProbe();

        // Dosya bilgilerini al
        $videoInfo = $ffprobe->streams($filePath)->videos()->first();
        $audioInfo = $ffprobe->streams($filePath)->audios()->first();

        error_log("Video Details: " . print_r([
            'width' => $videoInfo->get('width'),
            'height' => $videoInfo->get('height'),
            'bitrate' => $videoInfo->get('bit_rate'),
            'duration' => $videoInfo->get('duration')
        ], true));

        if ($audioInfo) {
            error_log("Audio Details: " . print_r([
                'bitrate' => $audioInfo->get('bit_rate'),
                'channels' => $audioInfo->get('channels')
            ], true));
        }
    }

    private function generatePosterFrame($inputPath, $posterPath){
        $this->ffmpeg->open($inputPath)
            ->frame(\FFMpeg\Coordinate\TimeCode::fromSeconds(5))
            ->save($posterPath);
    }

    private function generateThumbnails($inputPath, $thumbnailsDir, $spritePath, $vttPath){
        if (!file_exists($thumbnailsDir)) {
            mkdir($thumbnailsDir, 0777, true);
        }

        $frames = [];
        $frameInterval = 10; // Frame aralığı (sn)
        $video = $this->ffmpeg->open($inputPath);
        $duration = $video->getFormat()->get('duration');

        for ($i = 0; $i < $duration; $i += $frameInterval) {
            $framePath = "{$thumbnailsDir}/frame-" . sprintf('%04d', $i) . ".jpg";
            $video->frame(\FFMpeg\Coordinate\TimeCode::fromSeconds($i))->save($framePath);
            $this->resizeImage($framePath, 150); // Thumbnail genişliği 150 piksel
            $frames[] = $framePath;
        }

        $this->createSprite($frames, $spritePath);

        $this->createVtt($frames, $spritePath, $vttPath);

        foreach ($frames as $frame) {
            if (file_exists($frame)) {
                unlink($frame); // Frame dosyasını sil
            }
        }
    
    }

    private function resizeImage($filePath, $width){
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


    private function createSprite($frames, $spritePath){
        $columns = 15;
        $rows = ceil(count($frames) / $columns);
        list($width, $height) = getimagesize($frames[0]);

        $spriteWidth = $width * $columns;
        $spriteHeight = $height * $rows;

        $sprite = imagecreatetruecolor($spriteWidth, $spriteHeight);

        $xOffset = 0;
        $yOffset = 0;

        foreach ($frames as $index => $frame) {
            $frameImg = imagecreatefromjpeg($frame);
            imagecopy($sprite, $frameImg, $xOffset, $yOffset, 0, 0, $width, $height);
            imagedestroy($frameImg);

            $xOffset += $width;
            if (($index + 1) % $columns === 0) {
                $xOffset = 0;
                $yOffset += $height;
            }
        }

        imagejpeg($sprite, $spritePath);
        imagedestroy($sprite);
    }

    private function createVtt($frames, $spritePath, $vttPath){
        $columns = 15;
        $vtt = "WEBVTT\n\n";

        foreach ($frames as $index => $frame) {
            list($width, $height) = getimagesize($frame);

            $x = ($index % $columns) * $width;
            $y = floor($index / $columns) * $height;

            $startTime = gmdate("H:i:s", $index * 10);
            $endTime = gmdate("H:i:s", ($index + 1) * 10);

            $vtt .= "{$index}\n{$startTime}.000 --> {$endTime}.000\n";
            $vtt .= basename($spritePath) . "#xywh={$x},{$y},{$width},{$height}\n\n";
        }

        file_put_contents($vttPath, $vtt);
    }

    private function saveToMediaLibrary($postId, $filePath){
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

    public function disableIntermediateImageSizes($sizes){
        // Thumbnail, medium, large gibi boyutları kaldır
        return [];
    }
}
