<?php

class PluginManager {

    // Check and install required plugins from the $GLOBALS["plugins"] array
    public static function check_and_install_required_plugins() {
        $required_plugins = $GLOBALS["plugins"] ?? [];

        foreach ($required_plugins as $plugin) {
            if (!self::is_plugin_installed($plugin)) {
                self::install_plugin_via_composer($plugin);
            }
            self::activate_plugin($plugin);
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
        return file_exists(WP_PLUGIN_DIR . '/' . $plugin_name);
    }

    // Install a plugin via Composer
    private static function install_plugin_via_composer($plugin_name) {
        $command = "composer require " . escapeshellarg($plugin_name);
        exec($command, $output, $return_var);

        if ($return_var !== 0) {
            error_log("Failed to install plugin via Composer: $plugin_name");
        }
    }

    // Install a local plugin from the plugins directory
    private static function install_local_plugin($plugin_dir, $plugin_info) {
        $zip_file = $plugin_dir . '/' . $plugin_info['file'] . '.zip';
        $unzip_path = WP_PLUGIN_DIR . '/' . dirname($plugin_info['name']);

        if (file_exists($zip_file)) {
            $zip = new ZipArchive;
            if ($zip->open($zip_file) === true) {
                $zip->extractTo($unzip_path);
                $zip->close();
            } else {
                error_log("Failed to extract plugin zip file: " . $zip_file);
            }
        } else {
            error_log("Plugin zip file not found: " . $zip_file);
        }
    }

    // Activate a plugin
    private static function activate_plugin($plugin_name) {
        if (!is_plugin_active($plugin_name)) {
            activate_plugin($plugin_name);
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
