<?php

class Update {

    private static $github_repo = 'salthareket/theme'; // GitHub deposu adı
    private static $github_api_url = 'https://api.github.com/repos';
    private static $composer_lock_path;
    private static $repo_directory;
    private static $vendor_directory;

    // Admin notifi ekler
    public static function init() {
        self::initialize_paths();
        add_action('admin_notices', [__CLASS__, 'check_for_update_notice']);
        add_action('admin_menu', [__CLASS__, 'add_update_page']);
        add_action('wp_ajax_update_theme_package', [__CLASS__, 'process_update']);
    }

    // Dinamik yolları başlat
    private static function initialize_paths() {
        $theme_root = get_template_directory();
        self::$composer_lock_path = $theme_root . '/composer.lock';
        self::$repo_directory = $theme_root . '/vendor/salthareket/theme';
        self::$vendor_directory = $theme_root . '/vendor/salthareket';
    }

    private static function get_repo_directory() {
        $current_theme = wp_get_theme();
        return ABSPATH . 'wp-content/themes/' . $current_theme->get('TextDomain');
    }

    private static function get_composer_lock_path() {
        return get_template_directory() . '/composer.lock';
    }

    private static function get_vendor_directory() {
        return self::get_repo_directory() . '/vendor/salthareket';
    }

    private static function get_repo_directory_path() {
        return self::get_vendor_directory() . '/theme';
    }

    private static function get_current_version() {
        $theme_name = wp_get_theme()->get_stylesheet();
        $composer_lock_path = ABSPATH . 'wp-content/themes/' . $theme_name . '/composer.lock';

        if (!file_exists($composer_lock_path)) {
            error_log('composer.lock dosyası bulunamadı: ' . $composer_lock_path);
            return 'Unknown';
        }

        $lock_data = file_get_contents($composer_lock_path);

        if (!$lock_data) {
            error_log('composer.lock dosyası okunamadı: ' . $composer_lock_path);
            return 'Unknown';
        }

        $lock_data = json_decode($lock_data, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('JSON parse hatası: ' . json_last_error_msg());
            return 'Unknown';
        }

        if (empty($lock_data['packages'])) {
            error_log('composer.lock dosyasında paket bulunamadı.');
            return 'Unknown';
        }

        foreach ($lock_data['packages'] as $package) {
            if ($package['name'] === 'salthareket/theme') {
                error_log('Mevcut sürüm bulundu: ' . $package['version']);
                return $package['version'];
            }
        }

        error_log('Paket bulunamadı: salthareket/theme');
        return 'Unknown';
    }


    private static function get_latest_version() {
        $url = self::$github_api_url . '/' . self::$github_repo . '/releases/latest';
        
        $response = wp_remote_get($url, [
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version'),
                'Authorization' => 'Bearer ' . SALTHAREKET_TOKEN
            ]
        ]);

        if (is_wp_error($response)) {
            error_log('HTTP request error: ' . $response->get_error_message());
            return 'Error';
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('JSON decode error: ' . json_last_error_msg());
            return 'Error';
        }

        if (isset($data['tag_name'])) {
            return $data['tag_name'];
        }

        error_log('GitHub API response: ' . $body);
        return 'Unknown';
    }


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

    public static function process_update() {
    try {
        error_log("Update işlemi başlatıldı...");

        // Geçerli sürüm ve son sürüm bilgisi
        $latest_version = self::get_latest_version();
        if ($latest_version === 'Unknown') {
            error_log("Son sürüm alınamadı.");
            wp_send_json_error(['message' => 'Son sürüm bilgisi alınamadı.']);
        }
        error_log("Son sürüm: " . $latest_version);

        // ZIP dosyasını indirme
        $url = self::$github_api_url . '/' . self::$github_repo . '/zipball/' . $latest_version;
        $tmp_file = download_url($url);

        if (is_wp_error($tmp_file) || !file_exists($tmp_file) || filesize($tmp_file) === 0) {
            error_log("ZIP dosyası indirilemedi veya bozuk: " . $tmp_file);
            wp_send_json_error(['message' => 'ZIP dosyası indirilemedi veya bozuk.']);
        }
        error_log("ZIP dosyası indirildi: " . $tmp_file);

        // Geçici dizin kontrolü
        $temp_dir = self::$vendor_directory . '/temp';
        if (!file_exists($temp_dir)) {
            if (!mkdir($temp_dir, 0755, true) && !is_dir($temp_dir)) {
                error_log("Geçici dizin oluşturulamadı: " . $temp_dir);
                wp_send_json_error(['message' => 'Geçici dizin oluşturulamadı.']);
            }
            error_log("Geçici dizin oluşturuldu: " . $temp_dir);
        }

        // ZIP dosyasını çıkarma
        $unzip_result = unzip_file($tmp_file, $temp_dir);
        if (is_wp_error($unzip_result)) {
            error_log("unzip_file başarısız oldu: " . $unzip_result->get_error_message());

            // Fallback: ZipArchive kullanarak dosyayı çıkar
            $zip = new ZipArchive();
            if ($zip->open($tmp_file) === true) {
                $extract_result = $zip->extractTo($temp_dir);
                $zip->close();

                if (!$extract_result) {
                    error_log("ZipArchive ile çıkarma başarısız oldu.");
                    self::delete_directory($temp_dir);
                    wp_send_json_error(['message' => 'ZipArchive ile çıkarma başarısız oldu.']);
                }
                error_log("ZipArchive ile dosya başarıyla çıkarıldı.");
            } else {
                error_log("ZipArchive ile çıkarma başarısız oldu.");
                self::delete_directory($temp_dir);
                wp_send_json_error(['message' => 'ZIP dosyası çıkarılamadı.']);
            }
        } else {
            error_log("unzip_file ile ZIP dosyası başarıyla çıkarıldı.");
        }

        @unlink($tmp_file);

        // Çıkarılan klasörü taşıma
        $extracted_dir = glob($temp_dir . '/*')[0] ?? null;
        if ($extracted_dir && is_dir($extracted_dir)) {
            self::delete_directory(self::$repo_directory);
            if (!rename($extracted_dir, self::$repo_directory)) {
                error_log("Yeni sürüm taşınamadı: " . $extracted_dir . " -> " . self::$repo_directory);
                self::delete_directory($temp_dir);
                wp_send_json_error(['message' => 'Yeni sürüm taşınamadı.']);
            }
            error_log("Yeni sürüm başarıyla taşındı: " . self::$repo_directory);

            // Geçici dizini temizle
            self::delete_directory($temp_dir);
            self::update_composer_lock($latest_version);
            wp_send_json_success(['message' => 'Update işlemi başarıyla tamamlandı.']);
        } else {
            error_log("Çıkarılan klasör yapısı geçersiz: " . print_r(glob($temp_dir . '/*'), true));
            self::delete_directory($temp_dir);
            wp_send_json_error(['message' => 'Çıkarılan klasör yapısı geçersiz.']);
        }
    } catch (Exception $e) {
        error_log("Güncelleme sırasında hata: " . $e->getMessage());
        wp_send_json_error(['message' => 'Güncelleme sırasında hata: ' . $e->getMessage()]);
    }
}


    private static function is_composer_available() {
        $output = null;
        $result_code = null;
        exec('composer --version', $output, $result_code);
        return $result_code === 0;
    }

    private static function run_composer_update($path) {
        $command = 'composer update --working-dir=' . escapeshellarg($path);
        exec($command, $output, $result_code);
        if ($result_code !== 0) {
            error_log('Composer update failed: ' . implode("\n", $output));
            return false;
        }

        error_log('Composer update completed: ' . implode("\n", $output));
        return true;
    }

    private static function update_composer_lock($latest_version) {
        $composer_lock_path = self::get_composer_lock_path();
        if (!file_exists($composer_lock_path)) {
            error_log("composer.lock not found.");
            return;
        }

        $lock_data = json_decode(file_get_contents($composer_lock_path), true);
        foreach ($lock_data['packages'] as &$package) {
            if ($package['name'] === self::$github_repo) {
                $package['version'] = $latest_version;
            }
        }

        file_put_contents($composer_lock_path, json_encode($lock_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        error_log("composer.lock updated.");
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