<?php


if (!defined('ABSPATH')) {
    // WordPress kök yolunu yüklemek için wp-load.php dosyasını dahil et
    require_once dirname(__DIR__, 5) . '/wp-load.php';
}

// Gerekli WordPress dosyalarını dahil et
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

// Sessiz bir Upgrader Skin sınıfı, gereksiz geri bildirimleri devre dışı bırakır
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
        // Hataları log dosyasına yaz
        error_log(print_r($errors, true));
    }

    public function before() {
        // İşlem öncesi hazırlıklar
    }

    public function after() {
        // İşlem sonrası temizleme işlemleri
    }
}

class PluginManager {

    public static $plugin_dir =  __DIR__ . '/content/plugins';

    // Yönetim paneli menüsü ve scriptleri başlatmak için kullanılır
    public static function init() {
        //add_action('admin_menu', [__CLASS__, 'add_option_page']); // Yönetim menüsüne bir sayfa ekler
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']); // Script ve stilleri dahil eder
        add_action('wp_ajax_plugin_manager_process', [__CLASS__, 'process_plugin']); // AJAX işlemleri için callback
    }

    // Yönetim paneline bir seçenek sayfası ekler
    /*public static function add_option_page() {
        add_menu_page(
            'Plugin Yönetimi',
            'Plugin Yönetimi',
            'manage_options',
            'plugin-manager',
            [__CLASS__, 'render_option_page'], // Sayfa içeriğini oluşturur
            'dashicons-admin-plugins', // Menü simgesi
            80 // Menü sırası
        );
    }*/

    public static function render_option_page() {
        $required_plugins = $GLOBALS['plugins'] ?? [];
        $required_plugins_local = $GLOBALS['plugins_local'] ?? [];
        
        // Tüm pluginlerin verilerini işlemek için birleştirilmiş bir dizi oluşturuyoruz
        $plugins_data = [];
        $plugin_depencies = [];

        // Repo'dan yüklenen pluginler
        foreach ($required_plugins as $plugin) {
            $full_slug = self::get_full_slug($plugin['name']);
            $plugin_data = self::get_plugin_data($full_slug);

            $plugins_data[] = [
                'name' => $plugin_data['Name'] ?? self::get_plugin_name($full_slug),
                'slug' => $full_slug,
                'type' => $plugin['type'],
                'installed_version' => $plugin_data['Version'] ?? 'Not Installed',
                'current_version' => '', // Repo'dan gelenlerin current_version'ı boş
                'is_active' => self::is_plugin_active($full_slug),
                'is_installed' => self::is_plugin_installed($full_slug),
                'update_available' => false, // Repo için update kontrolü yapılmıyor
                'is_local' => false,
            ];
        }

        // Local pluginler
        foreach ($required_plugins_local as $plugin_info) {
            $full_slug = self::get_full_slug($plugin_info['name']);
            $plugin_data = self::get_plugin_data($full_slug);

            $installed_version = $plugin_data['Version'] ?? 'Not Installed';
            $current_version = $plugin_info['v'];
            $update_available = ($installed_version !== 'Not Installed' && $installed_version !== $current_version && version_compare($installed_version, $current_version, '<'));

            $plugins_data[] = [
                'name' => $plugin_data['Name'] ?? $plugin_info['file'],
                'slug' => $full_slug,
                'type' => $plugin_info['type'],
                'installed_version' => $installed_version,
                'current_version' => $current_version,
                'is_active' => self::is_plugin_active($full_slug),
                'is_installed' => self::is_plugin_installed($full_slug),
                'update_available' => $update_available,
                'is_local' => true,
            ];
        }

        // Kategorilere göre gruplama ve sıralama
        $grouped_plugins = [];
        foreach ($plugins_data as $plugin) {
            foreach ($plugin['type'] as $type) {
                $grouped_plugins[$type][] = $plugin;
            }
        }

        // Main'i al, diğerlerini alfabetik sıraya göre hazırla
        $main_plugins = $grouped_plugins['main'] ?? [];
        unset($grouped_plugins['main']);
        usort($main_plugins, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
        ksort($grouped_plugins); // Diğer kategorileri alfabetik sırala

        ?>
        <div class="wrap">
            <h1>Plugin Management</h1>
            <table id="plugin-manager-table" class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>Plugin Name</th>
                        <th>Version</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Main Kategorisi -->
                    <?php foreach ($main_plugins as $plugin): ?>
                        <?php self::render_plugin_row($plugin); ?>
                    <?php endforeach; ?>

                    <!-- Diğer Kategoriler -->
                    <?php foreach ($grouped_plugins as $category => $plugins): ?>
                        <tr>
                            <td colspan="3" style="font-weight: bold; background-color: #f1f1f1;">
                                <?php echo ucfirst($category); ?>
                            </td>
                        </tr>
                        <?php
                        usort($plugins, function ($a, $b) {
                            return strcmp($a['name'], $b['name']);
                        });
                        foreach ($plugins as $plugin): ?>
                            <?php self::render_plugin_row($plugin); ?>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    private static function render_plugin_row($plugin) {
        ?>
        <tr>
            <td><?php echo esc_html($plugin['name']); ?></td>
            <td>
                <?php
                if ($plugin['is_local'] && $plugin['update_available']) {
                    echo esc_html($plugin['installed_version'] . ' -> ' . $plugin['current_version']);
                } else {
                    echo esc_html($plugin['installed_version']);
                }
                ?>
            </td>
            <td>
                <?php if (!$plugin['is_installed']): ?>
                    <button class="button button-primary install-plugin" style="border:none;border-radius:6px;" data-plugin-slug="<?php echo esc_attr($plugin['slug']); ?>" data-local="<?php echo esc_attr($plugin['is_local'] ? 'true' : 'false'); ?>">Install</button>
                <?php elseif ($plugin['update_available']): ?>
                    <button class="button button-warning update-plugin" style="border:none;border-radius:6px;color: #111;background-color: orange;" data-plugin-slug="<?php echo esc_attr($plugin['slug']); ?>" data-local="true">Update</button>
                <?php elseif (!$plugin['is_active']): ?>
                    <button class="button button-success activate-plugin" style="border:none;border-radius:6px;color: #fff;background-color: green;font-weight:600;" data-plugin-slug="<?php echo esc_attr($plugin['slug']); ?>" data-local="<?php echo esc_attr($plugin['is_local'] ? 'true' : 'false'); ?>">Activate</button>
                <?php else: ?>
                    <button class="button button-danger deactivate-plugin" style="border:none;border-radius:6px;color:#fff;background-color:red;" data-plugin-slug="<?php echo esc_attr($plugin['slug']); ?>" data-local="<?php echo esc_attr($plugin['is_local'] ? 'true' : 'false'); ?>">Deactivate</button>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }
    private static function get_full_slug($plugin_slug) {
        if (strpos($plugin_slug, '/') === false) {
            // Eksik slug, tam slug oluştur
            return $plugin_slug . '/' . $plugin_slug . '.php';
        }
        return $plugin_slug;
    }




    // Scriptleri enqueue et
    public static function enqueue_scripts($hook) {
        if ($hook === 'theme-settings_page_plugin-manager') {
            wp_enqueue_script(
                'plugin-manager-script',
                  get_template_directory_uri() . '/vendor/salthareket/theme/src/js/plugin-manager.js',
                ['jquery'],
                '1.0',
                true
            );

            // Tüm plugin bilgilerini JS'ye aktar
            $plugins = [];
            foreach ($GLOBALS['plugins'] as $plugin) {
                $plugin_data = self::get_plugin_data($plugin["name"]);
                $plugins[] = [
                    'slug' => $plugin["name"],
                    'name' => $plugin_data['Name'] ?? self::get_plugin_name($plugin["name"]),
                    'version' => $plugin_data['Version'] ?? 'Not Installed',
                    'active' => self::is_plugin_active($plugin["name"]),
                    'installed' => self::is_plugin_installed($plugin["name"]),
                ];
            }

            $plugins_local = [];
            foreach ($GLOBALS['plugins_local'] as $plugin_info) {
                $plugin_data = self::get_plugin_data($plugin_info['name']);
                $plugins_local[] = [
                    'slug' => $plugin_info['file'],
                    'name' => $plugin_data['Name'] ?? $plugin_info['name'],
                    'version' => $plugin_data['Version'] ?? 'Not Installed',
                    'repo_version' => $plugin_info['v'],
                    'active' => self::is_plugin_active($plugin_info['name']),
                    'installed' => self::is_plugin_installed($plugin_info['name']),
                ];
            }

            wp_localize_script('plugin-manager-script', 'pluginManagerAjax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'plugins' => $plugins,
                'plugins_local' => $plugins_local,
            ]);

        }
    }

    // Plugin işlemini AJAX üzerinden yap
    public static function process_plugin() {

        ob_start(); // Tamponlamayı başlat

        try {
            $plugin_slug = $_POST['plugin_slug'] ?? '';
            $action_type = $_POST['action_type'] ?? '';
            $local = $_POST['local'];
            if (!$plugin_slug || !$action_type) {
                wp_send_json_error(['message' => 'Eksik parametreler!']);
            }

            // İşlem türüne göre ayrım
            if ($action_type === 'install' || $action_type === 'update') {
                if($local == "true"){
                    $required_plugins_local = $GLOBALS["plugins_local"] ?? [];
                    //$plugin_dir = __DIR__ . '/content/plugins';
                    $plugin_info = current(array_filter($required_plugins_local, fn($plugin) => $plugin['name'] === $plugin_slug));
                    self::remove_plugin($plugin_info['file']);
                    self::install_local_plugin($plugin_info);
                }else{
                    self::install_plugin_from_wp_repo($plugin_slug);
                }
            } elseif ($action_type === 'activate') {
                activate_plugin($plugin_slug);
            } elseif ($action_type === 'deactivate') {
                deactivate_plugins($plugin_slug);
            } else {
                wp_send_json_error(['message' => 'Bilinmeyen işlem türü!']);
            }

            // İşlem sonrası başarılı mesaj
            $response_message = match ($action_type) {
                'install' => $plugin_slug . ' başarıyla yüklendi.',
                'activate' => $plugin_slug . ' başarıyla aktifleştirildi.',
                'deactivate' => $plugin_slug . ' başarıyla devre dışı bırakıldı.',
                'update' => $plugin_slug . ' başarıyla güncellendi.',
                default => 'İşlem tamamlandı.'
            };

            ob_end_clean(); // Tüm tamponu temizle
            wp_send_json_success(['message' => $response_message]);
        } catch (Exception $e) {
            ob_end_clean(); // Tamponu temizle
            wp_send_json_error(['message' => 'Hata oluştu: ' . $e->getMessage()]);
        }

        ob_end_clean(); // Her durumda tamponu temizle
    }

    private static function get_plugin_name($plugin_slug) {
        $parts = explode('/', $plugin_slug);
        return ucwords(str_replace(['-', '_'], ' ', $parts[0])); // Plugin adını temizle ve büyük harfe çevir
    }

    private static function get_plugin_data($plugin_slug) {
        $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_slug;

        if (!file_exists($plugin_path)) {
            error_log("Plugin path not found: " . $plugin_path);
            return ['Name' => null, 'Version' => null];
        }

        $plugin_data = get_plugin_data($plugin_path, false, false);

        // Debug için log yaz
        error_log("Plugin data: " . print_r($plugin_data, true));

        return $plugin_data;
    }


    private static function get_installed_version($plugin_name) {
        if (!self::is_plugin_installed($plugin_name)) {
            return false; // Plugin yüklü değilse false döndür
        }
        $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_name, false, false);
        return $plugin_data['Version'] ?? false; // Sürümü döndür
    }

    // Check and install required plugins from the $GLOBALS["plugins"] array
    public static function check_and_install_required_plugins($plugin_types) {
        $required_plugins = $GLOBALS["plugins"] ?? [];
        foreach ($required_plugins as $plugin) {
            // Plugin zaten yüklü mü?
            if (!self::is_plugin_installed($plugin["name"])) {
                // WordPress repository'den yükleme
                if (
                    (in_array("main", $plugin["type"]) || empty(array_diff($plugin["type"], $plugin_types)))
                ) {
                    self::install_plugin_from_wp_repo($plugin["name"]);
                    self::activate_plugin($plugin['name']);
                }
            }
        }
    }

    // Check and update local plugins from the $GLOBALS["plugins_local"] array
    public static function check_and_update_local_plugins($plugin_types) {
        $required_plugins_local = $GLOBALS["plugins_local"] ?? [];
        //$plugin_dir = __DIR__ . '/content/plugins';
        foreach ($required_plugins_local as $plugin) {
            $plugin_path = WP_PLUGIN_DIR . '/' . $plugin['name'];
            // Check if the plugin exists and if the version is outdated
            if (!file_exists($plugin_path) || self::is_version_outdated($plugin['v'], $plugin['name'])) {
                self::remove_plugin($plugin['file']);
                if(in_array("main", $plugin["type"]) || empty(array_diff($plugin["type"], $plugin_types))){
                    self::install_local_plugin($plugin);
                }
            }
            if (!self::is_plugin_active($plugin['name']) && (in_array("main", $plugin["type"]) || empty(array_diff($plugin["type"], $plugin_types)))) {
                self::activate_plugin($plugin['name']);
            }
        }
    }

    // Check if a plugin is installed
    public static function is_plugin_installed($plugin_name) {
        $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_name;
        if (file_exists($plugin_path)) {
            error_log("Plugin zaten yüklü: " . $plugin_name);
            return true;
        } else {
            error_log("Plugin yüklü değil: " . $plugin_name);
            return false;
        }
    }

    private static function install_plugin_depencies($plugin_info, $is_local = false){
        if($is_local){
            if($plugin_info){
                if(isset($plugin_info["depency"]) && !empty($plugin_info["depency"])){
                    $depency = $plugin_info["depency"];
                    $depency = array_values(array_filter($GLOBALS["plugins"], function($item) use ($depency){
                        return $item['name'] === $depency;
                    }));
                    $depency = $depency[0] ?? null;
                    if(!self::is_plugin_installed($depency["name"])){
                        self::install_plugin_from_wp_repo($plugin_info["depency"]);
                    }
                }
            }
        }else{
            $plugin_info = array_values(array_filter($GLOBALS["plugins"], function($item) use ($plugin_info){
                return $item['name'] === $plugin_info;
            }));
            $plugin_info = $plugin_info[0] ?? null;
            if(isset($plugin_info["depency"]) && !empty($plugin_info["depency"])){
                $depency = $plugin_info["depency"];
                $depency = array_values(array_filter($GLOBALS["plugins_local"], function($item) use ($depency) {
                    return $item['name'] === $depency;
                }));
                $depency = $depency[0] ?? null;
                if(!self::is_plugin_installed($depency["name"])){
                    self::install_local_plugin($depency);
                }
            }
        }
    }

    private static function install_local_plugin($plugin_info) {
        error_log("install_local_plugin");
        $zip_file = self::$plugin_dir . '/' . $plugin_info['file'] . '.zip';

        if (file_exists($zip_file)) {
            WP_Filesystem();
            $skin = new Silent_Upgrader_Skin(); // Özel skin'i kullan
            $upgrader = new Plugin_Upgrader($skin);
            $result = $upgrader->install($zip_file);

            if (is_wp_error($result)) {
                error_log("Plugin yüklenemedi: " . $plugin_info['name'] . " - " . $result->get_error_message());
            } else {
                error_log("Plugin başarıyla yüklendi: " . $plugin_info['name']);
                self::install_plugin_depencies($plugin_info, true);
            }
        } else {
            error_log("Plugin zip file not found: " . $zip_file);
        }
    }

    private static function install_plugin_from_wp_repo($plugin_slug) {
        error_log("install_plugin_from_wp_repo");
        include_once ABSPATH . 'wp-admin/includes/file.php';
        include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        WP_Filesystem();
        $skin = new Silent_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader($skin);

        // Slug'ı temizle
        $slug_parts = explode('/', $plugin_slug);
        $clean_slug = $slug_parts[0]; // İlk kısmı al

        $plugin_url = 'https://downloads.wordpress.org/plugin/' . $clean_slug . '.zip';
        error_log("Downloading plugin from: " . $plugin_url);

        $result = $upgrader->install($plugin_url);

        if (is_wp_error($result)) {
            error_log("Plugin yüklenemedi: " . $clean_slug . " - " . $result->get_error_message());
        } else {
            // Plugin dizinini kontrol et
            $plugin_path = WP_PLUGIN_DIR . '/' . $clean_slug;
            if (!file_exists($plugin_path)) {
                error_log("Plugin yüklendi ancak doğru yere taşınamadı: " . $plugin_path);
            } else {
                error_log("Plugin başarıyla yüklendi: " . $plugin_path);
            }
            self::install_plugin_depencies($plugin_slug);
        }
    }

    // Activate a plugin
    private static function activate_plugin($plugin_name) {
        if (!is_plugin_active($plugin_name)) {
            activate_plugin($plugin_name);
            error_log("Plugin aktifleştirildi: " . $plugin_name);
        }
    }

    private static function deactivate_plugin($plugin_slug) {
        deactivate_plugins($plugin_slug);

        // Deaktif işlem sonrası kontrol
        if (!is_plugin_active($plugin_slug)) {
            error_log("Plugin başarıyla deaktif edildi: " . $plugin_slug);
        } else {
            error_log("Plugin deaktif edilemedi: " . $plugin_slug);
        }
    }


    // Remove an outdated plugin
    private static function remove_plugin($plugin_name) {
        if(empty($plugin_name)){
            return;
        }
        $plugin_path = WP_PLUGIN_DIR . '/' . ($plugin_name);
        error_log($plugin_path);
        if (file_exists($plugin_path)) {
            self::delete_directory($plugin_path);
            error_log("Plugin kaldırıldı: " . $plugin_name);
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

    // Check if a plugin is active
    private static function is_plugin_active($plugin_slug) {
        // Plugin slug'dan aktiflik durumu kontrol edilir
        return is_plugin_active($plugin_slug);
    }
}


