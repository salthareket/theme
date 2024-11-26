<?php

use FFMpeg\FFMpeg;
use FFMpeg\Format\Video\X264;
use FFMpeg\Coordinate\Dimension;


$themeDir = get_template_directory(); // Temanın kök dizinini al

if (stristr(PHP_OS, 'WIN')) {
    // Windows için
    $ffmpegPath = $themeDir . '/bin/b/ffmpeg.exe'; // 'bin/b' klasöründeki FFmpeg yolu
    $ffprobePath = $themeDir . '/bin/b/ffprobe.exe'; // 'bin/b' klasöründeki FFprobe yolu
} elseif (stristr(PHP_OS, 'LINUX')) {
    // Linux için
    $ffmpegPath = $themeDir . '/bin/b/ffmpeg'; // 'bin/b' klasöründeki FFmpeg yolu
    $ffprobePath = $themeDir . '/bin/b/ffprobe'; // 'bin/b' klasöründeki FFprobe yolu
} else {
    die('Desteklenmeyen işletim sistemi: ' . PHP_OS);
}

// FFMpeg yapılandırması
$ffmpeg = FFMpeg::create([
    'ffmpeg.binaries'  => $ffmpegPath,
    'ffprobe.binaries' => $ffprobePath,
]);

// Videoyu aç
$video = $ffmpeg->open('yuklenen_video.mp4');

// Videonun boyutunu kontrol et
$dimension = $video->getStreams()->videos()->first()->getDimensions();
$originalWidth = $dimension->getWidth();
$originalHeight = $dimension->getHeight();

$sizes = [
	"desktop" => 1080, 
	"tablet"  => 720,
	"phone"   => 480
];

foreach($sizes as $device => $size){


}
if($originalWidth > 1080){
	$newHeight = round($originalHeight * ($newWidth / $originalWidth));
}

// Eğer video 800 px ise
if ($originalWidth == 800) {
    // Telefon için daha düşük çözünürlükte optimize et (360p)
    $video->filters()->resize(new Dimension(640, 360))->synchronize();
    $video->save(new X264(), 'telefon_video_640x360.mp4');
    
    // Tablete uygun optimize et (800 px'e sadık kalarak)
    $video->save(new X264(), 'tablet_video_800x'.round(800 / $originalWidth * $originalHeight).'.mp4');
}
