<?php
/**
 * Header Builder Block
 *
 * <header id="header"> wrap'i uretir.
 * Icine header-row block'lari eklenir.
 * Her row kendi yukseklik/renk/padding degerlerine sahip olabilir.
 *
 * --header-height ve --header-height-affix CSS var'larini tum row'larin
 * toplamini alarak <style data-inline> ile inject eder.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$fields = get_fields() ?: ( $block['data'] ?? [] );

// ── <header> class'lari ────────────────────────────────────────────────────
$header_classes = [ 'header' ];
$fixed = $fields['fixed'] ?? 'no';
$affix = ! empty( $fields['affix'] );

// affix_offset: fixed=top icin kullanici girisi (default 50)
// bottom-start: JS data-affix-mode="viewport" sentinel'ini okuyup scrollHeight - innerHeight hesaplar
$affix_offset = 50;
if ( $fixed === 'top' && ! empty( $fields['affix_offset'] ) ) {
    $affix_offset = intval( $fields['affix_offset'] );
}

// Preview'da fixed/affixed class'lari eklenmez — editor layout'u bozar
if ( ! $is_preview ) {
    if ( $fixed === 'top' ) {
        $header_classes[] = 'fixed-top';
        if ( $affix ) $header_classes[] = 'affixed';
    } elseif ( $fixed === 'bottom' ) {
        $header_classes[] = 'fixed-bottom';
    } elseif ( $fixed === 'bottom-start' ) {
        $header_classes[] = 'fixed-bottom-start affixed';
    }
}

if ( ! empty( $fields['hide_on_scroll'] ) ) $header_classes[] = 'header-hide-on-scroll';
if ( ! empty( $fields['css_class'] ) )      $header_classes[] = $fields['css_class'];

$header_id        = ! empty( $block['anchor'] ) ? esc_attr( $block['anchor'] ) : 'header';
$header_class_str = esc_attr( implode( ' ', array_filter( $header_classes ) ) );
$allowed_rows     = esc_attr( json_encode( [ 'acf/header-row' ] ) );

// ── Equal context temizle ─────────────────────────────────────────────────
global $salt_header_equal_context;
$salt_header_equal_context = null;

// ── Breakpoint map ────────────────────────────────────────────────────────
$bps = [
    'xs'   => '',
    'sm'   => '576px',
    'md'   => '768px',
    'lg'   => '992px',
    'xl'   => '1200px',
    'xxl'  => '1400px',
    'xxxl' => '1600px',
];

// ── --header-height hesapla ───────────────────────────────────────────────
$total_css = '';
if ( ! $is_preview ) {

    // Template render sirasinda resolve_header() template post ID'sini global'a yazar
    $content_post_id = (int) ( $GLOBALS['_salt_header_template_post_id'] ?? 0 );
    if ( ! $content_post_id ) {
        $content_post_id = (int) ( $post_id ?: ( $GLOBALS['post']->ID ?? 0 ) );
    }

    // Header-row block'larini bul — once parse_blocks, fallback block->innerBlocks
    $row_blocks = [];
    if ( $content_post_id ) {
        $post_content = get_post_field( 'post_content', $content_post_id );
        $post_blocks  = parse_blocks( $post_content );

        $find_rows = null;
        $find_rows = function( array $blocks ) use ( &$find_rows ): array {
            foreach ( $blocks as $b ) {
                if ( ( $b['blockName'] ?? '' ) === 'acf/header-builder' ) {
                    // Bu builder — icindeki row'lari topla
                    $rows = [];
                    foreach ( $b['innerBlocks'] ?? [] as $rb ) {
                        if ( ( $rb['blockName'] ?? '' ) === 'acf/header-row' ) {
                            $rows[] = $rb;
                        }
                    }
                    return $rows;
                }
                if ( ! empty( $b['innerBlocks'] ) ) {
                    $found = $find_rows( $b['innerBlocks'] );
                    if ( ! empty( $found ) ) return $found;
                }
            }
            return [];
        };

        $row_blocks = $find_rows( $post_blocks );
    }

    // Fallback: parse bulamazsa $block->innerBlocks kullan
    if ( empty( $row_blocks ) && ! empty( $block['innerBlocks'] ) ) {
        foreach ( $block['innerBlocks'] as $rb ) {
            if ( ( $rb['blockName'] ?? '' ) === 'acf/header-row' ) {
                $row_blocks[] = $rb;
            }
        }
    }

    if ( ! empty( $row_blocks ) ) {

        // Cascade fill helper — bos bp'leri buyukten kucuge doldur
        $cascade_bp = function( array $data, string $prefix ) use ( $bps ): array {
            $last = '';
            foreach ( array_reverse( array_keys( $bps ) ) as $bp ) {
                $k = $prefix . $bp;
                $v = trim( $data[ $k ] ?? '' );
                if ( $v !== '' ) { $last = $v; }
                elseif ( $last !== '' ) { $data[ $k ] = $last; }
            }
            return $data;
        };

        // Theme Styles fallback — row height bos ise navbar height degerlerini kullan
        $ts_navbar_height       = [];
        $ts_navbar_height_affix = [];
        if ( class_exists( 'Theme_Styles' ) ) {
            static $_ts_hb_data = null;
            if ( $_ts_hb_data === null ) {
                $_ts_hb_data = \Theme_Styles::init()->get_data() ?: [];
            }
            $ts_navbar_height       = $_ts_hb_data['header']['navbar']['height']        ?? [];
            $ts_navbar_height_affix = $_ts_hb_data['header']['navbar']['height_affix']  ?? [];
        }

        $totals       = [];
        $totals_affix = [];

        foreach ( $bps as $bp => $min_width ) {
            $sum       = 0;
            $sum_affix = 0;

            foreach ( $row_blocks as $rb ) {
                $rdata = $rb['attrs']['data'] ?? [];

                // Flat key format: height_xs, height_md, ... (log'dan dogrulandi)
                $rdata = $cascade_bp( $rdata, 'height_' );
                $rdata = $cascade_bp( $rdata, 'height_affix_' );

                $h  = trim( $rdata[ 'height_'       . $bp ] ?? '' );
                $ha = trim( $rdata[ 'height_affix_' . $bp ] ?? '' );

                // Row'a hic height girilmemisse theme styles fallback kullan
                $row_has_any_height = false;
                foreach ( array_keys( $bps ) as $b ) {
                    if ( trim( $rdata[ 'height_' . $b ] ?? '' ) !== '' ) {
                        $row_has_any_height = true;
                        break;
                    }
                }
                if ( ! $row_has_any_height ) {
                    $h  = trim( $ts_navbar_height[ $bp ]       ?? '' );
                    $ha = trim( $ts_navbar_height_affix[ $bp ] ?? $h );
                }

                // Affix height girilmemisse height degerini kullan
                if ( $ha === '' && $h !== '' ) {
                    $ha = $h;
                }

                $hide_on_affix = ! empty( $rdata['hide_on_affix'] );

                if ( $h !== '' && is_numeric( str_replace( 'px', '', $h ) ) ) {
                    $sum += (int) str_replace( 'px', '', $h );
                }
                if ( ! $hide_on_affix && $ha !== '' && is_numeric( str_replace( 'px', '', $ha ) ) ) {
                    $sum_affix += (int) str_replace( 'px', '', $ha );
                }
            }

            if ( $sum > 0 )       $totals[ $bp ]       = $sum . 'px';
            if ( $sum_affix > 0 ) $totals_affix[ $bp ] = $sum_affix . 'px';
        }

        // CSS inject — :root'a --header-height ve --header-height-affix yaz
        if ( ! empty( $totals ) || ! empty( $totals_affix ) ) {
            // Fallback — en buyuk bp degeri (end() referans gerektiriyor, once degiskene al)
            $totals_filtered       = array_values( array_filter( $totals ) );
            $totals_affix_filtered = array_values( array_filter( $totals_affix ) );
            $last_h  = ! empty( $totals_filtered )       ? end( $totals_filtered )       : '';
            $last_ha = ! empty( $totals_affix_filtered ) ? end( $totals_affix_filtered ) : $last_h;

            if ( $last_h )  $total_css .= ":root { --header-height: {$last_h}; --header-height-affix: {$last_ha}; }\n";

            foreach ( $bps as $bp => $min_width ) {
                $h  = $totals[ $bp ]       ?? '';
                $ha = $totals_affix[ $bp ] ?? '';
                if ( ! $h && ! $ha ) continue;

                $vars = '';
                if ( $h )  $vars .= " --header-height: {$h};";
                if ( $ha ) $vars .= " --header-height-affix: {$ha};";

                if ( $bp === 'xs' || ! $min_width ) {
                    $total_css .= ":root {{$vars} }\n";
                } else {
                    $total_css .= "@media (min-width: {$min_width}) { :root {{$vars} } }\n";
                }
            }
        }
    }

    // 4. madde: render sonrasi global temizle
    unset( $GLOBALS['_salt_header_template_post_id'] );
}
?>

<?php if ( $total_css ) : ?>
<style id="header-total-height-vars" data-inline><?= $total_css ?></style>
<?php endif; ?>

<?php if ( $is_preview ) : ?>
<style id="salt-header-builder-preview-style">
#<?= esc_attr( $header_id ) ?>.header { background-color: var(--header-bg, #fff); min-height: 64px; }
.block-header-builder.is-preview { min-height: 64px; }
.salt-add-row-wrap { width: 100%; text-align: center; padding: 8px 0 2px; }
.salt-add-row-btn { background: #0f172a; border: none; border-radius: 4px; color: #fff; cursor: pointer; font-size: 12px; font-weight: 500; padding: 6px 18px; }
.salt-add-row-btn:hover { background: #1e293b; }
</style>
<script>
(function(){
    if (window._saltHdrBuilderBound) return;
    window._saltHdrBuilderBound = true;
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.salt-add-row-btn');
        if (!btn) return;
        e.preventDefault(); e.stopPropagation();
        var wp2 = (window.wp && window.wp.data) ? window.wp : (window.parent && window.parent.wp ? window.parent.wp : null);
        if (!wp2 || !wp2.blocks || !wp2.data) return;
        var select   = wp2.data.select('core/block-editor');
        var dispatch = wp2.data.dispatch('core/block-editor');
        var wrapper  = btn.closest('[data-type="acf/header-builder"]');
        var clientId = wrapper ? wrapper.getAttribute('data-block') : null;
        if (!clientId) {
            (function find(blocks) {
                for (var i = 0; i < blocks.length; i++) {
                    if (blocks[i].name === 'acf/header-builder') { clientId = blocks[i].clientId; return; }
                    if (blocks[i].innerBlocks && blocks[i].innerBlocks.length) find(blocks[i].innerBlocks);
                }
            })(select.getBlocks());
        }
        if (!clientId) return;
        var newBlock = wp2.blocks.createBlock('acf/header-row', {});
        dispatch.insertBlock(newBlock, select.getBlockOrder(clientId).length, clientId, false);
    }, true);
})();
</script>
<?php endif; ?>

<header id="<?= $header_id ?>"
    class="<?= $header_class_str ?>"
    itemtype="https://schema.org/WPHeader"
    itemscope=""
    role="banner"
    data-affix-offset="<?= esc_attr( $affix_offset ) ?>">
    <InnerBlocks
        class="header-rows-wrapper"
        allowedBlocks="<?= $allowed_rows ?>"
        template='[["acf/header-row"]]'
        orientation="vertical"
    />

</header>

<?php if ( $is_preview ) : ?>
<div class="salt-add-row-wrap">
    <button type="button" class="salt-add-row-btn">+ Row Ekle</button>
</div>
<?php endif; ?>
