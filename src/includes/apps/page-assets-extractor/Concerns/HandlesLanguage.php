<?php

/**
 * HandlesLanguage — PAE Dil Yönetimi Trait
 *
 * Çok dilli site desteği için dil tespiti, RTL CSS dönüşümü ve
 * arşiv URL üretimi işlemlerini kapsar.
 *
 * @package    SaltHareket\Theme\PageAssetsExtractor\Concerns
 * @version    1.0.0
 * @since      1.0.0
 * @author     SaltHareket
 *
 * @changelog
 *   1.0.0 - 2026-05-03
 *     - Refactor: class.page-assets-extractor.php'den ayrıldı
 *     - Add: CODING_PRINCIPLES uyumlu dokümantasyon eklendi
 *
 * HOW TO USE:
 *   Bu trait PageAssetsExtractor sınıfı içinde `use HandlesLanguage;` ile kullanılır.
 *   Polylang entegrasyonu, RTL CSS flip ve çok dilli arşiv URL üretimi sağlar.
 *
 *   TEMEL KULLANIM:
 *   - Varsayılan dili al: $this->pae_lang_default()
 *   - Dil listesini al: $this->pae_lang_list()
 *   - URL'den dil çıkar: $this->pae_lang_from_url($url)
 *   - İçerik dilini al: $this->get_content_lang($id, 'post')
 *   - RTL kontrolü: $this->is_lang_rtl('ar')
 *   - CSS RTL çevir: $this->flip_css_rtl($css)
 *   - Arşiv URL'lerini al: $this->get_post_type_archive_urls_all_lang('product')
 *   - Arşiv asset'lerini kaydet: $this->fetch_and_save_archives_assets('product')
 *
 * @example Varsayılan dili al:
 *   $default = $this->pae_lang_default(); // 'tr'
 *
 * @example URL'den dil çıkar:
 *   $lang = $this->pae_lang_from_url('https://example.com/en/about'); // 'en'
 *
 * @example İçerik dilini al (post):
 *   $lang = $this->get_content_lang(123, 'post'); // 'tr'
 *
 * @example RTL kontrolü:
 *   if ($this->is_lang_rtl('ar')) {
 *       $css = $this->flip_css_rtl($css);
 *   }
 *
 * @example Arşiv URL'lerini tüm diller için üret:
 *   $urls = $this->get_post_type_archive_urls_all_lang('product');
 *   // [['lang' => 'tr', 'url' => 'https://example.com/urunler/'], ...]
 *
 * @example Arşiv asset'lerini kaydet:
 *   $this->fetch_and_save_archives_assets('post');
 */

trait HandlesLanguage
{

    // =========================================================
    // ÖZEL YARDIMCILAR
    // =========================================================

    /**
     * Varsayılan dil kodunu döndürür.
     *
     * @return string Varsayılan dil kodu (örn. 'tr')
     */
    private function pae_lang_default(): string
    {
        return \Data::has('language_default') ? (string) \Data::get('language_default') : '';
    }

    // =========================================================

    /**
     * Sistemdeki tüm dil kodlarını liste olarak döndürür.
     * Data::get('languages') yoksa SaltHareket\Theme üzerinden yükler.
     *
     * @return string[] Dil kodu listesi (örn. ['tr', 'en', 'ar'])
     */
    private function pae_lang_list(): array
    {
        static $cached = null;
        if ( $cached !== null ) return $cached;

        if (\Data::has('languages') && is_array(\Data::get('languages'))) {
            $names  = array_column(\Data::get('languages'), 'name');
            $cached = array_values(array_filter(array_map('strval', $names)));
            return $cached;
        } else {
            if (class_exists('SaltHareket\Theme')) {
                $theme = \SaltHareket\Theme::getInstance();
                if (ENABLE_MULTILANGUAGE) {
                    $theme->language_settings();
                } else {
                    $theme->language_settings_basic();
                }
                // Tekrar oku — language_settings() Data::set('languages') yapmış olabilir
                if (\Data::has('languages') && is_array(\Data::get('languages'))) {
                    $names  = array_column(\Data::get('languages'), 'name');
                    $cached = array_values(array_filter(array_map('strval', $names)));
                    return $cached;
                }
            }
        }
        $cached = $this->pae_lang_default() ? [$this->pae_lang_default()] : [];
        return $cached;
    }

    // =========================================================

    /**
     * URL'den dil kodunu çıkarır (path'in her segmentinde arar).
     *
     * @param  string $url Kontrol edilecek URL
     * @return string Bulunan dil kodu veya varsayılan dil
     */
    private function pae_lang_from_url(string $url): string
    {
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

    // =========================================================

    /**
     * Verilen URL'nin varsayılan dil URL'si olup olmadığını kontrol eder.
     *
     * @param  string $url Kontrol edilecek URL
     * @return bool
     */
    private function pae_is_default_lang_url(string $url): bool
    {
        $def = strtolower($this->pae_lang_default());
        return $def && (strtolower($this->pae_lang_from_url($url)) === $def);
    }

    // =========================================================
    // PUBLIC METODLAR
    // =========================================================

    /**
     * İçeriğin tipine ve ID'sine göre dil kodunu tespit eder.
     * Polylang entegrasyonu gerektirir (ENABLE_MULTILANGUAGE === 'polylang').
     *
     * @param  int|string  $id   Post/term/user/comment ID
     * @param  string|null $type İçerik tipi ('post', 'term', 'archive', 'dynamic', 'user', 'comment')
     * @return string Dil kodu (örn. 'tr', 'en', 'ar') veya boş string
     */
    public function get_content_lang($id, $type = null): string
    {
        static $lang_cache = [];
        $type     = $type ?: $this->type;
        $cache_key = $type . ':' . $id;
        if ( isset( $lang_cache[ $cache_key ] ) ) return $lang_cache[ $cache_key ];
        $lang = '';

        if (defined('ENABLE_MULTILANGUAGE') && ENABLE_MULTILANGUAGE === 'polylang') {

            // 0. DYNAMIC (Format: {$post_type}_{$lang})
            if ($type === 'dynamic') {
                $parts = explode('_', $id);
                $lang  = end($parts);
            }
            // 1. ARCHIVE (Format: {$post_type}_archive_{$lang})
            if ($type === 'archive') {
                $parts = explode('_', $id);
                $lang  = end($parts);
            }
            // 2. POST (Page, Post, CPT)
            elseif ($type === 'post' && function_exists('pll_get_post_language')) {
                $lang = pll_get_post_language($id);
            }
            // 3. TERM (Category, Tag, Tax)
            elseif ($type === 'term' && function_exists('pll_get_term_language')) {
                $lang = pll_get_term_language($id);
            }
            // 4. USER
            elseif ($type === 'user') {
                if (function_exists('pll_get_member_languages')) {
                    $lang = pll_current_language();
                } else {
                    $lang = get_user_meta($id, 'description', true); // Fallback
                }
            }
            // 5. COMMENT
            elseif ($type === 'comment') {
                $comment = get_comment($id);
                if ($comment && function_exists('pll_get_post_language')) {
                    $lang = pll_get_post_language($comment->comment_post_ID);
                }
            }
        }

        $result = !empty($lang) ? strtolower($lang) : '';
        $lang_cache[ $cache_key ] = $result;
        return $result;
    }

    // =========================================================

    /**
     * Verilen dil kodunun RTL (sağdan sola) olup olmadığını kontrol eder.
     *
     * @param  string $lang Dil kodu (örn. 'ar', 'he', 'fa')
     * @return bool
     */
    public function is_lang_rtl($lang): bool
    {
        if (empty($lang)) return false;

        $rtl_codes = [
            'ar', 'ara', 'ary', 'arz', // Arapça varyantları
            'fa', 'per', 'fas', 'jpr', // Farsça varyantları
            'ur', 'urd',               // Urduca
            'he', 'heb', 'iw',         // İbranice
            'ps', 'pus',               // Peştuca
            'sd', 'snd',               // Sindhi
            'ku', 'kur', 'ckb',        // Kürtçe (Sorani)
            'ug', 'uig',               // Uygurca
            'dv', 'div',               // Dhivehi
            'yi', 'yid',               // Yidiş
        ];

        $lang = strtolower($lang);
        foreach ($rtl_codes as $code) {
            if (strpos($lang, $code) === 0) {
                return true;
            }
        }
        return false;
    }

    // =========================================================

    /**
     * CSS stringini RTL'ye çevirir (Sabberworm CSS Parser + RTLParser kullanır).
     *
     * @param  string $css Çevrilecek CSS içeriği
     * @return string RTL'ye çevrilmiş CSS veya hata durumunda orijinal CSS
     */
    private function flip_css_rtl($css): string
    {
        if (empty($css)) return $css;
        try {
            $parser = new \Sabberworm\CSS\Parser($css);
            $tree   = $parser->parse();
            $rtlcss = new \Irmmr\RTLCss\Parser($tree);
            $rtlcss->flip();
            return $tree->render();
        } catch (\Throwable $e) {
            $this->error_log('[PAE] CSS RTL Flip Error: ' . $e->getMessage());
            return $css; // Hata olursa orijinali bozma
        }
    }

    // =========================================================

    /**
     * Sistem çok dilli ise tüm diller için arşiv URL'lerini üretir.
     * Varsayılan dil prefixsiz, diğer diller '/{lang}/' prefixi ile.
     *
     * @param  string $post_type Post type adı (örn. 'product', 'post')
     * @return array  [['lang' => 'tr', 'url' => 'https://...'], ...]
     */
    private function get_post_type_archive_urls_all_lang($post_type): array
    {
        $pto = get_post_type_object($post_type);
        if (!$pto || empty($pto->has_archive)) {
            $this->error_log("[PAE] {$post_type} has_archive=false veya post type bulunamadı");
            return [];
        }

        // WooCommerce: product archive URL'i rewrite slug değil, shop page permalink'i
        // wc_get_page_permalink('shop') doğru URL'i döndürür (ör. /shop/)
        if ( $post_type === 'product' && function_exists('wc_get_page_permalink') ) {
            $shop_url = wc_get_page_permalink('shop');
            if ( $shop_url && ! is_wp_error($shop_url) ) {
                // home_url prefix'ini kaldır, sadece path kısmını al
                // ör: http://localhost/salthub/shop/ → shop
                $home_path = rtrim( parse_url( home_url(), PHP_URL_PATH ) ?: '', '/' );
                $shop_path = rtrim( parse_url( $shop_url, PHP_URL_PATH ) ?: '', '/' );
                // home path'i çıkar: /salthub/shop → /shop → shop
                if ( $home_path && strpos( $shop_path, $home_path ) === 0 ) {
                    $shop_path = substr( $shop_path, strlen( $home_path ) );
                }
                $slug = trim( $shop_path, '/' );
                $this->error_log("[PAE] product archive: using WC shop URL slug={$slug}");
            } else {
                $slug = isset($pto->rewrite['slug']) ? trim($pto->rewrite['slug'], '/') : trim($post_type, '/');
            }
        } else {
            $slug = isset($pto->rewrite['slug']) ? trim($pto->rewrite['slug'], '/') : trim($post_type, '/');
        }
        $langs   = (\Data::has('languages') && is_array(\Data::get('languages'))) ? \Data::get('languages') : [];
        $default = \Data::has('language_default') ? (string) \Data::get('language_default') : '';

        if (!$langs) {
            $langs = [['name' => $default ?: '']];
        }

        $urls = [];
        foreach ($langs as $lang_data) {
            $lang = isset($lang_data['name']) ? (string) $lang_data['name'] : '';

            if ($lang && $lang !== $default) {
                $base = rtrim($this->home_url, '/') . '/' . $lang . '/';
            } else {
                $base = rtrim($this->home_url, '/') . '/';
            }

            $url    = $base . $slug . '/';
            $urls[] = [
                'lang' => ($lang ?: $default ?: 'default'),
                'url'  => $url,
            ];

            $this->error_log("[PAE] base archive url={$url} lang=" . ($lang ?: $default ?: 'default'));
        }

        return $urls;
    }

    // =========================================================

    /**
     * Verilen post type için tüm dillerdeki arşiv sayfalarını fetch eder ve asset'leri kaydeder.
     *
     * @param  string $post_type Post type adı (örn. 'product', 'post')
     * @return void
     */
    private function fetch_and_save_archives_assets($post_type): void
    {
        $archives = $this->get_post_type_archive_urls_all_lang($post_type);
        $this->error_log('[PAE] archive urls count=' . count($archives));
        if (!$archives) {
            $this->error_log('[PAE] NO ARCHIVE URLS (has_archive false olabilir ya da rewrite yok)');
            return;
        }
        foreach ($archives as $item) {
            $lang = $item['lang'];
            $url  = $item['url'];

            $this->fetch($url, "{$post_type}_archive_{$lang}", 'archive');
            // save_meta() archive için zaten "{$id}_assets" option'ına yazar.
        }
    }
}
