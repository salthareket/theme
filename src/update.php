<?php

use Composer\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class Update {

    private static $github_repo = 'salthareket/theme'; // GitHub deposu adı
    private static $github_api_url = 'https://api.github.com/repos';
    private static $composer_lock_path;
    private static $vendor_directory;
    private static $repo_directory;

    // Admin notifi ekler
    public static function init() {
        $theme_root = get_template_directory();
        self::$composer_lock_path = $theme_root . '/composer.lock';
        self::$vendor_directory = $theme_root . '/vendor/salthareket';
        self::$repo_directory = $theme_root . '/vendor/salthareket/theme';
        add_action('admin_notices', [__CLASS__, 'check_for_update_notice']);
        //add_action('wp_ajax_update_theme_package', [__CLASS__, 'process_update']);
        add_action('wp_ajax_update_theme_package', [__CLASS__, 'run_composer_update']);
    }

    private static function get_current_version() {

        if (!file_exists(self::$composer_lock_path)) {
            error_log('composer.lock dosyası bulunamadı: ' . self::$composer_lock_path);
            return 'Unknown';
        }

        $lock_data = file_get_contents(self::$composer_lock_path);

        if (!$lock_data) {
            error_log('composer.lock dosyası okunamadı: ' . self::$composer_lock_path);
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
            if ($package['name'] === self::$github_repo) {
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

    private static function update_composer_lock($latest_version) {
        if (!file_exists(self::$composer_lock_path)) {
            error_log("composer.lock not found.");
            return;
        }

        $lock_data = json_decode(file_get_contents(self::$composer_lock_path), true);

        // En son commit hash'i al
        $url = self::$github_api_url . '/' . self::$github_repo . '/commits/' . $latest_version;
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . SALTHAREKET_TOKEN,
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version')
            ]
        ]);

        if (is_wp_error($response)) {
            error_log("Commit hash alınamadı: " . $response->get_error_message());
            return;
        }

        $commit_data = json_decode(wp_remote_retrieve_body($response), true);
        $commit_hash = $commit_data['sha'] ?? 'main';

        // composer.lock'u güncelle
        foreach ($lock_data['packages'] as &$package) {
            if ($package['name'] === self::$github_repo) {
                $package['version'] = $latest_version;
                $package['source']['reference'] = $commit_hash;
                $package['dist']['reference'] = $commit_hash;
                $package['dist']['url'] = "https://api.github.com/repos/" . self::$github_repo . "/zipball/" . $commit_hash;
            }
        }

        file_put_contents(self::$composer_lock_path, json_encode($lock_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        error_log("composer.lock updated with commit hash.");
    }

    private static function delete_directory($dir) {
        /*if (!is_dir($dir)) return;

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? self::delete_directory($path) : unlink($path);
        }
        rmdir($dir);*/
    }

    public static function run_composer_update() {
        try {
            // Aktif temanın kök dizinini al
            $theme_directory = get_template_directory();

            // composer.json dosyasını kontrol et
            if (!file_exists($theme_directory . '/composer.json')) {

                return 'composer.json dosyası mevcut değil.';
            }

            // Composer Application başlat
            $app = new Application();
            $app->setAutoExit(false);

            // Composer update komutunu ayarla
            $input = new ArrayInput([
                'command' => 'update',
                '--working-dir' => $theme_directory, // Çalışma dizini olarak tema kökünü ayarla
            ]);

            $output = new BufferedOutput();

            // Composer işlemini çalıştır
            $app->run($input, $output);

            // Çıktıyı analiz et
            $raw_output = $output->fetch();
            $lines = explode("\n", $raw_output);
            $result = [];

            foreach ($lines as $line) {
                if (preg_match('/Updating ([^ ]+) \(([^ ]+) => ([^ ]+)\)/', $line, $matches)) {
                    $result[] = sprintf('%s: %s -> %s', $matches[1], $matches[2], $matches[3]);
                } elseif (preg_match('/Installing ([^ ]+) \(([^ ]+)\)/', $line, $matches)) {
                    $result[] = sprintf('%s: %s installed', $matches[1], $matches[2]);
                }
            }
            echo $raw_output;

            echo !empty($result) ? implode("\n", $result) : 'No updates or installations performed.';

        } catch (Exception $e) {
            // Hata durumunda mesaj döndür
            error_log('Composer update işlemi sırasında hata: ' . $e->getMessage());
            echo 'Composer update işlemi sırasında hata: ' . $e->getMessage();
        }
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