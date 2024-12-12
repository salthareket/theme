<?php

class Update {

    private static $github_repo = 'salthareket/theme'; // GitHub deposu adı
    private static $github_api_url = 'https://api.github.com/repos';
    private static $composer_lock_path = ABSPATH . 'wp-content/themes/salthareket/composer.lock';
    private static $theme_directory = ABSPATH . 'wp-content/themes/salthareket';

    // Admin notifi ekler
    public static function init() {
        add_action('admin_notices', [__CLASS__, 'check_for_update_notice']);
        add_action('admin_menu', [__CLASS__, 'add_update_page']);
        add_action('wp_ajax_update_theme_package', [__CLASS__, 'process_update']);
    }

    // Güncel sürümü composer.lock dosyasından alır
    private static function get_current_version() {
        if (!file_exists(self::$composer_lock_path)) {
            return 'Unknown';
        }

        $lock_data = json_decode(file_get_contents(self::$composer_lock_path), true);
        if (!$lock_data || empty($lock_data['packages'])) {
            return 'Unknown';
        }

        foreach ($lock_data['packages'] as $package) {
            if ($package['name'] === self::$github_repo) {
                return $package['version'];
            }
        }

        return 'Unknown';
    }

    // GitHub API üzerinden son sürümü alır
    private static function get_latest_version() {
        $url = self::$github_api_url . '/' . self::$github_repo . '/releases/latest';
        $response = wp_remote_get($url, [
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version')
            ]
        ]);

        if (is_wp_error($response)) {
            return 'Error';
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        return $data['tag_name'] ?? 'Unknown';
    }

    // Admin paneline bildirim ekler
    public static function check_for_update_notice() {
        $current_version = self::get_current_version();
        $latest_version = self::get_latest_version();

        if ($current_version === 'Unknown' || $latest_version === 'Unknown' || version_compare($current_version, $latest_version, '>=')) {
            return;
        }

        printf(
            '<div class="notice notice-warning is-dismissible"><p>Yeni bir sürüm mevcut: %s (Yüklü: %s). <a href="%s">Şimdi Güncelle</a></p></div>',
            esc_html($latest_version),
            esc_html($current_version),
            admin_url('admin.php?page=update-theme')
        );
    }

    // Güncelleme sayfasını oluşturur
    public static function add_update_page() {
        add_menu_page(
            'Theme Update',
            'Theme Update',
            'manage_options',
            'update-theme',
            [__CLASS__, 'render_update_page'],
            'dashicons-update',
            90
        );
    }

    // Güncelleme sayfasını render eder
    public static function render_update_page() {
        $current_version = self::get_current_version();
        $latest_version = self::get_latest_version();

        echo '<div class="wrap">';
        echo '<h1>Theme Update</h1>';
        printf('<p>Current Version: <strong>%s</strong></p>', esc_html($current_version));
        printf('<p>Latest Version: <strong>%s</strong></p>', esc_html($latest_version));

        if ($latest_version !== 'Unknown' && version_compare($current_version, $latest_version, '<')) {
            echo '<button id="update-theme-button" class="button button-primary">Update to ' . esc_html($latest_version) . '</button>';
        } else {
            echo '<p>Your theme is up to date.</p>';
        }

        echo '</div>';

        self::enqueue_update_script();
    }

    // Güncelleme işlemini gerçekleştiren AJAX işlemi
    public static function process_update() {
        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        global $wp_filesystem;
        if (!WP_Filesystem()) {
            wp_send_json_error(['message' => 'WP_Filesystem başlatılamadı.']);
        }

        $latest_version = self::get_latest_version();

        if ($latest_version === 'Unknown') {
            wp_send_json_error(['message' => 'Could not retrieve latest version.']);
        }

        $url = self::$github_api_url . '/' . self::$github_repo . '/zipball/' . $latest_version;
        $tmp_file = download_url($url);

        if (is_wp_error($tmp_file)) {
            wp_send_json_error(['message' => 'Failed to download the update.']);
        }

        $temp_directory = self::$theme_directory . '/vendor/salthareket/temp';
        if (!is_dir($temp_directory)) {
            mkdir($temp_directory, 0755, true);
        }

        $unzip_result = unzip_file($tmp_file, $temp_directory);
        @unlink($tmp_file);

        if (is_wp_error($unzip_result)) {
            self::delete_directory($temp_directory);
            wp_send_json_error(['message' => 'Failed to unzip the update.']);
        }

        $extracted_dir = glob($temp_directory . '/*')[0] ?? null;
        if (!$extracted_dir || !is_dir($extracted_dir)) {
            self::delete_directory($temp_directory);
            wp_send_json_error(['message' => 'Invalid extracted directory structure.']);
        }

        // Eski dosyaları kaldır
        self::delete_directory(self::$theme_directory . '/vendor/salthareket/theme');

        // Yeni dosyaları taşı
        rename($extracted_dir, self::$theme_directory . '/vendor/salthareket/theme');

        // composer.lock güncelle
        self::update_composer_lock($latest_version);

        // Geçici klasörü kaldır
        self::delete_directory($temp_directory);

        wp_send_json_success(['message' => 'Update successful!']);
    }

    private static function update_composer_lock($latest_version) {
        if (!file_exists(self::$composer_lock_path)) {
            return;
        }

        $lock_data = json_decode(file_get_contents(self::$composer_lock_path), true);
        foreach ($lock_data['packages'] as &$package) {
            if ($package['name'] === self::$github_repo) {
                $package['version'] = $latest_version;
            }
        }

        file_put_contents(self::$composer_lock_path, json_encode($lock_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private static function delete_directory($dir) {
        if (!is_dir($dir)) return;

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? self::delete_directory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private static function enqueue_update_script() {
        wp_enqueue_script(
            'theme-update-script',
            get_template_directory_uri() . '/vendor/salthareket/theme/src/update.js',
            ['jquery'],
            '1.0',
            true
        );

        wp_localize_script('theme-update-script', 'updateAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('update_theme_nonce')
        ]);
    }
}
