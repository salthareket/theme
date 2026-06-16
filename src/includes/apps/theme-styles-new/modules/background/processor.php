<?php
if (!defined('ABSPATH')) exit;

function theme_styles_process_background($data, $generator) {
    // Module ID 'background' (klasör adı) - data['background'] altında gelir
    // Eski sistemle uyumluluk için 'body' key'i de kontrol edilir
    $body = $data['background'] ?? ($data['body'] ?? []);

    $bg_type  = $body['bg_type']  ?? 'color';
    $bg_color = $body['bg_color'] ?? '#ffffff';
    $bg_grad  = $body['bg_gradient'] ?? '';

    // bg_size: custom ise width/height birleştir
    $bg_size_raw = $body['bg_size'] ?? 'cover';
    if ($bg_size_raw === 'custom') {
        $w = $body['bg_size_w'] ?? '100%';
        $h = $body['bg_size_h'] ?? 'auto';
        $bg_size = "{$w} {$h}";
    } else {
        $bg_size = $bg_size_raw ?: 'cover';
    }

    // Image URL - relative path'e çevir
    $bg_image_url = $body['bg_image_url'] ?? '';
    if (!empty($bg_image_url) && function_exists('get_clean_root_path')) {
        $bg_image_url = get_clean_root_path($bg_image_url);
    }

    $variables = [
        'body-bg-color'              => $bg_color,
        // Gradient ve image her zaman set edilir - none ise CSS'de görünmez
        // bg_type sadece UI tab'ı için, CSS'de ikisi birden çalışabilir
        'body-bg-gradient'           => !empty($bg_grad) ? $bg_grad : 'none',
        'body-bg-image'              => !empty($bg_image_url) ? 'url(' . $bg_image_url . ')' : 'none',
        'body-bg-size'               => $bg_size,
        'body-bg-position'           => $body['bg_position']   ?? 'center center',
        'body-bg-repeat'             => $body['bg_repeat']     ?? 'no-repeat',
        'body-bg-attachment'         => $body['bg_attachment'] ?? 'scroll',
        'base-link-color'            => $body['link_color']         ?? '#2271b1',
        'base-link-color-hover'      => $body['link_color_hover']   ?? '#135e96',
        'base-link-color-visited'    => $body['link_color_visited'] ?? '#6f42c1',
        'body-selection-bg'          => $body['selection_bg']       ?? '#2271b1',
        'body-selection-color'       => $body['selection_color']    ?? '#ffffff',
        'body-scrollbar-width'       => $body['scrollbar_width']    ?? '6px',
        'body-scrollbar-track'       => $body['scrollbar_track']    ?? '#f1f1f1',
        'body-scrollbar-thumb'       => $body['scrollbar_thumb']    ?? '#c1c1c1',
        'body-scrollbar-thumb-hover' => $body['scrollbar_thumb_hover'] ?? '#a8a8a8',
        'body-focus-color'           => $body['focus_color']        ?? '#2271b1',
        'body-focus-width'           => $body['focus_width']        ?? '2px',
        'body-focus-style'           => $body['focus_style']        ?? 'solid',
        'body-bg-backdrop'           => $body['backdrop_color']     ?? 'transparent',
        'body-bg-backdrop-opacity'   => $body['backdrop_opacity']   ?? '0.5',
        'body-smooth-scroll'         => $body['smooth_scroll']      ?? 'smooth',
        'body-font-smoothing'        => $body['font_smoothing']     ?? 'antialiased',
        'body-text-rendering'        => $body['text_rendering']     ?? 'optimizeLegibility',
        // Image properties - her zaman set et
        'body-bg-image'              => !empty($bg_image_url) ? 'url(' . $bg_image_url . ')' : 'none',
        'body-bg-size'               => $bg_size,
        'body-bg-position'           => $body['bg_position']   ?? 'center center',
        'body-bg-repeat'             => $body['bg_repeat']     ?? 'no-repeat',
        'body-bg-attachment'         => $body['bg_attachment'] ?? 'scroll',
    ];

    return ['variables' => $variables, 'mobile' => [], 'media_queries' => []];
}

/**
 * Body class'larını filtrele - bg type'a göre has-bg-* class ekle
 */
add_filter('body_class', function(array $classes): array {
    $data    = function_exists('theme_styles_new_get_module')
        ? (theme_styles_new_get_module('background') ?? theme_styles_new_get_module('body') ?? [])
        : [];
    $bg_type = $data['bg_type'] ?? 'color';

    if ($bg_type === 'gradient') {
        $classes[] = 'has-bg-gradient';
    } elseif ($bg_type === 'image' && !empty($data['bg_image_url'])) {
        $classes[] = 'has-bg-image';
        if (!empty($data['bg_color'])) {
            $classes[] = 'has-bg-color';
        }
    }

    return $classes;
});
