<?php
/**
 * Theme Styles New - Preset Manager
 *
 * Preset'lerin JSON dosyası olarak kaydedilmesi, yüklenmesi, silinmesi,
 * yeniden adlandırılması, kopyalanması, export/import işlemlerini yönetir.
 * Her preset'e otomatik _meta (created, modified, colors) eklenir.
 *
 * @package SaltHareket\Theme\ThemeStyles
 * @version 2.1.0
 * @author  SaltHareket
 * @since   1.0.0
 *
 * CHANGELOG:
 * 2.1.0 - 2026-04-27
 * - duplicate() metodu eklendi
 * - rename() metodu eklendi
 * - get_all() artık meta (created, modified, colors) döndürüyor
 *
 * 2.0.0 - 2026-04-25
 * - save() metoduna _meta (name, created, modified, colors) eklendi
 * - get_all() meta bilgisini okuyor
 *
 * 1.0.0 - 2026-04-15
 * - Initial release
 *
 * HOW TO USE:
 * Preset dosyaları theme/static/data/theme-styles/presets/ klasöründe
 * JSON olarak saklanır. Tüm metodlar static'tir.
 *
 * BASIC USAGE:
 * - Tüm presetler : Theme_Styles_Preset_Manager::get_all()
 * - Kaydet        : Theme_Styles_Preset_Manager::save('my-preset', $data)
 * - Yükle         : Theme_Styles_Preset_Manager::load('my-preset')
 * - Sil           : Theme_Styles_Preset_Manager::delete('my-preset')
 * - Kopyala       : Theme_Styles_Preset_Manager::duplicate('src', 'dst')
 * - Yeniden adl.  : Theme_Styles_Preset_Manager::rename('old', 'new')
 * - Export        : Theme_Styles_Preset_Manager::export('my-preset')
 * - Import        : Theme_Styles_Preset_Manager::import($_FILES['file'])
 *
 * @example Preset kaydetme:
 * Theme_Styles_Preset_Manager::save('dark-theme', $data);
 *
 * @example Preset yükleme:
 * $data = Theme_Styles_Preset_Manager::load('dark-theme');
 * if ($data) { // kullan }
 *
 * @example Tüm presetleri listeleme:
 * $presets = Theme_Styles_Preset_Manager::get_all();
 * foreach ($presets as $name => $preset) {
 *     echo $preset['label'] . ' - ' . $preset['meta']['modified'];
 * }
 *
 * @example Kopyalama:
 * Theme_Styles_Preset_Manager::duplicate('dark-theme', 'dark-theme-v2');
 *
 * @example Yeniden adlandırma:
 * Theme_Styles_Preset_Manager::rename('dark-theme', 'night-mode');
 */

if (!defined('ABSPATH')) exit;

class Theme_Styles_Preset_Manager {
    
    /**
     * Get presets directory
     */
    private static function get_presets_dir() {
        $dir = get_template_directory() . '/theme/static/data/theme-styles/presets';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }
    
    /**
     * Get all presets
     */
    public static function get_all() {
        $dir = self::get_presets_dir();
        $presets = [];
        
        if ($handle = opendir($dir)) {
            while (false !== ($file = readdir($handle))) {
                if ($file != "." && $file != ".." && pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                    $name     = pathinfo($file, PATHINFO_FILENAME);
                    $filepath = $dir . '/' . $file;
                    $raw      = json_decode(file_get_contents($filepath), true) ?? [];
                    $meta     = $raw['_meta'] ?? [];
                    $presets[$name] = [
                        'name'   => $name,
                        'label'  => $meta['name'] ?? ucwords(str_replace(['-', '_'], ' ', $name)),
                        'file'   => $file,
                        'path'   => $filepath,
                        'meta'   => [
                            'created'  => $meta['created']  ?? filemtime($filepath),
                            'modified' => filemtime($filepath),
                            'colors'   => array_values(array_filter($meta['colors'] ?? [])),
                        ],
                    ];
                }
            }
            closedir($handle);
        }
        
        return $presets;
    }
    
    /**
     * Save preset
     */
    public static function save($name, $data) {
        $dir  = self::get_presets_dir();
        $file = $dir . '/' . sanitize_file_name($name) . '.json';

        // Metadata ekle
        $data['_meta'] = [
            'name'    => $name,
            'created' => file_exists($file)
                ? (json_decode(file_get_contents($file), true)['_meta']['created'] ?? time())
                : time(),
            'colors'  => array_filter([
                $data['colors']['primary']    ?? '',
                $data['colors']['secondary']  ?? '',
                $data['colors']['tertiary']   ?? '',
                $data['colors']['quaternary'] ?? '',
            ]),
        ];

        return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT)) !== false;
    }
    
    /**
     * Load preset
     */
    public static function load($name) {
        $dir = self::get_presets_dir();
        $file = $dir . '/' . sanitize_file_name($name) . '.json';
        
        if (file_exists($file)) {
            return json_decode(file_get_contents($file), true);
        }
        
        return null;
    }
    
    /**
     * Delete preset
     */
    public static function delete($name) {
        $dir = self::get_presets_dir();
        $file = $dir . '/' . sanitize_file_name($name) . '.json';
        
        if (file_exists($file)) {
            return unlink($file);
        }
        
        return false;
    }

    /**
     * Duplicate preset
     */
    public static function duplicate($name, $new_name) {
        $data = self::load($name);
        if (!$data) return false;
        unset($data['_meta']);
        return self::save($new_name, $data);
    }

    /**
     * Rename preset
     */
    public static function rename($name, $new_name) {
        $dir      = self::get_presets_dir();
        $old_file = $dir . '/' . sanitize_file_name($name) . '.json';
        $new_file = $dir . '/' . sanitize_file_name($new_name) . '.json';

        if (!file_exists($old_file)) return false;

        $data = json_decode(file_get_contents($old_file), true) ?? [];
        unset($data['_meta']);

        if (self::save($new_name, $data)) {
            unlink($old_file);
            return true;
        }
        return false;
    }
    
    /**
     * Export preset
     */
    public static function export($name) {
        $data = self::load($name);
        if ($data) {
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . $name . '.json"');
            echo json_encode($data, JSON_PRETTY_PRINT);
            exit;
        }
    }
    
    /**
     * Import preset
     */
    public static function import($file) {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return false;
        }
        
        $data = json_decode(file_get_contents($file['tmp_name']), true);
        if (!$data) {
            return false;
        }
        
        $name = pathinfo($file['name'], PATHINFO_FILENAME);
        return self::save($name, $data);
    }
}
