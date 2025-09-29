<?php

use Composer\Console\Application;
use Composer\Json\JsonFile;
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
        ["id" => "fix_packages", "name" => "Fixing Composer Packages"],
        ["id" => "update_theme_apperance", "name" => "Updating '".TEXT_DOMAIN."' Theme Apperance"],
        ["id" => "copy_theme", "name" => "Copying Theme Files"],
        //["id" => "copy_templates", "name" => "Copying Template Files"],
        //["id" => "copy_fonts", "name" => "Copying Fonts"],
        ["id" => "install_mu_plugins", "name" => "Installing Must Use plugins"],
        ["id" => "install_wp_plugins", "name" => "Installing required plugins"],
        ["id" => "install_local_plugins", "name" => "Installing required local plugins"],
        ["id" => "generate_files", "name" => "Generating Files"],
        ["id" => "copy_fields", "name" => "Copying ACF Fields"],
        ["id" => "register_fields", "name" => "Registering ACF Fields"],
        ["id" => "update_fields", "name" => "Updating ACF Fields"],
        ["id" => "npm_install", "name" => "npm packages installing"],
        ["id" => "compile_methods", "name" => "Compile Frontend & Admin Methods"],
        ["id" => "compile_js_css", "name" => "Compile JS/CSS"],
        ["id" => "defaults", "name" => "Defaults Settings"]
    ];

    public static $update_tasks = [
        ["id" => "install_mu_plugins", "name" => "Updating Must Use plugins"],
        ["id" => "install_local_plugins", "name" => "Updating local plugins"],
        ["id" => "copy_fields", "name" => "Copying ACF Fields"],
        ["id" => "register_fields", "name" => "Registering ACF Fields"],
        ["id" => "update_fields", "name" => "Updating ACF Fields"]
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
        add_action( 'admin_enqueue_scripts', [__CLASS__, 'disable_heartbeat']);
        add_action('admin_notices', [__CLASS__, 'check_for_update_notice']);
        add_action('wp_ajax_update_theme_package', [__CLASS__, 'composer']);
        add_action('wp_ajax_install_new_package', [__CLASS__, 'composer_install']);
        add_action('wp_ajax_remove_package', [__CLASS__, 'composer_remove']);
        add_action('wp_ajax_run_task', [__CLASS__, 'run_task']);
        add_action('wp_ajax_run_update_task', [__CLASS__, 'run_update_task']);

        add_action('wp_ajax_install_ffmpeg', [__CLASS__, 'install_ffmpeg']);

        add_action('admin_head', function () {
            $pages = ["update-theme" ];
            if (isset($_GET['page']) && in_array($_GET['page'], $pages)) {
                remove_all_actions('admin_notices');
                remove_all_actions('all_admin_notices');
            }
        });

        self::check_installation();
    }


    public static function disable_heartbeat($hook){
        if ($hook === 'theme-settings_page_update-theme') {
            wp_deregister_script('heartbeat');
        }
    }


    /*private static function check_installation(){
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
                    //$is_login_page = strpos($_SERVER['REQUEST_URI'], 'wp-login.php') !== false;
                    if (!is_login_page()) {
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
    }*/

    private static function check_installation(){
        if (!(defined('DOING_AJAX') && DOING_AJAX)) {
            $status = self::$status;
            $tasks_status = self::$tasks_status;

            if (empty($status)) {
                $status = "pending";
                $tasks_status = [];
                add_option('sh_theme_status', $status);
                add_option('sh_theme_tasks_status', $tasks_status);
            } else {
                if (count(self::$installation_tasks) > count($tasks_status)) {
                    $status = "pending";
                    $tasks_status = [];
                    update_option('sh_theme_status', $status);
                    update_option('sh_theme_tasks_status', $tasks_status);
                }
            }

            self::$status = $status;
            self::$tasks_status = $tasks_status;

            if ($status === 'pending' || !$status) {
                if (is_admin()) {
                    $current_page = $_GET['page'] ?? '';
                    if ($current_page !== 'update-theme') {
                        wp_safe_redirect(admin_url('admin.php?page=update-theme'));
                        exit;
                    }
                } else {
                    $uri_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
                    $is_asset = preg_match('/\.(js|css|jpg|jpeg|png|svg|woff2?|ttf|eot|gif|webp)$/i', $uri_path);
                    $is_rest_api = strpos($_SERVER['REQUEST_URI'], '/wp-json/') !== false;

                    if (!$is_asset && !$is_rest_api && !is_login_page()) {
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
    }

    private static function get_package_github_url($package_name) {
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
            if ($package['name'] === $package_name) {
                error_log('Github URL bulundu: '.$package_name . ":" . $package['dist']["url"]);
                $url = $package['dist']["url"];
                return preg_replace('#/[^/]+$#', '/', $url);
            }
        }

        error_log('Paket bulunamadı: '.$package_name);
        return 'Unknown';
    }
    private static function get_package_version($package_name) {

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
            if ($package['name'] === $package_name) {
                error_log('Mevcut sürüm bulundu: '.$package_name . ":" . $package['version']);
                return $package['version'];
            }
        }

        error_log('Paket bulunamadı: '.$package_name);
        return 'Unknown';
    }
    private static function get_current_version() {
        return self::get_package_version(self::$github_repo);
    }
    private static function get_latest_version($package_name = "") {
        if(empty($package_name)){
            $url = self::$github_api_url . '/' . self::$github_repo . '/releases/latest';
        }else{
            $url = $this->get_package_github_url($package_name).'releases/latest';
        }
        error_log("get_latest_version -> ".$url);
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
    private static function get_required_packages() {
        if (!file_exists(self::$composer_path)) {
            return [];
        }
        $json_data = json_decode(file_get_contents(self::$composer_path), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }
        $required_packages = array_keys($json_data['require'] ?? []);
        return $required_packages;
    }
    private static function get_installed_packages() {
        $lock_data = json_decode(file_get_contents(self::$composer_lock_path), true);
        return array_map(function($pkg) { return $pkg['name']; }, $lock_data['packages'] ?? []);
    }
    public static function check_for_update_notice() {
        $current_version = self::get_current_version();
        $latest_version = self::get_latest_version();

        if ($current_version === 'Unknown' || $latest_version === 'Unknown' || version_compare($current_version, $latest_version, '>=')) {
            return;
        }

        printf(
            '<div class="notice notice-warning is-dismissible d-flex py-4 ps-4"><img src="'.SH_URL.'/src/content/logo-salt-hareket.png" width="50" height="50"/><div class="ms-3"><h3 class="m-0">SaltHareket/Theme</h3><p class="mb-0">Version <strong>%s</strong> is available <a href="%s">Update Now</a></p></div></div>',
            esc_html($latest_version),
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
                    
                    <div class="alert alert-primary d-inline-block ms-auto me-auto py-3 px-5 mt-3 rounded-4 shadow">
                        <h5 class="fw-bold">Your theme's requirements</h5>
                        <hr>
                        <div class="d-flex text-start row row-cols-2 row-cols-lg-4 row-cols-xl-5">
                            <div class="ms-4 form-check d-flex align-items-center">
                                <input class="form-check-input" type="checkbox" name="plugin_types" value="multilanguage" id="multilanguageSwitch">
                                <label class="form-check-label ms-2" for="multilanguageSwitch">Multilanguage</label>
                            </div>
                            <div class="ms-4 form-check d-flex align-items-center">
                                <input class="form-check-input" type="checkbox" name="plugin_types" value="ecommerce" id="ecommerceSwitch">
                                <label class="form-check-label ms-2" for="ecommerceSwitch">E-commerce</label>
                            </div>
                            <div class="ms-4 form-check d-flex align-items-center">
                                <input class="form-check-input" type="checkbox" name="plugin_types" value="membership" id="membershipSwitch">
                                <label class="form-check-label ms-2" for="membershipSwitch">Membership</label>
                            </div>
                            <div class="ms-4 form-check d-flex align-items-center">
                                <input class="form-check-input" type="checkbox" name="plugin_types" value="contact-forms" id="contactFormsSwitch">
                                <label class="form-check-label ms-2" for="contactFormsSwitch">Contact Forms</label>
                            </div>
                            <div class="ms-4 form-check d-flex align-items-center">
                                <input class="form-check-input" type="checkbox" name="plugin_types" value="social-share" id="social-shareSwitch">
                                <label class="form-check-label ms-2" for="social-shareSwitch">Social Share</label>
                            </div>
                            <div class="ms-4 form-check d-flex align-items-center">
                                <input class="form-check-input" type="checkbox" name="plugin_types" value="newsletter" id="newsletterSwitch">
                                <label class="form-check-label ms-2" for="newsletterSwitch">Newsletter</label>
                            </div>
                            <div class="ms-4 form-check d-flex align-items-center">
                                <input class="form-check-input" type="checkbox" name="plugin_types" value="automation" id="automationSwitch">
                                <label class="form-check-label ms-2" for="automationSwitch">Automation</label>
                            </div>
                            <div class="ms-4 form-check d-flex align-items-center">
                                <input class="form-check-input" type="checkbox" name="plugin_types" value="cookie" id="cookieSwitch">
                                <label class="form-check-label ms-2" for="cookieSwitch">Cookie Consent</label>
                            </div>
                            <div class="ms-4 form-check d-flex align-items-center">
                                <input class="form-check-input" type="checkbox" name="plugin_types" value="security" id="securitySwitch">
                                <label class="form-check-label ms-2" for="securitySwitch">Security</label>
                            </div>
                        </div>
                    </div>

                    <div class="progress my-4" style="height: 30px;display:none;">
                        <div id="installation-progress" class="progress-bar progress-bar-striped progress-bar-animated text-end pe-3" role="progressbar" style="width: 0%;height:100%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                    </div>
                    <div class="installation-status" style="text-align:center;font-size: 22px;font-weight:bold;margin-top:20px;display:none;"></div>
                    <hr class="invisible m-0 p-0"/>
                    <button id="start-installation-button" class="button button-primary" style="margin-top:40px;font-size: 18px;border-radius: 22px;border: none;padding: 6px 28px;">Start Installation</button>
                </div>
            </div>
        </div>

        <?php
    }
    public static function render_update_page() {
        $current_version = self::get_current_version();
        $latest_version = self::get_latest_version();
        $required_packages = self::get_required_packages();
        
        $init_class = "";
        $dependencies = get_option('composer_dependencies');
        if($dependencies){
            $init_class = "init";
            update_option('composer_dependencies', []);
        }

        echo '<div class="wrap">';

            echo '<h1>Theme Update</h1>';
            printf('<p>Current Version: <strong>%s</strong></p>', esc_html($current_version));
            printf('<p>Latest Version: <strong>%s</strong></p>', esc_html($latest_version));
            if ($latest_version !== 'Unknown' && version_compare($current_version, $latest_version, '<')) {
                echo '<div class="alert alert-dismissible rounded-3 w-25 fade d-none" data-action="update"></div>';
                echo '<button id="update-theme-button" class="button button-primary '.$init_class.'">Update to ' . esc_html($latest_version) . '</button>';
            } else {
                echo '<h3 class="text-success fw-bold">Your theme is up to date.</h3>';
                echo '<div class="alert alert-dismissible rounded-3 w-25 fade d-none" data-action="update"></div>';
                echo '<button id="update-theme-button" class="button button-primary '.$init_class.'">Update Depencies</button>';
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
            foreach ($required_packages as $package) {
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
    public static function video_process_page() { 
        
        $video_process = new VideoProcessor();
        $is_supported = $video_process->supported;
        $is_available = $video_process->available;
    ?>
        <div class="wrap">
            <h1>Video Process</h1>
            <?php 
            $args = [
                'post_type'      => 'any',
                'post_status'    => 'publish',
                'meta_query'     => [
                    [
                        'key'     => 'video_tasks',
                        'compare' => 'EXISTS',
                    ],
                ],
                'posts_per_page' => -1,
                'orderby'        => 'ID',
                'order'          => 'ASC',
            ];
            $query = new WP_Query($args);
            $posts = $query->posts;

            if(!$is_supported){?>
               <div class="alert alert-danger text-danger alert-dismissible rounded-3 w-25"><?php echo PHP_OS;?> is not supported!</div>
            <?php
            }else{    
            ?>
                <ul class="list-group w-50">
                        <li class="list-group-item py-4">
                            <div class="row align-items-center">
                                <div class="col">
                                    <strong>FFMpeg</strong> for <?php echo PHP_OS;?>
                                </div>
                                <div class="col col-auto text-end">
                                    <?php
                                    if($is_available){?>
                                        <span class="text-success fw-bold">Installed</span>
                                    <?php
                                    }else{
                                    ?>
                                       <div class="alert alert-dismissible rounded-3 w-25 fade d-none"></div>
                                       <button id="install-ffmpeg-button" class="button button-primary">Install</button>
                                    <?php
                                    }
                                    ?>
                                </div>
                            </div>
                        </li>
                </ul>
                <?php
                if($posts){
                ?>
                    <div class="list-group w-50 mt-5">
                    <?php
                    foreach($posts as $post){
                        $tasks = get_post_meta($post->ID, 'video_tasks', true);
                        $blocks = parse_blocks($post->post_content);
                    ?>
                        <div class="list-group-item py-3">
                            <?php echo "<h3 class='mb-3 mt-0'>".$post->post_title.": <span class='text-success'>Video Processing...</span></h3>";
                            foreach($tasks as $task){ 
                                $status = isset($task["tasks"]);
                                $block = $blocks[$task["block_index"]];
                                $video = wp_get_attachment_url($block["attrs"]["data"]["video_file_desktop"]);
                            ?>
                                <div class="list-group">
                                    <div class="list-group-item py-3">
                                        <div class="row">
                                            <div class="col col-auto">
                                                <video 
                                                    src="<?php echo esc_url($video); ?>" 
                                                    preload="metadata" 
                                                    style="max-width: 200px; display: block; pointer-events: none;" 
                                                    muted 
                                                    playsinline 
                                                    oncontextmenu="return false;" 
                                                    class="rounded-3 overflow-hidden"
                                                >
                                                    Your browser does not support the video tag.
                                                </video>
                                            </div>
                                            <div class="col">
                                                <?php 
                                                if($status){
                                                ?>
                                                <table class="table table-light table-striped">
                                                    <tbody>
                                                        <?php
                                                         
                                                        if(isset($task["tasks"]["sizes"])){
                                                            foreach($task["tasks"]["sizes"] as $size => $size_status){
                                                            ?>
                                                            <tr>
                                                                <?php
                                                                if(!$size_status){
                                                                ?>
                                                                   <td class="loading loading-xs position-relative" style="width:30px;"></td>
                                                                <?php
                                                                }else{?>
                                                                   <td class="text-center text-success text-center" style="width:30px;"><i class="fa fa-check"></i></td>
                                                                <?php
                                                                }
                                                                ?>
                                                                <td class="fw-bold">Generate <?php echo $size;?>p size</td>
                                                                <td class="text-end">
                                                                    <?php 
                                                                     echo $size_status?"Completed":"In progress...";
                                                                    ?>
                                                                </td>
                                                            </tr>
                                                            <?php
                                                            }
                                                        }?>

                                                        <?php 
                                                        if(isset($task["tasks"]["poster"])){
                                                            ?>
                                                            <tr>
                                                                <?php
                                                                if(!$task["tasks"]["poster"]){
                                                                ?>
                                                                    <td class="loading loading-xs position-relative" style="width:30px;"></td> 
                                                                <?php
                                                                }else{?>
                                                                    <td class="text-center text-success text-center" style="width:30px;"><i class="fa fa-check"></i></td>
                                                                <?php
                                                                }
                                                                ?>
                                                                <td class="fw-bold">Generating poster frame</td>
                                                                <td class="text-end">
                                                                    <?php 
                                                                     echo $task["tasks"]["poster"]?"Completed":"In progress...";
                                                                    ?>
                                                                </td>
                                                            </tr>
                                                            <?php
                                                        }?>
                                                        
                                                        <?php 
                                                        if(isset($task["tasks"]["thumbnails"])){
                                                            ?>
                                                            <tr>
                                                                <?php
                                                                if(!$task["tasks"]["thumbnails"]){
                                                                ?>
                                                                   <td class="loading loading-xs position-relative" style="width:30px;"></td>
                                                                <?php
                                                                }else{?>
                                                                   <td class="text-center text-success text-center" style="width:30px;"><i class="fa fa-check"></i></td>
                                                                <?php
                                                                }
                                                                ?>
                                                                <td class="fw-bold">Generating thumbnails</td>
                                                                <td class="text-end">
                                                                    <?php 
                                                                     echo $task["tasks"]["thumbnails"]?"Completed":"In progress...";
                                                                    ?>
                                                                </td>
                                                            </tr>
                                                            <?php
                                                        }?>

                                                    </tbody>
                                                </table>
                                                <?php
                                                }else{?>
                                                   <span class="fw-bold text-success">Completed!</span>
                                                <?php
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php
                            }
                            ?>
                        </div>
                    <?php
                    }?>
                <?php 
                }else{
                ?>
                
                    <div class="list-group w-50 mt-5 rounded-4 py-5 text-center">
                        <h3 class="text-muted">There are currently no video processing tasks in the queue</h3>
                    </div>

                <?php
                }
            }
            ?>
        </div>
    <?php
    }
    public static function render_video_process_page() {
        self::video_process_page();
        self::enqueue_update_script();
    }

    public static function install_ffmpeg(){
        $package = "salthareket/ffmpeg-";
        $folder = self::$repo_directory . "/src/bin/";
        if (stristr(PHP_OS, 'WIN')) {
            $package .= "win";
            $folder .= "win/";
        }else{
            $package .= "linux";
            $folder .= "linux/";
        }
        // GitHub'dan zip dosyasını indirme
        $url = self::$github_api_url . '/' . $package . '/releases/latest';

        $response = wp_remote_get($url, [
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version'),
                'Authorization' => 'Bearer ' . SALTHAREKET_TOKEN
            ]
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'FFmpeg zip file could not be downloaded: ' . $response->get_error_message(), 'action' => 'error']);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['zipball_url'])) {
            wp_send_json_error(['message' => 'Invalid response from GitHub API.', 'action' => 'error']);
        }

        // Zip dosyasını indirme
        add_filter('http_request_timeout', function($timeout) {
            return 600;
        });

        $zip_url = $data['zipball_url'];
        $zip_file = download_url($zip_url);

        remove_filter('http_request_timeout', '__return_true');

        if (is_wp_error($zip_file)) {
            wp_send_json_error(['message' => 'Failed to download FFmpeg zip file: ' . $zip_file->get_error_message(), 'action' => 'error']);
        }

        // Geçici bir dizin oluşturma
        $temp_dir = self::$vendor_directory . '/temp';
        if (!file_exists($temp_dir)) {
            mkdir($temp_dir, 0755, true);
        }

        // Zip dosyasını açma
        $unzip_result = unzip_file($zip_file, $temp_dir);

        if (is_wp_error($unzip_result)) {
            // Fallback: ZipArchive kullanarak dosyayı çıkar
            $zip = new ZipArchive();
            if ($zip->open($zip_file) === true) {
                $extract_result = $zip->extractTo($temp_dir);
                $zip->close();
                if (!$extract_result) {
                    self::recurseDelete($temp_dir);
                    wp_send_json_error(['message' => 'ZipArchive failed to extract FFmpeg package.', 'action' => 'error']);
                }
            } else {
                self::recurseDelete($temp_dir);
                wp_send_json_error(['message' => 'Zip file could not be extracted using ZipArchive.', 'action' => 'error']);
            }
        }

        @unlink($zip_file); // Geçici zip dosyasını sil

        // Çıkarılan dosyaları hedef klasöre taşıma
        $extracted_dir = glob($temp_dir . '/*')[0] ?? null;

        if ($extracted_dir && is_dir($extracted_dir)) {
            // Çıkarılan klasör içindeki zip dosyalarını bul ve çıkar
            $inner_zip_files = glob($extracted_dir . '/*.zip');

            foreach ($inner_zip_files as $inner_zip_file) {
                $inner_unzip_result = unzip_file($inner_zip_file, $extracted_dir);

                if (is_wp_error($inner_unzip_result)) {
                    $zip = new ZipArchive();
                    if ($zip->open($inner_zip_file) === true) {
                        $extract_result = $zip->extractTo($extracted_dir);
                        $zip->close();
                        if (!$extract_result) {
                            wp_send_json_error(['message' => "Failed to extract inner zip file: $inner_zip_file"]);
                        }
                    } else {
                        wp_send_json_error(['message' => "Inner zip file could not be extracted: $inner_zip_file"]);
                    }
                }

                @unlink($inner_zip_file); // İç zip dosyasını sil
            }

            if (!self::moveFolderForce($extracted_dir, $folder)) {
                self::recurseDelete($temp_dir);
                wp_send_json_error(['message' => 'Failed to move FFmpeg files to target directory.', 'action' => 'error']);
            }

            // Geçici dizini temizle
            self::recurseDelete($temp_dir);

            wp_send_json_success(['message' => 'FFmpeg successfully installed.', 'action' => 'refresh']);
        } else {
            self::recurseDelete($temp_dir);
            wp_send_json_error(['message' => 'Invalid extracted directory structure.']);
        }
    }








    public static function composer_manuel_install($package_name, $latest_version="", $silent = false) {
        error_log("composer_manuel_install işlemi başlatıldı...");
        try {
        
            $package_folder = self::$theme_root . "/vendor/".$package_name;

            // ZIP dosyasını indirme
            //$url = self::composer_get_latest_version_url($package_name);
            //$url = self::$github_api_url . '/' . $package_name . '/zipball/' . $latest_version;
            $url = self::get_package_github_url($package_name) . $latest_version;
            $tmp_file = download_url($url);

            if (is_wp_error($tmp_file) || !file_exists($tmp_file) || filesize($tmp_file) === 0) {
                error_log($url);
                error_log(print_r($tmp_file, true));
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
                //self::delete_directory(self::$repo_directory);
                if (!self::moveFolderForce($extracted_dir, $package_folder)) {
                    error_log("Yeni sürüm taşınamadı: " . $extracted_dir . " -> " . $package_folder);
                    //self::delete_directory($temp_dir);
                    wp_send_json_error(['message' => 'Yeni sürüm taşınamadı.']);
                }
                error_log("Yeni sürüm başarıyla taşındı: " . $package_folder);

                // Geçici dizini temizle
                self::delete_directory($temp_dir);
                self::update_composer_lock($package_name, $latest_version);
                if(!$silent){
                    wp_send_json_success(['message' => 'Update işlemi başarıyla tamamlandı.']);                    
                }
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
    public static function update_composer_lock($package_name, $latest_version) {
        if (!file_exists(self::$composer_lock_path)) {
            error_log("composer.lock not found.");
            return;
        }

        $lock_data = json_decode(file_get_contents(self::$composer_lock_path), true);
        if (!$lock_data) {
            error_log("composer.lock dosyası okunamadı.");
            return;
        }

        // Packagist API'den gerekli bilgileri al
        $url = "https://repo.packagist.org/p2/" . urlencode($package_name) . ".json";
        $response = wp_remote_get($url);
        if (is_wp_error($response)) {
            error_log("Packagist API'den bilgi alınamadı: " . $response->get_error_message());
            return;
        }

        $package_data = json_decode(wp_remote_retrieve_body($response), true);
        $package_versions = $package_data['packages'][$package_name] ?? [];

        // Doğru sürüm bilgilerini bul
        $package_version_data = null;
        foreach ($package_versions as $version) {
            if ($version['version'] === $latest_version) {
                $package_version_data = $version;
                break;
            }
        }

        if (!$package_version_data) {
            error_log("İstenilen sürüm bulunamadı: $package_name - $latest_version");
            return;
        }

        // composer.lock içeriğini güncelle
        $packages_sections = ['packages', 'packages-dev'];
        foreach ($packages_sections as $section) {
            if (!isset($lock_data[$section])) {
                continue;
            }

            foreach ($lock_data[$section] as &$package) {
                if ($package['name'] === $package_name) {
                    $package = [
                        'name' => $package_name,
                        'version' => $latest_version,
                        'source' => [
                            'type' => 'git',
                            'url' => $package_version_data['source']['url'] ?? '',
                            'reference' => $package_version_data['source']['reference'] ?? '',
                        ],
                        'dist' => [
                            'type' => 'zip',
                            'url' => $package_version_data['dist']['url'] ?? '',
                            'reference' => $package_version_data['dist']['reference'] ?? '',
                            'shasum' => $package_version_data['dist']['shasum'] ?? '',
                        ],
                        'notification-url' => 'https://packagist.org/downloads/',
                        'require' => $package_version_data['require'] ?? [],
                        'require-dev' => $package_version_data['require-dev'] ?? [],
                        'type' => 'library',
                        'autoload' => $package_version_data['autoload'] ?? [],
                        'license' => $package_version_data['license'] ?? [],
                        'authors' => $package_version_data['authors'] ?? [],
                        'description' => $package_version_data['description'] ?? '',
                        'homepage' => $package_version_data['homepage'] ?? '',
                        'support' => $package_version_data['support'] ?? [],
                        'funding' => $package_version_data['funding'] ?? [],
                        'time' => $package_version_data['time'] ?? '',
                    ];
                    error_log("Paket güncellendi: $package_name - $latest_version");
                    break;
                }
            }
        }

        // composer.lock genel alanları düzenle
        $lock_data['aliases'] = $lock_data['aliases'] ?? [];
        $lock_data['stability-flags'] = (object) ($lock_data['stability-flags'] ?? []);
        $lock_data['platform'] = (object) ($lock_data['platform'] ?? []);
        $lock_data['platform-dev'] = (object) ($lock_data['platform-dev'] ?? []);

        // composer.lock dosyasını yaz
        $json_data = json_encode($lock_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (file_put_contents(self::$composer_lock_path, $json_data) === false) {
            error_log("composer.lock yazılamadı: " . self::$composer_lock_path);
            return;
        }

        // Composer uygulamasını çalıştırarak content-hash'i güncelle
        try {
            $application = new Application();
            $application->setAutoExit(false);

            $input = new ArrayInput([
                'command' => 'update',
                '--lock' => true,
                '--no-install' => true,
                '--working-dir' => dirname(self::$composer_path)
            ]);

            $output = new BufferedOutput();
            $application->run($input, $output);

            self::update_installed_package($package_name, $package_version_data);

            error_log("composer.lock ve content-hash başarıyla güncellendi.");
            error_log($output->fetch());
        } catch (Exception $e) {
            error_log("Composer uygulaması çalıştırılamadı: " . $e->getMessage());
        }

        error_log("composer.lock güncellemesi tamamlandı.");
    }
    public static function update_installed_package($package_name, $package_data) {
        $vendor_composer_dir = self::$theme_root . '/vendor/composer';

        error_log("update_installed_package();");
        error_log("package name: ".$package_name);
        error_log("package data: ");
        error_log(json_encode($package_data));

        //$vendor_composer_dir_encoded = str_replace('\\', '\\\\', $vendor_composer_dir);
        $vendor_composer_dir_encoded = str_replace('/', '\\', $vendor_composer_dir);

        // 1. installed.json dosyasını kontrol et
        $installed_json_path = $vendor_composer_dir . '/installed.json';
        if (!file_exists($installed_json_path)) {
            error_log("installed.json dosyası bulunamadı.");
            return;
        }

        // installed.json içeriğini oku
        $installed_data = json_decode(file_get_contents($installed_json_path), true);

        // Sadece packages anahtarındaki paketi güncelle
        $updated = false;
        foreach ($installed_data['packages'] as &$package) {
            if ($package['name'] === $package_name) {
                // Güncelleme işlemi
                $package['version'] = $package_data['version'] ?? 'dev-master';
                $package['version_normalized'] = $package_data['version_normalized'] ?? preg_replace('/^v/', '', $package['version'] ?? '0.0.0') . '.0';
                $package['source'] = $package_data['source'] ?? [];
                $package['dist'] = $package_data['dist'] ?? [];
                $package['time'] = $package_data['time'] ?? "";
                $updated = true;
                break;
            }
        }

        if (!$updated) {
            error_log("installed.json içinde paket bulunamadı: $package_name");
            return;
        }

        // Güncellenmiş installed.json'u yaz
        file_put_contents($installed_json_path, json_encode($installed_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        error_log("installed.json başarıyla güncellendi.");




        // 2. installed.php dosyasını güncelle
        $installed_php_path = $vendor_composer_dir . '/installed.php';
        $installed_php_save_path = $vendor_composer_dir . '/installed-test.php';
        if (!file_exists($installed_php_path)) {
            error_log("installed.php dosyası bulunamadı.");
            return;
        }

        // installed.php'yi include ederek oku
        $installed_php_data = include $installed_php_path;

        error_log("installed_php_data:");
        error_log(print_r($installed_php_data, true));

        if (!isset($installed_php_data['versions'][$package_name])) {
            error_log("installed.php içinde paket bulunamadı: $package_name");
            return;
        }

        $installed_php_data['root']["install_path"] = str_replace($vendor_composer_dir_encoded , "{__DIR__} . {quote}", $installed_php_data['root']["install_path"]);

        foreach($installed_php_data['versions'] as &$version){
            if(isset($version["install_path"])){
                $install_path = str_replace($vendor_composer_dir_encoded , "{__DIR__} . {quote}", $version["install_path"]);
                $version["install_path"] = $install_path;                
            }
        }

        // Doğru değerleri ayarla
        $pretty_version = $package_data['version'] ?? 'dev-master'; // Varsayılan değer: dev-master
        $version = $package_data['version_normalized'] ?? preg_replace('/^v/', '', $pretty_version) . '.0';
        $reference = $package_data['source']['reference'] ?? '';

        if (empty($reference)) {
            error_log("reference bilgisi eksik: $package_name");
        }

        // Sadece hedef paketi güncelle
        /*$installed_php_data['versions'][$package_name] = array_merge(
            $installed_php_data['versions'][$package_name],
            [
                'version' => $version,
                'pretty_version' => $pretty_version,
                'reference' => $reference,
                //'install_path' => "__DIR__ . '/../" . str_replace('/', '/', $package_name) . "'",
            ]
        );*/

        foreach ($installed_php_data['versions'] as $key => &$version_data) {
            if ($key === $package_name) {
                // Hedef paketi güncelle
                $version_data['version'] = $version;
                $version_data['pretty_version'] = $pretty_version;
                $version_data['reference'] = $reference;
                //$version_data['install_path'] = "__DIR__ . '/../" . str_replace('/', '/', $package_name) . "'";
            }
        }

        // installed.php'yi doğru formatta yaz
        $php_content = '<?php return ' . var_export($installed_php_data, true) . ';';

        // __DIR__ düzeltmesi yap
        $php_content = str_replace("'{__DIR__}", '__DIR__', $php_content);
        $php_content = str_replace("{quote}", "'", $php_content);

        $php_content = preg_replace([
            '/\n\s+array \(/',       // "array (" öncesindeki boşluk ve alt satırı kaldır
            '/array \(/',            // "array ("'ı "array(" yap
            '/,\s+\)/',              // Kapanış parantez öncesindeki fazladan virgülü kaldır
            '/\),/',                 // '),' satırlarını alt satıra taşı
            "/\n\s*\n/",             // Çift satır aralığını kaldır
            '/(\s+\'dev\' => true),/', // 'dev' => true) alt satıra taşınmasını sağla
        ], [
            ' array(',               // Tek satırda "array("
            'array(',                // Daha kompakt "array("
            ')',                     // Fazladan virgülleri kaldır
            "),\n",                  // Kapanış parantezi alt satıra taşı
            "\n",                    // Çift satır aralığını teke indir
            "$1\n)",                 // 'dev' => true'yi alt satıra taşı
        ], $php_content);

        // 3. Boş array'lerin düzgün görünmesi
        $php_content = preg_replace('/array\(\s*\)/', 'array()', $php_content);

        file_put_contents($installed_php_path, $php_content);
        error_log("installed.php başarıyla güncellendi."); 
    }


    private static function allow_git_safe_directories() {
        $vendorDir = get_template_directory() . '/vendor';

        if (!is_dir($vendorDir)) {
            return;
        }

        $directories = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($vendorDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($directories as $dir) {
            if ($dir->isDir() && file_exists($dir->getPathname() . '/.git')) {
                $gitPath = $dir->getPathname();
                exec('git config --global --add safe.directory "' . $gitPath . '"');
                error_log("✅ Güvenli git klasörü eklendi: " . $gitPath);
            }
        }
    }



    public static function composer($package_name="", $remove = false) {
        error_log("composer calıstıııııı -> ".$package_name);

        self::allow_git_safe_directories();

        try {

            if (!file_exists(self::$composer_path)) {
                wp_send_json_error(['message' => 'composer.json is not found.']);
            }

            
            //if($manually){
                $updates = self::get_composer_updates();
                if($updates){
                    $dependencies = array_filter($updates, function ($package) {
                        return isset($package['dependency']) && $package['dependency'] === true;
                    });
                    if($dependencies){
                        foreach($dependencies as $package){
                            self::composer_manuel_install($package["package"], $package["latest"], true);
                        }
                        //update_option('composer_dependencies', $dependencies);
                        //header("Refresh: 0");
                        //wp_send_json_success(['message' => "Refreshing page...", "action" => "refresh" ]);
                        //exit;
                    }                
                }else{
                    wp_send_json_success(['message' => 'No updates or installations performed. 2', "action" => "nothing" ]);
                }                
            //}

            $args = array(
                'command' => 'update',
                '--working-dir' => get_template_directory()
            );
            if(!empty($package_name)){
                $args["command"] = $remove?"remove":"require";
                $args["packages"] = [$package_name];
            }else{
                /*$new_packages = array_diff(array_keys(self::get_required_packages()), self::get_installed_packages());
                if (!empty($new_packages)) {
                    $args['command'] = 'install';
                    unset($args['packages']);
                }*/
            }
            
            error_log(json_encode($args));

            $app = new Application();
            $app->setAutoExit(false);
            $input = new ArrayInput($args);
            $output = new BufferedOutput();
            $app->run($input, $output);

            $raw_output = $output->fetch();
            $lines = explode("\n", $raw_output);

            error_log(json_encode($lines));

            foreach ($lines as $line) {
                if (strpos(trim($line), 'Could not find package') !== false) {
                    wp_send_json_error(['message' => "Could not find package: <strong>$package_name</strong>", "action" => "error" ]);
                    exit;
                }
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

                /*["id" => "copy_fields", "name" => "Copying ACF Fields"],
                ["id" => "register_fields", "name" => "Registering ACF Fields"],
                ["id" => "update_fields", "name" => "Updating ACF Fields"],*/

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


    public static function get_composer_updates() {

        // HOME veya COMPOSER_HOME ortam değişkenini ayarla (Linux/macOS)
        if (!getenv('HOME')) {
            putenv('HOME=' . sys_get_temp_dir()); // Geçici dizini HOME olarak ayarla
            $_SERVER['HOME'] = sys_get_temp_dir();
        }

        // Windows için COMPOSER_HOME ayarı (Opsiyonel)
        if (!getenv('COMPOSER_HOME')) {
            putenv('COMPOSER_HOME=' . sys_get_temp_dir());
            $_SERVER['COMPOSER_HOME'] = sys_get_temp_dir();
        }

        $app = new Application();
        $app->setAutoExit(false);
        $input = new ArrayInput([
            'command' => 'outdated',
            '--format' => 'json',
            '--no-ansi' => true,
            '--working-dir' => get_template_directory()
        ]);
        $output = new BufferedOutput();

        try {
            $app->run($input, $output);
            $rawOutput = $output->fetch();
            //error_log($rawOutput);

            $jsonStart = strpos($rawOutput, '{');
            if ($jsonStart === false) {
                error_log('Error: No valid JSON output found.');
                return [];
            }
            $cleanedJson = substr($rawOutput, $jsonStart);
            $data = json_decode($cleanedJson, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('JSON decode error: ' . json_last_error_msg());
                return [];
            }

            $packages = [];
            foreach ($data['installed'] as $package) {
                $packages[] = [
                    'package' => $package['name'],
                    'current' => $package['version'],
                    'latest' => $package['latest'],
                    'description' => $package['description'] ?? '',
                    'dependency' => self::is_composer_dependency($package['name'], $package['latest'])
                ];
            }

            error_log(" - UPDATES:");
            error_log(json_encode($packages));
            return $packages;

        } catch (Exception $e) {
            error_log('Error: ' . $e->getMessage());
            return [];
        }
    }
    public static function is_composer_dependency($package_name, $latest_version) {
        if (!file_exists(self::$composer_lock_path)) {
            error_log("composer.lock dosyası bulunamadı.");
            return false;
        }

        try {
            // composer.lock dosyasını oku
            $composerLockContent = file_get_contents(self::$composer_lock_path);

            if ($composerLockContent === false) {
                error_log("composer.lock dosyası okunamadı.");
                return false;
            }

            // JSON'u ayrıştır
            $composerLockData = json_decode($composerLockContent, true);

            if (!isset($composerLockData['packages']) && !isset($composerLockData['packages-dev'])) {
                error_log("composer.lock dosyasında 'packages' veya 'packages-dev' bulunamadı.");
                return false;
            }

            // composer/composer bağımlılıklarını al
            $composerDependencies = [];
            $composerRequirements = [];

            foreach (['packages', 'packages-dev'] as $key) {
                foreach ($composerLockData[$key] ?? [] as $package) {
                    if ($package['name'] === 'composer/composer' && isset($package['require'])) {
                        $composerDependencies = array_keys($package['require']);
                        $composerRequirements = $package['require'];
                        break 2; // Her iki alanı da kontrol ettiğimiz için dış döngüyü de kırıyoruz
                    }
                }
            }

            // Paketin composer/composer bağımlılığı olup olmadığını kontrol et
            if (!in_array($package_name, $composerDependencies, true)) {
                error_log("{$package_name} bağımlılık listesinde bulunamadı.");
                return false;
            }

            // Sürüm kontrolü yap
            if (isset($composerRequirements[$package_name])) {
                $requiredVersion = $composerRequirements[$package_name];

                // Sürüm uyumluluğunu kontrol et
                if (!self::is_version_compatible($latest_version, $requiredVersion)) {
                    error_log("{$package_name}: {$latest_version} mevcut gereksinimlere uygun değil ({$requiredVersion}).");
                    return false;
                }
            }

            return true; // Paket composer/composer bağımlılığı ve sürümü uyumlu
        } catch (Exception $e) {
            // Hata durumunda false döner
            error_log('Hata: ' . $e->getMessage());
            return false;
        }
    }
    private static function is_version_compatible($version, $constraint) {
        // Composer'ın semver kütüphanesi ile uyumluluğu kontrol et
        if (!class_exists('Composer\Semver\Semver')) {
            error_log('Composer Semver kütüphanesi yüklenmedi.');
            return false;
        }

        return \Composer\Semver\Semver::satisfies($version, $constraint);
    }








    private static function fix_packages(){
        $fixes_file = get_template_directory() . "/vendor/salthareket/theme/src/fix/index.php";
        if(file_exists($fixes_file)){
            $fixes = include $fixes_file;
            if($fixes){
                foreach($fixes as $fix){
                    if($fix["version"] == self::get_package_version($fix["package"])){
                        $file = get_template_directory() . "/vendor/salthareket/theme/src/fix/".basename($fix["file"]);
                        $target_file = get_template_directory()."/vendor/".$fix["package"]."/".$fix["file"];
                        if($fix["status"] && file_exists($file) && file_exists($target_file)){
                            self::fileCopy($file, $target_file);
                            error_log($fix["package"].":".$fix["version"]." fixed...");
                        }                            
                    }
                }
            }
        }
    }
    private static function copy_theme(){
        $srcDir = SH_PATH . 'content/theme';
        $target_dir = get_template_directory() . '/theme';
        error_log("copy_theme() -> ".$srcDir." -> ".$target_dir);
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        } 
        self::recurseCopy($srcDir, $target_dir);
        
    }
    public static function update_theme_apperance(){
        $style_file = self::$theme_root . '/style.css';
        $text_domain = basename(get_template_directory());
        $theme_name = ucwords(str_replace('-', ' ', $text_domain));
        if (file_exists($style_file)) {
            $style_content = file_get_contents($style_file);    
            $style_content = preg_replace('/(Theme Name:\\s*).*$/m', "Theme Name: $theme_name", $style_content);
            $style_content = preg_replace('/(Text Domain:\\s*).*$/m', "Text Domain: $text_domain", $style_content);
            file_put_contents($style_file, $style_content);
        } else {
            error_log("style.css dosyası bulunamadı.");
            return;
        }

        $screenshot_file = self::$theme_root . '/screenshot.png';
        $bg_color = [214, 223, 39]; // #d6df27
        $text_color = [17, 17, 17]; // #111
        $font_size = 120;
        $image_width = 1200;
        $image_height = 900;
        $image = imagecreatetruecolor($image_width, $image_height);
        $bg_color_alloc = imagecolorallocate($image, $bg_color[0], $bg_color[1], $bg_color[2]);
        imagefill($image, 0, 0, $bg_color_alloc);
        $text_color_alloc = imagecolorallocate($image, $text_color[0], $text_color[1], $text_color[2]);
        $font_path = self::$repo_directory . '/src/content/fonts/Lexend_Deca/static/LexendDeca-Bold.ttf';
        if (!file_exists($font_path)) {
            error_log("Font dosyası bulunamadı: $font_path");
            imagedestroy($image);
            return;
        }
        $bbox = imagettfbbox($font_size, 0, $font_path, $theme_name);
        $text_width = $bbox[2] - $bbox[0];
        $text_height = $bbox[1] - $bbox[7];
        $x = (int)(($image_width - $text_width) / 2); // Açıkça int dönüşümü
        $y = (int)(($image_height + $text_height) / 2); // Açıkça int dönüşümü
        imagettftext($image, $font_size, 0, $x, $y, $text_color_alloc, $font_path, $theme_name);
        imagepng($image, $screenshot_file);
        imagedestroy($image);
    }

    /*private static function copy_templates(){
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
    }*/
    private static function copy_fields(){
        $srcDir = SH_PATH . 'content/acf-json';
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
    private static function update_fields() {
        global $wpdb;
        $post_name = "group_66e309dc049c4";
        $query = $wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_name = %s", $post_name);
        $post_id = $wpdb->get_var($query);

        if ($post_id) {
            acf_save_post_block_columns_action($post_id, true);
            $cache = new UpdateFlexibleFieldLayouts($post_id, "acf_block_columns", $post_name);
            $cache->update_cache();
        }
        get_theme_styles([], true);
    }
    private static function npm_install(): string{
        $workingDir = ABSPATH;
        if (!is_dir($workingDir)) {
            wp_send_json_error(['message' => 'npm path not found: '.$dir]);
        }

        error_log(SH_PATH . "content/package.json -> ".$workingDir .'package.json');
        if (!file_exists($workingDir .'package.json')) {
            self::fileCopy(SH_PATH . "content/package.json", $workingDir .'package.json');
        }
        
        if(!isLocalhost()){
           return true;
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
    private static function install_mu_plugins(){
        $srcDir = SH_PATH . 'content/mu-plugins';
        $target_dir = WP_CONTENT_DIR . '/mu-plugins';
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true); 
        }
        if (is_dir($srcDir)) {
            self::recurseCopy($srcDir, $target_dir);
        }
    }
    private static function install_wp_plugins($plugin_types){
        \PluginManager::check_and_install_required_plugins($plugin_types);
    }
    private static function install_local_plugins($plugin_types){
        \PluginManager::check_and_update_local_plugins($plugin_types);
    }
    private static function compile_methods(){
        acf_methods_settings();
    }
    private static function generate_files(){
        $theme_styles = acf_get_theme_styles();
        save_theme_styles_colors($theme_styles);
        save_theme_styles_header_themes($theme_styles["header"]);
        get_theme_styles([], true);
    }
    private static function compile_js_css(){
        acf_compile_js_css();
    }
    private static function defaults($plugin_types = array()){
        self::disable_yearmonth_folders();
        self::update_site_logo(SH_PATH ."content/logo-salt-hareket.png");
        self::set_default_acf_values($plugin_types);
        self::create_home_page();
        self::create_menu();
        self::update_image_sizes();
        self::set_permalink_to_post_name();
        if(in_array("membership", $plugin_types)){
            update_option("enable_membership", true);
        }
        if (defined("WP_ROCKET_VERSION")) {
            self::wprocket_load_settings();
        }
        if (\PluginManager::is_plugin_installed("wp-hide-security-enhancer-pro/wp-hide.php")) {
            self::wph_load_settings();
        }
    }



    public static function run_task() {
        check_ajax_referer('update_theme_nonce', 'nonce');
        $task_id = isset($_POST['task_id']) ? sanitize_text_field($_POST['task_id']) : '';
        $plugin_types = isset($_POST['plugin_types']) && is_array($_POST['plugin_types'])
        ? array_map('sanitize_text_field', $_POST['plugin_types'])
        : [];
        $tasks_status = isset($_POST['tasks_status']) && is_array($_POST['tasks_status'])
        ? array_map('sanitize_text_field', $_POST['tasks_status'])
        : [];
        if($tasks_status){
            self::$tasks_status = $tasks_status;
        }
        try {
            switch ($task_id) {
                case 'fix_packages':
                    self::fix_packages();
                    self::update_task_status('fix_packages', true);
                    $tasks_status = json_encode(self::$tasks_status);
                    wp_send_json_success(['message' => 'Composer packages fixed successfully', 'tasks_status' => $tasks_status ]);
                    break;
                case "update_theme_apperance" :
                    self::update_theme_apperance();
                    self::update_task_status('update_theme_apperance', true);
                    $tasks_status = json_encode(self::$tasks_status);
                    wp_send_json_success(['message' => 'Theme apperances updated successfully', 'tasks_status' => $tasks_status ]);
                    break;
                case 'copy_theme':
                    self::copy_theme();
                    self::update_task_status('copy_theme', true);
                    $tasks_status = json_encode(self::$tasks_status);
                    wp_send_json_success(['message' => 'Theme files copied successfully', 'tasks_status' => $tasks_status ]);
                    break;
                /*case 'copy_templates':
                    self::copy_templates();
                    self::update_task_status('copy_templates', true);
                    wp_send_json_success(['message' => 'Template filess copied successfully', 'tasks_status' => $tasks_status ]);
                    break;
                case 'copy_fonts':
                    self::copy_fonts();
                    self::update_task_status('copy_fonts', true);
                    $tasks_status = json_encode(self::$tasks_status);
                    wp_send_json_success(['message' => 'Fonts copied successfully', 'tasks_status' => $tasks_status ]);
                    break;*/
                case 'copy_fields':
                    self::copy_fields();
                    self::update_task_status('copy_fields', true);
                    $tasks_status = json_encode(self::$tasks_status);
                    wp_send_json_success(['message' => 'ACF fields copied successfully', 'tasks_status' => $tasks_status ]);
                    break;
                case 'register_fields':
                    self::register_fields();
                    self::update_task_status('register_fields', true);
                    $tasks_status = json_encode(self::$tasks_status);
                    wp_send_json_success(['message' => 'ACF fields registered successfully', 'tasks_status' => $tasks_status ]);
                    break;
                case "update_fields":
                    self::update_fields();
                    self::update_task_status('update_fields', true);
                    $tasks_status = json_encode(self::$tasks_status);
                    wp_send_json_success(['message' => 'ACF fields updated successfully', 'tasks_status' => $tasks_status ]);
                    break;
                case "install_mu_plugins":
                    self::install_mu_plugins();
                    self::update_task_status('install_mu_plugins', true);
                    $tasks_status = json_encode(self::$tasks_status);
                    wp_send_json_success(['message' => 'Must Use plugins updated successfully', 'tasks_status' => $tasks_status ]);
                    break;
                case 'install_wp_plugins':
                    ob_start();
                    self::install_wp_plugins($plugin_types);
                    ob_clean();
                    self::update_task_status('install_wp_plugins', true);
                    $tasks_status = json_encode(self::$tasks_status);
                    wp_send_json_success(['message' => 'WP plugins installed successfully', 'tasks_status' => $tasks_status ]);
                    break;
                case 'install_local_plugins':
                    self::install_local_plugins($plugin_types);
                    self::update_task_status('install_local_plugins', true);
                    $tasks_status = json_encode(self::$tasks_status);
                    wp_send_json_success(['message' => 'Local plugins installed successfully', 'tasks_status' => $tasks_status ]);
                    break;
                case 'npm_install':
                    self::npm_install();
                    self::update_task_status('npm_install', true);
                    $tasks_status = json_encode(self::$tasks_status);
                    wp_send_json_success(['message' => 'NPM Packages installed successfully', 'tasks_status' => $tasks_status ]);
                    break;
                case 'compile_methods':
                    self::compile_methods();
                    self::update_task_status('compile_methods', true);
                    $tasks_status = json_encode(self::$tasks_status);
                    wp_send_json_success(['message' => 'ACF Methods compiled successfully', 'tasks_status' => $tasks_status ]);
                    break;
                case 'generate_files':
                    self::generate_files();
                    self::update_task_status('generate_files', true);
                    $tasks_status = json_encode(self::$tasks_status);
                    wp_send_json_success(['message' => 'Files generated successfully', 'tasks_status' => $tasks_status ]);
                    break;
                case 'compile_js_css':
                    self::compile_js_css();
                    self::update_task_status('compile_js_css', true);
                    $tasks_status = json_encode(self::$tasks_status);
                    wp_send_json_success(['message' => 'JS/CSS compiled successfully', 'tasks_status' => $tasks_status ]);
                    break;
                case 'defaults':
                    self::defaults($plugin_types);
                    self::update_task_status('defaults', true);
                    $tasks_status = json_encode(self::$tasks_status);
                    wp_send_json_success(['message' => "Default values have been successfully created.", 'tasks_status' => $tasks_status ]);
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
                //error_log($srcPath." -> ".$destPath);
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
    public static function moveFolderForce($src, $dst) {
        if (!is_dir($src)) {
            error_log("Kaynak klasör bulunamadı: $src");
            return false;
        }

        try {
            // Kopyala
            self::recurseCopy($src, $dst);

            // Kopyalama başarılı ise kaynak klasörü sil
            self::recurseDelete($src);

            return true;
        } catch (Exception $e) {
            error_log("Taşıma işlemi başarısız: " . $e->getMessage());
            return false;
        }
    }
    private static function recurseDelete($dir) {
        if (!is_dir($dir)) return;
        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? self::recurseDelete($path) : unlink($path);
        }
        rmdir($dir);
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


    


    // Default Contents
    private static function update_site_logo($logo_path) {
        // ACF'deki mevcut logo ID'sini kontrol et
        $current_logo_id = get_option('options_logo_footer'); // 'option' global ayar sayfası için

        if ($current_logo_id) {
            // Mevcut logo kontrolü
            $current_logo_url = wp_get_attachment_url($current_logo_id);
            if ($current_logo_url) {
                error_log("Logo already exists: " . $current_logo_url);
                return; // Logo zaten mevcutsa işlemi durdur
            }
        }

        // Logo dosyasını WordPress medya kütüphanesine yükle
        $attachment_id = self::upload_image($logo_path);

        if ($attachment_id) {
            // "logo" adlı ACF alanını güncelle
            update_field('logo', $attachment_id, 'option'); // 'option' global ayar sayfası için
            if(!get_option('options_logo_footer')){
                update_field('logo_footer', $attachment_id, 'option');
            }
            error_log("Logo successfully updated to ACF field.");
        } else {
            error_log("Failed to upload logo or update ACF field.");
        }
    }
    private static function upload_image($file_path) {
        // WordPress yükleme sistemiyle dosyayı içeri aktar
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        // Dosya sistemine uygun hale getir
        $filetype = wp_check_filetype($file_path);
        $upload_dir = wp_upload_dir();

        // Dosyanın yükleneceği hedef yol
        $target_path = $upload_dir['path'] . '/' . basename($file_path);

        // Dosyayı kopyala
        if (!copy($file_path, $target_path)) {
            error_log("Failed to copy logo file to upload directory.");
            return false;
        }

        // WordPress medya kütüphanesine ekle
        $attachment = [
            'guid'           => $upload_dir['url'] . '/' . basename($file_path),
            'post_mime_type' => $filetype['type'],
            'post_title'     => sanitize_file_name(basename($file_path)),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        // Attachment ID'sini al
        $attachment_id = wp_insert_attachment($attachment, $target_path);

        // Metadata oluştur
        $attach_data = wp_generate_attachment_metadata($attachment_id, $target_path);
        wp_update_attachment_metadata($attachment_id, $attach_data);

        return $attachment_id;
    }
    private static function set_default_acf_values($plugin_types=array()) {
        $header_tool_language = [
            'menu_item' => 'languages',
            'menu_type' => 'inactive'
        ];
        $default_values = [
            "header_container" => "default",
            "header_fixed" => "top",
            "header_affix" => 1,
            "header_hide_on_scroll_down" => 1,
            "header_start" => [ // Clone field içeriği
                "type" => "brand",
                "logo_height" => 0,
                "align" => "start"
            ],
            "header_center" => [ // Clone field içeriği
                "type" => "empty"
            ],
            "header_end" => [
                'type' => 'tools',
                'align' => 'end',
                'header_tools' => [
                    'header_tools' => [
                        [
                            'menu_item' => 'navigation',
                            'menu_type' => 'offcanvas',
                            'menu_nav' => 'header-menu',
                            'offcanvas_settings' => [
                                'position' => 'top',
                                'fullscreen' => 1,
                                'container' => 'default'
                            ],
                        ]
                    ],
                ],
            ],
            "footer_menu" => [
                [
                    "name" => "main",
                    "menu" => "header-menu"
                ]
            ],
            "default_container" => "",
            "default_ratio" => "16x9",
            "seperate_css" => 1,
            "seperate_js" => 1,
            "enable_production" => 1
        ];
        if(in_array("multilanguage", $plugin_types)){
            array_unshift($default_values["header_end"]["header_tools"]["header_tools"], $header_tool_language);
        }
        foreach ($default_values as $field_key => $value) {
            if(empty(get_option("options_".$field_key))){
                update_field($field_key, $value, 'option');
            }
        }
        error_log("Default header values have been set.");
    }
    private static function create_home_page() {
        if(get_option("page_on_front")){
            return;
        }
        // Sayfa içerik bloğu
        $block_content = wp_slash('<!-- wp:acf/text {"name":"acf/text","data":{"block_settings_hero":"0","_block_settings_hero":"field_66968c7c1b738_field_65f2ed0554105","block_settings_sticky_top":"0","_block_settings_sticky_top":"field_66968c7c1b738_field_66e8f7e0f1824","block_settings_stretch_height":"0","_block_settings_stretch_height":"field_66968c7c1b738_field_66429a3093974","block_settings_wrapper_class":"","_block_settings_wrapper_class":"field_66968c7c1b738_field_670e7b1be0435","block_settings_container":"lg","_block_settings_container":"field_66968c7c1b738_field_65f2ed055f287","block_settings_height":"auto","_block_settings_height":"field_66968c7c1b738_field_65f2ed0557b77","block_settings_margin_top":"","_block_settings_margin_top":"field_65f9d3527ed2f","block_settings_margin_left":"","_block_settings_margin_left":"field_65f9d3a07ed31","block_settings_margin_right":"","_block_settings_margin_right":"field_65f9d3b87ed32","block_settings_margin_bottom":"","_block_settings_margin_bottom":"field_65f9d3c47ed33","block_settings_margin":"","_block_settings_margin":"field_66968c7c1b738_field_65f9d3207ed2e","block_settings_padding_top":"5","_block_settings_padding_top":"field_673d11dd7a128","block_settings_padding_left":"","_block_settings_padding_left":"field_673d11dd7a12c","block_settings_padding_right":"","_block_settings_padding_right":"field_673d11dd7a130","block_settings_padding_bottom":"5","_block_settings_padding_bottom":"field_673d11dd7a134","block_settings_padding":"","_block_settings_padding":"field_66968c7c1b738_field_673d11dd7a126","block_settings_text_color":"","_block_settings_text_color":"field_66968c7c1b738_field_661a7d9ea0310","block_settings_vertical_align":"center","_block_settings_vertical_align":"field_66968c7c1b738_field_661c91c58dc73","block_settings_text_align":{"xxxl":"center","xxl":"center","xl":"center","lg":"center","md":"center","sm":"center","xs":"center"},"_block_settings_text_align":"field_66968c7c1b738_field_6642297e21c44","block_settings_horizontal_align":{"xxxl":"center","xxl":"center","xl":"center","lg":"center","md":"center","sm":"center","xs":"center"},"_block_settings_horizontal_align":"field_66968c7c1b738_field_673d17c8afeca","block_settings_column_active":"0","_block_settings_column_active":"field_66216f8939b9d","block_settings_column":"","_block_settings_column":"field_66968c7c1b738_field_66216f5d39b9c","block_settings_color":"","_block_settings_color":"field_66565b8dc73a1","block_settings_type":"none","_block_settings_type":"field_66d876dc1d556","block_settings_image_mask":"","_block_settings_image_mask":"field_671e529d3a857","block_settings_background":"","_block_settings_background":"field_66968c7c1b738_field_669675502a8a6","block_settings_custom_id":"","_block_settings_custom_id":"field_66968c7c1b738_field_674d65b2e1dd0","block_settings_column_id":"jtmbu","_block_settings_column_id":"field_66968c7c1b738_field_67213addcfaf3","block_settings":"","_block_settings":"field_65f9e036320a5","collapsible":"0","_collapsible":"field_671badf2b06b5","text":"<h1 class=\"title-xxl fw-600\" style=\"text-align: center;\"><span style=\"color: #168ec9;\">Welcome to Salthareket!</span></h1><p class=\"text-lg\" style=\"text-align: center;\"><span style=\"color: #666666;\">Salthareket is a lightweight and modular WordPress theme designed to bring speed, flexibility, and ease of customization to your website. Built with modern development practices, it seamlessly integrates with popular tools like ACF and Timber, offering a developer-friendly structure and a user-friendly experience. Whether you are building a blog, an e-commerce site, or a corporate platform, Salthareket adapts to your needs, empowering you to create without limits.</span></p>","_text":"field_65f1b3a9958b2"},"mode":"auto"} /-->');

        // Sayfanın var olup olmadığını kontrol et
         $query = new WP_Query([
            'post_type' => 'page',
            'title' => 'Home',
            'post_status' => 'publish',
        ]);

        if ($query->have_posts()) {
            // Sayfa zaten varsa, onu ana sayfa olarak ayarla
            $existing_page = $query->posts[0];
            update_option('page_on_front', $existing_page->ID);
            update_option('show_on_front', 'page');
            return;
        }

        // Yeni bir sayfa oluştur
        $page_id = wp_insert_post([
            'post_title' => 'Home',
            'post_content' => $block_content,
            'post_status' => 'publish',
            'post_type' => 'page',
        ]);

        if ($page_id) {
            // Yeni oluşturulan sayfayı ana sayfa olarak ayarla
            update_option('page_on_front', $page_id);
            update_option('show_on_front', 'page');
            error_log("Home page created and set as front page.");
        } else {
            error_log("Failed to create Home page.");
        }
    }
    private static function create_menu() {
        $menus = wp_get_nav_menus();
        if (!empty($menus)) {
           return;
        }
        // Menü adını ve konumunu tanımla
        $menu_name = 'header';
        $menu_location = 'header-menu';

        // Menü var mı kontrol et
        $menu_exists = wp_get_nav_menu_object($menu_name);

        if (!$menu_exists) {
            // Menü oluştur
            $menu_id = wp_create_nav_menu($menu_name);

            // Menü konumunu kaydet
            $locations = get_theme_mod('nav_menu_locations');
            $locations[$menu_location] = $menu_id;
            set_theme_mod('nav_menu_locations', $locations);
            //register_nav_menus(get_menu_locations());

            // "Home" sayfasını menüye ekle
            $home_page = get_page_by_title('Home');
            if ($home_page) {
                wp_update_nav_menu_item($menu_id, 0, [
                    'menu-item-title' => $home_page->post_title,
                    'menu-item-object' => 'page',
                    'menu-item-object-id' => $home_page->ID,
                    'menu-item-type' => 'post_type',
                    'menu-item-status' => 'publish',
                ]);
            }

            error_log("Menu '$menu_name' created and 'Home' page added.");
        } else {
            error_log("Menu '$menu_name' already exists.");
        }
    }
    private static function update_image_sizes() {
        // Mevcut Medium boyutlarını kontrol et
        $current_medium_width = get_option('medium_size_w');
        $current_medium_height = get_option('medium_size_h');

        // Mevcut Large boyutlarını kontrol et
        $current_large_width = get_option('large_size_w');
        $current_large_height = get_option('large_size_h');

        // Eğer boyutlar 300x300 (medium) ve 1024x1024 (large) ise güncelle
        if ($current_medium_width == 300 && $current_medium_height == 300 &&
            $current_large_width == 1024 && $current_large_height == 1024) {
            
            // Medium boyutlarını güncelle
            update_option('medium_size_w', 1200); // Yeni genişlik
            update_option('medium_size_h', 0);    // Yükseklik sınırsız
            update_option('medium_crop', 0);     // Kırpma yok

            // Large boyutlarını güncelle
            update_option('large_size_w', 1920); // Yeni genişlik
            update_option('large_size_h', 0);    // Yükseklik sınırsız
            update_option('large_crop', 0);     // Kırpma yok

            // Log mesajı
            error_log("Medium ve Large image sizes güncellendi.");
        } else {
            error_log("Boyutlar uygun değil, güncelleme yapılmadı.");
        }
    }
    private static function set_permalink_to_post_name(){
        global $wp_rewrite;
        update_option('permalink_structure', '/%postname%/');
        $wp_rewrite->set_permalink_structure('/%postname%/');
        $wp_rewrite->flush_rules();
    }
    private static function disable_yearmonth_folders(){
        update_option('uploads_use_yearmonth_folders', 0);
    }
    private static function wprocket_load_settings() {
        $settings_path = SH_PATH . "content/wp-rocket-settings.json";
        if (file_exists($settings_path)) {
            $settings_json = file_get_contents($settings_path);
            $settings_data = json_decode($settings_json, true);
            if (is_array($settings_data)) {
                foreach($settings_data as $key => $setting){
                    update_rocket_option( $key, $setting);
                }
                if (function_exists('rocket_generate_config_file')) {
                    rocket_generate_config_file();
                }
                error_log('WP Rocket ayarları başarıyla yüklendi!');
            } else {
                error_log('JSON dosyası çözülemedi. Lütfen dosyayı kontrol edin.');
            }
        } else {
            error_log('WP Rocket Ayar dosyası bulunamadı. Lütfen yolu kontrol edin.');
        }
        if (function_exists('rocket_regenerate_configuration')) {
            rocket_regenerate_configuration();
            error_log('WP Rocket yapılandırma dosyaları yeniden oluşturuldu!');
        }
    }
    public static function wph_load_settings() {
        $wph_settings = get_option("wph_settings");
        $settings_path = SH_PATH . "content/wph-settings.json";
        if ($wph_settings && file_exists($settings_path)) {
            $settings_json = file_get_contents($settings_path);
            $text_domain = basename(get_template_directory());
            $settings_json = str_replace("{text_domain}", $text_domain, $settings_json);
            $settings_data = json_decode($settings_json, true);
            if (is_array($settings_data)) {
                $wph_settings["module_settings"] = $settings_data;
                update_option("wph_settings", $wph_settings);
                error_log('WPH ayarları başarıyla yüklendi!');
            } else {
                error_log('JSON dosyası çözülemedi. Lütfen dosyayı kontrol edin.');
            }
        } else {
            error_log('WPH Ayar dosyası bulunamadı. Lütfen yolu kontrol edin.');
        }
    }






    private static function enqueue_update_script() {
        /*wp_enqueue_script(
            'theme-update-script',
            get_template_directory_uri() . '/vendor/salthareket/theme/src/js/update.js',
            ['jquery'],
            '1.0',
            true
        );

        $args = [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('update_theme_nonce')
        ];
        $args["status"] = self::$status;
        if (self::$status === 'pending') {
            $args["tasks"] = self::$installation_tasks;
        }else{
            $args["tasks"] = self::$update_tasks;
        }
        wp_localize_script('theme-update-script', 'updateAjax', $args);
        */
        $update_js_path = get_template_directory() . '/vendor/salthareket/theme/src/js/update.js';

        if (file_exists($update_js_path)) {
            echo '<script>';
            readfile($update_js_path);
            echo '</script>';
        } else {
            echo '<script>console.error("update.js not found.");</script>';
        }

        // wp_localize_script karşılığı olarak verileri göm
        ?>
        <script>
            const updateAjax = {
                ajax_url: "<?= esc_url(admin_url('admin-ajax.php')) ?>",
                nonce: "<?= esc_js(wp_create_nonce('update_theme_nonce')) ?>",
                status: "<?= esc_js(self::$status) ?>",
                tasks: <?= json_encode(self::$status === 'pending' ? self::$installation_tasks : self::$update_tasks) ?>
            };
        </script>
        <?php
     }
}