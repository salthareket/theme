<?php

/**
 * Page Assets Extractor — Ana Sınıf (Facade)
 *
 * CSS/JS asset extraction, caching ve optimizasyon sistemi.
 * Trait tabanlı modüler mimari — her sorumluluk ayrı dosyada.
 *
 * @package    SaltHareket\Theme\PageAssetsExtractor
 * @version    2.0.0
 * @since      1.0.0
 * @author     SaltHareket
 *
 * @changelog
 *   2.0.1 - 2026-05-18
 *     - Fix: REST API (Gutenberg) save desteği — variables.php'de rest_api_init hook'unda lazy load
 *     - Fix: asset-manager/bootstrap.php — REST_REQUEST koşulu eklendi (RemoveUnusedCss yükleniyor)
 *     - Add: on_save_post() — CSS dosyası fiziksel olarak yoksa force_rebuild=true
 *   2.0.0 - 2026-05-03
 *     - Refactor: 5068 satırlık tek dosya → trait tabanlı modüler yapı
 *       - Concerns/FetchesPages.php    — HTTP fetch, auth, dual fetch, lock
 *       - Concerns/ManagesMeta.php     — post/term/user/comment meta CRUD
 *       - Concerns/ManagesCache.php    — manifest, bundle, orphan cleanup
 *       - Admin/PageAssetsAdmin.php    — admin UI, AJAX handler'lar
 *       - Cron/CleanupTask.php         — cron, orphan temizliği
 *     - Add: CODING_PRINCIPLES uyumlu dokümantasyon
 *   1.9.6 - 2026-04-30
 *     - Add: Dual fetch (logged + unlogged HTML merge)
 *     - Add: PAE admin sayfası theme-settings altına submenu
 *   1.9.0 - 2026-04-21
 *     - Add: WooCommerce sayfaları ayrı tablo bölümü
 *   1.0.0 - İlk stabil versiyon
 *
 * HOW TO USE:
 *   Singleton pattern — new ile oluşturma, get_instance() kullan.
 *   Dosya include edildiğinde otomatik başlar (dosya sonundaki get_instance() çağrısı).
 *
 * @example Singleton al:
 *   $extractor = PageAssetsExtractor::get_instance();
 *
 * @example Post fetch:
 *   $extractor->fetch_post_url(123);
 *
 * @example Term fetch:
 *   $extractor->fetch_term_url(45, 'product_cat');
 *
 * @example Güvenli asset okuma (tema):
 *   $assets = PageAssetsExtractor::get_post_assets_safe($post_id);
 *
 * @example Debug modu:
 *   $extractor = PageAssetsExtractor::get_instance(true);
 *
 * @example Dual fetch kontrol:
 *   $extractor->is_dual_fetch(8428); // checkout page
 */

use MatthiasMullie\Minify;
use voku\helper\HtmlDomParser;
use Irmmr\RTLCss\Parser as RTLParser;

// ── Trait'leri yükle ──────────────────────────────────────────────────────────
require_once __DIR__ . '/Concerns/FetchesPages.php';
require_once __DIR__ . '/Concerns/ManagesMeta.php';
require_once __DIR__ . '/Concerns/ManagesCache.php';
require_once __DIR__ . '/Admin/PageAssetsAdmin.php';
require_once __DIR__ . '/Cron/CleanupTask.php';
require_once __DIR__ . '/Concerns/HandlesLanguage.php';
require_once __DIR__ . '/Concerns/HandlesGrouped.php';
require_once __DIR__ . '/Concerns/HandlesTwig.php';
require_once __DIR__ . '/Concerns/ExtractsAssets.php';

class PageAssetsExtractor {

    // ── Trait'ler ─────────────────────────────────────────────────────────────
    use FetchesPages;
    use ManagesMeta;
    use ManagesCache;
    use PageAssetsAdmin;
    use CleanupTask;
    use HandlesLanguage;
    use HandlesGrouped;
    use HandlesTwig;
    use ExtractsAssets;

    // =========================================================
    //  SABİTLER
    // =========================================================
    const ASSETS_STRUCTURE = [
        'js'             => [],
        'css'            => [],
        'css_page'       => '',
        'css_page_rtl'   => '',
        'plugins'        => [],
        'plugin_js'      => '',
        'plugin_css'     => '',
        'plugin_css_rtl' => '',
        'wp_js'          => '',
        'structure_fp'   => '',
        'meta'           => ['type' => '', 'id' => 0],
        'lcp'            => ['desktop' => [], 'mobile' => []],
    ];

    const META_KEY           = 'assets';
    const HTML_HASH_META_KEY = '_page_assets_last_html_hash';

    // =========================================================
    //  PROPERTIES
    // =========================================================

    /** @var PageAssetsExtractor|null Singleton instance */
    private static ?PageAssetsExtractor $instance = null;

    public array $excluded_post_types = [];
    public array $excluded_taxonomies = [];

    public array $technical_post_types = [
        'acf-field-group', 'acf-field', 'acf-ui-options-page',
        'acf-post-type', 'acf-taxonomy', 'revision', 'nav_menu_item',
        'custom_css', 'customize_changeset', 'attachment',
    ];

    public array $technical_taxonomies = [
        'link_category', 'nav_menu', 'post_format', 'language',
        'term_language', 'post_translations', 'term_translations',
        'acf-field-group-category', 'wp_pattern_category',
    ];

    // Genel durum
    public ?string $type         = null;
    public bool    $mass         = false;
    public int     $mass_index   = 0;
    public int     $mass_total   = 0;
    public bool    $disable_hooks = false;
    public bool    $force_rebuild = false;
    public bool    $grouped_fetch = false;
    public bool    $debug         = true;

    // Fetch state
    public string $home_url         = '';
    public string $home_url_encoded = '';
    public string $upload_url       = '';
    public string $upload_url_encoded = '';
    public        $url;
    public        $html;
    public string $source_css = '';
    public array  $auth_cookies = [];

    protected string $structure_fp = '';
    protected        $upload_dir   = '';

    // Manifest
    protected string $manifest_path = '';
    protected array  $manifest = [
        'version'       => 2,
        'global'        => [],
        'templates'     => [],
        'plugins'       => [],
        'content_usage' => [],
        'last_css_mtime' => 0,
    ];

    // Twig
    private string $twig_attr              = 'data-template';
    private array  $twig_template_paths    = [];
    private bool   $twig_scan_includes     = true;
    private array  $twig_seen_templates    = [];
    private array  $twig_locate_cache      = [];
    private array  $twig_approx_cache      = [];
    private bool   $twig_paths_initialized = false;
    private array  $twig_options           = [];

    // Static cache
    private static ?array $cached_public_post_types = null;
    private static ?array $cached_public_taxonomies = null;

    // =========================================================
    //  SINGLETON
    // =========================================================

    /**
     * Singleton instance döndürür.
     *
     * @param  bool $debug Debug modu
     * @return static
     *
     * @example
     *   $extractor = PageAssetsExtractor::get_instance();
     *   $extractor = PageAssetsExtractor::get_instance(true); // debug açık
     */
    public static function get_instance(bool $debug = false): static {
        if (null === self::$instance) {
            self::$instance = new self($debug);
        } elseif ($debug && !self::$instance->debug) {
            self::$instance->set_debug(true);
        }
        return self::$instance;
    }

    private function __clone() {}
    public function __wakeup() {}

    // =========================================================
    //  CONSTRUCTOR
    // =========================================================

    public function __construct(bool $debug = false) {
        if ($debug) $this->debug = true;
        $this->error_log('Initialized.', 'init');

        $this->source_css = STATIC_PATH . 'css/main-combined.css';

        $this->home_url         = function_exists('home_url') ? rtrim(home_url('/'), '/') . '/' : '/';
        $this->home_url_encoded = str_replace('/', '\/', $this->home_url);

        $upload_dir               = function_exists('wp_upload_dir') ? wp_upload_dir() : ['baseurl' => '/uploads'];
        $this->upload_dir         = $upload_dir;
        $upload_url               = rtrim($upload_dir['baseurl'] ?? '/uploads', '/') . '/';
        $this->upload_url         = $upload_url;
        $this->upload_url_encoded = str_replace('/', '\/', $this->upload_url);

        $cache_root = rtrim(defined('STATIC_PATH') ? STATIC_PATH : __DIR__ . '/', '/') . '/cache-manifest/';
        if (!is_dir($cache_root)) wp_mkdir_p($cache_root);
        $this->manifest_path = $cache_root . 'assets-manifest.json';
        $this->manifest_read();

        // CSS mtime kontrolü
        $css_mtime = file_exists($this->source_css) ? filemtime($this->source_css) : 0;
        if (!isset($this->manifest['last_css_mtime']) || $this->manifest['last_css_mtime'] !== $css_mtime) {
            $this->force_rebuild                  = true;
            $this->manifest['last_css_mtime']     = $css_mtime;
            $this->manifest_write();
        }

        // Excluded types
        $ex_archives = (array) get_option('options_exclude_post_types_from_cache', []);
        $ex_singles  = (array) get_option('options_exclude_posts_from_cache', []);
        $this->excluded_post_types = array_unique(array_merge($ex_archives, $ex_singles));
        foreach ($this->technical_post_types as $type) {
            if (!in_array($type, $this->excluded_post_types, true)) {
                $this->excluded_post_types[] = $type;
            }
        }

        $this->excluded_taxonomies = (array) get_option('options_exclude_taxonomies_from_cache', []);
        foreach ($this->technical_taxonomies as $tax) {
            if (!in_array($tax, $this->excluded_taxonomies, true)) {
                $this->excluded_taxonomies[] = $tax;
            }
        }

        $this->pae_lang_list();

        // AJAX hook'ları
        add_action('wp_ajax_page_assets_update',      [$this, 'page_assets_update']);
        add_action('wp_ajax_pae_clear_cache',         [$this, 'pae_clear_cache_ajax']);
        add_action('wp_ajax_pae_toggle_dual_fetch',   [$this, 'pae_toggle_dual_fetch_ajax']);

        // Admin menü
        add_action('admin_menu', [$this, 'register_admin_page']);

        // Admin notice gizle
        add_action('admin_head', function() {
            if (($_GET['page'] ?? '') === 'page-assets-update') {
                echo '<style>.notice:not(.pae-inline-notice),.updated:not(.pae-inline-notice),.error:not(.pae-inline-notice){display:none!important;}</style>';
            }
        });

        // Cron
        add_action('wp',                       [__CLASS__, 'schedule_cleanup_event']);
        add_action('my_daily_assets_cleanup',  [__CLASS__, 'run_cleanup_task']);
        add_action('wp_ajax_pae_test_cleanup', [__CLASS__, 'ajax_test_cleanup']);

        // Save hooks
        add_action('acf/render_field/name=page_assets', [$this, 'update_page_assets_message_field']);
    }

    // =========================================================
    //  DEBUG
    // =========================================================

    /**
     * Debug log yazar.
     *
     * @param  mixed  $msg
     * @param  string $context
     * @return void
     *
     * @example
     *   $this->error_log('Fetch başladı', 'fetch');
     */
    public function error_log($msg, string $context = ''): void {
        if (!$this->debug) return;
        $prefix = '[PAE]';
        if ($context) $prefix .= "[{$context}]";
        $msg = is_array($msg) || is_object($msg) ? print_r($msg, true) : $msg;
        error_log("{$prefix} {$msg}");
    }

    /**
     * Debug modunu aç/kapa.
     *
     * @param  bool $enabled
     * @return void
     *
     * @example
     *   $extractor->set_debug(true);
     */
    public function set_debug(bool $enabled): void {
        $this->debug = $enabled;
    }

    // =========================================================
    //  HOOK AKIŞI
    // =========================================================

    /**
     * Post'un fetch'e uygun olup olmadığını kontrol eder.
     *
     * @param  int           $post_id
     * @param  \WP_Post|null $post
     * @return bool
     *
     * @example
     *   if ($extractor->is_fetch_available($post_id, $post)) {
     *       $extractor->fetch_post_url($post_id);
     *   }
     */
    public function is_fetch_available(int $post_id, $post = null): bool {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return false;
        if (function_exists('wp_is_post_revision') && wp_is_post_revision($post_id)) return false;

        $post = empty($post) && function_exists('get_post') ? get_post($post_id) : $post;
        if (!$post) return false;
        if ($post->post_status !== 'publish') return false;
        if (!$this->is_supported_post_type($post->post_type)) return false;
        if ($this->disable_hooks) return false;
        if ($this->is_post_type_excluded($post->post_type)) return false;

        return true;
    }

    public function on_save_post(int $post_id, \WP_Post $post, bool $update) {
        if (!$this->is_fetch_available($post_id, $post)) {
            if (class_exists('WP_Rocket_Manifest_Manager')) {
                WP_Rocket_Manifest_Manager::getInstance()->catch_post_for_shutdown($post_id);
            }
            return;
        }

        // Meta'daki CSS dosyası fiziksel olarak yoksa force_rebuild — cache temizlenmiş olabilir
        $existing_meta = $this->meta_get('post', $post_id);
        if ( ! empty( $existing_meta['css_page'] ) ) {
            $css_abs = rtrim( STATIC_PATH, '/' ) . '/' . ltrim( $existing_meta['css_page'], '/' );
            if ( ! file_exists( $css_abs ) ) {
                $this->force_rebuild = true;
            }
        }

        $this->type = 'post';
        $ok         = $this->fetch_post_url($post_id);

        if ($ok !== false && !empty($this->html)) {
            $this->check_and_handle_html_change($post_id, $this->html, 'post');
            if (is_array($ok) && isset($ok['css_hash'])) {
                $this->finalize_assets_and_cleanup($post_id, $ok);
            }
        }

        $this->fetch_and_save_archives_assets($post->post_type);
        return $ok;
    }

    public function on_save_term(int $term_id, int $tt_id, string $taxonomy) {
        if (!$this->is_supported_taxonomy($taxonomy)) return;
        if ($this->disable_hooks) return;
        if ($this->is_taxonomy_excluded($taxonomy)) return;

        // Meta'daki CSS dosyası fiziksel olarak yoksa force_rebuild
        $existing_meta = $this->meta_get('term', $term_id);
        if ( ! empty( $existing_meta['css_page'] ) ) {
            $css_abs = rtrim( STATIC_PATH, '/' ) . '/' . ltrim( $existing_meta['css_page'], '/' );
            if ( ! file_exists( $css_abs ) ) {
                $this->force_rebuild = true;
            }
        }

        $this->type = 'term';
        $ok         = $this->fetch_term_url($term_id, $taxonomy);

        if ($ok !== false && !empty($this->html)) {
            $this->check_and_handle_html_change($term_id, $this->html, 'term');
        }

        return $ok;
    }

    // =========================================================
    //  EXCLUDE KONTROL
    // =========================================================

    public function is_post_type_excluded(string $post_type): bool {
        if (empty($this->excluded_post_types)) {
            $options_excluded      = (array) get_option('options_exclude_post_types_from_cache', []);
            $this->excluded_post_types = array_unique(array_merge($this->technical_post_types, $options_excluded));
        }
        return in_array($post_type, $this->excluded_post_types, true);
    }

    public function is_taxonomy_excluded(string $taxonomy): bool {
        if (empty($this->excluded_taxonomies)) {
            $options_excluded      = (array) get_option('options_exclude_taxonomies_from_cache', []);
            $this->excluded_taxonomies = array_unique(array_merge($this->technical_taxonomies, $options_excluded));
        }
        return in_array($taxonomy, $this->excluded_taxonomies, true);
    }

    // =========================================================
    //  HTML HASH KONTROL
    // =========================================================

    protected function check_and_handle_html_change($id, $html, string $context = 'post'): void {
        $html_string       = preg_replace('/\s+/', ' ', (string) $html);
        $current_html_hash = md5($html_string);
        $last_html_hash    = ($context === 'post')
            ? get_post_meta($id, self::HTML_HASH_META_KEY, true)
            : get_term_meta($id, self::HTML_HASH_META_KEY, true);

        if ($current_html_hash !== $last_html_hash) {
            $this->force_rebuild = true;
            if ($context === 'post') {
                update_post_meta($id, self::HTML_HASH_META_KEY, $current_html_hash);
            } else {
                update_term_meta($id, self::HTML_HASH_META_KEY, $current_html_hash);
            }
            $content_key = $context . ':' . $id;
            if (isset($this->manifest['content_usage'][$content_key])) {
                unset($this->manifest['content_usage'][$content_key]);
            }
            $this->manifest_write();
        }
    }

    // =========================================================
    //  POST TYPE / TAXONOMY SUPPORT
    // =========================================================

    private function is_supported_post_type(string $post_type): bool {
        if (self::$cached_public_post_types === null) {
            self::$cached_public_post_types = function_exists('get_post_types')
                ? array_keys(get_post_types(['public' => true], 'names'))
                : [];
        }
        return in_array($post_type, self::$cached_public_post_types, true);
    }

    private function is_supported_taxonomy(string $taxonomy): bool {
        if (self::$cached_public_taxonomies === null) {
            self::$cached_public_taxonomies = function_exists('get_taxonomies')
                ? array_values(get_taxonomies(['public' => true]))
                : [];
        }
        return in_array($taxonomy, self::$cached_public_taxonomies, true);
    }

    // =========================================================
    //  YARDIMCILAR
    // =========================================================

    private function detect_post_type($id): string {
        if ($this->type !== 'post' || !function_exists('get_post')) return '';
        $p = @get_post($id);
        return $p ? $p->post_type : '';
    }

    private function has_assets_simple(string $type, $id): bool {
        if ($type === 'archive' || $type === 'dynamic') {
            $opt = function_exists('get_option') ? get_option($id . '_assets', null) : null;
            return is_array($opt) && $opt !== [];
        }
        $val = $this->meta_get($type, $id);
        return is_array($val) && $val !== [];
    }

    public function get_roles(): array {
        global $wp_roles;
        if (!isset($wp_roles)) $wp_roles = new \WP_Roles();
        return array_keys($wp_roles->roles);
    }

    

    // =========================================================
    //  CSS PURGE HELPERS
    // =========================================================

    private function remove_unused_css($html, string $input = '', string $output = '', array $whitelist = [], bool $critical_css = false) {
        if (empty($input)) {
            $input = @file_get_contents($this->source_css);
        }
        $remover = new \SaltHareket\AssetManager\RemoveUnusedCss($html, $input, $output, $whitelist, $critical_css);
        return $remover->process();
    }

    private function remove_unused_css_cached($html, string $input, array $whitelist): string {
        $key        = sha1($this->structure_fp . '|' . json_encode($whitelist));
        $cache_dir  = rtrim(STATIC_PATH, '/') . '/css/cache/';
        if (!is_dir($cache_dir)) wp_mkdir_p($cache_dir);
        $cache_file = $cache_dir . 'purge-' . $key . '.css';
        if (file_exists($cache_file) && !$this->force_rebuild) {
            return (string) @file_get_contents($cache_file);
        }
        $purged = $this->remove_unused_css($html, $input, '', $whitelist);
        $purged = str_replace('../', '../../', $purged);
        @file_put_contents($cache_file, $this->normalize_content($purged, 'css'));
        return $purged;
    }

    public function remove_purge_css(): void {
        $purge_cache_dir = rtrim(STATIC_PATH, '/') . '/css/cache/';
        if (is_dir($purge_cache_dir)) {
            foreach (glob($purge_cache_dir . 'purge-*.css') ?: [] as $file) {
                @unlink($file);
            }
        }
    }

    public function remove_critical_css(): void {
        $this->error_log('[PAE] remove_critical_css: akıllı temizlik kullanıldığı için atlandı.');
    }

    

}

// ── Otomatik başlat ───────────────────────────────────────────────────────────
PageAssetsExtractor::get_instance();
