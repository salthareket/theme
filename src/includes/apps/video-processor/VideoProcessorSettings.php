<?php

namespace SaltHareket\VideoProcessor;

/**
 * VideoProcessorSettings — Video Processor varsayılan ayarlarını yönetir.
 * wp_options'da saklar, ACF bağımlılığı yok.
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-06-19
 *     - Add: Initial release
 *
 * @package SaltHareket\VideoProcessor
 *
 * How to use:
 *   VideoProcessorSettings::get()           // Tüm ayarları al
 *   VideoProcessorSettings::getDefaults()   // Varsayılan değerleri al
 *   VideoProcessorSettings::save($data)     // Ayarları kaydet
 *
 * Examples:
 *   $settings = VideoProcessorSettings::get();
 *   $settings['resize_720']       // true/false
 *   $settings['encode_480']       // true/false
 *   $settings['encode_360']       // true/false
 *   $settings['poster']           // true/false
 *   $settings['sprite_vtt']       // true/false
 *   $settings['notify_on_done']   // true/false
 *   $settings['notify_recipient'] // 'admin' | 'uploader' | int (user_id)
 */
class VideoProcessorSettings
{
    private const OPTION_KEY = 'vp_default_settings';

    private static ?array $cache = null;

    /**
     * Varsayılan ayarları döner.
     */
    public static function getDefaults(): array
    {
        return [
            'resize_720'        => true,
            'encode_480'        => true,
            'encode_360'        => true,
            'poster'            => true,
            'sprite_vtt'        => true,
            'notify_on_done'    => true,
            'notify_recipient'  => 'admin', // 'admin' | 'uploader' | int
        ];
    }

    /**
     * Tüm ayarları döner. Kayıtlı değerler yoksa varsayılanlar kullanılır.
     */
    public static function get(): array
    {
        if ( self::$cache !== null ) return self::$cache;

        $saved    = get_option( self::OPTION_KEY, [] );
        $defaults = self::getDefaults();

        self::$cache = array_merge( $defaults, is_array( $saved ) ? $saved : [] );
        return self::$cache;
    }

    /**
     * Belirli bir ayar değerini döner.
     */
    public static function getSetting( string $key, $default = null )
    {
        $settings = self::get();
        return $settings[$key] ?? $default;
    }

    /**
     * Ayarları kaydet.
     */
    public static function save( array $data ): void
    {
        $settings = [
            'resize_720'        => ! empty( $data['resize_720'] ),
            'encode_480'        => ! empty( $data['encode_480'] ),
            'encode_360'        => ! empty( $data['encode_360'] ),
            'poster'            => ! empty( $data['poster'] ),
            'sprite_vtt'        => ! empty( $data['sprite_vtt'] ),
            'notify_on_done'    => ! empty( $data['notify_on_done'] ),
            'notify_recipient'  => self::sanitizeRecipient( $data['notify_recipient'] ?? 'admin' ),
        ];

        update_option( self::OPTION_KEY, $settings, false );
        self::$cache = null;

        do_action( 'sh/video_processor/settings_saved', $settings );
    }

    /**
     * Cache'i temizle.
     */
    public static function clearCache(): void
    {
        self::$cache = null;
    }

    /**
     * Alıcı değerini sanitize et.
     */
    private static function sanitizeRecipient( $value ): mixed
    {
        if ( $value === 'admin' || $value === 'uploader' ) {
            return $value;
        }
        if ( is_numeric( $value ) && (int) $value > 0 ) {
            return (int) $value;
        }
        return 'admin';
    }

    /**
     * Aktif görev listesini döner (sadece enabled olanlar).
     */
    public static function getEnabledTasks(): array
    {
        $s     = self::get();
        $tasks = [];

        if ( $s['resize_720'] )  $tasks[] = 'resize_720';
        if ( $s['encode_480'] )  $tasks[] = 'encode_480';
        if ( $s['encode_360'] )  $tasks[] = 'encode_360';
        if ( $s['poster'] )      $tasks[] = 'poster';
        if ( $s['sprite_vtt'] )  $tasks[] = 'sprite_vtt';

        return $tasks;
    }
}
