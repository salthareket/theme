<?php

/**
 * PAE ManagesCache Trait
 *
 * Manifest okuma/yazma, JS/CSS bundle oluşturma ve cache yönetimi.
 * combine_and_cache_files(), cascade_rebuild_bundles(), update_css_usage_and_cleanup() içerir.
 *
 * @package    SaltHareket\Theme\PageAssetsExtractor
 * @version    1.0.0
 * @since      1.9.7
 *
 * @changelog
 *   1.0.0 - 2026-05-03
 *     - Refactor: class.page-assets-extractor.php'den ayrıldı
 *     - Add: CODING_PRINCIPLES uyumlu dokümantasyon
 *
 * HOW TO USE:
 *   Bu trait PageAssetsExtractor sınıfı içinde kullanılır.
 *   Manifest dosyası: STATIC_PATH/cache-manifest/assets-manifest.json
 *   Bundle cache: STATIC_PATH/js/cache/ ve STATIC_PATH/css/cache/
 *
 * @example Manifest oku:
 *   $this->manifest_read();
 *   $templates = $this->manifest['templates'];
 *
 * @example Bundle oluştur:
 *   $path = $this->combine_and_cache_files('css', $files, $whitelist);
 *   // → 'css/cache/abc123.css'
 *
 * @example Manifest yaz:
 *   $this->manifest_write();
 *
 * @example Mevcut asset'leri sil:
 *   $this->delete_existing_assets($post_id);
 *
 * @example Manifest'i tamamen temizle:
 *   $this->purge_page_assets_manifest();
 */
trait ManagesCache {

    // =========================================================
    //  MANIFEST
    // =========================================================

    /**
     * Manifest dosyasını okur ve $this->manifest'e yükler.
     *
     * @return void
     *
     * @example
     *   $this->manifest_read();
     *   echo $this->manifest['version'];
     */
    private function manifest_read(): void {
        if (file_exists($this->manifest_path)) {
            $content = @file_get_contents($this->manifest_path);
            if ($content) {
                $data = json_decode($content, true);
                if (is_array($data)) {
                    $this->manifest = array_merge($this->manifest, $data);
                }
            }
        }

        if (!isset($this->manifest['css_usage']) || !is_array($this->manifest['css_usage'])) {
            $this->manifest['css_usage'] = [];
        }
        if (!isset($this->manifest['content_usage']) || !is_array($this->manifest['content_usage'])) {
            $this->manifest['content_usage'] = [];
        }
    }

    /**
     * $this->manifest'i dosyaya yazar.
     * %2 ihtimalle orphan temizliği de tetikler.
     *
     * @return void
     *
     * @example
     *   $this->manifest['templates'][$fp] = $data;
     *   $this->manifest_write();
     */
    private function manifest_write(): void {
        @file_put_contents(
            $this->manifest_path,
            json_encode($this->manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        if (mt_rand(1, 100) <= 2) {
            $this->purge_orphan_assets();
        }
    }

    // =========================================================
    //  BUNDLE OLUŞTURMA
    // =========================================================

    /**
     * Verilen dosyaları birleştirir, minify eder ve cache'e yazar.
     *
     * @param  string   $type      'css' veya 'js'
     * @param  string[] $files     Kaynak dosya yolları
     * @param  string[] $whitelist CSS purge whitelist (sadece CSS için)
     * @return string|false Relative cache path (örn. 'css/cache/abc123.css') veya false
     *
     * @example CSS bundle:
     *   $path = $this->combine_and_cache_files('css', $css_files, $whitelist);
     *   // → 'css/cache/abc123.css'
     *
     * @example JS bundle:
     *   $path = $this->combine_and_cache_files('js', $js_files);
     *   // → 'js/cache/def456.js'
     */
    public function combine_and_cache_files(string $type, array $files, array $whitelist = []) {
        if ($type !== 'css' && $type !== 'js') return false;

        if ($type === 'js') {
            $initFiles  = array_values(array_filter($files, fn($f) => preg_match('/-init\.js$/', $f)));
            $otherFiles = array_values(array_filter($files, fn($f) => !preg_match('/-init\.js$/', $f)));
            sort($initFiles);
            sort($otherFiles);
            $files = array_merge($otherFiles, $initFiles);
        } else {
            sort($files);
        }

        $cache_dir = rtrim(STATIC_PATH, '/') . '/' . $type . '/cache/';
        if (!file_exists($cache_dir)) wp_mkdir_p($cache_dir);

        $combined_content = '';
        foreach ($files as $file) {
            $plugin_name      = basename($file);
            $candidate_paths  = [
                STATIC_PATH . 'js/plugins/' . $plugin_name,
                rtrim(STATIC_PATH, '/') . '/' . $type . '/' . $plugin_name,
            ];
            $file_system_path = '';
            foreach ($candidate_paths as $cand) {
                if (file_exists($cand)) { $file_system_path = $cand; break; }
            }
            if ($file_system_path === '') {
                $this->error_log("PAE missing file: {$plugin_name}");
                continue;
            }
            $content = @file_get_contents($file_system_path);
            if ($content !== false) {
                if ($type === 'css') {
                    $content           = str_replace(STATIC_URL, '../../', $content);
                    $content           = str_replace('[STATIC_URL]', '../../', $content);
                    $combined_content .= $content . "\n";
                } else {
                    $content = rtrim($content);
                    if ($content !== '' && !preg_match('/[;\}\)]$/', $content)) {
                        $content .= ';';
                    }
                    $combined_content .= $content . "\n";
                }
            }
        }

        if ($type === 'css' && $combined_content !== '') {
            $combined_content = $this->remove_unused_css($this->html, $combined_content, '', $whitelist);
        }

        $hash       = $this->content_hash($combined_content, $type);
        $cache_file = $cache_dir . $hash . '.' . $type;

        if (!file_exists($cache_file)) {
            @file_put_contents($cache_file, $this->normalize_content($combined_content, $type));
        }

        return $type . '/cache/' . $hash . '.' . $type;
    }

    /**
     * Plugin module import dosyası oluşturur (ES module sistemi için).
     *
     * @param  string[] $plugins
     * @param  string   $hash
     * @return string Relative path
     *
     * @example
     *   $path = $this->combine_and_cache_modules(['swiper', 'aos'], $hash);
     */
    private function combine_and_cache_modules(array $plugins, string $hash = ''): string {
        $type             = 'js';
        $combined_content = '';
        $cache_dir        = rtrim(STATIC_PATH, '/') . '/' . $type . '/cache/';
        if (!file_exists($cache_dir)) wp_mkdir_p($cache_dir);

        foreach ($plugins as $plugin) {
            $combined_content .= "import '" . STATIC_URL . "js/modules/plugins/{$plugin}.js';\n";
            if (file_exists(STATIC_PATH . "js/modules/plugins/{$plugin}-init.js")) {
                $combined_content .= "import '" . STATIC_URL . "js/modules/plugins/{$plugin}-init.js';\n";
            }
        }

        if ($hash === '') {
            $hash = $this->content_hash($combined_content, $type);
        }

        $cache_file = $cache_dir . $hash . '-module.' . $type;
        if (!file_exists($cache_file)) {
            @file_put_contents($cache_file, $this->normalize_content($combined_content, $type));
        }

        return $type . '/cache/' . $hash . '-module.' . $type;
    }

    // =========================================================
    //  MTIME HASH
    // =========================================================

    /**
     * Kaynak dosyaların mtime'larından fingerprint üretir.
     * Kaynak değişince aynı plugin kombinasyonu için yeni bundle oluşturulur.
     *
     * @param  string[] $files
     * @return string MD5 hash
     *
     * @example
     *   $hash = $this->source_files_mtime_hash($plugin_files);
     */
    private function source_files_mtime_hash(array $files): string {
        $data = '';
        foreach ($files as $file) {
            $plugin_name = basename($file);
            $candidates  = [
                STATIC_PATH . 'js/plugins/' . $plugin_name,
                rtrim(STATIC_PATH, '/') . '/css/' . $plugin_name,
                rtrim(STATIC_PATH, '/') . '/js/' . $plugin_name,
            ];
            foreach ($candidates as $c) {
                if (file_exists($c)) { $data .= $c . ':' . filemtime($c) . '|'; break; }
            }
        }
        return md5($data);
    }

    // =========================================================
    //  ASSET SİLME
    // =========================================================

    /**
     * Bir içeriğin mevcut asset meta'sını siler.
     * extract_assets() başında çağrılır.
     *
     * @param  int|string $id
     * @return void
     *
     * @example
     *   $this->delete_existing_assets($post_id);
     */
    public function delete_existing_assets($id): void {
        $norm_type = $this->normalize_meta_type($this->type);
        switch ($norm_type) {
            case 'post':    $this->meta_delete('post', $id);    break;
            case 'term':    $this->meta_delete('term', $id);    break;
            case 'user':    $this->meta_delete('user', $id);    break;
            case 'comment': $this->meta_delete('comment', $id); break;
        }

        if (in_array($this->type, ['archive', 'dynamic'], true)) {
            $option_name = $id . '_assets';
            if (get_option($option_name) !== false) delete_option($option_name);
        }
    }

    /**
     * Tüm manifest ve cache dosyalarını temizler.
     *
     * @return void
     *
     * @example
     *   $extractor->purge_page_assets_manifest();
     */
    public function purge_page_assets_manifest(): void {
        $cache_manifest = rtrim(defined('STATIC_PATH') ? STATIC_PATH : __DIR__ . '/', '/') . '/cache-manifest/assets-manifest.json';
        if (file_exists($cache_manifest)) unlink($cache_manifest);
        $this->force_rebuild = true;
        $this->remove_purge_css();
        $this->remove_critical_css();
    }

    // =========================================================
    //  CSS USAGE TRACKING
    // =========================================================

    /**
     * CSS hash kullanımını manifest'e kaydeder, eski hash'i düşer.
     *
     * @param  string     $old_hash
     * @param  string     $new_hash
     * @param  int|string $content_id
     * @return void
     *
     * @example
     *   $this->update_css_usage_and_cleanup($old_hash, $new_hash, $post_id);
     */
    protected function update_css_usage_and_cleanup(string $old_hash, string $new_hash, $content_id): void {
        $this->manifest_read();
        $content_id = (string) $content_id;

        if (!empty($new_hash)) {
            if (!isset($this->manifest['css_usage'][$new_hash])) {
                $this->manifest['css_usage'][$new_hash] = [];
            }
            if (!in_array($content_id, $this->manifest['css_usage'][$new_hash], true)) {
                $this->manifest['css_usage'][$new_hash][] = $content_id;
            }
            $this->manifest['css_usage'][$new_hash] = array_unique($this->manifest['css_usage'][$new_hash]);
        }

        if (!empty($old_hash) && $old_hash !== $new_hash) {
            if (isset($this->manifest['css_usage'][$old_hash])) {
                $this->manifest['css_usage'][$old_hash] = array_diff($this->manifest['css_usage'][$old_hash], [$content_id]);
                if (empty($this->manifest['css_usage'][$old_hash])) {
                    unset($this->manifest['css_usage'][$old_hash]);
                }
            }
        }

        $this->manifest_write();
    }

    /**
     * Asset çıkarma tamamlandıktan sonra CSS hash'lerini günceller ve meta'yı yazar.
     *
     * @param  int|string $content_id
     * @param  array      $new_assets_data
     * @return void
     *
     * @example
     *   $this->finalize_assets_and_cleanup($post_id, $new_assets);
     */
    public function finalize_assets_and_cleanup($content_id, array $new_assets_data): void {
        $type             = $this->type;
        $old_assets_data  = $this->meta_get($type, $content_id);
        $asset_hash_keys  = ['css_hash', 'plugin_css_hash', 'critical_css_hash'];

        foreach ($asset_hash_keys as $key) {
            $old_hash = $old_assets_data[$key] ?? '';
            $new_hash = $new_assets_data[$key] ?? '';
            if (!empty($old_hash) || !empty($new_hash)) {
                $this->update_css_usage_and_cleanup($old_hash, $new_hash, $content_id);
            }
        }

        if (!empty($new_assets_data)) {
            $this->meta_update($type, $content_id, $new_assets_data);
        }
    }

    // =========================================================
    //  PLUGIN BUNDLE YÖNETİMİ
    // =========================================================

    /**
     * Eski plugin bundle'ı başka content kullanmıyorsa siler.
     *
     * @param  string $plugins_key
     * @return void
     */
    private function _maybe_delete_plugin_bundle(string $plugins_key): void {
        $pm = $this->manifest['plugins'][$plugins_key] ?? null;
        if (!$pm) return;

        $contents = $pm['contents'] ?? [];
        if (!empty($contents)) {
            $this->error_log("[PAE] Plugin bundle {$plugins_key} hâlâ " . count($contents) . " content tarafından kullanılıyor.");
            return;
        }

        foreach (['css', 'css_rtl', 'js'] as $k) {
            if (!empty($pm[$k]) && $this->file_exists_rel($pm[$k])) {
                $abs = rtrim(STATIC_PATH, '/') . '/' . ltrim($pm[$k], '/');
                if (@unlink($abs)) {
                    $this->error_log("[PAE] Plugin bundle dosyası silindi: {$pm[$k]}");
                }
            }
        }

        unset($this->manifest['plugins'][$plugins_key]);
        $this->error_log("[PAE] Plugin bundle manifest'ten kaldırıldı: {$plugins_key}");
    }

    /**
     * Bundle dosyalarını siler (manifest entry'yi silmeden).
     *
     * @param  string $plugins_key
     * @return void
     */
    private function _maybe_delete_plugin_bundle_files(string $plugins_key): void {
        $pm = $this->manifest['plugins'][$plugins_key] ?? null;
        if (!$pm) return;
        foreach (['css', 'css_rtl', 'js'] as $k) {
            if (!empty($pm[$k]) && $this->file_exists_rel($pm[$k])) {
                $abs = rtrim(STATIC_PATH, '/') . '/' . ltrim($pm[$k], '/');
                @unlink($abs);
            }
        }
    }

    /**
     * Güncellenen plugin'leri içeren diğer bundle'ları yeniden oluşturur.
     *
     * @param  string[] $updated_plugins
     * @param  string   $skip_key
     * @return void
     */
    private function cascade_rebuild_bundles(array $updated_plugins, string $skip_key): void {
        if (empty($updated_plugins)) return;

        $affected_keys = [];
        foreach ($this->manifest['plugins'] as $pk => $pm) {
            if ($pk === $skip_key) continue;
            $list = $pm['plugins_list'] ?? [];
            if (empty($list)) continue;

            if (!empty(array_intersect($list, $updated_plugins))) {
                $check_files = [];
                foreach ($list as $p) {
                    $check_files[] = STATIC_PATH . 'js/plugins/' . $p . '.js';
                    $check_files[] = STATIC_PATH . 'js/plugins/' . $p . '-init.js';
                    $check_files[] = STATIC_PATH . 'js/plugins/' . $p . '.css';
                }
                $current_mtime = $this->source_files_mtime_hash($check_files);
                $expected_key  = sha1(json_encode($list) . '|' . $current_mtime);

                if ($expected_key !== $pk) {
                    $affected_keys[$pk] = [
                        'plugins_list' => $list,
                        'new_key'      => $expected_key,
                        'contents'     => $pm['contents'] ?? [],
                    ];
                }
            }
        }

        if (empty($affected_keys)) return;

        $this->error_log("[PAE] CASCADE: " . count($affected_keys) . " bundle yeniden oluşturulacak.", 'cascade');

        foreach ($affected_keys as $old_pk => $info) {
            $list        = $info['plugins_list'];
            $css_src     = [];
            $css_rtl_src = [];
            $js_src      = [];

            foreach ($list as $p) {
                $css_file = STATIC_PATH . 'js/plugins/' . $p . '.css';
                if (file_exists($css_file)) {
                    $css_src[]     = $css_file;
                    $css_rtl_src[] = STATIC_PATH . 'js/plugins/' . $p . '-rtl.css';
                }
                $js_src[] = STATIC_PATH . 'js/plugins/' . $p . '.js';
                $js_src[] = STATIC_PATH . 'js/plugins/' . $p . '-init.js';
            }

            $new_css     = !empty($css_src) ? str_replace(STATIC_URL, '', $this->combine_and_cache_files('css', $css_src)) : '';
            $new_css_rtl = !empty($css_rtl_src) ? str_replace(STATIC_URL, '', $this->combine_and_cache_files('css', $css_rtl_src)) : '';
            $js_src      = array_filter($js_src, 'file_exists');
            $new_js      = '';
            if (!empty($js_src)) {
                $new_js = str_replace(STATIC_URL, '', $this->combine_and_cache_files('js', array_values($js_src)));
                $this->combine_and_cache_modules($list, $new_js);
            }

            $new_key = $info['new_key'];
            $this->manifest['plugins'][$new_key] = [
                'css'          => $new_css,
                'css_rtl'      => $new_css_rtl,
                'js'           => $new_js,
                'plugins_list' => $list,
                'contents'     => $info['contents'],
            ];

            $this->_maybe_delete_plugin_bundle_files($old_pk);
            unset($this->manifest['plugins'][$old_pk]);

            foreach ($info['contents'] as $content_key) {
                $parts = explode(':', $content_key, 2);
                if (count($parts) !== 2 || !is_numeric($parts[1])) continue;
                [$ctx, $cid] = $parts;
                $meta = $this->meta_get($ctx, (int) $cid);
                if (!is_array($meta)) continue;
                $meta['plugin_js']       = $new_js;
                $meta['plugin_css']      = $new_css;
                $meta['plugin_css_rtl']  = $new_css_rtl;
                $this->meta_update($ctx, (int) $cid, $meta);
                if (isset($this->manifest['content_usage'][$content_key])) {
                    $this->manifest['content_usage'][$content_key]['plugins_key'] = $new_key;
                }
            }

            $this->error_log("[PAE] CASCADE DONE: old={$old_pk} → new={$new_key}", 'cascade');
        }

        $this->manifest_write();
    }

    // =========================================================
    //  YARDIMCILAR
    // =========================================================

    /**
     * Relative path'in STATIC_PATH'te var olup olmadığını kontrol eder.
     *
     * @param  string|null $rel
     * @return bool
     *
     * @example
     *   $exists = $this->file_exists_rel('css/cache/abc123.css');
     */
    private function file_exists_rel(?string $rel): bool {
        if (!$rel) return false;
        $rel = ltrim($rel, '/');
        $abs = rtrim(STATIC_PATH, '/') . '/' . $rel;
        return file_exists($abs) && is_file($abs);
    }

    /**
     * İçerik normalize eder (BOM, \r, yorum temizliği).
     *
     * @param  string $content
     * @param  string $type 'css' veya 'js'
     * @return string
     */
    private function normalize_content(string $content, string $type): string {
        if ($type === 'js') {
            $normalized = preg_replace("/\xEF\xBB\xBF/", '', $content);
            $normalized = str_replace("\r", '', $normalized);
            $normalized = preg_replace("/\n+/", "\n", $normalized);
            return trim($normalized);
        }

        $normalized = preg_replace("/\xEF\xBB\xBF/", '', $content);
        $normalized = str_replace("\r", '', $normalized);
        $normalized = preg_replace('!/\*.*?\*/!s', '', $normalized);
        $normalized = preg_replace('/[ \t]+/', ' ', $normalized);
        $normalized = preg_replace("/\n+/", "\n", $normalized);
        return trim($normalized);
    }

    /**
     * İçerik hash'i üretir.
     *
     * @param  string $content
     * @param  string $type
     * @return string MD5 hash
     */
    private function content_hash(string $content, string $type): string {
        return md5($this->normalize_content($content, $type));
    }
}
