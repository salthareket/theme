<?php
if (!defined('ABSPATH')) exit;

function theme_styles_process_offcanvas($data, $generator) {
    $oc   = $data['offcanvas'] ?? [];
    $ocg  = $oc['offcanvas']    ?? [];
    $och  = $oc['header']       ?? [];
    $ocni = $oc['nav_item']     ?? [];
    $ocns = $oc['nav_sub']      ?? [];
    $ocnsi= $oc['nav_sub_item'] ?? [];
    $vars = [];

    // ── Background ───────────────────────────────────────────────
    $bg_type = $ocg['bg_type']     ?? 'color';
    $bg_color= $ocg['bg_color']    ?? 'transparent';
    $bg_grad = $ocg['bg_gradient'] ?? '';

    $vars['offcanvas-bg-color']    = $bg_type === 'gradient' ? 'transparent' : $bg_color;
    $vars['offcanvas-bg-gradient'] = $bg_type === 'gradient' ? $bg_grad : '';

    if (!empty($ocg['bg_image_url'])) {
        $vars['offcanvas-bg-image'] = 'url(' . $ocg['bg_image_url'] . ')';
    }

    // Backdrop
    $vars['offcanvas-backdrop-color']    = $ocg['backdrop_color']   ?? 'rgba(0,0,0,0.5)';
    $vars['offcanvas-backdrop-gradient'] = $ocg['backdrop_gradient'] ?? '';
    $vars['offcanvas-backdrop-opacity']  = $ocg['backdrop_opacity']  ?? '0.7';

    $vars['offcanvas-padding']   = $ocg['padding']   ?? '20px 30px 40px 30px';
    $vars['offcanvas-align-hr']  = $ocg['align_hr']  ?? 'start';
    $vars['offcanvas-align-vr']  = $ocg['align_vr']  ?? 'start';

    // ── Header ───────────────────────────────────────────────────
    if (!empty($och['font_family']))    $vars['offcanvas-header-font']           = $och['font_family'];
    if (!empty($och['font_size']))      $vars['offcanvas-header-font-size']      = $och['font_size'];
    if (!empty($och['font_weight']))    $vars['offcanvas-header-font-weight']    = $och['font_weight'];
    if (!empty($och['color']))          $vars['offcanvas-header-color']          = $och['color'];
    if (!empty($och['padding']))        $vars['offcanvas-header-padding']        = $och['padding'];
    if (!empty($och['icon_font_size'])) $vars['offcanvas-header-icon-font-size'] = $och['icon_font_size'];
    if (!empty($och['icon_color']))     $vars['offcanvas-header-icon-color']     = $och['icon_color'];

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
