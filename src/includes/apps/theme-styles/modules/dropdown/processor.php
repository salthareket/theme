<?php
if (!defined('ABSPATH')) exit;

/**
 * Dropdown Processor
 * BS5 uyumlu variable adları.
 * https://getbootstrap.com/docs/5.3/components/dropdowns/
 */
function theme_styles_process_dropdown($data, $generator) {
    $d  = $data['dropdown'] ?? [];
    $it = $d['item']    ?? [];
    $hd = $d['header']  ?? [];
    $dv = $d['divider'] ?? [];
    $vars = [];

    // ── Menu Background ──────────────────────────────────────────
    // BS5: --dropdown-bg, --dropdown-color
    $bg_type = $d['bg_type']     ?? 'color';
    $bg_color= $d['bg_color']    ?? '';
    $bg_grad = $d['bg_gradient'] ?? '';
    if ( ! empty($bg_color) || $bg_type === 'gradient' ) {
        $vars['dropdown-bg'] = $bg_type === 'gradient' ? 'transparent' : $bg_color;
        if ( $bg_type === 'gradient' && ! empty($bg_grad) ) {
            $vars['dropdown-bg-gradient'] = $bg_grad; // custom
        }
    }
    if ( ! empty($d['color']) ) $vars['dropdown-color'] = $d['color'];

    // ── Menu Layout ──────────────────────────────────────────────
    // BS5: --dropdown-min-width, --dropdown-padding-x/y, --dropdown-border-color, --dropdown-border-radius, --dropdown-border-width, --dropdown-box-shadow, --dropdown-spacer, --dropdown-font-size
    if ( ! empty($d['min_width']) )     $vars['dropdown-min-width']     = $d['min_width'];
    if ( ! empty($d['padding_x']) )     $vars['dropdown-padding-x']     = $d['padding_x'];
    if ( ! empty($d['padding_y']) )     $vars['dropdown-padding-y']     = $d['padding_y'];
    if ( ! empty($d['border_color']) )  $vars['dropdown-border-color']  = $d['border_color'];
    if ( ! empty($d['border_radius']) ) $vars['dropdown-border-radius'] = $d['border_radius'];
    if ( ! empty($d['border_width']) )  $vars['dropdown-border-width']  = $d['border_width'];
    if ( ! empty($d['box_shadow']) )    $vars['dropdown-box-shadow']    = $d['box_shadow'];
    if ( ! empty($d['spacer']) )        $vars['dropdown-spacer']        = $d['spacer'];
    if ( ! empty($d['font_size']) )     $vars['dropdown-font-size']     = $d['font_size'];

    // ── Header ───────────────────────────────────────────────────
    // BS5: --dropdown-header-color, --dropdown-header-padding-x/y
    if ( ! empty($hd['color']) )     $vars['dropdown-header-color']     = $hd['color'];
    if ( ! empty($hd['padding_x']) ) $vars['dropdown-header-padding-x'] = $hd['padding_x'];
    if ( ! empty($hd['padding_y']) ) $vars['dropdown-header-padding-y'] = $hd['padding_y'];
    if ( ! empty($hd['font_size']) ) $vars['dropdown-header-font-size'] = $hd['font_size']; // custom

    // ── Item ─────────────────────────────────────────────────────
    // BS5: --dropdown-link-color/hover-color/hover-bg/active-color/active-bg/disabled-color
    //      --dropdown-item-padding-x/y, --dropdown-item-border-radius
    if ( ! empty($it['color']) )         $vars['dropdown-link-color']          = $it['color'];
    if ( ! empty($it['color_hover']) )   $vars['dropdown-link-hover-color']    = $it['color_hover'];
    if ( isset($it['bg_hover']) )        $vars['dropdown-link-hover-bg']       = $it['bg_hover'] ?: 'transparent';
    if ( ! empty($it['color_active']) )  $vars['dropdown-link-active-color']   = $it['color_active'];
    if ( isset($it['bg_active']) )       $vars['dropdown-link-active-bg']      = $it['bg_active'] ?: 'transparent';
    if ( ! empty($it['color_disabled']) )$vars['dropdown-link-disabled-color'] = $it['color_disabled'];
    if ( ! empty($it['padding_x']) )     $vars['dropdown-item-padding-x']      = $it['padding_x'];
    if ( ! empty($it['padding_y']) )     $vars['dropdown-item-padding-y']      = $it['padding_y'];
    if ( ! empty($it['border_radius']) ) $vars['dropdown-item-border-radius']  = $it['border_radius'];
    if ( ! empty($it['font_weight']) )   $vars['dropdown-item-font-weight']    = $it['font_weight']; // custom

    // ── Divider ──────────────────────────────────────────────────
    // BS5: --dropdown-divider-bg, --dropdown-divider-margin-y
    if ( ! empty($dv['color']) )    $vars['dropdown-divider-bg']       = $dv['color'];
    if ( ! empty($dv['margin_y']) ) $vars['dropdown-divider-margin-y'] = $dv['margin_y'];

    return ['variables' => $vars, 'mobile' => [], 'media_queries' => []];
}
