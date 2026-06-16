<?php
/**
 * Theme Styles New - Module Manager
 *
 * Modüllerin otomatik yüklenmesi, kaydedilmesi, öncelik sıralaması
 * ve template render işlemlerini yönetir. Her modül kendi klasöründeki
 * module.php dosyasından config array'i döner.
 *
 * @package SaltHareket\Theme\ThemeStylesNew
 * @version 1.1.0
 * @author  SaltHareket
 * @since   1.0.0
 *
 * CHANGELOG:
 * 1.1.0 - 2026-04-20
 * - get_enabled() metodu eklendi
 * - Priority bazlı sıralama eklendi
 * - Auto-load modules/ klasöründen yapılıyor
 *
 * 1.0.0 - 2026-04-15
 * - Initial release
 *
 * HOW TO USE:
 * Yeni modül eklemek için modules/{module_name}/module.php dosyası oluştur
 * ve config array'i return et. Otomatik yüklenir.
 *
 * MODULE CONFIG KEYS:
 * - id          : Modül ID (klasör adıyla aynı olmalı)
 * - title       : Sidebar'da görünen başlık
 * - description : Kısa açıklama
 * - icon        : Dashicons class
 * - priority    : Sıralama (küçük = üstte)
 * - template    : Template dosyası path'i
 * - processor   : CSS processor dosyası path'i
 * - enabled     : true/false
 *
 * @example Yeni modül module.php örneği:
 * return [
 *     'id'          => 'my_module',
 *     'title'       => 'My Module',
 *     'description' => 'Does something cool',
 *     'icon'        => 'dashicons-admin-generic',
 *     'priority'    => 50,
 *     'template'    => __DIR__ . '/template.php',
 *     'processor'   => __DIR__ . '/processor.php',
 *     'enabled'     => true,
 * ];
 *
 * @example Modül listesi alma:
 * $modules = Theme_Styles_Module_Manager::get_all();
 * $enabled = Theme_Styles_Module_Manager::get_enabled();
 *
 * @example Tek modül alma:
 * $header = Theme_Styles_Module_Manager::get('header');
 *
 * @example Template render:
 * Theme_Styles_Module_Manager::render_fields('header', $data['header'] ?? []);
 */

if (!defined('ABSPATH')) exit;

class Theme_Styles_Module_Manager {
    
    private static $modules = [];
    private static $initialized = false;
    
    /**
     * Initialize and auto-load modules
     */
    public static function init() {
        if (self::$initialized) return;
        
        self::auto_load_modules();
        self::$initialized = true;
    }
    
    /**
     * Auto-load modules from modules directory
     */
    private static function auto_load_modules() {
        $modules_dir = THEME_STYLES_NEW_PATH . '/modules';
        
        if (!is_dir($modules_dir)) return;
        
        $module_folders = glob($modules_dir . '/*', GLOB_ONLYDIR);
        
        foreach ($module_folders as $module_folder) {
            $module_name = basename($module_folder);
            $module_file = $module_folder . '/module.php';
            
            if (file_exists($module_file)) {
                $config = include $module_file;
                if (is_array($config)) {
                    self::register($module_name, $config);
                }
            }
        }
    }
    
    /**
     * Register module
     */
    public static function register($module_name, $config = []) {
        self::$modules[$module_name] = array_merge([
            'id' => $module_name,
            'title' => ucfirst($module_name),
            'description' => '',
            'icon' => 'dashicons-admin-generic',
            'priority' => 100,
            'template' => '',
            'processor' => '',
            'enabled' => true
        ], $config);
        
        // Sort by priority
        uasort(self::$modules, function($a, $b) {
            return ($a['priority'] ?? 100) - ($b['priority'] ?? 100);
        });
    }
    
    /**
     * Get module
     */
    public static function get($module_name) {
        return self::$modules[$module_name] ?? null;
    }
    
    /**
     * Get all modules
     */
    public static function get_all() {
        return self::$modules;
    }
    
    /**
     * Get enabled modules
     */
    public static function get_enabled() {
        return array_filter(self::$modules, function($module) {
            return $module['enabled'];
        });
    }
    
    /**
     * Render module fields
     */
    public static function render_fields($module_name, $data = []) {
        $module = self::get($module_name);
        if (!$module) return;
        
        $template_file = THEME_STYLES_NEW_PATH . "/modules/{$module_name}/template.php";
        if (file_exists($template_file)) {
            include $template_file;
        }
    }
}
