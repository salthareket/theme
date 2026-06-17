<?php
if (!defined('ABSPATH')) exit;

function theme_styles_process_colors($data, $generator) {
    $colors = $data['colors'] ?? [];
    $vars   = [];

    // Primary colors - eski sistemle aynı isimler
    foreach (['primary', 'secondary', 'tertiary', 'quaternary'] as $key) {
        if (!empty($colors[$key])) {
            $vars["{$key}-color"] = $colors[$key];
        }
    }

    // Custom colors
    if (!empty($colors['custom']) && is_array($colors['custom'])) {
        foreach ($colors['custom'] as $c) {
            if (!empty($c['title']) && !empty($c['color'])) {
                $vars[$c['title']] = $c['color'];
            }
        }
    }

    // Custom gradients
    if (!empty($colors['custom_gradients']) && is_array($colors['custom_gradients'])) {
        foreach ($colors['custom_gradients'] as $g) {
            if (!empty($g['title']) && !empty($g['color'])) {
                $vars['gradient-' . $g['title']] = $g['color'];
            }
        }
    }

    return ['variables' => $vars, 'mobile' => [], 'media_queries' => []];
}
