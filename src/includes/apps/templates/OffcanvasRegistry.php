<?php
/**
 * OffcanvasRegistry — Host-Kullanim Modeli
 *
 * Tum offcanvas HOST'lari (button-builder, page-settings, woo filter, header-tool)
 * kullandiklari offcanvas'i render sirasinda buraya kaydeder. wp_footer'da:
 *   - render_mode = 'static'  → HTML DOM'a basilir (SEO, sidebar).
 *   - render_mode = 'dynamic' → sadece JS registry'ye eklenir (SaltOffcanvas lazy build/AJAX).
 *   - method varsa            → her zaman dynamic (icerik query'den gelir).
 *   - kullanilmayan template  → hic basilmaz.
 *
 * Duplicate: ayni offcanvas ID'si birden cok host tarafindan kullanilsa bile
 * DOM'a 1 kez basilir (ilk kayit kazanir). Boylece unique ID + tek instance garantisi.
 *
 * ── Kullanim (her host render'inda) ──────────────────────────────────────────
 *   OffcanvasRegistry::add([
 *       'id'         => 'sh-oc-menu',        // unique DOM id
 *       'template'   => 123,                 // template post id (icerik icin)
 *       'render_mode'=> 'static',            // static | dynamic
 *       'method'     => '',                  // TurboAPI method (dynamic zorunlu)
 *       'position'   => 'end',
 *       'visibility' => 'offcanvas',         // offcanvas | offcanvas-md (sidebar)
 *       'backdrop'   => true,
 *       'scrollable' => false,
 *       'fullscreen' => false,
 *       'class'      => '',
 *       'title'      => '',
 *   ]);
 *
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class OffcanvasRegistry {

    /** @var array<string,array> id => entry */
    private static array $items = [];

    /**
     * Bir offcanvas kullanimini kaydet.
     * Ayni id ikinci kez gelirse yok sayilir (ilk kayit kazanir) — duplicate print engeli.
     *
     * @param array $entry  En az 'id' zorunlu.
     * @return string       Kaydedilen (veya mevcut) id.
     */
    public static function add( array $entry ): string {
        $id = isset( $entry['id'] ) ? (string) $entry['id'] : '';
        if ( $id === '' ) return '';

        // Duplicate — zaten kayitliysa dokunma
        if ( isset( self::$items[ $id ] ) ) return $id;

        $defaults = [
            'id'          => $id,
            'template'    => 0,
            'twig_path'   => '',   // custom/manual twig offcanvas (post ID'siz) icin twig dosya yolu
            'render_mode' => 'dynamic',
            'method'      => '',
            'position'    => 'end',
            'visibility'  => 'offcanvas',
            'backdrop'    => true,
            'scrollable'  => false,
            'fullscreen'  => false,
            'cached'      => true,
            'class'       => '',
            'title'       => '',
        ];

        $entry = array_merge( $defaults, $entry );

        // Method varsa render_mode her zaman dynamic (icerik query'den gelir, static basilamaz)
        if ( ! empty( $entry['method'] ) ) {
            $entry['render_mode'] = 'dynamic';
        }

        // Sidebar modu tespiti: visibility 'offcanvas-{bp}' ise sidebar (breakpoint ustunde gorunur).
        $entry['is_sidebar'] = ( $entry['visibility'] !== '' && $entry['visibility'] !== 'offcanvas' );

        self::$items[ $id ] = $entry;
        return $id;
    }

    /**
     * Kayitli bir offcanvas var mi?
     */
    public static function has( string $id ): bool {
        return isset( self::$items[ $id ] );
    }

    /**
     * Tum kayitlari dondur.
     * @return array<string,array>
     */
    public static function getAll(): array {
        return self::$items;
    }

    /**
     * Sadece static olanlari dondur (DOM'a basilacaklar).
     * Sidebar + dynamic de DOM'a basilir (bos), cunku acilma eventi yok → auto-load.
     * @return array<string,array>
     */
    public static function getPrintable(): array {
        $out = [];
        foreach ( self::$items as $id => $e ) {
            $needsDom = ( $e['render_mode'] === 'static' ) || ! empty( $e['is_sidebar'] );
            if ( $needsDom ) $out[ $id ] = $e;
        }
        return $out;
    }

    /**
     * JS registry icin tum kayitlari (client tarafi shOffcanvasRegistry) dondur.
     * @return array<string,array>
     */
    public static function getClientRegistry(): array {
        $out = [];
        foreach ( self::$items as $id => $e ) {
            $out[ $id ] = [
                'render_mode' => $e['render_mode'],
                'method'      => $e['method'],
                'position'    => $e['position'],
                'visibility'  => $e['visibility'],
                'backdrop'    => (bool) $e['backdrop'],
                'scrollable'  => (bool) $e['scrollable'],
                'fullscreen'  => (bool) $e['fullscreen'],
                'cached'      => (bool) $e['cached'],
                'className'   => $e['class'],
                'is_sidebar'  => (bool) $e['is_sidebar'],
                'template'    => (int) $e['template'],
                'twig_path'   => (string) ( $e['twig_path'] ?? '' ),
            ];
        }
        return $out;
    }

    /**
     * Reset — her request basinda otomatik bos baslar; gerekirse manuel cagrilabilir.
     */
    public static function reset(): void {
        self::$items = [];
    }

    public static function hasAny(): bool {
        return ! empty( self::$items );
    }

    /**
     * Tutarli, benzersiz offcanvas DOM id uretir.
     * Slug tek basina cakisabilir; namespace prefix 'sh-oc-' + base ile benzersizlestirir.
     *
     * Ayni offcanvas'i (ayni template/source) birden cok host kullaniyorsa AYNI id
     * uretilmeli ki tek instance paylasilsin (registry duplicate guard ile 1 kez basilir).
     * Bu yuzden id, icerik kaynagina (template id / source adi) gore deterministik uretilir.
     *
     * @param string $base  template slug, source adi veya template id gibi kaynak anahtari
     * @return string        'sh-oc-{sanitized-base}'
     */
    public static function makeId( string $base ): string {
        $base = sanitize_title( $base );
        if ( $base === '' ) $base = 'x';
        return 'sh-oc-' . $base;
    }
}
