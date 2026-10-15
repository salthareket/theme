<?php

namespace SaltHareket\FieldRegistry;

/**
 * FieldRegistry — ACF Field Resolver & Custom Admin Choices Yöneticisi
 *
 * CSS wrapper class → resolver mapping sistemi.
 * ACF `acf/load_field` hook'unu yönetir; aynı zamanda custom admin
 * sayfalarından doğrudan choices verisi almak için kullanılabilir.
 *
 * ─── HOW IT WORKS ──────────────────────────────────────────────────────────
 *
 *  1. ACF field'ının "Wrapper Class" alanına trigger class yaz:
 *     Örn: "acf-post-types"
 *
 *  2. FieldRegistry, `acf/load_field` hook'unda field'ın wrapper class'larını
 *     tarar ve eşleşen resolver'ı çalıştırır.
 *
 *  3. Resolver, $field array'ini modifiye edip (choices, type, multiple vs.)
 *     geri döndürür.
 *
 * ─── HOW TO USE (ACF Field) ────────────────────────────────────────────────
 *
 *  ACF > Field Group > Field > Presentation > Wrapper Class:
 *
 *    "acf-post-types"             → public post type listesi (tekli seçim)
 *    "acf-post-types-multiple"    → public post type listesi (çoklu seçim)
 *    "acf-post-type-archive"      → arşiv sayfası olan post type'lar
 *    "acf-taxonomies"             → taxonomy listesi (tekli)
 *    "acf-taxonomies-multiple"    → taxonomy listesi (çoklu)
 *    "acf-menu-locations"         → kayıtlı nav menu location'ları
 *    "acf-menu-names"             → menü adları (dilli varyantlar filtreli)
 *    "acf-breakpoints"            → Bootstrap breakpoint'leri
 *    "acf-breakpoints-none"       → breakpoint'ler + "None" seçeneği
 *    "acf-font-family"            → System + YABE custom font'lar
 *    "acf-font-size"              → title-* / text-* boyutları
 *    "acf-font-weight"            → 100..900
 *    "acf-text-transform"         → CSS text-transform değerleri
 *    "acf-title-sizes"            → title-xs..title-xxxl + fluid
 *    "acf-title-sizes-default"    → title sizes + Default seçeneği
 *    "acf-text-sizes"             → text-xs..text-xxxl + fluid
 *    "acf-text-sizes-default"     → text sizes + Default seçeneği
 *    "acf-modal-size"             → Bootstrap modal size class'ları (modal-sm/md/lg/xl + fullscreen variants) + Default
 *    "acf-color-classes"          → colors.json renk sınıfları
 *    "acf-color-classes-custom"   → renk sınıfları + "Custom" seçeneği
 *    "acf-heading"                → h1..h6
 *    "acf-columns"                → 1..12 + auto
 *    "acf-gaps"                   → 0..10 + auto + None
 *    "acf-align-hr"               → Left / Center / Right / Stretch
 *    "acf-bs-align-hr"            → Left / Center / Right / Stretch (BS variant)
 *    "acf-align-hr-responsive"    → Left / Center / Right / Stretch / Responsive
 *    "acf-align-vr"               → Top / Center / Bottom
 *    "acf-bs-align-vr"            → Top / Center / Bottom (BS variant)
 *    "acf-align-vr-none"          → Top / Center / Bottom / None
 *    "acf-align-vr-responsive"    → Top / Center / Bottom / Responsive
 *    "acf-position-vr"            → Top / Center / Bottom
 *    "acf-position-hr"            → Left / Center / Right
 *    "acf-width-height"           → auto / 100% + custom değer (ui:1)
 *    "acf-container-default"      → container boyutları (no/xxl/xl/lg/md/sm/xs)
 *    "acf-margin-padding"         → spacing değerleri (parent field'a göre)
 *    "acf-margin-padding-responsive" → spacing + Responsive seçeneği
 *    "acf-image-blend-mode"       → CSS blend mode değerleri
 *    "acf-image-filter"           → CSS filter değerleri
 *    "acf-plyr-video-options"     → Plyr.js video kontrol seçenekleri
 *    "acf-plyr-audio-options"     → Plyr.js audio kontrol seçenekleri
 *    "acf-template-custom"        → _custom/ dizini twig template'leri
 *    "acf-template-custom-default" → _custom/ + Default seçeneği
 *    "acf-template-custom-header" → header template postları
 *    "acf-template-custom-header-default" → header template postları (default ekli)
 *    "acf-template-custom-footer" → footer template postları
 *    "acf-template-custom-modal"  → modal template postları
 *    "acf-template-custom-offcanvas" → offcanvas template postları
 *    "acf-template-custom-dropdown"  → dropdown için offcanvas template postları (aynı içerik, farklı container)
 *    "acf-template-modal"         → /partials/modals/ twig dosyaları
 *    "acf-template-manual"        → templates/manual/ dizinindeki el ile yazılmış twig dosyaları
 *    "acf-body-classes"           → header theme class'ları + m/p utility'ler
 *    "acf-main-classes"           → margin/padding utility class'ları (m/p 0..10)
 *    "acf-contact-phone"          → contact post type telefon listesi
 *    "acf-contact-email"          → contact post type e-posta listesi
 *    "acf-map-service"            → Leaflet / Google Maps
 *    "acf-location-posts"         → harita için konum postları
 *    "acf-language-list"          → aktif diller
 *    "acf-wp-themes"              → kurulu WP temaları
 *    "acf-api-list"               → bilinen API servisleri
 *    "acf-ajax-methods"           → FieldChoices::ajaxMethods() (TurboAPI endpoint'leri)
 *    "acf-translatable-taxonomies"    → Polylang çevrilebilir taxonomy'ler
 *    "acf-untranslatable-taxonomies"  → Polylang çevrilemeyen taxonomy'ler
 *    "acf-untranslatable-post-types"  → Polylang çevrilemeyen post type'lar
 *    "acf-pages-default-lang"     → varsayılan dildeki sayfa listesi
 *    "acf-notification-type"      → buton notification count tipi seçimi:
 *                                   cart (ENABLE_CART), notifications (ENABLE_NOTIFICATIONS),
 *                                   messages (ENABLE_MEMBERSHIP), reviews (ENABLE_REVIEWS),
 *                                   + aktif Reactions tipleri (reaction:*)
 *    "acf-reaction-types"         → sadece Reactions type listesi (like, favorite, bookmark, follow vb.)
 *    "acf-reaction-mode-types"    → object_count (sayfadaki object sayısı) | user_count (user'ın toplam sayısı)
 *    "acf-reaction-object-types"  → user_count modunda hangi object_type sayılacak: post/user/term/comment
 *
 *    "acf-sh-icon-picker"         → Text field'a bu class eklenirse field bir icon-picker'a dönüşür
 *
 *  Global modifier class'ları (diğer class'larla birlikte kullanılır):
 *    "multiple"      → $field['multiple'] = 1 (çoklu seçim)
 *    "ui"            → $field['ui'] = 1 (Select2 UI)
 *    "default-none"  → $field['default_value'] = ''
 *
 *  Örnek kombinezonlar:
 *    "acf-post-types multiple ui"       → çoklu + Select2 UI
 *    "acf-taxonomies multiple"          → çoklu seçim, sade görünüm
 *    "acf-breakpoints default-none"     → breakpoint + varsayılan boş
 *    "acf-color-classes ui"             → renkler + Select2
 *
 *  NOT: image ve gallery type field'larında mime_types otomatik olarak
 *       "jpg,jpeg,png,gif,svg,webp,avif" set edilir — ek class gerekmez.
 *
 * ─── HOW TO USE (Custom Admin Page) ───────────────────────────────────────
 *
 *  use SaltHareket\FieldRegistry\FieldRegistry;
 *
 *  // Kısa tip adıyla (önerilen):
 *  $post_types = FieldRegistry::choices('post-types');
 *  $taxonomies = FieldRegistry::choices('taxonomies');
 *  $menus      = FieldRegistry::choices('menu-names');
 *  $menus_raw  = FieldRegistry::choices('menu-names', ['filter_lang' => false]);
 *  $tax_for_pt = FieldRegistry::choices('taxonomies-for-post-type', ['post_type' => 'post']);
 *  $templates  = FieldRegistry::choices('template-posts', ['type' => 'footer']);
 *
 *  // HTML select örneği:
 *  $post_types = FieldRegistry::choices('post-types');
 *  echo '<select name="my_pt">';
 *  foreach ( $post_types as $value => $label ) {
 *      $sel = selected( $saved, $value, false );
 *      echo "<option value='{$value}' {$sel}>{$label}</option>";
 *  }
 *  echo '</select>';
 *
 *  // Doğrudan FieldChoices'dan da çağrılabilir:
 *  use SaltHareket\FieldRegistry\FieldChoices;
 *  $fonts = FieldChoices::fontFamilies();
 *
 * ─── YENİ RESOLVER EKLEME ──────────────────────────────────────────────────
 *
 *  Adım 1 — FieldChoices.php'ye veri metodu ekle (gerekiyorsa):
 *
 *    public static function myData(): array {
 *        static $c = null;
 *        if ( $c !== null ) return $c;
 *        $c = [];
 *        // ... doldur ...
 *        return $c;
 *    }
 *
 *  Adım 2 — registerDefaults() içine resolver ekle:
 *
 *    self::register('acf-my-class', function ( array $f ) use ($C): array {
 *        $f['type']    = 'select';
 *        $f['choices'] = $C::myData();
 *        return $f;
 *    });
 *
 *  Adım 3 — (Opsiyonel) choices() match bloğuna kısa ad ekle:
 *
 *    'my-data' => $C::myData(),
 *
 *  Adım 4 — ACF field wrapper class'ına "acf-my-class" yaz → bitti.
 *
 * ─── ACF/LOAD_VALUE HOOK EKLEMEK ───────────────────────────────────────────
 *
 *  load_value (değer dönüştürme) hook'ları FieldRegistry'ye ait DEĞİLDİR.
 *  Bunlar specific field key/name/type bazlı olduğu için:
 *  → plugins/acf-admin.php dosyasının alt kısmına ekle.
 *
 *  Örnek:
 *    add_filter('acf/load_value/name=my_field', function($value, $post_id, $field) {
 *        return my_transform( $value );
 *    }, 10, 3);
 *
 * ─── CHANGELOG ─────────────────────────────────────────────────────────────
 *
 *  1.1.0 (2026-07-29)
 *    - acf-margin-padding ve acf-margin-padding-responsive resolver'ları eklendi
 *      (parent field name DB sorgusu, static cache ile)
 *    - Dosya header'ına HOW IT WORKS, HOW TO USE, yeni resolver ekleme
 *      rehberi ve changelog eklendi
 *
 *  1.0.0 (2026-07)
 *    - İlk sürüm: acf_add_field_options (plugins/acf-admin.php) içindeki
 *      tüm CSS class → choices mantığı bu sınıfa taşındı
 *    - FieldChoices ile ayrıştırıldı: Registry sadece mapping, Choices sadece data
 *    - Static registerDefaults() ile 45+ resolver tanımlandı
 *    - applyToAcfField() ACF hook'una bağlandı
 *    - choices() metodu ile custom admin sayfaları için unified API oluşturuldu
 *    - Global modifier'lar (multiple, ui, default-none) merkezi olarak uygulanıyor
 *
 * @version 1.1.0
 * @package SaltHareket\FieldRegistry
 */
class FieldRegistry
{
    /** @var array<string, callable> */
    private static array $resolvers = [];

    /** @var bool */
    private static bool $registered = false;

    // ─── Register ─────────────────────────────────────────────────────────────

    public static function register( string $class, callable $resolver ): void
    {
        self::$resolvers[ $class ] = $resolver;
    }

    /**
     * Tüm varsayılan resolver'ları kaydet.
     * bootstrap.php'de bir kez çağrılır.
     */
    public static function registerDefaults(): void
    {
        if ( self::$registered ) return;
        self::$registered = true;

        $C = FieldChoices::class;

        // ── Dil ────────────────────────────────────────────────────────────
        self::register('acf-language-list', function ( array $f ) use ($C): array {
            $f['type'] = 'select'; $f['multiple'] = 0; $f['allow_null'] = 0; $f['return_format'] = 'array';
            $f['choices'] = $C::languages();
            return $f;
        });

        // ── Breakpoints ────────────────────────────────────────────────────
        self::register('acf-breakpoints', function ( array $f ) use ($C): array {
            $f['type'] = 'select'; $f['default_value'] = 'xl'; $f['allow_null'] = 0;
            $f['choices'] = $C::breakpoints();
            return $f;
        });
        self::register('acf-breakpoints-none', function ( array $f ) use ($C): array {
            $f['type'] = 'select'; $f['default_value'] = ''; $f['allow_null'] = 0;
            $f['choices'] = $C::breakpoints( true );
            return $f;
        });

        // ── Layout ────────────────────────────────────────────────────────
        self::register('acf-columns', function ( array $f ): array {
            $f['type'] = $f['type'] === 'acf_bs_breakpoints' ? $f['type'] : 'select';
            $f['choices'] = array_combine(range(1,12), range(1,12));
            $f['choices']['auto'] = 'auto';
            return $f;
        });
        self::register('acf-gaps', function ( array $f ): array {
            $f['type'] = $f['type'] === 'acf_bs_breakpoints' ? $f['type'] : 'select';
            $choices = ['' => 'None', 'auto' => 'Auto'];
            foreach ( range(0, 10) as $n ) $choices[$n] = $n;
            $f['choices'] = $choices;
            return $f;
        });
        self::register('acf-heading', function ( array $f ): array {
            $f['type'] = 'select'; $f['default_value'] = 'h3';
            $f['choices'] = array_combine(['h1','h2','h3','h4','h5','h6'], ['h1','h2','h3','h4','h5','h6']);
            return $f;
        });

        // ── Alignment ─────────────────────────────────────────────────────
        foreach ( ['acf-bs-align-hr', 'acf-align-hr', 'acf-align-hr-responsive'] as $cls ) {
            self::register($cls, function ( array $f ) use ($cls): array {
                $f['choices'] = ['start' => 'Left', 'center' => 'Center', 'end' => 'Right', 'stretch' => 'Stretch'];
                if ( $cls === 'acf-align-hr-responsive' ) $f['choices']['responsive'] = 'Responsive';
                return $f;
            });
        }
        foreach ( ['acf-bs-align-vr', 'acf-align-vr', 'acf-align-vr-none', 'acf-align-vr-responsive'] as $cls ) {
            self::register($cls, function ( array $f ) use ($cls): array {
                $f['choices'] = ['start' => 'Top', 'center' => 'Center', 'end' => 'Bottom'];
                if ( str_contains($cls, 'none') )       $f['choices']['none'] = 'None';
                if ( str_contains($cls, 'responsive') ) $f['choices']['responsive'] = 'Responsive';
                return $f;
            });
        }
        self::register('acf-position-vr', function ( array $f ): array {
            $f['choices'] = ['top' => 'Top', 'center' => 'Center', 'bottom' => 'Bottom']; return $f;
        });
        self::register('acf-position-hr', function ( array $f ): array {
            $f['choices'] = ['left' => 'Left', 'center' => 'Center', 'right' => 'Right']; return $f;
        });
        self::register('acf-width-height', function ( array $f ): array {
            $f['ui'] = 1; $f['allow_custom'] = 1;
            $f['choices'] = ['auto' => 'auto', '100%' => '100%']; return $f;
        });

        // ── Typography ────────────────────────────────────────────────────
        self::register('acf-font-family', function ( array $f ) use ($C): array {
            $f['type'] = 'select'; $f['default_value'] = 'none';
            $f['choices'] = $C::fontFamilies();
            return $f;
        });
        self::register('acf-font-weight', function ( array $f ): array {
            $f['type'] = 'select'; $f['default_value'] = '400';
            foreach ( ['100','200','300','400','500','600','700','800','900'] as $w ) $f['choices'][$w] = $w;
            return $f;
        });
        self::register('acf-font-size', function ( array $f ) use ($C): array {
            $f['type'] = 'select'; $f['allow_null'] = 1;
            $f['choices'] = $C::fontSizes();
            return $f;
        });
        self::register('acf-text-transform', function ( array $f ): array {
            $f['type'] = 'select';
            foreach ( ['none','capitalize','uppercase','lowercase','full-width','full-size-kana','inherit','initial','revert','revert-layer','unset'] as $o ) $f['choices'][$o] = $o;
            return $f;
        });
        foreach ( ['acf-title-sizes','acf-title-sizes-default','acf-text-sizes','acf-text-sizes-default'] as $cls ) {
            self::register($cls, function ( array $f ) use ($cls, $C): array {
                $f['type'] = 'select';
                $is_title = str_contains($cls, 'title');
                $prefix   = $is_title ? 'title-' : 'text-';
                $choices  = ['default' => 'Default', $prefix . 'fluid' => 'Fluid'];
                foreach ( $C::breakpoints() as $k => $v ) $choices[$prefix . $k] = ($is_title ? 'Title ' : 'Text ') . $k;
                $f['choices'] = $choices;
                return $f;
            });
        }

        // ── Media ─────────────────────────────────────────────────────────
        self::register('acf-image-blend-mode', function ( array $f ): array {
            $f['type'] = 'select';
            $f['choices'] = ['' => 'No','multiply' => 'Multiply','screen' => 'Screen','overlay' => 'Overlay','darken' => 'Darken','lighten' => 'Lighten','color-dodge' => 'Color Dodge','color-burn' => 'Color Burn','hard-light' => 'Hard Light','soft-light' => 'Soft Light','difference' => 'Difference','exclusion' => 'Exclusion','hue' => 'Hue','saturation' => 'Saturation','color' => 'Color','luminosity' => 'Luminosity'];
            return $f;
        });
        self::register('acf-image-filter', function ( array $f ): array {
            $f['type'] = 'select';
            $f['choices'] = ['' => 'No','grayscale' => 'Grayscale','sepia' => 'Sepia','blur' => 'Blur','brightness' => 'Brightness','contrast' => 'Contrast','opacity' => 'Opacity'];
            return $f;
        });
        foreach ( ['acf-plyr-video-options', 'acf-plyr-audio-options'] as $cls ) {
            self::register($cls, function ( array $f ) use ($cls): array {
                $f['type'] = 'select'; $f['multiple'] = 1; $f['ui'] = 1;
                $opts = $cls === 'acf-plyr-video-options'
                    ? ['play-large','restart','rewind','play','fast-forward','progress','current-time','duration','mute','volume','captions','settings','pip','airplay','download','fullscreen']
                    : ['restart','rewind','play','fast-forward','progress','current-time','duration','mute','volume','settings','airplay','download'];
                foreach ( $opts as $o ) $f['choices'][$o] = $o;
                return $f;
            });
        }

        // ── Menu ──────────────────────────────────────────────────────────
        self::register('acf-menu-locations', function ( array $f ) use ($C): array {
            $f['type'] = 'select'; $f['choices'] = $C::menuLocations(); return $f;
        });
        self::register('acf-menu-names', function ( array $f ) use ($C): array {
            $f['type'] = 'select'; $f['choices'] = $C::menuNames(); return $f;
        });

        // ── Colors ────────────────────────────────────────────────────────
        self::register('acf-color-classes', function ( array $f ) use ($C): array {
            $f['type'] = 'select'; $f['choices'] = $C::colorClasses(); return $f;
        });
        self::register('acf-color-classes-custom', function ( array $f ) use ($C): array {
            $f['type'] = 'select';
            $f['choices'] = array_merge($C::colorClasses(), ['custom' => 'Custom']);
            return $f;
        });

        // ── Button Size ───────────────────────────────────────────────────
        // '' (none) → size class yok, _button.twig p-0 ekler
        self::register('acf-button-size', function ( array $f ): array {
            $f['type']    = 'select';
            $f['choices'] = [
                'sm'      => 'Small',
                'default' => 'Default',
                'lg'      => 'Large',
                ''        => '— None —',
            ];
            return $f;
        });
        self::register('acf-modal-size', function ( array $f ) use ($C): array {
            $f['type']    = 'select';
            $f['choices'] = $C::modalSizes( true );
            return $f;
        });

        // ── Post Types ────────────────────────────────────────────────────
        self::register('acf-post-types', function ( array $f ) use ($C): array {
            $f['type'] = 'select'; $f['allow_null'] = 1;
            $f['choices'] = $C::postTypes(); return $f;
        });
        self::register('acf-post-types-multiple', function ( array $f ) use ($C): array {
            $f['type'] = 'select'; $f['multiple'] = 1; $f['ui'] = 1; $f['allow_null'] = 1;
            $f['choices'] = $C::postTypes(); return $f;
        });
        self::register('acf-post-type-archive', function ( array $f ) use ($C): array {
            $f['type'] = 'select'; $f['allow_null'] = 1;
            $f['choices'] = $C::postTypesWithArchive(); return $f;
        });

        // ── Taxonomies ────────────────────────────────────────────────────
        self::register('acf-taxonomies', function ( array $f ) use ($C): array {
            $f['type'] = 'select'; $f['allow_null'] = 1;
            $f['choices'] = $C::taxonomies(); return $f;
        });
        self::register('acf-taxonomies-multiple', function ( array $f ) use ($C): array {
            $f['type'] = 'select'; $f['multiple'] = 1; $f['ui'] = 1; $f['allow_null'] = 1;
            $f['choices'] = $C::taxonomies(); return $f;
        });

        // ── Templates ─────────────────────────────────────────────────────
        self::register('acf-template-custom', function ( array $f ) use ($C): array {
            $f['choices'] = array_merge(
                ['vendor/salthareket/theme/src/templates/post/tease' => 'Post Tease (Predefined)'],
                $C::templatePosts('custom')
            );
            return $f;
        });
        self::register('acf-template-custom-default', function ( array $f ) use ($C): array {
            $f['choices'] = array_merge(
                ['default' => 'Default', 'vendor/salthareket/theme/src/templates/post/tease' => 'Post Tease (Predefined)'],
                $C::templatePosts('custom')
            );
            return $f;
        });
        self::register('acf-template-custom-header', function ( array $f ) use ($C): array {
            $f['choices'] = array_merge(['none' => '— None —', 'static' => 'Static'], $C::templatePosts('header')); return $f;
        });
        self::register('acf-template-custom-header-default', function ( array $f ) use ($C): array {
            $f['choices'] = array_merge(['default' => 'Default', 'none' => '— None —', 'static' => 'Static'], $C::templatePosts('header')); return $f;
        });
        self::register('acf-template-custom-footer', function ( array $f ) use ($C): array {
            $f['choices'] = array_merge(['none' => '— None —', 'static' => 'Static'], $C::templatePosts('footer')); return $f;
        });
        self::register('acf-template-custom-footer-default', function ( array $f ) use ($C): array {
            $f['choices'] = array_merge(['default' => 'Default', 'none' => '— None —', 'static' => 'Static'], $C::templatePosts('footer')); return $f;
        });
        self::register('acf-template-custom-modal', function ( array $f ) use ($C): array {
            $f['choices'] = $C::templatePosts('modal'); return $f;
        });
        self::register('acf-template-custom-offcanvas', function ( array $f ) use ($C): array {
            $f['choices'] = $C::templatePosts('offcanvas'); return $f;
        });
        // Dropdown için offcanvas template'leri — aynı içerik, farklı container (dropdown-menu)
        self::register('acf-template-custom-dropdown', function ( array $f ) use ($C): array {
            $f['choices'] = $C::templatePosts('offcanvas'); return $f;
        });
        self::register('acf-template-modal', function ( array $f ) use ($C): array {
            $f['choices'] = $C::modalTemplateFiles(); return $f;
        });

        // ── Manual Templates ──────────────────────────────────────────────────
        // templates/manual/ klasoründeki elle yazilmis twig dosyalari.
        // Offcanvas veya modal icin statik HTML dosyasi belirtmek icin kullanilir.
        self::register('acf-template-manual', function ( array $f ) use ($C): array {
            $f['type']       = 'select';
            $f['allow_null'] = 1;
            $f['choices']    = $C::manualTemplateFiles();
            return $f;
        });

        // ── Body Classes ──────────────────────────────────────────────────
        self::register('acf-body-classes', function ( array $f ) use ($C): array {
            $f['type'] = 'select'; $f['multiple'] = 1; $f['ui'] = 1;
            $styles = $C::themeStyles();
            $f['choices'] = [];
            if ( isset($styles['header']['themes']) ) {
                $f['choices']['##Body Classes'] = '##Body Classes';
                foreach ( $styles['header']['themes'] as $t ) {
                    if ( ! in_array($t['class'], ['body', 'html'], true) ) $f['choices'][$t['class']] = $t['class'];
                }
            }
            foreach ( ['m','mt','mb','ms','me','mx','my','p','pt','pb','ps','pe','px','py'] as $p ) {
                foreach ( range(0, 10) as $n ) $f['choices'][$p.'-'.$n] = $p.'-'.$n;
            }
            return $f;
        });
        self::register('acf-main-classes', function ( array $f ): array {
            $f['type'] = 'select'; $f['multiple'] = 1; $f['ui'] = 1;
            foreach ( ['m','mt','mb','ms','me','mx','my','p','pt','pb','ps','pe','px','py'] as $p ) {
                foreach ( range(0, 10) as $n ) $f['choices'][$p.'-'.$n] = $p.'-'.$n;
            }
            return $f;
        });

        // ── WP Themes ─────────────────────────────────────────────────────
        self::register('acf-wp-themes', function ( array $f ) use ($C): array {
            $f['type'] = 'select'; $f['choices'] = $C::wpThemes(); return $f;
        });

        // ── Margin / Padding (parent field name'e göre — DB sorgusu) ─────
        foreach ( ['acf-margin-padding', 'acf-margin-padding-responsive'] as $cls ) {
            self::register($cls, function ( array $f ) use ($cls): array {
                static $s_parent_names = [];
                if ( empty($f['parent']) || $f['parent'] == 0 ) return $f;
                $parent_id = $f['parent'];
                if ( ! isset($s_parent_names[$parent_id]) ) {
                    global $wpdb;
                    $s_parent_names[$parent_id] = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT post_excerpt FROM {$wpdb->posts} WHERE post_type = 'acf-field' AND post_name = %s",
                            $parent_id
                        )
                    );
                }
                $parent_name = $s_parent_names[$parent_id] ?? '';
                if ( ! $parent_name ) return $f;
                $valid = ['styles', 'margin', 'padding', 'default_margin', 'default_padding'];
                if ( ! in_array($parent_name, $valid, true) ) return $f;

                $f['type'] = $f['type'] === 'acf_bs_breakpoints' ? $f['type'] : 'select';
                $f['multiple'] = 0; $f['allow_null'] = 0; $f['default_value'] = '';
                $choices = [];
                if ( in_array($parent_name, ['styles', 'margin', 'padding'], true) ) $choices['default'] = 'Default';
                if ( $cls === 'acf-margin-padding-responsive' ) $choices['responsive'] = 'Responsive';
                $choices[''] = 'None';
                $choices['auto'] = 'auto';
                foreach ( range(0, 12) as $n ) $choices[$n] = $n;
                $f['choices'] = $choices;
                return $f;
            });
        }

        // ── Container Default ─────────────────────────────────────────────
        self::register('acf-container-default', function ( array $f ) use ($C): array {
            $f['type'] = 'select'; $f['allow_null'] = 0; $f['default_value'] = '';
            $f['choices'] = [
                'no'  => 'No Container',
                ''    => 'XXXLarge',   // suffix yok → en geniş BS container
                'xxl' => 'XXLarge',
                'xl'  => 'XLarge',
                'lg'  => 'Large',
                'md'  => 'Medium',
                'sm'  => 'Small',
                'xs'  => 'XSmall',
            ];
            return $f;
        });

        // ── Map ───────────────────────────────────────────────────────────
        self::register('acf-map-service', function ( array $f ): array {
            $f['type'] = 'select';
            $f['choices'] = ['leaflet' => 'Leaflet (OpenStreetMap)', 'google' => 'Google Maps'];
            return $f;
        });        self::register('acf-location-posts', function ( array $f ) use ($C): array {
            $map_view = \SaltHareket\SaltConfig::get('options_map_view');
            $f['type'] = 'select'; $f['multiple'] = ($map_view === 'js') ? 1 : 0; $f['ui'] = $f['multiple'];
            $f['choices'] = $C::locationPosts();
            return $f;
        });

        // ── Contact ───────────────────────────────────────────────────────
        self::register('acf-contact-phone', function ( array $f ) use ($C): array {
            $f['type'] = 'select'; $f['allow_null'] = 1;
            $f['choices'] = $C::contactPhones(); return $f;
        });
        self::register('acf-contact-email', function ( array $f ) use ($C): array {
            $f['type'] = 'select'; $f['allow_null'] = 1;
            $f['choices'] = $C::contactEmails(); return $f;
        });
        self::register('acf-contact-accounts', function ( array $f ): array {
            $f['type'] = 'select'; $f['allow_null'] = 1; return $f;
        });

        // ── Polylang ──────────────────────────────────────────────────────
        self::register('acf-translatable-taxonomies', function ( array $f ) use ($C): array {
            $f['type'] = 'select'; $f['multiple'] = 1; $f['ui'] = 1;
            $f['choices'] = $C::translatableTaxonomies() ?: ['' => 'Polylang aktif değil'];
            return $f;
        });
        self::register('acf-untranslatable-taxonomies', function ( array $f ) use ($C): array {
            $f['type'] = 'select'; $f['multiple'] = 1; $f['ui'] = 1;
            $f['choices'] = $C::untranslatableTaxonomies() ?: ['' => 'Polylang aktif değil'];
            return $f;
        });
        self::register('acf-untranslatable-post-types', function ( array $f ) use ($C): array {
            $f['type'] = 'select'; $f['multiple'] = 1; $f['ui'] = 1;
            $f['choices'] = $C::untranslatablePostTypes() ?: ['' => 'Polylang aktif değil'];
            return $f;
        });
        self::register('acf-pages-default-lang', function ( array $f ) use ($C): array {
            $f['type'] = 'select'; $f['multiple'] = 1; $f['ui'] = 1;
            $f['choices'] = $C::pagesDefaultLang();
            return $f;
        });

        // ── API List ──────────────────────────────────────────────────────
        self::register('acf-api-list', function ( array $f ): array {
            $f['type'] = 'select'; $f['ui'] = 1;
            $f['choices'] = [
                'google_maps'    => 'Google Maps API',
                'google_places'  => 'Google Places API',
                'openweather'    => 'OpenWeather',
                'stripe'         => 'Stripe API',
                'paypal'         => 'PayPal API',
                'mailchimp'      => 'MailChimp API',
            ];
            return $f;
        });

        // ── AJAX Methods ──────────────────────────────────────────────────────
        self::register('acf-ajax-methods', function ( array $f ) use ($C): array {
            $f['type']       = 'select';
            $f['allow_null'] = 1;
            $f['choices']    = $C::ajaxMethods();
            return $f;
        });

        // ── Notification Type ─────────────────────────────────────────────────
        // Buton notification count kaynagini secer.
        // Aktif feature flag'lerine gore dinamik choices uretir.
        // Reactions her zaman listelenir — app kapali olsa bile mevcut degerler kaybolmasin.
        self::register('acf-notification-type', function ( array $f ): array {
            $f['type']       = 'select';
            $f['allow_null'] = 1;
            $choices         = [];

            if ( defined('ENABLE_CART') && ENABLE_CART ) {
                $choices['cart'] = trans('Cart');
            }
            if ( defined('ENABLE_NOTIFICATIONS') && ENABLE_NOTIFICATIONS ) {
                $choices['notifications'] = trans('Notifications');
            }
            // Messages — chat aktifse (badge/skip mantigi ile ayni flag: ENABLE_CHAT).
            if ( defined('ENABLE_CHAT') && ENABLE_CHAT ) {
                $choices['messages'] = trans('Messages');
            }
            // Reviews — class varsa her zaman goster.
            if ( class_exists('Reviews') || ( defined('ENABLE_REVIEWS') && ENABLE_REVIEWS ) ) {
                $choices['reviews'] = trans('Reviews');
            }

            // Reactions her zaman goster — class varsa yeter, flag kontrolu yok
            // Boylece membership/reactions kapali olsa bile kaydedilen degerler kaybolmaz
            if ( class_exists('\SaltHareket\Reactions\ReactionsSettings') ) {
                $types = \SaltHareket\Reactions\ReactionsSettings::getTypes();
                foreach ( $types as $key => $config ) {
                    $choices[ 'reaction:' . $key ] = $config['label'] ?? ucfirst( $key );
                }
            }

            $f['choices'] = $choices;
            return $f;
        });

        // ── Reaction Types — sadece reaction type listesi ─────────────────────
        // Her zaman goster — app kapali olsa bile kaydedilen degerler kaybolmasin.
        self::register('acf-reaction-types', function ( array $f ): array {
            $f['type']       = 'select';
            $f['allow_null'] = 1;
            $choices         = [];

            if ( class_exists('\SaltHareket\Reactions\ReactionsSettings') ) {
                $types = \SaltHareket\Reactions\ReactionsSettings::getTypes();
                foreach ( $types as $key => $config ) {
                    $choices[ $key ] = $config['label'] ?? ucfirst( $key );
                }
            }

            $f['choices'] = $choices;
            return $f;
        });

        // ── Reaction Mode Types ───────────────────────────────────────────────
        // object_count: sayfadaki mevcut object'in reaction sayisi
        // user_count:   login kullanicinin bu tipte toplam reaction sayisi
        self::register('acf-reaction-mode-types', function ( array $f ): array {
            $f['type']    = 'select';
            $f['choices'] = [
                'object_count' => trans("Show Current Object's Count"),
                'user_count'   => trans("Show Logged User's Count"),
            ];
            return $f;
        });

        // ── Reaction Object Types ─────────────────────────────────────────────
        // user_count modunda hangi object_type uzerindeki reaction'lar sayilacak.
        self::register('acf-reaction-object-types', function ( array $f ): array {
            $f['type']    = 'select';
            $f['choices'] = [
                'post'    => trans('Post'),
                'user'    => trans('User'),
                'term'    => trans('Term'),
                'comment' => trans('Comment'),
            ];
            return $f;
        });

        // ─── acf-user-roles ───────────────────────────────────────────────────
        self::register('acf-user-roles', function ( array $f ): array {
            $roles = wp_roles()->roles;
            $choices = [ '' => '-- ' . trans('All Roles') . ' --' ];
            foreach ( $roles as $slug => $role ) {
                $choices[ $slug ] = trans( $role['name'] );
            }
            $f['type']    = 'select';
            $f['choices'] = $choices;
            return $f;
        });

        // ─── acf-comment-types ────────────────────────────────────────────────
        self::register('acf-comment-types', function ( array $f ): array {
            // WP core comment types — comment, pingback, trackback
            // Ayrıca WooCommerce review gibi özel tipler de listelenebilir
            global $wpdb;
            $types = $wpdb->get_col(
                "SELECT DISTINCT comment_type FROM {$wpdb->comments} WHERE comment_type != '' ORDER BY comment_type ASC"
            );
            $choices = [ '' => '-- ' . trans('All Types') . ' --', 'comment' => trans('Comment') ];
            foreach ( $types as $type ) {
                if ( $type && $type !== 'comment' ) {
                    $choices[ $type ] = ucfirst( str_replace( '_', ' ', $type ) );
                }
            }
            $f['type']    = 'select';
            $f['choices'] = $choices;
            return $f;
        });
    }

    // ─── Apply ────────────────────────────────────────────────────────────────

    /**
     * ACF acf/load_field hook'u için.
     * Field'ın wrapper class'larına bakarak kayıtlı resolver'ları çalıştırır.
     */
    public static function applyToAcfField( array $field ): array
    {
        if ( empty($field['wrapper']['class']) ) return $field;

        $classes = is_array($field['wrapper']['class'])
            ? $field['wrapper']['class']
            : explode(' ', $field['wrapper']['class']);

        foreach ( $classes as $cls ) {
            $cls = trim($cls);
            if ( isset(self::$resolvers[$cls]) ) {
                $field = (self::$resolvers[$cls])($field);
            }
        }

        // Global overrides
        if ( $field['type'] === 'select' ) {
            if ( in_array('multiple', $classes, true) ) $field['multiple'] = 1;
            if ( in_array('ui', $classes, true) )       $field['ui'] = 1;
        }
        if ( in_array('default-none', $classes, true) ) $field['default_value'] = '';
        if ( in_array($field['type'], ['image', 'gallery'], true) ) {
            $field['mime_types'] = 'jpg,jpeg,png,gif,svg,webp,avif';
        }

        return $field;
    }

    /**
     * Custom admin sayfalarından doğrudan veri çekmek için.
     *
     * @example FieldRegistry::choices('post-types')
     * @example FieldRegistry::choices('taxonomies')
     * @example FieldRegistry::choices('menu-names')
     */
    public static function choices( string $type, array $options = [] ): array
    {
        $C = FieldChoices::class;
        return match($type) {
            'post-types'                  => $C::postTypes(),
            'post-types-with-archive'     => $C::postTypesWithArchive(),
            'taxonomies'                  => $C::taxonomies(),
            'taxonomies-for-post-type'    => $C::taxonomiesForPostType($options['post_type'] ?? ''),
            'menu-locations'              => $C::menuLocations(),
            'menu-names'                  => $C::menuNames($options['filter_lang'] ?? true),
            'breakpoints'                 => $C::breakpoints($options['include_none'] ?? false),
            'languages'                   => $C::languages(),
            'font-families'               => $C::fontFamilies(),
            'font-sizes'                  => $C::fontSizes(),
            'color-classes'               => $C::colorClasses(),
            'template-posts'              => $C::templatePosts($options['type'] ?? 'footer'),
            'custom-twig-templates'       => $C::customTwigTemplates(),
            'contact-phones'              => $C::contactPhones(),
            'contact-emails'              => $C::contactEmails(),
            'location-posts'              => $C::locationPosts(),
            'wp-themes'                   => $C::wpThemes(),
            'pages-default-lang'          => $C::pagesDefaultLang(),
            'translatable-taxonomies'     => $C::translatableTaxonomies(),
            'untranslatable-taxonomies'   => $C::untranslatableTaxonomies(),
            'untranslatable-post-types'   => $C::untranslatablePostTypes(),
            'ajax-methods'                => $C::ajaxMethods(),
            default                       => [],
        };
    }
}
