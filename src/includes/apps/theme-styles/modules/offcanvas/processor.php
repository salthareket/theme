<?php
if (!defined('ABSPATH')) exit;

function theme_styles_process_offcanvas($data, $generator) {
    $oc   = $data['offcanvas'] ?? [];
    $och  = $oc['header']       ?? [];
    $ocni = $oc['nav_item']     ?? [];
    $ocns = $oc['nav_sub']      ?? [];
    $ocnsi= $oc['nav_sub_item'] ?? [];
    $vars = [];

    // ── Background ───────────────────────────────────────────────
    // _button.twig ve _applyCustomStyle() --offcanvas-bg bekliyor
    // root.css default: --offcanvas-bg: #ffffff
    $bg_type  = $oc['bg_type']     ?? 'color';
    $bg_color = $oc['bg_color']    ?? '';
    $bg_grad  = $oc['bg_gradient'] ?? '';
    $bg_img   = $oc['bg_image_url']?? '';

    if ( $bg_type === 'gradient' ) {
        $vars['offcanvas-bg']          = 'transparent';
        if ( ! empty( $bg_grad ) ) {
            $vars['offcanvas-bg-gradient'] = $bg_grad;
        }
    } elseif ( $bg_type === 'image' && ! empty( $bg_img ) ) {
        $vars['offcanvas-bg']       = 'transparent';
        $vars['offcanvas-bg-image'] = 'url(' . $bg_img . ')';
    } elseif ( ! empty( $bg_color ) ) {
        $vars['offcanvas-bg']          = $bg_color;
        $vars['offcanvas-bg-gradient'] = 'none';
        $vars['offcanvas-bg-image']    = 'none';
        // RGB versiyonu — rgba() ile opacity efektleri için
        $rgb = $generator ? $generator->hexToRgb( $bg_color ) : null;
        if ( $rgb ) {
            $vars['offcanvas-bg-rgb'] = $rgb;
        }
    }

    // ── Width ────────────────────────────────────────────────────
    if ( ! empty( $oc['width'] ) ) $vars['offcanvas-width'] = $oc['width'];

    // ── Color ────────────────────────────────────────────────────
    if ( ! empty( $oc['color'] ) ) $vars['offcanvas-color'] = $oc['color'];

    // ── Box Shadow ───────────────────────────────────────────────
    if ( ! empty( $oc['box_shadow'] ) ) $vars['offcanvas-box-shadow'] = $oc['box_shadow'];

    // ── Padding ──────────────────────────────────────────────────
    if ( ! empty( $oc['padding_x'] ) ) $vars['offcanvas-padding-x'] = $oc['padding_x'];
    if ( ! empty( $oc['padding_y'] ) ) $vars['offcanvas-padding-y'] = $oc['padding_y'];

    // ── Header ───────────────────────────────────────────────────
    if (!empty($och['font_family']))    $vars['offcanvas-header-font']           = $och['font_family'];
    if (!empty($och['font_size']))      $vars['offcanvas-header-font-size']      = $och['font_size'];
    if (!empty($och['font_weight']))    $vars['offcanvas-header-font-weight']    = $och['font_weight'];
    if (!empty($och['color']))          $vars['offcanvas-header-color']          = $och['color'];
    if (!empty($och['padding']))        $vars['offcanvas-header-padding']        = $och['padding'];
    if (!empty($och['icon_font_size'])) $vars['offcanvas-header-icon-font-size'] = $och['icon_font_size'];
    if (!empty($och['icon_color']))     $vars['offcanvas-header-icon-color']     = $och['icon_color'];
    if (!empty($och['bg_color']))       $vars['offcanvas-header-bg']             = $och['bg_color'];
    if (!empty($och['border_color']))   $vars['offcanvas-header-border-color']   = $och['border_color'];
    if (!empty($och['border_width']))   $vars['offcanvas-header-border-width']   = $och['border_width'];

    // ── Footer ───────────────────────────────────────────────────
    $ocf = $oc['footer'] ?? [];
    if (!empty($ocf['bg_color']))     $vars['offcanvas-footer-bg']           = $ocf['bg_color'];
    if (!empty($ocf['color']))        $vars['offcanvas-footer-color']        = $ocf['color'];
    if (!empty($ocf['padding']))      $vars['offcanvas-footer-padding']      = $ocf['padding'];
    if (!empty($ocf['border_color'])) $vars['offcanvas-footer-border-color'] = $ocf['border_color'];
    if (!empty($ocf['border_width'])) $vars['offcanvas-footer-border-width'] = $ocf['border_width'];

    // ── Nav Item ─────────────────────────────────────────────────
    if (!empty($ocni['font_family']))    $vars['offcanvas-item-font']         = $ocni['font_family'];
    if (!empty($ocni['font_size']))      $vars['offcanvas-item-font-size']    = $ocni['font_size'];
    if (!empty($ocni['font_weight']))    $vars['offcanvas-item-font-weight']  = $ocni['font_weight'];
    if (!empty($ocni['color']))          $vars['offcanvas-item-color']        = $ocni['color'];
    if (!empty($ocni['color_hover']))    $vars['offcanvas-item-color-hover']  = $ocni['color_hover'];
    $vars['offcanvas-item-bg']           = !empty($ocni['bg_color'])       ? $ocni['bg_color']       : 'transparent';
    $vars['offcanvas-item-bg-hover']     = !empty($ocni['bg_color_hover']) ? $ocni['bg_color_hover'] : 'transparent';
    if (!empty($ocni['padding']))        $vars['offcanvas-item-padding']      = $ocni['padding'];
    if (!empty($ocni['align_hr']))       $vars['offcanvas-item-align-hr']     = $ocni['align_hr'];

    // ── Sub Menu ─────────────────────────────────────────────────
    $vars['offcanvas-dropdown-bg']      = !empty($ocns['bg_color']) ? $ocns['bg_color'] : 'transparent';
    if (!empty($ocns['padding']))   $vars['offcanvas-dropdown-padding'] = $ocns['padding'];

    // ── Sub Item ─────────────────────────────────────────────────
    if (!empty($ocnsi['font_family']))       $vars['offcanvas-dropdown-item-font-family']      = $ocnsi['font_family'];
    if (!empty($ocnsi['font_size']))         $vars['offcanvas-dropdown-item-font-size']         = $ocnsi['font_size'];
    if (!empty($ocnsi['font_weight']))       $vars['offcanvas-dropdown-item-font-weight']       = $ocnsi['font_weight'];
    if (!empty($ocnsi['font_weight_hover'])) $vars['offcanvas-dropdown-item-font-weight-hover'] = $ocnsi['font_weight_hover'];
    if (!empty($ocnsi['color']))             $vars['offcanvas-dropdown-item-font-color']        = $ocnsi['color'];
    if (!empty($ocnsi['color_hover']))       $vars['offcanvas-dropdown-item-font-color-hover']  = $ocnsi['color_hover'];
    $vars['offcanvas-dropdown-item-bg']       = !empty($ocnsi['bg_color'])       ? $ocnsi['bg_color']       : 'transparent';
    $vars['offcanvas-dropdown-item-bg-hover'] = !empty($ocnsi['bg_color_hover']) ? $ocnsi['bg_color_hover'] : 'transparent';
    if (!empty($ocnsi['padding']))           $vars['offcanvas-dropdown-item-padding']           = $ocnsi['padding'];
    // Border - template tek string (border: "1px solid #ccc"), processor ayrı field'lar da destekler
    $border_single = $ocnsi['border'] ?? '';
    if (!empty($border_single)) {
        $vars['offcanvas-dropdown-item-border'] = $border_single;
    } else {
        $bw = $ocnsi['border_width'] ?? '0';
        $bs = $ocnsi['border_style'] ?? 'none';
        $bc = $ocnsi['border_color'] ?? '';
        $vars['offcanvas-dropdown-item-border'] = trim("{$bw} {$bs}" . ($bc ? " {$bc}" : ''));
    }

    return ['variables' => $vars, 'mobile' => [], 'media_queries' => []];
}
