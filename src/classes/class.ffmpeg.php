<?php

use FFMpeg\FFMpeg;
use FFMpeg\Format\Video\X264;
use Symfony\Component\Process\Process;

class VideoProcessor
{
    private $ffmpegPath;
    private $ffprobePath;
    private $ffmpeg;

    public function __construct()
    {
        $this->setBinaryPaths();
        $this->ffmpeg = FFMpeg::create([
            'ffmpeg.binaries'  => $this->ffmpegPath,
            'ffprobe.binaries' => $this->ffprobePath,
            'timeout'          => 3600,
            'ffmpeg.threads'   => 4,
        ]);
    }

    private function setBinaryPaths(){
        if (stristr(PHP_OS, 'WIN')) {
            // Windows için
            $this->ffmpegPath = SH_PATH . '/bin/win/ffmpeg.exe';
            $this->ffprobePath = SH_PATH . '/bin/win/ffprobe.exe';
        } else {
            // Linux için
            $this->ffmpegPath = SH_PATH . '/bin/linux/ffmpeg';
            $this->ffprobePath = SH_PATH . '/bin/linux/ffprobe';
        }
    }


    public function processVideo($postId, $inputPath, $resize = false, $previewThumbnails = false)
    {
        $uploadDir = wp_upload_dir();
        $outputDir = $uploadDir['path'];
        $baseFileName = $this->sanitizeFileName(pathinfo($inputPath, PATHINFO_FILENAME));
        $results = [];

        // 1. Format Kontrolü ve MP4'e Dönüştürme
        $inputPath = $this->convertToMp4IfNeeded($inputPath, $outputDir, $baseFileName);

        // 2. 720p'den Büyükse 720p'ye Düşür
        $inputPath = $this->resizeIfLargerThan720p($inputPath, $outputDir, $baseFileName);

        // 3. 480p ve 360p'yi Oluştur (Eğer $resize True ise)
        if ($resize) {
            $results[720] = $this->saveToMediaLibrary($postId, $inputPath);
            $results[480] = $this->convertResolution($postId, $inputPath, $outputDir, $baseFileName, 854, 480);
            $results[360] = $this->convertResolution($postId, $inputPath, $outputDir, $baseFileName, 640, 360);
        } else {
            $results[720] = $this->saveToMediaLibrary($postId, $inputPath);
        }

        // 4. Thumbnail ve VTT Dosyası Oluştur
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

    private function sanitizeFileName($fileName)
    {
        $fileName = sanitize_title($fileName); // WordPress'in dosya adını temizleme fonksiyonu
        $uploadDir = wp_upload_dir()['path'];
        $count = 1;

        while (file_exists("{$uploadDir}/{$fileName}.mp4")) {
            $fileName = $fileName . '-' . $count;
            $count++;
        }

        return $fileName;
    }

    private function convertToMp4IfNeeded($inputPath, $outputDir, $baseFileName)
    {
        $outputPath = "{$outputDir}/{$baseFileName}.mp4";
        $video = $this->ffmpeg->open($inputPath);
        $video->save(new X264(), $outputPath);

        return $outputPath;
    }

    private function resizeIfLargerThan720p($inputPath, $outputDir, $baseFileName)
    {
        $outputPath = "{$outputDir}/{$baseFileName}-720p.mp4";
        $this->convertResolutionToPath($inputPath, $outputPath, 1280, 720);
        return $outputPath;
    }

    private function convertResolution($postId, $inputPath, $outputDir, $baseFileName, $width, $height)
    {
        $outputPath = "{$outputDir}/{$baseFileName}-{$height}p.mp4";
        $this->convertResolutionToPath($inputPath, $outputPath, $width, $height);
        return $this->saveToMediaLibrary($postId, $outputPath);
    }

    private function convertResolutionToPath($inputPath, $outputPath, $width, $height)
    {
        $video = $this->ffmpeg->open($inputPath);
        $video->filters()->resize(new \FFMpeg\Coordinate\Dimension($width, $height))->synchronize();
        $video->save(new X264(), $outputPath);
    }

    private function generateThumbnails($inputPath, $thumbnailsDir, $spritePath, $vttPath)
    {
        if (!file_exists($thumbnailsDir)) {
            mkdir($thumbnailsDir, 0777, true);
        }

        // Thumbnail çıkarma
        $process = new Process([
            $this->ffmpegPath,
            '-i', $inputPath,
            '-vf', 'fps=1',
            "{$thumbnailsDir}/frame-%04d.jpg"
        ]);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }

        // VTT dosyası oluşturma
        $frames = glob("{$thumbnailsDir}/frame-*.jpg");
        $vtt = "WEBVTT\n\n";

        foreach ($frames as $index => $frame) {
            $startTime = gmdate("H:i:s", $index) . ".000";
            $endTime = gmdate("H:i:s", $index + 1) . ".000";
            $vtt .= "{$index}\n{$startTime} --> {$endTime}\n" . basename($frame) . "\n\n";
        }

        file_put_contents($vttPath, $vtt);

        // Sprite görseli oluşturma
        $spriteProcess = new Process([
            'montage',
            "{$thumbnailsDir}/frame-*.jpg",
            '-tile', 'x1',
            '-geometry', '+0+0',
            $spritePath
        ]);
        $spriteProcess->run();

        if (!$spriteProcess->isSuccessful()) {
            throw new \RuntimeException($spriteProcess->getErrorOutput());
        }
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
        require_once ABSPATH . 'wp-admin/includes/image.php';
        wp_generate_attachment_metadata($attachId, $filePath);

        return $attachId;
    }
}
