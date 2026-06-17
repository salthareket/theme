<?php
/**
 * Theme Styles New - Main Class
 *
 * WordPress admin arayüzü üzerinden tema stillerini yönetmeyi sağlayan
 * ana singleton class. Modül yönetimi, asset enqueue ve veri kayıt/yükleme
 * işlemlerini koordine eder.
 *
 * @package SaltHareket\Theme\ThemeStyles
 * @version 2.5.0
 * @author  SaltHareket
 * @since   1.0.0
 *
 * CHANGELOG:
 * 2.5.0 - 2026-06-17
 * - Change: theme-styles-new → theme-styles (rename). THEME_STYLES_NEW_* → THEME_STYLES_*
 * - Change: class adı Theme_Styles_New → Theme_Styles
 * - Add: save_data() sonunda scss-variables.json yazılır (SCSSCompiler için)
 * - Add: save_data() theme_styles_save_colors() çağrısı
 * - Change: WP option key'leri: theme_styles_new_* → theme_styles_*
 *
 * 2.4.0 - 2026-04-28
 * - save_data() → header themes CSS üretimi eklendi (save_theme_styles_header_themes entegrasyonu)
 * - CSS generator'a set_header_themes_css() metodu eklendi
 *
 * 2.3.0 - 2026-04-27
 * - save_data() preset_name parametresi eklendi
 * - activePreset option tracking eklendi
 * - Offcanvas ve Footer JS enqueue eklendi
 *
 * 2.2.0 - 2026-04-25
 * - Sticky toolbar için themeStyles.activePreset localize edildi
 * - Logo data (wp_logo, acf_logo, acf_logo_affix) eklendi
 *
 * 2.1.0 - 2026-04-23
 * - Header, Pagination, Breadcrumb, Buttons, Background modül JS'leri eklendi
 * - breakpointWidths localize edildi
 *
 * 2.0.0 - 2026-04-20
 * - Modüler yapıya geçildi
 * - CSS Generator entegre edildi
 * - Preset sistemi eklendi
 *
 * 1.0.0 - 2026-04-15
 * - Initial release
 *
 * HOW TO USE:
 * Bu class singleton pattern kullanır. Direkt instantiate etme.
 *
 * BASIC USAGE:
 * - Instance al : Theme_Styles::init()
 * - Data oku    : Theme_Styles::init()->get_data()
 * - Data kaydet : Theme_Styles::init()->save_data($data, 'preset-name')
 * - Modüller    : Theme_Styles::get_modules()
 *
 * @example Instance alma:
 * $ts = Theme_Styles::init();
 *
 * @example Mevcut data okuma:
 * $data = Theme_Styles::init()->get_data();
 * $primary_color = $data['colors']['primary'] ?? '#000';
 *
 * @example Data kaydetme (preset adıyla):
 * Theme_Styles::init()->save_data($data, 'my-preset');
 *
 * @example Data kaydetme (preset adı olmadan - default):
 * Theme_Styles::init()->save_data($data);
 *
 * @example Twig'de kullanım (frontend helpers üzerinden):
 * {{ theme_styles('colors.primary') }}
 */

if (!defined('ABSPATH')) exit;

class Theme_Styles {
    
    private static $instance = null;
    private static $data = null;
    private static $modules = [];
    
    /**
     * Initialize
     */
    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Initialize module manager
        Theme_Styles_Module_Manager::init();
        
        // Hooks
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        // theme-styles.css frontend'e enqueue edilmiyor — root.css yeterli
        // add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
    }
    
    /**
     * Get registered modules
     */
    public static function get_modules() {
        return Theme_Styles_Module_Manager::get_all();
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'theme-settings',
            __('🎨 Tema Stilleri', 'theme-styles'),
            __('🎨 Tema Stilleri', 'theme-styles'),
            'manage_options',
            'theme-styles',
            [$this, 'render_admin_page']
        );
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        include THEME_STYLES_PATH . '/admin/templates/main.php';
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'theme-settings_page_theme-styles') {
            return;
        }
        
        // CSS
        wp_enqueue_style(
            'theme-styles-admin',
            THEME_STYLES_URL . '/admin/assets/css/admin.css',
            [],
            THEME_STYLES_VERSION
        );
        
        // JS
        wp_enqueue_script(
            'theme-styles-admin',
            THEME_STYLES_URL . '/admin/assets/js/admin.js',
            ['jquery'],
            THEME_STYLES_VERSION,
            true
        );
        
        // Localize breakpoints from PHP
        wp_localize_script('theme-styles-admin', 'themeStyles', [
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce('theme_styles_nonce'),
            'modules'     => self::get_modules(),
            'data'        => $this->get_data(),
            'breakpoints' => array_keys(THEME_STYLES_BREAKPOINTS),
            'activePreset' => get_option( 'theme_styles_active_preset', '' ),
            'logos'       => [
                'site'        => get_site_icon_url(64) ?: '',
                'wp_logo'     => has_custom_logo() ? wp_get_attachment_image_url(get_theme_mod('custom_logo'), 'full') : '',
                'acf_logo'    => function_exists('get_field') ? get_field('logo', 'options') : '',
                'acf_logo_affix' => function_exists('get_field') ? get_field('logo_affix', 'options') : '',
            ],
            'breakpointWidths' => THEME_STYLES_BREAKPOINTS,
        ]);
        
        // Color picker
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        
        // jQuery UI Sortable for drag-drop
        wp_enqueue_script('jquery-ui-sortable');
        
        // WordPress components for gradient picker
        wp_enqueue_style('wp-components');
        wp_enqueue_script('wp-element');
        wp_enqueue_script('wp-components');
        wp_enqueue_script('wp-i18n');
        wp_enqueue_script('wp-compose');
        wp_enqueue_script('wp-primitives');
        
        // Colors module JS
        wp_enqueue_script(
            'theme-styles-colors',
            THEME_STYLES_URL . '/modules/colors/colors.js',
            ['jquery', 'jquery-ui-sortable', 'wp-element', 'wp-components', 'theme-styles-admin'],
            THEME_STYLES_VERSION,
            true
        );
        
        // Typography module CSS & JS
        wp_enqueue_style(
            'theme-styles-typography',
            THEME_STYLES_URL . '/modules/typography/typography.css',
            ['theme-styles-admin'],
            THEME_STYLES_VERSION
        );
        
        wp_enqueue_script(
            'theme-styles-typography',
            THEME_STYLES_URL . '/modules/typography/typography.js',
            ['jquery', 'theme-styles-admin'],
            THEME_STYLES_VERSION,
            true
        );
        
        // Header module JS
        wp_enqueue_script(
            'theme-styles-header',
            THEME_STYLES_URL . '/modules/header/header.js',
            ['jquery', 'theme-styles-admin'],
            THEME_STYLES_VERSION,
            true
        );
        
        // Pagination module CSS & JS
        wp_enqueue_style(
            'theme-styles-pagination',
            THEME_STYLES_URL . '/modules/pagination/pagination.css',
            ['theme-styles-admin'],
            THEME_STYLES_VERSION
        );
        
        wp_enqueue_script(
            'theme-styles-pagination',
            THEME_STYLES_URL . '/modules/pagination/pagination.js',
            ['jquery', 'theme-styles-admin'],
            THEME_STYLES_VERSION,
            true
        );
        
        // Breadcrumb module CSS & JS
        wp_enqueue_style(
            'theme-styles-breadcrumb',
            THEME_STYLES_URL . '/modules/breadcrumb/breadcrumb.css',
            ['theme-styles-admin'],
            THEME_STYLES_VERSION
        );
        
        wp_enqueue_script(
            'theme-styles-breadcrumb',
            THEME_STYLES_URL . '/modules/breadcrumb/breadcrumb.js',
            ['jquery', 'theme-styles-admin'],
            THEME_STYLES_VERSION,
            true
        );
        
        // Buttons module CSS & JS
        wp_enqueue_style(
            'theme-styles-buttons',
            THEME_STYLES_URL . '/modules/buttons/buttons.css',
            ['theme-styles-admin'],
            THEME_STYLES_VERSION
        );
        
        wp_enqueue_script(
            'theme-styles-buttons',
            THEME_STYLES_URL . '/modules/buttons/buttons.js',
            ['jquery', 'jquery-ui-sortable', 'theme-styles-admin'],
            THEME_STYLES_VERSION,
            true
        );
        
        // Background/Body module CSS & JS
        wp_enqueue_style(
            'theme-styles-background',
            THEME_STYLES_URL . '/modules/background/background.css',
            ['theme-styles-admin'],
            THEME_STYLES_VERSION
        );
        
        wp_enqueue_script(
            'theme-styles-background',
            THEME_STYLES_URL . '/modules/background/background.js',
            ['jquery', 'wp-element', 'wp-components', 'theme-styles-admin'],
            THEME_STYLES_VERSION,
            true
        );
        
        // WP Media for image upload
        wp_enqueue_media();

        // Footer module JS
        wp_enqueue_script(
            'theme-styles-footer',
            THEME_STYLES_URL . '/modules/footer/footer.js',
            ['jquery', 'theme-styles-admin'],
            THEME_STYLES_VERSION,
            true
        );

        // Offcanvas module JS
        wp_enqueue_script(
            'theme-styles-offcanvas',
            THEME_STYLES_URL . '/modules/offcanvas/offcanvas.js',
            ['jquery', 'theme-styles-admin'],
            THEME_STYLES_VERSION,
            true
        );
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        $css_file = get_template_directory() . '/theme/static/css/theme-styles.css';
        
        if (file_exists($css_file)) {
            wp_enqueue_style(
                'theme-styles',
                get_template_directory_uri() . '/theme/static/css/theme-styles.css',
                [],
                filemtime($css_file)
            );
        }
    }
    
    /**
     * Get data
     */
    public function get_data() {
        if (self::$data !== null) {
            return self::$data;
        }
        
        // Try cache first
        $cached = get_transient('theme_styles_cache');
        if ($cached !== false) {
            self::$data = $cached;
            return self::$data;
        }
        
        // Try JSON file
        $json_file = get_template_directory() . '/theme/static/data/theme-styles/latest.json';
        if (file_exists($json_file)) {
            $data = json_decode(file_get_contents($json_file), true);
            if ($data) {
                self::$data = $data;
                set_transient('theme_styles_cache', $data, DAY_IN_SECONDS);
                return self::$data;
            }
        }
        
        // Try database
        $data = get_option('theme_styles_data');
        if ($data) {
            self::$data = $data;
            set_transient('theme_styles_cache', $data, DAY_IN_SECONDS);
            return self::$data;
        }
        
        // Default
        $default_file = THEME_STYLES_PATH . '/data/default.json';
        if (file_exists($default_file)) {
            $data = json_decode(file_get_contents($default_file), true);
            self::$data = $data;
            return self::$data;
        }
        
        return [];
    }
    
    /**
     * Save data
     */
    public function save_data($data, $preset_name = '') {
        // Save to database
        update_option('theme_styles_data', $data);

        // Track active preset
        $active = $preset_name !== '' ? $preset_name : get_option('theme_styles_active_preset', '');
        update_option('theme_styles_active_preset', $active);

        // Aktif preset varsa dosyasını da güncelle
        if ($active !== '') {
            Theme_Styles_Preset_Manager::save($active, $data);
        }
        
        // Save to JSON
        $json_dir = get_template_directory() . '/theme/static/data/theme-styles';
        if (!is_dir($json_dir)) {
            mkdir($json_dir, 0755, true);
        }
        
        $json_file = $json_dir . '/latest.json';
        file_put_contents($json_file, json_encode($data, JSON_PRETTY_PRINT));
        
        // Clear cache
        delete_transient('theme_styles_cache');
        self::$data = null;
        
        // Generate CSS
        $generator = new Theme_Styles_CSS_Generator();

        // Header themes CSS (eski sistem SCSS mixin ile üretir, root.css'e eklenir)
        if (function_exists('save_theme_styles_header_themes') && !empty($data['header'])) {
            // Yeni sistemde z_index (underscore), eski sistemde z-index (dash) - normalize et
            $header_for_themes = $data['header'];
            if (!empty($header_for_themes['themes'])) {
                foreach ($header_for_themes['themes'] as &$theme) {
                    if (isset($theme['z_index']) && !isset($theme['z-index'])) {
                        $theme['z-index'] = $theme['z_index'];
                    }
                }
                unset($theme);
            }
            $header_themes_css = save_theme_styles_header_themes($header_for_themes);
            if (!empty($header_themes_css)) {
                $generator->set_header_themes_css($header_themes_css);
            }
        }

        $generator->generate($data);

        // Save colors (colors.json, _colors.scss)
        if (function_exists('theme_styles_save_colors')) {
            theme_styles_save_colors($data);
        }

        // SCSS variables JSON — SCSSCompiler bu dosyadan okur
        // CSS Generator'dan üretilen flat variable map'i kaydet
        $scss_vars = $generator->get_scss_variables();
        if (!empty($scss_vars)) {
            $scss_dir = get_template_directory() . '/theme/static/data';
            if (!is_dir($scss_dir)) mkdir($scss_dir, 0755, true);
            file_put_contents(
                $scss_dir . '/theme-styles/scss-variables.json',
                json_encode($scss_vars, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
        }

        return true;
    }
}
