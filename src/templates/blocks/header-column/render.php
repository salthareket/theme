<?php
/**
 * Header Column Block — child of acf/header-row
 *
 * Row'dan gelen col_breakpoint_mode context'e gore iki mod:
 *
 * SIMPLE MOD (col_breakpoint_mode = false):
 *   col_behavior: auto (flex-shrink-0) | grow (flex-grow-1) | responsive (col-12 col-lg-auto)
 *   justify:      start | center | end | between
 *   visibility:   all | lg-up | xl-up | mobile-only
 *
 * BREAKPOINT MOD (col_breakpoint_mode = true):
 *   col_widths: per-BP Bootstrap col class (xs/sm/md/lg/xl/xxl/xxxl)
 *   Her BP icin: auto / 1-12 / hidden
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$fields    = get_fields() ?: ( $block['data'] ?? [] );

// ── Row context ────────────────────────────────────────────────────────────
global $salt_header_equal_context;
$equal              = $salt_header_equal_context['equal']               ?? false;
$equal_on           = $salt_header_equal_context['equal_on']            ?? 'xl';
$col_breakpoint_mode = ! empty( $fields['use_breakpoints'] ) || ( $salt_header_equal_context['col_breakpoint_mode'] ?? false );

// ── Breakpoint map ────────────────────────────────────────────────────────
$bp_prefixes = [
    'xs'   => '',
    'sm'   => 'sm',
    'md'   => 'md',
    'lg'   => 'lg',
    'xl'   => 'xl',
    'xxl'  => 'xxl',
    'xxxl' => 'xxxl',
];

$col_classes   = [ 'header-col', 'd-flex', 'align-items-center' ];
$inner_classes = [];

if ( $col_breakpoint_mode ) {
    // ── BREAKPOINT MOD ────────────────────────────────────────────────────
    $col_widths = $fields['col_widths'] ?? [];
    $prev_val   = null;

    foreach ( $bp_prefixes as $bp => $prefix ) {
        $val = trim( $col_widths[ $bp ] ?? '' );
        if ( $val === '' ) $val = $prev_val ?? 'auto';
        $prev_val = $val;

        if ( $val === 'hidden' ) {
            // Gizle: bu BP'den itibaren display:none, bir sonraki visible BP'de geri ac
            $col_classes[] = $prefix ? 'd-' . $prefix . '-none' : 'd-none';
        } elseif ( $val === 'auto' ) {
            $col_classes[] = $prefix ? 'col-' . $prefix . '-auto' : 'col-auto';
        } elseif ( is_numeric( $val ) ) {
            $col_classes[] = $prefix ? 'col-' . $prefix . '-' . $val : 'col-' . $val;
        }
    }

    // Breakpoint modda justify default start
    $col_classes[] = 'justify-content-start';

} else {
    // ── SIMPLE MOD ────────────────────────────────────────────────────────
    $col_behavior = $fields['col_behavior'] ?? 'auto';
    $justify      = $fields['justify']      ?? 'start';
    $visibility   = $fields['visibility']   ?? 'all';
    $align        = $fields['align']        ?? 'start'; // geriye uyumluluk

    // Col behavior
    switch ( $col_behavior ) {
        case 'grow':
            $col_classes[] = 'col flex-grow-1 h-100';
            break;
        case 'responsive':
            $col_classes[] = 'col-12 col-lg-auto';
            break;
        default: // auto
            $col_classes[] = 'col-auto flex-shrink-0 h-inherit';
            break;
    }

    // Justify
    $col_classes[] = 'justify-content-' . $justify;

    // Visibility
    switch ( $visibility ) {
        case 'lg-up':
            $col_classes[] = 'd-none d-lg-flex';
            break;
        case 'xl-up':
            $col_classes[] = 'd-none d-xl-flex';
            break;
        case 'mobile-only':
            $col_classes[] = 'd-flex d-lg-none';
            break;
        // all: ekstra class yok
    }
}

// ── CSS Class ─────────────────────────────────────────────────────────────
$col_class = $fields['col_class'] ?? '';
if ( $col_class ) $col_classes[] = $col_class;

// ── Equal ─────────────────────────────────────────────────────────────────
$justify_val = $fields['justify'] ?? ( $fields['align'] ?? 'start' );
if ( $equal && $justify_val !== 'center' && ! $col_breakpoint_mode ) {
    $col_classes[] = 'nav-equal nav-equal-' . esc_attr( $equal_on );
}

// ── Preview class ──────────────────────────────────────────────────────────
if ( $is_preview ) $col_classes[] = 'block-header-column-preview';

$allowed = esc_attr( json_encode( [
    // Header-column ozel block'lari
    'acf/header-logo',
    'acf/header-tools',
    // Header tool block'lari
    'acf/header-tool-cart',
    'acf/header-tool-custom',
    'acf/header-tool-favorites',
    'acf/header-tool-login',
    'acf/header-tool-messages',
    'acf/header-tool-notifications',
    'acf/header-tool-search',
    'acf/header-tool-search-field',
    'acf/header-tool-user-menu',
    // Salt blocks
    'acf/buttons',
    'acf/image',
    'acf/languages',
    'acf/marquee',
    'acf/navigation',
    'acf/social-media',
    'acf/text',
    'acf/video',
] ) );
?>
<?php if ( $is_preview ) : ?>
<style>
.block-header-column-preview {
    min-width: 60px;
    min-height: 48px;
    outline: 1px dashed #94a3b8;
    background: rgba(241,245,249,.4);
    border-radius: 4px;
    position: relative;
}
</style>
<?php endif; ?>
<InnerBlocks
    class="<?= esc_attr( implode( ' ', array_filter( $col_classes ) ) ) ?>"
    allowedBlocks="<?= $allowed ?>"
    template="[]"
    renderAppender="InnerBlocks.ButtonBlockAppender"
/>