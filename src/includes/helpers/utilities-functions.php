<?php

/**
 * Utility Functions
 *
 * Boolean, renk, link, tarih, dosya, koordinat ve genel amaçlı yardımcılar.
 */

// ─── Boolean & Tip Yardımcıları ─────────────────────────────────

if (!function_exists('boolval')) {
    function boolval($val) { return (bool) $val; }
}

function boolstr($val = false) {
    return filter_var($val, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
}

function is_true($val, $return_null = false) {
    $boolval = is_string($val)
        ? filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
        : (bool) $val;
    return ($boolval === null && !$return_null) ? false : $boolval;
}

function isJson($string) {
    json_decode($string);
    return json_last_error() === JSON_ERROR_NONE;
}

function is_odd($num)  { return (bool) ($num & 1); }
function is_even($num) { return !($num & 1); }

// ─── Rastgele & Unique ──────────────────────────────────────────

function get_random_number($min, $max) {
    return rand($min, $max);
}

function unique_code($limit) {
    return substr(base_convert(sha1(uniqid(mt_rand())), 16, 36), 0, $limit);
}

function unicode_decode($str) {
    return preg_replace_callback(
        '/\\\\u([0-9a-fA-F]{4})/',
        fn($m) => mb_convert_encoding(pack('H*', $m[1]), 'UTF-8', 'UCS-2BE'),
        $str
    );
}

// ─── Renk Yardımcıları ──────────────────────────────────────────

/**
 * Hex renk kodunu [r, g, b] array'ine çevirir.
 * 3 veya 6 karakterli hex ve "transparent" destekler.
 */
function hex2rgb($hex) {
    $hex = trim(str_replace('#', '', strtolower($hex)));

    if ($hex === 'transparent') return [0, 0, 0];

    if (strlen($hex) === 3) {
        return [
            hexdec(str_repeat($hex[0], 2)),
            hexdec(str_repeat($hex[1], 2)),
            hexdec(str_repeat($hex[2], 2)),
        ];
    }

    if (strlen($hex) === 6) {
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    return null;
}

function hex2rgbValues($hex) {
    $rgb = hex2rgb($hex);
    return $rgb ? implode(',', $rgb) : '0,0,0';
}

function make_rgba($hex, $alpha = 1) {
    $rgb = hex2rgb($hex);
    if (!$rgb) return "rgba(0,0,0,{$alpha})";
    return 'rgba(' . implode(',', $rgb) . ',' . $alpha . ')';
}

/**
 * Dikey gradient CSS üretir. Modern tarayıcılar için sadece standard syntax.
 */
function make_gradient_vertical($color_1, $color_2, $color_1_alpha = null, $color_2_alpha = null) {
    $c1 = ($color_1_alpha !== null) ? make_rgba($color_1, $color_1_alpha) : $color_1;
    $c2 = ($color_2_alpha !== null) ? make_rgba($color_2, $color_2_alpha) : $color_2;
    return "background: {$c1}; background: linear-gradient(to bottom, {$c1} 0%, {$c2} 100%);";
}

function phpcolors($color, $method = 'getRgb', $amount = 0) {
    $c = new Mexitek\PHPColors\Color($color);
    return $c->$method();
}

function get_image_average_color($image_path) {
    return class_exists('ImageColor') ? ImageColor::fromFile($image_path) : false;
}

function calculate_contrast_color($color) {
    [$r, $g, $b] = sscanf($color, "#%02x%02x%02x");
    if (class_exists('ImageColor')) return ImageColor::contrastColor($r, $g, $b);
    return ((0.299 * $r + 0.587 * $g + 0.114 * $b) > 128) ? '#000000' : '#ffffff';
}

function calculate_contrast_color_mode($color) {
    return calculate_contrast_color($color) === '#000000' ? 'light' : 'dark';
}

// ─── Link Üretici Yardımcıları ──────────────────────────────────

function phone_link($phone, $class = '', $title = '') {
    $display = (!empty($title)) ? $title : $phone;
    $href    = filter_var($phone, FILTER_SANITIZE_NUMBER_INT);
    $class   = esc_attr($class);
    return "<a href=\"tel:{$href}\" class=\"{$class}\">{$display}</a>";
}

function email_link($email, $class = '', $title = '') {
    $display = (!empty($title)) ? $title : $email;
    $email   = esc_attr($email);
    $class   = esc_attr($class);
    return "<a href=\"mailto:{$email}\" class=\"{$class}\">{$display}</a>";
}

function url_link($link = '', $target = '_self', $title = '', $remove_protocol = false, $remove_www = false, $class = '', $text = '', $domain_only = false) {
    $link  = rtrim($link, '/');
    $label = (!empty($title)) ? $title : $link;

    if ($remove_protocol) $label = preg_replace('#^https?://#', '', $label);
    if ($remove_www)      $label = str_replace('www.', '', $label);
    if ($domain_only)     $label = parse_url($link, PHP_URL_HOST) ?: $label;

    $rel = ($target === '_blank') ? ' rel="nofollow noopener"' : '';
    return sprintf('<a href="%s" class="%s" target="%s"%s>%s%s</a>',
        esc_url($link), esc_attr($class), esc_attr($target), $rel, $label, $text
    );
}

/**
 * Sosyal medya hesaplarını liste olarak render eder.
 */
function list_social_accounts($accounts = [], $class = '', $hover = false) {
    if (empty($accounts)) return '';

    $items = '';
    foreach ($accounts as $acc) {
        $link = $acc['url'];
        $name = $acc['name'];

        if ($name === 'whatsapp') {
            $link = 'https://wa.me/' . str_replace('+', '', filter_var($link, FILTER_SANITIZE_NUMBER_INT));
        }

        $icon_suffix = ($name === 'facebook') ? '-f' : '';
        $hover_class = $hover ? "btn-social-{$name}-hover" : '';

        $items .= sprintf(
            '<li class="list-inline-item"><a href="%s" class="%s" title="%s" target="_blank" rel="nofollow" itemprop="sameAs"><i class="fab fa-%s%s fa-fw"></i></a></li>',
            esc_url($link), esc_attr($hover_class), esc_attr($name), esc_attr($name), $icon_suffix
        );
    }

    return "<ul class=\"" . esc_attr($class) . " list-social list-inline\">{$items}</ul>";
}

/**
 * Metnin son kelimesini belirtilen tag ile sarar.
 */
function wrap_last($text, $tag) {
    if (empty($text)) return $text;

    $words = preg_split('/\s+/', trim($text));
    if (count($words) <= 1) return $text;

    $last = array_pop($words);
    return implode(' ', $words) . " <{$tag}>{$last}</{$tag}>";
}


// ─── Tarih & Zaman ──────────────────────────────────────────────

function date_to_iso8601($date = '', $timezone = '') {
    $dt = date_create($date);
    if (!$dt) return '';
    if (!empty($timezone)) $dt->setTimezone(new DateTimeZone($timezone));
    return $dt->format('c');
}

function time_to_iso8601_duration($time) {
    $seconds = strtotime($time, 0);
    if ($seconds === false) return 'PT0S';

    $units = ['Y' => 365*86400, 'D' => 86400, 'H' => 3600, 'M' => 60, 'S' => 1];
    $str   = 'P';
    $inT   = false;

    foreach ($units as $name => $divisor) {
        $qty      = intval($seconds / $divisor);
        $seconds -= $qty * $divisor;
        if ($qty > 0) {
            if (!$inT && in_array($name, ['H', 'M', 'S'])) { $str .= 'T'; $inT = true; }
            $str .= $qty . $name;
        }
    }

    return $str;
}

function hour_to_timestamp($hour = '00:00') {
    [$h, $m] = explode(':', $hour);
    return mktime((int)$h, (int)$m, 0, 0, 0, 0);
}

function hour_to_date($hour = '00:00') {
    return date('Y-m-d H:i', hour_to_timestamp($hour));
}

function hour_to_number($time) {
    [$h, $m] = explode(':', $time);
    return (int)$h * 60 + (int)$m;
}

function getMonthName($month, $format = 'MMM') {
    try {
        $dt   = new DateTime("2024-{$month}-01");
        $fmt  = new IntlDateFormatter(get_locale(), IntlDateFormatter::FULL, IntlDateFormatter::NONE);
        $fmt->setPattern($format);
        return $fmt->format($dt);
    } catch (Exception $e) { return ''; }
}

function getDayName($day, $format = 'EEEE') {
    try {
        $dt   = new DateTime("2024-01-{$day}");
        $fmt  = new IntlDateFormatter(get_locale(), IntlDateFormatter::FULL, IntlDateFormatter::NONE);
        $fmt->setPattern($format);
        return $fmt->format($dt);
    } catch (Exception $e) { return ''; }
}

// ─── Dosya & Dizin ──────────────────────────────────────────────

/**
 * Byte cinsinden dosya boyutunu okunabilir formata çevirir.
 */
function convert_filesize($bytes = 0, $decimals = 2) {
    $bytes = (int) $bytes;
    if ($bytes <= 0) return '0 B';

    $units  = ['B', 'kB', 'MB', 'GB', 'TB', 'PB'];
    $factor = (int) floor(log($bytes, 1024));
    $factor = min($factor, count($units) - 1);

    return sprintf("%.{$decimals}f %s", $bytes / pow(1024, $factor), $units[$factor]);
}

function get_extension($file) {
    if (empty($file) || !is_string($file)) return false;
    return strtolower(pathinfo($file, PATHINFO_EXTENSION)) ?: false;
}

function get_iframe_src($input) {
    preg_match('/<iframe[^>]+src="([^"]+)"/', $input, $m);
    return $m[1] ?? '';
}

/**
 * Dizini recursive olarak siler.
 */
function rmdir_all($dir) {
    if (!is_dir($dir)) return;
    $it    = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
    }
    rmdir($dir);
}

function deleteFolder($dir) {
    if (!is_dir($dir)) return;
    foreach (array_diff(scandir($dir), ['.', '..']) as $item) {
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        is_dir($path) ? deleteFolder($path) : unlink($path);
    }
    rmdir($dir);
}

function copyFolder($src, $dest, $exclude = []) {
    if (!is_dir($dest)) mkdir($dest, 0755, true);

    $dir = opendir($src);
    while (false !== ($file = readdir($dir))) {
        if ($file === '.' || $file === '..') continue;
        if (in_array($file, $exclude)) continue;

        $srcPath  = $src . DIRECTORY_SEPARATOR . $file;
        $destPath = $dest . DIRECTORY_SEPARATOR . $file;

        is_dir($srcPath) ? copyFolder($srcPath, $destPath, $exclude) : copy($srcPath, $destPath);
    }
    closedir($dir);
}

function copyFile($source, $destination) {
    if (!file_exists($source)) return;
    $dir = dirname($destination);
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    copy($source, $destination);
}

function moveFolder($src, $dst) {
    if (!is_dir($src)) return false;
    try {
        copyFolder($src, $dst);
        deleteFolder($src);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// ─── MIME & Dosya Tipi ──────────────────────────────────────────

function mime2ext($mime) {
    static $map = null;
    if ($map === null) {
        $map = [
            'video/3gpp2' => '3g2', 'video/3gp' => '3gp', 'video/3gpp' => '3gp',
            'application/x-compressed' => '7zip', 'audio/x-acc' => 'aac', 'audio/ac3' => 'ac3',
            'application/postscript' => 'ai', 'audio/x-aiff' => 'aif', 'audio/aiff' => 'aif',
            'video/x-msvideo' => 'avi', 'video/msvideo' => 'avi', 'video/avi' => 'avi',
            'image/bmp' => 'bmp', 'image/x-bmp' => 'bmp', 'image/x-ms-bmp' => 'bmp',
            'text/css' => 'css', 'text/x-comma-separated-values' => 'csv',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/msword' => 'doc', 'image/gif' => 'gif',
            'application/x-gzip' => 'gzip', 'text/html' => 'html',
            'image/x-icon' => 'ico', 'image/vnd.microsoft.icon' => 'ico',
            'text/calendar' => 'ics', 'application/java-archive' => 'jar',
            'image/jp2' => 'jp2', 'image/jpeg' => 'jpg', 'image/pjpeg' => 'jpg',
            'application/x-javascript' => 'js', 'application/json' => 'json', 'text/json' => 'json',
            'audio/x-m4a' => 'm4a', 'audio/midi' => 'mid', 'video/quicktime' => 'mov',
            'audio/mpeg' => 'mp3', 'audio/mp3' => 'mp3', 'video/mp4' => 'mp4', 'video/mpeg' => 'mpeg',
            'audio/ogg' => 'ogg', 'video/ogg' => 'ogg', 'application/ogg' => 'ogg',
            'application/pdf' => 'pdf', 'application/octet-stream' => 'pdf',
            'image/png' => 'png', 'image/x-png' => 'png',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'image/vnd.adobe.photoshop' => 'psd',
            'application/x-rar' => 'rar', 'application/rar' => 'rar',
            'application/x-rar-compressed' => 'rar',
            'text/rtf' => 'rtf', 'image/svg+xml' => 'svg',
            'application/x-tar' => 'tar', 'image/tiff' => 'tiff', 'text/plain' => 'txt',
            'text/vtt' => 'vtt', 'audio/x-wav' => 'wav', 'audio/wav' => 'wav',
            'video/webm' => 'webm', 'audio/x-ms-wma' => 'wma',
            'video/x-ms-wmv' => 'wmv', 'video/x-ms-asf' => 'wmv',
            'application/xhtml+xml' => 'xhtml',
            'application/vnd.ms-excel' => 'xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/xml' => 'xml', 'text/xml' => 'xml',
            'application/x-zip' => 'zip', 'application/zip' => 'zip',
            'application/x-zip-compressed' => 'zip',
        ];
    }
    return $map[$mime] ?? false;
}


// ─── Koordinat Dönüşümleri ──────────────────────────────────────

/**
 * DMS (Derece°Dakika'Saniye") formatını decimal'e çevirir.
 */
function convertCoordinate($coordinates) {
    $parts = explode(' ', $coordinates);
    return [
        'lat' => convertToDecimal($parts[0]),
        'lng' => convertToDecimal($parts[1] ?? $parts[0]),
    ];
}

function convertToDecimal($coordinate) {
    if (!preg_match('/(\d+)°(\d+)\'([\d.]+)"/', $coordinate, $m)) return 0.0;
    return (int)$m[1] + ((int)$m[2] / 60) + ((float)$m[3] / 3600);
}

function decimalToDMS($lat, $lon) {
    $fmt = function($val, $pos, $neg) {
        $dir = ($val >= 0) ? $pos : $neg;
        $abs = abs($val);
        $deg = floor($abs);
        $min = floor(($abs - $deg) * 60);
        $sec = ($abs - $deg - $min / 60) * 3600;
        return sprintf("%02d°%02d'%04.1f\"%s", $deg, $min, $sec, $dir);
    };
    return $fmt($lat, 'N', 'S') . ' ' . $fmt($lon, 'E', 'W');
}

// ─── Sıralama ───────────────────────────────────────────────────

/**
 * Object array'i belirli bir property'ye göre sıralar.
 * Türkçe locale desteği ile.
 */
function objectSort($obj, $sortBy, $sort = 'desc', $numeric = false) {
    $dir = ($sort === 'asc') ? 1 : -1;

    usort($obj, function($a, $b) use ($sortBy, $dir, $numeric) {
        if ($numeric) {
            $av = is_numeric($a->$sortBy) ? $a->$sortBy : 0;
            $bv = is_numeric($b->$sortBy) ? $b->$sortBy : 0;
            return ($av - $bv) * $dir;
        }

        setlocale(LC_COLLATE, 'tr_TR.utf8', 'tr_TR.utf-8', 'tr_TR', 'turkish', 'en_US.utf8');
        $result = strcoll(mb_strtolower($a->$sortBy, 'UTF-8'), mb_strtolower($b->$sortBy, 'UTF-8'));
        return ($result <=> 0) * $dir;
    });

    return $obj;
}

// ─── Zaman Aralığı Yardımcıları ─────────────────────────────────

/**
 * Günden belirtilen zaman aralıklarını çıkarır, kalan aralıkları döndürür.
 */
function removeRangesFromDay($specifiedIntervals = []) {
    // Tüm dakikaları set olarak oluştur
    $all = [];
    for ($m = 0; $m < 1440; $m++) $all[$m] = true;

    // Belirtilen aralıkları çıkar
    foreach ($specifiedIntervals as $interval) {
        $start = (int) strtotime($interval['start']);
        $end   = (int) strtotime($interval['end']);
        for ($t = $start; $t <= $end; $t += 60) {
            $min = ((int) date('H', $t)) * 60 + (int) date('i', $t);
            unset($all[$min]);
        }
    }

    // Kalan dakikaları aralıklara dönüştür
    $result  = [];
    $current = null;
    foreach ($all as $min => $_) {
        $time = sprintf('%02d:%02d', intdiv($min, 60), $min % 60);
        if ($current === null) {
            $current = ['start' => $time, 'end' => $time];
        } elseif ($min === hour_to_number($current['end']) + 1) {
            $current['end'] = $time;
        } else {
            $result[] = $current;
            $current  = ['start' => $time, 'end' => $time];
        }
    }
    if ($current) $result[] = $current;

    return $result;
}

/**
 * Çakışan/bitişik zaman aralıklarını birleştirir.
 */
function mergeRangeConflicts($ranges) {
    // start == end olanları filtrele
    $ranges = array_values(array_filter($ranges, fn($r) => $r['start'] !== $r['end']));
    if (count($ranges) <= 1) return $ranges;

    $result = [$ranges[0]];
    for ($i = 1; $i < count($ranges); $i++) {
        $prev = &$result[count($result) - 1];
        $curr = $ranges[$i];

        if ($prev['end'] >= $curr['start']) {
            $prev['end'] = max($prev['end'], $curr['end']);
        } else {
            $result[] = $curr;
        }
    }
    return $result;
}

// ─── Değişken Yardımcıları ──────────────────────────────────────

/**
 * Array'den güvenli değer çıkarır, opsiyonel olarak array'e veya explode'a çevirir.
 */
function vars_fix($vars = [], $var = '', $is_array = false, $seperator = '') {
    $val = $vars[$var] ?? '';
    if (empty($val)) return $val;

    if ($is_array && !is_array($val)) return [$val];
    if (!empty($seperator) && !$is_array) return explode($seperator, $val);

    return $val;
}

// ─── HTML Üretici ───────────────────────────────────────────────

function array2List($array = [], $class = '', $tag = 'ul') {
    if (empty($array)) return '';

    $items = '';
    $item_class = $class ? "{$class}-item" : '';
    foreach ($array as $item) {
        $items .= "<li class=\"{$item_class}\">{$item}</li>";
    }
    return "<{$tag} class=\"{$class}\">{$items}</{$tag}>";
}

/**
 * Metin içindeki dosya linkini icon'lu HTML link'e çevirir.
 */
function convertToLink($inputString) {
    if (!preg_match('/\b(?:https?|ftp):\/\/\S+\.(jpg|jpeg|png|gif|pdf|docx|xlsx)\b/i', $inputString, $m)) {
        return $inputString;
    }
    $url = esc_url($m[0]);
    $ext = strtolower($m[1]);
    return "<a href=\"{$url}\" target=\"_blank\" class=\"text-primary\"><i class=\"icon fal fa-file-{$ext} fa-2x\"></i></a>";
}

// ─── İçerik Yardımcıları ────────────────────────────────────────

function get_post_read_time($content = '', $unit = 'min') {
    $words = str_word_count(strip_tags($content));
    $wpm   = 250;

    if (!in_array($unit, ['sec', 'min'])) $unit = 'min';
    if (ceil($words / $wpm) <= 1) $unit = 'sec';

    return ($unit === 'sec')
        ? ceil(($words * 60) / $wpm) . ' sec'
        : ceil($words / $wpm) . ' min';
}

function get_page_number($link) {
    return preg_match('/page\/(\d+)/', $link, $m) ? (int) $m[1] : null;
}

// ─── CSS Safelist ───────────────────────────────────────────────

function update_dynamic_css_whitelist($arr = []) {
    $path = get_template_directory() . '/theme/static/data/css_safelist.json';

    $data = [];
    if (file_exists($path)) {
        $json = json_decode(file_get_contents($path), true);
        $data = $json['dynamicSafelist'] ?? [];
    }

    $data = array_values(array_unique(array_merge($data, $arr)));
    file_put_contents($path, json_encode(['dynamicSafelist' => $data], JSON_PRETTY_PRINT));
}

// ─── SearchHistory Delegasyonu ──────────────────────────────────

function did_you_mean_search($input = '', $max_distance = 2) {
    if (!class_exists('SearchHistory')) return '';
    return (new SearchHistory())->did_you_mean($input, $max_distance) ?? '';
}

function search_suggestions($term = '', $count = 5) {
    if (empty($term) || !class_exists('SearchHistory')) return [];
    return (new SearchHistory())->suggestions($term, $count);
}

// ─── Çeviri Yükleme ────────────────────────────────────────────

function check_and_load_translation($textdomain, $locale = null) {
    $locale = $locale ?: determine_locale();
    $paths  = [
        WP_LANG_DIR . "/themes/{$textdomain}-{$locale}.mo",
        get_template_directory() . "/languages/{$locale}.mo",
        get_stylesheet_directory() . "/languages/{$locale}.mo",
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) load_textdomain($textdomain, $path);
    }
}

/**
 * modal_get_plugins_req
 * Post'un asset meta'sından plugin → init_func haritasını döndürür.
 * custom_modal ve template_modal PHP'lerinde tekrar eden mantık buraya taşındı.
 *
 * @param  int|WP_Post $post_id  Post ID veya WP_Post objesi
 * @return array                 [ 'leaflet' => 'init_leaflet', ... ]
 */
function modal_get_plugins_req( $post_id ): array {
    if ( ! function_exists('compile_files_config') ) {
        if ( defined('SH_INCLUDES_PATH') && file_exists( SH_INCLUDES_PATH . 'minify-rules.php' ) ) {
            require_once SH_INCLUDES_PATH . 'minify-rules.php';
        } else {
            return [];
        }
    }

    $post_id   = is_object($post_id) ? $post_id->ID : (int) $post_id;
    $assets    = get_post_meta( $post_id, 'assets', true );
    $plugins   = is_array($assets) ? ( $assets['plugins'] ?? [] ) : [];

    if ( empty($plugins) ) return [];

    $plugins_all = compile_files_config()['js']['plugins'] ?? [];
    $result      = [];

    foreach ( $plugins as $plugin ) {
        if ( isset( $plugins_all[$plugin]['init'] ) ) {
            $result[$plugin] = $plugins_all[$plugin]['init'];
        }
    }

    return $result;
}

/**
 * modal_json_output
 * Tüm modal PHP'lerinde tekrar eden json_encode + die() kalıbını tek noktaya toplar.
 * title varsa title+body, yoksa content formatında döner.
 *
 * @param string $html        Modal içeriği
 * @param array  $plugins_req [ 'leaflet' => 'init_leaflet', ... ]
 * @param array  $vars        Ajax vars (title için)
 * @param bool   $error
 * @param string $message
 */
function modal_json_output( string $html, array $plugins_req = [], array $vars = [], bool $error = false, string $message = '' ): void {
    $data = isset($vars['title'])
        ? [ 'title' => $vars['title'], 'body'    => $html, 'plugins' => $plugins_req ]
        : [ 'content' => $html,        'plugins' => $plugins_req ];

    echo json_encode([
        'error'   => $error,
        'message' => $message,
        'html'    => '',
        'data'    => $data,
    ]);
    die();
}
