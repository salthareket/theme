<?php
/**
 * Optimized Avif & WebP Converter Class
 *
 * @version 1.3.1
 *
 * @changelog
 *   1.3.1 - 2026-06-18
 *     - Fix: cleanup_leftover_original_files() artık orijinal dosyaları silmiyor
 *     - Fix: Sadece converted formatları (avif/webp + suffix) temizleniyor
 *     - Fix: WP'nin kendi delete işlemi guid'deki dosyayı zaten siler
 *   1.3.0 - 2026-06-18
 *     - Add: $converted_suffix yapılandırılabilir hale getirildi (constructor parametresi)
 *     - Fix: Orijinal yüklenen görsel artık silinmiyor (sadece dönüştürülen boyutlar siliniyor)
 *     - Fix: Dönüştürülen dosyalara opsiyonel suffix ekleme desteği
 *   1.2.0 - 2026-06-17
 *     - Add: process_and_convert_metadata() sonunda orijinal dosyayı siler
 *            (convert başarılı olunca JPG/PNG/GIF disk'ten temizlenir)
 *   1.1.0 - 2026-03-31
 *     - Fix: wp_update_post yerine $wpdb->update — save_post hook tetiklenmez, metadata bozulmaz
 *     - Fix: update_attached_file yerine update_post_meta — hook zinciri kırılmaz
 *     - Fix: Thumbnail metadata (.png/.jpg) artık doğru şekilde .avif/.webp olarak güncelleniyor
 *   1.0.0 - Önceki stabil versiyon
 *
 * @package SaltHareket
 *
 * How to use:
 *   // Default kullanım (suffix yok, orijinal korunur)
 *   new AvifConverter();
 *
 *   // Custom quality
 *   new AvifConverter(85);
 *
 *   // Suffix ile (eski davranış)
 *   new AvifConverter(null, '-converted');
 *   // Sonuç: image.jpg → image-converted.avif
 *
 *   // Custom quality + suffix
 *   new AvifConverter(90, '-optimized');
 *
 * Examples:
 *   // theme.php veya functions.php içinde:
 *   
 *   // Standart kullanım (önerilen)
 *   new AvifConverter();
 *   // → image.jpg yüklenir
 *   // → image.avif oluşturulur (DB'de kayıtlı)
 *   // → image-150x150.jpg → image-150x150.avif (thumbnail)
 *   // → Orijinal image.jpg DİSKTE KORUNUR
 *
 *   // Suffix ile (geriye uyumluluk)
 *   new AvifConverter(null, '-converted');
 *   // → image.jpg yüklenir
 *   // → image-converted.avif oluşturulur
 *   // → Orijinal image.jpg KORUNUR
 *
 * Conversion logic:
 *   - Alpha channel VAR → WebP (fallback)
 *   - Alpha channel YOK → AVIF (WebP fallback)
 *   - Orijinal yüklenen dosya → KORUNUR (DB'de kayıtlı)
 *   - Thumbnail'lar → Dönüştürülür ve orijinalleri silinir
 *   - Delete attachment → Sadece converted formatları temizlenir
 */
class AvifConverter {

    private $quality = null; 
    private $converted_suffix = ''; // Boşsa suffix eklenmez, doluysa (örn: '-converted') eklenir
    private $allowed_formats = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'heic'];

    public function __construct($quality = null, $converted_suffix = '') {
        if ($quality !== null) {
            $this->quality = (int) $quality;
        }
        
        // Suffix yapılandırması (boş string ise suffix yok)
        $this->converted_suffix = (string) $converted_suffix;

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
                        
                        // Sadece dönüştürülmüş formatları sil (orijinal yüklenen değil)
                        // Thumbnail'lerin orijinal formatlarını (jpg/png) sil
                        @unlink($thumb_path);
                    }
                }
            }
        }

        // Veritabanı Güncelleme
        // wp_update_post kullanmıyoruz — save_post hook'u tetikleyip metadata'yı bozabilir
        // Doğrudan DB update ile sadece mime type güncelliyoruz
        global $wpdb;
        $wpdb->update(
            $wpdb->posts,
            ['post_mime_type' => $main_conversion['mime']],
            ['ID' => $attachment_id],
            ['%s'],
            ['%d']
        );
        clean_post_cache($attachment_id);

        // _wp_attached_file meta'sını güncelle (hook tetiklemez)
        update_post_meta($attachment_id, '_wp_attached_file', _wp_relative_upload_path($new_main_path));

        // ÖNEMLİ: Orijinal yüklenen dosyayı SİLMİYORUZ
        // Sadece dönüştürülen boyutlar (thumbnails) zaten yukarıda siliniyor
        // Orijinal dosya DB'de kayıtlı ve korunmalı

        return $metadata;
    }

    private function attempt_conversion($source_path, $target_ext) {
        $base_path = preg_replace('/\.[^.]+$/', '', $source_path);
        
        // Suffix varsa ekle (örn: image-converted.avif), yoksa direkt (örn: image.avif)
        $target_path = $base_path . $this->converted_suffix . '.' . $target_ext;

        // Kaynak ve hedef aynıysa (zaten dönüştürülmüş) skip
        if ($source_path === $target_path) return false;

        // Güvenli yazım: önce .tmp'ye yaz, başarılıysa rename et
        $tmp_path = $target_path . '.tmp';
        $quality = $this->quality ?? ($target_ext === 'webp' ? $this->get_webp_quality($source_path) : $this->get_avif_quality($source_path));

        try {
            if (class_exists('Imagick')) {
                $im = new Imagick($source_path);
                try {
                    $im->stripImage();
                    $im->setImageFormat($target_ext);
                    $im->setImageCompressionQuality($quality);

                    if ($target_ext === 'webp') {
                        $im->setOption('webp:method', '6');
                        $im->setOption('webp:sharp-yuv', 'true');
                        if ($this->looks_like_flat_graphic($source_path)) $im->setOption('webp:near-lossless', '60');
                    } else {
                        $im->setOption('avif:effort', '3');
                    }

                    $im->writeImage($tmp_path);
                } finally {
                    $im->clear();
                    $im->destroy();
                }
            } elseif (function_exists('image' . $target_ext)) {
                $img = $this->create_image_from_file($source_path);
                if (!$img) return false;
                
                if ($target_ext === 'webp') {
                    imagesavealpha($img, true);
                    imagewebp($img, $tmp_path, $quality);
                } else {
                    imageavif($img, $tmp_path, $quality);
                }
                imagedestroy($img);
            }

            // Tmp başarılıysa rename, değilse temizle
            if (file_exists($tmp_path) && filesize($tmp_path) > 0) {
                rename($tmp_path, $target_path);
                return ['path' => $target_path, 'mime' => 'image/' . $target_ext];
            }

            @unlink($tmp_path);
            return false;

        } catch (Exception $e) {
            @unlink($tmp_path);
            return false;
        }
    }

    private function get_avif_quality($path = null) {
        $q = 75;
        if ($path && $this->looks_like_flat_graphic($path)) $q = 85;
        return $q;
    }

    private function get_webp_quality($path = null) {
        $q = 88;
        if ($path && $this->looks_like_flat_graphic($path)) $q = 92;
        return $q;
    }

    private function looks_like_flat_graphic($path) {
        if (!$path || !file_exists($path)) return false;
        $info = @getimagesize($path);
        if (!$info) return false;
        return ($info[0] < 1200 && filesize($path) < 512000); // 500KB altı ve orta boy
    }

    private function has_alpha_channel($path) {
        if (class_exists('Imagick')) {
            try {
                $im = new Imagick($path);
                $alpha = $im->getImageAlphaChannel();
                $im->destroy();
                return $alpha;
            } catch (Exception $e) {
                return false;
            }
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
        if (!$file) return;

        $base = preg_replace('/\.[^.]+$/', '', $file);

        // Converted formatları temizle (suffix varsa/yoksa)
        foreach (['avif', 'webp'] as $ext) {
            $path = $base . $this->converted_suffix . '.' . $ext;
            if (file_exists($path)) @unlink($path);
        }

        // Eski -converted suffix'li dosyalar varsa onları da temizle (geriye uyumluluk)
        // Sadece şu anki suffix ile aynı değilse temizle
        if ($this->converted_suffix !== '-converted') {
            foreach (array_merge($this->allowed_formats, ['avif', 'webp']) as $ext) {
                $path = $base . '-converted.' . $ext;
                if (file_exists($path)) @unlink($path);
            }
        }
        
        // ÖNEMLİ: Orijinal dosyaları (jpg, png, gif) SİLMİYORUZ
        // WP attachment delete zaten guid'deki dosyayı siler
        // Biz sadece dönüştürülmüş formatları temizliyoruz
    }
}