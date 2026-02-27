<?php

use FFMpeg\FFMpeg;
use FFMpeg\Format\Video\X264;
use FFMpeg\Filters\Video\ResizeFilter;
use FFMpeg\Coordinate\Dimension;

class VideoProcessor{
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

    public function update_video_task($post_id = 0, $task = []){
        $video_tasks = get_post_meta($post_id, 'video_tasks', true);
        if($task){
            $video_tasks[$task["index"]] = $task;
            $remove = true;
            foreach($video_tasks as $video_task){
                if(isset($video_task["tasks"])){
                    $remove = false;
                    continue;
                }
            }
            //error_log(print_r($video_tasks, true));
            //error_log("remove:" . $remove);
            if($remove){
                delete_post_meta($post_id, 'video_tasks');
            }else{
                update_post_meta($post_id, 'video_tasks', $video_tasks);
            }
        }
    }
    public function get_dimensions($inputPath){
        $video = $this->ffmpeg->open($inputPath);
        $videoInfo = $video->getStreams()->videos()->first();
        return [
            "width"  => $videoInfo->get('width'),
            "height" => $videoInfo->get('height')
        ];
    }

    public function processVideo($post_id, $inputPath, $inputId, $video_task = []){
        if(!$this->available){
            return [];
        }
        $uploadDir = wp_upload_dir();
        $outputDir = $uploadDir['path'];
        $baseFileName = $this->sanitizeFileName($inputPath, $inputId);
        $results = [];
        
        if (pathinfo($inputPath, PATHINFO_EXTENSION) != 'mp4') {
            $inputPath = $this->convertToMp4IfNeeded($inputPath, $outputDir, $baseFileName);
        }

        $dimensions = $this->get_dimensions($inputPath);

        $task_index = 0;
        $sizes = [];
        $thumbnails = false;
        $poster = false;
        if($video_task){
            $task_index = $video_task['index'];
            $sizes = isset($video_task["tasks"]['sizes']) ? array_keys($video_task["tasks"]['sizes']) : [];
            $poster = isset($video_task["tasks"]['poster']);
            $thumbnails = isset($video_task["tasks"]['thumbnails']);
        }

        if ($dimensions["height"] > 720) {
            $inputPath    = $this->resizeIfLargerThan720p($inputPath, $outputDir, $baseFileName, $dimensions["width"], $dimensions["height"]);
            $results[720] = $this->saveToMediaLibrary($post_id, $inputPath, $inputId);
        }
        $video_task["tasks"]['sizes']["720"] = true;
        $this->update_video_task($post_id, $video_task);
        
        if ($sizes) {
            $resolutions = is_array($sizes) ? $sizes : [480, 360];
            foreach ($resolutions as $height) {
                if($resolutions != 720){
                    $width = (int) round($height * ($dimensions["width"] / $dimensions["height"]));
                    $width = round($width / 2) * 2;
                    $height = round($height / 2) * 2;
                    $results[$height] = $this->convertResolution($post_id, $inputPath, $outputDir, $baseFileName, $width, $height);
                    $video_task["tasks"]['sizes'][$height] = true;
                    $this->update_video_task($post_id, $video_task);                    
                }
            }
        }
        
        if($poster){
            $posterPath = "{$outputDir}/{$baseFileName}-poster.jpg";
            $this->generatePosterFrame($inputPath, $posterPath);
            $results['poster'] = $this->saveToMediaLibrary($post_id, $posterPath);
            $video_task["tasks"]['poster'] = true;
            $this->update_video_task($post_id, $video_task);             
        }

        if ($thumbnails) {
            $thumbnailVideo = in_array(360, $sizes) ? "{$outputDir}/{$baseFileName}-360p.mp4" : "";
            if(empty($thumbnailVideo)){
                $thumbnailVideo = in_array(480, $sizes) ? "{$outputDir}/{$baseFileName}-480p.mp4" : $inputPath;
            }
            $thumbnailsDir = "{$outputDir}";//"{$outputDir}/thumbnails";
            $spritePath = "{$thumbnailsDir}/{$baseFileName}.jpg";
            $vttPath = "{$thumbnailsDir}/{$baseFileName}.vtt";

            $this->generateThumbnails($thumbnailVideo, $thumbnailsDir, $spritePath, $vttPath);

            $results['thumbnails'] = $this->saveToMediaLibrary($post_id, $spritePath);
            $results['vtt'] = $this->saveToMediaLibrary($post_id, $vttPath);

            $video_task["tasks"]['thumbnails'] = true;
            $this->update_video_task($post_id, $video_task); 
        }
        unset($video_task["tasks"]);
        $this->update_video_task($post_id, $video_task); 

        return $results;
    }

    /*private function sanitizeFileName($post_id){
        $postType = get_post_type($post_id);
        $uniqueString = $postType . '-' . $post_id;
        return md5($uniqueString);
    }*/
    private function sanitizeFileName($inputPath, $attachment_id) {
        $filename = pathinfo($inputPath, PATHINFO_FILENAME);
        $extension = pathinfo($inputPath, PATHINFO_EXTENSION);
        if (empty($extension)) {
            $extension = 'mp4';
        }
        return $filename;// . "-" . $attachment_id.".".$extension;
    }

    private function convertToMp4IfNeeded($inputPath, $outputDir, $baseFileName){

        $outputPath = "{$outputDir}/{$baseFileName}.mp4";

        $video = $this->ffmpeg->open($inputPath);

        $format = new X264('aac', 'libx264');
        $audioStream = $video->getStreams()->audios()->first();
        if (!$audioStream) {
            //error_log("Sessiz video işleniyor.");
            $format->setAudioCodec("copy");
        }

        $video->save($format, $outputPath);

        return $outputPath;
    }
    private function resizeIfLargerThan720p($inputPath, $outputDir, $baseFileName, $originalWidth, $originalHeight){
        $targetHeight = 720;
        $targetWidth = (int) round($targetHeight * ($originalWidth / $originalHeight));

        $outputPath = "{$outputDir}/{$baseFileName}.mp4";
        $this->convertResolutionToPath($inputPath, $outputPath, $targetWidth, $targetHeight);
        return $outputPath;
    }
    private function convertResolution($post_id, $inputPath, $outputDir, $baseFileName, $width, $height){
        $outputPath = "{$outputDir}/{$baseFileName}-{$height}p.mp4";
        $this->convertResolutionToPath($inputPath, $outputPath, $width, $height);
        return $this->saveToMediaLibrary($post_id, $outputPath);
    }
    private function convertResolutionToPath($inputPath, $outputPath, $width, $height) {
        $video = $this->ffmpeg->open($inputPath);

        // Orijinal video bilgilerini al
        $video_info = $video->getStreams()->videos()->first()->all();
        $original_bitrate = isset($video_info['bit_rate']) ? (int) $video_info['bit_rate'] / 1000 : 2000; // Varsayılan: 2000 kbps

        // CRF ve Preset Ayarları
        $crf = 23; // Varsayılan CRF
        $preset = 'veryslow'; // Yavaş kodlama, daha küçük dosya boyutu
        if ($height == 720) {
            $crf = 23;
        } elseif ($height == 480) {
            $crf = 28;
        } elseif ($height == 360) {
            $crf = 32;
        }

        // Ölçeklenmiş bitrate hesaplama
        $scaled_bitrate = $original_bitrate * ($height / $video_info['height']);

        // Çözünürlük ayarlama
        $dimension = new Dimension($width, $height);
        $video->filters()
            ->resize($dimension, ResizeFilter::RESIZEMODE_INSET, true)
            ->synchronize();

        // X264 formatını oluştur
        $format = new X264('aac', 'libx264');
        $format
            ->setAdditionalParameters([
                '-crf', (string) $crf,       // CRF (Kalite Faktörü)
                '-preset', $preset,          // Kodlama hızı (veryslow daha küçük dosya boyutu sağlar)
                '-tune', 'film',             // Görüntü kalitesini optimize etmek için
                '-movflags', '+faststart',   // Hızlı başlangıç için
                //'-b:v', '1000k'
            ])
            ->setKiloBitrate((int)$scaled_bitrate) // Ölçeklenmiş bitrate
            ->setAudioChannels(2) // Ses kanalı
            ->setAudioKiloBitrate(128); // Sabit ses bitrate

        // Video dosyasını kaydet
        $video->save($format, $outputPath);

        // İşlem sonrası video detaylarını logla
        $this->logVideoDetails($outputPath);

        // Debug için log
        //error_log("Processed Video - Height: $height, CRF: $crf, Scaled Bitrate: $scaled_bitrate kbps");
        /////error_log(print_r($video_info, true));
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
        $frameInterval = 5; // Frame aralığı (sn)
        $video = $this->ffmpeg->open($inputPath);
        $duration = $video->getFormat()->get('duration');

        for ($i = 0; $i < $duration; $i += $frameInterval) {
            $framePath = "{$thumbnailsDir}/frame-" . sprintf('%04d', $i) . ".jpg";
            $video->frame(\FFMpeg\Coordinate\TimeCode::fromSeconds($i))->save($framePath);
            $this->resizeImage($framePath, 150); // Thumbnail genişliği 150 piksel
            $frames[] = $framePath;
        }

        $this->createSprite($frames, $spritePath);

        // Frame intervalini VTT fonksiyonuna da gönder
        $this->createVtt($frames, $spritePath, $vttPath, $frameInterval);

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
    private function createVtt($frames, $spritePath, $vttPath, $frameInterval) {
        $columns = 15;
        $vtt = "WEBVTT\n\n";

        foreach ($frames as $index => $frame) {
            list($width, $height) = getimagesize($frame);

            $x = ($index % $columns) * $width;
            $y = floor($index / $columns) * $height;

            $startTime = gmdate("H:i:s", $index * $frameInterval);
            $endTime = gmdate("H:i:s", ($index + 1) * $frameInterval);

            $vtt .= "{$index}\n{$startTime}.000 --> {$endTime}.000\n";
            $vtt .= basename($spritePath) . "#xywh={$x},{$y},{$width},{$height}\n\n";
        }

        file_put_contents($vttPath, $vtt);
    }

    private function saveToMediaLibrary($post_id, $filePath, $attachment_id = null){
        $uploadDir = wp_upload_dir(); 
        $fileUrl = $uploadDir['url'] . '/' . basename($filePath);
        $filetype = wp_check_filetype(basename($filePath));

        if ($attachment_id) {
            $metadata = wp_get_attachment_metadata($attachment_id) ?: [];
            if (strpos($filetype['type'], 'video') !== false) {
                $dimensions = $this->get_dimensions($filePath);
                $metadata['width'] = $dimensions['width'];
                $metadata['height'] = $dimensions['height'];
            } else {
                list($metadata['width'], $metadata['height']) = getimagesize($filePath);
            }
            $metadata['file'] = str_replace($uploadDir['basedir'] . '/', '', $filePath);
            wp_update_attachment_metadata($attachment_id, $metadata);
            return $attachment_id; // Mevcut ID'yi döndür
        }

        $attachment = [
            'post_mime_type' => $filetype['type'],
            'post_title'     => sanitize_file_name(basename($filePath)),
            'post_content'   => '',
            'post_status'    => 'inherit',
            'guid'           => $fileUrl,
        ];
        $attachId = wp_insert_attachment($attachment, $filePath, $post_id);
        add_filter('intermediate_image_sizes_advanced', [$this, 'disableIntermediateImageSizes']);
        $metadata = wp_generate_attachment_metadata($attachId, $filePath);
        wp_update_attachment_metadata($attachId, $metadata);
        remove_filter('intermediate_image_sizes_advanced', [$this, 'disableIntermediateImageSizes']);
        return $attachId;
    }

    public function disableIntermediateImageSizes($sizes){
        // Thumbnail, medium, large gibi boyutları kaldır
        return [];
    }

    public function updateVideoMeta($result = []){
        if (!isset($result['720'])) {
            return false;
        }
        $attachment_id = $result['720'];
        if(isset($result['480'])){
            update_post_meta($attachment_id, 'tablet', $result['sizes']['480']);
        }
        if(isset($result['360'])){
            update_post_meta($attachment_id, 'phone', $result['sizes']['360']);
        }
        if(isset($result['poster'])){
            update_post_meta($attachment_id, 'poster', $result['poster']);
        }
        if(isset($result['thumbnails'])){
            update_post_meta($attachment_id, 'thumbnails', $result['thumbnails']);
        }
        if(isset($result['vtt'])){
            update_post_meta($attachment_id, 'vtt', $result['vtt']);
        }
        return true;
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
}
