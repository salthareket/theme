<?php

/**
 * ImageColor
 * Extract average/dominant color from images. Ultra-fast 1px resize method.
 * Supports: jpg, png, gif, webp, avif (with Imagick/GD fallbacks).
 *
 * @version 1.0.0
 *
 * @changelog
 *   1.0.0 - 2026-04-03
 *     - Add: Initial versioned release
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // From file path (fastest — recommended)
 * $color = ImageColor::fromFile('/path/to/image.jpg');
 * // ['average_color' => '#b43463', 'contrast_color' => '#ffffff', 'mode' => 'dark', 'rgb' => [180, 52, 99]]
 *
 * // From WP attachment ID
 * $color = ImageColor::fromAttachment(123);
 *
 * // From GD resource directly
 * $img = imagecreatefromjpeg('photo.jpg');
 * $color = ImageColor::analyze($img);
 * imagedestroy($img);
 *
 * // Border average (useful for background/frame detection)
 * $color = ImageColor::analyze($img, 'border');
 *
 * // Full pixel scan (slowest, most accurate for small images)
 * $color = ImageColor::analyze($img, 'full');
 *
 * // Just need hex?
 * echo ImageColor::fromFile('image.jpg')['average_color']; // #b43463
 *
 * // Contrast color for text overlay
 * echo ImageColor::fromFile('image.jpg')['contrast_color']; // #ffffff or #000000
 *
 * ──────────────────────────────────────────────────────────
 */
class ImageColor
{
    /**
     * Get color info from file path
     * @param string $path Image file path
     * @param string $method 'resize' (fast), 'border', 'full' (slow)
     * @return array|false
     */
    public static function fromFile(string $path, string $method = 'resize') {
        if (!file_exists($path)) return false;
        $img = self::createImage($path);
        if (!$img) return false;
        $result = self::analyze($img, $method);
        imagedestroy($img);
        return $result;
    }

    /**
     * Get color info from WP attachment ID
     */
    public static function fromAttachment(int $id, string $method = 'resize') {
        $path = get_attached_file($id);
        return $path ? self::fromFile($path, $method) : false;
    }

    /**
     * Analyze a GD image resource
     * @param \GdImage $image GD image resource
     * @param string $method 'resize' | 'border' | 'full'
     * @return array ['average_color'=>'#hex', 'contrast_color'=>'#hex', 'mode'=>'light|dark', 'rgb'=>[r,g,b]]
     */
    public static function analyze($image, string $method = 'resize'): array {
        $rgb = match ($method) {
            'border' => self::extractBorder($image),
            'full'   => self::extractFull($image),
            default  => self::extractResize($image),
        };

        $hex = sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
        $contrast = self::contrastColor($rgb[0], $rgb[1], $rgb[2]);
        $mode = $contrast === '#000000' ? 'light' : 'dark';

        return [
            'average_color'  => $hex,
            'contrast_color' => $contrast,
            'mode'           => $mode,
            'rgb'            => $rgb,
        ];
    }

    // ─── EXTRACTION METHODS ──────────────────────────────

    /**
     * Ultra-fast: resize to 1px, read that pixel. GD does the averaging.
     */
    private static function extractResize($image): array {
        $w = imagesx($image);
        $h = imagesy($image);
        $pixel = imagecreatetruecolor(1, 1);
        imagecopyresampled($pixel, $image, 0, 0, 0, 0, 1, 1, $w, $h);
        $c = imagecolorat($pixel, 0, 0);
        imagedestroy($pixel);
        return [($c >> 16) & 0xFF, ($c >> 8) & 0xFF, $c & 0xFF];
    }

    /**
     * Average of border pixels only (top, bottom, left, right edges)
     */
    private static function extractBorder($image): array {
        $w = imagesx($image);
        $h = imagesy($image);
        $r = $g = $b = $n = 0;

        for ($x = 0; $x < $w; $x++) {
            self::acc($image, $x, 0, $r, $g, $b, $n);
            if ($h > 1) self::acc($image, $x, $h - 1, $r, $g, $b, $n);
        }
        for ($y = 1; $y < $h - 1; $y++) {
            self::acc($image, 0, $y, $r, $g, $b, $n);
            if ($w > 1) self::acc($image, $w - 1, $y, $r, $g, $b, $n);
        }

        return $n ? [intval($r / $n), intval($g / $n), intval($b / $n)] : [0, 0, 0];
    }

    /**
     * Full pixel scan — most accurate, slowest
     */
    private static function extractFull($image): array {
        $w = imagesx($image);
        $h = imagesy($image);
        $r = $g = $b = $n = 0;

        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                self::acc($image, $x, $y, $r, $g, $b, $n);
            }
        }

        return $n ? [intval($r / $n), intval($g / $n), intval($b / $n)] : [0, 0, 0];
    }

    private static function acc($image, int $x, int $y, int &$r, int &$g, int &$b, int &$n): void {
        $c = imagecolorat($image, $x, $y);
        $r += ($c >> 16) & 0xFF;
        $g += ($c >> 8) & 0xFF;
        $b += $c & 0xFF;
        $n++;
    }

    // ─── CONTRAST / HELPERS ──────────────────────────────

    /**
     * Returns #000000 or #ffffff based on luminance
     */
    public static function contrastColor(int $r, int $g, int $b): string {
        $luminance = 0.299 * $r + 0.587 * $g + 0.114 * $b;
        return ($luminance > 128) ? '#000000' : '#ffffff';
    }

    /**
     * Returns 'light' or 'dark' based on luminance
     */
    public static function contrastMode(int $r, int $g, int $b): string {
        return self::contrastColor($r, $g, $b) === '#000000' ? 'light' : 'dark';
    }

    /**
     * Create GD image from any supported format
     * Handles: jpg, png, gif, webp, avif (Imagick fallback for avif)
     */
    private static function createImage(string $path) {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        // AVIF needs special handling
        if ($ext === 'avif') {
            // Try Imagick first (most reliable for AVIF)
            if (class_exists('Imagick')) {
                try {
                    $im = new \Imagick($path);
                    $im->transformImageColorspace(\Imagick::COLORSPACE_RGB);
                    $blob = $im->getImageBlob();
                    $im->destroy();
                    $img = @imagecreatefromstring($blob);
                    if ($img) return $img;
                } catch (\Exception $e) {
                    // fall through
                }
            }
            // Try GD native avif
            if (function_exists('imagecreatefromavif')) {
                $img = @imagecreatefromavif($path);
                if ($img) return $img;
            }
            return false;
        }

        return match ($ext) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($path),
            'png'         => @imagecreatefrompng($path),
            'gif'         => @imagecreatefromgif($path),
            'webp'        => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default       => false,
        };
    }
}
