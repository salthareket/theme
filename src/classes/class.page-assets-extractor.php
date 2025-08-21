<?php
use MatthiasMullie\Minify;
use voku\helper\HtmlDomParser;

class PageAssetsExtractor
{
    /* ======= Sabitler ======= */
    const META_KEY = 'assets';
    const HTML_HASH_META_KEY = '_page_assets_last_html_hash';

    /* ======= Genel Durum ======= */
    public    $type = null;                       // 'post' | 'term' | 'archive' | ...
    public    $mass = false;
    public    $disable_hooks = false;
    public    $force_rebuild = false;

    public $home_url = "";
    public $home_url_encoded = "";
    public $upload_url = "";
    public $upload_url_encoded = "";
    public $url;
    public $html;

    public $source_css = STATIC_PATH ."css/main-combined.css";

    protected $structure_fp = '';

    /* ======= Manifest ======= */
    protected $manifest_path;
    protected $manifest = [
        'version'   => 1,
        'global'    => [],
        'templates' => [],  // key = structure_fp
        'plugins'   => []   // key = sha1(json_encode(plugins))
    ];

    public function __construct() {
        error_log("PageAssetsExtractor initialized in admin.");

        $this->home_url = function_exists('home_url') ? rtrim(home_url("/"), '/') . '/' : "/";
        $this->home_url_encoded = str_replace("/","\/", $this->home_url);

        $upload_dir = function_exists('wp_upload_dir') ? wp_upload_dir() : ['baseurl' => '/uploads'];
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

        add_action('acf/render_field/name=page_assets', [$this, 'update_page_assets_message_field']);
        add_action('wp_ajax_page_assets_update', [$this,'page_assets_update']);
        add_action('wp_ajax_nopriv_page_assets_update', [$this,'page_assets_update']);
    }

    /* ===================== HOOK AKIŞI ===================== */

    public function on_save_post($post_id, $post, $update) {
        error_log("P O S T  S A V I N G  H O O K...." . $post_id);
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (function_exists('wp_is_post_revision') && wp_is_post_revision($post_id)) return;

        $post = empty($post) && function_exists('get_post') ? get_post($post_id) : $post;
        if (!$post) return;
        if ($post->post_status !== 'publish') return;
        if (!$this->is_supported_post_type($post->post_type)) return;
        if ($this->disable_hooks) return;

        error_log("Saved post : {$post_id} | type: " . $post->post_type);

        $this->type = "post";
        $ok = $this->fetch_post_url($post_id);

        if ($ok !== false && !empty($this->html)) {
            $this->check_and_handle_html_change(
                $post_id,
                $this->html,
                'post'
            );
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

            $opts = ["http" => ["header" => "User-Agent: MyFetchBot/1.0\r\n", "timeout" => 10]];
            $context = stream_context_create($opts);
            $html_content = @HtmlDomParser::file_get_html($fetch_url, false, $context);
            if (!$html_content) {
                error_log('[PAE] file_get_html FAILED');
                return false;
            }

            $this->html = $html_content;

            $result = $this->extract_assets($html_content, $id);
            error_log('[PAE] extract_assets DONE type=' . $this->type . ' id=' . $id);

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
        $critical_cache_dir = rtrim(STATIC_PATH, '/').'/css/cache/';
        if(is_dir($critical_cache_dir)) {
            // *-critical.css ile biten tüm dosyaları seç
            $files = glob($critical_cache_dir.'*-critical.css');
            foreach($files as $file) {
                @unlink($file);
            }
        }
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
        $normalized = str_replace([$this->upload_url, $this->upload_url_encoded], '{upload_url}', $content);
        $normalized = str_replace([$this->home_url, $this->home_url_encoded], '{home_url}', $normalized);
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
        if (is_readable($this->manifest_path)) {
            $json = @file_get_contents($this->manifest_path);
            $arr = @json_decode($json, true);
            if (is_array($arr)) {
                $this->manifest = array_merge($this->manifest, $arr);
            }
        }
    }
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

        // ---------- DOM kırpma ----------
        $html_temp = HtmlDomParser::str_get_html($html_content->__toString());

        $header_node = $html_temp->findOne('#header');
        $header_content = '';
        if ($header_node) { $header_content = $header_node->outerHtml(); $header_node->delete(); }

        $main_node = $html_temp->findOne('main');
        $main_content = '';
        if ($main_node) { $main_content = $main_node->outerHtml(); $main_node->delete(); }

        $block_content = '';
        $block_node = $html_temp->findOne('.block--hero');
        if ($block_node) { $block_content = $block_node->outerHtml(); $block_node->delete(); }

        $offcanvas_html = [];
        $offcanvas_elements = $html_temp->findMulti('.offcanvas');
        if (!empty($offcanvas_elements)) {
            foreach ($offcanvas_elements as $el) { $offcanvas_html[] = $el->outerHtml(); }
        }
        $offcanvas_string = implode("\n", $offcanvas_html);
        $html_temp = null;

        $final_html_string = $header_content . $main_content . $block_content . $offcanvas_string;
        $html = HtmlDomParser::str_get_html($final_html_string);

        // ---------- inline <script>/<style> topla ----------
        if ($html) {
            $scripts = $html->findMulti('script');
            $scripts_filtered = [];
            foreach ($scripts as $script) {
                if (!$script->hasAttribute('data-inline')) { $scripts_filtered[] = $script; }
            }
            foreach ($scripts_filtered as $script) {
                if (is_object($script) && method_exists($script, 'innerHtml')) {
                    $code = $script->innerHtml();
                    if ($code !== '') { $js[] = $code; }
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
            }

            $styles = $html->findMulti('style');
            $styles_filtered = [];
            foreach ($styles as $style) {
                if (!$style->hasAttribute('data-inline')) { $styles_filtered[] = $style; }
            }
            foreach ($styles_filtered as $style) {
                $code = $style->innerHtml();
                if ($code !== '') { $css[] = $code; }
            }
            if($css){
                $css = array_unique($css);
                $css = implode("\n", $css);
                $minifier = new Minify\CSS();
                $minifier->add($css);
                $css = $minifier->minify();
                $css = str_replace($this->upload_url, "{upload_url}", $css);
                $css = str_replace($this->upload_url_encoded, "{upload_url}", $css);
                $css = str_replace($this->home_url, "{home_url}", $css);
                $css = str_replace($this->home_url_encoded, "{home_url}", $css);
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
                            $exists = preg_match($pattern, $final_html_string);
                            error_log($key." için ".$class." varmı = ".($exists ? 'true' : 'false'));
                            if ($exists && $condition) { $plugins[] = $key; break; }
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
                                if ($exists && $condition) { $plugins[] = $key; break; }
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
        $shortcodes = ['contact_form', 'contact-form-7', 'form_modal', 'wpsr_share_icons'];
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
                foreach($plugins as $plugin){ $plugin_files_js[] = STATIC_PATH . 'js/plugins/'.$plugin.".js"; }
                foreach($plugins as $plugin){ $plugin_files_js[] = STATIC_PATH . 'js/plugins/'.$plugin."-init.js"; }
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
                $rtlcss = new \PrestaShop\RtlCss\RtlCss($tree);
                $rtlcss->flip();
                $css_page_rtl_raw  = $tree->render();
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

        if (!$langs) { $langs = [$default ?: '']; }

        $urls = [];
        foreach ($langs as $lang) {
            $lang = (string)$lang;
            if ($lang && $lang !== $default) {
                $base = rtrim($this->home_url, '/').'/'.$lang.'/';
            } else {
                $base = rtrim($this->home_url, '/').'/';
            }
            $url = $base . $slug . '/';
            $urls[] = ['lang' => ($lang ?: $default ?: 'default'), 'url' => $url];
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
            sort($initFiles); sort($otherFiles);
            $files = array_merge($initFiles, $otherFiles);
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
        return $merged;
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
        switch ($this->type) {
            case "post":    $existing = $this->meta_get('post', $id);    $this->meta_delete('post', $id);    break;
            case "term":    $existing = $this->meta_get('term', $id);    $this->meta_delete('term', $id);    break;
            case "user":    $existing = $this->meta_get('user', $id);    $this->meta_delete('user', $id);    break;
            case "comment": $existing = $this->meta_get('comment', $id); $this->meta_delete('comment', $id); break;
            case "archive": $option_name = $id . '_assets'; $existing = get_option($option_name); if ($existing !== false) delete_option($option_name); break;
            default:        $existing = null;
        }

        if (is_array($existing)) {
            foreach (['plugin_js','plugin_css','plugin_css_rtl'] as $k) {
                if (!empty($existing[$k])) {
                    $abs = rtrim(STATIC_PATH,'/').'/'.ltrim($existing[$k],'./');
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

    /*public function get_all_urls($sitemap_url = null, $urls = []) { //tum dilleri alır
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

        if ($xml->xpath('//ns:sitemap')) {
            foreach ($xml->xpath('//ns:sitemap/ns:loc') as $sitemap_loc) {
                $sub_sitemap_url = (string)$sitemap_loc;
                $urls = $this->get_all_urls($sub_sitemap_url, $urls);
            }
        } else {
            foreach ($xml->xpath('//ns:url/ns:loc') as $url_loc) {
                $url_string = (string)$url_loc;
                $sitemap_file_name = basename($sitemap_url, '-sitemap.xml');

                switch($sitemap_file_name){

                    case "page" :
                    case "post" :
                        $post_id = function_exists('url_to_postid') ? url_to_postid($url_string) : 0;
                        if ($post_id) {
                            $urls[$post_id] = [
                                "type" => "post",
                                "post_type" => function_exists('get_post_type') ? get_post_type($post_id) : '',
                                "url" => $url_string
                            ];
                        }
                    break;

                    case "post_tag" :
                    case "category" :
                    case "format" :
                        $term_slug = basename($url_string);
                        $term = function_exists('get_term_by') ? get_term_by('slug', $term_slug, $sitemap_file_name) : null;
                        if ($term) {
                            $urls[$term->term_id] = [
                                "type" => "term",
                                "post_type" => $sitemap_file_name,
                                "url" => $url_string
                            ];
                        }
                    break;

                    default :
                        // diğer sitemaplar: archive vb.
                        $urls[$sitemap_file_name] = [
                            "type" => "archive",
                            "post_type" => $sitemap_file_name,
                            "url" => $url_string
                        ];
                    break;
                }
            }
        }

        return $urls;
    }*/

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
                echo '<tr id="'.esc_attr($row["type"].'_'.$row["id"]).'" data-index="'.$i.'">';
                echo '<td data-id="'.esc_attr($row["id"]).'" style="padding:10px; border-bottom:1px solid #ddd;">'.esc_html($row["id"]).'</td>';
                echo '<td data-type="'.esc_attr($row["type"]).'" style="padding:10px; border-bottom:1px solid #ddd;">'.esc_html($row["post_type"]).'</td>';
                echo '<td data-url="'.esc_attr($row["url"]).'" style="padding:10px; border-bottom:1px solid #ddd; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:900px;">'.esc_html($row["url_short"]).'</td>';
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
}
