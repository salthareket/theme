<?php
/**
 * ACF Column Block — child of acf/columns
 * Bootstrap col-* div + InnerBlocks
 * Her kolon kendi genişliğini ACF field'larından alır.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ACF v3 child block'larda get_fields() bazen boş dönebilir.
// $block['data'] daha güvenilir — önce onu dene.
$fields = get_fields() ?: ( $block['data'] ?? [] );

// Parent columns block'tan row_cols context'ini al
// providesContext: "data" ile tüm columns data'sı geliyor — ama raw string, parse güvenilmez
// Global değişken daha güvenilir (columns render.php set ediyor)
global $salt_columns_row_cols_active;
$row_cols_active = ! empty( $salt_columns_row_cols_active );

// Context fallback — global yoksa dene
if ( ! $row_cols_active ) {
    $ctx_data = $context['acf/columns/row_cols'] ?? null;
    if ( is_array( $ctx_data ) ) {
        $row_cols_active = ! empty( $ctx_data['row_cols'] );
    }
}

$widths = $fields['col_widths'] ?? [];
$align  = $fields['col_align_self'] ?? '';
$order  = $fields['col_order'] ?? '';
$extra  = $fields['col_class'] ?? '';

// Responsive col-* class'larını üret
// row_cols aktifse col_widths'i yok say — row-cols-* zaten parent'tan geliyor
$col_classes = [];
$bp_order    = [ 'xs', 'sm', 'md', 'lg', 'xl', 'xxl', 'xxxl' ];
$last        = null;

if ( ! $row_cols_active ) {
    foreach ( $bp_order as $bp ) {
        $val = $widths[ $bp ] ?? null;
        if ( $val !== null && $val !== '' && $val !== $last ) {
            $col_classes[] = $bp === 'xs' ? 'col-' . $val : 'col-' . $bp . '-' . $val;
            $last = $val;
        }
    }
}

// Hiç tanımlanmamışsa (veya row_cols aktifse) eşit genişlik
if ( empty( $col_classes ) ) {
    $col_classes[] = 'col';
}

// align-self
if ( $align ) {
    $col_classes[] = 'align-self-' . $align;
}

// order
if ( $order !== '' ) {
    $col_classes[] = 'order-' . $order;
}

// block_settings spacing/visibility class'ları
if ( function_exists( 'block_classes' ) ) {
    $bs_extra = block_classes( $block, $fields, false );
    if ( $bs_extra ) {
        $col_classes[] = $bs_extra;
    }
}

if ( $extra ) {
    $col_classes[] = $extra;
}

$col_class_str = implode( ' ', array_filter( $col_classes ) );

// block attrs (id, data-index vs)
$block_attrs = '';
if ( function_exists( 'block_attrs' ) ) {
    $block_attrs = block_attrs( $block, $fields, false );
}

$block_css = '';
if ( function_exists( 'block_css' ) ) {
    $block_css = block_css( $block, $fields, false );
}
?>
<div class="<?= esc_attr( $col_class_str ) ?> block-salt-theme <?= $is_preview ? 'is-preview' : '' ?>" <?= $block_attrs ?>>
    <InnerBlocks class="w-100" />
    <?php if ( $block_css ) : ?>
        <?= $block_css ?>
    <?php endif; ?>
</div>