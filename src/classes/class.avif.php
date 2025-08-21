<?php
/**
 * Avif & WebP Converter Class (quality-tuned)
 *
 * - WebP kalite, AVIF’ten bağımsız ve genelde daha yüksek tutulur.
 * - Logolar/flat görseller için WebP kalitesi ekstra artırılır; sharp_yuv açılır.
 */
class AvifConverter {

    private $quality; // eski tek kalite alanı (opsiyonel override)
    private $allowed_formats = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'heic'];

    public function __construct($quality = null) {

        if ($quality !== null) {
            $this->quality = (int) $quality; // manuel override
        }

        if ($this->is_converter_supported()) {
            add_filter('wp_generate_attachment_metadata', [$this, 'process_and_convert_metadata'], 20, 2);
            add_filter('wp_editor_set_quality', [$this, 'set_editor_quality'], 10, 2);
            add_action('delete_attachment', [$this, 'cleanup_leftover_original_files']);
        }
    }

    public function cleanup_leftover_original_files($attachment_id) {
        $converted_path = get_attached_file($attachment_id);
        if (!$converted_path || !file_exists($converted_path)) return;

        // uploads/2025/08/image-converted.avif → image
        $base_path = preg_replace('/-converted\.\w+$/', '', $converted_path);

        foreach ($this->allowed_formats as $ext) {
            $original_candidate = $base_path . '.' . $ext;
            if (file_exists($original_candidate)) {
                @unlink($original_candidate);
                error_log("AVIF/WebP Converter: Orijinal kaynak dosya silindi → {$original_candidate}");
                break;
            }
        }
    }

    public function is_converter_supported() {
        if (class_exists('Imagick')) {
            $formats = (new Imagick())->queryFormats();
            if (in_array('AVIF', $formats) || in_array('WEBP', $formats)) {
                return true;
            }
        }
        if (function_exists('imageavif') || function_exists('imagewebp')) {
            return true;
        }
        return false;
    }

    /**
     * WP editor kalite filtresi: MIME’e göre ayrı kalite döndür.
     */
    public function set_editor_quality($quality, $mime_type) {
        // Manuel override edilmişse onu kullan
        if ($this->quality !== null) {
            return (int) $this->quality;
        }

        if ($mime_type === 'image/avif') {
            return $this->get_avif_quality();
        } elseif ($mime_type === 'image/webp') {
            return $this->get_webp_quality();
        }
        return $quality;
    }

    private function clean_and_rewrite_original($file_path) {
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        $temp_cleaned = preg_replace('/\.' . preg_quote($ext, '/') . '$/i', '', $file_path) . '-cleaned.' . $ext;

        try {
            if (class_exists('Imagick')) {
                $image = new Imagick($file_path);
                $image->stripImage();
                $image->setImageCompressionQuality(85);
                $image->writeImage($temp_cleaned);
                $image->destroy();
            } else {
                $image = $this->create_image_from_file($file_path);
                if (!$image) return false;

                switch ($ext) {
                    case 'jpg':
                    case 'jpeg':
                        imagejpeg($image, $temp_cleaned, 85);
                        break;
                    case 'png':
                        imagepng($image, $temp_cleaned, 9);
                        break;
                    case 'gif':
                        imagegif($image, $temp_cleaned);
                        break;
                    default:
                        return false;
                }
                imagedestroy($image);
            }

            if (file_exists($temp_cleaned) && filesize($temp_cleaned) > 0) {
                @unlink($file_path);
                @rename($temp_cleaned, $file_path);
                return true;
            }

        } catch (Exception $e) {
            error_log("Image Clean Error: " . $e->getMessage());
            return false;
        }

        return false;
    }

    public function process_and_convert_metadata($metadata, $attachment_id) {
        $original_file_path = get_attached_file($attachment_id);

        // Başında izin verilen format kontrolü
        $ext = strtolower(pathinfo($original_file_path, PATHINFO_EXTENSION));
        if (!in_array($ext, $this->allowed_formats)) {
            // Örn. SVG, TIFF vs. gibi izin verilmeyenleri atla
            return $metadata;
        }

        $this->clean_and_rewrite_original($original_file_path);

        if (!$original_file_path || !file_exists($original_file_path) || !in_array(strtolower(pathinfo($original_file_path, PATHINFO_EXTENSION)), $this->allowed_formats)) {
            return $metadata;
        }

        $original_metadata = $metadata;
        $files_to_delete = [];
        $conversion_failed = false;

        // 1) Tüm set için hedef format
        $has_alpha = $this->has_alpha_channel($original_file_path);
        $target_format = $has_alpha ? 'webp' : 'avif';
        $fallback_format = 'webp';

        // 2) Ana görsel
        $original_file_backup = $original_file_path . '.bak';
        if (!file_exists($original_file_backup)) {
            @copy($original_file_path, $original_file_backup);
        }
        $main_conversion_result = $this->attempt_conversion($original_file_path, $target_format);

        if (!$main_conversion_result && $target_format === 'avif') {
            error_log("AVIF/WebP Converter: AVIF ana görsel başarısız. Fallback '{$fallback_format}' deneniyor.");
            $target_format = $fallback_format;
            $main_conversion_result = $this->attempt_conversion($original_file_path, $target_format);
        }

        if (!$main_conversion_result) {
            error_log("AVIF/WebP Converter: Ana görsel tüm denemelerde başarısız. Attachment ID {$attachment_id}.");
            return $metadata;
        }

        $new_main_path = $main_conversion_result['path'];
        $new_main_mime = $main_conversion_result['mime'];
        $metadata['file'] = _wp_relative_upload_path($new_main_path);
        $metadata['filesize'] = file_exists($new_main_path) ? filesize($new_main_path) : null;

        // 3) Thumb’lar aynı hedef formata
        if (isset($original_metadata['sizes']) && is_array($original_metadata['sizes'])) {
            $original_file_dir = dirname($original_file_path);
            foreach ($original_metadata['sizes'] as $size_name => $size_data) {
                $original_thumb_path = $original_file_dir . '/' . $size_data['file'];
                if (file_exists($original_thumb_path)) {
                    $thumb_conversion_result = $this->attempt_conversion($original_thumb_path, $target_format);
                    if ($thumb_conversion_result) {
                        $metadata['sizes'][$size_name]['file'] = basename($thumb_conversion_result['path']);
                        $metadata['sizes'][$size_name]['mime-type'] = $thumb_conversion_result['mime'];
                        $metadata['sizes'][$size_name]['filesize'] = file_exists($thumb_conversion_result['path']) ? filesize($thumb_conversion_result['path']) : null;
                        $files_to_delete[] = $original_thumb_path;
                    } else {
                        error_log("AVIF/WebP Converter: '{$size_name}' thumbnail '{$target_format}' dönüştürme başarısız. Atlanacak. ID {$attachment_id}.");
                        $conversion_failed = true;
                    }
                }
            }
        }

        // 4) DB güncelle
        $new_guid = str_replace(basename($original_file_path), basename($new_main_path), get_the_guid($attachment_id));
        update_attached_file($attachment_id, $new_main_path);
        wp_update_post([
            'ID' => $attachment_id,
            'post_mime_type' => $new_main_mime,
            'guid' => $new_guid,
        ]);

        // 5) Sadece eski thumb’ları sil
        if (!$conversion_failed) {
            error_log("AVIF/WebP Converter: Silinecek thumb’lar #{$attachment_id}: " . print_r($files_to_delete, true));
            foreach ($files_to_delete as $file_to_delete) {
                if (file_exists($file_to_delete)) {
                    @unlink($file_to_delete);
                }
            }
        } else {
            error_log("AVIF/WebP Converter: Bazı thumb hataları nedeniyle silme atlandı (#{$attachment_id}).");
        }

        // 6) .bak temizliği
        $bak_file = $original_file_path . '.bak';
        if (file_exists($bak_file)) {
            @unlink($bak_file);
            error_log("AVIF/WebP Converter: .bak dosyası silindi -> {$bak_file}");
        }

        return $metadata;
    }

    /**
     * Asıl dönüşüm – kaliteyi hedef formata göre seç.
     */
    private function attempt_conversion($source_path, $target_extension) {
        $source_extension = strtolower(pathinfo($source_path, PATHINFO_EXTENSION));
        $target_mime = 'image/' . $target_extension;

        $target_path = preg_replace('/\.' . preg_quote($source_extension, '/') . '$/i', '', $source_path);
        $target_path .= '-converted.' . $target_extension;

        // Kalite seçimi (manuel override varsa kullan; yoksa format’a göre)
        $quality = ($this->quality !== null)
            ? (int) $this->quality
            : ($target_extension === 'webp'
                ? $this->get_webp_quality($source_path)
                : $this->get_avif_quality($source_path));

        try {
            if (class_exists('Imagick')) {
                $imagick = new Imagick($source_path);
                $formats = $imagick->queryFormats();
                if (!in_array(strtoupper($target_extension), $formats)) {
                    $imagick->destroy();
                    return false;
                }

                $imagick->setImageFormat($target_extension);
                $imagick->setImageCompressionQuality($quality);

                // Ortak temizleme
                $imagick->stripImage();

                if ($target_extension === 'webp') {
                    // WebP kalite iyileştirmeleri
                    // method: 0-6 (6 = en iyi)
                    $imagick->setOption('webp:method', '6');
                    // chroma subsampling keskinliği
                    $imagick->setOption('webp:sharp_yuv', 'true');
                    // alfa kanalı kalitesi (0-100)
                    $imagick->setOption('webp:alpha-quality', (string) max($quality, 85));
                    // near-lossless = 0 (lossy), 60-100 arası near-lossless mod; logosu çok düz olan görsellerde 60 deneyebiliriz
                    if ($this->looks_like_flat_graphic($source_path)) {
                        $imagick->setOption('webp:near-lossless', '60');
                    } else {
                        $imagick->setOption('webp:near-lossless', '0');
                    }
                } else { // AVIF
                    // effort: 0 (hızlı) – 9 (yavaş/iyi). Orta-iyi denge.
                    $imagick->setOption('avif:effort', '4');
                    // bazı imagick sürümlerinde heif:encoding-speed / heic:speed bulunabilir; bilinmeyen optionlar sessizce yok sayılır.
                    $imagick->setOption('heic:speed', '4');
                }

                $imagick->writeImage($target_path);
                $imagick->destroy();

            } elseif (function_exists('image' . $target_extension)) {
                $image = $this->create_image_from_file($source_path);
                if (!$image) return false;

                if (!imageistruecolor($image)) {
                    imagepalettetotruecolor($image);
                }

                if ($target_extension === 'webp') {
                    imagealphablending($image, false);
                    imagesavealpha($image, true);
                    imagewebp($image, $target_path, $quality);
                } else { // avif
                    imageavif($image, $target_path, $quality);
                }
                imagedestroy($image);
            } else {
                return false;
            }

            if (file_exists($target_path) && filesize($target_path) > 0) {
                return ['path' => $target_path, 'mime' => $target_mime];
            } else {
                if (file_exists($target_path)) @unlink($target_path);
                return false;
            }
        } catch (Exception $e) {
            error_log("AVIF/WebP Conversion Exception ({$target_extension}): " . $e->getMessage() . " for file " . $source_path);
            return false;
        }
    }

    /**
     * AVIF kalite mantığı.
     * Varsayılanı dışarıdaki kalite fonksiyonundan alır; yoksa 75.
     */
    private function get_avif_quality($source_path = null) {
        if (function_exists('get_google_optimized_avif_quality')) {
            $q = (int) get_google_optimized_avif_quality($source_path);
        } else {
            $q = 75;
        }
        // Çok düz/ikonik görseller AVIF’te de biraz daha kalite isteyebilir
        if ($this->looks_like_flat_graphic($source_path)) {
            $q = max($q, 80);
        }
        return max(40, min($q, 90));
    }

    /**
     * WebP kalite mantığı.
     * AVIF’e göre +10 buffer; logo/flat görselde min 88-92 aralığına çekilir.
     */
    private function get_webp_quality($source_path = null) {
        // Temel kaliteyi AVIF fonksiyonundan türet
        $base = $this->get_avif_quality($source_path);
        $q = min($base + 10, 95);

        // Alfa kanalı veya düz grafikse kaliteyi yükselt
        $ext = strtolower(pathinfo((string)$source_path, PATHINFO_EXTENSION));
        if (in_array($ext, ['png','gif']) || $this->has_alpha_channel((string)$source_path) || $this->looks_like_flat_graphic($source_path)) {
            $q = max($q, 92);
        }
        return max(70, min($q, 95));
    }

    /**
     * Basit bir “flat/ikonik görsel” sezgisi:
     * - PNG/GIF
     * - veya genişlik/ yükseklik küçük/orta ve dosya boyutu da küçükse
     */
    private function looks_like_flat_graphic($file_path = null) {
        if (!$file_path || !file_exists($file_path)) return false;
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        if (in_array($ext, ['png','gif'])) return true;

        if (function_exists('getimagesize')) {
            $info = @getimagesize($file_path);
            if (is_array($info)) {
                [$w, $h] = $info;
                $size = filesize($file_path) ?: 0;
                // Küçük/orta logo benzeri: genişlik veya yükseklik 1200’den küçük ve dosya boyutu < 600KB
                if ((($w && $w < 1200) || ($h && $h < 1200)) && $size > 0 && $size < 600 * 1024) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Şeffaflık kontrolü (alpha).
     */
    private function has_alpha_channel($file_path) {
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

        if (class_exists('Imagick')) {
            try {
                $imagick = new Imagick($file_path);
                $has_alpha = $imagick->getImageAlphaChannel();
                $imagick->destroy();
                if ($has_alpha) return true;
            } catch (Exception $e) {}
        }

        if ($extension === 'png') {
            if ($this->png_has_transparency($file_path)) return true;

            if (function_exists('imagecreatefrompng')) {
                $im = @imagecreatefrompng($file_path);
                if ($im) {
                    $width = imagesx($im);
                    $height = imagesy($im);
                    for ($x = 0; $x < $width; $x++) {
                        for ($y = 0; $y < $height; $y++) {
                            $rgba = imagecolorat($im, $x, $y);
                            $alpha = ($rgba & 0x7F000000) >> 24;
                            if ($alpha > 0) {
                                imagedestroy($im);
                                return true;
                            }
                        }
                    }
                    imagedestroy($im);
                }
            }
        }

        if ($extension === 'gif') return true;

        return false;
    }

    private function png_has_transparency($file_path) {
        if (!function_exists('imagecreatefrompng')) return false;

        $image = @imagecreatefrompng($file_path);
        if (!$image) return false;

        $width = imagesx($image);
        $height = imagesy($image);

        for ($x = 0; $x < $width; ++$x) {
            for ($y = 0; $y < $height; ++$y) {
                $rgba = imagecolorat($image, $x, $y);
                $alpha = ($rgba & 0x7F000000) >> 24;
                if ($alpha > 0) {
                    imagedestroy($image);
                    return true;
                }
            }
        }

        imagedestroy($image);
        return false;
    }

    private function create_image_from_file($file_path) {
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        switch ($extension) {
            case 'png': return @imagecreatefrompng($file_path);
            case 'gif': return @imagecreatefromgif($file_path);
            case 'jpg':
            case 'jpeg': return @imagecreatefromjpeg($file_path);
            default: return false;
        }
    }
}
