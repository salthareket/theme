<?php

function fix_turkish_plurals( array $data, Loco_Locale $locale ){
	if( 'tr' === $locale->lang ){
		$data[0] = 'n > 1';
		$data[1] = array('one','other');
	}
	return $data;
}
add_filter( 'loco_locale_plurals', 'fix_turkish_plurals', 10, 2 );



// Loco dosya kaydettiğinde tetikle
if(is_admin()){
    add_action('init', function(){
        if (defined('DOING_AJAX') && DOING_AJAX && isset($_POST['action'], $_POST['route'])) {
            if ($_POST['action'] === 'loco_json' && $_POST['route'] === 'save') {
                $locale = sanitize_text_field($_POST['locale'] ?? '');
                //error_log("[TranslationDictionary] Loco save AJAX tetiklendi: $locale");

                $dict = new TranslationDictionary();
                $dict->buildDictionaryFromLoco(get_template_directory() . '/languages/' . $locale . '.po');
            }
        }
    });    
}




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
    }

    /**
     * Loco Save Hook → JSON üret
     */
    public function buildDictionaryFromLoco($path) {
        //error_log("locoooooooooooooooooooooooooooooooooooooooo.....................");
        if (!preg_match('/\.po$/', $path)) {
            return;
        }

        $locale = basename($path, '.po');
        $locale = str_replace("-", "_", $locale);
        $dictionary = $this->generateDictionary($locale);

        //error_log("locale:".$locale);

        if (!empty($dictionary)) {
            $cache_file = $this->cache_dir . 'dictionary-' . $this->normalizeLocale($locale) . '.json';
            //error_log("cache_file:".$cache_file);
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
            $locale = $this->normalizeLocale(determine_locale());
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

        //error_log($template_file.", ".$locale_file);

        if (!file_exists($template_file) || !file_exists($locale_file)) {
            return [];
        }

        //error_log("Dosyalar var abi...");

        require_once ABSPATH . 'wp-includes/pomo/po.php';

        $template_po = new PO();
        $template_po->import_from_file($template_file);

        $locale_po = new PO();
        $locale_po->import_from_file($locale_file);

        //error_log(print_r($template_po->entries, true));

        foreach ($template_po->entries as $entry) {
            $key = $entry->singular;
            $locale_entry = $locale_po->entries[$entry->key()] ?? null;

            if (!$locale_entry) continue;

            /*if (!empty($entry->is_plural)) {
                $translations[$key] = [
                    $locale_entry->translations[0] ?? '',
                    $locale_entry->translations[1] ?? '',
                ];
            } else {
                $translations[$key] = $locale_entry->translations[0] ?? '';
            }*/
            if (!empty($entry->is_plural)) {
                $single = $locale_entry->translations[0] ?? '';
                $plural = $locale_entry->translations[1] ?? '';

                // Eğer tek/çoğul eksikse diğerini kullan
                if (!$single && $plural) $single = $plural;
                if (!$plural && $single) $plural = $single;

                // Hiçbiri yoksa atla
                if (!$single && !$plural) continue;

                $translations[$key] = [$single, $plural];
            } else {
                $single = $locale_entry->translations[0] ?? '';
                if (!$single) continue;
                $translations[$key] = $single;
            }
        }

        return $translations;
    }

    private function normalizeLocale($locale) {
        // eğer en-US, tr_TR gibi ise
        if (strpos($locale, '-') !== false || strpos($locale, '_') !== false) {
            return strtolower(substr($locale, 0, 2));
        }

        // zaten kısa kodsa (en, tr, ar vs.)
        return strtolower($locale);
    }
}
