<?php

if (!defined('ABSPATH')) {
    require_once dirname(__DIR__, 5) . '/wp-load.php'; // WordPress kök yolunu bul
}

require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

class Silent_Upgrader_Skin extends WP_Upgrader_Skin {
    public function feedback($string, ...$args) {
        // Geri bildirimleri devre dışı bırak
    }

    public function header() {
        // Header işlemlerini devre dışı bırak
    }

    public function footer() {
        // Footer işlemlerini devre dışı bırak
    }

    public function error($errors) {
        // Hata mesajlarını loglayabiliriz
        error_log(print_r($errors, true));
    }

    public function before() {
        // Başlamadan önce yapılacak işlemler
    }

    public function after() {
        // İşlem sonrası yapılacak işlemler
    }
}



class PluginManager {

    // Check and install required plugins from the $GLOBALS["plugins"] array
    public static function check_and_install_required_plugins() {
        $required_plugins = $GLOBALS["plugins"] ?? [];

        foreach ($required_plugins as $plugin_slug) {
            // Plugin zaten yüklü mü?
            if (!self::is_plugin_installed($plugin_slug)) {
                // WordPress repository'den yükleme
                self::install_plugin_from_wp_repo($plugin_slug);
            }
        }
    }


    // Check and update local plugins from the $GLOBALS["plugins_local"] array
    public static function check_and_update_local_plugins() {
        $required_plugins_local = $GLOBALS["plugins_local"] ?? [];
        $plugin_dir = __DIR__ . '/plugins';

        foreach ($required_plugins_local as $plugin_info) {
            $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_info['name'];

            // Check if the plugin exists and if the version is outdated
            if (!file_exists($plugin_path) || self::is_version_outdated($plugin_info['v'], $plugin_info['name'])) {
                self::remove_plugin($plugin_info['name']);
                self::install_local_plugin($plugin_dir, $plugin_info);
            }

            self::activate_plugin($plugin_info['name']);
        }
    }

    // Check if a plugin is installed
    private static function is_plugin_installed($plugin_name) {
        $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_name;
        if (file_exists($plugin_path)) {
            error_log("Plugin zaten yüklü: " . $plugin_name);
            return true;
        } else {
            error_log("Plugin yüklü değil: " . $plugin_name);
            return false;
        }
    }


    private static function install_local_plugin($plugin_dir, $plugin_info) {
        $zip_file = $plugin_dir . '/' . $plugin_info['file'] . '.zip';

        if (file_exists($zip_file)) {
            include_once ABSPATH . 'wp-admin/includes/file.php';
            include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

            WP_Filesystem();
            $skin = new Silent_Upgrader_Skin(); // Özel skin'i kullan
            $upgrader = new Plugin_Upgrader($skin);
            $upgrader->install($zip_file);

            // Yükleme sonrası dizini kontrol et
            $plugin_path = WP_PLUGIN_DIR . '/' . dirname($plugin_info['name']);
            if (!file_exists($plugin_path)) {
                error_log("Plugin yüklenemedi: " . $plugin_info['name']);
            }
        } else {
            error_log("Plugin zip file not found: " . $zip_file);
        }
    }


    private static function install_plugin_from_wp_repo($plugin_slug) {
        include_once ABSPATH . 'wp-admin/includes/file.php';
        include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        WP_Filesystem();
        $skin = new Silent_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader($skin);

        // Slug'ı temizle
        $slug_parts = explode('/', $plugin_slug);
        $clean_slug = $slug_parts[0]; // İlk kısmı al

        $plugin_url = 'https://downloads.wordpress.org/plugin/' . $clean_slug . '.zip';

        $result = $upgrader->install($plugin_url);

        if (is_wp_error($result)) {
            error_log("Plugin yüklenemedi: " . $clean_slug . " - " . $result->get_error_message());
        } else {
            error_log("Plugin başarıyla yüklendi: " . $clean_slug);
        }
    }


    // Activate a plugin
    private static function activate_plugin($plugin_name) {
        if (!is_plugin_active($plugin_name)) {
            //activate_plugin($plugin_name);
        }
    }

    // Remove an outdated plugin
    private static function remove_plugin($plugin_name) {
        $plugin_path = WP_PLUGIN_DIR . '/' . dirname($plugin_name);

        if (file_exists($plugin_path)) {
            self::delete_directory($plugin_path);
        }
    }

    // Check if a plugin version is outdated
    private static function is_version_outdated($new_version, $plugin_name) {
        $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_name, false, false);
        return version_compare($plugin_data['Version'], $new_version, '<');
    }

    // Utility function to delete a directory
    private static function delete_directory($dir) {
        if (!is_dir($dir)) return;

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? self::delete_directory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
