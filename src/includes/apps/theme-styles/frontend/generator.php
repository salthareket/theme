<?php
/**
 * Theme Styles New - CSS Generator (Root CSS)
 *
 * Tüm modül verilerini okuyarak eski sistemle aynı CSS variable isimlerini üretir.
 * FluidCss class'ı ile root.css dosyasına yazar.
 * save_theme_styles_colors_new() ile colors.json, _colors.scss üretir.
 *
 * @package SaltHareket\Theme\ThemeStyles
 * @version 1.0.0
 * @since   2026-04-27
 *
 * CHANGELOG:
 * 1.0.0 - 2026-04-27
 * - Initial release - eski acf-admin.php get_theme_styles() mantığı yeni veri yapısına uyarlandı
 *
 * HOW TO USE:
 * theme_styles_generate_root_css($data) → root.css yazar
 * theme_styles_save_colors($data)       → colors.json + _colors.scss yazar
 *
 * @example Save sonrası çağrım (save_data içinde):
 * theme_styles_generate_root_css($data);
 * theme_styles_save_colors($data);
 *
 * @example Manuel çağrım:
 * $data = Theme_Styles::init()->get_data();
 * theme_styles_generate_root_css($data);
 */

if (!defined('ABSPATH')) exit;

/**
 * Save colors - colors.json, _colors.scss, colors_mce.json, colors_gradients.json
 * Eski save_theme_styles_colors() ile aynı çıktılar
 */
function theme_styles_save_colors(array $data): void {
    $colors = $data['colors'] ?? [];

    $colors_list_file      = THEME_STATIC_PATH . 'data/colors.json';
    $colors_mce_file       = THEME_STATIC_PATH . 'data/colors_mce.json';
    $colors_file           = THEME_STATIC_PATH . 'scss/_colors.scss';
    $colors_gradients_file = THEME_STATIC_PATH . 'data/colors_gradients.json';

    $colors_list_default = ['primary','secondary','tertiary','quaternary','gray','danger','info','success','warning','light','dark'];
    $colors_code   = '';
    $custom_colors = '$custom-colors: (' . "\n";
    $colors_list   = [];
    $colors_mce    = [];
    $gradients     = [];

    foreach (['primary','secondary','tertiary','quaternary'] as $key) {
        if (!empty($colors[$key])) {
            $val = $colors[$key];
            $colors_code   .= "\${$key}: {$val};\n";
            $custom_colors .= "\t{$key}: {$val},\n";
            $colors_list[]  = $key;
            $colors_mce[$val] = $key;
        }
    }

    if (!empty($colors['custom']) && is_array($colors['custom'])) {
        foreach ($colors['custom'] as $c) {
            $key = $c['title'] ?? '';
            $val = $c['color'] ?? '';
            if (!$key || !$val) continue;
            $colors_code   .= "\${$key}: {$val};\n";
            $custom_colors .= "\t{$key}: {$val},\n";
            $colors_list[]  = $key;
            $colors_list_default[] = $key;
            $colors_mce[$val] = $key;
        }
    }

    if (!empty($colors['custom_gradients']) && is_array($colors['custom_gradients'])) {
        foreach ($colors['custom_gradients'] as $g) {
            if (!empty($g['title']) && !empty($g['color'])) {
                $gradients[] = ['name' => $g['title'], 'gradient' => $g['color']];
            }
        }
    }

    $custom_colors  = rtrim($custom_colors, ",\n") . "\n);\n";
    $colors_list_var = '$custom-colors-list: ' . implode(',', $colors_list) . ";\n";

    $dir = THEME_STATIC_PATH . 'data';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $scss_dir = THEME_STATIC_PATH . 'scss';
    if (!is_dir($scss_dir)) mkdir($scss_dir, 0755, true);

    file_put_contents($colors_file,           $colors_code . $custom_colors . $colors_list_var);
    file_put_contents($colors_list_file,      json_encode($colors_list_default));
    file_put_contents($colors_mce_file,       json_encode($colors_mce));
    file_put_contents($colors_gradients_file, json_encode($gradients));
}

/**
 * Generate inline CSS for post (legacy - gerekirse kullanılır)
 */
function theme_styles_generate_post_css($post_id) {
    $data = get_post_meta($post_id, '_theme_styles_data', true);
    if (!$data) return '';
    $generator = new Theme_Styles_CSS_Generator();
    return $generator->generate($data);
}
