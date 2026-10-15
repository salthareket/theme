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
    if (!\QueryCache::is_enabled() || (\QueryCache::get_config('menu') ?? true) === false) {
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
    // Önce SaltConfig PHP cache'inden oku (DB sorgusu yok)
    $sc_val = \SaltHareket\SaltConfig::get($field);
    if ( $sc_val !== false && $sc_val !== null ) {
        return $sc_val;
    }
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
 * Template App custom twig dosyasini dile gore coz.
 * post_id'den {post_id}_{lang}.twig arar, yoksa fallback'ler dener.
 *
 * @param int    $post_id  Template post ID
 * @param string $lang     Dil kodu (bos birakılırsa ml_get_current_language())
 * @return string|null     Timber'a gecilecek twig path (uzantisiz) veya null
 */
function get_template_twig_for_lang( int $post_id, string $lang = '' ): ?string {
    if ( $lang === '' ) {
        $lang = function_exists( 'ml_get_current_language' ) ? ml_get_current_language() : 'default';
    }

    $base_dir = get_stylesheet_directory() . '/theme/templates/_custom/';
    $twig_dir = 'theme/templates/_custom/';

    // Deneme sirasi: {id}_{lang} → {id}_default → {id}_
    $candidates = [
        $post_id . '_' . $lang,
        $post_id . '_default',
        $post_id . '_',
    ];

    foreach ( $candidates as $name ) {
        if ( file_exists( $base_dir . $name . '.twig' ) ) {
            return $twig_dir . $name;
        }
    }

    return null;
}

/**
 * template_validator( $twig_path, $type )
 * Bir twig dosyasinin belirli bir yapiya sahip olup olmadigini kontrol eder.
 * Twig fn() ile cagirilabilir: fn('template_validator', path, 'offcanvas')
 *
 * Types:
 *   'offcanvas' → root <div class="...offcanvas..." id="..."> + offcanvas-body iceriyor mu?
 *   'modal'     → root <div class="...modal..." id="..."> + modal-body iceriyor mu?
 *
 * Kullanim amaci: custom template dosyasinin kendi tam wrapper'ini icerip icermedigini
 * anlayarak gereksiz double-wrapper'i onlemek.
 *
 * @param string $twig_path  Timber twig path (uzantisiz)
 * @param string $type       'offcanvas' | 'modal'
 * @return bool
 */
function template_validator( string $twig_path, string $type = 'offcanvas' ): bool {
    static $cache = [];
    $cache_key = $twig_path . '|' . $type;
    if ( isset( $cache[ $cache_key ] ) ) return $cache[ $cache_key ];

    // Dosyayi bul — stylesheet dir veya template dir
    $abs = get_stylesheet_directory() . '/' . $twig_path . '.twig';
    if ( ! file_exists( $abs ) ) {
        // theme/templates/ altında ara
        $abs2 = get_stylesheet_directory() . '/theme/templates/' . ltrim( $twig_path, '/' ) . '.twig';
        if ( file_exists( $abs2 ) ) $abs = $abs2;
    }
    if ( ! file_exists( $abs ) ) {
        // Timber template path'lerinde ara
        if ( function_exists( 'get_timber_template_path' ) ) {
            $found = get_timber_template_path( '/' . ltrim( $twig_path, '/' ) . '.twig' );
            if ( $found ) $abs = $found;
        }
    }
    if ( ! file_exists( $abs ) ) return $cache[ $cache_key ] = false;

    $content = @file_get_contents( $abs );
    if ( ! $content ) return $cache[ $cache_key ] = false;

    switch ( $type ) {
        case 'offcanvas':
            // Root div: class icinde "offcanvas" + id attribute'u var
            // VE offcanvas-body class'i var
            $has_root = (bool) preg_match(
                '/<div\b[^>]*\bclass=["\'][^"\']*\boffcanvas\b[^"\']*["\'][^>]*\bid=["\'][^"\']+["\']|<div\b[^>]*\bid=["\'][^"\']+["\'][^>]*\bclass=["\'][^"\']*\boffcanvas\b[^"\']*["\']/',
                $content
            );
            $has_body = strpos( $content, 'offcanvas-body' ) !== false;
            return $cache[ $cache_key ] = ( $has_root && $has_body );

        case 'modal':
            $has_root = (bool) preg_match(
                '/<div\b[^>]*\bclass=["\'][^"\']*\bmodal\b[^"\']*["\'][^>]*\bid=["\'][^"\']+["\']|<div\b[^>]*\bid=["\'][^"\']+["\'][^>]*\bclass=["\'][^"\']*\bmodal\b[^"\']*["\']/',
                $content
            );
            $has_body = strpos( $content, 'modal-body' ) !== false;
            return $cache[ $cache_key ] = ( $has_root && $has_body );

        case 'dropdown':
            // Tam dropdown = root div class'inda dropdown-menu var + dropdown-body veya dropdown-container iceriyor
            $has_root = (bool) preg_match(
                '/<div\b[^>]*\bclass=["\'][^"\']*\bdropdown-menu\b[^"\']*["\']/',
                $content
            );
            $has_body = strpos( $content, 'dropdown-body' ) !== false
                     || strpos( $content, 'dropdown-container' ) !== false;
            return $cache[ $cache_key ] = ( $has_root && $has_body );

        default:
            return $cache[ $cache_key ] = false;
    }
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
 * Offcanvas HOST kaydi — twig'den cagrilir (register_offcanvas(...)).
 *
 * Bir host (button/page-settings/woo filter) kullandigi offcanvas'i OffcanvasRegistry'ye
 * kaydeder ve toggler'da kullanilacak benzersiz DOM id'sini dondurur.
 *
 * Icerik kaynagi (base) template id/slug ya da source adidir; ayni kaynak icin ayni id
 * uretilir → tek instance paylasimi + duplicate print engeli.
 *
 * @param string $base       template slug / source adi / template id (id turetme anahtari)
 * @param array  $settings   render_mode, method, position, visibility, backdrop, scrollable,
 *                           fullscreen, cached, class, title, template
 * @return string            benzersiz offcanvas DOM id'si ('sh-oc-...')
 */
function register_offcanvas_host($base = '', array $settings = []) {
    if ( ! class_exists( 'OffcanvasRegistry' ) ) return '';
    $base = (string) $base;
    if ( $base === '' ) return '';

    $id = OffcanvasRegistry::makeId( $base );

    OffcanvasRegistry::add( array_merge( $settings, [ 'id' => $id ] ) );

    return $id;
}

/**
 * Bos block/HTML elementlerini temizler.
 *
 * Gutenberg bazen bos <p class="wp-block-paragraph"></p> gibi anlamsiz elementler
 * ekliyor. Bu fonksiyon icerigi (whitespace, &nbsp;, <br>) disinda hicbir sey
 * icermeyen <p> ve heading (h1-h6) elementlerini kaldirir.
 *
 * @param string $html
 * @return string
 */
function sh_strip_empty_blocks( $html ) {
    if ( ! is_string( $html ) || $html === '' ) return $html;

    // Icerigi bos olan <p> ve h1-h6 elementlerini kaldir.
    // "Bos" = sadece whitespace, &nbsp; (0xA0 dahil), veya <br> iceren.
    $pattern = '#<(p|h[1-6])\b[^>]*>(?:\s|&nbsp;|\x{00A0}|<br\s*/?>)*</\1>#iu';

    // Ic ice bos wrapper'lari da yakalamak icin birkac gecis yap.
    $prev = null;
    $i    = 0;
    while ( $prev !== $html && $i < 3 ) {
        $prev = $html;
        $html = preg_replace( $pattern, '', $html );
        $i++;
    }

    return $html;
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
 * wp_states tablosundan belirli ülkenin state listesini döndürür.
 * Localization class olmasa da çalışır — direkt DB query.
 *
 * @param string $country_code ISO2 ülke kodu
 * @return array  [ ['id'=>int, 'name'=>string], ... ]
 */
function salt_get_states( string $country_code ): array {
    global $wpdb;
    static $cache = [];
    $code = strtoupper( trim( $country_code ) );
    if ( empty( $code ) ) return [];
    if ( isset( $cache[ $code ] ) ) return $cache[ $code ];

    $table = $wpdb->prefix . 'states';
    if ( ! $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) ) {
        $cache[ $code ] = [];
        return [];
    }

    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, name FROM {$table} WHERE country_code = %s ORDER BY name ASC",
        $code
    ), ARRAY_A );

    $cache[ $code ] = $rows ?: [];
    return $cache[ $code ];
}
function localization() {
    if (!class_exists('Localization')) return null;
    $loc = new Localization();
    $loc->woocommerce_support = true;
    return $loc;
}

/**
 * wp_countries tablosundan ülke listesi döndürür.
 * Localization class olmasa da çalışır — direkt DB query.
 *
 * @return array  [ ['iso2'=>'TR','name'=>'Turkey','phonecode'=>'90','states'=>12], ... ]
 */
function salt_get_countries(): array {
    global $wpdb;
    static $cache = null;
    if ( $cache !== null ) return $cache;

    $table = $wpdb->prefix . 'countries';
    if ( ! $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) ) {
        $cache = [];
        return $cache;
    }

    $rows = $wpdb->get_results(
        "SELECT c.iso2, c.name, c.phonecode,
                (SELECT COUNT(*) FROM {$wpdb->prefix}states s WHERE s.country_code = c.iso2) AS states
         FROM {$table} c
         ORDER BY c.name ASC",
        ARRAY_A
    );

    $cache = $rows ?: [];
    return $cache;
}