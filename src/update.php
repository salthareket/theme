<?php

use Composer\Console\Application;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class Update {

    private static $github_repo = 'salthareket/theme'; // GitHub deposu adı
    private static $github_api_url = 'https://api.github.com/repos';
    private static $protected_packages = [
        'salthareket/theme',
        "composer/composer",
        "scssphp/scssphp"
    ];
    private static $theme_root;
    private static $composer_path;
    private static $composer_lock_path;
    private static $vendor_directory;
    private static $repo_directory;

    public static $status;
    public static $tasks_status;
    public static $installation_tasks = [
        ["id" => "copy_theme", "name" => "Copying Theme Files"],
        ["id" => "copy_templates", "name" => "Copying Template Files"],
        ["id" => "copy_fonts", "name" => "Copying Fonts"],
        ["id" => "copy_fields", "name" => "Copying ACF Fields"],
        ["id" => "register_fields", "name" => "Registering ACF Fields"],
        ["id" => "update_fields", "name" => "Updating ACF Fields"],
        ["id" => "install_wp_plugins", "name" => "Installing required plugins"],
        ["id" => "install_local_plugins", "name" => "Installing required local plugins"],
        ["id" => "npm_install", "name" => "npm packages installing"],
        ["id" => "compile_methods", "name" => "Compile Frontend & Admin Methods"],
        ["id" => "compile_js_css", "name" => "Compile JS/CSS"]
    ];

    // Admin notifi ekler
    public static function init() {
        $theme_root = get_template_directory();
        self::$theme_root = $theme_root;
        self::$composer_path = $theme_root . '/composer.json';
        self::$composer_lock_path = $theme_root . '/composer.lock';
        self::$vendor_directory = $theme_root . '/vendor/salthareket';
        self::$repo_directory = $theme_root . '/vendor/salthareket/theme';
        self::$status = get_option('sh_theme_status', false);
        self::$tasks_status = get_option('sh_theme_tasks_status', []);
        self::$tasks_status = empty(self::$tasks_status)?[]:self::$tasks_status;
        if(!is_dir(get_template_directory() . '/theme/')){
           self::$status = "pending";
           self::$tasks_status = [];
        }
        add_action('admin_notices', [__CLASS__, 'check_for_update_notice']);
        add_action('wp_ajax_update_theme_package', [__CLASS__, 'composer']);
        add_action('wp_ajax_install_new_package', [__CLASS__, 'composer_install']);
        add_action('wp_ajax_remove_package', [__CLASS__, 'composer_remove']);
        add_action('wp_ajax_run_task', [__CLASS__, 'run_task']);
        self::fix();
        self::check_installation();
    }

    private static function check_installation(){
        if (!(defined('DOING_AJAX') && DOING_AJAX)) {
            $status = self::$status;
            $tasks_status = self::$tasks_status;
            if(empty($status)){
                $status = "pending";
                $tasks_status = [];
                add_option('sh_theme_status', $status);
                add_option('sh_theme_tasks_status', $tasks_status);
            }else{
                if(count(self::$installation_tasks) > count($tasks_status)){
                    $status = "pending";
                    $tasks_status = [];
                    update_option('sh_theme_status', $status);
                    update_option('sh_theme_tasks_status', $tasks_status);
                }            
            }
            self::$status = $status;
            self::$tasks_status = $tasks_status;
            if ($status == 'pending' || !$status) {
                if (is_admin()) {
                    $current_page = $_GET['page'] ?? '';
                    if ($current_page !== 'update-theme') {
                        wp_safe_redirect(admin_url('admin.php?page=update-theme'));
                        exit;
                    }
                } else {
                    wp_die(
                        sprintf(
                            '<h2 class="text-danger">Warning</h2>The theme setup is not complete. Please complete the installation from the <a href="%s">update page</a>.',
                            esc_url(admin_url('admin.php?page=update-theme'))
                        )
                    );
                }
            }
        }
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
    private static function get_installed_packages() {
        if (!file_exists(self::$composer_path)) {
            return [];
        }
        $json_data = json_decode(file_get_contents(self::$composer_path), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }
        $installed_packages = array_keys($json_data['require'] ?? []);
        return $installed_packages;
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



    public static function render_installation_page() {
        echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" type="text/css" media="all" />';
        ?>
        <div class="wrap">
            <h1>Installation Required</h1>

            <div style="display:flex;flex-direction:column;align-items:center;justify-content: center;height:100vh; text-align:center;">
                <div style="width:60%;">
                    <h2 style="font-weight:600;font-size:42px;line-height:1;margin-bottom:20px;"><small style="display:block;font-size:12px;font-weight:bold;margin-bottom:10px;background-color:#111;color:#ddd;padding:8px 12px;border-radius:22px;display:inline-block;">STEP 2</small><br>Install Requirements</h2>
                    <p>This theme requires some initial setup before you can start using it. Please complete the installation process below.</p>
                    <div class="progress my-4" style="height: 30px;display:none;">
                        <div id="installation-progress" class="progress-bar progress-bar-striped progress-bar-animated text-end pe-3" role="progressbar" style="width: 0%;height:100%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                    </div>
                    <div class="installation-status" style="text-align:center;font-size: 22px;font-weight:bold;margin-top:20px;display:none;"></div>
                    <button id="start-installation-button" class="button button-primary" style="margin-top:40px;font-size: 18px;border-radius: 22px;border: none;padding: 6px 28px;">Start Installation</button>
                </div>
            </div>
        </div>

        <?php
    }
    public static function render_update_page() {
        $current_version = self::get_current_version();
        $latest_version = self::get_latest_version();
        $installed_packages = self::get_installed_packages();

        echo '<div class="wrap">';

            echo '<h1>Theme Update</h1>';
            printf('<p>Current Version: <strong>%s</strong></p>', esc_html($current_version));
            printf('<p>Latest Version: <strong>%s</strong></p>', esc_html($latest_version));
            if ($latest_version !== 'Unknown' && version_compare($current_version, $latest_version, '<')) {
                echo '<div class="alert alert-dismissible rounded-3 w-25 fade d-none" data-action="update"></div>';
                echo '<button id="update-theme-button" class="button button-primary">Update to ' . esc_html($latest_version) . '</button>';
            } else {
                echo '<h3 class="text-success fw-bold">Your theme is up to date.</h3>';
            }

            echo '<hr class="my-5" />';

            echo '<h2>Install or Update Package</h2>';
            echo '<div class="alert alert-dismissible rounded-3 w-25 fade d-none" data-action="install"></div>';
            echo '<input type="text" id="install-package-name" name="install-package-name" placeholder="Enter package name (e.g., vendor/package)" style="width: 300px; margin-right: 10px;">';
            echo '<button id="install-package-button" class="button button-secondary">Install Package</button>';

            echo '<hr class="my-5" />';

            echo '<h2>Remove Package</h2>';
            echo '<div class="alert alert-dismissible rounded-3 w-25 fade d-none" data-action="remove"></div>';
            echo '<select id="remove-package-name" name="remove-package-name" style="width: 300px; margin-right: 10px;">';
            foreach ($installed_packages as $package) {
                echo '<option value="' . esc_attr($package) . '">' . esc_html($package) . '</option>';
            }
            echo '</select>';
            echo '<button id="remove-package-button" class="button button-secondary">Remove Package</button>';

        echo '</div>';
    }
    public static function render_page() {
        if (self::$status === 'pending') {
            self::render_installation_page();
        } else {
            self::render_update_page();
        }
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

        foreach ($lock_data['packages'] as &$package) {
            if ($package['name'] === self::$github_repo) {
                $package['version'] = $latest_version;
                $package['source']['reference'] = $commit_hash;
                $package['dist']['reference'] = $commit_hash;
                $package['dist']['url'] = "https://api.github.com/repos/" . self::$github_repo . "/zipball/" . $commit_hash;
            }
        }
        file_put_contents(self::$composer_lock_path, json_encode($lock_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
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



    public static function composer($package_name="", $remove = false) {
        try {

            if (!file_exists(self::$composer_path)) {
                wp_send_json_error(['message' => 'composer.json is not found.']);
            }

            $args = array(
                'command' => 'update',
                '--working-dir' => get_template_directory()
            );
            if(!empty($package_name)){
                $args["command"] = $remove?"remove":"require";
                $args["packages"] = [$package_name];

                if ($package_name === 'salthareket/theme' && !$remove) {
                    // Geçici klasör oluştur
                    $temp_dir = wp_upload_dir()['basedir'] . '/temp_' . uniqid();
                    if (!mkdir($temp_dir, 0755, true)) {
                        wp_send_json_error(['message' => 'Failed to create temporary directory.']);
                    }

                    $args['--working-dir'] = $temp_dir;
                }
            }

            $app = new Application();
            $app->setAutoExit(false);
            $input = new ArrayInput($args);
            $output = new BufferedOutput();
            $app->run($input, $output);

            $raw_output = $output->fetch();
            $lines = explode("\n", $raw_output);

            foreach ($lines as $line) {
                if (strpos(trim($line), 'Could not find package') !== false) {
                    wp_send_json_error(['message' => "Could not find package: <strong>$package_name</strong>", "action" => "error" ]);
                    exit;
                }
            }

            if ($package_name === 'salthareket/theme' && !$remove) {
                $target_dir = self::$repo_directory;

                // Mevcut hedef klasörü sil
                if (is_dir($target_dir)) {
                    self::recurseDelete($target_dir);
                }

                // Geçici klasörden içeriği taşı
                if (!rename($temp_dir . '/vendor/salthareket/theme', $target_dir)) {
                    wp_send_json_error(['message' => 'Failed to move updated files to target directory.']);
                }

                // Geçici klasörü sil
                self::recurseDelete($temp_dir);
            }

            $result = [
                "update" => [],
                "install" => [],
                "remove" => []
            ];
            $action = "nothing";
            foreach ($lines as $line) {
                if (preg_match('/Upgrading ([^ ]+) \(([^ ]+) => ([^ ]+)\)/', $line, $matches)) {
                    $action = "update";
                    $result["update"] = sprintf('%s: %s -> %s', $matches[1], $matches[2], $matches[3]);
                }elseif (preg_match('/Installing ([^ ]+) \(([^ ]+)\)/', $line, $matches)) {
                    $action = "install";
                    $result["install"] = sprintf('%s: %s installed', $matches[1], $matches[2]);
                }elseif (preg_match('/Removing ([^ ]+) \(([^ ]+)\)/', $line, $matches)) {
                    $action = "remove";
                    $result["remove"] = sprintf('%s: %s removed', $matches[1], $matches[2]);
                }
            }
            $message = [];
            if($result["update"]){
                $message[] = $result["update"];
            }
            if($result["install"]){
                $message[] = $result["install"];
            }
            if($result["remove"]){
                $message[] = $result["remove"];
            }
            if($message){
                $message = implode(", ", $message);
            }else{
                $message = 'No updates or installations performed.';
            }
            
            wp_send_json_success(['message' => $message, "action" => $action ]);

        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage(), "action" => $action ]);
        }
    }

    // Geçici klasörleri silmek için yardımcı fonksiyon
    private static function recurseDelete($dir) {
        if (!is_dir($dir)) return;
        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? self::recurseDelete($path) : unlink($path);
        }
        rmdir($dir);
    }

    public static function composer_install() {
        check_ajax_referer('update_theme_nonce', 'nonce');
        $package_name = isset($_POST['package']) ? sanitize_text_field($_POST['package']) : '';
        if (empty($package_name)) {
            wp_send_json_error(['message' => 'Package name is required.']);
        }
        try {
            self::composer($package_name);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    public static function composer_remove() {
        check_ajax_referer('update_theme_nonce', 'nonce');
        $package_name = isset($_POST['package']) ? sanitize_text_field($_POST['package']) : '';
        if (empty($package_name)) {
            wp_send_json_error(['message' => 'Package name is required.']);
            exit;
        }
        if(in_array($package_name, self::$protected_packages)){
            wp_send_json_error(['message' => 'You can not remove a protected package like: '.$package_name ]);
            exit;
        }
        try {
            self::composer($package_name, true);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    private static function copy_theme(){
        $srcDir = SH_PATH . 'theme';
        $target_dir = get_template_directory() . '/theme';
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true); 
            self::recurseCopy($srcDir, $target_dir);
        }
    }
    private static function copy_templates(){
        $srcDir = SH_PATH . 'templates';
        $target_dir = get_template_directory() . '/templates';
        if (is_dir($srcDir)) {
            self::recurseCopy($srcDir, $target_dir);
        }
    }
    private static function copy_fonts(){
        $srcDir = SH_STATIC_PATH . 'fonts';
        $target_dir = STATIC_PATH . 'fonts';
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true); 
        }
        if (is_dir($srcDir)) {
            self::recurseCopy($srcDir, $target_dir, ["scss"]);
        }
    }
    private static function copy_fields(){
        $srcDir = SH_PATH . 'acf-json';
        $target_dir = get_template_directory() . '/acf-json';
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true); 
        }
        if (is_dir($srcDir)) {
            self::recurseCopy($srcDir, $target_dir);
        }
    }
    private static function register_fields(){
        acf_json_to_db(get_template_directory() . '/acf-json');
    }
    private static function update_fields(){
        global $wpdb;
        $post_name = "group_66e309dc049c4";
        $query = $wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_name = %s", $post_name);
        $post_id = $wpdb->get_var($query);
        if($post_id){
            acf_save_post_block_columns_action( $post_id );
        }
    }
    private static function recurseCopy($src, $dest, $exclude = []){
        $dir = opendir($src);

        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }

        while (false !== ($file = readdir($dir))) {
            if ($file == '.' || $file == '..') {
                continue; // Geçerli ve üst dizini atla
            }

            $srcPath = $src . DIRECTORY_SEPARATOR . $file;
            $destPath = $dest . DIRECTORY_SEPARATOR . $file;

            // Hariç tutulacak klasör kontrolü
            if (is_dir($srcPath) && in_array($file, $exclude)) {
                continue; // Hariç tutulan klasörü atla
            }

            if (is_dir($srcPath)) {
                // Alt klasörleri kopyala
                self::recurseCopy($srcPath, $destPath, $exclude);
            } else {
                // Dosyayı kopyala
                copy($srcPath, $destPath);
            }
        }

        closedir($dir);
    }

    private static function fileCopy($source, $destination) {
        if (!file_exists($source)) {
            return;
        }
        $destinationDir = dirname($destination);
        if (!file_exists($destinationDir)) {
            if (!mkdir($destinationDir, 0777, true)) {
                return;
            }
        }
        if (copy($source, $destination)) {

        } else {
            return;
        }
    }

    private static function npm_install(): string{
        $workingDir = ABSPATH;
        if (!is_dir($workingDir)) {
            wp_send_json_error(['message' => 'npm path not found: '.$dir]);
        }

        error_log(SH_PATH . "package.json -> ".$workingDir .'package.json');
        if (!file_exists($workingDir .'package.json')) {
            self::fileCopy(SH_PATH . "package.json", $workingDir .'package.json');
        }
        $command = ['npm', 'install'];
        $process = new Process($command, $workingDir);
        $currentUser = getenv('USERNAME') ?: getenv('USER'); // Windows için USERNAME, diğer sistemlerde USER
        $nodeJsPath = 'C:\Program Files\nodejs';
        $npmPath = 'C:\Users\\' . $currentUser . '\AppData\Roaming\npm';
        $process->setEnv([
            'PATH' => getenv('PATH') . ';' . $nodeJsPath . ';' . $npmPath,
        ]);
        //print_r(getenv('PATH') . ';' . $nodeJsPath . ';' . $npmPath);
        $process->setTimeout(120);
        try {
            $process->mustRun();
            error_log($process->getOutput()); // Çıktıyı kaydet
            return true;
            //wp_send_json_success(['message' => 'npm packages installed!']);
            //return $process->getOutput();
        } catch (ProcessFailedException $e) {
            // Hata durumunda istisna fırlat
            error_log('Webpack execution failed: ' . $exception->getMessage());
            return false;
            //wp_send_json_error(['message' => 'npm packeges not installed: ' . $e->getMessage()]);
            //throw new \Exception("npm install işlemi başarısız oldu: " . $e->getMessage());
        }
    }

    private static function install_wp_plugins(){
        \PluginManager::check_and_install_required_plugins();
    }
    private static function install_local_plugins(){
        \PluginManager::check_and_update_local_plugins();
    }
    private static function compile_methods(){
        acf_methods_settings();
    }
    private static function compile_js_css(){
        acf_compile_js_css();
    }
    public static function run_task() {
        check_ajax_referer('update_theme_nonce', 'nonce');
        $task_id = isset($_POST['task_id']) ? sanitize_text_field($_POST['task_id']) : '';
        try {
            switch ($task_id) {
                case 'copy_theme':
                    self::copy_theme();
                    self::update_task_status('copy_theme', true);
                    wp_send_json_success(['message' => 'Theme files copied successfully']);
                    break;
                case 'copy_templates':
                    self::copy_templates();
                    self::update_task_status('copy_templates', true);
                    wp_send_json_success(['message' => 'Template filess copied successfully']);
                    break;
                case 'copy_fonts':
                    self::copy_fonts();
                    self::update_task_status('copy_fonts', true);
                    wp_send_json_success(['message' => 'Fonts copied successfully']);
                    break;
                case 'copy_fields':
                    self::copy_fields();
                    self::update_task_status('copy_fields', true);
                    wp_send_json_success(['message' => 'ACF fields copied successfully']);
                    break;
                case 'register_fields':
                    self::register_fields();
                    self::update_task_status('register_fields', true);
                    wp_send_json_success(['message' => 'ACF fields registered successfully']);
                    break;
                case "update_fields":
                    self::update_fields();
                    self::update_task_status('update_fields', true);
                    wp_send_json_success(['message' => 'ACF fields updated successfully']);
                    break;
                case 'install_wp_plugins':
                    ob_start();
                    self::install_wp_plugins();
                    ob_end_clean();
                    self::update_task_status('install_wp_plugins', true);
                    wp_send_json_success(['message' => 'WP plugins installed successfully']);
                    break;
                case 'install_local_plugins':
                    self::install_local_plugins();
                    self::update_task_status('install_local_plugins', true);
                    wp_send_json_success(['message' => 'Local plugins installed successfully']);
                    break;
                case 'npm_install':
                    self::npm_install();
                    self::update_task_status('npm_install', true);
                    wp_send_json_success(['message' => 'NPM Packages installed successfully']);
                    break;
                case 'compile_methods':
                    self::compile_methods();
                    self::update_task_status('compile_methods', true);
                    wp_send_json_success(['message' => 'ACF Methods compiled successfully']);
                    break;
                case 'compile_js_css':
                    self::compile_js_css();
                    self::update_task_status('compile_js_css', true);
                    wp_send_json_success(['message' => 'JS/CSS compiled successfully']);
                    break;
                default:
                    wp_send_json_error(['message' => 'Invalid task ID']);
            }

        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Error during task execution: ' . $e->getMessage()]);
        }
    }
    private static function update_task_status($task_id, $status) {
        $tasks_status = get_option('sh_theme_tasks_status', []);
        $tasks_status[$task_id] = $status;
        self::$tasks_status = $tasks_status;
        update_option('sh_theme_tasks_status', $tasks_status);
        error_log($task_id." yuklendi");
        error_log(self::tasks_completed());
        error_log(json_encode(get_option('sh_theme_tasks_status')));
        if (self::tasks_completed()) {
            update_option('sh_theme_status', true);
            self::$status = true;
            error_log("Tüm görevler tamamlandı. sh_theme_status true yapıldı.");
        }
    }
    public static function is_task_completed($task=""){
        $tasks_status = get_option('sh_theme_tasks_status', []);
        if(is_array($tasks_status) && in_array($task, array_keys($tasks_status))){
           return true;
        }
        return false;
    }
    public static function tasks_completed() {
        $tasks_status = get_option('sh_theme_tasks_status', []);
        foreach (self::$installation_tasks as $task) {
            if (empty($tasks_status[$task['id']]) || $tasks_status[$task['id']] !== true) {
                return false;
            }
        }
        return true;
    }

    private static function fix(){
        $fixes = include get_template_directory() . "/vendor/salthareket/theme/src/fix/index.php";
        error_log(json_encode($fixes));
        if($fixes){
            foreach($fixes as $fix){
                $file = get_template_directory() . "/vendor/salthareket/theme/src/fix/".$fix["file"];
                $target_file = get_template_directory()."/vendor/".$fix["target"].$fix["file"];
                if($fix["status"] && file_exists($file)){
                    self::fileCopy($file, $target_file);
                }
            }
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

        $args = [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('update_theme_nonce')
        ];
        if (self::$status === 'pending') {
            $args["tasks"] = self::$installation_tasks;
        }
        wp_localize_script('theme-update-script', 'updateAjax', $args);
    }
}