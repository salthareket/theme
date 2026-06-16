<?php
/**
 * ACF Columns Block — Bootstrap row wrapper via InnerBlocks class
 * ACF'in acf-innerblocks-container div'ine row class'larını veriyoruz.
 * Böylece ekstra bir row div'i olmadan Bootstrap grid direkt çalışır.
 * Child block: acf/column
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$fields  = get_fields() ?: [];
$bp_data = $fields['column_breakpoints'] ?? [];

// row_cols aktifse global değişkeni set et — child column'lar okuyacak
global $salt_columns_row_cols_active;
$row_cols_active = ! empty( $fields['row_cols'] );
$salt_columns_row_cols_active = $row_cols_active;

if ( $row_cols_active ) {
    // column_breakpoints clone'undan gap al — her bp bir group: {columns, gx, gy}
    $bp_order = [ 'xs', 'sm', 'md', 'lg', 'xl', 'xxl', 'xxxl' ];
    foreach ( $bp_order as $bp ) {
        if ( isset( $bp_data[ $bp ]['gx'] ) && $bp_data[ $bp ]['gx'] !== '' ) {
            $gx = $bp_data[ $bp ]['gx'];
        }
        if ( isset( $bp_data[ $bp ]['gy'] ) && $bp_data[ $bp ]['gy'] !== '' ) {
            $gy = $bp_data[ $bp ]['gy'];
        }
    }
} else {
    // gap group'tan al
    $gap = $fields['gap'] ?? [];
    $gx  = $gap['xs_gx'] ?? 3;
    $gy  = $gap['xs_gy'] ?? 3;
    // md+ override
    if ( ! empty( $gap['md_gx'] ) ) { $gx = $gap['md_gx']; }
    if ( ! empty( $gap['md_gy'] ) ) { $gy = $gap['md_gy']; }
}

// Row-cols class'ları — sadece row_cols aktifse
// column_breakpoints clone'undan her breakpoint'in columns değerini al
$row_cols_classes = '';
if ( $row_cols_active && ! empty( $bp_data ) ) {
    $bp_map   = [ 'xxxl', 'xxl', 'xl', 'lg', 'md', 'sm', 'xs' ];
    $rc_parts = [];
    $last_rc  = null;
    // Büyükten küçüğe tara, değişen değerleri ekle
    foreach ( $bp_map as $bp ) {
        $val = $bp_data[ $bp ]['columns'] ?? null;
        if ( $val !== null && $val !== '' && $val !== $last_rc ) {
            $rc_parts[ $bp ] = $val;
            $last_rc = $val;
        }
    }
    // Küçükten büyüğe sıralayıp class üret
    $rc_parts = array_reverse( $rc_parts );
    foreach ( $rc_parts as $bp => $val ) {
        $row_cols_classes .= $bp === 'xs' ? " row-cols-{$val}" : " row-cols-{$bp}-{$val}";
    }
    $row_cols_classes = trim( $row_cols_classes );
}
$align_class   = ! empty( $fields['row_align'] )  ? 'align-items-'    . $fields['row_align']  : '';
$justify_class = ! empty( $fields['row_justify'] ) ? 'justify-content-' . $fields['row_justify'] : '';
$nowrap_class  = ! empty( $fields['row_nowrap'] )  ? 'flex-nowrap' : '';
$row_class     = $fields['row_class'] ?? '';

// InnerBlocks'a verilecek row class string'i
// ACF bunu acf-innerblocks-container div'ine ekler
$innerblocks_class = trim( implode( ' ', array_filter( [
    'row',
    "gx-{$gx}",
    "gy-{$gy}",
    $row_cols_classes,
    $align_class,
    $justify_class,
    $nowrap_class,
    $row_class,
] ) ) );

// block_settings — outer wrapper class, container, spacing, bg vs
$block_meta_classes = '';
if ( function_exists( 'block_classes' ) ) {
    $block_meta_classes = block_classes( $block, $fields, false );
}
$block_attrs = '';
if ( function_exists( 'block_attrs' ) ) {
    $block_attrs = block_attrs( $block, $fields, false );
}
$container = '';
if ( function_exists( 'block_container' ) && isset( $fields['block_settings']['container'] ) ) {
    $container = block_container(
        $fields['block_settings']['container'],
        $fields['block_settings']['stretch_height'] ?? false
    );
}
$block_css = '';
if ( function_exists( 'block_css' ) ) {
    $block_css = block_css( $block, $fields, false );
}
?>

<div class="<?= esc_attr( $block_meta_classes ) ?> block-salt-theme <?= $is_preview ? 'is-preview' : '' ?>" <?= $block_attrs ?>>

    <?php if ( $container ) : ?>
    <div class="<?= esc_attr( $container ) ?>">
    <?php endif; ?>

        <InnerBlocks
            allowedBlocks='["acf/column"]'
            template='[["acf/column"], ["acf/column"]]'
            orientation="horizontal"
            class="<?= esc_attr( $innerblocks_class ) ?>"
        />

    <?php if ( $container ) : ?>
    </div>
    <?php endif; ?>

    <?php if ( $block_css ) : ?>
        <?= $block_css ?>
    <?php endif; ?>

</div>
