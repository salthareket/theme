<?php
/**
 * Optimized Avif & WebP Converter Class
 */
class AvifConverter {

    private $quality; 
    private $allowed_formats = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'heic'];

    public function __construct($quality = null) {
        if ($quality !== null) {
            $this->quality = (int) $quality;
        }

        if ($this->is_converter_supported()) {
            add_filter('wp_generate_attachment_metadata', [$this, 'process_and_convert_metadata'], 20, 2);
            add_filter('wp_editor_set_quality', [$this, 'set_editor_quality'], 10, 2);
            add_action('delete_attachment', [$this, 'cleanup_leftover_original_files']);
        }
    }

    /*public function is_converter_supported() {
        if (class_exists('Imagick')) {
            $formats = (new Imagick())->queryFormats();
            return (in_array('AVIF', $formats) || in_array('WEBP', $formats));
        }
        return (function_exists('imageavif') || function_exists('imagewebp'));
    }*/

    public function is_converter_supported() {
        // 1. Statik Cache: Bir kere kontrol ettiysek bir daha uğraşmayalım
        static $is_supported = null;
        if ($is_supported !== null) return $is_supported;

        // 2. Önce GD kütüphanesini kontrol et (Daha hızlı ve sorunsuz)
        if (function_exists('imageavif') || function_exists('imagewebp')) {
            $is_supported = true;
            return true;
        }

        // 3. GD yoksa Imagick'e bak, ama static metodla bak (Nesne oluşturmadan)
        if (class_exists('Imagick')) {
            try {
                // new Imagick() yerine statik metot kullanmak daha güvenlidir
                $formats = Imagick::queryFormats(); 
                if (in_array('AVIF', $formats) || in_array('WEBP', $formats)) {
                    $is_supported = true;
                    return true;
                }
            } catch (Exception $e) {
                // Bir hata olursa veya kilitlenirse buraya düşer
                $is_supported = false;
            }
        }

        $is_supported = false;
        return false;
    }

    public function set_editor_quality($quality, $mime_type) {
        if ($this->quality !== null) return (int) $this->quality;
        if ($mime_type === 'image/avif') return $this->get_avif_quality();
        if ($mime_type === 'image/webp') return $this->get_webp_quality();
        return $quality;
    }

    public function process_and_convert_metadata($metadata, $attachment_id) {
        $original_file_path = get_attached_file($attachment_id);
        if (!$original_file_path || !file_exists($original_file_path)) return $metadata;

        $ext = strtolower(pathinfo($original_file_path, PATHINFO_EXTENSION));
        if (!in_array($ext, $this->allowed_formats)) return $metadata;

        $has_alpha = $this->has_alpha_channel($original_file_path);
        $target_format = $has_alpha ? 'webp' : 'avif';
        
        // Ana Görsel Dönüştürme
        $main_conversion = $this->attempt_conversion($original_file_path, $target_format);
        
        // Fallback: AVIF başarısızsa WebP dene
        if (!$main_conversion && $target_format === 'avif') {
            $target_format = 'webp';
            $main_conversion = $this->attempt_conversion($original_file_path, $target_format);
        }

        if (!$main_conversion) return $metadata;

        // Metadata Güncelleme
        $new_main_path = $main_conversion['path'];
        $metadata['file'] = _wp_relative_upload_path($new_main_path);
        $metadata['filesize'] = filesize($new_main_path);

        // Thumbnail Dönüştürme
        if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
            $upload_dir = dirname($original_file_path);
            foreach ($metadata['sizes'] as $size_name => $size_data) {
                $thumb_path = $upload_dir . '/' . $size_data['file'];
                if (file_exists($thumb_path)) {
                    $thumb_conv = $this->attempt_conversion($thumb_path, $target_format);
                    if ($thumb_conv) {
                        $metadata['sizes'][$size_name]['file'] = basename($thumb_conv['path']);
                        $metadata['sizes'][$size_name]['mime-type'] = $thumb_conv['mime'];
                        $metadata['sizes'][$size_name]['filesize'] = filesize($thumb_conv['path']);
                        @unlink($thumb_path);
                    }
                }
            }
        }

        // Veritabanı Güncelleme (GUID değiştirilmez, sadece mime ve yol)
        update_attached_file($attachment_id, $new_main_path);
        wp_update_post([
            'ID' => $attachment_id,
            'post_mime_type' => $main_conversion['mime'],
        ]);

        return $metadata;
    }

    private function attempt_conversion($source_path, $target_ext) {
        $target_path = preg_replace('/\.[^.]+$/', '', $source_path) . '-converted.' . $target_ext;
        $quality = $this->quality ?? ($target_ext === 'webp' ? $this->get_webp_quality($source_path) : $this->get_avif_quality($source_path));

        try {
            if (class_exists('Imagick')) {
                $im = new Imagick($source_path);
                $im->stripImage(); // Metadata temizliği burada yapılıyor
                $im->setImageFormat($target_ext);
                $im->setImageCompressionQuality($quality);

                if ($target_ext === 'webp') {
                    $im->setOption('webp:method', '6');
                    $im->setOption('webp:sharp-yuv', 'true');
                    if ($this->looks_like_flat_graphic($source_path)) $im->setOption('webp:near-lossless', '60');
                } else {
                    $im->setOption('avif:effort', '3'); // Hız için 3 idealdir
                }

                $im->writeImage($target_path);
                $im->clear();
                $im->destroy();
            } elseif (function_exists('image' . $target_ext)) {
                $img = $this->create_image_from_file($source_path);
                if (!$img) return false;
                
                if ($target_ext === 'webp') {
                    imagesavealpha($img, true);
                    imagewebp($img, $target_path, $quality);
                } else {
                    imageavif($img, $target_path, $quality);
                }
                imagedestroy($img);
            }

            return (file_exists($target_path) && filesize($target_path) > 0) 
                ? ['path' => $target_path, 'mime' => 'image/' . $target_ext] : false;

        } catch (Exception $e) {
            //error_log("Conversion Error: " . $e->getMessage());
            return false;
        }
    }

    private function get_avif_quality($path = null) {
        $q = 75; // Dengeli varsayılan
        if ($path && $this->looks_like_flat_graphic($path)) $q = 85;
        return $q;
    }

    private function get_webp_quality($path = null) {
        return min($this->get_avif_quality($path) + 10, 92);
    }

    private function looks_like_flat_graphic($path) {
        if (!$path || !file_exists($path)) return false;
        $info = @getimagesize($path);
        if (!$info) return false;
        return ($info[0] < 1200 && filesize($path) < 512000); // 500KB altı ve orta boy
    }

    private function has_alpha_channel($path) {
        if (class_exists('Imagick')) {
            $im = new Imagick($path);
            $alpha = $im->getImageAlphaChannel();
            $im->destroy();
            return $alpha;
        }
        // PNG ise binary kontrol (Hızlı)
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'png') {
            $f = fopen($path, 'rb');
            fseek($f, 25);
            $type = ord(fread($f, 1));
            fclose($f);
            return ($type == 4 || $type == 6);
        }
        return false;
    }

    private function create_image_from_file($path) {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        switch ($ext) {
            case 'jpeg': case 'jpg': return @imagecreatefromjpeg($path);
            case 'png': return @imagecreatefrompng($path);
            case 'gif': return @imagecreatefromgif($path);
            default: return false;
        }
    }

    public function cleanup_leftover_original_files($attachment_id) {
        $file = get_attached_file($attachment_id);
        if ($file) {
            $base = preg_replace('/-converted\.\w+$/', '', $file);
            foreach ($this->allowed_formats as $ext) {
                if (file_exists($base . '.' . $ext)) @unlink($base . '.' . $ext);
            }
        }
    }
}