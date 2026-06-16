<?php

/**
 * PAE ManagesMeta Trait
 *
 * Post, term, user, comment ve option meta CRUD işlemleri.
 * save_meta(), normalize_meta_type(), maybe_copy_meta_to_translations() içerir.
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
 *   Tüm meta okuma/yazma işlemleri bu trait üzerinden yapılır.
 *
 *   Desteklenen tipler: post, term, user, comment, archive, dynamic
 *   page, product gibi custom post type'lar → 'post' olarak normalize edilir.
 *
 * @example Meta oku:
 *   $assets = $extractor->meta_get('post', 123);
 *
 * @example Meta yaz:
 *   $extractor->meta_update('term', 45, $assets_array);
 *
 * @example Güvenli statik okuma (tema tarafından):
 *   $assets = PageAssetsExtractor::get_post_assets_safe($post_id);
 *
 * @example Polylang çevirilerine kopyala:
 *   // save_meta() içinde otomatik çağrılır
 *   $extractor->maybe_copy_meta_to_translations($post_id, $merged_data);
 *
 * @example Archive option kaydet:
 *   // save_meta() içinde otomatik — type='archive' ise option'a yazar
 *   $extractor->type = 'archive';
 *   $extractor->save_meta($result, 'product_archive_tr');
 */
trait ManagesMeta {

    // =========================================================
    //  NORMALIZE
    // =========================================================

    /**
     * Meta type normalize eder.
     * page, product gibi post type'lar → 'post' olarak işlenir.
     * Sadece term, user, comment, archive, dynamic ayrı kalır.
     *
     * @param  string $type
     * @return string Normalize edilmiş tip
     *
     * @example
     *   $this->normalize_meta_type('page');    // → 'post'
     *   $this->normalize_meta_type('product'); // → 'post'
     *   $this->normalize_meta_type('term');    // → 'term'
     */
    private function normalize_meta_type(string $type): string {
        if (in_array($type, ['term', 'user', 'comment', 'archive', 'dynamic'], true)) {
            return $type;
        }
        return 'post';
    }

    // =========================================================
    //  CRUD
    // =========================================================

    /**
     * Meta değerini okur.
     *
     * @param  string     $type  post|term|user|comment
     * @param  int|string $id
     * @return mixed|null
     *
     * @example
     *   $assets = $this->meta_get('post', 123);
     *   $assets = $this->meta_get('term', 45);
     */
    private function meta_get(string $type, $id) {
        $type = $this->normalize_meta_type($type);
        switch ($type) {
            case 'post':    return get_post_meta($id, self::META_KEY, true);
            case 'term':    return get_term_meta($id, self::META_KEY, true);
            case 'user':    return get_user_meta($id, self::META_KEY, true);
            case 'comment': return get_comment_meta($id, self::META_KEY, true);
        }
        return null;
    }

    /**
     * Meta değerini günceller.
     *
     * @param  string     $type
     * @param  int|string $id
     * @param  mixed      $val
     * @return void
     *
     * @example
     *   $this->meta_update('post', 123, $assets);
     */
    private function meta_update(string $type, $id, $val): void {
        $type = $this->normalize_meta_type($type);
        switch ($type) {
            case 'post':    update_post_meta($id, self::META_KEY, $val);    break;
            case 'term':    update_term_meta($id, self::META_KEY, $val);    break;
            case 'user':    update_user_meta($id, self::META_KEY, $val);    break;
            case 'comment': update_comment_meta($id, self::META_KEY, $val); break;
        }
    }

    /**
     * Meta değerini ekler (unique).
     *
     * @param  string     $type
     * @param  int|string $id
     * @param  mixed      $val
     * @return void
     *
     * @example
     *   $this->meta_add('post', 123, $assets);
     */
    private function meta_add(string $type, $id, $val): void {
        $type = $this->normalize_meta_type($type);
        switch ($type) {
            case 'post':    add_post_meta($id, self::META_KEY, $val, true);    break;
            case 'term':    add_term_meta($id, self::META_KEY, $val, true);    break;
            case 'user':    add_user_meta($id, self::META_KEY, $val, true);    break;
            case 'comment': add_comment_meta($id, self::META_KEY, $val, true); break;
        }
    }

    /**
     * Meta değerini siler.
     *
     * @param  string     $type
     * @param  int|string $id
     * @return void
     *
     * @example
     *   $this->meta_delete('post', 123);
     */
    private function meta_delete(string $type, $id): void {
        $type = $this->normalize_meta_type($type);
        switch ($type) {
            case 'post':    delete_post_meta($id, self::META_KEY);    break;
            case 'term':    delete_term_meta($id, self::META_KEY);    break;
            case 'user':    delete_user_meta($id, self::META_KEY);    break;
            case 'comment': delete_comment_meta($id, self::META_KEY); break;
        }
    }

    // =========================================================
    //  SAVE META (ana kayıt metodu)
    // =========================================================

    /**
     * Asset sonuçlarını uygun meta/option'a kaydeder.
     * Archive/dynamic → WP option, diğerleri → post/term/user/comment meta.
     * Polylang aktifse çevirilere de kopyalar.
     *
     * @param  array      $result  extract_assets() sonucu
     * @param  int|string $id
     * @return array|false Kaydedilen merged data veya false
     *
     * @example
     *   $merged = $extractor->save_meta($result, $post_id);
     *
     * @example Archive:
     *   $extractor->type = 'archive';
     *   $extractor->save_meta($result, 'product_archive_tr');
     */
    public function save_meta(array $result, $id) {
        $this->error_log("save_meta START: id={$id} | type={$this->type}", 'meta');

        if (!$id || !$this->type) {
            $this->error_log("save_meta SKIP: id={$id} type={$this->type}", 'meta');
            return false;
        }

        if (!empty($this->structure_fp)) {
            if (!is_array($result)) $result = [];
            $result['structure_fp'] = $this->structure_fp;
        }

        $default_lcp = ['desktop' => [], 'mobile' => []];

        // ── ARCHIVE / DYNAMIC → option ──────────────────────────────
        if ($this->type === 'archive' || $this->type === 'dynamic') {
            $base_option_name = $id . '_assets';
            $default_lang     = $this->pae_lang_default();
            $archive_lang     = $this->get_content_lang($id);

            if (!isset($result['meta'])) {
                $result['meta'] = ['type' => $this->type, 'id' => $id];
            }
            if (!isset($result['lcp'])) {
                $result['lcp'] = $default_lcp;
            }

            $merged = $result;

            if ($archive_lang == $default_lang) {
                $lang_list = $this->pae_lang_list();
                foreach ($lang_list as $lang_item) {
                    $current_merged      = $result;
                    $current_option_name = str_replace(
                        "_archive_{$default_lang}_assets",
                        "_archive_{$lang_item}_assets",
                        $base_option_name
                    );
                    if ($current_option_name === $base_option_name && $lang_item !== $default_lang) {
                        $current_option_name = preg_replace('/_' . $default_lang . '_/', '_' . $lang_item . '_', $base_option_name);
                    }
                    if ($this->is_lang_rtl($lang_item) && !empty($current_merged['css'])) {
                        $current_merged['css'] = $this->flip_css_rtl($current_merged['css']);
                    }
                    $existing_opt = get_option($current_option_name, null);
                    if ($existing_opt !== null) {
                        update_option($current_option_name, $current_merged);
                    } else {
                        add_option($current_option_name, $current_merged);
                    }
                    $this->error_log("ARCHIVE AUTO-SYNC | Lang: {$lang_item} | Key: {$current_option_name}");
                }
            } else {
                if ($this->is_lang_rtl($archive_lang) && !empty($merged['css'])) {
                    $merged['css'] = $this->flip_css_rtl($merged['css']);
                }
                $existing_opt = get_option($base_option_name, null);
                if ($existing_opt !== null) {
                    update_option($base_option_name, $merged);
                } else {
                    add_option($base_option_name, $merged);
                }
            }

            return $merged;
        }

        // ── POST / TERM / USER / COMMENT → meta ─────────────────────
        $existing_raw = $this->meta_get($this->type, $id);
        $existing     = is_array($existing_raw) ? $existing_raw : [];

        if (isset($existing['meta']) && is_array($existing['meta'])) {
            $result['meta'] = $existing['meta'];
        } else {
            $result['meta'] = ['type' => $this->type, 'id' => $id];
        }

        if (!isset($result['lcp'])) {
            $result['lcp'] = (isset($existing['lcp']) && is_array($existing['lcp']))
                ? $existing['lcp']
                : $default_lcp;
        }

        $merged = array_replace_recursive(self::ASSETS_STRUCTURE, $existing, $result);

        if (!empty($existing_raw) || $existing_raw === '0') {
            $this->meta_update($this->type, $id, $merged);
        } else {
            $this->meta_add($this->type, $id, $merged);
        }

        if ($this->type === 'post' && !$this->mass) {
            $this->save_post_terms($id);
        }

        $this->disable_hooks = false;
        $this->error_log("META SAVED | type={$this->type} id={$id} | css_page=" . ($merged['css_page'] ?? '') . " | plugin_js=" . ($merged['plugin_js'] ?? ''));

        $this->maybe_copy_meta_to_translations($id, $merged);

        return $merged;
    }

    // =========================================================
    //  POLYLANG ÇEVİRİ KOPYALAMA
    // =========================================================

    /**
     * Polylang aktifse default dil meta'sını diğer dillere kopyalar.
     * RTL diller için CSS flip uygulanır.
     *
     * @param  int|string $id
     * @param  array      $merged
     * @return void
     *
     * @example
     *   // save_meta() içinde otomatik çağrılır
     *   $this->maybe_copy_meta_to_translations(123, $merged);
     */
    private function maybe_copy_meta_to_translations($id, array $merged): void {
        if (!function_exists('pll_default_language')) return;

        try {
            $default = pll_default_language();

            // POST
            if ($this->type === 'post' && function_exists('pll_get_post_language')) {
                $lang = pll_get_post_language($id);
                if ($lang !== $default) return;

                $translations = pll_get_post_translations($id);
                if (!is_array($translations)) return;

                if (isset($GLOBALS['polylang'])) {
                    remove_action('save_post', [$GLOBALS['polylang']->sync_post, 'save_post'], 10);
                }

                $prev = $this->disable_hooks;
                $this->disable_hooks = true;

                foreach ($translations as $l => $pid) {
                    if (!$pid || (int) $pid === (int) $id || $l === $default) continue;
                    $current_data = $merged;
                    if ($this->is_lang_rtl($l) && !empty($current_data['css'])) {
                        $current_data['css'] = $this->flip_css_rtl($current_data['css']);
                    }
                    delete_post_meta((int) $pid, self::META_KEY);
                    $res = add_post_meta((int) $pid, self::META_KEY, $current_data, true);
                    if (!$res) {
                        update_post_meta((int) $pid, self::META_KEY, $current_data);
                    }
                }

                $this->disable_hooks = $prev;
                if (isset($GLOBALS['polylang'])) {
                    add_action('save_post', [$GLOBALS['polylang']->sync_post, 'save_post'], 10, 3);
                }
                return;
            }

            // TERM
            if ($this->type === 'term' && function_exists('pll_get_term_translations')) {
                $translations = pll_get_term_translations($id);
                if (!is_array($translations)) return;

                $current_lang = function_exists('pll_get_term_language') ? pll_get_term_language($id) : '';
                if ($current_lang !== $default) return;

                $prev = $this->disable_hooks;
                $this->disable_hooks = true;

                foreach ($translations as $l => $tid) {
                    if (!$tid || (int) $tid === (int) $id) continue;
                    $current_data = $merged;
                    if ($this->is_lang_rtl($l) && !empty($current_data['css'])) {
                        $current_data['css'] = $this->flip_css_rtl($current_data['css']);
                    }
                    update_term_meta((int) $tid, self::META_KEY, $current_data);
                }

                $this->disable_hooks = $prev;
            }
        } catch (\Throwable $e) {
            $this->error_log('[PAE] maybe_copy_meta_to_translations error: ' . $e->getMessage());
        }
    }

    // =========================================================
    //  STATIK YARDIMCI
    // =========================================================

    /**
     * Tema tarafından güvenli post asset okuma.
     * Meta yoksa site_assets option'ına fallback yapar.
     *
     * @param  int $post_id
     * @return array|null
     *
     * @example PHP:
     *   $assets = PageAssetsExtractor::get_post_assets_safe($post_id);
     *   if ($assets) { echo $assets['css_page']; }
     *
     * @example Twig (theme.php üzerinden context'e geçirilir):
     *   {{ site_assets.css_page }}
     */
    public static function get_post_assets_safe(int $post_id) {
        $assets = function_exists('get_post_meta')
            ? get_post_meta($post_id, self::META_KEY, true)
            : null;

        if ($assets) return $assets;

        return function_exists('get_option') ? get_option('site_assets') : null;
    }

    // =========================================================
    //  TERM ASSET KAYDETME (shutdown'da)
    // =========================================================

    /**
     * Post'a bağlı term'lerin asset'lerini shutdown'da günceller.
     * Admin response'unu bloklamaz.
     *
     * @param  int $post_id
     * @return array Boş array (shutdown'da çalışır)
     *
     * @example
     *   // save_meta() içinde otomatik çağrılır
     *   $this->save_post_terms($post_id);
     */
    public function save_post_terms(int $post_id): array {
        if (!function_exists('get_post') || !get_post($post_id)) {
            return [];
        }

        $pt          = function_exists('get_post_type') ? get_post_type($post_id) : '';
        $tax_objects = function_exists('get_object_taxonomies')
            ? get_object_taxonomies($pt, 'objects')
            : [];

        if (empty($tax_objects)) return [];

        add_action('shutdown', function() use ($post_id, $tax_objects) {
            $prev                = $this->disable_hooks;
            $this->disable_hooks = true;

            try {
                foreach ($tax_objects as $taxonomy => $details) {
                    if (empty($details->public)) continue;
                    $terms = function_exists('get_the_terms') ? get_the_terms($post_id, $taxonomy) : [];
                    if (empty($terms) || is_wp_error($terms)) continue;
                    foreach ($terms as $term) {
                        $this->type = 'term';
                        $this->fetch_term_url($term->term_id, $taxonomy);
                    }
                }
            } catch (\Throwable $e) {
                $this->error_log('save_post_terms error: ' . $e->getMessage());
            } finally {
                $this->disable_hooks = $prev;
            }
        }, 99);

        return [];
    }
}
