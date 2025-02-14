<?php
class AvifConverter {
    private $quality;
    private $allowed_formats = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff'];

    public function __construct($quality = 75) {
        $this->quality = $quality;
        
        if ($this->isAvifSupported()) {
            add_filter('wp_handle_upload', [$this, 'handleUpload'], 10, 2);
        }
    }

    public function isAvifSupported() {
        if (class_exists('Imagick')) {
            $imagick = new Imagick();
            $formats = $imagick->queryFormats();
            if (in_array('AVIF', $formats)) {
                return true;
            }
        }
        
        if (function_exists('imageavif')) {
            return true;
        }
        
        if (shell_exec('which avifenc')) {
            return true;
        }
        
        return false;
    }

    public function handleUpload($file, $context) {
        if ($this->isFaviconUploadPage()) {
            return $file;
        }

        if ($context !== 'upload') {
            return $file;
        }

        $extension = strtolower(pathinfo($file['file'], PATHINFO_EXTENSION));

        if (in_array($extension, ['avif', 'webp'])) {
            return $file;
        }
        
        $converted_file = $this->convertToAvif($file['file']);
        
        if ($converted_file) {
            $this->updateImageMeta($file['file'], $converted_file);
            $file['file'] = $converted_file;
            $file['url'] = str_replace(wp_basename($file['url']), wp_basename($converted_file), $file['url']);
            $file['type'] = 'image/avif'; // MIME türünü AVIF olarak ayarla
        }

        return $file;
    }

    public function convertToAvif($file_path) {
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

        if (in_array($extension, ['avif', 'webp'])) {
            return $file_path;
        }

        if (!in_array($extension, $this->allowed_formats)) {
            return false;
        }

        $avif_path = preg_replace('/\.' . $extension . '$/', '.avif', $file_path);

        try {
            if (class_exists('Imagick')) {
                $imagick = new Imagick($file_path);
                $imagick->setImageFormat('avif');
                $imagick->setImageCompressionQuality($this->quality);

                // **Şeffaflık koruma ayarları**
                if ($imagick->getImageAlphaChannel() != Imagick::ALPHACHANNEL_UNDEFINED) {
                    $imagick->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
                    $imagick->setBackgroundColor('none'); // Şeffaf arka plan
                }

                $imagick->writeImage($avif_path);
                $imagick->destroy();
            } elseif (function_exists('imageavif')) {
                $image = $this->createImageFromFile($file_path);
                if (!$image) {
                    return false;
                }

                // **Şeffaflık koruma ayarları**
                imagealphablending($image, false);
                imagesavealpha($image, true);

                imageavif($image, $avif_path, $this->quality);
                imagedestroy($image);
            } elseif (shell_exec('which avifenc')) {
                shell_exec("avifenc --min 0 --max 63 --lossless -j 8 -a end-usage=q $file_path $avif_path");
            } else {
                return false;
            }
            
            return $avif_path;
        } catch (Exception $e) {
            error_log('AVIF dönüştürme hatası: ' . $e->getMessage());
            return false;
        }
    }

    private function createImageFromFile($file_path) {
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

        switch ($extension) {
            case 'png':
                return imagecreatefrompng($file_path);
            case 'gif':
                return imagecreatefromgif($file_path);
            case 'bmp':
                return imagecreatefrombmp($file_path);
            case 'jpeg':
            case 'jpg':
                return imagecreatefromjpeg($file_path);
            default:
                return false;
        }
    }

    private function updateImageMeta($original_path, $avif_path) {
        global $wpdb;
        $attachment_id = $wpdb->get_var($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE guid = %s", $original_path));

        if ($attachment_id) {
            $meta = wp_get_attachment_metadata($attachment_id);
            
            if ($meta) {
                $meta['file'] = str_replace(wp_basename($original_path), wp_basename($avif_path), $meta['file']);
                $meta['mime_type'] = 'image/avif'; // MIME türünü güncelle
                wp_update_attachment_metadata($attachment_id, $meta);
            }
        }
    }

    private function isFaviconUploadPage() {
        if (is_admin() && isset($_GET['page']) && strpos($_GET['page'], 'favicon-by-realfavicongenerator') !== false) {
            return true;
        }
        return false;
    }
}
