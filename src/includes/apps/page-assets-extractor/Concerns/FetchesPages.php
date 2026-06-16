<?php

/**
 * PAE FetchesPages Trait
 *
 * HTTP fetch, admin cookie yönetimi, dual fetch (logged + unlogged) ve
 * heavy process lock mekanizması.
 *
 * @package    SaltHareket\Theme\PageAssetsExtractor
 * @version    1.0.0
 * @since      1.9.7
 *
 * @changelog
 *   1.0.0 - 2026-05-03
 *     - Refactor: class.page-assets-extractor.php'den ayrıldı
 *     - Add: CODING_PRINCIPLES uyumlu dokümantasyon
 *     - Add: Dual fetch — logged + unlogged HTML merge
 *     - Add: get_forced_dual_fetch_ids(), get_optional_dual_fetch_ids(), is_dual_fetch(), set_dual_fetch()
 *
 * HOW TO USE:
 *   Bu trait PageAssetsExtractor sınıfı içinde kullanılır.
 *   Dışarıdan doğrudan çağrılmaz.
 *
 *   Dual fetch: Cart, Checkout, My Account, Order Received sayfaları
 *   zorunlu olarak hem logged hem unlogged state'te fetch edilir.
 *   Diğer sayfalar PAE admin tablosundan toggle edilebilir.
 *
 * @example Sayfa fetch et:
 *   $result = $extractor->fetch('https://site.com/urun/mascara/', 123, 'post');
 *
 * @example Post URL'ini fetch et:
 *   $result = $extractor->fetch_post_url(123);
 *
 * @example Term URL'ini fetch et:
 *   $result = $extractor->fetch_term_url(45, 'product_cat');
 *
 * @example Dual fetch aktif mi kontrol et:
 *   $is_dual = $extractor->is_dual_fetch(8428); // checkout page ID
 *
 * @example Opsiyonel dual fetch toggle:
 *   $extractor->set_dual_fetch(999, true);  // aç
 *   $extractor->set_dual_fetch(999, false); // kapat
 *
 * @example Zorunlu dual fetch ID'leri:
 *   $ids = $extractor->get_forced_dual_fetch_ids();
 *   // [8427, 8428, 8425, 8430] — cart, checkout, myaccount, order-received
 */
trait FetchesPages {

    // =========================================================
    //  URL FETCH
    // =========================================================

    /**
     * Post URL'ini fetch eder.
     *
     * @param  int $post_id
     * @return array|false
     *
     * @example
     *   $result = $extractor->fetch_post_url(123);
     */
    public function fetch_post_url(int $post_id) {
        $url       = function_exists('get_permalink') ? get_permalink($post_id) : '';
        $this->url = $url;
        $this->error_log("fetch_post_url : " . json_encode($url));
        return $this->fetch($url, $post_id, 'post');
    }

    /**
     * Term URL'ini fetch eder.
     *
     * @param  int    $term_id
     * @param  string $taxonomy
     * @return array|false
     *
     * @example
     *   $result = $extractor->fetch_term_url(45, 'product_cat');
     */
    public function fetch_term_url(int $term_id, string $taxonomy) {
        $term      = function_exists('get_term') ? get_term($term_id, $taxonomy) : null;
        $url       = function_exists('get_term_link') ? get_term_link($term) : '';
        $this->url = $url;
        $this->error_log("fetch_term_url : {$url}");
        if (!function_exists('is_wp_error') || !is_wp_error($url)) {
            return $this->fetch($url, $term_id, 'term');
        }
        return false;
    }

    /**
     * Verilen URL'i fetch eder, HTML'i parse eder ve asset'leri çıkarır.
     * Dual fetch aktifse hem logged hem unlogged HTML merge edilir.
     *
     * @param  string     $url
     * @param  int|string $id
     * @param  string|null $forceType
     * @return array|false
     *
     * @example
     *   $result = $extractor->fetch('https://site.com/sepet/', 8427, 'page');
     */
    public function fetch(string $url, $id, ?string $forceType = null) {
        $fetch_start = microtime(true);
        $this->error_log("fetch START: url={$url} | id={$id} | type={$forceType}", 'fetch');

        // Auth cookies — logged user CSS class'larının yakalanması için
        if (empty($this->auth_cookies)) {
            $this->auth_cookies = $this->get_admin_cookies();
        }

        if (!$this->start_heavy_process()) {
            $this->error_log('fetch SKIP: heavy process lock active', 'fetch');
            return false;
        }

        // Sadece site içi URL'leri fetch et
        $parsed_url = parse_url($url);
        $wp_domain  = parse_url(home_url(), PHP_URL_HOST);
        if (($parsed_url['host'] ?? '') !== $wp_domain) {
            $this->error_log('[PAE] fetch SKIP: domain not whitelisted: ' . ($parsed_url['host'] ?? ''));
            return false;
        }

        $prevType = $this->type;
        if ($forceType) $this->type = $forceType;

        if ($this->acquire_lock($id) === false) {
            $this->error_log("[PAE] fetch SKIP (lock) id={$id}");
            if ($forceType) $this->type = $prevType;
            return false;
        }

        try {
            $fetch_url = (!empty($url) && is_string($url))
                ? $url . (strpos($url, '?') === false ? '?fetch&nocache=true' : '&fetch&nocache=true')
                : '?fetch&nocache=true';

            $this->error_log("[PAE] fetch URL={$fetch_url} | id={$id} | type={$this->type}");

            if (function_exists('get_page_status')) {
                $st = @get_page_status($fetch_url);
                $this->error_log("[PAE] get_page_status={$st} for {$fetch_url}");
                if ($st != 200) {
                    if ($this->type !== 'dynamic') {
                        $this->error_log("PAE: Fetch Error! Status: {$st} for URL: {$fetch_url}");
                        return false;
                    }
                    if ($st >= 500) {
                        $this->error_log("PAE: Server Error! Status: {$st} for URL: {$fetch_url}");
                        return false;
                    }
                }
            }

            $this->url = $fetch_url;

            $response = wp_remote_get($fetch_url, [
                'timeout'     => 20,
                'headers'     => [
                    'User-Agent'       => 'MyFetchBot/1.0',
                    'X-Internal-Fetch' => '1',
                ],
                'httpversion' => '1.1',
                'cookies'     => $this->auth_cookies ?? [],
            ]);

            $html_content = null;

            if (is_wp_error($response)) {
                $this->error_log('PAE wp_remote_get ERROR: ' . $response->get_error_message());
            } else {
                $html_raw     = wp_remote_retrieve_body($response);
                $this->error_log('PAE wp_remote_get BODY LEN: ' . strlen($html_raw));
                $html_content = \voku\helper\HtmlDomParser::str_get_html($html_raw);
            }

            if (!$html_content) {
                $this->error_log('[PAE] file_get_html FAILED');
                return false;
            }

            $this->html = $html_content;

            // ── Dual fetch: logged + anon HTML merge ──────────────────
            if ($this->is_dual_fetch(is_numeric($id) ? (int) $id : 0)) {
                $anon_cookies       = $this->auth_cookies;
                $this->auth_cookies = [];

                $anon_response = wp_remote_get($fetch_url, [
                    'timeout'     => 20,
                    'headers'     => ['User-Agent' => 'MyFetchBot/1.0', 'X-Internal-Fetch' => '1'],
                    'httpversion' => '1.1',
                    'cookies'     => [],
                ]);

                $this->auth_cookies = $anon_cookies;

                if (!is_wp_error($anon_response)) {
                    $anon_html_raw = wp_remote_retrieve_body($anon_response);
                    if (!empty($anon_html_raw)) {
                        $merged_html_raw = wp_remote_retrieve_body($response) . "\n<!-- PAE_DUAL_ANON -->\n" . $anon_html_raw;
                        $merged_dom      = \voku\helper\HtmlDomParser::str_get_html($merged_html_raw);
                        if ($merged_dom) {
                            $html_content = $merged_dom;
                            $this->html   = $html_content;
                            $this->error_log('[PAE] dual fetch merged: logged + anon HTML', 'fetch');
                        }
                    }
                }
            }

            // ── Asset çıkarma ─────────────────────────────────────────
            $result = $this->extract_assets($html_content, $id);
            $this->error_log("[PAE] extract_assets DONE type={$this->type} id={$id}");

            // ── CSP domain tespiti ────────────────────────────────────
            $this->detect_and_save_csp_domains($html_content, $response);

            if (is_numeric($id) && function_exists('get_post') && get_post($id)) {
                $this->type = 'post';
            }

            if ($this->type === 'post') {
                $this->build_related_assets($id, $this->detect_post_type($id));
            }

            return $result;

        } finally {
            $this->release_lock($id);
            $elapsed = round((microtime(true) - $fetch_start) * 1000, 2);
            $this->error_log("[PAE] fetch EXIT id={$id} | {$elapsed}ms");
            if ($forceType) $this->type = $prevType;
            $this->end_heavy_process();
        }
    }

    // =========================================================
    //  TOPLU FETCH
    // =========================================================

    /**
     * Tüm URL'leri sırayla fetch eder.
     *
     * @return array
     *
     * @example
     *   $results = $extractor->fetch_all();
     */
    public function fetch_all(): array {
        $this->mass = true;
        $urls       = $this->get_all_urls();
        $results    = [];
        try {
            foreach ($urls as $id => $row) {
                $results[$row['url']] = $this->fetch($row['url'], $id, $row['type']);
            }
        } finally {
            $this->end_heavy_process();
            $this->mass = false;
        }
        return $results;
    }

    /**
     * Verilen URL listesini sırayla fetch eder.
     *
     * @param  array $urls
     * @return array
     *
     * @example
     *   $results = $extractor->fetch_urls($urls);
     */
    public function fetch_urls(array $urls): array {
        $this->mass = true;
        $results    = [];
        try {
            foreach ($urls as $id => $row) {
                $results[$row['url']] = $this->fetch($row['url'], $id, $row['type']);
            }
        } finally {
            $this->end_heavy_process();
            $this->mass = false;
        }
        return $results;
    }

    // =========================================================
    //  AUTH COOKIES
    // =========================================================

    /**
     * Mevcut request'teki WordPress auth cookie'lerini döndürür.
     * PAE admin sayfasından fetch yapılırken admin session kullanılır.
     *
     * @return \WP_Http_Cookie[]
     *
     * @example
     *   $this->auth_cookies = $this->get_admin_cookies();
     */
    private function get_admin_cookies(): array {
        $cookies = [];
        foreach ($_COOKIE as $name => $value) {
            if (strpos($name, 'wordpress_logged_in') === 0 || strpos($name, 'wordpress_sec') === 0) {
                $cookies[] = new \WP_Http_Cookie(['name' => $name, 'value' => $value]);
            }
        }
        return $cookies;
    }

    // =========================================================
    //  DUAL FETCH
    // =========================================================

    /**
     * Zorunlu dual fetch sayfa ID'lerini döndürür.
     * Cart, Checkout, My Account, Order Received — değiştirilemez.
     *
     * @return int[]
     *
     * @example
     *   $ids = $extractor->get_forced_dual_fetch_ids();
     *   // [8427, 8428, 8425, 8430]
     */
    public function get_forced_dual_fetch_ids(): array {
        static $cache = null;
        if ($cache !== null) return $cache;

        $ids = [];
        if (function_exists('wc_get_page_id')) {
            foreach (['cart', 'checkout', 'myaccount'] as $key) {
                $id = (int) wc_get_page_id($key);
                if ($id > 0) $ids[] = $id;
            }
        }

        $order_received_id = (int) get_option('woocommerce_order_received_page_id');
        if ($order_received_id > 0) $ids[] = $order_received_id;

        $cache = array_unique(array_filter($ids));
        return $cache;
    }

    /**
     * Opsiyonel dual fetch sayfa ID'lerini döndürür.
     * Kullanıcı PAE tablosundan toggle edebilir.
     *
     * @return int[]
     *
     * @example
     *   $ids = $extractor->get_optional_dual_fetch_ids();
     */
    public function get_optional_dual_fetch_ids(): array {
        return (array) get_option('pae_dual_fetch_pages', []);
    }

    /**
     * Verilen ID için dual fetch gerekli mi kontrol eder.
     *
     * @param  int|string $id
     * @return bool
     *
     * @example
     *   if ($extractor->is_dual_fetch(8428)) { // checkout
     *       // dual fetch yapılacak
     *   }
     */
    public function is_dual_fetch(int|string $id): bool {
        $int_id = is_numeric($id) ? (int) $id : 0;
        if ($int_id > 0 && in_array($int_id, $this->get_forced_dual_fetch_ids(), true)) return true;
        if ($int_id > 0 && in_array($int_id, $this->get_optional_dual_fetch_ids(), true)) return true;
        return false;
    }

    /**
     * Opsiyonel dual fetch sayfasını toggle eder.
     * Zorunlu sayfalar bu metotla değiştirilemez.
     *
     * @param  int  $id
     * @param  bool $enable
     * @return void
     *
     * @example
     *   $extractor->set_dual_fetch(999, true);  // aç
     *   $extractor->set_dual_fetch(999, false); // kapat
     */
    public function set_dual_fetch(int $id, bool $enable): void {
        $ids = $this->get_optional_dual_fetch_ids();
        if ($enable) {
            $ids[] = $id;
        } else {
            $ids = array_diff($ids, [$id]);
        }
        update_option('pae_dual_fetch_pages', array_values(array_unique($ids)));
    }

    // =========================================================
    //  HEAVY PROCESS LOCK
    // =========================================================

    /**
     * Ağır işlem öncesi kilitleme yapar.
     * Aynı anda birden fazla fetch çalışmasını engeller.
     *
     * @return bool false ise işlem zaten çalışıyor
     */
    private function start_heavy_process(): bool {
        static $is_already_running = false;
        if ($is_already_running) return false;

        if (get_transient('pae_global_lock')) {
            $this->error_log('PAE: İşlem zaten başka bir süreçte çalışıyor.');
            return false;
        }

        set_transient('pae_global_lock', 'true', 120);
        $is_already_running = true;

        if (!defined('DISABLE_WP_CRON')) define('DISABLE_WP_CRON', true);
        add_filter('pre_spawn_cron', '__return_false');
        add_filter('action_scheduler_allow_async_request', '__return_false', 999);
        add_filter('wp_doing_cron', '__return_true');

        $this->error_log('PAE: Heavy Process BAŞLADI.');
        return true;
    }

    /**
     * İşlem bittiğinde kilidi kaldırır.
     *
     * @return void
     */
    private function end_heavy_process(): void {
        remove_filter('pre_spawn_cron', '__return_false');
        remove_filter('action_scheduler_allow_async_request', '__return_false', 999);
        remove_filter('wp_doing_cron', '__return_true');
        delete_transient('pae_global_lock');
        $this->error_log('PAE: Heavy Process BİTTİ.');
    }

    /**
     * ID bazlı fetch lock alır.
     *
     * @param  int|string $id
     * @return bool false ise zaten kilitli
     */
    private function acquire_lock($id): bool {
        $key = 'pae_lock_' . $id;
        if (function_exists('get_transient')) {
            if (get_transient($key)) return false;
            set_transient($key, 1, 60);
            return true;
        }
        $lockf = sys_get_temp_dir() . '/' . $key . '.lock';
        if (file_exists($lockf)) return false;
        @file_put_contents($lockf, time());
        return true;
    }

    /**
     * ID bazlı fetch lock'u serbest bırakır.
     *
     * @param  int|string $id
     * @return void
     */
    private function release_lock($id): void {
        $key = 'pae_lock_' . $id;
        if (function_exists('delete_transient')) {
            delete_transient($key);
            return;
        }
        $lockf = sys_get_temp_dir() . '/' . $key . '.lock';
        if (file_exists($lockf)) @unlink($lockf);
    }

    // =========================================================
    //  CSP DOMAIN TESPİTİ (fetch içinden çağrılır)
    // =========================================================

    /**
     * Fetch edilen HTML'deki harici domain'leri tespit edip DB'ye kaydeder.
     *
     * @param  \voku\helper\HtmlDomParser $html_content
     * @param  array|\WP_Error            $response
     * @return void
     */
    private function detect_and_save_csp_domains($html_content, $response): void {
        $tags_to_check = [
            'iframe' => ['src', 'data-src', 'data-lazy-src'],
            'img'    => ['src', 'data-src', 'data-lazy-src'],
            'script' => ['src'],
            'link'   => ['href'],
            'video'  => ['src'],
            'audio'  => ['src'],
            'a'      => ['data-full-res', 'data-src', 'href'],
        ];

        $directive_map = [
            'iframe' => 'frame-src',
            'img'    => 'img-src',
            'script' => 'script-src',
            'link'   => 'style-src',
            'video'  => 'media-src',
            'audio'  => 'media-src',
            'a'      => 'img-src',
        ];

        $site_domain  = parse_url(get_site_url(), PHP_URL_HOST) ?? '';
        $new_domains  = [];

        foreach ($tags_to_check as $tag => $attributes) {
            foreach ($html_content->find($tag) as $element) {
                foreach ($attributes as $attr) {
                    $url = $element->getAttribute($attr);
                    if (!$url) continue;
                    $parsed    = parse_url($url);
                    $domain    = $parsed['host'] ?? null;
                    $scheme    = $parsed['scheme'] ?? '';
                    if ($domain && in_array($scheme, ['http', 'https'], true)) {
                        if (preg_match('/^(localhost|127\.0\.0\.1|10\.\d+\.\d+\.\d+|192\.168\.\d+\.\d+|172\.(1[6-9]|2\d|3[01])\.\d+\.\d+)$/', $domain)) continue;
                        if ($domain === $site_domain) continue;
                        $directive = $directive_map[$tag] ?? null;
                        if ($directive) {
                            $new_domains[$directive][] = $domain;
                            break;
                        }
                    }
                }
            }
        }

        if (!empty($new_domains)) {
            $approved = get_option('csp_approved_domains', []);
            if (!is_array($approved)) $approved = [];
            foreach ($new_domains as $directive => $domains) {
                if (!isset($approved[$directive]) || !is_array($approved[$directive])) {
                    $approved[$directive] = [];
                }
                $approved[$directive] = array_unique(array_merge($approved[$directive], $domains));
            }
            update_option('csp_approved_domains', $approved);
        }
    }
}
