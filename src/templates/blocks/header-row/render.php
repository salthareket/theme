<?php
/**
 * Header Row Block
 *
 * Bir header satiri (navbar-main + navbar-top) uretir.
 * header-builder icine N tane eklenebilir.
 * Her row kendi bg, height, padding degerlerini override edebilir.
 * Tanimsiz birakilirsa Theme Styles global var'lari kullanilir.
 *
 * Fields:
 *   container   string  container class
 *   bg_color    string  --row-bg (default state)
 *   bg_color_affix string --row-bg-affix
 *   height      array   per breakpoint height (px) — bos = Theme Styles default
 *   height_affix array  per breakpoint affix height
 *   padding     array   per breakpoint padding
 *   padding_affix array
 *   hide_on_affix bool  affix olunca bu row gizlensin
 *   equal       bool    Equal Start/End
 *   equal_on    string  breakpoint
 *   css_class   string  ekstra class
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$fields = get_fields() ?: ( $block['data'] ?? [] );

// ── Equal context — header-column'lara ilet ───────────────────────────────
global $salt_header_equal_context;
$salt_header_equal_context = [
    'equal'               => ! empty( $fields['equal'] ),
    'equal_on'            => $fields['equal_on'] ?? 'xl',
    'col_breakpoint_mode' => ! empty( $fields['col_breakpoint_mode'] ),
];

// ── Container ─────────────────────────────────────────────────────────────
$container       = $fields['container']       ?? 'default';
$flex_wrap       = ! empty( $fields['flex_wrap'] ) ? 'flex-wrap' : 'flex-nowrap';
$justify_content = $fields['justify_content'] ?? 'between';
$cont_class = 'navbar-top px-4 px-lg-3 justify-content-' . esc_attr( $justify_content ) . ' align-items-center ' . $flex_wrap;
if     ( $container === 'no' )                       { /* no container */ }
elseif ( $container === 'default' || ! $container )  { $cont_class .= ' container'; }
else                                                 { $cont_class .= ' container-' . $container; }

// ── Inline CSS variable'lari ───────────────────────────────────────────────
// Sadece dolu degerler yazilir — bos birakilirsa SCSS'deki global var'lar devreye girer
$inline_vars = [];

if ( ! empty( $fields['bg_color'] ) )       $inline_vars[] = '--row-bg: ' . esc_attr( $fields['bg_color'] ) . ';';
if ( ! empty( $fields['bg_color_affix'] ) ) $inline_vars[] = '--row-bg-affix: ' . esc_attr( $fields['bg_color_affix'] ) . ';';

$bps = [ 'xs' => '', 'sm' => '576px', 'md' => '768px', 'lg' => '992px', 'xl' => '1200px', 'xxl' => '1400px', 'xxxl' => '1600px' ];

// ── Cascade fill — bos birakilmis breakpoint'leri doldur ─────────────────
// Bootstrap mantigi: xxxl > xxl > xl > lg > md > sm > xs
// Bir breakpoint bos ise, kendinden buyuk dolu olan en yakin degerden cascade eder.
// Cascade yonu: BUYUKTEN KUCUGE (xxxl'den xs'e dogru)
$cascade_keys = [ 'xxxl', 'xxl', 'xl', 'lg', 'md', 'sm', 'xs' ];

$cascade_fill = function( array $data ) use ( $cascade_keys ): array {
    // Oncce buyukten kucuge cascade (xxxl degerini asagiya tas)
    $last = '';
    foreach ( $cascade_keys as $bp ) {
        $v = trim( $data[ $bp ] ?? '' );
        if ( $v !== '' ) {
            $last = $v;
        } elseif ( $last !== '' ) {
            $data[ $bp ] = $last;
        }
    }
    return $data;
};

$heights       = $cascade_fill( (array)( $fields['height']        ?? [] ) );
$heights_affix = $cascade_fill( (array)( $fields['height_affix']  ?? [] ) );
$paddings      = $cascade_fill( (array)( $fields['padding']       ?? [] ) );
$paddings_affix= $cascade_fill( (array)( $fields['padding_affix'] ?? [] ) );

$mq_css = '';

foreach ( $bps as $bp => $min_width ) {
    $h  = $heights[ $bp ]        ?? '';
    $ha = $heights_affix[ $bp ]  ?? '';
    $p  = $paddings[ $bp ]       ?? '';
    $pa = $paddings_affix[ $bp ] ?? '';

    $vars = '';
    // Row bazinda height ve padding CSS var override — her row kendi scope'unda degerleri set eder
    // Builder ise :root'a toplami inject eder (--header-height / --header-height-affix)
    if ( $h  !== '' ) $vars .= '--header-navbar-height: '        . esc_attr( $h )  . '; ';
    if ( $ha !== '' ) $vars .= '--header-navbar-height-affix: '  . esc_attr( $ha ) . '; ';
    if ( $p  !== '' ) $vars .= '--header-navbar-padding: '       . esc_attr( $p )  . '; ';
    if ( $pa !== '' ) $vars .= '--header-navbar-padding-affix: ' . esc_attr( $pa ) . '; ';

    if ( empty( $vars ) ) continue;

    if ( $bp === 'xs' || ! $min_width ) {
        $mq_css .= '.block-header-row-' . esc_attr( $block['id'] ) . ' { ' . $vars . "}\n";
    } else {
        $mq_css .= '@media (min-width: ' . $min_width . ') { .block-header-row-' . esc_attr( $block['id'] ) . ' { ' . $vars . "} }\n";
    }
}

// ── Navbar class'lari ──────────────────────────────────────────────────────
$navbar_classes = [ 'navbar-main', 'navbar', 'header-row', 'block-header-row-' . esc_attr( $block['id'] ) ];
if ( ! empty( $fields['hide_on_affix'] ) ) $navbar_classes[] = 'header-row-hide-on-affix';
if ( ! empty( $fields['css_class'] ) )     $navbar_classes[] = $fields['css_class'];

$navbar_class_str = esc_attr( implode( ' ', array_filter( $navbar_classes ) ) );

// InnerBlocks allowed — header-column
$allowed = esc_attr( json_encode( [ 'acf/header-column' ] ) );

// InnerBlocks wrapper class = navbar-top container div
$inner_class = esc_attr( implode( ' ', array_filter( [
    $cont_class,
    $is_preview ? 'is-preview' : '',
] ) ) );
?>

<?php if ( $mq_css || ! empty( $inline_vars ) ) : ?>
<style data-inline>
<?php if ( ! empty( $inline_vars ) ) : ?>
.block-header-row-<?= esc_attr( $block['id'] ) ?> { <?= implode( ' ', $inline_vars ) ?> }
<?php endif; ?>
<?= $mq_css ?>
</style>
<?php endif; ?>

<?php if ( $is_preview ) : ?>
<style>
/* header-row preview — column'lari yan yana goster */
[data-type="acf/header-row"] .acf-innerblocks-container.is-preview,
.block-header-row-<?= esc_attr( $block['id'] ) ?> .is-preview {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    width: 100% !important;
    min-height: 60px;
}
[data-type="acf/header-column"] .block-header-column-preview {
    outline: 1px dashed #94a3b8;
    min-height: 48px;
    padding: 6px 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(241,245,249,.4);
    border-radius: 4px;
    font-size: 11px;
    color: #64748b;
}
.salt-add-col-row-wrap { width: 100%; text-align: right; padding: 4px 8px 0; }
.salt-add-col-row-btn { background: #475569; border: none; border-radius: 3px; color: #fff; cursor: pointer; font-size: 11px; padding: 4px 12px; }
.salt-add-col-row-btn:hover { background: #334155; }
</style>
<script>
(function(){
    var ROW_ID = '<?= esc_js( $block['id'] ) ?>';
    var bound_key = '_saltHdrRowColBound_' + ROW_ID;
    if (window[bound_key]) return;
    window[bound_key] = true;

    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.salt-add-col-row-btn[data-row-id="' + ROW_ID + '"]');
        if (!btn) return;
        e.preventDefault(); e.stopPropagation();
        var wp2 = (window.wp && window.wp.data) ? window.wp : (window.parent && window.parent.wp ? window.parent.wp : null);
        if (!wp2 || !wp2.blocks || !wp2.data) return;
        var select   = wp2.data.select('core/block-editor');
        var dispatch = wp2.data.dispatch('core/block-editor');

        // Bu row'un clientId'sini bul
        var clientId = null;
        (function find(blocks) {
            for (var i = 0; i < blocks.length; i++) {
                if (blocks[i].name === 'acf/header-row') {
                    // Birden fazla row olabilir — data-block ile match et
                    var wrapper = document.querySelector('[data-type="acf/header-row"][data-block="' + blocks[i].clientId + '"]');
                    if (wrapper && wrapper.querySelector('.block-header-row-' + ROW_ID)) {
                        clientId = blocks[i].clientId; return;
                    }
                    // Fallback: ilk row
                    if (!clientId) clientId = blocks[i].clientId;
                }
                if (blocks[i].innerBlocks && blocks[i].innerBlocks.length) find(blocks[i].innerBlocks);
            }
        })(select.getBlocks());

        if (!clientId) return;
        var newBlock = wp2.blocks.createBlock('acf/header-column', {});
        dispatch.insertBlock(newBlock, select.getBlockOrder(clientId).length, clientId, false);
    }, true);
})();
</script>
<?php endif; ?>

<?php
// Default column template: Sol=shrink/start, Orta=grow/center, Sag=shrink/end
$col_template = json_encode( [
    [ 'acf/header-column', [ 'data' => [ 'field_shcol_behavior' => 'auto',  'field_shcol_justify' => 'start',  'field_shcol_visibility' => 'all' ] ] ],
    [ 'acf/header-column', [ 'data' => [ 'field_shcol_behavior' => 'grow',  'field_shcol_justify' => 'center', 'field_shcol_visibility' => 'all' ] ] ],
    [ 'acf/header-column', [ 'data' => [ 'field_shcol_behavior' => 'auto',  'field_shcol_justify' => 'end',    'field_shcol_visibility' => 'all' ] ] ],
] );
?>

<?php if ( ! $is_preview ) : ?>
<div class="<?= $navbar_class_str ?>">
    <InnerBlocks
        class="<?= $inner_class ?>"
        allowedBlocks="<?= $allowed ?>"
        template='<?= esc_attr( $col_template ) ?>'
        orientation="horizontal"
    />
</div>
<?php else : ?>
<div class="<?= $navbar_class_str ?>">
    <InnerBlocks
        class="<?= $inner_class ?>"
        allowedBlocks="<?= $allowed ?>"
        template='<?= esc_attr( $col_template ) ?>'
        orientation="horizontal"
    />
</div>
<div class="salt-add-col-row-wrap">
    <button type="button" class="salt-add-col-row-btn" data-row-id="<?= esc_attr( $block['id'] ) ?>">+ Kolon Ekle</button>
</div>
<?php endif; ?>

<?php
// Render sonrasi equal context temizle
$salt_header_equal_context = null;
?>
