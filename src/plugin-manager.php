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

    // Yönetim paneli menüsü ve scriptleri başlatmak için kullanılır
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_option_page']); // Yönetim menüsüne bir sayfa ekler
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']); // Script ve stilleri dahil eder
        add_action('wp_ajax_plugin_manager_process', [__CLASS__, 'process_plugin']); // AJAX işlemleri için callback
    }

    // Yönetim paneline bir seçenek sayfası ekler
    public static function add_option_page() {
        add_menu_page(
            'Plugin Yönetimi',
            'Plugin Yönetimi',
            'manage_options',
            'plugin-manager',
            [__CLASS__, 'render_option_page'], // Sayfa içeriğini oluşturur
            'dashicons-admin-plugins', // Menü simgesi
            80 // Menü sırası
        );
    }

    // Yönetim sayfası içeriğini oluşturur
    public static function render_option_page() {
        $required_plugins = $GLOBALS['plugins'] ?? []; // WordPress Repo'dan gelen pluginler
        $required_plugins_local = $GLOBALS['plugins_local'] ?? []; // Local ZIP pluginler

        ?>
        <div class="wrap">
            <h1>Plugin Yönetimi</h1>
            <table id="plugin-manager-table" class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>Plugin Adı</th>
                        <th>Durum</th>
                        <th>Versiyon</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($required_plugins as $plugin_slug): ?>
                        <?php $plugin_name = self::get_plugin_name($plugin_slug); ?>
                        <tr>
                            <td><?php echo esc_html($plugin_name); ?></td>
                            <td class="status">
                                <?php echo self::is_plugin_installed($plugin_slug) ? 'Installed' : 'Not Installed'; ?>
                            </td>
                            <td>N/A</td>
                            <td>
                                <?php if (self::is_plugin_installed($plugin_slug)): ?>
                                    <button class="button button-secondary" disabled>Installed</button>
                                <?php else: ?>
                                    <button class="button button-primary install-plugin" data-plugin-slug="<?php echo esc_attr($plugin_slug); ?>">Install</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php foreach ($required_plugins_local as $plugin_info): ?>
                        <?php 
                        $installed_version = self::get_installed_version($plugin_info['name']); // Yüklü sürüm bilgisi al
                        $status = self::is_plugin_installed($plugin_info['name']) ? 'Installed' : 'Not Installed'; // Plugin durumu
                        $update_available = self::is_version_outdated($plugin_info['v'], $plugin_info['name']); // Güncelleme durumu
                        ?>
                        <tr>
                            <td><?php echo esc_html($plugin_info['name']); ?></td>
                            <td class="status">
                                <?php echo $status; ?>
                            </td>
                            <td>
                                <?php echo $installed_version ? $installed_version . ' -> ' . $plugin_info['v'] : 'Not Installed'; ?>
                            </td>
                            <td>
                                <?php if ($update_available): ?>
                                    <button class="button button-warning update-plugin" data-plugin-file="<?php echo esc_attr($plugin_info['file']); ?>">Update</button>
                                <?php elseif ($status === 'Installed'): ?>
                                    <button class="button button-secondary" disabled>Installed</button>
                                <?php else: ?>
                                    <button class="button button-primary install-local-plugin" data-plugin-file="<?php echo esc_attr($plugin_info['file']); ?>">Install</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }


    // Scriptleri enqueue et
    public static function enqueue_scripts($hook) {
        if ($hook === 'toplevel_page_plugin-manager') {
            wp_enqueue_script(
                'plugin-manager-script',
                  get_template_directory_uri() . '/vendor/salthareket/theme/src/plugin-manager.js',
                ['jquery'],
                '1.0',
                true
            );

            wp_localize_script('plugin-manager-script', 'pluginManagerAjax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'plugins' => $GLOBALS["plugins"] ?? [], // WordPress repository pluginleri
                'plugins_local' => array_map(function ($plugin) {
                    $plugin['installed_version'] = self::get_installed_version($plugin['name']);
                    return $plugin;
                }, $GLOBALS["plugins_local"] ?? []),
            ]);

        }
    }

    // Plugin işlemini AJAX üzerinden yap
    public static function process_plugin() {
        $plugin_slug = $_POST['plugin_slug'] ?? ''; // WordPress repo için slug
        $plugin_file = $_POST['plugin_file'] ?? ''; // Local plugin için dosya adı
        $action = $_POST['action_type'] ?? ''; // İşlem türü (install/update)

        if (!$plugin_slug && !$plugin_file) {
            wp_send_json_error(['message' => 'Plugin bilgisi boş!']); // Hata mesajı döndür
        }

        if ($action === 'update' && $plugin_file) {
            self::remove_plugin($plugin_file); // Güncelleme için eski plugin kaldırılır
        }

        if ($plugin_file) {
            // Local plugin yükleme
            $plugin_info = array_filter($GLOBALS["plugins_local"], function ($plugin) use ($plugin_file) {
                return $plugin['file'] === $plugin_file; // Doğru dosyayı bul
            });

            if (empty($plugin_info)) {
                wp_send_json_error(['message' => 'Local plugin bilgisi bulunamadı.']);
            }

            $plugin_info = reset($plugin_info); // İlk öğeyi al
            $plugin_dir = __DIR__ . '/plugins'; // Local ZIP dizini
            self::install_local_plugin($plugin_dir, $plugin_info);
        } else {
            // WordPress repository plugin yükleme
            include_once ABSPATH . 'wp-admin/includes/file.php';
            include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

            WP_Filesystem();
            $skin = new Silent_Upgrader_Skin();
            $upgrader = new Plugin_Upgrader($skin);

            $plugin_url = 'https://downloads.wordpress.org/plugin/' . $plugin_slug . '.zip';
            $result = $upgrader->install($plugin_url);

            if (is_wp_error($result)) {
                wp_send_json_error(['message' => $result->get_error_message()]);
            }
        }

        // Plugin'i aktifleştir
        activate_plugin($plugin_slug);

        wp_send_json_success(['message' => $plugin_slug . ' başarıyla yüklendi ve aktifleştirildi.']);
    }

    private static function get_plugin_name($plugin_slug) {
        $parts = explode('/', $plugin_slug);
        return ucwords(str_replace(['-', '_'], ' ', $parts[0])); // Plugin adını temizle ve büyük harfe çevir
    }

    private static function get_installed_version($plugin_name) {
        if (!self::is_plugin_installed($plugin_name)) {
            return false; // Plugin yüklü değilse false döndür
        }
        $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_name, false, false);
        return $plugin_data['Version'] ?? false; // Sürümü döndür
    }


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

            if (!self::is_plugin_active($plugin_info['name'])) {
                self::activate_plugin($plugin_info['name']);
            }
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
            WP_Filesystem();
            $skin = new Silent_Upgrader_Skin(); // Özel skin'i kullan
            $upgrader = new Plugin_Upgrader($skin);
            $result = $upgrader->install($zip_file);

            if (is_wp_error($result)) {
                error_log("Plugin yüklenemedi: " . $plugin_info['name'] . " - " . $result->get_error_message());
            } else {
                error_log("Plugin başarıyla yüklendi: " . $plugin_info['name']);
            }
        } else {
            error_log("Plugin zip file not found: " . $zip_file);
        }
    }

    private static function install_plugin_from_wp_repo($plugin_slug) {
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
            activate_plugin($plugin_name);
            error_log("Plugin aktifleştirildi: " . $plugin_name);
        }
    }

    // Remove an outdated plugin
    private static function remove_plugin($plugin_name) {
        $plugin_path = WP_PLUGIN_DIR . '/' . dirname($plugin_name);

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
    private static function is_plugin_active($plugin_name) {
        return is_plugin_active($plugin_name);
    }
}


