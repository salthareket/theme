<?php

/**
 * String Helper Functions
 *
 * Çeviri, Türkçe karakter desteği, truncate, maskeleme, HTML/XML temizleme,
 * case dönüşüm ve format yardımcıları.
 */

// ─── Çeviri (i18n) ──────────────────────────────────────────────

/**
 * WordPress __() wrapper'ı. TEXT_DOMAIN tanımlıysa otomatik kullanır.
 */
function trans($text = '', $theme = '') {
    if ($theme === '' && defined('TEXT_DOMAIN')) {
        $theme = TEXT_DOMAIN;
    }
    return __($text, $theme);
}

function trans_n_noop($singular = '', $plural = '', $theme = '') {
    if ($theme === '' && defined('TEXT_DOMAIN')) {
        $theme = TEXT_DOMAIN;
    }
    return _n_noop($singular, $plural, $theme);
}

/**
 * Çoğul çeviri. $count 0 ise $null döner. {} placeholder'ı sayıyla replace edilir.
 */
function trans_plural($singular = '', $plural = '', $null = '', $count = 1, $theme = '') {
    $domain = $theme ?: (defined('TEXT_DOMAIN') ? TEXT_DOMAIN : 'default');

    if ($count == 0 && !empty($null)) return $null;

    $pluralized = _n($singular, $plural, $count, $domain);
    return str_replace('{}', $count, $pluralized);
}

/**
 * sprintf ile çeviri. Array'deki değerler sırayla yerleştirilir.
 */
function trans_arr($text, $arr) {
    if (empty($arr)) return $text;
    return call_user_func_array('sprintf', array_merge([trans($text)], $arr));
}

function printf_array($text, $arr) {
    return call_user_func_array('sprintf', array_merge((array) $text, $arr));
}

/**
 * Belirli bir locale ile çeviri yapar, sonra eski locale'e döner.
 */
function trans_lang($text, $domain = 'default', $the_locale = 'en_US') {
    global $locale;
    $old    = $locale;
    $locale = $the_locale;
    $result = __($text, $domain);
    $locale = $old;
    return $result;
}

/**
 * Statik HTML içindeki {{translate('...')}} ve {{function('...')}} ifadelerini çözümler.
 */
function trans_static($text) {
    $text = preg_replace_callback(
        "/\{\{translate\('([^']+)'\)\}\}/",
        fn($m) => trans($m[1]),
        $text
    );
    return trans_functions($text);
}

/**
 * {{function('funcName')}} ifadelerini çalıştırır.
 * Güvenlik: Sadece mevcut fonksiyonlar çağrılır.
 */
function trans_functions($text) {
    return preg_replace_callback(
        "/\{\{function\('([^']+)'\)\}\}/",
        fn($m) => function_exists($m[1]) ? $m[1]() : '',
        $text
    );
}

// ─── Türkçe Karakter Desteği ────────────────────────────────────

/**
 * Türkçe uyumlu uppercase. i→İ dönüşümünü doğru yapar.
 */
function uppertr($text) {
    if (_is_turkish_locale()) {
        $text = str_replace('i', 'İ', $text);
    }
    return mb_convert_case($text, MB_CASE_UPPER, 'UTF-8');
}

/**
 * Türkçe uyumlu lowercase. I→ı dönüşümünü doğru yapar.
 */
function lowertr($text) {
    if (_is_turkish_locale()) {
        $text = str_replace('I', 'ı', $text);
    }
    return mb_convert_case($text, MB_CASE_LOWER, 'UTF-8');
}

/**
 * Türkçe uyumlu ucwords (her kelimenin ilk harfi büyük).
 */
function ucwordstr($text) {
    if (_is_turkish_locale()) {
        $text = str_replace([' I', ' ı', ' İ', ' i'], [' I', ' I', ' İ', ' İ'], ' ' . $text);
    }
    return ltrim(mb_convert_case($text, MB_CASE_TITLE, 'UTF-8'));
}

/**
 * Türkçe uyumlu ucfirst (sadece ilk harf büyük).
 */
function ucfirsttr($text) {
    if (empty($text)) return $text;
    $first = mb_substr($text, 0, 1, 'UTF-8');
    $rest  = mb_substr($text, 1, null, 'UTF-8');
    return uppertr($first) . $rest;
}

/**
 * Aktif dilin Türkçe olup olmadığını kontrol eder.
 * @internal
 */
function _is_turkish_locale() {
    if (function_exists('qtranxf_getLanguage') && qtranxf_getLanguage() === 'tr') return true;
    if (function_exists('icl_get_languages') && defined('ICL_LANGUAGE_CODE') && ICL_LANGUAGE_CODE === 'tr') return true;
    if (function_exists('pll_current_language') && pll_current_language() === 'tr') return true;
    return false;
}

// ─── Truncate & Maskeleme ───────────────────────────────────────

/**
 * Metni kelime sınırına göre kırpar, sonuna ... ekler.
 */
function truncate($text, $chars = 25) {
    if (mb_strlen($text) <= $chars) return $text;

    $text = mb_substr($text, 0, $chars);
    $last = mb_strrpos($text, ' ');

    return ($last !== false ? mb_substr($text, 0, $last) : $text) . '...';
}

/**
 * Metni ortadan kırpar: "başlangıç...son"
 */
function truncate_middle($text, $chars = 25) {
    if (mb_strlen($text) <= $chars) return $text;

    $sep    = '...';
    $max    = $chars - mb_strlen($sep);
    $start  = (int) ceil($max / 2);
    $end    = (int) floor($max / 2);

    return mb_substr($text, 0, $start) . $sep . mb_substr($text, -$end);
}

/**
 * Metnin son N karakteri hariç hepsini * ile maskeler.
 */
function masked_text($text = '', $visible_digits = 4) {
    $len = mb_strlen($text);
    if ($len <= $visible_digits) return $text;

    return str_repeat('*', $len - $visible_digits) . mb_substr($text, -$visible_digits);
}

// ─── HTML / XML Temizleme ───────────────────────────────────────

/**
 * <p> tag'lerini <br>'ye çevirir, sondaki <br>'leri temizler.
 */
function ptobr($text) {
    $text = str_replace(['<p>', '</p>', '[p-filter]'], ['', '<br>', ''], $text);
    return preg_replace('/(<br>)+$/', '', $text);
}

/**
 * Belirli class/id'ye sahip HTML elementlerini siler.
 */
function stripTagsByClass($array_of_id_or_class, $text) {
    $name  = implode('|', array_map('preg_quote', $array_of_id_or_class));
    $regex = '#<(\w+)\s[^>]*(class|id)\s*=\s*[\'"](' . $name . ')[\'"][^>]*>.*</\\1>#isU';
    return preg_replace($regex, '', $text);
}

/**
 * Belirtilen tag'lerin içeriğini (tag dahil) siler. strip_tags'in tersi.
 */
function strip_tags_opposite($content, $tags) {
    foreach (explode('><', trim($tags, '<>')) as $tag) {
        $tag     = preg_quote($tag, '/');
        $content = preg_replace('/<' . $tag . '[^>]*>.*?<\s*\/' . $tag . '>/is', '', $content);
    }
    return $content;
}

function remove_html_comments($content = '') {
    return preg_replace('/<!--(.|\s)*?-->/', '', $content);
}

function remove_xml_declaration($content = '') {
    return preg_replace('/^\s*<\?xml[^>]*\?>\s*/', '', $content);
}

// ─── URL Temizleme ──────────────────────────────────────────────

/**
 * Metindeki tüm URL'leri boşlukla değiştirir.
 */
function removeUrls($text = '') {
    return preg_replace('/\b((https?|ftp|file):\/\/|www\.)[-A-Z0-9+&@#\/%?=~_|$!:,.;]*[A-Z0-9+&@#\/%=~_|$]/i', ' ', $text);
}

/**
 * Metindeki tüm URL'leri bulup array olarak döndürür.
 */
function extract_urls($string) {
    preg_match_all('/\b((?:https?:\/\/|www\.)[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|\/)))/i', $string, $m);
    return $m[0] ?? [];
}

// ─── Case Dönüşüm ──────────────────────────────────────────────

/**
 * camelCase → kebab-case
 */
function camel2Dashes($str, $separator = '-') {
    if (empty($str)) return $str;
    return strtolower(preg_replace('/[A-Z]/', $separator . '$0', lcfirst($str)));
}

/**
 * kebab-case veya snake_case → camelCase
 */
function dashes2Camel($string, $capitalizeFirstCharacter = false) {
    if (empty($string)) return $string;

    $sep = str_contains($string, '-') ? '-' : '_';
    $str = str_replace($sep, '', ucwords($string, $sep));

    return $capitalizeFirstCharacter ? $str : lcfirst($str);
}

// ─── Doğrulama & Format ─────────────────────────────────────────

/**
 * Geçerli hex renk kodu mu kontrol eder (#fff veya #ffffff).
 */
function is_hex_color($val): bool {
    return is_string($val) && (bool) preg_match('/^#([a-fA-F0-9]{3}|[a-fA-F0-9]{6})$/', $val);
}

/**
 * JSON verisini HTML attribute'a güvenli şekilde encode eder.
 */
function json_attr($json) {
    return esc_attr(wp_json_encode($json));
}