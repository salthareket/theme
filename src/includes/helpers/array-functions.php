<?php

/**
 * Array & String-Array Helper Functions
 *
 * Dizi manipülasyonu, arama, dönüştürme ve metin-dizi yardımcıları.
 */

// ─── Filtreleme & Arama ─────────────────────────────────────────

/**
 * Dizide $val ile eşleşen (trim'li) ilk elemanı döndürür.
 */
function acceptOnly($arr, $val) {
    if (!is_array($arr)) return null;
    foreach ($arr as $item) {
        if (trim($item) == $val) return $item;
    }
    return null;
}

/**
 * Dizide $val var mı kontrol eder (trim'li karşılaştırma).
 */
function acceptOnlyExist($arr, $val) {
    if (!is_array($arr)) return false;
    foreach ($arr as $item) {
        if (trim($item) == $val) return true;
    }
    return false;
}

/**
 * 2D array'de belirli bir field değerine göre index bulur.
 * Bulamazsa -1 döner.
 */
function array_search_by_field_value($needle, $haystack, $field) {
    foreach ($haystack as $index => $item) {
        if (isset($item[$field]) && $item[$field] === $needle) return $index;
    }
    return -1;
}

/**
 * 2D array'de field değerine göre index bulur. Bulamazsa false.
 */
function array_search2d_by_field($needle, $haystack, $field) {
    foreach ($haystack as $index => $row) {
        if (isset($row[$field]) && $row[$field] === $needle) return $index;
    }
    return false;
}

/**
 * 2D object array'de property değerine göre index bulur.
 */
function array_object_search2d_by_field($needle, $haystack, $field) {
    foreach ($haystack as $index => $obj) {
        if (isset($obj->{$field}) && $obj->{$field} === $needle) return $index;
    }
    return false;
}

/**
 * İki dizinin ortak elemanı var mı kontrol eder.
 */
function arrayMatch($array1, $array2) {
    return !empty(array_intersect($array1, $array2));
}

// ─── Ekleme / Çıkarma / Manipülasyon ────────────────────────────

/**
 * Diziye belirli bir pozisyona eleman ekler.
 * $position int ise splice, string ise key-based insert.
 */
function array_insert(&$array, $position, $insert) {
    if (is_int($position)) {
        array_splice($array, $position, 0, $insert);
    } else {
        $pos   = array_search($position, array_keys($array));
        $array = array_merge(
            array_slice($array, 0, $pos),
            $insert,
            array_slice($array, $pos)
        );
    }
}

/**
 * Diziden belirli bir değeri siler (strict karşılaştırma).
 */
function unsetValue(array $array, $value, $strict = true) {
    $key = array_search($value, $array, $strict);
    if ($key !== false) unset($array[$key]);
    return $array;
}

/**
 * Diziden belirli değere sahip tüm elemanları siler.
 */
function remove_element($array, $value) {
    return array_values(array_diff($array, [$value]));
}

/**
 * Boş (null, '', boş array) elemanları filtreler.
 */
function remove_empty_items(array $array): array {
    return array_filter($array, fn($v) => $v !== null && $v !== '' && (!is_array($v) || !empty($v)));
}

/**
 * Tekrarlayan elemanları temizler, index'leri sıfırlar.
 */
function remove_duplicated_items($array) {
    return array_values(array_unique($array));
}

// ─── Tip Kontrolü ───────────────────────────────────────────────

/**
 * Değişkenin iterable olup olmadığını kontrol eder.
 */
function array_iterable($var) {
    return $var !== null && (is_array($var) || $var instanceof Traversable);
}

// ─── Dot-Notation Erişim (Nested Array) ─────────────────────────

/**
 * Dot-notation ile nested array'den değer okur.
 * Örn: array_get_value($arr, 'user.name') → $arr['user']['name']
 */
function array_get_value(array &$array, $parents, $glue = '.') {
    if (!is_array($parents)) {
        $parents = explode($glue, $parents);
    }

    $ref = &$array;
    foreach ($parents as $key) {
        if (is_array($ref) && array_key_exists($key, $ref)) {
            $ref = &$ref[$key];
        } else {
            return null;
        }
    }
    return $ref;
}

/**
 * Dot-notation ile nested array'e değer yazar.
 * Immutable: yeni array döndürür.
 */
function array_set_value(array $array, $parents, $value = '', $glue = '.') {
    if (!is_array($parents)) {
        $parents = explode($glue, (string) $parents);
    }

    $ref = &$array;
    foreach ($parents as $key) {
        if (isset($ref) && !is_array($ref)) $ref = [];
        $ref = &$ref[$key];
    }
    $ref = $value;

    return $array;
}

/**
 * Dot-notation ile nested array'den key siler.
 */
function array_unset_value(&$array, $parents, $glue = '.') {
    if (!is_array($parents)) {
        $parents = explode($glue, $parents);
    }

    $key = array_shift($parents);
    if (empty($parents)) {
        unset($array[$key]);
    } elseif (isset($array[$key]) && is_array($array[$key])) {
        array_unset_value($array[$key], $parents);
    }
}

// ─── Deep Clone & Merge ─────────────────────────────────────────

/**
 * Array/object'i deep clone eder.
 */
function array_clone($mixed) {
    if (is_object($mixed)) return clone $mixed;
    if (is_array($mixed))  return array_map('array_clone', $mixed);
    return $mixed;
}

/**
 * İki array'i akıllıca recursive birleştirir.
 * - Array + Array → recursive merge
 * - Numeric/bool → overwrite
 * - String + String → BS breakpoint keyword varsa overwrite, yoksa concat
 * - Boş değer → dolu olan kazanır
 */
function array_merge_recursive_items($array1, $array2) {
    foreach ($array2 as $key => $value2) {
        if (!array_key_exists($key, $array1)) {
            $array1[$key] = $value2;
            continue;
        }

        $value1 = $array1[$key];

        // Her iki değer de array → recursive
        if (is_array($value1) && is_array($value2)) {
            $array1[$key] = array_merge_recursive_items($value1, $value2);
            continue;
        }

        // Numeric veya boolean → overwrite
        if ((is_numeric($value1) && is_numeric($value2)) || (is_bool($value1) && is_bool($value2))) {
            $array1[$key] = $value2;
            continue;
        }

        // Boş → dolu kazanır
        if (($value1 === null || $value1 === '') && $value2 !== null && $value2 !== '') {
            $array1[$key] = $value2;
            continue;
        }

        // String + String → BS breakpoint keyword varsa overwrite
        if (is_string($value1) && is_string($value2)) {
            $array1[$key] = preg_match('/\b(xs|sm|md|lg|xl|xxl|xxxl|xxxxl|top|bottom|start|end)\b/i', $value2)
                ? $value2
                : trim($value1 . ' ' . $value2);
            continue;
        }

        // Diğer → array2 öncelikli
        $array1[$key] = $value2;
    }

    return $array1;
}


// ─── Dönüştürme (Text ↔ Array) ──────────────────────────────────

/**
 * Metin'i ayırıcıya göre trim'li array'e çevirir.
 */
function make_array($text, $seperator = ',') {
    return array_map('trim', explode($seperator, $text));
}

/**
 * Satır sonlarına göre array'e böler.
 */
function brToArray($text) {
    return explode(PHP_EOL, $text);
}

/**
 * <br> tag'lerini newline'a çevirir.
 */
function br2Nl($text) {
    return str_ireplace(['<br />', '<br>', '<br/>'], "\r\n", $text);
}

/**
 * Newline'lara göre böler, boş satırları atar.
 */
function nl2Array($text) {
    return array_values(array_filter(
        array_map('trim', explode("\n", $text)),
        fn($line) => $line !== ''
    ));
}

/**
 * Tüm whitespace'i tek boşluğa indirger.
 */
function removeNewLine($text) {
    return trim(preg_replace('/\s+/', ' ', $text));
}

// ─── Template / HTML Yardımcıları ───────────────────────────────

/**
 * Text içindeki %key% placeholder'larını array değerleriyle replace eder.
 * Örn: str_replace_arr("Merhaba %name%", ["name" => "Ali"]) → "Merhaba Ali"
 */
function str_replace_arr($text = '', $array = []) {
    if (empty($text) || empty($array)) return $text;
    return preg_replace_callback('/%(.*?)%/', fn($m) => $array[$m[1]] ?? $m[0], $text);
}

/**
 * Associative array'i HTML attribute string'ine çevirir.
 *
 * @param array  $attributes    Key-value çiftleri
 * @param bool   $make_them_data  true ise data-* prefix ekler
 * @param string $prefix        Ek prefix (data- ile birleşir)
 * @param bool   $accept_empty  true ise boş değerli attribute'ları da dahil eder
 */
function array2Attrs($attributes = [], $make_them_data = 0, $prefix = '', $accept_empty = false) {
    if (empty($attributes)) return '';

    $pairs      = [];
    $prefix_str = $prefix !== '' ? $prefix . '-' : '';

    foreach ($attributes as $name => $value) {
        $attr_name = $make_them_data
            ? 'data-' . $prefix_str . $name
            : $name . $prefix_str;

        $attr_name  = htmlspecialchars($attr_name, ENT_QUOTES, 'UTF-8');
        $attr_value = htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');

        // Boş değer kontrolü
        if ($attr_value === '' && !$accept_empty) continue;

        if (is_bool($value)) {
            if ($value) $pairs[] = $attr_name;
        } else {
            $pairs[] = "{$attr_name}=\"{$attr_value}\"";
        }
    }

    return implode(' ', $pairs);
}

// ─── JSON Yardımcıları ──────────────────────────────────────────

/**
 * JSON string'i decode eder, hata varsa hata mesajını döndürür.
 * PHP 8.3+ json_validate() varsa onu tercih edin.
 */
function json_validate_custom($string) {
    $result = json_decode($string);

    if (json_last_error() === JSON_ERROR_NONE) {
        return $result;
    }

    $errors = [
        JSON_ERROR_DEPTH          => 'Maximum stack depth exceeded.',
        JSON_ERROR_STATE_MISMATCH => 'Invalid or malformed JSON.',
        JSON_ERROR_CTRL_CHAR      => 'Control character error.',
        JSON_ERROR_SYNTAX         => 'Syntax error, malformed JSON.',
        JSON_ERROR_UTF8           => 'Malformed UTF-8 characters.',
        JSON_ERROR_RECURSION      => 'Recursive references in value.',
        JSON_ERROR_INF_OR_NAN     => 'NAN or INF values in value.',
        JSON_ERROR_UNSUPPORTED_TYPE => 'Unsupported type.',
    ];

    return $errors[json_last_error()] ?? 'Unknown JSON error.';
}