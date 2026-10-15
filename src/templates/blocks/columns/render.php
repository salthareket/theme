<?php
/**
 * ACF Columns Block — Bootstrap row wrapper via InnerBlocks class
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$fields  = get_fields() ?: [];
$bp_data = $fields['column_breakpoints'] ?? [];

global $salt_columns_row_cols_active;
$row_cols_active = ! empty( $fields['row_cols'] );
$salt_columns_row_cols_active = $row_cols_active;

if ( $row_cols_active ) {
    $bp_order = [ 'xs', 'sm', 'md', 'lg', 'xl', 'xxl', 'xxxl' ];
    // Responsive gap class'lari uret: gx-N, gx-sm-N, gx-md-N ...
    $gx_classes = [];
    $gy_classes = [];
    $last_gx    = null;
    $last_gy    = null;
    foreach ( $bp_order as $bp ) {
        $val_gx = isset( $bp_data[ $bp ]['gx'] ) && $bp_data[ $bp ]['gx'] !== '' ? $bp_data[ $bp ]['gx'] : null;
        $val_gy = isset( $bp_data[ $bp ]['gy'] ) && $bp_data[ $bp ]['gy'] !== '' ? $bp_data[ $bp ]['gy'] : null;
        if ( $val_gx !== null && $val_gx !== $last_gx ) {
            $gx_classes[] = $bp === 'xs' ? "gx-{$val_gx}" : "gx-{$bp}-{$val_gx}";
            $last_gx = $val_gx;
        }
        if ( $val_gy !== null && $val_gy !== $last_gy ) {
            $gy_classes[] = $bp === 'xs' ? "gy-{$val_gy}" : "gy-{$bp}-{$val_gy}";
            $last_gy = $val_gy;
        }
    }
    // Fallback: hic deger yoksa 3
    $gx = ! empty( $gx_classes ) ? implode( ' ', $gx_classes ) : 'gx-3';
    $gy = ! empty( $gy_classes ) ? implode( ' ', $gy_classes ) : 'gy-3';
} else {
    $gap = $fields['gap'] ?? [];
    $gx  = $gap['xs_gx'] ?? 3;
    $gy  = $gap['xs_gy'] ?? 3;
    if ( ! empty( $gap['md_gx'] ) ) $gx = $gap['md_gx'];
    if ( ! empty( $gap['md_gy'] ) ) $gy = $gap['md_gy'];
}

$row_cols_classes = '';
if ( $row_cols_active && ! empty( $bp_data ) ) {
    $bp_map  = [ 'xxxl', 'xxl', 'xl', 'lg', 'md', 'sm', 'xs' ];
    $rc_parts = [];
    $last_rc  = null;
    foreach ( $bp_map as $bp ) {
        $val = $bp_data[ $bp ]['columns'] ?? null;
        if ( $val !== null && $val !== '' && $val !== $last_rc ) {
            $rc_parts[ $bp ] = $val;
            $last_rc = $val;
        }
    }
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

$innerblocks_class = trim( implode( ' ', array_filter( [
    'row',
    $row_cols_active ? $gx : "gx-{$gx}",
    $row_cols_active ? $gy : "gy-{$gy}",
    $row_cols_classes,
    $align_class, $justify_class, $nowrap_class, $row_class,
] ) ) );

$block_meta_classes = function_exists( 'block_classes' ) ? block_classes( $block, $fields, false ) : '';
$block_attrs        = function_exists( 'block_attrs' )   ? block_attrs( $block, $fields, false )   : '';
$container          = '';
if ( function_exists( 'block_container' ) && isset( $fields['block_settings']['container'] ) ) {
    $container = block_container( $fields['block_settings']['container'], $fields['block_settings']['stretch_height'] ?? false );
}
$block_css = function_exists( 'block_css' ) ? block_css( $block, $fields, false ) : '';
?>

<div class="<?= esc_attr( $block_meta_classes ) ?> block-salt-theme <?= $is_preview ? 'is-preview' : '' ?>" data-row-cols="<?= $row_cols_active ? '1' : '0' ?>" <?= $block_attrs ?>>

    <?php if ( $is_preview ) : ?>
    <?php static $columns_preview_style_printed = false; ?>
    <?php if ( ! $columns_preview_style_printed ) : $columns_preview_style_printed = true; ?>
    <style id="salt-columns-preview-style">
        .block-salt-theme.is-preview.block-columns {
            min-height: 80px;
            display:block!important;
        }
        .block-salt-theme.is-preview.block-columns .acf-innerblocks-container.row,
        .block-salt-theme.is-preview.block-columns > .container > .acf-innerblocks-container.row {
            min-height: 80px;
        }
        .block-salt-theme.is-preview.block-columns .block-editor-block-list__layout .block-editor-block-list__block {
            margin-left:unset!important;
            margin-right:unset!important;
        }
        .block-salt-theme.is-preview.block-columns .acf-innerblocks-container.row > .block-editor-block-list__block {
            flex: 1 0 0% !important;
            width: auto !important;
            max-width: unset !important;
        }
        .block-salt-theme.is-preview.block-column {
            outline: 1px dashed #cbd5e1;
            min-height: 80px;
            width: 100%;
            height:100%;
            background-color: rgba(241, 245, 249, 0.4);
        }
        .block-salt-theme.is-preview.block-column:hover {
            outline-color: #94a3b8;
        }
        .block-salt-theme.is-preview.block-column > .acf-innerblocks-container {
            width: 100% !important;
            min-height: 60px;
            box-sizing: border-box;
        }
        .block-salt-theme.is-preview.block-columns .acf-innerblocks-container.row > .block-editor-block-list__block.is-selected,
        .block-salt-theme.is-preview.block-columns .acf-innerblocks-container.row > .block-editor-block-list__block.is-highlighted,
        .block-salt-theme.is-preview.block-columns .acf-innerblocks-container.row > .block-editor-block-list__block.has-child-selected {
            flex: unset !important;
            max-width: unset !important;
            width: unset !important;
        }
        /* Kolon Ekle butonu — row'un altında ayrı satır */
        .block-salt-theme.is-preview.block-columns .salt-add-column-wrap {
            display: block;
            width: 100%;
            text-align: center;
            padding: 10px 0 4px;
            clear: both;
        }
        .salt-add-column-btn {
            background: #334155;
            border: none;
            border-radius: 4px!important;
            color: #fff;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            padding: 8px 20px;
            transition: background 0.15s;
        }
        .salt-add-column-btn:hover { background: #1e293b; }
    </style>
    <script>
    (function() {
        // Col wrapper class fix — block-column div'indeki tum class'lari
        // onu sarmalayan [data-type="acf/column"] wrapper'a kopyala
        function saltFixColumnWrappers() {
            document.querySelectorAll(
                '.block-salt-theme.is-preview.block-columns .row'
            ).forEach(function(rowDiv) {
                Array.from(rowDiv.children).filter(function(el) {
                    return el.dataset && el.dataset.type === 'acf/column';
                }).forEach(function(wrapper) {
                    var colDiv = wrapper.querySelector('.block-salt-theme.is-preview.block-column');
                    if (!colDiv) return;
                    // block-column'daki sadece col-* class'larini wrapper'a kopyala
                    Array.from(colDiv.classList).filter(function(c) {
                        return /^col(-[a-z]+)?(-\d+)?$/.test(c);
                    }).forEach(function(c) {
                        if (!wrapper.classList.contains(c)) wrapper.classList.add(c);
                    });
                });
            });
        }
        setTimeout(saltFixColumnWrappers, 100);
        new MutationObserver(function(m) {
            if (m.some(function(x) { return x.addedNodes.length > 0; })) setTimeout(saltFixColumnWrappers, 0);
        }).observe(document.body, { childList: true, subtree: true });

        // Kolon Ekle — iframe icinde calisir, parent frame'deki wp.data kullanir
        (function() {
            if (window._saltColClickBound) return;
            window._saltColClickBound = true;

            document.addEventListener('click', function(e) {
                var btn = e.target.closest('.salt-add-column-btn');
                if (!btn) return;
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();

                // iframe icindeyiz — wp parent frame'de
                var wpRef = (window.wp && window.wp.data) ? window.wp : (window.parent && window.parent.wp ? window.parent.wp : null);
                if (!wpRef || !wpRef.blocks || !wpRef.data) {
                    console.warn('[salt-columns] wp bulunamadi');
                    return;
                }

                var select   = wpRef.data.select('core/block-editor');
                var dispatch = wpRef.data.dispatch('core/block-editor');

                // [data-type="acf/columns"] wrapper bu document veya parent doc'ta olabilir
                var colsWrapper = btn.closest('[data-type="acf/columns"]');
                var clientId    = colsWrapper ? colsWrapper.getAttribute('data-block') : null;

                console.log('[salt-columns] iframe btn click, wrapper:', colsWrapper, 'clientId:', clientId);

                if (!clientId) {
                    // wp.data store'dan bul
                    (function find(blocks) {
                        for (var i = 0; i < blocks.length; i++) {
                            if (blocks[i].name === 'acf/columns') { clientId = blocks[i].clientId; return; }
                            if (blocks[i].innerBlocks && blocks[i].innerBlocks.length) find(blocks[i].innerBlocks);
                        }
                    })(select.getBlocks());
                    console.log('[salt-columns] store clientId:', clientId);
                }

                if (!clientId) { console.warn('[salt-columns] clientId yok'); return; }

                var newBlock   = wpRef.blocks.createBlock('acf/column', {});
                var innerOrder = select.getBlockOrder(clientId);
                dispatch.insertBlock(newBlock, innerOrder.length, clientId, false);

            }, true); // capture
        })();
    })();
    </script>
    <?php endif; ?>
    <?php endif; ?>

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

    <?php if ( $is_preview ) : ?>
    <div class="salt-add-column-wrap">
        <button type="button" class="salt-add-column-btn"><strong>+</strong> Add Column</button>
    </div>
    <?php endif; ?>

    <?php if ( $block_css ) : echo $block_css; endif; ?>

</div>
