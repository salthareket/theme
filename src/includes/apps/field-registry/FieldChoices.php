<?php

namespace SaltHareket\FieldRegistry;

/**
 * FieldChoices — Dinamik ACF & Custom Admin Field Seçenek Sağlayıcısı
 *
 * Tüm dinamik dropdown/select verilerini tek yerden üretir.
 * Her metod **static cache** kullanır: aynı PHP request'i içinde
 * aynı sorgu / disk okuması yalnızca 1 kez gerçekleşir.
 *
 * Kullanım Alanları:
 *   1. FieldRegistry üzerinden ACF `acf/load_field` hook'unda otomatik
 *   2. Custom admin sayfalarında doğrudan çağrı ile
 *   3. FieldRegistry::choices() wrapper'ı üzerinden
 *
 * ─── HOW TO USE ────────────────────────────────────────────────────────────
 *
 * // A) ACF field'ına choices otomatik inject (wrapper class yeterli):
 * //    ACF > Field > Wrapper Class: "acf-post-types"
 * //    → FieldRegistry otomatik olarak FieldChoices::postTypes() çağırır.
 *
 * // B) Custom admin sayfasında doğrudan kullanım:
 * use SaltHareket\FieldRegistry\FieldChoices;
 *
 * $post_types  = FieldChoices::postTypes();          // ['post' => 'Posts', ...]
 * $taxonomies  = FieldChoices::taxonomies();         // ['category' => 'Categories', ...]
 * $menu_names  = FieldChoices::menuNames();          // dilli varyantlar filtreli
 * $breakpoints = FieldChoices::breakpoints();        // ['xs' => 'xs', 'sm' => 'sm', ...]
 * $languages   = FieldChoices::languages();          // ['tr' => 'Türkçe', ...]
 * $fonts       = FieldChoices::fontFamilies();       // system + YABE custom fonts
 * $templates   = FieldChoices::templatePosts('footer'); // footer template postları
 *
 * // C) FieldRegistry::choices() wrapper'ı (tip adı ile):
 * use SaltHareket\FieldRegistry\FieldRegistry;
 *
 * $post_types = FieldRegistry::choices('post-types');
 * $tax        = FieldRegistry::choices('taxonomies');
 * $tax_for_pt = FieldRegistry::choices('taxonomies-for-post-type', ['post_type' => 'post']);
 * $menus      = FieldRegistry::choices('menu-names');                  // filtreli
 * $menus_all  = FieldRegistry::choices('menu-names', ['filter_lang' => false]); // filtresiz
 *
 * ─── YENİ METOD EKLEME ─────────────────────────────────────────────────────
 *
 * 1. Bu dosyaya yeni bir static metod ekle:
 *
 *    public static function myCustomData(): array {
 *        static $c = null;
 *        if ( $c !== null ) return $c;   // cache — sorgu 1 kez çalışır
 *        $c = [];
 *        // ... veri doldur ...
 *        return $c;
 *    }
 *
 * 2. FieldRegistry.php > registerDefaults() içine resolver ekle:
 *
 *    self::register('acf-my-custom-class', function ( array $f ) use ($C): array {
 *        $f['type']    = 'select';
 *        $f['choices'] = $C::myCustomData();
 *        return $f;
 *    });
 *
 * 3. (Opsiyonel) FieldRegistry::choices() match bloğuna kısa ad ekle:
 *
 *    'my-custom-data' => $C::myCustomData(),
 *
 * 4. ACF field'ında wrapper class olarak "acf-my-custom-class" yaz → bitti.
 *    Custom admin sayfasında: FieldChoices::myCustomData() veya
 *                             FieldRegistry::choices('my-custom-data')
 *
 * ─── MEVCUT METODLAR & RETURN DEĞERLERİ ───────────────────────────────────
 *
 *  Tüm metodlar ['value' => 'Label', ...] formatında array döndürür.
 *  ACF select choices'ı için key=kayıt değeri, value=görünen metin.
 *
 *  languages()
 *    → ['tr' => 'Türkçe', 'en' => 'English', ...]
 *    Polylang/qTranslate dillerini döndürür. Lang kodu key, dil adı value.
 *    Kaynak: get_all_languages(true) — Polylang/qT bağımlı.
 *
 *  breakpoints( bool $include_none = false )
 *    → ['xs' => 'xs', 'sm' => 'sm', 'md' => 'md', 'lg' => 'lg', 'xl' => 'xl', 'xxl' => 'xxl']
 *    $include_none=true ise başına ['' => 'None'] eklenir.
 *    Kaynak: Data::get('breakpoints') — tema ayarlarından.
 *
 *  postTypes( bool $public_only = true )
 *    → ['post' => 'Posts', 'page' => 'Pages', 'product' => 'Products', ...]
 *    Post type slug key, label value. $public_only=false tüm type'ları getirir.
 *    Kaynak: get_post_types()
 *
 *  postTypesWithArchive()
 *    → ['product' => 'Products', 'portfolio' => 'Portfolio', ...]
 *    Yalnızca has_archive=true olan public post type'lar.
 *    Kaynak: get_post_types(['public'=>true])
 *
 *  taxonomies( bool $public_only = true )
 *    → ['category' => 'Categories', 'post_tag' => 'Tags', ...]
 *    Polylang iç taxonomy'leri (language, post_translations vb.) filtreli.
 *    Taxonomy slug key, label value.
 *    Kaynak: get_taxonomies()
 *
 *  taxonomiesForPostType( string $post_type )
 *    → ['category' => 'Categories (category)', 'post_tag' => 'Tags (post_tag)']
 *    Belirli bir post type'a bağlı public taxonomy'ler. Label + (slug) formatında value.
 *    Kaynak: get_object_taxonomies()
 *
 *  menuLocations()
 *    → ['primary' => 5, 'footer' => 12, ...]   (location slug → atanmış menu term_id)
 *    Kayıtlı nav menu location'ları. Atanmamış location'lar da dahildir (değer 0).
 *    Kaynak: get_menu_locations()
 *
 *  menuNames( bool $filter_lang_variants = true )
 *    → ['main-menu' => 'Main Menu', 'footer-menu' => 'Footer Menu', ...]
 *    Menü slug key, adı value. $filter_lang_variants=true ise "Footer - TR" gibi
 *    Polylang dil varyantları filtrelenir (__ suffix ve " - TR/EN" suffix).
 *    Kaynak: wp_get_nav_menus()
 *
 *  templatePosts( string $type )
 *    → [42 => 'Footer Dark', 87 => 'Footer Light', ...]
 *    Post ID key, başlık value. $type: 'footer' | 'modal' | 'offcanvas' | herhangi taxonomy slug.
 *    Kaynak: get_posts() — 'template' post type + 'template-types' taxonomy filtresi.
 *
 *  customTwigTemplates()
 *    → ['theme/templates/_custom/my-card' => 'my-card', ...]
 *    theme/templates/_custom/ dizinindeki .twig dosyaları. Full path key, dosya adı value.
 *    Kaynak: filesystem — get_stylesheet_directory() + /theme/templates/_custom/
 *
 *  modalTemplateFiles()
 *    → ['/partials/modals/login' => 'login', '/partials/modals/search' => 'search', ...]
 *    /partials/modals/ dizinindeki .twig dosyaları. Path key (uzantısız), ad value.
 *    Kaynak: filesystem — get_timber_template_path()
 *
 *  themeStyles()
 *    → ['header' => ['themes' => [...]], 'typography' => [...], ...]
 *    Ham Theme_Styles verisi. Diğer metodlar (fontSizes, colorClasses) bu veriyi kullanır.
 *    Choices formatında DEĞİL — iç kullanım için.
 *    Kaynak: Theme_Styles::init()->get_data()
 *
 *  fontSizes()
 *    → ['title-xs' => 'title-xs - 24px', 'title-sm' => 'title-sm - 28px', ...,
 *        'text-xs' => 'text-xs', 'text-sm' => 'text-sm - 14px', ...]
 *    title-* ve text-* prefix'li breakpoint boyutları. Tema ayarında değer varsa " - 24px" eklenir.
 *    Kaynak: themeStyles() + breakpoints()
 *
 *  colorClasses()
 *    → ['primary' => 'primary', 'secondary' => 'secondary', 'dark' => 'dark', ...]
 *    Tema renk sınıfları. Key = value = CSS class adı.
 *    Kaynak: theme/static/data/colors.json
 *
 *  fontFamilies()
 *    → ['##Defaults' => '##Defaults', 'initial' => 'initial', 'inherit' => 'inherit',
 *        '##Custom Fonts' => '##Custom Fonts', "'Poppins', sans-serif" => 'Poppins',
 *        '##System Fonts' => '##System Fonts', 'Arial, Helvetica, sans-serif' => 'Arial...', ...]
 *    ## prefix'li key'ler ACF'de optgroup başlığı olarak render edilir.
 *    Sıra: Defaults → Custom (YABE) → System fonts.
 *    Kaynak: YABE_WEBFONT::yabe_get_fonts() + sabit system font listesi
 *
 *  contactPhones()
 *    → ['+905001234567' => 'İletişim Adı (mobile): +905001234567', ...]
 *    contact post type'ındaki contact_phone repeater'dan çekilir.
 *    Telefon numarası key, "Ad (tip): numara" formatında value.
 *    Kaynak: Timber::get_posts() — post_type=contact, meta_query contact_phone
 *
 *  contactEmails()
 *    → ['info@site.com' => 'İletişim Adı (work): info@site.com', ...]
 *    contact post type'ındaki contact_email repeater'dan çekilir.
 *    E-posta adresi key, "Ad (tip): e-posta" formatında value.
 *    Kaynak: Timber::get_posts() — post_type=contact, meta_query contact_email
 *
 *  locationPosts()
 *    → [42 => 'Merkez Ofis (contact)', 87 => 'İstanbul Şube (contact)', ...]
 *    Post ID key, "Başlık (post_type)" formatında value.
 *    Hangi post type'ların listeleneceği group_63e6945ee6760 field group location kuralından okunur.
 *    Kaynak: Timber::get_post() field group + get_posts()
 *
 *  wpThemes()
 *    → ['salthareket' => 'SaltHareket', 'twentytwentyfour' => 'Twenty Twenty-Four', ...]
 *    TextDomain key, tema adı value.
 *    Kaynak: wp_get_themes()
 *
 *  pagesDefaultLang()
 *    → [42 => 'Hakkımızda', 87 => 'İletişim', ...]
 *    Post ID key, sayfa başlığı value. Yalnızca Polylang varsayılan dili sayfaları.
 *    Kaynak: get_posts() — post_type=page, lang=pll_default_language()
 *
 *  translatableTaxonomies()
 *    → ['category' => 'category', 'post_tag' => 'post_tag', ...]
 *    Polylang'da çeviri desteği açık olan taxonomy'ler. Slug key = value.
 *    Kaynak: pll_is_translated_taxonomy()
 *
 *  untranslatableTaxonomies()
 *    → ['product_cat' => 'product_cat', 'brand' => 'brand', ...]
 *    Polylang'da çeviri desteği KAPALI olan public taxonomy'ler.
 *    Kaynak: pll_is_translated_taxonomy() — false olanlar
 *
 *  untranslatablePostTypes()
 *    → ['template' => 'template', 'acf-field-group' => 'acf-field-group', ...]
 *    Polylang'da çeviri desteği KAPALI olan custom post type'lar.
 *    Builtin type'lar (attachment, revision vb.) skip edilir.
 *    Kaynak: pll_is_translated_post_type() — false olanlar
 *
 * ─── CHANGELOG ─────────────────────────────────────────────────────────────
 *
 *  1.1.0 (2026-07-29)
 *    - Dosya header'ına HOW TO USE, yeni metod ekleme rehberi ve changelog eklendi
 *
 *  1.0.0 (2026-07)
 *    - İlk sürüm: acf_add_field_options içindeki tüm static cache'li
 *      data sağlayıcılar bu sınıfa taşındı
 *    - Her metod kendi static $c cache'ini yönetiyor
 *    - Polylang dilli menü varyantları filtresi eklendi
 *    - Internal taxonomy'ler (language, post_translations vb.) skip listesine eklendi
 *
 * @version 1.1.0
 * @package SaltHareket\FieldRegistry
 */
class FieldChoices
{
    // ─── Languages ──────────────────────────────────────────────────────────

    public static function languages(): array {
        static $c = null;
        if ( $c !== null ) return $c;
        $c = [];
        if ( function_exists('get_all_languages') ) {
            $langs = get_all_languages(true);
            if ( is_array($langs) ) {
                foreach ( $langs as $l ) $c[$l['lang']] = $l['name'];
            }
        }
        return $c;
    }

    // ─── Modal Sizes ────────────────────────────────────────────────────────

    /**
     * Bootstrap 5 modal size class'ları.
     * key = class değeri (data-size'a yazılır), value = etiket
     *
     * @param bool $include_default Başa "Default" (boş değer) ekle
     */
    public static function modalSizes( bool $include_default = false ): array {
        $sizes = [
            'modal-sm'                   => 'Small',
            'modal-md'                   => 'Medium',
            'modal-lg'                   => 'Large',
            'modal-xl'                   => 'Extra Large',
            'modal-fullscreen'           => 'Fullscreen',
            'modal-fullscreen-sm-down'   => 'Fullscreen below sm',
            'modal-fullscreen-md-down'   => 'Fullscreen below md',
            'modal-fullscreen-lg-down'   => 'Fullscreen below lg',
            'modal-fullscreen-xl-down'   => 'Fullscreen below xl',
            'modal-fullscreen-xxl-down'  => 'Fullscreen below xxl',
        ];
        if ( $include_default ) {
            return array_merge( [ '' => 'Default' ], $sizes );
        }
        return $sizes;
    }

    // ─── Breakpoints ────────────────────────────────────────────────────────

    public static function breakpoints( bool $include_none = false ): array {
        static $c = null;
        if ( $c === null ) {
            $c = [];
            $bps = class_exists('Data') ? \Data::get('breakpoints') : [];
            if ( is_array($bps) ) {
                foreach ( $bps as $key => $v ) $c[$key] = $key;
            }
        }
        if ( $include_none ) return array_merge(['' => 'None'], $c);
        return $c;
    }

    // ─── Post Types ─────────────────────────────────────────────────────────

    public static function postTypes( bool $public_only = true ): array {
        static $c = null;
        if ( $c !== null ) return $c;
        $c = [];
        $args = $public_only ? ['public' => true] : [];
        foreach ( get_post_types($args, 'objects') as $pt ) {
            $c[$pt->name] = $pt->label;
        }
        return $c;
    }

    public static function postTypesWithArchive(): array {
        static $c = null;
        if ( $c !== null ) return $c;
        $c = [];
        foreach ( get_post_types(['public' => true], 'objects') as $pt ) {
            if ( $pt->has_archive ) $c[$pt->name] = $pt->label;
        }
        return $c;
    }

    // ─── Taxonomies ─────────────────────────────────────────────────────────

    public static function taxonomies( bool $public_only = true ): array {
        static $c = null;
        if ( $c !== null ) return $c;
        $c = [];
        $skip = ['language', 'post_translations', 'term_language', 'term_translations', 'nav_menu'];
        $args = $public_only ? ['public' => true] : [];
        foreach ( get_taxonomies($args, 'objects') as $tax ) {
            if ( in_array($tax->name, $skip, true) ) continue;
            $c[$tax->name] = $tax->label ?: $tax->name;
        }
        return $c;
    }

    public static function taxonomiesForPostType( string $post_type ): array {
        static $cache = [];
        if ( isset($cache[$post_type]) ) return $cache[$post_type];
        $skip = ['language', 'post_translations', 'term_language', 'term_translations', 'nav_menu'];
        $result = [];
        $taxs = get_object_taxonomies($post_type, 'objects');
        foreach ( $taxs as $t ) {
            if ( ! $t->public ) continue;
            if ( in_array($t->name, $skip, true) ) continue;
            $result[$t->name] = $t->label . ' (' . $t->name . ')';
        }
        return $cache[$post_type] = $result;
    }

    // ─── Menus ──────────────────────────────────────────────────────────────

    public static function menuLocations(): array {
        static $c = null;
        if ( $c !== null ) return $c;
        $c = function_exists('get_menu_locations') ? get_menu_locations() : [];
        return $c;
    }

    public static function menuNames( bool $filter_lang_variants = true ): array {
        static $c = null;
        if ( $c !== null ) return $c;
        $c = [];
        $menus = wp_get_nav_menus() ?: [];

        $lang_codes = [];
        if ( $filter_lang_variants ) {
            if ( function_exists('pll_languages_list') ) {
                $lang_codes = pll_languages_list(['fields' => 'slug']) ?: [];
            } elseif ( function_exists('qtranxf_getSortedLanguages') ) {
                $lang_codes = array_keys(qtranxf_getSortedLanguages());
            }
        }

        foreach ( $menus as $menu ) {
            if ( $filter_lang_variants && ! empty($lang_codes) ) {
                $is_variant = false;
                foreach ( $lang_codes as $lc ) {
                    $uc = strtoupper($lc);
                    if ( str_ends_with($menu->name, ' - ' . $uc) || str_ends_with($menu->slug, '___' . $lc) ) {
                        $is_variant = true; break;
                    }
                }
                if ( $is_variant ) continue;
            }
            $c[$menu->slug] = $menu->name;
        }
        return $c;
    }

    // ─── Templates ──────────────────────────────────────────────────────────

    public static function templatePosts( string $type ): array {
        static $cache = [];
        if ( isset($cache[$type]) ) return $cache[$type];
        $posts = get_posts([
            'post_type'      => 'template',
            'posts_per_page' => -1,
            'tax_query'      => [['taxonomy' => 'template-types', 'field' => 'slug', 'terms' => $type]],
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);
        $result = [];
        foreach ( $posts as $p ) {
            if ( $type === 'custom' ) {
                // custom type: value = twig dosya path (direkt include icin)
                $twig_file = get_post_meta( $p->ID, 'template', true );
                if ( $twig_file ) {
                    $path = 'theme/templates/_custom/' . str_replace( '.twig', '', $twig_file );
                    $result[ $path ] = $p->post_title ?: basename( $twig_file, '.twig' );
                } else {
                    // Meta yok — post_name ile tahmin et
                    $guessed = 'theme/templates/_custom/' . $p->post_name;
                    $abs = get_stylesheet_directory() . '/' . $guessed . '.twig';
                    if ( file_exists( $abs ) ) {
                        $result[ $guessed ] = $p->post_title ?: $p->post_name;
                    } else {
                        $result[ 'post_' . $p->ID ] = ( $p->post_title ?: '#' . $p->ID ) . ' ⚠';
                    }
                }
            } else {
                // header/footer/offcanvas/modal: value = post ID (resolve_header/footer post ID bekler)
                $result[ $p->ID ] = $p->post_title;
            }
        }
        return $cache[$type] = $result;
    }

    public static function customTwigTemplates(): array {
        static $c = null;
        if ( $c !== null ) return $c;
        $c = [];
        $path = get_stylesheet_directory() . '/theme/templates/_custom/';
        if ( is_dir($path) && $h = opendir($path) ) {
            while ( false !== ($e = readdir($h)) ) {
                if ( pathinfo($e, PATHINFO_EXTENSION) === 'twig' ) {
                    $key = 'theme/templates/_custom/' . str_replace('.twig', '', $e);
                    $c[$key] = str_replace('.twig', '', $e);
                }
            }
            closedir($h);
        }
        return $c;
    }

    public static function modalTemplateFiles(): array {
        static $c = null;
        if ( $c !== null ) return $c;
        $c = [];
        if ( function_exists('get_timber_template_path') ) {
            $path = get_timber_template_path('/partials/modals/');
            if ( $path && is_dir($path) && $h = opendir($path) ) {
                while ( false !== ($e = readdir($h)) ) {
                    if ( $e !== '.' && $e !== '..' ) {
                        $key = '/partials/modals/' . str_replace('.twig', '', $e);
                        $c[$key] = str_replace('.twig', '', $e);
                    }
                }
                closedir($h);
            }
        }
        return $c;
    }

    /**
     * templates/manual/ dizinindeki .twig dosyaları.
     * Kullanici bu klasore elle twig dosyalari koyar.
     * Key: 'manual/my-file' (uzantisiz) — Timber include icin
     * Value: 'my-file.twig' — ACF select label
     *
     * @return array<string, string>
     */
    public static function manualTemplateFiles(): array {
        static $c = null;
        if ( $c !== null ) return $c;
        $c = [];
        if ( function_exists('get_timber_template_path') ) {
            $path = get_timber_template_path('/manual/');
            if ( $path && is_dir($path) && $h = opendir($path) ) {
                while ( false !== ($e = readdir($h)) ) {
                    if ( $e !== '.' && $e !== '..' && pathinfo($e, PATHINFO_EXTENSION) === 'twig' ) {
                        $key    = 'manual/' . str_replace('.twig', '', $e);
                        $c[$key] = str_replace('.twig', '', $e);
                    }
                }
                closedir($h);
            }
        }
        return $c;
    }

    // ─── Theme Styles ────────────────────────────────────────────────────────

    public static function themeStyles(): array {
        static $c = null;
        if ( $c !== null ) return $c;
        $c = class_exists('Theme_Styles') ? \Theme_Styles::init()->get_data() : [];
        return $c;
    }

    public static function fontSizes(): array {
        static $c = null;
        if ( $c !== null ) return $c;
        $c = [];
        $styles     = self::themeStyles();
        $breakpoints = self::breakpoints();
        $typo = $styles['typography'] ?? [];
        foreach ( ['title', 'text'] as $type ) {
            foreach ( $breakpoints as $key => $v ) {
                $size = isset($typo[$type][$key]['value'])
                    ? ' - ' . $typo[$type][$key]['value'] . ($typo[$type][$key]['unit'] ?? '')
                    : '';
                $c[$type . '-' . $key] = $type . '-' . $key . $size;
            }
        }
        return $c;
    }

    // ─── Colors ──────────────────────────────────────────────────────────────

    public static function colorClasses(): array {
        static $c = null;
        if ( $c !== null ) return $c;
        $c = [];
        $file = get_template_directory() . '/theme/static/data/colors.json';
        if ( file_exists($file) ) {
            $json = json_decode(file_get_contents($file), true);
            if ( is_array($json) ) foreach ( $json as $label ) $c[$label] = $label;
        }
        $c[''] = '— None —';
        return $c;
    }

    // ─── Fonts ───────────────────────────────────────────────────────────────

    public static function fontFamilies(): array {
        static $c = null;
        if ( $c !== null ) return $c;
        $system = [
            '##System Fonts'                                    => '##System Fonts',
            'Arial, Helvetica, sans-serif'                      => 'Arial, Helvetica, sans-serif',
            '"Arial Black", Gadget, sans-serif'                 => '"Arial Black", Gadget, sans-serif',
            '"Bookman Old Style", serif'                        => '"Bookman Old Style", serif',
            '"Comic Sans MS", cursive'                          => '"Comic Sans MS", cursive',
            'Courier, monospace'                                => 'Courier, monospace',
            'Garamond, serif'                                   => 'Garamond, serif',
            'Georgia, serif'                                    => 'Georgia, serif',
            'Impact, Charcoal, sans-serif'                      => 'Impact, Charcoal, sans-serif',
            '"Lucida Console", Monaco, monospace'               => '"Lucida Console", Monaco, monospace',
            '"Lucida Sans Unicode", "Lucida Grande", sans-serif' => '"Lucida Sans Unicode", "Lucida Grande", sans-serif',
            '"MS Sans Serif", Geneva, sans-serif'               => '"MS Sans Serif", Geneva, sans-serif',
            '"MS Serif", "New York", sans-serif'                => '"MS Serif", "New York", sans-serif',
            '"Palatino Linotype", "Book Antiqua", Palatino, serif' => '"Palatino Linotype", "Book Antiqua", Palatino, serif',
            'Tahoma, Geneva, sans-serif'                        => 'Tahoma, Geneva, sans-serif',
            '"Times New Roman", Times, serif'                   => '"Times New Roman", Times, serif',
            '"Trebuchet MS", Helvetica, sans-serif'             => '"Trebuchet MS", Helvetica, sans-serif',
            'Verdana, Geneva, sans-serif'                       => 'Verdana, Geneva, sans-serif',
        ];

        $custom = [];
        if ( class_exists('JooosiFon\Plugin') || class_exists('YABE_WEBFONT') ) {
            if ( function_exists('yabe_get_fonts') ) {
                $yabe = yabe_get_fonts();
                if ( ! empty($yabe) ) {
                    $custom['##Custom Fonts'] = '##Custom Fonts';
                    foreach ( $yabe as $font ) {
                        $family = trim($font['family'] ?? '');
                        if ( ! $family ) continue;
                        $sel    = trim($font['selector'] ?? '');
                        $safe   = "'" . str_replace("'", '', $family) . "'";
                        $name   = $sel ? "$safe, $sel" : $safe;
                        $custom[$name] = $font['title'] ?? $family;
                    }
                }
            }
        }

        $c = array_merge(
            ['##Defaults' => '##Defaults', 'initial' => 'initial', 'inherit' => 'inherit'],
            $custom,
            $system
        );
        return $c;
    }

    // ─── Contact ────────────────────────────────────────────────────────────

    public static function contactPhones(): array {
        static $c = null;
        if ( $c !== null ) return $c;
        $c = [];
        if ( ! class_exists('Timber\Timber') ) return $c;
        $posts = \Timber\Timber::get_posts(['post_type' => 'contact', 'posts_per_page' => -1,
            'meta_query' => [['key' => 'contact_phone', 'value' => '', 'compare' => '!=']]]);
        if ( $posts ) {
            foreach ( $posts as $p ) {
                $phones = $p->meta('contact_phone');
                if ( is_array($phones) ) {
                    foreach ( $phones as $ph ) $c[$ph['number']] = $p->title . ' (' . $ph['type'] . '): ' . $ph['number'];
                }
            }
        }
        return $c;
    }

    public static function contactEmails(): array {
        static $c = null;
        if ( $c !== null ) return $c;
        $c = [];
        if ( ! class_exists('Timber\Timber') ) return $c;
        $posts = \Timber\Timber::get_posts(['post_type' => 'contact', 'posts_per_page' => -1,
            'meta_query' => [['key' => 'contact_email', 'value' => '', 'compare' => '!=']]]);
        if ( $posts ) {
            foreach ( $posts as $p ) {
                $emails = $p->meta('contact_email');
                if ( is_array($emails) ) {
                    foreach ( $emails as $e ) $c[$e['email']] = $p->title . ' (' . $e['type'] . '): ' . $e['email'];
                }
            }
        }
        return $c;
    }

    public static function locationPosts(): array {
        static $c = null;
        if ( $c !== null ) return $c;
        $c = [];
        $post_types = [];
        $group = \Timber\Timber::get_post(['post_type' => 'acf-field-group', 'name' => 'group_63e6945ee6760', 'posts_per_page' => 1]);
        if ( $group ) {
            $settings = maybe_unserialize($group->post_content);
            foreach ( ($settings['location'] ?? []) as $loc ) {
                foreach ( $loc as $rule ) {
                    if ( ($rule['param'] ?? '') === 'post_type' ) $post_types[] = $rule['value'];
                }
            }
        }
        if ( ! empty($post_types) ) {
            $posts = get_posts(['post_type' => $post_types, 'posts_per_page' => -1, 'post_status' => 'publish']);
            foreach ( $posts as $p ) $c[$p->ID] = $p->post_title . ' (' . $p->post_type . ')';
        }
        return $c;
    }

    // ─── WP Themes ──────────────────────────────────────────────────────────

    public static function wpThemes(): array {
        static $c = null;
        if ( $c !== null ) return $c;
        $c = [];
        foreach ( wp_get_themes() as $theme ) $c[$theme->get('TextDomain')] = $theme->get('Name');
        return $c;
    }

    // ─── Pages ───────────────────────────────────────────────────────────────

    public static function pagesDefaultLang(): array {
        static $c = null;
        if ( $c !== null ) return $c;
        $c = [];
        if ( ! function_exists('pll_default_language') ) return $c;
        $pages = get_posts(['post_type' => 'page', 'posts_per_page' => -1,
            'lang' => pll_default_language(), 'post_status' => 'publish',
            'orderby' => 'title', 'order' => 'ASC']);
        foreach ( $pages as $p ) $c[$p->ID] = $p->post_title;
        return $c;
    }

    // ─── Polylang ────────────────────────────────────────────────────────────

    public static function translatableTaxonomies(): array {
        static $c = null;
        if ( $c !== null ) return $c;
        $c = [];
        if ( ! function_exists('pll_is_translated_taxonomy') ) return $c;
        foreach ( get_taxonomies(['public' => true, 'show_ui' => true], 'names') as $tax ) {
            if ( pll_is_translated_taxonomy($tax) ) $c[$tax] = $tax;
        }
        return $c;
    }

    public static function untranslatableTaxonomies(): array {
        static $c = null;
        if ( $c !== null ) return $c;
        $c = [];
        if ( ! function_exists('pll_is_translated_taxonomy') ) return $c;
        foreach ( get_taxonomies(['public' => true, 'show_ui' => true], 'names') as $tax ) {
            if ( ! pll_is_translated_taxonomy($tax) ) $c[$tax] = $tax;
        }
        return $c;
    }

    public static function untranslatablePostTypes(): array {
        static $c = null;
        if ( $c !== null ) return $c;
        $c = [];
        if ( ! function_exists('pll_is_translated_post_type') ) return $c;
        $skip = ['attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset'];
        foreach ( get_post_types(['public' => true, '_builtin' => false], 'names') as $pt ) {
            if ( in_array($pt, $skip, true) ) continue;
            if ( ! pll_is_translated_post_type($pt) ) $c[$pt] = $pt;
        }
        return $c;
    }

    // ─── AJAX Methods ────────────────────────────────────────────────────────

    /**
     * Compiled methods/index.php dosyasındaki switch-case metodlarını döndürür.
     * ACF select field'ı için method seçici olarak kullanılır.
     *
     * Wrapper class: "acf-ajax-methods"
     *
     * @return array ['method_name' => 'method_name', ...]
     */
    public static function ajaxMethods(): array {
        static $c = null;
        if ( $c !== null ) return $c;
        $c = [];

        // Compile sırasında index.php'ye yazılan get_ajax_methods() fonksiyonu
        $file = defined( 'SH_INCLUDES_PATH' )
            ? SH_INCLUDES_PATH . 'methods/index.php'
            : get_template_directory() . '/vendor/salthareket/theme/src/includes/methods/index.php';

        if ( file_exists( $file ) ) {
            // Henüz include edilmemiş olabilir — include edince get_ajax_methods() tanımlanır
            if ( ! function_exists( 'get_ajax_methods' ) ) {
                // Sadece fonksiyon tanımını almak için dosyanın tamamını include etmek yerine
                // sonundaki get_ajax_methods() bloğunu regex ile çekip eval ediyoruz
                $content = file_get_contents( $file );
                if ( preg_match( '/function get_ajax_methods\(\)[^}]+\}/s', $content, $m ) ) {
                    eval( $m[0] );
                }
            }

            if ( function_exists( 'get_ajax_methods' ) ) {
                $methods = get_ajax_methods();
                foreach ( $methods as $method ) {
                    $c[ $method ] = $method;
                }
                ksort( $c );
                return $c;
            }

            // Fallback: fonksiyon yoksa (henüz compile edilmemiş) — regex ile parse et
            $content = $content ?? file_get_contents( $file );
            if ( preg_match_all( "/^case '([a-z0-9_]+)':/m", $content, $matches ) ) {
                foreach ( $matches[1] as $method ) {
                    $c[ $method ] = $method;
                }
                ksort( $c );
            }
        }

        return $c;
    }
}
