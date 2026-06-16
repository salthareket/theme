<?php
if (!defined('ABSPATH')) exit;

function theme_styles_process_breadcrumb($data, $generator) {
    $bc   = $data['breadcrumb'] ?? [];
    $vars = [];

    if (!empty($bc['font_family']))     $vars['breadcrumb-item-font-family']          = $bc['font_family'];
    if (!empty($bc['font_size']))       $vars['breadcrumb-item-font-size']            = $bc['font_size'];
    if (!empty($bc['font_weight']))     $vars['breadcrumb-item-font-weight']          = $bc['font_weight'];
    if (!empty($bc['font_weight_hover']))   $vars['breadcrumb-item-font-weight-hover']  = $bc['font_weight_hover'];
    if (!empty($bc['font_weight_active'])) $vars['breadcrumb-item-font-weight-active'] = $bc['font_weight_active'];
    if (!empty($bc['text_transform']))  $vars['breadcrumb-item-text-transform']       = $bc['text_transform'];
    $vars['breadcrumb-item-letter-spacing'] = $bc['letter_spacing'] ?? '0';
    if (!empty($bc['color']))           $vars['breadcrumb-item-color']                = $bc['color'];
    if (!empty($bc['color_hover']))     $vars['breadcrumb-item-color-hover']          = $bc['color_hover'];
    if (!empty($bc['color_active']))    $vars['breadcrumb-item-color-active']         = $bc['color_active'];
    if (!empty($bc['text_decoration']))        $vars['breadcrumb-item-text-decoration']        = $bc['text_decoration'];
    if (!empty($bc['text_decoration_hover']))  $vars['breadcrumb-item-text-decoration-hover']  = $bc['text_decoration_hover'];
    if (!empty($bc['text_decoration_active'])) $vars['breadcrumb-item-text-decoration-active'] = $bc['text_decoration_active'];

    // Separator: template'de separator_icon, eski sistemde separator
    $sep = $bc['separator_icon'] ?? ($bc['separator'] ?? '');
    if (!empty($sep)) $vars['breadcrumb-sep'] = '"' . $sep . '"';

    if (!empty($bc['separator_color'])) $vars['breadcrumb-sep-color'] = $bc['separator_color'];
    if (!empty($bc['separator_size']))  $vars['breadcrumb-sep-size']  = $bc['separator_size'];
    if (!empty($bc['gap']))             $vars['breadcrumb-gap']        = $bc['gap'];
    if (!empty($bc['padding']))         $vars['breadcrumb-padding']    = $bc['padding'];

    // Truncation: template'de enable_truncation, eski sistemde truncate
    $truncate = ($bc['enable_truncation'] ?? ($bc['truncate'] ?? '')) === 'yes' || ($bc['truncate'] ?? '') === '1';
    if ($truncate && !empty($bc['max_width'])) {
        $vars['breadcrumb-max-width'] = $bc['max_width'];
    }

    return ['variables' => $vars, 'mobile' => [], 'media_queries' => []];
}
