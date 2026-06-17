<?php
if (!defined('ABSPATH')) exit;

function theme_styles_process_pagination($data, $generator) {
    $p    = $data['pagination'] ?? [];
    $vars = [];

    // Eski sistemle aynı variable isimleri
    if (!empty($p['align']))                $vars['pagination-align']                = $p['align'];

    // Item (page numbers)
    if (!empty($p['item_font_family']))     $vars['pagination-font-family']          = $p['item_font_family'];
    if (!empty($p['item_font_size']))       $vars['pagination-font-size']            = $p['item_font_size'];
    if (!empty($p['item_font_weight']))     $vars['pagination-font-weight']          = $p['item_font_weight'];
    if (!empty($p['item_font_weight_active'])) $vars['pagination-font-weight-active'] = $p['item_font_weight_active'];
    if (!empty($p['item_color']))           $vars['pagination-item-color']           = $p['item_color'];
    if (!empty($p['item_color_hover']))     $vars['pagination-item-color-hover']     = $p['item_color_hover'];
    if (!empty($p['item_color_active']))    $vars['pagination-item-color-active']    = $p['item_color_active'];
    if (!empty($p['item_bg']))              $vars['pagination-item-bg-color']        = $p['item_bg'];
    if (!empty($p['item_bg_hover']))        $vars['pagination-item-bg-color-hover']  = $p['item_bg_hover'];
    if (!empty($p['item_bg_active']))       $vars['pagination-item-bg-color-active'] = $p['item_bg_active'];
    if (!empty($p['item_border_radius']))   $vars['pagination-item-border-radius']   = $p['item_border_radius'];
    if (!empty($p['item_padding']))         $vars['pagination-item-padding']         = $p['item_padding'];

    // Item border builder
    $ibw = $p['item_border_width'] ?? '1px';
    $ibs = $p['item_border_style'] ?? 'solid';
    $ibc = $p['item_border_color'] ?? '#dee2e6';
    $vars['pagination-item-border'] = "{$ibw} {$ibs} {$ibc}";

    $ibwh = $p['item_border_width_hover'] ?? '1px';
    $ibsh = $p['item_border_style_hover'] ?? 'solid';
    $ibch = $p['item_border_color_hover'] ?? '#2271b1';
    $vars['pagination-item-border-hover'] = "{$ibwh} {$ibsh} {$ibch}";

    $ibwa = $p['item_border_width_active'] ?? '1px';
    $ibsa = $p['item_border_style_active'] ?? 'solid';
    $ibca = $p['item_border_color_active'] ?? '#2271b1';
    $vars['pagination-item-border-active'] = "{$ibwa} {$ibsa} {$ibca}";

    // Nav (prev/next)
    if (!empty($p['nav_font_family']))      $vars['pagination-nav-font-family']      = $p['nav_font_family'];
    if (!empty($p['nav_font_size']))        $vars['pagination-nav-font-size']        = $p['nav_font_size'];
    if (!empty($p['nav_font_weight']))      $vars['pagination-nav-font-weight']      = $p['nav_font_weight'];
    if (!empty($p['nav_color']))            $vars['pagination-nav-color']            = $p['nav_color'];
    if (!empty($p['nav_color_hover']))      $vars['pagination-nav-color-hover']      = $p['nav_color_hover'];
    if (!empty($p['nav_color_disabled']))   $vars['pagination-nav-color-disabled']   = $p['nav_color_disabled'];
    if (!empty($p['nav_bg']))               $vars['pagination-nav-bg-color']         = $p['nav_bg'];
    if (!empty($p['nav_bg_hover']))         $vars['pagination-nav-bg-color-hover']   = $p['nav_bg_hover'];
    if (!empty($p['nav_bg_disabled']))      $vars['pagination-nav-bg-color-disabled']= $p['nav_bg_disabled'];
    if (!empty($p['nav_border_radius']))    $vars['pagination-nav-border-radius']    = $p['nav_border_radius'];
    // Her zaman set et (boş olsa bile transparent)
    if (!isset($vars['pagination-nav-bg-color']))          $vars['pagination-nav-bg-color']          = 'transparent';
    if (!isset($vars['pagination-nav-bg-color-hover']))    $vars['pagination-nav-bg-color-hover']    = 'transparent';
    if (!isset($vars['pagination-nav-bg-color-disabled'])) $vars['pagination-nav-bg-color-disabled'] = 'transparent';

    // Nav border builder
    $nbw = $p['nav_border_width'] ?? '1px';
    $nbs = $p['nav_border_style'] ?? 'solid';
    $nbc = $p['nav_border_color'] ?? '#dee2e6';
    $vars['pagination-nav-border'] = "{$nbw} {$nbs} {$nbc}";

    $nbwh = $p['nav_border_width_hover'] ?? '1px';
    $nbsh = $p['nav_border_style_hover'] ?? 'solid';
    $nbch = $p['nav_border_color_hover'] ?? '#2271b1';
    $vars['pagination-nav-border-hover'] = "{$nbwh} {$nbsh} {$nbch}";

    // Icons - her zaman set et
    $vars['pagination-nav-prev-icon'] = !empty($p['prev_icon']) ? '"' . $p['prev_icon'] . '"' : '"\f053"';
    $vars['pagination-nav-next-icon'] = !empty($p['next_icon']) ? '"' . $p['next_icon'] . '"' : '"\f054"';

    // Eski sistemde var: prev/next text ve gap
    if (isset($p['prev_text'])) $vars['pagination-nav-prev-text'] = $p['prev_text'];
    if (isset($p['next_text'])) $vars['pagination-nav-next-text'] = $p['next_text'];
    if (!empty($p['gap']))      $vars['pagination-item-gap']       = $p['gap'];

    return ['variables' => $vars, 'mobile' => [], 'media_queries' => []];
}
