<?php

/**
 * Timber & Template Helper Functions
 *
 * Timber wrapper'ları, QueryCache entegrasyonu, Bootstrap grid,
 * menü yardımcıları ve template utilities.
 */

// ─── Timber Menü (QueryCache Entegrasyonlu) ─────────────────────

/**
 * Timber menüsünü 3 katmanlı cache ile döndürür:
 * 1. Static RAM cache (aynı request içinde)
 * 2. DB cache (wp_options tablosu)
 * 3. Timber::get_menu() fallback
 */
function timber_get_menu($name) {
    global $wpdb;

    static $cache = [];

    $lang = function_exists('ml_get_current_language') ? ml_get_current_language() : get_locale();
    $key  = '_transient_qcache_menu_' . $name . '_' . $lang;

    // 1. RAM cache
    if (isset($cache[$key])) return $cache[$key];

    // 2. Cache kapalıysa direkt Timber
    if (!\QueryCache::$cache || (\QueryCache::$config['menu'] ?? true) === false) {
        return Timber::get_menu($name);
    }

    // 3. DB cache
    $raw = $wpdb->get_var($wpdb->prepare(
        "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
        $key
    ));

    if ($raw) {
        $menu = unserialize($raw);
        $cache[$key] = $menu;
        return $menu;
    }

    // 4. Timber'dan oluştur → DB + RAM'e yaz
    $menu = Timber::get_menu($name);
    if ($menu) {
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->options} (option_name, option_value, autoload)
             VALUES (%s, %s, 'no')
             ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)",
            $key, serialize($menu)
        ));
        $cache[$key] = $menu;
    }

    return $menu;
}

// ─── Timber Shortcut Wrapper'ları ───────────────────────────────

function timber_image($id) {
    return new Timber\Image($id);
}

function _post_query($args) {
    return new Timber\PostQuery($args);
}

function _get_field($field, $post_id) {
    return \QueryCache::get_field($field, $post_id);
}

function _get_option($field) {
    return \QueryCache::get_option($field);
}

function _get_option_cpt($field, $post_type) {
    return get_field($field, $post_type . '_options');
}

function _get_widgets($widget) {
    return Timber::get_widgets($widget);
}

function _get_page($slug = '') {
    $page = get_page_by_path($slug);
    return $page ? Timber::get_post($page->ID) : null;
}

/**
 * Birden fazla post'un belirli bir meta key değerlerini toplar.
 *
 * @param string    $key   Meta key
 * @param array|int $posts Post ID dizisi veya post object dizisi veya tekil ID
 */
function _get_meta($key, $posts) {
    if (!is_array($posts)) {
        $posts = [$posts];
    }

    $ids = isset($posts[0]['ID']) ? wp_list_pluck($posts, 'ID') : $posts;

    return array_map(fn($id) => get_post_meta($id, $key, true), $ids);
}

/**
 * Belirli taxonomy term'ine ait post'ları döndürür.
 */
function _get_tax_posts($post_type, $taxonomy, $taxonomy_id, $post_count = -1) {
    return Timber::get_posts([
        'post_type'   => $post_type,
        'numberposts' => $post_count,
        'tax_query'   => [[
            'taxonomy' => $taxonomy,
            'field'    => 'id',
            'terms'    => $taxonomy_id,
        ]],
    ]);
}

// ─── Matematik ──────────────────────────────────────────────────

/**
 * Güvenli bölme. Sıfıra bölme durumunda null döner.
 */
function division($a, $b) {
    return ($b == 0) ? null : $a / $b;
}

// ─── Menü Yardımcıları ──────────────────────────────────────────

/**
 * Menüdeki ilk page tipli item'ın parent post'unu döndürür.
 */
function get_menu_parent($menu) {
    foreach ($menu as $item) {
        if ($item->object === 'page' && $item->post_parent > 0) {
            return new Timber\Post($item->post_parent);
        }
    }
    return null;
}

// ─── Bootstrap Grid ─────────────────────────────────────────────

/**
 * Breakpoint → kolon sayısı map'inden BS grid class'ları üretir.
 * Örn: ['xs' => 1, 'md' => 3] → "col-12 col-md-4"
 */
function get_bs_grid($sizes) {
    if (empty($sizes)) return '';

    $classes = [];
    foreach ($sizes as $bp => $cols) {
        if (!isset($cols) || $cols == 0) continue;

        $prefix = ($bp === 'xs') ? '-' : "-{$bp}-";
        $span   = 12 / $cols;

        $classes[] = is_int($span)
            ? "col{$prefix}{$span}"
            : "col{$prefix}1{$cols}";
    }
    return implode(' ', $classes);
}

/**
 * Breakpoint → gap map'inden BS row gap class'ları üretir.
 */
function get_bs_grid_gap($sizes) {
    if (empty($sizes)) return '';

    $classes = [];
    foreach ($sizes as $bp => $size) {
        if (!isset($size)) continue;
        $prefix    = ($bp === 'xs') ? '-' : "-{$bp}-";
        $classes[] = "row{$prefix}{$size}";
    }
    return implode(' ', $classes);
}

// ─── Template Yardımcıları ──────────────────────────────────────

/**
 * Post'un WP page template'ini yükler.
 */
function _get_template($post) {
    set_query_var('template_post_id', $post->ID);
    $template = get_template_directory() . '/' . $post->_wp_page_template;
    if (file_exists($template)) {
        return load_template($template, false);
    }
    return null;
}

/**
 * Timber template dizinlerinde .twig dosyası arar.
 */
function get_timber_template_path($path) {
    foreach ((array) \Timber::$dirname as $location) {
        $base = trailingslashit(get_stylesheet_directory()) . trailingslashit($location);
        $full = $base . $path;

        if (file_exists($full) && pathinfo($full, PATHINFO_EXTENSION) === 'twig') return $full;
        if (is_dir($full) && !empty(glob(trailingslashit($full) . '*.twig'))) return $full;
    }
    return false;
}

/**
 * HTML DOM'da element bulup class ekler (simple_html_dom gerektirir).
 */
function _addClass($code, $find, $contains = '', $class = '') {
    if (empty($code)) return $code;

    $html = new simple_html_dom();
    $html->load($code);
    $el = $html->find($find, 0);

    if ($el) {
        if ($contains) {
            if ($el->find($contains, 0)) $el->class = $class;
        } else {
            $el->class = $class;
        }
    }
    return $html;
}

// ─── UI Yardımcıları ────────────────────────────────────────────

function pluralize($count, $singular = '', $plural = '', $null = '', $theme = '') {
    return trans_plural($singular, $plural, $null, $count, $theme);
}

/**
 * Bootstrap offcanvas toggler button HTML'i üretir.
 */
function get_offcanvas_toggler($id = '', $class = '', $content = '', $title = '') {
    $id    = esc_attr($id);
    $class = esc_attr($class);
    $title = esc_attr($title);
    return "<button type=\"button\" class=\"{$class}\" data-bs-toggle=\"offcanvas\" data-bs-target=\"#{$id}\" aria-label=\"{$title}\">{$content}</button>";
}

/**
 * Array'i shuffle edip döndürür (orijinali bozmaz).
 */
function array_shuffle($array = []) {
    shuffle($array);
    return $array;
}

/**
 * WordPress filter'a sabit değer atar.
 */
function timber_add_filter($filter, $value) {
    add_filter($filter, fn() => $value);
}

/**
 * Localization sınıfı instance'ı döndürür.
 */
function localization() {
    if (!class_exists('Localization')) return null;
    $loc = new Localization();
    $loc->woocommerce_support = true;
    return $loc;
}