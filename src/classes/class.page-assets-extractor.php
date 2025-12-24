<?php
use MatthiasMullie\Minify;
use voku\helper\HtmlDomParser;
use Irmmr\RTLCss\Parser as RTLParser;

class PageAssetsExtractor
{
    /**
     * Sınıfın tekil örneğini (instance) tutar
     * @var PageAssetsExtractor|null
     */
    private static $instance = null;

    /* ======= Sabitler ======= */
    const META_KEY = 'assets';
    const HTML_HASH_META_KEY = '_page_assets_last_html_hash';

    public $excluded_post_types = [];
    public $excluded_taxonomies = [];

    public array $technical_post_types = [
        'acf-field-group',
        'acf-field', 
        'acf-ui-options-page', 
        'acf-post-type', 
        'acf-taxonomy',
        'revision',
        'nav_menu_item',
        'custom_css',
        'customize_changeset',
        'attachment',
        'template'
    ];
    public array $technical_taxonomies = [
        'link_category',
        'nav_menu',
        'post_format',
        'template-types',
        'language',
        'term_language',
        'post_translations',
        'term_translations',
        'acf-field-group-category',
        'wp_pattern_category'
    ];

    /* ======= Genel Durum ======= */
    public $type = null;                       // 'post' | 'term' | 'archive' | ...
    public $mass = false;
    public $disable_hooks = false;
    public $force_rebuild = false;

    public $home_url = "";
    public $home_url_encoded = "";
    public $upload_url = "";
    public $upload_url_encoded = "";
    public $url;
    public $html;

    public $source_css;

    protected $structure_fp = '';
    protected $upload_dir = '';

    /* ======= Manifest ======= */
    protected $manifest_path;
    protected $manifest = [
        'version'   => 1,
        'global'    => [],
        'templates' => [],  // key = structure_fp
        'plugins'   => []   // key = sha1(json_encode(plugins))
    ];

    private string $twig_attr = 'data-template';
    private array $twig_template_paths = [];   // Timber template paths (override edilebilir)
    private bool $twig_scan_includes = true;   // {% include %} yakala, rekürsif tara
    private array $twig_seen_templates = []; // 'magazalar/single-modal.twig' => true
    private array $twig_locate_cache   = []; // 'magazalar/single-modal.twig' => '/abs/path/...'
    private array $twig_approx_cache   = []; // '/abs/path/...' => '<div>...</div>'


    // lazy init için:
    private bool   $twig_paths_initialized = false;
    private array  $twig_options = []; // dışarıdan gelen opsiyonlar saklansın

    public function __construct() {
        error_log("PageAssetsExtractor initialized in admin.");

        $this->source_css = STATIC_PATH ."css/main-combined.css";

        $this->home_url = function_exists('home_url') ? rtrim(home_url("/"), '/') . '/' : "/";
        $this->home_url_encoded = str_replace("/","\/", $this->home_url);

        $upload_dir = function_exists('wp_upload_dir') ? wp_upload_dir() : ['baseurl' => '/uploads'];
        $this->upload_dir = $upload_dir;
        $upload_url = rtrim($upload_dir['baseurl'] ?? '/uploads', '/') . "/";
        $this->upload_url = $upload_url;
        $this->upload_url_encoded = str_replace("/","\/", $this->upload_url);

        $cache_root = rtrim(defined('STATIC_PATH') ? STATIC_PATH : __DIR__.'/', '/').'/cache-manifest/';
        if (!is_dir($cache_root)) { @mkdir($cache_root, 0755, true); }
        $this->manifest_path = $cache_root . 'assets-manifest.json';
        $this->manifest_read();

        // CSS güncelleme kontrolü
        $css_mtime = file_exists($this->source_css) ? filemtime($this->source_css) : 0;
        if (!isset($this->manifest['last_css_mtime']) || $this->manifest['last_css_mtime'] !== $css_mtime) {
            $this->force_rebuild = true;
            $this->manifest['last_css_mtime'] = $css_mtime;
            $this->manifest_write();
        }

        $this->excluded_post_types = (array) get_option('options_exclude_post_types_from_cache', []);
        foreach ($this->technical_post_types as $type) {
            if (!in_array($type, $this->excluded_post_types)) {
                $this->excluded_post_types[] = $type;
            }
        }

        $this->excluded_taxonomies = (array) get_option('options_exclude_taxonomies_from_cache', []);
        foreach ($this->technical_taxonomies as $tax) {
            if (!in_array($tax, $this->excluded_taxonomies)) {
                $this->excluded_taxonomies[] = $tax;
            }
        }

        add_action('acf/render_field/name=page_assets', [$this, 'update_page_assets_message_field']);
        add_action('wp_ajax_page_assets_update', [$this,'page_assets_update']);
        add_action('wp_ajax_nopriv_page_assets_update', [$this,'page_assets_update']);

        // 2. CRON GÖREVİ KANCALARI
        // Dün oluşturduğumuz statik metodları burası tetikleyecek.
        add_action('wp', [__CLASS__, 'schedule_cleanup_event']);
        add_action('my_daily_assets_cleanup', [__CLASS__, 'run_cleanup_task']);
    }

    /**
     * 2. Sınıfın tekil örneğini almak için ana metod.
     * Dışarıdan sadece bu metod çağrılabilir.
     *
     * @return PageAssetsExtractor
    */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * (Singleton) Klonlamayı engelle
     */
    private function __clone() {}

    /**
     * (Singleton) Unserialize etmeyi engelle
     */
    public function __wakeup() {}

    /* ===================== HOOK AKIŞI ===================== */

    public function on_save_post($post_id, $post, $update) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (function_exists('wp_is_post_revision') && wp_is_post_revision($post_id)) return;

        $post = empty($post) && function_exists('get_post') ? get_post($post_id) : $post;
        if (!$post) return;
        if ($post->post_status !== 'publish') return;
        if (!$this->is_supported_post_type($post->post_type)) return;
        if ($this->disable_hooks) return;

        if ($this->is_post_type_excluded($post_type)) {
            error_log("[PAE] Post {$post_id} excluded from cache generation.");
            return; // işlem yapma
        }

        error_log("Saved post : {$post_id} | type: " . $post->post_type);

        $this->type = "post";
        $ok = $this->fetch_post_url($post_id);

        if ($ok !== false && !empty($this->html)) {
            $this->check_and_handle_html_change(
                $post_id,
                $this->html,
                'post'
            );

            // <<< KRİTİK GÜNCELLEME BAŞLANGIÇ: Finalize Etme >>>
            if (is_array($ok) && isset($ok['css_hash'])) {
                $this->finalize_assets_and_cleanup($post_id, $ok);
            }
            // <<< GÜNCELLEME SONU >>>
        }

        // arşivleri güncelle
        $this->fetch_and_save_archives_assets($post->post_type);

        if ($ok !== false) {
            error_log('[PAE] on_save_post fallback build_related_assets call');
            $this->build_related_assets($post_id, $post->post_type);
        }

        return $ok;
    }

    public function on_save_term($term_id, $tt_id, $taxonomy) {
        error_log("on_save_term : {$term_id} | taxonomy: {$taxonomy}");
        if (!$this->is_supported_taxonomy($taxonomy)) return;
        if ($this->disable_hooks) return;

        if ($this->is_taxonomy_excluded($taxonomy)) {
            error_log("[PAE] Term {$term_id} excluded from cache generation.");
            return ; // işlem yapma
        }

        $this->type = "term";
        $ok = $this->fetch_term_url($term_id, $taxonomy);

        if ($ok !== false && !empty($this->html)) {
            $this->check_and_handle_html_change(
                $term_id,
                $this->html,
                'term'
            );
        }

        return $ok;
    }

    public function is_post_type_excluded($post_type) {
        if (empty($this->excluded_post_types)) {
            $options_excluded = (array) get_option('options_exclude_post_types_from_cache', []);
            $this->excluded_post_types = array_unique(array_merge($this->technical_post_types, $options_excluded));
        }
        return in_array($post_type, $this->excluded_post_types, true);
    }
    public function is_taxonomy_excluded($taxonomy) {
        if (empty($this->excluded_taxonomies)) {
            $options_excluded = (array) get_option('options_exclude_taxonomies_from_cache', []);
            $this->excluded_taxonomies = array_unique(array_merge($this->technical_taxonomies, $options_excluded));
        }
        return in_array($taxonomy, $this->excluded_taxonomies, true);
    }

    /* ===================== HTML HASH KONTROL ===================== */

    protected function check_and_handle_html_change($id, $html, $context = 'post') {
        $current_html_hash = md5($html);
        $last_html_hash = ($context === 'post')
            ? get_post_meta($id, self::HTML_HASH_META_KEY, true)
            : get_term_meta($id, self::HTML_HASH_META_KEY, true);

        if ($current_html_hash !== $last_html_hash) {
            error_log("[PAE] {$context} HTML değişmiş, manifest purge ediliyor...");
            $this->force_rebuild = true;

            if ($context === 'post') {
                update_post_meta($id, self::HTML_HASH_META_KEY, $current_html_hash);
            } else {
                update_term_meta($id, self::HTML_HASH_META_KEY, $current_html_hash);
            }

            $this->purge_page_assets_manifest();
            $this->manifest_write();
        } else {
            error_log("[PAE] {$context} HTML aynı, rebuild gerek yok.");
        }
    }


    /* ===================== FİLTRELER ===================== */

    private function is_supported_post_type($post_type) {
        $public_pts = function_exists('get_post_types') ? array_keys(get_post_types(['public' => true], 'names')) : [];
        return in_array($post_type, $public_pts, true);
    }
    private function is_supported_taxonomy($taxonomy) {
        $public_tax = function_exists('get_taxonomies') ? get_taxonomies(['public' => true]) : [];
        return in_array($taxonomy, $public_tax, true);
    }

    /* ===================== URL FETCH ===================== */

    public function fetch_post_url($post_id) {
        $url = function_exists('get_permalink') ? get_permalink($post_id) : '';
        $this->url = $url;
        error_log("fetch_post_url : ".json_encode($url));
        return $this->fetch($url, $post_id, 'post');
    }

    public function fetch_term_url($term_id, $taxonomy) {
        $term = function_exists('get_term') ? get_term($term_id, $taxonomy) : null;
        $url  = function_exists('get_term_link') ? get_term_link($term) : '';
        $this->url = $url;
        error_log("fetch_term_url : {$url}");
        if (!function_exists('is_wp_error') || !is_wp_error($url)) {
            return $this->fetch($url, $term_id, 'term');
        }
    }

    public function fetch($url, $id, $forceType = null) {

        // En başta: sadece site içi URL’leri fetch et
        $parsed_url = parse_url($url);
        $wp_domain = parse_url(home_url(), PHP_URL_HOST);
        if (($parsed_url['host'] ?? '') !== $wp_domain) {
            error_log('[PAE] fetch SKIP: domain not whitelisted (WP domain only): ' . ($parsed_url['host'] ?? ''));
            return false;
        }

        $prevType = $this->type;
        if ($forceType) $this->type = $forceType;

        error_log('[PAE] fetch ENTER type=' . $this->type . ' id=' . $id . ' url=' . $url);

        if ($this->acquire_lock($id) === false) {
            error_log("[PAE] fetch SKIP (lock) id={$id}");
            if ($forceType) $this->type = $prevType;
            return false;
        }

        try {
            $fetch_url = (!empty($url) && is_string($url))
                ? $url . (strpos($url, '?') === false ? '?fetch&nocache=true' : '&fetch&nocache=true')
                : '?fetch&nocache=true';

            error_log("[PAE] fetch URL=" . $fetch_url . " | id={$id} | type={$this->type}");

            if (function_exists('get_page_status')) {
                $st = @get_page_status($fetch_url);
                error_log('[PAE] get_page_status=' . $st . ' for ' . $fetch_url);
                if ($st != 200) { return false; }
            }

            $this->url = $fetch_url;

            $response = wp_remote_get($fetch_url, [
                'timeout' => 10,
                'headers' => [
                    'User-Agent' => 'MyFetchBot/1.0',
                    'X-Internal-Fetch' => '1',
                ],
                'httpversion' => '1.1',
            ]);

            if (is_wp_error($response)) {
                error_log('PAE wp_remote_get ERROR: ' . $response->get_error_message());
            } else {
                $html_raw = wp_remote_retrieve_body($response);
                error_log('PAE wp_remote_get BODY LEN: ' . strlen($html_raw));
                // Eğer HtmlDomParser parse edilecekse:
                $html_content = HtmlDomParser::str_get_html($html_raw);
            }

            if (!$html_content) {
                error_log('[PAE] file_get_html FAILED');
                return false;
            }

            $this->html = $html_content;

            // EXISTING: CSS/JS optimize et
            $result = $this->extract_assets($html_content, $id);
            error_log('[PAE] extract_assets DONE type=' . $this->type . ' id=' . $id);

            $tags_to_check = [
                'iframe' => ['src', 'data-src', 'data-lazy-src'],
                'img'    => ['src', 'data-src', 'data-lazy-src'],
                'script' => ['src'],
                'link'   => ['href'], // özellikle stylesheet için
                'video'  => ['src'],
                'audio'  => ['src']
            ];

            $new_domains = [];

            // Site domainini al
            $site_url = get_site_url();
            $parsed_site = parse_url($site_url);
            $site_domain = $parsed_site['host'] ?? '';

            // CSP direktif adı eşlemesi
            $directive_map = [
                'iframe' => 'frame-src',
                'img'    => 'img-src',
                'script' => 'script-src',
                'link'   => 'style-src', // link genelde stylesheet
                'video'  => 'media-src',
                'audio'  => 'media-src'
            ];

            foreach ($tags_to_check as $tag => $attributes) {
                foreach ($html_content->find($tag) as $element) {
                    foreach ($attributes as $attr) {
                        $url = $element->getAttribute($attr);
                        if ($url) {
                            $parsed = parse_url($url);
                            $domain = $parsed['host'] ?? null;
                            $scheme = $parsed['scheme'] ?? '';

                            // Filtre: localhost, private IP ve site domaini
                            if ($domain && in_array($scheme, ['http', 'https'])) {
                                if (preg_match('/^(localhost|127\.0\.0\.1|10\.\d{1,3}\.\d{1,3}\.\d{1,3}|192\.168\.\d{1,3}\.\d{1,3}|172\.(1[6-9]|2\d|3[0-1])\.\d{1,3}\.\d{1,3})$/', $domain)) {
                                    continue;
                                }
                                if ($domain === $site_domain) {
                                    continue;
                                }

                                // CSP direktif adıyla sakla
                                $directive = $directive_map[$tag] ?? null;
                                if ($directive) {
                                    if (!isset($new_domains[$directive])) {
                                        $new_domains[$directive] = [];
                                    }
                                    $new_domains[$directive][] = $domain;
                                    break; // ilk bulunan src ile devam et
                                }
                            }
                        }
                    }
                }
            }

            // DB’de sakla (direkt CSP direktif adıyla)
            if (!empty($new_domains)) {
                $approved_domains = get_option('csp_approved_domains', []);

                // Güvenlik: DB’den gelen değer array değilse array yap
                if (!is_array($approved_domains)) {
                    $approved_domains = [];
                }

                foreach ($new_domains as $directive => $domains) {
                    if (!isset($approved_domains[$directive]) || !is_array($approved_domains[$directive])) {
                        $approved_domains[$directive] = [];
                    }
                    $approved_domains[$directive] = array_unique(array_merge($approved_domains[$directive], $domains));
                }

                update_option('csp_approved_domains', $approved_domains);
            }


            if (is_numeric($id) && function_exists('get_post') && get_post($id)) {
                $this->type = 'post';
            }

            if ($this->type === 'post') {
                $this->build_related_assets($id, $this->detect_post_type($id));
                error_log('[PAE] build_related_assets CALLED');
            }

            return $result;

        } finally {
            $this->release_lock($id);
            error_log('[PAE] fetch EXIT id=' . $id);
            if ($forceType) $this->type = $prevType;
        }
    }

    public function fetch_all() {
        $urls = $this->get_all_urls();
        $results = [];
        foreach ($urls as $id => $row) {
            $results[$row["url"]] = $this->fetch($row["url"], $id, $row["type"]);
        }
        return $results;
    }

    public function fetch_urls($urls) {
        $results = [];
        foreach ($urls as $id => $row) {
            $results[$row["url"]] = $this->fetch($row["url"], $id, $row["type"]);
        }
        return $results;
    }

    /* ===================== CSS PURGE HELPERS ===================== */
    private function remove_unused_css($html, $input = "", $output = "", $whitelist = [], $critical_css = false){
        if(empty($input)){
            $input = @file_get_contents($this->source_css);
        }
        $remover = new RemoveUnusedCss($html, $input, $output, $whitelist, $critical_css);
        return $remover->process();
    }
    private function remove_unused_css_cached($html, $input, $whitelist) {
        $key = sha1($this->structure_fp . '|' . json_encode($whitelist));
        $cache_dir = rtrim(STATIC_PATH, '/').'/css/cache/';
        if (!is_dir($cache_dir)) { @mkdir($cache_dir, 0755, true); }
        $cache_file = $cache_dir . 'purge-' . $key . '.css';
        if (file_exists($cache_file) && !$this->force_rebuild) {
            return @file_get_contents($cache_file);
        }
        $purged = $this->remove_unused_css($html, $input, "", $whitelist);
        $purged = str_replace("../", "../../", $purged);
        @file_put_contents($cache_file, $this->normalize_content($purged, 'css'));
        return $purged;
    }
    // purge cache klasörünü tamamen temizle
    public function remove_purge_css(){
        $purge_cache_dir = rtrim(STATIC_PATH, '/').'/css/cache/';
        if(is_dir($purge_cache_dir)) {
            $files = glob($purge_cache_dir.'purge-*.css');
            foreach($files as $file) {
                @unlink($file);
            }
        }
    }
    public function remove_critical_css(){
        /*$critical_cache_dir = rtrim(STATIC_PATH, '/').'/css/cache/';
        if(is_dir($critical_cache_dir)) {
            // *-critical.css ile biten tüm dosyaları seç
            $files = glob($critical_cache_dir.'*-critical.css');
            foreach($files as $file) {
                @unlink($file);
            }
        }*/
        error_log("[PAE] remove_critical_css metodu çağrıldı. Akıllı temizlik kullanıldığı için bu işlem atlanmıştır.");
        return;
    }



    /* ===================== YARDIMCILAR ===================== */
    private function extract_class_list_from_html_string(string $html): array {
        $classes = [];
        if (preg_match_all('/class\s*=\s*(["\'])(.*?)\1/si', $html, $m)) {
            foreach ($m[2] as $chunk) {
                foreach (preg_split('/\s+/', trim($chunk)) as $c) {
                    if ($c !== '') { $classes[] = $c; }
                }
            }
        }
        if (count($classes) > 5000) { $classes = array_slice($classes, 0, 5000); }
        $classes = array_values(array_unique($classes));
        sort($classes);
        return $classes;
    }

    private function build_structure_fingerprint(array $parts): string {
        $norm = [];
        $norm['type']      = (string)($parts['type'] ?? '');
        $norm['post_type'] = (string)($parts['post_type'] ?? '');
        $norm['template']  = (string)($parts['template'] ?? '');
        $norm['dir']       = (string)($parts['dir'] ?? (function_exists('is_rtl') && is_rtl() ? 'rtl' : 'ltr'));

        $plugins = $parts['plugins'] ?? [];
        sort($plugins);                  $norm['plugins'] = $plugins;

        $wp_js = $parts['wp_js'] ?? [];
        sort($wp_js);                    $norm['wp_js'] = $wp_js;

        $wl = $parts['whitelist'] ?? [];
        sort($wl);                       $norm['whitelist'] = $wl;

        $classes = $parts['classes'] ?? [];
        $classes = array_values(array_unique(array_filter(array_map('trim', $classes))));
        sort($classes);                  $norm['classes'] = $classes;

        return sha1(json_encode($norm, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function normalize_content(string $content, string $type): string {
        $content = preg_replace_callback(
            '/console\.error\(\s*(["\'])(.*?)\1\s*\)/s',
            function($m) {
                $quote = $m[1];
                $text  = str_replace('//', '', $m[2]); // sadece // işaretini temizle
                return 'console.error(' . $quote . $text . $quote . ')';
            },
            $content
        );
        $normalized = $content;
        //$normalized = str_replace([$this->upload_url, $this->upload_url_encoded], '{upload_url}', $content);
        //$normalized = str_replace([$this->home_url, $this->home_url_encoded], '{home_url}', $normalized);
        $normalized = preg_replace("/\xEF\xBB\xBF/", '', $normalized);
        $normalized = str_replace("\r", "", $normalized);
        if ($type === 'css') {
            $normalized = preg_replace('!/\*.*?\*/!s', '', $normalized);
        } else {
            $normalized = preg_replace('~(^|\s)//[^\n]*~m', '$1', $normalized);
            $normalized = preg_replace('!/\*.*?\*/!s', '', $normalized);
        }
        $normalized = preg_replace("/[ \t]+/", " ", $normalized);
        $normalized = preg_replace("/\n+/", "\n", $normalized);
        return trim($normalized);
    }

    private function content_hash(string $content, string $type): string {
        return md5($this->normalize_content($content, $type));
    }

    private function manifest_read() {
        if (file_exists($this->manifest_path)) {
            // ... (Manifest okuma mantığı) ...
            $content = @file_get_contents($this->manifest_path);
            if ($content) {
                $data = json_decode($content, true);
                if (is_array($data)) {
                    $this->manifest = array_merge($this->manifest, $data);
                }
            }
        }
        // <<< KRİTİK GÜNCELLEME BAŞLANGIÇ: CSS Usage Counter Başlatma >>>
        if (!isset($this->manifest['css_usage']) || !is_array($this->manifest['css_usage'])) {
            $this->manifest['css_usage'] = []; // Format: Hash => [content_ids]
        }
        // <<< GÜNCELLEME SONU >>>
    }
    /*private function manifest_write() {
        // 1. Structure Fingerprint (yapı parmak izi) değerini al
        $structure_fp = $this->structure_fp; 

        if (!empty($structure_fp)) {
            
            // 2. Kritik CSS dosya yolunu (structure_fp tabanlı) hesapla
            $critical_css_relative_path = 'css/cache/' . $structure_fp . '-critical.css';

            // 3. Manifest'teki templates kaydına kritik CSS yolunu ekle
            // (Manifest'te ilgili $structure_fp kaydının zaten oluşturulmuş olduğunu varsayıyoruz)
            $this->manifest['templates'][$structure_fp]['critical_css'] = $critical_css_relative_path;
        }
        @file_put_contents($this->manifest_path, json_encode($this->manifest, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
    }*/
    private function manifest_write() {
        @file_put_contents($this->manifest_path, json_encode($this->manifest, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
    }

    private function acquire_lock($id) {
        $key = 'pae_lock_' . $id;
        if (function_exists('get_transient')) {
            if (get_transient($key)) return false;
            set_transient($key, 1, 60);
            return true;
        }
        $lockf = sys_get_temp_dir().'/'.$key.'.lock';
        if (file_exists($lockf)) return false;
        @file_put_contents($lockf, time());
        return true;
    }
    private function release_lock($id) {
        $key = 'pae_lock_' . $id;
        if (function_exists('delete_transient')) { delete_transient($key); return; }
        $lockf = sys_get_temp_dir().'/'.$key.'.lock';
        if (file_exists($lockf)) @unlink($lockf);
    }

    private function detect_post_type($id) {
        if ($this->type !== 'post' || !function_exists('get_post')) return '';
        $p = @get_post($id);
        return $p ? $p->post_type : '';
    }
    private function file_exists_rel(?string $rel): bool {
        if (!$rel) return false;
        $rel = ltrim($rel, '/');
        $abs = rtrim(STATIC_PATH, '/').'/' . $rel;
        return file_exists($abs) && is_file($abs);
    }

    // Sadece var/yok kontrolü (içerik kalitesi/anahtarlar umursanmaz)
    private function has_assets_simple(string $type, $id): bool {
        if ($type === 'archive') {
            $opt = function_exists('get_option') ? get_option($id . '_assets', null) : null;
            return is_array($opt) && $opt !== [];
        }
        $val = $this->meta_get($type, $id);
        return is_array($val) && $val !== [];
    }

    /* ====== DİL HELPER’LARI ====== */
    private function pae_lang_default(): string {
        return isset($GLOBALS['language_default']) ? (string)$GLOBALS['language_default'] : '';
    }

    private function pae_lang_list(): array {
        if (isset($GLOBALS['languages']) && is_array($GLOBALS['languages'])) {
            $names = array_column($GLOBALS['languages'], 'name');
            return array_values(array_filter(array_map('strval', $names)));
        }
        return $this->pae_lang_default() ? [$this->pae_lang_default()] : [];
    }

    /** URL’den dil çıkar (path’in her segmentinde ara) */
    private function pae_lang_from_url(string $url): string {
        $default = strtolower($this->pae_lang_default());
        $langs   = array_map('strtolower', $this->pae_lang_list());
        if (!$langs) return $default ?: '';

        $clean = strtok($url, '?#');
        $base  = rtrim(home_url('/'), '/');
        $path  = (stripos($clean, $base) === 0)
            ? ltrim(substr($clean, strlen($base)), '/')
            : ltrim((wp_parse_url($clean)['path'] ?? ''), '/');

        foreach (array_values(array_filter(explode('/', $path), 'strlen')) as $seg) {
            $seg = strtolower($seg);
            if (ctype_digit($seg)) continue;
            if (in_array($seg, $langs, true)) return $seg;
        }
        return $default ?: '';
    }

    private function pae_is_default_lang_url(string $url): bool {
        $def = strtolower($this->pae_lang_default());
        return $def && (strtolower($this->pae_lang_from_url($url)) === $def);
    }


    /* ===================== ÇEKİRDEK: ASSET ÇIKARMA ===================== */

    // ---- REQUIRED desteği (yalnızca plugin içi bağımlılık genişletme) ----
    private function expand_required_plugins(array $plugins, array $pluginMap): array {
        $queue = $plugins;
        $seen  = array_fill_keys($plugins, true);

        while (!empty($queue)) {
            $p = array_shift($queue);
            if (!isset($pluginMap[$p])) continue;

            if (!empty($pluginMap[$p]['required']) && is_array($pluginMap[$p]['required'])) {
                foreach ($pluginMap[$p]['required'] as $dep) {
                    $dep = trim((string)$dep);
                    if ($dep === '' || !isset($pluginMap[$dep])) {
                        if ($dep !== '') error_log("[PAE] WARN: required '{$dep}' tanımsız (source: {$p})");
                        continue;
                    }
                    if (!isset($seen[$dep])) {
                        $seen[$dep] = true;
                        $plugins[]  = $dep;
                        $queue[]    = $dep;
                    }
                }
            }
        }
        sort($plugins);
        return array_values(array_unique($plugins));
    }

    public function extract_assets($html_content, $id) {
        $js = [];
        $css = [];
        $css_page = "";
        $css_page_rtl = "";
        $plugins = [];
        $plugin_js = "";
        $plugin_css = "";
        $plugin_css_rtl = "";
        $wp_js = [];

        // data-template taraması
        $extra_html = $this->collectTwigLoadedHtml($html_content);
        if ($extra_html) {

            // Eklenen fragmenti logla
            error_log('[PAE] collected extra html length=' . strlen($extra_html));

            // NOT: innertext string kabul eder; doğrudan $extra_html kullanıyoruz
            $bodyNode = $html_content->find('main', 0);
            if ($bodyNode) {
                $bodyNode->innertext .= '<div id="__twig_extra__">' . $extra_html . '</div>';
                error_log('[PAE] extra html appended into <body>');
            } else {
                $html_content->innertext .= '<div id="__twig_extra__">' . $extra_html . '</div>';
                error_log('[PAE] extra html appended into root (no body)');
            }
        } else {
            error_log('[PAE] no extra twig html collected');
        }


        // ---------- DOM kırpma ----------
        $html_temp = HtmlDomParser::str_get_html($html_content->__toString());

        $header_node = $html_temp->findOne('#header');
        $header_content = '';
        if ($header_node) { 
            $header_content = $header_node->outerHtml(); 
            $header_node->delete(); 
        }

        /*$footer_node = $html_temp->findOne('#footer');
        $footer_content = '';
        if ($footer_node) { $footer_content = $footer_node->outerHtml(); $footer_node->delete(); }*/

        $main_node = $html_temp->findOne('main');
        $main_content = '';
        if ($main_node) { 
            $main_content = $main_node->outerHtml(); 
            $main_node->delete(); 
        }

        $block_content = '';
        $block_node = $html_temp->findOne('.block--hero');
        if ($block_node) { 
            $block_content = $block_node->outerHtml(); 
            $block_node->delete(); 
        }

        $offcanvas_html = [];
        $offcanvas_elements = $html_temp->findMulti('.offcanvas');
        if (!empty($offcanvas_elements)) {
            foreach ($offcanvas_elements as $el) { 
                $offcanvas_html[] = $el->outerHtml(); 
            }
        }
        $offcanvas_string = implode("\n", $offcanvas_html);
        $html_temp = null;

        $final_html_string = $header_content . $main_content . $block_content . $offcanvas_string . $footer_content;
        $html = HtmlDomParser::str_get_html($final_html_string);


        /*$theme_dir = get_template_directory();
        $file_path = $theme_dir . '/test.html';
        $success = file_put_contents($file_path, $final_html_string, LOCK_EX);*/

        // ---------- inline <script>/<style> topla ----------
        if ($html) {

            /*$scripts = $html->findMulti('script');
            foreach ($scripts as $script) {
                if ($script->hasAttribute('data-inline')) {
                    continue;
                }
                if (isset($script->src) && !empty($script->src)) {
                    continue;
                }
                $is_type_valid = true;
                if (isset($script->type)) {
                    if (strtolower(trim($script->type)) !== 'text/javascript') {
                        $is_type_valid = false;
                    }
                }
                if (!$is_type_valid) {
                    continue;
                }
                if (is_object($script) && method_exists($script, 'innerHtml')) {
                    $code = trim($script->innerHtml());
                    if ($code !== '') {
                        $js[] = $code;
                    }
                }
            }
            if($js){
                $js = array_unique($js);
                $js = implode("\n", $js);
                $minifier = new Minify\JS();
                $minifier->add($js);
                $js = $minifier->minify();
                $js = str_replace($this->upload_url, "{upload_url}", $js);
                $js = str_replace($this->upload_url_encoded, "{upload_url}", $js);
                $js = str_replace($this->home_url, "{home_url}", $js);
                $js = str_replace($this->home_url_encoded, "{home_url}", $js);
            }*/



        $blocks = $html->findMulti('.block-salt-theme');
        $js_codes = [];
        $js = '';

        if (!empty($blocks)) {
            foreach ($blocks as $block) {
                
                // Artık HTML dengeli olduğu için, kütüphane bu aramanın kapsamını 
                // doğru şekilde $block elementi ile sınırlayacaktır.
                $scripts = $block->findMulti('script'); 
                
                foreach ($scripts as $script) {
                    if ($script->hasAttribute('data-inline')) {
                        continue;
                    }
                    if (isset($script->src) && !empty($script->src)) {
                        continue;
                    }
                    if (isset($script->type) && strtolower(trim($script->type)) !== 'text/javascript') {
                        continue;
                    }
                    if (is_object($script) && method_exists($script, 'innerHtml')) {
                        $code = trim($script->innerHtml());
                        if ($code !== '') {
                            $js_codes[] = $code;
                        }
                    }
                }
            }
        }

        if (!empty($js_codes)) {
            $js_codes = array_unique($js_codes);
            $js = implode("\n", $js_codes);
            
            $minifier = new Minify\JS();
            $minifier->add($js);
            $js = $minifier->minify();
            
            $js = str_replace([
                $this->upload_url,
                $this->upload_url_encoded,
                $this->home_url,
                $this->home_url_encoded
            ], [
                "{upload_url}",
                "{upload_url}",
                "{home_url}",
                "{home_url}"
            ], $js);
        }

            


            $styles = $html->findMulti('style');
            $styles_filtered = [];
            foreach ($styles as $style) {
                if (!$style->hasAttribute('data-inline')) { 
                    $styles_filtered[] = $style; 
                }
            }
            foreach ($styles_filtered as $style) {
                $code = $style->innerHtml();
                if ($code !== '') { 
                    $css[] = $code; 
                }
            }
            if($css){
                $css = array_unique($css);
                $css = implode("\n", $css);
                $minifier = new Minify\CSS();
                $minifier->add($css);
                $css = $minifier->minify();
                //$css = str_replace($this->upload_url, "{upload_url}", $css);
                //$css = str_replace($this->upload_url_encoded, "{upload_url}", $css);
                //$css = str_replace($this->home_url, "{home_url}", $css);
                //$css = str_replace($this->home_url_encoded, "{home_url}", $css);
            }
        }

        // ---------- koşullu plugin map ----------
        if (!function_exists("compile_files_config")) {
            require SH_INCLUDES_PATH . "minify-rules.php";
        }
        $files = compile_files_config(true);

        if (!empty($files["js"]["plugins"])) {
            foreach ($files["js"]["plugins"] as $key => $plugin) {
                if (!empty($plugin['c'])) {
                    $condition = isset($plugin['condition']) ? $plugin['condition'] : 1;

                    if (!empty($plugin['class'])) {
                        foreach ($plugin['class'] as $class) {
                            $pattern = '/class\s*=\s*["\'][^"\']*\b' . preg_quote($class, '/') . '\b[^"\']*["\']/i';
                            $matches = [];
                            $exists = preg_match($pattern, $final_html_string, $matches);
                            error_log($key." için ".$class." varmı = ".($exists ? 'true' : 'false'));
                            /*if ($exists) {
                                $matched_html = $matches[0];
                                error_log(" | EŞLEŞEN HTML (Open Tag): " . substr($matched_html, 0, 150) . "...");
                            }*/
                            if ($exists && $condition) { 
                                $plugins[] = $key; 
                                break; 
                            }
                        }
                    }
                    if (!empty($plugin['attrs'])) {
                        foreach ($plugin['attrs'] as $attr) {
                            if (strpos($attr, '=') !== false) {
                                $exists = strpos($final_html_string, $attr) !== false;
                                if ($exists && $condition) { $plugins[] = $key; break; }
                            } else {
                                $pattern = '/\s' . preg_quote($attr, '/') . '\s*=\s*["\'].*?["\']/i';
                                $exists = preg_match($pattern, $final_html_string);
                                if ($exists && $condition) { 
                                    $plugins[] = $key; 
                                    break; 
                                }
                            }
                        }
                    }
                }
            }

            // data-required-js="plugin1,plugin2"
            if (preg_match_all('/\bdata-required-js\s*=\s*(["\'])(.*?)\1/si', $final_html_string, $m)) {
                foreach ($m[2] as $attrVal) {
                    $names = preg_split('/\s*,\s*/', $attrVal, -1, PREG_SPLIT_NO_EMPTY);
                    foreach ($names as $name) {
                        $key = trim($name);
                        if ($key !== '' && isset($files['js']['plugins'][$key])) {
                            $plugins[] = $key;
                        }
                    }
                }
            }

            if($plugins){ $plugins = array_values(array_unique($plugins)); }

            // REQUIRED bağımlılıklarını genişlet
            $plugins = $this->expand_required_plugins($plugins, $files['js']['plugins']);
        }

        // WP kısa kod bazlı JS ekleri
        $shortcodes = ['contact_form', 'contact-form-7', 'form_modal', 'wpsr_share_icons', 'newsletter'];
        foreach($shortcodes as $sc){
            if (strpos($final_html_string, $sc) !== false) {
                $wp_js[] = ($sc === 'form_modal') ? 'contact-form-7' : $sc;
            }
        }
        $wp_js = array_values(array_unique($wp_js));

        // plugin whitelist (CSS purge için)
        $plugin_files_whitelist = [];
        if (!empty($plugins) && !empty($files["js"]["plugins"])) {
            foreach($plugins as $plg){
                if(!empty($files["js"]["plugins"][$plg]["whitelist"])){
                    $plugin_files_whitelist = array_merge($plugin_files_whitelist, $files["js"]["plugins"][$plg]["whitelist"]);
                }
            }
            $plugin_files_whitelist = array_values(array_unique($plugin_files_whitelist));
        }

        $post_type_val = $this->detect_post_type($id);
        $template = '';
        if ($this->type === 'post' && $post_type_val === 'page' && function_exists('get_page_template_slug')) {
            $template = (string) get_page_template_slug($id);
        }
        $dom_classes = $this->extract_class_list_from_html_string($final_html_string);
        $dir = function_exists('is_rtl') && is_rtl() ? 'rtl' : 'ltr';

        $this->structure_fp = $this->build_structure_fingerprint([
            'type'      => $this->type,
            'post_type' => $post_type_val,
            'template'  => $template,
            'plugins'   => $plugins,
            'wp_js'     => $wp_js,
            'whitelist' => $plugin_files_whitelist,
            'classes'   => $dom_classes,
            'dir'       => $dir,
        ]);

        // Eski dosya/meta temizliği
        $this->delete_existing_assets($id);

        /* --------- PLUGIN BUNDLE (manifest + FS-check) --------- */
        $plugins_key = '';
        if(!empty($plugins) && !empty($files["js"]["plugins"])){

            $plugins_key = sha1(json_encode($plugins));
            $plugin_manifest = $this->manifest['plugins'][$plugins_key] ?? null;

            $need_rebuild_plugin = true;
            if ($plugin_manifest && !$this->force_rebuild) {
                $mc = $plugin_manifest['css']     ?? '';
                $mr = $plugin_manifest['css_rtl'] ?? '';
                $mj = $plugin_manifest['js']      ?? '';

                $has_css     = !$mc || $this->file_exists_rel($mc);
                $has_css_rtl = !$mr || $this->file_exists_rel($mr);
                $has_js      = !$mj || $this->file_exists_rel($mj);

                if ($has_css && $has_css_rtl && $has_js) {
                    $plugin_css     = $mc;
                    $plugin_css_rtl = $mr;
                    $plugin_js      = $mj;
                    $need_rebuild_plugin = false;
                }
            }

            if ($need_rebuild_plugin) {
                $plugin_files_css = [];
                $plugin_files_css_rtl = [];
                foreach($plugins as $plugin){
                    if(!empty($files["js"]["plugins"][$plugin]["css"])){
                        $plugin_files_css[]     = STATIC_URL . 'js/plugins/'.$plugin.".css";
                        $plugin_files_css_rtl[] = STATIC_URL . 'js/plugins/'.$plugin."-rtl.css";
                    }
                }

                if(!empty($plugin_files_css)){
                    $plugin_css = $this->combine_and_cache_files("css", $plugin_files_css, $plugin_files_whitelist);
                    $plugin_css = str_replace(STATIC_URL, '', $plugin_css);
                }
                if(!empty($plugin_files_css_rtl)){
                    $plugin_css_rtl = $this->combine_and_cache_files("css", $plugin_files_css_rtl, $plugin_files_whitelist);
                    $plugin_css_rtl = str_replace(STATIC_URL, '', $plugin_css_rtl);
                }

                $plugin_files_js = [];
                foreach($plugins as $plugin){ 
                    $plugin_files_js[] = STATIC_PATH . 'js/plugins/'.$plugin.".js"; 
                }
                foreach($plugins as $plugin){ 
                    $plugin_files_js[] = STATIC_PATH . 'js/plugins/'.$plugin."-init.js"; 
                }
                if($plugin_files_js){
                    $plugin_js = $this->combine_and_cache_files("js", $plugin_files_js);
                    $plugin_js = str_replace(STATIC_URL, '', $plugin_js);
                }

                $this->manifest['plugins'][$plugins_key] = [
                    'css'     => $plugin_css ?? '',
                    'css_rtl' => $plugin_css_rtl ?? '',
                    'js'      => $plugin_js ?? '',
                ];
                $this->manifest_write();
            }
        }

        /* --------- TEMPLATE/PAGE PRUNED CSS (manifest + FS-check) + RTL --------- */
        if($html_content){
            $tpl_manifest = $this->manifest['templates'][$this->structure_fp] ?? null;
            $need_rebuild_tpl = true;

            if ($tpl_manifest && !$this->force_rebuild) {
                $mc = $tpl_manifest['css']     ?? '';
                $mr = $tpl_manifest['css_rtl'] ?? '';

                $has_css     = $mc && $this->file_exists_rel($mc);
                $has_css_rtl = $mr && $this->file_exists_rel($mr);

                if ($has_css && $has_css_rtl) {
                    $css_page     = $mc;
                    $css_page_rtl = $mr;
                    $need_rebuild_tpl = false;
                }
            }

            if ($need_rebuild_tpl) {
                $css_page_raw = $this->remove_unused_css_cached($html_content, "", $plugin_files_whitelist);

                $cache_dir = rtrim(STATIC_PATH, '/').'/css/cache/';
                if (!is_dir($cache_dir)) { @mkdir($cache_dir, 0755, true); }

                $css_page_hash = $this->content_hash($css_page_raw, 'css');
                $css_page_file = $cache_dir . $css_page_hash . '.css';
                if (!file_exists($css_page_file)) {
                    @file_put_contents($css_page_file, $this->normalize_content($css_page_raw, 'css'));
                }
                $css_page = str_replace(STATIC_PATH, '', $css_page_file);

                // RTL üretimi
                $parser = new \Sabberworm\CSS\Parser($css_page_raw);
                $tree   = $parser->parse();

                /*
                $rtlcss = new \PrestaShop\RtlCss\RtlCss($tree);
                $rtlcss->flip();
                $css_page_rtl_raw  = $tree->render();*/

                $rtlcss = new RTLParser($tree);
                $rtlcss->flip();
                $css_page_rtl_raw = $tree->render();

                $css_page_rtl_hash = $this->content_hash($css_page_rtl_raw, 'css');
                $css_page_rtl_file = $cache_dir . $css_page_rtl_hash . '.css';
                if (!file_exists($css_page_rtl_file)) {
                    @file_put_contents($css_page_rtl_file, $this->normalize_content($css_page_rtl_raw, 'css'));
                }
                $css_page_rtl = str_replace(STATIC_PATH, '', $css_page_rtl_file);

                $this->manifest['templates'][$this->structure_fp] = [
                    'css'     => $css_page,
                    'css_rtl' => $css_page_rtl,
                    'plugins' => $plugins_key,
                    'critical_css' => 'css/cache/' . $this->structure_fp . '-critical.css',
                ];
                $this->manifest_write();
            }
        }

        $result = [
            "js"            => $js,
            "css"           => $css,
            "css_page"      => $css_page,
            "css_page_rtl"  => $css_page_rtl,
            "plugins"       => $plugins,
            "plugin_js"     => $plugin_js ?? '',
            "plugin_css"    => $plugin_css ?? '',
            "plugin_css_rtl"=> $plugin_css_rtl ?? '',
            "wp_js"         => $wp_js,
        ];
        $this->fix_js_data($result, ["js", "plugin_js", "wp_js"]);
        error_log("PAE result summary: css_page={$result['css_page']} | plugin_css={$result['plugin_css']} | plugins=".count($plugins));

        return $this->save_meta($result, $id);
    }

    /* ===================== DİLLER & ARŞİV ===================== */

    private function build_related_assets($id, $post_type_val = '') {
        error_log('[PAE] build_related_assets ENTER type=' . $this->type . ' id=' . $id . ' disable=' . ($this->disable_hooks ? '1':'0'));
        if ($this->disable_hooks) { error_log('[PAE] build_related_assets EARLY-RETURN (disable_hooks)'); return; }

        $this->disable_hooks = true;
        try {
            if ($this->type === 'post') {
                $post = function_exists('get_post') ? get_post($id) : null;
                error_log('[PAE] post obj=' . ($post ? ('ok#'.$post->ID) : 'null'));

                // Arşivleri (tüm diller) güncelle
                $pt = $post_type_val ?: ($post ? $post->post_type : '');
                error_log('[PAE] pt=' . $pt);
                if ($pt) { $this->fetch_and_save_archives_assets($pt); }

            } elseif ($this->type === 'term') {
                // Term çevirileri için özel bir eşleştirici yok; burada yalnız kendi dilinde çalışır.
            }
        } catch (\Throwable $e) {
            error_log("build_related_assets error: " . $e->getMessage());
        }
        $this->disable_hooks = false;
        error_log('[PAE] build_related_assets EXIT');
    }

    /**
     * Sistem çok dilli ise: $GLOBALS["languages"] ile her dil için arşiv URL üret.
     * Varsayılan dil ( $GLOBALS["language_default"] ) prefixsiz,
     * diğer diller '/{lang}/' prefixi ile.
     */
    private function get_post_type_archive_urls_all_lang($post_type) {
        $pto = get_post_type_object($post_type);
        if (!$pto || empty($pto->has_archive)) {
            error_log("[PAE] {$post_type} has_archive=false veya post type bulunamadı");
            return [];
        }

        $slug = isset($pto->rewrite['slug']) ? trim($pto->rewrite['slug'], '/') : trim($post_type, '/');

        $langs   = (isset($GLOBALS['languages']) && is_array($GLOBALS['languages'])) ? $GLOBALS['languages'] : [];
        $default = isset($GLOBALS['language_default']) ? (string)$GLOBALS['language_default'] : '';

        if (!$langs) { 
            $langs = [ ['name' => $default ?: ''] ]; 
        }

        $urls = [];
        foreach ($langs as $lang_data) {
            $lang = isset($lang_data['name']) ? (string)$lang_data['name'] : '';
            
            if ($lang && $lang !== $default) {
                $base = rtrim($this->home_url, '/') . '/' . $lang . '/';
            } else {
                $base = rtrim($this->home_url, '/') . '/';
            }

            $url = $base . $slug . '/';
            $urls[] = [
                'lang' => ($lang ?: $default ?: 'default'),
                'url'  => $url
            ];

            error_log("[PAE] base archive url={$url} lang=" . ($lang ?: $default ?: 'default'));
        }

        return $urls;
    }

    private function fetch_and_save_archives_assets($post_type) {
        $archives = $this->get_post_type_archive_urls_all_lang($post_type);
        error_log('[PAE] archive urls count=' . count($archives));
        if (!$archives) {
            error_log('[PAE] NO ARCHIVE URLS (has_archive false olabilir ya da rewrite yok)');
            return;
        }

        foreach ($archives as $item) {
            $lang = $item['lang'];
            $url  = $item['url'];

            $result = $this->fetch($url, "{$post_type}_archive_{$lang}", 'archive');
            // save_meta() archive için zaten "{$id}_assets" option’ına yazar.
        }
    }

    /* ===================== JS STRING SABİTLEME ===================== */
    private function fix_js_data(array &$data, array $js_keys): array {
        $fixed_keys = [];
        foreach ($js_keys as $key) {
            if (!isset($data[$key])) continue;
            $value = $data[$key];
            if (is_array($value)) {
                foreach ($value as $index => $js_code) {
                    if (!is_string($js_code)) continue;
                    $fixed = $this->fix_js_data_selector($js_code);
                    if ($fixed !== $js_code) {
                        $data[$key][$index] = $fixed;
                        $fixed_keys[] = "{$key}[$index]";
                    }
                }
            } elseif (is_string($value)) {
                $fixed = $this->fix_js_data_selector($value);
                if ($fixed !== $value) {
                    $data[$key] = $fixed;
                    $fixed_keys[] = $key;
                }
            }
        }
        return $fixed_keys;
    }
    private function fix_js_data_selector(string $js): string {
        $js = preg_replace_callback(
            '/("selector_matches"\s*:\s*)"((?:[^"\\\\]|\\\\.)*)"/',
            function ($m) { $escaped = addcslashes($m[2], '"\\'); return $m[1] . '"' . $escaped . '"'; },
            $js
        );
        return str_replace('</script', '<\/script', $js);
    }

    /* ===================== BİRLEŞTİRME & CACHE ===================== */
    public function combine_and_cache_files($type, $files, $whitelist = []) {
        if ($type !== 'css' && $type !== 'js') return false;

        if($type == "js"){
            $initFiles  = array_values(array_filter($files, fn($f)=>preg_match('/-init\.js$/',$f)));
            $otherFiles = array_values(array_filter($files, fn($f)=>!preg_match('/-init\.js$/',$f)));
            sort($initFiles);
            sort($otherFiles);
            $files = array_merge($otherFiles, $initFiles);
        } else {
            sort($files);
        }

        $cache_dir = rtrim(STATIC_PATH,'/').'/'.$type . '/cache/';
        if (!file_exists($cache_dir)) { @mkdir($cache_dir, 0755, true); }

        $combined_content = '';
        foreach ($files as $file) {
            $plugin_name = basename($file);
            $candidate_paths = [
                STATIC_PATH . 'js/plugins/' . $plugin_name,
                rtrim(STATIC_PATH,'/').'/'.$type.'/'.$plugin_name
            ];
            $file_system_path = '';
            foreach ($candidate_paths as $cand) {
                if (file_exists($cand)) { $file_system_path = $cand; break; }
            }
            if ($file_system_path === '') {
                error_log("PAE missing file: {$plugin_name}");
                continue;
            }
            $content = @file_get_contents($file_system_path);
            if ($content !== false) {
                if($type == "css"){
                    $content = str_replace(STATIC_URL, "../../", $content);
                    $content = str_replace("[STATIC_URL]", "../../", $content);
                }
                $combined_content .= $content . "\n";
            }
        }

        if($type == "css" && $combined_content !== ''){
            $combined_content = $this->remove_unused_css($this->html, $combined_content, "", $whitelist);
        }

        $combined_content = str_replace(["(function($) {","(function($){"], "", $combined_content);
        $combined_content = str_replace(["})(jQuery)","}(jQuery))"], "", $combined_content);

        $hash = $this->content_hash($combined_content, $type);
        $cache_file = $cache_dir . $hash . '.' . $type;

        if (!file_exists($cache_file)) {
            @file_put_contents($cache_file, $this->normalize_content($combined_content, $type));
        }

        return $type . '/cache/' . $hash . '.' . $type;
    }

    /* ===================== META KAYIT ===================== */
    public function save_meta($result, $id) {

        if (!$id || !$this->type) {
            return false;
        }

        // structure_fp değerini meta veriye ekle
        if (!empty($this->structure_fp)) {
            // Eğer $result array değilse (ki assets verisidir), array yapın.
            if (!is_array($result)) {
                $result = [];
            }
            
            // KRİTİK EKLENTİ BURADA: structure_fp'yi assets verisine ekle.
            $result['structure_fp'] = $this->structure_fp; 
        }

        $default_lcp = ['desktop' => [], 'mobile' => []];

        // ---- ARCHIVE (option) ----
        if ($this->type === 'archive') {
            $option_name  = $id . '_assets';
            $existing_opt = function_exists('get_option') ? get_option($option_name, null) : null;
            $existing     = is_array($existing_opt) ? $existing_opt : [];

            // META: varsa DEĞİŞME; yoksa oluştur
            if (isset($existing['meta']) && is_array($existing['meta'])) {
                $result['meta'] = $existing['meta'];
            } else {
                $result['meta'] = ['type' => 'archive', 'id' => $id];
            }

            // LCP: yoksa ekle; varsa dokunma
            if (!isset($result['lcp'])) {
                $result['lcp'] = (isset($existing['lcp']) && is_array($existing['lcp'])) ? $existing['lcp'] : $default_lcp;
            }

            // Diğer alanlar: eskiyi koru, yenileri yaz
            $merged = array_replace_recursive($existing, $result);

            if ($existing_opt !== null) {
                if (function_exists('update_option')) update_option($option_name, $merged);
            } else {
                if (function_exists('add_option')) add_option($option_name, $merged);
            }

            error_log("META SAVED | type=archive | key={$option_name} | css_page=" . ($merged['css_page'] ?? '') . " | plugin_js=" . ($merged['plugin_js'] ?? ''));
            return $merged;
        }

        // ---- POST/TERM/USER/COMMENT (meta) ----
        $existing_raw = $this->meta_get($this->type, $id);
        $existing     = is_array($existing_raw) ? $existing_raw : [];

        // META: varsa DEĞİŞME; yoksa oluştur
        if (isset($existing['meta']) && is_array($existing['meta'])) {
            $result['meta'] = $existing['meta'];
        } else {
            $result['meta'] = ['type' => $this->type, 'id' => $id];
        }

        // LCP: yoksa ekle; varsa dokunma
        if (!isset($result['lcp'])) {
            $result['lcp'] = (isset($existing['lcp']) && is_array($existing['lcp'])) ? $existing['lcp'] : $default_lcp;
        }

        // Diğer alanlar: merge
        $merged = array_replace_recursive($existing, $result);

        if (!empty($existing_raw) || $existing_raw === '0') {
            $this->meta_update($this->type, $id, $merged);
        } else {
            $this->meta_add($this->type, $id, $merged);
        }

        if ($this->type == 'post' && !$this->mass) {
            $this->save_post_terms($id);
        }

        $this->disable_hooks = false;
        error_log("META SAVED | type={$this->type} | key=assets | css_page=" . ($merged['css_page'] ?? '') . " | plugin_js=" . ($merged['plugin_js'] ?? ''));
        $this->maybe_copy_meta_to_translations($id, $merged);
        return $merged;
    }
    private function maybe_copy_meta_to_translations($id, $merged) {
        if (! function_exists('pll_default_language')) return;
        try {
            $default = pll_default_language();

            if ($this->type === 'post' && function_exists('pll_get_post_language')) {
                $lang = pll_get_post_language($id);
                if ($lang !== $default) return;

                $translations = pll_get_post_translations($id);
                if (!is_array($translations)) return;

                $prev = $this->disable_hooks; $this->disable_hooks = true;
                foreach ($translations as $l => $pid) {
                    if (!$pid || (int)$pid === (int)$id) continue;
                    update_post_meta((int)$pid, self::META_KEY, $merged);
                }
                $this->disable_hooks = $prev;
                return;
            }

            if ($this->type === 'term' && function_exists('pll_get_term_translations')) {
                $translations = pll_get_term_translations($id);
                if (!is_array($translations)) return;
                if (!isset($translations[$default]) || (int)$translations[$default] !== (int)$id) return;

                $prev = $this->disable_hooks; $this->disable_hooks = true;
                foreach ($translations as $l => $tid) {
                    if (!$tid || (int)$tid === (int)$id) continue;
                    update_term_meta((int)$tid, self::META_KEY, $merged);
                }
                $this->disable_hooks = $prev;
            }
        } catch (\Throwable $e) {
            error_log('[PAE] maybe_copy_meta_to_translations error: ' . $e->getMessage());
        }
    }


    public function save_post_terms( $post_id ) {
        if ( ! function_exists('get_post') || ! get_post($post_id) ) {
            return [];
        }

        $updated = [];
        $pt = function_exists('get_post_type') ? get_post_type($post_id) : '';
        $tax_objects = function_exists('get_object_taxonomies') ? get_object_taxonomies($pt, 'objects') : [];

        if (empty($tax_objects)) {
            return $updated;
        }

        // on_save_term vs. recursive tetiklenmesin diye korumayı aç/kapa
        $prev_disable = $this->disable_hooks;
        $this->disable_hooks = true;

        try {
            foreach ($tax_objects as $taxonomy => $details) {
                if (empty($details->public)) {
                    continue;
                }

                $terms = function_exists('get_the_terms') ? get_the_terms($post_id, $taxonomy) : [];
                if (empty($terms) || is_wp_error($terms)) {
                    continue;
                }

                foreach ($terms as $term) {
                    // Terim sayfasının asset’lerini rebuild et
                    $this->type = 'term';
                    $ok = $this->fetch_term_url($term->term_id, $taxonomy);
                    if ($ok !== false) {
                        $updated[] = $term->term_id;
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('save_post_terms error: ' . $e->getMessage());
        } finally {
            $this->disable_hooks = $prev_disable;
        }

        return $updated;
    }


    public function delete_existing_assets($id) {
        $existing = null;
        switch ($this->type) {
            case "post":    $existing = $this->meta_get('post', $id);    $this->meta_delete('post', $id);    break;
            case "term":    $existing = $this->meta_get('term', $id);    $this->meta_delete('term', $id);    break;
            case "user":    $existing = $this->meta_get('user', $id);    $this->meta_delete('user', $id);    break;
            case "comment": $existing = $this->meta_get('comment', $id); $this->meta_delete('comment', $id); break;
            case "archive": $option_name = $id . '_assets'; $existing = get_option($option_name); if ($existing !== false) delete_option($option_name); break;
            default:        $existing = null;
        }

        error_log("[PAE] delete_existing_assets(".$this->type.", ".$id);
        error_log(print_r($existing, true));

        if (is_array($existing)) {
            foreach (['plugin_js','plugin_css','plugin_css_rtl'] as $k) {
                if (!empty($existing[$k])) {
                    $abs = rtrim(STATIC_PATH,'/').'/'.ltrim($existing[$k],'./');
                    error_log("...siliniyor: ".$abs);
                    if (file_exists($abs)) @unlink($abs);
                }
            }
        }
    }
    
    public function purge_page_assets_manifest() {
        $cache_manifest = rtrim(defined('STATIC_PATH') ? STATIC_PATH : __DIR__.'/', '/').'/cache-manifest/assets-manifest.json';
        if (file_exists($cache_manifest)) {
            unlink($cache_manifest); // cache sil
        }
        $this->force_rebuild = true;
        $this->remove_purge_css();
        $this->remove_critical_css();
    }

    /* ===================== SİTEMAP & DİĞERLERİ ===================== */

    public function get_all_urls($sitemap_url = null, $urls = []) {
        if ($sitemap_url === null) {
            $sitemap_url = function_exists('site_url') ? site_url('/sitemap_index.xml') : '/sitemap_index.xml';
        }

        $sitemap_content = @file_get_contents($sitemap_url);
        if (!$sitemap_content) { return []; }

        $xml = @simplexml_load_string($sitemap_content);
        if(!$xml){ return []; }

        $namespaces = $xml->getDocNamespaces(true);
        if (isset($namespaces[''])) {
            $xml->registerXPathNamespace('ns', $namespaces['']);
        } else {
            $xml->registerXPathNamespace('ns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        }

        $sitemap_path      = parse_url($sitemap_url, PHP_URL_PATH) ?: '';
        $sitemap_file_name = preg_replace('/-sitemap\.xml$/', '', basename($sitemap_path));
        $roles             = method_exists($this, 'get_roles') ? (array) $this->get_roles() : [];

        if ($xml->xpath('//ns:sitemap')) {
            foreach ($xml->xpath('//ns:sitemap/ns:loc') as $sitemap_loc) {
                $sub_sitemap_url = (string)$sitemap_loc;
                $urls = $this->get_all_urls($sub_sitemap_url, $urls);
            }
            return $urls;
        }

        foreach ($xml->xpath('//ns:url/ns:loc') as $url_loc) {
            $url_string = (string)$url_loc;

            if ($this->is_post_type_excluded($sitemap_file_name)) continue;
            if ($this->is_taxonomy_excluded($sitemap_file_name)) continue;

            //if (in_array($sitemap_file_name, $this->excluded_post_types, true)) continue;
            //if (in_array($sitemap_file_name, $this->excluded_taxonomies, true)) continue;

            // === (A) ROLE-BAZLI USER SİTEMAPLERİ ===
            // Örn: /artist-sitemap.xml, /editor-sitemap.xml, projendeki özel roller...
            if (!empty($roles) && in_array($sitemap_file_name, $roles, true)) {
                // Senin eski mantığınla birebir:
                $author_name = basename($url_string);
                $author = function_exists('get_user_by') ? get_user_by('slug', $author_name) : null;
                if ($author) {
                    $urls[$author->ID] = [
                        "type"      => "user",
                        "post_type" => $sitemap_file_name, // role adı
                        "url"       => $url_string
                    ];
                }
                continue;
            }

            // === (B) COMMENT SİTEMAP ===
            if ($sitemap_file_name === 'comment') {
                $author_name = basename($url_string);
                $author = function_exists('get_user_by') ? get_user_by('slug', $author_name) : null;
                if ($author) {
                    $urls[$author->ID] = [
                        "type"      => "comment",
                        "post_type" => "comment",
                        "url"       => $url_string
                    ];
                }
                continue;
            }

            // === (C) AUTHOR SİTEMAP (standart) ===
            if ($sitemap_file_name === 'author') {
                if (preg_match('~/author/([^/]+)/?~i', $url_string, $m)) {
                    $user = function_exists('get_user_by') ? get_user_by('slug', sanitize_title($m[1])) : null;
                    if ($user) {
                        $urls[$user->ID] = [
                            "type"      => "user",
                            "post_type" => "author",
                            "url"       => $url_string
                        ];
                    }
                }
                continue;
            }

            // === (D) POST / PAGE / CPT ===
            if ($sitemap_file_name === 'post' || $sitemap_file_name === 'page' || (function_exists('post_type_exists') && post_type_exists($sitemap_file_name))) {

                $post_id = function_exists('url_to_postid') ? url_to_postid($url_string) : 0;

                // CPT arşiv: senin yeni mantığını koruyoruz
                if (!$post_id && function_exists('getUrlEndpoint')) {
                    if (getUrlEndpoint($url_string) == $sitemap_file_name && $this->pae_is_default_lang_url($url_string)) {
                        $urls[$sitemap_file_name] = [
                            "type"      => "archive",
                            "post_type" => $sitemap_file_name,
                            "url"       => $url_string
                        ];
                        continue;
                    }
                }

                // Fallback: slug'tan CPT objesi
                if (!$post_id && function_exists('get_page_by_path')) {
                    $slug = sanitize_title(basename(rtrim($url_string, '/')));
                    $obj  = get_page_by_path($slug, OBJECT, $sitemap_file_name);
                    if ($obj) { $post_id = (int) $obj->ID; }
                }

                if ($post_id) {
                    $urls[$post_id] = [
                        "type"      => "post",
                        "post_type" => function_exists('get_post_type') ? get_post_type($post_id) : $sitemap_file_name,
                        "url"       => $url_string
                    ];
                }
                continue;
            }

            // === (E) TAXONOMY ===
            $tax_alias = ['format' => 'post_format'];
            $tax_name  = $tax_alias[$sitemap_file_name] ?? $sitemap_file_name;

            if (in_array($sitemap_file_name, ['category','post_tag','post_format'], true) ||
                (function_exists('taxonomy_exists') && taxonomy_exists($tax_name))) {

                $term_slug = sanitize_title(basename(rtrim($url_string, '/')));
                $term      = function_exists('get_term_by') ? get_term_by('slug', $term_slug, $tax_name) : null;

                if ($term && !is_wp_error($term)) {
                    $urls[$term->term_id] = [
                        "type"      => "term",
                        "post_type" => $tax_name,
                        "url"       => $url_string
                    ];
                }
                continue;
            }

            // === (F) DİĞERLERİ: gerçekten archive/özel sitemap ===
            $urls[$sitemap_file_name] = [
                "type"      => "archive",
                "post_type" => $sitemap_file_name,
                "url"       => $url_string
            ];
        }

        return $urls;
    }

    /* ======== Tema için güvenli okuma (opsiyonel) ======== */
    public static function get_post_assets_safe($post_id) {
        $assets = function_exists('get_post_meta') ? get_post_meta($post_id, self::META_KEY, true) : null;
        if ($assets) return $assets;
        return function_exists('get_option') ? get_option('site_assets') : null;
    }

    // evrensel meta get/set/delete
    private function meta_get($type, $id) {
        error_log("--- start meta_get");
        error_log(print_r(get_post_meta($id, self::META_KEY, true), true));
        error_log("--- end meta_get");
        switch ($type) {
            case 'post':    return get_post_meta($id, self::META_KEY, true);
            case 'term':    return get_term_meta($id, self::META_KEY, true);
            case 'user':    return get_user_meta($id, self::META_KEY, true);
            case 'comment': return get_comment_meta($id, self::META_KEY, true);
        }

        return null;
    }
    private function meta_update($type, $id, $val) {
        switch ($type) {
            case 'post':    update_post_meta($id, self::META_KEY, $val);    break;
            case 'term':    update_term_meta($id, self::META_KEY, $val);    break;
            case 'user':    update_user_meta($id, self::META_KEY, $val);    break;
            case 'comment': update_comment_meta($id, self::META_KEY, $val); break;
        }
    }
    private function meta_add($type, $id, $val) {
        switch ($type) {
            case 'post':    add_post_meta($id, self::META_KEY, $val, true);    break;
            case 'term':    add_term_meta($id, self::META_KEY, $val, true);    break;
            case 'user':    add_user_meta($id, self::META_KEY, $val, true);    break;
            case 'comment': add_comment_meta($id, self::META_KEY, $val, true); break;
        }
    }
    private function meta_delete($type, $id) {
        switch ($type) {
            case 'post':    delete_post_meta($id, self::META_KEY);    break;
            case 'term':    delete_term_meta($id, self::META_KEY);    break;
            case 'user':    delete_user_meta($id, self::META_KEY);    break;
            case 'comment': delete_comment_meta($id, self::META_KEY); break;
        }
    }




    public function get_roles() {
        global $wp_roles;
        $roles = [];
        if (!isset($wp_roles)) {
            $wp_roles = new WP_Roles();
        }
        foreach ($wp_roles->roles as $role_key => $role_details) {
            $name = $role_details['name'];
            $roles[] = $role_key;
        }
        return $roles;
    }






    public function display_page_assets_table() {
        $raw = $this->get_all_urls();

        // --- Sadece default dil URL'leri ---
        $rows = [];
        foreach ($raw as $key => $item) {
            $url  = (string)($item['url'] ?? '');
            if (!$url) continue;

            // Default dil değilse atla
            if (!$this->pae_is_default_lang_url($url) ) continue;

            $type      = $item['type']      ?? 'post';
            $post_type = $item['post_type'] ?? $type;
            $id        = $key;

            // Arşiv satırı ID’sini okunaklılaştır
            if ($type === 'archive') {
                $lang = $this->pae_lang_from_url($url);
                $id   = 'archive_' . $lang;
            }

            $url_short = str_replace(home_url(), "", $url);

            $rows[] = [
                'id'        => $id,
                'type'      => $type,
                'post_type' => $post_type,
                'url'       => $url,
                'url_short' => $url_short
            ];
        }

        $total   = count($rows);
        $message = $total
            ? "JS & CSS Extraction process completed with <strong>{$total} default-language pages.</strong>"
            : "Not found any pages to extract process.";

        echo '<div class="bg-white rounded-3 p-3 shadow-sm">';
        echo '<div class="mb-3">'.$message.'</div>';

        if ($rows) {
            echo '<table class="table-page-assets table table-sm table-hover table-striped" style="width:100%; border-collapse: collapse;background-color:#fff;">';
            echo '<thead><tr style="background-color:#f2f2f2; text-align:left;">';
            echo '<th style="padding:10px; border-bottom:1px solid #ddd;">ID / Key</th>';
            echo '<th style="padding:10px; border-bottom:1px solid #ddd;">Type</th>';
            echo '<th style="padding:10px; border-bottom:1px solid #ddd;">Url</th>';
            echo '<th style="padding:10px; border-bottom:1px solid #ddd;">Actions</th>';
            echo '</tr></thead><tbody>';

            foreach ($rows as $i => $row) {
                echo '<tr id="'.esc_attr($row["type"].'_'.$row["id"]).'" data-index="'.$i.'" style="vertical-align:middle;">';
                echo '<td data-id="'.esc_attr($row["id"]).'" style="padding:10px; border-bottom:1px solid #ddd;">'.esc_html($row["id"]).'</td>';
                echo '<td data-type="'.esc_attr($row["type"]).'" style="padding:10px; border-bottom:1px solid #ddd;">'.esc_html($row["post_type"]).'</td>';
                echo '<td data-url="'.esc_attr($row["url"]).'" style="padding:10px; border-bottom:1px solid #ddd; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:900px;">'.esc_html($row["url_short"]).' <a href="'.esc_attr($row["url"]).'" target="_blank"><i class="fa-solid fa-link"></i></a></td>';
                echo '<td class="actions" style="width:80px;padding:10px; border-bottom:1px solid #ddd;"><a href="#" class="btn-page-assets-single btn btn-success btn-sm">Fetch</a></td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
            echo '<div class="table-page-assets-status text-center py-4">';
            echo '<div class="progress-page-assets progress d-none mb-4" role="progressbar" aria-label="Animated striped" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%"></div></div>';
            echo '<a href="#" class="btn-page-assets-update btn btn-success btn-lg px-4">Start Mass Update</a>';
            echo '</div>';
        } else {
            echo '<p>No data found.</p>';
        }
        echo '</div>';
        ?>
        <script type="text/javascript">
            var urls = <?php echo json_encode(array_values($rows));?>;
            jQuery(function($) {
                $(".btn-page-assets-single").on("click", function(e){
                    e.preventDefault();
                    var $row = $(this).closest("tr");
                    var idx  = parseInt($row.attr("data-index"),10) || 0;
                    $(this).addClass("disabled");
                    page_assets_update(idx, true);
                });
                $(".btn-page-assets-update").on("click", function(e){
                    e.preventDefault();
                    $(this).addClass("disabled");
                    $(".progress-page-assets").removeClass("d-none");
                    page_assets_update(0, false);
                });
            });
            function page_assets_update(i, single){
                var $row = $(".table-page-assets").find("tr[data-index='"+i+"']");
                $row.find(".actions").empty().addClass("loading loading-xs position-relative");
                jQuery.ajax({
                    url: ajaxurl,
                    type: 'post',
                    dataType: 'json',
                    data: { action:'page_assets_update', url: urls[i] },
                    success: function(res){
                        $row.find("td").addClass("bg-success text-white");
                        $row.find(".actions").removeClass("loading loading-xs").html("<strong>OK</strong>");
                        if(!single){
                            var percent = ((i+1) * 100) / urls.length;
                            jQuery(".progress-page-assets .progress-bar").css("width", percent+"%");
                            if(i < urls.length-1){ page_assets_update(i+1, false); }
                            else {
                                jQuery(".progress-page-assets").addClass("d-none");
                                jQuery(".table-page-assets-status").prepend("<div class='text-success fs-5 fw-bold mb-2'>COMPLETED</div>");
                                jQuery(".btn-page-assets-update, .btn-page-assets-single").removeClass("disabled");
                            }
                        } else {
                            jQuery(".btn-page-assets-single").removeClass("disabled");
                        }
                    },
                    error: function(xhr, st, err){
                        console.error('AJAX Error: ' + st + ' - ' + err);
                        $row.find(".actions").removeClass("loading loading-xs").html("<strong class='text-danger'>ERR</strong>");
                    }
                });
            }
        </script>
        <?php
    }
    public function update_page_assets_message_field($field){
        ob_start();
        $this->display_page_assets_table();
        echo ob_get_clean();
        return $field;
    }
    public function page_assets_update(){
        $row = isset($_POST["url"]) ? (array) $_POST["url"] : [];
        $id   = $row["id"]   ?? 0;
        $type = $row["type"] ?? 'post';
        $url  = $row["url"]  ?? '';

        $this->mass = true;
        $this->type = $type;

        $data = $this->fetch($url, $id, $type);
        wp_send_json([
            "error"   => false,
            "message" => "",
            "html"    => "",
            "data"    => $data,
        ]);
    }



    private function detectTimberTemplatePaths(): array {
        $paths = [];
        $checked = [];

        // 1) Child/Parent theme default "templates"
        if (function_exists('get_stylesheet_directory')) {
            $p = trailingslashit(get_stylesheet_directory()) . 'templates';
            $checked[] = $p;
            if (is_dir($p)) { $paths[] = $p; }
        }
        if (
            function_exists('get_template_directory') &&
            function_exists('get_stylesheet_directory') &&
            get_template_directory() !== get_stylesheet_directory()
        ) {
            $p = trailingslashit(get_template_directory()) . 'templates';
            $checked[] = $p;
            if (is_dir($p)) { $paths[] = $p; }
        }

        // 2) Timber::$locations (Timber 2)
        if (class_exists('\Timber\Timber') && !empty(\Timber\Timber::$locations)) {
            foreach (\Timber\Timber::$locations as $loc) {
                $abs = rtrim($loc, '/\\');
                $checked[] = $abs;
                if (is_dir($abs)) { $paths[] = $abs; }
            }
        }

        // 3) Timber::$dirname (legacy / hala yaygın)
        if (class_exists('\Timber\Timber') && !empty(\Timber\Timber::$dirname)) {
            $dirnames = (array) \Timber\Timber::$dirname;
            $resolved = $this->resolveTimberDirnamesToAbsolute($dirnames, $checked);
            $paths = array_merge($paths, $resolved);
        }

        // Temizle + logla
        $paths = array_values(array_unique(array_filter($paths, 'is_dir')));
        error_log('[PAE] detectTimberTemplatePaths checked=' . json_encode($checked, JSON_UNESCAPED_SLASHES));
        error_log('[PAE] detectTimberTemplatePaths final='   . json_encode($paths,   JSON_UNESCAPED_SLASHES));

        return $paths;
    }
    private function ensureTwigPaths(): void {
        if ($this->twig_paths_initialized) return;

        // 1) dışarıdan geldiyse önce onu kullan
        if (!empty($this->twig_options['twig_paths']) && is_array($this->twig_options['twig_paths'])) {
            $paths = array_values(array_filter($this->twig_options['twig_paths'], 'is_string'));
            $paths = array_values(array_filter($paths, 'is_dir'));
            $this->twig_template_paths = $paths;
            $this->twig_paths_initialized = true;
        } else {
            // 2) otomatik tespit (bir kez)
            $this->twig_template_paths = $this->detectTimberTemplatePaths();
            $this->twig_paths_initialized = true;
        }

        error_log('[PAE] twig_paths (lazy)=' . json_encode($this->twig_template_paths, JSON_UNESCAPED_SLASHES));
    }
    private function resolveTimberDirnamesToAbsolute(array $dirnames, array &$checked): array {
        $roots = [];

        // Child & parent theme kökleri
        if (function_exists('get_stylesheet_directory')) {
            $roots[] = trailingslashit(get_stylesheet_directory());
        }
        if (function_exists('get_template_directory')) {
            $roots[] = trailingslashit(get_template_directory());
        }

        // wp-content ve ABSPATH de dene (vendor/... gibi)
        if (defined('WP_CONTENT_DIR')) {
            $roots[] = trailingslashit(WP_CONTENT_DIR);
        }
        if (defined('ABSPATH')) {
            $roots[] = trailingslashit(ABSPATH);
        }

        // Bu sınıfın bulunduğu plugin/theme kökü (vendor senaryosu için iş görür)
        if (defined('__DIR__')) {
            $roots[] = trailingslashit(dirname(__DIR__)); // sınıfa göre 1 seviye yukarı
            $roots[] = trailingslashit(__DIR__);          // bulunduğu klasör
        }

        $out = [];
        foreach ($dirnames as $d) {
            if (!$d) { continue; }
            // Absolute geldiyse direkt dene
            if ($d[0] === '/' || preg_match('#^[A-Z]:[\\\\/]#i', $d)) {
                $candidate = rtrim($d, '/\\');
                $checked[] = $candidate;
                if (is_dir($candidate)) {
                    $out[] = $candidate;
                    continue;
                }
            }

            // Relative ise her root’a ekle
            foreach ($roots as $root) {
                $candidate = rtrim($root . ltrim($d, '/\\'), '/\\');
                $checked[] = $candidate;
                if (is_dir($candidate)) {
                    $out[] = $candidate;
                }
            }
        }

        // uniq
        $out = array_values(array_unique($out));
        return $out;
    }
    private function locateTwig(string $template): ?string {
        $this->ensureTwigPaths(); // path’lar yoksa şimdi tespit et

        $tpl = ltrim($template, '/\\');
        if (!str_ends_with($tpl, '.twig')) {
            $tpl .= '.twig';
        }
        if (array_key_exists($tpl, $this->twig_locate_cache)) {
            return $this->twig_locate_cache[$tpl] ?: null;
        }
        foreach ($this->twig_template_paths as $base) {
            $path = rtrim($base, '/\\') . DIRECTORY_SEPARATOR . $tpl;
            if (is_file($path)) {
                $this->twig_locate_cache[$tpl] = $path;
                return $path;
            }
        }
        $this->twig_locate_cache[$tpl] = null;
        return null;
    }
    private function collectIncludedTwigRaw(string $raw, string $current_dir, array &$visited = []): string {
        $out = '';
        if (preg_match_all('/\{\%\s*include\s*[\'"]([^\'"]+)[\'"]\s*(?:with\s+[^%]+)?\%\}/', $raw, $m)) {
            foreach ($m[1] as $inc) {
                $candidates = [];
                if (strpos($inc, '/') === 0) {
                    $candidates[] = $this->locateTwig(ltrim($inc, '/'));
                } else {
                    $rel = $current_dir . DIRECTORY_SEPARATOR . $inc;
                    $candidates[] = is_file($rel) ? $rel : null;
                    $candidates[] = $this->locateTwig($inc);
                }
                $found = false;
                foreach (array_filter($candidates) as $file) {
                    if (!isset($visited[$file]) && is_readable($file)) {
                        $visited[$file] = true;
                        $sub = file_get_contents($file);
                        if ($sub !== false) {
                            $out .= "\n" . $sub;
                            $found = true;
                            error_log('[PAE] include resolved: ' . $inc . ' -> ' . $file);
                            // rekürsif
                            $out .= "\n" . $this->collectIncludedTwigRaw($sub, dirname($file), $visited);
                        } else {
                            error_log('[PAE] include read fail: ' . $file);
                        }
                    }
                }
                if (!$found) {
                    error_log('[PAE] include NOT found: ' . $inc);
                }
            }
        }
        return $out;
    }
    private function logApproxHtmlSelectors(string $html, string $label = ''): void {
        $classes = [];
        $ids     = [];

        $frag = HtmlDomParser::str_get_html($html);
        if (!$frag) {
            error_log('[PAE] logApproxHtmlSelectors: failed to parse approx html for ' . $label);
            return;
        }

        foreach ($frag->find('*') as $el) {
            // class
            $cls = $el->getAttribute('class');
            if ($cls) {
                foreach (preg_split('/\s+/', trim($cls)) as $c) {
                    if ($c !== '') { $classes[$c] = true; }
                }
            }
            // id
            $id = $el->getAttribute('id');
            if ($id) {
                $ids[$id] = true;
            }
        }

        $classes = array_keys($classes);
        $ids     = array_keys($ids);

        // Çok uzun olmasın diye ilk 30 tanesini gösterelim
        $sampleClasses = array_slice($classes, 0, 30);
        $sampleIds     = array_slice($ids, 0, 30);

        error_log(sprintf('[PAE] selectors from %s | classes=%d ids=%d', $label, count($classes), count($ids)));
        error_log('[PAE] classes sample: ' . implode(', ', $sampleClasses));
        error_log('[PAE] ids sample: ' . implode(', ', $sampleIds));
    }
    private function twigToApproxHtml(string $twig): string {
        $s = $twig;

        // 1) Twig yorumları {# ... #}
        $s = preg_replace('/\{\#.*?\#\}/s', '', $s);

        // 2) Twig control blokları {% ... %} → tamamen sil
        $s = preg_replace('/\{\%.*?\%\}/s', '', $s);

        // 3) Twig değişkenleri {{ ... }} → boşalt (bazı yerlerde class attribute içinde olabilir)
        //   class="{{ something }}" → class="" kalsın
        $s = preg_replace('/\{\{.*?\}\}/s', '', $s);

        // 4) Bozuk kalan attribute/etiket kapanışlarını biraz toparla
        //   (Bu approx; HtmlDomParser çoğu durumda yine parse edebiliyor.)
        //   Fazla boşlukları azalt
        $s = preg_replace('/\s+/', ' ', $s);

        // 5) Twig include kalıntıları vs yok
        $s = trim($s);

        // Artık bu string, DOM’a gömülüp selector taramasında kullanılabilir
        return $s;
    }
    private function collectTwigLoadedHtml(\voku\helper\HtmlDomParser $dom): string {
        $nodes = $dom->find("*[{$this->twig_attr}]");
        if (!$nodes || count($nodes) === 0) {
            error_log('[PAE] data-template: node bulunamadı');
            return '';
        }

        $this->ensureTwigPaths();

        // 1) DOM’daki TÜM data değerlerini topla ve normalize et
        $uniqueTemplates = [];
        foreach ($nodes as $node) {
            $raw = trim((string) $node->getAttribute($this->twig_attr));
            if ($raw === '') { continue; }
            $parts = preg_split('/[,;]+/', $raw);
            foreach ($parts as $p) {
                $p = trim($p);
                if ($p === '') continue;
                if (!str_ends_with($p, '.twig')) $p .= '.twig';
                $uniqueTemplates[$p] = true;
            }
        }

        $all = array_keys($uniqueTemplates);
        if (empty($all)) {
            error_log('[PAE] data-template: template değeri yok (boş)');
            return '';
        }

        // 2) Daha önce işlenmişleri at
        $toProcess = [];
        foreach ($all as $tpl) {
            if (!isset($this->twig_seen_templates[$tpl])) {
                $this->twig_seen_templates[$tpl] = true;
                $toProcess[] = $tpl;
            }
        }

        if (empty($toProcess)) {
            error_log('[PAE] data-template: tüm template değerleri önceden işlenmiş, atlandı. uniq=' . count($all));
            return '';
        }

        error_log('[PAE] data-template: uniq=' . count($all) . ', yeni_islenecek=' . count($toProcess));
        $html_chunks = [];
        $foundFiles  = [];
        $missed      = [];

        // 3) Her bir uniq template için bir kez çalış
        foreach ($toProcess as $tpl) {
            $file = $this->locateTwig($tpl);
            if (!$file || !is_readable($file)) {
                $missed[] = $tpl;
                continue;
            }

            $foundFiles[] = $file;

            // 4) Dosya approx-HTML cache’i
            if (isset($this->twig_approx_cache[$file])) {
                $html_chunks[] = $this->twig_approx_cache[$file];
                continue;
            }

            $raw = file_get_contents($file);
            if ($raw === false) { continue; }

            // include’ları çöz (opsiyonel)
            if ($this->twig_scan_includes) {
                $raw .= "\n" . $this->collectIncludedTwigRaw($raw, dirname($file));
            }

            $approx_html = $this->twigToApproxHtml($raw);
            $this->twig_approx_cache[$file] = $approx_html ?: '';

            if ($approx_html) {
                $html_chunks[] = $approx_html;
            }
        }

        // 5) Log
        if ($foundFiles) {
            error_log('[PAE] Twig bulundu: ' . count($foundFiles));
            foreach ($foundFiles as $f) {
                error_log('[PAE]  - ' . $f);
            }
        }
        if ($missed) {
            error_log('[PAE] Twig bulunamadı: ' . json_encode($missed, JSON_UNESCAPED_SLASHES));
            error_log('[PAE]  aranan_yollar: ' . json_encode($this->twig_template_paths, JSON_UNESCAPED_SLASHES));
        }

        // Örnek: basit sınıf/ID istatistiği (yaklaşık)
        $summary = strip_tags(implode(' ', $html_chunks));
        preg_match_all('/class="([^"]+)"/', $summary, $m1);
        preg_match_all('/id="([^"]+)"/', $summary, $m2);
        $classes = [];
        if (!empty($m1[1])) {
            foreach ($m1[1] as $cstr) {
                foreach (preg_split('/\s+/', trim($cstr)) as $c) {
                    if ($c !== '') { $classes[$c] = true; }
                }
            }
        }
        $ids = array_unique($m2[1] ?? []);
        error_log('[PAE] approx selectors: classes=' . count($classes) . ' ids=' . count($ids));

        return implode("\n", $html_chunks);
    }



    /**
     * Çöp Toplayıcı: Yetim kalmış varlıkları (CSS/JS) ve manifest kayıtlarını temizler.
     * Bu metodun bir WP Cron görevi ile periyodik (örn. günde 1) çalıştırılması önerilir.
     */
    public function cleanup_orphaned_assets()
    {
        error_log('[PAE] Çöp toplama (GC) başlatılıyor...');

        // 1. Manifest'i güvenli bir şekilde oku (Bkz. Bölüm 2: get_manifest)
        $manifest = $this->get_manifest();
        if (empty($manifest['templates']) && empty($manifest['plugins'])) {
            error_log('[PAE] GC: Manifest boş veya okunamadı. İşlem iptal.');
            return false;
        }

        // 2. Veritabanından "aktif" olarak kullanılan TÜM structure_fp'leri topla
        // Sınıfınız 'assets' meta_key'ini kullanıyor gibi görünüyor.
        global $wpdb;
        $active_structure_hashes = [];
        
        // Yazı (Post) meta verilerini tara
        $post_meta_key = self::META_KEY; // 'assets'
        $results = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT meta_value FROM $wpdb->postmeta WHERE meta_key = %s",
            $post_meta_key
        ));

        foreach ($results as $meta_value) {
            $data = maybe_unserialize($meta_value);
            if (is_array($data) && !empty($data['structure_fp'])) {
                $active_structure_hashes[$data['structure_fp']] = 1; // Hızlı lookup için hash map kullan
            }
        }

        // Terim (Term) meta verilerini tara ve aktif listesine ekle (EKLEMENİZ GEREKEN KISIM)
        $term_meta_results = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT meta_value FROM $wpdb->termmeta WHERE meta_key = %s",
            $post_meta_key // Term meta için de aynı 'assets' anahtarını kullanıyorsunuz
        ));
        foreach ($term_meta_results as $meta_value) {
            $data = maybe_unserialize($meta_value);
            if (is_array($data) && !empty($data['structure_fp'])) {
                $active_structure_hashes[$data['structure_fp']] = 1;
            }
        }

        // (İsteğe bağlı) Terim (Term) meta verilerini tara
        // Eğer term'ler için de bu sistemi kullanıyorsanız, buraya benzer bir sorgu ekleyin
        // $results = $wpdb->get_col("... $wpdb->termmeta ...");
        // ...

        $active_structure_hashes = array_keys($active_structure_hashes);
        error_log('[PAE] GC: ' . count($active_structure_hashes) . ' adet aktif yapı (structure) bulundu.');

        // 3. Manifest'teki "tüm" kayıtları al
        $manifest_templates = $manifest['templates'];
        $manifest_plugins = $manifest['plugins'];
        $all_manifest_structures = array_keys($manifest_templates);

        // 4. Yetim (orphaned) yapıları bul (Manifest'te var ama aktifte yok)
        $orphaned_structures = array_diff($all_manifest_structures, $active_structure_hashes);
        
        if (empty($orphaned_structures)) {
            error_log('[PAE] GC: Yetim yapı bulunamadı. Temizlik tamamlandı.');
            return true; // Temizlenecek bir şey yok
        }

        error_log('[PAE] GC: ' . count($orphaned_structures) . ' adet yetim yapı (structure) bulundu.');

        // 5. Hangi dosyaların ve eklenti (plugin) hash'lerinin SİLİNECEĞİNİ ve hangilerinin KORUNACAĞINI belirle
        $files_to_delete = [];
        $plugins_to_delete = [];
        $files_to_keep = [];
        $plugins_to_keep = [];

        // Önce "korunacakları" bulalım
        foreach ($active_structure_hashes as $hash) {
            if (!isset($manifest_templates[$hash])) continue;
            
            $template = $manifest_templates[$hash];
            if (!empty($template['css'])) $files_to_keep[$template['css']] = 1;
            if (!empty($template['css_rtl'])) $files_to_keep[$template['css_rtl']] = 1;
            if (!empty($template['critical_css'])) $files_to_keep[$template['critical_css']] = 1;
            if (!empty($template['plugins'])) $plugins_to_keep[$template['plugins']] = 1;
        }

        // Şimdi "silinecek adayları" bulalım
        foreach ($orphaned_structures as $hash) {
            if (!isset($manifest_templates[$hash])) continue;

            $template = $manifest_templates[$hash];
            if (!empty($template['css'])) $files_to_delete[$template['css']] = 1;
            if (!empty($template['css_rtl'])) $files_to_delete[$template['css_rtl']] = 1;
            if (!empty($template['critical_css'])) $files_to_delete[$template['critical_css']] = 1;
            if (!empty($template['plugins'])) $plugins_to_delete[$template['plugins']] = 1;

            // Bu "yetim" kaydı manifest'ten sil
            unset($manifest['templates'][$hash]);
        }

        // 6. Eklenti (plugin) hash'lerini ve dosyalarını netleştir
        $orphaned_plugin_hashes = array_diff(array_keys($plugins_to_delete), array_keys($plugins_to_keep));
        
        foreach($orphaned_plugin_hashes as $plugin_hash) {
            if (!isset($manifest_plugins[$plugin_hash])) continue;
            
            $plugin_files = $manifest_plugins[$plugin_hash];
            if (!empty($plugin_files['css'])) $files_to_delete[$plugin_files['css']] = 1;
            if (!empty($plugin_files['css_rtl'])) $files_to_delete[$plugin_files['css_rtl']] = 1;
            if (!empty($plugin_files['js'])) $files_to_delete[$plugin_files['js']] = 1;

            // Bu "yetim" eklenti kaydını manifest'ten sil
            unset($manifest['plugins'][$plugin_hash]);
        }
        
        // 7. Silinecek son dosya listesini belirle (ÖNEMLİ: Korunacaklar listesinden çıkar)
        $final_files_to_delete = array_diff(array_keys($files_to_delete), array_keys($files_to_keep));

        // 8. Fiziksel dosyaları sil
        $deleted_count = 0;
        $base_path = rtrim(STATIC_PATH, '/'); // Varsayılan yol, gerekirse düzeltin

        foreach ($final_files_to_delete as $relative_path) {
            $file_path = $base_path . '/' . ltrim($relative_path, '/');
            if (file_exists($file_path)) {
                if (@unlink($file_path)) {
                    $deleted_count++;
                    error_log('[PAE] GC: Dosya silindi: ' . $file_path);
                } else {
                    error_log('[PAE] GC: HATA! Dosya silinemedi: ' . $file_path);
                }
            }
        }

        $structure_fp = $this->structure_fp; 
        if (!empty($structure_fp)) {
            
            // 2. Kritik CSS dosya yolunu (structure_fp tabanlı) hesapla
            $critical_css_relative_path = 'css/cache/' . $structure_fp . '-critical.css';

            // 3. Manifest'teki templates kaydına kritik CSS yolunu ekle
            // (Manifest'te ilgili $structure_fp kaydının zaten oluşturulmuş olduğunu varsayıyoruz)
            $this->manifest['templates'][$structure_fp]['critical_css'] = $critical_css_relative_path;
        }

        // 9. Temizlenmiş manifest'i kaydet (Bkz. Bölüm 2: save_manifest)
        $this->save_manifest($manifest);

        error_log("[PAE] GC: Temizlik tamamlandı. $deleted_count dosya silindi. " . count($orphaned_structures) . " yapı kaydı manifest'ten kaldırıldı.");
        return true;
    }

    /**
     * Manifest dosyasını "kilitleyerek" güvenli bir şekilde okur.
     * Yarış durumlarını (race conditions) engeller.
     * @return array Manifest içeriği
     */
    protected function get_manifest()
    {
        // $this->manifest_path sınıfınızda tanımlı
        $path = $this->manifest_path;
        
        if (!file_exists($path)) {
            // Dosya yoksa, varsayılan boş manifest'i döndür
            // $this->manifest sınıfınızda tanımlı
            return $this->manifest; 
        }

        $content = false;
        $fp = @fopen($path, 'r'); // Okuma modunda aç

        if ($fp) {
            // Paylaşımlı bir kilit al (okuma için)
            // Diğer okumalara izin verir, ancak özel (yazma) kilitleri bekler
            if (flock($fp, LOCK_SH)) { 
                $content = @file_get_contents($path); // file_get_contents anlık okur
                flock($fp, LOCK_UN); // Kilidi bırak
            }
            fclose($fp);
        }

        if ($content === false) {
            error_log('[PAE] Manifest dosyası okunamadı (belki kilitli?): ' . $path);
            // Okuma başarısız olursa (çok düşük ihtimal), varsayılanı döndür
            return $this->manifest;
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('[PAE] Manifest JSON hatası! Dosya bozulmuş olabilir: ' . $path);
            // Bozuksa, varsayılanı döndür (ve üzerine yazılmasını sağla)
            return $this->manifest;
        }

        // Gelen veriyi sınıfın varsayılanı ile birleştir, eksik key'ler sorun çıkarmasın
        return array_merge($this->manifest, $data);
    }

    /**
     * Manifest dosyasını "kilitleyerek" güvenli bir şekilde yazar.
     * @param array $data Kaydedilecek manifest dizisi
     * @return bool Başarı durumu
     */
    protected function save_manifest(array $data)
    {
        $path = $this->manifest_path;
        $json_data = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $fp = @fopen($path, 'w'); // Yazma modunda aç (dosyayı oluşturur/sıfırlar)
        if (!$fp) {
            error_log('[PAE] Manifest dosyası yazmak için açılamadı! İzinleri kontrol edin: ' . $path);
            return false;
        }

        // Özel bir kilit al (yazma için)
        // Diğer tüm okuma (LOCK_SH) ve yazma (LOCK_EX) kilitlerini bekler
        if (flock($fp, LOCK_EX)) {
            $result = fwrite($fp, $json_data);
            fflush($fp); // Buffer'ı diske zorla
            flock($fp, LOCK_UN); // Kilidi bırak
            fclose($fp);
            
            if ($result === false) {
                error_log('[PAE] Manifest dosyasına yazma hatası: ' . $path);
                return false;
            }
            return true;
        } else {
            fclose($fp);
            error_log('[PAE] Manifest dosyası kilitlenemedi! (Başka bir süreç kilitledi): ' . $path);
            return false;
        }
    }

    /**
     * Harici olarak Twig şablon yollarını ayarlar.
     * Bu, sınıfın Timber/Twig bulucuya uymasını sağlar.
     *
     * @param array $paths Aranacak dizinlerin tam yollarını içeren dizi
     */
    public function set_twig_template_paths(array $paths)
    {
        // Sınıfınızdaki özelliğin adı 'twig_template_paths'
        $this->twig_template_paths = $paths;
    }

    /**
     * YENİ: Güncellenen hash değerlerine göre CSS kullanımını kaydeder ve kullanılmayan eski dosyayı siler.
     * KRİTİK: Ortak kullanılan dosyaların silinmesini engeller.
     *
     * @param string $old_hash Eski kullanılan CSS dosyası hash'i.
     * @param string $new_hash Yeni kullanılan CSS dosyası hash'i.
     * @param int|string $content_id Etkin olan içeriğin (Post ID, Term ID, Options String vb.) ID'si.
     */
    protected function update_css_usage_and_cleanup(string $old_hash, string $new_hash, $content_id): void {
        
        // manifest_read() çağrılır: Manifest'in güncel halini okur ve css_usage'ı hazırlar.
        $this->manifest_read(); 

        $content_id = (string) $content_id;

        // 1. Yeni Hash'i Kaydet
        if (!empty($new_hash)) {
            if (!isset($this->manifest['css_usage'][$new_hash])) {
                $this->manifest['css_usage'][$new_hash] = [];
            }
            if (!in_array($content_id, $this->manifest['css_usage'][$new_hash])) {
                $this->manifest['css_usage'][$new_hash][] = $content_id;
            }
            $this->manifest['css_usage'][$new_hash] = array_unique($this->manifest['css_usage'][$new_hash]);
        }

        // 2. Eski Hash'i Temizle ve Silme Kontrolü
        if (!empty($old_hash) && $old_hash !== $new_hash) {
            
            if (isset($this->manifest['css_usage'][$old_hash])) {
                $this->manifest['css_usage'][$old_hash] = array_diff($this->manifest['css_usage'][$old_hash], [$content_id]);
                
                // EĞER KULLANIM LİSTESİ BOŞALIRSA (Kullanım Sayacı Kontrolü)
                if (empty($this->manifest['css_usage'][$old_hash])) {
                    
                    // Fiziksel dosyayı sil
                    $file_path_pattern = trailingslashit($this->upload_dir) . 'assets/' . $old_hash . '*.css';
                    
                    foreach (glob($file_path_pattern) as $file_path) {
                        if (is_file($file_path)) {
                            @unlink($file_path);
                            error_log("CSS Asset Silindi (Manifest Onaylı): " . basename($file_path));
                        }
                    }
                    
                    // Manifest'ten de hash kaydını sil
                    unset($this->manifest['css_usage'][$old_hash]);
                }
            }
        }

        // manifest_write() çağrılır: Manifest'in güncel halini diske yazar.
        $this->manifest_write(); 
    }

    /**
 * Varlık çıkarma işlemi tamamlandıktan sonra çağrılır.
 * Eski/yeni CSS hash'lerini karşılaştırır, Usage Manifest'i günceller
 * ve içeriğin (Post/Term/Options) meta verisini günceller.
 * * @param int|string $content_id İçeriğin ID'si.
 * @param array $new_assets_data Yeni çıkarılan varlık verileri (içinde css_hash ve plugin_css_hash olmalı).
 */
    public function finalize_assets_and_cleanup($content_id, array $new_assets_data): void {
        
        $type = $this->type;
        $old_assets_data = $this->meta_get($type, $content_id);

        // Tüm takip edilmesi gereken hash anahtarlarını tanımla
        $asset_hash_keys = [
            'css_hash',         // Ana CSS dosyası
            'plugin_css_hash',  // Plugin CSS dosyası (Büyük ihtimalle bu siliniyordu)
            // Eğer başka hash'ler de varsa buraya eklenmeli
        ];

        $has_new_hash = false;
        
        foreach ($asset_hash_keys as $key) {
            $old_hash = $old_assets_data[$key] ?? '';
            $new_hash = $new_assets_data[$key] ?? '';
            
            // Eğer yeni hash varsa, DB kaydının yapılacağını işaretle
            if (!empty($new_hash)) {
                $has_new_hash = true;
            }

            // Eski veya yeni hash mevcutsa Manifest güncellemesini çalıştır
            if (!empty($old_hash) || !empty($new_hash)) {
                $this->update_css_usage_and_cleanup($old_hash, $new_hash, $content_id);
            }
        }

        if (!$has_new_hash) {
             error_log("[PAE] FINALIZE: Hiçbir yeni CSS hash bulunamadı. Temizlik atlandı.");
             return; 
        }

        // Yeni varlık verilerini (tüm hash'ler dahil) meta verisine kaydet
        $this->meta_update($type, $content_id, $new_assets_data);

        error_log("[PAE] FINALIZE: Asset meta verisi güncellendi. Content ID: {$content_id}");
    }


    // =========================================================
    //                STATİK CRON METODLARI
    // =========================================================
    // Bunlar dün oluşturduğumuz gibi kalabilir,
    // `__construct` tarafından statik olarak çağrılmaları temiz bir yöntemdir.
    public static function schedule_cleanup_event(){
        if (!wp_next_scheduled('my_daily_assets_cleanup')) {
            wp_schedule_event(time(), 'daily', 'my_daily_assets_cleanup');
        }
    }
    public static function run_cleanup_task(){
        error_log('[PAE] Cron (run_cleanup_task) tetiklendi. Temizlik başlıyor...');
        // Statik bir metodun içindeyiz, bu yüzden SADECE `get_instance()` 
        // kullanarak sınıfın çalışan örneğini alabiliriz.
        // YENİ BİR TANE OLUŞTURMAYIZ (`new`), mevcudu alırız.
        $extractor = PageAssetsExtractor::get_instance();
        $extractor->cleanup_orphaned_assets();
    }

    public function clear_content_cache_and_hash($id)
    {
        // Yalnızca o içeriğe ait kullanılan sınıflar listesini tutan önbelleği sil.
        // Bu, PageAssetsExtractor'ın bir sonraki yüklemede bu içeriği yeniden analiz etmesini sağlar.
        delete_metadata('post', $id, self::META_KEY); 
        delete_metadata('term', $id, self::META_KEY);
        
        // O içeriğe ait son kullanılan HTML hash'ini sil.
        // Bu, içeriğin yeniden parse edilmesini tetikler ve yeni bir CSS Hash'inin hesaplanmasını sağlar.
        delete_metadata('post', $id, self::HTML_HASH_META_KEY);
        delete_metadata('term', $id, self::HTML_HASH_META_KEY);
        
        // ACF Option Page ID'leri için
        if (!is_numeric($id)) {
            delete_option(self::META_KEY . '_' . $id);
            delete_option(self::HTML_HASH_META_KEY . '_' . $id);
        }

        error_log("[PAE] İçerik önbelleği temizlendi (ID: {$id}). Yeni CSS Hash hesaplanacak.");
    }

}


function trigger_page_assets_rebuild_on_save($id) {
    // 1. Temel engellemeler
    if (wp_is_post_revision($id) || wp_is_post_autosave($id)) {
        return;
    }
    
    if (!class_exists('PageAssetsExtractor')) return;
    $extractor = PageAssetsExtractor::get_instance();

    // Hangi hook tetiklendi?
    $current_filter = current_filter();
    
    // --- DURUM A: BİR POST/SAYFA KAYDEDİLDİYSE ---
    if ($current_filter === 'save_post') {
        $post_type = get_post_type($id);
        if ($post_type && $extractor->is_post_type_excluded($post_type)) {
            return; 
        }

        // Sadece Post ID'sini temizle
        $extractor->clear_content_cache_and_hash($id); 
        
        // İlişkili arşivi temizle
        if ($post_type) {
            $extractor->clear_content_cache_and_hash($post_type . '_options');
        }
    } 
    
    // --- DURUM B: BİR TERM (KATEGORİ/ETİKET) KAYDEDİLDİYSE ---
    elseif ($current_filter === 'edited_term' || $current_filter === 'created_term') {
        $term = get_term($id);
        
        if ($term && !is_wp_error($term)) {
            // Taxonomy Exclude Kontrolü
            if ($extractor->is_taxonomy_excluded($term->taxonomy)) {
                return;
            }

            // Term ID'sini temizle
            $extractor->clear_content_cache_and_hash($id); 
            
            // Taksonomi arşivini temizle
            $extractor->clear_content_cache_and_hash($term->taxonomy . '_options');
        }
    }
    // ÖNEMLİ: Bu noktada başka bir global temizlik yapmaya GEREK YOKTUR. 
    // Sistemin bir sonraki sayfa yüklemesinde (normal kullanıcı veya bot) 
    // PageAssetsExtractor çalışır ve:
        // a) Eski Hash'i alır.
        // b) Yeni Sınıf Listesini oluşturur.
        // c) Yeni Hash'i hesaplar.
        // d) Hash'ler farklıysa, YENİ CSS DOSYASINI oluşturur.
        // e) Post/Term meta verisini yeni Hash ile günceller.
}

// Hook'lar
add_action('save_post', 'trigger_page_assets_rebuild_on_save', 20, 1);
add_action('edited_term', 'trigger_page_assets_rebuild_on_save', 20, 1);
add_action('created_term', 'trigger_page_assets_rebuild_on_save', 20, 1);

/**
 * ===================================================================
 * SINIFI OTOMATİK BAŞLAT
 * ===================================================================
 * Bu satır, class.page-assets-extractor.php dosyanızın EN ALTINDA,
 * sınıf tanımının dışında yer almalıdır.
 *
 * Sınıf dosyası "require" edildiği ANDA, bu kod çalışır,
 * "get_instance()" metodunu tetikler. O metod da "new self()"
 * ile constructor'ı SADECE BİR KEZ çalıştırır. Constructor da
 * tüm "add_action" kancalarını kaydeder.
 */
PageAssetsExtractor::get_instance();
