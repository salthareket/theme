<?php

function fix_turkish_plurals( array $data, Loco_Locale $locale ){
	if( 'tr' === $locale->lang ){
		$data[0] = 'n > 1';
		$data[1] = array('one','other');
	}
	return $data;
}
add_filter( 'loco_locale_plurals', 'fix_turkish_plurals', 10, 2 );


/*
function get_or_create_dictionary_cache($lang_code) {
    $cache_dir = get_template_directory() . '/theme/static/data/';
    $cache_file = $cache_dir . 'dictionary-' . $lang_code . '.json';

    if (file_exists($cache_file)) {
        $json_data = file_get_contents($cache_file);
        return json_decode($json_data, true) ?: [];
    }

    $locale = false;
    if (!empty($GLOBALS['languages'])) {
        foreach ($GLOBALS['languages'] as $language) {
            if (isset($language['name'], $language['locale']) && $language['name'] === $lang_code) {
                $locale = $language['locale'];
                break; // Eşleşme bulundu, döngüden çık.
            }
        }
    }

    if (!$locale) return [];
    $locale = str_replace("-", "_", $locale);
    
    $dictionary = generate_dictionary_for_locale($locale);

    if (is_writable($cache_dir)) {
        file_put_contents($cache_file, json_encode($dictionary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    } else {
        error_log('Translation Cache Error: Directory not writable: ' . $cache_dir);
    }

    return $dictionary;
}

function generate_dictionary_for_locale($locale = '') {
    $translations = [];

    $theme_dir = get_template_directory();
    $lang_dir  = $theme_dir . '/languages/';
    $domain    = defined('TEXT_DOMAIN') ? TEXT_DOMAIN : basename($theme_dir);

    if (!$locale) return [];

    $template_file = $lang_dir . $domain . '.pot';
    $locale_file   = $lang_dir . $locale . '.po';

    if (!file_exists($template_file) || !file_exists($locale_file)) return [];

    require_once ABSPATH . 'wp-includes/pomo/po.php';

    $template_po = new PO();
    $template_po->import_from_file($template_file);

    $locale_po = new PO();
    $locale_po->import_from_file($locale_file);

    foreach ($template_po->entries as $entry) {
        $key = $entry->singular;
        $locale_entry = $locale_po->entries[$entry->key()] ?? null;

        if (!$locale_entry) continue;

        if (!empty($entry->is_plural)) {
            $translations[$key] = [
                $locale_entry->translations[0] ?? '',
                $locale_entry->translations[1] ?? '',
            ];
        } else {
            $translations[$key] = $locale_entry->translations[0] ?? '';
        }
    }

    return $translations;
}


function clear_translation_json_cache() {
    $cache_dir = get_template_directory() . '/theme/static/data/';
    if (!is_dir($cache_dir)) return;

    $files = glob($cache_dir . 'dictionary-*.json');
    foreach ($files as $file) {
        if (is_writable($file)) unlink($file);
    }
}
add_action('loco_saved_file', 'clear_translation_json_cache');
*/



/**
 * Translation Dictionary Generator
 * - Loco Translate ile uyumlu
 * - Her save sonrası static JSON üretir
 * - Frontend’de hızlıca kullanılır
 */
class TranslationDictionary {

    private $cache_dir;

    public function __construct() {
        $this->cache_dir = get_template_directory() . '/theme/static/data/';
        if (!is_dir($this->cache_dir)) {
            wp_mkdir_p($this->cache_dir);
        }

        // Loco dosya kaydettiğinde tetikle
        add_action('loco_saved_file', [$this, 'buildDictionaryFromLoco']);
    }

    /**
     * Loco Save Hook → JSON üret
     */
    public function buildDictionaryFromLoco($file) {
        if (!preg_match('/\.po$/', $file)) {
            return;
        }

        $locale = basename($file, '.po');
        $dictionary = $this->generateDictionary($locale);

        if (!empty($dictionary)) {
            $cache_file = $this->cache_dir . 'dictionary-' . $locale . '.json';
            file_put_contents(
                $cache_file,
                json_encode($dictionary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
        }
    }

    /**
     * JSON’u oku (frontend için)
     */
    public function getDictionary($locale = null) {
        if (!$locale) {
            $locale = determine_locale();
        }
        $cache_file = $this->cache_dir . 'dictionary-' . $locale . '.json';
        if (file_exists($cache_file)) {
            return json_decode(file_get_contents($cache_file), true);
        }
        return [];
    }

    /**
     * PO dosyasından dictionary üret
     */
    private function generateDictionary($locale) {
        $translations = [];

        $theme_dir = get_template_directory();
        $lang_dir  = $theme_dir . '/languages/';
        $domain    = defined('TEXT_DOMAIN') ? TEXT_DOMAIN : basename($theme_dir);

        $template_file = $lang_dir . $domain . '.pot';
        $locale_file   = $lang_dir . $locale . '.po';

        if (!file_exists($template_file) || !file_exists($locale_file)) {
            return [];
        }

        require_once ABSPATH . 'wp-includes/pomo/po.php';

        $template_po = new PO();
        $template_po->import_from_file($template_file);

        $locale_po = new PO();
        $locale_po->import_from_file($locale_file);

        foreach ($template_po->entries as $entry) {
            $key = $entry->singular;
            $locale_entry = $locale_po->entries[$entry->key()] ?? null;

            if (!$locale_entry) continue;

            if (!empty($entry->is_plural)) {
                $translations[$key] = [
                    $locale_entry->translations[0] ?? '',
                    $locale_entry->translations[1] ?? '',
                ];
            } else {
                $translations[$key] = $locale_entry->translations[0] ?? '';
            }
        }

        return $translations;
    }
}
