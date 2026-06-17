<?php
if (!defined('ABSPATH')) exit;

function theme_styles_process_buttons($data, $generator) {
    $buttons = $data['buttons'] ?? [];
    $custom  = $buttons['custom'] ?? [];
    $vars    = [];

    // Button sizes — SCSS variable formatı: "(size: sm, padding_x: 8px, ...)"
    // wp_scss_set_variables üzerinden SCSS compile'a geçer
    if (!empty($custom) && is_array($custom)) {
        $sizes = [];
        foreach ($custom as $btn) {
            $size      = $btn['size']          ?? '';
            $padding_x = $btn['padding_x']     ?? '12px';
            $padding_y = $btn['padding_y']     ?? '8px';
            $font_size = $btn['font_size']     ?? '14px';
            $radius    = $btn['border_radius'] ?? '6px';
            if (!$size) continue;
            $sizes[] = "size: {$size}, padding_x: {$padding_x}, padding_y: {$padding_y}, font-size: {$font_size}, border-radius: {$radius}";
        }
        if (!empty($sizes)) {
            $vars['button-sizes'] = '(' . implode('), (', $sizes) . ')';
        }
    }

    return ['variables' => $vars, 'mobile' => [], 'media_queries' => []];
}
