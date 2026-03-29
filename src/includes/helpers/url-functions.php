<?php

/**
 * URL Helper Functions
 *
 * URL doğrulama, dönüştürme, parse etme ve navigasyon yardımcıları.
 * Tüm fonksiyonlar mevcut tema API'si ile uyumludur.
 */

// ─── Doğrulama & Bilgi ──────────────────────────────────────────

function is_url($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

function get_host_url() {
    return site_url();
}

/**
 * URL'den sadece domain adını (TLD hariç) döndürür.
 * Örn: https://www.example.com/path → "example"
 */
function get_url_domain($url) {
    $host = parse_url($url, PHP_URL_HOST) ?: '';
    if (preg_match('/(?P<domain>[a-z0-9][a-z0-9\-]{1,63}\.[a-z\.]{2,6})$/i', $host, $m)) {
        return strstr($m['domain'], '.', true);
    }
    return '';
}

/**
 * Site URL'sini protokolsüz döndürür.
 * Örn: https://example.com → example.com
 */
function get_host_domain_url() {
    return preg_replace('#^https?://#', '', site_url());
}

function is_local($url = "") {
    return parse_url(get_site_url(), PHP_URL_HOST) === parse_url($url, PHP_URL_HOST);
}

function is_external($url = "") {
    return Timber\URLHelper::is_external($url);
}

function is_current_url($url = "", $hash = false) {
    if ($hash && str_contains($url, '#')) {
        $url = explode('#', $url)[0];
    }
    return $url === current_url();
}

function isCurrentEndpoint($url = "") {
    return Timber\URLHelper::get_params(-1) === getUrlEndpoint($url);
}

// ─── Localhost Tespiti ───────────────────────────────────────────

function isLocalhost() {
    static $result = null;
    if ($result !== null) return $result;

    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    $server = $_SERVER['SERVER_ADDR'] ?? '';

    $locals = ['127.0.0.1', '::1', 'localhost'];

    if (in_array($remote, $locals) || in_array($server, $locals)) {
        return $result = true;
    }

    // Private network CIDR kontrolü
    foreach ([$remote, $server] as $ip) {
        $long = ip2long($ip);
        if ($long === false) continue;
        foreach (['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16'] as $cidr) {
            [$subnet, $bits] = explode('/', $cidr);
            $mask = -1 << (32 - (int)$bits);
            if (($long & $mask) === (ip2long($subnet) & $mask)) {
                return $result = true;
            }
        }
    }

    return $result = false;
}

// ─── Mevcut URL & Parçalama ─────────────────────────────────────

function current_url($port = false) {
    if (isLocalhost()) $port = true;

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
    $host   = $_SERVER['SERVER_NAME'] ?? 'localhost';
    $p      = ($port && ($_SERVER['SERVER_PORT'] ?? '80') !== '80') ? ':' . $_SERVER['SERVER_PORT'] : '';
    $uri    = $_SERVER['REQUEST_URI'] ?? '/';

    return $scheme . $host . $p . $uri;
}

/**
 * Site subfolder'ını döndürür.
 * Örn: http://localhost/my-site → /my-site/
 * Subfolder yoksa → /
 */
function getSiteSubfolder() {
    static $cache = null;
    if ($cache !== null) return $cache;

    $parsed = parse_url(get_site_url());
    $path   = trim($parsed['path'] ?? '', '/');

    return $cache = $path ? "/{$path}/" : '/';
}

/**
 * URL'yi parçalara ayırır, subfolder'ı çıkarır.
 */
function getUrlParts($url = "") {
    if (empty($url)) $url = current_url();

    $path = trim(parse_url($url, PHP_URL_PATH) ?: '', '/');

    $subfolder = trim(getSiteSubfolder(), '/');
    if ($subfolder) {
        $path = preg_replace('#^' . preg_quote($subfolder, '#') . '/?#', '', $path);
    }

    return $path !== '' ? explode('/', $path) : [];
}

/**
 * URL'den endpoint slug'ını çıkarır.
 * $base_endpoint verilmişse ondan sonraki segmenti döndürür.
 */
function getUrlEndpoint($url = "", $base_endpoint = "") {
    if (empty($url)) $url = current_url();

    $segments = explode('/', trim(parse_url($url, PHP_URL_PATH) ?: '', '/'));

    if ($base_endpoint !== '') {
        $idx = array_search($base_endpoint, $segments);
        if ($idx !== false && isset($segments[$idx + 1])) {
            $endpoint = $segments[$idx + 1];
        } else {
            $endpoint = end($segments) ?: '';
        }
    } else {
        $endpoint = end($segments) ?: '';
    }

    // Subfolder adını endpoint'ten temizle
    $subfolder = trim(getSiteSubfolder(), '/');
    if ($subfolder && $endpoint === $subfolder) {
        $endpoint = '';
    }

    return $endpoint;
}

// ─── URL Çıkarma & Dönüştürme ───────────────────────────────────

/**
 * String içindeki ilk URL'yi bulur ve döndürür.
 */
function extract_url($string) {
    if (preg_match('/\b(?:https?|ftp|file):\/\/[-A-Z0-9+&@#\/%=~_|$?!:,.]*[A-Z0-9+&@#\/%=~_|$]/i', $string, $m)) {
        return $m[0];
    }
    return null;
}

/**
 * Relative URL'yi absolute'a çevirir.
 */
function rel2abs($rel, $base) {
    if (parse_url($rel, PHP_URL_SCHEME) !== null && parse_url($rel, PHP_URL_SCHEME) !== '') return $rel;
    if (isset($rel[0]) && ($rel[0] === '#' || $rel[0] === '?')) return $base . $rel;

    $parts  = parse_url($base);
    $scheme = $parts['scheme'] ?? 'https';
    $host   = $parts['host'] ?? '';
    $path   = $parts['path'] ?? '';

    $path = preg_replace('#/[^/]*$#', '', $path);
    if (isset($rel[0]) && $rel[0] === '/') $path = '';

    $abs = "{$host}{$path}/{$rel}";

    // /./ ve /../ temizliği
    $re = ['#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#'];
    for ($n = 1; $n > 0; $abs = preg_replace($re, '/', $abs, -1, $n)) {}

    return "{$scheme}://{$abs}";
}

/**
 * Absolute path'i relative path'e çevirir.
 */
function abs2rel(string $base, string $path) {
    if (is_dir($base)) {
        $base = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.';
    }

    $a = explode(DIRECTORY_SEPARATOR, $base);
    $b = explode(DIRECTORY_SEPARATOR, $path);

    // Ortak prefix'i bul
    $common = 0;
    $limit  = min(count($a), count($b));
    while ($common < $limit && $a[$common] === $b[$common]) {
        $common++;
    }

    // Geri dönüş (..) sayısı: $a'nın kalan derinliği - 1 (dosya adı)
    $ups      = count($a) - $common - 1;
    $relative = array_merge(array_fill(0, max(0, $ups), '..'), array_slice($b, $common));

    return '.' . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $relative);
}

/**
 * Absolute URL veya fiziksel path'i site-relative URL'ye çevirir.
 */
function to_relative_url($input) {
    if (empty($input)) return $input;

    $site_url  = rtrim(home_url(), '/');
    $subfolder = '/' . trim(getSiteSubfolder(), '/');
    if ($subfolder === '/') $subfolder = '';

    // Absolute URL → relative
    if (stripos($input, 'http') === 0) {
        $path_only = str_ireplace($site_url, '', $input);
        $url = $subfolder . '/' . ltrim($path_only, '/');
    }
    // Fiziksel path → relative
    elseif (defined('ABSPATH') && stripos($input, ABSPATH) === 0) {
        $rel = str_replace(['\\', ABSPATH], ['/', ''], $input);
        $url = $subfolder . '/' . ltrim($rel, '/');
    }
    // Zaten relative
    else {
        if ($subfolder && stripos($input, $subfolder) !== 0) {
            $url = $subfolder . '/' . ltrim($input, '/');
        } else {
            $url = '/' . ltrim($input, '/');
        }
    }

    // Çift slash temizliği (protokol hariç)
    return preg_replace('#(?<!:)//+#', '/', $url);
}

/**
 * Array içindeki tüm absolute URL'leri recursive olarak relative yapar.
 */
function array_urls_to_relative($input) {
    if (!is_array($input)) {
        return (is_string($input) && stripos($input, 'http') === 0) ? to_relative_url($input) : $input;
    }

    $out = [];
    foreach ($input as $k => $v) {
        if (is_array($v)) {
            $out[$k] = array_urls_to_relative($v);
        } elseif (is_string($v) && stripos($v, 'http') === 0) {
            $out[$k] = to_relative_url($v);
        } else {
            $out[$k] = $v;
        }
    }
    return $out;
}

// ─── URL Temizleme ──────────────────────────────────────────────

/**
 * URL'den /search/... ve /page/N parametrelerini temizler.
 */
function remove_params_from_url($url) {
    $p    = parse_url($url);
    $path = $p['path'] ?? '';
    $path = preg_replace('#(/search/[^/]+(/[^/]+)?)?(/page/\d+)?/?$#', '', $path);

    $scheme = isset($p['scheme']) ? $p['scheme'] . '://' : '';
    $host   = $p['host'] ?? '';
    $port   = isset($p['port']) ? ':' . $p['port'] : '';

    return $scheme . $host . $port . $path;
}

/**
 * URL'den dil kodu ve query string temizleyerek saf path döndürür.
 * Polylang uyumlu.
 */
function get_clean_root_path($full_url) {
    if (empty($full_url)) return '';

    $path = parse_url($full_url, PHP_URL_PATH) ?: '/';
    $path = strtok($path, '?#');

    // /en/, /tr/ gibi 2 harfli dil prefix'ini temizle
    $path = preg_replace('#^/[a-z]{2}/#', '/', $path);

    return str_starts_with($path, '/') ? $path : '/' . $path;
}

// ─── HTTP Yardımcıları ──────────────────────────────────────────

/**
 * URL'nin HTTP durum kodunu döndürür (HEAD request).
 */
function get_page_status($url = "") {
    if (empty($url) || !function_exists('curl_init')) return 0;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_NOBODY         => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $code;
}

/**
 * Prefetch/prerender request mi kontrol eder.
 */
function is_prefetch_request() {
    if (isset($_SERVER['HTTP_X_PURPOSE']) && strtolower($_SERVER['HTTP_X_PURPOSE']) === 'preview') return true;
    if (isset($_SERVER['HTTP_X_MOZ']) && strtolower($_SERVER['HTTP_X_MOZ']) === 'prefetch') return true;

    $headers = function_exists('getallheaders') ? getallheaders() : [];
    return ($headers['Purpose'] ?? '') === 'prefetch'
        || ($headers['Sec-Purpose'] ?? '') === 'prefetch';
}

/**
 * URL'yi parse edip internal/external bilgisi döndürür.
 */
function parse_external_url($url = '', $internal_class = 'internal-link', $external_class = 'external-link') {
    if (empty($url)) return false;

    $ext = Timber\URLHelper::is_external($url);
    return [
        'type'   => $ext ? 'external' : 'internal',
        'class'  => $ext ? $external_class : $internal_class,
        'target' => $ext ? '_blank' : '_self',
        'url'    => $url,
    ];
}

// ─── Query String ───────────────────────────────────────────────

/**
 * Mevcut query string'i JSON olarak döndürür.
 */
function queryStringJSON() {
    parse_str($_SERVER['QUERY_STRING'] ?? '', $params);
    return json_encode((object) $params);
}


// ─── Hash / Onepage URL Yardımcıları ────────────────────────────

/**
 * URL'yi hash URL'ye çevirir (onepage navigasyon).
 */
function make_hash_url($url, $hash) {
    if (empty($url) || empty($hash)) return $url;

    $url      = str_replace(get_host_url(), '', $url);
    $segments = array_values(array_filter(explode('/', $url), fn($s) => trim($s) !== ''));

    if (empty($segments)) return $url;

    $hash = end($segments);
    return get_host_url() . '/' . $segments[0] . '#' . $hash;
}

/**
 * Çokdilli onepage URL oluşturur (menü item'dan).
 */
function make_onepage_url($item, $full_url) {
    $url = $item->link;
    if (empty($url) || empty($item->hash_url)) return $url;

    $site_url      = site_url();
    $url_temp      = $url;
    $multilanguage = false;
    $lang          = '';
    $lang_default  = '';

    // qTranslate desteği
    if (function_exists('qtranxf_getLanguage')) {
        $multilanguage = true;
        $lang          = qtranxf_getLanguage();
        $lang_default  = qtranxf_getLanguageDefault();
        $url_temp      = str_replace($lang . '/', '', $url_temp);
    }

    // WPML desteği
    if (function_exists('icl_get_languages')) {
        global $sitepress;
        $multilanguage = true;
        $lang          = ICL_LANGUAGE_CODE;
        $lang_default  = $sitepress->get_default_language();
        $url_temp      = str_replace($lang . '/', '', $url_temp);
    }

    $url_temp = str_replace($site_url, '', $url_temp);
    $segments = array_values(array_filter(explode('/', $url_temp), fn($s) => trim($s) !== ''));

    if (empty($segments)) {
        $result = ($full_url || !is_front_page() ? $url : '') . '#' . $item->slug;
        return trim(str_replace(get_host_domain_url() . '.', '', $result));
    }

    $url_end = str_replace('#', '', end($segments));

    // Dil prefix'i
    if ($multilanguage && !empty($lang) && $lang !== $lang_default) {
        $lang = $lang . '/';
    } else {
        $lang = '';
    }

    $hash = !empty($url_end) ? '#' . $url_end : '#' . $item->slug;

    // Ana sayfa çocuğuysa kısa URL
    $home_id = url_to_postid(get_home_url());
    if ($home_id == $item->post_parent) {
        $result = get_home_url() . $hash;
    } else {
        array_pop($segments);
        $paths  = implode('/', $segments);
        $result = $site_url . '/' . $paths . '/' . $lang . $hash;
    }

    return trim(str_replace(get_host_domain_url() . '.', '', $result));
}

/**
 * Post ID'den onepage URL oluşturur (qTranslate uyumlu).
 */
function make_onepage_url_by_id($id, $end_slug, $full_url = false) {
    $url = get_permalink($id);
    if (empty($url)) return $url;

    $site_url = site_url();
    $url_temp = $url;
    $lang     = '';

    if (function_exists('qtranxf_getLanguage')) {
        $lang     = qtranxf_getLanguage();
        $url_temp = str_replace($lang . '/', '', $url_temp);
    }

    $url_temp = str_replace($site_url, '', $url_temp);
    $segments = array_values(array_filter(explode('/', $url_temp), fn($s) => trim($s) !== ''));

    if (empty($segments)) {
        $result = ($full_url || !is_front_page() ? $url : '') . '#';
        return str_replace(get_host_domain_url() . '.', '', str_replace('http:/', '', $result));
    }

    $url_end = str_replace('#', '', $end_slug ? end($segments) : implode('/', $segments));

    if (function_exists('qtranxf_getLanguageDefault') && !empty($lang) && $lang !== qtranxf_getLanguageDefault()) {
        $lang = $lang . '/';
    } else {
        $lang = '';
    }

    $hash   = !empty($url_end) ? '#' . $url_end : '';
    $result = ($full_url || !is_front_page() ? $site_url . '/' . $lang : '') . $hash;
    $result = str_replace('http:/', '', $result);

    return str_replace(get_host_domain_url() . '.', '', $result);
}

// ─── Legacy: URL → Post ID (Rewrite Rules ile) ─────────────────

/**
 * URL'den post ID çözümler (WP core url_to_postid'in genişletilmiş versiyonu).
 * Rewrite rule matching ile çalışır.
 */
function bwp_url_to_postid($url) {
    global $wp_rewrite;

    $url = apply_filters('url_to_postid', $url);

    // Direkt query param kontrolü: ?p=N, ?page_id=N, ?attachment_id=N
    if (preg_match('#[?&](p|page_id|attachment_id)=(\d+)#', $url, $values)) {
        $id = absint($values[2]);
        if ($id) return $id;
    }

    $rewrite = $wp_rewrite->wp_rewrite_rules();
    if (empty($rewrite)) return 0;

    // Anchor ve query string temizliği
    $url = explode('#', $url)[0];
    $url = explode('?', $url)[0];

    // www. normalizasyonu
    $home = home_url();
    if (str_contains($home, '://www.') && !str_contains($url, '://www.')) {
        $url = str_replace('://', '://www.', $url);
    } elseif (!str_contains($home, '://www.')) {
        $url = str_replace('://www.', '://', $url);
    }

    if (!$wp_rewrite->using_index_permalinks()) {
        $url = str_replace('index.php/', '', $url);
    }

    // Domain'i soy
    if (str_contains($url, $home)) {
        $url = str_replace($home, '', $url);
    } else {
        $home_path = parse_url($home, PHP_URL_PATH) ?: '';
        $url = str_replace($home_path, '', $url);
    }

    $url     = trim($url, '/');
    $request = $url;

    foreach ((array) $rewrite as $match => $query) {
        $request_match = $request;
        if (!empty($url) && $url !== $request && strpos($match, $url) === 0) {
            $request_match = $url . '/' . $request;
        }

        if (!preg_match("!^{$match}!", $request_match, $matches)) continue;

        $query = preg_replace('!^.+\?!', '', $query);
        $query = addslashes(WP_MatchesMapRegex::apply($query, $matches));

        global $wp;
        parse_str($query, $query_vars);
        $filtered = [];

        foreach ((array) $query_vars as $key => $value) {
            if (in_array($key, $wp->public_query_vars)) {
                $filtered[$key] = $value;
            }
        }

        // Post type query var mapping
        $pt_vars = [];
        foreach ($GLOBALS['wp_post_types'] as $pt => $t) {
            if ($t->query_var) $pt_vars[$t->query_var] = $pt;
        }

        foreach ($wp->public_query_vars as $wpvar) {
            $val = $wp->extra_query_vars[$wpvar] ?? $_GET[$wpvar] ?? $query_vars[$wpvar] ?? null;
            if ($val === null) continue;

            $filtered[$wpvar] = is_array($val) ? array_map('strval', $val) : (string) $val;

            if (isset($pt_vars[$wpvar])) {
                $filtered['post_type'] = $pt_vars[$wpvar];
                $filtered['name']      = $filtered[$wpvar];
            }
        }

        $wp_query = new WP_Query($filtered);
        if (!empty($wp_query->posts) && $wp_query->is_singular()) {
            return $wp_query->posts[0]->ID;
        }

        return 0;
    }

    return 0;
}